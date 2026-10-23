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
if (!is_string($raw) || strlen($raw) > 4500000) {
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

if ($action === 'transcribe_audio') {
    $recentAudio = array_values(array_filter(
        (array)($_SESSION['ak_ai_audio_request_times'] ?? []),
        static fn($time) => is_int($time) && $time > time() - 60
    ));
    if (count($recentAudio) >= 5) {
        ak_ai_json(429, ['ok' => false, 'message' => 'انتظر دقيقة قبل استخدام الإدخال الصوتي مجدداً. / Please wait a minute before using voice input again.']);
    }
    $recentAudio[] = time();
    $_SESSION['ak_ai_audio_request_times'] = $recentAudio;

    $audioBase64 = (string)($input['audio_base64'] ?? '');
    $mimeType = strtolower(trim((string)($input['mime_type'] ?? '')));
    if (!in_array($mimeType, ['audio/webm', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/mpeg'], true)
        || $audioBase64 === ''
        || strlen($audioBase64) > 3300000
        || !preg_match('/^[A-Za-z0-9+\\/]*={0,2}$/', $audioBase64)) {
        ak_ai_json(422, ['ok' => false, 'message' => 'تعذر قراءة التسجيل الصوتي. حاول تسجيلاً أقصر أو اكتب سؤالك. / The audio recording is invalid or too large. Record a shorter clip or type your question.']);
    }
    $audioBytes = base64_decode($audioBase64, true);
    if (!is_string($audioBytes) || strlen($audioBytes) < 1000 || strlen($audioBytes) > 2400000) {
        ak_ai_json(422, ['ok' => false, 'message' => 'التسجيل الصوتي فارغ أو طويل جداً. حاول تسجيلاً أقصر أو اكتب سؤالك. / The recording is empty or too large. Record a shorter clip or type your question.']);
    }
    // Keep the validated bytes for the documented Gemini Files API upload.
    $hostingAi = isset($hostingConfig['ai']) && is_array($hostingConfig['ai']) ? $hostingConfig['ai'] : [];
    $apiKey = trim((string)(getenv('GEMINI_API_KEY') ?: ($hostingAi['gemini_api_key'] ?? '')));
    // Gemini 2.5 Flash was retired for the Gemini Developer API in October 2026.
    // Gemini 3.5 Transcribe is the current speech-to-text model and uses Interactions.
    $model = trim((string)(getenv('GEMINI_MODEL') ?: ($hostingAi['gemini_model'] ?? 'gemini-3.5-transcribe')));
    if ($apiKey === '' || !preg_match('/^[A-Za-z0-9._-]{1,80}$/', $model) || !function_exists('curl_init')) {
        ak_ai_json(503, ['ok' => false, 'message' => 'الإدخال الصوتي غير مهيأ على الخادم. يمكنك كتابة سؤالك بدلاً من ذلك. / Voice transcription is not configured on the server. You can type your question instead.']);
    }

    // Gemini's documented REST transcription flow uploads audio through Files API first,
    // then passes the returned file URI to the Interactions API.
    $apiMimeType = $mimeType === 'audio/mp4' ? 'audio/m4a' : $mimeType;
    $uploadUrl = '';
    $uploadInit = curl_init('https://generativelanguage.googleapis.com/upload/v1beta/files');
    curl_setopt_array($uploadInit, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$uploadUrl): int {
            if (stripos($line, 'x-goog-upload-url:') === 0) {
                $candidate = trim(substr($line, strlen('x-goog-upload-url:')));
                $parts = parse_url($candidate);
                if (is_array($parts) && strtolower((string)($parts['scheme'] ?? '')) === 'https'
                    && strtolower((string)($parts['host'] ?? '')) === 'generativelanguage.googleapis.com') {
                    $uploadUrl = $candidate;
                }
            }
            return strlen($line);
        },
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
            'X-Goog-Upload-Protocol: resumable',
            'X-Goog-Upload-Command: start',
            'X-Goog-Upload-Header-Content-Length: ' . strlen($audioBytes),
            'X-Goog-Upload-Header-Content-Type: ' . $mimeType,
        ],
        CURLOPT_POSTFIELDS => json_encode(['file' => ['display_name' => 'ahl-el-kheir-voice-transcription']], JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $uploadInitBody = curl_exec($uploadInit);
    $uploadInitStatus = (int)curl_getinfo($uploadInit, CURLINFO_HTTP_CODE);
    $uploadInitErrno = curl_errno($uploadInit);
    $uploadInitError = trim((string)curl_error($uploadInit));
    curl_close($uploadInit);
    if (!is_string($uploadInitBody) || $uploadInitStatus < 200 || $uploadInitStatus >= 300 || $uploadUrl === '') {
        $safeUploadError = preg_replace('/[\\r\\n]+/', ' ', $uploadInitError) ?? '';
        error_log('[Ahl El Kheir AI transcription] Files API init failed: HTTP=' . $uploadInitStatus . '; cURL errno=' . $uploadInitErrno . '; cURL error=' . mb_substr($safeUploadError, 0, 200, 'UTF-8'));
        ak_ai_json(502, ['ok' => false, 'message' => 'تعذر تجهيز التسجيل لخدمة تحويل الصوت. يمكنك كتابة سؤالك الآن. / Could not prepare the recording for transcription. You can type your question for now.']);
    }

    $uploadAudio = curl_init($uploadUrl);
    curl_setopt_array($uploadAudio, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: ' . $mimeType,
            'Content-Length: ' . strlen($audioBytes),
            'X-Goog-Upload-Offset: 0',
            'X-Goog-Upload-Command: upload, finalize',
        ],
        CURLOPT_POSTFIELDS => $audioBytes,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $uploadBody = curl_exec($uploadAudio);
    $uploadStatus = (int)curl_getinfo($uploadAudio, CURLINFO_HTTP_CODE);
    $uploadErrno = curl_errno($uploadAudio);
    $uploadError = trim((string)curl_error($uploadAudio));
    curl_close($uploadAudio);
    unset($audioBytes, $audioBase64);
    $uploadedFile = is_string($uploadBody) ? json_decode($uploadBody, true) : null;
    $fileUri = is_array($uploadedFile) ? trim((string)($uploadedFile['file']['uri'] ?? '')) : '';
    $fileUriParts = $fileUri !== '' ? parse_url($fileUri) : false;
    if (!is_string($uploadBody) || $uploadStatus < 200 || $uploadStatus >= 300
        || !is_array($fileUriParts)
        || strtolower((string)($fileUriParts['scheme'] ?? '')) !== 'https'
        || strtolower((string)($fileUriParts['host'] ?? '')) !== 'generativelanguage.googleapis.com') {
        $safeUploadError = preg_replace('/[\\r\\n]+/', ' ', $uploadError) ?? '';
        error_log('[Ahl El Kheir AI transcription] Files API upload failed: HTTP=' . $uploadStatus . '; cURL errno=' . $uploadErrno . '; cURL error=' . mb_substr($safeUploadError, 0, 200, 'UTF-8'));
        ak_ai_json(502, ['ok' => false, 'message' => 'تعذر رفع التسجيل إلى خدمة تحويل الصوت. يمكنك كتابة سؤالك الآن. / Could not upload the recording for transcription. You can type your question for now.']);
    }

    $payload = [
        'model' => $model,
        'input' => [[
            'type' => 'audio',
            'uri' => $fileUri,
            'mime_type' => $apiMimeType,
        ]],
        'generation_config' => [
            'transcription_config' => ['mode' => 'smart'],
        ],
    ];
    $url = 'https://generativelanguage.googleapis.com/v1beta/interactions';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $responseBody = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = trim((string)curl_error($ch));
    curl_close($ch);
    if (!is_string($responseBody) || $status < 200 || $status >= 300) {
        $safeCurlError = preg_replace('/[\\r\\n]+/', ' ', $curlError) ?? '';
        error_log('[Ahl El Kheir AI transcription] HTTP=' . $status . '; cURL errno=' . $curlErrno . '; cURL error=' . mb_substr($safeCurlError, 0, 240, 'UTF-8'));

        $geminiErrorStatus = '';
        $geminiErrorMessage = '';
        if (is_string($responseBody) && $responseBody !== '') {
            $errorData = json_decode($responseBody, true);
            if (is_array($errorData)) {
                $geminiErrorStatus = strtoupper(trim((string)($errorData['error']['status'] ?? '')));
                $geminiErrorMessage = trim((string)($errorData['error']['message'] ?? ''));
            }
        }
        $safeGeminiError = preg_replace('/[\\r\\n]+/', ' ', $geminiErrorStatus . ' ' . $geminiErrorMessage) ?? '';
        if ($safeGeminiError !== '') {
            error_log('[Ahl El Kheir AI transcription] Gemini response: ' . mb_substr($safeGeminiError, 0, 400, 'UTF-8'));
        }

        // Give the user a useful, non-technical next step without exposing provider details.
        if ($curlErrno !== 0 || !is_string($responseBody)) {
            $failureMessage = 'تعذر الاتصال بخدمة تحويل الصوت. يمكنك المحاولة لاحقاً أو كتابة سؤالك. / Could not reach the transcription service. Please try later or type your question.';
        } elseif ($status === 401 || $status === 403 || str_contains($geminiErrorStatus, 'UNAUTHENTICATED') || str_contains($geminiErrorStatus, 'PERMISSION_DENIED') || stripos($geminiErrorMessage, 'api key') !== false) {
            $failureMessage = 'إعداد الاتصال بخدمة تحويل الصوت يحتاج إلى مراجعة من مسؤول النظام. يمكنك كتابة سؤالك الآن. / The transcription service setup needs administrator attention. You can type your question for now.';
        } elseif ($status === 429 || str_contains($geminiErrorStatus, 'RESOURCE_EXHAUSTED')) {
            $failureMessage = 'خدمة تحويل الصوت مشغولة أو وصلت إلى حد الاستخدام المؤقت. حاول لاحقاً أو اكتب سؤالك. / The transcription service is temporarily rate-limited. Please try later or type your question.';
        } elseif ($status === 404 || str_contains($geminiErrorStatus, 'NOT_FOUND')) {
            $failureMessage = 'إعداد نموذج تحويل الصوت يحتاج إلى مراجعة من مسؤول النظام. يمكنك كتابة سؤالك الآن. / The transcription model setup needs administrator attention. You can type your question for now.';
        } elseif ($status === 400 || str_contains($geminiErrorStatus, 'INVALID_ARGUMENT')) {
            $failureMessage = 'تعذر قراءة صيغة هذا التسجيل. جرّب تسجيلاً قصيراً آخر أو اكتب سؤالك. / This recording format could not be processed. Try a short new recording or type your question.';
        } else {
            $failureMessage = 'تعذر تحويل الصوت إلى نص الآن. يمكنك المحاولة لاحقاً أو كتابة سؤالك. / Audio transcription is temporarily unavailable. Please try later or type your question.';
        }
        ak_ai_json(502, ['ok' => false, 'message' => $failureMessage]);
    }
    $decoded = json_decode($responseBody, true);
    $transcript = trim((string)($decoded['output_text'] ?? ''));
    if ($transcript === '' && is_array($decoded['steps'] ?? null)) {
        foreach ($decoded['steps'] as $step) {
            foreach ((array)($step['content'] ?? []) as $part) {
                if (isset($part['text']) && is_string($part['text'])) {
                    $transcript .= ($transcript === '' ? '' : "\n") . $part['text'];
                }
            }
        }
        $transcript = trim($transcript);
    }
    if ($transcript === '' || mb_strlen($transcript, 'UTF-8') > 1000) {
        // Diagnose the response contract without logging audio, API keys, or any returned transcript.
        $responseShape = [
            'json_valid' => is_array($decoded),
            'top_level_keys' => is_array($decoded) ? array_slice(array_keys($decoded), 0, 20) : [],
            'interaction_status' => is_array($decoded) ? (string)($decoded['status'] ?? '') : '',
            'output_text_length' => is_array($decoded) ? mb_strlen((string)($decoded['output_text'] ?? ''), 'UTF-8') : 0,
            'steps_count' => is_array($decoded) && is_array($decoded['steps'] ?? null) ? count($decoded['steps']) : 0,
            'output_count' => is_array($decoded) && is_array($decoded['output'] ?? null) ? count($decoded['output']) : 0,
        ];
        error_log('[Ahl El Kheir AI transcription] Empty transcript response shape: ' . json_encode($responseShape, JSON_UNESCAPED_SLASHES));
        ak_ai_json(422, ['ok' => false, 'message' => 'استجابت خدمة تحويل الصوت بصيغة غير متوقعة. تم تسجيل تفاصيل فنية آمنة للمراجعة؛ يمكنك كتابة سؤالك الآن. / The transcription service returned an unexpected response. Safe technical diagnostics were logged; you can type your question for now.']);
    }
    ak_ai_json(200, ['ok' => true, 'transcript' => $transcript]);
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
    $role = strtolower(trim($role));
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

    // VGM group/nanny assignment is an internal workflow; answer only for roles authorized by the source page.
    if (in_array(strtolower(trim($role)), ['vice_general_manager', 'vgm'], true)
        && str_contains($normalizedQuestion, 'حاضن')
        && (str_contains($normalizedQuestion, 'توزيع') || str_contains($normalizedQuestion, 'الاسر') || str_contains($normalizedQuestion, 'مجموعه') || str_contains($normalizedQuestion, 'مجموعات'))) {
        return [
            'answer' => 'افتح «إدارة مجموعات الأيتام» من صلاحيات نائب المدير العام (VGM). أنشئ مجموعة كمسودة وافتحها، ثم أضف الأيتام المؤهلين من الأسر النشطة (يشترط أن يكون اليتيم نشطاً وله كفالة نشطة). من قسم «الحاضنة الحالية وتعيين الحاضنة» اختر الحاضنة واضغط «تعيين». التعيين يكون للمجموعة وليس للأسرة مباشرة. أضف الأعضاء قبل تفعيل المجموعة؛ ولا يمكن تعديل العضوية بعد إنشاء صرف الشهر الحالي.',
            'sources' => ['AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md'],
            'mode' => 'local_guide',
        ];
    }

    // Common Nanny navigation question: answer deterministically from the reviewed local guide.
    // This avoids relying on keyword ranking for a high-frequency, role-specific task.
    if (strtolower(trim($role)) === 'nanny'
        && str_contains($normalizedQuestion, 'المجموعات')
        && (str_contains($normalizedQuestion, 'مخصص') || str_contains($normalizedQuestion, 'مسند') || str_contains($normalizedQuestion, 'لي'))) {
        return [
            'answer' => 'لعرض المجموعات المسندة إليك، افتحي «توثيق المجموعات والعائلات» من لوحة الناني، ثم اختاري المجموعة لعرض الأسر التابعة لها. ولعرض الأسر النشطة في جميع مجموعاتك النشطة في صفحة واحدة، افتحي «التحقق المسبق للأسر». اختاري شهر الصرف الصحيح قبل مراجعة التحقق أو تعديله.',
            'sources' => ['AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md'],
            'mode' => 'local_guide',
        ];
    }

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
$model = trim((string)(getenv('GEMINI_MODEL') ?: ($hostingAi['gemini_model'] ?? 'gemini-3.5-flash-lite')));
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
