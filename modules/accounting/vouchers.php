<?php
// modules/accounting/vouchers.php - Receipt & payment vouchers (issue / list / void)
// سند قبض = cash IN  (Dr cash/bank/wallet, Cr revenue)   |   سند صرف = cash OUT (Dr expense, Cr cash/bank/wallet)
// Issued by the Financial Manager and the accounting staff. Every voucher is posted to the ledger immediately,
// so the organization's cash / bank / wallet balances change the moment the voucher is issued.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib_vouchers.php';
Session::start();

$voucherRole = Session::getUserRole();
if (!Session::isLoggedIn() || !ak_voucher_can('view', $voucherRole)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$canIssue = ak_voucher_can('issue', $voucherRole);
$canVoid  = ak_voucher_can('void', $voucherRole);
$pageTitle = 'السندات المالية';
$active    = 'vouchers';
ak_ensure_tables(); ak_seed_accounts();

$cashAccounts    = dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE code IN ('1100','1200','1300') ORDER BY code");
$incomeAccounts  = dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE account_type = 'revenue' AND is_active = 1 ORDER BY code");
$expenseAccounts = dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE account_type = 'expense' AND is_active = 1 ORDER BY code");
$cashIds = array_map(static function ($c) { return (int)$c['id']; }, $cashAccounts);
$today = date('Y-m-d');

$errors = [];
$old = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($canIssue || $canVoid)) {
    if (!verify_csrf()) {
        $errors[] = 'انتهت صلاحية الجلسة.';
    } elseif (isset($_POST['save_voucher']) && $canIssue) {
        $old = $_POST;
        $vType   = ($_POST['voucher_type'] ?? 'receipt') === 'payment' ? 'payment' : 'receipt';
        $date    = trim($_POST['voucher_date'] ?? '') ?: $today;
        $amount  = round((float)str_replace(',', '', (string)($_POST['amount'] ?? 0)), 2);
        $cashId  = (int)($_POST['cash_account_id'] ?? 0);
        $otherId = (int)($_POST['other_account_id'] ?? 0);
        $party   = trim($_POST['party_name'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $ref     = trim($_POST['reference_number'] ?? '');

        $dateParts = explode('-', $date);
        if (count($dateParts) !== 3 || !checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
            $errors[] = 'تاريخ السند غير صحيح.';
        } elseif ($date > $today) {
            $errors[] = 'لا يمكن إصدار سند بتاريخ مستقبلي.';
        }
        if ($amount <= 0) $errors[] = 'المبلغ يجب أن يكون أكبر من صفر.';
        if ($amount > 999999999999.99) $errors[] = 'المبلغ كبير جداً.';
        if (!in_array($cashId, $cashIds, true)) $errors[] = 'اختر مكان النقد (صندوق/بنك/محفظة).';
        if ($otherId <= 0) $errors[] = 'اختر الحساب المقابل.';
        if ($party === '') $errors[] = $vType === 'receipt' ? 'اسم المُسلِّم (استلمنا من) مطلوب.' : 'اسم المستفيد (صرفنا إلى) مطلوب.';
        if (mb_strlen($desc) < 3) $errors[] = 'البيان (سبب السند) مطلوب.';
        if (mb_strlen($party) > 150 || mb_strlen($desc) > 255 || mb_strlen($ref) > 100) $errors[] = 'أحد الحقول النصية أطول من المسموح.';

        $newVoucherId = 0;
        if (!$errors) {
            $lockAcquired = false;
            try {
                $lockAcquired = ak_voucher_lock();
                if (!$lockAcquired) {
                    throw new RuntimeException('تعذر الحصول على قفل الترقيم. حاول مرة أخرى.');
                }
                db()->beginTransaction();

                $otherAccount = dbFetchOne("SELECT id, account_type, is_active FROM accounts WHERE id = ? FOR UPDATE", [$otherId]);
                if (!$otherAccount || (int)$otherAccount['is_active'] !== 1) {
                    throw new RuntimeException('الحساب المقابل غير موجود أو غير نشط.');
                }
                $requiredType = $vType === 'receipt' ? 'revenue' : 'expense';
                if ($otherAccount['account_type'] !== $requiredType) {
                    throw new RuntimeException($vType === 'receipt'
                        ? 'سند القبض يجب أن يستخدم حساب إيراد كحساب مقابل.'
                        : 'سند الصرف يجب أن يستخدم حساب مصروف كحساب مقابل.');
                }

                // Cash can never go below zero: a payment voucher needs enough money in the chosen cash / bank / wallet.
                if ($vType === 'payment') {
                    $available = ak_voucher_cash_balance($cashId);
                    if ($amount > $available + 0.000001) {
                        throw new RuntimeException('الرصيد غير كافٍ في الحساب المختار. المتاح: ' . number_format($available, 2) . ' ج.س');
                    }
                }

                $vNo = ak_voucher_next_number($vType);
                dbExecute("INSERT INTO vouchers (voucher_type, voucher_no, voucher_date, party_name, amount, cash_account_id, other_account_id, description, reference_number, status, created_by)
                           VALUES (?,?,?,?,?,?,?,?,?,'posted',?)",
                    [$vType, $vNo, $date, $party, $amount, $cashId, $otherId, $desc, $ref !== '' ? $ref : null, Session::getUserId()]);
                $vId = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);
                if ($vId <= 0) throw new RuntimeException('تعذر إنشاء السند.');

                // Balanced double entry: receipt => Dr cash / Cr revenue ; payment => Dr expense / Cr cash
                $jeCode = ak_voucher_next_journal_code();
                $label = ($vType === 'receipt' ? 'سند قبض ' : 'سند صرف ') . $vNo . ' — ' . $party;
                dbExecute("INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
                           VALUES (?,?,?,'voucher',?,'posted',?)",
                    [$jeCode, $date, mb_substr($label, 0, 250), $vId, Session::getUserId()]);
                $eid = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);
                if ($eid <= 0) throw new RuntimeException('تعذر إنشاء القيد المحاسبي للسند.');
                $debitAccount  = $vType === 'receipt' ? $cashId : $otherId;
                $creditAccount = $vType === 'receipt' ? $otherId : $cashId;
                dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)", [$eid, $debitAccount, $amount, 0, mb_substr($label, 0, 250)]);
                dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)", [$eid, $creditAccount, 0, $amount, mb_substr($label, 0, 250)]);

                $check = dbFetchOne("SELECT COUNT(*) n, COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c FROM journal_lines WHERE entry_id = ?", [$eid]);
                if ((int)$check['n'] !== 2 || abs((float)$check['d'] - $amount) > 0.000001 || abs((float)$check['c'] - $amount) > 0.000001) {
                    throw new RuntimeException('فشل التحقق من توازن القيد بعد الحفظ.');
                }
                dbExecute("UPDATE vouchers SET entry_id = ? WHERE id = ?", [$eid, $vId]);

                try {
                    dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                               VALUES (?, 'CREATE', 'vouchers', ?, NULL, ?, ?, ?)",
                        [Session::getUserId(), $vId, json_encode(['no' => $vNo, 'type' => $vType, 'amount' => $amount, 'entry' => $jeCode], JSON_UNESCAPED_UNICODE),
                         $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                } catch (Throwable $auditError) {}

                db()->commit();
                $newVoucherId = $vId;
            } catch (Throwable $e) {
                try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable $rollbackError) {}
                $errors[] = 'تعذر ترحيل السند: ' . $e->getMessage();
            } finally {
                if ($lockAcquired) ak_voucher_unlock();
            }
            if ($newVoucherId > 0) {
                flash('success', 'تم إصدار وترحيل ' . ($vType === 'receipt' ? 'سند القبض ' : 'سند الصرف ') . 'بنجاح — يمكنك طباعته الآن.');
                header('Location: ' . APP_URL . 'modules/accounting/voucher_print.php?id=' . $newVoucherId . '&new=1');
                exit();
            }
        }
    } elseif (isset($_POST['void_voucher']) && $canVoid) {
        $vId = (int)$_POST['void_voucher'];
        $voidReason = trim($_POST['void_reason'] ?? '');
        $lockAcquired = false;
        try {
            if (mb_strlen($voidReason) < 3) {
                throw new RuntimeException('سبب الإبطال مطلوب.');
            }
            $lockAcquired = ak_voucher_lock();
            if (!$lockAcquired) throw new RuntimeException('تعذر الحصول على قفل الترقيم. حاول مرة أخرى.');
            db()->beginTransaction();

            $vLocked = dbFetchOne("SELECT id, voucher_no, voucher_type, amount, cash_account_id, status, entry_id FROM vouchers WHERE id = ? FOR UPDATE", [$vId]);
            if (!$vLocked || $vLocked['status'] !== 'posted') {
                throw new RuntimeException('السند غير موجود أو ليس في حالة مرحّل.');
            }
            if (empty($vLocked['entry_id'])) {
                throw new RuntimeException('لا يمكن إبطال السند: لا يوجد قيد محاسبي مرتبط به.');
            }
            $journal = dbFetchOne("SELECT id, entry_code, entry_date, status, reference_type, reference_id
                                   FROM journal_entries
                                   WHERE id = ? AND reference_type = 'voucher' AND reference_id = ?
                                   FOR UPDATE", [(int)$vLocked['entry_id'], $vId]);
            if (!$journal) {
                throw new RuntimeException('لا يمكن إبطال السند: القيد المرتبط به غير موجود أو مرجعه غير صحيح.');
            }
            if ($journal['status'] !== 'posted') {
                throw new RuntimeException('لا يمكن إبطال السند: القيد المرتبط به ليس مرحّلاً.');
            }
            $linkedCount = (int)(dbFetchOne("SELECT COUNT(*) c FROM journal_entries WHERE reference_type = 'voucher' AND reference_id = ?", [$vId])['c'] ?? 0);
            if ($linkedCount !== 1) {
                throw new RuntimeException('لا يمكن إبطال السند: يجب أن يرتبط السند بقيد محاسبي واحد فقط.');
            }
            $reversalCount = (int)(dbFetchOne("SELECT COUNT(*) c FROM journal_entries WHERE reference_type = 'voucher_void' AND reference_id = ?", [$vId])['c'] ?? 0);
            if ($reversalCount !== 0) {
                throw new RuntimeException('لا يمكن إبطال السند: يوجد قيد عكسي سابق مرتبط به.');
            }
            $originalLines = dbFetchAll("SELECT account_id, debit, credit FROM journal_lines WHERE entry_id = ? ORDER BY id FOR UPDATE", [(int)$journal['id']]);
            if (count($originalLines) < 2) {
                throw new RuntimeException('لا يمكن إبطال السند: القيد الأصلي لا يحتوي على سطور محاسبية كافية.');
            }
            $totalDebit = 0.0; $totalCredit = 0.0;
            foreach ($originalLines as $line) { $totalDebit += (float)$line['debit']; $totalCredit += (float)$line['credit']; }
            if ($totalDebit <= 0 || abs($totalDebit - $totalCredit) > 0.000001) {
                throw new RuntimeException('لا يمكن إبطال السند: القيد الأصلي غير متوازن.');
            }
            // Voiding a RECEIPT takes the money back out of the cash / bank / wallet: it must still be there.
            if ($vLocked['voucher_type'] === 'receipt') {
                $available = ak_voucher_cash_balance((int)$vLocked['cash_account_id']);
                if ((float)$vLocked['amount'] > $available + 0.000001) {
                    throw new RuntimeException('لا يمكن إبطال سند القبض: رصيد الحساب الحالي (' . number_format($available, 2) . ' ج.س) أقل من مبلغ السند.');
                }
            }

            // Standard reversing entry: the original entry stays posted, and a separate posted entry swaps every debit
            // and credit. Together they net to zero in every report, and both remain visible for audit.
            $reversalCode  = ak_voucher_next_journal_code();
            $reversalLabel = 'عكس سند ' . $vLocked['voucher_no'] . ' — ' . $voidReason;
            dbExecute("INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
                       VALUES (?,?,?,'voucher_void',?,'posted',?)",
                [$reversalCode, $journal['entry_date'], mb_substr($reversalLabel, 0, 250), $vId, Session::getUserId()]);
            $reversalId = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);
            if ($reversalId <= 0) throw new RuntimeException('تعذر إنشاء القيد العكسي للسند.');
            foreach ($originalLines as $line) {
                dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)",
                    [$reversalId, (int)$line['account_id'], (float)$line['credit'], (float)$line['debit'], mb_substr($reversalLabel, 0, 250)]);
            }
            $reversalTotals = dbFetchOne("SELECT COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c FROM journal_lines WHERE entry_id = ?", [$reversalId]);
            if (abs((float)$reversalTotals['d'] - (float)$reversalTotals['c']) > 0.000001 || (float)$reversalTotals['d'] <= 0) {
                throw new RuntimeException('تعذر إبطال السند: القيد العكسي الناتج غير متوازن.');
            }
            if (dbExecute("UPDATE vouchers SET status='voided' WHERE id=? AND status='posted'", [$vId]) !== 1) {
                throw new RuntimeException('تعذر تحديث حالة السند إلى مبطل.');
            }
            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                           VALUES (?, 'VOID', 'vouchers', ?, ?, ?, ?, ?)",
                    [Session::getUserId(), $vId,
                     json_encode(['status' => 'posted', 'entry_id' => (int)$journal['id']], JSON_UNESCAPED_UNICODE),
                     json_encode(['status' => 'voided', 'entry_id' => (int)$journal['id'], 'reversal_journal_id' => $reversalId, 'reason' => $voidReason], JSON_UNESCAPED_UNICODE),
                     $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            } catch (Throwable $auditError) {}

            db()->commit();
            flash('success', 'تم إبطال السند ' . $vLocked['voucher_no'] . ' وإنشاء القيد العكسي ' . $reversalCode);
        } catch (Throwable $e) {
            try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable $rollbackError) {}
            flash('danger', $e->getMessage());
        } finally {
            if ($lockAcquired) ak_voucher_unlock();
        }
        header('Location: ' . APP_URL . 'modules/accounting/vouchers.php?tab=list'); exit();
    } elseif (isset($_POST['save_voucher']) || isset($_POST['void_voucher'])) {
        $errors[] = 'ليست لديك صلاحية تنفيذ هذا الإجراء.';
    }
}

