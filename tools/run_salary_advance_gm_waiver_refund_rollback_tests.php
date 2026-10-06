<?php
declare(strict_types=1);

/**
 * GM salary-advance waiver refund-branch rollback-only verification.
 *
 * Builds a temporary paid payroll inside one outer transaction using the
 * existing Stage 5 payroll/accounting functions, then runs:
 *   paid repayment -> FM preparation -> GM approval -> FM execution.
 *
 * Everything is rolled back. This verifies the real refund branch without
 * consuming a production payroll period or leaving accounting mutations.
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_waiver.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_payroll.php';
require_once __DIR__ . '/../modules/hr/lib_payroll_accounting.php';

$pdo = db();
ak_ensure_tables();
ak_seed_accounts();

function findRefundFixture(PDO $pdo): ?array
{
    return dbFetchOne(
        "SELECT r.id AS request_id, r.request_no, r.employee_id,
                r.status, r.outstanding_balance,
                s.id AS schedule_id, s.scheduled_month,
                s.scheduled_amount, s.applied_amount, s.status AS schedule_status,
                p.insufficient_salary_rule, p.eligible_salary_basis,
                r.approved_repayment_method
         FROM hr_salary_advance_requests r
         JOIN hr_salary_advance_repayment_schedule s
           ON s.salary_advance_request_id = r.id
         JOIN hr_salary_advance_policy_versions p
           ON p.id = r.policy_version_id
         WHERE r.status = 'disbursed'
           AND COALESCE(r.outstanding_balance, 0) > 0
           AND s.status IN ('pending', 'partial')
           AND NOT EXISTS (
               SELECT 1
               FROM hr_salary_advance_payroll_repayments pr
               WHERE pr.salary_advance_request_id = r.id
                 AND pr.payroll_id IN (
                     SELECT p2.id
                     FROM payroll p2
                     WHERE p2.employee_id = r.employee_id
                       AND p2.month = MONTH(s.scheduled_month)
                       AND p2.year = YEAR(s.scheduled_month)
                 )
           )
           AND NOT EXISTS (
               SELECT 1
               FROM payroll px
               WHERE px.employee_id = r.employee_id
                 AND px.month = MONTH(s.scheduled_month)
                 AND px.year = YEAR(s.scheduled_month)
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
        "SELECT u.id
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.is_active = 1
           AND r.code IN ($placeholders)
         ORDER BY u.id ASC
         LIMIT 1",
        $roleCodes
    );
    return $row ? (int)$row['id'] : null;
}

function findAccountByCode(PDO $pdo, string $code): ?int
{
    $row = dbFetchOne(
        "SELECT id
         FROM accounts
         WHERE code = ? AND is_active = 1
         LIMIT 1",
        [$code]
    );
    return $row ? (int)$row['id'] : null;
}

function findActiveExpenseAccount(PDO $pdo): ?int
{
    $row = dbFetchOne(
        "SELECT id, code
         FROM accounts
         WHERE is_active = 1
           AND account_type = 'expense'
         ORDER BY id ASC
         LIMIT 1"
    );
    return $row ? ['id' => (int)$row['id'], 'code' => (string)$row['code']] : null;
}

$fixture = findRefundFixture($pdo);
if (!$fixture) {
    echo "SKIP | No unused disbursed salary-advance schedule period is available for a rollback-only refund fixture.\n";
    exit(0);
}

$gmUserId = findActiveUserByRole($pdo, ['general_manager', 'gm']);
$fmUserId = findActiveUserByRole($pdo, ['financial_manager', 'fm', 'finance']);

if (!$gmUserId || !$fmUserId) {
    echo "SKIP | Required active GM/FM users were not found.\n";
    exit(0);
}

$refundAccountId = findAccountByCode($pdo, '1100');
$expenseAccount = findActiveExpenseAccount($pdo);

if (!$refundAccountId || !$expenseAccount) {
    echo "SKIP | Required active refund/expense accounts were not found.\n";
    exit(0);
}

$employeeId = (int)$fixture['employee_id'];
$month = (int)date('n', strtotime((string)$fixture['scheduled_month']));
$year = (int)date('Y', strtotime((string)$fixture['scheduled_month']));
$effectiveMonth = date('Y-m-01', strtotime((string)$fixture['scheduled_month']));

$beforeRequest = dbFetchOne(
    "SELECT status, outstanding_balance, closed_at
     FROM hr_salary_advance_requests
     WHERE id = ?",
    [$fixture['request_id']]
);

$beforeSchedule = dbFetchOne(
    "SELECT applied_amount, status
     FROM hr_salary_advance_repayment_schedule
     WHERE id = ?",
    [$fixture['schedule_id']]
);

$beforeWaiverDecisionCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions"
)['c'] ?? 0);
$beforeWaiverItemCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_items"
)['c'] ?? 0);
$beforeOverlayCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_schedule_items"
)['c'] ?? 0);
$beforePayrollCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM payroll"
)['c'] ?? 0);
$beforeRepaymentCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_payroll_repayments"
)['c'] ?? 0);
$beforeWaiverJournalCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c
     FROM journal_entries
     WHERE reference_type IN ('salary_advance_waiver', 'salary_advance_waiver_refund')"
)['c'] ?? 0);

$payrollId = 0;
$payrollJournalId = 0;
$decisionId = 0;

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
        [
            $employeeId,
            date('Y-m-t', strtotime($effectiveMonth)),
            $effectiveMonth
        ]
    );
    $basicSalary = round((float)($salaryRow['basic_salary'] ?? 10000.00), 2);
    if ($basicSalary <= 0.00) $basicSalary = 10000.00;

    $maxPayrollId = (int)(dbFetchOne(
        "SELECT COALESCE(MAX(id), 0) AS max_id FROM payroll"
    )['max_id'] ?? 0);
    $maxPayrollRef = (int)(dbFetchOne(
        "SELECT COALESCE(MAX(reference_id), 0) AS max_id
         FROM journal_entries
         WHERE reference_type = 'payroll'"
    )['max_id'] ?? 0);

    $payrollId = max($maxPayrollId, $maxPayrollRef) + 1;
    if ($payrollId <= 0 || $payrollId > 2147483647) {
        throw new RuntimeException('تعذر تخصيص رقم مسير مؤقت آمن لاختبار مسار رد الخصم.');
    }

    while (dbFetchOne(
        "SELECT id
         FROM journal_entries
         WHERE reference_type = 'payroll' AND reference_id = ?
         LIMIT 1",
        [$payrollId]
    )) {
        $payrollId++;
        if ($payrollId >= 2147483647) {
            throw new RuntimeException('تعذر العثور على رقم مسير مؤقت غير مستخدم.');
        }
    }

    $pdo->prepare(
        "INSERT INTO payroll
         (id, employee_id, month, year, basic_salary, allowances, overtime,
          deductions, salary_advance_deduction, net_salary, status)
         VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, ?, 'approved')"
    )->execute([
        $payrollId,
        $employeeId,
        $month,
        $year,
        $basicSalary,
        $basicSalary
    ]);

    $payroll = dbFetchOne(
        "SELECT p.*, e.full_name AS employee_name, e.employee_code
         FROM payroll p
         JOIN employees e ON e.id = p.employee_id
         WHERE p.id = ?",
        [$payrollId]
    );

    $preview = hrSalaryAdvancePayrollCalculateDraft(
        $pdo,
        $payroll,
        $effectiveMonth
    );
    $deduction = round((float)$preview['total'], 2);

    if ($deduction <= 0.00) {
        throw new RuntimeException('Temporary payroll did not produce a positive salary-advance deduction.');
    }

    $netSalary = round(max(0.00, $basicSalary - $deduction), 2);

    $pdo->prepare(
        "UPDATE payroll
         SET salary_advance_deduction = ?, net_salary = ?, status = 'paid', payment_date = CURRENT_DATE
         WHERE id = ?"
    )->execute([$deduction, $netSalary, $payrollId]);

    $payroll['status'] = 'paid';
    $payroll['payment_date'] = date('Y-m-d');
    $payroll['salary_advance_deduction'] = $deduction;
    $payroll['net_salary'] = $netSalary;
    $payroll['payment_account_id'] = null;

    if (dbFetchOne(
        "SELECT id
         FROM journal_entries
         WHERE reference_type = 'payroll' AND reference_id = ?
         LIMIT 1",
        [$payrollId]
    )) {
        throw new RuntimeException('Temporary payroll journal reference already exists before accounting.');
    }

    $payrollJournalId = hrPayrollPostAccounting($pdo, $payroll);
    $repaymentResult = hrSalaryAdvancePayrollApply($pdo, $payroll, $payrollJournalId);

    $repayment = dbFetchOne(
        "SELECT id, salary_advance_request_id, repayment_schedule_id,
                payroll_id, actual_amount, outcome, accounting_entry_id
         FROM hr_salary_advance_payroll_repayments
         WHERE payroll_id = ? AND salary_advance_request_id = ?
         ORDER BY id DESC
         LIMIT 1",
        [$payrollId, $fixture['request_id']]
    );

    $requestAfterRepayment = dbFetchOne(
        "SELECT outstanding_balance, status, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [$fixture['request_id']]
    );

    if (!$repayment ||
        (int)$repayment['payroll_id'] !== $payrollId ||
        (int)$repayment['salary_advance_request_id'] !== (int)$fixture['request_id'] ||
        round((float)$repayment['actual_amount'], 2) !== $deduction ||
        $repayment['outcome'] !== 'applied' ||
        (int)$repayment['accounting_entry_id'] !== $payrollJournalId) {
        throw new RuntimeException('Temporary paid payroll repayment evidence is inconsistent.');
    }

    if (round((float)$requestAfterRepayment['outstanding_balance'], 2) >=
        round((float)$beforeRequest['outstanding_balance'], 2)) {
        throw new RuntimeException('Temporary payroll repayment did not reduce the salary-advance balance.');
    }

    $decisionId = hrSalaryAdvanceWaiverCreateDecision(
        $pdo,
        'individual',
        $effectiveMonth,
        'Rollback-only paid-deduction refund verification',
        $fmUserId,
        $employeeId,
        $refundAccountId,
        (int)$expenseAccount['id']
    );

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Waiver preparation unexpectedly ended the caller-owned transaction.');
    }

    $preparedItem = dbFetchOne(
        "SELECT balance_before, current_period_repayment, refund_amount,
                balance_before_waiver, waived_amount, balance_after,
                previous_request_status, resulting_request_status
         FROM hr_salary_advance_waiver_items
         WHERE decision_id = ? AND salary_advance_request_id = ?",
        [$decisionId, $fixture['request_id']]
    );

    if (!$preparedItem ||
        round((float)$preparedItem['current_period_repayment'], 2) !== $deduction ||
        round((float)$preparedItem['refund_amount'], 2) !== 0.00 ||
        round((float)$preparedItem['balance_before_waiver'], 2) !==
            round((float)$preparedItem['balance_before'], 2)) {
        throw new RuntimeException('Waiver preparation did not capture the paid repayment snapshot correctly.');
    }

    hrSalaryAdvanceWaiverGMReview(
        $pdo,
        $decisionId,
        $gmUserId,
        true
    );

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('GM approval unexpectedly ended the caller-owned transaction.');
    }

    $execution = hrSalaryAdvanceWaiverExecute(
        $pdo,
        $decisionId,
        $fmUserId
    );

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Waiver execution unexpectedly ended the caller-owned transaction.');
    }

    $item = dbFetchOne(
        "SELECT balance_before, current_period_repayment, refund_amount,
                balance_before_waiver, waived_amount, balance_after,
                previous_request_status, resulting_request_status,
                refund_account_id, refund_journal_entry_id,
                waiver_expense_account_id, waiver_journal_entry_id
         FROM hr_salary_advance_waiver_items
         WHERE decision_id = ? AND salary_advance_request_id = ?",
        [$decisionId, $fixture['request_id']]
    );

    $decision = dbFetchOne(
        "SELECT status, refund_account_id, waiver_expense_account_id
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );

    $afterRequest = dbFetchOne(
        "SELECT outstanding_balance, status, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [$fixture['request_id']]
    );

    $afterRepayment = dbFetchOne(
        "SELECT id, actual_amount, outcome, accounting_entry_id
         FROM hr_salary_advance_payroll_repayments
         WHERE id = ?",
        [$repayment['id']]
    );

    if (!$decision || $decision['status'] !== 'executed' || !$item) {
        throw new RuntimeException('Refund-branch waiver did not reach executed state.');
    }

    $expectedOriginalBalance = round(
        (float)$beforeRequest['outstanding_balance'],
        2
    );
    $expectedRemainingAfterRepayment = round(
        $expectedOriginalBalance - $deduction,
        2
    );

    if (
        round((float)$item['balance_before'], 2) !== $expectedRemainingAfterRepayment ||
        round((float)$item['current_period_repayment'], 2) !== $deduction ||
        round((float)$item['refund_amount'], 2) !== $deduction ||
        round((float)$item['balance_before_waiver'], 2) !== $expectedOriginalBalance ||
        round((float)$item['waived_amount'], 2) !== $expectedOriginalBalance ||
        round((float)$item['balance_after'], 2) !== 0.00
    ) {
        throw new RuntimeException(
            'Refund/waiver amounts are inconsistent: original=' . $expectedOriginalBalance .
            ', deduction=' . $deduction .
            ', balance_before=' . (float)$item['balance_before'] .
            ', refund=' . (float)$item['refund_amount'] .
            ', waiver=' . (float)$item['waived_amount']
        );
    }

    if (
        $item['previous_request_status'] !== 'disbursed' ||
        $item['resulting_request_status'] !== 'disbursed' ||
        (int)$item['refund_account_id'] !== $refundAccountId ||
        (int)$item['waiver_expense_account_id'] !== (int)$expenseAccount['id'] ||
        !$item['refund_journal_entry_id'] ||
        !$item['waiver_journal_entry_id'] ||
        (int)$item['refund_journal_entry_id'] === (int)$item['waiver_journal_entry_id']
    ) {
        throw new RuntimeException('Refund/waiver journal linkage or request status is incorrect.');
    }

    if (
        !$afterRequest ||
        $afterRequest['status'] !== 'disbursed' ||
        round((float)$afterRequest['outstanding_balance'], 2) !== 0.00 ||
        $afterRequest['closed_at'] !== $beforeRequest['closed_at']
    ) {
        throw new RuntimeException('Final request state after refund-branch execution is incorrect.');
    }

    if (
        !$afterRepayment ||
        round((float)$afterRepayment['actual_amount'], 2) !== $deduction ||
        $afterRepayment['outcome'] !== 'applied' ||
        (int)$afterRepayment['accounting_entry_id'] !== $payrollJournalId
    ) {
        throw new RuntimeException('Historical paid repayment row was altered by waiver execution.');
    }

    $refundJournal = dbFetchOne(
        "SELECT je.id, je.reference_type,
                ROUND(COALESCE(SUM(jl.debit),0),2) AS debit_total,
                ROUND(COALESCE(SUM(jl.credit),0),2) AS credit_total,
                COUNT(jl.id) AS line_count,
                MAX(CASE WHEN a.code = '1410' THEN jl.debit ELSE 0 END) AS receivable_debit,
                MAX(CASE WHEN a.code = '1100' THEN jl.credit ELSE 0 END) AS source_credit
         FROM journal_entries je
         JOIN journal_lines jl ON jl.entry_id = je.id
         JOIN accounts a ON a.id = jl.account_id
         WHERE je.id = ?
         GROUP BY je.id, je.reference_type",
        [(int)$item['refund_journal_entry_id']]
    );

    $waiverJournal = dbFetchOne(
        "SELECT je.id, je.reference_type,
                ROUND(COALESCE(SUM(jl.debit),0),2) AS debit_total,
                ROUND(COALESCE(SUM(jl.credit),0),2) AS credit_total,
                COUNT(jl.id) AS line_count,
                MAX(CASE WHEN a.id = ? THEN jl.debit ELSE 0 END) AS expense_debit,
                MAX(CASE WHEN a.code = '1410' THEN jl.credit ELSE 0 END) AS receivable_credit
         FROM journal_entries je
         JOIN journal_lines jl ON jl.entry_id = je.id
         JOIN accounts a ON a.id = jl.account_id
         WHERE je.id = ?
         GROUP BY je.id, je.reference_type",
        [(int)$expenseAccount['id'], (int)$item['waiver_journal_entry_id']]
    );

    if (
        !$refundJournal ||
        $refundJournal['reference_type'] !== 'salary_advance_waiver_refund' ||
        round((float)$refundJournal['debit_total'], 2) !== $deduction ||
        round((float)$refundJournal['credit_total'], 2) !== $deduction ||
        (int)$refundJournal['line_count'] !== 2 ||
        round((float)$refundJournal['receivable_debit'], 2) !== $deduction ||
        round((float)$refundJournal['source_credit'], 2) !== $deduction
    ) {
        throw new RuntimeException('Refund journal does not match Dr 1410 / Cr 1100 for the paid deduction.');
    }

    if (
        !$waiverJournal ||
        $waiverJournal['reference_type'] !== 'salary_advance_waiver' ||
        round((float)$waiverJournal['debit_total'], 2) !== $expectedOriginalBalance ||
        round((float)$waiverJournal['credit_total'], 2) !== $expectedOriginalBalance ||
        (int)$waiverJournal['line_count'] !== 2 ||
        round((float)$waiverJournal['expense_debit'], 2) !== $expectedOriginalBalance ||
        round((float)$waiverJournal['receivable_credit'], 2) !== $expectedOriginalBalance
    ) {
        throw new RuntimeException('Waiver journal does not match Dr expense / Cr 1410 for the restored balance.');
    }

    if (
        round((float)$execution['refund_total'], 2) !== $deduction ||
        round((float)$execution['waiver_total'], 2) !== $expectedOriginalBalance ||
        (int)$execution['refund_journal_entry_id'] !== (int)$item['refund_journal_entry_id'] ||
        (int)$execution['waiver_journal_entry_id'] !== (int)$item['waiver_journal_entry_id']
    ) {
        throw new RuntimeException('Execution result does not match the refund-branch accounting records.');
    }

    echo "PASS | Paid-deduction refund branch | request={$fixture['request_no']} | payroll_id={$payrollId} | deduction=" .
        number_format($deduction, 2, '.', '') .
        " | refund=" . number_format((float)$item['refund_amount'], 2, '.', '') .
        " | waived=" . number_format((float)$item['waived_amount'], 2, '.', '') .
        " | refund_journal={$item['refund_journal_entry_id']} | waiver_journal={$item['waiver_journal_entry_id']}\n";

    $pdo->rollBack();

    $afterRollbackRequest = dbFetchOne(
        "SELECT status, outstanding_balance, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [$fixture['request_id']]
    );
    $afterRollbackSchedule = dbFetchOne(
        "SELECT applied_amount, status
         FROM hr_salary_advance_repayment_schedule
         WHERE id = ?",
        [$fixture['schedule_id']]
    );
    $afterDecisionCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions"
    )['c'] ?? 0);
    $afterItemCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_items"
    )['c'] ?? 0);
    $afterOverlayCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_schedule_items"
    )['c'] ?? 0);
    $afterPayrollCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM payroll"
    )['c'] ?? 0);
    $afterRepaymentCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_payroll_repayments"
    )['c'] ?? 0);
    $afterWaiverJournalCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM journal_entries
         WHERE reference_type IN ('salary_advance_waiver', 'salary_advance_waiver_refund')"
    )['c'] ?? 0);

    if (
        $afterDecisionCount !== $beforeWaiverDecisionCount ||
        $afterItemCount !== $beforeWaiverItemCount ||
        $afterOverlayCount !== $beforeOverlayCount ||
        $afterPayrollCount !== $beforePayrollCount ||
        $afterRepaymentCount !== $beforeRepaymentCount ||
        $afterWaiverJournalCount !== $beforeWaiverJournalCount
    ) {
        throw new RuntimeException('Rollback-only refund fixture did not fully clean all mutations.');
    }

    if (
        !$afterRollbackRequest ||
        $afterRollbackRequest['status'] !== $beforeRequest['status'] ||
        round((float)$afterRollbackRequest['outstanding_balance'], 2) !==
            round((float)$beforeRequest['outstanding_balance'], 2) ||
        $afterRollbackRequest['closed_at'] !== $beforeRequest['closed_at']
    ) {
        throw new RuntimeException('Rollback-only request state was not restored.');
    }

    if (
        !$afterRollbackSchedule ||
        round((float)$afterRollbackSchedule['applied_amount'], 2) !==
            round((float)$beforeSchedule['applied_amount'], 2) ||
        $afterRollbackSchedule['status'] !== $beforeSchedule['status']
    ) {
        throw new RuntimeException('Rollback-only schedule state was not restored.');
    }

    echo "PASS | Rollback-only refund cleanup | request={$fixture['request_no']} | payroll_rows={$afterPayrollCount} | repayment_rows={$afterRepaymentCount} | decision_rows={$afterDecisionCount} | waiver_journals={$afterWaiverJournalCount}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL | GM waiver refund rollback-only verification | " . $e->getMessage() . "\n");
    exit(1);
}
