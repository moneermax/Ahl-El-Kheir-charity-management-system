# Ahl El Kheir — Organizational Lifecycle Implementation Audit

**Date:** 2026-09-03  
**Scope:** Users, roles, departments, supervisor lifecycle, assignments, history, and auditability  
**Purpose:** Establish the current implementation baseline before extending the organizational-management model.

## 1. Executive finding

The system has already moved beyond a simple `active/inactive` model for supervisors. A dedicated supervisor lifecycle exists with the states `active`, `on_leave`, `suspended`, `returning`, `departed`, and `archived`. Leave records and assignment-history mechanisms are also present.

However, the organizational model is currently **asymmetric**: the richer lifecycle exists primarily for supervisors, while the general user-management page still exposes a direct `is_active` toggle/update path. The next phase should therefore unify lifecycle concepts without destroying the existing supervisor implementation.

## 2. What is already implemented

### Supervisor lifecycle

`config/supervisor_lifecycle.php` provides:

- lifecycle labels for active, leave, suspended, returning, departed, and archived;
- a work-eligibility check (`active` only);
- final-state protection for departed/archived supervisors;
- supervisor leave creation and activation;
- return-from-leave processing;
- permanent departure/archival;
- release of sponsor assignments on departure;
- preservation/release of supervisor letter assignments;
- transaction handling around lifecycle operations.

### Database foundation

The migration `database/migrations/2026-09-03_supervisor_lifecycle.sql` adds:

- `users.supervisor_status`;
- `supervisor_leaves`;
- assignment type/reason fields for sponsor-supervisor assignments;
- `supervisor_letter_assignment_history`.

### Status workflow

`modules/users/supervisor_status.php` does not treat every status as a simple toggle. It prevents direct toggling while a supervisor is on leave or returning and prevents reactivation of final states.

### Permanent departure

`modules/users/supervisor_departure.php` uses the lifecycle service to archive the supervisor, revoke login access, release operational assignments, and retain historical identity rather than deleting the user record.

## 3. Current gaps identified

### Gap A — General user management still uses `is_active` as the primary lifecycle control

`modules/users/index.php` still allows administrators to update `is_active` directly and provides a generic toggle action. This is appropriate for a basic account system, but it conflicts with the organizational model when applied indiscriminately to staff whose employment/organizational state needs history and controlled transitions.

### Gap B — Lifecycle is supervisor-specific

The richer state model is attached to `supervisor_status`. Other organizational users still rely mainly on role + department + manager + `is_active`. This creates inconsistent semantics across staff categories.

### Gap C — Organizational assignment history is not yet a unified concept

Supervisor sponsor and letter assignments have historical handling, but the organization does not yet have one generic assignment-history model covering role/department/manager/functional responsibility changes.

### Gap D — Role changes and reporting-line changes are not yet treated as lifecycle events

The user management page can directly change `role_id`, `department_id`, and `manager_id`. These changes should eventually be recorded as organizational events rather than silently replacing the previous state.

### Gap E — Audit logging is not yet the sole source of organizational history

Some supervisor operations explicitly create audit entries, but ordinary user edits do not yet appear to create a comparable structured organizational history record.

## 4. Target model

The intended organizational model should distinguish at least four dimensions:

1. **Identity** — the user/person record remains stable.
2. **Account access** — whether the account can authenticate.
3. **Employment/organizational lifecycle** — active, leave, suspended, departed, archived, etc., where applicable.
4. **Organizational assignment** — role, department, manager, and functional responsibilities, each with effective history.

These dimensions must not be collapsed into one boolean.

## 5. Safe implementation order

### Phase 1 — Baseline and protection

- Keep the existing supervisor lifecycle intact.
- Prevent accidental use of generic `is_active` toggles for supervisors.
- Keep final supervisor states irreversible through ordinary activation controls.
- Verify all lifecycle-related foreign keys and indexes.

### Phase 2 — Unified organizational history

Introduce a general organizational assignment/history table capable of recording:

- user;
- role;
- department;
- manager;
- effective start/end;
- assignment type;
- reason;
- actor;
- timestamps.

The existing supervisor-specific history remains valid and can coexist until migration is proven safe.

### Phase 3 — Controlled user transitions

Replace direct role/department/manager replacement with controlled transitions that:

- close the previous assignment;
- create the new assignment;
- record the actor and reason;
- preserve the previous organizational state.

### Phase 4 — Unified UI

The user-management UI should present organizational actions such as:

- Activate / suspend;
- Put on leave;
- Return from leave;
- Transfer department;
- Change manager;
- Change role;
- Record permanent departure;
- Archive.

The exact actions available must depend on the actor's authority and the target user's lifecycle.

## 6. Immediate safe change selected for this branch

This branch intentionally contains **documentation only** at this point. No production PHP or database behavior is changed by the audit itself.

The audit is stored in the repository so it can be reviewed alongside the code before the next implementation commit.

## 7. Acceptance criteria for the next implementation phase

Before expanding the lifecycle model, verify:

- supervisors cannot be accidentally reactivated through the generic user toggle;
- role/department/manager changes can be reconstructed historically;
- account access and organizational status remain separate concepts;
- departures preserve identity and historical assignments;
- temporary absence does not look like permanent departure;
- every controlled transition records who performed it and why;
- no existing sponsor, family, letter, messaging, or financial workflow is broken.
