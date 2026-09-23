<?php
// modules/projects/view_fm.php - Dedicated Financial Manager project review
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_transaction_review.php';
Session::start();

$id = (int)($_GET['id'] ?? $_POST['project_id'] ?? 0);
$project = $id ? akp_get_project($id) : null;
if (!$project) { flash('error', 'المشروع غير موجود.'); redirect('modules/projects/index.php'); }
if (akp_role() !== 'financial_manager' || !akp_can_view_project($id)) { header('Location: ' . APP_URL . 'index.php'); exit(); }

$approval = dbFetchOne('SELECT * FROM project_approval WHERE project_id = ?', [$id]) ?: ['approval_status' => 'draft'];
$closed = akp_project_is_closed($id);
$budgets = dbFetchAll("SELECT b.*, COALESCE(SUM(bl.estimated_amount),0) AS line_total, COUNT(bl.id) AS line_count
    FROM project_budgets b LEFT JOIN project_budget_lines bl ON bl.budget_id=b.id
    WHERE b.project_id=? GROUP BY b.id ORDER BY b.version_no DESC", [$id]);
$activeBudget = dbFetchOne("SELECT b.* FROM project_budgets b WHERE b.project_id=? AND b.status IN ('draft','approved') ORDER BY b.version_no DESC LIMIT 1", [$id]);
$budgetLines = $activeBudget ? dbFetchAll('SELECT bl.* FROM project_budget_lines bl WHERE bl.budget_id=? ORDER BY bl.id', [$activeBudget['id']]) : [];
$fundings = dbFetchAll("SELECT f.*, a.code AS source_account_code, a.name_ar AS source_account_name
    FROM project_funding_allocations f LEFT JOIN accounts a ON a.id=f.source_account_id
    WHERE f.project_id=? ORDER BY f.created_at DESC", [$id]);
$accounts = dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE is_active=1 AND code IN ('1100','1200','1300') ORDER BY code");

function fm_redirect_project(int $id): void {
    header('Location: ' . APP_URL . 'modules/projects/view_fm.php?id=' . $id);
    exit();
}
function fm_post(string $name, string $default=''): string {
    return trim((string)($_POST[$name] ?? $default));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('error', 'انتهت صلاحية الجلسة.'); fm_redirect_project($id); }
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($closed) throw new RuntimeException('لا يمكن تعديل مشروع مغلق.');

        } elseif ($action === 'fm_approve_budget') {
            $budgetId=(int)($_POST['budget_id']??0);
            $budget=dbFetchOne('SELECT * FROM project_budgets WHERE id=? AND project_id=?',[$budgetId,$id]);
            if (!$budget || $budget['status']!=='draft') throw new RuntimeException('نسخة الميزانية ليست مسودة.');
            $total=(float)(dbFetchOne('SELECT COALESCE(SUM(estimated_amount),0) n FROM project_budget_lines WHERE budget_id=?',[$budgetId])['n']??0);
            if ($total<=0) throw new RuntimeException('لا يمكن اعتماد ميزانية بدون بنود ومبلغ أكبر من صفر.');
            dbExecute("UPDATE project_budgets SET status='superseded' WHERE project_id=? AND status='approved'",[$id]);
            dbExecute("UPDATE project_budgets SET status='approved' WHERE id=? AND project_id=?",[$budgetId,$id]);
            akp_audit('FM_APPROVE_BUDGET','project_budget',$budgetId,['status'=>'draft'],['status'=>'approved','total'=>$total]);
            flash('success','تم اعتماد الميزانية مالياً. يمكن الآن تخصيص التمويل.');

        } elseif ($action === 'fm_reject_budget') {
            $budgetId=(int)($_POST['budget_id']??0); $reason=fm_post('rejection_reason');
            $budget=dbFetchOne('SELECT * FROM project_budgets WHERE id=? AND project_id=?',[$budgetId,$id]);
            if (!$budget || $budget['status']!=='draft') throw new RuntimeException('نسخة الميزانية ليست مسودة.');
            if ($reason==='') throw new RuntimeException('سبب رفض الميزانية مطلوب.');
            dbExecute("UPDATE project_approval SET fm_rejection_reason=? WHERE project_id=?",[$reason,$id]);
            akp_audit('FM_REJECT_BUDGET','project_budget',$budgetId,['status'=>'draft'],['status'=>'draft','reason'=>$reason]);
            flash('success','تم رفض الميزانية وإبلاغ مدير المشاريع بسبب الرفض.');

        } elseif ($action === 'fm_add_funding') {
            if (!akp_can_manage_funding($id)) throw new RuntimeException('تخصيص التمويل محصور بالمدير المالي.');
            if ((string)$approval['approval_status']!=='submitted') throw new RuntimeException('تخصيص التمويل متاح أثناء المراجعة المالية.');
            $budget=dbFetchOne("SELECT b.id,COALESCE(SUM(bl.estimated_amount),0) total FROM project_budgets b LEFT JOIN project_budget_lines bl ON bl.budget_id=b.id WHERE b.project_id=? AND b.status='approved' GROUP BY b.id ORDER BY b.version_no DESC LIMIT 1",[$id]);
            $budgetTotal=(float)($budget['total']??0);
            if ($budgetTotal<=0) throw new RuntimeException('اعتمد الميزانية أولاً.');
            $accountId=(int)($_POST['source_account_id']??0); $amount=(float)($_POST['funding_amount']??0);
            $account=$accountId?dbFetchOne('SELECT id,code,name_ar FROM accounts WHERE id=? AND is_active=1',[$accountId]):null;
            if (!$account || !in_array($account['code'],['1100','1200','1300'],true)) throw new RuntimeException('اختر حساب تمويل صالحاً.');
            if ($amount<=0) throw new RuntimeException('مبلغ التمويل يجب أن يكون أكبر من صفر.');
            $existing=(float)(dbFetchOne("SELECT COALESCE(SUM(amount),0) n FROM project_funding_allocations WHERE project_id=? AND status='draft'",[$id])['n']??0);
            if ($existing+$amount>$budgetTotal+0.01) throw new RuntimeException('إجمالي التمويل لا يمكن أن يتجاوز الميزانية المعتمدة.');
            dbExecute('INSERT INTO project_funding_allocations (project_id,budget_id,source_type,source_account_id,destination_account_id,transaction_id,amount,currency_code,allocation_date,reference_number,description,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$budget['id'],$account['code'],$account['id'],null,null,$amount,$project['currency_code']?:'SDG',fm_post('allocation_date',date('Y-m-d')),fm_post('funding_reference')?:null,fm_post('funding_description')?:null,'draft',akp_user_id()]);
            $allocationId=(int)(dbFetchOne('SELECT LAST_INSERT_ID() id')['id']??0);
            akp_audit('CREATE','project_funding_allocation',$allocationId,null,['project_id'=>$id,'amount'=>$amount,'source_account'=>$account['code'],'fm_review'=>true]);
            flash('success','تم تسجيل تخصيص التمويل.');

        } elseif ($action === 'fm_approve_project') {
            if ((string)$approval['approval_status']!=='submitted') throw new RuntimeException('المشروع ليس في حالة انتظار الاعتماد المالي.');
            $budget=dbFetchOne("SELECT b.id,COALESCE(SUM(bl.estimated_amount),0) total FROM project_budgets b LEFT JOIN project_budget_lines bl ON bl.budget_id=b.id WHERE b.project_id=? AND b.status='approved' GROUP BY b.id ORDER BY b.version_no DESC LIMIT 1",[$id]);
            $budgetTotal=(float)($budget['total']??0);
            $fundingTotal=(float)(dbFetchOne("SELECT COALESCE(SUM(amount),0) n FROM project_funding_allocations WHERE project_id=? AND status='draft'",[$id])['n']??0);
            if ($budgetTotal<=0) throw new RuntimeException('لا يمكن الاعتماد قبل اعتماد الميزانية.');
            if (abs($fundingTotal-$budgetTotal)>0.01) throw new RuntimeException('يجب أن يساوي إجمالي تخصيص التمويل الميزانية المعتمدة.');
            dbExecute("UPDATE project_approval SET approval_status='fm_approved',fm_reviewed_by=?,fm_reviewed_at=NOW() WHERE project_id=?", [akp_user_id(),$id]);
            akp_audit('FM_APPROVE_PROJECT','project_approval',$id,['approval_status'=>'submitted'],['approval_status'=>'fm_approved']);
            flash('success','تم اعتماد المشروع مالياً. المشروع الآن بانتظار اعتماد المدير العام.');

        } elseif ($action === 'fm_reject_project') {
            $reason=fm_post('rejection_reason');
            if ($reason==='') throw new RuntimeException('سبب الرفض مطلوب.');
            if ((string)$approval['approval_status']!=='submitted') throw new RuntimeException('المشروع ليس في انتظار المراجعة المالية.');
            dbExecute("UPDATE project_approval SET approval_status='rejected',fm_rejection_reason=?,fm_reviewed_by=?,fm_reviewed_at=NOW() WHERE project_id=?",[$reason,akp_user_id(),$id]);
            akp_audit('FM_REJECT_PROJECT','project_approval',$id,['approval_status'=>'submitted'],['approval_status'=>'rejected','reason'=>$reason]);

            // Notify active Projects Manager users through the existing event-aware notification infrastructure.
            // Notification delivery is isolated so it cannot roll back the completed rejection.
            try {
                $pmUsers = dbFetchAll(
                    "SELECT u.id
                     FROM users u
                     JOIN roles r ON u.role_id = r.id
                     WHERE r.code = 'projects_manager'
                       AND u.is_active = 1"
                );
                foreach ($pmUsers as $pmUser) {
                    ak_transaction_review_notify_event(
                        (int)$pmUser['id'],
                        'تم رفض المشروع مالياً',
                        'المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') تم رفضه مالياً. السبب: ' . $reason,
                        APP_URL . 'modules/projects/view_pm.php?id=' . $id,
                        $id,
                        'project_fm_rejection'
                    );
                }
            } catch (Throwable $notificationError) {
                // Notification delivery must never roll back the completed rejection.
            }

            flash('success','تم رفض المشروع مالياً وإعادته لمدير المشاريع.');
        }
    } catch (Throwable $e) {
        flash('error',$e->getMessage());
    }
    fm_redirect_project($id);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="container-fluid py-4" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h3 class="mb-1"><i class="fas fa-coins me-2"></i>المراجعة المالية للمشروع</h3><div class="text-muted"><?php echo e($project['project_code'] ?? ''); ?> · <?php echo e($project['name'] ?? ''); ?></div></div>
    </div>
    <div class="alert alert-primary"><strong>دور المدير المالي:</strong> مراجعة الميزانية، تعديلها عند الحاجة، اعتمادها، ثم تحديد حسابات التمويل وتخصيص المبلغ قبل الاعتماد المالي.</div>

    <div class="card mb-4"><div class="card-header"><strong>الميزانية</strong></div><div class="card-body">
        <?php if (!$activeBudget): ?><div class="alert alert-warning">لا توجد ميزانية للمراجعة.</div>
        <?php else: ?>
            <div class="mb-3"><strong><?php echo e($activeBudget['budget_name']); ?></strong> · النسخة <?php echo (int)$activeBudget['version_no']; ?> · الحالة <span class="badge bg-<?php echo $activeBudget['status']==='approved'?'success':'warning text-dark'; ?>"><?php echo e($activeBudget['status']); ?></span></div>
            <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>الفئة</th><th>الوصف</th><th>المبلغ</th><th>إجراء</th></tr></thead><tbody>
            <?php foreach($budgetLines as $line): ?><tr><td><?php echo e($line['category']); ?></td><td><?php echo e($line['description']); ?></td><td><?php echo number_format((float)$line['estimated_amount'],2).' '.e($project['currency_code']?:'SDG'); ?></td></tr>
            <?php endforeach; ?><?php if(!$budgetLines): ?><tr><td colspan="4" class="text-center text-muted">لا توجد بنود.</td></tr><?php endif; ?></tbody></table></div>
            <?php if($activeBudget['status']==='draft'): ?>
                <div class="d-flex gap-2"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_approve_budget"><input type="hidden" name="budget_id" value="<?php echo (int)$activeBudget['id']; ?>"><button class="btn btn-success">اعتماد الميزانية</button></form><button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectBudget">رفض الميزانية</button></div>
            <?php endif; ?>
        <?php endif; ?>
    </div></div>

    <div class="card mb-4"><div class="card-header"><strong>تخصيص التمويل</strong></div><div class="card-body">
        <?php $approvedExists=$activeBudget && $activeBudget['status']==='approved'; $fundingTotal=array_sum(array_map('floatval',array_column($fundings,'amount'))); ?>
        <?php if($approvedExists): ?>
            <div class="alert alert-info">يحدد المدير المالي حسابات المؤسسة التي سيمول منها المشروع: الصندوق النقدي أو البنك أو المحفظة الإلكترونية.</div>
            <?php if($approval['approval_status']==='submitted' && !$closed): ?><form method="post" class="border rounded p-3 mb-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_add_funding"><div class="row g-2"><div class="col-md-5"><select name="source_account_id" class="form-select" required><option value="">حساب التمويل</option><?php foreach($accounts as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo e($a['code'].' · '.$a['name_ar']); ?></option><?php endforeach; ?></select></div><div class="col-md-3"><input type="number" step="0.01" min="0.01" name="funding_amount" class="form-control" placeholder="المبلغ" required></div><div class="col-md-2"><input type="date" name="allocation_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div><div class="col-md-2"><input name="funding_reference" class="form-control" placeholder="المرجع"></div><div class="col-12"><input name="funding_description" class="form-control" placeholder="الوصف"></div></div><button class="btn btn-primary mt-2">حفظ تخصيص التمويل</button></form><?php endif; ?>
            <div class="small mb-2">إجمالي التخصيص الحالي: <strong><?php echo number_format($fundingTotal,2); ?> <?php echo e($project['currency_code']?:'SDG'); ?></strong></div>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>التاريخ</th><th>الحساب</th><th>المبلغ</th><th>المرجع/الوصف</th></tr></thead><tbody><?php foreach($fundings as $f): ?><tr><td><?php echo e($f['allocation_date']); ?></td><td><?php echo e(($f['source_account_code']??$f['source_type']).' · '.($f['source_account_name']??'')); ?></td><td><?php echo number_format((float)$f['amount'],2); ?></td><td><?php echo e(($f['reference_number']??'')); ?><?php if(!empty($f['description'])): ?><br><?php echo e($f['description']); ?><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$fundings): ?><tr><td colspan="4" class="text-center text-muted">لا توجد تخصيصات تمويل مسجلة بعد.</td></tr><?php endif; ?></tbody></table></div>
        <?php else: ?><div class="alert alert-warning">تظهر أدوات تخصيص التمويل بعد اعتماد الميزانية.</div><?php endif; ?>
    </div></div>

    <?php if($approval['approval_status']==='submitted' && $approvedExists): ?><div class="card mb-4 border-primary"><div class="card-body"><h5>الاعتماد المالي للمشروع</h5><div class="alert alert-light border mb-3"><div class="text-muted small mb-1">إجمالي الميزانية المعتمدة</div><div class="fs-4 fw-bold"><?php echo number_format((float)($activeBudget['line_total'] ?? 0),2); ?> <?php echo e($project['currency_code']?:'SDG'); ?></div></div><p class="text-muted">يجب أن يساوي إجمالي تخصيص التمويل الميزانية المعتمدة.</p><div class="d-flex gap-2"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_approve_project"><button class="btn btn-success">اعتماد المشروع مالياً</button></form><button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectProject">رفض المشروع مالياً</button></div></div></div><?php endif; ?>

    <div class="modal fade" id="rejectBudget"><div class="modal-dialog"><div class="modal-content"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_reject_budget"><input type="hidden" name="budget_id" value="<?php echo (int)($activeBudget['id']??0); ?>"><div class="modal-header"><h5>رفض الميزانية</h5></div><div class="modal-body"><textarea name="rejection_reason" class="form-control" required placeholder="سبب الرفض"></textarea></div><div class="modal-footer"><button class="btn btn-danger">تأكيد الرفض</button></div></form></div></div></div>
    <div class="modal fade" id="rejectProject"><div class="modal-dialog"><div class="modal-content"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_reject_project"><div class="modal-header"><h5>رفض المشروع مالياً</h5></div><div class="modal-body"><textarea name="rejection_reason" class="form-control" required placeholder="سبب الرفض"></textarea></div><div class="modal-footer"><button class="btn btn-danger">تأكيد الرفض</button></div></form></div></div></div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>