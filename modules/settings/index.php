<<<<<<< HEAD
<?php
// modules/settings/index.php - Organization settings incl. SMTP (Admin only)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = 'الإعدادات';
$active    = 'settings';

$defs = [
    'بيانات المنظمة' => [
        'org_name_ar' => ['اسم المنظمة (عربي)', 'text'],
        'org_name_en' => ['اسم المنظمة (إنجليزي)', 'text'],
        'org_address' => ['العنوان', 'text'],
        'tax_id' => ['الرقم الضريبي', 'text'],
        'support_phone' => ['هاتف الدعم', 'text'],
        'support_email' => ['بريد الدعم', 'email'],
    ],
    'الإعدادات المالية' => [
        'currency' => ['العملة الافتراضية', 'text'],
        'default_sponsorship_amount' => ['مبلغ الكفالة الافتراضي', 'number'],
        'default_admin_fee_percent' => ['نسبة الرسوم الإدارية %', 'number'],
        'financial_year_start' => ['بداية السنة المالية', 'date'],
    ],
    'البريد الإلكتروني SMTP (يُفعّل عند الاستضافة)' => [
        'smtp_host' => ['خادم SMTP', 'text'],
        'smtp_port' => ['المنفذ', 'number'],
        'smtp_username' => ['اسم المستخدم', 'text'],
        'smtp_password' => ['كلمة المرور (اتركها فارغة لعدم التغيير)', 'password'],
        'smtp_encryption' => ['التشفير', 'select', ['tls', 'ssl', 'none']],
        'mail_from' => ['البريد المرسل منه', 'email'],
    ],
    'النظام' => [
        'supervisor_auto_assign' => ['تعيين المشرف تلقائياً (0/1)', 'number'],
        'system_version' => ['إصدار النظام', 'text'],
    ],
];

$current = [];
foreach (dbFetchAll("SELECT setting_key, setting_value FROM settings") as $r) $current[$r['setting_key']] = $r['setting_value'];

// Handle logo upload
$logo_error = '';
$logo_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة.');
    } else {
        // Handle logo upload
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['site_logo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $logo_error = 'نوع الملف غير مدعوم. يرجى رفع صورة (JPEG, PNG, GIF, WEBP, SVG)';
            } elseif ($file['size'] > $max_size) {
                $logo_error = 'حجم الملف كبير جداً. الحد الأقصى 2MB';
            } else {
                // Create uploads directory if not exists
                $upload_dir = dirname(__DIR__, 2) . '/assets/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'logo.' . $ext;
                $filepath = $upload_dir . $filename;
                
                // Delete old logo files
                foreach (glob($upload_dir . 'logo.*') as $old_file) {
                    if (is_file($old_file) && $old_file !== $filepath) {
                        unlink($old_file);
                    }
                }
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Save logo path in settings
                    $logo_path = 'assets/uploads/' . $filename;
                    dbExecute("INSERT INTO settings (setting_key, setting_value) VALUES ('site_logo', ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$logo_path]);
                    
                    // Update current array
                    $current['site_logo'] = $logo_path;
                    $logo_success = 'تم تحديث شعار المنظمة بنجاح.';
                } else {
                    $logo_error = 'حدث خطأ أثناء رفع الملف.';
                }
            }
        }
        
        // Handle regular settings
        $changes = [];
        foreach ($defs as $group => $fields) {
            foreach ($fields as $key => $d) {
                if (!isset($_POST[$key])) continue;
                $val = trim((string)$_POST[$key]);
                if ($d[1] === 'password' && $val === '') continue; // keep existing
                $old = $current[$key] ?? '';
                if ($val !== $old) {
                    dbExecute("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$key, $val]);
                    $changes[$key] = ['old' => ($d[1] === 'password' ? '***' : $old), 'new' => ($d[1] === 'password' ? '***' : $val)];
                }
            }
        }
        
        if ($changes) {
            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                           VALUES (?, 'UPDATE', 'settings', NULL, ?, ?, ?, ?)",
                    [Session::getUserId(),
                     json_encode(array_map(fn($c) => $c['old'], $changes), JSON_UNESCAPED_UNICODE),
                     json_encode(array_map(fn($c) => $c['new'], $changes), JSON_UNESCAPED_UNICODE),
                     $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            } catch (Throwable $e) {}
            flash('success', 'تم حفظ الإعدادات (' . count($changes) . ' تغيير).');
        } elseif (empty($logo_error) && empty($logo_success)) {
            flash('success', 'لا تغييرات للحفظ.');
        }
        
        // If we have logo messages, don't redirect yet
        if (!empty($logo_error) || !empty($logo_success)) {
            // Refresh current settings
            $current = [];
            foreach (dbFetchAll("SELECT setting_key, setting_value FROM settings") as $r) $current[$r['setting_key']] = $r['setting_value'];
        } else {
            header('Location: ' . APP_URL . 'modules/settings/index.php'); exit();
        }
    }
}

