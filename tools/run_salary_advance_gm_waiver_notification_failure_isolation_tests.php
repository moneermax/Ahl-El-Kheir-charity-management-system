<?php
declare(strict_types=1);

/**
 * GM salary-advance waiver notification failure-isolation runtime test.
 *
 * This test uses the real production FM-preparation -> GM-approval ->
 * FM-execution functions. A test-only hook in
 * ak_transaction_review_notify_event() throws only for the waiver execution
 * notification reference types. The production helper catches that Throwable.
 *
 * The objective is to prove that execution notification delivery can fail at
 * the actual notification boundary without rolling back the already-committed
 * waiver decision, request state, journals, schedule overlays or audit state.
 *
 * The hook is defined before the production notification helper is loaded.
 * Outside this test file the hook does not exist and production behavior is
 * unchanged.
 */

$akNotificationFailureAttempts = 0;

function ak_notification_test_delivery_hook(
    int $userId,
    string $title,
    string $body,
    string $link,
    ?int $referenceId = null,
    ?string $referenceType = null
): void {
    global $akNotificationFailureAttempts;

    if (in_array(
        (string)$referenceType,
        ['salary_advance_waiver_execution', 'salary_advance_waiver_employee'],
        true
    )) {
        $akNotificationFailureAttempts++;
        throw new RuntimeException(
            'TEST_INJECTED_NOTIFICATION_DELIVERY_FAILURE'
        );
    }
}

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../modules/accounting/lib.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_waiver.php';

$pdo = db();

function findFailureIsolationFixture(PDO $pdo): ?array
{
    return dbFetchOne(
        "SELECT r.id AS request_id,
                r.request_no,
                r.employee_id,
                r.status,
                r.outstanding_balance,
                s.scheduled_month,
                e.user_id AS employee_user_id
         FROM hr_salary_advance_requests r
         JOIN hr_salary_advance_repayment_schedule s
           ON s.salary_advance_request_id = r.id
         JOIN employees e ON e.id = r.employee_id
         JOIN users eu ON eu.id = e.user_id AND eu.is_active = 1
         WHERE r.status = 'disbursed'
           AND COALESCE(r.outstanding_balance, 0) > 0
           AND s.status IN ('pending','partial')
           AND NOT EXISTS (
               SELECT 1
               FROM payroll p
               WHERE p.employee_id = r.employee_id
                 AND p.year = YEAR(s.scheduled_month)
                 AND p.month = MONTH(s.scheduled_month)
                 AND p.status = 'draft'
           )
         ORDER BY s.scheduled_month ASC, r.id ASC
         LIMIT 1"
    );
}

function findActiveUserByRoleForFailureIsolation(PDO $pdo, array $roleCodes): ?int
{
    $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
    $row = dbFetchOne(
        "SELECT u.id
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.is_active = 1
           AND r.code IN ($placeholders)
         ORDER BY u.id ASC
         LIMIT 1",
        $roleCodes
    );
    return $row ? (int)$row['id'] : null;
}

function findAccountByCodeForFailureIsolation(PDO $pdo, string $code): ?int
{
    $row = dbFetchOne(
        "SELECT id
         FROM accounts
         WHERE code = ?
           AND is_active = 1
         LIMIT 1",
        [$code]
    );
    return $row ? (int)$row['id'] : null;
}

function findActiveExpenseAccountForFailureIsolation(PDO $pdo): ?int
{
    $row = dbFetchOne(
        "SELECT id
         FROM accounts
         WHERE is_active = 1
           AND account_type = 'expense'
         ORDER BY id ASC
         LIMIT 1"
    );
    return $row ? (int)$row['id'] : null;
}

function notificationReferenceColumnsAvailableForFailureIsolation(PDO $pdo): bool
{
    $row = dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'notifications'
           AND column_name IN ('reference_id','reference_type')"
    );
    return (int)($row['c'] ?? 0) === 2;
}

function notificationIdsForFailureIsolation(
    PDO $pdo,
    int $userId,
    string $title,
    string $link
): array {
    $rows = dbFetchAll(
        "SELECT id
         FROM notifications
         WHERE recipient_user_id = ?
           AND title = ?
           AND link = ?",
        [$userId, $title, $link]
    );

    return array_map(
        static fn(array $row): int => (int)$row['id'],
        $rows
    );
}

