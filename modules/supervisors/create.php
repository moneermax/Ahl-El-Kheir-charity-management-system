<?php
// modules/supervisors/create.php - Create supervisor + assign MULTIPLE letters PER GENDER (Admin + VGM)
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
$pageTitle = 'إضافة مشرف';
$active    = 'supervisors';

/* ---------- gender helper (robust to legacy stored values) ---------- */
function ak_norm_gender($raw): string {
    $v = strtolower(trim((string)$raw));
    if (in_array($v, ['female', 'f', 'أنثى', 'انثى'], true)) return 'female';
    if (in_array($v, ['male', 'm', 'ذكر'], true)) return 'male';
    return '';
}

/* ---------- Letters + ownership map per letter+gender ---------- */
$letters = dbFetchAll("SELECT id, code, name_ar FROM letters WHERE is_active = 1 ORDER BY sort_order");
$takenRows = dbFetchAll("
    SELECT sl.letter_id, sl.gender, u.full_name
    FROM supervisor_letters sl
    JOIN users u ON u.id = sl.supervisor_id
");
$taken = []; // [letter_id]['male'|'female'] = owner name (legacy gender-less rows block BOTH until cleaned)
foreach ($takenRows as $t) {
    $g = ak_norm_gender($t['gender']);
    $lid = (int)$t['letter_id'];
    if ($g === '') { $taken[$lid]['male'] = $t['full_name']; $taken[$lid]['female'] = $t['full_name']; }
    else { $taken[$lid][$g] = $t['full_name']; }
}
$errors  = [];
$success = null;
$input   = ['full_name' => '', 'username' => '', 'email' => '', 'phone' => ''];
$inputMale   = [];
$inputFemale = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'انتهت صلاحية الجلسة، حاول مرة أخرى.';
    } else {
        $input['full_name'] = trim($_POST['full_name'] ?? '');
        $input['username']  = trim($_POST['username'] ?? '');
        $input['email']     = trim($_POST['email'] ?? '');
        $input['phone']     = trim($_POST['phone'] ?? '');
        $password           = $_POST['password'] ?? '';
        $inputMale   = array_values(array_unique(array_map('intval', $_POST['male_letters'] ?? [])));
        $inputFemale = array_values(array_unique(array_map('intval', $_POST['female_letters'] ?? [])));

        $pairs = [];
        foreach ($inputMale as $lid)   $pairs[] = [$lid, 'male'];
        foreach ($inputFemale as $lid) $pairs[] = [$lid, 'female'];

        if ($input['full_name'] === '') $errors[] = 'الاسم الكامل مطلوب.';
        if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $input['username'])) $errors[] = 'اسم المستخدم غير صالح (أحرف إنجليزية/أرقام، 3-30).';
        if (strlen($password) < 6) $errors[] = 'كلمة المرور يجب ألا تقل عن 6 أحرف.';
        if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صالح.';
        if (!$pairs) $errors[] = 'اختر حرفاً واحداً على الأقل مع جنس واحد (ذ / إ) — يمكن اختيار عدة حروف ولكل حرف جنس أو كلا الجنسين.';
        if (!$errors && dbFetchOne("SELECT id FROM users WHERE username = ?", [$input['username']])) {
            $errors[] = 'اسم المستخدم مستخدم بالفعل.';
        }
        // Server-side enforcement: same letter+same gender → one supervisor only
        $codeOf = function ($lid) use ($letters) { foreach ($letters as $L) { if ((int)$L['id'] === $lid) return $L['code']; } return '#'; };
        $conflicts = [];
        foreach ($pairs as [$lid, $g]) {
            if (isset($taken[$lid][$g])) {
                $conflicts[] = '«' . $codeOf($lid) . ($g === 'male' ? '(ذ)' : '(إ)') . '» مشغول للمشرف: ' . $taken[$lid][$g];
            }
        }
        if ($conflicts) $errors[] = implode(' — ', $conflicts);

        if (!$errors) {
            dbExecute(
                "INSERT INTO users (role_id, username, password_hash, full_name, email, phone, is_active, created_by)
                 VALUES ((SELECT id FROM roles WHERE code = 'supervisor'), ?, ?, ?, ?, ?, 1, ?)",
                [
                    $input['username'],
                    password_hash($password, PASSWORD_DEFAULT),
                    $input['full_name'],
                    $input['email'] !== '' ? $input['email'] : null,
                    $input['phone'] !== '' ? $input['phone'] : null,
                    Session::getUserId()
                ]
            );
            $row   = dbFetchOne("SELECT LAST_INSERT_ID() AS id");
            $newId = (int)($row['id'] ?? 0);
            $assignedCodes = [];
            foreach ($pairs as [$lid, $g]) {
                dbExecute(
                    "INSERT INTO supervisor_letters (supervisor_id, letter_id, gender, assigned_by)
                     VALUES (?, ?, ?, ?)",
                    [$newId, $lid, $g, Session::getUserId()]
                );
                $assignedCodes[] = $codeOf($lid) . ($g === 'male' ? '(ذ)' : '(إ)');
            }
            dbExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, 'CREATE', 'users', ?, NULL, ?, ?, ?)",
                [
                    Session::getUserId(),
                    $newId,
                    json_encode(['username' => $input['username'], 'full_name' => $input['full_name'], 'role' => 'supervisor', 'letters' => $assignedCodes], JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );
            $success = [
                'username'  => $input['username'],
                'password'  => $password,
                'full_name' => $input['full_name'],
                'letters'   => $assignedCodes
            ];
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
<h2>إضافة مشرف جديد</h2>
<p>يمكن للمشرف الواحد حمل <strong>أي عدد من الحروف</strong>، ولكل حرف: ذكور فقط / إناث فقط / كلا الجنسين — القيد الوحيد: نفس الحرف+نفس الجنس لا يتشاركه مشرفان</p>
</div>
<?php if ($success): ?>
<div class="alert alert-success fade-in">
<h5 class="alert-heading"><i class="fas fa-check-circle me-2"></i>تم إنشاء المشرف بنجاح</h5>
<p class="mb-1">الاسم: <strong><?php echo e($success['full_name']); ?></strong></p>
<p class="mb-1">اسم المستخدم: <strong><?php echo e($success['username']); ?></strong></p>
<p class="mb-1">كلمة المرور: <strong class="text-danger"><?php echo e($success['password']); ?></strong></p>
<p class="mb-2">الحروف + الجنس: <strong><?php echo e(implode(' ، ', $success['letters'])); ?></strong></p>
<p class="mb-3"><small>⚠ سلّم كلمة المرور للمشرف الآن — لن تظهر مرة أخرى.</small></p>
<a href="<?php echo APP_URL; ?>modules/supervisors/index.php" class="btn btn-primary btn-sm">العودة للقائمة</a>
<a href="<?php echo APP_URL; ?>modules/supervisors/create.php" class="btn btn-secondary btn-sm">إضافة مشرف آخر</a>
</div>
<?php endif; ?>
<?php if ($errors): ?>
<div class="alert alert-danger fade-in">
<ul class="mb-0"><?php foreach ($errors as $er) { echo '<li>' . e($er) . '</li>'; } ?></ul>
</div>
<?php endif; ?>
<div class="card fade-in">
<div class="card-header"><i class="fas fa-user-plus me-2"></i>بيانات المشرف</div>
<div class="card-body">
<form method="post">
<?php echo csrf_field(); ?>
<div class="row g-3">
<div class="col-md-6">
<label class="form-label">الاسم الكامل *</label>
<input type="text" name="full_name" class="form-control" required value="<?php echo e($input['full_name']); ?>">
</div>
<div class="col-md-6">
<label class="form-label">اسم المستخدم *</label>
<input type="text" name="username" class="form-control" required value="<?php echo e($input['username']); ?>" dir="ltr">
</div>
<div class="col-md-6">
<label class="form-label">البريد الإلكتروني</label>
<input type="email" name="email" class="form-control" value="<?php echo e($input['email']); ?>" dir="ltr">
</div>
<div class="col-md-6">
<label class="form-label">الهاتف</label>
<input type="text" name="phone" class="form-control" value="<?php echo e($input['phone']); ?>" dir="ltr">
</div>
<div class="col-md-6">
<label class="form-label">كلمة المرور *</label>
<div class="input-group">
<input type="text" name="password" id="password" class="form-control" required dir="ltr">
<button type="button" class="btn btn-secondary" onclick="genPassword()" title="توليد كلمة مرور">
<i class="fas fa-key"></i> توليد
</button>
</div>
</div>
</div>
<hr>
<label class="form-label d-block">حروف المشرف + الجنس * <small class="text-muted">(ذ = كفلاء ذكور، إ = كفلاء إناث — علّم كلا المربعين لكلا الجنسين — المجموعات المشغولة لمشرف آخر معطلة)</small></label>
<div class="d-flex flex-wrap">
<?php foreach ($letters as $L):
    $lid = (int)$L['id'];
    $tM = $taken[$lid]['male'] ?? null;
    $tF = $taken[$lid]['female'] ?? null;
    $allTaken = ($tM !== null && $tF !== null);
?>
<div class="letter-group <?php echo $allTaken ? 'taken' : ''; ?>">
<span class="lg-code"><?php echo e($L['code']); ?></span>
<label class="g-toggle m" title="<?php echo $tM !== null ? 'ذكور مشغول لـ ' . e($tM) : 'كفلاء ذكور'; ?>">
<input type="checkbox" name="male_letters[]" value="<?php echo $lid; ?>"
<?php echo $tM !== null ? 'disabled' : ''; ?> <?php echo in_array($lid, $inputMale, true) ? 'checked' : ''; ?>>
<span>ذ</span>
</label>
<label class="g-toggle f" title="<?php echo $tF !== null ? 'إناث مشغول لـ ' . e($tF) : 'كفلاء إناث'; ?>">
<input type="checkbox" name="female_letters[]" value="<?php echo $lid; ?>"
<?php echo $tF !== null ? 'disabled' : ''; ?> <?php echo in_array($lid, $inputFemale, true) ? 'checked' : ''; ?>>
<span>إ</span>
</label>
</div>
<?php endforeach; ?>
</div>
<div class="mt-4">
<button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ المشرف</button>
<a href="<?php echo APP_URL; ?>modules/supervisors/index.php" class="btn btn-secondary">إلغاء</a>
</div>
</form>
</div>
</div>
<script>
function genPassword(){
var chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789@#';
var p='';
for(var i=0;i<10;i++){ p+=chars[Math.floor(Math.random()*chars.length)]; }
document.getElementById('password').value=p;
}
</script>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>