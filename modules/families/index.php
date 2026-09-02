<?php
// modules/families/index.php - Families list (scoped + prefix search + edit) — Orphan-level pivot
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'nanny'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle = 'الأسر'; $active = 'families';
$norm = function (string $s): string { $s = preg_replace('/[\x{064B}-\x{0652}\x{0640}\x{200B}-\x{200D}\x{FEFF}]/u', '', $s); $s = str_replace(['أ','إ','آ','ٱ'], 'ا', $s); $s = str_replace(['ة'], 'ه', $s); return mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)), 'UTF-8'); };
$q = trim($_GET['q'] ?? ''); $fStatus = trim($_GET['status'] ?? ''); $page = max(1, (int)($_GET['page'] ?? 1)); $perPage = 50; $nq = $norm($q);
$myIds = null; $uid = Session::getUserId();
if ($role === 'supervisor') {
    $myLetterIds = array_map('intval', array_column(dbFetchAll("SELECT letter_id FROM supervisor_letters WHERE supervisor_id = ?", [$uid]), 'letter_id'));
    $letters = dbFetchAll("SELECT id, code FROM letters"); $myIds = [];
    foreach (dbFetchAll("SELECT id, supervisor_id, legacy_mother_first_letter FROM families") as $f) {
        if ((int)$f['supervisor_id'] === $uid) { $myIds[] = (int)$f['id']; continue; }
        $lid = null; foreach ($letters as $L) { if ($L['code'] === (string)$f['legacy_mother_first_letter'] || normalize_arabic_letter($L['code']) === normalize_arabic_letter((string)$f['legacy_mother_first_letter'])) { $lid = (int)$L['id']; break; } }
        if ($lid !== null && in_array($lid, $myLetterIds, true)) $myIds[] = (int)$f['id'];
    }
}
if ($role === 'nanny') $myIds = array_map('intval', array_column(dbFetchAll("SELECT id FROM families WHERE nanny_id = ?", [$uid]), 'id'));
$all = dbFetchAll("SELECT f.id, f.family_code, f.mother_name, f.mother_phone, f.status, f.children_count, f.nanny_id,
    n.full_name AS nanny_name,
    (SELECT COUNT(*) FROM family_children fc WHERE fc.family_id = f.id) AS actual_children_count,
    (SELECT COUNT(*) FROM sponsorships sp WHERE sp.status = 'active' AND sp.child_id IN (SELECT fc.id FROM family_children fc WHERE fc.family_id = f.id)) AS active_sponsorships,
    (SELECT COALESCE(SUM(sp.monthly_amount),0) FROM sponsorships sp WHERE sp.status = 'active' AND sp.child_id IN (SELECT fc.id FROM family_children fc WHERE fc.family_id = f.id)) AS monthly_commitment
    FROM families f LEFT JOIN users n ON n.id = f.nanny_id");
$filtered = [];
foreach ($all as $row) {
    if ($myIds !== null && !in_array((int)$row['id'], $myIds, true)) continue;
    if ($fStatus !== '' && $row['status'] !== $fStatus) continue;
    if ($nq !== '') { $codeHay = mb_strtolower((string)$row['family_code'] . ' ' . (string)$row['mother_phone'], 'UTF-8'); if (!(str_starts_with($norm((string)$row['mother_name']), $nq) || mb_strpos($codeHay, $nq, 0, 'UTF-8') !== false)) continue; }
    $filtered[] = $row;
}
$total = count($filtered); $pages = max(1, (int)ceil($total / $perPage)); $page = min($page, $pages); $rows = array_slice($filtered, ($page - 1) * $perPage, $perPage); $qs = fn(array $extra) => APP_URL . 'modules/families/index.php?' . http_build_query(array_merge($_GET, $extra));
$canDeleteFamily = in_array($role, ['admin', 'vice_general_manager'], true);
include dirname(__DIR__, 2) . '/includes/header.php'; ?>
<div class="welcome-section fade-in"><h2>الأسر</h2><p><?php echo $total; ?> أسرة</p><div class="quick-actions mt-3"><?php if (in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)): ?><a href="<?php echo APP_URL; ?>modules/families/create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> إضافة أسرة</a><?php endif; ?></div></div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($role === 'nanny' && $total === 0): ?><div class="alert alert-info fade-in"><i class="fas fa-circle-info me-2"></i>لا توجد أسر معينة لك بعد. تواصل مع المدير لتعيين الأسر.</div><?php endif; ?>
<div class="card mb-3 fade-in"><div class="card-body"><form method="get" class="row g-2 align-items-end"><div class="col-md-6"><label class="form-label">بحث <small class="text-muted">(يطابق بداية اسم الأم)</small></label><input type="text" name="q" class="form-control" value="<?php echo e($q); ?>"></div><div class="col-md-3"><label class="form-label">الحالة</label><select name="status" class="form-select"><option value="">الكل</option><option value="active" <?php echo $fStatus === 'active' ? 'selected' : ''; ?>>نشطة</option><option value="pending" <?php echo $fStatus === 'pending' ? 'selected' : ''; ?>>معلقة</option><option value="paused" <?php echo $fStatus === 'paused' ? 'selected' : ''; ?>>متوقفة</option><option value="completed" <?php echo $fStatus === 'completed' ? 'selected' : ''; ?>>مكتملة</option><option value="archived" <?php echo $fStatus === 'archived' ? 'selected' : ''; ?>>مؤرشفة</option></select></div><div class="col-md-3"><button class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> بحث</button></div></form></div></div>
<div class="card fade-in"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>الكود</th><th>اسم الأم</th><th>الهاتف</th><th>الأيتام</th><th>كفالات نشطة</th><th>الالتزام الشهري</th><th>الأخصائية</th><th>الحالة</th><th class="text-center">إجراءات</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">لا توجد نتائج.</td></tr><?php else: foreach ($rows as $r): $st = ['active'=>['نشطة','bg-success'],'pending'=>['معلقة','bg-warning text-dark'],'paused'=>['متوقفة','bg-warning text-dark'],'completed'=>['مكتملة','bg-info'],'archived'=>['مؤرشفة','bg-secondary'],'inactive'=>['غير نشطة','bg-secondary'],'closed'=>['مغلقة','bg-dark']]; [$sl,$sc] = $st[$r['status']] ?? [$r['status'],'bg-secondary']; $childCount = (int)$r['actual_children_count']; ?>
<tr><td><?php echo e($r['family_code']); ?></td><td><strong><?php echo e($r['mother_name']); ?></strong></td><td dir="ltr"><?php echo e($r['mother_phone'] ?? '-'); ?></td><td><?php echo $childCount; ?></td><td><?php echo (int)$r['active_sponsorships']; ?></td><td><?php echo number_format((float)$r['monthly_commitment'], 0); ?></td><td><?php echo e($r['nanny_name'] ?? '—'); ?></td><td><span class="badge <?php echo $sc; ?>"><?php echo $sl; ?></span></td><td class="text-center" style="white-space:nowrap;"><a class="btn btn-sm btn-primary" title="عرض" href="<?php echo APP_URL; ?>modules/families/view.php?id=<?php echo (int)$r['id']; ?>"><i class="fas fa-eye"></i></a><?php if ($role !== 'general_manager'): ?><a class="btn btn-sm btn-warning" title="تعديل" href="<?php echo APP_URL; ?>modules/families/edit.php?id=<?php echo (int)$r['id']; ?>"><i class="fas fa-pen"></i></a><?php endif; ?><?php if ($canDeleteFamily && $childCount === 0): ?><button type="button" class="btn btn-sm btn-danger" title="حذف الأسرة" data-bs-toggle="modal" data-bs-target="#deleteFamilyModal" data-family-id="<?php echo (int)$r['id']; ?>" data-family-code="<?php echo e($r['family_code']); ?>" data-mother-name="<?php echo e($r['mother_name']); ?>"><i class="fas fa-trash"></i></button><?php endif; ?></td></tr>
<?php endforeach; endif; ?></tbody></table></div></div></div>
<?php if ($pages > 1): ?><nav class="mt-3 mb-4"><ul class="pagination justify-content-center"><?php for ($i=1;$i<=$pages;$i++): ?><li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="<?php echo e($qs(['page'=>$i])); ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
<?php if ($canDeleteFamily): ?>
<div class="modal fade" id="deleteFamilyModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" dir="rtl"><div class="modal-header"><h5 class="modal-title text-danger"><i class="fas fa-triangle-exclamation me-2"></i>تأكيد حذف الأسرة</h5><button type="button" class="btn-close ms-0" data-bs-dismiss="modal" aria-label="إغلاق"></button></div><div class="modal-body"><p class="mb-2">هل أنت متأكد من حذف هذه الأسرة؟</p><div class="alert alert-warning mb-0"><strong id="deleteFamilyLabel"></strong><br><small>لا يوجد لها أي يتيم مسجل حالياً. سيتم التحقق مرة أخرى من الخادم قبل الحذف.</small></div></div><div class="modal-footer"><form method="post" action="<?php echo APP_URL; ?>modules/families/delete.php" class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="family_id" id="deleteFamilyId" value=""><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i> نعم، حذف الأسرة</button></form></div></div></div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('deleteFamilyModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        if (!button) return;
        document.getElementById('deleteFamilyId').value = button.getAttribute('data-family-id') || '';
        document.getElementById('deleteFamilyLabel').textContent = (button.getAttribute('data-family-code') || '') + ' — ' + (button.getAttribute('data-mother-name') || '');
    });
});
</script>
<?php endif; ?>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>