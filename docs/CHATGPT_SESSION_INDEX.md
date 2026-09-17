# Ahl El Kheir Charity Management System — ChatGPT Session Index

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-17

## START HERE

For every new ChatGPT session, read these in order:

1. `docs/CHATGPT_MASTER_CONTINUATION_PROMPT.md` — permanent development/continuation rules.
2. `docs/AHL_EL_KHEIR_MASTER_STATUS.md` — current high-level project status.
3. `docs/AHL_EL_KHEIR_MASTER_AUDIT.md` — detailed master audit, only as needed for the current task.

This index is the short handoff document. The repository and these documents are authoritative over old chat history.

## CURRENT ACTIVE AUDIT

**Accounting journal cross-module integrity review**, continuing from the completed Supervisor ↔ Accounting integration review.

The Supervisor ↔ Accounting integration boundary is now **PASS / CLOSED at the tested evidence boundary**. Do not restart the Supervisor audit or repeat its completed runtime tests.

Before continuing that audit, complete the immediate notification-toast runtime regression check documented below.

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
- Fina collection currency selector removed; server uses system-wide SDG. Commit: `3d84d57e2de0acb3fefb1fe4fdcc2ed9c4b703c9`.
- Fina collection form labels/fields changed to practical horizontal layout with data-appropriate control widths. Commit: `e2ea1ab741da709e6b5543c85556fcbc7a15c765`.

## NOTIFICATION CHECKPOINT — 2026-09-17

The shared notification behavior is centralized in `includes/notification_widget.php` and uses `modules/notifications/poll.php` every 5 seconds. Notification storage, recipient rules, read state, history, CSRF-safe actions, and non-destructive clear-all behavior remain preserved.

### Live right-corner toast regression

A genuine UI regression was identified on 2026-09-17. The shared widget still detected newly polled notification IDs, but attempted to call `window.AKNotify.toast()` even though the current repository contained no `AKNotify.toast` implementation. As a result, the unread badge/menu could update while the former small right-corner pop-up message no longer appeared.

The widget was corrected to provide its own lightweight `AKNotify.toast()` implementation. The toast appears at the right/top corner, shows the notification title/body, can be dismissed, auto-closes, and uses the notification destination when clicked. No notification database schema, recipient logic, accounting workflow, or read-state behavior was changed.

Commit:

- `e248ae74f6a69ea69ec1781df63a42b09cbd9699` — restore live right-corner notification toast.

### Immediate runtime test — pending

On the FM dashboard, keep the page open and have the Supervisor submit a new Fina payment that creates the normal FM notification.

Expected:

1. Within the existing 5-second polling interval, the FM notification unread indicator updates.
2. A small notification pop-up appears at the right/top corner without refreshing the page.
3. The pop-up contains the new notification title/body.
4. Clicking the pop-up follows its actionable notification destination.
5. The notification remains available in the bell/history according to the existing notification rules.

Do not repeat the previously closed notification audit scenarios. This is a targeted regression test for the missing live toast only.

### Previously completed notification controls — CLOSED

- Dynamic notification click forms preserve CSRF protection.
- Applicable dashboards share the same unread visual behavior, including the clear red dot beside unread notification titles.
- Full notification history exists at `modules/notifications/index.php`, and the bell includes `عرض الكل`.
- `مسح الكل` is menu-only and never deletes notification records. `clear_all.php` records a browser-local cutoff and the shared indicator hides cleared menu entries while keeping records available in history.
- Real notification scenarios already tested include HR leave approval/rejection and password recovery/change-request flows.

Do not rerun those closed scenarios unless a genuine regression appears.

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

## FINA STANDALONE PAYMENT — 2026-09-17

The Fina Al-Khair workflow remains standalone and isolated from normal sponsor accounting:

- Fina collections use `fina_sources` and `fina_collections`.
- Fina money is 100% Fina money; it is not Ahl El Kheir revenue, sponsorship revenue, or admin-fee revenue.
- Fina accounting uses dedicated liability/control account `2300`.
- Fina receipt attachments are served through authenticated `modules/accounting/fina_receipt.php`; direct storage exposure remains denied.
- The user runtime-tested the corrected Fina receipt link and confirmed it works.
- Currency is system-wide **SDG only**; the Fina entry form does not expose a currency selector and server-side creation uses `APP_CURRENCY_CODE`.
- The Fina entry form now uses horizontal label + field layout where practical and data-appropriate control widths. Runtime visual confirmation remains pending.

Relevant commits:

- `3d84d57e2de0acb3fefb1fe4fdcc2ed9c4b703c9` — remove redundant Fina currency field.
- `e2ea1ab741da709e6b5543c85556fcbc7a15c765` — improve Fina collection form field layout and sizing.
- `e248ae74f6a69ea69ec1781df63a42b09cbd9699` — restore live right-corner notification toast.

### Schema lifecycle hardening

Request-time Fina schema creation was removed. The standalone schema is provisioned through:

`database/migrations/2026-09-17_fina_standalone_schema.sql`

`modules/accounting/fina_lib.php::fina_ensure_tables()` now performs only a read-only table-existence check and does not execute `CREATE TABLE` or `ALTER TABLE` during a normal request.

Commits:

- `59fd2e3717e627a4f80b47fcedb32e27800cb60e` — standalone Fina schema migration.
- `5e11531c7a9c75b24ef1c08d7be9138719886b35` — remove request-time schema creation.
- `745db04c08d1f58821be1728986504074321872c` — update Fina documentation.
- `1080367531c037600141be3d3d97b8f104eeae10` — authenticated Fina receipt viewer link.

Do not weaken `storage/receipts/.htaccess` to bypass receipt authorization.

The detailed Fina model is recorded in `docs/FINA_STANDALONE_PAYMENT_MODEL.md`.

## ACCOUNTING JOURNAL CROSS-MODULE INTEGRITY — NEXT

After the immediate notification-toast runtime check and Fina form visual check, continue the remaining protected automated journal reference types:

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

## FUTURE UI CHECKLIST — PARKED FOR LATER

The user identified a broader form UX improvement that should be handled systematically later, not by blindly redesigning every form during the accounting audit:

- Use label + field inline/horizontally where practical.
- Size controls according to expected data length.
- Keep short controls such as phone, date, amount, and IDs compact.
- Give longer fields such as address, purpose, and descriptions more width.
- Preserve responsive/mobile usability.
- When a form is touched during normal work, apply the principle where appropriate.
- If a global redesign is eventually justified, inspect shared CSS/components first and implement the standard consistently rather than duplicating ad-hoc page CSS.

Fina entry form implementation is the first completed example of this principle; its runtime visual check remains pending.

## PROTECTED LOCAL FILES

Do not delete/reset/stash/overwrite:

- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`

## GIT / LOCAL SAFETY

The connected GitHub view cannot inspect the user's Windows working tree. Preserve any intentional local uncommitted work. Do not use destructive reset/restore/clean/stash operations or force-push as part of continuation.

Before pulling locally, inspect the working tree:

```powershell
cd D:\xampp\htdocs\AhlElKheir
git status --short --branch
```

Then use a safe pull appropriate to the actual state; do not discard local work merely to obtain the latest `main`.
