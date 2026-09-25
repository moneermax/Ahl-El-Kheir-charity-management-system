# Projects Module — Deep Audit and Remediation Plan

**Date:** 2026-09-21  
**Repository:** moneermax/Ahl-El-Kheir-charity-management-system  
**Branch:** main

## Purpose

This document is the dedicated execution plan for the deep audit of the Organization Projects module. It records findings, verification requirements, proposed fixes, and the controlled implementation order.

## Scope

Primary surfaces:
- dashboard/projects_dashboard.php
- modules/projects/index.php
- modules/projects/form.php
- modules/projects/view.php
- modules/projects/project_lib.php
- modules/projects/serve_project_document.php

Related workflows:
- creation/editing
- lifecycle/status changes
- approval
- supervisor/team assignment
- budgets/budget lines
- funding allocations
- expenses/journals/reversals
- milestones
- progress
- labor/helpers
- beneficiaries
- documents
- closure/reopening
- dashboard/list/detail navigation
- role authorization
- Arabic/English UI
- financial totals/reconciliation

## First-pass findings

### High priority
1. New-project creation is a multi-table write sequence without one enclosing transaction. A failure after an earlier insert can leave partial project state.
2. modules/projects/index.php calculates posted allocation totals differently from project_lib.php. project_lib.php excludes allocation rows already represented by a posted transaction; the portfolio query does not. This can produce inconsistent funded totals.

### Medium priority
3. modules/projects/view.php performs lifecycle creation/synchronization and closure-total updates during GET rendering. A read can mutate project_lifecycle.
4. Project-code generation uses a count-based sequence. Uniqueness, deletion, and concurrency behavior must be verified before deciding on a replacement.
5. General editing permits important financial/project-basis fields to change after workflow activity. The intended business rule must be established before imposing restrictions.
6. Multiple POST handlers need a complete server-side validation audit instead of relying on HTML/JavaScript constraints.
7. Project document upload needs an explicit application-level file-size limit consistent with the application's deployment policy.
8. Rejection/return actions and required reasons need field-by-field server-side validation review.
9. Budget/funding/approval multi-step mutations need explicit transaction-boundary review.
10. Project expense separation-of-duties must be verified against the intended role matrix before authorization changes are made.

## Mandatory constraints

- Inspect the actual schema before every SQL change; never invent tables/columns.
- No database triggers, views, stored procedures, functions, or events.
- Do not rename technical project_* identifiers merely for UI branding.
- Preserve existing audit/test evidence unless genuine regression evidence requires otherwise.
- Do not recreate old fixtures unnecessarily.
- Preserve current Back/sidebar/header behavior unless a Projects-specific regression is demonstrated.
- Preserve existing accounting journal semantics.
- Authorization changes require evidence from the current role/business model.
- Fix the smallest safe unit and verify it before proceeding.
- Do not mark a workflow complete until runtime behavior is verified.

## Execution order

### Phase 1 — Static completeness
- Inventory every Projects HTML page and endpoint.
- Trace every POST action to its database mutations.
- Map role permissions for view/create/edit/approve/finance/close/reopen/document/team actions.
- Verify every referenced table, column, status, and relationship against the actual schema.
- Compare financial calculations across portfolio, detail, dashboard, and accounting integration.
- Audit CSRF, validation, uploads, redirects, Back controls, and failure paths.

### Phase 2 — Data-integrity fixes
- Add a transaction around project creation after all involved schema/constraint checks.
- Unify portfolio/detail funding calculations around one authoritative calculation.
- Remove read-time lifecycle writes from view.php by moving synchronization into appropriate write workflows without changing intended business outcomes.
- Verify project-code generation and uniqueness before changing it.
- Review budget/funding/approval transaction boundaries.

### Phase 3 — Validation and authorization hardening
- Server-side allow-lists and numeric/range validation for every project POST action.
- Verify closed-project mutation rules.
- Verify supervisor/team section scope.
- Verify executive/project-manager/accounting role boundaries.
- Verify expense approval/posting separation of duties.
- Verify document size, MIME, filename/path handling, and rejection reasons.

### Phase 4 — Runtime workflow
Use existing development/test data wherever it can exercise the workflow. Create new controlled data only where necessary.

Minimum flow:
Create → save → view → edit → submit/approve → assign → budget → funding → expense → milestone/progress/labor → document → status changes → close → permitted reopen → final totals.

Also test:
- unauthorized direct URLs;
- invalid POST values;
- duplicate submissions;
- failed/partial operations;
- Back navigation;
- dashboard/list/detail consistency.

### Phase 5 — Accounting reconciliation
For projects with financial activity verify:
- portfolio and detail funding totals agree;
- approved budget agrees with budget lines;
- posted project expenses agree with journal entries;
- reversals do not duplicate totals;
- residual and variance use the same source calculation;
- Projects changes do not cross Fina/Ahl treasury boundaries.

### Phase 6 — Documentation and acceptance
After each meaningful fix:
1. commit code;
2. runtime-test the changed workflow;
3. record exact result and commit;
4. update the master audit;
5. update master status/session index when the continuation point changes.

