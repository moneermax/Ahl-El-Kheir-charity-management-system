<?php
// modules/users/password_recovery_request.php - Public password recovery request
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'modules/users/change_password.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.';
    } else {
        $identity = trim((string)($_POST['identity'] ?? ''));

        if ($identity === '') {
            $error = 'يرجى إدخال اسم المستخدم أو البريد الإلكتروني.';
        } else {
            try {
                // The Ahl El Kheir users table uses is_active, while role information
                // is stored in roles and linked through users.role_id.
                $user = dbFetchOne("SELECT id, username, full_name, email FROM users WHERE is_active = 1 AND (username = ? OR email = ?) LIMIT 1", [$identity, $identity]);

                if ($user) {
                    $pending = dbFetchOne("SELECT id FROM password_recovery_requests WHERE user_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1", [(int)$user['id']]);

                    if (!$pending) {
                        dbExecute("INSERT INTO password_recovery_requests (user_id, status, requested_at) VALUES (?, 'pending', NOW())", [(int)$user['id']]);

                        /* Notify both HR Manager and System Administrator so either authorized person can respond. */
                        $recoveryHandlers = dbFetchAll("SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.is_active = 1 AND r.code IN ('admin', 'hr_manager')");

                        foreach ($recoveryHandlers as $handler) {
                            try {
                                dbExecute("INSERT INTO notifications (recipient_user_id, type, title, body, link, is_read) VALUES (?, 'recovery', ?, ?, ?, 0)", [
                                    (int)$handler['id'],
                                    'طلب استعادة كلمة مرور جديد',
                                    'يوجد طلب جديد لاستعادة كلمة مرور للمستخدم: ' . $user['full_name'],
                                    'modules/users/recovery.php'
                                ]);
                            } catch (Throwable $e) {}
                        }
                    }
                }

                /* Generic response prevents account enumeration. */
                $message = 'تم استلام الطلب. إذا كان الحساب موجوداً ونشطاً، فسيقوم قسم الموارد البشرية أو مسؤول النظام بمراجعته والتواصل معك لاستلام كلمة المرور المؤقتة.';
            } catch (Throwable $e) {
                $error = 'تعذر إرسال الطلب حالياً. يرجى المحاولة مرة أخرى لاحقاً.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعادة كلمة المرور | <?php echo e(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background: linear-gradient(135deg, #0f172a, #1e293b); min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .recovery-card { width:100%; max-width:480px; background:#fff; border-radius:18px; box-shadow:0 20px 50px rgba(0,0,0,.25); overflow:hidden; }
        .recovery-head { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; padding:28px; text-align:center; }
        .recovery-head i { font-size:42px; margin-bottom:12px; }
        .recovery-body { padding:30px; }
        .form-control { min-height:48px; }
    </style>
</head>
<body>
<div class="recovery-card">
    <div class="recovery-head">
        <i class="fas fa-key"></i>
        <h2 class="h4 mb-1">استعادة كلمة المرور</h2>
        <p class="mb-0 opacity-75">طلب مساعدة من الموارد البشرية أو مسؤول النظام</p>
    </div>
    <div class="recovery-body">
        <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-circle-check me-1"></i><?php echo e($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-triangle-exclamation me-1"></i><?php echo e($error); ?></div><?php endif; ?>
        <?php if (!$message): ?>
        <p class="text-muted mb-4">أدخل اسم المستخدم أو البريد الإلكتروني المسجل في النظام. سيتم إرسال الطلب إلى الموارد البشرية ومسؤول النظام، ويمكن لأي منهما معالجته.</p>
        <form method="post" autocomplete="off">
            <?php echo csrf_field(); ?>
            <div class="mb-3"><label class="form-label fw-bold">اسم المستخدم أو البريد الإلكتروني</label><input type="text" name="identity" class="form-control" required autofocus maxlength="190"></div>
            <button type="submit" class="btn btn-primary w-100 py-2"><i class="fas fa-paper-plane me-1"></i> إرسال طلب الاستعادة</button>
        </form>
        <?php endif; ?>
        <div class="text-center mt-4"><a href="<?php echo e(APP_URL); ?>index.php" class="text-decoration-none"><i class="fas fa-arrow-right me-1"></i> العودة إلى تسجيل الدخول</a></div>
    </div>
</div>
</body>
<<<<<<< HEAD
</html>
=======
</html>
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
