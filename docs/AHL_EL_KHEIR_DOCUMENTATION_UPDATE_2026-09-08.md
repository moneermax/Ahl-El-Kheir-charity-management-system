# Ahl El Kheir Charity Management System
## Documentation Update — 2026-09-08

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Authoritative branch:** `main`  
**Purpose:** Synchronize the documentation with the verified implementation state reached during the HR integrity audit through 2026-09-08.

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

Example:

```text
Approved leave: 2026-09-08 → 2026-09-30
Return date:    2026-09-08

2026-09-07 → normal attendance
2026-09-08 → attendance eligible after return
2026-09-09 → attendance eligible
2026-09-10 → attendance eligible
...
2026-09-30 → attendance eligible
```

The original leave remains `2026-09-08 → 2026-09-30` for historical integrity.

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

Conceptually:

```text
HR approves leave
      │
      ├── leaves.start_date / end_date preserved
      │
      └── Employee unavailable for covered dates
                    │
                    ▼
            Return from leave
                    │
                    ▼
          hr_leave_returns record
                    │
                    ▼
       Attendance eligible from return_date onward
```

This model preserves both HR history and operational attendance behavior.

---

# 6. Database and Migration State

## 6.1 Authoritative database artifact — VERIFIED

The repository contains the authoritative database backup artifact `ahl_el_kheir.sql`.

The September development migrations were temporary implementation/application artifacts used to bring the local database to the required state.

## 6.2 Migration cleanup — VERIFIED

The completed migration scripts under the repository's root `migrations/` directory have now been removed from the active repository after their database changes were applied.

The repository should therefore no longer be treated as containing a queue of pending migration scripts.

Future schema changes must be handled deliberately and documented before being applied; an applied change must not be assumed to remain reproducible merely because an old migration file once existed.

## 6.3 Important operational rule

Before any future schema change, create/update the authoritative database backup and document the exact structural change. Do not delete or overwrite database evidence until the resulting schema has been captured.

---

# 7. Messaging — Current Verified Position

The messaging work documented in the 2026-09-02 update remains valid.

The system supports:

- message attachments;
- attachment download;
- attachment deletion where authorized;
- individual message soft deletion;
- preservation of reply relationships;
- suppression of deleted message content from normal previews;
- deletion audit information.

The attachment JSON response issue encountered during development was corrected so that the response remains valid JSON rather than JSON followed by an injected HTML/script payload.

This is an implementation-history note; the current repository behavior is the source of truth.

---

# 8. Disbursement and Accounting — Current Position

The previously documented disbursement workflow remains in force:

`Family Verification → Group Submission → Accountant Review → Batch Creation → Transfer → Nanny Confirmation → Return/Reversal → Reconciliation`

The system supports item-level payment confirmation, receipt evidence, returned amounts, reversal journal handling and group closure in the implemented workflow.

The distinction between operational disbursement state and accounting journal state must remain intact.

No new accounting policy is introduced by this documentation update.

---

# 9. Security and Integrity Principles Confirmed During HR Audit

The HR work reinforced the following project-wide rules:

1. UI state is not authoritative.
2. POST actions must repeat business-rule validation server-side.
3. Historical HR approvals must not be destroyed merely to make current operational behavior convenient.
4. Date-based business rules must evaluate the selected business date, not only the current date.
5. A durable lifecycle event should be persisted explicitly rather than inferred from a temporary UI note.
6. A later transaction must not erase the historical evidence required to explain why the transaction was allowed.
7. Legacy fields remain compatibility structures until a controlled migration removes them.
8. New HR behavior must not silently bypass payroll, attendance or accounting integrity rules.

---

# 10. Remaining HR Work — Not Yet Declared Complete

The HR audit has materially strengthened the employment-state, leave and attendance foundation, but the following should remain visible as future verification work rather than being falsely marked complete:

- full payroll calculation verification against attendance and approved leave;
- complete payroll/accounting reconciliation;
- final role/permission matrix across all HR actions;
- comprehensive audit-log coverage for every HR lifecycle mutation;
- broader regression testing across historical and future dates;
- production deployment review, including timezone and error-display policy;
- final reconciliation of legacy and canonical HR structures.

These are **OPEN / IMPLEMENTED-PARTIAL** areas, not failures of the current attendance fix.

---

# 11. Current System Status — 2026-09-08

The current verified HR attendance model is:

```text
Employee
   │
   ├── Effective employment state
   │       └── must be working
   │
   └── Approved leave for selected date?
           │
           ├── No → attendance eligible
           │
           └── Yes
                │
                ├── Effective return exists?
                │       ├── No → on leave / blocked
                │       └── Yes → attendance eligible
                │
                └── Return applies from return_date onward
```

The key integrity rule is now:

> **An approved leave is historical HR evidence; an effective return is a separate lifecycle event. Returning early must never rewrite the original approved leave interval.**

This is the authoritative documentation position for the attendance/leave behavior as of 2026-09-08.
