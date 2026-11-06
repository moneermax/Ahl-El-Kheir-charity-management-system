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


## 2026-11-02 — Tree assignment history and supervisor lifecycle refinement

Initial Tree assignment now uses `recordSponsorAssignment(..., 'tree_follow_up_assignment', 'permanent')` rather than the legacy-backfill helper, so the assignment history has the correct operational reason. Supervisor choices and server-side validation exclude supervisors whose lifecycle status is `departed` or `archived`, matching the current-supervisor list. Editing a Tree sponsor preserves the original `assigned_by` and `assigned_at` metadata as well as the supervisor ID. No schema change was introduced. CI and local runtime/browser verification remain pending.

## 2026-11-02 — Sponsor Tree entity classification expansion

The Tree creation form now offers distinct entity categories: company, charity association, local organization, international organization, civil/charitable foundation, government entity, educational institution, endowment, and other. Selecting "other" requires a free-text description (maximum 150 characters).

The selected category is stored separately in `sponsors.legal_entity_category` and `sponsors.legal_entity_category_other`. The existing `sponsors.sponsor_type` contract remains unchanged: only the company category maps to `company`; all other listed categories map to `organization`. Tree sponsorship, supervisor assignment, member relationships, and accounting workflows therefore continue to use the existing legal-entity sponsor model. The Tree detail page displays the specific category and custom description.

Migration: `database/migrations/2026-11-02_sponsor_tree_entity_categories.sql`, applied after the existing legal-entity contact and Tree migrations. It is repeatable on MariaDB 10.4 and performs no runtime DDL. The CI full-schema MariaDB job now applies it twice and checks both new columns plus the unchanged sponsor_type enum. The source regression contract covers the category whitelist, mapping, display, and custom-description validation.

**Verification boundary:** implementation and CI wiring are committed to `main`; GitHub Actions result and local Linux/XAMPP migration plus authenticated browser acceptance remain pending. Do not describe the feature as runtime-verified until those checks pass. Local application order: `2026-10-31_sponsor_legal_entity_contact_details.sql`, `2026-11-02_sponsor_trees.sql`, then `2026-11-02_sponsor_tree_entity_categories.sql`.

