<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/modules/sponsorships/lib_payment_status.php';

$checks = [];
$check = static function (string $name, bool $passed) use (&$checks): void {
    $checks[$name] = $passed;
    if (!$passed) throw new RuntimeException('FAIL | ' . $name);
};

$asOf = new DateTimeImmutable('2026-11-09');
$base = [
    'id'=>1,
    'sponsorship_code'=>'IMP-SH-000001',
    'monthly_amount'=>10000.00,
    'status'=>'active',
    'start_date'=>'2025-01-01',
    'end_date'=>null,
    'pause_start_date'=>null,
    'pause_end_date'=>null
];
$allocations = [
    '2026-08-01'=>[
        'commitment_versions'=>1,'commitment_amount'=>10000.00,
        'allocated_amount'=>5000.00,'unallocated_amount'=>0.00
    ],
    '2026-09-01'=>[
        'commitment_versions'=>1,'commitment_amount'=>10000.00,
        'allocated_amount'=>0.00,'unallocated_amount'=>0.00
    ]
];

$months = ak_sar_month_rows($base, $allocations, [], $asOf);
$check('imported_baseline_starts_august_2026', count($months) === 3 && $months[0]['month'] === '2026-08-01');
$check('partial_payment_reduces_outstanding', $months[0]['outstanding'] === 5000.00);
$check('two_consecutive_unpaid_eligible_months', ak_sar_has_two_consecutive_unpaid_months($months));

$pauseEvents = [
    ['event_type'=>'pause','effective_date'=>'2026-09-01'],
    ['event_type'=>'resume','effective_date'=>'2026-09-30']
];
$pausedMonths = ak_sar_month_rows($base, $allocations, $pauseEvents, $asOf);
$check('fully_paused_month_is_excluded', count($pausedMonths) === 2 && $pausedMonths[0]['month'] === '2026-08-01' && $pausedMonths[1]['month'] === '2026-10-01');
$check('paused_month_does_not_break_unpaid_sequence', ak_sar_has_two_consecutive_unpaid_months($pausedMonths));

$paidSeptember = $allocations;
$paidSeptember['2026-09-01']['allocated_amount'] = 10000.00;
$paidMonths = ak_sar_month_rows($base, $paidSeptember, [], $asOf);
$check('fully_paid_month_resets_unpaid_sequence', !ak_sar_has_two_consecutive_unpaid_months($paidMonths));

$newSponsorship = $base;
$newSponsorship['sponsorship_code'] = 'SH-000001';
$newSponsorship['start_date'] = '2026-08-15';
$newMonths = ak_sar_month_rows($newSponsorship, [], [], $asOf);
$check('partial_first_month_is_excluded', count($newMonths) === 2 && $newMonths[0]['month'] === '2026-09-01');

$endedSponsorship = $base;
$endedSponsorship['status'] = 'completed';
$endedSponsorship['end_date'] = '2026-09-15';
$endedMonths = ak_sar_month_rows($endedSponsorship, [], [], $asOf);
$check('partial_final_month_is_excluded', count($endedMonths) === 1 && $endedMonths[0]['month'] === '2026-08-01');

$legacyPause = $base;
$legacyPause['pause_start_date'] = '2026-09-10';
$legacyPause['pause_end_date'] = '2026-09-20';
$legacyPausedMonths = ak_sar_month_rows($legacyPause, [], [], $asOf);
$check('legacy_pause_interval_excludes_touched_month', count($legacyPausedMonths) === 2 && $legacyPausedMonths[1]['month'] === '2026-10-01');

$unknownEnd = $base;
$unknownEnd['status'] = 'completed';
try {
    ak_sar_month_rows($unknownEnd, [], [], $asOf);
    $check('unknown_end_date_fails_closed', false);
} catch (RuntimeException $e) {
    $check('unknown_end_date_fails_closed', true);
}

echo 'PASS | ' . implode(' | ', array_map(
    static fn(string $name, bool $passed): string => $name . '=' . ($passed ? 'PASS' : 'FAIL'),
    array_keys($checks),
    array_values($checks)
)) . PHP_EOL;
