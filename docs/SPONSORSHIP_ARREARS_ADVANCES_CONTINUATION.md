# Sponsorship Advances and Two-Month Arrears — Continuation

**Status:** Active — search-driven arrears report locally accepted; advances report and payment-workflow acceptance remain open.
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

## Implementation sequence and current status

1. **Complete:** per-payment allocation snapshots and append-only pause history migration; no runtime DDL.
2. **Complete:** atomic allocation/posting for direct FM approval and supervisor payment approval, with collector identity preserved.
3. **Complete:** future/unallocated net deferred to 2200, earned net recognized to 4100 at covered-month end, append-only void reversal, and approved seven-row historical backfill. Local XAMPP acceptance is recorded below.
4. **Implemented; local runtime pending:** separate arrears and advances reports, using existing supervisor authorization; pending/returned submissions remain separate from posted collections and linked approved submissions are not double-counted.
5. **Partially verified:** the new pure calculation contract test passes ten rule checks. Duplicate-pending serialization is implemented but still needs local database/runtime verification, including concurrency.
6. **CI passed; local feature acceptance pending:** PHP lint, calculation contract test, and all six jobs passed in workflow run 42574481700. Verify the two pages, real SQL result sets, role scope, pending/returned display, and both payment-entry paths against local XAMPP before closure.

## Current verification boundary

The sponsorship accounting migration and seven-row historical backfill have passed local XAMPP acceptance as documented below. The new arrears/advances source implementation has passed CI, but the report pages and duplicate-pending guard have **not** yet passed local runtime verification. No new schema migration or database mutation was introduced for the report feature. Continue on main only. Do not reopen closed HR, i18n, salary advance, Sponsor Tree, or the accepted sponsorship accounting backfill without concrete regression evidence.


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

Latest source/test commit `41c93536a08c546c0517adf27c75f3d142593a68`, workflow run `42575136850`: all six jobs passed, including `php-lint`, the full MariaDB migration workflow, and the new database-backed report/duplicate-guard runtime step.

New pure calculation contract test:
`tools/test_sponsorship_arrears_calculations.php`

Exact result:
```
PASS | imported_baseline_starts_august_2026=PASS | partial_payment_reduces_outstanding=PASS | two_consecutive_unpaid_eligible_months=PASS | fully_paused_month_is_excluded=PASS | paused_month_does_not_break_unpaid_sequence=PASS | fully_paid_month_resets_unpaid_sequence=PASS | partial_first_month_is_excluded=PASS | partial_final_month_is_excluded=PASS | legacy_pause_interval_excludes_touched_month=PASS | unknown_end_date_fails_closed=PASS
```

### Database-backed CI runtime evidence and data finding

The new rollback-only runtime harness, `tools/test_sponsorship_arrears_advances_runtime.php`, ran successfully in the full-schema MariaDB 10.4.32 CI job. It verified:
- report SQL execution for arrears and advances;
- Admin and VGM each receive all 3,560 sponsorship rows;
- supervisor results contain only sponsors accepted by the existing authorization function;
- an unapproved role is denied;
- duplicate pending supervisor submissions are rejected;
- duplicate pending direct FM-review transactions are rejected.

The test inserts its synthetic pending rows inside transactions and rolls both transactions back. No persistent test rows remain.

The first runtime assertion exposed 1,745 sponsorship rows with no matching `family_children` record (3,560 total, 1,815 with a child match, zero missing sponsor records). The report query now uses a left join for the child relation, keeps all sponsorship rows for Admin/VGM, keeps supervisor filtering tied to the sponsor record, labels unlinked child records, and does not render a broken detail link for them.

The duplicate tests prove sequential rejection through both entry tables while holding the sponsorship row lock. A true two-process concurrent execution test has **not** been run and must not be described as proven.


## 2026-11-09 — Local XAMPP database-backed runtime harness: PASS

The user pulled `main` through commit `0d78c02e2c9ba20b0fe5e49da3d7e8a11579c701` and ran:

