<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

final class AkSuspensionLiftForbidden extends RuntimeException
{
}

/**
 * Return the same scoped case list shown by modules/families/suspensions.php.
 * The authenticated endpoint passes the canonical role from the API bootstrap.
 */
function ak_api_list_suspension_cases(int $userId, string $role): array
{
    if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager', 'nanny'], true)) {
        throw new AkSuspensionLiftForbidden('Suspension cases are not available for this account.');
    }

    $scopeSql = '';
    $params = [];
    if ($role === 'nanny') {
        $scopeSql = ' AND COALESCE(f.nanny_id, f2.nanny_id) = ?';
        $params[] = $userId;
    }

    $rows = dbFetchAll(
        "SELECT c.id, c.entity_type, c.entity_id, c.reason, c.notes, c.status,
                c.suspended_at, c.lifted_at,
                u.full_name AS suspended_by_name, lb.full_name AS lifted_by_name,
                COALESCE(f.mother_name, fc.child_name) AS entity_name,
                COALESCE(f.family_code, f2.family_code) AS family_code
         FROM suspension_cases c
         LEFT JOIN families f ON f.id = c.entity_id AND c.entity_type = 'family'
         LEFT JOIN family_children fc ON fc.id = c.entity_id AND c.entity_type = 'child'
         LEFT JOIN families f2 ON f2.id = fc.family_id
         LEFT JOIN users u ON u.id = c.suspended_by
         LEFT JOIN users lb ON lb.id = c.lifted_by
         WHERE 1=1 {$scopeSql}
         ORDER BY (c.status = 'active') DESC, c.id DESC
         LIMIT 300",
        $params
    );

    $items = array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'entity_type' => (string)$row['entity_type'],
        'entity_id' => (int)$row['entity_id'],
        'reason' => (string)$row['reason'],
        'notes' => (string)($row['notes'] ?? ''),
        'status' => (string)$row['status'],
        'suspended_at' => (string)($row['suspended_at'] ?? ''),
        'lifted_at' => (string)($row['lifted_at'] ?? ''),
        'suspended_by_name' => (string)($row['suspended_by_name'] ?? ''),
        'lifted_by_name' => (string)($row['lifted_by_name'] ?? ''),
        'entity_name' => (string)($row['entity_name'] ?? ''),
        'family_code' => (string)($row['family_code'] ?? ''),
    ], $rows);

    return [
        'items' => $items,
        'total' => count($items),
        'can_lift' => in_array($role, ['admin', 'vice_general_manager'], true),
    ];
}

/**
 * Lift an active case using the existing web workflow's status and linkage rules.
 * The caller owns the transaction so the case, child/family and sponsorship changes commit together.
 */
function ak_api_lift_suspension_case(int $userId, string $role, int $caseId): array
{
    if (!in_array($role, ['admin', 'vice_general_manager'], true)) {
        throw new AkSuspensionLiftForbidden('This account cannot lift suspension cases.');
    }
    if ($userId < 1 || $caseId < 1) {
        throw new InvalidArgumentException('A valid user and suspension case ID are required.');
    }
    if (!db()->inTransaction()) {
        throw new LogicException('Lifting a suspension requires an active transaction.');
    }

    $case = dbFetchOne(
        "SELECT id, entity_type, entity_id, status
         FROM suspension_cases
         WHERE id = ? AND status = 'active'
         LIMIT 1 FOR UPDATE",
        [$caseId]
    );
    if (!$case) {
        throw new OutOfBoundsException('The active suspension case was not found.');
    }

    dbExecute(
        "UPDATE suspension_cases
         SET status = 'lifted', lifted_by = ?, lifted_at = NOW()
         WHERE id = ? AND status = 'active'",
        [$userId, $caseId]
    );
    if ((int)db()->query('SELECT ROW_COUNT()')->fetchColumn() !== 1) {
        throw new DomainException('The suspension case is no longer active.');
    }

    $entityType = (string)$case['entity_type'];
    $entityId = (int)$case['entity_id'];
    if ($entityType === 'child') {
        $child = dbFetchOne('SELECT id FROM family_children WHERE id = ? FOR UPDATE', [$entityId]);
        if (!$child) {
            throw new OutOfBoundsException('The child linked to this suspension no longer exists.');
        }
        dbExecute(
            'UPDATE family_children
             SET is_active = 1, disqualified_at = NULL, disqualification_reason = NULL
             WHERE id = ?',
            [$entityId]
        );
    } elseif ($entityType === 'family') {
        $family = dbFetchOne('SELECT id FROM families WHERE id = ? FOR UPDATE', [$entityId]);
        if (!$family) {
            throw new OutOfBoundsException('The family linked to this suspension no longer exists.');
        }
        dbExecute(
            "UPDATE families SET status = 'active', updated_at = NOW()
             WHERE id = ? AND status = 'paused'",
            [$entityId]
        );
    } else {
        throw new DomainException('The suspension case has an unsupported entity type.');
    }

    $marker = '%[CASE#' . $caseId . ']%';
    dbExecute(
        "UPDATE sponsorships
         SET status = 'active', pause_end_date = CURDATE(), updated_at = NOW()
         WHERE status = 'paused' AND pause_reason LIKE ?",
        [$marker]
    );
    $reactivated = (int)db()->query('SELECT ROW_COUNT()')->fetchColumn();

    return [
        'case_id' => $caseId,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'sponsorships_reactivated' => $reactivated,
    ];
}
