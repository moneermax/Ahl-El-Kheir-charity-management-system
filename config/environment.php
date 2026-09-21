<?php
declare(strict_types=1);

/*
|---------------------------------------------------------------------------
| Environment-aware configuration
|---------------------------------------------------------------------------
| Hosting may provide these values as real environment variables.
| Local XAMPP keeps safe development defaults when they are not present.
|
| Shared hosting fallback:
| If the host does not expose custom environment variables, a deployment-only
| config/hosting.php file may provide the production database settings.
| That file is intentionally ignored by Git and must never contain committed
| credentials.
*/

function ak_env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value === false || trim((string)$value) === '') {
        return $default;
    }
    return trim((string)$value);
}

function ak_is_local_host(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return true;
    }
    return str_starts_with($host, 'localhost:') || str_starts_with($host, '127.0.0.1:') || str_starts_with($host, '[::1]:');
}

$appHostForEnvironment = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
if ($appHostForEnvironment === '') {
    $appHostForEnvironment = 'localhost';
}
$appHostForEnvironment = preg_replace('/:\d+$/', '', $appHostForEnvironment) ?: $appHostForEnvironment;

$detectedEnvironment = ak_env('AHL_ENV');
if ($detectedEnvironment === null) {
    $detectedEnvironment = ak_is_local_host($appHostForEnvironment) ? 'development' : 'production';
}
$detectedEnvironment = strtolower($detectedEnvironment);

if (!in_array($detectedEnvironment, ['development', 'production'], true)) {
    $detectedEnvironment = 'development';
}

define('APP_ENV', $detectedEnvironment);

/*
|---------------------------------------------------------------------------
| Database environment variables
|---------------------------------------------------------------------------
| Local development remains compatible with the existing XAMPP setup.
| Production normally uses AHL_DB_* environment variables. Shared hosting
| can instead use the ignored config/hosting.php deployment file.
*/
$hostingConfig = [];
$hostingConfigPath = __DIR__ . '/hosting.php';

if (APP_ENV === 'production' && is_file($hostingConfigPath)) {
    $loadedHostingConfig = require $hostingConfigPath;
    if (is_array($loadedHostingConfig)) {
        $hostingConfig = $loadedHostingConfig;
    }
}

$hostingDb = isset($hostingConfig['db']) && is_array($hostingConfig['db'])
    ? $hostingConfig['db']
    : [];

define('DB_HOST', (string)ak_env('AHL_DB_HOST', (string)($hostingDb['host'] ?? '127.0.0.1')));
define('DB_PORT', (string)ak_env('AHL_DB_PORT', (string)($hostingDb['port'] ?? '3306')));
define('DB_NAME', (string)ak_env('AHL_DB_NAME', (string)($hostingDb['name'] ?? 'ahl_el_kheir')));
define('DB_USER', (string)ak_env('AHL_DB_USER', (string)($hostingDb['user'] ?? 'root')));
define('DB_PASS', (string)ak_env('AHL_DB_PASS', (string)($hostingDb['pass'] ?? '')));

if (APP_ENV === 'production' && (
    DB_HOST === '' ||
    DB_NAME === '' ||
    DB_USER === '' ||
    DB_PASS === ''
)) {
    http_response_code(500);
    exit('Application configuration error.');
}
