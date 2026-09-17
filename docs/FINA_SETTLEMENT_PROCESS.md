# Fina Al-Khair — Settlement Process, Deployment Plan & Checklist

**Checkpoint:** 2026-09-17  
**Status:** **STAGE 3 ENGINE CLOSED AS HISTORICAL DEVELOPMENT EVIDENCE. STAGE 4 MODEL REVISED — FULL FINA SETTLEMENT ONLY / FM-ONLY. IMPLEMENTATION ACTIVE.**

## 1. Locked business rules

- Fina is a protected third-party fund; account `2300` is the **permanent** Fina liability/control account.
- `2300` is never removed, recreated for each settlement, or replaced by another liability account.
- New approved Fina payments increase the same `2300` liability balance.
- When Fina requests settlement, the **entire currently owed Fina balance is settled in one settlement event**.
- Partial settlement is **not supported** and must not be exposed in the production UI or settlement API/library.
- After a full settlement, `2300` returns to **zero**.
- The next Fina payment starts the same cycle again by increasing `2300`.
- All settlement processing is **Financial Manager (FM) only**.
- Supervisors do not prepare, approve, transfer, reconcile, close, or otherwise execute settlements.
- There is no direct Fina-system integration/contact. Settlement is an internal Ahl El Kheir accounting event representing the actual transfer to Fina.
- Monthly settlement is normal; early full settlement is allowed.
- Settlement must not become Ahl revenue/expense, alter sponsor obligations, absorb sponsor-payment shortfalls, overwrite historical Fina collections, or erase original collection journals.
- Ahl operating treasury accounts `1100`, `1200`, and `1300` must **not** be used as the settlement/remitting account for Fina money.
- Fina-held money must be represented separately from Ahl operating treasury funds. The repository implementation must use a dedicated Fina-held-funds accounting control/asset account only after the actual chart of accounts and schema have been inspected and the correct account convention has been confirmed.

## 2. Accounting meaning

The permanent Fina liability is always account `2300`.

### Fina collection

```text
Debit   Fina-held funds / separate Fina custody account    X
Credit  Fina Liability 2300                               X
```

The debit side represents money held separately for Fina. It must not be Ahl operating cash, bank, wallet, revenue, expense, or sponsor funds.

### Full Fina settlement

```text
Debit   Fina Liability 2300                               X
Credit  Fina-held funds / separate Fina custody account   X
```

For the current cycle, `X` is the **entire outstanding Fina liability**. After the settlement journal posts successfully, account `2300` must be zero for that cycle while the account itself remains permanently available for future Fina collections.

## 3. Settlement data model — revised target

`fina_settlements` remains the historical settlement-event record and must retain audit history, evidence, references, actors, timestamps, status, and settlement journal information.

Production settlement logic must **not** use collection-level allocation as a partial-settlement mechanism. The current `fina_settlement_allocations` table and the two Stage 3 runtime settlement records are historical development evidence and must not reduce the live/current Fina liability for the next production settlement cycle.

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

A production settlement is one complete settlement of the current outstanding Fina liability. There is no partial-allocation workflow.

## 4. Stage 3 — Historical accounting-engine evidence — COMPLETE / RUNTIME VERIFIED / CLOSED

The original Stage 3 engine was runtime-verified on 2026-09-17. Its test evidence remains valuable, but its **partial-settlement behavior is no longer the target business model** and must not be carried forward into production behavior.

Implementation commit:

`580a9b2c9991d29a8a06fe6f56ee74ac2216a506`

### Retained development/test evidence

```text
FINA-SET-000001 — 50,000 SDG — collection #3 full — closed — journal 56
FINA-SET-000002 — 100,000 SDG — collection #4 partial — closed — journal 57

Original collection #3 → journal 54 → unchanged / posted
Original collection #4 → journal 55 → unchanged / posted
```

These records and their journals are retained. They are **historical development/test fixtures**, not current business settlements, and must not consume or reduce the current production Fina liability.

The original controlled test proved important accounting protections including FM authorization, posted-original-journal requirements, balanced settlement journals, original-record preservation, transfer-reference enforcement, lifecycle controls, and rollback behavior. Do not rerun the closed Stage 3 acceptance suite merely because the production settlement model has been revised; only targeted regression/runtime acceptance for the new full-settlement model is required.

## 5. Stage 4 — Production FM-only settlement UI and engine revision — ACTIVE

Stage 4 is now being revised to implement the finalized full-settlement model.

### Required changes

