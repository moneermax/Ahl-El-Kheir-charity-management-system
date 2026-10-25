<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once __DIR__ . '/suspension_service.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$role = (string)$user['role_code'];
if (!in_array($role, ['admin', 'vice_general_manager', 'nanny'], true)) {
    ak_api_json(403, [
        'ok' => false,
        'error' => ['code' => 'forbidden', 'message' => 'This account cannot suspend sponsorships from legal-age alerts.'],
    ]);
}

$input = ak_api_read_json();
$childId = filter_var($input['child_id'] ?? null, FILTER_VALIDATE_INT);
if (!$childId || $childId < 1) {
    ak_api_json(422, [
        'ok' => false,
        'error' => ['code' => 'invalid_child_id', 'message' => 'A valid child ID is required.'],
    ]);
}
$notes = trim((string)($input['notes'] ?? ''));
if (mb_strlen($notes, 'UTF-8') > 1000) {
    ak_api_json(422, [
        'ok' => false,
        'error' => ['code' => 'notes_too_long', 'message' => 'Notes must not exceed 1000 characters.'],
    ]);
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = ak_api_suspend_legal_age_child($userId, $role, (int)$childId, $notes);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    try {
        dbExecute(
            "INSERT INTO audit_log
                (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'SUSPEND', 'suspensions', ?, NULL, ?, ?, ?)",
            [
                $userId,
                (int)$result['case_id'],
                json_encode(['type' => 'child', 'entity_id' => (int)$childId, 'reason' => 'age_out'], JSON_UNESCAPED_UNICODE),
                (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]
        );
    } catch (Throwable $e) {
        error_log('Mobile legal-age suspension audit failed: ' . $e->getMessage());
    }

    ak_api_json(200, [
        'ok' => true,
        'data' => $result + [
            'message' => 'The child was disqualified for legal age and active linked sponsorships were paused.',
        ],
    ]);
} catch (AkLegalAgeSuspensionForbidden $e) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => $e->getMessage()]]);
} catch (OutOfBoundsException $e) {
    ak_api_json(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => $e->getMessage()]]);
} catch (DomainException $e) {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'already_suspended', 'message' => $e->getMessage()]]);
} catch (InvalidArgumentException $e) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_input', 'message' => $e->getMessage()]]);
} catch (Throwable $e) {
    error_log('Mobile legal-age suspension failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'suspension_failed', 'message' => 'Unable to complete the suspension.']]);
}
