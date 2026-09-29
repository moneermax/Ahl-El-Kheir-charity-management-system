<?php
declare(strict_types=1);

/**
 * Stage 5 — payroll draft integration for salary-advance repayments.
 *
 * This unit only calculates the salary-advance component for a payroll draft.
 * It does not mark repayments paid, reduce the outstanding balance, or post
 * accounting entries. Those actions belong to the transactional payroll-paid
 * integration unit.
 */

function hrSalaryAdvancePayrollPendingRows(PDO $pdo, int $employeeId, string $periodStart): array
{
    if ($employeeId <= 0 || $periodStart === '') {
        return [];
    }

    return dbFetchAll(
        "SELECT
            r.id AS request_id,
            r.request_no,
            r.employee_id,
            r.outstanding_balance,
            r.approved_repayment_method,
            s.id AS schedule_id,
            s.installment_no,
            s.scheduled_amount,
            s.applied_amount,
            s.status AS schedule_status,
            p.maximum_monthly_deduction,
            p.insufficient_salary_rule,
            p.eligible_salary_basis
         FROM hr_salary_advance_repayment_schedule s
         JOIN hr_salary_advance_requests r
           ON r.id = s.salary_advance_request_id
         JOIN hr_salary_advance_policy_versions p
           ON p.id = r.policy_version_id
         WHERE r.employee_id = ?
           AND r.status = 'disbursed'
           AND COALESCE(r.outstanding_balance, 0) > 0
           AND s.scheduled_month = ?
           AND s.status IN ('pending', 'partial')
         ORDER BY s.installment_no ASC, s.id ASC",
        [$employeeId, $periodStart]
    );
}

/**
 * Calculate the salary-advance repayment component for one payroll draft.
 *
 * The result is a preview only. No repayment/schedule state is mutated.
 */
function hrSalaryAdvancePayrollCalculateDraft(PDO $pdo, array $payroll, string $periodStart): array
{
    $employeeId = (int)($payroll['employee_id'] ?? 0);
    if ($employeeId <= 0) {
        return ['total' => 0.00, 'allocations' => []];
    }

    $rows = hrSalaryAdvancePayrollPendingRows($pdo, $employeeId, $periodStart);
    if (!$rows) {
        return ['total' => 0.00, 'allocations' => []];
    }

    $basic = round((float)($payroll['basic_salary'] ?? 0), 2);
    $allowances = round((float)($payroll['allowances'] ?? 0), 2);
    $overtime = round((float)($payroll['overtime'] ?? 0), 2);
    $ordinaryDeductions = round((float)($payroll['deductions'] ?? 0), 2);

    $gross = round(max(0.00, $basic + $allowances + $overtime), 2);
    $netBeforeAdvance = round(max(0.00, $gross - $ordinaryDeductions), 2);

    $remainingEligibleSalary = null;
    $total = 0.00;
    $allocations = [];

    foreach ($rows as $row) {
        $scheduledRemaining = round(
            max(0.00, (float)$row['scheduled_amount'] - (float)($row['applied_amount'] ?? 0)),
            2
        );
        $requestOutstanding = round(max(0.00, (float)$row['outstanding_balance']), 2);
        $remaining = round(min($scheduledRemaining, $requestOutstanding), 2);

        if ($remaining <= 0.00) {
            continue;
        }

        $basis = (string)($row['eligible_salary_basis'] ?? 'net_before_advance');
        $eligibleSalary = $basis === 'gross' ? $gross : $netBeforeAdvance;

        if ($remainingEligibleSalary === null) {
            $remainingEligibleSalary = $eligibleSalary;
        }

        $availableSalary = round(max(0.00, $remainingEligibleSalary), 2);
        $maximumDeduction = $row['maximum_monthly_deduction'] !== null
            ? round(max(0.00, (float)$row['maximum_monthly_deduction']), 2)
            : null;

        $cap = $remaining;
        if ($maximumDeduction !== null && $maximumDeduction > 0) {
            $cap = min($cap, $maximumDeduction);
        }

        $method = (string)($row['approved_repayment_method'] ?? '');
        if ($method === 'full_eligible_salary') {
            $planned = min($cap, $availableSalary);
        } else {
            $planned = min($cap, $availableSalary);
        }

        $insufficientRule = (string)($row['insufficient_salary_rule'] ?? 'available_salary');
        $isInsufficient = $availableSalary + 0.000001 < $cap;

        $actual = $planned;
        $outcome = $actual + 0.000001 >= $remaining ? 'applied' : 'partial';
        $reason = null;

        if ($isInsufficient && $insufficientRule === 'skip_month') {
            $actual = 0.00;
            $outcome = 'skipped';
            $reason = 'راتب مؤهل غير كافٍ وفق سياسة السلفة — تم إعداد الشهر للتجاوز.';
        } elseif ($isInsufficient && $insufficientRule === 'available_salary') {
            $actual = min($planned, $availableSalary);
            $outcome = $actual + 0.000001 >= $remaining ? 'applied' : 'partial';
            $reason = 'تم تحديد الخصم وفق الراتب المؤهل المتاح.';
        }

        $actual = round(max(0.00, min($actual, $remaining)), 2);

        if ($actual > 0) {
            $remainingEligibleSalary = round(max(0.00, $remainingEligibleSalary - $actual), 2);
            $total = round($total + $actual, 2);
        }

        $allocations[] = [
            'request_id' => (int)$row['request_id'],
            'request_no' => (string)$row['request_no'],
            'schedule_id' => (int)$row['schedule_id'],
            'installment_no' => (int)$row['installment_no'],
            'scheduled_amount' => round((float)$row['scheduled_amount'], 2),
            'remaining_schedule_amount' => $remaining,
            'eligible_salary' => $eligibleSalary,
            'maximum_allowed_deduction' => $maximumDeduction,
            'actual_amount' => $actual,
            'outcome' => $outcome,
            'outcome_reason' => $reason,
        ];
    }

    return ['total' => $total, 'allocations' => $allocations];
}

function hrSalaryAdvancePayrollRefreshDraft(PDO $pdo, int $payrollId): void
{
    if ($payrollId <= 0) {
        throw new InvalidArgumentException('سجل الرواتب غير صالح.');
    }

    $payroll = dbFetchOne(
        "SELECT id, employee_id, month, year, basic_salary, allowances, overtime,
                deductions, status, salary_advance_deduction
         FROM payroll
         WHERE id = ?
         LIMIT 1",
        [$payrollId]
    );

    if (!$payroll) {
        throw new RuntimeException('سجل الرواتب غير موجود.');
    }
    if ((string)$payroll['status'] !== 'draft') {
        return;
    }

    $periodStart = sprintf(
        '%04d-%02d-01',
        (int)$payroll['year'],
        (int)$payroll['month']
    );

    $preview = hrSalaryAdvancePayrollCalculateDraft($pdo, $payroll, $periodStart);
    $salaryAdvanceDeduction = round((float)$preview['total'], 2);

    $gross = round(
        max(
            0.00,
            (float)$payroll['basic_salary']
            + (float)$payroll['allowances']
            + (float)$payroll['overtime']
        ),
        2
    );
    $net = round(
        $gross
        - (float)$payroll['deductions']
        - $salaryAdvanceDeduction,
        2
    );
    if ($net < 0) {
        $net = 0.00;
    }

    dbExecute(
        "UPDATE payroll
         SET salary_advance_deduction = ?,
             net_salary = ?
         WHERE id = ?
           AND status = 'draft'",
        [$salaryAdvanceDeduction, $net, $payrollId]
    );
}
