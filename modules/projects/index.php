<?php
// modules/projects/index.php - Projects portfolio and financial progress
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';
Session::start();

$role = akp_role();
$portfolioRoles = ['admin', 'general_manager', 'vice_general_manager', 'projects_manager', 'project_supervisor', 'accountant', 'financial_manager'];
if (!Session::isLoggedIn() || !in_array($role, $portfolioRoles, true)) {
    // Assigned project users may still open a direct project link, but the portfolio list
    // is intentionally restricted to the established management/finance roles.
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'المشاريع';
$active = 'projects';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_status'])) {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة.');
    } else {
        $projectId = (int)$_POST['set_status'];
        $newStatus = (string)($_POST['new_status'] ?? '');
        $allowed = ['planned', 'active', 'completed', 'cancelled', 'under_review'];
        $project = akp_get_project($projectId);
        if (!$project || !akp_can_edit_section('operations', $projectId) || akp_project_is_closed($projectId)) {
            flash('error', 'لا تملك صلاحية تعديل حالة هذا المشروع أو أن المشروع مغلق.');
        } elseif (!in_array($newStatus, $allowed, true)) {
            flash('error', 'حالة المشروع غير صالحة.');
        } else {
            $oldStatus = (string)($project['lifecycle_status'] ?: $project['status']);
            $legacyStatus = in_array($newStatus, ['planned', 'active', 'completed', 'cancelled'], true) ? $newStatus : 'completed';
            dbExecute('UPDATE other_projects SET status = ?, updated_by = ? WHERE id = ?', [$legacyStatus, akp_user_id(), $projectId]);
            dbExecute('UPDATE project_lifecycle SET lifecycle_status = ? WHERE project_id = ?', [$newStatus, $projectId]);
            dbExecute('INSERT INTO project_status_history (project_id, old_status, new_status, reason, changed_by) VALUES (?,?,?,?,?)', [
                $projectId, $oldStatus, $newStatus, trim((string)($_POST['status_reason'] ?? '')) ?: null, akp_user_id()
            ]);
            akp_audit('STATUS_CHANGE', 'project_lifecycle', $projectId, ['status' => $oldStatus], ['status' => $newStatus]);
            flash('success', 'تم تحديث حالة المشروع.');
        }
    }
    header('Location: ' . APP_URL . 'modules/projects/index.php');
    exit();
}

$projects = dbFetchAll("SELECT p.*, COALESCE(l.lifecycle_status, p.status) AS current_status,
    COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.project_id = p.id AND t.status = 'posted'), 0) AS donation_total,
    COALESCE((SELECT SUM(f.amount) FROM project_funding_allocations f WHERE f.project_id = p.id AND f.status = 'posted'), 0) AS allocation_total,
    COALESCE((SELECT SUM(COALESCE(bl.approved_amount, bl.estimated_amount)) FROM project_budgets b JOIN project_budget_lines bl ON bl.budget_id = b.id WHERE b.project_id = p.id AND b.status = 'approved'), 0) AS approved_budget,
    COALESCE((SELECT SUM(e.amount) FROM project_expenses e WHERE e.project_id = p.id AND e.status = 'posted'), 0) AS expense_total,
    COALESCE((SELECT COUNT(*) FROM project_beneficiaries pb WHERE pb.project_id = p.id), 0) +
    COALESCE((SELECT COUNT(*) FROM project_beneficiary_records pbr WHERE pbr.project_id = p.id), 0) AS beneficiary_count,
    COALESCE((SELECT u.full_name FROM project_supervisor_assignments psa JOIN users u ON u.id = psa.supervisor_user_id WHERE psa.project_id = p.id AND psa.ended_at IS NULL ORDER BY psa.id DESC LIMIT 1), '—') AS supervisor_name,
    COALESCE((SELECT pa.approval_status FROM project_approval pa WHERE pa.project_id = p.id), 'approved') AS approval_status
    FROM other_projects p LEFT JOIN project_lifecycle l ON l.project_id = p.id
    ORDER BY p.id DESC");

$visibleProjects = [];
foreach ($projects as $project) {
    if (akp_can_view_project((int)$project['id'])) $visibleProjects[] = $project;
}
$projects = $visibleProjects;

