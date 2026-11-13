<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$files = [
    'create' => $root . '/modules/transactions/create.php',
    'edit' => $root . '/modules/transactions/edit_returned.php',
    'index' => $root . '/modules/transactions/index.php',
    'cancel' => $root . '/modules/transactions/cancel_returned.php',
    'disbursements' => $root . '/modules/accounting/disbursements.php',
];
foreach ($files as $key => $path) {
    if (!is_file($path)) { fwrite(STDERR, "FAIL | missing_source={$key}\n"); exit(1); }
    $files[$key] = file_get_contents($path);
}
$checks = [];
$checks['direct_incoming_entry_denied'] = strpos($files['create'], 'if (!in_array($role, [\'admin\', \'financial_manager\', \'supervisor\'], true))') !== false && strpos($files['create'], "'accountant_staff'") === false;
$checks['returned_edit_and_resubmission_denied'] = strpos($files['edit'], 'if (!in_array($role, [\'admin\'], true))') !== false && strpos($files['edit'], "'accountant_staff'") === false;
$checks['transaction_register_read_only'] = strpos($files['index'], "['admin','vice_general_manager','general_manager','supervisor','accountant_staff','financial_manager']") !== false && strpos($files['index'], "\$canEditReturned = in_array(\$role, ['admin'], true);") !== false && strpos($files['index'], "if(in_array(\$role,['financial_manager','admin','supervisor'],true))") !== false;
$checks['creator_cancellation_post_denied'] = strpos($files['cancel'], "if (\$role === 'accountant_staff')") !== false && strpos($files['cancel'], "http_response_code(403)") !== false;
$checks['outgoing_disbursement_access_preserved'] = strpos($files['disbursements'], "['admin', 'accountant_staff', 'financial_manager', 'general_manager', 'vice_general_manager', 'nanny']") !== false && strpos($files['disbursements'], "\$isAccountantStaff = (\$role === 'accountant_staff');") !== false;
$passed = 0;
foreach ($checks as $name => $ok) { echo ($ok ? 'PASS' : 'FAIL') . " | {$name}\n"; if ($ok) $passed++; }
echo "RESULT | {$passed}/" . count($checks) . " checks | " . ($passed === count($checks) ? 'PASS' : 'FAIL') . "\n";
exit($passed === count($checks) ? 0 : 1);
