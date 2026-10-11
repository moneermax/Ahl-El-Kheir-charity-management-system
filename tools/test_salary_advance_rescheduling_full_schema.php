<?php
declare(strict_types=1);

/**
 * Runs the Stage 7 migration against the repository's full checked-in schema
 * and legacy data, using only a dedicated disposable MariaDB database.
 */
if (getenv('SALARY_ADVANCE_MIGRATION_TEST') !== '1'
    || (string)getenv('DB_DATABASE') !== 'salary_advance_migration_test') {
    fwrite(STDERR, "Refusing to run outside the dedicated disposable migration-test database.\n");
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

function expectFullSchema(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

$schema = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
expectFullSchema($schema === 'salary_advance_migration_test', 'Connected database is not the dedicated disposable test database.');

$beforeRows = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_repayment_schedule')->fetchColumn();
$scheduledRequests = (int)$pdo->query('SELECT COUNT(DISTINCT salary_advance_request_id) FROM hr_salary_advance_repayment_schedule')->fetchColumn();
$orphanSchedules = (int)$pdo->query("
    SELECT COUNT(*)
    FROM hr_salary_advance_repayment_schedule s
    LEFT JOIN hr_salary_advance_requests r ON r.id = s.salary_advance_request_id
    LEFT JOIN hr_salary_advance_policy_versions p ON p.id = r.policy_version_id
    WHERE r.id IS NULL OR p.id IS NULL
")->fetchColumn();
expectFullSchema($beforeRows > 0, 'The checked-in schema fixture has no legacy repayment schedule rows.');
expectFullSchema($orphanSchedules === 0, 'Legacy schedule rows reference a missing request or policy; migration backfill cannot be validated safely.');

$migrationPath = dirname(__DIR__) . '/database/migrations/2026-10-11_hr_salary_advance_rescheduling.sql';
$migration = file_get_contents($migrationPath);
if ($migration === false) {
    throw new RuntimeException('Could not read the Stage 7 migration.');
}
$migration = preg_replace('/^\s*--.*$/m', '', $migration);

$runMigration = static function () use ($pdo, $migration): void {
    foreach (explode(';', $migration) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
};
$runMigration();

$afterRows = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_repayment_schedule')->fetchColumn();
$linkedRows = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_repayment_schedule WHERE repayment_plan_id IS NOT NULL')->fetchColumn();
$planCount = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_repayment_plans WHERE plan_no = 1')->fetchColumn();
$policyCount = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_policy_versions')->fetchColumn();
$enabledExistingPolicies = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_policy_versions WHERE allow_fm_repayment_rescheduling <> 0')->fetchColumn();

expectFullSchema($afterRows === $beforeRows, 'Migration changed the legacy schedule row count.');
expectFullSchema($linkedRows === $beforeRows, 'Not all legacy schedule rows were linked to an initial repayment plan.');
expectFullSchema($planCount === $scheduledRequests, 'Initial plan backfill count does not match requests with schedules.');
expectFullSchema($enabledExistingPolicies === 0, 'Existing policy versions must not be silently authorized for rescheduling.');

$mismatchedPlans = (int)$pdo->query("
    SELECT COUNT(*)
    FROM hr_salary_advance_repayment_plans rp
    JOIN hr_salary_advance_requests r ON r.id = rp.salary_advance_request_id
    JOIN hr_salary_advance_policy_versions p ON p.id = r.policy_version_id
    WHERE rp.plan_no = 1
      AND (
          rp.start_month <> (
              SELECT MIN(s.scheduled_month)
              FROM hr_salary_advance_repayment_schedule s
              WHERE s.salary_advance_request_id = r.id
          )
          OR NOT (rp.maximum_repayment_months <=> p.maximum_repayment_months)
          OR rp.maximum_monthly_deduction <> COALESCE(p.maximum_monthly_deduction, 0.01)
      )
")->fetchColumn();
expectFullSchema((int)$mismatchedPlans === 0, 'Backfilled plans do not preserve the original schedule start and policy duration/cap.');

$indexes = $pdo->query("
    SELECT DISTINCT INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hr_salary_advance_repayment_schedule'
")->fetchAll(PDO::FETCH_COLUMN);
expectFullSchema(in_array('uq_hr_salary_advance_schedule_plan_installment', $indexes, true), 'Plan-scoped installment uniqueness index is missing.');
expectFullSchema(in_array('uq_hr_salary_advance_schedule_plan_month', $indexes, true), 'Plan-scoped month uniqueness index is missing.');
expectFullSchema(!in_array('uq_hr_salary_advance_schedule_installment', $indexes, true), 'Legacy installment uniqueness index should be replaced.');
expectFullSchema(!in_array('uq_hr_salary_advance_schedule_month', $indexes, true), 'Legacy month uniqueness index should be replaced.');

$runMigration();
$afterRerunRows = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_repayment_schedule')->fetchColumn();
$afterRerunPlans = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_repayment_plans WHERE plan_no = 1')->fetchColumn();
$afterRerunLinked = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_repayment_schedule WHERE repayment_plan_id IS NOT NULL')->fetchColumn();
expectFullSchema($afterRerunRows === $beforeRows, 'Re-running the migration changed the legacy schedule row count.');
expectFullSchema($afterRerunPlans === $scheduledRequests, 'Re-running the migration duplicated or removed initial plans.');
expectFullSchema($afterRerunLinked === $beforeRows, 'Re-running the migration lost repayment-plan links.');

echo "PASS: full checked-in schema migration, legacy data backfill, disabled-by-default policy flag, index replacement, and rerun safety.\n";
