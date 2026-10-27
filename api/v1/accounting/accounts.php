<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) { header('Allow: GET, POST'); ak_api_json(405, ['ok'=>false,'error'=>['code'=>'method_not_allowed','message'=>'Method not allowed.']]); }
$user = ak_api_authenticated_user();
$role = (string)($user['role_code'] ?? '');
if (!in_array($role, ['admin','financial_manager','general_manager','vice_general_manager'], true)) ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Chart of accounts access is not allowed for this account.']]);
$canManage = in_array($role, ['admin','financial_manager'], true);
$pdo = db();
$exists = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='accounts'")->fetchColumn();
if (!(int)$exists) ak_api_json(503, ['ok'=>false,'error'=>['code'=>'accounts_table_missing','message'=>'The accounting accounts table is not available.']]);
if ($method === 'POST') {
 if (!$canManage) ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Only administrators and the Financial Manager can change accounts.']]);
 $input=ak_api_read_json(); $action=trim((string)($input['action']??''));
 if ($action==='add_account') {
  $code=trim((string)($input['code']??'')); $nameAr=trim((string)($input['name_ar']??'')); $nameEn=trim((string)($input['name_en']??'')); $type=trim((string)($input['account_type']??''));
  if (!preg_match('/^\d{3,6}$/D',$code)) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_account_code','message'=>'Account code must contain 3 to 6 digits.']]);
  if ($nameAr===''||mb_strlen($nameAr,'UTF-8')>150) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_account_name','message'=>'An Arabic account name of at most 150 characters is required.']]);
  if (mb_strlen($nameEn,'UTF-8')>150) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_account_name_en','message'=>'The English account name cannot exceed 150 characters.']]);
  if (!in_array($type,['asset','liability','equity','revenue','expense'],true)) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_account_type','message'=>'Select a valid account type.']]);
  $q=$pdo->prepare('SELECT id FROM accounts WHERE code=? LIMIT 1'); $q->execute([$code]);
  if ($q->fetchColumn()!==false) ak_api_json(409,['ok'=>false,'error'=>['code'=>'account_code_exists','message'=>'An account with this code already exists.']]);
  $q=$pdo->prepare('INSERT INTO accounts (code,name_ar,name_en,account_type) VALUES (?,?,?,?)'); $q->execute([$code,$nameAr,$nameEn!==''?$nameEn:null,$type]);
  ak_api_json(201,['ok'=>true,'data'=>['saved'=>true]]);
 }
 if ($action==='toggle_account') {
  $id=filter_var($input['account_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if ($id===false) ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_account_id','message'=>'A valid account ID is required.']]);
  $q=$pdo->prepare('SELECT is_active FROM accounts WHERE id=?'); $q->execute([(int)$id]); $active=$q->fetchColumn();
  if ($active===false) ak_api_json(404,['ok'=>false,'error'=>['code'=>'account_not_found','message'=>'The requested account was not found.']]);
  $q=$pdo->prepare('UPDATE accounts SET is_active=? WHERE id=?'); $q->execute([(int)$active===1?0:1,(int)$id]);
  ak_api_json(200,['ok'=>true,'data'=>['saved'=>true]]);
 }
 ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_action','message'=>'Unsupported chart-of-accounts action.']]);
}
try {
 $accounts=$pdo->query("SELECT a.id,a.code,a.name_ar,a.name_en,a.account_type,a.is_active,(SELECT COALESCE(SUM(l.debit-l.credit),0) FROM journal_lines l JOIN journal_entries j ON j.id=l.entry_id WHERE l.account_id=a.id AND j.status='posted') AS balance FROM accounts a ORDER BY a.code")->fetchAll(PDO::FETCH_ASSOC);
 foreach ($accounts as &$a) { $a['id']=(int)$a['id']; $a['is_active']=(int)$a['is_active']; $a['balance']=(float)$a['balance']; } unset($a);
 ak_api_json(200,['ok'=>true,'data'=>['accounts'=>$accounts,'can_manage'=>$canManage]]);
} catch (PDOException $e) { ak_api_json(503,['ok'=>false,'error'=>['code'=>'accounts_unavailable','message'=>'Chart of accounts data is temporarily unavailable.']]); }
