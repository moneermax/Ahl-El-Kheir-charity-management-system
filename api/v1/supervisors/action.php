<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once __DIR__ . '/action_service.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$role = (string)$user['role_code'];

if (!in_array($role, ['admin', 'vice_general_manager'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Supervisor management is not allowed for this account.']]);
}

$input = ak_api_read_json();
$supervisorId = filter_var($input['supervisor_id'] ?? null, FILTER_VALIDATE_INT);
$action = (string)($input['action'] ?? '');
if ($supervisorId === false || $supervisorId === null || $supervisorId < 1 || $action !== 'toggle_status') {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => 'A valid supervisor and status action are required.']]);
}

try {
    db()->beginTransaction();
    $result = ak_mobile_toggle_supervisor_status(
        (int)$supervisorId,
        (int)$user['id'],
        (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
    );
    db()->commit();
    ak_api_json(200, ['ok' => true, 'data' => $result]);
} catch (DomainException|InvalidArgumentException $e) {
    if (db()->inTransaction()) db()->rollBack();
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => $e->getMessage()]]);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log('Mobile supervisor status action failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'supervisor_action_failed', 'message' => 'Supervisor status could not be updated.']]);
}
