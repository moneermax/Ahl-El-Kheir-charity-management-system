<?php
declare(strict_types=1);

/**
 * Rollback-only production payroll/rescheduling contention regression.
 *
 * Runs only after the full Stage 7 migration against the disposable full-schema
 * CI database. A child PHP process exercises the real payroll status transition
 * so its db() helper and passed PDO share the same independent connection.
 */
// Only the migration-test database is provisioned by the checked-in CI
// workflow with the complete schema and legacy data dump. Do not accept an
// ad-hoc full_schema_test database that may contain tables but no employees.
$allowedDatabases = [
    'salary_advance_migration_test',
];
if (getenv('AHL_ENV') !== 'development'
    || !in_array((string)getenv('AHL_DB_NAME'), $allowedDatabases, true)) {
    fwrite(STDERR, "Refusing to run outside a named disposable Stage 7 full-schema test database.\n");
    exit(2);
}

$_SERVER['REQUEST_METHOD'] = 'CLI';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_payroll.php';
require_once __DIR__ . '/../modules/hr/lib_payroll_accounting.php';
require_once __DIR__ . '/../modules/hr/lib_payroll_transfer.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_repayment.php';

$pdo = db();

$connectedDatabase = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if (!in_array($connectedDatabase, $allowedDatabases, true)
    || $connectedDatabase !== (string)getenv('AHL_DB_NAME')) {
    fwrite(STDERR, "Refusing to continue: connected database does not match the named disposable test database.\n");
    exit(2);
}
$requiredTables = [
    'employees',
    'payroll',
    'users',
    'journal_entries',
    'hr_salary_advance_policy_versions',
    'hr_salary_advance_requests',
    'hr_salary_advance_repayment_plans',
    'hr_salary_advance_repayment_schedule',
    'hr_salary_advance_payroll_repayments',
    'hr_payroll_payment_proofs',
];
$checkTable = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
);
foreach ($requiredTables as $requiredTable) {
    $checkTable->execute([$requiredTable]);
    if ((int)$checkTable->fetchColumn() !== 1) {
        fwrite(STDERR, "Refusing to continue: required full-schema table is missing: " . $requiredTable . "\n");
        exit(2);
    }
}
$policyFlag = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'hr_salary_advance_policy_versions'
       AND column_name = 'allow_fm_repayment_rescheduling'"
)->fetchColumn();
$planReference = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'hr_salary_advance_repayment_schedule'
       AND column_name = 'repayment_plan_id'"
)->fetchColumn();
if ((int)$policyFlag !== 1 || (int)$planReference !== 1) {
    fwrite(STDERR, "Refusing to continue: Stage 7 migration markers are missing from this test database.\n");
    exit(2);
}

function concurrencyCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

function runChild(string $mode, int $payrollId): array
{
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, __FILE__, $mode, (string)$payrollId],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start independent payroll test process.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    return ['exit' => $exit, 'stdout' => trim((string)$stdout), 'stderr' => trim((string)$stderr)];
}

