<?php
// modules/accounting/journal_create.php - Manual balanced journal entry
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'accountant','financial_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = 'قيد يدوي جديد';
$active    = 'journal';
ak_ensure_tables(); ak_seed_accounts();

$accounts = dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE is_active = 1 ORDER BY code");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) $errors[] = 'انتهت صلاحية الجلسة.';
    else {
        $date = trim($_POST['entry_date'] ?? '') ?: date('Y-m-d');
        $desc = trim($_POST['description'] ?? '');
        $accIds = $_POST['account_id'] ?? []; $debits = $_POST['debit'] ?? []; $credits = $_POST['credit'] ?? []; $ldesc = $_POST['line_desc'] ?? [];
        $lines = []; $sumD = 0.0; $sumC = 0.0; $usedAccounts = [];
        foreach ($accIds as $i => $aid) {
            $aid = (int)$aid; $d = (float)str_replace(',', '', (string)($debits[$i] ?? 0)); $c = (float)str_replace(',', '', (string)($credits[$i] ?? 0));
            if ($aid <= 0 || ($d <= 0 && $c <= 0)) continue;
            if ($d > 0 && $c > 0) { $errors[] = 'السطر الواحد لا يجمع بين مدين ودائن.'; break; }
            if (in_array($aid, $usedAccounts, true)) {
                // Using the same account twice in one entry (e.g. once as debit, once as
                // credit) cancels itself out and has ZERO real financial effect, even
                // though the entry still looks "balanced". This is an easy mistake for
                // anyone not deeply familiar with double-entry bookkeeping to make —
                // caught here instead of silently producing a no-op entry.
                $accName = dbFetchOne("SELECT code, name_ar FROM accounts WHERE id = ?", [$aid]);
                $errors[] = 'لا يمكن استخدام نفس الحساب أكثر من مرة في نفس القيد (' . e($accName['code'] ?? '') . ' — ' . e($accName['name_ar'] ?? '') . '). هذا يُلغي أثره المالي تماماً حتى لو بدا القيد متوازناً.';
                break;
            }
            $usedAccounts[] = $aid;
            $lines[] = [$aid, $d, $c, trim((string)($ldesc[$i] ?? ''))];
            $sumD += $d; $sumC += $c;
        }
        if (!$errors && count($lines) < 2) $errors[] = 'القيد يحتاج سطرين على الأقل.';
        if (!$errors && $sumD <= 0) $errors[] = 'أدخل القيم.';
        if (!$errors && abs($sumD - $sumC) > 0.009) $errors[] = 'القيد غير متوازن: مدين ' . number_format($sumD, 2) . ' ≠ دائن ' . number_format($sumC, 2);
        if (!$errors) {
            $n = (int)(dbFetchOne("SELECT COUNT(*) c FROM journal_entries")['c'] ?? 0) + 1;
            $code = 'JE-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
            dbExecute("INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, status, created_by)
                       VALUES (?, ?, ?, 'manual', 'posted', ?)", [$code, $date, $desc !== '' ? $desc : 'قيد يدوي', Session::getUserId()]);
            $eid = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);
            foreach ($lines as $l) dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)", [$eid, $l[0], $l[1], $l[2], $l[3]]);
            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                           VALUES (?, 'CREATE', 'journal_entries', ?, NULL, ?, ?, ?)",
                    [Session::getUserId(), $eid, json_encode(['code' => $code, 'total' => $sumD], JSON_UNESCAPED_UNICODE),
                     $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            } catch (Throwable $e) {}
            flash('success', 'تم ترحيل القيد ' . $code);
            header('Location: ' . APP_URL . 'modules/accounting/journal.php?view=' . $eid); exit();
        }
    }
}
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>قيد يدوي جديد</h2>
    <p>يجب أن يتساوى طرفا المدين والدائن قبل الترحيل</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?><div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div><?php endif; ?>

<div class="card fade-in">
    <div class="card-body">
        <form method="post" id="jeForm">
            <?php echo csrf_field(); ?>
            <div class="row g-2 mb-3">
                <div class="col-md-3"><label class="form-label">التاريخ *</label><input type="date" name="entry_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="col-md-9"><label class="form-label">البيان *</label><input type="text" name="description" class="form-control" required placeholder="مثال: سداد إيجار المكتب عن شهر ..."></div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle" id="linesTable">
                    <thead><tr><th style="width:40%">الحساب</th><th>مدين</th><th>دائن</th><th>البيان</th><th></th></tr></thead>
                    <tbody>
                    <?php for ($i = 0; $i < 2; $i++): ?>
                    <tr class="je-line">
                        <td>
                            <select name="account_id[]" class="form-select" required>
                                <option value="">— اختر —</option>
                                <?php foreach ($accounts as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo e($a['code']); ?> — <?php echo e($a['name_ar']); ?></option><?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" step="0.01" min="0" name="debit[]" class="form-control amt" placeholder="0.00"></td>
                        <td><input type="number" step="0.01" min="0" name="credit[]" class="form-control amt" placeholder="0.00"></td>
                        <td><input type="text" name="line_desc[]" class="form-control"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalc();"><i class="fas fa-trash"></i></button></td>
                    </tr>
                    <?php endfor; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-active fw-bold">
                            <td>الإجمالي</td>
                            <td id="totD">0.00</td><td id="totC">0.00</td>
                            <td id="diff" colspan="2">متوازن ✔</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary" onclick="addLine()"><i class="fas fa-plus me-1"></i>إضافة سطر</button>
                <button class="btn btn-primary"><i class="fas fa-save me-1"></i>ترحيل القيد</button>
            </div>
        </form>
    </div>
</div>
<script>
function addLine(){
    var tbody = document.querySelector('#linesTable tbody');
    var row = tbody.querySelector('tr.je-line').cloneNode(true);
    row.querySelectorAll('input').forEach(function(i){ i.value = ''; });
    row.querySelector('select').value = '';
    tbody.appendChild(row);
}
function recalc(){
    var d = 0, c = 0;
    document.querySelectorAll('#linesTable tbody tr').forEach(function(tr){
        var inputs = tr.querySelectorAll('.amt');
        d += parseFloat(inputs[0].value || 0); c += parseFloat(inputs[1].value || 0);
    });
    document.getElementById('totD').textContent = d.toFixed(2);
    document.getElementById('totC').textContent = c.toFixed(2);
    var diff = Math.abs(d - c);
    var el = document.getElementById('diff');
    el.textContent = diff < 0.01 ? 'متوازن ✔' : 'فرق: ' + diff.toFixed(2);
    el.style.color = diff < 0.01 ? '#198754' : '#dc3545';
}
// Debit and credit must be mutually exclusive per line — a single journal line can never
// legitimately be both. Typing a nonzero value in one field now clears the other, so this
// can no longer happen by accident (typo, browser autofill, or otherwise) rather than only
// being caught after submission.
document.getElementById('jeForm').addEventListener('input', function(e) {
    if (!e.target.classList.contains('amt')) return;
    var tr = e.target.closest('tr');
    var inputs = tr.querySelectorAll('.amt');
    var thisIsDebit = (e.target === inputs[0]);
    var other = thisIsDebit ? inputs[1] : inputs[0];
    if (parseFloat(e.target.value || 0) > 0) { other.value = ''; }
});
document.getElementById('jeForm').addEventListener('input', recalc);
recalc();
</script>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>