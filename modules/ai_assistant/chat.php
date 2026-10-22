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

function ak_ai_allowed_sections(string $role): array {
    $map = [
        'admin' => ['## 1. All employees'],
        'sudo' => ['## 1. All employees'],
        'super_admin' => ['## 1. All employees'],
        'general_manager' => ['## 1. All employees', '## 2. GM'],
        'gm' => ['## 1. All employees', '## 2. GM'],
        'vice_general_manager' => ['## 1. All employees', '## 3. VGM'],
        'vgm' => ['## 1. All employees', '## 3. VGM'],
        'financial_manager' => ['## 1. All employees', '## 4. FM'],
        'fm' => ['## 1. All employees', '## 4. FM'],
        'finance' => ['## 1. All employees', '## 4. FM'],
        'accountant' => ['## 1. All employees', '## 5. Accountant / Accountant Staff'],
        'accountant_staff' => ['## 1. All employees', '## 5. Accountant / Accountant Staff'],
        'supervisor' => ['## 1. All employees', '## 6. Supervisor'],
        'nanny' => ['## 1. All employees', '## 7. Nanny'],
        'administration' => ['## 1. All employees', '## 8. Administration'],
        'staff' => ['## 1. All employees', '## 9. Staff'],
        'social_media' => ['## 1. All employees', '## 10. Social Media'],
        'projects_manager' => ['## 1. All employees', '## 11. PM'],
        'project_supervisor' => ['## 1. All employees', '## 12. PS'],
        'hr_manager' => ['## 1. All employees', '## 13. HR Manager / HR Staff'],
        'hr_staff' => ['## 1. All employees', '## 13. HR Manager / HR Staff'],
    ];
    return $map[$role] ?? ['## 1. All employees'];
}

function ak_ai_local_answer(string $question, string $role, string $root): ?array {
    $path = $root . '/docs/AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md';
    if (!is_file($path) || !is_readable($path)) return null;
    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') return null;
    $allowed = ak_ai_allowed_sections($role);
    $section = '';
    $paragraph = '';
    $candidates = [];

    $flush = static function () use (&$paragraph, &$section, &$candidates, $allowed): void {
        $text = trim($paragraph);
        if ($text !== '' && in_array($section, $allowed, true) && mb_strlen($text, 'UTF-8') >= 25 && mb_strlen($text, 'UTF-8') <= 1800) {
            $candidates[] = ['section' => $section, 'text' => $text];
        }
        $paragraph = '';
    };
    foreach (preg_split('/\R/u', $content) ?: [] as $line) {
        if (str_starts_with($line, '## ')) {
            $flush();
            $section = trim($line);
        } elseif (trim($line) === '' || preg_match('/^#{1,4}\s/u', $line) || str_starts_with(trim($line), '|')) {
            $flush();
        } else {
            $paragraph .= ($paragraph === '' ? '' : "\n") . $line;
        }
    }
    $flush();
    $normalizedQuestion = ak_ai_normalize($question);
    $terms = array_values(array_unique(array_filter(
        preg_split('/\s+/u', $normalizedQuestion) ?: [],
        static fn(string $term): bool => mb_strlen($term, 'UTF-8') >= 3
    )));
    if (!$terms) return null;
