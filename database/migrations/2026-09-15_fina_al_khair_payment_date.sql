-- Fina Al-Khair / فينا الخير
-- Preserve the actual Supervisor-entered payment date separately from created_at.
ALTER TABLE sponsor_payments
    ADD COLUMN IF NOT EXISTS payment_date DATE NULL AFTER payment_period;
