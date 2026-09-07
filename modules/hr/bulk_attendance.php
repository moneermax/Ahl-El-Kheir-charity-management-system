<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();
header('Content-Type: application/json; charset=utf-8');

$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'hr_staff', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'غير مصرح لك بتنفيذ هذا الإجراء.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'طريقة الطلب غير مسموحة.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = (string)($_POST['action'] ?? '');
$selectedDate = (string)($_POST['selected_date'] ?? date('Y-m-d'));
$mode = (string)($_POST['bulk_work_mode'] ?? 'remote');

$dateObject = DateTime::createFromFormat('Y-m-d', $selectedDate);
if (!$dateObject || $dateObject->format('Y-m-d') !== $selectedDate) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'التاريخ المحدد غير صالح.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ids = [];
if (isset($_POST['employee_ids_json'])) {
    $decoded = json_decode((string)$_POST['employee_ids_json'], true);
    if (is_array($decoded)) {
        $ids = array_map('intval', $decoded);
    }
}
if (!$ids && isset($_POST['employee_ids']) && is_array($_POST['employee_ids'])) {
    $ids = array_map('intval', $_POST['employee_ids']);
}
$ids = array_values(array_unique(array_filter($ids, static fn($id) => $id > 0)));

if (!$ids) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'لم يتم تحديد أي موظف.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($action, ['bulk_check_in', 'bulk_check_out', 'bulk_absent', 'bulk_leave'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'إجراء الحضور غير صالح.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($mode, ['remote', 'onsite', 'hybrid'], true)) {
    $mode = 'remote';
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $employees = dbFetchAll(
        "SELECT id FROM employees WHERE id IN ({$placeholders}) AND status = 'active'",
        $ids
    );
    $activeIds = array_map(static fn($row) => (int)$row['id'], $employees);
    $missingIds = array_values(array_diff($ids, $activeIds));
    if ($missingIds) {
        throw new RuntimeException('يوجد موظف غير موجود أو غير نشط ضمن التحديد. لم يتم تنفيذ أي تغيير.');
    }

    /*
     * Employment state is the canonical HR eligibility source.
     * An employee must have a working-category state that overlaps the
     * selected attendance date. The legacy employees.status check above is
     * retained only as a backward-compatibility safety check.
     */
    $stateRows = dbFetchAll(
        "SELECT e.id,
                s.code AS employment_state_code,
                s.name_ar AS employment_state_name,
                s.category AS employment_state_category
         FROM employees e
         INNER JOIN hr_employee_state_history h
                 ON h.employee_id = e.id
                AND h.effective_from <= CONCAT(?, ' 23:59:59')
                AND (h.effective_to IS NULL OR h.effective_to >= CONCAT(?, ' 00:00:00'))
         INNER JOIN hr_employment_states s
                 ON s.id = h.employment_state_id
         WHERE e.id IN ({$placeholders})
           AND h.id = (
                SELECT h2.id
                  FROM hr_employee_state_history h2
                 WHERE h2.employee_id = e.id
                   AND h2.effective_from <= CONCAT(?, ' 23:59:59')
                   AND (h2.effective_to IS NULL OR h2.effective_to >= CONCAT(?, ' 00:00:00'))
                 ORDER BY h2.effective_from DESC, h2.id DESC
                 LIMIT 1
           )",
        array_merge([$selectedDate, $selectedDate], $ids, [$selectedDate, $selectedDate])
    );

    $stateById = [];
    foreach ($stateRows as $stateRow) {
        $stateById[(int)$stateRow['id']] = $stateRow;
    }

    $ineligibleIds = [];
    foreach ($ids as $id) {
        $state = $stateById[$id] ?? null;
        if (!$state || $state['employment_state_category'] !== 'working') {
            $ineligibleIds[] = $id;
        }
    }

    if ($ineligibleIds) {
        throw new RuntimeException('يوجد موظف غير مؤهل للحضور بسبب حالة التوظيف في التاريخ المحدد. لم يتم تنفيذ أي تغيير.');
    }

    /*
     * The leaves table is the authoritative source for approved leave.
     * A daily attendance row containing the controlled return marker is an
     * explicit exception: HR has already returned that employee for this date.
     * This remains safe even if generated on_leave attendance rows were deleted.
     */
    $leaveRows = dbFetchAll(
        "SELECT employee_id
         FROM leaves
         WHERE employee_id IN ({$placeholders})
           AND status = 'hr_approved'
           AND start_date <= ?
           AND end_date >= ?",
        array_merge($ids, [$selectedDate, $selectedDate])
    );
    $approvedLeaveIds = array_values(array_unique(array_map(static fn($row) => (int)$row['employee_id'], $leaveRows)));

    $returnRows = dbFetchAll(
        "SELECT employee_id
         FROM attendance
         WHERE employee_id IN ({$placeholders})
           AND date = ?
           AND status = 'absent'
           AND notes LIKE 'عودة من الإجازة%'",
        array_merge($ids, [$selectedDate])
    );
    $returnedIds = array_values(array_unique(array_map(static fn($row) => (int)$row['employee_id'], $returnRows)));

    $leaveIds = array_values(array_diff($approvedLeaveIds, $returnedIds));
    $eligibleIds = array_values(array_diff($ids, $leaveIds));

    if (!$eligibleIds) {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'message' => 'جميع الموظفين المحددين في إجازة معتمدة. يجب تنفيذ "عودة من الإجازة" أولاً.',
            'blocked_employee_ids' => $leaveIds,
            'affected' => 0
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $affected = 0;
    $now = date('H:i:s');

    foreach ($eligibleIds as $id) {
        if ($action === 'bulk_check_in') {
            dbExecute(
                "INSERT INTO attendance (employee_id, date, check_in, work_mode, status, notes)
                 VALUES (?, ?, ?, ?, 'present', NULL)
                 ON DUPLICATE KEY UPDATE
                    check_in = VALUES(check_in),
                    work_mode = VALUES(work_mode),
                    status = 'present',
                    notes = NULL",
                [$id, $selectedDate, $now, $mode]
            );
        } elseif ($action === 'bulk_check_out') {
            dbExecute(
                "UPDATE attendance
                 SET check_out = ?,
                     status = CASE WHEN status = 'absent' THEN 'present' ELSE status END
                 WHERE employee_id = ? AND date = ? AND status <> 'on_leave'",
                [$now, $id, $selectedDate]
            );
        } elseif ($action === 'bulk_absent') {
            dbExecute(
                "INSERT INTO attendance (employee_id, date, status)
                 VALUES (?, ?, 'absent')
                 ON DUPLICATE KEY UPDATE
                    status = 'absent',
                    check_in = NULL,
                    check_out = NULL,
                    work_mode = NULL,
                    notes = NULL",
                [$id, $selectedDate]
            );
        } elseif ($action === 'bulk_leave') {
            dbExecute(
                "INSERT INTO attendance (employee_id, date, status, notes)
                 VALUES (?, ?, 'on_leave', 'إجازة يدوية')
                 ON DUPLICATE KEY UPDATE
                    status = 'on_leave',
                    check_in = NULL,
                    check_out = NULL,
                    work_mode = NULL,
                    notes = 'إجازة يدوية'",
                [$id, $selectedDate]
            );
        }
        $affected++;
    }

    $skipped = count($leaveIds);
    $message = $skipped > 0
        ? 'تم تنفيذ الإجراء على ' . $affected . ' موظف، وتم استثناء ' . $skipped . ' موظف في إجازة معتمدة.'
        : 'تم تنفيذ الإجراء للموظفين المحددين بنجاح.';

    echo json_encode([
        'ok' => true,
        'affected' => $affected,
        'skipped' => $skipped,
        'blocked_employee_ids' => $leaveIds,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'تعذر تنفيذ الإجراء: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
