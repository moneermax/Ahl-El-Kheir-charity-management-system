<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../modules/hr/lib_attendance_policy.php';
require_once __DIR__ . '/../modules/hr/lib_attendance_integrity.php';
require_once __DIR__ . '/../modules/hr/lib_payroll_policy.php';
require_once __DIR__ . '/../modules/hr/lib_salary_advance_payroll.php';

$pdo=db();
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
function fail_test(string $m): never { throw new RuntimeException($m); }
function money(float $v): float { return round($v,2); }

try {
    $pdo->beginTransaction();

    /* 1) Effective-date selection for both policy families. */
    $pv=(int)(dbFetchOne("SELECT COALESCE(MAX(version_no),0) v FROM hr_payroll_policy_versions")['v']??0);
    $av=(int)(dbFetchOne("SELECT COALESCE(MAX(version_no),0) v FROM hr_attendance_policy_versions")['v']??0);
    $payIns=$pdo->prepare("INSERT INTO hr_payroll_policy_versions
        (version_no,effective_from,absence_enabled,absence_deduction_percent,
         unpaid_leave_enabled,unpaid_leave_deduction_percent,paid_leave_deduction_percent,
         late_enabled,early_departure_enabled,overtime_enabled,overtime_multiplier,
         daily_deduction_method,rounding_decimals,notes,created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $payIns->execute([$pv+1,'2098-01-01',1,80,1,70,0,0,0,0,1,'monthly_salary_div_30',2,'ROLLBACK_TEST_POLICY',null]);
    $p1=(int)$pdo->lastInsertId();
    $payIns->execute([$pv+2,'2098-02-01',1,90,1,60,0,0,0,0,1,'monthly_salary_div_30',2,'ROLLBACK_TEST_POLICY',null]);
    $p2=(int)$pdo->lastInsertId();

    $attIns=$pdo->prepare("INSERT INTO hr_attendance_policy_versions
        (version_no,policy_name,effective_from,working_start_time,working_end_time,working_days,
         attendance_cutoff_time,absence_finalization_time,auto_login_attendance,auto_absence_enabled,
         default_work_mode,notes,created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $attIns->execute([$av+1,'ROLLBACK A','2098-01-01','07:00:00','16:00:00','1,2,3,4,5','16:00:00','16:00:00',1,1,'remote','ROLLBACK_TEST_POLICY',null]);
    $a1=(int)$pdo->lastInsertId();
    $attIns->execute([$av+2,'ROLLBACK B','2098-02-01','08:00:00','17:00:00','1,2,3,4,5','17:00:00','17:00:00',1,1,'onsite','ROLLBACK_TEST_POLICY',null]);
    $a2=(int)$pdo->lastInsertId();

    if((int)hrPayrollPolicyGetActive($pdo,'2098-01-15')['id']!==$p1 ||
       (int)hrPayrollPolicyGetActive($pdo,'2098-02-15')['id']!==$p2) fail_test('Payroll policy effective-date selection failed.');
    if((int)hrAttendancePolicyGetActive($pdo,'2098-01-15')['id']!==$a1 ||
       (int)hrAttendancePolicyGetActive($pdo,'2098-02-15')['id']!==$a2) fail_test('Attendance policy effective-date selection failed.');

    /* 2) Real salary-advance fixture: attendance reduces net_before_advance. */
    $adv=dbFetchOne("SELECT r.id request_id,r.employee_id,r.request_no,s.scheduled_month,s.id schedule_id,
        s.scheduled_amount,s.applied_amount,p.eligible_salary_basis
        FROM hr_salary_advance_repayment_schedule s
        JOIN hr_salary_advance_requests r ON r.id=s.salary_advance_request_id
        JOIN hr_salary_advance_policy_versions p ON p.id=r.policy_version_id
        WHERE r.status='disbursed' AND COALESCE(r.outstanding_balance,0)>0
          AND s.status IN ('pending','partial') AND p.eligible_salary_basis='net_before_advance'
          AND ROUND(GREATEST(0,s.scheduled_amount-COALESCE(s.applied_amount,0)),2)>0
          AND NOT EXISTS (SELECT 1 FROM payroll x WHERE x.employee_id=r.employee_id
                          AND x.month=MONTH(s.scheduled_month) AND x.year=YEAR(s.scheduled_month))
        ORDER BY s.scheduled_month,s.id LIMIT 1");
    if(!$adv) fail_test('No rollback-safe net_before_advance salary-advance schedule is available.');

    $period=(string)$adv['scheduled_month']; $start=$period; $end=date('Y-m-t',strtotime($start));
    $month=(int)date('n',strtotime($start)); $year=(int)date('Y',strtotime($start));
    $pp=hrPayrollPolicyGetActive($pdo,$end);
    $sal=dbFetchOne("SELECT basic_salary FROM hr_employee_salary_history
        WHERE employee_id=? AND basic_salary>0 AND effective_from<=?
          AND (effective_to IS NULL OR effective_to>=?)
        ORDER BY effective_from DESC,id DESC LIMIT 1",[(int)$adv['employee_id'],$end,$end]);
    if(!$pp||!$sal) fail_test('Could not resolve payroll policy/salary for salary-advance integration.');

    $basic=money((float)$sal['basic_salary']); $absenceDate=null;
    for($d=new DateTimeImmutable($start),$e=new DateTimeImmutable($end);$d<=$e;$d=$d->modify('+1 day')){
        $day=$d->format('Y-m-d'); $ap=hrAttendancePolicyGetActive($pdo,$day);
        $state=hrAttendanceEmploymentState((int)$adv['employee_id'],$day);
        $leave=hrAttendanceApprovedLeave((int)$adv['employee_id'],$day);
        $row=dbFetchOne("SELECT id FROM attendance WHERE employee_id=? AND date=? LIMIT 1",[(int)$adv['employee_id'],$day]);
        if($ap&&hrAttendancePolicyIsWorkingDay($ap,$day)&&$state&&($state['category']??'')==='working'&&!$leave&&!$row){$absenceDate=$day;break;}
    }
    if(!$absenceDate) fail_test('No clean working day exists for the salary-advance integration fixture.');

    $pdo->prepare("INSERT INTO payroll
        (employee_id,month,year,basic_salary,allowances,overtime,deductions,net_salary,status,payroll_policy_version_id,attendance_deduction,salary_advance_deduction)
        VALUES (?,?,?,?,0,0,0,?,'draft',?,0,0)")
        ->execute([(int)$adv['employee_id'],$month,$year,$basic,$basic,(int)$pp['id']]);
    $payrollId=(int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO attendance(employee_id,date,work_mode,status,notes)
        VALUES (?,?,'onsite','absent','ROLLBACK_TEST_ATTENDANCE_ADVANCE')")
        ->execute([(int)$adv['employee_id'],$absenceDate]);

    hrPayrollRefreshAttendanceDeductionDraft($pdo,$payrollId);
    $draft=dbFetchOne("SELECT * FROM payroll WHERE id=? LIMIT 1",[$payrollId]);
    $attendanceDeduction=money((float)$draft['attendance_deduction']);
    if($attendanceDeduction<=0) fail_test('Salary-advance combined fixture produced no attendance deduction.');

    $preview=hrSalaryAdvancePayrollCalculateDraft($pdo,$draft,$start);
    $advanceDeduction=money((float)$preview['total']);
    $alloc=$preview['allocations'][0]??null;
    if(!$alloc||$advanceDeduction<=0) fail_test('Salary-advance combined fixture produced no repayment deduction.');

    $expectedEligible=money($basic-(float)$draft['deductions']-$attendanceDeduction);
    if(money((float)$alloc['eligible_salary'])!==$expectedEligible)
        fail_test('net_before_advance eligible salary did not subtract attendance deduction.');

    hrSalaryAdvancePayrollRefreshDraft($pdo,$payrollId);
    $after=dbFetchOne("SELECT attendance_deduction,salary_advance_deduction,net_salary FROM payroll WHERE id=? LIMIT 1",[$payrollId]);
    $expectedNet=money($basic-$attendanceDeduction-$advanceDeduction);
    if(money((float)$after['salary_advance_deduction'])!==$advanceDeduction)
        fail_test('Stored salary-advance deduction differs from preview.');
    if(money((float)$after['net_salary'])!==$expectedNet)
        fail_test('Net salary did not subtract attendance plus salary-advance deductions.');

    /* 3) Paid payroll immutability: exact draft-only update condition must affect 0 rows. */
    $pdo->prepare("INSERT INTO payroll
        (employee_id,month,year,basic_salary,allowances,overtime,deductions,net_salary,status,payroll_policy_version_id,attendance_deduction,salary_advance_deduction)
        VALUES (?,?,?,?,?,?,?,?, 'paid',?,0,0)")
        ->execute([(int)$adv['employee_id'],12,2097,1234.56,10,0,0,1244.56,(int)$pp['id']]);
    $paidId=(int)$pdo->lastInsertId();
    $before=dbFetchOne("SELECT status,allowances,overtime,deductions,net_salary FROM payroll WHERE id=?",[$paidId]);
    $u=$pdo->prepare("UPDATE payroll SET allowances=?,overtime=?,deductions=?,net_salary=? WHERE id=? AND status='draft'");
    $u->execute([999,999,999,1,$paidId]);
    if($u->rowCount()!==0) fail_test('Paid payroll was modified through the draft-only update path.');
    $afterPaid=dbFetchOne("SELECT status,allowances,overtime,deductions,net_salary FROM payroll WHERE id=?",[$paidId]);
    if(json_encode($before)!==json_encode($afterPaid)) fail_test('Paid payroll values changed.');

    $pdo->rollBack();
    echo "PASS | Attendance/payroll extended integration"
        ." | policy_effective_dates=PASS"
        ." | payroll_versions={$p1},{$p2}"
        ." | attendance_versions={$a1},{$a2}"
        ." | net_before_advance=PASS"
        ." | employee={$adv['employee_id']}"
        ." | request={$adv['request_no']}"
        ." | period={$year}-".str_pad((string)$month,2,'0',STR_PAD_LEFT)
        ." | absence_date={$absenceDate}"
        ." | attendance_deduction={$attendanceDeduction}"
        ." | salary_advance_deduction={$advanceDeduction}"
        ." | net_salary={$expectedNet}"
        ." | paid_payroll_immutable=PASS"
        ." | rollback=PASS\n";
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,"FAIL | Attendance/payroll extended integration | ".$e->getMessage()."\n");
    exit(1);
}
