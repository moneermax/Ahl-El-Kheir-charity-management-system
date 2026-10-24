<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 2) . '/modules/hr/lib_attendance_integrity.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if (!in_array((string)$user['role_code'], ['hr_manager','hr_staff','admin'], true)) {
 ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Attendance register access is limited to HR and system administrators.']]);
}
$date = (string)($_GET['date'] ?? date('Y-m-d'));
$dateObject = DateTime::createFromFormat('!Y-m-d', $date);
if (!$dateObject || $dateObject->format('Y-m-d') !== $date) ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_date','message'=>'Date must use YYYY-MM-DD format.']]);
$employees = dbFetchAll(
 "SELECT e.id,e.full_name,d.name_ar AS department_name
  FROM employees e LEFT JOIN departments d ON d.id=e.department_id
  INNER JOIN hr_employee_state_history h ON h.employee_id=e.id
   AND h.id=(SELECT h2.id FROM hr_employee_state_history h2 WHERE h2.employee_id=e.id
    AND h2.effective_from<=? AND (h2.effective_to IS NULL OR h2.effective_to>=?)
    ORDER BY h2.effective_from DESC,h2.id DESC LIMIT 1)
  INNER JOIN hr_employment_states s ON s.id=h.employment_state_id
  WHERE e.status='active' AND s.category='working' ORDER BY e.full_name",
 [$date.' 23:59:59',$date.' 00:00:00']
);
$rows = dbFetchAll('SELECT employee_id,status,check_in,check_out,work_mode,notes FROM attendance WHERE date=?', [$date]);
$byEmployee=[]; foreach($rows as $row) $byEmployee[(int)$row['employee_id']]=$row;
$items=[];
foreach($employees as $employee) {
 $id=(int)$employee['id']; $record=$byEmployee[$id] ?? null;
 $leave=hrAttendanceApprovedLeave($id,$date); $returned=false;
 if($leave) {
  $returned=(bool)dbFetchOne('SELECT id FROM hr_leave_returns WHERE leave_id=? AND employee_id=? AND return_date<=? LIMIT 1',[(int)$leave['id'],$id,$date]);
  if(!$returned && $record && (string)($record['status'] ?? '')!=='on_leave' && str_starts_with((string)($record['notes'] ?? ''),'عودة من الإجازة')) $returned=true;
 }
 $items[]=[
  'employee_id'=>$id,'full_name'=>(string)$employee['full_name'],'department_name'=>(string)($employee['department_name'] ?? ''),
  'status'=>($leave && !$returned)?'on_leave':(string)($record['status'] ?? 'missing'),
  'check_in'=>(string)($record['check_in'] ?? ''),'check_out'=>(string)($record['check_out'] ?? ''),
  'work_mode'=>(string)($record['work_mode'] ?? ''),'notes'=>(string)($record['notes'] ?? ''),
 ];
}
$counts=['present'=>0,'absent'=>0,'on_leave'=>0,'missing'=>0,'other'=>0];
foreach($items as $item) { $s=$item['status']; if(array_key_exists($s,$counts)) $counts[$s]++; else $counts['other']++; }
ak_api_json(200,['ok'=>true,'data'=>['date'=>$date,'items'=>$items,'total'=>count($items),'counts'=>$counts]]);
