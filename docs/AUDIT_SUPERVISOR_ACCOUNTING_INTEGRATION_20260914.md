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
- Code commit: `ddd5e91e6f90935cd3a7e328f480ae1f8d906e34`.
- Documentation commit: `cd78fe373c72ce4063ff0b1a9c9a66e59a245961`.

## Notification integration checkpoint — 2026-09-15

The notification layer used by Supervisor/Accounting workflows is now centralized and confirmed to preserve the required attention behavior across applicable dashboards.

### Live delivery/visibility

- `modules/notifications/poll.php` provides authenticated polling for unread and recent notifications.
- The shared notification widget polls every 5 seconds, so newly created workflow notifications appear without manual refresh or logout/login.
- Dynamic notification actions preserve CSRF protection.
- Applicable dashboards use the same shared unread visual indicator, including a clear red dot beside unread notification titles.

Relevant commits:

- `ecc82aa6af0a8201adbb6d6de0c7dbb087e77305` — polling endpoint.
- `b82bd4effca75ffbde5a2f8e1930f326fc3b9664` — shared live notification widget.
- `2ff8f331e575fab9dce9916c783ad3d7d3e63097` — dynamic notification CSRF fix.
- `c9b151b960b962c6cddbb1b135ceef6ddd5b4e16` — unread red-dot indicator.

### Full history and menu clearing

- `modules/notifications/index.php` provides full notification history and the bell includes `عرض الكل`.
- `مسح الكل` is explicitly **menu-only**. It must not delete notification records.
- `modules/notifications/clear_all.php` now records a browser-local cutoff (`ak_notif_menu_cleared_before`) instead of deleting rows.
- `assets/js/notification_unread_indicator.js` hides cleared menu entries and recalculates the visible unread badge after live polling.
- The underlying notification records remain available in full history for audit/history purposes.

Commits:

- `01f161ac59c97e033626ba5c447e7793fe52da4c` — full notifications page.
- `0e830c3851eb2efd83f27881ff0b6c998f2d27ca` — bell `عرض الكل` link.
- `391474e6ea894812b9b3eba6e636bba238e8c66a` — non-destructive clear-all backend.
- `ddd9796896c49f4e2aaa150c3182253a6fa42d05` — client-side menu filtering.

The user tested the latest clear-all behavior and **confirmed it is correct**.

## Separation-of-duties result

Supervisor remains an operational oversight role. Financial approval/posting remains with FM/Accounting.

## Verification required / already closed

The following Supervisor ↔ Accounting controls have already been verified at the current checkpoint and should not be repeated unless new evidence shows regression:

1. A Sponsor inside the Supervisor's letter + gender scope remains accessible.
2. A Sponsor outside that scope is blocked from `create.php?sponsor_id=...`.
3. The blocked Sponsor does not appear in the Supervisor transaction list.
4. A Supervisor cannot submit a payment for an out-of-scope Sponsor by manipulating the POSTed Sponsor ID.
5. The Supervisor monthly-receipts KPI reflects only legitimately scoped Sponsor payments.
6. FM can receive and process a legitimate Supervisor submission.
7. Supervisor still has no Accounting journal/ledger approval authority.
8. Supervisor transaction list opens without a PHP parse error.
9. Transaction entry no longer offers a manual administrative-fee percentage field.
10. Orphan profile edit can upload/replace/remove a photo and the orphan form displays the saved photo.
11. A transaction with no receipt attachment opens a normal system message instead of a white/plain `Not found` page.
12. An existing receipt attachment still opens normally.
13. Existing real notification scenarios for HR leave approval/rejection and password recovery/change-request work correctly.
14. New notifications appear in the shared bell without manual refresh/login.
15. Unread notifications are visually distinguished by the shared red-dot indicator.
16. `عرض الكل` opens full notification history.
17. `مسح الكل` clears only the bell menu and does not delete notification records.

## Next audit direction

Continue from the **remaining real Supervisor → Accounting integration points**. Do not restart the Supervisor module audit or closed Accounting/Notification tests.

The next review should inspect actual repository code and answer:

1. Can Supervisor access any Accounting page/action directly or indirectly?
2. Does any Supervisor operational workflow create, submit, return, or otherwise mutate an Accounting-controlled record?
3. What financial status/result is appropriate for Supervisor to see without granting Accounting authority?
4. Are Supervisor submissions routed to FM/Accounting using the correct actor and scope rules?
5. Are Accounting notifications/results exposed only to the correct Supervisor?
6. Does any Accounting query accidentally expose data outside the Supervisor's legitimate Sponsor scope?
7. Does any Supervisor dashboard KPI/summary expose Accounting data broader than the Supervisor's operational scope?

No old fixtures or SQL verification should be recreated unless a genuinely new control requires new evidence.
