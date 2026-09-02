<?php
// includes/sidebar.php — v14: Role-based sidebar
if (!isset($active)) $active = '';
$raw_role = (string)current_user_role();
$rid = 0;
try {
    $r = dbFetchOne("SELECT r.id, r.code FROM roles r JOIN users u ON u.role_id = r.id WHERE u.id = ?", [current_user_id()]);
    if ($r) { $rid = (int)$r['id']; $raw_role = (string)$r['code']; }
} catch (Throwable $ex) {}
$aliasMap = ['sudo' => 'admin', 'gm' => 'general_manager', 'vgm' => 'vice_general_manager', 'fm' => 'financial_manager'];
$role = $aliasMap[$raw_role] ?? $raw_role;

/* Shared items */
$acctView = [];
if (in_array($role, ['admin', 'vice_general_manager', 'financial_manager', 'accountant'], true)) {
    $acctView = [['active' => 'accounting', 'label' => 'المحاسبة', 'icon' => 'fa-calculator', 'url' => 'modules/accounting/index.php']];
}
$acctManage = [];
if (in_array($role, ['admin', 'financial_manager', 'accountant'], true)) {
    $acctManage = [['active' => 'accounts', 'label' => 'دليل الحسابات', 'icon' => 'fa-sitemap', 'url' => 'modules/accounting/accounts.php']];
}
$projectsItem = ['active' => 'projects', 'label' => 'مشاريع المنظمة', 'icon' => 'fa-diagram-project', 'url' => 'modules/projects/index.php'];
$transactionsItem = ['active' => 'transactions', 'label' => 'سجل المعاملات', 'icon' => 'fa-money-bill-transfer', 'url' => 'modules/transactions/index.php'];
$fmDashItem = ['active' => 'fm_dashboard', 'label' => 'لوحة المدير المالي', 'icon' => 'fa-chart-line', 'url' => 'modules/accounting/fm_dashboard.php'];
$fmReviewItem = ['active' => 'fm_review', 'label' => 'طابور المراجعة المالية', 'icon' => 'fa-clipboard-check', 'url' => 'modules/accounting/fm_review_queue.php'];
$disbItem = ['active' => 'disbursements', 'label' => 'دفعات الأسر الشهرية', 'icon' => 'fa-money-check-dollar', 'url' => 'modules/accounting/disbursements.php'];
$reconItem = ['active' => 'reconciliation', 'label' => 'تقرير المصالحة', 'icon' => 'fa-scale-balanced', 'url' => 'modules/accounting/gm_reconciliation.php'];
$openingBalanceItem = ['active' => 'opening_balance', 'label' => 'الرصيد الافتتاحي', 'icon' => 'fa-vault', 'url' => 'modules/accounting/opening_balance.php'];
$orphanFormsItem = ['active' => 'orphan_forms', 'label' => 'استمارات الأيتام', 'icon' => 'fa-file-signature', 'url' => 'modules/families/orphan_forms_index.php'];
$suspItem = ['active' => 'suspensions', 'label' => 'حالات الإيقاف', 'icon' => 'fa-user-slash', 'url' => 'modules/families/suspensions.php'];
$winbackItem = ['active' => 'winback', 'label' => 'متابعة الاسترجاع', 'icon' => 'fa-person-booth', 'url' => 'modules/administration/winback.php'];
$sponsorReportItem = ['active' => 'sponsor_reports', 'label' => 'تقارير الكفلاء (PDF)', 'icon' => 'fa-file-pdf', 'url' => 'modules/transactions/sponsor-monthly-report.php'];
$lostContactReportItem = ['active' => 'lost_contact_report', 'label' => 'تقرير فقدان التواصل', 'icon' => 'fa-exclamation-triangle', 'url' => 'modules/reports/lost_contact_report.php'];
$confirmedDisbReportItem = ['active' => 'confirmed_disbursements_report', 'label' => 'تقرير التحويلات المؤكدة', 'icon' => 'fa-check-circle', 'url' => 'modules/reports/confirmed_disbursements_report.php'];

