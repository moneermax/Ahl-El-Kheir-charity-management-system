<?php
declare(strict_types=1);

/**
 * GM salary-advance waiver future-deduction blocking + draft refresh
 * rollback-only verification.
 *
 * Creates a temporary draft payroll for an unused scheduled month, proves the
 * salary-advance deduction is initially positive, then executes a real
 * rollback-only GM waiver. The waiver execution must refresh the draft and
 * remove the salary-advance deduction. The transaction is rolled back.
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

function findDraftRefreshFixture(PDO $pdo): ?array
{
    return dbFetchOne(
        "SELECT r.id AS request_id, r.request_no, r.employee_id,
                r.outstanding_balance,
                s.id AS schedule_id, s.scheduled_month, s.scheduled_amount,
                s.applied_amount, s.status AS schedule_status
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
        "SELECT id FROM accounts
         WHERE code = ? AND is_active = 1 LIMIT 1",
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

$fixture = findDraftRefreshFixture($pdo);
if (!$fixture) {
    echo "SKIP | No unused disbursed salary-advance schedule period without a payroll is available for draft-refresh verification.\n";
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

try {
    $pdo->beginTransaction();

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
    if ($basicSalary <= 0) $basicSalary = 10000.00;

    $maxPayrollId = (int)(dbFetchOne("SELECT COALESCE(MAX(id),0) AS max_id FROM payroll")['max_id'] ?? 0);
    $payrollId = $maxPayrollId + 1;

    $pdo->prepare(
        "INSERT INTO payroll
         (id, employee_id, month, year, basic_salary, allowances, overtime,
          deductions, salary_advance_deduction, net_salary, status)
         VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, ?, 'draft')"
    )->execute([$payrollId, $employeeId, $month, $year, $basicSalary, $basicSalary]);

    $payroll = dbFetchOne(
        "SELECT p.*, e.full_name AS employee_name, e.employee_code
         FROM payroll p JOIN employees e ON e.id = p.employee_id
         WHERE p.id = ?",
        [$payrollId]
    );

    hrSalaryAdvancePayrollRefreshDraft($pdo, $payrollId);

    $beforeWaiverPayroll = dbFetchOne(
        "SELECT status, salary_advance_deduction, net_salary
         FROM payroll WHERE id = ?",
        [$payrollId]
    );
    $expectedBefore = round(
        (float)$beforeWaiverPayroll['salary_advance_deduction'],
        2
    );

    if (!$beforeWaiverPayroll || $beforeWaiverPayroll['status'] !== 'draft' || $expectedBefore <= 0.00) {
        throw new RuntimeException('Temporary draft payroll did not receive the expected positive salary-advance deduction.');
    }

    $decisionId = hrSalaryAdvanceWaiverCreateDecision(
        $pdo,
        'individual',
        $effectiveMonth,
        'Rollback-only future deduction blocking and draft refresh verification',
        $fmUserId,
        $employeeId,
        $refundAccountId,
        $expenseAccountId
    );

    hrSalaryAdvanceWaiverGMReview($pdo, $decisionId, $gmUserId, true);
    $execution = hrSalaryAdvanceWaiverExecute($pdo, $decisionId, $fmUserId);

    $afterPayroll = dbFetchOne(
        "SELECT status, salary_advance_deduction, net_salary
         FROM payroll WHERE id = ?",
        [$payrollId]
    );

    $pendingRows = hrSalaryAdvancePayrollPendingRows(
        $pdo,
        $employeeId,
        $effectiveMonth
    );

    $decision = dbFetchOne(
        "SELECT status FROM hr_salary_advance_waiver_decisions WHERE id = ?",
        [$decisionId]
    );
    $request = dbFetchOne(
        "SELECT status, outstanding_balance
         FROM hr_salary_advance_requests WHERE id = ?",
        [$fixture['request_id']]
    );
    $overlays = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM hr_salary_advance_waiver_schedule_items wi
         JOIN hr_salary_advance_waiver_items i ON i.id = wi.waiver_item_id
         WHERE i.decision_id = ?",
        [$decisionId]
    )['c'] ?? 0);

    $expectedAfterNet = round($basicSalary, 2);

    if (
        !$afterPayroll ||
        $afterPayroll['status'] !== 'draft' ||
        round((float)$afterPayroll['salary_advance_deduction'], 2) !== 0.00 ||
        round((float)$afterPayroll['net_salary'], 2) !== $expectedAfterNet ||
        $pendingRows ||
        !$decision || $decision['status'] !== 'executed' ||
        !$request || $request['status'] !== 'disbursed' ||
        round((float)$request['outstanding_balance'], 2) !== 0.00 ||
        $overlays < 1 ||
        round((float)$execution['waiver_total'], 2) <= 0.00
    ) {
        throw new RuntimeException(
            'Waiver did not fully block the future deduction and refresh the draft payroll: ' .
            'before_deduction=' . $expectedBefore .
            ', after_deduction=' . (float)($afterPayroll['salary_advance_deduction'] ?? -1) .
            ', after_net=' . (float)($afterPayroll['net_salary'] ?? -1) .
            ', pending_rows=' . count($pendingRows) .
            ', overlays=' . $overlays
        );
    }

    echo "PASS | Future-deduction blocking + draft refresh | request={$fixture['request_no']} | payroll_id={$payrollId} | before_deduction={$expectedBefore} | after_deduction=0.00 | overlays={$overlays}\n";

    $pdo->rollBack();

    $afterRollbackPayroll = dbFetchOne(
        "SELECT id FROM payroll WHERE id = ?",
        [$payrollId]
    );
    $afterRollbackDecision = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions"
    )['c'] ?? 0);
    $afterRollbackItems = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_items"
    )['c'] ?? 0);
    $afterRollbackOverlays = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_schedule_items"
    )['c'] ?? 0);

    if (
        $afterRollbackPayroll ||
        $afterRollbackDecision !== 0 ||
        $afterRollbackItems !== 0 ||
        $afterRollbackOverlays !== 0
    ) {
        throw new RuntimeException('Rollback-only draft-refresh fixture did not clean up completely.');
    }

    echo "PASS | Rollback-only draft-refresh cleanup | payroll_id={$payrollId} | payroll_exists=0 | decision_rows={$afterRollbackDecision} | item_rows={$afterRollbackItems} | schedule_overlay_rows={$afterRollbackOverlays}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "FAIL | GM waiver future-deduction blocking + draft refresh | {$e->getMessage()}\n");
    exit(1);
}
