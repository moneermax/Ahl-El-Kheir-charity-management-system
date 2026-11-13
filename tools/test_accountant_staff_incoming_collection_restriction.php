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
    'disbursement_approval' => $root . '/modules/accounting/lib_disbursement_approval.php',
    'void_guard' => $root . '/modules/accounting/disbursement_void_guard.php',
    'receipt_viewer' => $root . '/modules/accounting/serve_receipt.php',
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
$checks['acc1_can_transfer_assigned_nanny_disbursements_after_fm_approval'] = strpos($files['disbursements'], "\$canManageAssigned && isset(\$_POST['transfer_with_receipt'])") !== false && strpos($files['disbursements'], 'accountant_nanny_assignments WHERE accountant_id = ? AND nanny_id = ?') !== false && strpos($files['disbursements'], "\$canManageAssigned && \$viewBatch['status'] === 'approved'") !== false;
$checks['acc1_transfer_requires_approved_batch_and_receipt'] = strpos($files['disbursements'], "\$b['status'] !== 'approved'") !== false && strpos($files['disbursements'], "\$_FILES['transfer_receipt']['tmp_name']") !== false;
$checks['acc1_cannot_void_posted_disbursement'] = strpos($files['disbursements'], "in_array(\$role, ['admin', 'financial_manager'], true) && isset(\$_POST['void_batch'])") !== false;
$checks['batch_creation_starts_as_draft'] = strpos($files['disbursements'], "VALUES (?, ?, ?, ?, 'draft', '5110', ?, NULL, NULL)") !== false;
$checks['explicit_submit_moves_draft_to_fm_queue'] = strpos($files['disbursements'], "isset(\$_POST['submit_for_fm_approval'])") !== false && strpos($files['disbursements'], "SET status = 'pending_approval'") !== false && strpos($files['disbursements'], 'ak_transaction_review_notify_fm_event(') !== false;
$checks['acc1_edits_only_before_approval_with_reason_and_assignment'] = strpos($files['disbursements'], "\$isAccountantStaff && isset(\$_POST['edit_preapproval_items'])") !== false && strpos($files['disbursements'], "\$reason === ''") !== false && strpos($files['disbursements'], "['draft', 'pending_approval']") !== false && strpos($files['disbursements'], 'ak_disbursement_assert_assigned') !== false;
$checks['fm_approval_is_strictly_role_gated_and_workflow_only'] = strpos($files['disbursements'], "\$isFinancialManager && isset(\$_POST['approve_fm_disbursement'])") !== false && strpos($files['disbursements'], "SET status = 'approved'") !== false && strpos($files['disbursements'], "'financial_posting_created' => false") !== false && strpos($files['disbursement_approval'], "['financial_manager', 'fm', 'finance']") !== false;
$approvalStart = strpos($files['disbursements'], "if (\$isFinancialManager && isset(\$_POST['approve_fm_disbursement']))");
$rejectionStart = strpos($files['disbursements'], "if (\$isFinancialManager && isset(\$_POST['reject_fm_disbursement']))");
$approvalBlock = ($approvalStart !== false && $rejectionStart !== false && $rejectionStart > $approvalStart) ? substr($files['disbursements'], $approvalStart, $rejectionStart - $approvalStart) : '';
$checks['fm_approval_does_not_insert_transaction_or_journal'] = $approvalBlock !== '' && strpos($approvalBlock, 'INSERT INTO transactions') === false && strpos($approvalBlock, 'INSERT INTO journal_entries') === false && strpos($approvalBlock, 'INSERT INTO journal_lines') === false;
$checks['fm_rejection_requires_reason_and_returns_to_editable_draft'] = strpos($files['disbursements'], "\$isFinancialManager && isset(\$_POST['reject_fm_disbursement'])") !== false && strpos($files['disbursements'], "SET status = 'draft'") !== false && strpos($files['disbursements'], "\$reason === ''") !== false;
$checks['amount_corrections_are_bounded_by_active_verified_sponsorships'] = strpos($files['disbursement_approval'], "sp.status = 'active'") !== false && strpos($files['disbursement_approval'], 'v.is_orphan_verified = 1') !== false && strpos($files['disbursement_approval'], 'v.is_mother_contact_verified = 1') !== false && strpos($files['disbursement_approval'], 'v.is_bank_verified = 1') !== false;
$checks['approval_and_transfer_audit_failures_propagate'] = strpos($files['disbursement_approval'], 'INSERT INTO audit_log') !== false && strpos($files['disbursement_approval'], 'catch') === false && strpos($files['disbursements'], "'DISBURSEMENT_TRANSFER'") !== false;
$checks['draft_and_approved_batches_remain_admin_fm_voidable'] = strpos($files['void_guard'], "['draft', 'pending_approval', 'approved', 'transferred']") !== false && strpos($files['void_guard'], "status IN ('draft','pending_approval','approved','transferred')") !== false;
$checks['acc1_disbursement_receipts_are_assignment_scoped'] = strpos($files['receipt_viewer'], "['admin', 'financial_manager', 'general_manager', 'vice_general_manager', 'accountant']") !== false && strpos($files['receipt_viewer'], "\$role === 'accountant_staff' && \$row") !== false && strpos($files['receipt_viewer'], 'accountant_nanny_assignments WHERE accountant_id = ? AND nanny_id = ?') !== false;
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
