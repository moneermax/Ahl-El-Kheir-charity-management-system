# Ahl El Kheir Charity Management System — ChatGPT Session Index

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-15

## START HERE

For every new ChatGPT session, read these in order:

1. `docs/CHATGPT_MASTER_CONTINUATION_PROMPT.md` — permanent development/continuation rules.
2. `docs/AHL_EL_KHEIR_MASTER_STATUS.md` — current high-level project status.
3. `docs/AHL_EL_KHEIR_MASTER_AUDIT.md` — detailed master audit, only as needed for the current task.

This index is the short handoff document. The repository and these documents are authoritative over old chat history.

## CURRENT ACTIVE AUDIT

**Accounting journal cross-module integrity review**, continuing from the completed Supervisor ↔ Accounting integration review.

The Supervisor ↔ Accounting integration boundary is now **PASS / CLOSED at the tested evidence boundary**. Do not restart the Supervisor audit or repeat its completed runtime tests.

## AUTHORITATIVE SUPERVISOR SCOPE RULE

Supervisor sponsor responsibility is determined by:

`Sponsor first-name letter + Sponsor gender → Supervisor`

This is the authoritative business rule for sponsor responsibility. A Sponsor outside the Supervisor's letter+gender responsibility scope must not be visible or accessible to that Supervisor merely through a direct record URL or related list.

Sponsor/family/orphan data follows the legitimate relationship from the in-scope Sponsor through Sponsorship → Child/Orphan → Family, subject to each destination's own record-level authorization.

Do **not** treat family name, mother's name, mother's first letter, family code, or orphan identity as a substitute for Sponsor responsibility.

A historical implementation also contains `sponsor.supervisor_id` direct-assignment paths. Those paths are operational assignment/history mechanisms and are **not** an independent authorization grant. The authoritative access rule is Sponsor Letter + Gender.

## NON-NEGOTIABLE CONTINUATION RULES

- Do not restart the project.
- Do not restart completed audits.
- Do not repeat completed tests, fixtures, or SQL verification unless a genuine regression requires it.
- Do not invent SQL table or column names; inspect schema/code first.
- Do not ask the user to manually edit repository files when repository changes can be made directly.
- When the user says proceed/do it/fix it, perform the repository work directly rather than repeatedly describing a plan.
- Preserve intentional local uncommitted work and protected FM dashboard backup files.
- Use server-side authorization as the security boundary.
- Keep project documentation under `docs/` current.
- After every repository change, tell the user exactly what changed, why, how to test it, the expected result, and what to report.

## COMPLETED CURRENT-CHECKPOINT WORK

- FM dashboard treasury/admin-fee regression fixed and closed: `783b160a60ce50f0f661a65a112aea7469979ca4`.
- **FM dashboard treasury calculation is runtime-verified and closed:** Cash `1100` = `48,721,100`; Bank `1200` = `24,796,000`; E-wallet `1300` = `25,060,000`; Total Treasury = `98,577,100`; exact arithmetic match confirmed.
- Supervisor sponsor ownership/family-scope restoration completed and preserved.
- Supervisor sponsor authorization was centralized across sponsor/sponsorship routes.
- Family orphan sponsorship status display regression fixed: `9901c6318225163ca851fbaeb92514d1774bb681`.
- Supervisor dashboard sponsor KPI scope aligned with the established sponsor scope rule: `2a82dd87482544ecd6f5edbf61b095b2c23b39f8`.
- General sponsor-request queue authorization narrowed: Supervisor is not authorized for `modules/sponsors/requests.php`; commit: `ba21c3415158787e2fb294eaf746cf37d7d845bc`.
- Sponsor runtime schema synchronization cleanup completed; explicit migration added for sponsor workflow schema requirements.
- VGM sponsor assignment/reassignment is confirmed as a VGM task. Supervisor direct access is blocked and FM has no sponsor-assignment action. All three runtime checks passed. Commit: `48fc2db0ac9e5585127b65c17eacb5e3dd285ce0`.
- **Supervisor sponsorship-list scope was aligned with the authoritative Letter + Gender rule:** direct `sponsor.supervisor_id` is no longer an independent authorization path. Commit: `e2a5a23b2aa3b5797cb7298641c5fd5bc85e76b6`.
- **Supervisor sponsor authorization helper was aligned with Letter + Gender:** `supervisorCanAccessSponsor()` no longer grants access from `sponsor.supervisor_id`. Commit: `5f8e5e0848261ab6cd6edf841a21e9193f1bc123`.
- User's previously completed sponsorship-list matrix tests remain closed; do not rerun unless regression evidence appears.
- **Receipt-file regression fixed and runtime-confirmed:** `modules/transactions/receipt_file.php` now presents a normal Arabic application message when a transaction has no receipt attachment or its referenced file is unavailable, while preserving authorization and valid receipt streaming. Code commit: `ddd5e91e6f90935cd3a7e328f480ae1f8d906e34`; documentation commit: `cd78fe373c72ce4063ff0b1a9c9a66e59a245961`.

## NOTIFICATION CHECKPOINT — 2026-09-15

The shared notification behavior is now confirmed and should not be changed casually:

