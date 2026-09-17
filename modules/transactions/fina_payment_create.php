<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/fina_lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_transaction_review.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
$uid = (int) Session::getUserId();
if (!in_array($role, ['admin','accountant','accountant_staff','financial_manager','supervisor','vice_general_manager'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }

fina_ensure_tables();
$pageTitle = 'تسجيل تحصيل لصالح فينا الخير';
$active = 'transactions';
$errors = [];
$currencies = dbFetchAll("SELECT code FROM currencies ORDER BY code");

// Sponsor lookup: first-name prefix plus additional full-name token narrowing.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fina_sponsor_search'])) {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string)$_GET['fina_sponsor_search']);
    $tokens = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
    $firstName = $tokens[0] ?? '';
    if ($firstName === '') { echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit(); }
    $conditions = ["SUBSTRING_INDEX(TRIM(full_name),' ',1) LIKE ?"];
    $params = [$firstName . '%'];
    for ($i = 1; $i < count($tokens); $i++) {
        $conditions[] = "full_name LIKE ?";
        $params[] = '%' . $tokens[$i] . '%';
    }
    $rows = dbFetchAll("SELECT id,sponsor_code,full_name,phone,alt_phone,email,address FROM sponsors WHERE status='active' AND " . implode(' AND ', $conditions) . " ORDER BY full_name LIMIT 30", $params);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit();
}

$input = ['source_type'=>'person','sponsor_id'=>'','source_name'=>'','source_phone'=>'','source_alt_phone'=>'','source_email'=>'','source_address'=>'','source_id_number'=>'','source_reference'=>'','contact_person'=>'','source_details'=>'','amount'=>'','currency_code'=>'SDG','method'=>'cash','date'=>date('Y-m-d'),'purpose_note'=>'','description'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input['source_type'] = $_POST['source_type'] ?? 'person';
    $input['sponsor_id'] = (string)(int)($_POST['sponsor_id'] ?? 0);
    foreach (['source_name','source_phone','source_alt_phone','source_email','source_address','source_id_number','source_reference','contact_person','source_details','amount','currency_code','method','date','purpose_note','description'] as $k) $input[$k] = trim($_POST[$k] ?? '');
    if (!verify_csrf()) $errors[] = 'انتهت صلاحية الجلسة.';
    if (!in_array($input['source_type'], ['sponsor','person','organization','other'], true)) $errors[] = 'تصنيف مصدر الأموال غير صالح.';
    $sponsor = null;
    if ($input['source_type'] === 'sponsor') {
        $sponsor = fina_source_from_sponsor((int)$input['sponsor_id']);
        if (!$sponsor) $errors[] = 'يجب اختيار كفيل صحيح من النظام.';
        else {
            $input['source_name'] = $sponsor['full_name']; $input['source_phone'] = $sponsor['phone'] ?? ''; $input['source_alt_phone'] = $sponsor['alt_phone'] ?? ''; $input['source_email'] = $sponsor['email'] ?? ''; $input['source_address'] = $sponsor['address'] ?? '';
        }
    } elseif ($input['source_name'] === '') $errors[] = 'اسم مصدر الأموال مطلوب.';
    if ($input['amount'] === '' || round((float)str_replace(',','',$input['amount']),2) <= 0) $errors[] = 'المبلغ يجب أن يكون أكبر من صفر.';
    if (!$currencies || !in_array($input['currency_code'], array_column($currencies,'code'), true)) $errors[] = 'العملة المحددة غير صالحة.';
    if (!in_array($input['method'], ['cash','bank_transfer','credit_card','mobile','other'], true)) $errors[] = 'طريقة الدفع غير صالحة.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date'])) $errors[] = 'تاريخ التحصيل غير صالح.';
    if ($input['source_email'] !== '' && !filter_var($input['source_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني لمصدر الأموال غير صالح.';
    $amount = round((float)str_replace(',','',$input['amount']),2); $receiptPath = null;
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['receipt_file']; $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) $errors[] = 'صيغة الإيصال يجب أن تكون JPG أو PNG أو PDF.';
        elseif ($file['size'] > 10*1024*1024) $errors[] = 'حجم الإيصال يتجاوز 10 ميجابايت.';
        else { $dir = dirname(__DIR__,2).'/storage/receipts'; if (!is_dir($dir)) @mkdir($dir,0777,true); $fn='FINA-EXT-'.date('YmdHis').'-'.bin2hex(random_bytes(3)).'.'.$ext; if (move_uploaded_file($file['tmp_name'],$dir.'/'.$fn)) $receiptPath='storage/receipts/'.$fn; else $errors[]='فشل حفظ الإيصال على الخادم.'; }
    } elseif (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] !== UPLOAD_ERR_NO_FILE) $errors[] = 'حدث خطأ أثناء رفع الإيصال.';
    if (!$errors) {
        try {
            db()->beginTransaction(); $sourceId=0;
            if ($input['source_type']==='sponsor') $sourceId=(int)(dbFetchOne("SELECT id FROM fina_sources WHERE source_type='sponsor' AND sponsor_id=? LIMIT 1",[(int)$input['sponsor_id']])['id'] ?? 0);
            if ($sourceId<=0) { $isSponsor=$input['source_type']==='sponsor'; dbExecute("INSERT INTO fina_sources (source_type,sponsor_id,source_name,source_phone,source_alt_phone,source_email,source_address,source_id_number,source_reference,contact_person,source_details,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",[$input['source_type'],$isSponsor?(int)$input['sponsor_id']:null,$isSponsor?null:($input['source_name']?:null),$isSponsor?null:($input['source_phone']?:null),$isSponsor?null:($input['source_alt_phone']?:null),$isSponsor?null:($input['source_email']?:null),$isSponsor?null:($input['source_address']?:null),$isSponsor?null:($input['source_id_number']?:null),$isSponsor?null:($input['source_reference']?:null),$isSponsor?null:($input['contact_person']?:null),$isSponsor?null:($input['source_details']?:null),$uid]); $sourceId=(int)dbLastInsertId(); }
            if ($sourceId<=0) throw new RuntimeException('تعذر إنشاء مصدر فينا الخير.');
            dbExecute("INSERT INTO fina_collections (fina_source_id,amount,currency_code,payment_method,collection_date,receipt_path,purpose_note,description,status,created_by) VALUES (?,?,?,?,?,?,?,?,'pending',?)",[$sourceId,$amount,$input['currency_code'],$input['method'],$input['date'],$receiptPath,$input['purpose_note']?:null,$input['description']?:null,$uid]);
            $collectionId = (int)dbLastInsertId();
            if ($collectionId<=0) throw new RuntimeException('تعذر إنشاء سجل التحصيل.');
            db()->commit();

            // Fina collections use the existing global FM notification workflow.
            // This is notification-only: it does not create or alter any sponsor accounting transaction.
            ak_transaction_review_notify_fm_event(
                $collectionId,
                'fina_collection',
                'تحصيل فينا الخير بانتظار المراجعة',
                'يوجد تحصيل جديد لصالح فينا الخير بانتظار مراجعة واعتماد المدير المالي.',
                APP_URL . 'modules/accounting/fina_payment_review.php',
                $uid
            );

            flash('success','تم تسجيل تحصيل فينا الخير وإرساله للمدير المالي للمراجعة.'); header('Location: '.APP_URL.'modules/accounting/fina_payment_review.php'); exit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); if ($receiptPath && is_file(dirname(__DIR__,2).'/'.$receiptPath)) @unlink(dirname(__DIR__,2).'/'.$receiptPath); $errors[]='تعذر حفظ تحصيل فينا الخير. لم يتم حفظ أي جزء من العملية.'; }
    }
}