## Proposed target architecture

Prefer centralized procedural helpers in project_lib.php over duplicated SQL.

Target:
- one authoritative project-total calculation;
- one authoritative role/section authorization layer;
- explicit transaction boundaries around multi-table business operations;
- read-only GET pages;
- server-side validation independent of browser controls;
- immutable/auditable financial history;
- no schema-side automation.

## Current status

Static first-pass audit: **IN PROGRESS**  
Projects code changes from this audit: **NONE YET**  
Runtime Projects workflow certification: **NOT YET STARTED**  
Next action: continue the page-by-page static scan and schema/reference verification, then implement the first smallest safe fix batch.


## 2026-09-21 — First remediation pass completed

The first targeted fixes were applied after the initial static scan:

1. **Atomic project creation** — new-project creation in `modules/projects/form.php` now runs inside a PDO transaction and rolls back the complete creation sequence if a later insert/update fails. Existing edit behavior is not wrapped in this creation-only transaction.
2. **Portfolio funding reconciliation** — `modules/projects/index.php` now applies the same posted-allocation exclusion rule used by `akp_project_totals()`, preventing an allocation already represented by a posted transaction from being counted twice in portfolio totals.
3. **Read-only project view** — `modules/projects/view.php` no longer synchronizes lifecycle rows or closure totals merely because a user opens the project page. Totals are calculated from current source data; closure synchronization remains an explicit closure workflow operation.
4. **Server-side workflow validation** — team section allow-list, labor timing/status allow-lists, progress percentage bounds, beneficiary required/non-negative values, document rejection reason, and a 10 MB project-document application limit were added.
5. **Closure validation order** — closure summary is validated before closure totals are synchronized.

Commits:
- `251c7c4933cb9f223f1b2b6d509c09e5e2c929d0` — Make project creation atomic
- `5bb36f8b87be644ac35fddf9b02e2f1f80b98432` — Align project portfolio funding totals
- `af59a1bce2a2ce46fa4b37ae44e5eb251f6b266f` — Harden project workflow validation

**Not yet certified:** project-code uniqueness/concurrency still requires actual production schema verification; full runtime workflow testing is also still pending.

## 2026-09-21 — Project submission notification gap identified and fixed

The Project Manager → FM submission workflow was working at the business-state level, but it did not notify the Financial Manager when `project_approval.approval_status` changed from `draft/rejected` to `submitted`.

### Root cause

`modules/projects/view.php` performed the approval-state update and audit logging but contained no system-notification writer in the `submit_project` handler.

### Existing notification mechanism used

No new notification table, column, or notification system was introduced. The fix reuses the existing event-aware helper:

`modules/accounting/lib_transaction_review.php::ak_transaction_review_notify_fm_event()`

That helper already:
- selects active users by the existing Financial Manager role codes (`financial_manager`, `fm`, `finance`);
- supports workflow reference ID/type when the installed notification schema supports them;
- falls back to the existing notification fields when reference columns are unavailable;
- suppresses an equivalent unread notification to avoid duplicate delivery;
- isolates notification failures so a completed business transition is not rolled back.

### Implementation

After a successful project submission state update and audit entry, `view.php` now sends:
- reference ID: project ID;
- reference type: `project_submission`;
- title: `مشروع بانتظار المراجعة المالية`;
- body containing the project name and project code;
- link to the existing project view, which already exposes the FM review controls when the project is `submitted`.

Code commit: `951dec73f88326f8b516dca7b5e4732329d53de7`.

### Runtime verification required

The remaining acceptance step is local XAMPP/browser verification: submit a project as Project Manager, confirm `submitted`, confirm the active FM recipient sees the notification in the existing bell/page UI, open it and verify the project destination, then verify refresh/reload does not create duplicates and that a rejection/resubmission behaves according to the existing unread/read deduplication rule.

No runtime result is recorded here until that local test is actually performed.


## 2026-09-21 — FM → GM project approval notification fixed

A second Projects notification gap was identified during runtime testing. After the Financial Manager changed a project's approval state from `submitted` to `fm_approved`, the business workflow completed correctly but the General Manager was not notified.

### Implementation

The smallest safe fix was applied in `modules/projects/view.php`:
- reuse the existing notification writer `ak_transaction_review_notify_event()`;
- select only active users with the existing `general_manager` role code;
- send the project name and project code with the message that the project was financially approved and is awaiting final GM approval;
- link directly to the existing project view;
- use project ID + reference type `project_fm_approval` for event-aware deduplication;
- isolate notification failures so the completed FM approval is never rolled back.

No notification table, column, helper system, or database change was introduced.

Code commit: `b7a655512c1234fb1459c068aba07e494355f023` — **Notify GM when project is financially approved**.

### Runtime verification status

Not yet runtime-tested. The next controlled test is Project 6: after pulling the commit, verify that FM approval succeeds and the active General Manager receives and can open the notification. Do not repeat the already-passed Project Manager → FM notification test unless a regression is observed.

## 2026-09-22 — Projects Module Working Plan: Complete Workflow and Role Audit

