<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_employee_identity.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$employee = hrGetEmployeeForUser(db(), (int)$user['id']);

if (!$employee) {
    ak_api_json(200, ['ok' => true, 'data' => [
        'employee_linked' => false,
        'items' => [],
        'total' => 0,
        'page' => 1,
        'pages' => 1,
    ]]);
}

$employeeId = (int)$employee['id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$eligibleWhere = "employee_id = ? AND status = 'paid' AND accounting_status = 'posted'";
$count = dbFetchOne("SELECT COUNT(*) AS total FROM payroll WHERE {$eligibleWhere}", [$employeeId]);
$total = (int)($count['total'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = dbFetchAll(
    "SELECT p.id, p.employee_id, p.month, p.year, p.basic_salary, p.allowances,
            p.overtime, p.deductions, p.attendance_deduction,
            p.salary_advance_deduction, p.incentive_amount, p.net_salary,
            p.payment_date, e.employee_code, e.full_name AS employee_name,
            COALESCE((
                SELECT s.salary_currency
                FROM hr_employee_salary_history s
                WHERE s.employee_id = p.employee_id
                  AND s.effective_from <= LAST_DAY(STR_TO_DATE(CONCAT(p.year, '-', LPAD(p.month, 2, '0'), '-01'), '%Y-%m-%d'))
                  AND (s.effective_to IS NULL OR s.effective_to >= LAST_DAY(STR_TO_DATE(CONCAT(p.year, '-', LPAD(p.month, 2, '0'), '-01'), '%Y-%m-%d')))
                ORDER BY s.effective_from DESC, s.id DESC
                LIMIT 1
            ), 'SDG') AS salary_currency
     FROM payroll p
     JOIN employees e ON e.id = p.employee_id
     WHERE p.employee_id = ?
       AND p.status = 'paid'
       AND p.accounting_status = 'posted'
     ORDER BY p.year DESC, p.month DESC, p.id DESC
     LIMIT " . (int)$perPage . " OFFSET " . (int)$offset,
    [$employeeId]
);

$items = array_map(static fn(array $row): array => [
    'id' => (int)$row['id'],
    'month' => (int)$row['month'],
    'year' => (int)$row['year'],
    'employee_code' => (string)$row['employee_code'],
    'employee_name' => (string)$row['employee_name'],
    'basic_salary' => (float)$row['basic_salary'],
    'allowances' => (float)$row['allowances'],
    'overtime' => (float)$row['overtime'],
    'deductions' => (float)$row['deductions'],
    'attendance_deduction' => (float)$row['attendance_deduction'],
    'salary_advance_deduction' => (float)$row['salary_advance_deduction'],
    'incentive_amount' => (float)$row['incentive_amount'],
    'net_salary' => (float)$row['net_salary'],
    'payment_date' => (string)($row['payment_date'] ?? ''),
    'salary_currency' => (string)($row['salary_currency'] ?? 'SDG'),
], $rows);

ak_api_json(200, ['ok' => true, 'data' => [
    'employee_linked' => true,
    'items' => $items,
    'total' => $total,
    'page' => $page,
    'pages' => $pages,
]]);
