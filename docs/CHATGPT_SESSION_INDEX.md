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

**Accounting Journal Cross-Module Integrity review**, continuing from the completed Supervisor ↔ Accounting integration review.

The Supervisor ↔ Accounting integration boundary is **PASS / CLOSED at the tested evidence boundary**. Do not restart that audit or repeat its completed runtime tests.

Before continuing the next accounting work, complete the two remaining targeted UI/runtime checks when a fresh test opportunity exists:

1. Notification right-corner toast persistence-until-read behavior.
2. Fina entry-form visual layout.

The notification test was not repeated at this checkpoint because the previously generated notification had already been opened/read. Do not manufacture unnecessary test data merely to recreate an already-read notification. Use the next appropriate new Fina submission when runtime verification is needed.

## NON-NEGOTIABLE CONTINUATION RULES

- Do not restart the project.
- Do not restart completed audits.
- Do not repeat completed tests, fixtures, or SQL verification unless a genuine regression requires it.
- Do not invent SQL table or column names; inspect schema/code first.
- Do not ask the user to manually edit repository files when repository changes can be made directly.
- When the user says proceed/do it/fix it, perform repository work directly rather than repeatedly describing a plan.
- Preserve intentional local uncommitted work and protected FM dashboard backup files.
- Use server-side authorization as the security boundary.
- Keep project documentation under `docs/` current.
- After repository changes, tell the user exactly what changed, why, how to test it, the expected result, and what to report.

## COMPLETED CURRENT-CHECKPOINT WORK

- FM dashboard treasury/admin-fee regression fixed and closed.
- FM treasury calculation runtime-verified and closed: Cash `1100` = `48,721,100`; Bank `1200` = `24,796,000`; E-wallet `1300` = `25,060,000`; Total Treasury = `98,577,100`.
- Supervisor sponsor ownership/family-scope restoration completed and preserved.
- Supervisor sponsor authorization is aligned with the authoritative Sponsor first-name letter + Sponsor gender rule.
- VGM sponsor assignment/reassignment is confirmed as a VGM task; Supervisor direct access is blocked and FM has no sponsor-assignment action.
- Supervisor sponsorship-list scope regression was fixed and its scope tests passed.
- Receipt-file regression was fixed and runtime-confirmed.
- Accountant Staff financial/reporting and disbursement authorization work is completed at the documented boundary.
- Fina standalone schema lifecycle hardening is implemented.
- Fina authenticated receipt viewer is runtime-confirmed working.
- Fina currency selector was removed; system-wide currency is SDG only and server-side creation uses `APP_CURRENCY_CODE`.
- Fina entry-form compact horizontal label/field layout and data-appropriate control sizing are implemented; visual runtime confirmation remains pending.
- Disbursement/reissue test batch `#12` is completed; do not recreate unless a genuine regression requires it.
- Transaction void test `TR-000016` / `JE-VOID-TXN-22` is completed; do not repeat unless regression evidence appears.
- Manual journal `JE-000027` and its balance/authorization checks are completed.

## NOTIFICATION CHECKPOINT — 2026-09-17

The shared notification behavior is centralized in `includes/notification_widget.php` and polls `modules/notifications/poll.php` every 5 seconds. Existing storage, recipient rules, read state, history, CSRF-safe actions, and non-destructive clear-all behavior remain preserved.

### Live right-corner toast — restored

A genuine UI regression was identified: the shared widget attempted to call `window.AKNotify.toast()` although the current widget contained no implementation, so the unread badge/menu could update without the former right-corner pop-up.

The widget was corrected to provide its own lightweight toast implementation.

Latest implementation commit:

- `908efe8c688316a3b7c4c637da4424fb6d6500af` — restore persistent unread notification toast behavior.

Required behavior:

1. A newly received notification produces the small right/top-corner pop-up while the page is open.
2. If the notification remains unread, refreshing the page or opening the dashboard in a new browser tab shows the pop-up again.
3. Dismissing/closing the toast hides it visually only; it does **not** mark the notification read.
4. Clicking/opening the notification marks that specific notification read through the existing CSRF-protected `mark_read.php` flow and follows its actionable destination.
5. Once that notification is actually read, its toast no longer reappears on refresh/new-tab.
6. Existing 5-second polling continues to display genuinely new notifications immediately.
7. This is shared behavior for applicable dashboards, not Fina-only behavior.

### Notification runtime verification — deferred to next fresh event

