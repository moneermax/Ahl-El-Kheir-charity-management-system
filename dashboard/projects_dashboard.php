<?php
// dashboard/projects_dashboard.php - Unified dashboard for Projects Manager and Project Supervisor
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/modules/projects/project_lib.php';

Session::start();
$role = akp_role();
$uid = akp_user_id();

// تقييد الوصول لمديري المشاريع ومشرفي المشاريع فقط
if (!Session::isLoggedIn() || !in_array($role, ['projects_manager', 'project_supervisor'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'لوحة تحكم المشاريع';
$active = 'projects_dashboard';

// ==========================================
// جلب البيانات بناءً على الدور والصلاحيات
// ==========================================

if ($role === 'projects_manager') {
    // إحصائيات مدير المشاريع
    $stats = dbFetchOne("
        SELECT 
            COUNT(*) as total_projects,
            SUM(CASE WHEN current_status IN ('active', 'reopened') THEN 1 ELSE 0 END) as active_projects,
            SUM(CASE WHEN approval_status = 'submitted' THEN 1 ELSE 0 END) as pending_approval,
            COALESCE(SUM(CASE WHEN current_status IN ('active', 'reopened') THEN target_amount ELSE 0 END), 0) as active_budget
        FROM (
            SELECT p.id, p.target_amount, COALESCE(l.lifecycle_status, p.status) AS current_status,
                   COALESCE((SELECT pa.approval_status FROM project_approval pa WHERE pa.project_id = p.id), 'approved') AS approval_status
            FROM other_projects p
            LEFT JOIN project_lifecycle l ON l.project_id = p.id
        ) as subquery
    ");

    // المشاريع بانتظار الاعتماد التنفيذي
    $pendingApprovals = dbFetchAll("
        SELECT p.id, p.name, p.project_code, pa.submitted_at, u.full_name as submitted_by_name
        FROM project_approval pa
        JOIN other_projects p ON p.id = pa.project_id
        LEFT JOIN users u ON u.id = pa.submitted_by
        WHERE pa.approval_status = 'submitted'
        ORDER BY pa.submitted_at DESC
        LIMIT 5
    ");

    // أحدث المشاريع
    $recentProjects = dbFetchAll("
        SELECT p.id, p.name, p.project_code, COALESCE(l.lifecycle_status, p.status) as status, p.target_amount
        FROM other_projects p
        LEFT JOIN project_lifecycle l ON l.project_id = p.id
        ORDER BY p.id DESC
        LIMIT 5
    ");

} else { // project_supervisor
    // إحصائيات مشرف المشروع
    $stats = dbFetchOne("
        SELECT 
            COUNT(DISTINCT p.id) as assigned_projects,
            SUM(CASE WHEN COALESCE(l.lifecycle_status, p.status) IN ('active', 'reopened') THEN 1 ELSE 0 END) as active_assigned,
            COUNT(DISTINCT lh.id) as pending_labor,
            COUNT(DISTINCT pm.id) as upcoming_milestones
        FROM other_projects p
        LEFT JOIN project_lifecycle l ON l.project_id = p.id
        LEFT JOIN project_supervisor_assignments psa ON psa.project_id = p.id AND psa.ended_at IS NULL AND psa.supervisor_user_id = ?
        LEFT JOIN project_labor_helpers lh ON lh.project_id = p.id AND lh.supervisor_user_id = ? AND lh.status IN ('planned', 'in_progress')
        LEFT JOIN project_milestones pm ON pm.project_id = p.id AND pm.status = 'pending'
        WHERE psa.supervisor_user_id = ?
    ", [$uid, $uid, $uid]);

    // المشاريع المكلف بها المشرف
    $assignedProjects = dbFetchAll("
        SELECT p.id, p.name, p.project_code, COALESCE(l.lifecycle_status, p.status) as status
        FROM other_projects p
        JOIN project_supervisor_assignments psa ON psa.project_id = p.id AND psa.ended_at IS NULL
        LEFT JOIN project_lifecycle l ON l.project_id = p.id
        WHERE psa.supervisor_user_id = ?
        ORDER BY p.id DESC
        LIMIT 5
    ", [$uid]);

    // سجلات العمالة الخارجية النشطة التي تحتاج متابعة
    $pendingLabor = dbFetchAll("
        SELECT lh.id, lh.provider_name, lh.work_description, p.name as project_name, lh.status
        FROM project_labor_helpers lh
        JOIN other_projects p ON p.id = lh.project_id
        WHERE lh.supervisor_user_id = ? AND lh.status IN ('planned', 'in_progress')
        ORDER BY lh.id DESC
        LIMIT 5
    ", [$uid]);
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2>مرحباً، <?php echo $role === 'projects_manager' ? 'مدير المشاريع' : 'مشرف المشروع'; ?></h2>
    <p>
        <?php echo $role === 'projects_manager' 
            ? 'لوحة التحكم الشاملة لإدارة محفظة المشاريع، الاعتمادات، والميزانيات.' 
            : 'لوحة المتابعة التشغيلية للمشاريع المكلف بها، بما في ذلك التقدم، العمالة، والمستفيدين.'; ?>
    </p>
    <div class="quick-actions mt-3">
        <?php if ($role === 'projects_manager'): ?>
            <a href="<?php echo APP_URL; ?>modules/projects/form.php" class="btn btn-primary btn-sm me-2">
                <i class="fas fa-plus me-1"></i> مشروع جديد
            </a>
            <a href="<?php echo APP_URL; ?>modules/projects/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-list me-1"></i> محفظة المشاريع
            </a>
        <?php else: ?>
            <a href="<?php echo APP_URL; ?>modules/projects/index.php" class="btn btn-primary btn-sm me-2">
                <i class="fas fa-folder-open me-1"></i> مشاريعي
            </a>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/alerts.php'; ?>

<!-- بطاقات الإحصائيات -->
<div class="row g-4 mb-4">
    <?php if ($role === 'projects_manager'): ?>
        <div class="col-md-3">
            <div class="card fade-in border-primary h-100">
                <div class="card-body text-center">
                    <i class="fas fa-diagram-project fa-2x text-primary mb-2"></i>
                    <h3><?php echo (int)($stats['total_projects'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">إجمالي المشاريع</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card fade-in border-success h-100">
                <div class="card-body text-center">
                    <i class="fas fa-spinner fa-spin-pulse fa-2x text-success mb-2"></i>
                    <h3><?php echo (int)($stats['active_projects'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">قيد التنفيذ</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card fade-in border-warning h-100">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                    <h3><?php echo (int)($stats['pending_approval'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">بانتظار الاعتماد</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card fade-in border-info h-100">
                <div class="card-body text-center">
                    <i class="fas fa-coins fa-2x text-info mb-2"></i>
                    <h3><?php echo number_format((float)($stats['active_budget'] ?? 0), 0); ?></h3>
                    <p class="text-muted mb-0">ميزانية المشاريع النشطة</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="col-md-3">
            <div class="card fade-in border-primary h-100">
                <div class="card-body text-center">
                    <i class="fas fa-folder-open fa-2x text-primary mb-2"></i>
                    <h3><?php echo (int)($stats['assigned_projects'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">مشاريع مكلف بها</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card fade-in border-success h-100">
                <div class="card-body text-center">
                    <i class="fas fa-play-circle fa-2x text-success mb-2"></i>
                    <h3><?php echo (int)($stats['active_assigned'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">مشاريع نشطة</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card fade-in border-warning h-100">
                <div class="card-body text-center">
                    <i class="fas fa-hard-hat fa-2x text-warning mb-2"></i>
                    <h3><?php echo (int)($stats['pending_labor'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">عمالة قيد التنفيذ</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card fade-in border-info h-100">
                <div class="card-body text-center">
                    <i class="fas fa-flag-checkered fa-2x text-info mb-2"></i>
                    <h3><?php echo (int)($stats['upcoming_milestones'] ?? 0); ?></h3>
                    <p class="text-muted mb-0">مراحل قادمة</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- العمود الرئيسي للمحتوى -->
    <div class="col-lg-8">
        <?php if ($role === 'projects_manager'): ?>
            <!-- طلبات الاعتماد المعلقة -->
            <div class="card fade-in mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clipboard-check me-2 text-warning"></i>طلبات الاعتماد المعلقة</h5>
                    <a href="<?php echo APP_URL; ?>modules/projects/index.php" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingApprovals)): ?>
                        <p class="text-muted text-center py-4 mb-0">لا توجد مشاريع بانتظار الاعتماد حالياً.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>المشروع</th>
                                        <th>مقدم الطلب</th>
                                        <th>تاريخ التقديم</th>
                                        <th class="text-center">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingApprovals as $pa): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo e($pa['name']); ?></strong><br>
                                                <small class="text-muted"><?php echo e($pa['project_code']); ?></small>
                                            </td>
                                            <td><?php echo e($pa['submitted_by_name'] ?? 'غير معروف'); ?></td>
                                            <td><?php echo e($pa['submitted_at']); ?></td>
                                            <td class="text-center">
                                                <a href="<?php echo APP_URL; ?>modules/projects/view.php?id=<?php echo (int)$pa['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> مراجعة
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- أحدث المشاريع -->
            <div class="card fade-in">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2 text-primary"></i>أحدث المشاريع</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>الكود</th>
                                    <th>اسم المشروع</th>
                                    <th>الميزانية</th>
                                    <th>الحالة</th>
                                    <th class="text-center">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentProjects as $rp): 
                                    $badge = ['planned'=>'bg-secondary','active'=>'bg-success','completed'=>'bg-info','under_review'=>'bg-warning text-dark','closed'=>'bg-dark','reopened'=>'bg-primary','cancelled'=>'bg-danger'][$rp['status']] ?? 'bg-secondary';
                                    $label = ['planned'=>'مخطط','active'=>'قيد التنفيذ','completed'=>'منجز','under_review'=>'مراجعة ختامية','closed'=>'مغلق','reopened'=>'معاد فتحه','cancelled'=>'ملغي'][$rp['status']] ?? $rp['status'];
                                ?>
                                    <tr>
                                        <td><code><?php echo e($rp['project_code']); ?></code></td>
                                        <td><strong><?php echo e($rp['name']); ?></strong></td>
                                        <td><?php echo number_format((float)$rp['target_amount'], 0); ?> ج.س</td>
                                        <td><span class="badge <?php echo $badge; ?>"><?php echo e($label); ?></span></td>
                                        <td class="text-center">
                                            <a href="<?php echo APP_URL; ?>modules/projects/view.php?id=<?php echo (int)$rp['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php else: // project_supervisor ?>
            <!-- المشاريع المكلفة -->
            <div class="card fade-in mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-briefcase me-2 text-primary"></i>مشاريعي المكلفة</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($assignedProjects)): ?>
                        <p class="text-muted text-center py-4 mb-0">لم يتم تكليفك بأي مشاريع حتى الآن.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>الكود</th>
                                        <th>اسم المشروع</th>
                                        <th>الحالة</th>
                                        <th class="text-center">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignedProjects as $ap): 
                                        $badge = ['planned'=>'bg-secondary','active'=>'bg-success','completed'=>'bg-info','under_review'=>'bg-warning text-dark','closed'=>'bg-dark','reopened'=>'bg-primary','cancelled'=>'bg-danger'][$ap['status']] ?? 'bg-secondary';
                                        $label = ['planned'=>'مخطط','active'=>'قيد التنفيذ','completed'=>'منجز','under_review'=>'مراجعة ختامية','closed'=>'مغلق','reopened'=>'معاد فتحه','cancelled'=>'ملغي'][$ap['status']] ?? $ap['status'];
                                    ?>
                                        <tr>
                                            <td><code><?php echo e($ap['project_code']); ?></code></td>
                                            <td><strong><?php echo e($ap['name']); ?></strong></td>
                                            <td><span class="badge <?php echo $badge; ?>"><?php echo e($label); ?></span></td>
                                            <td class="text-center">
                                                <a href="<?php echo APP_URL; ?>modules/projects/view.php?id=<?php echo (int)$ap['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> عرض
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- العمالة والمهام التشغيلية -->
            <div class="card fade-in">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-hard-hat me-2 text-warning"></i>العمالة والمساعدون النشطون</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingLabor)): ?>
                        <p class="text-muted text-center py-4 mb-0">لا توجد سجلات عمالة نشطة تتطلب متابعتك.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>المشروع</th>
                                        <th>مقدم الخدمة</th>
                                        <th>وصف العمل</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingLabor as $pl): ?>
                                        <tr>
                                            <td><small><?php echo e($pl['project_name']); ?></small></td>
                                            <td><strong><?php echo e($pl['provider_name']); ?></strong></td>
                                            <td><?php echo e($pl['work_description']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $pl['status'] === 'in_progress' ? 'bg-info' : 'bg-secondary'; ?>">
                                                    <?php echo $pl['status'] === 'in_progress' ? 'قيد التنفيذ' : 'مخطط'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- العمود الجانبي (إجراءات سريعة وتنبيهات) -->
    <div class="col-lg-4">
        <div class="card fade-in mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bolt me-2 text-warning"></i>إجراءات سريعة</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($role === 'projects_manager'): ?>
                        <a href="<?php echo APP_URL; ?>modules/projects/form.php" class="btn btn-outline-primary">
                            <i class="fas fa-plus me-2"></i> إنشاء مشروع جديد
                        </a>
                        <a href="<?php echo APP_URL; ?>modules/projects/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-chart-pie me-2"></i> تقرير المحفظة المالية
                        </a>
                    <?php else: ?>
                        <a href="<?php echo APP_URL; ?>modules/projects/index.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i> عرض جميع مشاريعي
                        </a>
                        <div class="alert alert-info small mb-0 mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            يمكنك إضافة تحديثات التقدم، تسجيل العمالة الخارجية، وإدارة المستفيدين من خلال صفحة تفاصيل كل مشروع.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($role === 'projects_manager'): ?>
            <div class="card fade-in">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bell me-2 text-danger"></i>تنبيهات النظام</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-exclamation-triangle text-warning mt-1 me-2"></i>
                                <div>
                                    <strong>مراجعة الميزانيات</strong>
                                    <p class="small text-muted mb-0">تأكد من اعتماد جميع نسخ الميزانيات للمشاريع النشطة قبل بدء الصرف.</p>
                                </div>
                            </div>
                        </li>
                        <li class="mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-file-invoice text-info mt-1 me-2"></i>
                                <div>
                                    <strong>المصروفات المعلقة</strong>
                                    <p class="small text-muted mb-0">يوجد مصروفات بانتظار الاعتماد المحاسبي في قسم المالية.</p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <div class="card fade-in">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2 text-success"></i>تلميحات للمشرف</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>سجل بيانات العمالة الخارجية فور بدء العمل.</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>حدث نسبة الإنجاز أسبوعياً في قسم "التشغيل والتقدم".</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>أرفق جميع الإيصالات والفواتير في قسم الوثائق قبل طلب الصرف.</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>