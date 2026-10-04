<?php
/** Deterministic CLI self-test for the Ahl El Kheir i18n runtime. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$root = dirname(__DIR__);
$failures = [];

function check_test(string $name, bool $condition, string $detail = ''): void {
    global $failures;
    if ($condition) {
        echo "PASS  {$name}\n";
        return;
    }
    $failures[] = $name . ($detail !== '' ? " — {$detail}" : '');
    echo "FAIL  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

function load_runtime(string $language): array {
    $_COOKIE = [];
    $_GET = ['lang' => $language];
    $_SERVER['SCRIPT_NAME'] = '/AhlElKheir/tools/i18n_runtime_selftest.php';

    // Each language is loaded in a separate PHP process by the main test below.
    require dirname(__DIR__) . '/config/lang.php';

    return [
        'lang' => AK_LANG,
        'dir' => AK_DIR,
        'catalog' => ak_catalog(AK_LANG),
        'ar' => ak_catalog('ar'),
        'en' => ak_catalog('en'),
        'reverse' => ak_reverse_dict(),
    ];
}

echo "AHL EL KHEIR - I18N RUNTIME SELF-TEST\n";
echo str_repeat('=', 38) . "\n";

$language = $argv[1] ?? 'both';
if (!in_array($language, ['ar', 'en', 'both'], true)) {
    fwrite(STDERR, "Usage: php tools/i18n_runtime_selftest.php [ar|en|both]\n");
    exit(2);
}

$tests = $language === 'both' ? ['ar', 'en'] : [$language];

foreach ($tests as $testLanguage) {
    // Run each language in an isolated subprocess so config/lang.php constants/cache
    // cannot contaminate the opposite-language runtime.
    $bootstrap = <<<'PHP'
<?php
declare(strict_types=1);
$root = getenv('AK_TEST_ROOT') ?: getcwd();
$_COOKIE = [];
$_GET = ['lang' => getenv('AK_TEST_LANG') ?: 'ar'];
$_SERVER['SCRIPT_NAME'] = '/AhlElKheir/tools/i18n_runtime_selftest.php';
require $root . '/config/lang.php';

$results = [];
$results['lang'] = AK_LANG;
$results['dir'] = AK_DIR;
$results['catalog_keys_ar'] = count(ak_catalog('ar'));
$results['catalog_keys_en'] = count(ak_catalog('en'));
$results['sample_ar'] = ak_t('common.organization_name', []);
$results['sample_en'] = (function () {
    $_GET['lang'] = 'en';
    return ak_catalog('en')['common.organization_name'] ?? '';
})();
$results['reverse_home'] = ak_translate_text(
    'الرئيسية',
    ak_reverse_dict(),
    [],
    [],
    true
);
echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
PHP;

    $tmp = tempnam(sys_get_temp_dir(), 'ak_i18n_');
    if ($tmp === false) {
        $failures[] = 'temporary test bootstrap creation';
        echo "FAIL  temporary test bootstrap creation\n";
        continue;
    }
    file_put_contents($tmp, $bootstrap);

    $command = PHP_BINARY . ' ' . escapeshellarg($tmp);
    $env = ['AK_TEST_LANG' => $testLanguage, 'AK_TEST_ROOT' => $root];
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($command, $descriptor, $pipes, $root, $env);
    if (!is_resource($proc)) {
        unlink($tmp);
        $failures[] = $testLanguage . ' subprocess';
        echo "FAIL  {$testLanguage} subprocess\n";
        continue;
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    unlink($tmp);

    if ($exit !== 0) {
        $failures[] = $testLanguage . ' bootstrap';
        echo "FAIL  {$testLanguage} bootstrap — " . trim($stderr) . "\n";
        continue;
    }

    $data = json_decode($stdout, true);
    if (!is_array($data)) {
        $failures[] = $testLanguage . ' JSON result';
        echo "FAIL  {$testLanguage} JSON result\n";
        continue;
    }

    echo "\n[" . strtoupper($testLanguage) . "]\n";
    check_test('selected language', ($data['lang'] ?? '') === $testLanguage);
    check_test('writing direction', ($data['dir'] ?? '') === ($testLanguage === 'ar' ? 'rtl' : 'ltr'));
    check_test('Arabic catalog loaded', (int)($data['catalog_keys_ar'] ?? 0) > 0);
    check_test('English catalog loaded', (int)($data['catalog_keys_en'] ?? 0) > 0);
    check_test('organization key has Arabic value', preg_match('/[\x{0600}-\x{06FF}]/u', (string)($data['sample_ar'] ?? '')) === 1);
    check_test('organization key has English value', preg_match('/[A-Za-z]/', (string)($data['sample_en'] ?? '')) === 1);

    if ($testLanguage === 'en') {
        check_test(
            'Arabic compatibility phrase translates to English',
            (string)($data['reverse_home'] ?? '') === 'Home',
            'expected Home, got ' . (string)($data['reverse_home'] ?? '')
        );
    }
}

echo "\n" . str_repeat('=', 38) . "\n";
if ($failures) {
    echo "RESULT: FAIL\n";
    foreach ($failures as $failure) echo "  - {$failure}\n";
    exit(1);
}
echo "RESULT: PASS — language selection, direction, catalogs, and representative runtime translation are valid.\n";
