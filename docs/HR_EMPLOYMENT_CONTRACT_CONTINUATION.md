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
- `modules/hr/contract_builder.php` is the single entry point for creating a new contract PDF and reserving its draft number. `modules/hr/contracts.php` is the contract register and salary-history management page; it does not create new contracts. Signed copies are uploaded to an existing contract record from the register.
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

Source commits on `main`: `73b91d1a40024564d7399a80ae91b011167dbf9c`, `8fc40930c4224f04dab7e1f8c9c96c4fdfafd4b7`, `ec565758660214ac371ef14b692b4265249fc2b7`, and `4d3d030bc4b96537141eec16fc6e3e0e86bce05d`, `59332e20a7d186eae3f969a9a94c24b1e2225d44`, `59ecd7b5057d9fe2910ccc3314b073d305cc19d6`.

Root cause: the contract builder had collapsed catalogue `role_purpose` and `responsibilities` into one repeatable `duties` control. Therefore the agreed `الغرض من الوظيفة` field did not exist in the contract UI, even though the catalogue stores that content separately.

The builder now renders separate repeatable controls for:
- `الغرض من الوظيفة` from `role_purpose`;
- `المهام والمسؤوليات الأساسية` from `responsibilities`;
- `المؤهلات والخبرات المطلوبة` from `qualifications`;
- `جهة الإشراف الوظيفي` from `reporting_to`.

The employee selector supplies each field from the best matching position + system-role template, falling back to the generic position template. Switching employees refreshes all four fields; selecting the blank option clears them. Existing role-specific special terms remain separate. PDF generation rebuilds the job-description sections from the edited values and keeps the current pending-review warning. Existing database schema and catalogue content are unchanged; no migration/runtime DDL was added.

**Verification status: SOURCE COMMITTED; local PHP lint, employee-switch browser check, and PDF inspection remain pending.** The repository connection cannot verify the user's XAMPP runtime. Next: pull `main`, lint `modules/hr/contract_builder.php`, then test two employees with distinct templates and verify purpose/responsibility/qualification sections in the PDF. Do not rerun migrations for this UI-only change.


## 2026-10-20 — Distinguish empty general fallback descriptions

The employee-wide catalogue audit showed that current linked employees generally match active role-specific templates, while the title-only record for **أخصائية شؤون الأمهات** (id 6) is an empty general fallback and the nanny-specific draft is a separate active record (id 20). Employee 21 (**HR Salary Test**) has no matching active template; the existing HR Test catalogue entry was intentionally deactivated, so this must not be fixed by blindly reactivating it or deleting employee data.

Source commit `6264c897b2bbadb460e0ba0d3e0c029b1443312d` updates `modules/hr/job_description_catalogue.php` to:
- label title-only entries as general fallback descriptions;
- show how many active role-specific descriptions exist for the same position;
- flag an empty general fallback when role-specific records exist; and
- show a contextual warning while editing that fallback record.

This is a catalogue clarity change, not a data repair. It preserves role-specific-first contract selection, existing draft content, activation flags and approval states. **Local PHP lint and browser verification remain pending; do not mark runtime verified until checked through the configured XAMPP application.**


### Follow-up: fallback employee-count consistency

Commit `4435226e37405e9a2963dd07af0d1c14f71e7f92` makes the catalogue's general-template employee-count condition treat both SQL NULL and empty `system_role_code` as a general fallback. This aligns the count with the contract builder's existing PHP behavior, which casts NULL to an empty string. The source was re-fetched from `main` and the change is present. The commit has no reported status checks in the GitHub status response; local PHP lint and browser verification are still required.
## 2026-10-20 — Restore editable drafts in general fallback descriptions

The catalogue screenshot exposed a usability gap: the earlier `2026-10-20_hr_job_description_z_clear_unreviewed_generic_drafts.sql` deliberately cleared exact generic title-only proposals, leaving general fallback forms with empty purpose/responsibility fields. Role-specific records remained separate, but HR still needs a useful editable starting draft when opening a general fallback.

Added data-only migration `database/migrations/2026-10-20_hr_job_description_zzz_general_fallback_drafts.sql` (commit `5b6a553b22ff5a0be3d0a2b6430556ccf5138be9`). It restores distinct, broad position-level draft purpose and responsibilities only for active, title-only records that remain `needs_review` and have blank fields. It does not copy role-specific text into fallback rows, overwrite non-empty content, alter approved/inactive records, change qualifications/reporting lines, reactivate `HR Test`, or modify employees/schema. All text remains an unapproved proposal for HR and direct-supervisor confirmation.

**Next:** pull `main`; apply this new migration once to the configured `ahl_el_kheir` database using an explicit UTF-8 client; run a read-only check that active title-only records now have non-empty purpose/responsibilities while all statuses remain `needs_review`; lint the catalogue PHP file and verify the page and contract-builder employee switching locally. Do not rerun earlier migrations. Runtime verification is pending.


## 2026-10-20 — Baseline qualifications and reorderable item lists

The requested baseline qualification items are added by the data-only migration `database/migrations/2026-10-20_hr_job_description_zzzz_qualification_baseline.sql`, after the existing job-description proposal/qualification migrations:
1. `الحد الأدنى للمؤهل الأكاديمي: شهادة الثانوية العامة أو ما يعادلها.`
2. `معرفة أساسية باستخدام الحاسوب وبرامج المكتب.`

The migration prepends these two items to active job-description templates while preserving existing qualification text. Changed descriptions are returned to `needs_review`; the new baseline is not silently approved. Apply it once after the earlier 2026-10-20 job-description migrations, including `2026-10-20_hr_job_description_zz_role_qualifications.sql` and `2026-10-20_hr_job_description_zzz_general_fallback_drafts.sql`.

The job-description catalogue and contract builder now expose a drag handle on repeatable purpose, responsibility, qualification and optional-term rows. Rows can be rearranged with pointer-based dragging (mouse or touch), and visible numbering updates automatically. The submitted list order is the displayed/PDF order. Existing add/delete behavior remains.

