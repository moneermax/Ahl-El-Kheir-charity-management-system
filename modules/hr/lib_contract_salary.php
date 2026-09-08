<?php
declare(strict_types=1);

/** HR contract and salary helpers. */

function hrGetEmployeeContract(int $contractId): ?array
{
    if ($contractId <= 0) return null;
    return dbFetchOne("SELECT c.*, e.employee_code, e.full_name FROM hr_employee_contracts c JOIN employees e ON e.id = c.employee_id WHERE c.id = ? LIMIT 1", [$contractId]);
}

function hrGetActiveEmployeeContract(int $employeeId, ?string $onDate = null): ?array
{
    if ($employeeId <= 0) return null;
    $onDate = $onDate ?: date('Y-m-d');
    return dbFetchOne("SELECT c.* FROM hr_employee_contracts c WHERE c.employee_id = ? AND c.status = 'active' AND c.start_date <= ? AND (c.end_date IS NULL OR c.end_date >= ?) ORDER BY c.start_date DESC, c.id DESC LIMIT 1", [$employeeId, $onDate, $onDate]);
}

function hrGetEmployeeSalary(int $employeeId, ?string $onDate = null): ?array
{
    if ($employeeId <= 0) return null;
    $onDate = $onDate ?: date('Y-m-d');
    return dbFetchOne("SELECT s.* FROM hr_employee_salary_history s WHERE s.employee_id = ? AND s.effective_from <= ? AND (s.effective_to IS NULL OR s.effective_to >= ?) ORDER BY s.effective_from DESC, s.id DESC LIMIT 1", [$employeeId, $onDate, $onDate]);
}

function hrGetEmployeeSalaryHistory(int $employeeId): array
{
    return dbFetchAll("SELECT s.*, c.contract_number FROM hr_employee_salary_history s LEFT JOIN hr_employee_contracts c ON c.id = s.contract_id WHERE s.employee_id = ? ORDER BY s.effective_from DESC, s.id DESC", [$employeeId]);
}

