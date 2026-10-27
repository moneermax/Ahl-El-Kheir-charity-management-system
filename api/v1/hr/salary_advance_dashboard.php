<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_salary_advance_accounting.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET') {
    header('Allow: GET');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
}

$user = ak_api_authenticated_user();
$role = (string)$user['role_code'];
if (!in_array($role, ['financial_manager', 'admin', 'fm'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Salary-advance dashboard access is not available for this role.']]);
}

try {
    $counts = dbFetchOne(
        "SELECT
            SUM(CASE WHEN status IN ('submitted','fm_review') THEN 1 ELSE 0 END) AS fm_pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS accounting_pending,
            SUM(CASE WHEN status = 'disbursed' THEN 1 ELSE 0 END) AS disbursed,
            SUM(CASE WHEN status = 'settled' THEN 1 ELSE 0 END) AS settled
         FROM hr_salary_advance_requests"
    ) ?: [];

    $history = hrSalaryAdvanceAccountingHistory(db());
    ak_api_json(200, ['ok' => true, 'data' => [
        'role' => $role,
        'can_manage_policy' => true,
        'can_reschedule' => in_array($role, ['financial_manager', 'fm'], true),
        'counts' => [
            'fm_pending' => (int)($counts['fm_pending'] ?? 0),
            'accounting_pending' => (int)($counts['accounting_pending'] ?? 0),
            'disbursed' => (int)($counts['disbursed'] ?? 0),
            'settled' => (int)($counts['settled'] ?? 0),
        ],
        'history' => $history,
    ]]);
} catch (Throwable $e) {
    error_log('Mobile salary-advance dashboard read failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'salary_advance_dashboard_unavailable', 'message' => 'Salary-advance dashboard data is temporarily unavailable.']]);
}
