<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if ((string)($user['raw_role_code'] ?? $user['role_code'] ?? '') !== 'admin') {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'Administrator financial overview access is required.']]);
}
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
try {
    // Read-only metrics mirror the existing FM dashboard calculations.
    $treasury = dbFetchOne("SELECT
        COALESCE(SUM(CASE WHEN a.code='1100' THEN jl.debit-jl.credit ELSE 0 END),0) cash,
        COALESCE(SUM(CASE WHEN a.code='1200' THEN jl.debit-jl.credit ELSE 0 END),0) bank,
        COALESCE(SUM(CASE WHEN a.code='1300' THEN jl.debit-jl.credit ELSE 0 END),0) wallet,
        COALESCE(SUM(jl.debit-jl.credit),0) total
        FROM journal_lines jl JOIN accounts a ON a.id=jl.account_id JOIN journal_entries je ON je.id=jl.entry_id
        WHERE a.code IN ('1100','1200','1300') AND a.is_active=1 AND je.status='posted'");
    $flow = dbFetchOne("SELECT
        COALESCE(SUM(CASE WHEN a.account_type='revenue' AND jl.credit>0 THEN jl.credit ELSE 0 END),0) income_total,
        COALESCE(SUM(CASE WHEN a.account_type='expense' AND jl.debit>0 THEN jl.debit ELSE 0 END),0) outgoing_total
        FROM journal_lines jl JOIN journal_entries je ON je.id=jl.entry_id JOIN accounts a ON a.id=jl.account_id
        WHERE je.entry_date BETWEEN ? AND ? AND je.status='posted'", [$monthStart,$monthEnd]);
    $fees = dbFetchOne("SELECT
        COALESCE(SUM(CASE WHEN a.code='4200' AND jl.credit>0 THEN jl.credit ELSE 0 END),0) admin_fees_total,
        COALESCE(SUM(CASE WHEN je.entry_date BETWEEN ? AND ? AND a.code='4200' AND jl.credit>0 THEN jl.credit ELSE 0 END),0) admin_fees_month
        FROM journal_entries je JOIN journal_lines jl ON jl.entry_id=je.id JOIN accounts a ON a.id=jl.account_id
        WHERE je.status='posted' AND EXISTS (
          SELECT 1 FROM journal_lines sl JOIN accounts sa ON sa.id=sl.account_id
          WHERE sl.entry_id=je.id AND sa.code='4100' AND sl.credit>0
        )", [$monthStart,$monthEnd]);
    $disb = dbFetchOne("SELECT COUNT(*) total,
        SUM(status='pending_approval') pending_approval, SUM(status='approved') approved,
        SUM(status='transferred') transferred, SUM(status='received') received,
        SUM(status='voided') voided, SUM(status='cancelled') cancelled, SUM(status='returned') returned,
        COALESCE(SUM(CASE WHEN status='pending_approval' THEN total_amount ELSE 0 END),0) pending_amount,
        COALESCE(SUM(CASE WHEN status='transferred' THEN total_amount ELSE 0 END),0) transferred_amount,
        COALESCE(SUM(CASE WHEN status='returned' THEN total_amount ELSE 0 END),0) returned_amount
        FROM monthly_disbursements");
    $quick = dbFetchOne("SELECT
        (SELECT COUNT(*) FROM sponsorships WHERE status='active') active_sponsorships,
        (SELECT COUNT(*) FROM families WHERE status='active') active_families,
        (SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='nanny' AND u.is_active=1) active_nannies,
        (SELECT COALESCE(SUM(total_amount),0) FROM monthly_disbursements WHERE status='received' AND month=DATE_FORMAT(NOW(),'%Y-%m')) received_this_month");
} catch (Throwable $e) {
    error_log('Mobile admin finance overview failed: '.$e->getMessage());
    ak_api_json(500, ['ok'=>false,'error'=>['code'=>'finance_overview_failed','message'=>'Financial overview data could not be loaded.']]);
}
$numeric = static function (array $row, array $keys, bool $counts=false): array {
    $out=[];
    foreach ($keys as $key) $out[$key]=$counts?(int)($row[$key]??0):(float)($row[$key]??0);
    return $out;
};
ak_api_json(200, ['ok'=>true,'data'=>[
    'period'=>['start'=>$monthStart,'end'=>$monthEnd],
    'treasury'=>$numeric(is_array($treasury)?$treasury:[],['cash','bank','wallet','total']),
    'monthly_flow'=>$numeric(is_array($flow)?$flow:[],['income_total','outgoing_total']),
    'admin_fees'=>$numeric(is_array($fees)?$fees:[],['admin_fees_total','admin_fees_month']),
    'disbursements'=>array_merge($numeric(is_array($disb)?$disb:[],['total','pending_approval','approved','transferred','received','voided','cancelled','returned'],true),$numeric(is_array($disb)?$disb:[],['pending_amount','transferred_amount','returned_amount'])),
    'quick_stats'=>array_merge($numeric(is_array($quick)?$quick:[],['active_sponsorships','active_families','active_nannies'],true),$numeric(is_array($quick)?$quick:[],['received_this_month'])),
]]);
