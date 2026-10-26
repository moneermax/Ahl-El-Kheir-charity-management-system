<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_payroll_transfer.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$payrollId = filter_var($_GET['payroll_id'] ?? null, FILTER_VALIDATE_INT);
if ($payrollId === false || $payrollId === null || $payrollId <= 0) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_payroll_id', 'message' => 'A valid payroll row is required.']]);
}
$pdo = db();
if (!hrPayrollTransferSchemaAvailable($pdo)) {
    ak_api_json(503, ['ok' => false, 'error' => ['code' => 'transfer_schema_unavailable', 'message' => 'Payroll proof storage is not configured.']]);
}

$stmt = $pdo->prepare(
    'SELECT pp.storage_key, pp.original_filename, pp.detected_mime_type, pp.proof_state,
            e.user_id AS employee_user_id
     FROM hr_payroll_payment_proofs pp
     JOIN payroll p ON p.id = pp.payroll_id
     JOIN employees e ON e.id = p.employee_id
     WHERE pp.payroll_id = ? LIMIT 1'
);
$stmt->execute([(int)$payrollId]);
$proof = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$proof) {
    ak_api_json(404, ['ok' => false, 'error' => ['code' => 'proof_not_found', 'message' => 'Payroll proof was not found.']]);
}

$role = (string)($user['raw_role_code'] ?? '');
$staffAllowed = in_array($role, ['hr_manager', 'hr_staff', 'financial_manager', 'admin'], true);
$employeeOwns = (int)($proof['employee_user_id'] ?? 0) === (int)$user['id'];
$employeeProofReleased = in_array((string)$proof['proof_state'], ['confirmed', 'legacy_evidence'], true);
if (!$staffAllowed && !($employeeOwns && $employeeProofReleased)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'You are not allowed to access this payroll proof.']]);
}

try {
    $path = hrPayrollProofPath((string)$proof['storage_key']);
} catch (Throwable $e) {
    ak_api_json(404, ['ok' => false, 'error' => ['code' => 'proof_unavailable', 'message' => 'Payroll proof is not available.']]);
}
if (!is_file($path) || !is_readable($path)) {
    ak_api_json(404, ['ok' => false, 'error' => ['code' => 'proof_unavailable', 'message' => 'Payroll proof is not available.']]);
}
$mime = (string)$proof['detected_mime_type'];
if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    ak_api_json(415, ['ok' => false, 'error' => ['code' => 'unsupported_proof_type', 'message' => 'This payroll proof format is not supported.']]);
}

$originalName = (string)$proof['original_filename'];
$safeHeaderName = preg_replace('/[\r\n"\\\\]/', '_', $originalName);
header_remove('Content-Type');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: inline; filename="' . ($safeHeaderName !== '' ? $safeHeaderName : 'payroll-proof') . '"; filename*=UTF-8\'\'' . rawurlencode($originalName));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
readfile($path);
exit;
