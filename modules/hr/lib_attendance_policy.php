<?php
declare(strict_types=1);

function hrAttendancePolicyValidate(array $input): array
{
    $p = [
        'policy_name' => trim((string)($input['policy_name'] ?? '')),
        'effective_from' => trim((string)($input['effective_from'] ?? '')),
        'working_start_time' => trim((string)($input['working_start_time'] ?? '07:00')),
        'working_end_time' => trim((string)($input['working_end_time'] ?? '16:00')),
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
    if (!$date || $date->format('Y-m-d') !== $p['effective_from'] || $p['effective_from'] <= date('Y-m-d')) {
        throw new InvalidArgumentException('تاريخ السريان يجب أن يكون تاريخاً مستقبلياً صالحاً.');
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

function hrAttendancePolicyLoginEligible(array $policy, string $time): bool
{
    return (int)$policy['auto_login_attendance'] === 1
        && $time <= (string)$policy['attendance_cutoff_time'];
}

function hrAttendancePolicyAbsenceFinalizationReached(array $policy, string $time): bool
{
    return (int)$policy['auto_absence_enabled'] === 1
        && $time >= (string)$policy['absence_finalization_time'];
}
