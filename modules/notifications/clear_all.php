<?php
// modules/notifications/clear_all.php - Delete all notification history for the current user
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

try {
    // Delete only this user's notification records. This does not affect
    // notifications belonging to any other user.
    dbExecute(
        "DELETE FROM notifications WHERE recipient_user_id = ?",
        [current_user_id()]
    );

} catch (Throwable $e) {
    flash('error', 'تعذر حذف الإشعارات. يرجى المحاولة مرة أخرى.');
}

$redirect = (string)($_POST['redirect'] ?? '');

if ($redirect === '') {
    $redirect = APP_URL;
} elseif (strpos($redirect, APP_URL) === 0) {
    // Keep existing absolute application URLs unchanged.
} else {
    $scheme = parse_url($redirect, PHP_URL_SCHEME);

    if ($scheme === null && !str_starts_with($redirect, '//')) {
        $relativeBase = trim(APP_BASE_PATH, '/');
        $relativeRedirect = ltrim($redirect, '/');

        if ($relativeBase !== '' && ($relativeRedirect === $relativeBase || str_starts_with($relativeRedirect, $relativeBase . '/'))) {
            $relativeRedirect = ltrim(substr($relativeRedirect, strlen($relativeBase)), '/');
        }

        $redirect = APP_URL . $relativeRedirect;
    } else {
        $redirect = APP_URL;
    }
}

header('Location: ' . $redirect);
exit;
