<?php
declare(strict_types=1);

/**
 * Static authorization contract: VGM is report-only for collection records.
 * This deliberately does not test or change the separate accountant-to-Nanny
 * disbursement workflow.
 */
$root = dirname(__DIR__);
$files = [
    'create' => $root . '/modules/transactions/create.php',
    'edit' => $root . '/modules/transactions/edit_returned.php',
    'index' => $root . '/modules/transactions/index.php',
    'cancel' => $root . '/modules/transactions/cancel_returned.php',
    'sponsor' => $root . '/modules/sponsors/view.php',
    'sponsorship' => $root . '/modules/sponsorships/view.php',
];
foreach ($files as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL | missing_source={$key}\n");
        exit(1);
    }
    $files[$key] = file_get_contents($path);
}
$checks = [];
$checks['direct_collection_create_denied'] =
    strpos($files['create'], 'if (!in_array($role, [\'admin\', \'accountant_staff\', \'financial_manager\', \'supervisor\'], true))') !== false
    && strpos($files['create'], "'vice_general_manager'") === false;
$checks['returned_editor_denied'] =
    strpos($files['edit'], 'if (!in_array($role, [\'admin\', \'accountant_staff\'], true))') !== false
    && strpos($files['edit'], "'vice_general_manager'") === false;
$checks['returned_cancel_post_denied'] =
    strpos($files['cancel'], "['vice_general_manager', 'vgm']") !== false
    && strpos($files['cancel'], "if (\$role === 'accountant_staff')") !== false
    && strpos($files['cancel'], "http_response_code(403)") !== false;
$checks['transaction_register_remains_read_only_report'] =
    strpos($files['index'], "['admin','vice_general_manager','general_manager','supervisor','accountant_staff','financial_manager']") !== false
    && strpos($files['index'], '$canEditReturned = in_array($role, [\'admin\',\'accountant_staff\'], true);') !== false
    && strpos($files['index'], "['financial_manager','admin','supervisor']") !== false;
$checks['sponsor_payment_entry_link_hidden_for_vgm'] =
    strpos($files['sponsor'], 'if($role!==\'vice_general_manager\')') !== false
    && strpos($files['sponsor'], "modules/transactions/create.php?sponsor_id=") !== false;
$checks['sponsorship_payment_entry_link_hidden_for_vgm'] =
    strpos($files['sponsorship'], '$canManage&&$role!==\'vice_general_manager\'&&in_array') !== false
    && strpos($files['sponsorship'], "modules/transactions/create.php?sponsorship_id=") !== false;

$passed = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . " | {$name}\n";
    if ($ok) $passed++;
}
echo "RESULT | {$passed}/" . count($checks) . " checks | " . ($passed === count($checks) ? 'PASS' : 'FAIL') . "\n";
exit($passed === count($checks) ? 0 : 1);
