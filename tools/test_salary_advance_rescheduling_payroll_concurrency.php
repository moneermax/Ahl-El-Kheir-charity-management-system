<?php
declare(strict_types=1);

/**
 * Rollback-only production payroll/rescheduling contention regression.
 *
 * Runs only after the full Stage 7 migration against the disposable full-schema
 * CI database. A child PHP process exercises the real payroll status transition
 * so its db() helper and passed PDO share the same independent connection.
 */
if (getenv('AHL_ENV') !== 'development'
    || (string)getenv('AHL_DB_NAME') !== 'salary_advance_migration_test') {
    fwrite(STDERR, "Refusing to run outside the dedicated disposable full-schema CI database.\n");
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
require_once __DIR__ . '/../modules/hr/lib_salary_advance_repayment.php';

$pdo = db();

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

$nextMonth = date('Y-m-01', strtotime(date('Y-m-01') . ' +1 month'));
$targetYear = (int)date('Y', strtotime($nextMonth));
$targetMonth = (int)date('n', strtotime($nextMonth));

$fixture = dbFetchOne(
    "SELECT r.id AS request_id, r.employee_id, r.policy_version_id,
            r.outstanding_balance, r.request_no, rp.id AS active_plan_id
     FROM hr_salary_advance_requests r
     JOIN hr_salary_advance_repayment_plans rp
       ON rp.salary_advance_request_id = r.id AND rp.status = 'active'
     WHERE r.status = 'disbursed'
       AND COALESCE(r.outstanding_balance, 0) > 0
       AND r.approved_repayment_method IN ('fixed_monthly', 'full_eligible_salary', 'full_settlement')
       AND NOT EXISTS (
           SELECT 1 FROM payroll p
           WHERE p.employee_id = r.employee_id AND p.month = ? AND p.year = ?
       )
     ORDER BY r.id ASC
     LIMIT 1",
    [$targetMonth, $targetYear]
);
concurrencyCheck((bool)$fixture, 'full-schema fixture needs an outstanding payroll-repaid advance and an unused next-month payroll period');

$requestId = (int)$fixture['request_id'];
$employeeId = (int)$fixture['employee_id'];
$policyId = (int)$fixture['policy_version_id'];
$balance = round((float)$fixture['outstanding_balance'], 2);
$planId = (int)$fixture['active_plan_id'];
concurrencyCheck($balance > 0, 'fixture outstanding balance must be positive');

// This test database is disposable. Prepare an expired plan and an explicitly
// authorized test policy without changing any non-test/local database.
$pdo->beginTransaction();
try {
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
    $pdo->prepare(
        "INSERT INTO payroll
            (id, employee_id, month, year, basic_salary, allowances, overtime,
             deductions, salary_advance_deduction, net_salary, status)
         VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, ?, 'approved')"
    )->execute([$payrollId, $employeeId, $targetMonth, $targetYear, $basicSalary, $basicSalary]);
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

    $startMonth = $nextMonth;
    $result = hrSalaryAdvanceScheduleReschedule(
        $pdo,
        $requestId,
        $startMonth,
        36,
        $balance,
        'Disposable CI production payroll/rescheduling contention regression',
        77
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
$pdo->prepare('DELETE FROM payroll WHERE id = ?')->execute([$payrollId]);

echo $blocked['stdout'] . "\n";
echo $retry['stdout'] . "\n";
echo "PASS | Production payroll posting and rescheduling contention regression | request_id={$requestId} | payroll_id={$payrollId} | replacement_plan_id=" . (int)$result['plan_id'] . "\n";