```bash
/opt/lampp/bin/php tools/test_sponsorship_arrears_advances_runtime.php
```

Exact local output:

```
PASS | pending_duplicate_rejected_December_2026=PASS | pending_duplicate_rejected_January_2027=PASS | admin_scope_returns_all_sponsorships=PASS | vgm_scope_returns_all_sponsorships=PASS | admin_scope_retains_sponsorships_without_child_links=PASS | supervisor_scope_contains_only_authorized_sponsors=PASS | arrears_report_query_executes=PASS | advances_report_query_executes=PASS | unapproved_role_denied=PASS
```

This confirms the database-backed report queries, Admin/VGM all-sponsorship scope, retention of sponsorships without linked child rows, supervisor authorization filtering, denial of an unapproved role, and sequential pending-duplicate rejection through both supervisor-submission and direct FM-review transaction tables. The harness uses transactions and rolls synthetic pending rows back.

**Acceptance boundary:** this is local XAMPP database-backed harness verification, not yet full UI/workflow acceptance. Still manually verify both pages under Admin, VGM, and supervisor accounts; inspect the pending/returned/future-credit presentation; and confirm normal payment submission and FM approval remain unaffected. True two-process concurrency remains untested. Do not mark the feature closed until these remaining checks are accepted.

### Remaining acceptance boundary

CI now verifies both calculation rules and database-backed report/duplicate-guard behavior. The user's local XAMPP application/database still needs verification of:
- visual behavior and actual page access for Admin, VGM, and assigned supervisors;
- future allocations, unallocated credits, pending and returned states in the local test data;
- normal existing payment submission and FM approval behavior.

Do not rerun the already-closed sponsorship accounting backfill or rollback harness. Do not claim the advances/arrears feature closed until local XAMPP acceptance is recorded.


## 2026-11-09 — Sponsorship report requests blocking logout: source fix committed, runtime pending

The user reported that both sponsorship arrears/advances pages remained loading without rendering, and logout also appeared to hang. Source inspection established a concrete shared-session defect: both report pages called `Session::start()` and then ran all report queries/calculations while retaining PHP's default per-session lock. A second request using the same browser session (including `logout.php`) waits for that lock. The report pages now capture the authenticated user ID, consume/clear the current flash queue, call `session_write_close()` before report work, and restore the request-local flash copy for the existing alerts include. This preserves the stored session's flash-consumption semantics while allowing concurrent logout/navigation requests.

Source commits on `main`:
- `f2f5159b5a868500f712ef9deb73b6225c0e8f40` — arrears page session-lock release.
- `a98b99b46c8cf170ec0e2933d59cf3274d19b57e` — advances page session-lock release.

**Verification status:** source-level cause and narrow fix identified; GitHub PHP syntax workflow run `42576565231` was queued at the time of this checkpoint. No local Apache/browser verification has yet been performed. Releasing the session lock explains the logout blockage but does not, by itself, prove why report rendering is slow. If the pages still do not render after this change, the next step is to capture the active MariaDB process/query and PHP/Apache error output while one report request is loading; do not change report calculations or add indexes without that evidence.

Do not rerun the already-passed report runtime harness. The feature remains open pending actual page rendering and workflow acceptance. Preserve unrelated staged Monthly Data Entry files and untracked local backups.

## 2026-11-09 — Sponsorship arrears report large-HTML rendering bottleneck: source fix + regression test

A source-level performance defect was identified in the global HTML translation output handler (config/lang.php). The handler protects every <code> element by replacing it with a unique placeholder, then previously restored placeholders with one str_replace() over the entire HTML document per protected element. The arrears report can render thousands of sponsorship-code <code> elements, making restoration work scale with both page size and protected-element count. The smaller advances report does not expose the same scale problem.

Correction on main:
- config/lang.php now restores the protected-block map with strtr($html, $protected), a single mapped replacement pass. This also avoids recursive replacement of newly inserted protected content.
- Added tools/test_large_html_translation_performance.php, which runs the actual translator against a synthetic 3,560-row sponsorship table and verifies all protected code blocks are restored without placeholder leakage.
- Added the regression test to .github/workflows/php-syntax-check.yml.

