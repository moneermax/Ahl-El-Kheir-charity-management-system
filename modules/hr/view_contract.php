<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib_contract_salary.php';

Session::start();

$allowedRoles = ['hr_manager', 'hr_staff', 'admin'];
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), $allowedRoles, true)) {
    http_response_code(403);
    exit('ليس لديك صلاحية عرض ملفات عقود العمل.');
}

$contractId = filter_input(INPUT_GET, 'contract_id', FILTER_VALIDATE_INT);
if (!$contractId || $contractId <= 0) {
    http_response_code(404);
    exit('ملف العقد غير موجود.');
}

$contract = hrGetEmployeeContract((int)$contractId);
if (!$contract || empty($contract['contract_file_path'])) {
    http_response_code(404);
    exit('ملف العقد غير موجود.');
}

$relativePath = str_replace('\\', '/', (string)$contract['contract_file_path']);
$expectedPrefix = 'storage/hr_contracts/';
if (!str_starts_with($relativePath, $expectedPrefix)) {
    http_response_code(404);
    exit('ملف العقد غير موجود.');
}

$fileName = substr($relativePath, strlen($expectedPrefix));
if ($fileName === '' || basename($fileName) !== $fileName || str_contains($fileName, '..')) {
    http_response_code(404);
    exit('ملف العقد غير موجود.');
}

$storageDirectory = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'hr_contracts');
$filePath = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
if ($storageDirectory === false || $filePath === false || !is_file($filePath)) {
    http_response_code(404);
    exit('ملف العقد غير موجود.');
}

$storagePrefix = rtrim($storageDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strncmp($filePath, $storagePrefix, strlen($storagePrefix)) !== 0) {
    http_response_code(404);
    exit('ملف العقد غير موجود.');
}

$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowedMimeTypes = [
    'pdf' => ['application/pdf'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
];
if (!isset($allowedMimeTypes[$extension])) {
    http_response_code(415);
    exit('صيغة ملف العقد غير مدعومة.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath);
if (!is_string($mimeType) || !in_array($mimeType, $allowedMimeTypes[$extension], true)) {
    http_response_code(415);
    exit('محتوى ملف العقد لا يطابق صيغته المسجلة.');
}

$mode = (string)($_GET['mode'] ?? 'preview');
if ($mode === 'file') {
    $download = isset($_GET['download']) && $_GET['download'] === '1';
    $disposition = $download ? 'attachment' : 'inline';
    $safeFileName = str_replace(["\\r", "\\n", '"'], '', $fileName);

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string)filesize($filePath));
    header("Content-Disposition: {$disposition}; filename=\"" . $safeFileName . "\"");
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    readfile($filePath);
    exit;
}

if ($mode !== 'preview') {
    http_response_code(400);
    exit('طلب غير صالح.');
}

$baseUrl = APP_URL . 'modules/hr/view_contract.php?contract_id=' . (int)$contractId;
$fileUrl = $baseUrl . '&mode=file';
$downloadUrl = $fileUrl . '&download=1';
$safeTitle = htmlspecialchars($fileName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$isPdf = $extension === 'pdf';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>معاينة ملف العقد</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f5f8;color:#182230;font-family:Tahoma,Arial,sans-serif}
.toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:12px 18px;background:#fff;border-bottom:1px solid #d0d5dd;position:sticky;top:0;z-index:2}
.filename{font-size:14px;overflow-wrap:anywhere;flex:1;min-width:180px}
.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:7px;padding:9px 14px;text-decoration:none;font-size:14px;cursor:pointer;background:#175cd3;color:#fff}
.btn.secondary{background:#475467}.preview{height:calc(100vh - 62px);padding:12px}
iframe{width:100%;height:100%;border:1px solid #d0d5dd;border-radius:6px;background:#fff}
img{display:block;max-width:100%;max-height:100%;margin:auto;object-fit:contain;background:#fff}
@media print{.toolbar{display:none}.preview{height:auto;padding:0}iframe{height:100vh;border:0}}
</style>
</head>
<body>
<header class="toolbar">
  <strong class="filename">معاينة ملف العقد: <?= $safeTitle ?></strong>
  <?php if ($isPdf): ?>
  <button class="btn" type="button" onclick="printContract()">طباعة</button>
  <?php endif; ?>
  <a class="btn" href="<?= htmlspecialchars($downloadUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">تنزيل الملف</a>
  <a class="btn secondary" href="<?= htmlspecialchars($fileUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener">فتح الملف في تبويب جديد</a>
</header>
<main class="preview">
  <?php if ($isPdf): ?>
  <iframe id="contractPreview" name="contractPreview" src="<?= htmlspecialchars($fileUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="معاينة PDF"></iframe>
  <?php else: ?>
  <img src="<?= htmlspecialchars($fileUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="معاينة صورة العقد">
  <?php endif; ?>
</main>
<script>
function printContract() {
    const frame = document.getElementById('contractPreview');
    if (!frame) return;
    try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    } catch (error) {
        window.print();
    }
}
</script>
</body>
</html>
