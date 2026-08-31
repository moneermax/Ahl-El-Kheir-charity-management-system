<<<<<<< HEAD
<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }

// Auto-add text column for "Brought By" if it doesn't exist
dbExecute("ALTER TABLE sponsors ADD COLUMN IF NOT EXISTS brought_by_name VARCHAR(255) NULL");

$pageTitle = 'إضافة كفيل';
$active = 'sponsors';
$errors = [];
$input = [
    'full_name' => '', 'email' => '', 'phone' => '', 'phone_purpose' => 'both',
    'alt_phone' => '', 'alt_phone_purpose' => 'both', 'address' => '',
    'sponsor_type' => 'individual', 'gender' => 'unknown', 'payment' => 'cash',
    'status' => 'active', 'desired_orphans' => '', 'notes' => '',
    'acquisition_source' => '', 'brought_by_name' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input['full_name'] = trim($_POST['full_name'] ?? '');
    $input['email'] = trim($_POST['email'] ?? '');
    $input['phone'] = trim($_POST['phone'] ?? '');
    $input['phone_purpose'] = in_array($_POST['phone_purpose'] ?? '', ['call','whatsapp','both'], true) ? $_POST['phone_purpose'] : 'both';
    $input['alt_phone'] = trim($_POST['alt_phone'] ?? '');
    $input['alt_phone_purpose'] = in_array($_POST['alt_phone_purpose'] ?? '', ['call','whatsapp','both'], true) ? $_POST['alt_phone_purpose'] : 'both';
    $input['address'] = trim($_POST['address'] ?? '');
    $input['sponsor_type'] = in_array($_POST['sponsor_type'] ?? '', ['individual','company','organization'], true) ? $_POST['sponsor_type'] : 'individual';
    $input['gender'] = in_array($_POST['gender'] ?? '', ['male','female','organization','unknown'], true) ? $_POST['gender'] : 'unknown';
    $input['payment'] = in_array($_POST['payment'] ?? '', ['cash','bank_transfer','credit_card','mobile','other'], true) ? $_POST['payment'] : 'cash';
    $input['status'] = in_array($_POST['status'] ?? '', ['active','inactive','suspended','cancelled'], true) ? $_POST['status'] : 'active';
    $input['desired_orphans'] = trim($_POST['desired_orphans'] ?? '');
    $input['notes'] = trim($_POST['notes'] ?? '');
    $input['acquisition_source'] = trim($_POST['acquisition_source'] ?? '');
    $input['brought_by_name'] = trim($_POST['brought_by_name'] ?? '');

    if ($input['full_name'] === '') $errors[] = 'اسم الكفيل مطلوب.';
    if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صالح.';

    if (!$errors && verify_csrf()) {
        $letterMap = [];
        foreach (dbFetchAll("SELECT id, code FROM letters WHERE is_active = 1") as $L) $letterMap[normalize_arabic_letter($L['code'])] = (int)$L['id'];
        $matrix = [];
        foreach (dbFetchAll("SELECT sl.supervisor_id, l.code, sl.gender FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id") as $r) {
            $n = normalize_arabic_letter($r['code']);
            $matrix[$n][$r['gender']] = (int)$r['supervisor_id'];
        }
        [$raw, $norm] = first_letter_of($input['full_name']);
        $letterId = $letterMap[$norm] ?? null;
        $supId = null;
        if (isset($matrix[$norm])) {
            $g = $input['gender'];
            if (in_array($g, ['male', 'female'])) $supId = $matrix[$norm][$g] ?? $matrix[$norm]['both'] ?? null;
            else $supId = $matrix[$norm]['both'] ?? $matrix[$norm]['male'] ?? $matrix[$norm]['female'] ?? null;
        }

        $n = (int)(dbFetchOne("SELECT COUNT(*) c FROM sponsors")['c'] ?? 0) + 1;
        $code = 'SP-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
        
        dbExecute(
            "INSERT INTO sponsors (full_name, first_letter_raw, first_letter_id, phone, phone_purpose, alt_phone, alt_phone_purpose, email, address, 
             sponsor_type, gender, preferred_payment_method, status, notes, created_by, sponsor_code, supervisor_id, assigned_by, assigned_at, 
             desired_orphans, is_manual_override, acquisition_source, brought_by_name) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 0, ?, ?)",
            [
                $input['full_name'], $raw, $letterId, 
                $input['phone'] !== '' ? $input['phone'] : null, $input['phone_purpose'],
                $input['alt_phone'] !== '' ? $input['alt_phone'] : null, $input['alt_phone_purpose'],
                $input['email'] !== '' ? $input['email'] : null, $input['address'] !== '' ? $input['address'] : null,
                $input['sponsor_type'], $input['gender'], $input['payment'], $input['status'], 
                $input['notes'] !== '' ? $input['notes'] : null, Session::getUserId(), $code, $supId, Session::getUserId(), 
                $input['desired_orphans'] !== '' ? (int)$input['desired_orphans'] : null,
                $input['acquisition_source'] !== '' ? $input['acquisition_source'] : null,
                $input['brought_by_name'] !== '' ? $input['brought_by_name'] : null
            ]
        );
        $newId = (int)dbLastInsertId();
        flash('success', 'تمت إضافة الكفيل: ' . $input['full_name'] . ' (' . $code . ')');
        header('Location: ' . APP_URL . 'modules/sponsors/view.php?id=' . $newId); exit();
    }
}
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
/* Hide up/down arrows from number inputs */
input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
input[type=number] { -moz-appearance: textfield; appearance: textfield; }
</style>

