<?php
// modules/users/recovery.php - Password recovery request management
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

/* Password recovery is handled by HR and the system administrator. */
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'hr_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'طلبات استعادة كلمات المرور';
$active    = 'users';
$approvedTemporaryPassword = null;
$approvedFor = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة.');
        header('Location: ' . APP_URL . 'modules/users/recovery.php');
        exit();
    }

    $rid = (int)($_POST['request_id'] ?? 0);
    $req = dbFetchOne("SELECT r.*, u.username, u.full_name, u.email FROM password_recovery_requests r JOIN users u ON u.id = r.user_id WHERE r.id = ?", [$rid]);

    if (!$req || $req['status'] !== 'pending') {
        flash('error', 'الطلب غير موجود أو تمت معالجته.');
    } elseif (isset($_POST['approve'])) {
        $temporaryPassword = bin2hex(random_bytes(6));
        $temporaryHash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

        try {
            dbExecute("UPDATE users SET password_hash = ?, password_change_required = 1 WHERE id = ?", [$temporaryHash, (int)$req['user_id']]);
            dbExecute("UPDATE password_recovery_requests SET status='approved', reviewed_by=?, reviewed_at=NOW(), expires_at=DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id=? AND status='pending'", [Session::getUserId(), $rid]);

            try {
                dbExecute("INSERT INTO notifications (recipient_user_id, type, title, body, link, is_read) VALUES (?, 'recovery', ?, ?, ?, 0)", [(int)$req['user_id'], 'تمت الموافقة على طلب استعادة كلمة المرور', 'تمت الموافقة على طلبك. تواصل مع مسؤول الموارد البشرية أو مسؤول النظام لاستلام كلمة المرور المؤقتة.', 'modules/users/change_password.php?forced=1']);
            } catch (Throwable $e) {}

            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, 'RECOVERY_APPROVED', 'users', ?, NULL, ?, ?, ?)", [Session::getUserId(), (int)$req['user_id'], json_encode(['recovery_request_id' => $rid, 'password_change_required' => true], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            } catch (Throwable $e) {}

            $approvedTemporaryPassword = $temporaryPassword;
            $approvedFor = $req['full_name'];
            flash('success', 'تمت الموافقة على الطلب وإنشاء كلمة مرور مؤقتة للمستخدم.');
        } catch (Throwable $e) {
            flash('error', 'تعذر تنفيذ استعادة كلمة المرور. لم يتم تغيير الحساب.');
        }
    } elseif (isset($_POST['reject'])) {
        dbExecute("UPDATE password_recovery_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE id=? AND status='pending'", [Session::getUserId(), $rid]);
        flash('success', 'تم رفض طلب استعادة كلمة المرور.');
    }

    if ($approvedTemporaryPassword === null) {
        header('Location: ' . APP_URL . 'modules/users/recovery.php');
        exit();
    }
}

try {
    dbExecute("UPDATE notifications SET is_read = 1 WHERE recipient_user_id = ? AND type = 'recovery'", [Session::getUserId()]);
} catch (Throwable $e) {}

$requests = dbFetchAll("SELECT r.*, u.username, u.full_name, rev.full_name AS reviewer_name FROM password_recovery_requests r JOIN users u ON u.id = r.user_id LEFT JOIN users rev ON rev.id = r.reviewed_by ORDER BY (r.status = 'pending') DESC, r.id DESC LIMIT 50");

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2>طلبات استعادة كلمات المرور</h2>
    <p>تتم معالجة طلبات استعادة كلمات المرور بواسطة الموارد البشرية أو مسؤول النظام. عند الموافقة يتم إنشاء كلمة مرور مؤقتة ويُجبر المستخدم على تغييرها بعد تسجيل الدخول.</p>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<?php if ($approvedTemporaryPassword): ?>
<div class="alert alert-warning fade-in">
    <h5 class="alert-heading"><i class="fas fa-key me-2"></i>كلمة المرور المؤقتة لـ <?php echo e($approvedFor); ?></h5>
    <p class="fs-3 fw-bold mb-1" dir="ltr" style="letter-spacing:4px"><?php echo e($approvedTemporaryPassword); ?></p>
    <p class="mb-0"><small>سلّم كلمة المرور للمستخدم الآن. ستصبح غير صالحة بعد تغيير المستخدم لها.</small></p>
</div>
<?php endif; ?>

