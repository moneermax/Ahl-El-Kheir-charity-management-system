<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();
$role = (string)Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($role, ['financial_manager', 'admin'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit;
}

$pdo = db();
$message = '';
$error = '';
$types = [
    'collection' => 'حوافز تحصيل الكفالات والتبرعات',
    'new_sponsor' => 'حافز استقطاب كفيل جديد',
    'reactivation' => 'حافز استعادة كفيل',
    'project_completion' => 'حافز إتمام المشروع',
];

function hrIncentiveCurrency(string $value): string
{
    $currency = strtoupper(trim($value));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        throw new InvalidArgumentException('يجب إدخال رمز عملة مكوّن من ثلاثة أحرف لاتينية.');
    }
    return $currency;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة وحاول مرة أخرى.');
        }

        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create_policy') {
            $name = trim((string)($_POST['policy_name'] ?? ''));
            $effectiveFrom = trim((string)($_POST['effective_from'] ?? ''));
            $agreementAt = trim((string)($_POST['agreement_recorded_at'] ?? ''));
            $agreementNotes = trim((string)($_POST['agreement_notes'] ?? ''));
            if ($name === '' || mb_strlen($name) > 150) {
                throw new InvalidArgumentException('اسم السياسة مطلوب وبحد أقصى 150 حرفاً.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveFrom);
            if (!$date || $date->format('Y-m-d') !== $effectiveFrom || $effectiveFrom < date('Y-m-d')) {
                throw new InvalidArgumentException('تاريخ السريان يجب أن يكون اليوم أو تاريخاً مستقبلياً؛ لا يمكن إنشاء سياسة بأثر رجعي.');
            }
            $agreementDate = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $agreementAt);
            if (!$agreementDate || $agreementNotes === '') {
                throw new InvalidArgumentException('سجّل تاريخ/وقت اتفاق GM وFM وملاحظات الاتفاق قبل إنشاء السياسة.');
            }

            $rules = [];
            foreach ($types as $type => $label) {
                $enabled = isset($_POST['enabled'][$type]) ? 1 : 0;
                $amountRaw = trim((string)($_POST['amount'][$type] ?? ''));
                $amount = filter_var($amountRaw, FILTER_VALIDATE_FLOAT);
                $currency = hrIncentiveCurrency((string)($_POST['currency'][$type] ?? ''));
                if ($amount === false || !is_finite((float)$amount) || (float)$amount < 0 || (float)$amount > 9999999999.99) {
                    throw new InvalidArgumentException('قيمة الحافز غير صالحة: ' . $label);
                }
                if ($enabled && (float)$amount <= 0) {
                    throw new InvalidArgumentException('قيمة الحافز المفعّل يجب أن تكون أكبر من صفر: ' . $label);
                }
                $rules[$type] = ['enabled' => $enabled, 'amount' => round((float)$amount, 2), 'currency' => $currency];
            }
            if (!array_filter($rules, static fn(array $rule): bool => $rule['enabled'] === 1)) {
                throw new InvalidArgumentException('فعّل نوع حافز واحداً على الأقل قبل حفظ السياسة.');
            }

            $pdo->beginTransaction();
            $next = dbFetchOne('SELECT COALESCE(MAX(version_no), 0) + 1 AS next_version FROM hr_incentive_policy_versions FOR UPDATE');
            $version = (int)($next['next_version'] ?? 1);
            $stmt = $pdo->prepare(
                "INSERT INTO hr_incentive_policy_versions
                 (version_no, policy_name, status, effective_from, agreement_recorded_at, agreement_notes, created_by_user_id)
                 VALUES (?, ?, 'draft', ?, ?, ?, ?)"
            );
            $stmt->execute([$version, $name, $effectiveFrom, $agreementDate->format('Y-m-d H:i:s'), $agreementNotes, (int)Session::getUserID()]);
            $policyId = (int)$pdo->lastInsertId();
            $ruleStmt = $pdo->prepare(
                'INSERT INTO hr_incentive_policy_rules (policy_version_id, incentive_type, reward_amount, currency_code, is_enabled)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($rules as $type => $rule) {
                $ruleStmt->execute([$policyId, $type, $rule['amount'], $rule['currency'], $rule['enabled']]);
            }
            $pdo->commit();
            flash('success', 'تم حفظ مسودة سياسة الحوافز V' . $version . '. يجب مراجعة تفاصيلها ثم تفعيلها من خلال إجراء التفعيل.');
            header('Location: ' . APP_URL . 'modules/hr/incentive_policy.php');
            exit;
        }

        if ($action === 'activate_policy') {
            $policyId = (int)($_POST['policy_id'] ?? 0);
            $pdo->beginTransaction();
            $policy = dbFetchOne('SELECT * FROM hr_incentive_policy_versions WHERE id = ? FOR UPDATE', [$policyId]);
            if (!$policy || $policy['status'] !== 'draft') {
                throw new InvalidArgumentException('المسودة غير موجودة أو لم تعد قابلة للتفعيل.');
            }
            if ((string)$policy['effective_from'] < date('Y-m-d')) {
                throw new InvalidArgumentException('لا يمكن تفعيل سياسة بأثر رجعي.');
            }
            if (trim((string)$policy['agreement_notes']) === '' || empty($policy['agreement_recorded_at'])) {
                throw new InvalidArgumentException('لا توجد بيانات موثقة لاتفاق GM وFM.');
            }
            $ruleRows = dbFetchAll('SELECT incentive_type, is_enabled, reward_amount FROM hr_incentive_policy_rules WHERE policy_version_id = ? FOR UPDATE', [$policyId]);
            if (count($ruleRows) !== count($types)) {
                throw new InvalidArgumentException('السياسة لا تحتوي على جميع أنواع الحوافز الأربعة.');
            }
            $enabledCount = 0;
            foreach ($ruleRows as $rule) {
                if ((int)$rule['is_enabled'] === 1) {
                    if ((float)$rule['reward_amount'] <= 0) {
                        throw new InvalidArgumentException('لا يمكن تفعيل حافز مفعّل بقيمة صفر.');
                    }
                    $enabledCount++;
                }
            }
            if ($enabledCount === 0) {
                throw new InvalidArgumentException('يجب تفعيل نوع حافز واحد على الأقل.');
            }

            $effectiveFrom = (string)$policy['effective_from'];
            $laterActive = dbFetchOne(
                "SELECT id, effective_from FROM hr_incentive_policy_versions
                 WHERE status = 'active' AND effective_from >= ?
                 ORDER BY effective_from ASC LIMIT 1 FOR UPDATE",
                [$effectiveFrom]
            );
            if ($laterActive) {
                throw new InvalidArgumentException(
                    'توجد سياسة مفعّلة بتاريخ سريان مساوٍ أو لاحق. فعّل الإصدارات بترتيب تاريخ السريان لتجنّب تداخل السياسات.'
                );
            }
            $prior = dbFetchAll(
                "SELECT id, effective_from FROM hr_incentive_policy_versions
                 WHERE status = 'active' AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)
                 FOR UPDATE",
                [$effectiveFrom, $effectiveFrom]
            );
            $closeDate = (new DateTimeImmutable($effectiveFrom))->modify('-1 day')->format('Y-m-d');
            foreach ($prior as $old) {
                $pdo->prepare("UPDATE hr_incentive_policy_versions SET status='retired', effective_to=? WHERE id=?")
                    ->execute([$closeDate, (int)$old['id']]);
            }
            $pdo->prepare(
                "UPDATE hr_incentive_policy_versions SET status='active', activated_by_user_id=?, activated_at=NOW()
                 WHERE id=? AND status='draft'"
            )->execute([(int)Session::getUserID(), $policyId]);
            $pdo->commit();
            flash('success', 'تم تفعيل سياسة الحوافز V' . (int)$policy['version_no'] . ' وفق تاريخ السريان المسجل، دون اعتماد GM ثانٍ.');
            header('Location: ' . APP_URL . 'modules/hr/incentive_policy.php');
            exit;
        }

        throw new InvalidArgumentException('الإجراء المطلوب غير معروف.');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $error = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
        ? $e->getMessage()
        : 'تعذر حفظ سياسة الحوافز. راجع سجل التطبيق قبل إعادة المحاولة.';
}

