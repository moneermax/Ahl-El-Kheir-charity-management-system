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
- **Partial settlement is not part of the production model.** No partial amount or allocation workflow is to be exposed.
- The settlement does not use Ahl operating treasury accounts `1100`, `1200`, or `1300`.
- Fina-held money is represented separately from Ahl operating funds through the correct dedicated Fina-held-funds accounting control/asset account, to be confirmed from the actual chart/schema before implementation.
- Full settlement posts `Dr 2300 / Cr Fina-held funds`.
- After successful settlement, `2300` returns to zero but remains permanently available.
- The next Fina payment increases the same `2300` again, repeating the cycle.
- Historical Fina collection journals remain unchanged.

All settlement processing is **Financial Manager (FM) only**. Supervisors, GM, and VGM do not gain settlement execution authority through links, notifications, or direct URLs.

### Completed stages

- Stage 0 — business rules: **COMPLETE**.
- Stage 1 — existing-system/schema inspection: **COMPLETE**.
- Stage 2 — settlement data model/migration: **COMPLETE**; migration applied successfully to live DB on 2026-09-17.
- Stage 3 — original accounting engine: **COMPLETE / RUNTIME VERIFIED / CLOSED** on 2026-09-17 as historical development evidence.

Stage 3 implementation:

- `modules/accounting/fina_settlement_lib.php`
- commit `580a9b2c9991d29a8a06fe6f56ee74ac2216a506`

### Historical Stage 3 test evidence — preserve, do not treat as current business settlement

- `FINA-SET-000001` — 50,000 SDG, full settlement of collection #3, closed, journal 56.
- `FINA-SET-000002` — 100,000 SDG, partial settlement of collection #4, closed, journal 57.
- Original collection #3/journal 54 and collection #4/journal 55 remained unchanged/posted.

These settlement records and journals are retained as development/test evidence. They **must not consume or reduce the current production Fina liability**. The old test result of 100,000 SDG remaining is not the current business balance.

Do not delete these records merely to clean development data, and do not rerun the closed Stage 3 acceptance suite. Only targeted regression/runtime acceptance for the revised full-settlement model is required.

### Stage 4 — ACTIVE / MODEL REVISION

The FM-only settlement UI is being revised to the finalized model. Required continuation:

1. Inspect the complete current settlement library/UI before changing behavior.
2. Inspect the actual accounts chart/schema for an existing dedicated Fina-held-funds account or established custody-account pattern.
3. Remove production partial-settlement/allocation behavior.
4. Remove the Ahl treasury-account selector (`1100`/`1200`/`1300`) from Fina settlement.
5. Make the settlement amount equal to the complete current Fina liability.
6. Post the full settlement as `Dr 2300 / Cr Fina-held funds`.
7. Ensure the resulting `2300` balance is zero after settlement while keeping the account permanently.
8. Ensure subsequent Fina payments reuse the same `2300` cycle.
9. Preserve historical Stage 3 test records/journals without letting them reduce the current liability.
10. Preserve FM-only server-side protection, evidence/reference controls, reconciliation, closure, cancellation, and audit history.
11. Update dashboards/reports only after the core accounting model is corrected.
12. Run local runtime acceptance only after the repository changes are pulled locally and do not claim verification until actual results are supplied.

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
**Stage 4 Fina FM-only settlement model/UI: ACTIVE — full current-balance settlement only, permanent `2300`, separate Fina-held funds, no partial settlement.**