// Get current logo
$current_logo = $current['site_logo'] ?? 'assets/img/logo.png';
$logo_full_path = dirname(__DIR__, 2) . '/' . $current_logo;
$logo_exists = file_exists($logo_full_path);

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.logo-upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    background: #f8f9fa;
}
.logo-upload-area:hover {
    border-color: #1b4d8f;
    background: #f0f4ff;
}
.logo-preview {
    max-width: 200px;
    max-height: 100px;
    margin: 10px auto;
    display: block;
    object-fit: contain;
}
.logo-preview-container {
    min-height: 120px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.logo-upload-area .file-input-wrapper {
    position: relative;
    display: inline-block;
}
.logo-upload-area .file-input-wrapper input[type="file"] {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}
.logo-preview-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 150px;
}
</style>

<div class="welcome-section fade-in">
    <h2>إعدادات المنظمة</h2>
    <p>القيم الأساسية المستخدمة في الكفالات والمحاسبة والتقارير</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<?php if ($logo_error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i> <?php echo e($logo_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($logo_success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i> <?php echo e($logo_success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    
    <!-- Logo Upload Section -->
    <div class="card fade-in mb-4">
        <div class="card-header"><i class="fas fa-image me-2"></i>شعار المنظمة</div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <div class="logo-preview-wrapper">
                        <?php if ($logo_exists): ?>
                            <img src="<?php echo APP_URL . $current_logo; ?>" alt="شعار المنظمة" class="logo-preview" id="logoPreview">
                        <?php else: ?>
                            <img src="<?php echo APP_URL; ?>assets/img/placeholder-logo.png" alt="شعار المنظمة" class="logo-preview" id="logoPreview" style="background: #f0f0f0; padding: 20px;">
                            <p class="text-muted small mt-2">لم يتم رفع شعار</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="logo-upload-area">
                        <div class="d-flex flex-column align-items-center">
                            <div class="file-input-wrapper">
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('site_logo').click();">
                                    <i class="fas fa-upload me-1"></i> اختر صورة الشعار
                                </button>
                                <input type="file" name="site_logo" id="site_logo" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" onchange="previewLogo(this)" style="display: none;">
                            </div>
                            <p class="text-muted small mt-2 mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                الصيغ المدعومة: JPEG, PNG, GIF, WEBP, SVG | الحد الأقصى: 2MB
                            </p>
                            <div id="logoFileName" style="display: none;" class="mt-2">
                                <span class="badge bg-info"><i class="fas fa-file me-1"></i> <span id="fileNameText"></span></span>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="clearLogoSelection()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($defs as $group => $fields): ?>
            <div class="col-lg-6">
                <div class="card fade-in h-100">
                    <div class="card-header"><i class="fas fa-gear me-2"></i><?php echo e($group); ?></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($fields as $key => $d): ?>
                                <div class="col-md-6">
                                    <label class="form-label"><?php echo e($d[0]); ?> <small class="text-muted">(<?php echo e($key); ?>)</small></label>
                                    <?php if ($d[1] === 'select'): ?>
                                        <select name="<?php echo e($key); ?>" class="form-select">
                                            <?php foreach ($d[2] as $opt): ?>
                                                <option value="<?php echo e($opt); ?>" <?php echo ($current[$key] ?? '') === $opt ? 'selected' : ''; ?>><?php echo e($opt); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="<?php echo e($d[1]); ?>" name="<?php echo e($key); ?>" class="form-control"
                                               value="<?php echo e($d[1] === 'password' ? '' : ($current[$key] ?? '')); ?>" step="any">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-4">
        <button class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ الإعدادات</button>
        <button type="reset" class="btn btn-outline-secondary ms-2"><i class="fas fa-undo me-1"></i> إعادة تعيين</button>
    </div>
</form>

<script>
function previewLogo(input) {
    const preview = document.getElementById('logoPreview');
    const fileNameDiv = document.getElementById('logoFileName');
    const fileNameText = document.getElementById('fileNameText');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
        
        // Show filename
        fileNameText.textContent = input.files[0].name;
        fileNameDiv.style.display = 'block';
    } else {
        // Reset to current logo
        const currentLogo = '<?php echo APP_URL . ($current_logo ?? "assets/img/logo.png"); ?>';
        preview.src = currentLogo;
        fileNameDiv.style.display = 'none';
    }
}

function clearLogoSelection() {
    const input = document.getElementById('site_logo');
    input.value = '';
    document.getElementById('logoFileName').style.display = 'none';
    
    // Reset preview to current logo
    const preview = document.getElementById('logoPreview');
    const currentLogo = '<?php echo APP_URL . ($current_logo ?? "assets/img/logo.png"); ?>';
    preview.src = currentLogo;
}

// Check if logo exists and handle SVG properly
document.addEventListener('DOMContentLoaded', function() {
    const preview = document.getElementById('logoPreview');
    if (preview) {
        preview.onerror = function() {
            // If logo fails to load, show placeholder
            this.src = '<?php echo APP_URL; ?>assets/img/placeholder-logo.png';
        };
    }
});
</script>

=======
<?php
// modules/settings/index.php - Organization settings incl. SMTP (Admin only)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = 'الإعدادات';
$active    = 'settings';

$defs = [
    'بيانات المنظمة' => [
        'org_name_ar' => ['اسم المنظمة (عربي)', 'text'],
        'org_name_en' => ['اسم المنظمة (إنجليزي)', 'text'],
        'org_address' => ['العنوان', 'text'],
        'tax_id' => ['الرقم الضريبي', 'text'],
        'support_phone' => ['هاتف الدعم', 'text'],
        'support_email' => ['بريد الدعم', 'email'],
    ],
    'الإعدادات المالية' => [
        'currency' => ['العملة الافتراضية', 'text'],
        'default_sponsorship_amount' => ['مبلغ الكفالة الافتراضي', 'number'],
        'default_admin_fee_percent' => ['نسبة الرسوم الإدارية %', 'number'],
        'financial_year_start' => ['بداية السنة المالية', 'date'],
    ],
    'البريد الإلكتروني SMTP (يُفعّل عند الاستضافة)' => [
        'smtp_host' => ['خادم SMTP', 'text'],
        'smtp_port' => ['المنفذ', 'number'],
        'smtp_username' => ['اسم المستخدم', 'text'],
        'smtp_password' => ['كلمة المرور (اتركها فارغة لعدم التغيير)', 'password'],
        'smtp_encryption' => ['التشفير', 'select', ['tls', 'ssl', 'none']],
        'mail_from' => ['البريد المرسل منه', 'email'],
    ],
    'النظام' => [
        'supervisor_auto_assign' => ['تعيين المشرف تلقائياً (0/1)', 'number'],
        'system_version' => ['إصدار النظام', 'text'],
    ],
];

$current = [];
foreach (dbFetchAll("SELECT setting_key, setting_value FROM settings") as $r) $current[$r['setting_key']] = $r['setting_value'];

// Handle logo upload
$logo_error = '';
$logo_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة.');
    } else {
        // Handle logo upload
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['site_logo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $logo_error = 'نوع الملف غير مدعوم. يرجى رفع صورة (JPEG, PNG, GIF, WEBP, SVG)';
            } elseif ($file['size'] > $max_size) {
                $logo_error = 'حجم الملف كبير جداً. الحد الأقصى 2MB';
            } else {
                // Create uploads directory if not exists
                $upload_dir = dirname(__DIR__, 2) . '/assets/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'logo.' . $ext;
                $filepath = $upload_dir . $filename;
                
                // Delete old logo files
                foreach (glob($upload_dir . 'logo.*') as $old_file) {
                    if (is_file($old_file) && $old_file !== $filepath) {
                        unlink($old_file);
                    }
                }
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Save logo path in settings
                    $logo_path = 'assets/uploads/' . $filename;
                    dbExecute("INSERT INTO settings (setting_key, setting_value) VALUES ('site_logo', ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$logo_path]);
                    
                    // Update current array
                    $current['site_logo'] = $logo_path;
                    $logo_success = 'تم تحديث شعار المنظمة بنجاح.';
                } else {
                    $logo_error = 'حدث خطأ أثناء رفع الملف.';
                }
            }
        }
        
        // Handle regular settings
        $changes = [];
        foreach ($defs as $group => $fields) {
            foreach ($fields as $key => $d) {
                if (!isset($_POST[$key])) continue;
                $val = trim((string)$_POST[$key]);
                if ($d[1] === 'password' && $val === '') continue; // keep existing
                $old = $current[$key] ?? '';
                if ($val !== $old) {
                    dbExecute("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$key, $val]);
                    $changes[$key] = ['old' => ($d[1] === 'password' ? '***' : $old), 'new' => ($d[1] === 'password' ? '***' : $val)];
                }
            }
        }
        
        if ($changes) {
            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                           VALUES (?, 'UPDATE', 'settings', NULL, ?, ?, ?, ?)",
                    [Session::getUserId(),
                     json_encode(array_map(fn($c) => $c['old'], $changes), JSON_UNESCAPED_UNICODE),
                     json_encode(array_map(fn($c) => $c['new'], $changes), JSON_UNESCAPED_UNICODE),
                     $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            } catch (Throwable $e) {}
            flash('success', 'تم حفظ الإعدادات (' . count($changes) . ' تغيير).');
        } elseif (empty($logo_error) && empty($logo_success)) {
            flash('success', 'لا تغييرات للحفظ.');
        }
        
        // If we have logo messages, don't redirect yet
        if (!empty($logo_error) || !empty($logo_success)) {
            // Refresh current settings
            $current = [];
            foreach (dbFetchAll("SELECT setting_key, setting_value FROM settings") as $r) $current[$r['setting_key']] = $r['setting_value'];
        } else {
            header('Location: ' . APP_URL . 'modules/settings/index.php'); exit();
        }
    }
}

// Get current logo
$current_logo = $current['site_logo'] ?? 'assets/img/logo.png';
$logo_full_path = dirname(__DIR__, 2) . '/' . $current_logo;
$logo_exists = file_exists($logo_full_path);

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.logo-upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    background: #f8f9fa;
}
.logo-upload-area:hover {
    border-color: #1b4d8f;
    background: #f0f4ff;
}
.logo-preview {
    max-width: 200px;
    max-height: 100px;
    margin: 10px auto;
    display: block;
    object-fit: contain;
}
.logo-preview-container {
    min-height: 120px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.logo-upload-area .file-input-wrapper {
    position: relative;
    display: inline-block;
}
.logo-upload-area .file-input-wrapper input[type="file"] {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}
.logo-preview-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 150px;
}
</style>

