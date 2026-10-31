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
    'legal-entity request assignment is an explicit VGM-only operation' =>
        str_contains($helper, 'function assignLegalEntitySponsorRequestToSupervisor')
        && str_contains($helper, "Session::getUserRole() !== 'vice_general_manager'"),
    'manual request assignment is restricted to company and organization types' =>
        str_contains($helper, "['company', 'organization']"),
    'manual request assignment only accepts actionable states' =>
        str_contains($helper, "['new', 'contacted']")
        && str_contains($helper, 'assigned_supervisor_id'),
    'manual assignment locks the request and validates an active supervisor account' =>
        str_contains($helper, 'FROM sponsor_requests WHERE id = ? FOR UPDATE')
        && str_contains($helper, "r.code = 'supervisor' AND u.is_active = 1")
        && str_contains($helper, "supervisor_status'] !== 'active'"),
    'manual assignment writes a distinct audit event' =>
        str_contains($helper, "'ASSIGN_SUPERVISOR', 'sponsor_request'"),
    'assigned supervisor receives an in-app request link after commit' =>
        str_contains($helper, 'modules/sponsors/my_request.php?id='),
    'only VGM sees the manual assignment control in the management queue' =>
        str_contains($queue, '$role === \'vice_general_manager\'')
        && str_contains($queue, 'assignLegalEntitySponsorRequestToSupervisor'),
    'management queue keeps the existing direct-processing conversion option' =>
        str_contains($queue, "convertSponsorRequestToSponsor(\$id, (int)Session::getUserId(), 'management_queue')"),
    'management conversion of legal entities is VGM-only and only while unassigned' =>
        str_contains($helper, "Session::getUserRole() !== 'vice_general_manager'")
        && str_contains($helper, 'if (!empty($req[\'assigned_supervisor_id\']))')
        && str_contains($helper, "requestType, ['company', 'organization']"),
    'legal-entity queue status transitions stop once assigned' =>
        str_contains($queue, "assigned_supervisor_id IS NULL")
        && str_contains($queue, "المشرف المسند إليه"),
    'authorized management conversion is a real native POST form, not a missing modal trigger' =>
        str_contains($queue, 'data-ak-native-post="1"')
        && str_contains($queue, 'name="action" value="convert"')
        && !str_contains($queue, '#convertLeadModal'),



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