Commits: a538c2491da526452b146529ef57ea97c91a6675 (source), c04e98e26b286252ccf979d90d8585115175646c (test), f14513e8688547aa1dec7c599163981efa1c2acc (CI wiring).

Verification boundary: source change and regression test are committed; GitHub Actions is running. This fixes the confirmed quadratic restoration path, but the local Apache page-rendering acceptance has not yet been independently verified. The earlier session-lock change remains separate and is responsible only for allowing navigation/logout while report requests run. Do not rerun the already-passed database/runtime harnesses or accounting backfill. Next inspect the CI result, then have the user pull the commit and perform one final browser acceptance of the arrears report; if it still fails, capture the new exact PHP fatal location before changing anything else.


## 2026-11-09 — Arrears report changed to search-driven loading

The arrears page no longer calculates the report on initial page load. The initial GET performs only a bounded identity query for the ten newest sponsorship records in the user's authorized scope. These are search suggestions only; they are **not** represented as confirmed arrears candidates. No payment allocations, pause history, pending submissions, or month-by-month arrears calculations are loaded until the user submits at least one filter.

The explicit search supports sponsorship code, sponsor name, orphan name, and (for Admin/VGM) the operational supervisor recorded on the sponsor. Filters are applied in SQL before report history is fetched. Searches are capped at 100 matching authorized sponsorships; broad searches are rejected with a request to refine the criteria rather than silently returning a partial report. For each selected sponsorship, the calculation still receives its full relevant allocation and pause history, preserving the consecutive eligible-month rule. Supervisor records are prefiltered in SQL and rechecked with `supervisorCanAccessSponsor()`.

No schema/data/accounting changes were made. The advances report and its all-scope behavior remain unchanged. Added pure filter tests and a source-contract regression test, wired into the existing PHP workflow. CI and local browser acceptance are pending; do not claim runtime acceptance until the user tests the page after pulling main.


## 2026-11-09 — Search-driven arrears report CI verification

The final search-driven implementation is on `main` at `20462f507890d025d64e3cbe76ebc32dfdca804d`. GitHub Actions workflow run `42587929222` completed **success** across all six jobs. The PHP job linted the changed PHP files and passed both the arrears calculation/filter tests and the new search-flow contract test. The full-schema MariaDB 10.4.32 job passed the arrears/advances runtime harness, including the new checks that the initial suggestion query returns no more than ten rows and that an exact sponsorship-code filter retrieves only the requested sponsorship. Existing supervisor-scope and duplicate-pending checks also passed.

Relevant implementation commits: `21338cc9c3f11a5064725e3ec1e88048685f44b5` (SQL-side scoped search/history loading), `7d805a624a5bfb18c99bdff392c5e1a8944342cf` (search-first page and ten suggestions), `8c190884de6b3d6cd945feea15514ffea0ca80e9` and `24c987078465bc96ca370bbe1a5b365fd43b53f1` (input hardening), `20462f507890d025d64e3cbe76ebc32dfdca804d` (exact supervisor prefilter precedence). Regression coverage and CI wiring are in `a6b039ee9fec46e5a1623b09d7afc5d97c8348e4`, `85fb0ffe4f7cf92330096bc7eced72b37ff84e53`, `8cac0d0ab67a7dc4992a671d2d9d240cad161555`, `43001206de63629991f20ee5861f21b72b0c854c`, `cfb40df510c7b89fead64ccd3f8cd45ad2530797`, and `f4107aea8deee7eab149c1217c0efb467ebba6cc`.

**Remaining acceptance:** local Apache/browser UI verification only. Pull the latest `main`, open the arrears page, confirm it renders promptly with ten suggestions and no arrears results before search, then search one known sponsorship by code and confirm the result. Also confirm a broad filter is rejected with a refine-search message. No database migration or data mutation is required. Do not rerun the already-passed accounting backfill or closed report runtime tests unless a regression is found.