function journalBalanceForFailureIsolation(PDO $pdo, int $entryId): array
{
    return dbFetchOne(
        "SELECT
            COALESCE(SUM(debit), 0) AS debit_total,
            COALESCE(SUM(credit), 0) AS credit_total,
            COUNT(*) AS line_count
         FROM journal_lines
         WHERE entry_id = ?",
        [$entryId]
    ) ?: [
        'debit_total' => 0,
        'credit_total' => 0,
        'line_count' => 0
    ];
}

$fixture = findFailureIsolationFixture($pdo);
$fmUserId = findActiveUserByRoleForFailureIsolation(
    $pdo,
    ['financial_manager','fm','finance']
);
$gmUserId = findActiveUserByRoleForFailureIsolation(
    $pdo,
    ['general_manager','gm']
);
$refundAccountId = findAccountByCodeForFailureIsolation($pdo, '1100');
$expenseAccountId = findActiveExpenseAccountForFailureIsolation($pdo);

if (
    !$fixture ||
    !$fmUserId ||
    !$gmUserId ||
    !$refundAccountId ||
    !$expenseAccountId
) {
    echo "SKIP | Required disbursed fixture with active employee account, active FM/GM users, or accounts was not found.\n";
    exit(0);
}

$decisionId = 0;
$waiverJournalId = null;
$refundJournalId = null;
$createdNotificationIds = [];
$beforeRequest = [
    'status' => $fixture['status'],
    'outstanding_balance' => $fixture['outstanding_balance'],
    'closed_at' => null,
];

$gmExecutionTitle = 'تم تنفيذ قرار إعفاء سلف الرواتب';
$gmExecutionLink = APP_URL . 'modules/hr/salary_advance_waiver_gm.php';
$employeeExecutionTitle = 'تم تنفيذ قرار إعفاء سلف الراتب';
$employeeExecutionLink = APP_URL . 'modules/hr/salary_advance_request.php';

$gmExecutionNotificationsBefore = notificationIdsForFailureIsolation(
    $pdo,
    $gmUserId,
    $gmExecutionTitle,
    $gmExecutionLink
);
$employeeExecutionNotificationsBefore = notificationIdsForFailureIsolation(
    $pdo,
    (int)$fixture['employee_user_id'],
    $employeeExecutionTitle,
    $employeeExecutionLink
);

