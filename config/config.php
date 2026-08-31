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

/*
|--------------------------------------------------------------------------
| Application filesystem root
|--------------------------------------------------------------------------
|
| On your XAMPP installation this becomes:
|
| D:/xampp/htdocs/AhlElKheir
|
*/
define('APP_DIR', dirname(__DIR__));


/*
|--------------------------------------------------------------------------
| Dynamic Application URL
|--------------------------------------------------------------------------
|
| Automatically detects:
|
|   Protocol:
|       http / https
|
|   Host:
|       localhost:8081
|       example.com
|
|   Application directory:
|       /AhlElKheir/
|       /
|
| Examples:
|
|   http://localhost:8081/AhlElKheir/
|   https://example.com/AhlElKheir/
|   https://example.com/
|
|--------------------------------------------------------------------------
*/


/*
 * STEP 1
 * Detect protocol.
 *
 * HTTP_X_FORWARDED_PROTO is useful when the live server is
 * behind a reverse proxy or SSL terminator.
 */
$appScheme = '';

if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {

    $forwardedProto = strtolower(
        trim(
            explode(
                ',',
                (string)$_SERVER['HTTP_X_FORWARDED_PROTO']
            )[0]
        )
    );

    if ($forwardedProto === 'https') {
        $appScheme = 'https';
    } elseif ($forwardedProto === 'http') {
        $appScheme = 'http';
    }
}


/*
 * If no proxy protocol was detected, inspect HTTPS directly.
 */
if ($appScheme === '') {

    if (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== '' &&
        strtolower((string)$_SERVER['HTTPS']) !== 'off' &&
        $_SERVER['HTTPS'] !== '0'
    ) {
        $appScheme = 'https';
    } else {
        $appScheme = 'http';
    }
}


/*
 * STEP 2
 * Detect hostname + port.
 *
 * HTTP_HOST normally gives:
 *
 * localhost:8081
 * example.com
 * example.com:8443
 */
$appHost = trim(
    (string)($_SERVER['HTTP_HOST'] ?? '')
);

if ($appHost === '') {
    $appHost = 'localhost';
}


/*
 * STEP 3
 * Normalize filesystem paths.
 *
 * Windows:
 *   D:\xampp\htdocs
 *
 * becomes:
 *   D:/xampp/htdocs
 */
$documentRoot = rtrim(
    str_replace(
        '\\',
        '/',
        (string)($_SERVER['DOCUMENT_ROOT'] ?? '')
    ),
    '/'
);

$appDirectory = rtrim(
    str_replace(
        '\\',
        '/',
        APP_DIR
    ),
    '/'
);


/*
 * STEP 4
 * Detect application subdirectory.
 *
 * Example:
 *
 * DOCUMENT_ROOT:
 * D:/xampp/htdocs
 *
 * APP_DIR:
 * D:/xampp/htdocs/AhlElKheir
 *
 * Result:
 *
 * /AhlElKheir/
 */
$appBasePath = '/';

if (
    $documentRoot !== '' &&
    stripos($appDirectory, $documentRoot) === 0
) {

    $relativeDirectory = trim(
        substr(
            $appDirectory,
            strlen($documentRoot)
        ),
        '/'
    );

    if ($relativeDirectory !== '') {

        $appBasePath =
            '/' .
            $relativeDirectory .
            '/';
    }
}


/*
 * STEP 5
 * Secondary fallback.
 *
 * This is useful on hosting environments where DOCUMENT_ROOT
 * does not directly match APP_DIR.
 */
if ($appBasePath === '/') {

    $scriptName = str_replace(
        '\\',
        '/',
        (string)($_SERVER['SCRIPT_NAME'] ?? '')
    );

    /*
     * Look for a known application-level directory.
     */
    $knownDirectories = [
        '/modules/',
        '/dashboard/',
        '/admin/',
        '/includes/',
    ];

    foreach ($knownDirectories as $knownDirectory) {

        $position = strpos(
            $scriptName,
            $knownDirectory
        );

        if ($position !== false) {

            $detectedRoot = substr(
                $scriptName,
                0,
                $position
            );

            $detectedRoot = trim(
                $detectedRoot,
                '/'
            );

            if ($detectedRoot === '') {
                $appBasePath = '/';
            } else {
                $appBasePath =
                    '/' .
                    $detectedRoot .
                    '/';
            }

            break;
        }
    }
}


/*
 * STEP 6
 * Normalize base path.
 */
$appBasePath = '/' .
    trim($appBasePath, '/') .
    '/';

if ($appBasePath === '//') {
    $appBasePath = '/';
}


/*
 * STEP 7
 * Export application URL constants.
 */
define(
    'APP_BASE_PATH',
    $appBasePath
);

define(
    'APP_URL',
    $appScheme .
    '://' .
    $appHost .
    APP_BASE_PATH
);


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


/*
|--------------------------------------------------------------------------
| Tables
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Storage
|--------------------------------------------------------------------------
*/

define(
    'BACKUP_DIR',
    APP_DIR . '/storage/backups'
);

define(
    'LOG_DIR',
    APP_DIR . '/storage/logs'
);


/*
|--------------------------------------------------------------------------
| Timezone
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Africa/Khartoum');


/*
|--------------------------------------------------------------------------
| Error reporting
|--------------------------------------------------------------------------
*/

if (APP_ENV === 'development') {

    ini_set(
        'display_errors',
        '1'
    );

    error_reporting(E_ALL);

} else {

    ini_set(
        'display_errors',
        '0'
    );
}


/*
|--------------------------------------------------------------------------
| Language
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/lang.php';