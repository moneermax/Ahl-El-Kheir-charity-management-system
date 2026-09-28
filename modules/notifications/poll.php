<?php
// modules/notifications/poll.php - Lightweight live notification polling endpoint
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'unread' => 0, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $unread = (int)(dbFetchOne(
        "SELECT COUNT(*) c
         FROM notifications
         WHERE recipient_user_id = ?
           AND is_read = 0",
        [current_user_id()]
    )['c'] ?? 0);

    $items = dbFetchAll(
        "SELECT id, title, body, link, created_at, is_read
         FROM notifications
         WHERE recipient_user_id = ?
         ORDER BY id DESC
         LIMIT 8",
        [current_user_id()]
    );

    echo json_encode([
        'ok' => true,
        'unread' => $unread,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'unread' => 0, 'items' => []], JSON_UNESCAPED_UNICODE);
}
