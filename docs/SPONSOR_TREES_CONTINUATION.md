# Sponsor Trees Continuation

**Date:** 2026-11-02  
**Branch:** `main`  
**Status:** Source implementation and GitHub Actions verification complete; local Linux/XAMPP migration and authenticated browser acceptance pending.

## Accepted business rules

- A Tree entity is represented by an ordinary legal-entity sponsor (`company` or `organization`). It therefore remains part of the existing sponsor, sponsorship, transaction, payment, receipt and reporting workflows.
- Tree type `optional_members`: it may be created with zero members; members can be added later.
- Tree type `required_members`: at least one active member is required at creation, and the last active membership cannot be ended through the management page.
- A member is an existing individual sponsor record. One individual may belong to multiple Trees.
- Membership is an independent relationship. Ending/reactivating a membership does not deactivate/reactivate the individual sponsor.
- Deactivating an individual sponsor does not delete or rewrite membership rows, sponsorships or payments.
- The entity's contact-person fields reuse the existing sponsor columns added by `2026-10-31_sponsor_legal_entity_contact_details.sql`.
- Tree membership is not a sponsorship allocation. Tree entities sponsor orphans through the ordinary `sponsorships` records. A Tree's contribution may be managed through the existing sponsorship/payment workflow; no equal-split or automatic distribution rule is introduced.

## Data model

Migration: `database/migrations/2026-11-02_sponsor_trees.sql`

- `sponsor_trees.sponsor_id` uniquely links one legal-entity sponsor to one Tree.
- `sponsor_trees.tree_type` distinguishes optional-member and required-member Trees.
- `sponsor_tree_members` links Tree IDs to existing individual sponsor IDs.
- Unique `(tree_id, member_sponsor_id)` prevents duplicate membership in the same Tree while allowing the same individual in other Trees.
- Membership state is retained as active/inactive with join/end timestamps and audit events; there is no membership DELETE action.
- Foreign keys use RESTRICT for sponsor/Tree relationships so deleting a sponsor or Tree cannot silently erase membership or sponsorship history.
- Schema DDL exists only in the migration. No runtime DDL was added.

## Implementation

- `modules/sponsors/trees.php`: create Tree entities, optionally attach initial members, add/re-activate members, and independently end/reactivate memberships. Admin and VGM have broad access; supervisors are limited by the existing sponsor-ownership/scope helper.
- `modules/sponsors/index.php`: Tree management entry point for admin/VGM.
- `modules/sponsors/view.php`: displays Tree membership context for admin/VGM and provides a management link where permitted.
- `modules/sponsorships/create.php`: shared sponsorship can be added to an already-sponsored orphan by a different sponsor; duplicate active/paused sponsorship is blocked only for the same sponsor–orphan pair. The orphan row is locked before insertion.
- `modules/families/orphan_profile.php`: adding a sponsorship marks the orphan matched and rechecks the same sponsor–orphan pair inside the transaction.
- `modules/sponsorships/view.php`: completion/cancellation uses the same orphan-first, sponsorship-second row-lock order as creation, changes only that sponsorship, and recalculates orphan match state from all remaining active/paused sponsorships in the same transaction.
- `tools/test_sponsor_tree_contract.php`: source regression contract.
- `.github/workflows/php-syntax-check.yml`: lints changed PHP files, runs the source contract, applies the contact-details prerequisite and Tree migration twice on the disposable full-schema MariaDB database, then verifies the two tables, composite membership index and RESTRICT foreign keys.

## Verification evidence

