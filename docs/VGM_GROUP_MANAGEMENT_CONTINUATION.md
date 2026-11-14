## 2026-11-14 — VGM monthly group opening and safe group removal (implementation pending verification)

Scope is deliberately limited to the VGM role. The Nanny-side close notification to VGM is deferred to the later Nanny pass. Accountant workflows and the paused checkpoint `docs/checkpoints/2026-11-13-monthly-nanny-disbursement-fm-approval.md` remain untouched and must not be resumed automatically.

### Source design

- New shared procedural helper: `modules/deputy_gm/lib_group_management.php`.
- Web VGM page: `modules/deputy_gm/groups.php`.
- Mobile API: `api/v1/deputy_gm/groups.php`.
- Flutter VGM group page and widget tests: `mobile/ahl_el_kheir_app/lib/orphan_groups_page.dart` and `mobile/ahl_el_kheir_app/test/orphan_groups_page_test.dart`.

### Monthly opening rules

The VGM can open the server's current month for one or more selected groups. Backend validation is authoritative:
1. Group lifecycle status must be `active`, `is_active=1`, and at least one current member must exist.
2. The group must have an active user account whose role code is `nanny` assigned through an open `nanny_group_assignments` row.
3. No `monthly_disbursements` row may already exist for the current month.
4. A never-used first cycle is accepted only when the group's verification state is `pending`, there is no disbursement history, and there is no prior group workflow audit.
5. A later cycle is accepted only when the group's current verification state is `closed`, its latest disbursement month is earlier than the current month, that latest batch is `received` or `returned`, and all historical batches are terminal (`received`, `returned`, `cancelled`, or `voided`). Any in-progress historical batch blocks opening.
6. The transaction locks the group and relevant disbursement/assignment rows, changes only `verification_status` to `pending`, clears the current-cycle `submitted_at`, `approved_at`, and `paid_at` markers, and leaves `closed_at`/`closed_by` and all old monthly rows untouched.
7. A mandatory `audit_log` row (`VGM_OPEN_MONTHLY_CYCLE`) is written in the same transaction. Notification attempts to the assigned Nanny occur only after commit using the existing failure-isolated transaction-review notification helper. The reported count is notification attempts, not proof of delivery.

The month-open event uses the existing `audit_log` schema; no migration or runtime DDL is needed. The canonical SQL dump already defines the required audit columns. The group-workflow enum was intentionally not extended.

### Group removal rules

- A group with no member, Nanny assignment, disbursement, group-workflow audit, or group-level general audit history can be physically deleted only while its lifecycle status is `draft` or `empty`.
- A used group is archived instead: `status='archived'`, `is_active=0`, reason and closure metadata are retained, and any active Nanny assignment is ended. No `group_children`, assignment, disbursement, or audit row is deleted or rewritten.
- Archiving is blocked while any disbursement is not in `received`, `returned`, `cancelled`, or `voided`.
- The web page, mobile API, and Flutter screen expose the safe behavior. The Flutter screen offers individual month opening; the web screen supports individual or bulk selection.

### Transfer boundary

Existing web/API member transfer remains in place and preserves the old membership end date plus the new membership row. The schema's unique key on `(group_id, child_id)` means a child cannot be transferred back into a group that already has a historical membership row. No schema change for repeat-return transfers is included.

### Explicitly deferred

- Nanny close/return completion notification to VGM: later Nanny pass.
- Accountant-side batch reopening/creation/approval/transfer/void logic: unchanged.
- Paused monthly Nanny disbursement FM approval checkpoint: do not resume until explicitly requested after the VGM, Nanny, and Accountant passes.

### Verification boundary

This is a source implementation checkpoint only. The static PHP contract and Flutter widget tests have been added, but neither GitHub Actions nor local PHP/Flutter execution has yet been verified in this checkpoint. Local Apache/database acceptance is still required. No local database rows, disbursements, receipts, transactions, journals, or cash balances were changed. Do not claim notification delivery or end-to-end runtime success until tested.

### Next verification

1. Commit the source/test/documentation set to `main` only after reviewing the exact diff.
2. Confirm GitHub PHP contract/lint and Flutter widget-test runs.
3. User pulls `main`, then perform VGM browser/mobile acceptance using test-only groups: first opening, duplicate-open rejection, missing/disabled Nanny rejection, previous cycle incomplete rejection, completed prior cycle opening, notification appearance, archive with member-history preservation, archive blocked by open batch, and never-used draft deletion.
4. Verify the existing transfer path still works without attempting a return transfer into a previously used target group.
5. Only then move to the Nanny pass. Do not change Nanny or Accountant behavior during this VGM checkpoint.
