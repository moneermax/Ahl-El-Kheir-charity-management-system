<?php
// modules/accounting/disbursement_receipt.php - Authenticated streamer for disbursement receipts
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();

// 1. Check if user is logged in
if (!Session::isLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}

$role = Session::getUserRole();
$uid = Session::getUserId();

// 2. Check user role permissions
$allowedRoles = ['admin', 'accountant', 'accountant_staff', 'financial_manager', 'nanny', 'general_manager'];
if (!in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    exit('Forbidden');
}

// 3. Get the disbursement ID from GET parameter
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid ID');
}

// 4. Fetch the disbursement record
$disbursement = dbFetchOne("
    SELECT d.id, d.receipt_file_path, d.nanny_id, d.status, d.month,
           n.username as nanny_username
    FROM monthly_disbursements d
    LEFT JOIN users n ON n.id = d.nanny_id
    WHERE d.id = ?
", [$id]);

// 5. Verify disbursement exists and has a receipt
if (!$disbursement || empty($disbursement['receipt_file_path'])) {
    http_response_code(404);
    exit('Receipt not found');
}

// 6. Verify user has permission to view this receipt
$hasPermission = false;

if (in_array($role, ['admin', 'financial_manager', 'general_manager'], true)) {
    // Admins and financial managers can view all receipts
    $hasPermission = true;
} elseif ($role === 'accountant' || $role === 'accountant_staff') {
    // Accountants can view receipts for nannies they manage
    if ($role === 'accountant') {
        $hasPermission = true; // All accountants can view
    } else {
        // Accountant staff can only view their assigned nannies
        $assignment = dbFetchOne("
            SELECT 1 FROM accountant_nanny_assignments 
            WHERE accountant_id = ? AND nanny_id = ?
        ", [$uid, $disbursement['nanny_id']]);
        $hasPermission = (bool)$assignment;
    }
} elseif ($role === 'nanny') {
    // Nannies can only view their own receipts
    $hasPermission = ((int)$disbursement['nanny_id'] === $uid);
}

if (!$hasPermission) {
    http_response_code(403);
    exit('Forbidden - You do not have permission to view this receipt');
}

// 7. Build the full file path
$filePath = dirname(__DIR__, 2) . '/' . $disbursement['receipt_file_path'];

// 8. Verify file exists and is readable
if (!file_exists($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('Receipt file not found on server');
}

// 9. Get file info
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($filePath);
$filename = basename($filePath);

// 10. Set headers and stream the file
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: private');
header('Expires: 0');

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Stream the file
readfile($filePath);
exit();