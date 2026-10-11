<?php
declare(strict_types=1);

/**
 * Disposable-database tests for the FM salary-advance rescheduling service.
 * This file must only run against the dedicated CI test database.
 */
if (getenv('SALARY_ADVANCE_RESCHEDULE_SERVICE_TEST') !== '1'
    || (string)getenv('DB_DATABASE') !== 'salary_advance_reschedule_service_test') {
    fwrite(STDERR, "Refusing to run outside the dedicated disposable service-test database.\n");
    exit(2);
}

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: '127.0.0.1',
        getenv('DB_PORT') ?: '3306',
        getenv('DB_DATABASE')
    ),
    (string)(getenv('DB_USERNAME') ?: 'root'),
    (string)(getenv('DB_PASSWORD') ?: ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

function expectRescheduleRejected(callable $operation, string $case, string $expectedMessage): void
{
    try {
        $operation();
    } catch (Throwable $e) {
        if (strpos($e->getMessage(), $expectedMessage) === false) {
            throw new RuntimeException(
                'Wrong rejection for ' . $case . ': ' . $e->getMessage(),
                0,
                $e
            );
        }
        return;
    }
    throw new RuntimeException('Expected rescheduling rejection: ' . $case);
}

foreach ([
    'hr_salary_advance_repayment_schedule',
    'hr_salary_advance_repayment_plans',
    'hr_salary_advance_requests',
    'hr_salary_advance_policy_versions',
    'employees',
    'audit_log',
] as $table) {
    $pdo->exec('DROP TABLE IF EXISTS ' . $table);
}

$pdo->exec("
    CREATE TABLE hr_salary_advance_policy_versions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        allow_fm_repayment_rescheduling TINYINT(1) NOT NULL DEFAULT 0,
        maximum_repayment_months INT UNSIGNED NULL,
        maximum_monthly_deduction DECIMAL(18,2) NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$pdo->exec("
    CREATE TABLE employees (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$pdo->exec("
    CREATE TABLE hr_salary_advance_requests (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        request_no VARCHAR(40) NOT NULL,
        employee_id INT UNSIGNED NOT NULL,
        policy_version_id INT UNSIGNED NOT NULL,
        status VARCHAR(30) NOT NULL,
        outstanding_balance DECIMAL(18,2) NOT NULL,
        approved_repayment_method VARCHAR(40) NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$pdo->exec("
    CREATE TABLE hr_salary_advance_repayment_plans (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        salary_advance_request_id INT UNSIGNED NOT NULL,
        plan_no INT UNSIGNED NOT NULL,
        status ENUM('active','superseded') NOT NULL DEFAULT 'active',
        reason VARCHAR(1000) NULL,
        outstanding_balance_at_creation DECIMAL(18,2) NOT NULL,
        start_month DATE NOT NULL,
        maximum_repayment_months INT UNSIGNED NULL,
        maximum_monthly_deduction DECIMAL(18,2) NOT NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        activated_at DATETIME NULL,
        superseded_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_hr_salary_advance_plan_no (salary_advance_request_id, plan_no),
        KEY idx_hr_salary_advance_plan_active (salary_advance_request_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$pdo->exec("
    CREATE TABLE hr_salary_advance_repayment_schedule (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        salary_advance_request_id INT UNSIGNED NOT NULL,
        repayment_plan_id INT UNSIGNED NULL,
        installment_no INT UNSIGNED NOT NULL,
        scheduled_month DATE NOT NULL,
        scheduled_amount DECIMAL(18,2) NOT NULL,
        applied_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        status ENUM('pending','partial','paid','skipped') NOT NULL DEFAULT 'pending',
        applied_payroll_id INT UNSIGNED NULL,
        applied_at DATETIME NULL,
        skip_reason VARCHAR(1000) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_hr_salary_advance_schedule_plan_installment (salary_advance_request_id, repayment_plan_id, installment_no),
        UNIQUE KEY uq_hr_salary_advance_schedule_plan_month (salary_advance_request_id, repayment_plan_id, scheduled_month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$pdo->exec("
    CREATE TABLE audit_log (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NULL,
        action VARCHAR(150) NOT NULL,
        entity_type VARCHAR(150) NOT NULL,
        entity_id INT UNSIGNED NULL,
        old_values LONGTEXT NULL,
        new_values LONGTEXT NULL,
        ip_address VARCHAR(100) NULL,
        user_agent VARCHAR(1000) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$policy = $pdo->prepare("INSERT INTO hr_salary_advance_policy_versions (id, allow_fm_repayment_rescheduling, maximum_repayment_months, maximum_monthly_deduction) VALUES (?, ?, 12, 200.00)");
$policy->execute([1, 1]);
$policy->execute([2, 0]);
$pdo->exec("INSERT INTO employees (id, user_id) VALUES (1, 9001)");

$requests = [
    [1, 'ADV-RESCHED-1', 1, 'fixed_monthly', 500.00],
    [2, 'ADV-RESCHED-2', 2, 'fixed_monthly', 500.00],
    [3, 'ADV-RESCHED-3', 1, 'fixed_monthly', 500.00],
    [4, 'ADV-RESCHED-4', 1, 'fixed_monthly', 500.00],
    [5, 'ADV-RESCHED-5', 1, 'fixed_monthly', 500.00],
    [6, 'ADV-RESCHED-6', 1, 'direct_repayment', 500.00],
    [7, 'ADV-RESCHED-7', 1, 'fixed_monthly', 500.00],
    [8, 'ADV-RESCHED-8', 1, 'fixed_monthly', 500.00],
];
$insertRequest = $pdo->prepare("
    INSERT INTO hr_salary_advance_requests
        (id, request_no, employee_id, policy_version_id, status, outstanding_balance, approved_repayment_method)
    VALUES (?, ?, 1, ?, 'disbursed', ?, ?)
");
foreach ($requests as [$id, $requestNo, $policyId, $method, $balance]) {
    $insertRequest->execute([$id, $requestNo, $policyId, $balance, $method]);
}

$oldStart = '2020-01-01';
$activeStart = date('Y-m-01');
$insertPlan = $pdo->prepare("
    INSERT INTO hr_salary_advance_repayment_plans
        (id, salary_advance_request_id, plan_no, status, reason,
         outstanding_balance_at_creation, start_month, maximum_repayment_months, maximum_monthly_deduction)
    VALUES (?, ?, 1, 'active', 'Fixture original plan', 500.00, ?, ?, 200.00)
");
for ($id = 1; $id <= 8; $id++) {
    $insertPlan->execute([$id, $id, $id === 3 ? $activeStart : $oldStart, 12]);
}
$insertSchedule = $pdo->prepare("
    INSERT INTO hr_salary_advance_repayment_schedule
        (salary_advance_request_id, repayment_plan_id, installment_no, scheduled_month, scheduled_amount)
    VALUES (?, ?, 1, ?, 200.00)
");
for ($id = 1; $id <= 8; $id++) {
    $insertSchedule->execute([$id, $id, $id === 3 ? $activeStart : $oldStart]);
}

require_once dirname(__DIR__) . '/modules/hr/lib_salary_advance_repayment.php';

$startMonth = date('Y-m-01', strtotime(date('Y-m-01') . ' +1 month'));
$success = hrSalaryAdvanceScheduleReschedule($pdo, 1, $startMonth, 3, 200.00, 'Approved policy exception test', 77);
check((int)$success['plan_no'] === 2, 'successful reschedule creates the next plan number');
check((int)$success['schedule_rows'] === 3, 'new schedule covers the outstanding balance');
check((int)$success['employee_user_id'] === 9001, 'service resolves the employee notification recipient');
check((int)$pdo->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = 1")->fetchColumn() === 2, 'old plan is preserved beside the replacement');
check((string)$pdo->query("SELECT status FROM hr_salary_advance_repayment_plans WHERE id = 1")->fetchColumn() === 'superseded', 'old plan is superseded');
check((string)$pdo->query("SELECT status FROM hr_salary_advance_repayment_plans WHERE id = " . (int)$success['plan_id'])->fetchColumn() === 'active', 'new plan is active');
check((int)$pdo->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_schedule WHERE salary_advance_request_id = 1")->fetchColumn() === 4, 'old schedule row and three new rows are all preserved');
check((int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'HR_SALARY_ADVANCE_REPAYMENT_RESCHEDULE'")->fetchColumn() === 1, 'successful reschedule writes one audit event');

expectRescheduleRejected(fn() => hrSalaryAdvanceScheduleReschedule($pdo, 2, $startMonth, 3, 200.00, 'Policy disabled test', 77), 'saved policy authorization disabled');
expectRescheduleRejected(fn() => hrSalaryAdvanceScheduleReschedule($pdo, 3, $startMonth, 3, 200.00, 'In-horizon test', 77), 'current plan has not expired');
expectRescheduleRejected(fn() => hrSalaryAdvanceScheduleReschedule($pdo, 4, $startMonth, 3, 201.00, 'Monthly cap test', 77), 'monthly cap exceeded');
expectRescheduleRejected(fn() => hrSalaryAdvanceScheduleReschedule($pdo, 5, $startMonth, 2, 200.00, 'Duration coverage test', 77), 'proposed terms do not cover balance');
expectRescheduleRejected(fn() => hrSalaryAdvanceScheduleReschedule($pdo, 6, $startMonth, 3, 200.00, 'Direct repayment test', 77), 'direct repayment cannot convert to payroll deductions');
expectRescheduleRejected(
    fn() => hrSalaryAdvanceScheduleReschedule($pdo, 2, $startMonth, 3, 200.00, 'Policy disabled test', 77),
    'saved policy authorization disabled',
    'غير مصرح بها'
);
expectRescheduleRejected(
    fn() => hrSalaryAdvanceScheduleReschedule($pdo, 3, $startMonth, 3, 200.00, 'In-horizon test', 77),
    'current plan has not expired',
    'لم تنتهِ مدة خطة السداد الحالية'
);
expectRescheduleRejected(
    fn() => hrSalaryAdvanceScheduleReschedule($pdo, 4, $startMonth, 3, 201.00, 'Monthly cap test', 77),
    'monthly cap exceeded',
    'يتجاوز الحد الأقصى للخصم الشهري'
);
expectRescheduleRejected(
    fn() => hrSalaryAdvanceScheduleReschedule($pdo, 4, $startMonth, 3, 0.001, 'Rounded zero installment test', 77),
    'installment rounds down to zero',
    'ألا يقل عن 0.01'
);
expectRescheduleRejected(
    fn() => hrSalaryAdvanceScheduleReschedule($pdo, 5, $startMonth, 2, 200.00, 'Duration coverage test', 77),
    'proposed terms do not cover balance',
    'لا يغطي الرصيد القائم'
);
expectRescheduleRejected(
    fn() => hrSalaryAdvanceScheduleReschedule($pdo, 6, $startMonth, 3, 200.00, 'Direct repayment test', 77),
    'direct repayment cannot convert to payroll deductions',
    'لا يمكن تحويل السداد المباشر إلى خصومات رواتب'
);

// A corrupted request with two active plans must not silently supersede only one.
$pdo->exec("
    INSERT INTO hr_salary_advance_repayment_plans
        (id, salary_advance_request_id, plan_no, status, reason,
         outstanding_balance_at_creation, start_month, maximum_repayment_months, maximum_monthly_deduction)
    VALUES (9, 8, 2, 'active', 'Fixture duplicate active plan', 500.00, '{$oldStart}', 12, 200.00)
");
expectRescheduleRejected(
    fn() => hrSalaryAdvanceScheduleReschedule($pdo, 8, $startMonth, 3, 200.00, 'Duplicate active plan test', 77),
    'multiple active plans are inconsistent',
    'أكثر من خطة سداد نشطة'
);
check((int)$pdo->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = 8")->fetchColumn() === 2, 'ambiguous active plans remain unchanged for manual review');
check((int)$pdo->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = 8 AND status = 'active'")->fetchColumn() === 2, 'service refuses to choose between multiple active plans');

foreach ([2, 3, 4, 5, 6, 8] as $id) {
    check((int)$pdo->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = {$id}")->fetchColumn() === 1, "rejected request {$id} retains only its original plan");
    check((string)$pdo->query("SELECT status FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = {$id}")->fetchColumn() === 'active', "rejected request {$id} keeps its original plan active");
}
check((int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'HR_SALARY_ADVANCE_REPAYMENT_RESCHEDULE'")->fetchColumn() === 1, 'rejected attempts do not add audit success events');

// Force an audit-write failure and prove the plan/schedule changes roll back.
$pdo->exec("ALTER TABLE audit_log DROP COLUMN user_agent");
$beforeSchedules = (int)$pdo->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_schedule WHERE salary_advance_request_id = 7")->fetchColumn();
try {
    hrSalaryAdvanceScheduleReschedule($pdo, 7, $startMonth, 3, 200.00, 'Audit rollback test', 77);
    throw new RuntimeException('Expected audit insert failure did not occur.');
} catch (PDOException $e) {
    // Expected: the service should roll back its own transaction.
}
check((int)$pdo->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = 7")->fetchColumn() === 1, 'audit failure rolls back the replacement plan');
check((string)$pdo->query("SELECT status FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = 7")->fetchColumn() === 'active', 'audit failure leaves the original plan active');
check((int)$pdo->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_schedule WHERE salary_advance_request_id = 7")->fetchColumn() === $beforeSchedules, 'audit failure rolls back inserted schedule rows');

echo "PASS: successful reschedule, specific rejection reasons, multiple-active-plan guard, history preservation, audit event, and transaction rollback.\n";
