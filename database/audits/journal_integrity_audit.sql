-- AHL EL KHEIR — Journal Integrity / Accounting History Audit
-- READ-ONLY AUDIT SCRIPT
-- Purpose: verify journal history, reference separation, reversal preservation,
-- voucher linkage, duplicate/missing relationships, and balanced posted/voided entries.
-- This script does NOT INSERT, UPDATE, DELETE, ALTER, or DROP anything.

USE ahl_el_kheir;

-- 1) Journal population by reference type and status.
SELECT
    reference_type,
    status,
    COUNT(*) AS journal_count
FROM journal_entries
GROUP BY reference_type, status
ORDER BY reference_type, status;

-- 2) Any journal entry without lines.
SELECT
    je.id,
    je.entry_code,
    je.reference_type,
    je.reference_id,
    je.status
FROM journal_entries je
LEFT JOIN journal_lines jl ON jl.entry_id = je.id
WHERE jl.id IS NULL
ORDER BY je.id;

-- 3) Any journal entry with invalid line amounts:
--    negative amount, or both debit and credit populated on one line.
SELECT
    jl.id,
    jl.entry_id,
    jl.account_id,
    jl.debit,
    jl.credit
FROM journal_lines jl
WHERE jl.debit < 0
   OR jl.credit < 0
   OR (jl.debit > 0 AND jl.credit > 0)
ORDER BY jl.entry_id, jl.id;

-- 4) Every journal must balance exactly to 2 decimals.
SELECT
    je.id,
    je.entry_code,
    je.reference_type,
    je.reference_id,
    je.status,
    ROUND(COALESCE(SUM(jl.debit), 0), 2) AS total_debit,
    ROUND(COALESCE(SUM(jl.credit), 0), 2) AS total_credit,
    ROUND(COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0), 2) AS difference
FROM journal_entries je
LEFT JOIN journal_lines jl ON jl.entry_id = je.id
GROUP BY je.id, je.entry_code, je.reference_type, je.reference_id, je.status
HAVING difference <> 0
ORDER BY je.id;

-- 5) Duplicate automated transaction journals.
SELECT
    reference_id AS transaction_id,
    COUNT(*) AS journal_count
FROM journal_entries
WHERE reference_type = 'transaction'
GROUP BY reference_id
HAVING COUNT(*) > 1
ORDER BY reference_id;

-- 6) Duplicate transaction reversal journals.
SELECT
    reference_id AS transaction_id,
    COUNT(*) AS reversal_count
FROM journal_entries
WHERE reference_type = 'transaction_void'
GROUP BY reference_id
HAVING COUNT(*) > 1
ORDER BY reference_id;

-- 7) Transaction journal relationship integrity.
-- A posted transaction should have exactly one posted journal.
SELECT
    t.id AS transaction_id,
    t.transaction_code,
    t.status AS transaction_status,
    COUNT(je.id) AS posted_journal_count
FROM transactions t
LEFT JOIN journal_entries je
       ON je.reference_type = 'transaction'
      AND je.reference_id = t.id
      AND je.status = 'posted'
WHERE t.status = 'posted'
GROUP BY t.id, t.transaction_code, t.status
HAVING posted_journal_count <> 1
ORDER BY t.id;

-- 8) A voided transaction must preserve its original journal as voided
-- and have exactly one transaction_void reversal.
SELECT
    t.id AS transaction_id,
    t.transaction_code,
    COUNT(CASE WHEN je.reference_type = 'transaction' AND je.status = 'voided' THEN 1 END) AS original_voided_count,
    COUNT(CASE WHEN je.reference_type = 'transaction_void' THEN 1 END) AS reversal_count
FROM transactions t
LEFT JOIN journal_entries je
       ON (je.reference_type = 'transaction' AND je.reference_id = t.id)
       OR (je.reference_type = 'transaction_void' AND je.reference_id = t.id)
WHERE t.status = 'voided'
GROUP BY t.id, t.transaction_code
HAVING original_voided_count <> 1 OR reversal_count <> 1
ORDER BY t.id;

-- 9) Orphan transaction journal references.
SELECT
    je.id,
    je.entry_code,
    je.reference_type,
    je.reference_id
