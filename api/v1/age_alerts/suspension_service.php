<?php
declare(strict_types=1);

/**
 * Apply the existing web legal-age child suspension operation.
 *
 * The caller owns the database transaction. This keeps the endpoint response,
 * audit event, and rollback boundary explicit, and lets disposable-schema tests
 * verify the business mutation without an HTTP server.
 */
final class AkLegalAgeSuspensionForbidden extends RuntimeException
{
}

function ak_api_normalize_legal_age_role(string $role): string
{
    return [
        'sudo' => 'admin',
        'super_admin' => 'admin',
        'vgm' => 'vice_general_manager',
        'gm' => 'general_manager',
    ][$role] ?? $role;
}

function ak_api_suspend_legal_age_child(int $userId, string $role, int $childId, string $notes): array
{
    $role = ak_api_normalize_legal_age_role($role);
    if (!in_array($role, ['admin', 'vice_general_manager', 'nanny'], true)) {
        throw new AkLegalAgeSuspensionForbidden('This account cannot suspend sponsorships from legal-age alerts.');
    }
    if ($userId < 1 || $childId < 1) {
        throw new InvalidArgumentException('A valid user and child ID are required.');
    }
    if (!db()->inTransaction()) {
        throw new LogicException('Legal-age suspension requires an active transaction.');
    }

    $child = dbFetchOne(
        "SELECT fc.id, fc.family_id, fc.child_name, fc.birth_date, fc.is_active,
                f.nanny_id
         FROM family_children fc
         INNER JOIN families f ON f.id = fc.family_id
         WHERE fc.id = ?
         FOR UPDATE",
        [$childId]
    );
    if (!$child) {
        throw new OutOfBoundsException('The child record was not found.');
    }
    if ($role === 'nanny' && (int)$child['nanny_id'] !== $userId) {
        throw new AkLegalAgeSuspensionForbidden('This family is outside your assigned scope.');
    }

    // Check the active case before alert eligibility so a repeated submission
    // returns a duplicate conflict rather than a misleading stale-alert result.
    $existing = dbFetchOne(
        "SELECT id FROM suspension_cases
         WHERE entity_type = 'child' AND entity_id = ? AND status = 'active'
         LIMIT 1 FOR UPDATE",
        [$childId]
    );
    if ($existing) {
        throw new DomainException('An active suspension already exists for this child.');
    }

    $eligible = dbFetchOne(
        "SELECT id FROM family_children
         WHERE id = ? AND is_active = 1 AND birth_date IS NOT NULL
           AND birth_date <= DATE_SUB(DATE_ADD(CURDATE(), INTERVAL 90 DAY), INTERVAL 18 YEAR)
           AND birth_date >= DATE_SUB(CURDATE(), INTERVAL 21 YEAR)",
        [$childId]
    );
    if (!$eligible) {
        throw new OutOfBoundsException('The child is no longer eligible for the legal-age alert action.');
    }

    dbExecute(
        "INSERT INTO suspension_cases (entity_type, entity_id, reason, notes, suspended_by)
         VALUES ('child', ?, 'age_out', ?, ?)",
        [$childId, $notes !== '' ? $notes : null, $userId]
    );
    $pdo = db();
    $caseId = (int)$pdo->lastInsertId();
    if ($caseId < 1) {
        throw new RuntimeException('The suspension case could not be created.');
    }

    $marker = 'تعليق [CASE#' . $caseId . '] بلوغ السن القانوني';
    dbExecute(
        "UPDATE family_children
         SET is_active = 0, disqualified_at = CURDATE(), disqualification_reason = 'age_out'
         WHERE id = ? AND is_active = 1",
        [$childId]
    );
    if ((int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn() !== 1) {
        throw new RuntimeException('The child record changed before suspension could be saved.');
    }

    dbExecute(
        "UPDATE sponsorships sp
         INNER JOIN sponsorship_children sc ON sc.sponsorship_id = sp.id
         SET sp.status = 'paused', sp.pause_reason = ?,
             sp.pause_start_date = CURDATE(), sp.updated_at = NOW()
         WHERE sc.family_child_id = ? AND sp.status = 'active'",
        [$marker, $childId]
    );
    $pausedSponsorships = (int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn();

    return [
        'case_id' => $caseId,
        'child_id' => $childId,
        'sponsorships_paused' => $pausedSponsorships,
    ];
}
