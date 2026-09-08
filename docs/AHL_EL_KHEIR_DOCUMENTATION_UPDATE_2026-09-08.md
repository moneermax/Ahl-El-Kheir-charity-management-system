# Ahl El Kheir Charity Management System
## Documentation Update — 2026-09-08

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Authoritative branch:** `main`  
**Purpose:** Synchronize the documentation with the verified implementation state reached during the HR integrity audit through 2026-09-08 and establish the exact continuation point for the next Accounting audit phase.

> This is a chronological implementation record. Earlier audit documents retain their historical conclusions. This document records later verified changes and supersedes older descriptions only where explicitly stated below.

---

# 1. Current Documentation Rule

The project remains an existing production-oriented application, not a new implementation. Development continues using the established rule:

`Inspect → Diagnose → Explain → Patch minimally → Test → Review diff → Commit`

Documentation follows the same evidence discipline:

`Inspect repository → verify implementation → classify behavior → document`

Evidence labels remain:

- **VERIFIED** — directly confirmed in current repository implementation.
- **IMPLEMENTED-PARTIAL** — implementation exists but broader cross-module verification remains.
- **BUSINESS RULE** — explicitly established organizational behavior.
- **INFERRED** — reasonable interpretation requiring confirmation before becoming policy.
- **LEGACY/UNVERIFIED** — historical or competing implementation that is not authoritative.
- **OPEN DECISION** — requires an organizational or architectural decision.

---

# 2. HR Foundation — Current State

## 2.1 Employment state model — VERIFIED

The HR foundation now uses a canonical employment-state layer based on:

- `hr_employment_states`
- `employees.employment_state_id`
- `employees.employment_state_changed_at`
- `hr_employee_state_history`

Attendance eligibility uses the employee's effective state on the selected date rather than relying only on the legacy `employees.status` value.

The canonical state categories distinguish:

- `working`
- `temporary_unavailable`
- `separation`

Only a state in the `working` category is eligible for normal attendance processing.

The legacy employee status field remains for compatibility and has not been silently removed.

## 2.2 HR contract/salary foundation — IMPLEMENTED-PARTIAL

The repository contains the HR contract/salary foundation and subsequent compatibility/hardening work developed during the September HR audit.

The documentation should therefore no longer describe the HR salary foundation as purely conceptual. It is implemented, while remaining subject to broader payroll/accounting reconciliation and final production verification.

## 2.3 Leave foundation — VERIFIED

Approved HR leave is treated as a date-based eligibility condition. An employee with an approved leave covering the selected date is not eligible for attendance unless an explicit return-from-leave action has established an effective return date.

The approved leave record itself remains historical and authoritative. Returning an employee does **not** shorten or delete the approved leave interval.

---

# 3. Attendance Integrity — Current Authoritative Behavior

## 3.1 Date-specific eligibility — VERIFIED

`modules/hr/lib_attendance_integrity.php` provides the canonical application-level attendance eligibility checks.

The decision sequence is:

```text
Employee active?
      ↓
Effective employment state exists for selected date?
      ↓
State category = working?
      ↓
Approved leave covers selected date?
      ↓
Return effective date exists and is <= selected date?
      ↓
Eligible for attendance
```

If the employee is not eligible, state-changing attendance actions are rejected server-side.

## 3.2 Approved leave — VERIFIED

The approved leave condition is based on:

- employee;
- `status = 'hr_approved'`;
- `start_date <= selected date`;
- `end_date >= selected date`.

Therefore an approved leave remains visible as an HR historical record even after the employee returns early.

## 3.3 Return from leave — VERIFIED

The organizational rule is now:

**عودة من الإجازة = effective return from the approved leave, beginning on the selected return date.**

It is not merely a one-day attendance override.

The original leave remains unchanged for historical integrity. The persistent `hr_leave_returns` record is the authoritative effective-return event.

## 3.4 Persistent return record — VERIFIED

The persistent return model uses `hr_leave_returns` with:

- `leave_id` — identifies the exact approved leave being returned from;
- `employee_id` — identifies the employee;
- `return_date` — effective date of return;
- `created_at` — records creation time.

There is one return record per leave through a unique constraint on `leave_id`.

This prevents an old return record from accidentally unlocking a later, unrelated leave for the same employee.

## 3.5 Attendance actions — VERIFIED

