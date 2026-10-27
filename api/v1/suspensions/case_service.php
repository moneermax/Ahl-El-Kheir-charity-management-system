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
        'can_create' => true,
    ];
}


final class AkSuspensionCreateForbidden extends RuntimeException
{
}

/** Search families and children using the existing web suspension-form scope. */
function ak_api_suspension_options(int $userId, string $role, string $query): array
{
    if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager', 'nanny'], true)) {
        throw new AkSuspensionLiftForbidden('Suspension cases are not available for this account.');
    }
    $query = trim($query);
    if (mb_strlen($query, 'UTF-8') < 2) return ['options' => [], 'can_create' => true];
    $like = '%' . $query . '%';
    $familyScope = $role === 'nanny' ? ' AND f.nanny_id = ?' : " AND f.status NOT IN ('closed','archived')";
    $familyParams = [$like, $like];
    if ($role === 'nanny') $familyParams[] = $userId;
    $families = dbFetchAll("SELECT f.id, f.family_code, f.mother_name FROM families f WHERE (f.family_code LIKE ? OR f.mother_name LIKE ?) {$familyScope} ORDER BY f.mother_name, f.id LIMIT 50", $familyParams);
    $childScope = $role === 'nanny' ? ' AND f.nanny_id = ?' : " AND f.status NOT IN ('closed','archived')";
    $childParams = [$like, $like, $like];
    if ($role === 'nanny') $childParams[] = $userId;
    $children = dbFetchAll(
        "SELECT c.id, c.child_name, f.id AS family_id, f.family_code, f.mother_name
         FROM family_children c JOIN families f ON f.id = c.family_id
         WHERE (c.child_name LIKE ? OR f.family_code LIKE ? OR f.mother_name LIKE ?) {$childScope}
         ORDER BY c.child_name, c.id LIMIT 50",
        $childParams
    );
    $options = [];
    foreach ($families as $family) $options[] = [
        'entity_type' => 'family', 'entity_id' => (int)$family['id'],
        'entity_name' => (string)$family['mother_name'], 'family_code' => (string)$family['family_code'],
        'family_id' => (int)$family['id'],
    ];
    foreach ($children as $child) $options[] = [
        'entity_type' => 'child', 'entity_id' => (int)$child['id'],
        'entity_name' => (string)$child['child_name'], 'family_code' => (string)$child['family_code'],
        'family_id' => (int)$child['family_id'], 'mother_name' => (string)$child['mother_name'],
    ];
    return ['options' => $options, 'can_create' => true];
}

/** Create a family/child suspension with the same side effects as the web form. */
function ak_api_create_suspension_case(int $userId, string $role, string $entityType, int $entityId, string $reason, string $notes): array
{
    if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager', 'nanny'], true)) {
        throw new AkSuspensionCreateForbidden('This account cannot create suspension cases.');
    }
    if ($userId < 1 || $entityId < 1 || !in_array($entityType, ['family', 'child'], true)) throw new InvalidArgumentException('Choose a valid family or child.');
    $reasons = [
        'age_out' => 'بلوغ السن القانوني', 'false_info' => 'معلومات غير صحيحة عند التقديم',
        'other_support' => 'وجود معيل/كفيل آخر من الأقارب', 'other' => 'سبب آخر',
    ];
    if (!isset($reasons[$reason])) throw new InvalidArgumentException('Choose a supported suspension reason.');
    if (!db()->inTransaction()) throw new LogicException('Creating a suspension requires an active transaction.');

    if ($entityType === 'family') {
        $entity = dbFetchOne('SELECT id, status, nanny_id FROM families WHERE id = ? LIMIT 1 FOR UPDATE', [$entityId]);
        if (!$entity) throw new OutOfBoundsException('The selected family was not found.');
        if ($role === 'nanny' && (int)($entity['nanny_id'] ?? 0) !== $userId) throw new AkSuspensionCreateForbidden('The selected family is outside your assigned scope.');
        if ($role !== 'nanny' && in_array((string)$entity['status'], ['closed', 'archived'], true)) throw new InvalidArgumentException('Closed or archived families cannot be suspended from this workflow.');
    } else {
        $entity = dbFetchOne('SELECT c.id, c.family_id, f.nanny_id FROM family_children c JOIN families f ON f.id = c.family_id WHERE c.id = ? LIMIT 1 FOR UPDATE', [$entityId]);
        if (!$entity) throw new OutOfBoundsException('The selected child was not found.');
        if ($role === 'nanny' && (int)($entity['nanny_id'] ?? 0) !== $userId) throw new AkSuspensionCreateForbidden('The selected child is outside your assigned family scope.');
    }
    $duplicate = dbFetchOne("SELECT id FROM suspension_cases WHERE entity_type = ? AND entity_id = ? AND status = 'active' LIMIT 1 FOR UPDATE", [$entityType, $entityId]);
    if ($duplicate) throw new DomainException('An active suspension already exists for this record.');

    dbExecute('INSERT INTO suspension_cases (entity_type, entity_id, reason, notes, suspended_by) VALUES (?, ?, ?, ?, ?)',
        [$entityType, $entityId, $reason, trim($notes) !== '' ? trim($notes) : null, $userId]);
    $caseId = (int)db()->lastInsertId();
    if ($caseId < 1) throw new RuntimeException('The suspension case ID could not be generated.');
    $marker = 'تعليق [CASE#' . $caseId . '] ' . $reasons[$reason];
    if ($entityType === 'child') {
        dbExecute('UPDATE family_children SET is_active = 0, disqualified_at = CURDATE(), disqualification_reason = ? WHERE id = ?', [$reason, $entityId]);
        dbExecute("UPDATE sponsorships sp JOIN sponsorship_children sc ON sc.sponsorship_id = sp.id SET sp.status = 'paused', sp.pause_reason = ?, sp.pause_start_date = CURDATE(), sp.updated_at = NOW() WHERE sc.family_child_id = ? AND sp.status = 'active'", [$marker, $entityId]);
    } else {
        dbExecute("UPDATE families SET status = 'paused', updated_at = NOW() WHERE id = ?", [$entityId]);
        dbExecute("UPDATE sponsorships SET status = 'paused', pause_reason = ?, pause_start_date = CURDATE(), updated_at = NOW() WHERE family_id = ? AND status = 'active'", [$marker, $entityId]);
    }
    $paused = (int)db()->query('SELECT ROW_COUNT()')->fetchColumn();
    return ['case_id' => $caseId, 'entity_type' => $entityType, 'entity_id' => $entityId, 'reason' => $reason, 'sponsorships_paused' => $paused];
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
