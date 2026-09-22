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
