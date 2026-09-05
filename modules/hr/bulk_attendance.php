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
    $affected = 0;
    $now = date('H:i:s');

    foreach ($ids as $id) {
        if ($action === 'bulk_check_in') {
            dbExecute(
                "INSERT INTO attendance (employee_id, date, check_in, work_mode, status)
                 VALUES (?, ?, ?, ?, 'present')
                 ON DUPLICATE KEY UPDATE
                    check_in = VALUES(check_in),
                    work_mode = VALUES(work_mode),
                    status = 'present'",
                [$id, $selectedDate, $now, $mode]
            );
        } elseif ($action === 'bulk_check_out') {
            dbExecute(
                "UPDATE attendance SET check_out=? WHERE employee_id=? AND date=?",
                [$now, $id, $selectedDate]
            );
        } elseif ($action === 'bulk_absent') {
            dbExecute(
                "INSERT INTO attendance (employee_id, date, status)
                 VALUES (?, ?, 'absent')
                 ON DUPLICATE KEY UPDATE status='absent'",
                [$id, $selectedDate]
            );
        } elseif ($action === 'bulk_leave') {
            dbExecute(
                "INSERT INTO attendance (employee_id, date, status)
                 VALUES (?, ?, 'on_leave')
                 ON DUPLICATE KEY UPDATE status='on_leave'",
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
