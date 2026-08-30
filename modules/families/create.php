<?php
// modules/families/create.php - Create family + optional multiple bank accounts
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = 'إضافة أسرة';
$active    = 'families';
$uid = Session::getUserId();

/* ---------- schema sync (idempotent) ---------- */
try {
    dbExecute("CREATE TABLE IF NOT EXISTS family_bank_accounts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
} catch (Throwable $e) { error_log('Families schema sync: ' . $e->getMessage()); }

$statuses = ['pending' => 'قيد الانتظار', 'active' => 'نشطة', 'paused' => 'متوقفة', 'completed' => 'مكتملة',
    'archived' => 'مؤرشفة', 'inactive' => 'غير نشطة', 'closed' => 'مغلقة'];

$supervisors = in_array($role, ['admin', 'vice_general_manager'], true)
    ? dbFetchAll("SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'supervisor' ORDER BY u.full_name") : [];
$nannies = in_array($role, ['admin', 'vice_general_manager'], true)
    ? dbFetchAll("SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'nanny' AND u.is_active = 1 ORDER BY u.full_name") : [];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $name = trim($_POST['mother_name'] ?? '');
    if ($name === '') $errors[] = 'اسم الأم مطلوب.';

    if (!$errors) {
        /* unique family code */
        $n = (int)dbFetchOne("SELECT COUNT(*) c FROM families")['c'] + 1;
        do {
            $code = 'FAM-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
            $exists = dbFetchOne("SELECT id FROM families WHERE family_code = ?", [$code]);
            if ($exists) $n++;
        } while ($exists);

        [$rawLetter, $normLetter] = first_letter_of($name);

        $supId = $role === 'supervisor' ? $uid
            : (isset($_POST['supervisor_id']) && $_POST['supervisor_id'] !== '' ? (int)$_POST['supervisor_id'] : null);
        $nannyId = ($role !== 'supervisor' && isset($_POST['nanny_id']) && $_POST['nanny_id'] !== '')
            ? (int)$_POST['nanny_id'] : null;

        dbExecute("INSERT INTO families (mother_name, mother_phone, mother_alt_phone, mother_job, mother_workplace,
            mother_national_id, mother_birth_date, father_name, father_death_date,
            city, district, address, monthly_need_amount, status, notes,
            supervisor_id, nanny_id, family_code, legacy_mother_first_letter, created_by, assigned_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$name,
            trim($_POST['mother_phone'] ?? '') !== '' ? trim($_POST['mother_phone']) : null,
            trim($_POST['mother_alt_phone'] ?? '') !== '' ? trim($_POST['mother_alt_phone']) : null,
            trim($_POST['mother_job'] ?? '') !== '' ? trim($_POST['mother_job']) : null,
            trim($_POST['mother_workplace'] ?? '') !== '' ? trim($_POST['mother_workplace']) : null,
            trim($_POST['mother_national_id'] ?? '') !== '' ? trim($_POST['mother_national_id']) : null,
            trim($_POST['mother_birth_date'] ?? '') !== '' ? trim($_POST['mother_birth_date']) : null,
            trim($_POST['father_name'] ?? '') !== '' ? trim($_POST['father_name']) : null,
            trim($_POST['father_death_date'] ?? '') !== '' ? trim($_POST['father_death_date']) : null,
            trim($_POST['city'] ?? '') !== '' ? trim($_POST['city']) : null,
            trim($_POST['district'] ?? '') !== '' ? trim($_POST['district']) : null,
            trim($_POST['address'] ?? '') !== '' ? trim($_POST['address']) : null,
            trim($_POST['monthly_need_amount'] ?? '') !== '' ? (float)str_replace(',', '', $_POST['monthly_need_amount']) : null,
            array_key_exists($_POST['status'] ?? '', $statuses) ? $_POST['status'] : 'pending',
            trim($_POST['notes'] ?? '') !== '' ? trim($_POST['notes']) : null,
            $supId, $nannyId, $code, $rawLetter, $uid, $supId]);

        $newId = (int)dbFetchOne("SELECT LAST_INSERT_ID() id")['id'];

        /* ---- bank accounts (multiple) ---- */
        $bnArr = $_POST['bank_name'] ?? [];
        $brArr = $_POST['bank_branch'] ?? [];
        $acArr = $_POST['account_number'] ?? [];
        $ahArr = $_POST['account_holder'] ?? [];
        $first = null;
        for ($i = 0; $i < count($bnArr); $i++) {
            $bn = trim((string)($bnArr[$i] ?? ''));
            $ac = trim((string)($acArr[$i] ?? ''));
            if ($bn !== '' && $ac !== '') {
                $isPrimary = ($first === null) ? 1 : 0;
                dbExecute("INSERT INTO family_bank_accounts (family_id, bank_name, bank_branch, account_number, account_holder, is_primary)
                    VALUES (?, ?, ?, ?, ?, ?)",
                    [$newId, $bn, trim((string)($brArr[$i] ?? '')) ?: null, $ac, trim((string)($ahArr[$i] ?? '')) ?: null, $isPrimary]);
                if ($first === null) $first = [$bn, $ac, trim((string)($brArr[$i] ?? '')) ?: null, trim((string)($ahArr[$i] ?? '')) ?: null];
            }
        }
        if ($first) {
            dbExecute("UPDATE families SET bank_name=?, bank_account_number=?, bank_branch=?, bank_account_holder=? WHERE id=?",
                [$first[0], $first[1], $first[2], $first[3], $newId]);
        }

        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, 'CREATE', 'families', ?, NULL, ?, ?, ?)",
            [$uid, $newId, json_encode(['mother_name' => $name, 'code' => $code], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);

        flash('success', 'تمت إضافة الأسرة: ' . $name . ' (' . $code . ')');
        header('Location: ' . APP_URL . 'modules/families/view.php?id=' . $newId); exit();
    }
}
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>إضافة أسرة جديدة</h2>
</div>
<?php if ($errors): ?>
<div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-body">
        <form method="post">
            <?php echo csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">اسم الأم *</label>
                    <input type="text" name="mother_name" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">الهاتف</label>
                    <input type="text" name="mother_phone" class="form-control" dir="ltr"></div>
                <div class="col-md-3"><label class="form-label">هاتف بديل</label>
                    <input type="text" name="mother_alt_phone" class="form-control" dir="ltr"></div>
                <div class="col-md-4"><label class="form-label">الرقم الوطني (الأم)</label>
                    <input type="text" name="mother_national_id" class="form-control" dir="ltr"></div>
                <div class="col-md-4"><label class="form-label">اسم الأب (والد الأطفال)</label>
                    <input type="text" name="father_name" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">تاريخ وفاة الأب</label>
                    <input type="date" name="father_death_date" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">المهنة</label>
                    <input type="text" name="mother_job" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">مكان العمل</label>
                    <input type="text" name="mother_workplace" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">تاريخ ميلاد الأم</label>
                    <input type="date" name="mother_birth_date" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">المدينة</label>
                    <input type="text" name="city" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">الحي/المنطقة</label>
                    <input type="text" name="district" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">الاحتياج الشهري (ج.س)</label>
                    <input type="text" inputmode="decimal" name="monthly_need_amount" class="form-control"></div>
                <div class="col-md-9"><label class="form-label">العنوان</label>
                    <input type="text" name="address" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <?php foreach ($statuses as $k => $label): ?>
                        <option value="<?php echo $k; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <?php if ($supervisors): ?>
                <div class="col-md-4"><label class="form-label">المشرف المسؤول</label>
                    <select name="supervisor_id" class="form-select">
                        <option value="">— غير معين —</option>
                        <?php foreach ($supervisors as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>"><?php echo e($s['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <?php endif; ?>
                <?php if ($nannies): ?>
                <div class="col-md-4"><label class="form-label">أخصائية شؤون الأمهات</label>
                    <select name="nanny_id" class="form-select">
                        <option value="">— غير معين —</option>
                        <?php foreach ($nannies as $nn): ?>
                        <option value="<?php echo (int)$nn['id']; ?>"><?php echo e($nn['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <?php endif; ?>

                <div class="col-12 mt-3"><h6 class="text-primary"><i class="fas fa-building-columns me-2"></i>الحسابات البنكية (اختياري — يمكن إضافة أكثر من حساب)</h6></div>
                <div class="col-12">
                    <div id="bankRows">
                        <div class="row g-2 bank-row mb-2">
                            <div class="col-md-3"><input type="text" name="bank_name[]" class="form-control" placeholder="اسم البنك"></div>
                            <div class="col-md-2"><input type="text" name="bank_branch[]" class="form-control" placeholder="الفرع"></div>
                            <div class="col-md-3"><input type="text" name="account_number[]" class="form-control" placeholder="رقم الحساب" dir="ltr"></div>
                            <div class="col-md-3"><input type="text" name="account_holder[]" class="form-control" placeholder="اسم صاحب الحساب"></div>
                            <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-bank w-100"><i class="fas fa-times"></i></button></div>
                        </div>
                    </div>
                    <button type="button" id="addBankRow" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus me-1"></i>إضافة حساب بنكي آخر</button>
                    <small class="text-muted d-block mt-1">أول حساب يُدخل يصبح الحساب الرئيسي تلقائياً.</small>
                </div>

                <div class="col-12"><label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea></div>
                <div class="col-12">
                    <button class="btn btn-primary btn-lg px-5"><i class="fas fa-save me-1"></i>حفظ الأسرة</button>
                    <a href="<?php echo APP_URL; ?>modules/families/index.php" class="btn btn-secondary btn-lg">إلغاء</a>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('click', function (e) {
    if (e.target.closest('#addBankRow')) {
        var wrap = document.getElementById('bankRows');
        var row = wrap.querySelector('.bank-row').cloneNode(true);
        row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        wrap.appendChild(row);
    }
    var rm = e.target.closest('.remove-bank');
    if (rm) {
        var rows = document.querySelectorAll('#bankRows .bank-row');
        if (rows.length > 1) rm.closest('.bank-row').remove();
    }
});
</script>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>