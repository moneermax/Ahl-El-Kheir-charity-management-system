<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$role = (string)$user['role_code'];
$input = ak_api_read_json();

$projectId = filter_var($input['project_id'] ?? null, FILTER_VALIDATE_INT);
if (!$projectId || $projectId < 1) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_project_id', 'message' => 'A valid project ID is required.']]);
}

$project = dbFetchOne(
    "SELECT op.id, op.status, pa.approval_status, pl.lifecycle_status
     FROM other_projects op
     LEFT JOIN project_approval pa ON pa.project_id = op.id
     LEFT JOIN project_lifecycle pl ON pl.project_id = op.id
     WHERE op.id = ? LIMIT 1",
    [$projectId]
);
if (!$project) {
    ak_api_json(404, ['ok' => false, 'error' => ['code' => 'project_not_found', 'message' => 'The project was not found.']]);
}

$lifecycleStatus = (string)($project['lifecycle_status'] ?? $project['status'] ?? '');
if ($lifecycleStatus === 'closed') {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'project_closed', 'message' => 'Progress updates cannot be added to a closed project.']]);
}
if ((string)($project['approval_status'] ?? '') !== 'approved'
    || !in_array($lifecycleStatus, ['active', 'reopened', 'completed', 'under_review', 'cancelled'], true)) {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'project_not_operational', 'message' => 'This project is not available for operational updates.']]);
}

$canEdit = $role === 'admin';
if (!$canEdit && $role === 'project_supervisor') {
    $primary = dbFetchOne(
        'SELECT id FROM project_supervisor_assignments WHERE project_id = ? AND supervisor_user_id = ? AND ended_at IS NULL LIMIT 1',
        [$projectId, $userId]
    );
    $operations = dbFetchOne(
        "SELECT id FROM project_team WHERE project_id = ? AND user_id = ? AND section_code = 'operations' AND unassigned_at IS NULL LIMIT 1",
        [$projectId, $userId]
    );
    $canEdit = (bool)$primary || (bool)$operations;
}
if (!$canEdit) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'You do not have permission to add progress updates to this project.']]);
}

$summary = trim((string)($input['summary'] ?? ''));
if ($summary === '') {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'summary_required', 'message' => 'A progress summary is required.']]);
}
if (mb_strlen($summary, 'UTF-8') > 500) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'summary_too_long', 'message' => 'The progress summary cannot exceed 500 characters.']]);
}
$percentRaw = $input['completion_percent'] ?? 0;
if ($percentRaw === '' || $percentRaw === null) $percentRaw = 0;
if (!is_numeric($percentRaw)) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_completion_percent', 'message' => 'Completion percentage must be between 0 and 100.']]);
}
$percent = (float)$percentRaw;
if (!is_finite($percent) || $percent < 0 || $percent > 100) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_completion_percent', 'message' => 'Completion percentage must be between 0 and 100.']]);
}
$dateRaw = trim((string)($input['update_date'] ?? ''));
if ($dateRaw === '') $dateRaw = date('Y-m-d');
$date = DateTime::createFromFormat('!Y-m-d', $dateRaw);
if (!$date || $date->format('Y-m-d') !== $dateRaw) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_update_date', 'message' => 'Update date must use YYYY-MM-DD format.']]);
}

$achievements = trim((string)($input['achievements'] ?? ''));
$issues = trim((string)($input['issues'] ?? ''));
$nextSteps = trim((string)($input['next_steps'] ?? ''));

try {
    dbExecute('START TRANSACTION');
    $lockedProject = dbFetchOne(
        "SELECT op.id, op.status, pa.approval_status, pl.lifecycle_status
         FROM other_projects op
         LEFT JOIN project_approval pa ON pa.project_id = op.id
         LEFT JOIN project_lifecycle pl ON pl.project_id = op.id
         WHERE op.id = ? FOR UPDATE",
        [$projectId]
    );
    $lockedStatus = (string)($lockedProject['lifecycle_status'] ?? $lockedProject['status'] ?? '');
    if (!$lockedProject || $lockedStatus === 'closed' || (string)($lockedProject['approval_status'] ?? '') !== 'approved'
        || !in_array($lockedStatus, ['active', 'reopened', 'completed', 'under_review', 'cancelled'], true)) {
        dbExecute('ROLLBACK');
        ak_api_json(409, ['ok' => false, 'error' => ['code' => 'project_state_changed', 'message' => 'The project state changed. Reload it and try again.']]);
    }
    if ($role === 'project_supervisor') {
        $primary = dbFetchOne(
            'SELECT id FROM project_supervisor_assignments WHERE project_id = ? AND supervisor_user_id = ? AND ended_at IS NULL LIMIT 1',
            [$projectId, $userId]
        );
        $operations = dbFetchOne(
            "SELECT id FROM project_team WHERE project_id = ? AND user_id = ? AND section_code = 'operations' AND unassigned_at IS NULL LIMIT 1",
            [$projectId, $userId]
        );
        if (!$primary && !$operations) {
            dbExecute('ROLLBACK');
            ak_api_json(403, ['ok' => false, 'error' => ['code' => 'assignment_changed', 'message' => 'Your operational assignment changed.']]);
        }
    }
    dbExecute(
        'INSERT INTO project_progress_updates (project_id, update_date, completion_percent, summary, achievements, issues, next_steps, submitted_by) VALUES (?,?,?,?,?,?,?,?)',
        [$projectId, $dateRaw, $percent, $summary, $achievements !== '' ? $achievements : null, $issues !== '' ? $issues : null, $nextSteps !== '' ? $nextSteps : null, $userId]
    );
    $updateId = (int)db()->lastInsertId();
    dbExecute(
        "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, 'CREATE', 'project_progress_update', ?, NULL, ?, ?, ?)",
        [$userId, $updateId, json_encode(['project_id' => $projectId], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
    );
    dbExecute('COMMIT');
} catch (Throwable $e) {
    try { dbExecute('ROLLBACK'); } catch (Throwable $ignored) {}
    error_log('Mobile project progress update failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'progress_update_failed', 'message' => 'Unable to save the progress update.']]);
}

ak_api_json(200, ['ok' => true, 'data' => ['update_id' => $updateId, 'message' => 'Progress update saved.']]);
