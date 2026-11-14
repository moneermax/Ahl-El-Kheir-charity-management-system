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
- GitHub Actions run `43556748481` — dedicated VGM Group Management Contract passed. PHP 8.2 lint passed for the shared helper, web VGM page, mobile API endpoint and contract test. The source-contract suite reported `18 checks | PASS`.
- Flutter analyzer passed in run `43556576331`. All six tests in `orphan_groups_page_test.dart` passed, including the new individual month-opening and used-group archive tests. The full Flutter suite did not pass: one unrelated existing test, `payroll_management_page_test.dart`, expected 10 widgets but found 11. APK build was skipped. Do not alter that payroll test as part of this VGM task.
- Mobile API auth integration run `43556748512` passed schema import, migration, PHP lint and all earlier service checks. The VGM-specific endpoint assertions passed (Nanny denied; VGM list/create/detail/edit; never-used draft deletion). The overall job failed only at the separate existing assertion `administrator can create a real SQL backup on the disposable database`. This run did not exercise the new month-opening action or archive of a used group; do not treat those as runtime-proven.

Verification boundary: source contract, PHP syntax, Flutter analysis and the six VGM Flutter widget tests are CI-proven. Local XAMPP/Apache/browser/database acceptance is still pending. No local database rows, disbursement batches, receipts, transactions, journals or cash balances were changed. Notification delivery is not runtime-proven; the helper is failure-isolated and the implementation reports attempts, not confirmed delivery.

Next gate remains local VGM acceptance only. Do not start the Nanny or Accountant pass yet, and do not resume the paused FM approval checkpoint.

## 2026-11-14 — VGM lifecycle atomicity and final source-contract result

Commit `79e37315a7b09d32aaae9227065a8c818bc7c447` wraps the VGM lifecycle status transitions (including the retained `archive_group` POST path) in a database transaction, so its row lock and archive-cycle guard are effective and exceptions roll back the status change. The contract suite now also checks this transaction boundary and distinguishes terminal older batches from the latest successfully closed cycle.

GitHub Actions run `43556927883` passed on this commit: PHP 8.2 lint passed for the shared helper, VGM web page, mobile API and contract script; the VGM contract result is `18 checks | PASS`.

The previous verification boundaries remain:
- Flutter analyzer passed and all six VGM group widget tests passed. The complete Flutter suite failed only in the unrelated existing payroll screen test (`payroll_management_page_test.dart`, expected 10 widgets, actual 11); APK build was skipped.
- Mobile API auth integration's VGM assertions passed, including unused-draft deletion. The job failed only at the separate SQL-backup assertion for the disposable database. The new monthly-opening and used-group-archive paths have not been runtime-exercised by that integration test.
- Local XAMPP/Apache/database acceptance is still pending. Do not treat static contract tests or widget tests as end-to-end proof.

## 2026-11-14 — Final VGM contract rerun

The VGM contract workflow was rerun on source commit `79e37315a7b09d32aaae9227065a8c818bc7c447` after adding the lifecycle transaction assertion. GitHub Actions run `43557000502` passed PHP 8.2 lint and reported `18 checks | PASS`. This is the latest VGM contract result; it supersedes the earlier 16/17-check counts.

## 2026-11-14 — Group creation hardening checkpoint (source implemented; local acceptance pending)

### Confirmed business rule
Group names must be unique globally across the organization. The unique comparison follows the table collation `utf8mb4_unicode_ci` (case/accent-insensitive). The form/API trim outer whitespace before validation.

### Implementation
- Migration: `database/migrations/2026-11-14_vgm_unique_group_names.sql` adds unique key `uq_orphan_groups_group_name`. Its preflight query must return no rows against the local database before the ALTER is applied.
- Shared helper `ak_vgm_validate_group_details()` validates string input, required name, name <=150 chars and description <=1000 chars, and reports existing duplicates. The DB index remains authoritative for concurrent requests.
- Web and API create/update use the same helper. Web and API convert unique-index collision to a clear duplicate-name response.
- Web POSTs now require CSRF and all 11 forms carry the token.
- Web Nanny assignment validates active account + role in the transaction and locks/revalidates the group.
- Web member add/remove/transfer now lock group rows and recheck current-month disbursement restrictions inside the transaction.
- Creation UI explains uniqueness, draft status and the follow-on steps. Confirmation popups consume existing `data-confirm` attributes; bulk add uses `requestSubmit()` to preserve same-page AJAX behavior.

