<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if (!in_array($user['role_code'], ['admin', 'sudo', 'super_admin'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Administrator access is required.']]);
}

$stats = [
    'users' => 0,
    'sponsors' => 0,
    'families' => 0,
    'sponsorships' => 0,
    'settings' => 0,
    'audit_log' => 0,
    'pending_recovery' => 0,
    'unread_notifications' => 0,
];
$queries = [
    'users' => 'SELECT COUNT(*) AS c FROM users',
    'sponsors' => 'SELECT COUNT(*) AS c FROM sponsors',
    'families' => 'SELECT COUNT(*) AS c FROM families',
    'sponsorships' => 'SELECT COUNT(*) AS c FROM sponsorships',
    'settings' => 'SELECT COUNT(*) AS c FROM settings',
    'audit_log' => 'SELECT COUNT(*) AS c FROM audit_log',
    'pending_recovery' => "SELECT COUNT(*) AS c FROM password_recovery_requests WHERE status = 'pending'",
];
foreach ($queries as $key => $sql) {
    try {
        $stats[$key] = (int)(dbFetchOne($sql)['c'] ?? 0);
    } catch (Throwable $e) {
        // Preserve a readable dashboard if an optional metric is unavailable.
    }
}
try {
    $stats['unread_notifications'] = (int)(dbFetchOne(
        'SELECT COUNT(*) AS c FROM notifications WHERE recipient_user_id = ? AND is_read = 0',
        [$user['id']]
    )['c'] ?? 0);
} catch (Throwable $e) {}

$recentAudit = [];
try {
    $recentAudit = dbFetchAll(
        'SELECT a.id, a.created_at, a.action, a.entity_type, a.entity_id, u.username
         FROM audit_log a
         LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.id DESC LIMIT 6'
    );
} catch (Throwable $e) {}

$recentAudit = array_map(static fn(array $row): array => [
    'id' => (int)$row['id'],
    'created_at' => (string)($row['created_at'] ?? ''),
    'action' => (string)($row['action'] ?? ''),
    'entity_type' => (string)($row['entity_type'] ?? ''),
    'entity_id' => isset($row['entity_id']) ? (int)$row['entity_id'] : null,
    'username' => (string)($row['username'] ?? ''),
], $recentAudit);

ak_api_json(200, [
    'ok' => true,
    'data' => [
        'stats' => $stats,
        'recent_audit' => $recentAudit,
        'server' => [
            'php_version' => PHP_VERSION,
            'database_connected' => true,
        ],
    ],
]);
