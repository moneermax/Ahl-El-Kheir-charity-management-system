<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if ((string)$user['role_code'] !== 'nanny') {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Preliminary verification is limited to nanny accounts.']]);
}
$month = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^([0-9]{4})-([0-9]{2})$/', $month, $parts) || !checkdate((int)$parts[2], 1, (int)$parts[1])) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_month', 'message' => 'Month must use YYYY-MM format.']]);
}

$families = dbFetchAll(
    "SELECT f.id, f.family_code, f.mother_name,
            GROUP_CONCAT(DISTINCT og.group_name ORDER BY og.group_name SEPARATOR '، ') AS group_names,
            COALESCE(v.is_orphan_verified, 0) AS is_orphan_verified,
            COALESCE(v.is_mother_contact_verified, 0) AS is_mother_contact_verified,
            COALESCE(v.is_bank_verified, 0) AS is_bank_verified,
            COALESCE(v.verification_notes, '') AS verification_notes,
            v.verified_at
     FROM families f
     INNER JOIN family_children fc ON fc.family_id = f.id AND fc.is_active = 1
     INNER JOIN group_children gc ON gc.child_id = fc.id AND gc.left_date IS NULL
     INNER JOIN orphan_groups og ON og.id = gc.group_id AND og.is_active = 1
     INNER JOIN nanny_group_assignments nga ON nga.group_id = og.id AND nga.nanny_id = ? AND nga.end_date IS NULL
     LEFT JOIN nanny_family_verifications v ON v.family_id = f.id AND v.month = ?
     GROUP BY f.id, f.family_code, f.mother_name, v.is_orphan_verified, v.is_mother_contact_verified, v.is_bank_verified, v.verification_notes, v.verified_at
     ORDER BY f.family_code ASC",
    [(int)$user['id'], $month]
);
$items = [];
$counts = ['total' => count($families), 'verified' => 0, 'partial' => 0, 'not_started' => 0];
foreach ($families as $family) {
    $orphan = (int)$family['is_orphan_verified'] === 1;
    $mother = (int)$family['is_mother_contact_verified'] === 1;
    $bank = (int)$family['is_bank_verified'] === 1;
    $checks = (int)$orphan + (int)$mother + (int)$bank;
    $status = $checks === 3 ? 'verified' : ($checks > 0 ? 'partial' : 'not_started');
    $counts[$status]++;
    $items[] = [
        'id' => (int)$family['id'],
        'family_code' => (string)$family['family_code'],
        'mother_name' => (string)$family['mother_name'],
        'group_names' => (string)($family['group_names'] ?? ''),
        'is_orphan_verified' => $orphan,
        'is_mother_contact_verified' => $mother,
        'is_bank_verified' => $bank,
        'verification_notes' => (string)$family['verification_notes'],
        'verified_at' => (string)($family['verified_at'] ?? ''),
        'verified_checks' => $checks,
        'verification_status' => $status,
    ];
}
ak_api_json(200, ['ok' => true, 'data' => ['month' => $month, 'items' => $items, 'counts' => $counts]]);
