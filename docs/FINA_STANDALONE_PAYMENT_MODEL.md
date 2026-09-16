# Fina Al-Khair — Standalone Payment Model

## Business rule

100% of money entered through the dedicated Fina payment workflow belongs to **فينا الخير**. Ahl El Kheir is collecting/holding the money on Fina's behalf.

Fina money is therefore not Ahl El Kheir revenue, sponsorship revenue, or administrative-fee revenue. It must not be used to absorb a shortfall in a sponsor's normal sponsorship obligation.

## Entry point

All users authorized by the application to directly enter sponsor-facing payments may use:

`modules/transactions/fina_payment_create.php`

The current authorization set is:

- admin
- accountant
- accountant_staff
- financial_manager
- supervisor
- vice_general_manager

The Supervisor is a primary operational user of this workflow.

## Review

Fina submissions are reviewed through:

`modules/accounting/fina_payment_review.php`

The review/approval stage remains controlled by the financial-management workflow.

## Accounting treatment

For a Fina-only collection of X:

- Debit the asset account corresponding to the actual payment method.
- Credit Fina's dedicated liability/control account **2300** for X.
- Ahl El Kheir revenue: 0.
- Ahl El Kheir administrative fee: 0.
- Sponsorship obligation: 0.

Any future remittance/settlement to Fina is a separate controlled event that reduces the Fina liability; it is not an Ahl El Kheir expense.

## Separation from normal sponsor payments

Normal sponsor/sponsorship payments continue through the existing payment workflow. A sponsor obligation shortfall remains a sponsor-accounting issue and must never be reclassified as Fina money merely to balance the transaction.

## UI direction

The Fina payment entry page should be surfaced from dashboards used by the authorized sponsor-facing roles, with the Supervisor dashboard treated as the primary operational entry point.

The existing Fina review page remains the review/history surface rather than introducing a second entry mechanism.
