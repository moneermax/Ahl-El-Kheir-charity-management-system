<?php
// Sponsor supervisor assignment history and lifecycle-safe assignment handling.
require_once __DIR__ . '/database.php';

/**
 * Resolve the supervisor responsible for a sponsor from the authoritative
 * sponsor assignment matrix: sponsor-name first letter + sponsor gender.
 * The family/mother name is intentionally not consulted here.
 */
function resolveSponsorSupervisorId(string $fullName, string $gender): ?int
{
    [$raw, $normalized] = first_letter_of($fullName);
    if ($normalized === '') return null;

    $gender = strtolower(trim($gender));
    if (!in_array($gender, ['male', 'female'], true)) return null;

    $row = dbFetchOne(
        "SELECT sl.supervisor_id
         FROM supervisor_letters sl
         JOIN letters l ON l.id = sl.letter_id
         WHERE l.is_active = 1
           AND l.code = ?
           AND sl.gender IN (?, 'both')
         ORDER BY CASE WHEN sl.gender = ? THEN 0 ELSE 1 END, sl.id ASC
         LIMIT 1",
        [$normalized, $gender, $gender]
    );

    return $row ? (int)$row['supervisor_id'] : null;
}

/**
 * Authoritative supervisor scope for an individual sponsor.
 * A supervisor may access a sponsor when either:
 * 1) the sponsor is directly assigned to that supervisor; or
 * 2) the supervisor's first-letter/gender responsibility matrix covers it.
 */
function supervisorCanAccessSponsor(int $supervisorId, array $sponsor): bool
{
    if ($supervisorId <= 0) return false;
    if ((int)($sponsor['supervisor_id'] ?? 0) === $supervisorId) return true;

    $letterId = (int)($sponsor['first_letter_id'] ?? 0);
    if ($letterId <= 0) return false;

    $gender = strtolower(trim((string)($sponsor['gender'] ?? '')));
    $gender = in_array($gender, ['m', 'male', 'ذكر'], true)
        ? 'male'
        : (in_array($gender, ['f', 'female', 'أنثى', 'انثى'], true) ? 'female' : 'other');
    if ($gender === 'other') return false;

    $rows = dbFetchAll(
        "SELECT gender FROM supervisor_letters WHERE supervisor_id = ? AND letter_id = ?",
        [$supervisorId, $letterId]
    );
    foreach ($rows as $row) {
        $scopeGender = strtolower(trim((string)($row['gender'] ?? '')));
        $scopeGender = in_array($scopeGender, ['m', 'male', 'ذكر'], true)
            ? 'male'
            : (in_array($scopeGender, ['f', 'female', 'أنثى', 'انثى'], true)
                ? 'female'
                : (in_array($scopeGender, ['both', 'all', 'كلاهما', 'الكل'], true) ? 'both' : 'other'));
        if ($scopeGender === 'both' || $scopeGender === $gender) return true;
    }
    return false;
}

function recordSponsorAssignment(int $sponsorId, ?int $supervisorId, ?int $actorId, string $reason = 'assignment', string $assignmentType = 'permanent'): void
{
    if (!in_array($assignmentType, ['permanent', 'temporary'], true)) $assignmentType = 'permanent';
    $current = dbFetchOne("SELECT id, supervisor_id FROM sponsors WHERE id = ?", [$sponsorId]);
    if (!$current) return;
    $old = (int)($current['supervisor_id'] ?? 0);
    $new = (int)($supervisorId ?? 0);
    if ($old === $new) return;
    dbExecute("UPDATE sponsor_supervisor_assignments SET ended_at = NOW(), ended_by = ?, end_reason = ? WHERE sponsor_id = ? AND ended_at IS NULL", [$actorId, $reason, $sponsorId]);
    if ($new > 0) dbExecute("INSERT INTO sponsor_supervisor_assignments (sponsor_id, supervisor_id, assigned_by, assignment_type, assignment_reason) VALUES (?, ?, ?, ?, ?)", [$sponsorId, $new, $actorId, $assignmentType, $reason]);
}

function ensureActiveSponsorAssignmentHistory(int $sponsorId, int $supervisorId, ?int $actorId = null): void
{
    $active = dbFetchOne("SELECT id FROM sponsor_supervisor_assignments WHERE sponsor_id = ? AND supervisor_id = ? AND ended_at IS NULL LIMIT 1", [$sponsorId, $supervisorId]);
    if ($active) return;
    $sponsor = dbFetchOne("SELECT assigned_at FROM sponsors WHERE id = ? AND supervisor_id = ?", [$sponsorId, $supervisorId]);
    if (!$sponsor) return;
    $assignedAt = !empty($sponsor['assigned_at']) ? $sponsor['assigned_at'] : date('Y-m-d H:i:s');
    dbExecute("INSERT INTO sponsor_supervisor_assignments (sponsor_id, supervisor_id, assigned_at, assigned_by, assignment_type, assignment_reason) VALUES (?, ?, ?, ?, 'permanent', 'legacy_backfill')", [$sponsorId, $supervisorId, $assignedAt, $actorId]);
}

function releaseSupervisorSponsors(int $supervisorId, ?int $actorId, string $reason = 'supervisor_departure'): int
{
    $rows = dbFetchAll("SELECT id FROM sponsors WHERE supervisor_id = ? FOR UPDATE", [$supervisorId]);
    foreach ($rows as $row) {
        $sid = (int)$row['id'];
        ensureActiveSponsorAssignmentHistory($sid, $supervisorId, $actorId);
        dbExecute("UPDATE sponsor_supervisor_assignments SET ended_at = NOW(), ended_by = ?, end_reason = ? WHERE sponsor_id = ? AND supervisor_id = ? AND ended_at IS NULL", [$actorId, $reason, $sid, $supervisorId]);
    }
    if ($rows) dbExecute("UPDATE sponsors SET supervisor_id = NULL, is_manual_override = 0, assigned_by = NULL, assigned_at = NULL, updated_by = ? WHERE supervisor_id = ?", [$actorId, $supervisorId]);
    return count($rows);
}

function restoreSponsorAssignment(int $sponsorId, int $supervisorId, ?int $actorId, string $reason = 'leave_return'): bool
{
    $current = dbFetchOne("SELECT supervisor_id FROM sponsors WHERE id = ? FOR UPDATE", [$sponsorId]);
    if (!$current) return false;
    if ((int)($current['supervisor_id'] ?? 0) === $supervisorId) return true;
    if ((int)($current['supervisor_id'] ?? 0) !== 0) return false;
    dbExecute("UPDATE sponsor_supervisor_assignments SET ended_at = NOW(), ended_by = ?, end_reason = ? WHERE sponsor_id = ? AND ended_at IS NULL", [$actorId, $reason, $sponsorId]);
    dbExecute("INSERT INTO sponsor_supervisor_assignments (sponsor_id, supervisor_id, assigned_by, assignment_type, assignment_reason) VALUES (?, ?, ?, 'permanent', ?)", [$sponsorId, $supervisorId, $actorId, $reason]);
    dbExecute("UPDATE sponsors SET supervisor_id = ?, assigned_by = ?, assigned_at = NOW(), updated_by = ? WHERE id = ? AND supervisor_id IS NULL", [$supervisorId, $actorId, $actorId, $sponsorId]);
    return true;
}
