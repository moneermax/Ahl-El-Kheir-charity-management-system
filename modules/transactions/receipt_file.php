<<<<<<< HEAD
<?php
// modules/transactions/receipt_file.php - Authenticated streamer for transaction receipts (v2: unified receipt support)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { http_response_code(403); exit('Forbidden'); }

$role = Session::getUserRole();
$allowed = ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'accountant', 'accountant_staff', 'financial_manager'];
if (!in_array($role, $allowed, true)) { http_response_code(403); exit('Forbidden'); }

dbExecute("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS receipt_path VARCHAR(255) NULL");
dbExecute("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS unified_receipt_path VARCHAR(255) NULL");

$kind = (($_GET['kind'] ?? 'own') === 'unified') ? 'unified' : 'own';
$id = (int)($_GET['id'] ?? 0);
$tx = dbFetchOne("SELECT t.receipt_path, t.unified_receipt_path, s.supervisor_id AS sp_sup, s.first_letter_id
                  FROM transactions t LEFT JOIN sponsors s ON s.id = t.sponsor_id WHERE t.id = ?", [$id]);
$path = $tx ? (($kind === 'unified') ? $tx['unified_receipt_path'] : $tx['receipt_path']) : null;
if (!$tx || empty($path)) { http_response_code(404); exit('Not found'); }

if ($role === 'supervisor') {
    $mine = ((int)($tx['sp_sup'] ?? 0) === Session::getUserId());
    if (!$mine && $tx['first_letter_id'] !== null) {
        $myLetterIds = array_map('intval', array_column(dbFetchAll("SELECT letter_id FROM supervisor_letters WHERE supervisor_id = ?", [Session::getUserId()]), 'letter_id'));
        $mine = in_array((int)$tx['first_letter_id'], $myLetterIds, true);
    }
    if (!$mine) { http_response_code(403); exit('Forbidden'); }
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
=======
<?php
// modules/transactions/receipt_file.php - Authenticated streamer for transaction receipts (v2: unified receipt support)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { http_response_code(403); exit('Forbidden'); }

$role = Session::getUserRole();
$allowed = ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'accountant', 'accountant_staff', 'financial_manager'];
if (!in_array($role, $allowed, true)) { http_response_code(403); exit('Forbidden'); }

dbExecute("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS receipt_path VARCHAR(255) NULL");
dbExecute("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS unified_receipt_path VARCHAR(255) NULL");

$kind = (($_GET['kind'] ?? 'own') === 'unified') ? 'unified' : 'own';
$id = (int)($_GET['id'] ?? 0);
$tx = dbFetchOne("SELECT t.receipt_path, t.unified_receipt_path, s.supervisor_id AS sp_sup, s.first_letter_id
                  FROM transactions t LEFT JOIN sponsors s ON s.id = t.sponsor_id WHERE t.id = ?", [$id]);
$path = $tx ? (($kind === 'unified') ? $tx['unified_receipt_path'] : $tx['receipt_path']) : null;
if (!$tx || empty($path)) { http_response_code(404); exit('Not found'); }

if ($role === 'supervisor') {
    $mine = ((int)($tx['sp_sup'] ?? 0) === Session::getUserId());
    if (!$mine && $tx['first_letter_id'] !== null) {
        $myLetterIds = array_map('intval', array_column(dbFetchAll("SELECT letter_id FROM supervisor_letters WHERE supervisor_id = ?", [Session::getUserId()]), 'letter_id'));
        $mine = in_array((int)$tx['first_letter_id'], $myLetterIds, true);
    }
    if (!$mine) { http_response_code(403); exit('Forbidden'); }
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
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
exit;