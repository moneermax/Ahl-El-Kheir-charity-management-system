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

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name=>$passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' | ' . $name . PHP_EOL;
}
echo 'RESULT | ' . count($checks) . ' checks | ' . (count($failed) === 0 ? 'PASS' : 'FAIL') . PHP_EOL;
if ($failed) exit(1);
