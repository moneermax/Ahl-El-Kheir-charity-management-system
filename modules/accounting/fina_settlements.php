<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/fina_settlement_lib.php';

Session::start();
$role = (string) Session::getUserRole();
if (!Session::isLoggedIn() || $role !== 'financial_manager') {
    http_response_code(403);
    include dirname(__DIR__, 2) . '/includes/header.php';
    echo '<div class="container py-5"><div class="alert alert-danger"><i class="fas fa-lock me-1"></i>تسويات فينا الخير متاحة للمدير المالي فقط.</div></div>';
    exit;
}

fina_settlement_ensure_tables();
$pageTitle = 'تسويات فينا الخير';
$active = 'fina_settlements';
$uid = (int) Session::getUserId();

function fina_settlement_status_label(string $status): string
{
    $labels = [
        'draft' => '<span class="badge bg-secondary">مسودة</span>',
        'approved' => '<span class="badge bg-warning text-dark">معتمدة للتحويل</span>',
        'transferred' => '<span class="badge bg-primary">تم التحويل</span>',
        'reconciled' => '<span class="badge bg-info text-dark">تمت المطابقة</span>',
        'closed' => '<span class="badge bg-success">مغلقة</span>',
        'cancelled' => '<span class="badge bg-danger">ملغاة</span>',
    ];
    return $labels[$status] ?? e($status);
}

function fina_settlement_payment_label(string $method): string
{
    $labels = [
        'cash' => 'نقدي',
        'bank_transfer' => 'تحويل بنكي',
        'credit_card' => 'بطاقة',
        'mobile' => 'محفظة إلكترونية',
        'other' => 'أخرى',
    ];
    return $labels[$method] ?? $method;
}

