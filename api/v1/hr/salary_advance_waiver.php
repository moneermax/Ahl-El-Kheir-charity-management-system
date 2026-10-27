<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_salary_advance_waiver.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
}

$user = ak_api_authenticated_user();
$role = (string)$user['role_code'];
$canGM = hrSalaryAdvanceWaiverCanGM($role);
$canFM = hrSalaryAdvanceWaiverCanFM($role);
if (!$canGM && !$canFM) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Salary-advance waiver access is not available for this role.']]);
}

$pdo = db();
$tablesReady = (int)dbFetchOne(
    "SELECT COUNT(*) AS c FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name IN ('hr_salary_advance_waiver_decisions','hr_salary_advance_waiver_items','hr_salary_advance_waiver_schedule_items')"
)['c'] === 3;

if (!$tablesReady) {
    if ($method === 'POST') {
        ak_api_json(503, ['ok' => false, 'error' => ['code' => 'waiver_not_migrated', 'message' => 'The salary-advance waiver feature is not enabled in this database.']]);
    }
    ak_api_json(200, ['ok' => true, 'data' => [
        'role' => $role,
        'can_gm_review' => $canGM,
        'can_fm_prepare' => $canFM,
        'tables_ready' => false,
        'effective_month' => date('Y-m-01'),
        'decisions' => [],
        'items' => [],
        'eligible_rows' => [],
        'employees' => [],
        'refund_accounts' => [],
        'expense_accounts' => [],
    ]]);
}

if ($method === 'GET') {
    $effectiveMonth = trim((string)($_GET['effective_month'] ?? date('Y-m-01')));
    $month = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveMonth);
    if (!$month || $month->format('Y-m-d') !== $effectiveMonth || $month->format('d') !== '01') {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_effective_month', 'message' => 'Effective month must be a valid date on the first day of a month.']]);
    }

    try {
        $statuses = $canFM ? ["pending_gm", "approved_by_gm"] : ["pending_gm"];
        $marks = implode(',', array_fill(0, count($statuses), '?'));
        $decisions = dbFetchAll(
            "SELECT d.*, creator.full_name AS creator_name, preparer.full_name AS preparer_name,
                    gm.full_name AS gm_name,
                    refund.code AS refund_code, refund.name_ar AS refund_name_ar, refund.name_en AS refund_name_en,
                    expense.code AS expense_code, expense.name_ar AS expense_name_ar, expense.name_en AS expense_name_en,
                    (SELECT COUNT(*) FROM hr_salary_advance_waiver_items wi WHERE wi.decision_id=d.id) AS item_count,
                    (SELECT COALESCE(SUM(wi.current_period_repayment),0) FROM hr_salary_advance_waiver_items wi WHERE wi.decision_id=d.id) AS refund_preview,
                    (SELECT COALESCE(SUM(CASE WHEN wi.previous_request_status='disbursed' THEN wi.balance_before + wi.current_period_repayment ELSE 0 END),0)
                     FROM hr_salary_advance_waiver_items wi WHERE wi.decision_id=d.id) AS waiver_preview
             FROM hr_salary_advance_waiver_decisions d
             LEFT JOIN users creator ON creator.id=d.created_by
             LEFT JOIN users preparer ON preparer.id=d.prepared_by
             LEFT JOIN users gm ON gm.id=d.gm_approved_by
             LEFT JOIN accounts refund ON refund.id=d.refund_account_id
             LEFT JOIN accounts expense ON expense.id=d.waiver_expense_account_id
             WHERE d.status IN ($marks)
             ORDER BY d.id " . ($canGM ? "DESC" : "ASC"),
            $statuses
        );

        $decisionIds = array_values(array_map(static fn(array $row): int => (int)$row['id'], $decisions));
        $items = [];
        if ($decisionIds) {
            $itemMarks = implode(',', array_fill(0, count($decisionIds), '?'));
            $items = dbFetchAll(
                "SELECT wi.*, e.full_name AS employee_name, e.employee_code, r.request_no,
                        r.status AS live_request_status
                 FROM hr_salary_advance_waiver_items wi
                 JOIN employees e ON e.id=wi.employee_id
                 JOIN hr_salary_advance_requests r ON r.id=wi.salary_advance_request_id
                 WHERE wi.decision_id IN ($itemMarks)
                 ORDER BY wi.decision_id, wi.id",
                $decisionIds
            );
        }

        $eligibleRows = $canFM ? hrSalaryAdvanceWaiverEligibleRows($pdo, $effectiveMonth) : [];
        $employees = [];
        foreach ($eligibleRows as $row) {
            $employeeId = (int)$row['employee_id'];
            if (!isset($employees[$employeeId])) {
                $employees[$employeeId] = [
                    'id' => $employeeId,
                    'employee_code' => (string)$row['employee_code'],
                    'full_name' => (string)$row['employee_name'],
                ];
            }
        }

        $refundAccounts = $canFM ? dbFetchAll(
            "SELECT id, code, name_ar, name_en FROM accounts
             WHERE code IN ('1100','1200','1300') AND is_active=1 ORDER BY code"
        ) : [];
        $expenseAccounts = $canFM ? dbFetchAll(
            "SELECT id, code, name_ar, name_en FROM accounts
             WHERE account_type='expense' AND is_active=1 ORDER BY code"
        ) : [];
    } catch (Throwable $exception) {
        error_log('Mobile salary-advance waiver read failed: ' . $exception->getMessage());
        ak_api_json(500, ['ok' => false, 'error' => ['code' => 'waiver_unavailable', 'message' => 'Salary-advance waiver data is temporarily unavailable.']]);
    }

    ak_api_json(200, ['ok' => true, 'data' => [
        'role' => $role,
        'can_gm_review' => $canGM,
        'can_fm_prepare' => $canFM,
        'tables_ready' => true,
        'effective_month' => $effectiveMonth,
        'today' => date('Y-m-d'),
        'decisions' => $decisions,
        'items' => $items,
        'eligible_rows' => $eligibleRows,
        'employees' => array_values($employees),
        'refund_accounts' => $refundAccounts,
        'expense_accounts' => $expenseAccounts,
    ]]);
}

