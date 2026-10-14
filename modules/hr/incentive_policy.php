<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../accounting/lib_transaction_review.php';

Session::start();
$role = (string)Session::getUserRole();
$canReviewPolicies = in_array($role, ['general_manager', 'gm'], true);
$canManagePolicies = $role === 'hr_manager';
$canActivatePolicies = $role === 'financial_manager';
$canManageTypes = $canManagePolicies;
if (!Session::isLoggedIn() || (!$canManagePolicies && !$canReviewPolicies)) {
    header('Location: ' . APP_URL . 'index.php');
    exit;
}

$pdo = db();
$message = '';
$error = '';
$fieldTypes = [
    'short_text' => t('incentives.short_text'),
    'long_text' => t('incentives.long_text'),
    'number' => t('incentives.number'),
    'date' => t('incentives.date'),
    'boolean' => t('incentives.yes_no'),
    'select' => t('incentives.select'),
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

function hrIncentiveFieldLabel(array $field): string
{
    $english = trim((string)($field['label_en'] ?? ''));
    return AK_LANG === 'en' && $english !== '' ? $english : (string)($field['label_ar'] ?? '');
}

function hrIncentivePolicyNotifyRoleUsers(int $policyId, array $roleCodes, string $title, string $body, string $link, string $referenceType, ?int $excludeUserId = null): void
{
    if ($policyId <= 0 || !$roleCodes || trim($title) === '') return;
    try {
        $roleCodes = array_values(array_unique(array_filter($roleCodes, static fn($code): bool => is_string($code) && $code !== '')));
        if (!$roleCodes) return;
        $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
        $users = dbFetchAll(
            "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code IN ($placeholders) AND u.is_active = 1",
            $roleCodes
        );
        foreach ($users as $user) {
            $userId = (int)($user['id'] ?? 0);
            if ($userId <= 0 || ($excludeUserId !== null && $userId === $excludeUserId)) continue;
            ak_transaction_review_notify_event($userId, $title, $body, $link, $policyId, $referenceType);
        }
    } catch (Throwable $notificationError) {
        // Notifications are post-commit handoffs; delivery failure never undoes the policy transition.
    }
}

function hrIncentivePolicyNotifyUser(int $userId, int $policyId, string $title, string $body, string $link, string $referenceType): void
{
    if ($userId <= 0 || $policyId <= 0) return;
    ak_transaction_review_notify_event($userId, $title, $body, $link, $policyId, $referenceType);
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
            flash('success', t('incentives.category_added'));
            header('Location: ' . APP_URL . 'modules/hr/incentive_policy.php');
            exit;
        }

        if ($action === 'create_policy') {
            if (!$canManagePolicies) throw new RuntimeException('ليس لديك صلاحية إنشاء مسودة سياسة.');
            $name = trim((string)($_POST['policy_name'] ?? ''));
            $effectiveFrom = trim((string)($_POST['effective_from'] ?? ''));
            if ($name === '' || mb_strlen($name) > 150) {
                throw new InvalidArgumentException('اسم السياسة مطلوب وبحد أقصى 150 حرفاً.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveFrom);
            if (!$date || $date->format('Y-m-d') !== $effectiveFrom || $effectiveFrom < date('Y-m-d')) {
                throw new InvalidArgumentException('تاريخ السريان يجب أن يكون اليوم أو تاريخاً مستقبلياً؛ لا يمكن إنشاء سياسة بأثر رجعي.');
            }
            $types = dbFetchAll('SELECT type_code, name_ar, name_en, qualification_mode FROM hr_incentive_types WHERE is_active = 1 ORDER BY sort_order, id');
            if (!$types) throw new RuntimeException('لا توجد أنواع حوافز نشطة لإضافتها إلى السياسة.');
            $rules = [];
            foreach ($types as $typeRow) {
                $typeCode = (string)$typeRow['type_code'];
                $enabled = isset($_POST['enabled'][$typeCode]) ? 1 : 0;
                $amountRaw = trim((string)($_POST['amount'][$typeCode] ?? ''));
                $amount = filter_var($amountRaw, FILTER_VALIDATE_FLOAT);
                if ($amount === false || !is_finite((float)$amount) || (float)$amount < 0 || (float)$amount > 9999999999.99) {
                    throw new InvalidArgumentException(t('incentives.invalid_amount') . ': ' . (string)(AK_LANG === 'en' ? $typeRow['name_en'] : $typeRow['name_ar']));
                }
                if ($enabled && (float)$amount <= 0) {
                    throw new InvalidArgumentException(t('incentives.enabled_amount_positive') . ': ' . (string)(AK_LANG === 'en' ? $typeRow['name_en'] : $typeRow['name_ar']));
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
                 (version_no, policy_name, status, effective_from, created_by_user_id)
                 VALUES (?, ?, 'draft', ?, ?)"
            );
            $stmt->execute([$version, $name, $effectiveFrom, (int)Session::getUserID()]);
            $policyId = (int)$pdo->lastInsertId();
            $ruleStmt = $pdo->prepare(
                'INSERT INTO hr_incentive_policy_rules (policy_version_id, incentive_type, reward_amount, currency_code, is_enabled)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($rules as $typeCode => $rule) {
                $ruleStmt->execute([$policyId, $typeCode, $rule['amount'], $rule['currency'], $rule['enabled']]);
            }
            $pdo->commit();
            flash('success', t('incentives.draft_saved'));
            header('Location: ' . APP_URL . 'modules/hr/incentive_policy.php');
            exit;
        }

        if ($action === 'add_custom_field') {
            if (!$canManagePolicies) {
                throw new RuntimeException('ليس لديك صلاحية إدارة حقول سياسة الحوافز.');
            }

            $policyId = (int)($_POST['policy_id'] ?? 0);
            $pdo->beginTransaction();
            $policy = dbFetchOne(
                'SELECT id, status FROM hr_incentive_policy_versions WHERE id = ? FOR UPDATE',
                [$policyId]
            );
            if (!$policy || $policy['status'] !== 'draft') {
                throw new InvalidArgumentException('يمكن إضافة الحقول إلى مسودة سياسة قابلة للتحرير فقط.');
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
            $pdo->commit();
            flash('success', t('incentives.field_added'));
            header('Location: ' . APP_URL . 'modules/hr/incentive_policy.php');
            exit;
        }


        if ($action === 'update_policy') {
            if (!$canManagePolicies) throw new RuntimeException('ليس لديك صلاحية تعديل سياسات الحوافز.');
            $policyId = (int)($_POST['policy_id'] ?? 0);
            $name = trim((string)($_POST['policy_name'] ?? ''));
            $effectiveFrom = trim((string)($_POST['effective_from'] ?? ''));
            if ($name === '' || mb_strlen($name) > 150) throw new InvalidArgumentException('اسم السياسة مطلوب وبحد أقصى 150 حرفاً.');
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveFrom);
            if (!$date || $date->format('Y-m-d') !== $effectiveFrom || $effectiveFrom < date('Y-m-d')) {
                throw new InvalidArgumentException('تاريخ السريان يجب أن يكون اليوم أو تاريخاً مستقبلياً؛ لا يمكن إنشاء سياسة بأثر رجعي.');
            }
            $pdo->beginTransaction();
            $policy = dbFetchOne('SELECT id, status FROM hr_incentive_policy_versions WHERE id = ? FOR UPDATE', [$policyId]);
            if (!$policy || $policy['status'] !== 'draft') throw new InvalidArgumentException('يمكن تعديل المسودات فقط.');
            $storedRules = dbFetchAll('SELECT r.incentive_type, t.name_ar, t.name_en, t.is_active FROM hr_incentive_policy_rules r JOIN hr_incentive_types t ON t.type_code=r.incentive_type WHERE r.policy_version_id=? FOR UPDATE', [$policyId]);
            if (!$storedRules) throw new InvalidArgumentException('السياسة لا تحتوي على فئات حوافز.');
            $enabledCount = 0;
            foreach ($storedRules as $storedRule) {
                $code = (string)$storedRule['incentive_type'];
                $enabled = isset($_POST['enabled'][$code]) ? 1 : 0;
                $raw = trim((string)($_POST['amount'][$code] ?? ''));
                $amount = filter_var($raw, FILTER_VALIDATE_FLOAT);
                if ($amount === false || !is_finite((float)$amount) || (float)$amount < 0 || (float)$amount > 9999999999.99) {
                    throw new InvalidArgumentException(t('incentives.invalid_amount') . ': ' . (string)(AK_LANG === 'en' ? $storedRule['name_en'] : $storedRule['name_ar']));
                }
                if ($enabled && (float)$amount <= 0) throw new InvalidArgumentException(t('incentives.enabled_amount_positive') . ': ' . (string)(AK_LANG === 'en' ? $storedRule['name_en'] : $storedRule['name_ar']));
                if ($enabled && (int)$storedRule['is_active'] !== 1) throw new InvalidArgumentException(t('incentives.inactive_category_enable') . ': ' . (string)(AK_LANG === 'en' ? $storedRule['name_en'] : $storedRule['name_ar']));
                if ($enabled) $enabledCount++;
                $pdo->prepare('UPDATE hr_incentive_policy_rules SET is_enabled=?, reward_amount=? WHERE policy_version_id=? AND incentive_type=?')
                    ->execute([$enabled, round((float)$amount, 2), $policyId, $code]);
            }
            if ($enabledCount < 1) throw new InvalidArgumentException('فعّل نوع حافز واحداً على الأقل قبل حفظ السياسة.');
            $pdo->prepare('UPDATE hr_incentive_policy_versions SET policy_name=?, effective_from=? WHERE id=? AND status=\'draft\'')
                ->execute([$name, $effectiveFrom, $policyId]);
            $storedFields = dbFetchAll('SELECT id, field_type, is_required, options_json FROM hr_incentive_policy_custom_fields WHERE policy_version_id=? FOR UPDATE', [$policyId]);
            foreach ($storedFields as $field) {
                $fieldId = (int)$field['id'];
                $rawValue = (string)($_POST['custom_values'][$fieldId] ?? '');
                $options = $field['options_json'] ? json_decode((string)$field['options_json'], true, 512, JSON_THROW_ON_ERROR) : [];
                $value = hrIncentiveValidateCustomValue((string)$field['field_type'], $rawValue, is_array($options) ? $options : [], (int)$field['is_required'] === 1);
                $pdo->prepare('UPDATE hr_incentive_policy_custom_fields SET field_value=? WHERE id=? AND policy_version_id=?')
                    ->execute([$value, $fieldId, $policyId]);
            }
            $pdo->commit();
            flash('success', t('incentives.draft_updated'));
            header('Location: ' . APP_URL . 'modules/hr/incentive_policy.php');
            exit;
        }

        if ($action === 'submit_policy') {
            if (!$canManagePolicies) throw new RuntimeException('ليس لديك صلاحية إرسال سياسة للاعتماد.');
            $policyId = (int)($_POST['policy_id'] ?? 0);
            $pdo->beginTransaction();
            $policy = dbFetchOne('SELECT * FROM hr_incentive_policy_versions WHERE id=? FOR UPDATE', [$policyId]);
            if (!$policy || $policy['status'] !== 'draft') throw new InvalidArgumentException('المسودة غير موجودة أو سبق إرسالها.');
            if ((string)$policy['effective_from'] < date('Y-m-d')) throw new InvalidArgumentException('لا يمكن إرسال سياسة بتاريخ سريان سابق.');
            $rules = dbFetchAll('SELECT r.incentive_type, r.is_enabled, r.reward_amount, t.name_ar, t.name_en, t.is_active FROM hr_incentive_policy_rules r JOIN hr_incentive_types t ON t.type_code=r.incentive_type WHERE r.policy_version_id=? FOR UPDATE', [$policyId]);
            $enabledCount = 0;
            foreach ($rules as $rule) {
                if ((int)$rule['is_enabled'] === 1) {
                    if ((float)$rule['reward_amount'] <= 0) throw new InvalidArgumentException(t('incentives.enabled_amount_positive') . ': ' . (string)(AK_LANG === 'en' ? $rule['name_en'] : $rule['name_ar']));
                    if ((int)$rule['is_active'] !== 1) throw new InvalidArgumentException(t('incentives.inactive_enabled_category') . ': ' . (string)(AK_LANG === 'en' ? $rule['name_en'] : $rule['name_ar']));
                    $enabledCount++;
                }
            }
            if ($enabledCount < 1) throw new InvalidArgumentException('فعّل نوع حافز واحداً على الأقل قبل الإرسال.');
            $missing = dbFetchAll("SELECT label_ar, label_en FROM hr_incentive_policy_custom_fields WHERE policy_version_id=? AND is_required=1 AND (field_value IS NULL OR TRIM(field_value)='')", [$policyId]);
            if ($missing) throw new InvalidArgumentException(t('incentives.required_fields_submit') . ': ' . implode(', ', array_map(static fn(array $field): string => (string)(AK_LANG === 'en' && !empty($field['label_en']) ? $field['label_en'] : $field['label_ar']), $missing)));
            $pdo->prepare("UPDATE hr_incentive_policy_versions SET status='pending_gm' WHERE id=? AND status='draft'")->execute([$policyId]);
            $pdo->prepare("INSERT INTO hr_incentive_policy_approval_history (policy_version_id, action, actor_user_id) VALUES (?, 'submitted', ?)")->execute([$policyId, (int)Session::getUserID()]);
            $pdo->commit();
            $policyLabel = 'سياسة الحوافز V' . (int)$policy['version_no'] . ' «' . (string)$policy['policy_name'] . '»';
            hrIncentivePolicyNotifyRoleUsers($policyId, ['general_manager', 'gm'], 'سياسة حوافز بانتظار اعتمادك', 'أرسل مدير الموارد البشرية ' . $policyLabel . ' للاعتماد. يرجى مراجعة تفاصيل السياسة واتخاذ قرار الاعتماد أو الرفض.', APP_URL . 'modules/hr/incentive_policy.php', 'hr_incentive_policy_submitted');
            flash('success', t('incentives.submit_success'));
            header('Location: ' . APP_URL . 'modules/hr/incentive_policy.php');
            exit;
        }

        if ($action === 'approve_policy') {
            if (!$canReviewPolicies) throw new RuntimeException('اعتماد سياسة الحوافز متاح للمدير العام فقط.');
            $policyId = (int)($_POST['policy_id'] ?? 0);
            $pdo->beginTransaction();
            $policy = dbFetchOne('SELECT id, status, policy_name, version_no, created_by_user_id FROM hr_incentive_policy_versions WHERE id=? FOR UPDATE', [$policyId]);
            if (!$policy || $policy['status'] !== 'pending_gm') throw new InvalidArgumentException('السياسة غير موجودة في قائمة انتظار اعتماد المدير العام.');
            $pdo->prepare("UPDATE hr_incentive_policy_versions SET status='approved' WHERE id=? AND status='pending_gm'")->execute([$policyId]);
            $pdo->prepare("INSERT INTO hr_incentive_policy_approval_history (policy_version_id, action, actor_user_id) VALUES (?, 'approved', ?)")->execute([$policyId, (int)Session::getUserID()]);
            $pdo->commit();
            $policyLabel = 'سياسة الحوافز V' . (int)$policy['version_no'] . ' «' . (string)$policy['policy_name'] . '»';
            hrIncentivePolicyNotifyUser((int)$policy['created_by_user_id'], $policyId, 'اعتمد المدير العام سياسة الحوافز', 'اعتمد المدير العام ' . $policyLabel . '. أصبحت السياسة متاحة للمدير المالي لاختيارها وتفعيلها وفق تاريخ السريان.', APP_URL . 'modules/hr/incentive_policy.php', 'hr_incentive_policy_approved');
            hrIncentivePolicyNotifyRoleUsers($policyId, ['financial_manager', 'fm', 'finance'], 'سياسة حوافز معتمدة بانتظار التفعيل', $policyLabel . ' اعتمدها المدير العام وأصبحت متاحة لك للاختيار والتفعيل وفق تاريخ السريان.', APP_URL . 'modules/hr/incentive_policy.php', 'hr_incentive_policy_approved_for_fm');
            flash('success', t('incentives.approve_success'));
            header('Location: ' . APP_URL . 'modules/hr/incentive_policy.php');
            exit;
        }

        if ($action === 'reject_policy') {
            if (!$canReviewPolicies) throw new RuntimeException('رفض سياسة الحوافز متاح للمدير العام فقط.');
            $policyId = (int)($_POST['policy_id'] ?? 0);
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($reason === '' || mb_strlen($reason) > 2000) throw new InvalidArgumentException('سبب الرفض مطلوب وبحد أقصى 2000 حرف.');
            $pdo->beginTransaction();
            $policy = dbFetchOne('SELECT id, status FROM hr_incentive_policy_versions WHERE id=? FOR UPDATE', [$policyId]);
            if (!$policy || $policy['status'] !== 'pending_gm') throw new InvalidArgumentException('السياسة غير موجودة في قائمة انتظار الاعتماد.');
            $pdo->prepare("INSERT INTO hr_incentive_policy_approval_history (policy_version_id, action, actor_user_id, reason) VALUES (?, 'rejected', ?, ?)")->execute([$policyId, (int)Session::getUserID(), $reason]);
            $pdo->prepare("UPDATE hr_incentive_policy_versions SET status='draft' WHERE id=? AND status='pending_gm'")->execute([$policyId]);
            $pdo->commit();
            $policyLabel = 'سياسة الحوافز V' . (int)$policy['version_no'] . ' «' . (string)$policy['policy_name'] . '»';
            hrIncentivePolicyNotifyUser((int)$policy['created_by_user_id'], $policyId, 'رفض المدير العام سياسة الحوافز', 'رفض المدير العام ' . $policyLabel . '. سبب الرفض: ' . $reason . ' يمكنك مراجعة السبب وتعديل المسودة ثم إعادة إرسالها.', APP_URL . 'modules/hr/incentive_policy.php', 'hr_incentive_policy_rejected');
            flash('success', t('incentives.reject_success'));
            header('Location: ' . APP_URL . 'modules/hr/incentive_policy.php');
            exit;
        }

        if ($action === 'activate_policy') {
            if (!$canActivatePolicies) throw new RuntimeException('تفعيل سياسة الحوافز من اختصاص المدير المالي فقط.');
            $policyId = (int)($_POST['policy_id'] ?? 0);
            $pdo->beginTransaction();
            $policy = dbFetchOne('SELECT * FROM hr_incentive_policy_versions WHERE id = ? FOR UPDATE', [$policyId]);
            if (!$policy || $policy['status'] !== 'approved') {
                throw new InvalidArgumentException('السياسة غير موجودة أو لم يعتمدها المدير العام بعد.');
            }
            if ((string)$policy['effective_from'] < date('Y-m-d')) {
                throw new InvalidArgumentException('لا يمكن تفعيل سياسة بأثر رجعي.');
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
                "SELECT label_ar, label_en FROM hr_incentive_policy_custom_fields
                 WHERE policy_version_id = ? AND is_required = 1 AND (field_value IS NULL OR TRIM(field_value) = '')",
                [$policyId]
            );
            if ($requiredFields) throw new InvalidArgumentException(t('incentives.required_fields_activate') . ': ' . implode(', ', array_map(static fn(array $field): string => (string)(AK_LANG === 'en' && !empty($field['label_en']) ? $field['label_en'] : $field['label_ar']), $requiredFields)));

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
                 WHERE id=? AND status='approved'"
            )->execute([(int)Session::getUserID(), $policyId]);
            $pdo->commit();
            $policyLabel = 'سياسة الحوافز V' . (int)$policy['version_no'] . ' «' . (string)$policy['policy_name'] . '»';
            hrIncentivePolicyNotifyUser((int)$policy['created_by_user_id'], $policyId, 'فعّل المدير المالي سياسة الحوافز', 'فعّل المدير المالي ' . $policyLabel . ' وفق تاريخ السريان المعتمد.', APP_URL . 'modules/hr/incentive_policy.php', 'hr_incentive_policy_activated_for_hr');
            try {
                $approver = dbFetchOne("SELECT actor_user_id FROM hr_incentive_policy_approval_history WHERE policy_version_id = ? AND action = 'approved' ORDER BY id DESC LIMIT 1", [$policyId]);
                hrIncentivePolicyNotifyUser((int)($approver['actor_user_id'] ?? 0), $policyId, 'تم تفعيل سياسة الحوافز التي اعتمدتها', 'فعّل المدير المالي ' . $policyLabel . ' وفق تاريخ السريان المعتمد.', APP_URL . 'modules/hr/incentive_policy.php', 'hr_incentive_policy_activated_for_gm');
            } catch (Throwable $notificationError) {
                // A post-commit notification lookup must not misreport activation as failed.
            }
            flash('success', t('incentives.activation_success'));
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
foreach ($allTypes as $typeRow) $typeNames[(string)$typeRow['type_code']] = (string)(AK_LANG === 'en' ? ($typeRow['name_en'] ?? $typeRow['name_ar']) : $typeRow['name_ar']);
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

$approvalRows = dbFetchAll(
    'SELECT h.*, u.full_name AS actor_name FROM hr_incentive_policy_approval_history h LEFT JOIN users u ON u.id=h.actor_user_id ORDER BY h.policy_version_id DESC, h.created_at, h.id'
);
$historyByPolicy = [];
foreach ($approvalRows as $approvalRow) $historyByPolicy[(int)$approvalRow['policy_version_id']][] = $approvalRow;

$pageTitle = t('incentives.title');
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-3" style="max-width:1320px">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1"><i class="fas fa-award text-primary me-2"></i>سياسة حوافز الموظفين</h1>
            <p class="text-muted mb-0 small">ينشئ مدير الموارد البشرية المسودة ويرسلها للاعتماد؛ يعتمد المدير العام السياسة أو يرفضها، ثم يختار المدير المالي السياسة المعتمدة ويفعّلها.</p>
        </div>
    </div>
    <?php if ($message !== ''): ?><div class="alert alert-success" data-ak-transient-alert="1"><?=e($message)?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger" data-ak-transient-alert="1"><?=e($error)?></div><?php endif; ?>

    <div class="alert alert-info small">
        <?=e(t('incentives.currency_notice'))?> <?=e(APP_CURRENCY_CODE)?> (<?=e(AK_LANG === 'en' ? APP_CURRENCY_NAME_EN : APP_CURRENCY_NAME_AR)?>).
        الفئات الأربع الأساسية مرتبطة بأحداث النظام الحالية. أي فئة جديدة تُضاف هنا بوضع «مراجعة يدوية» ولا تصدر مكافآت تلقائياً؛ مسار الترشيح والصرف للفئات اليدوية يحتاج تنفيذاً مستقلاً قبل استخدامها مالياً.
    </div>

    <?php if ($canManageTypes): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">إدارة فئات الحوافز</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="create_category">
                <div class="col-md-4">
                    <label class="form-label">الاسم بالعربية *</label>
                    <input class="form-control" name="name_ar" maxlength="190" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">الاسم بالإنجليزية *</label>
                    <input class="form-control" id="incentive-type-name-en" name="name_en" maxlength="190" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
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
                            <td><?=e(AK_LANG === 'en' ? $typeRow['name_en'] : $typeRow['name_ar'])?></td>
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

    <?php if ($canManagePolicies): ?>
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
                </div>
                <hr>
                <p class="small text-muted">حدّد الفئات المشمولة في هذا الإصدار وأدخل المبالغ الثابتة. الفئات التي ستُضاف لاحقاً لا تُدرج تلقائياً في الإصدارات السابقة.</p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light"><tr><th>فئة الحافز</th><th>آلية الاستحقاق</th><th>مفعّل؟</th><th>المبلغ (<?=e(APP_CURRENCY_CODE)?>)</th></tr></thead>
                        <tbody>
                        <?php foreach ($activeTypes as $typeRow): $typeCode = (string)$typeRow['type_code']; ?>
                            <tr>
                                <td><?=e(AK_LANG === 'en' ? $typeRow['name_en'] : $typeRow['name_ar'])?><br><code class="small"><?=e($typeCode)?></code></td>
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
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold">إصدارات السياسات</div>
        <div class="card-body">
            <?php if (!$policies): ?>
                <div class="text-muted text-center py-4">لا توجد إصدارات سياسة حتى الآن.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light"><tr><th>الإصدار</th><th><?=e(t('incentives.name'))?></th><th>الحالة</th><th>السريان</th><th>فئات مفعّلة</th><th>أُنشئت بواسطة</th><th>الإجراء</th></tr></thead>
                        <tbody>
                        <?php foreach ($policies as $policy): $policyId = (int)$policy['id']; ?>
                            <tr>
                                <td>V<?= (int)$policy['version_no'] ?></td>
                                <td><?=e($policy['policy_name'])?></td>
                                <td><?=e(['draft'=>'مسودة','pending_gm'=>'بانتظار اعتماد GM','approved'=>'معتمدة — بانتظار التفعيل','active'=>'سارية','retired'=>'منتهية/مستبدلة'][$policy['status']] ?? $policy['status'])?></td>
                                <td><?=e($policy['effective_from'])?><?= $policy['effective_to'] ? ' — ' . e($policy['effective_to']) : '' ?></td>
                                <td><?= (int)$policy['enabled_rules'] ?></td>
                                <td><small><?=e($policy['created_by_name'] ?? '')?></small></td>
                                <td>
                                    <?php if ($policy['status'] === 'draft' && $canManagePolicies): ?>
                                        <form method="post" class="mb-2">
                                            <?=csrf_field()?>
                                            <input type="hidden" name="action" value="submit_policy">
                                            <input type="hidden" name="policy_id" value="<?=$policyId?>">
                                            <button class="btn btn-sm btn-primary" type="submit">إرسال لاعتماد GM</button>
                                        </form>
                                    <?php elseif ($policy['status'] === 'pending_gm' && $canReviewPolicies): ?>
                                        <form method="post" class="mb-2" onsubmit="return confirm('اعتماد هذه السياسة؟ لن يتم تفعيلها تلقائياً.');">
                                            <?=csrf_field()?>
                                            <input type="hidden" name="action" value="approve_policy">
                                            <input type="hidden" name="policy_id" value="<?=$policyId?>">
                                            <button class="btn btn-sm btn-success" type="submit">اعتماد السياسة</button>
                                        </form>
                                        <form method="post" class="mt-2">
                                            <?=csrf_field()?>
                                            <input type="hidden" name="action" value="reject_policy">
                                            <input type="hidden" name="policy_id" value="<?=$policyId?>">
                                            <label class="form-label small">سبب الرفض *</label>
                                            <textarea class="form-control form-control-sm mb-2" name="reason" rows="2" maxlength="2000" required></textarea>
                                            <button class="btn btn-sm btn-outline-danger" type="submit">رفض وإعادة للمسودة</button>
                                        </form>
                                    <?php elseif ($policy['status'] === 'approved' && $canActivatePolicies): ?>
                                        <form method="post" onsubmit="return confirm('تفعيل السياسة المعتمدة وفق تاريخ السريان؟');">
                                            <?=csrf_field()?>
                                            <input type="hidden" name="action" value="activate_policy">
                                            <input type="hidden" name="policy_id" value="<?=$policyId?>">
                                            <button class="btn btn-sm btn-success" type="submit">تفعيل السياسة</button>
                                        </form>
                                    <?php elseif ($policy['status'] === 'draft' && !$canManagePolicies): ?>
                                        <span class="text-muted small">مسودة قابلة للتعديل لدى مدير الموارد البشرية</span>
                                    <?php elseif ($policy['status'] === 'pending_gm'): ?>
                                        <span class="text-muted small">بانتظار قرار GM</span>
                                    <?php elseif ($policy['status'] === 'approved'): ?>
                                        <span class="text-muted small">بانتظار تفعيل المدير المالي</span>
                                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                            </tr>
                            <tr class="table-light"><td colspan="7">
                                <div class="d-flex flex-wrap gap-2 small">
                                <?php foreach (($rulesByPolicy[$policyId] ?? []) as $rule): ?>
                                    <span class="border rounded px-2 py-1 bg-white"><strong><?=e($typeNames[$rule['incentive_type']] ?? $rule['incentive_type'])?>:</strong>
                                    <?= (int)$rule['is_enabled'] === 1 ? number_format((float)$rule['reward_amount'],2) . ' ' . e($rule['currency_code']) : t('incentives.disabled') ?></span>
                                <?php endforeach; ?>
                                </div>


                                <?php if (!empty($historyByPolicy[$policyId])): ?>
                                    <div class="mt-3"><strong class="small">سجل الاعتماد</strong><ul class="small mb-0 mt-1">
                                    <?php foreach ($historyByPolicy[$policyId] as $history): ?>
                                        <li><?=e(['submitted'=>'أُرسلت للاعتماد','approved'=>'اعتماد GM','rejected'=>'رفض GM وإعادة للمسودة'][$history['action']] ?? $history['action'])?> — <?=e($history['actor_name'] ?? 'مستخدم')?> — <?=e($history['created_at'])?><?php if (!empty($history['reason'])): ?><div class="text-danger"><?=nl2br(e($history['reason']))?></div><?php endif; ?></li>
                                    <?php endforeach; ?>
                                    </ul></div>
                                <?php endif; ?>

                                <?php if (!empty($fieldsByPolicy[$policyId])): ?>
                                    <div class="mt-3">
                                        <strong class="small">الحقول الإضافية لهذا الإصدار</strong>
                                        <div class="d-flex flex-column gap-1 mt-1">
                                        <?php foreach ($fieldsByPolicy[$policyId] as $field): ?>
                                            <div class="small">
                                                <strong><?=e(hrIncentiveFieldLabel($field))?><?= (int)$field['is_required'] === 1 ? ' *' : '' ?>:</strong>
                                                <?php
                                                $displayValue = (string)($field['field_value'] ?? '');
                                                if ($field['field_type'] === 'boolean' && $displayValue !== '') $displayValue = $displayValue === '1' ? t('incentives.yes') : t('incentives.no');
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
                                        <summary class="small fw-bold">تعديل بيانات المسودة والفئات والحقول</summary>
                                        <form method="post" class="mt-2">
                                            <?=csrf_field()?>
                                            <input type="hidden" name="action" value="update_policy">
                                            <input type="hidden" name="policy_id" value="<?=$policyId?>">
                                            <div class="row g-2 mb-3">
                                                <div class="col-md-6"><label class="form-label small">اسم السياسة *</label><input class="form-control form-control-sm" name="policy_name" maxlength="150" value="<?=e($policy['policy_name'])?>" required></div>
                                                <div class="col-md-6"><label class="form-label small">تاريخ السريان *</label><input class="form-control form-control-sm" type="date" name="effective_from" min="<?=e(date('Y-m-d'))?>" value="<?=e($policy['effective_from'])?>" required></div>
                                            </div>
                                            <div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead><tr><th>الفئة</th><th>مفعّل؟</th><th>المبلغ (<?=e(APP_CURRENCY_CODE)?>)</th></tr></thead><tbody>
                                            <?php foreach (($rulesByPolicy[$policyId] ?? []) as $editRule): $editCode=(string)$editRule['incentive_type']; ?>
                                                <tr><td><?=e($typeNames[$editCode] ?? $editCode)?></td><td class="text-center"><input class="form-check-input" type="checkbox" name="enabled[<?=e($editCode)?>]" value="1" <?= (int)$editRule['is_enabled']===1 ? 'checked' : '' ?>></td><td><input class="form-control form-control-sm" type="number" name="amount[<?=e($editCode)?>]" min="0" max="9999999999.99" step="0.01" value="<?=e($editRule['reward_amount'])?>" required></td></tr>
                                            <?php endforeach; ?>
                                            </tbody></table></div>
                                            <?php foreach (($fieldsByPolicy[$policyId] ?? []) as $editField): $editValue=(string)($editField['field_value'] ?? ''); ?>
                                                <div class="mb-2"><label class="form-label small"><?=e(hrIncentiveFieldLabel($editField))?><?= (int)$editField['is_required']===1 ? ' *' : '' ?></label>
                                                <?php if ($editField['field_type']==='long_text'): ?><textarea class="form-control form-control-sm" name="custom_values[<?=(int)$editField['id']?>]" maxlength="5000" rows="2" <?= (int)$editField['is_required']===1 ? 'required' : '' ?>><?=e($editValue)?></textarea>
                                                <?php elseif ($editField['field_type']==='select'): $editOptions=json_decode((string)($editField['options_json'] ?? '[]'),true) ?: []; ?><select class="form-select form-select-sm" name="custom_values[<?=(int)$editField['id']?>]" <?= (int)$editField['is_required']===1 ? 'required' : '' ?>><option value="">—</option><?php foreach ($editOptions as $editOption): ?><option value="<?=e($editOption)?>" <?= $editValue===(string)$editOption ? 'selected' : '' ?>><?=e($editOption)?></option><?php endforeach; ?></select>
                                                <?php elseif ($editField['field_type']==='boolean'): ?><select class="form-select form-select-sm" name="custom_values[<?=(int)$editField['id']?>]" <?= (int)$editField['is_required']===1 ? 'required' : '' ?>><option value="">—</option><option value="1" <?=$editValue==='1'?'selected':''?>><?=e(t('incentives.yes'))?></option><option value="0" <?=$editValue==='0'?'selected':''?>><?=e(t('incentives.no'))?></option></select>
                                                <?php else: ?><input class="form-control form-control-sm" type="<?= $editField['field_type']==='number' ? 'number' : ($editField['field_type']==='date' ? 'date' : 'text') ?>" name="custom_values[<?=(int)$editField['id']?>]" value="<?=e($editValue)?>" maxlength="<?= $editField['field_type']==='short_text' ? 500 : 5000 ?>" <?= (int)$editField['is_required']===1 ? 'required' : '' ?>><?php endif; ?></div>
                                            <?php endforeach; ?>
                                            <button class="btn btn-sm btn-outline-primary" type="submit">حفظ التعديلات</button>
                                        </form>
                                    </details>
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
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
