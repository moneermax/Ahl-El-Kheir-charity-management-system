<?php
declare(strict_types=1);

/**
 * Idempotent month-end sponsorship revenue recognition.
 * Dry-run is the default; pass --apply to create append-only journals/events.
 * Run only in the local development/test environment after the sponsorship
 * accounting migration has been applied.
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}
if (APP_ENV !== 'development') {
    fwrite(STDERR, "Refusing to run outside APP_ENV=development.\n");
    exit(1);
}

$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$months = array_values(array_filter($args, static fn($arg) => $arg !== '--apply'));
if (count($months) !== 1) {
    fwrite(STDERR, "Usage: php tools/recognize_sponsorship_revenue_month_end.php YYYY-MM [--apply]\n");
    exit(2);
}
$monthText = $months[0];
$monthDate = DateTimeImmutable::createFromFormat('!Y-m', $monthText);
$dateErrors = DateTimeImmutable::getLastErrors();
if (!$monthDate || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
    || $monthDate->format('Y-m') !== $monthText) {
    fwrite(STDERR, "Invalid month. Use YYYY-MM.\n");
    exit(2);
}
$coveredMonth = $monthDate->format('Y-m-01');
$monthEnd = $monthDate->modify('last day of this month')->format('Y-m-d');
if ($monthEnd > date('Y-m-d')) {
    fwrite(STDERR, "The covered month has not ended; no recognition is allowed yet.\n");
    exit(2);
}

$pdo = db();
try {
    $pdo->beginTransaction();
    $rows = dbFetchAll(
        "SELECT a.id,a.transaction_id,a.allocated_amount,e.id AS recognition_event_id
         FROM sponsorship_payment_allocations a
         JOIN transactions t ON t.id=a.transaction_id
         LEFT JOIN sponsorship_accounting_events e
           ON e.event_key=CONCAT('month-end-recognition:allocation:',a.id)
         WHERE a.covered_month=? AND a.allocated_amount>0
           AND t.status='posted' AND t.voided_at IS NULL
         ORDER BY a.id
         FOR UPDATE",
        [$coveredMonth]
    );
    $pending = 0;
    $already = 0;
    $pendingAmount = 0.00;
    foreach ($rows as $row) {
        if ($row['recognition_event_id'] === null) {
            $pending++;
            $pendingAmount += (float)$row['allocated_amount'];
        } else {
            $already++;
        }
    }

    if ($apply) {
        $result = ak_sp_recognize_month_end_prepared($coveredMonth, null);
        $pdo->commit();
        echo "APPLIED | month={$monthText} | recognized={$result['recognized_count']} | already_recognized={$result['already_recognized_count']} | amount=" . number_format($result['recognized_amount'], 2, '.', '') . " SDG | date={$monthEnd}\n";
    } else {
        $pdo->rollBack();
        echo "DRY-RUN | month={$monthText} | eligible_allocations=" . count($rows)
            . " | pending={$pending} | already_recognized={$already} | pending_amount="
            . number_format($pendingAmount, 2, '.', '') . " SDG | recognition_date={$monthEnd}\n";
        echo "No data was changed. Add --apply to post the append-only recognition journals.\n";
    }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "FAILED | " . $error->getMessage() . "\n");
    exit(1);
}
