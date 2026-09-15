# Supervisor ↔ Accounting Integration Review — 2026-09-14

## Scope

This review continues the existing Supervisor and Accounting audits. It does not reopen completed HR, Accounting, Notification, Accountant Staff, FM dashboard, or Supervisor ownership work.

## Authoritative Supervisor rule

Supervisor Sponsor responsibility is determined by:

**Sponsor first-name letter + Sponsor gender → Supervisor**

The existing direct Sponsor assignment path (`sponsors.supervisor_id`) is retained because it is an administrative/manual assignment mechanism already used by the repository. It is not treated as a replacement for the responsibility matrix.

## Integration findings

### Correct separation of duties

Supervisor may submit operational payment information for financial review, but Supervisor is not granted journal, ledger, FM approval/posting, or financial-control authority.

The FM workflow remains responsible for financial approval/posting and accounting journal creation.

### Defect identified

The Supervisor transaction entry/list paths used a weaker Sponsor scope based on direct assignment and first letter without applying Sponsor gender. This could allow an out-of-scope Sponsor to enter or appear in Supervisor financial workflow through direct Sponsor IDs or transaction lists.

The Supervisor dashboard monthly-receipts KPI also used `sponsor_payments.supervisor_id` directly instead of deriving scope from the Sponsor responsibility rule.

## Narrow fixes applied

### `modules/transactions/create.php`

- Reuses `config/sponsor_assignments.php`.
- Builds the Supervisor Sponsor list through `supervisorCanAccessSponsor()`.
- Validates a direct `sponsor_id` against the same scope before displaying or processing it.
- Revalidates Sponsor scope on POST before creating a Supervisor payment.
- Protects returned Sponsor-payment editing with the same Sponsor scope.
- The manual administrative-fee percentage input was removed. The form now explicitly tells the operator that administrative fees are applied automatically from the FM-controlled policy when the transaction is posted.
- Submission and returned-transaction editing no longer calculate or trust a manually entered fee percentage; the accounting posting layer remains the authoritative fee calculation point.

### `modules/transactions/index.php`

- Reuses the authoritative Sponsor-scope helper.
- Supervisor transaction results are limited to Sponsors the Supervisor can legitimately access.
- Accounting journal visibility remains unchanged and still requires the existing authorized Accounting/management roles.
- Fixed a syntax regression in the journal-link expression that caused a PHP parse error for Supervisors on this page.

### `dashboard/supervisor_dashboard.php`

- Monthly receipts are now calculated by joining `sponsor_payments` to `sponsors` and applying the same Sponsor scope used by the dashboard.
- No Accounting authority was added to the Supervisor dashboard.

### `modules/families/orphan_profile.php`

- Added a photo upload field to the orphan profile/edit page.
- Supports JPG/PNG uploads up to 10 MB.
- Existing photos can be replaced or removed from the profile page.
- The saved `family_children.photo_path` is the same field already consumed by the orphan form/photo display path.

## Administrative-fee policy control

The Accounting policy implementation remains anchored to Opening Balance:

- FM/admin configures `none`, `fixed`, or `percentage` before the opening balance becomes live.
- Once active, the policy is locked for historical consistency.
- At transaction posting, Accounting applies the active policy snapshot automatically.
- Sponsorship accounting remains gross treasury debit, net sponsorship revenue to 4100, and administrative-fee revenue to 4200 when applicable.
- There is no longer a manual fee-percentage input on the transaction entry form.

## Receipt-file regression fix — 2026-09-15

- `modules/transactions/receipt_file.php` previously returned a bare `404 Not found` response when a transaction had no receipt attachment or when the referenced receipt file was no longer available.
- The missing-receipt path now uses the normal application header and a clear Arabic system message instead of a white/plain error page.
- The existing authentication, role checks, Supervisor Sponsor-scope authorization, and secure real-path validation are preserved.
- Existing valid receipt files continue to be streamed inline with their detected MIME type.

## Separation-of-duties result

Supervisor remains an operational oversight role. Financial approval/posting remains with FM/Accounting.

## Verification required

Targeted testing should confirm:

1. A Sponsor inside the Supervisor's letter + gender scope remains accessible.
2. A Sponsor outside that scope is blocked from `create.php?sponsor_id=...`.
3. The blocked Sponsor does not appear in the Supervisor transaction list.
4. A Supervisor cannot submit a payment for an out-of-scope Sponsor by manipulating the POSTed Sponsor ID.
5. The Supervisor monthly-receipts KPI reflects only legitimately scoped Sponsor payments.
6. FM can still receive and process a legitimate Supervisor submission.
7. Supervisor still has no Accounting journal/ledger approval authority.
8. Supervisor transaction list opens without a PHP parse error.
9. Transaction entry no longer offers a manual administrative-fee percentage field.
10. Orphan profile edit can upload/replace/remove a photo and the orphan form displays the saved photo.
11. A transaction with no receipt attachment opens a normal system message instead of a white/plain `Not found` page.
12. An existing receipt attachment still opens normally.

No completed Accounting Audit tests or historical fixtures need to be rerun unless one of these targeted checks exposes a regression.