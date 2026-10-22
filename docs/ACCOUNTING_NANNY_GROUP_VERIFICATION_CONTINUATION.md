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