$totTarget = 0.0; $totFunded = 0.0; $totExpenses = 0.0; $activeCount = 0;
foreach ($projects as $project) {
    $totTarget += (float)$project['approved_budget'] > 0 ? (float)$project['approved_budget'] : (float)$project['target_amount'];
    $totFunded += (float)$project['donation_total'] + (float)$project['allocation_total'];
    $totExpenses += (float)$project['expense_total'];
    if (in_array($project['current_status'], ['active', 'reopened'], true)) $activeCount++;
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>المشاريع</h2>
    <p>إدارة مشاريع المنظمة من الاعتماد والتمويل وحتى التنفيذ والإغلاق النهائي.</p>
    <div class="quick-actions mt-3">
        <?php if (akp_can_create_project()): ?>
            <a href="<?php echo APP_URL; ?>modules/projects/form.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> مشروع جديد</a>
        <?php endif; ?>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="row g-3 mb-4 fade-in">
    <div class="col-6 col-xl-3"><div class="card text-center" style="border-right:4px solid #1b4d8f"><div class="card-body py-2">
        <div class="fs-5 fw-bold" style="color:#1b4d8f"><?php echo count($projects); ?></div><div class="text-muted small">إجمالي المشاريع</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card text-center" style="border-right:4px solid #2d9c6f"><div class="card-body py-2">
        <div class="fs-5 fw-bold" style="color:#2d9c6f"><?php echo $activeCount; ?></div><div class="text-muted small">قيد التنفيذ</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card text-center" style="border-right:4px solid #1b4d8f"><div class="card-body py-2">
        <div class="fs-5 fw-bold" style="color:#1b4d8f"><?php echo number_format($totTarget, 2); ?></div><div class="text-muted small">الميزانية المعتمدة</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card text-center" style="border-right:4px solid #c94f4f"><div class="card-body py-2">
        <div class="fs-5 fw-bold" style="color:#c94f4f"><?php echo number_format($totExpenses, 2); ?></div><div class="text-muted small">المصروفات المرحلة</div>
    </div></div></div>
</div>

<div class="card fade-in">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>الكود</th><th>المشروع</th><th>النوع</th><th>المشرف</th><th>الميزانية</th><th>الممول</th><th>المصروف</th><th>التنفيذ المالي</th><th>المستفيدون</th><th>الحالة/الاعتماد</th><th class="text-center">إجراءات</th></tr></thead>
                <tbody>
                <?php if (!$projects): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">لا توجد مشاريع متاحة.</td></tr>
                <?php else: foreach ($projects as $project):
                    $budget = (float)$project['approved_budget'] > 0 ? (float)$project['approved_budget'] : (float)$project['target_amount'];
                    $funded = (float)$project['donation_total'] + (float)$project['allocation_total'];
                    $expense = (float)$project['expense_total'];
                    $pct = $budget > 0 ? min(100, round(($expense / $budget) * 100)) : 0;
                    $status = (string)$project['current_status'];
                    $badge = ['planned'=>'bg-secondary','active'=>'bg-success','completed'=>'bg-info','under_review'=>'bg-warning text-dark','closed'=>'bg-dark','reopened'=>'bg-primary','cancelled'=>'bg-danger'][$status] ?? 'bg-secondary';
                ?>
                    <tr>
                        <td><code><?php echo e($project['project_code'] ?? ''); ?></code></td>
                        <td><strong><?php echo e($project['name']); ?></strong><br><small class="text-muted"><?php echo e($project['city'] ?? ''); ?></small></td>
                        <td><?php echo e($project['project_type'] ?? '-'); ?></td>
                        <td><?php echo e($project['supervisor_name'] ?? '—'); ?><br><small class="text-muted"><?php echo e($project['approval_status']); ?></small></td>
                        <td><?php echo akp_money($budget); ?></td>
                        <td><?php echo akp_money($funded); ?></td>
                        <td><?php echo akp_money($expense); ?></td>
                        <td style="min-width:130px"><div class="progress" style="height:8px"><div class="progress-bar" style="width:<?php echo $pct; ?>%;background:#1b4d8f"></div></div><small class="text-muted"><?php echo $pct; ?>%</small></td>
                        <td><?php echo (int)$project['beneficiary_count']; ?></td>
                        <td><span class="badge <?php echo $badge; ?>"><?php echo e(akp_status_label($status)); ?></span></td>
                        <td class="text-center" style="white-space:nowrap;">
                            <a class="btn btn-sm btn-primary" title="عرض الملف" href="<?php echo APP_URL; ?>modules/projects/view.php?id=<?php echo (int)$project['id']; ?>"><i class="fas fa-eye"></i></a>
                            <?php if (akp_can_edit_section('general', (int)$project['id'])): ?>
                                <a class="btn btn-sm btn-warning" title="تعديل البيانات الأساسية" href="<?php echo APP_URL; ?>modules/projects/form.php?id=<?php echo (int)$project['id']; ?>"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>
                            <?php if (akp_can_edit_section('operations', (int)$project['id']) && $status !== 'closed'): ?>
                                <form method="post" class="d-inline"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="set_status" value="<?php echo (int)$project['id']; ?>">
                                    <select name="new_status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()" aria-label="تغيير حالة المشروع">
                                        <?php foreach (['planned'=>'مخطط','active'=>'قيد التنفيذ','under_review'=>'مراجعة ختامية','completed'=>'منجز','cancelled'=>'ملغي'] as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $status === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