$tab = $_GET['tab'] ?? ($canIssue ? 'new' : 'list');
if ($tab === 'new' && !$canIssue) $tab = 'list';
$fType = $_GET['vtype'] ?? '';
$list = dbFetchAll("SELECT v.*, c1.name_ar cash_name, c2.name_ar other_name, u.full_name creator
                    FROM vouchers v
                    JOIN accounts c1 ON c1.id = v.cash_account_id
                    JOIN accounts c2 ON c2.id = v.other_account_id
                    LEFT JOIN users u ON u.id = v.created_by
                    " . ($fType !== '' ? "WHERE v.voucher_type = '" . ($fType === 'payment' ? 'payment' : 'receipt') . "' " : '') . "
                    ORDER BY v.id DESC LIMIT 300");
$balances = [];
foreach ($cashAccounts as $c) $balances[(int)$c['id']] = ak_voucher_cash_balance((int)$c['id']);

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>السندات المالية</h2>
    <p>سند قبض = نقد داخل (يزيد الصندوق/البنك) · سند صرف = نقد خارج (ينقص الصندوق/البنك) — الترحيل المحاسبي المزدوج يتم تلقائياً لحظة الإصدار</p>
    <div class="quick-actions mt-3">
        <?php if ($canIssue): ?>
            <a href="<?php echo APP_URL; ?>modules/accounting/vouchers.php?tab=new" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>سند جديد</a>
        <?php endif; ?>
        <a href="<?php echo APP_URL; ?>modules/accounting/vouchers.php?tab=list" class="btn btn-secondary btn-sm"><i class="fas fa-list me-1"></i>سجل السندات</a>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?><div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div><?php endif; ?>

<div class="row g-3 mb-3 fade-in">
    <?php foreach ($cashAccounts as $c): ?>
        <div class="col-md-4"><div class="card h-100"><div class="card-body d-flex justify-content-between align-items-center">
            <div><div class="text-muted small"><?php echo e($c['name_ar']); ?> (<?php echo e($c['code']); ?>)</div><div class="fs-5 fw-bold"><?php echo number_format($balances[(int)$c['id']], 2); ?> ج.س</div></div>
            <i class="fas fa-wallet fa-lg text-muted"></i>
        </div></div></div>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'new' && $canIssue): ?>
