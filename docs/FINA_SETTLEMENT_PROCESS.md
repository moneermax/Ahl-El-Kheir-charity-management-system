# Fina Al-Khair — Settlement Process, Deployment Plan & Checklist

**Checkpoint:** 2026-09-17  
**Status:** **STAGE 3 IMPLEMENTED — accounting engine committed; runtime verification pending.**

## 1. Locked business rule

**All Fina settlement processing is performed by the Financial Manager (FM) only.**

Supervisors may submit Fina collections and view only their own scoped collection information. They do not prepare, authorize, execute, record, or close Fina settlements.

The system has no direct integration/contact with a Fina system. Settlement is recorded in Ahl El Kheir as an internal Accounting event representing the actual transfer of money to Fina.

Fina is a protected third-party fund. Account `2300` is the dedicated Fina liability/control account.

## 2. Agreed settlement policy

### 2.1 Normal settlement

The normal operating cycle is **monthly**. At the monthly cutoff, FM reconciles approved but unsettled Fina collections and settles the amount due.

### 2.2 Early settlement

FM may initiate settlement **at any time** when Fina requests the money or circumstances require it. The system does not force month-end settlement.

### 2.3 Partial settlement

FM may settle the full outstanding balance or a smaller amount. Partial settlement must not mark the full underlying collection as settled unless its full amount has actually been remitted.

Example:

```text
Outstanding liability: 250,000 SDG
Settlement:            150,000 SDG
Remaining liability:   100,000 SDG
```

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

The actual remitting asset account is selected from the real chart of accounts. The current engine permits active asset accounts `1100`, `1200`, and `1300` for the settlement transfer.

## 4. Dashboard balance semantics after settlement

The current approved Fina amount must not simply disappear from history.

The live FM balance must distinguish:

1. **Pending** — submitted but not approved.
2. **Approved / Unsettled** — approved and posted to liability `2300`, but not yet remitted.
3. **Settled to Fina** — actually transferred; retained as historical cumulative settlement information.
4. **Returned** — returned/rejected collection; not part of the outstanding Fina liability.

A full settlement reduces the outstanding liability through its posted settlement journal; it does not erase the collection record.

Supervisor Fina amounts remain Supervisor-scoped. Settlement controls remain unavailable to Supervisors.

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

### `fina_settlements`

One row represents one FM settlement event/batch and preserves:

- settlement identifier/reference;
- actual settlement date;
- amount and currency;
- payment method;
- actual remitting account;
- transfer/reference number;
- supporting evidence path;
- settlement status;
- settlement journal reference;
- reconciliation/cancellation notes;
- FM actor/timestamps for creation, approval, transfer, reconciliation, and cancellation.

### `fina_settlement_allocations`

One row represents an amount of a specific Fina collection allocated to a settlement.

This supports partial settlement without modifying the original collection. A collection can therefore be allocated across multiple settlement batches over time, subject to runtime controls preventing allocation above its approved/unsettled amount.

## 7. Settlement lifecycle

```text
draft → approved → transferred → reconciled → closed
                     ↓
                  cancelled
```

All settlement actions remain FM-only. The `approved` state is an internal settlement lifecycle state and does not grant execution rights to another role.

## 8. Stage 3 accounting engine — IMPLEMENTED

Implementation file:

`modules/accounting/fina_settlement_lib.php`

Implementation commit:

`580a9b2c9991d29a8a06fe6f56ee74ac2216a506`

The engine currently provides:

- FM-only server-side authorization for settlement actions;
- settlement/allocation table readiness checks;
- strict settlement date validation;
- SDG/system-currency enforcement;
- validation of active treasury remitting accounts;
- draft settlement creation;
- collection-level allocation validation;
- full and partial settlement support;
- prevention of allocation above the approved/unsettled collection balance;
- rejection of pending/returned/non-approved collections;
- verification that the approved collection has a posted Fina collection journal;
- settlement approval;
- actual transfer recording with required transfer reference;
- settlement journal creation as a separate accounting event;
- `Dr 2300 / Cr actual remitting asset` accounting;
- balanced-journal verification before completion;
- preservation of original Fina collection records and journals;
- reconciliation and closure transitions;
- cancellation of draft/approved settlements with a mandatory reason;
- outstanding-liability calculation from approved collections minus non-cancelled settlement allocations.