**Verification status: SOURCE COMMITTED; local migration application, PHP lint, catalogue/contract browser interaction, employee switching and PDF output remain to be verified on the user's XAMPP application.** Do not claim runtime verification until the user confirms these checks. HR and the direct supervisor must review the qualification baseline internally; Sudanese legal review remains required before official contract use.


## 2026-10-20 — Automatic contract number reservation during PDF generation

User-approved behavior: generating the employment contract PDF also creates a draft in the existing contract register, reserving the official number and avoiding manual entry.

Source commit on main: 8ed43dc8c7e0f2e2c5693e1777bfb8324bdc3115.

- The contract-number field is read-only; the client cannot submit the official number.
- The builder inserts a draft row into hr_employee_contracts, then derives CTR-YYYY-000001 from the saved row ID and the contract start year.
- The employee row is locked while the existing overlapping draft/active contract rule is checked and the draft is inserted.
- The PDF is rendered to memory before the transaction commits. If PDF rendering or the transaction fails, the draft insert is rolled back.
- A one-use session token prevents duplicate draft creation when the same form is resubmitted.
- The builder deliberately does not call hrCreateEmployeeContract(): that helper writes salary-history segments and can update employees.basic_salary and employment state for a current/past start date. The new builder path leaves those fields unchanged; the existing explicit contract activation workflow remains responsible for activating the draft and applying salary/employment changes.
- No schema change or migration was required. The draft record stores the register's existing core fields and weekly hours when supplied; the full editable contract clauses remain in the generated PDF response and are not persisted as database columns by this change.

Verification status: SOURCE COMMITTED, NOT RUNTIME VERIFIED. On the configured Windows/XAMPP environment, pull main; lint modules/hr/contract_builder.php; generate a PDF from http://localhost:8081/AhlElKheir/modules/hr/contract_builder.php; compare the displayed number with the draft in modules/hr/contracts.php; verify the record remains draft; compare salary history, employees.basic_salary, and employment state before/after; verify overlapping draft/active contract periods are rejected; and verify resubmitting the same form does not create another record. Do not rerun unrelated completed module tests. Sudanese legal review remains required before official use.


## 2026-10-20 — Unify contract creation and register workflow

Source changes committed directly to `main`:
- `315f221c9f43d4bbbe55b0f2acdce88e3b403296` — removed the duplicate `add_contract` creation path from `modules/hr/contracts.php`. New-contract links now open `modules/hr/contract_builder.php` with the selected employee. The old `?view=add` URL now explains the canonical workflow instead of presenting a second creation form.
- `b312f117f077f14e8c5f4f91bd80a4aeaf0c2ebf` — the builder's return link now returns to the employee's contract-register view, not the retired add form.
- `dd2b1dcc99762226e1ddf8a28000e6fc4a7d5190` — added an explicit optional probation-end date to the builder, validates it against the contract start date, includes it in the PDF text, and persists it to the draft's existing `probation_end_date` column so the register's existing activation/employment-state behavior remains available.

The register continues to manage contract status and salary history. It now uploads or replaces a signed copy against an existing contract record, rather than creating a new contract as a side effect of uploading a file. The upload validates that the contract belongs to the selected employee and retains the existing 10 MB / PDF-JPG-JPEG-PNG limits. Replacing a file updates the register path but deliberately does not delete older files.

No schema change or migration was introduced. The builder continues to reserve the official number and create a draft when PDF generation succeeds; this does not activate the contract or update salary history/employment state. The full editable clause text remains in the generated PDF, while the register stores its existing core fields.

**Verification status: SOURCE COMMITTED; PHP lint, browser interaction, signed-copy upload, draft activation, salary-history/employment-state behavior, and generated PDF are NOT runtime verified.** Pull `main`, lint both changed PHP files, then test one non-production/appropriate employee record through the configured XAMPP URL. Do not create a second draft for an employee/date period already covered by an active or draft contract. Sudanese legal review remains required before official use.


## 2026-10-21 — Single HR contract dashboard entry point (source committed; local verification pending)

The dashboard now has only one contract-related shortcut, `بوابة عقود العمل`, targeting `modules/hr/contract_builder.php`. The separate dashboard card for `modules/hr/contracts.php` was removed. The portal header links to the contract register/salary-history workflow and the job-description catalogue. The register is not deleted because it still implements signed-copy upload/replacement, draft activation, active-contract termination/status handling and salary-history changes. The catalogue already provides a return link to the portal.

Commits: `be9c2515ec0f387406f4e54180e103d2985869bf` and `f3263b20eb666de05d096bc6d30d0143e92b15b7`. No migration or database mutation was introduced. **Local verification remains pending.** Next: lint the two changed PHP files and confirm dashboard/portal/register/catalogue navigation in the real XAMPP app. Only consider deleting/redirecting the legacy register after its needed functionality is integrated and verified. Existing pending migration and job-description/PDF runtime checks remain outstanding; do not mark the broader contract feature complete.

## Contract PDF preview follow-up — 2026-10-21
- Runtime report from the local contract builder showed an `Undefined array key "reporting_to"` warning during PDF generation. Source inspection found `reporting_to` was listed in the field-length validation limits but absent from the `$data` array.
- Corrected `modules/hr/contract_builder.php` on `main` by adding `reporting_to` to the validated data array (commit `86bb2343fd19ed1455420dcc9c4775a07cda4605`). This is source-committed but not yet locally linted or runtime-verified.
- The UI also reported an overlapping draft/active contract for the selected employee and period. Treat that as a separate safeguard; inspect the relevant employee's contract register before retrying, and do not bypass the overlap check.
- Next: user pulls `main`, PHP-lints the builder, then chooses an employee/period with no conflicting draft or active contract to generate and inspect the actual PDF design. If the conflict persists unexpectedly, capture the selected employee and inspect the register before changing logic.


