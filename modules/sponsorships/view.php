<?php
// modules/sponsorships/view.php - Sponsorship profile (Orphan-level) + pause/resume/complete/cancel
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'accountant'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$pageTitle = 'تفاصيل الكفالة';
$active = 'sponsorships';

$id = (int)($_GET['id'] ?? 0);
$sp = dbFetchOne("SELECT sp.*, s.full_name AS sponsor_name, s.sponsor_code, s.id AS sponsor_id,
    fc.id AS child_id, fc.child_name,
    f.mother_name, f.family_code, f.id AS family_id
    FROM sponsorships sp
    JOIN sponsors s ON s.id = sp.sponsor_id
    JOIN family_children fc ON fc.id = sp.child_id
    JOIN families f ON f.id = fc.family_id
    WHERE sp.id = ?", [$id]);

if (!$sp) { flash('error', 'الكفالة غير موجودة.'); redirect('modules/sponsorships/index.php'); }

/* - supervisor ownership - */
$canManage = in_array($role, ['admin', 'vice_general_manager'], true);
if ($role === 'supervisor') {
    $myLetterIds = array_map('intval', array_column(dbFetchAll("SELECT letter_id FROM supervisor_letters WHERE supervisor_id = ?", [Session::getUserId()]), 'letter_id'));
    $own = dbFetchOne("SELECT id FROM sponsors WHERE id = ? AND (supervisor_id = ?" . 
        ($myLetterIds ? " OR first_letter_id IN (" . implode(',', $myLetterIds) . ")" : '') . ")", 
        array_merge([(int)$sp['sponsor_id'], Session::getUserId()]));
    $canManage = (bool)$own;
}

/* - POST actions - */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة.');
    } else {
        $action = $_POST['action'] ?? '';
        $today = date('Y-m-d');
        $old = $sp['status'];
        $new = $old;
        
        if ($action === 'pause' && $old === 'active') {
            $new = 'paused';
            dbExecute("UPDATE sponsorships SET status = 'paused', pause_reason = ?, pause_start_date = ?, updated_by = ? WHERE id = ?",
                [trim($_POST['pause_reason'] ?? '') ?: null, $today, Session::getUserId(), $id]);
        } elseif ($action === 'resume' && $old === 'paused') {
            $new = 'active';
            dbExecute("UPDATE sponsorships SET status = 'active', pause_end_date = ?, updated_by = ? WHERE id = ?",
                [$today, Session::getUserId(), $id]);
        } elseif ($action === 'complete' && in_array($old, ['active', 'paused'], true)) {
            $new = 'completed';
            dbExecute("UPDATE sponsorships SET status = 'completed', end_date = ?, updated_by = ? WHERE id = ?",
                [$today, Session::getUserId(), $id]);
            // Directive 3: Free up the orphan
            dbExecute("UPDATE family_children SET match_status = 'unmatched' WHERE id = ?", [$sp['child_id']]);
        } elseif ($action === 'cancel' && $old !== 'cancelled') {
            $new = 'cancelled';
            dbExecute("UPDATE sponsorships SET status = 'cancelled', end_date = ?, updated_by = ? WHERE id = ?",
                [$today, Session::getUserId(), $id]);
            // Directive 3: Push orphan to top of Auto-Matching queue
            dbExecute("UPDATE family_children SET match_status = 'lost_sponsor' WHERE id = ?", [$sp['child_id']]);
        }
        
        if ($new !== $old) {
            dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, ?, 'sponsorships', ?, ?, ?, ?, ?)",
                [Session::getUserId(), $action === 'pause' ? 'PAUSE' : ($action === 'resume' ? 'RESUME' : 'UPDATE'), $id,
                json_encode(['status' => $old], JSON_UNESCAPED_UNICODE),
                json_encode(['status' => $new, 'reason' => $_POST['pause_reason'] ?? null], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            
            flash('success', 'تم تحديث حالة الكفالة إلى: ' . $new);
            header('Location: ' . APP_URL . 'modules/sponsorships/view.php?id=' . $id);
            exit();
        }
    }
}

