<?php
// PM-specific project view entry point.
// The shared project view remains the single source of business logic while
// the PM receives a dedicated role-specific URL.
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
Session::start();

if (akp_role() !== 'projects_manager') {
    header('Location: ' . APP_URL . 'modules/projects/view.php?id=' . (int)($_GET['id'] ?? $_POST['project_id'] ?? 0));
    exit();
}

require __DIR__ . '/view.php';
