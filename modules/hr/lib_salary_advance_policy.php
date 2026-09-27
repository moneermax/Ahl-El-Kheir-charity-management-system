<?php
declare(strict_types=1);

function hrSalaryAdvancePolicyValidate(array $input): array
{
    $p = [
        'policy_name' => trim((string)($input['policy_name'] ?? '')),
        'effective_from' => trim((string)($input['effective_from'] ?? '')),
        'notes' => trim((string)($input['notes'] ?? '')) ?: null,
        'allow_any_request_amount' => isset($input['allow_any_request_amount']) ? 1 : 0,
        'minimum_request_amount' => ($input['minimum_request_amount'] ?? '') !== '' ? (float)$input['minimum_request_amount'] : null,
        'maximum_request_amount' => ($input['maximum_request_amount'] ?? '') !== '' ? (float)$input['maximum_request_amount'] : null,
        'allow_multiple_active_advances' => isset($input['allow_multiple_active_advances']) ? 1 : 0,
        'allow_fixed_monthly_repayment' => isset($input['allow_fixed_monthly_repayment']) ? 1 : 0,
        'allow_full_eligible_salary_repayment' => isset($input['allow_full_eligible_salary_repayment']) ? 1 : 0,
        'allow_full_settlement_from_salary' => isset($input['allow_full_settlement_from_salary']) ? 1 : 0,
        'allow_direct_repayment' => isset($input['allow_direct_repayment']) ? 1 : 0,
        'allow_custom_repayment_terms' => isset($input['allow_custom_repayment_terms']) ? 1 : 0,
        'maximum_monthly_deduction' => ($input['maximum_monthly_deduction'] ?? '') !== '' ? (float)$input['maximum_monthly_deduction'] : null,
        'maximum_repayment_months' => ($input['maximum_repayment_months'] ?? '') !== '' ? (int)$input['maximum_repayment_months'] : null,
        'repayment_start_rule' => (string)($input['repayment_start_rule'] ?? 'next_payroll'),
        'insufficient_salary_rule' => (string)($input['insufficient_salary_rule'] ?? 'available_salary'),
        'eligible_salary_basis' => (string)($input['eligible_salary_basis'] ?? 'net_before_advance'),
        'minimum_service_days' => max(0, (int)($input['minimum_service_days'] ?? 0)),
        'probation_allowed' => isset($input['probation_allowed']) ? 1 : 0,
        'terminated_employee_allowed' => isset($input['terminated_employee_allowed']) ? 1 : 0,
        'require_accounting_verification' => isset($input['require_accounting_verification']) ? 1 : 0,
        'allow_early_settlement' => isset($input['allow_early_settlement']) ? 1 : 0,
    ];
    if ($p['policy_name'] === '') throw new InvalidArgumentException('اسم سياسة السلفة مطلوب.');
    $d = DateTime::createFromFormat('!Y-m-d', $p['effective_from']);
    if (!$d || $d->format('Y-m-d') !== $p['effective_from'] || $p['effective_from'] <= date('Y-m-d')) throw new InvalidArgumentException('تاريخ السريان يجب أن يكون تاريخاً مستقبلياً صالحاً.');
    if ($p['minimum_request_amount'] !== null && $p['minimum_request_amount'] < 0) throw new InvalidArgumentException('الحد الأدنى لا يمكن أن يكون سالباً.');
    if ($p['maximum_request_amount'] !== null && $p['maximum_request_amount'] < 0) throw new InvalidArgumentException('الحد الأقصى لا يمكن أن يكون سالباً.');
    if ($p['minimum_request_amount'] !== null && $p['maximum_request_amount'] !== null && $p['maximum_request_amount'] < $p['minimum_request_amount']) throw new InvalidArgumentException('الحد الأقصى يجب ألا يقل عن الحد الأدنى.');
    if ($p['allow_any_request_amount']) { $p['minimum_request_amount'] = null; $p['maximum_request_amount'] = null; }
    if ($p['maximum_monthly_deduction'] !== null && $p['maximum_monthly_deduction'] < 0) throw new InvalidArgumentException('الحد الأقصى للخصم الشهري لا يمكن أن يكون سالباً.');
    if ($p['maximum_repayment_months'] !== null && $p['maximum_repayment_months'] < 1) throw new InvalidArgumentException('الحد الأقصى لعدد الأقساط يجب أن يكون أكبر من صفر.');
    if (!in_array($p['repayment_start_rule'], ['next_payroll','specified_month'], true)) throw new InvalidArgumentException('قاعدة بدء السداد غير صالحة.');
    if (!in_array($p['insufficient_salary_rule'], ['available_salary','skip_month'], true)) throw new InvalidArgumentException('قاعدة عدم كفاية الراتب غير صالحة.');
    if (!in_array($p['eligible_salary_basis'], ['net_before_advance','gross'], true)) throw new InvalidArgumentException('أساس الراتب المؤهل غير صالح.');
    return $p;
}

function hrSalaryAdvancePolicyGetActive(PDO $pdo, ?string $asOfDate = null): ?array
{
    $date = $asOfDate ?: date('Y-m-d');
    return dbFetchOne("SELECT * FROM hr_salary_advance_policy_versions WHERE effective_from <= ? ORDER BY effective_from DESC, version_no DESC LIMIT 1", [$date]);
}

function hrSalaryAdvancePolicyGetAll(PDO $pdo): array
{
    return dbFetchAll("SELECT p.*, u.full_name AS created_by_name FROM hr_salary_advance_policy_versions p LEFT JOIN users u ON u.id=p.created_by ORDER BY p.effective_from ASC, p.version_no ASC");
}
