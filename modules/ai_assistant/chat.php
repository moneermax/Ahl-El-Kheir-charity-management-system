<?php
declare(strict_types=1);
/* Read-only assistant endpoint placeholder; no external request until configured. */
$root = dirname(__DIR__, 2);
require_once $root . '/config/config.php';
require_once $root . '/config/session.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required.']);
    exit;
}
Session::start();
if (!Session::isLoggedIn() || Session::getUserId() < 1) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please sign in first.']);
    exit;
}
echo json_encode(['ok' => true, 'message' => 'Assistant endpoint ready for implementation.']);
