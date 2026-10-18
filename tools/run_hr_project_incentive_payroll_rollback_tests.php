<?php
declare(strict_types=1);

/**
 * Project-completion incentive-to-payroll integration test.
 *
 * Uses one synthetic project and an eligible Project Supervisor inside a single
 * transaction on the named disposable incentive test database. No project,
 * team, audit, award, journal, payroll or notification changes are committed.
 * The fixture has no funding, expenses or returns; it does not invent financial
 * activity or edit posted accounting records.
 */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/projects/project_lib.php';
require_once __DIR__ . '/../modules/projects/project_funding_accounting.php';
require_once __DIR__ . '/../modules/hr/lib_payroll_policy.php';
require_once __DIR__ . '/../modules/hr/lib_employee_incentives.php';

$pdo = db();

function hrProjectIncentiveSuccessAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hrProjectIncentiveSuccessOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hrProjectIncentiveSuccessCount(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function hrProjectIncentiveSuccessSkip(string $reason): never
{
    echo "SKIP | Project incentive successful-path rollback test | {$reason}\n";
    exit(0);
}

$dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if ($dbName !== 'ahl_el_kheir_incentive_test_20261015_r1') {
    fwrite(STDERR, "BLOCKED | This harness only runs against ahl_el_kheir_incentive_test_20261015_r1; current database is {$dbName}.\n");
    exit(2);
}

$currency = strtoupper(trim((string)APP_CURRENCY_CODE));
if (!preg_match('/^[A-Z]{3}$/', $currency)) {
    hrProjectIncentiveSuccessSkip('APP_CURRENCY_CODE is not a valid three-letter currency code.');
}

$policy = hrProjectIncentiveSuccessOne(
    $pdo,
    "SELECT p.id, p.version_no, p.policy_name, p.effective_from, p.effective_to,
            r.reward_amount, r.currency_code
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
    hrProjectIncentiveSuccessSkip('No active effective project-completion rule matches the ledger currency.');
}
if ((string)$policy['policy_name'] !== 'ACCEPTANCE TEST - INCENTIVE POLICY - 2026-10-15-R5'
    || (int)$policy['version_no'] !== 1) {
    fwrite(STDERR, "BLOCKED | Active policy is not the protected R5 acceptance policy; no policy was changed.\n");
    exit(2);
}

$schemaChecks = [
    'approval metadata nullable' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hr_incentive_project_allocations' AND COLUMN_NAME IN ('approved_by_user_id','approved_at') AND IS_NULLABLE = 'YES'",
    'recognition journal field' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hr_incentive_awards' AND COLUMN_NAME = 'recognition_journal_entry_id'",
    'settlement journal field' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hr_incentive_awards' AND COLUMN_NAME = 'settlement_journal_entry_id'",
];
foreach ($schemaChecks as $label => $sql) {
    $expected = $label === 'approval metadata nullable' ? 2 : 1;
    if (hrProjectIncentiveSuccessCount($pdo, $sql) !== $expected) {
        hrProjectIncentiveSuccessSkip("Required schema gate failed: {$label}. Apply the relevant committed migrations to the disposable database first.");
    }
}

foreach ([['5500', 'expense'], ['2400', 'liability']] as [$code, $type]) {
    if (hrProjectIncentiveSuccessCount(
        $pdo,
        'SELECT COUNT(*) FROM accounts WHERE code = ? AND account_type = ? AND is_active = 1',
        [$code, $type]
    ) !== 1) {
        hrProjectIncentiveSuccessSkip("Required active account {$code} ({$type}) is missing or has the wrong type.");
    }
}

$projectSupervisor = hrProjectIncentiveSuccessOne(
    $pdo,
    "SELECT u.id AS user_id, e.id AS employee_id
     FROM users u
     JOIN roles r ON r.id = u.role_id AND r.code = 'project_supervisor'
     JOIN employees e ON e.user_id = u.id
     JOIN hr_employment_states es ON es.id = e.employment_state_id
     WHERE u.is_active = 1 AND es.category = 'working' AND es.is_active = 1
     ORDER BY u.id
     LIMIT 1"
);
if (!$projectSupervisor) {
    hrProjectIncentiveSuccessSkip('No active Project Supervisor is linked to an active working employee.');
}

$payrollFixture = hrProjectIncentiveSuccessOne(
    $pdo,
    "SELECT p.id AS payroll_id, p.incentive_amount, p.net_salary,
            p.month, p.year, s.salary_currency
     FROM payroll p
     JOIN hr_employee_salary_history s ON s.id = (
         SELECT s2.id FROM hr_employee_salary_history s2
         WHERE s2.employee_id = p.employee_id
           AND s2.effective_from <= LAST_DAY(STR_TO_DATE(CONCAT(p.year,'-',LPAD(p.month,2,'0'),'-01'),'%Y-%m-%d'))
           AND (s2.effective_to IS NULL OR s2.effective_to >= LAST_DAY(STR_TO_DATE(CONCAT(p.year,'-',LPAD(p.month,2,'0'),'-01'),'%Y-%m-%d')))
         ORDER BY s2.effective_from DESC, s2.id DESC LIMIT 1
     )
     WHERE p.employee_id = ?
       AND p.status = 'draft'
       AND (p.year > YEAR(CURDATE()) OR (p.year = YEAR(CURDATE()) AND p.month >= MONTH(CURDATE())))
       AND UPPER(TRIM(s.salary_currency)) = ?
       AND NOT EXISTS (
           SELECT 1 FROM hr_incentive_awards a
           JOIN hr_incentive_events e ON e.id = a.event_id
           WHERE a.employee_id = p.employee_id
             AND a.payroll_id IS NULL
             AND a.status IN ('earned','held_currency')
             AND e.status = 'verified'
       )
     ORDER BY p.year, p.month, p.id
     LIMIT 1",
    [$projectSupervisor['employee_id'], $currency]
);

$payrollSeed = null;
if ($payrollFixture) {
    $payrollId = (int)$payrollFixture['payroll_id'];
    $originalPayrollIncentive = round((float)$payrollFixture['incentive_amount'], 2);
    $originalPayrollNet = round((float)$payrollFixture['net_salary'], 2);
} else {
    // If no current/future draft exists, prepare to create one inside the same
    // rollback transaction, matching the production payroll-generation INSERT.
    $periodStart = date('Y-m-01');
    $periodEnd = date('Y-m-t');
    $payrollMonth = (int)date('n');
    $payrollYear = (int)date('Y');
    $payrollPolicy = hrPayrollPolicyGetActive($pdo, $periodEnd);
    if (!$payrollPolicy) {
        hrProjectIncentiveSuccessSkip('No effective payroll policy exists for the current payroll period.');
    }

    $salaryFixture = hrProjectIncentiveSuccessOne(
        $pdo,
        "SELECT e.hire_date, s.basic_salary, s.salary_currency
         FROM employees e
         JOIN hr_employee_salary_history s ON s.id = (
             SELECT s2.id FROM hr_employee_salary_history s2
             WHERE s2.employee_id = e.id
               AND s2.effective_from <= ?
               AND (s2.effective_to IS NULL OR s2.effective_to >= ?)
             ORDER BY s2.effective_from DESC, s2.id DESC
             LIMIT 1
         )
         WHERE e.id = ?
           AND (e.hire_date IS NULL OR e.hire_date <= ?)
         LIMIT 1",
        [$periodEnd, $periodEnd, $projectSupervisor['employee_id'], $periodEnd]
    );
    $employmentState = hrProjectIncentiveSuccessOne(
        $pdo,
        "SELECT s.category
         FROM hr_employee_state_history h
         JOIN hr_employment_states s ON s.id = h.employment_state_id
         WHERE h.employee_id = ?
           AND h.effective_from <= ?
           AND (h.effective_to IS NULL OR h.effective_to >= ?)
         ORDER BY h.effective_from DESC, h.id DESC
         LIMIT 1",
        [$projectSupervisor['employee_id'], $periodEnd . ' 23:59:59', $periodEnd . ' 00:00:00']
    );
    if (!$salaryFixture
        || (float)$salaryFixture['basic_salary'] <= 0
        || strtoupper(trim((string)$salaryFixture['salary_currency'])) !== $currency
        || !$employmentState
        || (string)$employmentState['category'] !== 'working') {
        hrProjectIncentiveSuccessSkip('Selected Project Supervisor is not eligible for a current-period payroll draft under the production salary/state/currency rules.');
    }
    if (hrProjectIncentiveSuccessCount(
        $pdo,
        'SELECT COUNT(*) FROM payroll WHERE employee_id = ? AND month = ? AND year = ?',
        [$projectSupervisor['employee_id'], $payrollMonth, $payrollYear]
    ) !== 0) {
        hrProjectIncentiveSuccessSkip('A payroll row already exists for the selected employee and current period, but it is not an eligible draft fixture.');
    }
    if (hrProjectIncentiveSuccessCount(
        $pdo,
        "SELECT COUNT(*) FROM hr_incentive_awards a
         JOIN hr_incentive_events e ON e.id = a.event_id
         WHERE a.employee_id = ?
           AND a.payroll_id IS NULL
           AND a.status IN ('earned','held_currency')
           AND e.status = 'verified'",
        [$projectSupervisor['employee_id']]
    ) !== 0) {
        hrProjectIncentiveSuccessSkip('Selected Project Supervisor has another verified unscheduled award; refusing to mix it into this focused fixture.');
    }

    $payrollSeed = [
        'employee_id' => (int)$projectSupervisor['employee_id'],
        'month' => $payrollMonth,
        'year' => $payrollYear,
        'basic_salary' => round((float)$salaryFixture['basic_salary'], 2),
        'policy_id' => (int)$payrollPolicy['id'],
    ];
    $payrollId = 0;
    $originalPayrollIncentive = 0.00;
    $originalPayrollNet = $payrollSeed['basic_salary'];
}

$projectManager = hrProjectIncentiveSuccessOne(
    $pdo,
    "SELECT u.id AS user_id
     FROM users u
     JOIN roles r ON r.id = u.role_id AND r.code = 'projects_manager'
     WHERE u.is_active = 1
     ORDER BY u.id
     LIMIT 1"
);
if (!$projectManager) {
    hrProjectIncentiveSuccessSkip('No active Projects Manager account is available for the isolated project fixture.');
}

$actorId = (int)$projectManager['user_id'];
$supervisorUserId = (int)$projectSupervisor['user_id'];
$supervisorEmployeeId = (int)$projectSupervisor['employee_id'];
$projectId = 0;
$eventId = 0;
$awardIds = [];
$journalIds = [];
$allocationCount = 0;
$incentiveNotificationCountBefore = 0;
$rewardAmount = round((float)$policy['reward_amount'], 2);

try {
    $pdo->beginTransaction();

    if ($payrollSeed !== null) {
        $insertPayroll = $pdo->prepare(
            "INSERT INTO payroll
                (employee_id, month, year, basic_salary, allowances, overtime,
                 deductions, net_salary, status, payroll_policy_version_id)
             VALUES (?, ?, ?, ?, 0, 0, 0, ?, 'draft', ?)"
        );
        $insertPayroll->execute([
            $payrollSeed['employee_id'],
            $payrollSeed['month'],
            $payrollSeed['year'],
            $payrollSeed['basic_salary'],
            $payrollSeed['basic_salary'],
            $payrollSeed['policy_id'],
        ]);
        $payrollId = (int)$pdo->lastInsertId();
        hrProjectIncentiveSuccessAssert($payrollId > 0, 'Could not create the temporary current-period payroll draft.');
    }

    // Match the existing project creation INSERT; every fixture row is rolled back.
    $insertProject = $pdo->prepare(
        "INSERT INTO other_projects
            (name, project_code, description, target_amount, currency_code,
             start_date, end_date, status, created_by, project_type, location,
             city, district, total_beneficiaries)
         VALUES (?, NULL, ?, ?, ?, CURDATE(), NULL, 'planned', ?, NULL, NULL, NULL, NULL, 0)"
    );
    $insertProject->execute([
        'ROLLBACK-ONLY INCENTIVE ACCEPTANCE FIXTURE',
        'Synthetic no-financial-activity fixture; transaction must roll back.',
        1.00,
        $currency,
        $actorId,
    ]);
    $projectId = (int)$pdo->lastInsertId();
    hrProjectIncentiveSuccessAssert($projectId > 0, 'Could not create the temporary project fixture.');

    $projectCode = 'PRJ-' . str_pad((string)$projectId, 4, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE other_projects SET project_code = ? WHERE id = ?')
        ->execute([$projectCode, $projectId]);

    $pdo->prepare(
        "INSERT INTO project_lifecycle (project_id, lifecycle_status, final_budget_amount)
         VALUES (?, 'planned', ?)"
    )->execute([$projectId, 1.00]);
    $pdo->prepare(
        "INSERT INTO project_approval (project_id, approval_status) VALUES (?, 'draft')"
    )->execute([$projectId]);

    // The eligible PS is assigned through the dedicated supervisor table only.
    // Deliberately do not add this user to project_team: this is the regression case.
    $pdo->prepare(
        "INSERT INTO project_supervisor_assignments
            (project_id, supervisor_user_id, assigned_by, notes)
         VALUES (?, ?, ?, 'Rollback-only incentive fixture; supervisor-only assignment before closure')"
    )->execute([$projectId, $supervisorUserId, $actorId]);
    hrProjectIncentiveSuccessAssert(
        hrProjectIncentiveSuccessCount(
            $pdo,
            'SELECT COUNT(*) FROM project_team WHERE project_id = ? AND user_id = ? AND unassigned_at IS NULL',
            [$projectId, $supervisorUserId]
        ) === 0,
        'Regression fixture accidentally placed the Project Supervisor in project_team.'
    );

    // Confirm the test project has no financial activity; no accounting rows are fabricated.
    $financialRows = [
        'posted funding' => ['SELECT COUNT(*) FROM project_funding_allocations WHERE project_id = ? AND status = \'posted\'', [$projectId]],
        'posted expenses' => ['SELECT COUNT(*) FROM project_expenses WHERE project_id = ? AND status = \'posted\'', [$projectId]],
        'funding returns' => ['SELECT COUNT(*) FROM project_funding_returns WHERE project_id = ?', [$projectId]],
    ];
    foreach ($financialRows as $label => [$sql, $params]) {
        hrProjectIncentiveSuccessAssert(
            hrProjectIncentiveSuccessCount($pdo, $sql, $params) === 0,
            "Synthetic fixture unexpectedly has {$label}."
        );
    }
    hrProjectIncentiveSuccessAssert(abs(akp_project_controlled_balance($projectId)) < 0.005, 'New fixture does not have a zero controlled balance.');

    // Model the persisted state reached after PM administrative closure.
    $pdo->prepare(
        "UPDATE project_lifecycle
         SET lifecycle_status = 'closed', closed_at = NOW(), closed_by = ?, closure_summary = ?
         WHERE project_id = ? AND lifecycle_status = 'planned'"
    )->execute([$actorId, 'Rollback-only synthetic closure for incentive acceptance.', $projectId]);
    $pdo->prepare(
        "UPDATE other_projects SET status = 'completed', updated_by = ? WHERE id = ?"
    )->execute([$actorId, $projectId]);

    $incentiveNotificationCountBefore = hrProjectIncentiveSuccessCount(
        $pdo,
        "SELECT COUNT(*) FROM notifications WHERE recipient_user_id = ? AND type = 'incentive'",
        [$supervisorUserId]
    );
    $eventId = hrIncentivePrepareProjectCompletion($pdo, $projectId, $actorId);
    hrProjectIncentiveSuccessAssert($eventId > 0, 'Project completion event was not created.');

    $incentiveNotificationCountAfterSnapshot = hrProjectIncentiveSuccessCount(
        $pdo,
        "SELECT COUNT(*) FROM notifications WHERE recipient_user_id = ? AND type = 'incentive'",
        [$supervisorUserId]
    );
    hrProjectIncentiveSuccessAssert(
        $incentiveNotificationCountAfterSnapshot === $incentiveNotificationCountBefore,
        'Project-completion notification was sent at PM administrative closure before FM final financial closure.'
    );

    $allocationRows = hrProjectIncentiveSuccessCount(
        $pdo,
        'SELECT COUNT(*) FROM hr_incentive_project_allocations WHERE event_id = ?',
        [$eventId]
    );
    hrProjectIncentiveSuccessAssert($allocationRows === 1, 'Expected exactly the eligible PS to be snapshotted in this fixture.');
    $allocation = hrProjectIncentiveSuccessOne(
        $pdo,
        'SELECT employee_id, recipient_user_id, allocation_percent, reward_amount, currency_code, approved_by_user_id, approved_at FROM hr_incentive_project_allocations WHERE event_id = ? LIMIT 1',
        [$eventId]
    );
    hrProjectIncentiveSuccessAssert(
        $allocation
        && (int)$allocation['employee_id'] === $supervisorEmployeeId
        && (int)$allocation['recipient_user_id'] === $supervisorUserId,
        'Project Supervisor was not included in the closure-time recipient snapshot.'
    );
    hrProjectIncentiveSuccessAssert(
        (float)$allocation['allocation_percent'] === 100.0
        && round((float)$allocation['reward_amount'], 2) === $rewardAmount
        && strtoupper((string)$allocation['currency_code']) === strtoupper((string)$policy['currency_code']),
        'Project Supervisor allocation does not equal the full configured policy reward.'
    );
    hrProjectIncentiveSuccessAssert(
        $allocation['approved_by_user_id'] === null && $allocation['approved_at'] === null,
        'System-generated allocation incorrectly claims human approval metadata.'
    );

    // Zero balance is not enough: without durable FM closure evidence, no award may issue.
    $blocked = hrIncentiveProcessProjectCompletion($pdo, $projectId, 0.00, $actorId);
    hrProjectIncentiveSuccessAssert($blocked === 0, 'Processor issued an award without FM financial-closure evidence.');
    hrProjectIncentiveSuccessAssert(
        hrProjectIncentiveSuccessCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_awards WHERE event_id = ?', [$eventId]) === 0,
        'Award exists before the FM financial-closure gate.'
    );

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
        'rollback-only project incentive success test',
    ]);

    $processed = hrIncentiveProcessProjectCompletion($pdo, $projectId, 0.00, $actorId);
    hrProjectIncentiveSuccessAssert($processed === $allocationRows, 'Processor did not award every closure-time allocation.');

    $pendingProjectNotices = hrProjectIncentiveSuccessCount(
        $pdo,
        "SELECT COUNT(*)
         FROM hr_incentive_notification_outbox o
         JOIN hr_incentive_awards a ON a.id = o.award_id
         WHERE a.event_id = ? AND o.recipient_user_id = ? AND o.status = 'pending'",
        [$eventId, $supervisorUserId]
    );
    hrProjectIncentiveSuccessAssert(
        $pendingProjectNotices === $allocationRows,
        'FM-gated award recognition did not queue one pending earned notice per eligible project recipient.'
    );
    hrProjectIncentiveSuccessAssert(
        hrProjectIncentiveSuccessCount(
            $pdo,
            "SELECT COUNT(*) FROM notifications WHERE recipient_user_id = ? AND type = 'incentive'",
            [$supervisorUserId]
        ) === $incentiveNotificationCountBefore,
        'Project earned notice was delivered before the surrounding FM closure transaction committed.'
    );

    $event = hrProjectIncentiveSuccessOne(
        $pdo,
        'SELECT status, verification_note FROM hr_incentive_events WHERE id = ?',
        [$eventId]
    );
    hrProjectIncentiveSuccessAssert(
        $event && $event['status'] === 'verified'
        && strpos((string)$event['verification_note'], 'system:project-recipient-snapshot-v1') !== false,
        'Project event did not reach verified state with the closure-time snapshot marker.'
    );

    $awards = $pdo->prepare(
        'SELECT id, employee_id, recipient_user_id, reward_amount, reward_currency_code, recognition_journal_entry_id FROM hr_incentive_awards WHERE event_id = ?'
    );
    $awards->execute([$eventId]);
    $awardRows = $awards->fetchAll(PDO::FETCH_ASSOC);
    hrProjectIncentiveSuccessAssert(count($awardRows) === $allocationRows, 'Award count does not match the allocation snapshot.');

    foreach ($awardRows as $award) {
        $awardId = (int)$award['id'];
        $awardIds[] = $awardId;
        hrProjectIncentiveSuccessAssert(
            (int)$award['employee_id'] === $supervisorEmployeeId
            && (int)$award['recipient_user_id'] === $supervisorUserId
            && round((float)$award['reward_amount'], 2) === $rewardAmount
            && strtoupper((string)$award['reward_currency_code']) === strtoupper((string)$policy['currency_code']),
            'Award recipient, amount or currency differs from the snapshotted policy allocation.'
        );
        $journalId = (int)$award['recognition_journal_entry_id'];
        hrProjectIncentiveSuccessAssert($journalId > 0, 'Award has no recognition journal.');
        $journalIds[] = $journalId;

        $journal = hrProjectIncentiveSuccessOne(
            $pdo,
            "SELECT id, status, reference_type, reference_id FROM journal_entries WHERE id = ?",
            [$journalId]
        );
        hrProjectIncentiveSuccessAssert(
            $journal && $journal['status'] === 'posted'
            && $journal['reference_type'] === 'hr_incentive_award_recognition'
            && (int)$journal['reference_id'] === $awardId,
            'Recognition journal is not posted or does not reference the exact award.'
        );

        $lines = $pdo->prepare(
            "SELECT a.code, SUM(jl.debit) AS debit_total, SUM(jl.credit) AS credit_total
             FROM journal_lines jl
             JOIN accounts a ON a.id = jl.account_id
             WHERE jl.entry_id = ?
             GROUP BY a.code"
        );
        $lines->execute([$journalId]);
        $lineRows = $lines->fetchAll(PDO::FETCH_ASSOC);
        $expense = null;
        $payable = null;
        foreach ($lineRows as $line) {
            if ((string)$line['code'] === '5500') { $expense = $line; }
            if ((string)$line['code'] === '2400') { $payable = $line; }
        }
        hrProjectIncentiveSuccessAssert(
            $expense && $payable
            && round((float)$expense['debit_total'], 2) === $rewardAmount
            && round((float)$expense['credit_total'], 2) === 0.00
            && round((float)$payable['credit_total'], 2) === $rewardAmount
            && round((float)$payable['debit_total'], 2) === 0.00,
            'Recognition journal does not debit account 5500 and credit account 2400 for the full award.'
        );
    }

    $scheduledAward = hrProjectIncentiveSuccessOne(
        $pdo,
        "SELECT id, status, payroll_id FROM hr_incentive_awards WHERE event_id = ?",
        [$eventId]
    );
    hrProjectIncentiveSuccessAssert(
        $scheduledAward !== null
        && $scheduledAward['status'] === 'scheduled'
        && (int)$scheduledAward['payroll_id'] === $payrollId,
        'Project-completion award was not attached to the selected current/future draft payroll.'
    );
    $refreshedPayroll = hrProjectIncentiveSuccessOne(
        $pdo,
        'SELECT incentive_amount, net_salary, status FROM payroll WHERE id = ?',
        [$payrollId]
    );
    hrProjectIncentiveSuccessAssert(
        $refreshedPayroll !== null
        && $refreshedPayroll['status'] === 'draft'
        && round((float)$refreshedPayroll['incentive_amount'], 2) === round($originalPayrollIncentive + $rewardAmount, 2)
        && round((float)$refreshedPayroll['net_salary'], 2) === $originalPayrollNet,
        'Draft payroll incentive component did not increase by the award while salary net remained unchanged.'
    );
    hrProjectIncentiveSuccessAssert(
        hrProjectIncentiveSuccessCount(
            $pdo,
            "SELECT COUNT(*) FROM hr_incentive_notification_outbox WHERE award_id = ? AND recipient_user_id = ? AND status = 'pending'",
            [(int)$scheduledAward['id'], $supervisorUserId]
        ) === 1,
        'Project award did not create exactly one pending post-commit notification outbox row.'
    );

    $auditCount = hrProjectIncentiveSuccessCount(
        $pdo,
        "SELECT COUNT(*) FROM audit_log WHERE action = 'FM_FINANCIAL_CLOSURE' AND entity_type = 'project_lifecycle' AND entity_id = ?",
        [$projectId]
    );
    hrProjectIncentiveSuccessAssert($auditCount === 1, 'Expected exactly one temporary FM financial-closure audit record.');

    $pdo->rollBack();

    if ($payrollSeed !== null) {
        hrProjectIncentiveSuccessAssert(
            hrProjectIncentiveSuccessCount($pdo, 'SELECT COUNT(*) FROM payroll WHERE id = ?', [$payrollId]) === 0,
            'Rollback left the temporary payroll draft behind.'
        );
    } else {
        $payrollAfterRollback = hrProjectIncentiveSuccessOne(
            $pdo,
            'SELECT incentive_amount, net_salary, status FROM payroll WHERE id = ?',
            [$payrollId]
        );
        hrProjectIncentiveSuccessAssert(
            $payrollAfterRollback !== null
            && $payrollAfterRollback['status'] === 'draft'
            && round((float)$payrollAfterRollback['incentive_amount'], 2) === $originalPayrollIncentive
            && round((float)$payrollAfterRollback['net_salary'], 2) === $originalPayrollNet,
            'Rollback did not restore the selected draft payroll amounts.'
        );
    }
    hrProjectIncentiveSuccessAssert(
        hrProjectIncentiveSuccessCount($pdo, 'SELECT COUNT(*) FROM other_projects WHERE id = ?', [$projectId]) === 0,
        'Rollback left the synthetic project behind.'
    );
    hrProjectIncentiveSuccessAssert(
        hrProjectIncentiveSuccessCount($pdo, "SELECT COUNT(*) FROM hr_incentive_events WHERE id = ?", [$eventId]) === 0,
        'Rollback left the incentive event behind.'
    );
    hrProjectIncentiveSuccessAssert(
        hrProjectIncentiveSuccessCount(
            $pdo,
            "SELECT COUNT(*) FROM notifications WHERE recipient_user_id = ? AND type = 'incentive'",
            [$supervisorUserId]
        ) === $incentiveNotificationCountBefore,
        'Rollback left the immediate incentive activity notice behind.'
    );
    foreach ($awardIds as $awardId) {
        hrProjectIncentiveSuccessAssert(
            hrProjectIncentiveSuccessCount($pdo, 'SELECT COUNT(*) FROM hr_incentive_awards WHERE id = ?', [$awardId]) === 0,
            'Rollback left an incentive award behind.'
        );
    }
    foreach ($journalIds as $journalId) {
        hrProjectIncentiveSuccessAssert(
            hrProjectIncentiveSuccessCount($pdo, 'SELECT COUNT(*) FROM journal_entries WHERE id = ?', [$journalId]) === 0,
            'Rollback left a recognition journal behind.'
        );
    }
    hrProjectIncentiveSuccessAssert(
        hrProjectIncentiveSuccessCount(
            $pdo,
            "SELECT COUNT(*) FROM audit_log WHERE action = 'FM_FINANCIAL_CLOSURE' AND entity_type = 'project_lifecycle' AND entity_id = ?",
            [$projectId]
        ) === 0,
        'Rollback left the temporary FM closure audit behind.'
    );

    echo "PASS | synthetic_project=no_financial_activity | PS_snapshot=PASS | full_policy_amount_per_employee=PASS\n";
    echo "PASS | missing_FM_gate_blocks_award=PASS | FM_gate=PASS | recognition_journal_5500_2400=PASS\n";
    echo "PASS | project_award_draft_payroll_link=PASS | incentive_total=PASS | net_salary_unchanged=PASS | outbox_pending=PASS | project_notice_waits_for_FM=PASS\n";
    echo "PASS | rollback_cleanup=PASS | temporary_payroll_rolled_back=" . ($payrollSeed !== null ? 'PASS' : 'NOT_NEEDED') . " | policy=R5 | reward=" . number_format($rewardAmount, 2, '.', '') . " " . strtoupper((string)$policy['currency_code']) . "\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL | Project incentive successful-path rollback test | " . $e->getMessage() . "\n");
    exit(1);
}
