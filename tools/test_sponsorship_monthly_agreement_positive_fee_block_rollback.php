<?php
declare(strict_types=1);

/**
 * Monthly-agreement positive-service-fee posting guard — rollback-only.
 *
 * Confirms that a new-model sponsorship transaction with an actual positive
 * service-fee receipt is rejected before allocation or journal creation.
 * All fixture writes occur inside one transaction and are rolled back.
 * No DDL, account seeding, agreement updates, or permanent payment is performed.
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';

if (PHP_SAPI !== 'cli' || APP_ENV !== 'development') {
    fwrite(STDERR, "Monthly-agreement positive-service-fee guard test requires CLI and APP_ENV=development.\n");
    exit(1);
}

function spmaPositiveFeeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = db();
if ($pdo->inTransaction()) {
    throw new RuntimeException('Test requires a connection with no open transaction.');
}

$actorId = (int)$pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
spmaPositiveFeeAssert($actorId > 0, 'No existing user is available for the rollback-only fixture.');
$_SESSION['user_id'] = $actorId;

$fixture = dbFetchOne(
    "SELECT a.id AS agreement_id, a.sponsorship_id, a.covered_month,
            a.agreed_sponsorship_amount, a.agreed_service_fee_amount, a.currency_code,
            s.sponsorship_code, s.status AS sponsorship_status, s.start_date, s.end_date
     FROM sponsorship_monthly_agreements a
     JOIN sponsorships s ON s.id = a.sponsorship_id
     WHERE a.id = 1 AND a.sponsorship_id = 3508
       AND a.covered_month = '2026-11-01'
       AND a.agreed_sponsorship_amount = 10000.00
       AND a.agreed_service_fee_amount = 2000.00
       AND a.currency_code = 'SDG'
     LIMIT 1"
);
spmaPositiveFeeAssert((bool)$fixture, 'Preflighted agreement 1 / SH-003508 no longer matches the approved test fixture.');
spmaPositiveFeeAssert((string)$fixture['sponsorship_code'] === 'SH-003508'
    && (string)$fixture['sponsorship_status'] === 'active'
    && (string)$fixture['start_date'] <= '2026-11-01'
    && (empty($fixture['end_date']) || (string)$fixture['end_date'] >= '2026-11-30'),
    'The test sponsorship is no longer eligible for November 2026.');

$pauseCount = (int)dbFetchOne(
    "SELECT COUNT(*) AS c FROM sponsorship_pause_events WHERE sponsorship_id=?",
    [(int)$fixture['sponsorship_id']]
)['c'];
$allocationCount = (int)dbFetchOne(
    "SELECT COUNT(*) AS c FROM sponsorship_payment_allocations
     WHERE sponsorship_id=? AND covered_month='2026-11-01'",
    [(int)$fixture['sponsorship_id']]
)['c'];
$agreementTransactionCount = (int)dbFetchOne(
    "SELECT COUNT(*) AS c FROM transactions WHERE monthly_agreement_id=?",
    [(int)$fixture['agreement_id']]
)['c'];
$agreementSubmissionCount = (int)dbFetchOne(
    "SELECT COUNT(*) AS c FROM sponsor_payments WHERE monthly_agreement_id=?",
    [(int)$fixture['agreement_id']]
)['c'];

spmaPositiveFeeAssert($pauseCount === 0, 'The sponsorship now has pause history; refusing to use it as a fixture.');
spmaPositiveFeeAssert($allocationCount === 0, 'November already has an allocation; refusing to alter the month commitment.');
spmaPositiveFeeAssert($agreementTransactionCount === 0 && $agreementSubmissionCount === 0,
    'The agreement is already linked to a transaction or submission; refusing to reuse it.');

$transactionCode = 'TEST-SPMA-POSITIVE-FEE-BLOCK-' . bin2hex(random_bytes(6));
$transactionId = 0;
$blockedMessage = 'لا يمكن ترحيل رسوم الخدمة الإيجابية قبل اعتماد توقيت الاعتراف المحاسبي (فوري أو نهاية شهر التغطية) وتأكيد حساب الإيراد المعتمد. بقيت الدفعة بانتظار القرار المحاسبي.';

try {
    $pdo->beginTransaction();

    dbExecute(
        "INSERT INTO transactions
         (sponsorship_id, amount, currency_code, payment_method, transaction_date,
          description, months_covered, payment_period, purpose,
          admin_fee_percent, admin_fee_amount, net_amount, status, created_by,
          transaction_code, transaction_type, admin_fee_method, admin_fee_value,
          admin_fee_policy_id, component_model, monthly_agreement_id,
          agreed_sponsorship_amount, agreed_service_fee_amount,
          actual_sponsorship_received, actual_service_fee_received,
          service_fee_account_mapping_id, service_fee_account_id)
         VALUES (?, 12000.00, 'SDG', 'cash', CURDATE(),
          'Rollback-only monthly-agreement positive-service-fee posting guard test',
          1.00, 'November/2026', 'monthly_sponsorship',
          0.00, 0.00, 0.00, 'posted', ?,
          ?, 'sponsorship_payment', 'none', 0.00,
          NULL, 'monthly_agreement', ?,
          10000.00, 2000.00, 10000.00, 2000.00, NULL, NULL)",
        [(int)$fixture['sponsorship_id'], $actorId, $transactionCode, (int)$fixture['agreement_id']]
    );
    $transactionId = (int)dbLastInsertId();
    spmaPositiveFeeAssert($transactionId > 0, 'Could not insert the temporary posted transaction.');

    $caughtMessage = '';
    try {
        ak_post_transaction_journal_prepared($transactionId, $actorId, $actorId);
    } catch (RuntimeException $error) {
        $caughtMessage = $error->getMessage();
    }

    spmaPositiveFeeAssert($caughtMessage === $blockedMessage,
        $caughtMessage === ''
            ? 'Positive service-fee posting unexpectedly returned without rejection.'
            : 'Posting failed for an unexpected reason: ' . $caughtMessage);

    $allocation = dbFetchOne(
        "SELECT id FROM sponsorship_payment_allocations WHERE transaction_id=? LIMIT 1",
        [$transactionId]
    );
    $journal = dbFetchOne(
        "SELECT id FROM journal_entries WHERE reference_type='transaction' AND reference_id=? LIMIT 1",
        [$transactionId]
    );
    spmaPositiveFeeAssert(!$allocation && !$journal,
        'The rejected positive-fee transaction created an allocation or journal.');

    $pdo->rollBack();

    spmaPositiveFeeAssert(!(bool)dbFetchOne("SELECT id FROM transactions WHERE transaction_code=? LIMIT 1", [$transactionCode]),
        'Temporary transaction survived rollback.');
    spmaPositiveFeeAssert(!(bool)dbFetchOne("SELECT id FROM sponsorship_payment_allocations WHERE transaction_id=?", [$transactionId]),
        'Temporary allocation survived rollback.');
    spmaPositiveFeeAssert(!(bool)dbFetchOne(
        "SELECT id FROM journal_entries WHERE reference_type='transaction' AND reference_id=?",
        [$transactionId]
    ), 'Temporary journal header survived rollback.');

    echo "PASS | monthly_agreement=1 | sponsorship=SH-003508 | covered_month=2026-11 | actual_sponsorship=10000.00 | actual_service_fee=2000.00 | total=12000.00 | posting=BLOCKED_BY_ACCOUNTING_POLICY | allocation=NONE | journal=NONE | rollback=PASS\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL | monthly-agreement positive-service-fee posting guard | " . $error->getMessage() . "\n");
    exit(1);
}
