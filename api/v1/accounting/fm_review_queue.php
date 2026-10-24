<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$allowedRoles = ['financial_manager', 'fm', 'finance', 'admin', 'sudo', 'general_manager'];
if (!in_array((string)$user['role_code'], $allowedRoles, true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Financial review queue access is not allowed for this account.',
    ]]);
}

$purposeLabels = [
    'monthly_sponsorship' => 'كفالة شهرية',
    'school_fees' => 'رسوم دراسية',
    'medicine' => 'علاج وأدوية',
    'gift' => 'هدية/عيدية',
    'other' => 'أخرى',
    'admin_fee' => 'رسوم إدارية',
    'general_donation' => 'تبرع عام',
    'project_donation' => 'تبرع مشروع',
];

$pendingRows = dbFetchAll(
    "SELECT sp.id, sp.payment_type, sp.payment_period, sp.amount, sp.currency_code,
            sp.payment_method, sp.created_at, sp.notes, sp.purpose_note,
            sp.receipt_file_path, sp.unified_receipt_path,
            u.full_name AS supervisor_name,
            spn.full_name AS sponsor_name, spn.sponsor_code,
            fc.child_name
     FROM sponsor_payments sp
     JOIN users u ON u.id = sp.supervisor_id
     LEFT JOIN sponsorships spon ON spon.id = sp.sponsorship_id
     LEFT JOIN sponsors spn ON spn.id = COALESCE(sp.sponsor_id, spon.sponsor_id)
     LEFT JOIN family_children fc ON fc.id = spon.child_id
     WHERE sp.status = 'pending'
     ORDER BY sp.created_at ASC, sp.id ASC"
);

$historyRows = dbFetchAll(
    "SELECT sp.id, sp.payment_type, sp.payment_period, sp.amount, sp.currency_code,
            sp.payment_method, sp.created_at, sp.reviewed_at, sp.return_note,
            sp.receipt_file_path, sp.unified_receipt_path, sp.status,
            u.full_name AS supervisor_name, spn.full_name AS sponsor_name,
            rev.full_name AS reviewer_name
     FROM sponsor_payments sp
     JOIN users u ON u.id = sp.supervisor_id
     LEFT JOIN sponsors spn ON spn.id = sp.sponsor_id
     LEFT JOIN users rev ON rev.id = sp.reviewed_by_user_id
     WHERE sp.status IN ('approved', 'returned')
     ORDER BY sp.reviewed_at DESC, sp.id DESC
     LIMIT 20"
);

$mapPayment = static function (array $row) use ($purposeLabels): array {
    $type = (string)($row['payment_type'] ?? '');
    return [
        'id' => (int)$row['id'],
        'payment_type' => $type,
        'purpose_label' => $purposeLabels[$type] ?? $type,
        'payment_period' => (string)($row['payment_period'] ?? ''),
        'amount' => (float)$row['amount'],
        'currency_code' => (string)($row['currency_code'] ?? 'SDG'),
        'payment_method' => (string)($row['payment_method'] ?? 'cash'),
        'created_at' => (string)($row['created_at'] ?? ''),
        'reviewed_at' => (string)($row['reviewed_at'] ?? ''),
        'supervisor_name' => (string)($row['supervisor_name'] ?? ''),
        'sponsor_name' => (string)($row['sponsor_name'] ?? ''),
        'sponsor_code' => (string)($row['sponsor_code'] ?? ''),
        'child_name' => (string)($row['child_name'] ?? ''),
        'reviewer_name' => (string)($row['reviewer_name'] ?? ''),
        'return_note' => (string)($row['return_note'] ?? ''),
        'notes' => (string)($row['notes'] ?? ''),
        'purpose_note' => (string)($row['purpose_note'] ?? ''),
        'has_receipt' => (string)($row['receipt_file_path'] ?? '') !== '',
        'has_unified_receipt' => (string)($row['unified_receipt_path'] ?? '') !== '',
        'status' => (string)($row['status'] ?? 'pending'),
    ];
};

$pending = array_map($mapPayment, $pendingRows);
$history = array_map($mapPayment, $historyRows);

ak_api_json(200, ['ok' => true, 'data' => [
    'pending' => $pending,
    'pending_count' => count($pending),
    'history' => $history,
]]);
