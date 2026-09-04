<?php
/** Ahl El Kheir i18n bootstrap. New code uses stable translation keys. */
declare(strict_types=1);

$ak_lang = $_COOKIE['ak_lang'] ?? 'ar';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)) {
    $ak_lang = $_GET['lang'];
    setcookie('ak_lang', $ak_lang, [
        'expires' => time() + 86400 * 365,
        'path' => '/',
        'samesite' => 'Lax',
    ]);
}
$ak_lang = in_array($ak_lang, ['ar', 'en'], true) ? $ak_lang : 'ar';
if (!defined('AK_LANG')) define('AK_LANG', $ak_lang);
if (!defined('AK_DIR')) define('AK_DIR', AK_LANG === 'ar' ? 'rtl' : 'ltr');

if (!function_exists('ak_catalog')) {
    function ak_catalog(string $language): array {
        static $cache = [];
        if (isset($cache[$language])) return $cache[$language];
        $file = $language === 'ar' ? __DIR__ . '/../lang/ar.php' : __DIR__ . '/../lang/catalog_en.php';
        $catalog = is_file($file) ? require $file : [];
        return $cache[$language] = is_array($catalog) ? $catalog : [];
    }
}

if (!function_exists('ak_legacy_catalog')) {
    function ak_legacy_catalog(): array {
        static $dict = null;
        if ($dict !== null) return $dict;
        $file = __DIR__ . '/../lang/en.php';
        $part = is_file($file) ? require $file : [];
        return $dict = is_array($part) ? $part : [];
    }
}

if (!function_exists('ak_interpolate')) {
    function ak_interpolate(string $value, array $params): string {
        foreach ($params as $name => $replacement) $value = str_replace(':' . $name, (string)$replacement, $value);
        return $value;
    }
}

if (!function_exists('ak_t')) {
    function ak_t(string $key, array $params = []): string {
        if ($key === '') return '';
        $catalog = ak_catalog(AK_LANG);
        if (array_key_exists($key, $catalog)) return ak_interpolate((string)$catalog[$key], $params);
        if (AK_LANG === 'en') {
            $legacy = ak_legacy_catalog();
            if (array_key_exists($key, $legacy)) return ak_interpolate((string)$legacy[$key], $params);
        }
        return ak_interpolate($key, $params);
    }
}
if (!function_exists('t')) {
    function t(string $key, array $params = []): string { return ak_t($key, $params); }
}
if (!function_exists('ak_harvest')) {
    function ak_harvest(string $s): void { /* Runtime harvesting is intentionally disabled. */ }
}

/** Legacy exact-match DOM compatibility; remove after page migration. */
if (!function_exists('ak_translate_page')) {
    function ak_translate_page(string $html): string {
        if (AK_LANG !== 'en' || $html === '' || !preg_match('/^\s*(<!DOCTYPE|<html)/i', $html)) return $html;
        $protected = [];
        $html = preg_replace_callback('/<(script|style|pre|code|textarea)\b[^>]*>.*?<\/\1\s*>/is', static function($m) use (&$protected) {
            $token = '__AK_I18N_PROTECTED_' . count($protected) . '__';
            $protected[$token] = $m[0];
            return $token;
        }, $html) ?? $html;
        $html = preg_replace_callback('/>([^<>]+)</u', static function($m) {
            $translated = ak_legacy_catalog()[$m[1]] ?? null;
            return $translated === null ? $m[0] : '>' . $translated . '<';
        }, $html) ?? $html;
        $html = preg_replace_callback('/\b(placeholder|title|aria-label|aria-description|data-bs-title|alt|data-confirm|data-reassign-confirm)=(["\'])(.*?)\2/iu', static function($m) {
            $translated = ak_legacy_catalog()[$m[3]] ?? null;
            return $translated === null ? $m[0] : $m[1] . '=' . $m[2] . htmlspecialchars((string)$translated, ENT_QUOTES | ENT_HTML5, 'UTF-8') . $m[2];
        }, $html) ?? $html;
        foreach ($protected as $token => $original) $html = str_replace($token, $original, $html);
        return $html;
    }
}
if (!defined('AK_OB_STARTED')) {
    define('AK_OB_STARTED', true);
    ob_start('ak_translate_page');
}
