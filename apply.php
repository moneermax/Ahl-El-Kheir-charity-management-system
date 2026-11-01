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

$done = false; $errors = []; $assignedSupervisorName = null;
$form = ['full_name'=>'','phone'=>'','phone_purpose'=>'both','alt_phone'=>'','alt_phone_purpose'=>'both','email'=>'','address'=>'','sponsor_type'=>'individual','gender'=>'','contact_person_name'=>'','contact_person_position'=>'','preferred_contact_method'=>'any','preferred_contact_time'=>'','preferred_payment_method'=>'cash','desired_orphans'=>'','proposed_amount'=>'','notes'=>''];
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
    $contactPersonName = $type !== 'individual' ? trim($form['contact_person_name']) : null;
    $contactPersonPosition = $type !== 'individual' && $form['contact_person_position'] !== '' ? trim($form['contact_person_position']) : null;
    $contactMethod = in_array($form['preferred_contact_method'], ['call', 'whatsapp', 'email', 'any'], true) ? $form['preferred_contact_method'] : null;
    $contactTime = $type !== 'individual' && $form['preferred_contact_time'] !== '' ? trim($form['preferred_contact_time']) : null;
    if ($name === '') $errors[] = 'الاسم الكامل مطلوب.';
    if ($phone === '') $errors[] = 'الهاتف مطلوب.';
    if ($type === 'individual' && !in_array($gender, ['male','female'], true)) $errors[] = 'يرجى تحديد الجنس للأفراد.';
    if ($type !== 'individual' && $contactPersonName === '') $errors[] = 'اسم الشخص المفوض بالتواصل مطلوب للشركات والمنظمات.';
    if ($type !== 'individual' && $contactMethod === null) $errors[] = 'يرجى تحديد طريقة التواصل المفضلة للجهة.';
    if ($type !== 'individual' && $contactMethod === 'email' && $form['email'] === '') $errors[] = 'البريد الإلكتروني مطلوب عند اختيار التواصل عبر البريد.';
    if ($form['desired_orphans'] !== '' && ($desiredOrphans === null || $desiredOrphans < 1 || $desiredOrphans > 50)) $errors[] = 'عدد الأيتام يجب أن يكون بين 1 و50.';
    if ($form['proposed_amount'] !== '' && $proposedAmount === null) $errors[] = 'المبلغ المقترح غير صالح.';
    if (!$errors) {
        [$raw, $norm] = first_letter_of($name);
        $letter = dbFetchOne("SELECT id FROM letters WHERE is_active = 1 AND code = ? LIMIT 1", [$norm]);
        $letterId = $letter ? (int)$letter['id'] : null;
        $supId = $type === 'individual' ? resolveSponsorSupervisorId($name, $gender) : null;
        if ($supId) {
            $assignedSupervisor = dbFetchOne("SELECT u.full_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.is_active = 1 AND r.code = 'supervisor' LIMIT 1", [$supId]);
            $assignedSupervisorName = trim((string)($assignedSupervisor['full_name'] ?? '')) ?: null;
            if ($assignedSupervisorName === null) $supId = null;
        }
        dbExecute("INSERT INTO sponsor_requests (sponsor_name, phone, phone_purpose, alt_phone, alt_phone_purpose, email, address, sponsor_type, contact_person_name, contact_person_position, preferred_contact_method, preferred_contact_time, gender, preferred_payment_method, proposed_amount, desired_orphans, notes, first_letter_raw, first_letter_id, assigned_supervisor_id, source, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'public_form', 'new')",
            [$name, $phone, $phonePurpose, $form['alt_phone'] !== '' ? $form['alt_phone'] : null, $altPhonePurpose, $form['email'] !== '' ? $form['email'] : null, $form['address'] !== '' ? $form['address'] : null, $type, $contactPersonName, $contactPersonPosition, $type !== 'individual' ? $contactMethod : null, $contactTime, $gender, $payment, $proposedAmount, $desiredOrphans, $form['notes'] !== '' ? $form['notes'] : null, $raw, $letterId, $supId]);
        $reqId = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);
        try {
            // Route each public application to one operational owner:
            // matrix-assigned individuals go straight to their supervisor;
            // legal entities (and individuals without a valid matrix match)
            // go to the VGM for direct processing or supervisor assignment.
            $targets = [];
            if ($supId) {
                $targets[] = $supId;
            } else {
                foreach (dbFetchAll("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'vice_general_manager' AND u.is_active = 1") as $u) {
                    $targets[] = (int)$u['id'];
                }
            }
            foreach (array_unique($targets) as $tid) {
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
$orgSettings = ['site_logo' => '', 'org_name_ar' => '', 'org_name_en' => '', 'org_address' => '', 'support_phone' => '', 'support_email' => ''];
foreach (dbFetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_logo', 'org_name_ar', 'org_name_en', 'org_address', 'support_phone', 'support_email')") as $setting) {
    $orgSettings[(string)$setting['setting_key']] = trim((string)($setting['setting_value'] ?? ''));
}
$orgName = AK_LANG === 'en'
    ? ($orgSettings['org_name_en'] ?: "Ahl El Kheir Women's Organization")
    : ($orgSettings['org_name_ar'] ?: 'منظمة أهل الخير النسوية لكفالة الأيتام');
$orgLogoPath = $orgSettings['site_logo'] ?: 'assets/img/logo.png';
if (!is_file(__DIR__ . '/' . ltrim($orgLogoPath, '/'))) $orgLogoPath = 'assets/img/logo.png';
$orgLogoUrl = APP_URL . ltrim($orgLogoPath, '/');
$orgContactDetails = [];
if ($orgSettings['org_address'] !== '') $orgContactDetails[] = ['icon' => 'fa-location-dot', 'value' => $orgSettings['org_address']];
if ($orgSettings['support_phone'] !== '') $orgContactDetails[] = ['icon' => 'fa-phone', 'value' => $orgSettings['support_phone']];
if ($orgSettings['support_email'] !== '') $orgContactDetails[] = ['icon' => 'fa-envelope', 'value' => $orgSettings['support_email']];
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
.apply-header{background:#fff;color:#142b4f;padding:1.35rem 1.6rem 1.15rem;text-align:center;border-radius:15px 15px 0 0;border-bottom:4px solid #c6a45b}
.apply-header-logo{display:block;width:112px;height:112px;object-fit:contain;margin:0 auto .8rem}
.apply-header h3{font-size:1.45rem;font-weight:700;line-height:1.5;margin:0 0 .4rem}
.apply-org-info{display:flex;justify-content:center;align-items:center;gap:.45rem .85rem;flex-wrap:nowrap;overflow-x:auto;white-space:nowrap;color:#526174;font-size:.78rem;margin-top:.65rem;max-width:100%;padding-bottom:.15rem}
.apply-org-info span{display:inline-flex;align-items:center;gap:.3rem;white-space:nowrap;flex:0 0 auto}
.apply-org-info i{color:#1b4d8f}
.apply-header .apply-form-title{display:inline-block;background:#1b4d8f;color:#fff;border-radius:8px;padding:.45rem 1.1rem;margin:.95rem 0 0;font-size:1rem}
.apply-body{padding:1.8rem}
.btn-primary{background:#1b4d8f;border-color:#1b4d8f;font-weight:600}
.btn-primary:hover{background:#0a1f44;border-color:#0a1f44}
.f-row{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.9rem}
.f-row label{font-weight:700;white-space:nowrap;margin:0}
.f-row .gap-label{margin-inline-start:1.4rem}
.terms-dialog{width:min(720px,calc(100% - 2rem));max-height:85vh;overflow:auto;border:0;border-radius:16px;padding:0;box-shadow:0 18px 55px rgba(0,0,0,.35)}
.terms-dialog::backdrop{background:rgba(4,16,38,.68)}
.terms-dialog-header{background:#1b4d8f;color:#fff;padding:1rem 1.25rem}
.terms-dialog-body{padding:1.25rem;line-height:1.9}
.assigned-supervisor-name{display:inline-block;color:#b4238a;background:#fff0fa;border:2px solid #f0a6d9;border-radius:8px;padding:.12rem .6rem;font-size:1.12em;font-weight:800;box-shadow:0 2px 8px rgba(180,35,138,.16)}
/* Hide number input spinners (up/down arrows) */
input[type=number]{-moz-appearance:textfield;appearance:textfield}
input[type=number]::-webkit-outer-spin-button,
input[type=number]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.sponsorship-terms-panel{background:#f7f9fc;border-color:#d9e2ef!important;line-height:1.9}
.sponsorship-terms-content[hidden]{display:none}
.sponsorship-terms-content:not([hidden]){animation:sponsorshipTermsIn .22s ease}
@keyframes sponsorshipTermsIn{from{opacity:0;transform:translateY(-.25rem)}to{opacity:1;transform:translateY(0)}}
@media(prefers-reduced-motion:reduce){.sponsorship-terms-content:not([hidden]){animation:none}}
.entity-contact-fields{overflow:hidden;max-height:900px;opacity:1;transform:translateY(0);transition:max-height .38s ease,opacity .24s ease,transform .24s ease,margin .38s ease,padding .38s ease;will-change:max-height,opacity,transform}
.entity-contact-fields.is-collapsed{max-height:0;opacity:0;transform:translateY(-.5rem);margin-block:0!important;padding-block:0!important;border-width:0!important;pointer-events:none}
@media(prefers-reduced-motion:reduce){.entity-contact-fields{transition:none}}
</style>
</head>
<body>
<div style="position:fixed;top:12px;inset-inline-end:12px"><a href="<?php echo $langSwitchUrl; ?>" class="btn btn-sm btn-light"><i class="fas fa-globe me-1"></i><?php echo AK_LANG === 'ar' ? 'EN' : 'عربي'; ?></a></div>
<div class="apply-card">
    <div class="apply-header">
        <img class="apply-header-logo" src="<?php echo e($orgLogoUrl); ?>" alt="<?php echo e($orgName); ?> logo" onerror="this.onerror=null;this.src='<?php echo e(APP_URL . 'assets/img/placeholder-logo.png'); ?>';">
        <h3><?php echo e($orgName); ?></h3>
        <?php if ($orgContactDetails): ?>
            <div class="apply-org-info" aria-label="<?php echo AK_LANG === 'en' ? 'Organization contact information' : 'معلومات التواصل مع المنظمة'; ?>">
                <?php foreach ($orgContactDetails as $detail): ?>
                    <span><i class="fas <?php echo e($detail['icon']); ?>" aria-hidden="true"></i><?php echo e($detail['value']); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <p class="apply-form-title mb-0"><?php echo t('استمارة كفيل جديدة'); ?></p>
    </div>
    <div class="apply-body">
        <?php if ($done): ?>
            <dialog class="terms-dialog" id="submissionConfirmationDialog" aria-labelledby="submissionConfirmationTitle">
                <div class="terms-dialog-header">
                    <h5 class="mb-0" id="submissionConfirmationTitle"><?php echo AK_LANG === 'en' ? 'Application received successfully' : 'تم استلام طلبك بنجاح'; ?></h5>
                </div>
                <div class="terms-dialog-body text-center">
                    <p class="mb-3"><?php echo AK_LANG === 'en' ? 'Thank you. The organization has received your request, and the responsible supervisor will contact you to confirm the details and guide you through the next steps.' : 'شكراً لكم. استلمت المنظمة طلبكم، وسيتواصل معكم المشرف المختص لتأكيد التفاصيل ومساعدتكم في استكمال الخطوات التالية.'; ?></p>
                    <?php if ($assignedSupervisorName): ?>
                        <p class="mb-3"><?php echo AK_LANG === 'en' ? 'Your assigned supervisor is:' : 'المشرف المختص بمتابعة طلبكم هو:'; ?> <strong class="assigned-supervisor-name"><?php echo e($assignedSupervisorName); ?></strong></p>
                    <?php else: ?>
                        <p class="mb-3"><?php echo AK_LANG === 'en' ? 'The organization will arrange the appropriate follow-up contact.' : 'سترتب إدارة المنظمة التواصل معكم من خلال المسؤول المختص.'; ?></p>
                    <?php endif; ?>
                    <a href="<?php echo APP_URL; ?>apply.php" class="btn btn-primary"><?php echo AK_LANG === 'en' ? 'Submit another application' : 'إرسال طلب آخر'; ?></a>
                </div>
            </dialog>
        <?php else: ?>
            <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div><?php endif; ?>
            <p class="text-muted"><?php echo AK_LANG === 'en' ? 'Submit your details and the organization will follow up with you.' : 'سجّل بياناتك وستتابع المنظمة طلبك.'; ?></p>
            <form method="post">
                <?php echo csrf_field(); ?>
                <!-- نوع مقدم الطلب -->
                <div class="f-row">
                    <label for="sponsorType"><?php echo t('النوع'); ?></label>
                    <select name="sponsor_type" id="sponsorType" class="form-select" style="max-width:200px">
                        <option value="individual" <?php echo $form['sponsor_type'] === 'individual' ? 'selected' : ''; ?>><?php echo t('فرد'); ?></option>
                        <option value="company" <?php echo $form['sponsor_type'] === 'company' ? 'selected' : ''; ?>><?php echo t('شركة'); ?></option>
                        <option value="organization" <?php echo $form['sponsor_type'] === 'organization' ? 'selected' : ''; ?>><?php echo t('منظمة'); ?></option>
                    </select>
                </div>
                <section id="sponsorshipTermsPanel" class="sponsorship-terms-panel border rounded-3 p-3 mb-3" aria-live="polite" aria-atomic="true">
                    <h6 id="sponsorshipTermsTitle" class="fw-bold mb-2"><?php echo AK_LANG === 'en' ? ($form['sponsor_type'] === 'individual' ? 'Individual sponsorship terms' : ($form['sponsor_type'] === 'company' ? 'Company sponsorship terms' : 'Organization sponsorship terms')) : ($form['sponsor_type'] === 'individual' ? 'شروط كفالة الأفراد' : ($form['sponsor_type'] === 'company' ? 'شروط كفالة الشركات' : 'شروط كفالة المنظمات')); ?></h6>
                    <?php if (AK_LANG === 'en'): ?>
                        <div id="individualSponsorshipTerms" class="sponsorship-terms-content"<?php echo $form['sponsor_type'] === 'individual' ? '' : ' hidden'; ?>>
                            <ul class="mb-0">
                                <li>This application is an initial request; it does not create an active sponsorship or a final financial commitment.</li>
                                <li>The requested number of children, proposed monthly amount, and preferred payment method are proposals for discussion. They become final only after confirmation with the organization.</li>
                                <li>Matching depends on eligible children being available and on the organization's procedures. Submitting an application does not guarantee assignment of a specific child.</li>
                                <li>Sponsorship starts only after the details are confirmed and the organization's normal registration steps are completed.</li>
                            </ul>
                        </div>
                        <div id="entitySponsorshipTerms" class="sponsorship-terms-content"<?php echo $form['sponsor_type'] === 'individual' ? ' hidden' : ''; ?>>
                            <ul class="mb-0">
                                <li>The company or organization may propose sponsoring one or more children and provide an overall proposed monthly budget.</li>
                                <li>The final number of children, monthly commitment, payment schedule, and required documentation will be agreed with the organization before activation.</li>
                                <li>This application is an expression of interest only; it does not create a financial obligation or activate any sponsorship.</li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div id="individualSponsorshipTerms" class="sponsorship-terms-content"<?php echo $form['sponsor_type'] === 'individual' ? '' : ' hidden'; ?>>
                            <ul class="mb-0">
                                <li>هذا الطلب مبدئي، ولا ينشئ كفالة قائمة أو التزاماً مالياً نهائياً.</li>
                                <li>عدد الأيتام والمبلغ الشهري المقترح وطريقة الدفع المفضلة هي مقترحات للمناقشة، ولا تصبح نهائية إلا بعد تأكيدها مع المنظمة.</li>
                                <li>تعتمد المطابقة على توفر الحالات المستوفية للشروط وإجراءات المنظمة، ولا يضمن تقديم الطلب تخصيص يتيم بعينه.</li>
                                <li>تبدأ الكفالة بعد تأكيد التفاصيل واستكمال إجراءات التسجيل المعتمدة لدى المنظمة.</li>
                            </ul>
                        </div>
                        <div id="entitySponsorshipTerms" class="sponsorship-terms-content"<?php echo $form['sponsor_type'] === 'individual' ? ' hidden' : ''; ?>>
                            <ul class="mb-0">
                                <li>يمكن للشركة أو المنظمة اقتراح كفالة طفل واحد أو أكثر وتحديد ميزانية شهرية إجمالية مقترحة.</li>
                                <li>يتم الاتفاق مع المنظمة قبل التفعيل على العدد النهائي للأطفال والالتزام الشهري وجدول السداد والمستندات المطلوبة.</li>
                                <li>هذا الطلب إبداء رغبة مبدئية فقط؛ ولا ينشئ التزاماً مالياً ولا يفعّل أي كفالة.</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </section>
                <!-- الاسم الكامل -->
                <div class="f-row">
                    <label id="applicantNameLabel"><?php echo t('الاسم الكامل'); ?> *</label>
                    <input type="text" name="full_name" class="form-control" style="max-width:560px" maxlength="255" value="<?php echo e($form['full_name']); ?>" required>
                </div>
                <!-- الهاتف + الغرض -->
                <div class="f-row">
                    <label id="applicantPhoneLabel"><?php echo t('الهاتف'); ?> *</label>
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
                    <label id="applicantEmailLabel"><?php echo t('البريد الإلكتروني'); ?></label>
                    <input type="email" name="email" class="form-control" style="max-width:360px" dir="ltr" value="<?php echo e($form['email']); ?>">
                </div>
                <div id="entityContactFields" class="entity-contact-fields border rounded-3 p-3 mb-3<?php echo $form['sponsor_type'] === 'individual' ? ' is-collapsed' : ''; ?>" aria-hidden="<?php echo $form['sponsor_type'] === 'individual' ? 'true' : 'false'; ?>">
                    <h6 class="fw-bold mb-2"><?php echo AK_LANG === 'en' ? 'Authorized representative and contact preferences' : 'بيانات الشخص المفوض بالتواصل وتفضيلات الاتصال'; ?></h6>
                    <p class="small text-muted mb-3"><?php echo AK_LANG === 'en' ? 'Use the official representative details. The phone and email above are the organization’s contact channels.' : 'يرجى إدخال بيانات ممثل الجهة المفوض. الهاتف والبريد أعلاه هما قنوات التواصل الرسمية للجهة.'; ?></p>
                    <div class="f-row"><label for="contactPersonName"><?php echo AK_LANG === 'en' ? 'Representative full name' : 'اسم الشخص المفوض بالتواصل'; ?> *</label><input id="contactPersonName" type="text" name="contact_person_name" class="form-control" style="max-width:420px" maxlength="255" value="<?php echo e($form['contact_person_name']); ?>"></div>
                    <div class="f-row"><label for="contactPersonPosition"><?php echo AK_LANG === 'en' ? 'Job title / position' : 'المسمى الوظيفي / المنصب'; ?></label><input id="contactPersonPosition" type="text" name="contact_person_position" class="form-control" style="max-width:360px" maxlength="150" value="<?php echo e($form['contact_person_position']); ?>"></div>
                    <div class="f-row"><label for="preferredContactMethod"><?php echo AK_LANG === 'en' ? 'Preferred contact method' : 'طريقة التواصل المفضلة'; ?> *</label><select id="preferredContactMethod" name="preferred_contact_method" class="form-select" style="max-width:220px"><option value="call" <?php echo $form['preferred_contact_method'] === 'call' ? 'selected' : ''; ?>><?php echo AK_LANG === 'en' ? 'Phone call' : 'اتصال هاتفي'; ?></option><option value="whatsapp" <?php echo $form['preferred_contact_method'] === 'whatsapp' ? 'selected' : ''; ?>>WhatsApp</option><option value="email" <?php echo $form['preferred_contact_method'] === 'email' ? 'selected' : ''; ?>>Email</option><option value="any" <?php echo $form['preferred_contact_method'] === 'any' ? 'selected' : ''; ?>><?php echo AK_LANG === 'en' ? 'Any method' : 'أي وسيلة مناسبة'; ?></option></select></div>
                    <div class="f-row"><label for="preferredContactTime"><?php echo AK_LANG === 'en' ? 'Preferred contact time' : 'الوقت المناسب للتواصل'; ?></label><input id="preferredContactTime" type="text" name="preferred_contact_time" class="form-control" style="max-width:360px" maxlength="100" placeholder="<?php echo AK_LANG === 'en' ? 'e.g. 10:00 AM–2:00 PM' : 'مثلاً: من 10 صباحاً إلى 2 ظهراً'; ?>" value="<?php echo e($form['preferred_contact_time']); ?>"></div>
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
                <div class="f-row" id="desiredOrphansRow">
                    <label id="desiredOrphansLabel"><?php echo t('عدد الأيتام المطلوب كفالتهم'); ?></label>
                    <input type="number" min="1" max="50" name="desired_orphans" class="form-control" style="max-width:120px" dir="ltr" value="<?php echo e($form['desired_orphans']); ?>">
                </div>
                <div class="f-row" id="proposedAmountRow">
                    <label for="proposedAmountInput" id="proposedAmountLabel"><?php echo t('المبلغ الشهري المقترح'); ?></label>
                    <input id="proposedAmountInput" type="number" min="0" step="500" name="proposed_amount" class="form-control" style="max-width:220px" dir="ltr" value="<?php echo e($form['proposed_amount']); ?>" aria-describedby="proposedAmountHelp">
                    <small id="proposedAmountHelp" class="text-muted"><?php echo AK_LANG === 'en' ? 'This is a non-binding proposed amount for discussion.' : 'قيمة مقترحة للمناقشة وليست التزاماً مالياً نهائياً.'; ?></small>
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
    const entityFields = document.getElementById('entityContactFields');
    const contactName = document.getElementById('contactPersonName');
    const contactMethod = document.getElementById('preferredContactMethod');
    const nameLabel = document.getElementById('applicantNameLabel');
    const phoneLabel = document.getElementById('applicantPhoneLabel');
    const emailLabel = document.getElementById('applicantEmailLabel');
    const orphanLabel = document.getElementById('desiredOrphansLabel');
    const amountLabel = document.getElementById('proposedAmountLabel');
    const termsTitle = document.getElementById('sponsorshipTermsTitle');
    const individualTerms = document.getElementById('individualSponsorshipTerms');
    const entityTerms = document.getElementById('entitySponsorshipTerms');
    function syncSponsorshipTerms() {
        const individual = type.value === 'individual';
        if (individualTerms) individualTerms.hidden = !individual;
        if (entityTerms) entityTerms.hidden = individual;
        if (termsTitle) {
            const isEnglish = document.documentElement.lang === 'en';
            const title = type.value === 'company'
                ? (isEnglish ? 'Company sponsorship terms' : 'شروط كفالة الشركات')
                : type.value === 'organization'
                    ? (isEnglish ? 'Organization sponsorship terms' : 'شروط كفالة المنظمات')
                    : (isEnglish ? 'Individual sponsorship terms' : 'شروط كفالة الأفراد');
            termsTitle.textContent = title;
        }
    }
    function syncEntityFields() {
        const entity = type.value !== 'individual';
        if (entityFields) {
            entityFields.classList.toggle('is-collapsed', !entity);
            entityFields.setAttribute('aria-hidden', entity ? 'false' : 'true');
            entityFields.querySelectorAll('input, select, textarea').forEach(control => {
                control.disabled = !entity;
            });
        }
        if (contactName) contactName.required = entity;
        if (contactMethod) contactMethod.required = entity;
        if (nameLabel) nameLabel.textContent = entity ? (document.documentElement.lang === 'en' ? 'Company / organization name *' : 'اسم الشركة / المنظمة *') : (document.documentElement.lang === 'en' ? 'Full name *' : 'الاسم الكامل *');
        if (phoneLabel) phoneLabel.textContent = entity ? (document.documentElement.lang === 'en' ? 'Official contact phone *' : 'هاتف التواصل الرسمي *') : (document.documentElement.lang === 'en' ? 'Phone *' : 'الهاتف *');
        if (emailLabel) emailLabel.textContent = entity ? (document.documentElement.lang === 'en' ? 'Official email' : 'البريد الإلكتروني الرسمي') : (document.documentElement.lang === 'en' ? 'Email' : 'البريد الإلكتروني');
        if (orphanLabel) orphanLabel.textContent = entity ? (document.documentElement.lang === 'en' ? 'Initial number of children proposed' : 'العدد المبدئي للأطفال المقترح كفالتهم') : (document.documentElement.lang === 'en' ? 'Number of orphans to sponsor' : 'عدد الأيتام المطلوب كفالتهم');
        if (amountLabel) amountLabel.textContent = entity ? (document.documentElement.lang === 'en' ? 'Proposed total monthly budget' : 'الميزانية الشهرية الإجمالية المقترحة') : (document.documentElement.lang === 'en' ? 'Proposed monthly amount' : 'المبلغ الشهري المقترح');
    }
    function syncTypePresentation() {
        syncGenderRequirement();
        syncEntityFields();
        syncSponsorshipTerms();
    }
    // Update the form in the current document; changing applicant type must
    // never submit or navigate the enclosing form.
    type.addEventListener('input', syncTypePresentation);
    type.addEventListener('change', syncTypePresentation);
    if (entityFields) {
        entityFields.querySelectorAll('input, select, textarea').forEach(control => {
            control.disabled = type.value === 'individual';
        });
    }
    syncTypePresentation();
})();
(function () {
    const confirmation = document.getElementById('submissionConfirmationDialog');
    if (!confirmation) return;
    if (typeof confirmation.showModal === 'function') {
        confirmation.showModal();
    } else {
        confirmation.setAttribute('open', 'open');
    }
})();
</script>
</body>
</html>