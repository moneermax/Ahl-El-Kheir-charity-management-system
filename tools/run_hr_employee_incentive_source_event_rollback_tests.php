<?php
declare(strict_types=1);

/**
 * Incentive source-event helper — rollback-only verification.
 * Creates a temporary active policy/rule and source event inside one transaction.
 * No DDL and no committed test data.
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/hr/lib_employee_incentives.php';

$pdo = db();

function incentiveSourceAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function incentiveSourceOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function incentiveSourceCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$currency = strtoupper(trim((string)APP_CURRENCY_CODE));
$recipient = incentiveSourceOne(
    $pdo,
    "SELECT e.id AS employee_id, e.user_id
     FROM employees e
     JOIN users u ON u.id = e.user_id
     WHERE e.user_id > 0
     ORDER BY e.id
     LIMIT 1"
);
$actor = incentiveSourceOne($pdo, 'SELECT id FROM users WHERE id > 0 ORDER BY id LIMIT 1');
$type = incentiveSourceOne(
    $pdo,
    "SELECT type_code FROM hr_incentive_types
     WHERE type_code = 'collection' AND is_active = 1 AND qualification_mode = 'source_event' LIMIT 1"
);
if (!$actor || !$recipient || !$type || !preg_match('/^[A-Z]{3}$/', $currency)) {
    echo "SKIP | Incentive source-event rollback tests | required user, collection category, or currency is unavailable.\n";
    exit(0);
}

$versionNo = (int)$pdo->query('SELECT COALESCE(MAX(version_no),0) + 1 FROM hr_incentive_policy_versions')->fetchColumn();
$sourceRecordId = random_int(900000000, 1900000000);
$occurredAt = date('Y-m-d H:i:s');
$policyId = 0;
$eventId = 0;

try {
    $pdo->beginTransaction();

    $policyInsert = $pdo->prepare(
        "INSERT INTO hr_incentive_policy_versions
         (version_no, policy_name, status, effective_from, agreement_recorded_at,
          agreement_notes, created_by_user_id, activated_by_user_id, activated_at)
         VALUES (?, ?, 'active', CURDATE(), NOW(), ?, ?, ?, NOW())"
    );
    $policyInsert->execute([
        $versionNo,
        'ROLLBACK TEST - Incentive source event',
        'Temporary rollback-only policy fixture; not an approval or production policy.',
        (int)$actor['id'],
        (int)$actor['id'],
    ]);
    $policyId = (int)$pdo->lastInsertId();

    $ruleInsert = $pdo->prepare(
        "INSERT INTO hr_incentive_policy_rules
         (policy_version_id, incentive_type, reward_amount, currency_code, is_enabled, rule_notes)
         VALUES (?, 'collection', 25.00, ?, 1, 'Rollback-only source event test')"
    );
    $ruleInsert->execute([$policyId, $currency]);

    $collectionBlocked = false;
    try {
        hrIncentiveRecordSourceEvent(
            $pdo,
            'sponsor_payments',
            $sourceRecordId,
            'collection',
            $occurredAt,
            321.45,
            $currency,
            (int)$actor['id'],
            (int)$recipient['user_id'],
            (int)$recipient['employee_id']
        );
    } catch (InvalidArgumentException $expected) {
        $collectionBlocked = true;
    }
    incentiveSourceAssert($collectionBlocked, 'Routine collection source event was not blocked.');
    incentiveSourceAssert(
        incentiveSourceCount(
            $pdo,
            'SELECT COUNT(*) FROM hr_incentive_events WHERE idempotency_key = ?',
            ['hr-incentive:sponsor_payments:' . $sourceRecordId . ':collection']
        ) === 0,
        'Blocked collection attempt created an event.'
    );

    $pdo->rollBack();

    incentiveSourceAssert(
        incentiveSourceCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_events WHERE idempotency_key = ?', ['hr-incentive:sponsor_payments:' . $sourceRecordId . ':collection']) === 0,
        'Blocked collection event appeared after rollback.'
    );
    incentiveSourceAssert(
        incentiveSourceCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_policy_versions WHERE id = ?', [$policyId]) === 0,
        'Rollback left the temporary policy behind.'
    );

    echo "PASS | collection_source_event=blocked | no_new_collection_award_path=PASS\n";
    echo "PASS | rollback_cleanup=PASS | policy={$policyId}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FAIL | Incentive source-event rollback tests | " . $e->getMessage() . "\n";
    exit(1);
}
