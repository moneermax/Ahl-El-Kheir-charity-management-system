<?php
declare(strict_types=1);

/**
 * HR contract and salary helpers.
 *
 * Contract and salary history are the canonical source for future payroll.
 * employees.basic_salary remains a compatibility mirror during migration.
 */

function hrGetEmployeeContract(int $contractId): ?array
{
    if ($contractId <= 0) {
        return null;
    }

    return dbFetchOne(
        "SELECT c.*, e.employee_code, e.full_name
         FROM hr_employee_contracts c
         JOIN employees e ON e.id = c.employee_id
         WHERE c.id = ?
         LIMIT 1",
        [$contractId]
    );
}

function hrGetActiveEmployeeContract(int $employeeId, ?string $onDate = null): ?array
{
    if ($employeeId <= 0) {
        return null;
    }

    $onDate = $onDate ?: date('Y-m-d');

    return dbFetchOne(
        "SELECT c.*
         FROM hr_employee_contracts c
         WHERE c.employee_id = ?
           AND c.status = 'active'
           AND c.start_date <= ?
           AND (c.end_date IS NULL OR c.end_date >= ?)
         ORDER BY c.start_date DESC, c.id DESC
         LIMIT 1",
        [$employeeId, $onDate, $onDate]
    );
}

function hrGetEmployeeSalary(int $employeeId, ?string $onDate = null): ?array
{
    if ($employeeId <= 0) {
        return null;
    }

    $onDate = $onDate ?: date('Y-m-d');

    return dbFetchOne(
        "SELECT s.*
         FROM hr_employee_salary_history s
         WHERE s.employee_id = ?
           AND s.effective_from <= ?
           AND (s.effective_to IS NULL OR s.effective_to >= ?)
         ORDER BY s.effective_from DESC, s.id DESC
         LIMIT 1",
        [$employeeId, $onDate, $onDate]
    );
}

function hrGetEmployeeSalaryHistory(int $employeeId): array
{
    return dbFetchAll(
        "SELECT s.*, c.contract_number
         FROM hr_employee_salary_history s
         LEFT JOIN hr_employee_contracts c ON c.id = s.contract_id
         WHERE s.employee_id = ?
         ORDER BY s.effective_from DESC, s.id DESC",
        [$employeeId]
    );
}

