<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function ak_ai_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    ak_ai_json(405, ['ok' => false, 'message' => 'POST required.']);
}
Session::start();
if (!Session::isLoggedIn()) {
    ak_ai_json(401, ['ok' => false, 'message' => 'يرجى تسجيل الدخول أولاً. / Please sign in first.']);
}
