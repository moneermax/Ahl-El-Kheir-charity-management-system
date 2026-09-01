<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/messaging.php';

Session::start();
require_login();

header('Content-Type: application/json; charset=utf-8');
$uid = Session::getUserId();

function message_delete_json(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['action'] ?? '') !== 'state') {
        message_delete_json(['ok' => false, 'message' => 'طلب غير معروف.'], 400);
    }

    $raw = trim((string)($_GET['message_ids'] ?? ''));
    $ids = array_values(array_unique(array_filter(array_map('intval', preg_split('/\s*,\s*/', $raw) ?: []), static fn(int $id): bool => $id > 0)));
    if (!$ids) message_delete_json(['ok' => false, 'message' => 'لا توجد رسائل صالحة.'], 422);

    $state = [];
    foreach ($ids as $id) {
        $row = dbFetchOne('SELECT id,deleted_at FROM messages WHERE id=? LIMIT 1', [$id]);
        if (!$row || !messaging_user_can_read($uid, $id)) continue;
        $state[(string)$id] = [
            'deleted' => !empty($row['deleted_at']),
            'can_delete' => messaging_user_can_delete($uid, $id),
        ];
    }
    message_delete_json(['ok' => true, 'messages' => $state]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    message_delete_json(['ok' => false, 'message' => 'طريقة الطلب غير مسموحة.'], 405);
}

if (!verify_csrf()) {
    message_delete_json(['ok' => false, 'message' => 'انتهت صلاحية الجلسة.'], 419);
}

if (($_POST['action'] ?? '') !== 'delete_message') {
    message_delete_json(['ok' => false, 'message' => 'طلب غير معروف.'], 400);
}

$messageId = (int)($_POST['message_id'] ?? 0);
if ($messageId <= 0) message_delete_json(['ok' => false, 'message' => 'الرسالة غير صالحة.'], 422);

if (!messaging_user_can_read($uid, $messageId)) {
    message_delete_json(['ok' => false, 'message' => 'ليس لديك صلاحية للوصول إلى هذه الرسالة.'], 403);
}
if (!messaging_user_can_delete($uid, $messageId)) {
    message_delete_json(['ok' => false, 'message' => 'يمكن حذف الرسالة بواسطة صاحبها أو مدير النظام فقط.'], 403);
}

if (!delete_message($uid, $messageId)) {
    message_delete_json(['ok' => false, 'message' => 'تعذر حذف الرسالة. ربما تم حذفها مسبقاً.'], 409);
}

message_delete_json([
    'ok' => true,
    'id' => $messageId,
    'message' => 'تم حذف الرسالة.'
]);
