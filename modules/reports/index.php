<<<<<<< HEAD
<?php
// modules/reports/index.php - Reports Center (Role-Based)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$role = Session::getUserRole();
$uid = Session::getUserId();

// تعريف التبويبات والملفات المرتبطة بها والصلاحيات
$all_tabs = [
    'overview'    => ['label' => 'نظرة عامة', 'file' => 'index.php', 'roles' => ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'accountant', 'financial_manager', 'nanny', 'hr_manager', 'hr_staff', 'accountant_staff']],
    'financial'   => ['label' => 'التقارير المالية', 'file' => 'financial.php', 'roles' => ['admin', 'general_manager', 'vice_general_manager', 'accountant', 'financial_manager', 'accountant_staff']],
    'sponsorship' => ['label' => 'تقارير الكفالات', 'file' => 'sponsorship.php', 'roles' => ['admin', 'general_manager', 'vice_general_manager', 'supervisor']],
    'operational' => ['label' => 'التقارير التشغيلية', 'file' => 'operational.php', 'roles' => ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny', 'financial_manager']],
    'hr'          => ['label' => 'تقارير الموارد البشرية', 'file' => 'hr.php', 'roles' => ['admin', 'general_manager', 'vice_general_manager', 'hr_manager', 'hr_staff']],
    'orphaned'    => ['label' => 'الأسر بلا كفالة', 'file' => 'orphaned_families.php', 'roles' => ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny']]
];

// فلترة التبويبات حسب صلاحية المستخدم الحالي
$allowed_tabs = [];
foreach ($all_tabs as $key => $tab_info) {
    if (in_array($role, $tab_info['roles'], true)) {
        $allowed_tabs[$key] = $tab_info;
    }
}

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));

$pageTitle = 'مركز التقارير';
$active = 'reports';

