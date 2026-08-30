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
:root{--ak-blue:#1b4d8f;--ak-dark:#0a1f44;--ak-gold:#d9b45a}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;font-family:'Cairo',sans-serif;color:#fff;overflow-x:hidden;background:linear-gradient(135deg,#071b3b 0%,#0d3670 48%,#1b4d8f 100%);position:relative}
body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.14;background:radial-gradient(circle at 12% 18%,rgba(255,255,255,.16),transparent 24%),radial-gradient(circle at 88% 78%,rgba(255,255,255,.1),transparent 28%)}
body:after{content:"";position:fixed;width:720px;height:720px;border:1px solid rgba(255,255,255,.06);border-radius:50%;left:-350px;bottom:-420px;box-shadow:0 0 0 45px rgba(255,255,255,.025),0 0 0 90px rgba(255,255,255,.018);pointer-events:none}
.page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:58px 28px 26px;position:relative;z-index:1}
.wrapper{width:min(1160px,100%);position:relative;display:grid;grid-template-columns:minmax(0,1.12fr) minmax(350px,.78fr);align-items:center;gap:26px;direction:ltr}
.visual,.card-wrap{direction:rtl}html[dir="ltr"] .visual,html[dir="ltr"] .card-wrap{direction:ltr}
.visual{padding:18px 34px 18px 38px;text-align:center}
.brand{display:flex;align-items:center;justify-content:center;gap:14px;margin-bottom:18px}.brand img{width:78px;height:78px;object-fit:contain;border-radius:18px;background:#fff;padding:4px;box-shadow:0 10px 28px rgba(0,0,0,.18)}.brand-name{font-weight:700;font-size:1.18rem;color:#fff}.brand-small{font-size:.72rem;color:rgba(255,255,255,.72)}
.visual h1{font-size:clamp(1.35rem,2.6vw,2.05rem);line-height:1.3;color:#fff;font-weight:700;margin:0 0 7px;white-space:nowrap;text-shadow:0 3px 14px rgba(0,0,0,.16)}
.tagline{font-size:.86rem;color:rgba(255,255,255,.88);font-weight:500;margin-bottom:13px}.description{font-size:.82rem;line-height:1.9;color:rgba(255,255,255,.82);max-width:650px;margin:0 auto 20px}
.goals{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;max-width:650px;margin:0 auto}.goal{min-height:92px;background:rgba(255,255,255,.055);border:1px solid rgba(255,255,255,.14);border-radius:12px;padding:12px 8px;font-size:.72rem;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;box-shadow:inset 0 1px 0 rgba(255,255,255,.04);backdrop-filter:blur(3px);color:rgba(255,255,255,.9)}.goal i{color:#fff;font-size:.85rem}
.card-wrap{display:flex;justify-content:center;position:relative}.login-card{width:min(410px,100%);background:#fff;border-radius:22px;padding:9px;box-shadow:0 24px 60px rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.75)}.card-inner{border:1px solid #edf0f4;border-radius:16px;padding:25px 27px 20px}.card-heading{text-align:center;margin-bottom:22px}.card-heading h2{font-size:1.2rem;font-weight:700;color:var(--ak-dark);margin:0 0 5px}.card-heading p{font-size:.76rem;color:#8491a0;margin:0}.field-label{font-size:.8rem;font-weight:600;margin-bottom:6px;color:#243447}.form-control{font-size:.86rem;padding:.65rem .75rem;border-color:#d9e0e8;border-radius:9px}.input-group-text{background:#f7f9fb;border-color:#d9e0e8;color:var(--ak-blue)}.form-control:focus{border-color:var(--ak-blue);box-shadow:0 0 0 .18rem rgba(27,77,143,.12)}.btn-login{background:var(--ak-blue);border:0;color:#fff;width:100%;padding:.68rem;border-radius:9px;font-weight:600;font-size:.86rem;box-shadow:0 6px 14px rgba(27,77,143,.2)}.btn-login:hover{background:var(--ak-dark);color:#fff}.btn-eye{background:#f7f9fb;border:1px solid #d9e0e8;color:var(--ak-blue)}.forgot{font-size:.78rem}.copyright{text-align:center;font-size:.68rem;color:#a0aab5;margin-top:18px}.language{position:fixed;top:15px;inset-inline-end:18px;z-index:5}.language .btn{background:rgba(7,27,59,.35);border:1px solid rgba(255,255,255,.22);border-radius:9px;color:#fff;font-weight:600;box-shadow:0 4px 14px rgba(0,0,0,.12);backdrop-filter:blur(5px)}
.alert{font-size:.76rem;padding:.55rem .7rem}.mission-label{font-size:.72rem;color:var(--ak-gold);font-weight:700;margin-bottom:7px;letter-spacing:.3px}
@media(max-width:950px){.wrapper{grid-template-columns:1fr;max-width:600px}.visual{padding:10px 5px 20px;order:1}.card-wrap{order:2}.goals{grid-template-columns:repeat(4,1fr)}}
@media(max-width:600px){.page{padding:62px 16px 22px}.visual h1{font-size:1.22rem}.description{font-size:.78rem}.goals{grid-template-columns:1fr 1fr}.goal{min-height:72px}.card-inner{padding:21px 20px 17px}.brand img{width:64px;height:64px}.brand-name{font-size:1.05rem}}
@media(max-width:400px){.visual h1{font-size:1.08rem}.goals{grid-template-columns:1fr}.login-card{border-radius:18px}.card-inner{padding:19px 16px 15px}}
</style>
</head>
<body>
<div class="language"><a href="<?php echo $langSwitchUrl; ?>" class="btn btn-sm"><i class="fas fa-globe me-1"></i><?php echo $isAr?'EN':'عربي'; ?></a></div>
<main class="page"><div class="wrapper">
<section class="visual">
    <div class="brand"><img src="<?php echo APP_URL; ?>assets/img/logo.png" alt="<?php echo htmlspecialchars($orgTitle); ?>"><div><div class="brand-name"><?php echo htmlspecialchars($orgTitle); ?></div><div class="brand-small"><?php echo $isAr?'نظام إدارة العمل الخيري':'Charity Management System'; ?></div></div></div>
    <div class="mission-label"><?php echo $isAr?'رسالتنا':'OUR MISSION'; ?></div>
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