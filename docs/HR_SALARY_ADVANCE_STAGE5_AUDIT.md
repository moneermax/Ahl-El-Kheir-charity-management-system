# HR Salary Advance — Stage 5 Audit & Design Checkpoint

**Date:** 2026-09-29  
**Branch:** `feature/hr-salary-advance-stage5-schedule`  
**Status:** SCHEMA MIGRATION APPLIED — SCHEDULE GENERATION IMPLEMENTED / RUNTIME VERIFICATION PENDING

## 1. Stage boundary

Stages 1–4 are complete and closed.

Stage 5 covers only:

- repayment schedule generation after actual disbursement;
- fixed monthly repayment;
- full eligible salary repayment;
- maximum monthly deduction enforcement;
- repayment start rule;
- insufficient-salary handling;
- payroll integration;
- accounting treatment of payroll repayments;
- employee visibility, notifications, and audit trail for repayments.

Stage 5 does **not** implement:

- direct employee repayment;
- manual settlement;
- full-settlement/early-settlement workflows;
- separation/cancellation exception handling beyond what is required to keep the schedule safe.

Those remain later stages.

## 2. Existing salary-advance policy inputs

The existing policy already contains the fields required for Stage 5:

- `maximum_monthly_deduction`
- `maximum_repayment_months`
- `repayment_start_rule`: `next_payroll` / `specified_month`
- `insufficient_salary_rule`: `available_salary` / `skip_month`
- `eligible_salary_basis`: `net_before_advance` / `gross`
- `allow_fixed_monthly_repayment`
- `allow_full_eligible_salary_repayment`
- `allow_custom_repayment_terms`

The approved request already snapshots:

- `approved_amount`
- `approved_repayment_method`
- `approved_monthly_amount`
- `approved_start_month`

Therefore Stage 5 must calculate from the **approved request snapshot and policy version**, not from today's policy.

## 3. Existing payroll architecture findings

The current payroll system uses:

- `payroll` as the monthly employee payroll record;
- historical salary from `hr_employee_salary_history`;
- `allowances`, `overtime`, and aggregate `deductions`;
- `net_salary`;
- statuses `draft → approved → paid`;
- accounting fields `accounting_status`, `accounting_entry_id`, and `payment_account_id`;
- payroll accounting implemented procedurally in `modules/hr/lib_payroll_accounting.php`;
- payroll journal reference type `payroll`;
- salary expense account `5200`;
- default payroll payment account `1200`.

The current payroll accounting entry is a two-line posting based on `net_salary`:

- Dr 5200 — salaries/wages
- Cr payment account

That means a salary-advance repayment cannot simply be placed into the aggregate payroll deduction field and left to the current accounting routine. Doing so would reduce cash without reducing account 1410.

## 4. Accounting design for salary-advance payroll repayment

For a payroll period with salary-advance repayment amount **A**, the Stage 5 payroll accounting event must include:

- Debit 5200 for the payroll expense amount currently represented by the payroll accounting convention;
- Credit 1410 by **A** to reduce the employee salary-advance receivable;
- Credit the payroll payment account by the actual cash paid after the advance repayment.

The resulting journal must balance.

The implementation must not create a separate expense for the repayment.

The salary-advance repayment is an **asset receivable reduction**, not an expense.

The journal must remain traceable to both:

- the payroll record; and
- the salary-advance repayment allocation(s).

If one payroll period repays more than one salary advance in a future policy configuration, the 1410 credit must be allocated by request rather than posted as an untraceable aggregate. The current active-advance policy normally limits employees to one active advance, but the schema/design should not make the accounting model incapable of explicit allocation.

## 5. Proposed Stage 5 data model

### A. Repayment schedule

Introduce a dedicated schedule table, tentatively:

`hr_salary_advance_repayment_schedule`

One row per planned repayment installment, linked to the salary-advance request.

The schedule should preserve:

- request ID;
- installment number;
- scheduled payroll month;
- scheduled amount;
- applied amount;
- remaining installment amount;
- status;
- applied payroll ID when paid;
- applied timestamp;
- created timestamp.

The schedule is generated only after the request reaches `disbursed`.

It must be based on the approved request terms captured at FM approval.

### B. Payroll repayment allocation

Introduce a dedicated allocation/audit table, tentatively:

`hr_salary_advance_payroll_repayments`

One row per actual payroll deduction allocated to a salary-advance request/schedule.

It should preserve:

- salary-advance request ID;
- schedule row ID;
- payroll ID;
- employee ID;
- eligible salary used for the calculation;
- maximum permitted deduction for that period;
- requested/scheduled amount;
- actual deducted amount;
- skipped/partial reason where applicable;
- accounting journal entry ID;
- created/applied timestamp.