FROM journal_entries je
LEFT JOIN transactions t ON t.id = je.reference_id
WHERE je.reference_type IN ('transaction', 'transaction_void')
  AND t.id IS NULL
ORDER BY je.id;

-- 10) Voucher ↔ journal linkage integrity.
-- Every voucher should point to an existing journal whose reference is voucher/id.
SELECT
    v.id AS voucher_id,
    v.voucher_no,
    v.status AS voucher_status,
    v.entry_id,
    je.id AS journal_id,
    je.reference_type,
    je.reference_id,
    je.status AS journal_status
FROM vouchers v
LEFT JOIN journal_entries je ON je.id = v.entry_id
WHERE v.entry_id IS NULL
   OR je.id IS NULL
   OR je.reference_type <> 'voucher'
   OR je.reference_id <> v.id
ORDER BY v.id;

-- 11) Duplicate voucher journals.
SELECT
    reference_id AS voucher_id,
    COUNT(*) AS journal_count
FROM journal_entries
WHERE reference_type = 'voucher'
GROUP BY reference_id
HAVING COUNT(*) > 1
ORDER BY reference_id;

-- 12) Voucher status ↔ journal status consistency for the current voucher model.
-- Posted voucher => posted journal; voided voucher => original voucher journal voided.
SELECT
    v.id AS voucher_id,
    v.voucher_no,
    v.status AS voucher_status,
    je.status AS journal_status
FROM vouchers v
JOIN journal_entries je
  ON je.id = v.entry_id
WHERE (v.status = 'posted' AND je.status <> 'posted')
   OR (v.status = 'voided' AND je.status <> 'voided')
ORDER BY v.id;

-- 13) Voucher reversal audit: if a voucher has been voided, inspect any
-- reversal entry explicitly. This does not assume a reversal type; it exposes
-- the actual history for review because the current voucher core may use its
-- own reversal implementation.
SELECT
    v.id AS voucher_id,
    v.voucher_no,
    v.status AS voucher_status,
    original.id AS original_journal_id,
    original.entry_code AS original_entry_code,
    original.status AS original_journal_status,
    reversal.id AS reversal_journal_id,
    reversal.entry_code AS reversal_entry_code,
    reversal.reference_type AS reversal_reference_type,
    reversal.status AS reversal_status
FROM vouchers v
LEFT JOIN journal_entries original ON original.id = v.entry_id
LEFT JOIN journal_entries reversal
       ON reversal.reference_id = v.id
      AND reversal.id <> v.entry_id
      AND reversal.reference_type IN ('voucher_void', 'transaction_void', 'manual_void', 'disbursement_void')
WHERE v.status = 'voided'
ORDER BY v.id;

-- 14) Journal lines pointing to missing accounts (should return zero rows).
SELECT
    jl.id,
    jl.entry_id,
    jl.account_id
FROM journal_lines jl
LEFT JOIN accounts a ON a.id = jl.account_id
WHERE a.id IS NULL
ORDER BY jl.entry_id, jl.id;

-- 15) Voided journal history must retain its original lines.
SELECT
    je.id,
    je.entry_code,
    je.reference_type,
    je.reference_id,
    COUNT(jl.id) AS line_count
FROM journal_entries je
LEFT JOIN journal_lines jl ON jl.entry_id = je.id
WHERE je.status = 'voided'
GROUP BY je.id, je.entry_code, je.reference_type, je.reference_id
HAVING line_count = 0
ORDER BY je.id;

-- 16) Accounting-history net balance check across ALL journal statuses.
-- Posted + reversal entries should balance globally; voided originals remain
-- historical records and are intentionally included as historical debit/credit.
SELECT
    COUNT(DISTINCT je.id) AS journal_count,
    COUNT(jl.id) AS line_count,
    ROUND(COALESCE(SUM(jl.debit), 0), 2) AS total_debit,
    ROUND(COALESCE(SUM(jl.credit), 0), 2) AS total_credit,
    ROUND(COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0), 2) AS difference
FROM journal_entries je
LEFT JOIN journal_lines jl ON jl.entry_id = je.id;

-- 17) Reference types currently present in the database.
SELECT
    COALESCE(reference_type, '(NULL)') AS reference_type,
    COUNT(*) AS journal_count
FROM journal_entries
GROUP BY reference_type
ORDER BY reference_type;
