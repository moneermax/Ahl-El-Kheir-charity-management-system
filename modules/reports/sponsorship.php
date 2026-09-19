<?php
// modules/reports/sponsorship.php - Sponsorship Reports
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
ak_report_require_access('sponsorship');

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));
$report_type = trim($_GET['report'] ?? 'coverage');
$allowed_reports = ['coverage', 'retention', 'distribution', 'status_summary'];
if (!in_array($report_type, $allowed_reports, true)) $report_type = 'coverage';

$pageTitle = t('sponsorships.title');

// ==================== Report 1: Child coverage ====================
$coverage_data = [];
if ($report_type === 'coverage') {
    $total_children = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM family_children WHERE is_active = 1")['c'] ?? 0);
    $sponsored_children = (int)(dbFetchOne("SELECT COUNT(DISTINCT child_id) AS c FROM sponsorships WHERE status = 'active' AND child_id IS NOT NULL")['c'] ?? 0);

    try {
        $orphaned_families = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM vw_families_without_sponsorship")['c'] ?? 0);
    } catch (Exception $e) {
        $orphaned_families = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM families f WHERE f.status IN ('active','pending') AND f.children_count > 0 AND NOT EXISTS (SELECT 1 FROM sponsorships sp JOIN family_children fc ON sp.child_id = fc.id WHERE fc.family_id = f.id AND sp.status = 'active')")['c'] ?? 0);
    }

    $total_families = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM families WHERE status IN ('active','pending')")['c'] ?? 0);
    $coverage_data = [
        'total_children' => $total_children,
        'sponsored_children' => $sponsored_children,
        'unsponsored_children' => max(0, $total_children - $sponsored_children),
        'coverage_percent' => $total_children > 0 ? round(($sponsored_children / $total_children) * 100, 1) : 0,
        'orphaned_families' => $orphaned_families,
        'total_families' => $total_families,
    ];
}

// ==================== Report 2: Sponsor retention ====================
$retention_data = [];
if ($report_type === 'retention') {
    $total_sponsors = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors")['c'] ?? 0);
    $active_sponsors = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE status = 'active'")['c'] ?? 0);
    $cancelled_sponsors = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE status = 'cancelled'")['c'] ?? 0);
    $completed_sponsors = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE status = 'completed'")['c'] ?? 0);
    $new_sponsors = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE created_at BETWEEN '{$from}' AND '{$to}'")['c'] ?? 0);
    $retention_data = [
        'total' => $total_sponsors,
        'active' => $active_sponsors,
        'cancelled' => $cancelled_sponsors,
        'completed' => $completed_sponsors,
        'new_in_period' => $new_sponsors,
        'retention_rate' => $total_sponsors > 0 ? round(($active_sponsors / $total_sponsors) * 100, 1) : 0,
    ];
}