This is preferable to storing the repayment only in `payroll.deductions`, because the latter is an aggregate payroll field and cannot provide a reliable salary-advance audit trail.

### C. Payroll record

Do not overload the existing aggregate `deductions` field as the authoritative salary-advance repayment record.

A dedicated payroll-level salary-advance deduction total may be added if needed for display/calculation efficiency, but the authoritative relationship must remain the repayment-allocation table.

## 6. Repayment calculation rules

### Fixed monthly

For an approved fixed monthly amount:

`planned = min(approved_monthly_amount, outstanding_balance)`

Then apply:

- maximum monthly deduction;
- eligible salary limit;
- remaining balance.

Actual deduction:

`actual = min(planned, maximum_allowed, eligible_salary_available, outstanding_balance)`

The exact eligible-salary calculation must use the approved policy's `eligible_salary_basis` and existing payroll values rather than inventing a second salary definition.

### Full eligible salary

For `full_eligible_salary`:

`planned = eligible_salary_available`

Then cap it by:

- maximum monthly deduction, when configured;
- outstanding balance.

This method must not exceed the actual outstanding receivable.

### Start rule

- `next_payroll`: first eligible payroll period after the approved/disbursed advance according to the approved start rule.
- `specified_month`: begin in the approved `approved_start_month`.

The schedule must not silently reinterpret a previously approved start month when a later policy version is created.

## 7. Insufficient-salary handling

The approved policy determines the behavior.

### `available_salary`

Deduct the maximum amount actually permitted by the eligible salary available for that payroll period.

The unpaid remainder stays outstanding and moves forward.

### `skip_month`

No salary-advance repayment is taken from that payroll period.

The schedule remains outstanding and records the skip reason.

No balance reduction occurs when the actual deduction is zero.

## 8. Maximum repayment months

For fixed monthly repayment, Stage 5 must validate the generated schedule against:

- approved amount;
- approved monthly amount;
- maximum monthly deduction;
- maximum repayment months.

The schedule must never generate installments that exceed the outstanding balance.

A schedule reaching zero must terminate cleanly and mark the salary advance as settled only through the later settlement lifecycle; Stage 5 must not introduce a second settlement mechanism.

## 9. Payroll lifecycle integration

The intended flow is:

`disbursed`
→ schedule generated
→ eligible payroll reaches period
→ repayment amount calculated
→ payroll draft reflects salary-advance repayment
→ payroll approved
→ payroll paid
→ accounting journal includes Cr 1410
→ repayment allocation marked applied
→ salary-advance outstanding balance reduced.

The repayment allocation and accounting posting must be transactional so that a payroll payment cannot claim a repayment that was not actually posted.

A failed accounting post must not reduce the salary-advance outstanding balance.

A failed repayment allocation must not leave a payroll record apparently paid with an unrecorded receivable reduction.

## 10. Payroll edit/approval boundary

Salary-advance repayment should be system-generated from the approved schedule.

Manual payroll editing must not silently erase or alter a generated salary-advance deduction.

If the payroll is still `draft`, the system may recalculate the salary-advance component when the payroll is regenerated, provided the repayment has not already been posted.

Once payroll becomes `approved` or `paid`, the salary-advance allocation for that payroll period must be immutable.

Corrections after payment belong to the existing payroll reversal/correction mechanisms and must not directly rewrite the salary-advance history.

## 11. Role/action matrix

| Role | Stage 5 responsibility |
|---|---|
| Employee | View own approved/disbursed advance, repayment schedule, deductions, and outstanding balance. No manual repayment alteration. |
| FM / Financial Manager | Owns salary-advance approval terms; may view repayment status and audit information. No editing of posted payroll repayment. |
| HR Manager / HR Staff | Operate the existing payroll lifecycle. System applies the salary-advance repayment automatically; no manual override that bypasses the approved schedule. |
| Accountant Staff | Existing authorized accounting work; can view accounting evidence according to current salary-advance authorization. |
| Admin | Existing privileged access. |
| System | Generate schedule, calculate eligible deduction, create allocation, update outstanding balance, and attach accounting evidence transactionally. |

No new role is required.

## 12. Notifications

Stage 5 should provide notifications at meaningful lifecycle points:

1. schedule created after disbursement;
2. repayment successfully applied to payroll;
3. partial repayment/insufficient salary;
4. skipped repayment where policy requires skip;
5. balance reaches zero / repayment completion event.

Notifications must not be used as the source of truth; the repayment tables and accounting journal remain authoritative.

## 13. Audit requirements

Every material Stage 5 action must be auditable:

- schedule generation;
- repayment calculation/application;
- skipped month;
- partial repayment;
- balance reduction;
- payroll association;
- accounting journal association.

The audit must preserve old/new values where a mutable record is changed.

No repayment should commit without its required audit/trace record.

## 14. Implementation order

