<?php
declare(strict_types=1);

/**
 * Source-contract checks for public sponsor application ownership and source labeling.
 */
$root = dirname(__DIR__);
$paths = [
    'application' => $root . '/apply.php',
    'management queue' => $root . '/modules/sponsors/requests.php',
    'conversion' => $root . '/modules/sponsors/lib_sponsor_request_conversion.php',
    'supervisor detail' => $root . '/modules/sponsors/my_request.php',
    'Arabic catalog' => $root . '/lang/sponsor_requests_ar.php',
    'English catalog' => $root . '/lang/sponsor_requests_en.php',
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
    'public application persists its distinct source identifier' =>
        str_contains($sources['application'], "'public_form', 'new'"),
    'matrix-assigned individuals notify only their assigned supervisor' =>
        str_contains($sources['application'], 'if ($supId) {')
        && str_contains($sources['application'], '$targets[] = $supId;')
        && str_contains($sources['application'], "'modules/sponsors/my_request.php?id=' . \$reqId"),
    'legal entities and unresolved individuals route notifications only to the VGM queue' =>
        str_contains($sources['application'], "r.code = 'vice_general_manager' AND u.is_active = 1")
        && !str_contains($sources['application'], "r.code IN ('admin','vice_general_manager')"),
    'online applications have a distinct source label and icon' =>
        str_contains($sources['management queue'], "'public_form' => ['icon'=>'fas fa-globe")
        && str_contains($sources['management queue'], "'key'=>'sponsors.request_source_online'"),
    'Arabic and English source labels are present' =>
        str_contains($sources['Arabic catalog'], "'sponsors.request_source_online'=>'تقديم عبر الموقع الإلكتروني'")
        && str_contains($sources['English catalog'], "'sponsors.request_source_online'=>'Website Application'"),
    'assigned online individuals are excluded from the active VGM queue while completed history remains visible' =>
        str_contains($sources['management queue'], "r.source = 'public_form' AND r.sponsor_type = 'individual' AND r.assigned_supervisor_id IS NOT NULL AND r.status IN ('new', 'contacted')")
        && str_contains($sources['management queue'], 'WHERE {$managementQueueVisibility}')
        && str_contains($sources['management queue'], "(r.source IS NULL OR r.source <> 'public_form')")
        && str_contains($sources['management queue'], "(r.source IS NULL OR NOT (r.source = 'public_form'"),
    'online requests are visible and actionable in management only for the VGM role' =>
        str_contains($sources['management queue'], "\$role === 'vice_general_manager'")
        && str_contains($sources['management queue'], "source'] ?? '') === 'public_form' && \$role !== 'vice_general_manager'"),
    'management status and reopen handlers reject supervisor-routed online individuals' =>
        str_contains($sources['management queue'], "\$request['source'] ?? '') === 'public_form'")
        && str_contains($sources['management queue'], 'لا يمكن إعادته من قائمة الإدارة'),
    'management conversion is blocked for supervisor-routed online individuals' =>
        str_contains($sources['conversion'], "\$req['source'] ?? '') === 'public_form'")
        && str_contains($sources['conversion'], 'يجب تحويله من صفحة طلبات المشرف'),
    'supervisor detail remains scoped to the assigned supervisor' =>
        str_contains($sources['supervisor detail'], 'WHERE id = ? AND assigned_supervisor_id = ?'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' | ' . $label . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}
if ($failed > 0) {
    fwrite(STDERR, "Public sponsor application routing contract failed: {$failed} check(s)." . PHP_EOL);
    exit(1);
}
echo 'PASS | public sponsor application routing contract complete' . PHP_EOL;
