<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$role = (string)$user['role_code'];
$projectId = max(0, (int)($_GET['id'] ?? 0));
if ($projectId < 1) ak_api_json(422, ['ok'=>false,'error'=>['code'=>'invalid_project_id','message'=>'A valid project ID is required.']]);
$allowedRoles = ['admin','general_manager','vice_general_manager','projects_manager','project_supervisor','accountant','financial_manager'];
if (!in_array($role, $allowedRoles, true)) ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'This account cannot access project details.']]);
$project = dbFetchOne(
 "SELECT p.*, l.lifecycle_status, l.closed_at, l.closure_summary,
         COALESCE(pa.approval_status, 'draft') AS approval_status
  FROM other_projects p
  LEFT JOIN project_lifecycle l ON l.project_id=p.id
  LEFT JOIN project_approval pa ON pa.project_id=p.id
  WHERE p.id=? LIMIT 1", [$projectId]
);
if (!$project) ak_api_json(404, ['ok'=>false,'error'=>['code'=>'project_not_found','message'=>'The project was not found.']]);
$visible = in_array($role, ['admin','general_manager','vice_general_manager','accountant','financial_manager'], true);
if (!$visible && $role === 'projects_manager') {
 $confirmed = dbFetchOne("SELECT id FROM audit_log WHERE action='FM_CONFIRM_PAYMENT_EVIDENCE' AND entity_type='project_payment_evidence' AND entity_id=? LIMIT 1", [$projectId]);
 $latest = dbFetchOne('SELECT new_status FROM project_status_history WHERE project_id=? ORDER BY id DESC LIMIT 1', [$projectId]);
 $visible = (bool)$confirmed
  || ((string)$project['approval_status']==='approved'
      && (string)($project['lifecycle_status'] ?? $project['status'])==='under_review'
      && (string)($latest['new_status'] ?? '')==='closure_requested')
  || ((int)$project['created_by']===$userId && in_array((string)$project['approval_status'], ['draft','submitted','rejected','fm_approved'], true));
}
if (!$visible && $role === 'project_supervisor') {
 $visible = (string)$project['approval_status']==='approved'
  && in_array((string)($project['lifecycle_status'] ?? $project['status']), ['active','reopened','completed','under_review','closed','cancelled'], true);
 if ($visible) {
  $assigned = dbFetchOne('SELECT id FROM project_supervisor_assignments WHERE project_id=? AND supervisor_user_id=? AND ended_at IS NULL LIMIT 1', [$projectId,$userId]);
  $section = dbFetchOne("SELECT id FROM project_team WHERE project_id=? AND user_id=? AND section_code IN ('operations','documents') AND unassigned_at IS NULL LIMIT 1", [$projectId,$userId]);
  $visible = (bool)$assigned || (bool)$section;
 }
}
if (!$visible && !in_array($role, ['projects_manager','project_supervisor'], true)) {
 $team = dbFetchOne('SELECT id FROM project_team WHERE project_id=? AND user_id=? AND unassigned_at IS NULL LIMIT 1', [$projectId,$userId]);
 $supervisor = dbFetchOne('SELECT id FROM project_supervisor_assignments WHERE project_id=? AND supervisor_user_id=? AND ended_at IS NULL LIMIT 1', [$projectId,$userId]);
 $visible = (bool)$team || (bool)$supervisor;
}
if (!$visible) ak_api_json(403, ['ok'=>false,'error'=>['code'=>'forbidden','message'=>'You do not have access to this project.']]);
$totals = dbFetchOne(
 "SELECT
  COALESCE((SELECT SUM(COALESCE(bl.approved_amount,bl.estimated_amount)) FROM project_budgets b JOIN project_budget_lines bl ON bl.budget_id=b.id WHERE b.project_id=? AND b.status='approved'),0) AS approved_budget,
  COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.project_id=? AND t.status='posted'),0) AS donation_total,
  COALESCE((SELECT SUM(f.amount) FROM project_funding_allocations f WHERE f.project_id=? AND f.status='posted' AND (f.transaction_id IS NULL OR NOT EXISTS (SELECT 1 FROM transactions t WHERE t.id=f.transaction_id AND t.status='posted'))),0) AS allocation_total,
  COALESCE((SELECT SUM(e.amount) FROM project_expenses e WHERE e.project_id=? AND e.status='posted'),0) AS expense_total",
 [$projectId,$projectId,$projectId,$projectId]
) ?: [];
$team = dbFetchAll('SELECT pt.section_code,pt.is_lead,u.full_name FROM project_team pt JOIN users u ON u.id=pt.user_id WHERE pt.project_id=? AND pt.unassigned_at IS NULL ORDER BY pt.section_code,pt.is_lead DESC,u.full_name', [$projectId]);
$milestones = dbFetchAll('SELECT m.id,m.title,m.description,m.planned_date,m.completion_percent FROM project_milestones m WHERE m.project_id=? ORDER BY m.planned_date,m.id', [$projectId]);
$progressUpdates = dbFetchAll('SELECT p.update_date,p.completion_percent,p.summary,p.achievements,p.issues,p.next_steps,u.full_name AS submitter_name FROM project_progress_updates p LEFT JOIN users u ON u.id=p.submitted_by WHERE p.project_id=? ORDER BY p.update_date DESC,p.id DESC LIMIT 10', [$projectId]);
$history = dbFetchAll('SELECT h.old_status,h.new_status,h.reason,h.created_at,u.full_name FROM project_status_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.project_id=? ORDER BY h.id DESC LIMIT 10', [$projectId]);
$supervisor = dbFetchOne('SELECT u.full_name FROM project_supervisor_assignments psa JOIN users u ON u.id=psa.supervisor_user_id WHERE psa.project_id=? AND psa.ended_at IS NULL ORDER BY psa.id DESC LIMIT 1', [$projectId]);
$projectStatus = (string)($project['lifecycle_status'] ?? $project['status'] ?? '');
$canAddProgress = $role === 'admin' && $projectStatus !== 'closed';
if (!$canAddProgress && $role === 'project_supervisor'
    && (string)$project['approval_status'] === 'approved'
    && in_array($projectStatus, ['active', 'reopened', 'completed', 'under_review', 'cancelled'], true)) {
 $primaryProgress = dbFetchOne('SELECT id FROM project_supervisor_assignments WHERE project_id=? AND supervisor_user_id=? AND ended_at IS NULL LIMIT 1', [$projectId,$userId]);
 $operationsProgress = dbFetchOne("SELECT id FROM project_team WHERE project_id=? AND user_id=? AND section_code='operations' AND unassigned_at IS NULL LIMIT 1", [$projectId,$userId]);
 $canAddProgress = (bool)$primaryProgress || (bool)$operationsProgress;
}
$canAddMilestone = $role === 'admin' && $projectStatus !== 'closed';
if (!$canAddMilestone && $role === 'project_supervisor'
    && (string)$project['approval_status'] === 'approved'
    && in_array($projectStatus, ['active', 'reopened', 'completed', 'under_review', 'cancelled'], true)) {
 $primaryMilestone = dbFetchOne('SELECT id FROM project_supervisor_assignments WHERE project_id=? AND supervisor_user_id=? AND ended_at IS NULL LIMIT 1', [$projectId,$userId]);
 $operationsMilestone = dbFetchOne("SELECT id FROM project_team WHERE project_id=? AND user_id=? AND section_code='operations' AND unassigned_at IS NULL LIMIT 1", [$projectId,$userId]);
 $canAddMilestone = (bool)$primaryMilestone || (bool)$operationsMilestone;
}
$canManageMilestones = $role === 'admin' && $projectStatus !== 'closed';
if (!$canManageMilestones && $role === 'project_supervisor' && $projectStatus !== 'closed') {
 $primaryManageMilestones = dbFetchOne('SELECT id FROM project_supervisor_assignments WHERE project_id=? AND supervisor_user_id=? AND ended_at IS NULL LIMIT 1', [$projectId,$userId]);
 $operationsManageMilestones = dbFetchOne("SELECT id FROM project_team WHERE project_id=? AND user_id=? AND section_code='operations' AND unassigned_at IS NULL LIMIT 1", [$projectId,$userId]);
 $canManageMilestones = (bool)$primaryManageMilestones || (bool)$operationsManageMilestones;
}
$budget = (float)($totals['approved_budget'] ?? 0);
if ($budget <= 0) $budget = (float)($project['target_amount'] ?? 0);
ak_api_json(200, ['ok'=>true,'data'=>[
 'project'=>[
  'id'=>(int)$project['id'],'project_code'=>(string)($project['project_code'] ?? ''),
  'name'=>(string)($project['name'] ?? ''),'description'=>(string)($project['description'] ?? ''),
  'current_status'=>(string)($project['lifecycle_status'] ?? $project['status'] ?? ''),
  'approval_status'=>(string)$project['approval_status'],'currency_code'=>(string)($project['currency_code'] ?? 'SDG'),
  'budget'=>$budget,'funded'=>(float)($totals['donation_total'] ?? 0)+(float)($totals['allocation_total'] ?? 0),
  'expenses'=>(float)($totals['expense_total'] ?? 0),'closed_at'=>(string)($project['closed_at'] ?? ''),
  'closure_summary'=>(string)($project['closure_summary'] ?? ''),'supervisor_name'=>(string)($supervisor['full_name'] ?? '—'),'can_add_progress'=>$canAddProgress,'can_add_milestone'=>$canAddMilestone,'can_manage_milestones'=>$canManageMilestones
 ],
 'team'=>array_map(static fn(array $r): array=>['full_name'=>(string)$r['full_name'],'section_code'=>(string)$r['section_code'],'is_lead'=>(int)$r['is_lead']===1],$team),
 'milestones'=>array_map(static fn(array $r): array=>['title'=>(string)$r['title'],'description'=>(string)($r['description'] ?? ''),'planned_date'=>(string)($r['planned_date'] ?? ''),'completion_percent'=>(float)($r['completion_percent'] ?? 0)],$milestones),
 'progress_updates'=>array_map(static fn(array $r): array=>['update_date'=>(string)($r['update_date'] ?? ''),'completion_percent'=>(float)($r['completion_percent'] ?? 0),'summary'=>(string)($r['summary'] ?? ''),'achievements'=>(string)($r['achievements'] ?? ''),'issues'=>(string)($r['issues'] ?? ''),'next_steps'=>(string)($r['next_steps'] ?? ''),'submitter_name'=>(string)($r['submitter_name'] ?? '—')],$progressUpdates),
 'history'=>array_map(static fn(array $r): array=>['old_status'=>(string)($r['old_status'] ?? ''),'new_status'=>(string)($r['new_status'] ?? ''),'reason'=>(string)($r['reason'] ?? ''),'created_at'=>(string)($r['created_at'] ?? ''),'actor_name'=>(string)($r['full_name'] ?? '—')],$history)
]]);
