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

$sentCsrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($sentCsrf === '' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $sentCsrf)) {
    ak_ai_json(403, ['ok' => false, 'message' => 'انتهت صلاحية الطلب. حدّث الصفحة ثم حاول مجدداً. / Refresh the page and try again.']);
}
$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 5000) {
    ak_ai_json(413, ['ok' => false, 'message' => 'الرسالة طويلة جداً. / Message is too long.']);
}
$input = json_decode($raw, true);
$question = is_array($input) ? trim((string)($input['question'] ?? '')) : '';
if (!is_array($input) || $question === '' || mb_strlen($question, 'UTF-8') > 1000) {
    ak_ai_json(422, ['ok' => false, 'message' => 'اكتب سؤالاً لا يتجاوز 1000 حرف. / Enter a question of at most 1000 characters.']);
}
$now = time();
$recent = array_values(array_filter((array)($_SESSION['ak_ai_request_times'] ?? []), static fn($time) => is_int($time) && $time > $now - 60));
if (count($recent) >= 8) {
    ak_ai_json(429, ['ok' => false, 'message' => 'انتظر دقيقة قبل إرسال المزيد. / Please wait a minute before sending more messages.']);
}
$recent[] = $now;
$_SESSION['ak_ai_request_times'] = $recent;

$user = dbFetchOne(
    "SELECT r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.is_active = 1 LIMIT 1",
    [Session::getUserId()]
);
if (!$user || empty($user['role_code'])) {
    ak_ai_json(403, ['ok' => false, 'message' => 'تعذر التحقق من صلاحية الحساب. / Could not verify account permissions.']);
}
$role = (string)$user['role_code'];

function ak_ai_normalize(string $value): string {
    $value = mb_strtolower($value, 'UTF-8');
    $value = str_replace(['أ', 'إ', 'آ', 'ٱ', 'ى'], ['ا', 'ا', 'ا', 'ا', 'ي'], $value);
    $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value) ?? $value;
    return preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? $value;
}
