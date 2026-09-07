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
    if ($policy['overtime_enabled'] && $policy['overtime_multiplier'] <= 0) {
        throw new InvalidArgumentException('معامل العمل الإضافي يجب أن يكون أكبر من صفر عند تفعيل العمل الإضافي.');
    }
    if ($policy['overtime_multiplier'] <= 0) {
        throw new InvalidArgumentException('معامل العمل الإضافي يجب أن يكون أكبر من صفر.');
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
