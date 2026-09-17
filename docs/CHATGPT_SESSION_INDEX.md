# Ahl El Kheir Charity Management System — ChatGPT Session Index

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current checkpoint:** 2026-09-17

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
- Revised settlement-model code/migration: **IMPLEMENTED IN REPOSITORY; LOCAL RUNTIME ACCEPTANCE PENDING**.

### Historical Stage 3 test evidence — preserve, do not treat as current business settlement

- `FINA-SET-000001` — 50,000 SDG, full settlement of collection #3, closed, journal 56.
- `FINA-SET-000002` — 100,000 SDG, partial settlement of collection #4, closed, journal 57.
- Original collection #3/journal 54 and collection #4/journal 55 remain historical evidence.

The revised migration marks these two settlement records as historical test fixtures and creates compensating posted restore entries so their old partial-settlement test effect does not reduce the current production liability. Their original journals are not edited or deleted. Do not clean or recreate these fixtures.

### Revised Stage 4 — ACTIVE / LOCAL ACCEPTANCE PENDING

Repository implementation now includes:

- `database/migrations/2026-09-17_fina_full_settlement_model.sql` — permanent 2300/full-cycle model, dedicated Fina-held-funds account, historical test-fixture neutralization, and historical collection-funds reclassification.
- `modules/accounting/fina_lib.php` — new Fina collections post to the dedicated Fina-held-funds asset instead of Ahl treasury accounts.
- `modules/accounting/fina_settlement_lib.php` — production settlement is full-current-balance only; no partial amount; no Ahl remitting account; settlement journal is `Dr 2300 / Cr Fina-held funds`.
- `modules/accounting/fina_settlements.php` — FM-only UI with no treasury-account selector and no partial-allocation controls.

### Database-structure decision at this checkpoint

The existing `fina_settlements` and `fina_settlement_allocations` tables are **retained intentionally**. They are current dependencies of the implemented settlement engine/UI and provide settlement history/audit evidence. Do not drop, recreate, or refactor them merely for cleanup. Leaving them in place does not alter the finalized full-settlement business flow.

The obsolete item was the separate old migration file and the old production partial-settlement behavior; the old migration definition has already been consolidated into the final full-settlement migration. No triggers or views are being introduced by this work.

### Required local acceptance — NEXT WORK

1. Check local Git status before synchronization; preserve intentional uncommitted files.
2. Confirm the local database is at the finalized settlement-model state; do not drop the existing settlement tables.
3. Verify the dedicated Fina-held-funds account exists and is an asset/control account.
4. Verify current production Fina liability is **250,000 SDG** despite the retained Stage 3 test records.
5. Verify 2300 represents the current 250,000 SDG liability.
6. Verify no Ahl treasury account is offered/required by the settlement UI.
7. Create one full settlement for exactly 250,000 SDG — no partial amount and no Ahl treasury account selection.
8. Verify the settlement journal is balanced `Dr 2300 / Cr Fina-held funds` and 2300 becomes zero.
9. Verify the historical Stage 3 records remain present and identifiable as test evidence.
10. Create/approve a new Fina payment after settlement and verify the same 2300 account increases again.
11. Verify FM-only authorization and evidence/reference/lifecycle controls.
12. Verify original Fina collection journals remain unchanged.

Do not claim this revised runtime acceptance is complete until the user runs the local application/database and provides the actual results.

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
**Stage 4 revised Fina settlement model/UI: IMPLEMENTED IN REPOSITORY; LOCAL RUNTIME ACCEPTANCE PENDING — full current-balance settlement only, permanent `2300`, separate Fina-held funds, no partial settlement.**  
**Database cleanup decision: retain `fina_settlements` and `fina_settlement_allocations`; do not spend additional audit time dropping them. Next work is targeted runtime acceptance.**
