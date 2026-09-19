<?php
// modules/accounting/admin_fee_report.php - Administrative-fee revenue view for FM
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once __DIR__.'/lib.php';
Session::start();
$role=Session::getUserRole();
if(!Session::isLoggedIn()||!in_array($role,['admin','financial_manager','fm','general_manager','vice_general_manager'],true)){header('Location: '.APP_URL.'index.php');exit();}
$pageTitle='إيرادات الرسوم الإدارية';$active='acct_reports';ak_ensure_tables();ak_seed_accounts();
$from=trim($_GET['from']??'');$to=trim($_GET['to']??'');
$where='je.status=\'posted\' AND jl.account_id=?';$params=[ak_account_id('4200')];
if($from!==''){$where.=' AND je.entry_date>=?';$params[]=$from;}if($to!==''){$where.=' AND je.entry_date<=?';$params[]=$to;}
$feeRows=dbFetchAll("SELECT je.entry_date,je.entry_code,je.description,jl.credit FROM journal_lines jl JOIN journal_entries je ON je.id=jl.entry_id WHERE $where AND jl.credit>0 ORDER BY je.entry_date,je.id",$params);
$feeTotal=0;foreach($feeRows as $r)$feeTotal+=round((float)$r['credit'],2);
$inParams=[];$inWhere="je.status='posted' AND jl.account_id IN (?,?,?)";$inParams=[ak_account_id('1100'),ak_account_id('1200'),ak_account_id('1300')];if($from!==''){$inWhere.=' AND je.entry_date>=?';$inParams[]=$from;}if($to!==''){$inWhere.=' AND je.entry_date<=?';$inParams[]=$to;}
$totalInflows=(float)(dbFetchOne("SELECT COALESCE(SUM(jl.debit),0) total FROM journal_lines jl JOIN journal_entries je ON je.id=jl.entry_id WHERE $inWhere",$inParams)['total']??0);
$revParams=[];$revWhere="je.status='posted' AND a.code IN ('4100','4200','4300','4400')";if($from!==''){$revWhere.=' AND je.entry_date>=?';$revParams[]=$from;}if($to!==''){$revWhere.=' AND je.entry_date<=?';$revParams[]=$to;}
$rev=dbFetchAll("SELECT a.code,a.name_ar,COALESCE(SUM(jl.credit-jl.debit),0) amount FROM journal_lines jl JOIN journal_entries je ON je.id=jl.entry_id JOIN accounts a ON a.id=jl.account_id WHERE $revWhere GROUP BY a.id,a.code,a.name_ar ORDER BY a.code",$revParams);
include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in"><h2><i class="fas fa-percent me-2"></i>إيرادات الرسوم الإدارية</h2><p>عرض مستقل للرسوم الإدارية المحصلة كما ظهرت فعلياً في القيود المحاسبية المرحّلة. الحساب المخصص هو <strong>4200 — الرسوم الإدارية</strong>.</p></div>
<div class="card mb-4 fade-in"><div class="card-body"><form method="get" class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">من</label><input type="date" name="from" class="form-control" value="<?php echo e($from);?>"></div><div class="col-md-3"><label class="form-label">إلى</label><input type="date" name="to" class="form-control" value="<?php echo e($to);?>"></div><div class="col-md-2"><button class="btn btn-primary w-100">عرض</button></div></form></div></div>
<div class="row g-3 mb-4 fade-in"><div class="col-md-4"><div class="card h-100 border-primary"><div class="card-body"><div class="text-muted">إجمالي الداخل للصندوق/البنك/المحافظ</div><div class="fs-3 fw-bold"><?php echo number_format($totalInflows,2);?> SDG</div></div></div></div><div class="col-md-4"><div class="card h-100 border-success"><div class="card-body"><div class="text-muted">إجمالي الرسوم الإدارية</div><div class="fs-3 fw-bold text-success"><?php echo number_format($feeTotal,2);?> SDG</div></div></div></div><div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted">النسبة من إجمالي الداخل</div><div class="fs-3 fw-bold"><?php echo $totalInflows>0?number_format($feeTotal/$totalInflows*100,2):'0.00';?>%</div></div></div></div></div>
<div class="card mb-4 fade-in"><div class="card-header"><i class="fas fa-chart-column me-2"></i>ملخص الإيرادات المرحّلة</div><div class="card-body table-responsive"><table class="table table-sm align-middle"><thead><tr><th>الحساب</th><th>الاسم</th><th>المبلغ</th></tr></thead><tbody><?php foreach($rev as $r):?><tr><td><code><?php echo e($r['code']);?></code></td><td><?php echo e($r['name_ar']);?></td><td><?php echo number_format((float)$r['amount'],2);?> SDG</td></tr><?php endforeach;?></tbody></table></div></div>
<div class="card fade-in"><div class="card-header"><i class="fas fa-list me-2"></i>حركات الرسوم الإدارية — الحساب 4200</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-light"><tr><th>التاريخ</th><th>القيد</th><th>البيان</th><th>الرسوم</th></tr></thead><tbody><?php if(!$feeRows):?><tr><td colspan="4" class="text-center text-muted py-4">لا توجد رسوم إدارية مرحّلة في الفترة المحددة.</td></tr><?php else:foreach($feeRows as $r):?><tr><td><?php echo e($r['entry_date']);?></td><td><code><?php echo e($r['entry_code']);?></code></td><td><?php echo e($r['description']??'');?></td><td><strong><?php echo number_format((float)$r['credit'],2);?> SDG</strong></td></tr><?php endforeach;?><tr class="table-active fw-bold"><td colspan="3">الإجمالي</td><td><?php echo number_format($feeTotal,2);?> SDG</td></tr><?php endif;?></tbody></table></div></div></div>

<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="<?php echo APP_URL; ?>modules/accounting/index.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>