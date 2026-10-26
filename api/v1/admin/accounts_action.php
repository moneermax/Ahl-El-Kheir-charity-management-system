<?php
declare(strict_types=1);
require_once __DIR__ . '/accounts_service.php';
ak_api_require_method('POST');
$user=ak_api_authenticated_user();
if ((string)($user['raw_role_code']??$user['role_code']??'')!=='admin') {
    ak_api_json(403,['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Chart of accounts is restricted to the administrator role.']]);
}
$input=ak_api_read_json();
try {
    $result=ak_api_admin_account_action($input);
    $status=(int)$result['http_status']; unset($result['http_status']);
    ak_api_json($status,['ok'=>true,'data'=>$result]);
} catch(AkAdminAccountException $e) {
    ak_api_json($e->httpStatus,['ok'=>false,'error'=>['code'=>$e->errorCode,'message'=>$e->getMessage()]]);
} catch(Throwable $e) {
    error_log('Mobile chart of accounts action failed: '.$e->getMessage());
    ak_api_json(500,['ok'=>false,'error'=>['code'=>'accounts_action_failed','message'=>'The account operation could not be completed.']]);
}