<?php $oType = ($old['voucher_type'] ?? 'receipt') === 'payment' ? 'payment' : 'receipt'; ?>
<div class="card fade-in"><div class="card-header"><i class="fas fa-file-invoice me-2"></i>إصدار سند</div><div class="card-body">
<form method="post" id="voucherForm" autocomplete="off">
<?php echo csrf_field(); ?>
<div class="row g-3">
<div class="col-md-3"><label class="form-label">نوع السند *</label>
    <select name="voucher_type" id="vType" class="form-select" required>
        <option value="receipt" <?php echo $oType === 'receipt' ? 'selected' : ''; ?>>سند قبض (نقد داخل)</option>
        <option value="payment" <?php echo $oType === 'payment' ? 'selected' : ''; ?>>سند صرف (نقد خارج)</option>
    </select></div>
<div class="col-md-3"><label class="form-label">مكان النقد *</label>
    <select name="cash_account_id" id="vCash" class="form-select" required>
        <?php foreach ($cashAccounts as $c): ?><option value="<?php echo (int)$c['id']; ?>" data-balance="<?php echo e((string)$balances[(int)$c['id']]); ?>" <?php echo (int)($old['cash_account_id'] ?? 0) === (int)$c['id'] ? 'selected' : ''; ?>><?php echo e($c['name_ar']); ?> (<?php echo e($c['code']); ?>)</option><?php endforeach; ?>
    </select></div>
