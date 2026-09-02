<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/sponsor_assignments.php';

Session::start();
$role = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($role, ['admin', 'vice_general_manager', 'general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$user = dbFetchOne("SELECT u.id, u.full_name, u.is_active, r.code AS role_code, r.name_ar AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?", [$id]);
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
        ensureSponsorAssignmentHistoryTable();
        db()->beginTransaction();
        $locked = dbFetchOne("SELECT u.id, u.is_active, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? FOR UPDATE", [$id]);
        if (!$locked || $locked['role_code'] !== 'supervisor') throw new RuntimeException('Invalid supervisor.');

        $released = releaseSupervisorSponsors($id, Session::getUserId());
        dbExecute("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?", [$id]);
        db()->commit();

        flash('success', 'تم تعطيل حساب المشرف ' . $user['full_name'] . ' وتحرير ' . $released . ' كفيل لإعادة التوزيع.');
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        error_log('Supervisor departure: ' . $e->getMessage());
        flash('error', 'تعذر تنفيذ عملية مغادرة المشرف.');
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
    <p>إنهاء عمل المشرف مع الحفاظ على حسابه وسجل الكفلاء.</p>
</div>
<div class="card fade-in border-warning">
    <div class="card-header bg-warning">تأكيد مغادرة المشرف</div>
    <div class="card-body">
        <p>المشرف: <strong><?php echo e($user['full_name']); ?></strong></p>
        <p>الكفلاء المرتبطون به حالياً: <strong><?php echo $assigned; ?></strong></p>
        <div class="alert alert-info">سيتم تعطيل الحساب، وإنهاء التعيينات الحالية، وإبقاء الكفلاء بدون مشرف ليظهروا في قائمة غير المعيّنين لإعادة توزيعهم لاحقاً.</div>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <button class="btn btn-warning"><i class="fas fa-user-slash me-1"></i> تأكيد مغادرة المشرف</button>
            <a href="<?php echo APP_URL; ?>modules/users/index.php" class="btn btn-secondary">إلغاء</a>
        </form>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
