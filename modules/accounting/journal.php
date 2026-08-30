<?php
// modules/accounting/journal.php - Journal entries (list / view / void)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'accountant', 'financial_manager','general_manager', 'vice_general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$role = Session::getUserRole();
$canManage = in_array($role, ['admin', 'accountant', 'financial_manager'], true);
$pageTitle = 'القيود اليومية';
$active    = 'journal';
ak_ensure_tables(); ak_seed_accounts();

/* ---------- void action ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['void_entry']) && $canManage) {
    if (verify_csrf()) {
        $eid = (int)$_POST['void_entry'];
        $reason = trim($_POST['void_reason'] ?? '') ?: 'إلغاء قيد';
        $existing = dbFetchOne("SELECT reference_type FROM journal_entries WHERE id = ?", [$eid]);
        if ($existing && in_array($existing['reference_type'], ['transaction', 'disbursement', 'voucher'], true)) {
            flash('error', 'لا يمكن إبطال هذا القيد من هنا — استخدم صفحة المصدر المخصصة (المعاملات/التحويلات الشهرية/السندات).');
        } else {
        dbExecute("UPDATE journal_entries SET status='voided', voided_at=NOW(), voided_by=?, void_reason=? WHERE id=? AND status='posted'",
            [Session::getUserId(), $reason, $eid]);
        try {
            dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                       VALUES (?, 'VOID', 'journal_entries', ?, NULL, ?, ?, ?)",
                [Session::getUserId(), $eid, json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE),
                 $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
        } catch (Throwable $e) {}
        flash('success', 'تم إبطال القيد.');
        }
    }
    header('Location: ' . APP_URL . 'modules/accounting/journal.php'); exit();
}

/* ---------- view single entry ---------- */
$viewId = (int)($_GET['view'] ?? 0);
if ($viewId) {
    $entry = dbFetchOne("SELECT je.*, u.full_name creator FROM journal_entries je LEFT JOIN users u ON u.id = je.created_by WHERE je.id = ?", [$viewId]);
    $lines = dbFetchAll("SELECT jl.*, a.code, a.name_ar FROM journal_lines jl JOIN accounts a ON a.id = jl.account_id WHERE jl.entry_id = ? ORDER BY jl.id", [$viewId]);
    $sumD = 0; $sumC = 0; foreach ($lines as $l) { $sumD += (float)$l['debit']; $sumC += (float)$l['credit']; }
    include dirname(__DIR__, 2) . '/includes/header.php';
    ?>
    <div class="welcome-section fade-in">
        <h2>قيد <?php echo e($entry['entry_code'] ?? ''); ?></h2>
        <p><?php echo e($entry['description'] ?? ''); ?> · <?php echo e($entry['entry_date']); ?>
           <?php echo ($entry['status'] ?? '') === 'voided' ? '<span class="badge bg-danger">مبطل: ' . e($entry['void_reason'] ?? '') . '</span>' : '<span class="badge bg-success">مرحّل</span>'; ?></p>
        <div class="quick-actions mt-3">
            <a href="<?php echo APP_URL; ?>modules/accounting/journal.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-right me-1"></i>رجوع</a>
            <?php if ($canManage && $entry['status'] === 'posted' && !in_array($entry['reference_type'], ['transaction', 'disbursement', 'voucher'], true)): ?>
                <form method="post" class="d-inline ak-void-form" data-confirm-msg="هل أنت متأكد من إبطال هذا القيد؟"><?php echo csrf_field(); ?>
                    <input type="hidden" name="void_entry" value="<?php echo $viewId; ?>">
                    <input type="text" name="void_reason" class="form-control form-control-sm d-inline-block w-auto" placeholder="السبب" required>
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-ban me-1"></i>إبطال</button>
                </form>
            <?php elseif ($entry['reference_type'] === 'transaction'): ?>
                <span class="badge bg-info">قيد آلي — يُبطل من صفحة المعاملات</span>
            <?php elseif ($entry['reference_type'] === 'disbursement'): ?>
                <span class="badge bg-info">قيد آلي — يُبطل من صفحة التحويلات الشهرية (زر "إبطال الدفعة")</span>
            <?php elseif ($entry['reference_type'] === 'voucher'): ?>
                <span class="badge bg-info">قيد آلي — يُبطل من صفحة السندات</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card fade-in">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>الحساب</th><th>البيان</th><th>مدين</th><th>دائن</th></tr></thead>
                    <tbody>
                    <?php foreach ($lines as $l): ?>
                        <tr>
                            <td><code><?php echo e($l['code']); ?></code> <?php echo e($l['name_ar']); ?></td>
                            <td><small><?php echo e($l['description'] ?? ''); ?></small></td>
                            <td><?php echo (float)$l['debit'] > 0 ? number_format((float)$l['debit'], 2) : '—'; ?></td>
                            <td><?php echo (float)$l['credit'] > 0 ? number_format((float)$l['credit'], 2) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-active fw-bold">
                        <td colspan="2">الإجمالي</td>
                        <td><?php echo number_format($sumD, 2); ?></td>
                        <td><?php echo number_format($sumC, 2); ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- SweetAlert2 for a styled confirmation instead of the plain browser dialog -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.querySelectorAll('.ak-void-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var msg = form.dataset.confirmMsg || 'هل أنت متأكد؟';
            function proceed() { form.submit(); }
            if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                Swal.fire({
                    title: 'تأكيد الإبطال', text: msg, icon: 'warning',
                    showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، إبطال', cancelButtonText: 'إلغاء', reverseButtons: true
                }).then(function(result) { if (result.isConfirmed) proceed(); });
            } else {
                // Fallback if the library ever fails to load — the action still works,
                // just with the plain browser dialog instead of the styled one.
                if (confirm(msg)) proceed();
            }
        });
    });
    </script>
    <?php include dirname(__DIR__, 2) . '/includes/footer.php'; exit();
}