function childPayrollTransition(PDO $pdo, string $mode, int $payrollId): never
{
    require_once __DIR__ . '/../modules/accounting/lib.php';
    require_once __DIR__ . '/../modules/hr/lib_salary_advance_payroll.php';
    require_once __DIR__ . '/../modules/hr/lib_payroll_accounting.php';

    ak_ensure_tables();
    ak_seed_accounts();
    $testActorId = (int)$pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
    if ($testActorId <= 0) {
        fwrite(STDERR, "FAIL: payroll proof audit fixture requires an existing user.\n");
        exit(1);
    }
    $_SESSION['user_id'] = $testActorId;
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');

    if ($mode === 'blocked') {
        $pdo->beginTransaction();
        try {
            hrPayrollChangeStatus($pdo, $payrollId, 'paid', null);
            $pdo->rollBack();
            fwrite(STDERR, "FAIL: payroll transition unexpectedly passed the competing request lock.\n");
            exit(1);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $code = (int)($e instanceof PDOException ? ($e->errorInfo[1] ?? 0) : 0);
            if ($code !== 1205) {
                fwrite(STDERR, "FAIL: expected InnoDB lock timeout, got " . $e->getMessage() . "\n");
                exit(1);
            }
            echo "PASS | Production payroll paid-transition waits on the rescheduling request lock and rolls back on timeout.\n";
            exit(0);
        }
    }

    if ($mode !== 'retry') {
        fwrite(STDERR, "Unknown child test mode.\n");
        exit(2);
    }

    $pdo->beginTransaction();
    try {
        $payroll = dbFetchOne(
            "SELECT p.*, e.full_name AS employee_name, e.employee_code
             FROM payroll p JOIN employees e ON e.id = p.employee_id
             WHERE p.id = ? LIMIT 1",
            [$payrollId]
        );
        if (!$payroll || (string)$payroll['status'] !== 'approved') {
            throw new RuntimeException('Rollback-only payroll fixture is not approved.');
        }

        $periodStart = sprintf('%04d-%02d-01', (int)$payroll['year'], (int)$payroll['month']);
        $preview = hrSalaryAdvancePayrollCalculateDraft($pdo, $payroll, $periodStart);
        $deduction = round((float)($preview['total'] ?? 0), 2);
        if ($deduction <= 0 || empty($preview['allocations'])) {
            throw new RuntimeException('New active plan did not produce an eligible payroll allocation.');
        }

        $pdo->prepare(
            "UPDATE payroll SET salary_advance_deduction = ?, net_salary = ?
             WHERE id = ? AND status = 'approved'"
        )->execute([$deduction, round(max(0, (float)$payroll['basic_salary'] - $deduction), 2), $payrollId]);

        $payroll = dbFetchOne(
            "SELECT p.*, e.full_name AS employee_name, e.employee_code
             FROM payroll p JOIN employees e ON e.id = p.employee_id
             WHERE p.id = ? LIMIT 1",
            [$payrollId]
        );
        hrPayrollChangeStatus($pdo, $payrollId, 'paid', null);

        $paid = dbFetchOne('SELECT status FROM payroll WHERE id = ?', [$payrollId]);
        $trace = dbFetchOne(
            "SELECT COUNT(*) AS c FROM hr_salary_advance_payroll_repayments WHERE payroll_id = ?",
            [$payrollId]
        );
        $journal = dbFetchOne(
            "SELECT id FROM journal_entries
             WHERE reference_type = 'payroll' AND reference_id = ? AND status = 'posted'
             LIMIT 1",
            [$payrollId]
        );
        if (!$paid || $paid['status'] !== 'paid' || (int)($trace['c'] ?? 0) < 1 || !$journal) {
            throw new RuntimeException('Payroll retry did not produce paid state, repayment trace, and posted journal.');
        }

        echo "PASS | Production payroll transition retries after rescheduling and applies the replacement plan; journal and repayment trace verified inside rollback-only transaction.\n";
        $pdo->rollBack();
        echo "PASS | Payroll posting fixture rolled back; no payroll posting was committed.\n";
        exit(0);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "FAIL | Payroll retry after rescheduling | " . $e->getMessage() . "\n");
        exit(1);
    }
}

if (isset($argv[1]) && in_array($argv[1], ['blocked', 'retry'], true)) {
    childPayrollTransition($pdo, (string)$argv[1], (int)($argv[2] ?? 0));
}

ak_ensure_tables();
ak_seed_accounts();

