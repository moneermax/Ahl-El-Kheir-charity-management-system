# Nanny Group Verification — Bulk Workflow Continuation

**Status:** Implementation committed; PHP syntax passed; local runtime verification pending  
**Baseline:** 2026-10-22  
**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Implementation:** `modules/accounting/group_verification.php`

## Scope

Reduce repetitive work for Nannies responsible for large groups without changing the three required verification facts or the downstream accounting rule.

1. Orphan verification: confirm the orphan is alive and remains eligible for sponsorship.
2. Mother contact verification: confirm the mother's phone number works and she can be reached.
3. Bank-account verification: confirm the bank-account details are correct and current.

## Implemented behavior

- The Nanny can select multiple families in the currently assigned group.
- Bulk action options are: orphan check only, mother-contact check only, bank-account check only, or all three checks.
- A single-check action preserves the other two existing flags. Bulk actions only mark checks as verified; they do not clear flags.
- A confirmation dialog states the selected operation and family count.
- Each changed family receives a row in the existing `verification_audit_log`.
- The selected family list is validated in full before writes begin. A selection containing a family outside the selected group is rejected.
- The bulk write and per-family audit rows run in one database transaction. Audit failure rolls back the whole batch.
- Bulk verification is limited server-side to an active group assignment and a group whose `verification_status` is `pending`.
- Individual verification and group submission now enforce the current Nanny/group/family relationship server-side.
- Group submission remains limited to fully verified families and the existing downstream accounting workflow.
- No schema migration was added: `nanny_family_verifications` already has a unique key on `(family_id, month)`, the three verification flags, and attribution fields; `verification_audit_log` already supports per-family before/after flag history.

## Important schema constraint

The schema has one `nanny_family_verifications` row per family and month, regardless of Nanny. The implementation locks and updates that row when it exists, preserves the existing flags for one-check bulk operations, and records the current acting Nanny as the verifier. Historical rows in `verification_audit_log` are retained.

## Follow-up fix — closed-group individual controls (2026-10-22)

Inspection confirmed the inconsistency in both layers: the individual POST handler checked assignment and family membership but did not require `verification_status = 'pending'`, and the row action/modal rendered for every group state. The handler now requires the pending-state guard used by bulk verification, and individual edit controls/modal are rendered only for pending groups. Non-pending rows show a dash instead of an edit action. No group status or verification records were changed.

Source commit on `main`: `e50686f0a8492506459e4465513d03778debc498`.

Verification: PHP lint **PASS** in GitHub Actions run `39716749893` for source commit `da5320d05e2656bb9d6e1f4a7a94b94b032fd177`. Local browser/database runtime verification remains pending. Do not treat the feature as runtime-verified until that check passes.

## Verification boundary

- Source/schema inspection: complete.
- PHP syntax: **PASS** in GitHub Actions run `39715384849`; the `php-lint` job completed successfully and included `modules/accounting/group_verification.php`.
- The same broad workflow's unrelated `migration-full-schema` job failed later during its existing Stage 5 salary-advance payroll integration check with `FAIL | Stage 5 payroll integration | يجب تطبيق ترحيل تجهيز تحويلات الرواتب قبل تأكيد الدفع.` This is not evidence of a failure in the Nanny page; do not reopen or alter the closed salary-advance module as part of this task.
- Runtime/browser verification against the real local application and `ahl_el_kheir`: **not yet performed**.
- No claim is made that runtime behavior has passed until the local workflow is exercised.

## Local acceptance checks

1. Pull `main` and sign in as a Nanny assigned to a group with `verification_status = 'pending'`.
2. Select multiple families and apply one check. Confirm the other two flags remain unchanged for every selected family.
3. Repeat with the other individual checks.
4. Apply all three checks to a selected set and confirm all three flags become verified only for that set.
5. Confirm the page's fully verified count changes only when all three flags are true.
6. Confirm each affected family has a corresponding `verification_audit_log` row.
7. Attempt a POST with a family outside the assigned group or with a non-pending group; the server must reject the bulk action without partial writes.
8. Confirm group submission still sends only fully verified families through the existing accounting workflow.

Do not create a new test database or alter the schema for these checks. Use the user's current local application/database and existing assigned groups.

## 2026-10-22 — Nanny preliminary monthly family verification

Added `modules/accounting/nanny_pre_verification.php` as a separate Nanny workspace and linked it from the Nanny sidebar and the existing group-verification page.

The page reuses `nanny_family_verifications` for the displayed payment month (`YYYY-MM`); it introduces no separate preliminary status and requires no schema migration. Added English compatibility translations for the new Arabic UI strings in `lang/bridge/ar_to_en.php`. Individual and bulk changes use the existing three flags and append per-family `verification_audit_log` records transactionally. Single-check bulk operations preserve the other flags; individual review can explicitly change or clear flags. The bulk selection is fully validated before writes.

