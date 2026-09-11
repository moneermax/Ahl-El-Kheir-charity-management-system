<?php
// Narrow accounting hardening: disbursements no longer accepts the legacy
// `accountant` role. Current roles are `financial_manager` for accounting
// authority and `accountant_staff` for assigned operational processing.
if (basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== 'disbursements.php') {
    return;
}

require_once __DIR__ . '/../../config/session.php';
Session::start();

if (Session::isLoggedIn() && Session::getUserRole() === 'accountant') {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
