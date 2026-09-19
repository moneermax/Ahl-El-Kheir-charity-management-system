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
| Navigation belongs to the sidebar. The header is reserved for global
| controls and contextual actions, so role-specific destinations are not
| duplicated here.
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


$qaMap = [];

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
            right: 10px;
            transform: translateY(-50%);
            z-index: 1202;
            width: 38px;
            height: 92px;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 19px;
            background: rgba(20, 35, 58, .96);
            color: #fff;
            box-shadow: 0 8px 24px rgba(0,0,0,.22);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: right .28s cubic-bezier(.22,.61,.36,1),
                        left .28s cubic-bezier(.22,.61,.36,1),
                        transform .18s ease,
                        background .18s ease,
                        box-shadow .18s ease;
        }


        .ak-sidebar-toggle:hover {
            background: var(--navy-dark);
            box-shadow: 0 10px 28px rgba(0,0,0,.30);
        }


        .ak-sidebar-toggle:active {
            transform: translateY(-50%) scale(.94);
        }


        .ak-sidebar-toggle:focus-visible {
            outline: 3px solid rgba(13,110,253,.45);
            outline-offset: 3px;
        }


        body.sidebar-open .ak-sidebar-toggle {
            right: calc(var(--ak-sidebar-width) + 10px);
        }


        [dir="ltr"] .ak-sidebar-toggle {
            right: auto;
            left: 14px;
        }


        [dir="ltr"] body.sidebar-open .ak-sidebar-toggle {
            left: calc(var(--ak-sidebar-width) + 10px);
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
            padding: 8px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            gap: 10px;
            flex-wrap: nowrap;
        }


        .qa-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
            flex-grow: 1;
            justify-content: flex-start;
        }


        .qa-user-controls {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;

            <?php if (AK_DIR === 'rtl'): ?>

            margin-right: auto;
            margin-left: 0;

            <?php else: ?>

            margin-left: auto;
            margin-right: 0;

            <?php endif; ?>
        }


        .qa-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            color: #fff !important;
            transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.2);
            white-space: nowrap;
        }


        .qa-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            filter: brightness(1.15);
        }


        .qa-user-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            text-decoration: none;
            white-space: nowrap;
        }


        .qa-user-btn:hover {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }


        .ak-dd {
            position: relative;
        }


        .ak-dd-menu {
            display: none;
            position: absolute;
            top: calc(100% + 5px);

            <?php if (AK_DIR === 'rtl'): ?>

            left: 0;
            right: auto;

            <?php else: ?>

            right: 0;
            left: auto;

            <?php endif; ?>

            min-width: 180px;
            background: #fff;
            border: 1px solid #e3e7ee;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(10,31,68,.18);
            z-index: 1050;
            padding: 0.3rem;
            color: #333;
        }


        .ak-dd.open .ak-dd-menu {
            display: block;
        }


        .ak-dd-item {
            display: block;
            padding: 0.4rem 0.6rem;
            border-radius: 4px;
            color: #21315b;
            text-decoration: none;
            font-size: 0.8rem;
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
            margin: 0.2rem 0;
            overflow: hidden;
            border-top: 1px solid #e3e7ee;
        }


        .ak-header-search-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 32px;
            padding: 4px 11px;
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 6px;
            background: rgba(255,255,255,0.12);
            color: #fff !important;
            text-decoration: none;
            font-size: 0.72rem;
            font-weight: 700;
            white-space: nowrap;
            transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .ak-header-search-btn:hover {
            background: rgba(255,255,255,0.22);
            color: #fff !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.16);
        }

        .ak-header-search-btn:focus-visible {
            outline: 3px solid rgba(255,255,255,0.72);
            outline-offset: 2px;
        }

        .ak-header-search-btn i {
            font-size: 0.82rem;
        }

        .ak-mobile-menu-btn {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 32px;
            padding: 4px 10px;
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 6px;
            background: rgba(255,255,255,0.12);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            min-width: 92px;
            line-height: 1.2;
        }

        .ak-mobile-menu-icon {
            display: inline-block;
            font-family: Arial, sans-serif;
            font-size: 1.15rem;
            line-height: 1;
            font-weight: 700;
        }

        .ak-mobile-menu-btn:hover,
        .ak-mobile-menu-btn:focus-visible {
            background: rgba(255,255,255,0.22);
            color: #fff;
        }

        body.theme-dark .ak-header-search-btn {
            background: rgba(255,255,255,0.09);
            border-color: rgba(255,255,255,0.28);
        }

        @media (max-width: 991.98px) {
            .ak-sidebar-toggle {
                width: 36px;
                height: 84px;
                right: 8px;
                border-radius: 18px;
            }

            body.sidebar-open .ak-sidebar-toggle {
                right: calc(var(--ak-sidebar-width) + 8px);
            }

            [dir="ltr"] .ak-sidebar-toggle {
                right: auto;
                left: 8px;
            }

            [dir="ltr"] body.sidebar-open .ak-sidebar-toggle {
                left: calc(var(--ak-sidebar-width) + 8px);
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
                width: 48px;
                height: 48px;
                right: 12px;
            }

            body.sidebar-open .ak-sidebar-toggle {
                right: calc(var(--ak-sidebar-width) + 10px);
            }

            [dir="ltr"] .ak-sidebar-toggle {
                right: auto;
                left: 12px;
            }

            [dir="ltr"] body.sidebar-open .ak-sidebar-toggle {
                left: calc(var(--ak-sidebar-width) + 10px);
            }

            .main-area {
                width: 100%;
                min-width: 0;
            }
        }

        @media (max-width: 768px) {
            .qa-top-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .qa-actions {
                width: 100%;
                justify-content: center;
            }

            .qa-user-controls {
                width: 100%;
                justify-content: center;
                margin-right: 0 !important;
                margin-left: 0 !important;
            }

            .ak-header-search-btn span {
                display: none;
            }

            .ak-header-search-btn {
                width: 32px;
                padding: 4px;
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
        aria-expanded="true"
        title="<?php echo e(AK_LANG === 'ar' ? 'إخفاء القائمة' : 'Hide menu'); ?>"
    >
        <i class="fas fa-chevron-right" aria-hidden="true"></i>
        <span class="visually-hidden"><?php echo e(AK_LANG === 'ar' ? 'إخفاء القائمة' : 'Hide menu'); ?></span>
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
                 TOP BAR
                 ========================================================= -->

            <div class="qa-top-bar">

                <div class="qa-actions">

                    <?php
                    foreach (
                        $currentQuickActions
                        as $qa
                    ):
                    ?>

                        <?php
                        if (!empty($qa['is_universal'])):
                        ?>

                            <a
                                href="<?php
                                    echo APP_URL .
                                        e($qa['url']);
                                ?>"
                                class="qa-btn"
                                style="background: rgba(255,255,255,0.15);"
                            >

                                <i
                                    class="fas <?php
                                        echo e($qa['icon']);
                                    ?>"
                                ></i>

                                <?php
                                echo e($qa['label']);
                                ?>

                            </a>

                        <?php else: ?>

                            <a
                                href="<?php
                                    echo APP_URL .
                                        e($qa['url']);
                                ?>"
                                class="qa-btn"
                                style="background: <?php
                                    echo e($qa['color']);
                                ?>;"
                            >

                                <i
                                    class="fas <?php
                                        echo e($qa['icon']);
                                    ?>"
                                ></i>

                                <?php
                                echo e($qa['label']);
                                ?>

                            </a>

                        <?php endif; ?>

                    <?php endforeach; ?>

                </div>

                <!-- =====================================================
                     USER CONTROLS
                     ===================================================== -->

                <div class="qa-user-controls">

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
                        style="background: rgba(255,255,255,0.1);"
                    >

                        <i class="fas fa-globe"></i>

                        <?php
                        echo AK_LANG === 'ar'
                            ? 'EN'
                            : 'عربي';
                        ?>

                    </a>

                    <?php if ($pendingRecoveries > 0): ?>

                        <a
                            href="<?php
                                echo APP_URL;
                            ?>modules/users/recovery.php"
                            class="qa-btn"
                            style="background: #dc3545;"
                        >

                            <i class="fas fa-key"></i>

                            <span class="badge bg-white text-danger">

                                <?php
                                echo $pendingRecoveries;
                                ?>

                            </span>

                        </a>

                    <?php endif; ?>

                    <!-- =================================================
                         USER DROPDOWN
                         ================================================= -->

                    <div
                        class="ak-dd"
                        id="userDropdown"
                    >

                        <button
                            class="qa-user-btn"
                            onclick="
                                document
                                    .getElementById('userDropdown')
                                    .classList
                                    .toggle('open')
                            "
                        >

                            <?php if ($avatarUrl): ?>

                                <img
                                    src="<?php
                                        echo e($avatarUrl);
                                    ?>"
                                    alt="Avatar"
                                    style="
                                        width:22px;
                                        height:22px;
                                        border-radius:50%;
                                        object-fit:cover;
                                    "
                                >

                            <?php else: ?>

                                <i
                                    class="fas fa-user-circle fa-lg"
                                ></i>

                            <?php endif; ?>

                            <span>

                                <?php
                                echo e(
                                    current_user_name()
                                );
                                ?>

                            </span>

                            <i
                                class="fas fa-chevron-down"
                                style="font-size:0.65rem;"
                            ></i>

                        </button>

                        <div class="ak-dd-menu">

                            <a
                                href="<?php
                                    echo APP_URL;
                                ?>modules/users/profile.php"
                                class="ak-dd-item"
                            >

                                <i
                                    class="fas fa-id-card me-2"
                                ></i>

                                الملف الشخصي

                            </a>

                            <a
                                href="<?php
                                    echo APP_URL;
                                ?>modules/users/settings.php"
                                class="ak-dd-item"
                            >

                                <i
                                    class="fas fa-cog me-2"
                                ></i>

                                الإعدادات

                            </a>

                            <div class="dropdown-divider"></div>

                            <a
                                href="<?php
                                    echo APP_URL;
                                ?>logout.php"
                                class="ak-dd-item text-danger"
                            >

                                <i
                                    class="fas fa-sign-out-alt me-2"
                                ></i>

                                تسجيل الخروج

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