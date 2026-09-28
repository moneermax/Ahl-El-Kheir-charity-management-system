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


function hrSalaryAdvanceFmCanReview(string $role): bool
{
    return in_array($role, ['financial_manager', 'fm', 'admin'], true);
}

function hrSalaryAdvanceGetRequestForFm(PDO $pdo, int $requestId): ?array
{
    if ($requestId <= 0) return null;
    return dbFetchOne(
        "SELECT r.*, p.version_no, p.policy_name, p.effective_from,
                p.allow_any_request_amount, p.minimum_request_amount, p.maximum_request_amount,
                p.allow_multiple_active_advances, p.allow_fixed_monthly_repayment,
                p.allow_full_eligible_salary_repayment, p.allow_full_settlement_from_salary,
                p.allow_direct_repayment, p.allow_custom_repayment_terms,
                p.maximum_monthly_deduction, p.maximum_repayment_months,
                p.repayment_start_rule, p.insufficient_salary_rule,
                p.eligible_salary_basis, p.minimum_service_days,
                p.probation_allowed, p.terminated_employee_allowed,
                p.require_accounting_verification, p.allow_early_settlement,
                e.full_name AS employee_name, e.employee_code, e.basic_salary,
                e.hire_date, e.employment_state_id,
                u.full_name AS submitted_by_name
         FROM hr_salary_advance_requests r
         JOIN hr_salary_advance_policy_versions p ON p.id = r.policy_version_id
         JOIN hr_employees e ON e.id = r.employee_id
         LEFT JOIN users u ON u.id = r.submitted_by
         WHERE r.id = ?
         LIMIT 1",
        [$requestId]
    );
}

function hrSalaryAdvanceFmPolicyMismatches(PDO $pdo, array $r): array
{
    $m = [];
    $amount = (float)$r['requested_amount'];
    if (!(int)$r['allow_any_request_amount']) {
        $min = $r['minimum_request_amount'] !== null ? (float)$r['minimum_request_amount'] : null;
        $max = $r['maximum_request_amount'] !== null ? (float)$r['maximum_request_amount'] : null;
        if ($min !== null && $amount < $min) $m[] = 'المبلغ المطلوب أقل من الحد الأدنى للسياسة.';
        if ($max !== null && $amount > $max) $m[] = 'المبلغ المطلوب يتجاوز الحد الأقصى للسياسة.';
    }

    $methodAllowed = [
        'fixed_monthly' => (int)$r['allow_fixed_monthly_repayment'],
        'full_eligible_salary' => (int)$r['allow_full_eligible_salary_repayment'],
        'full_settlement' => (int)$r['allow_full_settlement_from_salary'],
        'direct_repayment' => (int)$r['allow_direct_repayment'],
    ];
    if (empty($methodAllowed[$r['requested_repayment_method']])) {
        $m[] = 'طريقة السداد المطلوبة غير مسموحة في السياسة المرجعية.';
    }

    if ($r['requested_repayment_method'] === 'fixed_monthly') {
        $monthly = (float)($r['requested_monthly_amount'] ?? 0);
        if ($monthly <= 0) $m[] = 'القسط الشهري المطلوب غير محدد بشكل صحيح.';
        if ($r['maximum_monthly_deduction'] !== null && $monthly > (float)$r['maximum_monthly_deduction']) {
            $m[] = 'القسط الشهري المطلوب يتجاوز الحد الأقصى للخصم الشهري.';
        }
        if ($r['maximum_repayment_months'] !== null && $monthly > 0) {
            $months = (int)ceil($amount / $monthly);
            if ($months > (int)$r['maximum_repayment_months']) {
                $m[] = 'مدة السداد المطلوبة تتجاوز الحد الأقصى لعدد الأشهر.';
            }
        }
    }

    if ($r['requested_start_month'] !== null) {
        $start = (string)$r['requested_start_month'];
        $expected = date('Y-m-01', strtotime('+1 month'));
        if ($r['repayment_start_rule'] === 'next_payroll' && $start !== $expected) {
            $m[] = 'شهر بدء السداد لا يطابق قاعدة بدء السداد في السياسة.';
        }
    }

    $employee = [
        'employment_state_code' => null,
        'employment_state_category' => null,
        'hire_date' => $r['hire_date'] ?? null,
    ];
    $state = dbFetchOne(
        "SELECT code, category FROM hr_employment_states WHERE id = ? LIMIT 1",
        [(int)($r['employment_state_id'] ?? 0)]
    );
    if ($state) {
        $employee['employment_state_code'] = $state['code'];
        $employee['employment_state_category'] = $state['category'];
    }
    foreach (hrSalaryAdvanceValidateEmployeeEligibility($employee, $r) as $error) {
        $m[] = $error;
    }

    if (!(int)$r['allow_multiple_active_advances']) {
        $existing = dbFetchOne(
            "SELECT id FROM hr_salary_advance_requests
             WHERE employee_id = ?
               AND id <> ?
               AND status IN ('submitted','fm_review','approved')
             LIMIT 1",
            [(int)$r['employee_id'], (int)$r['id']]
        );
        if ($existing) $m[] = 'لدى الموظف طلب/سلفة نشطة أخرى وفق السياسة المرجعية.';
    }

    return array_values(array_unique($m));
}