<div class="col-md-3"><label class="form-label">التاريخ *</label><input type="date" name="voucher_date" class="form-control" value="<?php echo e($old['voucher_date'] ?? $today); ?>" max="<?php echo $today; ?>" required></div>
<div class="col-md-3"><label class="form-label">المبلغ (ج.س) *</label><input type="number" step="0.01" min="0.01" name="amount" id="vAmount" class="form-control" value="<?php echo e($old['amount'] ?? ''); ?>" required></div>
<div class="col-md-6"><label class="form-label">الحساب المقابل * <small class="text-muted" id="vAccHint"></small></label>
    <select name="other_account_id" id="vOther" class="form-select" required>
        <option value="">— اختر —</option>
        <?php foreach ($incomeAccounts as $a): ?><option data-for="receipt" value="<?php echo (int)$a['id']; ?>" <?php echo (int)($old['other_account_id'] ?? 0) === (int)$a['id'] ? 'selected' : ''; ?>><?php echo e($a['name_ar']); ?> (<?php echo e($a['code']); ?>)</option><?php endforeach; ?>
        <?php foreach ($expenseAccounts as $a): ?><option data-for="payment" value="<?php echo (int)$a['id']; ?>" <?php echo (int)($old['other_account_id'] ?? 0) === (int)$a['id'] ? 'selected' : ''; ?>><?php echo e($a['name_ar']); ?> (<?php echo e($a['code']); ?>)</option><?php endforeach; ?>
    </select></div>
