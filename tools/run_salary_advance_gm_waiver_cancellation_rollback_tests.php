<?php
declare(strict_types=1);

/**
 * GM salary-advance waiver undistributed-request cancellation rollback-only test.
 *
 * Uses an existing qualifying submitted/fm_review/approved request, runs:
 * FM preparation -> GM approval -> FM execution, and rolls everything back.
 *
 * This proves that an undistributed request is cancelled with no accounting
 * journal and without inventing a new salary-advance request status.
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

function findUndistributedFixture(PDO $pdo): ?array
{
    return dbFetchOne(
        "SELECT r.id AS request_id, r.request_no, r.employee_id, r.status,
                r.outstanding_balance, r.closed_at
         FROM hr_salary_advance_requests r
         WHERE r.status IN ('submitted','fm_review','approved')
           AND r.closed_at IS NULL
           AND NOT EXISTS (
               SELECT 1
               FROM hr_salary_advance_waiver_items wi
               JOIN hr_salary_advance_waiver_decisions wd ON wd.id = wi.decision_id
               WHERE wi.salary_advance_request_id = r.id
                 AND wd.status = 'executed'
           )
         ORDER BY r.id ASC
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

$fixture = findUndistributedFixture($pdo);
if (!$fixture) {
    echo "SKIP | No qualifying undistributed salary-advance request is available for a rollback-only cancellation fixture.\n";
    exit(0);
}

$gmUserId = findActiveUserByRole($pdo, ['general_manager', 'gm']);
$fmUserId = findActiveUserByRole($pdo, ['financial_manager', 'fm', 'finance']);
$refundAccountId = findAccountByCode($pdo, '1100');
$expenseAccountId = findActiveExpenseAccount($pdo);

if (!$gmUserId || !$fmUserId || !$refundAccountId || !$expenseAccountId) {
    echo "SKIP | Required active GM/FM or accounting accounts were not found.\n";
    exit(0);
}

$effectiveMonth = date('Y-m-01');

$beforeRequest = dbFetchOne(
    "SELECT status, outstanding_balance, closed_at
     FROM hr_salary_advance_requests
     WHERE id = ?",
    [$fixture['request_id']]
);

$beforeDecisionCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions"
)['c'] ?? 0);
$beforeItemCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_items"
)['c'] ?? 0);
$beforeOverlayCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_schedule_items"
)['c'] ?? 0);
$beforeWaiverJournalCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c
     FROM journal_entries
     WHERE reference_type IN ('salary_advance_waiver','salary_advance_waiver_refund')"
)['c'] ?? 0);

$decisionId = 0;

try {
    $pdo->beginTransaction();

    $decisionId = hrSalaryAdvanceWaiverCreateDecision(
        $pdo,
        'individual',
        $effectiveMonth,
        'Rollback-only undistributed request cancellation verification',
        $fmUserId,
        (int)$fixture['employee_id'],
        $refundAccountId,
        $expenseAccountId
    );

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Waiver preparation unexpectedly ended the caller-owned transaction.');
    }

    $prepared = dbFetchOne(
        "SELECT status, decision_type, effective_month, refund_account_id,
                waiver_expense_account_id
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );
    $item = dbFetchOne(
        "SELECT previous_request_status, resulting_request_status,
                balance_before, current_period_repayment, refund_amount,
                balance_before_waiver, waived_amount, balance_after
         FROM hr_salary_advance_waiver_items
         WHERE decision_id = ? AND salary_advance_request_id = ?",
        [$decisionId, $fixture['request_id']]
    );

    if (
        !$prepared ||
        $prepared['status'] !== 'pending_gm' ||
        $prepared['decision_type'] !== 'individual' ||
        $prepared['effective_month'] !== $effectiveMonth ||
        (int)$prepared['refund_account_id'] !== $refundAccountId ||
        (int)$prepared['waiver_expense_account_id'] !== $expenseAccountId ||
        !$item ||
        $item['previous_request_status'] !== $fixture['status'] ||
        $item['resulting_request_status'] !== 'cancelled' ||
        round((float)$item['balance_before'], 2) !== round((float)($fixture['outstanding_balance'] ?? 0), 2) ||
        round((float)$item['current_period_repayment'], 2) !== 0.00 ||
        round((float)$item['refund_amount'], 2) !== 0.00 ||
        round((float)$item['balance_before_waiver'], 2) !== round((float)($fixture['outstanding_balance'] ?? 0), 2) ||
        round((float)$item['waived_amount'], 2) !== 0.00 ||
        round((float)$item['balance_after'], 2) !== 0.00
    ) {
        throw new RuntimeException('Undistributed waiver preparation snapshot is inconsistent.');
    }

    hrSalaryAdvanceWaiverGMReview($pdo, $decisionId, $gmUserId, true);

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('GM approval unexpectedly ended the caller-owned transaction.');
    }

    $execution = hrSalaryAdvanceWaiverExecute($pdo, $decisionId, $fmUserId);

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Waiver execution unexpectedly ended the caller-owned transaction.');
    }

    $decision = dbFetchOne(
        "SELECT status
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );
    $executedItem = dbFetchOne(
        "SELECT previous_request_status, resulting_request_status,
                refund_amount, waived_amount, refund_journal_entry_id,
                waiver_journal_entry_id, executed_at
         FROM hr_salary_advance_waiver_items
         WHERE decision_id = ? AND salary_advance_request_id = ?",
        [$decisionId, $fixture['request_id']]
    );
    $afterRequest = dbFetchOne(
        "SELECT status, outstanding_balance, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [$fixture['request_id']]
    );
    $decisionJournals = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM journal_entries
         WHERE reference_type IN ('salary_advance_waiver','salary_advance_waiver_refund')
           AND reference_id = ?",
        [$decisionId]
    )['c'] ?? 0);
    $decisionOverlays = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM hr_salary_advance_waiver_schedule_items wi
         JOIN hr_salary_advance_waiver_items i ON i.id = wi.waiver_item_id
         WHERE i.decision_id = ?",
        [$decisionId]
    )['c'] ?? 0);

    if (
        !$decision ||
        $decision['status'] !== 'executed' ||
        !$executedItem ||
        $executedItem['previous_request_status'] !== $fixture['status'] ||
        $executedItem['resulting_request_status'] !== 'cancelled' ||
        round((float)$executedItem['refund_amount'], 2) !== 0.00 ||
        round((float)$executedItem['waived_amount'], 2) !== 0.00 ||
        $executedItem['refund_journal_entry_id'] !== null ||
        $executedItem['waiver_journal_entry_id'] !== null ||
        empty($executedItem['executed_at']) ||
        !$afterRequest ||
        $afterRequest['status'] !== 'cancelled' ||
        round((float)$afterRequest['outstanding_balance'], 2) !== round((float)($beforeRequest['outstanding_balance'] ?? 0), 2) ||
        $afterRequest['closed_at'] === null ||
        $decisionJournals !== 0 ||
        $decisionOverlays !== 0 ||
        round((float)$execution['refund_total'], 2) !== 0.00 ||
        round((float)$execution['waiver_total'], 2) !== 0.00 ||
        $execution['refund_journal_entry_id'] !== null ||
        $execution['waiver_journal_entry_id'] !== null
    ) {
        throw new RuntimeException('Undistributed request cancellation produced an unexpected financial or lifecycle effect.');
    }

    echo "PASS | Undistributed request cancellation | request={$fixture['request_no']} | previous_status={$fixture['status']} | resulting_status=cancelled | journals=0 | overlays=0\n";

    $pdo->rollBack();

    $afterRollbackRequest = dbFetchOne(
        "SELECT status, outstanding_balance, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [$fixture['request_id']]
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
    $afterWaiverJournalCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM journal_entries
         WHERE reference_type IN ('salary_advance_waiver','salary_advance_waiver_refund')"
    )['c'] ?? 0);

    if (
        $afterDecisionCount !== $beforeDecisionCount ||
        $afterItemCount !== $beforeItemCount ||
        $afterOverlayCount !== $beforeOverlayCount ||
        $afterWaiverJournalCount !== $beforeWaiverJournalCount ||
        !$afterRollbackRequest ||
        $afterRollbackRequest['status'] !== $beforeRequest['status'] ||
        round((float)$afterRollbackRequest['outstanding_balance'], 2) !== round((float)$beforeRequest['outstanding_balance'], 2) ||
        $afterRollbackRequest['closed_at'] !== $beforeRequest['closed_at']
    ) {
        throw new RuntimeException('Rollback-only cancellation fixture did not restore all request and waiver state.');
    }

    echo "PASS | Rollback-only cancellation cleanup | request={$fixture['request_no']} | decision_rows={$afterDecisionCount} | item_rows={$afterItemCount} | schedule_overlay_rows={$afterOverlayCount} | waiver_journals={$afterWaiverJournalCount}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL | GM waiver undistributed cancellation rollback-only verification | {$e->getMessage()}\n");
    exit(1);
}
