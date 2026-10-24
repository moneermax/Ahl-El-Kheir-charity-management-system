<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$role = (string)$user['role_code'];

$portfolioRoles = [
    'admin', 'general_manager', 'vice_general_manager',
    'projects_manager', 'project_supervisor', 'accountant', 'financial_manager',
];
if (!in_array($role, $portfolioRoles, true)) {
    ak_api_json(403, [
        'ok' => false,
        'error' => [
            'code' => 'forbidden',
            'message' => 'This account cannot access the project portfolio.',
        ],
    ]);
}

function ak_api_can_view_project_portfolio_row(array $project, int $userId, string $role): bool
{
    if (in_array($role, [
        'admin', 'general_manager', 'vice_general_manager', 'accountant', 'financial_manager',
    ], true)) {
        return true;
    }

    $projectId = (int)$project['id'];

    if ($role === 'projects_manager') {
        $confirmed = dbFetchOne(
            "SELECT id FROM audit_log
             WHERE action = 'FM_CONFIRM_PAYMENT_EVIDENCE'
               AND entity_type = 'project_payment_evidence'
               AND entity_id = ? LIMIT 1",
            [$projectId]
        );
        if ($confirmed) return true;

        $pmProject = dbFetchOne(
            "SELECT op.created_by,
                    COALESCE(pa.approval_status, 'draft') AS approval_status,
                    COALESCE(pl.lifecycle_status, op.status) AS lifecycle_status,
                    (SELECT h.new_status FROM project_status_history h
                     WHERE h.project_id = op.id ORDER BY h.id DESC LIMIT 1) AS latest_status
             FROM other_projects op
             LEFT JOIN project_approval pa ON pa.project_id = op.id
             LEFT JOIN project_lifecycle pl ON pl.project_id = op.id
             WHERE op.id = ?",
            [$projectId]
        );

        if (
            $pmProject
            && (string)$pmProject['approval_status'] === 'approved'
            && (string)$pmProject['lifecycle_status'] === 'under_review'
            && (string)($pmProject['latest_status'] ?? '') === 'closure_requested'
        ) return true;

        return $pmProject
            && (int)$pmProject['created_by'] === $userId
            && in_array((string)$pmProject['approval_status'], [
                'draft', 'submitted', 'rejected', 'fm_approved',
            ], true);
    }

    if ($role === 'project_supervisor') {
        $launchState = dbFetchOne(
            "SELECT pa.approval_status,
                    COALESCE(pl.lifecycle_status, op.status) AS lifecycle_status
             FROM other_projects op
             LEFT JOIN project_approval pa ON pa.project_id = op.id
             LEFT JOIN project_lifecycle pl ON pl.project_id = op.id
             WHERE op.id = ?",
            [$projectId]
        );

        if (
            !$launchState
            || (string)$launchState['approval_status'] !== 'approved'
            || !in_array((string)$launchState['lifecycle_status'], [
                'active', 'reopened', 'completed', 'under_review', 'closed', 'cancelled',
            ], true)
        ) return false;

        $assignedSupervisor = dbFetchOne(
            'SELECT id FROM project_supervisor_assignments
             WHERE project_id = ? AND supervisor_user_id = ? AND ended_at IS NULL LIMIT 1',
            [$projectId, $userId]
        );
        if ($assignedSupervisor) return true;

        return (bool)dbFetchOne(
            "SELECT id FROM project_team
             WHERE project_id = ? AND user_id = ?
               AND section_code IN ('operations', 'documents')
               AND unassigned_at IS NULL LIMIT 1",
            [$projectId, $userId]
        );
    }

    $teamAssignment = dbFetchOne(
        'SELECT id FROM project_team
         WHERE project_id = ? AND user_id = ? AND unassigned_at IS NULL LIMIT 1',
        [$projectId, $userId]
    );
    if ($teamAssignment) return true;

    return (bool)dbFetchOne(
        'SELECT id FROM project_supervisor_assignments
         WHERE project_id = ? AND supervisor_user_id = ? AND ended_at IS NULL LIMIT 1',
        [$projectId, $userId]
    );
}

$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

$projects = dbFetchAll(
    "SELECT p.id, p.project_code, p.name, p.target_amount,
            COALESCE(l.lifecycle_status, p.status) AS current_status,
            COALESCE((SELECT SUM(t.amount) FROM transactions t
                      WHERE t.project_id = p.id AND t.status = 'posted'), 0) AS donation_total,
            COALESCE((SELECT SUM(f.amount) FROM project_funding_allocations f
                      WHERE f.project_id = p.id AND f.status = 'posted'
                        AND (f.transaction_id IS NULL OR NOT EXISTS (
                            SELECT 1 FROM transactions t
                            WHERE t.id = f.transaction_id AND t.status = 'posted'
                        ))), 0) AS allocation_total,
            COALESCE((SELECT SUM(COALESCE(bl.approved_amount, bl.estimated_amount))
                      FROM project_budgets b
                      JOIN project_budget_lines bl ON bl.budget_id = b.id
                      WHERE b.project_id = p.id AND b.status = 'approved'), 0) AS approved_budget,
            COALESCE((SELECT SUM(e.amount) FROM project_expenses e
                      WHERE e.project_id = p.id AND e.status = 'posted'), 0) AS expense_total,
            COALESCE((SELECT COUNT(*) FROM project_beneficiaries pb
                      WHERE pb.project_id = p.id), 0)
              + COALESCE((SELECT COUNT(*) FROM project_beneficiary_records pbr
                          WHERE pbr.project_id = p.id), 0) AS beneficiary_count,
            COALESCE((SELECT u.full_name FROM project_supervisor_assignments psa
                      JOIN users u ON u.id = psa.supervisor_user_id
                      WHERE psa.project_id = p.id AND psa.ended_at IS NULL
                      ORDER BY psa.id DESC LIMIT 1), '—') AS supervisor_name,
            COALESCE((SELECT pa.approval_status FROM project_approval pa
                      WHERE pa.project_id = p.id), 'approved') AS approval_status
     FROM other_projects p
     LEFT JOIN project_lifecycle l ON l.project_id = p.id
     ORDER BY p.id DESC"
);

$visibleProjects = [];
foreach ($projects as $project) {
    if (ak_api_can_view_project_portfolio_row($project, $userId, $role)) {
        $visibleProjects[] = $project;
    }
}

$total = count($visibleProjects);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$visibleProjects = array_slice($visibleProjects, ($page - 1) * $perPage, $perPage);

$items = array_map(
    static function (array $project): array {
        $approvedBudget = (float)$project['approved_budget'];
        return [
            'id' => (int)$project['id'],
            'project_code' => (string)($project['project_code'] ?? ''),
            'name' => (string)($project['name'] ?? ''),
            'current_status' => (string)($project['current_status'] ?? ''),
            'approval_status' => (string)($project['approval_status'] ?? ''),
            'budget' => $approvedBudget > 0 ? $approvedBudget : (float)$project['target_amount'],
            'funded' => (float)$project['donation_total'] + (float)$project['allocation_total'],
            'expenses' => (float)$project['expense_total'],
            'beneficiary_count' => (int)$project['beneficiary_count'],
            'supervisor_name' => (string)($project['supervisor_name'] ?? '—'),
        ];
    },
    $visibleProjects
);

ak_api_json(200, [
    'ok' => true,
    'data' => ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages],
]);
