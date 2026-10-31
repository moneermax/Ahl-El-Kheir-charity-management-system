<?php
declare(strict_types=1);

/**
 * Source-contract regression test for editing company/organization sponsors.
 * Non-individual sponsor records use gender=organization and have no individual
 * supervisor-matrix assignment.
 */
$path = dirname(__DIR__) . '/modules/sponsors/edit.php';
$source = file_get_contents($path);
$assignmentSource = file_get_contents(dirname(__DIR__) . '/config/sponsor_assignments.php');
if ($source === false || $assignmentSource === false) {
    fwrite(STDERR, "FAIL | cannot read sponsor edit or assignment source" . PHP_EOL);
    exit(1);
}

$checks = [
    'non-individual types normalize gender to organization' =>
        str_contains($source, '$input[\'sponsor_type\']===\'individual\'?')
        && str_contains($source, ":'organization';"),
    'gender validation applies only to individual sponsors' =>
        str_contains($source, '$input[\'sponsor_type\']===\'individual\'&&!in_array($input[\'gender\'],[\'male\',\'female\'],true)'),
    'supervisor resolution applies only to individual sponsors' =>
        str_contains($source, '$input[\'sponsor_type\']===\'individual\'?resolveSponsorSupervisorId'),
    'edit form offers the organization gender value' =>
        str_contains($source, '<option value="organization"')
        && str_contains($source, '$input[\'gender\']===\'organization\''),
    'edit authorization delegates to the canonical sponsor access helper' =>
        str_contains($source, 'supervisorCanAccessSponsor(Session::getUserId(), $sp)'),
    'legal-entity access is limited to its converting creator' =>
        str_contains($assignmentSource, <<<'SOURCE'
in_array($sponsorType, ['company', 'organization'], true)
SOURCE
        )
        && str_contains($assignmentSource, <<<'SOURCE'
(int)($sponsor['created_by'] ?? 0) === $supervisorId
SOURCE
        ),
    'legal-entity ownership does not become an individual supervisor assignment' =>
        str_contains($assignmentSource, <<<'SOURCE'
$sponsorType = strtolower(trim((string)($sponsor['sponsor_type'] ?? 'individual')));
SOURCE
        ),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' | ' . $label . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}
if ($failed > 0) {
    fwrite(STDERR, "Sponsor non-individual edit contract failed: {$failed} check(s)." . PHP_EOL);
    exit(1);
}
echo "PASS | sponsor non-individual edit contract complete" . PHP_EOL;
