# Fina Al-Khair — Settlement Process, Deployment Plan & Checklist

**Checkpoint:** 2026-09-17  
**Status:** **STAGE 3 ORIGINAL ENGINE CLOSED AS HISTORICAL DEVELOPMENT EVIDENCE. REVISED STAGE 4 FULL-SETTLEMENT MODEL IMPLEMENTED IN REPOSITORY; LOCAL RUNTIME ACCEPTANCE PENDING.**

## 1. Locked business rules

- Fina is a protected third-party fund; account `2300` is the **permanent** Fina liability/control account.
- `2300` is never removed, recreated for each settlement, or replaced by another liability account.
- New approved Fina payments increase the same `2300` liability balance.
- When Fina requests settlement, the **entire currently owed Fina balance is settled in one settlement event**.
- Partial settlement is **not supported** and is not exposed in the production UI or settlement engine.
- After a full settlement, `2300` returns to **zero**.
- The next Fina payment starts the same cycle again by increasing `2300`.
- All settlement processing is **Financial Manager (FM) only**.
- Supervisors do not prepare, approve, transfer, reconcile, close, or otherwise execute settlements.
- There is no direct Fina-system integration/contact. Settlement is an internal Ahl El Kheir accounting event representing the actual transfer to Fina.
- Monthly settlement is normal; early full settlement is allowed.
- Settlement must not become Ahl revenue/expense, alter sponsor obligations, absorb sponsor-payment shortfalls, overwrite historical Fina collections, or erase original collection journals.
- Ahl operating treasury accounts `1100`, `1200`, and `1300` are **not** Fina settlement/remitting accounts.
- Fina-held money is represented separately through the dedicated `Fina Al-Khair Held Funds` asset/control account. This account is a custody/control representation of Fina money, not Ahl operating treasury.

## 2. Accounting meaning

### Fina collection

```text
Debit   Fina-held funds / custody asset       X
Credit  Fina Liability 2300                  X
```

The debit side represents money held separately for Fina. It is not Ahl operating cash, bank, wallet, revenue, expense, or sponsor funds.

### Full Fina settlement

```text
Debit   Fina Liability 2300                  X
Credit  Fina-held funds / custody asset      X
```

For the current cycle, `X` is the **entire outstanding Fina liability**. After the settlement journal posts successfully, `2300` is zero for that cycle while the account itself remains permanently available for future Fina collections.

## 3. Revised settlement data model

`fina_settlements` remains the historical settlement-event record and retains audit history, evidence, references, actors, timestamps, status, and settlement journal information.

The existing `fina_settlement_allocations` table is retained for historical compatibility and to record which approved collections belong to a completed full cycle. It is **not** a production partial-settlement mechanism. Every production settlement allocation is the full amount of each collection included in that cycle.

The production model is:

```text
Approved Fina collections
        ↓
Permanent liability 2300 accumulates
        ↓
Full settlement request
        ↓
One settlement for the complete current 2300 balance
        ↓
Dr 2300 / Cr Fina-held funds
        ↓
2300 = 0
        ↓
Next Fina payment → 2300 increases again
```

Production lifecycle remains:

```text
draft → approved → transferred → reconciled → closed
                     ↓
                  cancelled
```

## 4. Stage 3 — Original accounting-engine evidence — COMPLETE / RUNTIME VERIFIED / CLOSED

The original Stage 3 engine was runtime-verified on 2026-09-17. Its test evidence remains valuable, but its partial-settlement behavior is **historical development evidence only** and is no longer the production target.

Implementation commit:

`580a9b2c9991d29a8a06fe6f56ee74ac2216a506`

Retained evidence:

```text
FINA-SET-000001 — 50,000 SDG — collection #3 full — closed — journal 56
FINA-SET-000002 — 100,000 SDG — collection #4 partial — closed — journal 57

Original collection #3 → journal 54 → historical original journal retained
Original collection #4 → journal 55 → historical original journal retained
```

These settlement records and their original journals are retained. They are **historical development/test fixtures**, not current business settlements.

## 5. Revised Stage 4 — Full-settlement engine/UI — IMPLEMENTED / LOCAL RUNTIME PENDING

Repository implementation now includes:

- `database/migrations/2026-09-17_fina_full_settlement_model.sql`
  - makes the historical remitting account nullable for compatibility;
  - marks the two retained Stage 3 settlement records as test fixtures;
  - creates/ensures the dedicated `Fina Al-Khair Held Funds` asset/control account;
  - creates compensating posted restore journals for the two historical Stage 3 settlement effects without editing/deleting their original journals;
  - reclassifies the existing approved Fina collection asset side into the dedicated Fina-held-funds account while preserving the original collection journals.
