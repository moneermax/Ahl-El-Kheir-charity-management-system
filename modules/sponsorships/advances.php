<?php
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once __DIR__.'/lib_payment_status.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: '.APP_URL.'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin','vice_general_manager','supervisor'], true)) { header('Location: '.APP_URL.'index.php'); exit(); }
$pageTitle = 'الدفعات المقدمة للكفالات';
$active = 'sponsorship_advances';
$rows = [];
$loadError = null;
try { $rows = ak_sar_get_advances($role, Session::getUserId()); }
catch (Throwable $e) { $loadError = $e->getMessage(); }
include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in">
  <h2><i class="fas fa-calendar-plus me-2"></i>الدفعات المقدمة للكفالات</h2>
  <p>يعرض التخصيصات المرحلة عن أشهر مستقبلية، والرصيد الزائد غير المخصص بصورة مستقلة. الرصيد الزائد لا يُنقل تلقائياً إلى شهر آخر.</p>
  <div class="quick-actions mt-3">
    <a href="<?php echo APP_URL; ?>modules/sponsorships/arrears.php" class="btn btn-outline-warning btn-sm"><i class="fas fa-triangle-exclamation me-1"></i>متأخرات شهرين</a>
    <a href="<?php echo APP_URL; ?>modules/sponsorships/index.php" class="btn btn-outline-secondary btn-sm">سجل الكفالات</a>
  </div>
</div>
<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?>
<?php if ($loadError !== null): ?>
<div class="alert alert-danger"><?php echo e($loadError); ?></div>
<?php else: ?>
<div class="card fade-in">
 <div class="card-header"><strong>التغطية المستقبلية والرصيد غير المخصص</strong> <span class="badge bg-info text-dark"><?php echo count($rows); ?></span></div>
 <div class="card-body p-0"><div class="table-responsive">
 <table class="table table-hover align-middle mb-0">
 <thead class="table-light"><tr><th>الكفالة / اليتيم</th><th>الكفيل</th><th>الشهر المغطى</th><th>الالتزام</th><th>المخصص المدفوع</th><th>المتبقي من الالتزام</th><th>الرصيد الزائد غير المخصص</th></tr></thead>
 <tbody>
 <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">لا توجد دفعات مستقبلية مخصصة أو أرصدة زائدة غير مخصصة.</td></tr>
 <?php else: foreach ($rows as $item): $sp=$item['sponsorship']; $first=true; ?>
 <?php foreach ($item['future'] as $future): ?>
 <tr>
  <td><a href="<?php echo APP_URL; ?>modules/sponsorships/view.php?id=<?php echo (int)$sp['id']; ?>"><code><?php echo e($sp['sponsorship_code']); ?></code></a><div class="small text-muted"><?php echo e($sp['child_name']); ?></div></td>
  <td><?php echo e($sp['sponsor_name']); ?></td>
  <td><?php echo e($future['month']); ?></td>
  <?php if ($future['error'] !== null): ?><td colspan="3" class="text-danger"><?php echo e($future['error']); ?></td>
  <?php else: ?>
  <td><?php echo number_format($future['commitment'],2); ?> ج.س</td>
  <td><?php echo number_format($future['allocated'],2); ?> ج.س</td>
  <td><?php echo number_format($future['remaining'],2); ?> ج.س</td>
  <?php endif; ?>
  <?php if ($first): ?><td rowspan="<?php echo max(1,count($item['future'])); ?>"><?php echo number_format($item['unallocated'],2); ?> ج.س</td><?php $first=false; endif; ?>
 </tr>
 <?php endforeach; ?>
 <?php if (!$item['future']): ?><tr><td><a href="<?php echo APP_URL; ?>modules/sponsorships/view.php?id=<?php echo (int)$sp['id']; ?>"><code><?php echo e($sp['sponsorship_code']); ?></code></a><div class="small text-muted"><?php echo e($sp['child_name']); ?></div></td><td><?php echo e($sp['sponsor_name']); ?></td><td colspan="4" class="text-muted">لا توجد تغطية مستقبلية مخصصة</td><td><?php echo number_format($item['unallocated'],2); ?> ج.س</td></tr><?php endif; ?>
 <?php endforeach; endif; ?>
 </tbody></table></div></div></div>
<?php endif; ?>
<div class="container-fluid px-3 pb-4 mt-3"><a href="<?php echo APP_URL; ?>modules/sponsorships/index.php" class="btn btn-outline-secondary btn-sm">العودة إلى سجل الكفالات</a></div>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>
