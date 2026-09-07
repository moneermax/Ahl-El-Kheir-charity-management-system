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

/** Compatibility guard for legacy employee screens that still write salary directly. */
function hrEnsureEmployeeSalaryHistorySync(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $triggerAfterInsert = 'trg_employees_salary_history_sync_ai';
    $triggerAfterUpdate = 'trg_employees_salary_history_sync_au';

    if (!dbFetchOne("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ? LIMIT 1", [$triggerAfterInsert])) {
        db()->exec(<<<'SQL'
CREATE TRIGGER trg_employees_salary_history_sync_ai
AFTER INSERT ON employees
FOR EACH ROW
BEGIN
    IF NOT EXISTS (SELECT 1 FROM hr_employee_salary_history WHERE employee_id = NEW.id LIMIT 1) THEN
        INSERT INTO hr_employee_salary_history
            (employee_id, contract_id, effective_from, effective_to, basic_salary, salary_currency, pay_frequency, reason, notes)
        VALUES
            (NEW.id, NULL, COALESCE(NEW.hire_date, CURDATE()), NULL, COALESCE(NEW.basic_salary, 0.00), 'SDG', 'monthly', 'initial', 'Initial salary history created from employee record');
    END IF;
END
SQL
        );
    }

    if (!dbFetchOne("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ? LIMIT 1", [$triggerAfterUpdate])) {
        db()->exec(<<<'SQL'
CREATE TRIGGER trg_employees_salary_history_sync_au
AFTER UPDATE ON employees
FOR EACH ROW
BEGIN
    DECLARE v_future_from DATE DEFAULT NULL;
    DECLARE v_current_id BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_target_to DATE DEFAULT NULL;

    IF COALESCE(@hr_salary_history_sync, 0) <> 1
       AND NOT (OLD.basic_salary <=> NEW.basic_salary) THEN
        SELECT id INTO v_current_id
        FROM hr_employee_salary_history
        WHERE employee_id = NEW.id
          AND effective_from <= CURDATE()
          AND (effective_to IS NULL OR effective_to >= CURDATE())
        ORDER BY effective_from DESC, id DESC LIMIT 1;

        SELECT MIN(effective_from) INTO v_future_from
        FROM hr_employee_salary_history
        WHERE employee_id = NEW.id AND effective_from > CURDATE();

        IF v_current_id IS NOT NULL AND EXISTS (
            SELECT 1 FROM hr_employee_salary_history WHERE id = v_current_id AND effective_from = CURDATE()
        ) THEN
            UPDATE hr_employee_salary_history
            SET basic_salary = NEW.basic_salary, salary_currency = 'SDG', notes = 'Synchronized from employee record'
            WHERE id = v_current_id;
        ELSE
            IF v_current_id IS NOT NULL THEN
                UPDATE hr_employee_salary_history
                SET effective_to = DATE_SUB(CURDATE(), INTERVAL 1 DAY), salary_currency = 'SDG'
                WHERE id = v_current_id;
            END IF;
            SET v_target_to = CASE WHEN v_future_from IS NOT NULL THEN DATE_SUB(v_future_from, INTERVAL 1 DAY) ELSE NULL END;
            INSERT INTO hr_employee_salary_history
                (employee_id, contract_id, effective_from, effective_to, basic_salary, salary_currency, pay_frequency, reason, notes)
            VALUES
                (NEW.id, NULL, CURDATE(), v_target_to, COALESCE(NEW.basic_salary, 0.00), 'SDG', 'monthly', 'adjustment', 'Synchronized from employee record');
        END IF;
    END IF;
END
SQL
        );
    }
}

hrEnsureEmployeeSalaryHistorySync();