The Projects Module is now explicitly under a **complete workflow and role audit**. This supersedes treating individual UI/permission defects as isolated fixes. The objective is a reliable end-to-end project workflow with clear separation of duties, correct lifecycle timing, and correct financial/accounting behavior.

### Authoritative role boundaries for this audit

**Project Supervisor**
- Enters project and operational data.
- Maintains operational planning and execution information.
- Adds/follows operational progress, beneficiaries, external labor, milestones, and supporting documents as permitted by the workflow.

**Projects Manager**
- Reviews/follows up Project Supervisor work.
- Manages project workflow/readiness.
- Prepares and submits the project for financial review.
- Does not replace FM financial approval authority.

**Financial Manager (FM)**
- Financial review/control role at the approval stage.
- Reviews approved budget, funding allocation, financial warnings, and relevant supporting information.
- Approves or rejects the project financially.
- Must not enter operational project data.
- Operational sections in FM view should be read-only or presented as concise quick-report summaries.
- After final project approval, performs authorized project payment/disbursement actions when the workflow requires FM execution and prints payment receipts where applicable, especially for cash payments.
- Payment execution must remain distinct from operational data entry and from approval authority where separation of duties requires it.

**GM/VGM**
- Final project approval according to the existing authority rules.

**Accountant**
- Accounting execution/posting responsibilities where the existing accounting workflow assigns them.

### Required audit model

Every Projects section/action must be classified as one of:

A. **Pre-approval preparation**
B. **Approval / financial review**
C. **Post-approval execution**
D. **Management / reporting**

For every page and POST action, verify both:
- server-side authorization and lifecycle guards; and
- frontend visibility/editability/read-only presentation.

### Target FM experience

Before final approval, FM should have a **financial review/control view**, not an operational data-entry workspace. It should expose, as appropriate:
- project summary;
- approved budget and budget lines;
- funding allocations by source;
- total funded amount and remaining amount;
- financial warnings/validation state;
- relevant project documents as read-only;
- concise operational summaries as read-only;
- approval history;
- clear financial Approve / Reject actions.

After final approval, FM should have a **financial project execution view** where applicable:
- approved budget;
- funded amount;
- paid amount;
- actual expenses;
- remaining budget;
- pending payments;
- payment history;
- financial documents;
- authorized payment/disbursement actions;
- receipt/print actions where required.

### Required audit questions

1. Which project pages/endpoints exist and what does each POST action mutate?
2. Which roles can view, create, edit, approve, reject, fund, spend, close, reopen, upload/verify documents, manage teams, and execute payments?
3. Which sections should be Supervisor-editable, Projects-Manager-editable, FM read-only, FM financial-actionable, GM/VGM review-only, or Accountant-controlled?
4. Are lifecycle states enforcing the same separation of duties on the server as the UI suggests?
5. Are operational forms accidentally exposed to FM or other financial roles?
6. Is expense entry distinct from payment/disbursement execution and accounting posting?
7. Is the final-approval journal an actual payment/expenditure event or a funding/allocation/reservation event? These meanings must not be conflated.
8. Could final approval and later expense/payment posting recognize the same financial event twice?
9. Are project notifications routed to the correct role at each transition?
10. Do dashboards, lists, detail pages, direct URLs, and POST endpoints all enforce the same workflow rules?
11. Can an FM payment/receipt action occur only after the project is in the appropriate approved lifecycle state and only for an authorized financial event?
12. Are audit logs and accounting references preserved throughout the workflow?

### Implementation rule

Do **not** implement role changes from assumptions. First complete the repository/page/schema/reference audit and produce the role matrix and section-visibility matrix. Then implement the smallest safe changes in controlled batches, with runtime verification after each batch.

Do not create new schema objects, triggers, views, stored procedures, functions, or events. Do not alter existing accounting test evidence merely to make the new workflow appear correct.

### Current status

**Working plan accepted — audit/design phase.**
No implementation should begin from this plan until the next session completes the requested static audit and confirms the proposed target workflow against the actual repository code and schema.


## 2026-09-22 — Remediation Batch 1: Separate pre-approval financial preparation from FM review

The first controlled workflow-boundary change from the accepted Projects working plan is implemented.

### Implemented boundary

Before a project is submitted to the FM:

- **Projects Manager** is the server-authorized role for preparing the project budget, budget lines, and pre-approval funding allocations.
- Budget preparation/approval and budget-line add/edit/delete handlers now use the dedicated `akp_can_prepare_finance()` guard.
- Pre-approval funding creation now requires the Projects Manager and an approval state of `draft` or `rejected`.
- The previous FM funding-entry form during `submitted` review has been removed from the actionable UI and replaced with a read-only explanation.
- FM therefore receives the prepared financial package for review instead of entering the package during financial review.
- The existing final-approval/post-approval funding correction path for the General Manager was not removed in this batch because its accounting implications require the separate accounting-event decision.

### Code changes

- `modules/projects/project_lib.php`
  - added `akp_can_prepare_finance()`;
  - this helper is deliberately limited to `projects_manager` and respects closed projects.
