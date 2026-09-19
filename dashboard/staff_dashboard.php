<?php
// dashboard/staff_dashboard.php - Generic dashboard for departmental roles
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';
require_once dirname(__DIR__) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
$allowed = ['financial_manager', 'nanny', 'administration', 'staff', 'social_media'];
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
<?php if (in_array($role, ['administration','staff','social_media'], true)): ?>
<?php
$adminStats = [
    'new_requests' => (int)(dbFetchOne("SELECT COUNT(*) c FROM sponsor_requests WHERE status = 'new'")['c'] ?? 0),
    'contacted_requests' => (int)(dbFetchOne("SELECT COUNT(*) c FROM sponsor_requests WHERE status = 'contacted'")['c'] ?? 0),
];

if ($role === 'administration') {
    $adminStats['open_winback'] = (int)(dbFetchOne("SELECT COUNT(*) c FROM winback_campaigns WHERE status IN ('open','contacted')")['c'] ?? 0);
    $adminStats['uncovered_families'] = (int)(dbFetchOne("SELECT COUNT(*) c FROM families f WHERE f.status IN ('active','pending') AND NOT EXISTS (SELECT 1 FROM sponsorships sp JOIN family_children fc ON fc.id = sp.child_id WHERE fc.family_id = f.id AND sp.status = 'active')")['c'] ?? 0);
}

$recentRequests = dbFetchAll("SELECT id, sponsor_name, phone, source, status, created_at FROM sponsor_requests ORDER BY created_at DESC LIMIT 8");
$requestStatusLabels = [
    'new' => 'جديد',
    'contacted' => 'تم التواصل',
    'converted' => 'تم التحويل إلى كفيل',
    'lost' => 'مغلق / لم يكتمل',
];
$requestStatusBadges = [
    'new' => 'bg-info',
    'contacted' => 'bg-warning text-dark',
    'converted' => 'bg-success',
    'lost' => 'bg-secondary',
];
?>
<div class="row g-3 mb-4">
<?php
$stats = [
    ['new_requests', 'طلبات رعاية جديدة', 'text-primary', 'modules/sponsors/new_requests.php'],
    ['contacted_requests', 'طلبات تم التواصل معها', 'text-info', 'modules/sponsors/contacted_requests.php'],
];
if ($role === 'administration') {
    $stats[] = ['open_winback', 'متابعات استرجاع مفتوحة', 'text-warning', 'modules/administration/winback.php'];
    $stats[] = ['uncovered_families', 'أسر بلا كفالة نشطة', 'text-success', 'modules/administration/available_families.php'];
}
foreach ($stats as $stat):
?>
<div class="col-6 <?php echo $role === 'administration' ? 'col-xl-3' : 'col-xl-6'; ?>">
    <div class="card h-100 border-0 shadow-sm fade-in">
        <div class="card-body">
            <div class="text-muted small"><?php echo e($stat[1]); ?></div>
            <div class="fs-3 fw-bold <?php echo e($stat[2]); ?>"><?php echo (int)$adminStats[$stat[0]]; ?></div>
            <a href="<?php echo url($stat[3]); ?>" class="small text-decoration-none">فتح التفاصيل <i class="fas fa-arrow-left"></i></a>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<div class="row g-4">
