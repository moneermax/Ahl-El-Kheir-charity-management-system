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

$redirect = (string)($_POST['redirect'] ?? '');

if ($redirect === '') {
    $redirect = APP_URL;
} elseif (strpos($redirect, APP_URL) === 0) {
    // Keep existing absolute application URLs unchanged.
} else {
    $scheme = parse_url($redirect, PHP_URL_SCHEME);

    if ($scheme === null && !str_starts_with($redirect, '//')) {
        // Normalize relative current-page links that may already contain the app directory.
        $relativeBase = trim(APP_BASE_PATH, '/');
        $relativeRedirect = ltrim($redirect, '/');

        if ($relativeBase !== '' && ($relativeRedirect === $relativeBase || str_starts_with($relativeRedirect, $relativeBase . '/'))) {
            $relativeRedirect = ltrim(substr($relativeRedirect, strlen($relativeBase)), '/');
        }

        $redirect = APP_URL . $relativeRedirect;
    } else {
        // Reject external and protocol-relative redirects.
        $redirect = APP_URL;
    }
}

header('Location: ' . $redirect);
exit;