<div class="col-md-6"><label class="form-label" id="vPartyLabel">استلمنا من *</label><input type="text" name="party_name" class="form-control" maxlength="150" value="<?php echo e($old['party_name'] ?? ''); ?>" required></div>
<div class="col-md-8"><label class="form-label">البيان (سبب السند) *</label><input type="text" name="description" class="form-control" maxlength="255" value="<?php echo e($old['description'] ?? ''); ?>" required></div>
<div class="col-md-4"><label class="form-label">رقم المرجع <small class="text-muted">(اختياري)</small></label><input type="text" name="reference_number" class="form-control" maxlength="100" value="<?php echo e($old['reference_number'] ?? ''); ?>"></div>
</div>
<div class="alert alert-info mt-3 mb-0" id="vEffect"></div>
<div class="mt-3 d-flex gap-2"><button name="save_voucher" value="1" class="btn btn-primary"><i class="fas fa-save me-1"></i>إصدار السند وترحيله</button></div>
</form></div></div>
<div class="d-none">
    <span id="txtPartyReceipt">استلمنا من *</span><span id="txtPartyPayment">صرفنا إلى *</span>
    <span id="txtHintReceipt">(حساب إيراد)</span><span id="txtHintPayment">(حساب مصروف)</span>
    <span id="txtEffectReceipt">سند القبض يزيد رصيد مكان النقد المختار بقيمة المبلغ ويسجّل إيراداً.</span>
    <span id="txtEffectPayment">سند الصرف ينقص رصيد مكان النقد المختار بقيمة المبلغ ويسجّل مصروفاً — الرصيد المتاح:</span>
    <span id="txtEffectAfter">الرصيد بعد السند:</span><span id="txtCur">ج.س</span>
