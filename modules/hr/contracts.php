<?php
declare(strict_types=1);
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../config/functions.php';
require_once __DIR__.'/../../config/session.php';
require_once __DIR__.'/lib_employment.php';
require_once __DIR__.'/lib_contract_salary.php';
Session::start();
$pdo=db();
if(!Session::isLoggedIn()||!in_array(Session::getUserRole(),['hr_manager','hr_staff','admin'],true)){header('Location: '.APP_URL.'index.php');exit;}
$eid=(int)($_GET['employee_id']??$_POST['employee_id']??0);$view=(string)($_GET['view']??'contracts');$msg='';$err=false;
$types=['permanent'=>'دائم','fixed_term'=>'محدد المدة','part_time'=>'دوام جزئي','temporary'=>'مؤقت','internship'=>'تدريب','other'=>'أخرى'];
$freq=['monthly'=>'شهري','weekly'=>'أسبوعي','daily'=>'يومي','hourly'=>'بالساعة'];
$reasons=['initial'=>'راتب ابتدائي','annual_increase'=>'زيادة سنوية','promotion'=>'ترقية','adjustment'=>'تعديل','contract_change'=>'تغيير عقد','correction'=>'تصحيح','other'=>'أخرى'];
$statuses=['draft'=>['مسودة','muted'],'active'=>['ساري','success'],'expired'=>['منتهي','warning'],'terminated'=>['منهى','danger'],'cancelled'=>['ملغى','muted']];
function contractsSyncEmploymentState(array $c,int $userId):void{
 $today=date('Y-m-d');if(($c['start_date']??'')>$today)return;
 $code=(!empty($c['probation_end_date'])&&$c['probation_end_date']>=$today)?'probation':'active';$state=hrGetEmploymentStateByCode($code);
 if($state)hrSetEmploymentState((int)$c['employee_id'],(int)$state['id'],$userId,'تفعيل عقد العمل',$today.' 00:00:00');
}
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';$eid=(int)($_POST['employee_id']??$eid);
  if($action==='upload_signed_copy'){
   $cid=(int)($_POST['contract_id']??0);
   $contract=hrGetEmployeeContract($cid);
   if(!$contract||(int)$contract['employee_id']!==$eid)throw new RuntimeException('العقد المحدد غير موجود لهذا الموظف.');
   if(!isset($_FILES['contract_file'])||$_FILES['contract_file']['error']===UPLOAD_ERR_NO_FILE)throw new InvalidArgumentException('اختر ملف العقد الموقع أولاً.');
   $f=$_FILES['contract_file'];
   if($f['error']!==UPLOAD_ERR_OK)throw new RuntimeException('تعذر رفع ملف العقد.');
   if($f['size']>10*1024*1024)throw new RuntimeException('حجم الملف يتجاوز 10 ميجابايت.');
   $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
   if(!in_array($ext,['pdf','jpg','jpeg','png'],true))throw new RuntimeException('صيغة الملف غير مدعومة.');
   $dir=__DIR__.'/../../storage/hr_contracts/';
   if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('تعذر إنشاء مجلد ملفات العقود.');
   $name='contract_'.$eid.'_'.$cid.'_'.bin2hex(random_bytes(6)).'.'.$ext;
   $absolutePath=$dir.$name;
   if(!move_uploaded_file($f['tmp_name'],$absolutePath))throw new RuntimeException('تعذر حفظ ملف العقد.');
   $relativePath='storage/hr_contracts/'.$name;
   try{$pdo->prepare('UPDATE hr_employee_contracts SET contract_file_path=? WHERE id=? AND employee_id=?')->execute([$relativePath,$cid,$eid]);}
   catch(Throwable $uploadError){if(is_file($absolutePath))@unlink($absolutePath);throw $uploadError;}
   $msg='تم حفظ نسخة العقد الموقعة وربطها بسجل العقد.';
   $view='contracts';
  }elseif($action==='change_salary'){
   $salary=(float)($_POST['basic_salary']??0);$effective=$_POST['effective_from']??'';$reason=$_POST['reason']??'adjustment';$notes=trim($_POST['salary_notes']??'')?:null;$cid=(int)($_POST['contract_id']??0)?:null;$frequency=$_POST['pay_frequency']??'monthly';hrChangeEmployeeSalary($eid,$salary,$effective,$reason,Session::getUserID(),$notes,$cid,APP_CURRENCY_CODE,$frequency);$msg='تم حفظ تغيير الراتب في السجل التاريخي.';$view='salary';
  }elseif($action==='contract_status'){
   $cid=(int)($_POST['contract_id']??0);$new=$_POST['status']??'';$c=hrGetEmployeeContract($cid);if(!$c)throw new RuntimeException('العقد غير موجود.');if(!in_array($new,['active','terminated','cancelled'],true))throw new InvalidArgumentException('حالة العقد غير صالحة.');
   if($new==='active'){$pdo->beginTransaction();try{$pdo->prepare("UPDATE hr_employee_contracts SET status='expired' WHERE employee_id=? AND id<>? AND status='active'")->execute([$c['employee_id'],$cid]);$pdo->prepare("UPDATE hr_employee_contracts SET status='active' WHERE id=?")->execute([$cid]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}if($c['start_date']<=date('Y-m-d')){$fresh=hrGetEmployeeContract($cid);hrChangeEmployeeSalary((int)$c['employee_id'],(float)$c['basic_salary'],$c['start_date'],'contract_change',Session::getUserID(),'تم تفعيل العقد',$cid,$c['salary_currency'],$c['pay_frequency']);if($fresh)contractsSyncEmploymentState($fresh,Session::getUserID());}$msg='تم تفعيل العقد وتحديث حالة التوظيف وفق تاريخ السريان.';}
   elseif($new==='terminated'){$pdo->prepare("UPDATE hr_employee_contracts SET status='terminated' WHERE id=?")->execute([$cid]);$state=hrGetEmploymentStateByCode('terminated');if($state)hrSetEmploymentState((int)$c['employee_id'],(int)$state['id'],Session::getUserID(),'إنهاء عقد العمل',date('Y-m-d H:i:s'));$msg='تم إنهاء العقد وتسجيل الموظف كمنهي الخدمة.';}
   else{$pdo->prepare("UPDATE hr_employee_contracts SET status='cancelled' WHERE id=?")->execute([$cid]);$msg='تم إلغاء العقد دون تغيير حالة التوظيف؛ الإلغاء ليس إنهاء خدمة.';}
  }
 }
}catch(Throwable $e){$msg=$e->getMessage();$err=true;}
$employees=dbFetchAll("SELECT e.id,e.employee_code,e.full_name,e.basic_salary,d.name_ar dept_name,s.name_ar state_name FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN hr_employment_states s ON s.id=e.employment_state_id ORDER BY e.full_name",[]);
$emp=null;$contracts=[];$history=[];if($eid){$emp=dbFetchOne("SELECT e.*,d.name_ar dept_name,s.name_ar state_name FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN hr_employment_states s ON s.id=e.employment_state_id WHERE e.id=?",[$eid]);if($emp){$contracts=dbFetchAll("SELECT * FROM hr_employee_contracts WHERE employee_id=? ORDER BY start_date DESC,id DESC",[$eid]);$history=hrGetEmployeeSalaryHistory($eid);}}
$counts=dbFetchOne("SELECT COUNT(*) total,SUM(status='active') active_count,SUM(status='draft') draft_count,SUM(status='terminated') terminated_count FROM hr_employee_contracts",[]);
$expiring=dbFetchAll("SELECT c.employee_id,c.end_date,e.full_name FROM hr_employee_contracts c JOIN employees e ON e.id=c.employee_id WHERE c.status='active' AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) ORDER BY c.end_date LIMIT 6",[]);
$pageTitle='العقود والرواتب';require_once __DIR__.'/../../includes/header.php';
?>
<style>
.hr-c{max-width:1500px;margin:auto}.hr-c .hero{background:linear-gradient(135deg,#173f73,#2d67ad);color:#fff;border-radius:16px;padding:24px 28px;margin-bottom:18px}.hr-c .hero h1{margin:0;font-size:1.5rem;font-weight:800}.hr-c .hero p{margin:7px 0 0;opacity:.9;font-size:.84rem}.hr-c .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}.hr-c .card{background:#fff;border:1px solid #e5eaf0;border-radius:14px;box-shadow:0 3px 14px rgba(16,24,40,.05);overflow:hidden}.hr-c .stat{padding:15px 17px}.hr-c .stat small{display:block;color:#667085;font-size:.7rem;font-weight:700}.hr-c .stat strong{display:block;color:#173f73;font-size:1.35rem;margin-top:3px}.hr-c .head{padding:14px 16px;border-bottom:1px solid #edf0f3;display:flex;justify-content:space-between;align-items:center}.hr-c .head h2{margin:0;color:#173f73;font-size:.92rem;font-weight:800}.hr-c .body{padding:16px}.hr-c input,.hr-c select,.hr-c textarea{width:100%;border:1px solid #d5dce5;border-radius:9px;padding:9px 10px;font-size:.77rem;background:#fff}.hr-c .search{margin-bottom:9px;max-width:420px}.hr-c .elist{display:flex;flex-direction:column;gap:0;border:1px solid #e5eaf0;border-radius:11px;overflow:hidden}.hr-c .emp{display:grid;grid-template-columns:minmax(220px,2fr) minmax(160px,1.3fr) minmax(120px,1fr) minmax(140px,1fr);align-items:center;gap:12px;padding:12px 14px;border:0;border-bottom:1px solid #e5eaf0;border-radius:0;text-decoration:none;color:#263445;background:#fff;min-width:0;transition:background .15s,border-color .15s}.hr-c .emp:last-child{border-bottom:0}.hr-c .emp:hover{background:#f7f9fc}.hr-c .emp .name,.hr-c .emp .meta,.hr-c .emp .salary,.hr-c .emp .state{margin-top:0}.hr-c .emp[hidden],.hr-c #employeeSearchEmpty[hidden]{display:none!important}.hr-c .emp.active{background:#eef5ff;border-color:#9dbfe9;box-shadow:inset 0 0 0 1px #d5e5fa}.hr-c .name{font-size:.84rem;font-weight:800}.hr-c .meta,.hr-c .state{font-size:.69rem;color:#7a8695;margin-top:4px}.hr-c .salary{font-size:.76rem;color:#173f73;font-weight:800;margin-top:7px}.hr-c .tabs{display:flex;border-bottom:1px solid #e8edf2;padding:0 17px;overflow:auto;background:#fff}.hr-c .tab{padding:12px 14px;color:#667085;text-decoration:none;font-size:.76rem;font-weight:800;border-bottom:3px solid transparent;white-space:nowrap;flex:0 0 auto}.hr-c .tab.active{color:#173f73;border-color:#2d67ad}.hr-c .toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:14px}.hr-c .title{font-size:.98rem;color:#173f73;font-weight:800}.hr-c .btn{display:inline-flex;align-items:center;gap:5px;border-radius:8px;padding:8px 12px;font-size:.72rem;font-weight:800;text-decoration:none;border:1px solid transparent;cursor:pointer}.hr-c .primary{background:#173f73;color:#fff}.hr-c .light{background:#f5f7fa;color:#294a70;border-color:#dfe5ec}.hr-c .danger{background:#fff0ef;color:#b42318;border-color:#ffd0cb}.hr-c .success{background:#edf9f0;color:#187a35;border-color:#c9ecd2}.hr-c .table{width:100%;border-collapse:collapse}.hr-c .table th{background:#f7f9fb;color:#526071;font-size:.68rem;padding:10px;text-align:right;white-space:nowrap}.hr-c .table td{padding:10px 8px;border-bottom:1px solid #edf0f3;font-size:.74rem;vertical-align:middle}.hr-c .badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:.64rem;font-weight:800}.hr-c .badge.success{background:#e9f8ee;color:#187a35}.hr-c .badge.warning{background:#fff5d9;color:#946200}.hr-c .badge.danger{background:#fff0ef;color:#b42318}.hr-c .badge.muted{background:#eef1f5;color:#667085}.hr-c .current{background:#f5f9ff;border:1px solid #dce9f8;border-radius:11px;padding:14px;margin-bottom:15px}.hr-c .current small{color:#667085;font-size:.68rem}.hr-c .current strong{display:block;color:#173f73;font-size:1.35rem;margin:2px 0}.hr-c .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.hr-c .full{grid-column:1/-1}.hr-c .label{display:block;font-size:.71rem;font-weight:800;color:#475467;margin-bottom:5px}.hr-c .note,.hr-c .info{font-size:.66rem;color:#667085;margin-top:4px}.hr-c .info{background:#f8fafc;border:1px solid #e7ebf0;border-radius:10px;padding:10px;margin-top:12px}.hr-c .empty{text-align:center;padding:42px 15px;color:#8994a2;font-size:.78rem}.hr-c .side{margin-top:16px}.hr-c .side-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #edf0f3;font-size:.68rem}.hr-c .side-row a{color:#344054;text-decoration:none;font-weight:700}.hr-c .side-row span{color:#b54708;font-weight:800}.hr-c .form-actions{margin-top:15px;text-align:left}.hr-c .modal-dialog{max-width:min(96vw,1500px)}.hr-c .modal-content{border:0;border-radius:16px;box-shadow:0 18px 60px rgba(16,24,40,.22);overflow:hidden}.hr-c .modal-header{background:#f8fafc;border-bottom:1px solid #e5eaf0;padding:16px 22px}.hr-c .modal-title{color:#173f73;font-weight:800;font-size:1rem}.hr-c .modal-employee-meta{color:#667085;font-size:.72rem;margin-top:4px}.hr-c .modal-body{padding:0;min-height:180px}.hr-c #employeeDetailsModal .modal-footer,.hr-c #employeeDetailsModal .app-footer,.hr-c #employeeDetailsModal .ak-print-footer{display:none!important}.hr-c .modal-alert{margin:14px 16px 0;font-size:.78rem;border-radius:9px}@media(max-width:767.98px){.hr-c .stats{grid-template-columns:repeat(2,minmax(0,1fr))}.hr-c .hero{padding:20px}.hr-c .emp{grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:7px 12px}.hr-c .emp .name{grid-column:1/-1}.hr-c .grid{grid-template-columns:1fr}.hr-c .full{grid-column:auto}.hr-c .modal-dialog{max-width:100%;margin:.5rem}.hr-c .modal-header{padding:14px}.hr-c .tabs{padding:0 7px}.hr-c .tab{padding:11px 10px}.hr-c .toolbar{align-items:flex-start;flex-direction:column}}
</style>
<div class="hr-c"><div class="hero"><h1><i class="fas fa-file-signature me-2"></i>العقود والرواتب</h1><p>سجل العقود والمسودات ونسخ العقود الموقعة وسجل الرواتب التاريخي. لإنشاء عقد جديد ابدأ من بوابة إعداد العقد.</p><a href="<?= e(APP_URL) ?>modules/hr/contract_builder.php<?= $eid ? '?employee_id=' . $eid : '' ?>" class="btn btn-light btn-sm mt-3"><i class="fas fa-file-pdf me-1"></i> إعداد عقد جديد عبر البوابة</a></div>
<div class="stats"><div class="card stat"><small>إجمالي العقود</small><strong><?= (int)$counts['total'] ?></strong></div><div class="card stat"><small>العقود السارية</small><strong><?= (int)$counts['active_count'] ?></strong></div><div class="card stat"><small>العقود المسودة</small><strong><?= (int)$counts['draft_count'] ?></strong></div><div class="card stat"><small>العقود المنتهية</small><strong><?= (int)$counts['terminated_count'] ?></strong></div></div>
<?php if($msg && !$emp): ?><div class="alert alert-<?= $err?'danger':'success' ?> alert-dismissible fade show" role="alert" style="font-size:.78rem;border-radius:9px"><?= e($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button></div><?php endif; ?>

<section class="card employee-picker">
  <div class="head"><h2><i class="fas fa-users me-2"></i>اختيار الموظف</h2><span class="badge muted"><?= count($employees) ?> موظف</span></div>
  <div class="body">
    <input id="es" class="search" placeholder="ابحث بالاسم أو الكود..." aria-label="ابحث بالاسم أو الكود">
    <div id="elist" class="elist">
      <?php foreach($employees as $x): ?>
      <a class="emp <?= $eid===(int)$x['id']?'active':'' ?>" data-q="<?= e($x['full_name'].' '.$x['employee_code'].' '.($x['dept_name']??'')) ?>" href="?employee_id=<?= (int)$x['id'] ?>&view=contracts"<?= $eid===(int)$x['id']?' aria-current="true"':'' ?>>
        <div class="name"><?= e($x['full_name']) ?></div>
        <div class="meta"><?= e($x['employee_code']) ?> · <?= e($x['dept_name']??'بدون قسم') ?></div>
        <div class="salary"><?= number_format((float)$x['basic_salary'],2) ?> <?= e(APP_CURRENCY_CODE) ?></div>
        <div class="state">الحالة: <?= e($x['state_name']??'غير محددة') ?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <div id="employeeSearchEmpty" class="empty" hidden>لا توجد نتائج مطابقة للبحث.</div>
  </div>
</section>
<?php if($expiring): ?>
<section class="card side"><div class="head"><h2><i class="fas fa-hourglass-half me-2"></i>تنتهي خلال 30 يوماً</h2></div><div class="body">
  <?php foreach($expiring as $x): ?><div class="side-row"><a href="?employee_id=<?= (int)$x['employee_id'] ?>&view=contracts"><?= e($x['full_name']) ?></a><span><?= e($x['end_date']) ?></span></div><?php endforeach; ?>
</div></section>
<?php endif; ?>
<?php if($emp): ?>
<div class="modal fade" id="employeeDetailsModal" tabindex="-1" aria-labelledby="employeeDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><h2 class="modal-title" id="employeeDetailsModalLabel"><?= e($emp['full_name']) ?></h2><div class="modal-employee-meta"><?= e($emp['employee_code']) ?> · <?= e($emp['dept_name']??'بدون قسم') ?> · الحالة: <?= e($emp['state_name']??'غير محددة') ?></div></div><button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
    <div class="modal-body">
      <?php if($msg): ?><div class="alert alert-<?= $err?'danger':'success' ?> alert-dismissible fade show modal-alert" role="alert"><?= e($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button></div><?php endif; ?>
      <div class="tabs"><a class="tab <?= $view==='contracts'?'active':'' ?>" href="?employee_id=<?= $eid ?>&view=contracts">العقود</a><a class="tab <?= $view==='salary'?'active':'' ?>" href="?employee_id=<?= $eid ?>&view=salary">سجل الرواتب</a><a class="tab" href="<?= e(APP_URL) ?>modules/hr/contract_builder.php?employee_id=<?= $eid ?>">إعداد عقد جديد</a><a class="tab <?= $view==='salary_add'?'active':'' ?>" href="?employee_id=<?= $eid ?>&view=salary_add">تغيير الراتب</a></div>
      <div class="body">
<?php if($view==='contracts'):?><div class="toolbar"><div class="title"><?= e($emp['full_name']) ?> — سجل العقود (<?= count($contracts) ?>)</div><a class="btn primary" href="<?= e(APP_URL) ?>modules/hr/contract_builder.php?employee_id=<?= $eid ?>"><i class="fas fa-file-pdf"></i> إعداد عقد جديد</a></div><div class="table-responsive"><table class="table"><thead><tr><th>رقم العقد</th><th>النوع</th><th>الفترة</th><th>الراتب</th><th>الدورية</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody><?php if(!$contracts):?><tr><td colspan="7" class="empty">لا توجد عقود لهذا الموظف.</td></tr><?php else:foreach($contracts as $c):$st=$statuses[$c['status']]??['غير معروف','muted'];$days=$c['end_date']?(int)floor((strtotime($c['end_date'])-strtotime(date('Y-m-d')))/86400):null;?><tr><td><strong><?= e($c['contract_number']?:'—') ?></strong></td><td><?= e($types[$c['contract_type']]??$c['contract_type']) ?></td><td><?= e($c['start_date']) ?> → <?= e($c['end_date']?:'مفتوح') ?><?php if($days!==null&&$c['status']==='active'):?><div style="font-size:.63rem;color:<?= $days<=30?'#b54708':'#667085' ?>"><?= $days>=0?'متبقي '.$days.' يوم':'منتهي منذ '.abs($days).' يوم' ?></div><?php endif;?></td><td><strong><?= number_format((float)$c['basic_salary'],2) ?></strong> <?= e($c['salary_currency']?:APP_CURRENCY_CODE) ?></td><td><?= e($freq[$c['pay_frequency']]??$c['pay_frequency']) ?></td><td><span class="badge <?= e($st[1]) ?>"><?= e($st[0]) ?></span></td><td><?php if($c['status']==='draft'):?><form method="post" style="display:inline"><input type="hidden" name="action" value="contract_status"><input type="hidden" name="employee_id" value="<?= $eid ?>"><input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="status" value="active"><button class="btn success" type="submit">تفعيل</button></form><?php elseif($c['status']==='active'):?><form method="post" style="display:inline" onsubmit="return confirm('هل تريد إنهاء هذا العقد؟ سيتم تسجيل حالة الموظف كمنهي الخدمة.')"><input type="hidden" name="action" value="contract_status"><input type="hidden" name="employee_id" value="<?= $eid ?>"><input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="status" value="terminated"><button class="btn danger" type="submit">إنهاء</button></form><?php endif;?><?php if($c['contract_file_path']):?><a class="btn light" target="_blank" href="<?= e(APP_URL.$c['contract_file_path']) ?>"><i class="fas fa-paperclip"></i> الملف</a><?php endif;?><form method="post" enctype="multipart/form-data" style="display:inline-flex;align-items:center;gap:4px;margin-top:5px"><input type="hidden" name="action" value="upload_signed_copy"><input type="hidden" name="employee_id" value="<?= $eid ?>"><input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>"><input type="file" name="contract_file" accept=".pdf,.jpg,.jpeg,.png" required style="max-width:150px;padding:4px;font-size:.62rem"><button class="btn light" type="submit" title="رفع أو استبدال نسخة العقد الموقعة"><i class="fas fa-upload"></i> <?= $c['contract_file_path']?'استبدال الملف':'رفع الموقّع' ?></button></form></td></tr><?php endforeach;endif;?></tbody></table></div><div class="info">إنشاء المسودة ورقم العقد يتمان حصراً عبر بوابة إعداد العقد. بعد توقيع النسخة، ارفع الملف من صف العقد هنا ثم فعّل المسودة عند استكمال الاعتماد الداخلي. إنشاء المسودة لا يغيّر الراتب أو حالة التوظيف.</div><div class="info"><strong>حالة التوظيف:</strong> <?= e($emp['state_name']?:'غير محددة') ?> — تفعيل عقد بدأ بالفعل يضبط «فترة تجربة» إذا كانت فترة التجربة مستمرة، وإلا «على رأس العمل». إنهاء العقد يسجل «منهي الخدمة».</div>
<?php elseif($view==='salary'):$cur=hrGetEmployeeSalary($eid);?><div class="toolbar"><div class="title">السجل التاريخي للراتب</div><a class="btn primary" href="?employee_id=<?= $eid ?>&view=salary_add"><i class="fas fa-plus"></i> تغيير الراتب</a></div><div class="current"><small>الراتب الساري من السجل التاريخي</small><strong><?= $cur?number_format((float)$cur['basic_salary'],2):'0.00' ?> <?= e($cur['salary_currency']??APP_CURRENCY_CODE) ?></strong><small><?= e($freq[$cur['pay_frequency']??'monthly']??'شهري') ?> · من <?= e($cur['effective_from']??'—') ?></small></div><div class="table-responsive"><table class="table"><thead><tr><th>من</th><th>إلى</th><th>الراتب</th><th>الدورية</th><th>السبب</th><th>العقد</th><th>ملاحظات</th></tr></thead><tbody><?php if(!$history):?><tr><td colspan="7" class="empty">لا يوجد سجل رواتب.</td></tr><?php else:foreach($history as $r):?><tr><td><strong><?= e($r['effective_from']) ?></strong></td><td><?= e($r['effective_to']?:'ساري') ?></td><td><strong><?= number_format((float)$r['basic_salary'],2) ?></strong> <?= e($r['salary_currency']?:APP_CURRENCY_CODE) ?></td><td><?= e($freq[$r['pay_frequency']]??$r['pay_frequency']) ?></td><td><?= e($reasons[$r['reason']]??$r['reason']) ?></td><td><?= e($r['contract_number']??'—') ?></td><td><?= e($r['notes']??'—') ?></td></tr><?php endforeach;endif;?></tbody></table></div><div class="info"><strong>التوافق:</strong> employees.basic_salary مرآة توافقية فقط؛ السجل التاريخي هو المصدر الفعلي.</div>
<?php elseif($view==='add'):?><div class="current"><strong>إنشاء العقد من بوابة عقود العمل</strong><p style="margin:8px 0">لضمان استخدام نموذج العقد الكامل وحجز رقم العقد مرة واحدة، لم يعد إنشاء عقد جديد يتم من هذا السجل. افتح البوابة لإعداد العقد وتوليد PDF؛ ستظهر المسودة ورقمها هنا تلقائياً بعد نجاح التوليد.</p><a class="btn primary" href="<?= e(APP_URL) ?>modules/hr/contract_builder.php?employee_id=<?= $eid ?>"><i class="fas fa-file-pdf"></i> متابعة إعداد العقد</a><a class="btn light" href="?employee_id=<?= $eid ?>&view=contracts">العودة إلى سجل العقود</a></div>
<?php else:$cur=hrGetEmployeeSalary($eid);$active=hrGetActiveEmployeeContract($eid);?><div class="title" style="margin-bottom:15px">تغيير الراتب</div><?php if($cur):?><div class="current"><small>الراتب الحالي</small><strong><?= number_format((float)$cur['basic_salary'],2) ?> <?= e($cur['salary_currency']?:APP_CURRENCY_CODE) ?></strong><small>ساري من <?= e($cur['effective_from']) ?></small></div><?php endif;?><form method="post"><input type="hidden" name="action" value="change_salary"><input type="hidden" name="employee_id" value="<?= $eid ?>"><div class="grid"><div><label class="label">الراتب الجديد *</label><input type="number" name="basic_salary" step="0.01" min="0" required></div><div><label class="label">تاريخ السريان *</label><input type="date" name="effective_from" value="<?= date('Y-m-d') ?>" required></div><div><label class="label">سبب التغيير *</label><select name="reason"><?php foreach($reasons as $k=>$v):?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach;?></select></div><div><label class="label">العقد المرتبط</label><select name="contract_id"><option value="">بدون عقد محدد</option><?php foreach($contracts as $c):?><option value="<?= (int)$c['id'] ?>" <?= $active&&(int)$active['id']===(int)$c['id']?'selected':'' ?>><?= e($c['contract_number']?:'عقد #'.$c['id']) ?></option><?php endforeach;?></select></div><div><label class="label">العملة</label><input type="text" value="<?= e(APP_CURRENCY_CODE) ?> — <?= e(APP_CURRENCY_NAME_AR) ?>" readonly></div><div><label class="label">دورية الراتب</label><select name="pay_frequency"><?php foreach($freq as $k=>$v):?><option value="<?= e($k) ?>" <?= ($cur['pay_frequency']??'monthly')===$k?'selected':'' ?>><?= e($v) ?></option><?php endforeach;?></select></div><div class="full"><label class="label">ملاحظات</label><textarea name="salary_notes" rows="3"></textarea></div></div><div class="form-actions"><a class="btn light" href="?employee_id=<?= $eid ?>&view=salary">إلغاء</a> <button class="btn primary" type="submit">حفظ تغيير الراتب</button></div></form>
      </div>
    </div>
  </div></div>
</div>
<?php endif; ?>
<?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const search = document.getElementById('es');
  const employees = Array.from(document.querySelectorAll('#elist .emp'));
  const empty = document.getElementById('employeeSearchEmpty');
  if (search) {
    search.addEventListener('input', function () {
      const query = this.value.toLowerCase().trim();
      let visible = 0;
      employees.forEach(function (employee) {
        const match = employee.dataset.q.toLowerCase().includes(query);
        employee.hidden = !match;
        if (match) visible++;
      });
      if (empty) empty.hidden = visible !== 0;
    });
  }
  const modal = document.getElementById('employeeDetailsModal');
  if (modal && window.bootstrap && window.bootstrap.Modal) {
    window.bootstrap.Modal.getOrCreateInstance(modal).show();
  }
});

document.addEventListener('click', async function (event) {
  const link = event.target.closest('#employeeDetailsModal .tabs a.tab');
  if (!link || /contract_builder\.php(?:[?#]|$)/i.test(link.href)) return;

  const target = new URL(link.href, window.location.href);
  if (target.origin !== window.location.origin) return;

  event.preventDefault();
  const modal = document.getElementById('employeeDetailsModal');
  const content = modal && modal.querySelector('.modal-content');
  if (!content) return;

  link.setAttribute('aria-busy', 'true');
  try {
    const response = await fetch(target.href, {
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    });
    if (!response.ok) throw new Error('HTTP ' + response.status);

    const html = await response.text();
    const parsed = new DOMParser().parseFromString(html, 'text/html');
    const nextContent = parsed.querySelector('#employeeDetailsModal .modal-content');
    if (!nextContent) throw new Error('Employee modal was not returned by the server.');

    content.innerHTML = nextContent.innerHTML;
    window.history.pushState({employeeModalTab: true}, '', target.href);
  } catch (error) {
    const body = content.querySelector('.modal-body');
    if (body) {
      let alert = body.querySelector('.tab-load-error');
      if (!alert) {
        alert = document.createElement('div');
        alert.className = 'alert alert-danger tab-load-error';
        alert.setAttribute('role', 'alert');
        body.prepend(alert);
      }
      alert.textContent = 'تعذر تحميل هذا القسم دون تحديث الصفحة. يرجى المحاولة مرة أخرى.';
    }
  }
});
</script>

<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="<?php echo APP_URL; ?>modules/hr/index.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>