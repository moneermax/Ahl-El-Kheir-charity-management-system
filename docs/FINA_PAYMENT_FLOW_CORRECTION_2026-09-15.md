# Fina Al-Khair Payment Flow Correction — 2026-09-15

## Decision

Fina-only external payments are recorded through the dedicated Fina intake workflow. This workflow must not require a sponsor or sponsorship.

Sponsor-originated payments remain part of the normal sponsor payment business flow, where a payment may be allocated between Ahl El Kheir and Fina Al-Khair.

## Required flows

1. Normal transaction creation
   - Sponsor/sponsorship information remains available for sponsor-originated payments.
   - Structured Ahl/Fina allocation is now supported in the normal payment-entry experience without replacing the existing `modules/transactions/create.php` workflow.
   - The normal payment form exposes a Fina-share amount for each payment line (or the single amount for non-monthly entries).
   - Server-side allocation capture is performed during the existing `SUBMIT_FM` review/audit step, using the transaction ID already created by the normal workflow.
   - Fina share is recorded as liability to account 2300.
   - Ahl administrative fee applies only to the Ahl share.
   - Sponsor obligations are linked only to applicable sponsorship payments and count the Ahl share rather than the protected Fina share.
   - A sponsor and sponsorship are required when a Fina share is entered from the normal sponsor payment screen; external Fina money uses the dedicated Fina intake screen.

2. Dedicated Fina payment intake
   - External source only: person, organization, or other.
   - No sponsor field.
   - No sponsorship field.
   - 100% of the received amount is Fina share.
   - No sponsor obligation is created.
   - FM approval posts the receipt with a credit to Fina payable account 2300.

3. Fina settlement
   - Remains a separate settlement/transfer workflow.
   - Settlement is not an Ahl expense.

## Current implementation checkpoint

- `modules/transactions/fina_payment_create.php` is the dedicated external Fina-only intake screen.
- `modules/accounting/fina_payment_review.php` handles FM approval/return for Fina intake records and distinguishes external sources from sponsors.
- `modules/accounting/fm_transaction_review.php` uses the Fina-aware journal path for normal transactions.
- `modules/accounting/lib_fina.php` validates the allocation, calculates any Ahl-only administrative fee, posts Fina share to liability account 2300, and updates sponsor obligation paid amounts using the Ahl share.
- `modules/accounting/lib_transaction_review.php` captures the structured Fina allocation during the existing normal `SUBMIT_FM` path, after the transaction ID is known and before the surrounding transaction creation transaction commits.
- `assets/js/fina_payment_allocation.js` adds the Fina-share controls to the existing normal payment form without reconstructing or replacing `modules/transactions/create.php`.
- `includes/footer.php` loads that control script only for `modules/transactions/create.php`.
- The temporary `modules/transactions/fina_shared_payment_create.php` screen has been removed because sponsor-originated shared payments now use the normal payment-entry experience.

## Audit test correction

The dedicated Fina intake test is an external Fina-only receipt. The shared Ahl/Fina test belongs to the normal sponsor payment business flow and must verify that the Fina share is a liability, Ahl revenue/fees are calculated only from the Ahl share, and the sponsor obligation is updated only for the Ahl sponsorship payment.
