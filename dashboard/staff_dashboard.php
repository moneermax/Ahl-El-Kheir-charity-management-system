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
if (!in_array($role, $allowed, true)) { header('Location: ' . APP_URL . dashboard_for_role($role)); exit(); }
$pageTitle = t('common.home');
$active = 'dashboard';
$me = dbFetchOne("SELECT u.*, r.name_ar AS role_name, d.name_ar AS dept_name FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = ?", [Session::getUserId()]);
$roleLabels = ['financial_manager'=>'dashboard.financial_manager_role','nanny'=>'dashboard.nanny_role','administration'=>'dashboard.administration_role','social_media'=>'dashboard.social_media_role'];
$roleIcons = ['financial_manager'=>'fa-coins','nanny'=>'fa-hands-holding-child','administration'=>'fa-rotate-left','social_media'=>'fa-bullhorn'];
$roleLabel = $roleLabels[$role] ?? null;
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="welcome-section fade-in"><h2><?php echo e(t('dashboard.welcome')); ?>، <?php echo e($me['full_name']); ?></h2><p><?php echo e($roleLabel ? t($roleLabel) : ($me['role_name'] ?? $role)); ?> — <?php echo e($me['dept_name'] ?? ''); ?></p></div>
<?php include dirname(__DIR__) . '/includes/alerts.php'; ?>
<div class="row g-4">
<div class="col-md-8"><div class="card fade-in"><div class="card-header"><i class="fas <?php echo e($roleIcons[$role] ?? 'fa-gauge-high'); ?> me-2"></i><?php echo e(t('dashboard.panel')); ?> <?php echo e($roleLabel ? t($roleLabel) : ($me['role_name'] ?? $role)); ?></div><div class="card-body text-center py-5"><i class="fas <?php echo e($roleIcons[$role] ?? 'fa-gauge-high'); ?>" style="font-size:4rem;color:#1b4d8f;opacity:.3"></i><h5 class="mt-3 text-muted"><?php echo e(t('dashboard.under_development')); ?></h5><p class="text-muted"><?php echo e(t('dashboard.department_tools_coming')); ?></p></div></div></div>
<div class="col-md-4"><div class="card fade-in"><div class="card-header"><i class="fas fa-circle-info me-2"></i><?php echo e(t('dashboard.account_info')); ?></div><div class="card-body small"><div class="mb-2"><strong><?php echo e(t('dashboard.name')); ?>:</strong> <?php echo e($me['full_name']); ?></div><div class="mb-2"><strong><?php echo e(t('dashboard.username')); ?>:</strong> <span dir="ltr"><?php echo e($me['username']); ?></span></div><div class="mb-2"><strong><?php echo e(t('dashboard.role')); ?>:</strong> <?php echo e($roleLabel ? t($roleLabel) : ($me['role_name'] ?? $role)); ?></div><div class="mb-2"><strong><?php echo e(t('dashboard.department')); ?>:</strong> <?php echo e($me['dept_name'] ?? '—'); ?></div><div class="mb-2"><strong><?php echo e(t('dashboard.last_login')); ?>:</strong> <?php echo e($me['last_login_at'] ?? '—'); ?></div><hr><a href="<?php echo url('modules/users/profile.php'); ?>" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-id-card me-1"></i><?php echo e(t('common.profile')); ?></a></div></div></div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>