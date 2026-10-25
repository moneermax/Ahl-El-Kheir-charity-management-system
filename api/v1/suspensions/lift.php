<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once __DIR__ . '/case_service.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$role = (string)$user['role_code'];
$input = ak_api_read_json();
$caseId = filter_var($input['case_id'] ?? null, FILTER_VALIDATE_INT);

if (!$caseId || $caseId < 1) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_case_id', 'message' => 'A valid suspension case ID is required.']]);
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = ak_api_lift_suspension_case($userId, $role, (int)$caseId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    try {
        dbExecute(
            "INSERT INTO audit_log
                (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'LIFT', 'suspensions', ?, NULL, ?, ?, ?)",
            [
                $userId,
                (int)$result['case_id'],
                json_encode(['type' => $result['entity_type'], 'entity_id' => (int)$result['entity_id']], JSON_UNESCAPED_UNICODE),
                (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]
        );
    } catch (Throwable $e) {
        error_log('Mobile suspension lift audit failed: ' . $e->getMessage());
    }

    ak_api_json(200, ['ok' => true, 'data' => $result + ['message' => 'The suspension was lifted and linked sponsorships were resumed.']]);
} catch (AkSuspensionLiftForbidden $e) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => $e->getMessage()]]);
} catch (OutOfBoundsException $e) {
    ak_api_json(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => $e->getMessage()]]);
} catch (DomainException $e) {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'case_not_active', 'message' => $e->getMessage()]]);
} catch (InvalidArgumentException $e) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_input', 'message' => $e->getMessage()]]);
} catch (Throwable $e) {
    error_log('Mobile suspension lift failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'suspension_lift_failed', 'message' => 'Unable to lift the suspension.']]);
}
