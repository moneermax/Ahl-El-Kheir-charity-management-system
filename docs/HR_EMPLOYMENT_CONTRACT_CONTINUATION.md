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


## 2026-10-20 — Repeatable contract fields: blank-selection edge case

Source commit `7b0f675700651ea295569e3c410b44c7e39a679d` clears both repeatable lists and resets the job-description status message when the employee selector is returned to its blank option, preventing duties or special terms from the previously selected employee remaining visible. The fix is source-committed only. Local PHP lint, browser interaction, and PDF rendering remain unverified; do not mark the contract builder complete until the focused local checks pass.

## 2026-10-20 — Role-based qualification drafts from researched NGO job profiles

Added data-only migration `database/migrations/2026-10-20_hr_job_description_zz_role_qualifications.sql`. Apply it after the earlier 2026-10-20 job-description catalogue, proposals, system-role and generic-draft cleanup migrations, in filename order.

The migration:
- removes the known “مسودة مستندة إلى مسارات النظام” seed prefix from unapproved role-purpose drafts so the field reads as the actual proposed purpose;
- replaces only exact generic placeholder text for the generic `موظف` row and unknown-role fallback with useful draft wording;
- fills blank or exact generic-placeholder qualifications with role-specific, research-informed proposed qualifications for management, finance/accounting, HR, IT administration, project management/supervision, sponsor supervision, family/child support, administration, general staff and communications/social media;
- retains HR-authored non-empty qualifications, leaves approved catalogue records untouched, does not change approval states, and does not create runtime DDL.

Qualification wording is deliberately framed as a proposed profile (degree/training or equivalent experience where suitable; relevant skills and experience), not a mandatory legal or hiring condition. All modified descriptions remain pending review unless already approved, and approved rows are not changed by this migration.

Research references used to shape common qualification profiles:
- Saferworld, Finance Officer — Sudan (accounting/finance degree, accounting controls, NGO operations, software, confidentiality and communication): https://www.saferworld-global.org/downloadfile.php?filepath=downloads%2Ffinance-officer-sudan.pdffinance-officer-sudan-final-april-2021.pdf
- CARE Egypt, Project Accountant (finance/accounting degree, NGO experience, Excel/ERP and Arabic/English): https://care.org.eg/wp-content/uploads/2024/05/Project-Accountant_Upper-Egypt.pdf
- UN Careers, Human Resources Assistant (role-family reference): https://careers.un.org/jobSearchDescription/286436?language=en
- UN Careers, Information Technology Assistant (systems/network/security support reference): https://careers.un.org/jobSearchDescription/282210
- UN Careers, Communications Assistant (public information/communications reference): https://careers.un.org/jobSearchDescription/286125?language=en
- UN Careers, Programme Management Assistant (project coordination, reporting, stakeholder and budget tracking reference): https://careers.un.org/jobSearchDescription/278900

These sources are benchmarks, not a claim that Ahl El Kheir must adopt another organization's minimum criteria. HR and the direct supervisor must confirm actual day-to-day duties, required education and experience, reporting line and role-specific conditions before approval. A system role documents workflow access; it does not prove which tasks an employee has personally performed.

**Verification status: SOURCE COMMITTED; local migration application, PHP lint, catalogue browser check and contract PDF check remain pending.**


## 2026-10-20 — Separate purpose/responsibility draft fields in contract builder

Source commits on `main`: `73b91d1a40024564d7399a80ae91b011167dbf9c`, `8fc40930c4224f04dab7e1f8c9c96c4fdfafd4b7`, `ec565758660214ac371ef14b692b4265249fc2b7`, and `4d3d030bc4b96537141eec16fc6e3e0e86bce05d`.

Root cause: the contract builder had collapsed catalogue `role_purpose` and `responsibilities` into one repeatable `duties` control. Therefore the agreed `الغرض من الوظيفة` field did not exist in the contract UI, even though the catalogue stores that content separately.

The builder now renders separate repeatable controls for:
- `الغرض من الوظيفة` from `role_purpose`;
- `المهام والمسؤوليات الأساسية` from `responsibilities`;
- `المؤهلات والخبرات المطلوبة` from `qualifications`;
- `جهة الإشراف الوظيفي` from `reporting_to`.

The employee selector supplies each field from the best matching position + system-role template, falling back to the generic position template. Switching employees refreshes all four fields; selecting the blank option clears them. Existing role-specific special terms remain separate. PDF generation rebuilds the job-description sections from the edited values and keeps the current pending-review warning. Existing database schema and catalogue content are unchanged; no migration/runtime DDL was added.

**Verification status: SOURCE COMMITTED; local PHP lint, employee-switch browser check, and PDF inspection remain pending.** The repository connection cannot verify the user's XAMPP runtime. Next: pull `main`, lint `modules/hr/contract_builder.php`, then test two employees with distinct templates and verify purpose/responsibility/qualification sections in the PDF. Do not rerun migrations for this UI-only change.
