# Ahl El Kheir Charity Management System — ChatGPT Session Index

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-18

## START HERE

For every new ChatGPT session, read in order:

1. `docs/CHATGPT_MASTER_CONTINUATION_PROMPT.md` — permanent continuation/development rules.
2. `docs/AHL_EL_KHEIR_MASTER_STATUS.md` — high-level project status.
3. `docs/AHL_EL_KHEIR_MASTER_AUDIT.md` — detailed master audit as needed.
4. `docs/FINA_SETTLEMENT_PROCESS.md` — authoritative Fina settlement policy, stages, runtime evidence, and current continuation point.

This index is the short handoff document. The repository and these documents are authoritative over old chat history.

## NON-NEGOTIABLE CONTINUATION RULES

- Do not restart the project or completed audits.
- Do not repeat passed tests, fixtures, or SQL verification unless genuine regression evidence appears.
- Do not invent SQL table/column names; inspect actual schema/code first.
- When asked to proceed/fix/do it, perform repository work directly when possible.
- Preserve intentional local uncommitted work and protected FM dashboard backups.
- Never use destructive reset/restore/clean/stash operations or force-push.
- Server-side authorization is the security boundary.
- Keep documentation current after meaningful milestones.

## FINA SETTLEMENT — CURRENT ACTIVE WORK

Fina is a protected third-party fund. It is not Ahl El Kheir revenue, sponsorship revenue, administrative-fee revenue, or an Ahl expense. Dedicated liability/control account: **`2300`**, and this account is **permanent**.

### Finalized Fina settlement cycle

- New approved Fina payments accumulate in the same permanent `2300` liability account.
- When Fina requests settlement, the **entire current outstanding Fina liability is settled in full**.
- **Partial settlement is not part of the production model.** No partial amount or allocation workflow is exposed.
- The settlement does not use Ahl operating treasury accounts `1100`, `1200`, or `1300`.
- Fina-held money is represented separately through the dedicated `Fina Al-Khair Held Funds` asset/control account created by the new settlement-model migration. This is a custody/control account for Fina money, not Ahl operating treasury.
- Full settlement posts `Dr 2300 / Cr Fina-held funds`.
- After successful settlement, `2300` returns to zero but remains permanently available.
- The next Fina payment increases the same `2300` again, repeating the cycle.
- Historical Fina collection journals remain unchanged.

All settlement processing is **Financial Manager (FM) only**. Supervisors, GM, and VGM do not gain settlement execution authority through links, notifications, or direct URLs.

### Completed stages

- Stage 0 — business rules: **COMPLETE**.
- Stage 1 — existing-system/schema inspection: **COMPLETE**.
- Stage 2 — original settlement data model: **COMPLETE**; original migration applied successfully to live DB on 2026-09-17.
- Stage 3 — original accounting engine: **COMPLETE / RUNTIME VERIFIED / CLOSED** on 2026-09-17 as historical development evidence.
- Revised settlement-model code/migration: **IMPLEMENTED IN REPOSITORY; CORE FINA SETTLEMENT ACCEPTANCE COMPLETE**. Two production cycles, reusable 2300 behavior, FM-only access, and original-journal preservation are runtime-verified.

### Historical Stage 3 test evidence — preserve, do not treat as current business settlement

- `FINA-SET-000001` — 50,000 SDG, full settlement of collection #3, closed, journal 56.
- `FINA-SET-000002` — 100,000 SDG, partial settlement of collection #4, closed, journal 57.
- Original collection #3/journal 54 and collection #4/journal 55 remain historical evidence.

The revised migration marks these two settlement records as historical test fixtures and creates compensating posted restore entries so their old partial-settlement test effect does not reduce the current production liability. Their original journals are not edited or deleted. Do not clean or recreate these fixtures.

### Revised Stage 4 — CORE ACCEPTANCE COMPLETE

Repository implementation now includes:

- `database/migrations/2026-09-17_fina_full_settlement_model.sql` — permanent 2300/full-cycle model, dedicated Fina-held-funds account, historical test-fixture neutralization, and historical collection-funds reclassification.
- `modules/accounting/fina_lib.php` — new Fina collections post to the dedicated Fina-held-funds asset instead of Ahl treasury accounts.
- `modules/accounting/fina_settlement_lib.php` — production settlement is full-current-balance only; no partial amount; no Ahl remitting account; settlement journal is `Dr 2300 / Cr Fina-held funds`.
- `modules/accounting/fina_settlements.php` — FM-only UI with no treasury-account selector and no partial-allocation controls.

