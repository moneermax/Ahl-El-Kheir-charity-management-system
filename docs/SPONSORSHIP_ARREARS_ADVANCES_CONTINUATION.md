# Sponsorship Advances and Two-Month Arrears — Continuation

**Status:** Active — first implementation slice committed; local runtime acceptance pending.
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

### 2026-11-08 — Allocation schema foundation committed

Commit: `609c1a7ad474e00648096907908eaea010d84c7d`.

Added migration `database/migrations/2026-11-08_sponsorship_payment_accounting.sql` defining:
- one immutable monthly allocation snapshot per source transaction;
- append-only sponsorship accounting event links for historical reclassification, month-end recognition, and void reversal journals;
- append-only PAUSE/RESUME event history.

The migration is **committed but not applied**. No PHP posting path, pause handler, recognition runner, backfill tool, or database row/journal has been changed. Do not apply the migration as a standalone feature release until the matching source integration is committed.

Additional source facts confirmed:
- Imported sponsorships are explicitly coded `IMP-SH-xxxxxx`; the import routine sets their original start date to 2025-01-01. Their approved monitoring baseline remains 2026-08-01.
- New sponsorships receive `SH-xxxxxx` and `start_date = CURDATE()`; their monitoring begins at that actual start date.
- The transaction-entry form creates a separate transaction for each month/purpose line; monthly lines use `months_covered = 1.00`. There is no multi-month split hidden inside one transaction.
- The current pause/resume actions are in `modules/sponsorships/view.php`. They update the legacy date fields and audit log in one DB transaction; the new append-only pause-event insert must be added to that same transaction.

The next source change must use the explicit import code prefix to determine the monitoring baseline, snapshot the monthly commitment once per sponsorship/month, serialize allocation with a sponsorship row lock, and keep unassignable/ineligible/excess receipt amounts deferred.



### 2026-11-09 — Live allocation and pause-event source integration

The first live-path source slice is implemented on `main`:
- Monthly `sponsorship_payment` posting now records one immutable allocation snapshot inside the same DB transaction as the receipt journal.
- The receipt's net amount initially credits deferred revenue account 2200; administrative fees remain credited to 4200. Month-end recognition is a separate not-yet-implemented step.
- Supervisor approval passes `sponsor_payments.supervisor_id` directly into the posting helper because the transaction link/status is updated only after journal posting; direct transaction entry uses `transactions.created_by`.
- Allocation is capped at the remaining monthly commitment for the exact sponsorship/month. Excess, unlinked receipts, and ineligible months remain unallocated/deferred.
- Imported sponsorship monitoring begins 2026-08-01; new sponsorship monitoring uses the actual start date. Partial start/end months and months touched by pause periods are not allocated.
- Existing commitment snapshots are reused for subsequent payments in the same sponsorship/month. Prior allocations from voided transactions are excluded from the paid total.
- The sponsorship pause/resume handler appends an explicit event in its existing DB transaction only when the status actually transitions.
- Legacy pause dates are not converted into invented event rows; they are used only as bounded date evidence when both dates exist. Ambiguous legacy pause state fails closed.

**Verification boundary:** source implementation only. Migration remains unapplied. The repository PHP syntax workflow was extended to lint the new helper; its run result and local XAMPP/MariaDB runtime verification are still pending. Transaction-void reversal integration, month-end recognition, and the historical backfill tool remain unimplemented. Do not apply the migration or run the backfill yet.


### 2026-11-09 — Append-only event effects included in transaction void

The transaction void helper now calls a sponsorship-accounting reversal helper inside its existing DB transaction. It:
- reads only posted, non-voided historical-reclassification and month-end-recognition event journals linked to the source transaction;
- verifies each source event journal is balanced;
- aggregates the event effect by account and reverses only the residual net movement (so a fully reclassified-and-recognized month nets to zero);
- creates a separate traceable `transaction_void_reversal` event and journal without changing original event journals or lines;
- uses the same entry date as the existing transaction-void journal convention.

