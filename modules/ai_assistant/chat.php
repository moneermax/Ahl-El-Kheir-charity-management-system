<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Vary: Cookie');
function ak_ai_json(int $status, array $data): never {
    $conversationId = (int)($GLOBALS['ak_ai_history_conversation_id'] ?? 0);
    if ($status === 200 && ($data['ok'] ?? false) === true && isset($data['mode']) && $conversationId > 0) {
        $answer = trim((string)($data['message'] ?? $data['answer'] ?? ''));
        if ($answer !== '') {
            try {
                dbExecute(
                    "INSERT INTO ai_assistant_messages (conversation_id, speaker, content, answer_mode) VALUES (?, 'assistant', ?, ?)",
                    [$conversationId, $answer, (string)$data['mode']]
                );
                dbExecute(
                    "UPDATE ai_assistant_conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?",
                    [$conversationId, (int)$GLOBALS['ak_ai_history_user_id']]
                );
                $data['conversation_id'] = $conversationId;
            } catch (Throwable $e) {
                $status = 503;
                $data = [
                    'ok' => false,
                    'message' => 'تعذر حفظ المحادثة حالياً. تحقق من تطبيق ترحيل سجل المساعد ثم حاول مجدداً. / Could not save this conversation. Verify the assistant history migration is applied, then try again.',
                ];
            }
        }
    }
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
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (!str_contains($contentType, 'application/json')) {
    ak_ai_json(415, ['ok' => false, 'message' => 'Unsupported content type.']);
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
if (!is_array($input)) {
    ak_ai_json(400, ['ok' => false, 'message' => 'طلب غير صالح. / Invalid request.']);
}
$action = (string)($input['action'] ?? 'ask');
$historyUserId = Session::getUserId();

if ($action === 'history_list' || $action === 'history_load') {
    $activeUser = dbFetchOne("SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1", [$historyUserId]);
    if (!$activeUser) {
        ak_ai_json(403, ['ok' => false, 'message' => 'تعذر التحقق من صلاحية الحساب. / Could not verify account permissions.']);
    }
    try {
        if ($action === 'history_list') {
            $conversations = dbFetchAll(
                "SELECT id, title, created_at, last_message_at FROM ai_assistant_conversations WHERE user_id = ? ORDER BY last_message_at DESC, id DESC LIMIT 50",
                [$historyUserId]
            );
            ak_ai_json(200, ['ok' => true, 'conversations' => $conversations]);
        }
        $conversationId = filter_var($input['conversation_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$conversationId || $conversationId < 1) {
            ak_ai_json(422, ['ok' => false, 'message' => 'محادثة غير صالحة. / Invalid conversation.']);
        }
        $conversation = dbFetchOne(
            "SELECT id, title, created_at, last_message_at FROM ai_assistant_conversations WHERE id = ? AND user_id = ? LIMIT 1",
            [$conversationId, $historyUserId]
        );
        if (!$conversation) {
            ak_ai_json(404, ['ok' => false, 'message' => 'المحادثة غير موجودة. / Conversation not found.']);
        }
        $messages = dbFetchAll(
            "SELECT id, speaker, content, answer_mode, created_at FROM ai_assistant_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 200",
            [$conversationId]
        );
        $messages = array_reverse($messages);
        ak_ai_json(200, ['ok' => true, 'conversation' => $conversation, 'messages' => $messages]);
    } catch (Throwable $e) {
        ak_ai_json(503, ['ok' => false, 'message' => 'سجل المحادثات غير مهيأ بعد. طبّق ترحيل قاعدة البيانات الخاص بالمساعد. / Chat history is not ready. Apply the assistant database migration.']);
    }
}

if ($action !== 'ask') {
    ak_ai_json(400, ['ok' => false, 'message' => 'إجراء غير معروف. / Unknown action.']);
}
$question = trim((string)($input['question'] ?? ''));
if ($question === '' || mb_strlen($question, 'UTF-8') > 1000) {
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

$conversationIdInput = filter_var($input['conversation_id'] ?? null, FILTER_VALIDATE_INT);
try {
    if ($conversationIdInput !== false && $conversationIdInput !== null && $conversationIdInput > 0) {
        $ownedConversation = dbFetchOne(
            "SELECT id FROM ai_assistant_conversations WHERE id = ? AND user_id = ? LIMIT 1",
            [$conversationIdInput, Session::getUserId()]
        );
        if (!$ownedConversation) {
            ak_ai_json(404, ['ok' => false, 'message' => 'المحادثة غير موجودة. / Conversation not found.']);
        }
        $conversationId = (int)$ownedConversation['id'];
    } else {
        $title = mb_substr(preg_replace('/\\s+/u', ' ', $question) ?? $question, 0, 180, 'UTF-8');
        dbExecute(
            "INSERT INTO ai_assistant_conversations (user_id, title, last_message_at) VALUES (?, ?, CURRENT_TIMESTAMP)",
            [Session::getUserId(), $title]
        );
        $conversationId = (int)dbLastInsertId();
    }
    dbExecute(
        "INSERT INTO ai_assistant_messages (conversation_id, speaker, content, answer_mode) VALUES (?, 'user', ?, NULL)",
        [$conversationId, $question]
    );
    dbExecute(
        "UPDATE ai_assistant_conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?",
        [$conversationId, Session::getUserId()]
    );
    $GLOBALS['ak_ai_history_conversation_id'] = $conversationId;
    $GLOBALS['ak_ai_history_user_id'] = Session::getUserId();
} catch (Throwable $e) {
    ak_ai_json(503, ['ok' => false, 'message' => 'تعذر حفظ سجل المحادثة. تأكد من تطبيق ترحيل قاعدة البيانات الخاص بالمساعد. / Could not save chat history. Verify the assistant database migration is applied.']);
}

function ak_ai_normalize(string $value): string {
    $value = mb_strtolower($value, 'UTF-8');
    $value = str_replace(['أ', 'إ', 'آ', 'ٱ', 'ى', 'ة'], ['ا', 'ا', 'ا', 'ا', 'ي', 'ه'], $value);
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

    $matches = [];
    foreach ($candidates as $candidate) {
        $normalized = ak_ai_normalize($candidate['text']);
        $score = 0;
        foreach ($terms as $term) {
            if (mb_strpos($normalized, $term, 0, 'UTF-8') !== false) {
                $score += mb_strlen($term, 'UTF-8') >= 6 ? 2 : 1;
            }
        }
        if ($score >= 3) {
            $matches[] = ['score' => $score, 'text' => $candidate['text']];
        }
    }
    usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    $matches = array_slice($matches, 0, 2);
    if (!$matches) return null;
    return [
        'answer' => implode("\n\n", array_unique(array_column($matches, 'text'))),
        'sources' => ['AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md'],
        'mode' => 'local_guide',
    ];
}

$local = ak_ai_local_answer($question, $role, dirname(__DIR__, 2));
if ($local !== null) {
    ak_ai_json(200, ['ok' => true] + $local);
}
if (($input['allow_google'] ?? false) !== true) {
    ak_ai_json(200, [
        'ok' => true,
        'mode' => 'not_found',
        'sources' => [],
        'message' => 'لم أجد إجابة موثقة في الدليل المحلي. لم يتم إرسال سؤالك إلى Google. يمكنك تفعيل الموافقة للأسئلة العامة غير السرية فقط. / No verified local answer was found. Your question was not sent to Google. You may opt in for general, non-confidential questions only.',
    ]);
}

$normalizedQuestion = ak_ai_normalize($question);
$restrictedTerms = [
    'ahl el kheir', 'family', 'families', 'sponsor', 'sponsorship', 'payroll',
    'salary', 'advance', 'accounting', 'disbursement', 'verification', 'nanny',
    'supervisor', 'workflow', 'permission', 'project', 'database', 'policy',
    'approval', 'attendance', 'beneficiary', 'employee', 'child', 'orphan', 'mother',
    'medical', 'health', 'diagnosis', 'address', 'phone', 'bank', 'iban', 'personal',
    'مجموعه', 'مجموعات', 'المجموعه', 'المجموعات', 'الاسره', 'الاسر', 'اسر', 'عائله', 'العائله', 'عائلات', 'العائلات', 'توثيق', 'التحقق', 'ناني',
    'المخصصه', 'مسنده', 'صرف', 'تحقق', 'مستفيد', 'النظام', 'اجراء', 'صلاحيات',
    'حافز', 'راتب', 'سلف', 'اسرة', 'كفالة', 'كفيل', 'مشرف', 'ناني', 'صرف',
    'تحقق', 'اعتماد', 'صلاحيات', 'مشروع', 'محاسبة', 'سياسة', 'حضور', 'مستفيد',
    'موظف', 'طفل', 'يتيم', 'والدة', 'عنوان', 'هاتف', 'صحي', 'طبي', 'مرض', 'بيانات', 'بنك'
];
foreach ($restrictedTerms as $term) {
    $normalizedTerm = ak_ai_normalize($term);
    $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($normalizedTerm, '/') . '(?![\p{L}\p{N}])/u';
    if ($normalizedTerm !== '' && preg_match($pattern, $normalizedQuestion) === 1) {
        ak_ai_json(200, [
            'ok' => true,
            'mode' => 'not_sent',
            'sources' => [],
            'message' => 'لم يُرسل السؤال إلى Google لأنه يتعلق بالنظام أو بإجراء داخلي، ولم أجد إجابة موثقة في الدليل المتاح لدورك. / This question was not sent to Google because it concerns the application or an internal procedure, and no verified answer was found in the guide available to your role.',
        ]);
    }
}
if (str_contains($question, '@')
    || preg_match('/[0-9]{7,}/', $question)
    || preg_match('/password|api.?key|token|national.?id|account.?number|كلمة المرور|رقم الهوية|رقم الحساب/iu', $question)) {
    ak_ai_json(200, [
        'ok' => true,
        'mode' => 'not_sent',
        'sources' => [],
        'message' => 'لم يُرسل السؤال إلى Google لأنه قد يتضمن معلومات حساسة. / This question was not sent to Google because it may contain sensitive information.',
    ]);
}

$hostingAi = isset($hostingConfig['ai']) && is_array($hostingConfig['ai']) ? $hostingConfig['ai'] : [];
$apiKey = trim((string)(getenv('GEMINI_API_KEY') ?: ($hostingAi['gemini_api_key'] ?? '')));
$model = trim((string)(getenv('GEMINI_MODEL') ?: ($hostingAi['gemini_model'] ?? 'gemini-2.5-flash')));
if ($apiKey === '' || !preg_match('/^[A-Za-z0-9._-]{1,80}$/', $model) || !function_exists('curl_init')) {
    ak_ai_json(200, [
        'ok' => true,
        'mode' => 'unconfigured',
        'sources' => [],
        'message' => 'خدمة الإجابات العامة غير مهيأة بعد. لم يتم إرسال السؤال إلى أي خدمة خارجية. / General AI is not configured. Nothing was sent to an external service.',
    ]);
}

$payload = [
    'system_instruction' => ['parts' => [['text' => 'You are a general assistant. You have no access to Ahl El Kheir internal documentation, policies, database or records. Do not invent project-specific rules. If asked about internal procedures, say you cannot verify them and direct the user to the approved local guide or responsible staff. Answer concisely in Arabic or English.']]],
    'contents' => [['role' => 'user', 'parts' => [['text' => $question]]]],
    'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 450],
];
$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 18,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$responseBody = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if (!is_string($responseBody) || $status < 200 || $status >= 300) {
    ak_ai_json(200, [
        'ok' => true,
        'mode' => 'unavailable',
        'sources' => [],
        'message' => 'تعذر الحصول على إجابة عامة الآن. استخدم الدليل المحلي أو حاول لاحقاً. / General AI is unavailable right now. Use the local guide or try again later.',
    ]);
}
$decoded = json_decode($responseBody, true);
$answer = trim((string)($decoded['candidates'][0]['content']['parts'][0]['text'] ?? ''));
if ($answer === '') {
    ak_ai_json(200, ['ok' => true, 'mode' => 'unavailable', 'message' => 'لم تصل إجابة قابلة للاستخدام. / No usable answer returned.']);
}
ak_ai_json(200, ['ok' => true, 'mode' => 'gemini_general', 'message' => $answer, 'sources' => []]);
