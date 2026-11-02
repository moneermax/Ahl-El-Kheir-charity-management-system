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
- `modules/sponsorships/view.php`: completion/cancellation locks the sponsorship and orphan, changes only that sponsorship, and recalculates orphan match state from all remaining active/paused sponsorships in the same transaction.
- `tools/test_sponsor_tree_contract.php`: source regression contract.
- `.github/workflows/php-syntax-check.yml`: lints changed PHP files, runs the source contract, applies the contact-details prerequisite and Tree migration twice on the disposable full-schema MariaDB database, then verifies the two tables, composite membership index and RESTRICT foreign keys.

## Verification evidence

GitHub Actions run [#41444537899](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/41444537899) passed all six jobs on `main` commit `3c1ac328afe95b4ef29fb4702a7a1a4df340605b`.

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