## 2026-10-21 — Employment contract PDF design correction (source only)

The user rejected the first generated PDF because it did not reflect the previously agreed formal Arabic RTL navy-and-gold design. Source commit `ed3e4abf0bf4c3ea906ee21c30d9c2b6b465cc3c` updates `modules/hr/contract_builder.php` to add a distinct cover page with organization branding, contract number, employee/position and formatted dates; strengthen navy/gold section styling; isolate the signature/acknowledgment block on a dedicated final page; format dates as DD/MM/YYYY; and explicitly isolate Latin employee names, contract numbers and salary/currency text for more reliable bidirectional rendering. Existing Sudanese-law-first clause wording, NGO safeguarding terms, draft reservation, overlap guard and non-activation workflow remain intact. No schema change was made.

**Verification status: SOURCE COMMITTED; NOT LOCALLY VERIFIED.** The reported PDF also showed an apparently blank contract number and inconsistent Latin employee-name order. The source now explicitly presents the reserved number and isolates Latin text, but only a fresh local PDF can confirm TCPDF renders these correctly. User must pull `main`, lint `modules/hr/contract_builder.php`, and generate a PDF for an employee/date range with no overlapping draft/active contract. Inspect cover, number, name consistency, currency/date direction, page breaks and signature page. Do not create another draft for a covered period, bypass overlap protection, or claim runtime success before evidence.

## 2026-10-21 — Contract PDF aligned to existing voucher layout (source committed; runtime pending)

