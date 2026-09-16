<?php
// dashboard/supervisor_dashboard.php - Supervisor dashboard (orphan-level pivot)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
Session::start();
if (!Session::isLoggedIn() || Session::getUserRole() !== 'supervisor') { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle=t('dashboard.supervisor_title');$active='dashboard';$uid=Session::getUserId();$myLetters=dbFetchAll("SELECT l.code,l.name_ar FROM supervisor_letters sl JOIN letters l ON l.id=sl.letter_id WHERE sl.supervisor_id=? ORDER BY l.sort_order",[$uid]);

/*
 * Dashboard sponsor scope follows the authoritative sponsor first-letter +
 * sponsor-gender responsibility matrix. sponsor.supervisor_id is an
 * operational assignment/history field, not an independent authorization grant.
 */
$scopeSql="EXISTS (SELECT 1 FROM supervisor_letters sl WHERE sl.supervisor_id=? AND sl.letter_id=s.first_letter_id AND (CONVERT(sl.gender USING utf8mb4) COLLATE utf8mb4_unicode_ci=CONVERT(s.gender USING utf8mb4) COLLATE utf8mb4_unicode_ci OR CONVERT(sl.gender USING utf8mb4) COLLATE utf8mb4_unicode_ci IN ('both','all','كلاهما','الكل')))";
$s=dbFetchOne("SELECT
    (SELECT COUNT(*) FROM sponsors s WHERE $scopeSql) AS my_sponsors,
    (SELECT COUNT(*) FROM sponsorships sp JOIN sponsors s ON s.id=sp.sponsor_id WHERE sp.status='active' AND $scopeSql) AS my_active_sponsorships,
    (SELECT COUNT(DISTINCT sp.child_id) FROM sponsorships sp JOIN sponsors s ON s.id=sp.sponsor_id WHERE sp.status='active' AND $scopeSql) AS my_orphans,
    (SELECT COALESCE(SUM(sp.amount),0) FROM sponsor_payments sp JOIN sponsors s ON s.id=sp.sponsor_id WHERE sp.status='approved' AND DATE_FORMAT(sp.created_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') AND $scopeSql) AS month_collected",
    [$uid,$uid,$uid,$uid]);

/* Fina statistics are isolated to Fina intake records created by this supervisor.
 * Amounts are intentionally not summed here because Fina supports multiple currencies;
 * these cards therefore report auditable counts without mixing currencies. */
$finaStats=dbFetchOne("SELECT
    COUNT(fpi.id) AS fina_total,
    SUM(CASE WHEN sp.status='pending' THEN 1 ELSE 0 END) AS fina_pending,
    SUM(CASE WHEN sp.status='approved' AND DATE_FORMAT(sp.payment_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') THEN 1 ELSE 0 END) AS fina_approved_month
    FROM fina_payment_intakes fpi
    JOIN sponsor_payments sp ON sp.id=fpi.sponsor_payment_id
    WHERE sp.supervisor_id=?",[$uid]);

$recentPayments=dbFetchAll("SELECT sp.id,sp.created_at,sp.amount,sp.payment_method,sp.status,sp.payment_period,s.full_name AS sponsor_name FROM sponsor_payments sp JOIN sponsors s ON s.id=sp.sponsor_id WHERE sp.supervisor_id=? AND $scopeSql ORDER BY sp.created_at DESC,sp.id DESC LIMIT 20",[$uid,$uid]);
$methodLabels=['cash'=>'نقدي','bank_transfer'=>'تحويل بنكي','credit_card'=>'بطاقة','mobile'=>'محفظة إلكترونية','other'=>'أخرى'];
$statusLabels=['pending'=>'بانتظار المراجعة','approved'=>'معتمدة','returned'=>'مرتجعة','cancelled'=>'ملغاة'];
include __DIR__.'/../includes/header.php'; ?>
<style>
/* Supervisor dashboard: the page cards replace the global quick-action buttons. */
.qa-actions{display:none!important}
.sup-stat{border:none;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,.045);transition:transform .2s}.sup-stat:hover{transform:translateY(-2px)}.sup-stat .v{font-size:1.45rem;font-weight:700;color:#1b4d8f;line-height:1.2}.sup-stat .label{font-size:.78rem}
.sup-fina-stat{border:1px solid #e4e9ef;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,.04);background:#fff}.sup-fina-stat .v{font-size:1.5rem;font-weight:700;color:#176b57;line-height:1.2}.sup-fina-stat .label{font-size:.78rem}.sup-fina-heading{font-weight:700;color:#176b57;font-size:1rem}
.sup-action{display:flex;align-items:center;gap:14px;height:100%;min-height:72px;padding:12px 16px;border:1px solid #e6eaf0;border-radius:10px;background:#fff;color:inherit;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.035);transition:transform .2s,box-shadow .2s,border-color .2s}.sup-action:hover{color:inherit;transform:translateY(-2px);box-shadow:0 5px 14px rgba(0,0,0,.07);border-color:#b9c9df}.sup-action:focus-visible{outline:3px solid rgba(27,77,143,.2);outline-offset:2px}.sup-action .icon{width:38px;height:38px;min-width:38px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;background:#eef4fb;color:#1b4d8f;font-size:1rem}.sup-action .title{font-weight:700;color:#1b4d8f;font-size:.95rem}.sup-action .hint{font-size:.72rem;color:#6c757d}
</style>
<div class="welcome-section fade-in"><h2><?php echo e(t('dashboard.supervisor_title')); ?></h2><p><?php echo e(t('dashboard.supervisor_intro')); ?></p></div>
<?php include __DIR__.'/../includes/alerts.php'; ?>
<div class="row g-2 mb-3 fade-in"><div class="col-6 col-xl-3"><div class="card sup-stat text-center"><div class="card-body py-2"><div class="v"><?php echo(int)($s['my_sponsors']??0); ?></div><div class="text-muted label"><?php echo e(t('dashboard.my_sponsors')); ?></div></div></div></div><div class="col-6 col-xl-3"><div class="card sup-stat text-center"><div class="card-body py-2"><div class="v"><?php echo(int)($s['my_active_sponsorships']??0); ?></div><div class="text-muted label"><?php echo e(t('dashboard.active_sponsorships')); ?></div></div></div></div><div class="col-6 col-xl-3"><div class="card sup-stat text-center"><div class="card-body py-2"><div class="v"><?php echo(int)($s['my_orphans']??0); ?></div><div class="text-muted label"><?php echo e(t('dashboard.sponsored_orphans')); ?></div></div></div></div><div class="col-6 col-xl-3"><div class="card sup-stat text-center"><div class="card-body py-2"><div class="v"><?php echo number_format((float)($s['month_collected']??0),0); ?></div><div class="text-muted label"><?php echo e(t('dashboard.monthly_receipts')); ?></div></div></div></div></div>
<div class="mb-2 fade-in"><div class="sup-fina-heading"><i class="fas fa-hand-holding-dollar me-1"></i>إحصاءات تحصيل فينا الخير الخاصة بك</div><div class="text-muted small">الأرقام أدناه تخص تحصيلات فينا التي سجلتها أنت فقط، ولا تخلط العملات في إجمالي واحد.</div></div>
<div class="row g-2 mb-3 fade-in"><div class="col-6 col-xl-4"><div class="card sup-fina-stat text-center"><div class="card-body py-2"><div class="v"><?php echo(int)($finaStats['fina_total']??0); ?></div><div class="text-muted label">إجمالي تحصيلات فينا المسجلة</div></div></div></div><div class="col-6 col-xl-4"><div class="card sup-fina-stat text-center"><div class="card-body py-2"><div class="v"><?php echo(int)($finaStats['fina_pending']??0); ?></div><div class="text-muted label">تحصيلات فينا بانتظار المراجعة</div></div></div></div><div class="col-12 col-xl-4"><div class="card sup-fina-stat text-center"><div class="card-body py-2"><div class="v"><?php echo(int)($finaStats['fina_approved_month']??0); ?></div><div class="text-muted label">تحصيلات فينا المعتمدة هذا الشهر</div></div></div></div></div>
<div class="row g-2 mb-4 fade-in"><div class="col-md-3"><a href="<?php echo APP_URL; ?>modules/sponsors/index.php" class="sup-action"><div class="icon"><i class="fas fa-users"></i></div><div><div class="title"><?php echo e(t('common.sponsors')); ?></div><div class="hint"><?php echo e(t('dashboard.my_sponsors')); ?></div></div></a></div><div class="col-md-3"><a href="<?php echo APP_URL; ?>modules/families/index.php" class="sup-action"><div class="icon"><i class="fas fa-house"></i></div><div><div class="title"><?php echo e(t('common.families')); ?></div><div class="hint"><?php echo e(t('dashboard.supervisor_intro')); ?></div></div></a></div><div class="col-md-3"><a href="<?php echo APP_URL; ?>modules/sponsorships/index.php" class="sup-action"><div class="icon"><i class="fas fa-file-contract"></i></div><div><div class="title"><?php echo e(t('common.sponsorships')); ?></div><div class="hint"><?php echo e(t('dashboard.active_sponsorships')); ?></div></div></a></div><div class="col-md-3"><a href="<?php echo APP_URL; ?>modules/transactions/fina_payment_create.php" class="sup-action"><div class="icon"><i class="fas fa-hand-holding-dollar"></i></div><div><div class="title">تحصيل فينا الخير</div><div class="hint">تسجيل مبلغ مخصص 100% لفينا الخير</div></div></a></div></div>
<div class="card fade-in mb-4"><div class="card-header"><i class="fas fa-clock-rotate-left me-2"></i>سجل آخر التحصيلات التي أرسلتها</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>التاريخ</th><th>الكفيل</th><th>الفترة</th><th>طريقة التحصيل</th><th>المبلغ</th><th>الحالة</th></tr></thead><tbody><?php if(!$recentPayments): ?><tr><td colspan="6" class="text-center text-muted py-3">لا توجد تحصيلات سابقة.</td></tr><?php else: foreach($recentPayments as $rp): ?><tr><td><?php echo e(date('Y-m-d H:i',strtotime($rp['created_at']))); ?></td><td><?php echo e($rp['sponsor_name']); ?></td><td><?php echo e($rp['payment_period']??'—'); ?></td><td><?php echo e($methodLabels[$rp['payment_method']??'cash']??'نقدي'); ?></td><td><strong><?php echo number_format((float)$rp['amount'],2); ?></strong> ج.س</td><td><?php $st=$rp['status'];$stClass=$st==='approved'?'bg-success':($st==='returned'?'bg-danger':($st==='pending'?'bg-warning text-dark':'bg-secondary')); ?><span class="badge <?php echo $stClass; ?>"><?php echo e($statusLabels[$st]??$st); ?></span></td></tr><?php endforeach; endif; ?></tbody></table></div></div></div>
<?php if($myLetters): ?><div class="card fade-in"><div class="card-header"><i class="fas fa-font me-2"></i><?php echo e(t('dashboard.assigned_letters')); ?></div><div class="card-body"><?php foreach($myLetters as $L): ?><span class="badge bg-light text-dark border fs-6 me-2 px-3 py-2"><?php echo e($L['code']); ?></span><?php endforeach; ?></div></div><?php endif; ?>
<?php include __DIR__.'/../includes/footer.php'; ?>