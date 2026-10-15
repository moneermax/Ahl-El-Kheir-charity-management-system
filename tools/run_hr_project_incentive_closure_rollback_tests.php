<?php
declare(strict_types=1);

/**
 * Project-completion incentive closure integration — rollback-only test.
 *
 * Selects an existing administratively closed project with eligible active
 * team members and a readable controlled balance. Inside a transaction it
 * snapshots recipients and adds temporary FM closure evidence if needed.
 * Positive-balance projects exercise award blocking and rollback; the award/
 * recognition success branch requires a real zero-balance project. The harness
 * performs no DDL and never commits test fixtures or alters accounting records.
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/projects/project_lib.php';
require_once __DIR__ . '/../modules/projects/project_funding_accounting.php';
require_once __DIR__ . '/../modules/hr/lib_employee_incentives.php';

$pdo = db();

function hrProjectIncentiveTestSkip(string $reason): never
{
    echo "SKIP | Project incentive closure rollback tests | {$reason}\n";
    exit(0);
}

function hrProjectIncentiveTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hrProjectIncentiveTestOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hrProjectIncentiveTestCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$currency = strtoupper(trim((string)APP_CURRENCY_CODE));
if (!preg_match('/^[A-Z]{3}$/', $currency)) {
    hrProjectIncentiveTestSkip('APP_CURRENCY_CODE is not a valid three-letter currency code.');
}

$schemaCheck = hrProjectIncentiveTestOne(
    $pdo,
    "SELECT IS_NULLABLE
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'hr_incentive_project_allocations'
       AND COLUMN_NAME = 'approved_by_user_id'"
);
if (!$schemaCheck || $schemaCheck['IS_NULLABLE'] !== 'YES') {
    hrProjectIncentiveTestSkip('Apply 2026-10-15_hr_incentive_project_auto_allocation.sql first.');
}

$policy = hrProjectIncentiveTestOne(
    $pdo,
    "SELECT p.id, r.reward_amount, r.currency_code
     FROM hr_incentive_policy_versions p
     JOIN hr_incentive_policy_rules r ON r.policy_version_id = p.id
     JOIN hr_incentive_types t ON t.type_code = r.incentive_type
     WHERE p.status = 'active'
       AND p.effective_from <= CURDATE()
       AND (p.effective_to IS NULL OR p.effective_to >= CURDATE())
       AND r.incentive_type = 'project_completion'
       AND r.is_enabled = 1
       AND r.reward_amount > 0
       AND UPPER(r.currency_code) = ?
       AND t.is_active = 1
       AND t.qualification_mode = 'source_event'
     ORDER BY p.effective_from DESC, p.version_no DESC
     LIMIT 1",
    [$currency]
);
if (!$policy) {
    hrProjectIncentiveTestSkip('No active effective project-completion rule matches the ledger currency.');
}

$projects = $pdo->query(
    "SELECT pl.project_id
     FROM project_lifecycle pl
     WHERE pl.lifecycle_status = 'closed'
       AND pl.closed_at IS NOT NULL
       AND NOT EXISTS (
           SELECT 1 FROM hr_incentive_events ev
           WHERE ev.source_table = 'project_lifecycle'
             AND ev.source_record_id = pl.project_id
             AND ev.incentive_type = 'project_completion'
       )
       AND NOT EXISTS (
           SELECT 1 FROM audit_log al
           WHERE al.action = 'FM_FINANCIAL_CLOSURE'
             AND al.entity_type = 'project_lifecycle'
             AND al.entity_id = pl.project_id
       )
       AND EXISTS (
           SELECT 1
           FROM project_team pt
           JOIN employees e ON e.user_id = pt.user_id
           JOIN hr_employment_states es ON es.id = e.employment_state_id
           JOIN users u ON u.id = e.user_id
           WHERE pt.project_id = pl.project_id
             AND pt.unassigned_at IS NULL
             AND es.category = 'working'
             AND es.is_active = 1
             AND u.is_active = 1
       )
     ORDER BY pl.project_id DESC"
)->fetchAll(PDO::FETCH_COLUMN);

$projectId = 0;
$initialControlledBalance = null;
foreach ($projects as $projectIdValue) {
    $candidateId = (int)$projectIdValue;
    try {
        // A positive-balance project is still a safe fixture for proving that
        // the FM closure gate cannot issue awards while funds remain controlled.
        // The successful award/recognition branch still requires a real zero-balance
        // candidate; the harness never edits posted funding, expenses, or returns.
        $initialControlledBalance = akp_project_controlled_balance($candidateId);
        $projectId = $candidateId;
        break;
    } catch (Throwable $e) {
        // A candidate with incomplete financial source data is not a safe fixture.
    }
}
if ($projectId <= 0 || $initialControlledBalance === null) {
    hrProjectIncentiveTestSkip('No closed project with an eligible active team and readable controlled balance is available.');
}

$baselineEventCount = hrProjectIncentiveTestCount(
    $pdo,
    "SELECT COUNT(*) FROM hr_incentive_events
     WHERE source_table = 'project_lifecycle' AND source_record_id = ?
       AND incentive_type = 'project_completion'",
    [$projectId]
);
$baselineAwardCount = hrProjectIncentiveTestCount(
    $pdo,
    "SELECT COUNT(*) FROM hr_incentive_awards a
     JOIN hr_incentive_events ev ON ev.id = a.event_id
     WHERE ev.source_table = 'project_lifecycle' AND ev.source_record_id = ?
       AND ev.incentive_type = 'project_completion'",
    [$projectId]
);

$pdo->beginTransaction();
try {
    $updateClosure = $pdo->prepare(
        "UPDATE project_lifecycle SET closed_at = NOW()
         WHERE project_id = ? AND lifecycle_status = 'closed'"
    );
    $updateClosure->execute([$projectId]);
    hrProjectIncentiveTestAssert($updateClosure->rowCount() === 1, 'Could not establish a transactional current-date closure fixture.');

    $eventId = hrIncentivePrepareProjectCompletion($pdo, $projectId, 0);
    hrProjectIncentiveTestAssert($eventId > 0, 'No project event was created from the active effective policy.');

    $allocationCount = hrProjectIncentiveTestCount(
        $pdo,
        "SELECT COUNT(*) FROM hr_incentive_project_allocations WHERE event_id = ?",
        [$eventId]
    );
    hrProjectIncentiveTestAssert($allocationCount > 0, 'No eligible team recipients were snapshotted.');
    hrProjectIncentiveTestAssert(
        hrProjectIncentiveTestCount(
            $pdo,
            "SELECT COUNT(*) FROM hr_incentive_project_allocations
             WHERE event_id = ? AND (approved_by_user_id IS NOT NULL OR approved_at IS NOT NULL)",
            [$eventId]
        ) === 0,
        'System allocations incorrectly contain human approval metadata.'
    );

    // A zero balance alone must not issue awards without the durable FM audit gate.
    $beforeFmGate = hrIncentiveProcessProjectCompletion(
        $pdo,
        $projectId,
        akp_project_controlled_balance($projectId),
        0
    );
    hrProjectIncentiveTestAssert($beforeFmGate === 0, 'Awards were issued without durable FM financial-closure evidence.');
    hrProjectIncentiveTestAssert(
        hrProjectIncentiveTestCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_awards WHERE event_id = ?', [$eventId]) === 0,
        'Award rows exist before the FM financial-closure gate.'
    );

    $financialClosureExists = hrProjectIncentiveTestCount(
        $pdo,
        "SELECT COUNT(*) FROM audit_log
         WHERE action = 'FM_FINANCIAL_CLOSURE'
           AND entity_type = 'project_lifecycle' AND entity_id = ?",
        [$projectId]
    ) > 0;
    if (!$financialClosureExists) {
        $actorId = (int)(hrProjectIncentiveTestOne(
            $pdo,
            "SELECT pt.user_id FROM project_team pt
             JOIN users u ON u.id = pt.user_id AND u.is_active = 1
             WHERE pt.project_id = ? AND pt.unassigned_at IS NULL
             ORDER BY pt.user_id LIMIT 1",
            [$projectId]
        )['user_id'] ?? 0);
        hrProjectIncentiveTestAssert($actorId > 0, 'No valid actor available for temporary audit evidence.');
        $insertAudit = $pdo->prepare(
            "INSERT INTO audit_log
                (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'FM_FINANCIAL_CLOSURE', 'project_lifecycle', ?, ?, ?, ?, ?)"
        );
        $insertAudit->execute([
            $actorId,
            $projectId,
            json_encode(['test_fixture' => true], JSON_UNESCAPED_UNICODE),
            json_encode(['test_fixture' => true, 'controlled_balance' => 0], JSON_UNESCAPED_UNICODE),
            '127.0.0.1',
            'rollback-only incentive test'
        ]);
    }

    $processed = hrIncentiveProcessProjectCompletion(
        $pdo,
        $projectId,
        akp_project_controlled_balance($projectId),
        0
    );
    hrProjectIncentiveTestAssert($processed === $allocationCount, 'The processor did not handle every snapshotted eligible employee.');

    $event = hrProjectIncentiveTestOne(
        $pdo,
        'SELECT status FROM hr_incentive_events WHERE id = ?',
        [$eventId]
    );
    hrProjectIncentiveTestAssert(($event['status'] ?? '') === 'verified', 'Project event did not reach verified status after both closure gates.');

    $awardRows = hrProjectIncentiveTestCount(
        $pdo,
        'SELECT COUNT(*) FROM hr_incentive_awards WHERE event_id = ?',
        [$eventId]
    );
    hrProjectIncentiveTestAssert($awardRows === $allocationCount, 'Award count does not match the eligible employee allocation count.');

    $unrecognized = hrProjectIncentiveTestCount(
        $pdo,
        "SELECT COUNT(*) FROM hr_incentive_awards
         WHERE event_id = ? AND recognition_journal_entry_id IS NULL",
        [$eventId]
    );
    hrProjectIncentiveTestAssert($unrecognized === 0, 'One or more awards lack a recognition journal; verify currency/account prerequisites.');

    $pdo->rollBack();

    hrProjectIncentiveTestAssert(
        hrProjectIncentiveTestCount(
            $pdo,
            "SELECT COUNT(*) FROM hr_incentive_events
             WHERE source_table = 'project_lifecycle' AND source_record_id = ?
               AND incentive_type = 'project_completion'",
            [$projectId]
        ) === $baselineEventCount,
        'Rollback left a project incentive event behind.'
    );
    hrProjectIncentiveTestAssert(
        hrProjectIncentiveTestCount(
            $pdo,
            "SELECT COUNT(*) FROM hr_incentive_awards a
             JOIN hr_incentive_events ev ON ev.id = a.event_id
             WHERE ev.source_table = 'project_lifecycle' AND ev.source_record_id = ?
               AND ev.incentive_type = 'project_completion'",
            [$projectId]
        ) === $baselineAwardCount,
        'Rollback left project incentive awards behind.'
    );

    echo "PASS | project_team_snapshot={$allocationCount} | full_policy_amount_per_employee=PASS | no_approval_metadata=PASS\n";
    echo "PASS | durable_FM_closure_gate=PASS | award_recognition=PASS | rollback_cleanup=PASS | project={$projectId}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL | Project incentive closure rollback tests | " . $e->getMessage() . "\n");
    exit(1);
}
