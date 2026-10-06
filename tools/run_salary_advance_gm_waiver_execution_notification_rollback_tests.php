<?php
declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_waiver.php';

$pdo = db();

function findExecutionNotificationFixture(PDO $pdo): ?array
{
    return dbFetchOne(
        "SELECT r.id AS request_id, r.request_no, r.employee_id, r.status,
                r.outstanding_balance, s.scheduled_month, e.user_id AS employee_user_id
         FROM hr_salary_advance_requests r
         JOIN hr_salary_advance_repayment_schedule s
           ON s.salary_advance_request_id = r.id
         JOIN employees e ON e.id = r.employee_id
         JOIN users eu ON eu.id = e.user_id AND eu.is_active = 1
         WHERE r.status = 'disbursed'
           AND COALESCE(r.outstanding_balance, 0) > 0
           AND s.status IN ('pending','partial')
           AND NOT EXISTS (
               SELECT 1 FROM payroll p
               WHERE p.employee_id = r.employee_id
                 AND p.year = YEAR(s.scheduled_month)
                 AND p.month = MONTH(s.scheduled_month)
                 AND p.status = 'draft'
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

function notificationReferenceColumnsAvailable(PDO $pdo): bool
{
    $row = dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'notifications'
           AND column_name IN ('reference_id','reference_type')"
    );
    return (int)($row['c'] ?? 0) === 2;
}

function findNotificationByEvent(
    PDO $pdo,
    int $userId,
    int $decisionId,
    string $referenceType,
    string $title,
    string $link,
    bool $hasReferenceColumns
): ?int {
    if ($hasReferenceColumns) {
        $row = dbFetchOne(
            "SELECT id FROM notifications
             WHERE recipient_user_id = ? AND reference_id = ?
               AND reference_type = ? AND is_read = 0
             ORDER BY id DESC LIMIT 1",
            [$userId, $decisionId, $referenceType]
        );
    } else {
        $row = dbFetchOne(
            "SELECT id FROM notifications
             WHERE recipient_user_id = ? AND title = ?
               AND link = ? AND is_read = 0
             ORDER BY id DESC LIMIT 1",
            [$userId, $title, $link]
        );
    }
    return $row ? (int)$row['id'] : null;
}

$fixture = findExecutionNotificationFixture($pdo);
$fmUserId = findActiveUserByRole($pdo, ['financial_manager','fm','finance']);
$gmUserId = findActiveUserByRole($pdo, ['general_manager','gm']);
$refundAccountId = findAccountByCode($pdo, '1100');
$expenseAccountId = findActiveExpenseAccount($pdo);

if (!$fixture || !$fmUserId || !$gmUserId || !$refundAccountId || !$expenseAccountId) {
    echo "SKIP | Required disbursed fixture with active employee account, active FM/GM users, or accounts was not found.\n";
    exit(0);
}

$decisionId = 0;
$refundJournalId = null;
$waiverJournalId = null;
$gmNotificationId = 0;
$employeeNotificationId = 0;
$hasReferenceColumns = notificationReferenceColumnsAvailable($pdo);

$beforeRequest = dbFetchOne(
    "SELECT status, outstanding_balance, closed_at FROM hr_salary_advance_requests WHERE id = ?",
    [(int)$fixture['request_id']]
);

try {
    // Real production functions own and commit all three business steps.
    $decisionId = hrSalaryAdvanceWaiverCreateDecision(
        $pdo, 'individual',
        date('Y-m-01', strtotime((string)$fixture['scheduled_month'])),
        'Post-commit execution notification verification',
        $fmUserId, (int)$fixture['employee_id'],
        $refundAccountId, $expenseAccountId
    );

    hrSalaryAdvanceWaiverGMReview($pdo, $decisionId, $gmUserId, true);

    $execution = hrSalaryAdvanceWaiverExecute($pdo, $decisionId, $fmUserId);

    if ((int)($execution['decision_id'] ?? 0) !== $decisionId) {
        throw new RuntimeException('Execution returned an unexpected decision ID.');
    }

    $decision = dbFetchOne(
        "SELECT status, decision_no FROM hr_salary_advance_waiver_decisions WHERE id = ?",
        [$decisionId]
    );
    if (!$decision || $decision['status'] !== 'executed') {
        throw new RuntimeException('Committed execution did not leave the decision in executed state.');
    }

    $refundJournalId = !empty($execution['refund_journal_entry_id'])
        ? (int)$execution['refund_journal_entry_id'] : null;
    $waiverJournalId = !empty($execution['waiver_journal_entry_id'])
        ? (int)$execution['waiver_journal_entry_id'] : null;

    if (!$waiverJournalId) {
        throw new RuntimeException('Execution notification fixture did not create a waiver journal.');
    }

    $gmNotificationId = findNotificationByEvent(
        $pdo, $gmUserId, $decisionId,
        'salary_advance_waiver_execution',
        'تم تنفيذ قرار إعفاء سلف الرواتب',
        APP_URL . 'modules/hr/salary_advance_waiver_gm.php',
        $hasReferenceColumns
    );
    if ($gmNotificationId <= 0) {
        throw new RuntimeException('Approving GM execution notification was not visible after commit.');
    }

    $employeeNotificationId = findNotificationByEvent(
        $pdo, (int)$fixture['employee_user_id'], $decisionId,
        'salary_advance_waiver_employee',
        'تم تنفيذ قرار إعفاء سلف الراتب',
        APP_URL . 'modules/hr/salary_advance_request.php',
        $hasReferenceColumns
    );
    if ($employeeNotificationId <= 0) {
        throw new RuntimeException('Affected employee execution notification was not visible after commit.');
    }

    echo "PASS | Post-commit execution notifications | request={$fixture['request_no']} | decision_id={$decisionId} | gm_notification=1 | employee_notification=1 | waiver_journal={$waiverJournalId}\n";

    // Explicitly undo only this committed test fixture.
    $pdo->beginTransaction();

    if ($gmNotificationId > 0) {
        $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$gmNotificationId]);
    }
    if ($employeeNotificationId > 0) {
        $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$employeeNotificationId]);
    }

    $pdo->prepare(
        "DELETE FROM audit_log
         WHERE entity_type = 'hr_salary_advance_request' AND entity_id = ?
           AND action = 'HR_SALARY_ADVANCE_WAIVER_EXECUTED'"
    )->execute([(int)$fixture['request_id']]);

    $pdo->prepare(
        "DELETE FROM audit_log
         WHERE entity_type = 'hr_salary_advance_waiver_decision' AND entity_id = ?"
    )->execute([$decisionId]);

    $pdo->prepare(
        "DELETE FROM hr_salary_advance_waiver_schedule_items
         WHERE waiver_item_id IN (
             SELECT id FROM hr_salary_advance_waiver_items WHERE decision_id = ?
         )"
    )->execute([$decisionId]);

    $pdo->prepare("DELETE FROM hr_salary_advance_waiver_items WHERE decision_id = ?")
        ->execute([$decisionId]);

    $pdo->prepare("DELETE FROM hr_salary_advance_waiver_decisions WHERE id = ?")
        ->execute([$decisionId]);

    if ($refundJournalId) {
        $pdo->prepare("DELETE FROM journal_lines WHERE entry_id = ?")->execute([$refundJournalId]);
        $pdo->prepare("DELETE FROM journal_entries WHERE id = ?")->execute([$refundJournalId]);
    }
    if ($waiverJournalId) {
        $pdo->prepare("DELETE FROM journal_lines WHERE entry_id = ?")->execute([$waiverJournalId]);
        $pdo->prepare("DELETE FROM journal_entries WHERE id = ?")->execute([$waiverJournalId]);
    }

    $pdo->prepare(
        "UPDATE hr_salary_advance_requests
         SET status = ?, outstanding_balance = ?, closed_at = ?
         WHERE id = ?"
    )->execute([
        $beforeRequest['status'],
        $beforeRequest['outstanding_balance'],
        $beforeRequest['closed_at'],
        (int)$fixture['request_id']
    ]);

    $pdo->commit();

    $remainingDecision = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions WHERE id = ?",
        [$decisionId]
    )['c'] ?? 0);
    $remainingGm = $gmNotificationId > 0
        ? (int)(dbFetchOne("SELECT COUNT(*) AS c FROM notifications WHERE id = ?", [$gmNotificationId])['c'] ?? 0)
        : 0;
    $remainingEmployee = $employeeNotificationId > 0
        ? (int)(dbFetchOne("SELECT COUNT(*) AS c FROM notifications WHERE id = ?", [$employeeNotificationId])['c'] ?? 0)
        : 0;
    $remainingWaiverJournal = $waiverJournalId
        ? (int)(dbFetchOne("SELECT COUNT(*) AS c FROM journal_entries WHERE id = ?", [$waiverJournalId])['c'] ?? 0)
        : 0;
    $remainingRefundJournal = $refundJournalId
        ? (int)(dbFetchOne("SELECT COUNT(*) AS c FROM journal_entries WHERE id = ?", [$refundJournalId])['c'] ?? 0)
        : 0;

    if ($remainingDecision || $remainingGm || $remainingEmployee || $remainingWaiverJournal || $remainingRefundJournal) {
        throw new RuntimeException('Execution-notification cleanup left residual test rows.');
    }

    echo "PASS | Post-commit execution notification cleanup | decision_rows=0 | test_notifications=0 | journals=0\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | Post-commit execution notification verification | " . $e->getMessage() . "\n";
    exit(1);
}
