<?php
declare(strict_types=1);

/**
 * HR employment-state helpers.
 *
 * Employment state is the canonical HR lifecycle value. The legacy
 * employees.status column is maintained only as a compatibility mirror for
 * existing modules until those modules are migrated.
 */

function hrGetEmploymentState(int $stateId): ?array
{
    if ($stateId <= 0) {
        return null;
    }

    return dbFetchOne(
        "SELECT id, code, name_ar, name_en, category
         FROM hr_employment_states
         WHERE id = ? AND is_active = 1
         LIMIT 1",
        [$stateId]
    );
}

function hrGetEmploymentStateByCode(string $code): ?array
{
    return dbFetchOne(
        "SELECT id, code, name_ar, name_en, category
         FROM hr_employment_states
         WHERE code = ? AND is_active = 1
         LIMIT 1",
        [$code]
    );
}

/**
 * Keep the legacy status column usable while the application is migrated.
 * It must never become the source of truth again.
 */
function hrLegacyStatusForState(array $state): string
{
    if (($state['code'] ?? '') === 'suspended') {
        return 'suspended';
    }

    if (($state['category'] ?? '') === 'separation') {
        return 'terminated';
    }

    return 'active';
}

/**
 * Change an employee's canonical employment state and append a history row.
 * The current open history record is closed before the new state is opened.
 * The legacy status mirror is updated in the same transaction.
 */
function hrSetEmploymentState(
    int $employeeId,
    int $stateId,
    ?int $changedBy = null,
    ?string $reason = null,
    ?string $effectiveAt = null
): void {
    if ($employeeId <= 0) {
        throw new InvalidArgumentException('الموظف غير صالح.');
    }

    $state = hrGetEmploymentState($stateId);
    if (!$state) {
        throw new InvalidArgumentException('حالة التوظيف غير صالحة.');
    }

    $pdo = db();
    $effectiveAt = $effectiveAt ?: date('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $employee = dbFetchOne(
            "SELECT id, employment_state_id
             FROM employees
             WHERE id = ?
             LIMIT 1
             FOR UPDATE",
            [$employeeId]
        );

        if (!$employee) {
            throw new RuntimeException('الموظف غير موجود.');
        }

        $currentStateId = (int)($employee['employment_state_id'] ?? 0);
        if ($currentStateId === (int)$state['id']) {
            $pdo->commit();
            return;
        }

        $pdo->prepare(
            "UPDATE hr_employee_state_history
             SET effective_to = ?
             WHERE employee_id = ?
               AND effective_to IS NULL"
        )->execute([$effectiveAt, $employeeId]);

        $pdo->prepare(
            "INSERT INTO hr_employee_state_history
                (employee_id, employment_state_id, effective_from, effective_to, reason, changed_by)
             VALUES (?, ?, ?, NULL, ?, ?)"
        )->execute([
            $employeeId,
            (int)$state['id'],
            $effectiveAt,
            $reason,
            $changedBy
        ]);

        $legacyStatus = hrLegacyStatusForState($state);
        $pdo->prepare(
            "UPDATE employees
             SET employment_state_id = ?,
                 employment_state_changed_at = ?,
                 status = ?
             WHERE id = ?"
        )->execute([
            (int)$state['id'],
            $effectiveAt,
            $legacyStatus,
            $employeeId
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Assign the initial employment state to a newly created employee.
 */
function hrInitializeEmploymentState(
    int $employeeId,
    string $stateCode = 'active',
    ?int $createdBy = null,
    ?string $effectiveAt = null,
    ?string $reason = 'Initial employee creation'
): void {
    $state = hrGetEmploymentStateByCode($stateCode);
    if (!$state) {
        throw new InvalidArgumentException('حالة التوظيف الابتدائية غير صالحة.');
    }

    hrSetEmploymentState($employeeId, (int)$state['id'], $createdBy, $reason, $effectiveAt);
}

function hrGetEmploymentStates(bool $activeOnly = true): array
{
    $sql = "SELECT id, code, name_ar, name_en, category
            FROM hr_employment_states";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order, id";

    return dbFetchAll($sql);
}