## 2026-11-09 — Search-driven arrears report: local browser acceptance CLOSED

The user completed local browser acceptance of the search-driven arrears report.

Verified from the user's actual page results:
- Searching for `SH-003559` returned one qualifying sponsorship with two eligible completed months: September 2026 and October 2026.
- Each month showed a 5,000 SDG commitment, 0.00 SDG paid, and 5,000 SDG outstanding; no pending collection request was shown for either month.
- The user also tested the available filter/search methods. The search result for `SH-003559` confirms that the code filter reaches the arrears calculation and returns the qualifying record.
- The earlier `SH-003560` search correctly returned no arrears: the backup record was cancelled on its start date (2026-09-19), so it had no complete eligible month. Its presence in the ten newest suggestions was not a claim that it had arrears.

**Disposition:** search-driven arrears UI and its local browser acceptance are CLOSED at this evidence boundary. Do not weaken the two-consecutive-eligible-month rule or reopen the search implementation without concrete regression evidence.

**Next open work unit:** complete local acceptance of the remaining Sponsorship Advances / Two-Month Arrears workflow, beginning with the Advances report:
1. Verify the page renders and the locally applied accounting data is presented correctly: future allocations, unallocated excess, and any pending/returned future submissions that actually exist in the test database.
2. Verify the existing monthly sponsorship payment submission path and FM approval/posting path remain functional and that approved submissions are represented once through their linked transaction.
3. Verify the report pages and payment actions under their documented Admin, VGM, Supervisor, and FM role boundaries. Do not invent test records or alter the arrears eligibility rules to manufacture a result.
4. Preserve the explicit evidence gap: sequential duplicate rejection is verified; true two-process concurrent execution is not.

Do not rerun the completed sponsorship accounting migration, historical backfill, or rollback-only accounting harness. No production source or database changes were made in this browser-acceptance checkpoint.


## 2026-11-09 — Arrears statistics dashboard and full-result pagination (source committed; acceptance pending)

The user approved adding operational statistics cards and an explicit **عرض جميع المتأخرات** action. The latest implementation extends the existing search-driven arrears page; it does not replace the sponsorship accounting/allocation model.

### Implemented on main

- Initial GET remains lightweight: it loads only the ten newest authorized identity suggestions and does not load payment/pause history or calculate arrears.
- A filtered search or explicit all-report action is required before calculation. The all-report action uses the search=1 and all=1 query flags; it is not triggered automatically on page open.
- The former 100-sponsorship calculation cap and 501-row probe have been removed. The report calculates every sponsorship matching the supplied filters and the user's authoritative scope; it does not silently truncate the totals.
- Four cards summarize the complete result set:
  1. qualifying arrears sponsorship count;
  2. total outstanding amount across the unpaid eligible months shown by this report;
  3. count of eligible completed months with a positive outstanding balance;
  4. pending/returned collection submissions associated with those unpaid months.
- Summary totals are calculated before the page slices the visible result set.
- The table displays 25 sponsorships per page. Pagination preserves the search filters and selected card view.
- The pending/returned card filters the table to unpaid months with pending/returned collection submissions. Those submissions remain separate workflow states and never reduce confirmed arrears; only posted, non-voided allocation rows count as paid.
- Calculation-error records are shown separately in the table and excluded from confirmed arrears count/amount totals.
- Admin/VGM scope and the Supervisor SQL prefilter plus authoritative supervisorCanAccessSponsor() recheck remain unchanged.
- No schema migration, accounting change, financial write, or runtime DDL was introduced. The advances page is unchanged.

### Source and test commits

- 54775f28d18a595150a24ae60b2cd9408952eff3 — complete arrears report calculation and summary/filter helpers.
- 2ff6181b68fe8ff37e2c8fbbdc9922ab74160da5 — four-card dashboard, explicit all-report action, and 25-sponsorship display pagination.
- b58d08703d6570e7481dfda683ccf2419d9f86fe, af3840f23a151c03eb9cdbbed61ec89c957bd23b, fc0447ebba36f2e7ebd8486acdaf22acf4bba9c1, e459460ef513ecef34c276db6fa5c20e334e48f4 — regression-contract and calculation tests.
- 91bb22a208013e36accd8b930283096a2d3c5e97 — extends the database-backed runtime harness to exercise the unfiltered full arrears report and summary.

