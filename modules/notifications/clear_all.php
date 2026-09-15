<?php
// modules/notifications/clear_all.php - Clear the current user's bell menu without deleting notification history
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
    $row = dbFetchOne(
        "SELECT MAX(id) AS max_id
         FROM notifications
         WHERE recipient_user_id = ?",
        [current_user_id()]
    );
    $maxId = (int)($row['max_id'] ?? 0);

    // The bell is only being cleared visually. Notification records remain
    // available on the full notifications page for history/audit purposes.
    setcookie(
        'ak_notif_menu_cleared_before',
        (string)$maxId,
        [
            'expires' => time() + 31536000,
            'path' => defined('APP_BASE_PATH') && APP_BASE_PATH !== '' ? '/' . trim(APP_BASE_PATH, '/') . '/' : '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,
            'samesite' => 'Lax',
        ]
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
