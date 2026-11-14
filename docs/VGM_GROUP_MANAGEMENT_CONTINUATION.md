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

## 2026-11-14 — VGM group management verification boundary

Additional safety check after source review: archiving a used group is also blocked when its monthly verification cycle is still open or submitted/approved/transferred/returned, even if no disbursement row exists yet. A pending group can be archived only when there is no VGM month-opening audit event. This prevents the archive action from cutting off a Nanny workflow that has started but has not reached a terminal state.

The shared guard is used by both the web lifecycle archive action and the safe-delete/archive helper used by the web and mobile API.

## 2026-11-14 — VGM group management CI verification update

Implementation commits on `main`:
- `2f6030cae67f6cb712eca5dd903698c706ddea03` — initial VGM month-opening, notification, safe archive/delete, mobile UI/API, tests and continuation documentation.
- `75c901e225d6b2375af555913e5dc702582015c2` — archive guard for unfinished verification cycles and explicit mobile API response when no selected group is eligible.

Verified CI:
- GitHub Actions run `43556748481` — dedicated VGM Group Management Contract passed. PHP 8.2 lint passed for the shared helper, web VGM page, mobile API endpoint and contract test. The source-contract suite reported `17 checks | PASS`.
- Flutter analyzer passed in run `43556576331`. All six tests in `orphan_groups_page_test.dart` passed, including the new individual month-opening and used-group archive tests. The full Flutter suite did not pass: one unrelated existing test, `payroll_management_page_test.dart`, expected 10 widgets but found 11. APK build was skipped. Do not alter that payroll test as part of this VGM task.
- Mobile API auth integration run `43556748512` passed schema import, migration, PHP lint and all earlier service checks. The VGM-specific endpoint assertions passed (Nanny denied; VGM list/create/detail/edit; never-used draft deletion). The overall job failed only at the separate existing assertion `administrator can create a real SQL backup on the disposable database`. This run did not exercise the new month-opening action or archive of a used group; do not treat those as runtime-proven.

Verification boundary: source contract, PHP syntax, Flutter analysis and the six VGM Flutter widget tests are CI-proven. Local XAMPP/Apache/browser/database acceptance is still pending. No local database rows, disbursement batches, receipts, transactions, journals or cash balances were changed. Notification delivery is not runtime-proven; the helper is failure-isolated and the implementation reports attempts, not confirmed delivery.

Next gate remains local VGM acceptance only. Do not start the Nanny or Accountant pass yet, and do not resume the paused FM approval checkpoint.

## 2026-11-14 — VGM lifecycle atomicity and final source-contract result

Commit `79e37315a7b09d32aaae9227065a8c818bc7c447` wraps the VGM lifecycle status transitions (including the retained `archive_group` POST path) in a database transaction, so its row lock and archive-cycle guard are effective and exceptions roll back the status change. The contract suite now also checks this transaction boundary and distinguishes terminal older batches from the latest successfully closed cycle.

GitHub Actions run `43556927883` passed on this commit: PHP 8.2 lint passed for the shared helper, VGM web page, mobile API and contract script; the VGM contract result is `17 checks | PASS`.

The previous verification boundaries remain:
- Flutter analyzer passed and all six VGM group widget tests passed. The complete Flutter suite failed only in the unrelated existing payroll screen test (`payroll_management_page_test.dart`, expected 10 widgets, actual 11); APK build was skipped.
- Mobile API auth integration's VGM assertions passed, including unused-draft deletion. The job failed only at the separate SQL-backup assertion for the disposable database. The new monthly-opening and used-group-archive paths have not been runtime-exercised by that integration test.
- Local XAMPP/Apache/database acceptance is still pending. Do not treat static contract tests or widget tests as end-to-end proof.
