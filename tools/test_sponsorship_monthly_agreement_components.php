<?php
declare(strict_types=1);

/**
 * Focused unit checks for the monthly sponsorship agreement snapshot contract.
 * No database connection, schema changes, or financial writes are performed.
 */
$GLOBALS['ak_spma_test_mapping'] = null;
$GLOBALS['ak_spma_test_agreement'] = [
    'id'=>42,
    'sponsorship_id'=>3559,
    'covered_month'=>'2026-11-01',
    'version_no'=>2,
    'agreed_sponsorship_amount'=>'10000.00',
    'agreed_service_fee_amount'=>'1500.00',
    'currency_code'=>'SDG'
];
function dbFetchOne(string $sql, array $params = []): ?array
{
    if (strpos($sql, 'sponsorship_service_fee_account_mappings') !== false) {
        $mapping = $GLOBALS['ak_spma_test_mapping'];
        if ($mapping === null || (string)$mapping['effective_from'] > (string)($params[0] ?? '')) return null;
        return $mapping;
    }
    if (strpos($sql, 'sponsorship_monthly_agreements') === false) {
        throw new RuntimeException('Unexpected database query in isolated test.');
    }
    $row = $GLOBALS['ak_spma_test_agreement'];
    if ($row === null) return null;
    if (count($params) === 1) {
        return (int)$row['id'] === (int)($params[0] ?? 0) ? $row : null;
    }
    if ((int)$row['sponsorship_id'] !== (int)($params[0] ?? 0)
        || (string)$row['covered_month'] !== (string)($params[1] ?? '')) return null;
    return $row;
}
require_once dirname(__DIR__) . '/modules/sponsorships/lib_monthly_agreements.php';

$checks = [];
$assert = static function (bool $condition, string $name) use (&$checks): void {
    $checks[$name] = $condition;
};
$snapshot = ak_spma_build_submission_snapshot(3559, 'November/2026', '9000', '1200');
$assert($snapshot['component_model'] === 'monthly_agreement', 'component_model');
$assert($snapshot['monthly_agreement_id'] === 42, 'agreement_reference');
$assert($snapshot['agreed_sponsorship_amount'] === 10000.00, 'agreed_sponsorship_snapshot');
$assert($snapshot['agreed_service_fee_amount'] === 1500.00, 'agreed_service_fee_snapshot');
$assert($snapshot['actual_sponsorship_received'] === 9000.00, 'actual_sponsorship');
$assert($snapshot['actual_service_fee_received'] === 1200.00, 'actual_service_fee');
$assert($snapshot['amount'] === 10200.00, 'total_is_component_sum');
$assert($snapshot['currency_code'] === 'SDG', 'agreement_currency');

$zeroFee = $snapshot;
$zeroFee['sponsorship_id'] = 3559;
$zeroFee['payment_period'] = 'November/2026';
$zeroFee['currency_code'] = 'SDG';
$zeroFee['actual_service_fee_received'] = 0.00;
$zeroFee['amount'] = 9000.00;
$posting = ak_spma_validate_snapshot_for_posting($zeroFee);
$assert($posting['sponsorship_received'] === 9000.00, 'zero_fee_sponsorship_posting_allowed');
$zeroFeeMapping = ak_spma_resolve_service_fee_account_mapping(
    ['transaction_date'=>'2026-11-12'],
    0.00
);
$assert($zeroFeeMapping['mapping_id'] === null && $zeroFeeMapping['account_id'] === null, 'zero_fee_needs_no_revenue_mapping');

$positiveFee = $zeroFee;
$positiveFee['actual_service_fee_received'] = 1200.00;
$positiveFee['amount'] = 10200.00;
$positiveFee['transaction_date'] = '2026-11-12';
$positiveComponents = ak_spma_validate_snapshot_for_posting($positiveFee);
$assert($positiveComponents['service_fee_received'] === 1200.00, 'positive_service_fee_snapshot_validated');
$positivePostingBlocked = false;
try { ak_spma_assert_service_fee_posting_allowed($positiveComponents); }
catch (RuntimeException $e) { $positivePostingBlocked = str_contains($e->getMessage(), 'توقيت الاعتراف المحاسبي'); }
$assert($positivePostingBlocked, 'positive_service_fee_posting_waits_for_recognition_policy');
$zeroFeeAllowed = true;
try { ak_spma_assert_service_fee_posting_allowed($posting); }
catch (RuntimeException $e) { $zeroFeeAllowed = false; }
$assert($zeroFeeAllowed, 'zero_service_fee_posting_does_not_need_recognition_policy');

