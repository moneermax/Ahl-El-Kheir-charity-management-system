<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)($user['raw_role_code'] ?? $user['role_code'] ?? '');
if (!in_array($role, ['admin', 'accountant', 'accountant_staff', 'financial_manager'], true)) {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Assigned-nanny overview is not available for this role.']]);
}
$month = trim((string)($_GET['vmonth'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$nannyId = max(0, (int)($_GET['nanny'] ?? 0));
$scopeIds = null;
if ($role === 'accountant_staff') {
    $scopeIds = array_map('intval', array_column(dbFetchAll(
        'SELECT nanny_id FROM accountant_nanny_assignments WHERE accountant_id = ?', [(int)$user['id']]
    ), 'nanny_id'));
}
try {
    $nannies = [];
    if ($scopeIds === null || $scopeIds !== []) {
        $scopeSql = $scopeIds === null ? '' : ' AND u.id IN (' . implode(',', array_map('intval', $scopeIds)) . ')';
        $nannies = dbFetchAll(
            "SELECT u.id,u.full_name,u.phone,
              (SELECT COUNT(*) FROM families f WHERE f.nanny_id=u.id AND f.status='active') AS active_families,
              (SELECT COUNT(*) FROM nanny_family_verifications v WHERE v.nanny_id=u.id AND v.month=?
               AND v.is_orphan_verified=1 AND v.is_mother_contact_verified=1 AND v.is_bank_verified=1) AS verified_now,
              (SELECT d.status FROM monthly_disbursements d WHERE d.nanny_id=u.id AND d.month=? ORDER BY d.id DESC LIMIT 1) AS batch_status
             FROM users u JOIN roles r ON r.id=u.role_id
             WHERE r.code='nanny' AND u.is_active=1 {$scopeSql} ORDER BY u.full_name",
            [$month,$month]
        );
    }
    $detail = null;
    $families = [];
    if ($nannyId > 0) {
        if ($scopeIds !== null && !in_array($nannyId, $scopeIds, true)) {
            ak_api_json(404, ['ok'=>false,'error'=>['code'=>'nanny_not_found','message'=>'The selected nanny is outside the assigned scope.']]);
        }
        $detail = dbFetchOne("SELECT u.id,u.full_name,u.phone FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND r.code='nanny' LIMIT 1",[$nannyId]);
        if ($detail === null) ak_api_json(404, ['ok'=>false,'error'=>['code'=>'nanny_not_found','message'=>'The selected nanny was not found.']]);
        $families = dbFetchAll(
            "SELECT f.id,f.family_code,f.mother_name,f.mother_phone,f.bank_name,f.bank_account_number,
                    v.is_orphan_verified,v.is_mother_contact_verified,v.is_bank_verified,v.verification_notes
             FROM families f LEFT JOIN nanny_family_verifications v ON v.family_id=f.id AND v.month=?
             WHERE f.nanny_id=? AND f.status='active' ORDER BY f.family_code",
            [$month,$nannyId]
        );
    }
    ak_api_json(200,['ok'=>true,'data'=>['month'=>$month,'nannies'=>$nannies,'detail'=>$detail,'families'=>$families]]);
} catch (Throwable $e) {
    error_log('Assigned-nanny API failed: '.$e->getMessage());
    ak_api_json(500,['ok'=>false,'error'=>['code'=>'assigned_nannies_unavailable','message'=>'Assigned-nanny data could not be loaded.']]);
}
