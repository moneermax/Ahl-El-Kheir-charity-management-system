<?php
declare(strict_types=1);

/**
 * Database-free source-contract regression test for sponsor request conversion
 * authorization scopes. It verifies that management conversion is explicitly
 * authorized and does not weaken the supervisor's row-level ownership check.
 */
$root = dirname(__DIR__);
$helper = file_get_contents($root . '/modules/sponsors/lib_sponsor_request_conversion.php');
$queue = file_get_contents($root . '/modules/sponsors/requests.php');
$supervisor = file_get_contents($root . '/modules/sponsors/my_request.php');

if ($helper === false || $queue === false || $supervisor === false) {
    fwrite(STDERR, "FAIL | cannot read sponsor conversion source files\n");
    exit(1);
}

$checks = [
    'helper defaults to supervisor scope' =>
        str_contains($helper, "string \$authorizationScope = 'supervisor'"),
    'helper binds actor ID to the active session' =>
        str_contains($helper, '(int)Session::getUserId() !== $actorId'),
    'supervisor scope requires supervisor role' =>
        str_contains($helper, "Session::getUserRole() !== 'supervisor'"),
    'supervisor row lock retains assigned-supervisor ownership predicate' =>
        str_contains($helper, "SELECT * FROM sponsor_requests WHERE id = ? AND assigned_supervisor_id = ? FOR UPDATE"),
    'management scope has an explicit allowlist' =>
        str_contains($helper, "['admin', 'vice_general_manager', 'general_manager', 'administration', 'staff', 'social_media']"),
    'management queue explicitly requests its authorized scope' =>
        str_contains($queue, "convertSponsorRequestToSponsor(\$id, (int)Session::getUserId(), 'management_queue')"),
    'supervisor page uses the default restricted scope' =>
        str_contains($supervisor, 'convertSponsorRequestToSponsor($requestId, (int)Session::getUserId())'),
    'conversion still locks the request before changing it' =>
        str_contains($helper, 'FOR UPDATE'),
    'conversion still conditionally updates actionable states' =>
        str_contains($helper, "WHERE id = ? AND status IN ('new', 'contacted')"),
    'conversion still writes an audit record' =>
        str_contains($helper, "'CONVERT', 'sponsor_request'"),
];

$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' | ' . $name . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}
if ($failed > 0) {
    fwrite(STDERR, "Sponsor conversion authorization contract failed: {$failed} check(s).\n");
    exit(1);
}
echo "PASS | sponsor conversion authorization contract complete\n";
