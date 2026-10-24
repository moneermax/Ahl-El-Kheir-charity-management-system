<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$allowedRoles = ['admin', 'vice_general_manager', 'general_manager', 'administration', 'staff', 'social_media'];
if (!in_array((string)$user['role_code'], $allowedRoles, true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Sponsor request access is not allowed.']]);
}

$input = ak_api_read_json();
$action = trim((string)($input['action'] ?? ''));
$requestId = (int)($input['request_id'] ?? 0);
if ($requestId < 1) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_request_id', 'message' => 'A valid sponsor request ID is required.']]);
}
$transitions = [
    'mark_contacted' => ['from' => ['new'], 'to' => 'contacted'],
    'mark_lost' => ['from' => ['new', 'contacted'], 'to' => 'lost'],
    'reopen' => ['from' => ['lost'], 'to' => 'contacted'],
];
if (!isset($transitions[$action])) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => 'Unsupported sponsor request action.']]);
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $request = dbFetchOne('SELECT id, status FROM sponsor_requests WHERE id = ? FOR UPDATE', [$requestId]);
        if (!$request) {
            throw new OutOfBoundsException('Sponsor request not found.');
        }
        $transition = $transitions[$action];
        $currentStatus = (string)$request['status'];
        if (!in_array($currentStatus, $transition['from'], true)) {
            throw new DomainException('This sponsor request can no longer make that status transition.');
        }
        dbExecute('UPDATE sponsor_requests SET status = ? WHERE id = ? AND status = ?', [$transition['to'], $requestId, $currentStatus]);
        if ((int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn() !== 1) {
            throw new RuntimeException('The sponsor request changed before the action could be saved.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    ak_api_json(200, ['ok' => true, 'data' => [
        'request_id' => $requestId,
        'status' => $transition['to'],
        'message' => 'Sponsor request status updated.',
    ]]);
} catch (OutOfBoundsException $e) {
    ak_api_json(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => $e->getMessage()]]);
} catch (DomainException $e) {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'invalid_transition', 'message' => $e->getMessage()]]);
} catch (Throwable $e) {
    error_log('Mobile sponsor request action failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'sponsor_request_action_failed', 'message' => 'Unable to update the sponsor request.']]);
}
