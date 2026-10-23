<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'يجب تسجيل الدخول إلى نظام أهل الخير قبل تنزيل التطبيق.';
    exit;
}

$apkPath = dirname(__DIR__, 2) . '/storage/apps/ahl-el-kheir-sponsor-qr-scanner.apk';
if (!is_file($apkPath) || !is_readable($apkPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ملف تطبيق ماسح QR غير موجود في مخزن النظام. يرجى التواصل مع مسؤول النظام.';
    exit;
}

$size = filesize($apkPath);
if ($size === false || $size <= 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ملف تطبيق ماسح QR غير صالح للتنزيل.';
    exit;
}

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="Ahl-El-Kheir-Sponsor-QR-Scanner.apk"');
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
readfile($apkPath);
exit;
