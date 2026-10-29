<?php
// Supervisor-only detail view for a sponsor request assigned by the authoritative letter + gender matrix.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
if (Session::getUserRole() !== 'supervisor') {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$requestId || $requestId < 1) {
    http_response_code(400);
    $pageTitle = 'طلب كفيل';
    $active = 'sponsors';
    include dirname(__DIR__, 2) . '/includes/header.php';
    echo '<div class="alert alert-warning m-3">رقم الطلب غير صالح.</div>';
    include dirname(__DIR__, 2) . '/includes/footer.php';
    exit();
}

$request = dbFetchOne(
    "SELECT id, sponsor_name, phone, phone_purpose, alt_phone, alt_phone_purpose, email, address,
            sponsor_type, gender, preferred_payment_method, proposed_amount, desired_orphans,
            notes, source, status, created_at
     FROM sponsor_requests
     WHERE id = ? AND assigned_supervisor_id = ?
     LIMIT 1",
    [$requestId, Session::getUserId()]
);
if (!$request) {
    http_response_code(404);
    $pageTitle = 'طلب كفيل';
    $active = 'sponsors';
    include dirname(__DIR__, 2) . '/includes/header.php';
    echo '<div class="alert alert-warning m-3">الطلب غير موجود أو ليس ضمن الطلبات المسندة إليك.</div>';
    include dirname(__DIR__, 2) . '/includes/footer.php';
    exit();
}

$pageTitle = 'تفاصيل طلب كفيل';
$active = 'sponsors';
$statusLabels = ['new' => 'جديد', 'contacted' => 'تم التواصل', 'converted' => 'تم التحويل إلى كفيل', 'lost' => 'مغلق / لم يكتمل'];
$typeLabels = ['individual' => 'فرد', 'company' => 'شركة', 'organization' => 'منظمة'];
$purposeLabels = ['call' => 'للاتصال', 'whatsapp' => 'واتساب', 'both' => 'للاتصال وواتساب'];
$paymentLabels = ['cash' => 'نقدي', 'bank_transfer' => 'تحويل بنكي', 'credit_card' => 'بطاقة ائتمانية', 'mobile' => 'محفظة إلكترونية', 'other' => 'أخرى'];
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-hand-holding-heart me-2"></i>تفاصيل طلب كفيل</h2>
    <p>هذا الطلب مسند إليك وفقاً لتوزيع المشرفين المعتمد. يمكنك الاطلاع على بياناته هنا.</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="card mb-4"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h4 class="mb-0"><?php echo e($request['sponsor_name']); ?></h4>
        <span class="badge bg-info"><?php echo e($statusLabels[$request['status']] ?? $request['status']); ?></span>
    </div>
    <div class="table-responsive"><table class="table table-bordered align-middle">
        <tbody>
        <?php
        $details = [
            'الهاتف' => $request['phone'] ?: '—',
            'غرض الهاتف' => $purposeLabels[$request['phone_purpose'] ?? ''] ?? '—',
            'الهاتف البديل' => $request['alt_phone'] ?: '—',
            'غرض الهاتف البديل' => $purposeLabels[$request['alt_phone_purpose'] ?? ''] ?? '—',
            'البريد الإلكتروني' => $request['email'] ?: '—',
            'العنوان' => $request['address'] ?: '—',
            'نوع مقدم الطلب' => $typeLabels[$request['sponsor_type'] ?? ''] ?? 'فرد',
            'الجنس' => ['male' => 'ذكر', 'female' => 'أنثى', 'organization' => 'غير منطبق'][$request['gender'] ?? ''] ?? '—',
            'طريقة الدفع المفضلة' => $paymentLabels[$request['preferred_payment_method'] ?? ''] ?? '—',
            'عدد الأيتام المطلوب كفالتهم' => $request['desired_orphans'] ?? '—',
            'المبلغ الشهري المقترح' => $request['proposed_amount'] !== null ? number_format((float)$request['proposed_amount'], 2) : '—',
            'مصدر الطلب' => $request['source'] ?: '—',
            'تاريخ الطلب' => $request['created_at'] ?: '—',
            'ملاحظات' => $request['notes'] ?: '—',
        ];
        foreach ($details as $label => $value): ?>
            <tr><th style="width:28%"><?php echo e($label); ?></th><td><?php echo e((string)$value); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <a class="btn btn-outline-secondary" href="<?php echo e(APP_URL . 'modules/sponsors/index.php'); ?>">العودة إلى سجل الكفلاء</a>
</div></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
