<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function ak_api_profile_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function ak_api_profile_payload(int $userId): array
{
    $row = dbFetchOne(
        "SELECT u.id, u.username, u.full_name, u.email, u.phone, u.address,
                u.birth_date, u.gender, u.avatar_path, r.name_ar AS role_name, r.code AS role_code
         FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1",
        [$userId]
    );
    if (!$row) ak_api_json(404, ['ok' => false, 'error' => ['code' => 'profile_not_found', 'message' => 'The account profile was not found.']]);
    $gender = (string)($row['gender'] ?? '');
    $gender = in_array($gender, ['male', 'ذكر'], true) ? 'ذكر' : (in_array($gender, ['female', 'أنثى'], true) ? 'أنثى' : '');
    return [
        'id' => (int)$row['id'], 'username' => (string)$row['username'], 'full_name' => (string)$row['full_name'],
        'email' => (string)($row['email'] ?? ''), 'phone' => (string)($row['phone'] ?? ''),
        'address' => (string)($row['address'] ?? ''), 'birth_date' => (string)($row['birth_date'] ?? ''),
        'gender' => $gender, 'role_code' => (string)$row['role_code'], 'role_name' => (string)($row['role_name'] ?? ''),
        'has_avatar' => trim((string)($row['avatar_path'] ?? '')) !== '',
    ];
}

if ($method === 'GET') ak_api_json(200, ['ok' => true, 'data' => ['profile' => ak_api_profile_payload($userId)]]);
ak_api_require_method('POST');
$input = ak_api_read_json();
if (($input['action'] ?? 'update_profile') !== 'update_profile') {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => 'Unsupported profile action.']]);
}
$fullName = trim((string)($input['full_name'] ?? ''));
$email = trim((string)($input['email'] ?? ''));
$phone = trim((string)($input['phone'] ?? ''));
$address = trim((string)($input['address'] ?? ''));
$birth = trim((string)($input['birth_date'] ?? ''));
$genderInput = trim((string)($input['gender'] ?? ''));
$gender = match ($genderInput) {
    '', 'null' => null, 'ذكر', 'male' => 'ذكر', 'أنثى', 'female' => 'أنثى', default => '__invalid__',
};
if ($fullName === '' || ak_api_profile_length($fullName) > 150) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_full_name', 'message' => 'Enter a full name of at most 150 characters.']]);
}
if (ak_api_profile_length($email) > 190 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_email', 'message' => 'Enter a valid email address.']]);
}
if (ak_api_profile_length($phone) > 30) ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_phone', 'message' => 'The phone number is too long.']]);
if (ak_api_profile_length($address) > 255) ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_address', 'message' => 'The address is too long.']]);
if ($gender === '__invalid__') ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_gender', 'message' => 'Choose a supported gender value.']]);
$birthValue = null;
if ($birth !== '') {
    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $birth);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$birthDate || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $birthDate->format('Y-m-d') !== $birth) {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_birth_date', 'message' => 'Enter a valid birth date in YYYY-MM-DD format.']]);
    }
    $birthValue = $birth;
}
$emailValue = $email !== '' ? $email : null;
$phoneValue = $phone !== '' ? $phone : null;
$addressValue = $address !== '' ? $address : null;
if ($emailValue !== null && dbFetchOne('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$emailValue, $userId])) {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'email_in_use', 'message' => 'This email address is already used by another account.']]);
}
$old = dbFetchOne('SELECT full_name, email, phone, address, birth_date, gender FROM users WHERE id = ? LIMIT 1', [$userId]);
if (!$old) ak_api_json(404, ['ok' => false, 'error' => ['code' => 'profile_not_found', 'message' => 'The account profile was not found.']]);
$pdo = db();
try {
    $pdo->beginTransaction();
    dbExecute('UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, birth_date = ?, gender = ? WHERE id = ?', [$fullName, $emailValue, $phoneValue, $addressValue, $birthValue, $gender, $userId]);
    require_once dirname(__DIR__, 3) . '/modules/hr/lib_employee_provisioning.php';
    $employeeGender = $gender === 'ذكر' ? 'male' : ($gender === 'أنثى' ? 'female' : null);
    hrSyncEmployeeIdentityFromUser($pdo, $userId, [
        'full_name' => $fullName, 'email' => $emailValue, 'phone' => $phoneValue, 'address' => $addressValue,
        'birth_date' => $birthValue, 'gender' => $employeeGender,
    ]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Mobile self-profile update failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'profile_update_failed', 'message' => 'The profile could not be saved. Please try again.']]);
}
try {
    dbExecute(
        "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
         VALUES (?, 'UPDATE', 'users', ?, ?, ?, ?, ?)",
        [
            $userId, $userId, json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode(['full_name' => $fullName, 'email' => $emailValue, 'phone' => $phoneValue, 'address' => $addressValue, 'birth_date' => $birthValue, 'gender' => $gender], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (string)($_SERVER['REMOTE_ADDR'] ?? ''), substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]
    );
} catch (Throwable $auditError) { /* Preserve the existing web profile behavior if audit logging fails. */ }
ak_api_json(200, ['ok' => true, 'data' => ['profile' => ak_api_profile_payload($userId), 'message' => 'Profile saved successfully.']]);
