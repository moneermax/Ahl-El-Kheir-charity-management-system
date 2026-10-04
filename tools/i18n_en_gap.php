<?php
/** CLI-only English -> Arabic UI-source audit. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

$root = dirname(__DIR__);
$showAll = in_array('--all', $argv ?? [], true);
$bridge = is_file($root . '/lang/bridge/en_to_ar.php') ? require $root . '/lang/bridge/en_to_ar.php' : [];

$dict = [];
$flatten = static function ($value) use (&$flatten, &$dict): void {
    if (!is_array($value)) return;
    foreach ($value as $key => $item) {
        if (is_string($key) && is_string($item)) $dict[$key] = $item;
        elseif (is_array($item)) $flatten($item);
    }
};
$flatten($bridge);

$skip = ['/\.git/', '/storage/', '/database/', '/lang/', '/tools/', '/docs/', '/vendor/', '/TCPDF/', '/node_modules/'];
$missing = [];

function isIntentionalEnglish(string $path, string $text): bool {
    $t = strtolower(trim($text));
    // Known organization/brand names and technical display notation.
    $known = [
        'feena al-khair',
        'sudan flag',
        'pdf, jpg, png',
        'pdf, jpg, png, gif',
        'csv utf-8',
    ];
    if (in_array($t, $known, true)) return true;

    // English labels intentionally printed alongside Arabic in bilingual documents.
    if (preg_match('/(?:voucher|receipt|print|payment|repayment|fina)/i', $path)
        && preg_match('/^(?:prepared by|financial manager|printed by|posted|print|salary advance (?:payment voucher|repayment receipt)|voucher issued and posted(?:\s*[—-].*)?|new voucher)$/i', trim($text))) {
        return true;
    }

    return false;
}

function addCandidate(string $path, string $raw, array $dict, array &$missing): void {
    $text = html_entity_decode(trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim($text, " \t\r\n\"'");
    if ($text === '' || mb_strlen($text) > 220 || !preg_match('/[A-Za-z]/', $text)) return;
    if (isIntentionalEnglish($path, $text)) return;
    // Mixed Arabic/English strings are already Arabic-mode UI or bilingual/technical
    // content; this audit targets English-only UI text needing Arabic.
    if (preg_match('/[\\x{0600}-\\x{06FF}]/u', $text)) return;
    // Source-code fragments and translation-key calls are not visible English UI.
    if (preg_match('/(?:<\\/?[A-Za-z][^>]*>|<\\?php|\\?>|\\b(?:echo|print|t|ak_t)\\s*\\(|AK_LANG|csrf_token\\s*\\()/i', $text)) return;
    if (preg_match('/\$[A-Za-z_]|\{\{|\b(?:const|let|var|function)\b/i', $text)) return;
    // Dynamic JS fragments are implementation details, not standalone UI labels.
    if (preg_match('/\$\{|(?:escapeHtml|formatAmount|formatBytes|bytesLabel|bytes|esc)\s*\(|(?:toLocaleString|attachments\.length|window\.|innerHeight|actualHeight)\b/i', $text)) return;
    if (preg_match('/^[+\s]*(?:[A-Za-z_$][A-Za-z0-9_$]*\.)?[A-Za-z_$][A-Za-z0-9_$]*[+\s]*$/', $text)) return;
    // SQL fragments found by markup-like scanning are implementation details.
    if (preg_match('/\b(?:SELECT|UPDATE|INSERT|DELETE|FROM|WHERE|JOIN|LIMIT|SET)\b/i', $text)
        && preg_match('/[?=(),]/', $text)) return;
    if (preg_match('/^(?:https?:\/\/|mailto:|javascript:|[A-Za-z]:\\\\)/i', $text)) return;
    if (preg_match('/^[A-Za-z0-9._:#\/\\-]+$/', $text) && !preg_match('/\s/', $text)) return;

    $words = preg_split('/[^A-Za-z]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$words) return;
    $singleUi = ['save','cancel','delete','edit','add','back','search','view','submit','approve','reject','close','open','print','download','upload','logout','login','dashboard','settings','reports','accounting','employees','projects','sponsors','families','notifications','messages','attendance','payroll','return','confirm','yes','no','actions','phone','email','address','status','role','user','size'];
    if (count($words) === 1 && !in_array(strtolower($text), $singleUi, true)) return;
    if (array_key_exists($text, $dict)) return;
    $missing[$path][$text] = true;
}

function scanMarkup(string $path, string $code, array $dict, array &$missing): void {
    // Remove PHP blocks and intentionally bilingual English-only presentation elements
    // before scanning visible markup. These are not Arabic-mode translation defects.
    $html = preg_replace('~<\?(?:php|=).*?\?>~s', '', $code) ?? $code;
    $html = preg_replace('~<(?:span|div|small|button|td|th|p|label)[^>]*class=["\'][^"\']*\\b(?:en|title-en|org-en|name-en|english)[^"\']*["\'][^>]*>.*?</(?:span|div|small|button|td|th|p|label)>~is', '', $html) ?? $html;
    $html = preg_replace('~<script\b.*?</script>~is', '', $html) ?? $html;
    $html = preg_replace('~<style\b.*?</style>~is', '', $html) ?? $html;

    if (preg_match_all('~>([^<>]+)<~u', $html, $m)) {
        foreach ($m[1] as $text) addCandidate($path, $text, $dict, $missing);
    }
    if (preg_match_all('~\b(?:placeholder|title|aria-label|alt)\s*=\s*(["\'])(.*?)\1~isu', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) addCandidate($path, $row[2], $dict, $missing);
    }
    if (preg_match_all('~\bvalue\s*=\s*(["\'])(.*?)\1~isu', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) addCandidate($path, $row[2], $dict, $missing);
    }
}

function scanJs(string $path, string $code, array $dict, array &$missing): void {
    // Ignore JS dictionaries whose values are intentionally selected according
    // to the active language (for example lang === 'en' ? {...} : {...}).
    $code = preg_replace("~\\b(?:const|let|var)\\s+text\\s*=\\s*lang\\s*===\\s*[\\\"']en[\\\"']\\s*\\?\\s*\\{.*?\\}\\s*:\\s*\\{.*?\\}\\s*;~is", '', $code) ?? $code;
    $code = preg_replace('~\b(?:const|let|var)\s+(?:labels|statusMap)\s*=\s*\{.*?\}\s*;~is', '', $code) ?? $code;

    $patterns = [
        '~\b(?:alert|confirm|prompt)\s*\(\s*(["\'])(.*?)\1~isu',
        '~\.(?:textContent|innerHTML)\s*=\s*(["\'])(.*?)\1~isu',
        '~\b(?:text|html|append|prepend|toast|notify)\s*\(\s*(["\'])(.*?)\1~isu',
        '~\b(?:placeholder|title|ariaLabel)\s*=\s*(["\'])(.*?)\1~isu',
    ];
    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $code, $m, PREG_SET_ORDER)) continue;
        foreach ($m as $row) addCandidate($path, $row[2], $dict, $missing);
    }
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php','js','html'], true)) continue;
    $path = str_replace('\\', '/', $file->getPathname());
    foreach ($skip as $part) if (strpos($path, $part) !== false) continue 2;
    $code = (string)@file_get_contents($path);
    if ($code === '') continue;
    $relative = substr($path, strlen($root) + 1);
    scanMarkup($relative, $code, $dict, $missing);
    if (strtolower($file->getExtension()) === 'js' || preg_match('/<script\b/i', $code)) scanJs($relative, $code, $dict, $missing);
}

ksort($missing);
$total = array_sum(array_map('count', $missing));
echo "AHL EL KHEIR - ENGLISH -> ARABIC UI SOURCE AUDIT\n" . str_repeat('=', 48) . "\n";
foreach ($missing as $file => $texts) {
    printf("%4d  %s\n", count($texts), $file);
    $items = array_keys($texts);
    if (!$showAll) $items = array_slice($items, 0, 10);
    foreach ($items as $text) echo '        ' . mb_substr($text, 0, 140) . "\n";
}
printf("\nFiles with candidate UI gaps: %d | Candidate unmapped English UI strings: %d\n", count($missing), $total);
echo $total === 0 ? "OK: no candidate English UI strings were found without bridge coverage.\n" : "REVIEW: confirm each candidate is genuine visible UI before changing translations.\n";
exit($total === 0 ? 0 : 1);
