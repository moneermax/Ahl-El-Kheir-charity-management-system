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
        message_delete_json(['ok' => false, 'message' => t('messages.delete_unknown_request')], 400);
    }

    $raw = trim((string)($_GET['message_ids'] ?? ''));
    $ids = array_values(array_unique(array_filter(array_map('intval', preg_split('/\s*,\s*/', $raw) ?: []), static fn(int $id): bool => $id > 0)));
    if (!$ids) message_delete_json(['ok' => false, 'message' => t('messages.delete_no_valid_messages')], 422);

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
    message_delete_json(['ok' => false, 'message' => t('messages.delete_method_not_allowed')], 405);
}

if (!verify_csrf()) {
    message_delete_json(['ok' => false, 'message' => t('messages.delete_session_expired')], 419);
}

if (($_POST['action'] ?? '') !== 'delete_message') {
    message_delete_json(['ok' => false, 'message' => t('messages.delete_unknown_request')], 400);
}

$messageId = (int)($_POST['message_id'] ?? 0);
if ($messageId <= 0) message_delete_json(['ok' => false, 'message' => t('messages.delete_invalid_message')], 422);

if (!messaging_user_can_read($uid, $messageId)) {
    message_delete_json(['ok' => false, 'message' => t('messages.delete_access_denied')], 403);
}
if (!messaging_user_can_delete($uid, $messageId)) {
    message_delete_json(['ok' => false, 'message' => t('messages.delete_owner_or_admin')], 403);
}

if (!delete_message($uid, $messageId)) {
    message_delete_json(['ok' => false, 'message' => t('messages.delete_failed')], 409);
}

message_delete_json([
    'ok' => true,
    'id' => $messageId,
    'message' => t('messages.delete_success')
]);
