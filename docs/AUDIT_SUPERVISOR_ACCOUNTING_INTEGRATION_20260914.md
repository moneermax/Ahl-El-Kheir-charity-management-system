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

### `modules/transactions/index.php`

- Reuses the authoritative Sponsor-scope helper.
- Supervisor transaction results are limited to Sponsors the Supervisor can legitimately access.
- Accounting journal visibility remains unchanged and still requires the existing authorized Accounting/management roles.

### `dashboard/supervisor_dashboard.php`

- Monthly receipts are now calculated by joining `sponsor_payments` to `sponsors` and applying the same Sponsor scope used by the dashboard.
- No Accounting authority was added to the Supervisor dashboard.

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

No completed Accounting Audit tests or historical fixtures need to be rerun unless one of these targeted checks exposes a regression.
