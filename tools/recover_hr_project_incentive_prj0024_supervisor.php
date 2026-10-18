<?php
declare(strict_types=1);

/**
 * One-purpose historical recovery for PRJ-0024's omitted primary-supervisor incentive.
 *
 * Strictly limited to ahl_el_kheir_incentive_test_20261015_r1, project 24,
 * event 34, supervisor user 34 / employee 22. No DDL and no policy changes.
 * The normal project-completion processor creates the award, recognition journal,
 * payroll link (if an eligible draft exists), and earned-notice outbox row.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "BLOCKED | CLI only.\n");
    exit(2);
}
if (!in_array('--apply', $argv, true)) {
    fwrite(STDERR, "BLOCKED | No changes made. Run this tool with --apply after syncing main.\n");
    exit(2);
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/projects/project_lib.php';
require_once __DIR__ . '/../modules/projects/project_funding_accounting.php';
require_once __DIR__ . '/../modules/hr/lib_payroll_policy.php';
require_once __DIR__ . '/../modules/hr/lib_employee_incentives.php';

function hrPrj24RecoveryAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hrPrj24RecoveryOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hrPrj24RecoveryCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$pdo = db();
$dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
hrPrj24RecoveryAssert(
    $dbName === 'ahl_el_kheir_incentive_test_20261015_r1',
    'Wrong database; expected ahl_el_kheir_incentive_test_20261015_r1, got ' . ($dbName !== '' ? $dbName : '(NULL)')
);
$currency = strtoupper(trim((string)APP_CURRENCY_CODE));
hrPrj24RecoveryAssert($currency === 'SDG', 'Expected system ledger currency SDG; no changes made.');

$committed = false;
try {
    $pdo->beginTransaction();

    // Lock and validate the exact source event, project lifecycle, and policy snapshot.
    $event = hrPrj24RecoveryOne(
        $pdo,
        "SELECT ev.id, ev.source_table, ev.source_record_id, ev.incentive_type,
                ev.policy_version_id, ev.occurred_at, ev.status, ev.verification_note,
                pv.version_no, pv.policy_name, pv.status AS policy_status,
                pv.effective_from, pv.effective_to,
                r.reward_amount, r.currency_code, r.is_enabled
         FROM hr_incentive_events ev
         JOIN hr_incentive_policy_versions pv ON pv.id = ev.policy_version_id
         JOIN hr_incentive_policy_rules r
           ON r.policy_version_id = ev.policy_version_id
          AND r.incentive_type = ev.incentive_type
         WHERE ev.id = 34
           AND ev.source_table = 'project_lifecycle'
           AND ev.source_record_id = 24
           AND ev.incentive_type = 'project_completion'
         LIMIT 1 FOR UPDATE"
    );
    hrPrj24RecoveryAssert($event !== null, 'Expected project-completion event 34 for project 24 was not found.');
    hrPrj24RecoveryAssert(
        (int)$event['policy_version_id'] === 11
        && (int)$event['version_no'] === 1
        && (string)$event['policy_name'] === 'ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-15-R5'
        && $event['policy_status'] === 'active'
        && $event['status'] === 'verified'
        && strpos((string)$event['verification_note'], 'system:project-recipient-snapshot-v1') !== false,
        'Event/policy/snapshot no longer matches the reviewed R5 acceptance evidence.'
    );
    hrPrj24RecoveryAssert(
        (string)$event['effective_from'] <= substr((string)$event['occurred_at'], 0, 10)
        && (empty($event['effective_to']) || (string)$event['effective_to'] >= substr((string)$event['occurred_at'], 0, 10))
        && (int)$event['is_enabled'] === 1
        && round((float)$event['reward_amount'], 2) === 1500.00
        && strtoupper(trim((string)$event['currency_code'])) === $currency,
        'R5 project-completion reward is not effective/enabled at the recorded closure or no longer matches 1,500.00 SDG.'
    );

    $project = hrPrj24RecoveryOne(
        $pdo,
        "SELECT pl.project_id, pl.lifecycle_status, pl.closed_at, pl.closed_by,
                op.status AS project_status
         FROM project_lifecycle pl
         JOIN other_projects op ON op.id = pl.project_id
         WHERE pl.project_id = 24
         LIMIT 1 FOR UPDATE"
    );
    hrPrj24RecoveryAssert(
        $project !== null
        && $project['lifecycle_status'] === 'closed'
        && $project['project_status'] === 'completed'
        && (string)$project['closed_at'] === (string)$event['occurred_at'],
        'Project 24 is not in the exact previously reviewed closed/completed state.'
    );
    hrPrj24RecoveryAssert(
        hrPrj24RecoveryCount(
            $pdo,
            "SELECT COUNT(*) FROM audit_log
             WHERE action = 'FM_FINANCIAL_CLOSURE'
               AND entity_type = 'project_lifecycle' AND entity_id = 24"
        ) >= 1,
        'Durable FM financial-closure audit evidence is missing.'
    );
    hrPrj24RecoveryAssert(
        hrPrj24RecoveryCount(
            $pdo,
            "SELECT COUNT(*) FROM project_funding_allocations
             WHERE project_id = 24 AND status = 'draft'"
        ) === 0,
        'Project 24 still has draft funding allocations.'
    );
    $balance = (float)akp_project_controlled_balance(24);
    hrPrj24RecoveryAssert(is_finite($balance) && abs($balance) <= 0.009, 'Project controlled balance is not zero within the existing tolerance.');

    // Confirm the exact primary-supervisor assignment was active at closure.
    $assignment = hrPrj24RecoveryOne(
        $pdo,
        "SELECT psa.id, psa.project_id, psa.supervisor_user_id, psa.assigned_at,
                psa.ended_at, psa.notes
         FROM project_supervisor_assignments psa
         WHERE psa.id = 18 AND psa.project_id = 24 AND psa.supervisor_user_id = 34
           AND psa.assigned_at <= ?
           AND (psa.ended_at IS NULL OR psa.ended_at > ?)
           AND psa.id = (
               SELECT MAX(psa2.id)
               FROM project_supervisor_assignments psa2
               WHERE psa2.project_id = 24 AND psa2.assigned_at <= ?
           )
         LIMIT 1 FOR UPDATE",
        [(string)$project['closed_at'], (string)$project['closed_at'], (string)$project['closed_at']]
    );
    hrPrj24RecoveryAssert($assignment !== null, 'Supervisor assignment 18 was not the latest assignment active at project closure.');

    $supervisor = hrPrj24RecoveryOne(
        $pdo,
        "SELECT u.id AS user_id, u.is_active AS user_active, r.code AS role_code,
                e.id AS employee_id, e.user_id AS employee_user_id,
                es.category AS employment_category, es.is_active AS employment_state_active
         FROM users u
         JOIN roles r ON r.id = u.role_id
         JOIN employees e ON e.user_id = u.id
         JOIN hr_employment_states es ON es.id = e.employment_state_id
         WHERE u.id = 34 AND e.id = 22
         LIMIT 1 FOR UPDATE"
    );
    hrPrj24RecoveryAssert(
        $supervisor !== null
        && (int)$supervisor['user_id'] === 34
        && (int)$supervisor['employee_id'] === 22
        && (int)$supervisor['employee_user_id'] === 34
        && (int)$supervisor['user_active'] === 1
        && $supervisor['role_code'] === 'project_supervisor'
        && $supervisor['employment_category'] === 'working'
        && (int)$supervisor['employment_state_active'] === 1,
        'PS1 user/employee mapping, role, or active working eligibility no longer matches reviewed evidence.'
    );
    hrPrj24RecoveryAssert(
        hrPrj24RecoveryCount(
            $pdo,
            "SELECT COUNT(*) FROM project_team
             WHERE project_id = 24 AND user_id = 34 AND unassigned_at IS NULL"
        ) === 0,
        'PS1 is now an active project_team member; this is no longer the reviewed supervisor-only omission.'
    );

    // Refuse to touch any different recipient set or pre-existing recovery.
    hrPrj24RecoveryAssert(
        hrPrj24RecoveryCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_project_allocations WHERE event_id = 34') === 2,
        'Event 34 no longer has exactly the two previously reviewed allocations.'
    );
    $expectedRecipients = [
        ['employee_id' => 15, 'recipient_user_id' => 16],
        ['employee_id' => 17, 'recipient_user_id' => 28],
    ];
    $existingAllocations = $pdo->query(
        'SELECT employee_id, recipient_user_id FROM hr_incentive_project_allocations WHERE event_id = 34 ORDER BY employee_id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $actualRecipients = array_map(
        static fn(array $row): array => [
            'employee_id' => (int)$row['employee_id'],
            'recipient_user_id' => (int)$row['recipient_user_id'],
        ],
        $existingAllocations
    );
    usort($expectedRecipients, static fn(array $a, array $b): int => $a['employee_id'] <=> $b['employee_id']);
    hrPrj24RecoveryAssert($actualRecipients === $expectedRecipients, 'Existing event 34 recipients differ from the reviewed two-recipient snapshot.');

    hrPrj24RecoveryAssert(
        hrPrj24RecoveryCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_project_allocations WHERE event_id = 34 AND employee_id = 22') === 0
        && hrPrj24RecoveryCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_awards WHERE event_id = 34 AND employee_id = 22') === 0,
        'PS1 already has an allocation or award; duplicate recovery is refused.'
    );
    hrPrj24RecoveryAssert(
        hrPrj24RecoveryCount(
            $pdo,
            "SELECT COUNT(*)
             FROM hr_incentive_awards a
             JOIN hr_incentive_project_allocations pa ON pa.id = a.project_allocation_id
             WHERE pa.event_id = 34 AND a.status = 'scheduled'
               AND a.payroll_id IS NOT NULL AND a.recognition_journal_entry_id IS NOT NULL"
        ) === 2,
        'The two existing recipient awards are not both in the previously reviewed scheduled/accounted state.'
    );
    hrPrj24RecoveryAssert(
        hrPrj24RecoveryCount(
            $pdo,
            "SELECT COUNT(*)
             FROM hr_incentive_notification_outbox o
             JOIN hr_incentive_awards a ON a.id = o.award_id
             JOIN hr_incentive_project_allocations pa ON pa.id = a.project_allocation_id
             WHERE pa.event_id = 34 AND o.status = 'sent' AND o.notification_id IS NOT NULL"
        ) === 2,
        'The two existing earned notices are not both already sent.'
    );
    hrPrj24RecoveryAssert(
        hrPrj24RecoveryCount($pdo, "SELECT COUNT(*) FROM hr_incentive_notification_outbox WHERE status = 'pending'") === 0,
        'There are already pending incentive notices; refusing to dispatch unrelated notices during this recovery.'
    );

    // Schema gates: the production path deliberately does not fabricate a human approver.
    hrPrj24RecoveryAssert(
        hrPrj24RecoveryCount(
            $pdo,
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'hr_incentive_project_allocations'
               AND COLUMN_NAME IN ('approved_by_user_id','approved_at')
               AND IS_NULLABLE = 'YES'"
        ) === 2,
        'Allocation approval metadata is not nullable as required by the existing source path.'
    );
    hrPrj24RecoveryAssert(
        hrPrj24RecoveryCount(
            $pdo,
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'hr_incentive_awards'
               AND COLUMN_NAME = 'recognition_journal_entry_id'"
        ) === 1,
        'Expected recognition_journal_entry_id field is absent.'
    );

    echo "PREFLIGHT PASS | DB={$dbName} | project=24 | event=34 | PS1=user34/employee22 | reward=1500.00 SDG | balance=" . number_format($balance, 3, '.', '') . "\n";

    $allocationNote = 'Historical recovery after source fix dd5c072c: primary Project Supervisor assignment #18 was active at closure but omitted from the original snapshot. System recovery; no human approval is claimed.';
    $insertAllocation = $pdo->prepare(
        "INSERT INTO hr_incentive_project_allocations
            (event_id, employee_id, recipient_user_id, allocation_percent, reward_amount,
             currency_code, approved_by_user_id, approved_at, allocation_note)
         VALUES (34, 22, 34, 100.00, 1500.00, 'SDG', NULL, NULL, ?)"
    );
    $insertAllocation->execute([$allocationNote]);
    $allocationId = (int)$pdo->lastInsertId();
    hrPrj24RecoveryAssert($allocationId > 0, 'Could not insert the single PS1 historical allocation.');

    // Preserve an explicit audit record without falsely attributing the recovery to a human user.
    $auditNew = json_encode([
        'event_id' => 34,
        'project_id' => 24,
        'allocation_id' => $allocationId,
        'employee_id' => 22,
        'recipient_user_id' => 34,
        'allocation_percent' => 100.00,
        'reward_amount' => 1500.00,
        'currency_code' => 'SDG',
        'source_assignment_id' => 18,
        'reason' => 'Historical recovery after supervisor-only recipient omission; no human approval claimed.',
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $audit = $pdo->prepare(
        "INSERT INTO audit_log
            (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
         VALUES (NULL, 'HR_INCENTIVE_HISTORICAL_RECOVERY', 'hr_incentive_project_allocations', ?, ?, ?, NULL, ?)"
    );
    $audit->execute([
        $allocationId,
        json_encode(['allocation_exists' => false, 'event_id' => 34], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $auditNew,
        'CLI recovery tool; commit dd5c072c; disposable acceptance database',
    ]);

    // Reuse the existing gated, idempotent award/accounting/payroll/outbox processor.
    $processed = hrIncentiveProcessProjectCompletion($pdo, 24, $balance, 0);
    hrPrj24RecoveryAssert($processed === 3, 'Normal project-completion processor did not process exactly three allocations.');

    $award = hrPrj24RecoveryOne(
        $pdo,
        "SELECT id, event_id, employee_id, recipient_user_id, project_allocation_id,
                reward_amount, reward_currency_code, status, payroll_id, recognition_journal_entry_id
         FROM hr_incentive_awards
         WHERE event_id = 34 AND employee_id = 22
         LIMIT 1 FOR UPDATE"
    );
    hrPrj24RecoveryAssert(
        $award !== null
        && (int)$award['recipient_user_id'] === 34
        && (int)$award['project_allocation_id'] === $allocationId
        && round((float)$award['reward_amount'], 2) === 1500.00
        && strtoupper((string)$award['reward_currency_code']) === 'SDG'
        && in_array($award['status'], ['earned', 'scheduled'], true)
        && (int)$award['recognition_journal_entry_id'] > 0,
        'PS1 award was not created and recognized correctly by the existing processor.'
    );
    $journal = hrPrj24RecoveryOne(
        $pdo,
        "SELECT id, status, reference_type, reference_id
         FROM journal_entries WHERE id = ? LIMIT 1 FOR UPDATE",
        [(int)$award['recognition_journal_entry_id']]
    );
    hrPrj24RecoveryAssert(
        $journal !== null
        && $journal['status'] === 'posted'
        && $journal['reference_type'] === 'hr_incentive_award_recognition'
        && (int)$journal['reference_id'] === (int)$award['id'],
        'PS1 recognition journal is missing or references the wrong award.'
    );
    $lines = $pdo->prepare(
        "SELECT a.code, SUM(jl.debit) AS debit_total, SUM(jl.credit) AS credit_total
         FROM journal_lines jl
         JOIN accounts a ON a.id = jl.account_id
         WHERE jl.entry_id = ?
         GROUP BY a.code"
    );
    $lines->execute([(int)$journal['id']]);
    $lineMap = [];
    foreach ($lines->fetchAll(PDO::FETCH_ASSOC) as $line) {
        $lineMap[(string)$line['code']] = [
            'debit' => round((float)$line['debit_total'], 2),
            'credit' => round((float)$line['credit_total'], 2),
        ];
    }
    hrPrj24RecoveryAssert(
        isset($lineMap['5500'], $lineMap['2400'])
        && $lineMap['5500'] === ['debit' => 1500.00, 'credit' => 0.00]
        && $lineMap['2400'] === ['debit' => 0.00, 'credit' => 1500.00],
        'PS1 recognition journal does not balance the full reward to accounts 5500/2400.'
    );

    $outbox = hrPrj24RecoveryOne(
        $pdo,
        "SELECT id, award_id, recipient_user_id, status
         FROM hr_incentive_notification_outbox
         WHERE award_id = ? LIMIT 1 FOR UPDATE",
        [(int)$award['id']]
    );
    hrPrj24RecoveryAssert(
        $outbox !== null
        && (int)$outbox['recipient_user_id'] === 34
        && $outbox['status'] === 'pending',
        'PS1 earned-notice outbox row was not queued exactly at recognition.'
    );
    hrPrj24RecoveryAssert(
        hrPrj24RecoveryCount(
            $pdo,
            "SELECT COUNT(*) FROM audit_log
             WHERE action = 'HR_INCENTIVE_HISTORICAL_RECOVERY'
               AND entity_type = 'hr_incentive_project_allocations'
               AND entity_id = ?",
            [$allocationId]
        ) === 1,
        'Historical recovery audit row was not recorded.'
    );

    $pdo->commit();
    $committed = true;

    // Dispatch only when there was no pre-existing pending incentive outbox item.
    hrIncentiveDispatchPendingNotifications($pdo);

    $delivery = hrPrj24RecoveryOne(
        $pdo,
        "SELECT o.status, o.notification_id, o.attempt_count, o.last_error,
                n.recipient_user_id AS notification_recipient, n.type AS notification_type
         FROM hr_incentive_notification_outbox o
         LEFT JOIN notifications n ON n.id = o.notification_id
         WHERE o.id = ? LIMIT 1",
        [(int)$outbox['id']]
    );
    $finalAward = hrPrj24RecoveryOne(
        $pdo,
        "SELECT id, status, payroll_id, recognition_journal_entry_id
         FROM hr_incentive_awards WHERE id = ? LIMIT 1",
        [(int)$award['id']]
    );
    if ($delivery && $delivery['status'] === 'sent'
        && (int)$delivery['notification_recipient'] === 34
        && $delivery['notification_type'] === 'incentive') {
        echo "PASS | historical_allocation={$allocationId} | award=" . (int)$finalAward['id']
            . " | reward=1500.00 SDG | status=" . $finalAward['status']
            . " | recognition_journal=" . (int)$finalAward['recognition_journal_entry_id']
            . " | outbox=sent | notification=" . (int)$delivery['notification_id']
            . " | audit=recorded\n";
        exit(0);
    }

    fwrite(STDERR, "PARTIAL | allocation/award/accounting committed and verified; employee notice remains pending.\n");
    fwrite(STDERR, "outbox=" . (string)($delivery['status'] ?? 'missing')
        . " | last_error=" . (string)($delivery['last_error'] ?? '') . "\n");
    exit(3);
} catch (Throwable $e) {
    if (!$committed && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, ($committed ? "PARTIAL" : "BLOCKED") . " | PRJ-0024 PS1 historical incentive recovery | " . $e->getMessage() . "\n");
    exit($committed ? 3 : 1);
}
