# Fina Al-Khair — Settlement Process, Deployment Plan & Checklist

**Checkpoint:** 2026-09-17  
**Status:** STAGE 2 COMPLETE — schema migration prepared; live migration/workflow implementation not yet started.

## 1. Locked business rule

**All Fina settlement processing is performed by the Financial Manager (FM) only.**

Supervisors may submit Fina collections and view only their own scoped collection information. They do not prepare, authorize, execute, record, or close Fina settlements.

The system has no direct integration/contact with a Fina system. Settlement is recorded in Ahl El Kheir as an internal Accounting event representing the actual transfer of money to Fina.

Fina is a protected third-party fund. Account `2300` is the dedicated Fina liability/control account.

## 2. Agreed settlement policy

### 2.1 Normal settlement

The normal operating cycle is **monthly**.

At the end of the defined monthly cutoff, the FM reconciles all approved but unsettled Fina collections and settles the amount due to Fina.

### 2.2 Early settlement

The FM may initiate a settlement **at any time** when Fina requests the money or management/operational circumstances require it. The system must not force the FM to wait for month-end.

### 2.3 Partial settlement

Settlement amount is flexible. The FM may settle the full outstanding balance or a smaller amount.

Example:

```text
Outstanding liability: 250,000 SDG
Settlement:            150,000 SDG
Remaining liability:   100,000 SDG
```

A partial settlement must not mark the full underlying collection as settled unless its full amount has actually been remitted.

## 3. Accounting meaning

Approval of a Fina collection establishes the payable third-party liability:

```text
Debit   Cash / Bank / Wallet       X
Credit  Fina Liability 2300        X
```

Actual transfer/settlement is a separate accounting event:

```text
Debit   Fina Liability 2300       X
Credit  Cash / Bank / Wallet      X
```

Settlement is **not** Ahl El Kheir revenue and is **not** an expense. It is repayment of an existing third-party liability.

The actual remitting asset account must be selected from the real chart of accounts; no account is assumed by this document.

## 4. Dashboard balance semantics after settlement

The current approved Fina amount must not simply disappear from history.

The live FM balance should distinguish:

1. **Pending** — submitted but not approved.
2. **Approved / Unsettled** — approved and posted to liability `2300`, but not yet remitted.
3. **Settled to Fina** — amount actually transferred to Fina; retained as historical cumulative settlement information.
4. **Returned** — returned/rejected collection; not part of the outstanding Fina liability.

Example:

```text
Before settlement:
2300 outstanding = 250,000 SDG

After full settlement:
2300 outstanding = 0 SDG
Historical settled amount = 250,000 SDG
```

The system must never erase the collection or rewrite its original amount merely because it has been settled.

### Supervisor dashboard

Supervisor Fina amounts remain **Supervisor-scoped**. The dashboard must not expose organization-wide Fina liability merely because the Supervisor is viewing the dashboard.

Future Supervisor figures may distinguish that Supervisor's own:

- approved collections;
- approved/unsettled amount;
- historical amount settled to Fina.

Settlement controls remain unavailable to Supervisors.

## 5. Agreed settlement workflow

```text
Supervisor collections
        ↓
FM review
        ↓
Approved Fina collections
        ↓
Fina liability 2300
        ↓
FM settlement preparation/reconciliation
        ↓
FM settlement authorization/release
        ↓
Actual transfer to Fina
        ↓
FM records actual settlement date + transfer reference/evidence
        ↓
Settlement journal
        ↓
2300 liability reduced
        ↓
Settlement closed/reconciled
```

The actual settlement date is the date the money is actually transferred to Fina. It must not be replaced by the collection date or approval date.

## 6. Settlement record/batch requirements

The implementation uses a dedicated settlement record/batch plus collection-level allocation. It must not zero or overwrite amounts in `fina_collections`.

Stage 2 design is now fixed as:

### `fina_settlements`

One row represents one FM settlement event/batch. The schema preserves:

- settlement identifier/reference (`settlement_code`);
- actual settlement date (`settlement_date`);
- amount and currency;
- payment method;
- actual remitting account (`remitting_account_id`);
- transfer/reference number;
- supporting evidence path;
- settlement status;
- settlement journal reference;
- reconciliation/cancellation notes;
- FM actor/timestamps for creation, approval, transfer, reconciliation, and cancellation.

### `fina_settlement_allocations`

One row represents an amount of a specific Fina collection allocated to a settlement.

This explicitly supports partial settlement without modifying the original collection. A collection may therefore be allocated across multiple settlement batches over time, subject to runtime controls preventing allocation above its approved/unsettled amount.

The migration also enforces one allocation row per settlement/collection pair and preserves foreign-key linkage to both the settlement and the original Fina collection.

