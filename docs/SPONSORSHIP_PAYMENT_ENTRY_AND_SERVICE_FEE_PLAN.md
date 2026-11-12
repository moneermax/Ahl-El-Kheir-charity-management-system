# VGM Sponsorship Payment Entry and Monthly Service Fee — Working Plan

**Status:** APPROVED WORK PLAN / FM AGREEMENT SETUP CONFIRMED / CONFIGURABLE SERVICE-FEE ACCOUNTING DESIGN SELECTED / IMPLEMENTATION NOT STARTED  
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

The agreement defines two monthly reference values: the agreed sponsorship commitment and the agreed fixed service fee. Both must be loaded and displayed as read-only agreement values in the payment-entry row. Alongside them, provide separate editable actual-received fields, initially populated from the agreement values. The person recording the payment edits those actual-received fields to reflect what was collected. Editing a payment must not silently amend the underlying sponsorship agreement.

For each covered month:

`actual_month_total_received = actual_sponsorship_received + actual_service_fee_received`

The system must calculate the row and transaction totals from the two actual received values entered by the user—not silently substitute the agreement defaults.

Example only:
- Sponsorship: 5,000 SDG
- Agreed monthly service fee: 2,000 SDG
- Collected for that month: 7,000 SDG

For three covered months where the actual amounts received equal the agreement defaults:
- Sponsorship received/allocation: 15,000 SDG
- Service fees received: 6,000 SDG
- Total collection: 21,000 SDG

If the actual amounts received are lower or otherwise differ, the recorded total is the sum of the two actual entered components. Example: defaults of 5,000 sponsorship and 2,000 fee may be edited to actual receipts of 2,500 and 1,000 respectively; the system records a total receipt of 3,500. It must not force the full 7,000, gross-up either component, or deduct the fee from the sponsorship amount.

The fee is charged **per covered month**, not once per multi-month transaction.

### 2.2 No gross-up or fee deduction from sponsorship

Do not calculate the service fee as a percentage or gross-up the sponsorship amount for this requirement. Do not subtract the service fee from the agreed sponsorship amount.

The existing accounting posting model currently treats transaction amount as gross and deducts an administrative fee to derive net sponsorship allocation. That is not automatically equivalent to this business rule. The implementation must reconcile actual cash received, actual sponsorship component received, and actual service-fee component received explicitly, without changing unrelated transaction types. Agreement defaults and actual received values must remain separately identifiable in the submitted record and FM review.

### 2.3 Agreement ownership and stability

The agreed service fee is associated with the specific sponsorship and **each covered month**. It is not a universal sponsor-wide value and must not be treated as one immutable monthly amount for the entire life of the sponsorship.

The system must be able to retain the agreed fee applicable to each sponsorship/month. Different covered months may legitimately have different agreed fees. A multi-month advance therefore loads a separate agreement value for every month; it must not copy one current fee across all rows unless the stored agreement data says those months share that value.

A change to the agreement for a future month must not rewrite earlier months or any submitted payment snapshot. The Supervisor/VGM may edit the actual service fee received for a payment row, but that edit is a receipt fact, not an agreement amendment.

The global administrative-fee policy is **not the source of sponsorship service-fee amounts and must not calculate, override, prorate, gross-up, or deduct this fee on either sponsorship payment route**. Preserve the existing global administrative-fee policy and its callers for other transaction types unless a separate caller audit proves it can safely be retired. Do not delete or globally disable it as part of this task.

The exact storage/history mechanism for per-sponsorship, per-covered-month agreements is **not yet established**. Inspect current schema, migration conventions, and create/amend workflows before choosing the smallest justified data model.

**Agreement setup responsibility — confirmed by the user on 2026-11-12:** the GM, VGM, and FM will determine the agreed sponsorship amount and service fee in a meeting. The **FM is responsible for entering/setting up those agreed values in the system**. This assigns system data-entry responsibility; it does not establish unilateral FM decision authority or an in-system approval hierarchy. Do not invent either. Supervisors and VGM may enter actual receipt amounts in payment rows but must not amend agreement values through payment entry. Record agreement creation/amendment metadata and its meeting/decision reference where the final audit design supports it. The meeting's decision/evidence requirements remain to be confirmed before implementation.

### 2.4 Accounting separation