### Atomicity

Settlement creation, approval, and transfer accounting use database transactions. If the operation fails, its database changes are rolled back rather than leaving a partial settlement or partial journal.

### Historical preservation

The engine does not rewrite, zero, delete, or replace the original Fina collection amount or its original collection journal. Settlement is represented by its own header, allocations, and journal.

### Runtime status

Stage 3 is **implemented in repository code but not yet runtime-verified** against the live local application. No Stage 3 runtime acceptance result is claimed until the controlled tests are completed.

## 9. Notifications — locked rule

Settlement notifications are **not** sent to Supervisors.

After FM completes the actual settlement, the system should notify:

- **General Manager (GM)**
- **Vice General Manager (VGM)**

There is **no direct Fina-system notification/integration**. The Ahl El Kheir notification is internal management oversight.

Management notification delivery will be implemented/verified as a separate stage after the accounting engine and UI workflow are established.

## 10. Reports and reconciliation

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

A settlement must never create Ahl revenue, become an expense, reduce sponsor obligations, absorb sponsor-payment shortfalls, alter historical collection amounts, delete approved collection records, settle the same amount twice, create an unbalanced journal, or settle a returned/pending collection as though approved.

## 11. Deployment plan and completion checklist

### Stage 0 — Business rule freeze — COMPLETE

- [x] Monthly settlement is the normal cycle.
- [x] Early settlement is allowed at any time.
- [x] Full and partial settlement are supported.
- [x] FM is the only role that performs the entire settlement process.
- [x] Supervisors have no settlement action.
- [x] Settlement is an internal Ahl accounting event; no direct Fina-system integration.
- [x] Actual settlement date means the date money is actually transferred to Fina.
- [x] Settlement notifications go to GM and VGM, not Supervisors.

### Stage 1 — Existing-system/schema inspection — COMPLETE

- [x] Actual `fina_collections` schema inspected.
- [x] Actual account `2300` and posted-journal structure inspected.
- [x] Existing Fina collection journal creation paths inspected.
- [x] Treasury accounts and payment-method semantics inspected.
- [x] Relevant accounting/reference/evidence patterns inspected.
- [x] Relevant role/authorization and notification infrastructure inspected.
- [x] Confirmed no prior Fina settlement/allocation structure existed.

**Live evidence used for implementation:** `fina_collections` uses `pending / approved / returned`; account `2300` is an active liability; treasury accounts are `1100`, `1200`, and `1300`; existing Fina collection journals follow asset → `2300`; existing Fina tables were `fina_sources` and `fina_collections` before Stage 2.

### Stage 2 — Data model — COMPLETE

- [x] Dedicated settlement batch created by migration.
- [x] Collection-to-settlement allocation created.
- [x] Partial allocation model established.
- [x] Historical collection amounts/journals preserved.
- [x] Settlement lifecycle defined.
- [x] Evidence/reference fields defined.
- [x] Actor/timestamp audit fields defined.
- [x] Reconciliation/cancellation fields defined.
- [x] Migration committed and **applied successfully to the live database by the user on 2026-09-17, with no SQL errors**.

Migration:

`database/migrations/2026-09-17_fina_settlement_schema.sql`

### Stage 3 — Accounting engine — IMPLEMENTED / RUNTIME PENDING

- [x] Settlement journal creation implemented as a separate accounting event.
- [x] Debit liability `2300` by the settled amount.
- [x] Credit the selected actual remitting asset account.
- [x] Balanced journal verification implemented.
- [x] Allocation above eligible outstanding balance blocked server-side.
- [x] Duplicate/over-allocation control implemented through allocation-balance checks.
- [x] Partial settlement implemented.
- [x] Original collection journals preserved.
- [x] Transactional rollback implemented for settlement creation/approval/transfer.
- [ ] Runtime verify all Stage 3 controls against the live application.

