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

$pageTitle = 'لوحة تحكم المدير';
$active = 'dashboard';

/*
|--------------------------------------------------------------------------
| Safe dashboard statistics
|--------------------------------------------------------------------------
| These are intentionally simple control-panel metrics. The dashboard
| must not become a second workflow engine or invent business rules.
|--------------------------------------------------------------------------
*/

$stats = [
    'users' => 0,
    'sponsors' => 0,
    'families' => 0,
    'sponsorships' => 0,
    'settings' => 0,
    'audit_log' => 0,
    'unread_notifications' => 0,
    'pending_recovery' => 0,
];

try {
    $stats['users'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM users")['c'] ?? 0);
} catch (Throwable $e) {}

try {
    $stats['sponsors'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors")['c'] ?? 0);
} catch (Throwable $e) {}

try {
    $stats['families'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM families")['c'] ?? 0);
} catch (Throwable $e) {}

try {
    $stats['sponsorships'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsorships")['c'] ?? 0);
} catch (Throwable $e) {}

try {
    $stats['settings'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM settings")['c'] ?? 0);
} catch (Throwable $e) {}

try {
    $stats['audit_log'] = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM audit_log")['c'] ?? 0);
} catch (Throwable $e) {}

try {
    $stats['unread_notifications'] = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM notifications
         WHERE recipient_user_id = ?
           AND is_read = 0",
        [current_user_id()]
    )['c'] ?? 0);
} catch (Throwable $e) {}

try {
    $stats['pending_recovery'] = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM password_recovery_requests
         WHERE status = 'pending'"
    )['c'] ?? 0);
} catch (Throwable $e) {}

/*
|--------------------------------------------------------------------------
| Basic infrastructure health
|--------------------------------------------------------------------------
*/

$dbOnline = false;
$diskTotal = (float)@disk_total_space(__DIR__);
$diskFree = (float)@disk_free_space(__DIR__);
$diskUsed = max(0, $diskTotal - $diskFree);
$memoryLimit = ini_get('memory_limit') ?: 'غير محدد';
$uploadLimit = ini_get('upload_max_filesize') ?: 'غير محدد';
$executionLimit = ini_get('max_execution_time');
$executionLimit = ($executionLimit === false || $executionLimit === '') ? 'غير محدد' : $executionLimit . ' ثانية';
$httpsEnabled = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';

try {
    dbFetchOne("SELECT 1 AS ok");
    $dbOnline = true;
} catch (Throwable $e) {}

$formatBytes = static function (float $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = min((int)floor(log($bytes, 1024)), count($units) - 1);
    return number_format($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
};

$diskUsedPercent = $diskTotal > 0 ? ($diskUsed / $diskTotal) * 100 : 0;
$recentAudit = [];
try {
    $recentAudit = dbFetchAll(
        "SELECT a.id, a.created_at, a.action, a.entity_type, a.entity_id, u.username
         FROM audit_log a
         LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.id DESC
         LIMIT 6"
    );
} catch (Throwable $e) {}

$systemCards = [
    [
        'label' => 'المستخدمون',
        'value' => number_format($stats['users']),
        'icon' => 'fa-users',
        'class' => 'admin-cyan',
        'url' => url('modules/users/index.php'),
        'action' => 'إدارة المستخدمين',
    ],
    [
        'label' => 'الكفلاء',
        'value' => number_format($stats['sponsors']),
        'icon' => 'fa-hand-holding-heart',
        'class' => 'admin-amber',
        'url' => url('modules/sponsors/index.php'),
        'action' => 'فتح سجل الكفلاء',
    ],
    [
        'label' => 'الأسر',
        'value' => number_format($stats['families']),
        'icon' => 'fa-house-chimney',
        'class' => 'admin-green',
        'url' => url('modules/families/index.php'),
        'action' => 'فتح سجل الأسر',
    ],
    [
        'label' => 'الكفالات',
        'value' => number_format($stats['sponsorships']),
        'icon' => 'fa-file-contract',
        'class' => 'admin-violet',
        'url' => url('modules/sponsorships/index.php'),
        'action' => 'إدارة الكفالات',
    ],
];

$controlGroups = [
    [
        'title' => 'إدارة النظام',
        'subtitle' => 'حسابات المستخدمين والبنية الأساسية',
        'icon' => 'fa-sliders',
        'color' => '#7567c7',
        'items' => [
            ['title' => 'المستخدمون', 'description' => 'إنشاء الحسابات وإدارة المستخدمين', 'icon' => 'fa-users-gear', 'url' => 'modules/users/index.php'],
            ['title' => 'الأقسام', 'description' => 'إدارة أقسام المنظمة', 'icon' => 'fa-building', 'url' => 'modules/departments/index.php'],
            ['title' => 'إعدادات النظام', 'description' => 'بيانات المنظمة والإعدادات العامة', 'icon' => 'fa-gear', 'url' => 'modules/settings/index.php'],
        ],
    ],
    [
        'title' => 'البيانات الأساسية',
        'subtitle' => 'السجلات التشغيلية الرئيسية',
        'icon' => 'fa-database',
        'color' => '#4b78c2',
        'items' => [
            ['title' => 'الكفلاء', 'description' => 'سجل الكفلاء وبياناتهم', 'icon' => 'fa-hand-holding-heart', 'url' => 'modules/sponsors/index.php'],
            ['title' => 'الأسر', 'description' => 'سجل الأسر والبيانات المرتبطة بها', 'icon' => 'fa-house-chimney', 'url' => 'modules/families/index.php'],
            ['title' => 'الكفالات', 'description' => 'سجل الكفالات وعلاقاتها', 'icon' => 'fa-file-contract', 'url' => 'modules/sponsorships/index.php'],
            ['title' => 'التقارير', 'description' => 'مركز التقارير الموحد', 'icon' => 'fa-chart-line', 'url' => 'modules/reports/index.php'],
        ],
    ],
    [
        'title' => 'المالية',
        'subtitle' => 'وصول المدير إلى أدوات المتابعة المالية',
        'icon' => 'fa-coins',
        'color' => '#d88a2d',
        'items' => [
            ['title' => 'لوحة المالية', 'description' => 'ملخص ومؤشرات الإدارة المالية', 'icon' => 'fa-chart-line', 'url' => 'modules/accounting/fm_dashboard.php'],
            ['title' => 'دليل الحسابات', 'description' => 'مراجعة الحسابات المحاسبية', 'icon' => 'fa-sitemap', 'url' => 'modules/accounting/accounts.php'],
            ['title' => 'سجل المعاملات', 'description' => 'متابعة سجل المعاملات المالية', 'icon' => 'fa-money-bill-transfer', 'url' => 'modules/transactions/index.php'],
            ['title' => 'التحويلات الشهرية', 'description' => 'متابعة دفعات الأسر الشهرية', 'icon' => 'fa-money-check-dollar', 'url' => 'modules/accounting/disbursements.php'],
            ['title' => 'تحصيل فينا الخير', 'description' => 'فتح مسار تحصيل فينا الخير', 'icon' => 'fa-hand-holding-dollar', 'url' => 'modules/transactions/fina_payment_create.php'],
        ],
    ],
    [
        'title' => 'الحماية والصيانة',
        'subtitle' => 'أدوات التحكم الحساسة — استخدمها بعناية',
        'icon' => 'fa-shield-halved',
        'color' => '#c95d70',
        'items' => [
            ['title' => 'النسخ الاحتياطي', 'description' => 'إنشاء وإدارة نسخ قاعدة البيانات', 'icon' => 'fa-database', 'url' => 'modules/system/backup.php'],
            ['title' => 'إدارة قاعدة البيانات', 'description' => 'أدوات إدارة قاعدة البيانات', 'icon' => 'fa-table', 'url' => 'modules/system/database.php'],
            ['title' => 'سجل التدقيق', 'description' => 'مراجعة الأحداث الإدارية المسجلة', 'icon' => 'fa-file-lines', 'url' => 'modules/logs/audit.php'],
        ],
    ],
];

include __DIR__ . '/../includes/header.php';
?>

<style>
.admin-control-panel {
    --ac-bg: #f3f0ea;
    --ac-ink: #24201c;
    --ac-muted: #766e64;
    --ac-panel: #fffdf9;
    --ac-line: #e5ddd1;
    --ac-dark: #181512;
    --ac-dark-2: #2d2822;
    --ac-cyan: #27b3b0;
    --ac-green: #4f9d69;
    --ac-amber: #d88a2d;
    --ac-violet: #7567c7;
    --ac-blue: #4b78c2;
    --ac-rose: #c95d70;
    --ac-orange: #cf6f3e;
    --ac-teal: #238f88;
    margin: -24px;
    padding: 24px;
    min-height: calc(100vh - 120px);
    background:
        radial-gradient(circle at 100% 0%, rgba(207,111,62,.13), transparent 30%),
        radial-gradient(circle at 0% 20%, rgba(117,103,199,.10), transparent 28%),
        var(--ac-bg);
    color: var(--ac-ink);
}

.admin-hero {
    position: relative;
    overflow: hidden;
    border-radius: 14px;
    padding: 16px 20px;
    color: #fff;
    background:
        radial-gradient(circle at 85% 20%, rgba(39,179,176,.24), transparent 26%),
        linear-gradient(135deg, #172554 0%, #1e3a8a 55%, #2563eb 100%);
    box-shadow: 0 18px 45px rgba(17,24,39,.18);
    margin-bottom: 16px;
}

.admin-hero:after {
    content: "";
    position: absolute;
    width: 230px;
    height: 230px;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 50%;
    left: -70px;
    bottom: -145px;
}

.admin-hero-inner {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.admin-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--ac-cyan);
    font-size: .78rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.admin-hero h1 {
    margin: 0 0 4px;
    font-size: 1.35rem;
    line-height: 1.25;
    font-weight: 800;
}

.admin-hero p {
    margin: 0;
    color: rgba(255,255,255,.72);
    max-width: 760px;
    font-size: .82rem;
    line-height: 1.4;
}

.admin-health {
    min-width: 205px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.11);
    border-radius: 12px;
    padding: 10px 12px;
    backdrop-filter: blur(10px);
}

.admin-health-title {
    font-size: .7rem;
    color: rgba(255,255,255,.58);
    margin-bottom: 5px;
}

.admin-health-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    font-size: .78rem;
    margin-top: 4px;
}

.admin-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
}

