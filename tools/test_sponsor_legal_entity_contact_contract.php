<?php
declare(strict_types=1);

/**
 * Source-contract checks for legal-entity application contact details.
 */
$root = dirname(__DIR__);
$paths = [
    'application' => $root . '/apply.php',
    'conversion' => $root . '/modules/sponsors/lib_sponsor_request_conversion.php',
    'request detail' => $root . '/modules/sponsors/my_request.php',
    'management queue' => $root . '/modules/sponsors/requests.php',
    'sponsor edit' => $root . '/modules/sponsors/edit.php',
    'sponsor view' => $root . '/modules/sponsors/view.php',
    'migration' => $root . '/database/migrations/2026-10-31_sponsor_legal_entity_contact_details.sql',
];
$sources = [];
foreach ($paths as $label => $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, "FAIL | cannot read {$label} source" . PHP_EOL);
        exit(1);
    }
    $sources[$label] = $source;
}
$checks = [
    'legal-entity contact section is conditionally rendered' =>
        str_contains($sources['application'], 'id="entityContactFields"')
        && str_contains($sources['application'], 'contactName.required = entity'),
    'representative name and position are captured' =>
        str_contains($sources['application'], 'name="contact_person_name"')
        && str_contains($sources['application'], 'name="contact_person_position"'),
    'contact method and time are captured' =>
        str_contains($sources['application'], 'name="preferred_contact_method"')
        && str_contains($sources['application'], 'name="preferred_contact_time"'),
    'legal entity requires a representative and valid contact preference' =>
        str_contains($sources['application'], '$contactPersonName === \'\'')
        && str_contains($sources['application'], '$contactMethod === null'),
    'entity proposal instructions distinguish budget and final commitment' =>
        str_contains($sources['application'], 'overall proposed monthly budget')
        && str_contains($sources['application'], 'الميزانية الشهرية الإجمالية المقترحة'),
    'request INSERT persists all representative details' =>
        str_contains($sources['application'], 'contact_person_name, contact_person_position, preferred_contact_method, preferred_contact_time'),
    'conversion copies request contact details to sponsor' =>
        str_contains($sources['conversion'], '$req[\'contact_person_name\']')
        && str_contains($sources['conversion'], '$req[\'contact_person_position\']')
        && str_contains($sources['conversion'], '$req[\'preferred_contact_method\']')
        && str_contains($sources['conversion'], '$req[\'preferred_contact_time\']'),
    'supervisor request detail displays entity representative details' =>
        str_contains($sources['request detail'], 'contact_person_name')
        && str_contains($sources['request detail'], 'الوقت المناسب للتواصل'),
    'management queue displays legal-entity representative and all contact preferences' =>
        str_contains($sources['management queue'], "\$lead['contact_person_name']")
        && str_contains($sources['management queue'], "\$lead['preferred_contact_method']")
        && str_contains($sources['management queue'], "\$lead['preferred_contact_time']")
        && str_contains($sources['management queue'], '$contactMethodLabels')
        && str_contains($sources['management queue'], 'طريقة التواصل:'),
    'sponsor edit persists contact details' =>
        str_contains($sources['sponsor edit'], 'contact_person_name=?')
        && str_contains($sources['sponsor edit'], 'preferred_contact_time=?'),
    'sponsor profile displays saved contact details' =>
        str_contains($sources['sponsor view'], '$entityApplication[\'contact_person_name\']')
        && str_contains($sources['sponsor view'], '$entityApplication[\'preferred_contact_time\']'),
    'migration adds fields to request and sponsor tables idempotently' =>
        substr_count($sources['migration'], 'ADD COLUMN IF NOT EXISTS') === 8
        && str_contains($sources['migration'], 'ALTER TABLE sponsor_requests')
        && str_contains($sources['migration'], 'ALTER TABLE sponsors'),
];
$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' | ' . $label . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}
if ($failed > 0) {
    fwrite(STDERR, "Legal-entity contact contract failed: {$failed} check(s)." . PHP_EOL);
    exit(1);
}
echo 'PASS | legal-entity contact contract complete' . PHP_EOL;
