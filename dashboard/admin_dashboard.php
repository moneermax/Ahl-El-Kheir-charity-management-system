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

try {
    dbFetchOne("SELECT 1 AS ok");
    $dbOnline = true;
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
        'subtitle' => 'الهوية والصلاحيات والبنية الأساسية',
        'icon' => 'fa-sliders',
        'items' => [
            ['title' => 'المستخدمون', 'description' => 'إنشاء الحسابات وإدارة المستخدمين', 'icon' => 'fa-users-gear', 'url' => 'modules/users/index.php'],
            ['title' => 'الأدوار والصلاحيات', 'description' => 'إدارة الأدوار ومستويات الوصول', 'icon' => 'fa-user-shield', 'url' => 'modules/users/roles.php'],
            ['title' => 'الأقسام', 'description' => 'إدارة أقسام المنظمة', 'icon' => 'fa-building', 'url' => 'modules/departments/index.php'],
            ['title' => 'إعدادات النظام', 'description' => 'بيانات المنظمة والإعدادات العامة', 'icon' => 'fa-gear', 'url' => 'modules/settings/index.php'],
        ],
    ],
    [
        'title' => 'البيانات الأساسية',
        'subtitle' => 'السجلات التشغيلية الرئيسية',
        'icon' => 'fa-database',
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
    --ac-bg: #eef2f7;
    --ac-ink: #172033;
    --ac-muted: #68758a;
    --ac-panel: #ffffff;
    --ac-line: #dce3ed;
    --ac-dark: #111827;
    --ac-dark-2: #1f2937;
    --ac-cyan: #22d3ee;
    --ac-green: #34d399;
    --ac-amber: #fbbf24;
    --ac-violet: #a78bfa;
    margin: -24px;
    padding: 24px;
    min-height: calc(100vh - 120px);
    background:
        radial-gradient(circle at 100% 0%, rgba(34,211,238,.12), transparent 32%),
        radial-gradient(circle at 0% 20%, rgba(167,139,250,.10), transparent 28%),
        var(--ac-bg);
    color: var(--ac-ink);
}

.admin-hero {
    position: relative;
    overflow: hidden;
    border-radius: 22px;
    padding: 28px 30px;
    color: #fff;
    background:
        radial-gradient(circle at 85% 20%, rgba(34,211,238,.24), transparent 26%),
        linear-gradient(135deg, #0b1220 0%, #172033 55%, #24324a 100%);
    box-shadow: 0 18px 45px rgba(17,24,39,.18);
    margin-bottom: 22px;
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
    gap: 24px;
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
    margin-bottom: 8px;
}

.admin-hero h1 {
    margin: 0 0 8px;
    font-size: clamp(1.55rem, 3vw, 2.25rem);
    font-weight: 800;
}

.admin-hero p {
    margin: 0;
    color: rgba(255,255,255,.72);
    max-width: 760px;
}

.admin-health {
    min-width: 230px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.11);
    border-radius: 16px;
    padding: 15px 17px;
    backdrop-filter: blur(10px);
}

.admin-health-title {
    font-size: .76rem;
    color: rgba(255,255,255,.58);
    margin-bottom: 9px;
}

.admin-health-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    font-size: .88rem;
    margin-top: 7px;
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

.admin-section-title {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 16px;
    margin: 28px 0 13px;
}

.admin-section-title h2 {
    font-size: 1.12rem;
    margin: 0;
    font-weight: 800;
}

.admin-section-title p {
    margin: 4px 0 0;
    color: var(--ac-muted);
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

.admin-cyan .admin-stat-icon { background: #cffafe; color: #0891b2; }
.admin-amber .admin-stat-icon { background: #fef3c7; color: #d97706; }
.admin-green .admin-stat-icon { background: #d1fae5; color: #059669; }
.admin-violet .admin-stat-icon { background: #ede9fe; color: #7c3aed; }

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
    align-items: center;
    gap: 13px;
    padding: 17px 19px;
    border-bottom: 1px solid var(--ac-line);
    background: #fbfcfe;
}

.admin-control-heading-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: #e8eef7;
    color: #334155;
}

.admin-control-heading h3 {
    margin: 0;
    font-size: .98rem;
    font-weight: 800;
}

.admin-control-heading p {
    margin: 2px 0 0;
    color: var(--ac-muted);
    font-size: .73rem;
}

.admin-tool-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
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
    background: #f7faff;
}

.admin-tool-icon {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: #eef2f7;
    color: #334155;
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
    .admin-tool-grid {
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
    .admin-alert-grid {
        grid-template-columns: 1fr;
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
        <section class="admin-control-section">
            <div class="admin-control-heading">
                <div class="admin-control-heading-icon">
                    <i class="fas <?php echo e($group['icon']); ?>"></i>
                </div>
                <div>
                    <h3><?php echo e($group['title']); ?></h3>
                    <p><?php echo e($group['subtitle']); ?></p>
                </div>
            </div>

            <div class="admin-tool-grid">
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