### Verification boundary

Source and test changes are committed to main. No CI result or local PHP/browser runtime result is asserted yet for this new dashboard slice. The full-result calculation deliberately occurs only after explicit user action; however, its response time and memory use against the local 3,560-sponsorship database must be measured before acceptance. Display pagination does not imply that database calculation itself is paginated.

### Next local acceptance

1. Pull latest main; inspect the working tree first and preserve unrelated local files.
2. PHP-lint the arrears page, report helper, and three sponsorship arrears tests.
3. Run:
   - php tools/test_sponsorship_arrears_calculations.php
   - php tools/test_sponsorship_arrears_search_contract.php
   - php tools/test_sponsorship_arrears_advances_runtime.php
4. In the browser, verify initial load remains lightweight; the explicit all-report action produces all four totals; clicking each card preserves the full totals; pending/returned view filters only the displayed rows; and pagination reaches the final page without losing filters.
5. Verify a known sponsorship-code search still works and broad searches are no longer rejected merely for exceeding 100 matches.
6. Verify Admin, VGM and Supervisor record scopes. Do not grant FM access to this operational report.
7. If the explicit all-report request is still slow, capture the exact active MariaDB query/process and Apache/PHP error evidence before changing query strategy or adding indexes. Do not guess at optimization.
8. Do not create fake payment/accounting records merely to make the cards look realistic. With no posted sponsorship payments, many qualifying arrears are expected; confirm the actual figures from the local report.

The existing two-consecutive-eligible-month, pause/resume, partial-payment, partial-first/final-month, and posted-allocation rules remain authoritative. Do not reopen the completed sponsorship accounting backfill or unrelated modules.


## 2026-11-09 — Always-visible role-scoped arrears dashboard (source committed; acceptance pending)

The user clarified the dashboard visibility boundary:
- Admin: organization-wide arrears.
- VGM: organization-wide arrears, not limited to sponsors assigned to the VGM or supervisors.
- Supervisor: only arrears for sponsors authorized by the existing sponsor assignment rules. The SQL prefilter remains only a prefilter; supervisorCanAccessSponsor() remains the authoritative recheck.

Implemented on main:
- Added ak_sar_get_arrears_summary(), a summary-only loader that fetches the sponsorship fields, posted allocation aggregates, pause events and relevant pending/returned period references needed by the existing month calculation. It does not load child/sponsor display details or build full report rows.
- The helper reuses ak_sar_month_rows() and ak_sar_has_two_consecutive_unpaid_months(). It does not approximate arrears from active sponsorship counts or raw monthly amounts.
- The four cards now render before the search form on initial page load and remain role-wide when the user runs a filtered search.
- Each card opens the full role-scoped arrears report in its corresponding view, with no search filters carried into the all-record view.
- If the summary calculation fails, the cards show an em dash rather than misleading zero values.
- The database-backed runtime test now compares Admin and VGM summary results with the full organization-wide report, compares Supervisor summary results with the full supervisor-scoped report, and prints the initial Admin summary duration.

No schema migration, accounting mutation, financial write, or runtime DDL was introduced.

Verification boundary: source and regression-test changes are committed. CI and local runtime/browser acceptance are still pending. The summary avoids detailed display joins and full result construction, but it still evaluates the exact eligibility rules across the user's full authorized sponsorship scope; its actual local response time must be measured. Do not claim it is fast until the timing result is observed.

