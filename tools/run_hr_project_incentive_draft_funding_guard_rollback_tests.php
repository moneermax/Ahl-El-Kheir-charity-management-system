<?php
declare(strict_types=1);

/**
 * Regression test: draft funding allocations must block project-completion
 * incentive processing even when the posted controlled balance is zero.
 *
 * Uses an existing disposable acceptance fixture and inserts only temporary
 * FM closure audit evidence inside a transaction. No funding, allocation,
 * payment evidence, award, journal, policy, or payroll row is changed durably.
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

function hrProjectDraftGuardAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hrProjectDraftGuardOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hrProjectDraftGuardCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function hrProjectDraftGuardSkip(string $reason): never
{
    echo "SKIP | Project incentive draft-funding guard | {$reason}\n";
    exit(0);
}

$dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ($dbName !== 'ahl_el_kheir_incentive_test_20261015_r1') {
    fwrite(STDERR, "BLOCKED | This harness only runs against ahl_el_kheir_incentive_test_20261015_r1; current database is {$dbName}.\n");
    exit(2);
}

$currency = strtoupper(trim((string)APP_CURRENCY_CODE));
if (!preg_match('/^[A-Z]{3}$/', $currency)) {
    hrProjectDraftGuardSkip('APP_CURRENCY_CODE is not a valid three-letter currency code.');
}

$policy = hrProjectDraftGuardOne(
    $pdo,
    "SELECT p.version_no, p.policy_name
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
if (!$policy || (string)$policy['policy_name'] !== 'ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-15-R5'
    || (int)$policy['version_no'] !== 1) {
    fwrite(STDERR, "BLOCKED | Active project incentive policy is not the protected R5 acceptance policy; no policy was changed.\n");
    exit(2);
}

$candidates = $pdo->query(
    "SELECT pl.project_id, pl.closed_by AS actor_user_id, ev.id AS event_id
     FROM project_lifecycle pl
     JOIN hr_incentive_events ev
       ON ev.source_table = 'project_lifecycle'
      AND ev.source_record_id = pl.project_id
      AND ev.incentive_type = 'project_completion'
     WHERE pl.lifecycle_status = 'closed'
       AND pl.closed_at IS NOT NULL
       AND ev.status IN ('pending_verification', 'verified')
       AND ev.verification_note LIKE '%system:project-recipient-snapshot-v1%'
       AND EXISTS (
           SELECT 1 FROM project_funding_allocations fa
           WHERE fa.project_id = pl.project_id AND fa.status = 'draft'
       )
       AND NOT EXISTS (
           SELECT 1 FROM hr_incentive_awards aw WHERE aw.event_id = ev.id
       )
       AND NOT EXISTS (
           SELECT 1 FROM audit_log al
           WHERE al.action = 'FM_FINANCIAL_CLOSURE'
             AND al.entity_type = 'project_lifecycle'
             AND al.entity_id = pl.project_id
       )
     ORDER BY pl.project_id"
)->fetchAll(PDO::FETCH_ASSOC);

$fixture = null;
foreach ($candidates as $candidate) {
    $balance = akp_project_controlled_balance((int)$candidate['project_id']);
    if (abs($balance) <= 0.009 && (int)($candidate['actor_user_id'] ?? 0) > 0) {
        $fixture = $candidate;
        break;
    }
}
if (!$fixture) {
    hrProjectDraftGuardSkip('No closed, zero-posted-balance project with a snapshotted event, draft allocation, valid closure actor, no award, and no FM closure audit was found.');
}

$projectId = (int)$fixture['project_id'];
$eventId = (int)$fixture['event_id'];
$actorId = (int)$fixture['actor_user_id'];
$baselineAwards = hrProjectDraftGuardCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_awards WHERE event_id = ?', [$eventId]);
$baselineDrafts = hrProjectDraftGuardCount($pdo, "SELECT COUNT(*) FROM project_funding_allocations WHERE project_id = ? AND status = 'draft'", [$projectId]);
$baselineJournals = hrProjectDraftGuardCount(
    $pdo,
    "SELECT COUNT(*) FROM journal_entries je
     JOIN hr_incentive_awards aw ON aw.recognition_journal_entry_id = je.id
     WHERE aw.event_id = ?",
    [$eventId]
);
$baselineClosureAudits = hrProjectDraftGuardCount(
    $pdo,
    "SELECT COUNT(*) FROM audit_log
     WHERE action = 'FM_FINANCIAL_CLOSURE'
       AND entity_type = 'project_lifecycle' AND entity_id = ?",
    [$projectId]
);

try {
    $pdo->beginTransaction();

    $lockedDraft = $pdo->prepare(
        "SELECT id FROM project_funding_allocations
         WHERE project_id = ? AND status = 'draft'
         LIMIT 1 FOR UPDATE"
    );
    $lockedDraft->execute([$projectId]);
    hrProjectDraftGuardAssert($lockedDraft->fetchColumn() !== false, 'The selected fixture no longer has a draft funding allocation.');

    $closureGuardBlocked = false;
    try {
        akp_assert_no_draft_funding_allocations_for_financial_closure($projectId);
    } catch (RuntimeException $guardError) {
        $closureGuardBlocked = strpos($guardError->getMessage(), 'تخصيصات تمويل مسودة') !== false;
    }
    hrProjectDraftGuardAssert($closureGuardBlocked, 'Explicit FM financial-closure guard did not reject a draft allocation.');
    hrProjectDraftGuardAssert(akp_project_financial_closure_outcome(0.00, true) === 'draft_allocations_pending', 'Zero balance with a draft allocation must defer closure.');
    hrProjectDraftGuardAssert(akp_project_financial_closure_outcome(0.00, false) === 'complete', 'Zero balance without draft allocations should allow closure.');
    hrProjectDraftGuardAssert(akp_project_financial_closure_outcome(10.00, false) === 'balance_remaining', 'Positive balance must block closure.');

    $pdo->prepare(
        "INSERT INTO audit_log
            (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
         VALUES (?, 'FM_FINANCIAL_CLOSURE', 'project_lifecycle', ?, ?, ?, ?, ?)"
    )->execute([
        $actorId,
        $projectId,
        json_encode(['test_fixture' => true], JSON_UNESCAPED_UNICODE),
        json_encode(['test_fixture' => true, 'controlled_balance' => 0.00], JSON_UNESCAPED_UNICODE),
        '127.0.0.1',
        'rollback-only project incentive draft-funding guard test',
    ]);

    $processed = hrIncentiveProcessProjectCompletion($pdo, $projectId, 0.00, $actorId);
    hrProjectDraftGuardAssert($processed === 0, 'Processor issued an award while a draft funding allocation remained.');
    hrProjectDraftGuardAssert(
        hrProjectDraftGuardCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_awards WHERE event_id = ?', [$eventId]) === $baselineAwards,
        'Award count changed despite the draft-allocation guard.'
    );
    hrProjectDraftGuardAssert(
        hrProjectDraftGuardCount(
            $pdo,
            "SELECT COUNT(*) FROM journal_entries je
             JOIN hr_incentive_awards aw ON aw.recognition_journal_entry_id = je.id
             WHERE aw.event_id = ?",
            [$eventId]
        ) === $baselineJournals,
        'Recognition journal count changed despite the draft-allocation guard.'
    );
    hrProjectDraftGuardAssert(
        hrProjectDraftGuardCount($pdo, "SELECT COUNT(*) FROM project_funding_allocations WHERE project_id = ? AND status = 'draft'", [$projectId]) === $baselineDrafts,
        'Draft funding allocation evidence changed during the test.'
    );

    $pdo->rollBack();

    hrProjectDraftGuardAssert(
        hrProjectDraftGuardCount(
            $pdo,
            "SELECT COUNT(*) FROM audit_log
             WHERE action = 'FM_FINANCIAL_CLOSURE'
               AND entity_type = 'project_lifecycle' AND entity_id = ?",
            [$projectId]
        ) === $baselineClosureAudits,
        'Rollback left temporary FM closure audit evidence behind.'
    );
    hrProjectDraftGuardAssert(
        hrProjectDraftGuardCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_awards WHERE event_id = ?', [$eventId]) === $baselineAwards,
        'Rollback left an incentive award behind.'
    );

    echo "PASS | fm_explicit_closure_guard=PASS | automatic_closure_outcomes=PASS | draft_funding_allocation_blocks_processor=PASS | awards_unchanged=PASS | recognition_journals_unchanged=PASS\n";
    echo "PASS | funding_evidence_unchanged=PASS | rollback_cleanup=PASS | project={$projectId} | policy=R5\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL | Project incentive draft-funding guard | " . $e->getMessage() . "\n");
    exit(1);
}