- `modules/projects/view.php`
  - moved budget preparation mutations to the new preparation guard;
  - moved pre-approval funding creation to the Projects Manager and `draft/rejected` states;
  - moved budget preparation UI to the same guard;
  - removed FM's pre-approval funding-entry controls and replaced them with read-only review text.

Commits:
- `94e31c6a9ed41ef0d8d952f7ffab8ea5af6113a9` — Separate project financial preparation from FM review authority
- `f385d36bd29ec6e8771282e7b39238b9ab085c91` — Move project pre-approval budget and funding preparation to Projects Manager
- `04a45fd0ab802dc72ec1adc728c7c39214cc1692` — Enforce pre-review project funding preparation boundary

### Deliberately not changed in this batch

- Project expense entry/approval/posting.
- Post-approval payment/disbursement architecture.
- Final-approval project journal semantics.
- Post-approval funding correction/reversal behavior.
- GM/VGM final approval.
- Project notification routing beyond the already identified gaps.

Those remain separate audit items because changing them without resolving the accounting event and payment boundary could create duplicate or incorrectly timed accounting recognition.

### Verification status

Static repository verification confirms the new guard is used by the targeted budget and pre-approval funding mutations and that the FM pre-approval funding form is no longer actionable.

**Runtime verification: NOT YET PERFORMED.**

The next local test must first verify the Project Manager can prepare/modify budget and funding while the project is `draft`/returned, then submit it, and verify the FM sees the package as read-only financial review data with Approve/Reject controls and no budget/funding entry controls.




## 2026-09-22 — Projects UI design implementation batch

The Projects UI is now being aligned with the approved visual reference artifacts:
- `docs/code_artifact.html` is the visual/UI reference.
- `docs/code_artifact.md` is the functional/product reference.
- The implementation remains Bootstrap-based to preserve the existing application shell; the reference's visual language is adapted rather than replacing the system-wide framework.

### Implemented in this batch

- Added scoped shared Projects styling in `assets/css/projects-ui.css`.
- Updated `modules/projects/form.php` with:
  - reference-style project banner and section cards;
  - clearer field hierarchy and rounded form controls;
  - dynamic project-type guidance using the existing `project_type` field;
  - category-aware guidance for orphan, water, food/relief, economic, and custom projects;
  - budget template helper that adds descriptive budget lines without inventing monetary values;
  - preserved server-side budget equality validation.
- Updated `modules/projects/view.php` so existing workflow forms use the same visual language, while retaining their current POST actions and authorization conditions.
- Updated `modules/projects/index.php` so the project portfolio/listing uses the same visual language.
- No new database columns, tables, triggers, views, or runtime DDL were introduced.

### Important workflow boundary discovered during UI review

The user explicitly clarified that **funding entry/allocation is not a Projects Manager task**. Therefore the UI must not expose a funding-entry form to the Projects Manager merely because a project is in a preparation state.

The current code still contains a deeper pre-approval funding workflow contradiction: FM financial approval requires draft funding allocations to equal the approved budget, while the current role ownership of creating those allocations is not yet established by the authoritative workflow rules. This must be resolved before assigning the funding-entry form to any role. Do not infer the owner from the prototype or invent a new role responsibility.

### Verification status

Static repository checks completed:
- project form/view/index form tag counts remain balanced;
- the shared Projects stylesheet is loaded by all three user-facing Projects pages;
- existing POST actions were preserved in this UI batch.

Runtime visual verification is **pending**.


### 2026-09-22 — Operational data-entry boundary clarification
- Project Supervisor is the operational data-entry owner for project documents/receipts/certificates, external labor/helpers, milestones, and progress updates.
- Projects Manager follows up and manages workflow but must see these sections as read-only and must not add or edit their operational records.
- The implementation now enforces this boundary server-side for the `operations` and `documents` sections; the Project Manager labor-comment action is also no longer available.
- This clarification does not assign ownership of pre-approval funding entry; that ownership remains unresolved and must not be inferred.


## 2026-09-22 — Budget view UI clarification

A targeted UI cleanup was applied to `modules/projects/view.php` after review of the project detail page:

- Removed the inline **إضافة بند** budget-line entry fields from the project detail page. Budget-line preparation/editing belongs in the project editing/preparation flow rather than being duplicated inside the read/review page.
- Renamed the remaining budget-version action from the ambiguous **اعتماد** to **اعتماد نسخة الميزانية** so it cannot be confused with FM's separate **اعتماد مالي** project approval.
- Added a short explanatory note distinguishing budget-version preparation/approval from the project's financial approval.
- No POST action, database schema, authorization rule, funding ownership, accounting behavior, or lifecycle state was changed in this UI-only correction.

Implementation commit: `85dfa3f65725ade865be80824a7d340cdcbcf1db`.

Runtime verification is required after pulling the commit.

## 2026-09-22 — Budget view correction after workflow review

Follow-up review showed that the budget-version **اعتماد** control should not remain on the project detail page because it introduced a second approval concept alongside the FM project financial approval. The explanatory note was also unnecessary on the user-facing page.

The detail page was corrected to:
- remove the explanatory budget note;
- remove the **اعتماد نسخة الميزانية** button;
- remove the obsolete `approve_budget` POST handler from `modules/projects/view.php`.

