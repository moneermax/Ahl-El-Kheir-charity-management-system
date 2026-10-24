<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/config/sponsor_assignments.php';
ak_api_require_method('GET');
$user=ak_api_authenticated_user(); $userId=(int)$user['id']; $role=(string)$user['role_code'];
if(!in_array($role,['admin','vice_general_manager','general_manager','supervisor'],true)) ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Sponsor access is not allowed for this account.']]);
$q=trim((string)($_GET['q']??'')); if(mb_strlen($q,'UTF-8')>100) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_search','message'=>'Search text is too long.']]);
$status=(string)($_GET['status']??''); if($status!==''&&!in_array($status,['active','inactive','suspended','cancelled'],true)) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_status','message'=>'Invalid sponsor status filter.']]);
$link=(string)($_GET['link_status']??''); if($link!==''&&!in_array($link,['linked','unlinked'],true)) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_link_status','message'=>'Invalid sponsorship filter.']]);
$page=max(1,(int)($_GET['page']??1)); $perPage=30;
$activeCount="(SELECT COUNT(*) FROM sponsorships ss JOIN family_children fc ON fc.id=ss.child_id WHERE ss.sponsor_id=s.id AND ss.status='active')";
$sql="SELECT s.id,COALESCE(NULLIF(TRIM(s.sponsor_code),''),CONCAT('SP-',LPAD(s.id,6,'0'))) AS sponsor_code,s.full_name,s.phone,s.status,s.gender,s.supervisor_id,s.first_letter_id,$activeCount AS active_sponsorship_count FROM sponsors s WHERE 1=1";
$params=[];
if($role==='supervisor'){
 $matrix=dbFetchAll('SELECT letter_id FROM supervisor_letters WHERE supervisor_id=?',[$userId]);
 $letterIds=array_values(array_unique(array_map('intval',array_column($matrix,'letter_id'))));
 $allowed=[];
 if($letterIds){
  $marks=implode(',',array_fill(0,count($letterIds),'?'));
  foreach(dbFetchAll("SELECT id,first_letter_id,gender FROM sponsors WHERE first_letter_id IN ($marks)",$letterIds) as $candidate)
   if(supervisorCanAccessSponsor($userId,$candidate)) $allowed[]=(int)$candidate['id'];
 }
 if(!$allowed) $sql.=' AND 0=1';
 else{$marks=implode(',',array_fill(0,count($allowed),'?'));$sql.=" AND s.id IN ($marks)";$params=$allowed;}
}
if($status!==''){$sql.=' AND s.status=?';$params[]=$status;}
if($q!==''){$sql.=' AND (s.full_name LIKE ? OR s.sponsor_code LIKE ? OR s.phone LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}
if($link==='linked')$sql.=" AND EXISTS (SELECT 1 FROM sponsorships ss WHERE ss.sponsor_id=s.id AND ss.status='active')";
if($link==='unlinked')$sql.=" AND NOT EXISTS (SELECT 1 FROM sponsorships ss WHERE ss.sponsor_id=s.id AND ss.status='active')";
$all=dbFetchAll($sql.' ORDER BY s.id DESC',$params);
$total=count($all);$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);
$rows=array_slice($all,($page-1)*$perPage,$perPage);
$items=array_map(static fn(array $r):array=>[
 'id'=>(int)$r['id'],'sponsor_code'=>(string)$r['sponsor_code'],'full_name'=>(string)$r['full_name'],
 'phone'=>(string)($r['phone']??''),'status'=>(string)$r['status'],'gender'=>(string)($r['gender']??''),
 'active_sponsorship_count'=>(int)$r['active_sponsorship_count']
],$rows);
ak_api_json(200,['ok'=>true,'data'=>['items'=>$items,'total'=>$total,'page'=>$page,'pages'=>$pages]]);
