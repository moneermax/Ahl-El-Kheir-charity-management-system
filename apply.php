<?php
// Root: apply.php - Public sponsor application form (no login required)
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/session.php';
if (is_file(__DIR__ . '/config/lang.php')) require_once __DIR__ . '/config/lang.php';
Session::start();

if (!defined('AK_LANG')) define('AK_LANG', 'ar');
if (!defined('AK_DIR'))  define('AK_DIR', 'rtl');
if (!function_exists('t')) { function t(string $s): string { return function_exists('ak_t') ? ak_t($s) : $s; } }

dbExecute("CREATE TABLE IF NOT EXISTS sponsor_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL, phone VARCHAR(30) DEFAULT NULL, email VARCHAR(190) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    sponsor_type ENUM('individual','company','organization') NOT NULL DEFAULT 'individual',
    preferred_payment_method ENUM('cash','bank_transfer','credit_card','mobile','other') NOT NULL DEFAULT 'cash',
    proposed_amount DECIMAL(12,2) DEFAULT NULL, notes TEXT DEFAULT NULL,
    first_letter_raw VARCHAR(10) DEFAULT NULL, first_letter_id TINYINT UNSIGNED DEFAULT NULL,
    assigned_supervisor_id INT UNSIGNED DEFAULT NULL,
    status ENUM('pending','contacted','confirmed','rejected') NOT NULL DEFAULT 'pending',
    contact_notes TEXT DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL, confirmed_by INT UNSIGNED NULL, created_sponsor_id INT UNSIGNED NULL,
    KEY idx_sr_status (status), KEY idx_sr_supervisor (assigned_supervisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
foreach ([
  "ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS phone_purpose VARCHAR(20) NOT NULL DEFAULT 'both'",
  "ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS alt_phone VARCHAR(30) NULL",
  "ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS alt_phone_purpose VARCHAR(20) NOT NULL DEFAULT 'both'",
  "ALTER TABLE sponsor_requests ADD COLUMN IF NOT EXISTS desired_orphans SMALLINT UNSIGNED NULL",
] as $ddl) dbExecute($ddl);

$purposes = ['call' => 'للاتصال', 'whatsapp' => 'واتساب', 'both' => 'للاتصال وواتساب'];

$done = false; $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($name === '')  $errors[] = 'الاسم الكامل مطلوب.';
    if ($phone === '') $errors[] = 'الهاتف مطلوب.';
    if (!$errors) {
        [$raw, $norm] = first_letter_of($name);
        $letter = dbFetchOne("SELECT id FROM letters WHERE code = ?", [$norm]);
        $letterId = $letter ? (int)$letter['id'] : null;
        $supId = null;
        if ($letterId) {
            $s = dbFetchOne("SELECT supervisor_id FROM supervisor_letters WHERE letter_id = ?", [$letterId]);
            $supId = $s ? (int)$s['supervisor_id'] : null;
        }
        dbExecute("INSERT INTO sponsor_requests
            (full_name, phone, phone_purpose, alt_phone, alt_phone_purpose, email, address, sponsor_type,
             preferred_payment_method, proposed_amount, desired_orphans, notes, first_letter_raw, first_letter_id, assigned_supervisor_id, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')",
            [$name, $phone,
             in_array($_POST['phone_purpose'] ?? '', ['call','whatsapp','both'], true) ? $_POST['phone_purpose'] : 'both',
             trim($_POST['alt_phone'] ?? '') ?: null,
             in_array($_POST['alt_phone_purpose'] ?? '', ['call','whatsapp','both'], true) ? $_POST['alt_phone_purpose'] : 'both',
             trim($_POST['email'] ?? '') ?: null, trim($_POST['address'] ?? '') ?: null,
             in_array($_POST['sponsor_type'] ?? '', ['individual','company','organization'], true) ? $_POST['sponsor_type'] : 'individual',
             in_array($_POST['preferred_payment_method'] ?? '', ['cash','bank_transfer','credit_card','mobile','other'], true) ? $_POST['preferred_payment_method'] : 'cash',
             trim($_POST['proposed_amount'] ?? '') !== '' ? (float)$_POST['proposed_amount'] : null,
             trim($_POST['desired_orphans'] ?? '') !== '' ? (int)$_POST['desired_orphans'] : null,
             trim($_POST['notes'] ?? '') ?: null, $raw, $letterId, $supId]);
        $reqId = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);
        try {
            $targets = [];
            if ($supId) $targets[] = $supId;
            foreach (dbFetchAll("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code IN ('admin','vice_general_manager') AND u.is_active = 1") as $u)
                $targets[] = (int)$u['id'];
            foreach (array_unique($targets) as $tid) {
                dbExecute("INSERT INTO notifications (recipient_user_id, type, title, body, link) VALUES (?, 'sponsor_request', ?, ?, 'modules/sponsors/requests.php')",
                    [$tid, 'طلب كفيل جديد', $name . ' — ' . $phone]);
            }
        } catch (Throwable $e) {}
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
                <!-- الاسم الكامل -->
                <div class="f-row">
                    <label><?php echo t('الاسم الكامل'); ?> *</label>
                    <input type="text" name="full_name" class="form-control" style="max-width:560px" required>
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
                    <input type="email" name="email" class="form-control" style="max-width:360px" dir="ltr">
                </div>
                <!-- النوع + طريقة الدفع -->
                <div class="f-row">
                    <label><?php echo t('النوع'); ?></label>
                    <select name="sponsor_type" class="form-select" style="max-width:200px">
                        <option value="individual"><?php echo t('فرد'); ?></option>
                        <option value="company"><?php echo t('شركة'); ?></option>
                        <option value="organization"><?php echo t('منظمة'); ?></option>
                    </select>
                    <label class="gap-label"><?php echo t('طريقة الدفع المفضلة'); ?></label>
                    <select name="preferred_payment_method" class="form-select" style="max-width:200px">
                        <option value="cash"><?php echo t('نقدي'); ?></option>
                        <option value="bank_transfer"><?php echo t('تحويل بنكي'); ?></option>
                        <option value="mobile"><?php echo t('محفظة إلكترونية'); ?></option>
                        <option value="other"><?php echo t('أخرى'); ?></option>
                    </select>
                </div>
                <!-- العنوان -->
                <div class="f-row">
                    <label><?php echo t('العنوان'); ?></label>
                    <input type="text" name="address" class="form-control" style="max-width:560px">
                </div>
                <!-- عدد الأيتام + المبلغ المقترح -->
                <div class="f-row">
                    <label><?php echo t('عدد الأيتام المطلوب كفالتهم'); ?></label>
                    <input type="number" min="1" max="50" name="desired_orphans" class="form-control" style="max-width:120px" dir="ltr">
                    <label class="gap-label"><?php echo t('المبلغ الشهري المقترح'); ?></label>
                    <input type="number" min="0" step="500" name="proposed_amount" class="form-control" style="max-width:160px" dir="ltr">
                </div>
                <!-- ملاحظات -->
                <div class="f-row" style="align-items:flex-start">
                    <label><?php echo t('ملاحظات'); ?></label>
                    <textarea name="notes" rows="2" class="form-control" style="max-width:560px"></textarea>
                </div>
                <div class="mt-4 text-center">
                    <button class="btn btn-primary px-5"><i class="fas fa-paper-plane me-1"></i> <?php echo t('إرسال الطلب'); ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>