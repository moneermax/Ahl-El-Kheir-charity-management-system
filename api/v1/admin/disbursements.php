<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)($user['raw_role_code'] ?? $user['role_code'] ?? '');
$allowedRoles = ['admin', 'financial_manager', 'general_manager', 'accountant_staff', 'nanny'];
if (!in_array($role, $allowedRoles, true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'This role cannot access monthly disbursements.']]);
}
$userId = (int)($user['id'] ?? 0);
$canCreateBatches = in_array($role, ['admin', 'financial_manager', 'general_manager', 'accountant_staff'], true);
$canTransfer = in_array($role, ['admin', 'financial_manager', 'general_manager'], true);
$canVoid = in_array($role, ['admin', 'financial_manager'], true);
$canReopen = $canCreateBatches;
$canConfirmItems = $role === 'nanny';
$capabilities = [
    'can_create_batches' => $canCreateBatches,
    'can_transfer' => $canTransfer,
    'can_void' => $canVoid,
    'can_reopen' => $canReopen,
    'can_confirm_items' => $canConfirmItems,
];
$scopeSql = '';
$scopeParams = [];
if ($role === 'nanny') {
    $scopeSql = 'd.nanny_id = ?';
    $scopeParams[] = $userId;
} elseif ($role === 'accountant_staff') {
    $scopeSql = 'EXISTS (SELECT 1 FROM accountant_nanny_assignments ana WHERE ana.accountant_id = ? AND ana.nanny_id = d.nanny_id)';
    $scopeParams[] = $userId;
}

