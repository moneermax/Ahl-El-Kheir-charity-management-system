<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();
$role = (string)Session::getUserRole();
$canManagePolicies = in_array($role, ['financial_manager', 'admin', 'hr_manager', 'hr_staff'], true);
$canActivatePolicies = in_array($role, ['financial_manager', 'admin'], true);
$canManageTypes = in_array($role, ['financial_manager', 'admin', 'hr_manager', 'hr_staff'], true);
if (!Session::isLoggedIn() || !$canManagePolicies) {
    header('Location: ' . APP_URL . 'index.php');
    exit;
}

$pdo = db();
$message = '';
$error = '';
$fieldTypes = [
    'short_text' => 'نص قصير',
    'long_text' => 'نص طويل',
    'number' => 'رقم',
    'date' => 'تاريخ',
    'boolean' => 'نعم / لا',
    'select' => 'قائمة اختيار',
];

function hrIncentiveValidateCustomValue(string $type, string $value, array $options, bool $required): ?string
{
    $value = trim($value);
    if ($value === '') {
        if ($required) {
            throw new InvalidArgumentException('قيمة الحقل مطلوبة.');
        }
        return null;
    }

    switch ($type) {
        case 'short_text':
            if (mb_strlen($value) > 500) throw new InvalidArgumentException('النص القصير يجب ألا يتجاوز 500 حرف.');
            break;
        case 'long_text':
            if (mb_strlen($value) > 5000) throw new InvalidArgumentException('النص الطويل يجب ألا يتجاوز 5000 حرف.');
            break;
        case 'number':
            $number = filter_var($value, FILTER_VALIDATE_FLOAT);
            if ($number === false || !is_finite((float)$number)) throw new InvalidArgumentException('أدخل قيمة رقمية صحيحة للحقل.');
            break;
        case 'date':
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('أدخل تاريخاً صحيحاً للحقل.');
            break;
        case 'boolean':
            if (!in_array($value, ['0', '1'], true)) throw new InvalidArgumentException('اختر نعم أو لا للحقل.');
            break;
        case 'select':
            if (!in_array($value, $options, true)) throw new InvalidArgumentException('اختر قيمة موجودة في قائمة الحقل.');
            break;
        default:
            throw new InvalidArgumentException('نوع الحقل غير مدعوم.');
    }

    return $value;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة وحاول مرة أخرى.');
        }

        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create_category') {
            if (!$canManageTypes) throw new RuntimeException('ليس لديك صلاحية إدارة أنواع الحوافز.');
            $nameAr = trim((string)($_POST['name_ar'] ?? ''));
            $nameEn = trim((string)($_POST['name_en'] ?? ''));
            if ($nameAr === '' || mb_strlen($nameAr) > 190 || $nameEn === '' || mb_strlen($nameEn) > 190) {
                throw new InvalidArgumentException('اسم نوع الحافز بالعربية والإنجليزية مطلوب وبحد أقصى 190 حرفاً لكل اسم.');
            }

            // The code is generated server-side from the English name; posted client values are ignored.
            $baseCode = strtolower($nameEn);
            $baseCode = preg_replace('/[^a-z0-9]+/', '_', $baseCode) ?? '';
            $baseCode = trim($baseCode, '_');
            if ($baseCode === '') {
                $baseCode = 'incentive_category';
            } elseif (!preg_match('/^[a-z]/', $baseCode)) {
                $baseCode = 'incentive_' . $baseCode;
            }
            $baseCode = trim(substr($baseCode, 0, 80), '_');
            if (strlen($baseCode) < 2) {
                $baseCode = 'incentive_' . ($baseCode !== '' ? $baseCode : 'category');
            }
            $baseCode = substr($baseCode, 0, 80);

            $code = $baseCode;
            $suffix = 2;
            while (dbFetchOne('SELECT id FROM hr_incentive_types WHERE type_code = ? LIMIT 1', [$code])) {
                $suffixText = '_' . $suffix;
                $code = rtrim(substr($baseCode, 0, 80 - strlen($suffixText)), '_') . $suffixText;
                $suffix++;
            }
            $stmt = $pdo->prepare(
                "INSERT INTO hr_incentive_types
                    (type_code, name_ar, name_en, qualification_mode, is_system, is_active, sort_order, created_by_user_id)
                 VALUES (?, ?, ?, 'manual_review', 0, 1, 100, ?)"
            );
            $stmt->execute([$code, $nameAr, $nameEn, (int)Session::getUserID()]);
            flash('success', 'تمت إضافة نوع الحافز. النوع الجديد مخصص للمراجعة اليدوية ولا ينشئ مكافآت تلقائياً.');
            header('Location: ' . APP_URL . 'modules/hr/incentive_policy.php');
            exit;
        }

        if ($action === 'create_policy') {
            if (!$canManagePolicies) throw new RuntimeException('ليس لديك صلاحية إنشاء مسودة سياسة.');
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
            $agreementDateErrors = DateTimeImmutable::getLastErrors();
            if (
                !$agreementDate
                || $agreementDate->format('Y-m-d\\TH:i') !== $agreementAt
                || ($agreementDateErrors !== false
                    && ($agreementDateErrors['warning_count'] > 0 || $agreementDateErrors['error_count'] > 0))
                || $agreementDate > new DateTimeImmutable()
                || $agreementNotes === ''
                || mb_strlen($agreementNotes) > 2000
            ) {
                throw new InvalidArgumentException('تحقق من صحة تاريخ ووقت اتفاق GM وFM، وأنه ليس في المستقبل، وأدخل ملاحظات لا تتجاوز 2000 حرف.');
            }

            $types = dbFetchAll('SELECT type_code, name_ar, qualification_mode FROM hr_incentive_types WHERE is_active = 1 ORDER BY sort_order, id');
            if (!$types) throw new RuntimeException('لا توجد أنواع حوافز نشطة لإضافتها إلى السياسة.');
            $rules = [];
            foreach ($types as $typeRow) {
                $typeCode = (string)$typeRow['type_code'];
                $enabled = isset($_POST['enabled'][$typeCode]) ? 1 : 0;
                $amountRaw = trim((string)($_POST['amount'][$typeCode] ?? ''));
                $amount = filter_var($amountRaw, FILTER_VALIDATE_FLOAT);
                if ($amount === false || !is_finite((float)$amount) || (float)$amount < 0 || (float)$amount > 9999999999.99) {
                    throw new InvalidArgumentException('قيمة الحافز غير صالحة: ' . (string)$typeRow['name_ar']);
                }
                if ($enabled && (float)$amount <= 0) {
                    throw new InvalidArgumentException('قيمة الحافز المفعّل يجب أن تكون أكبر من صفر: ' . (string)$typeRow['name_ar']);
                }
                $rules[$typeCode] = ['enabled' => $enabled, 'amount' => round((float)$amount, 2), 'currency' => APP_CURRENCY_CODE];
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
            foreach ($rules as $typeCode => $rule) {
                $ruleStmt->execute([$policyId, $typeCode, $rule['amount'], $rule['currency'], $rule['enabled']]);
            }
            $pdo->commit();
            flash('success', 'تم حفظ مسودة سياسة الحوافز V' . $version . '. يمكن إضافة حقول مخصصة للمسودة، ويظل التفعيل من اختصاص FM.');
            header('Location: ' . APP_URL . 'modules/hr/incentive_policy.php');
            exit;
        }

        if ($action === 'add_custom_field') {
            $policyId = (int)($_POST['policy_id'] ?? 0);
            $policy = dbFetchOne('SELECT id, status FROM hr_incentive_policy_versions WHERE id = ? LIMIT 1', [$policyId]);
            if (!$policy || $policy['status'] !== 'draft') {
                throw new InvalidArgumentException('يمكن إضافة الحقول إلى مسودة سياسة فقط.');
            }
            $labelAr = trim((string)($_POST['label_ar'] ?? ''));
            $labelEn = trim((string)($_POST['label_en'] ?? ''));
            $type = (string)($_POST['field_type'] ?? '');
            $required = isset($_POST['is_required']) ? 1 : 0;
            $rawValue = (string)($_POST['field_value'] ?? '');
            $optionsRaw = trim((string)($_POST['options'] ?? ''));
            if ($labelAr === '' || mb_strlen($labelAr) > 190 || ($labelEn !== '' && mb_strlen($labelEn) > 190)) {
                throw new InvalidArgumentException('اسم الحقل بالعربية مطلوب؛ والاسم الإنجليزي اختياري، وبحد أقصى 190 حرفاً.');
            }
            if (!isset($fieldTypes[$type])) throw new InvalidArgumentException('اختر نوعاً مدعوماً للحقل.');
            $options = [];
            if ($type === 'select') {
                $options = array_values(array_unique(array_filter(array_map('trim', preg_split('/\\R/u', $optionsRaw) ?: []), static fn(string $v): bool => $v !== '')));
                if (count($options) < 2 || count($options) > 50) {
                    throw new InvalidArgumentException('قائمة الاختيار يجب أن تحتوي على 2 إلى 50 خياراً، خيار واحد في كل سطر.');
                }
                foreach ($options as $option) {
                    if (mb_strlen($option) > 190) throw new InvalidArgumentException('كل خيار يجب ألا يتجاوز 190 حرفاً.');
                }
            }
            $value = hrIncentiveValidateCustomValue($type, $rawValue, $options, $required === 1);
            if ($type !== 'select' && $optionsRaw !== '') {
                throw new InvalidArgumentException('قائمة الخيارات تستخدم مع نوع قائمة الاختيار فقط.');
            }

            $key = 'custom_' . bin2hex(random_bytes(8));
            $sort = (int)(dbFetchOne(
                'SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_order FROM hr_incentive_policy_custom_fields WHERE policy_version_id = ?',
                [$policyId]
            )['next_order'] ?? 10);
            $stmt = $pdo->prepare(
                'INSERT INTO hr_incentive_policy_custom_fields
                    (policy_version_id, field_key, label_ar, label_en, field_type, is_required, options_json, field_value, sort_order, created_by_user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $policyId, $key, $labelAr, ($labelEn !== '' ? $labelEn : null), $type, $required,
                ($type === 'select' ? json_encode($options, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null),
                $value, $sort, (int)Session::getUserID()
            ]);
            flash('success', 'تمت إضافة الحقل إلى مسودة السياسة. سيبقى ضمن سجل إصدار السياسة ولن يغيّر الإصدارات السابقة.');
            header('Location: ' . APP_URL . 'modules/hr/incentive_policy.php');
            exit;
        }

        if ($action === 'activate_policy') {
            if (!$canActivatePolicies) throw new RuntimeException('تفعيل سياسة الحوافز من اختصاص المدير المالي فقط.');
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
            $ruleRows = dbFetchAll(
                'SELECT r.incentive_type, r.is_enabled, r.reward_amount, t.is_active, t.qualification_mode
                 FROM hr_incentive_policy_rules r
                 JOIN hr_incentive_types t ON t.type_code = r.incentive_type
                 WHERE r.policy_version_id = ? FOR UPDATE',
                [$policyId]
            );
            if (!$ruleRows) throw new InvalidArgumentException('السياسة لا تحتوي على أي أنواع حوافز.');
            $enabledCount = 0;
            foreach ($ruleRows as $rule) {
                if ((int)$rule['is_enabled'] === 1) {
                    if ((float)$rule['reward_amount'] <= 0) {
                        throw new InvalidArgumentException('لا يمكن تفعيل حافز مفعّل بقيمة صفر.');
                    }
                    if ((int)$rule['is_active'] !== 1) {
                        throw new InvalidArgumentException('توجد فئة مفعّلة في السياسة لكنها غير نشطة في سجل الفئات.');
                    }
                    $enabledCount++;
                }
            }
            if ($enabledCount === 0) {
                throw new InvalidArgumentException('يجب تفعيل نوع حافز واحد على الأقل.');
            }

            $requiredFields = dbFetchAll(
                "SELECT label_ar FROM hr_incentive_policy_custom_fields
                 WHERE policy_version_id = ? AND is_required = 1 AND (field_value IS NULL OR TRIM(field_value) = '')",
                [$policyId]
            );
            if ($requiredFields) throw new InvalidArgumentException('أكمل الحقول الإضافية المطلوبة قبل تفعيل السياسة: ' . implode('، ', array_column($requiredFields, 'label_ar')));

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
$allTypes = dbFetchAll('SELECT * FROM hr_incentive_types ORDER BY sort_order, id');
$activeTypes = array_values(array_filter($allTypes, static fn(array $t): bool => (int)$t['is_active'] === 1));
$typeNames = [];
foreach ($allTypes as $typeRow) $typeNames[(string)$typeRow['type_code']] = (string)$typeRow['name_ar'];
$ruleRows = dbFetchAll(
    'SELECT r.*, p.version_no FROM hr_incentive_policy_rules r
     JOIN hr_incentive_policy_versions p ON p.id=r.policy_version_id
     ORDER BY p.version_no DESC, r.id'
);
$rulesByPolicy = [];
foreach ($ruleRows as $ruleRow) $rulesByPolicy[(int)$ruleRow['policy_version_id']][] = $ruleRow;
$customFieldRows = dbFetchAll(
    'SELECT f.*, p.version_no FROM hr_incentive_policy_custom_fields f
     JOIN hr_incentive_policy_versions p ON p.id=f.policy_version_id
     ORDER BY p.version_no DESC, f.sort_order, f.id'
);
$fieldsByPolicy = [];
foreach ($customFieldRows as $fieldRow) $fieldsByPolicy[(int)$fieldRow['policy_version_id']][] = $fieldRow;

$pageTitle = 'سياسة حوافز الموظفين';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-3" style="max-width:1320px">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1"><i class="fas fa-award text-primary me-2"></i>سياسة حوافز الموظفين</h1>
            <p class="text-muted mb-0 small">يسجّل HR أو FM مسودة السياسة بعد اتفاق GM وFM؛ التفعيل من اختصاص FM ولا يتطلب اعتماد GM ثانياً.</p>
        </div>
    </div>
    <?php if ($message !== ''): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>

    <div class="alert alert-info small">
        العملة المعتمدة على مستوى النظام هي <?=e(APP_CURRENCY_CODE)?> (<?=e(APP_CURRENCY_NAME_AR)?>).
        الفئات الأربع الأساسية مرتبطة بأحداث النظام الحالية. أي فئة جديدة تُضاف هنا بوضع «مراجعة يدوية» ولا تصدر مكافآت تلقائياً؛ مسار الترشيح والصرف للفئات اليدوية يحتاج تنفيذاً مستقلاً قبل استخدامها مالياً.
    </div>

    <?php if ($canManageTypes): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">إدارة فئات الحوافز</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="create_category">
                <div class="col-md-3">
                    <label class="form-label">الاسم بالعربية *</label>
                    <input class="form-control" name="name_ar" maxlength="190" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الاسم بالإنجليزية *</label>
                    <input class="form-control" id="incentive-type-name-en" name="name_en" maxlength="190" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">رمز الفئة (تلقائي)</label>
                    <input class="form-control font-monospace" id="incentive-type-code-preview" type="text" value="incentive_category" readonly aria-describedby="incentive-type-code-help">
                    <div class="form-text" id="incentive-type-code-help">يُولّد الرمز من الاسم الإنجليزي تلقائياً. إذا كان مستخدماً، سيضيف النظام رقماً تلقائياً.</div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-plus me-1"></i>إضافة فئة يدوية</button>
                </div>
            </form>
            <hr>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light"><tr><th>الرمز</th><th>الفئة</th><th>آلية الاستحقاق</th><th>الحالة</th></tr></thead>
                    <tbody>
                    <?php foreach ($allTypes as $typeRow): ?>
                        <tr>
                            <td><code><?=e($typeRow['type_code'])?></code></td>
                            <td><?=e($typeRow['name_ar'])?> <span class="text-muted small">/ <?=e($typeRow['name_en'])?></span></td>
                            <td><?= $typeRow['qualification_mode'] === 'source_event' ? 'حدث موثّق بالنظام' : 'مراجعة يدوية — لا إصدار تلقائي' ?></td>
                            <td><?= (int)$typeRow['is_active'] === 1 ? 'نشطة' : 'غير نشطة' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                <p class="small text-muted">حدّد الفئات المشمولة في هذا الإصدار وأدخل المبالغ الثابتة. الفئات التي ستُضاف لاحقاً لا تُدرج تلقائياً في الإصدارات السابقة.</p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light"><tr><th>فئة الحافز</th><th>آلية الاستحقاق</th><th>مفعّل؟</th><th>المبلغ (<?=e(APP_CURRENCY_CODE)?>)</th></tr></thead>
                        <tbody>
                        <?php foreach ($activeTypes as $typeRow): $typeCode = (string)$typeRow['type_code']; ?>
                            <tr>
                                <td><?=e($typeRow['name_ar'])?><br><code class="small"><?=e($typeCode)?></code></td>
                                <td><?= $typeRow['qualification_mode'] === 'source_event' ? 'حدث موثّق بالنظام' : '<span class="text-warning">مراجعة يدوية — لا إصدار تلقائي</span>' ?></td>
                                <td class="text-center"><input class="form-check-input" type="checkbox" name="enabled[<?=e($typeCode)?>]" value="1"></td>
                                <td><input class="form-control" type="number" name="amount[<?=e($typeCode)?>]" min="0" max="9999999999.99" step="0.01" value="0" required></td>
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
                        <thead class="table-light"><tr><th>الإصدار</th><th>الاسم</th><th>الحالة</th><th>السريان</th><th>فئات مفعّلة</th><th>الاتفاق</th><th>الإجراء</th></tr></thead>
                        <tbody>
                        <?php foreach ($policies as $policy): $policyId = (int)$policy['id']; ?>
                            <tr>
                                <td>V<?= (int)$policy['version_no'] ?></td>
                                <td><?=e($policy['policy_name'])?></td>
                                <td><?=e(['draft'=>'مسودة','active'=>'سارية','retired'=>'منتهية/مستبدلة'][$policy['status']] ?? $policy['status'])?></td>
                                <td><?=e($policy['effective_from'])?><?= $policy['effective_to'] ? ' — ' . e($policy['effective_to']) : '' ?></td>
                                <td><?= (int)$policy['enabled_rules'] ?></td>
                                <td><small><?=e($policy['agreement_recorded_at'] ?? '—')?></small><br><small class="text-muted"><?=e($policy['created_by_name'] ?? '')?></small></td>
                                <td>
                                    <?php if ($policy['status'] === 'draft' && $canActivatePolicies): ?>
                                        <form method="post" onsubmit="return confirm('تفعيل هذه السياسة وفق تاريخ السريان المسجل؟');">
                                            <?=csrf_field()?>
                                            <input type="hidden" name="action" value="activate_policy">
                                            <input type="hidden" name="policy_id" value="<?=$policyId?>">
                                            <button class="btn btn-sm btn-success" type="submit">تفعيل السياسة</button>
                                        </form>
                                    <?php elseif ($policy['status'] === 'draft'): ?>
                                        <span class="text-muted small">بانتظار تفعيل FM</span>
                                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                            </tr>
                            <tr class="table-light"><td colspan="7">
                                <div class="d-flex flex-wrap gap-3 small">
                                <?php foreach (($rulesByPolicy[$policyId] ?? []) as $rule): ?>
                                    <span><strong><?=e($typeNames[$rule['incentive_type']] ?? $rule['incentive_type'])?>:</strong>
                                    <?= (int)$rule['is_enabled'] === 1 ? number_format((float)$rule['reward_amount'],2) . ' ' . e($rule['currency_code']) : 'غير مفعّل' ?></span>
                                <?php endforeach; ?>
                                </div>
                                <?php if (!empty($policy['agreement_notes'])): ?><div class="small text-muted mt-2"><?=nl2br(e($policy['agreement_notes']))?></div><?php endif; ?>

                                <?php if (!empty($fieldsByPolicy[$policyId])): ?>
                                    <div class="mt-3">
                                        <strong class="small">الحقول الإضافية لهذا الإصدار</strong>
                                        <div class="d-flex flex-column gap-1 mt-1">
                                        <?php foreach ($fieldsByPolicy[$policyId] as $field): ?>
                                            <div class="small">
                                                <strong><?=e($field['label_ar'])?><?= (int)$field['is_required'] === 1 ? ' *' : '' ?>:</strong>
                                                <?php
                                                $displayValue = (string)($field['field_value'] ?? '');
                                                if ($field['field_type'] === 'boolean' && $displayValue !== '') $displayValue = $displayValue === '1' ? 'نعم' : 'لا';
                                                ?>
                                                <?= $displayValue !== '' ? nl2br(e($displayValue)) : '<span class="text-muted">—</span>' ?>
                                                <span class="text-muted">(<?=e($fieldTypes[$field['field_type']] ?? $field['field_type'])?>)</span>
                                            </div>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($policy['status'] === 'draft' && $canManagePolicies): ?>
                                    <details class="mt-3">
                                        <summary class="small fw-bold">إضافة حقل مخصص إلى هذه المسودة</summary>
                                        <form method="post" class="row g-2 mt-2">
                                            <?=csrf_field()?>
                                            <input type="hidden" name="action" value="add_custom_field">
                                            <input type="hidden" name="policy_id" value="<?=$policyId?>">
                                            <div class="col-md-3">
                                                <label class="form-label small">اسم الحقل بالعربية *</label>
                                                <input class="form-control form-control-sm" name="label_ar" maxlength="190" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small">اسم الحقل بالإنجليزية</label>
                                                <input class="form-control form-control-sm" name="label_en" maxlength="190">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">نوع الحقل *</label>
                                                <select class="form-select form-select-sm" name="field_type" required>
                                                    <?php foreach ($fieldTypes as $fieldType => $fieldLabel): ?>
                                                        <option value="<?=e($fieldType)?>"><?=e($fieldLabel)?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small">القيمة *</label>
                                                <textarea class="form-control form-control-sm" name="field_value" rows="1" maxlength="5000"></textarea>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label small">خيارات قائمة الاختيار (خيار في كل سطر)</label>
                                                <textarea class="form-control form-control-sm" name="options" rows="2" placeholder="تستخدم فقط إذا كان النوع قائمة اختيار"></textarea>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="is_required" value="1" id="field-required-<?=$policyId?>">
                                                    <label class="form-check-label small" for="field-required-<?=$policyId?>">حقل مطلوب</label>
                                                </div>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button class="btn btn-sm btn-outline-primary w-100" type="submit">إضافة الحقل</button>
                                            </div>
                                            <div class="col-12 small text-muted">لحقول نعم/لا استخدم 1 أو 0. يجب إدخال القيمة وفق نوع الحقل؛ الحقول المطلوبة لا يمكن تركها فارغة عند التفعيل.</div>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const englishName = document.getElementById('incentive-type-name-en');
    const codePreview = document.getElementById('incentive-type-code-preview');
    if (!englishName || !codePreview) return;

    const updateCodePreview = function () {
        let code = englishName.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        if (!code) code = 'incentive_category';
        else if (!/^[a-z]/.test(code)) code = 'incentive_' + code;
        if (code.length < 2) code = 'incentive_' + code;
        code = code.slice(0, 80).replace(/_+$/g, '');
        codePreview.value = code || 'incentive_category';
    };

    englishName.addEventListener('input', updateCodePreview);
    updateCodePreview();
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
