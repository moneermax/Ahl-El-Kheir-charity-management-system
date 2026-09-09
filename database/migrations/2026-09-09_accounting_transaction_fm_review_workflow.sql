-- AHL EL KHEIR
-- Accounting Phase 1: ordinary transaction FM review workflow
--
-- Lifecycle:
-- pending_fm_review -> returned -> pending_fm_review -> posted
-- pending_fm_review -> cancelled
-- posted -> voided (existing post-approval correction path)
--
-- Existing posted/voided records are preserved.

ALTER TABLE transactions
    MODIFY COLUMN status ENUM('pending_fm_review','returned','cancelled','posted','voided') NOT NULL DEFAULT 'pending_fm_review';

ALTER TABLE transactions
    ADD COLUMN fm_reviewed_by INT UNSIGNED NULL AFTER created_by,
    ADD COLUMN fm_reviewed_at DATETIME NULL AFTER fm_reviewed_by,
    ADD COLUMN fm_review_reason VARCHAR(255) NULL AFTER fm_reviewed_at,
    ADD COLUMN submitted_at DATETIME NULL AFTER fm_review_reason,
    ADD COLUMN resubmitted_at DATETIME NULL AFTER submitted_at,
    ADD COLUMN cancelled_at DATETIME NULL AFTER resubmitted_at,
    ADD COLUMN cancelled_by INT UNSIGNED NULL AFTER cancelled_at,
    ADD COLUMN cancel_reason VARCHAR(255) NULL AFTER cancelled_by;

CREATE INDEX idx_transactions_fm_review_status ON transactions (status);
CREATE INDEX idx_transactions_created_by_status ON transactions (created_by, status);

-- Backfill timestamps for existing records without changing their accounting state.
UPDATE transactions
SET submitted_at = COALESCE(submitted_at, created_at)
WHERE submitted_at IS NULL;
