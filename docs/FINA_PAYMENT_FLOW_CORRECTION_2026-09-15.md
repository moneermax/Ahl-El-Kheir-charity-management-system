# Fina Al-Khair Payment Flow Correction — 2026-09-15

## Decision

Fina-only external payments are recorded through the dedicated Fina intake workflow. This workflow must not require a sponsor or sponsorship.

Sponsor-originated payments remain part of the normal sponsor payment business flow, where a payment may be allocated between Ahl El Kheir and Fina Al-Khair.

## Required flows

1. Normal transaction creation
   - Sponsor/sponsorship information remains available for sponsor-originated payments.
   - Structured Ahl/Fina allocation must be supported in the normal payment-entry experience.
   - Fina share is recorded as liability to account 2300.
   - Ahl administrative fee applies only to the Ahl share.
   - Sponsor obligations are linked only to applicable sponsorship payments.

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

- `modules/transactions/fina_payment_create.php` is now the dedicated external Fina-only intake screen.
- `modules/accounting/fina_payment_review.php` handles FM approval/return for Fina intake records and distinguishes external sources from sponsors.
- `modules/transactions/fina_shared_payment_create.php` has been added as a controlled sponsor-originated shared Ahl/Fina intake path while the existing large `modules/transactions/create.php` is preserved intact.
- The shared path records the sponsor payment plus structured Fina allocation and sends it through the Fina FM review path, where the Fina-aware journal and sponsor-obligation logic are applied.
- The existing `modules/transactions/create.php` itself has **not** been replaced or rewritten. Its direct in-form Fina allocation integration remains the next implementation step; no destructive reconstruction of that large existing file is permitted.

## Audit test correction

The dedicated Fina intake test is an external Fina-only receipt. The shared Ahl/Fina test belongs to the normal sponsor payment business flow and must verify that the Fina share is a liability, Ahl revenue/fees are calculated only from the Ahl share, and the sponsor obligation is updated only for the Ahl sponsorship payment.
