<?php
declare(strict_types=1);

/**
 * Controlled two-connection InnoDB lock-order regression.
 * Runs only after the rescheduling service fixture has populated the
 * dedicated disposable CI database. It verifies that the parent request lock
 * serializes competing work before the active-plan lock is acquired.
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
$fixture = $a->query(
    "SELECT r.id AS request_id, rp.id AS plan_id
     FROM hr_salary_advance_requests r
     JOIN hr_salary_advance_repayment_plans rp
       ON rp.salary_advance_request_id = r.id
      AND rp.status = 'active'
     WHERE r.status = 'disbursed'
       AND COALESCE(r.outstanding_balance, 0) > 0
     ORDER BY r.id ASC
     LIMIT 1"
)->fetch();

assertLockTest(is_array($fixture), 'service fixture must provide a disbursed request with an active plan');
$requestId = (int)$fixture['request_id'];
$planId = (int)$fixture['plan_id'];

$a->exec('SET SESSION innodb_lock_wait_timeout = 5');
$b->exec('SET SESSION innodb_lock_wait_timeout = 1');

try {
    // Simulate the rescheduling order: parent request first, then active plan.
    $a->beginTransaction();
    $stmt = $a->prepare('SELECT id FROM hr_salary_advance_requests WHERE id = ? FOR UPDATE');
    $stmt->execute([$requestId]);
    assertLockTest((int)$stmt->fetchColumn() === $requestId, 'connection A must lock the parent request');

    $b->beginTransaction();
    $blockedAsExpected = false;
    try {
        // Payroll must acquire this same parent lock before locking schedule/plan rows.
        $stmt = $b->prepare('SELECT id FROM hr_salary_advance_requests WHERE id = ? FOR UPDATE');
        $stmt->execute([$requestId]);
        $stmt->fetchColumn();
    } catch (PDOException $e) {
        $mysqlCode = (int)($e->errorInfo[1] ?? 0);
        if ($mysqlCode !== 1205) {
            throw $e;
        }
        $blockedAsExpected = true;
    } finally {
        if ($b->inTransaction()) {
            $b->rollBack();
        }
    }
    assertLockTest($blockedAsExpected, 'competing connection must wait on the request lock rather than pass it');

    $stmt = $a->prepare('SELECT id FROM hr_salary_advance_repayment_plans WHERE id = ? AND salary_advance_request_id = ? AND status = \'active\' FOR UPDATE');
    $stmt->execute([$planId, $requestId]);
    assertLockTest((int)$stmt->fetchColumn() === $planId, 'connection A must lock its active plan after the request');
    $a->commit();

    // Once A commits, B can acquire request then plan without a lock inversion.
    $b->beginTransaction();
    $stmt = $b->prepare('SELECT id FROM hr_salary_advance_requests WHERE id = ? FOR UPDATE');
    $stmt->execute([$requestId]);
    assertLockTest((int)$stmt->fetchColumn() === $requestId, 'connection B acquires the request after A commits');
    $stmt = $b->prepare('SELECT id FROM hr_salary_advance_repayment_plans WHERE id = ? AND salary_advance_request_id = ? AND status = \'active\' FOR UPDATE');
    $stmt->execute([$planId, $requestId]);
    assertLockTest((int)$stmt->fetchColumn() === $planId, 'connection B then acquires the active plan');
    $b->commit();

    echo "PASS | Two-connection request-before-plan lock serialization | request_id={$requestId} | plan_id={$planId}\n";
    echo "NOTE | This validates InnoDB lock serialization primitives; it does not replace a concurrent end-to-end payroll-versus-reschedule execution test.\n";
} catch (Throwable $e) {
    if ($a->inTransaction()) {
        $a->rollBack();
    }
    if ($b->inTransaction()) {
        $b->rollBack();
    }
    fwrite(STDERR, "FAIL | Two-connection lock-order regression | " . $e->getMessage() . "\n");
    exit(1);
}
