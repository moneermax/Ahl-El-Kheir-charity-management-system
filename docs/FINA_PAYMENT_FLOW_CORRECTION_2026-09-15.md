# Fina Al-Khair Payment Flow Correction — 2026-09-15

## Decision

Fina-only external payments are recorded through the dedicated Fina intake workflow. This workflow must not require a sponsor or sponsorship.

Sponsor-originated payments remain in the normal transaction creation workflow, where a payment may be allocated between Ahl El Kheir and Fina Al-Khair.

## Required flows

1. Normal transaction creation
   - Sponsor/sponsorship information remains available for sponsor-originated payments.
   - Structured Ahl/Fina allocation is supported.
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

## Audit test correction

The dedicated Fina intake test is an external Fina-only receipt. The shared Ahl/Fina test belongs to the normal transaction creation workflow.