If event evidence is unbalanced or the generated reversal is incomplete, the existing outer transaction rolls back the entire void operation. The PHP syntax workflow now includes the transaction void helper.

**Verification boundary:** PHP syntax CI and repository migration CI are required after this commit; no local database runtime test has been run. Month-end recognition and the historical backfill runner are still pending. The migration remains unapplied.


### 2026-11-09 — Migration CI coverage added

The full-schema MariaDB 10.4.32 CI job now applies `2026-11-08_sponsorship_payment_accounting.sql` twice against the checked-in database schema and asserts all three tables, all three unique indexes, and all ten foreign-key constraints. This verifies clean-install compatibility and repeatability in CI; it does not apply the migration to the local XAMPP database.


### Legacy pause/resume compatibility hardening

The pause allocator now fails closed for a currently paused sponsorship with no open event history. For an active/completed/cancelled sponsorship, it also considers only the exact bounded legacy `pause_start_date`/`pause_end_date` interval when both dates are present. The resume handler does not create an unmatched `resume` event for a pause that predates event logging; it retains the legacy date evidence instead. New pause/resume cycles continue to append paired events. This avoids inventing historical intervals or creating malformed event sequences.


### 2026-11-09 — Month-end recognition runner added

Added `tools/recognize_sponsorship_revenue_month_end.php` and the shared recognition operation:
- accepts one covered month in `YYYY-MM` format;
- refuses future or not-yet-ended months;
- defaults to a read-only dry run; `--apply` is required to post;
- recognizes only allocated amounts from posted, non-voided transactions;
- posts Dr 2200 / Cr 4100 dated on the covered month's last calendar day;
- records one idempotent event/journal per allocation, retaining transaction-level traceability;
- verifies any existing event/journal before treating a repeat run as already complete;
- creates no tables and performs no runtime DDL.

This runner does not perform the historical backfill. It must not be used until the migration and full rollback-only integration harness are verified.


### 2026-11-09 — Rollback-only sponsorship accounting integration harness

Added `tools/test_sponsorship_payment_accounting_rollback.php` and wired it into the full-schema MariaDB CI job after the migration is applied. It uses an existing eligible sponsorship and rolls back every test mutation. Coverage includes:
- exact sponsorship/month allocation and commitment cap;
- partial-payment accumulation and excess retention;
- pause-touched month exclusion;
- unlinked receipt deferral and malformed period rejection;
- month-end recognition date, amount, and idempotency;
- append-only historical reclassification + recognition effects reversed atomically with the original transaction void;
- per-account zero residual after void and full rollback.

The harness creates no fixtures permanently and performs no DDL. Local XAMPP runtime verification remains a separate acceptance gate.


### 2026-11-09 — First rollback-only integration run passed in CI

At commit `06a5bde0620a52ce207129b187ebbeba40661a04`, the full-schema MariaDB 10.4.32 workflow completed the new sponsorship accounting rollback-only integration step successfully, after applying the sponsorship accounting migration twice. The harness found an existing eligible sponsorship in the checked-in backup and exercised allocation, partial accumulation, excess deferral, pause-month exclusion, unlinked receipts, strict period parsing, month-end recognition/idempotency, append-only event reversal on void, per-account zero residual, and rollback. No local XAMPP database was modified.


### 2026-11-09 — Approved historical backfill tool added

Added `tools/backfill_sponsorship_payment_accounting.php`. It is intentionally restricted to the seven source transactions verified in the checked-in backup (IDs 1, 2, 17, 18, 27, 28, 29), and fails closed if transaction, supervisor submission, sponsorship commitment, original journal, or pause evidence differs from the approved manifest.

Expected reconciliation for those seven:
- Original receipts: 87,000 SDG, preserved unchanged
- Allocations to covered months: 77,000 SDG
- Unallocated excess: 10,000 SDG
- Earned revenue after August/September month-end recognition: 66,000 SDG
- Deferred sponsorship revenue: 21,000 SDG

