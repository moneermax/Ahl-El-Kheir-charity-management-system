# Native HR Payroll — Continuation Checkpoint

**Date:** 2026-10-26  
**Status:** Implemented; PHP/API integration, Flutter analysis/widget tests, Android debug APK build and artifact publication passed. Local XAMPP/API/database and physical-device acceptance remain pending.  
**Branch:** `main`  
**Scope:** Native payroll period management in the existing Flutter app, backed by the existing procedural PHP payroll/accounting helpers.

## Required boundaries

- Preserve the existing web payroll workflow and its calculations; the mobile app is a native client, not a replacement payroll engine.
- Preserve exact server-side roles from `modules/hr/payroll.php`: `hr_manager`, `hr_staff`, `financial_manager`, and `admin`.
- Never allow General Manager or other roles to mutate payroll from the native client. Existing GM period history/report remains read-only.
- Do not change paid payroll amounts/status, bypass payment proof, skip journal posting, or let a proof upload imply payment.
- Do not add request-time schema changes. The transfer workflow schema is provided by `database/migrations/2026-10-20_hr_fm_payroll_transfer_workflow.sql`.
- Do not alter the existing salary advance/incentive/attendance calculations or reopen closed payroll acceptance work without evidence.
- Keep private proof files under the existing server-controlled storage contract; do not expose storage keys or direct storage paths.
- Do not add an external-browser handoff.

## Source findings confirmed on 2026-10-26

- Native HR/payroll APIs cover attendance, attendance policy, payroll integrity diagnostics, GM period history/report, employee salary statements, payroll management, and authenticated private proof streaming.
- `modules/hr/payroll.php` is the authority for payroll management actions. It permits exact roles `hr_manager`, `hr_staff`, `financial_manager`, and `admin`.
- Payroll period generation uses the active payroll policy effective at period end, effective salary history, hire date, and employment state category `working`; existing rows are refreshed only while draft. Existing attendance, salary-advance, and scheduled-incentive refresh helpers must remain in the calculation path.
- Draft component edits are restricted to draft rows and recalculate attendance, salary-advance, and incentive components.
- Approval transitions a draft to `approved` and `accounting_status='ready'`; it does not post accounting.
- New-transfer proof upload requires an approved row, an active saved payment account, a PDF/JPG/PNG upload within the existing 10 MiB limit, and no existing proof. It records state `prepared` only.
- Explicit transfer confirmation requires a prepared, readable private proof and saved active account; it performs the paid transition, accounting posting, salary-advance application, and proof confirmation inside the existing transaction. Employee and GM notifications happen after commit.
- Legacy evidence upload applies only to rows already paid, posted, and journal-linked; it must not alter historical payment/accounting data.
- Existing helper `hrPayrollChangeStatus()` relies on `Session::getUserId()` when a browser session class is loaded. The API uses bearer auth and does not load the browser session; actor IDs must be explicitly passed through any new API accounting/proof-confirmation path rather than silently lost.
- The checked-in transfer migration creates `hr_payroll_period_settings` and `hr_payroll_payment_proofs`; the mobile API integration workflow now applies this existing migration to its disposable MariaDB before exercising transfer paths.
- The checked-in schema file was not readable through the GitHub content tool in the source-audit request (empty response), so no new schema assumptions or migration changes are justified. Existing migration DDL is the current schema evidence for the transfer tables.

## Implementation plan — completed automated scope

- [x] Added the role-protected native payroll management API and period-scoped read-only data.
- [x] Wired generation, draft amount update, approval, period/account assignment, proof upload, legacy evidence, and explicit transfer confirmation to existing payroll helpers.
- [x] Passed the authenticated API actor ID into accounting and proof-confirmation helpers while preserving browser-session defaults.
- [x] Added the Flutter payroll management page, HR/Finance/Admin navigation, and private PDF/image proof preview.
- [x] Applied the existing transfer migration in disposable MariaDB CI and tested role denial, period validation, draft recalculation, approval-only behavior, proof upload without payment, private proof access, transactional confirmation, duplicate protection, balanced journal posting, and legacy evidence immutability.
- [x] PHP lint/API integration passed in [run 40319313245](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40319313245); Flutter analysis, all 105 widget tests, APK build and publication passed in [run 40318833316](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40318833316).
- [x] Updated the session index, master status, master audit, continuation prompt, mobile parity document, and this checkpoint.
- [ ] Verify against the user's local XAMPP/API/database and physical Android device; do not claim these as passed without direct evidence.

