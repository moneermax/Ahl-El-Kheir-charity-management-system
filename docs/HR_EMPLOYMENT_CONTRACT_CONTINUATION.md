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


## 2026-10-20 — Proposed job-description drafts and contract prefill

A follow-up migration `database/migrations/2026-10-20_hr_job_description_proposals.sql` now fills empty catalogue fields with proposed Arabic job-purpose/responsibility text for the current known positions. It only fills blank content fields, leaves existing HR-entered content untouched, does not overwrite existing approval statuses, and keeps new seed rows pending HR review. Generic qualification text is explicitly a proposal, not an official hiring requirement. The special role terms field remains blank unless HR has supplied it.

The `HR Test` catalogue entry is deactivated and remains pending review; no employee record is deleted. The employee account itself remains in the employee list unless separately managed through the normal employee workflow.

The contract builder now also pre-fills the supervisor field from the catalogue's `reporting_to` value when available. This is labeled as a proposed reporting line and must be confirmed by HR. Existing employee identity, department, position and current salary prefill remain intact. No nationality, contract dates/term, probation, hours, working days, location, allowances or other negotiated terms are inferred from a position alone.

**Verification boundary:** source committed on `main`; the new migration must be applied to `ahl_el_kheir`, followed by PHP syntax checks and a focused local browser check of the catalogue and contract prefill. Do not mark runtime verified until actually checked. Proposed job descriptions remain `needs_review`; Sudanese legal review remains required before official contract use.

## 2026-10-20 — Role-aware system-workflow descriptions and responsibility lists

The follow-up changes now distinguish the HR job title (`employees.position`) from the linked user's application role (`employees.user_id` → `users.role_id` → `roles.code`). This matters because a generic position such as `موظف` can be held by users with different system responsibilities. The new migration `database/migrations/2026-10-20_hr_job_description_system_roles.sql` adds `system_role_code` to the catalogue, changes uniqueness to position + system role, and seeds proposed role-specific descriptions from documented workflows for the current linked employee/role combinations. Existing title-only entries remain fallback records.

The follow-up migration `database/migrations/2026-10-20_hr_job_description_z_clear_unreviewed_generic_drafts.sql` removes only exact, unchanged generic proposal placeholders created by the earlier proposal migration. It preserves edited/approved content and clears only exact placeholder values. Apply the migrations in filename order; on the already-migrated local DB, apply `2026-10-20_hr_job_description_system_roles.sql` first, then `2026-10-20_hr_job_description_z_clear_unreviewed_generic_drafts.sql`.

`modules/hr/job_description_catalogue.php` now lets HR maintain responsibility items in individual fields, add/remove items, and optionally scope a description to an application role. Each saved item is stored as a newline-separated item in the existing TEXT column; no additional responsibilities table is needed. `modules/hr/contract_builder.php` selects the best matching position + role description first, falls back to the title-only description, displays the linked system role in the employee selector, and renders responsibilities as an indented HTML list in the generated PDF.

These descriptions are proposed from documented system workflows and the user's assigned application role; they do not prove that an employee actually performs every accessible action. HR and the direct supervisor must confirm day-to-day duties. All seeded descriptions remain `needs_review`; no legal or HR approval is implied. Local migration application, PHP lint, catalogue browser interaction, and PDF output verification remain pending.

## 2026-10-20 — Job-description save feedback toast

The job-description catalogue now presents successful-save feedback as a fixed Arabic RTL toast instead of an inline success alert. The toast is announced through a status region, can be dismissed, and automatically hides after five seconds. Validation and save errors remain inline so they stay visible beside the form. The save/redirect flow and approval behavior are unchanged. PHP lint and local browser verification are required before marking this UI refinement runtime verified.

## 2026-10-20 — Job-description item fields and supervisor selectors

Source changes committed directly to `main`:
- `6aecdb28e7abfbd58a8242090137ddfb1e09c89e` — separate editable item rows for role purpose, responsibilities, qualifications and optional role-specific terms; add/remove controls; reporting-line options sourced from distinct existing employee position titles.
- `21ee08af28a642800236d75369a69931b52a93a3` — persist the item lists as newline-separated text in the existing TEXT fields (no schema migration or runtime DDL).
- `ab2079268dfe72551613475aaa0e3bbc72478fc4` and `e97a1b5a1623b194d9f29e6559a71bc43e761b05` — direct-supervisor selector sourced from existing employee records, no longer guessed from the catalogue reporting title; prevent selecting the contract employee as their own supervisor; purpose, responsibility and qualification items render as separate list entries in the contract preview/PDF.

The existing “الأوصاف حسب المسمى الوظيفي ودور الحساب” catalogue table remains in place. Existing proposed purpose/responsibility text is preserved; no unsupported qualifications or optional contract clauses are invented. Proposed descriptions remain subject to HR review and approval, and the contract remains subject to qualified Sudanese legal review.

**Verification status: SOURCE UPDATED; LOCAL VERIFICATION REQUIRED.** No local XAMPP runtime or PHP CLI was available to this session, so do not mark the feature runtime verified. The GitHub Actions run triggered by the latest source commit reported failure, but its job steps/logs were not retrievable through the available GitHub connection; this is not evidence of a PHP syntax failure or pass. Local verification must run PHP lint and focused browser checks before acceptance.


## 2026-10-20 — Contract builder repeatable duties and special terms

On `modules/hr/contract_builder.php`, the two requested contract fields are now repeatable lists: **المهام والمسؤوليات والوصف الوظيفي** and **الشروط الخاصة المتفق عليها / البنود المرتبطة بالمسمى**. Each row has its own text area and add/delete controls. When an employee is selected, existing job-description and role-specific-term lines populate separate rows; submitted rows are combined with newline separators for the existing PDF rendering. No schema change or runtime DDL was introduced. Source commits: `05171ee43e74269a9ae397e9176fbb59e13544d9` and `61fd28bb49528d823b8e41e8a1cd59ad69e76d29`.

**Verification status: SOURCE UPDATED; LOCAL VERIFICATION REQUIRED.** PHP lint and browser/PDF checks have not been run in the local XAMPP application. After pulling `main`, verify adding, deleting, changing employee selection, and confirming all retained entries appear in the generated PDF.
