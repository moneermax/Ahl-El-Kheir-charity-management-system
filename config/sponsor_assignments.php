<?php
// Sponsor supervisor assignment history and lifecycle-safe assignment handling.
require_once __DIR__ . '/database.php';

function ensureSponsorAssignmentHistoryTable(): void
{
    dbExecute("CREATE TABLE IF NOT EXISTS sponsor_supervisor_assignments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sponsor_id INT UNSIGNED NOT NULL,
        supervisor_id INT UNSIGNED NOT NULL,
        assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        assigned_by INT UNSIGNED NULL,
        assignment_type ENUM('permanent','temporary') NOT NULL DEFAULT 'permanent',
        ended_at DATETIME NULL,
        ended_by INT UNSIGNED NULL,
        end_reason VARCHAR(100) NULL,
        assignment_reason VARCHAR(100) NULL,
        INDEX idx_ssa_sponsor (sponsor_id),
        INDEX idx_ssa_supervisor (supervisor_id),
        INDEX idx_ssa_active (sponsor_id, ended_at),
        CONSTRAINT fk_ssa_sponsor FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE,
        CONSTRAINT fk_ssa_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE RESTRICT,
        CONSTRAINT fk_ssa_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_ssa_ended_by FOREIGN KEY (ended_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function recordSponsorAssignment(
    int $sponsorId,
    ?int $supervisorId,
    ?int $actorId,
    string $reason = 'assignment',
    string $assignmentType = 'permanent'
): void {
    ensureSponsorAssignmentHistoryTable();

    if (!in_array($assignmentType, ['permanent', 'temporary'], true)) {
        $assignmentType = 'permanent';
    }

    $current = dbFetchOne("SELECT id, supervisor_id FROM sponsors WHERE id = ?", [$sponsorId]);
    if (!$current) return;

    $old = (int)($current['supervisor_id'] ?? 0);
    $new = (int)($supervisorId ?? 0);
    if ($old === $new) return;

    dbExecute("UPDATE sponsor_supervisor_assignments
        SET ended_at = NOW(), ended_by = ?, end_reason = ?
        WHERE sponsor_id = ? AND ended_at IS NULL", [$actorId, $reason, $sponsorId]);

    if ($new > 0) {
        dbExecute("INSERT INTO sponsor_supervisor_assignments
            (sponsor_id, supervisor_id, assigned_by, assignment_type, assignment_reason)
            VALUES (?, ?, ?, ?, ?)", [$sponsorId, $new, $actorId, $assignmentType, $reason]);
    }
}

function ensureActiveSponsorAssignmentHistory(int $sponsorId, int $supervisorId, ?int $actorId = null): void
{
    ensureSponsorAssignmentHistoryTable();

    $active = dbFetchOne("SELECT id FROM sponsor_supervisor_assignments
        WHERE sponsor_id = ? AND supervisor_id = ? AND ended_at IS NULL LIMIT 1", [$sponsorId, $supervisorId]);
    if ($active) return;

    $sponsor = dbFetchOne("SELECT assigned_at FROM sponsors WHERE id = ? AND supervisor_id = ?", [$sponsorId, $supervisorId]);
    if (!$sponsor) return;

    // Legacy assignments have no trustworthy historical assignment date. Prefer the existing
    // assignment timestamp when available; otherwise record the backfill moment explicitly.
    $assignedAt = !empty($sponsor['assigned_at']) ? $sponsor['assigned_at'] : date('Y-m-d H:i:s');
    dbExecute("INSERT INTO sponsor_supervisor_assignments
        (sponsor_id, supervisor_id, assigned_at, assigned_by, assignment_type, assignment_reason)
        VALUES (?, ?, ?, ?, 'permanent', 'legacy_backfill')",
        [$sponsorId, $supervisorId, $assignedAt, $actorId]);
}

function releaseSupervisorSponsors(int $supervisorId, ?int $actorId, string $reason = 'supervisor_departure'): int
{
    ensureSponsorAssignmentHistoryTable();

    $rows = dbFetchAll("SELECT id FROM sponsors WHERE supervisor_id = ? FOR UPDATE", [$supervisorId]);
    foreach ($rows as $row) {
        $sid = (int)$row['id'];
        ensureActiveSponsorAssignmentHistory($sid, $supervisorId, $actorId);
        dbExecute("UPDATE sponsor_supervisor_assignments
            SET ended_at = NOW(), ended_by = ?, end_reason = ?
            WHERE sponsor_id = ? AND supervisor_id = ? AND ended_at IS NULL",
            [$actorId, $reason, $sid, $supervisorId]);
    }

    if ($rows) {
        dbExecute("UPDATE sponsors
            SET supervisor_id = NULL,
                is_manual_override = 0,
                assigned_by = NULL,
                assigned_at = NULL,
                updated_by = ?
            WHERE supervisor_id = ?", [$actorId, $supervisorId]);
    }

    return count($rows);
}

function restoreSponsorAssignment(int $sponsorId, int $supervisorId, ?int $actorId, string $reason = 'leave_return'): bool
{
    ensureSponsorAssignmentHistoryTable();

    $current = dbFetchOne("SELECT supervisor_id FROM sponsors WHERE id = ? FOR UPDATE", [$sponsorId]);
    if (!$current) return false;
    if ((int)($current['supervisor_id'] ?? 0) === $supervisorId) return true;
    if ((int)($current['supervisor_id'] ?? 0) !== 0) return false;

    dbExecute("UPDATE sponsor_supervisor_assignments
        SET ended_at = NOW(), ended_by = ?, end_reason = ?
        WHERE sponsor_id = ? AND ended_at IS NULL", [$actorId, $reason, $sponsorId]);
    dbExecute("INSERT INTO sponsor_supervisor_assignments
        (sponsor_id, supervisor_id, assigned_by, assignment_type, assignment_reason)
        VALUES (?, ?, ?, 'permanent', ?)", [$sponsorId, $supervisorId, $actorId, $reason]);
    dbExecute("UPDATE sponsors SET supervisor_id = ?, assigned_by = ?, assigned_at = NOW(), updated_by = ? WHERE id = ? AND supervisor_id IS NULL",
        [$supervisorId, $actorId, $actorId, $sponsorId]);

    return true;
}