<div class="welcome-section fade-in">
    <h2>إعدادات المنظمة</h2>
    <p>القيم الأساسية المستخدمة في الكفالات والمحاسبة والتقارير</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<?php if ($logo_error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-2"></i> <?php echo e($logo_error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($logo_success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i> <?php echo e($logo_success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    
    <!-- Logo Upload Section -->
    <div class="card fade-in mb-4">
        <div class="card-header"><i class="fas fa-image me-2"></i>شعار المنظمة</div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <div class="logo-preview-wrapper">
                        <?php if ($logo_exists): ?>
                            <img src="<?php echo APP_URL . $current_logo; ?>" alt="شعار المنظمة" class="logo-preview" id="logoPreview">
                        <?php else: ?>
                            <img src="<?php echo APP_URL; ?>assets/img/placeholder-logo.png" alt="شعار المنظمة" class="logo-preview" id="logoPreview" style="background: #f0f0f0; padding: 20px;">
                            <p class="text-muted small mt-2">لم يتم رفع شعار</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="logo-upload-area">
                        <div class="d-flex flex-column align-items-center">
                            <div class="file-input-wrapper">
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('site_logo').click();">
                                    <i class="fas fa-upload me-1"></i> اختر صورة الشعار
                                </button>
                                <input type="file" name="site_logo" id="site_logo" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" onchange="previewLogo(this)" style="display: none;">
                            </div>
                            <p class="text-muted small mt-2 mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                الصيغ المدعومة: JPEG, PNG, GIF, WEBP, SVG | الحد الأقصى: 2MB
                            </p>
                            <div id="logoFileName" style="display: none;" class="mt-2">
                                <span class="badge bg-info"><i class="fas fa-file me-1"></i> <span id="fileNameText"></span></span>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="clearLogoSelection()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($defs as $group => $fields): ?>
            <div class="col-lg-6">
                <div class="card fade-in h-100">
                    <div class="card-header"><i class="fas fa-gear me-2"></i><?php echo e($group); ?></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($fields as $key => $d): ?>
                                <div class="col-md-6">
                                    <label class="form-label"><?php echo e($d[0]); ?> <small class="text-muted">(<?php echo e($key); ?>)</small></label>
                                    <?php if ($d[1] === 'select'): ?>
                                        <select name="<?php echo e($key); ?>" class="form-select">
                                            <?php foreach ($d[2] as $opt): ?>
                                                <option value="<?php echo e($opt); ?>" <?php echo ($current[$key] ?? '') === $opt ? 'selected' : ''; ?>><?php echo e($opt); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="<?php echo e($d[1]); ?>" name="<?php echo e($key); ?>" class="form-control"
                                               value="<?php echo e($d[1] === 'password' ? '' : ($current[$key] ?? '')); ?>" step="any">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-4">
        <button class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ الإعدادات</button>
        <button type="reset" class="btn btn-outline-secondary ms-2"><i class="fas fa-undo me-1"></i> إعادة تعيين</button>
    </div>
</form>

<script>
function previewLogo(input) {
    const preview = document.getElementById('logoPreview');
    const fileNameDiv = document.getElementById('logoFileName');
    const fileNameText = document.getElementById('fileNameText');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
        
        // Show filename
        fileNameText.textContent = input.files[0].name;
        fileNameDiv.style.display = 'block';
    } else {
        // Reset to current logo
        const currentLogo = '<?php echo APP_URL . ($current_logo ?? "assets/img/logo.png"); ?>';
        preview.src = currentLogo;
        fileNameDiv.style.display = 'none';
    }
}

function clearLogoSelection() {
    const input = document.getElementById('site_logo');
    input.value = '';
    document.getElementById('logoFileName').style.display = 'none';
    
    // Reset preview to current logo
    const preview = document.getElementById('logoPreview');
    const currentLogo = '<?php echo APP_URL . ($current_logo ?? "assets/img/logo.png"); ?>';
    preview.src = currentLogo;
}

// Check if logo exists and handle SVG properly
document.addEventListener('DOMContentLoaded', function() {
    const preview = document.getElementById('logoPreview');
    if (preview) {
        preview.onerror = function() {
            // If logo fails to load, show placeholder
            this.src = '<?php echo APP_URL; ?>assets/img/placeholder-logo.png';
        };
    }
});
</script>

>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>