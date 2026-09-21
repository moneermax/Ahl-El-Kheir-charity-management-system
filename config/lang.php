<?php
/** Ahl El Kheir i18n bootstrap. Stable-key catalogs are authoritative; legacy compatibility is temporary. */
declare(strict_types=1);

$ak_lang = $_COOKIE['ak_lang'] ?? 'ar';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)) {
    $ak_lang = $_GET['lang'];
    setcookie('ak_lang', $ak_lang, ['expires' => time() + 86400 * 365, 'path' => '/', 'samesite' => 'Lax']);
}
$ak_lang = in_array($ak_lang, ['ar', 'en'], true) ? $ak_lang : 'ar';
if (!defined('AK_LANG')) define('AK_LANG', $ak_lang);
if (!defined('AK_DIR')) define('AK_DIR', AK_LANG === 'ar' ? 'rtl' : 'ltr');

/** Load the main catalog plus every stable module catalog for the selected language. */
function ak_catalog(string $language): array {
    static $cache = [];
    if (isset($cache[$language])) return $cache[$language];

    $suffix = $language === 'ar' ? 'ar' : 'en';
    $catalog = [];
    $mainFile = __DIR__ . '/../lang/' . $suffix . '.php';
    if (is_file($mainFile)) {
        $main = require $mainFile;
        if (is_array($main)) $catalog = $main;
    }

    $moduleFiles = glob(__DIR__ . '/../lang/*_' . $suffix . '.php') ?: [];
    sort($moduleFiles, SORT_STRING);
    foreach ($moduleFiles as $moduleFile) {
        if (basename($moduleFile) === 'legacy_' . $suffix . '.php') continue;
        $moduleCatalog = require $moduleFile;
        if (is_array($moduleCatalog)) $catalog = array_merge($catalog, $moduleCatalog);
    }

    return $cache[$language] = $catalog;
}

function ak_legacy_catalog(): array {
    static $dict = null;
    if ($dict !== null) return $dict;
    $file = __DIR__ . '/../lang/legacy_en.php';
    $part = is_file($file) ? require $file : [];
    return $dict = is_array($part) ? $part : [];
}

/** Convert transitional "Arabic / English" values to the selected language's side. */
function ak_legacy_target(string $value): string {
    if (AK_LANG !== 'en') return $value;
    if (preg_match('/^\s*[\x{0600}-\x{06FF}].*?\s+\/\s+([^\r\n]+?)\s*$/u', $value, $m)) {
        return trim($m[1]);
    }
    return $value;
}

/**
 * Build the browser dictionary used by both AKLang.t() and the temporary
 * exact-value compatibility bridge. Stable key => English entries must remain
 * available for AKLang.t(); derived Arabic => English entries let still-unmigrated
 * pages benefit from the authoritative catalogs instead of requiring legacy data.
 */
function ak_dict(): array {
    static $cached = null;
    if ($cached !== null) return $cached;
    if (AK_LANG !== 'en') return $cached = ak_catalog('ar');

    $ar = ak_catalog('ar');
    $en = ak_catalog('en');
    $legacy = ak_legacy_catalog();
    $stableText = [];

    foreach ($ar as $key => $arabic) {
        if (!array_key_exists($key, $en)) continue;
        $source = ak_legacy_normalize((string)$arabic);
        if ($source === '') continue;
        $stableText[$source] = (string)$en[$key];
    }

    // Normalize transitional bilingual legacy values before exposing them to JS/DOM.
    $legacyNormalized = [];
    foreach ($legacy as $source => $target) {
        $legacyNormalized[$source] = ak_legacy_target((string)$target);
    }

    // Preserve stable key lookups first. Exact Arabic text mappings are
    // compatibility aliases, with stable catalog text taking precedence.
    // Lowest priority: text produced by JavaScript / native dialogs (small; sent to the browser).
    return $cached = array_merge(ak_bridge('ar_to_en_js'), $legacyNormalized, $stableText, $en);
}

/** Load a compatibility bridge file from lang/bridge/. Bridges are never stable-key catalogs. */
function ak_bridge(string $name): array {
    static $cache = [];
    if (isset($cache[$name])) return $cache[$name];
    $file = __DIR__ . '/../lang/bridge/' . $name . '.php';
    $data = is_file($file) ? require $file : [];
    return $cache[$name] = is_array($data) ? $data : [];
}

/**
 * Server-only English dictionary: everything ak_dict() has, plus the large Arabic -> English
 * bridge for static page text. It is NOT sent to the browser, so page weight does not grow.
 */
function ak_server_dict(): array {
    static $cached = null;
    if ($cached !== null) return $cached;
    return $cached = array_merge(ak_bridge('ar_to_en'), ak_dict());
}

