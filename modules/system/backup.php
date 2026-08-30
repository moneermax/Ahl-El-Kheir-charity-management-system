<?php
// modules/system/backup.php - Database backups via mysqldump (Admin only)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = 'النسخ الاحتياطي';
$active    = 'system';

$backupsDir = dirname(__DIR__, 2) . '/storage/backups';
if (!is_dir($backupsDir)) @mkdir($backupsDir, 0777, true);

/* ---- DB credentials (detect common constant names, XAMPP defaults) ---- */
$dbCfg = [
    'host' => defined('DB_HOST') ? DB_HOST : (defined('DB_SERVER') ? DB_SERVER : '127.0.0.1'),
    'user' => defined('DB_USER') ? DB_USER : (defined('DB_USERNAME') ? DB_USERNAME : 'root'),
    'pass' => defined('DB_PASS') ? DB_PASS : (defined('DB_PASSWORD') ? DB_PASSWORD : ''),
    'name' => defined('DB_NAME') ? DB_NAME : (defined('DB_DATABASE') ? DB_DATABASE : 'ahl_el_kheir'),
];

/* ---- download ---- */
if (isset($_GET['download'])) {
    $f = basename((string)$_GET['download']);
    $p = $backupsDir . '/' . $f;
    if ($f !== '' && str_ends_with($f, '.sql') && is_file($p)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $f . '"');
        header('Content-Length: ' . (string)filesize($p));
        readfile($p);
        exit();
    }
    flash('error', 'الملف غير موجود.');
    header('Location: ' . APP_URL . 'modules/system/backup.php'); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة.');
    } elseif (isset($_POST['create_backup'])) {
        $file = $backupsDir . '/backup_' . date('Ymd_His') . '.sql';
        $mysqldump = is_file('D:/xampp/mysql/bin/mysqldump.exe') ? 'D:/xampp/mysql/bin/mysqldump.exe' : 'mysqldump';
        $cmd = escapeshellarg($mysqldump)
            . ' --host=' . escapeshellarg($dbCfg['host'])
            . ' --user=' . escapeshellarg($dbCfg['user'])
            . ($dbCfg['pass'] !== '' ? ' --password=' . escapeshellarg($dbCfg['pass']) : '')
            . ' --default-character-set=utf8mb4 --single-transaction --routines --triggers '
            . escapeshellarg($dbCfg['name'])
            . ' > ' . escapeshellarg($file) . ' 2>&1';
        exec($cmd, $out, $ret);
        if ($ret === 0 && is_file($file) && filesize($file) > 500) {
            dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                       VALUES (?, 'EXPORT', 'database', NULL, NULL, ?, ?, ?)",
                [Session::getUserId(), json_encode(['file' => basename($file), 'size' => filesize($file)], JSON_UNESCAPED_UNICODE),
                 $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            flash('success', 'تم إنشاء النسخة الاحتياطية: ' . basename($file));
        } else {
            flash('error', 'فشل إنشاء النسخة. تأكد من مسار mysqldump وصلاحيات MySQL. خرج الأمر: ' . implode(' ', $out));
        }
    } elseif (isset($_POST['delete_backup'])) {
        $f = basename((string)$_POST['delete_backup']);
        $p = $backupsDir . '/' . $f;
        if (str_ends_with($f, '.sql') && is_file($p)) {
            @unlink($p);
            dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                       VALUES (?, 'DELETE', 'backup', NULL, NULL, ?, ?, ?)",
                [Session::getUserId(), json_encode(['file' => $f], JSON_UNESCAPED_UNICODE),
                 $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            flash('success', 'تم حذف النسخة: ' . $f);
        }
    }
    header('Location: ' . APP_URL . 'modules/system/backup.php'); exit();
}

$backups = glob($backupsDir . '/*.sql') ?: [];
usort($backups, fn($a, $b) => filemtime($b) <=> filemtime($a));

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>النسخ الاحتياطي</h2>
    <p>قاعدة البيانات: <code><?php echo e($dbCfg['name']); ?></code> · النسخ عبر mysqldump تُحفظ في storage/backups</p>
    <div class="quick-actions mt-3">
        <form method="post"><?php echo csrf_field(); ?>
            <button name="create_backup" value="1" class="btn btn-primary btn-sm" onclick="return confirm('إنشاء نسخة احتياطية الآن؟')">
                <i class="fas fa-database me-1"></i> إنشاء نسخة الآن
            </button>
        </form>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="card fade-in">
    <div class="card-header"><i class="fas fa-archive me-2"></i>النسخ المتوفرة (<?php echo count($backups); ?>)</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>الملف</th><th>الحجم</th><th>التاريخ</th><th class="text-center">إجراءات</th></tr></thead>
                <tbody>
                <?php if (!$backups): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">لا توجد نسخ احتياطية بعد.</td></tr>
                <?php else: foreach ($backups as $b): ?>
                    <tr>
                        <td><code><?php echo e(basename($b)); ?></code></td>
                        <td><?php echo number_format(filesize($b) / 1024, 1); ?> KB</td>
                        <td><?php echo date('Y-m-d H:i:s', filemtime($b)); ?></td>
                        <td class="text-center" style="white-space:nowrap;">
                            <a class="btn btn-sm btn-primary" href="<?php echo APP_URL; ?>modules/system/backup.php?download=<?php echo e(basename($b)); ?>"><i class="fas fa-download"></i></a>
                            <form method="post" class="d-inline"><?php echo csrf_field(); ?>
                                <button name="delete_backup" value="<?php echo e(basename($b)); ?>" class="btn btn-sm btn-danger"
                                        onclick="return confirm('حذف <?php echo e(basename($b)); ?>؟')"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>