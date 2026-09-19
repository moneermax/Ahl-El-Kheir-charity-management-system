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

/*
 * Multi-word autocomplete:
 * - Each typed word must match the beginning of the corresponding sponsor-name word.
 * - This makes "أمل ب" narrow to names beginning with "أمل" then a second word beginning with "ب".
 * - A complete multi-word query therefore narrows progressively instead of using one broad
 *   contains match against the whole query.
 * - Sponsor codes keep contains matching so partial codes remain easy to find.
 *
 * Arabic normalization is applied in PHP after the database has returned the authorized
 * candidate set. Authorization and duplicate-sponsorship exclusion remain enforced in SQL
 * before candidates reach this matcher.
 */
$normalizeSearchValue = static function (string $value): string {
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = mb_strtolower($value, 'UTF-8');
    $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value);
    $value = str_replace(['إ', 'أ', 'آ', 'ٱ'], 'ا', $value);
    $value = str_replace(['ى'], 'ي', $value);
    return trim($value);
};

$query = $normalizeSearchValue($searchTerm);
$queryTokens = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);

$where = [
    "s.status = 'active'",
    "NOT EXISTS (
        SELECT 1
        FROM sponsorships sx
        WHERE sx.sponsor_id = s.id
          AND sx.child_id = ?
          AND sx.status IN ('active', 'paused')
    )"
];
$params = [$childId];

if ($role === 'supervisor') {
    $uid = Session::getUserId();
    $scopeRows = dbFetchAll(
        "SELECT letter_id, gender FROM supervisor_letters WHERE supervisor_id = ?",
        [$uid]
    );

    $scopeParts = [];
    foreach ($scopeRows as $scopeRow) {
        $letterId = (int)($scopeRow['letter_id'] ?? 0);
        $scopeGender = strtolower(trim((string)($scopeRow['gender'] ?? '')));
        if ($letterId <= 0) continue;

        if (in_array($scopeGender, ['both', 'all', 'كلاهما', 'الكل'], true)) {
            $scopeParts[] = "(s.first_letter_id = ? AND LOWER(TRIM(s.gender)) IN ('male','female','m','f','ذكر','أنثى','انثى'))";
            $params[] = $letterId;
        } elseif (in_array($scopeGender, ['male', 'm', 'ذكر'], true)) {
            $scopeParts[] = "(s.first_letter_id = ? AND LOWER(TRIM(s.gender)) IN ('male','m','ذكر'))";
            $params[] = $letterId;
        } elseif (in_array($scopeGender, ['female', 'f', 'أنثى', 'انثى'], true)) {
            $scopeParts[] = "(s.first_letter_id = ? AND LOWER(TRIM(s.gender)) IN ('female','f','أنثى','انثى'))";
            $params[] = $letterId;
        }
    }

    if (!$scopeParts) {
        echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    $where[] = '(' . implode(' OR ', $scopeParts) . ')';
}

$sql = "SELECT s.id, s.full_name, s.sponsor_code, s.gender, s.first_letter_id
        FROM sponsors s
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.full_name
        LIMIT 500";

$candidateSponsors = dbFetchAll($sql, $params);

if ($role === 'supervisor') {
    $uid = Session::getUserId();
    $candidateSponsors = array_values(array_filter(
        $candidateSponsors,
        static fn(array $sponsor): bool => supervisorCanAccessSponsor($uid, $sponsor)
    ));
}

$matches = [];

foreach ($candidateSponsors as $sponsor) {
    $name = $normalizeSearchValue((string)($sponsor['full_name'] ?? ''));
    $code = $normalizeSearchValue((string)($sponsor['sponsor_code'] ?? ''));

    $nameWords = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);

    $nameMatches = true;
    foreach ($queryTokens as $index => $token) {
        if (!isset($nameWords[$index]) || mb_strpos($nameWords[$index], $token, 0, 'UTF-8') !== 0) {
            $nameMatches = false;
            break;
        }
    }

    $codeMatches = $query !== '' && mb_strpos($code, $query, 0, 'UTF-8') !== false;

    if ($nameMatches || $codeMatches) {
        $matches[] = $sponsor;
    }

    if (count($matches) >= 80) {
        break;
    }
}

$results = array_map(static function (array $sponsor): array {
    return [
        'id' => (int)$sponsor['id'],
        'name' => (string)($sponsor['full_name'] ?? ''),
        'code' => (string)($sponsor['sponsor_code'] ?? '')
    ];
}, $matches);

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
