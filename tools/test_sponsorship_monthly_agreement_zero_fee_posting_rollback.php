<?php
declare(strict_types=1);

/**
 * Monthly-agreement zero-service-fee posting integration — rollback-only.
 *
 * Uses the specifically preflighted November 2026 agreement for SH-003508.
 * Exercises the real prepared posting path, including snapshot application,
 * allocation creation, and journal creation. All writes are rolled back.
 * No DDL, schema setup, account seeding, or permanent fixture is performed.
 *
 * Run only while no other user/process is posting transactions: the production
 * journal-number allocator uses MAX(entry_code)+1 and does not serialize it.
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';

if (PHP_SAPI !== 'cli' || APP_ENV !== 'development') {
    fwrite(STDERR, "Monthly-agreement posting test requires CLI and APP_ENV=development.\n");
    exit(1);
}

function spmaZeroFeeAssert(bool $condition, string $message): void
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
spmaZeroFeeAssert($actorId > 0, 'No existing user is available for the rollback-only fixture.');
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
spmaZeroFeeAssert((bool)$fixture, 'Preflighted agreement 1 / SH-003508 no longer matches the approved test fixture.');
spmaZeroFeeAssert((string)$fixture['sponsorship_code'] === 'SH-003508'
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

spmaZeroFeeAssert($pauseCount === 0, 'The sponsorship now has pause history; refusing to use it as a fixture.');
spmaZeroFeeAssert($allocationCount === 0, 'November already has an allocation; refusing to alter the month commitment.');
spmaZeroFeeAssert($agreementTransactionCount === 0 && $agreementSubmissionCount === 0,
    'The agreement is already linked to a transaction or submission; refusing to reuse it.');

$accounts = dbFetchAll(
    "SELECT id, code, is_active FROM accounts WHERE code IN ('1100','2200')"
);
$accountIds = [];
foreach ($accounts as $account) {
    if ((int)$account['is_active'] === 1) {
        $accountIds[(string)$account['code']] = (int)$account['id'];
    }
}
spmaZeroFeeAssert(!empty($accountIds['1100']) && !empty($accountIds['2200']),
    'Active cash account 1100 and deferred sponsorship account 2200 are required.');

$transactionCode = 'TEST-SPMA-ZERO-FEE-' . bin2hex(random_bytes(6));
$transactionId = 0;
$journalId = 0;
$allocationId = 0;

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
         VALUES (?, 10000.00, 'SDG', 'cash', CURDATE(),
          'Rollback-only monthly-agreement zero-service-fee posting test',
          1.00, 'November/2026', 'monthly_sponsorship',
          0.00, 0.00, 0.00, 'posted', ?,
          ?, 'sponsorship_payment', 'none', 0.00,
          NULL, 'monthly_agreement', ?,
          10000.00, 2000.00, 10000.00, 0.00, NULL, NULL)",
        [(int)$fixture['sponsorship_id'], $actorId, $transactionCode, (int)$fixture['agreement_id']]
    );
    $transactionId = (int)dbLastInsertId();
    spmaZeroFeeAssert($transactionId > 0, 'Could not insert the temporary posted transaction.');

    $postedRow = dbFetchOne("SELECT * FROM transactions WHERE id=?", [$transactionId]);
    spmaZeroFeeAssert((bool)$postedRow, 'Could not reload the temporary transaction.');

    $journalId = ak_post_transaction_journal_prepared($transactionId, $actorId, $actorId);
    spmaZeroFeeAssert($journalId > 0, 'Prepared posting did not return a journal ID.');

    $after = dbFetchOne(
        "SELECT admin_fee_method, admin_fee_value, admin_fee_amount, admin_fee_policy_id,
                net_amount, service_fee_account_mapping_id, service_fee_account_id,
                actual_sponsorship_received, actual_service_fee_received
         FROM transactions WHERE id=?",
        [$transactionId]
    );
    spmaZeroFeeAssert($after
        && (string)$after['admin_fee_method'] === 'none'
        && round((float)$after['admin_fee_value'], 2) === 0.00
        && round((float)$after['admin_fee_amount'], 2) === 0.00
        && $after['admin_fee_policy_id'] === null
        && round((float)$after['net_amount'], 2) === 10000.00
        && $after['service_fee_account_mapping_id'] === null
        && $after['service_fee_account_id'] === null
        && round((float)$after['actual_sponsorship_received'], 2) === 10000.00
        && round((float)$after['actual_service_fee_received'], 2) === 0.00,
        'Zero-fee snapshot did not persist the expected no-fee/net-receipt values.');

    $allocation = dbFetchOne(
        "SELECT id, covered_month, commitment_amount, net_received,
                allocated_amount, unallocated_amount, allocation_status, source_type
         FROM sponsorship_payment_allocations WHERE transaction_id=?",
        [$transactionId]
    );
    spmaZeroFeeAssert((bool)$allocation, 'The real posting path did not create a sponsorship allocation.');
    $allocationId = (int)$allocation['id'];
    spmaZeroFeeAssert((string)$allocation['covered_month'] === '2026-11-01'
        && round((float)$allocation['commitment_amount'], 2) === 10000.00
        && round((float)$allocation['net_received'], 2) === 10000.00
        && round((float)$allocation['allocated_amount'], 2) === 10000.00
        && round((float)$allocation['unallocated_amount'], 2) === 0.00
        && (string)$allocation['allocation_status'] === 'allocated'
        && (string)$allocation['source_type'] === 'live',
        'Allocation snapshot does not match the November agreement and actual sponsorship receipt.');

    $journal = dbFetchOne(
        "SELECT id, entry_code, entry_date, reference_type, reference_id, status
         FROM journal_entries WHERE id=?",
        [$journalId]
    );
    spmaZeroFeeAssert($journal
        && (string)$journal['reference_type'] === 'transaction'
        && (int)$journal['reference_id'] === $transactionId
        && (string)$journal['status'] === 'posted',
        'Posted journal header does not reference the temporary transaction.');

    $journalLines = dbFetchAll(
        "SELECT a.code, jl.debit, jl.credit
         FROM journal_lines jl JOIN accounts a ON a.id=jl.account_id
         WHERE jl.entry_id=? ORDER BY a.code",
        [$journalId]
    );
    spmaZeroFeeAssert(count($journalLines) === 2, 'Zero-fee sponsorship journal must contain exactly two lines.');
    $lineByCode = [];
    foreach ($journalLines as $line) {
        $lineByCode[(string)$line['code']] = [
            'debit' => round((float)$line['debit'], 2),
            'credit' => round((float)$line['credit'], 2),
        ];
    }
    spmaZeroFeeAssert(isset($lineByCode['1100'], $lineByCode['2200'])
        && $lineByCode['1100'] === ['debit' => 10000.00, 'credit' => 0.00]
        && $lineByCode['2200'] === ['debit' => 0.00, 'credit' => 10000.00]
        && !isset($lineByCode['4200']),
        'Journal lines are not exactly Dr 1100 / Cr 2200 for 10,000 SDG with no service-fee revenue line.');

    $journalTotals = dbFetchOne(
        "SELECT ROUND(COALESCE(SUM(debit),0),2) AS debit_total,
                ROUND(COALESCE(SUM(credit),0),2) AS credit_total,
                COUNT(*) AS line_count
         FROM journal_lines WHERE entry_id=?",
        [$journalId]
    );
    spmaZeroFeeAssert((int)$journalTotals['line_count'] === 2
        && round((float)$journalTotals['debit_total'], 2) === 10000.00
        && round((float)$journalTotals['credit_total'], 2) === 10000.00,
        'The temporary journal is not balanced at 10,000 SDG.');

    $pdo->rollBack();

    spmaZeroFeeAssert(!(bool)dbFetchOne("SELECT id FROM transactions WHERE transaction_code=? LIMIT 1", [$transactionCode]),
        'Temporary transaction survived rollback.');
    spmaZeroFeeAssert(!(bool)dbFetchOne("SELECT id FROM sponsorship_payment_allocations WHERE transaction_id=?", [$transactionId]),
        'Temporary allocation survived rollback.');
    spmaZeroFeeAssert(!(bool)dbFetchOne(
        "SELECT id FROM journal_entries WHERE reference_type='transaction' AND reference_id=?",
        [$transactionId]
    ), 'Temporary journal header survived rollback.');
    spmaZeroFeeAssert(!(bool)dbFetchOne("SELECT id FROM journal_lines WHERE entry_id=?", [$journalId]),
        'Temporary journal lines survived rollback.');

    echo "PASS | monthly_agreement=1 | sponsorship=SH-003508 | covered_month=2026-11 | actual_sponsorship=10000.00 | actual_service_fee=0.00 | allocation=10000.00/0.00 | journal=Dr1100:10000.00,Cr2200:10000.00 | fee_revenue_line=NONE | rollback=PASS\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL | monthly-agreement zero-service-fee posting | " . $error->getMessage() . "\n");
    exit(1);
}
