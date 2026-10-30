<?php
declare(strict_types=1);

/**
 * Source-contract regression test for editing company/organization sponsors.
 * Non-individual sponsor records use gender=organization and have no individual
 * supervisor-matrix assignment.
 */
$path = dirname(__DIR__) . '/modules/sponsors/edit.php';
$source = file_get_contents($path);
if ($source === false) {
    fwrite(STDERR, "FAIL | cannot read modules/sponsors/edit.php" . PHP_EOL);
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
        && str_contains($source, '($input[\'gender\']===\'organization\')'),
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
