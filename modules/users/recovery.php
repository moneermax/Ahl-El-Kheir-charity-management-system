<?php
// modules/users/recovery.php - Approve/reject password recovery requests (Admin + VGM)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'vice_general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = 'طلبات استرداد كلمات المرور';
$active    = 'users';

/* ---------- SMTP-ready mail stub (logs to outbox until hosting provides SMTP) ---------- */
if (!function_exists('ak_send_mail')) {
    function ak_send_mail(string $to, string $subject, string $body): bool {
        $set = [];
        foreach (dbFetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp\\_%'") as $r) $set[$r['setting_key']] = (string)$r['setting_value'];
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
        $line = date('Y-m-d H:i:s') . ' | to=' . $to . ' | subject=' . $subject . ' | smtp=' . (trim($set['smtp_host'] ?? '') !== '' ? 'configured' : 'not-configured') . ' | ' . $body;
        @file_put_contents($logDir . '/mail_outbox.log', $line . PHP_EOL, FILE_APPEND);
        // TODO(hosting): replace with PHPMailer using smtp_* settings when online.
        return false;
    }
}

$approvedCode = null; $approvedFor = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة.');
    } else {
        $rid = (int)($_POST['request_id'] ?? 0);
        $req = dbFetchOne("SELECT r.*, u.username, u.full_name, u.email
                           FROM password_recovery_requests r JOIN users u ON u.id = r.user_id
                           WHERE r.id = ?", [$rid]);

        if (!$req || $req['status'] !== 'pending') {
            flash('error', 'الطلب غير موجود أو تمت معالجته.');
        } elseif (isset($_POST['approve'])) {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            dbExecute("UPDATE password_recovery_requests
                       SET status='approved', code_hash=?, reviewed_by=?, reviewed_at=NOW(),
                           expires_at=DATE_ADD(NOW(), INTERVAL 24 HOUR)
                       WHERE id=?",
                [hash('sha256', $code), Session::getUserId(), $rid]);
            if (!empty($req['email'])) {
                ak_send_mail($req['email'], 'رمز استرداد كلمة المرور — أهل الخير', 'رمزك: ' . $code . ' (صالح 24 ساعة)');
            }
            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                           VALUES (?, 'RECOVERY', 'users', ?, NULL, ?, ?, ?)",
                    [Session::getUserId(), (int)$req['user_id'],
                     json_encode(['approved_request' => $rid], JSON_UNESCAPED_UNICODE),
                     $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            } catch (Throwable $e) {}
            $approvedCode = $code;
            $approvedFor  = $req['full_name'];
            flash('success', 'تمت الموافقة على طلب: ' . $req['full_name']);
        } elseif (isset($_POST['reject'])) {
            dbExecute("UPDATE password_recovery_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE id=?",
                [Session::getUserId(), $rid]);
            flash('success', 'تم رفض الطلب.');
        }
    }
    if ($approvedCode === null) { header('Location: ' . APP_URL . 'modules/users/recovery.php'); exit(); }
}

// Mark my recovery notifications as read
try { dbExecute("UPDATE notifications SET is_read = 1 WHERE recipient_user_id = ? AND type = 'recovery'", [Session::getUserId()]); } catch (Throwable $e) {}

$requests = dbFetchAll("
    SELECT r.*, u.username, u.full_name, rev.full_name AS reviewer_name
    FROM password_recovery_requests r
    JOIN users u ON u.id = r.user_id
    LEFT JOIN users rev ON rev.id = r.reviewed_by
    ORDER BY (r.status = 'pending') DESC, r.id DESC
    LIMIT 50
");

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>طلبات استرداد كلمات المرور</h2>
    <p>عند الموافقة يُنشأ رمز لمرة واحدة (صالح 24 ساعة) يُسلَّم للمستخدم هاتفياً أو حضورياً</p>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<?php if ($approvedCode): ?>
<div class="alert alert-warning fade-in">
    <h5 class="alert-heading"><i class="fas fa-key me-2"></i>رمز الاسترداد لـ <?php echo e($approvedFor); ?></h5>
    <p class="fs-3 fw-bold mb-1" dir="ltr" style="letter-spacing:6px"><?php echo e($approvedCode); ?></p>
    <p class="mb-0"><small>سلّم هذا الرمز للمستخدم الآن — لن يظهر مرة أخرى.</small></p>
</div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>#</th><th>المستخدم</th><th>تاريخ الطلب</th><th>الحالة</th><th>راجعه</th><th>الصلاحية</th><th class="text-center">إجراء</th></tr></thead>
                <tbody>
                <?php if (!$requests): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">لا توجد طلبات.</td></tr>
                <?php else: foreach ($requests as $r): ?>
                    <tr>
                        <td><?php echo (int)$r['id']; ?></td>
                        <td><strong><?php echo e($r['full_name']); ?></strong> <small class="text-muted">(<?php echo e($r['username']); ?>)</small></td>
                        <td><small><?php echo e($r['requested_at']); ?></small></td>
                        <td>
                            <?php
                            $st = ['pending' => ['قيد المراجعة','bg-warning'], 'approved' => ['موافق عليه','bg-info'], 'completed' => ['مكتمل','bg-success'], 'rejected' => ['مرفوض','bg-danger'], 'expired' => ['منتهي','bg-secondary']];
                            [$sl, $sc] = $st[$r['status']] ?? [$r['status'], 'bg-secondary'];
                            ?>
                            <span class="badge <?php echo $sc; ?>"><?php echo $sl; ?></span>
                        </td>
                        <td><?php echo e($r['reviewer_name'] ?? '—'); ?></td>
                        <td><small><?php echo $r['expires_at'] ? e($r['expires_at']) : '—'; ?></small></td>
                        <td class="text-center">
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="post" class="d-inline"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                                    <button name="approve" value="1" class="btn btn-sm btn-success" onclick="return confirm('الموافقة وتوليد رمز؟')"><i class="fas fa-check me-1"></i>موافقة</button>
                                    <button name="reject" value="1" class="btn btn-sm btn-danger" onclick="return confirm('رفض الطلب؟')"><i class="fas fa-ban me-1"></i>رفض</button>
                                </form>
                            <?php else: echo '—'; endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>