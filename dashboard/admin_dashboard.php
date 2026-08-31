<<<<<<< HEAD
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'لوحة التحكم التقنية';
$active = 'dashboard';

include __DIR__ . '/../includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2>مرحباً، <?php echo e(current_user_name()); ?></h2>
    <p>هذه لوحة تحكم تقنية خاصة بالمطور / مدير النظام، وليست لوحة الإدارة التنظيمية.</p>

    <div class="quick-actions mt-3">
        <a href="<?php echo url('modules/system/backup.php'); ?>" class="btn btn-light btn-sm">
            <i class="fas fa-database me-1"></i>
            نسخة احتياطية
        </a>

        <a href="<?php echo url('modules/system/database.php'); ?>" class="btn btn-light btn-sm">
            <i class="fas fa-table me-1"></i>
            قاعدة البيانات
        </a>

        <a href="<?php echo url('modules/logs/audit.php'); ?>" class="btn btn-light btn-sm">
            <i class="fas fa-file-lines me-1"></i>
            السجلات
        </a>

        <a href="<?php echo url('modules/settings/index.php'); ?>" class="btn btn-light btn-sm">
            <i class="fas fa-gear me-1"></i>
            الإعدادات
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo PHP_VERSION; ?></div>
                    <div class="stat-label">إصدار PHP</div>
                </div>
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="fab fa-php"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number">MySQL</div>
                    <div class="stat-label">قاعدة البيانات</div>
                </div>
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="fas fa-database"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number">Admin</div>
                    <div class="stat-label">نوع الحساب</div>
                </div>
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo date('H:i'); ?></div>
                    <div class="stat-label">وقت الخادم</div>
                </div>
                <div class="stat-icon bg-danger-subtle text-danger">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-circle-info me-2"></i>
        معلومات النظام
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <tbody>
                    <tr>
                        <th style="width: 250px;">اسم التطبيق</th>
                        <td><?php echo e(APP_NAME); ?></td>
                    </tr>
                    <tr>
                        <th>مسار التطبيق</th>
                        <td><?php echo e(APP_DIR); ?></td>
                    </tr>
                    <tr>
                        <th>رابط التطبيق</th>
                        <td><?php echo e(APP_URL); ?></td>
                    </tr>
                    <tr>
                        <th>قاعدة البيانات</th>
                        <td><?php echo e(DB_NAME); ?></td>
                    </tr>
                    <tr>
                        <th>مستخدم قاعدة البيانات</th>
                        <td><?php echo e(DB_USER); ?></td>
                    </tr>
                    <tr>
                        <th>مجلد النسخ الاحتياطية</th>
                        <td><?php echo e(BACKUP_DIR); ?></td>
                    </tr>
                    <tr>
                        <th>مجلد السجلات</th>
                        <td><?php echo e(LOG_DIR); ?></td>
                    </tr>
                    <tr>
                        <th>المستخدم الحالي</th>
                        <td><?php echo e(current_user_name()); ?> (<?php echo e(current_user_role()); ?>)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/age_alert.php'; ?>
=======
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'لوحة التحكم التقنية';
$active = 'dashboard';

include __DIR__ . '/../includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2>مرحباً، <?php echo e(current_user_name()); ?></h2>
    <p>هذه لوحة تحكم تقنية خاصة بالمطور / مدير النظام، وليست لوحة الإدارة التنظيمية.</p>

    <div class="quick-actions mt-3">
        <a href="<?php echo url('modules/system/backup.php'); ?>" class="btn btn-light btn-sm">
            <i class="fas fa-database me-1"></i>
            نسخة احتياطية
        </a>

        <a href="<?php echo url('modules/system/database.php'); ?>" class="btn btn-light btn-sm">
            <i class="fas fa-table me-1"></i>
            قاعدة البيانات
        </a>

        <a href="<?php echo url('modules/logs/audit.php'); ?>" class="btn btn-light btn-sm">
            <i class="fas fa-file-lines me-1"></i>
            السجلات
        </a>

        <a href="<?php echo url('modules/settings/index.php'); ?>" class="btn btn-light btn-sm">
            <i class="fas fa-gear me-1"></i>
            الإعدادات
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo PHP_VERSION; ?></div>
                    <div class="stat-label">إصدار PHP</div>
                </div>
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="fab fa-php"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number">MySQL</div>
                    <div class="stat-label">قاعدة البيانات</div>
                </div>
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="fas fa-database"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number">Admin</div>
                    <div class="stat-label">نوع الحساب</div>
                </div>
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo date('H:i'); ?></div>
                    <div class="stat-label">وقت الخادم</div>
                </div>
                <div class="stat-icon bg-danger-subtle text-danger">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-circle-info me-2"></i>
        معلومات النظام
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <tbody>
                    <tr>
                        <th style="width: 250px;">اسم التطبيق</th>
                        <td><?php echo e(APP_NAME); ?></td>
                    </tr>
                    <tr>
                        <th>مسار التطبيق</th>
                        <td><?php echo e(APP_DIR); ?></td>
                    </tr>
                    <tr>
                        <th>رابط التطبيق</th>
                        <td><?php echo e(APP_URL); ?></td>
                    </tr>
                    <tr>
                        <th>قاعدة البيانات</th>
                        <td><?php echo e(DB_NAME); ?></td>
                    </tr>
                    <tr>
                        <th>مستخدم قاعدة البيانات</th>
                        <td><?php echo e(DB_USER); ?></td>
                    </tr>
                    <tr>
                        <th>مجلد النسخ الاحتياطية</th>
                        <td><?php echo e(BACKUP_DIR); ?></td>
                    </tr>
                    <tr>
                        <th>مجلد السجلات</th>
                        <td><?php echo e(LOG_DIR); ?></td>
                    </tr>
                    <tr>
                        <th>المستخدم الحالي</th>
                        <td><?php echo e(current_user_name()); ?> (<?php echo e(current_user_role()); ?>)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/age_alert.php'; ?>
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include __DIR__ . '/../includes/footer.php'; ?>