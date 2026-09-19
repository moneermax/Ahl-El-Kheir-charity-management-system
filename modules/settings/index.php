<?php
// modules/settings/index.php - Organization settings incl. SMTP (Admin only)
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = t('settings.title');
$active = 'settings';

$defs = [
    'settings.organization' => [
        'org_name_ar' => ['settings.org_name_ar', 'text'],
        'org_name_en' => ['settings.org_name_en', 'text'],
        'org_address' => ['settings.address', 'text'],
        'tax_id' => ['settings.tax_id', 'text'],
        'support_phone' => ['settings.support_phone', 'text'],
        'support_email' => ['settings.support_email', 'email'],
    ],
    'settings.financial' => [
        'currency' => ['settings.currency', 'text'],
        'default_sponsorship_amount' => ['settings.default_sponsorship_amount', 'number'],
        'default_admin_fee_percent' => ['settings.admin_fee_percent', 'number'],
        'financial_year_start' => ['settings.financial_year_start', 'date'],
    ],
    'settings.smtp' => [
        'smtp_host' => ['settings.smtp_host', 'text'],
        'smtp_port' => ['settings.smtp_port', 'number'],
        'smtp_username' => ['settings.smtp_username', 'text'],
        'smtp_password' => ['settings.smtp_password', 'password'],
        'smtp_encryption' => ['settings.smtp_encryption', 'select', ['tls', 'ssl', 'none']],
        'mail_from' => ['settings.mail_from', 'email'],
    ],
    'settings.system' => [
        'supervisor_auto_assign' => ['settings.supervisor_auto_assign', 'number'],
        'system_version' => ['settings.system_version', 'text'],
    ],
];

$current = [];
foreach (dbFetchAll('SELECT setting_key, setting_value FROM settings') as $r) $current[$r['setting_key']] = $r['setting_value'];
$logo_error = '';
$logo_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', t('settings.session_expired'));
    } else {
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['site_logo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $max_size = 2 * 1024 * 1024;
            if (!in_array($file['type'], $allowed_types, true)) {
                $logo_error = t('settings.file_type_unsupported');
            } elseif ($file['size'] > $max_size) {
                $logo_error = t('settings.file_too_large');
            } else {
                $upload_dir = dirname(__DIR__, 2) . '/assets/uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'logo.' . $ext;
                $filepath = $upload_dir . $filename;
                foreach (glob($upload_dir . 'logo.*') as $old_file) {
                    if (is_file($old_file) && $old_file !== $filepath) unlink($old_file);
                }
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $logo_path = 'assets/uploads/' . $filename;
                    dbExecute("INSERT INTO settings (setting_key, setting_value) VALUES ('site_logo', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$logo_path]);
                    $current['site_logo'] = $logo_path;
                    $logo_success = t('settings.logo_updated');
                } else {
                    $logo_error = t('settings.upload_error');
                }
            }
        }

        $changes = [];
        foreach ($defs as $fields) {
            foreach ($fields as $key => $d) {
                if (!isset($_POST[$key])) continue;
                $val = trim((string)$_POST[$key]);
                if ($d[1] === 'password' && $val === '') continue;
                $old = $current[$key] ?? '';
                if ($val !== $old) {
                    dbExecute("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$key, $val]);
                    $changes[$key] = ['old' => ($d[1] === 'password' ? '***' : $old), 'new' => ($d[1] === 'password' ? '***' : $val)];
                }
            }
        }

        if ($changes) {
            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, 'UPDATE', 'settings', NULL, ?, ?, ?, ?)", [
                    Session::getUserId(),
                    json_encode(array_map(fn($c) => $c['old'], $changes), JSON_UNESCAPED_UNICODE),
                    json_encode(array_map(fn($c) => $c['new'], $changes), JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Throwable $e) {}
            flash('success', t('settings.saved_changes', ['count' => count($changes)]));
        } elseif ($logo_error === '' && $logo_success === '') {
            flash('success', t('settings.no_changes'));
        }

        if ($logo_error !== '' || $logo_success !== '') {
            $current = [];
            foreach (dbFetchAll('SELECT setting_key, setting_value FROM settings') as $r) $current[$r['setting_key']] = $r['setting_value'];
        } else {
            header('Location: ' . APP_URL . 'modules/settings/index.php'); exit();
        }
    }
}

