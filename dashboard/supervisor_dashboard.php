<?php
// dashboard/supervisor_dashboard.php - Supervisor dashboard (orphan-level pivot)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';
Session::start();
if (!Session::isLoggedIn() || Session::getUserRole() !== 'supervisor') { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle = t('dashboard.supervisor_title');
$active = 'dashboard';
$uid = Session::getUserId();
$myLetters = dbFetchAll("SELECT l.code, l.name_ar FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id WHERE sl.supervisor_id = ? ORDER BY l.sort_order", [$uid]);
$scopeSql = "(s.supervisor_id = ? OR EXISTS (
    SELECT 1 FROM supervisor_letters sl
    JOIN letters l ON l.id = sl.letter_id
    WHERE sl.supervisor_id = ?
      AND sl.letter_id = s.first_letter_id
      AND l.gender = s.gender
))";
$s = dbFetchOne("SELECT
    (SELECT COUNT(*) FROM sponsors s WHERE $scopeSql) AS my_sponsors,
    (SELECT COUNT(*) FROM sponsorships sp JOIN sponsors s ON s.id = sp.sponsor_id WHERE sp.status = 'active' AND $scopeSql) AS my_active_sponsorships,
    (SELECT COUNT(DISTINCT sp.child_id) FROM sponsorships sp JOIN sponsors s ON s.id = sp.sponsor_id WHERE sp.status = 'active' AND $scopeSql) AS my_orphans,
    (SELECT COALESCE(SUM(amount),0) FROM sponsor_payments WHERE supervisor_id = ? AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')) AS month_collected",
    [$uid,$uid, $uid,$uid, $uid,$uid, $uid]);
include __DIR__ . '/../includes/header.php';
?>
<style>.sup-stat{border:none;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.05);transition:transform .2s}.sup-stat:hover{transform:translateY(-3px)}.sup-stat .v{font-size:2rem;font-weight:700;color:#1b4d8f}</style>
<div class="welcome-section fade-in"><h2><?php echo e(t('dashboard.supervisor_title')); ?></h2><p><?php echo e(t('dashboard.supervisor_intro')); ?></p><div class="quick-actions mt-3"><a href="<?php echo APP_URL; ?>modules/sponsors/index.php" class="btn btn-primary btn-sm"><i class="fas fa-users me-1"></i><?php echo e(t('common.sponsors')); ?></a><a href="<?php echo APP_URL; ?>modules/families/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-house me-1"></i><?php echo e(t('common.families')); ?></a><a href="<?php echo APP_URL; ?>modules/sponsorships/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-file-contract me-1"></i><?php echo e(t('common.sponsorships')); ?></a></div></div>
<?php include __DIR__ . '/../includes/alerts.php'; ?>
<div class="row g-3 mb-4 fade-in">
<div class="col-6 col-xl-3"><div class="card sup-stat text-center"><div class="card-body py-3"><div class="v"><?php echo (int)($s['my_sponsors'] ?? 0); ?></div><div class="text-muted small"><?php echo e(t('dashboard.my_sponsors')); ?></div></div></div></div>
<div class="col-6 col-xl-3"><div class="card sup-stat text-center"><div class="card-body py-3"><div class="v"><?php echo (int)($s['my_active_sponsorships'] ?? 0); ?></div><div class="text-muted small"><?php echo e(t('dashboard.active_sponsorships')); ?></div></div></div></div>
<div class="col-6 col-xl-3"><div class="card sup-stat text-center"><div class="card-body py-3"><div class="v"><?php echo (int)($s['my_orphans'] ?? 0); ?></div><div class="text-muted small"><?php echo e(t('dashboard.sponsored_orphans')); ?></div></div></div></div>
<div class="col-6 col-xl-3"><div class="card sup-stat text-center"><div class="card-body py-3"><div class="v"><?php echo number_format((float)($s['month_collected'] ?? 0),0); ?></div><div class="text-muted small"><?php echo e(t('dashboard.monthly_receipts')); ?></div></div></div></div>
</div>
<?php if ($myLetters): ?><div class="card fade-in"><div class="card-header"><i class="fas fa-font me-2"></i><?php echo e(t('dashboard.assigned_letters')); ?></div><div class="card-body"><?php foreach ($myLetters as $L): ?><span class="badge bg-light text-dark border fs-6 me-2 px-3 py-2"><?php echo e($L['code']); ?></span><?php endforeach; ?></div></div><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>