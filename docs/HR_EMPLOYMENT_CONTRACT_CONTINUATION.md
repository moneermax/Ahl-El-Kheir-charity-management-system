# HR Employment Contract Builder — Continuation

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Current source checkpoint:** 2026-10-20  
**Scope:** In-system editable Arabic employment contract form and PDF generation; no database mutation in this stage.

## User requirement

HR should prepare the employment contract inside the existing application, edit the contract-specific fields, open the generated PDF, and print it for review/signature. A separate Word master file is not required. The signed copy must later be attached to the correct employee record through an authorized private document workflow.

Sudanese law is the governing framework. Humanitarian/NGO good practice may supplement the contract only where compatible with mandatory law. The contract wording is not legally approved; a qualified Sudanese lawyer must review it before official use. Do not invent statutory leave, probation, notice, termination, or benefit entitlements.

## Source inspection

- Existing contract/salary management page: `modules/hr/contracts.php`.
- Existing contract and effective-dated salary helpers: `modules/hr/lib_contract_salary.php`.
- Existing contract metadata includes `contract_file_path`; current upload code accepts PDF/JPG/PNG and stores under `storage/hr_contracts/`.
- The application bundles TCPDF at `TCPDF/tcpdf.php` and Arabic font assets.
- `storage/.htaccess` denies direct web access to storage. Existing contract list currently constructs a direct URL to `contract_file_path`; this is a pre-existing document-access concern and must be reviewed before changing or extending signed-document delivery.
- The source schema dump `database/ahl_el_kheir.sql` was not retrievable through the repository file connector because of its size. No schema changes are included in this source stage; local schema inspection is still required before any database integration.

## Current implementation checkpoint

Added `modules/hr/contract_builder.php` on `main` (commit `f0e5fdba4b32e179091478b092eccd5dd10f033f`).

The page:
- restricts access to signed-in `hr_manager`, `hr_staff`, and `admin` users;
- loads employee options from existing employee/department records;
- pre-fills known identity fields from the selected employee, while allowing HR to edit the contract fields before PDF generation;
- validates CSRF, employee selection, required fields, date values/range, contract type, pay frequency, and non-negative numeric salary;
- generates a printable A4 Arabic RTL PDF using the bundled TCPDF library;
- includes job details, term/dates, salary/pay frequency, working arrangements, leave fields, safeguarding/PSEA, conduct, confidentiality/data, conflicts/fraud, health/safety, termination, disputes, governing law, and signature/witness areas;
- does not save draft contract text, alter contract status, change salary history, or mutate employee records.

A link from `modules/hr/contracts.php` was attempted but the repository write operation was blocked. The builder is currently reachable directly at `modules/hr/contract_builder.php`; adding a normal navigation link remains pending.

## Verification boundary — NOT YET CLOSED

No local XAMPP/browser execution or PHP syntax check has been performed for the new page. Do not claim this feature is runtime-verified. Before operational use:

1. Sync `main` locally and run `php -l modules/hr/contract_builder.php`.
2. Open `http://localhost:8081/AhlElKheir/modules/hr/contract_builder.php` as an authorized HR role.
3. Verify employee selection and prefill, edit fields, PDF opens in a new tab, Arabic RTL text and logo render correctly, multi-page pagination works, and browser print output is legible.
4. Verify an unauthorized role receives HTTP 403.
5. Verify CSRF failure is rejected.
6. Confirm PDF generation creates no database or storage changes.
7. Have a qualified Sudanese lawyer review the wording before official use.

## Next stage — signed contract and employee onboarding integration

Only after PDF rendering is locally verified, inspect the live local schema and existing account creation transaction. Integrate the contract preparation and signed-copy upload into the appropriate HR onboarding flow with minimal changes. Keep signed files private, use a permission-checked download endpoint rather than a public storage URL, validate detected MIME/size, generate opaque server-side filenames, prevent path traversal/overwrites, and preserve employee/contract association. Do not add columns or migrations until the actual schema and required persistence behavior are established.


### 2026-10-20 — Input-validation refinement

Follow-up commit `eef4d17e02543e1327ed8cc3db9fee3b916aa11f` adds server-side maximum lengths for contract fields, email validation, weekly-hours range validation, and a required end date for fixed-term contracts. Nationality is no longer prefilled with an assumed value. This remains source-only; local lint and browser/PDF verification are still pending.

### 2026-10-20 — Generated PDF privacy headers

Commit `1578984554da21e3ad8c6b69695fe4d44913a473` adds private/no-store cache headers and `X-Content-Type-Options: nosniff` before inline PDF output. This is a source hardening change only; it is not runtime verification.