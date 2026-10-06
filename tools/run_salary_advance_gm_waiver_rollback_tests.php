<?php
declare(strict_types=1);

/**
 * GM salary-advance waiver rollback-only verification.
 *
 * Uses an existing eligible disbursed request. All decision/review/execution
 * mutations run inside one outer transaction and are rolled back. This proves
 * the waiver service is composable with an existing transaction boundary and
 * exercises the no-refund execution path without leaving production data.
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_waiver.php';

$pdo = db();

function findWaiverFixture(PDO $pdo): ?array
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
           AND s.status IN ('pending', 'partial')
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
        "SELECT id
         FROM accounts
         WHERE is_active = 1
           AND account_type = 'expense'
         ORDER BY id ASC
         LIMIT 1"
    );
    return $row ? (int)$row['id'] : null;
}

$fixture = findWaiverFixture($pdo);
if (!$fixture) {
    echo "SKIP | No existing disbursed salary-advance waiver fixture is available.\n";
    exit(0);
}

$gmUserId = findActiveUserByRole($pdo, ['general_manager', 'gm']);
$fmUserId = findActiveUserByRole($pdo, ['financial_manager', 'fm', 'finance']);

if (!$gmUserId || !$fmUserId) {
    echo "SKIP | Required active GM/FM users were not found.\n";
    exit(0);
}

$refundAccountId = findAccountByCode($pdo, '1100');
$expenseAccountId = findActiveExpenseAccount($pdo);

if (!$refundAccountId || !$expenseAccountId) {
    echo "SKIP | Required active refund/expense accounts were not found.\n";
    exit(0);
}

$decisionId = 0;
$beforeRequest = dbFetchOne(
    "SELECT status, outstanding_balance, closed_at
     FROM hr_salary_advance_requests
     WHERE id = ?",
    [(int)$fixture['request_id']]
);
$beforeDecisionCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions"
)['c'] ?? 0);
$beforeItemCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_items"
)['c'] ?? 0);
$beforeScheduleItemCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_schedule_items"
)['c'] ?? 0);

try {
    $pdo->beginTransaction();

    $effectiveMonth = date('Y-m-01', strtotime((string)$fixture['scheduled_month']));

    $decisionId = hrSalaryAdvanceWaiverCreateDecision(
        $pdo,
        'individual',
        $effectiveMonth,
        'Rollback-only GM waiver verification',
        $fmUserId,
        (int)$fixture['employee_id'],
        $refundAccountId,
        $expenseAccountId
    );

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Create decision unexpectedly ended the caller-owned transaction.');
    }

    hrSalaryAdvanceWaiverGMReview(
        $pdo,
        $decisionId,
        $gmUserId,
        true
    );

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('GM review unexpectedly ended the caller-owned transaction.');
    }

    $execution = hrSalaryAdvanceWaiverExecute(
        $pdo,
        $decisionId,
        $fmUserId
    );

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Waiver execution unexpectedly ended the caller-owned transaction.');
    }

    $decision = dbFetchOne(
        "SELECT status, refund_account_id, waiver_expense_account_id
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );
    $item = dbFetchOne(
        "SELECT balance_before, current_period_repayment, refund_amount,
                waived_amount, balance_after, previous_request_status,
                resulting_request_status, refund_journal_entry_id,
                waiver_journal_entry_id
         FROM hr_salary_advance_waiver_items
         WHERE decision_id = ? AND salary_advance_request_id = ?",
        [$decisionId, (int)$fixture['request_id']]
    );
    $afterRequest = dbFetchOne(
        "SELECT status, outstanding_balance, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [(int)$fixture['request_id']]
    );
    $overlay = dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM hr_salary_advance_waiver_schedule_items
         WHERE waiver_item_id IN (
             SELECT id FROM hr_salary_advance_waiver_items WHERE decision_id = ?
         )",
        [$decisionId]
    );

    if (!$decision || $decision['status'] !== 'executed') {
        throw new RuntimeException('Expected rollback-only decision status executed.');
    }

    if (!$item) {
        throw new RuntimeException('Expected rollback-only waiver item was not created.');
    }

    if (round((float)$item['current_period_repayment'], 2) !== 0.00 ||
        round((float)$item['refund_amount'], 2) !== 0.00) {
        throw new RuntimeException('Fixture unexpectedly entered the refund branch.');
    }

    if (round((float)$item['balance_before'], 2) <= 0.00 ||
        round((float)$item['waived_amount'], 2) !== round((float)$item['balance_before'], 2) ||
        round((float)$item['balance_after'], 2) !== 0.00) {
        throw new RuntimeException('Remaining-balance waiver calculation is inconsistent.');
    }

    if ($item['previous_request_status'] !== 'disbursed' ||
        $item['resulting_request_status'] !== 'disbursed') {
        throw new RuntimeException('Disbursed request status was not preserved.');
    }

    if (!$item['waiver_journal_entry_id'] || $item['refund_journal_entry_id'] !== null) {
        throw new RuntimeException('Expected waiver journal only for the no-refund fixture.');
    }

    if (!$afterRequest || $afterRequest['status'] !== 'disbursed' ||
        round((float)$afterRequest['outstanding_balance'], 2) !== 0.00) {
        throw new RuntimeException('Executed rollback-only request state is incorrect.');
    }

    if ((int)$overlay['c'] < 1) {
        throw new RuntimeException('Expected at least one future schedule waiver overlay.');
    }

    $journal = dbFetchOne(
        "SELECT je.id,
                ROUND(COALESCE(SUM(jl.debit),0),2) AS debit_total,
                ROUND(COALESCE(SUM(jl.credit),0),2) AS credit_total,
                COUNT(jl.id) AS line_count
         FROM journal_entries je
         JOIN journal_lines jl ON jl.entry_id = je.id
         WHERE je.id = ?
         GROUP BY je.id",
        [(int)$item['waiver_journal_entry_id']]
    );

    if (!$journal ||
        round((float)$journal['debit_total'], 2) !== round((float)$item['waived_amount'], 2) ||
        round((float)$journal['credit_total'], 2) !== round((float)$item['waived_amount'], 2) ||
        (int)$journal['line_count'] !== 2) {
        throw new RuntimeException('Waiver journal is not balanced as expected.');
    }

    if ((int)$execution['refund_journal_entry_id'] !== 0) {
        throw new RuntimeException('Execution result unexpectedly reported a refund journal.');
    }

    if ((int)$execution['waiver_journal_entry_id'] !== (int)$item['waiver_journal_entry_id']) {
        throw new RuntimeException('Execution result waiver journal ID mismatch.');
    }

    echo "PASS | Transaction-composable GM waiver execution | request={$fixture['request_no']} | decision_id={$decisionId} | waived=" .
        number_format((float)$item['waived_amount'], 2, '.', '') .
        " | refund=0.00 | waiver_journal={$item['waiver_journal_entry_id']} | overlays=" .
        (int)$overlay['c'] . "\n";

    $pdo->rollBack();

    $afterRollbackRequest = dbFetchOne(
        "SELECT status, outstanding_balance, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [(int)$fixture['request_id']]
    );
    $afterDecisionCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions"
    )['c'] ?? 0);
    $afterItemCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_items"
    )['c'] ?? 0);
    $afterScheduleItemCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_schedule_items"
    )['c'] ?? 0);

    if (
        $afterDecisionCount !== $beforeDecisionCount ||
        $afterItemCount !== $beforeItemCount ||
        $afterScheduleItemCount !== $beforeScheduleItemCount
    ) {
        throw new RuntimeException('Rollback-only waiver rows were not fully removed.');
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

    echo "PASS | Rollback-only cleanup | request={$fixture['request_no']} | decision_rows={$afterDecisionCount} | item_rows={$afterItemCount} | schedule_overlay_rows={$afterScheduleItemCount}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | GM waiver rollback-only verification | " . $e->getMessage() . "\n";
    exit(1);
}
