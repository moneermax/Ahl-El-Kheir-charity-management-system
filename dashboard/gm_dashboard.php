<?php
// dashboard/gm_dashboard.php - GM strategic dashboard (read-only) with charts + insights
// v2 — post-pivot safe (no sponsorships.family_id anywhere).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
Session::start();
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['general_manager', 'admin'], true)) {
header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = 'لوحة المدير العام';
$active = 'dashboard';
/* ================= KPIs (post-pivot safe) ================= */
$kpi = dbFetchOne("SELECT
(SELECT COUNT(*) FROM sponsors) sponsors,
(SELECT COUNT(*) FROM sponsors WHERE status='active') sponsors_active,
(SELECT COUNT(*) FROM families) families,
(SELECT COUNT(*) FROM families WHERE status='active') families_active,
(SELECT COUNT(*) FROM families WHERE status='pending') families_pending,
(SELECT COUNT(*) FROM family_children) children,
(SELECT COUNT(*) FROM sponsorships WHERE status='active') ships_active,
(SELECT COUNT(*) FROM sponsorships WHERE status='paused') ships_paused,
(SELECT COALESCE(SUM(monthly_amount),0) FROM sponsorships WHERE status='active') commitment,
(SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='posted'
AND DATE_FORMAT(transaction_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')) month_collected,
(SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='posted'
AND YEAR(transaction_date) = YEAR(CURDATE())) year_collected,
(SELECT COUNT(*) FROM families f
WHERE f.status IN ('active','pending')
AND NOT EXISTS (SELECT 1 FROM family_children fc
JOIN sponsorships sp ON sp.child_id = fc.id AND sp.status='active'
WHERE fc.family_id = f.id)) uncovered_families,
(SELECT COUNT(*) FROM letters l
WHERE NOT EXISTS (SELECT 1 FROM supervisor_letters sl WHERE sl.letter_id = l.id)) unassigned_letters");
/* ================= Chart data ================= */
$monthly = dbFetchAll("SELECT DATE_FORMAT(transaction_date,'%Y-%m') m, COALESCE(SUM(amount),0) total
FROM transactions WHERE status='posted'
AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY m ORDER BY m");
$famStatus = dbFetchAll("SELECT status, COUNT(*) c FROM families GROUP BY status ORDER BY c DESC");
$shipStatus = dbFetchAll("SELECT status, COUNT(*) c FROM sponsorships GROUP BY status ORDER BY c DESC");
$byLetter = dbFetchAll("SELECT l.code, COUNT(s.id) c FROM letters l
LEFT JOIN sponsors s ON s.first_letter_id = l.id
GROUP BY l.id, l.code ORDER BY c DESC LIMIT 10");
$byMethod = dbFetchAll("SELECT payment_method, COALESCE(SUM(amount),0) total
FROM transactions WHERE status='posted' GROUP BY payment_method ORDER BY total DESC");
$bySup = dbFetchAll("SELECT COALESCE(u.full_name,'غير معين') name, COALESCE(SUM(t.amount),0) total
FROM transactions t
LEFT JOIN sponsors s ON s.id = t.sponsor_id
LEFT JOIN users u ON u.id = s.supervisor_id
WHERE t.status='posted' GROUP BY u.id, name ORDER BY total DESC LIMIT 8");
$medStats = dbFetchOne("SELECT COALESCE(SUM(has_medical_needs=1),0) with_needs,
COALESCE(SUM(has_medical_needs=0),0) without FROM family_children");
$topNeeds = dbFetchAll("SELECT COALESCE(mn.name, fc.medical_need, 'أخرى') name, COUNT(*) c
FROM family_children fc LEFT JOIN medical_needs mn ON mn.id = fc.medical_need_id
WHERE fc.has_medical_needs=1 GROUP BY name ORDER BY c DESC LIMIT 6");
/* ================= Strategic insights ================= */
$insights = [];
$totalFam = (int)$kpi['families'];
if ($totalFam > 0) {
$cov = round((($totalFam - (int)$kpi['uncovered_families']) / $totalFam) * 100);
$insights[] = 'نسبة تغطية الأسر بكفالات نشطة <strong>' . $cov . '%</strong>' .
($cov < 70 ? ' — فجوة تغطية تحتاج خطة كفالات عاجلة.' : ' — نسبة جيدة، يُستهدف رفعها فوق 85%.');
}
if ((int)$kpi['ships_paused'] > 0) $insights[] = '<strong>' . (int)$kpi['ships_paused'] . ' كفالة متوقفة</strong> تحتاج مراجعة أسباب الإيقاف واتخاذ قرار (استئناف/إنهاء).';
if ((int)$kpi['families_pending'] > 0) $insights[] = '<strong>' . (int)$kpi['families_pending'] . ' أسرة بحالة قيد الانتظار</strong> تحتاج دراسة حالة وتفعيل.';
if ((int)$medStats['with_needs'] > 0) $insights[] = '<strong>' . (int)$medStats['with_needs'] . ' طفل باحتياجات طبية</strong> — يمكن إطلاق مشروع علاجي مخصص.';
if ((int)$kpi['unassigned_letters'] > 0) $insights[] = '<strong>' . (int)$kpi['unassigned_letters'] . ' حرف بدون مشرف</strong> — فجوة تشغيلية في توزيع الحروف.';
if (!$insights) $insights[] = 'لا توجد تنبيهات حالية — المؤشرات ضمن النطاق المقبول.';
include __DIR__ . '/../includes/header.php';
?>
<style>
.gm-stat{border-right:4px solid #1b4d8f;border-radius:12px;box-shadow:0 2px 10px rgba(10,31,68,.08)}
.gm-stat .v{font-size:1.6rem;font-weight:800;color:#1b4d8f}
.chart-box{height:280px;position:relative}
</style>
<div class="welcome-section fade-in">
<h2>مرحباً، <?php echo e(Session::getUserName()); ?></h2>
<p>نظرة استراتيجية شاملة لدعم اتخاذ القرار</p>
<div class="quick-actions mt-3">
<a href="<?php echo APP_URL; ?>modules/reports/index.php" class="btn btn-primary btn-sm"><i class="fas fa-chart-line me-1"></i>مركز التقارير</a>
</div>
</div>
<?php include __DIR__ . '/../includes/alerts.php'; ?>
<!-- KPI cards -->
<div class="row g-3 mb-4 fade-in">
<?php foreach ([
[(int)$kpi['sponsors'], 'إجمالي الكفلاء', (int)$kpi['sponsors_active'] . ' نشط'],
[(int)$kpi['families'], 'الأسر', (int)$kpi['families_active'] . ' نشطة'],
[(int)$kpi['children'], 'الأطفال', (int)$medStats['with_needs'] . ' باحتياجات طبية'],
[(int)$kpi['ships_active'], 'كفالات نشطة', number_format((float)$kpi['commitment'], 0) . ' ج.س/شهر'],
[number_format((float)$kpi['month_collected'], 0), 'تحصيل هذا الشهر', 'ج.س'],
[number_format((float)$kpi['year_collected'], 0), 'تحصيل السنة', 'ج.س'],
[(int)$kpi['uncovered_families'], 'أسر بلا كفالة', 'فرصة للتغطية'],
[(int)$kpi['unassigned_letters'], 'حروف بلا مشرف', 'فجوة تشغيلية'],
] as $c): ?>
<div class="col-6 col-md-3 col-xl-3">
<div class="card gm-stat text-center">
<div class="card-body py-2">
<div class="v"><?php echo $c[0]; ?></div>
<div class="text-muted small"><?php echo $c[1]; ?></div>
<div class="text-muted" style="font-size:.7rem"><?php echo $c[2]; ?></div>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<!-- Strategic insights -->
<div class="card mb-4 fade-in" style="border-right:4px solid #f2b63c">
<div class="card-header" style="background:#fff8e6">
<i class="fas fa-lightbulb me-2" style="color:#8a6d1a"></i><strong style="color:#8a6d1a">رؤى استراتيجية وتوصيات</strong>
</div>
<div class="card-body">
<ul class="mb-0"><?php foreach ($insights as $i): ?><li class="mb-1"><?php echo $i; ?></li><?php endforeach; ?></ul>
</div>
</div>
<!-- Charts -->
<div class="row g-4 fade-in">
<div class="col-lg-8">
<div class="card"><div class="card-header"><i class="fas fa-chart-column me-2"></i>اتجاه التحصيل الشهري (آخر 12 شهراً)</div>
<div class="card-body"><div class="chart-box"><canvas id="chMonthly"></canvas></div></div></div>
</div>
<div class="col-lg-4">
<div class="card"><div class="card-header"><i class="fas fa-chart-pie me-2"></i>الأسر حسب الحالة</div>
<div class="card-body"><div class="chart-box"><canvas id="chFam"></canvas></div></div></div>
</div>
<div class="col-lg-4">
<div class="card"><div class="card-header"><i class="fas fa-chart-pie me-2"></i>الكفالات حسب الحالة</div>
<div class="card-body"><div class="chart-box"><canvas id="chShip"></canvas></div></div></div>
</div>
<div class="col-lg-4">
<div class="card"><div class="card-header"><i class="fas fa-chart-column me-2"></i>الكفلاء حسب الحرف (أعلى 10)</div>
<div class="card-body"><div class="chart-box"><canvas id="chLetter"></canvas></div></div></div>
</div>
<div class="col-lg-4">
<div class="card"><div class="card-header"><i class="fas fa-chart-pie me-2"></i>التحصيل حسب طريقة الدفع</div>
<div class="card-body"><div class="chart-box"><canvas id="chMethod"></canvas></div></div></div>
</div>
<div class="col-lg-6">
<div class="card"><div class="card-header"><i class="fas fa-chart-column me-2"></i>أعلى المشرفين تحصيلاً</div>
<div class="card-body"><div class="chart-box"><canvas id="chSup"></canvas></div></div></div>
</div>
<div class="col-lg-6">
<div class="card"><div class="card-header"><i class="fas fa-chart-pie me-2"></i>الاحتياجات الطبية</div>
<div class="card-body"><div class="chart-box"><canvas id="chMed"></canvas></div></div></div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
function bar(id, labels, data, horizontal) {
var el = document.getElementById(id);
if (!el || !window.Chart) return;
new Chart(el, {
type: 'bar',
data: { labels: labels, datasets: [{ data: data, backgroundColor: '#1b4d8f', borderRadius: 4 }] },
options: {
indexAxis: horizontal ? 'y' : 'x',
responsive: true, maintainAspectRatio: false,
plugins: { legend: { display: false } },
scales: { y: { ticks: { color: '#6b7280' } }, x: { ticks: { color: '#6b7280' } } }
}
});
}
function pie(id, labels, data) {
var el = document.getElementById(id);
if (!el || !window.Chart) return;
new Chart(el, {
type: 'doughnut',
data: { labels: labels, datasets: [{ data: data, backgroundColor: ['#1b4d8f','#2e7cd6','#f2b63c','#dc6b6b','#57b26a','#8f6bd6','#6bc5d6','#b8bcc4'] }] },
options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#6b7280' } } } }
});
}
bar('chMonthly', <?php echo json_encode(array_column($monthly, 'm')); ?>, <?php echo json_encode(array_map('floatval', array_column($monthly, 'total'))); ?>);
pie('chFam', <?php echo json_encode(array_column($famStatus, 'status'), JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode(array_map('intval', array_column($famStatus, 'c'))); ?>);
pie('chShip', <?php echo json_encode(array_column($shipStatus, 'status'), JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode(array_map('intval', array_column($shipStatus, 'c'))); ?>);
bar('chLetter', <?php echo json_encode(array_column($byLetter, 'code'), JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode(array_map('intval', array_column($byLetter, 'c'))); ?>);
pie('chMethod', <?php echo json_encode(array_column($byMethod, 'payment_method'), JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode(array_map('floatval', array_column($byMethod, 'total'))); ?>);
bar('chSup', <?php echo json_encode(array_column($bySup, 'name'), JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode(array_map('floatval', array_column($bySup, 'total'))); ?>, true);
pie('chMed', <?php echo json_encode(array_merge(['بدون احتياجات','باحتياجات طبية'], array_column($topNeeds,'name')), JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode(array_merge([(int)$medStats['without'], (int)$medStats['with_needs']], array_map('intval', array_column($topNeeds,'c')))); ?>);
</script>

<!-- ADDED: Load the Age Alert Popup Widget for GM Dashboard -->
<?php include __DIR__ . '/../includes/age_alert.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>