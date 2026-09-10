-- AHL EL KHEIR — Journal Integrity / Accounting History Audit
-- READ-ONLY AUDIT SCRIPT
-- Purpose: verify journal history, reference separation, reversal preservation,
-- voucher linkage, duplicate/missing relationships, and balanced posted/voided entries.
-- This script does NOT INSERT, UPDATE, DELETE, ALTER, or DROP anything.
--
-- IMPORTANT:
-- Generic transactions and monthly disbursements use different accounting lifecycles.
-- A disbursement transaction is a workflow wrapper for monthly_disbursements and
-- must NOT be required to have a reference_type='transaction' journal.

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

-- 7) Generic transaction journal relationship integrity.
-- Only generic transactions participate in the reference_type='transaction'
-- lifecycle. Disbursement transactions are deliberately excluded because their
-- accounting belongs to monthly_disbursements.
-- A posted generic transaction should have exactly one posted transaction journal.
SELECT
    t.id AS transaction_id,
    t.transaction_code,
    t.transaction_type,
    t.status AS transaction_status,
    COUNT(je.id) AS posted_journal_count
FROM transactions t
LEFT JOIN journal_entries je
       ON je.reference_type = 'transaction'
      AND je.reference_id = t.id
      AND je.status = 'posted'
WHERE t.status = 'posted'
  AND COALESCE(t.transaction_type, '') <> 'disbursement'
GROUP BY t.id, t.transaction_code, t.transaction_type, t.status
HAVING posted_journal_count <> 1
ORDER BY t.id;

-- 8) Generic transaction void integrity.
-- A voided generic transaction must preserve its original journal as voided
-- and have exactly one transaction_void reversal.
-- Disbursements are excluded because their void lifecycle is disbursement-specific.
SELECT
    t.id AS transaction_id,
    t.transaction_code,
    t.transaction_type,
    COUNT(CASE WHEN je.reference_type = 'transaction' AND je.status = 'voided' THEN 1 END) AS original_voided_count,
    COUNT(CASE WHEN je.reference_type = 'transaction_void' THEN 1 END) AS reversal_count
FROM transactions t
LEFT JOIN journal_entries je
       ON (je.reference_type = 'transaction' AND je.reference_id = t.id)
       OR (je.reference_type = 'transaction_void' AND je.reference_id = t.id)
WHERE t.status = 'voided'
  AND COALESCE(t.transaction_type, '') <> 'disbursement'
GROUP BY t.id, t.transaction_code, t.transaction_type
HAVING original_voided_count <> 1 OR reversal_count <> 1
ORDER BY t.id;

-- 9) Disbursement transaction wrappers must resolve to a monthly_disbursements row.
-- This identifies legacy/orphaned disbursement wrappers without manufacturing journals.
SELECT
    t.id AS transaction_id,
    t.transaction_code,
    t.status AS transaction_status,
    t.reference_number,
    md.id AS disbursement_id,
    md.status AS disbursement_status
FROM transactions t
LEFT JOIN monthly_disbursements md
       ON md.transaction_id = t.id
WHERE t.transaction_type = 'disbursement'
  AND md.id IS NULL
ORDER BY t.id;

-- 10) Posted disbursements must have exactly one posted disbursement journal.
-- This is the disbursement-specific equivalent of the generic transaction test.
SELECT
    md.id AS disbursement_id,
    md.transaction_id,
    md.status AS disbursement_status,
    md.total_amount,
    COUNT(je.id) AS posted_disbursement_journal_count
FROM monthly_disbursements md
LEFT JOIN journal_entries je
       ON je.reference_type = 'disbursement'
      AND je.reference_id = md.id
      AND je.status = 'posted'
WHERE md.status IN ('received', 'paid', 'completed', 'closed')
GROUP BY md.id, md.transaction_id, md.status, md.total_amount
HAVING posted_disbursement_journal_count <> 1
ORDER BY md.id;

-- 11) Voided disbursements must preserve exactly one original disbursement journal
-- as voided. A transaction_void reversal is NOT required by this lifecycle.
SELECT
    md.id AS disbursement_id,
    md.transaction_id,
    md.status AS disbursement_status,
    md.total_amount,
    COUNT(je.id) AS voided_disbursement_journal_count
FROM monthly_disbursements md
LEFT JOIN journal_entries je
       ON je.reference_type = 'disbursement'
      AND je.reference_id = md.id
      AND je.status = 'voided'
WHERE md.status = 'voided'
GROUP BY md.id, md.transaction_id, md.status, md.total_amount
HAVING voided_disbursement_journal_count <> 1
ORDER BY md.id;

-- 12) Disbursement reversal_journal_id, when populated, must point to the same
-- disbursement and use the disbursement_return reference type.
SELECT
    md.id AS disbursement_id,
    md.reversal_journal_id,
    je.id AS journal_id,
    je.reference_type,
    je.reference_id,
    je.status AS journal_status
FROM monthly_disbursements md
LEFT JOIN journal_entries je ON je.id = md.reversal_journal_id
WHERE md.reversal_journal_id IS NOT NULL
  AND (
       je.id IS NULL
       OR je.reference_type <> 'disbursement_return'
       OR je.reference_id <> md.id
  )
ORDER BY md.id;

-- 13) Orphan transaction journal references.
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

-- 14) Voucher ↔ journal linkage integrity.
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

-- 15) Duplicate voucher journals.
SELECT
    reference_id AS voucher_id,
    COUNT(*) AS journal_count
FROM journal_entries
WHERE reference_type = 'voucher'
GROUP BY reference_id
HAVING COUNT(*) > 1
ORDER BY reference_id;

-- 16) Voucher status ↔ journal status consistency for the current voucher model.
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

-- 17) Voucher reversal audit: if a voucher has been voided, inspect any
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

-- 18) Journal lines pointing to missing accounts (should return zero rows).
SELECT
    jl.id,
    jl.entry_id,
    jl.account_id
FROM journal_lines jl
LEFT JOIN accounts a ON a.id = jl.account_id
WHERE a.id IS NULL
ORDER BY jl.entry_id, jl.id;

-- 19) Voided journal history must retain its original lines.
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

-- 20) Accounting-history net balance check across ALL journal statuses.
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

-- 21) Reference types currently present in the database.
SELECT
    COALESCE(reference_type, '(NULL)') AS reference_type,
    COUNT(*) AS journal_count
FROM journal_entries
GROUP BY reference_type
ORDER BY reference_type;
