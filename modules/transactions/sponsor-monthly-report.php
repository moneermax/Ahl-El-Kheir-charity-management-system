<?php
// modules/transactions/sponsor-monthly-report.php - Sponsor Monthly Report Generator UI
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
Session::start();

// Access guard
$allowedRoles = ['nanny', 'financial_manager', 'admin', 'general_manager', 'vice_general_manager'];
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), $allowedRoles, true)) {
    flash(['type' => 'error', 'message' => 'ليس لديك صلاحية الوصول لهذه الصفحة']);
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'تقرير الكفيل الشهري';
$active = 'sponsor_reports';

$uid = Session::getUserId();
$role = Session::getUserRole();
$selectedMonth = $_GET['month'] ?? '';

// FIXED: Get months from BOTH transactions AND monthly_disbursements
$months = dbFetchAll("
    SELECT DISTINCT month FROM (
        SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month
        FROM transactions
        WHERE status = 'posted' AND sponsor_id IS NOT NULL
        
        UNION
        
        SELECT month
        FROM monthly_disbursements
        WHERE status IN ('transferred', 'received')
        " . ($role === 'nanny' ? "AND nanny_id = " . (int)$uid : "") . "
    ) as combined_months
    ORDER BY month DESC
    LIMIT 12
");

// Get sponsors based on role and selected month
$sponsors = [];

if ($role === 'nanny') {
    if (!empty($selectedMonth)) {
        // FIXED: Get sponsors who have active sponsorships for families that received disbursements in this month
        $sponsors = dbFetchAll("
            SELECT DISTINCT s.id, s.full_name, s.sponsor_code, s.phone, s.email
            FROM sponsors s
            INNER JOIN sponsorships sp ON sp.sponsor_id = s.id
            INNER JOIN family_children fc ON fc.id = sp.child_id
            INNER JOIN families f ON f.id = fc.family_id
            INNER JOIN disbursement_items di ON di.family_id = f.id
            INNER JOIN monthly_disbursements md ON md.id = di.disbursement_id
            WHERE s.status = 'active' 
            AND sp.status = 'active'
            AND fc.is_active = 1
            AND md.month = ?
            AND md.nanny_id = ?
            AND md.status IN ('transferred', 'received')
            ORDER BY s.full_name
        ", [$selectedMonth, $uid]);
    } else {
        // Show all sponsors related to nanny
        $sponsors = dbFetchAll("
            SELECT DISTINCT s.id, s.full_name, s.sponsor_code, s.phone, s.email
            FROM sponsors s
            INNER JOIN sponsorships sp ON sp.sponsor_id = s.id
            INNER JOIN family_children fc ON fc.id = sp.child_id
            INNER JOIN families f ON f.id = fc.family_id
            WHERE s.status = 'active' AND sp.status = 'active' AND f.nanny_id = ?
            ORDER BY s.full_name
        ", [$uid]);
    }
} else {
    // Other roles
    if (!empty($selectedMonth)) {
        $sponsors = dbFetchAll("
            SELECT DISTINCT s.id, s.full_name, s.sponsor_code, s.phone, s.email
            FROM sponsors s
            INNER JOIN sponsorships sp ON sp.sponsor_id = s.id
            INNER JOIN family_children fc ON fc.id = sp.child_id
            INNER JOIN families f ON f.id = fc.family_id
            INNER JOIN disbursement_items di ON di.family_id = f.id
            INNER JOIN monthly_disbursements md ON md.id = di.disbursement_id
            WHERE s.status = 'active' 
            AND sp.status = 'active'
            AND fc.is_active = 1
            AND md.month = ?
            AND md.status IN ('transferred', 'received')
            ORDER BY s.full_name
        ", [$selectedMonth]);
    } else {
        $sponsors = dbFetchAll("
            SELECT id, full_name, sponsor_code, phone, email
            FROM sponsors
            WHERE status = 'active'
            ORDER BY full_name
        ");
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-file-pdf me-2"></i>تقرير الكفيل الشهري</h2>
    <p>إنشاء تقرير PDF شهري للكفيل (بدون بيانات الأيتام أو الأسر للحفاظ على الخصوصية)</p>
</div>

<?php include __DIR__ . '/../../includes/alerts.php'; ?>

<div class="card fade-in">
    <div class="card-header">
        <i class="fas fa-cog me-2"></i>بيانات التقرير
    </div>
    <div class="card-body">
        <form action="<?php echo APP_URL; ?>modules/transactions/sponsor-report-preview.php" method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-calendar me-1"></i>الشهر
                        <span class="text-danger">*</span>
                    </label>
                    <select name="report_month" id="report_month" class="form-select" required>
                        <option value="">-- اختر الشهر أولاً --</option>
                        <?php foreach ($months as $m): ?>
                        <option value="<?php echo e($m['month']); ?>" <?php echo ($selectedMonth === $m['month']) ? 'selected' : ''; ?>>
                            <?php 
                            $monthName = date('F', strtotime($m['month'] . '-01'));
                            $monthAr = [
                                'January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس',
                                'April' => 'أبريل', 'May' => 'مايو', 'June' => 'يونيو',
                                'July' => 'يوليو', 'August' => 'أغسطس', 'September' => 'سبتمبر',
                                'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر'
                            ];
                            echo $monthAr[$monthName] ?? $m['month'];
                            ?> <?php echo substr($m['month'], 0, 4); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">اختر الشهر لعرض الكفلاء الذين لديهم مدفوعات مسجلة</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-user me-1"></i>اختر الكفيل
                        <span class="text-danger">*</span>
                    </label>
                    <select name="sponsor_id" class="form-select" required <?php echo empty($selectedMonth) ? 'disabled' : ''; ?>>
                        <option value="">-- اختر الكفيل --</option>
                        <?php if (empty($selectedMonth)): ?>
                        <option value="" disabled>اختر الشهر أولاً لعرض الكفلاء</option>
                        <?php else: ?>
                        <?php foreach ($sponsors as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>">
                            <?php echo e($s['full_name']); ?> (<?php echo e($s['sponsor_code']); ?>)
                        </option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (!empty($selectedMonth) && empty($sponsors)): ?>
                    <div class="alert alert-warning mt-2 mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        لا يوجد كفلاء لديهم مدفوعات مسجلة في هذا الشهر
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>ملاحظة مهمة:</strong> 
                        التقرير لن يحتوي على أسماء الأيتام أو الأسر للحفاظ على الخصوصية. 
                        سيتم استخدام أرقام الأيتام (Orphan IDs) فقط.
                    </div>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-lg" <?php echo (empty($selectedMonth) || empty($sponsors)) ? 'disabled' : ''; ?>>
                        <i class="fas fa-file-pdf me-2"></i>إنشاء التقرير (PDF)
                    </button>
                    <a href="<?php echo APP_URL; ?>modules/transactions/index.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>إلغاء
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('report_month').addEventListener('change', function() {
    const month = this.value;
    if (month) {
        window.location.href = '?month=' + month;
    } else {
        window.location.href = window.location.pathname;
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>