For a monthly sponsorship amount of 5,000 SDG and service fee of 2,000 SDG:
- Cash received: 7,000 SDG.
- Sponsorship allocation/revenue or deferred liability: 5,000 SDG, according to the existing coverage-period accounting rules.
- Service-fee revenue: 2,000 SDG.

For three months, preserve the equivalent split: 21,000 cash, 15,000 sponsorship component, and 6,000 fee component.

Do not weaken the existing rules for deferred future sponsorship receipts, allocation limits, journal balance, duplicate posting protection, or paid/posted record immutability.

**Service-fee account flexibility — design direction confirmed by the user on 2026-11-12:** do not hard-code sponsorship service-fee revenue to account `4200`, and do not ask the user to select an accounting classification without accounting expertise. Keep the service-fee component distinct in transaction data and route it through a controlled, auditable accounting mapping/configuration to a valid chart-of-accounts revenue account. The mapping must be governed by authorized accounting administration, validated before posting, and snapshotted or otherwise historically stable for posted journals. It must not be an arbitrary per-payment account picker. The GM/VGM/FM meeting or accounting owner must confirm the actual account classification before live posting is enabled. Do not invent an account code or create a new account merely to complete implementation.

## 3. Known source findings (must be revalidated against current main before coding)

The prior source review found:
- `modules/transactions/create.php` supports multiple payment-detail rows, a coverage month per row, shared transaction details, and row-level receipt handling.
- The direct VGM ordinary-transaction path currently initializes fee fields to zero and sets net amount equal to the entered line amount.
- `modules/accounting/lib_admin_fee_policy.php` currently calculates a gross-to-net administrative fee using the policy effective on the transaction date.
- `modules/accounting/lib.php` posts gross cash and splits the credit between net sponsorship and administrative-fee revenue; sponsorship allocation uses net.
- The separate Supervisor `sponsor_payments` approval path also calculates a fee from gross amount.
- The new sponsorship service fee is a manually agreed per-sponsorship/per-covered-month amount, not a global policy calculation. Both VGM and Supervisor sponsorship routes must bypass the global administrative-fee calculation for this component while preserving unrelated transaction behavior.
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

**Actual-receipt entry decision — confirmed by the user on 2026-11-12:** when a VGM or Supervisor opens a sponsorship payment row, the system must load and display the agreed sponsorship amount and agreed service-fee amount as read-only reference values, alongside separate editable actual-received fields initially populated with those agreement values. The recorder edits only the actual-received fields to reflect the amounts collected for that covered month and enters the remaining payment details. The system calculates the actual row/transaction total from those entered component amounts. The agreement values must remain distinguishable from actual received values and both must be snapshotted for audit/FM review. Partial receipts do not trigger an inferred fee rule: the recorder enters the actual service fee received. For example, agreement defaults 5,000 + 2,000 may be edited to actual receipts 2,500 + 1,000, producing an actual total of 3,500.


**Design direction (approved business rule; technical design pending):** store the agreed service fee per sponsorship and covered month, allowing different values in different months. Both VGM and Supervisor payment rows load that month's agreed sponsorship and service-fee references as read-only values, then allow editing of the actual amounts received. Snapshot agreement references/values and actual received components at submission. The global administrative-fee policy must not calculate or override the sponsorship service fee. Preserve the existing global policy for other transaction types pending a complete caller audit. Preserve the two existing review workflows and make posting distinguish the sponsorship component from the additional service-fee component. Exact table/column design, agreement amendment controls, and accounting account classification remain subject to source/schema audit.

**Per-month agreement decision — confirmed by the user on 2026-11-12:** the agreed service fee may differ from one covered month to another. Therefore it must be stored/looked up per sponsorship and covered month, not as one fixed amount copied to every month. A multi-month payment row loads the agreement for its own month. A Supervisor/VGM edit changes only the actual fee received for that row, never the agreement value.

**Global administrative-fee policy decision — confirmed by the user on 2026-11-12:** the sponsorship service fee must be accepted or edited manually by the Supervisor/VGM; it must not be automatically calculated by the global policy. The user's suggestion to remove/disable the policy is being applied narrowly: do not use that policy for either sponsorship payment route. Do not delete or globally disable the shared administrative-fee policy/helper until every other transaction caller is mapped and its independent accounting dependencies are understood.

