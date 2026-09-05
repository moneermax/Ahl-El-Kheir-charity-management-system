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

/**
 * Build the browser dictionary used by both AKLang.t() and the temporary
 * exact-value compatibility bridge. Stable key => English entries must remain
 * available for AKLang.t(); derived Arabic => English entries let still-unmigrated
 * pages benefit from the authoritative catalogs instead of requiring legacy data.
 */
function ak_dict(): array {
    if (AK_LANG !== 'en') return ak_catalog('ar');

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

    // Preserve stable key lookups first. Exact Arabic text mappings are
    // compatibility aliases, with stable catalog text taking precedence.
    return array_merge($legacy, $stableText, $en);
}

function ak_interpolate(string $value, array $params): string { foreach ($params as $name => $replacement) $value = str_replace(':' . $name, (string)$replacement, $value); return $value; }
function ak_t(string $key, array $params = []): string {
    if ($key === '') return '';
    $catalog = ak_catalog(AK_LANG);
    if (array_key_exists($key, $catalog)) return ak_interpolate((string)$catalog[$key], $params);
    return AK_LANG === 'en' && array_key_exists($key, ak_legacy_catalog()) ? ak_interpolate((string)ak_legacy_catalog()[$key], $params) : ak_interpolate($key, $params);
}
function t(string $key, array $params = []): string { return ak_t($key, $params); }
function ak_harvest(string $s): void { /* Runtime harvesting is intentionally disabled. */ }

/** Temporary exact-value compatibility bridge for pages not yet migrated. */
function ak_legacy_normalize(string $value): string {
    $value = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $value);
    return trim((string)(preg_replace('/\s+/u', ' ', $value) ?? $value));
}
function ak_legacy_lookup(string $value, array $legacy): ?string {
    if (array_key_exists($value, $legacy)) return (string)$legacy[$value];
    static $normalizedCache = null;
    if ($normalizedCache === null) {
        $normalizedCache = [];
        foreach ($legacy as $source => $target) {
            $normalizedSource = ak_legacy_normalize((string)$source);
            if ($normalizedSource !== '' && !array_key_exists($normalizedSource, $normalizedCache)) $normalizedCache[$normalizedSource] = (string)$target;
        }
    }
    $normalized = ak_legacy_normalize($value);
    return array_key_exists($normalized, $normalizedCache) ? $normalizedCache[$normalized] : null;
}
function ak_translate_page(string $html): string {
    if (AK_LANG !== 'en' || $html === '' || !preg_match('/^\s*(<!DOCTYPE|<html)/i', $html)) return $html;
    $legacy = ak_dict();
    if (!$legacy) return $html;
    $protected = [];
    $html = preg_replace_callback('/<(script|style|pre|code|textarea)\b[^>]*>.*?<\/\1\s*>/is', static function($m) use (&$protected) { $token = '__AK_I18N_PROTECTED_' . count($protected) . '__'; $protected[$token] = $m[0]; return $token; }, $html) ?? $html;
    $html = preg_replace_callback('/>([^<>]+)</u', static function($m) use ($legacy) { $translated = ak_legacy_lookup($m[1], $legacy); if ($translated === null) return $m[0]; $leading = preg_match('/^\s*/u', $m[1], $lm) ? $lm[0] : ''; $trailing = preg_match('/\s*$/u', $m[1], $tm) ? $tm[0] : ''; return '>' . $leading . $translated . $trailing . '<'; }, $html) ?? $html;
    $html = preg_replace_callback('/\b(placeholder|title|aria-label|aria-description|data-bs-title|alt|data-confirm|data-reassign-confirm)=([' . "\"'" . '])(.*?)\2/iu', static function($m) use ($legacy) { $translated = ak_legacy_lookup($m[3], $legacy); if ($translated === null) return $m[0]; return $m[1] . '=' . $m[2] . htmlspecialchars($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8') . $m[2]; }, $html) ?? $html;
    foreach ($protected as $token => $original) $html = str_replace($token, $original, $html);
    return $html;
}
if (!defined('AK_OB_STARTED')) { define('AK_OB_STARTED', true); ob_start('ak_translate_page'); }