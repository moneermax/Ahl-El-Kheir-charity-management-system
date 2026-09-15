<?php
// modules/transactions/fina_payment_create.php - Supervisor Fina Al-Khair payment intake
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once dirname(__DIR__,2).'/config/sponsor_assignments.php';
require_once dirname(__DIR__,2).'/modules/accounting/lib.php';
Session::start();
if(!Session::isLoggedIn()){header('Location: '.APP_URL.'index.php');exit();}
$role=Session::getUserRole();$uid=(int)Session::getUserId();
if($role!=='supervisor'){header('Location: '.APP_URL.'index.php');exit();}
$pageTitle='تسجيل دفعة لصالح Fina Al-Khair';$active='transactions';$errors=[];
$monthOptions=[];for($i=-24;$i<=12;$i++)$monthOptions[]=date('F/Y',strtotime(date('Y-m-01')." $i months"));$currentMonth=date('F/Y');
$mySponsors=[];$rows=dbFetchAll("SELECT id,full_name,sponsor_code,supervisor_id,first_letter_id,gender FROM sponsors WHERE supervisor_id=? OR first_letter_id IN (SELECT letter_id FROM supervisor_letters WHERE supervisor_id=?) ORDER BY full_name",[$uid,$uid]);foreach($rows as $row)if(supervisorCanAccessSponsor($uid,$row))$mySponsors[]=$row;
$input=['sponsor_id'=>(int)($_GET['sponsor_id']??0),'sponsorship_id'=>(int)($_GET['sponsorship_id']??0),'type'=>'monthly_sponsorship','month'=>$currentMonth,'amount'=>'','fina_share'=>'','method'=>'cash','date'=>date('Y-m-d'),'purpose_note'=>'','description'=>''];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $input['sponsor_id']=(int)($_POST['sponsor_id']??0);$input['sponsorship_id']=(int)($_POST['sponsorship_id']??0);$input['type']=$_POST['type']??'monthly_sponsorship';$input['month']=trim($_POST['month']??'');$input['amount']=trim($_POST['amount']??'');$input['fina_share']=trim($_POST['fina_share']??'');$input['method']=$_POST['method']??'cash';$input['date']=trim($_POST['date']??'')?:date('Y-m-d');$input['purpose_note']=trim($_POST['purpose_note']??'');$input['description']=trim($_POST['description']??'');
    if(!verify_csrf())$errors[]='انتهت صلاحية الجلسة.';
    $sponsor=null;if($input['sponsor_id']>0){$sponsor=dbFetchOne("SELECT id,full_name,sponsor_code,supervisor_id,first_letter_id,gender FROM sponsors WHERE id=?",[$input['sponsor_id']]);if(!$sponsor||!supervisorCanAccessSponsor($uid,$sponsor))$errors[]='هذا الكفيل خارج نطاق إشرافك.';}
    $ship=null;if($input['sponsorship_id']>0){$ship=dbFetchOne("SELECT id,sponsor_id,monthly_amount,status,currency_code FROM sponsorships WHERE id=?",[$input['sponsorship_id']]);if(!$ship)$errors[]='الكفالة المحددة غير موجودة.';elseif((int)$ship['sponsor_id']!==$input['sponsor_id'])$errors[]='الكفالة لا تخص الكفيل المحدد.';elseif($ship['status']!=='active')$errors[]='الكفالة المحددة غير نشطة.';}
    if(!in_array($input['type'],['monthly_sponsorship','general_donation','project_donation','other'],true))$errors[]='نوع الدفعة غير صالح.';
    $amount=round((float)str_replace(',','',$input['amount']),2);$finaShare=round((float)str_replace(',','',$input['fina_share']),2);
    if($amount<=0)$errors[]='إجمالي الدفعة يجب أن يكون أكبر من صفر.';
    if($finaShare<0||$finaShare>$amount)$errors[]='حصة Fina يجب أن تكون بين صفر وإجمالي الدفعة.';
    if($input['type']==='monthly_sponsorship'){if(!$ship)$errors[]='اختر الكفالة المرتبطة بالدفعة.';if(!in_array($input['month'],$monthOptions,true))$errors[]='الشهر المحدد غير صالح.';}
    if(!in_array($input['method'],['cash','bank_transfer','credit_card','mobile','other'],true))$errors[]='طريقة الدفع غير صالحة.';
    if(!$errors){
        $mode=$finaShare<=0?'ahl_only':($finaShare>=$amount?'fina_only':'shared');$paymentType=$input['type'];$purpose=$input['purpose_note']!==''?$input['purpose_note']:null;$period=$input['type']==='monthly_sponsorship'?$input['month']:null;
        try{db()->beginTransaction();
            dbExecute("INSERT INTO sponsor_payments (sponsorship_id,supervisor_id,payment_type,sponsor_id,project_id,other_source_note,purpose_note,payment_period,amount,currency_code,payment_method,notes,status) VALUES (?,?,?,?,NULL,NULL,?,?,?,?,?,?,'pending')",[$ship['id']??null,$uid,$paymentType,$input['sponsor_id']>0?$input['sponsor_id']:null,$purpose,$period,$amount,$ship['currency_code']??'SDG',$input['method'],$input['description']!==''?$input['description']:null]);
            $spId=(int)dbLastInsertId();if($spId<=0)throw new RuntimeException('تعذر إنشاء سجل التحصيل.');
            $integration='FINA-INTAKE-SP-'.$spId;
            dbExecute("INSERT INTO fina_payment_intakes (sponsor_payment_id,allocation_mode,fina_share_amount,integration_reference,created_by) VALUES (?,?,?,?,?)",[$spId,$mode,$finaShare,$integration,$uid]);
            db()->commit();
            flash('success','تم تسجيل دفعة Fina وإرسالها للمدير المالي للمراجعة.');header('Location: '.APP_URL.'modules/accounting/fina_payment_review.php');exit();
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$errors[]='تعذر حفظ دفعة Fina بشكل ذري. لم يتم حفظ أي جزء من العملية.';}
    }
}
include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in"><h2><i class="fas fa-hand-holding-dollar me-2"></i>تسجيل دفعة لصالح Fina Al-Khair</h2><p>يسجل المشرف التحصيل والتخصيص فقط. لا يتم إنشاء قيد محاسبي هنا؛ الاعتماد والترحيل من مسؤولية المدير المالي.</p></div>
<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?><?php if($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e)echo '<li>'.e($e).'</li>'; ?></ul></div><?php endif; ?>
<div class="card fade-in"><div class="card-header text-white" style="background:#1b4d8f">تفاصيل التحصيل والتخصيص</div><div class="card-body"><form method="post"><?php echo csrf_field(); ?><div class="row g-3">
<div class="col-md-6"><label class="form-label">الكفيل *</label><select name="sponsor_id" id="sponsorId" class="form-select" required><option value="">— اختر —</option><?php foreach($mySponsors as $s): ?><option value="<?php echo (int)$s['id']; ?>" <?php echo $input['sponsor_id']===(int)$s['id']?'selected':''; ?>><?php echo e($s['full_name']); ?> — <?php echo e($s['sponsor_code']); ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">نوع الدفعة *</label><select name="type" id="paymentType" class="form-select"><option value="monthly_sponsorship" <?php echo $input['type']==='monthly_sponsorship'?'selected':''; ?>>كفالة شهرية</option><option value="general_donation" <?php echo $input['type']==='general_donation'?'selected':''; ?>>تبرع عام</option><option value="project_donation" <?php echo $input['type']==='project_donation'?'selected':''; ?>>تبرع مشروع</option><option value="other" <?php echo $input['type']==='other'?'selected':''; ?>>أخرى</option></select></div>
<div class="col-md-6" id="sponsorshipBox"><label class="form-label">الكفالة *</label><select name="sponsorship_id" id="sponsorshipId" class="form-select"><option value="">— اختر الكفالة —</option></select></div>
<div class="col-md-6" id="monthBox"><label class="form-label">عن شهر *</label><select name="month" class="form-select"><?php foreach($monthOptions as $m): ?><option value="<?php echo e($m); ?>" <?php echo $input['month']===$m?'selected':''; ?>><?php echo e($m); ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">إجمالي ما دفعه الكفيل *</label><input type="number" step="0.01" min="0.01" name="amount" id="grossAmount" class="form-control" required value="<?php echo e($input['amount']); ?>"></div>
<div class="col-md-4"><label class="form-label">المخصص لـ Fina Al-Khair *</label><input type="number" step="0.01" min="0" name="fina_share" id="finaShare" class="form-control" required value="<?php echo e($input['fina_share']); ?>"><div class="form-text">يمكن أن يكون صفر، كامل المبلغ، أو جزءاً منه.</div></div>
<div class="col-md-4"><label class="form-label">حصة أهل الخير</label><input type="text" id="ahlShare" class="form-control" readonly></div>
<div class="col-md-4"><label class="form-label">طريقة الدفع</label><select name="method" class="form-select"><option value="cash">نقدي / كاش</option><option value="bank_transfer">تحويل بنكي</option><option value="mobile">محفظة إلكترونية</option><option value="credit_card">بطاقة</option><option value="other">أخرى</option></select></div>
<div class="col-md-4"><label class="form-label">تاريخ التحصيل *</label><input type="date" name="date" class="form-control" required value="<?php echo e($input['date']); ?>"></div>
<div class="col-12"><label class="form-label">ملاحظات</label><textarea name="description" class="form-control" rows="2"><?php echo e($input['description']); ?></textarea></div>
<div class="col-12"><label class="form-label">توضيح الغرض / التخصيص</label><input type="text" name="purpose_note" class="form-control" value="<?php echo e($input['purpose_note']); ?>"></div>
<div class="col-12"><div class="alert alert-warning mb-0"><strong>تنبيه محاسبي:</strong> حصة Fina تسجل كأموال طرف ثالث مستحقة لـ Fina، وليست إيراداً لأهل الخير. الرسوم الإدارية — إن انطبقت — تحسب على حصة أهل الخير فقط.</div></div>
</div><div class="mt-4"><button class="btn btn-primary btn-lg"><i class="fas fa-paper-plane me-1"></i> إرسال للمدير المالي</button></div></form></div></div>
<script>
function calc(){const a=parseFloat(document.getElementById('grossAmount').value)||0;const f=parseFloat(document.getElementById('finaShare').value)||0;document.getElementById('ahlShare').value=Math.max(0,a-f).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})+' ج.س';}
document.getElementById('grossAmount').addEventListener('input',calc);document.getElementById('finaShare').addEventListener('input',calc);document.getElementById('paymentType').addEventListener('change',function(){const m=this.value==='monthly_sponsorship';document.getElementById('sponsorshipBox').style.display=m?'':'none';document.getElementById('monthBox').style.display=m?'':'none';});calc();document.getElementById('paymentType').dispatchEvent(new Event('change'));
</script>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>
