-- AHL EL KHEIR
-- Final Fina settlement model: permanent 2300 liability, full-cycle settlement only,
-- separate Fina-held funds control asset, and historical test evidence preservation.
--
-- This is the single Fina settlement migration. It includes the original settlement
-- schema so the superseded Stage 3 migration does not remain a prerequisite.
-- No triggers or views are created by this migration.

CREATE TABLE IF NOT EXISTS fina_settlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    settlement_code VARCHAR(50) NOT NULL,
    settlement_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency_code VARCHAR(10) NOT NULL,
    payment_method VARCHAR(32) NOT NULL,
    remitting_account_id INT UNSIGNED NULL,
    transfer_reference VARCHAR(150) NULL,
    evidence_path VARCHAR(500) NULL,
    status ENUM('draft','approved','transferred','reconciled','closed','cancelled') NOT NULL DEFAULT 'draft',
    is_test TINYINT(1) NOT NULL DEFAULT 0,
    settlement_journal_id INT UNSIGNED NULL,
    reconciliation_note TEXT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    transferred_by INT UNSIGNED NULL,
    transferred_at DATETIME NULL,
    reconciled_by INT UNSIGNED NULL,
    reconciled_at DATETIME NULL,
    cancelled_by INT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancellation_note TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fina_settlements_code (settlement_code),
    KEY idx_fina_settlements_date (settlement_date),
    KEY idx_fina_settlements_status (status),
    KEY idx_fina_settlements_currency (currency_code),
    KEY idx_fina_settlements_account (remitting_account_id),
    KEY idx_fina_settlements_journal (settlement_journal_id),
    CONSTRAINT fk_fina_settlements_account FOREIGN KEY (remitting_account_id) REFERENCES accounts(id) ON UPDATE CASCADE,
    CONSTRAINT fk_fina_settlements_journal FOREIGN KEY (settlement_journal_id) REFERENCES journal_entries(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fina_settlement_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    settlement_id BIGINT UNSIGNED NOT NULL,
    fina_collection_id BIGINT UNSIGNED NOT NULL,
    allocated_amount DECIMAL(15,2) NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fina_settlement_collection (settlement_id, fina_collection_id),
    KEY idx_fina_allocations_collection (fina_collection_id),
    KEY idx_fina_allocations_settlement (settlement_id),
    CONSTRAINT fk_fina_allocations_settlement FOREIGN KEY (settlement_id) REFERENCES fina_settlements(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fina_allocations_collection FOREIGN KEY (fina_collection_id) REFERENCES fina_collections(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE fina_settlements
    MODIFY remitting_account_id INT UNSIGNED NULL;

ALTER TABLE fina_settlements
    ADD COLUMN IF NOT EXISTS is_test TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

UPDATE fina_settlements
   SET is_test=1
 WHERE settlement_code IN ('FINA-SET-000001','FINA-SET-000002');

SET @fina_holding_id := (
    SELECT id FROM accounts
     WHERE name_en='Fina Al-Khair Held Funds'
     LIMIT 1
);

SET @fina_holding_code := (
    SELECT LPAD(COALESCE(MAX(CAST(code AS UNSIGNED)),1399)+1,4,'0')
      FROM accounts
     WHERE account_type='asset'
       AND code REGEXP '^[0-9]+$'
       AND CAST(code AS UNSIGNED) BETWEEN 1400 AND 1498
);

INSERT INTO accounts (code,name_ar,name_en,account_type,is_active,description)
SELECT @fina_holding_code,
       'أموال فينا الخير المحتفظ بها',
       'Fina Al-Khair Held Funds',
       'asset',1,
       'Custody/control asset for Fina Al-Khair third-party funds. Not Ahl operating treasury.'
 WHERE @fina_holding_id IS NULL
   AND @fina_holding_code IS NOT NULL;

SET @fina_holding_id := (
    SELECT id FROM accounts
     WHERE name_en='Fina Al-Khair Held Funds'
     LIMIT 1
);

INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by)
SELECT CONCAT('JE-FINA-TEST-RESTORE-',LPAD(s.id,6,'0')),
       CURRENT_DATE,
       CONCAT('استعادة أثر سجل اختبار تسوية فينا الخير ',s.settlement_code,' — سجل اختبار محفوظ'),
       'fina_settlement_test_restore',s.id,'posted',s.created_by
  FROM fina_settlements s
 WHERE s.is_test=1
   AND s.settlement_journal_id IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM journal_entries j
        WHERE j.reference_type='fina_settlement_test_restore'
          AND j.reference_id=s.id
   );

INSERT INTO journal_lines (entry_id,account_id,debit,credit,description)
SELECT r.id,l.account_id,l.credit,l.debit,CONCAT('عكس محاسبي لسجل اختبار التسوية ',s.settlement_code)
  FROM fina_settlements s
  JOIN journal_entries r ON r.reference_type='fina_settlement_test_restore' AND r.reference_id=s.id
  JOIN journal_lines l ON l.entry_id=s.settlement_journal_id
 WHERE s.is_test=1
   AND NOT EXISTS (SELECT 1 FROM journal_lines x WHERE x.entry_id=r.id);

INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by)
SELECT CONCAT('JE-FINA-HOLDING-',LPAD(c.id,6,'0')),
       c.collection_date,
       CONCAT('إعادة تصنيف أموال تحصيل فينا الخير إلى حساب الأموال المحتفظ بها — تحصيل #',c.id),
       'fina_collection_holding_reclass',c.id,'posted',c.created_by
  FROM fina_collections c
 WHERE c.status='approved'
   AND c.accounting_journal_id IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM journal_entries j
        WHERE j.reference_type='fina_collection_holding_reclass'
          AND j.reference_id=c.id
   );

INSERT INTO journal_lines (entry_id,account_id,debit,credit,description)
SELECT r.id,@fina_holding_id,l.debit,0,'أموال فينا الخير المحتفظ بها — إعادة تصنيف'
  FROM fina_collections c
  JOIN journal_entries r ON r.reference_type='fina_collection_holding_reclass' AND r.reference_id=c.id
  JOIN journal_entries oj ON oj.id=c.accounting_journal_id
  JOIN journal_lines l ON l.entry_id=oj.id AND l.debit>0
 WHERE c.status='approved'
   AND NOT EXISTS (SELECT 1 FROM journal_lines x WHERE x.entry_id=r.id)
UNION ALL
SELECT r.id,l.account_id,0,l.debit,'إخراج أموال فينا الخير من حساب أموال أهل الخير التشغيلي'
  FROM fina_collections c
  JOIN journal_entries r ON r.reference_type='fina_collection_holding_reclass' AND r.reference_id=c.id
  JOIN journal_entries oj ON oj.id=c.accounting_journal_id
  JOIN journal_lines l ON l.entry_id=oj.id AND l.debit>0
 WHERE c.status='approved'
   AND NOT EXISTS (SELECT 1 FROM journal_lines x WHERE x.entry_id=r.id);