This is a UI/workflow-boundary cleanup. No database schema or accounting entries were changed. Budget preparation/editing remains outside this detail-page review surface.

## 2026-09-22 — Projects dashboard audit correction

The Projects Manager dashboard was reviewed against the authoritative project totals. The `active_budget` statistic was using each active/reopened project's `target_amount` even when an approved budget existed. This could make the dashboard disagree with the Projects portfolio, which uses the approved budget when available.

The dashboard statistic now uses the approved budget total for active/reopened projects, falling back to `target_amount` only when no approved budget exists. No workflow, authorization, database schema, or accounting behavior was changed.

Implementation commit: `561c6d9f49ee5381e8846490ca57f5eb5b6b1816`.
Runtime verification remains pending.

## 2026-09-22 — Project form repeatable detail fields

The project creation/edit form was expanded to support repeatable planning/detail records instead of forcing multiple values into single text fields:
- **المتطلبات الحكومية الأولية** is now a repeatable requirement + individual **الرسوم الحكومية (SDG)** pair, so each fee remains attached to its requirement.
- **الجهة المنفذة أو الشركاء** is repeatable with a role/participation field.
- **طرق الشراء أو التوريد** is repeatable with notes.
- **بيانات الاتصال** is repeatable with name, role, phone, email, and notes.
- Each group uses an explicit **إضافة** button; users can add or remove rows as needed.
- New child tables are introduced through `database/migrations/2026-09-22_project_repeatable_details.sql`; no triggers or views are used.
- Legacy single-value `project_details` fields remain populated for backward compatibility, and existing newline-separated values are backfilled into the new tables.

Implementation commits: `8f7274ed65202ad34d3dd305a6e8dce130a25125` (migration) and `06af8a25e460194ddef392ceb21f43b76e0636e1` / `daae330a0d4ede9c04d128cf81a8c2536e47111a` (form handling/UI).
Runtime verification and migration execution are still pending locally.


## 2026-09-22 — Repeatable project details added to project view

The project detail page was audited after the repeatable form fields were introduced. The edit flow already loaded, validated, deleted, and reinserted the repeatable records correctly; the missing piece was read-only display on `modules/projects/view.php`.

The project view now reads and displays:
- each government requirement with its associated fee and a total of the listed government fees;
- all implementing partners with their roles;
- all procurement methods with their notes;
- all contact records with role, phone, email, and notes.

The existing legacy **الشريك المنفذ** summary remains intact for compatibility. No POST action, authorization rule, schema change, trigger, view, or runtime DDL was added.

Implementation commit: `9cff782107e4c04abe7803d7d84830930dcc933b`.

The repeatable-details migration has been applied to the local database. Runtime UI verification of both edit and view pages remains the next required test.


### 2026-09-22 — Project form category selector and budget activation guard
- Improved **تصنيف استرشادي** as a clearly identifiable dropdown and expanded the dynamic-template categories to include education/training, health/medical care, housing/rehabilitation, seasonal projects, plus **أخرى / مشروع مخصص** for projects outside the predefined categories.
- Dynamic budget templates now have matching category guidance/template definitions; the custom category remains available for non-listed project types.
- **الميزانية التقديرية** must be greater than zero before the budget template loader, budget-line fields, and add-line action become active.
- A zero or empty estimated budget keeps those budget-dependent controls locked and prevents saving until a positive budget is entered and the budget-line total matches it.
- No schema change, runtime DDL, trigger, or view was added.


## 2026-09-23 — Post-GM project payment evidence workflow

The Projects payment boundary is now defined around the existing final-approval accounting event:

### Accounting/payment boundary

- GM/VGM final approval remains the point at which the project funding release is posted to the ledger through the existing project approval journal.
- No second accounting journal is created when payment evidence is documented.
- The new payment-evidence records are documentary records tied to the approved funding allocation and the GM approval journal.
- The existing FM funding-allocation **المرجع** field is no longer exposed in the dedicated FM funding UI. Existing database values are preserved.

### Post-GM evidence by source

For each approved funding allocation, the system derives the payment evidence method from the source account:

- 1100 — **نقدي**: FM confirms the cash payment evidence and can print the existing bilingual outgoing payment-voucher design through the existing voucher-print surface.
- 1200 — **تحويل بنكي**: FM attaches the transfer receipt and may record the bank transaction reference.
- 1300 — **محفظة إلكترونية**: FM attaches the wallet payment receipt and may record the transaction reference.

These controls are only actionable after project_approval.approval_status = 'approved'.

### Projects Manager visibility

After GM/VGM final approval, the Projects Manager's project view shows the same payment evidence status and allows read-only access to:
- the printed cash payment voucher;
- the bank-transfer receipt;
- the e-wallet receipt.

The PM does not issue the payment, upload FM evidence, or create an accounting entry.

### Schema

Migration added:

- database/migrations/2026-09-23_project_payment_evidence.sql
- table: project_payment_evidence

The table stores one documentary evidence record per funding allocation, including payment method, amount, source account, GM approval journal reference, status, reference number, and receipt metadata.

