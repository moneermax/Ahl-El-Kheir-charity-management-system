<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/_bootstrap.php';
require_once dirname(__DIR__, 4) . '/modules/hr/lib_employee_identity.php';
ak_api_require_method('POST');
$user=ak_api_authenticated_user(); $userId=(int)$user['id']; $role=(string)$user['role_code'];
$input=ak_api_read_json(); $action=(string)($input['action'] ?? '');
$hrRoles=['hr_manager','hr_staff']; $gmRoles=['general_manager','gm'];
function ak_api_leave_notify_user(int $recipient,string $title,string $body,string $link):void {
 if($recipient<=0)return;
 try {
  if(dbFetchOne('SELECT id FROM notifications WHERE recipient_user_id=? AND title=? AND link=? LIMIT 1',[$recipient,$title,$link]))return;
  dbExecute('INSERT INTO notifications (recipient_user_id,title,body,link) VALUES (?,?,?,?)',[$recipient,$title,$body,$link]);
 } catch(Throwable $e){error_log('Mobile leave notification failed: '.$e->getMessage());}
}
function ak_api_leave_notify_roles(array $roles,string $title,string $body,string $link):void {
 if(!$roles)return; $marks=implode(',',array_fill(0,count($roles),'?'));
 foreach(dbFetchAll("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code IN ($marks) AND u.is_active=1",$roles) as $u) ak_api_leave_notify_user((int)$u['id'],$title,$body,$link);
}
function ak_api_leave_notify_attendance(array $leave):void {
 $start=new DateTime((string)$leave['start_date']); $end=new DateTime((string)$leave['end_date']);
 $period=new DatePeriod($start,new DateInterval('P1D'),$end->modify('+1 day'));
 foreach($period as $date) dbExecute("INSERT IGNORE INTO attendance (employee_id,date,status,notes) VALUES (?,?,'on_leave','إجازة معتمدة')",[(int)$leave['employee_id'],$date->format('Y-m-d')]);
}
try {
 if($action==='request') {
  $employee=hrGetEmployeeForUser(db(),$userId);
  if(!$employee) throw new RuntimeException('No employee profile is linked to this account.');
  $type=trim((string)($input['leave_type']??'')); $start=trim((string)($input['start_date']??'')); $end=trim((string)($input['end_date']??'')); $reason=trim((string)($input['reason']??''));
  if(!in_array($type,['annual','sick','emergency','unpaid','remote_work_request'],true)) throw new InvalidArgumentException('Invalid leave type.');
  $d1=DateTime::createFromFormat('!Y-m-d',$start); $d2=DateTime::createFromFormat('!Y-m-d',$end);
  if(!$d1||!$d2||$d1->format('Y-m-d')!==$start||$d2->format('Y-m-d')!==$end) throw new InvalidArgumentException('Dates must use YYYY-MM-DD format.');
  if($d2<$d1) throw new InvalidArgumentException('End date must be on or after start date.');
  $employeeId=(int)$employee['id']; $days=$d1->diff($d2)->days+1;
  $duplicate=dbFetchOne("SELECT id FROM leaves WHERE employee_id=? AND leave_type=? AND start_date=? AND end_date=? AND status='pending' ORDER BY id LIMIT 1",[$employeeId,$type,$start,$end]);
  if($duplicate) ak_api_json(200,['ok'=>true,'data'=>['leave_id'=>(int)$duplicate['id'],'duplicate'=>true,'message'=>'An identical pending request already exists.']]);
  db()->beginTransaction();
  try {
   dbExecute("INSERT INTO leaves (employee_id,leave_type,start_date,end_date,days_count,reason,status) VALUES (?,?,?,?,?,?,'pending')",[$employeeId,$type,$start,$end,$days,$reason]);
   $leaveId=(int)db()->lastInsertId();
   db()->commit();
  } catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
  $link=APP_URL.'modules/hr/leaves.php?action=request&leave_id='.$leaveId;
  $period=$start===$end?$start:$start.' إلى '.$end;
  if(in_array($role,$hrRoles,true)) ak_api_leave_notify_roles($gmRoles,'طلب إجازة جديد من الموارد البشرية','قام الموظف '.(string)$employee['full_name'].' بتقديم طلب إجازة للفترة '.$period.' ويحتاج إلى اعتماد المدير العام.',$link);
  else ak_api_leave_notify_roles($hrRoles,'طلب إجازة جديد','قام الموظف '.(string)$employee['full_name'].' بتقديم طلب إجازة للفترة '.$period.' ويحتاج إلى مراجعة الموارد البشرية.',$link);
  ak_api_json(200,['ok'=>true,'data'=>['leave_id'=>$leaveId,'duplicate'=>false,'message'=>'Leave request submitted.']]);
 }
 if(!in_array($action,['approve','reject'],true)) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_action','message'=>'Unsupported leave action.']]);
 if(!in_array($role,array_merge($hrRoles,$gmRoles),true)) ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Leave approval access is restricted.']]);
 $leaveId=max(0,(int)($input['leave_id']??0));
 if($leaveId<1) throw new InvalidArgumentException('A valid leave request ID is required.');
 $isHr=in_array($role,$hrRoles,true);
 $roleCondition=$isHr?"r.code NOT IN ('hr_manager','hr_staff')":"r.code IN ('hr_manager','hr_staff')";
 $leave=dbFetchOne("SELECT l.*,e.user_id AS requester_user_id,e.full_name AS requester_name
  FROM leaves l JOIN employees e ON e.id=l.employee_id JOIN users u ON u.id=e.user_id JOIN roles r ON r.id=u.role_id
  WHERE l.id=? AND l.status='pending' AND $roleCondition LIMIT 1",[$leaveId]);
 if(!$leave) throw new RuntimeException('The request is missing, no longer pending, or outside your approval scope.');
 db()->beginTransaction();
 try {
  if($action==='approve') {
   if($isHr) dbExecute("UPDATE leaves SET status='hr_approved',hr_approved_by=?,hr_approved_at=NOW() WHERE id=? AND status='pending'",[$userId,$leaveId]);
   else dbExecute("UPDATE leaves SET status='hr_approved',manager_approved_by=?,manager_approved_at=NOW() WHERE id=? AND status='pending'",[$userId,$leaveId]);
   if(db()->query('SELECT ROW_COUNT()')->fetchColumn()!=1) throw new RuntimeException('The request changed before approval could be saved.');
   ak_api_leave_notify_attendance($leave);
  } else {
   dbExecute("UPDATE leaves SET status='rejected' WHERE id=? AND status='pending'",[$leaveId]);
   if(db()->query('SELECT ROW_COUNT()')->fetchColumn()!=1) throw new RuntimeException('The request changed before rejection could be saved.');
  }
  db()->commit();
 } catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
 $link=APP_URL.'modules/hr/leaves.php?action=request&leave_id='.$leaveId;
 if($action==='approve') ak_api_leave_notify_user((int)$leave['requester_user_id'],'تم اعتماد طلب الإجازة','تم اعتماد طلب إجازتك للفترة '.$leave['start_date'].' إلى '.$leave['end_date'].' من قبل '.($isHr?'الموارد البشرية':'المدير العام').'.',$link);
 else ak_api_leave_notify_user((int)$leave['requester_user_id'],'تم رفض طلب الإجازة','تم رفض طلب إجازتك للفترة '.$leave['start_date'].' إلى '.$leave['end_date'].'. يرجى مراجعة الموارد البشرية لمزيد من التفاصيل.',$link);
 ak_api_json(200,['ok'=>true,'data'=>['leave_id'=>$leaveId,'status'=>$action==='approve'?'hr_approved':'rejected','message'=>$action==='approve'?'Leave request approved.':'Leave request rejected.']]);
} catch(Throwable $e) {
 if(db()->inTransaction()) db()->rollBack();
 $status=$e instanceof InvalidArgumentException?422:($e instanceof RuntimeException?409:500);
 ak_api_json($status,['ok'=>false,'error'=>['code'=>'leave_action_failed','message'=>$e->getMessage()]]);
}
