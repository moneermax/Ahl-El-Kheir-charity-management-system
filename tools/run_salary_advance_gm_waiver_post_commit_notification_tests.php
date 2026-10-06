<?php
declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

/**
 * GM salary-advance waiver post-commit notification verification.
 *
 * This test intentionally uses the production transaction-owned functions
 * without an outer transaction so their startedHere=true commit paths execute.
 * It verifies preparation -> GM notification and GM approval -> FM notification
 * are visible only after the related business transaction has committed.
 *
 * The created decision is financially untouched (no FM execution), then all
 * test rows/notifications are explicitly removed in a cleanup transaction.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_waiver.php';

$pdo = db();

function findNotificationUserByRole(PDO $pdo, array $roleCodes, int $excludeUserId = 0): ?int
{
    $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
    $params = $roleCodes;
    $sql = "SELECT u.id
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE r.code IN ($placeholders)
              AND u.is_active = 1";
    if ($excludeUserId > 0) {
        $sql .= " AND u.id <> ?";
        $params[] = $excludeUserId;
    }
    $sql .= " ORDER BY u.id ASC LIMIT 1";

    $row = dbFetchOne($sql, $params);
    return $row ? (int)$row['id'] : null;
}

function notificationReferenceColumnsAvailable(PDO $pdo): bool
{
    $row = dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'notifications'
           AND column_name IN ('reference_id', 'reference_type')"
    );
    return (int)($row['c'] ?? 0) === 2;
}

function findNotificationByEvent(PDO $pdo, int $userId, int $decisionId, string $referenceType, string $title, string $link, bool $hasReferenceColumns): ?int
{
    if ($hasReferenceColumns) {
        $row = dbFetchOne(
            "SELECT id FROM notifications
             WHERE recipient_user_id = ?
               AND reference_id = ?
               AND reference_type = ?
               AND is_read = 0
             ORDER BY id DESC
             LIMIT 1",
            [$userId, $decisionId, $referenceType]
        );
    } else {
        $row = dbFetchOne(
            "SELECT id FROM notifications
             WHERE recipient_user_id = ?
               AND title = ?
               AND link = ?
               AND is_read = 0
             ORDER BY id DESC
             LIMIT 1",
            [$userId, $title, $link]
        );
    }
    return $row ? (int)$row['id'] : null;
}

function findFixture(PDO $pdo): ?array
{
    return dbFetchOne(
        "SELECT r.id AS request_id, r.request_no, r.employee_id, s.scheduled_month
         FROM hr_salary_advance_requests r
         JOIN hr_salary_advance_repayment_schedule s
           ON s.salary_advance_request_id = r.id
         WHERE r.status = 'disbursed'
           AND COALESCE(r.outstanding_balance, 0) > 0
           AND s.status IN ('pending','partial')
         ORDER BY s.scheduled_month ASC, r.id ASC
         LIMIT 1"
    );
}

function findActiveAccountByCode(PDO $pdo, string $code): ?int
{
    $row = dbFetchOne(
        "SELECT id FROM accounts WHERE code = ? AND is_active = 1 LIMIT 1",
        [$code]
    );
    return $row ? (int)$row['id'] : null;
}

$fixture = findFixture($pdo);
$fmUserId = findNotificationUserByRole($pdo, ['financial_manager','fm','finance']);
$gmUserId = findNotificationUserByRole($pdo, ['general_manager','gm'], $fmUserId);
$refundAccountId = findActiveAccountByCode($pdo, '1100');
$expenseRow = dbFetchOne(
    "SELECT id FROM accounts
     WHERE is_active = 1 AND account_type = 'expense'
     ORDER BY id ASC LIMIT 1"
);
$expenseAccountId = $expenseRow ? (int)$expenseRow['id'] : null;

if (!$fixture || !$fmUserId || !$gmUserId || !$refundAccountId || !$expenseAccountId) {
    echo "SKIP | Required fixture, active FM/GM users, or accounts were not found.\n";
    exit(0);
}

$decisionId = 0;
$gmNotificationId = 0;
$fmNotificationId = 0;
$hasReferenceColumns = notificationReferenceColumnsAvailable($pdo);

try {
    $effectiveMonth = date('Y-m-01', strtotime((string)$fixture['scheduled_month']));

    // Production function owns and commits this transaction.
    $decisionId = hrSalaryAdvanceWaiverCreateDecision(
        $pdo,
        'individual',
        $effectiveMonth,
        'Post-commit notification verification',
        $fmUserId,
        (int)$fixture['employee_id'],
        $refundAccountId,
        $expenseAccountId
    );

    $decision = dbFetchOne(
        "SELECT id, decision_no, status
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );

    if (!$decision || $decision['status'] !== 'pending_gm') {
        throw new RuntimeException('Committed preparation decision was not found in pending_gm state.');
    }

    $gmNotificationId = findNotificationByEvent(
        $pdo,
        $gmUserId,
        $decisionId,
        'salary_advance_waiver_gm_review',
        'قرار إعفاء سلف الرواتب بانتظار اعتمادك',
        APP_URL . 'modules/hr/salary_advance_waiver_gm.php',
        $hasReferenceColumns
    );

    if ($gmNotificationId <= 0) {
        throw new RuntimeException('GM preparation notification was not visible after the preparation commit.');
    }

    echo "PASS | Post-commit preparation notification | decision_id={$decisionId} | status=pending_gm | gm_notification=1\n";

    // Production function owns and commits this transaction too.
    hrSalaryAdvanceWaiverGMReview(
        $pdo,
        $decisionId,
        $gmUserId,
        true
    );

    $approved = dbFetchOne(
        "SELECT status
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );

    if (!$approved || $approved['status'] !== 'approved_by_gm') {
        throw new RuntimeException('Committed GM approval was not found in approved_by_gm state.');
    }

    $fmNotificationId = findNotificationByEvent(
        $pdo,
        $fmUserId,
        $decisionId,
        'salary_advance_waiver_fm_execution',
        'اعتماد GM لقرار إعفاء سلف الرواتب',
        APP_URL . 'modules/hr/salary_advance_waiver_fm.php',
        $hasReferenceColumns
    );

    if ($fmNotificationId <= 0) {
        throw new RuntimeException('FM approval notification was not visible after the GM approval commit.');
    }

    echo "PASS | Post-commit GM approval notification | decision_id={$decisionId} | status=approved_by_gm | fm_notification=1\n";

    // No financial execution occurred. Remove only this harness's committed rows.
    $pdo->beginTransaction();

    if ($gmNotificationId > 0) {
        $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$gmNotificationId]);
    }
    if ($fmNotificationId > 0) {
        $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$fmNotificationId]);
    }

    $pdo->prepare(
        "DELETE FROM audit_log
         WHERE entity_type = 'hr_salary_advance_waiver_decision'
           AND entity_id = ?"
    )->execute([$decisionId]);

    $pdo->prepare(
        "DELETE FROM hr_salary_advance_waiver_schedule_items
         WHERE waiver_item_id IN (
             SELECT id FROM hr_salary_advance_waiver_items WHERE decision_id = ?
         )"
    )->execute([$decisionId]);

    $pdo->prepare(
        "DELETE FROM hr_salary_advance_waiver_items WHERE decision_id = ?"
    )->execute([$decisionId]);

    $pdo->prepare(
        "DELETE FROM hr_salary_advance_waiver_decisions WHERE id = ?"
    )->execute([$decisionId]);

    $pdo->commit();

    $remaining = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    )['c'] ?? 0);

    $remainingNotificationIds = 0;
    if ($gmNotificationId > 0) {
        $remainingNotificationIds += (int)(dbFetchOne("SELECT COUNT(*) AS c FROM notifications WHERE id = ?", [$gmNotificationId])['c'] ?? 0);
    }
    if ($fmNotificationId > 0) {
        $remainingNotificationIds += (int)(dbFetchOne("SELECT COUNT(*) AS c FROM notifications WHERE id = ?", [$fmNotificationId])['c'] ?? 0);
    }

    if ($remaining !== 0 || $remainingNotificationIds !== 0) {
        throw new RuntimeException('Committed notification-test cleanup left residual rows.');
    }

    echo "PASS | Post-commit notification cleanup | decision_rows=0 | test_notifications=0\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | Post-commit notification verification | " . $e->getMessage() . "\n";
    exit(1);
}
