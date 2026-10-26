<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/reports/report_registry.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = ak_api_normalize_role_code((string)($user['raw_role_code'] ?? ''));
$report = trim((string)($_GET['report'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$from = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!in_array($report, ['financial','sponsorship','operational','hr'], true) || !ak_report_can_access($report, $role)) {
    ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'This report is not permitted for this role.']]);
}
$validDate = static function(string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};
if (!$validDate($from) || !$validDate($to) || $from > $to) {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_date_range','message'=>'Choose a valid date range with the start on or before the end.']]);
}
$types = [
 'financial'=>['income_expense','cashflow','treasury','disbursement','returns_voids'],
 'sponsorship'=>['coverage','retention','distribution','status_summary'],
 'operational'=>['supervisor','nanny','verification'],
 'hr'=>['headcount','attendance','leaves','payroll'],
];
if (!in_array($type, $types[$report], true)) {
    ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_report_type','message'=>'The selected report type is not available.']]);
}
$rows=[]; $summary=[]; $fromMonth=substr($from,0,7); $toMonth=substr($to,0,7);
if ($report==='financial') {
 if ($type==='income_expense') {
  $rows=dbFetchAll("SELECT DATE_FORMAT(transaction_date,'%Y-%m') AS month,
   SUM(CASE WHEN purpose='monthly_sponsorship' AND transaction_type<>'outflow' THEN amount ELSE 0 END) AS sponsorship_income,
   SUM(CASE WHEN purpose='general_donation' AND transaction_type<>'outflow' THEN amount ELSE 0 END) AS donation_income,
   SUM(CASE WHEN purpose='admin_fee' AND transaction_type<>'outflow' THEN amount ELSE 0 END) AS admin_fee_income,
   SUM(CASE WHEN transaction_type<>'outflow' THEN amount ELSE 0 END) AS total_income,
   SUM(CASE WHEN transaction_type='outflow' THEN amount ELSE 0 END) AS total_expense
   FROM transactions WHERE status='posted' AND transaction_date BETWEEN ? AND ?
   GROUP BY DATE_FORMAT(transaction_date,'%Y-%m') ORDER BY month",[$from,$to]);
 } elseif ($type==='cashflow') {
  $rows=dbFetchAll("SELECT DATE_FORMAT(transaction_date,'%Y-%m') AS month,
   COALESCE(SUM(CASE WHEN transaction_type<>'outflow' AND status='posted' THEN amount ELSE 0 END),0) AS inflow,
   COALESCE(SUM(CASE WHEN transaction_type='outflow' AND status='posted' THEN amount ELSE 0 END),0) AS outflow
   FROM transactions WHERE transaction_date BETWEEN ? AND ? GROUP BY DATE_FORMAT(transaction_date,'%Y-%m') ORDER BY month",[$from,$to]);
 } elseif ($type==='treasury') {
  $rows=dbFetchAll("SELECT a.code,a.name_ar,a.name_en,COALESCE(SUM(CASE WHEN je.status='posted' THEN jl.debit-jl.credit ELSE 0 END),0) AS balance
   FROM accounts a LEFT JOIN journal_lines jl ON a.id=jl.account_id LEFT JOIN journal_entries je ON jl.entry_id=je.id
   WHERE a.code IN ('1100','1200','1300') AND a.is_active=1 GROUP BY a.id,a.code,a.name_ar,a.name_en ORDER BY a.code");
 } elseif ($type==='disbursement') {
  $rows=dbFetchAll("SELECT md.month,u.full_name AS nanny_name,COUNT(di.id) AS families_count,COALESCE(SUM(di.amount),0) AS total_amount,
   SUM(CASE WHEN di.status='paid' THEN 1 ELSE 0 END) AS paid_count,
   COALESCE(SUM(CASE WHEN di.status='returned' THEN di.amount ELSE 0 END),0) AS returned_amount,md.status AS disbursement_status
   FROM monthly_disbursements md JOIN users u ON md.nanny_id=u.id LEFT JOIN disbursement_items di ON md.id=di.disbursement_id
   WHERE md.month BETWEEN ? AND ? GROUP BY md.id,md.month,md.nanny_id,u.full_name,md.status ORDER BY md.month DESC,u.full_name",[$fromMonth,$toMonth]);
 } else {
  $rows=dbFetchAll("SELECT 'return' AS type,di.id,di.return_reason AS reason,di.amount,di.returned_at AS date,f.mother_name AS entity_name,md.month
   FROM disbursement_items di JOIN monthly_disbursements md ON di.disbursement_id=md.id JOIN families f ON di.family_id=f.id
   WHERE di.status='returned' AND DATE(di.returned_at) BETWEEN ? AND ?
   UNION ALL SELECT 'void' AS type,t.id,t.void_reason AS reason,t.amount,t.voided_at AS date,
   COALESCE(s.full_name,f.mother_name,'غير محدد') AS entity_name,DATE_FORMAT(t.transaction_date,'%Y-%m') AS month
   FROM transactions t LEFT JOIN sponsorships sp ON t.sponsorship_id=sp.id LEFT JOIN sponsors s ON sp.sponsor_id=s.id
   LEFT JOIN families f ON t.family_id=f.id WHERE t.status='voided' AND DATE(t.voided_at) BETWEEN ? AND ? ORDER BY date DESC",
   [$from,$to,$from,$to]);
 }
} elseif ($report==='sponsorship') {
 if ($type==='coverage') {
  $summary=['total_children'=>(int)(dbFetchOne("SELECT COUNT(*) c FROM family_children WHERE is_active=1")['c']??0),
   'sponsored_children'=>(int)(dbFetchOne("SELECT COUNT(DISTINCT child_id) c FROM sponsorships WHERE status='active' AND child_id IS NOT NULL")['c']??0),
   'total_families'=>(int)(dbFetchOne("SELECT COUNT(*) c FROM families WHERE status IN ('active','pending')")['c']??0),
   'uncovered_families'=>(int)(dbFetchOne("SELECT COUNT(*) c FROM families f WHERE f.status IN ('active','pending')
    AND EXISTS(SELECT 1 FROM family_children fc WHERE fc.family_id=f.id AND fc.is_active=1)
    AND NOT EXISTS(SELECT 1 FROM sponsorships sp JOIN family_children fc2 ON sp.child_id=fc2.id WHERE fc2.family_id=f.id AND sp.status='active')")['c']??0)];
  $summary['coverage_percent']=$summary['total_children']>0?round(($summary['sponsored_children']/$summary['total_children'])*100,1):0;
  $rows=dbFetchAll("SELECT f.city,COUNT(DISTINCT f.id) AS families,COUNT(DISTINCT CASE WHEN sp.status='active' THEN fc.id END) AS sponsored_children
   FROM families f JOIN family_children fc ON fc.family_id=f.id AND fc.is_active=1 LEFT JOIN sponsorships sp ON sp.child_id=fc.id
   WHERE f.status IN ('active','pending') GROUP BY f.city ORDER BY f.city");
 } elseif ($type==='retention') {
  $rows=dbFetchAll("SELECT status,COUNT(*) AS sponsor_count FROM sponsors GROUP BY status ORDER BY sponsor_count DESC");
  $summary=['total_sponsors'=>(int)(dbFetchOne("SELECT COUNT(*) c FROM sponsors")['c']??0),
   'active_sponsors'=>(int)(dbFetchOne("SELECT COUNT(*) c FROM sponsors WHERE status='active'")['c']??0),
   'new_sponsors_in_period'=>(int)(dbFetchOne("SELECT COUNT(*) c FROM sponsors WHERE DATE(created_at) BETWEEN ? AND ?",[$from,$to])['c']??0)];
 } elseif ($type==='distribution') {
  $rows=dbFetchAll("SELECT u.full_name AS supervisor_name,COUNT(s.id) AS sponsor_count,SUM(CASE WHEN s.status='active' THEN 1 ELSE 0 END) AS active_count
   FROM users u LEFT JOIN sponsors s ON s.supervisor_id=u.id WHERE u.role_id=4 GROUP BY u.id,u.full_name ORDER BY sponsor_count DESC");
 } else {
  $rows=dbFetchAll("SELECT status,COUNT(*) AS sponsorship_count,COALESCE(SUM(monthly_amount),0) AS monthly_commitment FROM sponsorships GROUP BY status ORDER BY sponsorship_count DESC");
  $summary=['active_monthly_commitment'=>(float)(dbFetchOne("SELECT COALESCE(SUM(monthly_amount),0) total FROM sponsorships WHERE status='active'")['total']??0)];
 }
} elseif ($report==='operational') {
 if ($type==='supervisor') {
  $rows=dbFetchAll("SELECT u.full_name,COUNT(DISTINCT s.id) AS total_sponsors,SUM(CASE WHEN s.status='active' THEN 1 ELSE 0 END) AS active_sponsors,
   COALESCE(SUM(CASE WHEN t.status='posted' THEN t.amount ELSE 0 END),0) AS total_collected,
   COUNT(DISTINCT CASE WHEN t.status='posted' THEN t.id END) AS transaction_count
   FROM users u LEFT JOIN sponsors s ON s.supervisor_id=u.id LEFT JOIN transactions t ON t.sponsor_id=s.id AND t.transaction_date BETWEEN ? AND ?
   WHERE u.role_id=4 GROUP BY u.id,u.full_name ORDER BY total_collected DESC",[$from,$to]);
 } elseif ($type==='nanny') {
  $rows=dbFetchAll("SELECT u.full_name AS nanny_name,COUNT(DISTINCT md.id) AS disbursement_count,COALESCE(SUM(md.total_amount),0) AS total_amount,
   SUM(CASE WHEN di.status='paid' THEN 1 ELSE 0 END) AS paid_families,SUM(CASE WHEN di.status='returned' THEN 1 ELSE 0 END) AS returned_families,
   SUM(CASE WHEN di.status='pending' THEN 1 ELSE 0 END) AS pending_families,COALESCE(SUM(CASE WHEN di.status='returned' THEN di.amount ELSE 0 END),0) AS returned_amount
   FROM users u LEFT JOIN monthly_disbursements md ON md.nanny_id=u.id AND md.month BETWEEN ? AND ?
   LEFT JOIN disbursement_items di ON di.disbursement_id=md.id WHERE u.role_id=7 GROUP BY u.id,u.full_name ORDER BY total_amount DESC",[$fromMonth,$toMonth]);
 } else {
  $rows=dbFetchAll("SELECT u.full_name AS nanny_name,COUNT(nfv.id) AS total_families,SUM(nfv.is_orphan_verified) AS orphan_verified,
   SUM(nfv.is_mother_contact_verified) AS mother_verified,SUM(nfv.is_bank_verified) AS bank_verified,
   SUM(CASE WHEN nfv.is_orphan_verified=1 AND nfv.is_mother_contact_verified=1 AND nfv.is_bank_verified=1 THEN 1 ELSE 0 END) AS fully_verified
   FROM nanny_family_verifications nfv JOIN users u ON nfv.nanny_id=u.id WHERE DATE(nfv.created_at) BETWEEN ? AND ?
   GROUP BY u.id,u.full_name ORDER BY total_families DESC",[$from,$to]);
 }
} else {
 if ($type==='headcount') {
  $rows=dbFetchAll("SELECT status,COUNT(*) AS employee_count FROM employees GROUP BY status ORDER BY employee_count DESC");
  $summary=['total_employees'=>(int)(dbFetchOne("SELECT COUNT(*) c FROM employees")['c']??0),'active_employees'=>(int)(dbFetchOne("SELECT COUNT(*) c FROM employees WHERE status='active'")['c']??0)];
 } elseif ($type==='attendance') {
  $rows=dbFetchAll("SELECT e.full_name,e.employee_code,COUNT(a.id) AS total_days,SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present,
   SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) AS absent,SUM(CASE WHEN a.status='late' THEN 1 ELSE 0 END) AS late,
   SUM(CASE WHEN a.status='half_day' THEN 1 ELSE 0 END) AS half_day,SUM(CASE WHEN a.status='on_leave' THEN 1 ELSE 0 END) AS on_leave
   FROM employees e LEFT JOIN attendance a ON e.id=a.employee_id AND a.date BETWEEN ? AND ? WHERE e.status='active'
   GROUP BY e.id,e.full_name,e.employee_code ORDER BY present DESC",[$from,$to]);
 } elseif ($type==='leaves') {
  $rows=dbFetchAll("SELECT leave_type,COUNT(*) AS leave_count,COALESCE(SUM(days_count),0) AS total_days,
   SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,SUM(CASE WHEN status IN ('manager_approved','hr_approved') THEN 1 ELSE 0 END) AS approved,
   SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected FROM leaves WHERE start_date BETWEEN ? AND ? GROUP BY leave_type ORDER BY leave_count DESC",[$from,$to]);
 } else {
  $rows=dbFetchAll("SELECT p.year,p.month,COUNT(*) AS employee_count,COALESCE(SUM(p.basic_salary),0) AS total_basic,
   COALESCE(SUM(p.allowances),0) AS total_allowances,COALESCE(SUM(p.deductions),0) AS total_deductions,COALESCE(SUM(p.overtime),0) AS total_overtime,
   COALESCE(SUM(p.net_salary),0) AS total_net,SUM(CASE WHEN p.status='draft' THEN 1 ELSE 0 END) AS draft_count,
   SUM(CASE WHEN p.status='approved' THEN 1 ELSE 0 END) AS approved_count,SUM(CASE WHEN p.status='paid' THEN 1 ELSE 0 END) AS paid_count
   FROM payroll p WHERE (p.year>YEAR(?) OR (p.year=YEAR(?) AND p.month>=MONTH(?)))
   AND (p.year<YEAR(?) OR (p.year=YEAR(?) AND p.month<=MONTH(?)))
   GROUP BY p.year,p.month ORDER BY p.year DESC,p.month DESC",[$from,$from,$from,$to,$to,$to]);
 }
}
foreach($rows as &$row){foreach($row as $key=>$value){if(is_string($value)&&strlen($value)>500)$row[$key]=substr($value,0,500).'…';}} unset($row);
ak_api_json(200,['ok'=>true,'data'=>['report'=>$report,'type'=>$type,'from'=>$from,'to'=>$to,'summary'=>$summary,'rows'=>$rows,'total'=>count($rows)]]);
