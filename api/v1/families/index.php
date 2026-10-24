<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/config/family_scope.php';
ak_api_require_method('GET');
$user=ak_api_authenticated_user();$userId=(int)$user['id'];$role=(string)$user['role_code'];
if(!in_array($role,['admin','vice_general_manager','general_manager','supervisor','nanny'],true)) ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Family access is not allowed for this account.']]);
$q=trim((string)($_GET['q']??''));if(mb_strlen($q,'UTF-8')>100)ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_search','message'=>'Search text is too long.']]);
$status=(string)($_GET['status']??'');if($status!==''&&!in_array($status,['active','pending','paused','completed','archived','inactive','closed'],true))ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_status','message'=>'Invalid family status filter.']]);
$page=max(1,(int)($_GET['page']??1));$perPage=20;
$all=dbFetchAll("SELECT f.id,f.family_code,f.mother_name,f.mother_phone,f.status,f.children_count,f.nanny_id,f.supervisor_id,
 n.full_name AS nanny_name,
 (SELECT COUNT(*) FROM family_children fc WHERE fc.family_id=f.id) AS actual_children_count,
 (SELECT COUNT(*) FROM sponsorships sp WHERE sp.status='active' AND sp.child_id IN (SELECT fc.id FROM family_children fc WHERE fc.family_id=f.id)) AS active_sponsorships,
 (SELECT COALESCE(SUM(sp.monthly_amount),0) FROM sponsorships sp WHERE sp.status='active' AND sp.child_id IN (SELECT fc.id FROM family_children fc WHERE fc.family_id=f.id)) AS monthly_commitment
 FROM families f LEFT JOIN users n ON n.id=f.nanny_id ORDER BY f.id DESC");
$filtered=[];
foreach($all as $family){
 if($role==='supervisor'&&!ak_family_user_in_scope($family,$role,$userId))continue;
 if($role==='nanny'&&(int)($family['nanny_id']??0)!==$userId)continue;
 if($status!==''&&(string)$family['status']!==$status)continue;
 if($q!==''){
  $hay=mb_strtolower((string)$family['family_code'].' '.(string)$family['mother_phone'],'UTF-8');
  if(!str_starts_with(mb_strtolower((string)$family['mother_name'],'UTF-8'),mb_strtolower($q,'UTF-8'))&&mb_strpos($hay,mb_strtolower($q,'UTF-8'))===false)continue;
 }
 $filtered[]=$family;
}
$total=count($filtered);$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$rows=array_slice($filtered,($page-1)*$perPage,$perPage);
$items=array_map(static fn(array $r):array=>[
 'id'=>(int)$r['id'],'family_code'=>(string)$r['family_code'],'mother_name'=>(string)$r['mother_name'],
 'mother_phone'=>(string)($r['mother_phone']??''),'status'=>(string)$r['status'],
 'children_count'=>(int)$r['actual_children_count'],'active_sponsorships'=>(int)$r['active_sponsorships'],
 'monthly_commitment'=>(float)$r['monthly_commitment'],'nanny_name'=>(string)($r['nanny_name']??'')
],$rows);
ak_api_json(200,['ok'=>true,'data'=>['items'=>$items,'total'=>$total,'page'=>$page,'pages'=>$pages]]);
