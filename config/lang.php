<?php
/** Ahl El Kheir i18n bootstrap. Stable keys are authoritative; legacy text mapping is temporary. */
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

function ak_catalog(string $language): array {
    static $cache = [];
    if (isset($cache[$language])) return $cache[$language];
    $file = __DIR__ . '/../lang/' . ($language === 'ar' ? 'ar.php' : 'en.php');
    $catalog = is_file($file) ? require $file : [];
    return $cache[$language] = is_array($catalog) ? $catalog : [];
}

function ak_legacy_catalog(): array {
    static $dict = null;
    if ($dict !== null) return $dict;
    $file = __DIR__ . '/../lang/legacy_en.php';
    $part = is_file($file) ? require $file : [];
    return $dict = is_array($part) ? $part : [];
}

function ak_dict(): array {
    return AK_LANG === 'en' ? array_merge(ak_legacy_catalog(), ak_catalog('en')) : ak_catalog('ar');
}

function ak_interpolate(string $value, array $params): string {
    foreach ($params as $name => $replacement) $value = str_replace(':' . $name, (string)$replacement, $value);
    return $value;
}

function ak_t(string $key, array $params = []): string {
    if ($key === '') return '';
    $catalog = ak_catalog(AK_LANG);
    if (array_key_exists($key, $catalog)) return ak_interpolate((string)$catalog[$key], $params);
    return AK_LANG === 'en' && array_key_exists($key, ak_legacy_catalog())
        ? ak_interpolate((string)ak_legacy_catalog()[$key], $params)
        : ak_interpolate($key, $params);
}

function t(string $key, array $params = []): string { return ak_t($key, $params); }
function ak_harvest(string $s): void { /* Runtime harvesting is intentionally disabled. */ }

/** Temporary exact-match compatibility bridge for pages not yet migrated. */
function ak_translate_page(string $html): string {
    if (AK_LANG !== 'en' || $html === '' || !preg_match('/^\s*(<!DOCTYPE|<html)/i', $html)) return $html;
    $legacy = ak_legacy_catalog();
    if (!$legacy) return $html;
    $protected = [];
    $html = preg_replace_callback('/<(script|style|pre|code|textarea)\b[^>]*>.*?<\/\1\s*>/is', static function($m) use (&$protected) {
        $token = '__AK_I18N_PROTECTED_' . count($protected) . '__';
        $protected[$token] = $m[0];
        return $token;
    }, $html) ?? $html;
    $html = preg_replace_callback('/>([^<>]+)</u', static function($m) use ($legacy) {
        $translated = $legacy[$m[1]] ?? null;
        return $translated === null ? $m[0] : '>' . $translated . '<';
    }, $html) ?? $html;
    $html = preg_replace_callback('/\b(placeholder|title|aria-label|aria-description|data-bs-title|alt|data-confirm|data-reassign-confirm)=(["\'])(.*?)\2/iu', static function($m) use ($legacy) {
        $translated = $legacy[$m[3]] ?? null;
        return $translated === null ? $m[0] : $m[1] . '=' . $m[2] . htmlspecialchars((string)$translated, ENT_QUOTES | ENT_HTML5, 'UTF-8') . $m[2];
    }, $html) ?? $html;
    foreach ($protected as $token => $original) $html = str_replace($token, $original, $html);
    return $html;
}
if (!defined('AK_OB_STARTED')) {
    define('AK_OB_STARTED', true);
    ob_start('ak_translate_page');
}