- `modules/accounting/fina_lib.php`
  - new Fina collections post `Dr Fina-held funds / Cr 2300`;
  - no new Fina collection posts to Ahl treasury accounts `1100/1200/1300`.
- `modules/accounting/fina_settlement_lib.php`
  - production settlement amount must equal the entire current outstanding balance;
  - no partial settlement amount is accepted;
  - no Ahl remitting account is accepted or required;
  - full settlement posts `Dr 2300 / Cr Fina-held funds`;
  - a new collection arriving after a draft/approval causes the cycle to fail validation until the full current balance is represented.
- `modules/accounting/fina_settlements.php`
  - FM-only;
  - no Ahl treasury-account selector;
  - no partial amount control;
  - no allocation grid for user selection;
  - clearly shows the current full balance and the permanent 2300 cycle.

The implementation is committed to `main`, but **local runtime acceptance has not yet been performed**.

## 6. Stage 5 — Dashboard/report updates

Next after runtime acceptance:

- show current Fina liability separately from Ahl treasury balances;
- do not include Fina-held funds in Ahl operating treasury totals (`1100`, `1200`, `1300`);
- show `2300` as the permanent Fina liability/control account;
- show current outstanding liability before settlement;
- show `2300 = 0` after a successful full settlement;
- keep historical/test settlement records separately identifiable;
- preserve Supervisor scope.

## 7. Stage 6 — Management notifications

After completed settlement, notify GM and VGM internally. Do not notify Supervisors about settlement completion. Notification failure must not undo completed accounting settlement.

## 8. Stage 7 — Reconciliation and audit controls

Reconcile:

- approved Fina collections;
- permanent `2300` liability balance;
- dedicated Fina-held-funds balance/control account;
- full settlement journal;
- transfer evidence/reference;
- settlement lifecycle status;
- zero post-settlement `2300` balance;
- historical settlement searchability.

The repeating-cycle proof is:

```text
New Fina payment → 2300 increases
Full settlement → 2300 returns to 0
Next Fina payment → 2300 increases again
```

## 9. Stage 8 — Security / separation of duties

Verify that FM can complete the workflow and that Supervisor, GM, and VGM cannot acquire settlement execution authority through direct URLs, notifications, or UI navigation. Settlement actions must retain actor/time audit evidence.

## 10. Stage 9 — Revised runtime acceptance — PENDING

Do not repeat the obsolete partial-settlement acceptance suite. Perform only targeted acceptance for the finalized model:

1. Apply the new full-settlement migration safely.
2. Verify the dedicated Fina-held-funds account exists and is an active asset/control account.
3. Verify current production Fina liability is **250,000 SDG** despite the retained Stage 3 test settlement records.
4. Verify account `2300` represents the current 250,000 SDG liability.
5. Verify no Ahl treasury account is offered/required by the settlement UI.
6. Create one full settlement for exactly 250,000 SDG.
7. Verify the journal is balanced `Dr 2300 / Cr Fina-held funds`.
8. Verify `2300` becomes zero after the full settlement.
9. Verify `2300` remains permanently present in the chart.
10. Verify the two historical Stage 3 settlement records remain present and identifiable as test evidence.
11. Create/approve a new Fina payment after settlement and verify the same 2300 account increases again.
12. Verify evidence/reference/lifecycle controls and FM-only authorization.
13. Verify original Fina collection journals remain unchanged.

Runtime verification is **not complete** until the local application/database produces the actual results.

## 11. Stage 10 — Production readiness

Production deployment, management policy confirmation, evidence retention, backup/recovery review, final audit evidence, master audit/session index updates, and final feature closure remain pending until the revised code and runtime acceptance are complete.

## 12. Current continuation point

**Stage 0 — COMPLETE.**  
**Stage 1 — COMPLETE / live schema inspected.**  
**Stage 2 — COMPLETE / original migration applied successfully to live DB on 2026-09-17.**  
**Stage 3 — COMPLETE / original runtime evidence VERIFIED and CLOSED; partial behavior is historical only.**  
**Stage 4 — IMPLEMENTED IN REPOSITORY / LOCAL RUNTIME ACCEPTANCE PENDING: full current-balance settlement, permanent `2300`, separate Fina-held funds, no partial settlement.**

Next step: pull the repository safely, inspect local status before any merge, apply the new migration, then run the targeted runtime acceptance above. Preserve intentional local uncommitted receipt files and never use destructive reset/clean/stash operations.