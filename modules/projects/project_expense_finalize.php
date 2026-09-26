<?php
// modules/projects/project_expense_finalize.php - PS-side finalization of project-funded expenses
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
Session::start();

$id = (int)($_GET['id'] ?? $_POST['project_id'] ?? 0);
$project = $id ? akp_get_project($id) : null;
if (!$project || !akp_can_view_project($id)) {
    flash('error', 'المشروع غير موجود أو لا تملك صلاحية الوصول إليه.');
    redirect('modules/projects/index.php');
}
if (akp_role() !== 'project_supervisor' || !akp_is_primary_supervisor($id)) {
    flash('error', 'إتمام مصروفات المشروع متاح لمشرف المشروع المكلّف فقط.');
    redirect('modules/projects/view.php?id=' . $id);
}
if (akp_project_is_closed($id)) {
    flash('error', 'لا يمكن تعديل مصروفات مشروع مغلق.');
    redirect('modules/projects/view.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة.');
        redirect('modules/projects/project_expense_finalize.php?id=' . $id);
    }

    $expenseId = (int)($_POST['expense_id'] ?? 0);
    $expense = dbFetchOne('SELECT * FROM project_expenses WHERE id = ? AND project_id = ?', [$expenseId, $id]);
    if (!$expense || $expense['status'] !== 'draft') {
        flash('error', 'المصروف غير موجود أو تم إتمامه مسبقاً.');
        redirect('modules/projects/project_expense_finalize.php?id=' . $id);
    }

    $approvedBudgetTotal = (float)(dbFetchOne(
        "SELECT COALESCE(SUM(COALESCE(bl.approved_amount, bl.estimated_amount)),0) AS total
         FROM project_budgets b
         JOIN project_budget_lines bl ON bl.budget_id = b.id
         WHERE b.project_id = ? AND b.status = 'approved'",
        [$id]
    )['total'] ?? 0);

    $postedTotal = (float)(dbFetchOne(
        "SELECT COALESCE(SUM(amount),0) AS total FROM project_expenses
         WHERE project_id = ? AND status = 'posted' AND id <> ?",
        [$id, $expenseId]
    )['total'] ?? 0);

    if ($approvedBudgetTotal <= 0 || ($postedTotal + (float)$expense['amount']) > ($approvedBudgetTotal + 0.01)) {
        flash('error', 'لا يمكن إتمام هذا المصروف لأنه يتجاوز الرصيد المتاح من ميزانية المشروع.');
        redirect('modules/projects/project_expense_finalize.php?id=' . $id);
    }

    $storedAbsolutePath = null;
    $primaryDocumentId = !empty($expense['primary_document_id']) ? (int)$expense['primary_document_id'] : null;

    try {
        dbExecute('START TRANSACTION');

        if (!empty($_FILES['expense_receipt']['name'])) {
            $file = $_FILES['expense_receipt'];
            if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('فشل في رفع إيصال المصروف.');
            if ((int)$file['size'] > 10 * 1024 * 1024) throw new RuntimeException('حجم الإيصال يجب ألا يتجاوز 10 ميجابايت.');
            $mime = mime_content_type($file['tmp_name']);
            $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (!isset($allowed[$mime])) throw new RuntimeException('نوع الإيصال غير مسموح. استخدم PDF أو JPG أو PNG.');

            $relativeDir = 'storage/documents/projects/' . $id;
            $absoluteDir = dirname(__DIR__, 2) . '/' . $relativeDir;
            if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true)) throw new RuntimeException('تعذر إنشاء مجلد وثائق المشروع.');
            $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            $storedAbsolutePath = $absoluteDir . '/' . $stored;
            if (!move_uploaded_file($file['tmp_name'], $storedAbsolutePath)) throw new RuntimeException('تعذر حفظ إيصال المصروف.');
            $relativePath = $relativeDir . '/' . $stored;

            if ($primaryDocumentId) {
                $oldDoc = dbFetchOne("SELECT file_path FROM project_documents WHERE id = ? AND project_id = ? AND document_type = 'receipt'", [$primaryDocumentId, $id]);
                dbExecute(
                    "UPDATE project_documents SET title=?, file_path=?, original_name=?, mime_type=?, file_size=?, document_date=?, issuer=?, reference_number=?, amount=?, currency_code=?, notes=? WHERE id=? AND project_id=?",
                    ['إيصال مصروف: ' . $expense['description'], $relativePath, $file['name'], $mime, $file['size'], $expense['expense_date'], $expense['vendor_name'] ?: null, $expense['invoice_number'] ?: null, $expense['amount'], $expense['currency_code'], 'مرفق مباشرة بالمصروف المسجل بواسطة مشرف المشروع.', $primaryDocumentId, $id]
                );
                $oldReceiptPath = $oldDoc['file_path'] ?? null;
            } else {
                dbExecute(
                    'INSERT INTO project_documents (project_id, document_type, title, file_path, original_name, mime_type, file_size, document_date, issuer, reference_number, amount, currency_code, notes, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$id, 'receipt', 'إيصال مصروف: ' . $expense['description'], $relativePath, $file['name'], $mime, $file['size'], $expense['expense_date'], $expense['vendor_name'] ?: null, $expense['invoice_number'] ?: null, $expense['amount'], $expense['currency_code'], 'مرفق مباشرة بالمصروف المسجل بواسطة مشرف المشروع.', akp_user_id()]
                );
                $primaryDocumentId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
                $oldReceiptPath = null;
            }
        } else {
            $oldReceiptPath = null;
        }

        // Project-funded expenses do not create organization-account journal entries.
        // The posted expense is the budget deduction and project financial record.
        dbExecute(
            "UPDATE project_expenses SET status='posted', posted_by=?, primary_document_id=? WHERE id=? AND project_id=? AND status='draft'",
            [akp_user_id(), $primaryDocumentId, $expenseId, $id]
        );
        dbExecute("INSERT INTO project_expense_approvals (expense_id, action, actor_id) VALUES (?, 'posted', ?)", [$expenseId, akp_user_id()]);
        dbExecute('COMMIT');
    } catch (Throwable $e) {
        dbExecute('ROLLBACK');
        if ($storedAbsolutePath && is_file($storedAbsolutePath)) @unlink($storedAbsolutePath);
        flash('error', $e->getMessage());
        redirect('modules/projects/project_expense_finalize.php?id=' . $id);
    }

    if (!empty($oldReceiptPath) && $storedAbsolutePath) {
        $oldAbsolutePath = dirname(__DIR__, 2) . '/' . $oldReceiptPath;
        if ($oldAbsolutePath !== $storedAbsolutePath && is_file($oldAbsolutePath)) @unlink($oldAbsolutePath);
    }

    akp_audit('POST', 'project_expense', $expenseId, ['status' => 'draft'], ['status' => 'posted', 'primary_document_id' => $primaryDocumentId]);
    flash('success', 'تم تسجيل دفع المصروف من ميزانية المشروع وترحيله دون أي قيد على حسابات المنظمة.');
    redirect('modules/projects/project_expense_finalize.php?id=' . $id);
}

