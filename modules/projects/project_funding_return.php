<?php
// modules/projects/project_funding_return.php - FM controlled-fund return
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
Session::start();

$id = (int)($_GET['id'] ?? $_POST['project_id'] ?? 0);
$project = $id ? akp_get_project($id) : null;
if (!$project) { flash('error', 'المشروع غير موجود.'); redirect('modules/projects/index.php'); }
if (akp_role() !== 'financial_manager' || !akp_can_view_project($id)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة.');
        redirect('modules/projects/project_funding_return.php?id=' . $id);
    }
    try {
        $allocationId = (int)($_POST['allocation_id'] ?? 0);
        $amount = (float)($_POST['return_amount'] ?? 0);
        $returnDate = trim((string)($_POST['return_date'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $reference = trim((string)($_POST['return_reference'] ?? '')) ?: null;
        $description = trim((string)($_POST['return_description'] ?? '')) ?: null;

        $entryId = akp_return_project_funding($id, $allocationId, $amount, $returnDate, $reference, $description);
        akp_audit('PROJECT_FUNDING_RETURN', 'project_funding_return', $allocationId, null, [
            'project_id' => $id,
            'amount' => $amount,
            'journal_entry_id' => $entryId
        ]);
        flash('success', 'تم إرجاع الرصيد المتبقي إلى حساب المؤسسة وإنشاء القيد المحاسبي رقم ' . (string)(dbFetchOne('SELECT entry_code FROM journal_entries WHERE id=?', [$entryId])['entry_code'] ?? $entryId) . '.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('modules/projects/project_funding_return.php?id=' . $id);
}

$allocations = dbFetchAll(
    "SELECT f.id, f.amount, f.currency_code, a.code AS source_account_code, a.name_ar AS source_account_name,
            r.id AS return_id, r.amount AS returned_amount, r.journal_entry_id
     FROM project_funding_allocations f
     JOIN accounts a ON a.id=f.source_account_id
     LEFT JOIN project_funding_returns r ON r.funding_allocation_id=f.id
     WHERE f.project_id=? AND f.status='posted'
     ORDER BY f.id",
    [$id]
);
$controlledBalance = akp_project_controlled_balance($id);
$currency = $project['currency_code'] ?: 'SDG';

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="container-fluid py-4" dir="rtl">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="h4 mb-0">إرجاع الرصيد المتبقي للمشروع</h1>
        <a class="btn btn-outline-secondary" href="<?php echo e(APP_URL . 'modules/projects/view_fm.php?id=' . $id); ?>">العودة إلى المراجعة المالية</a>
    </div>

    <div class="alert alert-info">
        <strong>الرصيد تحت سيطرة المشروع:</strong>
        <?php echo number_format($controlledBalance, 2); ?> <?php echo e($currency); ?>.
        لا يمكن الإغلاق قبل إعادة هذا الرصيد بالكامل إلى حسابات المؤسسة.
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6">مصادر التمويل المرحّلة</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>المصدر</th>
                            <th>المبلغ المرحّل</th>
                            <th>المعاد</th>
                            <th>الحالة</th>
                            <th>الإجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allocations as $row): ?>
                        <tr>
                            <td><?php echo e($row['source_account_code'] . ' · ' . $row['source_account_name']); ?></td>
                            <td><?php echo number_format((float)$row['amount'], 2); ?> <?php echo e($row['currency_code'] ?: $currency); ?></td>
                            <td><?php echo number_format((float)($row['returned_amount'] ?? 0), 2); ?> <?php echo e($row['currency_code'] ?: $currency); ?></td>
                            <td>
                                <?php if (!empty($row['return_id'])): ?>
                                    <span class="badge bg-success">تم الإرجاع</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">متاح للمراجعة</span>
                                <?php endif; ?>
                            </td>
                            <td>
                            <?php if (empty($row['return_id']) && $controlledBalance > 0.01): ?>
                                <form method="post" class="row g-2 align-items-end">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="project_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="allocation_id" value="<?php echo (int)$row['id']; ?>">
                                    <div class="col-auto">
                                        <label class="form-label small">مبلغ الإرجاع</label>
                                        <input type="number" step="0.01" min="0.01" max="<?php echo e((string)min((float)$row['amount'], $controlledBalance)); ?>" name="return_amount" class="form-control" required>
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label small">التاريخ</label>
                                        <input type="date" name="return_date" class="form-control" value="<?php echo e(date('Y-m-d')); ?>" required>
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label small">المرجع</label>
                                        <input name="return_reference" class="form-control" maxlength="100">
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label small">الوصف</label>
                                        <input name="return_description" class="form-control" maxlength="500">
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-success" onclick="return confirm('هل تريد تسجيل إرجاع هذا المبلغ إلى حساب المؤسسة؟');">إرجاع الرصيد</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">لا يوجد إجراء مطلوب.</span>
                            <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($controlledBalance <= 0.01): ?>
        <div class="alert alert-success">
            تم تسوية كامل الرصيد تحت سيطرة المشروع. يمكن متابعة إغلاق المشروع وفق مساره المعتاد.
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-end mt-4 mb-2">
        <a class="btn btn-outline-secondary" href="<?php echo e(APP_URL . 'modules/projects/view_fm.php?id=' . $id); ?>">العودة إلى المراجعة المالية</a>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
