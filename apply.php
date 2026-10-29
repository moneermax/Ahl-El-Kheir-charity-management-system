<?php
// Root: apply.php - Public sponsor application form (no login required)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/sponsor_assignments.php';
if (is_file(__DIR__ . '/config/lang.php')) require_once __DIR__ . '/config/lang.php';
Session::start();

if (!defined('AK_LANG')) define('AK_LANG', 'ar');
if (!defined('AK_DIR'))  define('AK_DIR', 'rtl');
if (!function_exists('t')) { function t(string $s): string { return function_exists('ak_t') ? ak_t($s) : $s; } }

$purposes = ['call' => 'للاتصال', 'whatsapp' => 'واتساب', 'both' => 'للاتصال وواتساب'];

$done = false; $errors = [];
$form = ['full_name'=>'','phone'=>'','phone_purpose'=>'both','alt_phone'=>'','alt_phone_purpose'=>'both','email'=>'','address'=>'','sponsor_type'=>'individual','gender'=>'','preferred_payment_method'=>'cash','desired_orphans'=>'','proposed_amount'=>'','notes'=>''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) if (isset($_POST[$key]) && is_scalar($_POST[$key])) $form[$key] = trim((string)$_POST[$key]);
    if (!verify_csrf()) $errors[] = 'انتهت صلاحية النموذج أو أن الطلب غير صالح. حدّث الصفحة وحاول مرة أخرى.';
    $name = $form['full_name']; $phone = $form['phone'];
    $type = in_array($form['sponsor_type'], ['individual','company','organization'], true) ? $form['sponsor_type'] : 'individual';
    $gender = $type === 'individual' ? strtolower($form['gender']) : 'organization';
    $phonePurpose = in_array($form['phone_purpose'], ['call','whatsapp','both'], true) ? $form['phone_purpose'] : 'both';
    $altPhonePurpose = in_array($form['alt_phone_purpose'], ['call','whatsapp','both'], true) ? $form['alt_phone_purpose'] : 'both';
    $payment = in_array($form['preferred_payment_method'], ['cash','bank_transfer','credit_card','mobile','other'], true) ? $form['preferred_payment_method'] : 'cash';
    $desiredOrphans = $form['desired_orphans'] !== '' && ctype_digit($form['desired_orphans']) ? (int)$form['desired_orphans'] : null;
    $proposedAmount = $form['proposed_amount'] !== '' && is_numeric($form['proposed_amount']) && (float)$form['proposed_amount'] >= 0 ? (float)$form['proposed_amount'] : null;
    if ($name === '') $errors[] = 'الاسم الكامل مطلوب.';
    if ($phone === '') $errors[] = 'الهاتف مطلوب.';
    if ($type === 'individual' && !in_array($gender, ['male','female'], true)) $errors[] = 'يرجى تحديد الجنس للأفراد.';
    if ($form['desired_orphans'] !== '' && ($desiredOrphans === null || $desiredOrphans < 1 || $desiredOrphans > 50)) $errors[] = 'عدد الأيتام يجب أن يكون بين 1 و50.';
    if ($form['proposed_amount'] !== '' && $proposedAmount === null) $errors[] = 'المبلغ المقترح غير صالح.';
    if (!$errors) {
        [$raw, $norm] = first_letter_of($name);
        $letter = dbFetchOne("SELECT id FROM letters WHERE is_active = 1 AND code = ? LIMIT 1", [$norm]);
        $letterId = $letter ? (int)$letter['id'] : null;
        $supId = $type === 'individual' ? resolveSponsorSupervisorId($name, $gender) : null;
        dbExecute("INSERT INTO sponsor_requests (sponsor_name, phone, phone_purpose, alt_phone, alt_phone_purpose, email, address, sponsor_type, gender, preferred_payment_method, proposed_amount, desired_orphans, notes, first_letter_raw, first_letter_id, assigned_supervisor_id, source, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'public_form', 'new')",
            [$name, $phone, $phonePurpose, $form['alt_phone'] !== '' ? $form['alt_phone'] : null, $altPhonePurpose, $form['email'] !== '' ? $form['email'] : null, $form['address'] !== '' ? $form['address'] : null, $type, $gender, $payment, $proposedAmount, $desiredOrphans, $form['notes'] !== '' ? $form['notes'] : null, $raw, $letterId, $supId]);
        $reqId = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);
        try {
            $targets = [];
            if ($supId) $targets[] = $supId;
            foreach (dbFetchAll("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code IN ('admin','vice_general_manager') AND u.is_active = 1") as $u) $targets[] = (int)$u['id'];
            foreach (array_unique($targets) as $tid) {
                // Supervisors receive a scoped detail link; management retains the full queue.
                $targetLink = ($supId && (int)$tid === (int)$supId)
                    ? 'modules/sponsors/my_request.php?id=' . $reqId
                    : 'modules/sponsors/requests.php';
                dbExecute(
                    "INSERT INTO notifications (recipient_user_id, type, title, body, link) VALUES (?, 'sponsor_request', ?, ?, ?)",
                    [$tid, 'طلب كفيل جديد', $name . ' — ' . $phone, $targetLink]
                );
            }
        } catch (Throwable $e) { error_log('Public sponsor request notification failed for request ' . $reqId . ': ' . $e->getMessage()); }
        $done = true;
    }
}
$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
$langSwitchUrl = e($uri . (strpos($uri, '?') !== false ? '&' : '?') . 'lang=' . (AK_LANG === 'ar' ? 'en' : 'ar'));
?>
<!DOCTYPE html>
<html lang="<?php echo AK_LANG; ?>" dir="<?php echo AK_DIR; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo t('استمارة كفيل جديدة'); ?> - <?php echo t('أهل الخير'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?php echo AK_DIR === 'rtl' ? '.rtl' : ''; ?>.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{background:linear-gradient(135deg,#0a1f44 0%,#1b4d8f 100%);font-family:'Cairo',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;padding:2rem 0}
/* Card sized to hug the content -> no dead white space on either side */
.apply-card{background:#fff;border-radius:15px;box-shadow:0 10px 30px rgba(0,0,0,.25);max-width:780px;width:96%}
.apply-header{background:#1b4d8f;color:#fff;padding:1.6rem;text-align:center;border-radius:15px 15px 0 0}
.apply-header i{font-size:2.8rem;margin-bottom:.4rem}
.apply-body{padding:1.8rem}
.btn-primary{background:#1b4d8f;border-color:#1b4d8f;font-weight:600}
.btn-primary:hover{background:#0a1f44;border-color:#0a1f44}
.f-row{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.9rem}
.f-row label{font-weight:700;white-space:nowrap;margin:0}
.f-row .gap-label{margin-inline-start:1.4rem}
/* Hide number input spinners (up/down arrows) */
input[type=number]{-moz-appearance:textfield;appearance:textfield}
input[type=number]::-webkit-outer-spin-button,
input[type=number]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
</style>
</head>
<body>
<div style="position:fixed;top:12px;inset-inline-end:12px"><a href="<?php echo $langSwitchUrl; ?>" class="btn btn-sm btn-light"><i class="fas fa-globe me-1"></i><?php echo AK_LANG === 'ar' ? 'EN' : 'عربي'; ?></a></div>
<div class="apply-card">
    <div class="apply-header">
        <i class="fas fa-hand-holding-heart"></i>
        <h3 class="mt-2"><?php echo t('منظمة أهل الخير'); ?></h3>
        <p class="mb-0"><?php echo t('استمارة كفيل جديدة'); ?></p>
    </div>
    <div class="apply-body">
        <?php if ($done): ?>
            <div class="alert alert-success text-center">
                <h5><?php echo t('تم استلام طلبك بنجاح'); ?></h5>
                <p class="mb-2"><?php echo t('سيتواصل معك المشرف المختص قريباً.'); ?></p>
                <a href="<?php echo APP_URL; ?>apply.php" class="btn btn-primary btn-sm"><?php echo t('إرسال طلب آخر'); ?></a>
            </div>
        <?php else: ?>
            <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div><?php endif; ?>
            <p class="text-muted"><?php echo t('سجّل بياناتك وسيتواصل معك المشرف المختص'); ?></p>
            <form method="post">
                <?php echo csrf_field(); ?>
                <!-- الاسم الكامل -->
                <div class="f-row">
                    <label><?php echo t('الاسم الكامل'); ?> *</label>
                    <input type="text" name="full_name" class="form-control" style="max-width:560px" maxlength="255" value="<?php echo e($form['full_name']); ?>" required>
                </div>
                <!-- الهاتف + الغرض -->
                <div class="f-row">
                    <label><?php echo t('الهاتف'); ?> *</label>
                    <input type="tel" name="phone" class="form-control" style="max-width:240px" dir="ltr" maxlength="15" placeholder="09xxxxxxxx" required>
                    <label class="gap-label"><?php echo t('الغرض'); ?></label>
                    <select name="phone_purpose" class="form-select" style="max-width:180px">
                        <?php foreach ($purposes as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo t($v); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <!-- هاتف بديل + الغرض -->
                <div class="f-row">
                    <label><?php echo t('هاتف بديل'); ?></label>
                    <input type="tel" name="alt_phone" class="form-control" style="max-width:240px" dir="ltr" maxlength="15" placeholder="09xxxxxxxx">
                    <label class="gap-label"><?php echo t('الغرض'); ?></label>
                    <select name="alt_phone_purpose" class="form-select" style="max-width:180px">
                        <?php foreach ($purposes as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo t($v); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <!-- البريد الإلكتروني -->
                <div class="f-row">
                    <label><?php echo t('البريد الإلكتروني'); ?></label>
                    <input type="email" name="email" class="form-control" style="max-width:360px" dir="ltr" value="<?php echo e($form['email']); ?>">
                </div>
                <!-- نوع مقدم الطلب -->
                <div class="f-row">
                    <label for="sponsorType"><?php echo t('النوع'); ?></label>
                    <select name="sponsor_type" id="sponsorType" class="form-select" style="max-width:200px">
                        <option value="individual" <?php echo $form['sponsor_type'] === 'individual' ? 'selected' : ''; ?>><?php echo t('فرد'); ?></option>
                        <option value="company" <?php echo $form['sponsor_type'] === 'company' ? 'selected' : ''; ?>><?php echo t('شركة'); ?></option>
                        <option value="organization" <?php echo $form['sponsor_type'] === 'organization' ? 'selected' : ''; ?>><?php echo t('منظمة'); ?></option>
                    </select>
                </div>
                <div class="f-row" id="genderRow">
                    <label for="applicantGender"><?php echo t('الجنس'); ?> *</label>
                    <select name="gender" id="applicantGender" class="form-select" style="max-width:200px">
                        <option value="">— <?php echo t('اختر الجنس'); ?> —</option>
                        <option value="male" <?php echo $form['gender'] === 'male' ? 'selected' : ''; ?>><?php echo t('ذكر'); ?></option>
                        <option value="female" <?php echo $form['gender'] === 'female' ? 'selected' : ''; ?>><?php echo t('أنثى'); ?></option>
                    </select>
                </div>
                <div class="f-row">
                    <label><?php echo t('طريقة الدفع المفضلة'); ?></label>
                    <select name="preferred_payment_method" class="form-select" style="max-width:200px">
                        <option value="cash" <?php echo $form['preferred_payment_method'] === 'cash' ? 'selected' : ''; ?>><?php echo t('نقدي'); ?></option>
                        <option value="bank_transfer" <?php echo $form['preferred_payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>><?php echo t('تحويل بنكي'); ?></option>
                        <option value="mobile" <?php echo $form['preferred_payment_method'] === 'mobile' ? 'selected' : ''; ?>><?php echo t('محفظة إلكترونية'); ?></option>
                        <option value="other" <?php echo $form['preferred_payment_method'] === 'other' ? 'selected' : ''; ?>><?php echo t('أخرى'); ?></option>
                    </select>
                </div>
                <!-- العنوان -->
                <div class="f-row">
                    <label><?php echo t('العنوان'); ?></label>
                    <input type="text" name="address" class="form-control" style="max-width:560px" value="<?php echo e($form['address']); ?>">
                </div>
                <!-- عدد الأيتام + المبلغ المقترح -->
                <div class="f-row">
                    <label><?php echo t('عدد الأيتام المطلوب كفالتهم'); ?></label>
                    <input type="number" min="1" max="50" name="desired_orphans" class="form-control" style="max-width:120px" dir="ltr" value="<?php echo e($form['desired_orphans']); ?>">
                    <label class="gap-label"><?php echo t('المبلغ الشهري المقترح'); ?></label>
                    <input type="number" min="0" step="500" name="proposed_amount" class="form-control" style="max-width:160px" dir="ltr" value="<?php echo e($form['proposed_amount']); ?>">
                </div>
                <!-- ملاحظات -->
                <div class="f-row" style="align-items:flex-start">
                    <label><?php echo t('ملاحظات'); ?></label>
                    <textarea name="notes" rows="2" class="form-control" style="max-width:560px"><?php echo e($form['notes']); ?></textarea>
                </div>
                <div class="mt-4 text-center">
                    <button class="btn btn-primary px-5"><i class="fas fa-paper-plane me-1"></i> <?php echo t('إرسال الطلب'); ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    const type = document.getElementById('sponsorType');
    const row = document.getElementById('genderRow');
    const gender = document.getElementById('applicantGender');
    if (!type || !row || !gender) return;
    function syncGenderRequirement() {
        const individual = type.value === 'individual';
        row.hidden = !individual;
        gender.required = individual;
        if (!individual) gender.value = '';
    }
    type.addEventListener('change', syncGenderRequirement);
    syncGenderRequirement();
})();
</script>
</body>
</html>