**Additional source evidence — 2026-11-12:**
- `ak_apply_admin_fee_snapshot_prepared()` explicitly treats `sponsorship_payment` and `admin_fee` as eligible for the global policy. When a transaction lacks a policy snapshot, it loads the effective organization-wide policy using the transaction date and recalculates `admin_fee_amount` and `net_amount`.
- The common `ak_post_transaction_journal_prepared()` calls that helper before posting. For `sponsorship_payment`, it allocates `net_amount` through `ak_sp_create_payment_allocation_prepared()`, credits account `2200` with net, and credits account `4200` with the policy fee. This is a gross-to-net path and must be changed narrowly for sponsorship component receipts.
- The VGM direct route in `modules/transactions/create.php` currently creates one `transactions` row per detail month, initially sets `amount` to the one entered line amount, `admin_fee_amount=0`, and `net_amount=amount`. FM posting later invokes the global fee helper.
- The Supervisor route in the same page creates one pending `sponsor_payments` row per covered month. `ak_approve_sponsor_payment()` currently calculates the global policy against `sponsor_payments.amount`, inserts a posted transaction with the calculated net/fee snapshot, and posts the journal.
- The allocation helper uses the transaction's `net_amount` as the sponsorship component received, caps monthly allocation against the existing monthly commitment snapshot/amount, and records any excess as unallocated. It must receive the actual sponsorship component—not the total including service fee.
- `modules/transactions/edit_returned.php` still has a percentage-based fee input and recomputes gross-to-net values for returned VGM transactions. It is therefore a required compatibility point for returned sponsorship submissions; the new component contract must not be bypassed there.
- The existing chart-of-accounts seed labels account `4200` as administrative-fee revenue. Whether the agreed sponsorship service fee should post to this existing account or a distinct revenue account remains unapproved; do not silently classify it.

**Migration and FM review audit — 2026-11-12:**
- The inspected migration convention is explicit SQL under `database/migrations/`, with comments stating that request-time DDL is prohibited. The 2026-10-28 accounting schema cleanup uses `CREATE TABLE IF NOT EXISTS` and `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`; the 2026-11-08 sponsorship accounting migration creates allocation/event/pause tables with explicit indexes and foreign keys. An older 2026-10-05 migration uses plain `ALTER TABLE ... ADD COLUMN`, so migration idempotency conventions are not uniform. No migration README was found at `database/migrations/README.md`; the repository's actual migration application procedure still needs confirmation before applying any new migration.
- `modules/accounting/fm_transaction_review.php` lists pending VGM transactions with a single `transactions.amount` value. `modules/accounting/fm_review_queue.php` lists Supervisor submissions with a single `sponsor_payments.amount` value. Neither queue currently displays the two actual components and agreement references needed for informed review.
- The current VGM returned-transaction editor permits a monthly sponsorship percentage fee and recomputes `admin_fee_amount`/ `net_amount`. It must be redesigned to preserve the submitted per-month agreement snapshot and actual receipt components, not reintroduce percentage policy behavior after an FM return.
- The current allocation model stores `net_received` and calculates allocation against the sponsorship's monthly commitment. In the new contract, `net_received` must represent only actual sponsorship component receipts. Service-fee receipts must not inflate the sponsorship allocation or consume the monthly sponsorship commitment cap.

**Stage 1 audit boundary (updated 2026-11-12):** the local `git grep` inventory was completed on source baseline `4c5dcb4c2b2d5e25acf495515f3b323af8f58e07`. It found no additional callsites beyond the common journal-posting path and the separate Supervisor approval calculation path. A GitHub comparison from that baseline to current `main` (`5b0c8cd20657ecbf701a6aeeae273a0ed2673ed4`) shows 22 commits ahead, 0 behind, and no PHP source-file changes; the differences are documentation, the checked-in schema dump, and removal of two CSV files. Therefore the PHP caller inventory remains current for `main`. The checked-in schema dump did change and could not be retrieved through the connector because of its size, so re-inspect the current local schema and live database before writing a migration.

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
- Store the agreed service-fee amount by sponsorship and covered month; different months may have different agreed values.
- Make each month's agreed fee visible to authorized users at the appropriate create/edit/detail points.
- Implement FM-owned setup/entry of each month's agreed values, reflecting decisions made jointly by GM, VGM, and FM. Do not encode unilateral FM approval authority or a separate approval workflow unless the meeting explicitly establishes one.
- Define how the decision/meeting reference, effective covered month, amendments, and prior agreement values remain auditable.
- Preserve existing sponsorships and historical transactions; do not backfill guessed fees.
- Use an explicit migration for any database change.
- Ensure a fee change cannot silently change an already-submitted payment.