include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in"><h2><i class="fas fa-hand-holding-dollar me-2"></i>تسجيل تحصيل لصالح فينا الخير</h2><p>تحصيل مستقل مملوك بنسبة 100% لفينا الخير. يمكن أن يكون المصدر كفيلاً من النظام أو مصدراً خارجياً.</p></div>
<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card fade-in"><div class="card-header text-white" style="background:#1b4d8f">بيانات مصدر الأموال والتحصيل</div><div class="card-body"><form method="post" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<div class="row g-3">
<div class="col-md-4"><label class="form-label">مصدر الأموال *</label><select name="source_type" id="finaSourceType" class="form-select" required><option value="sponsor" <?php echo $input['source_type']==='sponsor'?'selected':''; ?>>كفيل من أهل الخير</option><option value="person" <?php echo $input['source_type']==='person'?'selected':''; ?>>شخص خارجي</option><option value="organization" <?php echo $input['source_type']==='organization'?'selected':''; ?>>منظمة / جهة</option><option value="other" <?php echo $input['source_type']==='other'?'selected':''; ?>>أخرى</option></select></div>
<div class="col-md-8" id="finaSponsorPicker"><label class="form-label">اختيار الكفيل *</label><div class="position-relative"><input type="text" id="finaSponsorSearch" class="form-control" autocomplete="off" placeholder="اكتب الاسم للبحث" value=""><input type="hidden" name="sponsor_id" id="finaSponsorId" value="<?php echo e($input['sponsor_id']); ?>"><div id="finaSponsorResults" class="list-group position-absolute w-100 shadow-sm" style="z-index:1050;max-height:280px;overflow-y:auto;display:none"></div></div><div id="finaSponsorSelected" class="form-text"></div></div>
<div class="col-md-8"><label class="form-label">اسم مصدر الأموال *</label><input type="text" name="source_name" id="finaSourceName" class="form-control" value="<?php echo e($input['source_name']); ?>"></div>
<div class="col-md-4"><label class="form-label">الهاتف</label><input type="tel" name="source_phone" id="finaSourcePhone" class="form-control" value="<?php echo e($input['source_phone']); ?>"></div>
<div class="col-md-4"><label class="form-label">هاتف إضافي</label><input type="tel" name="source_alt_phone" id="finaSourceAltPhone" class="form-control" value="<?php echo e($input['source_alt_phone']); ?>"></div>
<div class="col-md-4"><label class="form-label">البريد الإلكتروني</label><input type="email" name="source_email" id="finaSourceEmail" class="form-control" value="<?php echo e($input['source_email']); ?>"></div>
<div class="col-md-6"><label class="form-label">العنوان / موقع المصدر</label><input type="text" name="source_address" id="finaSourceAddress" class="form-control" value="<?php echo e($input['source_address']); ?>"></div>
<div class="col-md-3"><label class="form-label">رقم الهوية / السجل</label><input type="text" name="source_id_number" class="form-control" value="<?php echo e($input['source_id_number']); ?>"></div>
<div class="col-md-3"><label class="form-label">رقم مرجعي للمصدر</label><input type="text" name="source_reference" class="form-control" value="<?php echo e($input['source_reference']); ?>"></div>
<div class="col-md-4"><label class="form-label">اسم جهة الاتصال</label><input type="text" name="contact_person" class="form-control" value="<?php echo e($input['contact_person']); ?>"></div>
<div class="col-md-8"><label class="form-label">تفاصيل إضافية عن المصدر / الجهة</label><input type="text" name="source_details" class="form-control" value="<?php echo e($input['source_details']); ?>"></div>
<div class="col-md-4"><label class="form-label">المبلغ *</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="<?php echo e($input['amount']); ?>"></div>
<div class="col-md-4"><label class="form-label">العملة *</label><select name="currency_code" class="form-select" required><?php foreach ($currencies as $c): ?><option value="<?php echo e($c['code']); ?>" <?php echo $input['currency_code']===$c['code']?'selected':''; ?>><?php echo e($c['code']); ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">طريقة الدفع *</label><select name="method" class="form-select"><option value="cash">نقدي / كاش</option><option value="bank_transfer">تحويل بنكي</option><option value="mobile">محفظة إلكترونية</option><option value="credit_card">بطاقة</option><option value="other">أخرى</option></select></div>
<div class="col-md-4"><label class="form-label">تاريخ التحصيل *</label><input type="date" name="date" class="form-control" required value="<?php echo e($input['date']); ?>"></div>
<div class="col-md-8"><label class="form-label">إيصال التحصيل</label><input type="file" name="receipt_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
<div class="col-12"><label class="form-label">الغرض / التخصيص</label><input type="text" name="purpose_note" class="form-control" value="<?php echo e($input['purpose_note']); ?>"></div>
<div class="col-12"><label class="form-label">ملاحظات</label><textarea name="description" class="form-control" rows="2"><?php echo e($input['description']); ?></textarea></div>
</div>
<div class="mt-4 d-flex gap-2"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> تسجيل التحصيل</button><a href="<?php echo APP_URL; ?>modules/accounting/fina_payment_review.php" class="btn btn-secondary">إلغاء</a></div>
</form></div></div>
<script>
(function(){
    'use strict';
    const type=document.getElementById('finaSourceType'), picker=document.getElementById('finaSponsorPicker'), search=document.getElementById('finaSponsorSearch'), hidden=document.getElementById('finaSponsorId'), results=document.getElementById('finaSponsorResults'), selected=document.getElementById('finaSponsorSelected');
    const fields={name:'finaSourceName',phone:'finaSourcePhone',altPhone:'finaSourceAltPhone',email:'finaSourceEmail',address:'finaSourceAddress'};
    let timer=null, serial=0, controller=null;
    function setReadonly(flag){ Object.values(fields).forEach(id=>{const el=document.getElementById(id); if(el){el.readOnly=flag; el.classList.toggle('bg-light',flag);}}); }
    function clearFields(){ Object.values(fields).forEach(id=>{const el=document.getElementById(id); if(el) el.value='';}); hidden.value=''; selected.textContent=''; selected.className='form-text'; }
    function selectSponsor(s){
        hidden.value=String(s.id||'');
        search.value=String(s.full_name||'');
        document.getElementById('finaSourceName').value=s.full_name==null?'':String(s.full_name);
        document.getElementById('finaSourcePhone').value=s.phone==null?'':String(s.phone);
        document.getElementById('finaSourceAltPhone').value=s.alt_phone==null?'':String(s.alt_phone);
        document.getElementById('finaSourceEmail').value=s.email==null?'':String(s.email);
        document.getElementById('finaSourceAddress').value=s.address==null?'':String(s.address);
        setReadonly(true); selected.textContent='تم اختيار الكفيل: '+String(s.full_name||''); selected.className='form-text text-success'; results.style.display='none';
    }
    function render(rows){
        results.innerHTML='';
        if(!rows.length){results.innerHTML='<div class="list-group-item text-muted">لا توجد نتائج مطابقة للاسم</div>'; results.style.display='block'; return;}
        rows.forEach(s=>{const b=document.createElement('button'); b.type='button'; b.className='list-group-item list-group-item-action text-start'; const strong=document.createElement('strong'); strong.textContent=s.full_name||''; const small=document.createElement('small'); small.textContent=(s.sponsor_code||'')+(s.phone?' — '+s.phone:''); b.appendChild(strong); b.appendChild(document.createElement('br')); b.appendChild(small); b.addEventListener('click',()=>selectSponsor(s)); results.appendChild(b);});
        results.style.display='block';
    }
    async function searchSponsors(raw){
        const q=raw.trim(); const my=++serial;
        if(controller) controller.abort(); controller=new AbortController();
        if(!q){results.style.display='none'; return;}
        const url=new URL(window.location.href); url.search=''; url.searchParams.set('fina_sponsor_search',q);
        try{const r=await fetch(url.toString(),{headers:{Accept:'application/json'},cache:'no-store',signal:controller.signal}); if(!r.ok) throw new Error('HTTP '+r.status); const data=await r.json(); if(my===serial) render(Array.isArray(data)?data:[]);}catch(e){if(e.name==='AbortError'||my!==serial)return; results.innerHTML='<div class="list-group-item text-danger">تعذر تحميل نتائج الكفلاء</div>'; results.style.display='block';}
    }
    function mode(){const sponsor=type.value==='sponsor'; picker.style.display=sponsor?'block':'none'; setReadonly(sponsor); if(!sponsor) results.style.display='none'; }
    type.addEventListener('change',()=>{clearFields(); mode();});
    search.addEventListener('input',()=>{hidden.value=''; selected.textContent=''; selected.className='form-text'; setReadonly(true); clearTimeout(timer); timer=setTimeout(()=>searchSponsors(search.value),120);});
    search.addEventListener('focus',()=>{if(type.value==='sponsor'&&search.value.trim()) searchSponsors(search.value);});
    document.addEventListener('click',e=>{if(!picker.contains(e.target))results.style.display='none';});
    mode();
})();
</script>