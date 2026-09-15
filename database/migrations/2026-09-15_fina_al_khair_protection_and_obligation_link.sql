-- فينا الخير: preserve the monthly obligation identity on each posted sponsor payment.
ALTER TABLE transactions
    ADD COLUMN IF NOT EXISTS sponsor_payment_obligation_id INT UNSIGNED NULL AFTER payment_period;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transactions'
      AND CONSTRAINT_NAME = 'fk_transactions_sponsor_payment_obligation'
);
SET @fk_sql := IF(@fk_exists = 0,
    'ALTER TABLE transactions ADD CONSTRAINT fk_transactions_sponsor_payment_obligation FOREIGN KEY (sponsor_payment_obligation_id) REFERENCES sponsor_payment_obligations(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- IMPORTANT DATABASE RULE:
-- Do not create database triggers or views for this project.
-- Fina protected-funds enforcement and disbursement workflow validation belong
-- in the procedural PHP application layer, where the authorization, business
-- rules, audit trail, and user-facing error handling are explicit and testable.
-- This migration therefore contains schema/FK work only.
