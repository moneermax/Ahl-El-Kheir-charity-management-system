<?php
declare(strict_types=1);

/*
|---------------------------------------------------------------------------
| Environment-aware configuration
|---------------------------------------------------------------------------
| Hosting may provide these values as real environment variables.
| Local XAMPP keeps safe development defaults when they are not present.
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
$appHostForEnvironment = preg_replace('/:\\d+$/', '', $appHostForEnvironment) ?: $appHostForEnvironment;

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
| Production should provide AHL_DB_* through the hosting environment.
*/
define('DB_HOST', (string)ak_env('AHL_DB_HOST', '127.0.0.1'));
define('DB_PORT', (string)ak_env('AHL_DB_PORT', '3306'));
define('DB_NAME', (string)ak_env('AHL_DB_NAME', 'ahl_el_kheir'));
define('DB_USER', (string)ak_env('AHL_DB_USER', 'root'));
define('DB_PASS', (string)ak_env('AHL_DB_PASS', ''));

if (APP_ENV === 'production' && DB_PASS === '') {
    http_response_code(500);
    exit('Application configuration error.');
}