**CI result update — 2026-11-02:** GitHub Actions run [#41482209346](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/41482209346) completed **SUCCESS** on commit `0cdd6da3667b8b94431dd1c1507a84d68e38380a`. All six jobs passed. The PHP job passed the expanded Sponsor Tree source contract and PHP syntax checks. The full-schema MariaDB 10.4 job imported the checked-in schema, applied the contact-details prerequisite, Tree migration, and entity-category migration twice, confirmed both new columns, and confirmed the original `sponsor_type` enum remains `individual/company/organization`. This is CI/schema/source evidence only; local authenticated browser acceptance remains pending.


## 2026-11-02 — Entity-category menu locally accepted

The user confirmed that the expanded entity-category menu works in the local application. The local schema inspection confirmed:
- `sponsors.legal_entity_category` and `sponsors.legal_entity_category_other`;
- legal-entity contact fields `contact_person_name`, `contact_person_position`, `preferred_contact_method`, and `preferred_contact_time`;
- `sponsor_trees` and its core columns;
- the unchanged `sponsors.sponsor_type` enum (`individual`, `company`, `organization`).

The entity-category menu acceptance is **CLOSED**. Do not rerun/reapply the migrations unless a concrete schema regression appears.

**Still open — broader authenticated Tree lifecycle acceptance:** verify VGM creation with optional/required member rules; one individual belonging to multiple Trees; ending/reactivating one membership without changing another Tree or the individual's sponsor status; supervisor view-only access and VGM reassignment; and shared sponsorships remaining independent when one sponsorship is completed/cancelled. Preserve audit/history records; do not delete test records as cleanup.

## 2026-11-03 — Tree native-select popup experiment

User-reported issue: on `modules/sponsors/trees.php`, the entity-category selector, Tree follow-up supervisor selector, and member-picker supervisor filter may close immediately or unexpectedly select an option when reopened. Source inspection found no page JavaScript that assigns values to these selects or programmatically closes their popup. The behavior is therefore not attributed to application logic at this stage.

A narrowly scoped progressive enhancement was added to the affected selectors (including both Tree follow-up supervisor controls): `appearance: base-select` on the select and `::picker(select)`, guarded by `@supports (appearance: base-select)` and the page-only `.tree-page-select` class. Unsupported browsers retain the existing native select UI. No values, event handlers, form behavior, database schema, or other modules were changed.

**Verification boundary:** source change committed; local Linux Mint/Chrome browser acceptance is pending. This is an evidence-based browser-popup workaround experiment, not a proven root-cause fix. Test opening each affected dropdown, dismissing it without selection, reopening it, keyboard navigation, selected value persistence, and form submission. If the symptom persists, do not add JS value-reset/focus hacks; capture the browser/desktop environment and isolate the native popup behavior first.

## 2026-11-06 — Tree selector height alignment

The user confirmed that the affected Tree dropdowns now open and remain usable after the native-picker enhancement. Visual review then identified that the enhanced selectors were taller than adjacent text inputs. The cause was isolated to the page-only `.tree-page-select` controls receiving `appearance: base-select` without an explicit control height; ordinary inputs and unaffected selects retain the shared form-control sizing.

The Tree-only enhancement now explicitly sets `height: 48px`, `min-height: 48px`, and `line-height: 1.5` under the same `@supports (appearance: base-select)` guard. The 48px height matches the shared control box calculation (24px line box + 20px vertical padding + 4px border). The popup/top-layer behavior, values, event handlers, form processing, schema, and other modules are unchanged.

**Verification boundary:** source change committed to `main`; local visual confirmation of equal heights is pending. Recheck all affected selectors and verify the popup behavior remains fixed. No database action or form submission is required.

## 2026-11-06 — Tree supervisor reassignment lifecycle validation

A focused source audit found a server-side validation mismatch: the Tree reassignment selector and Tree-creation handler excluded supervisors whose effective lifecycle status is `departed` or `archived`, but the reassignment POST handler validated only the user's supervisor role. A crafted or stale POST could therefore assign an ineligible supervisor despite the UI hiding that choice.

The reassignment handler now applies the same effective-status predicate as the creation handler and current supervisor list, using `COALESCE(supervisor_status, CASE WHEN is_active = 1 THEN 'active' ELSE 'suspended' END) NOT IN ('departed', 'archived')`. The database-free Tree source contract now has a dedicated assertion for this reassignment path. Commits: `a347feb636215a5490c4343c457f98d0625e4e03` (source), `71b5d435304c8b172cbcb566fe6a28e86ece7658` (contract).

**Verification boundary:** source and regression assertion are committed to `main`; PHP lint, contract execution and GitHub Actions result are pending. This closes only the validation mismatch at source level; local authenticated reassignment and assignment-history acceptance remain open. No schema/migration or existing records changed.

## 2026-11-06 — Deferred Tree dropdown appearance TODO

User confirmed the Tree dropdowns now open and work, but the controls still do not visually match neighboring fields: height/shape and arrow-indicator appearance remain inconsistent (the enhanced selectors show a different/two-arrow indicator compared with the single-arrow controls beside them). **Deferred by explicit user request.** Do not modify the Tree picker CSS or appearance behavior during the remaining lifecycle work. Revisit this as a separate visual task later, after the functional Tree lifecycle acceptance.

**CI result update — 2026-11-06:** GitHub Actions runs [#42165265142](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/42165265142) (source commit) and [#42165275942](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/42165275942) (contract commit) both completed **SUCCESS**. All six jobs passed in each run; the PHP lint job passed, including `tools/test_sponsor_tree_contract.php`. This confirms syntax/source-contract and CI migration jobs, not local authenticated runtime behavior.


## 2026-11-06 — Assigned Tree supervisor scope in ordinary sponsor workflows

A source audit found that Tree records correctly stored their explicit follow-up supervisor using the existing manual-assignment fields, but several ordinary sponsor workflows passed incomplete sponsor rows to `supervisorCanAccessSponsor()` or rebuilt only the letter/gender matrix. This caused an assigned Tree supervisor to be denied in sponsorship creation/detail, transaction creation/returned-payment editing, and orphan-profile sponsorship selection even though the Tree page itself authorized the supervisor.

A narrow source correction is now committed on `main`:
- `modules/sponsorships/create.php` now preserves the preloaded letter/gender matrix for individual sponsors and adds only explicitly assigned manual-override sponsors (including Trees) through the shared `supervisorCanAccessSponsor()` helper. This avoids introducing a per-sponsor authorization-query loop.
- `modules/sponsorships/view.php` now supplies the sponsor type, creator, manual-override flag, and supervisor assignment to the same helper.
- `modules/transactions/create.php` now supplies those fields to sponsor-scope checks, including returned sponsor-payment editing. The payment submitter ID is separately aliased as `payment_supervisor_id` so it cannot be mistaken for the Tree's currently assigned supervisor.
- `modules/families/orphan_profile.php` now supplies the same scope metadata when listing and validating sponsors for a new shared sponsorship.
- `tools/test_sponsor_tree_contract.php` now guards these cross-module scope payloads and the separation of payment submitter from sponsor assignment.

Commits: `a523e9370d5aba3fa3bd150503298cf8e5fe35b9`, `3ca97d8a5dab0dcd8866e57e496806f1d6c42b71`, `988f6d6a16ff0cc24e430d9aa40333a44fde63e7`, `ec73a590f74283fe45a1c7a75f834d016a110aa8`, `05394490cb71cd67aac0d8354b8b162c9537d9e3`, plus contract refinement `5ac4a0e3fc1a90592e31dc03e1397d998609cb14`.

No database schema, migration, accounting calculation, membership state, or sponsorship lifecycle transition was changed. No records were created, deleted, or rewritten.

**Verification boundary:** source changes and contract assertions are committed. The latest commit's GitHub combined-status response contained no status entries, so do not claim CI PASS yet. Local PHP lint, contract execution, and authenticated runtime acceptance remain pending. In particular, test as the currently assigned supervisor: Tree read-only page, Tree sponsor profile, sponsorship creation and detail, orphan-profile shared-sponsorship creation, transaction submission, and returned sponsor-payment edit. Also verify reassignment changes access from the old supervisor to the new supervisor without altering the sponsorship or payment history. Do not reapply migrations.

The checked-in schema dump fetch did not return readable SQL content during this audit; no schema inference or DDL change was made. All queried fields above already exist in the inspected application source and Tree assignment implementation.

The Tree dropdown height/arrow appearance TODO remains explicitly deferred and must not be touched during this work.
