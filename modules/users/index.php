<?php
// modules/users/index.php - User accounts management (Admin only)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = t('إدارة المستخدمين');
$active    = 'users';
$lang = defined('AK_LANG') ? AK_LANG : 'ar';
$nameCol = ($lang === 'en') ? 'name_en' : 'name_ar';

/* ═══════════════════════════════════════════════════════════
   SCHEMA SYNC
   ═══════════════════════════════════════════════════════════ */
try {
    dbExecute("CREATE TABLE IF NOT EXISTS departments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name_ar VARCHAR(100) NOT NULL,
        name_en VARCHAR(100) NOT NULL,
        description TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Rename legacy "Administration" dept → Lost & Found (idempotent, covers all old variants)
    dbExecute("UPDATE departments SET name_ar = 'استرجاع الكفلاء السابقين', name_en = 'Lost & Found'
               WHERE name_en = 'Administration' OR name_ar = 'الإدارة التنفيذية' OR name_ar = 'المفقودات والاسترجاع'");
    // Rename the staff role display names (code stays 'administration')
    dbExecute("UPDATE roles SET name_ar = 'مندوب استرجاع الكفلاء', name_en = 'Recovery Officer' WHERE code = 'administration'");

    $depts = [
        ['الإدارة العامة', 'General Management'],
        ['شؤون الأمهات', 'Mothers Affairs'],
        ['استرجاع الكفلاء السابقين', 'Lost & Found'],
        ['العلاقات العامة والإعلام', 'Social Media'],
        ['شؤون الكفلاء', 'Sponsor Affairs'],
        ['المالية والمحاسبة', 'Accounting & Finance'],
    ];
    foreach ($depts as $d) {
        dbExecute("INSERT INTO departments (name_ar, name_en) SELECT ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name_en = ?)", [$d[0], $d[1], $d[1]]);
    }

    dbExecute("UPDATE roles SET name_ar = 'رئيس قسم المحاسبة', name_en = 'Accounting Dept Head' WHERE code = 'accountant'");

    $newRoles = [
        ['financial_manager', 'Financial Manager', 'المدير المالي'],
        ['accountant_staff',  'Accountant', 'محاسب'],
        ['nanny',             'Nanny / Case Worker', 'أخصائية شؤون الأمهات'],
        ['administration',    'Recovery Officer', 'مندوب استرجاع الكفلاء'],
        ['social_media',      'Social Media Staff', 'موظف العلاقات العامة والإعلام'],
    ];
    foreach ($newRoles as $r) {
        dbExecute("INSERT INTO roles (code, name_en, name_ar) SELECT ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM roles WHERE code = ?)", [$r[0], $r[1], $r[2], $r[0]]);
    }

    dbExecute("ALTER TABLE users ADD COLUMN IF NOT EXISTS department_id INT UNSIGNED NULL");
    dbExecute("ALTER TABLE users ADD COLUMN IF NOT EXISTS manager_id INT UNSIGNED NULL");
    dbExecute("ALTER TABLE sponsors ADD COLUMN IF NOT EXISTS gender ENUM('male','female','organization','unknown') DEFAULT 'unknown'");
    dbExecute("ALTER TABLE families ADD COLUMN IF NOT EXISTS nanny_id INT UNSIGNED NULL");
    dbExecute("ALTER TABLE supervisor_letters ADD COLUMN IF NOT EXISTS gender ENUM('male','female','both') DEFAULT 'both'");
    dbExecute("CREATE TABLE IF NOT EXISTS family_documents (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        family_id INT UNSIGNED NOT NULL,
        document_type VARCHAR(50) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_by INT UNSIGNED NOT NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dbExecute("CREATE TABLE IF NOT EXISTS accountant_nanny_assignments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        accountant_id INT UNSIGNED NOT NULL,
        nanny_id INT UNSIGNED NOT NULL,
        assigned_by INT UNSIGNED NULL,
        assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_acct_nanny (accountant_id, nanny_id),
        FOREIGN KEY (accountant_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (nanny_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    error_log('Schema sync: ' . $e->getMessage());
}

/* ═══════════════════════════════════════════════════════════
   PAGE DATA
   ═══════════════════════════════════════════════════════════ */
$roles       = dbFetchAll("SELECT id, code, name_ar, name_en FROM roles ORDER BY id");
$departments = dbFetchAll("SELECT id, name_ar, name_en FROM departments ORDER BY id");
$managers    = dbFetchAll("SELECT u.id, u.full_name, u.username FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code IN ('admin','sudo','general_manager','vice_general_manager','financial_manager','accountant') AND u.is_active = 1 ORDER BY u.full_name");
$nannies     = dbFetchAll("SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'nanny' AND u.is_active = 1 ORDER BY u.full_name");
$allowedRoles = array_column($roles, 'code');
$errors = [];

$editId = (int)($_GET['edit'] ?? 0);
$editUser = $editId ? dbFetchOne("SELECT u.*, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?", [$editId]) : null;

$manageAcctId = (int)($_GET['manage_assignments'] ?? 0);
$manageAcct = $manageAcctId ? dbFetchOne("SELECT u.id, u.full_name FROM users u WHERE u.id = ? AND u.role_id = (SELECT id FROM roles WHERE code = 'accountant_staff')", [$manageAcctId]) : null;

if ($manageAcct && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assignments']) && verify_csrf()) {
    $selectedNannies = array_map('intval', $_POST['nanny_ids'] ?? []);
    dbExecute("DELETE FROM accountant_nanny_assignments WHERE accountant_id = ?", [$manageAcct['id']]);
    foreach ($selectedNannies as $nid) {
        dbExecute("INSERT IGNORE INTO accountant_nanny_assignments (accountant_id, nanny_id, assigned_by) VALUES (?, ?, ?)", [$manageAcct['id'], $nid, Session::getUserId()]);
    }
    flash('success', 'تم تحديث تعيينات الأخصائيات للمحاسب.');
    header('Location: ' . APP_URL . 'modules/users/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$manageAcct) {
    if (!verify_csrf()) {
        $errors[] = 'انتهت صلاحية الجلسة.';
    } else {
        if (isset($_POST['create_user'])) {
            $fn = trim($_POST['full_name'] ?? '');
            $un = trim($_POST['username'] ?? '');
            $pw = $_POST['password'] ?? '';
            $rc = $_POST['role_code'] ?? '';
            $em = trim($_POST['email'] ?? '');
            $ph = trim($_POST['phone'] ?? '');
            $gn = in_array($_POST['gender'] ?? '', ['male','female'], true) ? $_POST['gender'] : null;
            $dept = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $mgr = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;

            if ($fn === '') $errors[] = 'الاسم الكامل مطلوب.';
            if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $un)) $errors[] = 'اسم المستخدم غير صالح.';
            if (strlen($pw) < 6) $errors[] = 'كلمة المرور 6 أحرف على الأقل.';
            if (!in_array($rc, $allowedRoles, true)) $errors[] = 'دور غير صالح.';
            if (!$errors && dbFetchOne("SELECT id FROM users WHERE username = ?", [$un])) $errors[] = 'اسم المستخدم موجود.';

            if (!$errors) {
                $roleId = (int)array_column($roles, 'id', 'code')[$rc];
                dbExecute("INSERT INTO users (role_id, username, password_hash, full_name, email, phone, is_active, created_by, department_id, manager_id, gender) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)",
                    [$roleId, $un, password_hash($pw, PASSWORD_DEFAULT), $fn, $em !== '' ? $em : null, $ph !== '' ? $ph : null, Session::getUserId(), $dept, $mgr, $gn]);
                flash('success', 'تم إنشاء المستخدم: ' . $un);
                header('Location: ' . APP_URL . 'modules/users/index.php'); exit();
            }
        }

        if (isset($_POST['update_user'])) {
            $uid = (int)$_POST['user_id'];
            $fn = trim($_POST['full_name'] ?? '');
            $em = trim($_POST['email'] ?? '');
            $ph = trim($_POST['phone'] ?? '');
            $rc = $_POST['role_code'] ?? '';
            $dept = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $mgr = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
            $gender = in_array($_POST['gender'] ?? '', ['male','female'], true) ? $_POST['gender'] : null;
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($fn === '') $errors[] = 'الاسم الكامل مطلوب.';
            if (!in_array($rc, $allowedRoles, true)) $errors[] = 'دور غير صالح.';

            if (!$errors) {
                $roleId = (int)array_column($roles, 'id', 'code')[$rc];
                dbExecute("UPDATE users SET full_name=?, email=?, phone=?, role_id=?, department_id=?, manager_id=?, gender=?, is_active=?, updated_at=NOW() WHERE id=?",
                    [$fn, $em !== '' ? $em : null, $ph !== '' ? $ph : null, $roleId, $dept, $mgr, $gender, $isActive, $uid]);
                flash('success', 'تم تحديث بيانات المستخدم.');
                header('Location: ' . APP_URL . 'modules/users/index.php'); exit();
            }
        }

        if (isset($_POST['toggle_user'])) {
            $id = (int)$_POST['toggle_user'];
            if ($id !== Session::getUserId()) {
                $u = dbFetchOne("SELECT id, is_active FROM users WHERE id = ?", [$id]);
                if ($u) {
                    dbExecute("UPDATE users SET is_active = ? WHERE id = ?", [((int)$u['is_active'] === 1) ? 0 : 1, $id]);
                    flash('success', 'تم تحديث الحالة.');
                }
            }
            header('Location: ' . APP_URL . 'modules/users/index.php'); exit();
        }

        if (isset($_POST['reset_user'])) {
            $id = (int)$_POST['reset_user'];
            $pw = $_POST['new_password'] ?? '';
            if ($id !== Session::getUserId() && strlen($pw) >= 6) {
                dbExecute("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($pw, PASSWORD_DEFAULT), $id]);
                flash('success', 'تمت إعادة تعيين كلمة المرور.');
            }
            header('Location: ' . APP_URL . 'modules/users/index.php'); exit();
        }
    }
}

$users = dbFetchAll("SELECT u.id, u.username, u.full_name, u.email, u.phone, u.is_active, u.last_login_at, u.gender, u.role_id,
    r.code AS role_code, r.name_ar AS role_name, r.name_en AS role_en,
    d.name_ar AS dept_name, d.name_en AS dept_en, m.full_name AS manager_name
    FROM users u JOIN roles r ON r.id = u.role_id
    LEFT JOIN departments d ON d.id = u.department_id
    LEFT JOIN users m ON m.id = u.manager_id
    ORDER BY u.id");

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><?php echo t('إدارة المستخدمين'); ?></h2>
    <p><?php echo t('إنشاء الحسابات وتعيين الأدوار والأقسام وإدارة الحالة وكلمات المرور'); ?></p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?>
<div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
<?php endif; ?>

<?php if ($manageAcct): ?>
<div class="card mb-4 fade-in border-info">
    <div class="card-header bg-info text-white"><i class="fas fa-users-gear me-2"></i>تعيين الأخصائيات للمحاسب: <?php echo e($manageAcct['full_name']); ?></div>
    <div class="card-body">
        <?php
        $assignedNannies = dbFetchAll("SELECT nanny_id FROM accountant_nanny_assignments WHERE accountant_id = ?", [$manageAcct['id']]);
        $assignedIds = array_column($assignedNannies, 'nanny_id');
        ?>
        <form method="post">
            <?php echo csrf_field(); ?>
            <div class="row g-2 mb-3">
                <?php foreach ($nannies as $n): ?>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="nanny_ids[]" value="<?php echo (int)$n['id']; ?>" id="nanny_<?php echo $n['id']; ?>" <?php echo in_array($n['id'], $assignedIds) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="nanny_<?php echo $n['id']; ?>"><?php echo e($n['full_name']); ?></label>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($nannies)): ?>
                <div class="col-12"><p class="text-muted">لا توجد أخصائيات في النظام بعد.</p></div>
                <?php endif; ?>
            </div>
            <button name="update_assignments" value="1" class="btn btn-success btn-lg"><i class="fas fa-save me-1"></i>حفظ التعيينات</button>
            <a href="<?php echo APP_URL; ?>modules/users/index.php" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-right me-1"></i>رجوع</a>
        </form>
    </div>
</div>

<?php elseif ($editUser): ?>
<div class="card mb-4 fade-in border-warning">
    <div class="card-header bg-warning text-dark"><i class="fas fa-user-edit me-2"></i><?php echo t('تعديل المستخدم:'); ?> <?php echo e($editUser['username']); ?></div>
    <div class="card-body">
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label"><?php echo t('الاسم الكامل'); ?> *</label><input type="text" name="full_name" class="form-control form-control-lg" value="<?php echo e($editUser['full_name']); ?>" required></div>
                <div class="col-md-3"><label class="form-label"><?php echo t('البريد الإلكتروني'); ?></label><input type="email" name="email" class="form-control form-control-lg" dir="ltr" value="<?php echo e($editUser['email'] ?? ''); ?>"></div>
                <div class="col-md-3"><label class="form-label"><?php echo t('الهاتف'); ?></label><input type="text" name="phone" class="form-control form-control-lg" dir="ltr" value="<?php echo e($editUser['phone'] ?? ''); ?>"></div>
                <div class="col-md-3">
                    <label class="form-label"><?php echo t('الدور'); ?> *</label>
                    <select name="role_code" class="form-select form-select-lg" required>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?php echo e($r['code']); ?>" <?php echo $editUser['role_code'] === $r['code'] ? 'selected' : ''; ?>><?php echo e($r[$nameCol]); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?php echo t('القسم'); ?></label>
                    <select name="department_id" class="form-select form-select-lg">
                        <option value=""><?php echo t('— غير معين —'); ?></option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?php echo (int)$d['id']; ?>" <?php echo ($editUser['department_id'] ?? 0) == $d['id'] ? 'selected' : ''; ?>><?php echo e($d[$nameCol]); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?php echo t('المسؤول المباشر'); ?></label>
                    <select name="manager_id" class="form-select form-select-lg">
                        <option value=""><?php echo t('— غير معين —'); ?></option>
                        <?php foreach ($managers as $m): ?>
                        <option value="<?php echo (int)$m['id']; ?>" <?php echo ($editUser['manager_id'] ?? 0) == $m['id'] ? 'selected' : ''; ?>><?php echo e(t($m['full_name'])); ?> (<?php echo e($m['username']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?php echo t('الجنس'); ?></label>
                    <select name="gender" class="form-select form-select-lg">
                        <option value=""><?php echo t('— الجنس —'); ?></option>
                        <option value="male" <?php echo ($editUser['gender'] ?? '') === 'male' ? 'selected' : ''; ?>><?php echo t('ذكر'); ?></option>
                        <option value="female" <?php echo ($editUser['gender'] ?? '') === 'female' ? 'selected' : ''; ?>><?php echo t('أنثى'); ?></option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="editActive" <?php echo $editUser['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="editActive"><?php echo t('الحساب نشط'); ?></label>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button name="update_user" value="1" class="btn btn-warning btn-lg"><i class="fas fa-save me-1"></i><?php echo t('حفظ التعديلات'); ?></button>
                    <a href="<?php echo APP_URL; ?>modules/users/index.php" class="btn btn-secondary btn-lg"><i class="fas fa-times me-1"></i><?php echo t('إلغاء'); ?></a>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4 fade-in">
    <div class="card-header"><i class="fas fa-user-plus me-2"></i><?php echo t('إضافة مستخدم'); ?></div>
    <div class="card-body">
        <form method="post">
            <?php echo csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label"><?php echo t('الاسم الكامل'); ?> *</label><input type="text" name="full_name" class="form-control form-control-lg" required></div>
                <div class="col-md-3"><label class="form-label"><?php echo t('اسم المستخدم'); ?> *</label><input type="text" name="username" class="form-control form-control-lg" dir="ltr" required></div>
                <div class="col-md-3"><label class="form-label"><?php echo t('كلمة المرور'); ?> *</label><input type="text" name="password" class="form-control form-control-lg" dir="ltr" required></div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo t('الدور'); ?> *</label>
                    <select name="role_code" class="form-select form-select-lg" required>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?php echo e($r['code']); ?>"><?php echo e($r[$nameCol]); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo t('القسم'); ?></label>
                    <select name="department_id" class="form-select form-select-lg">
                        <option value=""><?php echo t('— القسم —'); ?></option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?php echo (int)$d['id']; ?>"><?php echo e($d[$nameCol]); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo t('المسؤول المباشر'); ?></label>
                    <select name="manager_id" class="form-select form-select-lg">
                        <option value=""><?php echo t('— المسؤول المباشر —'); ?></option>
                        <?php foreach ($managers as $m): ?>
                        <option value="<?php echo (int)$m['id']; ?>"><?php echo e(t($m['full_name'])); ?> (<?php echo e($m['username']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label"><?php echo t('البريد الإلكتروني'); ?></label><input type="email" name="email" class="form-control form-control-lg" dir="ltr"></div>
                <div class="col-md-4"><label class="form-label"><?php echo t('الهاتف'); ?></label><input type="text" name="phone" class="form-control form-control-lg" dir="ltr"></div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo t('الجنس'); ?></label>
                    <select name="gender" class="form-select form-select-lg">
                        <option value=""><?php echo t('— الجنس —'); ?></option>
                        <option value="male"><?php echo t('ذكر'); ?></option>
                        <option value="female"><?php echo t('أنثى'); ?></option>
                    </select>
                </div>
                <div class="col-12">
                    <button name="create_user" value="1" class="btn btn-primary btn-lg px-5"><i class="fas fa-plus me-1"></i><?php echo t('إضافة مستخدم'); ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card fade-in">
    <div class="card-header"><i class="fas fa-users me-2"></i><?php echo t('حسابات المستخدمين'); ?> (<?php echo count($users); ?>)</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>#</th><th><?php echo t('الاسم الكامل'); ?></th><th><?php echo t('اسم المستخدم'); ?></th><th><?php echo t('الدور'); ?></th><th><?php echo t('القسم'); ?></th><th><?php echo t('المسؤول'); ?></th><th><?php echo t('آخر دخول'); ?></th><th><?php echo t('الحالة'); ?></th><th class="text-center"><?php echo t('إجراءات'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo (int)$u['id']; ?></td>
                        <td><strong><?php echo e($u['full_name']); ?></strong></td>
                        <td><?php echo e($u['username']); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo e($u[$lang === 'en' ? 'role_en' : 'role_name']); ?></span></td>
                        <td><?php echo e($u[$lang === 'en' ? 'dept_en' : 'dept_name'] ?? '—'); ?></td>
                        <td><?php echo e(t($u['manager_name'] ?? '—')); ?></td>
                        <td><?php echo e($u['last_login_at'] ?? '—'); ?></td>
                        <td><?php echo (int)$u['is_active'] === 1 ? '<span class="badge bg-success">' . t('نشط') . '</span>' : '<span class="badge bg-danger">' . t('موقوف') . '</span>'; ?></td>
                        <td class="text-center" style="white-space:nowrap;">
                            <a href="?edit=<?php echo (int)$u['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="<?php echo t('تعديل'); ?>"><i class="fas fa-edit"></i></a>
                            <?php if ($u['role_code'] === 'accountant_staff'): ?>
                            <a href="?manage_assignments=<?php echo (int)$u['id']; ?>" class="btn btn-sm btn-outline-info me-1" title="إدارة التعيينات"><i class="fas fa-users-gear"></i></a>
                            <?php endif; ?>
                            <form method="post" class="d-inline">
                                <?php echo csrf_field(); ?>
                                <button name="toggle_user" value="<?php echo (int)$u['id']; ?>" class="btn btn-sm <?php echo (int)$u['is_active'] === 1 ? 'btn-danger' : 'btn-success'; ?>" onclick="return confirm('تغيير الحالة؟')">
                                    <i class="fas <?php echo (int)$u['is_active'] === 1 ? 'fa-pause' : 'fa-play'; ?>"></i>
                                </button>
                            </form>
                            <form method="post" class="d-inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="reset_user" value="<?php echo (int)$u['id']; ?>">
                                <input type="text" name="new_password" class="form-control form-control-sm d-inline-block w-auto" placeholder="<?php echo t('كلمة مرور جديدة'); ?>" dir="ltr" style="width:130px!important" required>
                                <button class="btn btn-sm btn-warning" title="<?php echo t('تأكيد'); ?>"><i class="fas fa-key"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>