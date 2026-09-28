-- HR Salary Advance Stage 4: payment receipt evidence
-- Schema-only migration. No runtime DDL, triggers, or views.

CREATE TABLE IF NOT EXISTS hr_salary_advance_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    salary_advance_request_id INT UNSIGNED NOT NULL,
    document_type ENUM('payment_receipt') NOT NULL DEFAULT 'payment_receipt',
    file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hr_salary_advance_document (salary_advance_request_id, document_type),
    KEY idx_hr_salary_advance_documents_uploader (uploaded_by),
    CONSTRAINT fk_hr_salary_advance_documents_request
        FOREIGN KEY (salary_advance_request_id) REFERENCES hr_salary_advance_requests(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);