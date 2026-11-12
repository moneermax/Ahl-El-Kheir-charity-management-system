# VGM Sponsorship Payment Entry and Monthly Service Fee — Working Plan

**Status:** APPROVED WORK PLAN / IMPLEMENTATION NOT STARTED  
**Baseline:** 2026-11-11  
**Target branch:** `main` only  
**Primary page:** `modules/transactions/create.php`  
**Related domain:** Sponsorships, advances, ordinary transactions, FM review and accounting posting

## 1. Purpose and scope

This plan combines the original open task—continue the VGM sponsorship advance-payment workflow—with the payment-entry redesign and monthly service-fee rules clarified in the discussion.

The work must be completed in small, independently verifiable stages. Do not begin implementation by redesigning the HTML first: the service-fee and accounting contract must be established before the UI presents totals that the backend cannot preserve correctly.

In scope:
- Confirm the VGM can open the intended sponsorship and access the Register payment action.
- Continue the intended advance-payment path for a sponsorship covering multiple months.
- Redesign the payment-detail entry area as a compact, readable table while preserving the existing form's functionality.
- Represent the agreed monthly service fee as an addition to the monthly sponsorship amount.
- Preserve the separation between the net sponsorship allocation and administrative/service-fee revenue.
- Freeze the applicable fee agreement in the submitted transaction so a later policy/agreement change cannot silently alter a pending or posted payment.
- Verify the VGM submission → FM review → approval/posting → sponsorship allocation path.

Out of scope unless a source/schema audit proves a direct dependency:
- Reworking the completed arrears/advances calculations or historical accounting backfill.
- Changing unrelated Supervisor collection workflows, GM read-only reports, or ordinary donation transaction behavior. **Supervisor monthly-sponsorship submissions are in scope and must use the same sponsorship-specific fee agreement and calculation as VGM submissions.**
- Reopening closed accounting, payroll, i18n, or salary-advance work.
- Broad redesign of other pages.

## 2. Authoritative business rules

### 2.1 Monthly amounts

A monthly sponsorship payment has two distinct components:
- **Net sponsorship amount:** the amount credited/allocated to the sponsorship obligation.
- **Monthly service fee:** an additional fixed amount agreed with the sponsor.

The amount collected for one covered month is:

`monthly_total = monthly_sponsorship_amount + agreed_monthly_service_fee`

Example only:
- Sponsorship: 5,000 SDG
- Agreed monthly service fee: 2,000 SDG
- Collected for that month: 7,000 SDG

For three covered months under that same agreement:
- Sponsorship allocation: 15,000 SDG
- Service fees: 6,000 SDG
- Total collection: 21,000 SDG

The fee is charged **per covered month**, not once per multi-month transaction.

### 2.2 No gross-up or fee deduction from sponsorship

Do not calculate the service fee as a percentage or gross-up the sponsorship amount for this requirement. Do not subtract the service fee from the agreed sponsorship amount.

The existing accounting posting model currently treats transaction amount as gross and deducts an administrative fee to derive net sponsorship allocation. That is not automatically equivalent to this business rule. The implementation must reconcile the submitted gross total, agreed monthly fee, and net sponsorship allocation explicitly, without changing unrelated transaction types.

### 2.3 Agreement ownership and stability

The fee is agreed with the sponsor during sponsorship onboarding/creation. The agreement is associated with the relevant sponsorship arrangement, not assumed to be a universal sponsor-wide value.

An organization-wide policy change must not silently rewrite an existing sponsor agreement. An existing agreement changes only through an explicitly agreed amendment. A new agreement may use the then-current organizational default, if the actual application has such a default.

For a multi-month advance payment, apply the sponsorship's agreed monthly fee to each covered month in that payment. A later change must not retroactively alter the submitted payment or already-covered months.

The exact storage location and effective-date/history mechanism are **not yet established**. Inspect the live schema and current create/edit workflows before deciding whether a migration is required.

### 2.4 Accounting separation

For a monthly sponsorship amount of 5,000 SDG and service fee of 2,000 SDG:
- Cash received: 7,000 SDG.
- Sponsorship allocation/revenue or deferred liability: 5,000 SDG, according to the existing coverage-period accounting rules.
- Service-fee revenue: 2,000 SDG.

For three months, preserve the equivalent split: 21,000 cash, 15,000 sponsorship component, and 6,000 fee component.

Do not weaken the existing rules for deferred future sponsorship receipts, allocation limits, journal balance, duplicate posting protection, or paid/posted record immutability.

## 3. Known source findings (must be revalidated against current main before coding)

