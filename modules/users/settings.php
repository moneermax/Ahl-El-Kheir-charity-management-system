<<<<<<< HEAD
<?php
// modules/users/settings.php - User Preferences & Settings
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$uid = Session::getUserId();
$user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$uid]);

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    
    // 1. Change Password
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (empty($current) || empty($new) || empty($confirm)) {
            $error_message = 'جميع حقول كلمة المرور مطلوبة.';
        } elseif ($new !== $confirm) {
            $error_message = 'كلمة المرور الجديدة وتأكيدها غير متطابقين.';
        } elseif (strlen($new) < 8) {
            $error_message = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.';
        } else {
            if (!password_verify($current, $user['password_hash'])) {
                $error_message = 'كلمة المرور الحالية غير صحيحة.';
            } else {
                try {
                    $new_hash = password_hash($new, PASSWORD_BCRYPT);
                    dbExecute("UPDATE users SET password_hash = ? WHERE id = ?", [$new_hash, $uid]);
                    $success_message = 'تم تغيير كلمة المرور بنجاح.';
                } catch (Throwable $e) {
                    $error_message = 'حدث خطأ: ' . $e->getMessage();
                }
            }
        }
    }
    
    // 2. Update Theme Preference
    if (isset($_POST['update_theme'])) {
        $theme = $_POST['theme'] ?? 'light';
        $valid_themes = ['light', 'dark', 'auto', 'blue', 'green', 'purple'];
        if (in_array($theme, $valid_themes)) {
            try {
                dbExecute("UPDATE users SET theme_preference = ? WHERE id = ?", [$theme, $uid]);
                $success_message = 'تم تحديث المظهر بنجاح.';
                $user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$uid]);
                $_SESSION['theme_preference'] = $theme;
                setcookie('theme_preference', $theme, time() + (86400 * 30), '/');
            } catch (Throwable $e) {
                $error_message = 'حدث خطأ: ' . $e->getMessage();
            }
        }
    }
    
    // 3. Update Notification Preferences
    if (isset($_POST['update_notifications'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        $email_newsletter = isset($_POST['email_newsletter']) ? 1 : 0;
        
        try {
            dbExecute("UPDATE users SET 
                       email_notifications = ?, 
                       push_notifications = ?, 
                       email_newsletter = ? 
                       WHERE id = ?", 
                     [$email_notifications, $push_notifications, $email_newsletter, $uid]);
            $success_message = 'تم تحديث تفضيلات الإشعارات بنجاح.';
            $user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$uid]);
        } catch (Throwable $e) {
            $error_message = 'حدث خطأ: ' . $e->getMessage();
        }
    }
    
    // 4. Logout from all devices (except current)
    if (isset($_POST['logout_all_devices'])) {
        try {
            // Invalidate all sessions except current
            $current_session_id = session_id();
            dbExecute("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?", [$uid, $current_session_id]);
            $success_message = 'تم تسجيل الخروج من جميع الأجهزة الأخرى بنجاح.';
        } catch (Throwable $e) {
            $error_message = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}

$currentTheme = $_SESSION['theme_preference'] ?? $user['theme_preference'] ?? 'light';
$userEmailNotifications = (int)($user['email_notifications'] ?? 1);
$userPushNotifications = (int)($user['push_notifications'] ?? 1);
$userEmailNewsletter = (int)($user['email_newsletter'] ?? 1);

$pageTitle = 'الإعدادات الشخصية';
$active = 'settings';
include dirname(__DIR__, 2) . '/includes/header.php';
?>

<style>
:root {
    --navy: #1b4d8f;
    --navy-dark: #143a6b;
    --bg-body: #f4f7fb;
    --text-color: #212529;
    --card-bg: #ffffff;
    --border-color: #dee2e6;
}

body.theme-dark {
    --navy: #2d3748;
    --navy-dark: #1a202c;
    --bg-body: #1a1a2e;
    --text-color: #e2e8f0;
    --card-bg: #2d3748;
    --border-color: #4a5568;
}
body.theme-dark .settings-section {
    background: var(--card-bg);
    color: var(--text-color);
}
body.theme-dark .settings-section h5 {
    border-bottom-color: var(--border-color);
}
body.theme-dark .form-control {
    background: #1a1a2e;
    color: #e2e8f0;
    border-color: var(--border-color);
}
body.theme-dark .form-control:focus {
    background: #1a1a2e;
    color: #e2e8f0;
}
body.theme-dark .text-muted {
    color: #a0aec0 !important;
}
body.theme-dark .list-group-item {
    background: var(--card-bg);
    color: var(--text-color);
    border-color: var(--border-color);
}

body.theme-blue { --navy: #0d6efd; --navy-dark: #0a58ca; }
body.theme-green { --navy: #198754; --navy-dark: #146c43; }
body.theme-purple { --navy: #6f42c1; --navy-dark: #5a32a3; }

body.theme-dark .sidebar,
body.theme-blue .sidebar,
body.theme-green .sidebar,
body.theme-purple .sidebar { background: var(--navy); }
body.theme-dark .qa-top-bar,
body.theme-blue .qa-top-bar,
body.theme-green .qa-top-bar,
body.theme-purple .qa-top-bar { background: var(--navy); }

.settings-section {
    background: var(--card-bg, #fff);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(10,31,68,.08);
    padding: 24px;
    margin-bottom: 24px;
    transition: all 0.3s ease;
}
.settings-section h5 {
    color: var(--navy, #1b4d8f);
    font-weight: 700;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f0f2f5;
    transition: color 0.3s ease;
}

.theme-option {
    display: inline-block;
    padding: 10px 16px;
    border: 2px solid var(--border-color, #dee2e6);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    margin: 4px;
    min-width: 80px;
    text-align: center;
    background: var(--card-bg, #fff);
}
.theme-option:hover {
    border-color: var(--navy, #1b4d8f);
    transform: scale(1.02);
}
.theme-option.active {
    border-color: var(--navy, #1b4d8f);
    background: rgba(27, 77, 143, 0.1);
    box-shadow: 0 0 0 2px rgba(27, 77, 143, 0.2);
}
.theme-option .preview {
    width: 30px;
    height: 20px;
    border-radius: 4px;
    margin: 0 auto 4px;
    border: 1px solid var(--border-color, #dee2e6);
}
.theme-option .preview-light { background: #ffffff; }
.theme-option .preview-dark { background: #1a1a2e; }
.theme-option .preview-blue { background: #0d6efd; }
.theme-option .preview-green { background: #198754; }
.theme-option .preview-purple { background: #6f42c1; }
.theme-option .preview-auto { background: linear-gradient(135deg, #ffffff 50%, #1a1a2e 50%); }

.theme-preview-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    background: var(--navy, #1b4d8f);
    color: #fff;
}

.password-strength {
    height: 4px;
    border-radius: 2px;
    margin-top: 6px;
    transition: all 0.3s;
}

/* Simple password toggle - using input-group */
.password-toggle-group .btn {
    border-color: #dee2e6;
    background: #fff;
}
.password-toggle-group .btn:hover {
    background: #f8f9fa;
}
body.theme-dark .password-toggle-group .btn {
    background: #1a1a2e;
    border-color: #4a5568;
    color: #e2e8f0;
}
body.theme-dark .password-toggle-group .btn:hover {
    background: #2d3748;
}

.notification-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color, #f0f2f5);
}
.notification-toggle:last-child {
    border-bottom: none;
}
.notification-toggle .form-check-input {
    width: 3rem;
    height: 1.5rem;
    cursor: pointer;
}
.notification-toggle .form-check-input:checked {
    background-color: var(--navy, #1b4d8f);
    border-color: var(--navy, #1b4d8f);
}

@media (max-width: 768px) {
    .theme-option {
        min-width: 60px;
        padding: 6px 10px;
    }
}
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="bi bi-gear me-2"></i>الإعدادات الشخصية</h2>
            <p class="text-muted">إدارة تفضيلاتك وإعدادات حسابك</p>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i> <?php echo e($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i> <?php echo e($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column: Password -->
        <div class="col-lg-6">
            <!-- Change Password Section -->
            <div class="settings-section">
                <h5><i class="bi bi-key me-2"></i>تغيير كلمة المرور</h5>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">كلمة المرور الحالية</label>
                        <div class="input-group password-toggle-group">
                            <input type="password" class="form-control" name="current_password" id="current_password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                <span class="eye-icon">👁️</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">كلمة المرور الجديدة</label>
                        <div class="input-group password-toggle-group">
                            <input type="password" class="form-control" name="new_password" id="new_password" required minlength="8" onkeyup="checkPasswordStrength(this.value)">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                <span class="eye-icon">👁️</span>
                            </button>
                        </div>
                        <div class="password-strength" id="strengthBar"></div>
                        <small class="text-muted">يجب أن تكون 8 أحرف على الأقل</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">تأكيد كلمة المرور الجديدة</label>
                        <div class="input-group password-toggle-group">
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                <span class="eye-icon">👁️</span>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-warning">
                        <i class="bi bi-arrow-repeat me-1"></i> تغيير كلمة المرور
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column: Theme + Notifications + Sessions -->
        <div class="col-lg-6">
            <!-- Theme Selection -->
            <div class="settings-section">
                <h5><i class="bi bi-palette me-2"></i>المظهر والسمات</h5>
                <form method="POST" id="themeForm">
                    <?php echo csrf_field(); ?>
                    <p class="text-muted small">
                        اختر المظهر المفضل لك 
                        <span class="theme-preview-badge" id="themePreviewBadge">
                            <?php 
                            $themeNames = ['light' => 'فاتح', 'dark' => 'داكن', 'auto' => 'تلقائي', 'blue' => 'أزرق', 'green' => 'أخضر', 'purple' => 'بنفسجي'];
                            echo $themeNames[$currentTheme] ?? $currentTheme;
                            ?>
                        </span>
                    </p>
                    
                    <div class="d-flex flex-wrap" id="themeOptions">
                        <?php 
                        $themes = [
                            'light' => ['فاتح', 'light'],
                            'dark' => ['داكن', 'dark'],
                            'auto' => ['تلقائي', 'auto'],
                            'blue' => ['أزرق', 'blue'],
                            'green' => ['أخضر', 'green'],
                            'purple' => ['بنفسجي', 'purple'],
                        ];
                        ?>
                        <?php foreach ($themes as $key => $theme): 
                            $isActive = $currentTheme === $key;
                        ?>
                            <div class="theme-option <?php echo $isActive ? 'active' : ''; ?>" data-theme="<?php echo $key; ?>" onclick="selectTheme('<?php echo $key; ?>')">
                                <div class="preview preview-<?php echo $key; ?>"></div>
                                <small><?php echo $theme[0]; ?></small>
                                <?php if ($isActive): ?>
                                    <i class="bi bi-check-circle-fill text-success" style="font-size: 0.7rem; display: block;"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <input type="hidden" name="theme" id="selectedTheme" value="<?php echo e($currentTheme); ?>">
                    
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" name="update_theme" class="btn btn-primary" id="applyThemeBtn">
                            <i class="bi bi-check me-1"></i> تطبيق المظهر
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetTheme()">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> إعادة ضبط
                        </button>
                    </div>
                </form>
            </div>

            <!-- Notification Preferences -->
            <div class="settings-section">
                <h5><i class="bi bi-bell me-2"></i>تفضيلات الإشعارات</h5>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="notification-toggle">
                        <div>
                            <strong>إشعارات البريد الإلكتروني</strong>
                            <br><small class="text-muted">استلام إشعارات عبر البريد الإلكتروني</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="email_notifications" value="1" <?php echo $userEmailNotifications ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <div class="notification-toggle">
                        <div>
                            <strong>إشعارات المتصفح</strong>
                            <br><small class="text-muted">استلام إشعارات داخل المتصفح</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="push_notifications" value="1" <?php echo $userPushNotifications ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <div class="notification-toggle">
                        <div>
                            <strong>النشرة الإخبارية</strong>
                            <br><small class="text-muted">استلام التحديثات والأخبار عبر البريد</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="email_newsletter" value="1" <?php echo $userEmailNewsletter ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <button type="submit" name="update_notifications" class="btn btn-primary mt-3">
                        <i class="bi bi-save me-1"></i> حفظ التفضيلات
                    </button>
                </form>
            </div>

            <!-- Session Management -->
            <div class="settings-section">
                <h5><i class="bi bi-devices me-2"></i>إدارة الجلسات</h5>
                <p class="text-muted small">إدارة أجهزتك المتصلة بالحساب</p>
                
                <?php
                // Get active sessions (if you have a user_sessions table)
                $sessions = [];
                try {
                    $sessions = dbFetchAll("SELECT session_id, ip_address, user_agent, created_at, last_activity 
                                            FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC", [$uid]);
                } catch (Throwable $e) {
                    // Table might not exist yet
                }
                ?>
                
                <?php if (!empty($sessions)): ?>
                    <div class="list-group mb-3">
                        <?php foreach ($sessions as $session): 
                            $isCurrent = $session['session_id'] === session_id();
                        ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-<?php echo $isCurrent ? 'laptop' : 'device-desktop'; ?> me-2"></i>
                                    <?php echo $isCurrent ? '<span class="badge bg-success me-1">حالي</span>' : ''; ?>
                                    <small class="text-muted">
                                        <?php echo date('Y-m-d H:i', strtotime($session['last_activity'] ?? $session['created_at'] ?? 'now')); ?>
                                    </small>
                                </div>
                                <small class="text-muted"><?php echo e($session['ip_address'] ?? '—'); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small">لا توجد جلسات نشطة أخرى.</p>
                <?php endif; ?>
                
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="logout_all_devices" class="btn btn-outline-danger btn-sm" onclick="return confirm('سيتم تسجيل الخروج من جميع الأجهزة الأخرى. هل أنت متأكد؟')">
                        <i class="bi bi-box-arrow-right me-1"></i> تسجيل الخروج من جميع الأجهزة
                    </button>
                </form>
            </div>

            <!-- Quick Actions -->
            <div class="settings-section">
                <h5><i class="bi bi-activity me-2"></i>إجراءات سريعة</h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo APP_URL; ?>modules/users/profile.php" class="btn btn-outline-primary">
                        <i class="bi bi-person me-1"></i> الملف الشخصي
                    </a>
                    <a href="<?php echo APP_URL; ?>logout.php" class="btn btn-outline-danger">
                        <i class="bi bi-box-arrow-right me-1"></i> تسجيل الخروج
                    </a>
                    <?php if (in_array(current_user_role(), ['admin', 'hr_manager'])): ?>
                        <a href="<?php echo APP_URL; ?>modules/users/recovery.php" class="btn btn-outline-warning">
                            <i class="bi bi-key me-1"></i> طلبات استعادة كلمة المرور
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.parentElement.querySelector('.btn');
    const icon = button.querySelector('.eye-icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.textContent = '🙈';
    } else {
        field.type = 'password';
        icon.textContent = '👁️';
    }
}

// Apply theme immediately on selection
function applyTheme(theme) {
    document.body.classList.remove('theme-light', 'theme-dark', 'theme-blue', 'theme-green', 'theme-purple');
    
    if (theme !== 'auto') {
        document.body.classList.add('theme-' + theme);
    } else {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.body.classList.add('theme-dark');
        } else {
            document.body.classList.add('theme-light');
        }
    }
    
    const badge = document.getElementById('themePreviewBadge');
    const themeNames = {
        'light': 'فاتح',
        'dark': 'داكن',
        'auto': 'تلقائي',
        'blue': 'أزرق',
        'green': 'أخضر',
        'purple': 'بنفسجي'
    };
    badge.textContent = themeNames[theme] || theme;
    
    localStorage.setItem('theme_preference', theme);
}

// Select theme on click with real-time preview
function selectTheme(theme) {
    document.querySelectorAll('.theme-option').forEach(el => {
        el.classList.remove('active');
        const check = el.querySelector('.bi-check-circle-fill');
        if (check) check.remove();
    });
    
    const selected = document.querySelector('.theme-option[data-theme="' + theme + '"]');
    if (selected) {
        selected.classList.add('active');
        const check = document.createElement('i');
        check.className = 'bi bi-check-circle-fill text-success';
        check.style.cssText = 'font-size: 0.7rem; display: block;';
        selected.appendChild(check);
    }
    
    document.getElementById('selectedTheme').value = theme;
    applyTheme(theme);
}

// Reset to light theme
function resetTheme() {
    selectTheme('light');
    document.getElementById('selectedTheme').value = 'light';
    document.getElementById('themeForm').submit();
}

// Check password strength
function checkPasswordStrength(password) {
    const bar = document.getElementById('strengthBar');
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;
    
    const colors = ['#dc3545', '#ffc107', '#ffc107', '#28a745', '#28a745'];
    const labels = ['ضعيفة جداً', 'ضعيفة', 'متوسطة', 'قوية', 'قوية جداً'];
    const width = Math.min(score * 20, 100);
    
    bar.style.width = width + '%';
    bar.style.background = colors[score - 1] || '#dee2e6';
    
    let label = document.getElementById('strengthLabel');
    if (!label) {
        label = document.createElement('small');
        label.id = 'strengthLabel';
        label.className = 'd-block mt-1';
        bar.parentNode.appendChild(label);
    }
    label.textContent = password.length > 0 ? 'قوة كلمة المرور: ' + (labels[score - 1] || 'ضعيفة جداً') : '';
    label.style.color = colors[score - 1] || '#dee2e6';
}

// Apply saved theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme_preference') || '<?php echo e($currentTheme); ?>';
    if (savedTheme) {
        applyTheme(savedTheme);
        document.querySelectorAll('.theme-option').forEach(el => {
            el.classList.remove('active');
            const check = el.querySelector('.bi-check-circle-fill');
            if (check) check.remove();
        });
        const selected = document.querySelector('.theme-option[data-theme="' + savedTheme + '"]');
        if (selected) {
            selected.classList.add('active');
            const check = document.createElement('i');
            check.className = 'bi bi-check-circle-fill text-success';
            check.style.cssText = 'font-size: 0.7rem; display: block;';
            selected.appendChild(check);
        }
        document.getElementById('selectedTheme').value = savedTheme;
    }
});

// Auto theme listener
if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        const currentTheme = document.getElementById('selectedTheme').value;
        if (currentTheme === 'auto') {
            applyTheme('auto');
        }
    });
}
</script>

=======
<?php
// modules/users/settings.php - User Preferences & Settings
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$uid = Session::getUserId();
$user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$uid]);

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    
    // 1. Change Password
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (empty($current) || empty($new) || empty($confirm)) {
            $error_message = 'جميع حقول كلمة المرور مطلوبة.';
        } elseif ($new !== $confirm) {
            $error_message = 'كلمة المرور الجديدة وتأكيدها غير متطابقين.';
        } elseif (strlen($new) < 8) {
            $error_message = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.';
        } else {
            if (!password_verify($current, $user['password_hash'])) {
                $error_message = 'كلمة المرور الحالية غير صحيحة.';
            } else {
                try {
                    $new_hash = password_hash($new, PASSWORD_BCRYPT);
                    dbExecute("UPDATE users SET password_hash = ? WHERE id = ?", [$new_hash, $uid]);
                    $success_message = 'تم تغيير كلمة المرور بنجاح.';
                } catch (Throwable $e) {
                    $error_message = 'حدث خطأ: ' . $e->getMessage();
                }
            }
        }
    }
    
    // 2. Update Theme Preference
    if (isset($_POST['update_theme'])) {
        $theme = $_POST['theme'] ?? 'light';
        $valid_themes = ['light', 'dark', 'auto', 'blue', 'green', 'purple'];
        if (in_array($theme, $valid_themes)) {
            try {
                dbExecute("UPDATE users SET theme_preference = ? WHERE id = ?", [$theme, $uid]);
                $success_message = 'تم تحديث المظهر بنجاح.';
                $user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$uid]);
                $_SESSION['theme_preference'] = $theme;
                setcookie('theme_preference', $theme, time() + (86400 * 30), '/');
            } catch (Throwable $e) {
                $error_message = 'حدث خطأ: ' . $e->getMessage();
            }
        }
    }
    
    // 3. Update Notification Preferences
    if (isset($_POST['update_notifications'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        $email_newsletter = isset($_POST['email_newsletter']) ? 1 : 0;
        
        try {
            dbExecute("UPDATE users SET 
                       email_notifications = ?, 
                       push_notifications = ?, 
                       email_newsletter = ? 
                       WHERE id = ?", 
                     [$email_notifications, $push_notifications, $email_newsletter, $uid]);
            $success_message = 'تم تحديث تفضيلات الإشعارات بنجاح.';
            $user = dbFetchOne("SELECT * FROM users WHERE id = ?", [$uid]);
        } catch (Throwable $e) {
            $error_message = 'حدث خطأ: ' . $e->getMessage();
        }
    }
    
    // 4. Logout from all devices (except current)
    if (isset($_POST['logout_all_devices'])) {
        try {
            // Invalidate all sessions except current
            $current_session_id = session_id();
            dbExecute("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?", [$uid, $current_session_id]);
            $success_message = 'تم تسجيل الخروج من جميع الأجهزة الأخرى بنجاح.';
        } catch (Throwable $e) {
            $error_message = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}

$currentTheme = $_SESSION['theme_preference'] ?? $user['theme_preference'] ?? 'light';
$userEmailNotifications = (int)($user['email_notifications'] ?? 1);
$userPushNotifications = (int)($user['push_notifications'] ?? 1);
$userEmailNewsletter = (int)($user['email_newsletter'] ?? 1);

$pageTitle = 'الإعدادات الشخصية';
$active = 'settings';
include dirname(__DIR__, 2) . '/includes/header.php';
?>

<style>
:root {
    --navy: #1b4d8f;
    --navy-dark: #143a6b;
    --bg-body: #f4f7fb;
    --text-color: #212529;
    --card-bg: #ffffff;
    --border-color: #dee2e6;
}

body.theme-dark {
    --navy: #2d3748;
    --navy-dark: #1a202c;
    --bg-body: #1a1a2e;
    --text-color: #e2e8f0;
    --card-bg: #2d3748;
    --border-color: #4a5568;
}
body.theme-dark .settings-section {
    background: var(--card-bg);
    color: var(--text-color);
}
body.theme-dark .settings-section h5 {
    border-bottom-color: var(--border-color);
}
body.theme-dark .form-control {
    background: #1a1a2e;
    color: #e2e8f0;
    border-color: var(--border-color);
}
body.theme-dark .form-control:focus {
    background: #1a1a2e;
    color: #e2e8f0;
}
body.theme-dark .text-muted {
    color: #a0aec0 !important;
}
body.theme-dark .list-group-item {
    background: var(--card-bg);
    color: var(--text-color);
    border-color: var(--border-color);
}

body.theme-blue { --navy: #0d6efd; --navy-dark: #0a58ca; }
body.theme-green { --navy: #198754; --navy-dark: #146c43; }
body.theme-purple { --navy: #6f42c1; --navy-dark: #5a32a3; }

body.theme-dark .sidebar,
body.theme-blue .sidebar,
body.theme-green .sidebar,
body.theme-purple .sidebar { background: var(--navy); }
body.theme-dark .qa-top-bar,
body.theme-blue .qa-top-bar,
body.theme-green .qa-top-bar,
body.theme-purple .qa-top-bar { background: var(--navy); }

.settings-section {
    background: var(--card-bg, #fff);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(10,31,68,.08);
    padding: 24px;
    margin-bottom: 24px;
    transition: all 0.3s ease;
}
.settings-section h5 {
    color: var(--navy, #1b4d8f);
    font-weight: 700;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f0f2f5;
    transition: color 0.3s ease;
}

.theme-option {
    display: inline-block;
    padding: 10px 16px;
    border: 2px solid var(--border-color, #dee2e6);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    margin: 4px;
    min-width: 80px;
    text-align: center;
    background: var(--card-bg, #fff);
}
.theme-option:hover {
    border-color: var(--navy, #1b4d8f);
    transform: scale(1.02);
}
.theme-option.active {
    border-color: var(--navy, #1b4d8f);
    background: rgba(27, 77, 143, 0.1);
    box-shadow: 0 0 0 2px rgba(27, 77, 143, 0.2);
}
.theme-option .preview {
    width: 30px;
    height: 20px;
    border-radius: 4px;
    margin: 0 auto 4px;
    border: 1px solid var(--border-color, #dee2e6);
}
.theme-option .preview-light { background: #ffffff; }
.theme-option .preview-dark { background: #1a1a2e; }
.theme-option .preview-blue { background: #0d6efd; }
.theme-option .preview-green { background: #198754; }
.theme-option .preview-purple { background: #6f42c1; }
.theme-option .preview-auto { background: linear-gradient(135deg, #ffffff 50%, #1a1a2e 50%); }

.theme-preview-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    background: var(--navy, #1b4d8f);
    color: #fff;
}

.password-strength {
    height: 4px;
    border-radius: 2px;
    margin-top: 6px;
    transition: all 0.3s;
}

/* Simple password toggle - using input-group */
.password-toggle-group .btn {
    border-color: #dee2e6;
    background: #fff;
}
.password-toggle-group .btn:hover {
    background: #f8f9fa;
}
body.theme-dark .password-toggle-group .btn {
    background: #1a1a2e;
    border-color: #4a5568;
    color: #e2e8f0;
}
body.theme-dark .password-toggle-group .btn:hover {
    background: #2d3748;
}

.notification-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color, #f0f2f5);
}
.notification-toggle:last-child {
    border-bottom: none;
}
.notification-toggle .form-check-input {
    width: 3rem;
    height: 1.5rem;
    cursor: pointer;
}
.notification-toggle .form-check-input:checked {
    background-color: var(--navy, #1b4d8f);
    border-color: var(--navy, #1b4d8f);
}

@media (max-width: 768px) {
    .theme-option {
        min-width: 60px;
        padding: 6px 10px;
    }
}
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="bi bi-gear me-2"></i>الإعدادات الشخصية</h2>
            <p class="text-muted">إدارة تفضيلاتك وإعدادات حسابك</p>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i> <?php echo e($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i> <?php echo e($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column: Password -->
        <div class="col-lg-6">
            <!-- Change Password Section -->
            <div class="settings-section">
                <h5><i class="bi bi-key me-2"></i>تغيير كلمة المرور</h5>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">كلمة المرور الحالية</label>
                        <div class="input-group password-toggle-group">
                            <input type="password" class="form-control" name="current_password" id="current_password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                <span class="eye-icon">👁️</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">كلمة المرور الجديدة</label>
                        <div class="input-group password-toggle-group">
                            <input type="password" class="form-control" name="new_password" id="new_password" required minlength="8" onkeyup="checkPasswordStrength(this.value)">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                <span class="eye-icon">👁️</span>
                            </button>
                        </div>
                        <div class="password-strength" id="strengthBar"></div>
                        <small class="text-muted">يجب أن تكون 8 أحرف على الأقل</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">تأكيد كلمة المرور الجديدة</label>
                        <div class="input-group password-toggle-group">
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                <span class="eye-icon">👁️</span>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-warning">
                        <i class="bi bi-arrow-repeat me-1"></i> تغيير كلمة المرور
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column: Theme + Notifications + Sessions -->
        <div class="col-lg-6">
            <!-- Theme Selection -->
            <div class="settings-section">
                <h5><i class="bi bi-palette me-2"></i>المظهر والسمات</h5>
                <form method="POST" id="themeForm">
                    <?php echo csrf_field(); ?>
                    <p class="text-muted small">
                        اختر المظهر المفضل لك 
                        <span class="theme-preview-badge" id="themePreviewBadge">
                            <?php 
                            $themeNames = ['light' => 'فاتح', 'dark' => 'داكن', 'auto' => 'تلقائي', 'blue' => 'أزرق', 'green' => 'أخضر', 'purple' => 'بنفسجي'];
                            echo $themeNames[$currentTheme] ?? $currentTheme;
                            ?>
                        </span>
                    </p>
                    
                    <div class="d-flex flex-wrap" id="themeOptions">
                        <?php 
                        $themes = [
                            'light' => ['فاتح', 'light'],
                            'dark' => ['داكن', 'dark'],
                            'auto' => ['تلقائي', 'auto'],
                            'blue' => ['أزرق', 'blue'],
                            'green' => ['أخضر', 'green'],
                            'purple' => ['بنفسجي', 'purple'],
                        ];
                        ?>
                        <?php foreach ($themes as $key => $theme): 
                            $isActive = $currentTheme === $key;
                        ?>
                            <div class="theme-option <?php echo $isActive ? 'active' : ''; ?>" data-theme="<?php echo $key; ?>" onclick="selectTheme('<?php echo $key; ?>')">
                                <div class="preview preview-<?php echo $key; ?>"></div>
                                <small><?php echo $theme[0]; ?></small>
                                <?php if ($isActive): ?>
                                    <i class="bi bi-check-circle-fill text-success" style="font-size: 0.7rem; display: block;"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <input type="hidden" name="theme" id="selectedTheme" value="<?php echo e($currentTheme); ?>">
                    
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" name="update_theme" class="btn btn-primary" id="applyThemeBtn">
                            <i class="bi bi-check me-1"></i> تطبيق المظهر
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetTheme()">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> إعادة ضبط
                        </button>
                    </div>
                </form>
            </div>

            <!-- Notification Preferences -->
            <div class="settings-section">
                <h5><i class="bi bi-bell me-2"></i>تفضيلات الإشعارات</h5>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="notification-toggle">
                        <div>
                            <strong>إشعارات البريد الإلكتروني</strong>
                            <br><small class="text-muted">استلام إشعارات عبر البريد الإلكتروني</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="email_notifications" value="1" <?php echo $userEmailNotifications ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <div class="notification-toggle">
                        <div>
                            <strong>إشعارات المتصفح</strong>
                            <br><small class="text-muted">استلام إشعارات داخل المتصفح</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="push_notifications" value="1" <?php echo $userPushNotifications ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <div class="notification-toggle">
                        <div>
                            <strong>النشرة الإخبارية</strong>
                            <br><small class="text-muted">استلام التحديثات والأخبار عبر البريد</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="email_newsletter" value="1" <?php echo $userEmailNewsletter ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <button type="submit" name="update_notifications" class="btn btn-primary mt-3">
                        <i class="bi bi-save me-1"></i> حفظ التفضيلات
                    </button>
                </form>
            </div>

            <!-- Session Management -->
            <div class="settings-section">
                <h5><i class="bi bi-devices me-2"></i>إدارة الجلسات</h5>
                <p class="text-muted small">إدارة أجهزتك المتصلة بالحساب</p>
                
                <?php
                // Get active sessions (if you have a user_sessions table)
                $sessions = [];
                try {
                    $sessions = dbFetchAll("SELECT session_id, ip_address, user_agent, created_at, last_activity 
                                            FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC", [$uid]);
                } catch (Throwable $e) {
                    // Table might not exist yet
                }
                ?>
                
                <?php if (!empty($sessions)): ?>
                    <div class="list-group mb-3">
                        <?php foreach ($sessions as $session): 
                            $isCurrent = $session['session_id'] === session_id();
                        ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-<?php echo $isCurrent ? 'laptop' : 'device-desktop'; ?> me-2"></i>
                                    <?php echo $isCurrent ? '<span class="badge bg-success me-1">حالي</span>' : ''; ?>
                                    <small class="text-muted">
                                        <?php echo date('Y-m-d H:i', strtotime($session['last_activity'] ?? $session['created_at'] ?? 'now')); ?>
                                    </small>
                                </div>
                                <small class="text-muted"><?php echo e($session['ip_address'] ?? '—'); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small">لا توجد جلسات نشطة أخرى.</p>
                <?php endif; ?>
                
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="logout_all_devices" class="btn btn-outline-danger btn-sm" onclick="return confirm('سيتم تسجيل الخروج من جميع الأجهزة الأخرى. هل أنت متأكد؟')">
                        <i class="bi bi-box-arrow-right me-1"></i> تسجيل الخروج من جميع الأجهزة
                    </button>
                </form>
            </div>

            <!-- Quick Actions -->
            <div class="settings-section">
                <h5><i class="bi bi-activity me-2"></i>إجراءات سريعة</h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo APP_URL; ?>modules/users/profile.php" class="btn btn-outline-primary">
                        <i class="bi bi-person me-1"></i> الملف الشخصي
                    </a>
                    <a href="<?php echo APP_URL; ?>logout.php" class="btn btn-outline-danger">
                        <i class="bi bi-box-arrow-right me-1"></i> تسجيل الخروج
                    </a>
                    <?php if (in_array(current_user_role(), ['admin', 'hr_manager'])): ?>
                        <a href="<?php echo APP_URL; ?>modules/users/recovery.php" class="btn btn-outline-warning">
                            <i class="bi bi-key me-1"></i> طلبات استعادة كلمة المرور
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.parentElement.querySelector('.btn');
    const icon = button.querySelector('.eye-icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.textContent = '🙈';
    } else {
        field.type = 'password';
        icon.textContent = '👁️';
    }
}

// Apply theme immediately on selection
function applyTheme(theme) {
    document.body.classList.remove('theme-light', 'theme-dark', 'theme-blue', 'theme-green', 'theme-purple');
    
    if (theme !== 'auto') {
        document.body.classList.add('theme-' + theme);
    } else {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.body.classList.add('theme-dark');
        } else {
            document.body.classList.add('theme-light');
        }
    }
    
    const badge = document.getElementById('themePreviewBadge');
    const themeNames = {
        'light': 'فاتح',
        'dark': 'داكن',
        'auto': 'تلقائي',
        'blue': 'أزرق',
        'green': 'أخضر',
        'purple': 'بنفسجي'
    };
    badge.textContent = themeNames[theme] || theme;
    
    localStorage.setItem('theme_preference', theme);
}

// Select theme on click with real-time preview
function selectTheme(theme) {
    document.querySelectorAll('.theme-option').forEach(el => {
        el.classList.remove('active');
        const check = el.querySelector('.bi-check-circle-fill');
        if (check) check.remove();
    });
    
    const selected = document.querySelector('.theme-option[data-theme="' + theme + '"]');
    if (selected) {
        selected.classList.add('active');
        const check = document.createElement('i');
        check.className = 'bi bi-check-circle-fill text-success';
        check.style.cssText = 'font-size: 0.7rem; display: block;';
        selected.appendChild(check);
    }
    
    document.getElementById('selectedTheme').value = theme;
    applyTheme(theme);
}

// Reset to light theme
function resetTheme() {
    selectTheme('light');
    document.getElementById('selectedTheme').value = 'light';
    document.getElementById('themeForm').submit();
}

// Check password strength
function checkPasswordStrength(password) {
    const bar = document.getElementById('strengthBar');
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;
    
    const colors = ['#dc3545', '#ffc107', '#ffc107', '#28a745', '#28a745'];
    const labels = ['ضعيفة جداً', 'ضعيفة', 'متوسطة', 'قوية', 'قوية جداً'];
    const width = Math.min(score * 20, 100);
    
    bar.style.width = width + '%';
    bar.style.background = colors[score - 1] || '#dee2e6';
    
    let label = document.getElementById('strengthLabel');
    if (!label) {
        label = document.createElement('small');
        label.id = 'strengthLabel';
        label.className = 'd-block mt-1';
        bar.parentNode.appendChild(label);
    }
    label.textContent = password.length > 0 ? 'قوة كلمة المرور: ' + (labels[score - 1] || 'ضعيفة جداً') : '';
    label.style.color = colors[score - 1] || '#dee2e6';
}

// Apply saved theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme_preference') || '<?php echo e($currentTheme); ?>';
    if (savedTheme) {
        applyTheme(savedTheme);
        document.querySelectorAll('.theme-option').forEach(el => {
            el.classList.remove('active');
            const check = el.querySelector('.bi-check-circle-fill');
            if (check) check.remove();
        });
        const selected = document.querySelector('.theme-option[data-theme="' + savedTheme + '"]');
        if (selected) {
            selected.classList.add('active');
            const check = document.createElement('i');
            check.className = 'bi bi-check-circle-fill text-success';
            check.style.cssText = 'font-size: 0.7rem; display: block;';
            selected.appendChild(check);
        }
        document.getElementById('selectedTheme').value = savedTheme;
    }
});

// Auto theme listener
if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        const currentTheme = document.getElementById('selectedTheme').value;
        if (currentTheme === 'auto') {
            applyTheme('auto');
        }
    });
}
</script>

>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>