/* Menus per role */
$menus = [
    'admin' => array_merge([
        ['active' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/admin_dashboard.php'],
        ['active' => 'users', 'label' => 'إدارة المستخدمين', 'icon' => 'fa-users-gear', 'url' => 'modules/users/index.php'],
        ['active' => 'roles', 'label' => 'الصلاحيات', 'icon' => 'fa-user-shield', 'url' => 'modules/users/roles.php'],
        ['active' => 'departments', 'label' => 'الأقسام', 'icon' => 'fa-building', 'url' => 'modules/departments/index.php'],
        ['active' => 'sponsors', 'label' => 'سجل الكفلاء', 'icon' => 'fa-hand-holding-heart', 'url' => 'modules/sponsors/index.php'],
        ['active' => 'families', 'label' => 'سجل الأسر', 'icon' => 'fa-house-chimney', 'url' => 'modules/families/index.php'],
        ['active' => 'sponsorships', 'label' => 'سجل الكفالات', 'icon' => 'fa-file-contract', 'url' => 'modules/sponsorships/index.php'],
        [
            'type' => 'dropdown', 'label' => 'التقارير', 'icon' => 'fa-chart-line',
            'items' => [
                ['active' => 'reports', 'label' => 'التقارير العامة', 'icon' => 'fa-file-lines', 'url' => 'modules/reports/index.php'],
                $lostContactReportItem, $confirmedDisbReportItem, $sponsorReportItem,
            ]
        ],
        ['active' => 'audit', 'label' => 'سجل التدقيق', 'icon' => 'fa-file-lines', 'url' => 'modules/logs/audit.php'],
        ['active' => 'database', 'label' => 'إدارة قاعدة البيانات', 'icon' => 'fa-database', 'url' => 'modules/system/database.php'],
        ['active' => 'settings', 'label' => 'إعدادات النظام', 'icon' => 'fa-gear', 'url' => 'modules/settings/index.php'],
    ], $acctView, [$fmDashItem, $fmReviewItem, $disbItem, $reconItem, $openingBalanceItem]),

    'general_manager' => array_merge([
        ['active' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/gm_dashboard.php'],
        $projectsItem, $orphanFormsItem,
    ], $acctView, [$fmDashItem, $fmReviewItem, $disbItem, $reconItem]),

    /*
     * VGM navigation is intentionally limited to the functions used for
     * organizational oversight of families, orphans, sponsorships and
     * operational reports. Financial-manager workflow links are not shown
     * here because they belong to the financial roles.
     */
    'vice_general_manager' => [
        ['active' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/vgm_dashboard.php'],
        ['active' => 'families', 'label' => 'سجل الأسر', 'icon' => 'fa-house-chimney', 'url' => 'modules/families/index.php'],
        ['active' => 'orphan_groups', 'label' => 'مجموعات الأيتام', 'icon' => 'fa-people-group', 'url' => 'modules/deputy_gm/groups.php'],
        ['active' => 'sponsorships', 'label' => 'سجل الكفالات', 'icon' => 'fa-file-contract', 'url' => 'modules/sponsorships/index.php'],
        $suspItem,
        [
            'type' => 'dropdown', 'label' => 'التقارير', 'icon' => 'fa-chart-line',
            'items' => [
                ['active' => 'reports', 'label' => 'التقارير العامة', 'icon' => 'fa-file-lines', 'url' => 'modules/reports/index.php'],
                $lostContactReportItem, $confirmedDisbReportItem,
            ]
        ],
    ],

    'financial_manager' => [
        ['active' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'url' => 'modules/accounting/fm_dashboard.php'],
        ['active' => 'journal', 'label' => 'دفتر القيود', 'icon' => 'fa-book', 'url' => 'modules/accounting/journal.php'],
        ['active' => 'projects', 'label' => 'مشاريع المنظمة', 'icon' => 'fa-diagram-project', 'url' => 'modules/projects/index.php'],
        $openingBalanceItem,
        ['active' => 'disbursements', 'label' => 'التحويلات الشهرية', 'icon' => 'fa-money-check-dollar', 'url' => 'modules/accounting/disbursements.php'],
        ['active' => 'my_nannies', 'label' => 'الحاضنات المُعيّنات', 'icon' => 'fa-user-nurse', 'url' => 'modules/accounting/my_nannies.php'],
        ['active' => 'review', 'label' => 'طابور المراجعة المالية', 'icon' => 'fa-clipboard-check', 'url' => 'modules/accounting/fm_review_queue.php'],
        ['active' => 'reconciliation', 'label' => 'تقرير المصالحة', 'icon' => 'fa-scale-balanced', 'url' => 'modules/accounting/gm_reconciliation.php'],
    ],

    'accountant' => array_merge([
        ['active' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/accountant_dashboard.php'],
        ['active' => 'journal', 'label' => 'دفتر القيود', 'icon' => 'fa-book', 'url' => 'modules/accounting/journal.php'],
        ['active' => 'accounts', 'label' => 'دليل الحسابات', 'icon' => 'fa-sitemap', 'url' => 'modules/accounting/accounts.php'],
        ['active' => 'transactions', 'label' => 'سجل المعاملات', 'icon' => 'fa-money-bill-transfer', 'url' => 'modules/transactions/index.php'],
        ['active' => 'my_nannies', 'label' => 'الحاضنات المُعيّنات', 'icon' => 'fa-user-nurse', 'url' => 'modules/accounting/my_nannies.php'],
    ], $acctManage, [$disbItem, $fmDashItem, $fmReviewItem, $reconItem]),

    'accountant_staff' => [
        ['active' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/accountant_staff_dashboard.php'],
        $transactionsItem, $projectsItem, $disbItem, $orphanFormsItem,
        ['active' => 'my_nannies', 'label' => 'الحاضنات المُعيّنات', 'icon' => 'fa-user-nurse', 'url' => 'modules/accounting/my_nannies.php'],
        [
            'type' => 'dropdown', 'label' => 'التقارير', 'icon' => 'fa-chart-line',
            'items' => [$lostContactReportItem, $confirmedDisbReportItem]
        ],
    ],

    'nanny' => [
        ['active' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/nanny_dashboard.php'],
        ['active' => 'families', 'label' => 'سجل الأسر', 'icon' => 'fa-house-chimney', 'url' => 'modules/families/index.php'],
        $disbItem, $orphanFormsItem,
        [
            'type' => 'dropdown', 'label' => 'التقارير', 'icon' => 'fa-file-lines',
            'items' => [$sponsorReportItem, $lostContactReportItem, $confirmedDisbReportItem]
        ],
    ],

    'supervisor' => [
        ['active' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/supervisor_dashboard.php'],
        ['active' => 'sponsors', 'label' => 'سجل الكفلاء', 'icon' => 'fa-hand-holding-heart', 'url' => 'modules/sponsors/index.php'],
        ['active' => 'families', 'label' => 'سجل الأسر', 'icon' => 'fa-house-chimney', 'url' => 'modules/families/index.php'],
        $orphanFormsItem,
        ['active' => 'sponsorships', 'label' => 'سجل الكفالات', 'icon' => 'fa-file-contract', 'url' => 'modules/sponsorships/index.php'],
        [
            'type' => 'dropdown', 'label' => 'التقارير', 'icon' => 'fa-chart-line',
            'items' => [
                ['active' => 'reports', 'label' => 'التقارير العامة', 'icon' => 'fa-file-lines', 'url' => 'modules/reports/index.php'],
                $sponsorReportItem,
            ]
        ],
    ],

    'staff' => [
        ['active' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/staff_dashboard.php'],
        ['active' => 'requests', 'label' => 'طلبات الانضمام', 'icon' => 'fa-user-plus', 'url' => 'modules/sponsors/requests.php'],
        $orphanFormsItem,
    ],

    'social_media' => [
        ['active' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/staff_dashboard.php'],
        ['active' => 'requests', 'label' => 'طلبات الانضمام', 'icon' => 'fa-user-plus', 'url' => 'modules/sponsors/requests.php'],
        $orphanFormsItem,
    ],

    'projects_manager' => [
        ['active' => 'projects_dashboard', 'label' => 'لوحة تحكم المشاريع', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/projects_dashboard.php'],
        ['active' => 'projects', 'label' => 'محفظة المشاريع', 'icon' => 'fa-diagram-project', 'url' => 'modules/projects/index.php'],
    ],
    'project_supervisor' => [
        ['active' => 'projects_dashboard', 'label' => 'لوحة متابعة المشاريع', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/projects_dashboard.php'],
        ['active' => 'projects', 'label' => 'مشاريعي', 'icon' => 'fa-folder-open', 'url' => 'modules/projects/index.php'],
    ],
    'hr_manager' => [
        ['active' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/hr_dashboard.php'],
        ['active' => 'employees', 'label' => 'إدارة الموظفين', 'icon' => 'fa-users', 'url' => 'modules/hr/employees.php'],
        ['active' => 'attendance', 'label' => 'الحضور والانصراف', 'icon' => 'fa-clock', 'url' => 'modules/hr/attendance.php'],
        ['active' => 'leaves', 'label' => 'طلبات الإجازة', 'icon' => 'fa-calendar-alt', 'url' => 'modules/hr/leaves.php'],
        ['active' => 'payroll', 'label' => 'كشف الرواتب', 'icon' => 'fa-money-bill-wave', 'url' => 'modules/hr/payroll.php'],
        ['active' => 'contracts', 'label' => 'إدارة العقود', 'icon' => 'fa-file-contract', 'url' => 'modules/hr/contracts.php'],
    ],
    'hr_staff' => [
        ['active' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/hr_dashboard.php'],
        ['active' => 'employees', 'label' => 'إدارة الموظفين', 'icon' => 'fa-users', 'url' => 'modules/hr/employees.php'],
        ['active' => 'attendance', 'label' => 'الحضور والانصراف', 'icon' => 'fa-clock', 'url' => 'modules/hr/attendance.php'],
        ['active' => 'leaves', 'label' => 'طلبات الإجازة', 'icon' => 'fa-calendar-alt', 'url' => 'modules/hr/leaves.php'],
        ['active' => 'payroll', 'label' => 'كشف الرواتب', 'icon' => 'fa-money-bill-wave', 'url' => 'modules/hr/payroll.php'],
        ['active' => 'contracts', 'label' => 'إدارة العقود', 'icon' => 'fa-file-contract', 'url' => 'modules/hr/contracts.php'],
    ],
];

$personal = [
    ['active' => 'profile', 'label' => 'ملفي الشخصي', 'icon' => 'fa-user', 'url' => 'modules/users/profile.php'],
    ['active' => 'logout', 'label' => 'تسجيل خروج', 'icon' => 'fa-right-from-bracket', 'url' => 'logout.php'],
];

$menu = array_merge($menus[$role] ?? [], $personal);
?>
<!-- AK-SIDEBAR-ROLE: raw=<?php echo e($raw_role); ?>| rid=<?php echo $rid; ?>| resolved=<?php echo e($role); ?> -->
<aside id="sidebar" class="sidebar">
<div class="sidebar-brand">
<img src="<?php echo APP_URL; ?>assets/img/logo.png" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:50%;background:#fff;border:2px solid rgba(255,255,255,.25)">
<div>
<div class="brand-title">أهل الخير</div>
<div class="brand-subtitle">نظام الإدارة</div>
</div>
</div>
<ul class="sidebar-menu">
<?php foreach ($menu as $item): ?>
<?php if (isset($item['type']) && $item['type'] === 'dropdown'): ?>
<li class="sidebar-dropdown">
<a href="#" class="sidebar-link dropdown-trigger"><i class="fas <?php echo $item['icon']; ?>"></i><span><?php echo e($item['label']); ?></span></a>
<ul class="sidebar-dropdown-menu">
<?php foreach ($item['items'] as $subItem): ?>
<li><a href="<?php echo APP_URL . $subItem['url']; ?>" class="sidebar-link sub-link <?php echo ($active === $subItem['active']) ? 'active' : ''; ?>"><i class="fas <?php echo $subItem['icon']; ?>"></i><span><?php echo e($subItem['label']); ?></span></a></li>
<?php endforeach; ?>
</ul>
</li>
<?php else: ?>
<li><a href="<?php echo APP_URL . $item['url']; ?>" class="sidebar-link <?php echo ($active === $item['active']) ? 'active' : ''; ?>"><i class="fas <?php echo $item['icon']; ?>"></i><span><?php echo e($item['label']); ?></span></a></li>
<?php endif; ?>
<?php endforeach; ?>
</ul>
</aside>

<style>
.sidebar-dropdown { position: relative; }
.sidebar-dropdown .dropdown-trigger { display:flex; align-items:center; padding:.75rem 1.25rem; color:rgba(255,255,255,.85); text-decoration:none; transition:all .2s ease; cursor:pointer; }
.sidebar-dropdown .dropdown-trigger:hover { background:rgba(255,255,255,.1); color:#fff; }
.sidebar-dropdown .dropdown-trigger i:first-child { width:24px; margin-left:.5rem; font-size:1rem; }
.sidebar-dropdown .sidebar-dropdown-menu { list-style:none; margin:0; padding:0; background:rgba(0,0,0,.25); border-radius:0; max-height:0; overflow:hidden; transition:max-height .35s ease,padding .35s ease; }
.sidebar-dropdown:hover .sidebar-dropdown-menu { max-height:500px; padding:.5rem 0; }
.sidebar-dropdown .sidebar-dropdown-menu li { list-style:none; }
.sidebar-dropdown .sidebar-dropdown-menu .sub-link { display:flex; align-items:center; padding:.6rem 1.25rem .6rem 3rem; color:rgba(255,255,255,.8); text-decoration:none; transition:all .2s ease; font-size:.9rem; }
.sidebar-dropdown .sidebar-dropdown-menu .sub-link i { width:20px; margin-left:.5rem; font-size:.85rem; }
.sidebar-dropdown .sidebar-dropdown-menu .sub-link:hover { background:rgba(255,255,255,.08); color:#fff; padding-right:3.25rem; }
.sidebar-dropdown .sidebar-dropdown-menu .sub-link.active { background:rgba(13,110,253,.3); color:#fff; border-right:3px solid #0d6efd; }
.sidebar-dropdown:hover .dropdown-trigger, .sidebar-dropdown .sidebar-dropdown-menu .sub-link.active ~ .dropdown-trigger, .sidebar-dropdown:has(.sub-link.active) .dropdown-trigger { background:rgba(255,255,255,.08); color:#fff; }
[dir="rtl"] .sidebar-dropdown .sidebar-dropdown-menu .sub-link { padding:.6rem 3rem .6rem 1.25rem; }
[dir="rtl"] .sidebar-dropdown .sidebar-dropdown-menu .sub-link:hover { padding-left:3.25rem; padding-right:3rem; }
[dir="rtl"] .sidebar-dropdown .sidebar-dropdown-menu .sub-link.active { border-right:none; border-left:3px solid #0d6efd; }
[dir="rtl"] .sidebar-dropdown .dropdown-trigger i:first-child { margin-left:0; margin-right:.5rem; }
[dir="rtl"] .sidebar-dropdown .sidebar-dropdown-menu .sub-link i { margin-left:0; margin-right:.5rem; }
.sidebar-link { display:flex; align-items:center; padding:.75rem 1.25rem; color:rgba(255,255,255,.85); text-decoration:none; transition:all .2s ease; }
.sidebar-link:hover { background:rgba(255,255,255,.1); color:#fff; }
.sidebar-link.active { background:rgba(13,110,253,.3); color:#fff; border-right:3px solid #0d6efd; }
[dir="rtl"] .sidebar-link.active { border-right:none; border-left:3px solid #0d6efd; }
.sidebar-link i { width:24px; margin-left:.5rem; font-size:1rem; }
[dir="rtl"] .sidebar-link i { margin-left:0; margin-right:.5rem; }
</style>