The tool defaults to a read-only dry run; `--apply` is required. Apply appends seven allocation snapshots, seven reclassification journals/events, and six month-end recognition journals/events. July 2027 remains deferred. The rollback-only integration harness now exercises the backfill twice and checks idempotency and the 66,000/21,000 account reconciliation. Migration application and local XAMPP verification are still separate gates.


### 2026-11-09 — Historical backfill and target reconciliation passed in CI

At commit `28483dcd7329406a0b2cb3651b2c08ce9af3951e`, the full-schema MariaDB 10.4.32 job passed the rollback-only sponsorship accounting integration step, including the approved seven-transaction historical backfill, append-only reclassification, August/September recognition, repeat execution, and the 66,000 SDG earned / 21,000 SDG deferred reconciliation. The entire batch was rolled back by the harness; the CI database was not left with backfill records.

The harness was then tightened to explicitly assert that a backfill dry-run creates zero allocation rows before the apply simulation. This additional assertion is in the next CI run.


### Backfill CLI dry-run CI coverage

The full-schema MariaDB job now also runs `php tools/backfill_sponsorship_payment_accounting.php --dry-run` against the imported backup after applying the migration. This validates the exact seven-row manifest through the public CLI entry point before the rollback-only apply/idempotency harness runs.


### Rollback harness repeatability

The rollback-only harness now accepts either a fresh historical backfill state or an already-applied state. It can therefore be run before or after the local backfill without assuming that the seven approved allocation rows are absent. All harness changes remain inside its outer transaction and are rolled back.


### Latest CI result — 2026-11-09

Commit `69e6768f9bd0ee6d90de21ff9fa938c296d23b66`, workflow run `42569957570`, completed successfully. Confirmed: PHP lint; migration application and repeatability; the public historical backfill dry-run against the exact seven backup rows; rollback-only live posting, allocation, pause, month-end recognition, void reversal, backfill apply/idempotency, dry-run no-mutation, and 66,000/21,000 SDG target reconciliation. All test mutations were rolled back in CI. **Local migration and local backfill remain unapplied.**

## 2026-11-09 — Local XAMPP sponsorship accounting acceptance: PASS

The previously pending local acceptance gates have now passed against the user's Linux Mint XAMPP MariaDB 10.4.32 test database.

Environment and backup:
- XAMPP MariaDB 10.4.32 was confirmed on TCP port 3306 using `/opt/lampp/var/mysql/`.
- Database `ahl_el_kheir` was confirmed present.
- Pre-migration dump created at `backups/pre_sponsorship_accounting/ahl_el_kheir_before_migration.sql` (5.9 MB).
- Migration `database/migrations/2026-11-08_sponsorship_payment_accounting.sql` applied without SQL errors; all three new tables were confirmed present.

Local rollback-only integration:
```
PASS | sponsorship_allocation=PASS | live_posting_deferred=PASS | partial_accumulation=PASS | excess_deferred=PASS | pause_month_excluded=PASS | unlinked_deferred=PASS | strict_period=PASS | month_end_recognition=PASS | recognition_idempotency=PASS | void_event_reversal=PASS | historical_backfill=PASS | backfill_idempotency=PASS | target_reconciliation=66000/21000 | rollback=PASS
```
The harness rolled back its synthetic test mutations.

Historical backfill:
- Dry-run returned `state=ready` and exactly 7 transactions, gross 87,000 SDG, allocated 77,000 SDG, excess deferred 10,000 SDG, earned target 66,000 SDG, deferred target 21,000 SDG. It reported no data changed.
- The explicitly approved `--apply` completed with `state=applied`: 7 transactions; 87,000 gross; 77,000 allocated; 10,000 excess deferred; 66,000 earned; 21,000 deferred; 6 new month-end recognition events; 0 existing.
- Subsequent repeated dry-runs returned `state=already_applied` with the identical reconciliation and `No data was changed`, confirming repeat-run idempotency.
- The backfill implementation validates the seven source transactions and their original posted journals, appends separate reclassification/recognition journals and events, and verifies the 4100/2200 ledger targets before commit. Original source journals are not rewritten.

