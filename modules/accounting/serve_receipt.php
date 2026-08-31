<?php
// modules/accounting/serve_receipt.php — Secure receipt viewer
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Never show errors in file output

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_outflows.php';

Session::start();
if (!Session::isLoggedIn()) {
    http_response_code(403);
    exit('Unauthorized');
}

$uid = (int)Session::getUserId();
$role = (string)Session::getUserRole();
$allowed = in_array($role, ['admin', 'financial_manager', 'general_manager', 'vice_general_manager', 'accountant', 'accountant_staff'], true);

$id = (int)($_GET['id'] ?? 0);
$kind = (string)($_GET['kind'] ?? 'batch'); // 'batch' or 'item'

if ($id <= 0) {
    http_response_code(400);
    exit('Invalid ID');
}

ak_out_serve_receipt($id, $uid, $allowed, $kind);