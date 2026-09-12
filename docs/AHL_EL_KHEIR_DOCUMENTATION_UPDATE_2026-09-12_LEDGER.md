# Ahl El Kheir — Accounting Audit Ledger Checkpoint

**Date:** 2026-09-12  
**Area:** Account Ledger Integrity  
**Status:** Static repository audit — PASS; no code change required

## 1. Ledger authorization

`modules/accounting/account_ledger.php` restricts ledger access to:

- `admin`
- `financial_manager`

The account list exposes the ledger link only to the same roles. This matches the established financial/reporting authorization boundary.

## 2. Ledger source relationships

The ledger reads the authoritative accounting chain:

```text
accounts.id
   ↑
journal_lines.account_id
   ↑
journal_entries.id = journal_lines.entry_id
```

The selected account is loaded from `accounts`, and ledger rows join `journal_lines` to `journal_entries` and optionally to the creating user.

## 3. Posted/voided handling

The ledger includes only:

```text
journal_entries.status = 'posted'
```

Voided original journals are therefore excluded from the ledger balance, while independently posted reversal journals remain visible. This is consistent with the existing Trial Balance design.

## 4. Date filtering and balances

The ledger validates ISO date input, swaps an inverted date range, calculates opening balance from posted entries before the selected start date, loads posted entries within the requested period, and calculates:

```text
opening balance = Σ(debit - credit) before period
period debit    = Σ(debit) in period
period credit   = Σ(credit) in period
closing balance = opening + debit - credit
```

The displayed running balance applies the same debit-minus-credit calculation row by row.

This matches the existing Trial Balance net-balance convention.

## 5. Mutation surface

The ledger page contains no POST mutation path and no INSERT/UPDATE/DELETE operation against journal history. It is therefore read-only from the accounting-history perspective.

## 6. Result

**Account Ledger Integrity: STATIC PASS.**

No new accounting defect was identified and no protected accounting fixture was modified or recreated.

## 7. Minor architectural observation — not a defect

The ledger calls `ak_ensure_tables()` and `ak_seed_accounts()` on page load, as do other accounting read/report pages. `ak_seed_accounts()` only writes when the `accounts` table is empty.

This is an existing initialization pattern, not an observed ledger-integrity failure. It should be considered separately if the project later adopts a strict rule that all read-only pages must have zero initialization side effects.

## 8. Next target

Continue with remaining direct journal mutation/cross-module relationship review, then targeted runtime verification of the three newly protected automated reference types when the local test path is available:

- `payroll`
- `disbursement_void`
- `item_return`

Do not repeat completed audit fixtures.