function hrCreateEmployeeContract(int $employeeId, string $contractType, string $startDate, ?string $endDate, float $basicSalary, string $currency = APP_CURRENCY_CODE, string $payFrequency = 'monthly', ?string $contractNumber = null, ?string $probationEndDate = null, ?string $contractFilePath = null, ?string $notes = null, ?int $createdBy = null): int
{
    if ($employeeId <= 0) throw new InvalidArgumentException('الموظف غير صالح.');
    if ($basicSalary < 0) throw new InvalidArgumentException('الراتب لا يمكن أن يكون سالباً.');
    if ($endDate !== null && $endDate < $startDate) throw new InvalidArgumentException('تاريخ نهاية العقد يجب أن يكون بعد تاريخ بدايته.');
    $pdo = db(); $pdo->beginTransaction();
    try {
        if (!dbFetchOne("SELECT id FROM employees WHERE id = ? LIMIT 1 FOR UPDATE", [$employeeId])) throw new RuntimeException('الموظف غير موجود.');
        $overlap = dbFetchOne("SELECT id FROM hr_employee_contracts WHERE employee_id = ? AND status IN ('draft','active') AND start_date <= ? AND (end_date IS NULL OR end_date >= ?) LIMIT 1", [$employeeId, $endDate ?: '9999-12-31', $startDate]);
        if ($overlap) throw new RuntimeException('يوجد عقد آخر متداخل مع الفترة المحددة.');
        $status = $startDate <= date('Y-m-d') ? 'active' : 'draft';
        $currency = APP_CURRENCY_CODE;

        $stmt = $pdo->prepare("INSERT INTO hr_employee_contracts (employee_id, contract_number, contract_type, start_date, end_date, status, basic_salary, salary_currency, pay_frequency, probation_end_date, contract_file_path, notes, created_by) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$employeeId, $contractType, $startDate, $endDate, $status, $basicSalary, $currency, $payFrequency, $probationEndDate, $contractFilePath, $notes, $createdBy]);
        $contractId = (int)$pdo->lastInsertId();
        $contractNumber = 'CTR-' . date('Y', strtotime($startDate)) . '-' . str_pad((string)$contractId, 6, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE hr_employee_contracts SET contract_number = ? WHERE id = ?")->execute([$contractNumber, $contractId]);

        if ($status === 'active') $pdo->prepare("UPDATE hr_employee_contracts SET status = 'expired' WHERE employee_id = ? AND id <> ? AND status = 'active' AND (end_date IS NULL OR end_date >= ?)")->execute([$employeeId, $contractId, $startDate]);

        $existingAtDate = dbFetchOne(
            "SELECT id, effective_from, effective_to
             FROM hr_employee_salary_history
             WHERE employee_id = ?
               AND effective_from <= ?
               AND (effective_to IS NULL OR effective_to >= ?)
             ORDER BY effective_from DESC, id DESC
             LIMIT 1 FOR UPDATE",
            [$employeeId, $startDate, $startDate]
        );
        $future = dbFetchOne(
            "SELECT id, effective_from
             FROM hr_employee_salary_history
             WHERE employee_id = ? AND effective_from > ?
             ORDER BY effective_from ASC, id ASC
             LIMIT 1 FOR UPDATE",
            [$employeeId, $startDate]
        );
        $futureTo = $future ? date('Y-m-d', strtotime($future['effective_from'] . ' -1 day')) : null;

        if ($existingAtDate && $existingAtDate['effective_from'] === $startDate) {
            $pdo->prepare(
                "UPDATE hr_employee_salary_history
                 SET basic_salary = ?, salary_currency = ?, pay_frequency = ?, reason = 'contract_change', notes = ?, changed_by = ?, contract_id = ?, effective_to = ?
                 WHERE id = ?"
            )->execute([
                $basicSalary, $currency, $payFrequency, 'تم إنشاء سجل الراتب من عقد جديد', $createdBy,
                $contractId, $futureTo, (int)$existingAtDate['id']
            ]);
        } else {
            if ($existingAtDate) {
                $pdo->prepare("UPDATE hr_employee_salary_history SET effective_to = ? WHERE id = ?")
                    ->execute([date('Y-m-d', strtotime($startDate . ' -1 day')), (int)$existingAtDate['id']]);
            }
            $pdo->prepare(
                "INSERT INTO hr_employee_salary_history
                    (employee_id, contract_id, effective_from, effective_to, basic_salary, salary_currency, pay_frequency, reason, notes, changed_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'contract_change', ?, ?)"
            )->execute([
                $employeeId, $contractId, $startDate, $futureTo, $basicSalary,
                $currency, $payFrequency, 'تم إنشاء سجل الراتب من عقد جديد', $createdBy
            ]);
        }

        if ($startDate <= date('Y-m-d')) {
            $pdo->exec("SET @hr_salary_history_sync = 1");
            try {
                $pdo->prepare("UPDATE employees SET basic_salary = ? WHERE id = ?")->execute([$basicSalary, $employeeId]);
            } finally {
                $pdo->exec("SET @hr_salary_history_sync = 0");
            }
        }

        $pdo->commit();
        return $contractId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Create or update a salary-history segment effective on a specific date. */
function hrChangeEmployeeSalary(int $employeeId, float $basicSalary, string $effectiveFrom, string $reason = 'adjustment', ?int $changedBy = null, ?string $notes = null, ?int $contractId = null, string $currency = APP_CURRENCY_CODE, string $payFrequency = 'monthly'): void
{
    if ($employeeId <= 0) throw new InvalidArgumentException('الموظف غير صالح.');
    if ($basicSalary < 0) throw new InvalidArgumentException('الراتب لا يمكن أن يكون سالباً.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveFrom) || !strtotime($effectiveFrom)) throw new InvalidArgumentException('تاريخ سريان الراتب غير صالح.');
    $allowedReasons = ['initial','annual_increase','promotion','adjustment','contract_change','correction','other'];
    if (!in_array($reason, $allowedReasons, true)) throw new InvalidArgumentException('سبب تغيير الراتب غير صالح.');
    $pdo = db(); $pdo->beginTransaction();
    try {
        if (!dbFetchOne("SELECT id FROM employees WHERE id = ? LIMIT 1 FOR UPDATE", [$employeeId])) throw new RuntimeException('الموظف غير موجود.');
        $currency = APP_CURRENCY_CODE;
        $existingAtDate = dbFetchOne("SELECT id, effective_from, effective_to FROM hr_employee_salary_history WHERE employee_id = ? AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?) ORDER BY effective_from DESC, id DESC LIMIT 1 FOR UPDATE", [$employeeId, $effectiveFrom, $effectiveFrom]);
        $future = dbFetchOne("SELECT id, effective_from FROM hr_employee_salary_history WHERE employee_id = ? AND effective_from > ? ORDER BY effective_from ASC, id ASC LIMIT 1 FOR UPDATE", [$employeeId, $effectiveFrom]);
        $futureTo = $future ? date('Y-m-d', strtotime($future['effective_from'] . ' -1 day')) : null;
        if ($existingAtDate && $existingAtDate['effective_from'] === $effectiveFrom) {
            $pdo->prepare("UPDATE hr_employee_salary_history SET basic_salary = ?, salary_currency = ?, pay_frequency = ?, reason = ?, notes = ?, changed_by = ?, contract_id = ?, effective_to = ? WHERE id = ?")->execute([$basicSalary, $currency, $payFrequency, $reason, $notes, $changedBy, $contractId, $futureTo, (int)$existingAtDate['id']]);
        } else {
            if ($existingAtDate) $pdo->prepare("UPDATE hr_employee_salary_history SET effective_to = ? WHERE id = ?")->execute([date('Y-m-d', strtotime($effectiveFrom . ' -1 day')), (int)$existingAtDate['id']]);
            $pdo->prepare("INSERT INTO hr_employee_salary_history (employee_id, contract_id, effective_from, effective_to, basic_salary, salary_currency, pay_frequency, reason, notes, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([$employeeId, $contractId, $effectiveFrom, $futureTo, $basicSalary, $currency, $payFrequency, $reason, $notes, $changedBy]);
        }
        if ($effectiveFrom <= date('Y-m-d')) {
            $pdo->exec("SET @hr_salary_history_sync = 1");
            try { $pdo->prepare("UPDATE employees SET basic_salary = ? WHERE id = ?")->execute([$basicSalary, $employeeId]); }
            finally { $pdo->exec("SET @hr_salary_history_sync = 0"); }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
