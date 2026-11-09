<?php
declare(strict_types=1);

/**
 * Sponsorship accounting integration — rollback-only.
 *
 * Exercises allocation snapshots, month-end recognition, and transaction-void
 * reversal against an existing eligible sponsorship. Every inserted row and
 * journal is rolled back; this test creates no permanent fixture and performs
 * no DDL.
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/accounting/lib_transaction_void.php';

if (PHP_SAPI !== 'cli' || APP_ENV !== 'development') {
    fwrite(STDERR, "Sponsorship accounting rollback test requires CLI and APP_ENV=development.\n");
    exit(1);
}

function spaTestAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function spaTestCreateTransaction(int $sponsorshipId, float $amount, string $period, int $actorId, string $suffix, ?int $feePolicyId = null): array
{
    $code = 'TEST-SPA-' . $suffix . '-' . bin2hex(random_bytes(3));
    dbExecute(
        "INSERT INTO transactions
         (sponsorship_id,amount,currency_code,payment_method,transaction_date,receipt_number,description,
          months_covered,payment_period,purpose,admin_fee_percent,admin_fee_amount,net_amount,status,
          created_by,transaction_code,transaction_type,sponsor_id,admin_fee_method,admin_fee_value,admin_fee_policy_id)
         VALUES (?,?, 'SDG','cash',CURDATE(),NULL,?,1.00,?,'monthly_sponsorship',0.00,0.00,?,'posted',?,
                 ?,'sponsorship_payment',NULL,'none',0.00,?)",
        [$sponsorshipId > 0 ? $sponsorshipId : null,round($amount,2),'rollback-only sponsorship accounting test',$period,round($amount,2),$actorId,$code,$feePolicyId]
    );
    $id = (int)dbLastInsertId();
    if ($id <= 0) throw new RuntimeException('Could not create rollback-only sponsorship test transaction.');
    $row = dbFetchOne("SELECT * FROM transactions WHERE id=?",[$id]);
    if (!$row) throw new RuntimeException('Could not reload rollback-only sponsorship test transaction.');
    return $row;
}

function spaTestCreateJournal(string $code, string $date, string $description, string $referenceType, int $referenceId, int $actorId, array $lines): int
{
    if (dbFetchOne("SELECT id FROM journal_entries WHERE entry_code=? LIMIT 1",[$code])) {
        throw new RuntimeException('Rollback-only journal code collision: ' . $code);
    }
    dbExecute(
        "INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by)
         VALUES (?,?,?,?,?,'posted',?)",
        [$code,$date,$description,$referenceType,$referenceId,$actorId]
    );
    $journalId = (int)dbLastInsertId();
    if ($journalId <= 0) throw new RuntimeException('Could not create rollback-only journal.');
    foreach ($lines as $line) {
        dbExecute(
            "INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)",
            [$journalId,(int)$line[0],round((float)$line[1],2),round((float)$line[2],2),(string)$line[3]]
        );
    }
    $totals = dbFetchOne(
        "SELECT COALESCE(SUM(debit),0) debit,COALESCE(SUM(credit),0) credit,COUNT(*) line_count
         FROM journal_lines WHERE entry_id=?",
        [$journalId]
    );
    if ((int)$totals['line_count'] !== count($lines)
        || round((float)$totals['debit'],2) !== round((float)$totals['credit'],2)
        || round((float)$totals['debit'],2) <= 0) {
        throw new RuntimeException('Rollback-only test journal is not balanced.');
    }
    return $journalId;
}

$pdo = db();
$actorId = (int)$pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($actorId <= 0) throw new RuntimeException('Sponsorship accounting test requires one existing user.');
$_SESSION['user_id'] = $actorId;

$accounts = dbFetchAll("SELECT id,code,is_active FROM accounts WHERE code IN ('1100','2200','4100')");
$accountIds = [];
foreach ($accounts as $account) $accountIds[(string)$account['code']] = (int)$account['id'];
foreach (['1100','2200','4100'] as $code) {
    if (empty($accountIds[$code])) throw new RuntimeException('Required test account is missing: ' . $code);
}

$fixture = dbFetchOne(
    "SELECT s.id,s.monthly_amount,s.start_date,s.sponsorship_code
     FROM sponsorships s
     WHERE s.status='active' AND s.monthly_amount>0
       AND s.sponsorship_code IS NOT NULL
       AND s.start_date <= '2026-10-01'
       AND (s.end_date IS NULL OR s.end_date >= '2026-12-31')
       AND s.pause_start_date IS NULL AND s.pause_end_date IS NULL
       AND NOT EXISTS (SELECT 1 FROM sponsorship_pause_events pe WHERE pe.sponsorship_id=s.id)
       AND NOT EXISTS (
           SELECT 1 FROM sponsorship_payment_allocations a
           WHERE a.sponsorship_id=s.id AND a.covered_month IN ('2026-10-01','2026-11-01','2026-12-01')
       )
     ORDER BY s.id ASC LIMIT 1"
);
if (!$fixture) {
    echo "SKIP | No clean active sponsorship fixture is available for October–December 2026. No data changed.\n";
    exit(0);
}

$sponsorshipId = (int)$fixture['id'];
$commitment = round((float)$fixture['monthly_amount'],2);
if ($commitment <= 0.02) throw new RuntimeException('Test sponsorship monthly commitment is too small.');

$feePolicyRow = dbFetchOne("SELECT id FROM accounting_admin_fee_policies ORDER BY id DESC LIMIT 1");
$feePolicyId = $feePolicyRow ? (int)$feePolicyRow['id'] : null;

$pdo->beginTransaction();
try {
    // October: a legacy receipt originally credited to 4100, then reclassified.
    $octoberGross = round($commitment + 25.00,2);
    $octoberTxn = spaTestCreateTransaction($sponsorshipId,$octoberGross,'October/2026',$actorId,'OCT');
    $octoberAllocation = ak_sp_create_payment_allocation_prepared($octoberTxn,$octoberGross,$actorId,$actorId);
    spaTestAssert(round((float)$octoberAllocation['allocated_amount'],2) === $commitment,'October allocation did not stop at the monthly commitment.');
    spaTestAssert(round((float)$octoberAllocation['unallocated_amount'],2) === 25.00,'October excess was not retained as deferred/unallocated.');

    $originalJournalId = spaTestCreateJournal(
        'TEST-SPA-ORIG-' . (int)$octoberTxn['id'],
        '2026-10-15',
        'Rollback-only original sponsorship receipt',
        'transaction',
        (int)$octoberTxn['id'],
        $actorId,
        [
            [$accountIds['1100'],$octoberGross,0.00,'Test cash receipt'],
            [$accountIds['4100'],0.00,$octoberGross,'Legacy sponsorship revenue']
        ]
    );
    $allocationId = (int)dbFetchOne(
        "SELECT id FROM sponsorship_payment_allocations WHERE transaction_id=?",
        [(int)$octoberTxn['id']]
    )['id'];
    $reclassJournalId = spaTestCreateJournal(
        'TEST-SPA-RECLASS-' . (int)$octoberTxn['id'],
        '2026-10-31',
        'Rollback-only historical reclassification',
        'sponsorship_historical_reclassification',
        (int)$octoberTxn['id'],
        $actorId,
        [
            [$accountIds['4100'],$octoberGross,0.00,'Reclassify sponsorship receipt'],
            [$accountIds['2200'],0.00,$octoberGross,'Deferred sponsorship revenue']
        ]
    );
    dbExecute(
        "INSERT INTO sponsorship_accounting_events
         (event_key,event_type,transaction_id,allocation_id,journal_entry_id,event_date,amount,created_by)
         VALUES (?,'historical_reclassification',?,?,?,?,?,?)",
        ['test-historical-reclassification:' . (int)$octoberTxn['id'],(int)$octoberTxn['id'],$allocationId,$reclassJournalId,'2026-10-31',$octoberGross,$actorId]
    );

    $recognition = ak_sp_recognize_month_end_prepared('2026-10-01',null);
    spaTestAssert($recognition['recognized_count'] === 1,'October month-end recognition did not create exactly one journal.');
    spaTestAssert(round((float)$recognition['recognized_amount'],2) === $commitment,'October recognition included the unallocated excess.');
    $recognitionRepeat = ak_sp_recognize_month_end_prepared('2026-10-01',null);
    spaTestAssert($recognitionRepeat['recognized_count'] === 0 && $recognitionRepeat['already_recognized_count'] === 1,'Month-end recognition is not idempotent.');

    $recognitionEvent = dbFetchOne(
        "SELECT journal_entry_id FROM sponsorship_accounting_events WHERE event_key=?",
        ['month-end-recognition:allocation:' . $allocationId]
    );
    spaTestAssert((bool)$recognitionEvent,'Month-end recognition event is missing.');
    $recognitionJournalId = (int)$recognitionEvent['journal_entry_id'];

    ak_void_transaction_journal_atomic((int)$octoberTxn['id'],'Rollback-only sponsorship accounting void test');
    $voidReversal = dbFetchOne(
        "SELECT id FROM journal_entries WHERE reference_type='transaction_void' AND reference_id=?",
        [(int)$octoberTxn['id']]
    );
    spaTestAssert((bool)$voidReversal,'Original transaction void reversal was not created.');
    $eventReversal = dbFetchOne(
        "SELECT id,journal_entry_id,amount FROM sponsorship_accounting_events WHERE event_key=?",
        ['transaction-void-reversal:' . (int)$octoberTxn['id']]
    );
    spaTestAssert((bool)$eventReversal,'Append-only sponsorship event reversal was not created.');
    spaTestAssert(round((float)$eventReversal['amount'],2) === 25.00,'Void reversal did not reverse only the residual deferred excess.');
    $eventReversalJournalId = (int)$eventReversal['journal_entry_id'];
    $voidReversalId = (int)$voidReversal['id'];

    $voided = dbExecute(
        "UPDATE transactions SET status='voided',voided_at=NOW(),voided_by=?,void_reason=? WHERE id=? AND status='posted'",
        [$actorId,'Rollback-only sponsorship accounting void test',(int)$octoberTxn['id']]
    );
    spaTestAssert($voided === 1,'Test transaction status did not transition to voided.');

    $allJournalIds = [$originalJournalId,$reclassJournalId,$recognitionJournalId,$voidReversalId,$eventReversalJournalId];
    $placeholders = implode(',',array_fill(0,count($allJournalIds),'?'));
    $netRows = dbFetchAll(
        "SELECT account_id,ROUND(SUM(debit)-SUM(credit),2) AS net
         FROM journal_lines WHERE entry_id IN ($placeholders) GROUP BY account_id",
        $allJournalIds
    );
    foreach ($netRows as $row) {
        spaTestAssert(round((float)$row['net'],2) === 0.00,'Voided transaction left a residual balance in account id ' . $row['account_id']);
    }

    // November: two partial receipts fill one commitment; a third receipt remains excess.
    $firstPart = round($commitment / 2,2);
    $secondPart = round($commitment - $firstPart,2);
    $novemberOne = spaTestCreateTransaction($sponsorshipId,$firstPart,'November/2026',$actorId,'NOV1',$feePolicyId);
    $novemberTwo = spaTestCreateTransaction($sponsorshipId,$secondPart,'November/2026',$actorId,'NOV2');
    $novemberThree = spaTestCreateTransaction($sponsorshipId,25.00,'November/2026',$actorId,'NOV3');
    $novemberJournalId = ak_post_transaction_journal_prepared((int)$novemberOne['id'],$actorId,$actorId);
    spaTestAssert($novemberJournalId > 0,'Shared transaction posting did not create a journal.');
    $postedRevenueLine = dbFetchOne("SELECT jl.debit,jl.credit,a.code FROM journal_lines jl JOIN accounts a ON a.id=jl.account_id WHERE jl.entry_id=? AND a.code='2200'",[$novemberJournalId]);
    spaTestAssert($postedRevenueLine && round((float)$postedRevenueLine['credit'],2) === $firstPart && round((float)$postedRevenueLine['debit'],2) === 0.00,'Live sponsorship receipt did not credit deferred revenue account 2200.');
    $firstAllocationRow = dbFetchOne("SELECT collector_user_id,commitment_amount,allocated_amount,unallocated_amount FROM sponsorship_payment_allocations WHERE transaction_id=?",[(int)$novemberOne['id']]);
    spaTestAssert($firstAllocationRow && (int)$firstAllocationRow['collector_user_id'] === $actorId,'Collector identity was not preserved in the allocation snapshot.');
    spaTestAssert(round((float)$firstAllocationRow['commitment_amount'],2) === $commitment,'Monthly commitment snapshot is incorrect.');
    $a1 = ['allocated_amount'=>(float)$firstAllocationRow['allocated_amount'],'unallocated_amount'=>(float)$firstAllocationRow['unallocated_amount']];
    $a2 = ak_sp_create_payment_allocation_prepared($novemberTwo,$secondPart,$actorId,$actorId);
    $a3 = ak_sp_create_payment_allocation_prepared($novemberThree,25.00,$actorId,$actorId);
    spaTestAssert(round((float)$a1['allocated_amount'],2) === $firstPart,'First partial payment allocation is incorrect.');
    spaTestAssert(round((float)$a2['allocated_amount'],2) === $secondPart,'Second partial payment did not fill the remaining commitment.');
    spaTestAssert(round((float)$a3['allocated_amount'],2) === 0.00 && round((float)$a3['unallocated_amount'],2) === 25.00,'Third payment was not retained as unallocated excess.');

    // December: any pause touching the month excludes the entire month.
    dbExecute(
        "INSERT INTO sponsorship_pause_events (sponsorship_id,event_type,effective_date,reason,actor_user_id)
         VALUES (?,'pause','2026-12-01','rollback-only pause fixture',?)",
        [$sponsorshipId,$actorId]
    );
    dbExecute(
        "INSERT INTO sponsorship_pause_events (sponsorship_id,event_type,effective_date,reason,actor_user_id)
         VALUES (?,'resume','2026-12-02',NULL,?)",
        [$sponsorshipId,$actorId]
    );
    $decemberTxn = spaTestCreateTransaction($sponsorshipId,$commitment,'December/2026',$actorId,'DEC');
    $decemberAllocation = ak_sp_create_payment_allocation_prepared($decemberTxn,$commitment,$actorId,$actorId);
    spaTestAssert(round((float)$decemberAllocation['allocated_amount'],2) === 0.00
        && round((float)$decemberAllocation['unallocated_amount'],2) === $commitment,
        'A month touched by a pause was incorrectly allocated.');

    // Unlinked monthly receipts remain fully unallocated.
    $unlinkedTxn = spaTestCreateTransaction(0,100.00,'December/2026',$actorId,'UNLINKED');
    $unlinkedAllocation = ak_sp_create_payment_allocation_prepared($unlinkedTxn,100.00,$actorId,$actorId);
    spaTestAssert(round((float)$unlinkedAllocation['allocated_amount'],2) === 0.00
        && round((float)$unlinkedAllocation['unallocated_amount'],2) === 100.00,
        'An unlinked monthly receipt was incorrectly allocated.');

    // Malformed non-empty coverage periods fail closed.
    $malformedTxn = spaTestCreateTransaction($sponsorshipId,100.00,'September-2026',$actorId,'BADPERIOD');
    $malformedRejected = false;
    try {
        ak_sp_create_payment_allocation_prepared($malformedTxn,100.00,$actorId,$actorId);
    } catch (RuntimeException $error) {
        $malformedRejected = true;
    }
    spaTestAssert($malformedRejected,'Malformed payment period was not rejected.');

    // Approved historical backfill: dry-run must remain read-only before the atomic apply.
    $backfillDryRun = ak_sp_backfill_historical_payments_prepared(false,$actorId);
    spaTestAssert(in_array($backfillDryRun['state'],['ready','already_applied'],true),'Historical backfill dry-run returned an unexpected state.');
    $preApplyCount = (int)dbFetchOne("SELECT COUNT(*) AS c FROM sponsorship_payment_allocations WHERE transaction_id IN (1,2,17,18,27,28,29)")['c'];
    if ($backfillDryRun['state'] === 'ready') {
        spaTestAssert($preApplyCount === 0,'Historical backfill dry-run created allocation rows.');
    } else {
        spaTestAssert($preApplyCount === 7,'Existing historical backfill state is incomplete.');
    }

    // Apply the exact seven transactions, append-only journals, and month-end recognition.
    $backfill = ak_sp_backfill_historical_payments_prepared(true,$actorId);
    $expectedBackfillState = $backfillDryRun['state'] === 'ready' ? 'applied' : 'already_applied';
    spaTestAssert($backfill['state'] === $expectedBackfillState,'Historical backfill apply result did not match the dry-run state.');
    spaTestAssert((int)$backfill['transaction_count'] === 7,'Historical backfill transaction count is incorrect.');
    spaTestAssert(round((float)$backfill['gross_total'],2) === 87000.00,'Historical backfill gross total is incorrect.');
    spaTestAssert(round((float)$backfill['allocated_total'],2) === 77000.00,'Historical backfill allocated total is incorrect.');
    spaTestAssert(round((float)$backfill['unallocated_total'],2) === 10000.00,'Historical backfill excess total is incorrect.');
    spaTestAssert(round((float)$backfill['earned_revenue_target'],2) === 66000.00,'Historical backfill earned revenue target is incorrect.');
    spaTestAssert(round((float)$backfill['deferred_revenue_target'],2) === 21000.00,'Historical backfill deferred revenue target is incorrect.');
    $backfillRepeat = ak_sp_backfill_historical_payments_prepared(true,$actorId);
    spaTestAssert($backfillRepeat['state'] === 'already_applied','Historical backfill is not idempotent on repeat execution.');
    spaTestAssert((int)$backfillRepeat['recognition_august']['already_recognized_count'] === 1
        && (int)$backfillRepeat['recognition_september']['already_recognized_count'] === 5,
        'Historical month-end recognition was not idempotent on repeat execution.');

    $pdo->rollBack();
    echo "PASS | sponsorship_allocation=PASS | live_posting_deferred=PASS | partial_accumulation=PASS | excess_deferred=PASS | pause_month_excluded=PASS | unlinked_deferred=PASS | strict_period=PASS | month_end_recognition=PASS | recognition_idempotency=PASS | void_event_reversal=PASS | historical_backfill=PASS | backfill_idempotency=PASS | target_reconciliation=66000/21000 | rollback=PASS\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "FAIL | sponsorship accounting rollback tests | " . $error->getMessage() . "\n");
    exit(1);
}
