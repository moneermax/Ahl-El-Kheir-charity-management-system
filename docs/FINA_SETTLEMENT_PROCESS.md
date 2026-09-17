# Fina Al-Khair — Settlement Process and Balance Semantics

**Checkpoint:** 2026-09-17  
**Status:** DESIGN / AUDIT DECISION — settlement workflow is not yet implemented.

## 1. Purpose

Fina Al-Khair money is a protected third-party fund. Ahl El Kheir collects and holds the money on Fina's behalf. The dedicated liability/control account is `2300`.

The existing collection workflow recognizes the obligation when a Fina collection is approved. Settlement to Fina is a separate accounting event and must not be treated as Ahl El Kheir revenue or expense.

## 2. Recommended settlement model

The recommended operational policy is **monthly settlement with controlled early settlement when justified**.

A practical cycle is:

1. Supervisors submit Fina collections.
2. FM reviews and approves or returns each submission.
3. Approved collections are posted as Fina liability in account `2300`.
4. At the monthly cutoff, FM reconciles the approved/unsettled Fina balance to the underlying collection records and posted journals.
5. A settlement batch is prepared for the amount actually due to Fina.
6. The authorized settlement payment is made to Fina.
7. The settlement is recorded as a separate accounting event that reduces liability `2300`.
8. Transfer/payment evidence and settlement reference are attached to the settlement record.
9. The settlement is closed only after the amount, bank/cash movement, liability reduction, and evidence reconcile.

Monthly settlement should be the normal operating rhythm because it gives Fina a predictable remittance schedule and gives Accounting a clean period-end reconciliation. The system should still permit an authorized **early/manual settlement** when the balance becomes material, Fina requests an earlier remittance, or operational circumstances justify it.

The system should not require settlement merely because a collection was approved; approval establishes the payable liability, while settlement is the later remittance event.

## 3. Accounting treatment

If approved Fina collections total X and the money is currently held in the relevant Ahl El Kheir asset account:

### Collection approval

```text
Debit   Cash / Bank / Wallet       X
Credit  Fina Liability 2300        X
```

### Settlement/remittance to Fina

```text
Debit   Fina Liability 2300       X
Credit  Cash / Bank / Wallet      X
```

The settlement is **not** an Ahl El Kheir expense. It is the payment of an existing third-party liability.

The actual credit asset account must correspond to the payment method/account from which the remittance is made. No account should be assumed until the current chart of accounts and settlement payment method are inspected.

## 4. What the dashboard should mean after settlement

The current dashboard value representing Fina money must not simply disappear or be reset. Historical financial information must remain auditable.

The preferred dashboard model is to distinguish four concepts:

- **Pending:** submitted to FM but not approved.
- **Approved / Unsettled:** approved and posted to liability `2300`, but not yet remitted to Fina.
- **Settled to Fina:** amounts already remitted to Fina.
- **Returned:** submissions rejected/returned and therefore not part of the payable Fina liability.

For the current example:

```text
Before settlement:
Approved / unsettled Fina balance = 250,000 SDG

After settling the full 250,000 SDG:
Approved / unsettled Fina balance = 0 SDG
Settled to Fina (cumulative)       = 250,000 SDG
```

The historical approved collections remain approved and remain visible in reports/history. Settlement changes their **settlement state**, not their original collection history.

## 5. Recommended FM dashboard cards

The current card labelled as the amount associated with liability `2300` should ultimately be made explicit as:

**الرصيد غير المسدد لفينا — حساب الالتزام 2300**

This should represent the current outstanding Fina liability, not lifetime approved collections.

Additional cards/figures should distinguish:

- إجمالي التحصيلات المعتمدة — cumulative approved collections.
- الرصيد غير المسدد — current liability still owed to Fina.
- إجمالي ما تم تسديده لفينا — cumulative settled amount.
- تحصيلات معلقة — pending FM review.
- تحصيلات مرتجعة — returned collections.

This prevents the common misunderstanding that a historical approved total is still money currently held by Ahl El Kheir.

## 6. Supervisor dashboard semantics

Supervisor figures must remain Supervisor-scoped.

A Supervisor should see only that Supervisor's own Fina collections. Settlement is an FM/Accounting responsibility and must not become a Supervisor financial-control action.

A future Supervisor dashboard may show useful scoped information such as:

- approved Fina collections submitted by this Supervisor;
- this Supervisor's approved amount still unsettled;
- this Supervisor's historical amount settled to Fina.

