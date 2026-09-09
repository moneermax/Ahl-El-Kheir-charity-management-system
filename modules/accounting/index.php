<?php
declare(strict_types=1);

/*
 * Compatibility entry point only.
 *
 * Accounting no longer has a generic dashboard. Existing bookmarks/links
 * reaching /modules/accounting/ are routed to the user's canonical dashboard.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$target = dashboard_for_role(Session::getUserRole());

if ($target === 'index.php') {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

header('Location: ' . APP_URL . ltrim($target, '/'));
exit();
