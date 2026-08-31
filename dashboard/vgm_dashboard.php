<<<<<<< HEAD
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($supervisors)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">لا يوجد مشرفون حتى الآن</td>
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
=======
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($supervisors)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">لا يوجد مشرفون حتى الآن</td>
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
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include __DIR__ . '/../includes/footer.php'; ?>