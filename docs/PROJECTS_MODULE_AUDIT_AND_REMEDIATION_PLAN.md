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
