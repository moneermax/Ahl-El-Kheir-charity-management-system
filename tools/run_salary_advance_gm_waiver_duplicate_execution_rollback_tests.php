<?php
declare(strict_types=1);

/**
 * GM salary-advance waiver duplicate-execution rollback-only verification.
 *
 * Creates, approves, and executes one waiver decision inside a caller-owned
 * transaction, then attempts a second execution of the same decision.
 * The second execution must be rejected because the decision is already
 * executed. The outer transaction is rolled back so no production fixture is
 * left behind.
 *
 * Concurrency inspection is intentionally separate: the production execution
 * function locks the decision row with SELECT ... FOR UPDATE before checking
 * status. This harness proves the duplicate state transition at runtime;
 * it does not claim a two-process concurrent commit test.
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_waiver.php';

$pdo = db();

function findDuplicateExecutionFixture(PDO $pdo): ?array
{
    return dbFetchOne(
        "SELECT r.id AS request_id, r.request_no, r.employee_id,
                r.status, r.outstanding_balance, s.scheduled_month
         FROM hr_salary_advance_requests r
         JOIN hr_salary_advance_repayment_schedule s
           ON s.salary_advance_request_id = r.id
         WHERE r.status = 'disbursed'
           AND COALESCE(r.outstanding_balance, 0) > 0
           AND s.status IN ('pending', 'partial')
           AND NOT EXISTS (
               SELECT 1
               FROM hr_salary_advance_waiver_items wi
               JOIN hr_salary_advance_waiver_decisions wd ON wd.id = wi.decision_id
               WHERE wi.salary_advance_request_id = r.id
                 AND wd.status = 'executed'
           )
         ORDER BY s.scheduled_month ASC, r.id ASC
         LIMIT 1"
    );
}

function findActiveUserByRole(PDO $pdo, array $roleCodes): ?int
{
    $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
    $row = dbFetchOne(
        "SELECT u.id
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.is_active = 1
           AND r.code IN ($placeholders)
         ORDER BY u.id ASC
         LIMIT 1",
        $roleCodes
    );
    return $row ? (int)$row['id'] : null;
}

function findAccountByCode(PDO $pdo, string $code): ?int
{
    $row = dbFetchOne(
        "SELECT id
         FROM accounts
         WHERE code = ? AND is_active = 1
         LIMIT 1",
        [$code]
    );
    return $row ? (int)$row['id'] : null;
}

function findActiveExpenseAccount(PDO $pdo): ?int
{
    $row = dbFetchOne(
        "SELECT id
         FROM accounts
         WHERE is_active = 1
           AND account_type = 'expense'
         ORDER BY id ASC
         LIMIT 1"
    );
    return $row ? (int)$row['id'] : null;
}

$fixture = findDuplicateExecutionFixture($pdo);
if (!$fixture) {
    echo "SKIP | No existing disbursed salary-advance duplicate-execution fixture is available.\n";
    exit(0);
}

$gmUserId = findActiveUserByRole($pdo, ['general_manager', 'gm']);
$fmUserId = findActiveUserByRole($pdo, ['financial_manager', 'fm', 'finance']);
$refundAccountId = findAccountByCode($pdo, '1100');
$expenseAccountId = findActiveExpenseAccount($pdo);

if (!$gmUserId || !$fmUserId || !$refundAccountId || !$expenseAccountId) {
    echo "SKIP | Required active GM/FM users or accounts were not found.\n";
    exit(0);
}

$decisionId = 0;
$beforeDecisionCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions"
)['c'] ?? 0);
$beforeItemCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_items"
)['c'] ?? 0);
$beforeScheduleItemCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_schedule_items"
)['c'] ?? 0);

try {
    $pdo->beginTransaction();

    $effectiveMonth = date('Y-m-01', strtotime((string)$fixture['scheduled_month']));

    $decisionId = hrSalaryAdvanceWaiverCreateDecision(
        $pdo,
        'individual',
        $effectiveMonth,
        'Rollback-only duplicate execution verification',
        $fmUserId,
        (int)$fixture['employee_id'],
        $refundAccountId,
        $expenseAccountId
    );

    hrSalaryAdvanceWaiverGMReview($pdo, $decisionId, $gmUserId, true);

    $firstExecution = hrSalaryAdvanceWaiverExecute(
        $pdo,
        $decisionId,
        $fmUserId
    );

    $afterFirst = dbFetchOne(
        "SELECT status, executed_at
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );

    if (!$afterFirst || $afterFirst['status'] !== 'executed' || empty($afterFirst['executed_at'])) {
        throw new RuntimeException('First execution did not leave the decision in executed state.');
    }

    $journalCountBeforeDuplicate = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM journal_entries
         WHERE reference_type IN ('salary_advance_waiver','salary_advance_waiver_refund')
           AND reference_id = ?",
        [$decisionId]
    )['c'] ?? 0);

    $duplicateRejected = false;
    $duplicateError = '';

    try {
        hrSalaryAdvanceWaiverExecute($pdo, $decisionId, $fmUserId);
    } catch (Throwable $e) {
        $duplicateRejected = true;
        $duplicateError = $e->getMessage();
    }

    if (!$duplicateRejected) {
        throw new RuntimeException('Duplicate execution unexpectedly succeeded.');
    }

    if (strpos($duplicateError, 'ليس بانتظار التنفيذ المالي') === false) {
        throw new RuntimeException(
            'Duplicate execution was rejected, but not by the expected approved_by_gm state guard. Error: ' .
            $duplicateError
        );
    }

    $journalCountAfterDuplicate = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM journal_entries
         WHERE reference_type IN ('salary_advance_waiver','salary_advance_waiver_refund')
           AND reference_id = ?",
        [$decisionId]
    )['c'] ?? 0);

    if ($journalCountAfterDuplicate !== $journalCountBeforeDuplicate) {
        throw new RuntimeException('Duplicate execution changed the decision journal count.');
    }

    if ((int)($firstExecution['decision_id'] ?? 0) !== $decisionId) {
        throw new RuntimeException('First execution returned an unexpected decision ID.');
    }

    echo "PASS | Duplicate waiver execution protection | request={$fixture['request_no']} | decision_id={$decisionId} | first_execution=1 | second_execution_rejected=1 | journals_unchanged=1\n";

    $pdo->rollBack();

    $afterDecisionCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions"
    )['c'] ?? 0);
    $afterItemCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_items"
    )['c'] ?? 0);
    $afterScheduleItemCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_schedule_items"
    )['c'] ?? 0);

    if (
        $afterDecisionCount !== $beforeDecisionCount ||
        $afterItemCount !== $beforeItemCount ||
        $afterScheduleItemCount !== $beforeScheduleItemCount
    ) {
        throw new RuntimeException('Rollback-only duplicate-execution rows were not fully removed.');
    }

    echo "PASS | Rollback-only duplicate cleanup | decision_rows={$afterDecisionCount} | item_rows={$afterItemCount} | schedule_overlay_rows={$afterScheduleItemCount}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | GM waiver duplicate-execution rollback-only verification | " . $e->getMessage() . "\n";
    exit(1);
}