foreach (get_flashes() as $flashMessage) {
    if (($flashMessage['type'] ?? '') === 'success') $message = (string)($flashMessage['message'] ?? '');
    if (($flashMessage['type'] ?? '') === 'error') $error = (string)($flashMessage['message'] ?? '');
}

$policies = dbFetchAll(
    "SELECT p.*, u.full_name AS created_by_name, au.full_name AS activated_by_name,
            (SELECT COUNT(*) FROM hr_incentive_policy_rules r WHERE r.policy_version_id=p.id AND r.is_enabled=1) AS enabled_rules
     FROM hr_incentive_policy_versions p
     LEFT JOIN users u ON u.id=p.created_by_user_id
     LEFT JOIN users au ON au.id=p.activated_by_user_id
     ORDER BY p.version_no DESC"
);
$ruleRows = dbFetchAll(
    'SELECT r.*, p.version_no FROM hr_incentive_policy_rules r
     JOIN hr_incentive_policy_versions p ON p.id=r.policy_version_id
     ORDER BY p.version_no DESC, r.id'
);
$rulesByPolicy = [];
foreach ($ruleRows as $ruleRow) $rulesByPolicy[(int)$ruleRow['policy_version_id']][] = $ruleRow;

$pageTitle = 'سياسة حوافز الموظفين';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-3" style="max-width:1320px">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1"><i class="fas fa-award text-primary me-2"></i>سياسة حوافز الموظفين</h1>
            <p class="text-muted mb-0 small">يُسجّل FM اتفاق GM وFM ثم ينشئ السياسة ويفعّلها؛ لا توجد موافقة GM ثانية على السياسة.</p>
        </div>
    </div>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">إنشاء إصدار سياسة جديد</div>
        <div class="card-body">
            <form method="post">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="create_policy">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">اسم السياسة *</label>
                        <input class="form-control" name="policy_name" maxlength="150" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">تاريخ السريان *</label>
                        <input class="form-control" type="date" name="effective_from" min="<?=e(date('Y-m-d'))?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">تاريخ ووقت اتفاق GM وFM *</label>
                        <input class="form-control" type="datetime-local" name="agreement_recorded_at" max="<?=e(date('Y-m-d\\TH:i'))?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">ملخص الاتفاق / محضر النقاش *</label>
                        <textarea class="form-control" name="agreement_notes" rows="2" maxlength="2000" required></textarea>
                    </div>
                </div>
                <hr>
                <p class="small text-muted">لا توجد مبالغ افتراضية. أدخل المبلغ والعملة لكل نوع، وحدد الأنواع التي يشملها الإصدار. المبلغ ثابت وليس نسبة من التحصيل.</p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light"><tr><th>نوع الحافز</th><th>مفعّل؟</th><th>المبلغ</th><th>العملة (3 أحرف)</th></tr></thead>
                        <tbody>
                        <?php foreach ($types as $type => $label): ?>
                            <tr>
                                <td><?=e($label)?></td>
                                <td class="text-center"><input class="form-check-input" type="checkbox" name="enabled[<?=e($type)?>]" value="1"></td>
                                <td><input class="form-control" type="number" name="amount[<?=e($type)?>]" min="0" max="9999999999.99" step="0.01" value="0" required></td>
                                <td><input class="form-control text-uppercase" name="currency[<?=e($type)?>]" minlength="3" maxlength="3" pattern="[A-Za-z]{3}" required></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>حفظ المسودة</button>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold">إصدارات السياسات</div>
        <div class="card-body">
            <?php if (!$policies): ?>
                <div class="text-muted text-center py-4">لا توجد إصدارات سياسة حتى الآن.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light"><tr><th>الإصدار</th><th>الاسم</th><th>الحالة</th><th>السريان</th><th>أنواع مفعّلة</th><th>الاتفاق</th><th>الإجراء</th></tr></thead>
                        <tbody>
                        <?php foreach ($policies as $policy): ?>
                            <tr>
                                <td>V<?= (int)$policy['version_no'] ?></td>
                                <td><?=e($policy['policy_name'])?></td>
                                <td><?=e(['draft'=>'مسودة','active'=>'سارية','retired'=>'منتهية/مستبدلة'][$policy['status']] ?? $policy['status'])?></td>
                                <td><?=e($policy['effective_from'])?><?= $policy['effective_to'] ? ' — ' . e($policy['effective_to']) : '' ?></td>
                                <td><?= (int)$policy['enabled_rules'] ?> / 4</td>
                                <td><small><?=e($policy['agreement_recorded_at'] ?? '—')?></small><br><small class="text-muted"><?=e($policy['created_by_name'] ?? '')?></small></td>
                                <td>
                                    <?php if ($policy['status'] === 'draft'): ?>
                                        <form method="post" onsubmit="return confirm('تفعيل هذه السياسة وفق تاريخ السريان المسجل؟');">
                                            <?=csrf_field()?>
                                            <input type="hidden" name="action" value="activate_policy">
                                            <input type="hidden" name="policy_id" value="<?= (int)$policy['id'] ?>">
                                            <button class="btn btn-sm btn-success" type="submit">تفعيل السياسة</button>
                                        </form>
                                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                            </tr>
                            <tr class="table-light"><td colspan="7">
                                <div class="d-flex flex-wrap gap-3 small">
                                <?php foreach (($rulesByPolicy[(int)$policy['id']] ?? []) as $rule): ?>
                                    <span><strong><?=e($types[$rule['incentive_type']] ?? $rule['incentive_type'])?>:</strong>
                                    <?= (int)$rule['is_enabled'] === 1 ? number_format((float)$rule['reward_amount'],2) . ' ' . e($rule['currency_code']) : 'غير مفعّل' ?></span>
                                <?php endforeach; ?>
                                </div>
                                <?php if (!empty($policy['agreement_notes'])): ?><div class="small text-muted mt-2"><?=nl2br(e($policy['agreement_notes']))?></div><?php endif; ?>
                            </td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