<div class="welcome-section fade-in"><h2>إضافة كفيل</h2></div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-body">
        <form method="post">
            <?php echo csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">اسم الكفيل *</label><input type="text" name="full_name" class="form-control" required value="<?php echo e($input['full_name']); ?>"></div>
                <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" name="email" class="form-control" dir="ltr" value="<?php echo e($input['email']); ?>"></div>
                <div class="col-md-3"><label class="form-label">الهاتف</label><input type="text" name="phone" class="form-control" dir="ltr" value="<?php echo e($input['phone']); ?>"></div>
                <div class="col-md-3"><label class="form-label">نوع الاستخدام</label><select name="phone_purpose" class="form-select"><option value="call" <?php echo $input['phone_purpose'] === 'call' ? 'selected' : ''; ?>>للاتصال</option><option value="whatsapp" <?php echo $input['phone_purpose'] === 'whatsapp' ? 'selected' : ''; ?>>واتساب</option><option value="both" <?php echo $input['phone_purpose'] === 'both' ? 'selected' : ''; ?>>للاتصال وواتساب</option></select></div>
                <div class="col-md-3"><label class="form-label">هاتف بديل</label><input type="text" name="alt_phone" class="form-control" dir="ltr" value="<?php echo e($input['alt_phone']); ?>"></div>
                <div class="col-md-3"><label class="form-label">نوع الاستخدام</label><select name="alt_phone_purpose" class="form-select"><option value="call" <?php echo $input['alt_phone_purpose'] === 'call' ? 'selected' : ''; ?>>للاتصال</option><option value="whatsapp" <?php echo $input['alt_phone_purpose'] === 'whatsapp' ? 'selected' : ''; ?>>واتساب</option><option value="both" <?php echo $input['alt_phone_purpose'] === 'both' ? 'selected' : ''; ?>>للاتصال وواتساب</option></select></div>
                <div class="col-md-3"><label class="form-label">النوع</label><select name="sponsor_type" class="form-select"><option value="individual" <?php echo $input['sponsor_type'] === 'individual' ? 'selected' : ''; ?>>فرد</option><option value="company" <?php echo $input['sponsor_type'] === 'company' ? 'selected' : ''; ?>>شركة</option><option value="organization" <?php echo $input['sponsor_type'] === 'organization' ? 'selected' : ''; ?>>منظمة</option></select></div>
                <div class="col-md-3"><label class="form-label">الجنس</label><select name="gender" class="form-select"><option value="unknown" <?php echo $input['gender'] === 'unknown' ? 'selected' : ''; ?>>غير معروف</option><option value="male" <?php echo $input['gender'] === 'male' ? 'selected' : ''; ?>>ذكر</option><option value="female" <?php echo $input['gender'] === 'female' ? 'selected' : ''; ?>>أنثى</option><option value="organization" <?php echo $input['gender'] === 'organization' ? 'selected' : ''; ?>>منظمة</option></select></div>
                <div class="col-md-3"><label class="form-label">طريقة الدفع المفضلة</label><select name="payment" class="form-select"><option value="cash" <?php echo $input['payment'] === 'cash' ? 'selected' : ''; ?>>نقدي</option><option value="bank_transfer" <?php echo $input['payment'] === 'bank_transfer' ? 'selected' : ''; ?>>تحويل بنكي</option><option value="mobile" <?php echo $input['payment'] === 'mobile' ? 'selected' : ''; ?>>محفظة إلكترونية</option><option value="other" <?php echo $input['payment'] === 'other' ? 'selected' : ''; ?>>أخرى</option></select></div>
                <div class="col-md-3"><label class="form-label">الحالة</label><select name="status" class="form-select"><option value="active" <?php echo $input['status'] === 'active' ? 'selected' : ''; ?>>نشط</option><option value="inactive" <?php echo $input['status'] === 'inactive' ? 'selected' : ''; ?>>غير نشط</option><option value="suspended" <?php echo $input['status'] === 'suspended' ? 'selected' : ''; ?>>موقوف</option><option value="cancelled" <?php echo $input['status'] === 'cancelled' ? 'selected' : ''; ?>>ملغي</option></select></div>
                <div class="col-md-6"><label class="form-label">العنوان</label><input type="text" name="address" class="form-control" value="<?php echo e($input['address']); ?>"></div>
                <div class="col-md-6"><label class="form-label">عدد الأيتام الراغب في كفالتهم</label><input type="number" name="desired_orphans" class="form-control" min="0" value="<?php echo e($input['desired_orphans']); ?>"></div>

                <div class="col-12 mt-3 border-top pt-3">
                    <h6 class="text-muted mb-3"><i class="fas fa-bullhorn me-2"></i>معلومات الاستقطاب</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">مصدر الاستقطاب</label>
                            <select name="acquisition_source" class="form-select">
                                <option value="">— غير محدد —</option>
                                <option value="تيك توك" <?php echo $input['acquisition_source'] === 'تيك توك' ? 'selected' : ''; ?>>تيك توك</option>
                                <option value="فيسبوك" <?php echo $input['acquisition_source'] === 'فيسبوك' ? 'selected' : ''; ?>>فيسبوك</option>
                                <option value="حملة إعلامية" <?php echo $input['acquisition_source'] === 'حملة إعلامية' ? 'selected' : ''; ?>>حملة إعلامية</option>
                                <option value="موظف" <?php echo $input['acquisition_source'] === 'موظف' ? 'selected' : ''; ?>>موظف</option>
                                <option value="مباشر" <?php echo $input['acquisition_source'] === 'مباشر' ? 'selected' : ''; ?>>مباشر (مبادرة ذاتية)</option>
                                <option value="أخرى" <?php echo $input['acquisition_source'] === 'أخرى' ? 'selected' : ''; ?>>أخرى</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">جلب بواسطة (الموظف/المشرف)</label>
                            <input type="text" name="brought_by_name" class="form-control" value="<?php echo e($input['brought_by_name']); ?>" placeholder="أدخل اسم الشخص أو الجهة">
                        </div>
                    </div>
                </div>
                <div class="col-12 mt-3"><label class="form-label">ملاحظات</label><textarea name="notes" class="form-control" rows="2"><?php echo e($input['notes']); ?></textarea></div>
            </div>
            <div class="mt-4">
                <button class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ الكفيل</button>
                <a href="<?php echo APP_URL; ?>modules/sponsors/index.php" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