**Acceptance boundary:** local migration, rollback-only integration, historical apply, target reconciliation, and repeat-run idempotency are verified. This closes the sponsorship payment accounting/backfill acceptance gate for the approved seven-row historical set. It does not close the broader Sponsorship Advances / Two-Month Arrears feature: the advance-payment and arrears user workflows remain a separate next implementation/verification phase. Do not rerun the migration, backfill apply, or rollback harness without concrete regression evidence.

Next work: continue the existing Sponsorship Advances / Two-Month Arrears feature by auditing the current source/schema for the advance-payment and arrears views, then implement the smallest justified UI/workflow slice. Preserve the agreed eligibility, pause, pending-submission, partial-payment, and two-consecutive-eligible-month rules. Do not reopen unrelated closed modules.

## 2026-11-09 — Arrears/advance report and pending-duplicate guard implementation

The first implementation slice for the approved Sponsorship Advances / Two-Month Arrears feature is now committed on `main`.

Implemented:
- `modules/sponsorships/lib_payment_status.php`: read-only report calculations sourced only from posted, non-voided `sponsorship_payment_allocations`. Allocations are aggregated per sponsorship and covered month; zero commitment snapshots are ignored as non-obligation snapshots; unallocated balances are separately aggregated, including allocations without a covered month.
- `modules/sponsorships/arrears.php`: reports sponsorships that reach two consecutive eligible completed months with a positive unpaid balance. Partial allocations reduce outstanding without clearing a month. Fully paused/touched months are excluded and do not break the eligible-month sequence. Partial first/last months are excluded.
- `modules/sponsorships/advances.php`: reports future-month allocations and unallocated deferred credits separately. Pending/returned future submissions are displayed as review states, not as paid allocations. Excess is never automatically carried into another month.
- Both pages enforce server-side access: Admin and VGM can see all sponsorships; supervisors are filtered through the existing `supervisorCanAccessSponsor()` authorization rules, including letter/gender scope, manual overrides, and legal-entity ownership. No additional roles are authorized.
- `modules/transactions/create.php`: both supervisor submission and direct FM-review entry paths lock the sponsorship row, revalidate that it is active, and reject a second pending monthly-sponsorship submission for the same sponsorship/month across both `sponsor_payments` and `transactions`. Supervisor multi-line submission is now atomic; notifications are sent after commit.
- `config/sponsor_assignments.php` accepts an optional preloaded letter scope while retaining the existing authorization function and backward-compatible two-argument calls. The reports bulk-load supervisor letter scope to avoid a per-sponsor authorization query.
- Sidebar navigation and Arabic/English navigation labels were added for Admin, VGM, and supervisors.
- No schema migration, runtime DDL, or data mutation was introduced.

### CI verification

Commit `404930f6633e34d055f81e6dac00765a51bec15d`, workflow run `42574481700`: all six jobs passed, including `php-lint` and the full MariaDB migration workflow.

New pure calculation contract test:
`tools/test_sponsorship_arrears_calculations.php`

Exact result:
```
PASS | imported_baseline_starts_august_2026=PASS | partial_payment_reduces_outstanding=PASS | two_consecutive_unpaid_eligible_months=PASS | fully_paused_month_is_excluded=PASS | paused_month_does_not_break_unpaid_sequence=PASS | fully_paid_month_resets_unpaid_sequence=PASS | partial_first_month_is_excluded=PASS | partial_final_month_is_excluded=PASS | legacy_pause_interval_excludes_touched_month=PASS | unknown_end_date_fails_closed=PASS
```

### Remaining acceptance boundary

This is a CI-verified source implementation, not yet a locally runtime-accepted feature. The user's local XAMPP application/database still needs verification of:
- both report pages and their real SQL result sets;
- Admin/VGM all-record scope and supervisor-only assigned scope;
- future allocations, unallocated credits, pending and returned states;
- duplicate prevention for both direct and supervisor submission paths, including a second concurrent submission;
- normal existing payment submission and FM approval behavior.

Do not rerun the already-closed sponsorship accounting backfill or rollback harness. Do not claim the advances/arrears feature closed until local runtime verification is recorded.
