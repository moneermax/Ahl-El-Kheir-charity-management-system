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

if (!function_exists('ak_transaction_review_notify_event')) {
function ak_transaction_review_notify_event(int $userId, string $title, string $body, string $link, ?int $referenceId = null, ?string $referenceType = null): void {
    if ($userId <= 0 || trim($title) === '') return;
    try {
        if ($referenceId !== null && trim((string)$referenceType) !== '') {
            try {
                $existing = dbFetchOne("SELECT id FROM notifications WHERE recipient_user_id = ? AND reference_id = ? AND reference_type = ? AND is_read = 0 LIMIT 1", [$userId, $referenceId, $referenceType]);
                if (!$existing) {
                    dbExecute("INSERT INTO notifications (recipient_user_id, title, body, link, type, reference_id, reference_type, is_read, created_at) VALUES (?, ?, ?, ?, 'workflow', ?, ?, 0, NOW())", [$userId, $title, $body, $link, $referenceId, $referenceType]);
                }
                return;
            } catch (Throwable $referenceError) {}
        }
        $existing = dbFetchOne("SELECT id FROM notifications WHERE recipient_user_id = ? AND title = ? AND link = ? LIMIT 1", [$userId, $title, $link]);
        if (!$existing) dbExecute("INSERT INTO notifications (recipient_user_id, title, body, link, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())", [$userId, $title, $body, $link]);
    } catch (Throwable $e) {}
}
}

if (!function_exists('ak_transaction_review_notify_user')) {
function ak_transaction_review_notify_user(int $userId, string $title, string $body, string $link): void {
    ak_transaction_review_notify_event($userId, $title, $body, $link);
}
}

if (!function_exists('ak_transaction_review_notify_fm')) {
function ak_transaction_review_notify_fm(int $count, string $creatorName = ''): void {
    if ($count <= 0) return;
    try {
        $users = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code IN ('financial_manager', 'fm', 'finance') AND u.is_active = 1");
        foreach ($users as $u) ak_transaction_review_notify_user((int)$u['id'], 'دفعات بانتظار المراجعة المالية', 'لديك ' . $count . ' دفعة بانتظار الاعتماد' . ($creatorName !== '' ? ' من ' . $creatorName : '') . '.', APP_URL . 'modules/accounting/fm_transaction_review.php');
    } catch (Throwable $e) {}
}
}

if (!function_exists('ak_transaction_review_capture_fina_intake')) {
function ak_transaction_review_capture_fina_intake(int $paymentId): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['edit_record']) || $paymentId <= 0 || !isset($_POST['fina_share'])) return;

    static $shareQueue = null;
    static $queuePos = 0;
    if ($shareQueue === null) {
        $raw = $_POST['fina_share'];
        $shareQueue = is_array($raw) ? array_values($raw) : [$raw];
    }
    $rawShare = $shareQueue[$queuePos] ?? 0;
    $queuePos++;
    $finaShare = round((float)str_replace(',', '', (string)$rawShare), 2);
    if ($finaShare <= 0) return;

    $sp = dbFetchOne("SELECT id, amount, sponsor_id, sponsorship_id FROM sponsor_payments WHERE id = ? FOR UPDATE", [$paymentId]);
    if (!$sp) throw new RuntimeException('تعذر قراءة التحصيل لإنشاء حماية أموال فينا الخير.');
    $gross = round((float)$sp['amount'], 2);
    if ($gross <= 0 || $finaShare > $gross) throw new RuntimeException('حصة فينا الخير يجب أن تكون بين صفر وإجمالي التحصيل.');
    if ((int)($sp['sponsor_id'] ?? 0) <= 0 || (int)($sp['sponsorship_id'] ?? 0) <= 0) throw new RuntimeException('تخصيص أموال فينا الخير من شاشة التحصيل العادية يتطلب تحديد الكفيل والكفالة.');

    require_once __DIR__ . '/lib_fina.php';
    [$mode, $ahlShare, $finaShare] = ak_fina_validate_amounts($gross, $finaShare);
    if ($mode === 'ahl_only') return;

    $reference = 'FPI-SP-' . $paymentId;
    dbExecute("INSERT INTO fina_payment_intakes (sponsor_payment_id, allocation_mode, fina_share_amount, integration_reference, created_by) VALUES (?, ?, ?, ?, ?)", [$paymentId, $mode, $finaShare, $reference, (int)Session::getUserId()]);
}
}