The following operations are subject to the canonical eligibility check:

- check in;
- check out;
- mark absent;
- bulk attendance operations;
- other attendance state changes that require an eligible employee/date.

An employee on approved leave cannot bypass the rule by manipulating the UI or by submitting a direct POST request.

## 3.6 UI state — VERIFIED

The attendance page derives its effective status from the approved leave plus the persistent return state.

An employee is displayed as on leave only when:

`approved leave exists AND no effective return exists for the selected date.`

This keeps the UI aligned with the same business rule used by the server-side action handlers.

## 3.7 Same-day return and subsequent attendance — VERIFIED

The earlier defect where a return record disappeared from eligibility after check-in was corrected.

A normal attendance action may legitimately change the attendance row's status and clear the temporary return note. Eligibility is therefore no longer dependent on that note remaining in the attendance row.

The persistent leave-return record is the durable source of the return state.

---

# 4. Attendance Bulk Operations — VERIFIED

Bulk attendance processing respects the same individual eligibility rules.

Expected behavior:

- eligible employees are processed;
- employees still on approved leave are skipped/rejected as appropriate;
- selecting all employees does not grant an exception to leave rules;
- if every selected employee is ineligible, no attendance operation is silently performed.

The UI may offer convenience controls, but server-side eligibility remains authoritative.

---

# 5. HR Leave Lifecycle — Current Business Model

The current leave model must be understood as two separate concepts:

1. **Approved leave interval** — the HR-approved historical interval stored in `leaves`.
2. **Effective return** — the date on which the employee actually becomes eligible to resume attendance, stored separately in `hr_leave_returns`.

The original approved leave interval must remain unchanged when an employee returns early.

---

# 6. Database and Migration State

## 6.1 Authoritative database artifact — VERIFIED

The repository contains the authoritative database backup artifact `ahl_el_kheir.sql`.

The September development migrations were temporary implementation/application artifacts used to bring the local database to the required state.

## 6.2 Migration cleanup — VERIFIED

The completed migration scripts under the repository's root `migrations/` directory have now been removed from the active repository after their database changes were applied.

The repository should therefore no longer be treated as containing a queue of pending migration scripts.

## 6.3 Important operational rule

Before any future schema change, create/update the authoritative database backup and document the exact structural change. Do not delete or overwrite database evidence until the resulting schema has been captured.

---

# 7. Messaging — Current Verified Position

The messaging work documented in the 2026-09-02 update remains valid.

The system supports message attachments, attachment download/deletion where authorized, individual message soft deletion, preservation of reply relationships and deletion audit information.

The attachment JSON response issue encountered during development was corrected so that the response remains valid JSON rather than JSON followed by an injected HTML/script payload.

This is completed implementation history, not the current continuation task.

---

# 8. Disbursement and Accounting — Current Position

The previously documented disbursement workflow remains in force:

`Family Verification → Group Submission → Accountant Review → Batch Creation → Transfer → Nanny Confirmation → Return/Reversal → Reconciliation`

The system supports item-level payment confirmation, receipt evidence, returned amounts, reversal journal handling and group closure in the implemented workflow.

The distinction between operational disbursement state and accounting journal state must remain intact.

The Accounting module is **IMPLEMENTED-PARTIAL** and is now the **next audit phase**. No new accounting policy is introduced by this documentation update.

---

# 9. HR Dashboard and Navigation — CLOSED 2026-09-08

The HR dashboard/navigation audit was completed.

### Canonical HR dashboard

`dashboard/hr_dashboard.php` is the **single canonical/main HR dashboard**.

### Historical compatibility entry point

`modules/hr/index.php` no longer contains a duplicate dashboard. It redirects to `dashboard/hr_dashboard.php` so existing bookmarks/internal references remain functional.

### Final dashboard navigation

The canonical dashboard provides the HR user-facing actions for:

- employees;
- employment states;
- attendance;
- leaves;
- payroll;
- contracts;
- payroll policy;
- payroll financial corrections.

Sensitive payroll correction controls remain role-conditional for `hr_manager` and `admin`.

### Final dashboard layout

The clickable HR action cards were finalized as an even two-row grid of equal-sized cards. **التصحيحات المالية للرواتب** is included within the same action-card grid rather than being maintained as a separate toolbar.

