<?php
// includes/sidebar.php — v16: Role-based sidebar with stable i18n keys
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
    $acctView = [['active' => 'accounting', 'label_key' => 'navigation.accounting', 'icon' => 'fa-calculator', 'url' => 'modules/accounting/index.php']];
}
$acctManage = [];
if (in_array($role, ['admin', 'financial_manager', 'accountant'], true)) {
    $acctManage = [['active' => 'accounts', 'label_key' => 'navigation.chart_of_accounts', 'icon' => 'fa-sitemap', 'url' => 'modules/accounting/accounts.php']];
}
$projectsItem = ['active' => 'projects', 'label_key' => 'navigation.organization_projects', 'icon' => 'fa-diagram-project', 'url' => 'modules/projects/index.php'];
$transactionsItem = ['active' => 'transactions', 'label_key' => 'navigation.transaction_register', 'icon' => 'fa-money-bill-transfer', 'url' => 'modules/transactions/index.php'];
$fmDashItem = ['active' => 'fm_dashboard', 'label_key' => 'navigation.financial_manager_dashboard', 'icon' => 'fa-chart-line', 'url' => 'modules/accounting/fm_dashboard.php'];
$fmReviewItem = ['active' => 'fm_review', 'label_key' => 'navigation.financial_review_queue', 'icon' => 'fa-clipboard-check', 'url' => 'modules/accounting/fm_review_queue.php'];
$disbItem = ['active' => 'disbursements', 'label_key' => 'navigation.monthly_family_payments', 'icon' => 'fa-money-check-dollar', 'url' => 'modules/accounting/disbursements.php'];
$reconItem = ['active' => 'reconciliation', 'label_key' => 'navigation.reconciliation_report', 'icon' => 'fa-scale-balanced', 'url' => 'modules/accounting/gm_reconciliation.php'];
$openingBalanceItem = ['active' => 'opening_balance', 'label_key' => 'navigation.opening_balance', 'icon' => 'fa-vault', 'url' => 'modules/accounting/opening_balance.php'];
$orphanFormsItem = ['active' => 'orphan_forms', 'label_key' => 'navigation.orphan_forms', 'icon' => 'fa-file-signature', 'url' => 'modules/families/orphan_forms_index.php'];
$suspItem = ['active' => 'suspensions', 'label_key' => 'navigation.suspension_cases', 'icon' => 'fa-user-slash', 'url' => 'modules/families/suspensions.php'];
$winbackItem = ['active' => 'winback', 'label_key' => 'navigation.winback', 'icon' => 'fa-person-booth', 'url' => 'modules/administration/winback.php'];
$sponsorReportItem = ['active' => 'sponsor_reports', 'label_key' => 'navigation.sponsor_reports', 'icon' => 'fa-file-pdf', 'url' => 'modules/transactions/sponsor-monthly-report.php'];
$lostContactReportItem = ['active' => 'lost_contact_report', 'label_key' => 'navigation.lost_contact_report', 'icon' => 'fa-exclamation-triangle', 'url' => 'modules/reports/lost_contact_report.php'];
$confirmedDisbReportItem = ['active' => 'confirmed_disbursements_report', 'label_key' => 'navigation.confirmed_transfers_report', 'icon' => 'fa-check-circle', 'url' => 'modules/reports/confirmed_disbursements_report.php'];
$reportsItem = ['active' => 'reports', 'label_key' => 'navigation.reports', 'icon' => 'fa-chart-line', 'url' => 'modules/reports/index.php'];

