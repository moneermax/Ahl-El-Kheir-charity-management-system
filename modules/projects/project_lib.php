<?php
// modules/projects/project_lib.php - shared project-domain helpers
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_transaction_review.php';

if (!function_exists('akp_role')) {
    function akp_role(): string { return (string)Session::getUserRole(); }
}
if (!function_exists('akp_user_id')) {
    function akp_user_id(): int { return (int)Session::getUserId(); }
}
if (!function_exists('akp_is_executive')) {
    function akp_is_executive(): bool { return in_array(akp_role(), ['admin', 'general_manager', 'vice_general_manager'], true); }
}
if (!function_exists('akp_is_projects_manager')) {
    function akp_is_projects_manager(): bool { return akp_role() === 'projects_manager'; }
}
if (!function_exists('akp_is_primary_supervisor')) {
    function akp_is_primary_supervisor(int $projectId): bool { return (bool)dbFetchOne('SELECT id FROM project_supervisor_assignments WHERE project_id = ? AND supervisor_user_id = ? AND ended_at IS NULL LIMIT 1', [$projectId, akp_user_id()]); }
}
if (!function_exists('akp_is_dg')) {
    function akp_is_dg(): bool { return in_array(akp_role(), ['admin', 'general_manager'], true); }
}
if (!function_exists('akp_can_create_project')) {
    function akp_can_create_project(): bool { return in_array(akp_role(), ['admin', 'general_manager', 'vice_general_manager', 'projects_manager'], true); }
}

if (!function_exists('akp_can_prepare_finance')) {
    function akp_can_prepare_finance(int $projectId = 0): bool
    {
        if (akp_role() !== 'projects_manager') return false;
        if ($projectId > 0 && akp_project_is_closed($projectId)) return false;
        return true;
    }
}
if (!function_exists('akp_project_payment_method_from_account_code')) {
    function akp_project_payment_method_from_account_code(string $code): ?string
    {
        return ['1100' => 'cash', '1200' => 'bank_transfer', '1300' => 'e_wallet'][$code] ?? null;
    }
}

if (!function_exists('akp_can_manage_funding')) {
    function akp_can_manage_funding(int $projectId = 0): bool
    {
        if (akp_role() !== 'financial_manager') return false;
        if ($projectId > 0 && akp_project_is_closed($projectId)) return false;
        return true;
    }
}
if (!function_exists('akp_can_review_budget')) {
    /** FM may review/edit the submitted or financially rejected draft budget before project approval. */
    function akp_can_review_budget(int $projectId = 0): bool
    {
        if (akp_role() !== 'financial_manager') return false;
        if ($projectId > 0 && akp_project_is_closed($projectId)) return false;
        if ($projectId < 1) return true;
        $row = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$projectId]);
        return $row && in_array((string)$row['approval_status'], ['submitted', 'rejected'], true);
    }
}

