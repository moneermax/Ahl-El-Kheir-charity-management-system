<?php
/**
 * Centralized authorization policy for the global search.
 *
 * This controls WHICH search domains a role may request. Record-level
 * visibility remains enforced by modules/search/index.php.
 */

if (!function_exists('ak_search_allowed_types')) {
    function ak_search_allowed_types(?string $role = null): array
    {
        $role = (string)($role ?? current_user_role());

        // Explicit aliases used by the application.
        $aliases = [
            'sudo' => 'admin',
            'gm'   => 'general_manager',
            'vgm'  => 'vice_general_manager',
            'fm'   => 'financial_manager',
            'finance' => 'financial_manager',
            'staff' => 'administration',
        ];
        $role = $aliases[$role] ?? $role;

        $policy = [
            // Organization-wide roles.
            'admin' => ['families', 'sponsors', 'sponsorships', 'payments'],
            'general_manager' => ['families', 'sponsors', 'sponsorships', 'payments'],
            'vice_general_manager' => ['families', 'sponsors', 'sponsorships', 'payments'],
            'financial_manager' => ['families', 'sponsors', 'sponsorships', 'payments'],
            'accountant' => ['families', 'sponsors', 'sponsorships', 'payments'],

            // Operational roles with record-level scope enforced separately.
            'supervisor' => ['families', 'sponsors', 'sponsorships', 'payments'],
            'nanny' => ['families', 'sponsorships', 'payments'],
            'accountant_staff' => ['families', 'sponsorships', 'payments'],

            // Administration currently has access to family/sponsor operational
            // areas, but not financial/payment data.
            'administration' => ['families', 'sponsors', 'sponsorships'],

            // Social media has orphan-form and sponsor-request access, but the
            // current global-search policy does not grant broad record search.
            'social_media' => [],

            // HR is intentionally isolated from beneficiary/sponsorship/payment search.
            'hr_manager' => [],
            'hr_staff' => [],
        ];

        return $policy[$role] ?? [];
    }
}

if (!function_exists('ak_search_can_type')) {
    function ak_search_can_type(string $type, ?string $role = null): bool
    {
        $type = strtolower(trim($type));

        if ($type === 'all') {
            return count(ak_search_allowed_types($role)) > 0;
        }

        return in_array($type, ak_search_allowed_types($role), true);
    }
}

if (!function_exists('ak_search_normalize_type')) {
    function ak_search_normalize_type(string $type, ?string $role = null): string
    {
        $type = strtolower(trim($type));
        $allowed = ak_search_allowed_types($role);

        if ($type === 'all') {
            return $allowed ? 'all' : '';
        }

        return in_array($type, $allowed, true) ? $type : '';
    }
}
