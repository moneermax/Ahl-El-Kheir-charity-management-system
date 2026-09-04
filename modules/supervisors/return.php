<?php
// Restore a permanently departed supervisor to active status.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/supervisor_lifecycle.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'vgm'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$user = dbFetchOne("SELECT u.id, u.username, u.full_name, u.is_active,
        COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) AS supervisor_status
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.id = ? AND r.code = 'supervisor'", [$id]);

if (!$user) {
    flash('error', 'المشرف غير موجود.');
    redirect('modules/supervisors/index.php?show_archived=1');
}

if (!supervisorLifecycleIsFinal((string)$user['supervisor_status'])) {
    flash('error', 'هذا المشرف ليس في حالة مغادرة نهائية قابلة للعودة.');
    redirect('modules/supervisors/index.php?show_archived=1');
}

$previousLetters = dbFetchAll("SELECT h.letter_id, h.gender, l.code AS letter_code, h.ended_at, h.end_reason
    FROM supervisor_letter_assignment_history h
    INNER JOIN letters l ON l.id = h.letter_id
    WHERE h.supervisor_id = ?
      AND h.end_reason = 'supervisor_departure'
    ORDER BY h.ended_at DESC, l.sort_order, h.gender", [$id]);

$previousSponsors = (int)(dbFetchOne("SELECT COUNT(*) c
    FROM sponsor_supervisor_assignments
    WHERE supervisor_id = ? AND ended_at IS NOT NULL AND end_reason = 'supervisor_departure'", [$id])['c'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
        redirect('modules/supervisors/return.php?id=' . $id);
    }

    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    try {
        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('كلمتا المرور غير متطابقتين.');
        }
        restoreArchivedSupervisor($id, $newPassword, Session::getUserId());

        try {
            dbExecute("INSERT INTO audit_log
                (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, 'RESTORE', 'supervisor', ?, ?, ?, ?, ?)", [
                Session::getUserId(),
                $id,
                json_encode([
                    'full_name' => $user['full_name'],
                    'supervisor_status' => $user['supervisor_status'],
                    'is_active' => (int)$user['is_active'],
                ], JSON_UNESCAPED_UNICODE),
                json_encode([
                    'supervisor_status' => 'active',
                    'is_active' => 1,
                    'letters_auto_restored' => false,
                    'sponsors_auto_restored' => false,
                ], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
        } catch (Throwable $auditError) {
            error_log('Supervisor restore audit: ' . $auditError->getMessage());
        }

        flash('success', 'تمت إعادة المشرف ' . $user['full_name'] . ' إلى حالة نشط. لم تتم إعادة الكفلاء أو الحروف تلقائياً؛ يمكن اتخاذ قرار التوزيع بشكل صريح من صفحات التوزيع.');
        redirect('modules/supervisors/index.php?show_archived=1');
    } catch (Throwable $e) {
        error_log('Supervisor restore: ' . $e->getMessage());
        flash('error', $e->getMessage());
        redirect('modules/supervisors/return.php?id=' . $id);
    }
}

$pageTitle = 'إعادة مشرف إلى العمل';
$active = 'supervisors';
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.ak-return-table { font-size: 13px; }
.ak-return-table th, .ak-return-table td { padding: 7px 9px; vertical-align: middle; }
.ak-letter-chip { display:inline-flex; min-width:38px; height:32px; align-items:center; justify-content:center; border-radius:8px; font-weight:800; border:1px solid #d8e0ee; background:#eef2f9; }
</style>

<div class="welcome-section fade-in">
    <h2>إعادة المشرف إلى العمل</h2>
    <p>إعادة المشرف المغادر نهائياً إلى حالة نشط مع الحفاظ على سجل المغادرة السابق.</p>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="alert alert-warning fade-in py-2">
    <i class="fas fa-triangle-exclamation me-1"></i>
    <strong>مهم:</strong> إعادة المشرف إلى العمل لا تعيد الكفلاء أو الحروف تلقائياً. أي استعادة للحروف يجب أن تكون قراراً صريحاً، ولا يجوز سحب حرف من مشرف حالي دون تأكيد واضح.
</div>

<div class="card fade-in mb-3">
    <div class="card-header py-3"><i class="fas fa-user-rotate me-2"></i>بيانات المشرف</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><span class="text-muted d-block">المشرف</span><strong><?php echo e($user['full_name']); ?></strong></div>
            <div class="col-md-4"><span class="text-muted d-block">اسم المستخدم</span><strong dir="ltr">@<?php echo e($user['username']); ?></strong></div>
            <div class="col-md-4"><span class="text-muted d-block">الحالة الحالية</span><span class="badge bg-dark"><?php echo e(supervisorLifecycleStatusLabel((string)$user['supervisor_status'])); ?></span></div>
        </div>
    </div>
</div>

<div class="card fade-in mb-3">
    <div class="card-header py-3"><i class="fas fa-clock-rotate-left me-2"></i>المسؤوليات السابقة</div>
    <div class="card-body p-2 p-md-3">
        <div class="row g-3 mb-3">
            <div class="col-md-6"><div class="border rounded p-2"><strong><?php echo count($previousLetters); ?></strong> تعيين حرف سابق تم تحريره بسبب المغادرة النهائية.</div></div>
            <div class="col-md-6"><div class="border rounded p-2"><strong><?php echo $previousSponsors; ?></strong> تعيين كفيل سابق تم إنهاؤه بسبب المغادرة النهائية.</div></div>
        </div>
        <?php if ($previousLetters): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 ak-return-table">
                    <thead><tr><th>الحرف</th><th>الجنس</th><th>تاريخ التحرير</th><th>السبب</th></tr></thead>
                    <tbody>
                    <?php foreach ($previousLetters as $letter): ?>
                        <tr>
                            <td><span class="ak-letter-chip"><?php echo e($letter['letter_code']); ?></span></td>
                            <td><?php echo $letter['gender'] === 'male' ? 'ذكور' : ($letter['gender'] === 'female' ? 'إناث' : 'كلاهما'); ?></td>
                            <td><?php echo e($letter['ended_at']); ?></td>
                            <td><span class="badge bg-secondary">مغادرة نهائية</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-muted">لا توجد تعيينات حروف محفوظة من عملية المغادرة السابقة.</div>
        <?php endif; ?>
    </div>
</div>

<div class="card fade-in border-primary">
    <div class="card-header py-3">تفعيل حساب المشرف</div>
    <div class="card-body">
        <!-- This form intentionally does not use the global data-confirm attribute.
             Confirmation is handled only when the actual submit button is pressed. -->
        <form method="post" id="supervisorReturnForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">كلمة مرور جديدة *</label>
                    <input type="password" name="new_password" class="form-control" minlength="6" required autocomplete="new-password">
                    <small class="text-muted">كلمة المرور القديمة لم تعد صالحة بعد الأرشفة، لذلك يجب تعيين كلمة مرور جديدة.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">تأكيد كلمة المرور *</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="6" required autocomplete="new-password">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary" id="supervisorReturnSubmit"><i class="fas fa-user-check me-1"></i> إعادة المشرف إلى العمل</button>
                <a href="<?php echo APP_URL; ?>modules/supervisors/index.php?show_archived=1" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('supervisorReturnForm');
    if (!form || typeof window.AKNotify === 'undefined') return;

    form.addEventListener('submit', function (event) {
        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        window.AKNotify.confirm(
            'سيتم إعادة المشرف إلى حالة نشط وتفعيل تسجيل الدخول. لن تتم إعادة الكفلاء أو الحروف تلقائياً. هل تريد المتابعة؟',
            function () {
                form.dataset.confirmed = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    HTMLFormElement.prototype.submit.call(form);
                }
            }
        );
    });
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
