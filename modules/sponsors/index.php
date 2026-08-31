<<<<<<< HEAD
<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'supervisor'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

dbExecute("ALTER TABLE sponsors ADD COLUMN IF NOT EXISTS brought_by_name VARCHAR(255) NULL");

$pageTitle = 'الكفلاء';
$active = 'sponsors';
$q = trim($_GET['q'] ?? '');
$fStatus = trim($_GET['status'] ?? '');
$fSup = (int)($_GET['sup'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$supervisors = dbFetchAll(
    "SELECT u.id, u.full_name
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE r.code IN ('supervisor', 'admin', 'vice_general_manager')
     ORDER BY u.full_name"
);

$sql = "SELECT
            s.id,
            COALESCE(
                NULLIF(TRIM(s.sponsor_code), ''),
                CONCAT('SP-', LPAD(s.id, 6, '0'))
            ) AS sponsor_code,
            s.full_name,
            s.phone,
            s.status,
            s.gender
        FROM sponsors s
        WHERE 1=1";
$params = [];

/*
 * Supervisor scope:
 * 1. Keep sponsors explicitly assigned to the current supervisor.
 * 2. Also include sponsors whose first letter is assigned to the current
 *    supervisor with a matching gender. A blank/both matrix gender means
 *    that the letter is unrestricted by gender.
 */
if ($role === 'supervisor') {
    $uid = Session::getUserId();
    $matrixRows = dbFetchAll(
        "SELECT letter_id, gender
         FROM supervisor_letters
         WHERE supervisor_id = ?",
        [$uid]
    );

    $scopeParts = ['s.supervisor_id = ?'];
    $scopeParams = [$uid];
    $letterGenderMap = [];

    foreach ($matrixRows as $matrixRow) {
        $letterId = (int)$matrixRow['letter_id'];
        $rawGender = strtolower(trim((string)($matrixRow['gender'] ?? '')));

        if ($rawGender === 'male' || $rawGender === 'm' || $rawGender === 'ذكر') {
            $gender = 'male';
        } elseif ($rawGender === 'female' || $rawGender === 'f' || $rawGender === 'أنثى' || $rawGender === 'انثى') {
            $gender = 'female';
        } else {
            /* Empty, both, or legacy values mean no gender restriction. */
            $gender = 'both';
        }

        $letterGenderMap[$letterId][] = $gender;
    }

    foreach ($letterGenderMap as $letterId => $genders) {
        $genders = array_values(array_unique($genders));

        if (in_array('both', $genders, true)) {
            $scopeParts[] = 's.first_letter_id = ?';
            $scopeParams[] = $letterId;
            continue;
        }

        $genderPlaceholders = implode(',', array_fill(0, count($genders), '?'));
        $scopeParts[] = "(s.first_letter_id = ? AND s.gender IN ($genderPlaceholders))";
        $scopeParams[] = $letterId;
        foreach ($genders as $gender) $scopeParams[] = $gender;
    }

    $sql .= ' AND (' . implode(' OR ', $scopeParts) . ')';
    $params = array_merge($params, $scopeParams);
} elseif ($fSup > 0) {
    $sql .= " AND s.supervisor_id = ?";
    $params[] = $fSup;
}

if ($fStatus !== '') {
    $sql .= " AND s.status = ?";
    $params[] = $fStatus;
}

if ($q !== '') {
    $sql .= " AND (s.full_name LIKE ? OR s.sponsor_code LIKE ? OR s.phone LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$sql .= " ORDER BY s.id DESC";
$all = dbFetchAll($sql, $params);

$total = count($all);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$rows = array_slice($all, ($page - 1) * $perPage, $perPage);

$qs = fn(array $extra) => APP_URL . 'modules/sponsors/index.php?' . http_build_query(array_merge($_GET, $extra));

/* Preserve the current search/filter/page state when opening a sponsor. */
$returnParams = [];
if ($q !== '') $returnParams['q'] = $q;
if ($fStatus !== '') $returnParams['status'] = $fStatus;
if ($fSup > 0) $returnParams['sup'] = $fSup;
$returnParams['page'] = $page;
$returnQuery = http_build_query($returnParams);
$returnUrl = rawurlencode($returnQuery);

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2>الكفلاء</h2>
    <p><?php echo $total; ?> كفيل</p>
    <div class="quick-actions mt-3">
        <?php if (in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)): ?>
            <a href="<?php echo APP_URL; ?>modules/sponsors/create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> إضافة كفيل</a>
            <a href="<?php echo APP_URL; ?>modules/sponsors/requests.php" class="btn btn-info btn-sm text-white"><i class="fas fa-bullhorn me-1"></i> طلبات الاستقطاب</a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3 fade-in">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label">بحث</label><input type="text" name="q" class="form-control" value="<?php echo e($q); ?>" placeholder="اسم / كود / هاتف"></div>
            <div class="col-md-2"><label class="form-label">الحالة</label><select name="status" class="form-select"><option value="">الكل</option><option value="active" <?php echo $fStatus === 'active' ? 'selected' : ''; ?>>نشط</option><option value="inactive" <?php echo $fStatus === 'inactive' ? 'selected' : ''; ?>>غير نشط</option><option value="suspended" <?php echo $fStatus === 'suspended' ? 'selected' : ''; ?>>موقوف</option><option value="cancelled" <?php echo $fStatus === 'cancelled' ? 'selected' : ''; ?>>ملغي</option></select></div>
            <?php if ($role !== 'supervisor'): ?>
            <div class="col-md-3"><label class="form-label">المشرف</label><select name="sup" class="form-select"><option value="">الكل</option><?php foreach ($supervisors as $s): ?><option value="<?php echo (int)$s['id']; ?>" <?php echo $fSup === (int)$s['id'] ? 'selected' : ''; ?>><?php echo e($s['full_name']); ?></option><?php endforeach; ?></select></div>
            <?php endif; ?>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> بحث</button></div>
        </form>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>الكود</th><th>الاسم</th><th>الهاتف</th><th>الحالة</th><th class="text-center">إجراءات</th></tr></thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">لا توجد نتائج.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo e($r['sponsor_code']); ?></td>
                            <td><?php echo e($r['full_name']); ?></td>
                            <td dir="ltr"><?php echo e($r['phone'] ?? '-'); ?></td>
                            <td><?php
                                $st = ['active' => ['نشط','bg-success'], 'inactive' => ['غير نشط','bg-secondary'], 'suspended' => ['موقوف','bg-warning'], 'cancelled' => ['ملغي','bg-danger']];
                                [$stLabel, $stClass] = $st[$r['status']] ?? [$r['status'], 'bg-secondary'];
                            ?><span class="badge <?php echo $stClass; ?>"><?php echo $stLabel; ?></span></td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-primary" title="عرض" href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo (int)$r['id']; ?>&return=<?php echo $returnUrl; ?>"><i class="fas fa-eye"></i></a>
                                <?php if (in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)): ?>
                                    <a class="btn btn-sm btn-success" title="تسجيل دفعة" href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo (int)$r['id']; ?>&return=<?php echo $returnUrl; ?>"><i class="fas fa-receipt"></i></a>
                                <?php endif; ?>
                                <?php if ($role !== 'general_manager'): ?>
                                    <a class="btn btn-sm btn-warning" title="تعديل" href="<?php echo APP_URL; ?>modules/sponsors/edit.php?id=<?php echo (int)$r['id']; ?>&return=<?php echo $returnUrl; ?>"><i class="fas fa-pen"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3 mb-4"><ul class="pagination justify-content-center"><?php for ($i = 1; $i <= $pages; $i++): ?><li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="<?php echo e($qs(['page' => $i])); ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav>
<?php endif; ?>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
=======
<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'supervisor'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

dbExecute("ALTER TABLE sponsors ADD COLUMN IF NOT EXISTS brought_by_name VARCHAR(255) NULL");

$pageTitle = 'الكفلاء';
$active = 'sponsors';
$q = trim($_GET['q'] ?? '');
$fStatus = trim($_GET['status'] ?? '');
$fSup = (int)($_GET['sup'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$supervisors = dbFetchAll(
    "SELECT u.id, u.full_name
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE r.code IN ('supervisor', 'admin', 'vice_general_manager')
     ORDER BY u.full_name"
);

$sql = "SELECT
            s.id,
            COALESCE(
                NULLIF(TRIM(s.sponsor_code), ''),
                CONCAT('SP-', LPAD(s.id, 6, '0'))
            ) AS sponsor_code,
            s.full_name,
            s.phone,
            s.status,
            s.gender
        FROM sponsors s
        WHERE 1=1";
$params = [];

/*
 * Supervisor scope:
 * 1. Keep sponsors explicitly assigned to the current supervisor.
 * 2. Also include sponsors whose first letter is assigned to the current
 *    supervisor with a matching gender. A blank/both matrix gender means
 *    that the letter is unrestricted by gender.
 */
if ($role === 'supervisor') {
    $uid = Session::getUserId();
    $matrixRows = dbFetchAll(
        "SELECT letter_id, gender
         FROM supervisor_letters
         WHERE supervisor_id = ?",
        [$uid]
    );

    $scopeParts = ['s.supervisor_id = ?'];
    $scopeParams = [$uid];
    $letterGenderMap = [];

    foreach ($matrixRows as $matrixRow) {
        $letterId = (int)$matrixRow['letter_id'];
        $rawGender = strtolower(trim((string)($matrixRow['gender'] ?? '')));

        if ($rawGender === 'male' || $rawGender === 'm' || $rawGender === 'ذكر') {
            $gender = 'male';
        } elseif ($rawGender === 'female' || $rawGender === 'f' || $rawGender === 'أنثى' || $rawGender === 'انثى') {
            $gender = 'female';
        } else {
            /* Empty, both, or legacy values mean no gender restriction. */
            $gender = 'both';
        }

        $letterGenderMap[$letterId][] = $gender;
    }

    foreach ($letterGenderMap as $letterId => $genders) {
        $genders = array_values(array_unique($genders));

        if (in_array('both', $genders, true)) {
            $scopeParts[] = 's.first_letter_id = ?';
            $scopeParams[] = $letterId;
            continue;
        }

        $genderPlaceholders = implode(',', array_fill(0, count($genders), '?'));
        $scopeParts[] = "(s.first_letter_id = ? AND s.gender IN ($genderPlaceholders))";
        $scopeParams[] = $letterId;
        foreach ($genders as $gender) $scopeParams[] = $gender;
    }

    $sql .= ' AND (' . implode(' OR ', $scopeParts) . ')';
    $params = array_merge($params, $scopeParams);
} elseif ($fSup > 0) {
    $sql .= " AND s.supervisor_id = ?";
    $params[] = $fSup;
}

if ($fStatus !== '') {
    $sql .= " AND s.status = ?";
    $params[] = $fStatus;
}

if ($q !== '') {
    $sql .= " AND (s.full_name LIKE ? OR s.sponsor_code LIKE ? OR s.phone LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$sql .= " ORDER BY s.id DESC";
$all = dbFetchAll($sql, $params);

$total = count($all);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$rows = array_slice($all, ($page - 1) * $perPage, $perPage);

$qs = fn(array $extra) => APP_URL . 'modules/sponsors/index.php?' . http_build_query(array_merge($_GET, $extra));

/* Preserve the current search/filter/page state when opening a sponsor. */
$returnParams = [];
if ($q !== '') $returnParams['q'] = $q;
if ($fStatus !== '') $returnParams['status'] = $fStatus;
if ($fSup > 0) $returnParams['sup'] = $fSup;
$returnParams['page'] = $page;
$returnQuery = http_build_query($returnParams);
$returnUrl = rawurlencode($returnQuery);

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2>الكفلاء</h2>
    <p><?php echo $total; ?> كفيل</p>
    <div class="quick-actions mt-3">
        <?php if (in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)): ?>
            <a href="<?php echo APP_URL; ?>modules/sponsors/create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> إضافة كفيل</a>
            <a href="<?php echo APP_URL; ?>modules/sponsors/requests.php" class="btn btn-info btn-sm text-white"><i class="fas fa-bullhorn me-1"></i> طلبات الاستقطاب</a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3 fade-in">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label">بحث</label><input type="text" name="q" class="form-control" value="<?php echo e($q); ?>" placeholder="اسم / كود / هاتف"></div>
            <div class="col-md-2"><label class="form-label">الحالة</label><select name="status" class="form-select"><option value="">الكل</option><option value="active" <?php echo $fStatus === 'active' ? 'selected' : ''; ?>>نشط</option><option value="inactive" <?php echo $fStatus === 'inactive' ? 'selected' : ''; ?>>غير نشط</option><option value="suspended" <?php echo $fStatus === 'suspended' ? 'selected' : ''; ?>>موقوف</option><option value="cancelled" <?php echo $fStatus === 'cancelled' ? 'selected' : ''; ?>>ملغي</option></select></div>
            <?php if ($role !== 'supervisor'): ?>
            <div class="col-md-3"><label class="form-label">المشرف</label><select name="sup" class="form-select"><option value="">الكل</option><?php foreach ($supervisors as $s): ?><option value="<?php echo (int)$s['id']; ?>" <?php echo $fSup === (int)$s['id'] ? 'selected' : ''; ?>><?php echo e($s['full_name']); ?></option><?php endforeach; ?></select></div>
            <?php endif; ?>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> بحث</button></div>
        </form>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>الكود</th><th>الاسم</th><th>الهاتف</th><th>الحالة</th><th class="text-center">إجراءات</th></tr></thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">لا توجد نتائج.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo e($r['sponsor_code']); ?></td>
                            <td><?php echo e($r['full_name']); ?></td>
                            <td dir="ltr"><?php echo e($r['phone'] ?? '-'); ?></td>
                            <td><?php
                                $st = ['active' => ['نشط','bg-success'], 'inactive' => ['غير نشط','bg-secondary'], 'suspended' => ['موقوف','bg-warning'], 'cancelled' => ['ملغي','bg-danger']];
                                [$stLabel, $stClass] = $st[$r['status']] ?? [$r['status'], 'bg-secondary'];
                            ?><span class="badge <?php echo $stClass; ?>"><?php echo $stLabel; ?></span></td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-primary" title="عرض" href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo (int)$r['id']; ?>&return=<?php echo $returnUrl; ?>"><i class="fas fa-eye"></i></a>
                                <?php if (in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)): ?>
                                    <a class="btn btn-sm btn-success" title="تسجيل دفعة" href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo (int)$r['id']; ?>&return=<?php echo $returnUrl; ?>"><i class="fas fa-receipt"></i></a>
                                <?php endif; ?>
                                <?php if ($role !== 'general_manager'): ?>
                                    <a class="btn btn-sm btn-warning" title="تعديل" href="<?php echo APP_URL; ?>modules/sponsors/edit.php?id=<?php echo (int)$r['id']; ?>&return=<?php echo $returnUrl; ?>"><i class="fas fa-pen"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3 mb-4"><ul class="pagination justify-content-center"><?php for ($i = 1; $i <= $pages; $i++): ?><li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="<?php echo e($qs(['page' => $i])); ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav>
<?php endif; ?>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
