<<<<<<< HEAD
<?php
// config/lang.php - Lightweight i18n layer (cookie-based, runtime HTML translation)
// Session 6: + EN missing-key harvester (ak_harvest) -> storage/logs/untranslated.log
declare(strict_types=1);

// 1) Detect / persist language
$ak_lang = $_COOKIE['ak_lang'] ?? 'ar';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)) {
    $ak_lang = $_GET['lang'];
    setcookie('ak_lang', $ak_lang, time() + 86400 * 365, '/');
}
if (!defined('AK_LANG')) define('AK_LANG', $ak_lang);
if (!defined('AK_DIR'))  define('AK_DIR', AK_LANG === 'ar' ? 'rtl' : 'ltr');

// 2) Dictionary loader
if (!function_exists('ak_dict')) {
    function ak_dict(): array {
        static $dict = null;
        if ($dict === null) {
            $f = __DIR__ . '/../lang/en.php';
            $dict = is_file($f) ? (array)require $f : [];
        }
        return $dict;
    }
}

// 2b) EN missing-key harvester (Session 6)
// Logs each untranslated Arabic string ONCE to storage/logs/untranslated.log.
// Silent & non-blocking; zero effect in AR mode; curated at the final i18n polish step.
if (!function_exists('ak_harvest')) {
    function ak_harvest(string $s): void {
        $s = trim($s);
        if ($s === '' || !preg_match('/[\x{0600}-\x{06FF}]/u', $s)) return;
        $logFile = __DIR__ . '/../storage/logs/untranslated.log';
        static $seen = null;
        if ($seen === null) {
            $lines = is_file($logFile) ? @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
            $seen = $lines ? array_flip(array_map('trim', $lines)) : [];
        }
        if (isset($seen[$s])) return;
        $seen[$s] = true;
        $dir = dirname($logFile);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @file_put_contents($logFile, $s . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

// 3) Translate a single string
if (!function_exists('ak_t')) {
    function ak_t(string $s): string {
        if (AK_LANG === 'ar') return $s;
        $d = ak_dict();
        if (isset($d[$s])) return $d[$s];
        ak_harvest($s);
        return $s;
    }
}
// Alias used by public pages (apply.php etc.)
if (!function_exists('t')) {
    function t(string $s): string { return ak_t($s); }
}

// 4) Whole-page translation (HTML responses only; skips CSV/SQL/JSON downloads)
if (!function_exists('ak_translate_page')) {
    function ak_translate_page(string $html): string {
        if (AK_LANG !== 'en') return $html;
        if (!preg_match('/^\s*(<!DOCTYPE|<html)/i', $html)) return $html;
        $d = ak_dict();
        if (!$d) return $html;
        $keys = array_keys($d);
        usort($keys, fn($a, $b) => strlen($b) <=> strlen($a));
        $vals = array_map(fn($k) => $d[$k], $keys);
        $html = str_replace($keys, $vals, $html);
        // SECURITY FIX (August 2026): the previous version of this function also
        // scanned the ENTIRE rendered page for any Arabic-looking text run and
        // logged it as "untranslated" via ak_harvest(). Because this ran on the
        // final HTML output, it could not distinguish a static UI label from
        // dynamic database content — and in production it silently wrote real
        // children's names, mothers' names, and sponsor case notes into
        // storage/logs/untranslated.log (confirmed by cross-referencing the
        // existing log against the live database: ~86% of harvested lines were
        // real beneficiary/sponsor data, not UI text).
        //
        // This blanket, whole-page harvesting has been permanently removed.
        // The safe alternative is the harvester inside ak_t() above, which only
        // ever sees strings a developer explicitly wrote and passed through
        // t()/ak_t() — it can never see raw database output, because it never
        // touches the rendered page at all.
        //
        // If you are reading this because untranslated.log stopped growing:
        // that is expected and correct. New untranslated UI text will now only
        // be captured when a developer deliberately calls t('...') on it.
        return $html;
    }
}

// 5) Capture every page output
if (!defined('AK_OB_STARTED')) {
    define('AK_OB_STARTED', true);
    ob_start('ak_translate_page');
=======
<?php
// config/lang.php - Lightweight i18n layer (cookie-based, runtime HTML translation)
// Session 6: + EN missing-key harvester (ak_harvest) -> storage/logs/untranslated.log
declare(strict_types=1);

// 1) Detect / persist language
$ak_lang = $_COOKIE['ak_lang'] ?? 'ar';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)) {
    $ak_lang = $_GET['lang'];
    setcookie('ak_lang', $ak_lang, time() + 86400 * 365, '/');
}
if (!defined('AK_LANG')) define('AK_LANG', $ak_lang);
if (!defined('AK_DIR'))  define('AK_DIR', AK_LANG === 'ar' ? 'rtl' : 'ltr');

// 2) Dictionary loader
if (!function_exists('ak_dict')) {
    function ak_dict(): array {
        static $dict = null;
        if ($dict === null) {
            $f = __DIR__ . '/../lang/en.php';
            $dict = is_file($f) ? (array)require $f : [];
        }
        return $dict;
    }
}

// 2b) EN missing-key harvester (Session 6)
// Logs each untranslated Arabic string ONCE to storage/logs/untranslated.log.
// Silent & non-blocking; zero effect in AR mode; curated at the final i18n polish step.
if (!function_exists('ak_harvest')) {
    function ak_harvest(string $s): void {
        $s = trim($s);
        if ($s === '' || !preg_match('/[\x{0600}-\x{06FF}]/u', $s)) return;
        $logFile = __DIR__ . '/../storage/logs/untranslated.log';
        static $seen = null;
        if ($seen === null) {
            $lines = is_file($logFile) ? @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
            $seen = $lines ? array_flip(array_map('trim', $lines)) : [];
        }
        if (isset($seen[$s])) return;
        $seen[$s] = true;
        $dir = dirname($logFile);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @file_put_contents($logFile, $s . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

// 3) Translate a single string
if (!function_exists('ak_t')) {
    function ak_t(string $s): string {
        if (AK_LANG === 'ar') return $s;
        $d = ak_dict();
        if (isset($d[$s])) return $d[$s];
        ak_harvest($s);
        return $s;
    }
}
// Alias used by public pages (apply.php etc.)
if (!function_exists('t')) {
    function t(string $s): string { return ak_t($s); }
}

// 4) Whole-page translation (HTML responses only; skips CSV/SQL/JSON downloads)
if (!function_exists('ak_translate_page')) {
    function ak_translate_page(string $html): string {
        if (AK_LANG !== 'en') return $html;
        if (!preg_match('/^\s*(<!DOCTYPE|<html)/i', $html)) return $html;
        $d = ak_dict();
        if (!$d) return $html;
        $keys = array_keys($d);
        usort($keys, fn($a, $b) => strlen($b) <=> strlen($a));
        $vals = array_map(fn($k) => $d[$k], $keys);
        $html = str_replace($keys, $vals, $html);
        // SECURITY FIX (August 2026): the previous version of this function also
        // scanned the ENTIRE rendered page for any Arabic-looking text run and
        // logged it as "untranslated" via ak_harvest(). Because this ran on the
        // final HTML output, it could not distinguish a static UI label from
        // dynamic database content — and in production it silently wrote real
        // children's names, mothers' names, and sponsor case notes into
        // storage/logs/untranslated.log (confirmed by cross-referencing the
        // existing log against the live database: ~86% of harvested lines were
        // real beneficiary/sponsor data, not UI text).
        //
        // This blanket, whole-page harvesting has been permanently removed.
        // The safe alternative is the harvester inside ak_t() above, which only
        // ever sees strings a developer explicitly wrote and passed through
        // t()/ak_t() — it can never see raw database output, because it never
        // touches the rendered page at all.
        //
        // If you are reading this because untranslated.log stopped growing:
        // that is expected and correct. New untranslated UI text will now only
        // be captured when a developer deliberately calls t('...') on it.
        return $html;
    }
}

// 5) Capture every page output
if (!defined('AK_OB_STARTED')) {
    define('AK_OB_STARTED', true);
    ob_start('ak_translate_page');
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
}