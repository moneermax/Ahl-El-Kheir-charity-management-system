<?php

/*
|--------------------------------------------------------------------------
| includes/header.php
|--------------------------------------------------------------------------
| Main application header
|--------------------------------------------------------------------------
*/

// Centralized role-based permissions for the global search UI.
require_once dirname(__DIR__) . '/config/search_permissions.php';

$allowedSearchTypes = ak_search_allowed_types(current_user_role());
$canGlobalSearch = !empty($allowedSearchTypes);


/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$notifUnread = 0;

$notifItems = [];


try {

    $notifUnread =
        (int)(
            dbFetchOne(
                "SELECT COUNT(*) c
                 FROM notifications
                 WHERE recipient_user_id = ?
                 AND is_read = 0",
                [
                    current_user_id()
                ]
            )['c'] ?? 0
        );


    $notifItems =
        dbFetchAll(
            "SELECT
                title,
                body,
                link,
                created_at
             FROM notifications
             WHERE recipient_user_id = ?
             ORDER BY id DESC
             LIMIT 8",
            [
                current_user_id()
            ]
        );

} catch (Throwable $e) {

    $notifUnread = 0;
    $notifItems = [];
}


/*
|--------------------------------------------------------------------------
| Pending password recoveries
|--------------------------------------------------------------------------
*/

$pendingRecoveries = 0;