### Audit semantics
Creation now records a `VGM_CREATE_GROUP` audit event in the same transaction as the new draft. The web and mobile usage summaries exclude this one event from `workflow_history`, so creation alone does not make the group “used” and a never-used draft remains physically deletable. `created_by` and `created_at` remain on the group row.

### Acceptance checklist — NOT YET VERIFIED LOCALLY
1. Pull latest `main`; inspect the local DB duplicate preflight output.
2. If zero rows, apply the migration. If duplicates exist, stop and resolve names with the user before altering the table.
3. PHP lint: `modules/deputy_gm/lib_group_management.php`, `modules/deputy_gm/groups.php`, `api/v1/deputy_gm/groups.php`.
4. Run `php tools/test_vgm_group_management_contract.php` and the existing `tools/test_mobile_api_auth_integration.php` harness.
5. Browser test: create unique name; duplicate exact and case/accent variant; max length and overlength/type attempts; duplicate rename; invalid CSRF; confirmation cancel/confirm; visible success/error feedback; create -> selected draft details; bulk selection and submission; confirm page remains in-place and selection result updates correctly.
6. Inspect any failure before changing code. Do not move to Nanny/Accountant until this VGM acceptance gate is passed.

### UI confirmation and mobile usage follow-up correction — 2026-11-14

A second source pass inspected `assets/js/notifications.js` and confirmed the centralized handler already processes the page's `data-confirm` form attributes; no custom VGM confirmation handler is needed. A redundant page-scoped handler was removed to prevent duplicate confirmation dialogs. The bulk-add code had a real API-contract defect: it passed an options object to `AKNotify.confirm(message, onConfirm, options)` and treated the returned result object as a boolean, which could submit even after cancellation. It now calls the centralized API with a callback that runs only on confirmation, retains a SweetAlert/native fallback, and calls `requestSubmit()`. The no-selection toast argument order was also corrected to `toast(type, message)`.

The mobile usage summary was also corrected to include `workflow_history` using the same two audit sources as the web safe-archive calculation. The Flutter page already consumes this field, so no Flutter source change was necessary. Local runtime/browser verification remains pending.

Confirmation timing fix: the shared `app.js` window-capture same-page submit handler now defers any form with `data-confirm` until `notifications.js` has confirmed it and re-submits with `akConfirmBypass`. Without this hand-off, the earlier window-level fetch handler could start the POST before the document-level confirmation interceptor. The VGM contract now checks this ordering boundary. Bulk-add uses the correct `AKNotify.confirm(message, callback, options)` contract and submits only inside the confirmed callback.

### Automated verification evidence — 2026-11-14

- VGM Group Management Contract workflow: **PASS** after the shared confirmation hand-off and creation-audit assertions (latest successful run at commit `42736b3bcfc3e2e44320824aa43a33342096ea54`). PHP syntax checks and the 35 static contract checks passed.
- Mobile API integration workflow: the new migration was imported/applied successfully in CI; PHP syntax, earlier integration stages, and all VGM group assertions passed, including exact/case-insensitive duplicate rejection, length bounds, creation audit, and safe deletion of a never-used draft. The overall job remains **FAIL** at the unrelated pre-existing assertion `administrator can create a real SQL backup on the disposable database`; MariaDB logs show `Access denied for user 'root'@'localhost' (using password: NO)`. The same failure is present in an earlier run before these VGM changes (run 247), so it is not evidence of a VGM regression and was not modified here.

Local database migration and browser acceptance remain pending. The user must run the duplicate preflight against their actual local database before applying the migration.


## 2026-11-14 — VGM create confirmation race hardening (source committed; runtime pending)

