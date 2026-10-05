<?php
declare(strict_types=1);

function hrPayrollPolicyValidate(array $input): array
{
    $policy = [
        'effective_from' => trim((string)($input['effective_from'] ?? '')),
        'absence_enabled' => isset($input['absence_enabled']) ? 1 : 0,
        'absence_deduction_percent' => (float)($input['absence_deduction_percent'] ?? 100),
        'unpaid_leave_enabled' => isset($input['unpaid_leave_enabled']) ? 1 : 0,
        'unpaid_leave_deduction_percent' => (float)($input['unpaid_leave_deduction_percent'] ?? 100),
        'paid_leave_deduction_percent' => (float)($input['paid_leave_deduction_percent'] ?? 0),
        'late_enabled' => isset($input['late_enabled']) ? 1 : 0,
        'early_departure_enabled' => isset($input['early_departure_enabled']) ? 1 : 0,
        'overtime_enabled' => isset($input['overtime_enabled']) ? 1 : 0,
        'overtime_multiplier' => (float)($input['overtime_multiplier'] ?? 1),
        'daily_deduction_method' => 'monthly_salary_div_30',
        'rounding_decimals' => (int)($input['rounding_decimals'] ?? 2),
        'notes' => trim((string)($input['notes'] ?? '')) ?: null,
    ];

    $date = DateTime::createFromFormat('!Y-m-d', $policy['effective_from']);
    if (!$date || $date->format('Y-m-d') !== $policy['effective_from']) {
        throw new InvalidArgumentException('تاريخ سريان السياسة غير صالح.');
    }
    if ($policy['effective_from'] <= date('Y-m-d')) {
        throw new InvalidArgumentException('لا يمكن إنشاء نسخة جديدة بتاريخ سريان اليوم أو تاريخ سابق. استخدم تاريخاً مستقبلياً.');
    }

    foreach ([
        'absence_deduction_percent' => 'نسبة خصم الغياب',
        'unpaid_leave_deduction_percent' => 'نسبة خصم الإجازة غير المدفوعة',
        'paid_leave_deduction_percent' => 'نسبة خصم الإجازة المدفوعة',
    ] as $key => $label) {
        if ($policy[$key] < 0 || $policy[$key] > 100) {
            throw new InvalidArgumentException($label . ' يجب أن تكون بين 0 و100.');
        }
    }

    if ($policy['paid_leave_deduction_percent'] != 0.0) {
        throw new InvalidArgumentException('خصم الإجازة المدفوعة يجب أن يساوي 0 في الإصدار الأول.');
    }
    if ($policy['overtime_multiplier'] <= 0) {
        throw new InvalidArgumentException('معامل العمل الإضافي يجب أن يكون أكبر من صفر.');
    }
    if ($policy['daily_deduction_method'] !== 'monthly_salary_div_30') {
        throw new InvalidArgumentException('طريقة الخصم اليومية غير مدعومة في الإصدار الأول.');
    }
    if ($policy['rounding_decimals'] < 0 || $policy['rounding_decimals'] > 4) {
        throw new InvalidArgumentException('عدد المنازل العشرية يجب أن يكون بين 0 و4.');
    }

    return $policy;
}

function hrPayrollPolicyGetActive(PDO $pdo, ?string $asOfDate = null): ?array
{
    $date = $asOfDate ?: date('Y-m-d');
    return dbFetchOne(
        "SELECT * FROM hr_payroll_policy_versions
         WHERE effective_from <= ?
         ORDER BY effective_from DESC, version_no DESC
         LIMIT 1",
        [$date]
    );
}

function hrPayrollPolicyGetAll(PDO $pdo): array
{
    return dbFetchAll(
        "SELECT p.*, u.full_name AS created_by_name
         FROM hr_payroll_policy_versions p
         LEFT JOIN users u ON u.id = p.created_by
         ORDER BY p.effective_from ASC, p.version_no ASC"
    );
}


