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
            'hr_manager' => [],
            'hr_staff' => [],
        ][$role] ?? [];
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

/*
 * Enforce the requested search type before modules/search/index.php executes.
 * This prevents direct URL tampering from bypassing the search UI policy.
 */
if (!function_exists('ak_search_enforce_request')) {
    function ak_search_enforce_request(): void
    {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (strpos($script, '/modules/search/index.php') === false) {
            return;
        }

        $requestedType = strtolower(trim((string)($_GET['type'] ?? 'all')));

        if (!in_array($requestedType, ['all', 'families', 'sponsors', 'sponsorships', 'payments'], true)) {
            http_response_code(400);
            exit('Invalid search type.');
        }

        if (!ak_search_can_type($requestedType)) {
            http_response_code(403);
            exit('You are not authorized to search this data type.');
        }
    }
}

/*
 * Hide unauthorized search types from the existing global header without
 * rebuilding/replacing includes/header.php. The backend guard above remains
 * authoritative even if a user manually changes the request URL.
 */
if (!function_exists('ak_search_register_ui_filter')) {
    function ak_search_register_ui_filter(): void
    {
        static $registered = false;
        if ($registered || !function_exists('current_user_role')) {
            return;
        }
        $registered = true;

        $allowed = ak_search_allowed_types();
        $allowedJson = json_encode(array_values($allowed), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($allowedJson === false) {
            $allowedJson = '[]';
        }

        register_shutdown_function(static function () use ($allowedJson): void {
            if (!headers_sent()) {
                echo "\n<script>\n";
            } else {
                echo "\n<script>\n";
            }
            echo "document.addEventListener('DOMContentLoaded', function () {\n";
            echo "  const allowed = new Set(" . $allowedJson . ");\n";
            echo "  const typeSelect = document.getElementById('searchType');\n";
            echo "  if (typeSelect) {\n";
            echo "    Array.from(typeSelect.options).forEach(function (option) {\n";
            echo "      if (option.value === 'all') { option.hidden = allowed.size === 0; return; }\n";
            echo "      option.hidden = !allowed.has(option.value);\n";
            echo "    });\n";
            echo "    if (typeSelect.value !== 'all' && !allowed.has(typeSelect.value)) {\n";
            echo "      typeSelect.value = allowed.size ? Array.from(allowed)[0] : 'all';\n";
            echo "    }\n";
            echo "  }\n";
            echo "  document.querySelectorAll('a[href*=" . json_encode('type=', JSON_UNESCAPED_SLASHES) . "]').forEach(function (link) {\n";
            echo "    try { const u = new URL(link.href, window.location.href); const t = u.searchParams.get('type'); if (t && t !== 'all' && !allowed.has(t)) link.remove(); else if (t === 'all' && allowed.size === 0) link.remove(); } catch (e) {}\n";
            echo "  });\n";
            echo "});\n</script>\n";
        });
    }
}
