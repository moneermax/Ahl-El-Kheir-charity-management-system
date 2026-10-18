<?php
declare(strict_types=1);

/**
 * HR Manager incentive verification/award recognition — rollback-only integration check.
 *
 * Mirrors the database transition performed by modules/hr/incentive_verification.php
 * and calls the production recognition helper. All test rows/journals are rolled
 * back. No DDL, policy changes, payroll changes, or commits are performed.
 *
 * This is not an HTTP/session/CSRF browser test.
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/hr/lib_employee_incentives.php';

$pdo = db();

function hrVerificationTestSkip(string $reason): never
{
    echo "SKIP | HR incentive verification rollback tests | {$reason}\n";
    exit(0);
}

function hrVerificationTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hrVerificationTestOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hrVerificationTestCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$currency = strtoupper(trim((string)APP_CURRENCY_CODE));
if (!preg_match('/^[A-Z]{3}$/', $currency)) {
    hrVerificationTestSkip('APP_CURRENCY_CODE is not a three-letter currency code.');
}

$manager = hrVerificationTestOne(
    $pdo,
    "SELECT u.id
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.is_active = 1 AND r.code = 'hr_manager'
     ORDER BY u.id LIMIT 1"
);
if (!$manager) {
    hrVerificationTestSkip('No active user with role code hr_manager is available.');
}
$managerId = (int)$manager['id'];

$fixture = hrVerificationTestOne(
    $pdo,
    "SELECT e.id AS employee_id, e.user_id
     FROM employees e
     JOIN hr_employment_states es ON es.id = e.employment_state_id
     JOIN users u ON u.id = e.user_id
     WHERE es.category = 'working' AND es.is_active = 1
       AND u.is_active = 1 AND e.user_id IS NOT NULL AND e.user_id > 0
     ORDER BY e.id LIMIT 1"
);
if (!$fixture) {
    hrVerificationTestSkip('No active working employee with an active user account is available.');
}
$employeeId = (int)$fixture['employee_id'];
$recipientUserId = (int)$fixture['user_id'];

$policy = hrVerificationTestOne(
    $pdo,
    "SELECT pv.id AS policy_version_id, r.reward_amount, r.currency_code
     FROM hr_incentive_policy_versions pv
     JOIN hr_incentive_policy_rules r ON r.policy_version_id = pv.id
     WHERE pv.status IN ('approved','active','retired')
       AND r.incentive_type = 'new_sponsor'
       AND r.is_enabled = 1 AND r.reward_amount > 0
       AND UPPER(TRIM(r.currency_code)) = ?
     ORDER BY CASE pv.status WHEN 'active' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
              pv.id DESC
     LIMIT 1",
    [$currency]
);
if (!$policy) {
    hrVerificationTestSkip('No existing eligible new-sponsor policy rule matches the system currency; no policy was created or changed.');
}

$accounts = $pdo->query(
    "SELECT code, id, account_type, is_active FROM accounts WHERE code IN ('5500','2400')"
)->fetchAll(PDO::FETCH_ASSOC);
$accountMap = [];
foreach ($accounts as $account) {
    $accountMap[(string)$account['code']] = $account;
}
if (!isset($accountMap['5500'], $accountMap['2400'])
    || (int)$accountMap['5500']['is_active'] !== 1
    || (int)$accountMap['2400']['is_active'] !== 1
    || $accountMap['5500']['account_type'] !== 'expense'
    || $accountMap['2400']['account_type'] !== 'liability') {
    hrVerificationTestSkip('Required active incentive accounts 5500/2400 with correct types are unavailable.');
}

$eventId = 0;
$awardId = 0;
$journalId = 0;
$pdo->beginTransaction();

try {
    $policyVersionId = (int)$policy['policy_version_id'];
    $amount = round((float)$policy['reward_amount'], 2);
    $eventKey = 'rollback-hr-verification-' . bin2hex(random_bytes(12));

    $insertEvent = $pdo->prepare(
        "INSERT INTO hr_incentive_events
            (idempotency_key, source_table, source_record_id, incentive_type,
             policy_version_id, occurred_at, source_amount, source_currency_code,
             recipient_user_id, recipient_employee_id, status, created_by_user_id)
         VALUES (?, 'rollback_test', ?, 'new_sponsor', ?, NOW(), 1.00, ?,
                 ?, ?, 'pending_verification', ?)"
    );
    $insertEvent->execute([
        $eventKey,
        $employeeId,
        $policyVersionId,
        $currency,
        $recipientUserId,
        $employeeId,
        $managerId
    ]);
    $eventId = (int)$pdo->lastInsertId();

    // Match the production page's locked event/rule eligibility checks.
    $event = hrVerificationTestOne(
        $pdo,
        "SELECT ev.*, r.reward_amount, r.currency_code AS reward_currency_code,
                r.is_enabled
         FROM hr_incentive_events ev
         JOIN hr_incentive_policy_rules r
           ON r.policy_version_id = ev.policy_version_id
          AND r.incentive_type = ev.incentive_type
         WHERE ev.id = ? LIMIT 1 FOR UPDATE",
        [$eventId]
    );
    hrVerificationTestAssert($event !== null, 'Inserted event could not be reloaded.');
    hrVerificationTestAssert($event['status'] === 'pending_verification', 'Synthetic event did not start pending.');
    hrVerificationTestAssert((int)$event['is_enabled'] === 1 && (float)$event['reward_amount'] > 0, 'Selected policy rule is not eligible.');
    hrVerificationTestAssert($event['incentive_type'] === 'new_sponsor', 'Retained new-sponsor verification fixture was not selected.');

    $employee = hrVerificationTestOne(
        $pdo,
        "SELECT e.id, e.user_id, es.category,
                es.is_active AS state_is_active, u.is_active AS user_is_active
         FROM employees e
         JOIN hr_employment_states es ON es.id = e.employment_state_id
         JOIN users u ON u.id = e.user_id
         WHERE e.id = ? LIMIT 1 FOR UPDATE",
        [$employeeId]
    );
    hrVerificationTestAssert(
        $employee !== null
        && (int)$employee['user_id'] === $recipientUserId
        && $employee['category'] === 'working'
        && (int)$employee['state_is_active'] === 1
        && (int)$employee['user_is_active'] === 1,
        'Recipient relationship/employment eligibility check failed.'
    );

    $verify = $pdo->prepare(
        "UPDATE hr_incentive_events
         SET status = 'verified', verification_note = ?, verified_by_user_id = ?, verified_at = NOW()
         WHERE id = ? AND status = 'pending_verification'"
    );
    $verify->execute(['Rollback-only HR verification acceptance test', $managerId, $eventId]);
    hrVerificationTestAssert($verify->rowCount() === 1, 'Pending-to-verified event transition failed.');

    $insertAward = $pdo->prepare(
        "INSERT INTO hr_incentive_awards
            (event_id, employee_id, recipient_user_id, reward_amount,
             reward_currency_code, status, earned_at)
         VALUES (?, ?, ?, ?, ?, 'earned', ?)"
    );
    $insertAward->execute([
        $eventId,
        $employeeId,
        $recipientUserId,
        $amount,
        strtoupper(trim((string)$event['reward_currency_code'])),
        (string)$event['occurred_at']
    ]);
    $awardId = (int)$pdo->lastInsertId();

    $journalId = hrIncentiveRecognizeAward($pdo, $awardId, $managerId);
    hrVerificationTestAssert($journalId > 0, 'Recognition helper held the award instead of posting; inspect currency/configuration.');
    $award = hrVerificationTestOne(
        $pdo,
        "SELECT a.status, a.recognition_journal_entry_id, a.reward_amount,
                e.status AS event_status
         FROM hr_incentive_awards a
         JOIN hr_incentive_events e ON e.id = a.event_id
         WHERE a.id = ?",
        [$awardId]
    );
    hrVerificationTestAssert(
        $award !== null
        && $award['event_status'] === 'verified'
        && $award['status'] === 'earned'
        && (int)$award['recognition_journal_entry_id'] === $journalId,
        'Verified event, earned award, and recognition journal linkage do not agree.'
    );

    $journal = hrVerificationTestOne(
        $pdo,
        "SELECT je.status, je.reference_type, je.reference_id,
                ROUND(COALESCE(SUM(jl.debit), 0), 2) AS debit_total,
                ROUND(COALESCE(SUM(jl.credit), 0), 2) AS credit_total,
                ROUND(COALESCE(SUM(CASE WHEN a.code = '5500' THEN jl.debit ELSE 0 END), 0), 2) AS expense_debit,
                ROUND(COALESCE(SUM(CASE WHEN a.code = '2400' THEN jl.credit ELSE 0 END), 0), 2) AS payable_credit
         FROM journal_entries je
         JOIN journal_lines jl ON jl.entry_id = je.id
         JOIN accounts a ON a.id = jl.account_id
         WHERE je.id = ?
         GROUP BY je.id",
        [$journalId]
    );
    hrVerificationTestAssert(
        $journal !== null
        && $journal['status'] === 'posted'
        && $journal['reference_type'] === 'hr_incentive_award_recognition'
        && (int)$journal['reference_id'] === $awardId
        && (float)$journal['debit_total'] === $amount
        && (float)$journal['credit_total'] === $amount
        && (float)$journal['expense_debit'] === $amount
        && (float)$journal['payable_credit'] === $amount,
        'Recognition journal is missing, unbalanced, or uses incorrect incentive accounts.'
    );

    // Calling the production helper again must return the same posted journal.
    $duplicateJournalId = hrIncentiveRecognizeAward($pdo, $awardId, $managerId);
    hrVerificationTestAssert($duplicateJournalId === $journalId, 'Duplicate recognition did not return the original journal.');
    hrVerificationTestAssert(
        hrVerificationTestCount(
            $pdo,
            "SELECT COUNT(*) FROM journal_entries
             WHERE reference_type = 'hr_incentive_award_recognition' AND reference_id = ? AND status = 'posted'",
            [$awardId]
        ) === 1,
        'Duplicate recognition created more than one posted journal.'
    );

    $pdo->rollBack();

    hrVerificationTestAssert(
        hrVerificationTestCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_events WHERE idempotency_key = ?', [$eventKey]) === 0,
        'Rollback cleanup failed for the synthetic event.'
    );
    hrVerificationTestAssert(
        hrVerificationTestCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_awards WHERE event_id = ?', [$eventId]) === 0,
        'Rollback cleanup failed for the synthetic award.'
    );
    hrVerificationTestAssert(
        hrVerificationTestCount(
            $pdo,
            "SELECT COUNT(*) FROM journal_entries WHERE reference_type = 'hr_incentive_award_recognition' AND reference_id = ?",
            [$awardId]
        ) === 0,
        'Rollback cleanup failed for the synthetic recognition journal.'
    );

    echo "PASS | hr_manager_fixture=available | recipient_eligibility=PASS | pending_to_verified=PASS\n";
    echo "PASS | award_creation=PASS | recognition=Dr5500/Cr2400 {$amount} {$currency} | duplicate_recognition=PASS\n";
    echo "PASS | rollback_cleanup=PASS | policy={$policyVersionId} | employee={$employeeId}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | HR incentive verification rollback tests | " . $e->getMessage() . "\n";
    exit(1);
}
