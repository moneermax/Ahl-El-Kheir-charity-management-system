<?php
declare(strict_types=1);

/**
 * Read-only preflight for a distinct monthly-agreement Supervisor/FM return test.
 *
 * This script performs SELECTs only. It does not create fixtures, write audit
 * rows, upload files, or alter schema/data. It deliberately excludes all
 * previously protected sponsorship/payment evidence.
 *
 * Run:
 *   /opt/lampp/bin/php tools/preflight_sponsorship_return_fixture.php
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

if (PHP_SAPI !== 'cli' || APP_ENV !== 'development') {
    fwrite(STDERR, "REFUSED | requires CLI and APP_ENV=development.\n");
    exit(1);
}

$pdo = db();
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ($database !== 'ahl_el_kheir') {
    fwrite(STDERR, "REFUSED | expected database ahl_el_kheir; found {$database}.\n");
    exit(2);
}
if ($pdo->inTransaction()) {
    fwrite(STDERR, "REFUSED | connection already has an open transaction.\n");
    exit(2);
}

$requiredTables = [
    'sponsor_payments',
    'audit_log',
    'sponsorships',
    'sponsors',
    'sponsorship_monthly_agreements',
    'sponsorship_pause_events',
    'sponsorship_payment_allocations',
    'transactions',
    'journal_entries',
    'journal_lines',
];
$in = implode(',', array_fill(0, count($requiredTables), '?'));
$tableRows = dbFetchAll(
    "SELECT TABLE_NAME, ENGINE
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$in})",
    $requiredTables
);
$engines = [];
foreach ($tableRows as $row) {
    $engines[(string)$row['TABLE_NAME']] = strtoupper((string)$row['ENGINE']);
}
foreach ($requiredTables as $table) {
    if (!isset($engines[$table])) {
        fwrite(STDERR, "REFUSED | required table missing: {$table}.\n");
        exit(2);
    }
}
foreach (['sponsor_payments', 'audit_log', 'sponsorship_payment_allocations', 'transactions', 'journal_entries', 'journal_lines'] as $table) {
    if ($engines[$table] !== 'INNODB') {
        fwrite(STDERR, "REFUSED | {$table} is {$engines[$table]}, not InnoDB; atomic rollback cannot be assumed.\n");
        exit(2);
    }
}

$requiredColumns = [
    'sponsor_payments' => [
        'component_model', 'monthly_agreement_id', 'agreed_sponsorship_amount',
        'agreed_service_fee_amount', 'actual_sponsorship_received',
        'actual_service_fee_received', 'payment_period', 'status',
        'supervisor_id', 'sponsorship_id',
    ],
    'audit_log' => ['user_id', 'action', 'entity_type', 'entity_id', 'old_values', 'new_values'],
    'sponsorship_monthly_agreements' => [
        'id', 'sponsorship_id', 'covered_month', 'version_no',
        'agreed_sponsorship_amount', 'agreed_service_fee_amount', 'currency_code',
    ],
];
foreach ($requiredColumns as $table => $columns) {
    $columnRows = dbFetchAll(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
        [$table]
    );
    $actual = array_fill_keys(array_map(static fn(array $r): string => (string)$r['COLUMN_NAME'], $columnRows), true);
    foreach ($columns as $column) {
        if (!isset($actual[$column])) {
            fwrite(STDERR, "REFUSED | missing required column {$table}.{$column}.\n");
            exit(2);
        }
    }
}

$protectedPayments = (int)dbFetchOne(
    "SELECT COUNT(*) AS c FROM sponsor_payments WHERE id IN (7,8,9)"
)['c'];
$protectedTransactions = (int)dbFetchOne(
    "SELECT COUNT(*) AS c FROM transactions WHERE transaction_code IN ('TR-000023','TR-000024')"
)['c'];
$protectedJournal = (int)dbFetchOne(
    "SELECT COUNT(*) AS c FROM journal_entries WHERE entry_code = 'JE-000054'"
)['c'];

echo "READ_ONLY | database={$database}\n";
echo "PASS | required_tables_and_columns=present\n";
echo "PASS | transactional_engines=InnoDB\n";
echo "INFO | protected_legacy_returned_rows_7_9={$protectedPayments}\n";
echo "INFO | protected_transactions_TR-000023_TR-000024={$protectedTransactions}\n";
echo "INFO | protected_journal_JE-000054={$protectedJournal}\n";

$candidates = dbFetchAll(
    "SELECT a.id AS agreement_id, a.sponsorship_id, a.covered_month,
            a.version_no, a.agreed_sponsorship_amount, a.agreed_service_fee_amount,
            a.currency_code, s.sponsor_id, s.sponsorship_code,
            s.supervisor_id, s.status AS sponsorship_status,
            s.start_date, s.end_date, spn.status AS sponsor_status
     FROM sponsorship_monthly_agreements a
     JOIN sponsorships s ON s.id = a.sponsorship_id
     JOIN sponsors spn ON spn.id = s.sponsor_id
     JOIN users su ON su.id = spn.supervisor_id AND su.is_active = 1
     JOIN roles sr ON sr.id = su.role_id AND sr.code = 'supervisor'
     WHERE s.status = 'active'
       AND spn.status = 'active'
       AND s.start_date <= LAST_DAY(a.covered_month)
       AND (s.end_date IS NULL OR s.end_date >= a.covered_month)
       AND NOT EXISTS (
           SELECT 1 FROM sponsorship_pause_events pe
           WHERE pe.sponsorship_id = s.id
       )
       AND NOT EXISTS (
           SELECT 1 FROM sponsorship_payment_allocations al
           WHERE al.sponsorship_id = s.id
             AND al.covered_month = a.covered_month
       )
       AND NOT EXISTS (
           SELECT 1 FROM sponsor_payments pay
           WHERE pay.sponsorship_id = s.id
             AND (
                 pay.monthly_agreement_id = a.id
                 OR pay.payment_period = DATE_FORMAT(a.covered_month, '%M/%Y')
             )
       )
       AND NOT EXISTS (
           SELECT 1 FROM transactions t
           WHERE t.sponsorship_id = s.id
             AND (
                 t.monthly_agreement_id = a.id
                 OR t.payment_period = DATE_FORMAT(a.covered_month, '%M/%Y')
             )
       )
       AND NOT EXISTS (
           SELECT 1 FROM sponsorship_monthly_agreements newer
           WHERE newer.sponsorship_id = a.sponsorship_id
             AND newer.covered_month = a.covered_month
             AND newer.version_no > a.version_no
       )
     ORDER BY a.covered_month DESC, a.id ASC
     LIMIT 10"
);

if (!$candidates) {
    echo "RESULT | no_safe_candidate_found\n";
    exit(0);
}

foreach ($candidates as $row) {
    printf(
        "CANDIDATE | agreement=%d | sponsorship=%d | code=%s | month=%s | version=%d | supervisor=%d | agreed_sponsorship=%s | agreed_service_fee=%s | currency=%s\n",
        (int)$row['agreement_id'],
        (int)$row['sponsorship_id'],
        (string)$row['sponsorship_code'],
        (string)$row['covered_month'],
        (int)$row['version_no'],
        (int)$row['supervisor_id'],
        (string)$row['agreed_sponsorship_amount'],
        (string)$row['agreed_service_fee_amount'],
        (string)$row['currency_code']
    );
}
echo "RESULT | candidate_count=" . count($candidates) . " | READ_ONLY=PASS\n";
