-- HR Salary Advance Stage 6: direct repayment supporting evidence
-- Schema-only migration. No runtime DDL, triggers, or views.
--
-- Separate from disbursement receipts: one document belongs to one
-- direct repayment transaction, not to the salary-advance request as a whole.
CREATE TABLE IF NOT EXISTS hr_salary_advance_direct_repayment_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    direct_repayment_id INT UNSIGNED NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_salary_advance_direct_repayment_document (direct_repayment_id),
    KEY idx_hr_salary_advance_direct_repayment_documents_uploader (uploaded_by),
    CONSTRAINT fk_hr_salary_advance_direct_repayment_documents_repayment
        FOREIGN KEY (direct_repayment_id)
        REFERENCES hr_salary_advance_direct_repayments(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
