<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/lib_transaction_review.php';

final class AkApiIncentivePolicyException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly string $errorCode = 'invalid_policy_request'
    ) {
        parent::__construct($message);
    }
}

function ak_api_incentive_policy_validate_field(string $type, string $value, array $options, bool $required): ?string
{
    $value = trim($value);
    if ($value === '') {
        if ($required) throw new AkApiIncentivePolicyException('A value is required for this custom field.');
        return null;
    }
    switch ($type) {
        case 'short_text':
            if (mb_strlen($value) > 500) throw new AkApiIncentivePolicyException('Short text must not exceed 500 characters.');
            break;
        case 'long_text':
            if (mb_strlen($value) > 5000) throw new AkApiIncentivePolicyException('Long text must not exceed 5000 characters.');
            break;
        case 'number':
            $number = filter_var($value, FILTER_VALIDATE_FLOAT);
            if ($number === false || !is_finite((float)$number)) throw new AkApiIncentivePolicyException('Enter a valid number.');
            break;
        case 'date':
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) throw new AkApiIncentivePolicyException('Enter a valid date.');
            break;
        case 'boolean':
            if (!in_array($value, ['0', '1'], true)) throw new AkApiIncentivePolicyException('Choose yes or no.');
            break;
        case 'select':
            if (!in_array($value, $options, true)) throw new AkApiIncentivePolicyException('Choose an option from the configured list.');
            break;
        default:
            throw new AkApiIncentivePolicyException('Unsupported custom field type.');
    }
    return $value;
}

function ak_api_incentive_policy_date(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $value < date('Y-m-d')) {
        throw new AkApiIncentivePolicyException('Effective date must be today or a future date.');
    }
    return $value;
}

function ak_api_incentive_policy_rules_input(mixed $raw): array
{
    if (!is_array($raw)) throw new AkApiIncentivePolicyException('Policy rules are required.');
    $result = [];
    foreach ($raw as $item) {
        if (!is_array($item)) continue;
        $code = trim((string)($item['type_code'] ?? $item['incentive_type'] ?? ''));
        if ($code === '' || isset($result[$code])) throw new AkApiIncentivePolicyException('Policy rules contain a missing or duplicate category.');
        $result[$code] = $item;
    }
    return $result;
}

function ak_api_incentive_policy_notify_user(int $userId, int $policyId, string $title, string $body, string $referenceType): void
{
    if ($userId <= 0 || $policyId <= 0) return;
    try {
        ak_transaction_review_notify_event(
            $userId,
            $title,
            $body,
            APP_URL . 'modules/hr/incentive_policy.php',
            $policyId,
            $referenceType
        );
    } catch (Throwable $exception) {
        error_log('Mobile incentive-policy notification failed: ' . $exception->getMessage());
    }
}

function ak_api_incentive_policy_notify_roles(int $policyId, array $roleCodes, string $title, string $body, string $referenceType, ?int $excludeUserId = null): void
{
    if ($policyId <= 0 || !$roleCodes) return;
    try {
        $roleCodes = array_values(array_unique(array_filter($roleCodes, static fn($code): bool => is_string($code) && $code !== '')));
        if (!$roleCodes) return;
        $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
        $users = dbFetchAll(
            "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.code IN ($placeholders) AND u.is_active = 1",
            $roleCodes
        );
        foreach ($users as $row) {
            $userId = (int)($row['id'] ?? 0);
            if ($userId <= 0 || ($excludeUserId !== null && $userId === $excludeUserId)) continue;
            ak_api_incentive_policy_notify_user($userId, $policyId, $title, $body, $referenceType);
        }
    } catch (Throwable $exception) {
        error_log('Mobile incentive-policy role notification failed: ' . $exception->getMessage());
    }
}

function ak_api_incentive_policy_label(array $policy): string
{
    return 'سياسة الحوافز V' . (int)($policy['version_no'] ?? 0) . ' «' . (string)($policy['policy_name'] ?? '') . '»';
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
}

$user = ak_api_authenticated_user();
$role = (string)$user['role_code'];
$canManage = $role === 'hr_manager';
$canReview = $role === 'general_manager';
$canActivate = $role === 'financial_manager';
if (!$canManage && !$canReview && !$canActivate) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Incentive policy access is not available for this role.']]);
}

