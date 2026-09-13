<?php
// modules/accounting/fm_dashboard.php — v2.2 Complete Financial Oversight + Sponsor Accounting KPIs
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_outflows.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'login.php'); exit; }

$uid = (int)Session::getUserId();
$urole = (string)Session::getUserRole();

if (!in_array($urole, ['financial_manager', 'admin', 'general_manager', 'vice_general_manager', 'fm'], true)) {
    flash('error', t('fm.unauthorized'));
    header('Location: ' . APP_URL . 'dashboard/staff_dashboard.php');
    exit;
}

$active = 'fm_dashboard';
$pageTitle = t('fm.page_title');

ak_ensure_tables();
ak_seed_accounts();
ak_out_ensure_schema();

/* ══════════ PROJECT BUDGET FIRST-LEVEL APPROVAL ══════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_fm_review'])) {
    if (!verify_csrf()) {
        flash('error', t('fm.session_expired'));
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (!in_array($urole, ['financial_manager', 'admin', 'fm'], true)) {
        flash('error', t('fm.approval_fm_only'));
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $projectId = (int)($_POST['project_id'] ?? 0);
    $decision = (string)($_POST['project_fm_decision'] ?? '');
    $fmReason = trim((string)($_POST['fm_rejection_reason'] ?? ''));
    $postedFundingAmounts = $_POST['funding_amounts'][$projectId] ?? [];
    $approval = dbFetchOne(
        'SELECT pa.*, p.name AS project_name, p.currency_code
         FROM project_approval pa
         JOIN other_projects p ON p.id = pa.project_id
         WHERE pa.project_id = ?',
        [$projectId]
    );

    try {
        if (!$approval || $approval['approval_status'] !== 'submitted') {
            throw new RuntimeException(t('fm.project_not_in_queue'));
        }
        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new RuntimeException(t('fm.invalid_review_decision'));
        }
        if ($decision === 'reject' && $fmReason === '') {
            throw new RuntimeException(t('fm.rejection_reason_required'));
        }

        $budgetRow = dbFetchOne("SELECT b.id AS budget_id, COALESCE(SUM(COALESCE(bl.approved_amount, bl.estimated_amount)), 0) AS budget_amount
            FROM project_budgets b LEFT JOIN project_budget_lines bl ON bl.budget_id = b.id
            WHERE b.project_id = ? AND b.status = 'draft'
            GROUP BY b.id ORDER BY b.version_no DESC, b.id DESC LIMIT 1", [$projectId]);
        if (!$budgetRow) {
            $fallback = dbFetchOne('SELECT target_amount, currency_code FROM other_projects WHERE id = ?', [$projectId]);
            $budgetRow = ['budget_id' => null, 'budget_amount' => (float)($fallback['target_amount'] ?? 0)];
        }
        $budgetAmount = round((float)$budgetRow['budget_amount'], 2);

        dbExecute('START TRANSACTION');

        if ($decision === 'approve') {
            if ($budgetAmount <= 0) throw new RuntimeException(t('fm.no_budget_amount'));
            $accountIds = array_map('intval', array_keys($postedFundingAmounts));
            $allowedAccounts = dbFetchAll("SELECT a.id, a.code,
                    COALESCE(SUM(
                        CASE
                            WHEN je.status = 'posted' THEN jl.debit - jl.credit
                            ELSE 0
                        END
                    ), 0) AS ledger_balance,
                    COALESCE((SELECT SUM(f.amount)
                        FROM project_funding_allocations f
                        WHERE f.source_account_id = a.id AND f.status = 'approved'), 0) AS reserved_amount
                FROM accounts a
                LEFT JOIN journal_lines jl ON jl.account_id = a.id
                LEFT JOIN journal_entries je ON je.id = jl.entry_id
                WHERE a.code IN ('1100','1200','1300') AND a.is_active = 1
                GROUP BY a.id, a.code ORDER BY a.code");
            $accountsById = [];
            foreach ($allowedAccounts as $account) $accountsById[(int)$account['id']] = $account;
            $allocationTotal = 0.0; $allocationRows = [];
            foreach ($postedFundingAmounts as $accountId => $rawAmount) {
                $accountId = (int)$accountId; $amount = round((float)$rawAmount, 2);
                if ($amount <= 0) continue;
                if (!isset($accountsById[$accountId])) throw new RuntimeException(t('fm.invalid_funding_account'));
                $available = round((float)$accountsById[$accountId]['ledger_balance'] - (float)$accountsById[$accountId]['reserved_amount'], 2);
                if ($amount > $available) throw new RuntimeException(t('fm.insufficient_balance', ['code' => $accountsById[$accountId]['code'], 'amount' => number_format($available, 2)]));
                $allocationTotal += $amount;
                $allocationRows[] = [$accountId, $accountsById[$accountId]['code'], $amount];
            }
            if (round($allocationTotal, 2) !== $budgetAmount) throw new RuntimeException(t('fm.funding_total_mismatch', ['amount' => number_format($budgetAmount, 2)]));
            $expenseAccount = dbFetchOne("SELECT id FROM accounts WHERE code = '5110' AND is_active = 1 LIMIT 1");
            dbExecute('DELETE FROM project_funding_allocations WHERE project_id = ? AND status = \'approved\' AND journal_entry_id IS NULL', [$projectId]);
            foreach ($allocationRows as $allocation) {
                $sourceType = ['1100' => 'treasury', '1200' => 'bank', '1300' => 'electronic_wallet'][$allocation[1]] ?? 'treasury';
                dbExecute('INSERT INTO project_funding_allocations (project_id, budget_id, source_type, source_account_id, destination_account_id, amount, currency_code, allocation_date, reference_number, description, status, approved_by, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [$projectId, $budgetRow['budget_id'] ?: null, $sourceType, $allocation[0], $expenseAccount['id'] ?? null, $allocation[2], $approval['currency_code'] ?? 'SDG', date('Y-m-d'), 'FM-PRJ-' . $projectId, 'اعتماد مالي أولي لميزانية المشروع', 'approved', $uid, $uid]);
            }
            dbExecute("UPDATE project_budgets SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE project_id = ? AND status = 'draft' ORDER BY version_no DESC LIMIT 1", [$uid, $projectId]);
            dbExecute("UPDATE project_approval SET approval_status = 'fm_approved', fm_reviewed_by = ?, fm_reviewed_at = NOW(), fm_rejection_reason = NULL WHERE project_id = ? AND approval_status = 'submitted'", [$uid, $projectId]);
            $auditAction = 'FM_APPROVE_PROJECT_BUDGET';
            $auditNew = ['approval_status' => 'fm_approved'];
            $message = t('fm.approved_success');
        } else {
            dbExecute("UPDATE project_approval SET approval_status = 'rejected', fm_reviewed_by = ?, fm_reviewed_at = NOW(), fm_rejection_reason = ? WHERE project_id = ? AND approval_status = 'submitted'", [$uid, $fmReason, $projectId]);
            $auditAction = 'FM_REJECT_PROJECT_BUDGET';
            $auditNew = ['approval_status' => 'rejected', 'fm_rejection_reason' => $fmReason];
            $message = t('fm.rejected_success');
        }

        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, 'project_approval', ?, ?, ?, ?, ?)", [
            $uid, $auditAction, $projectId,
            json_encode(['approval_status' => 'submitted'], JSON_UNESCAPED_UNICODE),
            json_encode($auditNew, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        dbExecute('COMMIT');
        flash('success', $message);
    } catch (Throwable $e) {
        try { dbExecute('ROLLBACK'); } catch (Throwable $ignored) {}
        flash('error', $e->getMessage());
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

/* ══════════ PROJECT BUDGET REVIEW QUEUE ══════════ */
$projectApprovalQueue = dbFetchAll("SELECT
        p.id AS project_id, p.project_code, p.name AS project_name, p.project_type,
        p.currency_code, p.start_date, p.end_date, pa.submitted_at,
        submitter.full_name AS submitted_by_name,
        COALESCE(SUM(COALESCE(bl.approved_amount, bl.estimated_amount)), 0) AS budget_amount,
        b.version_no AS budget_version, COUNT(bl.id) AS budget_line_count
    FROM project_approval pa
    JOIN other_projects p ON p.id = pa.project_id
    LEFT JOIN users submitter ON submitter.id = pa.submitted_by
    LEFT JOIN project_budgets b ON b.id = (
        SELECT b2.id FROM project_budgets b2
        WHERE b2.project_id = p.id AND b2.status = 'draft'
        ORDER BY b2.version_no DESC, b2.id DESC LIMIT 1
    )
    LEFT JOIN project_budget_lines bl ON bl.budget_id = b.id
    WHERE pa.approval_status = 'submitted'
    GROUP BY p.id, p.project_code, p.name, p.project_type, p.currency_code,
             p.start_date, p.end_date, pa.submitted_at, submitter.full_name,
             b.version_no
    ORDER BY pa.submitted_at ASC, p.id ASC");
$projectApprovalCount = count($projectApprovalQueue);
$fundingAccounts = dbFetchAll("SELECT a.id, a.code, a.name_ar, a.name_en,
        COALESCE(SUM(
            CASE
                WHEN je.status = 'posted' THEN jl.debit - jl.credit
                ELSE 0
            END
        ), 0) AS ledger_balance,
        COALESCE((SELECT SUM(f.amount)
            FROM project_funding_allocations f
            WHERE f.source_account_id = a.id AND f.status = 'approved'), 0) AS reserved_amount
    FROM accounts a
    LEFT JOIN journal_lines jl ON jl.account_id = a.id
    LEFT JOIN journal_entries je ON je.id = jl.entry_id
    WHERE a.code IN ('1100','1200','1300') AND a.is_active = 1
    GROUP BY a.id, a.code, a.name_ar, a.name_en ORDER BY a.code");

$treasury = dbFetchOne("SELECT
    COALESCE(SUM(CASE WHEN a.code = '1100' THEN jl.debit - jl.credit ELSE 0 END), 0) AS cash,
    COALESCE(SUM(CASE WHEN a.code = '1200' THEN jl.debit - jl.credit ELSE 0 END), 0) AS bank,
    COALESCE(SUM(CASE WHEN a.code = '1300' THEN jl.debit - jl.credit ELSE 0 END), 0) AS wallet,
    COALESCE(SUM(jl.debit - jl.credit), 0) AS total
    FROM journal_lines jl JOIN accounts a ON a.id = jl.account_id JOIN journal_entries je ON je.id = jl.entry_id
    WHERE a.code IN ('1100','1200','1300') AND a.is_active = 1 AND je.status = 'posted'");

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthlyFlow = dbFetchOne("SELECT
    COALESCE(SUM(CASE WHEN a.account_type = 'revenue' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS income_total,
    COALESCE(SUM(CASE WHEN a.code = '4100' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS income_cash,
    COALESCE(SUM(CASE WHEN a.code = '4200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS income_bank,
    COALESCE(SUM(CASE WHEN a.account_type = 'expense' AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS outgoing_total,
    COALESCE(SUM(CASE WHEN a.code = '5100' AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS outgoing_cash,
    COALESCE(SUM(CASE WHEN a.code = '5200' AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS outgoing_bank
    FROM journal_lines jl JOIN journal_entries je ON je.id = jl.entry_id JOIN accounts a ON a.id = jl.account_id
    WHERE je.entry_date BETWEEN ? AND ? AND je.status = 'posted'", [$monthStart, $monthEnd]);

/* ══════════ SPONSOR ACCOUNTING KPIs ══════════
 * These values are ledger-authoritative. A sponsorship collection is identified by
 * a posted journal entry containing a credit to account 4100. Gross collection is
 * the treasury debit on those same posted journal entries; net revenue and admin
 * fees are the corresponding credits to 4100 and 4200. This keeps the dashboard
 * aligned with the atomic sponsor-payment journal model.
 */
$sponsorKpis = dbFetchOne("SELECT
    COALESCE(SUM(CASE WHEN a.code = '4100' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS net_sponsorship_total,
    COALESCE(SUM(CASE WHEN a.code = '4200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS admin_fee_total,
    COALESCE(SUM(CASE WHEN je.entry_date BETWEEN ? AND ? AND a.code = '4200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS admin_fee_month,
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
$sponsorNetTotal = (float)($sponsorKpis['net_sponsorship_total'] ?? 0);
$sponsorAdminFeeTotal = (float)($sponsorKpis['admin_fee_total'] ?? 0);
$sponsorAdminFeeMonth = (float)($sponsorKpis['admin_fee_month'] ?? 0);
$sponsorGrossTotal = (float)($sponsorKpis['gross_sponsorship_total'] ?? 0);
$sponsorGrossMonth = (float)($sponsorKpis['gross_sponsorship_month'] ?? 0);
$sponsorReconciliation = round($sponsorGrossTotal - ($sponsorNetTotal + $sponsorAdminFeeTotal), 2);

$disbStats = dbFetchOne("SELECT COUNT(*) AS total,
    SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END) AS pending_approval,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status = 'transferred' THEN 1 ELSE 0 END) AS transferred,
    SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) AS received,
    SUM(CASE WHEN status = 'voided' THEN 1 ELSE 0 END) AS voided,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
    SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) AS returned,
    COALESCE(SUM(CASE WHEN status = 'pending_approval' THEN total_amount ELSE 0 END), 0) AS pending_amount,
    COALESCE(SUM(CASE WHEN status = 'transferred' THEN total_amount ELSE 0 END), 0) AS transferred_amount
    FROM monthly_disbursements");

$openBatches = dbFetchAll("SELECT d.*, u.full_name AS nanny_name,
    (SELECT COUNT(*) FROM disbursement_items WHERE disbursement_id = d.id) AS total_items,
    (SELECT COUNT(*) FROM disbursement_items WHERE disbursement_id = d.id AND status = 'paid') AS paid_items,
    (SELECT COUNT(*) FROM disbursement_items WHERE disbursement_id = d.id AND status = 'pending') AS pending_items,
    (SELECT COUNT(*) FROM disbursement_items WHERE disbursement_id = d.id AND status = 'return_requested') AS return_items
    FROM monthly_disbursements d LEFT JOIN users u ON u.id = d.nanny_id
    WHERE d.status = 'transferred' ORDER BY d.transferred_at ASC");

$recentReturns = dbFetchAll("SELECT je.id, je.entry_code, je.entry_date, je.description,
    SUM(CASE WHEN jl.credit > 0 THEN jl.credit ELSE 0 END) AS amount, di.family_id, f.family_code
    FROM journal_entries je JOIN journal_lines jl ON jl.entry_id = je.id
    LEFT JOIN disbursement_items di ON di.reversal_journal_id = je.id LEFT JOIN families f ON f.id = di.family_id
    WHERE je.entry_code LIKE 'JE-RET%' AND je.status = 'posted'
    GROUP BY je.id ORDER BY je.created_at DESC LIMIT 10");

$recentVoids = dbFetchAll("SELECT d.id, d.month, d.total_amount, d.void_reason, d.voided_at,
    u.full_name AS voided_by_name, n.full_name AS nanny_name
    FROM monthly_disbursements d LEFT JOIN users u ON u.id = d.voided_by_user_id LEFT JOIN users n ON n.id = d.nanny_id
    WHERE d.status = 'voided' ORDER BY d.voided_at DESC LIMIT 10");

$quickStats = dbFetchOne("SELECT
    (SELECT COUNT(*) FROM sponsorships WHERE status = 'active') AS active_sponsorships,
    (SELECT COUNT(*) FROM families WHERE status = 'active') AS active_families,
    (SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'nanny' AND u.is_active = 1) AS active_nannies,
    (SELECT COALESCE(SUM(total_amount), 0) FROM monthly_disbursements WHERE status = 'received' AND month = DATE_FORMAT(NOW(), '%Y-%m')) AS received_this_month");

$pendingQueue = dbFetchAll("SELECT d.*, u.full_name AS nanny_name, n.full_name AS created_by_name
    FROM monthly_disbursements d LEFT JOIN users u ON u.id = d.nanny_id LEFT JOIN users n ON n.id = d.created_by
    WHERE d.status = 'pending_approval' ORDER BY d.submitted_at ASC");

$recentJournals = dbFetchAll("SELECT je.entry_code, je.entry_date, je.description, je.status,
    SUM(jl.debit) AS total_debit, SUM(jl.credit) AS total_credit
    FROM journal_entries je JOIN journal_lines jl ON jl.entry_id = je.id
    WHERE je.status = 'posted' GROUP BY je.id ORDER BY je.created_at DESC LIMIT 10");
?>

<?php require_once dirname(__DIR__, 2) . '/includes/header.php'; ?>
<?php require_once dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>

<style>
.fm-header { background: linear-gradient(135deg, #1b4d8f 0%, #2c5aa0 100%); color: #fff; padding: 25px; border-radius: 12px; margin-bottom: 25px; }
.fm-header h1 { margin: 0; font-size: 1.8rem; }
.fm-header p { margin: 5px 0 0; opacity: 0.9; }
.fm-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 20px; overflow: hidden; }
.fm-card-head { background: #1b4d8f; color: #fff; padding: 12px 18px; font-weight: 700; font-size: 1rem; display: flex; justify-content: space-between; align-items: center; }
.fm-card-body { padding: 20px; }
.stat-box { background: #fff; border-radius: 10px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-top: 4px solid #1b4d8f; text-align: center; }
.stat-box.green { border-top-color: #28a745; }
.stat-box.red { border-top-color: #dc3545; }
.stat-box.amber { border-top-color: #ffc107; }
.stat-box.blue { border-top-color: #17a2b8; }
.stat-box.purple { border-top-color: #6f42c1; }
.stat-value { font-size: 1.6rem; font-weight: 700; color: #1b4d8f; margin: 8px 0; }
.stat-label { color: #666; font-size: 0.85rem; }
.stat-sub { color: #999; font-size: 0.75rem; margin-top: 4px; }
.fm-table { width: 100%; border-collapse: collapse; }
.fm-table th, .fm-table td { padding: 10px 12px; border-bottom: 1px solid #eee; text-align: right; font-size: 0.9rem; }
.fm-table th { background: #f8f9fa; font-weight: 700; color: #1b4d8f; }
.fm-table tr:hover { background: #f8f9fa; }
.badge-fm { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; }
.badge-green { background: #d4edda; color: #155724; }
.badge-red { background: #f8d7da; color: #721c24; }
.badge-amber { background: #fff3cd; color: #856404; }
.badge-blue { background: #d1ecf1; color: #0c5460; }
.badge-gray { background: #e9ecef; color: #6c757d; }
.btn-fm { display: inline-block; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: 700; text-decoration: none; font-size: 0.85rem; margin: 2px; }
.btn-navy { background: #1b4d8f; color: #fff; }
.btn-navy:hover { background: #143a6b; color: #fff; }
.btn-ghost { background: transparent; color: #1b4d8f; border: 1px solid #1b4d8f; }
.btn-ghost:hover { background: #1b4d8f; color: #fff; }
.alert-fm { padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.flow-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.flow-row:last-child { border-bottom: none; }
.flow-label { color: #666; }
.flow-value { font-weight: 700; color: #1b4d8f; }
.flow-value.income { color: #28a745; }
.flow-value.outgoing { color: #dc3545; }
.empty-state { text-align: center; padding: 30px; color: #999; }
.grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px; }
.grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
.fm-top-layout { display: grid; grid-template-columns: minmax(300px, 0.34fr) minmax(0, 1fr); gap: 20px; align-items: start; margin-bottom: 20px; }
.fm-review-card { align-self: start; height: auto !important; min-height: 0 !important; margin: 0; }
.fm-review-card .fm-card-body { display: block; height: auto; min-height: 0; padding: 16px 20px 20px; }
.fm-top-right { align-self: start; min-width: 0; }
.fm-top-right > .grid-4 { margin-bottom: 20px; }
.fm-top-right > .fm-card { margin-bottom: 0; }
@media (max-width: 991.98px) {
    .fm-top-layout { grid-template-columns: 1fr; }
    .fm-review-card { margin-bottom: 20px; }
}
</style>

<main class="container-fluid py-4">
<div class="fm-header">
    <h1><?php echo e(t('fm.welcome')); ?></h1>
    <p><?php echo e(t('fm.overview', ['date' => date('Y-m-d')])); ?></p>
</div>

<?php
$fl = $_SESSION['flash'] ?? null;
if (is_array($fl)) {
    foreach ($fl as $m) {
        $cls = $m['type'] === 'error' ? 'alert-error' : 'alert-success';
        echo '<div class="alert-fm ' . $cls . '">' . e($m['message']) . '</div>';
    }
    unset($_SESSION['flash']);
}
?>

    <div class="fm-top-right">
        <!-- ══════════ TREASURY BALANCE ══════════ -->
        <div class="grid-4">
            <div class="stat-box"><div class="stat-label">💵 <?php echo e(t('fm.cash')); ?></div><div class="stat-value"><?php echo number_format((float)$treasury['cash'], 0); ?></div><div class="stat-sub"><?php echo e(t('fm.account_1100')); ?></div></div>
            <div class="stat-box blue"><div class="stat-label">🏦 <?php echo e(t('fm.bank')); ?></div><div class="stat-value"><?php echo number_format((float)$treasury['bank'], 0); ?></div><div class="stat-sub"><?php echo e(t('fm.account_1200')); ?></div></div>
            <div class="stat-box purple"><div class="stat-label">📱 <?php echo e(t('fm.wallet')); ?></div><div class="stat-value"><?php echo number_format((float)$treasury['wallet'], 0); ?></div><div class="stat-sub"><?php echo e(t('fm.account_1300')); ?></div></div>
            <div class="stat-box green"><div class="stat-label">💰 <?php echo e(t('fm.total_treasury')); ?></div><div class="stat-value"><?php echo number_format((float)$treasury['total'], 0); ?></div><div class="stat-sub"><?php echo e(t('fm.currency_sdg')); ?></div></div>
        </div>

        <!-- ══════════ SPONSOR ACCOUNTING KPIs ══════════ -->
        <div class="fm-card" id="sponsor-accounting-kpis">
            <div class="fm-card-head">
                <span>💳 مؤشرات تحصيل الكفالات والرسوم الإدارية</span>
                <span class="badge-fm badge-green">SDG · Posted Ledger</span>
            </div>
            <div class="fm-card-body">
                <div class="grid-4">
                    <div class="stat-box green">
                        <div class="stat-label">💰 إجمالي تحصيل الكفالات</div>
                        <div class="stat-value"><?php echo number_format($sponsorGrossTotal, 2); ?></div>
                        <div class="stat-sub">من قيود الخزينة المرتبطة بإيراد الكفالات 4100</div>
                    </div>
                    <div class="stat-box blue">
                        <div class="stat-label">📈 صافي إيرادات الكفالات</div>
                        <div class="stat-value"><?php echo number_format($sponsorNetTotal, 2); ?></div>
                        <div class="stat-sub">الحساب 4100 — أرصدة دائنة مرحلة</div>
                    </div>
                    <div class="stat-box amber">
                        <div class="stat-label">🧾 إجمالي الرسوم الإدارية المحصلة</div>
                        <div class="stat-value"><?php echo number_format($sponsorAdminFeeTotal, 2); ?></div>
                        <div class="stat-sub">الحساب 4200 — أرصدة دائنة مرحلة</div>
                    </div>
                    <div class="stat-box purple">
                        <div class="stat-label">🗓️ رسوم إدارية هذا الشهر</div>
                        <div class="stat-value"><?php echo number_format($sponsorAdminFeeMonth, 2); ?></div>
                        <div class="stat-sub"><?php echo e(date('Y-m')); ?> · SDG</div>
                    </div>
                </div>
                <div class="flow-row" style="margin-top:5px; padding-top:14px;">
                    <span class="flow-label"><strong>مطابقة تحصيل الكفالات</strong> = صافي إيرادات الكفالات + الرسوم الإدارية</span>
                    <span class="flow-value <?php echo abs($sponsorReconciliation) < 0.01 ? 'income' : 'outgoing'; ?>">
                        <?php echo abs($sponsorReconciliation) < 0.01 ? 'مطابق ✓' : 'فرق: ' . number_format($sponsorReconciliation, 2); ?>
                    </span>
                </div>
                <div class="flow-row">
                    <span class="flow-label">إجمالي الكفالات هذا الشهر</span>
                    <span class="flow-value income"><?php echo number_format($sponsorGrossMonth, 2); ?> SDG</span>
                </div>
            </div>
        </div>

        <!-- ══════════ TOP FINANCIAL OVERSIGHT LAYOUT ══════════ -->
        <div class="fm-top-layout">
            <div class="fm-card fm-review-card" id="fm-transaction-review">
                <div class="fm-card-head">
                    <span>🧾 مراجعة المعاملات المالية</span>
                    <span class="badge-fm badge-blue"><i class="fas fa-clipboard-check"></i></span>
                </div>
                <div class="fm-card-body">
                    <div style="width:100%;">
                        <div style="font-size:1.15rem; font-weight:700; color:#1b4d8f; margin-bottom:10px;">مراجعة واعتماد المعاملات المالية</div>
                        <a href="<?php echo APP_URL; ?>modules/accounting/fm_transaction_review.php" class="btn-fm btn-navy" style="width:100%; text-align:center; padding:12px 16px;">
                            <i class="fas fa-clipboard-check"></i>
                            فتح شاشة مراجعة المعاملات
                        </a>
                    </div>
                </div>
            </div>

            <!-- ══════════ PROJECT BUDGETS PENDING FM REVIEW ══════════ -->
            <div id="project-budget-review" class="fm-card" style="border-right:5px solid #ffc107;">
                <div class="fm-card-head">
                    <span>📁 <?php echo e(t('fm.project_budget_review')); ?></span>
                    <span class="badge-fm <?php echo $projectApprovalCount ? 'badge-amber' : 'badge-green'; ?>"><?php echo e(t('fm.request_count', ['count' => $projectApprovalCount])); ?></span>
                </div>
                <div class="fm-card-body">
                    <?php if (!$projectApprovalQueue): ?>
                        <div class="empty-state"><?php echo e(t('fm.no_project_budgets')); ?></div>
                    <?php else: ?>
                        <div style="overflow-x:auto">
                            <table class="fm-table">
                                <thead><tr><th><?php echo e(t('fm.project')); ?></th><th><?php echo e(t('fm.project_type')); ?></th><th><?php echo e(t('fm.created_by')); ?></th><th><?php echo e(t('fm.budget')); ?></th><th><?php echo e(t('fm.items')); ?></th><th><?php echo e(t('fm.submission_date')); ?></th><th><?php echo e(t('fm.funding_source_distribution')); ?></th><th><?php echo e(t('fm.action')); ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($projectApprovalQueue as $projectRequest): ?>
                                    <tr>
                                        <td><strong><?php echo e($projectRequest['project_name']); ?></strong><br><small class="text-muted"><code><?php echo e($projectRequest['project_code'] ?? ''); ?></code></small></td>
                                        <td><?php echo e($projectRequest['project_type'] ?? '-'); ?></td>
                                        <td><?php echo e($projectRequest['submitted_by_name'] ?? '-'); ?></td>
                                        <td><strong><?php echo number_format((float)$projectRequest['budget_amount'], 2); ?> <?php echo e($projectRequest['currency_code'] ?: t('fm.currency_sdg')); ?></strong><br><small class="text-muted"><?php echo e(t('fm.version', ['version' => (int)$projectRequest['budget_version']])); ?></small></td>
                                        <td><?php echo (int)$projectRequest['budget_line_count']; ?></td>
                                        <td><?php echo e($projectRequest['submitted_at'] ?? '-'); ?></td>
                                        <td style="min-width:260px">
                                            <?php $approvalFormId = 'fm-project-' . (int)$projectRequest['project_id']; ?>
                                            <?php foreach ($fundingAccounts as $fundingAccount): $available = max(0, round((float)$fundingAccount['ledger_balance'] - (float)$fundingAccount['reserved_amount'], 2)); ?>
                                                <label style="display:block; margin-bottom:5px; font-size:.8rem">
                                                    <span><?php echo e($fundingAccount['code'] . ' — ' . ($fundingAccount['name_ar'] ?: $fundingAccount['name_en'])); ?> (<?php echo e(t('fm.available', ['amount' => number_format($available, 2)])); ?>)</span>
                                                    <input form="<?php echo e($approvalFormId); ?>" type="number" step="0.01" min="0" max="<?php echo e((string)$available); ?>" name="funding_amounts[<?php echo (int)$projectRequest['project_id']; ?>][<?php echo (int)$fundingAccount['id']; ?>]" class="form-control form-control-sm" value="0" placeholder="<?php echo e(t('fm.amount_from_account')); ?>">
                                                </label>
                                            <?php endforeach; ?>
                                        </td>
                                        <td style="white-space:nowrap">
                                            <a class="btn-fm btn-navy" href="<?php echo APP_URL; ?>modules/projects/view.php?id=<?php echo (int)$projectRequest['project_id']; ?>"><?php echo e(t('fm.view_project')); ?></a>
                                            <form id="<?php echo e($approvalFormId); ?>" method="post" style="display:inline-block" onsubmit="return confirm('<?php echo e(t('fm.approve_project_confirm')); ?>');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="project_fm_review" value="1">
                                                <input type="hidden" name="project_id" value="<?php echo (int)$projectRequest['project_id']; ?>">
                                                <input type="hidden" name="project_fm_decision" value="approve">
                                                <button class="btn-fm btn-navy" type="submit"><?php echo e(t('fm.financial_approval')); ?></button>
                                            </form>
                                            <form method="post" style="display:inline-block" onsubmit="var r=prompt('<?php echo e(t('fm.reject_reason_prompt')); ?>'); if (!r || !r.trim()) return false; this.fm_rejection_reason.value=r; return true;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="project_fm_review" value="1">
                                                <input type="hidden" name="project_id" value="<?php echo (int)$projectRequest['project_id']; ?>">
                                                <input type="hidden" name="project_fm_decision" value="reject">
                                                <input type="hidden" name="fm_rejection_reason" value="">
                                                <button class="btn-fm btn-ghost" type="submit"><?php echo e(t('fm.reject')); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<!-- ══════════ MONTHLY FLOW ══════════ -->
<div class="grid-2">
    <div class="fm-card"><div class="fm-card-head"><span>📈 <?php echo e(t('fm.monthly_income', ['month' => date('Y-m')])); ?></span><span class="badge-fm badge-green"><?php echo e(t('fm.income')); ?></span></div><div class="fm-card-body"><div class="flow-row"><span class="flow-label"><?php echo e(t('fm.total_income')); ?></span><span class="flow-value income"><?php echo number_format((float)$monthlyFlow['income_total'], 0); ?></span></div><div class="flow-row"><span class="flow-label">💵 <?php echo e(t('fm.cash_income')); ?></span><span class="flow-value income"><?php echo number_format((float)$monthlyFlow['income_cash'], 0); ?></span></div><div class="flow-row"><span class="flow-label">🏦 <?php echo e(t('fm.bank_income')); ?></span><span class="flow-value income"><?php echo number_format((float)$monthlyFlow['income_bank'], 0); ?></span></div></div></div>
    <div class="fm-card"><div class="fm-card-head"><span>📉 <?php echo e(t('fm.monthly_expenses', ['month' => date('Y-m')])); ?></span><span class="badge-fm badge-red"><?php echo e(t('fm.outgoing')); ?></span></div><div class="fm-card-body"><div class="flow-row"><span class="flow-label"><?php echo e(t('fm.total_expenses')); ?></span><span class="flow-value outgoing"><?php echo number_format((float)$monthlyFlow['outgoing_total'], 0); ?></span></div><div class="flow-row"><span class="flow-label">💵 <?php echo e(t('fm.cash_expense')); ?></span><span class="flow-value outgoing"><?php echo number_format((float)$monthlyFlow['outgoing_cash'], 0); ?></span></div><div class="flow-row"><span class="flow-label">🏦 <?php echo e(t('fm.bank_expense')); ?></span><span class="flow-value outgoing"><?php echo number_format((float)$monthlyFlow['outgoing_bank'], 0); ?></span></div></div></div>
</div>

<div class="fm-card"><div class="fm-card-head"><span>📊 <?php echo e(t('fm.net_monthly_flow')); ?></span></div><div class="fm-card-body"><?php $netFlow = (float)$monthlyFlow['income_total'] - (float)$monthlyFlow['outgoing_total']; $netClass = $netFlow >= 0 ? 'income' : 'outgoing'; $netLabel = $netFlow >= 0 ? t('fm.surplus') : t('fm.deficit'); ?><div style="display:flex; justify-content:space-around; align-items:center; flex-wrap:wrap; gap:20px"><div style="text-align:center"><div class="stat-label"><?php echo e(t('fm.income')); ?></div><div class="stat-value income"><?php echo number_format((float)$monthlyFlow['income_total'], 0); ?></div></div><div style="font-size:2rem; color:#999">−</div><div style="text-align:center"><div class="stat-label"><?php echo e(t('fm.total_expenses')); ?></div><div class="stat-value outgoing"><?php echo number_format((float)$monthlyFlow['outgoing_total'], 0); ?></div></div><div style="font-size:2rem; color:#999">=</div><div style="text-align:center"><div class="stat-label"><?php echo e(t('fm.net_result', ['status' => $netLabel])); ?></div><div class="stat-value <?php echo $netClass; ?>"><?php echo number_format(abs($netFlow), 0); ?></div></div></div></div></div>

<div class="fm-card"><div class="fm-card-head"><span>📋 <?php echo e(t('fm.monthly_disbursement_status')); ?></span><a href="<?php echo APP_URL; ?>modules/accounting/disbursements.php" class="btn-fm btn-ghost" style="background:#fff; color:#1b4d8f; font-size:0.8rem"><?php echo e(t('fm.view_all')); ?></a></div><div class="fm-card-body"><div class="grid-4"><div class="stat-box amber"><div class="stat-value"><?php echo (int)$disbStats['pending_approval']; ?></div><div class="stat-label"><?php echo e(t('fm.pending_approval')); ?></div><div class="stat-sub"><?php echo number_format((float)$disbStats['pending_amount'], 0); ?> <?php echo e(t('fm.currency_sdg')); ?></div></div><div class="stat-box blue"><div class="stat-value"><?php echo (int)$disbStats['transferred']; ?></div><div class="stat-label"><?php echo e(t('fm.transferred_open')); ?></div><div class="stat-sub"><?php echo number_format((float)$disbStats['transferred_amount'], 0); ?> <?php echo e(t('fm.currency_sdg')); ?></div></div><div class="stat-box green"><div class="stat-value"><?php echo (int)$disbStats['received']; ?></div><div class="stat-label"><?php echo e(t('fm.received_closed')); ?></div><div class="stat-sub"><?php echo e(t('fm.fully_disbursed')); ?></div></div><div class="stat-box red"><div class="stat-value"><?php echo (int)$disbStats['voided']; ?></div><div class="stat-label"><?php echo e(t('fm.voided')); ?></div><div class="stat-sub"><?php echo e(t('fm.posted_reversal')); ?></div></div></div></div></div>

<?php if ($pendingQueue): ?><div class="fm-card"><div class="fm-card-head"><span>⏳ <?php echo e(t('fm.disbursement_approval_queue', ['count' => count($pendingQueue)])); ?></span></div><div class="fm-card-body"><div style="overflow-x:auto"><table class="fm-table"><thead><tr><th>#</th><th><?php echo e(t('common.month')); ?></th><th><?php echo e(t('fm.nanny')); ?></th><th><?php echo e(t('fm.created_by')); ?></th><th><?php echo e(t('accounting.amount')); ?></th><th><?php echo e(t('fm.submission_date')); ?></th><th><?php echo e(t('fm.action')); ?></th></tr></thead><tbody><?php foreach ($pendingQueue as $pq): ?><tr><td><strong>#<?php echo (int)$pq['id']; ?></strong></td><td><?php echo e($pq['month']); ?></td><td><?php echo e($pq['nanny_name'] ?? '-'); ?></td><td><?php echo e($pq['created_by_name'] ?? '-'); ?></td><td><strong><?php echo number_format((float)$pq['total_amount'], 0); ?></strong></td><td><?php echo e($pq['submitted_at'] ?? '-'); ?></td><td><a href="<?php echo APP_URL; ?>modules/accounting/disbursements.php?view=<?php echo (int)$pq['id']; ?>" class="btn-fm btn-navy"><?php echo e(t('fm.review')); ?></a></td></tr><?php endforeach; ?></tbody></table></div></div></div><?php endif; ?>

<?php if ($openBatches): ?><div class="fm-card"><div class="fm-card-head"><span>🔓 <?php echo e(t('fm.open_disbursements')); ?></span><span class="badge-fm badge-blue"><?php echo e(t('fm.disbursement_count', ['count' => count($openBatches)])); ?></span></div><div class="fm-card-body"><div style="overflow-x:auto"><table class="fm-table"><thead><tr><th>#</th><th><?php echo e(t('common.month')); ?></th><th><?php echo e(t('fm.nanny')); ?></th><th><?php echo e(t('fm.items')); ?></th><th><?php echo e(t('accounting.amount')); ?></th><th><?php echo e(t('fm.transfer_date')); ?></th><th><?php echo e(t('fm.days_open')); ?></th><th><?php echo e(t('fm.action')); ?></th></tr></thead><tbody><?php foreach ($openBatches as $ob): $daysOpen = $ob['transferred_at'] ? floor((time() - strtotime($ob['transferred_at'])) / 86400) : 0; $agingClass = $daysOpen > 14 ? 'badge-red' : ($daysOpen > 7 ? 'badge-amber' : 'badge-blue'); ?><tr><td><strong>#<?php echo (int)$ob['id']; ?></strong></td><td><?php echo e($ob['month']); ?></td><td><?php echo e($ob['nanny_name'] ?? '-'); ?></td><td><span class="badge-fm badge-green"><?php echo e(t('fm.paid_count', ['count' => (int)$ob['paid_items']])); ?></span><?php if ((int)$ob['pending_items'] > 0): ?> <span class="badge-fm badge-amber"><?php echo e(t('fm.pending_count', ['count' => (int)$ob['pending_items']])); ?></span><?php endif; ?><?php if ((int)$ob['return_items'] > 0): ?> <span class="badge-fm badge-red"><?php echo e(t('fm.return_count', ['count' => (int)$ob['return_items']])); ?></span><?php endif; ?></td><td><strong><?php echo number_format((float)$ob['total_amount'], 0); ?></strong></td><td><?php echo e($ob['transferred_at'] ?? '-'); ?></td><td><span class="badge-fm <?php echo $agingClass; ?>"><?php echo e(t('fm.day_count', ['count' => $daysOpen])); ?></span></td><td><a href="<?php echo APP_URL; ?>modules/accounting/disbursements.php?view=<?php echo (int)$ob['id']; ?>" class="btn-fm btn-ghost"><?php echo e(t('fm.details')); ?></a></td></tr><?php endforeach; ?></tbody></table></div></div></div><?php endif; ?>

<div class="grid-2">
<?php if ($recentReturns): ?><div class="fm-card"><div class="fm-card-head"><span><?php echo e(t('fm.recent_returns')); ?></span><span class="badge-fm badge-amber"><?php echo count($recentReturns); ?></span></div><div class="fm-card-body"><div style="overflow-x:auto"><table class="fm-table"><thead><tr><th><?php echo e(t('fm.entry')); ?></th><th><?php echo e(t('fm.date')); ?></th><th><?php echo e(t('fm.family')); ?></th><th><?php echo e(t('accounting.amount')); ?></th></tr></thead><tbody><?php foreach ($recentReturns as $rr): ?><tr><td><code><?php echo e($rr['entry_code']); ?></code></td><td><?php echo e($rr['entry_date']); ?></td><td><?php echo e($rr['family_code'] ?? '-'); ?></td><td><strong class="flow-value outgoing"><?php echo number_format((float)$rr['amount'], 0); ?></strong></td></tr><?php endforeach; ?></tbody></table></div></div></div><?php endif; ?>
<?php if ($recentVoids): ?><div class="fm-card"><div class="fm-card-head"><span><?php echo e(t('fm.recent_voided')); ?></span><span class="badge-fm badge-red"><?php echo count($recentVoids); ?></span></div><div class="fm-card-body"><div style="overflow-x:auto"><table class="fm-table"><thead><tr><th>#</th><th><?php echo e(t('common.month')); ?></th><th><?php echo e(t('fm.nanny')); ?></th><th><?php echo e(t('accounting.amount')); ?></th><th><?php echo e(t('fm.reason')); ?></th></tr></thead><tbody><?php foreach ($recentVoids as $rv): ?><tr><td><strong>#<?php echo (int)$rv['id']; ?></strong></td><td><?php echo e($rv['month']); ?></td><td><?php echo e($rv['nanny_name'] ?? '-'); ?></td><td><strong><?php echo number_format((float)$rv['total_amount'], 0); ?></strong></td><td title="<?php echo e($rv['void_reason'] ?? ''); ?>"><?php echo e(mb_substr($rv['void_reason'] ?? '-', 0, 30)); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div><?php endif; ?>
</div>

<div class="fm-card"><div class="fm-card-head"><span><?php echo e(t('fm.quick_stats')); ?></span></div><div class="fm-card-body"><div class="grid-4"><div class="stat-box"><div class="stat-value"><?php echo (int)$quickStats['active_sponsorships']; ?></div><div class="stat-label"><?php echo e(t('fm.active_sponsorships')); ?></div></div><div class="stat-box blue"><div class="stat-value"><?php echo (int)$quickStats['active_families']; ?></div><div class="stat-label"><?php echo e(t('fm.active_families')); ?></div></div><div class="stat-box purple"><div class="stat-value"><?php echo (int)$quickStats['active_nannies']; ?></div><div class="stat-label"><?php echo e(t('fm.active_nannies')); ?></div></div><div class="stat-box green"><div class="stat-value"><?php echo number_format((float)$quickStats['received_this_month'], 0); ?></div><div class="stat-label"><?php echo e(t('fm.disbursed_this_month')); ?></div></div></div></div></div>

<div class="fm-card"><div class="fm-card-head"><span>📒 <?php echo e(t('fm.recent_journals')); ?></span><a href="<?php echo APP_URL; ?>modules/accounting/journal.php" class="btn-fm btn-ghost" style="background:#fff; color:#1b4d8f; font-size:0.8rem"><?php echo e(t('fm.view_all')); ?></a></div><div class="fm-card-body"><div style="overflow-x:auto"><table class="fm-table"><thead><tr><th><?php echo e(t('fm.entry_number')); ?></th><th><?php echo e(t('fm.date')); ?></th><th><?php echo e(t('fm.description')); ?></th><th><?php echo e(t('fm.debit')); ?></th><th><?php echo e(t('fm.credit')); ?></th><th><?php echo e(t('fm.status')); ?></th></tr></thead><tbody><?php foreach ($recentJournals as $rj): ?><tr><td><code><?php echo e($rj['entry_code']); ?></code></td><td><?php echo e($rj['entry_date']); ?></td><td><?php echo e(mb_substr($rj['description'] ?? '-', 0, 50)); ?></td><td><?php echo number_format((float)$rj['total_debit'], 0); ?></td><td><?php echo number_format((float)$rj['total_credit'], 0); ?></td><td><span class="badge-fm badge-green"><?php echo e($rj['status'] === 'posted' ? t('fm.status_posted') : ($rj['status'] === 'voided' ? t('fm.status_voided') : $rj['status'])); ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></div>

</main>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>