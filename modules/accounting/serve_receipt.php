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

// A batch may have no final/closing receipt while its individual items already
// have receipt files. In that case, fall back to the first available item receipt
// instead of incorrectly returning 404. If neither the batch nor any item has a
// receipt, ak_out_serve_receipt() keeps the normal 404 behavior.
if ($kind !== 'item') {
    $batch = ak_out_row('monthly_disbursements', $id);
    if ($batch && (string)($batch['receipt_file_path'] ?? '') === '') {
        $item = dbFetchOne(
            "SELECT id FROM disbursement_items WHERE disbursement_id = ? AND receipt_file_path IS NOT NULL AND TRIM(receipt_file_path) <> '' ORDER BY id ASC LIMIT 1",
            [$id]
        );
        if ($item) {
            $kind = 'item';
            $id = (int)$item['id'];
        }
    }
}

ak_out_serve_receipt($id, $uid, $allowed, $kind);