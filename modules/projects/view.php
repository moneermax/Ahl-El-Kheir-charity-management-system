<?php
// modules/projects/view.php - Project profile, financial controls, documents, operations, closure
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_transaction_review.php';
Session::start();
$requestedProjectId = (int)($_GET['id'] ?? $_POST['project_id'] ?? 0);
if (!isset($_GET['role_view'])) {
if (akp_role() === 'financial_manager') {
header('Location: ' . APP_URL . 'modules/projects/view_fm.php?id=' . $requestedProjectId);
exit();
}
if (akp_role() === 'projects_manager') {
header('Location: ' . APP_URL . 'modules/projects/view_pm.php?id=' . $requestedProjectId);
exit();
}
}
$id = (int)($_GET['id'] ?? $_POST['project_id'] ?? 0);
$project = $id ? akp_get_project($id) : null;
if (!$project) {
flash('error', 'المشروع غير موجود.');
redirect('modules/projects/index.php');
}
if (!akp_can_view_project($id)) {
header('Location: ' . APP_URL . 'index.php');
exit();
}
$project = akp_get_project($id);
$totals = akp_project_totals($id);
$details = dbFetchOne('SELECT * FROM project_details WHERE project_id = ?', [$id]) ?: [];
$governmentRequirementRows = dbFetchAll(
'SELECT requirement_text, fee_amount
FROM project_government_requirements
WHERE project_id = ?
ORDER BY id ASC',
[$id]
);
$partnerRows = dbFetchAll(
'SELECT partner_name, role_description
FROM project_partners
WHERE project_id = ?
ORDER BY id ASC',
[$id]
);
$procurementRows = dbFetchAll(
'SELECT method_name, notes
FROM project_procurement_methods
WHERE project_id = ?
ORDER BY id ASC',
[$id]
);
$contactRows = dbFetchAll(
'SELECT contact_name, role_description, phone, email, notes
FROM project_contacts
WHERE project_id = ?
ORDER BY id ASC',
[$id]
);
$budgets = dbFetchAll("SELECT b.*, COALESCE(SUM(bl.estimated_amount),0) AS line_total, COUNT(bl.id) AS line_count FROM project_budgets b LEFT JOIN project_budget_lines bl ON bl.budget_id = b.id WHERE b.project_id = ? GROUP BY b.id ORDER BY b.version_no DESC", [$id]);
$approvedBudgetId = 0;
$draftBudgetId = 0;
$budgetLinesByBudgetId = [];
if ($budgets) {
$budgetIds = array_map(static function ($budget) { return (int)$budget['id']; }, $budgets);
if ($budgetIds) {
$placeholders = implode(',', array_fill(0, count($budgetIds), '?'));
$budgetLines = dbFetchAll(
"SELECT * FROM project_budget_lines WHERE budget_id IN ($placeholders) ORDER BY budget_id, id",
$budgetIds
);
foreach ($budgetLines as $budgetLine) {
$budgetLinesByBudgetId[(int)$budgetLine['budget_id']][] = $budgetLine;
}
}
}
foreach ($budgets as $budget) {
if ($budget['status'] === 'approved' && !$approvedBudgetId) $approvedBudgetId = (int)$budget['id'];
if ($budget['status'] === 'draft' && !$draftBudgetId) $draftBudgetId = (int)$budget['id'];
}
$fundings = dbFetchAll("SELECT f.*, a.code AS source_account_code, a.name_ar AS source_account_name FROM project_funding_allocations f LEFT JOIN accounts a ON a.id = f.source_account_id WHERE f.project_id = ? ORDER BY f.created_at DESC", [$id]);
$paymentEvidence = dbFetchAll("SELECT pe.*, a.code AS source_account_code, a.name_ar AS source_account_name, je.entry_code, u.full_name AS documented_by_name
FROM project_payment_evidence pe
LEFT JOIN accounts a ON a.id = pe.source_account_id
LEFT JOIN journal_entries je ON je.id = pe.journal_entry_id
LEFT JOIN users u ON u.id = pe.documented_by
WHERE pe.project_id = ? ORDER BY pe.id DESC", [$id]);
$expenses = dbFetchAll("SELECT e.*, je.entry_code, pd.title AS primary_document_title, lh.id AS labor_id, lh.provider_name AS labor_provider_name FROM project_expenses e LEFT JOIN journal_entries je ON je.id = e.journal_entry_id LEFT JOIN project_documents pd ON pd.id = e.primary_document_id LEFT JOIN project_labor_helpers lh ON lh.project_id = e.project_id AND e.transaction_reference = CONCAT('LABOR:', lh.id) WHERE e.project_id = ? ORDER BY e.expense_date DESC, e.id DESC", [$id]);
$financialSummary = akp_project_financial_requirement($id);
$approvedBudgetTotal = 0.0;
if ($approvedBudgetId > 0) $approvedBudgetTotal = (float)(dbFetchOne('SELECT COALESCE(SUM(estimated_amount), 0) AS total FROM project_budget_lines WHERE budget_id = ?', [$approvedBudgetId])['total'] ?? 0);
$projectExpenseTotal = (float)(dbFetchOne('SELECT COALESCE(SUM(amount), 0) AS total FROM project_expenses WHERE project_id = ?', [$id])['total'] ?? 0);
$projectExpenseRemaining = $approvedBudgetTotal - $projectExpenseTotal;
$documents = dbFetchAll("SELECT d.*, u.full_name AS uploader_name FROM project_documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.project_id = ? AND d.document_type <> 'receipt' ORDER BY d.id DESC", [$id]);
$milestones = dbFetchAll('SELECT m.*, u.full_name AS creator_name FROM project_milestones m LEFT JOIN users u ON u.id = m.created_by WHERE m.project_id = ? ORDER BY m.planned_date, m.id', [$id]);
$progressUpdates = dbFetchAll('SELECT p.*, u.full_name AS submitter_name FROM project_progress_updates p LEFT JOIN users u ON p.submitted_by = u.id WHERE p.project_id = ? ORDER BY p.update_date DESC', [$id]);
$labors = dbFetchAll("SELECT lh.*, u.full_name AS supervisor_name, pe.id AS payment_expense_id, pe.expense_date AS payment_date, pe.amount AS paid_amount, pe.primary_document_id AS payment_receipt_id, je.entry_code AS payment_entry_code FROM project_labor_helpers lh LEFT JOIN users u ON u.id = lh.supervisor_user_id LEFT JOIN project_expenses pe ON pe.project_id = lh.project_id AND pe.transaction_reference = CONCAT('LABOR:', lh.id) AND pe.status = 'posted' LEFT JOIN journal_entries je ON je.id = pe.journal_entry_id WHERE lh.project_id = ? ORDER BY lh.id DESC", [$id]);
$team = dbFetchAll('SELECT pt.*, u.full_name, u.username FROM project_team pt JOIN users u ON u.id = pt.user_id WHERE pt.project_id = ? AND pt.unassigned_at IS NULL ORDER BY pt.section_code, pt.is_lead DESC, u.full_name', [$id]);
$primarySupervisor = dbFetchOne("SELECT u.id, u.full_name, u.username FROM project_supervisor_assignments psa JOIN users u ON u.id = psa.supervisor_user_id WHERE psa.project_id = ? AND psa.ended_at IS NULL ORDER BY psa.id DESC LIMIT 1", [$id]);
$history = dbFetchAll('SELECT h.*, u.full_name FROM project_status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.project_id = ? ORDER BY h.created_at DESC LIMIT 20', [$id]);
foreach ($milestones as $milestoneHistory) {
$history[] = [
'old_status' => 'إضافة مرحلة',
'new_status' => $milestoneHistory['title'],
'reason' => 'التاريخ المخطط: ' . ($milestoneHistory['planned_date'] ?: '—') . ' | نسبة الإنجاز: ' . number_format((float)$milestoneHistory['completion_percent'], 2) . '% | وصف المرحلة: ' . ($milestoneHistory['description'] ?: '—'),
'full_name' => $milestoneHistory['creator_name'] ?? 'نظام',
'created_at' => $milestoneHistory['created_at'] ?? ($milestoneHistory['planned_date'] ?? '')
];
}
usort($history, static function ($a, $b) { return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')); });
$history = array_slice($history, 0, 20);
$closureRequest = dbFetchOne("SELECT h.*, u.full_name AS requester_name FROM project_status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.project_id = ? AND h.new_status IN ('closure_requested', 'reopen_requested') ORDER BY h.id DESC LIMIT 1", [$id]);
$closed = akp_project_is_closed($id);
$role = akp_role();
$approval = dbFetchOne('SELECT * FROM project_approval WHERE project_id = ?', [$id]) ?: ['approval_status' => 'approved'];
$errors = [];
function akp_redirect_project(int $id): void {
header('Location: ' . APP_URL . 'modules/projects/view.php?id=' . $id);
exit();
}
function akp_post_value(string $name, string $default = ''): string {
return trim((string)($_POST[$name] ?? $default));
}
/**
* Final project approval is a funding COMMITMENT, not a spending event: it earmarks the
* project's funding allocations (draft -> posted) but never touches the general ledger or
* real cash. Only a posted project_expense (see 'post_expense' below) moves real money.
* This mirrors how every other module in this system recognizes cash on a cash basis.
*/
function akp_commit_project_funding(int $projectId): void {
dbExecute(
"UPDATE project_funding_allocations
SET status = 'posted', approved_by = ?, posted_by = ?, posted_at = NOW()
WHERE project_id = ? AND status = 'draft'",
[akp_user_id(), akp_user_id(), $projectId]
);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (!verify_csrf()) {
flash('error', 'انتهت صلاحية الجلسة.');
akp_redirect_project($id);
}
$action = (string)($_POST['action'] ?? '');
$project = akp_get_project($id);
$closed = akp_project_is_closed($id);
try {
if ($action === 'submit_project') {
if ($role !== 'projects_manager') throw new RuntimeException('إرسال المشروع للاعتماد متاح لمدير المشاريع فقط.');
$approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
if (!$approvalCheck || !in_array($approvalCheck['approval_status'], ['draft', 'rejected'], true)) throw new RuntimeException('المشروع ليس في حالة تسمح بالإرسال.');
dbExecute("UPDATE project_approval SET approval_status = 'submitted', submitted_by = ?, submitted_at = NOW(), rejection_reason = NULL, fm_rejection_reason = NULL WHERE project_id = ?", [akp_user_id(), $id]);
akp_audit('SUBMIT_APPROVAL', 'project_approval', $id, ['approval_status' => $approvalCheck['approval_status']], ['approval_status' => 'submitted']);
// Reuse the existing event-aware FM notification infrastructure.
// Delivery is isolated from the completed project state transition,
// and unread notifications are deduplicated by project/event reference.
ak_transaction_review_notify_fm_event(
$id,
'project_submission',
'مشروع بانتظار المراجعة المالية',
'المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') بانتظار مراجعة المدير المالي.',
APP_URL . 'modules/projects/view.php?id=' . $id
);
$_SESSION['project_toast_success'] = 'تم إرسال المشروع إلى المدير المالي للمراجعة والاعتماد المبدئي.';
} elseif ($action === 'fm_approve_project') {
if ($role !== 'financial_manager') throw new RuntimeException('اعتماد المشروع مالياً محصور بالمدير المالي.');
$approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
if (!$approvalCheck || $approvalCheck['approval_status'] !== 'submitted') throw new RuntimeException('المشروع ليس في حالة انتظار الاعتماد المالي.');
// Financial approval is only valid against an explicitly approved budget.
// The lifecycle amount may contain an initial proposal and must not be
// treated as an approved accounting basis.
$approvedBudgetCheck = dbFetchOne(
"SELECT b.id,
COALESCE(SUM(bl.estimated_amount), 0) AS budget_total
FROM project_budgets b
LEFT JOIN project_budget_lines bl ON bl.budget_id = b.id
WHERE b.project_id = ?
AND b.status = 'approved'
GROUP BY b.id
ORDER BY b.version_no DESC
LIMIT 1",
[$id]
);
$approvedBudgetTotal = (float)($approvedBudgetCheck['budget_total'] ?? 0);
if (!$approvedBudgetCheck || $approvedBudgetTotal <= 0) {
throw new RuntimeException('لا يمكن اعتماد المشروع مالياً قبل اعتماد نسخة ميزانية سارية بمبلغ أكبر من صفر.');
}
$fundingTotal = (float)(dbFetchOne(
"SELECT COALESCE(SUM(amount), 0) AS n
FROM project_funding_allocations
WHERE project_id = ? AND status = 'draft'",
[$id]
)['n'] ?? 0);
if (abs($fundingTotal - $approvedBudgetTotal) > 0.01) {
$remaining = max(0, $approvedBudgetTotal - $fundingTotal);
throw new RuntimeException('لا يمكن اعتماد المشروع مالياً قبل اكتمال تخصيص التمويل من حسابات المؤسسة. الميزانية: ' . number_format($approvedBudgetTotal, 2) . '، المخصص: ' . number_format($fundingTotal, 2) . '، المتبقي: ' . number_format($remaining, 2) . '.');
}
dbExecute("UPDATE project_approval SET approval_status = 'fm_approved', fm_reviewed_by = ?, fm_reviewed_at = NOW() WHERE project_id = ?", [akp_user_id(), $id]);
akp_audit('FM_APPROVE_PROJECT', 'project_approval', $id, ['approval_status' => 'submitted'], ['approval_status' => 'fm_approved']);
// Notify active General Manager recipients through the existing notification infrastructure.
// Notification delivery is isolated so it cannot roll back the completed FM approval.
try {
$gmUsers = dbFetchAll(
"SELECT u.id
FROM users u
JOIN roles r ON u.role_id = r.id
WHERE r.code IN ('general_manager', 'vice_general_manager')
AND u.is_active = 1"
);
foreach ($gmUsers as $gmUser) {
ak_transaction_review_notify_event(
(int)$gmUser['id'],
'مشروع بانتظار الاعتماد النهائي',
'المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') تم اعتماده مالياً وبانتظار اعتماد المدير العام.',
APP_URL . 'modules/projects/view.php?id=' . $id,
$id,
'project_fm_approval'
);
}
} catch (Throwable $notificationError) {
// Notification delivery must never roll back the completed FM approval.
}
$_SESSION['project_toast_success'] = 'تم اعتماد المشروع مالياً. المشروع الآن بانتظار اعتماد المدير العام.';
} elseif ($action === 'fm_return_to_review') {
if ($role !== 'financial_manager') throw new RuntimeException('إعادة المشروع للمراجعة المالية متاحة للمدير المالي فقط.');
$reason = akp_post_value('return_reason');
if ($reason === '') throw new RuntimeException('سبب إعادة المشروع للمراجعة المالية مطلوب.');
$approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
if (!$approvalCheck || $approvalCheck['approval_status'] !== 'fm_approved') throw new RuntimeException('المشروع ليس في حالة اعتماد مالي تسمح بإعادته للمراجعة.');
dbExecute("UPDATE project_approval SET approval_status = 'submitted' WHERE project_id = ?", [$id]);
akp_audit('FM_RETURN_TO_REVIEW', 'project_approval', $id, ['approval_status' => 'fm_approved'], ['approval_status' => 'submitted', 'reason' => $reason]);
$_SESSION['project_toast_success'] = 'تمت إعادة المشروع إلى مرحلة المراجعة المالية لاستكمال تخصيص التمويل.';
} elseif ($action === 'fm_reject_project') {
if ($role !== 'financial_manager') throw new RuntimeException('رفض المشروع مالياً محصور بالمدير المالي.');
$reason = akp_post_value('rejection_reason');
if ($reason === '') throw new RuntimeException('سبب الرفض المالي مطلوب.');
$approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
if (!$approvalCheck || $approvalCheck['approval_status'] !== 'submitted') throw new RuntimeException('المشروع ليس في حالة انتظار الاعتماد المالي.');
dbExecute("UPDATE project_approval SET approval_status = 'rejected', fm_rejection_reason = ?, fm_reviewed_by = ?, fm_reviewed_at = NOW() WHERE project_id = ?", [$reason, akp_user_id(), $id]);
akp_audit('FM_REJECT_PROJECT', 'project_approval', $id, ['approval_status' => 'submitted'], ['approval_status' => 'rejected', 'reason' => $reason]);
$_SESSION['project_toast_success'] = 'تم رفض المشروع مالياً وإعادته لمدير المشاريع.';
} elseif ($action === 'approve_project') {
if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager'], true)) throw new RuntimeException('اعتماد المشروع نهائياً محصور بالمدير العام أو نائبه.');
$approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
if (!$approvalCheck || $approvalCheck['approval_status'] !== 'fm_approved') throw new RuntimeException('المشروع لم يتم اعتماده مالياً بعد.');
// Final approval must use the currently approved budget, never a draft
// budget or a stale/proposed lifecycle amount.
$approvedBudgetCheck = dbFetchOne(
"SELECT b.id,
COALESCE(SUM(bl.estimated_amount), 0) AS budget_total
FROM project_budgets b
LEFT JOIN project_budget_lines bl ON bl.budget_id = b.id
WHERE b.project_id = ?
AND b.status = 'approved'
GROUP BY b.id
ORDER BY b.version_no DESC
LIMIT 1",
[$id]
);
$budgetAmount = (float)($approvedBudgetCheck['budget_total'] ?? 0);
if (!$approvedBudgetCheck || $budgetAmount <= 0) {
throw new RuntimeException('لا يمكن اعتماد المشروع نهائياً قبل وجود نسخة ميزانية سارية ومعتمدة بمبلغ أكبر من صفر.');
}
$financialRequirement = akp_project_financial_requirement($id, $budgetAmount)['total_financial_requirement'];
// Funding must cover the approved project budget plus all recorded
// governmental fees before the single GM accounting release.
$fundingTotal = (float)(dbFetchOne(
"SELECT COALESCE(SUM(amount), 0) AS n
FROM project_funding_allocations
WHERE project_id = ?",
[$id]
)['n'] ?? 0);
if (abs($fundingTotal - $financialRequirement) > 0.01) {
throw new RuntimeException('لا يمكن اعتماد المشروع نهائياً قبل أن يساوي إجمالي تخصيصات التمويل إجمالي المتطلبات المالية (الميزانية + الرسوم الحكومية).');
}
try {
dbExecute('START TRANSACTION');
dbExecute("UPDATE project_approval SET approval_status = 'approved', approved_by = ?, approved_at = NOW() WHERE project_id = ?", [akp_user_id(), $id]);
/*
* Final approval does not launch the project. It returns the
* fully approved project to the Projects Manager for review.
* The PM must explicitly launch it before operational access
* is opened to the assigned Project Supervisor.
*/
dbExecute('UPDATE other_projects SET status = \'planned\' WHERE id = ?', [$id]);
dbExecute('UPDATE project_lifecycle SET lifecycle_status = \'planned\' WHERE project_id = ?', [$id]);
dbExecute('UPDATE project_lifecycle SET final_budget_amount = ? WHERE project_id = ?', [$financialRequirement, $id]);
// Earmark the funding: no ledger entry, no cash movement. Real cash only moves
// later, per actual expense or documented payment — see 'post_expense' below and
// project_payment_receipt.php, which is where journal_entry_id below gets filled in.
akp_commit_project_funding($id);
$entryId = null;
// Create one documentary payment-evidence row per approved funding source, with
// no journal entry yet: nothing has actually been paid or documented at this point.
$paymentRows = dbFetchAll(
"SELECT f.id, f.source_account_id, f.amount, f.currency_code, f.allocation_date, a.code AS source_code
FROM project_funding_allocations f
INNER JOIN accounts a ON a.id = f.source_account_id
WHERE f.project_id = ?",
[$id]
);
foreach ($paymentRows as $paymentRow) {
$method = akp_project_payment_method_from_account_code((string)$paymentRow['source_code']);
if ($method === null) {
throw new RuntimeException('مصدر تمويل المشروع لا يملك طريقة دفع معروفة.');
}
$existingEvidence = dbFetchOne(
'SELECT id FROM project_payment_evidence WHERE funding_allocation_id = ? LIMIT 1',
[(int)$paymentRow['id']]
);
if (!$existingEvidence) {
dbExecute(
"INSERT INTO project_payment_evidence
(project_id, funding_allocation_id, source_account_id, journal_entry_id, payment_method, amount, currency_code, payment_date, status, created_at)
VALUES (?,?,?,?,?,?,?,CURDATE(),'pending',NOW())",
[$id, (int)$paymentRow['id'], (int)$paymentRow['source_account_id'], $entryId, $method, (float)$paymentRow['amount'], $paymentRow['currency_code'] ?: ($project['currency_code'] ?: 'SDG')]
);
}
}
dbExecute('COMMIT');
akp_audit('GM_APPROVE_PROJECT', 'project_approval', $id, ['approval_status' => 'fm_approved'], ['approval_status' => 'approved']);
$_SESSION['project_toast_success'] = 'تم اعتماد المشروع نهائياً. التمويل مخصص ومحجوز للمشروع، ولن يُخصم من السيولة الفعلية إلا عند توثيق كل دفعة فعلية على حدة.';
} catch (Throwable $e) {
dbExecute('ROLLBACK');
throw $e;
}
} elseif ($action === 'reject_project') {
if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager'], true)) throw new RuntimeException('رفض المشروع نهائياً محصور بالمدير العام أو نائبه.');
$reason = akp_post_value('rejection_reason');
if ($reason === '') throw new RuntimeException('سبب الرفض مطلوب.');
$approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
if (!$approvalCheck || $approvalCheck['approval_status'] !== 'fm_approved') throw new RuntimeException('المشروع ليس في حالة انتظار الاعتماد النهائي.');
// GM rejection returns the project to FM review first.
// The FM then performs the financial rejection that returns the project to PM.
dbExecute("UPDATE project_approval SET approval_status = 'submitted', rejection_reason = ?, approved_by = NULL, approved_at = NULL WHERE project_id = ?", [$reason, $id]);
akp_audit('REJECT_PROJECT', 'project_approval', $id, ['approval_status' => 'fm_approved'], ['approval_status' => 'submitted', 'reason' => $reason]);
try {
$fmUsers = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code IN ('financial_manager', 'fm', 'finance') AND u.is_active = 1");
foreach ($fmUsers as $fmUser) {
ak_transaction_review_notify_event(
(int)$fmUser['id'],
'المشروع مرفوض من المدير العام ويحتاج مراجعة مالية',
'المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') رفضه المدير العام ويحتاج مراجعة المدير المالي قبل إعادته لمدير المشاريع. السبب: ' . $reason,
APP_URL . 'modules/projects/view.php?id=' . $id,
$id,
'project_gm_rejection'
);
}
} catch (Throwable $notificationError) {}
$_SESSION['project_toast_success'] = 'تم رفض المشروع من المدير العام وإعادته إلى المدير المالي للمراجعة.';
} elseif ($action === 'launch_project') {
if ($role !== 'projects_manager') {
throw new RuntimeException('إطلاق المشروع متاح لمدير المشاريع فقط.');
}
$approvalCheck = dbFetchOne(
'SELECT approval_status, approved_at
FROM project_approval
WHERE project_id = ?',
[$id]
);
if (!$approvalCheck || $approvalCheck['approval_status'] !== 'approved') {
throw new RuntimeException('لا يمكن إطلاق المشروع قبل الاعتماد النهائي.');
}
$currentLifecycle = dbFetchOne(
'SELECT lifecycle_status
FROM project_lifecycle
WHERE project_id = ?',
[$id]
);
$currentLifecycleStatus = (string)($currentLifecycle['lifecycle_status'] ?? '');
if ($currentLifecycleStatus !== 'planned') {
throw new RuntimeException('المشروع ليس في حالة انتظار الإطلاق.');
}
$supervisor = dbFetchOne(
"SELECT u.id, u.full_name
FROM project_supervisor_assignments psa
JOIN users u ON u.id = psa.supervisor_user_id
JOIN roles r ON r.id = u.role_id
WHERE psa.project_id = ?
AND psa.ended_at IS NULL
AND u.is_active = 1
AND r.code = 'project_supervisor'
ORDER BY psa.id DESC
LIMIT 1",
[$id]
);
if (!$supervisor) {
throw new RuntimeException('لا يمكن إطلاق المشروع قبل وجود مشرف مشروع أساسي نشط ومُعيّن.');
}
dbExecute('START TRANSACTION');
try {
dbExecute(
"UPDATE project_lifecycle
SET lifecycle_status = 'active'
WHERE project_id = ? AND lifecycle_status = 'planned'",
[$id]
);
dbExecute(
"UPDATE other_projects
SET status = 'active', updated_by = ?
WHERE id = ?",
[akp_user_id(), $id]
);
dbExecute(
"INSERT INTO project_status_history
(project_id, old_status, new_status, reason, changed_by)
VALUES (?,?,?,?,?)",
[$id, 'planned', 'active', 'تم إطلاق المشروع من مدير المشاريع بعد الاعتماد النهائي.', akp_user_id()]
);
akp_audit(
'LAUNCH_PROJECT',
'project_lifecycle',
$id,
['status' => 'planned'],
['status' => 'active', 'supervisor_user_id' => (int)$supervisor['id']]
);
dbExecute('COMMIT');
} catch (Throwable $e) {
dbExecute('ROLLBACK');
throw $e;
}
ak_transaction_review_notify_event(
(int)$supervisor['id'],
'تم إطلاق مشروع جديد للتنفيذ',
'المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') تم إطلاقه وأصبح متاحاً لكم للتنفيذ والمتابعة.',
APP_URL . 'modules/projects/view.php?id=' . $id,
$id,
'project_launch_ps'
);
$_SESSION['project_toast_success'] = 'تم إطلاق المشروع وإبلاغ المشرف المعيّن به.';
} elseif ($action === 'change_status') {
// ... (Original change_status logic preserved exactly)
$newStatus = akp_post_value('new_status');
if (!akp_can_edit_section('operations', $id) || $closed) throw new RuntimeException('لا تملك صلاحية تغيير حالة هذا المشروع.');
if (!in_array($newStatus, ['planned','active','completed','under_review','cancelled'], true)) throw new RuntimeException('الحالة غير صالحة.');
$oldStatus = (string)($project['lifecycle_status'] ?: $project['status']);
$legacyStatus = in_array($newStatus, ['planned','active','completed','cancelled'], true) ? $newStatus : 'completed';
dbExecute('UPDATE other_projects SET status = ?, updated_by = ? WHERE id = ?', [$legacyStatus, akp_user_id(), $id]);
dbExecute('UPDATE project_lifecycle SET lifecycle_status = ? WHERE project_id = ?', [$newStatus, $id]);
dbExecute('INSERT INTO project_status_history (project_id, old_status, new_status, reason, changed_by) VALUES (?,?,?,?,?)', [$id, $oldStatus, $newStatus, akp_post_value('reason') ?: null, akp_user_id()]);
akp_audit('STATUS_CHANGE', 'project_lifecycle', $id, ['status' => $oldStatus], ['status' => $newStatus]);
$_SESSION['project_toast_success'] = 'تم تحديث حالة المشروع.';
} elseif ($action === 'add_budget') {
// ... (Original add_budget logic preserved exactly)
if (!akp_can_prepare_finance($id) || $closed) throw new RuntimeException('إعداد الميزانية قبل المراجعة المالية محصور بمدير المشاريع.');
$name = akp_post_value('budget_name');
$lineCategory = akp_post_value('line_category');
$lineDescription = akp_post_value('line_description');
$estimate = (float)($_POST['estimated_amount'] ?? 0);
if ($name === '' || $lineCategory === '' || $lineDescription === '' || $estimate <= 0) throw new RuntimeException('اسم الميزانية وبندها ووصفه ومبلغه مطلوبة.');
$nextVersion = (int)(dbFetchOne('SELECT COALESCE(MAX(version_no),0) + 1 AS n FROM project_budgets WHERE project_id = ?', [$id])['n'] ?? 1);
dbExecute('INSERT INTO project_budgets (project_id, version_no, budget_name, currency_code, status, created_by) VALUES (?,?,?,?,?,?)', [$id, $nextVersion, $name, $project['currency_code'] ?: 'SDG', 'draft', akp_user_id()]);
$budgetId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
dbExecute('INSERT INTO project_budget_lines (budget_id, category, description, account_id, estimated_amount, approved_amount, notes, created_by) VALUES (?,?,?,?,?,?,?,?)', [$budgetId, $lineCategory, $lineDescription, (int)($_POST['budget_account_id'] ?? 0) ?: null, $estimate, null, akp_post_value('line_notes') ?: null, akp_user_id()]);
akp_audit('CREATE', 'project_budget', $budgetId, null, ['project_id' => $id, 'version_no' => $nextVersion, 'amount' => $estimate]);
$_SESSION['project_toast_success'] = 'تم إنشاء نسخة ميزانية وإضافة البند الأول.';
} elseif ($action === 'add_budget_line') {
// ... (Original add_budget_line logic preserved exactly)
if (!akp_can_prepare_finance($id) || $closed) throw new RuntimeException('إعداد بنود الميزانية قبل المراجعة المالية محصور بمدير المشاريع.');
$budgetId = (int)($_POST['budget_id'] ?? 0);
$budget = dbFetchOne('SELECT * FROM project_budgets WHERE id = ? AND project_id = ?', [$budgetId, $id]);
if (!$budget || $budget['status'] !== 'draft') throw new RuntimeException('لا يمكن تعديل نسخة ميزانية معتمدة.');
$category = akp_post_value('line_category');
$description = akp_post_value('line_description');
$estimate = (float)($_POST['estimated_amount'] ?? 0);
if ($category === '' || $description === '' || $estimate <= 0) throw new RuntimeException('الفئة والوصف والمبلغ التقديري مطلوبة.');
dbExecute('INSERT INTO project_budget_lines (budget_id, category, description, account_id, estimated_amount, notes, created_by) VALUES (?,?,?,?,?,?,?)', [$budgetId, $category, $description, (int)($_POST['budget_account_id'] ?? 0) ?: null, $estimate, akp_post_value('line_notes') ?: null, akp_user_id()]);
akp_audit('CREATE', 'project_budget_line', $budgetId, null, ['project_id' => $id, 'category' => $category, 'amount' => $estimate]);
$_SESSION['project_toast_success'] = 'تمت إضافة بند الميزانية.';
} elseif ($action === 'add_funding') {
if (!akp_can_manage_funding($id) || $closed) {
throw new RuntimeException('إضافة تخصيصات التمويل واختيار حسابات المصدر محصوران بالمدير المالي.');
}
$approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
if (!$approvalCheck || !in_array($approvalCheck['approval_status'], ['submitted', 'rejected'], true)) {
throw new RuntimeException('يمكن للمدير المالي تسجيل مصادر التمويل فقط أثناء المراجعة المالية أو بعد الرفض المالي.');
}
$sourceAccountIds = $_POST['funding_source_account_id'] ?? [];
$amounts = $_POST['funding_amount'] ?? [];
$dates = $_POST['funding_allocation_date'] ?? [];
$references = $_POST['funding_reference'] ?? [];
$descriptions = $_POST['funding_description'] ?? [];
if (!is_array($sourceAccountIds)) $sourceAccountIds = [$sourceAccountIds];
if (!is_array($amounts)) $amounts = [$amounts];
if (!is_array($dates)) $dates = [$dates];
if (!is_array($references)) $references = [$references];
if (!is_array($descriptions)) $descriptions = [$descriptions];
$approvedBudgetRow = dbFetchOne(
"SELECT COALESCE(SUM(bl.estimated_amount), 0) AS n
FROM project_budgets b
JOIN project_budget_lines bl ON bl.budget_id = b.id
WHERE b.project_id = ? AND b.status = 'approved'",
[$id]
);
$approvedBudgetTotal = (float)($approvedBudgetRow['n'] ?? 0);
if ($approvedBudgetTotal <= 0) {
throw new RuntimeException('لا يمكن تسجيل التمويل قبل اعتماد نسخة الميزانية.');
}
$financialRequirement = akp_project_financial_requirement($id, $approvedBudgetTotal)['total_financial_requirement'];
$existingTotal = (float)(dbFetchOne(
"SELECT COALESCE(SUM(amount),0) AS n
FROM project_funding_allocations
WHERE project_id = ? AND status = 'draft'",
[$id]
)['n'] ?? 0);
$rowsToInsert = [];
$batchTotal = 0.0;
$rowCount = max(count($sourceAccountIds), count($amounts));
if ($rowCount <= 0) {
throw new RuntimeException('يجب إضافة مصدر تمويل واحد على الأقل.');
}
for ($i = 0; $i < $rowCount; $i++) {
$sourceAccountId = (int)($sourceAccountIds[$i] ?? 0);
$amount = (float)($amounts[$i] ?? 0);
$sourceAccount = $sourceAccountId ? dbFetchOne('SELECT id, code, name_ar FROM accounts WHERE id = ?', [$sourceAccountId]) : null;
if (!$sourceAccount || !in_array($sourceAccount['code'], ['1100', '1200', '1300'], true)) {
throw new RuntimeException('كل تخصيص يجب أن يستخدم أحد مصادر التمويل التالية: الصندوق النقدي (1100)، البنك (1200)، أو المحفظة الإلكترونية (1300).');
}
if ($amount <= 0) {
throw new RuntimeException('يجب أن يكون مبلغ كل تخصيص تمويل أكبر من صفر.');
}
$rowsToInsert[] = [
'account' => $sourceAccount,
'amount' => $amount,
'date' => trim((string)($dates[$i] ?? '')) ?: date('Y-m-d'),
'reference' => trim((string)($references[$i] ?? '')) ?: null,
'description' => trim((string)($descriptions[$i] ?? '')) ?: null
];
$batchTotal += $amount;
}
if ($existingTotal + $batchTotal > $financialRequirement + 0.01) {
$remaining = max(0, $financialRequirement - $existingTotal);
throw new RuntimeException('لا يمكن أن يتجاوز إجمالي التمويل إجمالي المتطلبات المالية (' . number_format($financialRequirement, 2) . '). المتبقي المتاح للتخصيص: ' . number_format($remaining, 2) . '.');
}
$activeBudgetId = (int)(dbFetchOne(
"SELECT id FROM project_budgets WHERE project_id = ? AND status = 'approved' ORDER BY version_no DESC LIMIT 1",
[$id]
)['id'] ?? 0) ?: null;
foreach ($rowsToInsert as $row) {
dbExecute('INSERT INTO project_funding_allocations (project_id, budget_id, source_type, source_account_id, destination_account_id, transaction_id, amount, currency_code, allocation_date, reference_number, description, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
$id, $activeBudgetId, $row['account']['code'], $row['account']['id'], null, null, $row['amount'],
$project['currency_code'] ?: 'SDG',
$row['date'],
$row['reference'],
$row['description'],
'draft', akp_user_id()
]);
$allocationId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
akp_audit('CREATE', 'project_funding_allocation', $allocationId, null, [
'project_id' => $id,
'amount' => $row['amount'],
'source_account' => $row['account']['code']
]);
}
$remainingAfter = max(0, $financialRequirement - $existingTotal - $batchTotal);
$_SESSION['project_toast_success'] = 'تم تسجيل تخصيصات التمويل بنجاح. المتبقي من إجمالي المتطلبات المالية: ' . number_format($remainingAfter, 2) . '.';
} elseif ($action === 'edit_funding') {
$approvalStatus = (string)(dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id])['approval_status'] ?? '');
if ($closed) throw new RuntimeException('لا يمكن تعديل تخصيص تمويل لمشروع مغلق.');
if (!in_array($approvalStatus, ['submitted', 'rejected'], true) || !akp_can_manage_funding($id)) {
throw new RuntimeException('تعديل تخصيصات التمويل أثناء المراجعة المالية أو بعد الرفض المالي محصور بالمدير المالي.');
}
$allocationId = (int)($_POST['allocation_id'] ?? 0);
$allocation = dbFetchOne('SELECT * FROM project_funding_allocations WHERE id = ? AND project_id = ?', [$allocationId, $id]);
if (!$allocation) throw new RuntimeException('تخصيص التمويل غير موجود.');
$sourceAccountId = (int)($_POST['source_account_id'] ?? 0);
$amount = (float)($_POST['funding_amount'] ?? 0);
$sourceAccount = $sourceAccountId ? dbFetchOne('SELECT id, code, name_ar FROM accounts WHERE id = ?', [$sourceAccountId]) : null;
if (!$sourceAccount || !in_array($sourceAccount['code'], ['1100', '1200', '1300'], true)) {
throw new RuntimeException('يجب اختيار مصدر تمويل صالح: الصندوق النقدي (1100)، البنك (1200)، أو المحفظة الإلكترونية (1300).');
}
if ($amount <= 0) throw new RuntimeException('مبلغ التمويل يجب أن يكون أكبر من صفر.');
$approvedBudgetRow = dbFetchOne(
"SELECT COALESCE(SUM(bl.estimated_amount), 0) AS n
FROM project_budgets b
JOIN project_budget_lines bl ON bl.budget_id = b.id
WHERE b.project_id = ? AND b.status = 'approved'",
[$id]
);
$proposedBudget = (float)($approvedBudgetRow['n'] ?? 0);
if ($proposedBudget <= 0) {
throw new RuntimeException('لا يمكن تسجيل التمويل قبل اعتماد نسخة الميزانية.');
}
$financialRequirement = akp_project_financial_requirement($id, $proposedBudget)['total_financial_requirement'];
$otherTotal = (float)(dbFetchOne(
'SELECT COALESCE(SUM(amount),0) AS n FROM project_funding_allocations WHERE project_id = ? AND id <> ?',
[$id, $allocationId]
)['n'] ?? 0);
if ($otherTotal + $amount > $financialRequirement + 0.01) {
$remaining = max(0, $financialRequirement - $otherTotal);
throw new RuntimeException('لا يمكن أن يتجاوز إجمالي التمويل إجمالي المتطلبات المالية (' . number_format($financialRequirement, 2) . '). الحد الأقصى لهذا السجل: ' . number_format($remaining, 2) . '.');
}
dbExecute('START TRANSACTION');
try {
$oldAmount = (float)$allocation['amount'];
// Funding allocations are earmarks, not ledger entries (see akp_commit_project_funding):
// editing one after approval is just a data correction, with no journal to reverse or recreate.
dbExecute('UPDATE project_funding_allocations SET source_type = ?, source_account_id = ?, amount = ?, currency_code = ?, allocation_date = ?, reference_number = ?, description = ? WHERE id = ? AND project_id = ?', [
$sourceAccount['code'],
$sourceAccount['id'],
$amount,
akp_post_value('funding_currency', $project['currency_code'] ?: 'SDG'),
akp_post_value('allocation_date', date('Y-m-d')),
akp_post_value('funding_reference') ?: null,
akp_post_value('funding_description') ?: null,
$allocationId,
$id
]);
dbExecute('COMMIT');
akp_audit('UPDATE', 'project_funding_allocation', $allocationId,
['project_id' => $id, 'amount' => $oldAmount],
['project_id' => $id, 'amount' => $amount, 'approval_status' => $approvalStatus]
);
$_SESSION['project_toast_success'] = 'تم تعديل تخصيص التمويل بنجاح.';
} catch (Throwable $e) {
dbExecute('ROLLBACK');
throw $e;
}
} elseif ($action === 'delete_funding') {
$approvalStatus = (string)(dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id])['approval_status'] ?? '');
if ($closed) throw new RuntimeException('لا يمكن حذف تخصيص تمويل من مشروع مغلق.');
if (!in_array($approvalStatus, ['submitted', 'rejected'], true) || !akp_can_manage_funding($id)) {
throw new RuntimeException('حذف تخصيصات التمويل أثناء المراجعة المالية أو بعد الرفض المالي محصور بالمدير المالي.');
}
$allocationId = (int)($_POST['allocation_id'] ?? 0);
$allocation = dbFetchOne('SELECT * FROM project_funding_allocations WHERE id = ? AND project_id = ?', [$allocationId, $id]);
if (!$allocation) throw new RuntimeException('تخصيص التمويل غير موجود.');
dbExecute('START TRANSACTION');
try {
// Funding allocations are earmarks, not ledger entries (see akp_commit_project_funding):
// deleting one after approval is just a data correction, with no journal to reverse or recreate.
dbExecute('DELETE FROM project_funding_allocations WHERE id = ? AND project_id = ?', [$allocationId, $id]);
dbExecute('COMMIT');
akp_audit('DELETE', 'project_funding_allocation', $allocationId,
['project_id' => $id, 'amount' => $allocation['amount']],
['approval_status' => $approvalStatus]
);
$_SESSION['project_toast_success'] = 'تم حذف تخصيص التمويل.';
} catch (Throwable $e) {
dbExecute('ROLLBACK');
throw $e;
}
} elseif ($action === 'edit_budget_line') {
if (!akp_can_prepare_finance($id) || $closed) throw new RuntimeException('تعديل بنود الميزانية قبل المراجعة المالية محصور بمدير المشاريع.');
$lineId = (int)($_POST['line_id'] ?? 0);
$line = dbFetchOne('SELECT bl.*, b.status AS budget_status, b.project_id FROM project_budget_lines bl JOIN project_budgets b ON b.id = bl.budget_id WHERE bl.id = ?', [$lineId]);
if (!$line || (int)$line['project_id'] !== $id) throw new RuntimeException('بند الميزانية غير موجود.');
if ($line['budget_status'] !== 'draft') throw new RuntimeException('لا يمكن تعديل بند من نسخة ميزانية معتمدة.');
$category = akp_post_value('line_category');
$description = akp_post_value('line_description');
$estimate = (float)($_POST['estimated_amount'] ?? 0);
if ($category === '' || $description === '' || $estimate <= 0) throw new RuntimeException('الفئة والوصف والمبلغ التقديري مطلوبة.');
dbExecute('UPDATE project_budget_lines SET category = ?, description = ?, estimated_amount = ?, notes = ? WHERE id = ?', [$category, $description, $estimate, akp_post_value('line_notes') ?: null, $lineId]);
akp_audit('UPDATE', 'project_budget_line', $lineId, ['amount' => $line['estimated_amount']], ['amount' => $estimate]);
$_SESSION['project_toast_success'] = 'تم تعديل بند الميزانية.';
} elseif ($action === 'delete_budget_line') {
if (!akp_can_prepare_finance($id) || $closed) throw new RuntimeException('حذف بنود الميزانية قبل المراجعة المالية محصور بمدير المشاريع.');
$lineId = (int)($_POST['line_id'] ?? 0);
$line = dbFetchOne('SELECT bl.*, b.status AS budget_status, b.project_id FROM project_budget_lines bl JOIN project_budgets b ON b.id = bl.budget_id WHERE bl.id = ?', [$lineId]);
if (!$line || (int)$line['project_id'] !== $id) throw new RuntimeException('بند الميزانية غير موجود.');
if ($line['budget_status'] !== 'draft') throw new RuntimeException('لا يمكن حذف بند من نسخة ميزانية معتمدة.');
dbExecute('DELETE FROM project_budget_lines WHERE id = ?', [$lineId]);
akp_audit('DELETE', 'project_budget_line', $lineId, ['amount' => $line['estimated_amount']], null);
$_SESSION['project_toast_success'] = 'تم حذف بند الميزانية.';
} elseif ($action === 'approve_funding') {
throw new RuntimeException('هذه الخطوة لم تعد مطلوبة — يتم اعتماد كل مصادر التمويل تلقائياً عند الاعتماد المالي للمشروع بالكامل.');
} elseif ($action === 'post_funding') {
throw new RuntimeException('هذه الخطوة لم تعد مطلوبة — يتم ترحيل القيد المحاسبي تلقائياً عند الاعتماد النهائي من المدير العام.');
$_SESSION['project_toast_success'] = 'تم ترحيل تخصيص التمويل بقيد مزدوج متوازن.';
} elseif ($action === 'add_ps_expense') {
if ($role !== 'project_supervisor' || !akp_is_primary_supervisor($id) || $closed) throw new RuntimeException('إدخال مصروفات التنفيذ متاح لمشرف المشروع المكلّف فقط.');
$amount = (float)($_POST['expense_amount'] ?? 0);
$description = akp_post_value('expense_description');
$expenseDate = akp_post_value('expense_date', date('Y-m-d'));
$category = akp_post_value('expense_category', 'تنفيذ المشروع');
if ($amount <= 0 || $description === '') throw new RuntimeException('وصف المصروف والمبلغ مطلوبان.');
$approvedBudgetTotal = (float)(dbFetchOne("SELECT COALESCE(SUM(COALESCE(bl.approved_amount, bl.estimated_amount)), 0) AS total FROM project_budgets b JOIN project_budget_lines bl ON bl.budget_id = b.id WHERE b.id = (SELECT pb.id FROM project_budgets pb WHERE pb.project_id = ? AND pb.status = 'approved' ORDER BY pb.version_no DESC, pb.id DESC LIMIT 1)", [$id])['total'] ?? 0);
$existingExpenseTotal = (float)(dbFetchOne("SELECT COALESCE(SUM(amount), 0) AS total FROM project_expenses WHERE project_id = ? AND status = 'posted'", [$id])['total'] ?? 0);
if ($approvedBudgetTotal <= 0) throw new RuntimeException('لا توجد ميزانية معتمدة من المدير المالي يمكن تسجيل المصروفات عليها.');
if (($existingExpenseTotal + $amount) > ($approvedBudgetTotal + 0.01)) {
$remaining = max(0, $approvedBudgetTotal - $existingExpenseTotal);
throw new RuntimeException('المبلغ يتجاوز الرصيد المتبقي من الميزانية المعتمدة. المتبقي: ' . number_format($remaining, 2) . ' ' . ($project['currency_code'] ?: 'SDG') . '.');
}
$primaryDocumentId = null;
$storedAbsolutePath = null;
dbExecute('START TRANSACTION');
try {
if (!empty($_FILES['expense_receipt']['name'])) {
if ($_FILES['expense_receipt']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('فشل في رفع إيصال المصروف.');
$file = $_FILES['expense_receipt'];
if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('حجم إيصال المصروف يجب ألا يتجاوز 10 ميجابايت.');
$mime = mime_content_type($file['tmp_name']);
$allowed = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
if (!isset($allowed[$mime])) throw new RuntimeException('نوع إيصال المصروف غير مسموح. استخدم PDF أو JPG أو PNG.');
$relativeDir = 'storage/documents/projects/' . $id;
$absoluteDir = dirname(__DIR__, 2) . '/' . $relativeDir;
if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true)) throw new RuntimeException('تعذر إنشاء مجلد وثائق المشروع.');
$stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
$storedAbsolutePath = $absoluteDir . '/' . $stored;
if (!move_uploaded_file($file['tmp_name'], $storedAbsolutePath)) throw new RuntimeException('تعذر حفظ إيصال المصروف.');
dbExecute('INSERT INTO project_documents (project_id, document_type, title, file_path, original_name, mime_type, file_size, document_date, issuer, reference_number, amount, currency_code, notes, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$id,'receipt','إيصال مصروف: '.$description,$relativeDir.'/'.$stored,$file['name'],$mime,$file['size'],$expenseDate ?: null,akp_post_value('vendor_name') ?: null,akp_post_value('invoice_number') ?: null,$amount,$project['currency_code'] ?: 'SDG','مرفق مباشرة بالمصروف المسجل بواسطة مشرف المشروع.',akp_user_id()]);
$primaryDocumentId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
}
dbExecute('INSERT INTO project_expenses (project_id, budget_id, budget_line_id, expense_date, category, description, vendor_name, vendor_contact, invoice_number, government_fee_type, amount, currency_code, transaction_reference, expense_account_id, payment_account_id, primary_document_id, status, submitted_by, posted_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$id,$approvedBudgetId > 0 ? $approvedBudgetId : null,null,$expenseDate,$category,$description,akp_post_value('vendor_name') ?: null,null,akp_post_value('invoice_number') ?: null,null,$amount,$project['currency_code'] ?: 'SDG',akp_post_value('transaction_reference') ?: null,null,null,$primaryDocumentId,'posted',akp_user_id(),akp_user_id()]);
$expenseId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
dbExecute('COMMIT');
} catch (Throwable $e) {
dbExecute('ROLLBACK');
if ($storedAbsolutePath && is_file($storedAbsolutePath)) @unlink($storedAbsolutePath);
throw $e;
}
akp_audit('CREATE', 'project_expense', $expenseId, null, ['project_id'=>$id,'amount'=>$amount,'recorded_by_role'=>'project_supervisor','primary_document_id'=>$primaryDocumentId]);
$_SESSION['project_expense_success'] = 'تم تسجيل الدفع وخصم ' . number_format($amount, 2) . ' ' . ($project['currency_code'] ?: 'SDG') . ' من ميزانية المشروع وترحيل المصروف.';
} elseif ($action === 'edit_ps_expense') {
if ($role !== 'project_supervisor' || !akp_is_primary_supervisor($id) || $closed) throw new RuntimeException('تعديل مصروفات التنفيذ متاح لمشرف المشروع المكلّف فقط.');
$expenseId = (int)($_POST['expense_id'] ?? 0);
$expense = dbFetchOne('SELECT * FROM project_expenses WHERE id = ? AND project_id = ?', [$expenseId, $id]);
if (!$expense || $expense['status'] !== 'draft') throw new RuntimeException('لا يمكن تعديل المصروف بعد إرساله أو اعتماده.');
$amount = (float)($_POST['expense_amount'] ?? 0);
$description = akp_post_value('expense_description');
$expenseDate = akp_post_value('expense_date', date('Y-m-d'));
$category = akp_post_value('expense_category', 'تنفيذ المشروع');
if ($amount <= 0 || $description === '') throw new RuntimeException('وصف المصروف والمبلغ مطلوبان.');
$approvedBudgetTotal = (float)(dbFetchOne("SELECT COALESCE(SUM(COALESCE(bl.approved_amount, bl.estimated_amount)), 0) AS total FROM project_budgets b JOIN project_budget_lines bl ON bl.budget_id = b.id WHERE b.id = (SELECT pb.id FROM project_budgets pb WHERE pb.project_id = ? AND pb.status = 'approved' ORDER BY pb.version_no DESC, pb.id DESC LIMIT 1)", [$id])['total'] ?? 0);
$existingExpenseTotal = (float)(dbFetchOne("SELECT COALESCE(SUM(amount), 0) AS total FROM project_expenses WHERE project_id = ? AND status = 'posted' AND id <> ?", [$id, $expenseId])['total'] ?? 0);
if ($approvedBudgetTotal <= 0) throw new RuntimeException('لا توجد ميزانية معتمدة من المدير المالي يمكن تسجيل المصروفات عليها.');
if (($existingExpenseTotal + $amount) > ($approvedBudgetTotal + 0.01)) {
$remaining = max(0, $approvedBudgetTotal - $existingExpenseTotal);
throw new RuntimeException('المبلغ يتجاوز الرصيد المتبقي من الميزانية المعتمدة. المتبقي: ' . number_format($remaining, 2) . ' ' . ($project['currency_code'] ?: 'SDG') . '.');
}
$primaryDocumentId = !empty($expense['primary_document_id']) ? (int)$expense['primary_document_id'] : null;
if (!$primaryDocumentId && empty($_FILES['expense_receipt']['name'])) throw new RuntimeException('إيصال الدفع مطلوب عند تسجيل الدفع وترحيل المصروف.');
$storedAbsolutePath = null;
$oldReceiptPath = null;
dbExecute('START TRANSACTION');
try {
if (!empty($_FILES['expense_receipt']['name'])) {
if ($_FILES['expense_receipt']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('فشل في رفع إيصال المصروف.');
$file = $_FILES['expense_receipt'];
if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('حجم إيصال المصروف يجب ألا يتجاوز 10 ميجابايت.');
$mime = mime_content_type($file['tmp_name']);
$allowed = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
if (!isset($allowed[$mime])) throw new RuntimeException('نوع إيصال المصروف غير مسموح. استخدم PDF أو JPG أو PNG.');
$relativeDir = 'storage/documents/projects/' . $id;
$absoluteDir = dirname(__DIR__, 2) . '/' . $relativeDir;
if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true)) throw new RuntimeException('تعذر إنشاء مجلد وثائق المشروع.');
$stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
$storedAbsolutePath = $absoluteDir . '/' . $stored;
if (!move_uploaded_file($file['tmp_name'], $storedAbsolutePath)) throw new RuntimeException('تعذر حفظ إيصال المصروف.');
$relativePath = $relativeDir . '/' . $stored;
if ($primaryDocumentId) {
$oldDoc = dbFetchOne('SELECT file_path FROM project_documents WHERE id = ? AND project_id = ? AND document_type = \'receipt\'', [$primaryDocumentId, $id]);
$oldReceiptPath = $oldDoc['file_path'] ?? null;
dbExecute('UPDATE project_documents SET title = ?, file_path = ?, original_name = ?, mime_type = ?, file_size = ?, document_date = ?, issuer = ?, reference_number = ?, amount = ?, currency_code = ?, notes = ? WHERE id = ? AND project_id = ?', ['إيصال مصروف: ' . $description, $relativePath, $file['name'], $mime, $file['size'], $expenseDate ?: null, akp_post_value('vendor_name') ?: null, akp_post_value('invoice_number') ?: null, $amount, $project['currency_code'] ?: 'SDG', 'مرفق مباشرة بالمصروف المسجل بواسطة مشرف المشروع.', $primaryDocumentId, $id]);
} else {
dbExecute('INSERT INTO project_documents (project_id, document_type, title, file_path, original_name, mime_type, file_size, document_date, issuer, reference_number, amount, currency_code, notes, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$id,'receipt','إيصال مصروف: '.$description,$relativePath,$file['name'],$mime,$file['size'],$expenseDate ?: null,akp_post_value('vendor_name') ?: null,akp_post_value('invoice_number') ?: null,$amount,$project['currency_code'] ?: 'SDG','مرفق مباشرة بالمصروف المسجل بواسطة مشرف المشروع.',akp_user_id()]);
$primaryDocumentId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
}
} elseif ($primaryDocumentId) {
dbExecute('UPDATE project_documents SET title = ?, document_date = ?, issuer = ?, reference_number = ?, amount = ?, currency_code = ?, notes = ? WHERE id = ? AND project_id = ? AND document_type = \'receipt\'', ['إيصال مصروف: ' . $description, $expenseDate ?: null, akp_post_value('vendor_name') ?: null, akp_post_value('invoice_number') ?: null, $amount, $project['currency_code'] ?: 'SDG', 'مرفق مباشرة بالمصروف المسجل بواسطة مشرف المشروع.', $primaryDocumentId, $id]);
}
dbExecute('UPDATE project_expenses SET expense_date = ?, category = ?, description = ?, vendor_name = ?, invoice_number = ?, amount = ?, currency_code = ?, primary_document_id = ?, status = \'posted\', posted_by = ? WHERE id = ? AND project_id = ?', [$expenseDate, $category, $description, akp_post_value('vendor_name') ?: null, akp_post_value('invoice_number') ?: null, $amount, $project['currency_code'] ?: 'SDG', $primaryDocumentId, akp_user_id(), $expenseId, $id]);
dbExecute('COMMIT');
} catch (Throwable $e) {
dbExecute('ROLLBACK');
if ($storedAbsolutePath && is_file($storedAbsolutePath)) @unlink($storedAbsolutePath);
throw $e;
}
if ($oldReceiptPath && $storedAbsolutePath) {
$oldAbsolutePath = dirname(__DIR__, 2) . '/' . $oldReceiptPath;
if ($oldAbsolutePath !== $storedAbsolutePath && is_file($oldAbsolutePath)) @unlink($oldAbsolutePath);
}
akp_audit('UPDATE', 'project_expense', $expenseId, ['project_id'=>$id,'amount'=>(float)$expense['amount']], ['project_id'=>$id,'amount'=>$amount,'primary_document_id'=>$primaryDocumentId]);
$_SESSION['project_expense_success'] = 'تم تسجيل الدفع وخصم ' . number_format($amount, 2) . ' ' . ($project['currency_code'] ?: 'SDG') . ' من ميزانية المشروع وترحيل المصروف.';
} elseif ($action === 'delete_ps_expense') {
if ($role !== 'project_supervisor' || !akp_is_primary_supervisor($id) || $closed) throw new RuntimeException('حذف مصروفات التنفيذ متاح لمشرف المشروع المكلّف فقط.');
$expenseId = (int)($_POST['expense_id'] ?? 0);
$expense = dbFetchOne('SELECT * FROM project_expenses WHERE id = ? AND project_id = ?', [$expenseId, $id]);
if (!$expense || $expense['status'] !== 'draft') throw new RuntimeException('لا يمكن حذف المصروف بعد إرساله أو اعتماده.');
$receiptPath = null;
if (!empty($expense['primary_document_id'])) {
$receipt = dbFetchOne('SELECT file_path FROM project_documents WHERE id = ? AND project_id = ? AND document_type = \'receipt\'', [(int)$expense['primary_document_id'], $id]);
$receiptPath = $receipt['file_path'] ?? null;
}
dbExecute('START TRANSACTION');
try {
if (!empty($expense['primary_document_id'])) {
dbExecute('DELETE FROM project_documents WHERE id = ? AND project_id = ? AND document_type = \'receipt\'', [(int)$expense['primary_document_id'], $id]);
}
dbExecute('DELETE FROM project_expenses WHERE id = ? AND project_id = ? AND status = \'draft\'', [$expenseId, $id]);
dbExecute('COMMIT');
} catch (Throwable $e) {
dbExecute('ROLLBACK');
throw $e;
}
if ($receiptPath) {
$receiptAbsolutePath = dirname(__DIR__, 2) . '/' . $receiptPath;
if (is_file($receiptAbsolutePath)) @unlink($receiptAbsolutePath);
}
akp_audit('DELETE', 'project_expense', $expenseId, ['project_id'=>$id,'amount'=>(float)$expense['amount']], null);
$_SESSION['project_expense_success'] = 'تم حذف المصروف بنجاح.';
} elseif ($action === 'add_expense') {
throw new RuntimeException('إدخال مصروفات المشروع يتم مباشرة بواسطة مشرف المشروع من ميزانية المشروع المحوّلة إليه، ولا يتم استخدام حسابات المنظمة لهذا المسار.');
// ... (Legacy add_expense logic retained for historical compatibility only)
if (!akp_can_edit_section('finance', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إدخال مصروف.');
$amount = (float)($_POST['expense_amount'] ?? 0);
$description = akp_post_value('expense_description');
if ($amount <= 0 || $description === '') throw new RuntimeException('وصف المصروف والمبلغ مطلوبان.');
$expenseAccount = (int)($_POST['expense_account_id'] ?? 0) ?: null;
$paymentAccount = (int)($_POST['payment_account_id'] ?? 0) ?: null;
if (!$expenseAccount || !$paymentAccount) throw new RuntimeException('حساب المصروف وحساب الدفع مطلوبان.');
$primaryDocumentId = (int)($_POST['primary_document_id'] ?? 0) ?: null;
if ($primaryDocumentId && !dbFetchOne('SELECT id FROM project_documents WHERE id = ? AND project_id = ?', [$primaryDocumentId, $id])) throw new RuntimeException('مستند المصروف غير موجود لهذا المشروع.');
if (!dbFetchOne('SELECT id FROM accounts WHERE id = ?', [$expenseAccount]) || !dbFetchOne('SELECT id FROM accounts WHERE id = ?', [$paymentAccount])) throw new RuntimeException('حساب المصروف أو الدفع غير موجود.');
dbExecute('INSERT INTO project_expenses (project_id, budget_id, budget_line_id, expense_date, category, description, vendor_name, vendor_contact, invoice_number, government_fee_type, amount, currency_code, transaction_reference, expense_account_id, payment_account_id, primary_document_id, status, submitted_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$id, (int)($_POST['expense_budget_id'] ?? 0) ?: null, (int)($_POST['budget_line_id'] ?? 0) ?: null, akp_post_value('expense_date', date('Y-m-d')), akp_post_value('expense_category', 'عام'), $description, akp_post_value('vendor_name') ?: null, akp_post_value('vendor_contact') ?: null, akp_post_value('invoice_number') ?: null, akp_post_value('government_fee_type') ?: null, $amount, akp_post_value('expense_currency', $project['currency_code'] ?: 'SDG'), akp_post_value('transaction_reference') ?: null, $expenseAccount, $paymentAccount, $primaryDocumentId, 'draft', akp_user_id()]);
$expenseId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
akp_audit('CREATE', 'project_expense', $expenseId, null, ['project_id' => $id, 'amount' => $amount]);
$_SESSION['project_toast_success'] = 'تم حفظ المصروف كمسودة. أرفق المستند ثم أرسله للاعتماد.';
} elseif ($action === 'submit_expense') {
// ... (Original submit_expense logic preserved exactly)
if (!akp_can_edit_section('finance', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إرسال المصروف.');
$expenseId = (int)($_POST['expense_id'] ?? 0);
$expense = dbFetchOne('SELECT * FROM project_expenses WHERE id = ? AND project_id = ?', [$expenseId, $id]);
if (!$expense || $expense['status'] !== 'draft') throw new RuntimeException('المصروف ليس في حالة مسودة.');
dbExecute("UPDATE project_expenses SET status = 'submitted', submitted_by = ? WHERE id = ? AND project_id = ?", [akp_user_id(), $expenseId, $id]);
dbExecute("INSERT INTO project_expense_approvals (expense_id, action, actor_id) VALUES (?, 'submitted', ?)", [$expenseId, akp_user_id()]);
$_SESSION['project_toast_success'] = 'تم إرسال المصروف للاعتماد.';
} elseif ($action === 'approve_expense') {
// ... (Original approve_expense logic preserved exactly)
if (!akp_can_edit_section('finance', $id) || $closed) throw new RuntimeException('لا تملك صلاحية اعتماد المصروف.');
$expenseId = (int)($_POST['expense_id'] ?? 0);
$expense = dbFetchOne('SELECT * FROM project_expenses WHERE id = ? AND project_id = ?', [$expenseId, $id]);
if (!$expense || $expense['status'] !== 'submitted') throw new RuntimeException('المصروف ليس في حالة مرسل.');
dbExecute("UPDATE project_expenses SET status = 'approved', approved_by = ? WHERE id = ? AND project_id = ?", [akp_user_id(), $expenseId, $id]);
dbExecute("INSERT INTO project_expense_approvals (expense_id, action, actor_id) VALUES (?, 'approved', ?)", [$expenseId, akp_user_id()]);
$_SESSION['project_toast_success'] = 'تم اعتماد المصروف ويمكن ترحيله محاسبياً.';
} elseif ($action === 'post_expense') {
// ... (Original post_expense logic preserved exactly)
if (!in_array($role, ['admin', 'accountant', 'financial_manager'], true) || $closed) throw new RuntimeException('ترحيل المصروفات محصور بالمدير المالي أو المحاسب.');
$expenseId = (int)($_POST['expense_id'] ?? 0);
$expense = dbFetchOne('SELECT * FROM project_expenses WHERE id = ? AND project_id = ?', [$expenseId, $id]);
if (!$expense || $expense['status'] !== 'approved') throw new RuntimeException('المصروف يجب أن يكون معتمداً أولاً.');
if (!$expense['payment_account_id'] || !$expense['expense_account_id']) throw new RuntimeException('حسابات المصروف والدفع مطلوبة.');
dbExecute('START TRANSACTION');
try {
$entryCode = 'PRJ-EXP-' . $expenseId . '-' . date('YmdHis');
dbExecute("INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by) VALUES (?,?,?,?,?,'posted',?)", [$entryCode, date('Y-m-d'), 'مصروف مشروع: ' . $project['name'] . ' - ' . $expense['description'], 'project_expense', $expenseId, akp_user_id()]);
$entryId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
dbExecute('INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)', [$entryId, $expense['expense_account_id'], $expense['amount'], 0, 'مصروف مشروع']);
dbExecute('INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)', [$entryId, $expense['payment_account_id'], 0, $expense['amount'], 'مصروف مشروع']);
dbExecute("UPDATE project_expenses SET status = 'posted', posted_by = ?, journal_entry_id = ? WHERE id = ?", [akp_user_id(), $entryId, $expenseId]);
dbExecute("INSERT INTO project_expense_approvals (expense_id, action, actor_id) VALUES (?, 'posted', ?)", [$expenseId, akp_user_id()]);
dbExecute('COMMIT');
} catch (Throwable $e) {
dbExecute('ROLLBACK');
throw $e;
}
akp_audit('POST', 'project_expense', $expenseId, ['status' => 'approved'], ['status' => 'posted', 'journal_entry_id' => $entryId]);
$_SESSION['project_toast_success'] = 'تم ترحيل المصروف بقيد مزدوج متوازن.';
} elseif ($action === 'upload_document') {
// ... (Original upload_document logic preserved exactly)
if (!akp_can_edit_section('documents', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إضافة وثائق لهذا المشروع.');
if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('فشل في رفع الملف.');
$file = $_FILES['document'];
if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('حجم وثيقة المشروع يجب ألا يتجاوز 10 ميجابايت.');
$mime = mime_content_type($file['tmp_name']);
$allowed = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx'];
if (!isset($allowed[$mime])) throw new RuntimeException('نوع الملف غير مسموح. استخدم PDF أو JPG أو PNG أو DOCX أو XLSX.');
$relativeDir = 'storage/documents/projects/' . $id;
$absoluteDir = dirname(__DIR__, 2) . '/' . $relativeDir;
if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true)) throw new RuntimeException('تعذر إنشاء مجلد الوثائق.');
$stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
if (!move_uploaded_file($file['tmp_name'], $absoluteDir . '/' . $stored)) throw new RuntimeException('تعذر حفظ الملف.');
$relativePath = $relativeDir . '/' . $stored;
dbExecute('INSERT INTO project_documents (project_id, document_type, title, file_path, original_name, mime_type, file_size, document_date, issuer, reference_number, amount, currency_code, notes, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$id, akp_post_value('document_type', 'other'), akp_post_value('document_title'), $relativePath, $file['name'], $mime, $file['size'], akp_post_value('document_date') ?: null, akp_post_value('document_issuer') ?: null, akp_post_value('document_reference') ?: null, (float)(akp_post_value('document_amount') ?: 0), akp_post_value('document_currency', $project['currency_code'] ?: 'SDG'), akp_post_value('document_notes') ?: null, akp_user_id()]);
$documentId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
akp_audit('UPLOAD', 'project_document', $documentId, null, ['project_id' => $id, 'document_type' => akp_post_value('document_type', 'other')]);
$_SESSION['project_toast_success'] = 'تم حفظ الوثيقة في التخزين المحمي.';
} elseif ($action === 'edit_project_document') {
if ($role !== 'project_supervisor' || !akp_can_edit_section('documents', $id) || $closed) throw new RuntimeException('تعديل وثائق المشروع متاح لمشرف المشروع المكلّف فقط.');
$documentId = (int)($_POST['document_id'] ?? 0);
$doc = dbFetchOne('SELECT * FROM project_documents WHERE id = ? AND project_id = ? AND document_type <> \'receipt\'', [$documentId, $id]);
if (!$doc || $doc['verification_status'] !== 'unverified') throw new RuntimeException('لا يمكن تعديل الوثيقة بعد التحقق منها.');
$documentType = akp_post_value('document_type', 'other');
$allowedTypes = ['invoice','certificate','government_fee','permit','contract','quotation','progress_report','closure_report','other'];
if (!in_array($documentType, $allowedTypes, true)) throw new RuntimeException('نوع الوثيقة غير صالح.');
$title = akp_post_value('document_title');
if ($title === '') throw new RuntimeException('عنوان الوثيقة مطلوب.');
$newStoredAbsolutePath = null;
$oldStoredPath = $doc['file_path'] ?? null;
if (!empty($_FILES['document']['name'])) {
if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('فشل في رفع الملف الجديد.');
$file = $_FILES['document'];
if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('حجم وثيقة المشروع يجب ألا يتجاوز 10 ميجابايت.');
$mime = mime_content_type($file['tmp_name']);
$allowed = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx'];
if (!isset($allowed[$mime])) throw new RuntimeException('نوع الملف غير مسموح. استخدم PDF أو JPG أو PNG أو DOCX أو XLSX.');
$relativeDir = 'storage/documents/projects/' . $id;
$absoluteDir = dirname(__DIR__, 2) . '/' . $relativeDir;
if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true)) throw new RuntimeException('تعذر إنشاء مجلد الوثائق.');
$stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
$newStoredAbsolutePath = $absoluteDir . '/' . $stored;
if (!move_uploaded_file($file['tmp_name'], $newStoredAbsolutePath)) throw new RuntimeException('تعذر حفظ الملف الجديد.');
$relativePath = $relativeDir . '/' . $stored;
dbExecute('UPDATE project_documents SET document_type = ?, title = ?, file_path = ?, original_name = ?, mime_type = ?, file_size = ?, document_date = ?, issuer = ?, reference_number = ?, notes = ? WHERE id = ? AND project_id = ?', [$documentType, $title, $relativePath, $file['name'], $mime, $file['size'], akp_post_value('document_date') ?: null, akp_post_value('document_issuer') ?: null, akp_post_value('document_reference') ?: null, akp_post_value('document_notes') ?: null, $documentId, $id]);
} else {
dbExecute('UPDATE project_documents SET document_type = ?, title = ?, document_date = ?, issuer = ?, reference_number = ?, notes = ? WHERE id = ? AND project_id = ?', [$documentType, $title, akp_post_value('document_date') ?: null, akp_post_value('document_issuer') ?: null, akp_post_value('document_reference') ?: null, akp_post_value('document_notes') ?: null, $documentId, $id]);
}
if ($newStoredAbsolutePath && $oldStoredPath) {
$oldAbsolutePath = dirname(__DIR__, 2) . '/' . $oldStoredPath;
if ($oldAbsolutePath !== $newStoredAbsolutePath && is_file($oldAbsolutePath)) @unlink($oldAbsolutePath);
}
akp_audit('UPDATE', 'project_document', $documentId, ['project_id'=>$id,'title'=>$doc['title'],'document_type'=>$doc['document_type']], ['project_id'=>$id,'title'=>$title,'document_type'=>$documentType]);
$_SESSION['project_document_success'] = 'تم تعديل الوثيقة بنجاح.';
} elseif ($action === 'delete_project_document') {
if ($role !== 'project_supervisor' || !akp_can_edit_section('documents', $id) || $closed) throw new RuntimeException('حذف وثائق المشروع متاح لمشرف المشروع المكلّف فقط.');
$documentId = (int)($_POST['document_id'] ?? 0);
$doc = dbFetchOne('SELECT * FROM project_documents WHERE id = ? AND project_id = ? AND document_type <> \'receipt\'', [$documentId, $id]);
if (!$doc || $doc['verification_status'] !== 'unverified') throw new RuntimeException('لا يمكن حذف الوثيقة بعد التحقق منها.');
$linked = dbFetchOne('SELECT id FROM project_expenses WHERE project_id = ? AND primary_document_id = ? LIMIT 1', [$id, $documentId]);
if ($linked) throw new RuntimeException('لا يمكن حذف وثيقة مرتبطة بمصروف.');
dbExecute('DELETE FROM project_documents WHERE id = ? AND project_id = ? AND document_type <> \'receipt\' AND verification_status = \'unverified\'', [$documentId, $id]);
$storedPath = $doc['file_path'] ?? null;
if ($storedPath) {
$absolutePath = dirname(__DIR__, 2) . '/' . $storedPath;
if (is_file($absolutePath)) @unlink($absolutePath);
}
akp_audit('DELETE', 'project_document', $documentId, ['project_id'=>$id,'title'=>$doc['title'],'document_type'=>$doc['document_type']], null);
$_SESSION['project_document_success'] = 'تم حذف الوثيقة بنجاح.';
} elseif ($action === 'verify_document') {
// ... (Original verify_document logic preserved exactly)
if (!akp_can_edit_section('documents', $id) || $closed) throw new RuntimeException('لا تملك صلاحية التحقق من الوثائق.');
$documentId = (int)($_POST['document_id'] ?? 0);
$verification = akp_post_value('verification_status');
if (!in_array($verification, ['verified','rejected'], true)) throw new RuntimeException('حالة التحقق غير صالحة.');
$rejectionReason = akp_post_value('rejection_reason');
if ($verification === 'rejected' && $rejectionReason === '') throw new RuntimeException('سبب رفض الوثيقة مطلوب.');
$doc = dbFetchOne('SELECT * FROM project_documents WHERE id = ? AND project_id = ?', [$documentId, $id]);
if (!$doc) throw new RuntimeException('الوثيقة غير موجودة.');
dbExecute('UPDATE project_documents SET verification_status = ?, verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE id = ? AND project_id = ?', [$verification, akp_user_id(), $verification === 'rejected' ? $rejectionReason : null, $documentId, $id]);
akp_audit('VERIFY', 'project_document', $documentId, ['verification_status' => $doc['verification_status']], ['verification_status' => $verification]);
$_SESSION['project_toast_success'] = 'تم تحديث حالة الوثيقة.';
} elseif ($action === 'add_labor') {
// ... (Original add_labor logic preserved exactly)
if ($role !== 'project_supervisor' || (!akp_is_primary_supervisor($id) && !akp_has_project_section($id, 'operations')) || $closed) throw new RuntimeException('إضافة بيانات العمالة الخارجية متاحة لمشرف المشروع المكلّف فقط.');
$providerType = akp_post_value('labor_provider_type', 'individual');
$providerName = akp_post_value('labor_provider_name');
$workDescription = akp_post_value('labor_work_description');
$workers = max(1, (int)($_POST['labor_number_of_workers'] ?? 1));
$amount = max(0, (float)($_POST['labor_payment_amount'] ?? 0));
$paymentTiming = akp_post_value('labor_payment_timing', 'upon_completion');
$laborStatus = akp_post_value('labor_status', 'planned');
if (!in_array($providerType, ['individual', 'company'], true) || $providerName === '' || $workDescription === '' || $amount <= 0) throw new RuntimeException('نوع مقدم الخدمة والاسم ووصف العمل والمبلغ مطلوبة.');
if (!in_array($paymentTiming, ['upfront', 'daily', 'weekly', 'monthly', 'upon_completion'], true)) throw new RuntimeException('توقيت الدفع غير صالح.');
if (!in_array($laborStatus, ['planned', 'in_progress', 'completed'], true)) throw new RuntimeException('حالة العمالة غير صالحة.');
if ($providerType === 'company' && akp_post_value('labor_contact_person_name') === '') throw new RuntimeException('اسم جهة الاتصال مطلوب للشركة.');
dbExecute('INSERT INTO project_labor_helpers (project_id, supervisor_user_id, provider_type, provider_name, phone, contact_person_name, contact_person_phone, number_of_workers, work_description, payment_amount, currency_code, payment_timing, status, notes, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$id, akp_user_id(), $providerType, $providerName, akp_post_value('labor_phone') ?: null, akp_post_value('labor_contact_person_name') ?: null, akp_post_value('labor_contact_person_phone') ?: null, $workers, $workDescription, $amount, akp_post_value('labor_currency', $project['currency_code'] ?: 'SDG'), $paymentTiming, $laborStatus, akp_post_value('labor_notes') ?: null, akp_user_id(), akp_user_id()]);
$laborId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
akp_audit('CREATE', 'project_labor_helper', $laborId, null, ['project_id' => $id, 'provider_name' => $providerName, 'amount' => $amount]);
$_SESSION['project_toast_success'] = 'تم حفظ بيانات العامل/الجهة الخارجية.';
} elseif ($action === 'record_labor_payment') {
if ($role !== 'project_supervisor' || !akp_is_primary_supervisor($id) || $closed) throw new RuntimeException('تسجيل دفعات العمالة الخارجية متاح لمشرف المشروع المكلّف فقط.');
$laborId=(int)($_POST['labor_id']??0);
$labor=dbFetchOne('SELECT * FROM project_labor_helpers WHERE id=? AND project_id=?',[$laborId,$id]);
if(!$labor) throw new RuntimeException('سجل العمالة غير موجود.');
if(dbFetchOne("SELECT id FROM project_expenses WHERE project_id=? AND transaction_reference=? AND status='posted' LIMIT 1",[$id,'LABOR:'.$laborId])) throw new RuntimeException('تم تسجيل دفعة هذه العمالة مسبقاً.');
$amount=(float)$labor['payment_amount'];
if($amount<=0) throw new RuntimeException('مبلغ دفعة العمالة غير صالح.');
$paymentDate=akp_post_value('labor_payment_date',date('Y-m-d'));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$paymentDate)||$paymentDate>date('Y-m-d')) throw new RuntimeException('تاريخ الدفع غير صالح.');
$approvedBudgetTotal=(float)(dbFetchOne("SELECT COALESCE(SUM(COALESCE(bl.approved_amount,bl.estimated_amount)),0) total FROM project_budgets b JOIN project_budget_lines bl ON bl.budget_id=b.id WHERE b.id=? AND b.status='approved'",[$approvedBudgetId])['total']??0);
$existingExpenseTotal=(float)(dbFetchOne("SELECT COALESCE(SUM(amount),0) total FROM project_expenses WHERE project_id=? AND status='posted'",[$id])['total']??0);
if($approvedBudgetTotal<=0) throw new RuntimeException('لا توجد ميزانية معتمدة للمشروع.');
$remainingBudget=$approvedBudgetTotal-$existingExpenseTotal;
if($amount>($remainingBudget+0.01)) throw new RuntimeException('المبلغ يتجاوز الرصيد الحالي المتاح من ميزانية المشروع. المتبقي: '.number_format(max(0,$remainingBudget),2).' '.($project['currency_code']?:'SDG').'.');
$primaryDocumentId=null; $storedAbsolutePath=null; $voucherNo='PRJ-LPV-'.(string)($project['project_code']??$id).'-'.$laborId;
dbExecute('START TRANSACTION');
try {
if(!empty($_FILES['labor_payment_receipt']['name'])) {
if($_FILES['labor_payment_receipt']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('فشل في رفع إيصال الدفع.');
$file=$_FILES['labor_payment_receipt'];
if((int)($file['size']??0)>10*1024*1024) throw new RuntimeException('حجم إيصال الدفع يجب ألا يتجاوز 10 ميجابايت.');
$mime=mime_content_type($file['tmp_name']); $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
if(!isset($allowed[$mime])) throw new RuntimeException('نوع إيصال الدفع غير مسموح. استخدم PDF أو JPG أو PNG.');
$relativeDir='storage/documents/projects/'.$id; $absoluteDir=dirname(__DIR__,2).'/'.$relativeDir;
if(!is_dir($absoluteDir)&&!mkdir($absoluteDir,0750,true)) throw new RuntimeException('تعذر إنشاء مجلد وثائق المشروع.');
$stored=bin2hex(random_bytes(16)).'.'.$allowed[$mime]; $storedAbsolutePath=$absoluteDir.'/'.$stored;
if(!move_uploaded_file($file['tmp_name'],$storedAbsolutePath)) throw new RuntimeException('تعذر حفظ إيصال الدفع.');
$relativePath=$relativeDir.'/'.$stored;
dbExecute('INSERT INTO project_documents (project_id,document_type,title,file_path,original_name,mime_type,file_size,document_date,issuer,reference_number,amount,currency_code,notes,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,'receipt','إيصال دفعة عمالة: '.$labor['provider_name'],$relativePath,$file['name'],$mime,$file['size'],$paymentDate,$labor['provider_name'],$voucherNo,$amount,$labor['currency_code']?:($project['currency_code']?:'SDG'),'مرفق مباشرة بدفعة من ميزانية المشروع.',akp_user_id()]);
$primaryDocumentId=(int)(dbFetchOne('SELECT LAST_INSERT_ID() id')['id']??0);
}
dbExecute('INSERT INTO project_expenses (project_id,budget_id,budget_line_id,expense_date,category,description,vendor_name,vendor_contact,invoice_number,government_fee_type,amount,currency_code,transaction_reference,expense_account_id,payment_account_id,primary_document_id,status,submitted_by,posted_by,journal_entry_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$approvedBudgetId>0?$approvedBudgetId:null,null,$paymentDate,'عمالة خارجية','دفعة عمالة من ميزانية المشروع: '.$labor['provider_name'].' — '.$labor['work_description'],$labor['provider_name'],$labor['phone']?:null,$voucherNo,null,$amount,$labor['currency_code']?:($project['currency_code']?:'SDG'),'LABOR:'.$laborId,null,null,$primaryDocumentId,'posted',akp_user_id(),akp_user_id(),null]);
$expenseId=(int)(dbFetchOne('SELECT LAST_INSERT_ID() id')['id']??0);
if($expenseId<=0) throw new RuntimeException('تعذر تسجيل دفعة العمالة.');
dbExecute('COMMIT');
} catch(Throwable $e) {
dbExecute('ROLLBACK');
if($storedAbsolutePath&&is_file($storedAbsolutePath)) @unlink($storedAbsolutePath);
throw $e;
}
akp_audit('POST','project_labor_helper',$laborId,['payment_status'=>'unpaid'],['payment_status'=>'paid','amount'=>$amount,'expense_id'=>$expenseId,'voucher_no'=>$voucherNo]);
$_SESSION['project_toast_success']='تم تسجيل الدفع وخصم '.$amount.' من ميزانية المشروع وإصدار سند الصرف '.$voucherNo.'.';
 } elseif ($action === 'edit_labor') {
if ($role !== 'project_supervisor' || !akp_is_primary_supervisor($id) || $closed) throw new RuntimeException('تعديل بيانات العمالة الخارجية متاح لمشرف المشروع المكلّف فقط.');
$laborId=(int)($_POST['labor_id']??0); $labor=dbFetchOne('SELECT * FROM project_labor_helpers WHERE id=? AND project_id=?',[$laborId,$id]); if(!$labor) throw new RuntimeException('سجل العمالة غير موجود.');
$providerType=akp_post_value('labor_provider_type','individual'); $providerName=akp_post_value('labor_provider_name'); $workDescription=akp_post_value('labor_work_description'); $workers=max(1,(int)($_POST['labor_number_of_workers']??1)); $amount=max(0,(float)($_POST['labor_payment_amount']??0)); $paymentTiming=akp_post_value('labor_payment_timing','upon_completion'); $laborStatus=akp_post_value('labor_status','planned');
if(!in_array($providerType,['individual','company'],true)||$providerName===''||$workDescription===''||$amount<=0) throw new RuntimeException('نوع مقدم الخدمة والاسم ووصف العمل والمبلغ مطلوبة.');
if(!in_array($paymentTiming,['upfront','daily','weekly','monthly','upon_completion'],true)||!in_array($laborStatus,['planned','in_progress','completed'],true)) throw new RuntimeException('بيانات العمالة غير صالحة.');
if($providerType==='company'&&akp_post_value('labor_contact_person_name')==='') throw new RuntimeException('اسم جهة الاتصال مطلوب للشركة.');
$paymentExpense=dbFetchOne("SELECT * FROM project_expenses WHERE project_id=? AND transaction_reference=? AND status='posted' LIMIT 1",[$id,'LABOR:'.$laborId]);
if($paymentExpense){$budgetTotal=(float)(dbFetchOne("SELECT COALESCE(SUM(COALESCE(bl.approved_amount,bl.estimated_amount)),0) total FROM project_budgets b JOIN project_budget_lines bl ON bl.budget_id=b.id WHERE b.id=? AND b.status='approved'",[(int)$paymentExpense['budget_id']])['total']??0);$otherPosted=(float)(dbFetchOne("SELECT COALESCE(SUM(amount),0) total FROM project_expenses WHERE project_id=? AND status='posted' AND id<>?",[$id,(int)$paymentExpense['id']])['total']??0);if($budgetTotal<=0||$otherPosted+$amount>$budgetTotal+0.01)throw new RuntimeException('المبلغ الجديد يتجاوز الرصيد المتاح من ميزانية المشروع.');}
dbExecute('START TRANSACTION'); try {$currency=akp_post_value('labor_currency',$project['currency_code']?:'SDG'); dbExecute('UPDATE project_labor_helpers SET provider_type=?,provider_name=?,phone=?,contact_person_name=?,contact_person_phone=?,number_of_workers=?,work_description=?,payment_amount=?,currency_code=?,payment_timing=?,status=?,notes=?,updated_by=? WHERE id=? AND project_id=?',[$providerType,$providerName,akp_post_value('labor_phone')?:null,akp_post_value('labor_contact_person_name')?:null,akp_post_value('labor_contact_person_phone')?:null,$workers,$workDescription,$amount,$currency,$paymentTiming,$laborStatus,akp_post_value('labor_notes')?:null,akp_user_id(),$laborId,$id]); if($paymentExpense){dbExecute('UPDATE project_expenses SET description=?,vendor_name=?,vendor_contact=?,amount=?,currency_code=? WHERE id=? AND project_id=? AND transaction_reference=? AND status=\'posted\'',['دفعة عمالة من ميزانية المشروع: '.$providerName.' — '.$workDescription,$providerName,akp_post_value('labor_phone')?:null,$amount,$currency,(int)$paymentExpense['id'],$id,'LABOR:'.$laborId]);if(!empty($paymentExpense['primary_document_id']))dbExecute("UPDATE project_documents SET title=?,issuer=?,amount=?,currency_code=? WHERE id=? AND project_id=? AND document_type='receipt'",['إيصال دفعة عمالة: '.$providerName,$providerName,$amount,$currency,(int)$paymentExpense['primary_document_id'],$id]);} dbExecute('COMMIT');}catch(Throwable $e){dbExecute('ROLLBACK');throw $e;}
akp_audit('UPDATE','project_labor_helper',$laborId,['project_id'=>$id,'amount'=>(float)$labor['payment_amount']],['project_id'=>$id,'amount'=>$amount,'paid'=>!empty($paymentExpense)]); $_SESSION['project_toast_success']='تم تعديل بيانات العمالة وتحديث المصروف المرتبط بها.';
} elseif ($action === 'delete_labor') {
if($role!=='project_supervisor'||!akp_is_primary_supervisor($id)||$closed)throw new RuntimeException('حذف بيانات العمالة الخارجية متاح لمشرف المشروع المكلّف فقط.'); $laborId=(int)($_POST['labor_id']??0); $labor=dbFetchOne('SELECT * FROM project_labor_helpers WHERE id=? AND project_id=?',[$laborId,$id]); if(!$labor)throw new RuntimeException('سجل العمالة غير موجود.'); $paymentExpense=dbFetchOne("SELECT * FROM project_expenses WHERE project_id=? AND transaction_reference=? AND status='posted' LIMIT 1",[$id,'LABOR:'.$laborId]); $receiptPath=null; if($paymentExpense&&!empty($paymentExpense['primary_document_id'])){$receipt=dbFetchOne("SELECT file_path FROM project_documents WHERE id=? AND project_id=? AND document_type='receipt'",[(int)$paymentExpense['primary_document_id'],$id]);$receiptPath=$receipt['file_path']??null;}
dbExecute('START TRANSACTION');try{dbExecute('DELETE FROM project_labor_comments WHERE labor_id=?',[$laborId]);if($paymentExpense&&!empty($paymentExpense['primary_document_id']))dbExecute("DELETE FROM project_documents WHERE id=? AND project_id=? AND document_type='receipt'",[(int)$paymentExpense['primary_document_id'],$id]);if($paymentExpense)dbExecute("DELETE FROM project_expenses WHERE id=? AND project_id=? AND transaction_reference=? AND status='posted'",[(int)$paymentExpense['id'],$id,'LABOR:'.$laborId]);dbExecute('DELETE FROM project_labor_helpers WHERE id=? AND project_id=?',[$laborId,$id]);dbExecute('COMMIT');}catch(Throwable $e){dbExecute('ROLLBACK');throw $e;} if($receiptPath){$path=dirname(__DIR__,2).'/'.$receiptPath;if(is_file($path))@unlink($path);} akp_audit('DELETE','project_labor_helper',$laborId,['project_id'=>$id,'amount'=>(float)$labor['payment_amount'],'paid'=>!empty($paymentExpense)],null);$_SESSION['project_toast_success']='تم حذف سجل العمالة والمصروف المرتبط به وإعادة المبلغ إلى الرصيد المتاح للمشروع.';
} elseif ($action === 'comment_labor') {
// ... (Original comment_labor logic preserved exactly)
if (!akp_is_executive()) throw new RuntimeException('التعليق الإداري على العمالة الخارجية متاح للإدارة التنفيذية فقط.');
$laborId = (int)($_POST['labor_id'] ?? 0);
$comment = akp_post_value('labor_manager_comment');
if (!$laborId || $comment === '' || !dbFetchOne('SELECT id FROM project_labor_helpers WHERE id = ? AND project_id = ?', [$laborId, $id])) throw new RuntimeException('سجل العمالة أو التعليق غير صالح.');
dbExecute('INSERT INTO project_labor_comments (labor_id, manager_user_id, comment) VALUES (?,?,?)', [$laborId, akp_user_id(), $comment]);
dbExecute('UPDATE project_labor_helpers SET manager_comment = ?, manager_comment_by = ?, manager_comment_at = NOW() WHERE id = ? AND project_id = ?', [$comment, akp_user_id(), $laborId, $id]);
akp_audit('COMMENT', 'project_labor_helper', $laborId, null, ['project_id' => $id]);
$_SESSION['project_toast_success'] = 'تم حفظ تعليق مدير المشاريع.';
} elseif ($action === 'add_milestone') {
if (!akp_can_edit_section('operations', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إضافة مراحل.');
$title = akp_post_value('milestone_title');
if ($title === '') throw new RuntimeException('عنوان المرحلة مطلوب.');
$completionPercent = max(0, min(100, (float)($_POST['completion_percent'] ?? 0)));
$status = $completionPercent >= 100 ? 'completed' : 'in_progress';
dbExecute('INSERT INTO project_milestones (project_id, title, description, planned_date, status, completion_percent, notes, created_by) VALUES (?,?,?,?,?,?,?,?)', [$id, $title, akp_post_value('milestone_description') ?: null, akp_post_value('planned_date') ?: null, $status, $completionPercent, akp_post_value('milestone_notes') ?: null, akp_user_id()]);
$milestoneId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
$plannedDate = akp_post_value('planned_date') ?: '—';
$milestoneDescription = akp_post_value('milestone_description') ?: '—';
$historyReason = 'التاريخ المخطط: ' . $plannedDate . ' | نسبة الإنجاز: ' . number_format($completionPercent, 2) . '% | وصف المرحلة: ' . $milestoneDescription;
dbExecute('INSERT INTO project_status_history (project_id, old_status, new_status, reason, changed_by) VALUES (?, ?, ?, ?, ?)', [$id, 'إضافة مرحلة', $title, $historyReason, akp_user_id()]);
akp_audit('CREATE', 'project_milestone', $milestoneId, null, ['project_id' => $id, 'title' => $title, 'planned_date' => $plannedDate, 'completion_percent' => $completionPercent, 'description' => $milestoneDescription]);
$_SESSION['project_toast_success'] = 'تمت إضافة المرحلة.';
} elseif ($action === 'edit_milestone') {
if (!akp_can_edit_section('operations', $id) || $closed) throw new RuntimeException('لا تملك صلاحية تعديل المراحل.');
$milestoneId = (int)($_POST['milestone_id'] ?? 0);
$title = akp_post_value('milestone_title');
if (!$milestoneId || $title === '' || !dbFetchOne('SELECT id FROM project_milestones WHERE id = ? AND project_id = ?', [$milestoneId, $id])) throw new RuntimeException('بيانات المرحلة غير صالحة.');
$completionPercent = max(0, min(100, (float)($_POST['completion_percent'] ?? 0)));
$status = $completionPercent >= 100 ? 'completed' : 'in_progress';
dbExecute('UPDATE project_milestones SET title = ?, description = ?, planned_date = ?, status = ?, completion_percent = ? WHERE id = ? AND project_id = ?', [$title, akp_post_value('milestone_description') ?: null, akp_post_value('planned_date') ?: null, $status, $completionPercent, $milestoneId, $id]);
$_SESSION['project_toast_success'] = 'تم تعديل المرحلة.';
} elseif ($action === 'delete_milestone') {
if (!akp_can_edit_section('operations', $id) || $closed) throw new RuntimeException('لا تملك صلاحية حذف المراحل.');
$milestoneId = (int)($_POST['milestone_id'] ?? 0);
if (!$milestoneId || !dbFetchOne('SELECT id FROM project_milestones WHERE id = ? AND project_id = ?', [$milestoneId, $id])) throw new RuntimeException('بيانات المرحلة غير صالحة.');
dbExecute('DELETE FROM project_milestones WHERE id = ? AND project_id = ?', [$milestoneId, $id]);
$_SESSION['project_toast_success'] = 'تم حذف المرحلة.';
} elseif ($action === 'add_progress') {
// ... (Original add_progress logic preserved exactly)
if (!akp_can_edit_section('operations', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إضافة تحديث تشغيلي.');
$summary = akp_post_value('progress_summary');
$progressPercentRaw = akp_post_value('progress_percent');
$progressPercent = $progressPercentRaw === '' ? 0.0 : (float)$progressPercentRaw;
if ($progressPercent < 0 || $progressPercent > 100) throw new RuntimeException('نسبة الإنجاز يجب أن تكون بين 0 و100.');
if ($summary === '') throw new RuntimeException('ملخص التقدم مطلوب.');
dbExecute('INSERT INTO project_progress_updates (project_id, update_date, completion_percent, summary, achievements, issues, next_steps, submitted_by) VALUES (?,?,?,?,?,?,?,?)', [$id, akp_post_value('update_date') ?: date('Y-m-d'), $progressPercent, $summary, akp_post_value('achievements') ?: null, akp_post_value('issues') ?: null, akp_post_value('next_steps') ?: null, akp_user_id()]);
akp_audit('CREATE', 'project_progress_update', (int)dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'], null, ['project_id' => $id]);
$_SESSION['project_toast_success'] = 'تم حفظ تحديث التقدم.';
} elseif ($action === 'request_project_closure') {
if ($role !== 'project_supervisor' || !akp_is_primary_supervisor($id)) throw new RuntimeException('طلب إغلاق المشروع متاح لمشرف المشروع الأساسي فقط.');
if ($closed) throw new RuntimeException('المشروع مغلق بالفعل.');
if ($closureRequest && (string)$closureRequest['new_status'] === 'closure_requested') throw new RuntimeException('يوجد بالفعل طلب إغلاق بانتظار مدير المشاريع.');
$reason = akp_post_value('closure_request_reason');
if ($reason === '') throw new RuntimeException('ملاحظة طلب الإغلاق مطلوبة.');
dbExecute("INSERT INTO project_status_history (project_id, old_status, new_status, reason, changed_by) VALUES (?, ?, 'closure_requested', ?, ?)", [$id, $project['lifecycle_status'] ?: $project['status'], 'طلب إغلاق من مشرف المشروع: ' . $reason, akp_user_id()]);
akp_audit('REQUEST_CLOSE', 'project_lifecycle', $id, ['status' => $project['lifecycle_status'] ?: $project['status']], ['status' => 'closure_requested', 'reason' => $reason]);
try {
$projectManagers = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code = 'projects_manager' AND u.is_active = 1");
foreach ($projectManagers as $projectManager) {
ak_transaction_review_notify_event((int)$projectManager['id'], 'طلب إغلاق مشروع', 'المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') لديه طلب إغلاق من مشرف المشروع ويحتاج إجراء مدير المشاريع.', APP_URL . 'modules/projects/view.php?id=' . $id, $id, 'project_closure_request');
}
} catch (Throwable $notificationError) {}
$_SESSION['project_toast_success'] = 'تم إرسال طلب إغلاق المشروع إلى مدير المشاريع.';
} elseif ($action === 'request_project_reopen') {
if ($role !== 'project_supervisor' || !akp_is_primary_supervisor($id)) throw new RuntimeException('طلب إعادة فتح المشروع متاح لمشرف المشروع الأساسي فقط.');
if (!$closed) throw new RuntimeException('المشروع ليس مغلقاً.');
if ($closureRequest && (string)$closureRequest['new_status'] === 'reopen_requested') throw new RuntimeException('يوجد بالفعل طلب إعادة فتح بانتظار مدير المشاريع.');
$reason = akp_post_value('reopen_request_reason');
if ($reason === '') throw new RuntimeException('ملاحظة طلب إعادة الفتح مطلوبة.');
dbExecute("INSERT INTO project_status_history (project_id, old_status, new_status, reason, changed_by) VALUES (?, 'closed', 'reopen_requested', ?, ?)", [$id, 'طلب إعادة فتح من مشرف المشروع: ' . $reason, akp_user_id()]);
akp_audit('REQUEST_REOPEN', 'project_lifecycle', $id, ['status' => 'closed'], ['status' => 'reopen_requested', 'reason' => $reason]);
try {
$projectManagers = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code = 'projects_manager' AND u.is_active = 1");
foreach ($projectManagers as $projectManager) {
ak_transaction_review_notify_event((int)$projectManager['id'], 'طلب إعادة فتح مشروع', 'المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') لديه طلب إعادة فتح من مشرف المشروع ويحتاج إجراء مدير المشاريع.', APP_URL . 'modules/projects/view.php?id=' . $id, $id, 'project_reopen_request');
}
} catch (Throwable $notificationError) {}
$_SESSION['project_toast_success'] = 'تم إرسال طلب إعادة فتح المشروع إلى مدير المشاريع.';
} elseif ($action === 'close_project') {
if ($role !== 'projects_manager') throw new RuntimeException('إغلاق المشروع محصور بمدير المشاريع بعد طلب مشرف المشروع.');
if (!akp_can_edit_section('closure', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إغلاق المشروع أو أنه مغلق مسبقاً.');
$pendingRequest = dbFetchOne("SELECT h.* FROM project_status_history h WHERE h.project_id = ? AND h.new_status = 'closure_requested' ORDER BY h.id DESC LIMIT 1", [$id]);
if (!$pendingRequest) throw new RuntimeException('لا يوجد طلب إغلاق معلق من مشرف المشروع.');
// ... (Original close_project logic preserved exactly)
$pending = dbFetchOne("SELECT COUNT(*) AS n FROM project_expenses WHERE project_id = ? AND status IN ('draft','submitted','approved')", [$id]);
if ((int)($pending['n'] ?? 0) > 0) throw new RuntimeException('لا يمكن الإغلاق مع وجود مصروفات غير مرحلة.');
$summary = akp_post_value('closure_summary');
$varianceExplanation = akp_post_value('variance_explanation');
if ($summary === '') throw new RuntimeException('ملخص الإغلاق مطلوب.');
$totals = akp_sync_closure_totals($id);
dbExecute("UPDATE project_lifecycle SET lifecycle_status = 'closed', closed_at = NOW(), closed_by = ?, close_reason = ?, closure_summary = ?, variance_explanation = ? WHERE project_id = ?", [akp_user_id(), akp_post_value('closure_reason', 'other'), $summary, $varianceExplanation, $id]);
dbExecute("UPDATE other_projects SET status = 'completed', updated_by = ? WHERE id = ?", [akp_user_id(), $id]);
dbExecute("INSERT INTO project_status_history (project_id, old_status, new_status, reason, changed_by) VALUES (?, ?, 'closed', ?, ?)", [$id, $project['lifecycle_status'] ?: $project['status'], $summary, akp_user_id()]);
akp_audit('CLOSE', 'project_lifecycle', $id, ['status' => $project['lifecycle_status'] ?: $project['status']], ['status' => 'closed', 'reason' => $summary]);
try {
$executiveUsers = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code IN ('general_manager', 'vice_general_manager') AND u.is_active = 1");
foreach ($executiveUsers as $executiveUser) {
ak_transaction_review_notify_event((int)$executiveUser['id'], 'تم إغلاق مشروع', 'تم إغلاق المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') بواسطة مدير المشاريع بعد طلب الإغلاق من مشرف المشروع.', APP_URL . 'modules/projects/view.php?id=' . $id, $id, 'project_closed');
}
} catch (Throwable $notificationError) {}
$_SESSION['project_toast_success'] = 'تم إغلاق المشروع وإبلاغ المدير العام ونائبه.';
} elseif ($action === 'reopen_project') {
// ... (Original reopen_project logic preserved exactly)
if ($role !== 'projects_manager') throw new RuntimeException('إعادة فتح المشروع محصورة بمدير المشاريع بعد طلب مشرف المشروع.');
if (!$closed) throw new RuntimeException('المشروع ليس مغلقاً.');
$pendingRequest = dbFetchOne("SELECT h.* FROM project_status_history h WHERE h.project_id = ? AND h.new_status = 'reopen_requested' ORDER BY h.id DESC LIMIT 1", [$id]);
if (!$pendingRequest) throw new RuntimeException('لا يوجد طلب إعادة فتح معلق من مشرف المشروع.');
$reason = akp_post_value('reopen_reason');
if ($reason === '') throw new RuntimeException('سبب إعادة الفتح مطلوب.');
dbExecute("UPDATE project_lifecycle SET lifecycle_status = 'reopened', reopened_at = NOW(), reopened_by = ?, reopen_reason = ? WHERE project_id = ?", [akp_user_id(), $reason, $id]);
dbExecute("UPDATE other_projects SET status = 'active', updated_by = ? WHERE id = ?", [akp_user_id(), $id]);
dbExecute("INSERT INTO project_status_history (project_id, old_status, new_status, reason, changed_by) VALUES (?, 'closed', 'reopened', ?, ?)", [$id, $reason, akp_user_id()]);
akp_audit('REOPEN', 'project_lifecycle', $id, ['status' => 'closed'], ['status' => 'reopened', 'reason' => $reason]);
try {
$executiveUsers = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code IN ('general_manager', 'vice_general_manager') AND u.is_active = 1");
foreach ($executiveUsers as $executiveUser) {
ak_transaction_review_notify_event((int)$executiveUser['id'], 'تمت إعادة فتح مشروع', 'تمت إعادة فتح المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') بواسطة مدير المشاريع بعد طلب إعادة الفتح من مشرف المشروع.', APP_URL . 'modules/projects/view.php?id=' . $id, $id, 'project_reopened');
}
} catch (Throwable $notificationError) {}
$_SESSION['project_toast_success'] = 'تمت إعادة فتح المشروع وإبلاغ المدير العام ونائبه.';
}
} catch (Throwable $e) {
flash('error', $e->getMessage());
}
akp_redirect_project($id);
}
$currency = $project['currency_code'] ?: 'SDG';
$status = $project['lifecycle_status'] ?: $project['status'];
$projectToastSuccess = $_SESSION['project_toast_success'] ?? null;
$projectExpenseSuccess = $_SESSION['project_expense_success'] ?? null;
$projectDocumentSuccess = $_SESSION['project_document_success'] ?? null;
unset($_SESSION['project_toast_success'], $_SESSION['project_expense_success'], $_SESSION['project_document_success']);
$badge = ['planned'=>'bg-secondary','active'=>'bg-success','completed'=>'bg-info','under_review'=>'bg-warning text-dark','closed'=>'bg-dark','reopened'=>'bg-primary','cancelled'=>'bg-danger'][$status] ?? 'bg-secondary';
$varianceClass = $totals['variance'] > 0 ? 'text-danger' : 'text-success';
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<link rel="stylesheet" href="<?php echo e(APP_URL . 'assets/css/projects-ui.css'); ?>">
<?php if ($projectToastSuccess): ?>
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080; direction:rtl;" aria-live="polite" aria-atomic="true">
<div id="projectSuccessToast" class="toast text-bg-success border-0" role="status" data-bs-delay="4500" data-bs-autohide="true">
<div class="d-flex align-items-center">
<div class="toast-body fw-semibold">
<i class="fas fa-check-circle me-2" aria-hidden="true"></i><?php echo e($projectToastSuccess); ?>
</div>
<button type="button" class="btn-close btn-close-white me-2" data-bs-dismiss="toast" aria-label="إغلاق"></button>
</div>
</div>
</div>
<?php endif; ?>
<style>
.project-module-page {
max-width: 1500px;
margin-left: auto;
margin-right: auto;
width: 100%;
}
@media (min-width: 992px) {
.project-view-main-column {
flex: 0 0 80%;
max-width: 80%;
}
.project-view-side-column {
flex: 0 0 20%;
max-width: 20%;
}
.project-finance-card {
width: 100%;
}
.project-finance-card .table {
width: 100%;
table-layout: auto;
}
.project-finance-card .budget-details-row table {
width: 100%;
table-layout: auto;
}
.project-finance-card th,
.project-finance-card td {
white-space: normal;
word-break: normal;
overflow-wrap: anywhere;
vertical-align: middle;
}
.project-finance-card .budget-details-row th:nth-child(1) {
width: 16%;
}
.project-finance-card .budget-details-row th:nth-child(2) {
width: 28%;
}
.project-finance-card .budget-details-row th:nth-child(3),
.project-finance-card .budget-details-row th:nth-child(4) {
width: 16%;
}
.project-finance-card .budget-details-row th:nth-child(5) {
width: 24%;
}
}
</style>
<div class="project-module-page">
<div class="project-page-banner fade-in">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
<div>
<h2><?php echo e($project['name']); ?></h2>
<p><code><?php echo e($project['project_code'] ?? ''); ?></code> · <?php echo e($project['project_type'] ?? ''); ?> · <span class="badge <?php echo $badge; ?>"><?php echo e(akp_status_label($status)); ?></span> · اعتماد: <span class="badge bg-light text-dark"><?php echo e(akp_approval_status_label((string)$approval['approval_status'])); ?></span></p>
<?php if ($primarySupervisor): ?>
<p class="small mb-0"><i class="fas fa-user-tie me-1"></i>مشرف المشروع: <strong><?php echo e($primarySupervisor['full_name']); ?></strong></p>
<?php endif; ?>
</div>
<div class="d-flex gap-2">
<?php if ($role === 'projects_manager' && $approval['approval_status'] === 'approved' && (string)($project['lifecycle_status'] ?? $project['status']) === 'planned'): ?>
<form method="post" class="project-action-form d-inline">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="launch_project">
<button class="btn btn-success" onclick="return confirm('هل أنت متأكد من إطلاق المشروع للمشرف المعيّن؟')">
<i class="fas fa-play me-1"></i> إطلاق المشروع
</button>
</form>
<?php endif; ?>
<?php if ($role === 'projects_manager' && in_array($approval['approval_status'], ['draft', 'rejected'], true)): ?>
<a href="<?php echo e(APP_URL . 'modules/projects/form.php?id=' . $id); ?>" class="btn btn-primary text-white">
<i class="fas fa-edit me-1"></i> تعديل المشروع
</a>
<form method="post" class="project-action-form d-inline">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="submit_project">
<button class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> إرسال للمدير المالي</button>
</form>
<?php endif; ?>
</div>
</div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($approval['approval_status'] === 'submitted'): ?>
<div class="alert alert-info fade-in"><i class="fas fa-info-circle me-2"></i>المشروع بانتظار المراجعة والاعتماد المالي من المدير المالي.</div>
<?php elseif ($approval['approval_status'] === 'fm_approved'): ?>
<div class="alert alert-warning fade-in"><i class="fas fa-clock me-2"></i>تم اعتماد المشروع مالياً. بانتظار الاعتماد النهائي من المدير العام.</div>
<?php elseif ($approval['approval_status'] === 'rejected'): ?>
<div class="alert alert-danger fade-in">
<i class="fas fa-exclamation-triangle me-2"></i>تم رفض المشروع.
<?php if (!empty($approval['fm_rejection_reason'])): ?>
<br><strong>سبب الرفض المالي:</strong> <?php echo e($approval['fm_rejection_reason']); ?>
<?php elseif (!empty($approval['rejection_reason'])): ?>
<br><strong>سبب الرفض النهائي:</strong> <?php echo e($approval['rejection_reason']); ?>
<?php endif; ?>
</div>
<?php endif; ?>
<?php if ($role === 'financial_manager' && $approval['approval_status'] === 'submitted'): ?>
<div class="card mb-4 fade-in border-primary">
<div class="card-header bg-primary text-white"><i class="fas fa-money-check-alt me-2"></i>مراجعة المدير المالي</div>
<div class="card-body">
<div class="project-module-note mb-3">راجع الميزانية المعتمدة والرسوم الحكومية وتخصيصات التمويل. يجب تحديد حسابات التمويل الفعلية (1100 النقدية، 1200 البنك، 1300 المحفظة الإلكترونية) وتخصيص كامل إجمالي المتطلبات المالية قبل الاعتماد.</div>
<form method="post" class="project-action-form d-inline">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="fm_approve_project">
<button class="btn btn-success" onclick="return confirm('هل أنت متأكد من اعتماد هذا المشروع مالياً؟')"><i class="fas fa-check me-1"></i> اعتماد مالي</button>
</form>
<form method="post" class="project-action-form d-inline ms-2">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="fm_reject_project">
<input type="text" name="rejection_reason" class="form-control d-inline-block" style="width: 300px;" placeholder="سبب الرفض المالي (مطلوب)" required>
<button class="btn btn-danger ms-2"><i class="fas fa-times me-1"></i> رفض</button>
</form>
</div>
</div>
<?php endif; ?>
<?php if ($role === 'financial_manager' && $approval['approval_status'] === 'fm_approved'): ?>
<div class="card mb-4 fade-in border-warning">
<div class="card-header bg-warning text-dark"><i class="fas fa-rotate-left me-2"></i>استكمال تخصيص التمويل</div>
<div class="card-body">
<p class="mb-3">هذا المشروع تم اعتماده مالياً قبل تطبيق شرط تخصيص التمويل الصريح. أعده للمراجعة المالية، ثم سجّل حسابات التمويل الفعلية قبل إعادة الاعتماد.</p>
<form method="post" class="project-action-form">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="fm_return_to_review">
<div class="input-group">
<input type="text" name="return_reason" class="form-control" placeholder="سبب إعادة المراجعة (مطلوب)" required value="استكمال تخصيص حسابات تمويل المشروع">
<button class="btn btn-warning" onclick="return confirm('سيُعاد المشروع إلى مرحلة المراجعة المالية. هل تريد المتابعة؟')"><i class="fas fa-rotate-left me-1"></i> إعادة للمراجعة المالية</button>
</div>
</form>
</div>
</div>
<?php endif; ?>
<?php if (in_array($role, ['admin', 'general_manager', 'vice_general_manager'], true) && $approval['approval_status'] === 'fm_approved'): ?>
<div class="card mb-4 fade-in border-success">
<div class="card-header bg-success text-white"><i class="fas fa-user-tie me-2"></i>اعتماد المدير العام</div>
<div class="card-body">
<p class="mb-3">المشروع معتمد مالياً. راجع مصادر التمويل والمبالغ المسجلة أدناه، ثم اعتمد نهائياً. سيتم تخصيص التمويل وحجزه للمشروع فوراً، دون أي أثر على السيولة الفعلية أو القيود المحاسبية — لا يُخصم أي مبلغ من الصندوق أو البنك أو المحفظة الإلكترونية إلا عند توثيق كل دفعة فعلية على حدة لاحقاً.</p>
<form method="post" class="project-action-form d-inline">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="approve_project">
<button class="btn btn-success" onclick="return confirm('هل أنت متأكد من الاعتماد النهائي لهذا المشروع؟ سيتم تخصيص التمويل وحجزه للمشروع.')"><i class="fas fa-check-double me-1"></i> اعتماد نهائي وإنشاء قيد</button>
</form>
<form method="post" class="project-action-form d-inline ms-2">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="reject_project">
<input type="text" name="rejection_reason" class="form-control d-inline-block" style="width: 300px;" placeholder="سبب الرفض النهائي (مطلوب)" required>
<button class="btn btn-danger ms-2"><i class="fas fa-times me-1"></i> رفض</button>
</form>
</div>
</div>
<?php endif; ?>
<!-- Rest of the original UI continues exactly as it was -->
<div class="project-summary-grid mb-4">
<div class="project-summary-card">
<div class="card text-center">
<div class="card-body py-2">
<div class="fs-5 fw-bold text-primary"><?php echo akp_money($totals['total_financial_requirement'] ?? ($totals['approved_budget'] ?? 0)); ?></div>
<div class="text-muted small">إجمالي المتطلبات المالية</div>
</div>
</div>
</div>
<div class="project-summary-card">
<div class="card text-center">
<div class="card-body py-2">
<div class="fs-5 fw-bold text-success"><?php echo akp_money($totals['total_funded'] ?? 0); ?></div>
<div class="text-muted small">التمويل المعتمد</div>
</div>
</div>
</div>
<div class="project-summary-card">
<div class="card text-center">
<div class="card-body py-2">
<div class="fs-5 fw-bold text-danger"><?php echo akp_money($totals['total_expensed'] ?? 0); ?></div>
<div class="text-muted small">المصروفات المرحلة</div>
</div>
</div>
</div>
<div class="project-summary-card">
<div class="card text-center">
<div class="card-body py-2">
<div class="fs-5 fw-bold <?php echo $varianceClass; ?>"><?php echo akp_money($totals['variance']); ?></div>
<div class="text-muted small">فرق الميزانية</div>
</div>
</div>
</div>
</div>
<?php if ($status === 'closed'): ?>
<div class="alert alert-dark"><strong>المشروع مغلق.</strong> لا يمكن تعديل أي قسم أو إضافة مستندات أو مصروفات. إعادة الفتح متاحة للمدير العام فقط.</div>
<?php endif; ?>
<div class="row g-4 project-view-sections-grid">
<div class="col-lg-5 project-view-main-column">
<div class="card mb-4 fade-in">
<div class="card-header"><i class="fas fa-circle-info me-2"></i>ملخص المشروع</div>
<div class="card-body">
<div class="row g-3 small">
<div class="col-md-6"><strong>الوصف</strong><div><?php echo nl2br(e($project['description'] ?? '—')); ?></div></div>
<div class="col-md-6"><strong>الأهداف</strong><div><?php echo nl2br(e($details['objectives'] ?? '—')); ?></div></div>
<div class="col-md-6"><strong>الموقع</strong><div><?php echo e(implode(' · ', array_filter([$project['location'], $project['city'], $project['district']])) ?: '—'); ?></div></div>
<div class="col-md-6"><strong>الفترة</strong><div><?php echo e(($project['start_date'] ?: '—') . ' → ' . ($project['end_date'] ?: '—')); ?></div></div>
<div class="col-md-6"><strong>النتائج المتوقعة</strong><div><?php echo nl2br(e($details['expected_outcomes'] ?? '—')); ?></div></div>
<div class="col-md-6"><strong>الشريك المنفذ</strong><div><?php echo e($details['implementing_partner'] ?? '—'); ?></div></div>
<div class="col-md-6"><strong>الاستدامة</strong><div><?php echo nl2br(e($details['sustainability_plan'] ?? '—')); ?></div></div>
<div class="col-md-6"><strong>المخاطر والحد منها</strong><div><?php echo nl2br(e($details['risk_mitigation'] ?? '—')); ?></div></div>
</div>
</div>
</div>
<div class="card mb-4 fade-in">
<div class="card-header"><i class="fas fa-people-group me-2"></i>التنفيذ والشركاء والتوريد</div>
<div class="card-body">
<div class="row g-4">
<div class="col-md-6">
<h6 class="fw-bold">الجهات المنفذة أو الشركاء</h6>
<?php if ($partnerRows): ?>
<?php foreach ($partnerRows as $partner): ?>
<div class="border-bottom py-2 small">
<strong><?php echo e($partner['partner_name']); ?></strong>
<?php if (!empty($partner['role_description'])): ?>
<div class="text-muted"><?php echo e($partner['role_description']); ?></div>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="text-muted small">لا توجد جهات منفذة أو شركاء مسجلون.</div>
<?php endif; ?>
</div>
<div class="col-md-6">
<h6 class="fw-bold">طرق الشراء أو التوريد</h6>
<?php if ($procurementRows): ?>
<?php foreach ($procurementRows as $procurement): ?>
<div class="border-bottom py-2 small">
<strong><?php echo e($procurement['method_name']); ?></strong>
<?php if (!empty($procurement['notes'])): ?>
<div class="text-muted"><?php echo e($procurement['notes']); ?></div>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="text-muted small">لا توجد طرق شراء أو توريد مسجلة.</div>
<?php endif; ?>
</div>
<div class="col-md-6">
<div class="d-flex justify-content-between align-items-center mb-2">
<h6 class="fw-bold mb-0">المتطلبات الحكومية الأولية</h6>
<?php
$governmentFeesTotal = 0.0;
foreach ($governmentRequirementRows as $requirement) {
$governmentFeesTotal += (float)($requirement['fee_amount'] ?? 0);
}
?>
<?php if ($governmentRequirementRows): ?>
<span class="small text-muted">إجمالي الرسوم: <strong><?php echo akp_money($governmentFeesTotal); ?> SDG</strong></span>
<?php endif; ?>
</div>
<?php if ($governmentRequirementRows): ?>
<div class="table-responsive">
<table class="table table-sm align-middle mb-0">
<thead>
<tr>
<th>المتطلب</th>
<th class="text-nowrap">الرسوم (SDG)</th>
</tr>
</thead>
<tbody>
<?php foreach ($governmentRequirementRows as $requirement): ?>
<tr>
<td><?php echo e($requirement['requirement_text']); ?></td>
<td class="text-nowrap"><?php echo akp_money($requirement['fee_amount']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="text-muted small">لا توجد متطلبات حكومية مسجلة.</div>
<?php endif; ?>
</div>
<div class="col-md-6">
<h6 class="fw-bold">بيانات الاتصال</h6>
<?php if ($contactRows): ?>
<?php foreach ($contactRows as $contact): ?>
<div class="border-bottom py-2 small">
<strong><?php echo e($contact['contact_name']); ?></strong>
<?php if (!empty($contact['role_description'])): ?>
<div class="text-muted"><?php echo e($contact['role_description']); ?></div>
<?php endif; ?>
<?php if (!empty($contact['phone'])): ?>
<div>الهاتف: <?php echo e($contact['phone']); ?></div>
<?php endif; ?>
<?php if (!empty($contact['email'])): ?>
<div>البريد: <?php echo e($contact['email']); ?></div>
<?php endif; ?>
<?php if (!empty($contact['notes'])): ?>
<div class="text-muted"><?php echo e($contact['notes']); ?></div>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="text-muted small">لا توجد جهات اتصال مسجلة.</div>
<?php endif; ?>
</div>
</div>
</div>
</div>
<div class="card mb-4 fade-in">
<div class="card-body">
<div class="card border-primary mb-4 project-finance-card">
<div class="card-header bg-primary text-white"><i class="fas fa-file-invoice-dollar me-2"></i>الميزانية</div>
<div class="card-body">
<?php if ($approvedBudgetId && in_array((string)$approval['approval_status'], ['submitted', 'rejected'], true) && akp_can_manage_funding($id) && !$closed): ?>
<form method="post" class="project-form-panel" id="projectFundingForm">
<input type="hidden" name="action" value="add_funding">
<?php echo csrf_field(); ?>
<div id="fundingRows">
<div class="funding-row border rounded p-2 mb-2 bg-white">
<div class="row g-2 align-items-end">
<div class="col-md-5">
<label class="form-label small">حساب التمويل</label>
<select name="funding_source_account_id[]" class="form-select form-select-sm" required>
<option value="">اختر الحساب الذي سيموّل المشروع</option>
<?php foreach (dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE is_active = 1 AND code IN ('1100','1200','1300') ORDER BY code") as $account): ?>
<option value="<?php echo (int)$account['id']; ?>"><?php echo e($account['code'] . ' · ' . $account['name_ar']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-2">
<label class="form-label small">المبلغ</label>
<input type="number" step="0.01" min="0.01" name="funding_amount[]" class="form-control form-control-sm" placeholder="المبلغ" required>
</div>
<div class="col-md-2">
<label class="form-label small">التاريخ</label>
<input type="date" name="funding_allocation_date[]" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
</div>
<div class="col-md-2">
<label class="form-label small">المرجع</label>
<input name="funding_reference[]" class="form-control form-control-sm" placeholder="المرجع">
</div>
<div class="col-md-1 d-flex justify-content-end">
<button type="button" class="btn btn-sm btn-outline-danger remove-funding-row d-none" title="حذف مصدر التمويل"><i class="fas fa-times"></i></button>
</div>
<div class="col-12">
<input name="funding_description[]" class="form-control form-control-sm" placeholder="الوصف">
</div>
</div>
</div>
</div>
<div class="d-flex flex-wrap gap-2 align-items-center">
<button type="button" class="btn btn-sm btn-outline-success" id="addFundingRow"><i class="fas fa-plus me-1"></i> إضافة مصدر تمويل آخر</button>
<span class="small text-muted">يمكن إضافة أكثر من مصدر في نفس العملية، من الصندوق أو البنك أو المحفظة الإلكترونية.</span>
</div>
<div class="mt-2 small">
إجمالي التخصيصات الحالية: <strong><?php echo akp_money(array_sum(array_map('floatval', array_column($fundings, 'amount')))); ?> <?php echo e($project['currency_code'] ?: 'SDG'); ?></strong>
</div>
<div class="col-12 mt-2 small text-muted">يجب أن يساوي مجموع التخصيصات إجمالي المتطلبات المالية (الميزانية المعتمدة + الرسوم الحكومية) قبل الاعتماد المالي.</div>
<div class="col-12 mt-2"><button class="btn btn-sm btn-primary">حفظ تخصيصات التمويل</button></div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
const rows = document.getElementById('fundingRows');
const addButton = document.getElementById('addFundingRow');
if (!rows || !addButton) return;
function refreshRemoveButtons() {
const items = rows.querySelectorAll('.funding-row');
items.forEach(function (item) {
const remove = item.querySelector('.remove-funding-row');
if (remove) remove.classList.toggle('d-none', items.length === 1);
});
}
addButton.addEventListener('click', function () {
const source = rows.querySelector('.funding-row');
const clone = source.cloneNode(true);
clone.querySelectorAll('input').forEach(function (input) {
if (input.name === 'funding_allocation_date[]') input.value = '<?php echo date('Y-m-d'); ?>';
else input.value = '';
});
clone.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; });
rows.appendChild(clone);
refreshRemoveButtons();
});
rows.addEventListener('click', function (event) {
const button = event.target.closest('.remove-funding-row');
if (!button) return;
const row = button.closest('.funding-row');
if (row) row.remove();
refreshRemoveButtons();
});
refreshRemoveButtons();
});
</script>
<?php endif; ?>
<div class="table-responsive mt-3">
<table class="table table-sm">
<thead><tr><th>النسخة</th><th>الاسم</th><th>عدد البنود</th><th>الإجمالي</th><th>الحالة</th><th>إجراء</th></tr></thead>
<tbody>
<?php foreach ($budgets as $budget): ?>
<tr>
<td><?php echo (int)$budget['version_no']; ?></td>
<td><?php echo e($budget['budget_name']); ?></td>
<td><?php echo (int)$budget['line_count']; ?></td>
<td><?php echo number_format((float)$budget['line_total'], 2) . ' ' . e($budget['currency_code']); ?></td>
<td><span class="badge <?php echo $budget['status'] === 'approved' ? 'bg-success' : ($budget['status'] === 'superseded' ? 'bg-secondary' : 'bg-warning text-dark'); ?>"><?php echo e(akp_budget_status_label((string)$budget['status'])); ?></span></td>
<td></td>
</tr>
<tr class="budget-details-row">
<td colspan="6" class="bg-light">
<?php $budgetLines = $budgetLinesByBudgetId[(int)$budget['id']] ?? []; ?>
<?php if ($budgetLines): ?>
<div class="small fw-bold mb-2">تفاصيل الميزانية</div>
<div class="table-responsive">
<table class="table table-sm table-bordered align-middle mb-0 bg-white">
<thead>
<tr>
<th>الفئة</th>
<th>الوصف</th>
<th>المبلغ التقديري</th>
<th>المبلغ المعتمد</th>
<th>ملاحظات</th>
</tr>
</thead>
<tbody>
<?php foreach ($budgetLines as $budgetLine): ?>
<tr>
<td><?php echo e($budgetLine['category']); ?></td>
<td><?php echo e($budgetLine['description']); ?></td>
<td><?php echo akp_money($budgetLine['estimated_amount']); ?></td>
<td><?php echo $budgetLine['approved_amount'] !== null ? akp_money($budgetLine['approved_amount']) : '<span class="text-muted">—</span>'; ?></td>
<td><?php echo !empty($budgetLine['notes']) ? nl2br(e($budgetLine['notes'])) : '<span class="text-muted">—</span>'; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="text-muted small">لا توجد بنود تفصيلية لهذه النسخة.</div>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php
$budgetSummaryAmount = (float)($financialSummary['approved_budget'] ?? 0);
$governmentFeesAmount = (float)($financialSummary['government_fees'] ?? 0);
$grandFinancialRequirement = (float)($financialSummary['total_financial_requirement'] ?? ($budgetSummaryAmount + $governmentFeesAmount));
?>
<?php if ($budgets): ?>
<tr class="table-light">
<td colspan="3" class="fw-semibold">الميزانية المعتمدة</td>
<td class="fw-semibold"><?php echo akp_money($budgetSummaryAmount) . ' ' . e($project['currency_code']); ?></td>
<td colspan="2"></td>
</tr>
<tr class="table-warning">
<td colspan="3" class="fw-semibold">إجمالي الرسوم الحكومية</td>
<td class="fw-semibold"><?php echo akp_money($governmentFeesAmount) . ' ' . e($project['currency_code']); ?></td>
<td colspan="2" class="small text-muted">التزام مالي منفصل عن بنود تنفيذ المشروع</td>
</tr>
<tr class="table-primary">
<td colspan="3" class="fw-bold">الإجمالي الكلي للمتطلبات المالية</td>
<td class="fw-bold"><?php echo akp_money($grandFinancialRequirement) . ' ' . e($project['currency_code']); ?></td>
<td colspan="2" class="small fw-semibold">الميزانية + الرسوم الحكومية</td>
</tr>
<?php endif; ?>
<?php if (!$budgets): ?><tr><td colspan="6" class="text-center text-muted">لا توجد نسخ ميزانية.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<?php if ($role !== 'project_supervisor'): ?>
<div class="card border-success mb-0 project-finance-card">
<div class="card-header bg-success text-white"><i class="fas fa-money-bill-transfer me-2"></i>تخصيص التمويل</div>
<div class="card-body">
<?php if ($role === 'financial_manager' && $approval['approval_status'] === 'submitted' && !$closed): ?>
<div class="alert alert-info small mb-3">
<i class="fas fa-eye me-1"></i>
تم إعداد تخصيصات التمويل قبل الإرسال. دور المدير المالي هنا هو المراجعة المالية والاعتماد أو الرفض، دون تعديل بيانات التمويل.
</div>
<?php endif; ?>
<div class="table-responsive">
<table class="table table-sm">
<thead><tr><th>التاريخ</th><th>حساب التمويل</th><th>المبلغ</th><th>المرجع/الوصف</th><th>الحالة</th><th></th></tr></thead>
<tbody>
<?php foreach ($fundings as $funding): ?>
<tr>
<td><?php echo e($funding['allocation_date']); ?></td>
<td><?php echo e((string)($funding['source_account_code'] ?? $funding['source_type'])); ?> · <?php echo e((string)($funding['source_account_name'] ?? '')); ?></td>
<td><?php echo akp_money($funding['amount']); ?></td>
<td><small><?php echo e((string)($funding['reference_number'] ?? '')); ?><?php if (!empty($funding['description'])): ?><br><?php echo e((string)$funding['description']); ?><?php endif; ?></small></td>
<td><?php echo e(akp_funding_status_label((string)$funding['status'])); ?></td>
<td>
<?php if (in_array((string)$approval['approval_status'], ['submitted', 'rejected'], true) && akp_can_manage_funding($id) && !$closed): ?>
<button type="button" class="btn btn-sm btn-outline-primary"
data-bs-toggle="modal" data-bs-target="#editFundingModal"
data-id="<?php echo (int)$funding['id']; ?>"
data-source-account="<?php echo (int)$funding['source_account_id']; ?>"
data-amount="<?php echo e((string)$funding['amount']); ?>"
data-date="<?php echo e((string)$funding['allocation_date']); ?>"
data-reference="<?php echo e((string)($funding['reference_number'] ?? '')); ?>"
data-description="<?php echo e((string)($funding['description'] ?? '')); ?>">
<i class="fas fa-edit"></i> تعديل
</button>
<form method="post" class="project-action-form d-inline" onsubmit="return confirm('هل أنت متأكد من حذف تخصيص التمويل هذا؟');">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="delete_funding">
<input type="hidden" name="allocation_id" value="<?php echo (int)$funding['id']; ?>">
<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> حذف</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; if (!$fundings): ?>
<tr><td colspan="6" class="text-center text-muted">لا توجد تخصيصات تمويل مسجلة بعد.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php if (in_array((string)$approval['approval_status'], ['submitted', 'rejected'], true) && akp_can_manage_funding($id) && !$closed): ?>
<div class="modal fade" id="editFundingModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<form method="post">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="edit_funding">
<input type="hidden" name="allocation_id" id="editFundingId">
<div class="modal-header">
<h5 class="modal-title"><i class="fas fa-edit me-2"></i>تعديل تخصيص التمويل</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
</div>
<div class="modal-body">
<?php if ($approval['approval_status'] === 'approved'): ?>
<div class="alert alert-warning small">
<i class="fas fa-calculator me-1"></i>
هذا المشروع معتمد من المدير العام. عند حفظ التعديل سيتم عكس قيد الاعتماد السابق وإنشاء قيد جديد بالقيمة الصحيحة تلقائياً.
</div>
<?php endif; ?>
<div class="row g-2">
<div class="col-md-6">
<label class="form-label small">حساب المصدر</label>
<select name="source_account_id" id="editFundingSourceAccount" class="form-select form-select-sm" required>
<option value="">اختر حساب التمويل</option>
<?php foreach (dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE is_active = 1 AND code IN ('1100','1200','1300') ORDER BY code") as $account): ?>
<option value="<?php echo (int)$account['id']; ?>"><?php echo e($account['code'] . ' · ' . $account['name_ar']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-6">
<label class="form-label small">المبلغ</label>
<input type="number" step="0.01" min="0.01" name="funding_amount" id="editFundingAmount" class="form-control form-control-sm" required>
</div>
<div class="col-md-4">
<label class="form-label small">تاريخ التخصيص</label>
<input type="date" name="allocation_date" id="editFundingDate" class="form-control form-control-sm" required>
</div>
<div class="col-md-4">
<label class="form-label small">المرجع</label>
<input name="funding_reference" id="editFundingReference" class="form-control form-control-sm">
</div>
<div class="col-md-4">
<label class="form-label small">الوصف</label>
<input name="funding_description" id="editFundingDescription" class="form-control form-control-sm">
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
<button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ التعديل</button>
</div>
</form>
</div>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
var modal = document.getElementById('editFundingModal');
if (!modal) return;
modal.addEventListener('show.bs.modal', function (event) {
var button = event.relatedTarget;
document.getElementById('editFundingId').value = button.getAttribute('data-id') || '';
document.getElementById('editFundingSourceAccount').value = button.getAttribute('data-source-account') || '';
document.getElementById('editFundingAmount').value = button.getAttribute('data-amount') || '';
document.getElementById('editFundingDate').value = button.getAttribute('data-date') || '';
document.getElementById('editFundingReference').value = button.getAttribute('data-reference') || '';
document.getElementById('editFundingDescription').value = button.getAttribute('data-description') || '';
});
});
</script>
<?php endif; ?>
</div>
</div>
</div>
</div>
<?php endif; ?>
<?php if ($approval['approval_status'] === 'approved'): ?>
<?php if ($role !== 'project_supervisor'): ?>
<div class="card mb-4 fade-in border-success">
<div class="card-header bg-success text-white"><i class="fas fa-money-check-dollar me-2"></i>إثبات صرف تمويل المشروع</div>
<div class="card-body">
<div class="alert alert-light border">بعد اعتماد المدير العام أصبح صرف التمويل موثقاً محاسبياً. المدير المالي يستكمل مستند الصرف: سند صرف مطبوع للنقد، أو إيصال التحويل/المحفظة الإلكترونية. مدير المشاريع يستطيع الاطلاع على المستندات قبل بدء التنفيذ الفعلي.</div>
<div class="table-responsive">
<table class="table table-sm align-middle">
<thead><tr><th>حساب التمويل</th><th>طريقة الدفع</th><th>المبلغ</th><th>المرجع</th><th>المستند</th></tr></thead>
<tbody>
<?php if ($paymentEvidence): ?>
<?php foreach ($paymentEvidence as $payment): ?>
<?php $paymentMethodLabels = ['cash' => 'نقدي', 'bank_transfer' => 'تحويل بنكي', 'e_wallet' => 'محفظة إلكترونية']; $methodLabel = $paymentMethodLabels[$payment['payment_method']] ?? $payment['payment_method']; ?>
<tr>
<td><?php echo e(($payment['source_account_code'] ?? '') . ' · ' . ($payment['source_account_name'] ?? '')); ?></td>
<td><?php echo e($methodLabel); ?></td>
<td><?php echo number_format((float)$payment['amount'], 2) . ' ' . e($payment['currency_code'] ?: ($project['currency_code'] ?: 'SDG')); ?></td>
<td><?php echo !empty($payment['reference_number']) ? e($payment['reference_number']) : '<span class="text-muted">—</span>'; ?></td>
<td class="text-nowrap">
<?php if ($payment['payment_method'] === 'cash' && $payment['status'] === 'documented'): ?>
<a class="btn btn-sm btn-outline-primary" target="_blank" href="<?php echo APP_URL; ?>modules/accounting/voucher_print.php?project_payment_id=<?php echo (int)$payment['id']; ?>"><i class="fas fa-print me-1"></i>طباعة سند الصرف</a>
<?php elseif ($payment['payment_method'] !== 'cash' && !empty($payment['receipt_file_path'])): ?>
<a class="btn btn-sm btn-outline-primary" target="_blank" href="<?php echo APP_URL; ?>modules/projects/project_payment_receipt.php?id=<?php echo (int)$payment['id']; ?>"><i class="fas fa-paperclip me-1"></i>عرض الإيصال</a>
<?php else: ?>
<span class="text-muted"><?php echo $payment['payment_method'] === 'cash' ? 'بانتظار سند الصرف' : 'بانتظار إيصال التحويل'; ?></span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="5" class="text-center text-muted">لا توجد سجلات صرف مرتبطة بتخصيصات التمويل بعد.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php if ($approval['approval_status'] === 'approved'): ?>
<div class="card mb-4 fade-in" id="project-expenses">
<div class="card-header"><i class="fas fa-receipt me-2"></i>المصروفات</div>
<div class="card-body">
<?php if ($role === 'project_supervisor' && akp_is_primary_supervisor($id) && !$closed): ?>
<form method="post" enctype="multipart/form-data" class="project-form-panel border rounded p-3 mb-3">
<input type="hidden" name="action" value="add_ps_expense">
<?php echo csrf_field(); ?>
<div class="row g-2">
<div class="col-md-2"><label class="form-label small">التاريخ</label><input type="date" name="expense_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required></div>
<div class="col-md-2"><label class="form-label small">الفئة</label><input name="expense_category" class="form-control form-control-sm" placeholder="مثال: مواد / نقل" required></div>
<div class="col-md-2"><label class="form-label small">المبلغ</label><input type="number" step="0.01" min="0.01" max="<?php echo e(number_format(max(0, $projectExpenseRemaining), 2, '.', '')); ?>" name="expense_amount" class="form-control form-control-sm" placeholder="المبلغ" required></div>
<div class="col-md-6"><label class="form-label small">وصف المصروف</label><input name="expense_description" class="form-control form-control-sm" placeholder="وصف المصروف *" required></div>
<div class="col-md-4"><label class="form-label small">المورد</label><input name="vendor_name" class="form-control form-control-sm" placeholder="اسم المورد"></div>
<div class="col-md-4"><label class="form-label small">رقم الفاتورة</label><input name="invoice_number" class="form-control form-control-sm" placeholder="رقم الفاتورة"></div>
<div class="col-md-4"><label class="form-label small">إيصال المصروف</label><input type="file" name="expense_receipt" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png"></div>
<div class="col-12 d-flex align-items-center justify-content-between gap-2 flex-wrap">
<button class="btn btn-primary"><i class="fas fa-money-bill-transfer me-1"></i>تسجيل الدفع وترحيل المصروف</button>
<span class="small text-muted">عند حفظ الدفع مع الإيصال يُعتبر المصروف مدفوعاً ومرحّلاً مباشرة من ميزانية المشروع، دون أي قيد على حسابات المنظمة.</span>
</div>
</div>
</form>
<?php endif; ?>
<div class="table-responsive">
<table class="table table-sm align-middle">
<thead class="table-primary"><tr><th>التاريخ</th><th>الوصف</th><th>المورد/الفاتورة</th><th>المبلغ</th><th>الحالة</th><th>القيد</th><th></th></tr></thead>
<tbody>
<?php foreach ($expenses as $expense): ?>
<tr>
<td><?php echo e($expense['expense_date']); ?></td>
<td>
<?php echo e($expense['description']); ?>
<?php if (!empty($expense['labor_id'])): ?>
<br><span class="badge bg-info-subtle text-dark mt-1"><i class="fas fa-user-hard-hat me-1"></i>دفعة عمالة خارجية</span>
<?php endif; ?>
<?php if ($expense['government_fee_type']): ?><br><small class="text-muted">رسم: <?php echo e($expense['government_fee_type']); ?></small><?php endif; ?>
</td>
<td><?php echo e($expense['vendor_name'] ?: '—'); ?><br><small><?php echo e($expense['invoice_number'] ?: ''); ?></small></td>
<td><?php echo akp_money($expense['amount']); ?></td>
<td><span class="badge bg-<?php echo $expense['status'] === 'posted' ? 'dark' : ($expense['status'] === 'approved' ? 'success' : 'warning'); ?>"><?php echo e(akp_expense_status_label((string)$expense['status'])); ?></span></td>
<td><small class="text-muted"><?php echo e($expense['entry_code'] ?: '—'); ?></small></td>
<td>
<?php if (!empty($expense['primary_document_id'])): ?>
<a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo APP_URL; ?>modules/projects/serve_project_document.php?id=<?php echo (int)$expense['primary_document_id']; ?>"><i class="fas fa-paperclip me-1"></i>الإيصال</a>
<?php endif; ?>
<?php if (!empty($expense['labor_id']) && $role === 'project_supervisor' && akp_is_primary_supervisor($id) && !$closed): ?><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editLaborModal" data-labor='<?php $linkedLabor=$labors[array_search((int)$expense['labor_id'], array_map('intval', array_column($labors,'id')))] ?? null; echo e(json_encode($linkedLabor ?: ['id'=>(int)$expense['labor_id'],'payment_amount'=>$expense['amount'],'provider_name'=>$expense['vendor_name'],'work_description'=>$expense['description']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?>'><i class="fas fa-pen me-1"></i>تعديل</button><form method="post" class="d-inline project-delete-form"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_labor"><input type="hidden" name="labor_id" value="<?php echo (int)$expense['labor_id']; ?>"><button type="button" class="btn btn-sm btn-outline-danger project-delete-btn" data-confirm-title="حذف دفعة العمالة" data-confirm-text="سيتم حذف سجل العمالة والمصروف وسند الدفع والإيصال المرتبط به إن وُجد.">حذف</button></form>
<?php elseif ($expense['status'] === 'draft' && $role === 'project_supervisor' && akp_is_primary_supervisor($id) && !$closed): ?>
<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProjectExpenseModal"
data-expense-id="<?php echo (int)$expense['id']; ?>"
data-expense-date="<?php echo e($expense['expense_date']); ?>"
data-expense-category="<?php echo e($expense['category']); ?>"
data-expense-amount="<?php echo e($expense['amount']); ?>"
data-expense-description="<?php echo e($expense['description']); ?>"
data-expense-vendor="<?php echo e($expense['vendor_name'] ?? ''); ?>"
data-expense-invoice="<?php echo e($expense['invoice_number'] ?? ''); ?>">
<i class="fas fa-pen me-1"></i>تعديل
</button>
<form method="post" class="d-inline project-delete-form">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="delete_ps_expense">
<input type="hidden" name="expense_id" value="<?php echo (int)$expense['id']; ?>">
<button type="button" class="btn btn-sm btn-outline-danger project-delete-btn" data-confirm-title="حذف المصروف" data-confirm-text="سيتم حذف سجل المصروف وإيصال المصروف المرتبط به إن وُجد.">حذف</button>
</form>
<?php elseif ($expense['status'] === 'draft' && akp_can_edit_section('finance', $id) && !$closed): ?>
<form method="post" class="project-action-form d-inline">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="submit_expense">
<input type="hidden" name="expense_id" value="<?php echo (int)$expense['id']; ?>">
<button class="btn btn-sm btn-outline-primary">إرسال</button>
</form>
<?php elseif ($expense['status'] === 'submitted' && akp_can_edit_section('finance', $id) && !$closed): ?>
<form method="post" class="project-action-form d-inline">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="approve_expense">
<input type="hidden" name="expense_id" value="<?php echo (int)$expense['id']; ?>">
<button class="btn btn-sm btn-outline-success">اعتماد</button>
</form>
<?php elseif ($expense['status'] === 'approved' && in_array($role, ['admin','accountant','general_manager'], true) && !$closed): ?>
<form method="post" class="project-action-form d-inline">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="post_expense">
<input type="hidden" name="expense_id" value="<?php echo (int)$expense['id']; ?>">
<button class="btn btn-sm btn-outline-dark">ترحيل</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; if (!$expenses): ?>
<tr><td colspan="7" class="text-center text-muted">لا توجد مصروفات.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="row g-3 mt-1">
<div class="col-md-4"><div class="border rounded p-3 h-100 bg-light"><div class="small text-muted">الميزانية المعتمدة من المدير المالي</div><div class="fs-5 fw-bold"><?php echo akp_money($approvedBudgetTotal); ?> <?php echo e($project['currency_code'] ?: 'SDG'); ?></div></div></div>
<div class="col-md-4"><div class="border rounded p-3 h-100 bg-light"><div class="small text-muted">إجمالي المصروفات المسجلة</div><div class="fs-5 fw-bold"><?php echo akp_money($projectExpenseTotal); ?> <?php echo e($project['currency_code'] ?: 'SDG'); ?></div></div></div>
<div class="col-md-4"><div class="border rounded p-3 h-100 <?php echo $projectExpenseRemaining < -0.01 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle'; ?>"><div class="small text-muted">الرصيد المتبقي من الميزانية</div><div class="fs-5 fw-bold"><?php echo akp_money($projectExpenseRemaining); ?> <?php echo e($project['currency_code'] ?: 'SDG'); ?></div></div></div>
</div>
</div>
</div>
<?php if ($projectExpenseSuccess): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
var expenseSection = document.getElementById('project-expenses');
if (expenseSection) expenseSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
</script>
<?php endif; ?>
<?php if ($projectDocumentSuccess): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
var documentSection = document.getElementById('project-documents');
if (documentSection) documentSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
</script>
<?php endif; ?>
<div class="card mb-4 fade-in border-0 shadow-sm project-documents-card" id="project-documents">
<div class="card-header bg-dark text-white py-3">
<div class="d-flex align-items-center justify-content-between gap-2">
<div class="d-flex align-items-center">
<span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-10 me-2" style="width:36px;height:36px;"><i class="fas fa-file-shield"></i></span>
<div>
<div class="fw-bold">الوثائق والتصاريح والشهادات</div>
<div class="small text-white-50">حفظ ومراجعة المستندات المرتبطة بالمشروع</div>
</div>
</div>
<span class="badge bg-light text-dark px-3 py-2"><i class="fas fa-folder-open me-1"></i><?php echo count($documents); ?> مستند</span>
</div>
</div>
<div class="card-body p-3 p-lg-4">
<?php if ($projectDocumentSuccess): ?>
<div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3" role="alert">
<i class="fas fa-check-circle"></i>
<span><?php echo e($projectDocumentSuccess); ?></span>
</div>
<?php endif; ?>
<?php if (akp_can_edit_section('documents', $id) && !$closed): ?>
<div class="border rounded-3 p-3 bg-light mb-4">
<div class="d-flex align-items-center gap-2 mb-3">
<span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary" style="width:34px;height:34px;"><i class="fas fa-upload"></i></span>
<div>
<div class="fw-semibold">إضافة وثيقة جديدة</div>
<div class="small text-muted">أدخل بيانات الوثيقة وارفع الملف المرتبط بها.</div>
</div>
</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="action" value="upload_document">
<?php echo csrf_field(); ?>
<div class="row g-3">
<div class="col-md-3">
<label class="form-label small fw-semibold">نوع الوثيقة</label>
<select name="document_type" class="form-select" required>
<option value="invoice">فاتورة</option>
<option value="certificate">شهادة</option>
<option value="government_fee">رسم حكومي</option>
<option value="permit">تصريح</option>
<option value="contract">عقد</option>
<option value="quotation">عرض سعر</option>
<option value="progress_report">تقرير تقدم</option>
<option value="closure_report">تقرير إغلاق</option>
<option value="other">أخرى</option>
</select>
</div>
<div class="col-md-5">
<label class="form-label small fw-semibold">عنوان الوثيقة <span class="text-danger">*</span></label>
<input name="document_title" class="form-control" placeholder="عنوان واضح للوثيقة" required>
</div>
<div class="col-md-4">
<label class="form-label small fw-semibold">الملف <span class="text-danger">*</span></label>
<input type="file" name="document" class="form-control" required accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx">
</div>
<div class="col-md-4">
<label class="form-label small fw-semibold">الجهة المصدرة</label>
<input name="document_issuer" class="form-control" placeholder="الجهة المصدرة">
</div>
<div class="col-md-4">
<label class="form-label small fw-semibold">رقم الوثيقة/المرجع</label>
<input name="document_reference" class="form-control" placeholder="الرقم أو المرجع">
</div>
<div class="col-md-4">
<label class="form-label small fw-semibold">تاريخ الوثيقة</label>
<input type="date" name="document_date" class="form-control">
</div>
<div class="col-12">
<label class="form-label small fw-semibold">ملاحظات</label>
<input name="document_notes" class="form-control" placeholder="ملاحظات إضافية (اختياري)">
</div>
<div class="col-12 d-flex justify-content-end">
<button class="btn btn-primary px-4"><i class="fas fa-upload me-1"></i>رفع الوثيقة</button>
</div>
</div>
</form>
</div>
<?php endif; ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
<div>
<div class="fw-semibold">المستندات المسجلة</div>
<div class="small text-muted">يمكن عرض المستند، وتعديل أو حذف المستند غير المتحقق منه حسب الصلاحية.</div>
</div>
</div>
<div class="table-responsive">
<table class="table table-sm align-middle mb-0">
<thead class="table-primary">
<tr>
<th>النوع</th>
<th>عنوان الوثيقة</th>
<th>الجهة المصدرة</th>
<th>التاريخ</th>
<th>المرجع</th>
<th>حالة التحقق</th>
<th>الملف والإجراءات</th>
</tr>
</thead>
<tbody>
<?php foreach ($documents as $doc): ?>
<tr>
<td><span class="badge bg-secondary"><?php echo e($doc['document_type']); ?></span></td>
<td>
<div class="fw-semibold"><?php echo e($doc['title']); ?></div>
<?php if (!empty($doc['uploader_name'])): ?><small class="text-muted">رفع بواسطة: <?php echo e($doc['uploader_name']); ?></small><?php endif; ?>
</td>
<td><?php echo e($doc['issuer'] ?: '—'); ?></td>
<td><?php echo e($doc['document_date'] ?: '—'); ?></td>
<td><?php echo e($doc['reference_number'] ?: '—'); ?></td>
<td>
<?php $documentStatusClass = $doc['verification_status'] === 'verified' ? 'success' : ($doc['verification_status'] === 'rejected' ? 'danger' : 'warning'); ?>
<span class="badge bg-<?php echo $documentStatusClass; ?>"><?php echo e(akp_document_verification_status_label((string)$doc['verification_status'])); ?></span>
</td>
<td>
<div class="d-flex flex-wrap gap-1">
<a href="<?php echo APP_URL; ?>modules/projects/serve_project_document.php?id=<?php echo (int)$doc['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-eye me-1"></i>عرض</a>
<?php if ($doc['verification_status'] === 'unverified' && akp_can_edit_section('documents', $id) && !$closed): ?>
<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProjectDocumentModal"
data-document-id="<?php echo (int)$doc['id']; ?>"
data-document-type="<?php echo e($doc['document_type']); ?>"
data-document-title="<?php echo e($doc['title']); ?>"
data-document-date="<?php echo e($doc['document_date'] ?? ''); ?>"
data-document-issuer="<?php echo e($doc['issuer'] ?? ''); ?>"
data-document-reference="<?php echo e($doc['reference_number'] ?? ''); ?>"
data-document-notes="<?php echo e($doc['notes'] ?? ''); ?>">
<i class="fas fa-pen me-1"></i>تعديل
</button>
<form method="post" class="d-inline project-delete-form">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="delete_project_document">
<input type="hidden" name="document_id" value="<?php echo (int)$doc['id']; ?>">
<button type="button" class="btn btn-sm btn-outline-danger project-delete-btn" data-confirm-title="حذف الوثيقة" data-confirm-text="سيتم حذف الوثيقة والملف المرفق نهائياً."><i class="fas fa-trash me-1"></i>حذف</button>
</form>
<form method="post" class="project-action-form d-inline">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="verify_document">
<input type="hidden" name="document_id" value="<?php echo (int)$doc['id']; ?>">
<input type="hidden" name="verification_status" value="verified">
<button class="btn btn-sm btn-outline-success"><i class="fas fa-check me-1"></i>تحقق</button>
</form>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$documents): ?>
<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-folder-open me-1"></i>لا توجد وثائق مرفقة.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<div class="card mb-4 fade-in" id="project-labor">
<div class="card-header d-flex justify-content-between align-items-center"><span><i class="fas fa-hard-hat me-2"></i>العمالة الخارجية والمساعدون</span><span class="small text-muted">التسجيل والدفع من مخصصات المشروع</span></div>
<div class="card-body">
<?php $laborTotal=0.0; $laborPaidTotal=0.0; foreach($labors as $laborSummary){$laborTotal+=(float)$laborSummary['payment_amount']; if(!empty($laborSummary['payment_expense_id'])) $laborPaidTotal+=(float)$laborSummary['paid_amount'];} $laborRemainingTotal=max(0,$laborTotal-$laborPaidTotal); ?>
<div class="row g-3 mb-3">
<div class="col-md-4"><div class="border rounded-3 p-3 h-100 bg-light"><div class="small text-muted">إجمالي العمالة</div><div class="fs-5 fw-bold"><?php echo akp_money($laborTotal); ?> <?php echo e($currency); ?></div></div></div>
<div class="col-md-4"><div class="border rounded-3 p-3 h-100 bg-light"><div class="small text-muted">المدفوع</div><div class="fs-5 fw-bold text-success"><?php echo akp_money($laborPaidTotal); ?> <?php echo e($currency); ?></div></div></div>
<div class="col-md-4"><div class="border rounded-3 p-3 h-100 bg-light"><div class="small text-muted">المتبقي</div><div class="fs-5 fw-bold text-warning"><?php echo akp_money($laborRemainingTotal); ?> <?php echo e($currency); ?></div></div></div>
</div>
<?php if($role==='project_supervisor'&&(akp_is_primary_supervisor($id)||akp_has_project_section($id,'operations'))&&!$closed): ?>
<div class="project-form-panel border rounded-3 p-3 mb-4"><div class="fw-semibold mb-3"><i class="fas fa-plus-circle me-1"></i>إضافة عمالة / مساعد</div>
<form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_labor"><div class="row g-3">
<div class="col-md-3"><label class="form-label small fw-semibold">نوع مقدم الخدمة</label><select name="labor_provider_type" class="form-select form-select-sm"><option value="individual">فرد</option><option value="company">شركة</option></select></div>
<div class="col-md-5"><label class="form-label small fw-semibold">اسم العامل/الشركة *</label><input name="labor_provider_name" class="form-control form-control-sm" required></div>
<div class="col-md-4"><label class="form-label small fw-semibold">الهاتف</label><input name="labor_phone" class="form-control form-control-sm"></div>
<div class="col-md-4"><label class="form-label small fw-semibold">جهة الاتصال</label><input name="labor_contact_person_name" class="form-control form-control-sm"></div>
<div class="col-md-4"><label class="form-label small fw-semibold">هاتف جهة الاتصال</label><input name="labor_contact_person_phone" class="form-control form-control-sm"></div>
<div class="col-md-4"><label class="form-label small fw-semibold">عدد العمال</label><input name="labor_number_of_workers" type="number" min="1" value="1" class="form-control form-control-sm"></div>
<div class="col-md-8"><label class="form-label small fw-semibold">وصف العمل/المساعدة *</label><input name="labor_work_description" class="form-control form-control-sm" required></div>
<div class="col-md-4"><label class="form-label small fw-semibold">إجمالي المبلغ *</label><input name="labor_payment_amount" type="number" step="0.01" min="0.01" class="form-control form-control-sm" required></div>
<div class="col-md-4"><label class="form-label small fw-semibold">توقيت الدفع</label><select name="labor_payment_timing" class="form-select form-select-sm"><option value="upfront">مقدم</option><option value="daily">يومي</option><option value="weekly">أسبوعي</option><option value="monthly">شهري</option><option value="upon_completion">عند الإنجاز</option></select></div>
<div class="col-md-4"><label class="form-label small fw-semibold">حالة التنفيذ</label><select name="labor_status" class="form-select form-select-sm"><option value="planned">مخطط</option><option value="in_progress">قيد التنفيذ</option><option value="completed">مكتمل</option></select></div>
<div class="col-12"><label class="form-label small fw-semibold">ملاحظات المشرف</label><textarea name="labor_notes" class="form-control form-control-sm" rows="2"></textarea></div>
<div class="col-12"><button class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>حفظ بيانات العمالة</button></div>
</div></form></div>
<?php endif; ?>
<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0"><thead class="table-primary"><tr><th>مقدم الخدمة</th><th>العمل</th><th>المبلغ</th><th>التنفيذ</th><th>الدفع</th><th>المستندات</th><th>الإجراءات</th></tr></thead><tbody>
<?php foreach($labors as $labor): ?><tr>
<td><div class="fw-semibold"><?php echo e($labor['provider_name']); ?></div><small class="text-muted"><?php echo e($labor['provider_type']); ?> · <?php echo (int)$labor['number_of_workers']; ?> عامل</small></td>
<td><?php echo e($labor['work_description']); ?><br><small class="text-muted"><?php echo e($labor['phone']?:'بدون هاتف'); ?></small></td>
<td class="fw-semibold"><?php echo akp_money($labor['payment_amount']); ?> <?php echo e($labor['currency_code']); ?></td>
<td><span class="badge bg-<?php echo $labor['status']==='completed'?'success':($labor['status']==='in_progress'?'primary':'secondary'); ?>"><?php echo e(akp_labor_status_label((string)$labor['status'])); ?></span></td>
<td><?php if(!empty($labor['payment_expense_id'])): ?><span class="badge bg-success">مدفوع</span><br><small><?php echo e('PRJ-LPV-' . ($project['project_code'] ?? $id) . '-' . $labor['id']); ?> · <?php echo e($labor['payment_date']); ?></small><?php else: ?><span class="badge bg-warning text-dark">غير مدفوع</span><?php endif; ?></td>
<td><?php if(!empty($labor['payment_expense_id'])): ?><a class="btn btn-sm btn-outline-dark mb-1" target="_blank" href="<?php echo APP_URL; ?>modules/accounting/voucher_print.php?labor_payment_id=<?php echo (int)$labor['payment_expense_id']; ?>"><i class="fas fa-file-invoice-dollar me-1"></i>السند</a><?php endif; ?><?php if(!empty($labor['payment_receipt_id'])): ?><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo APP_URL; ?>modules/projects/serve_project_document.php?id=<?php echo (int)$labor['payment_receipt_id']; ?>"><i class="fas fa-paperclip me-1"></i>الإيصال</a><?php endif; ?></td>
<td class="text-nowrap"><?php if(!$closed&&$role==='project_supervisor'&&akp_is_primary_supervisor($id)): ?><button type="button" class="btn btn-sm btn-outline-primary mb-1" data-bs-toggle="modal" data-bs-target="#editLaborModal" data-labor='<?php echo e(json_encode($labor, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?>'><i class="fas fa-pen me-1"></i>تعديل</button><form method="post" class="d-inline project-delete-form"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_labor"><input type="hidden" name="labor_id" value="<?php echo (int)$labor['id']; ?>"><button type="button" class="btn btn-sm btn-outline-danger project-delete-btn mb-1" data-confirm-title="حذف العمالة" data-confirm-text="سيتم حذف سجل العمالة والمصروف وسند الدفع والإيصال المرتبط به إن وُجد، وإعادة المبلغ إلى الرصيد المتاح للمشروع."><i class="fas fa-trash me-1"></i>حذف</button><?php if(empty($labor['payment_expense_id'])): ?><button type="button" class="btn btn-sm btn-primary mb-1" data-bs-toggle="modal" data-bs-target="#recordLaborPaymentModal" data-labor-id="<?php echo (int)$labor['id']; ?>" data-labor-name="<?php echo e($labor['provider_name']); ?>" data-labor-work="<?php echo e($labor['work_description']); ?>" data-labor-amount="<?php echo e($labor['payment_amount']); ?>" data-labor-currency="<?php echo e($labor['currency_code']); ?>"><i class="fas fa-money-bill-wave me-1"></i>تسجيل الدفع</button><?php endif; ?><?php endif; ?><?php if(akp_is_executive()&&!$closed): ?><form method="post" class="project-inline-form mt-1"><?php echo csrf_field(); ?><input type="hidden" name="action" value="comment_labor"><input type="hidden" name="labor_id" value="<?php echo (int)$labor['id']; ?>"><input name="labor_manager_comment" class="form-control form-control-sm d-inline-block" style="max-width:220px" placeholder="تعليق الإدارة"><button class="btn btn-sm btn-outline-primary mt-1">تعليق</button></form><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div>
<?php if(!$labors): ?><div class="text-center text-muted py-4">لا توجد عمالة أو مساعدون مسجلون.</div><?php endif; ?>
<?php if($role==='project_supervisor'&&akp_is_primary_supervisor($id)&&!$closed): ?>
<div class="modal fade" id="editLaborModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="fas fa-pen me-2"></i>تعديل العمالة الخارجية</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div><form method="post"><div class="modal-body"><?php echo csrf_field(); ?><input type="hidden" name="action" value="edit_labor"><input type="hidden" name="labor_id" id="edit-labor-id"><div class="row g-3"><div class="col-md-3"><label class="form-label small fw-semibold">نوع مقدم الخدمة</label><select name="labor_provider_type" id="edit-labor-provider-type" class="form-select form-select-sm"><option value="individual">فرد</option><option value="company">شركة</option></select></div><div class="col-md-5"><label class="form-label small fw-semibold">اسم العامل/الشركة *</label><input name="labor_provider_name" id="edit-labor-provider-name" class="form-control form-control-sm" required></div><div class="col-md-4"><label class="form-label small fw-semibold">الهاتف</label><input name="labor_phone" id="edit-labor-phone" class="form-control form-control-sm"></div><div class="col-md-4"><label class="form-label small fw-semibold">جهة الاتصال</label><input name="labor_contact_person_name" id="edit-labor-contact" class="form-control form-control-sm"></div><div class="col-md-4"><label class="form-label small fw-semibold">هاتف جهة الاتصال</label><input name="labor_contact_person_phone" id="edit-labor-contact-phone" class="form-control form-control-sm"></div><div class="col-md-4"><label class="form-label small fw-semibold">عدد العمال</label><input name="labor_number_of_workers" id="edit-labor-workers" type="number" min="1" class="form-control form-control-sm"></div><div class="col-md-8"><label class="form-label small fw-semibold">وصف العمل/المساعدة *</label><input name="labor_work_description" id="edit-labor-work" class="form-control form-control-sm" required></div><div class="col-md-4"><label class="form-label small fw-semibold">إجمالي المبلغ *</label><input name="labor_payment_amount" id="edit-labor-amount" type="number" step="0.01" min="0.01" class="form-control form-control-sm" required></div><div class="col-md-4"><label class="form-label small fw-semibold">توقيت الدفع</label><select name="labor_payment_timing" id="edit-labor-timing" class="form-select form-select-sm"><option value="upfront">مقدم</option><option value="daily">يومي</option><option value="weekly">أسبوعي</option><option value="monthly">شهري</option><option value="upon_completion">عند الإنجاز</option></select></div><div class="col-md-4"><label class="form-label small fw-semibold">حالة التنفيذ</label><select name="labor_status" id="edit-labor-status" class="form-select form-select-sm"><option value="planned">مخطط</option><option value="in_progress">قيد التنفيذ</option><option value="completed">مكتمل</option></select></div><div class="col-12"><label class="form-label small fw-semibold">ملاحظات المشرف</label><textarea name="labor_notes" id="edit-labor-notes" class="form-control form-control-sm" rows="2"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button><button class="btn btn-primary"><i class="fas fa-save me-1"></i>حفظ التعديل</button></div></form></div></div></div><div class="modal fade" id="recordLaborPaymentModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>تسجيل دفعة من ميزانية المشروع</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
<form method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="action" value="record_labor_payment"><input type="hidden" name="labor_id" id="record-labor-id"><div class="modal-body">
<div class="alert alert-light border"><div class="fw-semibold" id="record-labor-name"></div><div class="small text-muted" id="record-labor-work"></div><div class="mt-1 fw-bold" id="record-labor-amount"></div></div>
<div class="alert alert-info"><i class="fas fa-circle-info me-1"></i>سيتم خصم هذا المبلغ مباشرة من الرصيد الحالي لميزانية المشروع المعتمدة. لا يتم اختيار صندوق أو بنك أو محفظة، ولا ينشأ قيد إضافي على حسابات المنظمة.</div>
<div class="row g-3">
<div class="col-md-6"><label class="form-label small fw-semibold">تاريخ الدفع *</label><input type="date" name="labor_payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required></div>
<div class="col-md-6"><label class="form-label small fw-semibold">إيصال الدفع (اختياري)</label><input type="file" name="labor_payment_receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png"><div class="small text-muted mt-1">PDF أو JPG أو PNG، بحد أقصى 10 ميجابايت.</div></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button><button type="submit" class="btn btn-primary"><i class="fas fa-file-invoice-dollar me-1"></i>تسجيل الدفع وإصدار السند</button></div></form>
</div></div></div>
<script>
document.addEventListener('DOMContentLoaded',function(){var edit=document.getElementById('editLaborModal');if(edit)edit.addEventListener('show.bs.modal',function(event){var b=event.relatedTarget;if(!b)return;var d=JSON.parse(b.getAttribute('data-labor')||'{}');var map={'id':'edit-labor-id','provider_type':'edit-labor-provider-type','provider_name':'edit-labor-provider-name','phone':'edit-labor-phone','contact_person_name':'edit-labor-contact','contact_person_phone':'edit-labor-contact-phone','number_of_workers':'edit-labor-workers','work_description':'edit-labor-work','payment_amount':'edit-labor-amount','payment_timing':'edit-labor-timing','status':'edit-labor-status','notes':'edit-labor-notes'};Object.keys(map).forEach(function(k){var el=document.getElementById(map[k]);if(el)el.value=d[k]??'';});});var modal=document.getElementById('recordLaborPaymentModal');if(!modal)return;modal.addEventListener('show.bs.modal',function(event){var button=event.relatedTarget;if(!button)return;document.getElementById('record-labor-id').value=button.getAttribute('data-labor-id')||'';document.getElementById('record-labor-name').textContent=button.getAttribute('data-labor-name')||'';document.getElementById('record-labor-work').textContent=button.getAttribute('data-labor-work')||'';document.getElementById('record-labor-amount').textContent=(button.getAttribute('data-labor-amount')||'0')+' '+(button.getAttribute('data-labor-currency')||'');});});
</script>
<?php endif; ?>
</div></div>
<div class="card mb-4 fade-in">
            <div class="card-header"><i class="fas fa-list-check me-2"></i>التشغيل والتقدم</div>
            <div class="card-body">
<?php if (akp_can_edit_section('operations', $id) && !$closed): ?>
<form method="post" class="project-form-panel mb-3">
<input type="hidden" name="action" value="add_milestone">
<?php echo csrf_field(); ?>
<div class="row g-2">
<div class="col-md-6">
<label class="form-label small fw-semibold mb-1">عنوان المرحلة *</label>
<input name="milestone_title" class="form-control form-control-sm" placeholder="عنوان المرحلة" required>
</div>
<div class="col-md-3">
<label class="form-label small fw-semibold mb-1">التاريخ المخطط</label>
<input type="date" name="planned_date" class="form-control form-control-sm">
</div>
<div class="col-md-3">
<label class="form-label small fw-semibold mb-1">نسبة الإنجاز (%)</label>
<input type="number" min="0" max="100" name="completion_percent" class="form-control form-control-sm" placeholder="%">
</div>
<div class="col-12">
<label class="form-label small fw-semibold mb-1">وصف المرحلة</label>
<textarea name="milestone_description" class="form-control form-control-sm" rows="2" placeholder="وصف المرحلة"></textarea>
</div>
<div class="col-12"><button class="btn btn-sm btn-outline-primary mt-2">إضافة مرحلة</button></div>
</div>
</form>
<?php endif; ?>

<?php if ($milestones): ?>
<div class="table-responsive">
<table class="table table-sm table-bordered align-middle mb-0">
<thead class="table-primary">
<tr>
<th>عنوان المرحلة</th><th>التاريخ المخطط</th><th>نسبة الإنجاز (%)</th><th>وصف المرحلة</th>
<?php if (akp_can_edit_section('operations', $id) && !$closed): ?><th>الإجراءات</th><?php endif; ?>
</tr>
</thead>
<tbody>
<?php foreach ($milestones as $milestone): ?>
<tr>
<td><?php echo e($milestone['title']); ?></td>
<td><?php echo e($milestone['planned_date'] ?: 'بدون تاريخ'); ?></td>
<td class="fw-semibold"><?php echo akp_money($milestone['completion_percent']); ?>%</td>
<td><?php echo nl2br(e($milestone['description'] ?? '')); ?></td>
<?php if (akp_can_edit_section('operations', $id) && !$closed): ?>
<td class="text-nowrap">
<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editMilestoneModal" data-milestone-id="<?php echo (int)$milestone['id']; ?>" data-milestone-title="<?php echo e($milestone['title']); ?>" data-milestone-date="<?php echo e($milestone['planned_date'] ?? ''); ?>" data-milestone-percent="<?php echo e($milestone['completion_percent']); ?>" data-milestone-description="<?php echo e($milestone['description'] ?? ''); ?>"><i class="fas fa-pen"></i> تعديل</button>
<form method="post" class="d-inline">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="delete_milestone">
<input type="hidden" name="milestone_id" value="<?php echo (int)$milestone['id']; ?>">
<button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('هل تريد حذف هذه المرحلة؟');"><i class="fas fa-trash"></i> حذف</button>
</form>
</td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?><div class="text-muted small">لا توجد مراحل بعد.</div><?php endif; ?>

<?php if (akp_can_edit_section('operations', $id) && !$closed): ?>
<div class="modal fade" id="editMilestoneModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header"><h5 class="modal-title">تعديل المرحلة</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
<form method="post">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="edit_milestone">
<input type="hidden" name="milestone_id" id="edit-milestone-id">
<div class="modal-body">
<div class="row g-2">
<div class="col-md-6"><label class="form-label small fw-semibold">عنوان المرحلة *</label><input name="milestone_title" id="edit-milestone-title" class="form-control form-control-sm" required></div>
<div class="col-md-3"><label class="form-label small fw-semibold">التاريخ المخطط</label><input type="date" name="planned_date" id="edit-milestone-date" class="form-control form-control-sm"></div>
<div class="col-md-3"><label class="form-label small fw-semibold">نسبة الإنجاز (%)</label><input type="number" min="0" max="100" name="completion_percent" id="edit-milestone-percent" class="form-control form-control-sm"></div>
<div class="col-12"><label class="form-label small fw-semibold">وصف المرحلة</label><textarea name="milestone_description" id="edit-milestone-description" class="form-control form-control-sm" rows="2"></textarea></div>
</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button><button type="submit" class="btn btn-primary">حفظ التعديل</button></div>
</form>
</div>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
document.querySelectorAll('[data-bs-target="#editMilestoneModal"]').forEach(function (button) {
button.addEventListener('click', function () {
document.getElementById('edit-milestone-id').value = button.dataset.milestoneId || '';
document.getElementById('edit-milestone-title').value = button.dataset.milestoneTitle || '';
document.getElementById('edit-milestone-date').value = button.dataset.milestoneDate || '';
document.getElementById('edit-milestone-percent').value = button.dataset.milestonePercent || '0';
document.getElementById('edit-milestone-description').value = button.dataset.milestoneDescription || '';
});
});
});
</script>
<?php endif; ?>
</div>
</div>
<div class="card mb-4 fade-in">
<div class="card-header"><i class="fas fa-history me-2"></i>سجل التغييرات</div>
<div class="card-body">
<?php foreach ($history as $h): ?>
<div class="small border-bottom pb-2 mb-2">
<div><strong><?php echo e(akp_history_status_label((string)$h['old_status'])); ?></strong> → <strong><?php echo e(akp_history_status_label((string)$h['new_status'])); ?></strong></div>
<div class="text-muted"><?php echo e($h['full_name'] ?? 'نظام'); ?> · <?php echo e($h['created_at']); ?></div>
<?php if ($h['reason']): ?><div class="fst-italic">"<?php echo e($h['reason']); ?>"</div><?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (!$history): ?><div class="text-muted small">لا يوجد سجل تغييرات.</div><?php endif; ?>
</div>
</div>
</div>
</div>
<div class="row g-4 project-closure-section">
<div class="col-12">
<div class="card mb-4 fade-in border-0 shadow-sm overflow-hidden project-closure-card">
<div class="card-header bg-dark text-white py-3">
<div class="d-flex align-items-center justify-content-between gap-2">
<div class="d-flex align-items-center">
<span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-10 me-2" style="width:36px;height:36px;"><i class="fas fa-lock"></i></span>
<div>
<div class="fw-bold">الإغلاق وإعادة الفتح</div>
<div class="small text-white-50">إجراء إداري نهائي يمر عبر مدير المشاريع</div>
</div>
</div>
<?php if ($status === 'closed'): ?>
<span class="badge bg-success-subtle text-success-emphasis px-3 py-2"><i class="fas fa-lock me-1"></i>مغلق</span>
<?php else: ?>
<span class="badge bg-warning-subtle text-warning-emphasis px-3 py-2"><i class="fas fa-unlock me-1"></i>مفتوح</span>
<?php endif; ?>
</div>
</div>
<div class="card-body p-3 p-lg-4">
<?php if ($role === 'project_supervisor' && akp_is_primary_supervisor($id)): ?>
<?php if ($status !== 'closed'): ?>
<div class="border rounded-3 p-3 bg-light-subtle">
<div class="d-flex align-items-start gap-3">
<div class="text-primary fs-4"><i class="fas fa-paper-plane"></i></div>
<div class="flex-grow-1">
<h6 class="fw-bold mb-1">طلب إغلاق المشروع</h6>
<p class="small text-muted mb-3">مشرف المشروع لا ينفذ الإغلاق مباشرة. أرسل الطلب إلى مدير المشاريع مع الملاحظات ليقوم بالمراجعة والتنفيذ.</p>
<?php if ($closureRequest && (string)$closureRequest['new_status'] === 'closure_requested'): ?>
<div class="alert alert-info d-flex align-items-start gap-2 small mb-0">
<i class="fas fa-clock mt-1"></i>
<div><strong>الطلب قيد المراجعة.</strong><br>تم إرسال طلب الإغلاق إلى مدير المشاريع.</div>
</div>
<?php else: ?>
<form method="post">
<input type="hidden" name="action" value="request_project_closure">
<?php echo csrf_field(); ?>
<label class="form-label small fw-semibold">ملاحظات ومبررات الطلب <span class="text-danger">*</span></label>
<textarea name="closure_request_reason" class="form-control mb-3" rows="3" placeholder="اكتب ملاحظات إتمام المشروع أو أسباب طلب الإغلاق..." required></textarea>
<button class="btn btn-dark px-4"><i class="fas fa-paper-plane me-1"></i>إرسال طلب الإغلاق</button>
</form>
<?php endif; ?>
</div>
</div>
</div>
<?php else: ?>
<div class="border rounded-3 p-3 bg-light-subtle">
<div class="d-flex align-items-start gap-3">
<div class="text-warning fs-4"><i class="fas fa-lock-open"></i></div>
<div class="flex-grow-1">
<h6 class="fw-bold mb-1">طلب إعادة فتح المشروع</h6>
<p class="small text-muted mb-3">مشرف المشروع لا يعيد فتح المشروع مباشرة. أرسل الطلب إلى مدير المشاريع مع سبب واضح للمراجعة والتنفيذ.</p>
<?php if ($closureRequest && (string)$closureRequest['new_status'] === 'reopen_requested'): ?>
<div class="alert alert-info d-flex align-items-start gap-2 small mb-0">
<i class="fas fa-clock mt-1"></i>
<div><strong>الطلب قيد المراجعة.</strong><br>تم إرسال طلب إعادة الفتح إلى مدير المشاريع.</div>
</div>
<?php else: ?>
<form method="post">
<input type="hidden" name="action" value="request_project_reopen">
<?php echo csrf_field(); ?>
<label class="form-label small fw-semibold">سبب إعادة الفتح <span class="text-danger">*</span></label>
<textarea name="reopen_request_reason" class="form-control mb-3" rows="3" placeholder="اكتب سبب الحاجة إلى إعادة فتح المشروع..." required></textarea>
<button class="btn btn-warning px-4"><i class="fas fa-paper-plane me-1"></i>إرسال طلب إعادة الفتح</button>
</form>
<?php endif; ?>
</div>
</div>
</div>
<?php endif; ?>
<?php elseif ($role === 'projects_manager'): ?>
<?php if ($status !== 'closed'): ?>
<div class="border rounded-3 p-3 bg-warning-subtle">
<div class="d-flex align-items-start gap-3">
<div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-25 text-warning-emphasis" style="width:40px;height:40px;"><i class="fas fa-clipboard-check"></i></div>
<div class="flex-grow-1">
<h6 class="fw-bold mb-1">مراجعة طلب الإغلاق</h6>
<p class="small text-muted mb-3">مراجعة مدير المشاريع لطلب الإغلاق المرسل من مشرف المشروع قبل تنفيذ الإغلاق النهائي.</p>
<?php if ($closureRequest && (string)$closureRequest['new_status'] === 'closure_requested'): ?>
<div class="card border-0 shadow-sm mb-3">
<div class="card-body p-3">
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
<span class="badge bg-warning-subtle text-warning-emphasis"><i class="fas fa-clock me-1"></i>طلب إغلاق بانتظار الإجراء</span>
<span class="small text-muted">من مشرف المشروع</span>
</div>
<div class="small">
<div class="mb-1"><strong>مقدم الطلب:</strong> <?php echo e($closureRequest['requester_name'] ?? 'مشرف المشروع'); ?></div>
<?php if (!empty($closureRequest['reason'])): ?>
<div><strong>ملاحظات المشرف:</strong><div class="mt-1 p-2 bg-light rounded"><?php echo nl2br(e($closureRequest['reason'])); ?></div></div>
<?php endif; ?>
</div>
</div>
</div>
<form method="post" class="border-top pt-3">
<input type="hidden" name="action" value="close_project">
<?php echo csrf_field(); ?>
<div class="mb-3">
<label class="form-label small fw-semibold">ملخص الإغلاق <span class="text-danger">*</span></label>
<textarea name="closure_summary" class="form-control" rows="3" placeholder="اكتب ملخص الإغلاق والأسباب..." required></textarea>
</div>
<div class="row g-3">
<div class="col-md-6">
<label class="form-label small fw-semibold">تصنيف الإغلاق</label>
<select name="closure_reason" class="form-select">
<option value="completed_successfully">إنجاز كامل</option>
<option value="cancelled">إلغاء</option>
<option value="transferred_to_another_project">نقل لمشروع آخر</option>
<option value="retained_for_followup">احتفاظ للمتابعة</option>
<option value="other">أخرى</option>
</select>
</div>
<div class="col-md-6">
<label class="form-label small fw-semibold">تفسير فرق الميزانية <span class="text-muted">(اختياري)</span></label>
<input name="variance_explanation" class="form-control" placeholder="مثلاً: وفر في التنفيذ أو مصروف إضافي...">
</div>
</div>
<div class="d-flex justify-content-end mt-3">
<button class="btn btn-dark px-4"><i class="fas fa-lock me-1"></i>تنفيذ إغلاق المشروع</button>
</div>
</form>
<?php else: ?>
<div class="alert alert-light border d-flex align-items-center gap-2 small mb-0">
<i class="fas fa-info-circle text-muted"></i>
<span>لا يوجد طلب إغلاق معلق من مشرف المشروع.</span>
</div>
<?php endif; ?>
</div>
</div>
</div>
<?php else: ?>
<div class="border rounded-3 p-3 bg-warning-subtle">
<div class="d-flex align-items-start gap-3">
<div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-25 text-warning-emphasis" style="width:40px;height:40px;"><i class="fas fa-lock-open"></i></div>
<div class="flex-grow-1">
<h6 class="fw-bold mb-1">مراجعة طلب إعادة الفتح</h6>
<p class="small text-muted mb-3">مراجعة مدير المشاريع لطلب إعادة الفتح المرسل من مشرف المشروع قبل تنفيذ إعادة الفتح.</p>
<?php if ($closureRequest && (string)$closureRequest['new_status'] === 'reopen_requested'): ?>
<div class="card border-0 shadow-sm mb-3">
<div class="card-body p-3">
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
<span class="badge bg-warning-subtle text-warning-emphasis"><i class="fas fa-clock me-1"></i>طلب إعادة فتح بانتظار الإجراء</span>
<span class="small text-muted">من مشرف المشروع</span>
</div>
<div class="small">
<div class="mb-1"><strong>مقدم الطلب:</strong> <?php echo e($closureRequest['requester_name'] ?? 'مشرف المشروع'); ?></div>
<?php if (!empty($closureRequest['reason'])): ?>
<div><strong>سبب إعادة الفتح:</strong><div class="mt-1 p-2 bg-light rounded"><?php echo nl2br(e($closureRequest['reason'])); ?></div></div>
<?php endif; ?>
</div>
</div>
</div>
<form method="post" class="border-top pt-3">
<input type="hidden" name="action" value="reopen_project">
<?php echo csrf_field(); ?>
<div class="mb-3">
<label class="form-label small fw-semibold">سبب اعتماد إعادة الفتح <span class="text-danger">*</span></label>
<textarea name="reopen_reason" class="form-control" rows="3" placeholder="سجل سبب اعتماد إعادة فتح المشروع..." required></textarea>
</div>
<div class="d-flex justify-content-end">
<button class="btn btn-warning px-4"><i class="fas fa-lock-open me-1"></i>تنفيذ إعادة فتح المشروع</button>
</div>
</form>
<?php else: ?>
<div class="alert alert-light border d-flex align-items-center gap-2 small mb-0">
<i class="fas fa-info-circle text-muted"></i>
<span>لا يوجد طلب إعادة فتح معلق من مشرف المشروع.</span>
</div>
<?php endif; ?>
</div>
</div>
</div>
<?php endif; ?>
<?php elseif ($status === 'closed'): ?>
<div class="alert alert-light border mb-0 small"><i class="fas fa-lock me-1 text-success"></i>المشروع مغلق. الإغلاق وإعادة الفتح يتمان عبر مدير المشاريع بناءً على طلب مشرف المشروع.</div>
<?php else: ?>
<div class="alert alert-light border mb-0 small"><i class="fas fa-info-circle me-1"></i>إجراءات الإغلاق وإعادة الفتح محصورة بمدير المشاريع، وتبدأ بطلب من مشرف المشروع.</div>
<?php endif; ?>
</div>
</div>
</div>
<div class="modal fade" id="editProjectExpenseModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><i class="fas fa-pen me-2"></i>تعديل المصروف</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
</div>
<form method="post" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="edit_ps_expense">
<input type="hidden" name="expense_id" id="edit-expense-id">
<div class="modal-body">
<div class="row g-3">
<div class="col-md-4"><label class="form-label">التاريخ</label><input type="date" name="expense_date" id="edit-expense-date" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">الفئة</label><input name="expense_category" id="edit-expense-category" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">المبلغ</label><input type="number" step="0.01" min="0.01" name="expense_amount" id="edit-expense-amount" class="form-control" required></div>
<div class="col-12"><label class="form-label">الوصف</label><input name="expense_description" id="edit-expense-description" class="form-control" required></div>
<div class="col-md-6"><label class="form-label">المورد</label><input name="vendor_name" id="edit-expense-vendor" class="form-control"></div>
<div class="col-md-6"><label class="form-label">رقم الفاتورة</label><input name="invoice_number" id="edit-expense-invoice" class="form-control"></div>
<div class="col-12"><label class="form-label">إيصال الدفع</label><input type="file" name="expense_receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png"><div class="form-text">إذا لم يكن للمصروف إيصال محفوظ، يجب إرفاقه هنا. عند حفظ التعديل يتم تسجيل الدفع وترحيل المصروف مباشرة من ميزانية المشروع.</div></div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
<button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>حفظ التعديل وتسجيل الدفع</button>
</div>
</form>
</div>
</div>
</div>
<div class="modal fade" id="editProjectDocumentModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><i class="fas fa-pen me-2"></i>تعديل الوثيقة</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
</div>
<form method="post" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="edit_project_document">
<input type="hidden" name="document_id" id="edit-document-id">
<div class="modal-body">
<div class="row g-3">
<div class="col-md-4">
<label class="form-label">نوع الوثيقة</label>
<select name="document_type" id="edit-document-type" class="form-select" required>
<option value="invoice">فاتورة</option>
<option value="certificate">شهادة</option>
<option value="government_fee">رسم حكومي</option>
<option value="permit">تصريح</option>
<option value="contract">عقد</option>
<option value="quotation">عرض سعر</option>
<option value="progress_report">تقرير تقدم</option>
<option value="closure_report">تقرير إغلاق</option>
<option value="other">أخرى</option>
</select>
</div>
<div class="col-md-8"><label class="form-label">عنوان الوثيقة</label><input name="document_title" id="edit-document-title" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">تاريخ الوثيقة</label><input type="date" name="document_date" id="edit-document-date" class="form-control"></div>
<div class="col-md-4"><label class="form-label">الجهة المصدرة</label><input name="document_issuer" id="edit-document-issuer" class="form-control"></div>
<div class="col-md-4"><label class="form-label">رقم الوثيقة/المرجع</label><input name="document_reference" id="edit-document-reference" class="form-control"></div>
<div class="col-12"><label class="form-label">استبدال الملف (اختياري)</label><input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"><div class="form-text">يمكنك تركه فارغاً للاحتفاظ بالملف الحالي.</div></div>
<div class="col-12"><label class="form-label">ملاحظات</label><textarea name="document_notes" id="edit-document-notes" class="form-control" rows="3"></textarea></div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
<button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>حفظ التعديل</button>
</div>
</form>
</div>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
var expenseModal = document.getElementById('editProjectExpenseModal');
if (expenseModal) {
expenseModal.addEventListener('show.bs.modal', function (event) {
var button = event.relatedTarget;
if (!button) return;
document.getElementById('edit-expense-id').value = button.dataset.expenseId || '';
document.getElementById('edit-expense-date').value = button.dataset.expenseDate || '';
document.getElementById('edit-expense-category').value = button.dataset.expenseCategory || '';
document.getElementById('edit-expense-amount').value = button.dataset.expenseAmount || '';
document.getElementById('edit-expense-description').value = button.dataset.expenseDescription || '';
document.getElementById('edit-expense-vendor').value = button.dataset.expenseVendor || '';
document.getElementById('edit-expense-invoice').value = button.dataset.expenseInvoice || '';
});
}
var documentModal = document.getElementById('editProjectDocumentModal');
if (documentModal) {
documentModal.addEventListener('show.bs.modal', function (event) {
var button = event.relatedTarget;
if (!button) return;
document.getElementById('edit-document-id').value = button.dataset.documentId || '';
document.getElementById('edit-document-type').value = button.dataset.documentType || 'other';
document.getElementById('edit-document-title').value = button.dataset.documentTitle || '';
document.getElementById('edit-document-date').value = button.dataset.documentDate || '';
document.getElementById('edit-document-issuer').value = button.dataset.documentIssuer || '';
document.getElementById('edit-document-reference').value = button.dataset.documentReference || '';
document.getElementById('edit-document-notes').value = button.dataset.documentNotes || '';
});
}
document.querySelectorAll('.project-delete-btn').forEach(function (button) {
button.addEventListener('click', function () {
var form = button.closest('form');
if (!form) return;
var title = button.dataset.confirmTitle || 'تأكيد الحذف';
var text = button.dataset.confirmText || 'سيتم حذف هذا السجل نهائياً.';
if (window.Swal) {
Swal.fire({
title: title,
text: text,
icon: 'warning',
showCancelButton: true,
confirmButtonText: 'حذف',
cancelButtonText: 'إلغاء',
reverseButtons: true
}).then(function (result) {
if (result.isConfirmed) form.submit();
});
} else if (window.confirm(text)) {
form.submit();
}
});
});
});
</script>
<script>
/* Project view: preserve the user's scroll position through the POST/redirect/GET cycle. */
document.addEventListener('DOMContentLoaded', function () {
var projectScrollKey = 'ak_project_view_scroll_' + window.location.pathname + window.location.search;
try {
history.scrollRestoration = 'manual';
var savedScroll = sessionStorage.getItem(projectScrollKey);
if (savedScroll !== null) {
var scrollY = parseInt(savedScroll, 10);
if (!isNaN(scrollY)) {
window.scrollTo(0, scrollY);
setTimeout(function () { window.scrollTo(0, scrollY); }, 50);
}
sessionStorage.removeItem(projectScrollKey);
}
document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(function (form) {
form.addEventListener('submit', function () {
sessionStorage.setItem(projectScrollKey, String(window.scrollY || window.pageYOffset || 0));
});
});
} catch (e) {}
var projectToast = document.getElementById('projectSuccessToast');
if (projectToast && window.bootstrap && bootstrap.Toast) {
bootstrap.Toast.getOrCreateInstance(projectToast, {delay: 4500, autohide: true}).show();
}
});
</script>
<?php endif; ?>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>