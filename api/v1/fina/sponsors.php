<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if (!in_array((string)($user['role_code'] ?? ''), ['admin', 'financial_manager'], true)) {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Fina sponsor lookup is restricted to Finance Manager and Administrator.']]);
}

$query = trim((string)($_GET['q'] ?? ''));
if ($query === '') {
    ak_api_json(200, ['ok'=>true,'data'=>['sponsors'=>[]]]);
}
if (strlen($query) > 180) {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'query_too_long','message'=>'Sponsor search text is too long.']]);
}

$tokens = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$firstName = (string)($tokens[0] ?? '');
if ($firstName === '') {
    ak_api_json(200, ['ok'=>true,'data'=>['sponsors'=>[]]]);
}

$conditions = ["SUBSTRING_INDEX(TRIM(full_name),' ',1) LIKE ?"];
$params = [$firstName . '%'];
for ($i = 1; $i < count($tokens); $i++) {
    $conditions[] = 'full_name LIKE ?';
    $params[] = '%' . $tokens[$i] . '%';
}

try {
    $rows = dbFetchAll(
        "SELECT id,sponsor_code,full_name,phone,alt_phone,email,address
         FROM sponsors
         WHERE status='active' AND " . implode(' AND ', $conditions) . "
         ORDER BY full_name LIMIT 30",
        $params
    );
    ak_api_json(200, ['ok'=>true,'data'=>['sponsors'=>$rows]]);
} catch (Throwable $e) {
    error_log('Mobile Fina sponsor lookup failed: '.$e->getMessage());
    ak_api_json(500, ['ok'=>false,'error'=>['code'=>'fina_sponsor_lookup_failed','message'=>'Sponsor search could not be completed.']]);
}