function hrSalaryAdvanceFmValidateDecision(array $r, array $input): array
{
    $decision = (string)($input['decision'] ?? '');
    if (!in_array($decision, ['approve', 'reject'], true)) {
        throw new InvalidArgumentException('قرار المراجعة غير صالح.');
    }

    $reason = trim((string)($input['fm_rejection_reason'] ?? ''));
    if ($decision === 'reject') {
        if ($reason === '') throw new InvalidArgumentException('سبب الرفض مطلوب.');
        if (mb_strlen($reason, 'UTF-8') > 2000) throw new InvalidArgumentException('سبب الرفض طويل أكثر من الحد المسموح.');
        return ['decision'=>'reject','reason'=>$reason];
    }

    $customized = !empty($input['fm_customized']);
    $customReason = trim((string)($input['fm_customization_reason'] ?? ''));
    if ($customized && !(int)$r['allow_custom_repayment_terms']) {
        throw new InvalidArgumentException('السياسة المرجعية لا تسمح بتخصيص شروط السداد لهذا الطلب.');
    }
    if ($customized && $customReason === '') {
        throw new InvalidArgumentException('سبب تخصيص شروط هذا الطلب مطلوب.');
    }
    if (mb_strlen($customReason, 'UTF-8') > 2000) {
        throw new InvalidArgumentException('سبب التخصيص طويل أكثر من الحد المسموح.');
    }

    $amount = (float)($input['approved_amount'] ?? 0);
    $method = trim((string)($input['approved_repayment_method'] ?? ''));
    $monthly = ($input['approved_monthly_amount'] ?? '') !== '' ? (float)$input['approved_monthly_amount'] : null;
    $start = trim((string)($input['approved_start_month'] ?? ''));

    if (!$customized) {
        $amount = (float)$r['requested_amount'];
        $method = (string)$r['requested_repayment_method'];
        $monthly = $r['requested_monthly_amount'] !== null ? (float)$r['requested_monthly_amount'] : null;
        $start = (string)($r['requested_start_month'] ?? '');
    }

    if ($amount <= 0) throw new InvalidArgumentException('المبلغ المعتمد يجب أن يكون أكبر من صفر.');
    if (!in_array($method, ['fixed_monthly','full_eligible_salary','full_settlement','direct_repayment'], true)) {
        throw new InvalidArgumentException('طريقة السداد المعتمدة غير صالحة.');
    }
    if ($method === 'fixed_monthly') {
        if ($monthly === null || $monthly <= 0) throw new InvalidArgumentException('يجب تحديد القسط الشهري المعتمد.');
        if ($monthly > $amount) throw new InvalidArgumentException('القسط الشهري لا يمكن أن يتجاوز المبلغ المعتمد.');
    } else {
        $monthly = null;
    }
    if ($start !== '') {
        $d = DateTime::createFromFormat('!Y-m-d', $start);
        if (!$d || $d->format('Y-m-d') !== $start || $start < date('Y-m-01')) {
            throw new InvalidArgumentException('شهر بدء السداد المعتمد غير صالح.');
        }
    } else {
        $start = null;
    }

    if (!$customized) {
        $mismatches = hrSalaryAdvanceFmPolicyMismatches($pdo ?? db(), $r);
        if ($mismatches) {
            throw new InvalidArgumentException('لا يمكن اعتماد الطلب بصيغته الأصلية لوجود مخالفات للسياسة. فعّل التخصيص وعدّل الشروط، أو ارفض الطلب.');
        }
    }

    return [
        'decision'=>'approve',
        'approved_amount'=>$amount,
        'approved_repayment_method'=>$method,
        'approved_monthly_amount'=>$monthly,
        'approved_start_month'=>$start,
        'customized'=>$customized ? 1 : 0,
        'customization_reason'=>$customized ? $customReason : null,
    ];
}