$month = trim((string)($_GET['month'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));
$query = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$batchId = max(0, (int)($_GET['id'] ?? 0));
$allowedStatuses = ['draft', 'pending_approval', 'approved', 'transferred', 'received', 'returned', 'voided', 'cancelled'];

if ($month !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    ak_api_json(400, ['ok' => false, 'error' => ['code' => 'invalid_month', 'message' => 'Month must use YYYY-MM format.']]);
}
if ($status !== 'all' && !in_array($status, $allowedStatuses, true)) {
    ak_api_json(400, ['ok' => false, 'error' => ['code' => 'invalid_status', 'message' => 'Unsupported disbursement status.']]);
}
if (strlen($query) > 100) {
    ak_api_json(400, ['ok' => false, 'error' => ['code' => 'query_too_long', 'message' => 'Search text is too long.']]);
}

try {
    $where = [];
    $params = [];
    if ($scopeSql !== '') {
        $where[] = $scopeSql;
        $params = array_merge($params, $scopeParams);
    }
    if ($month !== '') {
        $where[] = 'd.month = ?';
        $params[] = $month;
    }
    if ($status !== 'all') {
        $where[] = 'd.status = ?';
        $params[] = $status;
    }
    if ($query !== '') {
        $where[] = '(n.full_name LIKE ? OR og.group_name LIKE ? OR CAST(d.id AS CHAR) = ?)';
        $needle = '%' . $query . '%';
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $query;
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $previewMonth = $month !== '' ? $month : date('Y-m');
    $eligibleGroups = [];
    if ($canCreateBatches) {
        $eligibleGroups = dbFetchAll(
            "SELECT og.id, og.group_name, og.description, u.full_name AS nanny_name,
                    nga.nanny_id, COUNT(DISTINCT gc.child_id) AS orphan_count,
                    COALESCE(SUM(sp.monthly_amount),0) AS expected_amount
             FROM orphan_groups og
             LEFT JOIN nanny_group_assignments nga ON nga.group_id=og.id AND nga.end_date IS NULL
             LEFT JOIN users u ON u.id=nga.nanny_id
             LEFT JOIN group_children gc ON gc.group_id=og.id AND gc.left_date IS NULL
             LEFT JOIN family_children fc ON fc.id=gc.child_id
             INNER JOIN nanny_family_verifications v ON v.family_id=fc.family_id AND v.month=?
                AND v.is_orphan_verified=1 AND v.is_mother_contact_verified=1 AND v.is_bank_verified=1
             LEFT JOIN sponsorships sp ON sp.child_id=gc.child_id AND sp.status='active'
             WHERE og.verification_status IN ('submitted','approved','pending')
               AND (?=0 OR EXISTS (
                   SELECT 1 FROM accountant_nanny_assignments ana
                   WHERE ana.accountant_id=? AND ana.nanny_id=nga.nanny_id
               ))
             GROUP BY og.id, og.group_name, og.description, u.full_name, nga.nanny_id
             ORDER BY og.group_name",
            [$previewMonth, $role === 'accountant_staff' ? $userId : 0, $role === 'accountant_staff' ? $userId : 0]
        );
    }

    if ($batchId > 0) {
        $batch = dbFetchOne(
            "SELECT d.id, d.month, d.status, d.total_amount, d.created_at, d.nanny_id,
                    d.transaction_id, d.receipt_file_path, d.transfer_receipt_at, d.transfer_receipt_by_user_id,
                    n.full_name AS nanny_name, og.group_name, og.id AS group_id,
                    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id) AS items_total,
                    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status = 'paid') AS items_paid,
                    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status = 'pending') AS items_pending,
                    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status = 'returned') AS items_returned,
                    (SELECT COALESCE(SUM(i.amount), 0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status = 'paid') AS paid_amount,
                    (SELECT COALESCE(SUM(i.amount), 0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status = 'pending') AS pending_amount,
                    (SELECT COALESCE(SUM(i.amount), 0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status = 'returned') AS returned_amount
             FROM monthly_disbursements d
             LEFT JOIN users n ON n.id = d.nanny_id
             LEFT JOIN orphan_groups og ON og.id = d.group_id
             WHERE d.id = ?" . ($scopeSql !== '' ? ' AND ' . $scopeSql : ''),
            array_merge([$batchId], $scopeParams)
        );
        if (!$batch) {
            ak_api_json(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Disbursement batch was not found.']]);
        }
        $itemsPerPage = 100;
        $itemsTotal = (int)($batch['items_total'] ?? 0);
        $itemsPages = max(1, (int)ceil($itemsTotal / $itemsPerPage));
        $itemsPage = min(max(1, (int)($_GET['items_page'] ?? 1)), $itemsPages);
        $items = dbFetchAll(
            "SELECT i.id, i.family_id, f.family_code, f.mother_name, i.amount, i.status, i.confirmed_at, i.receipt_file_path, i.return_reason,
                    (SELECT COUNT(*) FROM family_children fc WHERE fc.family_id = f.id AND fc.is_active = 1) AS children_count
             FROM disbursement_items i
             JOIN families f ON f.id = i.family_id
             WHERE i.disbursement_id = ?
             ORDER BY f.mother_name, i.id
             LIMIT " . $itemsPerPage . " OFFSET " . (($itemsPage - 1) * $itemsPerPage),
            [$batchId]
        );
        ak_api_json(200, ['ok' => true, 'data' => [
            'batch' => $batch,
            'capabilities' => $capabilities,
            'items' => $items,
            'items_pagination' => [
                'page' => $itemsPage,
                'per_page' => $itemsPerPage,
                'total' => $itemsTotal,
                'pages' => $itemsPages,
            ],
        ]]);
    }

    $totalRow = dbFetchOne(
        "SELECT COUNT(*) AS total
         FROM monthly_disbursements d
         LEFT JOIN users n ON n.id = d.nanny_id
         LEFT JOIN orphan_groups og ON og.id = d.group_id" . $whereSql,
        $params
    );
    $total = (int)($totalRow['total'] ?? 0);
    $perPage = 50;
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;
    $rows = dbFetchAll(
        "SELECT d.id, d.month, d.status, d.total_amount, d.created_at, d.nanny_id,
                n.full_name AS nanny_name, og.group_name, og.id AS group_id,
                (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id) AS items_total,
                (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status = 'paid') AS items_paid,
                (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status = 'pending') AS items_pending,
                (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status = 'returned') AS items_returned,
                (SELECT COALESCE(SUM(i.amount), 0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status = 'paid') AS paid_amount,
                (SELECT COALESCE(SUM(i.amount), 0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status = 'pending') AS pending_amount,
                (SELECT COALESCE(SUM(i.amount), 0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status = 'returned') AS returned_amount
         FROM monthly_disbursements d
         LEFT JOIN users n ON n.id = d.nanny_id
         LEFT JOIN orphan_groups og ON og.id = d.group_id" . $whereSql . "
         ORDER BY d.created_at DESC, d.id DESC
         LIMIT " . $perPage . " OFFSET " . $offset,
        $params
    );
    ak_api_json(200, ['ok' => true, 'data' => [
        'filters' => ['month' => $month, 'status' => $status, 'q' => $query],
        'capabilities' => $capabilities,
        'eligible_groups' => $eligibleGroups,
        'eligible_month' => $previewMonth,
        'items' => $rows,
        'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $pages],
    ]]);
} catch (Throwable $e) {
    error_log('Mobile disbursements failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'disbursements_failed', 'message' => 'Disbursement records could not be loaded.']]);
}
