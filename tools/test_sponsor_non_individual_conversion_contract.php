<?php
declare(strict_types=1);

/**
 * Source-contract regression test for company/organization sponsor applications
 * and their conversion into canonical sponsor records.
 */
$root = dirname(__DIR__);
$sources = [
    'public application' => file_get_contents($root . '/apply.php'),
    'request conversion' => file_get_contents($root . '/modules/sponsors/lib_sponsor_request_conversion.php'),
];

foreach ($sources as $label => $source) {
    if ($source === false) {
        fwrite(STDERR, "FAIL | cannot read {$label} source" . PHP_EOL);
        exit(1);
    }
}

[$application, $conversion] = array_values($sources);
$checks = [
    'public form accepts all canonical sponsor types' =>
        str_contains($application, "['individual','company','organization']"),
    'public form normalizes non-individual gender to organization' =>
        str_contains($application, <<<'SOURCE'
$gender = $type === 'individual' ? strtolower($form['gender']) : 'organization';
SOURCE
        ),
    'public form resolves a supervisor only for individual applicants' =>
        str_contains($application, <<<'SOURCE'
$supId = $type === 'individual' ? resolveSponsorSupervisorId($name, $gender) : null;
SOURCE
        ),
    'public request persists type, gender and supervisor scope' =>
        str_contains($application, 'sponsor_type, contact_person_name')
        && str_contains($application, 'gender, preferred_payment_method')
        && str_contains($application, 'assigned_supervisor_id'),
    'conversion preserves company and organization request types' =>
        str_contains($conversion, "['individual', 'company', 'organization']"),
    'conversion normalizes non-individual gender to organization' =>
        str_contains($conversion, <<<'SOURCE'
$gender = $type === 'individual'
            ? strtolower(trim((string)($req['gender'] ?? '')))
            : 'organization';
SOURCE
        ),
    'conversion resolves supervisor only for individuals' =>
        str_contains($conversion, <<<'SOURCE'
$supervisorId = $type === 'individual'
            ? resolveSponsorSupervisorId($name, $gender)
            : null;
SOURCE
        ),
    'conversion records assignment history only when a supervisor exists' =>
        str_contains($conversion, 'if ($supervisorId) {')
        && str_contains($conversion, 'ensureActiveSponsorAssignmentHistory($newId, $supervisorId, $actorId);'),
    'sponsor insert persists the canonical type, gender and supervisor fields' =>
        str_contains($conversion, 'status, sponsor_type, gender, contact_person_name')
        && str_contains($conversion, 'preferred_contact_time, preferred_payment_method')
        && str_contains($conversion, 'supervisor_id, assigned_by, assigned_at'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' | ' . $label . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}
if ($failed > 0) {
    fwrite(STDERR, "Non-individual sponsor conversion contract failed: {$failed} check(s)." . PHP_EOL);
    exit(1);
}
echo "PASS | non-individual sponsor application/conversion contract complete" . PHP_EOL;
