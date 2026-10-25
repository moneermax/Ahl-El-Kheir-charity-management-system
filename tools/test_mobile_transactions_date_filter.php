<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/v1/transactions/query_service.php';

$pdo = db();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$fixtures = dbFetchAll(
    'SELECT id, transaction_date FROM transactions ORDER BY id LIMIT 3'
);
$assert(count($fixtures) === 3, 'At least three existing transaction records are required for the rollback-only date-boundary test.');

$ids = array_map(static fn(array $row): int => (int)$row['id'], $fixtures);
$beforeDates = array_map(static fn(array $row): string => (string)$row['transaction_date'], $fixtures);
$fromDate = '2026-01-10';
$insideDate = '2026-01-11';
$toDate = '2026-01-12';

$pdo->beginTransaction();
try {
    dbExecute('UPDATE transactions SET transaction_date = ? WHERE id = ?', [$fromDate, $ids[0]]);
    dbExecute('UPDATE transactions SET transaction_date = ? WHERE id = ?', [$insideDate, $ids[1]]);
    dbExecute('UPDATE transactions SET transaction_date = ? WHERE id = ?', [$toDate, $ids[2]]);

    $range = ak_api_list_transactions(1, 'admin', '', '', $insideDate, $toDate, 1);
    $rangeIds = array_map(static fn(array $row): int => (int)$row['id'], $range['items']);
    $assert(in_array($ids[1], $rangeIds, true), 'The start-date boundary record was excluded.');
    $assert(in_array($ids[2], $rangeIds, true), 'The end-date boundary record was excluded.');
    $assert(!in_array($ids[0], $rangeIds, true), 'A record before the start date was included.');

    $singleDay = ak_api_list_transactions(1, 'admin', '', '', $insideDate, $insideDate, 1);
    $singleDayIds = array_map(static fn(array $row): int => (int)$row['id'], $singleDay['items']);
    $assert(in_array($ids[1], $singleDayIds, true), 'The same-day inclusive range omitted its matching record.');
    $assert(!in_array($ids[2], $singleDayIds, true), 'The same-day range included a later record.');

    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$restored = dbFetchAll(
    'SELECT id, transaction_date FROM transactions WHERE id IN (?, ?, ?) ORDER BY id',
    $ids
);
$restoredMap = [];
foreach ($restored as $row) {
    $restoredMap[(int)$row['id']] = (string)$row['transaction_date'];
}
foreach ($ids as $index => $id) {
    $assert(($restoredMap[$id] ?? null) === $beforeDates[$index], 'Rollback did not restore transaction date for record ' . $id . '.');
}

echo 'PASS | mobile transaction date filter | inclusive start/end, same-day range, outside-bound exclusion, rollback=PASS' . PHP_EOL;
