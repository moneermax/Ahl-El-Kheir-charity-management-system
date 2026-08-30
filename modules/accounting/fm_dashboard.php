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
    flash('error', 'غير مصرح لك بعرض هذه الصفحة.');
    header('Location: ' . APP_URL . 'dashboard/staff_dashboard.php');
    exit;
}

$active = 'fm_dashboard';
$pageTitle = 'لوحة المدير المالي';

ak_ensure_tables();
ak_seed_accounts();
ak_out_ensure_schema();

/* ══════════ PROJECT BUDGET FIRST-LEVEL APPROVAL ══════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_fm_review'])) {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (!in_array($urole, ['financial_manager', 'admin', 'fm'], true)) {
        flash('error', 'اعتماد ميزانية المشروع متاح للمدير المالي فقط.');
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
            throw new RuntimeException('المشروع غير موجود في طابور المراجعة المالية.');
        }
        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new RuntimeException('قرار المراجعة غير صالح.');
        }
        if ($decision === 'reject' && $fmReason === '') {
            throw new RuntimeException('سبب رفض الميزانية مطلوب.');
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
            if ($budgetAmount <= 0) throw new RuntimeException('لا يمكن اعتماد مشروع دون مبلغ ميزانية مقترح.');
            $accountIds = array_map('intval', array_keys($postedFundingAmounts));
            $allowedAccounts = dbFetchAll("SELECT a.id, a.code,
                    COALESCE(SUM(jl.debit - jl.credit), 0) AS ledger_balance,
                    COALESCE((SELECT SUM(f.amount) FROM project_funding_allocations f WHERE f.source_account_id = a.id AND f.status = 'approved'), 0) AS reserved_amount
                FROM accounts a LEFT JOIN journal_lines jl ON jl.account_id = a.id
                LEFT JOIN journal_entries je ON je.id = jl.entry_id AND je.status = 'posted'
                WHERE a.code IN ('1100','1200','1300') AND a.is_active = 1
                GROUP BY a.id, a.code ORDER BY a.code");
            $accountsById = [];
            foreach ($allowedAccounts as $account) $accountsById[(int)$account['id']] = $account;
            $allocationTotal = 0.0; $allocationRows = [];
            foreach ($postedFundingAmounts as $accountId => $rawAmount) {
                $accountId = (int)$accountId; $amount = round((float)$rawAmount, 2);
                if ($amount <= 0) continue;
                if (!isset($accountsById[$accountId])) throw new RuntimeException('حساب مصدر التمويل غير صالح.');
                $available = round((float)$accountsById[$accountId]['ledger_balance'] - (float)$accountsById[$accountId]['reserved_amount'], 2);
                if ($amount > $available) throw new RuntimeException('الرصيد المتاح غير كافٍ في الحساب ' . $accountsById[$accountId]['code'] . '. المتاح: ' . number_format($available, 2));
                $allocationTotal += $amount;
                $allocationRows[] = [$accountId, $accountsById[$accountId]['code'], $amount];
            }
            if (round($allocationTotal, 2) !== $budgetAmount) throw new RuntimeException('يجب أن يساوي مجموع مصادر التمويل الميزانية المقترحة: ' . number_format($budgetAmount, 2));
            $expenseAccount = dbFetchOne("SELECT id FROM accounts WHERE code = '5110' AND is_active = 1 LIMIT 1");
            dbExecute('DELETE FROM project_funding_allocations WHERE project_id = ? AND status = \'approved\' AND journal_entry_id IS NULL', [$projectId]);
            foreach ($allocationRows as $allocation) {
                $sourceType = ['1100' => 'treasury', '1200' => 'bank', '1300' => 'electronic_wallet'][$allocation[1]] ?? 'treasury';
                dbExecute('INSERT INTO project_funding_allocations (project_id, budget_id, source_type, source_account_id, destination_account_id, amount, currency_code, allocation_date, reference_number, description, status, approved_by, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [$projectId, $budgetRow['budget_id'] ?: null, $sourceType, $allocation[0], $expenseAccount['id'] ?? null, $allocation[2], $approval['currency_code'] ?? 'SDG', date('Y-m-d'), 'FM-PRJ-' . $projectId, 'اعتماد مالي أولي لميزانية المشروع', 'approved', $uid, $uid]);
            }
            /* The latest draft budget becomes the budget reviewed by Finance. */
            dbExecute("UPDATE project_budgets
                       SET status = 'approved', approved_by = ?, approved_at = NOW()
                       WHERE project_id = ? AND status = 'draft'
                       ORDER BY version_no DESC LIMIT 1", [$uid, $projectId]);
            dbExecute("UPDATE project_approval
                       SET approval_status = 'fm_approved', fm_reviewed_by = ?,
                           fm_reviewed_at = NOW(), fm_rejection_reason = NULL
                       WHERE project_id = ? AND approval_status = 'submitted'", [$uid, $projectId]);
            $auditAction = 'FM_APPROVE_PROJECT_BUDGET';
            $auditNew = ['approval_status' => 'fm_approved'];
            $message = 'تم اعتماد ميزانية المشروع مبدئياً، وأصبح المشروع جاهزاً للاعتماد النهائي من المدير العام.';
        } else {
            dbExecute("UPDATE project_approval
                       SET approval_status = 'rejected', fm_reviewed_by = ?,
                           fm_reviewed_at = NOW(), fm_rejection_reason = ?
                       WHERE project_id = ? AND approval_status = 'submitted'", [$uid, $fmReason, $projectId]);
            $auditAction = 'FM_REJECT_PROJECT_BUDGET';
            $auditNew = ['approval_status' => 'rejected', 'fm_rejection_reason' => $fmReason];
            $message = 'تم رفض ميزانية المشروع وإعادتها إلى مدير المشاريع.';
        }

        dbExecute("INSERT INTO audit_log
                   (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                   VALUES (?, ?, 'project_approval', ?, ?, ?, ?, ?)", [
            $uid,
            $auditAction,
            $projectId,
            json_encode(['approval_status' => 'submitted'], JSON_UNESCAPED_UNICODE),
            json_encode($auditNew, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
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
        p.id AS project_id,
        p.project_code,
        p.name AS project_name,
        p.project_type,
        p.currency_code,
        p.start_date,
        p.end_date,
        pa.submitted_at,
        submitter.full_name AS submitted_by_name,
        COALESCE(SUM(COALESCE(bl.approved_amount, bl.estimated_amount)), 0) AS budget_amount,
        b.version_no AS budget_version,
        COUNT(bl.id) AS budget_line_count
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
        COALESCE(SUM(jl.debit - jl.credit), 0) AS ledger_balance,
        COALESCE((SELECT SUM(f.amount) FROM project_funding_allocations f WHERE f.source_account_id = a.id AND f.status = 'approved'), 0) AS reserved_amount
    FROM accounts a LEFT JOIN journal_lines jl ON jl.account_id = a.id
    LEFT JOIN journal_entries je ON je.id = jl.entry_id AND je.status = 'posted'
    WHERE a.code IN ('1100','1200','1300') AND a.is_active = 1
    GROUP BY a.id, a.code, a.name_ar, a.name_en ORDER BY a.code");

/* ══════════ 1. TREASURY BALANCE (Cash, Bank, Wallet) ══════════ */
$treasury = dbFetchOne("SELECT
    COALESCE(SUM(CASE WHEN a.code = '1100' THEN jl.debit - jl.credit ELSE 0 END), 0) AS cash,
    COALESCE(SUM(CASE WHEN a.code = '1200' THEN jl.debit - jl.credit ELSE 0 END), 0) AS bank,
    COALESCE(SUM(CASE WHEN a.code = '1300' THEN jl.debit - jl.credit ELSE 0 END), 0) AS wallet,
    COALESCE(SUM(jl.debit - jl.credit), 0) AS total
    FROM journal_lines jl
    JOIN accounts a ON a.id = jl.account_id
    JOIN journal_entries je ON je.id = jl.entry_id
    WHERE a.code IN ('1100','1200','1300') AND a.is_active = 1 AND je.status = 'posted'");

/* ══════════ 2. MONTHLY FLOW (This Month) ══════════ */
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$monthlyFlow = dbFetchOne("SELECT
    COALESCE(SUM(CASE WHEN a.account_type = 'revenue' AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS income_total,
    COALESCE(SUM(CASE WHEN a.account_type = 'revenue' AND a.code = '4100' AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS income_cash,
    COALESCE(SUM(CASE WHEN a.account_type = 'revenue' AND a.code = '4200' AND jl.debit > 0 THEN jl.debit ELSE 0 END), 0) AS income_bank,
    COALESCE(SUM(CASE WHEN a.account_type = 'expense' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS outgoing_total,
    COALESCE(SUM(CASE WHEN a.account_type = 'expense' AND a.code = '5100' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS outgoing_cash,
    COALESCE(SUM(CASE WHEN a.account_type = 'expense' AND a.code = '5200' AND jl.credit > 0 THEN jl.credit ELSE 0 END), 0) AS outgoing_bank
    FROM journal_lines jl
    JOIN journal_entries je ON je.id = jl.entry_id
    JOIN accounts a ON a.id = jl.account_id
    WHERE je.entry_date BETWEEN ? AND ? AND je.status = 'posted'", [$monthStart, $monthEnd]);

/* ══════════ 3. DISBURSEMENT STATUS ══════════ */
$disbStats = dbFetchOne("SELECT
    COUNT(*) AS total,
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

/* ══════════ 4. OPEN BATCHES (Transferred but not received) ══════════ */
$openBatches = dbFetchAll("SELECT d.*, u.full_name AS nanny_name,
    (SELECT COUNT(*) FROM disbursement_items WHERE disbursement_id = d.id) AS total_items,
    (SELECT COUNT(*) FROM disbursement_items WHERE disbursement_id = d.id AND status = 'paid') AS paid_items,
    (SELECT COUNT(*) FROM disbursement_items WHERE disbursement_id = d.id AND status = 'pending') AS pending_items,
    (SELECT COUNT(*) FROM disbursement_items WHERE disbursement_id = d.id AND status = 'return_requested') AS return_items
    FROM monthly_disbursements d
    LEFT JOIN users u ON u.id = d.nanny_id
    WHERE d.status = 'transferred'
    ORDER BY d.transferred_at ASC");

/* ══════════ 5. RECENT RETURNS (JE-RET) ═════════ */
$recentReturns = dbFetchAll("SELECT je.id, je.entry_code, je.entry_date, je.description,
    SUM(CASE WHEN jl.credit > 0 THEN jl.credit ELSE 0 END) AS amount,
    di.family_id, f.family_code
    FROM journal_entries je
    JOIN journal_lines jl ON jl.entry_id = je.id
    LEFT JOIN disbursement_items di ON di.reversal_journal_id = je.id
    LEFT JOIN families f ON f.id = di.family_id
    WHERE je.entry_code LIKE 'JE-RET%' AND je.status = 'posted'
    GROUP BY je.id
    ORDER BY je.created_at DESC LIMIT 10");

/* ══════════ 6. RECENT VOIDED BATCHES ══════════ */
$recentVoids = dbFetchAll("SELECT d.id, d.month, d.total_amount, d.void_reason, d.voided_at,
    u.full_name AS voided_by_name, n.full_name AS nanny_name
    FROM monthly_disbursements d
    LEFT JOIN users u ON u.id = d.voided_by_user_id
    LEFT JOIN users n ON n.id = d.nanny_id
    WHERE d.status = 'voided'
    ORDER BY d.voided_at DESC LIMIT 10");

/* ══════════ 7. QUICK STATS ══════════ */
$quickStats = dbFetchOne("SELECT
    (SELECT COUNT(*) FROM sponsorships WHERE status = 'active') AS active_sponsorships,
    (SELECT COUNT(*) FROM families WHERE status = 'active') AS active_families,
    (SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'nanny' AND u.is_active = 1) AS active_nannies,
    (SELECT COALESCE(SUM(total_amount), 0) FROM monthly_disbursements WHERE status = 'received' AND month = DATE_FORMAT(NOW(), '%Y-%m')) AS received_this_month");

/* ══════════ 8. PENDING MONTHLY DISBURSEMENT QUEUE ══════════ */
$pendingQueue = dbFetchAll("SELECT d.*, u.full_name AS nanny_name, n.full_name AS created_by_name
    FROM monthly_disbursements d
    LEFT JOIN users u ON u.id = d.nanny_id
    LEFT JOIN users n ON n.id = d.created_by
    WHERE d.status = 'pending_approval'
    ORDER BY d.submitted_at ASC");

/* ══════════ 9. LAST 10 JOURNAL ENTRIES ══════════ */
$recentJournals = dbFetchAll("SELECT je.entry_code, je.entry_date, je.description, je.status,
    SUM(jl.debit) AS total_debit, SUM(jl.credit) AS total_credit
    FROM journal_entries je
    JOIN journal_lines jl ON jl.entry_id = je.id
    WHERE je.status = 'posted'
    GROUP BY je.id
    ORDER BY je.created_at DESC LIMIT 10");
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
</style>

<main class="container-fluid py-4">
<div class="fm-header">
    <h1>مرحباً، المدير المالي</h1>
    <p>نظرة شاملة على الخزينة والتدفقات المالية — <?php echo date('Y-m-d'); ?></p>
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



<!-- ══════════ TREASURY BALANCE ══════════ -->
<div class="grid-4">
    <div class="stat-box"><div class="stat-label">💵 الصندوق النقدي</div><div class="stat-value"><?php echo number_format((float)$treasury['cash'], 0); ?></div><div class="stat-sub">حساب 1100</div></div>
    <div class="stat-box blue"><div class="stat-label">🏦 الحساب البنكي</div><div class="stat-value"><?php echo number_format((float)$treasury['bank'], 0); ?></div><div class="stat-sub">حساب 1200</div></div>
    <div class="stat-box purple"><div class="stat-label">📱 المحفظة الإلكترونية</div><div class="stat-value"><?php echo number_format((float)$treasury['wallet'], 0); ?></div><div class="stat-sub">حساب 1300</div></div>
    <div class="stat-box green"><div class="stat-label">💰 إجمالي الخزينة</div><div class="stat-value"><?php echo number_format((float)$treasury['total'], 0); ?></div><div class="stat-sub">SDG</div></div>
</div>
<!-- ══════════ PROJECT BUDGETS PENDING FM REVIEW ══════════ -->
<div id="project-budget-review" class="fm-card" style="border-right:5px solid #ffc107;">
    <div class="fm-card-head">
        <span>📁 ميزانيات المشاريع بانتظار المراجعة المالية</span>
        <span class="badge-fm <?php echo $projectApprovalCount ? 'badge-amber' : 'badge-green'; ?>"><?php echo $projectApprovalCount; ?> طلب</span>
    </div>
    <div class="fm-card-body">
        <?php if (!$projectApprovalQueue): ?>
            <div class="empty-state">لا توجد ميزانيات مشاريع بانتظار المراجعة المالية.</div>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table class="fm-table">
                    <thead><tr><th>المشروع</th><th>النوع</th><th>أنشأه</th><th>الميزانية</th><th>البنود</th><th>تاريخ الإرسال</th><th>توزيع مصادر التمويل من قبل FM</th><th>الإجراء</th></tr></thead>
                    <tbody>
                    <?php foreach ($projectApprovalQueue as $projectRequest): ?>
                        <tr>
                            <td><strong><?php echo e($projectRequest['project_name']); ?></strong><br><small class="text-muted"><code><?php echo e($projectRequest['project_code'] ?? ''); ?></code></small></td>
                            <td><?php echo e($projectRequest['project_type'] ?? '-'); ?></td>
                            <td><?php echo e($projectRequest['submitted_by_name'] ?? '-'); ?></td>
                            <td><strong><?php echo number_format((float)$projectRequest['budget_amount'], 2); ?> <?php echo e($projectRequest['currency_code'] ?: 'SDG'); ?></strong><br><small class="text-muted">نسخة <?php echo (int)$projectRequest['budget_version']; ?></small></td>
                            <td><?php echo (int)$projectRequest['budget_line_count']; ?></td>
                            <td><?php echo e($projectRequest['submitted_at'] ?? '-'); ?></td>
                            <td style="min-width:260px">
                                <?php $approvalFormId = 'fm-project-' . (int)$projectRequest['project_id']; ?>
                                <?php foreach ($fundingAccounts as $fundingAccount): $available = max(0, round((float)$fundingAccount['ledger_balance'] - (float)$fundingAccount['reserved_amount'], 2)); ?>
                                    <label style="display:block; margin-bottom:5px; font-size:.8rem">
                                        <span><?php echo e($fundingAccount['code'] . ' — ' . ($fundingAccount['name_ar'] ?: $fundingAccount['name_en'])); ?> (متاح <?php echo number_format($available, 2); ?>)</span>
                                        <input form="<?php echo e($approvalFormId); ?>" type="number" step="0.01" min="0" max="<?php echo e((string)$available); ?>" name="funding_amounts[<?php echo (int)$projectRequest['project_id']; ?>][<?php echo (int)$fundingAccount['id']; ?>]" class="form-control form-control-sm" value="0" placeholder="المبلغ من هذا الحساب">
                                    </label>
                                <?php endforeach; ?>
                            </td>
                            <td style="white-space:nowrap">
                                <a class="btn-fm btn-navy" href="<?php echo APP_URL; ?>modules/projects/view.php?id=<?php echo (int)$projectRequest['project_id']; ?>">عرض المشروع</a>
                                <form id="<?php echo e($approvalFormId); ?>" method="post" style="display:inline-block" onsubmit="return confirm('اعتماد ميزانية هذا المشروع بعد حفظ توزيع مصادر التمويل؟');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="project_fm_review" value="1">
                                    <input type="hidden" name="project_id" value="<?php echo (int)$projectRequest['project_id']; ?>">
                                    <input type="hidden" name="project_fm_decision" value="approve">
                                    <button class="btn-fm btn-navy" type="submit">اعتماد مالي</button>
                                </form>
                                <form method="post" style="display:inline-block" onsubmit="var r=prompt('اكتب سبب رفض الميزانية:'); if (!r || !r.trim()) return false; this.fm_rejection_reason.value=r; return true;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="project_fm_review" value="1">
                                    <input type="hidden" name="project_id" value="<?php echo (int)$projectRequest['project_id']; ?>">
                                    <input type="hidden" name="project_fm_decision" value="reject">
                                    <input type="hidden" name="fm_rejection_reason" value="">
                                    <button class="btn-fm btn-ghost" type="submit">رفض</button>
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
<!-- ══════════ MONTHLY FLOW ══════════ -->
<div class="grid-2">
    <div class="fm-card"><div class="fm-card-head"><span>📈 إيرادات الشهر (<?php echo date('Y-m'); ?>)</span><span class="badge-fm badge-green">دخل</span></div><div class="fm-card-body"><div class="flow-row"><span class="flow-label">إجمالي الإيرادات</span><span class="flow-value income"><?php echo number_format((float)$monthlyFlow['income_total'], 0); ?></span></div><div class="flow-row"><span class="flow-label">💵 نقداً (4100)</span><span class="flow-value income"><?php echo number_format((float)$monthlyFlow['income_cash'], 0); ?></span></div><div class="flow-row"><span class="flow-label">🏦 بنكياً (4200)</span><span class="flow-value income"><?php echo number_format((float)$monthlyFlow['income_bank'], 0); ?></span></div></div></div>
    <div class="fm-card"><div class="fm-card-head"><span>📉 مصروفات الشهر (<?php echo date('Y-m'); ?>)</span><span class="badge-fm badge-red">خرج</span></div><div class="fm-card-body"><div class="flow-row"><span class="flow-label">إجمالي المصروفات</span><span class="flow-value outgoing"><?php echo number_format((float)$monthlyFlow['outgoing_total'], 0); ?></span></div><div class="flow-row"><span class="flow-label">💵 نقداً (5100)</span><span class="flow-value outgoing"><?php echo number_format((float)$monthlyFlow['outgoing_cash'], 0); ?></span></div><div class="flow-row"><span class="flow-label">🏦 بنكياً (5200)</span><span class="flow-value outgoing"><?php echo number_format((float)$monthlyFlow['outgoing_bank'], 0); ?></span></div></div></div>
</div>

<!-- Net Flow -->
<div class="fm-card"><div class="fm-card-head"><span>📊 صافي التدفق الشهري</span></div><div class="fm-card-body"><?php $netFlow = (float)$monthlyFlow['income_total'] - (float)$monthlyFlow['outgoing_total']; $netClass = $netFlow >= 0 ? 'income' : 'outgoing'; $netLabel = $netFlow >= 0 ? 'فائض' : 'عجز'; ?><div style="display:flex; justify-content:space-around; align-items:center; flex-wrap:wrap; gap:20px"><div style="text-align:center"><div class="stat-label">الإيرادات</div><div class="stat-value income"><?php echo number_format((float)$monthlyFlow['income_total'], 0); ?></div></div><div style="font-size:2rem; color:#999">−</div><div style="text-align:center"><div class="stat-label">المصروفات</div><div class="stat-value outgoing"><?php echo number_format((float)$monthlyFlow['outgoing_total'], 0); ?></div></div><div style="font-size:2rem; color:#999">=</div><div style="text-align:center"><div class="stat-label">الصافي (<?php echo $netLabel; ?>)</div><div class="stat-value <?php echo $netClass; ?>"><?php echo number_format(abs($netFlow), 0); ?></div></div></div></div></div>

<!-- ══════════ DISBURSEMENT STATUS ═════════ -->
<div class="fm-card"><div class="fm-card-head"><span>📋 حالة الدفعات الشهرية</span><a href="<?php echo APP_URL; ?>modules/accounting/disbursements.php" class="btn-fm btn-ghost" style="background:#fff; color:#1b4d8f; font-size:0.8rem">عرض الكل</a></div><div class="fm-card-body"><div class="grid-4"><div class="stat-box amber"><div class="stat-value"><?php echo (int)$disbStats['pending_approval']; ?></div><div class="stat-label">بانتظار الاعتماد</div><div class="stat-sub"><?php echo number_format((float)$disbStats['pending_amount'], 0); ?> SDG</div></div><div class="stat-box blue"><div class="stat-value"><?php echo (int)$disbStats['transferred']; ?></div><div class="stat-label">محوّلة (مفتوحة)</div><div class="stat-sub"><?php echo number_format((float)$disbStats['transferred_amount'], 0); ?> SDG</div></div><div class="stat-box green"><div class="stat-value"><?php echo (int)$disbStats['received']; ?></div><div class="stat-label">مستلمة (مغلقة)</div><div class="stat-sub">تم الصرف الكامل</div></div><div class="stat-box red"><div class="stat-value"><?php echo (int)$disbStats['voided']; ?></div><div class="stat-label">مُبطَلة</div><div class="stat-sub">قيد عكسي مُرحّل</div></div></div></div></div>

<!-- ═════════ PENDING MONTHLY DISBURSEMENTS ═════════ -->
<?php if ($pendingQueue): ?><div class="fm-card"><div class="fm-card-head"><span>⏳ طابور اعتماد الدفعات — <?php echo count($pendingQueue); ?> دفعة بانتظار مراجعتك</span></div><div class="fm-card-body"><div style="overflow-x:auto"><table class="fm-table"><thead><tr><th>#</th><th>الشهر</th><th>الأخصائية</th><th>أنشأها</th><th>المبلغ</th><th>تاريخ الإرسال</th><th>إجراء</th></tr></thead><tbody><?php foreach ($pendingQueue as $pq): ?><tr><td><strong>#<?php echo (int)$pq['id']; ?></strong></td><td><?php echo e($pq['month']); ?></td><td><?php echo e($pq['nanny_name'] ?? '-'); ?></td><td><?php echo e($pq['created_by_name'] ?? '-'); ?></td><td><strong><?php echo number_format((float)$pq['total_amount'], 0); ?></strong></td><td><?php echo e($pq['submitted_at'] ?? '-'); ?></td><td><a href="<?php echo APP_URL; ?>modules/accounting/disbursements.php?view=<?php echo (int)$pq['id']; ?>" class="btn-fm btn-navy">مراجعة</a></td></tr><?php endforeach; ?></tbody></table></div></div></div><?php endif; ?>

<!-- ═════════ OPEN BATCHES (Aging) ══════════ -->
<?php if ($openBatches): ?><div class="fm-card"><div class="fm-card-head"><span>🔓 دفعات مفتوحة — بانتظار صرف الأخصائيات</span><span class="badge-fm badge-blue"><?php echo count($openBatches); ?> دفعة</span></div><div class="fm-card-body"><div style="overflow-x:auto"><table class="fm-table"><thead><tr><th>#</th><th>الشهر</th><th>الأخصائية</th><th>البنود</th><th>المبلغ</th><th>تاريخ التحويل</th><th>أيام مفتوحة</th><th>إجراء</th></tr></thead><tbody><?php foreach ($openBatches as $ob): $daysOpen = $ob['transferred_at'] ? floor((time() - strtotime($ob['transferred_at'])) / 86400) : 0; $agingClass = $daysOpen > 14 ? 'badge-red' : ($daysOpen > 7 ? 'badge-amber' : 'badge-blue'); ?><tr><td><strong>#<?php echo (int)$ob['id']; ?></strong></td><td><?php echo e($ob['month']); ?></td><td><?php echo e($ob['nanny_name'] ?? '-'); ?></td><td><span class="badge-fm badge-green"><?php echo (int)$ob['paid_items']; ?> مُصرَف</span><?php if ((int)$ob['pending_items'] > 0): ?> <span class="badge-fm badge-amber"><?php echo (int)$ob['pending_items']; ?> معلّق</span><?php endif; ?><?php if ((int)$ob['return_items'] > 0): ?> <span class="badge-fm badge-red"><?php echo (int)$ob['return_items']; ?> إرجاع</span><?php endif; ?></td><td><strong><?php echo number_format((float)$ob['total_amount'], 0); ?></strong></td><td><?php echo e($ob['transferred_at'] ?? '-'); ?></td><td><span class="badge-fm <?php echo $agingClass; ?>"><?php echo $daysOpen; ?> يوم</span></td><td><a href="<?php echo APP_URL; ?>modules/accounting/disbursements.php?view=<?php echo (int)$ob['id']; ?>" class="btn-fm btn-ghost">تفاصيل</a></td></tr><?php endforeach; ?></tbody></table></div></div></div><?php endif; ?>

<!-- ══════════ RECENT RETURNS & VOIDS ══════════ -->
<div class="grid-2">
<?php if ($recentReturns): ?><div class="fm-card"><div class="fm-card-head"><span>آخر عمليات الإرجاع</span><span class="badge-fm badge-amber"><?php echo count($recentReturns); ?></span></div><div class="fm-card-body"><div style="overflow-x:auto"><table class="fm-table"><thead><tr><th>القيد</th><th>التاريخ</th><th>الأسرة</th><th>المبلغ</th></tr></thead><tbody><?php foreach ($recentReturns as $rr): ?><tr><td><code><?php echo e($rr['entry_code']); ?></code></td><td><?php echo e($rr['entry_date']); ?></td><td><?php echo e($rr['family_code'] ?? '-'); ?></td><td><strong class="flow-value outgoing"><?php echo number_format((float)$rr['amount'], 0); ?></strong></td></tr><?php endforeach; ?></tbody></table></div></div></div><?php endif; ?>
<?php if ($recentVoids): ?><div class="fm-card"><div class="fm-card-head"><span>آخر الدفعات المُبطَلة</span><span class="badge-fm badge-red"><?php echo count($recentVoids); ?></span></div><div class="fm-card-body"><div style="overflow-x:auto"><table class="fm-table"><thead><tr><th>#</th><th>الشهر</th><th>الأخصائية</th><th>المبلغ</th><th>السبب</th></tr></thead><tbody><?php foreach ($recentVoids as $rv): ?><tr><td><strong>#<?php echo (int)$rv['id']; ?></strong></td><td><?php echo e($rv['month']); ?></td><td><?php echo e($rv['nanny_name'] ?? '-'); ?></td><td><strong><?php echo number_format((float)$rv['total_amount'], 0); ?></strong></td><td title="<?php echo e($rv['void_reason'] ?? ''); ?>"><?php echo e(mb_substr($rv['void_reason'] ?? '-', 0, 30)); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div><?php endif; ?>
</div>

<!-- ═════════ QUICK STATS ══════════ -->
<div class="fm-card"><div class="fm-card-head"><span>إحصائيات سريعة</span></div><div class="fm-card-body"><div class="grid-4"><div class="stat-box"><div class="stat-value"><?php echo (int)$quickStats['active_sponsorships']; ?></div><div class="stat-label">كفالة نشطة</div></div><div class="stat-box blue"><div class="stat-value"><?php echo (int)$quickStats['active_families']; ?></div><div class="stat-label">أسرة نشطة</div></div><div class="stat-box purple"><div class="stat-value"><?php echo (int)$quickStats['active_nannies']; ?></div><div class="stat-label">أخصائية نشطة</div></div><div class="stat-box green"><div class="stat-value"><?php echo number_format((float)$quickStats['received_this_month'], 0); ?></div><div class="stat-label">مُصرَف هذا الشهر</div></div></div></div></div>

<!-- ══════════ RECENT JOURNAL ENTRIES ══════════ -->
<div class="fm-card"><div class="fm-card-head"><span>📒 آخر القيود المحاسبية</span><a href="<?php echo APP_URL; ?>modules/accounting/journal.php" class="btn-fm btn-ghost" style="background:#fff; color:#1b4d8f; font-size:0.8rem">عرض الكل</a></div><div class="fm-card-body"><div style="overflow-x:auto"><table class="fm-table"><thead><tr><th>رقم القيد</th><th>التاريخ</th><th>الوصف</th><th>مدين</th><th>دائن</th><th>الحالة</th></tr></thead><tbody><?php foreach ($recentJournals as $rj): ?><tr><td><code><?php echo e($rj['entry_code']); ?></code></td><td><?php echo e($rj['entry_date']); ?></td><td><?php echo e(mb_substr($rj['description'] ?? '-', 0, 50)); ?></td><td><?php echo number_format((float)$rj['total_debit'], 0); ?></td><td><?php echo number_format((float)$rj['total_credit'], 0); ?></td><td><span class="badge-fm badge-green"><?php echo e($rj['status'] === 'posted' ? 'مرحّل' : ($rj['status'] === 'voided' ? 'مُبطَل' : $rj['status'])); ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></div>

<!-- ══════════ QUICK ACTIONS ══════════ -->
<div class="fm-card"><div class="fm-card-head"><span>إجراءات سريعة</span></div><div class="fm-card-body" style="display:flex; gap:10px; flex-wrap:wrap"><a href="#project-budget-review" class="btn-fm btn-navy">📁 مراجعة ميزانيات المشاريع (<?php echo $projectApprovalCount; ?>)</a><a href="<?php echo APP_URL; ?>modules/accounting/disbursements.php" class="btn-fm btn-navy">📋 التحويلات الشهرية</a><a href="<?php echo APP_URL; ?>modules/accounting/fm_review_queue.php" class="btn-fm btn-navy">⏳ طابور المراجعة</a><a href="<?php echo APP_URL; ?>modules/accounting/gm_reconciliation.php" class="btn-fm btn-navy">تقرير المصالحة</a><a href="<?php echo APP_URL; ?>modules/accounting/journal.php" class="btn-fm btn-ghost">📒 القيود اليومية</a><a href="<?php echo APP_URL; ?>modules/accounting/accounts.php" class="btn-fm btn-ghost">🌳 شجرة الحسابات</a><a href="<?php echo APP_URL; ?>modules/transactions/index.php" class="btn-fm btn-ghost">💳 المعاملات المالية</a></div></div>
</main>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