### Accounting linkage

`fina_settlements.settlement_journal_id` will point to the separate settlement journal. The original `fina_collections.accounting_journal_id` remains unchanged.

## 7. Settlement lifecycle

For the implementation, the dedicated settlement record supports:

```text
draft → approved → transferred → reconciled → closed
                     ↓
                  cancelled
```

All settlement actions remain FM-only. The `approved` state is an internal settlement lifecycle state and must not grant settlement execution to another role.

## 8. Notifications — locked rule

Settlement notifications are **not** sent to Supervisors.

The FM performs the complete settlement process.

After the FM completes the actual settlement, the system should notify:

- **General Manager (GM)**
- **Vice General Manager (VGM)**

The notification should contain the settlement reference, actual transfer date, amount, and relevant accounting/reference information without implying that the recipients performed the settlement.

There is **no direct Fina-system notification/integration**. The Ahl El Kheir notification is internal management oversight.

## 9. Reports and reconciliation

The Fina report must ultimately distinguish:

- submitted/pending collections;
- approved collections;
- returned collections;
- outstanding liability;
- settlements made to Fina;
- remaining balance after settlement.

FM must be able to reconcile:

`Previous outstanding liability + newly approved liability - settlement = new outstanding liability`

and reconcile the resulting outstanding balance to posted account `2300`.

A settlement must never:

- create Ahl El Kheir Fina revenue;
- become an expense;
- reduce sponsor obligations;
- absorb sponsor-payment shortfalls;
- alter historical collection amounts;
- delete approved collection records;
- settle the same amount twice;
- create an unbalanced journal;
- settle a returned collection as though it were approved.

## 10. Deployment plan and completion checklist

### Stage 0 — Business rule freeze

- [x] Monthly settlement is the normal cycle.
- [x] Early settlement is allowed at any time when Fina requests the money or management requires it.
- [x] Full and partial settlement are supported.
- [x] FM is the **only** role that performs the entire settlement process.
- [x] Supervisors have no settlement action.
- [x] Settlement is an internal Ahl El Kheir accounting event; there is no direct Fina-system integration.
- [x] Actual settlement date means the date money is actually transferred to Fina.
- [x] Settlement notifications go to GM and VGM, not Supervisors.

### Stage 1 — Existing-system/schema inspection — COMPLETE

- [x] Inspect actual `fina_collections` schema and current status semantics.
- [x] Inspect actual account `2300` and posted-journal structure.
- [x] Inspect all existing Fina collection journal creation paths.
- [x] Inspect available cash/bank/wallet accounts and payment-method semantics for outgoing settlement.
- [x] Inspect existing transaction/reference-number/evidence patterns relevant to accounting implementation.
- [x] Inspect existing role/authorization helpers for FM, GM, and VGM at the repository level.
- [x] Inspect existing notification infrastructure relevant to management notifications.
- [x] Confirm that no existing Fina settlement/allocation structure exists.

**Live database evidence:** `fina_collections` currently has only `pending / approved / returned`; account `2300` is an active liability; treasury accounts are `1100`, `1200`, and `1300`; existing Fina collection journals use the expected asset → `2300` pattern; existing Fina tables are `fina_sources` and `fina_collections` only.

### Stage 2 — Data model design — COMPLETE

- [x] Design dedicated settlement batch/record using the actual current schema conventions.
- [x] Design collection-to-settlement allocation, including partial allocation.
- [x] Preserve historical `fina_collections` amounts/statuses.
- [x] Define settlement status lifecycle.
- [x] Define evidence and transfer-reference fields.
- [x] Define actor/timestamp audit fields.
- [x] Define reconciliation/cancellation fields.
- [x] Produce migration after actual schema inspection.
- [x] Keep original collection journals separate from settlement journals.

Migration created:

`database/migrations/2026-09-17_fina_settlement_schema.sql`

The migration creates only the new settlement/allocation structures. It does **not** modify existing Fina collections or journals.

**Important:** the migration has been committed to the repository but has **not yet been applied to the live database**. Live schema execution will occur as part of the controlled implementation step.

### Stage 3 — Accounting engine

- [ ] Implement settlement journal creation as a separate accounting event.
- [ ] Debit liability `2300` by the settled amount.
- [ ] Credit the actual remitting asset account.
- [ ] Enforce balanced journal creation.
- [ ] Prevent settlement above the eligible outstanding balance.
- [ ] Prevent duplicate settlement of the same allocated amount.
- [ ] Support partial settlement.
- [ ] Preserve the original collection journal(s).
- [ ] Ensure settlement rollback/cancellation cannot silently corrupt account `2300`.

### Stage 4 — FM-only settlement UI

