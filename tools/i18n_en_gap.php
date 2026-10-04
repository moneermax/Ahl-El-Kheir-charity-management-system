<?php
/** CLI-only English -> Arabic source gap audit. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

$root = dirname(__DIR__);
$showAll = in_array('--all', $argv ?? [], true);
$bridge = is_file($root . '/lang/bridge/en_to_ar.php') ? require $root . '/lang/bridge/en_to_ar.php' : [];
$dict = [];
if (is_array($bridge)) foreach ((array)($bridge['*'] ?? []) as $source => $target) {
    if (is_string($source) && is_string($target)) $dict[$source] = $target;
}
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$skip = ['/\.git/', '/storage/', '/database/', '/lang/', '/tools/', '/docs/', '/vendor/', '/TCPDF/', '/node_modules/'];
$technical = ['php','mysql','mariadb','javascript','typescript','html','css','utf','utf-8','ajax','json','sql','csrf','bootstrap','font','awesome','cairo','api','http','https','localhost','admin','user','users','role','status','type','action','data','name','value','file','files','date','time','true','false','null','required','number','string','array','select','option','form','input','button','modal','class','style','script','div','span','table','thead','tbody','tr','td','th','href','src','target','blank','get','post','put','delete'];
$singleUi = ['save','cancel','delete','edit','add','back','search','view','submit','approve','reject','close','open','print','download','upload','logout','login','dashboard','settings','reports','accounting','employees','projects','sponsors','families','notifications','messages','attendance','payroll','return','confirm','yes','no'];
$missing = [];

function addCandidate(string $path, string $raw, array $dict, array $technical, array $singleUi, array &$missing): void {
    $text = trim(preg_replace('/\\s+/u', ' ', $raw) ?? $raw);
    $text = trim($text, " \\t\\r\\n\\\"'");
    $text = strip_tags($text);
    if ($text === '' || mb_strlen($text) > 180 || !preg_match('/[A-Za-z]/', $text)) return;
    if (preg_match('/^(?:\\$|@|%|#|\\{|\\[)/', $text)) return;
    if (preg_match('/^(?:https?:\\/\\/|mailto:|javascript:|[A-Za-z]:[\\\\\\/])/', $text)) return;
    if (preg_match('/[;{}]|=>|->|::|===|!==|&&|\\|\\||\\b(?:SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|JOIN|VALUES|SET|ORDER BY|GROUP BY|CREATE TABLE|ALTER TABLE|DROP TABLE)\\b/i', $text)) return;
    if (preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*)(?:[._:-][A-Za-z0-9_]+)+$/', $text)) return;
    if (preg_match('/^[A-Za-z0-9._:\\\/-]+$/', $text)) return;
    $words = preg_split('/[^A-Za-z]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $natural = array_values(array_filter($words, static fn($w) => !in_array($w, $technical, true)));
    if (count($natural) < 2 && !in_array(strtolower($text), $singleUi, true)) return;
    if (array_key_exists($text, $dict)) return;
    $missing[$path][$text] = true;
}

foreach ($files as $file) {
    if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php','js','html'], true)) continue;
    $path = str_replace('\\', '/', $file->getPathname());
    foreach ($skip as $part) if (strpos($path, $part) !== false) continue 2;
    $code = (string)@file_get_contents($path);
    if ($code === '') continue;
    $code = preg_replace('~/\\*.*?\\*/~s', '', $code) ?? $code;
    $code = preg_replace('~^\\s*(//|#).*$~m', '', $code) ?? $code;
    if (preg_match_all("/'((?:[^'\\\\\\n]|\\\\.)*)'|\"((?:[^\"\\\\\\n]|\\\\.)*)\"/u", $code, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $literal = $match[1] !== '' ? $match[1] : ($match[2] ?? '');
            if (preg_match('/<\\/?[a-z][^>]*>/iu', $literal) && preg_match_all('~>([^<>]+)<~u', $literal, $nodes)) {
                foreach ($nodes[1] as $node) addCandidate(substr($path, strlen($root) + 1), $node, $dict, $technical, $singleUi, $missing);
            } else {
                addCandidate(substr($path, strlen($root) + 1), $literal, $dict, $technical, $singleUi, $missing);
            }
        }
    }
    if (strtolower($file->getExtension()) === 'php') {
        $html = preg_replace('~<(script|style)\\b.*?</\\1>~is', "\\0", $code) ?? $code;
        $html = preg_replace('~<\\?(?:php|=).*?\\?>~s', "\\0", $html) ?? $html;
        if (preg_match_all('~>([^<>]+)<~u', $html, $nodes)) foreach ($nodes[1] as $node) addCandidate(substr($path, strlen($root) + 1), $node, $dict, $technical, $singleUi, $missing);
    }
}
ksort($missing);
$total = array_sum(array_map('count', $missing));
echo "AHL EL KHEIR - ENGLISH -> ARABIC GAP AUDIT\n" . str_repeat('=', 44) . "\n";
foreach ($missing as $file => $texts) {
    printf("%4d  %s\n", count($texts), $file);
    $items = array_keys($texts);
    if (!$showAll) $items = array_slice($items, 0, 5);
    foreach ($items as $text) echo '        ' . mb_substr($text, 0, 120) . "\n";
}
printf("\nFiles with candidate gaps: %d | Candidate unmapped English strings: %d\n", count($missing), $total);
echo $total === 0 ? "OK: no candidate English UI strings were found without bridge coverage.\n" : "REVIEW: confirm each candidate is UI; prefer t('stable.key') for new code.\n";
exit($total === 0 ? 0 : 1);