$mappingMissingBlocked = false;
$GLOBALS['ak_spma_test_mapping'] = null;
try { ak_spma_resolve_service_fee_account_mapping($positiveFee, 1200.00); }
catch (RuntimeException $e) { $mappingMissingBlocked = str_contains($e->getMessage(), 'لا يوجد حساب إيراد معتمد'); }
$assert($mappingMissingBlocked, 'positive_service_fee_requires_effective_mapping');

$GLOBALS['ak_spma_test_mapping'] = [
    'mapping_id'=>77,'account_id'=>420,'effective_from'=>'2026-11-01','version_no'=>1,
    'account_code'=>'4190','account_type'=>'revenue','is_active'=>1
];
$resolvedMapping = ak_spma_resolve_service_fee_account_mapping($positiveFee, 1200.00);
$assert($resolvedMapping['mapping_id'] === 77 && $resolvedMapping['account_id'] === 420, 'effective_mapping_resolved');
$stillBlockedAfterMapping = false;
try { ak_spma_assert_service_fee_posting_allowed($positiveComponents); }
catch (RuntimeException $e) { $stillBlockedAfterMapping = true; }
$assert($stillBlockedAfterMapping, 'account_mapping_alone_does_not_enable_posting');
$assert($resolvedMapping['account_code'] === '4190', 'configured_revenue_account_used');

$futureMappingBlocked = false;
$GLOBALS['ak_spma_test_mapping']['effective_from'] = '2026-12-01';
try { ak_spma_resolve_service_fee_account_mapping($positiveFee, 1200.00); }
catch (RuntimeException $e) { $futureMappingBlocked = true; }
$assert($futureMappingBlocked, 'future_mapping_not_used_early');

$GLOBALS['ak_spma_test_mapping'] = [
    'mapping_id'=>78,'account_id'=>421,'effective_from'=>'2026-11-01','version_no'=>2,
    'account_code'=>'5190','account_type'=>'expense','is_active'=>1
];
$wrongTypeBlocked = false;
try { ak_spma_resolve_service_fee_account_mapping($positiveFee, 1200.00); }
catch (RuntimeException $e) { $wrongTypeBlocked = str_contains($e->getMessage(), 'ليس حساب إيراد'); }
$assert($wrongTypeBlocked, 'expense_account_rejected');

$GLOBALS['ak_spma_test_mapping'] = [
    'mapping_id'=>79,'account_id'=>422,'effective_from'=>'2026-11-01','version_no'=>3,
    'account_code'=>'4191','account_type'=>'revenue','is_active'=>0
];
$inactiveBlocked = false;
try { ak_spma_resolve_service_fee_account_mapping($positiveFee, 1200.00); }
catch (RuntimeException $e) { $inactiveBlocked = str_contains($e->getMessage(), 'غير نشط'); }
$assert($inactiveBlocked, 'inactive_revenue_account_rejected');

$mismatchBlocked = false;
$mismatch = $zeroFee;
$mismatch['amount'] = 9001.00;
try { ak_spma_validate_snapshot_for_posting($mismatch); }
catch (RuntimeException $e) { $mismatchBlocked = true; }
$assert($mismatchBlocked, 'component_total_mismatch_blocked');

$missingAgreementBlocked = false;
$GLOBALS['ak_spma_test_agreement'] = null;
try { ak_spma_build_submission_snapshot(3559, 'November/2026', '5000', '0'); }
catch (RuntimeException $e) { $missingAgreementBlocked = str_contains($e->getMessage(), 'لا توجد اتفاقية'); }
$assert($missingAgreementBlocked, 'missing_agreement_blocked');

