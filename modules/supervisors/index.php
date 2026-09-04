<?php
// modules/supervisors/index.php - Supervisors list (Admin + VGM)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/supervisor_lifecycle.php';
Session::start();

if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }

$showArchived = isset($_GET['show_archived']) && $_GET['show_archived'] === '1';
$statusFilter = $showArchived ? "" : "AND COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) NOT IN ('departed', 'archived')";

$supervisors = dbFetchAll("SELECT u.id, u.username, u.full_name, u.phone, u.email, u.is_active,
    COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) AS supervisor_status,
    u.last_login_at,
    (SELECT GROUP_CONCAT(CONCAT(l.code, CASE sl.gender WHEN 'male' THEN '(ذ)' WHEN 'female' THEN '(إ)' ELSE '' END) ORDER BY l.sort_order SEPARATOR '، ')
     FROM supervisor_letters sl INNER JOIN letters l ON l.id = sl.letter_id WHERE sl.supervisor_id = u.id) AS letters,
    0 AS sponsor_count, 0 AS family_count
    FROM users u INNER JOIN roles r ON r.id = u.role_id
    WHERE r.code = 'supervisor' {$statusFilter} ORDER BY u.full_name");

/* Workload ownership is calculated with the same current letter/gender rules used by sponsors.php and families/index.php. */
$letterNormById = [];
foreach (dbFetchAll("SELECT id, code FROM letters") as $letter) {
    $letterNormById[(int)$letter['id']] = normalize_arabic_letter($letter['code']);
}

$sponsorOwners = [];
$familyLetterOwners = [];
foreach (dbFetchAll("SELECT sl.supervisor_id, sl.gender, l.code FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id") as $assignment) {
    $norm = normalize_arabic_letter($assignment['code']);
    if ($norm === '') continue;
    $sid = (int)$assignment['supervisor_id'];
    $gender = strtolower(trim((string)$assignment['gender']));
    if (in_array($gender, ['أنثى', 'انثى', 'f'], true)) $gender = 'female';
    elseif (in_array($gender, ['ذكر', 'm'], true)) $gender = 'male';
    if ($gender === 'male' || $gender === 'female') $sponsorOwners[$norm][$gender] = $sid;
    else { $sponsorOwners[$norm]['male'] = $sid; $sponsorOwners[$norm]['female'] = $sid; }
    $familyLetterOwners[$norm] = $sid;
}

$sponsorCounts = [];
foreach (dbFetchAll("SELECT id, first_letter_raw, first_letter_id, supervisor_id, gender, full_name FROM sponsors") as $sponsor) {
    $norm = '';
    if ($sponsor['first_letter_id'] !== null) $norm = $letterNormById[(int)$sponsor['first_letter_id']] ?? '';
    if ($norm === '') [, $norm] = first_letter_of((string)($sponsor['first_letter_raw'] ?? ''));
    if ($norm === '') [, $norm] = first_letter_of((string)$sponsor['full_name']);
    $gender = strtolower(trim((string)($sponsor['gender'] ?? '')));
    if (in_array($gender, ['أنثى', 'انثى', 'f'], true)) $gender = 'female';
    elseif (in_array($gender, ['ذكر', 'm'], true)) $gender = 'male';
    else $gender = '';
    $genders = $gender !== '' ? [$gender] : ['male', 'female'];
    $owners = [];
    foreach ($genders as $g) { if (!empty($sponsorOwners[$norm][$g])) $owners[] = (int)$sponsorOwners[$norm][$g]; }
    if ($owners) {
        foreach (array_unique($owners) as $ownerId) $sponsorCounts[$ownerId] = ($sponsorCounts[$ownerId] ?? 0) + 1;
    } elseif ((int)($sponsor['supervisor_id'] ?? 0) > 0) {
        $ownerId = (int)$sponsor['supervisor_id']; $sponsorCounts[$ownerId] = ($sponsorCounts[$ownerId] ?? 0) + 1;
    }
}

$familyCounts = [];
foreach (dbFetchAll("SELECT supervisor_id, legacy_mother_first_letter FROM families") as $family) {
    $ownerId = (int)($family['supervisor_id'] ?? 0);
    if ($ownerId <= 0) {
        $norm = normalize_arabic_letter((string)($family['legacy_mother_first_letter'] ?? ''));
        $ownerId = (int)($familyLetterOwners[$norm] ?? 0);
    }
    if ($ownerId > 0) $familyCounts[$ownerId] = ($familyCounts[$ownerId] ?? 0) + 1;
}

