<<<<<<< HEAD
<?php
// modules/supervisors/index.php - Supervisors list (Admin + VGM)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
// ---- Access guard ----
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
// ---- POST: toggle active / suspended ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $id = (int)($_POST['user_id'] ?? 0);
    $target = dbFetchOne("
        SELECT u.id, u.full_name, u.is_active
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.id = ? AND r.code = 'supervisor'
    ", [$id]);
    if ($target) {
        $newActive = ((int)$target['is_active'] === 1) ? 0 : 1;
        dbExecute("UPDATE users SET is_active = ? WHERE id = ?", [$newActive, $id]);
        try {
            dbExecute("
                INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, 'UPDATE', 'users', ?, ?, ?, ?, ?)
            ", [
                Session::getUserId(),
                $id,
                json_encode(['is_active' => (int)$target['is_active']], JSON_UNESCAPED_UNICODE),
                json_encode(['is_active' => $newActive], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Throwable $e) { /* never break the page because of audit */ }
        flash('success', 'تم تحديث حالة المشرف بنجاح.');
    }
    header('Location: ' . APP_URL . 'modules/supervisors/index.php');
    exit();
}
// ---- Load supervisors + letters(+gender) + workloads ----
$supervisors = dbFetchAll("
    SELECT u.id, u.username, u.full_name, u.phone, u.email, u.is_active, u.last_login_at,
        (SELECT GROUP_CONCAT(CONCAT(l.code, CASE sl.gender WHEN 'male' THEN '(ذ)' WHEN 'female' THEN '(إ)' ELSE '' END)
             ORDER BY l.sort_order SEPARATOR '، ')
         FROM supervisor_letters sl
         INNER JOIN letters l ON l.id = sl.letter_id
         WHERE sl.supervisor_id = u.id) AS letters,
        (SELECT COUNT(*)
         FROM supervisor_letters sl2
         INNER JOIN sponsors s ON s.first_letter_id = sl2.letter_id
         WHERE sl2.supervisor_id = u.id) AS sponsor_count,
        (SELECT COUNT(*) FROM families f WHERE f.supervisor_id = u.id) AS family_count
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE r.code = 'supervisor'
    ORDER BY u.full_name
");
$pageTitle = 'إدارة المشرفين';
$active    = 'supervisors';
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
<h2>إدارة المشرفين</h2>
<p>إنشاء حسابات المشرفين، توزيع الحروف، ومتابعة أعباء العمل</p>
<div class="quick-actions mt-3">
<a href="<?php echo APP_URL; ?>modules/supervisors/create.php" class="btn btn-primary btn-sm">
<i class="fas fa-user-plus me-1"></i> إضافة مشرف
</a>
<a href="<?php echo APP_URL; ?>modules/supervisors/assign-letters.php" class="btn btn-secondary btn-sm">
<i class="fas fa-font me-1"></i> توزيع الحروف
</a>
</div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="card fade-in">
<div class="card-header d-flex justify-content-between align-items-center">
<span><i class="fas fa-user-tie me-2"></i>قائمة المشرفين</span>
<span class="badge bg-primary"><?php echo count($supervisors); ?> مشرف</span>
</div>
<div class="card-body">
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
<tr>
<th>#</th>
<th>المشرف</th>
<th>اسم المستخدم</th>
<th>الحروف</th>
<th>الكفلاء</th>
<th>الأسر</th>
<th>الحالة</th>
<th class="text-center">إجراءات</th>
</tr>
</thead>
<tbody>
<?php if (empty($supervisors)): ?>
<tr><td colspan="8" class="text-center text-muted py-4">لا يوجد مشرفون بعد.</td></tr>
<?php else: foreach ($supervisors as $i => $sup): ?>
<tr>
<td><?php echo $i + 1; ?></td>
<td><strong><?php echo e($sup['full_name']); ?></strong></td>
<td><?php echo e($sup['username']); ?></td>
<td>
<?php if (!empty($sup['letters'])): ?>
<?php foreach (explode('، ', $sup['letters']) as $L): ?>
<span class="badge bg-light text-dark border"><?php echo e($L); ?></span>
<?php endforeach; ?>
<?php else: ?>
<span class="text-muted">— لا حروف —</span>
<?php endif; ?>
</td>
<td>
<a href="<?php echo APP_URL; ?>modules/supervisors/sponsors.php?supervisor=<?php echo (int)$sup['id']; ?>">
<?php echo (int)$sup['sponsor_count']; ?>
</a>
</td>
<td><?php echo (int)$sup['family_count']; ?></td>
<td>
<?php if ((int)$sup['is_active'] === 1): ?>
<span class="badge bg-success">نشط</span>
<?php else: ?>
<span class="badge bg-danger">موقوف</span>
<?php endif; ?>
</td>
<td class="text-center" style="white-space:nowrap;">
<a class="btn btn-sm btn-primary" title="كفلاء المشرف"
href="<?php echo APP_URL; ?>modules/supervisors/sponsors.php?supervisor=<?php echo (int)$sup['id']; ?>">
<i class="fas fa-hand-holding-heart"></i>
</a>
<a class="btn btn-sm btn-warning" title="تعديل"
href="<?php echo APP_URL; ?>modules/supervisors/edit.php?id=<?php echo (int)$sup['id']; ?>">
<i class="fas fa-pen"></i>
</a>
<a class="btn btn-sm btn-info" title="الحروف"
href="<?php echo APP_URL; ?>modules/supervisors/assign-letters.php?supervisor=<?php echo (int)$sup['id']; ?>">
<i class="fas fa-font"></i>
</a>
<form method="post" class="d-inline" onsubmit="return confirm('تغيير حالة المشرف؟');">
<input type="hidden" name="user_id" value="<?php echo (int)$sup['id']; ?>">
<button type="submit" name="toggle_status" value="1"
class="btn btn-sm <?php echo ((int)$sup['is_active'] === 1) ? 'btn-danger' : 'btn-success'; ?>"
title="<?php echo ((int)$sup['is_active'] === 1) ? 'إيقاف' : 'تفعيل'; ?>">
<i class="fas <?php echo ((int)$sup['is_active'] === 1) ? 'fa-pause' : 'fa-play'; ?>"></i>
</button>
</form>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>
</div>
=======
<?php
// modules/supervisors/index.php - Supervisors list (Admin + VGM)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
// ---- Access guard ----
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
// ---- POST: toggle active / suspended ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $id = (int)($_POST['user_id'] ?? 0);
    $target = dbFetchOne("
        SELECT u.id, u.full_name, u.is_active
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.id = ? AND r.code = 'supervisor'
    ", [$id]);
    if ($target) {
        $newActive = ((int)$target['is_active'] === 1) ? 0 : 1;
        dbExecute("UPDATE users SET is_active = ? WHERE id = ?", [$newActive, $id]);
        try {
            dbExecute("
                INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, 'UPDATE', 'users', ?, ?, ?, ?, ?)
            ", [
                Session::getUserId(),
                $id,
                json_encode(['is_active' => (int)$target['is_active']], JSON_UNESCAPED_UNICODE),
                json_encode(['is_active' => $newActive], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Throwable $e) { /* never break the page because of audit */ }
        flash('success', 'تم تحديث حالة المشرف بنجاح.');
    }
    header('Location: ' . APP_URL . 'modules/supervisors/index.php');
    exit();
}
// ---- Load supervisors + letters(+gender) + workloads ----
$supervisors = dbFetchAll("
    SELECT u.id, u.username, u.full_name, u.phone, u.email, u.is_active, u.last_login_at,
        (SELECT GROUP_CONCAT(CONCAT(l.code, CASE sl.gender WHEN 'male' THEN '(ذ)' WHEN 'female' THEN '(إ)' ELSE '' END)
             ORDER BY l.sort_order SEPARATOR '، ')
         FROM supervisor_letters sl
         INNER JOIN letters l ON l.id = sl.letter_id
         WHERE sl.supervisor_id = u.id) AS letters,
        (SELECT COUNT(*)
         FROM supervisor_letters sl2
         INNER JOIN sponsors s ON s.first_letter_id = sl2.letter_id
         WHERE sl2.supervisor_id = u.id) AS sponsor_count,
        (SELECT COUNT(*) FROM families f WHERE f.supervisor_id = u.id) AS family_count
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE r.code = 'supervisor'
    ORDER BY u.full_name
");
$pageTitle = 'إدارة المشرفين';
$active    = 'supervisors';
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
<h2>إدارة المشرفين</h2>
<p>إنشاء حسابات المشرفين، توزيع الحروف، ومتابعة أعباء العمل</p>
<div class="quick-actions mt-3">
<a href="<?php echo APP_URL; ?>modules/supervisors/create.php" class="btn btn-primary btn-sm">
<i class="fas fa-user-plus me-1"></i> إضافة مشرف
</a>
<a href="<?php echo APP_URL; ?>modules/supervisors/assign-letters.php" class="btn btn-secondary btn-sm">
<i class="fas fa-font me-1"></i> توزيع الحروف
</a>
</div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="card fade-in">
<div class="card-header d-flex justify-content-between align-items-center">
<span><i class="fas fa-user-tie me-2"></i>قائمة المشرفين</span>
<span class="badge bg-primary"><?php echo count($supervisors); ?> مشرف</span>
</div>
<div class="card-body">
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
<tr>
<th>#</th>
<th>المشرف</th>
<th>اسم المستخدم</th>
<th>الحروف</th>
<th>الكفلاء</th>
<th>الأسر</th>
<th>الحالة</th>
<th class="text-center">إجراءات</th>
</tr>
</thead>
<tbody>
<?php if (empty($supervisors)): ?>
<tr><td colspan="8" class="text-center text-muted py-4">لا يوجد مشرفون بعد.</td></tr>
<?php else: foreach ($supervisors as $i => $sup): ?>
<tr>
<td><?php echo $i + 1; ?></td>
<td><strong><?php echo e($sup['full_name']); ?></strong></td>
<td><?php echo e($sup['username']); ?></td>
<td>
<?php if (!empty($sup['letters'])): ?>
<?php foreach (explode('، ', $sup['letters']) as $L): ?>
<span class="badge bg-light text-dark border"><?php echo e($L); ?></span>
<?php endforeach; ?>
<?php else: ?>
<span class="text-muted">— لا حروف —</span>
<?php endif; ?>
</td>
<td>
<a href="<?php echo APP_URL; ?>modules/supervisors/sponsors.php?supervisor=<?php echo (int)$sup['id']; ?>">
<?php echo (int)$sup['sponsor_count']; ?>
</a>
</td>
<td><?php echo (int)$sup['family_count']; ?></td>
<td>
<?php if ((int)$sup['is_active'] === 1): ?>
<span class="badge bg-success">نشط</span>
<?php else: ?>
<span class="badge bg-danger">موقوف</span>
<?php endif; ?>
</td>
<td class="text-center" style="white-space:nowrap;">
<a class="btn btn-sm btn-primary" title="كفلاء المشرف"
href="<?php echo APP_URL; ?>modules/supervisors/sponsors.php?supervisor=<?php echo (int)$sup['id']; ?>">
<i class="fas fa-hand-holding-heart"></i>
</a>
<a class="btn btn-sm btn-warning" title="تعديل"
href="<?php echo APP_URL; ?>modules/supervisors/edit.php?id=<?php echo (int)$sup['id']; ?>">
<i class="fas fa-pen"></i>
</a>
<a class="btn btn-sm btn-info" title="الحروف"
href="<?php echo APP_URL; ?>modules/supervisors/assign-letters.php?supervisor=<?php echo (int)$sup['id']; ?>">
<i class="fas fa-font"></i>
</a>
<form method="post" class="d-inline" onsubmit="return confirm('تغيير حالة المشرف؟');">
<input type="hidden" name="user_id" value="<?php echo (int)$sup['id']; ?>">
<button type="submit" name="toggle_status" value="1"
class="btn btn-sm <?php echo ((int)$sup['is_active'] === 1) ? 'btn-danger' : 'btn-success'; ?>"
title="<?php echo ((int)$sup['is_active'] === 1) ? 'إيقاف' : 'تفعيل'; ?>">
<i class="fas <?php echo ((int)$sup['is_active'] === 1) ? 'fa-pause' : 'fa-play'; ?>"></i>
</button>
</form>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>
</div>
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>