<?php
// modules/families/create.php - Create family with mandatory first orphan
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle = 'إضافة أسرة'; $active = 'families'; $uid = Session::getUserId();
try {
    dbExecute("CREATE TABLE IF NOT EXISTS family_bank_accounts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,family_id INT UNSIGNED NOT NULL,bank_name VARCHAR(100) NOT NULL,bank_branch VARCHAR(100) NULL,account_number VARCHAR(50) NOT NULL,account_holder VARCHAR(150) NULL,is_primary TINYINT(1) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dbExecute("ALTER TABLE families ADD COLUMN IF NOT EXISTS father_name VARCHAR(150) NULL");
    dbExecute("ALTER TABLE families ADD COLUMN IF NOT EXISTS father_death_date DATE NULL");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS national_id VARCHAR(20) NULL");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS nationality VARCHAR(50) NOT NULL DEFAULT 'سودانية'");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS monthly_sponsorship_value DECIMAL(12,2) NULL");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS extra_allowance DECIMAL(12,2) NULL");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS form_serial VARCHAR(30) NULL");
} catch (Throwable $e) { error_log('Families schema sync: '.$e->getMessage()); }
$statuses=['pending'=>'قيد الانتظار','active'=>'نشطة','paused'=>'متوقفة','completed'=>'مكتملة','archived'=>'مؤرشفة','inactive'=>'غير نشطة','closed'=>'مغلقة'];
$supervisors=in_array($role,['admin','vice_general_manager'],true)?dbFetchAll("SELECT u.id,u.full_name FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='supervisor' ORDER BY u.full_name"):[];
$nannies=in_array($role,['admin','vice_general_manager'],true)?dbFetchAll("SELECT u.id,u.full_name FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='nanny' AND u.is_active=1 ORDER BY u.full_name"):[];
$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!verify_csrf()) $errors[]='انتهت صلاحية الجلسة.';
    $name=trim($_POST['mother_name']??''); $childName=trim($_POST['child_name']??'');
    if ($name==='') $errors[]='اسم الأم مطلوب.';
    if ($childName==='') $errors[]='يجب تسجيل يتيم واحد على الأقل مع الأسرة. اسم اليتيم مطلوب.';
    if (!$errors) {
        $n=(int)(dbFetchOne("SELECT COUNT(*) c FROM families")['c']??0)+1;
        do { $code='FAM-'.str_pad((string)$n,6,'0',STR_PAD_LEFT); $exists=dbFetchOne("SELECT id FROM families WHERE family_code=?",[$code]); if($exists)$n++; } while($exists);
        [$rawLetter,$normLetter]=first_letter_of($name);
        $supId=$role==='supervisor'?$uid:(isset($_POST['supervisor_id'])&&$_POST['supervisor_id']!==''?(int)$_POST['supervisor_id']:null);
        $nannyId=($role!=='supervisor'&&isset($_POST['nanny_id'])&&$_POST['nanny_id']!=='')?(int)$_POST['nanny_id']:null;
        try {
            db()->beginTransaction();
            $joiningDate = trim($_POST['registration_date'] ?? '') ?: date('Y-m-d');
            dbExecute("INSERT INTO families (mother_name,mother_phone,mother_alt_phone,mother_job,mother_workplace,mother_national_id,registration_date,father_name,father_death_date,city,district,address,monthly_need_amount,status,notes,supervisor_id,nanny_id,family_code,legacy_mother_first_letter,created_by,assigned_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$name,trim($_POST['mother_phone']??'')?:null,trim($_POST['mother_alt_phone']??'')?:null,trim($_POST['mother_job']??'')?:null,trim($_POST['mother_workplace']??'')?:null,trim($_POST['mother_national_id']??'')?:null,$joiningDate,trim($_POST['father_name']??'')?:null,trim($_POST['father_death_date']??'')?:null,trim($_POST['city']??'')?:null,trim($_POST['district']??'')?:null,trim($_POST['address']??'')?:null,trim($_POST['monthly_need_amount']??'')!==''?(float)str_replace(',','',$_POST['monthly_need_amount']):null,array_key_exists($_POST['status']??'',$statuses)?$_POST['status']:'pending',trim($_POST['notes']??'')?:null,$supId,$nannyId,$code,$rawLetter,$uid,$supId]);
            $newId=(int)db()->lastInsertId();
            $gender=in_array($_POST['gender']??'', ['male','female','unknown'],true)?$_POST['gender']:'unknown';
            $mx=dbFetchOne("SELECT MAX(CAST(SUBSTRING(form_serial,4) AS UNSIGNED)) m FROM family_children WHERE form_serial LIKE 'AK-%'");
            $formSerial='AK-'.str_pad((string)(((int)($mx['m']??0))+1),5,'0',STR_PAD_LEFT);
            dbExecute("INSERT INTO family_children (family_id,child_name,national_id,birth_date,gender,education_level,has_medical_needs,medical_need_id,medical_notes,nationality,monthly_sponsorship_value,extra_allowance,form_serial,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)",
            [$newId,$childName,trim($_POST['child_national_id']??'')?:null,trim($_POST['child_birth_date']??'')?:null,$gender,trim($_POST['child_education_level']??'')?:null,0,null,trim($_POST['child_medical_notes']??'')?:null,trim($_POST['child_nationality']??'')!==''?trim($_POST['child_nationality']):'سودانية',trim($_POST['child_monthly_sponsorship_value']??'')!==''?(float)str_replace(',','',$_POST['child_monthly_sponsorship_value']):null,trim($_POST['child_extra_allowance']??'')!==''?(float)str_replace(',','',$_POST['child_extra_allowance']):null,$formSerial]);
            dbExecute("UPDATE families SET children_count=1 WHERE id=?",[$newId]);
            $bnArr=$_POST['bank_name']??[];$brArr=$_POST['bank_branch']??[];$acArr=$_POST['account_number']??[];$ahArr=$_POST['account_holder']??[];$first=null;
            for($i=0;$i<count($bnArr);$i++){ $bn=trim((string)($bnArr[$i]??''));$ac=trim((string)($acArr[$i]??'')); if($bn!==''&&$ac!==''){ $isPrimary=$first===null?1:0; dbExecute("INSERT INTO family_bank_accounts (family_id,bank_name,bank_branch,account_number,account_holder,is_primary) VALUES (?,?,?,?,?,?)",[$newId,$bn,trim((string)($brArr[$i]??''))?:null,$ac,trim((string)($ahArr[$i]??''))?:null,$isPrimary]); if($first===null)$first=[$bn,$ac,trim((string)($brArr[$i]??''))?:null,trim((string)($ahArr[$i]??''))?:null]; }}
            if($first) dbExecute("UPDATE families SET bank_name=?,bank_account_number=?,bank_branch=?,bank_account_holder=? WHERE id=?",[$first[0],$first[1],$first[2],$first[3],$newId]);
            dbExecute("INSERT INTO audit_log (user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?, 'CREATE','families',?,NULL,?,?,?)",[$uid,$newId,json_encode(['mother_name'=>$name,'code'=>$code,'first_orphan'=>$childName],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'',$_SERVER['HTTP_USER_AGENT']??'']);
            db()->commit();
            flash('success','تمت إضافة الأسرة واليتيم الأول: '.$name.' ('.$code.')');
            header('Location: '.APP_URL.'modules/families/view.php?id='.$newId); exit();
        } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); error_log('Family/orphan creation failed: '.$e->getMessage()); $errors[]='تعذر حفظ الأسرة واليتيم. لم يتم إنشاء سجل جزئي. يرجى المحاولة مرة أخرى.'; }
    }
}
include dirname(__DIR__,2).'/includes/header.php'; ?>
<div class="welcome-section fade-in"><h2>إضافة أسرة جديدة</h2><p>يجب تسجيل يتيم واحد على الأقل مع كل أسرة.</p></div>
<?php if($errors): ?><div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach($errors as $er): ?><li><?php echo e($er); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card fade-in"><div class="card-body"><form method="post"><?php echo csrf_field(); ?><div class="row g-3">
	<div class="col-md-4"><label class="form-label">تاريخ الإنضمام</label><input type="date" name="registration_date" class="form-control" value="<?php echo e($_POST['registration_date'] ?? date('Y-m-d')); ?>" required></div>
	<div class="col-md-6"><label class="form-label">اسم الأم *</label><input type="text" name="mother_name" class="form-control" value="<?php echo e($_POST['mother_name']??''); ?>" required></div>
