<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if ((string)$user['role_code'] !== 'nanny') {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Group verification is limited to nanny accounts.']]);
}

$month = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^([0-9]{4})-([0-9]{2})$/', $month, $parts) || !checkdate((int)$parts[2], 1, (int)$parts[1])) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_month', 'message' => 'Month must use YYYY-MM format.']]);
}
$nannyId = (int)$user['id'];
$groups = dbFetchAll(
    "SELECT og.id, og.group_name, og.description, og.verification_status, og.submitted_at
     FROM orphan_groups og
     INNER JOIN nanny_group_assignments nga ON nga.group_id = og.id AND nga.nanny_id = ? AND nga.end_date IS NULL
     WHERE og.is_active = 1
     ORDER BY og.group_name",
    [$nannyId]
);
$groups = array_map(static function (array $group): array {
    $status = (string)$group['verification_status'];
    return [
        'id' => (int)$group['id'],
        'group_name' => (string)$group['group_name'],
        'description' => (string)($group['description'] ?? ''),
        'verification_status' => $status,
        'submitted_at' => (string)($group['submitted_at'] ?? ''),
        'can_bulk_verify' => $status === 'pending',
        'can_submit' => $status === 'pending',
    ];
}, $groups);

$groupId = (int)($_GET['group_id'] ?? 0);
$data = ['month' => $month, 'groups' => $groups, 'selected_group' => null, 'families' => [], 'counts' => [
    'verified_count' => 0, 'unverified_count' => 0, 'verified_amount' => 0.0, 'unverified_amount' => 0.0,
]];
if ($groupId > 0) {
    $selected = dbFetchOne(
        "SELECT og.id, og.group_name, og.description, og.verification_status, og.submitted_at
         FROM orphan_groups og
         INNER JOIN nanny_group_assignments nga ON nga.group_id = og.id AND nga.nanny_id = ? AND nga.end_date IS NULL
         WHERE og.id = ? AND og.is_active = 1 LIMIT 1",
        [$nannyId, $groupId]
    );
    if (!$selected) {
        ak_api_json(403, ['ok' => false, 'error' => ['code' => 'group_not_assigned', 'message' => 'This group is not currently assigned to your account.']]);
    }
    $data['selected_group'] = [
        'id' => (int)$selected['id'],
        'group_name' => (string)$selected['group_name'],
        'description' => (string)($selected['description'] ?? ''),
        'verification_status' => (string)$selected['verification_status'],
        'submitted_at' => (string)($selected['submitted_at'] ?? ''),
        'can_bulk_verify' => (string)$selected['verification_status'] === 'pending',
        'can_submit' => (string)$selected['verification_status'] === 'pending',
    ];
    $families = dbFetchAll(
        "SELECT DISTINCT f.id, f.family_code, f.mother_name,
                COALESCE((SELECT SUM(sp.monthly_amount)
                          FROM sponsorships sp
                          INNER JOIN family_children fc2 ON fc2.id = sp.child_id
                          WHERE fc2.family_id = f.id AND fc2.is_active = 1 AND sp.status = 'active'), 0) AS amount,
                COALESCE(v.is_orphan_verified, 0) AS is_orphan_verified,
                COALESCE(v.is_mother_contact_verified, 0) AS is_mother_contact_verified,
                COALESCE(v.is_bank_verified, 0) AS is_bank_verified,
                COALESCE(v.verification_notes, '') AS verification_notes,
                v.verified_at
         FROM group_children gc
         INNER JOIN family_children fc ON fc.id = gc.child_id
         INNER JOIN families f ON f.id = fc.family_id
         LEFT JOIN nanny_family_verifications v ON v.family_id = f.id AND v.month = ?
         WHERE gc.group_id = ? AND gc.left_date IS NULL
         ORDER BY f.family_code ASC",
        [$month, $groupId]
    );
    foreach ($families as $family) {
        $orphan = (int)$family['is_orphan_verified'] === 1;
        $mother = (int)$family['is_mother_contact_verified'] === 1;
        $bank = (int)$family['is_bank_verified'] === 1;
        $verified = $orphan && $mother && $bank;
        $amount = (float)$family['amount'];
        $data['families'][] = [
            'id' => (int)$family['id'],
            'family_code' => (string)$family['family_code'],
            'mother_name' => (string)$family['mother_name'],
            'amount' => $amount,
            'is_orphan_verified' => $orphan,
            'is_mother_contact_verified' => $mother,
            'is_bank_verified' => $bank,
            'verification_notes' => (string)$family['verification_notes'],
            'verified_at' => (string)($family['verified_at'] ?? ''),
            'is_fully_verified' => $verified,
        ];
        $key = $verified ? 'verified' : 'unverified';
        $data['counts'][$key . '_count']++;
        $data['counts'][$key . '_amount'] += $amount;
    }
}
ak_api_json(200, ['ok' => true, 'data' => $data]);
