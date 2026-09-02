-- Sponsor supervisor assignment history
-- Keeps the organization-level sponsor record intact while preserving every assignment period.
CREATE TABLE IF NOT EXISTS sponsor_supervisor_assignments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
