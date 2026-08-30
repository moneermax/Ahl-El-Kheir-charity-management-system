<?php
// modules/transactions/sponsor-report-preview.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();

$allowedRoles = ['nanny', 'financial_manager', 'admin', 'general_manager', 'vice_general_manager'];
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), $allowedRoles, true)) {
    flash(['type' => 'error', 'message' => 'ليس لديك صلاحية الوصول لهذه الصفحة']);
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$sponsorId = isset($_POST['sponsor_id']) ? (int)$_POST['sponsor_id'] : 0;
$reportMonth = isset($_POST['report_month']) ? $_POST['report_month'] : '';

if ($sponsorId <= 0 || empty($reportMonth)) {
    flash(['type' => 'error', 'message' => 'بيانات غير صحيحة']);
    header('Location: ' . APP_URL . 'modules/transactions/sponsor-monthly-report.php');
    exit();
}

$sponsor = dbFetchOne("SELECT id, full_name, sponsor_code, phone, email FROM sponsors WHERE id = ?", [$sponsorId]);
if (!$sponsor) {
    flash(['type' => 'error', 'message' => 'الكفيل غير موجود']);
    header('Location: ' . APP_URL . 'modules/transactions/sponsor-monthly-report.php');
    exit();
}

// Get active sponsorships
$sponsorships = dbFetchAll("
    SELECT sp.child_id, sp.monthly_amount, fc.family_id, f.family_code
    FROM sponsorships sp
    INNER JOIN family_children fc ON fc.id = sp.child_id AND fc.is_active = 1
    INNER JOIN families f ON f.id = fc.family_id
    WHERE sp.sponsor_id = ? AND sp.status = 'active'
    ORDER BY fc.family_id, sp.child_id
", [$sponsorId]);

if (empty($sponsorships)) {
    flash(['type' => 'warning', 'message' => 'هذا الكفيل ليس لديه كفالات نشطة']);
    header('Location: ' . APP_URL . 'modules/transactions/sponsor-monthly-report.php');
    exit();
}

// Get family IDs
$familyIds = array_unique(array_column($sponsorships, 'family_id'));

// Get disbursement status
$placeholders = implode(',', array_fill(0, count($familyIds), '?'));
$disbursements = dbFetchAll("
    SELECT di.family_id, di.status, di.confirmed_at
    FROM disbursement_items di
    INNER JOIN monthly_disbursements md ON md.id = di.disbursement_id
    WHERE di.family_id IN ($placeholders)
    AND md.month = ?
    AND md.status IN ('transferred', 'received')
    AND di.status = 'paid'
", array_merge($familyIds, [$reportMonth]));

$paidFamilies = [];
foreach ($disbursements as $d) {
    $paidFamilies[$d['family_id']] = [
        'confirmed_at' => $d['confirmed_at']
    ];
}

// NEW: Get actual payment details for the month
// Convert reportMonth format (e.g., '2026-08' to 'August/2026')
$paymentPeriod = date('F/Y', strtotime($reportMonth . '-01'));

// FIXED: Changed status filter to accept both 'posted' and 'approved'
$paymentDetails = dbFetchAll("
    SELECT 
        COALESCE(fc.family_id, 0) as family_id,
        COALESCE(f.family_code, 'عام / غير محدد') as family_code,
        sp.payment_type,
        sp.purpose_note,
        SUM(sp.amount) as total_amount
    FROM sponsor_payments sp
    LEFT JOIN sponsorships s ON s.id = sp.sponsorship_id
    LEFT JOIN family_children fc ON fc.id = s.child_id
    LEFT JOIN families f ON f.id = fc.family_id
    WHERE sp.sponsor_id = ?
      AND sp.payment_period = ?
      AND sp.status IN ('posted', 'approved')
    GROUP BY COALESCE(fc.family_id, 0), COALESCE(f.family_code, 'عام / غير محدد'), sp.payment_type, sp.purpose_note
    ORDER BY COALESCE(fc.family_id, 0) ASC, sp.payment_type ASC
", [$sponsorId, $paymentPeriod]);

// Group payments by family
$paymentsByFamily = [];
$totalActualPayments = 0;
foreach ($paymentDetails as $payment) {
    $fid = $payment['family_id'];
    if (!isset($paymentsByFamily[$fid])) {
        $paymentsByFamily[$fid] = [
            'family_code' => $payment['family_code'],
            'payments' => []
        ];
    }
    $paymentsByFamily[$fid]['payments'][] = [
        'type' => $payment['payment_type'],
        'note' => $payment['purpose_note'],
        'amount' => (float)$payment['total_amount']
    ];
    $totalActualPayments += (float)$payment['total_amount'];
}

// Build byFamily array for display
$byFamily = [];
$totalAmount = 0;
foreach ($sponsorships as $sp) {
    $fid = $sp['family_id'];
    if (!isset($byFamily[$fid])) {
        $byFamily[$fid] = [
            'family_code' => $sp['family_code'],
            'children' => [],
            'disbursed' => isset($paidFamilies[$fid]),
            'confirmed_at' => $paidFamilies[$fid]['confirmed_at'] ?? null
        ];
    }
    $byFamily[$fid]['children'][] = [
        'child_id' => $sp['child_id'],
        'monthly_amount' => (float)$sp['monthly_amount']
    ];
    $totalAmount += (float)$sp['monthly_amount'];
}

$pageTitle = 'معاينة تقرير الكفيل';
$active = 'sponsor_reports';
include __DIR__ . '/../../includes/header.php';
?>

<!-- ===== REPORT ONLY - NO HEADER/SIDEBAR ===== -->
<div class="ak-report-page">
    <!-- Action Buttons (hidden on print) -->
    <div class="ak-report-actions no-print">
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="fas fa-print me-1"></i> طباعة / حفظ كـ PDF
        </button>
        <a href="<?php echo APP_URL; ?>modules/transactions/sponsor-monthly-report.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> رجوع
        </a>
    </div>

    <!-- Report Content -->
    <div class="ak-report-content">
        <!-- Header with Logo and Title Centered Together -->
        <div style="display: flex; align-items: center; justify-content: center; gap: 15px; border-bottom: 3px solid #1b4d8f; padding-bottom: 15px; margin-bottom: 20px;">
            <img src="<?php echo APP_URL; ?>assets/img/logo.png" alt="شعار أهل الخير" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 2px solid #1b4d8f;">
            <div style="text-align: center;">
                <h3 style="color: #1b4d8f; font-weight: 700; font-size: 24px; margin: 0 0 5px 0;">منظمة أهل الخير الخيرية</h3>
                <h4 style="color: #333; font-weight: 600; font-size: 20px; margin: 0;">تقرير الكفيل الشهري</h4>
            </div>
        </div>

        <!-- Sponsor Info -->
        <div class="ak-info-grid">
            <div class="ak-info-row">
                <span class="ak-label">اسم الكفيل:</span>
                <span class="ak-value"><?php echo e($sponsor['full_name']); ?></span>
            </div>
            <div class="ak-info-row">
                <span class="ak-label">الشهر:</span>
                <span class="ak-value"><?php echo date('F Y', strtotime($reportMonth . '-01')); ?></span>
            </div>
            <div class="ak-info-row">
                <span class="ak-label">رقم الكفيل:</span>
                <span class="ak-value"><?php echo e($sponsor['sponsor_code']); ?></span>
            </div>
            <div class="ak-info-row">
                <span class="ak-label">تاريخ الطباعة:</span>
                <span class="ak-value"><?php echo date('Y-m-d H:i'); ?></span>
            </div>
        </div>

        <!-- Summary -->
        <div class="ak-summary-box">
            <div class="ak-summary-title">ملخص الكفالات للشهر</div>
            <div class="ak-summary-total">
                <span>إجمالي مبلغ الكفالة الشهرية المتوقع:</span>
                <strong><?php echo number_format($totalAmount, 2); ?> ج.س</strong>
            </div>
            <?php if ($totalActualPayments > 0): ?>
            <div class="ak-summary-total" style="margin-top: 8px; border-top: 1px dashed #1b4d8f; padding-top: 8px;">
                <span>إجمالي المدفوعات الفعلية المسجلة:</span>
                <strong style="color: #28a745;"><?php echo number_format($totalActualPayments, 2); ?> ج.س</strong>
            </div>
            <?php endif; ?>
        </div>

        <!-- Expected Sponsorship Table -->
        <?php if (empty($byFamily)): ?>
            <div class="alert alert-warning">لا توجد كفالات نشطة لهذا الكفيل.</div>
        <?php else: ?>
            <h5 style="color: #1b4d8f; margin: 15px 0 10px 0; font-size: 14px; font-weight: 700;">
                أولاً: الكفالات الشهرية المتوقعة
            </h5>
            <table class="ak-report-table">
                <thead>
                    <tr>
                        <th>رقم الأسرة</th>
                        <th>رقم اليتيم</th>
                        <th>مبلغ الكفالة (ج.س)</th>
                        <th>حالة الصرف</th>
                        <th>تاريخ التأكيد</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($byFamily as $fid => $data): ?>
                        <?php $first = true; foreach ($data['children'] as $child): ?>
                            <tr>
                                <?php if ($first): ?>
                                    <td rowspan="<?php echo count($data['children']); ?>"><?php echo e($data['family_code']); ?></td>
                                <?php endif; ?>
                                <td><strong><?php echo $child['child_id']; ?></strong></td>
                                <td class="ak-amount"><?php echo number_format($child['monthly_amount'], 2); ?></td>
                                <td>
                                    <?php if ($data['disbursed']): ?>
                                        <span class="ak-badge-paid">✓ تم الصرف</span>
                                    <?php else: ?>
                                        <span class="ak-badge-pending"> قيد الانتظار</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $data['confirmed_at'] ? date('Y-m-d', strtotime($data['confirmed_at'])) : '-'; ?></td>
                            </tr>
                        <?php $first = false; endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="ak-total-label">الإجمالي الكلي للكفالة الشهرية:</td>
                        <td class="ak-total-amount"><?php echo number_format($totalAmount, 2); ?> ج.س</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>

        <!-- NEW: Actual Payment Details Section -->
        <?php if (!empty($paymentsByFamily)): ?>
            <h5 style="color: #1b4d8f; margin: 20px 0 10px 0; font-size: 14px; font-weight: 700; border-top: 2px solid #1b4d8f; padding-top: 15px;">
                ثانياً: تفاصيل المدفوعات الفعلية المسجلة
            </h5>
            <table class="ak-report-table">
                <thead>
                    <tr>
                        <th>رقم الأسرة</th>
                        <th>نوع الدفع</th>
                        <th>ملاحظات</th>
                        <th>المبلغ (ج.س)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentsByFamily as $fid => $familyData): ?>
                        <?php foreach ($familyData['payments'] as $payment): ?>
                            <tr>
                                <td><?php echo e($familyData['family_code']); ?></td>
                                <td>
                                    <?php
                                    // Translate payment types to Arabic
                                    $typeLabels = [
                                        'monthly_sponsorship' => 'كفالة شهرية',
                                        'school_fees' => 'رسوم دراسية',
                                        'medicine' => 'علاج وأدوية',
                                        'clothing' => 'ملابس',
                                        'food' => 'مواد غذائية',
                                        'housing' => 'إسكان',
                                        'emergency' => 'مساعدات طارئة',
                                        'other' => 'أخرى'
                                    ];
                                    echo $typeLabels[$payment['type']] ?? ucfirst($payment['type']);
                                    ?>
                                </td>
                                <td><?php echo e($payment['note'] ?? '-'); ?></td>
                                <td class="ak-amount" style="color: #28a745;"><?php echo number_format($payment['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="ak-total-label">إجمالي المدفوعات الفعلية:</td>
                        <td class="ak-total-amount" style="color: #28a745;"><?php echo number_format($totalActualPayments, 2); ?> ج.س</td>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <!-- No actual payments yet -->
            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; font-size: 12px;">
                <strong>ملاحظة:</strong> لا توجد مدفوعات فعلية مسجلة لهذا الشهر حتى الآن. المبالغ المعروضة أعلاه تمثل الالتزام الشهري المتوقع حسب اتفاقية الكفالة.
            </div>
        <?php endif; ?>

        <!-- Note -->
        <div class="ak-note-box">
            <strong>ملاحظة مهمة:</strong> المبالغ المعروضة تمثل <strong>مبلغ الكفالة الشهرية</strong> التي يلتزم بها الكفيل لكل يتيم حسب اتفاقية الكفالة. حالة الصرف توضح ما إذا تم صرف مستحقات الأسرة في الشهر المحدد.
        </div>

        <!-- Signatures: Side by Side -->
        <div class="ak-signatures">
            <div class="ak-sig-block">
                <div class="ak-sig-title">توقيع المدير المالي (FM)</div>
                <div class="ak-sig-line"></div>
                <div class="ak-sig-date">التاريخ: ____/____/________</div>
            </div>
            <div class="ak-sig-block">
                <div class="ak-sig-title">توقيع المدير العام (GM)</div>
                <div class="ak-sig-line"></div>
                <div class="ak-sig-date">التاريخ: ____/____/________</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="ak-report-footer">
            <strong>هذا التقرير صادر عن نظام أهل الخير الإلكتروني</strong><br>
            للاستفسار: يرجى التواصل مع الأخصائية الاجتماعية<br>
            تم إصدار هذا التقرير في <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
</div>

<style>
/* ===== SCREEN STYLES ===== */
.ak-report-page {
    background: #f4f7fb;
    padding: 10px;
    min-height: 100vh;
}

.ak-report-actions {
    max-width: 210mm;
    margin: 0 auto 10px;
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.ak-report-content {
    max-width: 210mm;
    margin: 0 auto;
    background: #fff;
    padding: 15mm 12mm;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 4px;
}

/* Info Grid */
.ak-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 20px;
    margin-bottom: 12px;
    font-size: 13px;
}

.ak-info-row {
    display: flex;
    gap: 8px;
}

.ak-label {
    font-weight: 700;
    color: #1b4d8f;
    min-width: 110px;
}

.ak-value {
    color: #333;
}

/* Summary */
.ak-summary-box {
    background: #e8f4f8;
    border: 2px solid #1b4d8f;
    border-radius: 6px;
    padding: 10px 14px;
    margin-bottom: 12px;
}

.ak-summary-title {
    font-size: 13px;
    font-weight: 700;
    color: #1b4d8f;
    margin-bottom: 4px;
}

.ak-summary-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
}

.ak-summary-total strong {
    color: #1b4d8f;
    font-size: 18px;
}

/* Table */
.ak-report-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
    font-size: 12px;
}

.ak-report-table th {
    background: #1b4d8f;
    color: #fff;
    padding: 7px 8px;
    text-align: right;
    font-size: 12px;
    border: 1px solid #1b4d8f;
}

.ak-report-table td {
    padding: 6px 8px;
    border: 1px solid #ddd;
    text-align: right;
}

.ak-amount {
    font-weight: 700;
    color: #1b4d8f;
}

.ak-badge-paid {
    background: #28a745;
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
}

.ak-badge-pending {
    background: #ffc107;
    color: #333;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
}

.ak-total-label {
    font-weight: 700;
    text-align: left;
    background: #e8f4f8;
    padding: 8px;
}

.ak-total-amount {
    font-weight: 800;
    color: #1b4d8f;
    font-size: 14px;
    background: #e8f4f8;
    padding: 8px;
}

/* Note */
.ak-note-box {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 11px;
    margin-bottom: 15px;
}

/* Signatures: Side by Side */
.ak-signatures {
    display: flex;
    justify-content: space-between;
    gap: 30px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 2px solid #1b4d8f;
}

.ak-sig-block {
    flex: 1;
    text-align: center;
}

.ak-sig-title {
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 25px;
    color: #333;
}

.ak-sig-line {
    border-top: 1.5px solid #333;
    width: 80%;
    margin: 0 auto 8px;
    padding-top: 4px;
}

.ak-sig-date {
    font-size: 12px;
    color: #666;
}

/* Footer */
.ak-report-footer {
    text-align: center;
    font-size: 10px;
    color: #666;
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #ddd;
}

/* ===== PRINT STYLES - CRITICAL ===== */
@media print {
    @page {
        size: A4 portrait;
        margin: 10mm 12mm;
    }
    
    html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }
    
    /* Hide ALL site elements */
    header, .qa-top-bar, .topbar, .navbar, nav,
    .sidebar, aside, #sidebar,
    .quick-actions-bar, .quick-actions,
    .ak-report-actions, .no-print,
    footer, .footer, .page-footer,
    .alert-dismissible,
    .breadcrumb, .page-title-section {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
        overflow: hidden !important;
    }
    
    /* Reset report page */
    .ak-report-page {
        background: #fff !important;
        padding: 0 !important;
        margin: 0 !important;
        min-height: auto !important;
    }
    
    .ak-report-content {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        background: #fff !important;
    }
    
    /* Compact everything for one page */
    .ak-report-header {
        padding-bottom: 6px !important;
        margin-bottom: 8px !important;
    }
    
    .ak-logo-img {
        width: 55px !important;
        height: 55px !important;
    }
    
    .ak-org-name {
        font-size: 18px !important;
        margin-bottom: 2px !important;
    }
    
    .ak-report-name {
        font-size: 15px !important;
    }
    
    .ak-info-grid {
        gap: 3px 15px !important;
        margin-bottom: 8px !important;
        font-size: 11px !important;
    }
    
    .ak-label {
        min-width: 90px !important;
    }
    
    .ak-summary-box {
        padding: 6px 10px !important;
        margin-bottom: 8px !important;
    }
    
    .ak-summary-title {
        font-size: 11px !important;
        margin-bottom: 2px !important;
    }
    
    .ak-summary-total {
        font-size: 12px !important;
    }
    
    .ak-summary-total strong {
        font-size: 15px !important;
    }
    
    .ak-report-table {
        font-size: 10px !important;
        margin-bottom: 8px !important;
    }
    
    .ak-report-table th,
    .ak-report-table td {
        padding: 4px 5px !important;
    }
    
    .ak-note-box {
        padding: 5px 8px !important;
        font-size: 10px !important;
        margin-bottom: 10px !important;
    }
    
    .ak-signatures {
        margin-top: 12px !important;
        padding-top: 10px !important;
        gap: 20px !important;
    }
    
    .ak-sig-title {
        font-size: 12px !important;
        margin-bottom: 18px !important;
    }
    
    .ak-sig-date {
        font-size: 10px !important;
    }
    
    .ak-report-footer {
        font-size: 9px !important;
        margin-top: 10px !important;
        padding-top: 6px !important;
    }
    
    /* Prevent page breaks inside elements */
    .ak-report-content,
    .ak-report-header,
    .ak-info-grid,
    .ak-summary-box,
    .ak-report-table,
    .ak-note-box,
    .ak-signatures {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
    
    tr {
        page-break-inside: avoid !important;
    }
    
    /* Colors print */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>