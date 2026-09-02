<?php
// dashboard/vgm_dashboard.php - Vice General Manager dashboard
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';

Session::start();

// Access guard
if (!Session::isLoggedIn() || Session::getUserRole() !== 'vice_general_manager') {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

// Supervisor account actions: edit, pause/resume, and safe account deletion.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supervisor_action'])) {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
        redirect('dashboard/vgm_dashboard.php');
    }

    $supervisorId = (int)($_POST['supervisor_id'] ?? 0);
    $action = (string)($_POST['supervisor_action'] ?? '');
    $target = dbFetchOne("SELECT u.id, u.full_name, u.is_active
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.id = ? AND r.code = 'supervisor'", [$supervisorId]);

    if (!$target) {
        flash('error', 'المشرف غير موجود.');
        redirect('dashboard/vgm_dashboard.php');
    }

    try {
        if ($action === 'toggle_status') {
            $newActive = ((int)$target['is_active'] === 1) ? 0 : 1;
            dbExecute('UPDATE users SET is_active = ? WHERE id = ?', [$newActive, $supervisorId]);
            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                    VALUES (?, 'UPDATE', 'users', ?, ?, ?, ?, ?)", [
                    Session::getUserId(), $supervisorId,
                    json_encode(['is_active' => (int)$target['is_active']], JSON_UNESCAPED_UNICODE),
                    json_encode(['is_active' => $newActive], JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Throwable $e) { /* audit must never break the action */ }
            flash('success', $newActive ? 'تم تفعيل حساب المشرف.' : 'تم إيقاف حساب المشرف.');
        } elseif ($action === 'delete_account') {
            // Logical account deletion: revoke login access and detach assignments, but retain the
            // user row so audit/history and all business records remain intact.
            $pdo = db();
            $pdo->beginTransaction();
            dbExecute('UPDATE families SET supervisor_id = NULL WHERE supervisor_id = ?', [$supervisorId]);
            dbExecute('UPDATE sponsors SET supervisor_id = NULL WHERE supervisor_id = ?', [$supervisorId]);
            dbExecute('UPDATE sponsor_payments SET supervisor_id = NULL WHERE supervisor_id = ?', [$supervisorId]);
            dbExecute('DELETE FROM supervisor_letters WHERE supervisor_id = ?', [$supervisorId]);
            dbExecute('DELETE FROM user_sessions WHERE user_id = ?', [$supervisorId]);
            dbExecute("UPDATE users
                SET is_active = 0,
                    legacy_status = 'deleted',
                    username = CONCAT('deleted_supervisor_', id, '_', UNIX_TIMESTAMP()),
                    password_hash = ?,
                    email = NULL,
                    phone = NULL
                WHERE id = ?", [password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $supervisorId]);
            $pdo->commit();
            flash('success', 'تم حذف صلاحية دخول حساب المشرف وفصل ارتباطاته دون حذف بيانات الأسر أو الأيتام أو الكفلاء.');
        } else {
            flash('error', 'إجراء غير صالح.');
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'تعذر تنفيذ الإجراء، ولم يتم حذف أو تعديل البيانات المرتبطة.');
    }

    redirect('dashboard/vgm_dashboard.php');
}

// Statistics (live schema)
$stats = dbFetchOne("SELECT
    (SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = 'supervisor' AND u.is_active = 1) AS active_supervisors,
    (SELECT COUNT(*) FROM supervisor_letters) AS letters_assigned,
    (SELECT COUNT(*) FROM letters l WHERE NOT EXISTS (SELECT 1 FROM supervisor_letters sl WHERE sl.letter_id = l.id)) AS letters_unassigned,
    (SELECT COUNT(*) FROM sponsors) AS total_sponsors,
    (SELECT COUNT(*) FROM families) AS total_families,
    (SELECT COUNT(*) FROM families WHERE status = 'active') AS active_families,
    (SELECT COUNT(*) FROM sponsorships WHERE status = 'active') AS active_sponsorships
");

$stats = is_array($stats) ? $stats : [];

$supervisors = dbFetchAll("SELECT 
    u.id, 
    u.full_name, 
    u.is_active,
    GROUP_CONCAT(l.name_ar SEPARATOR '، ') AS letters,
    (SELECT COUNT(*) FROM sponsors s WHERE s.supervisor_id = u.id) AS sponsor_count
FROM users u
INNER JOIN roles r ON r.id = u.role_id
LEFT JOIN supervisor_letters sl ON sl.supervisor_id = u.id
LEFT JOIN letters l ON l.id = sl.letter_id
WHERE r.code = 'supervisor'
  AND COALESCE(u.legacy_status, '') <> 'deleted'
GROUP BY u.id, u.full_name, u.is_active
ORDER BY u.full_name
");

$cards = [
    ['value' => (int)($stats['active_supervisors'] ?? 0), 'label' => 'مشرفون نشطون', 'icon' => 'fa-user-tie'],
    ['value' => (int)($stats['letters_assigned'] ?? 0), 'label' => 'حروف موزعة', 'icon' => 'fa-font'],
    ['value' => (int)($stats['letters_unassigned'] ?? 0), 'label' => 'حروف غير موزعة', 'icon' => 'fa-keyboard'],
    ['value' => (int)($stats['total_sponsors'] ?? 0), 'label' => 'إجمالي الكفلاء', 'icon' => 'fa-hand-holding-heart'],
];

$pageTitle = 'لوحة نائب المدير العام';
$active = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<style>
.stat-card {
    border-right: 4px solid #1b4d8f;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(10,31,68,.08);
    transition: transform .2s;
}
.stat-card:hover {
    transform: translateY(-3px);
}
.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1b4d8f;
}
.stat-label {
    color: #6c757d;
    font-size: .9rem;
}
.quick-action-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(10,31,68,.08);
    transition: all .2s;
    text-decoration: none;
    color: inherit;
}
.quick-action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(10,31,68,.15);
    color: inherit;
}
</style>

