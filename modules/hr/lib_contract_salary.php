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

        // Currency is system-wide for HR contracts/salaries.
        $currency = APP_CURRENCY_CODE;

        // Contract numbers are system-generated after the database assigns the contract ID.
        $stmt = $pdo->prepare("INSERT INTO hr_employee_contracts (employee_id, contract_number, contract_type, start_date, end_date, status, basic_salary, salary_currency, pay_frequency, probation_end_date, contract_file_path, notes, created_by) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$employeeId, $contractType, $startDate, $endDate, $status, $basicSalary, $currency, $payFrequency, $probationEndDate, $contractFilePath, $notes, $createdBy]);
        $contractId = (int)$pdo->lastInsertId();
        $contractNumber = 'CTR-' . date('Y', strtotime($startDate)) . '-' . str_pad((string)$contractId, 6, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE hr_employee_contracts SET contract_number = ? WHERE id = ?")->execute([$contractNumber, $contractId]);

        if ($status === 'active') $pdo->prepare("UPDATE hr_employee_contracts SET status = 'expired' WHERE employee_id = ? AND id <> ? AND status = 'active' AND (end_date IS NULL OR end_date >= ?)")->execute([$employeeId, $contractId, $startDate]);

        // Every contract defines a salary effective from its start date, including future contracts.
        // Keep employees.basic_salary unchanged until a future-dated salary actually becomes effective.
        $existingAtDate = dbFetchOne(
            "SELECT id, effective_from, effective_to
             FROM hr_employee_salary_history
             WHERE employee_id = ?
               AND effective_from <= ?
               AND (effective_to IS NULL OR effective_to >= ?)
             ORDER BY effective_from DESC, id DESC
             LIMIT 1
             FOR UPDATE",
            [$employeeId, $startDate, $startDate]
        );
        $future = dbFetchOne(
            "SELECT id, effective_from
             FROM hr_employee_salary_history
             WHERE employee_id = ? AND effective_from > ?
             ORDER BY effective_from ASC, id ASC
             LIMIT 1
             FOR UPDATE",
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
                $pdo->prepare(
                    "UPDATE hr_employee_salary_history
                     SET effective_to = ?
                     WHERE id = ?"
                )->execute([date('Y-m-d', strtotime($startDate . ' -1 day')), (int)$existingAtDate['id']]);
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

        $pdo->commit(); return $contractId;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
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
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

function hrEnsurePayrollAccountingIntegration(): void
{
    static $done = false; if ($done) return; $done = true;
    require_once dirname(__DIR__) . '/accounting/lib.php'; ak_ensure_tables(); ak_seed_accounts();
    dbExecute("ALTER TABLE payroll ADD COLUMN IF NOT EXISTS accounting_status ENUM('none','ready','posted') NOT NULL DEFAULT 'none'");
    dbExecute("ALTER TABLE payroll ADD COLUMN IF NOT EXISTS accounting_entry_id INT UNSIGNED NULL");
    dbExecute("ALTER TABLE payroll ADD COLUMN IF NOT EXISTS payment_account_id INT UNSIGNED NULL");
    if (dbFetchOne("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'trg_payroll_accounting_before_update' LIMIT 1")) return;
    $sql = <<<'SQL'
CREATE TRIGGER trg_payroll_accounting_before_update
BEFORE UPDATE ON payroll
FOR EACH ROW
BEGIN
    DECLARE v_entry_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_expense_account_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_payment_account_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_employee_name VARCHAR(150) DEFAULT NULL;
    DECLARE v_entry_date DATE;
    DECLARE v_amount DECIMAL(14,2);
    DECLARE v_entry_code VARCHAR(50);
    IF OLD.status <> 'approved' AND NEW.status = 'approved' THEN SET NEW.accounting_status = 'ready'; END IF;
    IF OLD.status <> 'paid' AND NEW.status = 'paid' THEN
        SET v_amount = COALESCE(NEW.net_salary, 0);
        IF v_amount <= 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'لا يمكن ترحيل مسير راتب بصافي راتب غير صالح إلى المحاسبة.'; END IF;
        SELECT id INTO v_expense_account_id FROM accounts WHERE code = '5200' LIMIT 1;
        IF v_expense_account_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'حساب الرواتب 5200 غير موجود في دليل الحسابات.'; END IF;
        SET v_payment_account_id = NEW.payment_account_id;
        IF v_payment_account_id IS NULL OR v_payment_account_id = 0 THEN SELECT id INTO v_payment_account_id FROM accounts WHERE code = '1200' AND is_active = 1 LIMIT 1;
        ELSE SELECT id INTO v_payment_account_id FROM accounts WHERE id = v_payment_account_id AND is_active = 1 LIMIT 1; END IF;
        IF v_payment_account_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'حساب الدفع البنكي غير صالح.'; END IF;
        SET v_entry_code = CONCAT('PAY-', NEW.id); SET v_entry_date = COALESCE(NEW.payment_date, CURDATE());
        SELECT id INTO v_entry_id FROM journal_entries WHERE reference_type = 'payroll' AND reference_id = NEW.id AND status = 'posted' LIMIT 1;
        IF v_entry_id IS NULL THEN
            SELECT full_name INTO v_employee_name FROM employees WHERE id = NEW.employee_id LIMIT 1;
            INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by) VALUES (v_entry_code, v_entry_date, CONCAT('صرف راتب الموظف: ', COALESCE(v_employee_name, CONCAT('ID ', NEW.employee_id)), ' - ', NEW.year, '-', LPAD(NEW.month, 2, '0')), 'payroll', NEW.id, 'posted', NULL);
            SET v_entry_id = LAST_INSERT_ID();
            INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (v_entry_id, v_expense_account_id, v_amount, 0, CONCAT('رواتب وأجور - ', NEW.year, '-', LPAD(NEW.month, 2, '0')));
            INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (v_entry_id, v_payment_account_id, 0, v_amount, CONCAT('صرف رواتب - ', NEW.year, '-', LPAD(NEW.month, 2, '0')));
        END IF;
        SET NEW.accounting_entry_id = v_entry_id; SET NEW.accounting_status = 'posted';
    END IF;
END
SQL;
    db()->exec($sql);
}

function hrEnsurePayrollImmutabilityTrigger(): void
{
    static $done = false; if ($done) return; $done = true;
    if (dbFetchOne("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'trg_payroll_immutable_before_update' LIMIT 1")) return;
    $sql = <<<'SQL'
CREATE TRIGGER trg_payroll_immutable_before_update
BEFORE UPDATE ON payroll
FOR EACH ROW
BEGIN
    IF OLD.status = 'paid' AND (
        NOT (OLD.employee_id <=> NEW.employee_id) OR NOT (OLD.month <=> NEW.month) OR NOT (OLD.year <=> NEW.year) OR
        NOT (OLD.basic_salary <=> NEW.basic_salary) OR NOT (OLD.allowances <=> NEW.allowances) OR NOT (OLD.overtime <=> NEW.overtime) OR
        NOT (OLD.deductions <=> NEW.deductions) OR NOT (OLD.net_salary <=> NEW.net_salary) OR NOT (OLD.status <=> NEW.status) OR
        NOT (OLD.payment_date <=> NEW.payment_date)
    ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'لا يمكن تعديل مسير راتب بعد صرفه. استخدم إجراء تصحيح/عكس محاسبي مستقل.'; END IF;
END
SQL;
    db()->exec($sql);
}