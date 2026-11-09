<?php
declare(strict_types=1);

/**
 * Approved historical sponsorship-payment accounting backfill.
 * Default is dry-run. --apply appends allocations, reclassification journals,
 * events, and month-end recognition journals for the approved closed months.
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
$allowed = ['--apply','--dry-run'];
foreach ($args as $arg) {
    if (!in_array($arg,$allowed,true)) {
        fwrite(STDERR, "Usage: php tools/backfill_sponsorship_payment_accounting.php [--dry-run|--apply]\n");
        exit(2);
    }
}
if (in_array('--apply',$args,true) && in_array('--dry-run',$args,true)) {
    fwrite(STDERR, "Choose either --dry-run or --apply, not both.\n");
    exit(2);
}
$apply = in_array('--apply',$args,true);
$pdo = db();
try {
    $pdo->beginTransaction();
    $result = ak_sp_backfill_historical_payments_prepared($apply,null);
    if ($apply) {
        $pdo->commit();
        $aug = $result['recognition_august'];
        $sep = $result['recognition_september'];
        echo "APPLIED | state={$result['state']} | transactions={$result['transaction_count']} | gross=" . number_format($result['gross_total'],2,'.','')
            . " SDG | allocated=" . number_format($result['allocated_total'],2,'.','')
            . " SDG | excess_deferred=" . number_format($result['unallocated_total'],2,'.','')
            . " SDG | earned=" . number_format($result['earned_revenue_target'],2,'.','')
            . " SDG | deferred=" . number_format($result['deferred_revenue_target'],2,'.','')
            . " SDG | recognition_new=" . ((int)$aug['recognized_count'] + (int)$sep['recognized_count'])
            . " | recognition_existing=" . ((int)$aug['already_recognized_count'] + (int)$sep['already_recognized_count']) . "\n";
    } else {
        $pdo->rollBack();
        echo "DRY-RUN | state={$result['state']} | transactions={$result['transaction_count']} | gross=" . number_format($result['gross_total'],2,'.','')
            . " SDG | allocated=" . number_format($result['allocated_total'],2,'.','')
            . " SDG | excess_deferred=" . number_format($result['unallocated_total'],2,'.','')
            . " SDG | earned_target=" . number_format($result['earned_revenue_target'],2,'.','')
            . " SDG | deferred_target=" . number_format($result['deferred_revenue_target'],2,'.','') . "\n";
        echo "No data was changed. Run with --apply only after reviewing this exact reconciliation.\n";
    }
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "FAILED | " . $error->getMessage() . "\n");
    exit(1);
}
