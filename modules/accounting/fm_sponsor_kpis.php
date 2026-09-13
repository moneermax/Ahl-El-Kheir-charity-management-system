<?php
// FM sponsor accounting KPI data endpoint.
// Authoritative source: posted accounting ledger only.

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';

Session::start();

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$role = (string)Session::getUserRole();
if (!in_array($role, ['financial_manager', 'admin', 'general_manager', 'vice_general_manager', 'fm'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$kpis = dbFetchOne("SELECT
    COALESCE(SUM(CASE WHEN a.code = '4200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS admin_fee_total,
    COALESCE(SUM(CASE WHEN je.entry_date BETWEEN ? AND ? AND a.code = '4200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS admin_fee_month,
    COALESCE(SUM(CASE WHEN a.code = '4100' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS net_sponsorship_total,
    COALESCE(SUM(CASE WHEN a.code IN ('1100','1200','1300') AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS gross_sponsorship_total,
    COALESCE(SUM(CASE WHEN je.entry_date BETWEEN ? AND ? AND a.code IN ('1100','1200','1300') AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS gross_sponsorship_month
    FROM journal_entries je
    JOIN journal_lines jl ON jl.entry_id = je.id
    JOIN accounts a ON a.id = jl.account_id
    WHERE je.status = 'posted'
      AND EXISTS (
          SELECT 1
          FROM journal_lines sponsor_line
          JOIN accounts sponsor_account ON sponsor_account.id = sponsor_line.account_id
          WHERE sponsor_line.entry_id = je.id
            AND sponsor_account.code = '4100'
            AND sponsor_line.credit > 0
      )", [$monthStart, $monthEnd, $monthStart, $monthEnd]);

$adminFeeTotal = round((float)($kpis['admin_fee_total'] ?? 0), 2);
$adminFeeMonth = round((float)($kpis['admin_fee_month'] ?? 0), 2);
$netSponsorshipTotal = round((float)($kpis['net_sponsorship_total'] ?? 0), 2);
$grossSponsorshipTotal = round((float)($kpis['gross_sponsorship_total'] ?? 0), 2);
$grossSponsorshipMonth = round((float)($kpis['gross_sponsorship_month'] ?? 0), 2);
$reconciliationDifference = round($grossSponsorshipTotal - ($netSponsorshipTotal + $adminFeeTotal), 2);

 echo json_encode([
    'success' => true,
    'currency' => APP_CURRENCY_CODE,
    'month' => date('Y-m'),
    'gross_sponsorship_total' => $grossSponsorshipTotal,
    'gross_sponsorship_month' => $grossSponsorshipMonth,
    'net_sponsorship_total' => $netSponsorshipTotal,
    'admin_fee_total' => $adminFeeTotal,
    'admin_fee_month' => $adminFeeMonth,
    'reconciliation_difference' => $reconciliationDifference,
    'reconciled' => abs($reconciliationDifference) < 0.01,
], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
