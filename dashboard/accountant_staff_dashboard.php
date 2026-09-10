<?php
// Accounting Staff Dashboard — restricted operational overview
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';

Session::start();
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'accountant', 'accountant_staff'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = t('dashboard.accountant_title');
$active = 'dashboard';
$uid = Session::getUserId();
$role = Session::getUserRole();
$me = dbFetchOne("SELECT u.*, r.name_ar AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?", [$uid]);

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
        <?php if ($role === 'accountant_staff'): ?>
            <a href="<?php echo url('modules/reports/my_financial.php'); ?>" class="btn btn-outline-primary btn-sm me-2"><i class="fas fa-file-invoice-dollar me-1"></i>تقاريري المالية</a>
        <?php else: ?>
            <a href="<?php echo url('modules/accounting/group_disbursements.php'); ?>" class="btn btn-success btn-sm me-2"><i class="fas fa-users-cog me-1"></i><?php echo e(t('accounting.group_disbursements')); ?></a>
            <a href="<?php echo url('modules/accounting/disbursements.php'); ?>" class="btn btn-primary btn-sm me-2"><i class="fas fa-money-check-dollar me-1"></i><?php echo e(t('accounting.individual_disbursements')); ?></a>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/alerts.php'; ?>

<?php if ($role !== 'accountant_staff'): ?>
<?php
$accountingStats = dbFetchOne("SELECT
  (SELECT COUNT(*) FROM transactions WHERE status = 'posted') AS tx_count,
  (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status = 'posted' AND DATE_FORMAT(transaction_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')) AS month_total,
  (SELECT COUNT(*) FROM sponsor_payments WHERE status = 'pending') AS pending_inflows,
  (SELECT COUNT(*) FROM monthly_disbursements WHERE status IN ('draft','pending_approval')) AS pending_outflows,
  (SELECT COALESCE(SUM(total_amount),0) FROM monthly_disbursements WHERE status = 'pending_approval') AS outflow_amount_pending
");
$recentTransactions = dbFetchAll("SELECT t.id, t.transaction_date, t.amount, t.receipt_number, s.full_name AS sponsor_name
  FROM transactions t LEFT JOIN sponsors s ON s.id = t.sponsor_id
  WHERE t.status = 'posted' ORDER BY t.id DESC LIMIT 5");
$disbursementStats = dbFetchAll("SELECT status, COUNT(*) AS c, COALESCE(SUM(total_amount),0) AS total
    FROM monthly_disbursements
    WHERE status IN ('draft', 'pending_approval', 'approved', 'transferred', 'received')
    GROUP BY status");
$disbursementMap = [];
foreach ($disbursementStats as $row) {
    $disbursementMap[$row['status']] = $row;
}
$group_stats = dbFetchOne("SELECT
    SUM(CASE WHEN verification_status = 'submitted' THEN 1 ELSE 0 END) AS submitted_count,
    SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
    SUM(CASE WHEN verification_status = 'transferred' THEN 1 ELSE 0 END) AS transferred_count
    FROM orphan_groups");
?>
<div class="row g-3 mb-4 fade-in">
    <div class="col-md-3 col-sm-6"><div class="card stat-card border-start border-primary border-4"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1"><?php echo e(t('accounting.total_transactions')); ?></h6><h3 class="mb-0"><?php echo (int)($accountingStats['tx_count'] ?? 0); ?></h3></div><div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-receipt"></i></div></div></div></div></div>
    <div class="col-md-3 col-sm-6"><div class="card stat-card border-start border-success border-4"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1"><?php echo e(t('accounting.month_collection')); ?></h6><h3 class="mb-0"><?php echo number_format((float)($accountingStats['month_total'] ?? 0), 0); ?></h3></div><div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-coins"></i></div></div></div></div></div>
    <div class="col-md-3 col-sm-6"><div class="card stat-card border-start border-warning border-4"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1"><?php echo e(t('accounting.pending_income')); ?></h6><h3 class="mb-0"><?php echo (int)($accountingStats['pending_inflows'] ?? 0); ?></h3></div><div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-hourglass-half"></i></div></div></div></div></div>
    <div class="col-md-3 col-sm-6"><div class="card stat-card border-start border-danger border-4"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1"><?php echo e(t('accounting.pending_outflows')); ?></h6><h3 class="mb-0"><?php echo (int)($accountingStats['pending_outflows'] ?? 0); ?></h3><small class="text-muted"><?php echo number_format((float)($accountingStats['outflow_amount_pending'] ?? 0), 0); ?> <?php echo e(t('accounting.currency_sdg')); ?></small></div><div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-money-check-dollar"></i></div></div></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card fade-in border-warning"><div class="card-body text-center"><i class="fas fa-clipboard-list fa-2x text-warning mb-2"></i><h3 class="mb-0"><?php echo (int)($group_stats['submitted_count'] ?? 0); ?></h3><small class="text-muted"><?php echo e(t('dashboard.groups_pending_review')); ?></small></div></div></div>
    <div class="col-md-3"><div class="card fade-in border-info"><div class="card-body text-center"><i class="fas fa-clock fa-2x text-info mb-2"></i><h3 class="mb-0"><?php echo (int)($group_stats['approved_count'] ?? 0); ?></h3><small class="text-muted"><?php echo e(t('dashboard.batches_pending_receipt')); ?></small></div></div></div>
    <div class="col-md-3"><div class="card fade-in border-success"><div class="card-body text-center"><i class="fas fa-check-circle fa-2x text-success mb-2"></i><h3 class="mb-0"><?php echo (int)($group_stats['transferred_count'] ?? 0); ?></h3><small class="text-muted"><?php echo e(t('dashboard.groups_transferred')); ?></small></div></div></div>
    <div class="col-md-3"><div class="card fade-in border-primary"><div class="card-body text-center"><i class="fas fa-users-gear fa-2x text-primary me-1"></i><h3 class="mb-0"><?php echo count($myNannies); ?></h3><small class="text-muted"><?php echo e(t('dashboard.assigned_nannies')); ?></small></div></div></div>
</div>
<div class="row g-4 fade-in mb-4">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><i class="fas fa-receipt me-2"></i><?php echo e(t('accounting.last_income_transactions')); ?></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>#</th><th><?php echo e(t('accounting.date')); ?></th><th><?php echo e(t('accounting.sponsor')); ?></th><th><?php echo e(t('accounting.receipt')); ?></th><th><?php echo e(t('accounting.amount')); ?></th></tr></thead><tbody>
                <?php if (!$recentTransactions): ?><tr><td colspan="5" class="text-center text-muted py-4"><?php echo e(t('accounting.no_transactions')); ?></td></tr>
                <?php else: foreach ($recentTransactions as $r): ?><tr><td><?php echo (int)$r['id']; ?></td><td><?php echo e($r['transaction_date']); ?></td><td><?php echo e($r['sponsor_name'] ?? '-'); ?></td><td><?php echo e($r['receipt_number'] ?? '-'); ?></td><td><strong><?php echo number_format((float)$r['amount'], 0); ?></strong></td></tr><?php endforeach; endif; ?>
            </tbody></table></div></div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white"><i class="fas fa-money-check-dollar me-2"></i><?php echo e(t('accounting.monthly_transfer_status')); ?><a href="<?php echo url('modules/accounting/disbursements.php'); ?>" class="btn btn-sm btn-light float-end"><?php echo e(t('accounting.manage_outflows')); ?></a></div>
            <div class="card-body"><ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-pencil-alt text-secondary me-2"></i><?php echo e(t('accounting.drafts')); ?></span><span class="badge bg-secondary rounded-pill"><?php echo (int)($disbursementMap['draft']['c'] ?? 0); ?></span></li>
                <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-paper-plane text-warning me-2"></i><?php echo e(t('accounting.sent_to_financial_manager')); ?></span><span class="badge bg-warning text-dark rounded-pill"><?php echo (int)($disbursementMap['pending_approval']['c'] ?? 0); ?></span></li>
                <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-check-circle text-info me-2"></i><?php echo e(t('accounting.approved_waiting_transfer')); ?></span><span class="badge bg-info text-dark rounded-pill"><?php echo (int)($disbursementMap['approved']['c'] ?? 0); ?></span></li>
                <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-truck text-primary me-2"></i><?php echo e(t('accounting.transferred_to_specialist')); ?></span><span class="badge bg-primary rounded-pill"><?php echo (int)($disbursementMap['transferred']['c'] ?? 0); ?></span></li>
                <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="fas fa-flag-checkered text-success me-2"></i><?php echo e(t('accounting.completed_received')); ?></span><span class="badge bg-success rounded-pill"><?php echo (int)($disbursementMap['received']['c'] ?? 0); ?></span></li>
            </ul></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-header"><i class="fas fa-users me-2"></i><?php echo e(t('dashboard.assigned_nannies_families')); ?></div>
    <div class="card-body">
        <?php if (empty($myNannies)): ?>
            <div class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-3"></i><p><?php echo e(t('dashboard.no_assigned_nannies')); ?></p></div>
        <?php else: ?>
            <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th><?php echo e(t('dashboard.nanny')); ?></th><th><?php echo e(t('common.phone')); ?></th><th><?php echo e(t('common.families')); ?></th><th><?php echo e(t('common.children')); ?></th><?php if ($role !== 'accountant_staff'): ?><th><?php echo e(t('common.actions')); ?></th><?php endif; ?></tr></thead><tbody>
            <?php foreach ($myNannies as $n): ?><tr><td><strong><?php echo e($n['full_name']); ?></strong></td><td dir="ltr"><?php echo e($n['phone'] ?? '—'); ?></td><td><span class="badge bg-success"><?php echo (int)$n['families_count']; ?></span></td><td><span class="badge bg-info"><?php echo (int)$n['children_count']; ?></span></td><?php if ($role !== 'accountant_staff'): ?><td><a href="<?php echo url('modules/accounting/group_disbursements.php'); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-money-check-dollar me-1"></i><?php echo e(t('accounting.group_transfers')); ?></a></td><?php endif; ?></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>