<?php
// modules/families/sponsor_search.php - Authorized sponsor autocomplete endpoint
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/sponsor_assignments.php';

Session::start();
header('Content-Type: application/json; charset=UTF-8');

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$childId = (int)($_GET['child_id'] ?? 0);
$searchTerm = trim((string)($_GET['q'] ?? ''));
$searchTerm = preg_replace('/\s+/u', ' ', $searchTerm);

if ($childId <= 0 || $searchTerm === '') {
    echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$child = dbFetchOne("SELECT id FROM family_children WHERE id = ?", [$childId]);
if (!$child) {
    http_response_code(404);
    echo json_encode(['error' => 'child_not_found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$likeTerm = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchTerm) . '%';

$sql = "
    SELECT s.id, s.full_name, s.sponsor_code, s.gender, s.first_letter_id
    FROM sponsors s
    WHERE s.status = 'active'
      AND (s.full_name LIKE ? ESCAPE '\\' OR s.sponsor_code LIKE ? ESCAPE '\\')
      AND NOT EXISTS (
          SELECT 1
          FROM sponsorships sx
          WHERE sx.sponsor_id = s.id
            AND sx.child_id = ?
            AND sx.status IN ('active', 'paused')
      )
    ORDER BY s.full_name
    LIMIT 80
";

$searchSponsors = dbFetchAll($sql, [$likeTerm, $likeTerm, $childId]);

if ($role === 'supervisor') {
    $uid = Session::getUserId();
    $searchSponsors = array_values(array_filter(
        $searchSponsors,
        static fn(array $sponsor): bool => supervisorCanAccessSponsor($uid, $sponsor)
    ));
}

$results = array_map(static function (array $sponsor): array {
    return [
        'id' => (int)$sponsor['id'],
        'name' => (string)($sponsor['full_name'] ?? ''),
        'code' => (string)($sponsor['sponsor_code'] ?? '')
    ];
}, $searchSponsors);

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
