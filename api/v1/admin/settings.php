<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

$user = ak_api_authenticated_user();
// The website settings page checks the persisted role exactly. Do not authorize
// sudo/super_admin via their normalized admin routing role.
if ((string)$user['raw_role_code'] !== 'admin') {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'System settings are restricted to the administrator role.',
    ]]);
}

$definitions = [
    'organization' => [
        'org_name_ar' => ['type' => 'text', 'label_ar' => 'اسم المنظمة بالعربية'],
        'org_name_en' => ['type' => 'text', 'label_ar' => 'اسم المنظمة بالإنجليزية'],
        'org_address' => ['type' => 'text', 'label_ar' => 'العنوان'],
        'tax_id' => ['type' => 'text', 'label_ar' => 'الرقم الضريبي'],
        'support_phone' => ['type' => 'text', 'label_ar' => 'هاتف الدعم'],
        'support_email' => ['type' => 'email', 'label_ar' => 'بريد الدعم'],
    ],
    'financial' => [
        'currency' => ['type' => 'text', 'label_ar' => 'العملة'],
        'default_sponsorship_amount' => ['type' => 'number', 'label_ar' => 'قيمة الكفالة الافتراضية'],
        'default_admin_fee_percent' => ['type' => 'number', 'label_ar' => 'نسبة المصروفات الإدارية'],
        'financial_year_start' => ['type' => 'date', 'label_ar' => 'بداية السنة المالية'],
    ],
    'smtp' => [
        'smtp_host' => ['type' => 'text', 'label_ar' => 'خادم SMTP'],
        'smtp_port' => ['type' => 'number', 'label_ar' => 'منفذ SMTP'],
        'smtp_username' => ['type' => 'text', 'label_ar' => 'مستخدم SMTP'],
        'smtp_password' => ['type' => 'password', 'label_ar' => 'كلمة مرور SMTP'],
        'smtp_encryption' => ['type' => 'select', 'label_ar' => 'تشفير SMTP', 'options' => ['tls', 'ssl', 'none']],
        'mail_from' => ['type' => 'email', 'label_ar' => 'بريد الإرسال'],
    ],
    'system' => [
        'supervisor_auto_assign' => ['type' => 'number', 'label_ar' => 'التعيين التلقائي للمشرف'],
        'system_version' => ['type' => 'text', 'label_ar' => 'إصدار النظام'],
    ],
];

$allowed = [];
foreach ($definitions as $fields) {
    foreach ($fields as $key => $definition) {
        $allowed[$key] = $definition;
    }
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
}

if ($method === 'POST' && str_contains($contentType, 'multipart/form-data')) {
    $file = $_FILES['site_logo'] ?? null;
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'logo_upload_required', 'message' => 'Choose a logo image to upload.']]);
    }
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 2 * 1024 * 1024 || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_logo_file', 'message' => 'Logo images must be between 1 byte and 2 MB.']]);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string)$file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime])) {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'unsupported_logo_type', 'message' => 'Use a valid JPG, PNG, GIF, or WebP logo image.']]);
    }
    $root = dirname(__DIR__, 3);
    $uploadDir = $root . '/assets/uploads';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        ak_api_json(500, ['ok' => false, 'error' => ['code' => 'logo_storage_unavailable', 'message' => 'Logo storage is unavailable.']]);
    }
    $filename = 'logo_' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
    $destination = $uploadDir . '/' . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
        ak_api_json(500, ['ok' => false, 'error' => ['code' => 'logo_upload_failed', 'message' => 'The logo image could not be saved.']]);
    }
    $logoPath = 'assets/uploads/' . $filename;
    $previous = dbFetchOne('SELECT setting_value FROM settings WHERE setting_key=?', ['site_logo']);
    try {
        dbExecute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', ['site_logo', $logoPath]);
    } catch (Throwable $e) {
        @unlink($destination);
        error_log('Mobile organization logo setting failed: ' . $e->getMessage());
        ak_api_json(500, ['ok' => false, 'error' => ['code' => 'logo_setting_failed', 'message' => 'The logo setting could not be updated.']]);
    }
    try {
        dbExecute(
            "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'UPDATE', 'settings', NULL, ?, ?, ?, ?)",
            [(int)$user['id'], json_encode(['site_logo' => (string)($previous['setting_value'] ?? '')], JSON_UNESCAPED_UNICODE),
             json_encode(['site_logo' => $logoPath], JSON_UNESCAPED_UNICODE),
             (string)($_SERVER['REMOTE_ADDR'] ?? ''), (string)($_SERVER['HTTP_USER_AGENT'] ?? '')]
        );
    } catch (Throwable $e) {
        error_log('Mobile organization logo audit failed: ' . $e->getMessage());
    }
    foreach (glob($uploadDir . '/logo*') ?: [] as $oldPath) {
        $oldName = basename($oldPath);
        if ($oldName === $filename || !is_file($oldPath)
            || preg_match('/^logo_[a-f0-9]{12}\.(?:jpe?g|png|gif|webp)$/i', $oldName) !== 1) {
            continue;
        }
        @unlink($oldPath);
    }
}