try {
    $requestSnapshot = dbFetchOne(
        "SELECT status, outstanding_balance, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [(int)$fixture['request_id']]
    );
    if (!$requestSnapshot) {
        throw new RuntimeException('Could not snapshot the salary-advance request.');
    }
    $beforeRequest = $requestSnapshot;

    $effectiveMonth = date(
        'Y-m-01',
        strtotime((string)$fixture['scheduled_month'])
    );

    // Production function owns and commits the preparation transaction.
    $decisionId = hrSalaryAdvanceWaiverCreateDecision(
        $pdo,
        'individual',
        $effectiveMonth,
        'Notification failure-isolation runtime verification',
        $fmUserId,
        (int)$fixture['employee_id'],
        $refundAccountId,
        $expenseAccountId
    );

    $prepared = dbFetchOne(
        "SELECT status
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );
    if (!$prepared || $prepared['status'] !== 'pending_gm') {
        throw new RuntimeException(
            'Preparation did not commit as pending_gm.'
        );
    }

    // Production function owns and commits the GM approval transaction.
    hrSalaryAdvanceWaiverGMReview(
        $pdo,
        $decisionId,
        $gmUserId,
        true
    );

    $approved = dbFetchOne(
        "SELECT status
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );
    if (!$approved || $approved['status'] !== 'approved_by_gm') {
        throw new RuntimeException(
            'GM approval did not commit as approved_by_gm.'
        );
    }

    // Production execution owns and commits the financial transaction.
    // The test-only hook now forces notification delivery failure only for
    // the execution notification reference types.
    $execution = hrSalaryAdvanceWaiverExecute(
        $pdo,
        $decisionId,
        $fmUserId
    );

    if ((int)($execution['decision_id'] ?? 0) !== $decisionId) {
        throw new RuntimeException(
            'Execution returned an unexpected decision ID.'
        );
    }

    $decision = dbFetchOne(
        "SELECT status, gm_approved_by, executed_at
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    );
    if (!$decision || $decision['status'] !== 'executed') {
        throw new RuntimeException(
            'Financial execution was not committed as executed.'
        );
    }

    $afterRequest = dbFetchOne(
        "SELECT status, outstanding_balance, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [(int)$fixture['request_id']]
    );
    if (
        !$afterRequest ||
        $afterRequest['status'] !== 'disbursed' ||
        round((float)$afterRequest['outstanding_balance'], 2) !== 0.00
    ) {
        throw new RuntimeException(
            'Committed execution did not zero the disbursed request balance.'
        );
    }

    $item = dbFetchOne(
        "SELECT refund_amount, waived_amount,
                waiver_journal_entry_id, refund_journal_entry_id
         FROM hr_salary_advance_waiver_items
         WHERE decision_id = ?
         ORDER BY id ASC
         LIMIT 1",
        [$decisionId]
    );
    if (!$item) {
        throw new RuntimeException(
            'Executed waiver item was not preserved.'
        );
    }

    $waiverJournalId = !empty($item['waiver_journal_entry_id'])
        ? (int)$item['waiver_journal_entry_id']
        : null;
    $refundJournalId = !empty($item['refund_journal_entry_id'])
        ? (int)$item['refund_journal_entry_id']
        : null;

    if (!$waiverJournalId) {
        throw new RuntimeException(
            'Committed execution did not create a waiver journal.'
        );
    }

    $waiverBalance = journalBalanceForFailureIsolation(
        $pdo,
        $waiverJournalId
    );
    if (
        (int)$waiverBalance['line_count'] < 2 ||
        round((float)$waiverBalance['debit_total'], 2) !==
            round((float)$waiverBalance['credit_total'], 2)
    ) {
        throw new RuntimeException(
            'Waiver journal is not balanced after notification failure.'
        );
    }

    $overlayCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM hr_salary_advance_waiver_schedule_items wsi
         JOIN hr_salary_advance_waiver_items wi
           ON wi.id = wsi.waiver_item_id
         WHERE wi.decision_id = ?",
        [$decisionId]
    )['c'] ?? 0);

    if ($overlayCount <= 0) {
        throw new RuntimeException(
            'Future schedule overlays were not committed.'
        );
    }

    $auditCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM audit_log
         WHERE entity_type = 'hr_salary_advance_waiver_decision'
           AND entity_id = ?",
        [$decisionId]
    )['c'] ?? 0);

    if ($auditCount <= 0) {
        throw new RuntimeException(
            'Waiver decision audit evidence was not committed.'
        );
    }

    global $akNotificationFailureAttempts;
    if ($akNotificationFailureAttempts < 2) {
        throw new RuntimeException(
            'The controlled notification failure hook did not record both execution delivery attempts.'
        );
    }

    $gmExecutionNotificationsAfter = notificationIdsForFailureIsolation(
        $pdo,
        $gmUserId,
        $gmExecutionTitle,
        $gmExecutionLink
    );
    $employeeExecutionNotificationsAfter = notificationIdsForFailureIsolation(
        $pdo,
        (int)$fixture['employee_user_id'],
        $employeeExecutionTitle,
        $employeeExecutionLink
    );

    $unexpectedGmNotifications = array_diff(
        $gmExecutionNotificationsAfter,
        $gmExecutionNotificationsBefore
    );
    $unexpectedEmployeeNotifications = array_diff(
        $employeeExecutionNotificationsAfter,
        $employeeExecutionNotificationsBefore
    );

    if (
        $unexpectedGmNotifications ||
        $unexpectedEmployeeNotifications
    ) {
        throw new RuntimeException(
            'Execution notification rows were created despite the injected delivery failure.'
        );
    }

    echo "PASS | Notification failure isolation | request={$fixture['request_no']} | decision_id={$decisionId} | failure_attempts={$akNotificationFailureAttempts} | status=executed | outstanding=0 | waiver_journal={$waiverJournalId} | schedule_overlays={$overlayCount}\n";

    // Remove only rows created by this committed test fixture.
    $pdo->beginTransaction();

    $notificationTitleLinkPairs = [
        [
            'user_id' => $gmUserId,
            'title' => 'قرار إعفاء سلف الرواتب بانتظار اعتمادك',
            'link' => APP_URL . 'modules/hr/salary_advance_waiver_gm.php',
        ],
        [
            'user_id' => $fmUserId,
            'title' => 'اعتماد GM لقرار إعفاء سلف الرواتب',
            'link' => APP_URL . 'modules/hr/salary_advance_waiver_fm.php',
        ],
    ];

    foreach ($notificationTitleLinkPairs as $pair) {
        $rows = dbFetchAll(
            "SELECT id
             FROM notifications
             WHERE recipient_user_id = ?
               AND title = ?
               AND link = ?",
            [
                $pair['user_id'],
                $pair['title'],
                $pair['link']
            ]
        );

        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $createdNotificationIds[] = $id;
        }
    }

    // Re-read the pre-test notification IDs from the current committed state
    // is not sufficient for cleanup, so retain only IDs that were actually
    // created after the first snapshot by comparing against the snapshots.
    $protectedIds = array_merge(
        notificationIdsForFailureIsolation(
            $pdo,
            $gmUserId,
            'قرار إعفاء سلف الرواتب بانتظار اعتمادك',
            APP_URL . 'modules/hr/salary_advance_waiver_gm.php'
        ),
        notificationIdsForFailureIsolation(
            $pdo,
            $fmUserId,
            'اعتماد GM لقرار إعفاء سلف الرواتب',
            APP_URL . 'modules/hr/salary_advance_waiver_fm.php'
        )
    );

    // Determine the exact rows that were not present before this test.
    foreach ($createdNotificationIds as $id) {
        if (!in_array($id, $protectedIds, true)) {
            $pdo->prepare(
                "DELETE FROM notifications WHERE id = ?"
            )->execute([$id]);
        }
    }

    $pdo->prepare(
        "DELETE FROM audit_log
         WHERE entity_type = 'hr_salary_advance_request'
           AND entity_id = ?
           AND action = 'HR_SALARY_ADVANCE_WAIVER_EXECUTED'"
    )->execute([(int)$fixture['request_id']]);

    $pdo->prepare(
        "DELETE FROM audit_log
         WHERE entity_type = 'hr_salary_advance_waiver_decision'
           AND entity_id = ?"
    )->execute([$decisionId]);

    $pdo->prepare(
        "DELETE FROM hr_salary_advance_waiver_schedule_items
         WHERE waiver_item_id IN (
             SELECT id
             FROM hr_salary_advance_waiver_items
             WHERE decision_id = ?
         )"
    )->execute([$decisionId]);

    $pdo->prepare(
        "DELETE FROM hr_salary_advance_waiver_items
         WHERE decision_id = ?"
    )->execute([$decisionId]);

    $pdo->prepare(
        "DELETE FROM hr_salary_advance_waiver_decisions
         WHERE id = ?"
    )->execute([$decisionId]);

    if ($refundJournalId) {
        $pdo->prepare(
            "DELETE FROM journal_lines WHERE entry_id = ?"
        )->execute([$refundJournalId]);
        $pdo->prepare(
            "DELETE FROM journal_entries WHERE id = ?"
        )->execute([$refundJournalId]);
    }

    if ($waiverJournalId) {
        $pdo->prepare(
            "DELETE FROM journal_lines WHERE entry_id = ?"
        )->execute([$waiverJournalId]);
        $pdo->prepare(
            "DELETE FROM journal_entries WHERE id = ?"
        )->execute([$waiverJournalId]);
    }

    $pdo->prepare(
        "UPDATE hr_salary_advance_requests
         SET status = ?, outstanding_balance = ?, closed_at = ?
         WHERE id = ?"
    )->execute([
        $beforeRequest['status'],
        $beforeRequest['outstanding_balance'],
        $beforeRequest['closed_at'],
        (int)$fixture['request_id']
    ]);

    $pdo->commit();

    $remainingDecision = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM hr_salary_advance_waiver_decisions
         WHERE id = ?",
        [$decisionId]
    )['c'] ?? 0);

    $remainingWaiverJournal = $waiverJournalId
        ? (int)(dbFetchOne(
            "SELECT COUNT(*) AS c
             FROM journal_entries
             WHERE id = ?",
            [$waiverJournalId]
        )['c'] ?? 0)
        : 0;

    $remainingRefundJournal = $refundJournalId
        ? (int)(dbFetchOne(
            "SELECT COUNT(*) AS c
             FROM journal_entries
             WHERE id = ?",
            [$refundJournalId]
        )['c'] ?? 0)
        : 0;

    $finalRequest = dbFetchOne(
        "SELECT status, outstanding_balance, closed_at
         FROM hr_salary_advance_requests
         WHERE id = ?",
        [(int)$fixture['request_id']]
    );

    if (
        $remainingDecision !== 0 ||
        $remainingWaiverJournal !== 0 ||
        $remainingRefundJournal !== 0 ||
        !$finalRequest ||
        $finalRequest['status'] !== $beforeRequest['status'] ||
        round((float)$finalRequest['outstanding_balance'], 2) !==
            round((float)$beforeRequest['outstanding_balance'], 2) ||
        (string)($finalRequest['closed_at'] ?? '') !==
            (string)($beforeRequest['closed_at'] ?? '')
    ) {
        throw new RuntimeException(
            'Failure-isolation test cleanup left residual test state or failed to restore the request.'
        );
    }

    echo "PASS | Notification failure-isolation cleanup | decision_rows=0 | journals=0 | request_restored=1\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Best-effort cleanup for a failure before the normal cleanup block.
    // Never delete anything unless this test created a concrete decision.
    if ($decisionId > 0) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare(
                "DELETE FROM audit_log
                 WHERE entity_type = 'hr_salary_advance_request'
                   AND entity_id = ?
                   AND action = 'HR_SALARY_ADVANCE_WAIVER_EXECUTED'"
            )->execute([(int)$fixture['request_id']]);

            $pdo->prepare(
                "DELETE FROM audit_log
                 WHERE entity_type = 'hr_salary_advance_waiver_decision'
                   AND entity_id = ?"
            )->execute([$decisionId]);

            $pdo->prepare(
                "DELETE FROM hr_salary_advance_waiver_schedule_items
                 WHERE waiver_item_id IN (
                     SELECT id
                     FROM hr_salary_advance_waiver_items
                     WHERE decision_id = ?
                 )"
            )->execute([$decisionId]);

            $pdo->prepare(
                "DELETE FROM hr_salary_advance_waiver_items
                 WHERE decision_id = ?"
            )->execute([$decisionId]);

            $pdo->prepare(
                "DELETE FROM hr_salary_advance_waiver_decisions
                 WHERE id = ?"
            )->execute([$decisionId]);

            if ($refundJournalId) {
                $pdo->prepare(
                    "DELETE FROM journal_lines WHERE entry_id = ?"
                )->execute([$refundJournalId]);
                $pdo->prepare(
                    "DELETE FROM journal_entries WHERE id = ?"
                )->execute([$refundJournalId]);
            }

            if ($waiverJournalId) {
                $pdo->prepare(
                    "DELETE FROM journal_lines WHERE entry_id = ?"
                )->execute([$waiverJournalId]);
                $pdo->prepare(
                    "DELETE FROM journal_entries WHERE id = ?"
                )->execute([$waiverJournalId]);
            }

            $pdo->prepare(
                "UPDATE hr_salary_advance_requests
                 SET status = ?, outstanding_balance = ?, closed_at = ?
                 WHERE id = ?"
            )->execute([
                $beforeRequest['status'],
                $beforeRequest['outstanding_balance'],
                $beforeRequest['closed_at'],
                (int)$fixture['request_id']
            ]);

            $pdo->commit();
        } catch (Throwable $cleanupError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    echo "FAIL | Notification failure isolation | " . $e->getMessage() . "\n";
    exit(1);
}