## Acceptance boundary

Automated implementation acceptance has passed. Local runtime and physical-device acceptance remain open until verified against the intended XAMPP/API/database and Android device.

## Implementation step 1 — Shared management helpers extracted

- Added `modules/hr/lib_payroll_management.php` in commit `474ce24b9b9c4442739db640705bd6ce9cecd756`.
- Moved the existing `payrollEligibleEmployees()`, `payrollStateAtDate()`, and `payrollRefreshDraftBaseSalary()` functions without changing their function bodies, query predicates, or refresh ordering.
- Updated `modules/hr/payroll.php` to require the shared library in commit `2e5c834377e8abe9de23f538b831966f67ef54df`. This is a behavior-preserving extraction so the native API can call the same period eligibility and draft-refresh logic instead of duplicating it.
- Subsequent verification: PHP lint passed in [run 40318633490](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40318633490), with expanded API integration in [run 40319313245](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40319313245).

## Implementation step 2 — Explicit API actor support in accounting helper

- Updated `hrPayrollPostAccounting()` and `hrPayrollChangeStatus()` in commit `a29c0660004da062b85b1c7558e58ad2115aee9d` to accept an optional authenticated actor ID.
- Existing browser calls retain their Session-based fallback and default accounting initialization behavior.
- API callers can pass the bearer-authenticated user ID so new journal entries and proof-confirmation metadata record the actual actor even when no browser `Session` class is loaded.
- The API can explicitly disable accounting initialization for its call path; it must rely on already-migrated accounting tables and does not execute `ak_ensure_tables()`/seeding at request time.
- Verified by PHP lint and disposable MariaDB integration in [run 40319313245](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40319313245), including actor attribution and balanced accounting.

## Implementation step 3 — Initial native payroll management API

- Added `api/v1/hr/payroll_management.php` in commit `17e50e06c57c65b58413e3d4e95d6c1bd697a036`; corrected response-array syntax in `993275e7f3c81b64280731e7cde946f874e341b7`; enforced selected-period scope for proof upload/confirmation in `06185be9b874e43f239d464ce128d6a1f2748dd7`.
- The endpoint supports period-scoped read-only rows/account choices/policy metadata plus actions for generate, draft amount update, approval, period/account assignment, private proof upload, legacy evidence upload, and explicit proof-backed transfer confirmation.
- It checks exact raw roles matching the existing web payroll page. Mutating actions use the existing payroll/transfer/accounting helpers. It deliberately uses `hrPayrollApproveSelected()` instead of `hrPayrollApproveAll()` for mobile approval so the API path does not invoke request-time accounting table initialization.
- Transfer confirmation passes the authenticated actor ID to the shared status helper and disables runtime accounting initialization; the checked-in accounting schema must already exist. Proof upload remains `prepared` and does not mark the payroll paid.
- Updated `.github/workflows/mobile-api-auth-integration.yml` in commit `2940a305d9cde2410b7ab0f701f86b7fd410abc0` to apply the existing 2026-10-20 payroll transfer migration to the disposable test database.
- This API checkpoint is verified by the passing disposable integration and PHP lint runs listed above. Native UI and APK verification passed in [run 40318833316](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40318833316).

## Implementation step 4 — Native Flutter payroll screen and routing

