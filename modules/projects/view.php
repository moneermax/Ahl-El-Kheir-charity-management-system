<?php
// modules/projects/view.php - Project profile, financial controls, documents, operations, closure
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_transaction_review.php';
Session::start();

$requestedProjectId = (int)($_GET['id'] ?? $_POST['project_id'] ?? 0);
if (!isset($_GET['role_view'])) {
    if (akp_role() === 'financial_manager') {
        header('Location: ' . APP_URL . 'modules/projects/view_fm.php?id=' . $requestedProjectId);
        exit();
    }
    if (akp_role() === 'projects_manager') {
        header('Location: ' . APP_URL . 'modules/projects/view_pm.php?id=' . $requestedProjectId);
        exit();
    }
}

$id = (int)($_GET['id'] ?? $_POST['project_id'] ?? 0);
$project = $id ? akp_get_project($id) : null;
if (!$project) {
    flash('error', 'المشروع غير موجود.');
    redirect('modules/projects/index.php');
}

if (!akp_can_view_project($id)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$project = akp_get_project($id);