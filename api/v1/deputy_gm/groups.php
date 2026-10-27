<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
}

$user = ak_api_authenticated_user();
if ((string)$user['role_code'] !== 'vice_general_manager') {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Orphan-group management is limited to the Vice General Manager.']]);
}

function ak_mobile_group_can_change_members(string $status): bool
{
    return in_array($status, ['draft', 'active', 'empty'], true);
}

function ak_mobile_group_usage(int $groupId): array
{
    $row = dbFetchOne(
        'SELECT
            (SELECT COUNT(*) FROM group_children WHERE group_id = ?) AS member_history,
            (SELECT COUNT(*) FROM nanny_group_assignments WHERE group_id = ?) AS nanny_history,
            (SELECT COUNT(*) FROM monthly_disbursements WHERE group_id = ?) AS disbursement_history',
        [$groupId, $groupId, $groupId]
    ) ?: [];
    return [
        'member_history' => (int)($row['member_history'] ?? 0),
        'nanny_history' => (int)($row['nanny_history'] ?? 0),
        'disbursement_history' => (int)($row['disbursement_history'] ?? 0),
    ];
}

function ak_mobile_group_has_current_disbursement(int $groupId, string $month): bool
{
    return dbFetchOne(
        'SELECT 1 AS found FROM monthly_disbursements WHERE group_id = ? AND month = ? LIMIT 1',
        [$groupId, $month]
    ) !== null;
}

function ak_mobile_group_sync_state(int $groupId): void
{
    $group = dbFetchOne('SELECT status FROM orphan_groups WHERE id = ? FOR UPDATE', [$groupId]);
    if (!$group || !in_array((string)$group['status'], ['active', 'empty'], true)) return;
    $row = dbFetchOne('SELECT COUNT(*) AS total FROM group_children WHERE group_id = ? AND left_date IS NULL', [$groupId]) ?: [];
    $status = (int)($row['total'] ?? 0) > 0 ? 'active' : 'empty';
    dbExecute('UPDATE orphan_groups SET status = ?, is_active = 1, updated_at = NOW() WHERE id = ?', [$status, $groupId]);
}

