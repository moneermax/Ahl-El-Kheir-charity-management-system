<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/config/family_scope.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)($user['raw_role_code'] ?? '');
$viewRoles = ['admin','vice_general_manager','general_manager','supervisor','nanny','accountant','accountant_staff','administration','staff','social_media'];
if (!in_array($role, $viewRoles, true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Orphan form list access is not permitted for this role.']]);
}
$userId = (int)$user['id'];
$search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60, 'UTF-8');
$scopeFamilyIds = null;
if ($role === 'nanny') {
    $scopeFamilyIds = array_map('intval', array_column(dbFetchAll('SELECT id FROM families WHERE nanny_id=?', [$userId]), 'id'));
} elseif ($role === 'supervisor') {
    $scopeFamilyIds = array_map('intval', array_column(dbFetchAll(
        "SELECT DISTINCT f.id
         FROM families f
         LEFT JOIN family_children fc ON fc.family_id=f.id
         LEFT JOIN sponsorships s ON s.child_id=fc.id
         LEFT JOIN sponsors sp ON sp.id=s.sponsor_id
         LEFT JOIN supervisor_letters sl ON sl.supervisor_id=? AND sl.letter_id=sp.first_letter_id
         WHERE f.supervisor_id=? OR (
           sl.id IS NOT NULL AND (
             CONVERT(sl.gender USING utf8mb4) COLLATE utf8mb4_unicode_ci=CONVERT(sp.gender USING utf8mb4) COLLATE utf8mb4_unicode_ci
             OR CONVERT(sl.gender USING utf8mb4) COLLATE utf8mb4_unicode_ci='both'
           )
         )",
        [$userId, $userId]
    ), 'id'));
}
$searchSql = '';
$params = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $searchSql = ' AND (fc.child_name LIKE ? OR f.mother_name LIKE ? OR f.family_code LIKE ? OR fc.form_serial LIKE ?)';
    $params = [$like, $like, $like, $like];
}
if ($scopeFamilyIds !== null && !$scopeFamilyIds) {
    $rows = [];
} else {
    $scopeSql = '';
    if ($scopeFamilyIds !== null) {
        $placeholders = implode(',', array_fill(0, count($scopeFamilyIds), '?'));
        $scopeSql = " AND f.id IN ($placeholders)";
        $params = array_merge($scopeFamilyIds, $params);
    }
    $rows = dbFetchAll(
        "SELECT fc.id, fc.child_name, fc.gender, fc.form_serial, f.family_code, f.mother_name, f.status AS family_status
         FROM family_children fc
         JOIN families f ON f.id=fc.family_id
         WHERE 1=1 $scopeSql $searchSql
         ORDER BY fc.id DESC LIMIT 200",
        $params
    );
}
$canEdit = in_array($role, ['admin','vice_general_manager','supervisor','nanny'], true);
$canSeeFinancial = in_array($role, ['admin','vice_general_manager','general_manager','supervisor','nanny'], true);
ak_api_json(200, ['ok' => true, 'data' => [
    'children' => $rows,
    'count' => count($rows),
    'search' => $search,
    'can_edit' => $canEdit,
    'can_see_financial' => $canSeeFinancial,
    'read_only' => !$canEdit,
    'limit' => 200,
]]);
