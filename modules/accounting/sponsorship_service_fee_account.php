<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';

Session::start();
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'financial_manager'], true)) {
    http_response_code(403);
    exit('هذه الصفحة مخصصة للإدارة المخولة بإعدادات الحسابات.');
}

$pageTitle = 'حساب إيراد رسوم خدمة الكفالات';
$active = 'sponsorship_service_fee_account';
$uid = (int)Session::getUserId();
$errors = [];
$accountId = (int)($_POST['account_id'] ?? 0);
$effectiveFrom = trim((string)($_POST['effective_from'] ?? date('Y-m-d')));
$authorizationReference = trim((string)($_POST['authorization_reference'] ?? ''));
$authorizationNote = trim((string)($_POST['authorization_note'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'انتهت صلاحية الجلسة. أعد تحميل الصفحة وحاول مرة أخرى.';
    } else {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveFrom);
        if (!$date || $date->format('Y-m-d') !== $effectiveFrom) {
            $errors[] = 'حدد تاريخ سريان صحيحاً.';
        }
        if ($accountId <= 0) $errors[] = 'اختر حساب إيراد نشطاً.';
        if ($authorizationReference === '' || strlen($authorizationReference) > 255) {
            $errors[] = 'مرجع الاعتماد مطلوب ويجب ألا يتجاوز 255 حرفاً.';
        }
        if (strlen($authorizationNote) > 4000) $errors[] = 'ملاحظة الاعتماد أطول من الحد المسموح.';
        if (!$errors) {
            try {
                db()->beginTransaction();
                $account = dbFetchOne(
                    "SELECT id, code, name_ar, account_type, is_active
                     FROM accounts WHERE id = ? FOR UPDATE",
                    [$accountId]
                );
                if (!$account || (string)$account['account_type'] !== 'revenue' || (int)$account['is_active'] !== 1) {
                    throw new RuntimeException('الحساب المختار غير موجود أو غير نشط أو ليس حساب إيراد.');
                }
                dbFetchOne(
                    "SELECT id FROM sponsorship_service_fee_account_mappings
                     WHERE effective_from = ? ORDER BY version_no DESC, id DESC LIMIT 1 FOR UPDATE",
                    [$effectiveFrom]
                );
                $version = (int)(dbFetchOne(
                    "SELECT COALESCE(MAX(version_no), 0) AS max_version
                     FROM sponsorship_service_fee_account_mappings WHERE effective_from = ?",
                    [$effectiveFrom]
                )['max_version'] ?? 0) + 1;
                dbExecute(
                    "INSERT INTO sponsorship_service_fee_account_mappings
                        (effective_from, version_no, account_id, authorization_reference, authorization_note, created_by)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $effectiveFrom, $version, $accountId, $authorizationReference,
                        $authorizationNote !== '' ? $authorizationNote : null, $uid
                    ]
                );
                $mappingId = (int)dbLastInsertId();
                if ($mappingId <= 0) throw new RuntimeException('تعذر حفظ إعداد حساب رسوم الخدمة.');
                dbExecute(
                    "INSERT INTO audit_log
                        (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                     VALUES (?, ?, 'sponsorship_service_fee_account_mappings', ?, ?, ?, ?, ?)",
                    [
                        $uid, 'CREATE', $mappingId, json_encode([], JSON_UNESCAPED_UNICODE),
                        json_encode([
                            'effective_from'=>$effectiveFrom,
                            'version_no'=>$version,
                            'account_id'=>$accountId,
                            'account_code'=>$account['code'],
                            'account_name'=>$account['name_ar'],
                            'authorization_reference'=>$authorizationReference,
                            'authorization_note'=>$authorizationNote !== '' ? $authorizationNote : null
                        ], JSON_UNESCAPED_UNICODE),
                        $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]
                );
                db()->commit();
                flash('success', 'تم تسجيل إصدار إعداد حساب رسوم الخدمة رقم ' . $version . '. لن تتغير القيود المرحلة سابقاً.');
                header('Location: ' . APP_URL . 'modules/accounting/sponsorship_service_fee_account.php');
                exit();
            } catch (Throwable $e) {
                if (db()->inTransaction()) db()->rollBack();
                $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'تعذر حفظ إعداد حساب رسوم الخدمة. لم يتم اعتماد أي تغيير.';
            }
        }
    }
}