- Added `mobile/ahl_el_kheir_app/lib/payroll_management_page.dart` in commit `9a3ce4e8da95d65c09a49f4ec2a64d38291ab617`.
- The screen supports period loading, payroll generation/draft refresh, draft amount editing, individual and all-draft approval, period default and employee-specific active account assignment, private proof upload, and explicit confirmation of prepared transfers. It distinguishes new transfer proof from legacy paid evidence and presents a separate confirmation dialog before the payment/accounting action.
- Added widget coverage for the separate approval/confirmation boundary and proof upload in commit `cb448a14ad37ee9af00b45f9d3c383e85d12fc6c`.
- Wired authenticated GET/POST/multipart methods and the native route in `main.dart`, restored via a Git blob/tree commit to avoid large-file truncation in the contents API: `1b02e638cccc93a9088b20573a5b865e71169763`. Added the Finance Manager payroll tool in `role_tools_section.dart` commit `35ea753944f59fb28a34eca3ad17cc0a84540c7b`.
- The first contents-API write to the large `main.dart` duplicated its suffix and failed Flutter analysis; this was traced to replacement-string interpolation of the Dart regex `r'/+$'`. The file has been reconstructed from last-known-good source `4952889a754138467d8af61077090e879096f028` plus the intended additions, committed using Git blob/tree/commit APIs, and checked to contain a single `LoginPage` declaration. The new Flutter gate is [run 40317872315](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40317872315); do not claim its analysis/widget/APK results until the run completes.
- API integration already passed in [run 40317519179](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40317519179), including exact-role denial, invalid period rejection, proof upload remaining prepared with no payment/journal, wrong-period confirmation denial, explicit actor capture, balanced posting, and duplicate-confirmation protection.

## Implementation step 5 — Private proof preview for native payroll

- Added `api/v1/hr/payroll_proof.php` in commit `319c1653cce551c3f2fae2753376b5f6ab17f56f`. It streams only supported PDF/JPEG/PNG content from the existing private proof storage, checks bearer authentication on every request, and mirrors the existing web proof-download policy: payroll/HR/finance/admin roles can view payroll evidence; an employee may view only their own proof after its state is `confirmed` or `legacy_evidence`. Prepared proofs are not released to employee self-service.
- Added the native in-app PDF/image preview to `payroll_management_page.dart` in commit `86adaa2051e00a38facfc2f382be63fa825aa37c`, and wired authenticated binary loading in `main.dart` via Git blob/tree commit `f46c2e1872bb0e340d379181eb89c43a6be4a9cf`. The view control is now covered by the payroll widget test.
- Extended disposable API integration to verify a prepared PDF is viewable by payroll staff, denied to a non-payroll role, and streamed as PDF bytes without exposing its storage key. Extended CI PHP lint to include the proof endpoint.
- Disposable MariaDB payroll API integration and PHP syntax checks passed in [run 40318619066](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40318619066) and [run 40318633490](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40318633490). The expanded API suite, including draft amount recalculation and approval-only behavior, passed in [run 40319313245](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40319313245). Verified cases include staff-role denial, invalid periods, prepared proof upload without payment/journal, denial of prepared-proof access to a non-payroll role, authorized private PDF streaming, wrong-period confirmation rejection, explicit actor attribution, balanced paid/accounting/proof confirmation, duplicate confirmation rejection, legacy evidence preserving paid/payment-date/journal history, draft component recalculation, and approval without payment/accounting posting.

- Flutter analysis and all 105 widget tests passed; Android debug APK build and artifact publication passed in [run 40318833316](https://github.com/moneermax/Ahl-El-Kheir-charity-management-system/actions/runs/40318833316) for source `339440d1747abdf4388188bcb8809cead65c3df7`. Published artifact `ahl-el-kheir-flutter-android-debug-apk` is 102,218,468 bytes, artifact ID `12647679265`, expires 2026-11-25. Uploaded artifact ZIP SHA-256: `c30a9d0fe19579c72ebc25cfd75f027d37dc2553f82631b0d81b03e2bc9916f0`. This verifies automated CI/disposable-schema behavior only; local XAMPP/API/database and physical-device acceptance remain unverified.
