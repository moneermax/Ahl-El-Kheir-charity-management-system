<?php
declare(strict_types=1);

/**
 * Read-only local preflight for salary-advance Stage 7.
 *
 * This script never applies a migration and never changes application data.
 * Run from the repository root with the local XAMPP PHP CLI.
 */
$_SERVER['REQUEST_METHOD'] = 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$problems = [];

function inspectStage7TableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() === 1;
}

function inspectStage7Columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare(
        'SELECT column_name FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function inspectStage7Fail(array &$problems, string $message): void
{
    $problems[] = $message;
    fwrite(STDERR, "FAIL | " . $message . "\n");
}

echo "READ-ONLY salary-advance Stage 7 preflight\n";
echo "Database: " . $database . "\n";
echo "No migrations or data changes will be performed.\n";

$required = [
    'hr_salary_advance_policy_versions' => [
        'id', 'maximum_monthly_deduction', 'maximum_repayment_months',
    ],
    'hr_salary_advance_requests' => [
        'id', 'request_no', 'employee_id', 'policy_version_id', 'status',
        'outstanding_balance', 'approved_repayment_method',
    ],
    'hr_salary_advance_repayment_schedule' => [
        'id', 'salary_advance_request_id', 'installment_no', 'scheduled_month',
        'scheduled_amount', 'applied_amount', 'status', 'applied_payroll_id',
        'applied_at', 'skip_reason',
    ],
    'hr_salary_advance_payroll_repayments' => [
        'id', 'salary_advance_request_id', 'repayment_schedule_id', 'payroll_id',
        'employee_id', 'actual_amount', 'outcome', 'accounting_entry_id',
    ],
    'audit_log' => [
        'id', 'user_id', 'action', 'entity_type', 'entity_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ],
];

$columnsByTable = [];
foreach ($required as $table => $requiredColumns) {
    if (!inspectStage7TableExists($pdo, $table)) {
        inspectStage7Fail($problems, "Required base table is missing: " . $table);
        continue;
    }

    $columns = inspectStage7Columns($pdo, $table);
    $columnsByTable[$table] = $columns;
    $missing = array_values(array_diff($requiredColumns, $columns));
    if ($missing) {
        inspectStage7Fail(
            $problems,
            $table . " is missing required column(s): " . implode(', ', $missing)
        );
    }
}

$hasPolicyFlag = in_array(
    'allow_fm_repayment_rescheduling',
    $columnsByTable['hr_salary_advance_policy_versions'] ?? [],
    true
);
$hasPlanTable = inspectStage7TableExists($pdo, 'hr_salary_advance_repayment_plans');
$hasPlanReference = in_array(
    'repayment_plan_id',
    $columnsByTable['hr_salary_advance_repayment_schedule'] ?? [],
    true
);
$artifactsPresent = (int)$hasPolicyFlag + (int)$hasPlanTable + (int)$hasPlanReference;

if ($artifactsPresent === 0) {
    echo "Stage 7 schema state: migration not applied; pre-migration state confirmed.\n";
} elseif ($artifactsPresent === 3) {
    echo "Stage 7 schema state: all three migration markers are present.\n";
    echo "Do not reapply the migration based on this preflight; verify migration history separately.\n";
} else {
    inspectStage7Fail(
        $problems,
        'Partial Stage 7 schema detected (policy flag, repayment-plan table, and schedule plan reference are not all present). Stop before applying the migration.'
    );
}

if (isset($columnsByTable['hr_salary_advance_requests'])
    && isset($columnsByTable['hr_salary_advance_policy_versions'])
    && isset($columnsByTable['hr_salary_advance_repayment_schedule'])) {
    $requestCount = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_requests')->fetchColumn();
    $scheduleCount = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_repayment_schedule')->fetchColumn();
    $policyCount = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_policy_versions')->fetchColumn();

    $orphanRequests = (int)$pdo->query(
        "SELECT COUNT(*)
         FROM hr_salary_advance_requests r
         LEFT JOIN hr_salary_advance_policy_versions p ON p.id = r.policy_version_id
         WHERE p.id IS NULL"
    )->fetchColumn();
    $orphanSchedules = (int)$pdo->query(
        "SELECT COUNT(*)
         FROM hr_salary_advance_repayment_schedule s
         LEFT JOIN hr_salary_advance_requests r ON r.id = s.salary_advance_request_id
         WHERE r.id IS NULL"
    )->fetchColumn();

    echo "Base records: requests={$requestCount}, schedule_rows={$scheduleCount}, policy_versions={$policyCount}\n";
    echo "Integrity counts: requests_without_policy={$orphanRequests}, schedules_without_request={$orphanSchedules}\n";

    if ($orphanRequests > 0) {
        inspectStage7Fail($problems, 'Some salary-advance requests reference a missing policy version.');
    }
    if ($orphanSchedules > 0) {
        inspectStage7Fail($problems, 'Some repayment schedule rows reference a missing salary-advance request.');
    }

    if ($hasPlanTable && $hasPlanReference) {
        $unlinkedSchedules = (int)$pdo->query(
            'SELECT COUNT(*) FROM hr_salary_advance_repayment_schedule WHERE repayment_plan_id IS NULL'
        )->fetchColumn();
        $multipleActivePlans = (int)$pdo->query(
            "SELECT COUNT(*) FROM (
                SELECT salary_advance_request_id
                FROM hr_salary_advance_repayment_plans
                WHERE status = 'active'
                GROUP BY salary_advance_request_id
                HAVING COUNT(*) > 1
            ) active_plan_conflicts"
        )->fetchColumn();
        echo "Stage 7 integrity counts: schedules_without_plan={$unlinkedSchedules}, requests_with_multiple_active_plans={$multipleActivePlans}\n";
        if ($unlinkedSchedules > 0) {
            inspectStage7Fail($problems, 'Some schedule rows have no repayment-plan identity.');
        }
        if ($multipleActivePlans > 0) {
            inspectStage7Fail($problems, 'Some requests have more than one active repayment plan.');
        }
    }
}

if ($problems) {
    fwrite(STDERR, "RESULT: FAIL (" . count($problems) . " issue(s)); no changes were made.\n");
    exit(1);
}

echo "RESULT: PASS; read-only schema and integrity checks found no blocking issue.\n";
