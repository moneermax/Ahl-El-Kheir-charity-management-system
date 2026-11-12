-- AHL EL KHEIR
-- Per-monthly-payment evidence for Supervisor submissions.
-- Existing rows remain NULL and retain the historical created_at fallback.
-- Apply through the project's migration process. No request-time DDL.

SET NAMES utf8mb4;

ALTER TABLE sponsor_payments
    ADD COLUMN IF NOT EXISTS transaction_date DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS receipt_number VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS reference_number VARCHAR(100) DEFAULT NULL;
