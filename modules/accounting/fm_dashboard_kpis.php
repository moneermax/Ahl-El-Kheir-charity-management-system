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

    // Authoritative total/monthly administrative-fee revenue: posted credits to account 4200.
    $adminFees = dbFetchOne("SELECT
        COALESCE(SUM(jl.credit), 0) AS total,
        COALESCE(SUM(CASE WHEN je.entry_date BETWEEN ? AND ? THEN jl.credit ELSE 0 END), 0) AS month_total
        FROM journal_lines jl
        JOIN journal_entries je ON je.id = jl.entry_id
        JOIN accounts a ON a.id = jl.account_id
        WHERE a.code = '4200'
          AND jl.credit > 0
          AND je.status = 'posted'", [$monthStart, $monthEnd]);

    // Monthly flow is calculated from the posted ledger using normal double-entry direction:
    // revenue = credits, expenses = debits. Voided journals are excluded.
    $monthlyFlow = dbFetchOne("SELECT
        COALESCE(SUM(CASE WHEN a.account_type = 'revenue' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS income_total,
        COALESCE(SUM(CASE WHEN a.code = '4100' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS income_sponsorship,
        COALESCE(SUM(CASE WHEN a.code = '4200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS income_admin_fee,
        COALESCE(SUM(CASE WHEN a.account_type = 'expense' AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS outgoing_total,
        COALESCE(SUM(CASE WHEN a.code = '5100' AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS outgoing_programs,
        COALESCE(SUM(CASE WHEN a.code = '5200' AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS outgoing_salaries
        FROM journal_lines jl
        JOIN journal_entries je ON je.id = jl.entry_id
        JOIN accounts a ON a.id = jl.account_id
        WHERE je.entry_date BETWEEN ? AND ?
          AND je.status = 'posted'", [$monthStart, $monthEnd]);

    // Sponsorship reconciliation is restricted to posted journals generated from sponsorship transactions.
    // Gross = debit collected into cash/bank/mobile; net = credit to 4100; fees = credit to 4200.
    $sponsorship = dbFetchOne("SELECT
        COALESCE(SUM(CASE WHEN a.code IN ('1100','1200','1300') AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS gross,
        COALESCE(SUM(CASE WHEN a.code = '4100' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS net,
        COALESCE(SUM(CASE WHEN a.code = '4200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS fees
        FROM journal_entries je
        JOIN transactions t ON t.id = je.reference_id
        JOIN journal_lines jl ON jl.entry_id = je.id
        JOIN accounts a ON a.id = jl.account_id
        WHERE je.status = 'posted'
          AND je.reference_type = 'transaction'
          AND t.transaction_type = 'sponsorship_payment'");

    $gross = round((float)($sponsorship['gross'] ?? 0), 2);
    $net = round((float)($sponsorship['net'] ?? 0), 2);
    $sponsorshipFees = round((float)($sponsorship['fees'] ?? 0), 2);

    echo json_encode([
        'ok' => true,
        'currency' => 'SDG',
        'month' => date('Y-m'),
        'admin_fees_total' => round((float)($adminFees['total'] ?? 0), 2),
        'admin_fees_month' => round((float)($adminFees['month_total'] ?? 0), 2),
        'monthly_income_total' => round((float)($monthlyFlow['income_total'] ?? 0), 2),
        'monthly_income_sponsorship' => round((float)($monthlyFlow['income_sponsorship'] ?? 0), 2),
        'monthly_income_admin_fee' => round((float)($monthlyFlow['income_admin_fee'] ?? 0), 2),
        'monthly_expense_total' => round((float)($monthlyFlow['outgoing_total'] ?? 0), 2),
        'monthly_expense_programs' => round((float)($monthlyFlow['outgoing_programs'] ?? 0), 2),
        'monthly_expense_salaries' => round((float)($monthlyFlow['outgoing_salaries'] ?? 0), 2),
        'sponsorship_gross' => $gross,
        'sponsorship_net' => $net,
        'sponsorship_admin_fees' => $sponsorshipFees,
        'sponsorship_reconciliation' => round($gross - $net - $sponsorshipFees, 2),
        'report_url' => APP_URL . 'modules/accounting/admin_fee_report.php'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
