<?php
// Supervisor-only detail view for a sponsor request assigned through the individual matrix or VGM legal-entity routing.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/sponsor_assignments.php';
require_once __DIR__ . '/lib_sponsor_request_conversion.php';
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
            notes, source, status, created_at, created_sponsor_id
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        http_response_code(403);
        $pageTitle = 'طلب كفيل';
        $active = 'sponsors';
        include dirname(__DIR__, 2) . '/includes/header.php';
        echo '<div class="alert alert-danger m-3">انتهت صلاحية النموذج أو تعذر التحقق من الطلب. حدّث الصفحة ثم حاول مجدداً.</div>';
        include dirname(__DIR__, 2) . '/includes/footer.php';
        exit();
    }

    $postedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $action = trim((string)($_POST['action'] ?? ''));
    $newStatus = trim((string)($_POST['status'] ?? ''));

    if (!$postedId || $postedId !== (int)$request['id'] || !in_array($action, ['set_status', 'convert'], true)) {
        flash('error', 'تعذر التحقق من إجراء الطلب.');
    } elseif ($action === 'convert') {
        try {
            $sponsorId = convertSponsorRequestToSponsor($requestId, (int)Session::getUserId());
            flash('success_long', 'تم تحويل الطلب إلى كفيل بنجاح. يمكنك الآن متابعة تسجيل الأيتام وتحديد تفاصيل الكفالة من صفحة الكفيل.');
            header('Location: ' . APP_URL . 'modules/sponsors/view.php?id=' . $sponsorId);
            exit();
        } catch (Throwable $e) {
            flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'تعذر تحويل الطلب إلى كفيل. ربما تم تحويله مسبقاً أو حدث خطأ أثناء الحفظ.');
        }
    } elseif (!in_array($newStatus, ['contacted', 'lost'], true)) {
        flash('error', 'حالة الطلب غير صالحة.');
    } else {
        $currentStatus = (string)$request['status'];
        $allowedCurrentStatuses = $newStatus === 'contacted' ? ['new'] : ['new', 'contacted'];

        if (!in_array($currentStatus, $allowedCurrentStatuses, true)) {
            flash('error', 'لا يمكن تغيير حالة الطلب في وضعه الحالي.');
        } else {
            $updated = $newStatus === 'contacted'
                ? dbExecute(
                    "UPDATE sponsor_requests SET status = 'contacted' WHERE id = ? AND assigned_supervisor_id = ? AND status = 'new'",
                    [$requestId, Session::getUserId()]
                )
                : dbExecute(
                    "UPDATE sponsor_requests SET status = 'lost' WHERE id = ? AND assigned_supervisor_id = ? AND status IN ('new', 'contacted')",
                    [$requestId, Session::getUserId()]
                );

            $current = dbFetchOne(
                "SELECT status FROM sponsor_requests WHERE id = ? AND assigned_supervisor_id = ? LIMIT 1",
                [$requestId, Session::getUserId()]
            );
            if ($current && (string)$current['status'] === $newStatus) {
                flash('success', $newStatus === 'contacted' ? 'تم تحديث الطلب إلى «تم التواصل».' : 'تم إغلاق الطلب بوصفه غير مكتمل.');
            } elseif (!$updated || !$current) {
                flash('error', 'تعذر تحديث الطلب؛ ربما تغيرت حالته أو لم يعد ضمن الطلبات المسندة إليك.');
            } else {
                flash('error', 'لم تتغير حالة الطلب. حدّث الصفحة وتحقق من الحالة الحالية.');
            }
        }
    }

    header('Location: ' . APP_URL . 'modules/sponsors/my_request.php?id=' . $requestId);
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
    <p>هذا الطلب مسند إليك لمتابعة التواصل واستكمال إجراءات تحويله إلى كفيل عند الاتفاق.</p>
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
    <?php if (in_array((string)$request['status'], ['new', 'contacted'], true)): ?>
    <hr>
    <div class="d-flex flex-wrap gap-2 align-items-center" aria-label="إجراءات طلب الكفيل">
        <?php if ((string)$request['status'] === 'new'): ?>
        <form method="post" class="d-inline" onsubmit="return confirm('تأكيد تسجيل أنه تم التواصل مع مقدم الطلب؟');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="set_status">
            <input type="hidden" name="status" value="contacted">
            <input type="hidden" name="id" value="<?php echo (int)$request['id']; ?>">
            <button type="submit" class="btn btn-outline-info"><i class="fas fa-phone me-1"></i>تم التواصل</button>
        </form>
        <?php endif; ?>
        <form method="post" class="d-inline" data-ak-native-post="1" onsubmit="return confirm('تأكيد تحويل هذا الطلب إلى سجل كفيل نشط؟ سيتم إنشاء سجل الكفيل ثم فتح صفحته لمتابعة إجراءات الكفالة.');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="convert">
            <input type="hidden" name="id" value="<?php echo (int)$request['id']; ?>">
            <button type="submit" class="btn btn-success"><i class="fas fa-user-check me-1"></i>تحويل إلى كفيل ومتابعة</button>
        </form>
        <form method="post" class="d-inline" onsubmit="return confirm('تأكيد إغلاق الطلب بوصفه غير مكتمل؟');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="set_status">
            <input type="hidden" name="status" value="lost">
            <input type="hidden" name="id" value="<?php echo (int)$request['id']; ?>">
            <button type="submit" class="btn btn-outline-secondary"><i class="fas fa-xmark me-1"></i>إغلاق / لم يكتمل</button>
        </form>
    </div>
    <?php elseif ((string)$request['status'] === 'lost'): ?>
    <div class="alert alert-secondary mt-3 mb-0">هذا الطلب مغلق. إعادة فتح الطلب أو تحويله إلى سجل كفيل تتم من خلال مسار الإدارة المخوّل.</div>
    <?php elseif ((string)$request['status'] === 'converted'): ?>
    <div class="alert alert-success mt-3 mb-0">تم تحويل هذا الطلب إلى سجل كفيل.</div>
    <?php if (!empty($request['created_sponsor_id'])): ?>
    <a class="btn btn-success mt-2" href="<?php echo e(APP_URL . 'modules/sponsors/view.php?id=' . (int)$request['created_sponsor_id']); ?>"><i class="fas fa-arrow-up-right-from-square me-1"></i>فتح سجل الكفيل ومتابعة الكفالة</a>
    <?php endif; ?>
    <?php endif; ?>
    <div class="mt-3">
        <a class="btn btn-outline-secondary" href="<?php echo e(APP_URL . 'modules/sponsors/index.php'); ?>">العودة إلى سجل الكفلاء</a>
    </div>
</div></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
