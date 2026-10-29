<?php
// Public, read-only lookup for the supervisor assigned by the existing sponsor letter + gender matrix.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/sponsor_assignments.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['assigned' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim((string)($_GET['name'] ?? ''));
$gender = strtolower(trim((string)($_GET['gender'] ?? '')));
if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) > 1020 || !in_array($gender, ['male', 'female'], true)) {
    echo json_encode(['assigned' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$supervisorId = resolveSponsorSupervisorId($name, $gender);
if (!$supervisorId) {
    echo json_encode(['assigned' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$supervisor = dbFetchOne(
    "SELECT u.full_name
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.id = ? AND u.is_active = 1 AND r.code = 'supervisor'
     LIMIT 1",
    [$supervisorId]
);

if (!$supervisor || trim((string)($supervisor['full_name'] ?? '')) === '') {
    echo json_encode(['assigned' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'assigned' => true,
    'supervisor_name' => (string)$supervisor['full_name'],
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
