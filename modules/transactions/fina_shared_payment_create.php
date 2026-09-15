<?php
// modules/transactions/fina_shared_payment_create.php - Sponsor-originated Ahl/Fina shared payment intake
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once dirname(__DIR__,2).'/config/sponsor_assignments.php';
require_once dirname(__DIR__,2).'/modules/accounting/lib_fina.php';
require_once dirname(__DIR__,2).'/modules/accounting/lib_transaction_review.php';
Session::start();
if(!Session::isLoggedIn()){header('Location: '.APP_URL.'index.php');exit();}
$role=Session::getUserRole();$uid=(int)Session::getUserId();
if(!in_array($role,['supervisor','admin','financial_manager','vice_general_manager'],true)){header('Location: '.APP_URL.'index.php');exit();}
$pageTitle='تسجيل دفعة كفيل مشتركة مع فينا الخير';$active='transactions';$errors=[];
$currencies=dbFetchAll("SELECT code,name FROM currencies ORDER BY code");
$input=['sponsor_id'=>(int)($_GET['sponsor_id']??0),'sponsorship_id'=>(int)($_GET['sponsorship_id']??0),'amount'=>'','fina_share'=>'','currency_code'=>'SDG','method'=>'cash','date'=>date('Y-m-d'),'purpose'=>'monthly_sponsorship','payment_period'=>date('F/Y'),'purpose_note'=>'','description'=>''];
$mySponsorIds=[];
if($role==='supervisor'){
    $rows=dbFetchAll("SELECT id,supervisor_id,first_letter_id,gender FROM sponsors WHERE supervisor_id=? OR first_letter_id IN (SELECT letter_id FROM supervisor_letters WHERE supervisor_id=?)",[$uid,$uid]);
    foreach($rows as $r)if(supervisorCanAccessSponsor($uid,$r))$mySponsorIds[]=(int)$r['id'];
}
$sponsorSql="SELECT id,full_name,sponsor_code FROM sponsors";$sponsorParams=[];
if($role==='supervisor'){
    if(!$mySponsorIds)$sponsorSql.=' WHERE 0=1';
    else{$ph=implode(',',array_fill(0,count($mySponsorIds),'?'));$sponsorSql.=" WHERE id IN ($ph)";$sponsorParams=$mySponsorIds;}
}
$sponsorSql.=' ORDER BY full_name LIMIT 500';$sponsors=dbFetchAll($sponsorSql,$sponsorParams);
if($input['sponsor_id']>0){$candidate=dbFetchOne("SELECT id,full_name,sponsor_code,supervisor_id,first_letter_id,gender FROM sponsors WHERE id=?",[$input['sponsor_id']]);if(!$candidate||($role==='supervisor'&&!supervisorCanAccessSponsor($uid,$candidate))){$input['sponsor_id']=0;$errors[]='هذا الكفيل خارج نطاق إشرافك.';}}
$ships=[];
if($input['sponsor_id']>0)$ships=dbFetchAll("SELECT sp.id,sp.sponsorship_code,sp.monthly_amount,fc.child_name FROM sponsorships sp JOIN family_children fc ON fc.id=sp.child_id WHERE sp.sponsor_id=? AND sp.status='active' ORDER BY sp.sponsorship_code",[$input['sponsor_id']]);
if($_SERVER['REQUEST_METHOD']==='POST'){
    $input['sponsor_id']=(int)($_POST['sponsor_id']??0);$input['sponsorship_id']=(int)($_POST['sponsorship_id']??0);$input['amount']=trim($_POST['amount']??'');$input['fina_share']=trim($_POST['fina_share']??'');$input['currency_code']=trim($_POST['currency_code']??'SDG');$input['method']=$_POST['method']??'cash';$input['date']=trim($_POST['date']??'')?:date('Y-m-d');$input['purpose']=$_POST['purpose']??'monthly_sponsorship';$input['payment_period']=trim($_POST['payment_period']??'');$input['purpose_note']=trim($_POST['purpose_note']??'');$input['description']=trim($_POST['description']??'');
    if(!verify_csrf())$errors[]='انتهت صلاحية الجلسة.';
    if($input['sponsor_id']<=0)$errors[]='اختر الكفيل.';
    if($input['sponsor_id']>0){$s=dbFetchOne("SELECT id,supervisor_id,first_letter_id,gender FROM sponsors WHERE id=?",[$input['sponsor_id']]);if(!$s||($role==='supervisor'&&!supervisorCanAccessSponsor($uid,$s)))$errors[]='هذا الكفيل خارج نطاق صلاحيتك.';}
    if($input['sponsorship_id']<=0)$errors[]='اختر الكفالة.';
    $ship=$input['sponsorship_id']>0?dbFetchOne("SELECT id,sponsor_id,status,monthly_amount FROM sponsorships WHERE id=?",[$input['sponsorship_id']]):null;
    if(!$ship||((int)$ship['sponsor_id']!==$input['sponsor_id'])||$ship['status']!=='active')$errors[]='الكفالة المحددة غير صالحة أو غير نشطة.';
    $gross=round((float)str_replace(',','',$input['amount']),2);$fina=round((float)str_replace(',','',$input['fina_share']),2);
    try{[$mode,$ahl,$fina]=ak_fina_validate_amounts($gross,$fina);}catch(Throwable $e){$errors[]=$e->getMessage();$mode=null;$ahl=0.0;}
    if($fina<=0||$ahl<=0)$errors[]='هذه الشاشة مخصصة للدفعة المشتركة: يجب أن تكون هناك حصة لأهل الخير وحصة لفينا الخير.';
    if(!$currencies||!in_array($input['currency_code'],array_column($currencies,'code'),true))$errors[]='العملة المحددة غير صالحة.';
    if(!in_array($input['method'],['cash','bank_transfer','credit_card','mobile','other'],true))$errors[]='طريقة الدفع غير صالحة.';
    if($input['purpose']!=='monthly_sponsorship')$errors[]='الدفعة المشتركة هنا مرتبطة بكفالة شهرية فقط.';
    if($input['payment_period']==='')$errors[]='حدد فترة الكفالة.';
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$input['date']))$errors[]='تاريخ التحصيل غير صالح.';
    $receiptPath=null;
    if(isset($_FILES['receipt_file'])&&$_FILES['receipt_file']['error']===UPLOAD_ERR_OK){$file=$_FILES['receipt_file'];$ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));if(!in_array($ext,['jpg','jpeg','png','pdf'],true))$errors[]='صيغة الإيصال يجب أن تكون JPG أو PNG أو PDF.';elseif($file['size']>10*1024*1024)$errors[]='حجم الإيصال يتجاوز 10 ميجابايت.';else{$dir=dirname(__DIR__,2).'/storage/receipts';if(!is_dir($dir))@mkdir($dir,0777,true);$fn='TR-FINA-SHARED-'.date('YmdHis').'-'.bin2hex(random_bytes(3)).'.'.$ext;if(move_uploaded_file($file['tmp_name'],$dir.'/'.$fn))$receiptPath='storage/receipts/'.$fn;else$errors[]='فشل حفظ الإيصال.';}}
    if(!$errors){
        try{db()->beginTransaction();
            $sourceNote=json_encode(['source_type'=>'sponsor','source_name'=>dbFetchOne("SELECT full_name FROM sponsors WHERE id=?",[$input['sponsor_id']])['full_name']??'','source_details'=>'Sponsor-originated shared Ahl/Fina payment'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            dbExecute("INSERT INTO sponsor_payments (sponsorship_id,supervisor_id,payment_type,sponsor_id,project_id,other_source_note,purpose_note,payment_period,payment_date,amount,currency_code,payment_method,receipt_file_path,notes,status) VALUES (?,?,?,?,NULL,?,?,?,?,?,?,?,?,?,'pending')",[$input['sponsorship_id'],$uid,'monthly_sponsorship',$input['sponsor_id'],$sourceNote,$input['purpose_note']!==''?$input['purpose_note']:null,$input['payment_period'],$input['date'],$gross,$input['currency_code'],$input['method'],$receiptPath,$input['description']!==''?$input['description']:null]);
            $spId=(int)dbLastInsertId();if($spId<=0)throw new RuntimeException('تعذر إنشاء سجل التحصيل.');
            dbExecute("INSERT INTO fina_payment_intakes (sponsor_payment_id,allocation_mode,fina_share_amount,integration_reference,created_by) VALUES (?,?,?,?,?)",[$spId,$mode,$fina,'FINA-SHARED-INTAKE-SP-'.$spId,$uid]);
            db()->commit();
            flash('success','تم تسجيل دفعة الكفيل المشتركة وإرسالها للمدير المالي للمراجعة. حصة فينا الخير محمية كمبلغ طرف ثالث.');
            header('Location: '.APP_URL.'modules/accounting/fina_payment_review.php');exit();
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($receiptPath&&is_file(dirname(__DIR__,2).'/'.$receiptPath))@unlink(dirname(__DIR__,2).'/'.$receiptPath);$errors[]='تعذر حفظ الدفعة المشتركة بشكل ذري. لم يتم حفظ أي جزء من العملية.';}
    }
    $ships=$input['sponsor_id']>0?dbFetchAll("SELECT id,sponsorship_code,monthly_amount,child_id FROM sponsorships WHERE sponsor_id=? AND status='active' ORDER BY sponsorship_code",[$input['sponsor_id']]):[];
}
include dirname(__DIR__,2).'/includes/header.php';?>
<div class="welcome-section fade-in"><h2><i class="fas fa-scale-balanced me-2"></i>تسجيل دفعة كفيل مشتركة مع فينا الخير</h2><p>هذه الشاشة للدفعة الواردة من كفيل لدى أهل الخير عندما تكون جزء من المبلغ مخصصاً لفينا الخير. الدفعة الخارجية التي لا تخص كفيلاً تستخدم شاشة التحصيل الخارجي لفينا الخير.</p></div>
<?php include dirname(__DIR__,2).'/includes/alerts.php';?><?php if($errors):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e):?><li><?php echo e($e);?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="card fade-in"><div class="card-header text-white" style="background:#1b4d8f">بيانات الدفعة المشتركة</div><div class="card-body"><form method="post" enctype="multipart/form-data"><?php echo csrf_field();?><div class="row g-3">
<div class="col-md-6"><label class="form-label">الكفيل *</label><select name="sponsor_id" id="sponsor_id" class="form-select" required onchange="this.form.submit()"><option value="">— اختر الكفيل —</option><?php foreach($sponsors as $s):?><option value="<?php echo(int)$s['id'];?>" <?php echo $input['sponsor_id']===(int)$s['id']?'selected':'';?>><?php echo e($s['full_name']);?> — <?php echo e($s['sponsor_code']);?></option><?php endforeach;?></select><div class="form-text">يُستخدم هذا المسار فقط لكفيل موجود في أهل الخير.</div></div>
<div class="col-md-6"><label class="form-label">الكفالة *</label><select name="sponsorship_id" class="form-select" required><option value="">— اختر الكفالة —</option><?php foreach($ships as $sh):?><option value="<?php echo(int)$sh['id'];?>" <?php echo $input['sponsorship_id']===(int)$sh['id']?'selected':'';?>><?php echo e($sh['sponsorship_code']);?> — <?php echo e($sh['child_name']??$sh['id']);?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label">إجمالي المبلغ *</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="<?php echo e($input['amount']);?>"></div><div class="col-md-4"><label class="form-label">حصة فينا الخير *</label><input type="number" step="0.01" min="0.01" name="fina_share" class="form-control" required value="<?php echo e($input['fina_share']);?>"></div><div class="col-md-4"><label class="form-label">حصة أهل الخير</label><input type="text" id="ahl_share" class="form-control" readonly></div>
<div class="col-md-4"><label class="form-label">العملة *</label><select name="currency_code" class="form-select"><?php foreach($currencies as $c):?><option value="<?php echo e($c['code']);?>" <?php echo $input['currency_code']===$c['code']?'selected':'';?>><?php echo e($c['code']);?> — <?php echo e($c['name']);?></option><?php endforeach;?></select></div><div class="col-md-4"><label class="form-label">تاريخ التحصيل *</label><input type="date" name="date" class="form-control" value="<?php echo e($input['date']);?>" required></div><div class="col-md-4"><label class="form-label">طريقة الدفع *</label><select name="method" class="form-select"><option value="cash">نقدي / كاش</option><option value="bank_transfer">تحويل بنكي</option><option value="mobile">محفظة إلكترونية</option><option value="credit_card">بطاقة</option><option value="other">أخرى</option></select></div>
<div class="col-md-4"><label class="form-label">شهر الكفالة *</label><input type="text" name="payment_period" class="form-control" value="<?php echo e($input['payment_period']);?>" placeholder="مثال: September/2026" required></div><div class="col-md-8"><label class="form-label">إيصال</label><input type="file" name="receipt_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf"><div class="form-text">JPG / PNG / PDF حتى 10MB.</div></div>
<div class="col-12"><label class="form-label">توضيح الغرض</label><input type="text" name="purpose_note" class="form-control" value="<?php echo e($input['purpose_note']);?>"></div><div class="col-12"><label class="form-label">ملاحظات</label><textarea name="description" class="form-control" rows="2"><?php echo e($input['description']);?></textarea></div>
<div class="col-12"><div class="alert alert-warning mb-0"><strong>قاعدة محاسبية:</strong> حصة فينا الخير ليست إيراداً لأهل الخير. الرسوم الإدارية تُحسب فقط على حصة أهل الخير عند اعتماد الدفعة، وحصة فينا الخير تُسجل في الحساب 2300 كالتزام محمي.</div></div></div><div class="mt-4"><button class="btn btn-primary btn-lg"><i class="fas fa-paper-plane me-1"></i> إرسال للمراجعة المالية</button><a href="<?php echo APP_URL;?>modules/transactions/create.php" class="btn btn-secondary btn-lg ms-2">العودة لتسجيل الدفعة العادية</a></div></form></div></div>
<script>function calc(){var a=parseFloat(document.querySelector('[name=amount]').value)||0,f=parseFloat(document.querySelector('[name=fina_share]').value)||0;document.getElementById('ahl_share').value=Math.max(0,a-f).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}document.addEventListener('input',calc);document.addEventListener('DOMContentLoaded',calc);</script>
<?php include dirname(__DIR__,2).'/includes/footer.php';?>