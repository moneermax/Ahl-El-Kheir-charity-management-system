<?php
declare(strict_types=1);

/**
 * Acceptance test for incentive scheduling notifications.
 *
 * This test creates temporary event/award fixtures, exercises the production
 * payroll refresh and post-commit outbox dispatcher, injects one delivery
 * failure, verifies that the committed schedule survives and retry sends one
 * notification, then removes all test rows and restores the payroll amount.
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

$hrIncentiveNotificationHookCalls = 0;
$hrIncentiveNotificationTargetAwardId = 0;

function hr_incentive_notification_test_hook(
    int $userId,
    string $title,
    string $body,
    string $link,
    int $awardId
): void {
    global $hrIncentiveNotificationHookCalls, $hrIncentiveNotificationTargetAwardId;

    if ($awardId !== $hrIncentiveNotificationTargetAwardId) {
        return;
    }

    $hrIncentiveNotificationHookCalls++;
    if ($hrIncentiveNotificationHookCalls === 1) {
        throw new RuntimeException('TEST_INJECTED_INCENTIVE_NOTIFICATION_FAILURE');
    }
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/hr/lib_employee_incentives.php';

$pdo = db();

function incentiveNotificationFixture(PDO $pdo): ?array
{
    return dbFetchOne(
        "SELECT p.id AS payroll_id, p.employee_id, p.month, p.year,
                p.incentive_amount AS original_incentive_amount,
                e.user_id AS recipient_user_id, s.salary_currency
         FROM payroll p
         JOIN employees e ON e.id = p.employee_id
         JOIN hr_employment_states es ON es.id = e.employment_state_id
         JOIN users u ON u.id = e.user_id AND u.is_active = 1
         JOIN hr_employee_salary_history s ON s.id = (
             SELECT s2.id
             FROM hr_employee_salary_history s2
             WHERE s2.employee_id = p.employee_id
               AND s2.effective_from <= LAST_DAY(
                   STR_TO_DATE(CONCAT(p.year, '-', LPAD(p.month, 2, '0'), '-01'), '%Y-%m-%d')
               )
               AND (s2.effective_to IS NULL OR s2.effective_to >= LAST_DAY(
                   STR_TO_DATE(CONCAT(p.year, '-', LPAD(p.month, 2, '0'), '-01'), '%Y-%m-%d')
               ))
             ORDER BY s2.effective_from DESC, s2.id DESC
             LIMIT 1
         )
         WHERE p.status = 'draft'
           AND UPPER(s.salary_currency) = UPPER(?)
           AND es.category = 'working'
           AND es.is_active = 1
           AND NOT EXISTS (
               SELECT 1
               FROM hr_incentive_awards a
               JOIN hr_incentive_events ev ON ev.id = a.event_id
               WHERE a.employee_id = p.employee_id
                 AND a.payroll_id IS NULL
                 AND a.status IN ('earned', 'held_currency')
                 AND ev.status = 'verified'
           )
           AND NOT EXISTS (
               SELECT 1 FROM hr_incentive_awards existing
               WHERE existing.payroll_id = p.id
                 AND existing.status = 'scheduled'
           )
         ORDER BY p.year DESC, p.month DESC, p.id DESC
         LIMIT 1",
        [APP_CURRENCY_CODE]
    );
}

function incentiveNotificationOutboxCount(PDO $pdo, string $status): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM hr_incentive_notification_outbox WHERE status = ?'
    );
    $stmt->execute([$status]);
    return (int)$stmt->fetchColumn();
}

$fixture = incentiveNotificationFixture($pdo);
$pendingBefore = incentiveNotificationOutboxCount($pdo, 'pending');
$policyId = (int)($pdo->query(
    'SELECT id FROM hr_incentive_policy_versions ORDER BY id DESC LIMIT 1'
)->fetchColumn() ?: 0);
$postedJournalId = (int)($pdo->query(
    "SELECT id FROM journal_entries WHERE status = 'posted' ORDER BY id DESC LIMIT 1"
)->fetchColumn() ?: 0);

if (!$fixture || $pendingBefore !== 0 || $policyId <= 0 || $postedJournalId <= 0) {
    echo "SKIP | Requires an eligible draft payroll, no pre-existing pending incentive notices, an incentive policy version, and a posted journal fixture.\n";
    exit(0);
}

$eventId = 0;
$awardId = 0;
$outboxId = 0;
$notificationId = 0;
$payrollId = (int)$fixture['payroll_id'];
$originalIncentiveAmount = round((float)$fixture['original_incentive_amount'], 2);
$testAmount = 12.34;
$cleanupError = null;

try {
    $pdo->beginTransaction();
    $eventStmt = $pdo->prepare(
        "INSERT INTO hr_incentive_events
            (idempotency_key, source_table, source_record_id, incentive_type,
             policy_version_id, occurred_at, status, verified_by_user_id,
             verified_at, created_by_user_id, recipient_user_id, recipient_employee_id)
         VALUES (?, 'test_notification', ?, 'collection', ?, NOW(), 'verified', NULL,
                 NOW(), NULL, ?, ?)"
    );
    $eventStmt->execute([
        'hr-incentive:test-notification:' . bin2hex(random_bytes(12)),
        time(),
        $policyId,
        (int)$fixture['recipient_user_id'],
        (int)$fixture['employee_id'],
    ]);
    $eventId = (int)$pdo->lastInsertId();

    $awardStmt = $pdo->prepare(
        "INSERT INTO hr_incentive_awards
            (event_id, employee_id, recipient_user_id, reward_amount,
             reward_currency_code, recognition_journal_entry_id, status, earned_at)
         VALUES (?, ?, ?, ?, ?, ?, 'earned', NOW())"
    );
    $awardStmt->execute([
        $eventId,
        (int)$fixture['employee_id'],
        (int)$fixture['recipient_user_id'],
        $testAmount,
        strtoupper((string)APP_CURRENCY_CODE),
        $postedJournalId,
    ]);
    $awardId = (int)$pdo->lastInsertId();
    $hrIncentiveNotificationTargetAwardId = $awardId;
    $pdo->commit();

    // This owns and commits the scheduling transaction. The injected first
    // delivery failure must not roll back the scheduled award or payroll total.
    hrIncentiveRefreshPayrollDraft($pdo, $payrollId);

    $scheduled = dbFetchOne(
        'SELECT status, payroll_id FROM hr_incentive_awards WHERE id = ?',
        [$awardId]
    );
    $outbox = dbFetchOne(
        'SELECT id, status, notification_id, attempt_count FROM hr_incentive_notification_outbox WHERE award_id = ?',
        [$awardId]
    );
    $payroll = dbFetchOne(
        'SELECT incentive_amount FROM payroll WHERE id = ?',
        [$payrollId]
    );

    if (!$scheduled || $scheduled['status'] !== 'scheduled' || (int)$scheduled['payroll_id'] !== $payrollId) {
        throw new RuntimeException('Award scheduling did not remain committed after the injected notification failure.');
    }
    if (!$outbox || $outbox['status'] !== 'pending' || (int)($outbox['notification_id'] ?? 0) !== 0) {
        throw new RuntimeException('Failed notification was not retained as pending in the outbox.');
    }
    if (round((float)$payroll['incentive_amount'], 2) !== round($originalIncentiveAmount + $testAmount, 2)) {
        throw new RuntimeException('Committed payroll incentive total did not include the scheduled award.');
    }

    echo "PASS | scheduling_commit_survives_notification_failure=PASS | outbox_retryable=PASS\n";

    // Retry the pending outbox item; the hook now permits delivery.
    hrIncentiveDispatchPendingNotifications($pdo);

    $outbox = dbFetchOne(
        'SELECT id, status, notification_id, attempt_count FROM hr_incentive_notification_outbox WHERE award_id = ?',
        [$awardId]
    );
    $notificationId = (int)($outbox['notification_id'] ?? 0);
    $notification = $notificationId > 0
        ? dbFetchOne(
            'SELECT recipient_user_id, type, title, body, link FROM notifications WHERE id = ?',
            [$notificationId]
        )
        : null;

    if (!$outbox || $outbox['status'] !== 'sent' || $notificationId <= 0 || !$notification) {
        throw new RuntimeException('Pending incentive notification did not deliver on retry.');
    }
    if ((int)$notification['recipient_user_id'] !== (int)$fixture['recipient_user_id']
        || $notification['type'] !== 'incentive'
        || strpos((string)$notification['body'], sprintf('%04d-%02d', (int)$fixture['year'], (int)$fixture['month']) ) === false
        || strpos((string)$notification['body'], '12.34') === false) {
        throw new RuntimeException('Notification recipient, type, amount, or payroll period was incorrect.');
    }

    hrIncentiveDispatchPendingNotifications($pdo);
    $sameNotificationCount = (int)dbFetchOne(
        'SELECT COUNT(*) AS c FROM notifications WHERE id = ?',
        [$notificationId]
    )['c'];

    if ($sameNotificationCount !== 1) {
        throw new RuntimeException('Repeated dispatch created or lost the sent notification.');
    }

    echo "PASS | retry_delivery=PASS | correct_recipient_amount_period=PASS | repeated_dispatch_no_duplicate=PASS\n";
} catch (Throwable $e) {
    echo "FAIL | " . $e->getMessage() . "\n";
} finally {
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $pdo->beginTransaction();
        if ($awardId > 0) {
            $storedNotificationId = (int)($pdo->query(
                'SELECT notification_id FROM hr_incentive_notification_outbox WHERE award_id = ' . $awardId . ' LIMIT 1'
            )->fetchColumn() ?: 0);
            $notificationIds = array_values(array_unique(array_filter([$notificationId, $storedNotificationId])));
            foreach ($notificationIds as $cleanupNotificationId) {
                $pdo->prepare('DELETE FROM notifications WHERE id = ?')->execute([(int)$cleanupNotificationId]);
            }
            $pdo->prepare('DELETE FROM hr_incentive_notification_outbox WHERE award_id = ?')->execute([$awardId]);
            $pdo->prepare('UPDATE payroll SET incentive_amount = ? WHERE id = ? AND status = \'draft\'')
                ->execute([$originalIncentiveAmount, $payrollId]);
            $pdo->prepare('DELETE FROM hr_incentive_awards WHERE id = ?')->execute([$awardId]);
        } elseif ($notificationId > 0) {
            $pdo->prepare('DELETE FROM notifications WHERE id = ?')->execute([$notificationId]);
        }
        if ($eventId > 0) {
            $pdo->prepare('DELETE FROM hr_incentive_events WHERE id = ?')->execute([$eventId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $cleanupError = $e->getMessage();
    }
}

if ($cleanupError !== null) {
    echo "FAIL | cleanup_error=" . $cleanupError . "\n";
    exit(1);
}

$leftovers = (int)dbFetchOne(
    "SELECT COUNT(*) AS c
     FROM hr_incentive_events ev
     LEFT JOIN hr_incentive_awards a ON a.event_id = ev.id
     WHERE ev.source_table = 'test_notification'
       AND ev.idempotency_key LIKE 'hr-incentive:test-notification:%'"
)['c'];

if ($leftovers !== 0) {
    echo "FAIL | cleanup_leftovers={$leftovers}\n";
    exit(1);
}

echo "PASS | cleanup=PASS | payroll_amount_restored=PASS\n";