**Acceptance:** a controlled test proves a sponsorship's agreed fee is saved and retrieved correctly; existing records retain their prior meaning; migration is reversible/operationally safe according to project migration conventions.

### Stage 3 — Correct payment calculation and immutable submission snapshot

Implement the backend contract before presenting the redesigned totals.

For each eligible monthly sponsorship row:
- load and display that sponsorship/month's agreed sponsorship commitment and agreed service fee as read-only reference values; different months may load different fee values, with separate actual-received inputs initially populated from the corresponding month's agreement values;
- accept the actual sponsorship amount received and actual service-fee amount received entered by the VGM/Supervisor;
- calculate row total as actual sponsorship received + actual service fee received;
- calculate transaction total as the sum of row totals;
- preserve the per-month breakdown so multiple months do not collapse into one fee;
- snapshot both agreement defaults and both actual received component amounts, plus the agreement reference/value, on submission;
- prevent later policy/agreement changes from changing a pending or posted transaction.

Do not apply this fee to donations, campaigns, other purposes, or non-monthly transaction types unless the existing domain rules explicitly require it.

Reconcile the implementation with the current gross-to-net accounting helper. Prefer a narrow, explicit sponsorship-payment path over changing shared fee semantics for all transaction types. If shared posting code must change, first map every caller and add regression coverage for unaffected types.

**Acceptance:** backend-level tests prove the exact per-row and transaction totals, immutable snapshot behavior, correct net allocation, and unchanged behavior for unrelated transaction types.

### Stage 4 — Compact payment-detail table redesign

Redesign the existing payment detail rows into a compact table, preserving all current functionality.

Required per-row information:
- covered month;
- purpose/type where applicable;
- agreed sponsorship default and actual sponsorship amount received;
- agreed service-fee default and actual service-fee amount received;
- actual total received for that month;
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
| One month, defaults 5,000 sponsorship + 2,000 fee; actual receipts match defaults | 7,000 collected; 5,000 sponsorship component; 2,000 service-fee component |
| Partial actual receipts: 2,500 sponsorship + 1,000 service fee | 3,500 collected; components preserved separately; no automatic fee inference or gross-up |
| Three months at same agreement | 21,000 collected; 15,000 sponsorship; 6,000 fees |
| Different fee on a newly agreed sponsorship | New agreement uses its own fixed fee |
| Existing agreement after organization default changes | Existing fee remains unchanged absent explicit amendment |
| Pending submission after agreement changes | Submitted snapshot remains unchanged |
| Advance covering future months | Fee applied once per covered month; net future amount remains deferred |
| Partial payment | Actual sponsorship and service-fee receipts are independently entered, snapshotted, and summed; outstanding-balance treatment follows existing verified rules |
| Non-sponsorship transaction | Existing behavior unchanged |
| FM return and correction | No duplicate journal; fee snapshot behavior is deterministic and auditable |
| Duplicate approval/posting attempt | No duplicate journal or allocation |
| Receipt on row/shared receipt | Existing upload and access behavior preserved |
| Responsive RTL layout | All row values and actions remain usable |

Partial receipts are represented by the two actual component amounts entered by the recorder. The system does not infer a full, prorated, waived, or grossed-up service fee from a partial sponsorship receipt. Any remaining-balance/arrears calculation is a separate behavior and must follow the existing verified domain rules; do not invent a new balance rule as part of this payment-entry change.

## 6. Explicit open questions / decisions not to invent

1. **Agreement decision and evidence details:** GM, VGM, and FM will determine the agreed values in a meeting; FM enters them in the system. The meeting must still settle what reference/evidence is recorded, who may request or authorize amendments, and whether any in-system confirmation is required. Do not infer an approval hierarchy.
2. **Existing sponsorships with no recorded fee agreement:** do not infer zero, apply today's policy, or backfill a fee without an explicit rule.
3. **Outstanding-balance behavior:** how actual partial receipts affect any existing arrears/advance balance presentation must be verified against the existing domain rules; do not create a new balance calculation in this task.
4. **Service-fee revenue mapping target:** implement a controlled, auditable mapping so the destination is configurable without changing payment-entry code. Before enabling posting, an authorized accounting owner must select/confirm the actual chart-of-accounts destination. Do not assume `4200` or invent a new account code.
5. **Migration application procedure:** confirm the repository's actual migration process before preparing/applying a new migration.

