<?php
// modules/notifications/mark_all_read.php - Mark all current user's notifications as read
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'login.php');
    exit;
}

try {
    dbExecute(
        "UPDATE notifications
         SET is_read = 1
         WHERE recipient_user_id = ?
           AND is_read = 0",
        [current_user_id()]
    );
} catch (Throwable $e) {
    // Do not expose database details to the user.
}

$redirect = (string)($_SERVER['HTTP_REFERER'] ?? '');

if ($redirect === '' || strpos($redirect, APP_URL) !== 0) {
    $redirect = APP_URL;
}

header('Location: ' . $redirect);
exit;
