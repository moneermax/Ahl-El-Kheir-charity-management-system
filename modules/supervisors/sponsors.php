<<<<<<< HEAD
<?php
// modules/supervisors/sponsors.php - Sponsors of one supervisor (letter + GENDER aware)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
// ---- Access guard ----
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$pageTitle = 'كفلاء المشرف';
$active    = 'supervisors';

/* ---------- gender helper ---------- */
function ak_norm_gender($raw): string {
    $v = strtolower(trim((string)$raw));
    if (in_array($v, ['female', 'f', 'أنثى', 'انثى'], true)) return 'female';
    if (in_array($v, ['male', 'm', 'ذكر'], true)) return 'male';
    return '';
}
$G_LABEL = ['male' => 'ذكر', 'female' => 'أنثى', '' => '—'];

$id = (int)($_GET['supervisor'] ?? 0);
if ($role === 'supervisor') { $id = Session::getUserId(); } // supervisor can only view his own

$sup = dbFetchOne("
    SELECT u.id, u.full_name
    FROM users u JOIN roles r ON r.id = u.role_id
    WHERE u.id = ? AND r.code = 'supervisor'
", [$id]);
if (!$sup) {
    flash('error', 'المشرف غير موجود.');
    redirect('modules/supervisors/index.php');
}

/* ---------- Supervisor's letters per gender (chips) ---------- */
$letterRows = dbFetchAll("
    SELECT l.id, l.code, sl.gender
    FROM supervisor_letters sl
    JOIN letters l ON l.id = sl.letter_id
    WHERE sl.supervisor_id = ?
    ORDER BY l.sort_order
", [$id]);
$letterChips = [];
foreach ($letterRows as $r) {
    $g = ak_norm_gender($r['gender']);
    $suffix = ($g === 'male') ? '(ذ)' : (($g === 'female') ? '(إ)' : '');
    $letterChips[] = $r['code'] . $suffix;
}

/* ---------- Session-6 FIX: global letter+gender ownership matrix ---------- */
$letterNormById = [];
foreach (dbFetchAll("SELECT id, code FROM letters") as $L) $letterNormById[(int)$L['id']] = normalize_arabic_letter($L['code']);

$ownByNorm = []; // normalized letter => ['male'=>supId, 'female'=>supId]
foreach (dbFetchAll("SELECT sl.supervisor_id, sl.gender, l.code FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id") as $mr) {
    $n = normalize_arabic_letter($mr['code']);
    $g = ak_norm_gender($mr['gender']);
    if ($g === '') { $ownByNorm[$n]['male'] = (int)$mr['supervisor_id']; $ownByNorm[$n]['female'] = (int)$mr['supervisor_id']; }
    else { $ownByNorm[$n][$g] = (int)$mr['supervisor_id']; }
}

/* ---------- Load all sponsors; matrix ownership first, Rule-1 fallback only for unowned letters ---------- */
$all = dbFetchAll("
    SELECT s.id, s.sponsor_code, s.full_name, s.first_letter_raw, s.first_letter_id, s.supervisor_id,
           s.phone, s.sponsor_type, s.status, s.gender,
           l.code AS sponsor_letter,
           (SELECT COUNT(*) FROM sponsorships sp WHERE sp.sponsor_id = s.id AND sp.status = 'active') AS active_sponsorships
    FROM sponsors s
    LEFT JOIN letters l ON l.id = s.first_letter_id
    ORDER BY s.full_name
");
$sponsors = [];
foreach ($all as $row) {
    $sg = ak_norm_gender($row['gender'] ?? '');

    // normalized letter: id -> raw -> name
    $norm = '';
    if ($row['first_letter_id'] !== null) $norm = $letterNormById[(int)$row['first_letter_id']] ?? '';
    if ($norm === '') [, $norm] = first_letter_of((string)($row['first_letter_raw'] ?? ''));
    if ($norm === '') [, $norm] = first_letter_of((string)$row['full_name']);

    $genders = ($sg !== '') ? [$sg] : ['male', 'female'];
    $owners = [];
    if ($norm !== '') {
        foreach ($genders as $g) {
            $o = $ownByNorm[$norm][$g] ?? null;
            if ($o) $owners[] = $o;
        }
    }

    if ($owners) {
        // Letter+gender owned in the matrix: only the CURRENT holder(s) see this sponsor
        if (in_array($id, $owners, true)) $sponsors[] = $row;
        continue;
    }
    // Letter not in the matrix: Rule-1 explicit override applies (badged «مباشر»)
    if ($id > 0 && (int)($row['supervisor_id'] ?? 0) === $id) {
        $row['ak_direct'] = 1;
        $sponsors[] = $row;
    }
}
$total       = count($sponsors);
$activeCount = count(array_filter($sponsors, fn($r) => $r['status'] === 'active'));
$commit = ['total' => 0];
if ($total) {
    $ids = array_map(fn($r) => (int)$r['id'], $sponsors);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $commit = dbFetchOne(
        "SELECT COALESCE(SUM(monthly_amount), 0) AS total
         FROM sponsorships
         WHERE status = 'active' AND sponsor_id IN ($ph)",
        $ids
    );
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>كفلاء المشرف: <?php echo e($sup['full_name']); ?></h2>
    <p>
        الحروف + الجنس:
        <?php if ($letterChips): foreach ($letterChips as $L): ?>
            <span class="badge bg-light text-dark border"><?php echo e($L); ?></span>
        <?php endforeach; else: echo '— لا حروف —'; endif; ?>
    </p>
    <div class="quick-actions mt-3">
        <a href="<?php echo APP_URL; ?>modules/supervisors/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-right me-1"></i> رجوع للمشرفين</a>
        <a href="<?php echo APP_URL; ?>modules/supervisors/edit.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-pen me-1"></i> تعديل المشرف</a>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="row g-4 mb-4 fade-in">
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body">
            <div class="fs-3 fw-bold" style="color:#1b4d8f"><?php echo $total; ?></div>
            <div class="text-muted small">إجمالي الكفلاء</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body">
            <div class="fs-3 fw-bold" style="color:#1b4d8f"><?php echo $activeCount; ?></div>
            <div class="text-muted small">كفلاء نشطون</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body">
            <div class="fs-3 fw-bold" style="color:#1b4d8f"><?php echo number_format((float)($commit['total'] ?? 0), 0); ?></div>
            <div class="text-muted small">الالتزام الشهري (الكفالات النشطة)</div>
        </div></div>
    </div>
</div>
<div class="card fade-in">
    <div class="card-header"><i class="fas fa-hand-holding-heart me-2"></i>قائمة الكفلاء (<?php echo $total; ?>)</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>الكود</th><th>الاسم</th><th>الحرف</th><th>الجنس</th><th>الهاتف</th><th>النوع</th><th>كفالات نشطة</th><th>الحالة</th><th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$sponsors): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">لا يوجد كفلاء لهذا المشرف بعد.</td></tr>
                <?php else: foreach ($sponsors as $r): ?>
                    <tr>
                        <td><?php echo e($r['sponsor_code']); ?></td>
                        <td>
                            <strong><?php echo e($r['full_name']); ?></strong>
                            <?php if (!empty($r['ak_direct'])): ?><span class="badge bg-info" title="تعيين مباشر — خارج مصفوفة الحروف">مباشر</span><?php endif; ?>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?php echo e($r['sponsor_letter'] ?? '-'); ?></span></td>
                        <td><?php echo e($G_LABEL[ak_norm_gender($r['gender'] ?? '')]); ?></td>
                        <td><?php echo e($r['phone'] ?? '-'); ?></td>
                        <td><?php echo e($r['sponsor_type']); ?></td>
                        <td><?php echo (int)$r['active_sponsorships']; ?></td>
                        <td><?php echo $r['status'] === 'active' ? '<span class="badge bg-success">نشط</span>' : '<span class="badge bg-secondary">موقوف</span>'; ?></td>
                        <td>
                            <a class="btn btn-sm btn-primary" title="عرض" href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo (int)$r['id']; ?>">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
=======
<?php
// modules/supervisors/sponsors.php - Sponsors of one supervisor (letter + GENDER aware)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
// ---- Access guard ----
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$pageTitle = 'كفلاء المشرف';
$active    = 'supervisors';

/* ---------- gender helper ---------- */
function ak_norm_gender($raw): string {
    $v = strtolower(trim((string)$raw));
    if (in_array($v, ['female', 'f', 'أنثى', 'انثى'], true)) return 'female';
    if (in_array($v, ['male', 'm', 'ذكر'], true)) return 'male';
    return '';
}
$G_LABEL = ['male' => 'ذكر', 'female' => 'أنثى', '' => '—'];

$id = (int)($_GET['supervisor'] ?? 0);
if ($role === 'supervisor') { $id = Session::getUserId(); } // supervisor can only view his own

$sup = dbFetchOne("
    SELECT u.id, u.full_name
    FROM users u JOIN roles r ON r.id = u.role_id
    WHERE u.id = ? AND r.code = 'supervisor'
", [$id]);
if (!$sup) {
    flash('error', 'المشرف غير موجود.');
    redirect('modules/supervisors/index.php');
}

/* ---------- Supervisor's letters per gender (chips) ---------- */
$letterRows = dbFetchAll("
    SELECT l.id, l.code, sl.gender
    FROM supervisor_letters sl
    JOIN letters l ON l.id = sl.letter_id
    WHERE sl.supervisor_id = ?
    ORDER BY l.sort_order
", [$id]);
$letterChips = [];
foreach ($letterRows as $r) {
    $g = ak_norm_gender($r['gender']);
    $suffix = ($g === 'male') ? '(ذ)' : (($g === 'female') ? '(إ)' : '');
    $letterChips[] = $r['code'] . $suffix;
}

/* ---------- Session-6 FIX: global letter+gender ownership matrix ---------- */
$letterNormById = [];
foreach (dbFetchAll("SELECT id, code FROM letters") as $L) $letterNormById[(int)$L['id']] = normalize_arabic_letter($L['code']);

$ownByNorm = []; // normalized letter => ['male'=>supId, 'female'=>supId]
foreach (dbFetchAll("SELECT sl.supervisor_id, sl.gender, l.code FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id") as $mr) {
    $n = normalize_arabic_letter($mr['code']);
    $g = ak_norm_gender($mr['gender']);
    if ($g === '') { $ownByNorm[$n]['male'] = (int)$mr['supervisor_id']; $ownByNorm[$n]['female'] = (int)$mr['supervisor_id']; }
    else { $ownByNorm[$n][$g] = (int)$mr['supervisor_id']; }
}

/* ---------- Load all sponsors; matrix ownership first, Rule-1 fallback only for unowned letters ---------- */
$all = dbFetchAll("
    SELECT s.id, s.sponsor_code, s.full_name, s.first_letter_raw, s.first_letter_id, s.supervisor_id,
           s.phone, s.sponsor_type, s.status, s.gender,
           l.code AS sponsor_letter,
           (SELECT COUNT(*) FROM sponsorships sp WHERE sp.sponsor_id = s.id AND sp.status = 'active') AS active_sponsorships
    FROM sponsors s
    LEFT JOIN letters l ON l.id = s.first_letter_id
    ORDER BY s.full_name
");
$sponsors = [];
foreach ($all as $row) {
    $sg = ak_norm_gender($row['gender'] ?? '');

    // normalized letter: id -> raw -> name
    $norm = '';
    if ($row['first_letter_id'] !== null) $norm = $letterNormById[(int)$row['first_letter_id']] ?? '';
    if ($norm === '') [, $norm] = first_letter_of((string)($row['first_letter_raw'] ?? ''));
    if ($norm === '') [, $norm] = first_letter_of((string)$row['full_name']);

    $genders = ($sg !== '') ? [$sg] : ['male', 'female'];
    $owners = [];
    if ($norm !== '') {
        foreach ($genders as $g) {
            $o = $ownByNorm[$norm][$g] ?? null;
            if ($o) $owners[] = $o;
        }
    }

    if ($owners) {
        // Letter+gender owned in the matrix: only the CURRENT holder(s) see this sponsor
        if (in_array($id, $owners, true)) $sponsors[] = $row;
        continue;
    }
    // Letter not in the matrix: Rule-1 explicit override applies (badged «مباشر»)
    if ($id > 0 && (int)($row['supervisor_id'] ?? 0) === $id) {
        $row['ak_direct'] = 1;
        $sponsors[] = $row;
    }
}
$total       = count($sponsors);
$activeCount = count(array_filter($sponsors, fn($r) => $r['status'] === 'active'));
$commit = ['total' => 0];
if ($total) {
    $ids = array_map(fn($r) => (int)$r['id'], $sponsors);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $commit = dbFetchOne(
        "SELECT COALESCE(SUM(monthly_amount), 0) AS total
         FROM sponsorships
         WHERE status = 'active' AND sponsor_id IN ($ph)",
        $ids
    );
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>كفلاء المشرف: <?php echo e($sup['full_name']); ?></h2>
    <p>
        الحروف + الجنس:
        <?php if ($letterChips): foreach ($letterChips as $L): ?>
            <span class="badge bg-light text-dark border"><?php echo e($L); ?></span>
        <?php endforeach; else: echo '— لا حروف —'; endif; ?>
    </p>
    <div class="quick-actions mt-3">
        <a href="<?php echo APP_URL; ?>modules/supervisors/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-right me-1"></i> رجوع للمشرفين</a>
        <a href="<?php echo APP_URL; ?>modules/supervisors/edit.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-pen me-1"></i> تعديل المشرف</a>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="row g-4 mb-4 fade-in">
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body">
            <div class="fs-3 fw-bold" style="color:#1b4d8f"><?php echo $total; ?></div>
            <div class="text-muted small">إجمالي الكفلاء</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body">
            <div class="fs-3 fw-bold" style="color:#1b4d8f"><?php echo $activeCount; ?></div>
            <div class="text-muted small">كفلاء نشطون</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card text-center"><div class="card-body">
            <div class="fs-3 fw-bold" style="color:#1b4d8f"><?php echo number_format((float)($commit['total'] ?? 0), 0); ?></div>
            <div class="text-muted small">الالتزام الشهري (الكفالات النشطة)</div>
        </div></div>
    </div>
</div>
<div class="card fade-in">
    <div class="card-header"><i class="fas fa-hand-holding-heart me-2"></i>قائمة الكفلاء (<?php echo $total; ?>)</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>الكود</th><th>الاسم</th><th>الحرف</th><th>الجنس</th><th>الهاتف</th><th>النوع</th><th>كفالات نشطة</th><th>الحالة</th><th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$sponsors): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">لا يوجد كفلاء لهذا المشرف بعد.</td></tr>
                <?php else: foreach ($sponsors as $r): ?>
                    <tr>
                        <td><?php echo e($r['sponsor_code']); ?></td>
                        <td>
                            <strong><?php echo e($r['full_name']); ?></strong>
                            <?php if (!empty($r['ak_direct'])): ?><span class="badge bg-info" title="تعيين مباشر — خارج مصفوفة الحروف">مباشر</span><?php endif; ?>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?php echo e($r['sponsor_letter'] ?? '-'); ?></span></td>
                        <td><?php echo e($G_LABEL[ak_norm_gender($r['gender'] ?? '')]); ?></td>
                        <td><?php echo e($r['phone'] ?? '-'); ?></td>
                        <td><?php echo e($r['sponsor_type']); ?></td>
                        <td><?php echo (int)$r['active_sponsorships']; ?></td>
                        <td><?php echo $r['status'] === 'active' ? '<span class="badge bg-success">نشط</span>' : '<span class="badge bg-secondary">موقوف</span>'; ?></td>
                        <td>
                            <a class="btn btn-sm btn-primary" title="عرض" href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo (int)$r['id']; ?>">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>