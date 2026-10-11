<?php
declare(strict_types=1);

/**
 * Disposable MariaDB compatibility test for the Stage 7 rescheduling migration.
 *
 * This intentionally creates and drops only fixture tables. It refuses to run
 * unless the explicit test switch and exact dedicated test database are set.
 */
if (getenv('SALARY_ADVANCE_MIGRATION_TEST') !== '1') {
    fwrite(STDERR, "Refusing to run: set SALARY_ADVANCE_MIGRATION_TEST=1 for the disposable migration test.\n");
    exit(2);
}

$database = (string)getenv('DB_DATABASE');
if ($database !== 'salary_advance_migration_test') {
    fwrite(STDERR, "Refusing to run: DB_DATABASE must be exactly salary_advance_migration_test.\n");
    exit(2);
}

$host = (string)(getenv('DB_HOST') ?: '127.0.0.1');
$port = (string)(getenv('DB_PORT') ?: '3306');
$user = (string)(getenv('DB_USERNAME') ?: 'root');
$password = (string)(getenv('DB_PASSWORD') ?: '');

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo->exec('DROP TABLE IF EXISTS hr_salary_advance_repayment_plans');
$pdo->exec('DROP TABLE IF EXISTS hr_salary_advance_repayment_schedule');
$pdo->exec('DROP TABLE IF EXISTS hr_salary_advance_requests');
$pdo->exec('DROP TABLE IF EXISTS hr_salary_advance_policy_versions');

$pdo->exec("
    CREATE TABLE hr_salary_advance_policy_versions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        maximum_monthly_deduction DECIMAL(18,2) NULL,
        maximum_repayment_months INT UNSIGNED NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$pdo->exec("
    CREATE TABLE hr_salary_advance_requests (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        policy_version_id INT UNSIGNED NOT NULL,
        outstanding_balance DECIMAL(18,2) NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
$pdo->exec("
    CREATE TABLE hr_salary_advance_repayment_schedule (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        salary_advance_request_id INT UNSIGNED NOT NULL,
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
        UNIQUE KEY uq_hr_salary_advance_schedule_installment (salary_advance_request_id, installment_no),
        UNIQUE KEY uq_hr_salary_advance_schedule_month (salary_advance_request_id, scheduled_month),
        KEY idx_hr_salary_advance_schedule_request_status (salary_advance_request_id, status),
        KEY idx_hr_salary_advance_schedule_payroll (applied_payroll_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("INSERT INTO hr_salary_advance_policy_versions (id, maximum_monthly_deduction, maximum_repayment_months) VALUES (1, 200.00, 12)");
$pdo->exec("INSERT INTO hr_salary_advance_requests (id, policy_version_id, outstanding_balance) VALUES (1, 1, 500.00)");
$pdo->exec("
    INSERT INTO hr_salary_advance_repayment_schedule
        (salary_advance_request_id, installment_no, scheduled_month, scheduled_amount)
    VALUES
        (1, 1, '2026-01-01', 200.00),
        (1, 2, '2026-02-01', 200.00)
");

$migrationPath = dirname(__DIR__) . '/database/migrations/2026-10-11_hr_salary_advance_rescheduling.sql';
$migration = file_get_contents($migrationPath);
if ($migration === false) {
    throw new RuntimeException('Could not read the Stage 7 migration.');
}
$migration = preg_replace('/^\s*--.*$/m', '', $migration);
foreach (explode(';', $migration) as $statement) {
    $statement = trim($statement);
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}

$policy = $pdo->query("SELECT allow_fm_repayment_rescheduling FROM hr_salary_advance_policy_versions WHERE id = 1")->fetch();
expect((int)$policy['allow_fm_repayment_rescheduling'] === 0, 'Existing policy authorization must default to disabled.');

$plan = $pdo->query("SELECT * FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = 1 AND plan_no = 1")->fetch();
expect(is_array($plan), 'Legacy schedule must be backfilled into initial plan 1.');
expect((string)$plan['start_month'] === '2026-01-01', 'Backfilled plan must start at the earliest legacy scheduled month.');
expect((int)$plan['maximum_repayment_months'] === 12, 'Backfilled plan must preserve policy duration.');
expect((float)$plan['maximum_monthly_deduction'] === 200.0, 'Backfilled plan must preserve policy monthly cap.');

$schedule = $pdo->query("SELECT COUNT(*) AS total, SUM(repayment_plan_id IS NOT NULL) AS linked FROM hr_salary_advance_repayment_schedule WHERE salary_advance_request_id = 1")->fetch();
expect((int)$schedule['total'] === 2 && (int)$schedule['linked'] === 2, 'All legacy schedule rows must remain and link to the backfilled plan.');

$indexes = $pdo->query("
    SELECT DISTINCT INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hr_salary_advance_repayment_schedule'
")->fetchAll(PDO::FETCH_COLUMN);
expect(in_array('uq_hr_salary_advance_schedule_plan_installment', $indexes, true), 'Plan-scoped installment uniqueness index is missing.');
expect(in_array('uq_hr_salary_advance_schedule_plan_month', $indexes, true), 'Plan-scoped month uniqueness index is missing.');
expect(!in_array('uq_hr_salary_advance_schedule_installment', $indexes, true), 'Legacy installment uniqueness index should be replaced.');
expect(!in_array('uq_hr_salary_advance_schedule_month', $indexes, true), 'Legacy month uniqueness index should be replaced.');

// The migration is designed to recover from a repeat run after partial application.
foreach (explode(';', $migration) as $statement) {
    $statement = trim($statement);
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}
$count = (int)$pdo->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = 1 AND plan_no = 1")->fetchColumn();
expect($count === 1, 'Re-running the migration must not duplicate the initial repayment plan.');
$scheduleCount = (int)$pdo->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_schedule WHERE salary_advance_request_id = 1")->fetchColumn();
expect($scheduleCount === 2, 'Re-running the migration must not duplicate or remove legacy schedule rows.');

echo "PASS: Stage 7 migration, legacy backfill, policy default, plan-scoped indexes, and rerun safety.\n";
