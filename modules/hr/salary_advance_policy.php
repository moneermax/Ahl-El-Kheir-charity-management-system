<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_salary_advance_policy.php';

Session::start();
$role = (string)Session::getUserRole();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'login.php'); exit; }
if (!in_array($role, ['financial_manager','admin','fm'], true)) { header('Location: ' . APP_URL . 'index.php'); exit; }
$pdo=db(); $message=''; $error='';
try {
 if($_SERVER['REQUEST_METHOD']==='POST') {
  if(!verify_csrf()) throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة وحاول مرة أخرى.');
  $action=(string)($_POST['action']??'');
  if($action==='delete_policy'){
   $policyId=(int)($_POST['policy_id']??0);
   if($policyId<1) throw new InvalidArgumentException('إصدار السياسة غير صالح.');
   $policy=dbFetchOne('SELECT * FROM hr_salary_advance_policy_versions WHERE id=? LIMIT 1',[$policyId]);
   if(!$policy) throw new InvalidArgumentException('إصدار السياسة المطلوب غير موجود.');
   $today=date('Y-m-d');
   if((string)$policy['effective_from']<=$today) throw new InvalidArgumentException('لا يمكن حذف سياسة سارية أو منتهية. استخدم إصداراً جديداً بدلاً من تعديل السجل التاريخي.');
   $usage=dbFetchOne('SELECT COUNT(*) AS c FROM hr_salary_advance_requests WHERE policy_version_id=?',[$policyId]);
   if((int)($usage['c']??0)>0) throw new InvalidArgumentException('لا يمكن حذف هذه السياسة لأنها مرتبطة بطلبات سلف. السجل المرتبط يجب أن يبقى محفوظاً للتدقيق.');
   $pdo->prepare('DELETE FROM hr_salary_advance_policy_versions WHERE id=?')->execute([$policyId]);
   $message='تم حذف إصدار السياسة المستقبلي غير المستخدم بأمان.';
  } elseif($action==='update_policy'){
   $policyId=(int)($_POST['policy_id']??0);
   if($policyId<1) throw new InvalidArgumentException('إصدار السياسة غير صالح.');
   $existingPolicy=dbFetchOne('SELECT * FROM hr_salary_advance_policy_versions WHERE id=? LIMIT 1',[$policyId]);
   if(!$existingPolicy) throw new InvalidArgumentException('إصدار السياسة المطلوب غير موجود.');
   $today=date('Y-m-d');
   if((string)$existingPolicy['effective_from']<=$today) throw new InvalidArgumentException('لا يمكن تعديل سياسة سارية أو منتهية. أنشئ إصداراً جديداً للحفاظ على السجل التاريخي.');
   $usage=dbFetchOne('SELECT COUNT(*) AS c FROM hr_salary_advance_requests WHERE policy_version_id=?',[$policyId]);
   if((int)($usage['c']??0)>0) throw new InvalidArgumentException('لا يمكن تعديل هذه السياسة لأنها مرتبطة بطلبات سلف.');
   $p=hrSalaryAdvancePolicyValidate($_POST);
   $collision=dbFetchOne('SELECT id FROM hr_salary_advance_policy_versions WHERE effective_from=? AND id<>? LIMIT 1',[$p['effective_from'],$policyId]);
   if($collision) throw new InvalidArgumentException('يوجد إصدار سياسة آخر بنفس تاريخ السريان بالفعل.');
   $sql="UPDATE hr_salary_advance_policy_versions SET policy_name=?,effective_from=?,notes=?,allow_any_request_amount=?,minimum_request_amount=?,maximum_request_amount=?,allow_multiple_active_advances=?,allow_fixed_monthly_repayment=?,allow_full_eligible_salary_repayment=?,allow_full_settlement_from_salary=?,allow_direct_repayment=?,allow_custom_repayment_terms=?,maximum_monthly_deduction=?,maximum_repayment_months=?,repayment_start_rule=?,insufficient_salary_rule=?,eligible_salary_basis=?,minimum_service_days=?,probation_allowed=?,terminated_employee_allowed=?,require_accounting_verification=?,allow_early_settlement=? WHERE id=?";
   $pdo->prepare($sql)->execute([$p['policy_name'],$p['effective_from'],$p['notes'],$p['allow_any_request_amount'],$p['minimum_request_amount'],$p['maximum_request_amount'],$p['allow_multiple_active_advances'],$p['allow_fixed_monthly_repayment'],$p['allow_full_eligible_salary_repayment'],$p['allow_full_settlement_from_salary'],$p['allow_direct_repayment'],$p['allow_custom_repayment_terms'],$p['maximum_monthly_deduction'],$p['maximum_repayment_months'],$p['repayment_start_rule'],$p['insufficient_salary_rule'],$p['eligible_salary_basis'],$p['minimum_service_days'],$p['probation_allowed'],$p['terminated_employee_allowed'],$p['require_accounting_verification'],$p['allow_early_settlement'],$policyId]);
   $message='تم تحديث إصدار السياسة V'.(int)$existingPolicy['version_no'].' بأمان.';
  } elseif($action==='create_policy'){
   $p=hrSalaryAdvancePolicyValidate($_POST);
  $pdo->beginTransaction();
  try {
   $existing=dbFetchOne('SELECT id FROM hr_salary_advance_policy_versions WHERE effective_from=? LIMIT 1',[$p['effective_from']]);
   if($existing) throw new InvalidArgumentException('يوجد إصدار سياسة بنفس تاريخ السريان بالفعل.');
   $next=dbFetchOne('SELECT COALESCE(MAX(version_no),0)+1 AS next_version FROM hr_salary_advance_policy_versions'); $v=(int)$next['next_version'];
   $sql="INSERT INTO hr_salary_advance_policy_versions (version_no,policy_name,effective_from,notes,allow_any_request_amount,minimum_request_amount,maximum_request_amount,allow_multiple_active_advances,allow_fixed_monthly_repayment,allow_full_eligible_salary_repayment,allow_full_settlement_from_salary,allow_direct_repayment,allow_custom_repayment_terms,maximum_monthly_deduction,maximum_repayment_months,repayment_start_rule,insufficient_salary_rule,eligible_salary_basis,minimum_service_days,probation_allowed,terminated_employee_allowed,require_accounting_verification,allow_early_settlement,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
   $pdo->prepare($sql)->execute([$v,$p['policy_name'],$p['effective_from'],$p['notes'],$p['allow_any_request_amount'],$p['minimum_request_amount'],$p['maximum_request_amount'],$p['allow_multiple_active_advances'],$p['allow_fixed_monthly_repayment'],$p['allow_full_eligible_salary_repayment'],$p['allow_full_settlement_from_salary'],$p['allow_direct_repayment'],$p['allow_custom_repayment_terms'],$p['maximum_monthly_deduction'],$p['maximum_repayment_months'],$p['repayment_start_rule'],$p['insufficient_salary_rule'],$p['eligible_salary_basis'],$p['minimum_service_days'],$p['probation_allowed'],$p['terminated_employee_allowed'],$p['require_accounting_verification'],$p['allow_early_settlement'],Session::getUserID()]);
   $pdo->commit(); $message='تم إنشاء إصدار سياسة السلف رقم V'.$v.' بتاريخ سريان '.$p['effective_from'].'.';
  } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 }
} catch(Throwable $e){$error=$e->getMessage();}
$activePolicy=hrSalaryAdvancePolicyGetActive($pdo); $policies=hrSalaryAdvancePolicyGetAll($pdo);
$editPolicyId=(int)($_GET['edit']??0);
$editPolicy=null;
if($editPolicyId>0){
 $editPolicy=dbFetchOne('SELECT * FROM hr_salary_advance_policy_versions WHERE id=? LIMIT 1',[$editPolicyId]);
 if($editPolicy && (string)$editPolicy['effective_from']<=date('Y-m-d')) $editPolicy=null;
}
$pageTitle='سياسة السلف على الراتب'; $active='salary_advance_policy'; $salaryAdvanceBackUrl=APP_URL.'modules/hr/salary_advance_dashboard.php'; require_once __DIR__.'/../../includes/header.php';
?>
<style>
.salary-advance-policy{max-width:1500px;margin:0 auto}
.salary-advance-policy .hero{background:linear-gradient(135deg,#173f73,#2d67ad);color:#fff;border-radius:14px;padding:22px 25px;margin-bottom:16px}
.salary-advance-policy .hero-row{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.salary-advance-policy .hero h1{font-size:1.35rem;font-weight:800;margin:0}
.salary-advance-policy .hero p{font-size:.74rem;margin:6px 0 0;opacity:.9;line-height:1.5}
.salary-advance-policy .card{background:#fff;border:1px solid #e5eaf0;border-radius:12px;box-shadow:0 2px 12px rgba(16,24,40,.05);margin-bottom:15px;overflow:hidden}
.salary-advance-policy .card-body{padding:16px}
@media(max-width:650px){.salary-advance-policy .hero{padding:17px}}
</style>
<script>window.AK_PAGE_BACK_URL=<?php echo json_encode($salaryAdvanceBackUrl, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;</script>
<div class="salary-advance-policy">
<section class="hero"><div class="hero-row"><div><h1><i class="fas fa-hand-holding-dollar me-2"></i>سياسة السلف على الراتب</h1><p>سياسة سنوية/مُصدرة من FM وتستخدم كإعدادات افتراضية لطلبات السلف. يمكن تخصيص الشروط لاحقاً لكل طلب دون تعديل السياسة الأصلية.</p></div></div></section>
<div class="card shadow-sm border-0"><div class="card-body"><?php if($message):?><div class="alert alert-success py-2"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger py-2"><?=e($error)?></div><?php endif;?><?php if($activePolicy):?><div class="alert alert-info py-2 small">السياسة السارية حالياً: <strong>V<?= (int)$activePolicy['version_no'] ?></strong> — <?=e($activePolicy['policy_name'])?> — <?=e($activePolicy['effective_from'])?></div><?php else:?><div class="alert alert-warning py-2 small">لا توجد سياسة سلف سارية حالياً.</div><?php endif;?><form method="post"><?php echo csrf_field();?><input type="hidden" name="action" value="<?= $editPolicy ? 'update_policy' : 'create_policy' ?>"><?php if($editPolicy): ?><input type="hidden" name="policy_id" value="<?= (int)$editPolicy['id'] ?>"><?php endif; ?><div class="row g-3"><div class="col-md-4"><label class="form-label">اسم السياسة *</label><input name="policy_name" class="form-control" required value="<?=e($editPolicy['policy_name']??'سياسة السلف على الراتب')?>"></div><div class="col-md-4"><label class="form-label">تاريخ السريان *</label><input type="date" name="effective_from" class="form-control" min="<?=e(date('Y-m-d',strtotime('+1 day')))?>" value="<?=e($editPolicy['effective_from']??'')?>" required></div><div class="col-md-4"><label class="form-label">أساس الراتب المؤهل</label><select name="eligible_salary_basis" class="form-select"><option value="net_before_advance" <?= (($editPolicy['eligible_salary_basis']??'net_before_advance')==='net_before_advance')?'selected':'' ?>>صافي الراتب قبل خصم السلفة</option><option value="gross" <?= (($editPolicy['eligible_salary_basis']??'')==='gross')?'selected':'' ?>>الراتب الإجمالي</option></select></div><div class="col-12"><hr><strong>قواعد الطلب</strong></div><div class="col-md-3"><label class="form-label"><input type="checkbox" name="allow_any_request_amount" <?= (($editPolicy['allow_any_request_amount']??1)?'checked':'') ?>> السماح بأي مبلغ مطلوب</label></div><div class="col-md-3"><label class="form-label">الحد الأدنى <span class="request-limit-required text-danger">*</span><input type="number" step="0.01" min="0" name="minimum_request_amount" id="minimum_request_amount" class="form-control" value="<?=e((string)($editPolicy['minimum_request_amount']??''))?>"></label></div><div class="col-md-3"><label class="form-label">الحد الأقصى <span class="request-limit-required text-danger">*</span><input type="number" step="0.01" min="0" name="maximum_request_amount" id="maximum_request_amount" class="form-control" value="<?=e((string)($editPolicy['maximum_request_amount']??''))?>"></label></div><div class="col-md-3"><label class="form-label"><input type="checkbox" name="allow_multiple_active_advances" <?= (($editPolicy['allow_multiple_active_advances']??0)?'checked':'') ?>> السماح بأكثر من سلفة نشطة</label></div><div class="col-12"><hr><strong>طرق السداد</strong></div><div class="col-md-4"><label><input type="checkbox" name="allow_fixed_monthly_repayment" <?= (($editPolicy['allow_fixed_monthly_repayment']??1)?'checked':'') ?>> قسط شهري ثابت</label></div><div class="col-md-4"><label><input type="checkbox" name="allow_full_eligible_salary_repayment" <?= (($editPolicy['allow_full_eligible_salary_repayment']??1)?'checked':'') ?>> خصم كامل الراتب المؤهل</label></div><div class="col-md-4"><label><input type="checkbox" name="allow_full_settlement_from_salary" <?= (($editPolicy['allow_full_settlement_from_salary']??1)?'checked':'') ?>> تسوية كامل الرصيد من الراتب</label></div><div class="col-md-4"><label><input type="checkbox" name="allow_direct_repayment" <?= (($editPolicy['allow_direct_repayment']??1)?'checked':'') ?>> سداد مباشر</label></div><div class="col-md-4"><label><input type="checkbox" name="allow_custom_repayment_terms" <?= (($editPolicy['allow_custom_repayment_terms']??1)?'checked':'') ?>> تخصيص شروط السداد لكل طلب</label></div><div class="col-md-4"><label class="form-label">الحد الأقصى للخصم الشهري <span class="text-danger">*</span><input type="number" step="0.01" min="0.01" name="maximum_monthly_deduction" class="form-control" required value="<?=e((string)($editPolicy['maximum_monthly_deduction']??''))?>"></label></div><div class="col-md-4"><label class="form-label">أقصى عدد أشهر للسداد <span class="text-danger">*</span><input type="number" min="1" name="maximum_repayment_months" class="form-control" required value="<?=e((string)($editPolicy['maximum_repayment_months']??''))?>"></label></div><div class="col-md-4"><label class="form-label">بدء السداد<select name="repayment_start_rule" class="form-select"><option value="next_payroll" <?= (($editPolicy['repayment_start_rule']??'next_payroll')==='next_payroll')?'selected':'' ?>>المسير التالي</option><option value="specified_month" <?= (($editPolicy['repayment_start_rule']??'')==='specified_month')?'selected':'' ?>>شهر يحدده الطلب</option></select></label></div><div class="col-md-4"><label class="form-label">عند عدم كفاية الراتب<select name="insufficient_salary_rule" class="form-select"><option value="available_salary" <?= (($editPolicy['insufficient_salary_rule']??'available_salary')==='available_salary')?'selected':'' ?>>خصم المتاح وترحيل الباقي</option><option value="skip_month" <?= (($editPolicy['insufficient_salary_rule']??'')==='skip_month')?'selected':'' ?>>تجاوز الشهر وترحيل القسط</option></select></label></div><div class="col-12"><hr><strong>الأهلية والضوابط</strong></div><div class="col-md-3"><label class="form-label">الحد الأدنى للخدمة (يوم)<input type="number" min="0" name="minimum_service_days" class="form-control" value="<?=e((string)($editPolicy['minimum_service_days']??0))?>"></label></div><div class="col-md-3"><label><input type="checkbox" name="probation_allowed" <?= (($editPolicy['probation_allowed']??1)?'checked':'') ?>> السماح خلال فترة التجربة</label></div><div class="col-md-3"><label><input type="checkbox" name="terminated_employee_allowed" <?= (($editPolicy['terminated_employee_allowed']??0)?'checked':'') ?>> السماح لمنهي الخدمة</label></div><div class="col-md-3"><label><input type="checkbox" name="require_accounting_verification" <?= (($editPolicy['require_accounting_verification']??1)?'checked':'') ?>> إلزام التحقق المحاسبي</label></div><div class="col-md-3"><label><input type="checkbox" name="allow_early_settlement" <?= (($editPolicy['allow_early_settlement']??1)?'checked':'') ?>> السماح بالتسوية المبكرة</label></div><div class="col-12"><label class="form-label">ملاحظات<textarea name="notes" class="form-control" rows="2"><?=e($editPolicy['notes']??'')?></textarea></label></div><div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="fas <?= $editPolicy ? 'fa-save' : 'fa-plus' ?> me-1"></i><?= $editPolicy ? 'حفظ تعديلات السياسة' : 'إنشاء إصدار السياسة' ?></button><?php if($editPolicy): ?><a class="btn btn-outline-secondary" href="<?=e(APP_URL.'modules/hr/salary_advance_policy.php')?>">إلغاء التعديل</a><?php endif; ?></div></div></form></div></div><div class="card shadow-sm border-0 mt-3"><div class="card-body"><h5>سجل إصدارات السياسة</h5><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>الإصدار</th><th>الاسم</th><th>السريان</th><th>المبلغ</th><th>السداد</th><th>الأهلية</th><th>أنشأه</th><th>الإجراءات</th></tr></thead><tbody><?php foreach($policies as $p):?><tr><td><strong>V<?= (int)$p['version_no']?></strong></td><td><?=e($p['policy_name'])?></td><td><?=e($p['effective_from'])?></td><td><?= (int)$p['allow_any_request_amount']?'أي مبلغ':number_format((float)$p['minimum_request_amount'],2).' — '.number_format((float)$p['maximum_request_amount'],2)?></td><td><?= (int)$p['allow_fixed_monthly_repayment']?'شهري ':'' ?><?= (int)$p['allow_full_eligible_salary_repayment']?'كامل الراتب ':'' ?><?= (int)$p['allow_direct_repayment']?'مباشر':'' ?></td><td><?= (int)$p['probation_allowed']?'تجربة مسموحة':'تجربة غير مسموحة' ?></td><td><?=e($p['created_by_name']??'—')?></td><td class="text-nowrap"><?php $policyFuture=(string)$p['effective_from']>date('Y-m-d'); ?><a class="btn btn-sm btn-outline-primary <?= $policyFuture ? '' : 'disabled' ?>" href="<?= $policyFuture ? e(APP_URL.'modules/hr/salary_advance_policy.php?edit='.(int)$p['id']) : '#' ?>" title="<?= $policyFuture ? 'تعديل السياسة المستقبلية' : 'لا يمكن تعديل سياسة سارية أو منتهية' ?>"><i class="fas fa-pen"></i></a><form method="post" class="d-inline" onsubmit="return confirm('هل تريد حذف إصدار السياسة هذا؟ لا يمكن التراجع عن الحذف.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_policy"><input type="hidden" name="policy_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit" <?= $policyFuture ? '' : 'disabled' ?> title="<?= $policyFuture ? 'حذف السياسة المستقبلية غير المستخدمة' : 'لا يمكن حذف سياسة سارية أو منتهية' ?>"><i class="fas fa-trash"></i></button></form></td></tr><?php endforeach;?></tbody></table></div></div></div></div>
<script>
(function(){
  const anyAmount = document.querySelector('input[name="allow_any_request_amount"]');
  const minAmount = document.getElementById('minimum_request_amount');
  const maxAmount = document.getElementById('maximum_request_amount');
  const requiredMarks = document.querySelectorAll('.request-limit-required');
  if (!anyAmount || !minAmount || !maxAmount) return;
  function syncRequestLimits(){
    const required = !anyAmount.checked;
    minAmount.required = required;
    maxAmount.required = required;
    requiredMarks.forEach(function(mark){ mark.hidden = !required; });
    minAmount.disabled = !required;
    maxAmount.disabled = !required;
    if (!required) {
      minAmount.value = '';
      maxAmount.value = '';
    }
  }
  anyAmount.addEventListener('change', syncRequestLimits);
  syncRequestLimits();
})();
</script>
<?php require_once __DIR__.'/../../includes/footer.php'; ?>