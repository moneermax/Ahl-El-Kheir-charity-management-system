<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once __DIR__ . '/query_service.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$role = (string)$user['role_code'];
$allowed = ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'accountant_staff', 'financial_manager'];
if (!in_array($role, $allowed, true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Transaction access is not allowed for this account.']]);
}

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q, 'UTF-8') > 100) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_search', 'message' => 'Search text is too long.']]);
}

$status = (string)($_GET['status'] ?? '');
if ($status !== '' && !in_array($status, ['pending_fm_review', 'returned', 'cancelled', 'posted', 'voided'], true)) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_status', 'message' => 'Invalid transaction status filter.']]);
}

$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
foreach (['from' => $from, 'to' => $to] as $key => $date) {
    if ($date !== '' && (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))
    )) {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_date', 'message' => $key . ' must use YYYY-MM-DD format.']]);
    }
}
if ($from !== '' && $to !== '' && $from > $to) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_date_range', 'message' => 'The start date must not be after the end date.']]);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$data = ak_api_list_transactions($userId, $role, $q, $status, $from, $to, $page);
ak_api_json(200, ['ok' => true, 'data' => $data]);