function hrCreateEmployeeContract(
    int $employeeId,
    string $contractType,
    string $startDate,
    ?string $endDate,
    float $basicSalary,
    string $currency = 'EGP',
    string $payFrequency = 'monthly',
    ?string $contractNumber = null,
    ?string $probationEndDate = null,
    ?string $contractFilePath = null,
    ?string $notes = null,
    ?int $createdBy = null
): int {
    if ($employeeId <= 0) {
        throw new InvalidArgumentException('الموظف غير صالح.');
    }
    if ($basicSalary < 0) {
        throw new InvalidArgumentException('الراتب لا يمكن أن يكون سالباً.');
    }
    if ($endDate !== null && $endDate < $startDate) {
        throw new InvalidArgumentException('تاريخ نهاية العقد يجب أن يكون بعد تاريخ بدايته.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $employee = dbFetchOne(
            "SELECT id FROM employees WHERE id = ? LIMIT 1 FOR UPDATE",
            [$employeeId]
        );
        if (!$employee) {
            throw new RuntimeException('الموظف غير موجود.');
        }

        $overlap = dbFetchOne(
            "SELECT id FROM hr_employee_contracts
             WHERE employee_id = ?
               AND status IN ('draft','active')
               AND start_date <= ?
               AND (end_date IS NULL OR end_date >= ?)
             LIMIT 1",
            [$employeeId, $endDate ?: '9999-12-31', $startDate]
        );
        if ($overlap) {
            throw new RuntimeException('يوجد عقد آخر متداخل مع الفترة المحددة.');
        }

        $status = $startDate <= date('Y-m-d') ? 'active' : 'draft';
        $stmt = $pdo->prepare(
            "INSERT INTO hr_employee_contracts
                (employee_id, contract_number, contract_type, start_date, end_date, status,
                 basic_salary, salary_currency, pay_frequency, probation_end_date,
                 contract_file_path, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $employeeId, $contractNumber ?: null, $contractType, $startDate, $endDate,
            $status, $basicSalary, strtoupper($currency), $payFrequency, $probationEndDate,
            $contractFilePath, $notes, $createdBy
        ]);

        $contractId = (int)$pdo->lastInsertId();

        if ($status === 'active') {
            $pdo->prepare(
                "UPDATE hr_employee_contracts
                 SET status = 'expired'
                 WHERE employee_id = ?
                   AND id <> ?
                   AND status = 'active'
                   AND (end_date IS NULL OR end_date >= ?)"
            )->execute([$employeeId, $contractId, $startDate]);
        }

        $pdo->commit();
        return $contractId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function hrChangeEmployeeSalary(
    int $employeeId,
    float $basicSalary,
    string $effectiveFrom,
    string $reason = 'adjustment',
    ?int $changedBy = null,
    ?string $notes = null,
    ?int $contractId = null,
    string $currency = 'EGP',
    string $payFrequency = 'monthly'
): void {
    if ($employeeId <= 0) {
        throw new InvalidArgumentException('الموظف غير صالح.');
    }
    if ($basicSalary < 0) {
        throw new InvalidArgumentException('الراتب لا يمكن أن يكون سالباً.');
    }

    $allowedReasons = ['initial','annual_increase','promotion','adjustment','contract_change','correction','other'];
    if (!in_array($reason, $allowedReasons, true)) {
        throw new InvalidArgumentException('سبب تغيير الراتب غير صالح.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $employee = dbFetchOne(
            "SELECT id FROM employees WHERE id = ? LIMIT 1 FOR UPDATE",
            [$employeeId]
        );
        if (!$employee) {
            throw new RuntimeException('الموظف غير موجود.');
        }

        $current = dbFetchOne(
            "SELECT id, effective_from FROM hr_employee_salary_history
             WHERE employee_id = ?
               AND effective_to IS NULL
             ORDER BY effective_from DESC, id DESC
             LIMIT 1",
            [$employeeId]
        );

        if ($current && $effectiveFrom < $current['effective_from']) {
            throw new RuntimeException('تاريخ سريان الراتب الجديد لا يمكن أن يسبق آخر سجل راتب.');
        }

        if ($current && $effectiveFrom === $current['effective_from']) {
            $pdo->prepare(
                "UPDATE hr_employee_salary_history
                 SET basic_salary = ?, salary_currency = ?, pay_frequency = ?, reason = ?, notes = ?, changed_by = ?, contract_id = ?
                 WHERE id = ?"
            )->execute([
                $basicSalary, strtoupper($currency), $payFrequency, $reason, $notes, $changedBy,
                $contractId, (int)$current['id']
            ]);
        } else {
            if ($current) {
                $previousTo = date('Y-m-d', strtotime($effectiveFrom . ' -1 day'));
                $pdo->prepare(
                    "UPDATE hr_employee_salary_history SET effective_to = ? WHERE id = ?"
                )->execute([$previousTo, (int)$current['id']]);
            }

            $pdo->prepare(
                "INSERT INTO hr_employee_salary_history
                    (employee_id, contract_id, effective_from, effective_to, basic_salary,
                     salary_currency, pay_frequency, reason, notes, changed_by)
                 VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $employeeId, $contractId, $effectiveFrom, $basicSalary,
                strtoupper($currency), $payFrequency, $reason, $notes, $changedBy
            ]);
        }

        // Compatibility mirror only. Payroll will read salary history instead.
        if ($effectiveFrom <= date('Y-m-d')) {
            $pdo->prepare("UPDATE employees SET basic_salary = ? WHERE id = ?")
                ->execute([$basicSalary, $employeeId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
