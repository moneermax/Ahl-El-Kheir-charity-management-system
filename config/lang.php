<?php
// config/lang.php - Application-wide i18n bootstrap.
//
// The application supports Arabic (ar) and English (en). Translation is
// deliberately UI-only: database values are never harvested or translated.
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

if (!defined('AK_LANG')) define('AK_LANG', $ak_lang);
if (!defined('AK_DIR')) define('AK_DIR', AK_LANG === 'ar' ? 'rtl' : 'ltr');

if (!function_exists('ak_dict')) {
    function ak_dict(): array {
        static $dict = null;
        if ($dict !== null) return $dict;

        $dict = [];
        $files = [
            __DIR__ . '/../lang/common.php',
            __DIR__ . '/../lang/en.php',
        ];

        foreach ($files as $file) {
            if (is_file($file)) {
                $part = require $file;
                if (is_array($part)) $dict = array_merge($dict, $part);
            }
        }

        return $dict;
    }
}

if (!function_exists('ak_normalize_translation_text')) {
    function ak_normalize_translation_text(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $text);
        $text = preg_replace('/[\x{00A0}\x{202F}\s]+/u', ' ', $text) ?? $text;
        return trim($text);
    }
}

if (!function_exists('ak_translation_lookup')) {
    function ak_translation_lookup(string $text): ?string {
        $dict = ak_dict();
        if (!$dict) return null;

        if (array_key_exists($text, $dict)) return (string)$dict[$text];

        $normalized = ak_normalize_translation_text($text);
        if ($normalized !== $text && array_key_exists($normalized, $dict)) {
            return (string)$dict[$normalized];
        }

        return null;
    }
}

if (!function_exists('ak_harvest')) {
    function ak_harvest(string $s): void {
        // Intentionally disabled for runtime pages. Harvesting rendered HTML
        // can capture real beneficiary/sponsor data and is therefore unsafe.
        return;
    }
}

if (!function_exists('ak_t')) {
    function ak_t(string $s): string {
        if (AK_LANG === 'ar' || $s === '') return $s;
        $translated = ak_translation_lookup($s);
        return $translated ?? $s;
    }
}

if (!function_exists('t')) {
    function t(string $s): string { return ak_t($s); }
}

/**
 * Translate only UI text nodes and known UI attributes.
 *
 * Unlike the old global str_replace(), this never searches inside scripts,
 * styles, URLs, form values, or arbitrary markup. Exact dictionary matches
 * are required for attributes, protecting user/database content.
 */
if (!function_exists('ak_translate_page')) {
    function ak_translate_page(string $html): string {
        if (AK_LANG !== 'en' || $html === '') return $html;
        if (!preg_match('/^\s*(<!DOCTYPE|<html)/i', $html)) return $html;

        $protected = [];
        $html = preg_replace_callback(
            '/<(script|style|pre|code|textarea)\b[^>]*>.*?<\/\1\s*>/is',
            static function ($m) use (&$protected): string {
                $token = "__AK_I18N_PROTECTED_" . count($protected) . "__";
                $protected[$token] = $m[0];
                return $token;
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/>([^<>]+)</u',
            static function ($m): string {
                $text = $m[1];
                if (trim($text) === '') return $m[0];
                $translated = ak_translation_lookup($text);
                if ($translated !== null) return '>' . $translated . '<';

                $dict = ak_dict();
                $keys = array_keys($dict);
                usort($keys, static fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
                foreach ($keys as $key) {
                    if ($key === '' || mb_strlen($key, 'UTF-8') < 2) continue;
                    if (mb_strpos($text, $key, 0, 'UTF-8') !== false) {
                        $text = str_replace($key, (string)$dict[$key], $text);
                    }
                }
                return '>' . $text . '<';
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/\b(placeholder|title|aria-label|aria-description|data-bs-title|alt|data-confirm|data-reassign-confirm)=(["\'])(.*?)\2/iu',
            static function ($m): string {
                $translated = ak_translation_lookup($m[3]);
                return $translated === null
                    ? $m[0]
                    : $m[1] . '=' . $m[2] . htmlspecialchars($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8') . $m[2];
            },
            $html
        ) ?? $html;

        // Submit/button/reset input labels are UI, while other input values
        // are deliberately left untouched because they may contain user data.
        $html = preg_replace_callback(
            '/(<input\b[^>]*\btype=["\'](?:submit|button|reset)["\'][^>]*\bvalue=)(["\'])(.*?)\2/iu',
            static function ($m): string {
                $translated = ak_translation_lookup($m[3]);
                return $translated === null
                    ? $m[0]
                    : $m[1] . $m[2] . htmlspecialchars($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8') . $m[2];
            },
            $html
        ) ?? $html;

        foreach ($protected as $token => $original) {
            $html = str_replace($token, $original, $html);
        }

        return $html;
    }
}

if (!defined('AK_OB_STARTED')) {
    define('AK_OB_STARTED', true);
    ob_start('ak_translate_page');
}