### Finding and change
The same-page POST flow previously relied on two separate event levels: `app.js` window-capture deferred forms with `data-confirm`, then `notifications.js` document-capture opened the confirmation. The reported behavior (confirmation closes and a success popup appears without an affirmative click) is consistent with the fetch/request starting before the confirmation is resolved. SweetAlert2 has one active popup surface; a toast can destroy a confirmation dialog if both are fired concurrently.

The source now uses a stricter single-owner boundary:
- `assets/js/app.js` intercepts same-page `data-confirm` submissions in the same window-capture handler that owns fetch. It prevents the original event and stops propagation, guards duplicate pending submits, and calls `requestSubmit()` only inside the `AKNotify.confirm` confirmed callback. If the confirmation helper is unavailable, it fails closed and does not send the POST.
- The one-use bypass is cleared after synchronous resubmission dispatch so a blocked/invalid resubmit cannot leave a stale bypass.
- `assets/js/notifications.js` serializes central confirmation dialogs and toast notifications through one promise queue. Toasts wait until the active confirmation has settled, and multiple queued toasts display sequentially.
- `tools/test_vgm_group_management_contract.php` now checks the same-page gate, `isConfirmed` callback, duplicate-submit guard, bypass cleanup, and popup serialization.

Source commits on `main`: `6e111302`, `5d22422c`, `72a494eb`, `38ed2c32`, `c9194984`, `9f28af41`. No DB/schema changes. The source-contract/PHP checks and local Apache/browser acceptance are still pending. Do not describe this as runtime-fixed until the normal UI acceptance is complete.

### Required UI acceptance (no developer tools)
1. Open the VGM group-management page and click “إنشاء كمسودة”. The confirmation must remain visible awaiting a choice.
2. Cancel. The page must remain on the same screen and no new group may appear.
3. Repeat with a unique test name and explicitly confirm. Exactly one draft must appear and exactly one success notification should be shown.
4. Repeat only if the first run shows a concrete failure; inspect the exact failing path before changing more code.

Do not ask the user to run SQL or browser-console snippets for this confirmation issue. The global unique-name migration remains a separate prerequisite: its duplicate preflight must be checked before applying it. Do not begin Nanny/Accountant or resume the paused FM approval checkpoint.


Verification refinement: `.github/workflows/vgm-group-management.yml` now runs `node --check` against both modified JavaScript files (`assets/js/app.js` and `assets/js/notifications.js`) in addition to the existing PHP syntax and VGM contract checks. Workflow change commit: `948626d25f22b0b5dad3cc5a7aad6bf9dd0ab6c8`. The Actions run result is not yet available through the connected GitHub interface, so this remains pending CI verification. Local browser acceptance also remains pending.

Popup serialization was extended to `AKNotify.modal()` as well, so all three centralized popup entry points (`confirm`, `toast`, and `modal`) use the same queue. The VGM contract now asserts that standard modals also enqueue. Latest source/test commits: `22456c5e` and `2e4fc80c`. The existing workflow includes Node syntax checks for both changed JavaScript files; its result is still pending and cannot be inspected through the connected GitHub Actions interface.

## 2026-11-14 — VGM group-creation confirmation: USER ACCEPTED

The user tested the updated VGM group-creation flow and reported: “now it works normally.” The supplied page capture includes the success message “تم إنشاء المجموعة كمسودة. أضف الأعضاء ثم عيّن الحاضنة وفعّل المجموعة عند جاهزيتها.” and the selected newly created group in **مسودة** status with **0** current members and **بدون حاضنة**. This is the expected post-creation draft state.

**Acceptance result:** the reported confirmation-disappears/automatic-creation symptom is no longer occurring in the user's normal UI test. Treat the group-creation confirmation fix as user-accepted. This is local browser evidence supplied by the user, not an independently observed browser session.

**Scope boundary:** this acceptance covers the create-confirmation interaction and its resulting draft state only. It does not prove the separate global unique-name migration was applied, duplicate-name handling, all group membership/caregiver workflows, or the wider VGM lifecycle. The unique-name migration preflight/application remains a separate gate. Do not begin the Nanny/Accountant passes or resume the paused FM approval checkpoint until the remaining VGM acceptance work is explicitly completed.

No schema or database changes were made as part of this confirmation fix.
