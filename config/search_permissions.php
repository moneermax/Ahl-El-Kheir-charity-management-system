<?php
/**
 * Centralized authorization policy for the global search.
 *
 * This controls WHICH search domains a role may request. Record-level
 * visibility remains enforced separately by the search queries.
 */

if (!function_exists('ak_search_allowed_types')) {
    function ak_search_allowed_types(?string $role = null): array
    {
        $role = (string)($role ?? current_user_role());

        $aliases = [
            'sudo' => 'admin',
            'gm'   => 'general_manager',
            'vgm'  => 'vice_general_manager',
            'fm'   => 'financial_manager',
            'finance' => 'financial_manager',
            'staff' => 'administration',
        ];

        $role = $aliases[$role] ?? $role;

        return [
            'admin' => ['families', 'sponsors', 'sponsorships', 'payments'],
            'general_manager' => ['families', 'sponsors', 'sponsorships', 'payments'],
            'vice_general_manager' => ['families', 'sponsors', 'sponsorships', 'payments'],
            'financial_manager' => ['families', 'sponsors', 'sponsorships', 'payments'],
            'accountant' => ['families', 'sponsors', 'sponsorships', 'payments'],
            'supervisor' => ['families', 'sponsors', 'sponsorships', 'payments'],
            'nanny' => ['families', 'sponsorships', 'payments'],
            'accountant_staff' => ['families', 'sponsorships', 'payments'],
            'administration' => ['families', 'sponsors', 'sponsorships'],
            'social_media' => [],
            'hr_manager' => ['families', 'sponsors', 'sponsorships'],
            'hr_staff' => ['families', 'sponsors', 'sponsorships'],
            'projects_manager' => ['families', 'sponsors', 'sponsorships'],
            'project_supervisor' => ['families', 'sponsors', 'sponsorships'],
            'staff' => ['families', 'sponsors', 'sponsorships'],
        ][$role] ?? [];
    }
}

if (!function_exists('ak_search_can_type')) {
    function ak_search_can_type(string $type, ?string $role = null): bool
    {
        $type = strtolower(trim($type));
        $allowed = ak_search_allowed_types($role);

        if ($type === 'all') {
            $allTypes = ['families', 'sponsors', 'sponsorships', 'payments'];

            return count($allowed) === count($allTypes)
                && !array_diff($allTypes, $allowed);
        }

        return in_array($type, $allowed, true);
    }
}

if (!function_exists('ak_search_normalize_type')) {
    function ak_search_normalize_type(string $type, ?string $role = null): string
    {
        $type = strtolower(trim($type));

        if ($type === 'all') {
            return ak_search_can_type('all', $role) ? 'all' : '';
        }

        return ak_search_can_type($type, $role) ? $type : '';
    }
}

if (!function_exists('ak_search_enforce_request')) {
    function ak_search_enforce_request(): void
    {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));

        if (strpos($script, '/modules/search/index.php') === false) {
            return;
        }

        require_once __DIR__ . '/session.php';
        Session::start();

        if (!Session::isLoggedIn()) {
            http_response_code(403);
            exit('You are not authorized to search this data type.');
        }

        $rawType = trim((string)($_GET['type'] ?? ''));
        $requestedType = strtolower($rawType);

        /*
         * A bare link to the Search Center should work for roles that cannot
         * search every domain. Default to the first permitted scope instead
         * of treating the omitted type as an unauthorized "all" request.
         */
        if ($requestedType === '') {
            $role = Session::getUserRole();
            $allowed = ak_search_allowed_types($role);
            $requestedType = ak_search_can_type('all', $role)
                ? 'all'
                : (string)($allowed[0] ?? '');

            if ($requestedType === '') {
                http_response_code(403);
                exit('You are not authorized to search this data type.');
            }

            $_GET['type'] = $requestedType;
            return;
        }

        if (!in_array(
            $requestedType,
            ['all', 'families', 'sponsors', 'sponsorships', 'payments'],
            true
        )) {
            http_response_code(400);
            exit('Invalid search type.');
        }

        if (!ak_search_can_type($requestedType, Session::getUserRole())) {
            http_response_code(403);
            exit('You are not authorized to search this data type.');
        }
    }
}


/*
 * Search presentation is rendered directly by includes/header.php and
 * assets/js/app.js. The permission helpers above remain the source of truth
 * for route authorization and the header's available search scopes.
 */
