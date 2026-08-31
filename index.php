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
$orgTitle=$isAr?'أهل الخير':'Ahl El Kheir';
$tagline=$isAr?'معاً نصنع أثراً يدوم':'Together, we create lasting impact';
$description=$isAr?'نحن نؤمن بأن العمل الخيري رسالة إنسانية نبيلة تهدف إلى دعم المحتاجين وتمكين الأفراد وبناء مجتمع متراحم ومتعاون. نسعى من خلال هذا النظام إلى إدارة مواردنا بكفاءة وشفافية لتحقيق أكبر أثر ممكن.':'We believe charitable work is a noble humanitarian mission focused on supporting those in need, empowering individuals, and building a compassionate and cooperative community. Through this system, we manage our resources efficiently and transparently to achieve the greatest possible impact.';
$goals=$isAr?[
    ['icon'=>'fa-bullseye','title'=>'الاستدامة والتطوير','text'=>'تطوير مستمر لبرامجنا لتحقيق أثر مستدام'],
    ['icon'=>'fa-handshake','title'=>'الشراكة والتعاون','text'=>'بناء شراكات فعالة مع الجهات الداعمة والمتطوعين'],
    ['icon'=>'fa-chart-column','title'=>'الشفافية والمصداقية','text'=>'إدارة مواردنا بشفافية تامة ومصداقية عالية'],
    ['icon'=>'fa-people-group','title'=>'خدمة المجتمع','text'=>'تقديم الدعم للأسر المحتاجة والمستفيدين']
]:[
    ['icon'=>'fa-bullseye','title'=>'Sustainability & Development','text'=>'Continuous development of our programs for lasting impact'],
    ['icon'=>'fa-handshake','title'=>'Partnership & Cooperation','text'=>'Building effective partnerships with supporters and volunteers'],
    ['icon'=>'fa-chart-column','title'=>'Transparency & Credibility','text'=>'Managing our resources with complete transparency and integrity'],
    ['icon'=>'fa-people-group','title'=>'Community Service','text'=>'Providing support to families in need and beneficiaries']
];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $dir; ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo t('تسجيل الدخول'); ?> - <?php echo t('أهل الخير'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?php echo $dir==='rtl'?'.rtl':''; ?>.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="<?php echo APP_URL; ?>assets/css/style.css" rel="stylesheet">
<link rel="manifest" href="<?php echo APP_URL; ?>manifest.json"><meta name="theme-color" content="#0753ad">
<link rel="icon" type="image/png" href="<?php echo APP_URL; ?>assets/img/logo.png"><link rel="apple-touch-icon" href="<?php echo APP_URL; ?>assets/img/logo.png">
<style>
:root{--ak-blue:#0753ad;--ak-blue-dark:#063f86;--ak-blue-light:#1768d0;--ak-gold:#f0c55b;--ak-white:#fff}
*{box-sizing:border-box}
html,body{min-height:100%;margin:0}
body{font-family:'Cairo',sans-serif;color:#fff;overflow-x:hidden;background:#0753ad;position:relative}
body:before{content:"";position:fixed;inset:0;pointer-events:none;background:radial-gradient(circle at 60% 35%,rgba(48,135,255,.22),transparent 33%),radial-gradient(circle at 100% 80%,rgba(0,27,78,.25),transparent 32%),linear-gradient(135deg,rgba(0,38,91,.18),transparent 45%);z-index:0}
body:after{content:"";position:fixed;inset:0;pointer-events:none;opacity:.15;background-image:radial-gradient(circle at 10% 15%,transparent 0 70px,rgba(255,255,255,.11) 71px 72px,transparent 73px),radial-gradient(circle at 91% 83%,transparent 0 85px,rgba(255,255,255,.08) 86px 87px,transparent 88px);background-size:230px 230px,270px 270px;z-index:0}
.page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:42px 34px 32px;position:relative;z-index:1}
.wrapper{width:min(1280px,100%);display:grid;grid-template-columns:minmax(560px,1.08fr) minmax(430px,.82fr);gap:68px;align-items:center;direction:ltr}
.visual,.card-wrap{direction:rtl}html[dir="ltr"] .visual,html[dir="ltr"] .card-wrap{direction:ltr}
.visual{text-align:center;padding:0 8px}
.brand-logo{width:128px;height:128px;object-fit:contain;display:block;margin:0 auto 2px;filter:drop-shadow(0 12px 22px rgba(0,0,0,.18))}
.brand-title{font-size:clamp(2.3rem,4vw,3.15rem);font-weight:800;line-height:1.08;margin:0;color:#fff;text-shadow:0 5px 18px rgba(0,0,0,.16)}
.brand-subtitle{font-size:1.08rem;font-weight:700;color:#fff;margin-top:8px}
.tagline-wrap{display:flex;align-items:center;justify-content:center;gap:12px;margin:12px 0 24px;color:var(--ak-gold);font-size:.94rem;font-weight:700;white-space:nowrap}.tagline-wrap:before,.tagline-wrap:after{content:"";height:2px;width:72px;background:var(--ak-gold);opacity:.9}
.description{max-width:590px;margin:0 auto 38px;font-size:.91rem;line-height:2.05;color:rgba(255,255,255,.93);font-weight:500}
.goals-heading{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:13px;color:var(--ak-gold);font-size:1rem;font-weight:800}.goals-heading:before,.goals-heading:after{content:"";height:1px;background:rgba(240,197,91,.65);flex:1;max-width:190px}
.goals{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;max-width:640px;margin:0 auto}.goal{min-height:176px;padding:12px 9px 14px;border:1px solid rgba(255,255,255,.15);border-radius:10px;background:linear-gradient(180deg,rgba(28,111,213,.32),rgba(2,62,137,.23));box-shadow:inset 0 1px 0 rgba(255,255,255,.08);display:flex;flex-direction:column;align-items:center;justify-content:flex-start;text-align:center;color:#fff}.goal-icon{width:66px;height:66px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:9px;border:2px solid rgba(0,210,220,.55);background:rgba(0,78,171,.38);box-shadow:inset 0 0 0 5px rgba(255,255,255,.025),0 6px 18px rgba(0,0,0,.1);font-size:1.75rem;color:#fff}.goal:nth-child(2) .goal-icon{border-color:rgba(28,123,255,.7)}.goal:nth-child(3) .goal-icon{border-color:rgba(28,123,255,.7)}.goal:nth-child(4) .goal-icon{border-color:rgba(28,123,255,.7)}.goal-title{font-size:.82rem;font-weight:800;line-height:1.55;margin-bottom:7px}.goal-text{font-size:.68rem;line-height:1.85;color:rgba(255,255,255,.92)}
.card-wrap{display:flex;justify-content:center}.login-card{width:100%;max-width:470px;background:#fff;border-radius:22px;padding:0;box-shadow:0 24px 65px rgba(0,21,61,.38);border:1px solid rgba(255,255,255,.7);color:#16263e}.card-inner{padding:42px 38px 30px}.card-heading{text-align:center;margin-bottom:30px}.card-heading h2{font-size:1.72rem;font-weight:800;color:#102b54;margin:0 0 7px}.card-heading p{font-size:.82rem;color:#7c8797;margin:0}.field-label{display:block;font-size:.78rem;font-weight:800;margin-bottom:7px;color:#1a3150}.form-control{font-size:.82rem;padding:.78rem .82rem;border-color:#cfd7e1;border-radius:9px;color:#39475a}.input-group-text{background:#fff;border-color:#cfd7e1;color:#9aa5b3;min-width:43px;justify-content:center}.form-control:focus{border-color:var(--ak-blue);box-shadow:0 0 0 .18rem rgba(7,83,173,.12)}.btn-eye{background:#fff;border:1px solid #cfd7e1;color:#9aa5b3}.btn-login{background:#1760d3;border:0;color:#fff;width:100%;padding:.82rem;border-radius:9px;font-weight:800;font-size:.92rem;box-shadow:0 7px 16px rgba(23,96,211,.22)}.btn-login:hover{background:var(--ak-blue-dark);color:#fff}.remember-row{display:flex;align-items:center;justify-content:flex-start;gap:7px;margin:1px 0 18px;color:#243753;font-size:.76rem}.remember-row input{width:17px;height:17px;accent-color:#1760d3}.forgot-divider{display:flex;align-items:center;gap:12px;margin:21px 0 16px;color:#a1a9b4;font-size:.74rem}.forgot-divider:before,.forgot-divider:after{content:"";height:1px;background:#dfe3e8;flex:1}.forgot{font-size:.79rem;font-weight:700}.copyright{text-align:center;font-size:.66rem;color:#9aa4b0;margin-top:25px}.language{position:fixed;top:35px;inset-inline-end:42px;z-index:5}.language .btn{background:rgba(0,48,113,.32);border:1px solid rgba(255,255,255,.18);border-radius:24px;color:#fff;font-weight:600;padding:.48rem .9rem;box-shadow:0 5px 15px rgba(0,0,0,.1);backdrop-filter:blur(5px)}.language .btn:hover{background:rgba(0,48,113,.5);color:#fff}.alert{font-size:.74rem;padding:.6rem .75rem}
.footer{position:fixed;left:0;right:0;bottom:17px;text-align:center;font-size:.7rem;color:rgba(255,255,255,.78);z-index:2}.footer i{color:#4d9bff}
@media(max-width:1100px){.wrapper{grid-template-columns:minmax(480px,1fr) minmax(390px,.82fr);gap:35px}.visual{padding:0}.description{margin-bottom:28px}.goals{gap:8px}.goal{min-height:155px}.brand-logo{width:108px;height:108px}}
@media(max-width:900px){.page{padding:75px 22px 65px}.wrapper{grid-template-columns:1fr;max-width:620px;gap:38px}.visual{order:1}.card-wrap{order:2}.footer{position:relative;bottom:auto;margin-top:25px}.language{top:18px;inset-inline-end:18px}.goals{max-width:600px}.login-card{max-width:520px}}
@media(max-width:600px){.page{padding:70px 14px 40px}.brand-logo{width:92px;height:92px}.brand-title{font-size:2.05rem}.brand-subtitle{font-size:.9rem}.tagline-wrap{font-size:.79rem;gap:8px;margin:10px 0 18px}.tagline-wrap:before,.tagline-wrap:after{width:45px}.description{font-size:.78rem;line-height:1.9;margin-bottom:25px}.goals{grid-template-columns:1fr 1fr;gap:9px}.goal{min-height:145px}.goal-icon{width:54px;height:54px;font-size:1.4rem}.goal-title{font-size:.75rem}.goal-text{font-size:.63rem}.card-inner{padding:30px 22px 23px}.card-heading h2{font-size:1.45rem}}
@media(max-width:390px){.goals{grid-template-columns:1fr}.goal{min-height:120px}.brand-title{font-size:1.85rem}}
</style>
</head>
<body>
<div class="language"><a href="<?php echo $langSwitchUrl; ?>" class="btn btn-sm"><i class="fas fa-globe me-1"></i><?php echo $isAr?'العربية | English':'English | العربية'; ?></a></div>
<main class="page"><div class="wrapper">
<section class="visual">
    <img class="brand-logo" src="<?php echo APP_URL; ?>assets/img/logo.png" alt="<?php echo htmlspecialchars($orgTitle); ?>">
    <h1 class="brand-title"><?php echo htmlspecialchars($orgTitle); ?></h1>
    <div class="brand-subtitle"><?php echo $isAr?'نظام إدارة الجمعيات الخيرية':'Charity Management System'; ?></div>
    <div class="tagline-wrap"><?php echo htmlspecialchars($tagline); ?></div>
    <p class="description"><?php echo htmlspecialchars($description); ?></p>
    <div class="goals-heading"><?php echo $isAr?'أهدافنا':'Our Goals'; ?></div>
    <div class="goals">
        <?php foreach($goals as $goal): ?>
            <div class="goal">
                <div class="goal-icon"><i class="fas <?php echo htmlspecialchars($goal['icon']); ?>"></i></div>
                <div class="goal-title"><?php echo htmlspecialchars($goal['title']); ?></div>
                <div class="goal-text"><?php echo htmlspecialchars($goal['text']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<section class="card-wrap"><div class="login-card"><div class="card-inner">
    <div class="card-heading"><h2><?php echo $isAr?'تسجيل الدخول':'Sign in'; ?></h2><p><?php echo $isAr?'يرجى إدخال بياناتك للوصول إلى النظام':'Please enter your credentials to access the system'; ?></p></div>
    <?php if($error): ?><div class="alert alert-danger text-center mb-3"><i class="fas fa-exclamation-triangle me-1"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="POST" action="">
        <div class="mb-3"><label for="username" class="field-label"><?php echo t('اسم المستخدم'); ?></label><div class="input-group"><span class="input-group-text"><i class="fas fa-user"></i></span><input type="text" name="username" id="username" class="form-control" placeholder="<?php echo $isAr?'أدخل اسم المستخدم':'Enter username'; ?>" required autofocus></div></div>
        <div class="mb-3"><label for="password" class="field-label"><?php echo t('كلمة المرور'); ?></label><div class="input-group"><span class="input-group-text"><i class="fas fa-lock"></i></span><input type="password" name="password" id="password" class="form-control" placeholder="<?php echo $isAr?'أدخل كلمة المرور':'Enter password'; ?>" required><button type="button" class="btn btn-eye" id="togglePassword" tabindex="-1"><i class="fas fa-eye" id="togglePasswordIcon"></i></button></div></div>
        <div class="remember-row"><input type="checkbox" id="remember"><label for="remember"><?php echo $isAr?'تذكرني':'Remember me'; ?></label></div>
        <button type="submit" class="btn btn-login"><i class="fas fa-lock me-2"></i><?php echo t('تسجيل الدخول'); ?></button>
    </form>
    <div class="forgot-divider"><span><?php echo $isAr?'أو':'OR'; ?></span></div>
    <div class="text-center"><a href="<?php echo APP_URL; ?>modules/users/password_recovery_request.php" class="forgot text-primary text-decoration-none"><i class="fas fa-key me-1"></i><?php echo $isAr?'نسيت كلمة المرور؟':'Forgot your password?'; ?></a></div>
    <div class="copyright">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($orgTitle); ?> · <?php echo $isAr?'جميع الحقوق محفوظة':'All rights reserved'; ?></div>
</div></div></section>
</div></main>
<div class="footer"><?php echo $isAr?'أهل الخير':'Ahl El Kheir'; ?> <span>♥</span> <?php echo $isAr?'جميع الحقوق محفوظة':'All rights reserved'; ?></div>
<script>(function(){var p=document.getElementById('password'),b=document.getElementById('togglePassword'),i=document.getElementById('togglePasswordIcon');if(!p||!b)return;b.onclick=function(){p.type=p.type==='password'?'text':'password';i.classList.toggle('fa-eye');i.classList.toggle('fa-eye-slash');p.focus();};})();</script>
<script>if('serviceWorker' in navigator){navigator.serviceWorker.register('<?php echo APP_URL; ?>sw.js').catch(function(){});}</script>
</body></html>