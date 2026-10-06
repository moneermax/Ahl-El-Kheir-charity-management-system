<?php
declare(strict_types=1);

/**
 * Final 1410 salary-advance reconciliation runtime verification.
 *
 * Read-only. Derives the reconciliation from the actual accounting model:
 * account 1410 is an asset; salary-advance disbursements debit 1410,
 * payroll repayments credit 1410, waiver refunds debit 1410, and executed
 * waivers credit 1410. The live salary-advance subledger is the sum of
 * outstanding balances on disbursed requests.
 *
 * No schema/data mutation is performed.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

$pdo = db();

$account = dbFetchOne(
    "SELECT id, code, name_ar, account_type, is_active
     FROM accounts
     WHERE code = '1410'
     LIMIT 1"
);

if (!$account) {
    fwrite(STDERR, "FAIL | 1410 reconciliation | Account 1410 does not exist.\n");
    exit(1);
}

if ((string)$account['account_type'] !== 'asset' || (int)$account['is_active'] !== 1) {
    fwrite(STDERR, "FAIL | 1410 reconciliation | Account 1410 is not an active asset account.\n");
    exit(1);
}

$accountId = (int)$account['id'];

$ledger = dbFetchOne(
    "SELECT
        COALESCE(SUM(jl.debit), 0) AS debit_total,
        COALESCE(SUM(jl.credit), 0) AS credit_total,
        COUNT(DISTINCT je.id) AS journal_count,
        COUNT(jl.id) AS line_count
     FROM journal_lines jl
     JOIN journal_entries je ON je.id = jl.entry_id
     WHERE jl.account_id = ?
       AND je.status = 'posted'",
    [$accountId]
);

$ledgerDebit = round((float)($ledger['debit_total'] ?? 0), 2);
$ledgerCredit = round((float)($ledger['credit_total'] ?? 0), 2);
$ledgerBalance = round($ledgerDebit - $ledgerCredit, 2);

$live = dbFetchOne(
    "SELECT
        COUNT(*) AS request_count,
        COALESCE(SUM(CASE WHEN status = 'disbursed'
                          THEN GREATEST(COALESCE(outstanding_balance, 0), 0)
                          ELSE 0 END), 0) AS outstanding_total,
        COALESCE(SUM(CASE WHEN status = 'disbursed'
                          AND COALESCE(outstanding_balance, 0) > 0
                          THEN 1 ELSE 0 END), 0) AS open_request_count
     FROM hr_salary_advance_requests"
);

$liveOutstanding = round((float)($live['outstanding_total'] ?? 0), 2);
$openRequestCount = (int)($live['open_request_count'] ?? 0);

$componentRows = dbFetchAll(
    "SELECT
        COALESCE(je.reference_type, '(null)') AS reference_type,
        COUNT(DISTINCT je.id) AS journal_count,
        COALESCE(SUM(jl.debit), 0) AS debit_total,
        COALESCE(SUM(jl.credit), 0) AS credit_total
     FROM journal_lines jl
     JOIN journal_entries je ON je.id = jl.entry_id
     WHERE jl.account_id = ?
       AND je.status = 'posted'
     GROUP BY je.reference_type
     ORDER BY je.reference_type",
    [$accountId]
);

$expectedReferenceTypes = [
    'salary_advance_disbursement',
    'payroll',
    'salary_advance_waiver_refund',
    'salary_advance_waiver',
];

$unexpected = [];
$components = [];
foreach ($componentRows as $row) {
    $type = (string)$row['reference_type'];
    $components[$type] = [
        'journals' => (int)$row['journal_count'],
        'debit' => round((float)$row['debit_total'], 2),
        'credit' => round((float)$row['credit_total'], 2),
    ];
    if (!in_array($type, $expectedReferenceTypes, true)) {
        $unexpected[] = $type;
    }
}

$disbursementDebit = round((float)($components['salary_advance_disbursement']['debit'] ?? 0), 2);
$payrollCredit = round((float)($components['payroll']['credit'] ?? 0), 2);
$refundDebit = round((float)($components['salary_advance_waiver_refund']['debit'] ?? 0), 2);
$waiverCredit = round((float)($components['salary_advance_waiver']['credit'] ?? 0), 2);

$disbursementOther = round(
    (float)($components['salary_advance_disbursement']['credit'] ?? 0),
    2
);
$payrollOther = round(
    (float)($components['payroll']['debit'] ?? 0),
    2
);
$refundOther = round(
    (float)($components['salary_advance_waiver_refund']['credit'] ?? 0),
    2
);
$waiverOther = round(
    (float)($components['salary_advance_waiver']['debit'] ?? 0),
    2
);

$expectedDebit = round($disbursementDebit + $refundDebit, 2);
$expectedCredit = round($payrollCredit + $waiverCredit, 2);
$componentBalance = round($expectedDebit - $expectedCredit, 2);

if ($unexpected) {
    fwrite(
        STDERR,
        "FAIL | 1410 reconciliation | Unexpected posted journal reference types on 1410: " .
        implode(', ', $unexpected) . "\n"
    );
    exit(1);
}

if (
    abs($disbursementOther) > 0.009 ||
    abs($payrollOther) > 0.009 ||
    abs($refundOther) > 0.009 ||
    abs($waiverOther) > 0.009
) {
    fwrite(STDERR, "FAIL | 1410 reconciliation | A salary-advance reference type has an unexpected 1410 debit/credit direction.\n");
    exit(1);
}

if (abs($ledgerDebit - $expectedDebit) > 0.009 ||
    abs($ledgerCredit - $expectedCredit) > 0.009) {
    fwrite(STDERR, "FAIL | 1410 reconciliation | 1410 ledger totals do not equal the derived salary-advance journal components.\n");
    exit(1);
}

if (abs($ledgerBalance - $liveOutstanding) > 0.009) {
    fwrite(
        STDERR,
        "FAIL | 1410 reconciliation | Ledger balance {$ledgerBalance} does not equal live disbursed outstanding {$liveOutstanding}.\n"
    );
    exit(1);
}

if (abs($componentBalance - $liveOutstanding) > 0.009) {
    fwrite(
        STDERR,
        "FAIL | 1410 reconciliation | Derived journal balance {$componentBalance} does not equal live outstanding {$liveOutstanding}.\n"
    );
    exit(1);
}

echo "PASS | 1410 reconciliation | ledger_balance={$ledgerBalance} | live_outstanding={$liveOutstanding} | debit={$ledgerDebit} | credit={$ledgerCredit} | disbursement_debit={$disbursementDebit} | payroll_credit={$payrollCredit} | waiver_refund_debit={$refundDebit} | waiver_credit={$waiverCredit} | open_requests={$openRequestCount} | journals=" . (int)($ledger['journal_count'] ?? 0) . " | lines=" . (int)($ledger['line_count'] ?? 0) . "\n";
