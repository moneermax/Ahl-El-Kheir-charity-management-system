<?php
declare(strict_types=1);

function hrAttendancePolicyValidate(array $input): array
{
    $p = [
        'policy_name' => trim((string)($input['policy_name'] ?? '')),
        'effective_from' => trim((string)($input['effective_from'] ?? '')),
        'working_start_time' => trim((string)($input['working_start_time'] ?? '07:00')),
        'working_end_time' => trim((string)($input['working_end_time'] ?? '16:00')),
        'working_days' => trim((string)($input['working_days'] ?? '1,2,3,4,5')),
        'attendance_cutoff_time' => trim((string)($input['attendance_cutoff_time'] ?? '16:00')),
        'absence_finalization_time' => trim((string)($input['absence_finalization_time'] ?? '16:00')),
        'auto_login_attendance' => isset($input['auto_login_attendance']) ? 1 : 0,
        'auto_absence_enabled' => isset($input['auto_absence_enabled']) ? 1 : 0,
        'default_work_mode' => (string)($input['default_work_mode'] ?? 'remote'),
        'notes' => trim((string)($input['notes'] ?? '')) ?: null,
    ];

    if ($p['policy_name'] === '') {
        throw new InvalidArgumentException('اسم سياسة الحضور مطلوب.');
    }

    $date = DateTime::createFromFormat('!Y-m-d', $p['effective_from']);
    if (!$date || $date->format('Y-m-d') !== $p['effective_from'] || $p['effective_from'] < date('Y-m-d')) {
        throw new InvalidArgumentException('تاريخ السريان يجب أن يكون اليوم أو تاريخاً مستقبلياً صالحاً.');
    }

    foreach (['working_start_time', 'working_end_time', 'attendance_cutoff_time', 'absence_finalization_time'] as $key) {
        $value = $p[$key];
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
            throw new InvalidArgumentException('وقت السياسة غير صالح: ' . $key);
        }
        if (strlen($value) === 5) $p[$key] .= ':00';
    }

    if ($p['working_start_time'] >= $p['working_end_time']) {
        throw new InvalidArgumentException('وقت بداية العمل يجب أن يسبق وقت نهاية العمل.');
    }
    if (!preg_match('/^[1-7](?:,[1-7])*$/', $p['working_days']) || count(array_unique(explode(',', $p['working_days']))) !== count(explode(',', $p['working_days']))) {
        throw new InvalidArgumentException('أيام العمل يجب أن تكون أرقاماً فريدة من 1 إلى 7 مفصولة بفواصل.');
    }
    if ($p['attendance_cutoff_time'] > $p['working_end_time']) {
        throw new InvalidArgumentException('حد تسجيل الحضور لا يمكن أن يتجاوز نهاية ساعات العمل.');
    }
    if ($p['absence_finalization_time'] > $p['attendance_cutoff_time']) {
        throw new InvalidArgumentException('وقت إنهاء الغياب لا يمكن أن يتجاوز حد تسجيل الحضور.');
    }
    if (!in_array($p['default_work_mode'], ['remote', 'onsite', 'hybrid'], true)) {
        throw new InvalidArgumentException('نمط العمل الافتراضي غير صالح.');
    }

    return $p;
}

function hrAttendancePolicyGetActive(PDO $pdo, ?string $asOfDate = null): ?array
{
    $date = $asOfDate ?: date('Y-m-d');
    return dbFetchOne(
        "SELECT * FROM hr_attendance_policy_versions
         WHERE effective_from <= ?
         ORDER BY effective_from DESC, version_no DESC
         LIMIT 1",
        [$date]
    );
}

function hrAttendancePolicyGetAll(PDO $pdo): array
{
    return dbFetchAll(
        "SELECT p.*, u.full_name AS created_by_name
         FROM hr_attendance_policy_versions p
         LEFT JOIN users u ON u.id = p.created_by
         ORDER BY p.effective_from ASC, p.version_no ASC"
    );
}

function hrAttendancePolicyIsWorkingDay(array $policy, string $date): bool
{
    $day = (int)(new DateTimeImmutable($date))->format('N');
    return in_array($day, array_map('intval', explode(',', (string)$policy['working_days'])), true);
}

function hrAttendancePolicyLoginEligible(array $policy, string $time): bool
{
    return (int)$policy['auto_login_attendance'] === 1
        && $time >= (string)$policy['working_start_time']
        && $time <= (string)$policy['attendance_cutoff_time'];
}

function hrAttendancePolicyAbsenceFinalizationReached(array $policy, string $time): bool
{
    return (int)$policy['auto_absence_enabled'] === 1
        && $time >= (string)$policy['absence_finalization_time'];
}


function hrAttendanceEmployeeForUser(int $userId): ?array
{
    if ($userId <= 0) return null;

    return dbFetchOne(
        "SELECT e.id, e.user_id, e.full_name
         FROM employees e
         WHERE e.user_id = ? AND e.status = 'active'
         LIMIT 1",
        [$userId]
    );
}

function hrAttendanceAutoCheckInForUser(int $userId, ?DateTimeImmutable $now = null): bool
{
    $now = $now ?: new DateTimeImmutable('now');
    $policy = hrAttendancePolicyGetActive(db());
    if (!$policy) return false;

    $time = $now->format('H:i:s');
    if (!hrAttendancePolicyLoginEligible($policy, $time)) return false;
    if (!hrAttendancePolicyIsWorkingDay($policy, $now->format('Y-m-d'))) return false;

    $employee = hrAttendanceEmployeeForUser($userId);
    if (!$employee) return false;

    $date = $now->format('Y-m-d');
    $eligibility = hrAttendanceEligibility((int)$employee['id'], $date);
    if (!$eligibility['eligible']) return false;

    $mode = (string)$policy['default_work_mode'];
    $existing = dbFetchOne(
        "SELECT id, status, check_in
         FROM attendance
         WHERE employee_id = ? AND date = ?
         LIMIT 1",
        [(int)$employee['id'], $date]
    );

    if ($existing && (string)$existing['status'] === 'on_leave') {
        return false;
    }

    if ($existing && !empty($existing['check_in'])) {
        return false;
    }

    dbExecute(
        "INSERT INTO attendance
            (employee_id, date, check_in, work_mode, status, notes)
         VALUES (?, ?, ?, ?, 'present', NULL)
         ON DUPLICATE KEY UPDATE
            check_in = COALESCE(check_in, VALUES(check_in)),
            work_mode = COALESCE(work_mode, VALUES(work_mode)),
            status = 'present',
            notes = NULL",
        [(int)$employee['id'], $date, $time, $mode]
    );

    try {
        dbExecute(
            "INSERT INTO audit_log
             (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent)
             VALUES (?, 'HR_ATTENDANCE_AUTO_CHECK_IN', 'attendance', ?, ?, ?, ?)",
            [
                $userId,
                (int)$employee['id'],
                json_encode([
                    'date' => $date,
                    'check_in' => $time,
                    'work_mode' => $mode,
                    'policy_version' => (int)$policy['version_no'],
                    'source' => 'successful_login'
                ], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
    } catch (Throwable $e) {
        // Attendance remains authoritative; audit logging must not break login.
    }

    return true;
}
