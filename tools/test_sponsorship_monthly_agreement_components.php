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
        str_contains($paymentEntrySource, 'payment-components-table{width:100%;min-width:0;table-layout:fixed;}')
        && str_contains($paymentEntrySource, 'payment-components-table-wrap{width:100%;overflow-x:hidden;}')
        && str_contains($paymentEntrySource, '<col style="width:10%"><col style="width:11%"><col style="width:13.5%"><col style="width:13.5%"><col style="width:9%"><col style="width:17%"><col style="width:17%"><col style="width:9%">')
        && !str_contains($paymentEntrySource, 'width:1590px')
        && !str_contains($paymentEntrySource, 'overflow-x:auto;'),
        'payment_components_fit_available_width_without_horizontal_scroll'
    );
    $agreementSummaryStart = strpos($paymentEntrySource, '<div class="col-12" id="grpLines">');
    $agreementSummaryEnd = strpos($paymentEntrySource, '<div class="col-md-4" id="grpSingleAmount"', $agreementSummaryStart === false ? 0 : $agreementSummaryStart);
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
    $assert(
        str_contains($paymentEntrySource, 'class="pay-evidence-row"')
        && str_contains($paymentEntrySource, 'name="pay_date[]"')
        && str_contains($paymentEntrySource, 'name="pay_method[]"')
        && str_contains($paymentEntrySource, 'name="pay_receipt[]"')
        && str_contains($paymentEntrySource, 'name="pay_reference[]"')
        && str_contains($paymentEntrySource, 'name="pay_description[]"')
        && str_contains($paymentEntrySource, 'name="pay_receipt_file[]"'),
        'monthly_payment_evidence_is_row_specific'
    );
    $sharedEvidenceStart = strpos($paymentEntrySource, 'id="grpSharedEvidence"');
    $assert(
        $sharedEvidenceStart !== false
        && strpos($paymentEntrySource, 'name="date"', $sharedEvidenceStart) !== false
        && strpos($paymentEntrySource, 'name="method"', $sharedEvidenceStart) !== false
        && strpos($paymentEntrySource, 'name="receipt"', $sharedEvidenceStart) !== false
        && strpos($paymentEntrySource, 'name="reference"', $sharedEvidenceStart) !== false
        && strpos($paymentEntrySource, 'name="description"', $sharedEvidenceStart) !== false
        && strpos($paymentEntrySource, 'name="receipt_file"', $sharedEvidenceStart) !== false
        && str_contains($paymentEntrySource, "document.getElementById('grpSharedEvidence').style.display=monthly?'none':'';"),
        'shared_evidence_section_is_nonmonthly_only'
    );
    $monthlyWarningPosition = strpos($paymentEntrySource, 'تنبيه:</strong> قيم الاتفاقية الشهرية مرجعية');
    $monthlySectionStart = strpos($paymentEntrySource, '<div class="col-12" id="grpLines">');
    $agreementSummaryPosition = strpos($paymentEntrySource, 'id="agreementSummary"');
    $assert(
        $monthlyWarningPosition !== false
        && $monthlySectionStart !== false
        && $agreementSummaryPosition !== false
        && $monthlyWarningPosition > $monthlySectionStart
        && $monthlyWarningPosition < $agreementSummaryPosition,
        'exact_agreement_warning_at_top_of_monthly_section'
    );
    $assert(
        str_contains($paymentEntrySource, '$line[\'date\']')
        && str_contains($paymentEntrySource, '$line[\'method\']')
        && str_contains($paymentEntrySource, '$line[\'receipt\']')
        && str_contains($paymentEntrySource, '$line[\'reference\']')
        && str_contains($paymentEntrySource, '$line[\'description\']'),
        'monthly_row_metadata_used_during_submission'
    );
    $approvalSource = file_get_contents(dirname(__DIR__) . '/modules/accounting/lib_sponsor_payment_approval.php');
    $reviewQueueSource = file_get_contents(dirname(__DIR__) . '/modules/accounting/fm_review_queue.php');
    $migrationSource = file_get_contents(dirname(__DIR__) . '/database/migrations/2026-11-12_sponsor_payment_row_evidence.sql');
    $assert(
        is_string($approvalSource)
        && str_contains($approvalSource, '$sp[\'transaction_date\']')
        && str_contains($approvalSource, '$sp[\'receipt_number\']')
        && str_contains($approvalSource, '$sp[\'reference_number\']')
        && str_contains($approvalSource, '$sp[\'notes\']')
        && str_contains($approvalSource, '$transactionCode'),
        'supervisor_evidence_carried_into_transaction_approval'
    );
    $assert(
        is_string($reviewQueueSource)
        && str_contains($reviewQueueSource, '$p[\'transaction_date\']')
        && str_contains($reviewQueueSource, '$p[\'receipt_number\']')
        && str_contains($reviewQueueSource, '$p[\'reference_number\']')
        && str_contains($reviewQueueSource, '$p[\'notes\']'),
        'fm_queue_displays_row_evidence'
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