<div class="welcome-section fade-in">
    <h2>مرحباً، <?php echo e(Session::getUserName()); ?></h2>
    <p>إدارة المشرفين وتوزيع الحروف ومتابعة العمليات</p>
    <div class="quick-actions mt-3">
        <a href="<?php echo APP_URL; ?>modules/supervisors/index.php" class="btn btn-primary btn-sm">
            <i class="fas fa-user-tie me-1"></i> إدارة المشرفين
        </a>
        <a href="<?php echo APP_URL; ?>modules/supervisors/create.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-user-plus me-1"></i> إضافة مشرف
        </a>
        <a href="<?php echo APP_URL; ?>modules/supervisors/assign-letters.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-font me-1"></i> توزيع الحروف
        </a>
        <!-- NEW: Orphan Groups Management Link -->
        <a href="<?php echo APP_URL; ?>modules/deputy_gm/groups.php" class="btn btn-success btn-sm">
            <i class="fas fa-users me-1"></i> إدارة مجموعات الأيتام
        </a>
    </div>
</div>

<?php include __DIR__ . '/../includes/alerts.php'; ?>

<!-- Quick Access Cards Row -->
<div class="row g-3 mb-4 fade-in">
    <div class="col-md-3">
        <a href="<?php echo APP_URL; ?>modules/deputy_gm/groups.php" class="quick-action-card card h-100">
            <div class="card-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-users text-success" style="font-size: 2.5rem;"></i>
                </div>
                <h6 class="card-title text-success">مجموعات الأيتام</h6>
                <p class="card-text text-muted small">إنشاء المجموعات وتعيين الحاضنات</p>
            </div>
        </a>
    </div>
    <?php foreach ($cards as $c): ?>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center">
                <div class="card-body py-3">
                    <div class="stat-value"><?php echo $c['value']; ?></div>
                    <div class="stat-label">
                        <i class="fas <?php echo $c['icon']; ?> me-1"></i>
                        <?php echo $c['label']; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card fade-in">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-user-tie me-2"></i>المشرفون وحروفهم</span>
        <span class="badge bg-primary"><?php echo count($supervisors); ?> مشرف</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>المشرف</th>
                        <th>الحروف</th>
                        <th>الكفلاء</th>
                        <th>الحالة</th>
                        <th class="text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($supervisors)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">لا يوجد مشرفون حتى الآن</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($supervisors as $s): ?>
                            <tr>
                                <td><strong><?php echo e($s['full_name']); ?></strong></td>
                                <td><?php echo e($s['letters'] ?: 'لا يوجد'); ?></td>
                                <td><?php echo (int)$s['sponsor_count']; ?></td>
                                <td>
                                    <?php if ((int)$s['is_active'] === 1): ?>
                                        <span class="badge bg-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">موقوف</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" style="white-space:nowrap;">
                                    <a class="btn btn-sm btn-warning" title="تعديل بيانات الحساب"
                                       href="<?php echo APP_URL; ?>modules/supervisors/edit.php?id=<?php echo (int)$s['id']; ?>">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('هل تريد تغيير حالة هذا الحساب؟');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="supervisor_id" value="<?php echo (int)$s['id']; ?>">
                                        <button type="submit" name="supervisor_action" value="toggle_status"
                                                class="btn btn-sm <?php echo ((int)$s['is_active'] === 1) ? 'btn-danger' : 'btn-success'; ?>"
                                                title="<?php echo ((int)$s['is_active'] === 1) ? 'إيقاف الحساب' : 'تفعيل الحساب'; ?>">
                                            <i class="fas <?php echo ((int)$s['is_active'] === 1) ? 'fa-pause' : 'fa-play'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('سيتم حذف صلاحية دخول حساب المشرف وفصل ارتباطه بالأسر والكفلاء مع إبقاء جميع البيانات والسجل التاريخي. هل تريد المتابعة؟');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="supervisor_id" value="<?php echo (int)$s['id']; ?>">
                                        <button type="submit" name="supervisor_action" value="delete_account" class="btn btn-sm btn-outline-danger" title="حذف صلاحية الحساب وفصل الارتباطات">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADDED: Load the Age Alert Popup Widget for VGM Dashboard -->
<?php include __DIR__ . '/../includes/age_alert.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>