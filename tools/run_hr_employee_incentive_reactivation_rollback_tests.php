<?php
declare(strict_types=1);

/**
 * Rollback-only test for financially confirmed sponsor reactivation qualification.
 * Requires the recipient snapshot and win-back attribution migrations.
 * All policy, campaign, payment, transaction, journal and event fixtures roll back.
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/hr/lib_employee_incentives.php';

$pdo = db();

function reactivationTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function reactivationTestOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function reactivationTestCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$currency = strtoupper(trim((string)APP_CURRENCY_CODE));
$recipient = reactivationTestOne(
    $pdo,
    "SELECT e.id AS employee_id, e.user_id
     FROM employees e JOIN users u ON u.id = e.user_id
     WHERE e.user_id > 0 ORDER BY e.id LIMIT 1"
);
$sponsor = reactivationTestOne($pdo, 'SELECT id FROM sponsors WHERE id > 0 ORDER BY id LIMIT 1');
$reviewer = $recipient ? reactivationTestOne(
    $pdo,
    'SELECT id FROM users WHERE id > 0 AND id <> ? ORDER BY id LIMIT 1',
    [(int)$recipient['user_id']]
) : null;
$accounts = reactivationTestOne(
    $pdo,
    "SELECT
        (SELECT id FROM accounts WHERE code = '1100' AND is_active = 1 LIMIT 1) AS cash_id,
        (SELECT id FROM accounts WHERE code = '4100' AND is_active = 1 LIMIT 1) AS revenue_id"
);
if (!$recipient || !$reviewer || !$sponsor || !$accounts || (int)$accounts['cash_id'] <= 0 ||
    (int)$accounts['revenue_id'] <= 0 || !preg_match('/^[A-Z]{3}$/', $currency)) {
    echo "SKIP | Reactivation incentive rollback test | employee-linked user, distinct reviewer, sponsor, active cash/revenue accounts, or currency unavailable.\n";
    exit(0);
}

$versionNo = (int)$pdo->query('SELECT COALESCE(MAX(version_no),0) + 1 FROM hr_incentive_policy_versions')->fetchColumn();
$token = strtoupper(bin2hex(random_bytes(6)));
$actorUserId = (int)$recipient['user_id'];
$reviewerUserId = (int)$reviewer['id'];
$employeeId = (int)$recipient['employee_id'];
$sponsorId = (int)$sponsor['id'];
$now = new DateTimeImmutable();
$reactivatedAt = $now->modify('-100 days');
$priorConfirmedAt = $reactivatedAt->modify('-91 days');
$newPaymentCreatedAt = $reactivatedAt->modify('+1 day');
$policyId = $campaignId = $priorTransactionId = $newTransactionId = $priorPaymentId = $newPaymentId = $eventId = 0;
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
        'ROLLBACK TEST - Reactivation qualification ' . $token,
        'Temporary policy fixture; transaction rolls back.',
        $actorUserId,
        $actorUserId,
    ]);
    $policyId = (int)$pdo->lastInsertId();

    $rule = $pdo->prepare(
        "INSERT INTO hr_incentive_policy_rules
         (policy_version_id, incentive_type, reward_amount, currency_code, is_enabled, rule_notes)
         VALUES (?, 'reactivation', 35.00, ?, 1, 'Rollback-only reactivation qualification')"
    );
    $rule->execute([$policyId, $currency]);

    $campaign = $pdo->prepare(
        "INSERT INTO winback_campaigns
         (sponsor_id, handled_by, reactivated_by_user_id, reactivated_at, status, closed_at)
         VALUES (?, ?, ?, ?, 'persuaded', ?)"
    );
    $campaign->execute([
        $sponsorId,
        $actorUserId,
        $actorUserId,
        $reactivatedAt->format('Y-m-d H:i:s'),
        $reactivatedAt->format('Y-m-d H:i:s'),
    ]);
    $campaignId = (int)$pdo->lastInsertId();

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

    $makeConfirmedPayment = static function (
        DateTimeImmutable $createdAt,
        DateTimeImmutable $reviewedAt,
        string $suffix
    ) use (
        $pdo, $transactionInsert, $paymentInsert, $sponsorId, $actorUserId, $reviewerUserId,
        $currency, $token, &$journalIds
    ): array {
        $amount = 100.00;
        $code = 'RWT-' . $token . '-' . $suffix;
        $transactionInsert->execute([
            $amount,
            $currency,
            $createdAt->format('Y-m-d'),
            $code,
            'Rollback-only reactivation test ' . $suffix,
            $reviewerUserId,
            $code,
            $sponsorId,
            $createdAt->format('M/Y'),
            $amount,
        ]);
        $transactionId = (int)$pdo->lastInsertId();

        $entryCode = 'JE-RWT-' . $token . '-' . $suffix;
        $entry = $pdo->prepare(
            "INSERT INTO journal_entries
             (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
             VALUES (?, ?, ?, 'transaction', ?, 'posted', ?)"
        );
        $entry->execute([
            $entryCode,
            $createdAt->format('Y-m-d'),
            'Rollback-only confirmed sponsor payment ' . $suffix,
            $transactionId,
            $actorUserId,
        ]);
        $journalId = (int)$pdo->lastInsertId();
        $journalIds[] = $journalId;

        $lines = $pdo->prepare(
            "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
             VALUES (?, ?, 100.00, 0.00, ?), (?, ?, 0.00, 100.00, ?)"
        );
        $cashId = (int)reactivationTestOne($pdo, "SELECT id FROM accounts WHERE code='1100' AND is_active=1 LIMIT 1")['id'];
        $revenueId = (int)reactivationTestOne($pdo, "SELECT id FROM accounts WHERE code='4100' AND is_active=1 LIMIT 1")['id'];
        $lines->execute([
            $journalId, $cashId, 'Rollback test cash',
            $journalId, $revenueId, 'Rollback test sponsorship revenue',
        ]);

        $paymentInsert->execute([
            $sponsorId,
            $actorUserId,
            $createdAt->format('M/Y'),
            $amount,
            $currency,
            $reviewerUserId,
            $reviewedAt->format('Y-m-d H:i:s'),
            $transactionId,
            $createdAt->format('Y-m-d H:i:s'),
        ]);
        return ['transaction_id' => $transactionId, 'payment_id' => (int)$pdo->lastInsertId()];
    };

    $prior = $makeConfirmedPayment($priorConfirmedAt, $priorConfirmedAt, 'OLD');
    $priorTransactionId = $prior['transaction_id'];
    $priorPaymentId = $prior['payment_id'];

    $new = $makeConfirmedPayment($newPaymentCreatedAt, $now, 'NEW');
    $newTransactionId = $new['transaction_id'];
    $newPaymentId = $new['payment_id'];

    $eventId = hrIncentiveRecordReactivationPaymentEvent($pdo, $newPaymentId, $reviewerUserId);
    reactivationTestAssert($eventId > 0, 'Qualifying first confirmed payment did not record a reactivation event.');

    $event = reactivationTestOne(
        $pdo,
        "SELECT source_record_id, incentive_type, status, recipient_user_id,
                recipient_employee_id, created_by_user_id, occurred_at
         FROM hr_incentive_events WHERE id = ?",
        [$eventId]
    );
    reactivationTestAssert(
        $event !== null &&
        (int)$event['source_record_id'] === $newPaymentId &&
        $event['incentive_type'] === 'reactivation' &&
        $event['status'] === 'pending_verification' &&
        (int)$event['recipient_user_id'] === $actorUserId &&
        (int)$event['recipient_employee_id'] === $employeeId &&
        (int)$event['created_by_user_id'] === $reviewerUserId &&
        $event['occurred_at'] === $now->format('Y-m-d H:i:s'),
        'Reactivation event did not preserve payment, recipient, creator, and confirmation-time evidence.'
    );

    $repeatId = hrIncentiveRecordReactivationPaymentEvent($pdo, $newPaymentId, $reviewerUserId);
    reactivationTestAssert($repeatId === $eventId, 'Repeated processing did not return the existing event.');
    reactivationTestAssert(
        reactivationTestCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_events WHERE id = ?', [$eventId]) === 1,
        'Repeated processing created a duplicate event.'
    );
    reactivationTestAssert(
        reactivationTestCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_awards WHERE event_id = ?', [$eventId]) === 0,
        'Reactivation source event created an award before HR verification.'
    );

    $pdo->rollBack();

    foreach ([
        ['hr_incentive_events', $eventId],
        ['hr_incentive_policy_versions', $policyId],
        ['winback_campaigns', $campaignId],
        ['sponsor_payments', $priorPaymentId],
        ['sponsor_payments', $newPaymentId],
        ['transactions', $priorTransactionId],
        ['transactions', $newTransactionId],
        ['journal_entries', $journalIds[0] ?? 0],
        ['journal_entries', $journalIds[1] ?? 0],
    ] as [$table, $id]) {
        if ($id > 0) {
            reactivationTestAssert(
                reactivationTestCount($pdo, "SELECT COUNT(*) FROM {$table} WHERE id = ?", [(int)$id]) === 0,
                "Rollback left test data in {$table}."
            );
        }
    }

    echo "PASS | reactivation=90_day_lapse_and_confirmed_payment | recipient_snapshot=PASS\n";
    echo "PASS | first_payment_idempotency=PASS | no_award_before_HR_verification=PASS | rollback_cleanup=PASS\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | Reactivation incentive rollback test | " . $e->getMessage() . "\n";
    exit(1);
}
