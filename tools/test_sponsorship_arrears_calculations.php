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

$filters = ak_sar_normalize_arrears_filters([
    'code' => '  IMP-SH-000001  ',
    'sponsor' => '  علي  ',
    'child' => ['malformed'],
    'supervisor_id' => '19'
]);
$check('arrears_filters_trim_and_reject_non_scalar', $filters === [
    'code'=>'IMP-SH-000001',
    'sponsor'=>'علي',
    'child'=>'',
    'supervisor_id'=>19
]);
$emptyFilters = ak_sar_normalize_arrears_filters([]);
$check('empty_arrears_filter_does_not_trigger_search', !ak_sar_has_arrears_filter($emptyFilters));
$check('supervisor_filter_is_a_valid_search_criterion', ak_sar_has_arrears_filter(['code'=>'','sponsor'=>'','child'=>'','supervisor_id'=>19]));
$check('arrears_like_wildcards_are_escaped', ak_sar_like_value('A%_!B') === '%A!%!_!!B%');


$summaryFixture = [
    ['error'=>null, 'months'=>[
        ['outstanding'=>500.00, 'pending'=>[['id'=>11]]],
        ['outstanding'=>250.00, 'pending'=>[]]
    ]],
    ['error'=>null, 'months'=>[
        ['outstanding'=>100.00, 'pending'=>[['id'=>12],['id'=>13]]]
    ]],
    ['error'=>'بيانات غير مكتملة', 'months'=>[]]
];
$summary = ak_sar_summarize_arrears($summaryFixture);
$check('summary_counts_only_calculated_arrears_sponsorships', $summary['sponsorship_count'] === 2 && $summary['calculation_error_count'] === 1);
$check('summary_totals_cover_all_unpaid_months', $summary['outstanding_total'] === 850.00 && $summary['unpaid_month_count'] === 3);
$check('summary_counts_pending_requests_without_treating_them_as_paid', $summary['pending_request_count'] === 3);
$pendingView = ak_sar_filter_arrears_view($summaryFixture, 'pending');
$check('pending_card_view_shows_only_months_with_pending_requests', count($pendingView) === 2 && count($pendingView[0]['months']) === 1 && count($pendingView[1]['months']) === 1);
$check('non_pending_views_preserve_all_qualifying_rows', count(ak_sar_filter_arrears_view($summaryFixture, 'all')) === 3);

echo 'PASS | ' . implode(' | ', array_map(
    static fn(string $name, bool $passed): string => $name . '=' . ($passed ? 'PASS' : 'FAIL'),
    array_keys($checks),
    array_values($checks)
)) . PHP_EOL;