$preflight = [
    'employees' => (int)$pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn(),
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'requests' => (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_requests')->fetchColumn(),
    'schedule_rows' => (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_repayment_schedule')->fetchColumn(),
];
if ($preflight['employees'] < 1 || $preflight['users'] < 1 || $preflight['requests'] < 1) {
    throw new RuntimeException(
        'FAIL: this database is not the imported full-schema fixture required by the concurrency regression; counts='
        . json_encode($preflight, JSON_UNESCAPED_SLASHES)
        . '. Import database/ahl_el_kheir.sql into the disposable salary_advance_migration_test database and run the full-schema migration verification before this test.'
    );
}

// Select an existing request with an active migrated plan, then make its
// eligibility deterministic inside this disposable test database. Checked-in
// legacy data is not guaranteed to contain a currently outstanding payroll
// advance; relying on such a row made this regression stop before testing locks.
$candidates = dbFetchAll(
    "SELECT r.id AS request_id, r.employee_id, r.policy_version_id,
            r.outstanding_balance, r.request_no, rp.id AS active_plan_id
     FROM hr_salary_advance_requests r
     JOIN employees e ON e.id = r.employee_id
     LEFT JOIN hr_salary_advance_repayment_plans rp
       ON rp.salary_advance_request_id = r.id AND rp.status = 'active'
     ORDER BY r.id ASC"
);

$fixture = null;
$nextMonth = '';
$targetYear = 0;
$targetMonth = 0;
$periodsChecked = 0;
$employeesChecked = [];
$employeesWithAllPeriodsOccupied = 0;
foreach ($candidates as $candidate) {
    $employeeIdForSearch = (int)$candidate['employee_id'];
    $employeesChecked[$employeeIdForSearch] = true;
    $allPeriodsOccupied = true;
    for ($monthOffset = 1; $monthOffset <= 60; $monthOffset++) {
        $candidateMonth = date('Y-m-01', strtotime(date('Y-m-01') . ' +' . $monthOffset . ' months'));
        $candidateYear = (int)date('Y', strtotime($candidateMonth));
        $candidateMonthNumber = (int)date('n', strtotime($candidateMonth));
        $periodsChecked++;
        $existingPayroll = dbFetchOne(
            'SELECT id FROM payroll WHERE employee_id = ? AND month = ? AND year = ? LIMIT 1',
            [$employeeIdForSearch, $candidateMonthNumber, $candidateYear]
        );
        if (!$existingPayroll) {
            $fixture = $candidate;
            $nextMonth = $candidateMonth;
            $targetYear = $candidateYear;
            $targetMonth = $candidateMonthNumber;
            $allPeriodsOccupied = false;
            break 2;
        }
    }
    if ($allPeriodsOccupied) {
        $employeesWithAllPeriodsOccupied++;
    }
}
if (!$fixture) {
    $requestCount = (int)$pdo->query('SELECT COUNT(*) FROM hr_salary_advance_requests')->fetchColumn();
    $employeeCount = (int)$pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
    $payrollCount = (int)$pdo->query('SELECT COUNT(*) FROM payroll')->fetchColumn();
    $linkedRequestCount = (int)$pdo->query(
        'SELECT COUNT(*) FROM hr_salary_advance_requests r JOIN employees e ON e.id = r.employee_id'
    )->fetchColumn();
    throw new RuntimeException(
        'FAIL: no fixture candidate found; diagnostics=' . json_encode([
            'requests_total' => $requestCount,
            'employees_total' => $employeeCount,
            'payroll_rows_total' => $payrollCount,
            'requests_with_employee' => count($candidates),
            'verified_request_employee_joins' => $linkedRequestCount,
            'distinct_candidate_employees_checked' => count($employeesChecked),
            'future_payroll_periods_checked' => $periodsChecked,
            'employees_with_all_60_future_periods_occupied' => $employeesWithAllPeriodsOccupied,
            'candidate_request_ids' => array_slice(array_map(
                static fn(array $row): int => (int)$row['request_id'],
                $candidates
            ), 0, 10),
        ], JSON_UNESCAPED_SLASHES)
    );
}

$requestId = (int)$fixture['request_id'];
$employeeId = (int)$fixture['employee_id'];
$policyId = (int)$fixture['policy_version_id'];
// Use a deterministic balance independent of legacy sample data.
$balance = 500.00;
$planId = (int)($fixture['active_plan_id'] ?? 0);
$proofPath = null;
$storageKey = null;

// This test database is disposable. Prepare an eligible request, an active
// plan even when legacy data has no migrated active plan, and policy authorization.
$pdo->beginTransaction();
try {
    if ($planId <= 0) {
        $pdo->prepare(
            "UPDATE hr_salary_advance_repayment_plans
             SET status = 'superseded', superseded_at = CURRENT_TIMESTAMP
             WHERE salary_advance_request_id = ? AND status = 'active'"
        )->execute([$requestId]);
        $nextPlanNo = (int)$pdo->query(
            'SELECT COALESCE(MAX(plan_no), 0) + 1 FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = ' . $requestId
        )->fetchColumn();
        $pdo->prepare(
            "INSERT INTO hr_salary_advance_repayment_plans
                (salary_advance_request_id, plan_no, status, reason,
                 outstanding_balance_at_creation, start_month,
                 maximum_repayment_months, maximum_monthly_deduction, activated_at)
             VALUES (?, ?, 'active', ?, ?, ?, 1, ?, CURRENT_TIMESTAMP)"
        )->execute([
            $requestId,
            $nextPlanNo,
            'Disposable payroll/rescheduling concurrency fixture',
            $balance,
            date('Y-m-01', strtotime(date('Y-m-01') . ' -2 months')),
            $balance,
        ]);
        $planId = (int)$pdo->lastInsertId();
    }
    $pdo->prepare(
        "UPDATE hr_salary_advance_requests
         SET status = 'disbursed',
             outstanding_balance = ?,
             approved_repayment_method = 'fixed_monthly'
         WHERE id = ?"
    )->execute([$balance, $requestId]);
    $pdo->prepare(
        "UPDATE hr_salary_advance_policy_versions
         SET allow_fm_repayment_rescheduling = 1,
             maximum_repayment_months = 36,
             maximum_monthly_deduction = ?
         WHERE id = ?"
    )->execute([$balance, $policyId]);
    $pdo->prepare(
        "UPDATE hr_salary_advance_repayment_plans
         SET start_month = ?, maximum_repayment_months = 1
         WHERE id = ? AND salary_advance_request_id = ? AND status = 'active'"
    )->execute([
        date('Y-m-01', strtotime(date('Y-m-01') . ' -2 months')),
        $planId,
        $requestId,
    ]);

    $maxPayroll = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM payroll')->fetchColumn();
    $maxJournalRef = (int)$pdo->query(
        "SELECT COALESCE(MAX(reference_id), 0) FROM journal_entries WHERE reference_type = 'payroll'"
    )->fetchColumn();
    $payrollId = max($maxPayroll, $maxJournalRef) + 1000;
    concurrencyCheck($payrollId > 0 && $payrollId < 2147483647, 'temporary payroll ID must be within signed INT range');

    $basicSalary = round($balance + 1000000, 2);
    $paymentAccountId = hrPayrollResolvePaymentAccount($pdo, null);
    $pdo->prepare(
        "INSERT INTO payroll
            (id, employee_id, month, year, basic_salary, allowances, overtime,
             deductions, salary_advance_deduction, net_salary, status, payment_account_id)
         VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, ?, 'approved', ?)"
    )->execute([$payrollId, $employeeId, $targetMonth, $targetYear, $basicSalary, $basicSalary, $paymentAccountId]);

    $storageKey = hash('sha256', 'concurrency-' . $payrollId . '-' . bin2hex(random_bytes(16))) . '.pdf';
    $proofPath = hrPayrollProofPath($storageKey);
    if (file_put_contents($proofPath, "%PDF-1.4\n% Concurrency rollback-only fixture\n%%EOF\n") === false) {
        throw new RuntimeException('Could not create the rollback-only payment proof fixture.');
    }
    $pdo->prepare(
        "INSERT INTO hr_payroll_payment_proofs
            (payroll_id, proof_state, storage_key, original_filename, detected_mime_type, file_size_bytes)
         VALUES (?, 'prepared', ?, 'concurrency-fixture.pdf', 'application/pdf', ?)"
    )->execute([$payrollId, $storageKey, filesize($proofPath)]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$pdo->exec('SET SESSION innodb_lock_wait_timeout = 5');
$pdo->beginTransaction();
try {
    $lock = $pdo->prepare('SELECT id FROM hr_salary_advance_requests WHERE id = ? FOR UPDATE');
    $lock->execute([$requestId]);
    concurrencyCheck((int)$lock->fetchColumn() === $requestId, 'parent connection must hold the request row lock');

    $blocked = runChild('blocked', $payrollId);
    concurrencyCheck($blocked['exit'] === 0, 'production payroll transition did not fail safely under the competing request lock: ' . $blocked['stdout'] . ' ' . $blocked['stderr']);

    $actorId = (int)$pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
    concurrencyCheck($actorId > 0, 'full-schema fixture needs an existing audit-log user');

    $startMonth = $nextMonth;
    $result = hrSalaryAdvanceScheduleReschedule(
        $pdo,
        $requestId,
        $startMonth,
        36,
        $balance,
        'Disposable CI production payroll/rescheduling contention regression',
        $actorId
    );
    concurrencyCheck((int)$result['plan_no'] >= 2, 'rescheduling must create a replacement plan version');
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL | Reschedule during production payroll contention | " . $e->getMessage() . "\n");
    exit(1);
}

$retry = runChild('retry', $payrollId);
concurrencyCheck($retry['exit'] === 0, 'production payroll retry did not succeed safely: ' . $retry['stdout'] . ' ' . $retry['stderr']);

// The database itself is disposable CI infrastructure. Remove the committed
// payroll fixture; the successful posting attempt above was rolled back.
$pdo->prepare('DELETE FROM hr_payroll_payment_proofs WHERE payroll_id = ?')->execute([$payrollId]);
$pdo->prepare('DELETE FROM payroll WHERE id = ?')->execute([$payrollId]);
if (is_string($proofPath) && is_file($proofPath)) {
    @unlink($proofPath);
}

echo $blocked['stdout'] . "\n";
echo $retry['stdout'] . "\n";
echo "PASS | Production payroll posting and rescheduling contention regression | request_id={$requestId} | payroll_id={$payrollId} | replacement_plan_id=" . (int)$result['plan_id'] . "\n";
