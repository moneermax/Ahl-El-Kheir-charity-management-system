# HR Employment Contract Portal — Continuation

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Branch:** `main`  
**Checkpoint:** 2026-10-20  
**Status:** Source implementation committed; migration application and local runtime/PDF verification remain pending.

## Agreed design

- Sudanese law is the governing framework. NGO/international humanitarian safeguards may supplement it only where compatible with mandatory Sudanese law.
- Contract wording is not legally approved. A qualified Sudanese lawyer must review the final wording before official use. Do not invent statutory leave, probation, notice, termination or benefit entitlements.
- Arabic RTL, formal navy-and-gold design, official logo and organization details from existing settings where configured.
- HR selects an employee; the system uses that employee's actual `employees.position` to load the matching role description and role-specific contract clauses.
- Shared PDF layout and standard clauses are maintained separately from role-specific content. The job-description catalogue can be edited without changing the PDF layout.
- Catalogue rows seeded from existing employee positions start as `needs_review`; no duties or qualifications are invented or marked approved.
- Existing `modules/hr/contracts.php` remains the contracts/salary-history page and signed-file upload workflow. The new portal is a separate dashboard shortcut.
- Do not map application permission roles to employee positions.

## Implemented on main

- `database/migrations/2026-10-20_hr_job_description_catalogue.sql`
  - Creates `hr_job_description_templates`.
  - Seeds distinct existing employee positions without fabricated job content.
  - Includes role purpose, responsibilities, qualifications, reporting line, role-specific contract terms, review status and activation flag.
  - No runtime DDL, triggers, views or stored procedures.
- `modules/hr/job_description_catalogue.php`
  - HR Manager, HR Staff and Admin access checks.
  - Maintains job descriptions and optional role-specific contract clauses.
  - HR Manager/Admin can mark descriptions internally approved.
  - Records changes in the existing audit log when available.
  - Prevents renaming a catalogue title while employees still use that exact position; this protects the position-to-description match.
- `modules/hr/contract_builder.php`
  - Uses actual employee position as the source of role matching.
  - Loads the selected role description and role-specific terms.
  - Marks descriptions requiring review in the page and generated PDF.
  - Uses configured organization logo/name/address/support phone/email when those settings are populated; falls back to the repository logo and organization name.
  - Repeats organization branding/contact details in the TCPDF header on multi-page contracts and retains the PDF footer.
  - Refreshes employee identity, position and current basic-salary prefill when HR changes the employee selection.
  - Keeps contract PDF generation separate from saving employment contracts or changing salary history.
- `dashboard/hr_dashboard.php`
  - Adds separate `بوابة عقود العمل` shortcut; existing `العقود والرواتب` remains intact.

## Current employee-position inventory

The local inventory previously supplied for design included: `HR Test`, `أخصائية شؤون الأمهات`, `المدير العام`, `المدير المالي`, `محاسب`, `مدير الموارد البشرية`, `مدير النظام`, `مشرف`, `مشرف مشروع`, `مندوب استرجاع الكفلاء`, `موظف`, `موظف العلاقات العامة والإعلام`, and `نائب المدير العام`. The migration seeds whatever distinct non-empty position values actually exist when it is run. Do not assume `HR Test` is a real production role or delete it without inspecting its employee record.

The existing role/workflow matrix documents broad system-role responsibilities, not approved detailed job descriptions. These are not to be copied as if they were complete position descriptions. HR must complete and approve the seeded catalogue records.

## Verification boundary

The changes above are source-committed, but this is **not yet runtime verified**. The latest GitHub PHP syntax workflow should be checked for a completed result. Local XAMPP verification is still needed after applying the migration:

1. Pull `main`.
2. Apply `database/migrations/2026-10-20_hr_job_description_catalogue.sql` once to the configured `ahl_el_kheir` database.
3. Run PHP syntax checks for `modules/hr/contract_builder.php`, `modules/hr/job_description_catalogue.php`, and `dashboard/hr_dashboard.php`.
4. Open the HR dashboard at `http://localhost:8081/AhlElKheir/`; verify the separate portal shortcut.
5. Open the catalogue, fill and approve one description, then select an employee with that exact `employees.position`; confirm description and role-specific terms load.
6. Generate a PDF and inspect Arabic RTL, configured logo/contact line, multi-page layout and the warning for unapproved descriptions.
7. Confirm unauthorized roles receive HTTP 403. Do not alter or rerun unrelated closed-module tests.

The existing signed-copy upload workflow remains on `modules/hr/contracts.php`; the portal returns there with the selected employee. Review the existing storage access concern before expanding signed-document download/serving behavior.

## Legal and content boundary

A seeded row marked `needs_review` is a catalogue placeholder, not an approved job description. Do not treat a PDF generated with that warning as an officially approved employment instrument. A qualified Sudanese lawyer must review the contract language before official use.
