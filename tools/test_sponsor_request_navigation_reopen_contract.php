<?php
declare(strict_types=1);

/**
 * Database-free source contract for VGM sponsor-request navigation and reopen UI authorization.
 */
$root = dirname(__DIR__);
$paths = [
    'sidebar' => $root . '/includes/sidebar.php',
    'VGM dashboard' => $root . '/dashboard/vgm_dashboard.php',
    'request queue' => $root . '/modules/sponsors/requests.php',
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
    'VGM sidebar exposes sponsor requests using the existing role-safe queue' =>
        str_contains($sources['sidebar'], "'vice_general_manager' => [")
        && str_contains($sources['sidebar'], "'active' => 'requests', 'label_key' => 'navigation.join_requests'")
        && str_contains($sources['sidebar'], "'url' => 'modules/sponsors/requests.php'")
        && str_contains($sources['request queue'], "$"."active = 'requests';"),
    'VGM dashboard has a direct sponsor requests action' =>
        str_contains($sources['VGM dashboard'], "'href'=>'modules/sponsors/requests.php'")
        && str_contains($sources['VGM dashboard'], "'key'=>'navigation.join_requests'")
        && str_contains($sources['VGM dashboard'], "'desc'=>'sponsors.requests_intro'"),
    'reopen button visibility excludes assigned online individual requests' =>
        str_contains($sources['request queue'], '$canReopenLead = (!$isOnlineApplication && !$isLegalEntity)')
        && str_contains($sources['request queue'], '!($isOnlineApplication && !$isLegalEntity && !empty($lead[\'assigned_supervisor_id\']))')
        && str_contains($sources['request queue'], 'if ($canReopenLead):'),
    'server-side reopen authorization still rejects assigned online individual requests' =>
        str_contains($sources['request queue'], "source'] ?? '') === 'public_form'")
        && str_contains($sources['request queue'], 'لا يمكن إعادته من قائمة الإدارة'),
    'reopen action retains the established lost-to-contacted transition' =>
        str_contains($sources['request queue'], "UPDATE sponsor_requests SET status = 'contacted' WHERE id = ? AND status = 'lost'"),
];

$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' | ' . $name . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}
if ($failed > 0) {
    fwrite(STDERR, "Sponsor request navigation/reopen contract failed: {$failed} check(s)." . PHP_EOL);
    exit(1);
}
echo "PASS | sponsor request navigation/reopen contract complete" . PHP_EOL;