$current_logo = $current['site_logo'] ?? 'assets/img/logo.png';
$logo_full_path = dirname(__DIR__, 2) . '/' . $current_logo;
$logo_exists = file_exists($logo_full_path);
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.logo-upload-area{border:2px dashed #dee2e6;border-radius:8px;padding:20px;text-align:center;transition:all .3s ease;background:#f8f9fa}.logo-upload-area:hover{border-color:#1b4d8f;background:#f0f4ff}.logo-preview{max-width:200px;max-height:100px;margin:10px auto;display:block;object-fit:contain}.logo-preview-wrapper{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:150px}.logo-upload-area .file-input-wrapper{position:relative;display:inline-block}.logo-upload-area .file-input-wrapper input[type=file]{position:absolute;left:0;top:0;opacity:0;width:100%;height:100%;cursor:pointer}
</style>

<div class="welcome-section fade-in">
    <h2><?php echo e(t('settings.title')); ?></h2>
    <p><?php echo e(t('settings.subtitle')); ?></p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<?php if ($logo_error): ?><div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?php echo e($logo_error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($logo_success): ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?php echo e($logo_success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <div class="card fade-in mb-4">
        <div class="card-header"><i class="fas fa-image me-2"></i><?php echo e(t('settings.logo')); ?></div>
        <div class="card-body"><div class="row align-items-center">
            <div class="col-md-3 text-center"><div class="logo-preview-wrapper">
                <?php if ($logo_exists): ?><img src="<?php echo e(APP_URL . $current_logo); ?>" alt="<?php echo e(t('settings.logo')); ?>" class="logo-preview" id="logoPreview">
                <?php else: ?><img src="<?php echo e(APP_URL); ?>assets/img/placeholder-logo.png" alt="<?php echo e(t('settings.logo')); ?>" class="logo-preview" id="logoPreview" style="background:#f0f0f0;padding:20px"><p class="text-muted small mt-2"><?php echo e(t('settings.no_logo')); ?></p><?php endif; ?>
            </div></div>
            <div class="col-md-9"><div class="logo-upload-area"><div class="d-flex flex-column align-items-center">
                <div class="file-input-wrapper"><button type="button" class="btn btn-primary" onclick="document.getElementById('site_logo').click();"><i class="fas fa-upload me-1"></i><?php echo e(t('settings.choose_logo')); ?></button><input type="file" name="site_logo" id="site_logo" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" onchange="previewLogo(this)" style="display:none"></div>
                <p class="text-muted small mt-2 mb-0"><i class="fas fa-info-circle me-1"></i><?php echo e(t('settings.supported_formats')); ?></p>
                <div id="logoFileName" style="display:none" class="mt-2"><span class="badge bg-info"><i class="fas fa-file me-1"></i><span id="fileNameText"></span></span><button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="clearLogoSelection()"><i class="fas fa-times"></i></button></div>
            </div></div></div>
        </div></div>
    </div>

    <div class="row g-4">
        <?php foreach ($defs as $groupKey => $fields): ?>
        <div class="col-lg-6"><div class="card fade-in h-100"><div class="card-header"><i class="fas fa-gear me-2"></i><?php echo e(t($groupKey)); ?></div><div class="card-body"><div class="row g-3">
            <?php foreach ($fields as $key => $d): ?><div class="col-md-6"><label class="form-label"><?php echo e(t($d[0])); ?> <small class="text-muted">(<?php echo e($key); ?>)</small></label>
                <?php if ($d[1] === 'select'): ?><select name="<?php echo e($key); ?>" class="form-select"><?php foreach ($d[2] as $opt): ?><option value="<?php echo e($opt); ?>" <?php echo ($current[$key] ?? '') === $opt ? 'selected' : ''; ?>><?php echo e($opt); ?></option><?php endforeach; ?></select>
                <?php else: ?><input type="<?php echo e($d[1]); ?>" name="<?php echo e($key); ?>" class="form-control" value="<?php echo e($d[1] === 'password' ? '' : ($current[$key] ?? '')); ?>" step="any"><?php endif; ?>
            </div><?php endforeach; ?>
        </div></div></div></div>
        <?php endforeach; ?>
    </div>
    <div class="mt-4"><button class="btn btn-primary"><i class="fas fa-save me-1"></i><?php echo e(t('settings.save')); ?></button><button type="reset" class="btn btn-outline-secondary ms-2"><i class="fas fa-undo me-1"></i><?php echo e(t('settings.reset')); ?></button></div>
</form>
<script>
function previewLogo(input){const preview=document.getElementById('logoPreview'),fileNameDiv=document.getElementById('logoFileName'),fileNameText=document.getElementById('fileNameText');if(input.files&&input.files[0]){const reader=new FileReader();reader.onload=e=>{preview.src=e.target.result;preview.style.display='block'};reader.readAsDataURL(input.files[0]);fileNameText.textContent=input.files[0].name;fileNameDiv.style.display='block'}else{preview.src=<?php echo json_encode(APP_URL . $current_logo); ?>;fileNameDiv.style.display='none'}}
function clearLogoSelection(){const input=document.getElementById('site_logo');input.value='';document.getElementById('logoFileName').style.display='none';document.getElementById('logoPreview').src=<?php echo json_encode(APP_URL . $current_logo); ?>}
document.addEventListener('DOMContentLoaded',()=>{const preview=document.getElementById('logoPreview');if(preview)preview.onerror=function(){this.src=<?php echo json_encode(APP_URL . 'assets/img/placeholder-logo.png'); ?>}});
</script>

<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="modules/admin_dashboard.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>