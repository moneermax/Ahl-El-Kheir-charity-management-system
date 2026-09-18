<?php
// dashboard/gm_dashboard.php - GM executive dashboard
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['general_manager', 'admin'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'لوحة المدير العام';
$active = 'dashboard';

/*
 * Executive definitions:
 * - Financial figures below are posted transaction figures only.
 * - Sponsorship coverage is measured against active/pending families.
 * - Operational details remain drill-down reports rather than dashboard clutter.
 */
$kpi = dbFetchOne("SELECT
    (SELECT COUNT(*) FROM sponsors WHERE status='active') active_sponsors,
    (SELECT COUNT(*) FROM families WHERE status='active') active_families,
    (SELECT COUNT(*) FROM families WHERE status='pending') pending_families,
    (SELECT COUNT(*) FROM family_children) children,
    (SELECT COUNT(*) FROM sponsorships WHERE status='active') active_sponsorships,
    (SELECT COUNT(*) FROM sponsorships WHERE status='paused') paused_sponsorships,
    (SELECT COALESCE(SUM(monthly_amount),0) FROM sponsorships WHERE status='active') monthly_commitment,
    (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='posted' AND DATE_FORMAT(transaction_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')) month_posted,
    (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='posted' AND YEAR(transaction_date)=YEAR(CURDATE())) year_posted,
    (SELECT COUNT(*) FROM families f WHERE f.status IN ('active','pending') AND NOT EXISTS (
        SELECT 1 FROM family_children fc
        JOIN sponsorships sp ON sp.child_id=fc.id AND sp.status='active'
        WHERE fc.family_id=f.id
    )) uncovered_families,
    (SELECT COUNT(*) FROM letters l WHERE NOT EXISTS (SELECT 1 FROM supervisor_letters sl WHERE sl.letter_id=l.id)) unassigned_letters,
    (SELECT COUNT(*) FROM winback_campaigns WHERE status IN ('open','contacted')) open_winback,
    (SELECT COUNT(*) FROM sponsor_requests WHERE status IN ('new','contacted')) open_sponsor_requests
");

$monthly = dbFetchAll("SELECT DATE_FORMAT(transaction_date,'%Y-%m') m,
    COALESCE(SUM(amount),0) total
    FROM transactions
    WHERE status='posted' AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY m ORDER BY m");

$famStatus = dbFetchAll("SELECT status, COUNT(*) c FROM families GROUP BY status ORDER BY c DESC");
$shipStatus = dbFetchAll("SELECT status, COUNT(*) c FROM sponsorships GROUP BY status ORDER BY c DESC");
$byMethod = dbFetchAll("SELECT payment_method, COALESCE(SUM(amount),0) total
    FROM transactions WHERE status='posted' GROUP BY payment_method ORDER BY total DESC");

$attention = [];
if ((int)$kpi['uncovered_families'] > 0) {
    $attention[] = ['danger','fa-house-circle-exclamation','أسر بلا كفالة نشطة',(int)$kpi['uncovered_families'],'modules/reports/orphaned_families.php','مراجعة فرص التغطية'];
}
if ((int)$kpi['paused_sponsorships'] > 0) {
    $attention[] = ['warning','fa-pause-circle','كفالات متوقفة',(int)$kpi['paused_sponsorships'],'modules/reports/sponsorship.php','مراجعة استمرارية الكفالات'];
}
if ((int)$kpi['pending_families'] > 0) {
    $attention[] = ['info','fa-hourglass-half','أسر بانتظار الإجراء',(int)$kpi['pending_families'],'modules/reports/operational.php','مراجعة الوضع التشغيلي'];
}
if ((int)$kpi['open_winback'] > 0) {
    $attention[] = ['warning','fa-rotate-left','متابعات استعادة الكفلاء',(int)$kpi['open_winback'],'modules/administration/winback.php','مراجعة المتابعات المفتوحة'];
}
if ((int)$kpi['unassigned_letters'] > 0) {
    $attention[] = ['secondary','fa-font','حروف غير مسندة للمشرفين',(int)$kpi['unassigned_letters'],'modules/reports/operational.php','مراجعة التوزيع التشغيلي'];
}
if ((int)$kpi['open_sponsor_requests'] > 0) {
    $attention[] = ['primary','fa-bullhorn','طلبات رعاية قيد المتابعة',(int)$kpi['open_sponsor_requests'],'modules/sponsors/requests.php','مراجعة مسار الطلبات'];
}

$coveragePopulation = (int)$kpi['active_families'] + (int)$kpi['pending_families'];
$coveragePct = $coveragePopulation > 0
    ? round(max(0, (($coveragePopulation - (int)$kpi['uncovered_families']) / $coveragePopulation) * 100))
    : 0;

$quickReports = [
    ['financial','fa-coins','التقارير المالية','الإيرادات والمصروفات والخزينة والدفعات والإرجاعات'],
    ['sponsorship','fa-hand-holding-heart','تقارير الكفالات والرعاة','التغطية والاستمرارية وحالة الكفالات'],
    ['operational','fa-tasks','التقارير التشغيلية','المشرفون والحاضنات والمتابعة التشغيلية'],
    ['hr','fa-users-cog','تقارير الموارد البشرية','القوى العاملة والحضور والإجازات والرواتب'],
    ['reconciliation','fa-scale-balanced','المصالحة المالية','رؤية إدارية للحركة والدفعات والإرجاعات والإبطالات'],
];

include __DIR__ . '/../includes/header.php';
?>
<style>
.gm-hero{background:linear-gradient(135deg,#173f73,#1b4d8f);color:#fff;border-radius:16px;padding:1.35rem 1.5rem;box-shadow:0 6px 22px rgba(10,31,68,.14)}
.gm-hero .sub{opacity:.86;font-size:.9rem}
.gm-kpi{border:0;border-radius:14px;box-shadow:0 3px 14px rgba(10,31,68,.08);height:100%}
.gm-kpi .value{font-size:1.65rem;font-weight:800;color:#173f73}
.gm-kpi .label{font-size:.82rem;font-weight:700;color:#667085}
.gm-kpi .meta{font-size:.72rem;color:#98a2b3}
.gm-section{border:0;border-radius:14px;box-shadow:0 3px 14px rgba(10,31,68,.07)}
.gm-section .card-header{background:#fff;border-bottom:1px solid #eef1f5;font-weight:800;color:#173f73}
.gm-attention{border-radius:12px;border:1px solid #eef1f5;text-decoration:none;color:inherit;display:flex;align-items:center;gap:.8rem;padding:.8rem;transition:.18s}
.gm-attention:hover{transform:translateY(-2px);box-shadow:0 5px 16px rgba(10,31,68,.10);color:inherit}
.gm-attention .ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#f4f6f8}
.gm-report{border:1px solid #e7ebf0;border-radius:12px;text-decoration:none;color:inherit;display:block;height:100%;padding:1rem;transition:.18s}
.gm-report:hover{border-color:#1b4d8f;box-shadow:0 5px 16px rgba(27,77,143,.10);transform:translateY(-2px);color:inherit}
.gm-report i{font-size:1.25rem;color:#1b4d8f}
.chart-box{height:260px;position:relative}
@media(max-width:576px){.gm-hero{padding:1rem}.gm-kpi .value{font-size:1.35rem}}
</style>

<div class="gm-hero fade-in mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="small mb-1"><i class="fas fa-compass me-1"></i>الإدارة التنفيذية</div>
            <h2 class="mb-1">مرحباً، <?php echo e(Session::getUserName()); ?></h2>
            <div class="sub">صورة تنفيذية مختصرة لما يحتاج انتباهك الآن، مع إمكانية التعمق في التقارير عند الحاجة.</div>
        </div>
        <a href="<?php echo APP_URL; ?>modules/reports/index.php" class="btn btn-light">
            <i class="fas fa-chart-line me-1"></i>مركز التقارير
        </a>
    </div>
</div>

<?php include __DIR__ . '/../includes/alerts.php'; ?>

<div class="row g-3 mb-4 fade-in">
<?php
$cards = [
    [(int)$kpi['active_sponsors'],'الكفلاء النشطون','حالياً','fa-hand-holding-heart'],
    [(int)$kpi['active_families'],'الأسر النشطة','مسجلة وفعالة','fa-house-chimney'],
    [(int)$kpi['active_sponsorships'],'الكفالات النشطة',number_format((float)$kpi['monthly_commitment'],0).' شهرياً','fa-file-contract'],
    [number_format((float)$kpi['month_posted'],0),'التحصيل المرحّل هذا الشهر','إجمالي المعاملات المرحّلة','fa-coins'],
    [$coveragePct.'%','نسبة تغطية الأسر','من الأسر النشطة والمعلقة','fa-chart-pie'],
    [(int)$kpi['uncovered_families'],'أسر بلا كفالة نشطة','فرص تغطية','fa-house-circle-exclamation'],
];
foreach($cards as $c):
?>
<div class="col-6 col-xl-2 col-lg-4">
    <div class="card gm-kpi text-center">
        <div class="card-body py-3">
            <div class="mb-2"><i class="fas <?php echo e($c[3]); ?> text-primary"></i></div>
            <div class="value"><?php echo e((string)$c[0]); ?></div>
            <div class="label"><?php echo e($c[1]); ?></div>
            <div class="meta mt-1"><?php echo e($c[2]); ?></div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<div class="row g-4 mb-4 fade-in">
    <div class="col-xl-7">
        <div class="card gm-section h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-triangle-exclamation me-2"></i>ما يحتاج انتباه الإدارة</span>
                <span class="badge bg-light text-dark border"><?php echo count($attention); ?> عناصر</span>
            </div>
            <div class="card-body">
                <?php if (!$attention): ?>
                    <div class="text-center text-success py-4"><i class="fas fa-circle-check fa-2x mb-2"></i><div class="fw-bold">لا توجد مؤشرات تتطلب تدخلاً حالياً</div></div>
                <?php else: ?>
                    <div class="row g-2">
                    <?php foreach($attention as $a): ?>
                        <div class="col-md-6">
                            <a class="gm-attention" href="<?php echo APP_URL.$a[4]; ?>">
                                <span class="ico"><i class="fas <?php echo e($a[1]); ?> text-<?php echo e($a[0]); ?>"></i></span>
                                <span class="flex-grow-1"><strong class="d-block"><?php echo e($a[2]); ?></strong><small class="text-muted"><?php echo e($a[5]); ?></small></span>
                                <span class="badge text-bg-<?php echo e($a[0]); ?>"><?php echo (int)$a[3]; ?></span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card gm-section h-100">
            <div class="card-header"><i class="fas fa-bullseye me-2"></i>ملخص الإدارة</div>
            <div class="card-body">
                <div class="d-flex justify-content-between border-bottom py-2"><span>الأسر بانتظار الإجراء</span><strong><?php echo (int)$kpi['pending_families']; ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>الكفالات المتوقفة</span><strong><?php echo (int)$kpi['paused_sponsorships']; ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>متابعات استعادة الكفلاء</span><strong><?php echo (int)$kpi['open_winback']; ?></strong></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span>طلبات الرعاية المفتوحة</span><strong><?php echo (int)$kpi['open_sponsor_requests']; ?></strong></div>
                <div class="d-flex justify-content-between py-2"><span>حروف غير مسندة</span><strong><?php echo (int)$kpi['unassigned_letters']; ?></strong></div>
                <div class="alert alert-light border mt-3 mb-0 small"><i class="fas fa-circle-info me-1 text-primary"></i>الأرقام التنفيذية هنا للتوجيه السريع؛ التفاصيل والإجراءات المتخصصة تبقى داخل التقارير والوحدات المختصة.</div>
            </div>
        </div>
    </div>
</div>

<div class="card gm-section mb-4 fade-in">
    <div class="card-header"><i class="fas fa-chart-line me-2"></i>التحصيل المرحّل — آخر 12 شهراً</div>
    <div class="card-body"><div class="chart-box"><canvas id="chMonthly"></canvas></div></div>
</div>

<div class="row g-4 mb-4 fade-in">
    <div class="col-lg-6">
        <div class="card gm-section h-100"><div class="card-header"><i class="fas fa-chart-pie me-2"></i>حالة الأسر</div><div class="card-body"><div class="chart-box"><canvas id="chFam"></canvas></div></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card gm-section h-100"><div class="card-header"><i class="fas fa-chart-pie me-2"></i>حالة الكفالات</div><div class="card-body"><div class="chart-box"><canvas id="chShip"></canvas></div></div></div>
    </div>
</div>

<div class="card gm-section mb-4 fade-in">
    <div class="card-header"><i class="fas fa-folder-open me-2"></i>الوصول التنفيذي السريع</div>
    <div class="card-body">
        <div class="row g-3">
        <?php foreach($quickReports as $r): ?>
            <?php if (function_exists('ak_report_can_access') && !ak_report_can_access($r[0], Session::getUserRole())) continue; ?>
            <div class="col-md-6 col-xl-4">
                <a class="gm-report" href="<?php echo APP_URL; ?>modules/reports/<?php
                    echo $r[0] === 'financial' ? 'financial.php' :
                        ($r[0] === 'sponsorship' ? 'sponsorship.php' :
                        ($r[0] === 'operational' ? 'operational.php' :
                        ($r[0] === 'hr' ? 'hr.php' : '../accounting/gm_reconciliation.php')));
                ?>">
                    <i class="fas <?php echo e($r[1]); ?> me-2"></i><strong><?php echo e($r[2]); ?></strong>
                    <div class="small text-muted mt-2"><?php echo e($r[3]); ?></div>
                </a>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
function akBar(id,labels,data){const el=document.getElementById(id);if(!el||!window.Chart)return;new Chart(el,{type:'bar',data:{labels:labels,datasets:[{data:data,backgroundColor:'#1b4d8f',borderRadius:5}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true},x:{ticks:{color:'#667085'}}}}});}
function akPie(id,labels,data){const el=document.getElementById(id);if(!el||!window.Chart)return;new Chart(el,{type:'doughnut',data:{labels:labels,datasets:[{data:data,backgroundColor:['#1b4d8f','#2e7cd6','#f2b63c','#dc6b6b','#57b26a','#8f6bd6','#6bc5d6','#b8bcc4']}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});}
akBar('chMonthly',<?php echo json_encode(array_column($monthly,'m')); ?>,<?php echo json_encode(array_map('floatval',array_column($monthly,'total'))); ?>);
akPie('chFam',<?php echo json_encode(array_column($famStatus,'status'),JSON_UNESCAPED_UNICODE); ?>,<?php echo json_encode(array_map('intval',array_column($famStatus,'c'))); ?>);
akPie('chShip',<?php echo json_encode(array_column($shipStatus,'status'),JSON_UNESCAPED_UNICODE); ?>,<?php echo json_encode(array_map('intval',array_column($shipStatus,'c'))); ?>);
</script>

<?php include __DIR__ . '/../includes/age_alert.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>