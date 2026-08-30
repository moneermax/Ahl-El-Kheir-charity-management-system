<?php
// Root: index.php - Login Page
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/session.php';

Session::start();

if (!function_exists('t')) { function t(string $s): string { return $s; } }

$lang = defined('AK_LANG') ? AK_LANG : 'ar';
$dir  = defined('AK_DIR') ? AK_DIR : 'rtl';

// Route legacy logout links (?logout / ?action=logout) to the real logout script
if (isset($_GET['logout']) || (isset($_GET['action']) && $_GET['action'] === 'logout')) {
    header('Location: ' . APP_URL . 'logout.php');
    exit();
}

// If already logged in, redirect to dashboard immediately (loop-safe)
if (Session::isLoggedIn()) {
    $dash = dashboard_for_role(Session::getUserRole());
    if ($dash === 'index.php') {
        session_unset(); session_destroy();
    } else {
        header('Location: ' . APP_URL . $dash);
        exit();
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور.';
    } else {
        $user = dbFetchOne("
            SELECT u.id, u.username, u.full_name, u.password_hash, u.is_active, r.code as role_code
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.username = ?
        ", [$username]);

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                $error = 'حسابك موقوف. يرجى التواصل مع الإدارة.';
            } else {
                $_SESSION['user_id']       = (int)$user['id'];
                $_SESSION['user_name']     = $user['full_name'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_role']     = $user['role_code'];

                dbExecute("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);

                try {
                    dbExecute("
                        INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address, user_agent, created_at)
                        VALUES (?, 'LOGIN', 'users', ?, ?, ?, NOW())
                    ", [
                        $user['id'],
                        $user['id'],
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                } catch (Throwable $e) { /* Fail silently */ }

                header('Location: ' . APP_URL . dashboard_for_role($user['role_code']));
                exit();
            }
        } else {
            $error = 'بيانات الدخول غير صحيحة.';
        }
    }
}

$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
$langSwitchUrl = e($uri . (strpos($uri, '?') !== false ? '&' : '?') . 'lang=' . ($lang === 'ar' ? 'en' : 'ar'));
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo t('تسجيل الدخول'); ?> - <?php echo t('أهل الخير'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?php echo $dir === 'rtl' ? '.rtl' : ''; ?>.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link href="<?php echo APP_URL; ?>assets/css/style.css" rel="stylesheet">
<link rel="manifest" href="<?php echo APP_URL; ?>manifest.json">
<meta name="theme-color" content="#1b4d8f">
<link rel="icon" type="image/png" href="<?php echo APP_URL; ?>assets/img/logo.png">
<link rel="apple-touch-icon" href="<?php echo APP_URL; ?>assets/img/logo.png">
<style>
body {
background: linear-gradient(135deg, #0a1f44 0%, #1b4d8f 100%);
font-family: 'Cairo', sans-serif;
min-height: 100vh;
display: flex;
align-items: center;
justify-content: center;
margin: 0;
}
.login-card {
background: #fff;
border-radius: 15px;
box-shadow: 0 10px 30px rgba(0,0,0,0.2);
overflow: hidden;
max-width: 450px;
width: 100%;
}
.login-header {
background: #1b4d8f;
color: #fff;
padding: 2rem;
text-align: center;
}
.login-logo {
width: 92px; height: 92px; object-fit: cover; border-radius: 50%;
border: 3px solid rgba(255,255,255,.45); background: #fff;
margin: 0 auto 1rem; display: block;
}
.login-body {
padding: 2rem;
}
.form-control {
border-radius: 8px;
padding: 0.75rem 1rem;
border: 1px solid #ced4da;
}
.form-control:focus {
border-color: #1b4d8f;
box-shadow: 0 0 0 0.25rem rgba(27, 77, 143, 0.25);
}
.input-group-text {
background-color: #f8f9fa;
border: 1px solid #ced4da;
color: #1b4d8f;
}
.btn-primary {
background-color: #1b4d8f;
border-color: #1b4d8f;
padding: 0.75rem;
font-weight: 600;
border-radius: 8px;
}
.btn-primary:hover {
background-color: #0a1f44;
border-color: #0a1f44;
}
.btn-eye {
background-color: #f8f9fa;
border: 1px solid #ced4da;
color: #1b4d8f;
padding: 0.75rem 0.9rem;
}
.btn-eye:hover {
background-color: #e9ecef;
color: #0a1f44;
}
</style>
</head>
<body>
<div style="position:fixed;top:12px;inset-inline-end:12px;z-index:10">
<a href="<?php echo $langSwitchUrl; ?>" class="btn btn-sm btn-light"><i class="fas fa-globe me-1"></i><?php echo $lang === 'ar' ? 'EN' : 'عربي'; ?></a>
</div>
<div class="login-card fade-in">
<div class="login-header">
<img src="<?php echo APP_URL; ?>assets/img/logo.png" alt="منظمة أهل الخير" class="login-logo">
<h3><?php echo t('منظمة أهل الخير'); ?></h3>
<p class="mb-0"><?php echo t('نظام إدارة الكفالات والأسر'); ?></p>
</div>
<div class="login-body">
<?php if ($error): ?>
<div class="alert alert-danger py-2 text-center">
<i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>
<form method="POST" action="">
<div class="mb-3">
<label for="username" class="form-label"><?php echo t('اسم المستخدم'); ?></label>
<div class="input-group">
<span class="input-group-text"><i class="fas fa-user"></i></span>
<input type="text" name="username" id="username" class="form-control" required autofocus>
</div>
</div>
<div class="mb-4">
<label for="password" class="form-label"><?php echo t('كلمة المرور'); ?></label>
<div class="input-group">
<span class="input-group-text"><i class="fas fa-lock"></i></span>
<input type="password" name="password" id="password" class="form-control" required>
<button type="button" class="btn btn-eye" id="togglePassword" tabindex="-1"
title="<?php echo $lang === 'ar' ? 'إظهار / إخفاء كلمة المرور' : 'Show / Hide password'; ?>">
<i class="fas fa-eye" id="togglePasswordIcon"></i>
</button>
</div>
</div>
<button type="submit" class="btn btn-primary w-100">
<i class="fas fa-sign-in-alt me-2"></i> <?php echo t('تسجيل الدخول'); ?>
</button>
</form>
<div class="text-center mt-3 text-muted small">
&copy; <?php echo date('Y'); ?> <?php echo t('منظمة أهل الخير'); ?>. <?php echo t('جميع الحقوق محفوظة.'); ?>
</div>
</div>
</div>
<script>
(function(){
var pwd  = document.getElementById('password');
var btn  = document.getElementById('togglePassword');
var icon = document.getElementById('togglePasswordIcon');
if (!btn || !pwd || !icon) return;
btn.addEventListener('click', function(){
if (pwd.type === 'password') {
pwd.type = 'text';
icon.classList.remove('fa-eye');
icon.classList.add('fa-eye-slash');
} else {
pwd.type = 'password';
icon.classList.remove('fa-eye-slash');
icon.classList.add('fa-eye');
}
pwd.focus();
});
})();
</script>
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('<?php echo APP_URL; ?>sw.js').catch(function(){});
}
</script>
</body>
</html>