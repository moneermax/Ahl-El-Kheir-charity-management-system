<?php
/**
 * CLI-only i18n audit.
 *
 * Run from the project root:
 *   php tools/i18n_audit.php
 *
 * It audits application source for hard-coded Arabic UI text and validates
 * that every key in the authoritative Arabic/English catalogs exists in both.
 * Database values are never inspected.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$root = dirname(__DIR__);
$arFile = $root . '/lang/ar.php';
$enFile = $root . '/lang/catalog_en.php';
$ar = is_file($arFile) ? require $arFile : [];
$en = is_file($enFile) ? require $enFile : [];
$ar = is_array($ar) ? $ar : [];
$en = is_array($en) ? $en : [];

$arKeys = array_keys($ar);
$enKeys = array_keys($en);
$missingEn = array_values(array_diff($arKeys, $enKeys));
$missingAr = array_values(array_diff($enKeys, $arKeys));

$extensions = ['php', 'js', 'html'];
$excluded = [DIRECTORY_SEPARATOR . 'TCPDF' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$arabicHits = [];
$keyHits = [];

foreach ($files as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    $skip = false;
    foreach ($excluded as $part) {
        if (str_contains($normalized, $part)) { $skip = true; break; }
    }
    if ($skip || !in_array(strtolower($file->getExtension()), $extensions, true)) continue;

    $content = @file_get_contents($path);
    if ($content === false) continue;
    $relative = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);

    if (preg_match_all('/[\x{0600}-\x{06FF}][^\r\n<>{};]*[\x{0600}-\x{06FF}]/u', $content, $matches)) {
        foreach (array_unique($matches[0]) as $match) {
            $match = trim(preg_replace('/\s+/u', ' ', $match) ?? $match);
            if ($match !== '') $arabicHits[$relative][] = mb_substr($match, 0, 160);
        }
    }

    if (preg_match_all("/(?:t|AKLang\\.t)\\s*\\(\\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
        foreach (array_unique($matches[1]) as $key) $keyHits[$relative][] = $key;
    }
}

$hardCodedCount = 0;
foreach ($arabicHits as $items) $hardCodedCount += count($items);
$keyCount = 0;
$unknownKeys = [];
foreach ($keyHits as $path => $keys) {
    $keyCount += count($keys);
    foreach ($keys as $key) {
        if (!array_key_exists($key, $ar) || !array_key_exists($key, $en)) $unknownKeys[$path][] = $key;
    }
}

$ok = !$missingEn && !$missingAr && !$unknownKeys;
echo "AHL EL KHEIR I18N AUDIT\n";
echo str_repeat('=', 28) . "\n";
printf("Arabic catalog keys : %d\n", count($arKeys));
printf("English catalog keys: %d\n", count($enKeys));
printf("Missing English     : %d\n", count($missingEn));
printf("Missing Arabic      : %d\n", count($missingAr));
printf("Key usages scanned  : %d\n", $keyCount);
printf("Unknown key usages  : %d\n", array_sum(array_map('count', $unknownKeys)));
printf("Arabic source hits  : %d\n", $hardCodedCount);

if ($missingEn) { echo "\nMissing English keys:\n"; foreach ($missingEn as $key) echo "  - $key\n"; }
if ($missingAr) { echo "\nMissing Arabic keys:\n"; foreach ($missingAr as $key) echo "  - $key\n"; }
if ($unknownKeys) {
    echo "\nUnknown translation keys:\n";
    foreach ($unknownKeys as $path => $keys) foreach (array_unique($keys) as $key) echo "  - $path :: $key\n";
}
if ($arabicHits) {
    echo "\nArabic source literals (review; names/data may be legitimate):\n";
    foreach ($arabicHits as $path => $items) foreach (array_unique($items) as $item) echo "  - $path :: $item\n";
}

echo "\nResult: " . ($ok ? 'PASS' : 'REVIEW REQUIRED') . "\n";
exit($ok ? 0 : 1);
