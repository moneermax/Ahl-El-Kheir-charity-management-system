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

        $requestedType = strtolower(trim((string)($_GET['type'] ?? 'all')));

        if (!in_array(
            $requestedType,
            ['all', 'families', 'sponsors', 'sponsorships', 'payments'],
            true
        )) {
            http_response_code(400);
            exit('Invalid search type.');
        }

        if (
            !Session::isLoggedIn()
            || !ak_search_can_type($requestedType, Session::getUserRole())
        ) {
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
            $script = str_replace(
                '\\',
                '/',
                (string)($_SERVER['SCRIPT_NAME'] ?? '')
            );

            if (strpos($script, '/modules/search/index.php') === false) {
                return;
            }

            $allowed = ak_search_allowed_types();
            $allowAll = ak_search_can_type('all');

            $allowedJson = json_encode(
                array_values($allowed),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

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
 * Header search presentation.
 *
 * The search engine and authorization remain unchanged. This presentation
 * layer removes the large legacy search form from the header and places a
 * compact search entry inside the existing top bar.
 *
 * IMPORTANT:
 * Do not use single-quoted strings containing \\n * here. In PHP those would render the literal characters \\n * into the page, which was the source of the broken header seen previously.
 */
if (!defined('AK_SEARCH_HEADER_REDESIGN_BUFFER')) {
    define('AK_SEARCH_HEADER_REDESIGN_BUFFER', true);

    ob_start(static function (string $html): string {
        if (
            strpos($html, 'id="globalSearchForm"') === false
            || strpos($html, 'ak-search-bar-wrap') === false
        ) {
            return $html;
        }

        $label = AK_LANG === 'ar' ? 'البحث' : 'Search';
        $aria = AK_LANG === 'ar'
            ? 'فتح البحث العام'
            : 'Open global search';
        $title = AK_LANG === 'ar'
            ? 'البحث العام'
            : 'Global Search';

        $url = htmlspecialchars(
            APP_URL . 'modules/search/index.php',
            ENT_QUOTES,
            'UTF-8'
        );

        $pattern = '~\s*<div class="ak-search-bar-wrap">\s*'
            . '<form\b[^>]*id="globalSearchForm".*?</form>\s*'
            . '</div>\s*~s';

        $updated = preg_replace(
            $pattern,
            "\n",
            $html,
            1,
            $count
        );

        if ($count !== 1 || $updated === null) {
            return $html;
        }

        $searchEntry = "\n"
            . '                <a href="' . $url . '"'
            . ' class="qa-search-entry"'
            . ' aria-label="' . htmlspecialchars($aria, ENT_QUOTES, 'UTF-8') . '"'
            . ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">'
            . '<i class="fas fa-search"></i>'
            . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
            . "</a>\n";

        $userMarker = '<!-- ====================================================='
            . "\n"
            . '                     USER CONTROLS'
            . "\n"
            . '                     ===================================================== -->';

        if (strpos($updated, $userMarker) === false) {
            return $html;
        }

        $updated = str_replace(
            $userMarker,
            $searchEntry . '                ' . $userMarker,
            $updated,
            $markerCount
        );

        if ($markerCount !== 1) {
            return $html;
        }

        $css = "\n<style>\n"
            . ".qa-search-entry{"
            . "display:inline-flex;"
            . "align-items:center;"
            . "justify-content:center;"
            . "gap:6px;"
            . "height:32px;"
            . "padding:4px 11px;"
            . "border:1px solid rgba(255,255,255,.22);"
            . "border-radius:5px;"
            . "background:rgba(255,255,255,.12);"
            . "color:#fff!important;"
            . "text-decoration:none;"
            . "font-size:.72rem;"
            . "font-weight:700;"
            . "white-space:nowrap;"
            . "transition:background .15s ease,transform .15s ease;"
            . "}\n"
            . ".qa-search-entry:hover{"
            . "background:rgba(255,255,255,.22);"
            . "color:#fff!important;"
            . "transform:translateY(-1px);"
            . "}\n"
            . ".qa-search-entry i{font-size:.78rem;}\n"
            . "@media(max-width:768px){"
            . ".qa-search-entry span{display:none;}"
            . ".qa-search-entry{width:32px;padding:4px;}"
            . "}\n"
            . "</style>\n";

        if (strpos($updated, '.qa-search-entry{') === false) {
            $updated = str_replace(
                '</head>',
                $css . '</head>',
                $updated,
                $headCount
            );

            if ($headCount !== 1) {
                return $html;
            }
        }

        return $updated;
    });
}