The current test notification had already been opened/read before this checkpoint, so there is nothing visible to verify from that notification now.

For the next fresh Fina submission that creates an FM notification, verify only this targeted regression:

- pop-up appears without refresh;
- while still unread, refresh causes it to appear again;
- a new dashboard tab also shows it;
- dismissing it does not mark it read;
- after the notification is opened/read, it stops reappearing.

Do not repeat the broader notification audit scenarios already closed.

## FINA STANDALONE PAYMENT — 2026-09-17

Fina is a third-party protected fund:

- Fina money is not Ahl El Kheir revenue, sponsorship revenue, or administrative-fee revenue.
- Dedicated liability/control account: `2300`.
- Fina uses `fina_sources` and `fina_collections`.
- Entry: `modules/transactions/fina_payment_create.php`.
- Review: `modules/accounting/fina_payment_review.php`.
- Authenticated receipt serving: `modules/accounting/fina_receipt.php`.
- Normal request-time schema creation has been removed; migration is authoritative.
- Supervisor is a primary operational user but remains read-only at FM approval/posting.

Currency policy is **SDG only**:

- `APP_CURRENCY_CODE = 'SDG'`
- `APP_CURRENCY_NAME_AR = 'الجنيه السوداني'`
- `APP_CURRENCY_SYMBOL = 'ج.س'`

The Fina entry form no longer exposes a currency selector. Server-side creation uses the system currency, while `fina_collections.currency_code` remains for historical/accounting evidence.

Fina form UI implementation:

- `3d84d57e2de0acb3fefb1fe4fdcc2ed9c4b703c9` — remove redundant currency field.
- `e2ea1ab741da709e6b5543c85556fcbc7a15c765` — compact horizontal label/field layout and data-appropriate widths.

Runtime visual confirmation of the latest Fina form remains pending.

Detailed Fina documentation: `docs/FINA_STANDALONE_PAYMENT_MODEL.md`.

## ACCOUNTING JOURNAL CROSS-MODULE INTEGRITY — NEXT

After the immediate notification-toast runtime check and Fina form visual check, continue the remaining protected automated journal reference types:

- `payroll`
- `disbursement_void`
- `item_return`

Existing semantics are authoritative:

- `disbursement_void` is created by the batch-void workflow and references the monthly disbursement.
- `item_return` is created by the partial item-return workflow and references the disbursement item.
- `payroll` is created by `modules/hr/lib_payroll_accounting.php` and links to the payroll record.

Existing protection commit:

- `8e3ee6fd7d95c0efef834a284ed428d125064bfc`

### Next workflow

1. Inspect actual current callers/workflows for the three reference types.
2. Inspect the actual schema before any SQL or fixture creation.
3. Reuse existing evidence if a control is already genuinely proven.
4. Create only new controlled test data when a missing runtime control requires it.
5. Verify server-side protection against manual journal void/mutation.
6. Verify journal creation, linkage, balance, and source-record state for each newly tested automated route.
7. Inspect remaining callers of `ak_void_journal_for_voucher()` and direct journal mutation routes.
8. Document each result in the single master audit and this index.

Do not relabel reference types or alter historical accounting evidence merely to make an audit query pass.

## PARKED UI CHECKLIST

Apply this form UX direction systematically later, not as a blind redesign during the accounting audit:

- label + field inline/horizontally where practical;
- short fields such as phone, date, amount, and IDs compact;
- longer fields such as address, purpose, source details, and descriptions wider;
- preserve responsive/mobile usability;
- when a form is touched during normal work, apply the principle where appropriate;
- if a global redesign is justified, inspect shared CSS/components first rather than duplicating ad-hoc page CSS.

The Fina entry form is the first completed example; its visual runtime check remains pending.

## PROTECTED LOCAL FILES

Do not delete/reset/stash/overwrite:

- `modules/accounting/fm_dashboard.php.pre-fix-backup-20260914`
- `modules/accounting/fm_dashboard.php.regression-backup-20260914-111900`

## LOCAL GIT SAFETY

The connected GitHub view cannot inspect the user's Windows working tree. Preserve intentional local uncommitted work. Do not use destructive reset/restore/clean/stash operations or force-push.

Before pulling locally:

```powershell
cd D:\xampp\htdocs\AhlElKheir
git status --short --branch
```

Then use a safe pull appropriate to the actual state; never discard local work merely to obtain the latest `main`.
