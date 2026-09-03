<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/supervisor_lifecycle.php';

Session::start();
$role = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($role, ['admin', 'vice_general_manager', 'general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$user = dbFetchOne("SELECT u.id, u.full_name, u.is_active,
        COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) AS supervisor_status,
        r.code AS role_code, r.name_ar AS role_name
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?", [$id]);

if (!$user || $user['role_code'] !== 'supervisor') {
    flash('error', 'المشرف غير موجود.');
    redirect('modules/users/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة.');
        redirect('modules/users/index.php');
    }
    if ($id === Session::getUserId()) {
        flash('error', 'لا يمكن تعطيل حسابك الحالي.');
        redirect('modules/users/index.php');
    }

    try {
        $released = archiveSupervisor($id, Session::getUserId());

        try {
            dbExecute("INSERT INTO audit_log
                (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, 'ARCHIVE', 'supervisor', ?, ?, ?, ?, ?)", [
                    Session::getUserId(),
                    $id,
                    json_encode([
                        'full_name' => $user['full_name'],
                        'supervisor_status' => $user['supervisor_status'],
                        'is_active' => (int)$user['is_active'],
                        'released_sponsors' => $released,
                    ], JSON_UNESCAPED_UNICODE),
                    json_encode([
                        'supervisor_status' => 'archived',
                        'is_active' => 0,
                        'released_sponsors' => $released,
                    ], JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );
        } catch (Throwable $auditError) {
            error_log('Supervisor archive audit: ' . $auditError->getMessage());
        }

        flash('success', 'تمت أرشفة المشرف ' . $user['full_name'] . ' وإيقاف صلاحية الدخول وتحرير ' . $released . ' كفيل لإعادة التوزيع، مع الحفاظ على السجل التاريخي.');
    } catch (Throwable $e) {
        error_log('Supervisor departure: ' . $e->getMessage());
        flash('error', 'تعذر تنفيذ عملية مغادرة المشرف، ولم يتم تغيير البيانات المرتبطة.');
    }

    redirect('modules/users/index.php');
}

$assigned = (int)(dbFetchOne("SELECT COUNT(*) c FROM sponsors WHERE supervisor_id = ?", [$id])['c'] ?? 0);
$pageTitle = 'مغادرة المشرف';
$active = 'users';
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>مغادرة المشرف</h2>
    <p>هذه العملية مخصصة للمغادرة الدائمة، مع الحفاظ على هوية المشرف وسجل المسؤوليات.</p>
</div>
<div class="card fade-in border-danger">
    <div class="card-header bg-danger text-white">تأكيد المغادرة الدائمة</div>
    <div class="card-body">
        <p>المشرف: <strong><?php echo e($user['full_name']); ?></strong></p>
        <p>الحالة الحالية: <strong><?php echo e(supervisorLifecycleStatusLabel((string)$user['supervisor_status'])); ?></strong></p>
        <p>الكفلاء المرتبطون به حالياً: <strong><?php echo $assigned; ?></strong></p>
        <div class="alert alert-warning">
            <strong>تنبيه:</strong> هذه العملية تعني مغادرة دائمة، وليست إجازة مؤقتة.
            سيتم أرشفة الحساب، وإنهاء التعيينات الحالية للكفلاء، وإظهار الكفلاء غير المعيّنين في قائمة إعادة التوزيع.
            لن يتم حذف السجل التاريخي للمشرف.
        </div>
        <div class="alert alert-info">
            إذا كان المشرف في إجازة أو غياب مؤقت وقد يعود، استخدم <strong>إدارة الإجازة</strong> بدلاً من هذه العملية.
        </div>
        <form method="post" data-confirm="هل أنت متأكد من تسجيل المغادرة الدائمة لهذا المشرف؟ سيتم أرشفة الحساب وتحرير الكفلاء لإعادة التوزيع.">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <button class="btn btn-danger"><i class="fas fa-user-slash me-1"></i> تأكيد المغادرة الدائمة</button>
            <a href="<?php echo APP_URL; ?>modules/users/index.php" class="btn btn-secondary">إلغاء</a>
        </form>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