<div class="col-lg-8">
    <div class="card fade-in">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-bullhorn me-2"></i>أحدث طلبات الرعاية</span>
            <a href="<?php echo url('modules/sponsors/requests.php'); ?>" class="btn btn-sm btn-outline-primary">كل الطلبات</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>التاريخ</th><th>الاسم</th><th>الهاتف</th><th>المصدر</th><th>الحالة</th><th class="text-center">إجراء</th></tr></thead>
                    <tbody>
                    <?php if (!$recentRequests): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">لا توجد طلبات حالياً.</td></tr>
                    <?php else: foreach ($recentRequests as $req):
                        $status = (string)$req['status'];
                        $badge = $requestStatusBadges[$status] ?? 'bg-secondary';
                        $statusLabel = $requestStatusLabels[$status] ?? $status;
                    ?>
                        <tr>
                            <td><small><?php echo e(date('Y-m-d', strtotime($req['created_at']))); ?></small></td>
                            <td><?php echo e($req['sponsor_name']); ?></td>
                            <td dir="ltr">
                                <?php if (!empty($req['phone'])): ?>
                                    <a href="tel:<?php echo e($req['phone']); ?>" class="text-decoration-none"><?php echo e($req['phone']); ?></a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?php echo e($req['source'] ?? '—'); ?></td>
                            <td><span class="badge <?php echo e($badge); ?>"><?php echo e($statusLabel); ?></span></td>
                            <td class="text-center">
                                <a href="<?php echo url('modules/sponsors/requests.php?status=' . urlencode($status)); ?>" class="btn btn-sm btn-outline-primary" title="فتح طلبات الرعاية حسب الحالة">
                                    <i class="fas fa-arrow-up-right-from-square"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="col-lg-4">
    <div class="card fade-in h-100">
        <div class="card-header"><i class="fas fa-bolt me-2"></i>إجراءات سريعة</div>
        <div class="card-body d-grid gap-2">
            <a href="<?php echo url('modules/sponsors/requests.php'); ?>" class="btn btn-primary">
                <i class="fas fa-user-plus me-1"></i>طلبات الانضمام / الرعاية
            </a>
            <?php if ($role === 'administration'): ?>
                <a href="<?php echo url('modules/administration/winback.php'); ?>" class="btn btn-outline-primary">
                    <i class="fas fa-rotate-left me-1"></i>متابعات الاسترجاع
                </a>
            <?php endif; ?>
            <a href="<?php echo url('modules/families/orphan_forms_index.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-file-signature me-1"></i>استمارات الأيتام
            </a>
        </div>
    </div>
</div>
</div>
<?php else: ?>
<div class="row g-4">
<div class="col-md-8"><div class="card fade-in"><div class="card-header"><i class="fas <?php echo e($roleIcons[$role] ?? 'fa-gauge-high'); ?> me-2"></i><?php echo e(t('dashboard.panel')); ?> <?php echo e($roleLabel ? t($roleLabel) : ($me['role_name'] ?? $role)); ?></div><div class="card-body text-center py-5"><i class="fas <?php echo e($roleIcons[$role] ?? 'fa-gauge-high'); ?>" style="font-size:4rem;color:#1b4d8f;opacity:.3"></i><h5 class="mt-3 text-muted"><?php echo e(t('dashboard.under_development')); ?></h5><p class="text-muted"><?php echo e(t('dashboard.department_tools_coming')); ?></p></div></div></div>
<div class="col-md-4"><div class="card fade-in"><div class="card-header"><i class="fas fa-circle-info me-2"></i><?php echo e(t('dashboard.account_info')); ?></div><div class="card-body small"><div class="mb-2"><strong><?php echo e(t('dashboard.name')); ?>:</strong> <?php echo e($me['full_name']); ?></div><div class="mb-2"><strong><?php echo e(t('dashboard.username')); ?>:</strong> <span dir="ltr"><?php echo e($me['username']); ?></span></div><div class="mb-2"><strong><?php echo e(t('dashboard.role')); ?>:</strong> <?php echo e($roleLabel ? t($roleLabel) : ($me['role_name'] ?? $role)); ?></div><div class="mb-2"><strong><?php echo e(t('dashboard.department')); ?>:</strong> <?php echo e($me['dept_name'] ?? '—'); ?></div><div class="mb-2"><strong><?php echo e(t('dashboard.last_login')); ?>:</strong> <?php echo e($me['last_login_at'] ?? '—'); ?></div><hr><a href="<?php echo url('modules/users/profile.php'); ?>" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-id-card me-1"></i><?php echo e(t('common.profile')); ?></a></div></div></div>
</div>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>