/* Menus per role */
$menus = [
    'admin' => array_merge([
        ['active' => 'dashboard', 'label_key' => 'navigation.dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/admin_dashboard.php'],
        ['active' => 'users', 'label_key' => 'navigation.users_management', 'icon' => 'fa-users-gear', 'url' => 'modules/users/index.php'],
        ['active' => 'roles', 'label_key' => 'navigation.permissions', 'icon' => 'fa-user-shield', 'url' => 'modules/users/roles.php'],
        ['active' => 'departments', 'label_key' => 'navigation.departments', 'icon' => 'fa-building', 'url' => 'modules/departments/index.php'],
        ['active' => 'sponsors', 'label_key' => 'navigation.sponsor_register', 'icon' => 'fa-hand-holding-heart', 'url' => 'modules/sponsors/index.php'],
        ['active' => 'families', 'label_key' => 'navigation.family_register', 'icon' => 'fa-house-chimney', 'url' => 'modules/families/index.php'],
        ['active' => 'sponsorships', 'label_key' => 'navigation.sponsorship_register', 'icon' => 'fa-file-contract', 'url' => 'modules/sponsorships/index.php'],
        $reportsItem,
        ['active' => 'audit', 'label_key' => 'navigation.audit_log', 'icon' => 'fa-file-lines', 'url' => 'modules/logs/audit.php'],
        ['active' => 'database', 'label_key' => 'navigation.database_management', 'icon' => 'fa-database', 'url' => 'modules/system/database.php'],
        ['active' => 'settings', 'label_key' => 'navigation.system_settings', 'icon' => 'fa-gear', 'url' => 'modules/settings/index.php'],
    ], $acctView, [$fmDashItem, $fmReviewItem, $disbItem, $reconItem, $openingBalanceItem]),

    'general_manager' => array_merge([
        ['active' => 'dashboard', 'label_key' => 'navigation.dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/gm_dashboard.php'],
        $projectsItem, $orphanFormsItem,
    ], $acctView, [$reportsItem, $fmDashItem, $fmReviewItem, $disbItem, $reconItem]),

    'vice_general_manager' => [
        ['active' => 'dashboard', 'label_key' => 'navigation.dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/vgm_dashboard.php'],
        ['active' => 'families', 'label_key' => 'navigation.family_register', 'icon' => 'fa-house-chimney', 'url' => 'modules/families/index.php'],
        ['active' => 'orphan_groups', 'label_key' => 'navigation.orphan_groups', 'icon' => 'fa-people-group', 'url' => 'modules/deputy_gm/groups.php'],
        ['active' => 'sponsorships', 'label_key' => 'navigation.sponsorship_register', 'icon' => 'fa-file-contract', 'url' => 'modules/sponsorships/index.php'],
        $suspItem,
        $reportsItem,
    ],

    'financial_manager' => [
        ['active' => 'dashboard', 'label_key' => 'navigation.dashboard', 'icon' => 'fa-gauge-high', 'url' => 'modules/accounting/fm_dashboard.php'],
        $reportsItem,
        ['active' => 'journal', 'label_key' => 'navigation.journal', 'icon' => 'fa-book', 'url' => 'modules/accounting/journal.php'],
        ['active' => 'projects', 'label_key' => 'navigation.organization_projects', 'icon' => 'fa-diagram-project', 'url' => 'modules/projects/index.php'],
        $openingBalanceItem,
        ['active' => 'disbursements', 'label_key' => 'navigation.monthly_transfers', 'icon' => 'fa-money-check-dollar', 'url' => 'modules/accounting/disbursements.php'],
        ['active' => 'my_nannies', 'label_key' => 'navigation.assigned_nannies', 'icon' => 'fa-user-nurse', 'url' => 'modules/accounting/my_nannies.php'],
        ['active' => 'review', 'label_key' => 'navigation.financial_review_queue', 'icon' => 'fa-clipboard-check', 'url' => 'modules/accounting/fm_review_queue.php'],
        ['active' => 'reconciliation', 'label_key' => 'navigation.reconciliation_report', 'icon' => 'fa-scale-balanced', 'url' => 'modules/accounting/gm_reconciliation.php'],
    ],

    'accountant' => array_merge([
        ['active' => 'dashboard', 'label_key' => 'navigation.dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/accountant_dashboard.php'],
        $reportsItem,
        ['active' => 'journal', 'label_key' => 'navigation.journal', 'icon' => 'fa-book', 'url' => 'modules/accounting/journal.php'],
        ['active' => 'accounts', 'label_key' => 'navigation.chart_of_accounts', 'icon' => 'fa-sitemap', 'url' => 'modules/accounting/accounts.php'],
        ['active' => 'transactions', 'label_key' => 'navigation.transaction_register', 'icon' => 'fa-money-bill-transfer', 'url' => 'modules/transactions/index.php'],
        ['active' => 'my_nannies', 'label_key' => 'navigation.assigned_nannies', 'icon' => 'fa-user-nurse', 'url' => 'modules/accounting/my_nannies.php'],
    ], $acctManage, [$disbItem, $fmDashItem, $fmReviewItem, $reconItem]),

    'accountant_staff' => [
        ['active' => 'dashboard', 'label_key' => 'navigation.dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/accountant_staff_dashboard.php'],
        $transactionsItem, $disbItem, $orphanFormsItem,
        ['active' => 'my_nannies', 'label_key' => 'navigation.assigned_nannies', 'icon' => 'fa-user-nurse', 'url' => 'modules/accounting/my_nannies.php'],
        $reportsItem,
    ],

    'nanny' => [
        ['active' => 'dashboard', 'label_key' => 'navigation.dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/nanny_dashboard.php'],
        ['active' => 'families', 'label_key' => 'navigation.family_register', 'icon' => 'fa-house-chimney', 'url' => 'modules/families/index.php'],
        $disbItem, $orphanFormsItem,
        $reportsItem,
    ],

    'supervisor' => [
        ['active' => 'dashboard', 'label_key' => 'navigation.dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/supervisor_dashboard.php'],
        ['active' => 'sponsors', 'label_key' => 'navigation.sponsor_register', 'icon' => 'fa-hand-holding-heart', 'url' => 'modules/sponsors/index.php'],
        ['active' => 'families', 'label_key' => 'navigation.family_register', 'icon' => 'fa-house-chimney', 'url' => 'modules/families/index.php'],
        $orphanFormsItem,
        ['active' => 'sponsorships', 'label_key' => 'navigation.sponsorship_register', 'icon' => 'fa-file-contract', 'url' => 'modules/sponsorships/index.php'],
        $reportsItem,
    ],

    'staff' => [
        ['active' => 'dashboard', 'label_key' => 'navigation.dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/staff_dashboard.php'],
        ['active' => 'requests', 'label_key' => 'navigation.join_requests', 'icon' => 'fa-user-plus', 'url' => 'modules/sponsors/requests.php'],
        $orphanFormsItem,
    ],

    'social_media' => [
        ['active' => 'dashboard', 'label_key' => 'navigation.dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/staff_dashboard.php'],
        ['active' => 'requests', 'label_key' => 'navigation.join_requests', 'icon' => 'fa-user-plus', 'url' => 'modules/sponsors/requests.php'],
        $orphanFormsItem,
    ],

    'projects_manager' => [
        ['active' => 'projects_dashboard', 'label_key' => 'navigation.projects_dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/projects_dashboard.php'],
        ['active' => 'projects', 'label_key' => 'navigation.project_portfolio', 'icon' => 'fa-diagram-project', 'url' => 'modules/projects/index.php'],
    ],
    'project_supervisor' => [
        ['active' => 'projects_dashboard', 'label_key' => 'navigation.projects_monitoring_dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/projects_dashboard.php'],
        ['active' => 'projects', 'label_key' => 'navigation.my_projects', 'icon' => 'fa-folder-open', 'url' => 'modules/projects/index.php'],
    ],
    'hr_manager' => [
        ['active' => 'dashboard', 'label_key' => 'navigation.dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/hr_dashboard.php'],
        $reportsItem,
        ['active' => 'employees', 'label_key' => 'navigation.employees', 'icon' => 'fa-users', 'url' => 'modules/hr/employees.php'],
        ['active' => 'employment_states', 'label_key' => 'navigation.employment_states', 'icon' => 'fa-id-badge', 'url' => 'modules/hr/employment_states.php'],
        ['active' => 'attendance', 'label_key' => 'navigation.attendance', 'icon' => 'fa-clock', 'url' => 'modules/hr/attendance.php'],
        ['active' => 'leaves', 'label_key' => 'navigation.leave_requests', 'icon' => 'fa-calendar-alt', 'url' => 'modules/hr/leaves.php'],
        ['active' => 'payroll', 'label_key' => 'navigation.payroll', 'icon' => 'fa-money-bill-wave', 'url' => 'modules/hr/payroll.php'],
        ['active' => 'contracts', 'label_key' => 'navigation.contracts', 'icon' => 'fa-file-contract', 'url' => 'modules/hr/contracts.php'],
    ],
    'hr_staff' => [
        ['active' => 'dashboard', 'label_key' => 'navigation.dashboard', 'icon' => 'fa-gauge-high', 'url' => 'dashboard/hr_dashboard.php'],
        $reportsItem,
        ['active' => 'employees', 'label_key' => 'navigation.employees', 'icon' => 'fa-users', 'url' => 'modules/hr/employees.php'],
        ['active' => 'employment_states', 'label_key' => 'navigation.employment_states', 'icon' => 'fa-id-badge', 'url' => 'modules/hr/employment_states.php'],
        ['active' => 'attendance', 'label_key' => 'navigation.attendance', 'icon' => 'fa-clock', 'url' => 'modules/hr/attendance.php'],
        ['active' => 'leaves', 'label_key' => 'navigation.leave_requests', 'icon' => 'fa-calendar-alt', 'url' => 'modules/hr/leaves.php'],
        ['active' => 'payroll', 'label_key' => 'navigation.payroll', 'icon' => 'fa-money-bill-wave', 'url' => 'modules/hr/payroll.php'],
        ['active' => 'contracts', 'label_key' => 'navigation.contracts', 'icon' => 'fa-file-contract', 'url' => 'modules/hr/contracts.php'],
    ],
];

