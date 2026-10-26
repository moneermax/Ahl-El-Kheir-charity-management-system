<?php
declare(strict_types=1);

/** Shared payroll period eligibility and draft-refresh helpers used by web and mobile API. */

function payrollEligibleEmployees(PDO $pdo, string $periodEnd): array
{
    return dbFetchAll(
        "SELECT e.id, e.employee_code, e.full_name, e.hire_date,
                s.basic_salary, s.salary_currency, s.pay_frequency,
                c.id AS contract_id, c.contract_number, c.contract_type
         FROM employees e
         JOIN hr_employee_salary_history s ON s.id = (
             SELECT s2.id FROM hr_employee_salary_history s2
             WHERE s2.employee_id = e.id
               AND s2.effective_from <= ?
               AND (s2.effective_to IS NULL OR s2.effective_to >= ?)
             ORDER BY s2.effective_from DESC, s2.id DESC LIMIT 1
         )
         LEFT JOIN hr_employee_contracts c ON c.id = s.contract_id
         WHERE e.hire_date IS NULL OR e.hire_date <= ?
         ORDER BY e.full_name ASC",
        [$periodEnd, $periodEnd, $periodEnd]
    );
}

function payrollStateAtDate(int $employeeId, string $periodEnd): ?array
{
    return dbFetchOne(
        "SELECT s.code, s.name_ar, s.name_en, s.category
         FROM hr_employee_state_history h
         JOIN hr_employment_states s ON s.id = h.employment_state_id
         WHERE h.employee_id = ?
           AND h.effective_from <= ?
           AND (h.effective_to IS NULL OR h.effective_to >= ?)
         ORDER BY h.effective_from DESC, h.id DESC LIMIT 1",
        [$employeeId, $periodEnd . ' 23:59:59', $periodEnd . ' 00:00:00']
    );
}

function payrollRefreshDraftBaseSalary(PDO $pdo, int $month, int $year): int
{
    $periodEnd = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    $drafts = dbFetchAll(
        "SELECT p.id, p.employee_id, p.month, p.year, p.allowances, p.overtime,
                p.deductions, p.salary_advance_deduction, s.basic_salary
         FROM payroll p
         JOIN hr_employee_salary_history s ON s.id = (
             SELECT s2.id FROM hr_employee_salary_history s2
             WHERE s2.employee_id = p.employee_id
               AND s2.effective_from <= ?
               AND (s2.effective_to IS NULL OR s2.effective_to >= ?)
             ORDER BY s2.effective_from DESC, s2.id DESC LIMIT 1
         )
         WHERE p.month = ? AND p.year = ? AND p.status = 'draft'",
        [$periodEnd, $periodEnd, $month, $year]
    );

    $updated = 0;
    $salaryStmt = $pdo->prepare(
        "UPDATE payroll
         SET basic_salary = ?
         WHERE id = ? AND status = 'draft'"
    );

    foreach ($drafts as $row) {
        $salaryStmt->execute([(float)$row['basic_salary'], (int)$row['id']]);
        hrPayrollRefreshAttendanceDeductionDraft($pdo, (int)$row['id']);
        hrSalaryAdvancePayrollRefreshDraft($pdo, (int)$row['id']);
        hrIncentiveRefreshPayrollDraft($pdo, (int)$row['id']);
        $updated++;
    }

    return $updated;
}
