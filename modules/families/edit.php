<?php
// modules/families/edit.php - Edit family + bank accounts + children (Admin/VGM/Supervisor-own/Nanny-own)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor', 'nanny'], true)) {
header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = 'تعديل أسرة';
$active = 'families';
$uid = Session::getUserId();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$fam = dbFetchOne("SELECT * FROM families WHERE id = ?", [$id]);
if (!$fam) { flash('error', 'الأسرة غير موجودة.'); redirect('modules/families/index.php'); }
/* - schema sync - */
try {
dbExecute("CREATE TABLE IF NOT EXISTS family_bank_accounts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
family_id INT UNSIGNED NOT NULL,
bank_name VARCHAR(100) NOT NULL,
bank_branch VARCHAR(100) NULL,
account_number VARCHAR(50) NOT NULL,
account_holder VARCHAR(150) NULL,
is_primary TINYINT(1) NOT NULL DEFAULT 0,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
dbExecute("ALTER TABLE families ADD COLUMN IF NOT EXISTS father_name VARCHAR(150) NULL");
dbExecute("ALTER TABLE families ADD COLUMN IF NOT EXISTS father_death_date DATE NULL");
dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS national_id VARCHAR(20) NULL");
dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS nationality VARCHAR(50) NOT NULL DEFAULT 'سودانية'");
dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS health_status VARCHAR(150) DEFAULT NULL");
dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS health_status_other VARCHAR(150) DEFAULT NULL");
dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS psychological_state VARCHAR(150) DEFAULT NULL");
dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS psychological_state_other VARCHAR(150) DEFAULT NULL");
dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS guardian_name VARCHAR(150) DEFAULT NULL");
dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS guardian_relationship VARCHAR(60) DEFAULT NULL");
dbExecute("ALTER TABLE families ADD COLUMN IF NOT EXISTS father_death_cause VARCHAR(150) DEFAULT NULL");
dbExecute("ALTER TABLE families ADD COLUMN IF NOT EXISTS mother_marital_status VARCHAR(30) DEFAULT 'أرملة'");
/* Session-7: orphan financial fields (consistency across ALL orphans) */
dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS monthly_sponsorship_value DECIMAL(12,2) NULL");
dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS extra_allowance DECIMAL(12,2) NULL");
/* Session-7: orphan documents archive */
dbExecute("CREATE TABLE IF NOT EXISTS orphan_documents (
id INT AUTO_INCREMENT PRIMARY KEY,
child_id INT NOT NULL,
family_id INT NOT NULL,
doc_type VARCHAR(50) NOT NULL DEFAULT 'other',
file_path VARCHAR(255) NOT NULL,
file_name VARCHAR(255) NOT NULL,
file_size INT NOT NULL,
uploaded_by INT NULL,
uploaded_at DATETIME NOT NULL,
INDEX idx_od_child (child_id),
INDEX idx_od_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) { error_log('Families schema sync: ' . $e->getMessage()); }
function sync_primary_bank(int $familyId): void {
dbExecute("UPDATE family_bank_accounts SET is_primary = 0 WHERE family_id = ?", [$familyId]);
$primary = dbFetchOne("SELECT * FROM family_bank_accounts WHERE family_id = ? ORDER BY is_primary DESC, id ASC LIMIT 1", [$familyId]);
if ($primary) {
dbExecute("UPDATE family_bank_accounts SET is_primary = 1 WHERE id = ?", [$primary['id']]);
dbExecute("UPDATE families SET bank_name=?, bank_account_number=?, bank_branch=?, bank_account_holder=? WHERE id=?",
[$primary['bank_name'], $primary['account_number'], $primary['bank_branch'], $primary['account_holder'], $familyId]);
} else {
dbExecute("UPDATE families SET bank_name=NULL, bank_account_number=NULL, bank_branch=NULL, bank_account_holder=NULL WHERE id=?", [$familyId]);
}
}
/* - ownership checks - */
if ($role === 'nanny') {
if ((int)($fam['nanny_id'] ?? 0) !== $uid) { flash('error', 'هذه الأسرة ليست ضمن نطاقك.'); redirect('modules/families/index.php'); }
}
if ($role === 'supervisor') {
$myNormCodes = array_map(fn($r) => normalize_arabic_letter($r['code']),
dbFetchAll("SELECT l.code FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id WHERE sl.supervisor_id = ?", [$uid]));
$mine = ((int)($fam['supervisor_id'] ?? 0) === $uid)
|| in_array(normalize_arabic_letter((string)($fam['legacy_mother_first_letter'] ?? '')), $myNormCodes, true);
if (!$mine) { flash('error', 'هذه الأسرة ليست ضمن نطاقك.'); redirect('modules/families/index.php'); }
}
/* - dropdown options (orphan fields) - */
$optsFile = dirname(__DIR__, 2) . '/includes/orphan_options.php';
$AK_ORPHAN_OPTS = is_file($optsFile) ? include $optsFile : [
'health' => ['سليم', 'أخرى'], 'psych' => ['سليم', 'أخرى'],
'marital' => ['أرملة', 'مطلقة', 'متزوجة', 'منفصلة', 'عزباء'],
'relationships' => ['الأم', 'الأب', 'الجد', 'الجدة', 'العم', 'الخال', 'وصي شرعي', 'أخرى'],
];
$statuses = ['pending' => 'قيد الانتظار', 'active' => 'نشطة', 'paused' => 'متوقفة', 'completed' => 'مكتملة', 'closed' => 'مغلقة'];
$supervisors = in_array($role, ['admin', 'vice_general_manager'], true)
? dbFetchAll("SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'supervisor' AND u.is_active = 1 ORDER BY u.full_name") : [];
$nannies = $role !== 'nanny'
? dbFetchAll("SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'nanny' AND u.is_active = 1 ORDER BY u.full_name") : [];
$bankAccounts = dbFetchAll("SELECT * FROM family_bank_accounts WHERE family_id = ? ORDER BY is_primary DESC, id ASC", [$id]);
$children = dbFetchAll("SELECT fc.*, mn.name need_name FROM family_children fc
LEFT JOIN medical_needs mn ON mn.id = fc.medical_need_id
WHERE fc.family_id = ? ORDER BY fc.id", [$id]);
$medicalNeeds = dbFetchAll("SELECT id, name FROM medical_needs ORDER BY id");
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (!verify_csrf()) {
$errors[] = 'انتهت صلاحية الجلسة.';
} elseif (isset($_POST['update_family'])) {
$name = trim($_POST['mother_name'] ?? '');
if ($name === '') $errors[] = 'اسم الأم مطلوب.';
if (!$errors) {
$supId = ($role !== 'supervisor' && $role !== 'nanny' && isset($_POST['supervisor_id']) && $_POST['supervisor_id'] !== '')
? (int)$_POST['supervisor_id'] : ($fam['supervisor_id'] ?? null);
$nannyId = ($role !== 'nanny' && isset($_POST['nanny_id']) && $_POST['nanny_id'] !== '')
? (int)$_POST['nanny_id'] : ($fam['nanny_id'] ?? null);
dbExecute("UPDATE families SET mother_name=?, mother_phone=?, mother_alt_phone=?, mother_job=?, mother_workplace=?,
mother_national_id=?, mother_birth_date=?, father_name=?, father_death_date=?, father_death_cause=?, mother_marital_status=?,
city=?, district=?, address=?, monthly_need_amount=?,
status=?, notes=?, supervisor_id=?, nanny_id=?, assigned_by=?, assigned_at=NOW(), updated_by=? WHERE id=?",
[$name,
trim($_POST['mother_phone'] ?? '') ?: null,
trim($_POST['mother_alt_phone'] ?? '') ?: null,
trim($_POST['mother_job'] ?? '') ?: null,
trim($_POST['mother_workplace'] ?? '') ?: null,
trim($_POST['mother_national_id'] ?? '') ?: null,
trim($_POST['mother_birth_date'] ?? '') ?: null,
trim($_POST['father_name'] ?? '') ?: null,
trim($_POST['father_death_date'] ?? '') ?: null,
trim($_POST['father_death_cause'] ?? '') ?: null,
trim($_POST['mother_marital_status'] ?? '') ?: 'أرملة',
trim($_POST['city'] ?? '') ?: null,
trim($_POST['district'] ?? '') ?: null,
trim($_POST['address'] ?? '') ?: null,
trim($_POST['monthly_need_amount'] ?? '') !== '' ? (float)str_replace(',', '', $_POST['monthly_need_amount']) : null,
array_key_exists($_POST['status'] ?? '', $statuses) ? $_POST['status'] : $fam['status'],
trim($_POST['notes'] ?? '') ?: null,
$supId, $nannyId,
$supId !== null ? $uid : null,
$uid, $id]);
flash('success', 'تم حفظ بيانات الأسرة.');
redirect('modules/families/edit.php?id=' . $id);
}
} elseif (isset($_POST['add_bank'])) {
$bn = trim($_POST['bank_name'] ?? '');
$acc = trim($_POST['account_number'] ?? '');
if ($bn === '' || $acc === '') {
flash('error', 'اسم البنك ورقم الحساب مطلوبان.');
} else {
$isFirst = !dbFetchOne("SELECT id FROM family_bank_accounts WHERE family_id = ?", [$id]);
dbExecute("INSERT INTO family_bank_accounts (family_id, bank_name, bank_branch, account_number, account_holder, is_primary)
VALUES (?, ?, ?, ?, ?, ?)",
[$id, $bn, trim($_POST['bank_branch'] ?? '') ?: null, $acc, trim($_POST['account_holder'] ?? '') ?: null, ($isFirst || isset($_POST['is_primary'])) ? 1 : 0]);
sync_primary_bank($id);
flash('success', 'تمت إضافة الحساب البنكي.');
}
redirect('modules/families/edit.php?id=' . $id);
} elseif (isset($_POST['add_child'])) {
$cn = trim($_POST['child_name'] ?? '');
if ($cn === '') $errors[] = 'اسم الطفل مطلوب.';
if (!$errors) {
$needId = (int)($_POST['medical_need_id'] ?? 0) ?: null;
$health = trim($_POST['health_status'] ?? 'سليم');
$psych  = trim($_POST['psychological_state'] ?? 'سليم');
dbExecute("INSERT INTO family_children (family_id, child_name, national_id, birth_date, gender, education_level,
has_medical_needs, medical_need_id, medical_notes,
nationality, health_status, health_status_other, psychological_state, psychological_state_other, guardian_name, guardian_relationship,
monthly_sponsorship_value, extra_allowance, is_active)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
[$id, $cn,
trim($_POST['national_id'] ?? '') ?: null,
trim($_POST['birth_date'] ?? '') ?: null,
in_array($_POST['gender'] ?? '', ['male', 'female', 'unknown'], true) ? $_POST['gender'] : 'unknown',
trim($_POST['education_level'] ?? '') ?: null,
$needId !== null ? 1 : 0, $needId,
trim($_POST['medical_notes'] ?? '') ?: null,
trim($_POST['nationality'] ?? '') !== '' ? trim($_POST['nationality']) : 'سودانية',
$health, $health === 'أخرى' ? trim($_POST['health_status_other'] ?? '') : null,
$psych, $psych === 'أخرى' ? trim($_POST['psychological_state_other'] ?? '') : null,
trim($_POST['guardian_name'] ?? '') ?: null,
trim($_POST['guardian_relationship'] ?? '') ?: null,
trim($_POST['monthly_sponsorship_value'] ?? '') !== '' ? (float)str_replace(',', '', $_POST['monthly_sponsorship_value']) : null,
trim($_POST['extra_allowance'] ?? '') !== '' ? (float)str_replace(',', '', $_POST['extra_allowance']) : null]);
flash('success', 'تمت إضافة الطفل.');
redirect('modules/families/edit.php?id=' . $id);
}
/* Session-7: update existing child (financials + basics). DELETE child removed by design (data safety). */
} elseif (isset($_POST['update_child'])) {
$cid = (int)$_POST['update_child'];
$cn = trim($_POST['child_name'] ?? '');
if ($cn === '') $errors[] = 'اسم الطفل مطلوب.';
if (!dbFetchOne("SELECT id FROM family_children WHERE id = ? AND family_id = ?", [$cid, $id])) $errors[] = 'الطفل غير موجود.';
if (!$errors) {
dbExecute("UPDATE family_children SET child_name=?, national_id=?, birth_date=?, gender=?, education_level=?,
monthly_sponsorship_value=?, extra_allowance=?, guardian_name=?, guardian_relationship=? WHERE id=? AND family_id=?",
[$cn,
trim($_POST['national_id'] ?? '') ?: null,
trim($_POST['birth_date'] ?? '') ?: null,
in_array($_POST['gender'] ?? '', ['male', 'female', 'unknown'], true) ? $_POST['gender'] : 'unknown',
trim($_POST['education_level'] ?? '') ?: null,
trim($_POST['monthly_sponsorship_value'] ?? '') !== '' ? (float)str_replace(',', '', $_POST['monthly_sponsorship_value']) : null,
trim($_POST['extra_allowance'] ?? '') !== '' ? (float)str_replace(',', '', $_POST['extra_allowance']) : null,
trim($_POST['guardian_name'] ?? '') ?: null,
trim($_POST['guardian_relationship'] ?? '') ?: null,
$cid, $id]);
try {
dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
VALUES (?, 'UPDATE', 'family_children', ?, NULL, ?, ?, ?)",
[$uid, $cid, json_encode(['child_name' => $cn], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
} catch (Throwable $e) {}
flash('success', 'تم حفظ بيانات الطفل.');
redirect('modules/families/edit.php?id=' . $id);
}
}
}
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button{ -webkit-appearance:none; margin:0; }
input[type=number]{ -moz-appearance:textfield; appearance:textfield; }
</style>
<div class="welcome-section fade-in">
<h2>تعديل أسرة: <?php echo e($fam['mother_name']); ?></h2>
<p><?php echo e($fam['family_code']); ?></p>
<div class="quick-actions mt-3" style="position:static; margin-bottom:1.25rem;">
<a href="<?php echo APP_URL; ?>modules/families/view.php?id=<?php echo $id; ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye me-1"></i>عرض</a>
<a href="<?php echo APP_URL; ?>modules/families/documents.php?id=<?php echo $id; ?>" class="btn btn-info btn-sm"><i class="fas fa-folder-open me-1"></i>الوثائق</a>
<a href="<?php echo APP_URL; ?>modules/families/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-right me-1"></i>رجوع</a>
</div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?>
<div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
<?php endif; ?>
<div class="card mb-4 fade-in">
<div class="card-header"><i class="fas fa-house-chimney me-2"></i>بيانات الأسرة</div>
<div class="card-body">
<form method="post">
<?php echo csrf_field(); ?>
<input type="hidden" name="id" value="<?php echo $id; ?>">
<div class="row g-3">
<div class="col-md-6"><label class="form-label">اسم الأم *</label><input type="text" name="mother_name" class="form-control" required value="<?php echo e($fam['mother_name']); ?>"></div>
<div class="col-md-3"><label class="form-label">الهاتف</label><input type="text" name="mother_phone" class="form-control" dir="ltr" value="<?php echo e($fam['mother_phone'] ?? ''); ?>"></div>
<div class="col-md-3"><label class="form-label">هاتف بديل</label><input type="text" name="mother_alt_phone" class="form-control" dir="ltr" value="<?php echo e($fam['mother_alt_phone'] ?? ''); ?>"></div>
<div class="col-md-4"><label class="form-label">الرقم الوطني (الأم)</label><input type="text" name="mother_national_id" class="form-control" dir="ltr" value="<?php echo e($fam['mother_national_id'] ?? ''); ?>"></div>
<div class="col-md-4"><label class="form-label">الحالة الاجتماعية للأم</label>
<select name="mother_marital_status" class="form-select">
<?php foreach ($AK_ORPHAN_OPTS['marital'] as $o): ?>
<option value="<?php echo e($o); ?>" <?php echo ($fam['mother_marital_status'] ?? 'أرملة') === $o ? 'selected' : ''; ?>><?php echo e($o); ?></option>
<?php endforeach; ?>
</select></div>
<div class="col-md-4"><label class="form-label">اسم الأب (والد الأطفال)</label><input type="text" name="father_name" class="form-control" value="<?php echo e($fam['father_name'] ?? ''); ?>"></div>
<div class="col-md-3"><label class="form-label">تاريخ وفاة الأب</label><input type="date" name="father_death_date" class="form-control" value="<?php echo e($fam['father_death_date'] ?? ''); ?>"></div>
<div class="col-md-3"><label class="form-label">سبب وفاة الأب</label><input type="text" name="father_death_cause" class="form-control" value="<?php echo e($fam['father_death_cause'] ?? ''); ?>"></div>
<div class="col-md-3"><label class="form-label">مهنة الأم</label><input type="text" name="mother_job" class="form-control" value="<?php echo e($fam['mother_job'] ?? ''); ?>"></div>
<div class="col-md-3"><label class="form-label">مكان العمل</label><input type="text" name="mother_workplace" class="form-control" value="<?php echo e($fam['mother_workplace'] ?? ''); ?>"></div>
<div class="col-md-4"><label class="form-label">تاريخ ميلاد الأم</label><input type="date" name="mother_birth_date" class="form-control" value="<?php echo e($fam['mother_birth_date'] ?? ''); ?>"></div>
<div class="col-md-3"><label class="form-label">المدينة</label><input type="text" name="city" class="form-control" value="<?php echo e($fam['city'] ?? ''); ?>"></div>
<div class="col-md-3"><label class="form-label">الحي/المنطقة</label><input type="text" name="district" class="form-control" value="<?php echo e($fam['district'] ?? ''); ?>"></div>
<div class="col-md-3"><label class="form-label">الاحتياج الشهري (ج.س)</label><input type="text" inputmode="decimal" name="monthly_need_amount" class="form-control" value="<?php echo e((string)($fam['monthly_need_amount'] ?? '')); ?>"></div>
<div class="col-md-9"><label class="form-label">العنوان</label><input type="text" name="address" class="form-control" value="<?php echo e($fam['address'] ?? ''); ?>"></div>
<div class="col-md-3"><label class="form-label">الحالة</label>
<select name="status" class="form-select">
<?php foreach ($statuses as $k => $label): ?>
<option value="<?php echo $k; ?>" <?php echo $fam['status'] === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
<?php endforeach; ?>
</select></div>
<?php if ($supervisors): ?>
<div class="col-md-4"><label class="form-label">المشرف المسؤول</label>
<select name="supervisor_id" class="form-select"><option value="">— غير معين —</option>
<?php foreach ($supervisors as $s): ?>
<option value="<?php echo (int)$s['id']; ?>" <?php echo (int)($fam['supervisor_id'] ?? 0) === (int)$s['id'] ? 'selected' : ''; ?>><?php echo e($s['full_name']); ?></option>
<?php endforeach; ?>
</select></div>
<?php endif; ?>
<?php if ($nannies): ?>
<div class="col-md-4"><label class="form-label">أخصائية شؤون الأمهات</label>
<select name="nanny_id" class="form-select"><option value="">— غير معين —</option>
<?php foreach ($nannies as $nn): ?>
<option value="<?php echo (int)$nn['id']; ?>" <?php echo (int)($fam['nanny_id'] ?? 0) === (int)$nn['id'] ? 'selected' : ''; ?>><?php echo e($nn['full_name']); ?></option>
<?php endforeach; ?>
</select></div>
<?php endif; ?>
<div class="col-md-4"><label class="form-label">ملاحظات</label><input type="text" name="notes" class="form-control" value="<?php echo e($fam['notes'] ?? ''); ?>"></div>
</div>
<div class="mt-3"><button name="update_family" value="1" class="btn btn-primary"><i class="fas fa-save me-1"></i>حفظ بيانات الأسرة</button></div>
</form>
</div>
</div>
<div class="card mb-4 fade-in">
<div class="card-header"><i class="fas fa-building-columns me-2"></i>الحسابات البنكية</div>
<div class="card-body">
<div class="table-responsive mb-4">
<table class="table table-hover align-middle">
<thead><tr><th>اسم البنك</th><th>الفرع</th><th>رقم الحساب</th><th>اسم صاحب الحساب</th><th>رئيسي</th><th class="text-center">حذف</th></tr></thead>
<tbody>
<?php if (!$bankAccounts): ?>
<tr><td colspan="6" class="text-center text-muted py-3">لا توجد حسابات بنكية مسجلة.</td></tr>
<?php else: foreach ($bankAccounts as $b): ?>
<tr>
<td><strong><?php echo e($b['bank_name']); ?></strong></td>
<td><?php echo e($b['bank_branch'] ?? '-'); ?></td>
<td dir="ltr"><?php echo e($b['account_number']); ?></td>
<td><?php echo e($b['account_holder'] ?? '-'); ?></td>
<td><?php echo $b['is_primary'] ? '<span class="badge bg-success">رئيسي</span>' : '—'; ?></td>
<td class="text-center"><span class="text-muted small">عبر إدارة الحسابات</span></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
<form method="post" class="row g-2">
<?php echo csrf_field(); ?>
<input type="hidden" name="id" value="<?php echo $id; ?>">
<div class="col-md-3"><input type="text" name="bank_name" class="form-control" placeholder="اسم البنك"></div>
<div class="col-md-2"><input type="text" name="bank_branch" class="form-control" placeholder="الفرع"></div>
<div class="col-md-3"><input type="text" name="account_number" class="form-control" placeholder="رقم الحساب" dir="ltr"></div>
<div class="col-md-3"><input type="text" name="account_holder" class="form-control" placeholder="اسم صاحب الحساب"></div>
<div class="col-md-1"><button name="add_bank" value="1" class="btn btn-primary w-100"><i class="fas fa-plus"></i></button></div>
</form>
</div>
</div>
<div class="card fade-in">
<div class="card-header"><i class="fas fa-children me-2"></i>الأطفال</div>
<div class="card-body">
<div class="table-responsive mb-4">
<table class="table table-hover align-middle">
<thead><tr><th>الاسم</th><th>تاريخ الميلاد</th><th>النوع</th><th>الرقم الوطني</th><th>التعليم</th><th>احتياج طبي</th><th>كفالة شهرية</th><th>إضافة</th><th class="text-center">استمارة</th><th class="text-center">تعديل</th><th class="text-center">ملف اليتيم</th></tr></thead>
<tbody>
<?php if (!$children): ?>
<tr><td colspan="11" class="text-center text-muted py-3">لا يوجد أطفال.</td></tr>
<?php else: foreach ($children as $c): ?>
<tr>
<td><strong><?php echo e($c['child_name']); ?></strong></td>
<td><?php echo e($c['birth_date'] ?? '-'); ?></td>
<td><?php echo ['male' => 'ذكر', 'female' => 'أنثى', 'unknown' => '—'][$c['gender']] ?? '—'; ?></td>
<td dir="ltr"><?php echo e($c['national_id'] ?? '—'); ?></td>
<td><?php echo e($c['education_level'] ?? '-'); ?></td>
<td><?php echo $c['need_name'] ? '<span class="badge bg-warning text-dark">' . e($c['need_name']) . '</span>' : '-'; ?></td>
<td><?php echo $c['monthly_sponsorship_value'] !== null ? number_format((float)$c['monthly_sponsorship_value'], 0) : '—'; ?></td>
<td><?php echo $c['extra_allowance'] !== null ? number_format((float)$c['extra_allowance'], 0) : '—'; ?></td>
<td class="text-center">
<a class="btn btn-sm btn-success" href="<?php echo APP_URL; ?>modules/families/orphan_form.php?child=<?php echo (int)$c['id']; ?>"><i class="fas fa-file-signature me-1"></i>استمارة</a>
</td>
<td class="text-center">
<button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="collapse" data-bs-target="#editChild<?php echo (int)$c['id']; ?>"><i class="fas fa-pen"></i></button>
</td>
<td class="text-center">
<a class="btn btn-sm btn-primary" href="<?php echo APP_URL; ?>modules/families/orphan_profile.php?child=<?php echo (int)$c['id']; ?>"><i class="fas fa-folder-open me-1"></i>ملف</a>
</td>
</tr>
<tr><td colspan="11" class="p-0 border-0 bg-transparent">
<div class="collapse mt-1 mb-2" id="editChild<?php echo (int)$c['id']; ?>">
<form method="post" class="row g-2 bg-light p-2 rounded border">
<?php echo csrf_field(); ?>
<input type="hidden" name="id" value="<?php echo $id; ?>">
<input type="hidden" name="update_child" value="<?php echo (int)$c['id']; ?>">
<div class="col-md-3"><label class="form-label small mb-1">اسم الطفل *</label><input type="text" name="child_name" class="form-control form-control-sm" required value="<?php echo e($c['child_name']); ?>"></div>
<div class="col-md-2"><label class="form-label small mb-1">الرقم الوطني</label><input type="text" name="national_id" class="form-control form-control-sm" dir="ltr" value="<?php echo e($c['national_id'] ?? ''); ?>"></div>
<div class="col-md-2"><label class="form-label small mb-1">تاريخ الميلاد</label><input type="date" name="birth_date" class="form-control form-control-sm" value="<?php echo e($c['birth_date'] ?? ''); ?>"></div>
<div class="col-md-1"><label class="form-label small mb-1">النوع</label>
<select name="gender" class="form-select form-select-sm">
<option value="unknown" <?php echo ($c['gender'] ?? 'unknown') === 'unknown' ? 'selected' : ''; ?>>—</option>
<option value="male" <?php echo ($c['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>ذكر</option>
<option value="female" <?php echo ($c['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>أنثى</option>
</select></div>
<div class="col-md-2"><label class="form-label small mb-1">التعليم</label><input type="text" name="education_level" class="form-control form-control-sm" value="<?php echo e($c['education_level'] ?? ''); ?>"></div>
<div class="col-md-2"><label class="form-label small mb-1">كفالة شهرية (ج.س)</label><input type="number" step="100" min="0" name="monthly_sponsorship_value" class="form-control form-control-sm" value="<?php echo e((string)($c['monthly_sponsorship_value'] ?? '')); ?>"></div>
<div class="col-md-2"><label class="form-label small mb-1">إضافة شهرية (ج.س)</label><input type="number" step="100" min="0" name="extra_allowance" class="form-control form-control-sm" value="<?php echo e((string)($c['extra_allowance'] ?? '')); ?>"></div>
<div class="col-md-3"><label class="form-label small mb-1">الوصي/المعيل</label><input type="text" name="guardian_name" class="form-control form-control-sm" value="<?php echo e($c['guardian_name'] ?? ''); ?>"></div>
<div class="col-md-3"><label class="form-label small mb-1">صلة الوصي</label><input type="text" name="guardian_relationship" class="form-control form-control-sm" value="<?php echo e($c['guardian_relationship'] ?? ''); ?>"></div>
<div class="col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-warning w-100"><i class="fas fa-save me-1"></i>حفظ</button></div>
</form>
</div>
</td></tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
<h6 class="text-primary mb-2"><i class="fas fa-plus me-1"></i>إضافة طفل</h6>
<form method="post" class="row g-2">
<?php echo csrf_field(); ?>
<input type="hidden" name="id" value="<?php echo $id; ?>">
<div class="col-md-3"><label class="form-label">اسم الطفل *</label><input type="text" name="child_name" class="form-control" required></div>
<div class="col-md-2"><label class="form-label">الرقم الوطني</label><input type="text" name="national_id" class="form-control" dir="ltr"></div>
<div class="col-md-2"><label class="form-label">تاريخ الميلاد</label><input type="date" name="birth_date" class="form-control"></div>
<div class="col-md-1"><label class="form-label">النوع</label>
<select name="gender" class="form-select"><option value="unknown">—</option><option value="male">ذكر</option><option value="female">أنثى</option></select></div>
<div class="col-md-2"><label class="form-label">الجنسية</label><input type="text" name="nationality" class="form-control" value="سودانية"></div>
<div class="col-md-2"><label class="form-label">المستوى التعليمي</label><input type="text" name="education_level" class="form-control"></div>
<div class="col-md-2"><label class="form-label">احتياج طبي</label>
<select name="medical_need_id" class="form-select"><option value="">بدون</option>
<?php foreach ($medicalNeeds as $m): ?><option value="<?php echo (int)$m['id']; ?>"><?php echo e($m['name']); ?></option><?php endforeach; ?>
</select></div>
<div class="col-md-2"><label class="form-label">الحالة الصحية</label>
<select name="health_status" class="form-select" onchange="document.getElementById('ch_h_other').style.display = this.value==='أخرى' ? '' : 'none';">
<?php foreach ($AK_ORPHAN_OPTS['health'] as $o): ?><option value="<?php echo e($o); ?>"><?php echo e($o); ?></option><?php endforeach; ?>
</select></div>
<div class="col-md-2"><label class="form-label">إذا أخرى حدد</label><input type="text" name="health_status_other" id="ch_h_other" class="form-control" style="display:none"></div>
<div class="col-md-2"><label class="form-label">الحالة النفسية</label>
<select name="psychological_state" class="form-select" onchange="document.getElementById('ch_p_other').style.display = this.value==='أخرى' ? '' : 'none';">
<?php foreach ($AK_ORPHAN_OPTS['psych'] as $o): ?><option value="<?php echo e($o); ?>"><?php echo e($o); ?></option><?php endforeach; ?>
</select></div>
<div class="col-md-2"><label class="form-label">إذا أخرى حدد</label><input type="text" name="psychological_state_other" id="ch_p_other" class="form-control" style="display:none"></div>
<div class="col-md-3"><label class="form-label">اسم الوصي/المعيل</label><input type="text" name="guardian_name" class="form-control"></div>
<div class="col-md-3"><label class="form-label">صلة الوصي باليتيم</label>
<input type="text" name="guardian_relationship" list="akRelList" class="form-control">
<datalist id="akRelList"><?php foreach ($AK_ORPHAN_OPTS['relationships'] as $o): ?><option value="<?php echo e($o); ?>"><?php endforeach; ?></datalist></div>
<div class="col-md-3"><label class="form-label">قيمة الكفالة الشهرية (ج.س)</label><input type="number" step="100" min="0" name="monthly_sponsorship_value" class="form-control"></div>
<div class="col-md-3"><label class="form-label">إضافة شهرية (ج.س)</label><input type="number" step="100" min="0" name="extra_allowance" class="form-control"></div>
<div class="col-md-12 mt-2"><button name="add_child" value="1" class="btn btn-primary"><i class="fas fa-plus me-1"></i>إضافة الطفل</button></div>
</form>
</div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>