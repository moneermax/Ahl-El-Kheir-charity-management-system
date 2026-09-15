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

**Supervisor ↔ Accounting integration review**, within the broader Supervisor Module Audit boundary.

The immediate goal is NOT to re-audit the Supervisor module. The Supervisor module has been working correctly in the tested areas. The next work is to inspect only the actual integration points between Supervisor operational workflows and Accounting authorization/visibility.

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
- Supervisor sponsor ownership/family-scope restoration completed and preserved.
- Supervisor sponsor authorization was centralized across sponsor/sponsorship routes.
- Family orphan sponsorship status display regression fixed: `9901c6318225163ca851fbaeb92514d1774bb681`.
- Supervisor dashboard sponsor KPI scope aligned with the established sponsor scope rule: `2a82dd87482544ecd6f5edbf61b095b2c23b39f8`.
- General sponsor-request queue authorization narrowed: Supervisor is not authorized for `modules/sponsors/requests.php`; commit: `ba21c3415158787e2fb294eaf746cf37d7d845bc`.
- Sponsor runtime schema synchronization cleanup completed; explicit migration added for sponsor workflow schema requirements.
- VGM sponsor assignment/reassignment is confirmed as a VGM task. VGM dashboard exposes `modules/sponsors/assign.php`; Supervisor direct access is blocked and FM has no sponsor-assignment action. All three runtime checks passed. Commit: `48fc2db0ac9e5585127b65c17eacb5e3dd285ce0`.
- **Supervisor sponsorship-list scope was aligned with the authoritative Letter + Gender rule:** direct `sponsor.supervisor_id` is no longer an independent authorization path. Commit: `e2a5a23b2aa3b5797cb7298641c5fd5bc85e76b6`.
- **Supervisor sponsor authorization helper was aligned with Letter + Gender:** `supervisorCanAccessSponsor()` no longer grants access from `sponsor.supervisor_id`. Commit: `5f8e5e0848261ab6cd6edf841a21e9193f1bc123`.
- User's previously completed sponsorship-list matrix tests remain closed; do not rerun unless regression evidence appears.
- **Receipt-file regression fixed and runtime-confirmed:** `modules/transactions/receipt_file.php` now presents a normal Arabic application message when a transaction has no receipt attachment or its referenced file is unavailable, while preserving authorization and valid receipt streaming. Code commit: `ddd5e91e6f90935cd3a7e328f480ae1f8d906e34`; documentation commit: `cd78fe373c72ce4063ff0b1a9c9a66e59a245961`.

## NOTIFICATION CHECKPOINT — 2026-09-15

The shared notification behavior is now confirmed and should not be changed casually:

- Live polling is centralized in the shared notification widget and refreshes every 5 seconds, so new workflow notifications appear without manual page refresh or logout/login.
- Dynamic notification click forms preserve CSRF protection.
- Applicable dashboards share the same unread visual behavior, including a clear red dot beside unread notification titles.
- A full notification history page exists at `modules/notifications/index.php`, and the bell includes `عرض الكل`.
- `مسح الكل` is **menu-only** and **must never delete notification records**. `clear_all.php` records a browser-local cutoff and the shared indicator hides those cleared menu entries while keeping the records available in full history.
- Real notification scenarios already tested include HR leave approval/rejection and password recovery/change-request flows; both work correctly.

Relevant commits:

- `ecc82aa6af0a8201adbb6d6de0c7dbb087e77305` — polling endpoint.
- `b82bd4effca75ffbde5a2f8e1930f326fc3b9664` — live shared notification widget.
- `2ff8f331e575fab9dce9916c783ad3d7d3e63097` — CSRF fix for dynamically rendered notifications.
- `c9b151b960b962c6cddbb1b135ceef6ddd5b4e16` — unread red-dot indicator.
- `01f161ac59c97e033626ba5c447e7793fe52da4c` — full notifications page.
- `0e830c3851eb2efd83f27881ff0b6c998f2d27ca` — bell `عرض الكل` link.
- `391474e6ea894812b9b3eba6e636bba238e8c66a` — non-destructive clear-all backend.
- `ddd9796896c49f4e2aaa150c3182253a6fa42d05` — client-side menu filtering after clear-all.

The user has explicitly tested and confirmed the latest clear-all behavior as correct. Do not rerun these notification tests unless a genuine regression appears.

## SUPERVISOR ↔ ACCOUNTING PAYMENT-METHOD FINDING — 2026-09-15

A genuine integration defect was identified from runtime evidence: Supervisor payment entry displayed a payment-method selector (`cash`, `bank_transfer`, `mobile`, `credit_card`, `other`), but the Supervisor branch that inserted records into `sponsor_payments` did **not** persist `payment_method`. The database therefore retained its default method (`cash`). The FM review queue correctly displayed the stored value, and Accounting journal posting correctly maps stored methods through `ak_cash_code()` (`cash` → `1100`, `bank_transfer`/`credit_card` → `1200`, `mobile` → `1300`).

This explains why Supervisor-submitted payments selected as bank/wallet appeared in the FM queue as cash and were posted to the cash account.

Fix:

- `modules/transactions/create.php` now persists the Supervisor-selected `payment_method` for both monthly sponsorship and non-monthly Supervisor payment submissions.
- `dashboard/supervisor_dashboard.php` now includes a scoped **سجل آخر التحصيلات التي أرسلتها** showing date, Sponsor, period, payment method, amount, and operational review status. The query is restricted to the logged-in Supervisor's own submissions and the authoritative Sponsor Letter + Gender scope.

Commits:

- `8255b4c1a205eb978920ade5dc15d21af498d478` — persist Supervisor payment method for FM accounting.
- `a5c31a65fea68a2a52141f591f0ac93e30cb023f` — add scoped Supervisor collection history.

### Historical data limitation

Supervisor payments already submitted before this fix may have `payment_method='cash'` because the selected method was never stored. The application cannot safely reconstruct the user's original selection from the database alone. Do **not** invent or bulk-change historical payment methods. Existing posted transactions must be treated as requiring evidence-based accounting correction only if the actual original payment method can be established.

## REQUIRED TESTS ALREADY COMPLETED

The previously completed sponsorship-list scope tests remain closed and PASS. Do not repeat them unless a genuine regression appears.

## NEXT AUDIT DIRECTION — SUPERVISOR ↔ ACCOUNTING

Continue only with the remaining real integration controls:

1. Verify a new Supervisor bank-transfer submission is stored as `bank_transfer` in `sponsor_payments` and appears as **تحويل بنكي** in the FM queue.
2. Verify FM approval of that new payment posts the debit to account `1200` (Bank), not `1100` (Cash).
3. Verify a new Supervisor mobile-wallet submission is stored as `mobile` and posts to account `1300` (Electronic Wallet).
4. Verify Supervisor history shows only that Supervisor's own in-scope Sponsor submissions.
5. Do not rerun closed Accounting tests or recreate old fixtures.

## LATEST CHECKPOINT — 2026-09-15

The latest genuine regression is the Supervisor payment-method persistence defect described above. It has been fixed in the repository. The next runtime test is the smallest possible new payment-method test using a fresh Supervisor submission; no historical fixture recreation is required.

## PROTECTED LOCAL FILES

Do not delete/reset/stash/overwrite:

- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`