$negativeBlocked = false;
$GLOBALS['ak_spma_test_agreement'] = [
    'id'=>42,'sponsorship_id'=>3559,'covered_month'=>'2026-11-01','version_no'=>2,
    'agreed_sponsorship_amount'=>'10000.00','agreed_service_fee_amount'=>'1500.00','currency_code'=>'SDG'
];
try { ak_spma_build_submission_snapshot(3559, 'November/2026', '-1', '0'); }
catch (RuntimeException $e) { $negativeBlocked = true; }
$assert($negativeBlocked, 'negative_actual_blocked');

$agreementTransaction = [
    'component_model'=>'monthly_agreement',
    'monthly_agreement_id'=>42,
    'agreed_sponsorship_amount'=>10000.00
];
$assert(
    ak_spma_resolve_allocation_commitment($agreementTransaction, [], ['monthly_amount'=>5000.00]) === 10000.00,
    'new_model_allocation_uses_agreement'
);
$assert(
    ak_spma_resolve_allocation_commitment($agreementTransaction, [['commitment_amount'=>10000.00]], ['monthly_amount'=>5000.00]) === 10000.00,
    'existing_allocation_snapshot_remains_authoritative'
);
$allocationMismatchBlocked = false;
try { ak_spma_resolve_allocation_commitment($agreementTransaction, [['commitment_amount'=>5000.00]], ['monthly_amount'=>5000.00]); }
catch (RuntimeException $e) { $allocationMismatchBlocked = true; }
$assert($allocationMismatchBlocked, 'conflicting_agreement_allocation_blocked');

$legacyTransaction = ['component_model'=>'legacy'];
$assert(
    ak_spma_resolve_allocation_commitment($legacyTransaction, [], ['monthly_amount'=>5000.00]) === 5000.00,
    'legacy_allocation_fallback_unchanged'
);
$assert(
    ak_spma_resolve_allocation_commitment($legacyTransaction, [['commitment_amount'=>4500.00]], ['monthly_amount'=>5000.00]) === 4500.00,
    'legacy_existing_snapshot_unchanged'
);

$agreementRow = ['agreed_sponsorship_amount'=>10000.00];
$assert(
    ak_spma_resolve_report_commitment(null, $agreementRow, 5000.00) === 10000.00,
    'report_uses_agreement_without_allocation'
);
$assert(
    ak_spma_resolve_report_commitment(['commitment_versions'=>1,'commitment_amount'=>5000.00], $agreementRow, 7000.00) === 5000.00,
    'report_prefers_existing_allocation_snapshot'
);
$assert(
    ak_spma_resolve_report_commitment(null, null, 5000.00) === 5000.00,
    'report_legacy_fallback_unchanged'
);
$reportConflictBlocked = false;
try { ak_spma_resolve_report_commitment(['commitment_versions'=>2,'commitment_amount'=>5000.00], $agreementRow, 5000.00); }
catch (RuntimeException $e) { $reportConflictBlocked = true; }
$assert($reportConflictBlocked, 'report_conflicting_snapshots_blocked');

