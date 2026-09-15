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

-- The existing disbursement workflow changes monthly_disbursements to transferred
-- before creating its outgoing transaction/journal. Block that transition when
-- protected فينا الخير funds would be consumed.
DROP TRIGGER IF EXISTS trg_fina_protect_disbursement_transfer;
DELIMITER $$
CREATE TRIGGER trg_fina_protect_disbursement_transfer
BEFORE UPDATE ON monthly_disbursements
FOR EACH ROW
BEGIN
    DECLARE protected_amount DECIMAL(14,2) DEFAULT 0.00;
    DECLARE currency_code_value CHAR(3) DEFAULT 'SDG';

    IF NEW.status = 'transferred' AND COALESCE(OLD.status,'') <> 'transferred' THEN
        SET protected_amount = COALESCE((
            SELECT SUM(fina_share_amount - settled_amount)
            FROM fina_payment_allocations
            WHERE currency_code = currency_code_value
              AND status IN ('protected','partially_settled')
        ),0.00);

        IF ROUND(COALESCE(NEW.total_amount,0),2) > ROUND(protected_amount + (
            SELECT COALESCE(SUM(jl.debit - jl.credit),0)
            FROM journal_lines jl
            JOIN journal_entries je ON je.id = jl.entry_id
            JOIN accounts a ON a.id = jl.account_id
            WHERE je.reference_type = 'disbursement'
              AND je.reference_id = NEW.id
              AND je.status = 'posted'
              AND a.code IN ('1100','1200','1300')
        ),2) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'لا يمكن تنفيذ الصرف: العملية ستستهلك أموالاً محمية ومستحقة لفينا الخير.';
        END IF;
    END IF;
END$$
DELIMITER ;

-- Defense-in-depth: a direct insertion of a disbursement transaction is allowed
-- only when its monthly disbursement batch is already in the transferred state.
DROP TRIGGER IF EXISTS trg_fina_validate_disbursement_transaction;
DELIMITER $$
CREATE TRIGGER trg_fina_validate_disbursement_transaction
BEFORE INSERT ON transactions
FOR EACH ROW
BEGIN
    DECLARE batch_status VARCHAR(30) DEFAULT NULL;
    DECLARE batch_id INT UNSIGNED DEFAULT NULL;

    IF NEW.transaction_type = 'disbursement' AND NEW.reference_number LIKE 'DISB-OUT-%' THEN
        SET batch_id = CAST(SUBSTRING(NEW.reference_number,10) AS UNSIGNED);
        SELECT status INTO batch_status
        FROM monthly_disbursements
        WHERE id = batch_id
        LIMIT 1;

        IF batch_status IS NULL OR batch_status <> 'transferred' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'لا يمكن إنشاء قيد صرف مباشر قبل اعتماد وتحويل دفعة الصرف.';
        END IF;
    END IF;
END$$
DELIMITER ;
