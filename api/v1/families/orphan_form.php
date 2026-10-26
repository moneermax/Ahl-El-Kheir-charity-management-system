<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/config/family_scope.php';
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET','POST'], true)) {
    header('Allow: GET, POST');
    ak_api_json(405, ['ok'=>false,'error'=>['code'=>'method_not_allowed','message'=>'Use GET or POST.']]);
}
$user = ak_api_authenticated_user();
$role = (string)($user['raw_role_code'] ?? '');
$allowedRoles = ['admin','vice_general_manager','general_manager','supervisor','nanny','accountant','accountant_staff','administration','social_media'];
$editableRoles = ['admin','vice_general_manager','supervisor','nanny'];
if (!in_array($role, $allowedRoles, true)) {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Orphan form access is not permitted for this role.']]);
}
$childId = filter_var($_GET['child_id'] ?? 0, FILTER_VALIDATE_INT);
if (!$childId || $childId < 1) {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_child_id','message'=>'A valid child ID is required.']]);
}
$child = dbFetchOne('SELECT * FROM family_children WHERE id=?', [(int)$childId]);
if (!$child) ak_api_json(404, ['ok'=>false,'error'=>['code'=>'child_not_found','message'=>'The child record was not found.']]);
$family = dbFetchOne('SELECT * FROM families WHERE id=?', [(int)$child['family_id']]);
if (!$family) ak_api_json(404, ['ok'=>false,'error'=>['code'=>'family_not_found','message'=>'The family record was not found.']]);
if (in_array($role, ['nanny','supervisor'], true) && !ak_family_user_in_scope($family, $role, (int)$user['id'])) {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'This child is outside your family responsibility scope.']]);
}
$options = require dirname(__DIR__, 3) . '/includes/orphan_options.php';
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'POST') {
    if (!in_array($role, $editableRoles, true)) {
        ak_api_json(403, ['ok'=>false,'error'=>['code'=>'read_only','message'=>'This role can view but not edit the orphan form.']]);
    }
    $body = ak_api_read_json();
    $values = $body['values'] ?? null;
    if (!is_array($values)) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_values','message'=>'A form values object is required.']]);
    }
    $allowedFields = ['nationality','health_status','health_status_other','psychological_state','psychological_state_other','guardian_name','guardian_relationship','father_death_cause','mother_marital_status'];
    foreach ($values as $key => $value) {
        if (!is_string($key) || !in_array($key, $allowedFields, true) || (!is_string($value) && !is_numeric($value))) {
            ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_field','message'=>'One or more form fields are invalid.']]);
        }
    }
    $read = fn(string $key, string $fallback = ''): string => trim((string)($values[$key] ?? $fallback));
    $nationality = $read('nationality', (string)($child['nationality'] ?? 'سودانية'));
    $health = $read('health_status', (string)($child['health_status'] ?? 'سليم'));
    $psych = $read('psychological_state', (string)($child['psychological_state'] ?? 'سليم'));
    $healthOther = $health === 'أخرى' ? $read('health_status_other') : '';
    $psychOther = $psych === 'أخرى' ? $read('psychological_state_other') : '';
    $guardian = $read('guardian_name', (string)($child['guardian_name'] ?? ''));
    $relationship = $read('guardian_relationship', (string)($child['guardian_relationship'] ?? ''));
    $deathCause = $read('father_death_cause', (string)($family['father_death_cause'] ?? ''));
    $marital = $read('mother_marital_status', (string)($family['mother_marital_status'] ?? 'أرملة'));
    if ($nationality === '') $nationality = 'سودانية';
    if (!in_array($health, $options['health'], true) || !in_array($psych, $options['psych'], true) || !in_array($marital, $options['marital'], true)) {
        ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_option','message'=>'Choose a valid health, psychological, or marital-status option.']]);
    }
    foreach ([[$nationality,50],[$healthOther,150],[$psychOther,150],[$guardian,150],[$relationship,60],[$deathCause,150]] as [$value,$max]) {
        if (strlen($value) > $max) ak_api_json(422, ['ok'=>false,'error'=>['code'=>'field_too_long','message'=>'A form field exceeds its allowed length.']]);
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        dbExecute('UPDATE family_children SET nationality=?,health_status=?,health_status_other=?,psychological_state=?,psychological_state_other=?,guardian_name=?,guardian_relationship=?,updated_at=NOW() WHERE id=?',
            [$nationality,$health,$healthOther !== '' ? $healthOther : null,$psych,$psychOther !== '' ? $psychOther : null,$guardian !== '' ? $guardian : null,$relationship !== '' ? $relationship : null,(int)$childId]);
        dbExecute('UPDATE families SET father_death_cause=?,mother_marital_status=?,updated_by=?,updated_at=NOW() WHERE id=?',
            [$deathCause !== '' ? $deathCause : null,$marital,(int)$user['id'],(int)$family['id']]);
        try {
            dbExecute("INSERT INTO audit_log (user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent) VALUES (?,'UPDATE','orphan_form',?,?,?,?,?)",
                [(int)$user['id'],(int)$childId,json_encode([
                    'nationality'=>$child['nationality']??null,'health_status'=>$child['health_status']??null,
                    'psychological_state'=>$child['psychological_state']??null,'guardian_name'=>$child['guardian_name']??null,
                    'father_death_cause'=>$family['father_death_cause']??null,'mother_marital_status'=>$family['mother_marital_status']??null,
                ],JSON_UNESCAPED_UNICODE),json_encode([
                    'nationality'=>$nationality,'health_status'=>$health,'psychological_state'=>$psych,
                    'guardian_name'=>$guardian,'father_death_cause'=>$deathCause,'mother_marital_status'=>$marital,
                ],JSON_UNESCAPED_UNICODE),(string)($_SERVER['REMOTE_ADDR']??''),(string)($_SERVER['HTTP_USER_AGENT']??'')]);
        } catch (Throwable $auditError) { error_log('Mobile orphan form audit failed: '.$auditError->getMessage()); }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Mobile orphan form save failed: '.$e->getMessage());
        ak_api_json(500,['ok'=>false,'error'=>['code'=>'save_failed','message'=>'The orphan form could not be saved.']]);
    }
    $child = dbFetchOne('SELECT * FROM family_children WHERE id=?', [(int)$childId]) ?? $child;
    $family = dbFetchOne('SELECT * FROM families WHERE id=?', [(int)$family['id']]) ?? $family;
}
$sponsorships = dbFetchAll("SELECT sp.id,sp.monthly_amount,sp.start_date,sp.status,s.full_name AS sponsor_name,s.sponsor_code
    FROM sponsorships sp JOIN sponsors s ON s.id=sp.sponsor_id
    WHERE sp.child_id=? AND sp.status IN ('active','paused') ORDER BY sp.id", [(int)$childId]);
ak_api_json(200,['ok'=>true,'data'=>[
    'can_edit'=>in_array($role,$editableRoles,true),
    'child'=>[
        'id'=>(int)$child['id'],'child_name'=>(string)$child['child_name'],'father_name'=>(string)($child['father_name']??''),
        'birth_date'=>(string)($child['birth_date']??''),'gender'=>(string)($child['gender']??'unknown'),
        'nationality'=>(string)($child['nationality']??'سودانية'),'health_status'=>(string)($child['health_status']??'سليم'),
        'health_status_other'=>(string)($child['health_status_other']??''),'psychological_state'=>(string)($child['psychological_state']??'سليم'),
        'psychological_state_other'=>(string)($child['psychological_state_other']??''),'guardian_name'=>(string)($child['guardian_name']??''),
        'guardian_relationship'=>(string)($child['guardian_relationship']??''),'photo_path'=>(string)($child['photo_path']??''),
        'form_serial'=>(string)($child['form_serial']??''),'family_id'=>(int)$child['family_id'],
    ],
    'family'=>[
        'id'=>(int)$family['id'],'family_code'=>(string)($family['family_code']??''),'mother_name'=>(string)$family['mother_name'],
        'mother_phone'=>(string)($family['mother_phone']??''),'city'=>(string)($family['city']??''),
        'address'=>(string)($family['address']??''),'father_death_cause'=>(string)($family['father_death_cause']??''),
        'mother_marital_status'=>(string)($family['mother_marital_status']??'أرملة'),
    ],
    'sponsorships'=>array_map(static fn(array $r):array=>[
        'id'=>(int)$r['id'],'sponsor_name'=>(string)$r['sponsor_name'],'sponsor_code'=>(string)($r['sponsor_code']??''),
        'monthly_amount'=>(float)$r['monthly_amount'],'start_date'=>(string)($r['start_date']??''),'status'=>(string)$r['status'],
    ],$sponsorships),
    'options'=>$options,
]]);
