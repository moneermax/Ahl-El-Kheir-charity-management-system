<?php
// modules/system/database.php - Read-only schema browser (Admin only)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = 'قاعدة البيانات';
$active    = 'database';

$names = array_column(dbFetchAll(
    "SELECT TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME"
), 't');

$tables = [];
foreach ($names as $n) {
    $c = (int)(dbFetchOne("SELECT COUNT(*) c FROM `$n`")['c'] ?? 0);
    $tables[] = ['name' => $n, 'count' => $c];
}

$sel = preg_replace('/[^a-z0-9_]/', '', (string)($_GET['table'] ?? ''));
if ($sel !== '' && !in_array($sel, $names, true)) $sel = '';
$cols = []; $rows = [];
if ($sel !== '') {
    $cols = dbFetchAll("SHOW COLUMNS FROM `$sel`");
    $rows = dbFetchAll("SELECT * FROM `$sel` ORDER BY 1 DESC LIMIT 50");
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>قاعدة البيانات</h2>
    <p>متصفح قراءة فقط للجداول والسجلات (آخر 50 سجل بكل جدول)</p>
</div>

<div class="row g-4">
    <div class="col-md-3">
        <div class="card fade-in">
            <div class="card-header"><i class="fas fa-table me-2"></i>الجداول (<?php echo count($tables); ?>)</div>
            <div class="list-group list-group-flush">
                <?php foreach ($tables as $t): ?>
                    <a href="<?php echo APP_URL; ?>modules/system/database.php?table=<?php echo e($t['name']); ?>"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $sel === $t['name'] ? 'active' : ''; ?>">
                        <code><?php echo e($t['name']); ?></code>
                        <span class="badge bg-primary"><?php echo $t['count']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <?php if ($sel === ''): ?>
            <div class="alert alert-secondary fade-in">اختر جدولاً من القائمة لعرض هيكله وبياناته.</div>
        <?php else: ?>
            <div class="card mb-4 fade-in">
                <div class="card-header"><i class="fas fa-columns me-2"></i>هيكل الجدول: <code><?php echo e($sel); ?></code></div>
                <div class="card-body table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>الحقل</th><th>النوع</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead>
                        <tbody>
                        <?php foreach ($cols as $c): ?>
                            <tr>
                                <td><code><?php echo e($c['Field']); ?></code></td>
                                <td><small><?php echo e($c['Type']); ?></small></td>
                                <td><?php echo e($c['Null']); ?></td>
                                <td><?php echo e($c['Key']); ?></td>
                                <td><?php echo e((string)($c['Default'] ?? '')); ?></td>
                                <td><small><?php echo e($c['Extra']); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card fade-in">
                <div class="card-header"><i class="fas fa-database me-2"></i>آخر 50 سجل</div>
                <div class="card-body table-responsive" style="max-height:480px;overflow:auto">
                    <table class="table table-sm table-hover align-middle">
                        <thead><tr><?php foreach ($cols as $c): ?><th><small><?php echo e($c['Field']); ?></small></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="<?php echo count($cols); ?>" class="text-center text-muted py-3">لا توجد سجلات.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr><?php foreach ($cols as $c): ?>
                                <td><small><?php $v = $r[$c['Field']] ?? ''; echo e(mb_strtolower((string)$v) === 'null' ? '' : (string)$v); ?></small></td>
                            <?php endforeach; ?></tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>