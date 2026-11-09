<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$library = file_get_contents($root . '/modules/sponsorships/lib_payment_status.php');
$page = file_get_contents($root . '/modules/sponsorships/arrears.php');
$advancesPage = file_get_contents($root . '/modules/sponsorships/advances.php');
$gmDashboard = file_get_contents($root . '/dashboard/gm_dashboard.php');
if ($library === false || $page === false || $advancesPage === false || $gmDashboard === false) {
    throw new RuntimeException('Unable to read arrears report source.');
}

$checks = [];
$check = static function (string $name, bool $passed) use (&$checks): void {
    $checks[$name] = $passed;
    if (!$passed) throw new RuntimeException('FAIL | ' . $name);
};

$candidateStart = strpos($library, 'function ak_sar_get_arrears_candidates(');
$candidateEnd = strpos($library, 'function ak_sar_load_report_data(', $candidateStart ?: 0);
$candidateSource = ($candidateStart !== false && $candidateEnd !== false)
    ? substr($library, $candidateStart, $candidateEnd - $candidateStart)
    : '';

$check('initial_candidates_use_bounded_identity_query', str_contains($candidateSource, 'ORDER BY sp.id DESC LIMIT 100'));
$check('gm_role_allowed_by_arrears_page', str_contains($page, "['admin','general_manager','vice_general_manager','supervisor']"));
$check('gm_role_allowed_by_advances_page', str_contains($advancesPage, "['admin','general_manager','vice_general_manager','supervisor']"));
$check('gm_role_allowed_by_all_shared_report_guards', substr_count($library, "['admin', 'general_manager', 'vice_general_manager', 'supervisor']") === 3);
$check('gm_dashboard_exposes_both_sponsorship_monitoring_links', str_contains($gmDashboard, 'modules/sponsorships/arrears.php') && str_contains($gmDashboard, 'modules/sponsorships/advances.php'));

$check('initial_candidates_do_not_load_payment_history', !str_contains($candidateSource, 'ak_sar_load_report_data'));
$check('arrears_loader_supports_optional_filters', str_contains($library, 'function ak_sar_get_arrears(string $role, int $userId, ?array $filters = null)'));
$check('all_authorized_matches_are_not_silently_truncated', !str_contains($library, 'LIMIT 501') && !str_contains($library, 'count($sponsorships) > 100'));
$check('arrears_loader_accepts_explicit_all_scope', str_contains($library, 'ak_sar_load_report_data($role, $userId, $filters)'));
$check('complete_summary_is_calculated_before_pagination', str_contains($library, 'function ak_sar_summarize_arrears(array $items)'));
$check('pending_view_does_not_change_paid_calculation', str_contains($library, 'function ak_sar_filter_arrears_view(array $items, string $view)'));
$check('page_requires_explicit_all_action_for_unfiltered_report', str_contains($page, "\$allMode = \$searchRequested") && str_contains($page, 'search=1&amp;all=1'));
$check('page_calculates_summary_before_display_pagination', strpos($page, 'ak_sar_summarize_arrears($allRows)') < strpos($page, 'array_slice($displayRows'));
$check('page_uses_twenty_five_sponsorships_per_page', str_contains($page, '$pageSize = 25'));
$check('page_exposes_four_operational_stat_cards', str_contains($page, 'الكفالات المتأخرة') && str_contains($page, 'إجمالي المتأخرات') && str_contains($page, 'الأشهر غير المسددة') && str_contains($page, 'طلبات التحصيل المعلقة / المعادة'));
$check('page_preserves_pending_card_filter_and_pagination', str_contains($page, 'ak_sar_filter_arrears_view($allRows, $view)') && str_contains($page, 'pagination'));
$check('full_report_calculation_runs_only_after_search_submission', str_contains($page, 'if ($searchRequested && $loadError === null)') && str_contains($page, 'ak_sar_get_arrears($role, $reportUserId, $hasFilters ? $filters : null)'));
$check('summary_only_calculation_runs_independently_of_search', str_contains($page, 'ak_sar_get_arrears_summary($role, $reportUserId)'));
$check('page_discloses_suggestions_are_not_confirmed_arrears', str_contains($page, 'ظهور الكفالة هنا لا يعني أنها مصنفة كمتأخرة'));
$check('supervisor_scope_is_rechecked_by_authoritative_helper', str_contains($library, 'supervisorCanAccessSponsor($userId, $sponsor, $letterScope)'));
$summaryStart = strpos($library, 'function ak_sar_get_arrears_summary(');
$summaryEnd = strpos($library, 'function ak_sar_filter_arrears_view(', $summaryStart ?: 0);
$summarySource = ($summaryStart !== false && $summaryEnd !== false)
    ? substr($library, $summaryStart, $summaryEnd - $summaryStart)
    : '';
$statsPosition = strpos($page, 'الكفالات المتأخرة');
$searchPosition = strpos($page, '<div class="card fade-in mb-3">');
$check('role_wide_summary_has_a_dedicated_lightweight_loader', $summarySource !== '' && !str_contains($summarySource, 'ak_sar_load_report_data') && !str_contains($summarySource, 'family_children'));
$check('dashboard_summary_reuses_authoritative_month_eligibility', str_contains($summarySource, 'ak_sar_month_rows(') && str_contains($summarySource, 'ak_sar_has_two_consecutive_unpaid_months('));
$check('dashboard_summary_rechecks_supervisor_authorization', str_contains($summarySource, 'supervisorCanAccessSponsor($userId'));
$check('dashboard_cards_are_rendered_before_the_search_section', $statsPosition !== false && $searchPosition !== false && $statsPosition < $searchPosition);
$check('dashboard_cards_open_unfiltered_role_wide_report', str_contains($page, '$buildAllArrearsUrl') && str_contains($page, "'all'=>'1'"));
$check('role_wide_summary_runs_on_initial_get', str_contains($page, 'ak_sar_get_arrears_summary($role, $reportUserId)') && str_contains($page, 'if (!$canReuseAllReportSummary)'));
$check('search_results_do_not_replace_role_wide_dashboard_totals', str_contains($page, 'if (!$hasFilters) $summary = ak_sar_summarize_arrears($allRows)'));


echo 'PASS | ' . implode(' | ', array_map(
    static fn(string $name, bool $passed): string => $name . '=' . ($passed ? 'PASS' : 'FAIL'),
    array_keys($checks),
    array_values($checks)
)) . PHP_EOL;
