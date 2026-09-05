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
        $allowed = ak_search_allowed_types($role);

        if ($type === 'all') {
            $allTypes = ['families', 'sponsors', 'sponsorships', 'payments'];
            return count($allowed) === count($allTypes) && !array_diff($allTypes, $allowed);
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

        $requestedType = strtolower(trim((string)($_GET['type'] ?? 'all')));

        if (!in_array($requestedType, ['all', 'families', 'sponsors', 'sponsorships', 'payments'], true)) {
            http_response_code(400);
            exit('Invalid search type.');
        }

        if (!Session::isLoggedIn() || !ak_search_can_type($requestedType, Session::getUserRole())) {
            http_response_code(403);
            exit('You are not authorized to search this data type.');
        }
    }
}

if (!function_exists('ak_search_register_ui_filter')) {
    function ak_search_register_ui_filter(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        register_shutdown_function(static function (): void {
            $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));

            if (strpos($script, '/modules/search/index.php') === false) {
                return;
            }

            $allowed = ak_search_allowed_types();
            $allowAll = ak_search_can_type('all');
            $allowedJson = json_encode(array_values($allowed), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($allowedJson === false) {
                $allowedJson = '[]';
            }

            echo "\n<script>\n";
            echo "document.addEventListener('DOMContentLoaded', function () {\n";
            echo "  const allowed = new Set(" . $allowedJson . ");\n";
            echo "  const allowAll = " . ($allowAll ? 'true' : 'false') . ";\n";
            echo "  const typeSelect = document.getElementById('searchType');\n";
            echo "  if (typeSelect) {\n";
            echo "    Array.from(typeSelect.options).forEach(function (option) {\n";
            echo "      if (option.value === 'all') { option.hidden = !allowAll; return; }\n";
            echo "      option.hidden = !allowed.has(option.value);\n";
            echo "    });\n";
            echo "    if (typeSelect.value === 'all' && !allowAll) {\n";
            echo "      typeSelect.value = allowed.size ? Array.from(allowed)[0] : '';\n";
            echo "    } else if (typeSelect.value !== 'all' && !allowed.has(typeSelect.value)) {\n";
            echo "      typeSelect.value = allowed.size ? Array.from(allowed)[0] : '';\n";
            echo "    }\n";
            echo "  }\n";
            echo "});\n</script>\n";
        });
    }
}

/*
 * Header presentation layer for the global search.
 *
 * The legacy search form is still present in older header.php revisions.
 * Replace only that rendered HTML fragment with the new compact entry point.
 * This keeps the existing authorization/search engine intact while allowing
 * the header redesign to be deployed safely without touching the large header
 * template itself.
 *
 * The callback is intentionally limited to HTML responses that actually
 * contain the legacy form. JSON/AJAX responses are returned untouched.
 */
if (!defined('AK_SEARCH_HEADER_REDESIGN_BUFFER')) {
    define('AK_SEARCH_HEADER_REDESIGN_BUFFER', true);

    ob_start(static function (string $html): string {
        if (strpos($html, 'id="globalSearchForm"') === false) {
            return $html;
        }

        if (strpos($html, 'ak-search-bar-wrap') === false) {
            return $html;
        }

        $label = AK_LANG === 'ar' ? 'البحث' : 'Search';
        $aria  = AK_LANG === 'ar' ? 'فتح البحث العام' : 'Open global search';
        $url   = htmlspecialchars(APP_URL . 'modules/search/index.php', ENT_QUOTES, 'UTF-8');

        $replacement = '\n            <div class="ak-search-entry-wrap">\n'
            . '                <a href="' . $url . '" class="ak-search-entry" aria-label="'
            . htmlspecialchars($aria, ENT_QUOTES, 'UTF-8') . '">\n'
            . '                    <i class="fas fa-search"></i>\n'
            . '                    <span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>\n'
            . '                </a>\n'
            . '            </div>\n';

        $pattern = '~\s*<div class="ak-search-bar-wrap">\s*<form\b[^>]*id="globalSearchForm".*?</form>\s*</div>\s*~s';
        $updated = preg_replace($pattern, $replacement, $html, 1, $count);

        if ($count !== 1 || $updated === null) {
            return $html;
        }

        $css = '\n<style>\n'
            . '.ak-search-entry-wrap{display:flex;justify-content:flex-end;align-items:center;padding:6px 15px;background:#fff;border-bottom:1px solid #e3e7ee;}\n'
            . '.ak-search-entry{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:32px;padding:4px 11px;border:1px solid rgba(255,255,255,.2);border-radius:5px;background:#1b4d8f;color:#fff!important;text-decoration:none;font-size:.72rem;font-weight:600;white-space:nowrap;transition:all .2s ease;}\n'
            . '.ak-search-entry:hover{color:#fff!important;transform:translateY(-1px);filter:brightness(1.08);}\n'
            . '.ak-search-entry i{font-size:.78rem;}\n'
            . 'body.theme-dark .ak-search-entry-wrap{background:#2d3748;border-bottom-color:#4a5568;}\n'
            . '</style>\n';

        if (strpos($updated, '.ak-search-entry{') === false) {
            $updated = str_replace('</head>', $css . '</head>', $updated, $cssCount);
            if ($cssCount !== 1) {
                $updated = $css . $updated;
            }
        }

        return $updated;
    });
}
