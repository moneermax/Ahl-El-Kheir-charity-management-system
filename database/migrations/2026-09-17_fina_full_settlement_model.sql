-- AHL EL KHEIR
-- Fina settlement model correction: permanent 2300 liability, full-cycle settlement only,
-- and separate Fina-held funds control asset.
-- This migration preserves historical settlement/collection journals and test evidence.

ALTER TABLE fina_settlements
    MODIFY remitting_account_id INT UNSIGNED NULL;

ALTER TABLE fina_settlements
    ADD COLUMN IF NOT EXISTS is_test TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

UPDATE fina_settlements
   SET is_test=1
 WHERE settlement_code IN ('FINA-SET-000001','FINA-SET-000002');

-- The Fina-held funds account is a custody/control asset, not an Ahl treasury account.
-- Its numeric code is selected from the reserved 1400-1499 asset range without
-- overwriting an existing account. The stable lookup key is name_en.
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

-- Restore the permanent 2300 liability from the two retained Stage 3 development
-- settlement journals. The original test journals remain untouched; compensating
-- posted entries neutralize their accounting effect while preserving their history.
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
SELECT r.id,
       l.account_id,
       l.credit,
       l.debit,
       CONCAT('عكس محاسبي لسجل اختبار التسوية ',s.settlement_code)
  FROM fina_settlements s
  JOIN journal_entries r
    ON r.reference_type='fina_settlement_test_restore'
   AND r.reference_id=s.id
  JOIN journal_lines l ON l.entry_id=s.settlement_journal_id
 WHERE s.is_test=1;

-- Reclassify the original Fina collection debit from the Ahl payment-method asset
-- into the dedicated Fina-held funds asset. Original collection journals remain intact.
INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by)
SELECT CONCAT('JE-FINA-HOLDING-',LPAD(c.id,6,'0')),
       c.collection_date,
       CONCAT('إعادة تصنيف أموال تحصيل فينا الخير إلى حساب الأموال المحتفظ بها — تحصيل #',c.id),
       'fina_collection_holding_reclass',c.id,'posted',c.reviewed_by
  FROM fina_collections c
 WHERE c.status='approved'
   AND NOT EXISTS (
       SELECT 1 FROM journal_entries j
        WHERE j.reference_type='fina_collection_holding_reclass'
          AND j.reference_id=c.id
   );

INSERT INTO journal_lines (entry_id,account_id,debit,credit,description)
SELECT r.id,@fina_holding_id,l.debit,0,'أموال فينا الخير المحتفظ بها — إعادة تصنيف'
  FROM fina_collections c
  JOIN journal_entries r
    ON r.reference_type='fina_collection_holding_reclass'
   AND r.reference_id=c.id
  JOIN journal_entries oj ON oj.id=c.accounting_journal_id
  JOIN journal_lines l ON l.entry_id=oj.id AND l.debit>0
 WHERE c.status='approved';

INSERT INTO journal_lines (entry_id,account_id,debit,credit,description)
SELECT r.id,l.account_id,0,l.debit,'إخراج أموال فينا الخير من حساب أموال أهل الخير التشغيلي'
  FROM fina_collections c
  JOIN journal_entries r
    ON r.reference_type='fina_collection_holding_reclass'
   AND r.reference_id=c.id
  JOIN journal_entries oj ON oj.id=c.accounting_journal_id
  JOIN journal_lines l ON l.entry_id=oj.id AND l.debit>0
 WHERE c.status='approved';