/** Dictionary for the Arabic UI (English -> Arabic): global entries plus this page's entries. */
function ak_reverse_dict(): array {
    static $cached = null;
    if ($cached !== null) return $cached;
    $bridge = ak_bridge('en_to_ar');
    $page = strtolower(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $dict = [];
    foreach ([(array)($bridge['*'] ?? []), (array)($bridge[$page] ?? [])] as $part) $dict = array_merge($dict, $part);
    return $cached = $dict;
}

/** Split leading/trailing punctuation off a UI string: [prefix, core, suffix]. */
function ak_split_decoration(string $value): array {
    if (preg_match('/^([\s*:：.…\-—–!؟?()\[\]«»"\'،,؛;\p{Nd}#]*)(.*?)([\s*:：.…\-—–!؟?()\[\]«»"\'،,؛;\p{Nd}#]*)$/us', $value, $m)) return [$m[1], $m[2], $m[3]];
    return ['', $value, ''];
}

/** Index Arabic dictionary keys by their punctuation-free core so "الاسم *" and "الاسم:" reuse "الاسم". */
function ak_core_index(array $dict): array {
    $exact = []; $decorated = [];
    foreach ($dict as $source => $target) {
        if (!is_string($source) || !is_string($target) || !preg_match('/[\x{0600}-\x{06FF}]/u', $source)) continue;
        [$prefix, $core, $suffix] = ak_split_decoration(ak_legacy_normalize($source));
        if ($core === '') continue;
        if ($prefix === '' && $suffix === '') { $exact[$core] = $target; continue; }
        if (!isset($decorated[$core])) { [, $targetCore] = ak_split_decoration($target); if ($targetCore !== '') $decorated[$core] = $targetCore; }
    }
    return $exact + $decorated;
}

/** Translate a string that differs from a known one only by leading/trailing punctuation. */
function ak_core_lookup(string $value, array $coreIndex): ?string {
    if (!$coreIndex) return null;
    [$prefix, $core, $suffix] = ak_split_decoration(ak_legacy_normalize($value));
    if ($core === '' || ($prefix === '' && $suffix === '') || !isset($coreIndex[$core])) return null;
    $marks = ['؟' => '?', '،' => ',', '؛' => ';'];
    return strtr($prefix, $marks) . $coreIndex[$core] . strtr($suffix, $marks);
}

function ak_interpolate(string $value, array $params): string { foreach ($params as $name => $replacement) $value = str_replace(':' . $name, (string)$replacement, $value); return $value; }
function ak_t(string $key, array $params = []): string {
    if ($key === '') return '';
    $catalog = ak_catalog(AK_LANG);
    if (array_key_exists($key, $catalog)) return ak_interpolate((string)$catalog[$key], $params);
    if (AK_LANG === 'en' && array_key_exists($key, ak_legacy_catalog())) {
        return ak_interpolate(ak_legacy_target((string)ak_legacy_catalog()[$key]), $params);
    }
    return ak_interpolate($key, $params);
}
function t(string $key, array $params = []): string { return ak_t($key, $params); }
function ak_harvest(string $s): void { /* Runtime harvesting is intentionally disabled. */ }

/** Temporary exact-value compatibility bridge for pages not yet migrated. */
function ak_legacy_normalize(string $value): string {
    $value = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $value);
    return trim((string)(preg_replace('/\s+/u', ' ', $value) ?? $value));
}
function ak_legacy_lookup(string $value, array $legacy): ?string {
    if (array_key_exists($value, $legacy)) return ak_legacy_target((string)$legacy[$value]);
    static $normalizedCache = null;
    if ($normalizedCache === null) {
        $normalizedCache = [];
        foreach ($legacy as $source => $target) {
            $normalizedSource = ak_legacy_normalize((string)$source);
            if ($normalizedSource !== '' && !array_key_exists($normalizedSource, $normalizedCache)) $normalizedCache[$normalizedSource] = ak_legacy_target((string)$target);
        }
    }
    $normalized = ak_legacy_normalize($value);
    return array_key_exists($normalized, $normalizedCache) ? $normalizedCache[$normalized] : null;
}
function ak_translate_page(string $html): string {
    if ($html === '' || !preg_match('/^\s*(<!DOCTYPE|<html)/i', $html)) return $html;
    $legacy = AK_LANG === 'en' ? ak_server_dict() : ak_reverse_dict();
    if (!$legacy) return $html;
    $coreIndex = AK_LANG === 'en' ? ak_core_index($legacy) : [];
    $protected = [];
    $html = preg_replace_callback('/<(script|style|pre|code|textarea)\b[^>]*>.*?<\/\1\s*>/is', static function($m) use (&$protected) { $token = '__AK_I18N_PROTECTED_' . count($protected) . '__'; $protected[$token] = $m[0]; return $token; }, $html) ?? $html;
    $html = preg_replace_callback('/>([^<>]+)</u', static function($m) use ($legacy, $coreIndex) { $translated = ak_legacy_lookup($m[1], $legacy) ?? ak_core_lookup($m[1], $coreIndex); if ($translated === null) return $m[0]; $leading = preg_match('/^\s*/u', $m[1], $lm) ? $lm[0] : ''; $trailing = preg_match('/\s*$/u', $m[1], $tm) ? $tm[0] : ''; return '>' . $leading . $translated . $trailing . '<'; }, $html) ?? $html;
    $html = preg_replace_callback('/\b(placeholder|title|aria-label|aria-description|data-bs-title|alt|data-confirm|data-reassign-confirm)=([' . "\"'" . '])(.*?)\2/iu', static function($m) use ($legacy, $coreIndex) { $translated = ak_legacy_lookup($m[3], $legacy) ?? ak_core_lookup($m[3], $coreIndex); if ($translated === null) return $m[0]; return $m[1] . '=' . $m[2] . htmlspecialchars($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8') . $m[2]; }, $html) ?? $html;
    foreach ($protected as $token => $original) $html = str_replace($token, $original, $html);
    return $html;
}
if (!defined('AK_OB_STARTED')) { define('AK_OB_STARTED', true); ob_start('ak_translate_page'); }