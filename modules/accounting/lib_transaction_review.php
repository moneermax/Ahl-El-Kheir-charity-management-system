<?php
// Accounting transaction FM review helpers.
// Procedural PHP only. Ordinary transactions follow:
// pending_fm_review -> returned -> pending_fm_review -> posted
// pending_fm_review -> cancelled
// Posted transactions remain immutable through the normal edit path.

if (!function_exists('ak_transaction_review_is_fm')) {
function ak_transaction_review_is_fm(string $role): bool {
    return in_array($role, ['financial_manager', 'fm', 'finance', 'admin'], true);
}
}

if (!function_exists('ak_transaction_review_receipt_refs')) {
function ak_transaction_review_receipt_refs(string $path): int {
    if ($path === '') return 0;
    $count = 0;
    try {
        $r = dbFetchOne("SELECT COUNT(*) c FROM transactions WHERE receipt_path = ? OR unified_receipt_path = ?", [$path, $path]);
        $count += (int)($r['c'] ?? 0);
    } catch (Throwable $e) {}
    try {
        $r = dbFetchOne("SELECT COUNT(*) c FROM sponsor_payments WHERE receipt_file_path = ? OR unified_receipt_path = ?", [$path, $path]);
        $count += (int)($r['c'] ?? 0);
    } catch (Throwable $e) {}
    return $count;
}
}

if (!function_exists('ak_transaction_review_delete_receipt_if_unreferenced')) {
function ak_transaction_review_delete_receipt_if_unreferenced(?string $path): void {
    $path = trim((string)$path);
    if ($path === '' || ak_transaction_review_receipt_refs($path) > 0) return;
    $root = dirname(__DIR__, 2);
    $relative = ltrim(str_replace(['\\', '/'], '/', $path), '/');
    $full = $root . '/' . $relative;
    if (is_file($full)) @unlink($full);
}
}

if (!function_exists('ak_transaction_review_notify_user')) {
function ak_transaction_review_notify_user(int $userId, string $title, string $body, string $link): void {
    if ($userId <= 0 || trim($title) === '') return;

    try {
        // The notifications table is recipient_user_id/title/body/link/is_read/created_at.
        // Avoid duplicate delivery when a workflow POST is replayed or retried.
        $existing = dbFetchOne(
            "SELECT id
             FROM notifications
             WHERE recipient_user_id = ?
               AND title = ?
               AND link = ?
             LIMIT 1",
            [$userId, $title, $link]
        );

        if ($existing) return;

        dbExecute(
            "INSERT INTO notifications (recipient_user_id, title, body, link, is_read, created_at)
             VALUES (?, ?, ?, ?, 0, NOW())",
            [$userId, $title, $body, $link]
        );
    } catch (Throwable $e) {
        // Notification delivery must never roll back an already-completed business action.
    }
}
}

if (!function_exists('ak_transaction_review_notify_fm')) {
function ak_transaction_review_notify_fm(int $count, string $creatorName = ''): void {
    if ($count <= 0) return;

    try {
        $users = dbFetchAll(
            "SELECT u.id
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE r.code IN ('financial_manager', 'fm', 'finance')
               AND u.is_active = 1"
        );

        foreach ($users as $u) {
            ak_transaction_review_notify_user(
                (int)$u['id'],
                'دفعات بانتظار المراجعة المالية',
                'لديك ' . $count . ' دفعة بانتظار الاعتماد' .
                    ($creatorName !== '' ? ' من ' . $creatorName : '') . '.',
                APP_URL . 'modules/accounting/fm_transaction_review.php'
            );
        }
    } catch (Throwable $e) {}
}
}

if (!function_exists('ak_transaction_review_audit')) {
function ak_transaction_review_audit(int $userId, string $action, int $entityId, $old, $new): void {
    try {
        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                   VALUES (?, ?, 'transactions', ?, ?, ?, ?, ?)",
            [$userId, $action, $entityId,
             json_encode($old, JSON_UNESCAPED_UNICODE),
             json_encode($new, JSON_UNESCAPED_UNICODE),
             $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (Throwable $e) {}
}
}

if (!function_exists('ak_transaction_review_creator')) {
function ak_transaction_review_creator(int $transactionId): ?array {
    return dbFetchOne("SELECT t.*, u.full_name AS creator_name FROM transactions t LEFT JOIN users u ON u.id = t.created_by WHERE t.id = ?", [$transactionId]);
}
}

if (!function_exists('ak_transaction_review_validate_returned_owner')) {
function ak_transaction_review_validate_returned_owner(int $transactionId, int $userId): ?array {
    $t = ak_transaction_review_creator($transactionId);
    if (!$t || $t['status'] !== 'returned' || (int)$t['created_by'] !== $userId) return null;
    return $t;
}
}