.admin-status:before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
    box-shadow: 0 0 0 4px rgba(52,211,153,.10);
}

.admin-online { color: var(--ac-green); }
.admin-neutral { color: #d1d5db; }

.admin-section-title,
.admin-recent-heading,
.admin-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 15px 18px;
    color: #fff;
    background:
        radial-gradient(circle at 85% 20%, rgba(39,179,176,.24), transparent 26%),
        linear-gradient(135deg, #172554 0%, #1e3a8a 55%, #2563eb 100%);
    border: 1px solid rgba(255,255,255,.10);
    border-radius: 14px;
    box-shadow: 0 8px 22px rgba(17,24,39,.12);
}

.admin-section-title {
    margin: 28px 0 13px;
}

.admin-section-title h2,
.admin-section-heading h2 {
    font-size: 1.12rem;
    margin: 0;
    font-weight: 800;
    color: #fff;
}

.admin-section-title p,
.admin-section-heading p {
    margin: 4px 0 0;
    color: rgba(255,255,255,.72);
    font-size: .8rem;
}

.admin-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.admin-stat {
    position: relative;
    min-height: 128px;
    padding: 18px;
    border: 1px solid var(--ac-line);
    border-radius: 16px;
    background: var(--ac-panel);
    box-shadow: 0 8px 22px rgba(15,23,42,.05);
    text-decoration: none;
    color: inherit;
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}

.admin-stat:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 30px rgba(15,23,42,.10);
    border-color: #b9c5d5;
}

