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

function attachment_json(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function attachment_storage_path(): string {
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'message_attachments';
}

function attachment_allowed_types(): array {
    return [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'ppt'  => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'txt'  => ['text/plain', 'application/octet-stream'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list') {
    $messageId = (int)($_GET['message_id'] ?? 0);
    if ($messageId <= 0 || !messaging_user_can_read($uid, $messageId)) {
        attachment_json(['ok' => false, 'message' => 'ليس لديك صلاحية لعرض مرفقات هذه الرسالة.'], 403);
    }

    $rows = dbFetchAll(
        "SELECT a.id,a.message_id,a.original_name,a.mime_type,a.size_bytes,a.created_at,u.full_name uploader_name
         FROM message_attachments a
         JOIN users u ON u.id=a.uploader_user_id
         WHERE a.message_id=? OR a.message_id IN (SELECT id FROM messages WHERE parent_id=?)
         ORDER BY a.id ASC",
        [$messageId, $messageId]
    );

    foreach ($rows as &$row) {
        $row['download_url'] = APP_URL . 'modules/messages/download_attachment.php?id=' . (int)$row['id'];
        $bytes = (int)$row['size_bytes'];
        $row['size_label'] = $bytes >= 1048576
            ? number_format($bytes / 1048576, 1) . ' MB'
            : number_format(max(1, $bytes / 1024), 1) . ' KB';
        $row['can_delete'] = ((int)$row['uploader_user_id'] === $uid || messaging_role_for_user($uid) === 'admin');
        unset($row['uploader_user_id'], $row['stored_name']);
    }
    unset($row);

    attachment_json(['ok' => true, 'attachments' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    attachment_json(['ok' => false, 'message' => 'طريقة الطلب غير مسموحة.'], 405);
}

if (!verify_csrf()) {
    attachment_json(['ok' => false, 'message' => 'انتهت صلاحية الجلسة.'], 419);
}

$action = (string)($_POST['action'] ?? '');

if ($action === 'delete_attachment') {
    $attachmentId = (int)($_POST['attachment_id'] ?? 0);
    if ($attachmentId <= 0) {
        attachment_json(['ok' => false, 'message' => 'المرفق غير صالح.'], 422);
    }

    $row = dbFetchOne(
        "SELECT a.*,m.id message_id,m.sender_id,m.recipient_user_id,m.recipient_role
         FROM message_attachments a
         JOIN messages m ON m.id=a.message_id
         WHERE a.id=? LIMIT 1",
        [$attachmentId]
    );
    if (!$row) attachment_json(['ok' => false, 'message' => 'المرفق غير موجود.'], 404);
    if (!messaging_user_can_read($uid, (int)$row['message_id'])) {
        attachment_json(['ok' => false, 'message' => 'ليس لديك صلاحية لحذف هذا المرفق.'], 403);
    }

    $isOwner = (int)$row['uploader_user_id'] === $uid;
    $isAdmin = messaging_role_for_user($uid) === 'admin';
    if (!$isOwner && !$isAdmin) {
        attachment_json(['ok' => false, 'message' => 'يمكن حذف المرفق بواسطة صاحبه أو مدير النظام فقط.'], 403);
    }

    $path = attachment_storage_path() . DIRECTORY_SEPARATOR . basename((string)$row['stored_name']);
    try {
        dbExecute('DELETE FROM message_attachments WHERE id=?', [$attachmentId]);
        if (is_file($path)) @unlink($path);
        error_log('message attachment deleted: id=' . $attachmentId . ' by user=' . $uid);
        attachment_json(['ok' => true, 'message' => 'تم حذف المرفق.']);
    } catch (Throwable $e) {
        error_log('message attachment delete: ' . $e->getMessage());
        attachment_json(['ok' => false, 'message' => 'تعذر حذف المرفق.'], 500);
    }
}

if ($action !== 'upload_attachment') {
    attachment_json(['ok' => false, 'message' => 'طلب مرفق غير معروف.'], 400);
}

$messageId = (int)($_POST['message_id'] ?? 0);
if ($messageId <= 0) {
    attachment_json(['ok' => false, 'message' => 'بيانات المرفق غير مكتملة.'], 422);
}
if (!messaging_user_can_read($uid, $messageId)) {
    attachment_json(['ok' => false, 'message' => 'ليس لديك صلاحية لإرفاق ملف بهذه الرسالة.'], 403);
}
if (empty($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
    attachment_json(['ok' => false, 'message' => 'لم يتم اختيار ملف.'], 422);
}

$file = $_FILES['attachment'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    attachment_json(['ok' => false, 'message' => 'تعذر رفع الملف.'], 422);
}

$maxSize = 10 * 1024 * 1024;
if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxSize) {
    attachment_json(['ok' => false, 'message' => 'حجم الملف يجب ألا يتجاوز 10 ميجابايت.'], 422);
}

$original = basename((string)$file['name']);
$original = preg_replace('/[^\p{L}\p{N}\s._()\-\[\]]/u', '_', $original) ?: 'attachment';
$original = mb_substr($original, 0, 240, 'UTF-8');
$allowed = attachment_allowed_types();
$ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
if (!isset($allowed[$ext])) {
    attachment_json(['ok' => false, 'message' => 'نوع الملف غير مسموح.'], 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed[$ext], true)) {
    attachment_json(['ok' => false, 'message' => 'محتوى الملف لا يتطابق مع نوعه المعلن.'], 422);
}

$storage = attachment_storage_path();
if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
    attachment_json(['ok' => false, 'message' => 'تعذر إنشاء مساحة تخزين المرفقات.'], 500);
}

$stored = bin2hex(random_bytes(20)) . '.' . $ext;
$target = $storage . DIRECTORY_SEPARATOR . $stored;
if (!move_uploaded_file($file['tmp_name'], $target)) {
    attachment_json(['ok' => false, 'message' => 'تعذر حفظ المرفق.'], 500);
}

try {
    $stmt = db()->prepare(
        'INSERT INTO message_attachments (message_id,uploader_user_id,original_name,stored_name,mime_type,size_bytes,created_at) VALUES (?,?,?,?,?,?,NOW())'
    );
    $stmt->execute([$messageId, $uid, $original, $stored, $mime, (int)$file['size']]);
    attachment_json([
        'ok' => true,
        'id' => (int)db()->lastInsertId(),
        'name' => $original,
        'size' => (int)$file['size'],
        'message' => 'تم إرفاق الملف بنجاح.'
    ]);
} catch (Throwable $e) {
    @unlink($target);
    error_log('message attachment insert: ' . $e->getMessage());
    attachment_json(['ok' => false, 'message' => 'تعذر تسجيل المرفق.'], 500);
}