$paymentEntrySource = file_get_contents(dirname(__DIR__) . '/modules/transactions/create.php');
$assert(is_string($paymentEntrySource), 'payment_entry_source_readable');
if (is_string($paymentEntrySource)) {
    $assert(
        str_contains($paymentEntrySource, '$actualS=(string)($ln[\'sponsorship_received\']??\'\');')
        && str_contains($paymentEntrySource, '$actualF=(string)($ln[\'service_fee_received\']??\'\');'),
        'initial_actual_receipts_never_fall_back_to_agreement'
    );
    $assert(
        str_contains($paymentEntrySource, '$lineTotal=$isMonthlyLine?round((float)$actualS+(float)$actualF,2):$ln[\'amount\'];'),
        'initial_monthly_line_total_uses_actual_receipts_only'
    );
    $assert(
        !str_contains($paymentEntrySource, "actualS.value=a?Number(a.sponsorship).toFixed(2)")
        && !str_contains($paymentEntrySource, "actualF.value=a?Number(a.service_fee).toFixed(2)"),
        'javascript_never_prefills_actual_receipts_from_agreement'
    );
    $assert(
        str_contains($paymentEntrySource, "if(resetActuals){actualS.value='';actualF.value='';}"),
        'changing_covered_month_clears_actual_receipts'
    );
    $assert(
        str_contains($paymentEntrySource, 'payment-components-table{width:100%;min-width:0;table-layout:fixed;margin-bottom:0;')
        && str_contains($paymentEntrySource, 'payment-components-table-wrap{width:100%;max-width:100%;overflow:visible;}')
        && str_contains($paymentEntrySource, '<col style="width:12%"><col style="width:14%"><col style="width:16%"><col style="width:16%"><col style="width:12%"><col style="width:20%"><col style="width:10%">')
        && str_contains($paymentEntrySource, '<td colspan="7">')
        && str_contains($paymentEntrySource, 'class="payment-section-title"')
        && str_contains($paymentEntrySource, 'class="payment-setup-panel"')
        && !str_contains($paymentEntrySource, 'width:1590px;')
        && !str_contains($paymentEntrySource, 'overflow-x:auto;-webkit-overflow-scrolling:touch;'),
        'payment_components_use_fit_screen_seven_column_grid_and_sections'
    );
    $agreementSummaryStart = strpos($paymentEntrySource, '<div class="col-12" id="grpLines">');
    $agreementSummaryEnd = strpos($paymentEntrySource, '<div class="card fade-in mb-4"><div class="card-header text-white" style="background:#276749"', $agreementSummaryStart === false ? 0 : $agreementSummaryStart);
    $agreementSummaryMarkup = ($agreementSummaryStart !== false && $agreementSummaryEnd !== false)
        ? substr($paymentEntrySource, $agreementSummaryStart, $agreementSummaryEnd - $agreementSummaryStart)
        : '';
    $assert(
        str_contains($agreementSummaryMarkup, 'id="selectedAgreedSponsorship"')
        && str_contains($agreementSummaryMarkup, 'id="selectedAgreedServiceFee"')
        && str_contains($agreementSummaryMarkup, 'class="col-md-6"'),
        'agreed_amounts_display_side_by_side_above_table'
    );
    $assert(
        str_contains($agreementSummaryMarkup, '<th>الكفالة المستلمة فعلياً</th>')
        && str_contains($agreementSummaryMarkup, '<th>رسوم الخدمة المستلمة فعلياً</th>')
        && !str_contains($agreementSummaryMarkup, '<th>الكفالة المتفق عليها</th>')
        && !str_contains($agreementSummaryMarkup, '<th>رسوم الخدمة المتفق عليها</th>'),
        'payment_table_contains_remaining_fields_not_agreement_references'
    );
    $assert(
        str_contains($paymentEntrySource, 'function showAgreementSummary(line)')
        && str_contains($paymentEntrySource, "document.addEventListener('focusin'"),
        'agreement_summary_follows_selected_month_row'
    );
    $assert(
        !str_contains($paymentEntrySource, '$input[\'amount\']=(string)((float)$pre[\'monthly_amount\'])')
        && str_contains($paymentEntrySource, "'amount'=>'','date'=>date('Y-m-d')"),
        'unrelated_single_payment_amount_starts_blank'
    );
    $donationFormStart = strpos($paymentEntrySource, 'name="entry_section" value="donation"');
    $donationFormEnd = $donationFormStart !== false ? strpos($paymentEntrySource, '</form>', $donationFormStart) : false;
    $donationFormMarkup = ($donationFormStart !== false && $donationFormEnd !== false)
        ? substr($paymentEntrySource, $donationFormStart, $donationFormEnd - $donationFormStart)
        : '';
    $sponsorshipFormStart = strpos($paymentEntrySource, 'name="entry_section" value="sponsorship"');
    $assert(
        $sponsorshipFormStart !== false
        && $donationFormStart !== false
        && $sponsorshipFormStart < $donationFormStart
        && str_contains($paymentEntrySource, 'name="type" id="payType"')
        && str_contains($paymentEntrySource, '<option value="monthly_sponsorship" selected>تحصيل كفالة شهرية</option>')
        && !str_contains($paymentEntrySource, '<option value="admin_fee"')
        && str_contains($paymentEntrySource, 'name="entry_section" value="sponsorship"')
        && str_contains($paymentEntrySource, 'name="entry_section" value="donation"')
        && str_contains($paymentEntrySource, 'إرسال تحصيل الكفالة للمراجعة المالية')
        && str_contains($paymentEntrySource, 'إرسال التبرعات للمراجعة المالية'),
        'two_independent_forms_and_no_standalone_admin_fee_type'
    );
    $assert(
        str_contains($donationFormMarkup, 'donation-entry-table')
        && str_contains($donationFormMarkup, 'name="donation_type[]"')
        && str_contains($donationFormMarkup, 'name="donation_amount[]"')
        && str_contains($donationFormMarkup, 'name="donation_project_id[]"')
        && str_contains($donationFormMarkup, 'name="donation_other_source_note[]"')
        && str_contains($donationFormMarkup, 'name="donation_receipt_file[]"')
        && str_contains($donationFormMarkup, 'name="donation_date[]"')
        && str_contains($donationFormMarkup, 'name="donation_method[]"')
        && str_contains($donationFormMarkup, 'name="donation_receipt[]"')
        && str_contains($donationFormMarkup, 'name="donation_reference[]"')
        && str_contains($donationFormMarkup, 'name="donation_description[]"')
        && str_contains($paymentEntrySource, 'function addDonationLine()')
        && str_contains($donationFormMarkup, 'لا تُفرض رسوم خدمة على هذه التبرعات.')
        && !str_contains($donationFormMarkup, 'name="pay_month[]"')
        && !str_contains($donationFormMarkup, 'name="pay_service_fee_received[]"')
        && str_contains($donationFormMarkup, 'value="<?php echo $input[\'sponsor_id\']; ?>"><div class="payment-section-title"'),
        'donation_form_supports_independent_rows_and_row_evidence_without_service_fees'
    );
    $assert(
        str_contains($paymentEntrySource, 'class="pay-evidence-row"')
        && str_contains($paymentEntrySource, 'line-purpose-note-wrap')
        && str_contains($paymentEntrySource, 'name="pay_note[]"')
        && str_contains($paymentEntrySource, "purposeNote.style.display=line.querySelector('.line-purpose').value==='other'?'':'none';")
        && str_contains($paymentEntrySource, "json_encode(date('Y-m-d'))")
        && str_contains($paymentEntrySource, 'name="pay_date[]"')
        && str_contains($paymentEntrySource, 'name="pay_method[]"')
        && str_contains($paymentEntrySource, 'name="pay_receipt[]"')
        && str_contains($paymentEntrySource, 'name="pay_reference[]"')
        && str_contains($paymentEntrySource, 'name="pay_description[]"')
        && str_contains($paymentEntrySource, 'name="pay_receipt_file[]"')
        && !str_contains($agreementSummaryMarkup, 'name="receipt_file"')
        && str_contains($paymentEntrySource, 'كل سطر يمثل تحصيلاً مستقلاً'),
        'monthly_payment_evidence_is_row_specific_without_shared_proof'
    );
    $sharedEvidenceStart = strpos($paymentEntrySource, 'id="grpSharedEvidence"');
    $assert(
        str_contains($paymentEntrySource, 'name="donation_date[]"')
        && str_contains($paymentEntrySource, 'name="donation_method[]"')
        && str_contains($paymentEntrySource, 'name="donation_receipt[]"')
        && str_contains($paymentEntrySource, 'name="donation_reference[]"')
        && str_contains($paymentEntrySource, 'name="donation_description[]"')
        && str_contains($paymentEntrySource, 'name="donation_receipt_file[]"')
        && str_contains($paymentEntrySource, '$donationReceiptPaths[$donationLine[\'idx\']]')
        && str_contains($paymentEntrySource, 'foreach ($donationLines as $donationLine)')
        && str_contains($paymentEntrySource, 'function removeDonationLine(button)')
        && str_contains($paymentEntrySource, 'recalcDonationTotal()')
        && !str_contains($paymentEntrySource, 'id="grpSharedEvidence"'),
        'donation_evidence_and_server_persistence_are_per_line'
    );
    $monthlyWarningPosition = strpos($paymentEntrySource, 'تنبيه:</strong> قيم الاتفاقية الشهرية مرجعية');
    $paymentSetupPosition = strpos($paymentEntrySource, '<div class="payment-setup-panel">');
    $monthlySectionStart = strpos($paymentEntrySource, '<div class="col-12" id="grpLines">');
    $assert(
        $monthlyWarningPosition !== false
        && $paymentSetupPosition !== false
        && $monthlySectionStart !== false
        && $monthlyWarningPosition < $paymentSetupPosition
        && $paymentSetupPosition < $monthlySectionStart,
        'exact_agreement_warning_before_section_one'
    );
    $typeFieldPosition = strpos($paymentEntrySource, '<div class="col-12"><label class="form-label">نوع الدفعة *</label>');
    $sponsorshipFieldPosition = strpos($paymentEntrySource, '<div class="col-md-5" id="grpShip">');
    $sponsorshipSearchPosition = strpos($paymentEntrySource, 'id="sponsorshipSearch"');
    $assert(
        $typeFieldPosition !== false
        && $sponsorshipFieldPosition !== false
        && $sponsorshipSearchPosition !== false
        && $typeFieldPosition < $sponsorshipFieldPosition
        && $sponsorshipFieldPosition < $sponsorshipSearchPosition
        && str_contains($paymentEntrySource, 'id="sponsorshipSelect" class="form-select form-select-sm"')
        && str_contains($paymentEntrySource, 'class="col-md-7"><label class="form-label sponsorship-field-label" for="sponsorshipSearch">')
        && str_contains($paymentEntrySource, 'placeholder="ابحث باسم الكفيل أو اليتيم أو كود الكفالة..."')
        && str_contains($paymentEntrySource, 'يمكنك البحث باسم الكفيل أو اليتيم أو كود الكفالة.')
        && str_contains($paymentEntrySource, "option.textContent.toLocaleLowerCase('ar').includes(term)"),
        'payment_type_top_row_and_compact_searchable_sponsorship_field'
    );
    $assert(
        str_contains($paymentEntrySource, "\$line['date']")
        && str_contains($paymentEntrySource, "\$line['method']")
        && str_contains($paymentEntrySource, "\$line['receipt']")
        && str_contains($paymentEntrySource, "\$line['reference']")
        && str_contains($paymentEntrySource, "\$line['description']"),
        'monthly_row_metadata_used_during_submission'
    );
    $assert(
        str_contains($paymentEntrySource, "'date'=>\$line['date']")
        && str_contains($paymentEntrySource, "'method'=>\$line['method']")
        && str_contains($paymentEntrySource, "'receipt'=>\$line['receipt']")
        && str_contains($paymentEntrySource, "'reference'=>\$line['reference']")
        && str_contains($paymentEntrySource, "'description'=>\$line['description']")
        && str_contains($paymentEntrySource, "'description'=>\$line['description'], 'own'=>\$donationReceiptPaths"),
        'vgm_monthly_and_nonmonthly_rows_receive_payment_metadata'
    );
    $approvalSource = file_get_contents(dirname(__DIR__) . '/modules/accounting/lib_sponsor_payment_approval.php');
    $reviewQueueSource = file_get_contents(dirname(__DIR__) . '/modules/accounting/fm_review_queue.php');
    $migrationSource = file_get_contents(dirname(__DIR__) . '/database/migrations/2026-11-12_sponsor_payment_row_evidence.sql');
    $transactionReviewSource = file_get_contents(dirname(__DIR__) . '/modules/accounting/lib_transaction_review.php');
    $returnedEditorSource = file_get_contents(dirname(__DIR__) . '/modules/transactions/edit_returned.php');
    $assert(
        is_string($approvalSource)
        && str_contains($approvalSource, "\$sp['transaction_date']")
        && str_contains($approvalSource, "\$sp['receipt_number']")
        && str_contains($approvalSource, "\$sp['reference_number']")
        && str_contains($approvalSource, "\$sp['notes']")
        && str_contains($approvalSource, '$transactionCode'),
        'supervisor_evidence_carried_into_transaction_approval'
    );
    $assert(
        is_string($reviewQueueSource)
        && str_contains($reviewQueueSource, "\$p['transaction_date']")
        && str_contains($reviewQueueSource, "\$p['receipt_number']")
        && str_contains($reviewQueueSource, "\$p['reference_number']")
        && str_contains($reviewQueueSource, "\$p['notes']"),
        'fm_queue_displays_row_evidence'
    );
    $assert(
        str_contains($reviewQueueSource, "(\$h['component_model']??'legacy')==='monthly_agreement'")
        && str_contains($reviewQueueSource, "\$h['actual_sponsorship_received']")
        && str_contains($reviewQueueSource, "\$h['actual_service_fee_received']")
        && str_contains($reviewQueueSource, "\$h['agreed_sponsorship_amount']")
        && str_contains($reviewQueueSource, "\$h['agreed_service_fee_amount']"),
        'fm_review_history_preserves_monthly_agreement_component_breakdown'
    );
    $assert(
        str_contains($reviewQueueSource, "SELECT * FROM sponsor_payments WHERE id=? AND status='pending' FOR UPDATE")
        && str_contains($reviewQueueSource, '$pdo->beginTransaction();')
        && str_contains($reviewQueueSource, "'RETURN','sponsor_payments'")
        && str_contains($reviewQueueSource, "'monthly_agreement_id','agreed_sponsorship_amount','agreed_service_fee_amount','actual_sponsorship_received','actual_service_fee_received'")
        && str_contains($reviewQueueSource, '$pdo->commit();'),
        'fm_return_locks_payment_and_commits_required_snapshot_audit_atomically'
    );
    $assert(
        str_contains($paymentEntrySource, "SELECT * FROM sponsor_payments WHERE id = ? AND status = 'returned' AND supervisor_id = ? FOR UPDATE")
        && str_contains($paymentEntrySource, "'RESUBMIT','sponsor_payments'")
        && str_contains($paymentEntrySource, "'component_model','monthly_agreement_id','agreed_sponsorship_amount','agreed_service_fee_amount','actual_sponsorship_received','actual_service_fee_received'")
        && str_contains($paymentEntrySource, '$pdo->commit();'),
        'supervisor_correction_audits_actuals_and_preserves_agreement_snapshot'
    );
    $assert(
        is_string($returnedEditorSource)
        && str_contains($returnedEditorSource, "if(\$componentModel==='monthly_agreement')")
        && str_contains($returnedEditorSource, "ak_transaction_review_audit(\$uid,'RESUBMIT_FM',\$tid,\$t,\$upd)")
        && str_contains($returnedEditorSource, "\$upd['actual_sponsorship_received']=\$actualS")
        && str_contains($returnedEditorSource, "\$upd['actual_service_fee_received']=\$actualF")
        && !str_contains($returnedEditorSource, "\$upd['monthly_agreement_id']")
        && !str_contains($returnedEditorSource, "\$upd['agreed_sponsorship_amount']")
        && !str_contains($returnedEditorSource, "\$upd['agreed_service_fee_amount']"),
        'vgm_returned_monthly_correction_changes_actuals_only_and_audits_snapshot'
    );
    $assert(
        is_string($transactionReviewSource)
        && str_contains($transactionReviewSource, 'function ak_transaction_review_audit')
        && str_contains($transactionReviewSource, 'dbExecute("INSERT INTO audit_log')
        && !str_contains(substr($transactionReviewSource, strpos($transactionReviewSource, 'function ak_transaction_review_audit'), 900), 'catch (Throwable'),
        'transaction_review_audit_failures_propagate_to_caller_transaction'
    );
    $assert(
        is_string($migrationSource)
        && str_contains($migrationSource, 'transaction_date DATE DEFAULT NULL')
        && str_contains($migrationSource, 'receipt_number VARCHAR(50) DEFAULT NULL')
        && str_contains($migrationSource, 'reference_number VARCHAR(100) DEFAULT NULL'),
        'row_evidence_migration_preserves_legacy_nulls'
    );
    $assert(
        !str_contains($paymentEntrySource, 'الرسوم الإدارية لا تُدخل يدوياً.'),
        'stale_admin_fee_message_removed'
    );
}
$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name=>$passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' | ' . $name . PHP_EOL;
}
echo 'RESULT | ' . count($checks) . ' checks | ' . (count($failed) === 0 ? 'PASS' : 'FAIL') . PHP_EOL;
if ($failed) exit(1);
