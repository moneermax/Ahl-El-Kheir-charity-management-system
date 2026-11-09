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
$reportUserId = Session::getUserId();
// Release PHP's per-session lock before the report's potentially long read-only queries.
// Preserve flash consumption semantics: clear it in the stored session, then expose this request's copy.
$reportFlashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
session_write_close();
$_SESSION['flash'] = $reportFlashes;
$pageTitle = 'متأخرات الكفالات لشهرين';
$active = 'sponsorship_arrears';
$rows = [];
$loadError = null;
try { $rows = ak_sar_get_arrears($role, $reportUserId); }
catch (Throwable $e) { $loadError = $e->getMessage(); }
include dirname(__DIR__,2).'/includes/header.php';
?>
<div class="welcome-section fade-in">
  <h2><i class="fas fa-triangle-exclamation me-2"></i>متأخرات الكفالات لشهرين</h2>
  <p>يعرض الكفالات التي لديها شهران مؤهلان متتاليان مكتملان على الأقل، ولكل منهما رصيد غير مسدد. الأشهر الموقوفة بالكامل مستبعدة ولا تقطع تسلسل الأشهر المؤهلة.</p>
  <div class="quick-actions mt-3">
    <a href="<?php echo APP_URL; ?>modules/sponsorships/advances.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-calendar-plus me-1"></i>الدفعات المقدمة</a>
    <a href="<?php echo APP_URL; ?>modules/sponsorships/index.php" class="btn btn-outline-secondary btn-sm">سجل الكفالات</a>
  </div>
</div>
<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?>
<?php if ($loadError !== null): ?>
<div class="alert alert-danger"><?php echo e($loadError); ?></div>
<?php else: ?>
<div class="card fade-in">
 <div class="card-header d-flex justify-content-between align-items-center"><strong>الكفالات التي بلغت حد المتأخرات</strong><span class="badge bg-warning text-dark"><?php echo count($rows); ?></span></div>
 <div class="card-body p-0"><div class="table-responsive">
 <table class="table table-hover align-middle mb-0">
 <thead class="table-light"><tr><th>الكفالة / اليتيم</th><th>الكفيل</th><th>الشهر المستحق</th><th>الالتزام</th><th>المسدد</th><th>المتبقي</th><th>حالة التحصيل</th><th>التفاصيل</th></tr></thead>
 <tbody>
 <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">لا توجد كفالات بلغت حد الشهرين وفق البيانات المرحلة الحالية.</td></tr>
 <?php else: foreach ($rows as $item): $sp=$item['sponsorship']; ?>
 <?php if ($item['error'] !== null): ?>
 <tr><td><code><?php echo e($sp['sponsorship_code']); ?></code> — <?php echo e($sp['child_name']); ?></td><td><?php echo e($sp['sponsor_name']); ?></td><td colspan="6"><span class="text-danger"><?php echo e($item['error']); ?></span></td></tr>
 <?php else: foreach ($item['months'] as $month): ?>
 <tr>
  <td><?php if (!empty($sp['family_child_record_id'])): ?><a href="<?php echo APP_URL; ?>modules/sponsorships/view.php?id=<?php echo (int)$sp['id']; ?>"><code><?php echo e($sp['sponsorship_code']); ?></code></a><?php else: ?><code><?php echo e($sp['sponsorship_code']); ?></code><?php endif; ?><div class="small text-muted"><?php echo e($sp['child_name']); ?></div></td>
  <td><?php echo e($sp['sponsor_name']); ?></td>
  <td><?php echo e($month['label']); ?></td>
  <td><?php echo number_format($month['commitment'],2); ?> ج.س</td>
  <td><?php echo number_format($month['paid'],2); ?> ج.س</td>
  <td class="fw-bold text-danger"><?php echo number_format($month['outstanding'],2); ?> ج.س</td>
  <td>
   <?php if (!$month['pending']): ?><span class="badge bg-secondary">لا يوجد طلب معلق</span>
   <?php else: foreach ($month['pending'] as $pending): ?>
    <?php $isPending=in_array($pending['status'],['pending','pending_fm_review'],true); ?>
    <span class="badge <?php echo $isPending?'bg-warning text-dark':'bg-danger'; ?>"><?php echo $isPending?'بانتظار المراجعة المالية':'مُعاد للتعديل'; ?></span>
    <small class="d-block"><?php echo number_format((float)$pending['amount'],2); ?> ج.س</small>
   <?php endforeach; endif; ?>
  </td>
  <td><a class="btn btn-sm btn-outline-primary" href="<?php echo APP_URL; ?>modules/sponsorships/view.php?id=<?php echo (int)$sp['id']; ?>">عرض الكفالة</a></td>
 </tr>
 <?php endforeach; endif; ?>
 <?php endforeach; endif; ?>
 </tbody></table></div></div></div>
<?php endif; ?>
<div class="container-fluid px-3 pb-4 mt-3"><a href="<?php echo APP_URL; ?>modules/sponsorships/index.php" class="btn btn-outline-secondary btn-sm">العودة إلى سجل الكفالات</a></div>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>
