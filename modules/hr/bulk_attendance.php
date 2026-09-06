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
     * Leave protection is deliberately checked before any write. A bulk
     * attendance request is atomic at the application level: if even one
     * selected employee is still on leave, the entire request is rejected.
     */
    $leaveParams = array_merge($ids, [$selectedDate]);
    $leaveRows = dbFetchAll(
        "SELECT employee_id FROM attendance
         WHERE employee_id IN ({$placeholders}) AND date = ? AND status = 'on_leave'",
        $leaveParams
    );

    if ($leaveRows) {
        $leaveIds = array_map(static fn($row) => (int)$row['employee_id'], $leaveRows);
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'message' => count($leaveIds) === 1
                ? 'يوجد موظف في إجازة ضمن التحديد. يجب تنفيذ "عودة من الإجازة" أولاً، ثم تسجيل الحضور.'
                : 'يوجد ' . count($leaveIds) . ' موظفين في إجازة ضمن التحديد. يجب تنفيذ "عودة من الإجازة" أولاً، ثم تسجيل الحضور.',
            'blocked_employee_ids' => $leaveIds
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $affected = 0;
    $now = date('H:i:s');

    foreach ($ids as $id) {
        if ($action === 'bulk_check_in') {
            dbExecute(
                "INSERT INTO attendance (employee_id, date, check_in, work_mode, status, notes)
                 VALUES (?, ?, ?, ?, 'present', NULL)
                 ON DUPLICATE KEY UPDATE
                    check_in = VALUES(check_in), work_mode = VALUES(work_mode), status = 'present', notes = NULL",
                [$id, $selectedDate, $now, $mode]
            );
        } elseif ($action === 'bulk_check_out') {
            dbExecute(
                "UPDATE attendance SET check_out = ?, status = CASE WHEN status = 'absent' THEN 'present' ELSE status END
                 WHERE employee_id = ? AND date = ? AND status <> 'on_leave'",
                [$now, $id, $selectedDate]
            );
        } elseif ($action === 'bulk_absent') {
            dbExecute(
                "INSERT INTO attendance (employee_id, date, status)
                 VALUES (?, ?, 'absent')
                 ON DUPLICATE KEY UPDATE status = 'absent', check_in = NULL, check_out = NULL, work_mode = NULL",
                [$id, $selectedDate]
            );
        } elseif ($action === 'bulk_leave') {
            dbExecute(
                "INSERT INTO attendance (employee_id, date, status, notes)
                 VALUES (?, ?, 'on_leave', 'إجازة يدوية')
                 ON DUPLICATE KEY UPDATE status = 'on_leave', check_in = NULL, check_out = NULL, work_mode = NULL, notes = 'إجازة يدوية'",
                [$id, $selectedDate]
            );
        }
        $affected++;
    }

    echo json_encode([
        'ok' => true,
        'affected' => $affected,
        'message' => 'تم تنفيذ الإجراء للموظفين المحددين بنجاح.'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'تعذر تنفيذ الإجراء: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
