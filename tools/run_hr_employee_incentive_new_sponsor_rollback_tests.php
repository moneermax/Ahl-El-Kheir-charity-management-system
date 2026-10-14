<?php
declare(strict_types=1);

/**
 * Rollback-only test for new-sponsor incentive qualification.
 * Requires the incentive ledger, catalogue, recipient snapshot and account migrations.
 * It uses an existing sponsor with no approved monthly-sponsorship payment history.
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/hr/lib_employee_incentives.php';

$pdo = db();

function newSponsorTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function newSponsorTestOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function newSponsorTestCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$currency = strtoupper(trim((string)APP_CURRENCY_CODE));
$recipient = newSponsorTestOne(
    $pdo,
    "SELECT s.id AS sponsor_id, s.brought_by_user_id, s.created_at,
            MIN(e.id) AS employee_id
     FROM sponsors s
     JOIN users u ON u.id = s.brought_by_user_id AND u.is_active = 1
     JOIN employees e ON e.user_id = u.id
     WHERE s.brought_by_user_id IS NOT NULL
       AND s.created_at IS NOT NULL
       AND NOT EXISTS (
           SELECT 1 FROM sponsor_payments sp
           LEFT JOIN sponsorships sx ON sx.id = sp.sponsorship_id
           WHERE sp.payment_type = 'monthly_sponsorship'
             AND sp.status = 'approved'
             AND COALESCE(sp.sponsor_id, sx.sponsor_id) = s.id
       )
       AND NOT EXISTS (
           SELECT 1 FROM winback_campaigns wc
           WHERE wc.sponsor_id = s.id AND wc.status = 'persuaded'
       )
     GROUP BY s.id, s.brought_by_user_id, s.created_at
     HAVING COUNT(e.id) = 1
     ORDER BY s.id
     LIMIT 1"
);
$reviewer = $recipient ? newSponsorTestOne(
    $pdo,
    'SELECT id FROM users WHERE id > 0 AND id <> ? ORDER BY id LIMIT 1',
    [(int)$recipient['brought_by_user_id']]
) : null;
$accounts = newSponsorTestOne(
    $pdo,
    "SELECT
        (SELECT id FROM accounts WHERE code = '1100' AND is_active = 1 LIMIT 1) AS cash_id,
        (SELECT id FROM accounts WHERE code = '4100' AND is_active = 1 LIMIT 1) AS revenue_id"
);
if (!$recipient || !$reviewer || !$accounts || (int)$accounts['cash_id'] <= 0 ||
    (int)$accounts['revenue_id'] <= 0 || !preg_match('/^[A-Z]{3}$/', $currency)) {
    echo "SKIP | New-sponsor incentive rollback test | no safely eligible sponsor with unique employee attribution and no prior approved monthly payment, reviewer, accounts, or valid currency.\n";
    exit(0);
}

$actorUserId = (int)$recipient['brought_by_user_id'];
$employeeId = (int)$recipient['employee_id'];
$sponsorId = (int)$recipient['sponsor_id'];
$reviewerUserId = (int)$reviewer['id'];
$versionNo = (int)$pdo->query('SELECT COALESCE(MAX(version_no),0) + 1 FROM hr_incentive_policy_versions')->fetchColumn();
$token = strtoupper(bin2hex(random_bytes(6)));
$policyId = $eventId = 0;
$paymentIds = [];
$transactionIds = [];
$journalIds = [];

try {
    $pdo->beginTransaction();

    $policy = $pdo->prepare(
        "INSERT INTO hr_incentive_policy_versions
         (version_no, policy_name, status, effective_from, agreement_recorded_at,
          agreement_notes, created_by_user_id, activated_by_user_id, activated_at)
         VALUES (?, ?, 'active', CURDATE(), NOW(), ?, ?, ?, NOW())"
    );
    $policy->execute([
        $versionNo,
        'ROLLBACK TEST - New sponsor ' . $token,
        'Temporary new-sponsor source-event fixture; transaction rolls back.',
        $actorUserId,
        $actorUserId,
    ]);
    $policyId = (int)$pdo->lastInsertId();

    $rule = $pdo->prepare(
        "INSERT INTO hr_incentive_policy_rules
         (policy_version_id, incentive_type, reward_amount, currency_code, is_enabled, rule_notes)
         VALUES (?, 'new_sponsor', 35.00, ?, 1, 'Rollback-only new-sponsor qualification')"
    );
    $rule->execute([$policyId, $currency]);

    $transactionInsert = $pdo->prepare(
        "INSERT INTO transactions
         (sponsorship_id, amount, currency_code, payment_method, transaction_date,
          receipt_number, description, months_covered, transaction_type,
          receipt_path, unified_receipt_path, status, created_by, transaction_code,
          sponsor_id, project_id, payment_period, purpose_note, purpose,
          admin_fee_percent, admin_fee_amount, net_amount, admin_fee_method,
          admin_fee_value, admin_fee_policy_id)
         VALUES (NULL, ?, ?, 'cash', ?, ?, ?, 1.00, 'sponsorship_payment',
                 NULL, NULL, 'posted', ?, ?, ?, NULL, ?, NULL, 'monthly_sponsorship',
                 0.00, 0.00, ?, 'none', 0.00, NULL)"
    );
    $paymentInsert = $pdo->prepare(
        "INSERT INTO sponsor_payments
         (payment_type, sponsorship_id, sponsor_id, supervisor_id, payment_period,
          amount, currency_code, status, reviewed_by_user_id, reviewed_at,
          transaction_id, created_at)
         VALUES ('monthly_sponsorship', NULL, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?)"
    );

    $makePayment = static function (string $suffix) use (
        $pdo, $transactionInsert, $paymentInsert, $sponsorId, $actorUserId,
        $reviewerUserId, $currency, $token, &$paymentIds, &$transactionIds, &$journalIds
    ): int {
        $now = new DateTimeImmutable();
        $amount = 100.00;
        $stamp = $now->format('Y-m-d H:i:s');
        $code = 'NSW-' . $token . '-' . $suffix;
        $transactionInsert->execute([
            $amount, $currency, $now->format('Y-m-d'), $code,
            'Rollback-only new-sponsor test ' . $suffix,
            $reviewerUserId, $code, $sponsorId, $now->format('M/Y'), $amount,
        ]);
        $transactionId = (int)$pdo->lastInsertId();
        $transactionIds[] = $transactionId;

        $entry = $pdo->prepare(
            "INSERT INTO journal_entries
             (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
             VALUES (?, ?, ?, 'transaction', ?, 'posted', ?)"
        );
        $entry->execute([
            'JE-NSW-' . $token . '-' . $suffix,
            $now->format('Y-m-d'),
            'Rollback-only confirmed new-sponsor payment ' . $suffix,
            $transactionId,
            $reviewerUserId,
        ]);
        $journalId = (int)$pdo->lastInsertId();
        $journalIds[] = $journalId;

        $lines = $pdo->prepare(
            "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
             VALUES (?, ?, 100.00, 0.00, ?), (?, ?, 0.00, 100.00, ?)"
        );
        $cashId = (int)newSponsorTestOne($pdo, "SELECT id FROM accounts WHERE code='1100' AND is_active=1 LIMIT 1")['id'];
        $revenueId = (int)newSponsorTestOne($pdo, "SELECT id FROM accounts WHERE code='4100' AND is_active=1 LIMIT 1")['id'];
        $lines->execute([
            $journalId, $cashId, 'Rollback test cash',
            $journalId, $revenueId, 'Rollback test sponsorship revenue',
        ]);

        $paymentInsert->execute([
            $sponsorId, $actorUserId, $now->format('M/Y'), $amount, $currency,
            $reviewerUserId, $stamp, $transactionId, $stamp,
        ]);
        $paymentId = (int)$pdo->lastInsertId();
        $paymentIds[] = $paymentId;
        return $paymentId;
    };

    $firstPaymentId = $makePayment('FIRST');
    $eventId = hrIncentiveRecordNewSponsorPaymentEvent($pdo, $firstPaymentId, $reviewerUserId);
    newSponsorTestAssert($eventId > 0, 'First financially confirmed payment did not record a new-sponsor event.');

    $event = newSponsorTestOne(
        $pdo,
        "SELECT source_record_id, incentive_type, status, recipient_user_id,
                recipient_employee_id, created_by_user_id
         FROM hr_incentive_events WHERE id = ?",
        [$eventId]
    );
    newSponsorTestAssert(
        $event !== null &&
        (int)$event['source_record_id'] === $firstPaymentId &&
        $event['incentive_type'] === 'new_sponsor' &&
        $event['status'] === 'pending_verification' &&
        (int)$event['recipient_user_id'] === $actorUserId &&
        (int)$event['recipient_employee_id'] === $employeeId &&
        (int)$event['created_by_user_id'] === $reviewerUserId,
        'New-sponsor event did not preserve recipient and confirmation evidence.'
    );
    newSponsorTestAssert(
        newSponsorTestCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_awards WHERE event_id = ?', [$eventId]) === 0,
        'Source event created an award before HR Manager verification.'
    );

    $secondPaymentId = $makePayment('SECOND');
    $secondEventId = hrIncentiveRecordNewSponsorPaymentEvent($pdo, $secondPaymentId, $reviewerUserId);
    newSponsorTestAssert($secondEventId === 0, 'A subsequent confirmed payment incorrectly qualified as a new-sponsor achievement.');

    $pdo->rollBack();

    foreach ($paymentIds as $id) {
        newSponsorTestAssert(newSponsorTestCount($pdo, 'SELECT COUNT(*) FROM sponsor_payments WHERE id = ?', [$id]) === 0, 'Rollback left a test payment.');
    }
    foreach ($transactionIds as $id) {
        newSponsorTestAssert(newSponsorTestCount($pdo, 'SELECT COUNT(*) FROM transactions WHERE id = ?', [$id]) === 0, 'Rollback left a test transaction.');
    }
    foreach ($journalIds as $id) {
        newSponsorTestAssert(newSponsorTestCount($pdo, 'SELECT COUNT(*) FROM journal_entries WHERE id = ?', [$id]) === 0, 'Rollback left a test journal.');
    }
    newSponsorTestAssert(newSponsorTestCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_events WHERE id = ?', [$eventId]) === 0, 'Rollback left the source event.');
    newSponsorTestAssert(newSponsorTestCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_policy_versions WHERE id = ?', [$policyId]) === 0, 'Rollback left the policy fixture.');

    echo "PASS | new_sponsor=first_confirmed_payment | recipient_snapshot=PASS\n";
    echo "PASS | later_payment_rejected=PASS | no_award_before_HR_verification=PASS\n";
    echo "PASS | rollback_cleanup=PASS | sponsor={$sponsorId} | employee={$employeeId}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | New-sponsor incentive rollback test | " . $e->getMessage() . "\n";
    exit(1);
}
