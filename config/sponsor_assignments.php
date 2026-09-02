<?php
// Sponsor supervisor assignment history and departure handling.
require_once __DIR__ . '/database.php';

function ensureSponsorAssignmentHistoryTable(): void
{
    dbExecute("CREATE TABLE IF NOT EXISTS sponsor_supervisor_assignments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sponsor_id INT UNSIGNED NOT NULL,
        supervisor_id INT UNSIGNED NOT NULL,
        assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        assigned_by INT UNSIGNED NULL,
        ended_at DATETIME NULL,
        ended_by INT UNSIGNED NULL,
        end_reason VARCHAR(100) NULL,
        INDEX idx_ssa_sponsor (sponsor_id),
        INDEX idx_ssa_supervisor (supervisor_id),
        INDEX idx_ssa_active (sponsor_id, ended_at),
        CONSTRAINT fk_ssa_sponsor FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE,
        CONSTRAINT fk_ssa_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE RESTRICT,
        CONSTRAINT fk_ssa_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_ssa_ended_by FOREIGN KEY (ended_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function recordSponsorAssignment(int $sponsorId, ?int $supervisorId, ?int $actorId, string $reason = 'assignment'): void
{
    ensureSponsorAssignmentHistoryTable();
    $current = dbFetchOne("SELECT id, supervisor_id FROM sponsors WHERE id = ?", [$sponsorId]);
    if (!$current) return;
    $old = (int)($current['supervisor_id'] ?? 0);
    $new = (int)($supervisorId ?? 0);
    if ($old === $new) return;

    dbExecute("UPDATE sponsor_supervisor_assignments SET ended_at = NOW(), ended_by = ?, end_reason = ? WHERE sponsor_id = ? AND ended_at IS NULL", [$actorId, $reason, $sponsorId]);
    if ($new > 0) {
        dbExecute("INSERT INTO sponsor_supervisor_assignments (sponsor_id, supervisor_id, assigned_by) VALUES (?, ?, ?)", [$sponsorId, $new, $actorId]);
    }
}

function releaseSupervisorSponsors(int $supervisorId, ?int $actorId): int
{
    ensureSponsorAssignmentHistoryTable();
    $rows = dbFetchAll("SELECT id FROM sponsors WHERE supervisor_id = ? FOR UPDATE", [$supervisorId]);
    foreach ($rows as $row) {
        $sid = (int)$row['id'];
        dbExecute("UPDATE sponsor_supervisor_assignments SET ended_at = NOW(), ended_by = ?, end_reason = 'supervisor_departure' WHERE sponsor_id = ? AND ended_at IS NULL", [$actorId, $sid]);
    }
    if ($rows) dbExecute("UPDATE sponsors SET supervisor_id = NULL, is_manual_override = 0, assigned_by = NULL, assigned_at = NULL, updated_by = ? WHERE supervisor_id = ?", [$actorId, $supervisorId]);
    return count($rows);
}