### Exact next acceptance
1. Verify the latest main CI run passes PHP syntax, calculation/search contract tests, and the database-backed arrears/advances runtime harness.
2. On local XAMPP, inspect the working tree before pulling; preserve unrelated staged files and backups.
3. Run PHP lint on modules/sponsorships/arrears.php, modules/sponsorships/lib_payment_status.php, and the updated test files.
4. Run tools/test_sponsorship_arrears_search_contract.php and tools/test_sponsorship_arrears_advances_runtime.php; record dashboard_summary_ms.
5. In browser, confirm cards appear above search for Admin, VGM and Supervisor; Admin/VGM totals are organization-wide and Supervisor totals include only authorized sponsors; clicking each card opens the appropriate full result view; a specific search changes only the table, not the role-wide card totals.
6. If the initial page is slow, capture exact MariaDB/PHP timing and active query evidence before optimization. Do not change the eligibility algorithm or add schema/index changes speculatively.
7. Do not rerun the closed sponsorship accounting backfill or unrelated closed modules.


### 2026-11-09 — Dashboard summary CI verification

GitHub Actions run 42642522337 for commit e655f7318e97acb4d0fef3bdf057b29eb3c62bbc completed successfully across all six jobs. PHP lint and the arrears calculation/search-contract tests passed. The MariaDB 10.4.32 full-schema runtime job passed the arrears/advances harness, including:
- Admin dashboard summary exactly matches the full Admin arrears report.
- VGM dashboard summary exactly matches the full organization-wide arrears report.
- Supervisor dashboard summary exactly matches the full Supervisor-scoped arrears report.
- Existing Admin/VGM full-scope and Supervisor authorization checks pass.

The test measured the Admin summary-only calculation at 297.88 ms in this CI run. A prior successful run measured 111.34 ms, so treat the CI evidence as sub-second in the checked-in test database, not as a guarantee of local browser latency. The earlier CI attempts exposed and then fixed the stale search-contract expectation and a MariaDB collation mismatch in a UNION across the two pending-payment sources.

**Still pending:** local XAMPP PHP/runtime execution and browser acceptance. CI does not prove the user's local DB response time or actual role-account browser behavior.


## 2026-11-09 — VGM dashboard navigation consolidation

The VGM dashboard exposes the Sponsorship Arrears and Sponsorship Advances pages as clickable action cards beside the existing dashboard cards. All six cards are rendered in one horizontal row; on narrow screens the row remains unwrapped and scrolls horizontally. Existing card styling and dimensions are retained.

The two corresponding links were removed from the **vice_general_manager** sidebar menu only. The Supervisor sidebar entries remain unchanged. The destination pages' own role checks and report logic were not modified; no accounting logic, schema, or data was changed.

Source commits:
- `2b113e2e7b5b7050ddd28e65347b1bc4a52ba708` — VGM dashboard cards.
- `f1b96f190ceae9e36dbf7419029a29dc334bd84b` — VGM sidebar cleanup.

**Verification boundary:** source edits are committed to main. PHP syntax and local browser acceptance are still required. Verify that both cards navigate correctly, the VGM sidebar no longer lists either page, and the Supervisor sidebar still lists both pages. This is a navigation-only change; do not rerun the arrears accounting/runtime harnesses without regression evidence.


**Sidebar scope verification:** follow-up commit `8d04baecca5663fae536e7b39eac49481bf91efe` scopes the removal strictly to `vice_general_manager`; it restores/preserves both links in the Admin menu and preserves both links in the Supervisor menu. Source inspection confirms the final role-specific menus.


**Localization:** the two new card descriptions use stable translation keys present in both authoritative catalogs. Commits: `f8545bc511d0a685270ef301969dd3a65fcbae07` (dashboard keys), `2ecfabb7e5c7f5beb9d75e9f450dc53c0fad99fb` (Arabic), and `60f3c0cff35b8a27bca5c83f9de0bdd641be9375` (English). Source inspection confirms both keys are referenced and defined in both languages.


**2026-11-09 — PHP rendering hotfix:** The first single-row renderer commit accidentally retained the old outer `endforeach`, leaving an unmatched PHP loop terminator and causing HTTP 500 on the VGM dashboard. Commit `27256b3146aee6eb42833daecbe3a79dffbb0200` removes the extra terminator. Source inspection confirms the card loop now closes once. Local PHP lint and browser confirmation remain pending; do not treat the page as runtime-verified until the user pulls and tests it.