The prior source review found:
- `modules/transactions/create.php` supports multiple payment-detail rows, a coverage month per row, shared transaction details, and row-level receipt handling.
- The direct VGM ordinary-transaction path currently initializes fee fields to zero and sets net amount equal to the entered line amount.
- `modules/accounting/lib_admin_fee_policy.php` currently calculates a gross-to-net administrative fee using the policy effective on the transaction date.
- `modules/accounting/lib.php` posts gross cash and splits the credit between net sponsorship and administrative-fee revenue; sponsorship allocation uses net.
- The separate Supervisor `sponsor_payments` approval path also calculates a fee from gross amount.
- No prior inspection established that a per-sponsorship agreed service-fee field already exists.

These are investigation leads, not permission to assume the current schema or source has not changed. Re-fetch current `main` source and schema before implementation.

## 3A. Stage 1 audit evidence — 2026-11-12

The following findings were revalidated against the current `main` source and the updated `database/ahl_el_kheir.sql` dump. They are evidence, not implementation decisions:

- `sponsorships` stores `monthly_amount` but has no sponsorship-specific service-fee agreement field.
- `transactions` stores the existing administrative-fee fields (`admin_fee_method`, `admin_fee_value`, `admin_fee_amount`, `admin_fee_policy_id`, `net_amount`) but no distinct sponsorship service-fee agreement or service-fee snapshot.
- `sponsor_payments` stores one Supervisor submission row per covered month/purpose, with `amount`, `payment_period`, `sponsorship_id`, and review/transaction linkage; it has no service-fee agreement or snapshot fields.
- `modules/sponsorships/create.php` saves the monthly sponsorship commitment but no service-fee agreement. `modules/sponsorships/view.php` has no fee-amendment action; its current management actions concern sponsorship status.
- VGM/admin sponsorship payments use the direct `transactions` path in `modules/transactions/create.php`. The pending transaction currently stores the entered row amount as `amount`, sets the administrative-fee amount to zero, and sets `net_amount` equal to that amount. At FM posting, `ak_apply_admin_fee_snapshot_prepared()` may calculate the organization-wide policy if no policy snapshot exists.
- Supervisor submissions in the same page create one pending `sponsor_payments` row for each detail row. FM approval in `ak_approve_sponsor_payment()` converts that row into a posted `transactions` record and calculates the organization-wide fee using the policy effective on the submission date. This is a separate submission path from VGM's direct transaction path.
- The shared posting function allocates the transaction's `net_amount` to sponsorship and credits administrative-fee revenue to account `4200`. That account is currently labelled/used for administrative fees; whether the new agreed service fee belongs in this account is not yet established and must not be assumed.
- The current global fee helper is gross-to-net: a fixed fee is `min(gross_amount, policy_value)`; it is not the required additive agreement rule. It must not be repurposed as the sponsorship service-fee agreement calculation.
- The FM queues currently display a single amount, not an auditable breakdown of sponsorship component, agreed service fee, and total.

**Supervisor parity decision — confirmed by the user on 2026-11-12:** the same sponsorship-specific agreement and calculation must govern Supervisor submissions because this is a core Supervisor responsibility. VGM and Supervisor entry routes must not charge different amounts for the same sponsorship and covered month. This parity is a mandatory acceptance criterion.

**Design direction (not yet approved for implementation):** keep the agreed fee attached to the sponsorship agreement; snapshot the agreement reference and monetary components when either route submits; preserve the two existing workflow/review paths; and make the accounting posting distinguish the sponsorship component from the additional service-fee component without changing unrelated donation or ordinary transaction behavior. Exact table/column design and accounting account classification remain subject to the remaining business decisions and migration-convention audit.

**Known data fixture:** the updated repository dump contains active sponsorship `SH-003559` (ID `3559`), monthly commitment `5,000.00` SDG, start date `2026-08-27`, sponsor `SP-002857` (`معاويه عوض عبد العظيم`), child `اسراء`. The dump contains no linked `transactions`, `sponsor_payments`, or `sponsorship_payment_allocations` rows for this sponsorship. It remains a candidate fixture only; no payment was created or altered.

**Search limitation:** repository code search returned no indexed matches for the fee-helper identifiers, so the caller inventory is based on the active paths directly inspected above, not a claim that every repository caller has been exhaustively enumerated. A full local/repository grep is still required before any shared helper change.

## 4. Ordered implementation plan and acceptance gates

### Stage 0 — Repository and current-workflow preflight

1. Read the canonical project documents and this continuation plan from `main`.
2. Confirm current `main` head and inspect local working-tree state before pulling or editing.
3. Inspect the intended sponsorship details page as VGM and confirm whether Register payment is available.
4. Confirm the exact sponsorship record and intended payment periods without submitting or modifying financial data.
5. Reinspect current source for the payment form, sponsorship detail action, FM review, posting, and allocation paths.

**Acceptance:** the intended VGM route and exact sponsorship/payment context are established; no payment has been created or altered during this inspection.

