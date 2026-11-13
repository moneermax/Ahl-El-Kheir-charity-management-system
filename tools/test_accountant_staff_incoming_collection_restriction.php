<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/modules/accounting/lib_vouchers.php';
$files = [
    'create' => $root . '/modules/transactions/create.php',
    'edit' => $root . '/modules/transactions/edit_returned.php',
    'index' => $root . '/modules/transactions/index.php',
    'cancel' => $root . '/modules/transactions/cancel_returned.php',
    'disbursements' => $root . '/modules/accounting/disbursements.php',
    'voucher_permissions' => $root . '/modules/accounting/lib_vouchers.php',
    'voucher_web' => $root . '/modules/accounting/vouchers.php',
    'voucher_api' => $root . '/api/v1/accounting/vouchers.php',
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
$checks['acc1_can_transfer_assigned_nanny_disbursements'] = strpos($files['disbursements'], "\$canManageAssigned && isset(\$_POST['transfer_with_receipt'])") !== false && strpos($files['disbursements'], 'accountant_nanny_assignments WHERE accountant_id = ? AND nanny_id = ?') !== false && strpos($files['disbursements'], "\$canManageAssigned && \$viewBatch['status'] === 'pending_approval'") !== false;
$checks['acc1_transfer_still_requires_pending_batch_and_receipt'] = strpos($files['disbursements'], "\$b['status'] !== 'pending_approval'") !== false && strpos($files['disbursements'], "\$_FILES['transfer_receipt']['tmp_name']") !== false;
$checks['acc1_cannot_void_posted_disbursement'] = strpos($files['disbursements'], "in_array(\$role, ['admin', 'financial_manager'], true) && isset(\$_POST['void_batch'])") !== false;
$checks['accountant_staff_can_issue_payment_vouchers'] = strpos($files['voucher_permissions'], "define('AK_VOUCHER_PAYMENT_ROLES', ['admin', 'financial_manager', 'accountant_staff'])") !== false;
$checks['accountant_staff_cannot_issue_receipt_vouchers'] = strpos($files['voucher_permissions'], "define('AK_VOUCHER_RECEIPT_ROLES', ['admin', 'financial_manager'])") !== false;
$checks['web_receipt_voucher_post_is_server_denied'] = strpos($files['voucher_web'], 'ak_voucher_can(\'issue_receipt\', $voucherRole)') !== false;
$checks['web_receipt_option_is_permission_gated'] = strpos($files['voucher_web'], 'if ($canIssueReceipt):') !== false;
$checks['mobile_receipt_voucher_post_is_server_denied'] = strpos($files['voucher_api'], 'ak_voucher_can(\'issue_receipt\', $role)') !== false;
$checks['mobile_voucher_capabilities_are_direction_specific'] = strpos($files['voucher_api'], '\'can_issue_receipt\' => ak_voucher_can(\'issue_receipt\', $role)') !== false && strpos($files['voucher_api'], '\'can_issue_payment\' => ak_voucher_can(\'issue_payment\', $role)') !== false;
$checks['runtime_acc1_payment_only_capability'] = ak_voucher_can('issue', 'accountant_staff') && ak_voucher_can('issue_payment', 'accountant_staff') && !ak_voucher_can('issue_receipt', 'accountant_staff');
$checks['runtime_admin_and_fm_keep_both_voucher_directions'] = ak_voucher_can('issue_receipt', 'admin') && ak_voucher_can('issue_payment', 'admin') && ak_voucher_can('issue_receipt', 'financial_manager') && ak_voucher_can('issue_payment', 'financial_manager');
$checks['runtime_gm_and_vgm_cannot_issue_vouchers'] = !ak_voucher_can('issue', 'general_manager') && !ak_voucher_can('issue', 'vice_general_manager');
$checks['web_voucher_audit_failures_propagate_to_transaction'] = strpos($files['voucher_web'], 'catch (Throwable $auditError) {}') === false && substr_count($files['voucher_web'], 'INSERT INTO audit_log') === 2;
$checks['mobile_voucher_audit_failures_propagate_to_transaction'] = strpos($files['voucher_api'], 'catch(Throwable $auditError) {}') === false && substr_count($files['voucher_api'], 'INSERT INTO audit_log') === 2;
$passed = 0;
foreach ($checks as $name => $ok) { echo ($ok ? 'PASS' : 'FAIL') . " | {$name}\n"; if ($ok) $passed++; }
echo "RESULT | {$passed}/" . count($checks) . " checks | " . ($passed === count($checks) ? 'PASS' : 'FAIL') . "\n";
exit($passed === count($checks) ? 0 : 1);