$personal = [
    ['active' => 'profile', 'label_key' => 'navigation.profile', 'icon' => 'fa-user', 'url' => 'modules/users/profile.php'],
    ['active' => 'logout', 'label_key' => 'navigation.logout', 'icon' => 'fa-right-from-bracket', 'url' => 'logout.php'],
];

$menu = array_merge($menus[$role] ?? [], $personal);
?>
<!-- AK-SIDEBAR-ROLE: raw=<?php echo e($raw_role); ?>| rid=<?php echo $rid; ?>| resolved=<?php echo e($role); ?> -->
<aside id="sidebar" class="sidebar">
<div class="sidebar-brand">
<img src="<?php echo APP_URL; ?>assets/img/logo.png" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:50%;background:#fff;border:2px solid rgba(255,255,255,.25)">
<div>
<div class="brand-title"><?php echo e(t('common.brand_name')); ?></div>
<div class="brand-subtitle"><?php echo e(t('common.system_management')); ?></div>
</div>
</div>
<ul class="sidebar-menu">
<?php foreach ($menu as $item): ?>
<?php if (isset($item['type']) && $item['type'] === 'dropdown'): ?>
<li class="sidebar-dropdown">
<a href="#" class="sidebar-link dropdown-trigger"><i class="fas <?php echo $item['icon']; ?>"></i><span><?php echo e(t($item['label_key'])); ?></span></a>
<ul class="sidebar-dropdown-menu">
<?php foreach ($item['items'] as $subItem): ?>
<li><a href="<?php echo APP_URL . $subItem['url']; ?>" class="sidebar-link sub-link <?php echo ($active === $subItem['active']) ? 'active' : ''; ?>"><i class="fas <?php echo $subItem['icon']; ?>"></i><span><?php echo e(t($subItem['label_key'])); ?></span></a></li>
<?php endforeach; ?>
</ul>
</li>
<?php else: ?>
<li><a href="<?php echo APP_URL . $item['url']; ?>" class="sidebar-link <?php echo ($active === $item['active']) ? 'active' : ''; ?>"><i class="fas <?php echo $item['icon']; ?>"></i><span><?php echo e(t($item['label_key'])); ?></span></a></li>
<?php endif; ?>
<?php endforeach; ?>
</ul>
</aside>

<style>
.sidebar-link { display:flex; align-items:center; padding:.75rem 1.25rem; color:rgba(255,255,255,.85); text-decoration:none; transition:all .2s ease; }
.sidebar-link:hover { background:rgba(255,255,255,.1); color:#fff; }
.sidebar-link.active { background:rgba(13,110,253,.3); color:#fff; border-right:3px solid #0d6efd; }
[dir="rtl"] .sidebar-link.active { border-right:none; border-left:3px solid #0d6efd; }
.sidebar-link i { width:24px; margin-left:.5rem; font-size:1rem; }
[dir="rtl"] .sidebar-link i { margin-left:0; margin-right:.5rem; }
</style>