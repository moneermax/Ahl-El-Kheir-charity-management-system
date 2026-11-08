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


## 2026-11-08 — Month-end recognition and historical backfill approved

The user approved recognition on the **last calendar day of the covered month**, rather than the first day, and approved a historical backfill with append-only journal corrections. The source backup is test data, not live financial data.

### Exact backup evidence

Backup: `database/ahl_el_kheir.sql`, generated 2026-11-08 16:12, MariaDB 10.4.32. The backup was read from Git blob `3255afebaa319cb7c8232f82ceb148f4fefe3b57` at commit `b957c17bebbb646bbfbe7709b109ae03466263b2`.

Seven posted, non-voided `sponsorship_payment` transactions are eligible for the initial monthly-sponsorship backfill:

| Transaction | Payment | Covered month | Monthly commitment | Covered allocation | Unallocated excess | Original journal |
|---:|---|---|---:|---:|---:|---|
| 1 | SP-000001 | July 2027 | 11,000 | 11,000 | 0 | JE-000001 |
| 2 | SP-000002 | August 2026 | 11,000 | 11,000 | 0 | JE-000002 |
| 17 | SP-000004 | September 2026 | 10,000 | 10,000 | 5,000 | JE-000015 |
| 18 | SP-000005 | September 2026 | 10,000 | 10,000 | 5,000 | JE-000016 |
| 27 | SP-000006 | September 2026 | 15,000 | 15,000 | 0 | JE-000028 |
| 28 | SP-000010 | September 2026 | 10,000 | 10,000 | 0 | JE-000029 |
| 29 | SP-000011 | September 2026 | 10,000 | 10,000 | 0 | JE-000030 |

All seven have zero recorded administrative fees; gross equals net. The two overpayments are SP-000004 and SP-000005, each with 5,000 excess. No excess is automatically moved to another month.

### Backfill reconciliation target

- Total eligible gross/net receipts: **87,000 SDG**.
- August 2026 covered allocation: **11,000 SDG**, recognized 2026-08-31.
- September 2026 covered allocations: **55,000 SDG**, recognized 2026-09-30.
- Future July 2027 allocation: **11,000 SDG**, remains deferred until 2027-07-31.
- Unallocated excess: **10,000 SDG**, remains deferred until explicitly allocated.
- Target balance from these seven receipts after backfill: **4100 revenue = 66,000 SDG; 2200 deferred revenue = 21,000 SDG**.

### Append-only correction rule

Do not update, delete, void, or rewrite the seven original posted journals or their lines. Add separately traceable reclassification journals to move the original 87,000 net credit from 4100 to 2200, linked to each source transaction. Add month-end recognition journals dated 2026-08-31 and 2026-09-30 for 11,000 and 55,000 respectively. Preserve the remaining 21,000 in 2200.

The backup contains 3,560 sponsorships, 22 transactions (16 posted, 5 voided, 1 cancelled), and 11 supervisor submissions (8 approved, 3 returned); all eight approved submissions have linked transactions. Only the seven eligible posted monthly-sponsorship transactions above enter this backfill. Pending, returned, cancelled, and voided records are excluded.

No PAUSE/RESUME audit events exist in this backup. Do not invent historical pause intervals. The relevant sponsorships are active in the backup; imported records use the accepted 2026-08-01 monitoring baseline, while newly created records use their actual start dates.

### Implementation status

Approval and backup reconciliation are complete. **No migration, source code, database row, or journal has yet been changed.** Next: implement the allocation/pause-history schema, atomic posting and month-end recognition path, an idempotent dry-run/apply backfill tool, and rollback-safe verification. Apply only to the local test database after review; never run this backfill against live data.


### Historical commitment evidence limitation

For the six sponsorship records referenced by the seven payments, the backup provides the current `sponsorships.monthly_amount` values shown above. No matching amount-change audit rows were found for those sponsorship IDs. Current application source creates sponsorships with an initial amount and records that amount in the creation audit, but no sponsorship edit route was found that changes `monthly_amount`. Therefore the backfill will snapshot the amount present in this backup as the available commitment evidence; it will not invent a different historical amount. Any manual database-only change outside the application cannot be reconstructed from this backup.


### 2026-11-08 — Posting-path and period-format audit

Further inspection of current `main` confirmed both live approval paths converge on `ak_post_transaction_journal_prepared()`:
- Ordinary direct/supervisor-entered transactions are approved in `modules/accounting/fm_transaction_review.php`, which marks the transaction posted and calls `ak_post_transaction_journal()`.
- Supervisor submissions are converted to posted transactions in `ak_approve_sponsor_payment()` in `modules/accounting/lib_sponsor_payment_approval.php`, which calls `ak_post_transaction_journal_prepared()` within the caller's open transaction.

Therefore the central prepared posting helper is the correct shared accounting integration point; duplicating allocation logic in the two UI handlers would be unsafe.

The UI stores coverage as English `F/Y` strings (examples: `September/2026`, `July/2027`), not ISO `YYYY-MM`. The existing parser is `DateTime::createFromFormat('!F/Y', $period)`. The implementation must use a strict parser consistent with this stored format and must not silently reinterpret malformed periods.

The transaction-entry workflow also permits a monthly-sponsorship record without a linked `sponsorship_id` in some paths/fixtures. Such receipts cannot be assigned to an individual sponsorship obligation. They must remain fully unallocated/deferred rather than being incorrectly recognized as earned sponsorship revenue or assigned to a guessed sponsorship. The approved seven historical backfill rows all have a concrete `sponsorship_id`.

The existing transaction void helper reverses the original transaction journal by adding a separate reversal journal, but it also writes void metadata to the original journal header. Any new sponsorship recognition/reclassification reversals must integrate atomically with that helper, leave original journal lines unchanged, and avoid leaving recognized revenue or deferred liability behind after a source transaction is voided.

No source code, migration, or database data has been changed in this audit step.
