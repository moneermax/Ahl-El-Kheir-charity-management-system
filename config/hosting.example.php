<?php
declare(strict_types=1);

/*
 * COPY THIS FILE TO config/hosting.php ON THE HOSTING SERVER ONLY.
 * Fill in the exact database values shown by the hosting control panel.
 * Do NOT commit config/hosting.php to Git.
 */

return [
    'db' => [
        'host' => 'REPLACE_WITH_HOSTING_DB_HOST',
        'port' => '3306',
        'name' => 'REPLACE_WITH_HOSTING_DB_NAME',
        'user' => 'REPLACE_WITH_HOSTING_DB_USER',
        'pass' => 'REPLACE_WITH_HOSTING_DB_PASSWORD',
    ],
];
