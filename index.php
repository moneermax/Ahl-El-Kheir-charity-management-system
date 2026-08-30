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
if (isset($_GET['logout']) || (isset($_GET['action']) && $_GET['action'] === 'logout')) { header('Location: ' . APP_URL . 'logout.php'); exit(); }
if (Session::isLoggedIn()) { $dash = dashboard_for_role(Session::getUserRole()); if ($dash === 'index.php') { session_unset(); session_destroy(); } else { header('Location: ' . APP_URL . $dash); exit(); } }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? ''); $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') { $error = 'يرجى إدخال اسم المستخدم وكلمة المرور.'; }
    else {
        $user = dbFetchOne("SELECT u.id, u.username, u.full_name, u.password_hash, u.is_active, r.code as role_code FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ?", [$username]);
        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) { $error = 'حسابك موقوف. يرجى التواصل مع الإدارة.'; }
            else {
                $_SESSION['user_id']=(int)$user['id']; $_SESSION['user_name']=$user['full_name']; $_SESSION['user_username']=$user['username']; $_SESSION['user_role']=$user['role_code'];
                dbExecute("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
                try { dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES (?, 'LOGIN', 'users', ?, ?, ?, NOW())", [$user['id'],$user['id'],$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']); } catch (Throwable $e) {}
                header('Location: ' . APP_URL . dashboard_for_role($user['role_code'])); exit();
            }
        } else { $error = 'بيانات الدخول غير صحيحة.'; }
    }
}
$uri=(string)($_SERVER['REQUEST_URI']??''); $langSwitchUrl=e($uri.(strpos($uri,'?')!==false?'&':'?').'lang='.($lang==='ar'?'en':'ar'));
$isAr=$lang==='ar';
$orgTitle=$isAr?'منظمة أهل الخير':'Ahl El Kheir Organization';
$tagline=$isAr?'معاً نصنع أثراً يدوم':'Together, we create lasting impact';
$description=$isAr?'منصة متكاملة لتنظيم العمل الخيري وإدارة الأسر والأيتام والكفالات والدعم، بما يساعد فريق العمل على تقديم خدمة أكثر كفاءة وشفافية.':'An integrated platform for managing charitable work, families, orphans, sponsorships and support, helping teams deliver services with greater efficiency and transparency.';
$goals=$isAr?['رعاية الأسر والأيتام','إدارة الكفالات والدعم','بيانات وتقارير دقيقة','شفافية وكفاءة في العمل']:['Family & orphan care','Sponsorship & support','Accurate data & reporting','Transparency & efficiency'];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo t('تسجيل الدخول'); ?> - <?php echo t('أهل الخير'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?php echo $dir==='rtl'?'.rtl':''; ?>.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?php echo APP_URL; ?>assets/css/style.css" rel="stylesheet">
<link rel="manifest" href="<?php echo APP_URL; ?>manifest.json"><meta name="theme-color" content="#1b4d8f">
<link rel="icon" type="image/png" href="<?php echo APP_URL; ?>assets/img/logo.png"><link rel="apple-touch-icon" href="<?php echo APP_URL; ?>assets/img/logo.png">
<style>
:root{--ak-blue:#1b4d8f;--ak-dark:#0a1f44;--ak-light:#eef4fb}
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:'Cairo',sans-serif;background:#f3f6fa;color:#243447;overflow-x:hidden}
.page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:54px 28px 24px;position:relative}
.background-shape{position:absolute;inset:0;overflow:hidden;pointer-events:none}.background-shape:before{content:"";position:absolute;width:58vw;height:58vw;max-width:720px;max-height:720px;border-radius:50%;background:var(--ak-blue);top:-28%;inset-inline-start:-18%;opacity:.07}.background-shape:after{content:"";position:absolute;width:420px;height:420px;border-radius:50%;background:var(--ak-blue);bottom:-230px;inset-inline-end:-150px;opacity:.06}
.wrapper{width:min(1120px,100%);position:relative;display:grid;grid-template-columns:minmax(0,1.15fr) minmax(330px,.72fr);align-items:center;direction:ltr}
html[dir="rtl"] .wrapper{grid-template-columns:minmax(330px,.72fr) minmax(0,1.15fr)}
.visual,.card-wrap{direction:rtl}html[dir="ltr"] .visual,html[dir="ltr"] .card-wrap{direction:ltr}
.visual{padding:20px 55px 20px 35px}.brand{display:flex;align-items:center;gap:14px;margin-bottom:30px}.brand img{width:68px;height:68px;object-fit:cover;border-radius:18px;box-shadow:0 8px 24px rgba(27,77,143,.18)}.brand-name{font-weight:700;font-size:1.15rem;color:var(--ak-dark)}.brand-small{font-size:.72rem;color:#6d7c8f}
.visual h1{font-size:clamp(2rem,4vw,3.25rem);line-height:1.25;color:var(--ak-dark);font-weight:700;margin:0 0 10px}.tagline{font-size:1.05rem;color:var(--ak-blue);font-weight:600;margin-bottom:16px}.description{font-size:.9rem;line-height:2;color:#637286;max-width:620px;margin-bottom:25px}
.goals{display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:610px}.goal{background:#fff;border:1px solid #e4eaf1;border-radius:12px;padding:11px 13px;font-size:.78rem;display:flex;align-items:center;gap:9px;box-shadow:0 4px 15px rgba(20,45,80,.04)}.goal i{color:var(--ak-blue);font-size:.8rem}
.card-wrap{display:flex;justify-content:center;position:relative}.login-card{width:min(405px,100%);background:#fff;border-radius:22px;padding:9px;box-shadow:0 22px 55px rgba(24,50,85,.16);border:1px solid #e5eaf0}.card-inner{border:1px solid #edf0f4;border-radius:16px;padding:25px 27px 20px}.card-heading{text-align:center;margin-bottom:22px}.card-heading h2{font-size:1.2rem;font-weight:700;color:var(--ak-dark);margin:0 0 5px}.card-heading p{font-size:.76rem;color:#8491a0;margin:0}.field-label{font-size:.8rem;font-weight:600;margin-bottom:6px}.form-control{font-size:.86rem;padding:.65rem .75rem;border-color:#d9e0e8;border-radius:9px}.input-group-text{background:#f7f9fb;border-color:#d9e0e8;color:var(--ak-blue)}.form-control:focus{border-color:var(--ak-blue);box-shadow:0 0 0 .18rem rgba(27,77,143,.12)}.btn-login{background:var(--ak-blue);border:0;color:#fff;width:100%;padding:.68rem;border-radius:9px;font-weight:600;font-size:.86rem;box-shadow:0 6px 14px rgba(27,77,143,.2)}.btn-login:hover{background:var(--ak-dark);color:#fff}.btn-eye{background:#f7f9fb;border:1px solid #d9e0e8;color:var(--ak-blue)}.forgot{font-size:.78rem}.copyright{text-align:center;font-size:.68rem;color:#a0aab5;margin-top:18px}.language{position:fixed;top:15px;inset-inline-end:18px;z-index:5}.language .btn{background:#fff;border:1px solid #dce3eb;border-radius:9px;color:var(--ak-blue);font-weight:600;box-shadow:0 4px 14px rgba(30,55,90,.08)}
.alert{font-size:.76rem;padding:.55rem .7rem}
@media(max-width:850px){.page{padding:60px 18px 25px}.wrapper,html[dir="rtl"] .wrapper{grid-template-columns:1fr;max-width:560px}.visual{padding:10px 5px 22px;text-align:center;order:1}.brand{justify-content:center;margin-bottom:18px}.brand-name{text-align:start}.visual h1{font-size:1.8rem}.description{margin-left:auto;margin-right:auto}.goals{text-align:start;margin:auto}.card-wrap{order:2}.login-card{width:100%}}
@media(max-width:470px){.goals{grid-template-columns:1fr}.card-inner{padding:21px 20px 17px}.visual h1{font-size:1.55rem}.description{font-size:.82rem}}
</style>
</head>
<body>
<div class="background-shape"></div>
<div class="language"><a href="<?php echo $langSwitchUrl; ?>" class="btn btn-sm"><i class="fas fa-globe me-1"></i><?php echo $isAr?'EN':'عربي'; ?></a></div>
<main class="page"><div class="wrapper">
<section class="visual">
    <div class="brand"><img src="<?php echo APP_URL; ?>assets/img/logo.png" alt="<?php echo htmlspecialchars($orgTitle); ?>"><div><div class="brand-name"><?php echo htmlspecialchars($orgTitle); ?></div><div class="brand-small"><?php echo $isAr?'نظام إدارة العمل الخيري':'Charity Management System'; ?></div></div></div>
    <h1><?php echo htmlspecialchars($tagline); ?></h1>
    <div class="tagline"><?php echo $isAr?'نخدم باهتمام، ندير بكفاءة، ونقيس الأثر.':'Serve with care. Manage with purpose. Measure impact.'; ?></div>
    <p class="description"><?php echo htmlspecialchars($description); ?></p>
    <div class="goals"><?php foreach($goals as $goal): ?><div class="goal"><i class="fas fa-circle-check"></i><span><?php echo htmlspecialchars($goal); ?></span></div><?php endforeach; ?></div>
</section>
<section class="card-wrap"><div class="login-card"><div class="card-inner">
    <div class="card-heading"><h2><?php echo $isAr?'تسجيل الدخول':'Sign in'; ?></h2><p><?php echo $isAr?'أدخل بيانات حسابك للمتابعة إلى النظام':'Enter your account details to continue'; ?></p></div>
    <?php if($error): ?><div class="alert alert-danger text-center mb-3"><i class="fas fa-exclamation-triangle me-1"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="POST" action="">
        <div class="mb-3"><label for="username" class="field-label"><?php echo t('اسم المستخدم'); ?></label><div class="input-group"><span class="input-group-text"><i class="fas fa-user"></i></span><input type="text" name="username" id="username" class="form-control" required autofocus></div></div>
        <div class="mb-3"><label for="password" class="field-label"><?php echo t('كلمة المرور'); ?></label><div class="input-group"><span class="input-group-text"><i class="fas fa-lock"></i></span><input type="password" name="password" id="password" class="form-control" required><button type="button" class="btn btn-eye" id="togglePassword" tabindex="-1"><i class="fas fa-eye" id="togglePasswordIcon"></i></button></div></div>
        <button type="submit" class="btn btn-login"><i class="fas fa-sign-in-alt me-2"></i><?php echo t('تسجيل الدخول'); ?></button>
    </form>
    <div class="text-center mt-3"><a href="<?php echo APP_URL; ?>modules/users/password_recovery_request.php" class="forgot text-primary text-decoration-none"><i class="fas fa-key me-1"></i><?php echo $isAr?'نسيت كلمة المرور؟':'Forgot your password?'; ?></a></div>
    <div class="copyright">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($orgTitle); ?> · <?php echo $isAr?'جميع الحقوق محفوظة':'All rights reserved'; ?></div>
</div></div></section>
</div></main>
<script>(function(){var p=document.getElementById('password'),b=document.getElementById('togglePassword'),i=document.getElementById('togglePasswordIcon');if(!p||!b)return;b.onclick=function(){p.type=p.type==='password'?'text':'password';i.classList.toggle('fa-eye');i.classList.toggle('fa-eye-slash');p.focus();};})();</script>
<script>if('serviceWorker' in navigator){navigator.serviceWorker.register('<?php echo APP_URL; ?>sw.js').catch(function(){});}</script>
</body></html>