$accounts = dbFetchAll(
    "SELECT id, code, name_ar FROM accounts
     WHERE account_type = 'revenue' AND is_active = 1
     ORDER BY code"
);
$history = dbFetchAll(
    "SELECT m.*, a.code AS account_code, a.name_ar AS account_name, u.full_name AS creator_name
     FROM sponsorship_service_fee_account_mappings m
     JOIN accounts a ON a.id = m.account_id
     JOIN users u ON u.id = m.created_by
     ORDER BY m.effective_from DESC, m.version_no DESC, m.id DESC
     LIMIT 100"
);
$current = dbFetchOne(
    "SELECT m.*, a.code AS account_code, a.name_ar AS account_name
     FROM sponsorship_service_fee_account_mappings m
     JOIN accounts a ON a.id = m.account_id
     WHERE m.effective_from <= CURDATE()
     ORDER BY m.effective_from DESC, m.version_no DESC, m.id DESC
     LIMIT 1"
);

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-coins me-2"></i>حساب إيراد رسوم خدمة الكفالات</h2>
    <p>إعداد مؤرخ وقابل للمراجعة يحدد حساب الإيراد الذي يستقبل رسوم الخدمة الفعلية. لا يوجد حساب افتراضي؛ يلزم إدخال مرجع قرار الاعتماد قبل تفعيل أي إعداد.</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="alert alert-warning">
    لا يكفي اختيار حساب من دليل الحسابات. يجب أن يستند الإعداد إلى قرار محاسبي معتمد. لا يؤثر تغيير الإعداد على القيود المرحلة؛ كل معاملة جديدة تحفظ رقم إصدار الإعداد والحساب المستخدم عند الترحيل.
</div>

<div class="card mb-4 fade-in">
    <div class="card-header"><i class="fas fa-sliders me-2"></i>تسجيل إصدار إعداد جديد</div>
    <div class="card-body">
        <form method="post" class="row g-3">
            <?php echo csrf_field(); ?>
            <div class="col-md-5">
                <label class="form-label">حساب الإيراد النشط *</label>
                <select name="account_id" class="form-select" required>
                    <option value="">اختر حساب الإيراد</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo (int)$account['id']; ?>" <?php echo $accountId === (int)$account['id'] ? 'selected' : ''; ?>>
                            <?php echo e($account['code'] . ' — ' . $account['name_ar']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">تاريخ السريان *</label>
                <input type="date" name="effective_from" class="form-control" required value="<?php echo e($effectiveFrom); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">مرجع قرار الاعتماد *</label>
                <input type="text" name="authorization_reference" class="form-control" maxlength="255" required value="<?php echo e($authorizationReference); ?>" placeholder="رقم محضر / قرار / اعتماد">
            </div>
            <div class="col-12">
                <label class="form-label">ملاحظة القرار</label>
                <textarea name="authorization_note" class="form-control" rows="2" maxlength="4000"><?php echo e($authorizationNote); ?></textarea>
            </div>
            <div class="col-12 text-end"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>حفظ إصدار جديد</button></div>
        </form>
    </div>
</div>

<div class="card mb-4 fade-in">
    <div class="card-header">الإعداد الساري اليوم</div>
    <div class="card-body">
        <?php if ($current): ?>
            <strong><?php echo e($current['account_code'] . ' — ' . $current['account_name']); ?></strong>
            <div class="small text-muted">ساري من <?php echo e($current['effective_from']); ?> — الإصدار <?php echo (int)$current['version_no']; ?> — مرجع الاعتماد: <?php echo e($current['authorization_reference']); ?></div>
        <?php else: ?>
            <div class="text-danger">لم يتم تسجيل حساب إيراد معتمد حتى الآن. أي دفعة تحتوي على رسوم خدمة فعلية ستبقى غير قابلة للترحيل حتى تسجيل الإعداد.</div>
        <?php endif; ?>
    </div>
</div>

<div class="card fade-in">
    <div class="card-header">سجل الإصدارات — محفوظ دون حذف أو تعديل</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>السريان</th><th>الإصدار</th><th>الحساب</th><th>مرجع الاعتماد</th><th>الملاحظة</th><th>أُدخل بواسطة</th><th>تاريخ الإدخال</th></tr></thead>
                <tbody>
                <?php if (!$history): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">لا توجد إعدادات مسجلة.</td></tr>
                <?php else: foreach ($history as $row): ?>
                    <tr>
                        <td><?php echo e($row['effective_from']); ?></td>
                        <td><?php echo (int)$row['version_no']; ?></td>
                        <td><?php echo e($row['account_code'] . ' — ' . $row['account_name']); ?></td>
                        <td><?php echo e($row['authorization_reference']); ?></td>
                        <td><?php echo e($row['authorization_note'] ?? '—'); ?></td>
                        <td><?php echo e($row['creator_name']); ?></td>
                        <td><?php echo e($row['created_at']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
