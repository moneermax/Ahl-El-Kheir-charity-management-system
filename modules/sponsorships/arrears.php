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
// Release PHP's per-session lock before any report query.
$reportFlashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
session_write_close();
$_SESSION['flash'] = $reportFlashes;

$pageTitle = 'متأخرات الكفالات لشهرين';
$active = 'sponsorship_arrears';
$rows = [];
$candidates = [];
$supervisors = [];
$loadError = null;
$searchRequested = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && (($_GET['search'] ?? '') === '1');
$filters = ['code'=>'', 'sponsor'=>'', 'child'=>'', 'supervisor_id'=>0];

try {
    $filters = ak_sar_normalize_arrears_filters($_GET);
    // This is a bounded identity-only query; it does not load payment history
    // or calculate arrears for the suggested sponsorships.
    $candidates = ak_sar_get_arrears_candidates($role, $reportUserId);

    if (in_array($role, ['admin','vice_general_manager'], true)) {
        $supervisors = dbFetchAll(
            "SELECT u.id, u.full_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.code = 'supervisor'
             ORDER BY u.full_name"
        );
    }

    if ($searchRequested) {
        if (!ak_sar_has_arrears_filter($filters)) {
            $loadError = 'استخدم أحد حقول البحث أولاً؛ لن يتم تحميل جميع الكفالات تلقائياً.';
        } else {
            $rows = ak_sar_get_arrears($role, $reportUserId, $filters);
        }
    }
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

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

<div class="card fade-in mb-3">
 <div class="card-header"><strong><i class="fas fa-filter me-1"></i>تصفية الكفالات</strong></div>
 <div class="card-body">
  <form method="get" action="<?php echo APP_URL; ?>modules/sponsorships/arrears.php" class="row g-3 align-items-end">
   <input type="hidden" name="search" value="1">
   <div class="col-md-3">
    <label class="form-label">كود الكفالة</label>
    <input type="text" name="code" class="form-control" value="<?php echo e($filters['code']); ?>" placeholder="مثال: IMP-SH-000001">
   </div>
   <div class="col-md-3">
    <label class="form-label">اسم الكفيل</label>
    <input type="text" name="sponsor" class="form-control" value="<?php echo e($filters['sponsor']); ?>" placeholder="اكتب جزءاً من اسم الكفيل">
   </div>
   <div class="col-md-3">
    <label class="form-label">اسم اليتيم</label>
    <input type="text" name="child" class="form-control" value="<?php echo e($filters['child']); ?>" placeholder="اكتب جزءاً من اسم اليتيم">
   </div>
   <?php if (in_array($role, ['admin','vice_general_manager'], true)): ?>
   <div class="col-md-3">
    <label class="form-label">المشرف المسجل على الكفيل</label>
    <select name="supervisor_id" class="form-select">
     <option value="0">جميع المشرفين</option>
     <?php foreach ($supervisors as $supervisor): ?>
      <option value="<?php echo (int)$supervisor['id']; ?>" <?php echo $filters['supervisor_id'] === (int)$supervisor['id'] ? 'selected' : ''; ?>><?php echo e($supervisor['full_name']); ?></option>
     <?php endforeach; ?>
    </select>
   </div>
   <?php endif; ?>
   <div class="col-md-3">
    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>بحث وعرض المتأخرات</button>
   </div>
   <div class="col-md-3">
    <a href="<?php echo APP_URL; ?>modules/sponsorships/arrears.php" class="btn btn-outline-secondary w-100"><i class="fas fa-rotate-right me-1"></i>مسح البحث</a>
   </div>
  </form>
  <small class="text-muted d-block mt-2">أدخل معياراً واحداً على الأقل. إذا تجاوزت النتائج 100 كفالة، أضف معياراً لتضييق البحث. لا يتم حساب المتأخرات قبل الضغط على زر البحث.</small>
 </div>
</div>

<div class="card fade-in mb-3">
 <div class="card-header d-flex justify-content-between align-items-center">
  <strong><i class="fas fa-lightbulb me-1"></i>أحدث 10 كفالات مقترحة للبحث</strong>
  <span class="badge bg-secondary"><?php echo count($candidates); ?></span>
 </div>
 <div class="card-body">
  <p class="text-muted small">هذه أحدث سجلات الكفالات ضمن نطاق صلاحيتك لتسهيل بدء البحث؛ ظهور الكفالة هنا لا يعني أنها مصنفة كمتأخرة.</p>
  <?php if (!$candidates): ?>
   <div class="text-muted text-center py-3">لا توجد كفالات متاحة ضمن نطاق صلاحيتك.</div>
  <?php else: ?>
   <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
     <thead class="table-light"><tr><th>كود الكفالة</th><th>اليتيم</th><th>الكفيل</th><?php if (in_array($role, ['admin','vice_general_manager'], true)): ?><th>المشرف المسجل</th><?php endif; ?><th>اختيار</th></tr></thead>
     <tbody>
      <?php foreach ($candidates as $candidate): ?>
       <tr>
        <td><code><?php echo e($candidate['sponsorship_code']); ?></code></td>
        <td><?php echo e($candidate['child_name'] ?: 'سجل اليتيم غير مرتبط'); ?></td>
        <td><?php echo e($candidate['sponsor_name']); ?></td>
        <?php if (in_array($role, ['admin','vice_general_manager'], true)): ?><td><?php echo e($candidate['supervisor_name'] ?: '—'); ?></td><?php endif; ?>
        <td><a class="btn btn-sm btn-outline-primary" href="<?php echo APP_URL; ?>modules/sponsorships/arrears.php?<?php echo e(http_build_query(['search'=>'1','code'=>$candidate['sponsorship_code']])); ?>">استخدم الكود</a></td>
       </tr>
      <?php endforeach; ?>
     </tbody>
    </table>
   </div>
  <?php endif; ?>
 </div>
</div>

<?php if ($loadError !== null): ?>
<div class="alert alert-danger"><?php echo e($loadError); ?></div>
<?php elseif (!$searchRequested): ?>
<div class="alert alert-info"><i class="fas fa-circle-info me-1"></i>الصفحة جاهزة. استخدم الفلاتر أعلاه لعرض المتأخرات؛ لم يتم تحميل سجل المدفوعات أو إجراء حساب شامل.</div>
<?php else: ?>
<div class="card fade-in">
 <div class="card-header d-flex justify-content-between align-items-center"><strong>نتائج البحث — الكفالات التي بلغت حد المتأخرات</strong><span class="badge bg-warning text-dark"><?php echo count($rows); ?></span></div>
 <div class="card-body p-0"><div class="table-responsive">
 <table class="table table-hover align-middle mb-0">
 <thead class="table-light"><tr><th>الكفالة / اليتيم</th><th>الكفيل</th><th>الشهر المستحق</th><th>الالتزام</th><th>المسدد</th><th>المتبقي</th><th>حالة التحصيل</th><th>التفاصيل</th></tr></thead>
 <tbody>
 <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">لا توجد كفالات مطابقة بلغت حد الشهرين وفق البيانات المرحلة الحالية.</td></tr>
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