.admin-stat-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.admin-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 1rem;
}

.admin-stat-value {
    margin-top: 15px;
    font-size: 1.7rem;
    line-height: 1;
    font-weight: 800;
    letter-spacing: -.03em;
}

.admin-stat-label {
    margin-top: 6px;
    color: var(--ac-muted);
    font-size: .78rem;
    font-weight: 700;
}

.admin-stat-action {
    position: absolute;
    left: 18px;
    bottom: 15px;
    color: #64748b;
    font-size: .72rem;
    font-weight: 700;
}

.admin-cyan .admin-stat-icon { background: #d9f2ef; color: #238f88; }
.admin-amber .admin-stat-icon { background: #f8e5c9; color: #b96d1e; }
.admin-green .admin-stat-icon { background: #dceee2; color: #3e8055; }
.admin-violet .admin-stat-icon { background: #e8e4f7; color: #6457ad; }

.admin-control-section {
    margin-top: 22px;
    border: 1px solid var(--ac-line);
    border-radius: 18px;
    background: var(--ac-panel);
    box-shadow: 0 8px 22px rgba(15,23,42,.04);
    overflow: hidden;
}

.admin-control-heading {
    display: flex;
    position: relative;
    align-items: center;
    gap: 13px;
    padding: 17px 19px;
    color: #fff;
    border-bottom: 1px solid rgba(255,255,255,.10);
    background:
        radial-gradient(circle at 85% 20%, rgba(39,179,176,.24), transparent 26%),
        linear-gradient(135deg, #172554 0%, #1e3a8a 55%, #2563eb 100%);
}

.admin-control-heading:before {
    content: "";
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    background: rgba(39,179,176,.85);
}

.admin-control-heading-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: rgba(255,255,255,.10);
    border: 1px solid rgba(255,255,255,.12);
    color: #fff;
}

.admin-control-heading h3 {
    margin: 0;
    font-size: .98rem;
    font-weight: 800;
    color: #fff;
}

.admin-control-heading p {
    margin: 2px 0 0;
    color: rgba(255,255,255,.72);
    font-size: .73rem;
}

.admin-tool-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.admin-tool-grid.admin-tool-grid-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.admin-tool {
    min-height: 126px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 13px;
    padding: 17px 18px;
    color: inherit;
    text-decoration: none;
    border-left: 1px solid var(--ac-line);
    border-bottom: 1px solid var(--ac-line);
    transition: background .18s ease, transform .18s ease;
}

.admin-tool:nth-child(4n + 1) { border-left: 0; }

.admin-tool:hover {
    background: #fbf6ee;
}

.admin-tool:hover .admin-tool-icon {
    background: color-mix(in srgb, var(--section-color, #4b78c2) 12%, white);
    color: var(--section-color, #4b78c2);
}

.admin-tool-icon {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: #f1ece4;
    color: #5f574e;
}

.admin-tool-title {
    font-size: .86rem;
    font-weight: 800;
}

.admin-tool-description {
    color: var(--ac-muted);
    font-size: .7rem;
    line-height: 1.65;
    margin-top: 2px;
}

.admin-tool-arrow {
    color: #94a3b8;
    font-size: .72rem;
}

.admin-system-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
    margin-bottom:22px;
}
.admin-system-card {
    min-height:96px;
    display:flex;
    align-items:flex-start;
    gap:12px;
    padding:14px;
    border:1px solid var(--ac-line);
    border-radius:14px;
    background:var(--ac-panel);
    box-shadow:0 6px 18px rgba(15,23,42,.04);
}
.admin-system-icon {
    width:36px;height:36px;border-radius:10px;display:grid;place-items:center;flex:0 0 auto;
}
.admin-system-card strong {display:block;font-size:.8rem;margin-bottom:4px}
.admin-system-card span {display:block;color:#4b5563;font-size:.74rem;font-weight:700}
.admin-system-card small {display:block;color:var(--ac-muted);font-size:.66rem;margin-top:4px}
.admin-system-green {background:#dceee2;color:#3e8055}
.admin-system-blue {background:#e3edfb;color:#3d67a8}
.admin-system-violet {background:#e8e4f7;color:#6457ad}
.admin-system-amber {background:#f8e5c9;color:#b96d1e}
.admin-system-orange {background:#f5e1d8;color:#b95e35}
.admin-system-ok {color:#3e8055 !important}
.admin-system-bad {color:#b42318 !important}
.admin-recent-grid {margin-bottom:22px}
.admin-recent-card {
    border:1px solid var(--ac-line);border-radius:16px;background:var(--ac-panel);
    box-shadow:0 8px 22px rgba(15,23,42,.04);overflow:hidden;
}
.admin-recent-heading {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    padding:14px 17px;
    border-bottom:1px solid rgba(255,255,255,.10);
}
.admin-recent-heading strong {display:block;font-size:.86rem;color:#fff}
.admin-recent-heading span {display:block;color:rgba(255,255,255,.72);font-size:.7rem;margin-top:3px}
.admin-recent-heading a {font-size:.72rem;font-weight:800;text-decoration:none;color:#fff;white-space:nowrap}
.admin-recent-card .table {font-size:.72rem}
.admin-alert-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-top: 22px;
}

.admin-attention {
    border-radius: 16px;
    padding: 17px 18px;
    border: 1px solid var(--ac-line);
    background: #fff;
}

.admin-attention strong {
    display: block;
    font-size: .88rem;
    margin-bottom: 5px;
}

.admin-attention span {
    color: var(--ac-muted);
    font-size: .73rem;
}

.admin-attention-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-top: 12px;
    font-size: .74rem;
    font-weight: 800;
    text-decoration: none;
}

.admin-attention.warning {
    background: #fffbeb;
    border-color: #fde68a;
}

.admin-attention.warning .admin-attention-link { color: #b45309; }

.admin-attention.info {
    background: #ecfeff;
    border-color: #a5f3fc;
}

.admin-attention.info .admin-attention-link { color: #0e7490; }

.admin-footer-strip {
    margin-top: 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 13px 16px;
    border: 1px solid var(--ac-line);
    border-radius: 13px;
    background: rgba(255,255,255,.72);
    color: var(--ac-muted);
    font-size: .72rem;
}

@media (max-width: 1100px) {
    .admin-stat-grid,
    .admin-tool-grid,
    .admin-tool-grid.admin-tool-grid-3,
    .admin-system-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-tool:nth-child(4n + 1) { border-left: 1px solid var(--ac-line); }
    .admin-tool:nth-child(2n + 1) { border-left: 0; }
}

@media (max-width: 768px) {
    .admin-control-panel {
        margin: -16px;
        padding: 16px;
    }

    .admin-hero-inner {
        flex-direction: column;
        align-items: stretch;
    }

    .admin-health {
        min-width: 0;
    }

    .admin-stat-grid,
    .admin-tool-grid,
    .admin-tool-grid.admin-tool-grid-3,
    .admin-system-grid,
    .admin-alert-grid {
        grid-template-columns: 1fr;
    }

    .admin-recent-heading {
        align-items:flex-start;
        flex-direction:column;
    }

    .admin-tool,
    .admin-tool:nth-child(2n + 1),
    .admin-tool:nth-child(4n + 1) {
        border-left: 0;
    }

    .admin-stat {
        min-height: 118px;
    }

    .admin-footer-strip {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<div class="admin-control-panel">

    <section class="admin-hero">
        <div class="admin-hero-inner">
            <div>
                <div class="admin-kicker">
                    <i class="fas fa-terminal"></i>
                    ADMIN CONTROL PANEL
                </div>
                <h1>مركز التحكم الرئيسي</h1>
                <p>
                    نقطة الإدارة المركزية للنظام: المستخدمون، الصلاحيات، البيانات، المالية،
                    التقارير، النسخ الاحتياطي، وإعدادات المنصة — في مساحة واحدة مخصصة للمدير.
                </p>
            </div>

            <div class="admin-health">
                <div class="admin-health-title">حالة البيئة الحالية</div>

                <div class="admin-health-row">
                    <span>قاعدة البيانات</span>
                    <span class="admin-status <?php echo $dbOnline ? 'admin-online' : 'admin-neutral'; ?>">
                        <?php echo $dbOnline ? 'متصلة' : 'غير متاحة'; ?>
                    </span>
                </div>

                <div class="admin-health-row">
                    <span>PHP</span>
                    <strong><?php echo e(PHP_VERSION); ?></strong>
                </div>

                <div class="admin-health-row">
                    <span>المستخدم الحالي</span>
                    <strong><?php echo e(current_user_name()); ?></strong>
                </div>
            </div>
        </div>
    </section>

    <div class="admin-section-title">
        <div>
            <h2>حالة النظام والبيئة</h2>
            <p>مؤشرات تقنية للمدير لمراقبة بيئة تشغيل النظام دون تكرار أدوات الإدارة الموجودة في الوحدات.</p>
        </div>
    </div>

    <?php foreach ($controlGroups as $group): ?>
        <?php if ($group['title'] !== 'الحماية والصيانة') continue; ?>
        <section class="admin-control-section" style="--section-color: <?php echo $group['color'] ?? '#4b78c2'; ?>;">
            <div class="admin-control-heading">
                <div class="admin-control-heading-icon">
                    <i class="fas <?php echo e($group['icon']); ?>"></i>
                </div>
                <div>
                    <h3><?php echo e($group['title']); ?></h3>
                    <p><?php echo e($group['subtitle']); ?></p>
                </div>
            </div>

            <div class="admin-tool-grid <?php echo count($group['items']) === 3 ? 'admin-tool-grid-3' : ''; ?>">
                <?php foreach ($group['items'] as $item): ?>
                    <a class="admin-tool" href="<?php echo e(url($item['url'])); ?>">
                        <div>
                            <div class="admin-tool-icon">
                                <i class="fas <?php echo e($item['icon']); ?>"></i>
                            </div>
                        </div>
                        <div>
                            <div class="admin-tool-title"><?php echo e($item['title']); ?></div>
                            <div class="admin-tool-description"><?php echo e($item['description']); ?></div>
                        </div>
                        <div class="admin-tool-arrow">
                            فتح <i class="fas fa-arrow-left ms-1"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="admin-system-grid">
        <div class="admin-system-card">
            <div class="admin-system-icon admin-system-green"><i class="fas fa-database"></i></div>
            <div><strong>قاعدة البيانات</strong><span class="<?php echo $dbOnline ? 'admin-system-ok' : 'admin-system-bad'; ?>"><?php echo $dbOnline ? 'متصلة' : 'غير متاحة'; ?></span></div>
        </div>
        <div class="admin-system-card">
            <div class="admin-system-icon admin-system-blue"><i class="fas fa-server"></i></div>
            <div><strong>إصدار PHP</strong><span><?php echo e(PHP_VERSION); ?></span></div>
        </div>
        <div class="admin-system-card">
            <div class="admin-system-icon admin-system-violet"><i class="fas fa-hard-drive"></i></div>
            <div><strong>مساحة التخزين</strong><span><?php echo e($formatBytes($diskFree)); ?> متاحة من <?php echo e($formatBytes($diskTotal)); ?></span><small><?php echo number_format($diskUsedPercent, 1); ?>% مستخدمة</small></div>
        </div>
        <div class="admin-system-card">
            <div class="admin-system-icon admin-system-amber"><i class="fas fa-memory"></i></div>
            <div><strong>حد ذاكرة PHP</strong><span><?php echo e($memoryLimit); ?></span></div>
        </div>
        <div class="admin-system-card">
            <div class="admin-system-icon admin-system-orange"><i class="fas fa-upload"></i></div>
            <div><strong>حد رفع الملفات</strong><span><?php echo e($uploadLimit); ?></span></div>
        </div>
        <div class="admin-system-card">
            <div class="admin-system-icon admin-system-blue"><i class="fas fa-stopwatch"></i></div>
            <div><strong>حد تنفيذ PHP</strong><span><?php echo e($executionLimit); ?></span></div>
        </div>
        <div class="admin-system-card">
            <div class="admin-system-icon <?php echo $httpsEnabled ? 'admin-system-green' : 'admin-system-amber'; ?>"><i class="fas fa-lock"></i></div>
            <div><strong>HTTPS</strong><span><?php echo $httpsEnabled ? 'مفعّل' : 'غير مفعّل'; ?></span><small><?php echo $httpsEnabled ? 'الاتصال الحالي يستخدم HTTPS' : 'الاتصال الحالي يستخدم HTTP'; ?></small></div>
        </div>
        <div class="admin-system-card">
            <div class="admin-system-icon admin-system-violet"><i class="fas fa-shield-halved"></i></div>
            <div><strong>صلاحية اللوحة</strong><span>Admin فقط</span><small>الحماية الفعلية تبقى على مستوى كل صفحة</small></div>
        </div>
    </section>

    <section class="admin-recent-grid">
        <div class="admin-recent-card">
            <div class="admin-recent-heading">
                <div><strong><i class="fas fa-clock-rotate-left me-1"></i> آخر نشاط إداري</strong><span>آخر 6 أحداث مسجلة في سجل التدقيق</span></div>
                <a href="<?php echo e(url('modules/logs/audit.php')); ?>">فتح السجل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>التاريخ</th><th>المستخدم</th><th>الإجراء</th><th>الكيان</th></tr></thead>
                    <tbody>
                    <?php if (!$recentAudit): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">لا توجد أحداث مسجلة.</td></tr>
                    <?php else: foreach ($recentAudit as $event): ?>
                        <tr>
                            <td><small><?php echo e($event['created_at']); ?></small></td>
                            <td><?php echo e($event['username'] ?? '—'); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo e($event['action']); ?></span></td>
                            <td><small><?php echo e($event['entity_type']); ?><?php echo $event['entity_id'] !== null ? ' #' . e((string)$event['entity_id']) : ''; ?></small></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <div class="admin-section-heading" style="margin-top: 22px;">
        <div>
            <h2>نظرة سريعة على النظام</h2>
            <p>أرقام تشغيلية مباشرة تساعدك على معرفة حجم النظام قبل الدخول إلى أي وحدة.</p>
        </div>
    </div>

    <section class="admin-stat-grid">
        <?php foreach ($systemCards as $card): ?>
            <a class="admin-stat <?php echo e($card['class']); ?>" href="<?php echo e($card['url']); ?>">
                <div class="admin-stat-top">
                    <div>
                        <div class="admin-stat-label"><?php echo e($card['label']); ?></div>
                        <div class="admin-stat-value"><?php echo e($card['value']); ?></div>
                    </div>
                    <div class="admin-stat-icon">
                        <i class="fas <?php echo e($card['icon']); ?>"></i>
                    </div>
                </div>
                <div class="admin-stat-action">
                    <?php echo e($card['action']); ?>
                    <i class="fas fa-arrow-left ms-1"></i>
                </div>
            </a>
        <?php endforeach; ?>
    </section>

    <div class="admin-alert-grid">
        <div class="admin-attention <?php echo $stats['pending_recovery'] > 0 ? 'warning' : 'info'; ?>">
            <strong>
                <i class="fas fa-key me-1"></i>
                طلبات استعادة كلمة المرور
            </strong>
            <span>
                <?php if ($stats['pending_recovery'] > 0): ?>
                    يوجد <?php echo number_format($stats['pending_recovery']); ?> طلب بانتظار المراجعة.
                <?php else: ?>
                    لا توجد طلبات استعادة كلمة مرور معلقة حالياً.
                <?php endif; ?>
            </span>
            <a class="admin-attention-link" href="<?php echo e(url('modules/users/index.php')); ?>">
                مراجعة المستخدمين
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>

        <div class="admin-attention <?php echo $stats['unread_notifications'] > 0 ? 'warning' : 'info'; ?>">
            <strong>
                <i class="fas fa-bell me-1"></i>
                إشعارات المدير
            </strong>
            <span>
                <?php if ($stats['unread_notifications'] > 0): ?>
                    لديك <?php echo number_format($stats['unread_notifications']); ?> إشعار غير مقروء.
                <?php else: ?>
                    لا توجد إشعارات غير مقروءة حالياً.
                <?php endif; ?>
            </span>
            <a class="admin-attention-link" href="<?php echo e(url('modules/notifications/index.php')); ?>">
                فتح الإشعارات
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>


    <?php foreach ($controlGroups as $group): ?>
        <?php if ($group['title'] === 'الحماية والصيانة') continue; ?>
        <section class="admin-control-section" style="--section-color: <?php echo $group['color'] ?? '#4b78c2'; ?>;">
            <div class="admin-control-heading">
                <div class="admin-control-heading-icon">
                    <i class="fas <?php echo e($group['icon']); ?>"></i>
                </div>
                <div>
                    <h3><?php echo e($group['title']); ?></h3>
                    <p><?php echo e($group['subtitle']); ?></p>
                </div>
            </div>

            <div class="admin-tool-grid <?php echo count($group['items']) === 3 ? 'admin-tool-grid-3' : ''; ?>">
                <?php foreach ($group['items'] as $item): ?>
                    <a class="admin-tool" href="<?php echo e(url($item['url'])); ?>">
                        <div>
                            <div class="admin-tool-icon">
                                <i class="fas <?php echo e($item['icon']); ?>"></i>
                            </div>
                        </div>
                        <div>
                            <div class="admin-tool-title">
                                <?php echo e($item['title']); ?>
                            </div>
                            <div class="admin-tool-description">
                                <?php echo e($item['description']); ?>
                            </div>
                        </div>
                        <div class="admin-tool-arrow">
                            فتح <i class="fas fa-arrow-left ms-1"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <div class="admin-footer-strip">
        <span>
            <i class="fas fa-shield-halved me-1"></i>
            هذه اللوحة متاحة لحساب المدير فقط. الصلاحيات الفعلية تظل محكومة بحماية الصفحات نفسها.
        </span>
        <span>
            <?php echo e(date('Y-m-d H:i')); ?>
        </span>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/age_alert.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