### Stage 1 — Data-model and workflow audit for the agreed fee

Inspect:
- `database/ahl_el_kheir.sql` and every relevant migration;
- sponsorship create/edit/detail pages and their POST handlers;
- all uses of `admin_fee_amount`, `admin_fee_method`, `admin_fee_value`, `admin_fee_policy_id`, and `net_amount`;
- all callers of `ak_apply_admin_fee_snapshot_prepared()` and `ak_calculate_admin_fee_prepared()`;
- VGM direct transactions and Supervisor `sponsor_payments` approval paths;
- FM approval, journal posting, and sponsorship allocation.

Answer from evidence:
1. Does the sponsorship record already store an agreed monthly fee?
2. If yes, where is it set, edited, displayed, and protected from accidental changes?
3. If no, what is the smallest normalized schema change that can store the agreement and preserve history?
4. How can the submitted transaction snapshot the fee and its source agreement so pending submissions remain stable?
5. Which payment-entry paths must share the rule, and which must remain unchanged?

**Acceptance:** a source/schema-backed design is written down before any migration or code change. No guessed column/table names and no runtime DDL.

### Stage 2 — Persist and manage the sponsorship fee agreement

Implement only if Stage 1 proves storage is absent or insufficient.

Requirements:
- Store a fixed monthly fee against the correct sponsorship agreement.
- Make the fee visible to authorized users at the appropriate create/edit/detail points.
- Define whether an agreement can be amended, who may amend it, and how prior agreement values remain auditable.
- Preserve existing sponsorships and historical transactions; do not backfill guessed fees.
- Use an explicit migration for any database change.
- Ensure a fee change cannot silently change an already-submitted payment.

**Acceptance:** a controlled test proves a sponsorship's agreed fee is saved and retrieved correctly; existing records retain their prior meaning; migration is reversible/operationally safe according to project migration conventions.

### Stage 3 — Correct payment calculation and immutable submission snapshot

Implement the backend contract before presenting the redesigned totals.

For each eligible monthly sponsorship row:
- derive the net sponsorship amount from the existing sponsorship commitment/agreement;
- derive the agreed fixed service fee per covered month;
- calculate row total as sponsorship + fee;
- calculate transaction total as the sum of row totals;
- preserve the per-month breakdown so multiple months do not collapse into one fee;
- snapshot the fee amount and agreement reference/value on submission;
- prevent later policy/agreement changes from changing a pending or posted transaction.

Do not apply this fee to donations, campaigns, other purposes, or non-monthly transaction types unless the existing domain rules explicitly require it.

Reconcile the implementation with the current gross-to-net accounting helper. Prefer a narrow, explicit sponsorship-payment path over changing shared fee semantics for all transaction types. If shared posting code must change, first map every caller and add regression coverage for unaffected types.

**Acceptance:** backend-level tests prove the exact per-row and transaction totals, immutable snapshot behavior, correct net allocation, and unchanged behavior for unrelated transaction types.

### Stage 4 — Compact payment-detail table redesign

Redesign the existing payment detail rows into a compact table, preserving all current functionality.

Required per-row information:
- covered month;
- purpose/type where applicable;
- net sponsorship amount;
- agreed monthly service fee;
- total for that month;
- existing note/description field where applicable;
- existing row-level receipt control where applicable;
- existing row add/remove behavior and validation.

Keep shared transaction information (actual receipt date, payment method, shared receipt/reference fields, sponsor/sponsorship selection, and any existing common fields) in its existing logical area.

UI requirements:
- Arabic RTL and current Bootstrap 5.3 RTL conventions.
- Compact controls and aligned numeric columns.
- Clear distinction between sponsorship amount, service fee, and row total.
- A visible aggregate total consistent with the backend.
- Responsive behavior for narrow screens; do not make the table unusable on mobile.
- Preserve all current parsing/autofill behavior, field names, validation, CSRF protections, receipt upload handling, and returned-transaction correction behavior.
- Do not remove a field or change its semantics merely to make the table smaller.

**Acceptance:** manual browser checks cover adding/removing rows, multiple months, all existing purpose types, receipts, validation errors, RTL layout, and narrow viewport. No regression in existing parser/autofill or submission behavior.

### Stage 5 — FM review and accounting integration

Verify the entire financial lifecycle using fresh, disposable test data or an approved rollback-only harness:
1. VGM submits a multi-month sponsorship payment.
2. Submitted detail shows each month, net sponsorship, service fee, and total.
3. FM queue shows the correct total and sufficient component detail for review.
4. Return/correction/resubmission preserves or recalculates only what the business rules permit; no silent fee drift.
5. Before approval, no posted journal exists.
6. On approval, exactly one balanced journal is posted.
7. Cash debit equals the full collected total.
8. Sponsorship net component is allocated to the correct coverage months.
9. Service-fee component is credited separately.
10. Future-period net receipts remain deferred until earned under the existing month-end recognition rule.
11. Excess amounts remain unallocated according to the existing rule; no automatic spillover.
12. Duplicate approval/posting is prevented.

