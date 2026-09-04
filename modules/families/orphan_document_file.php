<?php
// modules/families/orphan_document_file.php - Authenticated streamer for orphan documents
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { http_response_code(403); exit(t('families.file_forbidden')); }
$role = Session::getUserRole();
$allowed = ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny', 'accountant', 'accountant_staff', 'financial_manager'];
if (!in_array($role, $allowed, true)) { http_response_code(403); exit(t('families.file_forbidden')); }
$id = (int)($_GET['id'] ?? 0);
$doc = dbFetchOne("SELECT d.*, f.nanny_id FROM orphan_documents d LEFT JOIN families f ON f.id = d.family_id WHERE d.id = ?", [$id]);
if (!$doc) { http_response_code(404); exit(t('families.file_not_found')); }
if ($role === 'nanny' && (int)($doc['nanny_id'] ?? 0) !== Session::getUserId()) { http_response_code(403); exit(t('families.file_forbidden')); }
$root = realpath(dirname(__DIR__, 2));
$real = realpath($root . '/' . $doc['file_path']);
if ($real === false || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) { http_response_code(404); exit(t('families.file_not_found')); }
$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'][$ext] ?? 'application/octet-stream';
$mode = (($_GET['mode'] ?? 'view') === 'download') ? 'download' : 'view';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($real));
header('Cache-Control: private, max-age=300');
header('Content-Disposition: ' . ($mode === 'download' ? 'attachment' : 'inline') . '; filename="' . rawurlencode((string)$doc['file_name']) . '"');
readfile($real);
exit;