<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$allowedRoles = ['admin', 'general_manager', 'vice_general_manager', 'financial_manager'];
if (!in_array((string)$user['role_code'], $allowedRoles, true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Reconciliation access is not available for this role.']]);
}

try {
    $ledger = dbFetchAll(
        "SELECT DATE_FORMAT(j.entry_date, '%Y-%m') AS month,
                COALESCE(SUM(CASE WHEN a.code LIKE '4%' THEN (jl.credit - jl.debit) ELSE 0 END), 0) AS inflow,
                COALESCE(SUM(CASE WHEN a.code LIKE '5%' THEN (jl.debit - jl.credit) ELSE 0 END), 0) AS outflow,
                COALESCE(SUM(CASE WHEN a.code LIKE '1%' THEN (jl.debit - jl.credit) ELSE 0 END), 0) AS cashnet
         FROM journal_lines jl
         JOIN journal_entries j ON j.id = jl.entry_id
         JOIN accounts a ON a.id = jl.account_id
         WHERE j.status = 'posted'
         GROUP BY month
         ORDER BY month DESC"
    );

    $monthlyDisbursements = dbFetchAll(
        "SELECT month, COUNT(*) AS count, COALESCE(SUM(total_amount), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN status = 'voided' THEN total_amount ELSE 0 END), 0) AS voided_total
         FROM monthly_disbursements
         GROUP BY month ORDER BY month DESC"
    );

    $openBatches = dbFetchAll(
        "SELECT d.id, d.month, d.status, d.created_at, d.total_amount, n.full_name AS nanny_name,
                (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id) AS item_count,
                (SELECT COUNT(*) FROM disbursement_items i
                 WHERE i.disbursement_id = d.id AND i.status IN ('pending', 'return_requested')) AS open_item_count
         FROM monthly_disbursements d
         LEFT JOIN users n ON n.id = d.nanny_id
         WHERE d.status IN ('draft', 'pending', 'pending_approval', 'approved', 'transferred')
         ORDER BY d.created_at ASC"
    );

    $returns = dbFetchAll(
        "SELECT i.id, i.amount, i.return_reason, i.returned_at, i.reversal_journal_id,
                d.month, n.full_name AS nanny_name, u.full_name AS returned_by
         FROM disbursement_items i
         JOIN monthly_disbursements d ON d.id = i.disbursement_id
         LEFT JOIN users n ON n.id = d.nanny_id
         LEFT JOIN users u ON u.id = i.returned_by_user_id
         WHERE i.status = 'returned'
         ORDER BY i.returned_at DESC LIMIT 50"
    );

    $voidedBatches = dbFetchAll(
        "SELECT d.id, d.month, d.total_amount, d.void_reason, d.voided_at,
                u.full_name AS voided_by, d.reversal_journal_id
         FROM monthly_disbursements d
         LEFT JOIN users u ON u.id = d.voided_by_user_id
         WHERE d.status = 'voided'
         ORDER BY d.voided_at DESC LIMIT 50"
    );
} catch (Throwable $exception) {
    error_log('Mobile GM reconciliation read failed: ' . $exception->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'reconciliation_unavailable', 'message' => 'Reconciliation data is temporarily unavailable.']]);
}

$totalInflow = 0.0;
$totalOutflow = 0.0;
$totalCashNet = 0.0;
foreach ($ledger as $row) {
    $totalInflow += (float)$row['inflow'];
    $totalOutflow += (float)$row['outflow'];
    $totalCashNet += (float)$row['cashnet'];
}

ak_api_json(200, [
    'ok' => true,
    'data' => [
        'summary' => [
            'total_inflow' => $totalInflow,
            'total_outflow' => $totalOutflow,
            'total_cash_net' => $totalCashNet,
            'difference' => $totalInflow - $totalOutflow,
        ],
        'monthly_ledger' => $ledger,
        'monthly_disbursements' => $monthlyDisbursements,
        'open_batches' => $openBatches,
        'returns' => $returns,
        'voided_batches' => $voidedBatches,
    ],
]);
