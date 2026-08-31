<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/messaging.php';

Session::start();
require_login();
header('Content-Type: application/json; charset=utf-8');

$uid = Session::getUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'طريقة الطلب غير مسموحة.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!verify_csrf()) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'انتهت صلاحية الجلسة.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? '';
$messageId = (int)($_POST['message_id'] ?? 0);

if ($action !== 'upload_attachment' || $messageId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'بيانات المرفق غير مكتملة.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!messaging_user_can_read($uid, $messageId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'ليس لديك صلاحية لإرفاق ملف بهذه الرسالة.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'لم يتم اختيار ملف.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['attachment'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'تعذر رفع الملف.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$maxSize = 10 * 1024 * 1024;
if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxSize) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'حجم الملف يجب ألا يتجاوز 10 ميجابايت.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$original = basename((string)$file['name']);
$original = preg_replace('/[^\p{L}\p{N}\s._()\-\[\]]/u', '_', $original) ?: 'attachment';
$original = mb_substr($original, 0, 240, 'UTF-8');

$allowed = [
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
    'txt' => ['text/plain', 'application/octet-stream'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'gif' => ['image/gif'],
    'webp' => ['image/webp'],
    'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
];

$ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
if (!isset($allowed[$ext])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'نوع الملف غير مسموح.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed[$ext], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'محتوى الملف لا يتطابق مع نوعه المعلن.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$storage = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'message_attachments';
if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'تعذر إنشاء مساحة تخزين المرفقات.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stored = bin2hex(random_bytes(20)) . '.' . $ext;
$target = $storage . DIRECTORY_SEPARATOR . $stored;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'تعذر حفظ المرفق.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = db()->prepare('INSERT INTO message_attachments (message_id, uploader_user_id, original_name, stored_name, mime_type, size_bytes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$messageId, $uid, $original, $stored, $mime, (int)$file['size']]);
    echo json_encode(['ok' => true, 'id' => (int)db()->lastInsertId(), 'name' => $original, 'size' => (int)$file['size'], 'message' => 'تم إرفاق الملف بنجاح.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    @unlink($target);
    error_log('message attachment insert: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'تعذر تسجيل المرفق.'], JSON_UNESCAPED_UNICODE);
}