foreach ($supervisors as &$supervisor) {
    $sid = (int)$supervisor['id'];
    $supervisor['sponsor_count'] = $sponsorCounts[$sid] ?? 0;
    $supervisor['family_count'] = $familyCounts[$sid] ?? 0;
}
unset($supervisor);

$pageTitle = 'إدارة المشرفين';
$active = 'supervisors';
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.supervisor-management-table { font-size: 13px; }
.supervisor-management-table thead th { padding: 9px 10px; white-space: nowrap; }
.supervisor-management-table tbody td { padding: 8px 10px; vertical-align: middle; }
.supervisor-management-table .supervisor-name { min-width: 180px; }
.supervisor-management-table .supervisor-actions { min-width: 220px; width: 220px; }
.supervisor-management-table .supervisor-actions .btn { width:31px;height:31px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:7px; }
.supervisor-management-table .supervisor-actions form { display:inline-flex;margin:0; }
.supervisor-management-table .badge { font-size:11px;padding:4px 8px; }
.supervisor-management-table .workload-link { font-weight:700;text-decoration:none; }
.supervisor-management-table .workload-cell { white-space:nowrap; }
.supervisor-management-table .letters-cell { min-width:125px; }
</style>
<div class="welcome-section fade-in">
    <h2>إدارة المشرفين</h2>
    <p>إدارة المشرفين، توزيع الحروف، متابعة أعباء العمل، وإدارة دورة حياة المشرف.</p>
    <div class="quick-actions mt-3">
        <a href="<?php echo APP_URL; ?>modules/supervisors/create.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus me-1"></i> إضافة مشرف</a>
        <a href="<?php echo APP_URL; ?>modules/supervisors/assign-letters.php" class="btn btn-secondary btn-sm"><i class="fas fa-font me-1"></i> توزيع الحروف</a>
        <a href="<?php echo APP_URL; ?>modules/supervisors/reassign-letters.php" class="btn btn-warning btn-sm"><i class="fas fa-right-left me-1"></i> إعادة تعيين الحروف المحررة</a>
        <a href="<?php echo APP_URL; ?>modules/supervisors/index.php?show_archived=<?php echo $showArchived ? '0' : '1'; ?>" class="btn btn-outline-dark btn-sm"><i class="fas <?php echo $showArchived ? 'fa-user-check' : 'fa-box-archive'; ?> me-1"></i><?php echo $showArchived ? 'إخفاء المؤرشفين' : 'عرض المؤرشفين'; ?></a>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="alert alert-info fade-in py-2 mb-3"><i class="fas fa-circle-info me-1"></i><strong>دورة الحياة:</strong> الإيقاف المؤقت لا يعني المغادرة النهائية. المغادرة النهائية تحفظ السجل التاريخي وتحرر الكفلاء والحروف لإعادة التوزيع اليدوي.<?php if (!$showArchived): ?> <span class="text-muted">المشرفون المغادرون/المؤرشفون مخفيون من قائمة العمل ويمكن عرضهم من زر «عرض المؤرشفين».</span><?php endif; ?></div>