### Database-structure decision at this checkpoint

The existing `fina_settlements` and `fina_settlement_allocations` tables are **retained intentionally**. They are current dependencies of the implemented settlement engine/UI and provide settlement history/audit evidence. Do not drop, recreate, or refactor them merely for cleanup. Leaving them in place does not alter the finalized full-settlement business flow.

The obsolete item was the separate old migration file and the old production partial-settlement behavior; the old migration definition has already been consolidated into the final full-settlement migration. No triggers or views are being introduced by this work.

### Runtime acceptance — CORE ACCEPTANCE COMPLETE

1. Check local Git status before synchronization; preserve intentional uncommitted files. — **COMPLETE**.
2. Confirm the local database is at the finalized settlement-model state; do not drop the existing settlement tables. — **COMPLETE**.
3. Verify the dedicated Fina-held-funds account exists and is an asset/control account. — **COMPLETE** (`1401`).
4. Verify current production Fina liability is **250,000 SDG** despite the retained Stage 3 test records. — **COMPLETE**.
5. Verify 2300 represents the current 250,000 SDG liability. — **COMPLETE**.
6. Verify no Ahl treasury account is offered/required by the settlement UI. — **COMPLETE during `FINA-SET-000003` creation**.
7. Create one full settlement for exactly 250,000 SDG — **COMPLETE** (`FINA-SET-000003`).
8. Verify the settlement journal is balanced `Dr 2300 / Cr Fina-held funds` and 2300 becomes zero. — **COMPLETE** (`JE-000035`; both `1401` and `2300` are 0 afterward).
9. Verify the historical Stage 3 records remain present and identifiable as test evidence. — **COMPLETE**.
10. Create/approve a new Fina payment after settlement and verify the same 2300 account increases again. — **COMPLETE** (`JE-000036`, collection #5, 600,000 SDG).
11. Verify FM-only authorization and evidence/reference/lifecycle controls. — **COMPLETE**; Supervisor `sv2` direct access was denied.
12. Verify original Fina collection journals remain unchanged. — **COMPLETE**; journals #54 and #55 remain posted and unchanged.

### Future development — workflow simplification

The current production lifecycle remains intentionally unchanged for this audit:

`مسودة → معتمدة للتحويل → تم التحويل → تمت المطابقة → مغلقة`

After the settlement process and acceptance work are fully completed, evaluate a shorter user-facing workflow, potentially `مسودة → معتمدة → مغلقة`, while retaining transfer/reconciliation information as audit data/events. This is a future UX/workflow simplification and must not interrupt the current acceptance work.

**Core Fina settlement accounting acceptance is complete. No further financial transactions are required for this audit area.**

## FINA STANDALONE PAYMENT

Existing Fina collection flow remains authoritative:

- Entry: `modules/transactions/fina_payment_create.php`.
- Review: `modules/accounting/fina_payment_review.php`.
- Receipt: `modules/accounting/fina_receipt.php`.
- Dashboard summary: `modules/accounting/fina_dashboard_summary.php`.
- Collections use `fina_sources` and `fina_collections`.
- System currency is SDG only via `APP_CURRENCY_CODE`.

Do not alter historical Fina collection amounts or original collection journals merely to support settlement.

## OTHER COMPLETED CURRENT-CHECKPOINT WORK

- FM treasury/admin-fee dashboard regression fixed and closed.
- FM treasury calculation runtime-verified and closed.
- Supervisor sponsor ownership/family-scope restoration completed and preserved.
- Supervisor sponsor authorization follows Sponsor first-name letter + Sponsor gender responsibility rule.
- VGM sponsor assignment/reassignment confirmed as a VGM task; Supervisor direct access blocked.
- Receipt-file regression fixed and runtime-confirmed.
- Accountant Staff financial/reporting and disbursement authorization work completed at the documented boundary.
- Disbursement/reissue test batch #12 completed; do not recreate unless regression requires it.
- Transaction void test `TR-000016` / `JE-VOID-TXN-22` completed.
- Manual journal `JE-000027` balance/authorization checks completed.
- Shared notification system and non-destructive clear-all behavior remain preserved.

## PARKED / LATER AUDIT WORK

The accounting journal cross-module integrity review remains a separate parked direction unless explicitly resumed after the Fina settlement work. Previously completed Supervisor ↔ Accounting integration tests and other closed runtime tests must not be repeated without regression evidence.

## LOCAL GIT SAFETY

The connected GitHub view cannot inspect the user's Windows working tree. Before pulling locally:

```powershell
cd D:\xampp\htdocs\AhlElKheir
git status --short --branch
```

Then use a safe pull appropriate to the actual local state. Never discard local work merely to obtain latest `main`.

## CURRENT CONTINUATION POINT

**Stage 3 original Fina accounting engine: COMPLETE / RUNTIME VERIFIED / CLOSED as historical evidence.**  
**Stage 4 revised Fina settlement model/UI: IMPLEMENTED IN REPOSITORY; CORE ACCEPTANCE COMPLETE — two production cycles verified through close, permanent `2300`, separate Fina-held funds, no partial settlement, FM-only authorization, and original collection journals preserved.**  
**Database cleanup decision: retain `fina_settlements` and `fina_settlement_allocations`; do not spend additional audit time dropping them. Next work is targeted runtime acceptance.**


## DASHBOARD / NAVIGATION REVIEW — CURRENT HANDOFF 2026-09-18

Accounting audit is intentionally parked. Current active work is the dashboard UX and role-safe navigation review, with special attention to the Administration/Staff/Social Media dashboard.

### Universal Reports

The project now uses one universal Reports Dashboard at modules/reports/index.php, backed by modules/reports/report_registry.php. Sidebar report navigation is a single Reports entry, not a dropdown. Direct report URLs use the same central authorization.

Important business rule:
- GM and VGM need broad organizational reporting visibility for management decisions, including financial reports where authorized by the report catalog.
- FM keeps financial workflow/control authority.
- Reporting access and workflow authority must not be conflated.

### Current Administration dashboard

dashboard/staff_dashboard.php was developed from its previous placeholder into an Administration/Staff/Social Media operational dashboard.

The user has now runtime-tested it and confirmed it loads without errors after the latest fixes.

Verified database relationship:
families.id ← family_children.family_id ← family_children.id ← sponsorships.child_id

There is no sponsorships.family_id and no children table.

The dashboard and Winback page were corrected to use the real relationship.

Current related commits:
- 11c978de7da1f90166c0aad9677f69989be2d105 — Administration dashboard development.
- 6c7a08fed514a2090a56e3ee30b16edafd6c134c — Administration sidebar Dashboard entry.
- ba934c6cce0bf17eced9f9d1769a2a5908df0915 — sponsor-request role authorization.
- 754a53b467e8e027543c1e089ae5e74586b10e — Administration Winback sidebar link.
- 8a2dabea8b7a7a6854c2a08ecad2c9d106e36a86 — dashboard family sponsorship query correction.
- 0d26093b53864b205ee42e4d91fe04cc7dfaee2c — Winback family sponsorship query correction.
- 1257b54f8718c5c4546f6006191f51921518836f — role/link alignment and removal of invalid Administration Reports/families shortcuts.

Runtime status:
- Administration dashboard: loads without errors.
- Winback link/page: no longer errors after the relationship fix.
- Invalid direct Families link and unauthorized Reports shortcut were removed/repointed rather than weakening authorization.

### OPEN WORK — DO NOT MARK COMPLETE

The Administration dashboard still needs UX/functional review. The user explicitly said there are more fixes/work to do on the same dashboard.

Next session should inspect:
1. KPI meanings and whether each number is useful/actionable for Administration.
2. Sponsor-request table fields, status presentation, and links/actions.
3. Winback workflow integration and whether the dashboard exposes the right Administration actions.
4. Orphan-form workflow link and whether it is valid for the role.
5. Reports visibility for Administration/Staff/Social Media based on the actual report catalog; do not add unauthorized links just to fill space.
6. Sidebar/dashboard consistency.
7. Role differences between administration, staff, and social_media so users do not see inappropriate actions.
8. Any remaining schema assumptions in the dashboard. Verify actual schema before SQL.
9. Arabic labels/encoding and responsive UX.
10. Any runtime errors the user reports.

Do not restart the dashboard. Do not repeat the already-fixed sponsorship-family schema investigation.

### CONTINUATION RULE

Start by reading this index, then master status, then relevant master-audit sections, and inspect the current dashboard/staff_dashboard.php and linked pages before changing anything.
