<?php
declare(strict_types=1);

/**
 * Stage 5 payroll edge-case verification.
 *
 * Uses existing disbursed salary-advance data. When a required policy
 * combination is unavailable, the harness may temporarily adapt an existing
 * protected fixture inside a SAVEPOINT and roll that adaptation back before
 * continuing. No request, employee, schedule, policy, payroll, or accounting
 * mutation is committed.
 *
 * Covered:
 * - fixed_monthly + available_salary with insufficient eligible salary
 * - fixed_monthly + skip_month with insufficient eligible salary
 * - full_eligible_salary with low eligible salary
 * - repeated draft calculation remains deterministic
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_payroll.php';

$pdo = db();
ak_ensure_tables();
ak_seed_accounts();

function findEdgeFixture(PDO $pdo, string $rule, ?string $method = null): ?array
{
    $sql = "SELECT r.id AS request_id, r.request_no, r.employee_id,
                   r.approved_repayment_method,
                   s.id AS schedule_id, s.scheduled_month, s.scheduled_amount,
                   s.applied_amount,
                   p.maximum_monthly_deduction,
                   p.insufficient_salary_rule, p.eligible_salary_basis
            FROM hr_salary_advance_requests r
            JOIN hr_salary_advance_repayment_schedule s
              ON s.salary_advance_request_id = r.id
            JOIN hr_salary_advance_policy_versions p
              ON p.id = r.policy_version_id
            WHERE r.status = 'disbursed'
              AND COALESCE(r.outstanding_balance, 0) > 0
              AND s.status IN ('pending', 'partial')
              AND p.insufficient_salary_rule = ?
              AND COALESCE(s.scheduled_amount - s.applied_amount, 0) > 1.00
              AND NOT EXISTS (
                  SELECT 1
                  FROM payroll px
                  WHERE px.employee_id = r.employee_id
                    AND px.month = MONTH(s.scheduled_month)
                    AND px.year = YEAR(s.scheduled_month)
              )";
    $params = [$rule];

    if ($method !== null) {
        $sql .= " AND r.approved_repayment_method = ?";
        $params[] = $method;
    }

    $sql .= " ORDER BY s.scheduled_month ASC, r.id ASC LIMIT 1";
    return dbFetchOne($sql, $params);
}

function nextFixturePayrollId(PDO $pdo): int
{
    $row = dbFetchOne("SELECT COALESCE(MAX(id), 0) AS max_id FROM payroll");
    $journalRow = dbFetchOne(
        "SELECT COALESCE(MAX(reference_id), 0) AS max_id
         FROM journal_entries
         WHERE reference_type = 'payroll'"
    );
    $id = max((int)($row['max_id'] ?? 0), (int)($journalRow['max_id'] ?? 0)) + 1;

    while (dbFetchOne(
        "SELECT id FROM journal_entries
         WHERE reference_type = 'payroll' AND reference_id = ? LIMIT 1",
        [$id]
    )) {
        $id++;
    }

    return $id;
}

function findRollbackBaseFixture(PDO $pdo): ?array
{
    return findEdgeFixture($pdo, 'available_salary', 'fixed_monthly');
}

function runPreview(PDO $pdo, array $fixture, int $payrollId): array
{
    $month = (int)date('n', strtotime($fixture['scheduled_month']));
    $year = (int)date('Y', strtotime($fixture['scheduled_month']));

    $pdo->prepare(
        "INSERT INTO payroll
            (id, employee_id, month, year, basic_salary, allowances, overtime,
             deductions, salary_advance_deduction, net_salary, status)
         VALUES (?, ?, ?, ?, 1.00, 0, 0, 0, 0, 1.00, 'draft')"
    )->execute([
        $payrollId,
        (int)$fixture['employee_id'],
        $month,
        $year,
    ]);

    $payroll = dbFetchOne(
        "SELECT p.*, e.full_name AS employee_name, e.employee_code
         FROM payroll p
         JOIN employees e ON e.id = p.employee_id
         WHERE p.id = ?",
        [$payrollId]
    );

    $first = hrSalaryAdvancePayrollCalculateDraft(
        $pdo,
        $payroll,
        sprintf('%04d-%02d-01', $year, $month)
    );
    $second = hrSalaryAdvancePayrollCalculateDraft(
        $pdo,
        $payroll,
        sprintf('%04d-%02d-01', $year, $month)
    );

    if (round((float)$first['total'], 2) !== round((float)$second['total'], 2)) {
        throw new RuntimeException('Repeated draft calculation is not deterministic.');
    }

    return [$first, $second];
}

$tests = [
    [
        'label' => 'Insufficient salary — available_salary',
        'rule' => 'available_salary',
        'method' => 'fixed_monthly',
    ],
    [
        'label' => 'Insufficient salary — skip_month',
        'rule' => 'skip_month',
        'method' => 'fixed_monthly',
    ],
];

$fullFixture = findEdgeFixture($pdo, 'available_salary', 'full_eligible_salary');

try {
    $pdo->beginTransaction();

    foreach ($tests as $test) {
        $fixture = findEdgeFixture($pdo, $test['rule'], $test['method']);
        $syntheticFixture = false;

        if (!$fixture && $test['rule'] === 'skip_month') {
            $baseFixture = findRollbackBaseFixture($pdo);
            if ($baseFixture) {
                $pdo->exec('SAVEPOINT stage5_skip_month_fixture');

                $pdo->prepare(
                    "UPDATE hr_salary_advance_policy_versions
                     SET insufficient_salary_rule = 'skip_month'
                     WHERE id = (
                         SELECT policy_version_id
                         FROM hr_salary_advance_requests
                         WHERE id = ?
                         LIMIT 1
                     )"
                )->execute([(int)$baseFixture['request_id']]);

                $fixture = findEdgeFixture($pdo, 'skip_month', 'fixed_monthly');
                if (!$fixture) {
                    $pdo->exec('ROLLBACK TO SAVEPOINT stage5_skip_month_fixture');
                    $pdo->exec('RELEASE SAVEPOINT stage5_skip_month_fixture');
                    throw new RuntimeException(
                        'Rollback-only skip_month fixture could not be prepared from an existing disbursed fixed_monthly fixture.'
                    );
                }
                $syntheticFixture = true;
            }
        }

        if (!$fixture) {
            echo "SKIP | {$test['label']} | No existing disbursed fixture matches this policy rule and no safe rollback-only base fixture was available.
";
            continue;
        }

        $payrollId = nextFixturePayrollId($pdo);
        [$preview] = runPreview($pdo, $fixture, $payrollId);
        $allocations = $preview['allocations'] ?? [];

        if (!$allocations) {
            throw new RuntimeException($test['label'] . ': no repayment allocation was produced.');
        }

        $allocation = $allocations[0];
        $total = round((float)$preview['total'], 2);

        if ($test['rule'] === 'available_salary') {
            if ($total !== 1.00 || $allocation['outcome'] !== 'partial') {
                throw new RuntimeException(
                    $test['label'] . ': expected 1.00 partial deduction, got total=' .
                    $total . ', outcome=' . (string)$allocation['outcome']
                );
            }
        } else {
            if ($total !== 0.00 || $allocation['outcome'] !== 'skipped') {
                throw new RuntimeException(
                    $test['label'] . ': expected zero skipped deduction, got total=' .
                    $total . ', outcome=' . (string)$allocation['outcome']
                );
            }
        }

        echo "PASS | {$test['label']} | request={$fixture['request_no']} | scheduled_remaining={$allocation['remaining_schedule_amount']} | eligible_salary={$allocation['eligible_salary']} | deduction={$total} | outcome={$allocation['outcome']}" .
            ($syntheticFixture ? " | rollback_fixture=existing_request_policy_override" : "") . "
";

        if ($syntheticFixture) {
            $pdo->exec('ROLLBACK TO SAVEPOINT stage5_skip_month_fixture');
            $pdo->exec('RELEASE SAVEPOINT stage5_skip_month_fixture');
        }
    }

    $fullFixture = findEdgeFixture($pdo, 'available_salary', 'full_eligible_salary');
    $syntheticFullFixture = false;

    if (!$fullFixture) {
        $baseFixture = findRollbackBaseFixture($pdo);
        if ($baseFixture) {
            $pdo->exec('SAVEPOINT stage5_full_eligible_fixture');

            $pdo->prepare(
                "UPDATE hr_salary_advance_requests
                 SET approved_repayment_method = 'full_eligible_salary'
                 WHERE id = ?"
            )->execute([(int)$baseFixture['request_id']]);

            $fullFixture = findEdgeFixture($pdo, 'available_salary', 'full_eligible_salary');
            if (!$fullFixture) {
                $pdo->exec('ROLLBACK TO SAVEPOINT stage5_full_eligible_fixture');
                $pdo->exec('RELEASE SAVEPOINT stage5_full_eligible_fixture');
                throw new RuntimeException(
                    'Rollback-only full_eligible_salary fixture could not be prepared from an existing disbursed fixture.'
                );
            }
            $syntheticFullFixture = true;
        }
    }

    if ($fullFixture) {
        $payrollId = nextFixturePayrollId($pdo);
        [$preview] = runPreview($pdo, $fullFixture, $payrollId);
        $allocation = $preview['allocations'][0] ?? null;
        $total = round((float)$preview['total'], 2);
        if (
            !$allocation ||
            $total !== 1.00 ||
            $allocation['outcome'] !== 'partial'
        ) {
            throw new RuntimeException(
                'Full eligible salary low-salary behavior did not deduct exactly the available eligible salary as a partial repayment.'
            );
        }
        echo "PASS | Full eligible salary with low eligible salary | request={$fullFixture['request_no']} | deduction={$total} | outcome={$allocation['outcome']}" .
            ($syntheticFullFixture ? " | rollback_fixture=existing_request_method_override" : "") . "
";
    } else {
        echo "SKIP | Full eligible salary with low eligible salary | No existing disbursed full_eligible_salary fixture matches the required period and no safe rollback-only base fixture was available.
";
    }

    if ($syntheticFullFixture) {
        $pdo->exec('ROLLBACK TO SAVEPOINT stage5_full_eligible_fixture');
        $pdo->exec('RELEASE SAVEPOINT stage5_full_eligible_fixture');
    }

    $pdo->rollBack();
    echo "PASS | Rollback-only cleanup | no payroll/request/schedule/journal mutation was committed.
";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL | Stage 5 payroll edge cases | " . $e->getMessage() . "\n");
    exit(1);
}
