<?php
// modules/projects/view_pm.php - Dedicated Projects Manager project view
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
Session::start();
if (akp_role() !== 'projects_manager') { header('Location: ' . APP_URL . 'index.php'); exit(); }
$_GET['role_view'] = 'pm';
require __DIR__ . '/view.php';
?>