function fina_settlement_upload_evidence(): ?string
{
    if (!isset($_FILES['evidence']) || ($_FILES['evidence']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES['evidence']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('تعذر رفع مستند إثبات التحويل.');
    }
    if ((int) $_FILES['evidence']['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('حجم مستند إثبات التحويل يجب ألا يتجاوز 5 ميجابايت.');
    }

    $original = (string) $_FILES['evidence']['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('صيغة إثبات التحويل يجب أن تكون PDF أو JPG أو JPEG أو PNG.');
    }

    $dir = dirname(__DIR__, 2) . '/storage/receipts/fina_settlements';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('تعذر إنشاء مجلد مستندات تسويات فينا الخير.');
    }

    $safe = 'fina_settlement_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $dir . '/' . $safe;
    if (!move_uploaded_file($_FILES['evidence']['tmp_name'], $target)) {
        throw new RuntimeException('تعذر حفظ مستند إثبات التحويل.');
    }
    return 'storage/receipts/fina_settlements/' . $safe;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    try {
        if (isset($_POST['create_settlement'])) {
            $allocations = [];
            foreach ((array) ($_POST['alloc'] ?? []) as $collectionId => $amount) {
                $allocations[(int) $collectionId] = $amount;
            }
            $amount = round((float) str_replace(',', '', (string) ($_POST['amount'] ?? 0)), 2);
            $id = fina_settlement_create_draft([
                'settlement_date' => (string) ($_POST['settlement_date'] ?? ''),
                'amount' => $amount,
                'currency_code' => APP_CURRENCY_CODE,
                'payment_method' => (string) ($_POST['payment_method'] ?? ''),
                'remitting_account_id' => (int) ($_POST['remitting_account_id'] ?? 0),
                'allocations' => $allocations,
            ], $uid);
            flash('success', 'تم إنشاء مسودة تسوية فينا الخير بنجاح.');
            header('Location: ' . APP_URL . 'modules/accounting/fina_settlements.php?view=' . $id);
            exit;
        }

        if (isset($_POST['approve_settlement'])) {
            fina_settlement_approve((int) $_POST['approve_settlement'], $uid);
            flash('success', 'تم اعتماد مسودة التسوية.');
        } elseif (isset($_POST['transfer_settlement'])) {
            $evidence = fina_settlement_upload_evidence();
            try {
                $journalId = fina_settlement_transfer(
                    (int) $_POST['transfer_settlement'],
                    (string) ($_POST['transfer_reference'] ?? ''),
                    $evidence,
                    (string) ($_POST['actual_date'] ?? ''),
                    $uid
                );
                flash('success', 'تم تسجيل التحويل الفعلي وترحيل قيد التسوية رقم #' . $journalId . '.');
            } catch (Throwable $e) {
                if ($evidence) {
                    $absolute = dirname(__DIR__, 2) . '/' . $evidence;
                    if (is_file($absolute)) @unlink($absolute);
                }
                throw $e;
            }
        } elseif (isset($_POST['reconcile_settlement'])) {
            fina_settlement_mark_reconciled((int) $_POST['reconcile_settlement'], (string) ($_POST['reconciliation_note'] ?? ''), $uid);
            flash('success', 'تمت مطابقة تسوية فينا الخير.');
        } elseif (isset($_POST['close_settlement'])) {
            fina_settlement_close((int) $_POST['close_settlement'], $uid);
            flash('success', 'تم إغلاق تسوية فينا الخير.');
        } elseif (isset($_POST['cancel_settlement'])) {
            fina_settlement_cancel((int) $_POST['cancel_settlement'], (string) ($_POST['cancellation_note'] ?? ''), $uid);
            flash('success', 'تم إلغاء مسودة/تسوية فينا الخير.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    $redirect = APP_URL . 'modules/accounting/fina_settlements.php';
    if (!empty($_POST['settlement_id'])) $redirect .= '?view=' . (int) $_POST['settlement_id'];
    header('Location: ' . $redirect);
    exit;
}

$accounts = dbFetchAll("SELECT id,code,name_ar FROM accounts WHERE is_active=1 AND account_type='asset' AND code IN ('1100','1200','1300') ORDER BY FIELD(code,'1100','1200','1300')");
$outstanding = fina_settlement_outstanding_total();

$settlements = dbFetchAll(
    "SELECT s.*, a.code remitting_code, a.name_ar remitting_name, u.full_name creator_name
       FROM fina_settlements s
       LEFT JOIN accounts a ON a.id=s.remitting_account_id
       LEFT JOIN users u ON u.id=s.created_by
      ORDER BY s.id DESC
      LIMIT 100"
);

$eligible = dbFetchAll(
    "SELECT c.id,c.amount,c.currency_code,c.payment_method,c.collection_date,c.status,c.accounting_journal_id,
            fs.source_type,fs.source_name,s.full_name sponsor_name,s.sponsor_code,
            COALESCE(x.allocated,0) allocated_amount,
            (c.amount-COALESCE(x.allocated,0)) remaining_amount
       FROM fina_collections c
       JOIN fina_sources fs ON fs.id=c.fina_source_id
       LEFT JOIN sponsors s ON s.id=fs.sponsor_id
       LEFT JOIN (
           SELECT a.fina_collection_id,SUM(a.allocated_amount) allocated
             FROM fina_settlement_allocations a
             JOIN fina_settlements st ON st.id=a.settlement_id
            WHERE st.status <> 'cancelled'
            GROUP BY a.fina_collection_id
       ) x ON x.fina_collection_id=c.id
      WHERE c.status='approved'
        AND c.currency_code=?
        AND (c.amount-COALESCE(x.allocated,0)) > 0
      ORDER BY c.collection_date ASC,c.id ASC",
    [APP_CURRENCY_CODE]
);

$viewId = (int) ($_GET['view'] ?? 0);
$view = null;
$viewAllocations = [];
if ($viewId > 0) {
    $view = dbFetchOne(
        "SELECT s.*,a.code remitting_code,a.name_ar remitting_name,u.full_name creator_name,au.full_name approver_name,
                tu.full_name transferor_name,ru.full_name reconciler_name,cu.full_name canceller_name
           FROM fina_settlements s
           LEFT JOIN accounts a ON a.id=s.remitting_account_id
           LEFT JOIN users u ON u.id=s.created_by
           LEFT JOIN users au ON au.id=s.approved_by
           LEFT JOIN users tu ON tu.id=s.transferred_by
           LEFT JOIN users ru ON ru.id=s.reconciled_by
           LEFT JOIN users cu ON cu.id=s.cancelled_by
          WHERE s.id=? LIMIT 1",
        [$viewId]
    );
    if ($view) {
        $viewAllocations = dbFetchAll(
            "SELECT a.fina_collection_id,a.allocated_amount,c.amount,c.currency_code,c.collection_date,c.status,c.payment_method,
                    fs.source_type,fs.source_name,s.full_name sponsor_name,s.sponsor_code
               FROM fina_settlement_allocations a
               JOIN fina_collections c ON c.id=a.fina_collection_id
               JOIN fina_sources fs ON fs.id=c.fina_source_id
               LEFT JOIN sponsors s ON s.id=fs.sponsor_id
              WHERE a.settlement_id=? ORDER BY a.id",
            [$viewId]
        );
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2><i class="fas fa-building-columns me-2"></i>تسويات فينا الخير</h2>
            <p class="text-muted mb-0">تسوية التزام الطرف الثالث من الحساب 2300 — للمدير المالي فقط.</p>
        </div>
        <a href="<?php echo APP_URL; ?>modules/accounting/fm_dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-right me-1"></i>العودة إلى لوحة المدير المالي</a>
    </div>

    <?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-warning h-100"><div class="card-body"><div class="text-muted">الالتزام المعتمد غير المسدد</div><div class="fs-3 fw-bold"><?php echo number_format($outstanding, 2); ?> <?php echo e(APP_CURRENCY_CODE); ?></div><div class="small text-muted">من التحصيلات المعتمدة مطروحاً منها تخصيصات التسويات غير الملغاة.</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted">عدد التسويات المسجلة</div><div class="fs-3 fw-bold"><?php echo count($settlements); ?></div><div class="small text-muted">السجل يعرض آخر 100 تسوية.</div></div></div></div>
        <div class="col-md-4"><div class="card border-success h-100"><div class="card-body"><div class="text-muted">قاعدة المحاسبة</div><div class="fs-5 fw-bold">مدين 2300 ← دائن الخزينة</div><div class="small text-muted">التسوية ليست إيراداً ولا مصروفاً لأهل الخير.</div></div></div></div>
    </div>

    <?php if ($view): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center"><strong>تفاصيل <?php echo e($view['settlement_code']); ?></strong><?php echo fina_settlement_status_label($view['status']); ?></div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><span class="text-muted d-block">المبلغ</span><strong><?php echo number_format((float)$view['amount'],2); ?> <?php echo e($view['currency_code']); ?></strong></div>
                    <div class="col-md-3"><span class="text-muted d-block">التاريخ الفعلي</span><strong><?php echo e($view['settlement_date']); ?></strong></div>
                    <div class="col-md-3"><span class="text-muted d-block">طريقة التحويل</span><strong><?php echo e(fina_settlement_payment_label($view['payment_method'])); ?></strong></div>
                    <div class="col-md-3"><span class="text-muted d-block">حساب التحويل</span><strong><?php echo e($view['remitting_code'] . ' — ' . $view['remitting_name']); ?></strong></div>
                </div>
                <?php if ($view['transfer_reference']): ?><div class="alert alert-light border"><strong>مرجع التحويل:</strong> <?php echo e($view['transfer_reference']); ?><?php if ($view['settlement_journal_id']): ?> — <strong>قيد التسوية:</strong> #<?php echo (int)$view['settlement_journal_id']; ?><?php endif; ?></div><?php endif; ?>

                <h5 class="mt-3">تخصيصات التحصيلات</h5>
                <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>#</th><th>المصدر</th><th>تاريخ التحصيل</th><th>المعتمد</th><th>المخصص للتسوية</th></tr></thead><tbody>
                <?php foreach ($viewAllocations as $a): ?>
                    <tr><td><?php echo (int)$a['fina_collection_id']; ?></td><td><?php echo e(($a['sponsor_name'] ?: $a['source_name'] ?: '—')); ?></td><td><?php echo e($a['collection_date']); ?></td><td><?php echo number_format((float)$a['amount'],2); ?> <?php echo e($a['currency_code']); ?></td><td><strong><?php echo number_format((float)$a['allocated_amount'],2); ?></strong></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <?php if ($view['status'] === 'draft'): ?>
                        <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="settlement_id" value="<?php echo $viewId; ?>"><button name="approve_settlement" value="<?php echo $viewId; ?>" class="btn btn-success" onclick="return confirm('اعتماد مسودة التسوية؟');"><i class="fas fa-check me-1"></i>اعتماد</button></form>
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"><i class="fas fa-ban me-1"></i>إلغاء المسودة</button>
                    <?php elseif ($view['status'] === 'approved'): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transferModal"><i class="fas fa-money-bill-transfer me-1"></i>تسجيل التحويل الفعلي</button>
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"><i class="fas fa-ban me-1"></i>إلغاء</button>
                    <?php elseif ($view['status'] === 'transferred'): ?>
                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#reconcileModal"><i class="fas fa-scale-balanced me-1"></i>مطابقة</button>
                    <?php elseif ($view['status'] === 'reconciled'): ?>
                        <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="settlement_id" value="<?php echo $viewId; ?>"><button name="close_settlement" value="<?php echo $viewId; ?>" class="btn btn-success" onclick="return confirm('إغلاق التسوية بعد اكتمال المطابقة؟');"><i class="fas fa-lock me-1"></i>إغلاق التسوية</button></form>
                    <?php endif; ?>
                    <?php if ($view['evidence_path']): ?><a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?php echo APP_URL . e($view['evidence_path']); ?>"><i class="fas fa-paperclip me-1"></i>إثبات التحويل</a><?php endif; ?>
                </div>

                <?php if ($view['status'] === 'closed'): ?>
                    <div class="alert alert-success mt-3 mb-0"><i class="fas fa-circle-check me-1"></i>تمت مطابقة التسوية وإغلاقها. يحتفظ النظام بالسجل والقيد والتخصيصات التاريخية.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($viewId > 0): ?>
        <div class="alert alert-warning">سجل التسوية المطلوب غير موجود.</div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><strong>إنشاء مسودة تسوية جديدة</strong></div>
        <div class="card-body">
            <?php if (!$eligible): ?>
                <div class="alert alert-info mb-0">لا توجد حالياً تحصيلات فينا الخير معتمدة ولها رصيد قابل للتسوية.</div>
            <?php else: ?>
                <form method="post" id="createSettlementForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="create_settlement" value="1">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><label class="form-label">تاريخ التسوية</label><input type="date" name="settlement_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                        <div class="col-md-3"><label class="form-label">المبلغ</label><input type="number" step="0.01" min="0.01" name="amount" id="settlementAmount" class="form-control" required><div class="form-text">يجب أن يساوي مجموع التخصيصات.</div></div>
                        <div class="col-md-3"><label class="form-label">طريقة التحويل</label><select name="payment_method" class="form-select" required><option value="">اختر</option><option value="bank_transfer">تحويل بنكي</option><option value="cash">نقدي</option><option value="mobile">محفظة إلكترونية</option><option value="credit_card">بطاقة</option><option value="other">أخرى</option></select></div>
                        <div class="col-md-3"><label class="form-label">حساب التحويل</label><select name="remitting_account_id" class="form-select" required><option value="">اختر حساب الخزينة</option><?php foreach ($accounts as $account): ?><option value="<?php echo (int)$account['id']; ?>"><?php echo e($account['code'] . ' — ' . $account['name_ar']); ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0">التحصيلات القابلة للتخصيص</h5><button type="button" class="btn btn-outline-secondary btn-sm" id="fillAll"><i class="fas fa-list-check me-1"></i>تخصيص المتاح بالكامل</button></div>
                    <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>اختيار</th><th>المصدر</th><th>تاريخ التحصيل</th><th>المبلغ الأصلي</th><th>المتبقي القابل للتسوية</th><th>المخصص</th></tr></thead><tbody>
                    <?php foreach ($eligible as $c): ?>
                        <tr>
                            <td><input type="checkbox" class="form-check-input alloc-check" data-id="<?php echo (int)$c['id']; ?>"></td>
                            <td><?php echo e($c['sponsor_name'] ?: $c['source_name'] ?: '—'); ?><div class="small text-muted">تحصيل #<?php echo (int)$c['id']; ?></div></td>
                            <td><?php echo e($c['collection_date']); ?></td>
                            <td><?php echo number_format((float)$c['amount'],2); ?> <?php echo e($c['currency_code']); ?></td>
                            <td><strong><?php echo number_format((float)$c['remaining_amount'],2); ?></strong></td>
                            <td><input type="number" step="0.01" min="0" max="<?php echo e((string)$c['remaining_amount']); ?>" class="form-control form-control-sm alloc-input" name="alloc[<?php echo (int)$c['id']; ?>]" value="0" data-id="<?php echo (int)$c['id']; ?>" data-max="<?php echo e((string)$c['remaining_amount']); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                    <div class="alert alert-light border d-flex justify-content-between"><span>إجمالي التخصيص</span><strong id="allocationTotal">0.00 <?php echo e(APP_CURRENCY_CODE); ?></strong></div>
                    <button class="btn btn-primary" type="submit"><i class="fas fa-file-circle-plus me-1"></i>إنشاء مسودة التسوية</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>سجل تسويات فينا الخير</strong></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>المرجع</th><th>التاريخ</th><th>المبلغ</th><th>حساب التحويل</th><th>الحالة</th><th>مرجع التحويل</th><th></th></tr></thead><tbody>
        <?php if (!$settlements): ?><tr><td colspan="7" class="text-center text-muted py-4">لا توجد تسويات مسجلة بعد.</td></tr><?php else: foreach ($settlements as $s): ?>
            <tr><td><strong><?php echo e($s['settlement_code']); ?></strong></td><td><?php echo e($s['settlement_date']); ?></td><td><?php echo number_format((float)$s['amount'],2); ?> <?php echo e($s['currency_code']); ?></td><td><?php echo e($s['remitting_code'] . ' — ' . $s['remitting_name']); ?></td><td><?php echo fina_settlement_status_label($s['status']); ?></td><td><?php echo e($s['transfer_reference'] ?: '—'); ?></td><td><a href="<?php echo APP_URL; ?>modules/accounting/fina_settlements.php?view=<?php echo (int)$s['id']; ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-eye me-1"></i>التفاصيل</a></td></tr>
        <?php endforeach; endif; ?>
        </tbody></table></div></div>
    </div>
</div>

<?php if ($view && $view['status'] === 'approved'): ?>
<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="settlement_id" value="<?php echo $viewId; ?>"><div class="modal-header"><h5 class="modal-title">تسجيل التحويل الفعلي</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="text-muted">هذه الخطوة تنشئ القيد المحاسبي: مدين 2300 / دائن حساب التحويل.</p><div class="mb-3"><label class="form-label">تاريخ التحويل الفعلي</label><input type="date" name="actual_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div><div class="mb-3"><label class="form-label">مرجع التحويل</label><input type="text" name="transfer_reference" class="form-control" maxlength="255" required></div><div class="mb-3"><label class="form-label">إثبات التحويل</label><input type="file" name="evidence" class="form-control" accept=".pdf,.jpg,.jpeg,.png"><div class="form-text">اختياري حالياً — PDF/JPG/PNG حتى 5 ميجابايت.</div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button name="transfer_settlement" value="<?php echo $viewId; ?>" class="btn btn-primary" onclick="return confirm('تسجيل التحويل الفعلي وترحيل قيد التسوية؟');">تسجيل التحويل</button></div></form></div></div></div>
<?php endif; ?>
<?php if ($view && in_array($view['status'], ['draft','approved'], true)): ?>
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="settlement_id" value="<?php echo $viewId; ?>"><div class="modal-header"><h5 class="modal-title">إلغاء التسوية</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">سبب الإلغاء</label><textarea name="cancellation_note" class="form-control" rows="4" required></textarea></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button name="cancel_settlement" value="<?php echo $viewId; ?>" class="btn btn-danger">تأكيد الإلغاء</button></div></form></div></div></div>
<?php endif; ?>
<?php if ($view && $view['status'] === 'transferred'): ?>
<div class="modal fade" id="reconcileModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="settlement_id" value="<?php echo $viewId; ?>"><div class="modal-header"><h5 class="modal-title">مطابقة التسوية</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">ملاحظة المطابقة</label><textarea name="reconciliation_note" class="form-control" rows="4" placeholder="اختياري"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button name="reconcile_settlement" value="<?php echo $viewId; ?>" class="btn btn-info">تأكيد المطابقة</button></div></form></div></div></div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const amount = document.getElementById('settlementAmount');
    const total = document.getElementById('allocationTotal');
    const inputs = Array.from(document.querySelectorAll('.alloc-input'));
    const checks = Array.from(document.querySelectorAll('.alloc-check'));
    function recalc() {
        let sum = 0;
        inputs.forEach(function (input) { sum += parseFloat(input.value || '0') || 0; });
        sum = Math.round(sum * 100) / 100;
        if (total) total.textContent = sum.toFixed(2) + ' <?php echo e(APP_CURRENCY_CODE); ?>';
        if (amount) amount.value = sum > 0 ? sum.toFixed(2) : '';
    }
    inputs.forEach(function (input) {
        input.addEventListener('input', function () {
            const max = parseFloat(input.dataset.max || '0');
            let value = parseFloat(input.value || '0') || 0;
            if (value < 0) value = 0;
            if (value > max) value = max;
            input.value = value ? value.toFixed(2) : '0';
            const check = checks.find(function (c) { return c.dataset.id === input.dataset.id; });
            if (check) check.checked = value > 0;
            recalc();
        });
    });
    checks.forEach(function (check) {
        check.addEventListener('change', function () {
            const input = inputs.find(function (i) { return i.dataset.id === check.dataset.id; });
            if (!input) return;
            if (check.checked && parseFloat(input.value || '0') === 0) input.value = parseFloat(input.dataset.max || '0').toFixed(2);
            if (!check.checked) input.value = '0';
            recalc();
        });
    });
    const fillAll = document.getElementById('fillAll');
    if (fillAll) fillAll.addEventListener('click', function () {
        inputs.forEach(function (input) { input.value = parseFloat(input.dataset.max || '0').toFixed(2); });
        checks.forEach(function (check) { check.checked = true; });
        recalc();
    });
});
</script>
