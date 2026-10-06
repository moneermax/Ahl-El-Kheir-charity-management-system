<?php
declare(strict_types=1);

/**
 * GM salary-advance waiver approved-but-unpaid payroll protection
 * rollback-only verification.
 *
 * Creates a temporary approved payroll with a positive salary-advance
 * deduction for an eligible disbursed request. Waiver preparation and GM
 * approval are performed, then FM execution must abort before any financial
 * mutation because an approved unpaid payroll must be corrected through the
 * controlled payroll workflow first.
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_waiver.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_payroll.php';

$pdo = db();
ak_ensure_tables();
ak_seed_accounts();

function findApprovedPayrollFixture(PDO $pdo): ?array
{
    return dbFetchOne(
        "SELECT r.id AS request_id, r.request_no, r.employee_id,
                r.status, r.outstanding_balance,
                s.id AS schedule_id, s.scheduled_month,
                s.scheduled_amount, s.applied_amount, s.status AS schedule_status
         FROM hr_salary_advance_requests r
         JOIN hr_salary_advance_repayment_schedule s
           ON s.salary_advance_request_id = r.id
         WHERE r.status = 'disbursed'
           AND COALESCE(r.outstanding_balance, 0) > 0
           AND s.status IN ('pending','partial')
           AND NOT EXISTS (
               SELECT 1 FROM payroll p
               WHERE p.employee_id = r.employee_id
                 AND p.month = MONTH(s.scheduled_month)
                 AND p.year = YEAR(s.scheduled_month)
           )
           AND NOT EXISTS (
               SELECT 1
               FROM hr_salary_advance_waiver_items wi
               JOIN hr_salary_advance_waiver_decisions wd ON wd.id = wi.decision_id
               WHERE wi.salary_advance_request_id = r.id
                 AND wd.status = 'executed'
           )
         ORDER BY s.scheduled_month ASC, r.id ASC
         LIMIT 1"
    );
}

function findActiveUserByRole(PDO $pdo, array $roleCodes): ?int
{
    $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
    $row = dbFetchOne(
        "SELECT u.id FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.is_active = 1 AND r.code IN ($placeholders)
         ORDER BY u.id ASC LIMIT 1",
        $roleCodes
    );
    return $row ? (int)$row['id'] : null;
}

function findAccountByCode(PDO $pdo, string $code): ?int
{
    $row = dbFetchOne(
        "SELECT id FROM accounts WHERE code = ? AND is_active = 1 LIMIT 1",
        [$code]
    );
    return $row ? (int)$row['id'] : null;
}

function findActiveExpenseAccount(PDO $pdo): ?int
{
    $row = dbFetchOne(
        "SELECT id FROM accounts
         WHERE is_active = 1 AND account_type = 'expense'
         ORDER BY id ASC LIMIT 1"
    );
    return $row ? (int)$row['id'] : null;
}

$fixture = findApprovedPayrollFixture($pdo);
if (!$fixture) {
    echo "SKIP | No unused disbursed salary-advance schedule period without a payroll is available for approved-unpaid protection verification.\n";
    exit(0);
}

$gmUserId = findActiveUserByRole($pdo, ['general_manager','gm']);
$fmUserId = findActiveUserByRole($pdo, ['financial_manager','fm','finance']);
$refundAccountId = findAccountByCode($pdo, '1100');
$expenseAccountId = findActiveExpenseAccount($pdo);

if (!$gmUserId || !$fmUserId || !$refundAccountId || !$expenseAccountId) {
    echo "SKIP | Required active GM/FM or accounting accounts were not found.\n";
    exit(0);
}

$employeeId = (int)$fixture['employee_id'];
$effectiveMonth = (string)$fixture['scheduled_month'];
$month = (int)date('n', strtotime($effectiveMonth));
$year = (int)date('Y', strtotime($effectiveMonth));
$payrollId = 0;
$decisionId = 0;

$beforeRequest = null;
$beforeSchedule = null;

try {
    $pdo->beginTransaction();

    $beforeRequest = dbFetchOne(
        "SELECT status, outstanding_balance, closed_at
         FROM hr_salary_advance_requests WHERE id = ?",
        [$fixture['request_id']]
    );
    $beforeSchedule = dbFetchOne(
        "SELECT applied_amount, status
         FROM hr_salary_advance_repayment_schedule WHERE id = ?",
        [$fixture['schedule_id']]
    );

    $salaryRow = dbFetchOne(
        "SELECT basic_salary
         FROM hr_employee_salary_history
         WHERE employee_id = ?
           AND effective_from <= ?
           AND (effective_to IS NULL OR effective_to >= ?)
         ORDER BY effective_from DESC, id DESC
         LIMIT 1",
        [$employeeId, date('Y-m-t', strtotime($effectiveMonth)), $effectiveMonth]
    );
    $basicSalary = round((float)($salaryRow['basic_salary'] ?? 10000), 2);
    if ($basicSalary <= 0.00) $basicSalary = 10000.00;

    $maxPayrollId = (int)(dbFetchOne("SELECT COALESCE(MAX(id),0) AS max_id FROM payroll")['max_id'] ?? 0);
    $payrollId = $maxPayrollId + 1;

    $deduction = round(min(
        max(1.00, (float)$fixture['scheduled_amount']),
        max(1.00, $basicSalary / 2)
    ), 2);
    $netSalary = round(max(0.00, $basicSalary - $deduction), 2);

    $pdo->prepare(
        "INSERT INTO payroll
         (id, employee_id, month, year, basic_salary, allowances, overtime,
          deductions, salary_advance_deduction, net_salary, status)
         VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, 'approved')"
    )->execute([
        $payrollId,
        $employeeId,
        $month,
        $year,
        $basicSalary,
        0.00,
        $deduction,
        $netSalary
    ]);

    $beforePayroll = dbFetchOne(
        "SELECT status, salary_advance_deduction, net_salary, payment_date
         FROM payroll WHERE id = ?",
        [$payrollId]
    );

    if (
        !$beforePayroll ||
        $beforePayroll['status'] !== 'approved' ||
        round((float)$beforePayroll['salary_advance_deduction'], 2) !== $deduction
    ) {
        throw new RuntimeException('Temporary approved payroll fixture was not created with the expected positive deduction.');
    }

    $decisionId = hrSalaryAdvanceWaiverCreateDecision(
        $pdo,
        'individual',
        $effectiveMonth,
        'Rollback-only approved-unpaid payroll protection verification',
        $fmUserId,
        $employeeId,
        $refundAccountId,
        $expenseAccountId
    );

    hrSalaryAdvanceWaiverGMReview($pdo, $decisionId, $gmUserId, true);

    $executionFailed = false;
    $failureMessage = '';

    try {
        hrSalaryAdvanceWaiverExecute($pdo, $decisionId, $fmUserId);
    } catch (Throwable $e) {
        $executionFailed = true;
        $failureMessage = $e->getMessage();
    }

    if (!$executionFailed) {
        throw new RuntimeException('Waiver execution unexpectedly succeeded despite an approved unpaid payroll with a positive salary-advance deduction.');
    }

    $afterDecision = dbFetchOne(
        "SELECT status, executed_at
         FROM hr_salary_advance_waiver_decisions WHERE id = ?",
        [$decisionId]
    );
    $afterRequest = dbFetchOne(
        "SELECT status, outstanding_balance, closed_at
         FROM hr_salary_advance_requests WHERE id = ?",
        [$fixture['request_id']]
    );
    $afterSchedule = dbFetchOne(
        "SELECT applied_amount, status
         FROM hr_salary_advance_repayment_schedule WHERE id = ?",
        [$fixture['schedule_id']]
    );
    $afterPayroll = dbFetchOne(
        "SELECT status, salary_advance_deduction, net_salary, payment_date
         FROM payroll WHERE id = ?",
        [$payrollId]
    );

    $journalCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM journal_entries
         WHERE reference_type IN ('salary_advance_waiver','salary_advance_waiver_refund')
           AND reference_id = ?",
        [$decisionId]
    )['c'] ?? 0);

    $itemCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM hr_salary_advance_waiver_items WHERE decision_id = ?",
        [$decisionId]
    )['c'] ?? 0);

    if (
        !$afterDecision ||
        $afterDecision['status'] !== 'approved_by_gm' ||
        $afterDecision['executed_at'] !== null ||
        !$afterRequest ||
        $afterRequest['status'] !== $beforeRequest['status'] ||
        round((float)$afterRequest['outstanding_balance'], 2) !== round((float)$beforeRequest['outstanding_balance'], 2) ||
        $afterRequest['closed_at'] !== $beforeRequest['closed_at'] ||
        !$afterSchedule ||
        round((float)$afterSchedule['applied_amount'], 2) !== round((float)$beforeSchedule['applied_amount'], 2) ||
        $afterSchedule['status'] !== $beforeSchedule['status'] ||
        !$afterPayroll ||
        $afterPayroll['status'] !== 'approved' ||
        round((float)$afterPayroll['salary_advance_deduction'], 2) !== $deduction ||
        round((float)$afterPayroll['net_salary'], 2) !== $netSalary ||
        $afterPayroll['payment_date'] !== $beforePayroll['payment_date'] ||
        $journalCount !== 0 ||
        $itemCount <= 0
    ) {
        throw new RuntimeException(
            'Approved-unpaid protection detected a failure but did not preserve the pre-execution state: ' .
            'decision=' . ($afterDecision['status'] ?? 'missing') .
            ', request_balance=' . (float)($afterRequest['outstanding_balance'] ?? -1) .
            ', payroll_deduction=' . (float)($afterPayroll['salary_advance_deduction'] ?? -1) .
            ', journals=' . $journalCount .
            ', items=' . $itemCount
        );
    }

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Approved-unpaid protection unexpectedly ended the caller-owned transaction.');
    }

    echo "PASS | Approved-unpaid payroll protection | request={$fixture['request_no']} | payroll_id={$payrollId} | deduction={$deduction} | execution_blocked=1 | journals=0 | decision_status=approved_by_gm | error=" . $failureMessage . "\n";

    $pdo->rollBack();

    $payrollExists = dbFetchOne("SELECT id FROM payroll WHERE id = ?", [$payrollId]);
    $decisionRows = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions")['c'] ?? 0);
    $itemRows = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_items")['c'] ?? 0);
    $overlayRows = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_schedule_items")['c'] ?? 0);
    $waiverJournals = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM journal_entries
         WHERE reference_type IN ('salary_advance_waiver','salary_advance_waiver_refund')"
    )['c'] ?? 0);

    if ($payrollExists || $decisionRows !== 0 || $itemRows !== 0 || $overlayRows !== 0 || $waiverJournals !== 0) {
        throw new RuntimeException('Rollback-only approved-unpaid protection fixture did not clean up completely.');
    }

    echo "PASS | Rollback-only approved-unpaid cleanup | payroll_exists=0 | decision_rows={$decisionRows} | item_rows={$itemRows} | schedule_overlay_rows={$overlayRows} | waiver_journals={$waiverJournals}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "FAIL | GM waiver approved-unpaid payroll protection | {$e->getMessage()}\n");
    exit(1);
}
