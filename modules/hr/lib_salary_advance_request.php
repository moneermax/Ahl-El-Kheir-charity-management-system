<?php
declare(strict_types=1);
require_once __DIR__ . '/lib_employee_identity.php';

function hrSalaryAdvanceGetEmployeeForUser(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $employee = hrGetEmployeeForUser($pdo, $userId);
    if (!$employee) {
        return null;
    }

    $employee['employment_state_code'] = null;
    $employee['employment_state_name'] = null;
    $employee['employment_state_category'] = null;

    $state = dbFetchOne(
        "SELECT code, name_ar, category
         FROM hr_employment_states
         WHERE id = ?
         LIMIT 1",
        [(int)($employee['employment_state_id'] ?? 0)]
    );

    if ($state) {
        $employee['employment_state_code'] = $state['code'];
        $employee['employment_state_name'] = $state['name_ar'];
        $employee['employment_state_category'] = $state['category'];
    }

    return $employee;
}

function hrSalaryAdvanceGetRequestPolicy(PDO $pdo, int $policyId): ?array
{
    if ($policyId <= 0) return null;
    return dbFetchOne(
        "SELECT * FROM hr_salary_advance_policy_versions WHERE id = ? LIMIT 1",
        [$policyId]
    );
}

function hrSalaryAdvanceValidateEmployeeEligibility(array $employee, array $policy): array
{
    $errors = [];
    $state = (string)($employee['employment_state_code'] ?? '');
    $category = (string)($employee['employment_state_category'] ?? '');

    if ($category === 'separation' && !(int)$policy['terminated_employee_allowed']) {
        $errors[] = 'حالة الموظف الحالية لا تسمح بطلب سلفة وفق السياسة السارية.';
    }
    if ($state === 'probation' && !(int)$policy['probation_allowed']) {
        $errors[] = 'الموظف خلال فترة التجربة غير مؤهل وفق السياسة السارية.';
    }
    $hireDate = (string)($employee['hire_date'] ?? '');
    if ((int)$policy['minimum_service_days'] > 0 && $hireDate !== '') {
        $hire = DateTime::createFromFormat('!Y-m-d', $hireDate);
        if ($hire) {
            $serviceDays = (int)$hire->diff(new DateTime('today'))->format('%r%a');
            if ($serviceDays < (int)$policy['minimum_service_days']) {
                $errors[] = 'لم يستوف الموظف الحد الأدنى لمدة الخدمة المطلوبة.';
            }
        }
    }
    return $errors;
}

function hrSalaryAdvanceValidateRequest(array $input, array $policy): array
{
    $amount = (float)($input['requested_amount'] ?? 0);
    $method = trim((string)($input['requested_repayment_method'] ?? ''));
    $monthly = ($input['requested_monthly_amount'] ?? '') !== '' ? (float)$input['requested_monthly_amount'] : null;
    $startMonth = trim((string)($input['requested_start_month'] ?? ''));
    $reason = trim((string)($input['request_reason'] ?? '')) ?: null;

    if ($amount <= 0) throw new InvalidArgumentException('مبلغ السلفة المطلوب يجب أن يكون أكبر من صفر.');
    if (!(int)$policy['allow_any_request_amount']) {
        $min = $policy['minimum_request_amount'] !== null ? (float)$policy['minimum_request_amount'] : null;
        $max = $policy['maximum_request_amount'] !== null ? (float)$policy['maximum_request_amount'] : null;
        if ($min !== null && $amount < $min) throw new InvalidArgumentException('مبلغ السلفة أقل من الحد الأدنى المسموح به في السياسة.');
        if ($max !== null && $amount > $max) throw new InvalidArgumentException('مبلغ السلفة يتجاوز الحد الأقصى المسموح به في السياسة.');
    }

    $allowedMethods = [
        'fixed_monthly' => 'allow_fixed_monthly_repayment',
        'full_eligible_salary' => 'allow_full_eligible_salary_repayment',
        'full_settlement' => 'allow_full_settlement_from_salary',
        'direct_repayment' => 'allow_direct_repayment',
    ];
    if (!isset($allowedMethods[$method]) || !(int)$policy[$allowedMethods[$method]]) {
        throw new InvalidArgumentException('طريقة السداد المختارة غير مسموحة في السياسة السارية.');
    }

    if ($method === 'fixed_monthly') {
        if ($monthly === null || $monthly <= 0) throw new InvalidArgumentException('يجب تحديد قيمة القسط الشهري.');
        if ((float)$monthly > $amount) throw new InvalidArgumentException('القسط الشهري لا يمكن أن يتجاوز مبلغ السلفة.');
        if ($policy['maximum_monthly_deduction'] !== null && $monthly > (float)$policy['maximum_monthly_deduction']) {
            throw new InvalidArgumentException('القسط الشهري يتجاوز الحد الأقصى للخصم الشهري في السياسة.');
        }
        if ($policy['maximum_repayment_months'] !== null) {
            $months = (int)ceil($amount / $monthly);
            if ($months > (int)$policy['maximum_repayment_months']) {
                throw new InvalidArgumentException('القسط المقترح يؤدي إلى مدة سداد تتجاوز الحد الأقصى في السياسة.');
            }
        }
    } else {
        $monthly = null;
    }

    if ($startMonth !== '') {
        $d = DateTime::createFromFormat('!Y-m-d', $startMonth);
        if (!$d || $d->format('Y-m-d') !== $startMonth || $startMonth < date('Y-m-01')) {
            throw new InvalidArgumentException('شهر بدء السداد غير صالح.');
        }
        if (($policy['repayment_start_rule'] ?? 'next_payroll') === 'next_payroll' && $startMonth !== date('Y-m-01', strtotime('+1 month'))) {
            throw new InvalidArgumentException('السياسة تحدد بدء السداد مع المسير التالي.');
        }
    } elseif (($policy['repayment_start_rule'] ?? 'next_payroll') === 'specified_month') {
        throw new InvalidArgumentException('يجب تحديد شهر بدء السداد.');
    }

    if (mb_strlen($reason ?? '', 'UTF-8') > 2000) {
        throw new InvalidArgumentException('سبب الطلب طويل أكثر من الحد المسموح.');
    }

    return [
        'requested_amount' => $amount,
        'requested_repayment_method' => $method,
        'requested_monthly_amount' => $monthly,
        'requested_start_month' => $startMonth !== '' ? $startMonth : null,
        'request_reason' => $reason,
    ];
}

function hrSalaryAdvanceNextRequestNo(PDO $pdo): string
{
    $row = dbFetchOne("SELECT id FROM hr_salary_advance_requests ORDER BY id DESC LIMIT 1");
    $next = ((int)($row['id'] ?? 0)) + 1;
    return 'SAR-' . date('Y') . '-' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
}

function hrSalaryAdvanceGetEmployeeRequests(PDO $pdo, int $employeeId): array
{
    return dbFetchAll(
        "SELECT r.*, p.version_no, p.policy_name, p.effective_from
         FROM hr_salary_advance_requests r
         JOIN hr_salary_advance_policy_versions p ON p.id = r.policy_version_id
         WHERE r.employee_id = ?
         ORDER BY r.id DESC",
        [$employeeId]
    );
}