No triggers, views, stored procedures, events, or request-time schema creation were introduced.

### Implementation

- modules/projects/project_lib.php — payment-method mapping for funding source accounts.
- modules/projects/view.php — creates documentary payment-evidence rows during GM final approval and displays them to project viewers/PM.
- modules/projects/view_fm.php — FM-only post-GM cash-voucher confirmation and bank/e-wallet receipt upload.
- modules/projects/project_payment_receipt.php — authenticated receipt viewer.
- modules/accounting/voucher_print.php — reuses the existing outgoing payment-voucher print design for project cash-payment evidence.

### Verification status

**Runtime verification is required after applying the migration.**

Controlled test order:
1. Prepare a project with an approved budget and funding allocation.
2. Verify the FM funding page no longer contains the funding **المرجع** field.
3. Verify the project cannot expose payment-evidence actions before GM/VGM final approval.
4. Complete GM/VGM final approval and verify payment-evidence rows are created for each funding source.
5. For a cash source, FM confirms the cash payment evidence and prints the outgoing voucher; verify the existing voucher design and project data.
6. For bank/wallet sources, FM uploads a PDF/JPG/PNG receipt and optional transaction reference; verify the authenticated receipt viewer.
7. Open the PM project view and verify the PM can see the documented evidence but cannot modify it.
8. Verify no second journal is created when the voucher is printed or a receipt is uploaded.


## 2026-09-24 — FM payment-evidence completion workflow

The post-GM **صرف وتمييز مستندات التمويل** stage was refined without changing the accounting boundary.

### Implemented behavior

- FM cash-payment confirmation and bank/e-wallet receipt upload now support an asynchronous submission path so a successful operation does **not** reload the page or jump the user to the top.
- Successful payment-evidence actions show an inline green check/tick (**تم التوثيق**) in the relevant payment row.
- Added an FM-only **تعديل** action for documented payment evidence before the final completion confirmation:
  - cash: correct the payment date;
  - bank/e-wallet: correct the payment date/reference and optionally replace the receipt file.
- Added a final **تأكيد اكتمال مستندات التمويل** action.
- The final confirmation is allowed only when every project_payment_evidence row for the approved project is documented.
- Final confirmation records an audit event and sends an event-aware notification to active Projects Manager users.
- After final confirmation, the payment-evidence stage is treated as locked; the FM edit controls are no longer exposed.
- The final confirmation and payment-evidence documentation actions do **not** create a second accounting journal. The GM final-approval journal remains the accounting release event.
- No new table, column, trigger, view, or runtime DDL was introduced.

### Verification status

**Runtime verification pending.**

Controlled test should verify:
1. Upload a bank/e-wallet receipt and confirm the page does not reload/jump to the top; the row shows the green check.
2. Confirm cash evidence and verify the same inline check behavior.
3. Use **تعديل** on a documented row and verify the corrected date/reference/receipt appears without a page reload.
4. Leave one payment evidence row pending and verify final confirmation is blocked.
5. Document all payment rows and click **تأكيد اكتمال مستندات التمويل**.
6. Verify the FM sees the final-completion check and edit controls are removed.
7. Verify the Projects Manager receives the final payment-evidence notification and can open the project evidence read-only.
8. Verify no second journal entry is created by documentation, editing, or final confirmation.

## 2026-09-24 — Project Approval Notification Workflow Correction

The approval/rejection notification chain was re-aligned to the required business workflow.

### Required approval path

```
Projects Manager
  → submit / resubmit
Financial Manager
  → approve financially
General Manager / VGM
  → approve finally
Projects Manager
  ← final approval notification
```

### Required rejection path

```
General Manager / VGM
  → reject
Financial Manager
  ← GM rejection notification / review
  → reject financially
Projects Manager
  ← final FM rejection notification
  → edit + resubmit OR close project as rejected
```

This means a GM rejection is **not** a terminal rejection and must not notify the Projects Manager directly. It returns the approval state to `submitted` so the FM can perform the financial rejection step. The existing FM rejection then moves the state to `rejected` and notifies the Projects Manager.

### Changes implemented

1. Final GM approval now sends an event-aware notification to active Projects Manager users using reference type `project_final_approval`.
2. GM rejection now changes `fm_approved → submitted`, preserves the GM rejection reason in `rejection_reason`, and sends an event-aware notification to active FM users using reference type `project_gm_rejection`.
3. The obsolete direct GM-rejection → Projects Manager notification path was removed from `akp_audit()`.
4. FM rejection remains the terminal rejection transition for this approval cycle: `submitted → rejected`, with the existing Projects Manager notification using `project_fm_rejection`.
5. Existing notification infrastructure, role-code resolution, deduplication, CSRF, and notification-failure isolation were reused; no schema change was introduced.

Commits:
- `4d985d094f7bde4d6faa503fa704db55457b9e04` — Notify Projects Manager after final project approval
- `f0c37431ce67029b36defb7132e43db2c024fbe4` — Route GM project rejection back through FM
- `58f7c62f6df05f98da7d003fe470c0e616a41eb9` — Remove obsolete final-rejection notification route

### Runtime verification required