if (!function_exists('ak_transaction_review_apply_fina_intake')) {
function ak_transaction_review_apply_fina_intake(int $paymentId, int $transactionId): void {
    $intake = dbFetchOne("SELECT * FROM fina_payment_intakes WHERE sponsor_payment_id = ? FOR UPDATE", [$paymentId]);
    if (!$intake) return;

    $t = dbFetchOne("SELECT id, amount, currency_code, transaction_date FROM transactions WHERE id = ? FOR UPDATE", [$transactionId]);
    if (!$t) throw new RuntimeException('تعذر قراءة المعاملة لإنشاء توزيع فينا الخير.');
    require_once __DIR__ . '/lib_fina.php';
    [$mode, $ahlShare, $finaShare] = ak_fina_validate_amounts((float)$t['amount'], (float)$intake['fina_share_amount']);
    if ($mode !== $intake['allocation_mode']) throw new RuntimeException('توزيع فينا الخير لا يطابق إجمالي التحصيل.');

    $existing = dbFetchOne("SELECT id FROM fina_payment_allocations WHERE transaction_id = ? LIMIT 1", [$transactionId]);
    if ($existing) throw new RuntimeException('يوجد بالفعل توزيع فينا الخير لهذه المعاملة.');

    $policy = ak_get_admin_fee_policy((string)date('Y-m-d', strtotime($t['transaction_date'] ?? date('Y-m-d'))));
    $calc = ak_fina_calculate_fee((float)$t['amount'], $finaShare, $policy);
    dbExecute("INSERT INTO fina_payment_allocations (transaction_id, sponsor_payment_id, allocation_mode, gross_amount, ahl_share_amount, fina_share_amount, ahl_admin_fee_amount, ahl_net_amount, settled_amount, currency_code, status, integration_reference, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, 'protected', ?, ?)", [$transactionId, $paymentId, $mode, round((float)$t['amount'], 2), $ahlShare, $finaShare, $calc['amount'], $calc['net_amount'], $t['currency_code'], 'FPA-TXN-' . $transactionId, (int)Session::getUserId()]);
}
}

if (!function_exists('ak_transaction_review_notify_fm_event')) {
function ak_transaction_review_notify_fm_event(int $referenceId, string $referenceType, string $title, string $body, string $link, ?int $excludeUserId = null): void {
    if ($referenceId <= 0 || trim($referenceType) === '' || trim($title) === '') return;
    if ($referenceType === 'sponsor_payment_submitted') ak_transaction_review_capture_fina_intake($referenceId);
    try {
        $users = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code IN ('financial_manager', 'fm', 'finance') AND u.is_active = 1");
        foreach ($users as $u) {
            $userId = (int)$u['id'];
            if ($excludeUserId !== null && $userId === $excludeUserId) continue;
            ak_transaction_review_notify_event($userId, $title, $body, $link, $referenceId, $referenceType);
        }
    } catch (Throwable $e) {}
}
}

if (!function_exists('ak_transaction_review_capture_fina_allocation')) {
function ak_transaction_review_capture_fina_allocation(int $transactionId): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['edit_record'])) return;
    if (!isset($_POST['fina_share'])) return;

    static $shareQueue = null;
    static $queuePos = 0;
    if ($shareQueue === null) {
        $raw = $_POST['fina_share'];
        $shareQueue = is_array($raw) ? array_values($raw) : [$raw];
    }
    $rawShare = $shareQueue[$queuePos] ?? 0;
    $queuePos++;
    $finaShare = round((float)str_replace(',', '', (string)$rawShare), 2);
    if ($finaShare <= 0) return;

    $t = dbFetchOne("SELECT id, amount, currency_code, sponsor_id, sponsorship_id, transaction_type FROM transactions WHERE id = ? FOR UPDATE", [$transactionId]);
    if (!$t) throw new RuntimeException('تعذر قراءة المعاملة لإنشاء توزيع فينا الخير.');
    if ((int)($t['sponsor_id'] ?? 0) <= 0) throw new RuntimeException('تخصيص أموال فينا الخير من شاشة التحصيل العادية يتطلب تحديد الكفيل. استخدم شاشة تحصيل فينا الخير للأموال الخارجية.');
    if ((int)($t['sponsorship_id'] ?? 0) <= 0) throw new RuntimeException('تخصيص أموال فينا الخير من شاشة التحصيل العادية يتطلب تحديد الكفالة. استخدم شاشة تحصيل فينا الخير للأموال الخارجية.');

    require_once __DIR__ . '/lib_fina.php';
    [$mode, $ahlShare, $finaShare] = ak_fina_validate_amounts((float)$t['amount'], $finaShare);
    if ($mode === 'ahl_only') return;

    $existing = dbFetchOne("SELECT id FROM fina_payment_allocations WHERE transaction_id = ? LIMIT 1", [$transactionId]);
    if ($existing) throw new RuntimeException('يوجد بالفعل توزيع فينا الخير لهذه المعاملة.');

    $reference = 'FPA-TXN-' . $transactionId;
    dbExecute("INSERT INTO fina_payment_allocations (transaction_id, sponsor_payment_id, allocation_mode, gross_amount, ahl_share_amount, fina_share_amount, ahl_admin_fee_amount, ahl_net_amount, settled_amount, currency_code, status, integration_reference, created_by) VALUES (?, NULL, ?, ?, ?, ?, 0.00, ?, 0.00, ?, 'protected', ?, ?)", [$transactionId, $mode, round((float)$t['amount'], 2), $ahlShare, $finaShare, $ahlShare, $t['currency_code'], $reference, (int)Session::getUserId()]);
}
}

if (!function_exists('ak_transaction_review_audit')) {
function ak_transaction_review_audit(int $userId, string $action, int $entityId, $old, $new): void {
    try {
        if ($action === 'SUBMIT_FM') ak_transaction_review_capture_fina_allocation($entityId);
    } catch (Throwable $finaError) {
        throw $finaError;
    }
    try {
        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, 'transactions', ?, ?, ?, ?, ?)", [$userId, $action, $entityId, json_encode($old, JSON_UNESCAPED_UNICODE), json_encode($new, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
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
