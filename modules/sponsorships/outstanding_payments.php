<?php
// modules/sponsorships/outstanding_payments.php - Sponsor monthly obligations and follow-up
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once dirname(__DIR__,2).'/config/sponsor_assignments.php';
require_once dirname(__DIR__,2).'/modules/accounting/lib.php';
require_once dirname(__DIR__,2).'/modules/accounting/lib_fina.php';
Session::start();
if(!Session::isLoggedIn()){header('Location: '.APP_URL.'index.php');exit();}
$role=Session::getUserRole();$uid=(int)Session::getUserId();
if(!in_array($role,['supervisor','financial_manager','accountant_staff','admin','general_manager','vice_general_manager'],true)){header('Location: '.APP_URL.'index.php');exit();}
$pageTitle='المبالغ المستحقة على الكفلاء';$active='transactions';$errors=[];$sponsorFilter=(int)($_GET['sponsor_id']??0);
$where="s.status='active'";$params=[];
if($sponsorFilter>0){$where.=' AND s.id=?';$params[]=$sponsorFilter;}
if($role==='supervisor'){$rows=dbFetchAll("SELECT id,first_letter_id,gender FROM sponsors WHERE first_letter_id IN (SELECT letter_id FROM supervisor_letters WHERE supervisor_id=?)",[$uid]);$ids=[];foreach($rows as $r)if(supervisorCanAccessSponsor($uid,$r))$ids[]=(int)$r['id'];if(!$ids)$where.=' AND 0=1';else{$ph=implode(',',array_fill(0,count($ids),'?'));$where.=" AND s.id IN ($ph)";$params=array_merge($params,$ids);}}
$rows=dbFetchAll("SELECT s.id sponsor_id,s.full_name,s.sponsor_code,sp.id sponsorship_id,sp.sponsorship_code,sp.monthly_amount,sp.currency_code,sp.start_date,sp.end_date,sp.status FROM sponsorships sp JOIN sponsors s ON s.id=sp.sponsor_id WHERE $where ORDER BY s.full_name,sp.id",$params);
$report=[];
foreach($rows as $r){$start=new DateTime($r['start_date']);$end=!empty($r['end_date'])?new DateTime($r['end_date']):new DateTime('first day of this month');$cursor=new DateTime($start->format('Y-m-01'));$today=new DateTime('first day of this month');$limit=$end<$today?$end:$today;while($cursor<=$limit){$period=$cursor->format('F/Y');$paid=dbFetchOne("SELECT COALESCE(SUM(amount),0) paid FROM transactions WHERE sponsorship_id=? AND payment_period=? AND transaction_type='sponsorship_payment' AND status='posted'",[(int)$r['sponsorship_id'],$period]);$paidAmount=round((float)($paid['paid']??0),2);$due=round((float)$r['monthly_amount'],2);$out=round(max(0,$due-$paidAmount),2);if($out>0)$report[]=['sponsor_id'=>$r['sponsor_id'],'sponsor_name'=>$r['full_name'],'sponsor_code'=>$r['sponsor_code'],'sponsorship_id'=>$r['sponsorship_id'],'sponsorship_code'=>$r['sponsorship_code'],'period'=>$period,'due'=>$due,'paid'=>$paidAmount,'outstanding'=>$out,'currency'=>$r['currency_code']];$cursor->modify('+1 month');}}
$total=0;foreach($report as $r)$total+=$r['outstanding'];
include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in"><h2><i class="fas fa-file-invoice-dollar me-2"></i>المبالغ المستحقة على الكفلاء</h2><p>هذا التقرير يقارن الالتزام الشهري المسجل في الكفالة بالدفعات المرحّلة. الدفعة اللاحقة تُسجّل كمعاملة جديدة وتغلق الرصيد المتبقي دون تعديل التاريخ السابق.</p></div>
<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?>
<div class="alert alert-warning"><strong>إجمالي المتأخرات المعروضة:</strong> <?php echo number_format($total,2); ?> ج.س</div>
<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>الكفيل</th><th>الكفالة</th><th>الشهر</th><th>المستحق</th><th>المدفوع</th><th>المتبقي</th><th>الإجراء</th></tr></thead><tbody><?php if(!$report):?><tr><td colspan="7" class="text-center text-muted py-4">لا توجد مبالغ مستحقة وفق الدفعات المرحّلة.</td></tr><?php else:foreach($report as $r):?><tr><td><?php echo e($r['sponsor_name']); ?><small class="text-muted d-block"><?php echo e($r['sponsor_code']); ?></small></td><td><?php echo e($r['sponsorship_code']); ?></td><td><?php echo e($r['period']); ?></td><td><?php echo number_format($r['due'],2); ?> <?php echo e($r['currency']); ?></td><td><?php echo number_format($r['paid'],2); ?></td><td><strong class="text-danger"><?php echo number_format($r['outstanding'],2); ?></strong></td><td class="text-nowrap"><a class="btn btn-sm btn-primary" href="<?php echo APP_URL; ?>modules/transactions/create.php?sponsor_id=<?php echo (int)$r['sponsor_id']; ?>&sponsorship_id=<?php echo (int)$r['sponsorship_id'];"><i class="fas fa-plus me-1"></i>تسجيل الدفعة المتبقية</a></td></tr><?php endforeach;endif;?></tbody></table></div></div></div>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>
