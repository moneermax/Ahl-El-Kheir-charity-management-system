<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/config/sponsor_assignments.php';
ak_api_require_method('GET');
$user=ak_api_authenticated_user();$userId=(int)$user['id'];$role=(string)$user['role_code'];$id=max(0,(int)($_GET['id']??0));
if($id<1)ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_sponsor_id','message'=>'A valid sponsor ID is required.']]);
if(!in_array($role,['admin','vice_general_manager','general_manager','supervisor','financial_manager','accountant'],true))ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Sponsor details are not allowed for this account.']]);
$s=dbFetchOne("SELECT s.*,l.code AS letter,u.full_name AS supervisor_name FROM sponsors s LEFT JOIN letters l ON l.id=s.first_letter_id LEFT JOIN users u ON u.id=s.supervisor_id WHERE s.id=? LIMIT 1",[$id]);
if(!$s)ak_api_json(404,['ok'=>false,'error'=>['code'=>'sponsor_not_found','message'=>'Sponsor not found.']]);
if($role==='supervisor'&&!supervisorCanAccessSponsor($userId,$s))ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'This sponsor is outside your responsibility scope.']]);
$ships=dbFetchAll("SELECT sp.id,COALESCE(NULLIF(TRIM(sp.sponsorship_code),''),CONCAT('SH-',LPAD(sp.id,6,'0'))) AS sponsorship_code,sp.monthly_amount,sp.start_date,sp.status,fc.child_name,f.mother_name,f.family_code FROM sponsorships sp JOIN family_children fc ON fc.id=sp.child_id JOIN families f ON f.id=fc.family_id WHERE sp.sponsor_id=? ORDER BY sp.status ASC,sp.id DESC",[$id]);
$active=(int)(dbFetchOne("SELECT COUNT(DISTINCT sp.child_id) AS total FROM sponsorships sp WHERE sp.sponsor_id=? AND sp.status='active'",[$id])['total']??0);
ak_api_json(200,['ok'=>true,'data'=>[
 'sponsor'=>['id'=>(int)$s['id'],'sponsor_code'=>(string)($s['sponsor_code']?:('SP-'.str_pad((string)$s['id'],6,'0',STR_PAD_LEFT))),'full_name'=>(string)$s['full_name'],'phone'=>(string)($s['phone']??''),'email'=>(string)($s['email']??''),'address'=>(string)($s['address']??''),'sponsor_type'=>(string)($s['sponsor_type']??''),'preferred_payment_method'=>(string)($s['preferred_payment_method']??''),'status'=>(string)$s['status'],'gender'=>(string)($s['gender']??''),'letter'=>(string)($s['letter']??''),'supervisor_name'=>(string)($s['supervisor_name']??'—'),'acquisition_source'=>(string)($s['acquisition_source']??''),'brought_by_name'=>(string)($s['brought_by_name']??''),'active_orphan_count'=>$active],
 'sponsorships'=>array_map(static fn(array $r):array=>['id'=>(int)$r['id'],'sponsorship_code'=>(string)$r['sponsorship_code'],'child_name'=>(string)$r['child_name'],'mother_name'=>(string)$r['mother_name'],'family_code'=>(string)$r['family_code'],'monthly_amount'=>(float)$r['monthly_amount'],'start_date'=>(string)($r['start_date']??''),'status'=>(string)$r['status']],$ships)
]]);
