<?php
// modules/system/health.php - System health checks (Admin only)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = 'فحص النظام';
$active    = 'system';

$checks = [];
$checks[] = ['إصدار PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION];
foreach (['pdo_mysql', 'mbstring', 'zip', 'json', 'simplexml'] as $ext) {
    $checks[] = ['امتداد PHP: ' . $ext, extension_loaded($ext), extension_loaded($ext) ? 'محمّل' : 'مفقود'];
}
try {
    dbFetchOne("SELECT 1");
    $checks[] = ['اتصال قاعدة البيانات', true, 'متصل'];
} catch (Throwable $e) {
    $checks[] = ['اتصال قاعدة البيانات', false, $e->getMessage()];
}
$tables = ['users','roles','letters','supervisor_letters','sponsors','families','family_children','sponsorships','sponsorship_children','transactions','settings','audit_log'];
foreach ($tables as $t) {
    try {
        $c = (int)(dbFetchOne("SELECT COUNT(*) c FROM `$t`")['c'] ?? 0);
        $checks[] = ['جدول: ' . $t, true, $c . ' سجل'];
    } catch (Throwable $e) {
        $checks[] = ['جدول: ' . $t, false, 'غير موجود/خطأ'];
    }
}
$root = dirname(__DIR__, 2);
foreach (['storage/backups', 'storage/logs', 'storage/imports'] as $d) {
    $p = $root . '/' . $d;
    $checks[] = ['مجلد قابل للكتابة: ' . $d, is_dir($p) && is_writable($p), is_dir($p) ? 'موجود' : 'مفقود'];
}
$checks[] = ['APP_URL يتضمن المنفذ 8081', str_contains(APP_URL, ':8081'), APP_URL];
$checks[] = ['الجلسة تعمل (Session class)', class_exists('Session', false) && Session::isLoggedIn(), Session::getUserRole()];
$bk = glob($root . '/storage/backups/*.sql') ?: [];
$checks[] = ['نسخة احتياطية موجودة', count($bk) > 0, $bk ? date('Y-m-d H:i', max(array_map('filemtime', $bk))) : 'لا يوجد'];
$checks[] = ['مساحة قرص حرة', true, number_format(disk_free_space($root) / 1073741824, 2) . ' GB'];

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>فحص صحة النظام</h2>
    <p>مراجعة سريعة للبنية التحتية قبل التشغيل اليومي</p>
</div>

<div class="card fade-in">
    <div class="card-body">
        <table class="table align-middle">
            <thead><tr><th>الفحص</th><th>النتيجة</th><th>تفاصيل</th></tr></thead>
            <tbody>
            <?php foreach ($checks as $c): ?>
                <tr>
                    <td><?php echo e($c[0]); ?></td>
                    <td><?php echo $c[1] ? '<span class="badge bg-success">سليم</span>' : '<span class="badge bg-danger">مشكلة</span>'; ?></td>
                    <td><small><?php echo e($c[2]); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>