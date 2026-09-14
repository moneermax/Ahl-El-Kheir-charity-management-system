<?php
// modules/accounting/returned_funds_nanny.php - Nanny side of later redelivery
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib_returned_funds.php';
Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'nanny') {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$uid = Session::getUserId();
returned_funds_ensure_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf() && isset($_POST['redeliver'])) {
    $reissueId = (int)$_POST['redeliver'];
    $record = dbFetchOne("SELECT r.id, r.disbursement_item_id, r.family_id
        FROM returned_disbursement_reissues r
        INNER JOIN disbursement_items i ON i.id = r.disbursement_item_id
        INNER JOIN monthly_disbursements d ON d.id = r.disbursement_id
        WHERE r.id = ? AND r.status = 'authorized' AND i.status = 'returned' AND d.nanny_id = ? LIMIT 1",
        [$reissueId, $uid]);
    if (!$record) {
        flash('error', 'سجل إعادة الصرف غير متاح لك.');
    } elseif (empty($_FILES['redelivery_receipt']['tmp_name'])) {
        flash('error', 'إيصال استلام الأسرة إجباري لإتمام إعادة الصرف.');
    } else {
        $upload = returned_funds_upload_receipt($_FILES['redelivery_receipt'], (int)$record['disbursement_item_id'], (int)$record['family_id']);
        if (!$upload['success']) {
            flash('error', $upload['message']);
        } else {
            $result = returned_funds_redeliver($reissueId, $uid, $uid, $upload['path']);
            if ($result['success']) {
                flash('success', 'تم تأكيد إعادة تسليم المبلغ وتسجيل القيد المحاسبي وخصم المبلغ من الصندوق بنجاح.');
            } else {
                if (!empty($upload['absolute_path']) && is_file($upload['absolute_path'])) {
                    @unlink($upload['absolute_path']);
                }
                flash('error', 'تعذر إتمام إعادة الصرف: ' . $result['message']);
            }
        }
    }
    header('Location: ' . APP_URL . 'modules/accounting/returned_funds_nanny.php');
    exit();
}

$rows = dbFetchAll("SELECT r.*, d.month, d.nanny_id, f.family_code, f.mother_name,
    d.group_id, og.group_name
    FROM returned_disbursement_reissues r
    INNER JOIN disbursement_items i ON i.id = r.disbursement_item_id
    INNER JOIN monthly_disbursements d ON d.id = r.disbursement_id
    INNER JOIN families f ON f.id = r.family_id
    LEFT JOIN orphan_groups og ON og.id = d.group_id
    WHERE d.nanny_id = ? AND r.status = 'authorized' AND i.status = 'returned'
    ORDER BY r.authorized_at DESC, r.id DESC", [$uid]);

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-hand-holding-dollar me-2"></i>إعادة صرف المبالغ المرتجعة</h2>
    <p>هذه الصفحة تعرض فقط المبالغ التي اعتمدها المحاسب لإعادة تسليمها للأسرة. لا يتم خصم أي مبلغ جديد إلا بعد رفع إيصال التسليم.</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="alert alert-info">
    <strong>تنبيه مالي:</strong> هذه مبالغ سبق إرجاعها للصندوق وإلغاء أثر المصروف السابق. عند التسليم الفعلي فقط سيتم تسجيل مصروف جديد وخصم المبلغ من الصندوق.
</div>
<div class="card fade-in">
    <div class="card-header"><strong>المبالغ المعتمدة لإعادة التسليم</strong></div>
    <div class="card-body">
        <?php if (!$rows): ?>
            <div class="text-center text-muted py-5">لا توجد مبالغ معتمدة لإعادة التسليم حالياً.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>الأسرة</th><th>الشهر الأصلي</th><th>المجموعة</th><th>المبلغ</th><th>تاريخ الاعتماد</th><th>الإجراء</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><strong><?php echo e($row['mother_name']); ?></strong><br><small class="text-muted"><?php echo e($row['family_code']); ?></small></td>
                    <td><?php echo e($row['month']); ?></td>
                    <td><?php echo e($row['group_name'] ?? '—'); ?></td>
                    <td><strong><?php echo number_format((float)$row['amount'], 2); ?> ج.س</strong></td>
                    <td><?php echo e($row['authorized_at']); ?></td>
                    <td>
                        <form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-end">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="redeliver" value="<?php echo (int)$row['id']; ?>">
                            <div><label class="form-label small mb-1">إيصال استلام الأسرة <span class="text-danger">*</span></label><input type="file" name="redelivery_receipt" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.gif" required></div>
                            <button class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i> تأكيد التسليم</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[action=""]').forEach(function () {});
    document.querySelectorAll('button').forEach(function (button) {
        if (!button.classList.contains('btn-success')) return;
        button.addEventListener('click', function (e) {
            const form = this.closest('form');
            if (!form) return;
            const file = form.querySelector('input[type="file"]');
            if (!file || !file.files.length) return;
            e.preventDefault();
            Swal.fire({
                title: 'تأكيد إعادة التسليم',
                text: 'سيتم تسجيل إيصال الأسرة وخصم المبلغ من الصندوق وتسجيل القيد المحاسبي الجديد.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، تم التسليم',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then(function (result) { if (result.isConfirmed) form.submit(); });
        });
    });
});
</script>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
