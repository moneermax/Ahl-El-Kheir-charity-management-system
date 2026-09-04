<?php
// modules/logs/system.php - System logs viewer (Admin only)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = t('admin.logs');
$active    = 'logs';

$logsDir = dirname(__DIR__, 2) . '/storage/logs';
$files = glob($logsDir . '/*.log') ?: [];
usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

$sel = basename((string)($_GET['log'] ?? ''));
if ($sel !== '' && !in_array($logsDir . '/' . $sel, $files, true)) $sel = '';
if ($sel === '' && $files) $sel = basename($files[0]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_log']) && $sel !== '') {
    if (verify_csrf()) {
        @file_put_contents($logsDir . '/' . $sel, '');
        try {
            dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                       VALUES (?, 'DELETE', 'logs', NULL, NULL, ?, ?, ?)",
                [Session::getUserId(), json_encode(['file' => $sel], JSON_UNESCAPED_UNICODE),
                 $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
        } catch (Throwable $e) {}
        flash('success', t('common.completed') . ': ' . $sel);
    }
    header('Location: ' . APP_URL . 'modules/logs/system.php'); exit();
}

$lines = [];
if ($sel !== '' && is_file($logsDir . '/' . $sel)) {
    $all = @file($logsDir . '/' . $sel, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_slice($all, -300);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><?php echo e(t('admin.logs')); ?></h2>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="card mb-3 fade-in">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label"><?php echo e(t('common.documents')); ?></label>
                <select name="log" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($files as $f): ?>
                        <option value="<?php echo e(basename($f)); ?>" <?php echo basename($f) === $sel ? 'selected' : ''; ?>>
                            <?php echo e(basename($f)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($sel !== ''): ?>
            <div class="col-md-3">
                <form method="post"><?php echo csrf_field(); ?>
                    <input type="hidden" name="log" value="<?php echo e($sel); ?>">
                    <button name="clear_log" value="1" class="btn btn-outline-danger w-100" data-confirm="<?php echo e(t('common.confirm')); ?>">
                        <i class="fas fa-eraser me-1"></i> <?php echo e(t('common.clear')); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card fade-in">
    <div class="card-header"><i class="fas fa-file-lines me-2"></i><?php echo e($sel ?: '—'); ?></div>
    <div class="card-body">
        <?php if (!$lines): ?>
            <div class="text-muted"><?php echo e(t('common.no_data')); ?></div>
        <?php else: ?>
            <pre dir="ltr" style="max-height:520px;overflow:auto;background:#0a1f44;color:#d8e0ee;border-radius:10px;padding:1rem;font-size:.8rem"><?php foreach ($lines as $l) echo e($l) . "\n"; ?></pre>
        <?php endif; ?>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>