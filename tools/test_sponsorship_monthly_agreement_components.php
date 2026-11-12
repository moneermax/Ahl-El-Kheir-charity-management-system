<?php
declare(strict_types=1);

/**
 * Focused unit checks for the monthly sponsorship agreement snapshot contract.
 * No database connection, schema changes, or financial writes are performed.
 */
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
    if (strpos($sql, 'sponsorship_monthly_agreements') === false) {
        throw new RuntimeException('Unexpected database query in isolated test.');
    }
    $row = $GLOBALS['ak_spma_test_agreement'];
    if ($row === null) return null;
    if (strpos($sql, 'WHERE id = ?') !== false) {
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

$positiveFeeBlocked = false;
try { ak_spma_validate_snapshot_for_posting($snapshot); }
catch (RuntimeException $e) { $positiveFeeBlocked = str_contains($e->getMessage(), 'المعالجة المحاسبية'); }
$assert($positiveFeeBlocked, 'positive_service_fee_posting_blocked');

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

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
foreach ($checks as $name=>$passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' | ' . $name . PHP_EOL;
}
echo 'RESULT | ' . count($checks) . ' checks | ' . (count($failed) === 0 ? 'PASS' : 'FAIL') . PHP_EOL;
if ($failed) exit(1);
