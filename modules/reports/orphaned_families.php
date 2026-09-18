<?php
// modules/reports/orphaned_families.php - Orphaned Families Report
// Family/orphan counts are derived from family_children, not families.children_count.
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
ak_report_require_access('orphaned');

$city_filter = trim($_GET['city'] ?? '');
$status_filter = trim($_GET['status'] ?? 'active');
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));

$pageTitle = t('families.title');
$active = 'reports';

/* family_children is the authoritative family -> orphan relationship. */
$sql = "SELECT f.id, f.family_code, f.mother_name, f.city,
    (SELECT COUNT(*) FROM family_children fc_all WHERE fc_all.family_id = f.id) AS actual_children_count,
    (SELECT COUNT(*) FROM family_children fc_active WHERE fc_active.family_id = f.id AND fc_active.is_active = 1) AS active_children,
    f.monthly_need_amount, f.status
    FROM families f
    WHERE f.status IN ('active', 'pending')
      AND EXISTS (SELECT 1 FROM family_children fc_exists WHERE fc_exists.family_id = f.id AND fc_exists.is_active = 1)
      AND NOT EXISTS (SELECT 1 FROM sponsorships sp JOIN family_children fc ON sp.child_id = fc.id WHERE fc.family_id = f.id AND sp.status = 'active')";
$params = [];
if (!empty($city_filter)) { $sql .= " AND f.city = ?"; $params[] = $city_filter; }
if (!empty($status_filter) && $status_filter !== 'all') { $sql .= " AND f.status = ?"; $params[] = $status_filter; }
$sql .= " ORDER BY f.city ASC, f.mother_name ASC";
$families = dbFetchAll($sql, $params);
$cities_sql = "SELECT DISTINCT city FROM families WHERE city IS NOT NULL AND city != '' ORDER BY city";
$cities = array_column(dbFetchAll($cities_sql), 'city');
$total_orphaned = count($families);
$total_children_waiting = array_sum(array_column($families, 'active_children'));

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<div class="welcome-section fade-in"><h2><i class="fas fa-users-slash me-2"></i><?php echo e($pageTitle); ?></h2><p class="text-muted"><?php echo e(t('families.no_results')); ?></p></div>
<div class="card mb-4 fade-in shadow-sm"><div class="card-body"><form method="GET" action="" class="row g-3 align-items-end"><div class="col-md-3"><label class="form-label fw-bold"><?php echo e(t('common.search')); ?></label><select name="city" class="form-select"><option value=""><?php echo e(t('common.all')); ?></option><?php foreach ($cities as $c): ?><option value="<?php echo e($c); ?>" <?php echo $city_filter === $c ? 'selected' : ''; ?>><?php echo e($c); ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label fw-bold"><?php echo e(t('common.status')); ?></label><select name="status" class="form-select"><option value="all"><?php echo e(t('common.all')); ?></option><option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>><?php echo e(t('families.status_active')); ?></option><option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>><?php echo e(t('families.status_pending')); ?></option></select></div><div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i><?php echo e(t('common.search')); ?></button></div></form></div></div>
<div class="row g-4 mb-4 fade-in"><div class="col-md-6"><div class="card border-0 shadow-sm text-center bg-light h-100"><div class="card-body"><h6 class="text-muted"><?php echo e(t('families.count')); ?></h6><div class="display-6 fw-bold text-danger"><?php echo number_format($total_orphaned); ?></div></div></div></div><div class="col-md-6"><div class="card border-0 shadow-sm text-center bg-light h-100"><div class="card-body"><h6 class="text-muted"><?php echo e(t('families.orphans')); ?></h6><div class="display-6 fw-bold text-warning"><?php echo number_format($total_children_waiting); ?></div></div></div></div></div>
<div class="mb-3 text-end fade-in"><button onclick="exportToExcel()" class="btn btn-success me-2"><i class="fas fa-file-excel me-1"></i><?php echo e(t('common.documents')); ?> Excel</button><button onclick="exportToPDF()" class="btn btn-danger"><i class="fas fa-file-pdf me-1"></i><?php echo e(t('common.documents')); ?> PDF</button></div>
<div id="report-content" class="fade-in"><div class="card shadow-sm"><div class="card-header bg-white fw-bold"><i class="fas fa-list me-2"></i><?php echo e(t('families.title')); ?></div><div class="card-body"><?php if (!empty($families)): ?><div class="table-responsive"><table class="table table-bordered table-hover" id="report-table"><thead class="table-light"><tr><th><?php echo e(t('families.code')); ?></th><th><?php echo e(t('families.mother_name')); ?></th><th><?php echo e(t('families.specialist')); ?></th><th><?php echo e(t('families.orphans')); ?></th><th><?php echo e(t('families.orphans')); ?></th><th><?php echo e(t('families.monthly_commitment')); ?></th><th><?php echo e(t('common.status')); ?></th><th><?php echo e(t('common.actions')); ?></th></tr></thead><tbody><?php foreach ($families as $row): $status_badge = $row['status'] === 'active' ? 'bg-success' : 'bg-warning text-dark'; $status_label = $row['status'] === 'active' ? t('families.status_active') : t('families.status_pending'); ?><tr><td><code><?php echo e($row['family_code'] ?? '-'); ?></code></td><td class="fw-bold"><?php echo e($row['mother_name']); ?></td><td><?php echo e($row['city'] ?? t('common.no_data')); ?></td><td><?php echo number_format((int)$row['actual_children_count']); ?></td><td><span class="badge bg-info"><?php echo number_format((int)$row['active_children']); ?></span></td><td class="text-primary fw-bold"><?php echo $row['monthly_need_amount'] ? number_format($row['monthly_need_amount'], 2) . ' ' . e(t('accounting.currency_sdg')) : '-'; ?></td><td><span class="badge <?php echo $status_badge; ?>"><?php echo e($status_label); ?></span></td><td><a href="<?php echo APP_URL; ?>modules/families/view.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-eye me-1"></i><?php echo e(t('common.view')); ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="alert alert-success text-center"><i class="fas fa-check-circle fa-2x mb-2"></i><h5 class="mb-0"><?php echo e(t('families.no_results')); ?></h5></div><?php endif; ?></div></div></div>
<div class="mt-4 text-center fade-in"><a href="<?php echo APP_URL; ?>modules/reports/index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-right me-1"></i><?php echo e(t('common.back')); ?></a></div>
<script>function exportToExcel(){const table=document.querySelector('#report-content table');if(!table){alert(<?php echo json_encode(t('common.no_data')); ?>);return;}const wb=XLSX.utils.table_to_book(table,{sheet:<?php echo json_encode(t('families.title')); ?>});XLSX.writeFile(wb,'orphaned_families_<?php echo date("Y-m-d"); ?>.xlsx');}function exportToPDF(){const element=document.getElementById('report-content');const opt={margin:0.5,filename:'orphaned_families_<?php echo date("Y-m-d"); ?>.pdf',image:{type:'jpeg',quality:0.98},html2canvas:{scale:2,useCORS:true},jsPDF:{unit:'in',format:'a4',orientation:'landscape'}};html2pdf().set(opt).from(element).save();}</script>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>