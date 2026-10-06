<?php
declare(strict_types=1);

/**
 * GM salary-advance waiver preparation/rejection rollback-only verification.
 *
 * Uses an existing eligible disbursed request. Preparation and GM rejection
 * run inside one outer transaction and are rolled back. This proves:
 * - FM preparation creates pending_gm decision/item records only;
 * - selected accounts and effective month are persisted;
 * - no journal is posted during preparation or rejection;
 * - GM rejection requires/stores a rejection reason;
 * - the caller-owned transaction remains active;
 * - rollback restores all waiver rows and the request state.
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_waiver.php';

$pdo = db();

function findWaiverPreparationFixture(PDO $pdo): ?array
{
    return dbFetchOne(
        "SELECT r.id AS request_id, r.request_no, r.employee_id,
                r.status, r.outstanding_balance,
                s.id AS schedule_id, s.scheduled_month
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

$fixture = findWaiverPreparationFixture($pdo);
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
$beforeJournalCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c
     FROM journal_entries
     WHERE reference_type IN ('salary_advance_waiver', 'salary_advance_waiver_refund')"
)['c'] ?? 0);

$decisionId = 0;

try {
    $pdo->beginTransaction();

    $effectiveMonth = date('Y-m-01', strtotime((string)$fixture['scheduled_month']));

    $decisionId = hrSalaryAdvanceWaiverCreateDecision(
        $pdo,
        'individual',
        $effectiveMonth,
        'Rollback-only GM waiver preparation/rejection verification',
        $fmUserId,
        (int)$fixture['employee_id'],
        $refundAccountId,
        $expenseAccountId
    );

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Create decision unexpectedly ended the caller-owned transaction.');
    }

    $pending = dbFetchOne(
        "SELECT decision_no, decision_type, effective_month, reason, status,
                created_by, prepared_by, prepared_at,
                refund_account_id, waiver_expense_account_id,
                gm_approved_by, gm_approved_at, gm_rejection_reason
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );

    $pendingItem = dbFetchOne(
        "SELECT salary_advance_request_id, employee_id,
                balance_before, current_period_repayment, refund_amount,
                balance_before_waiver, waived_amount, balance_after,
                previous_request_status, resulting_request_status,
                refund_journal_entry_id, waiver_journal_entry_id, executed_at
         FROM hr_salary_advance_waiver_items
         WHERE decision_id = ? AND salary_advance_request_id = ?",
        [$decisionId, (int)$fixture['request_id']]
    );

    $journalCountAfterPreparation = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM journal_entries
         WHERE reference_type IN ('salary_advance_waiver', 'salary_advance_waiver_refund')"
    )['c'] ?? 0);

    if (!$pending ||
        $pending['status'] !== 'pending_gm' ||
        $pending['decision_type'] !== 'individual' ||
        $pending['effective_month'] !== $effectiveMonth ||
        $pending['reason'] !== 'Rollback-only GM waiver preparation/rejection verification' ||
        (int)$pending['created_by'] !== $fmUserId ||
        (int)$pending['prepared_by'] !== $fmUserId ||
        (int)$pending['refund_account_id'] !== $refundAccountId ||
        (int)$pending['waiver_expense_account_id'] !== $expenseAccountId ||
        $pending['gm_approved_by'] !== null ||
        $pending['gm_approved_at'] !== null ||
        $pending['gm_rejection_reason'] !== null) {
        throw new RuntimeException('Prepared decision fields/status are inconsistent.');
    }

    if (!$pendingItem ||
        (int)$pendingItem['salary_advance_request_id'] !== (int)$fixture['request_id'] ||
        (int)$pendingItem['employee_id'] !== (int)$fixture['employee_id'] ||
        round((float)$pendingItem['balance_before'], 2) !== round((float)$fixture['outstanding_balance'], 2) ||
        round((float)$pendingItem['balance_before_waiver'], 2) !== round((float)$fixture['outstanding_balance'], 2) ||
        round((float)$pendingItem['current_period_repayment'], 2) < 0.00 ||
        round((float)$pendingItem['refund_amount'], 2) !== 0.00 ||
        round((float)$pendingItem['waived_amount'], 2) !== 0.00 ||
        round((float)$pendingItem['balance_after'], 2) !== 0.00 ||
        $pendingItem['previous_request_status'] !== 'disbursed' ||
        $pendingItem['resulting_request_status'] !== 'disbursed' ||
        $pendingItem['refund_journal_entry_id'] !== null ||
        $pendingItem['waiver_journal_entry_id'] !== null ||
        $pendingItem['executed_at'] !== null) {
        throw new RuntimeException('Prepared waiver item contains unexpected accounting/execution state.');
    }

    if ($journalCountAfterPreparation !== $beforeJournalCount) {
        throw new RuntimeException('Preparation posted a salary-advance waiver journal unexpectedly.');
    }

    $requestDuringPreparation = dbFetchOne(
        "SELECT status, outstanding_balance, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [(int)$fixture['request_id']]
    );

    if (!$requestDuringPreparation ||
        $requestDuringPreparation['status'] !== $beforeRequest['status'] ||
        round((float)$requestDuringPreparation['outstanding_balance'], 2) !==
            round((float)$beforeRequest['outstanding_balance'], 2) ||
        $requestDuringPreparation['closed_at'] !== $beforeRequest['closed_at']) {
        throw new RuntimeException('Preparation changed the underlying salary-advance request.');
    }

    hrSalaryAdvanceWaiverGMReview(
        $pdo,
        $decisionId,
        $gmUserId,
        false,
        'Rollback-only rejection verification reason'
    );

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('GM rejection unexpectedly ended the caller-owned transaction.');
    }

    $rejected = dbFetchOne(
        "SELECT status, gm_approved_by, gm_approved_at, gm_rejection_reason
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );

    $journalCountAfterRejection = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM journal_entries
         WHERE reference_type IN ('salary_advance_waiver', 'salary_advance_waiver_refund')"
    )['c'] ?? 0);

    if (!$rejected ||
        $rejected['status'] !== 'rejected_by_gm' ||
        (int)$rejected['gm_approved_by'] !== $gmUserId ||
        $rejected['gm_approved_at'] === null ||
        $rejected['gm_rejection_reason'] !== 'Rollback-only rejection verification reason') {
        throw new RuntimeException('GM rejection was not recorded exactly as expected.');
    }

    if ($journalCountAfterRejection !== $beforeJournalCount) {
        throw new RuntimeException('GM rejection posted a salary-advance waiver journal unexpectedly.');
    }

    $requestAfterRejection = dbFetchOne(
        "SELECT status, outstanding_balance, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [(int)$fixture['request_id']]
    );

    if (!$requestAfterRejection ||
        $requestAfterRejection['status'] !== $beforeRequest['status'] ||
        round((float)$requestAfterRejection['outstanding_balance'], 2) !==
            round((float)$beforeRequest['outstanding_balance'], 2) ||
        $requestAfterRejection['closed_at'] !== $beforeRequest['closed_at']) {
        throw new RuntimeException('GM rejection changed the underlying salary-advance request.');
    }

    echo "PASS | GM waiver preparation + rejection | request={$fixture['request_no']} | decision_id={$decisionId} | status=rejected_by_gm | journals_unchanged={$beforeJournalCount}\n";

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
    $afterJournalCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM journal_entries
         WHERE reference_type IN ('salary_advance_waiver', 'salary_advance_waiver_refund')"
    )['c'] ?? 0);

    if (
        $afterDecisionCount !== $beforeDecisionCount ||
        $afterItemCount !== $beforeItemCount ||
        $afterScheduleItemCount !== $beforeScheduleItemCount ||
        $afterJournalCount !== $beforeJournalCount
    ) {
        throw new RuntimeException('Rollback-only preparation/rejection changes were not fully removed.');
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

    echo "PASS | Rollback-only cleanup | request={$fixture['request_no']} | decision_rows={$afterDecisionCount} | item_rows={$afterItemCount} | schedule_overlay_rows={$afterScheduleItemCount} | waiver_journals={$afterJournalCount}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | GM waiver preparation/rejection rollback-only verification | " . $e->getMessage() . "\n";
    exit(1);
}
