<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if (!in_array((string)($user['raw_role_code'] ?? ''), ['general_manager', 'gm'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'General Manager access is required.']]);
}
$month = filter_var($_GET['month'] ?? null, FILTER_VALIDATE_INT);
$year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
if ($month === false || $year === false || $month === null || $year === null || $month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_period', 'message' => 'Select a valid payroll month and year.']]);
}
require_once dirname(__DIR__, 3) . '/modules/hr/lib_gm_payroll_period.php';
try {
    $state = hrGmPayrollPeriodState(db(), (int)$month, (int)$year);
    $net = 0.0;
    $incentives = 0.0;
    $employees = [];
    foreach ($state['employees'] as $employee) {
        $complete = (bool)$employee['completion_paid'];
        if ($complete) {
            $net += (float)$employee['net_salary'];
            $incentives += (float)($employee['incentive_amount'] ?? 0);
        }
        $employees[] = [
            'employee_id' => (int)$employee['employee_id'], 'employee_code' => (string)$employee['employee_code'],
            'full_name' => (string)$employee['full_name'], 'salary_currency' => (string)($employee['salary_currency'] ?? ''),
            'effective_basic_salary' => (float)$employee['effective_basic_salary'],
            'payroll_id' => $employee['payroll_id'] === null ? null : (int)$employee['payroll_id'],
            'basic_salary' => $employee['basic_salary'] === null ? null : (float)$employee['basic_salary'],
            'allowances' => $employee['allowances'] === null ? null : (float)$employee['allowances'],
            'overtime' => $employee['overtime'] === null ? null : (float)$employee['overtime'],
            'deductions' => $employee['deductions'] === null ? null : (float)$employee['deductions'],
            'attendance_deduction' => $employee['attendance_deduction'] === null ? null : (float)$employee['attendance_deduction'],
            'salary_advance_deduction' => $employee['salary_advance_deduction'] === null ? null : (float)$employee['salary_advance_deduction'],
            'net_salary' => $employee['net_salary'] === null ? null : (float)$employee['net_salary'],
            'incentive_amount' => $employee['incentive_amount'] === null ? null : (float)$employee['incentive_amount'],
            'payment_date' => $employee['payment_date'] ?? null,
            'accounting_entry_id' => $employee['accounting_entry_id'] === null ? null : (int)$employee['accounting_entry_id'],
            'accounting_status' => $employee['accounting_status'] ?? null, 'payroll_status' => $employee['status'] ?? null,
            'proof_state' => $employee['proof_state'] ?? null, 'completion_paid' => $complete,
            'completion_has_proof' => (bool)$employee['completion_has_proof'],
            'completion_employee_notification' => (bool)$employee['completion_employee_notification'],
        ];
    }
    ak_api_json(200, ['ok' => true, 'data' => [
        'month' => (int)$month, 'year' => (int)$year, 'period' => sprintf('%04d-%02d', (int)$year, (int)$month),
        'period_end' => (string)$state['period_end'], 'eligible_count' => (int)$state['eligible_count'],
        'paid_count' => (int)$state['paid_count'], 'complete' => (bool)$state['complete'],
        'net' => round($net, 2), 'incentives' => round($incentives, 2), 'total' => round($net + $incentives, 2),
        'employees' => $employees, 'read_only' => true,
    ]]);
} catch (Throwable $e) {
    error_log('Mobile GM payroll period report failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'payroll_period_report_failed', 'message' => 'The payroll period report could not be loaded.']]);
}