<div class="col-md-3"><label class="form-label">الهاتف</label><input type="text" name="mother_phone" class="form-control" dir="ltr"></div><div class="col-md-3"><label class="form-label">هاتف بديل</label><input type="text" name="mother_alt_phone" class="form-control" dir="ltr"></div>
<div class="col-md-4"><label class="form-label">الرقم الوطني (الأم)</label><input type="text" name="mother_national_id" class="form-control" dir="ltr"></div><div class="col-md-4"><label class="form-label">اسم الأب (والد الأطفال)</label><input type="text" name="father_name" class="form-control"></div><div class="col-md-4"><label class="form-label">تاريخ وفاة الأب</label><input type="date" name="father_death_date" class="form-control"></div>
<div class="col-md-4"><label class="form-label">المهنة</label><input type="text" name="mother_job" class="form-control"></div><div class="col-md-4"><label class="form-label">مكان العمل</label><input type="text" name="mother_workplace" class="form-control"></div>
<div class="col-md-3"><label class="form-label">المدينة</label><input type="text" name="city" class="form-control"></div><div class="col-md-3"><label class="form-label">الحي/المنطقة</label><input type="text" name="district" class="form-control"></div><div class="col-md-3"><label class="form-label">الاحتياج الشهري</label><input type="text" name="monthly_need_amount" class="form-control"></div><div class="col-md-9"><label class="form-label">العنوان</label><input type="text" name="address" class="form-control"></div><div class="col-md-3"><label class="form-label">الحالة</label><select name="status" class="form-select"><?php foreach($statuses as $k=>$label): ?><option value="<?php echo $k; ?>"><?php echo $label; ?></option><?php endforeach; ?></select></div>
<?php if($supervisors): ?><div class="col-md-4"><label class="form-label">المشرف المسؤول</label><select name="supervisor_id" class="form-select"><option value="">— غير معين —</option><?php foreach($supervisors as $s): ?><option value="<?php echo (int)$s['id']; ?>"><?php echo e($s['full_name']); ?></option><?php endforeach; ?></select></div><?php endif; ?>
<?php if($nannies): ?><div class="col-md-4"><label class="form-label">أخصائية شؤون الأمهات</label><select name="nanny_id" class="form-select"><option value="">— غير معين —</option><?php foreach($nannies as $nn): ?><option value="<?php echo (int)$nn['id']; ?>"><?php echo e($nn['full_name']); ?></option><?php endforeach; ?></select></div><?php endif; ?>
<div class="col-12"><hr><h5 class="text-primary"><i class="fas fa-child me-2"></i>بيانات اليتيم الأول <span class="text-danger">*</span></h5><div class="alert alert-info py-2">الأسرة لا يمكن إنشاؤها بدون يتيم واحد على الأقل. يمكنك إضافة بقية الأيتام من ملف الأسرة بعد الحفظ.</div></div>
<div class="col-md-6"><label class="form-label">اسم اليتيم *</label><input type="text" name="child_name" class="form-control" required></div><div class="col-md-3"><label class="form-label">تاريخ الميلاد</label><input type="date" name="child_birth_date" class="form-control"></div><div class="col-md-3"><label class="form-label">الجنس</label><select name="gender" class="form-select"><option value="unknown">غير معروف</option><option value="male">ذكر</option><option value="female">أنثى</option></select></div>
<div class="col-md-4"><label class="form-label">الرقم الوطني</label><input type="text" name="child_national_id" class="form-control" dir="ltr"></div><div class="col-md-4"><label class="form-label">الجنسية</label><input type="text" name="child_nationality" class="form-control" value="سودانية"></div><div class="col-md-4"><label class="form-label">المستوى التعليمي</label><input type="text" name="child_education_level" class="form-control"></div>
<div class="col-md-6"><label class="form-label">قيمة الكفالة الشهرية</label><input type="text" name="child_monthly_sponsorship_value" class="form-control"></div><div class="col-md-6"><label class="form-label">بدل إضافي</label><input type="text" name="child_extra_allowance" class="form-control"></div>
<div class="col-12"><label class="form-label">ملاحظات طبية</label><textarea name="child_medical_notes" class="form-control" rows="2"></textarea></div>
<div class="col-12 mt-3"><h6 class="text-primary"><i class="fas fa-building-columns me-2"></i>الحسابات البنكية (اختياري)</h6><div id="bankRows"><div class="row g-2 bank-row mb-2"><div class="col-md-3"><input type="text" name="bank_name[]" class="form-control" placeholder="اسم البنك"></div><div class="col-md-2"><input type="text" name="bank_branch[]" class="form-control" placeholder="الفرع"></div><div class="col-md-3"><input type="text" name="account_number[]" class="form-control" placeholder="رقم الحساب" dir="ltr"></div><div class="col-md-3"><input type="text" name="account_holder[]" class="form-control" placeholder="اسم صاحب الحساب"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-bank w-100"><i class="fas fa-times"></i></button></div></div></div><button type="button" id="addBankRow" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus me-1"></i>إضافة حساب آخر</button></div>
<div class="col-12"><label class="form-label">ملاحظات الأسرة</label><textarea name="notes" class="form-control" rows="2"></textarea></div><div class="col-12"><button class="btn btn-primary btn-lg px-5"><i class="fas fa-save me-1"></i>حفظ الأسرة واليتيم</button> <a href="<?php echo APP_URL; ?>modules/families/index.php" class="btn btn-secondary btn-lg">إلغاء</a></div>
</div></form></div></div>
<script>document.addEventListener('click',function(e){if(e.target.closest('#addBankRow')){var w=document.getElementById('bankRows'),r=w.querySelector('.bank-row').cloneNode(true);r.querySelectorAll('input').forEach(function(i){i.value='';});w.appendChild(r);}var rm=e.target.closest('.remove-bank');if(rm){var rows=document.querySelectorAll('#bankRows .bank-row');if(rows.length>1)rm.closest('.bank-row').remove();}});</script>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>