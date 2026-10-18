<?php
declare(strict_types=1);

/**
 * Rollback-only acceptance for immediate provisional employee incentive notices.
 * Exercises the shared notification helper for the three source-event categories.
 * The business-route call sites are reviewed separately; this creates no committed data.
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/hr/lib_employee_incentives.php';

$pdo = db();

function immediateNoticeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function immediateNoticeOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function immediateNoticeCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$currency = strtoupper(trim((string)APP_CURRENCY_CODE));
$recipient = immediateNoticeOne(
    $pdo,
    "SELECT e.id AS employee_id, e.user_id
     FROM employees e
     JOIN users u ON u.id = e.user_id AND u.is_active = 1
     WHERE e.user_id > 0
     ORDER BY e.id
     LIMIT 1"
);
$types = immediateNoticeOne(
    $pdo,
    "SELECT COUNT(DISTINCT type_code)
     FROM hr_incentive_types
     WHERE type_code IN ('new_sponsor', 'reactivation')
       AND is_active = 1 AND qualification_mode = 'source_event'"
);
if (!$recipient || !$types || (int)$types['COUNT(DISTINCT type_code)'] !== 2 ||
    !preg_match('/^[A-Z]{3}$/', $currency)) {
    echo "SKIP | Immediate activity-notice rollback test | eligible employee, source-event catalogue types, or currency unavailable.\n";
    exit(0);
}

$actorUserId = (int)$recipient['user_id'];
$employeeId = (int)$recipient['employee_id'];
$versionNo = (int)$pdo->query('SELECT COALESCE(MAX(version_no),0) + 1 FROM hr_incentive_policy_versions')->fetchColumn();
$token = strtoupper(bin2hex(random_bytes(6)));
$policyId = 0;
$noticeIds = [];
$noticeTypes = [
    'new_sponsor' => ['تسجيل كفيل جديد', '35.00 ' . $currency],
    'reactivation' => ['إعادة تفعيل كفيل', '45.00 ' . $currency],
];

try {
    $pdo->beginTransaction();

    $policy = $pdo->prepare(
        "INSERT INTO hr_incentive_policy_versions
         (version_no, policy_name, status, effective_from, agreement_recorded_at,
          agreement_notes, created_by_user_id, activated_by_user_id, activated_at)
         VALUES (?, ?, 'active', CURDATE(), NOW(), ?, ?, ?, NOW())"
    );
    $policy->execute([
        $versionNo,
        'ROLLBACK TEST - Immediate activity notices ' . $token,
        'Temporary rollback-only notification fixture; no approval or production policy.',
        $actorUserId,
        $actorUserId,
    ]);
    $policyId = (int)$pdo->lastInsertId();

    $rule = $pdo->prepare(
        "INSERT INTO hr_incentive_policy_rules
         (policy_version_id, incentive_type, reward_amount, currency_code, is_enabled, rule_notes)
         VALUES (?, ?, ?, ?, 1, 'Rollback-only immediate activity notice test')"
    );
    foreach (['new_sponsor' => 35.00, 'reactivation' => 45.00] as $type => $amount) {
        $rule->execute([$policyId, $type, $amount, $currency]);
    }

    $previousCollectionNoticeId = (int)($pdo->query('SELECT COALESCE(MAX(id), 0) FROM notifications')->fetchColumn() ?: 0);
    hrIncentiveNotifyPendingActivity($pdo, $actorUserId, 'collection', date('Y-m-d H:i:s'));
    $afterCollectionNoticeId = (int)($pdo->query('SELECT COALESCE(MAX(id), 0) FROM notifications')->fetchColumn() ?: 0);
    immediateNoticeAssert($afterCollectionNoticeId === $previousCollectionNoticeId, 'Routine collection still generated a provisional incentive notice.');

    foreach ($noticeTypes as $type => [$label, $amountText]) {
        $previousNoticeId = (int)($pdo->query('SELECT COALESCE(MAX(id), 0) FROM notifications')->fetchColumn() ?: 0);
        hrIncentiveNotifyPendingActivity($pdo, $actorUserId, $type, date('Y-m-d H:i:s'));
        $notice = immediateNoticeOne(
            $pdo,
            "SELECT id, recipient_user_id, type, title, body, link, is_read
             FROM notifications
             WHERE recipient_user_id = ? AND type = 'incentive'
             ORDER BY id DESC LIMIT 1",
            [$actorUserId]
        );
        immediateNoticeAssert($notice !== null, "No immediate notification was inserted for {$type}.");
        immediateNoticeAssert(
            (int)$notice['id'] > $previousNoticeId &&
            (int)$notice['recipient_user_id'] === $actorUserId &&
            $notice['type'] === 'incentive' &&
            str_contains((string)$notice['title'], 'تم تسجيل نشاط مرتبط بحافز') &&
            str_contains((string)$notice['body'], $label) &&
            str_contains((string)$notice['body'], $amountText) &&
            str_contains((string)$notice['body'], 'ينتظر') &&
            str_contains((string)$notice['body'], 'لا يعني أن الحافز اعتُمد نهائياً أو صُرف') &&
            (int)$notice['is_read'] === 0,
            "Notification recipient, reward context, provisional wording, or unread state is incorrect for {$type}."
        );
        $noticeIds[$type] = (int)$notice['id'];
    }

    $pdo->rollBack();

    foreach ($noticeIds as $type => $id) {
        immediateNoticeAssert(
            immediateNoticeCount($pdo, 'SELECT COUNT(*) FROM notifications WHERE id = ?', [$id]) === 0,
            "Rollback left the temporary {$type} notification behind."
        );
    }
    immediateNoticeAssert(
        immediateNoticeCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_policy_versions WHERE id = ?', [$policyId]) === 0,
        'Rollback left the temporary policy behind.'
    );

    echo "PASS | immediate_notice=new_sponsor,reactivation | collection_notice=blocked | correct_recipient=PASS\n";
    echo "PASS | provisional_wording=PASS | reward_context=PASS | unread=PASS | rollback_cleanup=PASS\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | Immediate activity-notice rollback test | " . $e->getMessage() . "\n";
    exit(1);
}
