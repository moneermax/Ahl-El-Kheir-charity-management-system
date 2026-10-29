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
$redirect = (string)($_POST['redirect'] ?? '');

// Repair legacy sponsor-request notifications at click time. Older rows point
// to the management queue, which intentionally denies supervisor access.
if ($notificationId > 0 && Session::getUserRole() === 'supervisor') {
    try {
        $notification = dbFetchOne(
            "SELECT type, body, link FROM notifications WHERE id = ? AND recipient_user_id = ? LIMIT 1",
            [$notificationId, current_user_id()]
        );
        if ($notification && (string)($notification['type'] ?? '') === 'sponsor_request') {
            $storedLink = trim((string)($notification['link'] ?? ''));
            if (strpos($storedLink, 'modules/sponsors/my_request.php') === false) {
                $request = dbFetchOne(
                    "SELECT id FROM sponsor_requests
                     WHERE assigned_supervisor_id = ?
                       AND CONCAT(COALESCE(sponsor_name, ''), ' — ', COALESCE(phone, '')) = ?
                     ORDER BY id DESC LIMIT 1",
                    [current_user_id(), (string)($notification['body'] ?? '')]
                );
                if ($request) {
                    $relativeLink = 'modules/sponsors/my_request.php?id=' . (int)$request['id'];
                    $redirect = APP_URL . $relativeLink;
                    dbExecute(
                        "UPDATE notifications SET link = ? WHERE id = ? AND recipient_user_id = ?",
                        [$relativeLink, $notificationId, current_user_id()]
                    );
                } else {
                    // Never send a supervisor to the unrestricted management queue.
                    $redirect = $notificationPageUrl;
                }
            } else {
                $redirect = APP_URL . ltrim($storedLink, '/');
            }
        }
    } catch (Throwable $e) {
        // On lookup failure, do not grant access through the management queue.
        $redirect = $notificationPageUrl;
    }
}

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

// The salary-advance FM review page was consolidated into the unified
// processing page. Normalize legacy notification redirects at click time as
// well as through the cleanup migration, so old notifications remain usable
// even if the migration has not yet been run on an existing database.
$legacySalaryAdvancePath = 'modules/hr/salary_advance_fm_review.php';
$newSalaryAdvancePath = 'modules/hr/salary_advance_processing.php';
if ($redirect !== '' && strpos($redirect, $legacySalaryAdvancePath) !== false) {
    $redirect = str_replace($legacySalaryAdvancePath, $newSalaryAdvancePath, $redirect);
    if ($notificationId > 0) {
        try {
            dbExecute(
                "UPDATE notifications
                 SET link = REPLACE(link, ?, ?)
                 WHERE id = ?
                   AND recipient_user_id = ?",
                [$legacySalaryAdvancePath, $newSalaryAdvancePath, $notificationId, current_user_id()]
            );
        } catch (Throwable $e) {
            // Redirect normalization remains effective even if cleanup fails.
        }
    }
}

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
