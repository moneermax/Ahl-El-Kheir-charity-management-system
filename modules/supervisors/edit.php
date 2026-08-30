<?php
// modules/supervisors/edit.php - Edit supervisor + MULTIPLE letters PER GENDER (Admin + VGM)
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
if (!in_array(Session::getUserRole(), ['admin', 'vice_general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$pageTitle = 'تعديل مشرف';
$active    = 'supervisors';

/* ---------- gender helper ---------- */
function ak_norm_gender($raw): string {
    $v = strtolower(trim((string)$raw));
    if (in_array($v, ['female', 'f', 'أنثى', 'انثى'], true)) return 'female';
    if (in_array($v, ['male', 'm', 'ذكر'], true)) return 'male';
    return '';
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$sup = dbFetchOne("
    SELECT u.id, u.username, u.full_name, u.email, u.phone, u.is_active
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE u.id = ? AND r.code = 'supervisor'
", [$id]);
if (!$sup) {
    flash('error', 'المشرف غير موجود.');
    redirect('modules/supervisors/index.php');
}

/* ---------- Letters + ownership per letter+gender (exclude self from "taken") ---------- */
$letters = dbFetchAll("SELECT id, code, name_ar FROM letters WHERE is_active = 1 ORDER BY sort_order");
$takenRows = dbFetchAll("
    SELECT sl.letter_id, sl.gender, u.full_name
    FROM supervisor_letters sl
    JOIN users u ON u.id = sl.supervisor_id
    WHERE sl.supervisor_id <> ?
", [$id]);
$otherTaken = [];
foreach ($takenRows as $t) {
    $g = ak_norm_gender($t['gender']);
    $lid = (int)$t['letter_id'];
    if ($g === '') { $otherTaken[$lid]['male'] = $t['full_name']; $otherTaken[$lid]['female'] = $t['full_name']; }
    else { $otherTaken[$lid][$g] = $t['full_name']; }
}
$myRows = dbFetchAll("SELECT letter_id, gender FROM supervisor_letters WHERE supervisor_id = ?", [$id]);
$myMale = []; $myFemale = []; $hasLegacy = false;
foreach ($myRows as $r) {
    $g = ak_norm_gender($r['gender']);
    if ($g === 'male') $myMale[] = (int)$r['letter_id'];
    elseif ($g === 'female') $myFemale[] = (int)$r['letter_id'];
    else $hasLegacy = true;
}

$errors = [];
$input  = [
    'full_name' => $sup['full_name'],
    'username'  => $sup['username'],
    'email'     => $sup['email'] ?? '',
    'phone'     => $sup['phone'] ?? '',
    'active'    => (int)$sup['is_active'] === 1
];
$wantedMale   = $myMale;
$wantedFemale = $myFemale;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'انتهت صلاحية الجلسة، حاول مرة أخرى.';
    } else {
        $input['full_name'] = trim($_POST['full_name'] ?? '');
        $input['username']  = trim($_POST['username'] ?? '');
        $input['email']     = trim($_POST['email'] ?? '');
        $input['phone']     = trim($_POST['phone'] ?? '');
        $input['active']    = (($_POST['status'] ?? 'active') !== 'suspended');
        $newPassword        = $_POST['new_password'] ?? '';
        $wantedMale   = array_values(array_unique(array_map('intval', $_POST['male_letters'] ?? [])));
        $wantedFemale = array_values(array_unique(array_map('intval', $_POST['female_letters'] ?? [])));

        $pairs = [];
        foreach ($wantedMale as $lid)   $pairs[] = [$lid, 'male'];
        foreach ($wantedFemale as $lid) $pairs[] = [$lid, 'female'];

        if ($input['full_name'] === '') $errors[] = 'الاسم الكامل مطلوب.';
        if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $input['username'])) $errors[] = 'اسم المستخدم غير صالح (أحرف إنجليزية/أرقام، 3-30).';
        if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صالح.';
        if ($newPassword !== '' && strlen($newPassword) < 6) $errors[] = 'كلمة المرور الجديدة يجب ألا تقل عن 6 أحرف.';
        if (dbFetchOne("SELECT id FROM users WHERE username = ? AND id <> ?", [$input['username'], $id])) {
            $errors[] = 'اسم المستخدم مستخدم بالفعل.';
        }
        if (!$pairs) $errors[] = 'اختر حرفاً واحداً على الأقل مع جنس واحد (ذ / إ).';
        $codeOf = function ($lid) use ($letters) { foreach ($letters as $L) { if ((int)$L['id'] === $lid) return $L['code']; } return '#'; };
        $conflictCodes = [];
        foreach ($pairs as [$lid, $g]) {
            if (isset($otherTaken[$lid][$g])) {
                $conflictCodes[] = '«' . $codeOf($lid) . ($g === 'male' ? '(ذ)' : '(إ)') . '»';
            }
        }
        if ($conflictCodes) $errors[] = 'مجموعات مشغولة لمشرف آخر: ' . implode('، ', $conflictCodes);

        if (!$errors) {
            $isActive = $input['active'] ? 1 : 0;
            if ($newPassword !== '') {
                dbExecute(
                    "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, is_active = ?, password_hash = ? WHERE id = ?",
                    [
                        $input['full_name'], $input['username'],
                        $input['email'] !== '' ? $input['email'] : null,
                        $input['phone'] !== '' ? $input['phone'] : null,
                        $isActive, password_hash($newPassword, PASSWORD_DEFAULT), $id
                    ]
                );
            } else {
                dbExecute(
                    "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, is_active = ? WHERE id = ?",
                    [
                        $input['full_name'], $input['username'],
                        $input['email'] !== '' ? $input['email'] : null,
                        $input['phone'] !== '' ? $input['phone'] : null,
                        $isActive, $id
                    ]
                );
            }
            // ---- Sync letter+gender pairs (add new / remove unchecked) ----
            $currentPairs = [];
            foreach ($myMale as $lid)   $currentPairs[] = $lid . '|male';
            foreach ($myFemale as $lid) $currentPairs[] = $lid . '|female';
            $wantedPairs = [];
            foreach ($pairs as [$lid, $g]) $wantedPairs[] = $lid . '|' . $g;
            foreach (array_diff($currentPairs, $wantedPairs) as $key) {
                [$lid, $g] = explode('|', $key);
                dbExecute("DELETE FROM supervisor_letters WHERE supervisor_id = ? AND letter_id = ? AND gender = ?", [$id, (int)$lid, $g]);
            }
            foreach (array_diff($wantedPairs, $currentPairs) as $key) {
                [$lid, $g] = explode('|', $key);
                dbExecute(
                    "INSERT INTO supervisor_letters (supervisor_id, letter_id, gender, assigned_by) VALUES (?, ?, ?, ?)",
                    [$id, (int)$lid, $g, Session::getUserId()]
                );
            }
            dbExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, 'UPDATE', 'users', ?, ?, ?, ?, ?)",
                [
                    Session::getUserId(),
                    $id,
                    json_encode(['full_name' => $sup['full_name'], 'username' => $sup['username'], 'is_active' => (int)$sup['is_active']], JSON_UNESCAPED_UNICODE),
                    json_encode(['full_name' => $input['full_name'], 'username' => $input['username'], 'is_active' => $isActive, 'letters' => $wantedPairs], JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );
            flash('success', 'تم حفظ تعديلات المشرف: ' . $input['full_name']);
            redirect('modules/supervisors/index.php');
        }
    }
}
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.letter-group{display:inline-flex;align-items:center;gap:6px;border:2px solid #e9ecef;border-radius:12px;padding:6px 8px;margin:3px;background:#fff}
.letter-group.taken{opacity:.45}
.lg-code{font-weight:800;font-size:18px;color:#0a1f44;min-width:22px;text-align:center}
.g-toggle{cursor:pointer;margin:0}
.g-toggle input{display:none}
.g-toggle span{display:inline-flex;width:30px;height:30px;align-items:center;justify-content:center;border-radius:8px;border:2px solid #e9ecef;font-weight:700;font-size:13px;color:#6c757d;transition:all .2s}
.g-toggle.m input:checked+span{background:#1b4d8f;border-color:#1b4d8f;color:#fff}
.g-toggle.f input:checked+span{background:#8f1b4d;border-color:#8f1b4d;color:#fff}
.g-toggle input:disabled+span{background:#f8f9fa;color:#adb5bd;cursor:not-allowed}
</style>
<div class="welcome-section fade-in">
<h2>تعديل المشرف: <?php echo e($sup['full_name']); ?></h2>
<p>يمكن إضافة/إزالة أي عدد من الحروف، ولكل حرف جنس أو كلا الجنسين</p>
</div>
<?php if ($hasLegacy): ?>
<div class="alert alert-warning fade-in">
<i class="fas fa-triangle-exclamation me-2"></i>هذا المشرف لديه تعيينات قديمة بدون جنس — يمكن تنظيفها من صفحة «توزيع الحروف».
</div>
<?php endif; ?>
<?php if ($errors): ?>
<div class="alert alert-danger fade-in">
<ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul>
</div>
<?php endif; ?>
<div class="card fade-in">
<div class="card-body">
<form method="post">
<?php echo csrf_field(); ?>
<input type="hidden" name="id" value="<?php echo $id; ?>">
<div class="row g-3">
<div class="col-md-6">
<label class="form-label">الاسم الكامل *</label>
<input type="text" name="full_name" class="form-control" required value="<?php echo e($input['full_name']); ?>">
</div>
<div class="col-md-6">
<label class="form-label">اسم المستخدم *</label>
<input type="text" name="username" class="form-control" required dir="ltr" value="<?php echo e($input['username']); ?>">
</div>
<div class="col-md-6">
<label class="form-label">البريد الإلكتروني</label>
<input type="email" name="email" class="form-control" dir="ltr" value="<?php echo e($input['email']); ?>">
</div>
<div class="col-md-6">
<label class="form-label">الهاتف</label>
<input type="text" name="phone" class="form-control" dir="ltr" value="<?php echo e($input['phone']); ?>">
</div>
<div class="col-md-6">
<label class="form-label">كلمة مرور جديدة <small class="text-muted">(اتركها فارغة للإبقاء على الحالية)</small></label>
<input type="password" name="new_password" class="form-control" dir="ltr">
</div>
<div class="col-md-6">
<label class="form-label">الحالة</label>
<select name="status" class="form-select">
<option value="active" <?php echo $input['active'] ? 'selected' : ''; ?>>نشط</option>
<option value="suspended" <?php echo !$input['active'] ? 'selected' : ''; ?>>موقوف</option>
</select>
</div>
</div>
<hr>
<label class="form-label d-block">الحروف + الجنس <small class="text-muted">(ذ = كفلاء ذكور، إ = كفلاء إناث — علّم كلا المربعين لكلا الجنسين — المجموعات المشغولة لمشرف آخر معطلة)</small></label>
<div class="d-flex flex-wrap">
<?php foreach ($letters as $L):
    $lid = (int)$L['id'];
    $tM = $otherTaken[$lid]['male'] ?? null;
    $tF = $otherTaken[$lid]['female'] ?? null;
    $allTaken = ($tM !== null && $tF !== null);
?>
<div class="letter-group <?php echo $allTaken ? 'taken' : ''; ?>">
<span class="lg-code"><?php echo e($L['code']); ?></span>
<label class="g-toggle m" title="<?php echo $tM !== null ? 'ذكور مشغول لـ ' . e($tM) : 'كفلاء ذكور'; ?>">
<input type="checkbox" name="male_letters[]" value="<?php echo $lid; ?>"
<?php echo $tM !== null ? 'disabled' : ''; ?> <?php echo in_array($lid, $wantedMale, true) ? 'checked' : ''; ?>>
<span>ذ</span>
</label>
<label class="g-toggle f" title="<?php echo $tF !== null ? 'إناث مشغول لـ ' . e($tF) : 'كفلاء إناث'; ?>">
<input type="checkbox" name="female_letters[]" value="<?php echo $lid; ?>"
<?php echo $tF !== null ? 'disabled' : ''; ?> <?php echo in_array($lid, $wantedFemale, true) ? 'checked' : ''; ?>>
<span>إ</span>
</label>
</div>
<?php endforeach; ?>
</div>
<div class="mt-4">
<button class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ التعديلات</button>
<a href="<?php echo APP_URL; ?>modules/supervisors/index.php" class="btn btn-secondary">إلغاء</a>
</div>
</form>
</div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>