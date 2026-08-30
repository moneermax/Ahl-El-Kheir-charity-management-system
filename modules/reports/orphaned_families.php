<?php
// modules/reports/orphaned_families.php - Orphaned Families Report
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$role = Session::getUserRole();
$uid = Session::getUserId();

// التحقق من الصلاحية
$allowed_roles = ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny'];
if (!in_array($role, $allowed_roles, true)) {
    $_SESSION['flash'][] = ['type' => 'error', 'message' => 'ليس لديك صلاحية الوصول لهذه الصفحة'];
    header('Location: ' . APP_URL . 'modules/reports/index.php');
    exit();
}

// معالجة الفلاتر
$city_filter = trim($_GET['city'] ?? '');
$status_filter = trim($_GET['status'] ?? 'active');
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));

$pageTitle = 'الأسر بلا كفالة';
$active = 'reports';

// بناء استعلام الأسر غير المكفولة
// نستخدم استعلاماً مباشراً وآمناً لضمان العمل حتى لو لم يتم إنشاء الـ View بعد
$sql = "SELECT 
    f.id,
    f.family_code,
    f.mother_name,
    f.city,
    f.children_count,
    f.monthly_need_amount,
    f.status,
    (SELECT COUNT(*) FROM family_children fc WHERE fc.family_id = f.id AND fc.is_active = 1) as active_children
    FROM families f
    WHERE f.status IN ('active', 'pending') 
      AND f.children_count > 0
      AND NOT EXISTS (
          SELECT 1 FROM sponsorships sp
          JOIN family_children fc ON sp.child_id = fc.id
          WHERE fc.family_id = f.id
            AND sp.status = 'active'
      )";

// إضافة فلاتر اختيارية
$params = [];
if (!empty($city_filter)) {
    $sql .= " AND f.city = ?";
    $params[] = $city_filter;
}
if (!empty($status_filter) && $status_filter !== 'all') {
    $sql .= " AND f.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY f.city ASC, f.mother_name ASC";

$families = dbFetchAll($sql, $params);

// جلب قائمة المدن المتاحة للفلترة
$cities_sql = "SELECT DISTINCT city FROM families WHERE city IS NOT NULL AND city != '' ORDER BY city";
$cities = array_column(dbFetchAll($cities_sql), 'city');

// إحصائية سريعة
$total_orphaned = count($families);
$total_children_waiting = array_sum(array_column($families, 'active_children'));

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-users-slash me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted">قائمة الأسر والأطفال الذين ينتظرون ربطهم بكفلاء</p>
</div>

<!-- نموذج الفلتر -->
<div class="card mb-4 fade-in shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">المدينة</label>
                <select name="city" class="form-select">
                    <option value="">جميع المدن</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?php echo e($c); ?>" <?php echo $city_filter === $c ? 'selected' : ''; ?>>
                            <?php echo e($c); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">حالة الأسرة</label>
                <select name="status" class="form-select">
                    <option value="all">الكل</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>نشطة</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100" style="background-color: #1b4d8f; border-color: #1b4d8f;">
                    <i class="fas fa-filter me-1"></i> تطبيق
                </button>
            </div>
        </form>
    </div>
</div>

<!-- بطاقات الإحصائيات -->
<div class="row g-4 mb-4 fade-in">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm text-center bg-light h-100" style="border-right: 4px solid #dc3545 !important;">
            <div class="card-body">
                <h6 class="text-muted">إجمالي الأسر بانتظار الكفالة</h6>
                <div class="display-6 fw-bold text-danger"><?php echo number_format($total_orphaned); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm text-center bg-light h-100" style="border-right: 4px solid #fd7e14 !important;">
            <div class="card-body">
                <h6 class="text-muted">إجمالي الأطفال بانتظار الكفالة</h6>
                <div class="display-6 fw-bold text-warning"><?php echo number_format($total_children_waiting); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- أزرار التصدير -->
<div class="mb-3 text-end fade-in">
    <button onclick="exportToExcel()" class="btn btn-success me-2">
        <i class="fas fa-file-excel me-1"></i> تصدير Excel
    </button>
    <button onclick="exportToPDF()" class="btn btn-danger">
        <i class="fas fa-file-pdf me-1"></i> تصدير PDF
    </button>
</div>

<!-- محتوى التقرير -->
<div id="report-content" class="fade-in">
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
            <i class="fas fa-list me-2"></i> تفاصيل الأسر غير المكفولة
        </div>
        <div class="card-body">
            <?php if (!empty($families)): ?>
                <div class="table-responsive">
                <table class="table table-bordered table-hover" id="report-table">
                    <thead class="table-light">
                        <tr>
                            <th>كود الأسرة</th>
                            <th>اسم الأم</th>
                            <th>المدينة</th>
                            <th>عدد الأطفال</th>
                            <th>الأطفال النشطين</th>
                            <th>الاحتياج الشهري</th>
                            <th>الحالة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($families as $row): 
                            $status_badge = $row['status'] === 'active' ? 'bg-success' : 'bg-warning text-dark';
                            $status_label = $row['status'] === 'active' ? 'نشطة' : 'قيد الانتظار';
                        ?>
                        <tr>
                            <td><code><?php echo e($row['family_code'] ?? '-'); ?></code></td>
                            <td class="fw-bold"><?php echo e($row['mother_name']); ?></td>
                            <td><?php echo e($row['city'] ?? 'غير محدد'); ?></td>
                            <td><?php echo number_format($row['children_count']); ?></td>
                            <td><span class="badge bg-info"><?php echo number_format($row['active_children']); ?></span></td>
                            <td class="text-primary fw-bold">
                                <?php echo $row['monthly_need_amount'] ? number_format($row['monthly_need_amount'], 2) . ' ج.س' : '-'; ?>
                            </td>
                            <td><span class="badge <?php echo $status_badge; ?>"><?php echo e($status_label); ?></span></td>
                            <td>
                                <a href="<?php echo APP_URL; ?>modules/families/view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="fas fa-eye me-1"></i> عرض
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success text-center">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <h5 class="mb-0">ممتاز! لا توجد أسر بانتظار الكفالة حالياً حسب الفلاتر المحددة.</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- زر العودة -->
<div class="mt-4 text-center fade-in">
    <a href="<?php echo APP_URL; ?>modules/reports/index.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-right me-1"></i> العودة إلى مركز التقارير
    </a>
</div>

<script>
// تصدير إلى Excel
function exportToExcel() {
    const table = document.querySelector('#report-content table');
    if (!table) {
        alert('لا يوجد بيانات لتصديرها');
        return;
    }
    const wb = XLSX.utils.table_to_book(table, {sheet: "Orphaned Families"});
    XLSX.writeFile(wb, 'orphaned_families_<?php echo date("Y-m-d"); ?>.xlsx');
}

// تصدير إلى PDF
function exportToPDF() {
    const element = document.getElementById('report-content');
    const opt = {
        margin: 0.5,
        filename: 'orphaned_families_<?php echo date("Y-m-d"); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>