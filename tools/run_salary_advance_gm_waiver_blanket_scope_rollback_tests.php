<?php
declare(strict_types=1);

/**
 * GM salary-advance waiver blanket/multiple-advance rollback-only verification.
 *
 * Verifies that a blanket decision snapshots every currently eligible request
 * for the effective month (not only one employee), preserves all request
 * identities, and can execute across the complete item set atomically.
 *
 * If the live database contains an employee with multiple eligible advances,
 * the harness also proves that all of that employee's eligible requests are
 * included in the same blanket decision. No production data is committed.
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_waiver.php';

$pdo = db();

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
        "SELECT id FROM accounts
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

function eligibleRequestIds(PDO $pdo, string $effectiveMonth): array
{
    $rows = hrSalaryAdvanceWaiverEligibleRows($pdo, $effectiveMonth);
    return array_map(
        static fn(array $row): int => (int)$row['request_id'],
        $rows
    );
}

$effectiveMonth = date('Y-m-01');

$eligibleRows = hrSalaryAdvanceWaiverEligibleRows($pdo, $effectiveMonth);
if (!$eligibleRows) {
    echo "SKIP | No eligible salary-advance requests exist for effective month {$effectiveMonth}.\n";
    exit(0);
}

$gmUserId = findActiveUserByRole($pdo, ['general_manager', 'gm']);
$fmUserId = findActiveUserByRole($pdo, ['financial_manager', 'fm', 'finance']);
$refundAccountId = findAccountByCode($pdo, '1100');
$expenseAccountId = findActiveExpenseAccount($pdo);

if (!$gmUserId || !$fmUserId) {
    echo "SKIP | Required active GM/FM users were not found.\n";
    exit(0);
}
if (!$refundAccountId || !$expenseAccountId) {
    echo "SKIP | Required active refund/expense accounts were not found.\n";
    exit(0);
}

$expectedIds = array_map(
    static fn(array $row): int => (int)$row['request_id'],
    $eligibleRows
);
$expectedIds = array_values(array_unique($expectedIds));
sort($expectedIds);

$employeeCounts = [];
foreach ($expectedIds as $requestId) {
    foreach ($eligibleRows as $row) {
        if ((int)$row['request_id'] !== $requestId) {
            continue;
        }
        $employeeId = (int)$row['employee_id'];
        $employeeCounts[$employeeId] = ($employeeCounts[$employeeId] ?? 0) + 1;
        break;
    }
}
$multipleEmployeeId = null;
$multipleExpectedCount = 0;
foreach ($employeeCounts as $employeeId => $count) {
    if ($count >= 2) {
        $multipleEmployeeId = (int)$employeeId;
        $multipleExpectedCount = $count;
        break;
    }
}

$beforeDecisionCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions"
)['c'] ?? 0);
$beforeItemCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_items"
)['c'] ?? 0);
$beforeOverlayCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_schedule_items"
)['c'] ?? 0);
$decisionId = 0;

try {
    $pdo->beginTransaction();

    $decisionId = hrSalaryAdvanceWaiverCreateDecision(
        $pdo,
        'blanket',
        $effectiveMonth,
        'Rollback-only blanket GM waiver scope verification',
        $fmUserId,
        null,
        $refundAccountId,
        $expenseAccountId
    );

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Blanket preparation unexpectedly ended the caller-owned transaction.');
    }

    $items = dbFetchAll(
        "SELECT salary_advance_request_id, employee_id, previous_request_status
         FROM hr_salary_advance_waiver_items
         WHERE decision_id = ?
         ORDER BY salary_advance_request_id ASC",
        [$decisionId]
    );

    $actualIds = array_map(
        static fn(array $row): int => (int)$row['salary_advance_request_id'],
        $items
    );
    sort($actualIds);

    if ($actualIds !== $expectedIds) {
        throw new RuntimeException(
            'Blanket decision item set does not exactly match the eligible request set. ' .
            'expected=' . implode(',', $expectedIds) .
            ' actual=' . implode(',', $actualIds)
        );
    }

    $decision = dbFetchOne(
        "SELECT decision_type, status, effective_month, refund_account_id, waiver_expense_account_id
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );

    if (!$decision ||
        $decision['decision_type'] !== 'blanket' ||
        $decision['status'] !== 'pending_gm' ||
        $decision['effective_month'] !== $effectiveMonth ||
        (int)$decision['refund_account_id'] !== $refundAccountId ||
        (int)$decision['waiver_expense_account_id'] !== $expenseAccountId
    ) {
        throw new RuntimeException('Blanket decision metadata is inconsistent.');
    }

    if ($multipleEmployeeId !== null) {
        $actualMultipleCount = 0;
        foreach ($items as $item) {
            if ((int)$item['employee_id'] === $multipleEmployeeId) {
                $actualMultipleCount++;
            }
        }
        if ($actualMultipleCount !== $multipleExpectedCount) {
            throw new RuntimeException(
                "Multiple-advance employee scope mismatch: expected={$multipleExpectedCount} actual={$actualMultipleCount}"
            );
        }
    }

    hrSalaryAdvanceWaiverGMReview($pdo, $decisionId, $gmUserId, true);

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Blanket GM review unexpectedly ended the caller-owned transaction.');
    }

    $execution = hrSalaryAdvanceWaiverExecute($pdo, $decisionId, $fmUserId);

    if (!$pdo->inTransaction()) {
        throw new RuntimeException('Blanket execution unexpectedly ended the caller-owned transaction.');
    }

    $executedItems = dbFetchAll(
        "SELECT salary_advance_request_id, employee_id, previous_request_status,
                resulting_request_status, refund_amount, waived_amount, balance_after,
                waiver_journal_entry_id
         FROM hr_salary_advance_waiver_items
         WHERE decision_id = ?
         ORDER BY salary_advance_request_id ASC",
        [$decisionId]
    );

    if (count($executedItems) !== count($expectedIds)) {
        throw new RuntimeException('Executed waiver item count does not match the blanket snapshot.');
    }

    $waiverJournalId = (int)($execution['waiver_journal_entry_id'] ?? 0);
    if ($waiverJournalId <= 0) {
        throw new RuntimeException('Expected one waiver journal for the disbursed blanket items.');
    }

    $waiverJournal = dbFetchOne(
        "SELECT ROUND(COALESCE(SUM(jl.debit),0),2) AS debit_total,
                ROUND(COALESCE(SUM(jl.credit),0),2) AS credit_total,
                COUNT(jl.id) AS line_count
         FROM journal_entries je
         JOIN journal_lines jl ON jl.entry_id = je.id
         WHERE je.id = ?
         GROUP BY je.id",
        [$waiverJournalId]
    );

    $expectedWaiverTotal = 0.00;
    $cancelledCount = 0;
    $disbursedCount = 0;
    foreach ($executedItems as $item) {
        $expectedWaiverTotal = round($expectedWaiverTotal + max(0.00, (float)$item['waived_amount']), 2);
        if ($item['previous_request_status'] === 'disbursed') {
            $disbursedCount++;
            if ($item['resulting_request_status'] !== 'disbursed' ||
                round((float)$item['balance_after'], 2) !== 0.00 ||
                (int)$item['waiver_journal_entry_id'] !== $waiverJournalId) {
                throw new RuntimeException('A disbursed blanket item did not reach the expected waived state.');
            }
        } else {
            $cancelledCount++;
            if ($item['resulting_request_status'] !== 'cancelled' ||
                round((float)$item['waived_amount'], 2) !== 0.00) {
                throw new RuntimeException('An undistributed blanket item did not reach the expected cancellation state.');
            }
        }
    }

    if ($disbursedCount > 0) {
        if (!$waiverJournal ||
            round((float)$waiverJournal['debit_total'], 2) !== $expectedWaiverTotal ||
            round((float)$waiverJournal['credit_total'], 2) !== $expectedWaiverTotal ||
            (int)$waiverJournal['line_count'] !== 2) {
            throw new RuntimeException('Blanket waiver journal is not balanced to the executed item totals.');
        }
    } elseif ($waiverJournalId !== 0) {
        throw new RuntimeException('No disbursed items existed but a waiver journal was created.');
    }

    if ((int)$execution['item_count'] !== count($expectedIds)) {
        throw new RuntimeException('Execution result item count mismatch for blanket decision.');
    }

    echo "PASS | Blanket waiver scope + execution | effective_month={$effectiveMonth} | eligible_requests=" .
        count($expectedIds) . " | executed_items=" . count($executedItems) .
        " | disbursed={$disbursedCount} | cancelled={$cancelledCount} | waiver_total=" .
        number_format($expectedWaiverTotal, 2, '.', '') .
        " | multiple_employee_advances=" .
        ($multipleEmployeeId !== null ? $multipleExpectedCount : 'not_present') .
        " | waiver_journal=" . ($waiverJournalId ?: 0) . "\n";

    $pdo->rollBack();

    $afterDecisionCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_decisions"
    )['c'] ?? 0);
    $afterItemCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_items"
    )['c'] ?? 0);
    $afterOverlayCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_salary_advance_waiver_schedule_items"
    )['c'] ?? 0);

    if ($afterDecisionCount !== $beforeDecisionCount ||
        $afterItemCount !== $beforeItemCount ||
        $afterOverlayCount !== $beforeOverlayCount) {
        throw new RuntimeException('Rollback-only blanket waiver rows were not fully restored.');
    }

    foreach ($expectedIds as $requestId) {
        $row = dbFetchOne(
            "SELECT status, outstanding_balance, closed_at
             FROM hr_salary_advance_requests
             WHERE id = ?",
            [$requestId]
        );
        $original = null;
        foreach ($eligibleRows as $candidate) {
            if ((int)$candidate['request_id'] === $requestId) {
                $original = $candidate;
                break;
            }
        }
        if (!$row || !$original) {
            throw new RuntimeException("Rollback verification could not re-read request {$requestId}.");
        }
        if ($row['status'] !== $original['status'] ||
            round((float)$row['outstanding_balance'], 2) !== round((float)$original['outstanding_balance'], 2)) {
            throw new RuntimeException("Rollback did not restore request {$requestId}.");
        }
    }

    echo "PASS | Rollback-only blanket cleanup | decision_rows={$afterDecisionCount} | item_rows={$afterItemCount} | schedule_overlay_rows={$afterOverlayCount}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | Blanket/multiple-advance rollback-only verification | " . $e->getMessage() . "\n";
    exit(1);
}
