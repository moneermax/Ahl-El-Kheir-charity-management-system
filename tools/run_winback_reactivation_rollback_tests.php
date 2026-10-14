<?php
declare(strict_types=1);

/**
 * Win-back reactivation transition — rollback-only integration test.
 * Requires migration 2026-10-14_winback_reactivation_attribution.sql.
 * Creates one temporary campaign and rolls back every business-data change.
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/administration/lib_winback.php';

$pdo = db();

function winbackTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function winbackTestOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$actor = winbackTestOne($pdo, 'SELECT id FROM users WHERE id > 0 ORDER BY id LIMIT 1');
$sponsor = winbackTestOne($pdo, 'SELECT id, status FROM sponsors WHERE id > 0 ORDER BY id LIMIT 1');
if (!$actor || !$sponsor) {
    echo "SKIP | Winback reactivation rollback test | existing user or sponsor is unavailable.\n";
    exit(0);
}

$campaignId = 0;
$originalSponsorStatus = (string)$sponsor['status'];

try {
    $pdo->beginTransaction();

    $insert = $pdo->prepare(
        "INSERT INTO winback_campaigns (sponsor_id, handled_by, status)
         VALUES (?, ?, 'contacted')"
    );
    $insert->execute([(int)$sponsor['id'], (int)$actor['id']]);
    $campaignId = (int)$pdo->lastInsertId();

    $result = wb_mark_returned($pdo, $campaignId, (int)$actor['id']);
    winbackTestAssert($result !== null, 'A contacted campaign did not transition to returned.');
    winbackTestAssert(
        (int)$result['sponsor_id'] === (int)$sponsor['id'] &&
        (int)$result['reactivated_by_user_id'] === (int)$actor['id'] &&
        trim((string)$result['reactivated_at']) !== '',
        'The transition did not return durable sponsor/actor/time attribution.'
    );

    $campaign = winbackTestOne(
        $pdo,
        "SELECT status, handled_by, reactivated_by_user_id, reactivated_at
         FROM winback_campaigns WHERE id = ?",
        [$campaignId]
    );
    $currentSponsor = winbackTestOne(
        $pdo,
        'SELECT status FROM sponsors WHERE id = ?',
        [(int)$sponsor['id']]
    );
    winbackTestAssert(
        $campaign !== null &&
        $campaign['status'] === 'persuaded' &&
        (int)$campaign['handled_by'] === (int)$actor['id'] &&
        (int)$campaign['reactivated_by_user_id'] === (int)$actor['id'] &&
        !empty($campaign['reactivated_at']) &&
        $currentSponsor !== null &&
        $currentSponsor['status'] === 'active',
        'Sponsor and campaign state/attribution were not committed together inside the test transaction.'
    );

    $repeat = wb_mark_returned($pdo, $campaignId, (int)$actor['id']);
    winbackTestAssert($repeat === null, 'A second transition was not blocked after the campaign closed.');

    $pdo->rollBack();

    $campaignAfterRollback = winbackTestOne(
        $pdo,
        'SELECT id FROM winback_campaigns WHERE id = ?',
        [$campaignId]
    );
    $sponsorAfterRollback = winbackTestOne(
        $pdo,
        'SELECT status FROM sponsors WHERE id = ?',
        [(int)$sponsor['id']]
    );
    winbackTestAssert($campaignAfterRollback === null, 'Rollback left the temporary campaign behind.');
    winbackTestAssert(
        $sponsorAfterRollback !== null &&
        $sponsorAfterRollback['status'] === $originalSponsorStatus,
        'Rollback did not restore the sponsor status.'
    );

    echo "PASS | sponsor_reactivation=atomic | durable_actor_timestamp=PASS | repeat_transition=blocked\n";
    echo "PASS | rollback_cleanup=PASS | campaign={$campaignId} | sponsor=" . (int)$sponsor['id'] . "\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | Winback reactivation rollback test | " . $e->getMessage() . "\n";
    exit(1);
}