$draftExpenses = dbFetchAll(
    "SELECT e.*, pd.title AS primary_document_title
     FROM project_expenses e
     LEFT JOIN project_documents pd ON pd.id = e.primary_document_id
     WHERE e.project_id = ? AND e.status = 'draft'
     ORDER BY e.expense_date ASC, e.id ASC",
    [$id]
);
?>
<?php include dirname(__DIR__, 2) . '/includes/header.php'; ?>
<div class="container-fluid py-4" style="max-width:1100px;direction:rtl;">
    <div class="d-flex justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h3 class="mb-1">إتمام مصروفات المشروع</h3>
            <div class="text-muted"><?php echo e($project['name']); ?> · <?php echo e($project['project_code']); ?></div>
        </div>
        <a href="<?php echo e(APP_URL . 'modules/projects/view.php?id=' . $id); ?>" class="btn btn-outline-secondary">العودة للمشروع</a>
    </div>

    <div class="alert alert-info">
        <strong>مصروفات من ميزانية المشروع</strong><br>
        عند تسجيل الدفع هنا يُخصم المبلغ من الرصيد المتاح لميزانية المشروع ويصبح المصروف <strong>مرحّلاً</strong>.
        لا يتم اختيار صندوق أو بنك أو محفظة، ولا يتم إنشاء قيد إضافي على حسابات المنظمة، ولا تحتاج العملية إلى مراجعة FM أو PM.
    </div>

    <?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

    <?php if (!$draftExpenses): ?>
        <div class="alert alert-success">لا توجد مصروفات مسودة معلقة. جميع مصروفات المشروع المكتملة مرحّلة.</div>
    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">المصروفات التي تحتاج تسجيل الدفع</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>التاريخ</th><th>الوصف</th><th>المورد/الفاتورة</th><th>المبلغ</th><th>المستند</th><th>الإجراء</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($draftExpenses as $expense): ?>
                            <tr>
                                <td><?php echo e($expense['expense_date']); ?></td>
                                <td><?php echo e($expense['description']); ?></td>
                                <td><?php echo e($expense['vendor_name'] ?: '—'); ?><br><small><?php echo e($expense['invoice_number'] ?: ''); ?></small></td>
                                <td class="fw-bold"><?php echo akp_money($expense['amount']); ?> <?php echo e($expense['currency_code']); ?></td>
                                <td><?php if ($expense['primary_document_id']): ?><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo e(APP_URL . 'modules/projects/serve_project_document.php?id=' . (int)$expense['primary_document_id']); ?>">عرض</a><?php else: ?>—<?php endif; ?></td>
                                <td>
                                    <form method="post" enctype="multipart/form-data" class="border rounded p-2 bg-light">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="project_id" value="<?php echo (int)$id; ?>">
                                        <input type="hidden" name="expense_id" value="<?php echo (int)$expense['id']; ?>">
                                        <label class="form-label small mb-1">إيصال الدفع (اختياري)</label>
                                        <input type="file" name="expense_receipt" class="form-control form-control-sm mb-2" accept=".pdf,.jpg,.jpeg,.png">
                                        <button class="btn btn-sm btn-success" onclick="return confirm('سيتم تسجيل دفع هذا المصروف من ميزانية المشروع وترحيله. هل تريد المتابعة؟')"><i class="fas fa-check me-1"></i>تسجيل الدفع وترحيل المصروف</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
