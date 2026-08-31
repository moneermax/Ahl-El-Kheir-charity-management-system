<?php
/**
 * modules/accounting/opening_balance.php
 *
 * A guided, foolproof way to record the organization's true starting cash
 * position, built specifically because the general-purpose manual journal
 * entry tool (journal_create.php) requires understanding double-entry
 * bookkeeping well enough to pick two DIFFERENT accounts — an easy mistake
 * to make otherwise (using the same account for both sides silently
 * produces a zero-effect entry, which is exactly what happened before this
 * tool existed).
 *
 * This page never lets the user choose accounts at all. It only ever posts
 * to the three real asset accounts (1100 Cash, 1200 Bank, 1300 Wallet) as
 * debits, and the Opening Balances equity account (3100) as the single
 * matching credit — the only shape an opening balance entry can correctly
 * take. There is no way to use this tool and produce an invalid entry.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'financial_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = 'الرصيد الافتتاحي';
$active    = 'opening_balance';
ak_ensure_tables(); ak_seed_accounts();

$uid = Session::getUserId();
$errors = [];

// Has an opening balance ever been posted before? We don't hard-block a second one
// (maybe the first was voided and needs redoing, exactly like today), but we warn
// clearly so nobody accidentally records the starting balance twice.
$existingCount = (int)(dbFetchOne(
    "SELECT COUNT(*) c FROM journal_entries WHERE reference_type = 'opening_balance' AND status = 'posted'"
)['c'] ?? 0);
$existingEntries = dbFetchAll(
    "SELECT je.id, je.entry_code, je.entry_date, je.status,
     (SELECT COALESCE(SUM(jl.debit),0) FROM journal_lines jl WHERE jl.entry_id = je.id) AS total
     FROM journal_entries je WHERE je.reference_type = 'opening_balance' ORDER BY je.id DESC"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_opening_balance'])) {
    if (!verify_csrf()) {
        $errors[] = 'انتهت صلاحية الجلسة، حاول مرة أخرى.';
    } else {
        $cash   = (float)str_replace(',', '', (string)($_POST['cash'] ?? 0));
        $bank   = (float)str_replace(',', '', (string)($_POST['bank'] ?? 0));
        $wallet = (float)str_replace(',', '', (string)($_POST['wallet'] ?? 0));
        $date   = trim($_POST['as_of_date'] ?? '') ?: date('Y-m-d');
        $confirmed = isset($_POST['confirm_understood']);

        if ($cash < 0 || $bank < 0 || $wallet < 0) {
            $errors[] = 'لا يمكن إدخال مبلغ سالب.';
        }
        $total = $cash + $bank + $wallet;
        if ($total <= 0) {
            $errors[] = 'أدخل مبلغاً واحداً على الأقل أكبر من صفر.';
        }
        if (!$confirmed) {
            $errors[] = 'يرجى تأكيد أن هذا هو الرصيد الفعلي الحقيقي قبل المتابعة.';
        }

        if (!$errors) {
            $cashId   = ak_account_id('1100');
            $bankId   = ak_account_id('1200');
            $walletId = ak_account_id('1300');
            $obId     = ak_account_id('3100');

            if (!$cashId || !$bankId || !$walletId || !$obId) {
                $errors[] = 'أحد الحسابات الأساسية (1100/1200/1300/3100) غير موجود في دليل الحسابات. راجع صفحة "دليل الحسابات" أولاً.';
            } else {
                $n = (int)(dbFetchOne("SELECT COUNT(*) c FROM journal_entries")['c'] ?? 0) + 1;
                $code = 'JE-OB-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);

                dbExecute(
                    "INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, status, created_by)
                     VALUES (?, ?, ?, 'opening_balance', 'posted', ?)",
                    [$code, $date, 'الرصيد الافتتاحي الحقيقي للمنظمة', $uid]
                );
                $eid = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);

                if ($cash > 0)   dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,0,?)", [$eid, $cashId, $cash, 'رصيد افتتاحي — نقدي']);
                if ($bank > 0)   dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,0,?)", [$eid, $bankId, $bank, 'رصيد افتتاحي — بنكي']);
                if ($wallet > 0) dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,0,?)", [$eid, $walletId, $wallet, 'رصيد افتتاحي — محفظة إلكترونية']);
                dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,0,?,?)", [$eid, $obId, $total, 'الأرصدة الافتتاحية']);

                try {
                    dbExecute(
                        "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                         VALUES (?, 'CREATE_OPENING_BALANCE', 'journal_entries', ?, NULL, ?, ?, ?)",
                        [$uid, $eid, json_encode(['cash' => $cash, 'bank' => $bank, 'wallet' => $wallet], JSON_UNESCAPED_UNICODE),
                         $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
                    );
                } catch (Throwable $e) {}

                flash('success', 'تم تسجيل الرصيد الافتتاحي بنجاح: ' . number_format($total, 2) . ' ج.س (' . $code . ')');
                header('Location: ' . APP_URL . 'modules/accounting/journal.php?view=' . $eid); exit();
            }
        }
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-vault me-2"></i>الرصيد الافتتاحي</h2>
    <p>سجّل هنا المبلغ الحقيقي الذي كان موجوداً فعلياً في الصندوق/البنك/المحفظة عند بدء استخدام النظام. هذه الأداة مخصصة لهذه العملية فقط، ولا تسمح بأي خطأ محاسبي — على عكس القيد اليدوي العام.</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?><div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . $er . '</li>'; ?></ul></div><?php endif; ?>

<?php if ($existingCount > 0): ?>
<div class="alert alert-warning fade-in">
    <i class="fas fa-triangle-exclamation me-1"></i>
    <strong>تنبيه:</strong> يوجد بالفعل <?php echo $existingCount; ?> قيد رصيد افتتاحي مُرحّل مسبقاً. تسجيل رصيد جديد هنا سيُضاف فوق أي رصيد سابق ولن يستبدله — إذا كان الرصيد السابق خاطئاً، أبطله أولاً من صفحة "دفتر القيود" قبل المتابعة.
</div>
<?php endif; ?>

<?php if ($existingEntries): ?>
<div class="card mb-4 fade-in">
    <div class="card-header bg-white"><i class="fas fa-clock-rotate-left me-2"></i>سجل الأرصدة الافتتاحية السابقة</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>الكود</th><th>التاريخ</th><th>المبلغ</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($existingEntries as $e): ?>
                <tr>
                    <td><code><?php echo e($e['entry_code']); ?></code></td>
                    <td><?php echo e($e['entry_date']); ?></td>
                    <td><?php echo number_format((float)$e['total'], 2); ?></td>
                    <td><?php echo $e['status'] === 'posted' ? '<span class="badge bg-success">مرحّل</span>' : '<span class="badge bg-danger">مبطل</span>'; ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="<?php echo APP_URL; ?>modules/accounting/journal.php?view=<?php echo (int)$e['id']; ?>">عرض</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-header bg-white"><i class="fas fa-plus me-2"></i>تسجيل رصيد افتتاحي جديد</div>
    <div class="card-body">
        <form method="post">
            <?php echo csrf_field(); ?>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-money-bill-wave text-success me-1"></i> النقدي في الصندوق (1100)</label>
                    <input type="number" step="0.01" min="0" name="cash" class="form-control form-control-lg" placeholder="0.00">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-building-columns text-primary me-1"></i> الرصيد في البنك (1200)</label>
                    <input type="number" step="0.01" min="0" name="bank" class="form-control form-control-lg" placeholder="0.00">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-wallet text-warning me-1"></i> المحفظة الإلكترونية (1300)</label>
                    <input type="number" step="0.01" min="0" name="wallet" class="form-control form-control-lg" placeholder="0.00">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">تاريخ الرصيد</label>
                <input type="date" name="as_of_date" class="form-control" style="max-width:220px" value="<?php echo date('Y-m-d'); ?>">
                <div class="form-text">التاريخ الذي يمثل فيه هذا المبلغ الرصيد الفعلي — عادة تاريخ بدء استخدام النظام.</div>
            </div>
            <div class="alert alert-light border">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="confirm_understood" id="confirmBox" required>
                    <label class="form-check-label" for="confirmBox">
                        أؤكد أن هذه هي المبالغ الحقيقية الفعلية الموجودة حالياً، وأنني أدخلها لأول مرة (أو بعد إبطال رصيد سابق خاطئ).
                    </label>
                </div>
            </div>
            <button type="submit" name="save_opening_balance" value="1" class="btn btn-primary btn-lg">
                <i class="fas fa-save me-1"></i> تسجيل الرصيد الافتتاحي
            </button>
        </form>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>