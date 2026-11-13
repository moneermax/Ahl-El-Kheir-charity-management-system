<?php
declare(strict_types=1);

/**
 * Monthly-agreement Supervisor-payment FM approval integration — rollback-only.
 *
 * Creates one temporary pending sponsor_payments row matching the current
 * November 2026 agreement, invokes the real shared FM approval operation,
 * and verifies the approved payment, posted transaction, allocation and
 * balanced journal. All writes are rolled back.
 *
 * This exercises the approval operation, not the browser submission UI.
 * Run only while no other process is posting transactions: the production
 * journal-number allocator uses MAX(entry_code)+1 without serialization.
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib_sponsor_payment_approval.php';

if (PHP_SAPI !== 'cli' || APP_ENV !== 'development') {
    fwrite(STDERR, "Monthly-agreement FM approval test requires CLI and APP_ENV=development.\n");
    exit(1);
}

function spmaFmApprovalAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = db();
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== 'ahl_el_kheir') {
    fwrite(STDERR, "REFUSED | expected database ahl_el_kheir.\n");
    exit(2);
}
if ($pdo->inTransaction()) {
    throw new RuntimeException('Test requires a connection with no open transaction.');
}

$fixture = dbFetchOne(
    "SELECT a.id AS agreement_id, a.sponsorship_id, a.covered_month,
            a.agreed_sponsorship_amount, a.agreed_service_fee_amount, a.currency_code,
            s.sponsor_id, s.sponsorship_code, s.status AS sponsorship_status,
            s.start_date, s.end_date
     FROM sponsorship_monthly_agreements a
     JOIN sponsorships s ON s.id = a.sponsorship_id
     WHERE a.id = 1 AND a.sponsorship_id = 3508
       AND a.covered_month = '2026-11-01'
       AND a.agreed_sponsorship_amount = 10000.00
       AND a.agreed_service_fee_amount = 2000.00
       AND a.currency_code = 'SDG'
     LIMIT 1"
);
spmaFmApprovalAssert((bool)$fixture, 'Preflighted agreement 1 / SH-003508 no longer matches the test fixture.');
spmaFmApprovalAssert((int)$fixture['sponsor_id'] > 0
    && (string)$fixture['sponsorship_code'] === 'SH-003508'
    && (string)$fixture['sponsorship_status'] === 'active'
    && (string)$fixture['start_date'] <= '2026-11-01'
    && (empty($fixture['end_date']) || (string)$fixture['end_date'] >= '2026-11-30'),
    'The test sponsorship is no longer eligible for November 2026.');

$supervisor = dbFetchOne(
    "SELECT u.id
     FROM users u JOIN roles r ON r.id = u.role_id
     WHERE u.is_active = 1 AND r.code = 'supervisor'
     ORDER BY u.id LIMIT 1"
);
$reviewer = dbFetchOne(
    "SELECT u.id
     FROM users u JOIN roles r ON r.id = u.role_id
     WHERE u.is_active = 1
       AND r.code IN ('financial_manager','fm','finance','admin','sudo')
     ORDER BY FIELD(r.code,'financial_manager','fm','finance','admin','sudo'), u.id
     LIMIT 1"
);
spmaFmApprovalAssert((bool)$supervisor && (bool)$reviewer
    && (int)$supervisor['id'] > 0 && (int)$reviewer['id'] > 0
    && (int)$supervisor['id'] !== (int)$reviewer['id'],
    'An active Supervisor and a distinct active FM-authorized reviewer are required.');

$pauseCount = (int)dbFetchOne(
    "SELECT COUNT(*) AS c FROM sponsorship_pause_events WHERE sponsorship_id = ?",
    [(int)$fixture['sponsorship_id']]
)['c'];
$allocationCount = (int)dbFetchOne(
    "SELECT COUNT(*) AS c FROM sponsorship_payment_allocations
     WHERE sponsorship_id = ? AND covered_month = '2026-11-01'",
    [(int)$fixture['sponsorship_id']]
)['c'];
$agreementTransactionCount = (int)dbFetchOne(
    "SELECT COUNT(*) AS c FROM transactions WHERE monthly_agreement_id = ?",
    [(int)$fixture['agreement_id']]
)['c'];
$agreementSubmissionCount = (int)dbFetchOne(
    "SELECT COUNT(*) AS c FROM sponsor_payments WHERE monthly_agreement_id = ?",
    [(int)$fixture['agreement_id']]
)['c'];
$accounts = dbFetchAll(
    "SELECT code, id FROM accounts
     WHERE code IN ('1100','2200') AND is_active = 1"
);
$accountIds = [];
foreach ($accounts as $account) {
    $accountIds[(string)$account['code']] = (int)$account['id'];
}
spmaFmApprovalAssert($pauseCount === 0, 'The sponsorship has pause history; refusing to use it as a fixture.');
spmaFmApprovalAssert($allocationCount === 0, 'November already has an allocation; refusing to reuse the month.');
spmaFmApprovalAssert($agreementTransactionCount === 0 && $agreementSubmissionCount === 0,
    'The agreement is already linked to a transaction or submission; refusing to reuse it.');
spmaFmApprovalAssert(!empty($accountIds['1100']) && !empty($accountIds['2200']),
    'Active cash account 1100 and deferred sponsorship account 2200 are required.');

$paymentId = 0;
$transactionId = 0;
$journalId = 0;
$allocationId = 0;

try {
    $pdo->beginTransaction();

    dbExecute(
        "INSERT INTO sponsor_payments
         (payment_type, sponsorship_id, sponsor_id, supervisor_id, payment_period,
          amount, currency_code, receipt_file_path, unified_receipt_path, notes,
          status, reviewed_by_user_id, reviewed_at, return_note, transaction_id,
          payment_method, component_model, monthly_agreement_id,
          agreed_sponsorship_amount, agreed_service_fee_amount,
          actual_sponsorship_received, actual_service_fee_received,
          transaction_date, receipt_number, reference_number)
         VALUES
         ('monthly_sponsorship', ?, ?, ?, 'November/2026',
          10000.00, 'SDG', NULL, NULL, 'Rollback-only monthly agreement FM approval test',
          'pending', NULL, NULL, NULL, NULL,
          'cash', 'monthly_agreement', ?, 10000.00, 2000.00, 10000.00, 0.00,
          CURDATE(), NULL, NULL)",
        [
            (int)$fixture['sponsorship_id'],
            (int)$fixture['sponsor_id'],
            (int)$supervisor['id'],
            (int)$fixture['agreement_id'],
        ]
    );
    $paymentId = (int)dbLastInsertId();
    spmaFmApprovalAssert($paymentId > 0, 'Could not create the temporary pending Supervisor payment.');

    $expectedTransactionCode = 'SP-' . str_pad((string)$paymentId, 6, '0', STR_PAD_LEFT);
    spmaFmApprovalAssert(
        !(bool)dbFetchOne('SELECT id FROM transactions WHERE transaction_code = ? LIMIT 1', [$expectedTransactionCode]),
        'The generated approval transaction code already exists; refusing to continue.'
    );

    $approval = ak_approve_sponsor_payment($pdo, $paymentId, (int)$reviewer['id']);
    $transactionId = (int)$approval['transaction_id'];
    $journalId = (int)$approval['journal_id'];
    spmaFmApprovalAssert($transactionId > 0 && $journalId > 0,
        'The FM approval operation did not return transaction and journal IDs.');

    $approvedPayment = dbFetchOne(
        "SELECT status, reviewed_by_user_id, transaction_id, reviewed_at
         FROM sponsor_payments WHERE id = ?",
        [$paymentId]
    );
    spmaFmApprovalAssert($approvedPayment
        && (string)$approvedPayment['status'] === 'approved'
        && (int)$approvedPayment['reviewed_by_user_id'] === (int)$reviewer['id']
        && (int)$approvedPayment['transaction_id'] === $transactionId
        && !empty($approvedPayment['reviewed_at']),
        'The pending Supervisor payment was not atomically marked approved and linked.');

    $transaction = dbFetchOne(
        "SELECT status, transaction_type, component_model, monthly_agreement_id,
                amount, net_amount, admin_fee_method, admin_fee_amount,
                actual_sponsorship_received, actual_service_fee_received,
                created_by, transaction_code
         FROM transactions WHERE id = ?",
        [$transactionId]
    );
    spmaFmApprovalAssert($transaction
        && (string)$transaction['status'] === 'posted'
        && (string)$transaction['transaction_type'] === 'sponsorship_payment'
        && (string)$transaction['component_model'] === 'monthly_agreement'
        && (int)$transaction['monthly_agreement_id'] === (int)$fixture['agreement_id']
        && round((float)$transaction['amount'], 2) === 10000.00
        && round((float)$transaction['net_amount'], 2) === 10000.00
        && (string)$transaction['admin_fee_method'] === 'none'
        && round((float)$transaction['admin_fee_amount'], 2) === 0.00
        && round((float)$transaction['actual_sponsorship_received'], 2) === 10000.00
        && round((float)$transaction['actual_service_fee_received'], 2) === 0.00
        && (int)$transaction['created_by'] === (int)$reviewer['id']
        && (string)$transaction['transaction_code'] === $expectedTransactionCode,
        'The approved transaction does not preserve the zero-fee monthly-agreement snapshot.');

    $allocation = dbFetchOne(
        "SELECT id, collector_user_id, covered_month, commitment_amount,
                net_received, allocated_amount, unallocated_amount, allocation_status, source_type
         FROM sponsorship_payment_allocations WHERE transaction_id = ?",
        [$transactionId]
    );
    spmaFmApprovalAssert((bool)$allocation, 'FM approval did not create the sponsorship allocation.');
    $allocationId = (int)$allocation['id'];
    spmaFmApprovalAssert((int)$allocation['collector_user_id'] === (int)$supervisor['id']
        && (string)$allocation['covered_month'] === '2026-11-01'
        && round((float)$allocation['commitment_amount'], 2) === 10000.00
        && round((float)$allocation['net_received'], 2) === 10000.00
        && round((float)$allocation['allocated_amount'], 2) === 10000.00
        && round((float)$allocation['unallocated_amount'], 2) === 0.00
        && (string)$allocation['allocation_status'] === 'allocated'
        && (string)$allocation['source_type'] === 'live',
        'The FM-approved allocation does not match the Supervisor, month and zero-fee receipt.');

    $journal = dbFetchOne(
        "SELECT id, reference_type, reference_id, status
         FROM journal_entries WHERE id = ?",
        [$journalId]
    );
    spmaFmApprovalAssert($journal
        && (string)$journal['reference_type'] === 'transaction'
        && (int)$journal['reference_id'] === $transactionId
        && (string)$journal['status'] === 'posted',
        'The FM approval journal is not a posted transaction journal.');

    $journalLines = dbFetchAll(
        "SELECT a.code, jl.debit, jl.credit
         FROM journal_lines jl JOIN accounts a ON a.id = jl.account_id
         WHERE jl.entry_id = ? ORDER BY a.code",
        [$journalId]
    );
    $lineByCode = [];
    foreach ($journalLines as $line) {
        $lineByCode[(string)$line['code']] = [
            'debit' => round((float)$line['debit'], 2),
            'credit' => round((float)$line['credit'], 2),
        ];
    }
    spmaFmApprovalAssert(count($journalLines) === 2
        && isset($lineByCode['1100'], $lineByCode['2200'])
        && $lineByCode['1100'] === ['debit' => 10000.00, 'credit' => 0.00]
        && $lineByCode['2200'] === ['debit' => 0.00, 'credit' => 10000.00]
        && !isset($lineByCode['4200']),
        'The FM approval journal is not exactly Dr 1100 / Cr 2200 with no service-fee revenue line.');

    $approvalAudit = dbFetchOne(
        "SELECT user_id, action, entity_type, entity_id, new_values
         FROM audit_log
         WHERE entity_type = 'sponsor_payments' AND entity_id = ? AND action = 'APPROVE'
         ORDER BY id DESC LIMIT 1",
        [$paymentId]
    );
    spmaFmApprovalAssert($approvalAudit
        && (int)$approvalAudit['user_id'] === (int)$reviewer['id']
        && (string)$approvalAudit['action'] === 'APPROVE'
        && (string)$approvalAudit['entity_type'] === 'sponsor_payments'
        && (int)$approvalAudit['entity_id'] === $paymentId,
        'FM approval did not persist its mandatory audit event.');
    $auditValues = json_decode((string)$approvalAudit['new_values'], true);
    spmaFmApprovalAssert(is_array($auditValues)
        && ($auditValues['status'] ?? null) === 'approved'
        && (int)($auditValues['txn_id'] ?? 0) === $transactionId
        && (int)($auditValues['journal_id'] ?? 0) === $journalId,
        'FM approval audit does not identify the approved transaction and journal.');

    $totals = dbFetchOne(
        "SELECT ROUND(COALESCE(SUM(debit),0),2) AS debit_total,
                ROUND(COALESCE(SUM(credit),0),2) AS credit_total, COUNT(*) AS line_count
         FROM journal_lines WHERE entry_id = ?",
        [$journalId]
    );
    spmaFmApprovalAssert((int)$totals['line_count'] === 2
        && round((float)$totals['debit_total'], 2) === 10000.00
        && round((float)$totals['credit_total'], 2) === 10000.00,
        'The FM approval journal is not balanced at 10,000 SDG.');

    $pdo->rollBack();

    spmaFmApprovalAssert(!(bool)dbFetchOne('SELECT id FROM sponsor_payments WHERE id = ?', [$paymentId]),
        'Temporary Supervisor payment survived rollback.');
    spmaFmApprovalAssert(!(bool)dbFetchOne('SELECT id FROM transactions WHERE id = ?', [$transactionId]),
        'Temporary accounting transaction survived rollback.');
    spmaFmApprovalAssert(!(bool)dbFetchOne('SELECT id FROM sponsorship_payment_allocations WHERE transaction_id = ?', [$transactionId]),
        'Temporary sponsorship allocation survived rollback.');
    spmaFmApprovalAssert(!(bool)dbFetchOne('SELECT id FROM journal_entries WHERE id = ?', [$journalId]),
        'Temporary journal header survived rollback.');
    spmaFmApprovalAssert(!(bool)dbFetchOne(
        "SELECT id FROM audit_log
         WHERE entity_type = 'sponsor_payments' AND entity_id = ? AND action = 'APPROVE'
         LIMIT 1",
        [$paymentId]
    ), 'Temporary approval audit event survived rollback.');
    spmaFmApprovalAssert((int)dbFetchOne(
        "SELECT COUNT(*) AS c FROM hr_incentive_events
         WHERE source_table = 'sponsor_payments' AND source_record_id = ?",
        [$paymentId]
    )['c'] === 0, 'Temporary incentive event survived rollback.');

    echo "PASS | Supervisor_pending_payment=PASS | FM_approval=PASS | transaction=POSTED | payment=APPROVED\n";
    echo "PASS | allocation=10000.00/0.00 | collector=SUPERVISOR | journal=Dr1100:10000.00,Cr2200:10000.00 | balanced=PASS\n";
    echo "PASS | no_service_fee_revenue=PASS | approval_audit_rollback=PASS | incentive_rollback=PASS | rollback=PASS\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL | monthly-agreement Supervisor/FM approval | " . $error->getMessage() . "\n");
    exit(1);
}
