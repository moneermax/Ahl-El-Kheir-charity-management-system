<?php
// modules/accounting/fm_dashboard_kpis.php — authoritative posted-ledger KPIs for FM dashboard.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';

Session::start();
header('Content-Type: application/json; charset=utf-8');

$role = (string)Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($role, ['financial_manager', 'admin', 'general_manager', 'vice_general_manager', 'fm'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    ak_ensure_tables();
    ak_seed_accounts();

    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    $kpis = dbFetchOne("SELECT
        COALESCE(SUM(CASE WHEN a.code = '4200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS admin_fees_total,
        COALESCE(SUM(CASE WHEN je.entry_date BETWEEN ? AND ? AND a.code = '4200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS admin_fees_month,
        COALESCE(SUM(CASE WHEN a.code = '4100' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS sponsorship_net,
        COALESCE(SUM(CASE WHEN a.code IN ('1100','1200','1300') AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS sponsorship_gross,
        COALESCE(SUM(CASE WHEN je.entry_date BETWEEN ? AND ? AND a.code IN ('1100','1200','1300') AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS sponsorship_gross_month
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

    $adminFeesTotal = round((float)($kpis['admin_fees_total'] ?? 0), 2);
    $adminFeesMonth = round((float)($kpis['admin_fees_month'] ?? 0), 2);
    $sponsorshipNet = round((float)($kpis['sponsorship_net'] ?? 0), 2);
    $sponsorshipGross = round((float)($kpis['sponsorship_gross'] ?? 0), 2);
    $sponsorshipGrossMonth = round((float)($kpis['sponsorship_gross_month'] ?? 0), 2);
    $reconciliation = round($sponsorshipGross - $sponsorshipNet - $adminFeesTotal, 2);

    echo json_encode([
        'ok' => true,
        'currency' => 'SDG',
        'month' => date('Y-m'),
        'admin_fees_total' => $adminFeesTotal,
        'admin_fees_month' => $adminFeesMonth,
        'sponsorship_gross' => $sponsorshipGross,
        'sponsorship_gross_month' => $sponsorshipGrossMonth,
        'sponsorship_net' => $sponsorshipNet,
        'sponsorship_reconciliation' => $reconciliation,
        'reconciled' => abs($reconciliation) < 0.01
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
