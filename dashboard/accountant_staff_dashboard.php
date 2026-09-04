<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';

Session::start();
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'accountant', 'accountant_staff'], true)) {
    header('Location: ' . APP_URL . 'dashboard/');
    exit();
}

$pageTitle = t('dashboard.accountant_title');
$active = 'dashboard';
$uid = Session::getUserId();
$me = dbFetchOne("SELECT u.*, r.name_ar AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?", [$uid]);

$group_stats = dbFetchOne("SELECT
    SUM(CASE WHEN verification_status = 'submitted' THEN 1 ELSE 0 END) AS submitted_count,
    SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
    SUM(CASE WHEN verification_status = 'transferred' THEN 1 ELSE 0 END) AS transferred_count
    FROM orphan_groups");

$myNannies = dbFetchAll("SELECT u.id, u.full_name, u.phone, COUNT(DISTINCT f.id) AS families_count, COUNT(DISTINCT fc.id) AS children_count
    FROM accountant_nanny_assignments a
    JOIN users u ON u.id = a.nanny_id
    LEFT JOIN families f ON f.nanny_id = u.id AND f.status IN ('active','pending')
    LEFT JOIN family_children fc ON fc.family_id = f.id
    WHERE a.accountant_id = ?
    GROUP BY u.id, u.full_name, u.phone", [$uid]);

include __DIR__ . '/../includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><?php echo e(t('dashboard.welcome_user', ['name' => $me['full_name'] ?? ''])); ?></h2>
    <p><?php echo e(t('dashboard.accountant_intro')); ?></p>
    <div class="quick-actions mt-3">
        <a href="<?php echo url('modules/accounting/group_disbursements.php'); ?>" class="btn btn-success btn-sm me-2"><i class="fas fa-users-cog me-1"></i><?php echo e(t('accounting.group_disbursements')); ?></a>
        <a href="<?php echo url('modules/accounting/disbursements.php'); ?>" class="btn btn-primary btn-sm me-2"><i class="fas fa-money-check-dollar me-1"></i><?php echo e(t('accounting.individual_disbursements')); ?></a>
    </div>
</div>
<?php include __DIR__ . '/../includes/alerts.php'; ?>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card fade-in border-warning"><div class="card-body text-center"><i class="fas fa-clipboard-list fa-2x text-warning mb-2"></i><h3 class="mb-0"><?php echo (int)($group_stats['submitted_count'] ?? 0); ?></h3><small class="text-muted"><?php echo e(t('dashboard.groups_pending_review')); ?></small></div></div></div>
    <div class="col-md-3"><div class="card fade-in border-info"><div class="card-body text-center"><i class="fas fa-clock fa-2x text-info mb-2"></i><h3 class="mb-0"><?php echo (int)($group_stats['approved_count'] ?? 0); ?></h3><small class="text-muted"><?php echo e(t('dashboard.batches_pending_receipt')); ?></small></div></div></div>
    <div class="col-md-3"><div class="card fade-in border-success"><div class="card-body text-center"><i class="fas fa-check-circle fa-2x text-success mb-2"></i><h3 class="mb-0"><?php echo (int)($group_stats['transferred_count'] ?? 0); ?></h3><small class="text-muted"><?php echo e(t('dashboard.groups_transferred')); ?></small></div></div></div>
    <div class="col-md-3"><div class="card fade-in border-primary"><div class="card-body text-center"><i class="fas fa-users-gear fa-2x text-primary mb-2"></i><h3 class="mb-0"><?php echo count($myNannies); ?></h3><small class="text-muted"><?php echo e(t('dashboard.assigned_nannies')); ?></small></div></div></div>
</div>
<div class="card fade-in">
    <div class="card-header"><i class="fas fa-users me-2"></i><?php echo e(t('dashboard.assigned_nannies_families')); ?></div>
    <div class="card-body">
        <?php if (empty($myNannies)): ?>
            <div class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-3"></i><p><?php echo e(t('dashboard.no_assigned_nannies')); ?></p></div>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th><?php echo e(t('dashboard.nanny')); ?></th><th><?php echo e(t('common.phone')); ?></th><th><?php echo e(t('common.families')); ?></th><th><?php echo e(t('common.children')); ?></th><th><?php echo e(t('common.actions')); ?></th></tr></thead><tbody>
            <?php foreach ($myNannies as $n): ?><tr><td><strong><?php echo e($n['full_name']); ?></strong></td><td dir="ltr"><?php echo e($n['phone'] ?? '—'); ?></td><td><span class="badge bg-success"><?php echo (int)$n['families_count']; ?></span></td><td><span class="badge bg-info"><?php echo (int)$n['children_count']; ?></span></td><td><a href="<?php echo url('modules/accounting/group_disbursements.php'); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-money-check-dollar me-1"></i><?php echo e(t('accounting.group_transfers')); ?></a></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>