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
        foreach ($m as $hit) {
            $literal = $hit[1] !== '' ? $hit[1] : ($hit[2] ?? '');
            // A JavaScript literal may contain generated HTML. The whole literal is not
            // one translatable phrase; inspect its visible text nodes instead.
            if (preg_match('/<\\/?[a-z][^>]*>/iu', $literal)) {
                if (preg_match_all('~>([^<>]+)<~u', $literal, $htmlText)) {
                    foreach ($htmlText[1] as $piece) $found[] = $piece;
                }
            } else {
                $found[] = $literal;
            }
        }
    }
    // HTML text-node scanning is useful for PHP templates, but running it over JavaScript
    // source treats ">" and "<" inside JS/HTML string literals as if they were real tags.
    // That creates false fragments such as `href="...">Label`. JS literals are already
    // collected by the quoted-string scanner above, so only PHP templates need this pass.
    if (strtolower($file->getExtension()) === 'php') {
        $html = preg_replace('~<(script|style)\\b.*?</\\1>~is', "\0", $code) ?? $code;
        $html = preg_replace('~<\\?(?:php|=).*?\\?>~s', "\0", $html) ?? $html;
        if (preg_match_all('~>([^<>]+)<~u', $html, $m)) foreach ($m[1] as $text) foreach (explode("\0", $text) as $piece) $found[] = $piece;
    }
    foreach ($found as $text) {
        $text = ak_legacy_normalize(strip_tags(stripslashes($text)));
        // onclick="return confirm('message')" -> check the message itself (native dialogs are translated at runtime).
        if (preg_match('/\b(?:confirm|alert|prompt)\(\s*([\'"])(.*?)\1/us', $text, $dialog)) $text = ak_legacy_normalize($dialog[2]);
        // PHP HTML extraction can occasionally retain a structural quote prefix
        // such as `">طباعة سند الصرف`. Remove only that prefix, not meaningful text.
        $text = preg_replace("/^[\\s\"'>]+(?=[\\x{0600}-\\x{06FF}])/u", '', $text) ?? $text;
        if ($text === '' || !preg_match('/[\x{0600}-\x{06FF}]/u', $text)) continue;
        // Ignore source-code / schema literals that contain Arabic but are not UI text.
        if (preg_match('/[\$\{\};]|->|::|\b(?:SELECT|INSERT|UPDATE|DELETE|ALTER|CREATE|DROP|TRUNCATE)\b|\b(?:FROM|JOIN|WHERE|VALUES|SET|ADD|COLUMN|TABLE)\b/i', $text)) continue;
        if (preg_match('/\b(?:document|getElementById|querySelector|classList|style|innerHTML|textContent)\b|===|!==|=>|\b(?:CASE|WHEN|THEN|ELSE|END)\b/i', $text)) continue;
        // CSS/DOM selector literals containing Arabic attribute values are code, not visible UI text.
        if (preg_match('/(?:^|[\\s,{])(?:[.#][A-Za-z_-][\\w-]*|[A-Za-z][\\w-]*)\\[[^\\]]*[\\x{0600}-\\x{06FF}][^\\]]*\\]/u', $text)) continue;
        // Regex literals containing Arabic are executable patterns, not translatable UI text.
        if (preg_match('~^/(?:[^/\\\\]|\\\\.)*[\\x{0600}-\\x{06FF}](?:[^/\\\\]|\\\\.)*/[a-z]*$~iu', $text)) continue;
        // Single Arabic letters are data/filter values, not translatable UI words.
        if (mb_strlen(preg_replace('/\s+/u', '', $text) ?? $text) <= 2) continue;
        // SQL LIKE wildcards and enum/value lists are source/data fragments, not UI text.
        if (strpos($text, '%') !== false || preg_match('/\b(?:male|female|ذكر|أنثى|انثى)\b.*\b(?:male|female|ذكر|أنثى|انثى)\b/iu', $text)) continue;
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