if (!function_exists('akp_can_view_project')) {
    function akp_can_view_project(int $projectId): bool
    {
        if (in_array(akp_role(), ['admin', 'general_manager', 'vice_general_manager', 'projects_manager', 'accountant', 'financial_manager'], true)) return true;
        $row = dbFetchOne("SELECT 1 AS allowed FROM project_team WHERE project_id = ? AND user_id = ? AND unassigned_at IS NULL LIMIT 1", [$projectId, akp_user_id()]);
        if ($row) return true;
        return akp_is_primary_supervisor($projectId);
    }
}
if (!function_exists('akp_can_edit_section')) {
    function akp_can_edit_section(string $section, int $projectId = 0): bool
    {
        $role = akp_role();
        if ($role === 'admin') return true;
        if ($projectId > 0 && akp_project_is_closed($projectId) && !akp_is_dg()) return false;
        if ($section === 'general') {
            if (in_array($role, ['general_manager', 'vice_general_manager', 'projects_manager'], true)) return true;
            if ($projectId > 0 && akp_is_primary_supervisor($projectId)) {
                $approval = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$projectId]);
                return $approval && in_array($approval['approval_status'], ['draft', 'rejected'], true);
            }
            return false;
        }
        if ($section === 'finance') return in_array($role, ['general_manager', 'vice_general_manager', 'accountant', 'financial_manager'], true) || akp_has_project_section($projectId, 'finance');
        if ($section === 'operations') return $role === 'project_supervisor' && (akp_is_primary_supervisor($projectId) || akp_has_project_section($projectId, 'operations'));
        if ($section === 'documents') return $role === 'project_supervisor' && (akp_is_primary_supervisor($projectId) || akp_has_project_section($projectId, 'documents'));
        if ($section === 'closure') return $role === 'projects_manager';
        if ($section === 'team') return in_array($role, ['general_manager', 'vice_general_manager', 'projects_manager'], true);
        return false;
    }
}
if (!function_exists('akp_has_project_section')) {
    function akp_has_project_section(int $projectId, string $section): bool
    {
        if ($projectId < 1) return false;
        $row = dbFetchOne("SELECT 1 AS allowed FROM project_team WHERE project_id = ? AND user_id = ? AND section_code = ? AND unassigned_at IS NULL LIMIT 1", [$projectId, akp_user_id(), $section]);
        return (bool)$row;
    }
}
if (!function_exists('akp_audit')) {
    function akp_audit(string $action, string $entityType, int $entityId, ?array $oldValues, ?array $newValues): void
    {
        try {
            dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [akp_user_id(), $action, $entityType, $entityId, $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE), $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            if (in_array($action, ['FM_REJECT_PROJECT', 'REJECT_PROJECT'], true) && $entityType === 'project_approval') {
                try {
                    $project = dbFetchOne('SELECT project_code, name FROM other_projects WHERE id = ?', [$entityId]);
                    if ($project) {
                        $reason = trim((string)($newValues['reason'] ?? ''));
                        if ($action === 'FM_REJECT_PROJECT') {
                            $projectManagers = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code = 'projects_manager' AND u.is_active = 1");
                            foreach ($projectManagers as $projectManager) {
                                ak_transaction_review_notify_event((int)$projectManager['id'], 'تم رفض المشروع مالياً', 'المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') تم رفضه مالياً وإعادته للمراجعة. السبب: ' . $reason, APP_URL . 'modules/projects/view.php?id=' . $entityId, $entityId, 'project_fm_rejection');
                            }
                        } else {
                            ak_transaction_review_notify_fm_event($entityId, 'project_final_rejection', 'تم رفض المشروع نهائياً من المدير العام', 'المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') تم رفضه نهائياً بعد الاعتماد المالي. السبب: ' . $reason, APP_URL . 'modules/projects/view.php?id=' . $entityId);
                        }
                    }
                } catch (Throwable $notificationError) {}
            }
        } catch (Throwable $e) {}
    }
}
if (!function_exists('akp_get_project')) {
    function akp_get_project(int $projectId): ?array
    {
        $row = dbFetchOne("SELECT p.*, l.lifecycle_status, l.closed_at, l.closed_by, l.reopened_at, l.reopened_by, l.closure_summary, l.final_budget_amount, l.total_funded_amount, l.total_expensed_amount, l.variance_amount, l.variance_percent, l.variance_explanation, l.residual_amount, l.residual_action FROM other_projects p LEFT JOIN project_lifecycle l ON l.project_id = p.id WHERE p.id = ?", [$projectId]);
        return $row ?: null;
    }
}
if (!function_exists('akp_project_is_closed')) {
    function akp_project_is_closed(int $projectId): bool
    {
        $row = dbFetchOne("SELECT lifecycle_status FROM project_lifecycle WHERE project_id = ?", [$projectId]);
        return $row && $row['lifecycle_status'] === 'closed';
    }
}
if (!function_exists('akp_sync_lifecycle_row')) {
    function akp_sync_lifecycle_row(int $projectId): void
    {
        $project = dbFetchOne("SELECT status, target_amount, currency_code FROM other_projects WHERE id = ?", [$projectId]);
        if (!$project) return;
        $existing = dbFetchOne("SELECT project_id FROM project_lifecycle WHERE project_id = ?", [$projectId]);
        if (!$existing) {
            $status = in_array($project['status'], ['planned', 'active', 'completed', 'cancelled'], true) ? $project['status'] : 'planned';
            dbExecute("INSERT INTO project_lifecycle (project_id, lifecycle_status, final_budget_amount) VALUES (?, ?, ?)", [$projectId, $status, $project['target_amount']]);
        }
    }
}
if (!function_exists('akp_project_totals')) {
    function akp_project_totals(int $projectId): array
    {
        $funded = dbFetchOne("SELECT COALESCE(SUM(f.amount),0) AS value FROM project_funding_allocations f WHERE f.project_id = ? AND f.status = 'posted' AND (f.transaction_id IS NULL OR NOT EXISTS (SELECT 1 FROM transactions t WHERE t.id = f.transaction_id AND t.status = 'posted'))", [$projectId]);
        $donations = dbFetchOne("SELECT COALESCE(SUM(amount),0) AS value FROM transactions WHERE project_id = ? AND status = 'posted'", [$projectId]);
        $expenses = dbFetchOne("SELECT COALESCE(SUM(amount),0) AS value FROM project_expenses WHERE project_id = ? AND status = 'posted'", [$projectId]);
        $budget = dbFetchOne("SELECT COALESCE(SUM(COALESCE(bl.approved_amount, bl.estimated_amount)),0) AS value FROM project_budgets b JOIN project_budget_lines bl ON bl.budget_id = b.id WHERE b.project_id = ? AND b.status = 'approved'", [$projectId]);
        $fundingAllocations = (float)($funded['value'] ?? 0); $donationAmount = (float)($donations['value'] ?? 0); $totalFunded = $fundingAllocations + $donationAmount; $totalExpensed = (float)($expenses['value'] ?? 0); $approvedBudget = (float)($budget['value'] ?? 0);
        if ($approvedBudget <= 0) { $p = dbFetchOne("SELECT target_amount FROM other_projects WHERE id = ?", [$projectId]); $approvedBudget = (float)($p['target_amount'] ?? 0); }
        $variance = $totalExpensed - $approvedBudget; $percent = $approvedBudget > 0 ? ($variance / $approvedBudget) * 100 : null;
        return ['approved_budget'=>$approvedBudget,'funding_allocations'=>$fundingAllocations,'donations'=>$donationAmount,'total_funded'=>$totalFunded,'total_expensed'=>$totalExpensed,'variance'=>$variance,'variance_percent'=>$percent,'residual'=>$totalFunded-$totalExpensed];
    }
}
if (!function_exists('akp_sync_closure_totals')) {
    function akp_sync_closure_totals(int $projectId): array
    {
        $totals = akp_project_totals($projectId);
        dbExecute("UPDATE project_lifecycle SET final_budget_amount = ?, total_funded_amount = ?, total_expensed_amount = ?, variance_amount = ?, variance_percent = ?, residual_amount = ? WHERE project_id = ?", [$totals['approved_budget'], $totals['total_funded'], $totals['total_expensed'], $totals['variance'], $totals['variance_percent'], $totals['residual'], $projectId]);
        return $totals;
    }
}
if (!function_exists('akp_status_label')) {
    function akp_status_label(string $status): string { return ['planned'=>'مخطط','active'=>'قيد التنفيذ','completed'=>'منجز','under_review'=>'قيد المراجعة الختامية','closed'=>'مغلق','reopened'=>'معاد فتحه','cancelled'=>'ملغي'][$status] ?? $status; }
}
if (!function_exists('akp_money')) {
    function akp_money($value): string { return number_format((float)$value, 2); }
}