$transactions = dbFetchAll("SELECT t.id, t.transaction_code, t.amount, t.transaction_date, t.payment_method, t.status, t.receipt_number
    FROM transactions t
    WHERE t.sponsorship_id = ?
    ORDER BY t.transaction_date DESC, t.id DESC", [$id]);

$totalPaid = 0;
foreach ($transactions as $t) if ($t['status'] === 'posted') $totalPaid += (float)$t['amount'];

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2>كفالة <?php echo e($sp['sponsorship_code']); ?></h2>
    <p><?php echo e($sp['sponsor_name']); ?> ← <strong><?php echo e($sp['child_name']); ?></strong></p>
    <div class="quick-actions mt-3">
        <a href="<?php echo APP_URL; ?>modules/sponsorships/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-right me-1"></i> رجوع</a>
        <?php if ($canManage): ?>
            <?php if ($sp['status'] === 'active'): ?>
                <form method="post" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="pause">
                    <input type="text" name="pause_reason" placeholder="سبب الإيقاف" class="form-control form-control-sm d-inline-block w-auto">
                    <button class="btn btn-warning btn-sm" onclick="return confirm('إيقاف الكفالة؟')"><i class="fas fa-pause me-1"></i> إيقاف مؤقت</button>
                </form>
            <?php elseif ($sp['status'] === 'paused'): ?>
                <form method="post" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="resume">
                    <button class="btn btn-success btn-sm"><i class="fas fa-play me-1"></i> استئناف</button>
                </form>
            <?php endif; ?>
            
            <?php if (in_array($sp['status'], ['active', 'paused'], true)): ?>
                <form method="post" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="complete">
                    <button class="btn btn-info btn-sm" onclick="return confirm('إنهاء الكفالة كمكتملة؟')"><i class="fas fa-check-double me-1"></i> إكمال</button>
                </form>
                <form method="post" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="cancel">
                    <button class="btn btn-danger btn-sm" onclick="return confirm('إلغاء الكفالة نهائياً؟ سيتم إرجاع اليتيم لقائمة الانتظار.')"><i class="fas fa-ban me-1"></i> إلغاء</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="row g-3 mb-4 fade-in">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold" style="color:#1b4d8f"><?php echo number_format((float)$sp['monthly_amount'], 0); ?></div>
                <div class="text-muted small">المبلغ الشهري</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold" style="color:#1b4d8f">1</div>
                <div class="text-muted small">يتيم مكفول</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold" style="color:#1b4d8f"><?php echo count($transactions); ?></div>
                <div class="text-muted small">مدفوعات</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-4 fw-bold" style="color:#1b4d8f"><?php echo number_format($totalPaid, 0); ?></div>
                <div class="text-muted small">إجمالي المحصل</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 fade-in">
    <div class="card-header"><i class="fas fa-info-circle me-2"></i>التفاصيل</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <small class="text-muted">الكفيل</small>
                <div><a href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo (int)$sp['sponsor_id']; ?>"><?php echo e($sp['sponsor_name']); ?></a></div>
            </div>
            <div class="col-md-4">
                <small class="text-muted">اليتيم</small>
                <div><strong><?php echo e($sp['child_name']); ?></strong></div>
            </div>
            <div class="col-md-4">
                <small class="text-muted">الأسرة</small>
                <div><a href="<?php echo APP_URL; ?>modules/families/view.php?id=<?php echo (int)$sp['family_id']; ?>"><?php echo e($sp['mother_name']); ?></a></div>
            </div>
            <div class="col-md-4 mt-2">
                <small class="text-muted">الحالة</small>
                <div><?php echo e($sp['status']); ?></div>
            </div>
            <div class="col-md-4 mt-2">
                <small class="text-muted">تاريخ البداية</small>
                <div><?php echo e($sp['start_date']); ?></div>
            </div>
            <div class="col-md-4 mt-2">
                <small class="text-muted">تاريخ النهاية</small>
                <div><?php echo e($sp['end_date'] ?? '-'); ?></div>
            </div>
            <?php if ($sp['status'] === 'paused'): ?>
            <div class="col-md-4 mt-2">
                <small class="text-muted">سبب الإيقاف</small>
                <div><?php echo e($sp['pause_reason'] ?? '-'); ?> (من <?php echo e($sp['pause_start_date'] ?? '-'); ?>)</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mb-4 fade-in">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-money-bill-wave me-2"></i>سجل المدفوعات</span>
        <?php if ($canManage && in_array($sp['status'], ['active', 'paused'], true)): ?>
            <a href="<?php echo APP_URL; ?>modules/transactions/create.php?sponsorship_id=<?php echo $id; ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> تسجيل دفعة</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>التاريخ</th>
                        <th>المبلغ</th>
                        <th>الطريقة</th>
                        <th>الإيصال</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$transactions): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">لا توجد مدفوعات مسجلة.</td></tr>
                    <?php else: foreach ($transactions as $t): ?>
                        <tr>
                            <td><?php echo e($t['transaction_code']); ?></td>
                            <td><?php echo e($t['transaction_date']); ?></td>
                            <td><?php echo number_format((float)$t['amount'], 0); ?></td>
                            <td><?php echo e($t['payment_method']); ?></td>
                            <td><?php echo e($t['receipt_number'] ?? '-'); ?></td>
                            <td><?php echo $t['status'] === 'posted' ? '<span class="badge bg-success">مرحّلة</span>' : '<span class="badge bg-danger">ملغية</span>'; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>