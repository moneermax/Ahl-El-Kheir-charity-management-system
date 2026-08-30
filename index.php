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

if (isset($_GET['logout']) || (isset($_GET['action']) && $_GET['action'] === 'logout')) {
    header('Location: ' . APP_URL . 'logout.php');
    exit();
}

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
        $user = dbFetchOne("SELECT u.id, u.username, u.full_name, u.password_hash, u.is_active, r.code as role_code FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ?", [$username]);

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
                    dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES (?, 'LOGIN', 'users', ?, ?, ?, NOW())", [$user['id'], $user['id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
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

$orgTitle = $lang === 'ar' ? 'منظمة أهل الخير' : 'Ahl El Kheir Organization';
$orgSubtitle = $lang === 'ar' ? 'نحو أثرٍ مستدام وخدمةٍ إنسانية منظمة' : 'Toward sustainable impact and organized humanitarian service';
$orgText = $lang === 'ar'
    ? 'نعمل على تنظيم وإدارة برامج الكفالة والدعم للأسر والأيتام بكفاءة وشفافية، مع توثيق البيانات ومتابعة الحالات لضمان وصول الدعم إلى مستحقيه وتحقيق أثر إنساني مستدام.'
    : 'We organize and manage sponsorship and support programs for families and orphans with efficiency and transparency, maintaining accurate records and following up on cases to ensure support reaches those who need it and creates lasting humanitarian impact.';
