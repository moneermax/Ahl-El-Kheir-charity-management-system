<?php
declare(strict_types=1);
require_once __DIR__ . '/accounts_service.php';
ak_api_require_method('GET');
$user=ak_api_authenticated_user();
if ((string)($user['raw_role_code']??$user['role_code']??'')!=='admin') {
    ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Chart of accounts is restricted to the administrator role.']]);
}
try { ak_api_json(200,['ok'=>true,'data'=>ak_api_admin_list_accounts()]); }
catch(Throwable $e) {
    error_log('Mobile chart of accounts list failed: '.$e->getMessage());
    ak_api_json(500,['ok'=>false,'error'=>['code'=>'accounts_list_failed','message'=>'The chart of accounts could not be loaded.']]);
}