// جلب الإحصائيات العامة
$stats = [];
$stats['sponsors'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE status='active'")['c'] ?? 0);
$stats['families'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM families WHERE status='active'")['c'] ?? 0);
$stats['active_sponsorships'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsorships WHERE status='active'")['c'] ?? 0);
$stats['monthly_commitment'] = (float)(dbFetchOne("SELECT COALESCE(SUM(monthly_amount), 0) AS s FROM sponsorships WHERE status='active'")['s'] ?? 0);

try {
    $stats['orphaned_families'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM vw_families_without_sponsorship")['c'] ?? 0);
} catch (Exception $e) {
    $stats['orphaned_families'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM families f WHERE f.status IN ('active','pending') AND f.children_count > 0 AND NOT EXISTS (SELECT 1 FROM sponsorships sp JOIN family_children fc ON sp.child_id = fc.id WHERE fc.family_id = f.id AND sp.status = 'active')")['c'] ?? 0);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-chart-pie me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted">تقارير تشغيلية ومالية لدعم اتخاذ القرار — <?php echo e($from); ?> → <?php echo e($to); ?></p>
</div>

<!-- نموذج فلترة التاريخ -->
<div class="card mb-4 fade-in shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">من تاريخ</label>
                <input type="date" name="from" class="form-control" value="<?php echo e($from); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">إلى تاريخ</label>
                <input type="date" name="to" class="form-control" value="<?php echo e($to); ?>" required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100" style="background-color: #1b4d8f; border-color: #1b4d8f;">
                    <i class="fas fa-filter me-1"></i> تطبيق الفلتر
                </button>
            </div>
        </form>
    </div>
</div>

<!-- تبويبات التنقل (كل تبويب يفتح ملفه الخاص) -->
<ul class="nav nav-pills mb-4 fade-in flex-wrap gap-2">
    <?php foreach ($allowed_tabs as $key => $tab_info): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $key === 'overview' ? 'active' : ''; ?>" 
               href="<?php echo APP_URL; ?>modules/reports/<?php echo e($tab_info['file']); ?>?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>"
               style="<?php echo $key === 'overview' ? 'background-color: #1b4d8f; color: white;' : 'color: #1b4d8f; background-color: #f8f9fa;'; ?>">
                <i class="fas fa-<?php echo $key === 'overview' ? 'chart-line' : ($key === 'financial' ? 'coins' : ($key === 'hr' ? 'users-cog' : 'file-alt')); ?> me-1"></i>
                <?php echo e($tab_info['label']); ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<!-- محتوى نظرة عامة -->
<div class="fade-in">
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-right: 4px solid #1b4d8f !important;">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-primary mb-2"><?php echo number_format($stats['sponsors']); ?></div>
                    <div class="text-muted small fw-bold">كفيل نشط</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-right: 4px solid #198754 !important;">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-success mb-2"><?php echo number_format($stats['active_sponsorships']); ?></div>
                    <div class="text-muted small fw-bold">كفالة نشطة</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-right: 4px solid #fd7e14 !important;">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-warning mb-2"><?php echo number_format($stats['families']); ?></div>
                    <div class="text-muted small fw-bold">أسرة مستفيدة</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-right: 4px solid #6f42c1 !important;">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold" style="color: #6f42c1; font-size: 1.8rem;"><?php echo number_format($stats['monthly_commitment'], 0); ?> <span class="fs-6">ج.س</span></div>
                    <div class="text-muted small fw-bold">الالتزام الشهري</div>
                </div>
            </div>
        </div>
    </div>

    <!-- قسم الإجراءات السريعة -->
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
            <i class="fas fa-folder-open me-2"></i> انتقل إلى التقارير التفصيلية
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php if (isset($allowed_tabs['financial'])): ?>
                <div class="col-md-4">
                    <a href="<?php echo APP_URL; ?>modules/reports/financial.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="text-decoration-none">
                        <div class="p-3 border rounded hover-shadow text-center" style="transition: all 0.2s;">
                            <i class="fas fa-coins fa-2x mb-2 text-primary"></i>
                            <h6 class="mb-0 fw-bold">التقارير المالية</h6>
                            <small class="text-muted">الإيرادات، المصروفات، وأرصدة الخزائن</small>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isset($allowed_tabs['sponsorship'])): ?>
                <div class="col-md-4">
                    <a href="<?php echo APP_URL; ?>modules/reports/sponsorship.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="text-decoration-none">
                        <div class="p-3 border rounded hover-shadow text-center" style="transition: all 0.2s;">
                            <i class="fas fa-hand-holding-heart fa-2x mb-2 text-success"></i>
                            <h6 class="mb-0 fw-bold">تقارير الكفالات</h6>
                            <small class="text-muted">نسب الاحتفاظ، وتوزيع الكفلاء</small>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isset($allowed_tabs['operational'])): ?>
                <div class="col-md-4">
                    <a href="<?php echo APP_URL; ?>modules/reports/operational.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="text-decoration-none">
                        <div class="p-3 border rounded hover-shadow text-center" style="transition: all 0.2s;">
                            <i class="fas fa-tasks fa-2x mb-2 text-info"></i>
                            <h6 class="mb-0 fw-bold">التقارير التشغيلية</h6>
                            <small class="text-muted">أداء المشرفين والكافلات</small>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isset($allowed_tabs['orphaned'])): ?>
                <div class="col-md-4">
                    <a href="<?php echo APP_URL; ?>modules/reports/orphaned_families.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="text-decoration-none">
                        <div class="p-3 border rounded hover-shadow text-center" style="transition: all 0.2s;">
                            <i class="fas fa-users-slash fa-2x mb-2 text-danger"></i>
                            <h6 class="mb-0 fw-bold">الأسر بلا كفالة</h6>
                            <small class="text-muted"><?php echo number_format($stats['orphaned_families']); ?> أسرة بانتظار الدعم</small>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isset($allowed_tabs['hr'])): ?>
                <div class="col-md-4">
                    <a href="<?php echo APP_URL; ?>modules/reports/hr.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="text-decoration-none">
                        <div class="p-3 border rounded hover-shadow text-center" style="transition: all 0.2s;">
                            <i class="fas fa-users-cog fa-2x mb-2 text-secondary"></i>
                            <h6 class="mb-0 fw-bold">تقارير الموارد البشرية</h6>
                            <small class="text-muted">الموظفين، الحضور، والرواتب</small>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-shadow:hover {
        box-shadow: 0 0.5rem 1rem rgba(27, 77, 143, 0.15) !important;
        transform: translateY(-2px);
        border-color: #1b4d8f !important;
    }
</style>

=======
<?php
// modules/reports/index.php - Reports Center (Role-Based)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$role = Session::getUserRole();
$uid = Session::getUserId();

// تعريف التبويبات والملفات المرتبطة بها والصلاحيات
$all_tabs = [
    'overview'    => ['label' => 'نظرة عامة', 'file' => 'index.php', 'roles' => ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'accountant', 'financial_manager', 'nanny', 'hr_manager', 'hr_staff', 'accountant_staff']],
    'financial'   => ['label' => 'التقارير المالية', 'file' => 'financial.php', 'roles' => ['admin', 'general_manager', 'vice_general_manager', 'accountant', 'financial_manager', 'accountant_staff']],
    'sponsorship' => ['label' => 'تقارير الكفالات', 'file' => 'sponsorship.php', 'roles' => ['admin', 'general_manager', 'vice_general_manager', 'supervisor']],
    'operational' => ['label' => 'التقارير التشغيلية', 'file' => 'operational.php', 'roles' => ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny', 'financial_manager']],
    'hr'          => ['label' => 'تقارير الموارد البشرية', 'file' => 'hr.php', 'roles' => ['admin', 'general_manager', 'vice_general_manager', 'hr_manager', 'hr_staff']],
    'orphaned'    => ['label' => 'الأسر بلا كفالة', 'file' => 'orphaned_families.php', 'roles' => ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny']]
];

// فلترة التبويبات حسب صلاحية المستخدم الحالي
$allowed_tabs = [];
foreach ($all_tabs as $key => $tab_info) {
    if (in_array($role, $tab_info['roles'], true)) {
        $allowed_tabs[$key] = $tab_info;
    }
}

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));

$pageTitle = 'مركز التقارير';
$active = 'reports';

// جلب الإحصائيات العامة
$stats = [];
$stats['sponsors'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE status='active'")['c'] ?? 0);
$stats['families'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM families WHERE status='active'")['c'] ?? 0);
$stats['active_sponsorships'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsorships WHERE status='active'")['c'] ?? 0);
$stats['monthly_commitment'] = (float)(dbFetchOne("SELECT COALESCE(SUM(monthly_amount), 0) AS s FROM sponsorships WHERE status='active'")['s'] ?? 0);

try {
    $stats['orphaned_families'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM vw_families_without_sponsorship")['c'] ?? 0);
} catch (Exception $e) {
    $stats['orphaned_families'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM families f WHERE f.status IN ('active','pending') AND f.children_count > 0 AND NOT EXISTS (SELECT 1 FROM sponsorships sp JOIN family_children fc ON sp.child_id = fc.id WHERE fc.family_id = f.id AND sp.status = 'active')")['c'] ?? 0);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-chart-pie me-2"></i><?php echo e($pageTitle); ?></h2>
    <p class="text-muted">تقارير تشغيلية ومالية لدعم اتخاذ القرار — <?php echo e($from); ?> → <?php echo e($to); ?></p>
</div>

<!-- نموذج فلترة التاريخ -->
<div class="card mb-4 fade-in shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">من تاريخ</label>
                <input type="date" name="from" class="form-control" value="<?php echo e($from); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">إلى تاريخ</label>
                <input type="date" name="to" class="form-control" value="<?php echo e($to); ?>" required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100" style="background-color: #1b4d8f; border-color: #1b4d8f;">
                    <i class="fas fa-filter me-1"></i> تطبيق الفلتر
                </button>
            </div>
        </form>
    </div>
</div>

<!-- تبويبات التنقل (كل تبويب يفتح ملفه الخاص) -->
<ul class="nav nav-pills mb-4 fade-in flex-wrap gap-2">
    <?php foreach ($allowed_tabs as $key => $tab_info): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $key === 'overview' ? 'active' : ''; ?>" 
               href="<?php echo APP_URL; ?>modules/reports/<?php echo e($tab_info['file']); ?>?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>"
               style="<?php echo $key === 'overview' ? 'background-color: #1b4d8f; color: white;' : 'color: #1b4d8f; background-color: #f8f9fa;'; ?>">
                <i class="fas fa-<?php echo $key === 'overview' ? 'chart-line' : ($key === 'financial' ? 'coins' : ($key === 'hr' ? 'users-cog' : 'file-alt')); ?> me-1"></i>
                <?php echo e($tab_info['label']); ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<!-- محتوى نظرة عامة -->
<div class="fade-in">
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-right: 4px solid #1b4d8f !important;">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-primary mb-2"><?php echo number_format($stats['sponsors']); ?></div>
                    <div class="text-muted small fw-bold">كفيل نشط</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-right: 4px solid #198754 !important;">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-success mb-2"><?php echo number_format($stats['active_sponsorships']); ?></div>
                    <div class="text-muted small fw-bold">كفالة نشطة</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-right: 4px solid #fd7e14 !important;">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-warning mb-2"><?php echo number_format($stats['families']); ?></div>
                    <div class="text-muted small fw-bold">أسرة مستفيدة</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-right: 4px solid #6f42c1 !important;">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold" style="color: #6f42c1; font-size: 1.8rem;"><?php echo number_format($stats['monthly_commitment'], 0); ?> <span class="fs-6">ج.س</span></div>
                    <div class="text-muted small fw-bold">الالتزام الشهري</div>
                </div>
            </div>
        </div>
    </div>

    <!-- قسم الإجراءات السريعة -->
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold" style="color: #1b4d8f;">
            <i class="fas fa-folder-open me-2"></i> انتقل إلى التقارير التفصيلية
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php if (isset($allowed_tabs['financial'])): ?>
                <div class="col-md-4">
                    <a href="<?php echo APP_URL; ?>modules/reports/financial.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="text-decoration-none">
                        <div class="p-3 border rounded hover-shadow text-center" style="transition: all 0.2s;">
                            <i class="fas fa-coins fa-2x mb-2 text-primary"></i>
                            <h6 class="mb-0 fw-bold">التقارير المالية</h6>
                            <small class="text-muted">الإيرادات، المصروفات، وأرصدة الخزائن</small>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isset($allowed_tabs['sponsorship'])): ?>
                <div class="col-md-4">
                    <a href="<?php echo APP_URL; ?>modules/reports/sponsorship.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="text-decoration-none">
                        <div class="p-3 border rounded hover-shadow text-center" style="transition: all 0.2s;">
                            <i class="fas fa-hand-holding-heart fa-2x mb-2 text-success"></i>
                            <h6 class="mb-0 fw-bold">تقارير الكفالات</h6>
                            <small class="text-muted">نسب الاحتفاظ، وتوزيع الكفلاء</small>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isset($allowed_tabs['operational'])): ?>
                <div class="col-md-4">
                    <a href="<?php echo APP_URL; ?>modules/reports/operational.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="text-decoration-none">
                        <div class="p-3 border rounded hover-shadow text-center" style="transition: all 0.2s;">
                            <i class="fas fa-tasks fa-2x mb-2 text-info"></i>
                            <h6 class="mb-0 fw-bold">التقارير التشغيلية</h6>
                            <small class="text-muted">أداء المشرفين والكافلات</small>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isset($allowed_tabs['orphaned'])): ?>
                <div class="col-md-4">
                    <a href="<?php echo APP_URL; ?>modules/reports/orphaned_families.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="text-decoration-none">
                        <div class="p-3 border rounded hover-shadow text-center" style="transition: all 0.2s;">
                            <i class="fas fa-users-slash fa-2x mb-2 text-danger"></i>
                            <h6 class="mb-0 fw-bold">الأسر بلا كفالة</h6>
                            <small class="text-muted"><?php echo number_format($stats['orphaned_families']); ?> أسرة بانتظار الدعم</small>
                        </div>
                    </a>
                </div>
                <?php endif; ?>

                <?php if (isset($allowed_tabs['hr'])): ?>
                <div class="col-md-4">
                    <a href="<?php echo APP_URL; ?>modules/reports/hr.php?from=<?php echo e($from); ?>&to=<?php echo e($to); ?>" class="text-decoration-none">
                        <div class="p-3 border rounded hover-shadow text-center" style="transition: all 0.2s;">
                            <i class="fas fa-users-cog fa-2x mb-2 text-secondary"></i>
                            <h6 class="mb-0 fw-bold">تقارير الموارد البشرية</h6>
                            <small class="text-muted">الموظفين، الحضور، والرواتب</small>
                        </div>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-shadow:hover {
        box-shadow: 0 0.5rem 1rem rgba(27, 77, 143, 0.15) !important;
        transform: translateY(-2px);
        border-color: #1b4d8f !important;
    }
</style>

>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>