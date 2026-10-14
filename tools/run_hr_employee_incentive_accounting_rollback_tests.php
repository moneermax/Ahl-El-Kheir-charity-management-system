<?php
declare(strict_types=1);

/**
 * Employee incentive accounting integration — rollback-only verification.
 *
 * Uses one existing draft payroll and an existing policy version. All event,
 * award, journal, and payroll mutations run inside one outer transaction and
 * are rolled back. This harness performs no DDL and never commits fixtures.
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_employee_incentives.php';
require_once __DIR__ . '/../modules/hr/lib_payroll_accounting.php';

$pdo = db();

function incentiveTestSkip(string $reason): never
{
    echo "SKIP | Employee incentive accounting rollback tests | {$reason}\n";
    exit(0);
}

function incentiveTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function incentiveTestOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function incentiveTestCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$currency = strtoupper(trim((string)APP_CURRENCY_CODE));
if (!preg_match('/^[A-Z]{3}$/', $currency)) {
    incentiveTestSkip('APP_CURRENCY_CODE is not a three-letter currency code.');
}

$policy = incentiveTestOne(
    $pdo,
    "SELECT id FROM hr_incentive_policy_versions
     WHERE status IN ('approved','active','retired')
     ORDER BY CASE status WHEN 'approved' THEN 0 WHEN 'active' THEN 1 ELSE 2 END, id DESC
     LIMIT 1"
);
if (!$policy) {
    incentiveTestSkip('No existing approved/active/retired incentive policy version is available; no policy was created.');
}

$fixture = incentiveTestOne(
    $pdo,
    "SELECT p.id AS payroll_id, p.employee_id, p.month, p.year,
            p.basic_salary, p.allowances, p.overtime, p.deductions,
            p.attendance_deduction, p.salary_advance_deduction,
            p.net_salary, p.status AS payroll_status,
            e.user_id AS recipient_user_id,
            s.salary_currency
     FROM payroll p
     JOIN employees e ON e.id = p.employee_id
     JOIN hr_employee_salary_history s ON s.id = (
         SELECT s2.id FROM hr_employee_salary_history s2
         WHERE s2.employee_id = p.employee_id
           AND s2.effective_from <= LAST_DAY(STR_TO_DATE(CONCAT(p.year,'-',LPAD(p.month,2,'0'),'-01'),'%Y-%m-%d'))
           AND (s2.effective_to IS NULL OR s2.effective_to >= LAST_DAY(STR_TO_DATE(CONCAT(p.year,'-',LPAD(p.month,2,'0'),'-01'),'%Y-%m-%d')))
         ORDER BY s2.effective_from DESC, s2.id DESC LIMIT 1
     )
     WHERE p.status = 'draft'
       AND p.basic_salary > 0
       AND COALESCE(p.incentive_amount, 0) = 0
       AND NOT EXISTS (
           SELECT 1 FROM hr_incentive_awards existing_payroll_award
           WHERE existing_payroll_award.payroll_id = p.id
       )
       AND NOT EXISTS (
           SELECT 1
           FROM hr_incentive_awards existing_eligible_award
           JOIN hr_incentive_events existing_event ON existing_event.id = existing_eligible_award.event_id
           WHERE existing_eligible_award.employee_id = p.employee_id
             AND existing_eligible_award.payroll_id IS NULL
             AND existing_eligible_award.status IN ('earned','held_currency')
             AND existing_event.status = 'verified'
       )
       AND e.user_id IS NOT NULL AND e.user_id > 0
       AND UPPER(TRIM(s.salary_currency)) = ?
       AND NOT EXISTS (
           SELECT 1 FROM journal_entries je
           WHERE je.reference_type = 'payroll'
             AND je.reference_id = p.id
             AND je.status = 'posted'
       )
       AND NOT EXISTS (
           SELECT 1 FROM journal_entries je
           WHERE je.entry_code = CONCAT('PAY-', p.id)
       )
     ORDER BY p.year DESC, p.month DESC, p.id DESC
     LIMIT 1",
    [$currency]
);
if (!$fixture) {
    incentiveTestSkip('No draft payroll fixture with matching salary currency and no existing payroll journal is available.');
}

$accountRows = $pdo->query(
    "SELECT code, id, account_type, is_active FROM accounts WHERE code IN ('5200','1200','1410','2400','5500')"
)->fetchAll(PDO::FETCH_ASSOC);
$accounts = [];
foreach ($accountRows as $account) {
    $accounts[(string)$account['code']] = $account;
}
foreach (['5200', '1200', '2400', '5500'] as $code) {
    if (!isset($accounts[$code]) || (int)$accounts[$code]['is_active'] !== 1) {
        incentiveTestSkip("Required account {$code} is missing or inactive.");
    }
}
if ((float)$fixture['salary_advance_deduction'] > 0 && (!isset($accounts['1410']) || (int)$accounts['1410']['is_active'] !== 1)) {
    incentiveTestSkip('Payroll fixture has a salary-advance deduction but active account 1410 is missing.');
}
if ($accounts['2400']['account_type'] !== 'liability' || $accounts['5500']['account_type'] !== 'expense') {
    incentiveTestSkip('Incentive accounts 2400/5500 do not have the expected liability/expense types.');
}

$payrollId = (int)$fixture['payroll_id'];
$employeeId = (int)$fixture['employee_id'];
$recipientUserId = (int)$fixture['recipient_user_id'];
$policyId = (int)$policy['id'];
$awardAmount = 123.45;
$mismatchCurrency = $currency === 'USD' ? 'SAR' : 'USD';
$eventIds = [];
$awardIds = [];
$journalIds = [];
$payrollBefore = incentiveTestOne(
    $pdo,
    "SELECT status, incentive_amount, accounting_entry_id, accounting_status,
            net_salary, salary_advance_deduction
     FROM payroll WHERE id = ?",
    [$payrollId]
);
$beforeRecognitionCount = incentiveTestCount(
    $pdo,
    "SELECT COUNT(*) FROM journal_entries
     WHERE reference_type = 'hr_incentive_award_recognition'"
);
$beforeSettlementCount = incentiveTestCount(
    $pdo,
    "SELECT COUNT(*) FROM journal_entries
     WHERE reference_type = 'payroll' AND reference_id = ?",
    [$payrollId]
);

try {
    $pdo->beginTransaction();

    $eventInsert = $pdo->prepare(
        "INSERT INTO hr_incentive_events
            (idempotency_key, source_table, source_record_id, incentive_type,
             policy_version_id, occurred_at, status, verification_note)
         VALUES (?, 'rollback_test', ?, 'collection', ?, NOW(), 'verified', 'Rollback-only accounting verification')"
    );
    $awardInsert = $pdo->prepare(
        "INSERT INTO hr_incentive_awards
            (event_id, employee_id, recipient_user_id, reward_amount,
             reward_currency_code, status, earned_at)
         VALUES (?, ?, ?, ?, ?, 'earned', NOW())"
    );

    $eventInsert->execute(['rollback-incentive-' . bin2hex(random_bytes(12)), $payrollId, $policyId]);
    $eventId = (int)$pdo->lastInsertId();
    $eventIds[] = $eventId;
    $awardInsert->execute([$eventId, $employeeId, $recipientUserId, $awardAmount, $currency]);
    $awardId = (int)$pdo->lastInsertId();
    $awardIds[] = $awardId;

    $recognitionId = hrIncentiveRecognizeAward($pdo, $awardId, $recipientUserId);
    incentiveTestAssert($recognitionId > 0, 'Verified award did not create a recognition journal.');
    $journalIds[] = $recognitionId;

    $recognition = incentiveTestOne(
        $pdo,
        "SELECT je.status, je.reference_type, je.reference_id,
                COUNT(jl.id) AS line_count,
                ROUND(COALESCE(SUM(jl.debit),0),2) AS debit_total,
                ROUND(COALESCE(SUM(jl.credit),0),2) AS credit_total,
                ROUND(COALESCE(SUM(CASE WHEN jl.account_id = ? THEN jl.debit ELSE 0 END),0),2) AS expense_debit,
                ROUND(COALESCE(SUM(CASE WHEN jl.account_id = ? THEN jl.credit ELSE 0 END),0),2) AS payable_credit
         FROM journal_entries je
         LEFT JOIN journal_lines jl ON jl.entry_id = je.id
         WHERE je.id = ?
         GROUP BY je.id",
        [(int)$accounts['5500']['id'], (int)$accounts['2400']['id'], $recognitionId]
    );
    incentiveTestAssert(
        $recognition !== null &&
        $recognition['status'] === 'posted' &&
        $recognition['reference_type'] === 'hr_incentive_award_recognition' &&
        (int)$recognition['reference_id'] === $awardId &&
        (int)$recognition['line_count'] === 2 &&
        (float)$recognition['debit_total'] === $awardAmount &&
        (float)$recognition['credit_total'] === $awardAmount &&
        (float)$recognition['expense_debit'] === $awardAmount &&
        (float)$recognition['payable_credit'] === $awardAmount,
        'Recognition journal is not exactly Dr 5500 / Cr 2400 for the award amount.'
    );

    $recognitionCountAfterFirst = incentiveTestCount(
        $pdo,
        "SELECT COUNT(*) FROM journal_entries
         WHERE reference_type = 'hr_incentive_award_recognition' AND reference_id = ? AND status = 'posted'",
        [$awardId]
    );
    $recognitionAgainId = hrIncentiveRecognizeAward($pdo, $awardId, $recipientUserId);
    $recognitionCountAfterRepeat = incentiveTestCount(
        $pdo,
        "SELECT COUNT(*) FROM journal_entries
         WHERE reference_type = 'hr_incentive_award_recognition' AND reference_id = ? AND status = 'posted'",
        [$awardId]
    );
    incentiveTestAssert(
        $recognitionAgainId === $recognitionId &&
        $recognitionCountAfterFirst === 1 &&
        $recognitionCountAfterRepeat === 1,
        'Repeated recognition created or returned a duplicate journal.'
    );

    $mismatchEventInsert = $pdo->prepare(
        "INSERT INTO hr_incentive_events
            (idempotency_key, source_table, source_record_id, incentive_type,
             policy_version_id, occurred_at, status, verification_note)
         VALUES (?, 'rollback_test', ?, 'collection', ?, NOW(), 'verified', 'Rollback-only currency hold verification')"
    );
    $mismatchEventInsert->execute(['rollback-incentive-currency-' . bin2hex(random_bytes(12)), $payrollId, $policyId]);
    $mismatchEventId = (int)$pdo->lastInsertId();
    $eventIds[] = $mismatchEventId;
    $awardInsert->execute([$mismatchEventId, $employeeId, $recipientUserId, 77.77, $mismatchCurrency]);
    $mismatchAwardId = (int)$pdo->lastInsertId();
    $awardIds[] = $mismatchAwardId;

    incentiveTestAssert($pdo->inTransaction(), 'Harness lost its outer transaction before currency-hold test.');
    $mismatchRecognitionId = hrIncentiveRecognizeAward($pdo, $mismatchAwardId, $recipientUserId);
    incentiveTestAssert($pdo->inTransaction(), 'Recognition helper ended the caller-owned transaction on currency mismatch.');
    $mismatchAward = incentiveTestOne(
        $pdo,
        "SELECT status, hold_reason, recognition_journal_entry_id
         FROM hr_incentive_awards WHERE id = ?",
        [$mismatchAwardId]
    );
    incentiveTestAssert(
        $mismatchRecognitionId === 0 &&
        $mismatchAward !== null &&
        $mismatchAward['status'] === 'held_currency' &&
        trim((string)$mismatchAward['hold_reason']) !== '' &&
        $mismatchAward['recognition_journal_entry_id'] === null,
        'Currency mismatch did not persist a held award without a recognition journal.'
    );

    hrIncentiveRefreshPayrollDraft($pdo, $payrollId);
    $scheduled = incentiveTestOne(
        $pdo,
        "SELECT a.status, a.payroll_id, a.recognition_journal_entry_id,
                p.incentive_amount, p.status AS payroll_status
         FROM hr_incentive_awards a
         JOIN payroll p ON p.id = a.payroll_id
         WHERE a.id = ?",
        [$awardId]
    );
    incentiveTestAssert(
        $scheduled !== null &&
        $scheduled['status'] === 'scheduled' &&
        (int)$scheduled['payroll_id'] === $payrollId &&
        (int)$scheduled['recognition_journal_entry_id'] === $recognitionId &&
        $scheduled['payroll_status'] === 'draft' &&
        (float)$scheduled['incentive_amount'] === $awardAmount,
        'Matching-currency earned award was not scheduled into the draft payroll with the correct amount.'
    );
    hrIncentiveRefreshPayrollDraft($pdo, $payrollId);
    incentiveTestAssert(
        incentiveTestCount(
            $pdo,
            "SELECT COUNT(*) FROM hr_incentive_awards WHERE payroll_id = ? AND status = 'scheduled'",
            [$payrollId]
        ) === 1,
        'Repeated payroll refresh duplicated or altered the scheduled award set.'
    );

    $payrollUpdate = $pdo->prepare(
        "UPDATE payroll SET status = 'paid', payment_date = CURDATE() WHERE id = ? AND status = 'draft'"
    );
    $payrollUpdate->execute([$payrollId]);
    incentiveTestAssert($payrollUpdate->rowCount() === 1, 'Could not move the rollback-only payroll fixture to the paid transition boundary.');

    $payrollForPosting = incentiveTestOne(
        $pdo,
        "SELECT p.*, e.full_name AS employee_name, e.employee_code
         FROM payroll p JOIN employees e ON e.id = p.employee_id
         WHERE p.id = ? LIMIT 1",
        [$payrollId]
    );
    incentiveTestAssert($payrollForPosting !== null, 'Payroll fixture disappeared before accounting posting.');
    $settlementId = hrPayrollPostAccounting($pdo, $payrollForPosting);
    $journalIds[] = $settlementId;

    $settlement = incentiveTestOne(
        $pdo,
        "SELECT je.status, je.reference_type, je.reference_id,
                COUNT(jl.id) AS line_count,
                ROUND(COALESCE(SUM(jl.debit),0),2) AS debit_total,
                ROUND(COALESCE(SUM(jl.credit),0),2) AS credit_total,
                ROUND(COALESCE(SUM(CASE WHEN jl.account_id = ? THEN jl.debit ELSE 0 END),0),2) AS payable_debit,
                ROUND(COALESCE(SUM(CASE WHEN jl.account_id = ? THEN jl.debit ELSE 0 END),0),2) AS salary_expense_debit,
                ROUND(COALESCE(SUM(CASE WHEN jl.account_id = ? THEN jl.credit ELSE 0 END),0),2) AS bank_credit,
                ROUND(COALESCE(SUM(CASE WHEN jl.account_id = ? THEN jl.credit ELSE 0 END),0),2) AS advance_credit
         FROM journal_entries je
         JOIN journal_lines jl ON jl.entry_id = je.id
         WHERE je.id = ?
         GROUP BY je.id",
        [(int)$accounts['2400']['id'], (int)$accounts['5200']['id'], (int)$accounts['1200']['id'], (int)($accounts['1410']['id'] ?? 0), $settlementId]
    );
    $netSalary = round((float)$fixture['net_salary'], 2);
    $advanceDeduction = round((float)$fixture['salary_advance_deduction'], 2);
    incentiveTestAssert(
        $settlement !== null &&
        $settlement['status'] === 'posted' &&
        $settlement['reference_type'] === 'payroll' &&
        (int)$settlement['reference_id'] === $payrollId &&
        (float)$settlement['debit_total'] === round($netSalary + $advanceDeduction + $awardAmount, 2) &&
        (float)$settlement['credit_total'] === round($netSalary + $advanceDeduction + $awardAmount, 2) &&
        (float)$settlement['payable_debit'] === $awardAmount &&
        (float)$settlement['salary_expense_debit'] === round($netSalary + $advanceDeduction, 2) &&
        (float)$settlement['bank_credit'] === round($netSalary + $awardAmount, 2) &&
        (float)$settlement['advance_credit'] === $advanceDeduction,
        'Payroll settlement journal did not preserve separate incentive, salary, bank, and salary-advance accounting.'
    );

    $paidAward = incentiveTestOne(
        $pdo,
        "SELECT status, payroll_id, settlement_journal_entry_id
         FROM hr_incentive_awards WHERE id = ?",
        [$awardId]
    );
    incentiveTestAssert(
        $paidAward !== null &&
        $paidAward['status'] === 'paid' &&
        (int)$paidAward['payroll_id'] === $payrollId &&
        (int)$paidAward['settlement_journal_entry_id'] === $settlementId,
        'Settled award did not retain paid status and settlement journal linkage.'
    );

    $paidIncentiveAmount = incentiveTestOne(
        $pdo,
        "SELECT status, incentive_amount, accounting_entry_id, accounting_status
         FROM payroll WHERE id = ?",
        [$payrollId]
    );
    incentiveTestAssert(
        $paidIncentiveAmount !== null &&
        $paidIncentiveAmount['status'] === 'paid' &&
        (float)$paidIncentiveAmount['incentive_amount'] === $awardAmount &&
        (int)$paidIncentiveAmount['accounting_entry_id'] === $settlementId &&
        $paidIncentiveAmount['accounting_status'] === 'posted',
        'Payroll settlement did not retain the incentive component and accounting reference.'
    );

    hrIncentiveRefreshPayrollDraft($pdo, $payrollId);
    $afterPaidRefresh = incentiveTestOne(
        $pdo,
        "SELECT status, incentive_amount FROM payroll WHERE id = ?",
        [$payrollId]
    );
    incentiveTestAssert(
        $afterPaidRefresh !== null &&
        $afterPaidRefresh['status'] === 'paid' &&
        (float)$afterPaidRefresh['incentive_amount'] === $awardAmount,
        'Draft refresh changed a paid payroll or its incentive component.'
    );

    incentiveTestAssert($pdo->inTransaction(), 'Harness transaction ended before final rollback.');
    $pdo->rollBack();

    $afterRollbackPayroll = incentiveTestOne(
        $pdo,
        "SELECT status, incentive_amount, accounting_entry_id, accounting_status,
                net_salary, salary_advance_deduction
         FROM payroll WHERE id = ?",
        [$payrollId]
    );
    incentiveTestAssert(
        $afterRollbackPayroll !== null &&
        $afterRollbackPayroll['status'] === $payrollBefore['status'] &&
        (float)$afterRollbackPayroll['incentive_amount'] === (float)$payrollBefore['incentive_amount'] &&
        $afterRollbackPayroll['accounting_entry_id'] === $payrollBefore['accounting_entry_id'] &&
        $afterRollbackPayroll['accounting_status'] === $payrollBefore['accounting_status'] &&
        (float)$afterRollbackPayroll['net_salary'] === (float)$payrollBefore['net_salary'] &&
        (float)$afterRollbackPayroll['salary_advance_deduction'] === (float)$payrollBefore['salary_advance_deduction'],
        'Rollback did not restore the original payroll fields.'
    );

    foreach ($eventIds as $id) {
        incentiveTestAssert(
            incentiveTestCount($pdo, "SELECT COUNT(*) FROM hr_incentive_events WHERE id = ?", [$id]) === 0,
            "Rollback left incentive event {$id} behind."
        );
    }
    foreach ($awardIds as $id) {
        incentiveTestAssert(
            incentiveTestCount($pdo, "SELECT COUNT(*) FROM hr_incentive_awards WHERE id = ?", [$id]) === 0,
            "Rollback left incentive award {$id} behind."
        );
    }
    foreach ($journalIds as $id) {
        incentiveTestAssert(
            incentiveTestCount($pdo, "SELECT COUNT(*) FROM journal_entries WHERE id = ?", [$id]) === 0,
            "Rollback left journal entry {$id} behind."
        );
    }

    incentiveTestAssert(
        incentiveTestCount(
            $pdo,
            "SELECT COUNT(*) FROM journal_entries
             WHERE reference_type = 'hr_incentive_award_recognition'"
        ) === $beforeRecognitionCount,
        'Rollback changed the pre-existing recognition journal count.'
    );
    incentiveTestAssert(
        incentiveTestCount(
            $pdo,
            "SELECT COUNT(*) FROM journal_entries
             WHERE reference_type = 'payroll' AND reference_id = ?",
            [$payrollId]
        ) === $beforeSettlementCount,
        'Rollback changed the pre-existing payroll journal count.'
    );

    echo "PASS | recognition=Dr5500/Cr2400 {$awardAmount} {$currency} | duplicate_recognition=PASS | caller_transaction_currency_hold=PASS\n";
    echo "PASS | draft_refresh=PASS | repeated_refresh=PASS | settlement=balanced | salary_5200=preserved | advance_1410=preserved | award_paid_link=PASS\n";
    echo "PASS | rollback_cleanup=PASS | payroll={$payrollId} | employee={$employeeId} | policy={$policyId} | test_rows=2 awards=2 journals=2\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | Employee incentive accounting rollback tests | " . $e->getMessage() . "\n";
    exit(1);
}
