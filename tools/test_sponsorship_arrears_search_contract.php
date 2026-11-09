<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$library = file_get_contents($root . '/modules/sponsorships/lib_payment_status.php');
$page = file_get_contents($root . '/modules/sponsorships/arrears.php');
if ($library === false || $page === false) {
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
$check('page_runs_calculation_only_after_search_submission', str_contains($page, "if (\$searchRequested)") && str_contains($page, 'ak_sar_get_arrears($role, $reportUserId, $hasFilters ? $filters : null)'));
$check('page_discloses_suggestions_are_not_confirmed_arrears', str_contains($page, 'ظهور الكفالة هنا لا يعني أنها مصنفة كمتأخرة'));
$check('supervisor_scope_is_rechecked_by_authoritative_helper', str_contains($library, 'supervisorCanAccessSponsor($userId, $sponsor, $letterScope)'));

echo 'PASS | ' . implode(' | ', array_map(
    static fn(string $name, bool $passed): string => $name . '=' . ($passed ? 'PASS' : 'FAIL'),
    array_keys($checks),
    array_values($checks)
)) . PHP_EOL;
