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
$check('arrears_loader_requires_explicit_filters', str_contains($library, 'function ak_sar_get_arrears(string $role, int $userId, array $filters)'));
$check('broad_search_is_bounded_before_allocations', strpos($library, 'LIMIT 501') < strpos($library, '$allocationRows = dbFetchAll('));
$check('broad_search_is_rejected_above_100_results', str_contains($library, "count(\$sponsorships) > 100"));
$check('page_runs_calculation_only_after_search_submission', str_contains($page, "if (\$searchRequested)") && str_contains($page, 'ak_sar_get_arrears($role, $reportUserId, $filters)'));
$check('page_discloses_suggestions_are_not_confirmed_arrears', str_contains($page, 'ظهور الكفالة هنا لا يعني أنها مصنفة كمتأخرة'));
$check('supervisor_scope_is_rechecked_by_authoritative_helper', str_contains($library, 'supervisorCanAccessSponsor($userId, $sponsor, $letterScope)'));

echo 'PASS | ' . implode(' | ', array_map(
    static fn(string $name, bool $passed): string => $name . '=' . ($passed ? 'PASS' : 'FAIL'),
    array_keys($checks),
    array_values($checks)
)) . PHP_EOL;