Use the existing controlled project test data. Do not create a new project merely for this audit.

Approval case:
1. PM submits/resubmits → approval status `submitted` → FM receives notification.
2. FM approves → `fm_approved` → GM/VGM receives notification.
3. GM approves → `approved` → PM receives final-approval notification.

Rejection case:
1. PM submits → `submitted` → FM receives notification.
2. FM approves → `fm_approved` → GM/VGM receives notification.
3. GM rejects with reason → `submitted` → FM receives GM-rejection notification; PM must not receive a terminal rejection notification at this stage.
4. FM rejects with reason → `rejected` → PM receives FM-rejection notification.
5. PM may edit/resubmit from `rejected`, or close the project as rejected according to the existing permitted workflow.
6. Confirm each expected notification opens the existing project destination and that the same unread event is not duplicated by repeated page loads/actions.

No runtime result is recorded until the user performs the controlled local XAMPP test.


## 2026-09-24 — Governmental Fees Financial Integration

The existing repeatable **المتطلبات الحكومية الأولية / الرسوم الحكومية** records are now treated as an organizational financial obligation throughout the project financial approval chain.

### Business rule

- The normal project budget remains the implementation/project-cost budget.
- Every recorded governmental fee is an Ahl El Kheir financial obligation; the original source of the organization's funds is not relevant to this calculation.
- Governmental fees remain a separate component and are **added on top of** the approved project budget.
- The financial requirement is therefore:

`Total Financial Requirement = Approved Project Budget + Government Fees`

### Workflow impact

The centralized project financial calculation now exposes:
- approved project budget;
- total governmental fees;
- total financial requirement.

The financial requirement is now used for:
- FM funding-allocation limits;
- FM financial approval validation;
- GM final-approval validation;
- the single GM project accounting release amount, because that journal is created from the complete funding allocations;
- project closure/final financial variance basis.

The normal project budget itself is not inflated by governmental fees. Budget-line equality remains based on the suggested/approved project budget only.

### Accounting/payment boundary

No second accounting journal is introduced for governmental fees.

Instead, FM must allocate the **full total financial requirement** across the organization's approved project funding accounts before financial approval. The existing GM approval journal then posts the complete funding amount, and the existing post-GM payment-evidence workflow documents the actual payment evidence for those funding allocations.

### Change-control rule

Governmental fees cannot be silently changed after financial approval because the project general-information editing workflow only permits the relevant project edit/resubmission states. A project that returns to PM editing must pass through FM financial review again before GM final approval.

### Implementation

- `modules/projects/project_lib.php`
  - added centralized governmental-fee total and total-financial-requirement helpers;
  - closure variance basis now uses the total financial requirement.
- `modules/projects/view_fm.php`
  - FM funding limits and financial approval now use budget + governmental fees;
  - FM review UI explicitly displays the fee total and total financial requirement.
- `modules/projects/view.php`
  - pre-approval funding limits/messages use the total financial requirement;
  - GM final approval requires funding to equal the total financial requirement;
  - lifecycle final budget basis uses the total financial requirement;
  - existing GM accounting release remains the single accounting event.
- No new table, column, trigger, view, or runtime DDL was introduced.

### Verification status

**Runtime verification required.**

Controlled test:
1. Use an existing test project or a controlled draft project.
2. Enter a normal suggested/approved project budget, for example 500,000 SDG.
3. Enter one or more governmental fees, for example 25,000 SDG.
4. Verify the PM/project view shows the fees separately and the total financial requirement as 525,000 SDG.
5. Verify FM sees 525,000 SDG as the amount that must be fully allocated.
6. Verify FM cannot financially approve when funding allocations total only 500,000 SDG.
7. Add the remaining 25,000 SDG and verify FM approval succeeds at 525,000 SDG.
8. Verify GM final approval also requires 525,000 SDG and creates the single accounting release for the full allocated amount.
9. Verify payment-evidence rows still correspond to the actual funding allocations and the existing FM completion workflow remains unchanged.
10. Verify project closure/financial variance uses the total financial requirement rather than treating governmental fees as an unexplained overspend.

---

## Resolution — 2026-09-24: "Is final approval a spend or a reservation?"

Resolved, per explicit decision: **final approval is a funding reservation, never a spend.** It
earmarks the project's funding allocations (`project_funding_allocations.status`: `draft` →
`posted`, with `approved_by`/`posted_by`/`posted_at` set) and does **not** touch the general
ledger or any cash/bank/wallet balance. Real money only moves later, per individual posted
`project_expense`, exactly as every other cash-basis flow in this system already works
(transactions, vouchers, disbursements, payroll).

This closes the "could final approval and later expense posting recognize the same financial
event twice?" question above: **yes, they did.** Final approval previously posted a journal entry
debiting account 5100 ("Program and aid expenses") for the *entire* approved budget and crediting
the chosen funding-source accounts — recognizing the whole budget as spent before a single pound
had actually left the organization. Editing or deleting a funding allocation after approval then
reversed and re-posted that same phantom entry. Confirmed against a live copy of the database:
already-approved projects had drained real cash/bank/wallet with zero posted expenses. A one-time
correction (`tools/fix_project_approval_journals.php`) reverses any such entry with a proper,
audited reversing entry (original stays posted; nothing is deleted or altered in place).

