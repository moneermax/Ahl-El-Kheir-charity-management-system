<?php
declare(strict_types=1);

/**
 * HR employment-state helpers.
 * Employment state is the canonical HR lifecycle value. employees.status is
 * retained only as a compatibility mirror for legacy modules.
 */
function hrGetEmploymentState(int $stateId): ?array
{
    if ($stateId <= 0) return null;
    return dbFetchOne("SELECT id, code, name_ar, name_en, category FROM hr_employment_states WHERE id = ? AND is_active = 1 LIMIT 1", [$stateId]);
}

function hrGetEmploymentStateByCode(string $code): ?array
{
    return dbFetchOne("SELECT id, code, name_ar, name_en, category FROM hr_employment_states WHERE code = ? AND is_active = 1 LIMIT 1", [$code]);
}

function hrLegacyStatusForState(array $state): string
{
    if (($state['code'] ?? '') === 'suspended') return 'suspended';
    if (($state['category'] ?? '') === 'separation') return 'terminated';
    return 'active';
}

function hrSetEmploymentState(int $employeeId, int $stateId, ?int $changedBy = null, ?string $reason = null, ?string $effectiveAt = null): void
{
    if ($employeeId <= 0) throw new InvalidArgumentException('الموظف غير صالح.');
    $state = hrGetEmploymentState($stateId);
    if (!$state) throw new InvalidArgumentException('حالة التوظيف غير صالحة.');

    $pdo = db();
    $effectiveAt = $effectiveAt ?: date('Y-m-d H:i:s');
    $pdo->beginTransaction();
    try {
        $employee = dbFetchOne("SELECT id, employment_state_id FROM employees WHERE id = ? LIMIT 1 FOR UPDATE", [$employeeId]);
        if (!$employee) throw new RuntimeException('الموظف غير موجود.');
        if ((int)($employee['employment_state_id'] ?? 0) === (int)$state['id']) {
            $pdo->commit();
            return;
        }
        $pdo->prepare("UPDATE hr_employee_state_history SET effective_to = ? WHERE employee_id = ? AND effective_to IS NULL")
            ->execute([$effectiveAt, $employeeId]);
        $pdo->prepare("INSERT INTO hr_employee_state_history (employee_id, employment_state_id, effective_from, effective_to, reason, changed_by) VALUES (?, ?, ?, NULL, ?, ?)")
            ->execute([$employeeId, (int)$state['id'], $effectiveAt, $reason, $changedBy]);
        $pdo->prepare("UPDATE employees SET employment_state_id = ?, employment_state_changed_at = ?, status = ? WHERE id = ?")
            ->execute([(int)$state['id'], $effectiveAt, hrLegacyStatusForState($state), $employeeId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function hrInitializeEmploymentState(int $employeeId, string $stateCode = 'active', ?int $createdBy = null, ?string $effectiveAt = null, ?string $reason = 'Initial employee creation'): void
{
    $state = hrGetEmploymentStateByCode($stateCode);
    if (!$state) throw new InvalidArgumentException('حالة التوظيف الابتدائية غير صالحة.');
    hrSetEmploymentState($employeeId, (int)$state['id'], $createdBy, $reason, $effectiveAt);
}

function hrGetEmploymentStates(bool $activeOnly = true): array
{
    $sql = "SELECT id, code, name_ar, name_en, category FROM hr_employment_states";
    if ($activeOnly) $sql .= " WHERE is_active = 1";
    $sql .= " ORDER BY sort_order, id";
    return dbFetchAll($sql);
}

/**
 * Explicit application-level replacement for the former salary-history triggers.
 * Call this immediately after an employee is created or after employees.basic_salary changes.
 */
function hrSyncEmployeeSalaryHistory(int $employeeId, ?float $basicSalary = null, ?string $effectiveDate = null, string $reason = 'adjustment'): void
{
    if ($employeeId <= 0) throw new InvalidArgumentException('الموظف غير صالح.');

    $employee = dbFetchOne(
        "SELECT id, hire_date, basic_salary FROM employees WHERE id = ? LIMIT 1",
        [$employeeId]
    );
    if (!$employee) throw new RuntimeException('الموظف غير موجود.');

    $salary = $basicSalary !== null ? $basicSalary : (float)($employee['basic_salary'] ?? 0);
    $effective = $effectiveDate ?: date('Y-m-d');
    $effectiveObj = DateTime::createFromFormat('Y-m-d', $effective);
    if (!$effectiveObj || $effectiveObj->format('Y-m-d') !== $effective) {
        throw new InvalidArgumentException('تاريخ سريان الراتب غير صالح.');
    }

    $existing = dbFetchOne(
        "SELECT id
         FROM hr_employee_salary_history
         WHERE employee_id = ? AND effective_from = ?
         ORDER BY id DESC LIMIT 1",
        [$employeeId, $effective]
    );

    if ($existing) {
        db()->prepare(
            "UPDATE hr_employee_salary_history
             SET basic_salary = ?, salary_currency = 'SDG', notes = 'Synchronized from employee record'
             WHERE id = ?"
        )->execute([$salary, (int)$existing['id']]);
        return;
    }

    $current = dbFetchOne(
        "SELECT id
         FROM hr_employee_salary_history
         WHERE employee_id = ?
           AND effective_from <= ?
           AND (effective_to IS NULL OR effective_to >= ?)
         ORDER BY effective_from DESC, id DESC LIMIT 1",
        [$employeeId, $effective, $effective]
    );

    $future = dbFetchOne(
        "SELECT effective_from
         FROM hr_employee_salary_history
         WHERE employee_id = ? AND effective_from > ?
         ORDER BY effective_from ASC, id ASC LIMIT 1",
        [$employeeId, $effective]
    );

    if ($current) {
        db()->prepare(
            "UPDATE hr_employee_salary_history
             SET effective_to = DATE_SUB(?, INTERVAL 1 DAY), salary_currency = 'SDG'
             WHERE id = ?"
        )->execute([$effective, (int)$current['id']]);
    }

    $targetTo = $future ? (new DateTime($future['effective_from']))->modify('-1 day')->format('Y-m-d') : null;
    db()->prepare(
        "INSERT INTO hr_employee_salary_history
            (employee_id, contract_id, effective_from, effective_to, basic_salary, salary_currency, pay_frequency, reason, notes)
         VALUES (?, NULL, ?, ?, ?, 'SDG', 'monthly', ?, 'Synchronized from employee record')"
    )->execute([$employeeId, $effective, $targetTo, $salary, $reason]);
}
