<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/supervisor_lifecycle.php';

Session::start();
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'vice_general_manager', 'general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$id = (int)($_POST['id'] ?? 0);
if (!$id || !verify_csrf()) {
    flash('error', 'تعذر التحقق من الطلب.');
    redirect('modules/supervisors/index.php');
}
if ($id === Session::getUserId()) {
    flash('error', 'لا يمكن تغيير حالة حسابك الحالي بهذه الطريقة.');
    redirect('modules/supervisors/index.php');
}

try {
    $supervisor = getSupervisorLifecycle($id);
    if (!$supervisor) throw new RuntimeException('Supervisor not found.');

    $status = (string)$supervisor['supervisor_status'];
    if (supervisorLifecycleIsFinal($status)) {
        throw new RuntimeException('Archived/departed supervisor cannot be reactivated from this workflow.');
    }
    if ($status === 'on_leave') {
        throw new RuntimeException('Supervisor is on leave. Use the leave return workflow.');
    }
    if ($status === 'returning') {
        throw new RuntimeException('Supervisor is in the return process.');
    }

    $newStatus = $status === 'active' ? 'suspended' : 'active';
    dbExecute("UPDATE users SET supervisor_status = ?, is_active = ?, updated_at = NOW() WHERE id = ?", [$newStatus, $newStatus === 'active' ? 1 : 0, $id]);

    try {
        dbExecute("INSERT INTO audit_log
            (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, 'STATUS_CHANGE', 'supervisor', ?, ?, ?, ?, ?)", [
            Session::getUserId(),
            $id,
            json_encode(['supervisor_status' => $status, 'is_active' => (int)$supervisor['is_active']], JSON_UNESCAPED_UNICODE),
            json_encode(['supervisor_status' => $newStatus, 'is_active' => $newStatus === 'active' ? 1 : 0], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Throwable $auditError) {
        error_log('Supervisor status audit: ' . $auditError->getMessage());
    }

    flash('success', $newStatus === 'active' ? 'تم تفعيل المشرف.' : 'تم إيقاف المشرف مؤقتاً.');
} catch (Throwable $e) {
    error_log('Supervisor status: ' . $e->getMessage());
    flash('error', 'تعذر تغيير حالة المشرف.');
}

redirect('modules/supervisors/index.php');