</div>
<script>
(function () {
    var type = document.getElementById('vType'), other = document.getElementById('vOther'), cash = document.getElementById('vCash'), amount = document.getElementById('vAmount');
    function txt(id) { return document.getElementById(id).textContent; }
    function refresh() {
        var t = type.value;
        Array.prototype.forEach.call(other.options, function (o) {
            if (!o.getAttribute('data-for')) return;
            var show = o.getAttribute('data-for') === t;
            o.hidden = !show; o.disabled = !show;
            if (!show && o.selected) other.value = '';
        });
        document.getElementById('vPartyLabel').textContent = t === 'receipt' ? txt('txtPartyReceipt') : txt('txtPartyPayment');
        document.getElementById('vAccHint').textContent = t === 'receipt' ? txt('txtHintReceipt') : txt('txtHintPayment');
        var bal = parseFloat(cash.options[cash.selectedIndex].getAttribute('data-balance')) || 0;
        var amt = parseFloat(amount.value) || 0;
        var cur = txt('txtCur');
        var msg = t === 'receipt' ? txt('txtEffectReceipt') : txt('txtEffectPayment') + ' ' + bal.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ' + cur;
        if (amt > 0) {
            var after = t === 'receipt' ? bal + amt : bal - amt;
            msg += ' · ' + txt('txtEffectAfter') + ' ' + after.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' ' + cur;
        }
        var box = document.getElementById('vEffect');
        box.textContent = msg;
        box.className = 'alert mt-3 mb-0 ' + (t === 'payment' && amt > bal ? 'alert-danger' : 'alert-info');
    }
    [type, cash, amount].forEach(function (el) { el.addEventListener('input', refresh); el.addEventListener('change', refresh); });
    refresh();
})();
</script>
<?php endif; ?>

<?php if ($tab === 'list'): ?>
<div class="card fade-in"><div class="card-body">
<div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">سجل السندات</h5><form method="get" class="d-flex gap-2"><input type="hidden" name="tab" value="list"><select name="vtype" class="form-select form-select-sm"><option value="">الكل</option><option value="receipt" <?php echo $fType==='receipt'?'selected':''; ?>>قبض</option><option value="payment" <?php echo $fType==='payment'?'selected':''; ?>>صرف</option></select><button class="btn btn-sm btn-outline-primary">تصفية</button></form></div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>الرقم</th><th>التاريخ</th><th>النوع</th><th>الجهة</th><th>المبلغ</th><th>مكان النقد</th><th>أصدره</th><th>الحالة</th><th>القيد</th><th>إجراء</th></tr></thead><tbody>
<?php if (!$list): ?><tr><td colspan="10" class="text-center text-muted py-4">لا توجد سندات.</td></tr><?php endif; ?>
<?php foreach ($list as $v): ?><tr>
<td><code><?php echo e($v['voucher_no']); ?></code></td>
<td><?php echo e($v['voucher_date']); ?></td>
<td><?php echo $v['voucher_type']==='receipt'?'<span class="badge bg-success">قبض</span>':'<span class="badge bg-danger">صرف</span>'; ?></td>
<td><?php echo e($v['party_name']??''); ?></td>
<td><?php echo number_format((float)$v['amount'],2); ?></td>
<td><?php echo e($v['cash_name']); ?></td>
<td><?php echo e($v['creator'] ?? ''); ?></td>
<td><?php echo $v['status']==='posted'?'<span class="badge bg-success">مرحّل</span>':'<span class="badge bg-secondary">مبطل</span>'; ?></td>
<td><?php echo $v['entry_id'] ? '<code>#'.(int)$v['entry_id'].'</code>' : '—'; ?></td>
<td class="text-nowrap">
    <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?php echo APP_URL; ?>modules/accounting/voucher_print.php?id=<?php echo (int)$v['id']; ?>"><i class="fas fa-print me-1"></i>طباعة</a>
    <?php if ($canVoid && $v['status']==='posted' && $v['entry_id']): ?>
        <form method="post" class="d-inline js-void-form"><?php echo csrf_field(); ?><input type="hidden" name="void_reason" value=""><button name="void_voucher" value="<?php echo (int)$v['id']; ?>" class="btn btn-sm btn-outline-danger">إبطال</button></form>
    <?php endif; ?>
</td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<div class="d-none"><span id="txtVoidPrompt">اكتب سبب إبطال السند (مطلوب):</span></div>
<script>
document.querySelectorAll('.js-void-form').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
        var reason = window.prompt(document.getElementById('txtVoidPrompt').textContent, '');
        if (reason === null || reason.trim().length < 3) { ev.preventDefault(); return; }
        f.querySelector('input[name="void_reason"]').value = reason.trim();
    });
});
</script>
<?php endif; ?>

<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="<?php echo APP_URL; ?>modules/accounting/index.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