GitHub Actions run [#41445182344](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/41445182344) passed all six jobs on code/test commit `fcfbea2991d3171b2bb74f015e8d6a943e520ab8`, including the follow-up consistent orphan-first lock-order assertion. The follow-up lock-order correction and regression assertion are tracked by subsequent CI runs.

Verified by CI:
- PHP 8.2 syntax checks for the Tree page and changed sponsor/sponsorship/orphan pages.
- Tree source contract.
- MariaDB 10.4 full-schema import.
- Legal-entity contact migration followed by Tree migration applied twice.
- Required Tree tables, composite member uniqueness index and RESTRICT foreign keys.
- All existing workflow jobs passed.

## Remaining local acceptance

After safely pulling `main` on Linux Mint:

1. Apply `database/migrations/2026-11-02_sponsor_trees.sql` after the existing legal-entity contact migration (if that prerequisite has not already been applied).
2. As admin/VGM, create an optional-member Tree with no members and confirm it saves.
3. Create a required-member Tree with no members and confirm server-side rejection; create it with one or more individual sponsors and confirm success.
4. Add one individual to two different Trees; confirm both memberships appear.
5. End/reactivate one membership and confirm the other Tree membership and the individual's sponsor status are unchanged.
6. Deactivate an individual sponsor and confirm membership and sponsorship/payment records remain present.
7. Create two sponsorships for the same orphan from different sponsors; complete/cancel one and confirm the other remains active/paused and the orphan remains matched.
8. Confirm transactions remain attached to their original `sponsorship_id`.

Do not delete test sponsors, orphans, sponsorships or transactions to clean up. Use controlled test records and retain audit history.

**Verification boundary:** CI proves source syntax/contracts and migration behavior on a disposable MariaDB full schema. It does not prove local authenticated browser behavior or end-to-end application/accounting behavior. Do not mark the feature fully accepted until the local checks above are completed.


## 2026-11-02 — Sponsor Tree page usability follow-up

The authenticated VGM browser review identified three page-level usability issues. A targeted follow-up was committed to `main`:
- Removed Bootstrap's `text-muted` utility from the subtitle inside the dark Tree title banner. The shared `.welcome-section p` white-text rule now controls that subtitle; no global theme change was made.
- Replaced visible `الكيان` wording with Tree terminology. The company/organization selector is labelled `نوع الجهة` to distinguish it from `نوع الشجرة` (optional/required membership policy).
- Replaced the initial creation form's native multi-select with a searchable checklist. It filters by name/sponsor code, gender and assigned supervisor; shows visible/selected counts; supports selecting all visible results, clearing visible results, and clearing all selections. Filtering hides rows without clearing their checked state. Validation errors preserve submitted selections.

The initial creation picker now loads all active individual sponsors rather than silently limiting the list to 500. Existing server-side eligibility checks, supervisor scope checks, CSRF protection, required-member validation, and membership persistence are unchanged. The separate add-member control on an already-created Tree is unchanged. No database/schema/migration change was made.

Code commits: `92374f84bad0529aa5292076abad0fb8ad156ded` (page UX), `a168cfffa6349e1a2c00e9781d3e6e5200a993d6` (filter visibility/Arabic search normalization), `102e56f486c71ab76798fd56465ff95fa574548d` and `bed1482f4e61db6d97ffcce1f5ed27c8e1a4809d` (source-contract coverage).

**CI verification — PASS:** GitHub Actions run [#41461453055](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/41461453055) on code/test commit `bed1482f4e61db6d97ffcce1f5ed27c8e1a4809d` passed all six jobs. The PHP 8.2 lint job confirms the expanded Tree source contract, including title contrast, terminology, search/filters, bulk actions, selection retention and removal of the 500-row creation cap. This is source/CI evidence, not local browser verification.

**Remaining local acceptance:** pull `main`, test search by name and code, each filter, bulk select/clear, selection retention while filtering, and a required-member validation error. Do not create real Tree records solely to test the picker. This UI follow-up does not close the broader Sponsor Tree local acceptance checklist.


## 2026-11-02 — Sponsor Tree member search matching refinement

The initial Tree-creation member picker now matches typed search text against the **beginning of the first name only**, rather than any substring anywhere in the full name. Sponsor-code matching remains available as a separate alternative. The existing `input` event continues to recalculate results immediately on both typing and deletion; gender/supervisor filters and checked selections remain independent. Arabic normalization remains in place. No schema, migration, POST, permission, membership or sponsorship behavior changed.

A source-contract assertion now guards first-name prefix matching, retained sponsor-code matching, live input-event filtering and removal of full-name substring matching. Local PHP lint/contract execution and authenticated browser confirmation are pending; verify both typing and deleting characters before accepting the picker. No Tree records need to be created for this UI check.


## 2026-11-02 — Sponsor Tree multi-word name search correction

Browser feedback showed that searching a valid multi-word prefix such as `محمد ال` returned no results. Root cause: the picker reduced each full name to its first token, then tested that token against the entire query. The search now normalizes the complete name and checks `fullName.startsWith(query)`, preserving prefix-only matching while supporting multiple leading name words. Searching a word that appears only later in the name remains intentionally unsupported. Sponsor-code substring matching, immediate input/deletion filtering, gender/supervisor filters, and checkbox retention are unchanged.

No schema, migration, POST, permission, membership or sponsorship behavior changed. Source-contract coverage now explicitly requires full-name prefix matching and rejects the former first-token-only expression. Local PHP lint, contract execution, CI and authenticated browser confirmation remain pending.


## 2026-11-02 — Sponsor Tree ownership and follow-up assignment

VGM is the operational owner of Tree creation and membership: VGM creates each Tree, selects its individual members, and assigns or reassigns its follow-up supervisor. Admin retains technical oversight. Assigned supervisors can view their Trees and member lists and see the Tree in their normal sponsor scope, but cannot create Trees, change membership, or reassign the Tree. A follow-up supervisor is mandatory at Tree creation.

Assignment reuses the existing sponsor fields `supervisor_id`, `assigned_by`, `assigned_at`, and `is_manual_override`, plus the existing `sponsor_supervisor_assignments` history. Legal entities do not use the individual letter/gender assignment matrix, so Tree assignment is explicitly marked as a manual override for the existing supervisor-access helper. Reassignment records the assignment transition and a Tree audit event. No schema or migration change was required.

The Tree page heading, VGM sidebar link and sponsor-register shortcut now use a green Font Awesome tree icon. Source regression coverage and PHP lint are wired into CI. CI and authenticated browser acceptance are pending.


## 2026-11-02 — Preserve Tree supervisor assignment during sponsor edits

Follow-up review found that the ordinary sponsor edit handler recalculated legal-entity supervisor assignment as null, which would silently remove a Tree's assigned follow-up supervisor. The edit handler now preserves a Tree's explicit manual supervisor assignment while the sponsor remains a company/organization, and blocks converting a Tree sponsor into an individual. This is scoped to records present in `sponsor_trees`; non-Tree sponsor assignment behavior is unchanged.

The sponsor profile's Tree action now uses the green tree icon and labels the action as view-only for supervisors. The source contract covers the Tree-specific edit safeguard. No schema or migration change was made. CI and authenticated browser verification remain pending.
