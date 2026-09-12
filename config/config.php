<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'Ahl El Kheir');
define('APP_NAME_AR', 'أهل الخير');
define('APP_ENV', 'development');

/* System-wide monetary configuration. */
define('APP_CURRENCY_CODE', 'SDG');
define('APP_CURRENCY_NAME_AR', 'الجنيه السوداني');
define('APP_CURRENCY_NAME_EN', 'Sudanese Pound');
define('APP_CURRENCY_SYMBOL', 'ج.س');

/*
|--------------------------------------------------------------------------
| Application filesystem root
|--------------------------------------------------------------------------
*/
define('APP_DIR', dirname(__DIR__));

/*
|--------------------------------------------------------------------------
| Dynamic Application URL
|--------------------------------------------------------------------------
*/
$appScheme = '';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $forwardedProto = strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
    if ($forwardedProto === 'https') $appScheme = 'https';
    elseif ($forwardedProto === 'http') $appScheme = 'http';
}
if ($appScheme === '') {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off' && $_SERVER['HTTPS'] !== '0') $appScheme = 'https';
    else $appScheme = 'http';
}
$appHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
if ($appHost === '') $appHost = 'localhost';
$documentRoot = rtrim(str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$appDirectory = rtrim(str_replace('\\', '/', APP_DIR), '/');
$appBasePath = '/';
if ($documentRoot !== '' && stripos($appDirectory, $documentRoot) === 0) {
    $relativeDirectory = trim(substr($appDirectory, strlen($documentRoot)), '/');
    if ($relativeDirectory !== '') $appBasePath = '/' . $relativeDirectory . '/';
}
if ($appBasePath === '/') {
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach (['/modules/', '/dashboard/', '/admin/', '/includes/'] as $knownDirectory) {
        $position = strpos($scriptName, $knownDirectory);
        if ($position !== false) {
            $detectedRoot = trim(substr($scriptName, 0, $position), '/');
            $appBasePath = $detectedRoot === '' ? '/' : '/' . $detectedRoot . '/';
            break;
        }
    }
}
$appBasePath = '/' . trim($appBasePath, '/') . '/';
if ($appBasePath === '//') $appBasePath = '/';
define('APP_BASE_PATH', $appBasePath);
define('APP_URL', $appScheme . '://' . $appHost . APP_BASE_PATH);

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'ahl_el_kheir');
define('DB_USER', 'root');
define('DB_PASS', '');

/* Tables */
define('TABLE_USERS', 'users');
define('TABLE_ROLES', 'roles');
define('TABLE_LETTERS', 'letters');
define('TABLE_SUPERVISOR_LETTERS', 'supervisor_letters');
define('TABLE_SPONSORS', 'sponsors');
define('TABLE_FAMILIES', 'families');
define('TABLE_FAMILY_CHILDREN', 'family_children');
define('TABLE_SPONSORSHIPS', 'sponsorships');
define('TABLE_SPONSORSHIP_CHILDREN', 'sponsorship_children');
define('TABLE_TRANSACTIONS', 'transactions');
define('TABLE_SETTINGS', 'settings');
define('TABLE_AUDIT_LOG', 'audit_log');

/* Storage */
define('BACKUP_DIR', APP_DIR . '/storage/backups');
define('LOG_DIR', APP_DIR . '/storage/logs');

date_default_timezone_set('Africa/Khartoum');

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/family_scope.php';

// Narrow accounting hardening: intercept only the legacy disbursement void POST
// before modules/accounting/disbursements.php can mutate a posted journal directly.
if (basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'disbursements.php'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['void_batch'])) {
    require_once __DIR__ . '/../modules/accounting/disbursement_void_guard.php';
}

// Narrow role normalization: the legacy `accountant` role is no longer valid
// for the disbursement workflow. Current authority is split between
// `financial_manager` and `accountant_staff`.
if (basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'disbursements.php') {
    require_once __DIR__ . '/../modules/accounting/disbursement_legacy_role_guard.php';
}