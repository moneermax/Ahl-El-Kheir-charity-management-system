<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$role = (string)$user['role_code'];
$input = ak_api_read_json();

if (!in_array($role, ['admin', 'project_supervisor'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'You do not have permission to manage project milestones.']]);
}

$projectId = filter_var($input['project_id'] ?? null, FILTER_VALIDATE_INT);
if (!$projectId || $projectId < 1) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_project_id', 'message' => 'A valid project ID is required.']]);
}

$title = trim((string)($input['title'] ?? ''));
if ($title === '') {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'title_required', 'message' => 'A milestone title is required.']]);
}
$percentRaw = $input['completion_percent'] ?? 0;
if ($percentRaw === '' || $percentRaw === null) $percentRaw = 0;
if (!is_numeric($percentRaw) || !is_finite((float)$percentRaw) || (float)$percentRaw < 0 || (float)$percentRaw > 100) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_completion_percent', 'message' => 'Completion percentage must be between 0 and 100.']]);
}
$percent = (float)$percentRaw;
$plannedDate = trim((string)($input['planned_date'] ?? ''));
if ($plannedDate !== '') {
    $parsedDate = DateTime::createFromFormat('!Y-m-d', $plannedDate);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $plannedDate) {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_planned_date', 'message' => 'Planned date must use YYYY-MM-DD format.']]);
    }
}

$description = trim((string)($input['description'] ?? ''));
$notes = trim((string)($input['notes'] ?? ''));

try {
    dbExecute('START TRANSACTION');
    $project = dbFetchOne(
        "SELECT op.id, COALESCE(pl.lifecycle_status, op.status) AS lifecycle_status
         FROM other_projects op
         LEFT JOIN project_lifecycle pl ON pl.project_id = op.id
         WHERE op.id = ? FOR UPDATE",
        [$projectId]
    );
    if (!$project) {
        dbExecute('ROLLBACK');
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'project_not_found', 'message' => 'The project was not found.']]);
    }
    if ((string)$project['lifecycle_status'] === 'closed') {
        dbExecute('ROLLBACK');
        ak_api_json(409, ['ok' => false, 'error' => ['code' => 'project_closed', 'message' => 'Milestones cannot be added to a closed project.']]);
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
            ak_api_json(403, ['ok' => false, 'error' => ['code' => 'assignment_required', 'message' => 'You no longer have an operational assignment to this project.']]);
        }
    }

    $status = $percent >= 100 ? 'completed' : 'in_progress';
    dbExecute(
        'INSERT INTO project_milestones (project_id, title, description, planned_date, status, completion_percent, notes, created_by) VALUES (?,?,?,?,?,?,?,?)',
        [$projectId, $title, $description !== '' ? $description : null, $plannedDate !== '' ? $plannedDate : null, $status, $percent, $notes !== '' ? $notes : null, $userId]
    );
    $milestoneId = (int)db()->lastInsertId();
    $historyReason = 'التاريخ المخطط: ' . ($plannedDate !== '' ? $plannedDate : '—') . ' | نسبة الإنجاز: ' . number_format($percent, 2) . '% | وصف المرحلة: ' . ($description !== '' ? $description : '—');
    dbExecute(
        'INSERT INTO project_status_history (project_id, old_status, new_status, reason, changed_by) VALUES (?, ?, ?, ?, ?)',
        [$projectId, 'إضافة مرحلة', $title, $historyReason, $userId]
    );
    dbExecute('COMMIT');
} catch (Throwable $e) {
    try { dbExecute('ROLLBACK'); } catch (Throwable $ignored) {}
    error_log('Mobile project milestone creation failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'milestone_create_failed', 'message' => 'Unable to save the milestone.']]);
}

try {
    dbExecute(
        "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, 'CREATE', 'project_milestone', ?, NULL, ?, ?, ?)",
        [$userId, $milestoneId, json_encode(['project_id' => $projectId, 'title' => $title, 'planned_date' => $plannedDate, 'completion_percent' => $percent, 'description' => $description], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
    );
} catch (Throwable $e) {
    error_log('Mobile project milestone audit failed: ' . $e->getMessage());
}

ak_api_json(200, ['ok' => true, 'data' => ['milestone_id' => $milestoneId, 'message' => 'Milestone saved.']]);
