<?php
declare(strict_types=1);

/**
 * Compatibility entry point for the historical HR module index.
 *
 * The canonical HR landing page is dashboard/hr_dashboard.php.
 * Keep this URL working for existing bookmarks/internal references, but do not
 * maintain a second HR dashboard here.
 */
require_once __DIR__ . '/../../config/config.php';

header('Location: ' . APP_URL . 'dashboard/hr_dashboard.php');
exit();
