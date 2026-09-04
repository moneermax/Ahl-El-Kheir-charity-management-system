<?php

function ensureSupervisorLetterHistoryTable(): void
{
    dbExecute("CREATE TABLE IF NOT EXISTS supervisor_letter_assignment_history (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        supervisor_letter_id INT UNSIGNED NULL,
        supervisor_id INT UNSIGNED NOT NULL,
        letter_id TINYINT UNSIGNED NOT NULL,
        gender ENUM('male','female','both') NOT NULL DEFAULT 'both',
        assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        assigned_by INT UNSIGNED NULL,
        assignment_type ENUM('permanent','temporary') NOT NULL DEFAULT 'permanent',
        assignment_reason VARCHAR(100) NULL,
        ended_at DATETIME NULL,
        ended_by INT UNSIGNED NULL,
        end_reason VARCHAR(100) NULL,
        INDEX idx_slah_supervisor (supervisor_id),
        INDEX idx_slah_letter (letter_id),
        INDEX idx_slah_supervisor_letter (supervisor_letter_id),
        INDEX idx_slah_active (letter_id, gender, ended_at),
        CONSTRAINT fk_slah_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE RESTRICT,
        CONSTRAINT fk_slah_letter FOREIGN KEY (letter_id) REFERENCES letters(id) ON DELETE RESTRICT,
        CONSTRAINT fk_slah_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_slah_ended_by FOREIGN KEY (ended_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_slah_supervisor_letter FOREIGN KEY (supervisor_letter_id) REFERENCES supervisor_letters(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