Server-side scope is limited to the logged-in Nanny's active assignments and pending groups. Because the schema stores one verification row per family/month rather than per group, families also present in active non-pending groups are excluded to avoid modifying a shared record already used by a submitted/locked group. This page does not submit groups to Accounting.

**Verification boundary:** source committed to `main`; PHP 8.2 syntax lint PASS in GitHub Actions run `39719045605` (the `php-lint` job explicitly includes `modules/accounting/nanny_pre_verification.php`); local XAMPP/browser/database acceptance remains pending. Use the existing `ahl_el_kheir` database only; do not create a disposable database or reset operational data.


## 2026-10-22 — Monthly scope correction

This section supersedes the earlier pending-only scope description above: the preliminary page now includes families in every active group assigned to the Nanny, regardless of global group status. Both Nanny pages use a validated selected payment month and carry it across navigation and POSTs. Monthly checks remain keyed by family/month in the existing table. Group-page edits for the selected month are permitted for any active assigned group, while group submission remains pending-only and the downstream accounting workflow is unchanged.

Source commits: `53190c05f7f4ba4bcfd0952e8a5fc17129d8a1f3`, `fd5d9c70e7b1c0be75e7a10b8823982f79f30f95`, `23722801177e921a82850b9c8ff15fcd6838fe55`, `7198ef5a3a488a404dfb73b41bc2c72e73def6a1`.

Verification: PHP syntax and local runtime/browser acceptance pending. No DB schema/data changes were made.


## 2026-10-22 — Shared family/month verification visibility correction

Source commit: `f108ac6f43a71b210518e8d932977e3f0a1ccd11` updates `modules/accounting/lib_group_workflow.php`. The existing schema stores one verification record per family/month, but `get_latest_verification_status()` also filtered by the last verifier's `nanny_id`. That made the same shared family/month record appear unverified when read by a different Nanny assigned to another group containing that family. The lookup now uses the family/month key; `nanny_id` remains attribution for the latest verification action, not a visibility filter.

No schema or data changes were made. This affects the preliminary Nanny page and group verification reads that share this helper. **Verification boundary:** source committed to `main`; PHP lint, relevant regression checks, and local XAMPP/browser acceptance are still pending. Verify that the same family's flags for a selected month remain consistent between the preliminary page and each assigned group, including when the record's last verifier differs, and that another month remains independent. Do not claim runtime acceptance until tested against the existing `ahl_el_kheir` database.


## 2026-10-22 — Monthly family verification selection follow-up

After the user reported that the bulk-verification UI still showed “لم يتم تحديد أسر” after selecting families, `modules/accounting/nanny_pre_verification.php` was updated in commit `5e61c72e3693b9511fcc1420e0ef1e4358657b9c`.

The page now mirrors checked family IDs into hidden controls physically inside `bulkVerificationForm`, synchronizes that mirror when individual or select-all checkboxes change, and uses the synchronized selection count for confirmation. After confirmation it uses `requestSubmit()` where available so the shared same-page POST handler can process the request rather than bypassing the submit event with `form.submit()`.

No schema/database changes were made. Source-level implementation is committed; PHP syntax check, browser behavior, and real local XAMPP/database verification are **pending**. The user will pull `main` and perform the local acceptance steps; do not mark fixed until the bank-only operation succeeds and preserves the orphan flag.


## 2026-10-22 — Enforce locked group verification state

The user confirmed the preliminary monthly bulk verification now works, then identified that the group-verification page still exposed editing controls for a group already marked `closed` when a future payment month was selected. Source inspection confirmed the live `main` version did not consistently enforce the documented pending-only rule: bulk and individual POST handlers called the assignment check without requiring pending status, and the table rendered selection/edit controls for every group state.

Commit `465cbc77c29b2d1e410ba7938da21429ff548120` updates `modules/accounting/group_verification.php` so bulk and individual POSTs require `verification_status = 'pending'`; the bulk selector, family selection checkboxes, individual actions, and edit modals render only while the selected group is pending. Non-pending groups remain viewable for their saved monthly verification status, but cannot be edited through this page. Selecting a future month does not reopen a closed group. No database/schema changes or verification-record changes were made.

**Verification status:** source committed to `main`; local PHP syntax check and browser verification against the existing `ahl_el_kheir` database are pending. Test the closed group in the UI with both its current and a future month: values should remain visible, all verification controls should be absent, and no data should change. Also confirm a pending group still exposes its verification controls. Do not mark runtime-verified until the user confirms these checks.
