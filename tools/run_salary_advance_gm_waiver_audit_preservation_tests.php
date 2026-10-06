<?php
declare(strict_types=1);

/**
 * GM salary-advance waiver audit-preservation runtime verification.
 *
 * Uses an existing disbursed request with a historical paid payroll repayment,
 * executes a real individual waiver inside one outer transaction, snapshots
 * historical evidence before/after execution, then rolls the complete fixture
 * back. This proves that waiver execution adds separate accounting/audit
 * evidence without rewriting historical disbursement, payroll repayment,
 * payroll accounting or original schedule rows.
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_waiver.php';

$pdo = db();
ak_ensure_tables();
ak_seed_accounts();

function findAuditPreservationFixture(PDO $pdo): ?array
{
    return dbFetchOne(
        "SELECT r.id AS request_id, r.request_no, r.employee_id,
                r.status, r.outstanding_balance,
                pr.id AS repayment_id, pr.payroll_id,
                pr.actual_amount, pr.accounting_entry_id,
                p.year AS repayment_year, p.month AS repayment_month,
                p.status AS payroll_status
         FROM hr_salary_advance_requests r
         JOIN hr_salary_advance_payroll_repayments pr
           ON pr.salary_advance_request_id = r.id
         JOIN payroll p ON p.id = pr.payroll_id
         WHERE r.status = 'disbursed'
           AND COALESCE(r.outstanding_balance, 0) > 0
           AND p.status = 'paid'
           AND COALESCE(pr.actual_amount, 0) > 0
           AND pr.accounting_entry_id IS NOT NULL
           AND NOT EXISTS (
               SELECT 1
               FROM hr_salary_advance_waiver_items wi
               JOIN hr_salary_advance_waiver_decisions wd
                 ON wd.id = wi.decision_id
               WHERE wi.salary_advance_request_id = r.id
                 AND wd.status = 'executed'
           )
           AND NOT EXISTS (
               SELECT 1
               FROM payroll px
               WHERE px.employee_id = r.employee_id
                 AND px.year = p.year
                 AND px.month = p.month
                 AND px.status = 'approved'
                 AND COALESCE(px.salary_advance_deduction, 0) > 0
           )
         ORDER BY p.year ASC, p.month ASC, r.id ASC, pr.id ASC
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

function findActiveExpenseAccount(PDO $pdo): ?array
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

function fetchJournalEvidence(PDO $pdo, int $entryId): ?array
{
    $entry = dbFetchOne(
        "SELECT id, entry_code, entry_date, description, reference_type,
                reference_id, status, created_by
         FROM journal_entries
         WHERE id = ?",
        [$entryId]
    );
    if (!$entry) return null;

    $lines = dbFetchAll(
        "SELECT id, account_id, debit, credit, description
         FROM journal_lines
         WHERE entry_id = ?
         ORDER BY id ASC",
        [$entryId]
    );

    return [
        'entry' => $entry,
        'lines' => $lines,
    ];
}

function fetchRepaymentEvidence(PDO $pdo, int $repaymentId): ?array
{
    return dbFetchOne(
        "SELECT id, salary_advance_request_id, repayment_schedule_id,
                payroll_id, scheduled_amount, actual_amount, outcome,
                outcome_reason, accounting_entry_id, created_at
         FROM hr_salary_advance_payroll_repayments
         WHERE id = ?",
        [$repaymentId]
    );
}

function fetchScheduleEvidence(PDO $pdo, int $requestId): array
{
    return dbFetchAll(
        "SELECT id, installment_no, scheduled_month, scheduled_amount,
                applied_amount, status, applied_payroll_id, applied_at,
                skip_reason
         FROM hr_salary_advance_repayment_schedule
         WHERE salary_advance_request_id = ?
         ORDER BY id ASC",
        [$requestId]
    );
}

function fetchRequestAuditEvidence(PDO $pdo, int $requestId): array
{
    return dbFetchAll(
        "SELECT id, user_id, action, entity_type, entity_id,
                old_values, new_values, ip_address, user_agent
         FROM audit_log
         WHERE entity_type = 'hr_salary_advance_request'
           AND entity_id = ?
         ORDER BY id ASC",
        [$requestId]
    );
}

$fixture = findAuditPreservationFixture($pdo);
if (!$fixture) {
    echo "SKIP | No existing disbursed salary advance with a paid historical payroll repayment and unused waiver fixture was found.\n";
    exit(0);
}

$gmUserId = findActiveUserByRole($pdo, ['general_manager', 'gm']);
$fmUserId = findActiveUserByRole($pdo, ['financial_manager', 'fm', 'finance']);
$refundAccountId = findAccountByCode($pdo, '1100');
$expenseAccount = findActiveExpenseAccount($pdo);

if (!$gmUserId || !$fmUserId || !$refundAccountId || !$expenseAccount) {
    echo "SKIP | Required active GM/FM users or accounting accounts were not found.\n";
    exit(0);
}

$requestId = (int)$fixture['request_id'];
$repaymentId = (int)$fixture['repayment_id'];
$payrollId = (int)$fixture['payroll_id'];
$disbursementJournalId = (int)(dbFetchOne(
    "SELECT disbursement_journal_entry_id
     FROM hr_salary_advance_requests
     WHERE id = ?",
    [$requestId]
)['disbursement_journal_entry_id'] ?? 0);
$payrollJournalId = (int)$fixture['accounting_entry_id'];
$effectiveMonth = sprintf(
    '%04d-%02d-01',
    (int)$fixture['repayment_year'],
    (int)$fixture['repayment_month']
);

if ($disbursementJournalId <= 0 || $payrollJournalId <= 0) {
    echo "SKIP | Fixture does not have both original disbursement and payroll accounting journal references.\n";
    exit(0);
}

$beforeRequest = dbFetchOne(
    "SELECT id, request_no, employee_id, status, outstanding_balance,
            disbursement_journal_entry_id, closed_at
     FROM hr_salary_advance_requests
     WHERE id = ?",
    [$requestId]
);
$beforeDisbursementJournal = fetchJournalEvidence($pdo, $disbursementJournalId);
$beforePayrollJournal = fetchJournalEvidence($pdo, $payrollJournalId);
$beforeRepayment = fetchRepaymentEvidence($pdo, $repaymentId);
$beforeSchedule = fetchScheduleEvidence($pdo, $requestId);
$beforeAudit = fetchRequestAuditEvidence($pdo, $requestId);

if (!$beforeRequest || !$beforeDisbursementJournal || !$beforePayrollJournal ||
    !$beforeRepayment || !$beforeSchedule) {
    echo "SKIP | Required historical evidence could not be snapshotted completely.\n";
    exit(0);
}

$decisionId = 0;

try {
    $pdo->beginTransaction();

    $decisionId = hrSalaryAdvanceWaiverCreateDecision(
        $pdo,
        'individual',
        $effectiveMonth,
        'Rollback-only audit preservation verification',
        $fmUserId,
        (int)$fixture['employee_id'],
        $refundAccountId,
        (int)$expenseAccount['id']
    );

    hrSalaryAdvanceWaiverGMReview(
        $pdo,
        $decisionId,
        $gmUserId,
        true
    );

    $execution = hrSalaryAdvanceWaiverExecute(
        $pdo,
        $decisionId,
        $fmUserId
    );

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Audit-preservation fixture unexpectedly lost the caller-owned transaction.');
    }

    $afterRequest = dbFetchOne(
        "SELECT id, request_no, employee_id, status, outstanding_balance,
                disbursement_journal_entry_id, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [$requestId]
    );
    $afterDisbursementJournal = fetchJournalEvidence($pdo, $disbursementJournalId);
    $afterPayrollJournal = fetchJournalEvidence($pdo, $payrollJournalId);
    $afterRepayment = fetchRepaymentEvidence($pdo, $repaymentId);
    $afterSchedule = fetchScheduleEvidence($pdo, $requestId);
    $afterAudit = fetchRequestAuditEvidence($pdo, $requestId);

    $item = dbFetchOne(
        "SELECT id, decision_id, salary_advance_request_id, employee_id,
                balance_before, current_period_repayment, refund_amount,
                balance_before_waiver, waived_amount, balance_after,
                previous_request_status, resulting_request_status,
                refund_journal_entry_id, waiver_journal_entry_id, executed_at
         FROM hr_salary_advance_waiver_items
         WHERE decision_id = ? AND salary_advance_request_id = ?",
        [$decisionId, $requestId]
    );

    $decision = dbFetchOne(
        "SELECT id, decision_no, status, effective_month, reason,
                refund_account_id, waiver_expense_account_id,
                gm_approved_by, gm_approved_at, executed_at
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );

    $overlays = dbFetchAll(
        "SELECT id, waiver_item_id, repayment_schedule_id,
                previous_schedule_status, waived_at
         FROM hr_salary_advance_waiver_schedule_items
         WHERE waiver_item_id = ?
         ORDER BY id ASC",
        [(int)($item['id'] ?? 0)]
    );

    $waiverAudit = dbFetchAll(
        "SELECT id, user_id, action, entity_type, entity_id,
                old_values, new_values
         FROM audit_log
         WHERE entity_type = 'hr_salary_advance_waiver_decision'
           AND entity_id = ?
         ORDER BY id ASC",
        [$decisionId]
    );

    $requestWaiverAudits = dbFetchAll(
        "SELECT id, user_id, action, entity_type, entity_id,
                old_values, new_values
         FROM audit_log
         WHERE entity_type = 'hr_salary_advance_request'
           AND entity_id = ?
           AND action = 'HR_SALARY_ADVANCE_WAIVER_EXECUTED'
           AND new_values LIKE ?
         ORDER BY id ASC",
        [$requestId, '%' . $decisionId . '%']
    );

    if ($afterRequest['disbursement_journal_entry_id'] != $beforeRequest['disbursement_journal_entry_id']) {
        throw new RuntimeException('Original disbursement journal reference on the request was rewritten.');
    }

    if ($afterDisbursementJournal !== $beforeDisbursementJournal) {
        throw new RuntimeException('Original salary-advance disbursement journal or lines changed.');
    }

    if ($afterPayrollJournal !== $beforePayrollJournal) {
        throw new RuntimeException('Original payroll accounting journal or lines changed.');
    }

    if ($afterRepayment !== $beforeRepayment) {
        throw new RuntimeException('Historical payroll repayment row changed.');
    }

    if ($afterSchedule !== $beforeSchedule) {
        throw new RuntimeException('Original repayment schedule rows changed.');
    }

    $historicalAuditMap = [];
    foreach ($beforeAudit as $row) {
        $historicalAuditMap[(int)$row['id']] = $row;
    }
    foreach ($afterAudit as $row) {
        $id = (int)$row['id'];
        if (isset($historicalAuditMap[$id]) && $row !== $historicalAuditMap[$id]) {
            throw new RuntimeException('Historical audit row id=' . $id . ' was rewritten.');
        }
    }
    foreach ($historicalAuditMap as $id => $row) {
        $found = false;
        foreach ($afterAudit as $candidate) {
            if ((int)$candidate['id'] === $id) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new RuntimeException('Historical audit row id=' . $id . ' disappeared.');
        }
    }

    if (!$decision || $decision['status'] !== 'executed' || !$item) {
        throw new RuntimeException('Executed waiver decision/item evidence is incomplete.');
    }

    if (
        (int)$item['decision_id'] !== $decisionId ||
        (int)$item['salary_advance_request_id'] !== $requestId ||
        $item['previous_request_status'] !== 'disbursed' ||
        $item['resulting_request_status'] !== 'disbursed' ||
        round((float)$item['balance_after'], 2) !== 0.00 ||
        (int)$item['waiver_journal_entry_id'] <= 0
    ) {
        throw new RuntimeException('Waiver item evidence is incomplete or inconsistent.');
    }

    if (!$overlays) {
        throw new RuntimeException('Executed waiver did not create schedule-overlay evidence.');
    }

    $newAuditCount = count($afterAudit) - count($beforeAudit);
    if (count($waiverAudit) < 1 || count($requestWaiverAudits) < 1 || $newAuditCount < 1) {
        throw new RuntimeException('Waiver execution audit evidence is incomplete.');
    }

    $waiverJournal = fetchJournalEvidence(
        $pdo,
        (int)$item['waiver_journal_entry_id']
    );
    if (!$waiverJournal ||
        $waiverJournal['entry']['reference_type'] !== 'salary_advance_waiver') {
        throw new RuntimeException('Separate waiver journal evidence is missing.');
    }

    $refundJournalId = (int)($item['refund_journal_entry_id'] ?? 0);
    if ((float)$item['refund_amount'] > 0.00) {
        if ($refundJournalId <= 0) {
            throw new RuntimeException('A paid payroll deduction existed but no separate refund journal was recorded.');
        }
        $refundJournal = fetchJournalEvidence($pdo, $refundJournalId);
        if (!$refundJournal ||
            $refundJournal['entry']['reference_type'] !== 'salary_advance_waiver_refund') {
            throw new RuntimeException('Separate refund journal evidence is missing or uses the wrong reference type.');
        }
    }

    echo "PASS | Audit preservation | request={$fixture['request_no']} | decision_id={$decisionId} | disbursement_journal={$disbursementJournalId} | payroll_id={$payrollId} | payroll_journal={$payrollJournalId} | repayment_id={$repaymentId} | historical_audit_rows_preserved=" . count($beforeAudit) . " | waiver_audit_rows=" . count($waiverAudit) . " | schedule_overlays=" . count($overlays) . "\n";

    $pdo->rollBack();

    $finalRequest = dbFetchOne(
        "SELECT id, request_no, employee_id, status, outstanding_balance,
                disbursement_journal_entry_id, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [$requestId]
    );
    $finalDisbursementJournal = fetchJournalEvidence($pdo, $disbursementJournalId);
    $finalPayrollJournal = fetchJournalEvidence($pdo, $payrollJournalId);
    $finalRepayment = fetchRepaymentEvidence($pdo, $repaymentId);
    $finalSchedule = fetchScheduleEvidence($pdo, $requestId);
    $finalAudit = fetchRequestAuditEvidence($pdo, $requestId);

    if (
        $finalRequest !== $beforeRequest ||
        $finalDisbursementJournal !== $beforeDisbursementJournal ||
        $finalPayrollJournal !== $beforePayrollJournal ||
        $finalRepayment !== $beforeRepayment ||
        $finalSchedule !== $beforeSchedule ||
        $finalAudit !== $beforeAudit
    ) {
        throw new RuntimeException('Audit-preservation rollback did not restore the complete pre-test evidence set.');
    }

    $remainingDecision = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions WHERE id = ?",
        [$decisionId]
    )['c'] ?? 0);

    if ($remainingDecision !== 0) {
        throw new RuntimeException('Audit-preservation rollback left the test waiver decision behind.');
    }

    echo "PASS | Audit preservation cleanup | request={$fixture['request_no']} | decision_rows=0 | historical_journals_restored=1 | repayment_restored=1 | schedules_restored=1 | audit_restored=1\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL | GM waiver audit preservation | " . $e->getMessage() . "\n");
    exit(1);
}