### Stage 4 — FM-only settlement UI — NEXT

- [ ] Add FM settlement entry/list page.
- [ ] Show current outstanding liability from eligible approved collections.
- [ ] Allow FM to choose full or partial settlement.
- [ ] Capture actual transfer date.
- [ ] Capture payment method/remitting account.
- [ ] Capture transfer reference.
- [ ] Capture supporting evidence.
- [ ] Show eligible collections and allocation.
- [ ] Enforce FM-only access server-side on every settlement action.
- [ ] Ensure Supervisors cannot access settlement actions by direct URL.
- [ ] Ensure GM/VGM notifications do not grant settlement authority.

### Stage 5 — Dashboard/report updates

- [ ] Change FM primary live Fina balance to outstanding/unsettled liability.
- [ ] Keep cumulative settled amount visible separately.
- [ ] Keep pending and returned counts separate.
- [ ] Update Fina report to distinguish collections, outstanding liability, and settlements.
- [ ] Keep Supervisor totals scoped to the individual Supervisor.
- [ ] Never display a settled amount as still outstanding.

### Stage 6 — Management notifications

- [ ] On completed settlement, notify GM.
- [ ] On completed settlement, notify VGM.
- [ ] Do not notify Supervisors about settlement completion.
- [ ] Include settlement reference, amount, and actual transfer date.
- [ ] Use existing notification/read/history infrastructure where applicable.
- [ ] Notification failure must not undo completed accounting settlement.

### Stage 7 — Reconciliation and audit controls

- [ ] Reconcile eligible approved/unsettled collections to settlement amount.
- [ ] Reconcile settlement journal to actual remitting account movement.
- [ ] Reconcile closing Fina liability to account `2300`.
- [ ] Verify full settlement produces the expected zero outstanding balance for the settled portion.
- [ ] Verify partial settlement leaves the correct remaining liability.
- [ ] Verify historical settlement records remain searchable.
- [ ] Verify evidence/reference remains attached.
- [ ] Verify duplicate settlement is blocked.
- [ ] Verify returned/pending collections cannot be settled.

### Stage 8 — Security and separation-of-duties tests

- [ ] FM can perform the complete settlement workflow.
- [ ] Supervisor cannot create/approve/release/close a settlement.
- [ ] GM cannot acquire settlement execution rights merely by receiving notification.
- [ ] VGM cannot acquire settlement execution rights merely by receiving notification.
- [ ] Direct URL/action attempts by non-FM users are server-side blocked.
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
- [ ] Report reconciles with ledger and settlement records.

### Stage 10 — Production readiness / closure

- [ ] Production deployment process confirmed.
- [ ] Settlement policy/configuration confirmed by management.
- [ ] Transfer evidence retention confirmed.
- [ ] Backup/recovery considerations reviewed.
- [ ] Final audit evidence recorded.
- [ ] Master audit updated.
- [ ] Session index updated.
- [ ] Continuation prompt updated if any permanent rule changed.
- [ ] Settlement feature marked COMPLETE only after code + runtime verification.

## 12. Important implementation rule

Do **not** implement settlement by simply setting account `2300` to zero in isolation.

The accounting result of a full settlement naturally reduces the outstanding `2300` balance through a posted settlement journal. Historical collection and settlement records remain intact.

Do not add a simple `settled = yes/no` flag to `fina_collections` without considering partial settlement and allocation. The settlement event and allocation remain auditable.

## 13. Current continuation point

**Stage 0 — COMPLETE.**  
**Stage 1 — COMPLETE / live schema inspected.**  
**Stage 2 — COMPLETE / migration applied successfully to live DB.**  
**Stage 3 — IMPLEMENTED / runtime verification pending.**  
**Stage 4 — NEXT: FM-only settlement UI.**

The next session should first perform the controlled Stage 3 runtime verification of the new accounting engine. Once those tests pass, continue directly into Stage 4 UI implementation. Do not recreate the migration or modify existing Fina collection records/journals.