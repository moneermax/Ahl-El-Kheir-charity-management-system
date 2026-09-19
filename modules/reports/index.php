<?php
// modules/reports/index.php - Reports Center (Role-Based)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/report_registry.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$role = Session::getUserRole();
$uid = Session::getUserId();
ak_report_require_access('overview');

$allowed_reports = ak_report_allowed_catalog($role);

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));

$pageTitle = t('navigation.reports');
$active = 'reports';

$stats = [];
$stats['sponsors'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE status='active'")['c'] ?? 0);
$stats['families'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM families WHERE status='active'")['c'] ?? 0);
$stats['active_sponsorships'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsorships WHERE status='active'")['c'] ?? 0);
$stats['monthly_commitment'] = (float)(dbFetchOne("SELECT COALESCE(SUM(monthly_amount), 0) AS s FROM sponsorships WHERE status='active'")['s'] ?? 0);

try {
    $stats['orphaned_families'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM vw_families_without_sponsorship")['c'] ?? 0);
} catch (Exception $e) {
    $stats['orphaned_families'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM families f WHERE f.status IN ('active','pending') AND f.children_count > 0 AND NOT EXISTS (SELECT 1 FROM sponsorships sp JOIN family_children fc ON sp.child_id = fc.id WHERE fc.family_id = f.id AND sp.status = 'active')")['c'] ?? 0);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-chart-pie me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted"><?php echo e(t('navigation.general_reports')); ?> — <?php echo e($from); ?> → <?php echo e($to); ?></p>
</div>

<div class="card mb-4 fade-in shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold"><?php echo e(t('accounting.from')); ?></label>
                <input type="date" name="from" class="form-control" value="<?php echo e($from); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold"><?php echo e(t('accounting.to')); ?></label>
                <input type="date" name="to" class="form-control" value="<?php echo e($to); ?>" required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100" style="background-color: #1b4d8f; border-color: #1b4d8f;">
                    <i class="fas fa-filter me-1"></i><?php echo e(t('common.search')); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="alert alert-light border mb-4 fade-in">
    <i class="fas fa-shield-halved me-1 text-primary"></i>
    <strong>لوحة التقارير الموحدة:</strong> تظهر هنا التقارير المتاحة لك وفق دور المستخدم وصلاحيات الوصول.
</div>

<div class="card shadow-sm fade-in mb-4">
    <div class="card-header bg-white fw-bold" style="color:#1b4d8f;">
        <i class="fas fa-folder-open me-2"></i>التقارير المتاحة لك
        <span class="badge bg-light text-dark border ms-2"><?php echo count($allowed_reports); ?></span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($allowed_reports as $key => $report): ?>
                <?php if ($key === 'overview') continue; ?>
                <div class="col-xl-4 col-md-6">
                    <a href="<?php echo APP_URL . $report['url']; ?>?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="text-decoration-none">
                        <div class="report-card h-100 p-4 border rounded-3">
                            <div class="d-flex align-items-start gap-3">
                                <div class="report-icon"><i class="fas <?php echo e($report['icon']); ?>"></i></div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-2 fw-bold"><?php echo e($report['label']); ?></h5>
                                    <p class="text-muted small mb-0"><?php echo e($report['description']); ?></p>
                                </div>
                                <i class="fas fa-arrow-left text-muted mt-1"></i>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
            <?php if (isset($allowed_reports['overview'])): ?>
                <div class="col-xl-4 col-md-6">
                    <div class="report-card report-current h-100 p-4 border rounded-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="report-icon"><i class="fas fa-chart-pie"></i></div>
                            <div class="flex-grow-1">
                                <h5 class="mb-2 fw-bold">المؤشرات العامة</h5>
                                <p class="text-muted small mb-0">أنت الآن في لوحة التقارير الموحدة والمؤشرات العامة.</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.report-card{background:#fff;transition:all .2s ease;min-height:128px}
.report-card:hover{box-shadow:0 .5rem 1rem rgba(27,77,143,.15);transform:translateY(-2px);border-color:#1b4d8f!important}
.report-current{background:#f8fbff;border-color:#1b4d8f!important}
.report-icon{width:48px;height:48px;min-width:48px;border-radius:12px;background:#eef4ff;color:#1b4d8f;display:flex;align-items:center;justify-content:center;font-size:1.25rem}
</style>

<div class="row g-4 mb-4 fade-in">
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100" style="border-right:4px solid #1b4d8f !important;"><div class="card-body text-center"><div class="display-6 fw-bold text-primary mb-2"><?php echo number_format($stats['sponsors']); ?></div><div class="text-muted small fw-bold"><?php echo e(t('common.active')); ?> <?php echo e(t('common.sponsors')); ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100" style="border-right:4px solid #198754 !important;"><div class="card-body text-center"><div class="display-6 fw-bold text-success mb-2"><?php echo number_format($stats['active_sponsorships']); ?></div><div class="text-muted small fw-bold"><?php echo e(t('common.active')); ?> <?php echo e(t('common.sponsorships')); ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100" style="border-right:4px solid #fd7e14 !important;"><div class="card-body text-center"><div class="display-6 fw-bold text-warning mb-2"><?php echo number_format($stats['families']); ?></div><div class="text-muted small fw-bold"><?php echo e(t('common.families')); ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100" style="border-right:4px solid #6f42c1 !important;"><div class="card-body text-center"><div class="display-6 fw-bold" style="color:#6f42c1;font-size:1.8rem;"><?php echo number_format($stats['monthly_commitment'],0); ?> <span class="fs-6"><?php echo e(t('accounting.currency_sdg')); ?></span></div><div class="text-muted small fw-bold"><?php echo e(t('families.monthly_commitment')); ?></div></div></div></div>
</div>


<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="<?php echo APP_URL; ?>modules/reports/index.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
