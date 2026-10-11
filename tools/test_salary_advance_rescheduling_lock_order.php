<?php
declare(strict_types=1);

/**
 * Controlled two-connection InnoDB regression for the actual rescheduling
 * service under request-row contention. Runs only against the dedicated
 * disposable CI database after the service fixture has been populated.
 */
if (getenv('SALARY_ADVANCE_RESCHEDULE_SERVICE_TEST') !== '1'
    || (string)getenv('DB_DATABASE') !== 'salary_advance_reschedule_service_test') {
    fwrite(STDERR, "Refusing to run outside the dedicated disposable service-test database.\n");
    exit(2);
}

function connection(): PDO
{
    return new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_PORT') ?: '3306',
            getenv('DB_DATABASE')
        ),
        (string)(getenv('DB_USERNAME') ?: 'root'),
        (string)(getenv('DB_PASSWORD') ?: ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function assertLockTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

$a = connection();
$b = connection();

// The preceding service suite intentionally removes this column to force an
// audit-write rollback. Restore it only in this disposable test database so
// the real rescheduling service can also be exercised here.
$a->exec("ALTER TABLE audit_log ADD COLUMN user_agent VARCHAR(255) NULL");

require_once dirname(__DIR__) . '/modules/hr/lib_salary_advance_repayment.php';

// Request 7 is the service fixture reserved for audit-failure rollback; that
// test must leave its original plan active and its balance unchanged.
$fixture = $a->query(
    "SELECT r.id AS request_id, rp.id AS plan_id, rp.plan_no
     FROM hr_salary_advance_requests r
     JOIN hr_salary_advance_repayment_plans rp
       ON rp.salary_advance_request_id = r.id
      AND rp.status = 'active'
     WHERE r.id = 7
       AND r.status = 'disbursed'
       AND COALESCE(r.outstanding_balance, 0) > 0
     ORDER BY rp.plan_no ASC
     LIMIT 1"
)->fetch();

assertLockTest(is_array($fixture), 'request 7 must retain an outstanding balance and one active plan');
$requestId = (int)$fixture['request_id'];
$planId = (int)$fixture['plan_id'];
$startMonth = date('Y-m-01', strtotime(date('Y-m-01') . ' +1 month'));

$a->exec('SET SESSION innodb_lock_wait_timeout = 5');
$b->exec('SET SESSION innodb_lock_wait_timeout = 1');

try {
    // Simulate a competing payroll transaction acquiring the parent request
    // lock first. The real rescheduling service must wait and roll back cleanly.
    $a->beginTransaction();
    $stmt = $a->prepare('SELECT id FROM hr_salary_advance_requests WHERE id = ? FOR UPDATE');
    $stmt->execute([$requestId]);
    assertLockTest((int)$stmt->fetchColumn() === $requestId, 'connection A must lock the parent request');

    $blockedAsExpected = false;
    try {
        hrSalaryAdvanceScheduleReschedule(
            $b,
            $requestId,
            $startMonth,
            3,
            200.00,
            'Concurrent service lock regression',
            77
        );
    } catch (PDOException $e) {
        $mysqlCode = (int)($e->errorInfo[1] ?? 0);
        if ($mysqlCode !== 1205) {
            throw $e;
        }
        $blockedAsExpected = true;
    }
    assertLockTest($blockedAsExpected, 'actual rescheduling service must time out behind the competing request lock');
    assertLockTest((int)$a->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = 7")->fetchColumn() === 1, 'blocked service must not create a replacement plan');
    assertLockTest((string)$a->query("SELECT status FROM hr_salary_advance_repayment_plans WHERE id = " . $planId)->fetchColumn() === 'active', 'blocked service must not supersede the original plan');

    $stmt = $a->prepare("SELECT id FROM hr_salary_advance_repayment_plans WHERE id = ? AND salary_advance_request_id = ? AND status = 'active' FOR UPDATE");
    $stmt->execute([$planId, $requestId]);
    assertLockTest((int)$stmt->fetchColumn() === $planId, 'connection A locks the active plan only after the request');
    $a->commit();

    // Retry through the actual service after contention clears. It should
    // succeed atomically, proving the timeout left no open/partial transaction.
    $success = hrSalaryAdvanceScheduleReschedule(
        $b,
        $requestId,
        $startMonth,
        3,
        200.00,
        'Concurrent service lock regression retry',
        77
    );
    assertLockTest((int)$success['plan_no'] === 2, 'retry creates the next plan version');
    assertLockTest((int)$b->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = 7")->fetchColumn() === 2, 'successful retry preserves old plan and creates one replacement');
    assertLockTest((int)$b->query("SELECT COUNT(*) FROM hr_salary_advance_repayment_plans WHERE salary_advance_request_id = 7 AND status = 'active'")->fetchColumn() === 1, 'successful retry leaves exactly one active plan');
    assertLockTest((int)$b->query("SELECT COUNT(*) FROM audit_log WHERE action = 'HR_SALARY_ADVANCE_REPAYMENT_RESCHEDULE' AND entity_id = 7")->fetchColumn() === 1, 'successful retry writes one rescheduling audit event');

    echo "PASS | Actual rescheduling service serializes behind a competing request lock, rolls back on lock timeout, and succeeds cleanly on retry | request_id={$requestId} | original_plan_id={$planId} | replacement_plan_id=" . (int)$success['plan_id'] . "\n";
    echo "NOTE | This exercises the actual rescheduling service against a competing parent-row lock. It does not yet execute a simultaneous production payroll-posting transaction against rescheduling.\n";
} catch (Throwable $e) {
    if ($a->inTransaction()) {
        $a->rollBack();
    }
    if ($b->inTransaction()) {
        $b->rollBack();
    }
    fwrite(STDERR, "FAIL | Two-connection rescheduling-service lock regression | " . $e->getMessage() . "\n");
    exit(1);
}