// ==================== Report 3: Distribution by letter/supervisor ====================
$distribution_data = [];
if ($report_type === 'distribution') {
    $distribution_data['by_letter'] = dbFetchAll("SELECT
        l.name_ar as letter_name,
        l.code as letter_code,
        COUNT(s.id) as sponsor_count,
        SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_count
        FROM letters l
        LEFT JOIN sponsors s ON s.first_letter_id = l.id
        GROUP BY l.id, l.name_ar, l.code
        ORDER BY sponsor_count DESC");

    $distribution_data['by_supervisor'] = dbFetchAll("SELECT
        u.full_name as supervisor_name,
        COUNT(s.id) as sponsor_count,
        SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_count
        FROM users u
        LEFT JOIN sponsors s ON s.supervisor_id = u.id
        WHERE u.role_id = 4
        GROUP BY u.id, u.full_name
        ORDER BY sponsor_count DESC");
}

// ==================== Report 4: Sponsorship status summary ====================
$status_summary = [];
$total_commitment = 0.0;
if ($report_type === 'status_summary') {
    $status_summary = dbFetchAll("SELECT
        status,
        COUNT(*) as count,
        SUM(monthly_amount) as total_monthly_commitment
        FROM sponsorships
        GROUP BY status
        ORDER BY count DESC");
    $total_commitment = (float)(dbFetchOne("SELECT COALESCE(SUM(monthly_amount), 0) AS s FROM sponsorships WHERE status = 'active'")['s'] ?? 0);
}

$reports = [
    'coverage' => 'reports.coverage',
    'retention' => 'reports.retention',
    'distribution' => 'reports.distribution',
    'status_summary' => 'reports.status_summary',
];

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-hand-holding-heart me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted"><?php echo e(t('reports.subtitle', ['from' => $from, 'to' => $to])); ?></p>
</div>

<div class="card mb-4 fade-in shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <input type="hidden" name="report" value="<?php echo e($report_type); ?>">
            <div class="col-md-3">
                <label class="form-label fw-bold"><?php echo e(t('accounting.from')); ?></label>
                <input type="date" name="from" class="form-control" value="<?php echo e($from); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold"><?php echo e(t('accounting.to')); ?></label>
                <input type="date" name="to" class="form-control" value="<?php echo e($to); ?>" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i><?php echo e(t('reports.apply_filter')); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<ul class="nav nav-pills mb-4 fade-in flex-wrap gap-2">
    <?php foreach ($reports as $key => $labelKey): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $report_type === $key ? 'active' : ''; ?>"
               href="?report=<?php echo e($key); ?>&from=<?php echo e($from); ?>&to=<?php echo e($to); ?>">
                <?php echo e(t($labelKey)); ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="mb-3 text-end">
    <button onclick="exportToExcel()" class="btn btn-success me-2">
        <i class="fas fa-file-excel me-1"></i><?php echo e(t('reports.export_excel')); ?>
    </button>
    <button onclick="exportToPDF()" class="btn btn-danger">
        <i class="fas fa-file-pdf me-1"></i><?php echo e(t('reports.export_pdf')); ?>
    </button>
</div>

<div id="report-content" class="fade-in">
<?php if ($report_type === 'coverage'): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold" style="color:#1b4d8f;">
            <i class="fas fa-users me-2"></i><?php echo e(t('reports.coverage_title')); ?>
        </div>
        <div class="card-body">
            <div class="row g-4 mb-4">
                <div class="col-md-3"><div class="card border-0 shadow-sm text-center bg-light"><div class="card-body">
                    <h6 class="text-muted"><?php echo e(t('reports.total_children')); ?></h6>
                    <div class="display-6 fw-bold text-primary"><?php echo number_format($coverage_data['total_children']); ?></div>
                </div></div></div>
                <div class="col-md-3"><div class="card border-0 shadow-sm text-center bg-light"><div class="card-body">
                    <h6 class="text-muted"><?php echo e(t('reports.sponsored_children')); ?></h6>
                    <div class="display-6 fw-bold text-success"><?php echo number_format($coverage_data['sponsored_children']); ?></div>
                    <small class="text-success fw-bold"><?php echo e(t('reports.coverage_percent', ['percent' => $coverage_data['coverage_percent']])); ?></small>
                </div></div></div>
                <div class="col-md-3"><div class="card border-0 shadow-sm text-center bg-light"><div class="card-body">
                    <h6 class="text-muted"><?php echo e(t('reports.unsponsored_children')); ?></h6>
                    <div class="display-6 fw-bold text-danger"><?php echo number_format($coverage_data['unsponsored_children']); ?></div>
                </div></div></div>
                <div class="col-md-3"><div class="card border-0 shadow-sm text-center bg-light"><div class="card-body">
                    <h6 class="text-muted"><?php echo e(t('reports.uncovered_families')); ?></h6>
                    <div class="display-6 fw-bold text-warning"><?php echo number_format($coverage_data['orphaned_families']); ?></div>
                    <small class="text-muted"><?php echo e(t('reports.of_families', ['count' => number_format($coverage_data['total_families'])])); ?></small>
                </div></div></div>
            </div>
            <div class="row justify-content-center"><div class="col-md-6 col-lg-5"><canvas id="coverageChart" style="max-height:300px;"></canvas></div></div>
        </div>
    </div>

<?php elseif ($report_type === 'retention'): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-chart-pie me-2"></i><?php echo e(t('reports.retention')); ?></div>
        <div class="card-body">
            <div class="row g-4 mb-4">
                <div class="col-md-3"><div class="card border-0 shadow-sm text-center bg-light"><div class="card-body">
                    <h6 class="text-muted"><?php echo e(t('reports.total_sponsors')); ?></h6><div class="display-6 fw-bold text-primary"><?php echo number_format($retention_data['total']); ?></div>
                </div></div></div>
                <div class="col-md-3"><div class="card border-0 shadow-sm text-center bg-light"><div class="card-body">
                    <h6 class="text-muted"><?php echo e(t('reports.active_sponsors')); ?></h6><div class="display-6 fw-bold text-success"><?php echo number_format($retention_data['active']); ?></div>
                    <small class="text-success fw-bold"><?php echo e(t('reports.retention_percent', ['percent' => $retention_data['retention_rate']])); ?></small>
                </div></div></div>
                <div class="col-md-3"><div class="card border-0 shadow-sm text-center bg-light"><div class="card-body">
                    <h6 class="text-muted"><?php echo e(t('reports.cancelled_sponsors')); ?></h6><div class="display-6 fw-bold text-danger"><?php echo number_format($retention_data['cancelled']); ?></div>
                </div></div></div>
                <div class="col-md-3"><div class="card border-0 shadow-sm text-center bg-light"><div class="card-body">
                    <h6 class="text-muted"><?php echo e(t('reports.new_sponsors_period')); ?></h6><div class="display-6 fw-bold text-info"><?php echo number_format($retention_data['new_in_period']); ?></div>
                </div></div></div>
            </div>
            <div class="row justify-content-center"><div class="col-md-6 col-lg-5"><canvas id="retentionChart" style="max-height:300px;"></canvas></div></div>
        </div>
    </div>

<?php elseif ($report_type === 'distribution'): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-envelope me-2"></i><?php echo e(t('reports.distribution_by_letter')); ?></div>
        <div class="card-body"><div class="table-responsive">
            <table class="table table-bordered table-hover" id="report-table-letters"><thead class="table-light"><tr>
                <th><?php echo e(t('reports.letter')); ?></th><th><?php echo e(t('families.code')); ?></th><th><?php echo e(t('reports.total_sponsors')); ?></th><th><?php echo e(t('reports.active_sponsors_short')); ?></th>
            </tr></thead><tbody>
            <?php foreach ($distribution_data['by_letter'] as $row): ?><tr>
                <td><?php echo e($row['letter_name']); ?></td><td><code><?php echo e($row['letter_code']); ?></code></td>
                <td><?php echo number_format($row['sponsor_count']); ?></td><td class="text-success fw-bold"><?php echo number_format($row['active_count']); ?></td>
            </tr><?php endforeach; ?>
            </tbody></table>
        </div></div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-user-tie me-2"></i><?php echo e(t('reports.distribution_by_supervisor')); ?></div>
        <div class="card-body"><div class="table-responsive">
            <table class="table table-bordered table-hover" id="report-table-supervisors"><thead class="table-light"><tr>
                <th><?php echo e(t('reports.supervisor')); ?></th><th><?php echo e(t('reports.total_sponsors')); ?></th><th><?php echo e(t('reports.active_sponsors_short')); ?></th>
            </tr></thead><tbody>
            <?php foreach ($distribution_data['by_supervisor'] as $row): ?><tr>
                <td><?php echo e($row['supervisor_name']); ?></td><td><?php echo number_format($row['sponsor_count']); ?></td><td class="text-success fw-bold"><?php echo number_format($row['active_count']); ?></td>
            </tr><?php endforeach; ?>
            </tbody></table>
        </div></div>
    </div>

<?php elseif ($report_type === 'status_summary'): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold" style="color:#1b4d8f;"><i class="fas fa-list-alt me-2"></i><?php echo e(t('reports.status_summary')); ?></div>
        <div class="card-body">
            <div class="alert alert-info"><strong><?php echo e(t('reports.monthly_active_commitment')); ?></strong>
                <span class="fw-bold fs-5"><?php echo number_format($total_commitment, 2); ?> <?php echo e(t('accounting.currency_sdg')); ?></span>
            </div>
            <div class="table-responsive"><table class="table table-bordered table-hover" id="report-table-status">
                <thead class="table-light"><tr><th><?php echo e(t('reports.status')); ?></th><th><?php echo e(t('reports.count')); ?></th><th><?php echo e(t('reports.monthly_commitment_total')); ?></th><th><?php echo e(t('reports.percentage')); ?></th></tr></thead>
                <tbody>
                <?php
                $total_sponsorships = array_sum(array_column($status_summary, 'count'));
                $status_labels = [
                    'active' => ['reports.status_active', 'success'],
                    'paused' => ['reports.status_paused', 'warning'],
                    'completed' => ['reports.status_completed', 'info'],
                    'cancelled' => ['reports.status_cancelled', 'danger'],
                ];
                foreach ($status_summary as $row):
                    [$labelKey, $color] = $status_labels[$row['status']] ?? [null, 'secondary'];
                    $label = $labelKey ? t($labelKey) : (string)$row['status'];
                    $percent = $total_sponsorships > 0 ? round(($row['count'] / $total_sponsorships) * 100, 1) : 0;
                ?>
                    <tr>
                        <td><span class="badge bg-<?php echo e($color); ?>"><?php echo e($label); ?></span></td>
                        <td class="fw-bold"><?php echo number_format($row['count']); ?></td>
                        <td><?php echo number_format($row['total_monthly_commitment'] ?? 0, 2); ?> <?php echo e(t('accounting.currency_sdg')); ?></td>
                        <td><div class="progress" style="height:20px;"><div class="progress-bar bg-<?php echo e($color); ?>" style="width:<?php echo $percent; ?>%"><?php echo $percent; ?>%</div></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
<?php endif; ?>
</div>

<div class="mt-4 text-center">
    <a href="<?php echo APP_URL; ?>modules/reports/index.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i><?php echo e(t('reports.back_to_center')); ?>
    </a>
</div>

<script>
<?php if ($report_type === 'coverage'): ?>
const covCtx = document.getElementById('coverageChart').getContext('2d');
new Chart(covCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo json_encode(t('reports.sponsored'), JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode(t('reports.unsponsored'), JSON_UNESCAPED_UNICODE); ?>],
        datasets: [{ data: [<?php echo (int)$coverage_data['sponsored_children']; ?>, <?php echo (int)$coverage_data['unsponsored_children']; ?>], backgroundColor: ['rgba(40,167,69,.8)', 'rgba(220,53,69,.8)'], borderWidth: 2 }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: {
        legend: { position: 'bottom', labels: { padding: 15, font: { size: 12 } } },
        title: { display: true, text: <?php echo json_encode(t('reports.coverage_chart'), JSON_UNESCAPED_UNICODE); ?>, font: { size: 14 } }
    }, cutout: '60%' }
});
<?php endif; ?>

<?php if ($report_type === 'retention'): ?>
const retCtx = document.getElementById('retentionChart').getContext('2d');
new Chart(retCtx, {
    type: 'pie',
    data: {
        labels: [<?php echo json_encode(t('reports.active'), JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode(t('reports.cancelled'), JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode(t('reports.completed'), JSON_UNESCAPED_UNICODE); ?>],
        datasets: [{ data: [<?php echo (int)$retention_data['active']; ?>, <?php echo (int)$retention_data['cancelled']; ?>, <?php echo (int)$retention_data['completed']; ?>], backgroundColor: ['rgba(40,167,69,.8)', 'rgba(220,53,69,.8)', 'rgba(23,162,184,.8)'], borderWidth: 2 }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: {
        legend: { position: 'bottom', labels: { padding: 15, font: { size: 12 } } },
        title: { display: true, text: <?php echo json_encode(t('reports.retention_chart'), JSON_UNESCAPED_UNICODE); ?>, font: { size: 14 } }
    } }
});
<?php endif; ?>

function exportToExcel() {
    const tables = document.querySelectorAll('#report-content table');
    if (tables.length === 0) {
        alert(<?php echo json_encode(t('reports.no_table_export'), JSON_UNESCAPED_UNICODE); ?>);
        return;
    }
    const wb = XLSX.utils.book_new();
    tables.forEach((table, index) => {
        const ws = XLSX.utils.table_to_sheet(table);
        XLSX.utils.book_append_sheet(wb, ws, `Sheet${index + 1}`);
    });
    XLSX.writeFile(wb, 'sponsorship_report_<?php echo date('Y-m-d'); ?>.xlsx');
}

function exportToPDF() {
    const element = document.getElementById('report-content');
    const opt = {
        margin: 0.5,
        filename: 'sponsorship_report_<?php echo date('Y-m-d'); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>


<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="<?php echo APP_URL; ?>modules/reports/index.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>