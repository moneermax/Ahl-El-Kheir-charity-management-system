<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/config/family_scope.php';
ak_api_require_method('GET');
$user=ak_api_authenticated_user();$userId=(int)$user['id'];$role=(string)$user['role_code'];$id=max(0,(int)($_GET['id']??0));
if($id<1)ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_family_id','message'=>'A valid family ID is required.']]);
if(!in_array($role,['admin','vice_general_manager','general_manager','supervisor','nanny'],true))ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Family details are not allowed for this account.']]);
$f=dbFetchOne("SELECT f.*,n.full_name AS nanny_name,s.full_name AS supervisor_name FROM families f LEFT JOIN users n ON n.id=f.nanny_id LEFT JOIN users s ON s.id=f.supervisor_id WHERE f.id=? LIMIT 1",[$id]);
if(!$f)ak_api_json(404,['ok'=>false,'error'=>['code'=>'family_not_found','message'=>'Family not found.']]);
if($role==='nanny'&&(int)($f['nanny_id']??0)!==$userId)ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'This family is outside your assignment.']]);
if($role==='supervisor'&&!ak_family_user_in_scope($f,$role,$userId))ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'This family is outside your responsibility scope.']]);
$children=dbFetchAll("SELECT fc.id,fc.child_name,fc.gender,fc.birth_date,fc.match_status,fc.is_critical,CASE WHEN EXISTS (SELECT 1 FROM sponsorships sp WHERE sp.child_id=fc.id AND sp.status='active') THEN 'matched' ELSE fc.match_status END AS display_match_status FROM family_children fc WHERE fc.family_id=? ORDER BY fc.birth_date DESC",[$id]);
$ships=dbFetchAll("SELECT sp.id,sp.monthly_amount,sp.start_date,sp.status,s.full_name AS sponsor_name,s.sponsor_code,fc.child_name FROM sponsorships sp JOIN sponsors s ON s.id=sp.sponsor_id JOIN family_children fc ON fc.id=sp.child_id WHERE fc.family_id=? ORDER BY sp.id DESC",[$id]);
$monthly=0.0;foreach($ships as $ship)if((string)$ship['status']==='active')$monthly+=(float)$ship['monthly_amount'];
ak_api_json(200,['ok'=>true,'data'=>[
 'family'=>['id'=>(int)$f['id'],'family_code'=>(string)$f['family_code'],'mother_name'=>(string)$f['mother_name'],'mother_phone'=>(string)($f['mother_phone']??''),'status'=>(string)$f['status'],'nanny_name'=>(string)($f['nanny_name']??'—'),'supervisor_name'=>(string)($f['supervisor_name']??'—'),'monthly_commitment'=>$monthly],
 'children'=>array_map(static fn(array $r):array=>['id'=>(int)$r['id'],'child_name'=>(string)$r['child_name'],'gender'=>(string)($r['gender']??'unknown'),'birth_date'=>(string)($r['birth_date']??''),'match_status'=>(string)($r['display_match_status']??'unmatched'),'is_critical'=>(int)($r['is_critical']??0)===1],$children),
 'sponsorships'=>array_map(static fn(array $r):array=>['id'=>(int)$r['id'],'sponsor_name'=>(string)$r['sponsor_name'],'sponsor_code'=>(string)($r['sponsor_code']??''),'child_name'=>(string)$r['child_name'],'monthly_amount'=>(float)$r['monthly_amount'],'start_date'=>(string)($r['start_date']??''),'status'=>(string)$r['status']],$ships)
]]);