if (
    in_array(
        current_user_role(),
        [
            'admin',
            'hr_manager'
        ],
        true
    )
) {

    try {

        $pendingRecoveries =
            (int)(
                dbFetchOne(
                    "SELECT COUNT(*) c
                     FROM password_recovery_requests
                     WHERE status = 'pending'"
                )['c'] ?? 0
            );

    } catch (Throwable $e) {

        $pendingRecoveries = 0;
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT USER AVATAR
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Physical filesystem path:
|
| D:/xampp/htdocs/AhlElKheir/
|     storage/avatars/user_X.jpg
|
| Browser URL:
|
| http://localhost:8081/AhlElKheir/
|     storage/avatars/user_X.jpg
|
| We deliberately keep these two concepts separate.
|--------------------------------------------------------------------------
*/

$avatarUrl = '';


try {


    /*
     * Current logged-in user.
     */
    $headerUserId =
        current_user_id();


    /*
     * Get avatar path from database.
     */
    $avatarRecord =
        dbFetchOne(
            "SELECT avatar_path
             FROM users
             WHERE id = ?",
            [
                $headerUserId
            ]
        );


    if (
        $avatarRecord &&
        !empty($avatarRecord['avatar_path'])
    ) {


        /*
         * Database example:
         *
         * storage/avatars/user_5_123456.jpg
         *
         * We only need:
         *
         * user_5_123456.jpg
         */
        $headerAvatarFileName =
            basename(
                (string)$avatarRecord['avatar_path']
            );


        /*
         * Physical filesystem path.
         */
        $headerAvatarPhysicalPath =
            dirname(__DIR__) .
            DIRECTORY_SEPARATOR .
            'storage' .
            DIRECTORY_SEPARATOR .
            'avatars' .
            DIRECTORY_SEPARATOR .
            $headerAvatarFileName;


        /*
         * Verify that Apache/PHP has an actual file
         * at the expected filesystem location.
         */
        if (
            is_file(
                $headerAvatarPhysicalPath
            )
        ) {


            /*
             * Construct browser URL.
             *
             * APP_URL example:
             *
             * http://localhost:8081/AhlElKheir/
             *
             * Result:
             *
             * http://localhost:8081/AhlElKheir/
             * storage/avatars/user_5_123456.jpg
             */
            $avatarUrl =
                APP_URL .
                'storage/avatars/' .
                rawurlencode(
                    $headerAvatarFileName
                );


            /*
             * Cache buster.
             *
             * Added ONCE.
             */
            $headerAvatarModifiedTime =
                filemtime(
                    $headerAvatarPhysicalPath
                );


            if (
                $headerAvatarModifiedTime !== false
            ) {

                $avatarUrl .=
                    '?v=' .
                    $headerAvatarModifiedTime;
            }
        }
    }


} catch (Throwable $e) {

    /*
     * Never allow avatar problems to break the header.
     */
    $avatarUrl = '';
}


/*
|--------------------------------------------------------------------------
| Notification / language URLs
|--------------------------------------------------------------------------
*/

$markReadUrl =
    e(
        $_SERVER['REQUEST_URI'] .
        (
            strpos(
                (string)$_SERVER['REQUEST_URI'],
                '?'
            ) !== false
                ? '&'
                : '?'
        ) .
        'mark_notif_read=1'
    );


$uri =
    (string)(
        $_SERVER['REQUEST_URI']
        ?? ''
    );


$langSwitchUrl =
    e(
        $uri .
        (
            strpos(
                $uri,
                '?'
            ) !== false
                ? '&'
                : '?'
        ) .
        'lang=' .
        (
            AK_LANG === 'ar'
                ? 'en'
                : 'ar'
        )
    );


/*
|--------------------------------------------------------------------------
| HEADER ACTIONS
|--------------------------------------------------------------------------
|
| Navigation belongs to the sidebar. The header contains the centered
| global controls and direct user actions for quick access without a menu.
|--------------------------------------------------------------------------
*/

$raw_role =
    (string)current_user_role();


$aliasMap = [

    'sudo' =>
        'admin',

    'gm' =>
        'general_manager',

    'vgm' =>
        'vice_general_manager',

    'fm' =>
        'financial_manager'

];


$resolved_role =
    $aliasMap[$raw_role]
    ?? $raw_role;


$qaMap = [

    'admin' => [
        ['label' => 'إدارة المستخدمين', 'url' => 'modules/users/index.php', 'icon' => 'fa-users-gear', 'color' => '#0d6efd'],
        ['label' => 'سجل التدقيق', 'url' => 'modules/logs/audit.php', 'icon' => 'fa-file-lines', 'color' => '#6c757d'],
        ['label' => 'إدارة قاعدة البيانات', 'url' => 'modules/system/database.php', 'icon' => 'fa-database', 'color' => '#dc3545'],
        ['label' => 'إعدادات النظام', 'url' => 'modules/settings/index.php', 'icon' => 'fa-gear', 'color' => '#6610f2'],
    ],

    'general_manager' => [
        ['label' => 'التقارير العامة', 'url' => 'modules/reports/index.php', 'icon' => 'fa-chart-line', 'color' => '#0d6efd'],
        ['label' => 'مشاريع المنظمة', 'url' => 'modules/projects/index.php', 'icon' => 'fa-diagram-project', 'color' => '#17a2b8'],
    ],

    'vice_general_manager' => [
        ['label' => 'سجل الأسر', 'url' => 'modules/families/index.php', 'icon' => 'fa-house-chimney', 'color' => '#20c997'],
        ['label' => 'مجموعات الأيتام', 'url' => 'modules/deputy_gm/groups.php', 'icon' => 'fa-people-group', 'color' => '#6f42c1'],
        ['label' => 'سجل الكفالات', 'url' => 'modules/sponsorships/index.php', 'icon' => 'fa-file-contract', 'color' => '#6f42c1'],
        ['label' => 'حالات الإيقاف', 'url' => 'modules/families/suspensions.php', 'icon' => 'fa-user-slash', 'color' => '#dc3545'],
        ['label' => 'التقارير العامة', 'url' => 'modules/reports/index.php', 'icon' => 'fa-chart-line', 'color' => '#0d6efd'],
    ],

    'financial_manager' => [
        ['label' => 'دفتر اليومية', 'url' => 'modules/accounting/journal.php', 'icon' => 'fa-book', 'color' => '#1b4d8f'],
        ['label' => 'مشاريع المنظمة', 'url' => 'modules/projects/index.php', 'icon' => 'fa-diagram-project', 'color' => '#17a2b8'],
        ['label' => 'الأرصدة الافتتاحية', 'url' => 'modules/accounting/opening_balance.php', 'icon' => 'fa-vault', 'color' => '#6f42c1'],
        ['label' => 'التحويلات الشهرية', 'url' => 'modules/accounting/disbursements.php', 'icon' => 'fa-money-check-dollar', 'color' => '#28a745'],
        ['label' => 'الحاضنات المسندة', 'url' => 'modules/accounting/my_nannies.php', 'icon' => 'fa-user-nurse', 'color' => '#20c997'],
        ['label' => 'طابور المراجعة المالية', 'url' => 'modules/accounting/fm_review_queue.php', 'icon' => 'fa-clipboard-check', 'color' => '#ffc107'],
        ['label' => 'التقارير', 'url' => 'modules/reports/index.php', 'icon' => 'fa-chart-line', 'color' => '#0d6efd'],
    ],

    'accountant' => [
        ['label' => 'دفتر اليومية', 'url' => 'modules/accounting/journal.php', 'icon' => 'fa-book', 'color' => '#1b4d8f'],
        ['label' => 'دليل الحسابات', 'url' => 'modules/accounting/accounts.php', 'icon' => 'fa-sitemap', 'color' => '#2195c4'],
        ['label' => 'سجل المعاملات', 'url' => 'modules/transactions/index.php', 'icon' => 'fa-money-bill-transfer', 'color' => '#20c997'],
        ['label' => 'التحويلات الشهرية', 'url' => 'modules/accounting/disbursements.php', 'icon' => 'fa-money-check-dollar', 'color' => '#28a745'],
        ['label' => 'الحاضنات المسندة', 'url' => 'modules/accounting/my_nannies.php', 'icon' => 'fa-user-nurse', 'color' => '#6f42c1'],
        ['label' => 'التقارير', 'url' => 'modules/reports/index.php', 'icon' => 'fa-chart-line', 'color' => '#0d6efd'],
    ],

    'accountant_staff' => [
        ['label' => 'سجل المعاملات', 'url' => 'modules/transactions/index.php', 'icon' => 'fa-money-bill-transfer', 'color' => '#20c997'],
        ['label' => 'التحويلات الشهرية', 'url' => 'modules/accounting/disbursements.php', 'icon' => 'fa-money-check-dollar', 'color' => '#28a745'],
        ['label' => 'استمارات الأيتام', 'url' => 'modules/families/orphan_forms_index.php', 'icon' => 'fa-file-signature', 'color' => '#fd7e14'],
        ['label' => 'الحاضنات المسندة', 'url' => 'modules/accounting/my_nannies.php', 'icon' => 'fa-user-nurse', 'color' => '#6f42c1'],
        ['label' => 'التقارير', 'url' => 'modules/reports/index.php', 'icon' => 'fa-chart-line', 'color' => '#0d6efd'],
    ],

    'supervisor' => [
        ['label' => 'سجل الكفلاء', 'url' => 'modules/sponsors/index.php', 'icon' => 'fa-hand-holding-heart', 'color' => '#e83e8c'],
        ['label' => 'سجل الأسر', 'url' => 'modules/families/index.php', 'icon' => 'fa-house-chimney', 'color' => '#20c997'],
        ['label' => 'استمارات الأيتام', 'url' => 'modules/families/orphan_forms_index.php', 'icon' => 'fa-file-signature', 'color' => '#fd7e14'],
        ['label' => 'سجل الكفالات', 'url' => 'modules/sponsorships/index.php', 'icon' => 'fa-file-contract', 'color' => '#6f42c1'],
        ['label' => 'التقارير', 'url' => 'modules/reports/index.php', 'icon' => 'fa-chart-line', 'color' => '#0d6efd'],
    ],

    'nanny' => [
        ['label' => 'سجل الأسر', 'url' => 'modules/families/index.php', 'icon' => 'fa-house-chimney', 'color' => '#20c997'],
        ['label' => 'التحويلات الشهرية', 'url' => 'modules/accounting/disbursements.php', 'icon' => 'fa-money-check-dollar', 'color' => '#28a745'],
        ['label' => 'استمارات الأيتام', 'url' => 'modules/families/orphan_forms_index.php', 'icon' => 'fa-file-signature', 'color' => '#fd7e14'],
        ['label' => 'التقارير', 'url' => 'modules/reports/index.php', 'icon' => 'fa-chart-line', 'color' => '#0d6efd'],
    ],

    'administration' => [
        ['label' => 'طلبات الانضمام / الرعاية', 'url' => 'modules/sponsors/requests.php', 'icon' => 'fa-user-plus', 'color' => '#0d6efd'],
        ['label' => 'استمارات الأيتام', 'url' => 'modules/families/orphan_forms_index.php', 'icon' => 'fa-file-signature', 'color' => '#fd7e14'],
        ['label' => 'متابعة الاسترجاع', 'url' => 'modules/administration/winback.php', 'icon' => 'fa-rotate-left', 'color' => '#6f42c1'],
    ],

    'staff' => [
        ['label' => 'طلبات الانضمام / الرعاية', 'url' => 'modules/sponsors/requests.php', 'icon' => 'fa-user-plus', 'color' => '#0d6efd'],
        ['label' => 'استمارات الأيتام', 'url' => 'modules/families/orphan_forms_index.php', 'icon' => 'fa-file-signature', 'color' => '#fd7e14'],
    ],

    'social_media' => [
        ['label' => 'طلبات الانضمام / الرعاية', 'url' => 'modules/sponsors/requests.php', 'icon' => 'fa-user-plus', 'color' => '#0d6efd'],
        ['label' => 'استمارات الأيتام', 'url' => 'modules/families/orphan_forms_index.php', 'icon' => 'fa-file-signature', 'color' => '#fd7e14'],
    ],

    'projects_manager' => [
        ['label' => 'مشاريع المنظمة', 'url' => 'modules/projects/index.php', 'icon' => 'fa-diagram-project', 'color' => '#17a2b8'],
    ],

    'project_supervisor' => [
        ['label' => 'مشاريعي', 'url' => 'modules/projects/index.php', 'icon' => 'fa-folder-open', 'color' => '#17a2b8'],
    ],

    'hr_manager' => [
        ['label' => 'الموظفون', 'url' => 'modules/hr/employees.php', 'icon' => 'fa-users', 'color' => '#1b4d8f'],
        ['label' => 'حالات التوظيف', 'url' => 'modules/hr/employment_states.php', 'icon' => 'fa-id-badge', 'color' => '#6f42c1'],
        ['label' => 'الحضور والانصراف', 'url' => 'modules/hr/attendance.php', 'icon' => 'fa-clock', 'color' => '#6c757d'],
        ['label' => 'طلبات الإجازة', 'url' => 'modules/hr/leaves.php', 'icon' => 'fa-calendar-alt', 'color' => '#ffc107'],
        ['label' => 'كشف الرواتب', 'url' => 'modules/hr/payroll.php', 'icon' => 'fa-money-bill-wave', 'color' => '#28a745'],
        ['label' => 'العقود', 'url' => 'modules/hr/contracts.php', 'icon' => 'fa-file-contract', 'color' => '#17a2b8'],
        ['label' => 'التقارير', 'url' => 'modules/reports/index.php', 'icon' => 'fa-chart-line', 'color' => '#0d6efd'],
    ],

    'hr_staff' => [
        ['label' => 'الموظفون', 'url' => 'modules/hr/employees.php', 'icon' => 'fa-users', 'color' => '#1b4d8f'],
        ['label' => 'حالات التوظيف', 'url' => 'modules/hr/employment_states.php', 'icon' => 'fa-id-badge', 'color' => '#6f42c1'],
        ['label' => 'الحضور والانصراف', 'url' => 'modules/hr/attendance.php', 'icon' => 'fa-clock', 'color' => '#6c757d'],
        ['label' => 'طلبات الإجازة', 'url' => 'modules/hr/leaves.php', 'icon' => 'fa-calendar-alt', 'color' => '#ffc107'],
        ['label' => 'كشف الرواتب', 'url' => 'modules/hr/payroll.php', 'icon' => 'fa-money-bill-wave', 'color' => '#28a745'],
        ['label' => 'العقود', 'url' => 'modules/hr/contracts.php', 'icon' => 'fa-file-contract', 'color' => '#17a2b8'],
        ['label' => 'التقارير', 'url' => 'modules/reports/index.php', 'icon' => 'fa-chart-line', 'color' => '#0d6efd'],
    ],

];

$currentQuickActions =
    $qaMap[$resolved_role]
    ?? [];


/*
|--------------------------------------------------------------------------
| Universal Request Leave button
|--------------------------------------------------------------------------
*/

if (Session::isLoggedIn()) {

    $currentQuickActions[] = [

        'label' =>
            'طلب إجازة',

        'url' =>
            'modules/hr/leaves.php?action=request',

        'icon' =>
            'fa-calendar-plus',

        'color' =>
            '#17a2b8',

        'is_universal' =>
            true

    ];
}

?>

<!DOCTYPE html>

<html
    lang="<?php echo e(AK_LANG); ?>"
    dir="<?php echo e(AK_DIR); ?>"
>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php
        echo e(
            $pageTitle
            ?? 'أهل الخير'
        );
        ?>
        | نظام إدارة الكفالات
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap"
        rel="stylesheet"
    >


    <style>

        :root {
            --navy: #1b4d8f;
            --navy-dark: #143a6b;
        }


        body {
            font-family: 'Cairo', sans-serif;
            background: #f4f7fb;
            margin: 0;
        }


        .app-wrapper {
            min-height: 100vh;
        }


        :root {
            --ak-sidebar-width: clamp(260px, 22vw, 320px);
        }


        .sidebar {
            width: var(--ak-sidebar-width);
            background: var(--navy);
            color: #fff;
            position: fixed;
            top: 0;
            right: 0;
            left: auto;
            height: 100vh;
            overflow-y: auto;
            z-index: 1200;
            box-shadow: -10px 0 30px rgba(0,0,0,0.22);
            transform: translateX(0);
            transition: transform 0.28s cubic-bezier(.22,.61,.36,1);
            will-change: transform;
        }


        [dir="ltr"] .sidebar {
            right: auto;
            left: 0;
            box-shadow: 10px 0 30px rgba(0,0,0,0.22);
        }


        body:not(.sidebar-open) .sidebar {
            transform: translateX(100%);
        }


        [dir="ltr"] body:not(.sidebar-open) .sidebar {
            transform: translateX(-100%);
        }


        .ak-sidebar-toggle {
            position: fixed;
            top: 50%;
            right: 0;
            transform: translateY(-50%);
            z-index: 1202;
            width: 28px;
            height: 118px;
            border: 0;
            border-radius: 18px 0 0 18px;
            background: var(--navy);
            color: #fff;
            box-shadow: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: right .28s cubic-bezier(.22,.61,.36,1),
                        left .28s cubic-bezier(.22,.61,.36,1),
                        transform .18s ease,
                        background .18s ease,
                        box-shadow .18s ease,
                        border-radius .18s ease;
        }


        .ak-sidebar-toggle:hover {
            background: var(--navy-dark);
            box-shadow: none;
        }


        .ak-sidebar-toggle:active {
            transform: translateY(-50%) scale(.94);
        }


        .ak-sidebar-toggle:focus-visible {
            outline: 3px solid rgba(13,110,253,.45);
            outline-offset: 3px;
        }


        body.sidebar-open .ak-sidebar-toggle {
            right: var(--ak-sidebar-width);
            border-radius: 0;
        }


        [dir="ltr"] .ak-sidebar-toggle {
            right: auto;
            left: 0;
            border-radius: 0 18px 18px 0;
        }


        [dir="ltr"] body.sidebar-open .ak-sidebar-toggle {
            left: var(--ak-sidebar-width);
            border-radius: 0;
        }


        .ak-sidebar-toggle i {
            font-size: 1rem;
        }


        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }


        .brand-title {
            font-size: 1.3rem;
            font-weight: 800;
        }


        .brand-subtitle {
            font-size: 0.75rem;
            opacity: 0.8;
        }


        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }


        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            transition: all 0.2s;
        }


        .sidebar-link:hover,
        .sidebar-link.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }


        .sidebar-link i {
            width: 20px;
            text-align: center;
        }


        .main-area {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }


        /* Sticky header container - MUST be at top of main-area */
        .header-sticky-wrapper {
            position: sticky;
            top: 0;
            z-index: 1050;
            background: var(--navy);
            flex-shrink: 0;
        }

        /* Organization header banner */
        .org-header-banner {
            background: var(--navy);
            color: #fff;
            text-align: center;
            padding: 10px 20px;
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            font-family: 'Cairo', sans-serif;
            flex-shrink: 0;
        }

        .org-header-banner .org-name {
            font-size: 1.3rem;
            font-weight: 800;
        }

        .org-header-banner .org-flag {
            font-size: 1.5rem;
            margin: 0 6px;
        }

        body.theme-dark .org-header-banner {
            background: #1a202c;
            border-bottom-color: #2d3748;
        }

        body.theme-dark .header-sticky-wrapper {
            background: #1a202c;
        }


        .qa-top-bar {
            background: var(--navy);
            color: #fff;
            padding: 8px 20px 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.10);
        }

        .qa-actions {
            width: min(100%, 1180px);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .qa-group {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 6px;
            padding: 3px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 10px;
            background: rgba(255,255,255,.045);
        }

        .qa-group-label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 0 7px;
            color: rgba(255,255,255,.58);
            font-size: .62rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .qa-user-controls {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .qa-user-controls .qa-user-btn {
            margin-inline-end: 2px;
        }

        .qa-btn,
        .qa-user-btn,
        .ak-header-search-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 34px;
            padding: 5px 11px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 0.73rem;
            font-weight: 700;
            color: #fff !important;
            border: 1px solid rgba(255,255,255,0.22);
            white-space: nowrap;
            transition: transform .18s ease, background .18s ease, box-shadow .18s ease, filter .18s ease;
        }

        .qa-btn:hover,
        .qa-user-btn:hover,
        .ak-header-search-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0,0,0,.16);
            filter: brightness(1.08);
            color: #fff !important;
        }

        .qa-user-btn {
            background: rgba(255,255,255,0.12);
            cursor: default;
        }

        .qa-user-btn img {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(255,255,255,.45);
        }

        .qa-user-btn i.fa-user-circle {
            font-size: 1.15rem;
        }

        .ak-header-search-btn {
            background: rgba(255,255,255,0.12);
        }

        .ak-header-search-btn:focus-visible,
        .qa-btn:focus-visible {
            outline: 3px solid rgba(255,255,255,.72);
            outline-offset: 2px;
        }

        .ak-header-action-profile {
            background: rgba(255,255,255,0.10);
        }

        .ak-header-action-settings {
            background: rgba(255,255,255,0.10);
        }

        .ak-header-action-logout {
            background: rgba(220,53,69,.88);
            border-color: rgba(255,255,255,.28);
        }

        .ak-header-action-logout:hover {
            background: #dc3545;
        }

        .ak-header-actions-divider {
            width: 1px;
            height: 25px;
            background: rgba(255,255,255,.20);
            margin: 0 2px;
        }

        .ak-dd {
            position: relative;
            display: inline-flex;
        }

        .ak-dd-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: auto;
            min-width: 190px;
            background: #fff;
            border: 1px solid #e3e7ee;
            border-radius: 9px;
            box-shadow: 0 10px 28px rgba(10,31,68,.20);
            z-index: 1205;
            padding: 5px;
            color: #333;
            text-align: start;
        }

        [dir="ltr"] .ak-dd-menu {
            left: auto;
            right: 0;
        }

        .ak-dd.open .ak-dd-menu {
            display: block;
        }

        .ak-dd-item {
            display: block;
            padding: 8px 10px;
            border-radius: 6px;
            color: #21315b;
            text-decoration: none;
            font-size: .78rem;
            font-weight: 600;
        }

        .ak-dd-item:hover {
            background: #eef2f9;
        }

        .ak-dd-item.text-danger {
            color: #dc3545;
        }

        .ak-dd-item.text-danger:hover {
            background: #ffe5e5;
        }

        .dropdown-divider {
            height: 0;
            margin: 4px 0;
            overflow: hidden;
            border-top: 1px solid #e3e7ee;
        }

        .qa-user-btn {
            cursor: pointer;
        }

        .qa-user-btn .qa-user-chevron {
            font-size: .62rem;
            opacity: .75;
            transition: transform .18s ease;
        }

        .ak-dd.open .qa-user-chevron {
            transform: rotate(180deg);
        }

        @media (max-width: 991.98px) {
            .ak-sidebar-toggle {
                width: 28px;
                height: 104px;
                right: 0;
                border-radius: 14px 0 0 14px;
            }

            body.sidebar-open .ak-sidebar-toggle {
                right: var(--ak-sidebar-width);
                border-radius: 0;
            }

            [dir="ltr"] .ak-sidebar-toggle {
                right: auto;
                left: 0;
                border-radius: 0 16px 16px 0;
            }

            [dir="ltr"] body.sidebar-open .ak-sidebar-toggle {
                left: var(--ak-sidebar-width);
                border-radius: 0;
            }

            .sidebar-overlay {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.45);
                z-index: 1190;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: opacity 0.25s ease, visibility 0.25s ease;
            }

            body.sidebar-open .sidebar-overlay {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }

            .ak-sidebar-toggle {
                width: 28px;
                height: 104px;
                right: 0;
                border-radius: 14px 0 0 14px;
            }

            body.sidebar-open .ak-sidebar-toggle {
                right: var(--ak-sidebar-width);
            }

            [dir="ltr"] .ak-sidebar-toggle {
                right: auto;
                left: 0;
                border-radius: 0 14px 14px 0;
            }

            [dir="ltr"] body.sidebar-open .ak-sidebar-toggle {
                left: var(--ak-sidebar-width);
            }

            .main-area {
                width: 100%;
                min-width: 0;
            }
        }

        @media (max-width: 768px) {
            .qa-top-bar {
                padding: 8px 10px 10px;
            }

            .qa-actions {
                gap: 6px;
            }

            .qa-group {
                width: auto;
                max-width: 100%;
            }

            .qa-group-label {
                display: none;
            }

            .qa-user-controls {
                gap: 5px;
            }

            .qa-btn,
            .qa-user-btn,
            .ak-header-search-btn {
                min-height: 32px;
                padding: 4px 8px;
                font-size: 0.68rem;
            }

            .qa-user-btn span {
                max-width: 120px;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .ak-header-actions-divider {
                display: none;
            }
        }

        .content {
            padding: 24px;
            flex-grow: 1;
            background: #f4f7fb;
        }


        .welcome-section {
            margin-bottom: 24px;
        }


        .welcome-section h2 {
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 4px;
        }


        .fade-in {
            animation: fadeIn 0.4s ease-in-out;
        }


        @keyframes fadeIn {

            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }

        }



    </style>


    <script>
    function akToggleUserMenu(event) {
        event.preventDefault();
        event.stopPropagation();

        var menu = document.getElementById('userDropdown');
        if (!menu) return;

        var willOpen = !menu.classList.contains('open');
        menu.classList.toggle('open', willOpen);

        var button = menu.querySelector('.qa-user-btn');
        if (button) {
            button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        }
    }

    document.addEventListener('click', function (event) {
        var menu = document.getElementById('userDropdown');
        if (!menu || !menu.classList.contains('open')) return;

        if (!menu.contains(event.target)) {
            menu.classList.remove('open');
            var button = menu.querySelector('.qa-user-btn');
            if (button) button.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;

        var menu = document.getElementById('userDropdown');
        if (!menu || !menu.classList.contains('open')) return;

        menu.classList.remove('open');
        var button = menu.querySelector('.qa-user-btn');
        if (button) {
            button.setAttribute('aria-expanded', 'false');
            button.focus();
        }
    });
    </script>

    <!-- Apply saved theme -->

    <script>

    document.addEventListener(
        'DOMContentLoaded',
        function () {

            const theme =
                localStorage.getItem(
                    'theme_preference'
                )
                ||
                '<?php
                    echo $_SESSION['theme_preference']
                        ?? 'light';
                ?>';


            if (
                theme &&
                theme !== 'auto'
            ) {

                document.body.classList.add(
                    'theme-' + theme
                );

            } else if (
                theme === 'auto'
            ) {

                if (
                    window.matchMedia &&
                    window.matchMedia(
                        '(prefers-color-scheme: dark)'
                    ).matches
                ) {

                    document.body.classList.add(
                        'theme-dark'
                    );
                }
            }

        }
    );

    </script>

</head>


<body>


<div class="app-wrapper">

    <button
        type="button"
        class="ak-sidebar-toggle"
        id="akSidebarToggle"
        aria-controls="sidebar"
        aria-expanded="false"
        title="<?php echo e(AK_LANG === 'ar' ? 'إظهار القائمة' : 'Show menu'); ?>"
    >
        <i class="fas fa-bars" aria-hidden="true"></i>
        <span class="visually-hidden"><?php echo e(AK_LANG === 'ar' ? 'إظهار القائمة' : 'Show menu'); ?></span>
    </button>

    <?php
    include __DIR__ . '/sidebar.php';
    ?>


    <div class="main-area">

        <!-- =========================================================
             STICKY HEADER WRAPPER - All header elements stay on top
             ========================================================= -->

        <div class="header-sticky-wrapper">

            <!-- =========================================================
                 ORGANIZATION HEADER BANNER
                 ========================================================= -->
            <div class="org-header-banner">
                <span class="org-flag">🇸🇩</span>
                <span class="org-name">منظمة أهل الخير النسوية لكفالة الأيتام</span>
                <span class="org-flag">🇸🇩</span>
            </div>

            <!-- =========================================================
                 CENTERED HEADER ACTIONS
                 ========================================================= -->
            <div class="qa-top-bar">
                <div class="qa-actions">

                    <?php if (!empty($currentQuickActions)): ?>
                        <div class="qa-group qa-group-primary">
                                            <?php foreach ($currentQuickActions as $qa): ?>
                        <a
                            href="<?php echo APP_URL . e($qa['url']); ?>"
                            class="qa-btn"
                            style="background: <?php echo !empty($qa['is_universal']) ? 'rgba(255,255,255,0.12)' : e($qa['color']); ?>;"
                        >
                            <i class="fas <?php echo e($qa['icon']); ?>" aria-hidden="true"></i>
                            <span><?php echo e($qa['label']); ?></span>
                        </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="qa-group qa-group-tools">
                    <?php if ($canGlobalSearch): ?>
                        <a
                            href="<?php echo APP_URL; ?>modules/search/index.php"
                            class="ak-header-search-btn"
                            data-search-types="<?php echo e(implode(',', $allowedSearchTypes)); ?>"
                            aria-label="<?php echo e(AK_LANG === 'ar' ? 'فتح البحث العام' : 'Open global search'); ?>"
                            title="<?php echo e(AK_LANG === 'ar' ? 'البحث العام' : 'Global Search'); ?>"
                        >
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <span><?php echo e(AK_LANG === 'ar' ? 'البحث' : 'Search'); ?></span>
                        </a>
                    <?php endif; ?>

                    <a
                        href="<?php echo e($langSwitchUrl); ?>"
                        class="qa-btn"
                        style="background: rgba(255,255,255,0.10);"
                        title="<?php echo e(AK_LANG === 'ar' ? 'تغيير اللغة' : 'Change language'); ?>"
                    >
                        <i class="fas fa-globe" aria-hidden="true"></i>
                        <span><?php echo AK_LANG === 'ar' ? 'EN' : 'عربي'; ?></span>
                    </a>

                    <?php if ($pendingRecoveries > 0): ?>
                        <a
                            href="<?php echo APP_URL; ?>modules/users/recovery.php"
                            class="qa-btn"
                            style="background: #dc3545;"
                            title="<?php echo e(AK_LANG === 'ar' ? 'طلبات استعادة كلمة المرور المعلقة' : 'Pending password recoveries'); ?>"
                        >
                            <i class="fas fa-key" aria-hidden="true"></i>
                            <span><?php echo $pendingRecoveries; ?></span>
                        </a>
                    <?php endif; ?>

                        </div>

                    <div class="ak-dd" id="userDropdown">
                        <button
                            type="button"
                            class="qa-user-btn"
                            aria-haspopup="true"
                            aria-expanded="false"
                            onclick="akToggleUserMenu(event)"
                        >
                            <?php if ($avatarUrl): ?>
                                <img src="<?php echo e($avatarUrl); ?>" alt="" aria-hidden="true">
                            <?php else: ?>
                                <i class="fas fa-user-circle" aria-hidden="true"></i>
                            <?php endif; ?>
                            <span><?php echo e(current_user_name()); ?></span>
                            <i class="fas fa-chevron-down qa-user-chevron" aria-hidden="true"></i>
                        </button>

                        <div class="ak-dd-menu" role="menu">
                            <a
                                href="<?php echo APP_URL; ?>modules/users/profile.php"
                                class="ak-dd-item"
                                role="menuitem"
                            >
                                <i class="fas fa-id-card me-2"></i>
                                <?php echo e(AK_LANG === 'ar' ? 'الملف الشخصي' : 'Profile'); ?>
                            </a>

                            <a
                                href="<?php echo APP_URL; ?>modules/users/settings.php"
                                class="ak-dd-item"
                                role="menuitem"
                            >
                                <i class="fas fa-cog me-2"></i>
                                <?php echo e(AK_LANG === 'ar' ? 'الإعدادات' : 'Settings'); ?>
                            </a>

                            <div class="dropdown-divider"></div>

                            <a
                                href="<?php echo APP_URL; ?>logout.php"
                                class="ak-dd-item text-danger"
                                role="menuitem"
                            >
                                <i class="fas fa-sign-out-alt me-2"></i>
                                <?php echo e(AK_LANG === 'ar' ? 'تسجيل الخروج' : 'Logout'); ?>
                            </a>
                        </div>
                    </div>

                </div>
            </div>

        </div>
        <!-- END header-sticky-wrapper -->

        <!-- =========================================================
             CONTENT
             ========================================================= -->

        <div class="content">


            <?php
            if (
                isset($_GET['mark_notif_read']) &&
                $_GET['mark_notif_read'] == '1'
            ):
            ?>

                <?php

                try {

                    dbQuery(
                        "UPDATE notifications
                         SET is_read = 1
                         WHERE recipient_user_id = ?",
                        [
                            current_user_id()
                        ]
                    );

                } catch (Throwable $e) {}

                header(
                    'Location: ' .
                    strtok(
                        $_SERVER["REQUEST_URI"],
                        '?'
                    )
                );

                exit;

                ?>

            <?php endif; ?>


            <?php if (!empty($_SESSION['flash'])): ?>


                <?php
                foreach (
                    $_SESSION['flash']
                    as $msg
                ):
                ?>


                    <div
                        class="alert alert-<?php
                            echo e(
                                $msg['type'] === 'error'
                                    ? 'danger'
                                    : $msg['type']
                            );
                        ?> alert-dismissible fade-show"
                        role="alert"
                    >

                        <?php
                        echo e(
                            $msg['message']
                        );
                        ?>


                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Close"
                        ></button>


                    </div>


                <?php endforeach; ?>


                <?php
                unset(
                    $_SESSION['flash']
                );
                ?>


            <?php endif; ?>