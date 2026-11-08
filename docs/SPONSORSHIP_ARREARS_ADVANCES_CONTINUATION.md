# Sponsorship Advances and Two-Month Arrears — Continuation

**Status:** Active — source/schema audit completed; implementation not yet started.
**Baseline:** imported sponsorship monitoring starts 2026-08-01; newly created sponsorships use their actual start date.
**Repository:** main only. Test database/data, not live organizational data.

## Accepted business rules

1. Calculate obligations independently for each individual sponsorship, never at sponsor-wide level.
2. Imported sponsorships start monitoring in August 2026. Newly created sponsorships use the actual sponsorship start date.
3. Count only complete calendar months in which the sponsorship was eligible throughout. Exclude a partial first/last month and any month touched by a pause. A pause/resumption in the middle of a month means that month is not an obligation month.
4. Preserve unpaid eligible months before a pause. Resume obligations only at the next complete eligible month.
5. A month is fully paid only when confirmed posted, non-voided monthly-sponsorship allocations for that exact sponsorship/month total at least the full monthly commitment. Partial payments accumulate. Pending, returned, cancelled, and voided records do not count.
6. Do not automatically move excess to another month. Do not automatically deactivate sponsorships for arrears.
7. If a submission for the same sponsorship/month is pending FM review, warn/show it and do not ask for another payment. Show pending and returned submissions separately from posted collections.
8. The collector/direct-contact employee is the person who collected or recorded the payment. Do not substitute the sponsor's current supervisor or FM approver.
9. Future-period sponsorship net receipts are liabilities in 2200 until earned; earned sponsorship net is 4100; applicable administrative fee remains 4200. Excess not allocated to an obligation remains a liability.
10. An approved `sponsor_payments` submission is represented by its linked `transactions` record once; never count both.

## Arrears classification — business rule confirmed 2026-11-08

A sponsorship is classified as having reached the two-month arrears threshold when at least two consecutive **eligible completed months** each retain a positive unpaid balance after applying confirmed posted, non-voided allocations for that exact sponsorship/month.

- Partial payment reduces that month's outstanding balance but does not clear the month.
- A fully paused month is excluded from the eligible-month sequence; it neither creates an obligation nor breaks the sequence of unpaid eligible months.
- Pre-pause arrears remain outstanding throughout the pause.
- After resumption, counting continues with the first complete eligible month after the pause.
- Partial start/end months and any month touched by a pause remain excluded.
- The classification is informational and must not automatically deactivate, pause, or otherwise change a sponsorship.

This is a two-eligible-month threshold, not a requirement for two adjacent calendar months. Example: unpaid August, paused September, unpaid October reaches the threshold.

## Audited implementation facts

- `transactions` has `sponsorship_id`, `payment_period`, `amount`, `net_amount`, `status`, `voided_at`, `created_by`, `transaction_type`, and `purpose`. There is no per-month allocation table in the checked-in schema.
- `sponsor_payments.transaction_id` links approved supervisor submissions to their created transactions.
- `ak_approve_sponsor_payment()` sets the new transaction's `created_by` to the FM reviewer. The actual collector is `sponsor_payments.supervisor_id`. For direct transaction entry, `transactions.created_by` is the submitting user.
- `ak_post_transaction_journal_prepared()` currently credits 4100 for sponsorship net without distinguishing future coverage. Account 2200 already exists as deferred revenue.
- The existing transaction history uses UNION ALL over transactions and sponsor_payments, so linked approved submissions can appear twice.
- Backup evidence: 3,560 sponsorships; 22 transactions (16 posted, 5 voided, 1 cancelled); 11 supervisor submissions (8 approved, 3 returned); all 8 approved submissions have a transaction link.
- SP-000001: 11,000 SDG received 2026-08-16 for July/2027, currently credited to 4100.
- SP-000004: 15,000 SDG received for September/2026 against sponsorship IMP-SH-002947 with a 10,000 SDG monthly commitment.
- The pause handler records PAUSE/RESUME audit events with timestamps, but the inspected backup has no PAUSE/RESUME rows. One imported active sponsorship has both pause dates set to 2026-08-10. Do not fabricate missing historical pause intervals. Preserve every future pause/resume event.

## Implementation sequence

1. Design and migrate explicit per-payment monthly allocation/credit evidence and pause-period history. No runtime CREATE/ALTER.
2. Integrate the allocation operation atomically into both direct FM approval and supervisor payment approval; preserve the actual collector identity.
3. Correct posting: future allocated net and unallocated excess to liability 2200; earned allocated net to 4100; fee to 4200. Add idempotent monthly recognition journals and reversal handling for later transaction voids.
4. Build separate advance-payment and two-month arrears pages. Reuse existing supervisor authorization. Approved submissions are read through linked transactions; pending/returned submissions remain visible separately.
5. Add regression harness coverage for exact sponsorship/month, partial accumulation, overpayment, pending duplicates, future periods, voids, pause/resumption month boundaries, collector identity, and journal balance/idempotency.
6. Run PHP syntax checks, CI migration tests, and runtime verification against the local test database; update this document and all canonical project documents with exact evidence.

## Current verification boundary

Source/schema audit only. No application code, migration, database row, or accounting journal has been changed. No PHP, SQL migration, CI, or local runtime test has been run. Continue on main only. Do not reopen closed HR, i18n, salary advance, or Sponsor Tree work.