/* ---------- list ---------- */
$from = trim($_GET['from'] ?? ''); $to = trim($_GET['to'] ?? '');
$sql = "SELECT je.*, u.full_name creator, (SELECT COALESCE(SUM(jl.debit),0) FROM journal_lines jl WHERE jl.entry_id = je.id) total
        FROM journal_entries je LEFT JOIN users u ON u.id = je.created_by WHERE 1=1";
$params = [];
if ($from) { $sql .= " AND je.entry_date >= ?"; $params[] = $from; }
if ($to)   { $sql .= " AND je.entry_date <= ?"; $params[] = $to; }
$sql .= " ORDER BY je.entry_date DESC, je.id DESC LIMIT 300";
$entries = dbFetchAll($sql, $params);

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>القيود اليومية</h2>
    <p>القيود الآلية من التحصيل + القيود اليدوية</p>
    <div class="quick-actions mt-3">
        <?php if ($canManage): ?>
            <a href="<?php echo APP_URL; ?>modules/accounting/journal_create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>قيد يدوي جديد</a>
        <?php endif; ?>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="card mb-3 fade-in">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">من</label><input type="date" name="from" class="form-control" value="<?php echo e($from); ?>"></div>
            <div class="col-md-3"><label class="form-label">إلى</label><input type="date" name="to" class="form-control" value="<?php echo e($to); ?>"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-search"></i> عرض</button></div>
        </form>
    </div>
</div>
<div class="card fade-in">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>الكود</th><th>التاريخ</th><th>البيان</th><th>المرجع</th><th>القيمة</th><th>الحالة</th><th>أنشأه</th><th class="text-center">عرض</th></tr></thead>
                <tbody>
                <?php if (!$entries): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">لا توجد قيود — سجّل معاملة تحصيل أو قيداً يدوياً.</td></tr>
                <?php else: foreach ($entries as $en): ?>
                    <tr>
                        <td><code><?php echo e($en['entry_code']); ?></code></td>
                        <td><?php echo e($en['entry_date']); ?></td>
                        <td><?php echo e($en['description'] ?? ''); ?></td>
                        <td><?php echo e($en['reference_type'] ?? 'يدوي'); ?></td>
                        <td><strong><?php echo number_format((float)$en['total'], 2); ?></strong></td>
                        <td><?php echo $en['status'] === 'posted' ? '<span class="badge bg-success">مرحّل</span>' : '<span class="badge bg-danger">مبطل</span>'; ?></td>
                        <td><small><?php echo e($en['creator'] ?? ''); ?></small></td>
                        <td class="text-center"><a class="btn btn-sm btn-primary" href="<?php echo APP_URL; ?>modules/accounting/journal.php?view=<?php echo (int)$en['id']; ?>"><i class="fas fa-eye"></i></a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>