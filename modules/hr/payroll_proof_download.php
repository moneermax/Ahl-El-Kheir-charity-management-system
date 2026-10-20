<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_payroll_transfer.php';

Session::start();
if (!Session::isLoggedIn()) {
    http_response_code(401);
    exit('يلزم تسجيل الدخول لعرض إثبات الدفع.');
}

$pdo = db();
$payrollId = filter_input(INPUT_GET, 'payroll_id', FILTER_VALIDATE_INT);
if (!$payrollId || $payrollId < 1) {
    http_response_code(400);
    exit('مرجع إثبات الدفع غير صالح.');
}

$stmt = $pdo->prepare(
    'SELECT pp.storage_key, pp.original_filename, pp.detected_mime_type, pp.proof_state,
            p.employee_id, e.user_id AS employee_user_id
     FROM hr_payroll_payment_proofs pp
     JOIN payroll p ON p.id = pp.payroll_id
     JOIN employees e ON e.id = p.employee_id
     WHERE pp.payroll_id = ? LIMIT 1'
);
$stmt->execute([$payrollId]);
$proof = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$proof) {
    http_response_code(404);
    exit('إثبات الدفع غير موجود.');
}

$role = Session::getUserRole();
$staffAllowed = in_array($role, ['hr_manager', 'hr_staff', 'financial_manager', 'admin'], true);
$employeeOwns = (int)($proof['employee_user_id'] ?? 0) === Session::getUserId();
$employeeProofReleased = in_array($proof['proof_state'], ['confirmed', 'legacy_evidence'], true);
if (!$staffAllowed && !($employeeOwns && $employeeProofReleased)) {
    http_response_code(403);
    exit('ليس لديك صلاحية الوصول إلى إثبات الدفع.');
}

try {
    $path = hrPayrollProofPath((string)$proof['storage_key']);
} catch (Throwable $e) {
    http_response_code(404);
    exit('ملف إثبات الدفع غير متاح.');
}
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('ملف إثبات الدفع غير متاح.');
}

$mimeAllowlist = ['application/pdf', 'image/jpeg', 'image/png'];
$mime = (string)$proof['detected_mime_type'];
if (!in_array($mime, $mimeAllowlist, true)) {
    http_response_code(415);
    exit('صيغة إثبات الدفع غير مدعومة.');
}

$filename = preg_replace('/[\r\n"\\\\]/', '_', (string)$proof['original_filename']);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: attachment; filename="' . ($filename !== '' ? $filename : 'payroll-proof') . '"; filename*=UTF-8\'\'' . rawurlencode((string)$proof['original_filename']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
readfile($path);
exit;