if ($method === 'GET') {
    if ($canActivate) {
        $visibility = "WHERE p.status IN ('approved', 'active', 'retired')";
    } elseif ($canReview) {
        $visibility = "WHERE p.status IN ('pending_gm', 'approved', 'active', 'retired')";
    } else {
        $visibility = '';
    }
    try {
        $policies = dbFetchAll(
            "SELECT p.*, u.full_name AS created_by_name, au.full_name AS activated_by_name,
                    (SELECT COUNT(*) FROM hr_incentive_policy_rules r
                     WHERE r.policy_version_id=p.id AND r.is_enabled=1) AS enabled_rules
             FROM hr_incentive_policy_versions p
             LEFT JOIN users u ON u.id=p.created_by_user_id
             LEFT JOIN users au ON au.id=p.activated_by_user_id
             $visibility
             ORDER BY p.version_no DESC"
        );
        $types = dbFetchAll('SELECT * FROM hr_incentive_types ORDER BY sort_order, id');
        $policyIds = array_values(array_map(static fn(array $row): int => (int)$row['id'], $policies));
        $rules = [];
        $customFields = [];
        $history = [];
        if ($policyIds) {
            $marks = implode(',', array_fill(0, count($policyIds), '?'));
            $rules = dbFetchAll(
                "SELECT r.*, t.name_ar, t.name_en, t.qualification_mode, t.is_active AS type_is_active
                 FROM hr_incentive_policy_rules r
                 JOIN hr_incentive_types t ON t.type_code = r.incentive_type
                 WHERE r.policy_version_id IN ($marks)
                 ORDER BY r.policy_version_id, r.id",
                $policyIds
            );
            $customFields = dbFetchAll(
                "SELECT * FROM hr_incentive_policy_custom_fields
                 WHERE policy_version_id IN ($marks)
                 ORDER BY policy_version_id, sort_order, id",
                $policyIds
            );
            $history = dbFetchAll(
                "SELECT h.*, u.full_name AS actor_name
                 FROM hr_incentive_policy_approval_history h
                 LEFT JOIN users u ON u.id=h.actor_user_id
                 WHERE h.policy_version_id IN ($marks)
                 ORDER BY h.policy_version_id, h.created_at, h.id",
                $policyIds
            );
        }
    } catch (Throwable $exception) {
        error_log('Mobile incentive-policy read failed: ' . $exception->getMessage());
        ak_api_json(500, ['ok' => false, 'error' => ['code' => 'policy_unavailable', 'message' => 'Incentive policy data is temporarily unavailable.']]);
    }

    ak_api_json(200, [
        'ok' => true,
        'data' => [
            'role' => $role,
            'can_manage' => $canManage,
            'can_review' => $canReview,
            'can_activate' => $canActivate,
            'currency_code' => APP_CURRENCY_CODE,
            'today' => date('Y-m-d'),
            'policies' => $policies,
            'types' => $types,
            'rules' => $rules,
            'custom_fields' => $customFields,
            'approval_history' => $history,
        ],
    ]);
}

$input = ak_api_read_json();
$action = trim((string)($input['action'] ?? ''));
$userId = (int)$user['id'];
$needsManager = ['create_category', 'create_policy', 'add_custom_field', 'update_policy', 'submit_policy'];
$needsGm = ['approve_policy', 'reject_policy', 'withdraw_approval_to_draft'];
if (in_array($action, $needsManager, true) && !$canManage) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Only the HR Manager can manage policy drafts and categories.']]);
}
if (in_array($action, $needsGm, true) && !$canReview) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Only the General Manager can review policy approval.']]);
}
if ($action === 'activate_policy' && !$canActivate) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Only the Financial Manager can activate an approved policy.']]);
}
if (!in_array($action, array_merge($needsManager, $needsGm, ['activate_policy']), true)) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'unknown_action', 'message' => 'Unknown incentive-policy action.']]);
}

