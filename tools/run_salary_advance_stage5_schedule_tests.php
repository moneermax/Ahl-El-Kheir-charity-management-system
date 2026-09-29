<?php
declare(strict_types=1);

// config/functions.php expects REQUEST_METHOD even when this verification runs from CLI.
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

/**
 * Development-only Stage 5 schedule-rule verification.
 *
 * Every test runs inside its own DB transaction and rolls back.
 * No salary-advance request, policy, schedule row, journal, or audit
 * record created by this harness survives the test.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_repayment.php';

$pdo = db();

function testFail(string $message): never
{
    throw new RuntimeException($message);
}

function sourceRequest(PDO $pdo): array
{
    $row = dbFetchOne(
        "SELECT *
         FROM hr_salary_advance_requests
         WHERE status = 'disbursed'
           AND outstanding_balance > 0
           AND disbursed_at IS NOT NULL
         ORDER BY id DESC
         LIMIT 1"
    );

    if (!$row) {
        testFail('No disbursed salary-advance request with an outstanding balance is available as a temporary source fixture.');
    }

    return $row;
}

function clonePolicy(PDO $pdo, array $source, string $startRule, float $maxDeduction, int $maxMonths): int
{
    $policy = dbFetchOne(
        "SELECT *
         FROM hr_salary_advance_policy_versions
         WHERE id = ?
         LIMIT 1",
        [(int)$source['policy_version_id']]
    );

    if (!$policy) {
        testFail('Source policy was not found.');
    }

    $version = dbFetchOne(
        "SELECT COALESCE(MAX(version_no), 0) + 1000 AS next_version
         FROM hr_salary_advance_policy_versions"
    );

    $nextVersion = (int)$version['next_version'];
    $effectiveFrom = '2099-01-01';

    $stmt = $pdo->prepare(
        "INSERT INTO hr_salary_advance_policy_versions
        (
            version_no, policy_name, effective_from, notes,
            allow_any_request_amount, minimum_request_amount, maximum_request_amount,
            allow_multiple_active_advances, allow_fixed_monthly_repayment,
            allow_full_eligible_salary_repayment, allow_full_settlement_from_salary,
            allow_direct_repayment, allow_custom_repayment_terms,
            maximum_monthly_deduction, maximum_repayment_months,
            repayment_start_rule, insufficient_salary_rule, eligible_salary_basis,
            minimum_service_days, probation_allowed, terminated_employee_allowed,
            require_accounting_verification, allow_early_settlement, created_by
        )
        VALUES
        (
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?
        )"
    );

    $stmt->execute([
        $nextVersion,
        'Stage 5 temporary verification fixture',
        $effectiveFrom,
        'ROLLBACK-ONLY TEST FIXTURE',
        (int)$policy['allow_any_request_amount'],
        $policy['minimum_request_amount'],
        $policy['maximum_request_amount'],
        (int)$policy['allow_multiple_active_advances'],
        (int)$policy['allow_fixed_monthly_repayment'],
        (int)$policy['allow_full_eligible_salary_repayment'],
        (int)$policy['allow_full_settlement_from_salary'],
        (int)$policy['allow_direct_repayment'],
        (int)$policy['allow_custom_repayment_terms'],
        $maxDeduction,
        $maxMonths,
        $startRule,
        $policy['insufficient_salary_rule'],
        $policy['eligible_salary_basis'],
        (int)$policy['minimum_service_days'],
        (int)$policy['probation_allowed'],
        (int)$policy['terminated_employee_allowed'],
        (int)$policy['require_accounting_verification'],
        (int)$policy['allow_early_settlement'],
        $source['submitted_by'] ?? null,
    ]);

    return (int)$pdo->lastInsertId();
}

function cloneRequest(PDO $pdo, array $source, int $policyId, string $method, ?float $monthly, ?string $startMonth): int
{
    $requestNo = 'TEST-S5-' . date('YmdHis') . '-' . random_int(100, 999);

    $stmt = $pdo->prepare(
        "INSERT INTO hr_salary_advance_requests
        (
            request_no, employee_id, policy_version_id,
            requested_amount, requested_repayment_method, requested_monthly_amount,
            requested_start_month, request_reason, status,
            submitted_by, submitted_at,
            fm_reviewed_by, fm_reviewed_at, fm_decision, fm_rejection_reason,
            approved_amount, approved_repayment_method, approved_monthly_amount,
            approved_start_month, fm_customized, fm_customization_reason,
            accounting_status, accounting_verified_by, accounting_verified_at,
            accounting_rejection_reason, disbursed_by, disbursed_at,
            disbursement_account_id, disbursement_journal_entry_id,
            disbursement_reference, outstanding_balance, settled_by, settled_at
        )
        VALUES
        (
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, 'disbursed',
            ?, ?,
            ?, ?, 'approved', NULL,
            ?, ?, ?,
            ?, ?, NULL,
            'verified', ?, ?, NULL, ?, ?,
            ?, ?, ?, ?, NULL, NULL
        )"
    );

    $amount = round((float)$source['approved_amount'], 2);
    $stmt->execute([
        $requestNo,
        (int)$source['employee_id'],
        $policyId,
        $amount,
        $method,
        $monthly,
        $startMonth,
        'ROLLBACK-ONLY TEST FIXTURE',
        (int)$source['submitted_by'],
        $source['submitted_at'],
        $source['fm_reviewed_by'],
        $source['fm_reviewed_at'],
        $amount,
        $method,
        $monthly,
        $startMonth,
        (int)$source['fm_customized'],
        (int)$source['accounting_verified_by'],
        $source['accounting_verified_at'],
        (int)$source['disbursed_by'],
        $source['disbursed_at'],
        $source['disbursement_account_id'],
        $source['disbursement_journal_entry_id'],
        'ROLLBACK-ONLY-' . $requestNo,
        $amount,
    ]);

    return (int)$pdo->lastInsertId();
}

function scheduleRows(PDO $pdo, int $requestId): array
{
    return dbFetchAll(
        "SELECT installment_no, scheduled_month, scheduled_amount
         FROM hr_salary_advance_repayment_schedule
         WHERE salary_advance_request_id = ?
         ORDER BY installment_no",
        [$requestId]
    );
}

function runTest(string $label, callable $test): array
{
    global $pdo;

    $pdo->beginTransaction();

    try {
        $result = $test($pdo);
        $pdo->rollBack();

        return ['label' => $label, 'status' => 'PASS', 'detail' => $result];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['label' => $label, 'status' => 'FAIL', 'detail' => $e->getMessage()];
    }
}

$results = [];

$results[] = runTest('Test 4 — specified_month', function (PDO $pdo): string {
    $source = sourceRequest($pdo);
    $policyId = clonePolicy($pdo, $source, 'specified_month', 10000.00, 6);
    $requestId = cloneRequest(
        $pdo,
        $source,
        $policyId,
        'fixed_monthly',
        10000.00,
        '2026-12-01'
    );

    $count = hrSalaryAdvanceScheduleGenerate($pdo, $requestId);
    $rows = scheduleRows($pdo, $requestId);

    if ($count < 1 || !$rows) {
        testFail('No schedule rows were generated.');
    }

    if ($rows[0]['scheduled_month'] !== '2026-12-01') {
        testFail('Expected first scheduled month 2026-12-01, got ' . $rows[0]['scheduled_month'] . '.');
    }

    return 'First scheduled month honored approved specified month: 2026-12-01.';
});

$results[] = runTest('Test 5 — duplicate schedule generation', function (PDO $pdo): string {
    $source = sourceRequest($pdo);
    $policyId = clonePolicy($pdo, $source, 'next_payroll', 10000.00, 10);
    $requestId = cloneRequest(
        $pdo,
        $source,
        $policyId,
        'fixed_monthly',
        10000.00,
        date('Y-m-01')
    );

    $firstCount = hrSalaryAdvanceScheduleGenerate($pdo, $requestId);
    $firstRows = scheduleRows($pdo, $requestId);

    $secondCount = hrSalaryAdvanceScheduleGenerate($pdo, $requestId);
    $secondRows = scheduleRows($pdo, $requestId);

    if ($firstCount !== $secondCount || count($firstRows) !== count($secondRows)) {
        testFail('Second generation changed the schedule count.');
    }

    if ($firstRows !== $secondRows) {
        testFail('Second generation changed existing schedule rows.');
    }

    return 'Second generation returned the existing schedule without adding rows.';
});

$results[] = runTest('Test 6 — full_eligible_salary planning', function (PDO $pdo): string {
    $source = sourceRequest($pdo);
    $amount = round((float)$source['approved_amount'], 2);

    $maxDeduction = 10000.00;
    $maxMonths = (int)ceil($amount / $maxDeduction);

    $policyId = clonePolicy($pdo, $source, 'next_payroll', $maxDeduction, $maxMonths);
    $requestId = cloneRequest(
        $pdo,
        $source,
        $policyId,
        'full_eligible_salary',
        null,
        date('Y-m-01')
    );

    $count = hrSalaryAdvanceScheduleGenerate($pdo, $requestId);
    $rows = scheduleRows($pdo, $requestId);

    if ($count !== $maxMonths) {
        testFail('Expected ' . $maxMonths . ' planned installments, got ' . $count . '.');
    }

    $total = 0.00;
    foreach ($rows as $row) {
        $amountRow = round((float)$row['scheduled_amount'], 2);
        if ($amountRow > $maxDeduction + 0.000001) {
            testFail('A planned installment exceeds the maximum monthly deduction.');
        }
        $total += $amountRow;
    }

    if (round($total, 2) !== $amount) {
        testFail('Planned installments total ' . number_format($total, 2) . ' but outstanding balance is ' . number_format($amount, 2) . '.');
    }

    return 'Full-eligible-salary planning respects the monthly cap and covers the full outstanding balance within the saved horizon.';
});

$failed = false;

echo PHP_EOL . "Stage 5 schedule-rule verification (rollback-only)" . PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;

foreach ($results as $result) {
    echo $result['status'] . ' | ' . $result['label'] . ' | ' . $result['detail'] . PHP_EOL;
    if ($result['status'] !== 'PASS') {
        $failed = true;
    }
}

echo str_repeat('=', 60) . PHP_EOL;
echo $failed ? "RESULT: FAIL" . PHP_EOL : "RESULT: ALL THREE TESTS PASS" . PHP_EOL;

exit($failed ? 1 : 0);
