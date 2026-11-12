<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$role = (string) Session::getUserRole();
if (!in_array($role, ['financial_manager', 'fm'], true)) {
    http_response_code(403);
    exit('هذه الصفحة مخصصة للمدير المالي.');
}

$pageTitle = 'اتفاقيات الكفالات الشهرية';
$active = 'sponsorship_monthly_agreements';
$uid = (int) Session::getUserId();
$errors = [];
$success = '';
$search = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
if (strlen($search) > 120) {
    $search = substr($search, 0, 120);
}

$monthInput = trim((string) ($_GET['month'] ?? $_POST['covered_month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthInput)) {
    $monthInput = date('Y-m');
}
$coveredMonth = $monthInput . '-01';
$selectedId = (int) ($_GET['sponsorship_id'] ?? $_POST['sponsorship_id'] ?? 0);

function ak_sma_amount(string $raw, bool $allowZero, string $label): string
{
    $raw = trim(str_replace(',', '', $raw));
    if ($raw === '' || !is_numeric($raw)) {
        throw new RuntimeException('أدخل ' . $label . ' كقيمة رقمية صحيحة.');
    }
    $amount = (float) $raw;
    if (!is_finite($amount) || $amount < 0 || (!$allowZero && $amount <= 0) || $amount > 9999999999.99) {
        throw new RuntimeException('قيمة ' . $label . ' خارج النطاق المسموح.');
    }
    return number_format($amount, 2, '.', '');
}

function ak_sma_load_sponsorship(int $sponsorshipId, bool $forUpdate = false): ?array
{
    $sql = "SELECT sp.id, sp.sponsorship_code, sp.monthly_amount, sp.currency_code, sp.status,
                   s.full_name AS sponsor_name, fc.child_name
            FROM sponsorships sp
            JOIN sponsors s ON s.id = sp.sponsor_id
            LEFT JOIN family_children fc ON fc.id = sp.child_id
            WHERE sp.id = ?" . ($forUpdate ? " FOR UPDATE" : "");
    return dbFetchOne($sql, [$sponsorshipId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'انتهت صلاحية الجلسة. أعد تحميل الصفحة وحاول مرة أخرى.';
    } else {
        try {
            $selectedId = (int) ($_POST['sponsorship_id'] ?? 0);
            $monthInput = trim((string) ($_POST['covered_month'] ?? ''));
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthInput)) {
                throw new RuntimeException('حدد شهر الاتفاقية بصورة صحيحة.');
            }
            $coveredMonth = $monthInput . '-01';
            $agreedSponsorship = ak_sma_amount((string) ($_POST['agreed_sponsorship_amount'] ?? ''), false, 'قيمة الكفالة الشهرية المتفق عليها');
            $agreedServiceFee = ak_sma_amount((string) ($_POST['agreed_service_fee_amount'] ?? ''), true, 'رسوم الخدمة الشهرية المتفق عليها');
            $meetingReference = trim((string) ($_POST['meeting_reference'] ?? ''));
            $amendmentReason = trim((string) ($_POST['amendment_reason'] ?? ''));
            if (strlen($meetingReference) > 255) {
                throw new RuntimeException('مرجع الاجتماع يجب ألا يتجاوز 255 حرفاً.');
            }
            if (strlen($amendmentReason) > 4000) {
                throw new RuntimeException('سبب التعديل طويل أكثر من الحد المسموح.');
            }
            if ($selectedId <= 0) {
                throw new RuntimeException('اختر الكفالة أولاً.');
            }

            db()->beginTransaction();
            $sponsorship = ak_sma_load_sponsorship($selectedId, true);
            if (!$sponsorship || !in_array((string) $sponsorship['status'], ['active', 'paused'], true)) {
                throw new RuntimeException('الكفالة غير موجودة أو لا تسمح حالتها بإعداد اتفاقية شهرية.');
            }

            $latest = dbFetchOne(
                "SELECT * FROM sponsorship_monthly_agreements
                 WHERE sponsorship_id = ? AND covered_month = ?
                 ORDER BY version_no DESC, id DESC LIMIT 1 FOR UPDATE",
                [$selectedId, $coveredMonth]
            );

            $allocation = dbFetchOne(
                "SELECT COUNT(*) AS allocation_count,
                        COUNT(DISTINCT NULLIF(a.commitment_amount, 0)) AS commitment_versions,
                        MIN(NULLIF(a.commitment_amount, 0)) AS frozen_commitment
                 FROM sponsorship_payment_allocations a
                 JOIN transactions t ON t.id = a.transaction_id
                 WHERE a.sponsorship_id = ? AND a.covered_month = ?
                   AND t.status = 'posted' AND t.voided_at IS NULL",
                [$selectedId, $coveredMonth]
            );
            $allocationCount = (int) ($allocation['allocation_count'] ?? 0);
            if ((int) ($allocation['commitment_versions'] ?? 0) > 1) {
                throw new RuntimeException('توجد لقطات التزام متعارضة لهذا الشهر. أوقفنا التعديل لحماية السجل المالي.');
            }

            $frozenCommitment = $allocationCount > 0
                ? round((float) ($allocation['frozen_commitment'] ?? 0), 2)
                : null;
            if ($allocationCount > 0 && $frozenCommitment <= 0) {
                throw new RuntimeException('تعذر تحديد الالتزام المجمد من التخصيصات المرحلة لهذا الشهر.');
            }
            if ($frozenCommitment !== null && $latest
                && round((float) $latest['agreed_sponsorship_amount'], 2) !== $frozenCommitment) {
                throw new RuntimeException('إصدار الاتفاقية الحالي لا يطابق الالتزام المثبت في التخصيصات المرحلة. يلزم فحص ومطابقة السجل قبل أي تعديل.');
            }
            if ($frozenCommitment !== null && round((float) $agreedSponsorship, 2) !== $frozenCommitment) {
                throw new RuntimeException(
                    'تم ترحيل تخصيص لهذا الشهر؛ لا يمكن تغيير قيمة التزام الكفالة (' .
                    number_format($frozenCommitment, 2) .
                    '). يمكن تعديل رسوم الخدمة مع الإبقاء على الالتزام نفسه.'
                );
            }
            if ($latest && $amendmentReason === '') {
                throw new RuntimeException('اكتب سبب تعديل الاتفاقية قبل إنشاء إصدار جديد.');
            }

            $versionNo = $latest ? ((int) $latest['version_no'] + 1) : 1;
            $currency = (string) ($sponsorship['currency_code'] ?? 'SDG');
            if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new RuntimeException('عملة الكفالة غير صالحة لإعداد الاتفاقية.');
            }

            dbExecute(
                "INSERT INTO sponsorship_monthly_agreements
                    (sponsorship_id, covered_month, version_no, agreed_sponsorship_amount,
                     agreed_service_fee_amount, currency_code, meeting_reference,
                     amendment_reason, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $selectedId, $coveredMonth, $versionNo, $agreedSponsorship,
                    $agreedServiceFee, $currency,
                    $meetingReference !== '' ? $meetingReference : null,
                    $amendmentReason !== '' ? $amendmentReason : null,
                    $uid
                ]
            );
            $agreementId = (int) dbLastInsertId();
            if ($agreementId <= 0) {
                throw new RuntimeException('تعذر حفظ إصدار الاتفاقية.');
            }

            $oldValues = $latest ? [
                'version_no' => (int) $latest['version_no'],
                'agreed_sponsorship_amount' => (float) $latest['agreed_sponsorship_amount'],
                'agreed_service_fee_amount' => (float) $latest['agreed_service_fee_amount']
            ] : null;
            $newValues = [
                'version_no' => $versionNo,
                'covered_month' => $coveredMonth,
                'agreed_sponsorship_amount' => (float) $agreedSponsorship,
                'agreed_service_fee_amount' => (float) $agreedServiceFee,
                'meeting_reference' => $meetingReference !== '' ? $meetingReference : null,
                'amendment_reason' => $amendmentReason !== '' ? $amendmentReason : null,
                'frozen_commitment' => $frozenCommitment
            ];
            dbExecute(
                "INSERT INTO audit_log
                    (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, ?, 'sponsorship_monthly_agreements', ?, ?, ?, ?, ?)",
                [
                    $uid,
                    $latest ? 'AMEND' : 'CREATE',
                    $agreementId,
                    $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                    json_encode($newValues, JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );
            db()->commit();

            flash('success', 'تم حفظ إصدار الاتفاقية الشهرية رقم ' . $versionNo . ' دون تعديل الإصدارات أو الدفعات السابقة.');
            header('Location: ' . APP_URL . 'modules/sponsorships/monthly_agreements.php?sponsorship_id=' . $selectedId . '&month=' . rawurlencode($monthInput) . '&q=' . rawurlencode($search));
            exit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'تعذر حفظ الاتفاقية الشهرية. لم يتم اعتماد أي تغيير.';
        }
    }
}

$sponsorships = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $sponsorships = dbFetchAll(
        "SELECT sp.id, sp.sponsorship_code, sp.monthly_amount, sp.currency_code, sp.status,
                s.full_name AS sponsor_name, fc.child_name
         FROM sponsorships sp
         JOIN sponsors s ON s.id = sp.sponsor_id
         LEFT JOIN family_children fc ON fc.id = sp.child_id
         WHERE sp.status IN ('active','paused')
           AND (sp.sponsorship_code LIKE ? OR s.full_name LIKE ? OR fc.child_name LIKE ?)
         ORDER BY s.full_name, fc.child_name, sp.id
         LIMIT 100",
        [$like, $like, $like]
    );
} else {
    $sponsorships = dbFetchAll(
        "SELECT sp.id, sp.sponsorship_code, sp.monthly_amount, sp.currency_code, sp.status,
                s.full_name AS sponsor_name, fc.child_name
         FROM sponsorships sp
         JOIN sponsors s ON s.id = sp.sponsor_id
         LEFT JOIN family_children fc ON fc.id = sp.child_id
         WHERE sp.status IN ('active','paused')
         ORDER BY s.full_name, fc.child_name, sp.id
         LIMIT 100"
    );
}

$selected = $selectedId > 0 ? ak_sma_load_sponsorship($selectedId) : null;
$latest = null;
$history = [];
$allocation = null;
if ($selected) {
    $latest = dbFetchOne(
        "SELECT a.*, u.full_name AS creator_name
         FROM sponsorship_monthly_agreements a
         JOIN users u ON u.id = a.created_by
         WHERE a.sponsorship_id = ? AND a.covered_month = ?
         ORDER BY a.version_no DESC, a.id DESC LIMIT 1",
        [$selectedId, $coveredMonth]
    );
    $history = dbFetchAll(
        "SELECT a.*, u.full_name AS creator_name
         FROM sponsorship_monthly_agreements a
         JOIN users u ON u.id = a.created_by
         WHERE a.sponsorship_id = ? AND a.covered_month = ?
         ORDER BY a.version_no DESC, a.id DESC",
        [$selectedId, $coveredMonth]
    );
    $allocation = dbFetchOne(
        "SELECT COUNT(*) AS allocation_count,
                COUNT(DISTINCT NULLIF(a.commitment_amount, 0)) AS commitment_versions,
                MIN(NULLIF(a.commitment_amount, 0)) AS frozen_commitment
         FROM sponsorship_payment_allocations a
         JOIN transactions t ON t.id = a.transaction_id
         WHERE a.sponsorship_id = ? AND a.covered_month = ?
           AND t.status = 'posted' AND t.voided_at IS NULL",
        [$selectedId, $coveredMonth]
    );
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-file-signature me-2"></i>اتفاقيات الكفالات الشهرية</h2>
    <p>تسجيل القيم التي اتفق عليها GM وVGM وFM لكل كفالة وشهر. يقوم المدير المالي بإدخال الاتفاق، ولا يغيّر هذا الإجراء أي دفعة أو تخصيص أو قيد سابق.</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card mb-4 fade-in">
    <div class="card-header"><i class="fas fa-search me-2"></i>البحث عن الكفالة</div>
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-7">
                <label class="form-label">اسم الكفيل أو اليتيم أو رمز الكفالة</label>
                <input type="text" name="q" class="form-control" value="<?php echo e($search); ?>" placeholder="اكتب اسم الكفيل أو رمز الكفالة">
            </div>
            <div class="col-md-3">
                <label class="form-label">الشهر محل الاتفاق</label>
                <input type="month" name="month" class="form-control" required value="<?php echo e($monthInput); ?>">
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fas fa-search me-1"></i>بحث</button></div>
        </form>
        <?php if ($sponsorships): ?>
            <div class="table-responsive mt-3">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>رمز الكفالة</th><th>الكفيل</th><th>اليتيم</th><th>المبلغ المسجل حالياً</th><th>الحالة</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($sponsorships as $item): ?>
                        <tr>
                            <td><strong><?php echo e($item['sponsorship_code']); ?></strong></td>
                            <td><?php echo e($item['sponsor_name']); ?></td>
                            <td><?php echo e($item['child_name'] ?? '—'); ?></td>
                            <td><?php echo number_format((float) $item['monthly_amount'], 2); ?> <?php echo e($item['currency_code'] ?? 'SDG'); ?></td>
                            <td><?php echo e($item['status']); ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="<?php echo APP_URL; ?>modules/sponsorships/monthly_agreements.php?sponsorship_id=<?php echo (int) $item['id']; ?>&month=<?php echo e($monthInput); ?>&q=<?php echo rawurlencode($search); ?>">إدارة الاتفاق</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-muted mt-3">لا توجد نتائج مطابقة. أدخل معيار بحث لعرض الكفالات.</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($selected): ?>
<div class="card mb-4 fade-in">
    <div class="card-header"><i class="fas fa-calendar-check me-2"></i>الاتفاقية: <?php echo e($selected['sponsorship_code']); ?> — <?php echo e($monthInput); ?></div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4"><small class="text-muted">الكفيل</small><div><strong><?php echo e($selected['sponsor_name']); ?></strong></div></div>
            <div class="col-md-4"><small class="text-muted">اليتيم</small><div><strong><?php echo e($selected['child_name'] ?? '—'); ?></strong></div></div>
            <div class="col-md-4"><small class="text-muted">المبلغ المسجل في سجل الكفالة (مرجع قديم فقط)</small><div><strong><?php echo number_format((float) $selected['monthly_amount'], 2); ?> <?php echo e($selected['currency_code'] ?? 'SDG'); ?></strong></div></div>
        </div>

        <?php $frozen = (int) ($allocation['allocation_count'] ?? 0) > 0; ?>
        <?php if ($frozen): ?>
            <div class="alert alert-warning">تم ترحيل تخصيص لهذا الشهر. التزام الكفالة مجمد عند <?php echo number_format((float) ($allocation['frozen_commitment'] ?? 0), 2); ?>. يمكن إصدار اتفاق جديد لرسوم الخدمة فقط مع الإبقاء على هذا الالتزام.</div>
        <?php else: ?>
            <div class="alert alert-info">لا يوجد تخصيص مرحّل لهذا الشهر. يمكن أن يختلف التزام هذا الشهر عن الأشهر السابقة وفق الاتفاق الجديد.</div>
        <?php endif; ?>

        <form method="post" class="border rounded p-3">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="sponsorship_id" value="<?php echo $selectedId; ?>">
            <input type="hidden" name="covered_month" value="<?php echo e($monthInput); ?>">
            <input type="hidden" name="q" value="<?php echo e($search); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">قيمة الكفالة الشهرية المتفق عليها *</label>
                    <input type="number" name="agreed_sponsorship_amount" class="form-control" min="0.01" step="0.01" required
                           value="<?php echo e($frozen ? number_format((float) ($allocation['frozen_commitment'] ?? 0), 2, '.', '') : ($latest ? number_format((float) $latest['agreed_sponsorship_amount'], 2, '.', '') : '')); ?>"
                           <?php echo $frozen ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-6">
                    <label class="form-label">رسوم الخدمة الشهرية المتفق عليها *</label>
                    <input type="number" name="agreed_service_fee_amount" class="form-control" min="0" step="0.01" required
                           value="<?php echo $latest ? e(number_format((float) $latest['agreed_service_fee_amount'], 2, '.', '')) : ''; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">مرجع الاجتماع / القرار</label>
                    <input type="text" name="meeting_reference" class="form-control" maxlength="255"
                           value="<?php echo e($latest['meeting_reference'] ?? ''); ?>" placeholder="مرجع اختياري حتى اعتماد صيغة موحدة">
                </div>
                <div class="col-md-6">
                    <label class="form-label">سبب التعديل <?php echo $latest ? '*' : ''; ?></label>
                    <input type="text" name="amendment_reason" class="form-control" maxlength="4000"
                           value="" <?php echo $latest ? 'required' : ''; ?>
                           placeholder="<?php echo $latest ? 'اشرح سبب إصدار الاتفاقية الجديدة' : 'لا يلزم للإصدار الأول'; ?>">
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?php echo $latest ? 'حفظ إصدار تعديل جديد' : 'حفظ الاتفاقية الأولى'; ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4 fade-in">
    <div class="card-header"><i class="fas fa-history me-2"></i>سجل إصدارات الاتفاقية لهذا الشهر</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>الإصدار</th><th>قيمة الكفالة</th><th>رسوم الخدمة</th><th>مرجع الاجتماع</th><th>سبب التعديل</th><th>أُدخل بواسطة</th><th>التاريخ</th></tr></thead>
                <tbody>
                <?php if (!$history): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">لا توجد اتفاقية مسجلة لهذا الشهر بعد.</td></tr>
                <?php else: foreach ($history as $version): ?>
                    <tr>
                        <td><?php echo (int) $version['version_no']; ?></td>
                        <td><?php echo number_format((float) $version['agreed_sponsorship_amount'], 2); ?></td>
                        <td><?php echo number_format((float) $version['agreed_service_fee_amount'], 2); ?></td>
                        <td><?php echo e($version['meeting_reference'] ?? '—'); ?></td>
                        <td><?php echo e($version['amendment_reason'] ?? '—'); ?></td>
                        <td><?php echo e($version['creator_name']); ?></td>
                        <td><?php echo e($version['created_at']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($selectedId > 0): ?>
    <div class="alert alert-warning">الكفالة المحددة غير موجودة أو ليست نشطة/موقوفة.</div>
<?php endif; ?>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