Stage 5 should be implemented in these controlled units:

1. **Schema migration**
   - repayment schedule;
   - payroll repayment allocation;
   - required payroll linkage only.
2. **Schedule generation**
   - create schedule after disbursement;
   - verify fixed monthly and full eligible salary calculations.
3. **Payroll draft integration**
   - calculate and display salary-advance deduction separately;
   - preserve aggregate payroll deductions correctly.
4. **Payroll accounting integration**
   - post the 1410 repayment leg;
   - preserve balanced journals and existing payroll behavior.
5. **Balance/application transaction**
   - reduce outstanding balance only after successful payroll repayment posting;
   - mark schedule/allocation applied.
6. **Notifications and audit**
7. **Runtime verification**
   - fixed monthly;
   - full eligible salary;
   - maximum deduction;
   - insufficient salary / available-salary;
   - insufficient salary / skip-month;
   - start rule;
   - no deduction after balance reaches zero;
   - journal balance and Cr 1410;
   - no duplicate allocation on the same payroll/request;
   - payroll draft regeneration safety;
   - employee visibility.

## 15. Explicit non-goals

Do not implement in Stage 5:

- direct repayment;
- manual settlement;
- early settlement;
- cash/direct repayment receipt;
- employee separation settlement;
- cancellation after disbursement;
- generic payroll-accounting redesign unrelated to salary advances;
- triggers;
- views;
- stored procedures/functions/events;
- runtime DDL.

## 16. Current decision

**Stage 5 design/audit is complete, the schema checkpoint is applied, and the schedule-generation implementation is ready for runtime verification.**

Schedule generation is the first application-code checkpoint. It is implemented in `modules/hr/lib_salary_advance_repayment.php` and is invoked atomically from the existing salary-advance disbursement workflow in `modules/hr/lib_salary_advance_accounting.php`.

No payroll deduction, payroll accounting, outstanding-balance reduction, or repayment-allocation application has been implemented yet.


## 17. Schema checkpoint

The first Stage 5 implementation unit is prepared in:

`database/migrations/2026-09-29_hr_salary_advance_repayment.sql`

It adds:

- `payroll.salary_advance_deduction` for the period-level salary-advance deduction total;
- `hr_salary_advance_repayment_schedule` for planned installments;
- `hr_salary_advance_payroll_repayments` for payroll-period allocation/audit trace.

No existing payroll values are changed by the migration because the new payroll field defaults to zero.

**The migration was applied successfully on 2026-09-29 with all 5 queries executing without errors.** The new payroll column and both repayment tables are now present, and the new payroll field defaults to zero for existing records.

### 17.1 Schedule-generation implementation

The current checkpoint implements only schedule generation:

- schedule creation is allowed only for a request whose status is `disbursed`;
- the approved request snapshot and its saved policy version are used;
- `next_payroll` starts from the first payroll month after the actual disbursement month;
- `specified_month` uses the approved start month, but never schedules before the first payroll month after disbursement;
- fixed-monthly schedules use the approved monthly amount, capped by the saved maximum monthly deduction, and terminate at the exact outstanding balance;
- fixed-monthly generation refuses a schedule that would exceed the saved maximum repayment months;
- full-eligible-salary schedules require a saved maximum repayment-month horizon and use that horizon plus the saved maximum monthly deduction as the planned schedule ceiling; actual eligible salary is intentionally deferred to payroll integration;
- duplicate generation is idempotent: an existing schedule is not generated again;
- schedule creation occurs inside the existing disbursement transaction, so a schedule-generation failure rolls back the disbursement rather than leaving an unscheduled receivable;
- a schedule-generation audit record is written when a user ID is available.

### 17.2 Explicit current non-goals

This checkpoint does **not** yet:

- calculate actual payroll eligible salary;
- write `payroll.salary_advance_deduction`;
- create `hr_salary_advance_payroll_repayments` allocation rows;
- reduce `hr_salary_advance_requests.outstanding_balance`;
- post the payroll Cr 1410 accounting leg;
- send repayment notifications.

Those remain the next controlled Stage 5 units after schedule-generation runtime verification.

### 17.3 Runtime verification required

Before merging/closing this checkpoint, runtime-test at least:

1. a fresh fixed-monthly salary advance that is disbursed successfully;
2. generated schedule row count and months;
3. installment amounts never exceeding outstanding balance;
4. maximum monthly deduction enforcement;
5. maximum repayment-month enforcement;
6. `next_payroll` start rule;
7. `specified_month` start rule;
8. duplicate schedule-generation protection;
9. full-eligible-salary schedule behavior when a maximum repayment-month horizon is configured;
10. failure safety: schedule-generation failure must not leave a disbursed request without the required schedule.

No Stage 6 direct repayment/settlement behavior is part of this checkpoint.