function ak_mobile_group_fail(string $code, string $message, int $status = 422): never
{
    ak_api_json($status, ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
}

if ($method === 'POST') {
    $input = ak_api_read_json();
    $action = trim((string)($input['action'] ?? ''));
    $groupId = filter_var($input['group_id'] ?? 0, FILTER_VALIDATE_INT);
    $groupId = $groupId === false ? 0 : (int)$groupId;
    $actorId = (int)$user['id'];
    $month = date('Y-m');

    $allowedActions = [
        'create_group', 'update_group', 'activate_group', 'suspend_group', 'close_group',
        'delete_group', 'assign_nanny', 'add_orphan', 'add_orphan_bulk', 'remove_orphan', 'transfer_orphan',
    ];
    if (!in_array($action, $allowedActions, true)) {
        ak_mobile_group_fail('invalid_action', 'A supported group action is required.');
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        if ($action === 'create_group') {
            $name = trim((string)($input['group_name'] ?? ''));
            $description = trim((string)($input['description'] ?? ''));
            if ($name === '' || mb_strlen($name, 'UTF-8') > 150 || mb_strlen($description, 'UTF-8') > 1000) {
                throw new DomainException('A group name (up to 150 characters) and description (up to 1000 characters) are required.');
            }
            dbExecute(
                "INSERT INTO orphan_groups (group_name, description, created_by, is_active, status, updated_at)
                 VALUES (?, ?, ?, 0, 'draft', NOW())",
                [$name, $description, $actorId]
            );
            $newId = (int)$pdo->lastInsertId();
            $pdo->commit();
            ak_api_json(200, ['ok' => true, 'data' => ['action' => $action, 'group_id' => $newId, 'message' => 'Group created as a draft.']]);
        }

        if ($groupId < 1) throw new DomainException('A valid group ID is required.');
        $group = dbFetchOne('SELECT * FROM orphan_groups WHERE id = ? FOR UPDATE', [$groupId]);
        if (!$group) {
            $pdo->rollBack();
            ak_mobile_group_fail('group_not_found', 'The selected group does not exist.', 404);
        }
        $status = (string)$group['status'];

        if ($action === 'update_group') {
            $name = trim((string)($input['group_name'] ?? ''));
            $description = trim((string)($input['description'] ?? ''));
            if ($name === '' || mb_strlen($name, 'UTF-8') > 150 || mb_strlen($description, 'UTF-8') > 1000) {
                throw new DomainException('A group name (up to 150 characters) and description (up to 1000 characters) are required.');
            }
            if (in_array($status, ['closed', 'cancelled', 'archived'], true)) {
                throw new DomainException('Closed, cancelled, and archived groups cannot be edited.');
            }
            dbExecute('UPDATE orphan_groups SET group_name = ?, description = ?, updated_at = NOW() WHERE id = ?', [$name, $description, $groupId]);
        } elseif (in_array($action, ['activate_group', 'suspend_group', 'close_group'], true)) {
            $members = dbFetchOne('SELECT COUNT(*) AS total FROM group_children WHERE group_id = ? AND left_date IS NULL', [$groupId]) ?: [];
            $memberCount = (int)($members['total'] ?? 0);
            $reason = trim((string)($input['reason'] ?? ''));
            if ($action === 'activate_group') {
                if (in_array($status, ['closed', 'cancelled', 'archived'], true)) throw new DomainException('A terminal group cannot be reactivated.');
                if ($memberCount < 1) throw new DomainException('An empty group cannot be activated. Add members first.');
                dbExecute("UPDATE orphan_groups SET status = 'active', is_active = 1, lifecycle_reason = ?, updated_at = NOW() WHERE id = ?", [$reason !== '' ? $reason : 'تفعيل المجموعة', $groupId]);
            } elseif ($action === 'suspend_group') {
                if (!in_array($status, ['active', 'empty'], true)) throw new DomainException('Only active or empty groups can be suspended.');
                dbExecute("UPDATE orphan_groups SET status = 'suspended', is_active = 0, lifecycle_reason = ?, updated_at = NOW() WHERE id = ?", [$reason, $groupId]);
            } else {
                if (!in_array($status, ['active', 'empty', 'suspended'], true)) throw new DomainException('Only active, empty, or suspended groups can be closed.');
                dbExecute("UPDATE orphan_groups SET status = 'closed', is_active = 0, lifecycle_reason = ?, closed_at = COALESCE(closed_at, NOW()), closed_by = ?, updated_at = NOW() WHERE id = ?", [$reason, $actorId, $groupId]);
            }
        } elseif ($action === 'delete_group') {
            $usage = ak_mobile_group_usage($groupId);
            if (array_sum($usage) > 0) throw new DomainException('A group with member, nanny, or disbursement history cannot be deleted. Close it instead.');
            if (!in_array($status, ['draft', 'empty'], true)) throw new DomainException('Only a never-used draft or empty group can be deleted.');
            dbExecute('DELETE FROM orphan_groups WHERE id = ?', [$groupId]);
        } elseif ($action === 'assign_nanny') {
            if (!ak_mobile_group_can_change_members($status)) throw new DomainException('A nanny can only be assigned to an operational group.');
            $nannyId = filter_var($input['nanny_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$nannyId || $nannyId < 1) throw new DomainException('Select an active nanny.');
            $nanny = dbFetchOne(
                "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND r.code = 'nanny' AND u.is_active = 1 FOR UPDATE",
                [(int)$nannyId]
            );
            if (!$nanny) throw new DomainException('The selected account is not an active nanny.');
            dbExecute('UPDATE nanny_group_assignments SET end_date = CURDATE() WHERE group_id = ? AND end_date IS NULL', [$groupId]);
            dbExecute('INSERT INTO nanny_group_assignments (group_id, nanny_id, start_date, assigned_by) VALUES (?, ?, CURDATE(), ?)', [$groupId, (int)$nannyId, $actorId]);
        } elseif (in_array($action, ['add_orphan', 'add_orphan_bulk'], true)) {
            if (!ak_mobile_group_can_change_members($status)) throw new DomainException('Members cannot be changed for this group status.');
            if (ak_mobile_group_has_current_disbursement($groupId, $month)) throw new DomainException('Members cannot be changed after this month’s disbursement is created.');
            $rawIds = $action === 'add_orphan' ? [$input['child_id'] ?? 0] : ($input['child_ids'] ?? []);
            if (!is_array($rawIds)) throw new DomainException('A list of child IDs is required.');
            $childIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn(int $id): bool => $id > 0)));
            if (!$childIds) throw new DomainException('Select at least one eligible child.');
            $check = $pdo->prepare(
                "SELECT fc.id FROM family_children fc
                 JOIN families f ON f.id = fc.family_id AND f.status = 'active'
                 JOIN sponsorships sp ON sp.child_id = fc.id AND sp.status = 'active'
                 WHERE fc.id = ? AND fc.is_active = 1
                   AND NOT EXISTS (
                     SELECT 1 FROM group_children gc
                     JOIN monthly_disbursements md ON md.group_id = gc.group_id AND md.month = ?
                     WHERE gc.child_id = fc.id AND gc.left_date IS NULL
                   )"
            );
            $insert = $pdo->prepare('INSERT INTO group_children (group_id, child_id, joined_date, added_by) VALUES (?, ?, CURDATE(), ?)');
            $added = 0;
            foreach ($childIds as $childId) {
                $check->execute([$childId, $month]);
                if (!$check->fetchColumn()) continue;
                try {
                    $insert->execute([$groupId, $childId, $actorId]);
                    $added++;
                } catch (PDOException $e) {
                    if ((string)$e->getCode() !== '23000') throw $e;
                }
            }
            if ($added === 0) throw new DomainException('No members were added. Check eligibility, existing membership, and current-month disbursement protection.');
            ak_mobile_group_sync_state($groupId);
        } elseif ($action === 'remove_orphan') {
            $childId = filter_var($input['child_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$childId || $childId < 1) throw new DomainException('A valid child ID is required.');
            if (!ak_mobile_group_can_change_members($status)) throw new DomainException('Members cannot be changed for this group status.');
            if (ak_mobile_group_has_current_disbursement($groupId, $month)) throw new DomainException('A member cannot be removed after the current-month disbursement is created.');
            $stmt = $pdo->prepare('UPDATE group_children SET left_date = CURDATE() WHERE group_id = ? AND child_id = ? AND left_date IS NULL');
            $stmt->execute([$groupId, (int)$childId]);
            if ($stmt->rowCount() < 1) throw new DomainException('The child is not a current member of this group.');
            ak_mobile_group_sync_state($groupId);
        } elseif ($action === 'transfer_orphan') {
            $childId = filter_var($input['child_id'] ?? null, FILTER_VALIDATE_INT);
            $targetId = filter_var($input['target_group_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$childId || $childId < 1 || !$targetId || $targetId < 1 || (int)$targetId === $groupId) throw new DomainException('Valid source, child, and different target group IDs are required.');
            if (!ak_mobile_group_can_change_members($status)) throw new DomainException('The source group is not operational.');
            if (ak_mobile_group_has_current_disbursement($groupId, $month) || ak_mobile_group_has_current_disbursement((int)$targetId, $month)) {
                throw new DomainException('Members cannot be transferred to or from a group with a current-month disbursement.');
            }
            $target = dbFetchOne('SELECT id, status FROM orphan_groups WHERE id = ? FOR UPDATE', [(int)$targetId]);
            if (!$target || !ak_mobile_group_can_change_members((string)$target['status'])) throw new DomainException('The target group is not operational.');
            $member = dbFetchOne('SELECT id FROM group_children WHERE group_id = ? AND child_id = ? AND left_date IS NULL FOR UPDATE', [$groupId, (int)$childId]);
            if (!$member) throw new DomainException('The child is not a current member of the source group.');
            $prior = dbFetchOne('SELECT id FROM group_children WHERE group_id = ? AND child_id = ? LIMIT 1', [(int)$targetId, (int)$childId]);
            if ($prior) throw new DomainException('The child already has membership history in the target group.');
            dbExecute('UPDATE group_children SET left_date = CURDATE() WHERE group_id = ? AND child_id = ? AND left_date IS NULL', [$groupId, (int)$childId]);
            dbExecute('INSERT INTO group_children (group_id, child_id, joined_date, added_by) VALUES (?, ?, CURDATE(), ?)', [(int)$targetId, (int)$childId, $actorId]);
            ak_mobile_group_sync_state($groupId);
            ak_mobile_group_sync_state((int)$targetId);
        }

        $pdo->commit();
        ak_api_json(200, ['ok' => true, 'data' => ['action' => $action, 'group_id' => $groupId, 'message' => 'Group operation completed.']]);
    } catch (DomainException|InvalidArgumentException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        ak_mobile_group_fail('invalid_group_action', $e->getMessage());
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        error_log('Mobile orphan-group action failed: ' . $e->getMessage());
        ak_mobile_group_fail('group_action_failed', 'The group operation could not be completed.', 500);
    }
}

$month = date('Y-m');
$groupId = filter_var($_GET['group_id'] ?? 0, FILTER_VALIDATE_INT);
$groupId = $groupId === false ? 0 : (int)$groupId;
$groups = dbFetchAll(
    "SELECT og.id, og.group_name, og.description, og.is_active, og.status, og.lifecycle_reason, og.created_at,
            COALESCE(nga.nanny_id, 0) AS nanny_id, COALESCE(u.full_name, '') AS nanny_name,
            (SELECT COUNT(*) FROM group_children gc WHERE gc.group_id = og.id AND gc.left_date IS NULL) AS orphan_count,
            (SELECT COUNT(*) FROM monthly_disbursements md WHERE md.group_id = og.id) AS disbursement_count
     FROM orphan_groups og
     LEFT JOIN nanny_group_assignments nga ON nga.group_id = og.id AND nga.end_date IS NULL
     LEFT JOIN users u ON u.id = nga.nanny_id
     ORDER BY FIELD(og.status, 'active','empty','draft','suspended','closed','cancelled','archived'), og.created_at DESC"
);
$data = [
    'month' => $month,
    'groups' => $groups,
    'selected_group' => null,
    'members' => [],
    'nanny_history' => [],
    'usage' => ['member_history' => 0, 'nanny_history' => 0, 'disbursement_history' => 0],
    'nannies' => dbFetchAll("SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'nanny' AND u.is_active = 1 ORDER BY u.full_name"),
    'eligible_orphans' => [],
    'target_groups' => [],
];
if ($groupId > 0) {
    $selected = dbFetchOne('SELECT * FROM orphan_groups WHERE id = ?', [$groupId]);
    if (!$selected) ak_mobile_group_fail('group_not_found', 'The selected group does not exist.', 404);
    $data['selected_group'] = $selected;
    $data['members'] = dbFetchAll(
        "SELECT gc.child_id, gc.joined_date, fc.child_name, fc.birth_date, f.mother_name AS family_name,
                TIMESTAMPDIFF(YEAR, fc.birth_date, CURDATE()) AS age
         FROM group_children gc
         JOIN family_children fc ON fc.id = gc.child_id
         LEFT JOIN families f ON f.id = fc.family_id
         WHERE gc.group_id = ? AND gc.left_date IS NULL
         ORDER BY fc.child_name",
        [$groupId]
    );
    $data['nanny_history'] = dbFetchAll(
        "SELECT nga.id, nga.nanny_id, nga.start_date, nga.end_date, u.full_name AS nanny_name,
                u2.full_name AS assigned_by_name
         FROM nanny_group_assignments nga JOIN users u ON u.id = nga.nanny_id
         LEFT JOIN users u2 ON u2.id = nga.assigned_by
         WHERE nga.group_id = ? ORDER BY nga.start_date DESC, nga.id DESC",
        [$groupId]
    );
    $data['usage'] = ak_mobile_group_usage($groupId);
    $data['has_current_disbursement'] = ak_mobile_group_has_current_disbursement($groupId, $month);
    if (ak_mobile_group_can_change_members((string)$selected['status'])) {
        $data['eligible_orphans'] = dbFetchAll(
            "SELECT fc.id, fc.child_name, f.mother_name AS family_name, f.family_code,
                    TIMESTAMPDIFF(YEAR, fc.birth_date, CURDATE()) AS age,
                    s.full_name AS sponsor_name
             FROM family_children fc
             JOIN families f ON f.id = fc.family_id AND f.status = 'active'
             JOIN sponsorships sp ON sp.child_id = fc.id AND sp.status = 'active'
             JOIN sponsors s ON s.id = sp.sponsor_id
             WHERE fc.is_active = 1
               AND NOT EXISTS (
                 SELECT 1 FROM group_children gc
                 JOIN monthly_disbursements md ON md.group_id = gc.group_id AND md.month = ?
                 WHERE gc.child_id = fc.id AND gc.left_date IS NULL
               )
             ORDER BY f.mother_name, fc.child_name",
            [$month]
        );
        $data['target_groups'] = dbFetchAll(
            "SELECT og.id, og.group_name,
                    (SELECT COUNT(*) FROM group_children gc WHERE gc.group_id = og.id AND gc.left_date IS NULL) AS orphan_count
             FROM orphan_groups og
             WHERE og.id <> ? AND og.status IN ('active','empty')
               AND NOT EXISTS (SELECT 1 FROM monthly_disbursements md WHERE md.group_id = og.id AND md.month = ?)
             ORDER BY og.group_name",
            [$groupId, $month]
        );
    }
}
$data['can_change_members'] = $groupId > 0 && ak_mobile_group_can_change_members((string)($data['selected_group']['status'] ?? ''));
$data['has_current_disbursement'] = $data['has_current_disbursement'] ?? false;
ak_api_json(200, ['ok' => true, 'data' => $data]);
