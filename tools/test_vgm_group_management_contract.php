<?php
declare(strict_types=1);

/**
 * Static contract checks for the VGM monthly group-opening and safe archive
 * boundary. This is not a substitute for local Apache/database acceptance.
 */
$root = dirname(__DIR__);
$files = [
    'helper' => $root . '/modules/deputy_gm/lib_group_management.php',
    'web' => $root . '/modules/deputy_gm/groups.php',
    'api' => $root . '/api/v1/deputy_gm/groups.php',
];
foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL | missing {$name} source\n");
        exit(1);
    }
}
$src = [];
foreach ($files as $name => $path) $src[$name] = (string)file_get_contents($path);
$checks = [
    'opening requires active group' => str_contains($src['helper'], "['status'] !== 'active'"),
    'opening requires current members' => str_contains($src['helper'], 'left_date IS NULL'),
    'opening requires active assigned nanny' => str_contains($src['helper'], "r.code = 'nanny'") && str_contains($src['helper'], 'u.is_active = 1'),
    'opening blocks an existing current-month batch' => str_contains($src['helper'], 'if ($currentBatch)'),
    'later opening requires closed previous cycle' => str_contains($src['helper'], '$oldStatus !== \'closed\''),
    'older batches must be terminal' => str_contains($src['helper'], "['received', 'returned', 'cancelled', 'voided']"),
    'latest prior batch must have completed Nanny closure' => str_contains($src['helper'], "['received', 'returned']"),
    'opening writes required audit evidence' => str_contains($src['helper'], 'VGM_OPEN_MONTHLY_CYCLE') && str_contains($src['helper'], 'INSERT INTO audit_log'),
    'notification happens after commit' => strpos(substr($src['helper'], strpos($src['helper'], 'function ak_vgm_open_monthly_cycles')), '$pdo->commit();') < strpos(substr($src['helper'], strpos($src['helper'], 'function ak_vgm_open_monthly_cycles')), 'ak_transaction_review_notify_event('),
    'web supports individual/bulk selected groups' => str_contains($src['web'], 'name="group_ids[]"') && str_contains($src['web'], "ak_vgm_open_monthly_cycles"),
    'mobile API supports monthly opening' => str_contains($src['api'], "'open_monthly_cycle'") && str_contains($src['api'], 'ak_vgm_open_monthly_cycles'),
    'mobile API rejects an opening with no eligible groups' => str_contains($src['api'], 'no_groups_opened'),
    'used groups are archived, not physically deleted' => str_contains($src['helper'], "SET status = 'archived', is_active = 0") && str_contains($src['helper'], 'UPDATE nanny_group_assignments SET end_date'),
    'archive blocks unfinished disbursements' => str_contains($src['helper'], "status NOT IN ('received', 'returned', 'cancelled', 'voided')"),
    'archive blocks an opened but unfinished verification cycle' => str_contains($src['helper'], 'ak_vgm_assert_group_archivable') && str_contains($src['helper'], 'VGM_OPEN_MONTHLY_CYCLE'),
    'web lifecycle archive runs inside a transaction' => str_contains($src['web'], 'in_array($action, [\'activate_group\'') && str_contains($src['web'], '$pdo->beginTransaction();') && str_contains($src['web'], '$pdo->commit();'),
    'web transfer capability remains' => str_contains($src['web'], 'elseif ($action === \'transfer_orphan\')'),
    'mobile transfer capability remains' => str_contains($src['api'], 'elseif ($action === \'transfer_orphan\')'),
];
$failed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ' | ' . $label . PHP_EOL;
    if (!$ok) $failed++;
}
echo 'RESULT | ' . count($checks) . ' checks | ' . ($failed === 0 ? 'PASS' : 'FAIL') . PHP_EOL;
exit($failed === 0 ? 0 : 1);
