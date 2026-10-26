<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_payroll_policy.php';

$user = ak_api_authenticated_user();
if (!in_array((string)($user['raw_role_code'] ?? ''), ['hr_manager', 'hr_staff', 'admin'], true)) {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Payroll policy management is restricted to HR Manager, HR Staff, and administrator roles.']]);
}

function mobilePayrollPolicySerialize(array $p, ?int $activeId, string $today): array {
    $id = (int)$p['id'];
    return [
        'id'=>$id, 'version_no'=>(int)$p['version_no'], 'effective_from'=>(string)$p['effective_from'],
        'absence_enabled'=>(int)$p['absence_enabled']===1, 'absence_deduction_percent'=>(float)$p['absence_deduction_percent'],
        'unpaid_leave_enabled'=>(int)$p['unpaid_leave_enabled']===1, 'unpaid_leave_deduction_percent'=>(float)$p['unpaid_leave_deduction_percent'],
        'paid_leave_deduction_percent'=>(float)$p['paid_leave_deduction_percent'],
        'late_enabled'=>(int)$p['late_enabled']===1, 'early_departure_enabled'=>(int)$p['early_departure_enabled']===1,
        'overtime_enabled'=>(int)$p['overtime_enabled']===1, 'overtime_multiplier'=>(float)$p['overtime_multiplier'],
        'daily_deduction_method'=>(string)$p['daily_deduction_method'], 'rounding_decimals'=>(int)$p['rounding_decimals'],
        'notes'=>(string)($p['notes']??''), 'created_by_name'=>(string)($p['created_by_name']??''),
        'created_at'=>(string)($p['created_at']??''), 'is_active'=>$activeId!==null && $id===$activeId,
        'is_future'=>(string)$p['effective_from']>$today,
    ];
}

try {
    $pdo=db();
    $today=date('Y-m-d');
    if (strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='GET') {
        ak_api_require_method('GET');
        $active=hrPayrollPolicyGetActive($pdo,$today);
        $activeId=$active?(int)$active['id']:null;
        $policies=array_map(static fn(array $p):array=>mobilePayrollPolicySerialize($p,$activeId,$today),hrPayrollPolicyGetAll($pdo));
        $activeSerialized=null; $next=null;
        foreach($policies as $p) {
            if($activeId!==null && (int)$p['id']===$activeId) $activeSerialized=$p;
            if($next===null && (string)$p['effective_from']>$today) $next=$p;
        }
        ak_api_json(200,['ok'=>true,'data'=>['today'=>$today,'active_policy'=>$activeSerialized,'next_policy'=>$next,'policies'=>$policies]]);
    }
    ak_api_require_method('POST');
    $body=ak_api_read_json();
    if((string)($body['action']??'')!=='create') {
        ak_api_json(422,['ok'=>false,'error'=>['code'=>'invalid_action','message'=>'Only creation of a new future policy version is supported.']]);
    }
    // The existing web validator represents unchecked HTML checkboxes as absent keys.
    // Convert JSON false to an absent key before using that authoritative validator.
    $input=$body;
    foreach(['absence_enabled','unpaid_leave_enabled','late_enabled','early_departure_enabled','overtime_enabled'] as $flag) {
        if(empty($body[$flag])) unset($input[$flag]); else $input[$flag]=1;
    }
    $policy=hrPayrollPolicyValidate($input);
    $pdo->beginTransaction();
    try {
        if(dbFetchOne('SELECT id FROM hr_payroll_policy_versions WHERE effective_from=? LIMIT 1 FOR UPDATE',[$policy['effective_from']])) {
            throw new DomainException('A payroll policy already exists with that effective date.');
        }
        $nextVersion=dbFetchOne('SELECT COALESCE(MAX(version_no),0)+1 AS next_version FROM hr_payroll_policy_versions');
        $versionNo=(int)($nextVersion['next_version']??1);
        dbExecute(
            'INSERT INTO hr_payroll_policy_versions
             (version_no,effective_from,absence_enabled,absence_deduction_percent,
              unpaid_leave_enabled,unpaid_leave_deduction_percent,paid_leave_deduction_percent,
              late_enabled,early_departure_enabled,overtime_enabled,overtime_multiplier,
              daily_deduction_method,rounding_decimals,notes,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$versionNo,$policy['effective_from'],$policy['absence_enabled'],$policy['absence_deduction_percent'],
             $policy['unpaid_leave_enabled'],$policy['unpaid_leave_deduction_percent'],$policy['paid_leave_deduction_percent'],
             $policy['late_enabled'],$policy['early_departure_enabled'],$policy['overtime_enabled'],$policy['overtime_multiplier'],
             $policy['daily_deduction_method'],$policy['rounding_decimals'],$policy['notes'],(int)$user['id']]
        );
        $id=(int)$pdo->lastInsertId();
        $pdo->commit();
        ak_api_json(201,['ok'=>true,'data'=>['id'=>$id,'version_no'=>$versionNo,'effective_from'=>$policy['effective_from'],'message'=>'Payroll policy version created. Existing payroll records were not changed.']]);
    } catch(DomainException $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        ak_api_json(409,['ok'=>false,'error'=>['code'=>'policy_conflict','message'=>$e->getMessage()]]);
    } catch(PDOException $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        if((string)$e->getCode()==='23000') ak_api_json(409,['ok'=>false,'error'=>['code'=>'policy_conflict','message'=>'The policy version or effective date conflicts with an existing record.']]);
        throw $e;
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
} catch(InvalidArgumentException $e) {
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction()) $pdo->rollBack();
    ak_api_json(422,['ok'=>false,'error'=>['code'=>'policy_validation_failed','message'=>$e->getMessage()]]);
} catch(PDOException $e) {
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction()) $pdo->rollBack();
    error_log('Mobile payroll policy failed: '.$e->getMessage());
    ak_api_json(500,['ok'=>false,'error'=>['code'=>'payroll_policy_failed','message'=>'Payroll policy information could not be processed.']]);
} catch(Throwable $e) {
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction()) $pdo->rollBack();
    error_log('Mobile payroll policy failed: '.$e->getMessage());
    ak_api_json(500,['ok'=>false,'error'=>['code'=>'payroll_policy_failed','message'=>'Payroll policy information could not be processed.']]);
}