<div class="card fade-in">
    <div class="card-header py-3 d-flex justify-content-between align-items-center"><span><i class="fas fa-user-tie me-2"></i><?php echo $showArchived ? 'المشرفون المؤرشفون وسجل المشرفين' : 'المشرفون الحاليون'; ?></span><span class="badge bg-primary"><?php echo count($supervisors); ?> مشرف</span></div>
    <div class="card-body p-2 p-md-3"><div class="table-responsive"><table class="table table-hover align-middle mb-0 supervisor-management-table"><thead><tr><th class="text-center" style="width:42px">#</th><th>المشرف</th><th class="letters-cell">الحروف</th><th class="text-center">الأعباء</th><th class="text-center">الحالة</th><th class="text-center supervisor-actions">الإجراءات</th></tr></thead><tbody>
    <?php if (empty($supervisors)): ?><tr><td colspan="6" class="text-center text-muted py-5"><i class="fas fa-users-slash fa-2x mb-2 d-block"></i><?php echo $showArchived ? 'لا يوجد مشرفون مؤرشفون.' : 'لا يوجد مشرفون حاليون.'; ?></td></tr>
    <?php else: foreach ($supervisors as $i => $sup): $status = (string)$sup['supervisor_status']; $isFinal = supervisorLifecycleIsFinal($status); $statusClass = match ($status) { 'active'=>'bg-success','on_leave'=>'bg-warning text-dark','returning'=>'bg-info text-dark','suspended'=>'bg-danger','departed','archived'=>'bg-dark',default=>'bg-secondary' }; ?>
    <tr>
        <td class="text-center text-muted"><?php echo $i + 1; ?></td>
        <td class="supervisor-name"><strong><?php echo e($sup['full_name']); ?></strong><small class="d-block text-muted mt-1">@<?php echo e($sup['username']); ?></small></td>
        <td class="letters-cell"><?php if (!empty($sup['letters'])): foreach (explode('، ', $sup['letters']) as $L): ?><span class="badge bg-light text-dark border me-1 mb-1"><?php echo e($L); ?></span><?php endforeach; else: ?><span class="text-muted">—</span><?php endif; ?></td>
        <td class="text-center workload-cell"><a class="workload-link" href="<?php echo APP_URL; ?>modules/supervisors/sponsors.php?supervisor=<?php echo (int)$sup['id']; ?>" title="الكفلاء المرتبطون"><i class="fas fa-hand-holding-heart text-muted"></i> <?php echo (int)$sup['sponsor_count']; ?></a><span class="text-muted mx-1">·</span><span title="الأسر"><i class="fas fa-house-user text-muted"></i> <?php echo (int)$sup['family_count']; ?></span></td>
        <td class="text-center"><span class="badge <?php echo $statusClass; ?>"><?php echo e(supervisorLifecycleStatusLabel($status)); ?></span></td>
        <td class="text-center supervisor-actions"><div class="d-inline-flex flex-wrap justify-content-center gap-1">
            <a class="btn btn-sm btn-primary" title="الكفلاء" aria-label="الكفلاء" href="<?php echo APP_URL; ?>modules/supervisors/sponsors.php?supervisor=<?php echo (int)$sup['id']; ?>"><i class="fas fa-hand-holding-heart"></i></a>
            <?php if (!$isFinal): ?>
                <a class="btn btn-sm btn-warning" title="تعديل" aria-label="تعديل" href="<?php echo APP_URL; ?>modules/supervisors/edit.php?id=<?php echo (int)$sup['id']; ?>"><i class="fas fa-pen"></i></a>
                <a class="btn btn-sm btn-info" title="الحروف" aria-label="الحروف" href="<?php echo APP_URL; ?>modules/supervisors/assign-letters.php?supervisor=<?php echo (int)$sup['id']; ?>"><i class="fas fa-font"></i></a>
                <?php if (in_array($status, ['active','suspended'], true)): ?><form method="post" action="<?php echo APP_URL; ?>modules/users/supervisor_status.php" data-confirm="<?php echo $status === 'active' ? 'هل تريد إيقاف هذا المشرف مؤقتاً؟' : 'هل تريد إعادة تفعيل هذا المشرف؟'; ?>"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int)$sup['id']; ?>"><button type="submit" class="btn btn-sm <?php echo $status === 'active' ? 'btn-danger' : 'btn-success'; ?>" title="<?php echo $status === 'active' ? 'إيقاف مؤقت' : 'تفعيل'; ?>" aria-label="<?php echo $status === 'active' ? 'إيقاف مؤقت' : 'تفعيل'; ?>"><i class="fas <?php echo $status === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i></button></form><?php endif; ?>
                <a class="btn btn-sm btn-outline-danger" title="مغادرة نهائية" aria-label="مغادرة نهائية" href="<?php echo APP_URL; ?>modules/users/supervisor_departure.php?id=<?php echo (int)$sup['id']; ?>"><i class="fas fa-user-slash"></i></a>
            <?php else: ?><a class="btn btn-sm btn-success" title="إعادة المشرف إلى العمل" aria-label="إعادة المشرف إلى العمل" href="<?php echo APP_URL; ?>modules/supervisors/return.php?id=<?php echo (int)$sup['id']; ?>"><i class="fas fa-user-rotate"></i></a><span class="badge bg-dark align-self-center">نهائي</span><?php endif; ?>
        </div></td>
    </tr>
    <?php endforeach; endif; ?></tbody></table></div></div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
