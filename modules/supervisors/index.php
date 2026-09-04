<?php
// modules/supervisors/index.php - Supervisors list (Admin + VGM)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/supervisor_lifecycle.php';
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

// ---- Load supervisors + letters(+gender) + workloads + lifecycle ----
$supervisors = dbFetchAll("
    SELECT u.id, u.username, u.full_name, u.phone, u.email, u.is_active,
        COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) AS supervisor_status,
        u.last_login_at,
        (SELECT GROUP_CONCAT(CONCAT(l.code, CASE sl.gender WHEN 'male' THEN '(ذ)' WHEN 'female' THEN '(إ)' ELSE '' END)
             ORDER BY l.sort_order SEPARATOR '، ')
         FROM supervisor_letters sl
         INNER JOIN letters l ON l.id = sl.letter_id
         WHERE sl.supervisor_id = u.id) AS letters,
        (SELECT COUNT(*)
         FROM sponsor_supervisor_assignments ssa
         WHERE ssa.supervisor_id = u.id AND ssa.ended_at IS NULL) AS sponsor_count,
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
    <p>إدارة المشرفين، توزيع الحروف، متابعة أعباء العمل، وإدارة دورة حياة المشرف.</p>
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

<div class="alert alert-info fade-in">
    <i class="fas fa-circle-info me-1"></i>
    <strong>دورة حياة المشرف:</strong>
    الإيقاف المؤقت لا يعني المغادرة النهائية. المغادرة النهائية تحفظ السجل التاريخي وتحرر الكفلاء لإعادة التوزيع اليدوي.
</div>

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
                    <th class="text-center">إجراءات المشرف</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($supervisors)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">لا يوجد مشرفون بعد.</td></tr>
                <?php else: foreach ($supervisors as $i => $sup):
                    $status = (string)$sup['supervisor_status'];
                    $isFinal = supervisorLifecycleIsFinal($status);
                    $statusClass = match ($status) {
                        'active' => 'bg-success',
                        'on_leave' => 'bg-warning text-dark',
                        'returning' => 'bg-info text-dark',
                        'suspended' => 'bg-danger',
                        'departed', 'archived' => 'bg-dark',
                        default => 'bg-secondary',
                    };
                ?>
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
                        <span class="badge <?php echo $statusClass; ?>">
                            <?php echo e(supervisorLifecycleStatusLabel($status)); ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <div class="d-flex flex-wrap justify-content-center gap-1">
                            <a class="btn btn-sm btn-primary"
                               title="عرض الكفلاء المرتبطين بالمشرف"
                               href="<?php echo APP_URL; ?>modules/supervisors/sponsors.php?supervisor=<?php echo (int)$sup['id']; ?>">
                                <i class="fas fa-hand-holding-heart me-1"></i> الكفلاء
                            </a>
                            <a class="btn btn-sm btn-warning"
                               title="تعديل بيانات المشرف"
                               href="<?php echo APP_URL; ?>modules/supervisors/edit.php?id=<?php echo (int)$sup['id']; ?>">
                                <i class="fas fa-pen me-1"></i> تعديل
                            </a>
                            <a class="btn btn-sm btn-info"
                               title="إدارة الحروف المسندة للمشرف"
                               href="<?php echo APP_URL; ?>modules/supervisors/assign-letters.php?supervisor=<?php echo (int)$sup['id']; ?>">
                                <i class="fas fa-font me-1"></i> الحروف
                            </a>

                            <?php if (!$isFinal && in_array($status, ['active', 'suspended'], true)): ?>
                                <form method="post" action="<?php echo APP_URL; ?>modules/users/supervisor_status.php" class="d-inline" data-confirm="<?php echo $status === 'active' ? 'هل تريد إيقاف هذا المشرف مؤقتاً؟' : 'هل تريد إعادة تفعيل هذا المشرف؟'; ?>">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$sup['id']; ?>">
                                    <button type="submit"
                                            class="btn btn-sm <?php echo $status === 'active' ? 'btn-danger' : 'btn-success'; ?>"
                                            title="<?php echo $status === 'active' ? 'إيقاف مؤقت' : 'إعادة تفعيل'; ?>">
                                        <i class="fas <?php echo $status === 'active' ? 'fa-pause' : 'fa-play'; ?> me-1"></i>
                                        <?php echo $status === 'active' ? 'إيقاف مؤقت' : 'تفعيل'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (!$isFinal): ?>
                                <a class="btn btn-sm btn-outline-danger"
                                   title="تسجيل مغادرة دائمة وأرشفة الحساب وتحرير الكفلاء لإعادة التوزيع"
                                   href="<?php echo APP_URL; ?>modules/users/supervisor_departure.php?id=<?php echo (int)$sup['id']; ?>">
                                    <i class="fas fa-user-slash me-1"></i> مغادرة نهائية
                                </a>
                            <?php else: ?>
                                <span class="badge bg-dark align-self-center py-2">حساب نهائي/مؤرشف</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>