=======
<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }

// Auto-add text column for "Brought By" if it doesn't exist
dbExecute("ALTER TABLE sponsors ADD COLUMN IF NOT EXISTS brought_by_name VARCHAR(255) NULL");

$pageTitle = 'إضافة كفيل';
$active = 'sponsors';
$errors = [];
$input = [
    'full_name' => '', 'email' => '', 'phone' => '', 'phone_purpose' => 'both',
    'alt_phone' => '', 'alt_phone_purpose' => 'both', 'address' => '',
    'sponsor_type' => 'individual', 'gender' => 'unknown', 'payment' => 'cash',
    'status' => 'active', 'desired_orphans' => '', 'notes' => '',
    'acquisition_source' => '', 'brought_by_name' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input['full_name'] = trim($_POST['full_name'] ?? '');
    $input['email'] = trim($_POST['email'] ?? '');
    $input['phone'] = trim($_POST['phone'] ?? '');
    $input['phone_purpose'] = in_array($_POST['phone_purpose'] ?? '', ['call','whatsapp','both'], true) ? $_POST['phone_purpose'] : 'both';
    $input['alt_phone'] = trim($_POST['alt_phone'] ?? '');
    $input['alt_phone_purpose'] = in_array($_POST['alt_phone_purpose'] ?? '', ['call','whatsapp','both'], true) ? $_POST['alt_phone_purpose'] : 'both';
    $input['address'] = trim($_POST['address'] ?? '');
    $input['sponsor_type'] = in_array($_POST['sponsor_type'] ?? '', ['individual','company','organization'], true) ? $_POST['sponsor_type'] : 'individual';
    $input['gender'] = in_array($_POST['gender'] ?? '', ['male','female','organization','unknown'], true) ? $_POST['gender'] : 'unknown';
    $input['payment'] = in_array($_POST['payment'] ?? '', ['cash','bank_transfer','credit_card','mobile','other'], true) ? $_POST['payment'] : 'cash';
    $input['status'] = in_array($_POST['status'] ?? '', ['active','inactive','suspended','cancelled'], true) ? $_POST['status'] : 'active';
    $input['desired_orphans'] = trim($_POST['desired_orphans'] ?? '');
    $input['notes'] = trim($_POST['notes'] ?? '');
    $input['acquisition_source'] = trim($_POST['acquisition_source'] ?? '');
    $input['brought_by_name'] = trim($_POST['brought_by_name'] ?? '');

    if ($input['full_name'] === '') $errors[] = 'اسم الكفيل مطلوب.';
    if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صالح.';

    if (!$errors && verify_csrf()) {
        $letterMap = [];
        foreach (dbFetchAll("SELECT id, code FROM letters WHERE is_active = 1") as $L) $letterMap[normalize_arabic_letter($L['code'])] = (int)$L['id'];
        $matrix = [];
        foreach (dbFetchAll("SELECT sl.supervisor_id, l.code, sl.gender FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id") as $r) {
            $n = normalize_arabic_letter($r['code']);
            $matrix[$n][$r['gender']] = (int)$r['supervisor_id'];
        }
        [$raw, $norm] = first_letter_of($input['full_name']);
        $letterId = $letterMap[$norm] ?? null;
        $supId = null;
        if (isset($matrix[$norm])) {
            $g = $input['gender'];
            if (in_array($g, ['male', 'female'])) $supId = $matrix[$norm][$g] ?? $matrix[$norm]['both'] ?? null;
            else $supId = $matrix[$norm]['both'] ?? $matrix[$norm]['male'] ?? $matrix[$norm]['female'] ?? null;
        }

        $n = (int)(dbFetchOne("SELECT COUNT(*) c FROM sponsors")['c'] ?? 0) + 1;
        $code = 'SP-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
        
        dbExecute(
            "INSERT INTO sponsors (full_name, first_letter_raw, first_letter_id, phone, phone_purpose, alt_phone, alt_phone_purpose, email, address, 
             sponsor_type, gender, preferred_payment_method, status, notes, created_by, sponsor_code, supervisor_id, assigned_by, assigned_at, 
             desired_orphans, is_manual_override, acquisition_source, brought_by_name) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 0, ?, ?)",
            [
                $input['full_name'], $raw, $letterId, 
                $input['phone'] !== '' ? $input['phone'] : null, $input['phone_purpose'],
                $input['alt_phone'] !== '' ? $input['alt_phone'] : null, $input['alt_phone_purpose'],
                $input['email'] !== '' ? $input['email'] : null, $input['address'] !== '' ? $input['address'] : null,
                $input['sponsor_type'], $input['gender'], $input['payment'], $input['status'], 
                $input['notes'] !== '' ? $input['notes'] : null, Session::getUserId(), $code, $supId, Session::getUserId(), 
                $input['desired_orphans'] !== '' ? (int)$input['desired_orphans'] : null,
                $input['acquisition_source'] !== '' ? $input['acquisition_source'] : null,
                $input['brought_by_name'] !== '' ? $input['brought_by_name'] : null
            ]
        );
        $newId = (int)dbLastInsertId();
        flash('success', 'تمت إضافة الكفيل: ' . $input['full_name'] . ' (' . $code . ')');
        header('Location: ' . APP_URL . 'modules/sponsors/view.php?id=' . $newId); exit();
    }
}
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
/* Hide up/down arrows from number inputs */
input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
input[type=number] { -moz-appearance: textfield; appearance: textfield; }
</style>

