<?php
// modules/accounting/my_groups.php - Nanny's view of their assigned groups
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'nanny') {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$uid = Session::getUserId();
$pageTitle = 'مجموعاتي';
$active = 'my_groups';

// Get groups assigned to this nanny
$myGroups = dbFetchAll("
    SELECT 
        og.id,
        og.group_name,
        og.description,
        og.is_active,
        og.created_at,
        nga.start_date as assigned_date,
        COUNT(DISTINCT gc.child_id) as orphan_count,
        COUNT(DISTINCT CASE WHEN gc.left_date IS NULL THEN gc.child_id END) as active_orphans
    FROM orphan_groups og
    JOIN nanny_group_assignments nga ON nga.group_id = og.id AND nga.end_date IS NULL
    LEFT JOIN group_children gc ON gc.group_id = og.id
    WHERE nga.nanny_id = ?
    GROUP BY og.id, og.group_name, og.description, og.is_active, og.created_at, nga.start_date
    ORDER BY og.created_at DESC
", [$uid]);

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-users me-2"></i>مجموعاتي</h2>
    <p>عرض المجموعات الم assigned لك وإدارة التوثيق الشهري</p>
    <div class="quick-actions mt-3">
        <a href="<?php echo url('dashboard/nanny_dashboard.php'); ?>" class="btn btn-secondary btn-sm me-2">
            <i class="fas fa-arrow-left me-1"></i> الرجوع للوحة التحكم
        </a>
        <a href="<?php echo url('modules/accounting/disbursements.php'); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-money-check-dollar me-1"></i> التحويلات الشهرية
        </a>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="row g-4">
    <?php if (empty($myGroups)): ?>
        <div class="col-12">
            <div class="card fade-in">
                <div class="card-body text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">لا توجد مجموعات معينة لك حالياً</h5>
                    <p class="text-muted">سيتم إنشاء المجموعات وتعيينك عليها من قبل نائب المدير العام</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($myGroups as $group): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card fade-in h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo e($group['group_name']); ?></h5>
                        <span class="badge bg-<?php echo $group['is_active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $group['is_active'] ? 'نشط' : 'معطل'; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3"><?php echo e($group['description'] ?: 'لا يوجد وصف'); ?></p>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="text-center p-2 bg-light rounded">
                                    <i class="fas fa-child fa-2x text-primary mb-2"></i>
                                    <div class="fs-5 fw-bold"><?php echo (int)$group['active_orphans']; ?></div>
                                    <small class="text-muted">يتيم نشط</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 bg-light rounded">
                                    <i class="fas fa-calendar fa-2x text-success mb-2"></i>
                                    <div class="fs-6"><?php echo date('Y-m', strtotime($group['assigned_date'])); ?></div>
                                    <small class="text-muted">تاريخ التعيين</small>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <a href="<?php echo url('modules/accounting/disbursements.php'); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-money-check-dollar me-1"></i> التحويلات الشهرية
                            </a>
                            <a href="<?php echo url('modules/families/index.php?group=' . $group['id']); ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-list me-1"></i> عرض الأسر
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>