- Live polling is centralized in the shared notification widget and refreshes every 5 seconds.
- Dynamic notification click forms preserve CSRF protection.
- Applicable dashboards share the same unread visual behavior, including a clear red dot beside unread notification titles.
- A full notification history page exists at `modules/notifications/index.php`, and the bell includes `عرض الكل`.
- `مسح الكل` is menu-only and never deletes notification records. `clear_all.php` records a browser-local cutoff and the shared indicator hides those cleared menu entries while keeping the records available in full history.
- Real notification scenarios already tested include HR leave approval/rejection and password recovery/change-request flows; both work correctly.

Relevant commits:

- `ecc82aa6af0a8201adbb6d6de0c7dbb087e77305`
- `b82bd4effca75ffbde5a2f8e1930f326fc3b9664`
- `2ff8f331e575fab9dce9916c783ad3d7d3e63097`
- `c9b151b960b962c6cddbb1b135ceef6ddd5b4e16`
- `01f161ac59c97e033626ba5c447e7793fe52da4c`
- `0e830c3851eb2efd83f27881ff0b6c998f2d27ca`
- `391474e6ea894812b9b3eba6e636bba238e8c66a`
- `ddd9796896c49f4e2aaa150c3182253a6fa42d05`

The user explicitly tested and confirmed the latest clear-all behavior as correct. Do not rerun these notification tests unless a genuine regression appears.

## SUPERVISOR ↔ ACCOUNTING INTEGRATION — 2026-09-15 — PASS / CLOSED

The Supervisor ↔ Accounting integration boundary was inspected in repository code and then runtime-tested by the user.

### Runtime result

**All requested runtime checks passed.** No source-code change was required for this integration boundary.

Verified controls included:

- Supervisor collection-history visibility is limited to the Supervisor's own legitimate Sponsor-scope submissions.
- No unauthorized cross-Supervisor financial exposure was observed.
- Supervisor cannot create Accounting journal entries.
- Supervisor cannot modify or void journals.
- Supervisor cannot access the Accounting ledger.
- Supervisor cannot access Accounting reports/accounts as an Accounting role.
- Supervisor cannot access FM review/approval controls.
- Direct URL/server-side authorization also held for the tested Accounting-only routes.

Repository inspection also confirmed:

- `modules/accounting/journal.php` excludes Supervisor.
- `modules/accounting/journal_create.php` is restricted to Accounting-authorized roles.
- `modules/accounting/account_ledger.php` excludes Supervisor.
- `modules/accounting/accounts.php` and `reports.php` exclude Supervisor.
- `modules/accounting/fm_review_queue.php` and `fm_transaction_review.php` exclude Supervisor.
- `modules/accounting/serve_receipt.php` and `voucher_print.php` exclude Supervisor.
- `modules/transactions/create.php` Supervisor submissions route through `supervisorCanAccessSponsor()`, persist the Supervisor actor, start as pending, and notify FM; it does not directly create a journal.

The broader Supervisor audit must not be restarted from this point.

## SUPERVISOR PAYMENT / ACCOUNTING CHECKPOINTS — 2026-09-15

### Bank transfer — PASS / CLOSED

- Transaction `SP-000010` / ID `28`.
- Gross `10,000.00`.
- Stored method `bank_transfer`.
- Administrative-fee method `none`; amount `0.00`; policy ID `NULL`.
- Posted journal `JE-000029` / ID `52`.
- Bank `1200` debit `10,000.00`.
- Sponsorship revenue `4100` credit `10,000.00`.
- No `4200` line.
- Bank balance `24,786,000` → `24,796,000`.

### Mobile wallet — PASS / CLOSED

- Supervisor selected `mobile`.
- E-wallet `1300` balance `25,050,000` → `25,060,000`.
- Increase exactly `10,000`.

### FM treasury — PASS / CLOSED

- Cash `1100`: `48,721,100`.
- Bank `1200`: `24,796,000`.
- E-wallet `1300`: `25,060,000`.
- Total Treasury: `98,577,100`.
- Exact arithmetic match confirmed.
- The reconciliation flow figures are a separate report and are not the current treasury asset balance.

Do not repeat these closed tests unless genuine regression evidence appears.

## ACCOUNTING JOURNAL CROSS-MODULE INTEGRITY — NEXT

The next substantive audit target is the remaining protected automated journal reference types:

- `payroll`
- `disbursement_void`
- `item_return`

Existing semantics are authoritative:

- `disbursement_void` is created by the batch-void workflow and references the monthly disbursement.
- `item_return` is created by the partial item-return workflow and references the disbursement item.
- `payroll` is created by `modules/hr/lib_payroll_accounting.php` and links to the payroll record.

The existing protection commit is `8e3ee6fd7d95c0efef834a284ed428d125064bfc`.

### Next workflow

1. Inspect actual current callers/workflows for the three reference types.
2. Inspect the actual schema before any SQL or test fixture creation.
3. Reuse existing evidence if a control is already genuinely proven.
4. Create only new controlled test data when a missing runtime control requires it.
5. Verify server-side protection against manual journal void/mutation.
6. Verify journal creation, linkage, balance, and source record state for each newly tested automated route.
7. Inspect remaining callers of `ak_void_journal_for_voucher()` and direct journal mutation routes.
8. Document each result in the single master audit and this index.

Do not relabel reference types or alter historical accounting evidence merely to make an audit query pass.

## PROTECTED LOCAL FILES

Do not delete/reset/stash/overwrite:

- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`

## GIT / LOCAL SAFETY

The connected GitHub view cannot inspect the user's Windows working tree. Preserve any intentional local uncommitted work. Do not use destructive reset/restore/clean/stash operations or force-push as part of continuation.
