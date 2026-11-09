<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/sponsor_assignments.php';
require_once dirname(__DIR__) . '/modules/sponsorships/lib_payment_status.php';

if (APP_ENV !== 'development') {
    fwrite(STDERR, "FAIL | development_environment_required\n");
    exit(1);
}

$checks = [];
$check = static function (string $name, bool $passed) use (&$checks): void {
    $checks[$name] = $passed;
    if (!$passed) throw new RuntimeException('FAIL | ' . $name);
};

$active = dbFetchOne(
    "SELECT sp.id, sp.sponsor_id, sp.sponsorship_code
     FROM sponsorships sp
     WHERE sp.status='active'
     ORDER BY sp.id
     LIMIT 1"
);
$supervisor = dbFetchOne(
    "SELECT u.id
     FROM users u
     JOIN roles r ON r.id=u.role_id
     WHERE r.code='supervisor'
     ORDER BY u.id
     LIMIT 1"
);
if (!$active || !$supervisor) {
    throw new RuntimeException('Test prerequisites missing: active sponsorship or supervisor user.');
}
$sponsorshipId = (int)$active['id'];
$sponsorId = (int)$active['sponsor_id'];
$supervisorId = (int)$supervisor['id'];

$freePeriods = [];
for ($offset = 1; $offset <= 12 && count($freePeriods) < 2; $offset++) {
    $month = (new DateTimeImmutable('first day of this month'))->modify('+' . $offset . ' months');
    $period = $month->format('F/Y');
    $direct = dbFetchOne(
        "SELECT id FROM transactions
         WHERE sponsorship_id=? AND payment_period=? AND purpose='monthly_sponsorship'
           AND transaction_type='sponsorship_payment' AND status='pending_fm_review'
         LIMIT 1",
        [$sponsorshipId, $period]
    );
    $submission = dbFetchOne(
        "SELECT id FROM sponsor_payments
         WHERE sponsorship_id=? AND payment_period=? AND payment_type='monthly_sponsorship'
           AND status='pending' AND transaction_id IS NULL
         LIMIT 1",
        [$sponsorshipId, $period]
    );
    if (!$direct && !$submission) $freePeriods[] = $period;
}
if (count($freePeriods) !== 2) {
    throw new RuntimeException('Could not find two unused future months for rollback-only duplicate tests.');
}

$expectDuplicateBlock = static function (int $sid, string $period) use ($check): void {
    $blocked = false;
    try {
        ak_sar_assert_no_pending_month_payment($sid, $period);
    } catch (RuntimeException $e) {
        $blocked = str_contains($e->getMessage(), 'بانتظار المراجعة المالية');
    }
    $check('pending_duplicate_rejected_' . str_replace('/', '_', $period), $blocked);
};

// Supervisor submission duplicate: the inserted row and the guard check are rolled back.
try {
    db()->beginTransaction();
    ak_sar_assert_no_pending_month_payment($sponsorshipId, $freePeriods[0]);
    dbExecute(
        "INSERT INTO sponsor_payments
         (sponsorship_id, supervisor_id, payment_type, sponsor_id, amount, currency_code,
          payment_method, payment_period, status, notes)
         VALUES (?, ?, 'monthly_sponsorship', ?, 1.00, 'SDG', 'cash', ?, 'pending', 'rollback-only duplicate guard test')",
        [$sponsorshipId, $supervisorId, $sponsorId, $freePeriods[0]]
    );
    $expectDuplicateBlock($sponsorshipId, $freePeriods[0]);
    db()->rollBack();
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    throw $e;
}