$goals = $lang === 'ar'
    ? ['تحسين إدارة ومتابعة حالات الأسر والأيتام', 'تعزيز الشفافية ودقة السجلات والتقارير', 'تنظيم عمليات الكفالة والتبرعات والدعم', 'رفع كفاءة العمل وتحسين جودة الخدمة الإنسانية']
    : ['Improve family and orphan case management', 'Strengthen transparency and record accuracy', 'Organize sponsorship, donation and support operations', 'Increase efficiency and improve humanitarian service quality'];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo t('تسجيل الدخول'); ?> - <?php echo t('أهل الخير'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?php echo $dir === 'rtl' ? '.rtl' : ''; ?>.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?php echo APP_URL; ?>assets/css/style.css" rel="stylesheet">
<link rel="manifest" href="<?php echo APP_URL; ?>manifest.json">
<meta name="theme-color" content="#1b4d8f">
<link rel="icon" type="image/png" href="<?php echo APP_URL; ?>assets/img/logo.png">
<link rel="apple-touch-icon" href="<?php echo APP_URL; ?>assets/img/logo.png">
<style>
:root { --ak-blue:#1b4d8f; --ak-dark:#0a1f44; }
* { box-sizing:border-box; }
body {
    background: radial-gradient(circle at 15% 20%, rgba(255,255,255,.08), transparent 30%), linear-gradient(135deg, #0a1f44 0%, #1b4d8f 100%);
    font-family:'Cairo',sans-serif; min-height:100vh; margin:0; color:#243447;
    overflow-x:hidden;
}
.login-page { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:58px 24px 28px; position:relative; }
.login-shell { width:min(1060px,100%); display:grid; grid-template-columns:minmax(0,1.05fr) minmax(330px,.75fr); direction:ltr; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.22); border-radius:24px; padding:14px; box-shadow:0 24px 65px rgba(0,0,0,.28); backdrop-filter:blur(8px); }
.login-card,.info-panel { direction:rtl; }
html[dir="ltr"] .login-card, html[dir="ltr"] .info-panel { direction:ltr; }
.login-card { background:#fff; border-radius:18px; overflow:hidden; box-shadow:0 12px 35px rgba(0,0,0,.16); max-width:430px; width:100%; justify-self:center; }
.login-header { background:linear-gradient(145deg,var(--ak-blue),var(--ak-dark)); color:#fff; padding:1.45rem 1.5rem 1.25rem; text-align:center; }
.login-logo { width:74px; height:74px; object-fit:cover; border-radius:50%; border:3px solid rgba(255,255,255,.5); background:#fff; margin:0 auto .65rem; display:block; }
.login-header h3 { font-size:1.25rem; margin-bottom:.2rem; font-weight:700; }
.login-header p { font-size:.82rem; opacity:.9; }
.login-body { padding:1.35rem 1.55rem 1.2rem; }
.form-label { font-size:.88rem; font-weight:600; margin-bottom:.35rem; }
.form-control { border-radius:8px; padding:.62rem .8rem; font-size:.9rem; }
.form-control:focus { border-color:var(--ak-blue); box-shadow:0 0 0 .2rem rgba(27,77,143,.16); }
.input-group-text { background:#f8f9fa; border:1px solid #ced4da; color:var(--ak-blue); }
.btn-primary { background:var(--ak-blue); border-color:var(--ak-blue); padding:.62rem; font-weight:600; border-radius:8px; }
.btn-primary:hover { background:var(--ak-dark); border-color:var(--ak-dark); }
.btn-eye { background:#f8f9fa; border:1px solid #ced4da; color:var(--ak-blue); padding:.62rem .75rem; }
.forgot-link { font-size:.84rem; }
.info-panel { color:#fff; padding:2.3rem 2.4rem; display:flex; flex-direction:column; justify-content:center; }
.info-panel .eyebrow { font-size:.8rem; font-weight:600; opacity:.75; letter-spacing:.3px; margin-bottom:.55rem; }
.info-panel h1 { font-size:clamp(1.55rem,3vw,2.15rem); font-weight:700; line-height:1.35; margin-bottom:.55rem; }
.info-panel .subtitle { font-size:.98rem; opacity:.9; margin-bottom:1rem; }
.info-panel .description { font-size:.86rem; line-height:1.9; opacity:.84; max-width:560px; margin-bottom:1.15rem; }
.goals { display:grid; gap:.55rem; margin:0; padding:0; list-style:none; }
.goals li { display:flex; align-items:flex-start; gap:.6rem; font-size:.82rem; line-height:1.55; opacity:.9; }
.goals i { margin-top:.28rem; font-size:.65rem; }
.language-switch { position:fixed; top:14px; inset-inline-end:16px; z-index:10; }
.language-switch .btn { border-radius:8px; font-weight:600; box-shadow:0 3px 12px rgba(0,0,0,.12); }
@media (max-width:850px) {
    .login-shell { grid-template-columns:1fr; max-width:500px; }
    .info-panel { order:2; padding:1.4rem 1.6rem 1.55rem; }
    .login-card { order:1; max-width:none; }
    .info-panel h1 { font-size:1.45rem; }
}
@media (max-height:700px) and (min-width:851px) {
    .login-page { padding-top:48px; padding-bottom:16px; }
    .login-header { padding:1rem 1.2rem; }
    .login-logo { width:58px; height:58px; margin-bottom:.4rem; }
    .login-body { padding:1rem 1.35rem .85rem; }
    .mb-3 { margin-bottom:.65rem !important; }
    .mb-4 { margin-bottom:.8rem !important; }
    .info-panel { padding:1.5rem 2rem; }
}
</style>
</head>
<body>
<div class="language-switch">
<a href="<?php echo $langSwitchUrl; ?>" class="btn btn-sm btn-light"><i class="fas fa-globe me-1"></i><?php echo $lang === 'ar' ? 'EN' : 'عربي'; ?></a>
</div>

<main class="login-page">
<div class="login-shell fade-in">
    <section class="login-card">
        <div class="login-header">
            <img src="<?php echo APP_URL; ?>assets/img/logo.png" alt="<?php echo htmlspecialchars($orgTitle); ?>" class="login-logo">
            <h3><?php echo htmlspecialchars($orgTitle); ?></h3>
            <p class="mb-0"><?php echo t('نظام إدارة الكفالات والأسر'); ?></p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 text-center small"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label"><?php echo t('اسم المستخدم'); ?></label>
                    <div class="input-group"><span class="input-group-text"><i class="fas fa-user"></i></span><input type="text" name="username" id="username" class="form-control" required autofocus></div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label"><?php echo t('كلمة المرور'); ?></label>
                    <div class="input-group"><span class="input-group-text"><i class="fas fa-lock"></i></span><input type="password" name="password" id="password" class="form-control" required><button type="button" class="btn btn-eye" id="togglePassword" tabindex="-1" title="<?php echo $lang === 'ar' ? 'إظهار / إخفاء كلمة المرور' : 'Show / Hide password'; ?>"><i class="fas fa-eye" id="togglePasswordIcon"></i></button></div>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt me-2"></i><?php echo t('تسجيل الدخول'); ?></button>
            </form>
            <div class="text-center mt-3">
                <a href="<?php echo APP_URL; ?>modules/users/password_recovery_request.php" class="text-primary text-decoration-none forgot-link"><i class="fas fa-key me-1"></i><?php echo $lang === 'ar' ? 'نسيت كلمة المرور؟' : 'Forgot your password?'; ?></a>
            </div>
            <div class="text-center mt-2 text-muted small">&copy; <?php echo date('Y'); ?> <?php echo t('منظمة أهل الخير'); ?>. <?php echo t('جميع الحقوق محفوظة.'); ?></div>
        </div>
    </section>

    <section class="info-panel">
        <div class="eyebrow"><?php echo $lang === 'ar' ? 'رسالتنا' : 'OUR MISSION'; ?></div>
        <h1><?php echo htmlspecialchars($orgTitle); ?></h1>
        <div class="subtitle"><?php echo htmlspecialchars($orgSubtitle); ?></div>
        <p class="description"><?php echo htmlspecialchars($orgText); ?></p>
        <ul class="goals">
            <?php foreach ($goals as $goal): ?>
            <li><i class="fas fa-circle-check"></i><span><?php echo htmlspecialchars($goal); ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
</main>

<script>
(function(){var pwd=document.getElementById('password'),btn=document.getElementById('togglePassword'),icon=document.getElementById('togglePasswordIcon');if(!btn||!pwd||!icon)return;btn.addEventListener('click',function(){if(pwd.type==='password'){pwd.type='text';icon.classList.remove('fa-eye');icon.classList.add('fa-eye-slash');}else{pwd.type='password';icon.classList.remove('fa-eye-slash');icon.classList.add('fa-eye');}pwd.focus();});})();
</script>
<script>if ('serviceWorker' in navigator) { navigator.serviceWorker.register('<?php echo APP_URL; ?>sw.js').catch(function(){}); }</script>
</body>
</html>