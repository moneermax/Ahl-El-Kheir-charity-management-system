<?php
// modules/notifications/mark_read.php - Mark one current user's notification as read
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL);
    exit;
}

if (!verify_csrf()) {
    flash('error', 'انتهت جلسة الأمان. يرجى المحاولة مرة أخرى.');
    header('Location: ' . APP_URL);
    exit;
}

$notificationId = (int)($_POST['notification_id'] ?? 0);

try {
    if ($notificationId > 0) {
        dbExecute(
            "UPDATE notifications
             SET is_read = 1
             WHERE id = ?
               AND recipient_user_id = ?
               AND is_read = 0",
            [$notificationId, current_user_id()]
        );
    }
} catch (Throwable $e) {
    // Do not expose database details to the user.
}

$redirect = (string)($_POST['redirect'] ?? '');

if ($redirect === '') {
    $redirect = APP_URL;
} elseif (strpos($redirect, APP_URL) === 0) {
    // Keep existing absolute application URLs unchanged.
} else {
    $scheme = parse_url($redirect, PHP_URL_SCHEME);

    if ($scheme === null && !str_starts_with($redirect, '//')) {
        // Support legacy notification links stored as relative application paths.
        $redirect = APP_URL . ltrim($redirect, '/');
    } else {
        // Reject external and protocol-relative redirects.
        $redirect = APP_URL;
    }
}

header('Location: ' . $redirect);
exit;
