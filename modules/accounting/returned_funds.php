<?php
// modules/accounting/returned_funds.php - Returned funds awaiting later re-disbursement
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib_returned_funds.php';
Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'accountant_staff', 'financial_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$role = Session::getUserRole();
$uid = Session::getUserId();
returned_funds_ensure_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf() && isset($_POST['authorize_reissue'])) {
    $itemId = (int)$_POST['authorize_reissue'];
    $reason = trim((string)($_POST['authorization_reason'] ?? ''));
    $result = returned_funds_authorize($itemId, $uid, $role, $reason);
    flash($result['success'] ? 'success' : 'error', $result['success']
        ? 'تم اعتماد السجل لإعادة الصرف لاحقاً. لم يتم خصم أي مبلغ من الصندوق في هذه الخطوة.'
        : $result['message']);
    header('Location: ' . APP_URL . 'modules/accounting/returned_funds.php');
    exit();
}

$scope = '';
$params = [];
if ($role === 'accountant_staff') {
    $scope = 'AND d.nanny_id IN (SELECT nanny_id FROM accountant_nanny_assignments WHERE accountant_id = ?)';
    $params[] = $uid;
}

$rows = dbFetchAll("SELECT
    i.id AS item_id, i.disbursement_id, i.family_id, i.amount,
    i.return_reason, i.returned_at, i.reversal_journal_id,
    d.month, d.nanny_id, n.full_name AS nanny_name,
    f.family_code, f.mother_name,
    r.id AS reissue_id, r.status AS reissue_status,
    r.authorized_at, r.authorization_reason,
    r.redelivered_at, r.redelivery_receipt_path, r.redelivery_journal_id
    FROM disbursement_items i
    INNER JOIN monthly_disbursements d ON d.id = i.disbursement_id
    INNER JOIN users n ON n.id = d.nanny_id
    INNER JOIN families f ON f.id = i.family_id
    LEFT JOIN returned_disbursement_reissues r ON r.disbursement_item_id = i.id
    WHERE (i.status = 'returned' OR r.id IS NOT NULL) $scope
    ORDER BY
        CASE WHEN r.status = 'redelivered' THEN 2 WHEN r.status = 'authorized' THEN 1 ELSE 0 END,
        i.returned_at DESC, i.id DESC", $params);

$counts = ['awaiting' => 0, 'authorized' => 0, 'redelivered' => 0, 'amount' => 0.0];
foreach ($rows as $row) {
    $status = $row['reissue_status'] ?: 'awaiting_redelivery';
    if ($status === 'authorized') $counts['authorized']++;
    elseif ($status === 'redelivered') $counts['redelivered']++;
    else $counts['awaiting']++;
    if ($status !== 'redelivered') $counts['amount'] += (float)$row['amount'];
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-rotate-left me-2"></i>المبالغ المرتجعة المستحقة لإعادة الصرف</h2>
    <p>سجل مستقل يحافظ على تاريخ الإرجاع، ثم يسمح باعتماد إعادة الصرف لاحقاً دون محو القيد الأصلي.</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-warning h-100"><div class="card-body"><small class="text-muted">بانتظار اعتماد إعادة الصرف</small><div class="fs-3 fw-bold text-warning"><?php echo (int)$counts['awaiting']; ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-primary h-100"><div class="card-body"><small class="text-muted">تم اعتمادها</small><div class="fs-3 fw-bold text-primary"><?php echo (int)$counts['authorized']; ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-success h-100"><div class="card-body"><small class="text-muted">تمت إعادة الصرف</small><div class="fs-3 fw-bold text-success"><?php echo (int)$counts['redelivered']; ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-danger h-100"><div class="card-body"><small class="text-muted">المبالغ المفتوحة</small><div class="fs-5 fw-bold text-danger"><?php echo number_format($counts['amount'], 2); ?> ج.س</div></div></div></div>
</div>
<div class="card fade-in">
    <div class="card-header"><strong><i class="fas fa-list me-2"></i>السجلات المرتجعة</strong></div>
    <div class="card-body">
        <?php if (!$rows): ?>
            <div class="text-center text-muted py-5">لا توجد مبالغ مرتجعة ضمن نطاق صلاحيتك.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr>
                    <th>الأسرة</th><th>الشهر الأصلي</th><th>المبلغ</th><th>الحاضنة</th>
                    <th>سبب الإرجاع</th><th>تاريخ الإرجاع</th><th>الحالة</th><th>إجراء</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $status = $row['reissue_status'] ?: 'awaiting_redelivery'; ?>
                    <tr>
                        <td><strong><?php echo e($row['mother_name']); ?></strong><br><small class="text-muted"><?php echo e($row['family_code']); ?></small></td>
                        <td><?php echo e($row['month']); ?></td>
                        <td><?php echo number_format((float)$row['amount'], 2); ?> ج.س</td>
                        <td><?php echo e($row['nanny_name']); ?></td>
                        <td><?php echo e($row['return_reason'] ?: '—'); ?></td>
                        <td><?php echo e($row['returned_at'] ?: '—'); ?></td>
                        <td>
                            <?php if ($status === 'authorized'): ?>
                                <span class="badge bg-primary">معتمد لإعادة الصرف</span>
                            <?php elseif ($status === 'redelivered'): ?>
                                <span class="badge bg-success">تمت إعادة الصرف</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">بانتظار اعتماد إعادة الصرف</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($status === 'awaiting_redelivery' || $status === 'cancelled'): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#authorizeModal<?php echo (int)$row['item_id']; ?>">
                                <i class="fas fa-unlock me-1"></i> إعادة فتح لإعادة الصرف
                            </button>
                            <div class="modal fade" id="authorizeModal<?php echo (int)$row['item_id']; ?>" tabindex="-1">
                                <div class="modal-dialog"><form method="post"><div class="modal-content">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="authorize_reissue" value="<?php echo (int)$row['item_id']; ?>">
                                    <div class="modal-header"><h5 class="modal-title">إعادة فتح لإعادة الصرف</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <p>سيتم السماح للحاضنة <strong><?php echo e($row['nanny_name']); ?></strong> بتسليم مبلغ <strong><?php echo number_format((float)$row['amount'], 2); ?> ج.س</strong> للأسرة لاحقاً.</p>
                                        <div class="alert alert-warning small">لن يتم خصم أي مبلغ من الصندوق الآن. الخصم والقيد الجديد يحدثان فقط عند رفع إيصال التسليم الفعلي.</div>
                                        <label class="form-label">سبب إعادة الفتح لإعادة الصرف <span class="text-danger">*</span></label>
                                        <textarea name="authorization_reason" class="form-control" rows="3" required placeholder="مثال: تم التواصل مع الأسرة مجدداً ويمكن تسليم المبلغ المرتجع"></textarea>
                                    </div>
                                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button class="btn btn-primary">اعتماد إعادة الصرف</button></div>
                                </div></form></div>
                            </div>
                            <?php elseif ($status === 'authorized'): ?>
                                <span class="text-primary small">بانتظار إيصال الحاضنة</span>
                            <?php else: ?>
                                <span class="text-success small">قيد مكتمل</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
