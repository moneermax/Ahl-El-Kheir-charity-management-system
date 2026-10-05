<?php
declare(strict_types=1);

/**
 * Date-specific attendance calendar.
 * Weekly workdays remain in hr_attendance_policy_versions.
 * Confirmed calendar holidays override the weekly schedule for that date.
 */

function hrAttendanceHolidayForDate(PDO $pdo, string $date): ?array
{
    return dbFetchOne(
        "SELECT c.*, d.code AS definition_code
         FROM hr_holiday_calendar c
         LEFT JOIN hr_holiday_definitions d ON d.id = c.holiday_definition_id
         WHERE c.status = 'confirmed'
           AND c.start_date <= ?
           AND c.end_date >= ?
         ORDER BY c.start_date ASC, c.id ASC
         LIMIT 1",
        [$date, $date]
    );
}

function hrAttendanceIsHoliday(PDO $pdo, string $date): bool
{
    return hrAttendanceHolidayForDate($pdo, $date) !== null;
}

function hrAttendanceIsWorkingDate(PDO $pdo, array $policy, string $date): bool
{
    if (hrAttendanceIsHoliday($pdo, $date)) {
        return false;
    }

    return hrAttendancePolicyIsWorkingDay($policy, $date);
}
