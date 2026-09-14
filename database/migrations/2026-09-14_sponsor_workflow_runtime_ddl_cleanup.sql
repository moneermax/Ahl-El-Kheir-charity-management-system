-- AHL EL KHEIR
-- Sponsor workflow schema migration.
-- These tables/columns must exist before normal sponsor pages are rendered.
-- The application must not create or alter schema during a normal web request.

CREATE TABLE IF NOT EXISTS sponsor_supervisor_assignments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sponsor_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sponsor_name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    source VARCHAR(100),
    brought_by INT,
    brought_by_name VARCHAR(255),
    status ENUM('new','contacted','converted','lost') DEFAULT 'new',
    notes TEXT,
    created_sponsor_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sponsor_requests
    ADD COLUMN IF NOT EXISTS created_sponsor_id INT NULL;
