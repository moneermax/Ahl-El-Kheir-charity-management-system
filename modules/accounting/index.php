<?php
// modules/accounting/index.php - Accounting Dashboard
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'general_manager', 'vice_general_manager', 'accountant', 'financial_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = t('accounting.dashboard_title');
$active = 'accounting';
$stats = [];
$stats['total_accounts'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM accounts WHERE is_active = 1")['c'] ?? 0);
$stats['asset_accounts'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM accounts WHERE account_type = 'asset' AND is_active = 1")['c'] ?? 0);
$stats['revenue_accounts'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM accounts WHERE account_type = 'revenue' AND is_active = 1")['c'] ?? 0);
$stats['expense_accounts'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM accounts WHERE account_type = 'expense' AND is_active = 1")['c'] ?? 0);
$stats['recent_entries'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM journal_entries WHERE status = 'posted' AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")['c'] ?? 0);
$stats['monthly_transactions'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM transactions WHERE transaction_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND status = 'posted'")['c'] ?? 0);
$stats['pending_disbursements'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM monthly_disbursements WHERE status = 'pending_approval'")['c'] ?? 0);
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-calculator me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted"><?php echo e(t('accounting.dashboard_description')); ?></p>
</div>
<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100" style="border-right:4px solid #1b4d8f !important;"><div class="card-body text-center"><div class="display-6 fw-bold text-primary mb-2"><?php echo number_format($stats['total_accounts']); ?></div><div class="text-muted small fw-bold"><?php echo e(t('accounting.accounts_tree')); ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100" style="border-right:4px solid #198754 !important;"><div class="card-body text-center"><div class="display-6 fw-bold text-success mb-2"><?php echo number_format($stats['asset_accounts']); ?></div><div class="text-muted small fw-bold"><?php echo e(t('accounting.type_asset')); ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100" style="border-right:4px solid #fd7e14 !important;"><div class="card-body text-center"><div class="display-6 fw-bold text-warning mb-2"><?php echo number_format($stats['expense_accounts']); ?></div><div class="text-muted small fw-bold"><?php echo e(t('accounting.type_expense')); ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100" style="border-right:4px solid #6f42c1 !important;"><div class="card-body text-center"><div class="display-6 fw-bold text-info mb-2"><?php echo number_format($stats['revenue_accounts']); ?></div><div class="text-muted small fw-bold"><?php echo e(t('accounting.type_revenue')); ?></div></div></div></div>
</div>
<div class="row g-4">
    <div class="col-md-6"><div class="card shadow-sm"><div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-bolt me-2"></i><?php echo e(t('accounting.manage_outflows')); ?></div><div class="card-body"><div class="d-grid gap-2">
        <a href="<?php echo APP_URL; ?>modules/accounting/accounts.php" class="btn btn-outline-primary"><i class="fas fa-list me-2"></i><?php echo e(t('accounting.accounts_tree')); ?></a>
        <a href="<?php echo APP_URL; ?>modules/accounting/journal.php" class="btn btn-outline-primary"><i class="fas fa-book me-2"></i><?php echo e(t('accounting.journal')); ?></a>
        <a href="<?php echo APP_URL; ?>modules/transactions/index.php" class="btn btn-outline-primary"><i class="fas fa-exchange-alt me-2"></i><?php echo e(t('accounting.transactions')); ?></a>
        <a href="<?php echo APP_URL; ?>modules/reports/financial.php" class="btn btn-outline-primary"><i class="fas fa-chart-bar me-2"></i><?php echo e(t('accounting.financial_reports')); ?></a>
    </div></div></div></div>
    <div class="col-md-6"><div class="card shadow-sm"><div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-chart-line me-2"></i><?php echo e(t('accounting.activity_summary')); ?></div><div class="card-body"><ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between align-items-center"><?php echo e(t('accounting.recent_journal_entries')); ?><span class="badge bg-primary rounded-pill"><?php echo number_format($stats['recent_entries']); ?></span></li>
        <li class="list-group-item d-flex justify-content-between align-items-center"><?php echo e(t('accounting.month_transactions')); ?><span class="badge bg-success rounded-pill"><?php echo number_format($stats['monthly_transactions']); ?></span></li>
        <li class="list-group-item d-flex justify-content-between align-items-center"><?php echo e(t('accounting.pending_disbursements')); ?><span class="badge bg-warning text-dark rounded-pill"><?php echo number_format($stats['pending_disbursements']); ?></span></li>
    </ul></div></div></div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>