**2026-11-09 — VGM dashboard card alignment:** The horizontal action-card row now centers its contents when the available dashboard width is sufficient, while retaining a content-sized row and horizontal scrolling when the cards exceed the viewport. No card dimensions, destinations, role access, or business logic changed.



## 2026-11-09 — GM sponsorship arrears/advances read-only access (source committed; acceptance pending)

The GM role was being redirected from both existing sponsorship reports because the page guards and shared reporting-helper allowlists admitted only Admin, VGM, and Supervisor. The shared scope implementation already applies a restricted scope only to Supervisor; therefore adding `general_manager` grants GM organization-wide read-only visibility, matching the executive role and the existing Admin/VGM report scope.

Implemented on `main`:
- Added `general_manager` to the arrears and advances page guards.
- Added `general_manager` to all three shared helper guards used by initial arrears suggestions, report loading, and role-wide arrears summary.
- Added two always-visible, read-only links to the GM dashboard's **ما يحتاج انتباه الإدارة** section:
  - `modules/sponsorships/arrears.php`
  - `modules/sponsorships/advances.php`
- The GM sidebar remains executive-level and was not changed.
- FM remains denied. No payment, approval, posting, editing, accounting, schema, or data permissions were added.

Regression coverage added:
- Static search-contract assertions for both page guards, all three shared helper guards, and both GM dashboard destinations.
- Database-backed runtime assertions that GM sees all sponsorships, GM arrears summary equals the organization-wide full report, and GM advances output matches Admin's organization-wide output. Existing Supervisor scope and FM denial assertions remain.

Source commits:
- `163f4e313ba7bdba6526a02edf11c9454db05d89` — arrears page guard.
- `1308d0be91504a270d6917f5c85c309d80dbadd4` — advances page guard.
- `a3bcca546dc1e4495cbe7478e3568ec123227ffd` — shared helper guards.
- `f3fdea7fe38c567c49a1d325c0bc1871d56f8ce4` — GM dashboard links.
- `64300b26dba9facbfefa5468776d7558c982e8fc` — static contract coverage.
- `6bcaed8ab8fc38625a34d851e902941c890d0b65` — database-backed GM scope coverage.

Verification boundary: source and regression tests are committed; CI and local PHP/browser acceptance are pending. No local runtime result is claimed. Next, verify CI, then locally lint the changed PHP files, run the updated search-contract and arrears/advances runtime tests, and log in as GM to open both dashboard links and both direct URLs. Confirm GM sees organization-wide read-only reports and that FM remains denied. Then continue the existing Advances/payment/FM workflow acceptance. Do not rerun the closed accounting backfill or unrelated completed tests.



**Follow-up refinement — GM report detail and regression coverage:** The GM arrears view also exposes the same supervisor filter/name column already available to Admin and VGM, so executive-wide results can be reviewed by responsible supervisor. The static contract test asserts all four GM supervisor-detail presentation checks. The runtime harness now explicitly calls the GM suggestion loader as well. Commits: `754af0ee93bd90167aa99562630f9afe330fa9bf`, `da4575af3ff584dcf279844ab9531287fb00b430`, `164c7b4cf3e74c0929a5ee2542500591c8579627`. CI/local execution remains pending.



## 2026-11-09 — GM monitoring cards: browser acceptance and presentation refinement

**GM browser acceptance — confirmed by the user:** Both dashboard cards appear in the correct **ما يحتاج انتباه الإدارة** section, and both report pages open and display data. A small presentation refinement removes the redundant “فتح التقرير” label and left-arrow from both cards; the full card remains the link. Regression coverage asserts that this prompt/arrow is absent.


## 2026-11-10 — GM sponsor collection review is read-only (source committed; CI pending)

Business authority decision confirmed by the user: General Manager (GM) retains read-only access to pending supervisor collection submissions and recent review history. GM must not approve or return a collection. Existing action authority remains with the current Financial Manager aliases (`financial_manager`, `fm`, `finance`) and Admin aliases (`admin`, `sudo`).