Ask only the decision that blocks the next implementation stage; do not front-load all questions.

## 7. Current starting point

The last documented next step before this plan was read-only inspection of the VGM-accessible sponsorship detail page to confirm whether Register payment is available. After that, continue the existing Advances → payment submission → FM approval/posting workflow.

The VGM payment form and accounting implementation had been inspected in an earlier source review, but the current main/schema must be revalidated before coding. The redesign and monthly additive fee implementation have **not** been implemented or runtime verified.

Closed adjacent work remains closed. In particular, do not rerun the completed sponsorship accounting backfill, arrears/advances calculation suites, GM/FM sponsor-review acceptance, or VGM ordinary-transaction return/resubmission acceptance without concrete regression evidence.


## 2026-11-12 — Read-only accounting architecture audit completed

**Audit disposition:** the repository-side architecture review is complete enough to start a carefully gated implementation session. This is not implementation approval for an unconfirmed accounting treatment. No application code, schema, migration, or financial data has been changed in this audit.

### Confirmed source and configuration findings

1. **Chart of accounts administration**
   - `modules/accounting/accounts.php` permits `admin` and `financial_manager` to add accounts and toggle active status. GM/VGM are read-only on this page.
   - The page does not provide a sponsorship-service-fee mapping facility, does not record an `audit_log` event for account add/toggle actions, and does not provide a controlled version history for mappings.
   - The accounts model contains account code, Arabic/English names, account type, parent, and active status. Account code is unique. The valid account types include `revenue`.
   - The journal-line foreign key ensures the selected account exists, but the generic journal-line schema does not itself enforce that a revenue component uses a revenue account or that the account is active.

2. **Existing configurable-account patterns**
   - Project accounting stores project-specific revenue/expense account IDs. `akp_project_expense_account_id()` validates a configured account as active and of type `expense`, but then falls back to a hard-coded `5100`. This is a project-specific fallback pattern, not a safe template for a sponsorship service-fee mapping; the new mapping must not silently fall back.
   - HR incentive policy uses versioned policy records, effective dates, lifecycle statuses, and a separate approval-history table. It demonstrates version/history mechanics, but its HR → GM → FM approval chain must not be copied into this task without an organizational decision.
   - `accounting_admin_fee_policies` is a single organization-wide draft/active/superseded policy activated at opening-balance setup and locked once opening balance is live. It is not a per-sponsorship or per-covered-month agreement store and is not a reusable generic account-mapping facility.

3. **Audit and journal safeguards**
   - The application has a generic `audit_log` with actor/action/entity and old/new JSON values. The audit viewer is restricted to Admin and GM; this does not prevent FM-originated audit writes.
   - The existing chart-of-accounts add/toggle handlers do not write audit events. A new service-fee mapping change must therefore write its own immutable/versioned history and a corresponding audit event in the same database transaction. Audit-write failure must abort the configuration change; do not swallow it.
   - `ak_post_transaction_journal_prepared()` validates non-negative debit/credit values, one-sided journal lines, total balance, and that the referenced account code resolves. It does not generally validate account type or active status.
   - The new mapping must be validated explicitly as an existing, active `revenue` account both when configured and at posting. If there is no effective valid mapping, posting must fail closed with a clear FM-facing error. No default to `4200`.

4. **Shared administrative-fee helper caller inventory**
   - The local source grep covered `ak_apply_admin_fee_snapshot_prepared`, `ak_apply_admin_fee_snapshot`, `ak_calculate_admin_fee_prepared`, and `ak_calculate_admin_fee`.
   - The common application call is inside `ak_post_transaction_journal_prepared()`; that common path is used by ordinary FM review and the Supervisor approval workflow, so it cannot be globally redefined as sponsorship-only.
   - The Supervisor approval helper also calculates the current organization policy directly before inserting the transaction.
   - The shared helper must retain legacy/non-sponsorship behavior. New sponsorship rows need an explicit component-model marker/snapshot so the posting path can distinguish the new actual sponsorship + service-fee model from legacy rows. Before changing behavior, inspect the live DB for pending legacy sponsorship rows and define their compatibility treatment; never silently reinterpret them.
   - The VGM returned-transaction editor still exposes a percentage-based administrative-fee field and recomputes gross/net. It must be updated as part of the new sponsorship component contract without changing unrelated transaction correction behavior.

