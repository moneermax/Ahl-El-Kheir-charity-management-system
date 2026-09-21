<?php
// modules/accounting/fm_dashboard.php — v2.1 Complete Financial Oversight + Project Budget Review
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

// FM dashboard-only navigation cards. These are rendered directly on this
// page and never moved out of, or into, the shared global header.
$headerQuickActions = [
    ['label'=>'التحويلات الشهرية','url'=>'modules/accounting/disbursements.php','icon'=>'fa-money-check-dollar','color'=>'#28a745'],
    ['label'=>' دليل الحسابات','url'=>'modules/accounting/accounts.php','icon'=>'fa-sitemap','color'=>'#2195c4'],
    ['label'=>'مراجعة ميزانيات المشاريع','url'=>'modules/accounting/fm_dashboard.php#project-budget-review','icon'=>'fa-clipboard-check','color'=>'#ffc107'],
    ['label'=>'مراجعة المعاملات المالية','url'=>'modules/accounting/fm_transaction_review.php','icon'=>'fa-file-invoice-dollar','color'=>'#6c757d'],
    ['label'=>'التقارير المالية','url'=>'modules/reports/financial.php','icon'=>'fa-chart-pie','color'=>'#0d6efd'],
    ['label'=>'سجل المعاملات','url'=>'modules/transactions/index.php','icon'=>'fa-money-bill-transfer','color'=>'#2daf79'],
    ['label'=>'منظمة فينا الخير','url'=>'modules/accounting/fina_dashboard.php','icon'=>'fina-logo','color'=>'#6f42c1'],
];

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