$pdo = db();
try {
    if ($action === 'create_category') {
        $nameAr = trim((string)($input['name_ar'] ?? ''));
        $nameEn = trim((string)($input['name_en'] ?? ''));
        if ($nameAr === '' || mb_strlen($nameAr) > 190 || $nameEn === '' || mb_strlen($nameEn) > 190) {
            throw new AkApiIncentivePolicyException('Arabic and English category names are required (maximum 190 characters each).');
        }
        $baseCode = strtolower($nameEn);
        $baseCode = preg_replace('/[^a-z0-9]+/', '_', $baseCode) ?? '';
        $baseCode = trim($baseCode, '_');
        if ($baseCode === '') {
            $baseCode = 'incentive_category';
        } elseif (!preg_match('/^[a-z]/', $baseCode)) {
            $baseCode = 'incentive_' . $baseCode;
        }
        $baseCode = trim(substr($baseCode, 0, 80), '_');
        if (strlen($baseCode) < 2) $baseCode = 'incentive_' . ($baseCode !== '' ? $baseCode : 'category');
        $baseCode = substr($baseCode, 0, 80);
        $code = $baseCode;
        $suffix = 2;
        while (dbFetchOne('SELECT id FROM hr_incentive_types WHERE type_code=? LIMIT 1', [$code])) {
            $suffixText = '_' . $suffix;
            $code = rtrim(substr($baseCode, 0, 80 - strlen($suffixText)), '_') . $suffixText;
            $suffix++;
        }
        dbExecute(
            "INSERT INTO hr_incentive_types
                (type_code, name_ar, name_en, qualification_mode, is_system, is_active, sort_order, created_by_user_id)
             VALUES (?, ?, ?, 'manual_review', 0, 1, 100, ?)",
            [$code, $nameAr, $nameEn, $userId]
        );
        ak_api_json(201, ['ok' => true, 'data' => ['type_code' => $code, 'qualification_mode' => 'manual_review', 'is_active' => 1]]);
    }

    if ($action === 'create_policy') {
        $name = trim((string)($input['policy_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) throw new AkApiIncentivePolicyException('Policy name is required and must not exceed 150 characters.');
        $effectiveFrom = ak_api_incentive_policy_date(trim((string)($input['effective_from'] ?? '')));
        $types = dbFetchAll('SELECT type_code, is_active, name_ar, name_en FROM hr_incentive_types WHERE is_active=1 ORDER BY sort_order, id');
        if (!$types) throw new AkApiIncentivePolicyException('No active incentive categories are available.');
        $rulesInput = ak_api_incentive_policy_rules_input($input['rules'] ?? null);
        $rules = [];
        $enabledCount = 0;
        foreach ($types as $type) {
            $code = (string)$type['type_code'];
            if (!isset($rulesInput[$code])) throw new AkApiIncentivePolicyException('A rule is required for every active category.');
            $enabled = !empty($rulesInput[$code]['enabled']) ? 1 : 0;
            $amount = filter_var(trim((string)($rulesInput[$code]['amount'] ?? '')), FILTER_VALIDATE_FLOAT);
            if ($amount === false || !is_finite((float)$amount) || (float)$amount < 0 || (float)$amount > 9999999999.99) {
                throw new AkApiIncentivePolicyException('Enter a valid non-negative amount for every category.');
            }
            if ($enabled && (float)$amount <= 0) throw new AkApiIncentivePolicyException('Enabled incentive categories must have a positive amount.');
            if ($enabled) $enabledCount++;
            $rules[$code] = ['enabled' => $enabled, 'amount' => round((float)$amount, 2)];
        }
        if ($enabledCount < 1) throw new AkApiIncentivePolicyException('Enable at least one incentive category.');

        $pdo->beginTransaction();
        $next = dbFetchOne('SELECT COALESCE(MAX(version_no), 0) + 1 AS next_version FROM hr_incentive_policy_versions FOR UPDATE');
        $version = (int)($next['next_version'] ?? 1);
        $stmt = $pdo->prepare(
            "INSERT INTO hr_incentive_policy_versions (version_no, policy_name, status, effective_from, created_by_user_id)
             VALUES (?, ?, 'draft', ?, ?)"
        );
        $stmt->execute([$version, $name, $effectiveFrom, $userId]);
        $policyId = (int)$pdo->lastInsertId();
        $ruleStmt = $pdo->prepare(
            'INSERT INTO hr_incentive_policy_rules (policy_version_id, incentive_type, reward_amount, currency_code, is_enabled)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($rules as $code => $rule) {
            $ruleStmt->execute([$policyId, $code, $rule['amount'], APP_CURRENCY_CODE, $rule['enabled']]);
        }
        $pdo->commit();
        ak_api_json(201, ['ok' => true, 'data' => ['policy_id' => $policyId, 'version_no' => $version, 'status' => 'draft']]);
    }

    if ($action === 'add_custom_field') {
        $policyId = filter_var($input['policy_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($policyId === false || $policyId <= 0) throw new AkApiIncentivePolicyException('A valid draft policy is required.');
        $pdo->beginTransaction();
        $policy = dbFetchOne('SELECT id, status FROM hr_incentive_policy_versions WHERE id=? FOR UPDATE', [(int)$policyId]);
        if (!$policy) throw new AkApiIncentivePolicyException('Policy was not found.', 404, 'not_found');
        if ($policy['status'] !== 'draft') throw new AkApiIncentivePolicyException('Custom fields can be added to drafts only.', 409, 'invalid_transition');

        $labelAr = trim((string)($input['label_ar'] ?? ''));
        $labelEn = trim((string)($input['label_en'] ?? ''));
        $type = trim((string)($input['field_type'] ?? ''));
        $required = !empty($input['is_required']) ? 1 : 0;
        $rawValue = (string)($input['field_value'] ?? '');
        if ($labelAr === '' || mb_strlen($labelAr) > 190 || ($labelEn !== '' && mb_strlen($labelEn) > 190)) {
            throw new AkApiIncentivePolicyException('Arabic field label is required; English label is optional (maximum 190 characters).');
        }
        $fieldTypes = ['short_text', 'long_text', 'number', 'date', 'boolean', 'select'];
        if (!in_array($type, $fieldTypes, true)) throw new AkApiIncentivePolicyException('Choose a supported custom field type.');
        $options = [];
        $rawOptions = $input['options'] ?? [];
        if (is_string($rawOptions)) $rawOptions = preg_split('/\R/u', $rawOptions) ?: [];
        if ($type === 'select') {
            if (!is_array($rawOptions)) throw new AkApiIncentivePolicyException('Select-field options must be a list.');
            $options = array_values(array_unique(array_filter(array_map(static fn($v): string => trim((string)$v), $rawOptions), static fn(string $v): bool => $v !== '')));
            if (count($options) < 2 || count($options) > 50) throw new AkApiIncentivePolicyException('Select fields need between 2 and 50 options.');
            foreach ($options as $option) if (mb_strlen($option) > 190) throw new AkApiIncentivePolicyException('Each option must not exceed 190 characters.');
        } elseif (is_array($rawOptions) && $rawOptions) {
            throw new AkApiIncentivePolicyException('Options are only valid for select fields.');
        } elseif (is_string($rawOptions) && trim($rawOptions) !== '') {
            throw new AkApiIncentivePolicyException('Options are only valid for select fields.');
        }
        $value = ak_api_incentive_policy_validate_field($type, $rawValue, $options, $required === 1);
        $sort = (int)(dbFetchOne('SELECT COALESCE(MAX(sort_order),0)+10 AS next_order FROM hr_incentive_policy_custom_fields WHERE policy_version_id=?', [(int)$policyId])['next_order'] ?? 10);
        $key = 'custom_' . bin2hex(random_bytes(8));
        $stmt = $pdo->prepare(
            'INSERT INTO hr_incentive_policy_custom_fields
                (policy_version_id, field_key, label_ar, label_en, field_type, is_required, options_json, field_value, sort_order, created_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$policyId, $key, $labelAr, $labelEn !== '' ? $labelEn : null, $type, $required,
            $type === 'select' ? json_encode($options, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            $value, $sort, $userId
        ]);
        $fieldId = (int)$pdo->lastInsertId();
        $pdo->commit();
        ak_api_json(201, ['ok' => true, 'data' => ['field_id' => $fieldId, 'field_key' => $key]]);
    }

    if ($action === 'update_policy') {
        $policyId = filter_var($input['policy_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($policyId === false || $policyId <= 0) throw new AkApiIncentivePolicyException('A valid draft policy is required.');
        $name = trim((string)($input['policy_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) throw new AkApiIncentivePolicyException('Policy name is required and must not exceed 150 characters.');
        $effectiveFrom = ak_api_incentive_policy_date(trim((string)($input['effective_from'] ?? '')));
        $pdo->beginTransaction();
        $policy = dbFetchOne('SELECT id, status FROM hr_incentive_policy_versions WHERE id=? FOR UPDATE', [(int)$policyId]);
        if (!$policy) throw new AkApiIncentivePolicyException('Policy was not found.', 404, 'not_found');
        if ($policy['status'] !== 'draft') throw new AkApiIncentivePolicyException('Only drafts can be edited.', 409, 'invalid_transition');
        $storedRules = dbFetchAll(
            'SELECT r.incentive_type, t.name_ar, t.name_en, t.is_active
             FROM hr_incentive_policy_rules r JOIN hr_incentive_types t ON t.type_code=r.incentive_type
             WHERE r.policy_version_id=? FOR UPDATE',
            [(int)$policyId]
        );
        if (!$storedRules) throw new AkApiIncentivePolicyException('Policy has no incentive categories.');
        $rulesInput = ak_api_incentive_policy_rules_input($input['rules'] ?? null);
        $enabledCount = 0;
        foreach ($storedRules as $stored) {
            $code = (string)$stored['incentive_type'];
            if (!isset($rulesInput[$code])) throw new AkApiIncentivePolicyException('A rule is required for every stored category.');
            $enabled = !empty($rulesInput[$code]['enabled']) ? 1 : 0;
            $amount = filter_var(trim((string)($rulesInput[$code]['amount'] ?? '')), FILTER_VALIDATE_FLOAT);
            if ($amount === false || !is_finite((float)$amount) || (float)$amount < 0 || (float)$amount > 9999999999.99) throw new AkApiIncentivePolicyException('Enter a valid non-negative amount for every category.');
            if ($enabled && (float)$amount <= 0) throw new AkApiIncentivePolicyException('Enabled incentive categories must have a positive amount.');
            if ($enabled && (int)$stored['is_active'] !== 1) throw new AkApiIncentivePolicyException('Inactive categories cannot be enabled.');
            if ($enabled) $enabledCount++;
            $pdo->prepare('UPDATE hr_incentive_policy_rules SET is_enabled=?, reward_amount=? WHERE policy_version_id=? AND incentive_type=?')
                ->execute([$enabled, round((float)$amount, 2), (int)$policyId, $code]);
        }
        if ($enabledCount < 1) throw new AkApiIncentivePolicyException('Enable at least one incentive category.');
        $pdo->prepare("UPDATE hr_incentive_policy_versions SET policy_name=?, effective_from=? WHERE id=? AND status='draft'")
            ->execute([$name, $effectiveFrom, (int)$policyId]);
        $fields = dbFetchAll('SELECT id, field_type, is_required, options_json FROM hr_incentive_policy_custom_fields WHERE policy_version_id=? FOR UPDATE', [(int)$policyId]);
        $customValues = is_array($input['custom_values'] ?? null) ? $input['custom_values'] : [];
        foreach ($fields as $field) {
            $fieldId = (int)$field['id'];
            $rawValue = (string)($customValues[(string)$fieldId] ?? $customValues[$fieldId] ?? '');
            $options = $field['options_json'] ? json_decode((string)$field['options_json'], true, 512, JSON_THROW_ON_ERROR) : [];
            $value = ak_api_incentive_policy_validate_field((string)$field['field_type'], $rawValue, is_array($options) ? $options : [], (int)$field['is_required'] === 1);
            $pdo->prepare('UPDATE hr_incentive_policy_custom_fields SET field_value=? WHERE id=? AND policy_version_id=?')
                ->execute([$value, $fieldId, (int)$policyId]);
        }
        $pdo->commit();
        ak_api_json(200, ['ok' => true, 'data' => ['policy_id' => (int)$policyId, 'status' => 'draft']]);
    }

    if ($action === 'submit_policy') {
        $policyId = filter_var($input['policy_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($policyId === false || $policyId <= 0) throw new AkApiIncentivePolicyException('A valid draft policy is required.');
        $pdo->beginTransaction();
        $policy = dbFetchOne('SELECT * FROM hr_incentive_policy_versions WHERE id=? FOR UPDATE', [(int)$policyId]);
        if (!$policy) throw new AkApiIncentivePolicyException('Policy was not found.', 404, 'not_found');
        if ($policy['status'] !== 'draft') throw new AkApiIncentivePolicyException('Only drafts can be submitted.', 409, 'invalid_transition');
        if ((string)$policy['effective_from'] < date('Y-m-d')) throw new AkApiIncentivePolicyException('A policy cannot be submitted with a past effective date.');
        $rules = dbFetchAll(
            'SELECT r.incentive_type, r.is_enabled, r.reward_amount, t.is_active
             FROM hr_incentive_policy_rules r JOIN hr_incentive_types t ON t.type_code=r.incentive_type
             WHERE r.policy_version_id=? FOR UPDATE',
            [(int)$policyId]
        );
        $enabledCount = 0;
        foreach ($rules as $rule) {
            if ((int)$rule['is_enabled'] === 1) {
                if ((float)$rule['reward_amount'] <= 0) throw new AkApiIncentivePolicyException('Enabled incentive categories must have a positive amount.');
                if ((int)$rule['is_active'] !== 1) throw new AkApiIncentivePolicyException('An enabled category is inactive.');
                $enabledCount++;
            }
        }
        if ($enabledCount < 1) throw new AkApiIncentivePolicyException('Enable at least one incentive category before submission.');
        $missing = dbFetchAll("SELECT id FROM hr_incentive_policy_custom_fields WHERE policy_version_id=? AND is_required=1 AND (field_value IS NULL OR TRIM(field_value)='')", [(int)$policyId]);
        if ($missing) throw new AkApiIncentivePolicyException('Required custom fields must be filled before submission.');
        $update = $pdo->prepare("UPDATE hr_incentive_policy_versions SET status='pending_gm' WHERE id=? AND status='draft'");
        $update->execute([(int)$policyId]);
        if ($update->rowCount() !== 1) throw new AkApiIncentivePolicyException('Policy status changed before submission.', 409, 'invalid_transition');
        $pdo->prepare("INSERT INTO hr_incentive_policy_approval_history (policy_version_id, action, actor_user_id) VALUES (?, 'submitted', ?)")
            ->execute([(int)$policyId, $userId]);
        $pdo->commit();
        $label = ak_api_incentive_policy_label($policy);
        ak_api_incentive_policy_notify_roles((int)$policyId, ['general_manager', 'gm'], 'سياسة حوافز بانتظار اعتمادك', 'أرسل مدير الموارد البشرية ' . $label . ' للاعتماد. يرجى مراجعة تفاصيل السياسة واتخاذ قرار الاعتماد أو الرفض.', 'hr_incentive_policy_submitted');
        ak_api_json(200, ['ok' => true, 'data' => ['policy_id' => (int)$policyId, 'status' => 'pending_gm']]);
    }

    if ($action === 'approve_policy') {
        $policyId = filter_var($input['policy_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($policyId === false || $policyId <= 0) throw new AkApiIncentivePolicyException('A valid policy is required.');
        $pdo->beginTransaction();
        $policy = dbFetchOne('SELECT id, status, policy_name, version_no FROM hr_incentive_policy_versions WHERE id=? FOR UPDATE', [(int)$policyId]);
        if (!$policy) throw new AkApiIncentivePolicyException('Policy was not found.', 404, 'not_found');
        if ($policy['status'] !== 'pending_gm') throw new AkApiIncentivePolicyException('Only policies awaiting GM review can be approved.', 409, 'invalid_transition');
        $update = $pdo->prepare("UPDATE hr_incentive_policy_versions SET status='approved' WHERE id=? AND status='pending_gm'");
        $update->execute([(int)$policyId]);
        if ($update->rowCount() !== 1) throw new AkApiIncentivePolicyException('Policy status changed before approval.', 409, 'invalid_transition');
        $pdo->prepare("INSERT INTO hr_incentive_policy_approval_history (policy_version_id, action, actor_user_id) VALUES (?, 'approved', ?)")
            ->execute([(int)$policyId, $userId]);
        $pdo->commit();
        $label = ak_api_incentive_policy_label($policy);
        ak_api_incentive_policy_notify_roles((int)$policyId, ['financial_manager', 'fm', 'finance'], 'سياسة حوافز معتمدة بانتظار التفعيل', $label . ' اعتمدها المدير العام وأصبحت متاحة لك للاختيار والتفعيل وفق تاريخ السريان.', 'hr_incentive_policy_approved_for_fm');
        ak_api_json(200, ['ok' => true, 'data' => ['policy_id' => (int)$policyId, 'status' => 'approved']]);
    }

    if ($action === 'reject_policy') {
        $policyId = filter_var($input['policy_id'] ?? 0, FILTER_VALIDATE_INT);
        $reason = trim((string)($input['reason'] ?? ''));
        if ($policyId === false || $policyId <= 0) throw new AkApiIncentivePolicyException('A valid policy is required.');
        if ($reason === '' || mb_strlen($reason) > 2000) throw new AkApiIncentivePolicyException('Rejection reason is required and must not exceed 2000 characters.');
        $pdo->beginTransaction();
        $policy = dbFetchOne('SELECT id, status, policy_name, version_no, created_by_user_id FROM hr_incentive_policy_versions WHERE id=? FOR UPDATE', [(int)$policyId]);
        if (!$policy) throw new AkApiIncentivePolicyException('Policy was not found.', 404, 'not_found');
        if ($policy['status'] !== 'pending_gm') throw new AkApiIncentivePolicyException('Only policies awaiting GM review can be rejected.', 409, 'invalid_transition');
        $pdo->prepare("INSERT INTO hr_incentive_policy_approval_history (policy_version_id, action, actor_user_id, reason) VALUES (?, 'rejected', ?, ?)")
            ->execute([(int)$policyId, $userId, $reason]);
        $update = $pdo->prepare("UPDATE hr_incentive_policy_versions SET status='draft' WHERE id=? AND status='pending_gm'");
        $update->execute([(int)$policyId]);
        if ($update->rowCount() !== 1) throw new AkApiIncentivePolicyException('Policy status changed during rejection.', 409, 'invalid_transition');
        $pdo->commit();
        $label = ak_api_incentive_policy_label($policy);
        ak_api_incentive_policy_notify_user((int)$policy['created_by_user_id'], (int)$policyId, 'رفض المدير العام سياسة الحوافز', 'رفض المدير العام ' . $label . '. سبب الرفض: ' . $reason . ' يمكنك مراجعة السبب وتعديل المسودة ثم إعادة إرسالها.', 'hr_incentive_policy_rejected');
        ak_api_json(200, ['ok' => true, 'data' => ['policy_id' => (int)$policyId, 'status' => 'draft']]);
    }

    if ($action === 'withdraw_approval_to_draft') {
        $policyId = filter_var($input['policy_id'] ?? 0, FILTER_VALIDATE_INT);
        $reason = trim((string)($input['reason'] ?? ''));
        if ($policyId === false || $policyId <= 0) throw new AkApiIncentivePolicyException('A valid policy is required.');
        if ($reason === '' || mb_strlen($reason) > 2000) throw new AkApiIncentivePolicyException('Withdrawal reason is required and must not exceed 2000 characters.');
        $pdo->beginTransaction();
        $policy = dbFetchOne('SELECT * FROM hr_incentive_policy_versions WHERE id=? FOR UPDATE', [(int)$policyId]);
        if (!$policy) throw new AkApiIncentivePolicyException('Policy was not found.', 404, 'not_found');
        if ($policy['status'] !== 'approved' || !empty($policy['activated_at']) || !empty($policy['activated_by_user_id'])) {
            throw new AkApiIncentivePolicyException('Only approved and never-activated policies can be returned to draft.', 409, 'invalid_transition');
        }
        $pdo->prepare("INSERT INTO hr_incentive_policy_approval_history (policy_version_id, action, actor_user_id, reason) VALUES (?, 'returned_to_draft', ?, ?)")
            ->execute([(int)$policyId, $userId, $reason]);
        $update = $pdo->prepare(
            "UPDATE hr_incentive_policy_versions SET status='draft'
             WHERE id=? AND status='approved' AND activated_at IS NULL AND activated_by_user_id IS NULL"
        );
        $update->execute([(int)$policyId]);
        if ($update->rowCount() !== 1) throw new AkApiIncentivePolicyException('Policy status changed during approval withdrawal.', 409, 'invalid_transition');
        $pdo->commit();
        $label = ak_api_incentive_policy_label($policy);
        ak_api_incentive_policy_notify_user((int)$policy['created_by_user_id'], (int)$policyId, 'سحب المدير العام اعتماد سياسة الحوافز', 'سحب المدير العام اعتماد ' . $label . '. السبب: ' . $reason . ' أعيدت السياسة إلى المسودة ويمكن لمدير الموارد البشرية تعديلها ثم إعادة إرسالها للاعتماد.', 'hr_incentive_policy_approval_withdrawn');
        ak_api_incentive_policy_notify_roles((int)$policyId, ['financial_manager', 'fm', 'finance'], 'سحب اعتماد سياسة حوافز', 'سحب المدير العام اعتماد ' . $label . ' وأعيدت إلى المسودة؛ لم تعد متاحة للتفعيل.', 'hr_incentive_policy_approval_withdrawn_for_fm');
        ak_api_json(200, ['ok' => true, 'data' => ['policy_id' => (int)$policyId, 'status' => 'draft']]);
    }

    if ($action === 'activate_policy') {
        $policyId = filter_var($input['policy_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($policyId === false || $policyId <= 0) throw new AkApiIncentivePolicyException('A valid approved policy is required.');
        $pdo->beginTransaction();
        $policy = dbFetchOne('SELECT * FROM hr_incentive_policy_versions WHERE id=? FOR UPDATE', [(int)$policyId]);
        if (!$policy) throw new AkApiIncentivePolicyException('Policy was not found.', 404, 'not_found');
        if ($policy['status'] !== 'approved') throw new AkApiIncentivePolicyException('Only GM-approved policies can be activated.', 409, 'invalid_transition');
        if ((string)$policy['effective_from'] < date('Y-m-d')) throw new AkApiIncentivePolicyException('A policy cannot be activated retroactively.');

        $ruleRows = dbFetchAll(
            'SELECT r.incentive_type, r.is_enabled, r.reward_amount, t.is_active
             FROM hr_incentive_policy_rules r JOIN hr_incentive_types t ON t.type_code=r.incentive_type
             WHERE r.policy_version_id=? FOR UPDATE',
            [(int)$policyId]
        );
        if (!$ruleRows) throw new AkApiIncentivePolicyException('Policy has no incentive categories.');
        $enabledCount = 0;
        foreach ($ruleRows as $rule) {
            if ((int)$rule['is_enabled'] === 1) {
                if ((float)$rule['reward_amount'] <= 0) throw new AkApiIncentivePolicyException('Enabled incentive categories must have a positive amount.');
                if ((int)$rule['is_active'] !== 1) throw new AkApiIncentivePolicyException('An enabled category is inactive.');
                $enabledCount++;
            }
        }
        if ($enabledCount < 1) throw new AkApiIncentivePolicyException('Enable at least one incentive category.');
        $missing = dbFetchAll(
            "SELECT id FROM hr_incentive_policy_custom_fields
             WHERE policy_version_id=? AND is_required=1 AND (field_value IS NULL OR TRIM(field_value)='')",
            [(int)$policyId]
        );
        if ($missing) throw new AkApiIncentivePolicyException('Required custom fields must be filled before activation.');

        $effectiveFrom = (string)$policy['effective_from'];
        $laterActive = dbFetchOne(
            "SELECT id FROM hr_incentive_policy_versions
             WHERE status='active' AND effective_from>=? ORDER BY effective_from ASC LIMIT 1 FOR UPDATE",
            [$effectiveFrom]
        );
        if ($laterActive) throw new AkApiIncentivePolicyException('Activate policies in effective-date order; a same-date or later active policy exists.', 409, 'effective_date_order');
        $prior = dbFetchAll(
            "SELECT id FROM hr_incentive_policy_versions
             WHERE status='active' AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?) FOR UPDATE",
            [$effectiveFrom, $effectiveFrom]
        );
        $closeDate = (new DateTimeImmutable($effectiveFrom))->modify('-1 day')->format('Y-m-d');
        foreach ($prior as $old) {
            dbExecute("UPDATE hr_incentive_policy_versions SET status='retired', effective_to=? WHERE id=?", [$closeDate, (int)$old['id']]);
        }
        $update = $pdo->prepare(
            "UPDATE hr_incentive_policy_versions SET status='active', activated_by_user_id=?, activated_at=NOW()
             WHERE id=? AND status='approved'"
        );
        $update->execute([$userId, (int)$policyId]);
        if ($update->rowCount() !== 1) throw new AkApiIncentivePolicyException('Policy status changed during activation.', 409, 'invalid_transition');
        $pdo->commit();

        $label = ak_api_incentive_policy_label($policy);
        ak_api_incentive_policy_notify_user((int)$policy['created_by_user_id'], (int)$policyId, 'فعّل المدير المالي سياسة الحوافز', 'فعّل المدير المالي ' . $label . ' وفق تاريخ السريان المعتمد.', 'hr_incentive_policy_activated_for_hr');
        $approver = null;
        try {
            $approver = dbFetchOne("SELECT actor_user_id FROM hr_incentive_policy_approval_history WHERE policy_version_id=? AND action='approved' ORDER BY id DESC LIMIT 1", [(int)$policyId]);
        } catch (Throwable $exception) {
            error_log('Mobile incentive-policy approver lookup failed: ' . $exception->getMessage());
        }
        ak_api_incentive_policy_notify_user((int)($approver['actor_user_id'] ?? 0), (int)$policyId, 'تم تفعيل سياسة الحوافز التي اعتمدتها', 'فعّل المدير المالي ' . $label . ' وفق تاريخ السريان المعتمد.', 'hr_incentive_policy_activated_for_gm');
        ak_api_json(200, ['ok' => true, 'data' => ['policy_id' => (int)$policyId, 'status' => 'active']]);
    }
} catch (AkApiIncentivePolicyException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ak_api_json($exception->httpStatus, ['ok' => false, 'error' => ['code' => $exception->errorCode, 'message' => $exception->getMessage()]]);
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_policy_request', 'message' => $exception->getMessage()]]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Mobile incentive-policy action failed: ' . $exception->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'policy_action_failed', 'message' => 'The incentive-policy action could not be completed.']]);
}