<div class="card fade-in"><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle">
<thead><tr><th>#</th><th>المستخدم</th><th>تاريخ الطلب</th><th>الحالة</th><th>راجعه</th><th>الصلاحية</th><th class="text-center">إجراء</th></tr></thead>
<tbody>
<?php if (!$requests): ?>
<tr><td colspan="7" class="text-center text-muted py-4">لا توجد طلبات.</td></tr>
<?php else: foreach ($requests as $r): ?>
<tr>
<td><?php echo (int)$r['id']; ?></td>
<td><strong><?php echo e($r['full_name']); ?></strong> <small class="text-muted">(<?php echo e($r['username']); ?>)</small></td>
<td><small><?php echo e($r['requested_at']); ?></small></td>
<td><?php $st=['pending'=>['قيد المراجعة','bg-warning text-dark'],'approved'=>['موافق عليه','bg-info'],'completed'=>['مكتمل','bg-success'],'rejected'=>['مرفوض','bg-danger'],'expired'=>['منتهي','bg-secondary']]; [$sl,$sc]=$st[$r['status']]??[$r['status'],'bg-secondary']; ?><span class="badge <?php echo $sc; ?>"><?php echo $sl; ?></span></td>
<td><?php echo e($r['reviewer_name'] ?? '—'); ?></td>
<td><small><?php echo $r['expires_at'] ? e($r['expires_at']) : '—'; ?></small></td>
<td class="text-center">
<?php if ($r['status'] === 'pending'): ?>
<form method="post" class="d-inline recovery-action-form">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
    <button type="button" name="approve" value="1" class="btn btn-sm btn-success recovery-confirm-btn" data-action="approve"><i class="fas fa-check me-1"></i>موافقة</button>
    <button type="button" name="reject" value="1" class="btn btn-sm btn-danger recovery-confirm-btn" data-action="reject"><i class="fas fa-ban me-1"></i>رفض</button>
</form>
<?php else: ?>—<?php endif; ?>
</td></tr>
<?php endforeach; endif; ?>
</tbody></table></div></div></div>

<!-- System confirmation modal: avoids browser confirm() dialogs. -->
<div class="modal fade" id="recoveryConfirmModal" tabindex="-1" aria-labelledby="recoveryConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="recoveryConfirmTitle"><i class="fas fa-circle-question me-2"></i>تأكيد الإجراء</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i id="recoveryConfirmIcon" class="fas fa-key fa-3x mb-3"></i>
                <p id="recoveryConfirmMessage" class="fs-5 mb-0">هل أنت متأكد من تنفيذ هذا الإجراء؟</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" id="recoveryConfirmSubmit" class="btn btn-primary px-4">تأكيد</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('recoveryConfirmModal');
    const messageElement = document.getElementById('recoveryConfirmMessage');
    const titleElement = document.getElementById('recoveryConfirmTitle');
    const iconElement = document.getElementById('recoveryConfirmIcon');
    const submitButton = document.getElementById('recoveryConfirmSubmit');
    let pendingForm = null;
    let pendingAction = null;

    if (!modalElement || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalElement);

    document.querySelectorAll('.recovery-confirm-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            pendingForm = button.closest('form');
            pendingAction = button.dataset.action;

            if (pendingAction === 'approve') {
                titleElement.innerHTML = '<i class="fas fa-key me-2"></i>تأكيد الموافقة';
                messageElement.textContent = 'هل أنت متأكد من الموافقة وتوليد كلمة مرور مؤقتة لهذا المستخدم؟';
                iconElement.className = 'fas fa-key fa-3x mb-3 text-success';
                submitButton.className = 'btn btn-success px-4';
                submitButton.textContent = 'موافقة وتوليد كلمة المرور';
            } else {
                titleElement.innerHTML = '<i class="fas fa-ban me-2"></i>تأكيد الرفض';
                messageElement.textContent = 'هل أنت متأكد من رفض طلب استعادة كلمة المرور؟';
                iconElement.className = 'fas fa-triangle-exclamation fa-3x mb-3 text-danger';
                submitButton.className = 'btn btn-danger px-4';
                submitButton.textContent = 'رفض الطلب';
            }

            modal.show();
        });
    });

    submitButton.addEventListener('click', function () {
        if (!pendingForm || !pendingAction) return;

        const hiddenAction = document.createElement('input');
        hiddenAction.type = 'hidden';
        hiddenAction.name = pendingAction;
        hiddenAction.value = '1';
        pendingForm.appendChild(hiddenAction);
        pendingForm.submit();
    });

    modalElement.addEventListener('hidden.bs.modal', function () {
        pendingForm = null;
        pendingAction = null;
    });
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>