The same server-side capability is now shared by the web queue and mobile API. The web queue keeps GM read access but hides approve/return controls and rejects forged POST actions with HTTP 403. The mobile GET queue returns a server-derived `can_review_actions` flag; the Flutter page hides controls for read-only users. The mobile POST action endpoint independently enforces the shared capability and denies GM. Ordinary transaction review and unrelated GM monitoring/report permissions are unchanged.

Regression coverage:
- PHP role-boundary contract test wired into the PHP workflow.
- Flutter widget test verifies pending collections render read-only when the server denies actions.
- No database/schema/accounting data changes and no runtime DDL.

Source commits: `dc30560`, `ecefa4b`, `6b2d170`, `f317c77`, `3e13d4b`, `9c3081d`, `fc60ea6`, `297d60a`, `03793a8`, `05cfcc3`.

Verification status: GitHub PHP workflow run `42754519832` and Flutter workflow run `42754442088` were queued/in progress at documentation time. Do not claim CI or local runtime acceptance until results are checked. Next verify both workflows, then perform local PHP lint and browser role acceptance (GM read-only; FM/Admin can approve/return). Do not rerun closed sponsorship arrears/advances calculation or accounting backfill tests.


### 2026-11-10 — Verification update

- PHP workflow run `42754730990` completed **success** across all six jobs. The PHP lint job passed and executed `tools/test_sponsor_payment_review_role_contract.php`, which reported: `PASS | sponsor_payment_review_roles=PASS | web_read_only_guard=PASS | mobile_api_read_only_guard=PASS | mobile_ui_read_only=PASS`. The test was rerun after removing its PHP interpolation warning.
- Flutter workflow run `42754442088`: Dart analysis passed, and both `fm_review_queue_page_test.dart` tests passed, including the new read-only rendering test. The overall Flutter suite did **not** pass: an unrelated existing `payroll_management_page_test.dart` assertion failed (`Expected: <10>`). No payroll files were changed.
- Mobile API auth workflow run `42754433616` failed at the unrelated existing assertion that an administrator can create a SQL backup on the disposable database. The preceding endpoint checks passed; this run did not contain a sponsor-review-specific role assertion.
- **Still required:** local Apache/browser acceptance of GM read-only queue access and confirmation that FM/Admin retain the action controls. No local live-database/API action test is claimed. No schema or accounting data was changed.


## 2026-11-10 — Sponsorship payment history duplicate-row correction (source committed; local acceptance pending)

The payment form history query in `modules/transactions/create.php` previously used `UNION ALL` to show both `transactions` and `sponsor_payments` without suppressing an approved submission after FM had linked it to its posted transaction. This made one approved collection appear twice in the history even though the accounting transaction and journal were created once.

Correction on `main`:
- The `sponsor_payments` history branch now excludes only rows where `status='approved'` and `transaction_id IS NOT NULL`.
- The linked posted transaction remains visible through the `transactions` branch.
- Pending and returned submissions remain visible.
- Approved legacy submissions without a transaction link remain visible rather than being silently hidden.
- No payment data, transaction, journal, schema, migration, or approval behavior changed.

Regression contract: `tools/test_sponsor_payment_history_dedup_contract.php`, wired into `.github/workflows/php-syntax-check.yml`.

Commits:
- `481af1e3df891bfde610f57625a8267331e603f4` — history query correction.
- `97bc6f36f1c765be5e53603571ec697848beb6b3` — regression contract.
- `cb70e7a9df4a0db3f7ae8546b55b5c280e89723e` — CI wiring.

**Verification boundary:** source and regression contract are committed; CI and local runtime/browser verification are not yet claimed. After CI passes, pull `main`, lint `modules/transactions/create.php` and `tools/test_sponsor_payment_history_dedup_contract.php`, run the new contract, and reopen the same sponsorship payment form. Confirm the 15,000 approved collection appears once as its posted transaction, while pending and returned submissions (if present) remain visible. Do not submit a payment or change financial data.
