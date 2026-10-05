<?php
declare(strict_types=1);

/**
 * CLI-only attendance absence finalizer.
 *
 * Normal production execution is intended to run from Windows Task Scheduler
 * after the effective policy's absence_finalization_time. No schema changes are
 * performed here.
 *
 * Optional:
 *   php tools/finalize_daily_attendance.php --dry-run
 *   php tools/finalize_daily_attendance.php --date=2026-10-05 --force
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/hr/lib_attendance_policy.php';
require_once __DIR__ . '/../modules/hr/lib_attendance_integrity.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$options = getopt('', ['date::', 'time::', 'dry-run', 'force']);
$selectedDate = (string)($options['date'] ?? date('Y-m-d'));
$selectedTime = (string)($options['time'] ?? date('H:i:s'));
$dryRun = array_key_exists('dry-run', $options);
$force = array_key_exists('force', $options);

$date = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate);
if (!$date || $date->format('Y-m-d') !== $selectedDate) {
    fwrite(STDERR, "Invalid date.\n");
    exit(2);
}

if (!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/', $selectedTime)) {
    fwrite(STDERR, "Invalid time.\n");
    exit(2);
}
if (strlen($selectedTime) === 5) $selectedTime .= ':00';

$pdo = db();
$policy = hrAttendancePolicyGetActive($pdo, $selectedDate);
if (!$policy) {
    fwrite(STDERR, "No attendance policy is effective for {$selectedDate}. Nothing finalized.\n");
    exit(0);
}

if (!$force && !hrAttendancePolicyAbsenceFinalizationReached($policy, $selectedTime)) {
    echo "Policy V" . (int)$policy['version_no'] .
        " finalization time is " . $policy['absence_finalization_time'] .
        "; current evaluation time is {$selectedTime}. Nothing finalized.\n";
    exit(0);
}

$employees = dbFetchAll(
    "SELECT e.id, e.full_name
     FROM employees e
     WHERE e.status = 'active'
       AND (
           SELECT s.category
           FROM hr_employee_state_history h
           INNER JOIN hr_employment_states s ON s.id = h.employment_state_id
           WHERE h.employee_id = e.id
             AND h.effective_from <= ?
             AND (h.effective_to IS NULL OR h.effective_to >= ?)
           ORDER BY h.effective_from DESC, h.id DESC
           LIMIT 1
       ) = 'working'
       AND (
           SELECT s.is_active
           FROM hr_employee_state_history h
           INNER JOIN hr_employment_states s ON s.id = h.employment_state_id
           WHERE h.employee_id = e.id
             AND h.effective_from <= ?
             AND (h.effective_to IS NULL OR h.effective_to >= ?)
           ORDER BY h.effective_from DESC, h.id DESC
           LIMIT 1
       ) = 1
     ORDER BY e.id",
    [$selectedDate . ' 23:59:59', $selectedDate . ' 00:00:00']
);

$eligible = [];
foreach ($employees as $employee) {
    $eligibility = hrAttendanceEligibility((int)$employee['id'], $selectedDate);
    if ($eligibility['eligible']) {
        $eligible[] = $employee;
    }
}

$created = 0;
$skippedExisting = 0;

foreach ($eligible as $employee) {
    $employeeId = (int)$employee['id'];
    $existing = dbFetchOne(
        "SELECT id, status, check_in
         FROM attendance
         WHERE employee_id = ? AND date = ?
         LIMIT 1",
        [$employeeId, $selectedDate]
    );

    if ($existing) {
        $skippedExisting++;
        continue;
    }

    if ($dryRun) {
        $created++;
        continue;
    }

    dbExecute(
        "INSERT INTO attendance
            (employee_id, date, status, work_mode, notes)
         VALUES (?, ?, 'absent', NULL, ?)",
        [
            $employeeId,
            $selectedDate,
            'غياب آلي — لم يتم تسجيل الحضور حتى ' . substr($policy['absence_finalization_time'], 0, 5)
        ]
    );

    try {
        dbExecute(
            "INSERT INTO audit_log
             (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent)
             VALUES (NULL, 'HR_ATTENDANCE_AUTO_ABSENCE', 'attendance', ?, ?, '', 'CLI')",
            [
                $employeeId,
                json_encode([
                    'date' => $selectedDate,
                    'status' => 'absent',
                    'policy_version' => (int)$policy['version_no'],
                    'finalized_at' => $selectedTime,
                    'source' => 'daily_absence_finalizer'
                ], JSON_UNESCAPED_UNICODE)
            ]
        );
    } catch (Throwable $e) {
        // The attendance record is the authoritative event; audit logging must
        // not roll it back after it has been successfully created.
    }

    $created++;
}

echo ($dryRun ? '[DRY RUN] ' : '') .
    "Attendance absence finalization completed. " .
    "date={$selectedDate}, policy=V" . (int)$policy['version_no'] .
    ", eligible=" . count($eligible) .
    ", absent_created={$created}, existing_skipped={$skippedExisting}.\n";
