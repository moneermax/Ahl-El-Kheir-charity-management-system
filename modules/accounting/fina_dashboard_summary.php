<?php
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once __DIR__.'/fina_lib.php';

Session::start();
header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'message'=>'غير مسجل الدخول'], JSON_UNESCAPED_UNICODE);
    exit;
}

$role=(string)Session::getUserRole();
if (!in_array($role,['financial_manager','fm','admin','general_manager','vice_general_manager'],true)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'غير مصرح'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    fina_ensure_tables();
    $liabilityId=fina_ensure_liability_account();
    $pending=dbFetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM fina_collections WHERE status='pending'");
    $approved=dbFetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM fina_collections WHERE status='approved'");
    $returned=dbFetchOne("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM fina_collections WHERE status='returned'");
    $account=dbFetchOne("SELECT code,name_ar,name_en,account_type,is_active,(SELECT COALESCE(SUM(jl.debit-jl.credit),0) FROM journal_lines jl JOIN journal_entries je ON je.id=jl.entry_id WHERE jl.account_id=accounts.id AND je.status='posted') AS balance FROM accounts WHERE id=?",[$liabilityId]);
    $recent=dbFetchAll("SELECT fc.id,fc.amount,fc.currency_code,fc.collection_date,fc.status,fc.payment_method,fs.source_type,fs.source_name,s.full_name AS sponsor_name,s.sponsor_code FROM fina_collections fc JOIN fina_sources fs ON fs.id=fc.fina_source_id LEFT JOIN sponsors s ON s.id=fs.sponsor_id ORDER BY fc.created_at DESC,fc.id DESC LIMIT 5");
    echo json_encode(['ok'=>true,'pending'=>['count'=>(int)$pending['cnt'],'total'=>(float)$pending['total']], 'approved'=>['count'=>(int)$approved['cnt'],'total'=>(float)$approved['total']], 'returned'=>['count'=>(int)$returned['cnt'],'total'=>(float)$returned['total']], 'account'=>$account, 'recent'=>$recent], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'تعذر تحميل ملخص فينا الخير.'], JSON_UNESCAPED_UNICODE);
}
