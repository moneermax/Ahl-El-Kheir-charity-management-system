<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 2) . '/modules/hr/lib_employee_identity.php';
ak_api_require_method('GET');
$user=ak_api_authenticated_user();
$userId=(int)$user['id']; $role=(string)$user['role_code'];
$hrRoles=['hr_manager','hr_staff']; $gmRoles=['general_manager','gm'];
$view=(string)($_GET['view'] ?? 'mine');
if(!in_array($view,['mine','queue'],true)) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_view','message'=>'View must be mine or queue.']]);
if($view==='queue' && !in_array($role,array_merge($hrRoles,$gmRoles),true)) ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Leave approval queue access is restricted.']]);
$filter=(string)($_GET['status'] ?? 'all');
if(!in_array($filter,['all','pending','hr_approved','rejected'],true)) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_status','message'=>'Invalid leave status filter.']]);
$page=max(1,(int)($_GET['page'] ?? 1)); $perPage=20;
$where=[]; $params=[];
if($view==='mine'){
 $employee=hrGetEmployeeForUser(db(),$userId);
 if(!$employee) ak_api_json(200,['ok'=>true,'data'=>['view'=>'mine','items'=>[],'total'=>0,'page'=>1,'pages'=>1,'employee_linked'=>false]]);
 $where[]='l.employee_id=?'; $params[]=(int)$employee['id'];
} else {
 $roleCondition=in_array($role,$hrRoles,true)?"r.code NOT IN ('hr_manager','hr_staff')":"r.code IN ('hr_manager','hr_staff')";
 $where[]=$roleCondition;
}
if($filter!=='all'){$where[]='l.status=?';$params[]=$filter;}
$sql="SELECT l.id,l.employee_id,l.leave_type,l.start_date,l.end_date,l.days_count,l.reason,l.status,l.created_at,
             e.full_name AS employee_name,d.name_ar AS department_name,
             u1.full_name AS manager_approver_name,u2.full_name AS hr_approver_name,r.code AS employee_role
      FROM leaves l JOIN employees e ON e.id=l.employee_id
      LEFT JOIN departments d ON d.id=e.department_id
      LEFT JOIN users u1 ON u1.id=l.manager_approved_by
      LEFT JOIN users u2 ON u2.id=l.hr_approved_by
      LEFT JOIN users u ON u.id=e.user_id LEFT JOIN roles r ON r.id=u.role_id
      WHERE ".implode(' AND ',$where);
$countRow=dbFetchOne("SELECT COUNT(*) AS total FROM leaves l JOIN employees e ON e.id=l.employee_id LEFT JOIN users u ON u.id=e.user_id LEFT JOIN roles r ON r.id=u.role_id WHERE ".implode(' AND ',$where),$params);
$total=(int)($countRow['total']??0); $pages=max(1,(int)ceil($total/$perPage)); $page=min($page,$pages); $offset=($page-1)*$perPage;
$rows=dbFetchAll($sql." ORDER BY l.created_at DESC,l.id DESC LIMIT ".(int)$perPage." OFFSET ".(int)$offset,$params);
$items=array_map(static fn(array $r):array=>[
 'id'=>(int)$r['id'],'employee_id'=>(int)$r['employee_id'],'employee_name'=>(string)$r['employee_name'],
 'department_name'=>(string)($r['department_name']??''),'leave_type'=>(string)$r['leave_type'],
 'start_date'=>(string)$r['start_date'],'end_date'=>(string)$r['end_date'],'days_count'=>(int)$r['days_count'],
 'reason'=>(string)($r['reason']??''),'status'=>(string)$r['status'],'created_at'=>(string)($r['created_at']??''),
 'manager_approver_name'=>(string)($r['manager_approver_name']??''),'hr_approver_name'=>(string)($r['hr_approver_name']??'')
],$rows);
ak_api_json(200,['ok'=>true,'data'=>['view'=>$view,'items'=>$items,'total'=>$total,'page'=>$page,'pages'=>$pages,'employee_linked'=>true]]);
