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

$notificationPageUrl = APP_URL . 'modules/notifications/index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $notificationPageUrl);
    exit;
}

if (!verify_csrf()) {
    flash('error', 'انتهت جلسة الأمان. يرجى المحاولة مرة أخرى.');
    header('Location: ' . $notificationPageUrl);
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
    $redirect = $notificationPageUrl;
} elseif (strpos($redirect, APP_URL) === 0) {
    // Keep existing absolute application URLs unchanged.
} else {
    $scheme = parse_url($redirect, PHP_URL_SCHEME);

    if ($scheme === null && !str_starts_with($redirect, '//')) {
        // Normalize legacy relative links that may already contain the app directory.
        $relativeBase = trim(APP_BASE_PATH, '/');
        $relativeRedirect = ltrim($redirect, '/');

        if ($relativeBase !== '' && ($relativeRedirect === $relativeBase || str_starts_with($relativeRedirect, $relativeBase . '/'))) {
            $relativeRedirect = ltrim(substr($relativeRedirect, strlen($relativeBase)), '/');
        }

        $redirect = $relativeRedirect !== ''
            ? APP_URL . $relativeRedirect
            : $notificationPageUrl;
    } else {
        // Reject external and protocol-relative redirects.
        $redirect = $notificationPageUrl;
    }
}

header('Location: ' . $redirect);
exit;
