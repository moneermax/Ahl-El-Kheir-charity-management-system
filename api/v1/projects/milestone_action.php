<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$role = (string)$user['role_code'];
$input = ak_api_read_json();
$action = trim((string)($input['action'] ?? ''));

if (!in_array($role, ['admin', 'project_supervisor'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'You do not have permission to manage project milestones.']]);
}
if (!in_array($action, ['edit', 'delete'], true)) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => 'A supported milestone action is required.']]);
}
$projectId = filter_var($input['project_id'] ?? null, FILTER_VALIDATE_INT);
$milestoneId = filter_var($input['milestone_id'] ?? null, FILTER_VALIDATE_INT);
if (!$projectId || $projectId < 1 || !$milestoneId || $milestoneId < 1) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_ids', 'message' => 'Valid project and milestone IDs are required.']]);
}

$title = trim((string)($input['title'] ?? ''));
$description = trim((string)($input['description'] ?? ''));
$plannedDate = trim((string)($input['planned_date'] ?? ''));
$percentRaw = $input['completion_percent'] ?? 0;
if ($action === 'edit') {
    if ($title === '') {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'title_required', 'message' => 'A milestone title is required.']]);
    }
    if ($percentRaw === '' || $percentRaw === null) $percentRaw = 0;
    if (!is_numeric($percentRaw) || !is_finite((float)$percentRaw) || (float)$percentRaw < 0 || (float)$percentRaw > 100) {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_completion_percent', 'message' => 'Completion percentage must be between 0 and 100.']]);
    }
    if ($plannedDate !== '') {
        $parsedDate = DateTime::createFromFormat('!Y-m-d', $plannedDate);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $plannedDate) {
            ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_planned_date', 'message' => 'Planned date must use YYYY-MM-DD format.']]);
        }
    }
}

try {
    dbExecute('START TRANSACTION');
    $project = dbFetchOne(
        "SELECT op.id,
                COALESCE(pa.approval_status, 'draft') AS approval_status,
                COALESCE(pl.lifecycle_status, op.status) AS lifecycle_status
         FROM other_projects op
         LEFT JOIN project_approval pa ON pa.project_id = op.id
         LEFT JOIN project_lifecycle pl ON pl.project_id = op.id
         WHERE op.id = ? FOR UPDATE",
        [(int)$projectId]
    );
    if (!$project) {
        dbExecute('ROLLBACK');
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'project_not_found', 'message' => 'The project was not found.']]);
    }
    $lifecycle = (string)$project['lifecycle_status'];
    if ($lifecycle === 'closed') {
        dbExecute('ROLLBACK');
        ak_api_json(409, ['ok' => false, 'error' => ['code' => 'project_closed', 'message' => 'Milestones cannot be changed on a closed project.']]);
    }
    if ($role !== 'admin' && (
        (string)$project['approval_status'] !== 'approved'
        || !in_array($lifecycle, ['active', 'reopened', 'completed', 'under_review', 'cancelled'], true)
    )) {
        dbExecute('ROLLBACK');
        ak_api_json(409, ['ok' => false, 'error' => [
            'code' => 'project_not_operational',
            'message' => 'This project is not available for operational updates.',
        ]]);
    }
    if ($role === 'project_supervisor') {
        $primary = dbFetchOne(
            'SELECT id FROM project_supervisor_assignments WHERE project_id = ? AND supervisor_user_id = ? AND ended_at IS NULL LIMIT 1',
            [(int)$projectId, $userId]
        );
        $operations = dbFetchOne(
            "SELECT id FROM project_team WHERE project_id = ? AND user_id = ? AND section_code = 'operations' AND unassigned_at IS NULL LIMIT 1",
            [(int)$projectId, $userId]
        );
        if (!$primary && !$operations) {
            dbExecute('ROLLBACK');
            ak_api_json(403, ['ok' => false, 'error' => ['code' => 'assignment_required', 'message' => 'You no longer have an operational assignment to this project.']]);
        }
    }

    $milestone = dbFetchOne(
        'SELECT id, title, description, planned_date, status, completion_percent FROM project_milestones WHERE id = ? AND project_id = ? FOR UPDATE',
        [(int)$milestoneId, (int)$projectId]
    );
    if (!$milestone) {
        dbExecute('ROLLBACK');
        ak_api_json(404, ['ok' => false, 'error' => ['code' => 'milestone_not_found', 'message' => 'The milestone was not found in this project.']]);
    }

    if ($action === 'edit') {
        $percent = (float)$percentRaw;
        $status = $percent >= 100 ? 'completed' : 'in_progress';
        dbExecute(
            'UPDATE project_milestones SET title = ?, description = ?, planned_date = ?, status = ?, completion_percent = ? WHERE id = ? AND project_id = ?',
            [$title, $description !== '' ? $description : null, $plannedDate !== '' ? $plannedDate : null, $status, $percent, (int)$milestoneId, (int)$projectId]
        );
    } else {
        dbExecute('DELETE FROM project_milestones WHERE id = ? AND project_id = ?', [(int)$milestoneId, (int)$projectId]);
    }
    dbExecute('COMMIT');
} catch (Throwable $e) {
    try { dbExecute('ROLLBACK'); } catch (Throwable $ignored) {}
    error_log('Mobile project milestone action failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'milestone_action_failed', 'message' => 'Unable to apply the milestone change.']]);
}

ak_api_json(200, ['ok' => true, 'data' => ['action' => $action, 'milestone_id' => (int)$milestoneId, 'message' => $action === 'edit' ? 'Milestone updated.' : 'Milestone deleted.']]);
