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
$activeBudget = dbFetchOne("SELECT b.*, COALESCE(SUM(bl.estimated_amount),0) AS line_total FROM project_budgets b LEFT JOIN project_budget_lines bl ON bl.budget_id=b.id WHERE b.project_id=? AND b.status IN ('draft','approved') GROUP BY b.id ORDER BY b.version_no DESC LIMIT 1", [$id]);
$budgetLines = $activeBudget ? dbFetchAll('SELECT bl.* FROM project_budget_lines bl WHERE bl.budget_id=? ORDER BY bl.id', [$activeBudget['id']]) : [];
$fundings = dbFetchAll("SELECT f.*, a.code AS source_account_code, a.name_ar AS source_account_name
    FROM project_funding_allocations f LEFT JOIN accounts a ON a.id=f.source_account_id
    WHERE f.project_id=? ORDER BY f.created_at DESC", [$id]);
$paymentEvidence = dbFetchAll("SELECT pe.*, a.code AS source_account_code, a.name_ar AS source_account_name, je.entry_code
    FROM project_payment_evidence pe
    LEFT JOIN accounts a ON a.id=pe.source_account_id
    LEFT JOIN journal_entries je ON je.id=pe.journal_entry_id
    WHERE pe.project_id=? ORDER BY pe.id DESC", [$id]);
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

        if ($action === 'fm_approve_budget') {
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

        } elseif ($action === 'fm_save_funding_batch') {
            if (!akp_can_manage_funding($id)) throw new RuntimeException('تخصيص التمويل محصور بالمدير المالي.');
            if ((string)$approval['approval_status']!=='submitted') throw new RuntimeException('تخصيص التمويل متاح أثناء المراجعة المالية.');
            $budget=dbFetchOne("SELECT b.id,COALESCE(SUM(bl.estimated_amount),0) total FROM project_budgets b LEFT JOIN project_budget_lines bl ON bl.budget_id=b.id WHERE b.project_id=? AND b.status='approved' GROUP BY b.id ORDER BY b.version_no DESC LIMIT 1",[$id]);
            $budgetTotal=(float)($budget['total']??0);
            if ($budgetTotal<=0) throw new RuntimeException('اعتمد الميزانية أولاً.');

            $sourceIds=$_POST['source_account_id']??[]; $amounts=$_POST['funding_amount']??[]; $dates=$_POST['allocation_date']??[]; $descs=$_POST['funding_description']??[];
            if (!is_array($sourceIds)) $sourceIds=[$sourceIds]; if (!is_array($amounts)) $amounts=[$amounts]; if (!is_array($dates)) $dates=[$dates]; if (!is_array($descs)) $descs=[$descs];

            $batchByAccount=[];
            $rowCount=max(count($sourceIds),count($amounts));
            for($i=0;$i<$rowCount;$i++){
                $accountId=(int)($sourceIds[$i]??0); $amount=(float)($amounts[$i]??0);
                if($accountId===0 && $amount===0) continue;
                $account=$accountId?dbFetchOne('SELECT id,code,name_ar FROM accounts WHERE id=? AND is_active=1',[$accountId]):null;
                if(!$account || !in_array($account['code'],['1100','1200','1300'],true)) throw new RuntimeException('اختر حساب تمويل صالحاً لكل بند.');
                if($amount<=0) throw new RuntimeException('مبلغ التمويل يجب أن يكون أكبر من صفر لكل بند.');
                $code=(string)$account['code'];
                if(!isset($batchByAccount[$code])) $batchByAccount[$code]=['account'=>$account,'amount'=>0.0,'date'=>trim((string)($dates[$i]??''))?:date('Y-m-d'),'description'=>''];
                $batchByAccount[$code]['amount']+=$amount;
                $extraDate=trim((string)($dates[$i]??'')); $extraDesc=trim((string)($descs[$i]??''));
                if($extraDate!=='') $batchByAccount[$code]['date']=$extraDate;
                if($extraDesc!=='') $batchByAccount[$code]['description']=trim($batchByAccount[$code]['description'].' / '.$extraDesc,' /');
            }
            if(!$batchByAccount) throw new RuntimeException('أضف مصدراً واحداً على الأقل مع مبلغ صحيح.');
            $batchTotal=0.0; foreach($batchByAccount as $item) $batchTotal+=$item['amount'];
            $existingTotal=(float)(dbFetchOne("SELECT COALESCE(SUM(amount),0) n FROM project_funding_allocations WHERE project_id=? AND status='draft'",[$id])['n']??0);
            if($existingTotal+$batchTotal>$budgetTotal+0.01) throw new RuntimeException('إجمالي التمويل لا يمكن أن يتجاوز الميزانية المعتمدة.');

            foreach($batchByAccount as $item){
                $accountId=(int)$item['account']['id'];
                $existingRows=dbFetchAll("SELECT id,amount FROM project_funding_allocations WHERE project_id=? AND source_account_id=? AND status='draft' ORDER BY id",[$id,$accountId]);
                $existingAmount=0.0; foreach($existingRows as $row) $existingAmount+=(float)$row['amount'];
                if($existingRows){
                    $primaryId=(int)$existingRows[0]['id']; $newAmount=$existingAmount+$item['amount'];
                    dbExecute("UPDATE project_funding_allocations SET budget_id=?,source_type=?,amount=?,currency_code=?,allocation_date=?,description=?,created_by=? WHERE id=? AND project_id=?",[$budget['id'],$item['account']['code'],$newAmount,$project['currency_code']?:'SDG',$item['date'],$item['description']?:null,akp_user_id(),$primaryId,$id]);
                    foreach(array_slice($existingRows,1) as $duplicate) dbExecute("DELETE FROM project_funding_allocations WHERE id=? AND project_id=? AND status='draft'",[(int)$duplicate['id'],$id]);
                    akp_audit('UPDATE','project_funding_allocation',$primaryId,['amount'=>$existingAmount],['amount'=>$newAmount,'source_account'=>$item['account']['code'],'fm_review'=>true]);
                }else{
                    dbExecute('INSERT INTO project_funding_allocations (project_id,budget_id,source_type,source_account_id,destination_account_id,transaction_id,amount,currency_code,allocation_date,reference_number,description,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$budget['id'],$item['account']['code'],$accountId,null,null,$item['amount'],$project['currency_code']?:'SDG',$item['date'],null,$item['description']?:null,'draft',akp_user_id()]);
                    $allocationId=(int)(dbFetchOne('SELECT LAST_INSERT_ID() id')['id']??0); akp_audit('CREATE','project_funding_allocation',$allocationId,null,['project_id'=>$id,'amount'=>$item['amount'],'source_account'=>$item['account']['code'],'fm_review'=>true]);
                }
            }
            flash('success','تم حفظ تخصيصات التمويل وتجميع كل مصدر تمويل في سجل واحد.');

        } elseif ($action === 'fm_edit_funding') {
            if (!akp_can_manage_funding($id)) throw new RuntimeException('تخصيص التمويل محصور بالمدير المالي.');
            if ((string)$approval['approval_status']!=='submitted') throw new RuntimeException('لا يمكن تعديل التمويل بعد انتهاء المراجعة المالية.');
            $allocationId=(int)($_POST['allocation_id']??0); $accountId=(int)($_POST['source_account_id']??0); $amount=(float)($_POST['funding_amount']??0);
            $date=fm_post('allocation_date',date('Y-m-d')); $description=fm_post('funding_description');
            $account=$accountId?dbFetchOne('SELECT id,code,name_ar FROM accounts WHERE id=? AND is_active=1',[$accountId]):null;
            $row=$allocationId?dbFetchOne("SELECT * FROM project_funding_allocations WHERE id=? AND project_id=? AND status='draft'",[$allocationId,$id]):null;
            if(!$row || !$account || !in_array($account['code'],['1100','1200','1300'],true)) throw new RuntimeException('تخصيص التمويل المطلوب غير صالح.');
            if($amount<=0) throw new RuntimeException('مبلغ التمويل يجب أن يكون أكبر من صفر.');
            $budgetTotal=(float)(dbFetchOne("SELECT COALESCE(SUM(bl.estimated_amount),0) total
                FROM project_budgets b LEFT JOIN project_budget_lines bl ON bl.budget_id=b.id
                WHERE b.project_id=? AND b.status='approved'
                GROUP BY b.id ORDER BY b.version_no DESC LIMIT 1",[$id])['total']??0);
            if($budgetTotal<=0) throw new RuntimeException('اعتمد الميزانية أولاً.');
            $totalWithout=(float)(dbFetchOne("SELECT COALESCE(SUM(amount),0) n FROM project_funding_allocations WHERE project_id=? AND status='draft' AND id<>?",[$id,$allocationId])['n']??0);
            $duplicateRows=dbFetchAll("SELECT id,amount FROM project_funding_allocations WHERE project_id=? AND source_account_id=? AND status='draft' AND id<>?",[$id,$accountId,$allocationId]);
            $mergedAmount=$amount; foreach($duplicateRows as $duplicate) $mergedAmount+=(float)$duplicate['amount'];
            if($totalWithout+$mergedAmount>$budgetTotal+0.01) throw new RuntimeException('إجمالي التمويل لا يمكن أن يتجاوز الميزانية المعتمدة.');
            dbExecute("UPDATE project_funding_allocations SET source_type=?,source_account_id=?,amount=?,currency_code=?,allocation_date=?,description=?,created_by=? WHERE id=? AND project_id=?",[$account['code'],$accountId,$mergedAmount,$project['currency_code']?:'SDG',$date,$description?:null,akp_user_id(),$allocationId,$id]);
            foreach($duplicateRows as $duplicate) dbExecute("DELETE FROM project_funding_allocations WHERE id=? AND project_id=? AND status='draft'",[(int)$duplicate['id'],$id]);
            akp_audit('UPDATE','project_funding_allocation',$allocationId,['amount'=>$row['amount']],['amount'=>$mergedAmount,'source_account'=>$account['code'],'fm_review'=>true]);
            flash('success','تم تعديل تخصيص التمويل وتجميع المصدر نفسه.');

        } elseif ($action === 'fm_delete_funding') {
            if (!akp_can_manage_funding($id)) throw new RuntimeException('تخصيص التمويل محصور بالمدير المالي.');
            if ((string)$approval['approval_status']!=='submitted') throw new RuntimeException('لا يمكن حذف التمويل بعد انتهاء المراجعة المالية.');
            $allocationId=(int)($_POST['allocation_id']??0);
            $row=$allocationId?dbFetchOne("SELECT * FROM project_funding_allocations WHERE id=? AND project_id=? AND status='draft'",[$allocationId,$id]):null;
            if(!$row) throw new RuntimeException('تخصيص التمويل المطلوب غير موجود.');
            dbExecute("DELETE FROM project_funding_allocations WHERE project_id=? AND source_account_id=? AND status='draft'",[$id,(int)$row['source_account_id']]);
            akp_audit('DELETE','project_funding_allocation',$allocationId,['project_id'=>$id,'source_account'=>$row['source_account_id'],'amount'=>$row['amount']],null);
            flash('success','تم حذف تخصيص مصدر التمويل بالكامل.');

        } elseif ($action === 'fm_confirm_cash_payment') {
            if (akp_role() !== 'financial_manager') throw new RuntimeException('إصدار سند صرف المشروع محصور بالمدير المالي.');
            if ((string)$approval['approval_status'] !== 'approved') throw new RuntimeException('لا يمكن إصدار سند الصرف قبل الاعتماد النهائي من المدير العام.');
            $paymentId=(int)($_POST['payment_id']??0);
            $payment=dbFetchOne("SELECT * FROM project_payment_evidence WHERE id=? AND project_id=? AND payment_method='cash'",[$paymentId,$id]);
            if(!$payment) throw new RuntimeException('سجل صرف النقد المطلوب غير موجود.');
            if($payment['status']==='documented') throw new RuntimeException('تم إصدار سند الصرف لهذا المبلغ مسبقاً.');
            dbExecute("UPDATE project_payment_evidence SET status='documented',voucher_confirmed_at=NOW(),documented_by=?,documented_at=NOW() WHERE id=? AND project_id=? AND status='pending'",[akp_user_id(),$paymentId,$id]);
            akp_audit('DOCUMENT_PROJECT_CASH_PAYMENT','project_payment_evidence',$paymentId,['status'=>'pending'],['status'=>'documented','payment_method'=>'cash']);
            flash('success','تم توثيق سند الصرف النقدي. يمكنك طباعته الآن.');
        } elseif ($action === 'fm_upload_payment_receipt') {
            if (akp_role() !== 'financial_manager') throw new RuntimeException('إرفاق إيصال التحويل محصور بالمدير المالي.');
            if ((string)$approval['approval_status'] !== 'approved') throw new RuntimeException('لا يمكن إرفاق إيصال قبل الاعتماد النهائي من المدير العام.');
            $paymentId=(int)($_POST['payment_id']??0);
            $reference=trim((string)($_POST['payment_reference']??''));
            $payment=dbFetchOne("SELECT * FROM project_payment_evidence WHERE id=? AND project_id=? AND payment_method IN ('bank_transfer','e_wallet')",[$paymentId,$id]);
            if(!$payment) throw new RuntimeException('سجل التحويل المطلوب غير موجود.');
            if(!isset($_FILES['payment_receipt']) || $_FILES['payment_receipt']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('إيصال التحويل مطلوب.');
            $file=$_FILES['payment_receipt'];
            if((int)$file['size']>10*1024*1024) throw new RuntimeException('حجم الإيصال يجب ألا يتجاوز 10 ميجابايت.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
            if(!isset($allowed[$mime])) throw new RuntimeException('نوع الإيصال غير مسموح. استخدم PDF أو JPG أو PNG.');
            $dir=dirname(__DIR__,2).'/storage/documents/projects/'.$id.'/payments';
            if(!is_dir($dir) && !mkdir($dir,0777,true)) throw new RuntimeException('تعذر إنشاء مجلد إيصالات المشروع.');
            $name='payment_'.$paymentId.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
            $dest=$dir.'/'.$name;
            if(!move_uploaded_file($file['tmp_name'],$dest)) throw new RuntimeException('فشل حفظ إيصال التحويل.');
            $relative='storage/documents/projects/'.$id.'/payments/'.$name;
            dbExecute("UPDATE project_payment_evidence SET status='documented',reference_number=?,receipt_file_path=?,receipt_original_name=?,receipt_mime_type=?,documented_by=?,documented_at=NOW() WHERE id=? AND project_id=? AND status='pending'",[$reference!==''?$reference:null,$relative,$file['name'],$mime,akp_user_id(),$paymentId,$id]);
            akp_audit('DOCUMENT_PROJECT_PAYMENT_RECEIPT','project_payment_evidence',$paymentId,['status'=>'pending'],['status'=>'documented','payment_method'=>$payment['payment_method'],'reference'=>$reference,'receipt'=>$relative]);
            flash('success','تم إرفاق إيصال التحويل وتوثيق عملية الدفع.');
        } elseif ($action === 'fm_approve_project') {
            if ((string)$approval['approval_status']!=='submitted') throw new RuntimeException('المشروع ليس في حالة انتظار الاعتماد المالي.');
            $budget=dbFetchOne("SELECT b.id,COALESCE(SUM(bl.estimated_amount),0) total FROM project_budgets b LEFT JOIN project_budget_lines bl ON bl.budget_id=b.id WHERE b.project_id=? AND b.status='approved' GROUP BY b.id ORDER BY b.version_no DESC LIMIT 1",[$id]);
            $budgetTotal=(float)($budget['total']??0);
            $fundingTotal=(float)(dbFetchOne("SELECT COALESCE(SUM(amount),0) n FROM project_funding_allocations WHERE project_id=? AND status='draft'",[$id])['n']??0);
            if ($budgetTotal<=0) throw new RuntimeException('لا يمكن الاعتماد قبل اعتماد الميزانية.');
            if (abs($fundingTotal-$budgetTotal)>0.01) throw new RuntimeException('يجب أن يساوي إجمالي تخصيص التمويل الميزانية المعتمدة.');
            dbExecute("UPDATE project_approval SET approval_status='fm_approved',fm_reviewed_by=?,fm_reviewed_at=NOW() WHERE project_id=?", [akp_user_id(),$id]);
            akp_audit('FM_APPROVE_PROJECT','project_approval',$id,['approval_status'=>'submitted'],['approval_status'=>'fm_approved']);

            // Notify active General Manager recipients that the project is now
            // waiting for final approval. Delivery is isolated so it cannot
            // roll back the completed FM approval.
            try {
                $gmUsers = dbFetchAll(
                    "SELECT u.id
                     FROM users u
                     JOIN roles r ON u.role_id = r.id
                     WHERE r.code IN ('general_manager', 'vice_general_manager')
                       AND u.is_active = 1"
                );
                foreach ($gmUsers as $gmUser) {
                    ak_transaction_review_notify_event(
                        (int)$gmUser['id'],
                        'مشروع بانتظار الاعتماد النهائي',
                        'المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') تم اعتماده مالياً وبانتظار اعتماد المدير العام.',
                        APP_URL . 'modules/projects/view.php?id=' . $id,
                        $id,
                        'project_fm_approval'
                    );
                }
            } catch (Throwable $notificationError) {
                // Notification delivery must never roll back the completed FM approval.
            }
            flash('success','تم اعتماد المشروع مالياً. المشروع الآن بانتظار اعتماد المدير العام.');

        } elseif ($action === 'fm_reject_project') {
            $reason=fm_post('rejection_reason');
            if ($reason==='') throw new RuntimeException('سبب الرفض مطلوب.');
            if ((string)$approval['approval_status']!=='submitted') throw new RuntimeException('المشروع ليس في انتظار المراجعة المالية.');
            dbExecute("UPDATE project_approval SET approval_status='rejected',fm_rejection_reason=?,fm_reviewed_by=?,fm_reviewed_at=NOW() WHERE project_id=?",[$reason,akp_user_id(),$id]);
            akp_audit('FM_REJECT_PROJECT','project_approval',$id,['approval_status'=>'submitted'],['approval_status'=>'rejected','reason'=>$reason]);

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
    <div class="alert alert-primary"><strong>دور المدير المالي:</strong> مراجعة الميزانية، اعتمادها عند قبولها، ثم تحديد حسابات التمويل وتخصيص المبلغ قبل الاعتماد المالي. إذا احتاج المشروع إلى تعديل، يتم رفضه وإعادته لمدير المشاريع مع توضيح السبب.</div>

    <div class="card mb-4"><div class="card-header"><strong>الميزانية المقترحة</strong></div><div class="card-body">
        <?php if (!$activeBudget): ?><div class="alert alert-warning">لا توجد ميزانية مقترحة للمراجعة.</div>
        <?php else: ?>
            <div class="mb-3"><strong><?php echo e($activeBudget['budget_name']); ?></strong> · النسخة <?php echo (int)$activeBudget['version_no']; ?> · الحالة <span class="badge bg-<?php echo $activeBudget['status']==='approved'?'success':'warning text-dark'; ?>"><?php echo e($activeBudget['status']); ?></span></div>
            <div class="alert alert-light border d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted">إجمالي الميزانية المقترحة</span>
                <strong class="fs-5"><?php echo number_format((float)($activeBudget['line_total'] ?? 0),2); ?> <?php echo e($project['currency_code']?:'SDG'); ?></strong>
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>الفئة</th><th>الوصف</th><th>المبلغ</th></tr></thead><tbody>
            <?php foreach($budgetLines as $line): ?><tr><td><?php echo e($line['category']); ?></td><td><?php echo e($line['description']); ?></td><td><?php echo number_format((float)$line['estimated_amount'],2).' '.e($project['currency_code']?:'SDG'); ?></td></tr>
            <?php endforeach; ?><?php if(!$budgetLines): ?><tr><td colspan="3" class="text-center text-muted">لا توجد بنود.</td></tr><?php endif; ?></tbody></table></div>
            <?php if($activeBudget['status']==='draft'): ?>
                <div class="d-flex gap-2"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_approve_budget"><input type="hidden" name="budget_id" value="<?php echo (int)$activeBudget['id']; ?>"><button class="btn btn-success">اعتماد الميزانية</button></form><button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectBudget">رفض الميزانية</button></div>
            <?php endif; ?>
        <?php endif; ?>
    </div></div>

    <div class="card mb-4"><div class="card-header"><strong>تخصيص التمويل</strong></div><div class="card-body">
        <?php $approvedExists=$activeBudget && $activeBudget['status']==='approved'; $fundingTotal=array_sum(array_map('floatval',array_column($fundings,'amount'))); ?>
        <?php if($approvedExists): ?>
            <div class="alert alert-info">يحدد المدير المالي حسابات المؤسسة التي سيمول منها المشروع: الصندوق النقدي أو البنك أو المحفظة الإلكترونية.</div>
            <?php if($approval['approval_status']==='submitted' && !$closed): ?>
                <form method="post" class="border rounded p-3 mb-3" id="fundingForm">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_save_funding_batch">
                    <div id="fundingRows">
                        <div class="funding-row border rounded p-3 mb-2"><div class="row g-2 align-items-end">
                            <div class="col-md-4"><label class="form-label">حساب التمويل</label><select name="source_account_id[]" class="form-select" required><option value="">اختر الحساب</option><?php foreach($accounts as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo e($a['code'].' · '.$a['name_ar']); ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-2"><label class="form-label">المبلغ</label><input type="number" step="0.01" min="0.01" name="funding_amount[]" class="form-control" required></div>
                            <div class="col-md-2"><label class="form-label">التاريخ</label><input type="date" name="allocation_date[]" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
                            
                            <div class="col-md-2"><label class="form-label">الوصف</label><input name="funding_description[]" class="form-control"></div>
                        </div></div>
                    </div>
                    <div class="d-flex gap-2 mt-2"><button type="button" class="btn btn-outline-secondary" id="addFundingRow"><i class="fas fa-plus me-1"></i>إضافة مصدر آخر</button><button type="submit" class="btn btn-primary">حفظ جميع مصادر التمويل</button></div>
                </form>
            <?php endif; ?>
            <div class="small mb-2">إجمالي التخصيص الحالي: <strong><?php echo number_format($fundingTotal,2); ?> <?php echo e($project['currency_code']?:'SDG'); ?></strong></div>
            <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>التاريخ</th><th>الحساب</th><th>المبلغ</th><th>الوصف</th><th>إجراء</th></tr></thead><tbody>
            <?php foreach($fundings as $f): ?><tr><td class="align-middle"><?php echo e($f['allocation_date']); ?></td><td class="align-middle"><?php echo e(($f['source_account_code']??$f['source_type']).' · '.($f['source_account_name']??'')); ?></td><td class="align-middle"><?php echo number_format((float)$f['amount'],2); ?></td><td class="align-middle"><?php echo !empty($f['description']) ? e($f['description']) : '<span class="text-muted">—</span>'; ?></td><td class="align-middle text-nowrap"><?php if($approval['approval_status']==='submitted' && !$closed): ?><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editFunding<?php echo (int)$f['id']; ?>">تعديل</button> <form method="post" class="d-inline" onsubmit="return confirm('هل تريد حذف تخصيص هذا المصدر بالكامل؟');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_delete_funding"><input type="hidden" name="allocation_id" value="<?php echo (int)$f['id']; ?>"><button class="btn btn-sm btn-outline-danger">حذف</button></form><?php endif; ?></td></tr>
            <?php endforeach; ?><?php if(!$fundings): ?><tr><td colspan="5" class="text-center text-muted">لا توجد تخصيصات تمويل مسجلة بعد.</td></tr><?php endif; ?></tbody></table></div>
            <script>
            document.addEventListener('DOMContentLoaded',function(){
                const rows=document.getElementById('fundingRows'), add=document.getElementById('addFundingRow');
                if(!rows||!add)return;
                add.addEventListener('click',function(){
                    const first=rows.querySelector('.funding-row'), clone=first.cloneNode(true);
                    clone.querySelectorAll('input').forEach(function(input){ input.value=input.name==='allocation_date[]'?new Date().toISOString().slice(0,10):''; });
                    clone.querySelector('select').selectedIndex=0;
                    const remove=document.createElement('button'); remove.type='button'; remove.className='btn btn-sm btn-outline-danger mt-2 remove-funding-row'; remove.textContent='إزالة هذا المصدر';
                    const col=document.createElement('div'); col.className='col-12'; col.appendChild(remove); clone.querySelector('.row').appendChild(col); rows.appendChild(clone);
                });
                rows.addEventListener('click',function(e){ if(e.target.classList.contains('remove-funding-row')){ const all=rows.querySelectorAll('.funding-row'); if(all.length>1)e.target.closest('.funding-row').remove(); }});
            });
            </script>
        <?php else: ?><div class="alert alert-warning">تظهر أدوات تخصيص التمويل بعد اعتماد الميزانية.</div><?php endif; ?>
    </div></div>

    <?php if($approval['approval_status']==='submitted' && $approvedExists): ?><div class="card mb-4 border-primary"><div class="card-body"><h5>الاعتماد المالي للمشروع</h5><div class="alert alert-light border mb-3"><div class="text-muted small mb-1">إجمالي الميزانية المعتمدة</div><div class="fs-4 fw-bold"><?php echo number_format((float)($activeBudget['line_total'] ?? 0),2); ?> <?php echo e($project['currency_code']?:'SDG'); ?></div></div><p class="text-muted">يجب أن يساوي إجمالي تخصيص التمويل الميزانية المعتمدة.</p><div class="d-flex gap-2"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_approve_project"><button class="btn btn-success">اعتماد المشروع مالياً</button></form><button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectProject">رفض المشروع مالياً</button></div></div></div><?php endif; ?>

    <?php if ($approval['approval_status']==='approved'): ?>
    <div class="card mb-4 border-success">
        <div class="card-header bg-success text-white"><i class="fas fa-money-check-dollar me-2"></i>صرف وتمييز مستندات التمويل</div>
        <div class="card-body">
            <div class="alert alert-light border mb-3">تظهر هذه الخيارات فقط بعد الاعتماد النهائي من المدير العام. لا تنشئ هذه الإجراءات قيداً محاسبياً جديداً؛ القيد الذي أنشأه الاعتماد النهائي هو حدث خروج الأموال، وهذه الخطوة توثق سند الصرف أو إيصال التحويل.</div>
            <?php if ($paymentEvidence): ?>
                <?php foreach ($paymentEvidence as $payment): ?>
                    <?php $paymentMethodLabels=['cash'=>'نقدي','bank_transfer'=>'تحويل بنكي','e_wallet'=>'محفظة إلكترونية']; ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3"><div class="small text-muted">حساب التمويل</div><strong><?php echo e(($payment['source_account_code']??'').' · '.($payment['source_account_name']??'')); ?></strong></div>
                            <div class="col-md-2"><div class="small text-muted">طريقة الدفع</div><strong><?php echo e($paymentMethodLabels[$payment['payment_method']]??$payment['payment_method']); ?></strong></div>
                            <div class="col-md-2"><div class="small text-muted">المبلغ</div><strong><?php echo number_format((float)$payment['amount'],2).' '.e($payment['currency_code']?:'SDG'); ?></strong></div>
                            <div class="col-md-2"><div class="small text-muted">المرجع</div><strong><?php echo !empty($payment['reference_number'])?e($payment['reference_number']):'—'; ?></strong></div>
                            <div class="col-md-3">
                                <?php if ($payment['status']==='documented'): ?>
                                    <?php if ($payment['payment_method']==='cash'): ?>
                                        <a class="btn btn-sm btn-primary" target="_blank" href="<?php echo APP_URL; ?>modules/accounting/voucher_print.php?project_payment_id=<?php echo (int)$payment['id']; ?>"><i class="fas fa-print me-1"></i>طباعة سند الصرف</a>
                                    <?php else: ?>
                                        <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?php echo APP_URL; ?>modules/projects/project_payment_receipt.php?id=<?php echo (int)$payment['id']; ?>"><i class="fas fa-paperclip me-1"></i>عرض الإيصال</a>
                                    <?php endif; ?>
                                <?php elseif ($payment['payment_method']==='cash'): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('هل تم صرف النقد فعلياً وتريد توثيق سند الصرف؟');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_confirm_cash_payment"><input type="hidden" name="payment_id" value="<?php echo (int)$payment['id']; ?>"><button class="btn btn-sm btn-success"><i class="fas fa-file-invoice-dollar me-1"></i>تأكيد سند الصرف</button></form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#paymentReceipt<?php echo (int)$payment['id']; ?>"><i class="fas fa-paperclip me-1"></i>إرفاق الإيصال</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-muted">لا توجد سجلات صرف بعد الاعتماد النهائي.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php foreach($paymentEvidence as $payment): ?>
        <?php if ($payment['status']==='pending' && $payment['payment_method']!=='cash'): ?>
        <div class="modal fade" id="paymentReceipt<?php echo (int)$payment['id']; ?>" tabindex="-1">
            <div class="modal-dialog"><div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_upload_payment_receipt"><input type="hidden" name="payment_id" value="<?php echo (int)$payment['id']; ?>">
                    <div class="modal-header"><h5 class="modal-title">إرفاق إيصال <?php echo e($payment['payment_method']==='bank_transfer'?'التحويل البنكي':'المحفظة الإلكترونية'); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <label class="form-label">رقم المرجع</label><input name="payment_reference" class="form-control mb-3" maxlength="100" placeholder="رقم العملية أو المرجع من البنك/المحفظة">
                        <label class="form-label">الإيصال</label><input type="file" name="payment_receipt" class="form-control" accept="application/pdf,image/jpeg,image/png" required>
                        <div class="form-text">PDF أو JPG أو PNG — بحد أقصى 10 ميجابايت.</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button class="btn btn-primary">حفظ الإيصال</button></div>
                </form>
            </div></div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php foreach($fundings as $f): ?><div class="modal fade" id="editFunding<?php echo (int)$f['id']; ?>"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_edit_funding"><input type="hidden" name="allocation_id" value="<?php echo (int)$f['id']; ?>"><div class="modal-header"><h5 class="modal-title">تعديل تخصيص التمويل</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">حساب التمويل</label><select name="source_account_id" class="form-select" required><?php foreach($accounts as $a): ?><option value="<?php echo (int)$a['id']; ?>" <?php echo ((int)$a['id']===(int)$f['source_account_id'])?'selected':''; ?>><?php echo e($a['code'].' · '.$a['name_ar']); ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">المبلغ</label><input type="number" step="0.01" min="0.01" name="funding_amount" class="form-control" value="<?php echo e((string)$f['amount']); ?>" required></div><div class="col-md-6"><label class="form-label">التاريخ</label><input type="date" name="allocation_date" class="form-control" value="<?php echo e((string)$f['allocation_date']); ?>"></div><div class="col-12"><label class="form-label">الوصف</label><input name="funding_description" class="form-control" value="<?php echo e((string)($f['description']??'')); ?>"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button class="btn btn-primary">حفظ التعديل</button></div></form></div></div></div><?php endforeach; ?>

    <div class="modal fade" id="rejectBudget"><div class="modal-dialog"><div class="modal-content"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_reject_budget"><input type="hidden" name="budget_id" value="<?php echo (int)($activeBudget['id']??0); ?>"><div class="modal-header"><h5>رفض الميزانية</h5></div><div class="modal-body"><textarea name="rejection_reason" class="form-control" required placeholder="سبب الرفض"></textarea></div><div class="modal-footer"><button class="btn btn-danger">تأكيد الرفض</button></div></form></div></div></div>
    <div class="modal fade" id="rejectProject"><div class="modal-dialog"><div class="modal-content"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fm_reject_project"><div class="modal-header"><h5>رفض المشروع مالياً</h5></div><div class="modal-body"><textarea name="rejection_reason" class="form-control" required placeholder="سبب الرفض"></textarea></div><div class="modal-footer"><button class="btn btn-danger">تأكيد الرفض</button></div></form></div></div></div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>