<?php
// modules/departments/sync.php - Synchronize linked user departments from employees

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
    flash('error', 'غير مصرح لك بالوصول إلى مزامنة الأقسام.');
    redirect('index.php');
}

$pageTitle = 'مزامنة أقسام المستخدمين والموظفين';
$active = 'departments';
$error = '';
$success = '';
$rows = [];
$changed = null;

function syncRows(): array
{
    return dbFetchAll(
        'SELECT e.id AS employee_id, e.full_name AS employee_name, e.employee_code,
                e.department_id AS employee_department_id,
                ed.name_ar AS employee_department_name,
                e.user_id,
                u.username,
                u.full_name AS user_name,
                u.department_id AS user_department_id,
                ud.name_ar AS user_department_name
         FROM employees e
         INNER JOIN users u ON u.id = e.user_id
         LEFT JOIN departments ed ON ed.id = e.department_id
         LEFT JOIN departments ud ON ud.id = u.department_id
         WHERE e.department_id IS NOT NULL
           AND (u.department_id IS NULL OR u.department_id <> e.department_id)
         ORDER BY e.full_name'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'انتهت صلاحية الجلسة. يرجى إعادة تحميل الصفحة.';
    } elseif (isset($_POST['apply_sync'])) {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'UPDATE users u
                 INNER JOIN employees e ON e.user_id = u.id
                 SET u.department_id = e.department_id
                 WHERE e.department_id IS NOT NULL
                   AND (u.department_id IS NULL OR u.department_id <> e.department_id)'
            );
            $stmt->execute();
            $changed = $stmt->rowCount();
            $pdo->commit();
            $success = "تمت مزامنة {$changed} حساب مستخدم مع أقسام الموظفين بنجاح.";
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            error_log('Department sync: ' . $e->getMessage());
            $error = 'حدث خطأ ولم يتم حفظ التغييرات. تم التراجع عن العملية.';
        }
    }
}

if ($error === '' || $success !== '') {
    try { $rows = syncRows(); } catch (Throwable $e) { $error = 'تعذر قراءة بيانات المزامنة.'; }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="mb-1"><i class="fas fa-arrows-rotate me-2"></i>مزامنة أقسام المستخدمين والموظفين</h3><p class="text-muted mb-0">الموظف هو السجل الأساسي للقسم، وسيتم تحديث حساب المستخدم المرتبط به.</p></div>
    <a href="<?php echo e(APP_URL); ?>modules/departments/index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-right me-1"></i>العودة للأقسام</a>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($error): ?><div class="alert alert-danger"><strong>خطأ:</strong> <?php echo e($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check me-1"></i><?php echo e($success); ?></div><?php endif; ?>
<div class="alert alert-warning"><strong>تنبيه:</strong> خذ نسخة احتياطية من قاعدة البيانات قبل التنفيذ. سيتم تعديل <code>users.department_id</code> فقط للحسابات المرتبطة بسجل موظف، ولن يتم تغيير بيانات الموظفين.</div>
<div class="card mb-4"><div class="card-header"><i class="fas fa-chart-simple me-2"></i>المعاينة</div><div class="card-body"><p>عدد الحسابات التي تحتاج إلى مزامنة: <strong><?php echo count($rows); ?></strong></p><?php if ($rows): ?><div class="table-responsive"><table class="table table-bordered table-hover align-middle"><thead><tr><th>الموظف</th><th>حساب المستخدم</th><th>قسم الموظف — المصدر</th><th>قسم المستخدم الحالي</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?php echo e($row['employee_name']); ?><br><small class="text-muted"><?php echo e($row['employee_code']); ?></small></td><td><?php echo e($row['user_name']); ?><br><small class="text-muted"><?php echo e($row['username']); ?></small></td><td class="text-success fw-bold"><?php echo e($row['employee_department_name'] ?? ('#'.$row['employee_department_id'])); ?></td><td class="text-danger"><?php echo e($row['user_department_name'] ?? ($row['user_department_id'] ? '#'.$row['user_department_id'] : 'غير محدد')); ?></td></tr><?php endforeach; ?></tbody></table></div><form method="post" onsubmit="return confirm('هل تريد مزامنة كل حسابات المستخدمين الظاهرة مع أقسام الموظفين؟');"><?php echo csrf_field(); ?><button class="btn btn-primary" name="apply_sync" value="1"><i class="fas fa-check-double me-1"></i>تطبيق المزامنة تلقائياً</button></form><?php else: ?><div class="alert alert-success mb-0"><i class="fas fa-check me-1"></i>لا توجد اختلافات. أقسام المستخدمين والموظفين متزامنة.</div><?php endif; ?></div></div>
<div class="card"><div class="card-header"><i class="fas fa-circle-info me-2"></i>قاعدة المزامنة</div><div class="card-body"><p class="mb-0">عند وجود موظف مرتبط بحساب مستخدم، يكون <strong>قسم الموظف</strong> هو المصدر المعتمد. بعد التنفيذ ستتساوى أعداد المستخدمين والموظفين في كل قسم، باستثناء أي موظفين غير مرتبطين بحساب مستخدم.</p></div></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
