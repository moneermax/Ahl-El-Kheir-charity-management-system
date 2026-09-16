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

$pageTitle = t('admin.dashboard_title');
$active = 'dashboard';

include __DIR__ . '/../includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2><?php echo e(t('admin.welcome', ['name' => current_user_name()])); ?></h2>
    <p><?php echo e(t('admin.dashboard_description')); ?></p>

    <div class="quick-actions mt-3">
        <a href="<?php echo url('modules/transactions/fina_payment_create.php'); ?>" class="btn btn-warning btn-sm">
            <i class="fas fa-hand-holding-dollar me-1"></i>
            تحصيل فينا الخير
        </a>

        <a href="<?php echo url('modules/system/backup.php'); ?>" class="btn btn-light btn-sm">
            <i class="fas fa-database me-1"></i>
            <?php echo e(t('admin.backup')); ?>
        </a>

        <a href="<?php echo url('modules/system/database.php'); ?>" class="btn btn-light btn-sm">
            <i class="fas fa-table me-1"></i>
            <?php echo e(t('admin.database')); ?>
        </a>

        <a href="<?php echo url('modules/logs/audit.php'); ?>" class="btn btn-light btn-sm">
            <i class="fas fa-file-lines me-1"></i>
            <?php echo e(t('admin.logs')); ?>
        </a>

        <a href="<?php echo url('modules/settings/index.php'); ?>" class="btn btn-light btn-sm">
            <i class="fas fa-gear me-1"></i>
            <?php echo e(t('admin.settings')); ?>
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo PHP_VERSION; ?></div>
                    <div class="stat-label"><?php echo e(t('admin.php_version')); ?></div>
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
                    <div class="stat-label"><?php echo e(t('admin.database_type')); ?></div>
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
                    <div class="stat-label"><?php echo e(t('admin.account_type')); ?></div>
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
                    <div class="stat-label"><?php echo e(t('admin.server_time')); ?></div>
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
        <?php echo e(t('admin.system_info')); ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <tbody>
                    <tr>
                        <th style="width: 250px;"><?php echo e(t('admin.app_name')); ?></th>
                        <td><?php echo e(APP_NAME); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo e(t('admin.app_path')); ?></th>
                        <td><?php echo e(APP_DIR); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo e(t('admin.app_url')); ?></th>
                        <td><?php echo e(APP_URL); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo e(t('admin.db_name')); ?></th>
                        <td><?php echo e(DB_NAME); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo e(t('admin.db_user')); ?></th>
                        <td><?php echo e(DB_USER); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo e(t('admin.backup_dir')); ?></th>
                        <td><?php echo e(BACKUP_DIR); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo e(t('admin.log_dir')); ?></th>
                        <td><?php echo e(LOG_DIR); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo e(t('admin.current_user')); ?></th>
                        <td><?php echo e(current_user_name()); ?> (<?php echo e(current_user_role()); ?>)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/age_alert.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>