5. **Migration execution**
   - Migrations are dated SQL files under `database/migrations/`; the repository has no generic local migration runner and no `database/migrations/README.md`.
   - CI's full-schema job imports `database/ahl_el_kheir.sql` using the MariaDB `mysql` client, then applies dated SQL migration files using the same client. CI tests some migrations for repeatability on disposable MariaDB 10.4.
   - Production preparation says to apply migrations created after the baseline, but does not define the local XAMPP application procedure. Before applying a migration locally, confirm whether the user wants to use phpMyAdmin or the XAMPP MariaDB CLI, take a verified backup, and verify the target database name. Do not invent a migration runner.

### Recommended implementation architecture (subject to current local schema verification)

- Store the agreement per `sponsorship_id + covered_month`, including both the agreed sponsorship amount and agreed service-fee amount. Preserve amendments as new immutable versions rather than overwriting values used by earlier submissions. FM enters the meeting-agreed values; do not invent unilateral FM approval authority.
- Each payment month snapshots its agreement-version ID and both agreed values. Store actual sponsorship received and actual service fee received separately. The recorder can edit only the actual values; agreement values remain read-only.
- For new-model sponsorship rows, the transaction's cash total is the sum of the two actual components. The sponsorship allocation input must be the actual sponsorship component only. Keep the service-fee component separate from legacy `admin_fee_*` fields; do not repurpose those fields as service fee.
- Keep the existing global administrative-fee policy behavior for unrelated transaction types. Use an explicit new-model branch for sponsorship payments; retain a compatibility path for any pre-existing legacy pending sponsorship records until their status and treatment are inspected.
- Use a versioned, effective-dated service-fee account mapping to an active revenue account. The selected mapping version/account must be snapshotted on the transaction at posting and the journal line must use that account ID. A later mapping change must not rewrite any posted journal. Do not expose arbitrary account selection on each payment.
- FM review must display the agreed and actual component breakdown and agreement reference. VGM returned-transaction correction must preserve the same component snapshots. Supervisor approval must use the same component contract while preserving its existing separate review workflow.
- Preserve the existing sponsorship month-end recognition and allocation controls. Do not let the service fee increase the sponsorship allocation cap or net sponsorship received.

### Accounting decisions still required before enabling live posting

1. **Service-fee recognition timing:** the source audit establishes that sponsorship net receipts are deferred and recognized at month-end, while the existing administrative fee is credited immediately. It does not establish whether this new sponsorship service fee is earned immediately on collection or deferred until the covered month. This is a real accounting policy decision, not a coding detail. Obtain the authorized accounting owner's direction. If unavailable, implement data capture and review with posting safely blocked for the service-fee component; do not guess.
2. **Revenue destination:** the user selected a configurable mapping design, but no actual revenue account has been approved. An authorized accounting owner must confirm the valid revenue account before live posting.
3. **Agreement amendments/evidence:** FM enters the meeting-agreed values. The organization still needs to decide what meeting reference/evidence is stored, who authorizes amendments, and whether in-system confirmation is required. Do not create a GM/VGM approval workflow by assumption.
4. **Missing agreement:** recommended safe behavior is to block monthly sponsorship submission for any covered month without a recorded agreement, rather than assume zero or silently use `sponsorships.monthly_amount`/a global policy. Confirm this behavior before implementation.
5. **Existing legacy pending rows:** inspect current `transactions` and `sponsor_payments` before deciding how old pending/returned sponsorship submissions are handled under the new model.

### Next implementation-session gate

Re-read all required canonical docs and the current local `main`; inspect the updated `database/ahl_el_kheir.sql` and live `ahl_el_kheir` schema; inspect the pending legacy sponsorship rows; then begin the smallest justified schema-first implementation. Do not reuse or modify `TR-000023`, `TR-000024`, or `JE-000054`. Do not repeat closed arrears/advances, GM/FM review-role, sponsorship accounting backfill, or ordinary VGM return/resubmission tests absent regression evidence.
