<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/hr/lib_attendance_policy.php';
require_once __DIR__ . '/../modules/hr/lib_attendance_integrity.php';
require_once __DIR__ . '/../modules/hr/lib_payroll_policy.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_payroll.php';

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function fail_test(string $message): never { throw new RuntimeException($message); }
function money(float $v): float { return round($v, 2); }

try {
    $pdo->beginTransaction();

    $payPolicy = dbFetchOne(
        "SELECT * FROM hr_payroll_policy_versions
         WHERE effective_from <= ?
         ORDER BY effective_from DESC, version_no DESC
         LIMIT 1",
        [date('Y-m-d')]
    );
    if (!$payPolicy) {
        fail_test('No effective payroll policy is available.');
    }

    $employee = dbFetchOne(
        "SELECT e.id, e.full_name, s.basic_salary
         FROM employees e
         JOIN hr_employee_salary_history s ON s.id = (
             SELECT s2.id
             FROM hr_employee_salary_history s2
             WHERE s2.employee_id = e.id
               AND s2.effective_from <= ?
               AND (s2.effective_to IS NULL OR s2.effective_to >= ?)
             ORDER BY s2.effective_from DESC, s2.id DESC
             LIMIT 1
         )
         WHERE e.status = 'active'
           AND s.basic_salary > 0
         ORDER BY e.id
         LIMIT 1",
        [date('Y-m-d'), date('Y-m-d')]
    );
    if (!$employee) {
        fail_test('No active employee with valid salary history is available.');
    }

    /*
     * Find a rollback-safe future/current payroll period with:
     * - one clean working day for explicit absence
     * - one clean working day for approved paid leave
     * - one clean working day for approved unpaid leave
     * - one clean non-working day for an explicit absent row
     *
     * Existing payroll, attendance, and approved leave rows are never reused.
     */
    $candidate = null;
    $base = new DateTimeImmutable(date('Y-m-01'));

    for ($m = 0; $m < 18 && !$candidate; $m++) {
        $periodStart = $base->modify("+{$m} month")->format('Y-m-01');
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        $month = (int)date('n', strtotime($periodStart));
        $year = (int)date('Y', strtotime($periodStart));

        if (dbFetchOne(
            "SELECT id FROM payroll WHERE employee_id = ? AND month = ? AND year = ? LIMIT 1",
            [(int)$employee['id'], $month, $year]
        )) {
            continue;
        }

        $linkedPolicy = dbFetchOne(
            "SELECT * FROM hr_payroll_policy_versions
             WHERE id = ? AND effective_from <= ?
             LIMIT 1",
            [(int)$payPolicy['id'], $periodEnd]
        );
        if (!$linkedPolicy) {
            continue;
        }

        $working = [];
        $nonWorking = [];
        $d = new DateTimeImmutable($periodStart);
        $end = new DateTimeImmutable($periodEnd);

        while ($d <= $end) {
            $day = $d->format('Y-m-d');
            $attendancePolicy = hrAttendancePolicyGetActive($pdo, $day);
            $state = hrAttendanceEmploymentState((int)$employee['id'], $day);
            $leave = hrAttendanceApprovedLeave((int)$employee['id'], $day);
            $attendance = dbFetchOne(
                "SELECT id FROM attendance WHERE employee_id = ? AND date = ? LIMIT 1",
                [(int)$employee['id'], $day]
            );

            if ($attendancePolicy && $state && ($state['category'] ?? '') === 'working'
                && !$leave && !$attendance) {
                if (hrAttendancePolicyIsWorkingDay($attendancePolicy, $day)) {
                    $working[] = $day;
                } else {
                    $nonWorking[] = $day;
                }
            }

            $d = $d->modify('+1 day');
        }

        if (count($working) >= 3 && count($nonWorking) >= 1) {
            $candidate = [
                'periodStart' => $periodStart,
                'periodEnd' => $periodEnd,
                'month' => $month,
                'year' => $year,
                'payPolicy' => $linkedPolicy,
                'absence_date' => $working[0],
                'paid_leave_date' => $working[1],
                'unpaid_leave_date' => $working[2],
                'non_working_absent_date' => $nonWorking[0],
            ];
        }
    }

    if (!$candidate) {
        fail_test('Could not find a rollback-safe payroll period with the required working/non-working dates.');
    }

    $basic = money((float)$employee['basic_salary']);
    $rounding = (int)$candidate['payPolicy']['rounding_decimals'];

    $expectedAbsence = money(round(
        ($basic / 30) * ((float)$candidate['payPolicy']['absence_deduction_percent'] / 100),
        $rounding
    ));
    $expectedUnpaid = (int)$candidate['payPolicy']['unpaid_leave_enabled'] === 1
        ? money(round(
            ($basic / 30) * ((float)$candidate['payPolicy']['unpaid_leave_deduction_percent'] / 100),
            $rounding
        ))
        : 0.00;

    if ((int)$candidate['payPolicy']['absence_enabled'] === 1 && $expectedAbsence <= 0.00) {
        fail_test('Configured absence policy is enabled but produces zero deduction.');
    }

    /*
     * Create one temporary draft payroll. All fixtures are inside one
     * transaction and are rolled back at the end.
     */
    $pdo->prepare(
        "INSERT INTO payroll
         (employee_id, month, year, basic_salary, allowances, overtime, deductions,
          net_salary, status, payroll_policy_version_id, attendance_deduction,
          salary_advance_deduction)
         VALUES (?, ?, ?, ?, 0, 0, 0, ?, 'draft', ?, 0, 0)"
    )->execute([
        (int)$employee['id'],
        $candidate['month'],
        $candidate['year'],
        $basic,
        $basic,
        (int)$candidate['payPolicy']['id']
    ]);
    $payrollId = (int)$pdo->lastInsertId();

    /*
     * Working-day explicit absence.
     */
    $attendanceInsert = $pdo->prepare(
        "INSERT INTO attendance
         (employee_id, date, check_in, check_out, work_mode, status, notes)
         VALUES (?, ?, NULL, NULL, 'onsite', 'absent', 'ROLLBACK_TEST_ATTENDANCE_PAYROLL')"
    );
    $attendanceInsert->execute([
        (int)$employee['id'],
        $candidate['absence_date']
    ]);

    /*
     * Approved paid and unpaid leave. Leave rows are enough for the
     * calculation engine; no attendance row is created for these fixtures.
     */
    $leaveInsert = $pdo->prepare(
        "INSERT INTO leaves
         (employee_id, leave_type, start_date, end_date, days_count, reason, status)
         VALUES (?, ?, ?, ?, 1, 'ROLLBACK_TEST_ATTENDANCE_PAYROLL', 'hr_approved')"
    );
    $leaveInsert->execute([
        (int)$employee['id'],
        'annual',
        $candidate['paid_leave_date'],
        $candidate['paid_leave_date']
    ]);
    $paidLeaveId = (int)$pdo->lastInsertId();

    $leaveInsert->execute([
        (int)$employee['id'],
        'unpaid',
        $candidate['unpaid_leave_date'],
        $candidate['unpaid_leave_date']
    ]);
    $unpaidLeaveId = (int)$pdo->lastInsertId();

    /*
     * Explicit absent row on a non-working day. The integration must ignore
     * it because attendance policy, not the row alone, determines whether
     * the calendar date is payroll-relevant.
     */
    $attendanceInsert->execute([
        (int)$employee['id'],
        $candidate['non_working_absent_date']
    ]);

    $calculation = hrPayrollAttendanceDeductionCalculate(
        $pdo,
        [
            'employee_id' => (int)$employee['id'],
            'basic_salary' => $basic,
            'payroll_policy_version_id' => (int)$candidate['payPolicy']['id'],
        ],
        $candidate['periodStart'],
        $candidate['periodEnd']
    );

    $rows = $calculation['rows'] ?? [];
    $rowByType = [];
    foreach ($rows as $row) {
        $rowByType[(string)$row['type'] . ':' . (string)$row['date']] = $row;
    }

    if ((int)$candidate['payPolicy']['absence_enabled'] === 1) {
        $key = 'absence:' . $candidate['absence_date'];
        if (!isset($rowByType[$key])) {
            fail_test('Explicit working-day absence was not included in the deduction rows.');
        }
        if (money((float)$rowByType[$key]['amount']) !== $expectedAbsence) {
            fail_test('Explicit absence amount mismatch.');
        }
    }

    if ((int)$candidate['payPolicy']['unpaid_leave_enabled'] === 1 && $expectedUnpaid > 0.00) {
        $key = 'unpaid_leave:' . $candidate['unpaid_leave_date'];
        if (!isset($rowByType[$key])) {
            fail_test('Approved unpaid leave was not included in the deduction rows.');
        }
        if (money((float)$rowByType[$key]['amount']) !== $expectedUnpaid) {
            fail_test('Approved unpaid leave amount mismatch.');
        }
    }

    if (isset($rowByType['absence:' . $candidate['paid_leave_date'])
        || isset($rowByType['unpaid_leave:' . $candidate['paid_leave_date'])) {
        fail_test('Approved paid leave incorrectly produced an attendance deduction.');
    }

    if (isset($rowByType['absence:' . $candidate['non_working_absent_date'])) {
        fail_test('Explicit absence on a non-working day incorrectly produced a deduction.');
    }

    $expectedTotal = money(
        ((int)$candidate['payPolicy']['absence_enabled'] === 1 ? $expectedAbsence : 0.00)
        + $expectedUnpaid
    );

    if (money((float)$calculation['total']) !== $expectedTotal) {
        fail_test(
            "Combined attendance deduction mismatch: expected {$expectedTotal}, got "
            . money((float)$calculation['total']) . '.'
        );
    }

    hrPayrollRefreshAttendanceDeductionDraft($pdo, $payrollId);
    hrSalaryAdvancePayrollRefreshDraft($pdo, $payrollId);

    $row = dbFetchOne(
        "SELECT attendance_deduction, salary_advance_deduction, net_salary
         FROM payroll
         WHERE id = ?
         LIMIT 1",
        [$payrollId]
    );

    $actualDeduction = money((float)$row['attendance_deduction']);
    $actualNet = money((float)$row['net_salary']);
    $expectedNet = money($basic - $expectedTotal);

    if ($actualDeduction !== $expectedTotal) {
        fail_test("Stored attendance deduction mismatch: expected {$expectedTotal}, got {$actualDeduction}.");
    }
    if ($actualNet !== $expectedNet) {
        fail_test("Net salary mismatch: expected {$expectedNet}, got {$actualNet}.");
    }
    if (money((float)$row['salary_advance_deduction']) !== 0.00) {
        fail_test('Unexpected salary-advance deduction in isolated attendance test.');
    }

    /*
     * Remove all temporary attendance/leave facts and refresh the draft.
     * Missing attendance must not become an automatic absence.
     */
    $pdo->prepare(
        "DELETE FROM attendance
         WHERE employee_id = ?
           AND notes = 'ROLLBACK_TEST_ATTENDANCE_PAYROLL'
           AND date IN (?, ?, ?)"
    )->execute([
        (int)$employee['id'],
        $candidate['absence_date'],
        $candidate['non_working_absent_date'],
        $candidate['paid_leave_date']
    ]);

    /*
     * The unpaid/paid leave rows are temporary and will be removed before
     * the zero-state refresh. Explicit IDs make cleanup deterministic.
     */
    $pdo->prepare(
        "DELETE FROM leaves
         WHERE id IN (?, ?)
           AND employee_id = ?
           AND reason = 'ROLLBACK_TEST_ATTENDANCE_PAYROLL'"
    )->execute([
        $paidLeaveId,
        $unpaidLeaveId,
        (int)$employee['id']
    ]);

    hrPayrollRefreshAttendanceDeductionDraft($pdo, $payrollId);
    hrSalaryAdvancePayrollRefreshDraft($pdo, $payrollId);

    $row = dbFetchOne(
        "SELECT attendance_deduction, net_salary
         FROM payroll
         WHERE id = ?
         LIMIT 1",
        [$payrollId]
    );

    if (money((float)$row['attendance_deduction']) !== 0.00) {
        fail_test('Missing attendance/leave rows were treated as an absence.');
    }
    if (money((float)$row['net_salary']) !== $basic) {
        fail_test(
            "Missing-attendance net salary mismatch: expected {$basic}, got "
            . money((float)$row['net_salary']) . '.'
        );
    }

    $pdo->rollBack();

    echo "PASS | Attendance/payroll integration"
        . " | employee={$employee['id']}"
        . " | period={$candidate['year']}-" . str_pad((string)$candidate['month'], 2, '0', STR_PAD_LEFT)
        . " | absence_date={$candidate['absence_date']}"
        . " | paid_leave_date={$candidate['paid_leave_date']}"
        . " | unpaid_leave_date={$candidate['unpaid_leave_date']}"
        . " | non_working_absent_date={$candidate['non_working_absent_date']}"
        . " | absence_deduction=" . ($expectedAbsence)
        . " | unpaid_leave_deduction=" . $expectedUnpaid
        . " | total_attendance_deduction={$actualDeduction}"
        . " | net_salary={$actualNet}"
        . " | paid_leave=0.00"
        . " | non_working_absence=0.00"
        . " | missing_attendance=0.00"
        . " | rollback=PASS\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "FAIL | Attendance/payroll integration | " . $e->getMessage() . "\n");
    exit(1);
}
