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


/*
 * Exercise the exact policy-version INSERT used by the FM policy editor.
 * This catches column/placeholder/parameter drift for the rescheduling flag.
 * The fixture is always rolled back and never persists in the disposable DB.
 */
require_once dirname(__DIR__) . '/modules/hr/lib_salary_advance_policy.php';
$actorId = (int)$pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
expectFullSchema($actorId > 0, 'A valid actor is required to test policy creation.');

$futureDate = new DateTimeImmutable('2099-01-01');
$effectiveFrom = '';
for ($i = 0; $i < 366; $i++) {
    $candidate = $futureDate->modify('+' . $i . ' days')->format('Y-m-d');
    $checkDate = $pdo->prepare('SELECT id FROM hr_salary_advance_policy_versions WHERE effective_from = ? LIMIT 1');
    $checkDate->execute([$candidate]);
    if (!$checkDate->fetchColumn()) {
        $effectiveFrom = $candidate;
        break;
    }
}
expectFullSchema($effectiveFrom !== '', 'Could not find an unused future effective date for the rollback-only policy test.');

$policyInput = hrSalaryAdvancePolicyValidate([
    'policy_name' => 'CI rescheduling flag insert regression',
    'effective_from' => $effectiveFrom,
    'allow_any_request_amount' => '1',
    'allow_multiple_active_advances' => '1',
    'allow_fixed_monthly_repayment' => '1',
    'allow_full_eligible_salary_repayment' => '1',
    'allow_full_settlement_from_salary' => '1',
    'allow_direct_repayment' => '1',
    'allow_custom_repayment_terms' => '1',
    'maximum_monthly_deduction' => '200.00',
    'maximum_repayment_months' => '12',
    'allow_fm_repayment_rescheduling' => '1',
    'repayment_start_rule' => 'specified_month',
    'insufficient_salary_rule' => 'skip_month',
    'eligible_salary_basis' => 'gross',
    'minimum_service_days' => '0',
    'probation_allowed' => '1',
    'terminated_employee_allowed' => '0',
    'require_accounting_verification' => '1',
    'allow_early_settlement' => '1',
]);
$nextVersion = (int)$pdo->query('SELECT COALESCE(MAX(version_no), 0) + 1 FROM hr_salary_advance_policy_versions')->fetchColumn();
$pdo->beginTransaction();
try {
    hrSalaryAdvancePolicyInsertVersion($pdo, $nextVersion, $policyInput, $actorId);
    $insertedPolicy = $pdo->prepare('SELECT allow_fm_repayment_rescheduling, repayment_start_rule, insufficient_salary_rule, eligible_salary_basis FROM hr_salary_advance_policy_versions WHERE version_no = ? LIMIT 1');
    $insertedPolicy->execute([$nextVersion]);
    $inserted = $insertedPolicy->fetch(PDO::FETCH_ASSOC);
    expectFullSchema(is_array($inserted), 'Policy create helper did not insert the test policy version.');
    expectFullSchema((int)$inserted['allow_fm_repayment_rescheduling'] === 1, 'Policy create helper did not persist the enabled rescheduling authorization.');
    expectFullSchema((string)$inserted['repayment_start_rule'] === 'specified_month', 'Policy create helper shifted the repayment-start value into the wrong column.');
    expectFullSchema((string)$inserted['insufficient_salary_rule'] === 'skip_month', 'Policy create helper shifted the insufficient-salary value into the wrong column.');
    expectFullSchema((string)$inserted['eligible_salary_basis'] === 'gross', 'Policy create helper shifted the eligible-salary value into the wrong column.');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

require_once dirname(__DIR__) . '/modules/hr/lib_salary_advance_request.php';

$recommended = hrSalaryAdvanceRecommendMonthlyInstallment(70000.00, 6, 15000.00);
expectFullSchema($recommended['monthly_amount'] === 11666.67, 'Automatic installment recommendation must round up to the cent needed to fit six months.');
expectFullSchema($recommended['within_policy_cap'] === true, 'A recommendation below the policy monthly cap must be eligible.');
$capBlocked = hrSalaryAdvanceRecommendMonthlyInstallment(70000.00, 6, 10000.00);
expectFullSchema($capBlocked['monthly_amount'] === 11666.67, 'The system should still calculate the required installment when the cap makes it infeasible.');
expectFullSchema($capBlocked['within_policy_cap'] === false, 'A recommendation above the saved monthly cap must be blocked, not silently approved.');
$edgeRounded = hrSalaryAdvanceRecommendMonthlyInstallment(1000.00, 3, 400.00);
expectFullSchema($edgeRounded['monthly_amount'] === 333.34 && $edgeRounded['within_policy_cap'] === true, 'Automatic installment calculation must round upward to currency precision.');
$invalidRecommendation = hrSalaryAdvanceRecommendMonthlyInstallment(70000.00, null, 15000.00);
expectFullSchema($invalidRecommendation['monthly_amount'] === null && $invalidRecommendation['within_policy_cap'] === false, 'No automatic installment should be proposed without a finite policy horizon.');

echo "PASS: full-schema migration/backfill/rerun and rollback-only policy creation with rescheduling authorization.\\n";