// Direct FM-review transaction duplicate: the inserted row is rolled back too.
try {
    db()->beginTransaction();
    ak_sar_assert_no_pending_month_payment($sponsorshipId, $freePeriods[1]);
    $code = 'TR-DUP-' . strtoupper(bin2hex(random_bytes(4)));
    dbExecute(
        "INSERT INTO transactions
         (sponsorship_id, amount, currency_code, payment_method, transaction_date,
          receipt_number, description, months_covered, admin_fee_percent, admin_fee_amount,
          net_amount, status, created_by, transaction_code, transaction_type, sponsor_id,
          project_id, reference_number, receipt_path, unified_receipt_path, payment_period,
          purpose_note, purpose, submitted_at)
         VALUES (?, 1.00, 'SDG', 'cash', CURDATE(), NULL, 'rollback-only duplicate guard test',
                 1.00, 0.00, 0.00, 1.00, 'pending_fm_review', ?, ?, 'sponsorship_payment',
                 ?, NULL, NULL, NULL, NULL, ?, NULL, 'monthly_sponsorship', NOW())",
        [$sponsorshipId, $supervisorId, $code, $sponsorId, $freePeriods[1]]
    );
    $expectDuplicateBlock($sponsorshipId, $freePeriods[1]);
    db()->rollBack();
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    throw $e;
}

$adminData = ak_sar_load_report_data('admin', 0);
$vgmData = ak_sar_load_report_data('vice_general_manager', 0);
$joinDiagnostics = dbFetchOne(
    "SELECT COUNT(*) AS total,
            SUM(s.id IS NULL) AS missing_sponsor,
            SUM(fc.id IS NULL) AS missing_child
     FROM sponsorships sp
     LEFT JOIN sponsors s ON s.id=sp.sponsor_id
     LEFT JOIN family_children fc ON fc.id=sp.child_id"
);
$totalSponsorships = (int)($joinDiagnostics['total'] ?? 0);
$adminCount = count($adminData['sponsorships']);
$vgmCount = count($vgmData['sponsorships']);
if ($adminCount !== $totalSponsorships || $vgmCount !== $totalSponsorships) {
    throw new RuntimeException(
        'Admin/VGM scope count mismatch: total=' . $totalSponsorships
        . ', admin=' . $adminCount . ', vgm=' . $vgmCount
        . ', missing_sponsor=' . (int)($joinDiagnostics['missing_sponsor'] ?? 0)
        . ', missing_child=' . (int)($joinDiagnostics['missing_child'] ?? 0)
    );
}
$check('admin_scope_returns_all_sponsorships', $adminCount === $totalSponsorships);
$check('vgm_scope_returns_all_sponsorships', $vgmCount === $totalSponsorships);
$unlinkedChildCount = (int)(dbFetchOne(
    "SELECT COUNT(*) AS total
     FROM sponsorships sp
     LEFT JOIN family_children fc ON fc.id=sp.child_id
     WHERE fc.id IS NULL"
)['total'] ?? 0);
$adminUnlinkedCount = count(array_filter(
    $adminData['sponsorships'],
    static fn(array $row): bool => empty($row['family_child_record_id'])
));
$check('admin_scope_retains_sponsorships_without_child_links', $adminUnlinkedCount === $unlinkedChildCount);

$supervisorData = ak_sar_load_report_data('supervisor', $supervisorId);
$supervisorScopeValid = true;
foreach ($supervisorData['sponsorships'] as $row) {
    if (!supervisorCanAccessSponsor($supervisorId, [
        'id'=>$row['sponsor_id'],
        'supervisor_id'=>$row['supervisor_id'],
        'first_letter_id'=>$row['first_letter_id'],
        'gender'=>$row['gender'],
        'is_manual_override'=>$row['is_manual_override'],
        'sponsor_type'=>$row['sponsor_type'],
        'created_by'=>$row['sponsor_created_by']
    ])) {
        $supervisorScopeValid = false;
        break;
    }
}
$check('supervisor_scope_contains_only_authorized_sponsors', $supervisorScopeValid);

$arrears = ak_sar_get_arrears('admin', 0, ['code'=>(string)$active['sponsorship_code']]);
$advances = ak_sar_get_advances('admin', 0);
$check('arrears_report_query_executes', is_array($arrears));
$check('advances_report_query_executes', is_array($advances));

$denied = false;
try {
    ak_sar_load_report_data('financial_manager', 0);
} catch (RuntimeException $e) {
    $denied = true;
}
$check('unapproved_role_denied', $denied);

echo 'PASS | ' . implode(' | ', array_map(
    static fn(string $name, bool $passed): string => $name . '=' . ($passed ? 'PASS' : 'FAIL'),
    array_keys($checks),
    array_values($checks)
)) . PHP_EOL;
