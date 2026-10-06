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
         WHERE effective_from <= ? AND absence_enabled = 1
         ORDER BY effective_from DESC, version_no DESC LIMIT 1",
        [date('Y-m-d')]
    );
    if (!$payPolicy) fail_test('No payroll policy with absence deduction enabled is available.');

    $employee = dbFetchOne(
        "SELECT e.id, e.full_name, s.basic_salary
         FROM employees e
         JOIN hr_employee_salary_history s ON s.id = (
             SELECT s2.id FROM hr_employee_salary_history s2
             WHERE s2.employee_id=e.id AND s2.effective_from<=?
               AND (s2.effective_to IS NULL OR s2.effective_to>=?)
             ORDER BY s2.effective_from DESC,s2.id DESC LIMIT 1
         )
         WHERE e.status='active' AND s.basic_salary>0
         ORDER BY e.id LIMIT 1",
        [date('Y-m-d'), date('Y-m-d')]
    );
    if (!$employee) fail_test('No active employee with valid salary history is available.');

    $candidate = null;
    $base = new DateTimeImmutable(date('Y-m-01'));

    for ($m=0; $m<18 && !$candidate; $m++) {
        $periodStart=$base->modify("+{$m} month")->format('Y-m-01');
        $periodEnd=date('Y-m-t',strtotime($periodStart));
        $month=(int)date('n',strtotime($periodStart));
        $year=(int)date('Y',strtotime($periodStart));

        if (dbFetchOne("SELECT id FROM payroll WHERE employee_id=? AND month=? AND year=? LIMIT 1",
            [(int)$employee['id'],$month,$year])) continue;

        $linkedPolicy=dbFetchOne(
            "SELECT * FROM hr_payroll_policy_versions WHERE id=? AND effective_from<=? LIMIT 1",
            [(int)$payPolicy['id'],$periodEnd]
        );
        if (!$linkedPolicy) continue;

        $d=new DateTimeImmutable($periodStart);
        $end=new DateTimeImmutable($periodEnd);
        while($d<=$end){
            $day=$d->format('Y-m-d');
            $ap=hrAttendancePolicyGetActive($pdo,$day);
            $state=hrAttendanceEmploymentState((int)$employee['id'],$day);
            $leave=hrAttendanceApprovedLeave((int)$employee['id'],$day);
            $att=dbFetchOne("SELECT id FROM attendance WHERE employee_id=? AND date=? LIMIT 1",
                [(int)$employee['id'],$day]);

            if($ap && hrAttendancePolicyIsWorkingDay($ap,$day)
                && $state && ($state['category']??'')==='working'
                && !$leave && !$att){
                $candidate=[
                    'periodStart'=>$periodStart,'periodEnd'=>$periodEnd,
                    'month'=>$month,'year'=>$year,'date'=>$day,
                    'payPolicy'=>$linkedPolicy,'attendancePolicy'=>$ap
                ];
                break;
            }
            $d=$d->modify('+1 day');
        }
    }

    if(!$candidate) fail_test('Could not find a rollback-safe payroll period and working attendance date.');

    $basic=money((float)$employee['basic_salary']);
    $rounding=(int)$candidate['payPolicy']['rounding_decimals'];
    $expected=money(round(
        ($basic/30)*((float)$candidate['payPolicy']['absence_deduction_percent']/100),
        $rounding
    ));
    if($expected<=0) fail_test('Selected absence policy produces zero deduction.');

    $pdo->prepare(
        "INSERT INTO payroll
         (employee_id,month,year,basic_salary,allowances,overtime,deductions,net_salary,
          status,payroll_policy_version_id,attendance_deduction,salary_advance_deduction)
         VALUES (?,?,?, ?,0,0,0,?,'draft',?,0,0)"
    )->execute([
        (int)$employee['id'],$candidate['month'],$candidate['year'],$basic,$basic,
        (int)$candidate['payPolicy']['id']
    ]);
    $payrollId=(int)$pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO attendance
         (employee_id,date,check_in,check_out,work_mode,status,notes)
         VALUES (?,?,NULL,NULL,'onsite','absent','ROLLBACK_TEST_ATTENDANCE_PAYROLL')"
    )->execute([(int)$employee['id'],$candidate['date']]);

    hrPayrollRefreshAttendanceDeductionDraft($pdo,$payrollId);
    hrSalaryAdvancePayrollRefreshDraft($pdo,$payrollId);

    $row=dbFetchOne(
        "SELECT attendance_deduction,salary_advance_deduction,net_salary
         FROM payroll WHERE id=? LIMIT 1",[$payrollId]
    );
    $actualDeduction=money((float)$row['attendance_deduction']);
    $actualNet=money((float)$row['net_salary']);
    $expectedNet=money($basic-$expected);

    if($actualDeduction!==$expected)
        fail_test("Attendance deduction mismatch: expected {$expected}, got {$actualDeduction}.");
    if($actualNet!==$expectedNet)
        fail_test("Net salary mismatch: expected {$expectedNet}, got {$actualNet}.");
    if(money((float)$row['salary_advance_deduction'])!==0.00)
        fail_test('Unexpected salary-advance deduction in isolated attendance test.');

    $pdo->prepare(
        "DELETE FROM attendance
         WHERE employee_id=? AND date=? AND notes='ROLLBACK_TEST_ATTENDANCE_PAYROLL'"
    )->execute([(int)$employee['id'],$candidate['date']]);

    hrPayrollRefreshAttendanceDeductionDraft($pdo,$payrollId);
    hrSalaryAdvancePayrollRefreshDraft($pdo,$payrollId);

    $row=dbFetchOne("SELECT attendance_deduction,net_salary FROM payroll WHERE id=? LIMIT 1",[$payrollId]);
    if(money((float)$row['attendance_deduction'])!==0.00)
        fail_test('Missing attendance row was treated as an absence.');
    if(money((float)$row['net_salary'])!==$basic)
        fail_test("Missing-attendance net salary mismatch: expected {$basic}, got ".money((float)$row['net_salary']).".");

    $pdo->rollBack();

    echo "PASS | Attendance/payroll integration | employee={$employee['id']} | period={$candidate['year']}-"
        .str_pad((string)$candidate['month'],2,'0',STR_PAD_LEFT)
        ." | absent_date={$candidate['date']} | attendance_deduction={$actualDeduction}"
        ." | net_salary={$actualNet} | missing_attendance_deduction=0.00 | rollback=PASS\n";
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,"FAIL | Attendance/payroll integration | ".$e->getMessage()."\n");
    exit(1);
}