This dashboard/navigation work is **CLOSED**. Future HR navigation changes must be made in `dashboard/hr_dashboard.php` unless a deliberate architectural decision changes the design.

---

# 10. HR Phase Closure — IMPORTANT CONTINUATION RULE

The HR integrity/foundation phase is now **CLOSED for continuation purposes**.

The following are historical completed work and must not be treated as the next task merely because older documentation discusses them:

- the original attendance eligibility investigation;
- the `عودة من الإجازة` implementation/debugging sequence;
- the persistent `hr_leave_returns` correction;
- the HR dashboard duplicate-entry-point investigation;
- the HR dashboard navigation consolidation;
- the final HR dashboard card-layout adjustment.

These should only be reopened if current repository code provides concrete evidence of a regression or if a later cross-module audit proves a dependency requiring a targeted HR change.

Some broader HR items remain **IMPLEMENTED-PARTIAL**, especially complete payroll/accounting reconciliation, organization-wide permission coverage, comprehensive audit-log coverage and production regression testing. Those are future verification items and do not change the current phase checkpoint.

---

# 11. NEXT PHASE — ACCOUNTING AUDIT

The exact next development phase is:

**ACCOUNTING AUDIT — PHASE 1: REPOSITORY/CODE AUDIT**

The first Accounting phase must begin with inspection and mapping, not random fixes.

The Accounting audit sequence is:

1. Repository/code audit.
2. Database/schema audit.
3. Accounting integrity audit.
4. Cross-module accounting audit.
5. User workflow and authorization audit.
6. Reporting and reconciliation audit.
7. Targeted fixes only where concrete issues are proven.
8. Testing and verification.
9. Documentation and session-index update.

The first Accounting inspection must identify:

- accounting dashboards;
- chart of accounts;
- journal entries and journal lines;
- opening balances;
- vouchers;
- receipts/payments/transactions;
- posting and reversal/void behavior;
- financial-manager review;
- reconciliation;
- accounting reports;
- payroll integration;
- donation/transaction integration;
- sponsorship integration;
- disbursement/return/reversal integration;
- project financial integration;
- authorization and audit logging.

Do not modify code until the current Accounting implementation and authoritative structures have been mapped.

---

# 12. Current System Status for the Next Session

The continuation state is explicitly:

```text
HR FOUNDATION / INTEGRITY AUDIT
        ↓
      CLOSED
        ↓
HR DASHBOARD / NAVIGATION
        ↓
      CLOSED
        ↓
FINAL HR CARD LAYOUT
        ↓
      CLOSED
        ↓
ACCOUNTING AUDIT
        ↓
PHASE 1 — REPOSITORY / CODE AUDIT
```

**Authoritative continuation point:** Accounting Phase 1.

A new AI/chat session must not resume an older HR investigation when this checkpoint is present.

---

# 13. Documentation Maintenance Decision

The documentation set now has an explicit continuation hierarchy:

### Historical audit layer

- `AHL_EL_KHEIR_REPOSITORY_AUDIT.md`
- `AHL_EL_KHEIR_COMPLETE_REPOSITORY_AUDIT.md`

These preserve historical evidence and conclusions.

### Long-lived architecture layer

- `AHL_EL_KHEIR_SYSTEM_ANALYSIS.md`

This remains the architectural/functional handoff document.

### Current/chronological layer

- `AHL_EL_KHEIR_DOCUMENTATION_UPDATE_2026-09-02.md`
- `AHL_EL_KHEIR_DOCUMENTATION_UPDATE_2026-09-08.md`
- `AHL_EL_KHEIR_CURRENT_SYSTEM_STATUS_2026-09-08.md`
- `HR_DASHBOARD_NAVIGATION_AUDIT_2026-09-08.md`

### Continuation/navigation layer

- `CHATGPT_SESSION_INDEX.md`

This file now contains the explicit **HR CLOSED → ACCOUNTING NEXT** checkpoint and the emergency continuation prompt.

---

# 14. Maintenance Principle

For every meaningful milestone:

**Code correct → behavior verified → documentation updated → session index updated → next continuation point clear.**

When a major phase closes, the repository documentation must explicitly record:

- the phase that closed;
- final architectural decisions;
- historical issues that must not be reopened without evidence;
- exact next phase;
- exact first audit/action to perform.

This prevents historical ChatGPT conversations from being mistaken for the current project state.
