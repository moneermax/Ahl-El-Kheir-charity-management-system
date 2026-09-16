<?php
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once dirname(__DIR__,2).'/modules/accounting/fina_lib.php';
Session::start();
if(!Session::isLoggedIn()){header('Location: '.APP_URL.'index.php');exit();}
$role=Session::getUserRole();$uid=(int)Session::getUserId();
if(!in_array($role,['admin','accountant','accountant_staff','financial_manager','supervisor','vice_general_manager'],true)){header('Location: '.APP_URL.'index.php');exit();}
fina_ensure_tables();
$pageTitle='تسجيل تحصيل لصالح فينا الخير';$active='transactions';$errors=[];
$currencies=dbFetchAll("SELECT code FROM currencies ORDER BY code");
$sponsorSearch=trim($_GET['sponsor_q']??'');
$sponsors=[];
if($sponsorSearch!==''){$like='%'.$sponsorSearch.'%';$sponsors=dbFetchAll("SELECT id,sponsor_code,full_name,phone FROM sponsors WHERE full_name LIKE ? OR sponsor_code LIKE ? OR phone LIKE ? ORDER BY full_name LIMIT 30",[$like,$like,$like]);}
$input=['source_type'=>'person','sponsor_id'=>'','source_name'=>'','source_phone'=>'','source_alt_phone'=>'','source_email'=>'','source_address'=>'','source_id_number'=>'','source_reference'=>'','contact_person'=>'','source_details'=>'','amount'=>'','currency_code'=>'SDG','method'=>'cash','date'=>date('Y-m-d'),'purpose_note'=>'','description'=>''];
if($_SERVER['REQUEST_METHOD']==='POST'){
    foreach($input as $k=>$v)$input[$k]=trim($_POST[$k]??$v);
    $input['source_type']=$_POST['source_type']??'person';$input['sponsor_id']=(string)(int)($_POST['sponsor_id']??0);
    if(!verify_csrf())$errors[]='انتهت صلاحية الجلسة.';
    if(!in_array($input['source_type'],['sponsor','person','organization','other'],true))$errors[]='تصنيف مصدر الأموال غير صالح.';
    $sponsor=null;
    if($input['source_type']==='sponsor'){
        $sponsor=fina_source_from_sponsor((int)$input['sponsor_id']);
        if(!$sponsor)$errors[]='يجب اختيار كفيل صحيح من النظام.';
        else{$input['source_name']=$sponsor['full_name'];$input['source_phone']=$sponsor['phone']??'';$input['source_alt_phone']=$sponsor['alt_phone']??'';$input['source_email']=$sponsor['email']??'';$input['source_address']=$sponsor['address']??'';}
    }else{$input['sponsor_id']='';if($input['source_name']==='')$errors[]='اسم مصدر الأموال مطلوب.';}
    if($input['amount']===''||round((float)str_replace(',','',$input['amount']),2)<=0)$errors[]='المبلغ يجب أن يكون أكبر من صفر.';
    if(!$currencies||!in_array($input['currency_code'],array_column($currencies,'code'),true))$errors[]='العملة المحددة غير صالحة.';
    if(!in_array($input['method'],['cash','bank_transfer','credit_card','mobile','other'],true))$errors[]='طريقة الدفع غير صالحة.';
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$input['date']))$errors[]='تاريخ التحصيل غير صالح.';
    if($input['source_email']!==''&&!filter_var($input['source_email'],FILTER_VALIDATE_EMAIL))$errors[]='البريد الإلكتروني لمصدر الأموال غير صالح.';
    $amount=round((float)str_replace(',','',$input['amount']),2);$receiptPath=null;
    if(isset($_FILES['receipt_file'])&&$_FILES['receipt_file']['error']===UPLOAD_ERR_OK){$file=$_FILES['receipt_file'];$ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));if(!in_array($ext,['jpg','jpeg','png','pdf'],true))$errors[]='صيغة الإيصال يجب أن تكون JPG أو PNG أو PDF.';elseif($file['size']>10*1024*1024)$errors[]='حجم الإيصال يتجاوز 10 ميجابايت.';else{$dir=dirname(__DIR__,2).'/storage/receipts';if(!is_dir($dir))@mkdir($dir,0777,true);$fn='FINA-EXT-'.date('YmdHis').'-'.bin2hex(random_bytes(3)).'.'.$ext;if(move_uploaded_file($file['tmp_name'],$dir.'/'.$fn))$receiptPath='storage/receipts/'.$fn;else$errors[]='فشل حفظ الإيصال على الخادم.';}}elseif(isset($_FILES['receipt_file'])&&$_FILES['receipt_file']['error']!==UPLOAD_ERR_NO_FILE)$errors[]='حدث خطأ أثناء رفع الإيصال.';
    if(!$errors){try{db()->beginTransaction();
        $sourceId=0;
        if($input['source_type']==='sponsor'){
            $sourceId=(int)(dbFetchOne("SELECT id FROM fina_sources WHERE source_type='sponsor' AND sponsor_id=? LIMIT 1",[(int)$input['sponsor_id']])['id']??0);
        }
        if($sourceId<=0){dbExecute("INSERT INTO fina_sources (source_type,sponsor_id,source_name,source_phone,source_alt_phone,source_email,source_address,source_id_number,source_reference,contact_person,source_details,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",[$input['source_type'],$input['source_type']==='sponsor'?(int)$input['sponsor_id']:null,$input['source_name']?:null,$input['source_phone']?:null,$input['source_alt_phone']?:null,$input['source_email']?:null,$input['source_address']?:null,$input['source_id_number']?:null,$input['source_reference']?:null,$input['contact_person']?:null,$input['source_details']?:null,$uid]);$sourceId=(int)dbLastInsertId();}
        if($sourceId<=0)throw new RuntimeException('تعذر إنشاء مصدر فينا الخير.');
        dbExecute("INSERT INTO fina_collections (fina_source_id,amount,currency_code,payment_method,collection_date,receipt_path,purpose_note,description,status,created_by) VALUES (?,?,?,?,?,?,?,?,'pending',?)",[$sourceId,$amount,$input['currency_code'],$input['method'],$input['date'],$receiptPath,$input['purpose_note']?:null,$input['description']?:null,$uid]);
        if((int)dbLastInsertId()<=0)throw new RuntimeException('تعذر إنشاء سجل التحصيل.');
        db()->commit();flash('success','تم تسجيل تحصيل فينا الخير وإرساله للمدير المالي للمراجعة.');header('Location: '.APP_URL.'modules/accounting/fina_payment_review.php');exit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($receiptPath&&is_file(dirname(__DIR__,2).'/'.$receiptPath))@unlink(dirname(__DIR__,2).'/'.$receiptPath);$errors[]='تعذر حفظ تحصيل فينا الخير. لم يتم حفظ أي جزء من العملية.';}}
}
include dirname(__DIR__,2).'/includes/header.php';?>
<div class="welcome-section fade-in"><h2><i class="fas fa-hand-holding-dollar me-2"></i>تسجيل تحصيل لصالح فينا الخير</h2><p>تحصيل مستقل مملوك بنسبة 100% لفينا الخير. يمكن أن يكون المصدر كفيلاً من النظام أو مصدراً خارجياً.</p></div><?php include dirname(__DIR__,2).'/includes/alerts.php';?>
<?php if($errors):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e):?><li><?php echo e($e);?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="card fade-in"><div class="card-header text-white" style="background:#1b4d8f">بيانات مصدر الأموال والتحصيل</div><div class="card-body"><form method="post" enctype="multipart/form-data"><?php echo csrf_field();?><div class="row g-3">
<div class="col-md-4"><label class="form-label">مصدر الأموال *</label><select name="source_type" id="finaSourceType" class="form-select" required><option value="sponsor" <?php echo $input['source_type']==='sponsor'?'selected':'';?>>كفيل من أهل الخير</option><option value="person" <?php echo $input['source_type']==='person'?'selected':'';?>>شخص خارجي</option><option value="organization" <?php echo $input['source_type']==='organization'?'selected':'';?>>منظمة / جهة</option><option value="other" <?php echo $input['source_type']==='other'?'selected':'';?>>أخرى</option></select></div>
<div class="col-md-8" id="finaSponsorPicker"><label class="form-label">اختيار الكفيل *</label><select name="sponsor_id" id="finaSponsorId" class="form-select"><option value="">— ابحث واختر الكفيل —</option><?php foreach($sponsors as $s):?><option value="<?php echo(int)$s['id'];?>" <?php echo (string)$s['id']===$input['sponsor_id']?'selected':'';?>><?php echo e(($s['sponsor_code']??'').' — '.$s['full_name'].' — '.($s['phone']??''));?></option><?php endforeach;?></select><small class="text-muted">اكتب ?sponsor_q= في الرابط للبحث، أو استخدم قائمة الكفلاء المحملة.</small></div>
<div class="col-md-8" id="finaExternalName"><label class="form-label">اسم مصدر الأموال *</label><input type="text" name="source_name" id="finaSourceName" class="form-control" value="<?php echo e($input['source_name']);?>"></div>
<div class="col-md-4"><label class="form-label">الهاتف</label><input type="tel" name="source_phone" id="finaSourcePhone" class="form-control" value="<?php echo e($input['source_phone']);?>"></div><div class="col-md-4"><label class="form-label">هاتف إضافي</label><input type="tel" name="source_alt_phone" id="finaSourceAltPhone" class="form-control" value="<?php echo e($input['source_alt_phone']);?>"></div><div class="col-md-4"><label class="form-label">البريد الإلكتروني</label><input type="email" name="source_email" id="finaSourceEmail" class="form-control" value="<?php echo e($input['source_email']);?>"></div>
<div class="col-md-6"><label class="form-label">العنوان / موقع المصدر</label><input type="text" name="source_address" id="finaSourceAddress" class="form-control" value="<?php echo e($input['source_address']);?>"></div><div class="col-md-3"><label class="form-label">رقم الهوية / السجل</label><input type="text" name="source_id_number" class="form-control" value="<?php echo e($input['source_id_number']);?>"></div><div class="col-md-3"><label class="form-label">رقم مرجعي للمصدر</label><input type="text" name="source_reference" class="form-control" value="<?php echo e($input['source_reference']);?>"></div>
<div class="col-md-4"><label class="form-label">اسم جهة الاتصال</label><input type="text" name="contact_person" class="form-control" value="<?php echo e($input['contact_person']);?>"></div><div class="col-md-8"><label class="form-label">تفاصيل إضافية عن المصدر / الجهة</label><input type="text" name="source_details" class="form-control" value="<?php echo e($input['source_details']);?>"></div>
<div class="col-md-4"><label class="form-label">المبلغ *</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="<?php echo e($input['amount']);?>"></div><div class="col-md-4"><label class="form-label">العملة *</label><select name="currency_code" class="form-select" required><?php foreach($currencies as $c):?><option value="<?php echo e($c['code']);?>" <?php echo $input['currency_code']===$c['code']?'selected':'';?>><?php echo e($c['code']);?></option><?php endforeach;?></select></div><div class="col-md-4"><label class="form-label">طريقة الدفع *</label><select name="method" class="form-select"><option value="cash">نقدي / كاش</option><option value="bank_transfer">تحويل بنكي</option><option value="mobile">محفظة إلكترونية</option><option value="credit_card">بطاقة</option><option value="other">أخرى</option></select></div>
<div class="col-md-4"><label class="form-label">تاريخ التحصيل *</label><input type="date" name="date" class="form-control" required value="<?php echo e($input['date']);?>"></div><div class="col-md-8"><label class="form-label">إيصال التحصيل</label><input type="file" name="receipt_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div><div class="col-12"><label class="form-label">الغرض / التخصيص</label><input type="text" name="purpose_note" class="form-control" value="<?php echo e($input['purpose_note']);?>"></div><div class="col-12"><label class="form-label">ملاحظات</label><textarea name="description" class="form-control" rows="2"><?php echo e($input['description']);?></textarea></div><div class="col-12"><div class="alert alert-warning mb-0"><strong>تنبيه محاسبي:</strong> 100% من هذا التحصيل مخصص لفينا الخير، ويسجل كالتزام مستحق لفينا الخير في حساب 2300. لا يسجل كإيراد لأهل الخير، ولا تحسب عليه رسوم إدارية، ولا ينشئ التزام كفالة.</div></div></div><div class="mt-4"><button class="btn btn-primary btn-lg"><i class="fas fa-paper-plane me-1"></i> إرسال للمدير المالي</button></div></form></div></div>
<script>
(function(){const type=document.getElementById('finaSourceType'),picker=document.getElementById('finaSponsorPicker'),external=document.getElementById('finaExternalName'),ids=['finaSourceName','finaSourcePhone','finaSourceAltPhone','finaSourceEmail','finaSourceAddress'];function sync(){const sponsor=type.value==='sponsor';picker.style.display=sponsor?'block':'none';external.style.display=sponsor?'none':'block';ids.forEach(id=>{const el=document.getElementById(id);if(el){el.readOnly=sponsor;el.classList.toggle('bg-light',sponsor);}})}type.addEventListener('change',sync);sync();})();
</script>
<?php include dirname(__DIR__,2).'/includes/footer.php';?>