function hrPayrollAttendanceDeductionCalculate(PDO $pdo, array $payroll, string $periodStart, string $periodEnd): array
{
    require_once __DIR__ . '/lib_attendance_policy.php';
    require_once __DIR__ . '/lib_attendance_integrity.php';

    $employeeId = (int)($payroll['employee_id'] ?? 0);
    $basicSalary = round((float)($payroll['basic_salary'] ?? 0), 2);
    if ($employeeId <= 0 || $basicSalary <= 0) {
        return ['total' => 0.00, 'rows' => []];
    }

    $payrollPolicyId = (int)($payroll['payroll_policy_version_id'] ?? 0);
    if ($payrollPolicyId <= 0) {
        return ['total' => 0.00, 'rows' => []];
    }

    $payrollPolicy = dbFetchOne(
        "SELECT * FROM hr_payroll_policy_versions WHERE id = ? LIMIT 1",
        [$payrollPolicyId]
    );
    if (!$payrollPolicy) {
        throw new RuntimeException('سياسة الرواتب المرتبطة بالمسير غير موجودة.');
    }

    $dailyBase = round($basicSalary / 30, 4);
    $total = 0.00;
    $rows = [];

    $date = new DateTimeImmutable($periodStart);
    $end = new DateTimeImmutable($periodEnd);

    while ($date <= $end) {
        $day = $date->format('Y-m-d');
        $attendancePolicy = hrAttendancePolicyGetActive($pdo, $day);

        // No attendance policy means no attendance-derived payroll deduction.
        // This prevents the payroll engine from inventing an absence.
        if (!$attendancePolicy || !hrAttendancePolicyIsWorkingDay($attendancePolicy, $day)) {
            $date = $date->modify('+1 day');
            continue;
        }

        $eligibility = hrAttendanceEligibility($employeeId, $day);
        if (!$eligibility['eligible']) {
            // Approved leave and non-working employment states are not absence.
            // Unpaid approved leave is handled explicitly below.
            if (($eligibility['reason'] ?? '') !== 'approved_leave') {
                $date = $date->modify('+1 day');
                continue;
            }
        }

        $leave = hrAttendanceApprovedLeave($employeeId, $day);
        if ($leave) {
            $leaveType = (string)$leave['leave_type'];
            if ($leaveType === 'unpaid' && (int)$payrollPolicy['unpaid_leave_enabled'] === 1) {
                $amount = round($dailyBase * ((float)$payrollPolicy['unpaid_leave_deduction_percent'] / 100), (int)$payrollPolicy['rounding_decimals']);
                if ($amount > 0) {
                    $total = round($total + $amount, 2);
                    $rows[] = ['date' => $day, 'type' => 'unpaid_leave', 'amount' => $amount];
                }
            }
            $date = $date->modify('+1 day');
            continue;
        }

        $attendance = dbFetchOne(
            "SELECT status, check_in, check_out
             FROM attendance
             WHERE employee_id = ? AND date = ?
             LIMIT 1",
            [$employeeId, $day]
        );

        if ($attendance && (string)$attendance['status'] === 'absent' && (int)$payrollPolicy['absence_enabled'] === 1) {
            $amount = round($dailyBase * ((float)$payrollPolicy['absence_deduction_percent'] / 100), (int)$payrollPolicy['rounding_decimals']);
            if ($amount > 0) {
                $total = round($total + $amount, 2);
                $rows[] = ['date' => $day, 'type' => 'absence', 'amount' => $amount];
            }
        }

        $date = $date->modify('+1 day');
    }

    return ['total' => $total, 'rows' => $rows];
}

function hrPayrollRefreshAttendanceDeductionDraft(PDO $pdo, int $payrollId): void
{
    if ($payrollId <= 0) {
        throw new InvalidArgumentException('سجل الرواتب غير صالح.');
    }

    $payroll = dbFetchOne(
        "SELECT id, employee_id, month, year, basic_salary, payroll_policy_version_id,
                status, attendance_deduction
         FROM payroll
         WHERE id = ?
         LIMIT 1",
        [$payrollId]
    );
    if (!$payroll || (string)$payroll['status'] !== 'draft') {
        return;
    }

    $periodStart = sprintf('%04d-%02d-01', (int)$payroll['year'], (int)$payroll['month']);
    $periodEnd = date('Y-m-t', strtotime($periodStart));
    $result = hrPayrollAttendanceDeductionCalculate($pdo, $payroll, $periodStart, $periodEnd);

    dbExecute(
        "UPDATE payroll SET attendance_deduction = ? WHERE id = ? AND status = 'draft'",
        [round((float)$result['total'], 2), $payrollId]
    );
}