1. Remove production partial-settlement/allocation behavior from the settlement workflow.
2. Remove the settlement-form concept of selecting an Ahl treasury/remitting account.
3. Remove the current `1100` / `1200` / `1300` settlement-account selector from the Fina settlement UI.
4. Determine the correct dedicated Fina-held-funds accounting account from the actual chart of accounts/schema before making any accounting migration.
5. Make the settlement amount equal to the **entire current Fina liability**, not an arbitrary user-entered partial amount.
6. Ensure the full settlement posts `Dr 2300 / Cr Fina-held funds`.
7. Ensure the resulting posted ledger balance of `2300` is zero after a successful full settlement.
8. Preserve `2300` permanently for the next Fina payment cycle.
9. Preserve all historical Fina collection journals and historical Stage 3 test settlement records.
10. Ensure the historical Stage 3 settlement records do not affect the live/current settlement balance.
11. Keep FM-only server-side authorization on every settlement action and direct URL.
12. Preserve transfer reference, evidence, actor, timestamp, reconciliation, closure, and cancellation auditability.
13. Keep the settlement history searchable and clearly distinguish historical/test evidence from the current business cycle.

### UI target

The FM settlement page should show:

- current outstanding Fina liability;
- the fact that settlement is **full/current-balance only**;
- the settlement date;
- the complete settlement amount, derived from the current liability rather than entered as a partial amount;
- transfer reference;
- supporting evidence;
- settlement detail/status/history;
- approve, transfer, reconcile, close, and cancel actions according to lifecycle state;
- clear confirmation that Ahl treasury accounts are not being selected or credited by the Fina settlement.

No allocation grid or partial amount control should remain in the production settlement workflow.

## 6. Stage 5 — Dashboard/report updates

- Show the current Fina liability separately from Ahl treasury balances.
- Do not include Fina-held funds in Ahl operating treasury totals (`1100`, `1200`, `1300`).
- Show `2300` as the permanent Fina liability/control account.
- Show current outstanding Fina liability before settlement.
- After a successful full settlement, show `2300 = 0` for the completed cycle.
- Keep historical/cumulative settlement records separately visible.
- Distinguish development/test settlement fixtures from real/current business settlements where the UI exposes historical records.
- Preserve Supervisor scope.

## 7. Stage 6 — Management notifications

After completed settlement, notify GM and VGM internally. Do not notify Supervisors about settlement completion. Notification failure must not undo a completed accounting settlement.

## 8. Stage 7 — Reconciliation and audit controls

Reconcile:

- approved Fina collections;
- the permanent `2300` liability balance;
- the dedicated Fina-held-funds balance/control account;
- the full settlement journal;
- transfer evidence/reference;
- settlement lifecycle status;
- the zero post-settlement `2300` balance;
- historical settlement searchability.

The reconciliation must prove the repeating cycle:

```text
New Fina payment → 2300 increases
Full settlement → 2300 returns to 0
Next Fina payment → 2300 increases again
```

## 9. Stage 8 — Security / separation of duties

Verify that FM can complete the workflow and that Supervisor, GM, and VGM cannot acquire settlement execution authority through direct URLs, notifications, or UI navigation. Settlement actions must retain actor/time audit evidence.

## 10. Stage 9 — Runtime acceptance after implementation

Runtime acceptance will be targeted to the revised model and will not repeat obsolete partial-settlement acceptance.

Required acceptance evidence:

1. Current approved Fina liability is correctly derived from the real/current cycle.
2. Historical Stage 3 test settlements do not reduce that current liability.
3. No partial settlement amount can be submitted.
4. No Ahl treasury account can be selected as a Fina settlement account.
5. Full settlement amount equals the complete current Fina liability.
6. Settlement posts a balanced `Dr 2300 / Cr Fina-held funds` journal.
7. `2300` becomes zero after successful settlement.
8. `2300` remains in the chart of accounts and is reusable.
9. A subsequent Fina payment increases the same `2300` account again.
10. Historical Fina collections and original journals remain unchanged.
11. Evidence/reference/lifecycle controls work as intended.
12. FM-only authorization remains enforced.

Do not claim this runtime acceptance is complete until the user runs the local application and provides the actual results.

## 11. Stage 10 — Production readiness

Production deployment, management policy confirmation, evidence retention, backup/recovery review, final audit evidence, master audit/session index updates, and final feature closure remain pending until the revised code and runtime acceptance are complete.

## 12. Current continuation point

**Stage 0 — COMPLETE.**  
**Stage 1 — COMPLETE / live schema inspected.**  
**Stage 2 — COMPLETE / migration applied successfully to live DB on 2026-09-17.**  
**Stage 3 — COMPLETE / historical runtime evidence VERIFIED and CLOSED; its partial-settlement behavior is no longer the production target.**  
**Stage 4 — ACTIVE: revise the FM-only settlement engine/UI to the finalized full-settlement, permanent-2300, separate-Fina-funds model.**

Next implementation step: inspect the complete current settlement library/UI and the actual chart-of-accounts/schema for an existing dedicated Fina-held-funds account or established custody-account pattern. Then implement the narrowest safe migration/code changes. Preserve all existing historical/test records and local uncommitted receipt files. Do not run destructive cleanup and do not claim runtime verification before local testing.