**Acceptance:** source tests and local MariaDB/browser evidence agree on the same amounts and allocation rows. No existing posted transaction is edited or reused.

### Stage 6 — Advance-payment end-to-end acceptance

After Stages 0–5 are accepted, execute the intended VGM advance-payment scenario using a fresh eligible sponsorship/test record. The earlier discussion referenced a three-month advance example; confirm the live sponsorship commitment, agreed fee, periods, and exact amount from the application before submission.

Do not reuse existing financial evidence:
- TR-000023 is already posted and allocated to SH-003557 for November 2026; preserve it.
- TR-000024 / JE-000054 are ordinary general-donation review evidence; preserve them.
- Do not modify, return, void, or reuse either transaction.

Verify:
- VGM can access the payment action;
- each covered month is represented once;
- per-month fee is applied once per month;
- total collected matches the visible row totals;
- FM can review and approve;
- allocations and journal are correct;
- the Advances report reflects the resulting committed/future allocations and any remaining unallocated balance accurately.

**Acceptance:** exact transaction, journal, audit, and allocation evidence is recorded; user confirms the local browser workflow.

### Stage 7 — Documentation, CI, local verification, and closure

- Add regression tests for the business rules and unaffected transaction paths.
- Run relevant PHP syntax checks and focused tests; run repository CI.
- Apply migrations only through the documented migration process.
- Perform local XAMPP/Apache/MariaDB browser acceptance when required.
- Update the master status/audit and session continuation references without rewriting historical evidence.
- Record exact commit hashes, test output, local acceptance evidence, and known limitations.
- Commit to `main` only; preserve unrelated staged/untracked files and backups.

**Acceptance:** no item is called complete without the corresponding evidence. CI success is not a substitute for local browser/database acceptance.

## 5. Test matrix (minimum)

| Scenario | Expected result |
|---|---|
| One month, 5,000 sponsorship + 2,000 fee | 7,000 collected; 5,000 allocated; 2,000 fee |
| Three months at same agreement | 21,000 collected; 15,000 sponsorship; 6,000 fees |
| Different fee on a newly agreed sponsorship | New agreement uses its own fixed fee |
| Existing agreement after organization default changes | Existing fee remains unchanged absent explicit amendment |
| Pending submission after agreement changes | Submitted snapshot remains unchanged |
| Advance covering future months | Fee applied once per covered month; net future amount remains deferred |
| Partial payment | Existing allocation/shortfall rules remain intact; fee treatment follows explicit agreement and must be defined by the source-backed design |
| Non-sponsorship transaction | Existing behavior unchanged |
| FM return and correction | No duplicate journal; fee snapshot behavior is deterministic and auditable |
| Duplicate approval/posting attempt | No duplicate journal or allocation |
| Receipt on row/shared receipt | Existing upload and access behavior preserved |
| Responsive RTL layout | All row values and actions remain usable |

The partial-payment case requires particular care: do not assume whether a fixed monthly service fee is due in full on a partial sponsorship payment or proportionally. Resolve it from the existing agreement/business rule with the user before coding that branch if the current source does not already define it.

## 6. Explicit open questions / decisions not to invent

1. **Partial-month/partial-amount payment:** is the fixed fee charged in full, waived, or otherwise handled when the sponsor pays less than the full monthly sponsorship amount?
2. **Agreement amendment authority:** which role can record a negotiated fee change, and from which covered month does it apply?
3. **Supervisor path parity:** should the same per-sponsorship agreement govern Supervisor submissions through `sponsor_payments`, or is the immediate release intentionally limited to VGM direct transactions? Audit and present the impact before changing either path.
4. **Existing sponsorships with no recorded fee agreement:** do not infer zero, apply today's policy, or backfill a fee without an explicit rule.

Ask only the decision that blocks the next implementation stage; do not front-load all questions.

## 7. Current starting point

The last documented next step before this plan was read-only inspection of the VGM-accessible sponsorship detail page to confirm whether Register payment is available. After that, continue the existing Advances → payment submission → FM approval/posting workflow.

The VGM payment form and accounting implementation had been inspected in an earlier source review, but the current main/schema must be revalidated before coding. The redesign and monthly additive fee implementation have **not** been implemented or runtime verified.

Closed adjacent work remains closed. In particular, do not rerun the completed sponsorship accounting backfill, arrears/advances calculation suites, GM/FM sponsor-review acceptance, or VGM ordinary-transaction return/resubmission acceptance without concrete regression evidence.
