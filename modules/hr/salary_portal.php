<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'login.php');
    exit;
}

$role = (string)Session::getUserRole();
$portalRoles = ['financial_manager', 'admin', 'fm', 'hr_manager', 'hr_staff'];
if (!in_array($role, $portalRoles, true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit;
}

$pageTitle = 'بوابة الرواتب والحوافز';
$active = 'salary_portal';

$links = [];
if (in_array($role, ['financial_manager', 'admin', 'fm'], true)) {
    $links[] = [
        'title' => 'إدارة سلف الرواتب',
        'description' => 'إدارة سياسة السلف ومراجعة طلبات الموظفين ومتابعة المعالجة المحاسبية والصرف.',
        'url' => 'modules/hr/salary_advance_dashboard.php',
        'icon' => 'fa-hand-holding-dollar',
        'theme' => 'success',
    ];
    $links[] = [
        'title' => 'إعفاء سلف الرواتب',
        'description' => 'إعداد ومراجعة قرارات إعفاء سلف الرواتب وفق الصلاحيات ومسار الاعتماد المعتمد.',
        'url' => 'modules/hr/salary_advance_waiver_fm.php',
        'icon' => 'fa-hand-sparkles',
        'theme' => 'primary',
    ];
}
if (in_array($role, ['hr_manager', 'hr_staff', 'financial_manager', 'admin'], true)) {
    $links[] = [
        'title' => 'إدارة الرواتب',
        'description' => 'إنشاء ومراجعة مسيرات الرواتب ومتابعة مكونات الراتب وخصم السلف.',
        'url' => 'modules/hr/payroll.php',
        'icon' => 'fa-file-invoice-dollar',
        'theme' => 'info',
    ];
}
if ($role === 'financial_manager') {
    $links[] = [
        'title' => 'سياسة حوافز الموظفين',
        'description' => 'إدارة إصدارات سياسة الحوافز وإرسالها لاعتماد المدير العام ومتابعة مبالغها.',
        'url' => 'modules/hr/incentive_policy.php',
        'icon' => 'fa-award',
        'theme' => 'warning',
    ];
}

require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.salary-portal{max-width:1450px;margin:0 auto}
.salary-portal .hero{background:linear-gradient(135deg,#173f73,#2d67ad);color:#fff;border-radius:14px;padding:24px 26px;margin-bottom:22px}
.salary-portal .hero h1{font-size:1.45rem;font-weight:800;margin:0}
.salary-portal .hero p{margin:7px 0 0;opacity:.92;font-size:.88rem;line-height:1.7}
.salary-portal .portal-card{height:100%;background:#fff;border:1px solid #e5eaf0;border-radius:14px;box-shadow:0 2px 12px rgba(16,24,40,.06);transition:transform .15s ease,box-shadow .15s ease;border-top:4px solid #1b4d8f}
.salary-portal .portal-card:hover{transform:translateY(-2px);box-shadow:0 5px 18px rgba(16,24,40,.1)}
.salary-portal .portal-body{padding:22px}
.salary-portal .portal-icon{width:52px;height:52px;border-radius:12px;background:#eaf2fb;color:#1b4d8f;display:flex;align-items:center;justify-content:center;font-size:1.35rem;margin-bottom:14px}
.salary-portal .portal-title{font-size:1.05rem;font-weight:800;color:#173f73;margin-bottom:7px}
.salary-portal .portal-desc{color:#687385;font-size:.84rem;line-height:1.7;min-height:72px}
</style>

<div class="salary-portal">
    <section class="hero">
        <h1><i class="fas fa-users-gear me-2"></i><?=e($pageTitle)?></h1>
        <p>بوابة موحدة تجمع روابط إدارة الرواتب والسلف وسياسة حوافز الموظفين في مكان واحد، مع إظهار الخيارات التي تسمح بها صلاحيات حسابك.</p>
    </section>

    <div class="row g-4">
        <?php foreach ($links as $link): ?>
            <div class="col-md-6 col-xl-4">
                <a href="<?=e(APP_URL . $link['url'])?>" class="text-decoration-none text-reset">
                    <div class="portal-card">
                        <div class="portal-body">
                            <div class="portal-icon"><i class="fas <?=e($link['icon'])?>"></i></div>
                            <div class="portal-title"><?=e($link['title'])?></div>
                            <div class="portal-desc"><?=e($link['description'])?></div>
                            <span class="btn btn-outline-<?=e($link['theme'])?> btn-sm mt-2">فتح الصفحة <i class="fas fa-arrow-left ms-1"></i></span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
