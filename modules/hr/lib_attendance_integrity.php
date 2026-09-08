<?php
declare(strict_types=1);

/**
 * Canonical application-level attendance integrity rules.
 *
 * This helper is the PHP replacement for the former attendance employment-state
 * triggers. Attendance eligibility is determined from the employee's effective
 * HR state on the requested date and approved leave records.
 */
function hrAttendanceEmploymentState(int $employeeId, string $date): ?array
{
    return dbFetchOne(
        "SELECT h.employment_state_id, s.code, s.name_ar, s.name_en, s.category
         FROM hr_employee_state_history h
         INNER JOIN hr_employment_states s ON s.id = h.employment_state_id
         WHERE h.employee_id = ?
           AND h.effective_from <= ?
           AND (h.effective_to IS NULL OR h.effective_to >= ?)
         ORDER BY h.effective_from DESC, h.id DESC
         LIMIT 1",
        [$employeeId, $date . ' 23:59:59', $date . ' 00:00:00']
    );
}

function hrAttendanceApprovedLeave(int $employeeId, string $date): ?array
{
    return dbFetchOne(
        "SELECT id, employee_id, start_date, end_date, leave_type, status
         FROM leaves
         WHERE employee_id = ?
           AND status = 'hr_approved'
           AND start_date <= ?
           AND end_date >= ?
         ORDER BY start_date DESC, id DESC
         LIMIT 1",
        [$employeeId, $date, $date]
    );
}

function hrAttendanceHasReturnOverride(int $employeeId, string $date): bool
{
    return dbFetchOne(
        "SELECT id
         FROM attendance
         WHERE employee_id = ?
           AND date = ?
           AND status = 'absent'
           AND notes LIKE 'عودة من الإجازة%'
         LIMIT 1",
        [$employeeId, $date]
    ) !== null;
}

/**
 * Return the complete attendance eligibility state for one employee/date.
 * reason is one of: invalid_employee, no_state, non_working, approved_leave, eligible.
 */
function hrAttendanceEligibility(int $employeeId, string $date): array
{
    if ($employeeId <= 0) {
        return ['eligible' => false, 'reason' => 'invalid_employee', 'message' => 'الموظف المحدد غير صالح.'];
    }

    $employee = dbFetchOne(
        "SELECT id FROM employees WHERE id = ? AND status = 'active' LIMIT 1",
        [$employeeId]
    );
    if (!$employee) {
        return ['eligible' => false, 'reason' => 'invalid_employee', 'message' => 'الموظف غير موجود أو غير نشط.'];
    }

    $state = hrAttendanceEmploymentState($employeeId, $date);
    if (!$state) {
        return [
            'eligible' => false,
            'reason' => 'no_state',
            'state' => null,
            'message' => 'لا توجد حالة توظيف معتمدة لهذا الموظف في التاريخ المحدد.'
        ];
    }

    if (($state['category'] ?? '') !== 'working') {
        return [
            'eligible' => false,
            'reason' => 'non_working',
            'state' => $state,
            'message' => 'الموظف غير مؤهل لتسجيل الحضور في التاريخ المحدد وفق حالة التوظيف الحالية.'
        ];
    }

    $leave = hrAttendanceApprovedLeave($employeeId, $date);
    $returned = $leave !== null && hrAttendanceHasReturnOverride($employeeId, $date);

    if ($leave !== null && !$returned) {
        return [
            'eligible' => false,
            'reason' => 'approved_leave',
            'state' => $state,
            'leave' => $leave,
            'returned' => false,
            'message' => 'الموظف في إجازة معتمدة. يجب تنفيذ "عودة من الإجازة" أولاً قبل تسجيل أي إجراء حضور.'
        ];
    }

    return [
        'eligible' => true,
        'reason' => 'eligible',
        'state' => $state,
        'leave' => $leave,
        'returned' => $returned,
        'message' => null
    ];
}

function hrAttendanceRequireEligible(int $employeeId, string $date): void
{
    $result = hrAttendanceEligibility($employeeId, $date);
    if (!$result['eligible']) {
        $exceptionClass = ($result['reason'] ?? '') === 'approved_leave'
            ? RuntimeException::class
            : InvalidArgumentException::class;
        throw new $exceptionClass((string)$result['message']);
    }
}