A second, related defect was fixed at the same time: `project_funding_allocations.status` could
never reach `'posted'` (the only actions that transitioned it, `approve_funding`/`post_funding`,
had already been disabled elsewhere as "no longer needed"), so every project's committed-funding
total silently displayed as zero regardless of how much funding was actually allocated. Final
approval now performs that transition directly.

The `project_payment_evidence` documentary-evidence row created per funding source at approval
time is unaffected: it is created exactly as before, with `journal_entry_id = NULL` (the column
already allowed this), since no journal entry exists until that funding source's payment is
actually documented.

Changed: `modules/projects/view.php` (`approve_project`, `edit_funding`, `delete_funding` handlers;
removed `akp_create_project_approval_journal()` and `akp_reverse_project_journal()`; added
`akp_commit_project_funding()`). No schema changes. `akp_project_totals()` and the portfolio
listing query already filtered on `status = 'posted'` and needed no changes.


---

## Resolution — 2026-09-25: Project Supervisor operational expense tracking

After the PM → FM → GM → PM final approval → explicit PM launch workflow was runtime-verified for PRJ-0010, the Project Supervisor project view was narrowed to operational responsibilities.

- Project Supervisors no longer see the **تخصيص التمويل** section.
- Project Supervisors no longer see the **إثبات صرف تمويل المشروع** section.
- The existing financial/accounting controls remain available to their authorized roles and were not removed or reassigned.
- The **المصروفات** section now gives the assigned Project Supervisor a dedicated operational expense-entry form without exposing accounting-account selection.
- A supervisor expense is stored as a `draft` project expense so later financial/accounting processing remains separate from operational recording.
- The supervisor can optionally attach a PDF/JPG/PNG receipt directly to the expense; the existing protected project-document storage is reused.
- The expense view now shows the FM-approved budget total, total recorded project expenses, and the remaining budget after recorded expenses.
- Server-side validation prevents the assigned Project Supervisor from recording an expense that would exceed the remaining FM-approved project budget.
- No schema change, runtime DDL, trigger, view, stored procedure, or new accounting event was introduced.
- Existing PM/FM/GM funding, payment-evidence, expense approval, and posting workflows remain unchanged.

Implementation: modules/projects/view.php.

Runtime verification remains required locally for the new supervisor expense-entry and receipt attachment path before this work unit is marked fully verified.


---

## Resolution — 2026-09-25: Project view conditional closure

A PHP parse error was identified in modules/projects/view.php after the Project Supervisor expense/visibility changes. The approved-project payment-evidence block opened an outer approval conditional and a nested Project Supervisor visibility conditional, but only the nested conditional was closed before the following expenses block began.

- Added the missing endif for the outer approved-project conditional.
- No business logic, permissions, database schema, accounting behavior, or workflow was changed by this correction.
- The correction restores normal parsing of modules/projects/view.php so the project view can render again.

Implementation: modules/projects/view.php.


## 2026-09-25 — Project view Back-button and expense feedback polish

- Corrected the shared Back-button logic so audited non-dashboard pages keep exactly two controls: one top Back button and one bottom Back button immediately before the footer; an existing contextual Back button is no longer duplicated at the top.
- Project Supervisor expense success feedback is now rendered inside the **المصروفات** section instead of the global page alert area, and the page returns the user to that section after a successful save.
- The expense table header now uses a distinct Bootstrap table color (`table-primary`) for clearer separation from the data rows.
- No database/schema changes, migrations, triggers, views, stored procedures, or accounting behavior were changed.


## 2026-09-25 — Project expense/receipt and supporting-document separation

- The Project Supervisor's **المصروفات** section is now the single project-view location for expense receipts. Receipt-type records are excluded from the separate documents list, so an expense receipt is not duplicated under the general documents section.
- The separate documents section is now labeled **الوثائق والتصاريح والشهادات** and is intended for non-receipt supporting records such as government permits, contracts, certificates, quotations, invoices, progress reports, closure reports, and other project documents.
- Project Supervisors can edit or delete their own operational expense records while the expense remains in \`draft\`; editing can also replace the attached receipt. Submitted/approved/posted expenses remain protected from destructive edits/deletes.
- Project Supervisors can edit or delete unverified supporting documents while the project remains editable; verified documents are protected, and documents linked as an expense's primary document cannot be deleted through the general documents section.
- Edit operations use the existing project document/expense tables and protected project storage. Delete operations remove the associated stored file when appropriate and write audit events.
- Both record types now expose **تعديل** and **حذف** controls beside eligible records, with professional confirmation for destructive actions.
- The shared Back-button helper is now deterministic and idempotent: audited non-dashboard pages render exactly two shared Back controls inside \`.content\`, one top-left and one bottom-right, with no duplicate generated control.
- No new table, column, migration, trigger, view, stored procedure, or accounting event was introduced. PM/FM/GM approval, funding, payment-evidence, posting, and launch workflows remain unchanged.
