<?php
// modules/transactions/receipt_sp.php - Authenticated streamer for sponsor_payments receipts (v2: unified support)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { http_response_code(403); exit('Forbidden'); }

$role = Session::getUserRole();
$allowed = ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'accountant', 'accountant_staff', 'financial_manager'];
if (!in_array($role, $allowed, true)) { http_response_code(403); exit('Forbidden'); }

$kind = (($_GET['kind'] ?? 'own') === 'unified') ? 'unified' : 'own';
$id = (int)($_GET['id'] ?? 0);
$sp = dbFetchOne("SELECT receipt_file_path, unified_receipt_path, supervisor_id FROM sponsor_payments WHERE id = ?", [$id]);
$path = $sp ? (($kind === 'unified') ? $sp['unified_receipt_path'] : $sp['receipt_file_path']) : null;
if (!$sp || empty($path)) { http_response_code(404); exit('Not found'); }

if ($role === 'supervisor' && (int)$sp['supervisor_id'] !== Session::getUserId()) {
    http_response_code(403); exit('Forbidden');
}

$root = realpath(dirname(__DIR__, 2));
$real = realpath($root . '/' . $path);
if ($real === false || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) { http_response_code(404); exit('Not found'); }

$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'][$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($real));
header('Cache-Control: private, max-age=300');
header('Content-Disposition: inline; filename="' . rawurlencode(basename($real)) . '"');
readfile($real);
exit;