- [ ] Add FM settlement entry/list page.
- [ ] Show current outstanding liability from posted accounting/eligible collections.
- [ ] Allow FM to choose full or partial settlement.
- [ ] Capture actual transfer date.
- [ ] Capture payment method/remitting account.
- [ ] Capture transfer reference.
- [ ] Capture supporting evidence.
- [ ] Show eligible collections and allocation.
- [ ] Make settlement controls server-side FM-only.
- [ ] Ensure Supervisors cannot access settlement actions by direct URL.
- [ ] Ensure GM/VGM notification does not grant settlement authority.

### Stage 5 — Dashboard/report updates

- [ ] Change FM primary live Fina balance to **outstanding/unsettled liability**.
- [ ] Keep cumulative settled amount visible separately.
- [ ] Keep pending and returned counts separate.
- [ ] Update Fina report to distinguish collections, outstanding liability, and settlements.
- [ ] Keep Supervisor totals scoped to the individual Supervisor.
- [ ] Never display a settled amount as still outstanding.

### Stage 6 — Management notifications

- [ ] On completed settlement, notify GM.
- [ ] On completed settlement, notify VGM.
- [ ] Do not notify Supervisors about settlement completion.
- [ ] Notification must identify amount, settlement reference, and actual transfer date.
- [ ] Notification must use the existing CSRF/read/unread/history model where applicable.
- [ ] Notification failure must not undo a successfully completed accounting settlement.

### Stage 7 — Reconciliation and audit controls

- [ ] Reconcile eligible approved/unsettled collections to settlement amount.
- [ ] Reconcile settlement journal to actual remitting account movement.
- [ ] Reconcile closing Fina liability to account `2300`.
- [ ] Verify full settlement produces zero outstanding `2300` for the settled portion.
- [ ] Verify partial settlement leaves the correct remaining liability.
- [ ] Verify historical settlement records remain searchable.
- [ ] Verify evidence/reference remains attached.
- [ ] Verify duplicate settlement is blocked.
- [ ] Verify returned/pending collections cannot be settled.

### Stage 8 — Security and separation-of-duties tests

- [ ] FM can perform the complete settlement workflow.
- [ ] Supervisor cannot create/approve/release/close a settlement.
- [ ] GM cannot accidentally acquire settlement execution rights merely by receiving notification.
- [ ] VGM cannot accidentally acquire settlement execution rights merely by receiving notification.
- [ ] Direct URL/API-style attempts against settlement actions are server-side blocked for non-FM users.
- [ ] Settlement actions retain actor/time audit evidence.

### Stage 9 — Runtime acceptance tests

- [ ] Full monthly settlement.
- [ ] Early settlement before month-end.
- [ ] Partial settlement.
- [ ] Attempted over-settlement.
- [ ] Attempted duplicate settlement.
- [ ] Attempted settlement of pending collection.
- [ ] Attempted settlement of returned collection.
- [ ] Correct `2300` reduction.
- [ ] Correct remitting asset-account reduction.
- [ ] Balanced settlement journal.
- [ ] Actual transfer date recorded correctly.
- [ ] GM notification received.
- [ ] VGM notification received.
- [ ] Supervisor receives no settlement notification.
- [ ] Dashboard outstanding balance changes correctly after settlement.
- [ ] Historical settled amount remains visible.
- [ ] Report reconciles with the ledger and settlement records.

### Stage 10 — Production readiness / closure

- [ ] Migration reviewed and executed in the correct deployment process.
- [ ] Production settlement policy/configuration confirmed by management.
- [ ] Transfer evidence retention confirmed.
- [ ] Backup/recovery considerations reviewed.
- [ ] Final audit evidence recorded.
- [ ] Master audit updated.
- [ ] Session index updated.
- [ ] Continuation prompt updated if any permanent rule changed.
- [ ] Settlement feature marked COMPLETE only after code + runtime verification.

## 11. Important implementation rule

Do **not** implement settlement by simply setting account `2300` to zero in isolation.

The accounting result of a full settlement will naturally make the outstanding `2300` balance zero **through a posted settlement journal**. Historical collection and settlement records remain intact.

Likewise, do not add a simple `settled = yes/no` flag to `fina_collections` without considering partial settlement and allocation. The settlement event and its allocation must remain auditable.

## 12. Current status / next continuation point

**Stage 0 — COMPLETE.**  
**Stage 1 — COMPLETE / live schema inspected.**  
**Stage 2 — COMPLETE / settlement and allocation schema committed.**  
**Stage 3 — NEXT: accounting engine.**

The live database has not yet received the new settlement tables. The next implementation step is to apply the new migration through the controlled deployment process, verify the resulting live schema, then implement the FM-only settlement accounting engine.

No existing Fina collection record or original Fina collection journal is to be modified as part of that migration.
