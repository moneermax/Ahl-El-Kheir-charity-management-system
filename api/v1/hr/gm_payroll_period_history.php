<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if (!in_array((string)($user['raw_role_code'] ?? ''), ['general_manager', 'gm'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'General Manager access is required.']]);
}
require_once dirname(__DIR__, 3) . '/modules/hr/lib_gm_payroll_period.php';
try {
    $pdo = db();
    $periodRows = dbFetchAll("SELECT DISTINCT year, month FROM payroll WHERE status = 'paid' ORDER BY year DESC, month DESC");
    $periods = [];
    foreach ($periodRows as $periodRow) {
        $month = (int)$periodRow['month'];
        $year = (int)$periodRow['year'];
        $state = hrGmPayrollPeriodState($pdo, $month, $year);
        $net = 0.0;
        $incentives = 0.0;
        foreach ($state['employees'] as $employee) {
            if (!$employee['completion_paid']) continue;
            $net += (float)$employee['net_salary'];
            $incentives += (float)($employee['incentive_amount'] ?? 0);
        }
        $periods[] = [
            'month' => $month, 'year' => $year, 'period' => sprintf('%04d-%02d', $year, $month),
            'eligible_count' => (int)$state['eligible_count'], 'paid_count' => (int)$state['paid_count'],
            'complete' => (bool)$state['complete'], 'net' => round($net, 2),
            'incentives' => round($incentives, 2), 'total' => round($net + $incentives, 2),
        ];
    }
    ak_api_json(200, ['ok' => true, 'data' => ['periods' => $periods, 'count' => count($periods), 'read_only' => true]]);
} catch (Throwable $e) {
    error_log('Mobile GM payroll period history failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'payroll_period_history_failed', 'message' => 'The payroll period history could not be loaded.']]);
}
