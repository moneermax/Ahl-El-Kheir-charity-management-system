<?php
// dashboard/staff_dashboard.php - Generic dashboard for departmental roles
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';
require_once dirname(__DIR__) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }

$role = Session::getUserRole();
$allowed = ['financial_manager', 'nanny', 'administration', 'social_media'];
if (!in_array($role, $allowed, true)) {
    header('Location: ' . APP_URL . dashboard_for_role($role));
    exit();
}

$pageTitle = 'الرئيسية';
$active    = 'dashboard';

$me = dbFetchOne("SELECT u.*, r.name_ar AS role_name, d.name_ar AS dept_name
                  FROM users u
                  JOIN roles r ON r.id = u.role_id
                  LEFT JOIN departments d ON d.id = u.department_id
                  WHERE u.id = ?", [Session::getUserId()]);

$roleLabels = [
    'financial_manager' => 'المدير المالي',
    'nanny'             => 'أخصائية شؤون الأمهات',
    'administration'    => 'مندوب استرجاع الكفلاء',
    'social_media'      => 'موظف العلاقات العامة والإعلام',
];
$roleIcons = [
    'financial_manager' => 'fa-coins',
    'nanny'             => 'fa-hands-holding-child',
    'administration'    => 'fa-rotate-left',
    'social_media'      => 'fa-bullhorn',
];

include dirname(__DIR__) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>مرحباً، <?php echo e($me['full_name']); ?></h2>
    <p><?php echo e($roleLabels[$role] ?? $role); ?> — <?php echo e($me['dept_name'] ?? ''); ?></p>
</div>
<?php include dirname(__DIR__) . '/includes/alerts.php'; ?>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card fade-in">
            <div class="card-header"><i class="fas <?php echo e($roleIcons[$role] ?? 'fa-gauge-high'); ?> me-2"></i>لوحة <?php echo e($roleLabels[$role] ?? $role); ?></div>
            <div class="card-body text-center py-5">
                <i class="fas <?php echo e($roleIcons[$role] ?? 'fa-gauge-high'); ?>" style="font-size:4rem;color:#1b4d8f;opacity:.3"></i>
                <h5 class="mt-3 text-muted">هذه اللوحة قيد التطوير</h5>
                <p class="text-muted">سيتم إضافة الإحصائيات والأدوات الخاصة بقسمك في المراحل القادمة.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card fade-in">
            <div class="card-header"><i class="fas fa-circle-info me-2"></i>معلومات الحساب</div>
            <div class="card-body small">
                <div class="mb-2"><strong>الاسم:</strong> <?php echo e($me['full_name']); ?></div>
                <div class="mb-2"><strong>المستخدم:</strong> <span dir="ltr"><?php echo e($me['username']); ?></span></div>
                <div class="mb-2"><strong>الدور:</strong> <?php echo e($me['role_name']); ?></div>
                <div class="mb-2"><strong>القسم:</strong> <?php echo e($me['dept_name'] ?? '—'); ?></div>
                <div class="mb-2"><strong>آخر دخول:</strong> <?php echo e($me['last_login_at'] ?? '—'); ?></div>
                <hr>
                <a href="<?php echo url('modules/users/profile.php'); ?>" class="btn btn-outline-primary btn-sm w-100">
                    <i class="fas fa-id-card me-1"></i> ملفي الشخصي
                </a>
            </div>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>