$input = ak_api_read_json();
$action = trim((string)($input['action'] ?? ''));
$userId = (int)$user['id'];

try {
    if ($action === 'prepare') {
        if (!$canFM) {
            ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Only the Financial Manager may prepare a waiver decision.']]);
        }
        $decisionType = trim((string)($input['decision_type'] ?? ''));
        $effectiveMonth = trim((string)($input['effective_month'] ?? ''));
        $reason = trim((string)($input['reason'] ?? ''));
        $employeeId = $decisionType === 'individual' ? (int)($input['employee_id'] ?? 0) : null;
        $decisionId = hrSalaryAdvanceWaiverCreateDecision(
            $pdo,
            $decisionType,
            $effectiveMonth,
            $reason,
            $userId,
            $employeeId,
            (int)($input['refund_account_id'] ?? 0),
            (int)($input['waiver_expense_account_id'] ?? 0)
        );
        ak_api_json(200, ['ok' => true, 'data' => ['decision_id' => $decisionId, 'status' => 'pending_gm']]);
    }

    if ($action === 'approve' || $action === 'reject') {
        if (!$canGM) {
            ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Only the General Manager may review a waiver decision.']]);
        }
        $decisionId = (int)($input['decision_id'] ?? 0);
        $reason = trim((string)($input['reason'] ?? ''));
        hrSalaryAdvanceWaiverGMReview($pdo, $decisionId, $userId, $action === 'approve', $reason);
        ak_api_json(200, ['ok' => true, 'data' => [
            'decision_id' => $decisionId,
            'status' => $action === 'approve' ? 'approved_by_gm' : 'rejected_by_gm',
        ]]);
    }

    if ($action === 'execute') {
        if (!$canFM) {
            ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Only the Financial Manager may execute an approved waiver decision.']]);
        }
        $result = hrSalaryAdvanceWaiverExecute($pdo, (int)($input['decision_id'] ?? 0), $userId);
        ak_api_json(200, ['ok' => true, 'data' => $result + ['status' => 'executed']]);
    }

    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => 'Unsupported salary-advance waiver action.']]);
} catch (InvalidArgumentException $exception) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_request', 'message' => $exception->getMessage()]]);
} catch (RuntimeException $exception) {
    error_log('Mobile salary-advance waiver action rejected: ' . $exception->getMessage());
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'waiver_action_rejected', 'message' => $exception->getMessage()]]);
} catch (Throwable $exception) {
    error_log('Mobile salary-advance waiver action failed: ' . $exception->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'waiver_action_failed', 'message' => 'The salary-advance waiver action could not be completed.']]);
}
