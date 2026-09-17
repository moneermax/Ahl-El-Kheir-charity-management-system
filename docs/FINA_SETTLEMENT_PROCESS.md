# Fina Al-Khair — Settlement Process, Deployment Plan & Checklist

**Checkpoint:** 2026-09-17  
**Status:** **STAGE 3 COMPLETE / RUNTIME VERIFIED / CLOSED. STAGE 4 ACTIVE — FM-ONLY SETTLEMENT UI.**

## 1. Locked business rules

- Fina is a protected third-party fund; account `2300` is the dedicated liability/control account.
- All Fina settlement processing is **Financial Manager (FM) only**.
- Supervisors do not prepare, approve, transfer, reconcile, close, or otherwise execute settlements.
- There is no direct Fina-system integration/contact. Settlement is an internal Ahl El Kheir accounting event representing the actual transfer to Fina.
- Monthly settlement is normal; early settlement is allowed.
- Full and partial settlement are supported.
- Actual settlement date is the date money is actually transferred.
- Settlement must not become Ahl revenue/expense, alter sponsor obligations, absorb sponsor-payment shortfalls, overwrite historical Fina collections, or erase collection journals.

## 2. Accounting meaning

Fina collection:

```text
Debit   Cash / Bank / Wallet       X
Credit  Fina Liability 2300        X
```

Actual settlement:

```text
Debit   Fina Liability 2300        X
Credit  Cash / Bank / Wallet       X
```

The settlement engine permits active treasury asset accounts `1100`, `1200`, and `1300`.

## 3. Settlement data model

`fina_settlements` is the settlement event/batch. It records the settlement reference, actual settlement date, amount/currency, payment method, remitting account, transfer reference, evidence path, lifecycle status, settlement journal, reconciliation/cancellation notes, and actor/timestamp audit fields.

`fina_settlement_allocations` links settlement amounts to individual Fina collections. This is the required model for full and partial settlement and prevents rewriting the original collection amount.

Lifecycle:

```text
draft → approved → transferred → reconciled → closed
                     ↓
                  cancelled
```

## 4. Stage 3 — Accounting engine — COMPLETE / RUNTIME VERIFIED / CLOSED

Implementation:

`modules/accounting/fina_settlement_lib.php`

Implementation commit:

`580a9b2c9991d29a8a06fe6f56ee74ac2216a506`

### Runtime acceptance — 2026-09-17

Controlled verification was completed against the live local application using the existing approved Fina collections only.

Before testing:

- Eligible approved/unsettled Fina liability: **250,000 SDG**.

Verified:

- over-allocation was rejected;
- missing transfer reference was rejected;
- collection `#3` was fully settled for **50,000 SDG**;
- collection `#4` was partially settled for **100,000 SDG**;
- both settlement lifecycles reached `closed`;
- settlement journals were created and balanced;
- original collections remained unchanged and approved;
- original collection journals remained posted;
- remaining approved/unsettled Fina liability: **100,000 SDG**.

Retained development/test evidence:

```text
FINA-SET-000001 — 50,000 SDG — collection #3 full — closed — journal 56
FINA-SET-000002 — 100,000 SDG — collection #4 partial — closed — journal 57

Original collection #3 → journal 54 → unchanged / posted
Original collection #4 → journal 55 → unchanged / posted
Remaining approved/unsettled liability → 100,000 SDG
```

These valid development/test settlement records are retained as audit evidence and must not be deleted merely to clean the development database.

### Stage 3 controls verified

- FM-only server-side authorization;
- settlement/allocation table readiness;
- date and system-currency validation;
- active treasury-account validation;
- draft creation and collection-level allocation;
- full and partial settlement;
- over-allocation/duplicate-allocation prevention;
- rejection of pending/returned/non-approved collections;
- posted original Fina journal requirement;
- approval and actual-transfer recording;
- required transfer reference;
- separate `Dr 2300 / Cr remitting asset` settlement journal;
- balanced-journal verification;
- original collection/journal preservation;
- reconciliation and closure;
- cancellation of draft/approved settlements with a mandatory reason;
- outstanding-liability calculation.

**Stage 3 result: PASS / COMPLETE / CLOSED.** Do not repeat Stage 3 acceptance tests unless genuine regression evidence appears.

## 5. Stage 4 — FM-only settlement UI — ACTIVE

The production UI is now the active implementation stage and will be built on the tested Stage 3 engine without duplicating its accounting rules.

Required UI scope:

- FM settlement list/entry page;
- live approved/unsettled liability display;
- eligible approved collections with remaining allocatable amounts;
- full or partial allocation controls;
- settlement date;
- payment method;
- remitting treasury account;
- transfer reference;
- supporting evidence;
- settlement detail/status view;
- approve, transfer, reconcile, close, and cancel actions according to lifecycle state;
- server-side FM authorization on every action;
- direct-URL blocking for Supervisors and other non-FM roles;
- clear distinction between outstanding liability and historical settled amounts.

No settlement action is granted by a dashboard link or notification; server-side authorization remains the security boundary.

## 6. Stage 5 — Dashboard/report updates

- Change FM primary live Fina balance to outstanding/unsettled liability.
- Keep cumulative settled amount visible separately.
- Keep pending and returned counts separate.
- Update Fina reporting to distinguish collections, outstanding liability, settlements, and remaining balance.
- Preserve Supervisor scope.

## 7. Stage 6 — Management notifications

After completed settlement, notify GM and VGM internally. Do not notify Supervisors about settlement completion. Notification failure must not undo completed accounting settlement.

## 8. Stage 7 — Reconciliation and audit controls

Reconcile approved/unsettled collections, settlement allocations, settlement journals, treasury movement, and closing account `2300`. Verify historical settlement searchability, evidence/reference retention, duplicate blocking, and rejection of pending/returned collections.

## 9. Stage 8 — Security / separation of duties

Verify that FM can complete the workflow and that Supervisor, GM, and VGM cannot acquire settlement execution authority through direct URLs, notifications, or UI navigation. Settlement actions must retain actor/time audit evidence.

## 10. Stage 9 — Runtime acceptance after UI

UI acceptance will cover full/partial settlement, early settlement, over-settlement, duplicate settlement, pending/returned rejection, `2300` reduction, remitting-account reduction, balanced journal, actual transfer date, evidence/reference, dashboard balance, historical settled amount, reports, and management notifications.

## 11. Stage 10 — Production readiness

Production deployment, management policy confirmation, evidence retention, backup/recovery review, final audit evidence, master audit/session index updates, and final feature closure remain pending until code and runtime acceptance are complete.

## 12. Current continuation point

**Stage 0 — COMPLETE.**  
**Stage 1 — COMPLETE / live schema inspected.**  
**Stage 2 — COMPLETE / migration applied successfully to live DB on 2026-09-17.**  
**Stage 3 — COMPLETE / runtime verified and CLOSED on 2026-09-17.**  
**Stage 4 — ACTIVE: FM-only settlement UI.**

Next implementation step: inspect the existing FM accounting UI patterns and implement the production settlement screens directly on the tested Stage 3 engine. Do not recreate the migration, rerun closed Stage 3 tests, or modify the existing Fina collection records/journals except for a genuine regression fix.