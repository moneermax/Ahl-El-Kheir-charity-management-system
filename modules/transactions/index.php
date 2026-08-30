<?php
// modules/transactions/index.php - Payments list + void
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
/* Session-7: CR-001 alignment — financial_manager & accountant_staff join finance pages */
if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'accountant', 'accountant_staff', 'financial_manager'], true)) {
header('Location: ' . APP_URL . 'index.php'); exit();
}
$canVoid = in_array($role, ['admin', 'accountant', 'financial_manager'], true);
$pageTitle = 'سجل المعاملات';
$active    = 'transactions';
/* ---------- void ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['void_tx']) && $canVoid) {
if (verify_csrf()) {
$tid = (int)$_POST['void_tx'];
$reason = trim($_POST['void_reason'] ?? '') ?: 'إلغاء';
$t = dbFetchOne("SELECT id, status, transaction_code FROM transactions WHERE id = ?", [$tid]);
if ($t && $t['status'] === 'posted') {
dbExecute("UPDATE transactions SET status='voided', voided_at=NOW(), voided_by=?, void_reason=? WHERE id=?", [Session::getUserId(), $reason, $tid]);
ak_void_journal_for_transaction($tid, $reason);
try {
dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
VALUES (?, 'VOID', 'transactions', ?, ?, ?, ?, ?)",
[Session::getUserId(), $tid, json_encode(['code' => $t['transaction_code'], 'status' => 'posted'], JSON_UNESCAPED_UNICODE),
json_encode(['status' => 'voided', 'reason' => $reason], JSON_UNESCAPED_UNICODE),
$_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
} catch (Throwable $e) {}
flash('success', 'تم إبطال المعاملة وقيدها.');
}
}
header('Location: ' . APP_URL . 'modules/transactions/index.php'); exit();
}
/* ---------- filters ---------- */
$q = trim($_GET['q'] ?? ''); $from = trim($_GET['from'] ?? ''); $to = trim($_GET['to'] ?? '');
$fStatus = trim($_GET['tstatus'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1)); $perPage = 50;
$mySponsorIds = null;
if ($role === 'supervisor') {
$myLetterIds = array_map('intval', array_column(dbFetchAll("SELECT letter_id FROM supervisor_letters WHERE supervisor_id = ?", [Session::getUserId()]), 'letter_id'));
$sql = "SELECT id FROM sponsors WHERE supervisor_id = ?"; $params = [Session::getUserId()];
if ($myLetterIds) { $ph = implode(',', array_fill(0, count($myLetterIds), '?')); $sql .= " OR first_letter_id IN ($ph)"; $params = array_merge($params, $myLetterIds); }
$mySponsorIds = array_map('intval', array_column(dbFetchAll($sql, $params), 'id'));
}
$all = dbFetchAll("
SELECT t.*, s.full_name sponsor, f.mother_name family
FROM transactions t
LEFT JOIN sponsors s ON s.id = t.sponsor_id
LEFT JOIN families f ON f.id = t.family_id
ORDER BY t.transaction_date DESC, t.id DESC");
$filtered = [];
foreach ($all as $row) {
if ($mySponsorIds !== null && !in_array((int)($row['sponsor_id'] ?? 0), $mySponsorIds, true)) continue;
if ($fStatus !== '' && $row['status'] !== $fStatus) continue;
if ($from !== '' && $row['transaction_date'] < $from) continue;
if ($to !== '' && $row['transaction_date'] > $to) continue;
if ($q !== '') {
$hay = mb_strtolower(($row['sponsor'] ?? '') . ' ' . ($row['family'] ?? '') . ' ' . $row['transaction_code'] . ' ' . ($row['receipt_number'] ?? ''), 'UTF-8');
if (mb_strpos($hay, mb_strtolower($q, 'UTF-8'), 0, 'UTF-8') === false) continue;
}
$filtered[] = $row;
}
$totalSum = 0; foreach ($filtered as $r) if ($r['status'] === 'posted') $totalSum += (float)$r['amount'];
$total = count($filtered); $pages = max(1, (int)ceil($total / $perPage)); $page = min($page, $pages);
$rows = array_slice($filtered, ($page - 1) * $perPage, $perPage);
$jeMap = [];
if ($rows) {
$ids = array_map(fn($r) => (int)$r['id'], $rows);
foreach (dbFetchAll("SELECT reference_id, id FROM journal_entries WHERE reference_type='transaction' AND reference_id IN (" . implode(',', $ids) . ")") as $j)
$jeMap[(int)$j['reference_id']] = (int)$j['id'];
}
$qs = fn(array $extra) => APP_URL . 'modules/transactions/index.php?' . http_build_query(array_merge($_GET, $extra));
/* Session-7: windowed pagination (1 … 4 5 6 … 45) */
if ($pages <= 7) {
$win = range(1, $pages);
} else {
$win = [1];
if ($page > 4) $win[] = 0;
$start = max(2, $page - 1); $end = min($pages - 1, $page + 1);
if ($page <= 4)          { $start = 2;          $end = 5; }
if ($page >= $pages - 3) { $start = $pages - 5; $end = $pages - 1; }
for ($i = $start; $i <= $end; $i++) $win[] = $i;
if ($page < $pages - 3) $win[] = 0;
$win[] = $pages;
}
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
<h2>سجل المعاملات</h2>
<p><?php echo $total; ?> معاملة · المحصّل (مرحّل): <?php echo number_format($totalSum, 0); ?> ج.س</p>
<div class="quick-actions mt-3">
<?php if (in_array($role, ['accountant', 'accountant_staff', 'financial_manager', 'admin', 'supervisor'], true)): ?>
<a href="<?php echo APP_URL; ?>modules/transactions/create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> تسجيل دفعة</a>
<?php endif; ?>
<a href="<?php echo APP_URL; ?>modules/accounting/journal.php" class="btn btn-secondary btn-sm"><i class="fas fa-book me-1"></i>القيود اليومية</a>
</div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="card mb-3 fade-in">
<div class="card-body">
<form method="get" class="row g-2 align-items-end">
<div class="col-md-4"><label class="form-label">بحث</label><input type="text" name="q" class="form-control" value="<?php echo e($q); ?>"></div>
<div class="col-md-2"><label class="form-label">من</label><input type="date" name="from" class="form-control" value="<?php echo e($from); ?>"></div>
<div class="col-md-2"><label class="form-label">إلى</label><input type="date" name="to" class="form-control" value="<?php echo e($to); ?>"></div>
<div class="col-md-2">
<label class="form-label">الحالة</label>
<select name="tstatus" class="form-select">
<option value="">الكل</option>
<option value="posted" <?php echo $fStatus === 'posted' ? 'selected' : ''; ?>>مرحّلة</option>
<option value="voided" <?php echo $fStatus === 'voided' ? 'selected' : ''; ?>>مبطلة</option>
</select>
</div>
<div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>بحث</button></div>
</form>
</div>
</div>
<div class="card fade-in">
<div class="card-body">
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>الكود</th><th>التاريخ</th><th>الكفيل/الأسرة</th><th>النوع</th><th>المبلغ</th><th>الطريقة</th><th>الإيصال</th><th>الحالة</th><th class="text-center">إجراءات</th></tr></thead>
<tbody>
<?php if (!$rows): ?>
<tr><td colspan="9" class="text-center text-muted py-4">لا توجد نتائج.</td></tr>
<?php else: foreach ($rows as $r): ?>
<tr>
<td><code><?php echo e($r['transaction_code']); ?></code></td>
<td><?php echo e($r['transaction_date']); ?></td>
<td><?php echo e($r['sponsor'] ?? '—'); ?><br><small class="text-muted"><?php echo e($r['family'] ?? ''); ?></small></td>
<td><small><?php echo e($r['transaction_type'] ?? ''); ?></small></td>
<td><strong><?php echo number_format((float)$r['amount'], 0); ?></strong></td>
<td><?php echo e($r['payment_method']); ?></td>
<td>
<?php echo e($r['receipt_number'] ?? '-'); ?>
<?php if (!empty($r['receipt_path'])): ?>
<br><a href="<?php echo APP_URL; ?>modules/transactions/receipt_file.php?id=<?php echo (int)$r['id']; ?>" target="_blank" class="badge bg-info text-decoration-none" title="عرض ملف الإيصال"><i class="fas fa-file-pdf me-1"></i>ملف</a>
<?php endif; ?>
</td>
<td><?php echo $r['status'] === 'posted' ? '<span class="badge bg-success">مرحّلة</span>' : '<span class="badge bg-danger">مبطلة</span>'; ?></td>
<td class="text-center" style="white-space:nowrap;">
<?php if (isset($jeMap[(int)$r['id']])): ?>
<a class="btn btn-sm btn-info" title="القيد المحاسبي" href="<?php echo APP_URL; ?>modules/accounting/journal.php?view=<?php echo $jeMap[(int)$r['id']]; ?>"><i class="fas fa-book"></i></a>
<?php endif; ?>
<?php if ($canVoid && $r['status'] === 'posted'): ?>
<form method="post" class="d-inline ak-void-form" data-confirm-msg="هل أنت متأكد من إبطال هذه المعاملة وقيدها؟"><?php echo csrf_field(); ?>
<input type="hidden" name="void_tx" value="<?php echo (int)$r['id']; ?>">
<input type="text" name="void_reason" class="form-control form-control-sm d-inline-block" style="width:110px" placeholder="السبب" required>
<button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-ban"></i></button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
<?php if ($pages > 1): ?>
<nav class="mt-2">
<ul class="pagination pagination-sm justify-content-center">
<?php foreach ($win as $i): ?>
<?php if ($i === 0): ?>
<li class="page-item disabled"><span class="page-link">…</span></li>
<?php else: ?>
<li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
<a class="page-link" href="<?php echo e($qs(['page' => $i])); ?>"><?php echo $i; ?></a>
</li>
<?php endif; ?>
<?php endforeach; ?>
</ul>
</nav>
<?php endif; ?>
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
            if (confirm(msg)) proceed();
        }
    });
});
</script>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>