if ($method === 'POST' && !str_contains($contentType, 'multipart/form-data')) {
    $input = ak_api_read_json();
    $values = $input['values'] ?? null;
    if (!is_array($values)) {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_values', 'message' => 'A settings values object is required.']]);
    }

    foreach ($values as $key => $value) {
        if (!is_string($key) || !isset($allowed[$key]) || (!is_string($value) && !is_numeric($value))) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_setting', 'message' => 'One or more settings fields are invalid.']]);
        }
        $value = trim((string)$value);
        $type = $allowed[$key]['type'];
        if (strlen($value) > 255) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'setting_too_long', 'message' => 'A settings value exceeds 255 bytes.']]);
        }
        if ($type === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_email', 'message' => 'Enter a valid email address.']]);
        }
        if ($type === 'number' && $value !== '' && !is_numeric($value)) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_number', 'message' => 'Enter a valid number.']]);
        }
        if ($type === 'select' && !in_array($value, $allowed[$key]['options'] ?? [], true)) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_option', 'message' => 'A selected settings option is invalid.']]);
        }
    }

    $current = [];
    foreach (dbFetchAll('SELECT setting_key, setting_value FROM settings') as $row) {
        $current[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
    }
    $oldAudit = [];
    $newAudit = [];
    foreach ($values as $key => $rawValue) {
        $value = trim((string)$rawValue);
        // As on the web settings form, an empty password means keep the existing secret.
        if ($key === 'smtp_password' && $value === '') {
            continue;
        }
        $oldValue = $current[$key] ?? '';
        if ($value === $oldValue) {
            continue;
        }
        dbExecute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $value]
        );
        $oldAudit[$key] = $key === 'smtp_password' ? '***' : $oldValue;
        $newAudit[$key] = $key === 'smtp_password' ? '***' : $value;
        $current[$key] = $value;
    }

    if ($newAudit !== []) {
        try {
            dbExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, 'UPDATE', 'settings', NULL, ?, ?, ?, ?)",
                [
                    (int)$user['id'],
                    json_encode($oldAudit, JSON_UNESCAPED_UNICODE),
                    json_encode($newAudit, JSON_UNESCAPED_UNICODE),
                    (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                    (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
                ]
            );
        } catch (Throwable $e) {
            error_log('Mobile settings audit insert failed: ' . $e->getMessage());
        }
    }
}

$stored = [];
foreach (dbFetchAll('SELECT setting_key, setting_value FROM settings') as $row) {
    $stored[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
}
$safeValues = [];
foreach ($allowed as $key => $_definition) {
    $safeValues[$key] = $key === 'smtp_password' ? '' : ($stored[$key] ?? '');
}
$safeValues['smtp_password_configured'] = isset($stored['smtp_password']) && $stored['smtp_password'] !== '';
$safeValues['site_logo'] = $stored['site_logo'] ?? '';

ak_api_json(200, ['ok' => true, 'data' => [
    'groups' => $definitions,
    'values' => $safeValues,
    'can_upload_logo' => true,
]]);