<div class="welcome-section fade-in"><h2>إضافة كفيل</h2></div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-body">
        <form method="post">
            <?php echo csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">اسم الكفيل *</label><input type="text" name="full_name" class="form-control" required value="<?php echo e($input['full_name']); ?>"></div>
                <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" name="email" class="form-control" dir="ltr" value="<?php echo e($input['email']); ?>"></div>
                <div class="col-md-3"><label class="form-label">الهاتف</label><input type="text" name="phone" class="form-control" dir="ltr" value="<?php echo e($input['phone']); ?>"></div>
                <div class="col-md-3"><label class="form-label">نوع الاستخدام</label><select name="phone_purpose" class="form-select"><option value="call" <?php echo $input['phone_purpose'] === 'call' ? 'selected' : ''; ?>>للاتصال</option><option value="whatsapp" <?php echo $input['phone_purpose'] === 'whatsapp' ? 'selected' : ''; ?>>واتساب</option><option value="both" <?php echo $input['phone_purpose'] === 'both' ? 'selected' : ''; ?>>للاتصال وواتساب</option></select></div>
                <div class="col-md-3"><label class="form-label">هاتف بديل</label><input type="text" name="alt_phone" class="form-control" dir="ltr" value="<?php echo e($input['alt_phone']); ?>"></div>
                <div class="col-md-3"><label class="form-label">نوع الاستخدام</label><select name="alt_phone_purpose" class="form-select"><option value="call" <?php echo $input['alt_phone_purpose'] === 'call' ? 'selected' : ''; ?>>للاتصال</option><option value="whatsapp" <?php echo $input['alt_phone_purpose'] === 'whatsapp' ? 'selected' : ''; ?>>واتساب</option><option value="both" <?php echo $input['alt_phone_purpose'] === 'both' ? 'selected' : ''; ?>>للاتصال وواتساب</option></select></div>
                <div class="col-md-3"><label class="form-label">النوع</label><select name="sponsor_type" class="form-select"><option value="individual" <?php echo $input['sponsor_type'] === 'individual' ? 'selected' : ''; ?>>فرد</option><option value="company" <?php echo $input['sponsor_type'] === 'company' ? 'selected' : ''; ?>>شركة</option><option value="organization" <?php echo $input['sponsor_type'] === 'organization' ? 'selected' : ''; ?>>منظمة</option></select></div>
                <div class="col-md-3"><label class="form-label">الجنس</label><select name="gender" class="form-select"><option value="unknown" <?php echo $input['gender'] === 'unknown' ? 'selected' : ''; ?>>غير معروف</option><option value="male" <?php echo $input['gender'] === 'male' ? 'selected' : ''; ?>>ذكر</option><option value="female" <?php echo $input['gender'] === 'female' ? 'selected' : ''; ?>>أنثى</option><option value="organization" <?php echo $input['gender'] === 'organization' ? 'selected' : ''; ?>>منظمة</option></select></div>
                <div class="col-md-3"><label class="form-label">طريقة الدفع المفضلة</label><select name="payment" class="form-select"><option value="cash" <?php echo $input['payment'] === 'cash' ? 'selected' : ''; ?>>نقدي</option><option value="bank_transfer" <?php echo $input['payment'] === 'bank_transfer' ? 'selected' : ''; ?>>تحويل بنكي</option><option value="mobile" <?php echo $input['payment'] === 'mobile' ? 'selected' : ''; ?>>محفظة إلكترونية</option><option value="other" <?php echo $input['payment'] === 'other' ? 'selected' : ''; ?>>أخرى</option></select></div>
                <div class="col-md-3"><label class="form-label">الحالة</label><select name="status" class="form-select"><option value="active" <?php echo $input['status'] === 'active' ? 'selected' : ''; ?>>نشط</option><option value="inactive" <?php echo $input['status'] === 'inactive' ? 'selected' : ''; ?>>غير نشط</option><option value="suspended" <?php echo $input['status'] === 'suspended' ? 'selected' : ''; ?>>موقوف</option><option value="cancelled" <?php echo $input['status'] === 'cancelled' ? 'selected' : ''; ?>>ملغي</option></select></div>
                <div class="col-md-6"><label class="form-label">العنوان</label><input type="text" name="address" class="form-control" value="<?php echo e($input['address']); ?>"></div>
                <div class="col-md-6"><label class="form-label">عدد الأيتام الراغب في كفالتهم</label><input type="number" name="desired_orphans" class="form-control" min="0" value="<?php echo e($input['desired_orphans']); ?>"></div>

                <div class="col-12 mt-3 border-top pt-3">
                    <h6 class="text-muted mb-3"><i class="fas fa-bullhorn me-2"></i>معلومات الاستقطاب</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">مصدر الاستقطاب</label>
                            <select name="acquisition_source" class="form-select">
                                <option value="">— غير محدد —</option>
                                <option value="تيك توك" <?php echo $input['acquisition_source'] === 'تيك توك' ? 'selected' : ''; ?>>تيك توك</option>
                                <option value="فيسبوك" <?php echo $input['acquisition_source'] === 'فيسبوك' ? 'selected' : ''; ?>>فيسبوك</option>
                                <option value="حملة إعلامية" <?php echo $input['acquisition_source'] === 'حملة إعلامية' ? 'selected' : ''; ?>>حملة إعلامية</option>
                                <option value="موظف" <?php echo $input['acquisition_source'] === 'موظف' ? 'selected' : ''; ?>>موظف</option>
                                <option value="مباشر" <?php echo $input['acquisition_source'] === 'مباشر' ? 'selected' : ''; ?>>مباشر (مبادرة ذاتية)</option>
                                <option value="أخرى" <?php echo $input['acquisition_source'] === 'أخرى' ? 'selected' : ''; ?>>أخرى</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">جلب بواسطة (الموظف/المشرف)</label>
                            <input type="text" name="brought_by_name" class="form-control" value="<?php echo e($input['brought_by_name']); ?>" placeholder="أدخل اسم الشخص أو الجهة">
                        </div>
                    </div>
                </div>
                <div class="col-12 mt-3"><label class="form-label">ملاحظات</label><textarea name="notes" class="form-control" rows="2"><?php echo e($input['notes']); ?></textarea></div>
            </div>
            <div class="mt-4">
                <button class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ الكفيل</button>
                <a href="<?php echo APP_URL; ?>modules/sponsors/index.php" class="btn btn-secondary">إلغاء</a>
            </div>
        </form>
    </div>
</div>
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>