$monthStart = date('Y-m-01'); $monthEnd = date('Y-m-t');
$monthlyFlow = dbFetchOne("SELECT
    COALESCE(SUM(CASE WHEN a.account_type = 'revenue' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS income_total,
    COALESCE(SUM(CASE WHEN a.code = '4100' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS income_cash,
    COALESCE(SUM(CASE WHEN a.code = '4200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS income_bank,
    COALESCE(SUM(CASE WHEN a.account_type = 'expense' AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS outgoing_total,
    COALESCE(SUM(CASE WHEN a.code = '5100' AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS outgoing_cash,
    COALESCE(SUM(CASE WHEN a.code = '5200' AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS outgoing_bank
    FROM journal_lines jl JOIN journal_entries je ON je.id = jl.entry_id JOIN accounts a ON a.id = jl.account_id
    WHERE je.entry_date BETWEEN ? AND ? AND je.status = 'posted'", [$monthStart, $monthEnd]);

$adminFees = dbFetchOne("SELECT
    COALESCE(SUM(CASE WHEN a.code = '4200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS admin_fees_total,
    COALESCE(SUM(CASE WHEN je.entry_date BETWEEN ? AND ? AND a.code = '4200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS admin_fees_month
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
      )", [$monthStart, $monthEnd]);

$disbStats = dbFetchOne("SELECT COUNT(*) AS total,
    SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END) AS pending_approval,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status = 'transferred' THEN 1 ELSE 0 END) AS transferred,
    SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) AS received,
    SUM(CASE WHEN status = 'voided' THEN 1 ELSE 0 END) AS voided,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
    SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) AS returned,
    COALESCE(SUM(CASE WHEN status = 'pending_approval' THEN total_amount ELSE 0 END), 0) AS pending_amount,
    COALESCE(SUM(CASE WHEN status = 'transferred' THEN total_amount ELSE 0 END), 0) AS transferred_amount,
    COALESCE(SUM(CASE WHEN status = 'returned' THEN total_amount ELSE 0 END), 0) AS returned_amount
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

<style>
.fm-header { background: linear-gradient(135deg, #1b4d8f 0%, #2c5aa0 100%); color: #fff; padding: 16px 20px; border-radius: 14px; margin-top: -6px; margin-bottom: 16px; }
.fm-header h1 { margin: 0; font-size: 1.35rem; line-height: 1.25; }
.fm-header p { margin: 4px 0 0; opacity: 0.9; font-size: .82rem; line-height: 1.4; }
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
.fm-top-layout { margin-bottom: 20px; }
.project-review-list { display: grid; gap: 16px; }
.project-review-item { border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; overflow: hidden; box-shadow: 0 1px 5px rgba(0,0,0,.04); }
.project-review-item-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; padding:14px 16px; background:#fbfcfe; border-bottom:1px solid #edf0f4; }
.project-review-title { font-size:1rem; font-weight:700; color:#1b4d8f; }
.project-review-code { font-size:.78rem; color:#6c757d; margin-top:3px; }
.project-review-meta { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; padding:14px 16px; border-bottom:1px solid #edf0f4; }
.project-review-meta-item { min-width:0; }
.project-review-meta-label { display:block; color:#7a828b; font-size:.72rem; margin-bottom:3px; }
.project-review-meta-value { display:block; color:#26313d; font-size:.86rem; font-weight:700; overflow-wrap:anywhere; }
.project-review-funding { padding:14px 16px; background:#fcfcfd; border-bottom:1px solid #edf0f4; }
.project-review-funding-title { font-size:.84rem; font-weight:700; color:#1b4d8f; margin-bottom:10px; }
.project-review-funding-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
.project-review-account { border:1px solid #e5e7eb; border-radius:9px; padding:10px; background:#fff; }
.project-review-account label { display:block; margin:0; }
.project-review-account-name { display:block; font-size:.78rem; font-weight:700; color:#374151; margin-bottom:3px; }
.project-review-account-available { display:block; font-size:.7rem; color:#6c757d; margin-bottom:7px; }
.project-review-actions { display:flex; justify-content:flex-end; align-items:center; flex-wrap:wrap; gap:8px; padding:12px 16px; }
.project-review-actions form { margin:0; }

/* Original FM dashboard action-card design, now rendered server-side so
   the global header is never used as a temporary navigation source. */
.ak-fm-action-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin:0 auto 1.25rem; max-width:1180px; }
.ak-fm-action-card { min-height:104px; height:104px; border:0; border-radius:10px; box-shadow:0 2px 8px rgba(10,31,68,.08); text-decoration:none; color:inherit; background:#fff; display:flex; align-items:center; justify-content:center; transition:transform .18s,box-shadow .18s; }
.ak-fm-action-card:hover { transform:translateY(-3px); box-shadow:0 5px 14px rgba(10,31,68,.14); color:inherit; }
.ak-fm-action-icon { font-size:1.45rem; margin-bottom:.35rem; display:flex; align-items:center; justify-content:center; min-height:38px; }
.ak-fm-action-fina-logo { width:38px !important; height:38px !important; max-width:38px !important; max-height:38px !important; object-fit:contain; border-radius:7px; background:#fff; border:1px solid #e9ecef; padding:2px; display:block; margin:0 auto .35rem; box-sizing:border-box; }
.ak-fm-action-title { font-size:.82rem; font-weight:700; line-height:1.35; }
.ak-fm-action-desc { font-size:.66rem; line-height:1.3; color:#6c757d; margin-top:.18rem; }
.ak-fm-action-card.ak-fm-action-1 { border-top:3px solid #d3701f; }
.ak-fm-action-card.ak-fm-action-2 { border-top:3px solid #28a745; }
.ak-fm-action-card.ak-fm-action-3 { border-top:3px solid #2195c4; }
.ak-fm-action-card.ak-fm-action-4 { border-top:3px solid #ffc107; }
.ak-fm-action-card.ak-fm-action-5 { border-top:3px solid #0d6efd; }
.ak-fm-action-card.ak-fm-action-6 { border-top:3px solid #2daf79; }
.ak-fm-action-card.ak-fm-action-7 { border-top:3px solid #6f42c1; }
@media(max-width:991.98px) { .ak-fm-action-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:767.98px) { .ak-fm-action-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:420px) { .ak-fm-action-grid { grid-template-columns:1fr; } .ak-fm-action-card { height:96px; min-height:96px; } }

.fm-top-right { align-self: start; min-width: 0; }
.fm-top-right > .grid-4 { margin-bottom: 20px; }
.fm-top-right > .fm-card { margin-bottom: 0; }
@media (max-width: 991.98px) {
    .project-review-meta { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .project-review-funding-grid { grid-template-columns:1fr; }
}
@media (max-width: 575.98px) {
    .project-review-item-head { flex-direction:column; }
    .project-review-meta { grid-template-columns:1fr; }
    .project-review-actions { justify-content:stretch; }
    .project-review-actions .btn-fm,
    .project-review-actions form,
    .project-review-actions form .btn-fm { width:100%; text-align:center; }
}
</style>

<main class="container-fluid">
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

<?php
$fmActionDescriptions = [
    'متابعة التحويلات والصرف الشهري',
    'عرض وإدارة دليل الحسابات',
    'مراجعة واعتماد ميزانيات المشاريع',
    'مراجعة واعتماد المعاملات المالية',
    'عرض التقارير والحركة المالية',
    'مراجعة سجل المعاملات المالية',
    'لوحة مستقلة لتحصيلات وتسويات منظمة فينا الخير',
];
?>
<div class="ak-fm-action-grid fade-in" aria-label="إجراءات سريعة">
    <?php foreach ($headerQuickActions as $index => $qa): ?>
        <a href="<?php echo APP_URL . e($qa['url']); ?>" class="ak-fm-action-card ak-fm-action-<?php echo (int)$index + 1; ?>">
            <div class="text-center px-2">
                <div class="ak-fm-action-icon">
                    <?php if (($qa['icon'] ?? '') === 'fina-logo'): ?><img src="<?php echo APP_URL; ?>assets/img/Feen_logo.jpeg" alt="منظمة فينا الخير — Feena Al-Khair" class="ak-fm-action-fina-logo"><?php else: ?><i class="fas <?php echo e($qa['icon']); ?>" aria-hidden="true"></i><?php endif; ?>
                </div>
                <div class="ak-fm-action-title"><?php echo e($qa['label']); ?></div>
                <div class="ak-fm-action-desc"><?php echo e($fmActionDescriptions[$index] ?? ''); ?></div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

    <div class="fm-top-right">
        <!-- ══════════ TREASURY BALANCE ══════════ -->
        <div class="grid-4">
            <div class="stat-box"><div class="stat-label">💵 <?php echo e(t('fm.cash')); ?></div><div class="stat-value"><?php echo number_format((float)$treasury['cash'], 0); ?></div><div class="stat-sub"><?php echo e(t('fm.account_1100')); ?></div></div>
            <div class="stat-box blue"><div class="stat-label">🏦 <?php echo e(t('fm.bank')); ?></div><div class="stat-value"><?php echo number_format((float)$treasury['bank'], 0); ?></div><div class="stat-sub"><?php echo e(t('fm.account_1200')); ?></div></div>
            <div class="stat-box purple"><div class="stat-label">📱 <?php echo e(t('fm.wallet')); ?></div><div class="stat-value"><?php echo number_format((float)$treasury['wallet'], 0); ?></div><div class="stat-sub"><?php echo e(t('fm.account_1300')); ?></div></div>
            <div class="stat-box green"><div class="stat-label">💰 <?php echo e(t('fm.total_treasury')); ?></div><div class="stat-value"><?php echo number_format((float)$treasury['total'], 0); ?></div><div class="stat-sub"><?php echo e(t('fm.currency_sdg')); ?></div></div>
        </div>
<!-- ══════════ PROJECT BUDGETS PENDING FM REVIEW ══════════ -->
<div class="fm-top-layout">
    <div id="project-budget-review" class="fm-card" style="border-right:5px solid #ffc107;">
        <div class="fm-card-head">
            <span>📁 <?php echo e(t('fm.project_budget_review')); ?></span>
            <span class="badge-fm <?php echo $projectApprovalCount ? 'badge-amber' : 'badge-green'; ?>"><?php echo e(t('fm.request_count', ['count' => $projectApprovalCount])); ?></span>
        </div>
        <div class="fm-card-body">
            <?php if (!$projectApprovalQueue): ?>
                <div class="empty-state"><?php echo e(t('fm.no_project_budgets')); ?></div>
            <?php else: ?>
                <div class="project-review-list">
                    <?php foreach ($projectApprovalQueue as $projectRequest): ?>
                        <?php $approvalFormId = 'fm-project-' . (int)$projectRequest['project_id']; ?>
                        <section class="project-review-item">
                            <div class="project-review-item-head">
                                <div>
                                    <div class="project-review-title"><?php echo e($projectRequest['project_name']); ?></div>
                                    <div class="project-review-code"><code><?php echo e($projectRequest['project_code'] ?? ''); ?></code></div>
                                </div>
                                <span class="badge-fm badge-amber"><?php echo number_format((float)$projectRequest['budget_amount'], 2); ?> <?php echo e($projectRequest['currency_code'] ?: t('fm.currency_sdg')); ?></span>
                            </div>

                            <div class="project-review-meta">
                                <div class="project-review-meta-item">
                                    <span class="project-review-meta-label"><?php echo e(t('fm.project_type')); ?></span>
                                    <span class="project-review-meta-value"><?php echo e($projectRequest['project_type'] ?? '-'); ?></span>
                                </div>
                                <div class="project-review-meta-item">
                                    <span class="project-review-meta-label"><?php echo e(t('fm.created_by')); ?></span>
                                    <span class="project-review-meta-value"><?php echo e($projectRequest['submitted_by_name'] ?? '-'); ?></span>
                                </div>
                                <div class="project-review-meta-item">
                                    <span class="project-review-meta-label"><?php echo e(t('fm.budget')); ?></span>
                                    <span class="project-review-meta-value"><?php echo number_format((float)$projectRequest['budget_amount'], 2); ?> <?php echo e($projectRequest['currency_code'] ?: t('fm.currency_sdg')); ?></span>
                                </div>
                                <div class="project-review-meta-item">
                                    <span class="project-review-meta-label"><?php echo e(t('fm.items')); ?></span>
                                    <span class="project-review-meta-value"><?php echo (int)$projectRequest['budget_line_count']; ?> · <?php echo e(t('fm.version', ['version' => (int)$projectRequest['budget_version']])); ?></span>
                                </div>
                                <div class="project-review-meta-item">
                                    <span class="project-review-meta-label"><?php echo e(t('fm.submission_date')); ?></span>
                                    <span class="project-review-meta-value"><?php echo e($projectRequest['submitted_at'] ?? '-'); ?></span>
                                </div>
                            </div>

                            <div class="project-review-funding">
                                <div class="project-review-funding-title"><?php echo e(t('fm.funding_source_distribution')); ?></div>
                                <div class="project-review-funding-grid">
                                    <?php foreach ($fundingAccounts as $fundingAccount): $available = max(0, round((float)$fundingAccount['ledger_balance'] - (float)$fundingAccount['reserved_amount'], 2)); ?>
                                        <div class="project-review-account">
                                            <label>
                                                <span class="project-review-account-name"><?php echo e($fundingAccount['code'] . ' — ' . ($fundingAccount['name_ar'] ?: $fundingAccount['name_en'])); ?></span>
                                                <span class="project-review-account-available"><?php echo e(t('fm.available', ['amount' => number_format($available, 2)])); ?></span>
                                                <input form="<?php echo e($approvalFormId); ?>" type="number" step="0.01" min="0" max="<?php echo e((string)$available); ?>" name="funding_amounts[<?php echo (int)$projectRequest['project_id']; ?>][<?php echo (int)$fundingAccount['id']; ?>]" class="form-control form-control-sm" value="0" placeholder="<?php echo e(t('fm.amount_from_account')); ?>">
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="project-review-actions">
                                <a class="btn-fm btn-ghost" href="<?php echo APP_URL; ?>modules/projects/view.php?id=<?php echo (int)$projectRequest['project_id']; ?>"><?php echo e(t('fm.view_project')); ?></a>
                                <form id="<?php echo e($approvalFormId); ?>" method="post" onsubmit="return confirm('<?php echo e(t('fm.approve_project_confirm')); ?>');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="project_fm_review" value="1">
                                    <input type="hidden" name="project_id" value="<?php echo (int)$projectRequest['project_id']; ?>">
                                    <input type="hidden" name="project_fm_decision" value="approve">
                                    <button class="btn-fm btn-navy" type="submit"><?php echo e(t('fm.financial_approval')); ?></button>
                                </form>
                                <form method="post" onsubmit="var r=prompt('<?php echo e(t('fm.reject_reason_prompt')); ?>'); if (!r || !r.trim()) return false; this.fm_rejection_reason.value=r; return true;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="project_fm_review" value="1">
                                    <input type="hidden" name="project_id" value="<?php echo (int)$projectRequest['project_id']; ?>">
                                    <input type="hidden" name="project_fm_decision" value="reject">
                                    <input type="hidden" name="fm_rejection_reason" value="">
                                    <button class="btn-fm btn-ghost" type="submit"><?php echo e(t('fm.reject')); ?></button>
                                </form>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══════════ MONTHLY FLOW ══════════ -->
<div class="grid-2">
    <div class="fm-card"><div class="fm-card-head"><span>📈 <?php echo e(t('fm.monthly_income', ['month' => date('Y-m')])); ?></span><span class="badge-fm badge-green"><?php echo e(t('fm.income')); ?></span></div><div class="fm-card-body"><div class="flow-row"><span class="flow-label"><?php echo e(t('fm.total_income')); ?></span><span class="flow-value income"><?php echo number_format((float)$monthlyFlow['income_total'], 0); ?></span></div><div class="flow-row"><span class="flow-label">💵 <?php echo e(t('fm.cash_income')); ?></span><span class="flow-value income"><?php echo number_format((float)$monthlyFlow['income_cash'], 0); ?></span></div><div class="flow-row"><span class="flow-label">🏦 <?php echo e(t('fm.bank_income')); ?></span><span class="flow-value income"><?php echo number_format((float)$monthlyFlow['income_bank'], 0); ?></span></div></div></div>
    <div class="fm-card"><div class="fm-card-head"><span>📉 <?php echo e(t('fm.monthly_expenses', ['month' => date('Y-m')])); ?></span><span class="badge-fm badge-red"><?php echo e(t('fm.outgoing')); ?></span></div><div class="fm-card-body"><div class="flow-row"><span class="flow-label"><?php echo e(t('fm.total_expenses')); ?></span><span class="flow-value outgoing"><?php echo number_format((float)$monthlyFlow['outgoing_total'], 0); ?></span></div><div class="flow-row"><span class="flow-label">💵 <?php echo e(t('fm.cash_expense')); ?></span><span class="flow-value outgoing"><?php echo number_format((float)$monthlyFlow['outgoing_cash'], 0); ?></span></div><div class="flow-row"><span class="flow-label">🏦 <?php echo e(t('fm.bank_expense')); ?></span><span class="flow-value outgoing"><?php echo number_format((float)$monthlyFlow['outgoing_bank'], 0); ?></span></div></div></div>
</div>

<div class="fm-card"><div class="fm-card-head"><span>📊 <?php echo e(t('fm.net_monthly_flow')); ?></span></div><div class="fm-card-body"><?php $netFlow = (float)$monthlyFlow['income_total'] - (float)$monthlyFlow['outgoing_total']; $netClass = $netFlow >= 0 ? 'income' : 'outgoing'; $netLabel = $netFlow >= 0 ? t('fm.surplus') : t('fm.deficit'); ?><div style="display:flex; justify-content:space-around; align-items:center; flex-wrap:wrap; gap:20px"><div style="text-align:center"><div class="stat-label"><?php echo e(t('fm.income')); ?></div><div class="stat-value income"><?php echo number_format((float)$monthlyFlow['income_total'], 0); ?></div></div><div style="font-size:2rem; color:#999">−</div><div style="text-align:center"><div class="stat-label"><?php echo e(t('fm.total_expenses')); ?></div><div class="stat-value outgoing"><?php echo number_format((float)$monthlyFlow['outgoing_total'], 0); ?></div></div><div style="font-size:2rem; color:#999">=</div><div style="text-align:center"><div class="stat-label"><?php echo e(t('fm.net_result', ['status' => $netLabel])); ?></div><div class="stat-value <?php echo $netClass; ?>"><?php echo number_format(abs($netFlow), 0); ?></div></div></div></div></div>

<div class="fm-card">
    <div class="fm-card-head"><span>💳 الرسوم الإدارية المحصلة</span></div>
    <div class="fm-card-body">
        <div class="grid-4">
            <div class="stat-box blue">
                <div class="stat-value"><?php echo number_format((float)$adminFees['admin_fees_total'], 0); ?></div>
                <div class="stat-label">إجمالي الرسوم الإدارية المحصلة</div>
                <div class="stat-sub">الحساب 4200 — القيود المرحلة</div>
            </div>
            <div class="stat-box green">
                <div class="stat-value"><?php echo number_format((float)$adminFees['admin_fees_month'], 0); ?></div>
                <div class="stat-label">رسوم إدارية هذا الشهر</div>
                <div class="stat-sub"><?php echo e(date('Y-m')); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="fm-card"><div class="fm-card-head"><span>📋 <?php echo e(t('fm.monthly_disbursement_status')); ?></span><a href="<?php echo APP_URL; ?>modules/accounting/disbursements.php" class="btn-fm btn-ghost" style="background:#fff; color:#1b4d8f; font-size:0.8rem"><?php echo e(t('fm.view_all')); ?></a></div><div class="fm-card-body"><div class="grid-4"><div class="stat-box amber"><div class="stat-value"><?php echo (int)$disbStats['pending_approval']; ?></div><div class="stat-label"><?php echo e(t('fm.pending_approval')); ?></div><div class="stat-sub"><?php echo number_format((float)$disbStats['pending_amount'], 0); ?> <?php echo e(t('fm.currency_sdg')); ?></div></div><div class="stat-box blue"><div class="stat-value"><?php echo (int)$disbStats['transferred']; ?></div><div class="stat-label"><?php echo e(t('fm.transferred_open')); ?></div><div class="stat-sub"><?php echo number_format((float)$disbStats['transferred_amount'], 0); ?> <?php echo e(t('fm.currency_sdg')); ?></div></div><div class="stat-box green"><div class="stat-value"><?php echo (int)$disbStats['received']; ?></div><div class="stat-label"><?php echo e(t('fm.received_closed')); ?></div><div class="stat-sub"><?php echo e(t('fm.fully_disbursed')); ?></div></div><div class="stat-box red"><div class="stat-value"><?php echo (int)$disbStats['voided']; ?></div><div class="stat-label"><?php echo e(t('fm.voided')); ?></div><div class="stat-sub"><?php echo e(t('fm.posted_reversal')); ?></div></div><div class="stat-box amber"><div class="stat-value"><?php echo (int)$disbStats['returned']; ?></div><div class="stat-label">المبالغ المرتجعة</div><div class="stat-sub"><?php echo number_format((float)$disbStats['returned_amount'], 0); ?> <?php echo e(t('fm.currency_sdg')); ?></div></div></div></div></div>

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
