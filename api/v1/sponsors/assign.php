<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once __DIR__ . '/assignment_service.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$role = (string)$user['role_code'];
$actorId = (int)$user['id'];

if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Sponsor assignment is not allowed for this account.']]);
}

$input = ak_api_read_json();
$sponsorId = filter_var($input['sponsor_id'] ?? null, FILTER_VALIDATE_INT);
$supervisorId = filter_var($input['supervisor_id'] ?? null, FILTER_VALIDATE_INT);
if ($sponsorId === false || $sponsorId === null || $sponsorId < 1 || $supervisorId === false || $supervisorId === null || $supervisorId < 1) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_assignment', 'message' => 'Select a valid sponsor and active supervisor.']]);
}

try {
    db()->beginTransaction();
    $result = ak_mobile_assign_sponsor((int)$sponsorId, (int)$supervisorId, $actorId);
    db()->commit();
    ak_api_json(200, ['ok' => true, 'data' => $result]);
} catch (DomainException|InvalidArgumentException $e) {
    if (db()->inTransaction()) db()->rollBack();
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_assignment', 'message' => $e->getMessage()]]);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log('Mobile sponsor assignment failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'assignment_failed', 'message' => 'Sponsor assignment could not be completed.']]);
}
