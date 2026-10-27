<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once __DIR__ . '/case_service.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
}
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$role = (string)$user['role_code'];
try {
    if ($method === 'GET' && (string)($_GET['options'] ?? '') === '1') {
        $query = trim((string)($_GET['query'] ?? ''));
        if (mb_strlen($query, 'UTF-8') > 100) ak_api_json(422, ['ok' => false, 'error' => ['code' => 'query_too_long', 'message' => 'Search must not exceed 100 characters.']]);
        ak_api_json(200, ['ok' => true, 'data' => ak_api_suspension_options($userId, $role, $query)]);
    }
    if ($method === 'GET') ak_api_json(200, ['ok' => true, 'data' => ak_api_list_suspension_cases($userId, $role)]);

    $input = ak_api_read_json();
    $entityType = trim((string)($input['entity_type'] ?? ''));
    $entityId = filter_var($input['entity_id'] ?? null, FILTER_VALIDATE_INT);
    $reason = trim((string)($input['reason'] ?? ''));
    $notes = trim((string)($input['notes'] ?? ''));
    if (!$entityId || $entityId < 1) ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_entity_id', 'message' => 'Choose a valid family or child.']]);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = ak_api_create_suspension_case($userId, $role, $entityType, (int)$entityId, $reason, $notes);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    try {
        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, 'SUSPEND', 'suspensions', ?, NULL, ?, ?, ?)",
            [$userId, (int)$result['case_id'], json_encode(['type' => $entityType, 'entity_id' => (int)$entityId, 'reason' => $reason], JSON_UNESCAPED_UNICODE),
             (string)($_SERVER['REMOTE_ADDR'] ?? ''), (string)($_SERVER['HTTP_USER_AGENT'] ?? '')]);
    } catch (Throwable $e) { error_log('Mobile suspension creation audit failed: ' . $e->getMessage()); }
    ak_api_json(201, ['ok' => true, 'data' => $result + ['message' => 'Suspension created and linked sponsorships paused.']]);
} catch (AkSuspensionLiftForbidden|AkSuspensionCreateForbidden $e) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => $e->getMessage()]]);
} catch (OutOfBoundsException $e) {
    ak_api_json(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => $e->getMessage()]]);
} catch (DomainException $e) {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'active_case_exists', 'message' => $e->getMessage()]]);
} catch (InvalidArgumentException $e) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_input', 'message' => $e->getMessage()]]);
} catch (Throwable $e) {
    error_log('Mobile suspension endpoint failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'suspension_action_failed', 'message' => 'The suspension action could not be completed.']]);
}
