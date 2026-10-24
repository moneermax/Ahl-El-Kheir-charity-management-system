<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/config/sponsor_assignments.php';
ak_api_require_method('GET');
$user=ak_api_authenticated_user();$userId=(int)$user['id'];$role=(string)$user['role_code'];
$allowed=['admin','vice_general_manager','general_manager','supervisor','accountant_staff','financial_manager'];
if(!in_array($role,$allowed,true))ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Transaction access is not allowed for this account.']]);
$q=trim((string)($_GET['q']??''));if(mb_strlen($q,'UTF-8')>100)ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_search','message'=>'Search text is too long.']]);
$status=(string)($_GET['status']??'');if($status!==''&&!in_array($status,['pending_fm_review','returned','cancelled','posted','voided'],true))ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_status','message'=>'Invalid transaction status filter.']]);
$from=(string)($_GET['from']??'');$to=(string)($_GET['to']??'');
foreach(['from'=>$from,'to'=>$to] as $key=>$date){if($date!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))))ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_date','message'=>$key.' must use YYYY-MM-DD format.']]);}
if($from!==''&&$to!==''&&$from>$to)ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_date_range','message'=>'The start date must not be after the end date.']]);
$page=max(1,(int)($_GET['page']??1));$perPage=50;$params=[];$where=[];
if($role==='accountant_staff'){$where[]='t.created_by=?';$params[]=$userId;}
elseif($role==='supervisor'){
 $scopeRows=dbFetchAll('SELECT id,supervisor_id,first_letter_id,gender FROM sponsors WHERE supervisor_id=? OR first_letter_id IN (SELECT letter_id FROM supervisor_letters WHERE supervisor_id=?)',[$userId,$userId]);
 $ids=[];foreach($scopeRows as $row)if(supervisorCanAccessSponsor($userId,$row))$ids[]=(int)$row['id'];
 $ids=array_values(array_unique($ids));
 if(!$ids)ak_api_json(200,['ok'=>true,'data'=>['items'=>[],'total'=>0,'pages'=>1,'page'=>1,'posted_total'=>0]]);
 $marks=implode(',',array_fill(0,count($ids),'?'));$where[]="t.sponsor_id IN ($marks)";$params=array_merge($params,$ids);
}
if($status!==''){$where[]='t.status=?';$params[]=$status;}
if($from!==''){$where[]='t.transaction_date>=?';$params[]=$from;}
if($to!==''){$where[]='t.transaction_date<=?';$params[]=$to;}
$sql="SELECT t.id,t.transaction_code,t.transaction_date,t.transaction_type,t.amount,t.payment_method,t.receipt_number,t.receipt_path,t.status,t.created_by,s.full_name AS sponsor_name,f.mother_name AS family_name FROM transactions t LEFT JOIN sponsors s ON s.id=t.sponsor_id LEFT JOIN families f ON f.id=t.family_id";
if($where)$sql.=' WHERE '.implode(' AND ',$where);
$rows=dbFetchAll($sql.' ORDER BY t.transaction_date DESC,t.id DESC',$params);
if($q!==''){$needle=mb_strtolower($q,'UTF-8');$rows=array_values(array_filter($rows,static function(array $r)use($needle):bool{$hay=mb_strtolower((string)($r['sponsor_name']??'').' '.(string)($r['family_name']??'').' '.(string)$r['transaction_code'].' '.(string)($r['receipt_number']??''),'UTF-8');return mb_strpos($hay,$needle,0,'UTF-8')!==false;}));}
$postedTotal=0.0;foreach($rows as $row)if((string)$row['status']==='posted')$postedTotal+=(float)$row['amount'];
$total=count($rows);$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$rows=array_slice($rows,($page-1)*$perPage,$perPage);
$items=array_map(static fn(array $r):array=>['id'=>(int)$r['id'],'transaction_code'=>(string)$r['transaction_code'],'transaction_date'=>(string)$r['transaction_date'],'transaction_type'=>(string)($r['transaction_type']??''),'amount'=>(float)$r['amount'],'payment_method'=>(string)($r['payment_method']??''),'receipt_number'=>(string)($r['receipt_number']??''),'has_receipt'=>(string)($r['receipt_path']??'')!=='','status'=>(string)$r['status'],'sponsor_name'=>(string)($r['sponsor_name']??''),'family_name'=>(string)($r['family_name']??'')],$rows);
ak_api_json(200,['ok'=>true,'data'=>['items'=>$items,'total'=>$total,'pages'=>$pages,'page'=>$page,'posted_total'=>$postedTotal]]);