The user explicitly requested the employment contract PDF follow the general layout/design of existing financial vouchers. The concrete source reference is `modules/accounting/voucher_print.php`: bilingual organization identity, boxed document-number/date panel, strong navy title bar, structured bordered/shaded information tables, consistent accent colors, and clear signature blocks. Commit `1cd7a318cb1f6075f66badfe235ba758aab4b527` updates `modules/hr/contract_builder.php` to remove the standalone decorative cover page and instead start the contract with a voucher-style title/number/date panel and employee/position/date summary, apply the voucher navy (#1b4d8f), muted shaded table labels and gold (#9a7628) accent to clause headings and signature tables, and use the same official Arabic/English organization names as the voucher print page. The legal clauses, draft reservation, overlap guard, and explicit activation workflow remain unchanged; no schema change was made.

**Verification status: SOURCE COMMITTED; PHP lint and actual local PDF rendering NOT verified.** User must pull `main`, lint `modules/hr/contract_builder.php`, then inspect a valid existing draft/eligible preview without generating another contract for an overlapping employee/date range. Confirm there is no oversized standalone cover, contract number/date panel renders correctly, header and names match the voucher branding, clause bars/tables are readable, no warning appears, and the final signature page remains usable. Do not claim visual acceptance until the local PDF has been inspected.

## 2026-10-21 — Employment contract branding correction (source committed; local PDF verification pending)

After the user reported that the revised PDF still looked unprofessional and had no visible organization logo, source inspection found the contract depended on TCPDF's generic header for the logo while the main contract body did not explicitly embed it. The PDF also included an internal HR job-description review warning inside the employee-facing contract. Source commits `7619773e52cb2e62bcde0721fa750b7913161689` and `19a79e00d2747616609edbd5a82b36a9b66cfc96` now use the same repository logo as the voucher print page (`assets/img/logo.png`), explicitly embed a bilingual branded letterhead in the contract body and signature page, avoid a duplicate generic TCPDF header, and remove the internal review warning from the contract PDF. HR review status remains a builder/catalogue concern; this change does not mark any job description approved. No database/schema or contract lifecycle changes were made.

**Verification status: SOURCE COMMITTED; NOT LOCALLY LINTED OR RENDERED.** User must pull `main`, run PHP lint, and inspect the actual PDF before acceptance. Confirm the logo is visibly embedded, there is one clean organization letterhead (not duplicate branding), the contract number/date and Latin text render correctly, the internal review warning is absent from the employee-facing PDF, clause styling and page breaks remain readable, and the signature page is properly branded. Do not create a new contract solely to test if an overlapping draft/active contract already exists; preserve the overlap guard and existing records.


## 2026-10-21 — Contract PDF logo compatibility check

Read-only inspection of the current `main` source confirmed the existing browser-facing shared print header and the accounting, salary-advance, and repayment voucher pages use `assets/img/logo.png`. Organization settings and the contract-builder portal instead honor the configured `site_logo` (currently `assets/uploads/logo.png`). The contract PDF had diverged by embedding `assets/img/logo.png` directly in TCPDF HTML. The user confirmed `assets/img/logo.jpg` is the same organization logo. The PDF now uses that existing JPEG solely for TCPDF rendering; browser logo sources, configured settings, contract styling, database schema and contract lifecycle remain unchanged.

**Verification status: SOURCE UPDATED ON MAIN; local PHP lint and actual PDF rendering are still required.** Do not create another contract if the chosen employee/date period is already covered by a draft or active contract.


## 2026-10-21 — Second contract PDF design revision (voucher-style hierarchy; runtime pending)

Following user feedback that the employment contract still looked weaker than the existing vouchers, `modules/accounting/voucher_print.php` was re-read as the design reference. The contract's first-page composition has been revised to use a single bordered three-part letterhead (logo, centered bilingual organization identity, and boxed contract number/date), a full-width navy bilingual title bar, a compact employee/position and term summary, and a dedicated highlighted basic-salary band inspired by the voucher amount panel. Clause headings now use a pale-blue panel with navy text and a gold rule instead of a heavy filled bar, to improve readability across long legal text. Existing clauses, job-description content, signature page, logo JPEG compatibility fix, draft reservation/overlap guard, and activation workflow are preserved. No schema or accounting changes.

**Verification status: SOURCE COMMITTED; local PHP lint and actual PDF visual inspection are still pending.** The rendered PDF must be inspected before deciding whether this revision is accepted; do not create another overlapping contract draft just to preview the design.

## 2026-10-21 — Contract PDF: one letterhead and compact two-page target

Source commit `ddffae6105a2da56bce1b5d706bc7d40e1f94ba2` updates `modules/hr/contract_builder.php` to keep the organizational letterhead on the first contract page only; the signature page no longer repeats the logo/organization/number/date header. The PDF body typography, section spacing, table padding, logo size, page margins and page-break reserve were reduced to make the legal clauses fit on page one while retaining the separate signature page as page two. No schema, migration, contract lifecycle or accounting behavior changed.

**Verification status: SOURCE COMMITTED; PHP lint and local PDF page-count/visual verification are pending.** Pull `main`, run `D:\xampp\php\php.exe -l modules\hr\contract_builder.php`, and inspect the generated PDF through the configured XAMPP app. Confirm the letterhead appears only on page one and the complete contract plus signature page totals exactly two pages. If content still spills to a third page or text is too small to read, capture the actual PDF result and adjust from evidence; do not claim two-page runtime success before inspection. Do not create a new draft for an overlapping employee/date range.

## 2026-10-21 — Contract PDF header matched to voucher composition (source committed; runtime pending)

After the user confirmed the contract PDF still occupied three pages and described the letterhead as too tall, the voucher source `modules/accounting/voucher_print.php` was inspected directly. Its outer composition is a compact logo/organization/number-date row, thin accent divider, navy bilingual title bar, and tightly grouped document details inside a bordered voucher. Commit `f30fd321ae7932daa2e87b7b70be8e3f97d437a3` revises `modules/hr/contract_builder.php` toward that composition: logo reduced to 24px, narrower logo and number/date columns, smaller bilingual organization text, compact number/date box, smaller title band, tighter document margins and body typography, and reduced table/paragraph/section spacing. The signature page remains without a repeated organization header. No schema or lifecycle change.

**Verification status: SOURCE COMMITTED; local PHP lint and actual PDF page count/readability are pending.** Pull `main`, lint the builder, and inspect the PDF. Confirm the letterhead appears only on page one and the total is two pages. Do not claim the page-count target until the generated PDF is verified.

## 2026-10-21 — Contract PDF header proportion correction

Source inspection found the logo was rendered at 24 mm inside a 10%-width header cell, making it wider than its own column and increasing the header row height. Commit `489f2967bb4bd7b7a1bdff71ffacc1bb86d65eb8` reduces the logo to 14 mm, sets the logo cell to 12%, keeps the contract number/date panel at 24%, adjusts the organization cell to 64%, and reduces header-cell padding from 2 mm to 1 mm. This retains the existing voucher-style navy frame, organization identity, and boxed number/date panel. No schema, contract lifecycle, legal wording, or page-break logic changed.

**Verification status: SOURCE COMMITTED; local PHP lint and rendered PDF/page-count verification remain pending.** Pull `main`, lint `modules/hr/contract_builder.php`, then preview only an eligible existing contract period (do not create an overlapping draft). Confirm the logo fits inside its cell, the header is compact, the contract is readable, and the PDF totals exactly two pages. The source still has automatic page breaks for the contract body followed by an explicit signature-page break, so a two-page result is not yet proven.

## 2026-10-21 — Corrected voucher-header mismatch

The user's rendered screenshot demonstrated that the earlier sizing-only change did not match the voucher composition: it retained a full-width bordered grid, placed the number/date panel on the left because TCPDF RTL reverses cell display order, and left the logo undersized. Follow-up source comparison against `modules/accounting/voucher_print.php` led to commit `81f5401ee589814839a519b565bed942f6721733`: remove the enclosing grid border, put the number/date panel first in source so it renders at left under RTL, keep organization identity centered, restore a 20 mm logo at right, and retain the divider/title styling. This is a source correction, not a claim of visual success; local PHP lint and PDF page count still require checking. The explicit signature-page break and automatic body page breaks are unchanged.

## 2026-10-21 — Voucher composition correction applied

Commits `de20eb5745565f6ba9997ebf3e02c5ada6edd53f` and `e45a9d19e5b8d0bdcf198b345ac553ca00f677b6` update the contract PDF header markup to match the voucher's element order (logo, centered organization identity, framed number/date panel) and remove the extra rectangular outline around the navy bilingual title bar. Header uses 12%/64%/24% columns and 20 mm logo. Changes are source committed; local PHP lint and rendered layout/page-count checks are still pending.

## 2026-10-21 — Contract PDF rebuilt on the voucher composition; exactly two pages

Root causes (found by rendering the real PDF, not by guesswork):
- The letterhead was an HTML `<table>` inside `writeHTML`. TCPDF cannot express the voucher's rounded accent frame, circular logo, bordered number/date box or solid title bar, so every earlier width/padding tweak could only approximate it. The logo did not render at all in that table.
- The header showed an **empty contract number**: `$v` is a `static fn` created before the reserved number was assigned to `$data`, so it captured the empty value.
- The body was set at 7.2 pt / 1.05 line-height, spilled onto page 2, and an unconditional `AddPage()` pushed the signatures to a third page.
- TCPDF ignores the table-level `bordercolor` attribute, so the "voucher" borders printed black.

Change (`modules/hr/contract_builder.php` only; no clause text, schema, workflow or permission change):
- Page-one letterhead is drawn natively with TCPDF primitives in the voucher's proportions: 2 px navy rounded frame (every page), 20 mm circular logo with grey ring, centred Arabic/English organisation block, 38 mm bordered number/date box showing the reserved contract number and issue date, 2 px header rule, solid navy title bar `عقد عمل | EMPLOYMENT CONTRACT`. The letterhead is not repeated on page two.
- Key data uses the voucher's table language (`#eef2f9` label cells, `#b9c3d3` borders applied per cell) and a framed salary box like the voucher amount box.
- Bilingual labels are drawn as separately positioned Arabic and English runs so bidi cannot move separators or punctuation.
- Page fit: the PDF is rendered at the largest body size from 9.5 pt down to 7.0 pt that yields exactly two A4 pages; the signature section is always wholly on page two. If content is so long that even 7.0 pt needs a third page, the 7.0 pt result is still produced (three pages) — shorten the duties/special terms in that case.
- Contract-number reservation, overlap guard, one-use token, transaction/rollback and draft-only behaviour are unchanged (PDF is still rendered before commit).

Verification (sandbox: PHP 8.3 + MariaDB built from the repo schema/migrations, real login and real POST to `contract_builder.php`, output inspected with `pdfinfo`/`pdftoppm`): baseline reproduced at 3 pages; after the change typical and long (doubled) responsibilities both give exactly 2 A4 pages with the signature block on page two; header number now populated; draft row created with status `draft`. `php -l` passes. **Not verified:** Windows/XAMPP PHP 8.2 rendering, the configured organisation logo/address/phone settings (sandbox settings were empty, so the contact line is not exercised), print/Acrobat rendering, and Sudanese legal review remains required before official use.
## 2026-10-21 — Contract-builder invalid-form feedback toast

The contract builder's invalid/expired one-use submission-token message (`تم استخدام هذا النموذج أو انتهت صلاحيته. أعد تحميل الصفحة ثم حاول مرة أخرى.`) and other caught generation errors are now rendered in a fixed-position RTL error toast instead of an in-flow red alert. The toast overlays the page, includes an accessible alert region and close button, and dismisses automatically after seven seconds. The change is limited to `modules/hr/contract_builder.php` presentation; CSRF checks, one-use token validation, transaction rollback, contract-number reservation, overlap protection, PDF generation and form target are unchanged. No schema/migration or shared/global JavaScript changes.

**Verification:** source committed only; local PHP lint and browser check remain required. Verify the expired-token message is fixed-position and does not add layout height; close/autohide work; valid PDF generation still opens in a new tab; token and overlap safeguards remain active. A normal browser document navigation itself can start at the top; this UI change prevents the message from pushing page content but does not intercept or suppress navigation.

## 2026-10-21 — Keep contract-builder validation on the current page

The prior fixed-position toast alone did not solve the reported jump: `contractBuilderForm` intentionally targeted `_blank` so a valid PDF could open separately, which meant server-side validation HTML (for example, `تاريخ نهاية العقد مطلوب للعقد محدد المدة.`) was rendered as a full page in that new tab. Commit `bd088d1f4d090506df48eecf666c270add50f91b` adds a contract-builder-local submit handler. It validates the fixed-term end-date requirement before sending, submits the existing form data (including CSRF and one-use token) with `fetch`, opens the returned PDF in a new tab only when the response is actually a PDF, and otherwise closes the temporary tab and shows the server error in a fixed toast on the existing form page. No shared `assets/js/app.js` changes, database changes, or business-rule/transaction changes.

**Verification status: source committed; local PHP lint and browser test remain pending.** Test a fixed-term contract with no end date (toast, no navigation), a stale/reused token (toast stays on form page), a server-side validation error (same), and a valid PDF (opens in a new tab). Confirm token/CSRF and overlap protection still reject invalid submissions. The handler does not automatically retry after a network error.


## 2026-10-21 — Place validation feedback beside the PDF button

The prior fixed-position toast still appeared at the top of the viewport because `.cb-error-toast` used `position: fixed` and `showToast()` appended it to `document.body`. Commit `e41cadc856223cd861dcfced9983efbb8009231a` changes only `modules/hr/contract_builder.php`: the error message now sits in the form action area immediately before the PDF button, the server-rendered error is placed there too, and toast styling is in-flow rather than viewport-fixed. The local fetch submission behavior, successful PDF opening in a new tab, CSRF and one-use-token checks, rollback, overlap protection and PDF generation logic are retained. No shared JS, schema or migration changes.

**Verification status: source committed; local PHP lint and browser verification still required.** Pull `main`, run `php -l modules/hr/contract_builder.php`, then test a stale/reused token and a normal server validation error. Confirm the message appears beside the PDF action area at the bottom of the form without scrolling/navigation; then verify valid PDF generation still opens in a new tab. Do not mark runtime verified until tested on the local XAMPP app.

## 2026-10-21 — Roll back post-redesign feedback experiments

The user reported that contract viewing/creation had become unusable and explicitly requested restoration to the point where the voucher-style PDF design was completed. Source inspection found commit `e41cadc856223cd861dcfced9983efbb8009231a` had malformed `.cb-actions` markup: the PDF submit button was left unclosed and the return link was nested inside it. This was a concrete regression in the latest feedback-positioning edit. Commit `d074486b7cff1a204ab3bf353c829a59ecef32f7` restores `modules/hr/contract_builder.php` exactly to the source at `e7f96e838c7c33ae2636a8b363cc2a421a77781b`, preserving the voucher-style PDF implementation and removing all three later toast/fetch experiments (`f84a39a`, `bd088d1`, `e41cadc`). The form again has a complete PDF submit button and a separate return link. This is a source rollback only; local XAMPP PHP lint, browser contract creation, existing-contract viewing, and generated PDF rendering have not yet been verified. Do not claim runtime success until the user tests the restored local app.

Correction to rollback record: the complete 799-line checkpoint source was re-applied and checked against the same 799-line source fetched from `e7f96e838c7c33ae2636a8b363cc2a421a77781b`. Final source commit: `7df2bc435d2a6464692895db62cb97ec03a2f109`. The complete file ends with the shared footer include; the PDF submit button and return link are separate. This comparison establishes source equality with the checkpoint, not local PHP/browser runtime success.

## 2026-10-21 — Employment-contract PDF download filename hardening

The user reported that using Download in the browser PDF preview saved the contract with a `.php` extension. Source inspection confirmed the PDF response already used `Content-Type: application/pdf` and an inline `filename="Ahl-El-Kheir-Employment-Contract.pdf"`, so the root cause is not proven without inspecting the actual local HTTP response headers. Commit `1cf3fb0b753b30b49ba5f950498ff534fb802de2` makes the response more robust by clearing any active output buffers immediately before PDF headers/body, and supplying both the ASCII `filename` and RFC 5987 `filename*` parameters while preserving `inline` preview behavior. This is source-only. After pulling `main`, run PHP lint and generate a PDF in the local XAMPP app; use the PDF viewer's Download control and confirm the saved file ends in `.pdf`. If it still saves as `.php`, inspect the actual response headers in browser DevTools before changing more code. Do not claim runtime success before this check.

### 2026-10-21 — Contract-builder error feedback toast

Source commit: `197f53d75cf4546e8efd6a483e9ea850a18916b5`.

Root cause: the PDF form submits to a new tab (`target="_blank"`), while caught server-side errors were rendered as an inline alert at the top of the full HTML page. The error therefore appeared at the top of a separate tab instead of near the initiating button.

The error path now returns a small same-origin response that posts the error to the original page and closes the preview tab. The original page renders a dismissible, timed toast near the saved submit-button position. Successful PDF responses retain the existing inline PDF response and filename headers; the form page is not navigated for errors. No database, contract lifecycle or PDF layout changes.

**Verification boundary:** source committed to `main`; local PHP lint and browser verification remain required. Test one intentional server-side validation failure and one successful PDF preview. Confirm the error appears next to the clicked button without scrolling/reloading the form page, the error tab closes, and successful PDF preview/download behavior remains unchanged. Do not mark runtime verified until these checks pass.

### 2026-10-21 — Corrected contract-builder error response

Follow-up source commit: `890b9ef95390af64820083a8567c6e5e7fc2ccb0`.

The prior `window.opener.postMessage()` approach failed in the user's browser: the server error rendered in the newly opened tab, so the message was not delivered to the original page. Replaced that approach with a same-page `fetch()` submission. The original page now handles the response: JSON errors close the temporary preview tab and show the toast near the clicked button; successful PDF responses are converted to a blob and loaded into the preview tab. The original form page is not navigated for either outcome.

**Verification:** source committed to `main`; local PHP lint and real-browser checks remain pending. Test the specific fixed-term contract case with missing end date, then a valid contract preview. Confirm the error toast appears beside the clicked button on the original page and successful preview still works.

## 2026-10-21 — Restore pre-toast Contract Builder source

After the latest fetch-based toast experiment produced a local Apache “Not Found” response, `modules/hr/contract_builder.php` was restored exactly to checkpoint `e7f96e838c7c33ae2636a8b363cc2a421a77781b` in commit `8af298793a4c86d38d303516b2b03992b7258405`. This removes the toast/fetch submission changes and returns the form/PDF response path to the pre-toast implementation. The restored file blob matches checkpoint blob `01442b9fa87a0ab6c9d47d63175681cd1439854d`. Local XAMPP lint and browser/PDF behavior have not been verified by the assistant; pull `main` and test the existing workflow before considering it runtime-verified.

## 2026-10-21 — Authoritative post-rollback checkpoint

The latest source rollback is commit `8af298793a4c86d38d303516b2b03992b7258405`, restoring `modules/hr/contract_builder.php` to the pre-toast voucher-style PDF checkpoint `e7f96e838c7c33ae2636a8b363cc2a421a77781b`. The restored source blob matches the checkpoint. Treat this as the current source baseline; do not continue toast/fetch changes unless explicitly requested by the user.

Runtime acceptance is still unverified after rollback: local XAMPP PHP lint, existing-contract viewing, normal contract creation, and actual PDF output must be checked before claiming the restored workflow works. Use `http://localhost:8081/AhlElKheir/` and preserve the existing draft/active overlap guard. Do not create a duplicate/overlapping contract solely for preview.

## 2026-10-21 — Contract Builder toast handling with native PDF response

The user explicitly resumed the Contract Builder toast task and requested that its feedback match proven notification patterns elsewhere in the application. The existing `modules/hr/job_description_catalogue.php` pattern uses a fixed-position RTL toast with accessible live announcement, dismiss control, and five-second auto-dismiss. `modules/hr/contract_builder.php` now applies that toast presentation to caught generation errors on the original form page.

Source commit: `54d4cef36e58695faea509a61b2a0fc5232589bb`. The form targets a named preview window (`contractPdfPreview`); the server returns caught generation errors as a small same-origin response that posts the message to the opener and closes the preview window. Successful generation continues to return the original native `application/pdf` response with its existing inline filename headers, avoiding a fetch/blob preview that could alter download naming. Contract validation, one-use submission token, CSRF handling, transaction/rollback, overlap protection, and PDF generation logic were not intentionally changed. The interim fetch/blob implementation in commit `572ccf46ea16b856592885d492ee6b4b9fc0a30b` was superseded by this native-response approach.

**Verification boundary: SOURCE COMMITTED; local PHP lint and browser behavior are not yet verified.** On the configured XAMPP app (`http://localhost:8081/AhlElKheir/`), first test one controlled server-side validation failure and confirm the toast appears on the original form page without navigating it or leaving an error page open. Verify that the named preview window closes after delivering the message. Check PHP syntax. Test a successful PDF response only when creating the corresponding contract draft is intentional; do not create duplicate/overlapping drafts merely to test preview. Confirm the browser still receives the native PDF response and filename headers. Do not mark this fixed until actual local evidence is available.


## 2026-10-21 — Fixed-term missing end-date error stays on the form

After the user confirmed that the exact validation message `تاريخ نهاية العقد مطلوب للعقد محدد المدة.` still rendered in the preview tab, source inspection found that the submit handler opened `contractPdfPreview` before PHP performed this deterministic validation. The popup response's `window.opener.postMessage()` path had not delivered the message in the user's browser. Commit `c1b80acd47904f91e6d11e615e19de740138ddb5` adds a narrowly scoped pre-submit check for `contract_type=fixed_term` with an empty `end_date`; it prevents submission/opening the preview tab and uses the existing toast on the original page. The authoritative PHP validation remains unchanged.

**Verification boundary:** source committed to `main`; local PHP lint and browser behavior are not yet verified. Test this exact missing-end-date case first: the original page must remain in place, show the toast, and open no new tab. This change only covers the confirmed validation case; other server-side generation errors still need separate runtime diagnosis and must not be described as fixed by this narrow change. Successful PDF generation and existing contract safeguards were not intentionally changed.


## 2026-10-21 — Contract Builder error routing and stale-token recovery (supersedes earlier toast attempts)

User runtime feedback showed that the fixed `contractPdfPreview` browsing-context name could navigate the current tab when that tab already had the same browsing-context name; the resulting server error page was not consistently reaching the form toast. Source inspection also confirmed that successful PDF generation intentionally unsets `ak_contract_builder_submission_token`, leaving the still-open form with a consumed token until it is refreshed.

Commit `c89e22386f921c339b8965de1cf49fd07a11f25b` changes the form to open a fresh, unique preview-window name for each submission while retaining the native form POST and native PDF response (including the inline filename header). Server-side errors send the error and the current/reissued form token to the opener via same-origin `postMessage`; the original page displays the existing toast and refreshes its hidden token. The deterministic missing-end-date check remains before opening any preview window. The AJAX/blob implementation from intermediate commit `dc6a80b0dfc9c086c5cc0346d028b6b7bc872b4b` was superseded to preserve native PDF response/download behavior.

**Verification boundary:** static source inspection confirms the unique preview target, error message/token payload, client token refresh, unchanged native PDF `Content-Disposition`, and preserved one-time-token consumption after success. Local PHP lint and browser runtime are not available from this remote source session; do not call this resolved until the exact local acceptance test passes. Test (1) fixed-term contract with empty end date: toast on original page, no preview tab; (2) server-side token/validation error: toast on original page, error tab closes, no white/error page; (3) successful PDF: opens in separate tab with native PDF response/filename. Do not create duplicate contract drafts solely for testing.

## 2026-10-21 — Contract Builder toast task closed at user-requested boundary

The user confirmed that the error toast now appears after completely closing the page and logging out/in again. On a further check, the user clarified that the page does **not** jump/scroll upward; instead, there is a very brief refresh-like visual effect before the toast appears.

The user explicitly instructed us to stop here and make no further changes. **Task status: CLOSED BY USER REQUEST; residual visual behavior is documented, not diagnosed or claimed fixed.** No further source edits, experiments, or speculative scroll/focus/navigation workarounds are authorized for this task unless the user explicitly reopens it. Do not reopen automatically in a new session.

No cause has been established for the brief refresh-like effect. The current observed behavior is the acceptance boundary the user chose; it is not evidence that the underlying visual effect was technically eliminated. No local runtime or PHP-lint result is newly claimed by this documentation update.

For the next session, read the canonical project documentation and inspect current `main` to determine the next genuinely open task or follow the user's new requirement. Do not resume Contract Builder toast/scroll work unless explicitly requested.

## 2026-10-21 — HR contracts employee details moved into a modal

User request: on `modules/hr/contracts.php`, selecting an employee should open the employee's contract/salary area as a popup, and the employee list should expand to use the width previously occupied by the two-column list/details layout.

Source commit: `29f650e189f748777427ac90eafc630feac58a18` (on `main`).

Implemented in `modules/hr/contracts.php`:
- Replaced the narrow left-hand employee sidebar plus always-visible details panel with a full-width responsive employee-card grid.
- Selecting an employee loads that employee's existing `employee_id` URL and automatically opens a wide, scrollable Bootstrap modal.
- Moved the employee-specific tabs and existing sections into the modal: `العقود`, `سجل الرواتب`, `إعداد عقد جديد`, and `تغيير الراتب`.
- Kept the existing contract status actions, signed-copy upload, salary history, salary-change form, employee/contract queries, permissions, and business rules in place; no schema or migration change.
- Search continues to filter employee cards and now shows an empty-results message. The modal is reopened after the existing form POST reload so the user can see the result/error inside the employee context.

**Verification boundary:** source change committed to `main`; no local XAMPP/PHP lint or real-browser runtime verification is claimed from this session. After pulling, check PHP syntax and test employee selection, all four modal sections, search/no-results, contract actions/upload, salary history/change, and that success/error feedback remains visible inside the modal after POST. Do not claim runtime verified until these checks pass.


## 2026-10-21 — HR contracts modal parse error corrected

After the user pulled the employee-details modal change, local PHP lint reported an unexpected end of file on line 134, expecting `elseif`, `else`, or `endif`. Source inspection identified the exact cause: the employee-selection `if ($emp)` alternative-syntax block had no closing `endif`; the existing terminator closed the nested view switch instead. Commit `decb8a577df182396def4eea906220bd4b4b009a` adds the missing outer `endif` in `modules/hr/contracts.php` without changing application logic.

**Verification boundary:** source correction is committed to `main`; the user's local PHP lint has not yet been rerun, and browser/runtime behavior is not verified. After pulling, run `php -l modules/hr/contracts.php` and report the exact result before browser testing. Preserve the existing untracked `storage/hr_contracts/` directory; it was not modified or included in the commit.

## 2026-10-21 — HR employee details modal initialization timing fix

The user reported that selecting an employee only refreshed the page and did not display the employee-details modal. Inspection of `modules/hr/contracts.php` and `includes/footer.php` identified the ordering defect: the page's inline script attempted to open the Bootstrap modal while the Bootstrap JavaScript bundle is loaded later in the shared footer. At that point `window.bootstrap` was not yet available, so the guarded modal-opening code silently did nothing.

Commit `4fe5b9baaa09830c3bfc06b37c18c3f080660f45` wraps the page's existing employee search and modal initialization in `DOMContentLoaded`, which runs after the footer's Bootstrap bundle has loaded. The employee selector is now rendered as full-width list rows rather than cards, preserving the full-width picker while keeping employee-specific details in the modal. No query, authorization, contract, salary, or schema logic was changed.

**Verification boundary:** source fix committed to `main`; local PHP lint and browser runtime are not yet verified. Pull the commit, run `php -l modules\\hr\\contracts.php`, then click one employee. Acceptance: URL refresh may occur, but the employee's contract/salary modal must open automatically. Test a second employee and the four modal sections. Preserve the untracked `storage/hr_contracts/` directory.


Follow-up UI correction: the user clarified that the employee selector should appear as a list, not clickable cards. Commit `7464fc22c31f613fcf26f3c33f32ca3d92be491c` changes only the selector presentation to full-width list rows, with a compact responsive layout on narrow screens. The modal timing fix remains in place. Local lint and browser verification are still pending.

## 2026-10-21 — Remove redundant modal footer

The employee-details modal had its own `modal-footer` containing a second “إغلاق” button, while the shared application page already provides its navigation/footer controls. Removed only that modal-footer block in commit `7c724c77e65a055e9eb66da7605dfe69ec9ad300`. The modal header close control remains, and the shared application footer is unchanged. Local PHP lint and browser verification are pending.

## 2026-10-21 — Employee modal footer suppression and in-modal tab navigation

Source commit `40f1198a3b0c54b022b2154b55a12dfce345b730` updates `modules/hr/contracts.php`:
- suppresses footer elements if they appear inside the employee modal;
- intercepts the modal's internal Contracts / Salary History / Salary Change links, fetches the selected view, and replaces only the modal content instead of navigating/reloading the whole page;
- leaves the separate Contract Builder link as normal navigation and leaves POST-based save/status/upload actions unchanged.

**Verification boundary: SOURCE COMMITTED; local PHP lint and browser verification are pending.** Confirm the popup contains no footer and each internal tab changes content without a full-page refresh. If tab loading fails, the modal displays an inline error instead of silently navigating.

Follow-up commit `889a10e6f7368190f8e78a9d356a7fabd638b1c6` also hides the shared application footer while the employee modal is open (and restores it when the modal closes), so the page footer cannot visually appear as part of the popup. Local verification remains pending.

## 2026-10-21 — Secure viewing of uploaded employment contract copies

Root cause confirmed from repository source: `storage/.htaccess` contains `Require all denied`, which correctly blocks direct HTTP access to `storage/hr_contracts/`. The contract register previously linked directly to `APP_URL . contract_file_path`, so Apache returned `403 Forbidden`.

Source changes committed on `main`:
- `773229e8753979ecfa66ec588b8626b96d1325a8` — change the contract-file link to `modules/hr/view_contract.php?contract_id=...`.
- `a6576eb379988d11f8a112f9c6c073a14ee84c2a` — add the protected viewer. It applies the same HR manager / HR staff / admin role gate as the contract register, resolves the file through the existing contract record, confines real paths to `storage/hr_contracts/`, validates allowed extension and detected MIME type, and streams supported files inline. The storage-wide deny rule is intentionally unchanged; no schema change or migration is needed.

**Verification status: SOURCE COMMITTED; local PHP lint and browser verification remain pending.** After pulling `main`, lint both `modules/hr/contracts.php` and `modules/hr/view_contract.php`, then open an uploaded PDF from the HR contract register while logged in with an allowed HR role. Also verify the raw `storage/hr_contracts/...` URL remains forbidden and a logged-out/non-HR request to the viewer is denied.


## 2026-10-21 — Contract preview, print, and download controls

The HR contract file route now defaults to an authorized HTML preview page rather than returning the file response as the top-level page. The preview embeds the protected file endpoint, provides an explicit **طباعة** action for PDFs and an explicit **تنزيل الملف** action, and retains **فتح الملف في تبويب جديد**. The file endpoint uses `Content-Disposition: inline` by default and switches to `attachment` only when the explicit download action is requested. Storage remains protected by `storage/.htaccess`; no database/schema changes were made.

Commit: `ac91f1b65f87300114f53ceeae98b28734f90d64`.

Verification boundary: source is committed to `main`; local PHP 8.2 lint and browser behavior are not yet verified. In particular, browser PDF handling can still depend on client settings, so confirm the embedded PDF preview and print/download controls in the local Chrome/XAMPP environment before calling this runtime-verified.


## 2026-10-21 — Reverted contract preview wrapper

At the user's request, removed the additional HTML preview wrapper and restored the prior direct authorized file response in `modules/hr/view_contract.php`. The HR contract register continues linking to that protected route; `storage/.htaccess` remains restrictive. The route sends the file with `Content-Disposition: inline`, allowing the browser's PDF viewer/settings to determine inline display versus download. No database/schema changes.

Commit: `e3b458eb57e618acaf8c8bb0a06df79cd02ece7e`. Local PHP 8.2 lint and browser behavior remain unverified.

## 2026-10-22 — HR job-description migration safety review

Before asking the user to apply the pending job-description migrations, source review found a concrete data-preservation risk in `database/migrations/2026-10-20_hr_job_description_zzzz_qualification_baseline.sql`: its qualification-baseline update could change an already `approved` active description and downgrade its status to `needs_review`, contrary to the documented preservation boundary. Commit `0e7ec314d4335d2ff3428640b76b6c18629fc86b` adds `AND description_status <> 'approved'` to that migration's update predicate. This is a migration-file correction only; no local database was accessed or changed. Existing approved descriptions are now excluded from this baseline update. Do not claim the migrations are applied or the portal runtime-verified. The remaining work is to inspect the entire migration sequence against the current schema and actual local database state, then perform the local application and focused HR portal checks without touching unrelated pages or existing contract files.
