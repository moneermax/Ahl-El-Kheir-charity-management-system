<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/fina_lib.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)($user['raw_role_code'] ?? '');
$allowed = ['financial_manager', 'fm', 'admin', 'general_manager', 'vice_general_manager', 'supervisor'];
if (!in_array($role, $allowed, true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Fina dashboard access is not permitted for this role.',
    ]]);
}

try {
    fina_ensure_tables();
    $pending = dbFetchOne("SELECT COUNT(*) AS cnt FROM fina_collections WHERE status='pending'");
    $approved = dbFetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS amount FROM fina_collections WHERE status='approved'");
    $returned = dbFetchOne("SELECT COUNT(*) AS cnt FROM fina_collections WHERE status='returned'");
    $account = dbFetchOne("SELECT code,account_type,is_active FROM accounts WHERE code='2300' LIMIT 1");
    $canViewReview = in_array($role, ['financial_manager', 'fm', 'admin', 'vice_general_manager', 'supervisor'], true);

    ak_api_json(200, ['ok' => true, 'data' => [
        'pending_count' => (int)($pending['cnt'] ?? 0),
        'approved_count' => (int)($approved['cnt'] ?? 0),
        'returned_count' => (int)($returned['cnt'] ?? 0),
        'approved_amount' => (float)($approved['amount'] ?? 0),
        'currency_code' => APP_CURRENCY_CODE,
        'liability_account' => [
            'code' => (string)($account['code'] ?? '2300'),
            'configured' => $account !== null && ($account['account_type'] ?? '') === 'liability',
            'active' => $account !== null && (int)($account['is_active'] ?? 0) === 1,
        ],
        'can_view_review' => $canViewReview,
    ]]);
} catch (Throwable $e) {
    error_log('Mobile Fina dashboard failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'fina_dashboard_failed',
        'message' => 'Fina dashboard data could not be loaded.',
    ]]);
}
