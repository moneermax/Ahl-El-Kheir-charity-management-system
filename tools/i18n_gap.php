<?php
/**
 * CLI-only. Lists Arabic UI text in the source that has NO English translation yet.
 * Run from the project root:   php tools/i18n_gap.php          (summary + samples)
 *                              php tools/i18n_gap.php --all     (every string)
 * Exit code 0 = nothing missing, 1 = missing translations found.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
$_COOKIE['ak_lang'] = 'en';
$root = dirname(__DIR__);
require $root . '/config/lang.php';
while (ob_get_level() > 0) ob_end_clean();
$showAll = in_array('--all', $argv ?? [], true);
$dict = ak_server_dict();
$coreIndex = ak_core_index($dict);
$skip = ['/TCPDF/', '/.git/', '/storage/', '/database/', '/lang/', '/tools/', '/docs/', '/modules/system/'];
$missing = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js'], true)) continue;
    $path = str_replace('\\', '/', $file->getPathname());
    foreach ($skip as $part) if (strpos($path, $part) !== false) continue 2;
    $code = (string)file_get_contents($path);
    $code = preg_replace('~/\*.*?\*/~s', '', $code) ?? $code;      // block comments
    $code = preg_replace('~^\s*(//|#).*$~m', '', $code) ?? $code;   // whole-line comments
    $found = [];
    if (preg_match_all("/'((?:[^'\\\\\\n]|\\\\.)*)'|\"((?:[^\"\\\\\\n]|\\\\.)*)\"/u", $code, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) $found[] = $hit[1] !== '' ? $hit[1] : ($hit[2] ?? '');
    }
    $html = preg_replace('~<(script|style)\b.*?</\1>~is', "\0", $code) ?? $code;
    $html = preg_replace('~<\?(?:php|=).*?\?>~s', "\0", $html) ?? $html;
    if (preg_match_all('~>([^<>]+)<~u', $html, $m)) foreach ($m[1] as $text) foreach (explode("\0", $text) as $piece) $found[] = $piece;
    foreach ($found as $text) {
        $text = ak_legacy_normalize(strip_tags(stripslashes($text)));
        // onclick="return confirm('message')" -> check the message itself (native dialogs are translated at runtime).
        if (preg_match('/\b(?:confirm|alert|prompt)\(\s*([\'"])(.*?)\1/us', $text, $dialog)) $text = ak_legacy_normalize($dialog[2]);
        if ($text === '' || !preg_match('/[\x{0600}-\x{06FF}]/u', $text)) continue;
        if (preg_match('/[\$\{\};]|->|::|\bSELECT\b|\bINSERT\b|\bUPDATE\b/', $text)) continue; // code, not UI text
        if (ak_legacy_lookup($text, $dict) !== null || ak_core_lookup($text, $coreIndex) !== null) continue;
        $missing[substr($path, strlen($root) + 1)][$text] = true;
    }
}
ksort($missing);
$total = array_sum(array_map('count', $missing));
echo "AHL EL KHEIR - MISSING ENGLISH TRANSLATIONS\n" . str_repeat('=', 44) . "\n";
foreach ($missing as $file => $texts) {
    printf("%4d  %s\n", count($texts), $file);
    foreach (array_slice(array_keys($texts), 0, $showAll ? PHP_INT_MAX : 3) as $text) echo '        ' . mb_substr($text, 0, 110) . "\n";
}
printf("\nFiles with gaps: %d | Untranslated Arabic strings: %d\n", count($missing), $total);
echo $total === 0 ? "OK: every Arabic UI string has an English translation.\n" : "Add each string to lang/bridge/ar_to_en.php (or, better, migrate it to t('stable.key')).\n";
exit($total === 0 ? 0 : 1);
