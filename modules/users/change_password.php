<?php
// modules/users/change_password.php - Self-service password change (all roles)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'تغيير كلمة المرور';
$active    = 'password';

$me = dbFetchOne("SELECT * FROM users WHERE id = ?", [current_user_id()]);
$hashCol = null;
if ($me) {
    if (array_key_exists('password_hash', $me))      $hashCol = 'password_hash';
    elseif (array_key_exists('password', $me))       $hashCol = 'password';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', t('انتهت صلاحية الجلسة، حاول مرة أخرى.'));
    } else {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if (!$me || !$hashCol) {
            flash('error', t('تعذر تحميل بيانات الحساب.'));
        } elseif ($current === '' || $new === '' || $confirm === '') {
            flash('error', t('يرجى تعبئة جميع الحقول.'));
        } elseif (!password_verify($current, (string)$me[$hashCol])) {
            flash('error', t('كلمة المرور الحالية غير صحيحة.'));
        } elseif (mb_strlen($new) < 6) {
            flash('error', t('كلمة المرور الجديدة يجب ألا تقل عن 6 أحرف.'));
        } elseif ($new !== $confirm) {
            flash('error', t('كلمتا المرور الجديدتان غير متطابقتين.'));
        } else {
            dbExecute("UPDATE users SET {$hashCol} = ? WHERE id = ?", [password_hash($new, PASSWORD_DEFAULT), current_user_id()]);
            dbExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, 'CHANGE_PASSWORD', 'users', ?, NULL, ?, ?, ?)",
                [
                    current_user_id(),
                    current_user_id(),
                    json_encode(['password_changed' => true], JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );
            flash('success', t('تم تغيير كلمة المرور بنجاح.'));
            redirect(url('modules/users/change_password.php'));
        }
    }
    redirect(url('modules/users/change_password.php'));
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-key me-2"></i><?php echo t('تغيير كلمة المرور'); ?></h2>
    <p><?php echo t('اختر كلمة مرور قوية ولا تشاركها مع أحد.'); ?></p>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="card mb-4 fade-in" style="max-width:560px">
    <div class="card-body p-4">
        <form method="post" autocomplete="new-password">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label"><?php echo t('كلمة المرور الحالية'); ?></label>
                <div class="input-group">
                    <input type="password" name="current_password" id="ak_cur" class="form-control" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="var i=document.getElementById('ak_cur');i.type=i.type==='password'?'text':'password';"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label"><?php echo t('كلمة المرور الجديدة'); ?></label>
                <div class="input-group">
                    <input type="password" name="new_password" id="ak_new" class="form-control" minlength="6" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="var i=document.getElementById('ak_new');i.type=i.type==='password'?'text':'password';"><i class="fas fa-eye"></i></button>
                </div>
                <div class="form-text"><?php echo t('6 أحرف على الأقل.'); ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label"><?php echo t('تأكيد كلمة المرور الجديدة'); ?></label>
                <div class="input-group">
                    <input type="password" name="confirm_password" id="ak_conf" class="form-control" minlength="6" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="var i=document.getElementById('ak_conf');i.type=i.type==='password'?'text':'password';"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?php echo t('حفظ'); ?></button>
                <a href="<?php echo url('modules/users/profile.php'); ?>" class="btn btn-outline-secondary"><?php echo t('رجوع'); ?></a>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>