It must not expose the organization-wide Fina liability or another Supervisor's collections merely because the Supervisor is viewing a dashboard.

## 7. Settlement batch design

A future settlement feature should use a dedicated settlement record/batch rather than editing `fina_collections` amounts or changing historical collection records in place.

The settlement record should, after the actual schema is inspected and designed, be able to preserve at least:

- settlement identifier/reference;
- settlement date;
- amount settled;
- currency;
- recipient Fina information where appropriate;
- payment method/account used for the remittance;
- authorized/prepared/released actors according to separation of duties;
- supporting transfer/payment evidence;
- accounting journal reference;
- included Fina collections or an auditable allocation basis;
- settlement status and timestamps;
- reconciliation/notes where required.

Exact table and column names must be decided only after inspecting the current schema. No schema is prescribed by this document.

## 8. Partial settlement

Partial settlement should be supported if operationally useful.

Example:

```text
Outstanding Fina liability: 250,000 SDG
Settlement #1:                150,000 SDG
Remaining liability:          100,000 SDG
```

The dashboard must then show 100,000 SDG as the outstanding liability while retaining 150,000 SDG as settled history.

A collection-level settlement allocation is preferable to a vague aggregate adjustment because it makes later reconciliation possible. If a settlement is partial, the system should never mark the entire underlying collection as settled unless its full amount has actually been remitted.

## 9. Reconciliation controls

Before closing a settlement, FM should be able to reconcile:

`Approved collections not previously settled`

against

`Settlement amount`

and then verify:

`Previous outstanding liability + new approved liability - settlement = new outstanding liability`

The settlement must not:

- create Fina revenue for Ahl El Kheir;
- reduce sponsor obligations;
- absorb sponsor-payment shortfalls;
- alter historical collection amounts;
- delete approved collection records;
- silently change a returned collection into an approved collection;
- create an unbalanced journal;
- settle the same collection amount twice.

## 10. Separation of duties

The preferred control is:

```text
Collection creator / Supervisor
        ↓
FM review and approval
        ↓
Fina liability 2300
        ↓
Settlement preparation / reconciliation
        ↓
Authorized settlement release
        ↓
Settlement journal + evidence
```

Where organizational roles permit, preparation and release should be separated. At minimum, the settlement must require an authorized Accounting/FM actor and retain actor/time/evidence information.

## 11. Settlement frequency policy

Recommended default:

**Monthly settlement at a defined cutoff.**

Recommended exceptions:

- early settlement when Fina requests it;
- early settlement when the outstanding amount becomes materially large;
- special settlement for an agreed event or reporting period.

The system should support a configurable policy later rather than hard-coding an arbitrary amount or date before the organization's actual agreement with Fina is known.

The final operational policy should be agreed with Fina/management before production deployment because the software should record the organization's real settlement agreement rather than invent one.

## 12. Reports after settlement

The Fina report should eventually separate:

1. Submitted/pending activity.
2. Approved collections.
3. Returned collections.
4. Outstanding liability.
5. Settlements made to Fina.
6. Remaining balance after settlements.

For any selected period, the report should make clear whether an amount is a **collection**, a **liability still outstanding**, or a **remittance already paid to Fina**.

The current correction that report financial totals are based on approved collections remains valid. Settlement is a subsequent layer and must not be confused with approval.

## 13. Current example — 250,000 SDG

The FM dashboard currently shows the approved Fina amount associated with liability `2300` as `250,000 SDG`.

That means, under the current accounting model, Ahl El Kheir has an approved Fina liability of 250,000 SDG represented by the posted Fina collections.

It does **not** mean that 250,000 SDG should be erased after payment to Fina. Instead:

```text
Before settlement
-----------------
2300 outstanding liability = 250,000 SDG

Settlement payment
------------------
Dr 2300 liability          250,000
Cr remitting cash/bank     250,000

After full settlement
---------------------
2300 outstanding liability = 0 SDG
Historical settled total  = 250,000 SDG
```

The exact settlement asset account and settlement evidence workflow remain to be implemented after schema/code inspection.

## 14. Implementation status

This document records the recommended business/accounting design only.

**Not yet implemented:**

- settlement tables/fields;
- settlement creation UI;
- settlement approval/release workflow;
- settlement journal creation;
- settlement-to-collection allocation;
- dashboard outstanding/settled split;
- settlement report/history;
- settlement-specific notifications.

No existing Fina collection data should be modified merely to prepare for this future feature.
