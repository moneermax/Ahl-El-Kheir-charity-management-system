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

function hrSalaryAdvancePolicyGetRequestReference(PDO $pdo): ?array
{
    $active = hrSalaryAdvancePolicyGetActive($pdo);
    if ($active) return $active;

    return dbFetchOne(
        "SELECT * FROM hr_salary_advance_policy_versions
         WHERE effective_from > ?
         ORDER BY effective_from ASC, version_no ASC
         LIMIT 1",
        [date('Y-m-d')]
    );
}

function hrSalaryAdvanceValidateRequest(array $input, array $policy = []): array
{
    $amount = (float)($input['requested_amount'] ?? 0);
    $method = trim((string)($input['requested_repayment_method'] ?? ''));
    $monthly = ($input['requested_monthly_amount'] ?? '') !== '' ? (float)$input['requested_monthly_amount'] : null;
    $startMonth = trim((string)($input['requested_start_month'] ?? ''));
    $reason = trim((string)($input['request_reason'] ?? '')) ?: null;

    if ($amount <= 0) throw new InvalidArgumentException('مبلغ السلفة المطلوب يجب أن يكون أكبر من صفر.');
    $allowedMethods = ['fixed_monthly', 'full_eligible_salary', 'full_settlement', 'direct_repayment'];
    if (!in_array($method, $allowedMethods, true)) {
        throw new InvalidArgumentException('طريقة السداد المختارة غير صالحة.');
    }

    if ($method === 'fixed_monthly') {
        if ($monthly === null || $monthly <= 0) throw new InvalidArgumentException('يجب تحديد قيمة القسط الشهري.');
        if ((float)$monthly > $amount) throw new InvalidArgumentException('القسط الشهري لا يمكن أن يتجاوز مبلغ السلفة.');
    } else {
        $monthly = null;
    }

    if ($startMonth !== '') {
        $d = DateTime::createFromFormat('!Y-m-d', $startMonth);
        if (!$d || $d->format('Y-m-d') !== $startMonth || $startMonth < date('Y-m-01')) {
            throw new InvalidArgumentException('شهر بدء السداد غير صالح.');
        }
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
