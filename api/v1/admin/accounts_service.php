<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

final class AkAdminAccountException extends RuntimeException {
    public function __construct(public readonly int $httpStatus, public readonly string $errorCode, string $message) {
        parent::__construct($message);
    }
}

function ak_api_admin_list_accounts(): array {
    $rows = dbFetchAll("SELECT a.id, a.code, a.name_ar, a.name_en, a.account_type, a.is_active,
        (SELECT COALESCE(SUM(jl.debit-jl.credit),0)
         FROM journal_lines jl JOIN journal_entries je ON je.id=jl.entry_id
         WHERE jl.account_id=a.id AND je.status='posted') AS balance
        FROM accounts a ORDER BY a.code");
    $items = array_map(static fn(array $r): array => [
        'id'=>(int)$r['id'], 'code'=>(string)$r['code'], 'name_ar'=>(string)($r['name_ar']??''),
        'name_en'=>(string)($r['name_en']??''), 'account_type'=>(string)$r['account_type'],
        'is_active'=>(int)$r['is_active'], 'balance'=>(float)($r['balance']??0),
    ], $rows);
    return ['items'=>$items,'total'=>count($items)];
}

function ak_api_admin_account_action(array $input): array {
    $action=trim((string)($input['action']??''));
    if ($action==='create') {
        $code=trim((string)($input['code']??''));
        $nameAr=trim((string)($input['name_ar']??''));
        $nameEn=trim((string)($input['name_en']??''));
        $type=(string)($input['account_type']??'');
        if (!preg_match('/^\d{3,6}$/',$code)) throw new AkAdminAccountException(422,'invalid_account_code','Account code must contain 3 to 6 digits.');
        if ($nameAr==='') throw new AkAdminAccountException(422,'account_name_required','Arabic account name is required.');
        if (mb_strlen($nameAr,'UTF-8')>150 || mb_strlen($nameEn,'UTF-8')>150) throw new AkAdminAccountException(422,'account_name_too_long','Account names must not exceed 150 characters.');
        if (!in_array($type,['asset','liability','equity','revenue','expense'],true)) throw new AkAdminAccountException(422,'invalid_account_type','Invalid account type.');
        if (dbFetchOne('SELECT id FROM accounts WHERE code=? LIMIT 1',[$code])) throw new AkAdminAccountException(409,'duplicate_account_code','An account with this code already exists.');
        dbExecute('INSERT INTO accounts (code,name_ar,name_en,account_type) VALUES (?,?,?,?)',[$code,$nameAr,$nameEn!==''?$nameEn:null,$type]);
        $id=(int)dbLastInsertId();
        return ['http_status'=>201,'id'=>$id,'code'=>$code,'name_ar'=>$nameAr,'name_en'=>$nameEn,'account_type'=>$type,'is_active'=>1];
    }
    if ($action==='toggle_status') {
        $id=(int)($input['id']??0);
        if ($id<1) throw new AkAdminAccountException(422,'invalid_account_id','A valid account ID is required.');
        $account=dbFetchOne('SELECT id,is_active FROM accounts WHERE id=? LIMIT 1',[$id]);
        if (!$account) throw new AkAdminAccountException(404,'account_not_found','Account not found.');
        $active=(int)$account['is_active']===1?0:1;
        dbExecute('UPDATE accounts SET is_active=? WHERE id=?',[$active,$id]);
        return ['http_status'=>200,'id'=>$id,'is_active'=>$active];
    }
    throw new AkAdminAccountException(422,'invalid_action','Unsupported chart-account action.');
}
