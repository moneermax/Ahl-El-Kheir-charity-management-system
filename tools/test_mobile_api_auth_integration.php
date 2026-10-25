<?php
declare(strict_types=1);

/**
 * Destructive operations are limited to a dedicated, disposable CI database.
 * The selected existing user is restored and all sessions created by this test
 * are deleted before exit. Never run against a development/production database.
 */
$database = (string)(getenv('AHL_DB_NAME') ?: '');
if (getenv('MOBILE_API_AUTH_TEST') !== '1' || $database !== 'mobile_api_auth_test') {
    fwrite(STDERR, "Refusing to run outside mobile_api_auth_test with MOBILE_API_AUTH_TEST=1.\n");
    exit(2);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('AHL_DB_HOST') ?: '127.0.0.1', getenv('AHL_DB_PORT') ?: '3306', $database),
    (string)(getenv('AHL_DB_USER') ?: 'root'),
    (string)(getenv('AHL_DB_PASS') ?: 'root'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== 'mobile_api_auth_test') {
    throw new RuntimeException('Connected to an unexpected database.');
}

function mobileApiExpect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    echo "PASS: {$message}\n";
}
function mobileApiRequest(string $path, string $method = 'GET', ?array $body = null, ?string $token = null, int $timeoutSeconds = 5): array {
    $headers = ['Accept: application/json'];
    $content = '';
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $content = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $content,
        'ignore_errors' => true,
        'timeout' => max(1, $timeoutSeconds),
    ]]);
    $raw = file_get_contents('http://127.0.0.1:18080' . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $m)) $status = (int)$m[1];
    }
    $json = is_string($raw) ? json_decode($raw, true) : null;
    return ['status' => $status, 'json' => is_array($json) ? $json : [], 'raw' => (string)$raw];
}

$roles = [];
foreach ($pdo->query("SELECT id, code FROM roles WHERE code IN ('admin','nanny','hr_staff','project_supervisor','financial_manager','supervisor')") as $role) $roles[(string)$role['code']] = (int)$role['id'];
if (!isset($roles['admin'], $roles['nanny'], $roles['hr_staff'], $roles['project_supervisor'], $roles['financial_manager'], $roles['supervisor'])) {
    throw new RuntimeException('Checked-in schema must contain admin, nanny, hr_staff, project_supervisor and financial_manager roles.');
}
$user = $pdo->query('SELECT id, username, password_hash, is_active, password_change_required, role_id, last_login_at FROM users ORDER BY id LIMIT 1')->fetch();
if (!$user || trim((string)$user['username']) === '') throw new RuntimeException('Checked-in schema has no usable user fixture.');

$userId = (int)$user['id'];
$username = (string)$user['username'];
$knownPassword = 'MobileApi-CI-Only-2026!';
$testStart = date('Y-m-d H:i:s');
$testTokenHashes = [];
$server = null;
$serverPipes = [];
$failure = null;
$testProjectId = 0;
$testPaymentId = 0;
$testMilestoneId = 0;
$testDraftMilestoneId = 0;
$testForeignProjectId = 0;
$testForeignMilestoneId = 0;
$testNotificationId = 0;
$testSponsorRequestId = 0;
$testNannyFamilyId = 0;
$testForeignNannyFamilyId = 0;
$testLeaveId = 0;
$testLeaveEmployeeId = 0;
$testLeaveAttendanceDate = '';
$testSponsorId = 0;
$testProjectCode = 'MOBILE-API-CI-' . bin2hex(random_bytes(6));

try {
    $pdo->prepare('INSERT INTO other_projects (name, project_code, status, created_by) VALUES (?, ?, \'planned\', ?)')
        ->execute(['Mobile API approval fallback integration fixture', $testProjectCode, $userId]);
    $testProjectId = (int)$pdo->lastInsertId();

    $pdo->prepare('UPDATE users SET password_hash=?, is_active=1, password_change_required=0, role_id=? WHERE id=?')
        ->execute([password_hash($knownPassword, PASSWORD_DEFAULT), $roles['nanny'], $userId]);

    $server = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:18080', '-t', dirname(__DIR__)],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $serverPipes,
        dirname(__DIR__),
        array_merge($_ENV, [
            'AHL_ENV' => 'development',
            'AHL_DB_HOST' => getenv('AHL_DB_HOST') ?: '127.0.0.1',
            'AHL_DB_PORT' => getenv('AHL_DB_PORT') ?: '3306',
            'AHL_DB_NAME' => 'mobile_api_auth_test',
            'AHL_DB_USER' => getenv('AHL_DB_USER') ?: 'root',
            'AHL_DB_PASS' => getenv('AHL_DB_PASS') ?: 'root',
        ])
    );
    if (!is_resource($server)) throw new RuntimeException('Could not start the PHP test server.');
    stream_set_blocking($serverPipes[1], false);
    stream_set_blocking($serverPipes[2], false);

    $ready = false;
    for ($i = 0; $i < 40; $i++) {
        usleep(250000);
        $probe = mobileApiRequest('/api/v1/auth/me.php');
        if ($probe['status'] === 401) { $ready = true; break; }
    }
    if (!$ready) throw new RuntimeException('PHP test server did not become ready.');

    $r = mobileApiRequest('/api/v1/auth/me.php');
    mobileApiExpect($r['status'] === 401 && ($r['json']['error']['code'] ?? '') === 'unauthenticated', 'missing bearer token is rejected');
    $r = mobileApiRequest('/api/v1/auth/login.php', 'GET');
    mobileApiExpect($r['status'] === 405, 'login rejects the wrong HTTP method');
    $r = mobileApiRequest('/api/v1/auth/login.php', 'POST', ['username' => $username, 'password' => 'wrong-password']);
    mobileApiExpect($r['status'] === 401, 'invalid credentials are rejected');

    $r = mobileApiRequest('/api/v1/auth/login.php', 'POST', ['username' => $username, 'password' => $knownPassword]);
    mobileApiExpect($r['status'] === 200 && isset($r['json']['data']['access_token']), 'valid credentials issue a bearer token');
    $token = (string)$r['json']['data']['access_token'];
    mobileApiExpect((bool)preg_match('/^[a-f0-9]{64}$/', $token), 'access token has expected opaque-token format');
    $tokenHash = hash('sha256', $token);
    $testTokenHashes[] = $tokenHash;
    $stored = $pdo->prepare('SELECT token_hash FROM mobile_api_sessions WHERE token_hash=?');
    $stored->execute([$tokenHash]);
    mobileApiExpect((string)$stored->fetchColumn() === $tokenHash && $tokenHash !== $token, 'only the SHA-256 token hash is stored');


    $otherUser = $pdo->prepare('SELECT id FROM users WHERE id <> ? ORDER BY id LIMIT 1');
    $otherUser->execute([$userId]);
    $notificationRecipientId = (int)$otherUser->fetchColumn();
    if ($notificationRecipientId < 1) throw new RuntimeException('Notification isolation test requires a second user in the disposable schema.');
    $foreignNotificationTitle = 'Mobile API cross-account fixture ' . bin2hex(random_bytes(6));
    $pdo->prepare('INSERT INTO notifications (recipient_user_id, type, title, body, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())')
        ->execute([$notificationRecipientId, 'info', $foreignNotificationTitle, 'Disposable authorization test', null]);
    $testNotificationId = (int)$pdo->lastInsertId();

    $ownFamilyCode = 'MOBILE-NANNY-CI-' . bin2hex(random_bytes(5));
    $foreignFamilyCode = 'MOBILE-NANNY-FOREIGN-CI-' . bin2hex(random_bytes(5));
    $insertFamily = $pdo->prepare('INSERT INTO families (mother_name, family_code, status, created_by, nanny_id) VALUES (?, ?, \'pending\', ?, ?)');
    $insertFamily->execute(['Mobile API assigned nanny family fixture', $ownFamilyCode, $userId, $userId]);
    $testNannyFamilyId = (int)$pdo->lastInsertId();
    $insertFamily->execute(['Mobile API foreign nanny family fixture', $foreignFamilyCode, $userId, $notificationRecipientId]);
    $testForeignNannyFamilyId = (int)$pdo->lastInsertId();

    $r = mobileApiRequest('/api/v1/notifications/index.php', 'GET', null, $token);
    $listedNotificationIds = array_map(
        static fn(array $item): int => (int)($item['id'] ?? 0),
        $r['json']['data']['items'] ?? []
    );
    mobileApiExpect(
        $r['status'] === 200
        && ($r['json']['ok'] ?? false) === true
        && !in_array($testNotificationId, $listedNotificationIds, true),
        'notification listing excludes another account\'s notification'
    );

    $r = mobileApiRequest('/api/v1/notifications/mark_read.php', 'POST', ['notification_id' => $testNotificationId], $token);
    $foreignNotificationReadState = $pdo->prepare('SELECT is_read FROM notifications WHERE id=? AND recipient_user_id=?');
    $foreignNotificationReadState->execute([$testNotificationId, $notificationRecipientId]);
    mobileApiExpect(
        $r['status'] === 200
        && ($r['json']['ok'] ?? false) === true
        && (int)$foreignNotificationReadState->fetchColumn() === 0,
        'mark-read cannot change another account\'s notification'
    );

    $r = mobileApiRequest('/api/v1/families/detail.php?id=' . $testNannyFamilyId, 'GET', null, $token);
    mobileApiExpect(
        $r['status'] === 200
        && ($r['json']['ok'] ?? false) === true
        && (int)($r['json']['data']['family']['id'] ?? 0) === $testNannyFamilyId,
        'nanny can view an assigned family'
    );
    $r = mobileApiRequest('/api/v1/families/detail.php?id=' . $testForeignNannyFamilyId, 'GET', null, $token);
    mobileApiExpect(
        $r['status'] === 403
        && ($r['json']['error']['code'] ?? '') === 'forbidden',
        'nanny cannot view a family assigned to another account'
    );
    $r = mobileApiRequest('/api/v1/families/index.php', 'GET', null, $token, 20);
    $listedFamilyIds = array_map(
        static fn(array $item): int => (int)($item['id'] ?? 0),
        $r['json']['data']['items'] ?? []
    );
    mobileApiExpect(
        $r['status'] === 200
        && in_array($testNannyFamilyId, $listedFamilyIds, true)
        && !in_array($testForeignNannyFamilyId, $listedFamilyIds, true),
        'nanny family listing includes assigned fixture and excludes foreign fixture'
    );

    $sponsorRequestName = 'Mobile API CI sponsor request ' . bin2hex(random_bytes(6));
    $pdo->prepare('INSERT INTO sponsor_requests (sponsor_name, phone, gender, source, brought_by, brought_by_name, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, \'new\')')
        ->execute([$sponsorRequestName, null, 'female', 'integration_test', (string)$userId, 'Disposable CI fixture', 'Authorization integration fixture']);
    $testSponsorRequestId = (int)$pdo->lastInsertId();

    $r = mobileApiRequest('/api/v1/sponsor_requests/index.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 403, 'nanny cannot list sponsor requests');
    $r = mobileApiRequest('/api/v1/sponsor_requests/action.php', 'POST', [
        'request_id' => $testSponsorRequestId,
        'action' => 'mark_contacted',
    ], $token);
    $sponsorRequestStatus = $pdo->prepare('SELECT status FROM sponsor_requests WHERE id=?');
    $sponsorRequestStatus->execute([$testSponsorRequestId]);
    mobileApiExpect(
        $r['status'] === 403 && (string)$sponsorRequestStatus->fetchColumn() === 'new',
        'nanny cannot change sponsor request status'
    );

    $r = mobileApiRequest('/api/v1/auth/me.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 200 && ($r['json']['data']['user']['role_code'] ?? '') === 'nanny', '/me accepts a valid token and returns the live role');
    $r = mobileApiRequest('/api/v1/hr/employees.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 403, 'nanny is denied access to the HR employee directory');
    $r = mobileApiRequest('/api/v1/hr/leaves.php?view=queue', 'GET', null, $token);
    mobileApiExpect($r['status'] === 403, 'nanny is denied access to the HR leave approval queue');
    $r = mobileApiRequest('/api/v1/projects/milestone_create.php', 'POST', ['project_id' => 1, 'title' => 'Unauthorized test milestone'], $token);
    mobileApiExpect($r['status'] === 403, 'nanny cannot create project milestones');

    $r = mobileApiRequest('/api/v1/hr/attendance.php?date=2026-10-01', 'GET', null, $token);
    mobileApiExpect($r['status'] === 403, 'nanny is denied access to the HR attendance register');
    $r = mobileApiRequest('/api/v1/hr/employee_detail.php?id=1', 'GET', null, $token);
    mobileApiExpect($r['status'] === 403, 'nanny is denied access to individual HR employee details');
    $r = mobileApiRequest('/api/v1/sponsorships/index.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 403, 'nanny is denied access to the sponsorship directory');
    $r = mobileApiRequest('/api/v1/sponsorships/detail.php?id=1', 'GET', null, $token);
    mobileApiExpect($r['status'] === 403, 'nanny is denied access to sponsorship details');
    $r = mobileApiRequest('/api/v1/transactions/index.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 403, 'nanny is denied access to the transaction register');
    $r = mobileApiRequest('/api/v1/supervisors/index.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 403, 'nanny is denied access to supervisor management');
    $r = mobileApiRequest('/api/v1/accounting/fm_review_queue.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 403, 'nanny is denied access to the financial review queue');

    $leaveEmployee = $pdo->prepare(
        "SELECT e.id
         FROM employees e
         JOIN users u ON u.id = e.user_id
         JOIN roles r ON r.id = u.role_id
         WHERE u.id <> ?
           AND r.code NOT IN ('hr_manager', 'hr_staff')
         ORDER BY e.id
         LIMIT 1"
    );
    $leaveEmployee->execute([$userId]);
    $testLeaveEmployeeId = (int)$leaveEmployee->fetchColumn();
    if ($testLeaveEmployeeId < 1) {
        throw new RuntimeException('Leave-action integration test requires an employee linked to a non-HR user.');
    }
    $candidateDate = new DateTimeImmutable('2099-12-31');
    for ($attempt = 0; $attempt < 365; $attempt++) {
        $candidate = $candidateDate->modify('+' . $attempt . ' days')->format('Y-m-d');
        $existingAttendance = $pdo->prepare('SELECT 1 FROM attendance WHERE employee_id=? AND date=? LIMIT 1');
        $existingAttendance->execute([$testLeaveEmployeeId, $candidate]);
        if (!$existingAttendance->fetchColumn()) {
            $testLeaveAttendanceDate = $candidate;
            break;
        }
    }
    if ($testLeaveAttendanceDate === '') {
        throw new RuntimeException('Could not allocate a unique disposable attendance date for the leave-action test.');
    }
    $pdo->prepare(
        "INSERT INTO leaves (employee_id, leave_type, start_date, end_date, days_count, reason, status)
         VALUES (?, 'annual', ?, ?, 1, 'Disposable mobile API integration fixture', 'pending')"
    )->execute([$testLeaveEmployeeId, $testLeaveAttendanceDate, $testLeaveAttendanceDate]);
    $testLeaveId = (int)$pdo->lastInsertId();

    $r = mobileApiRequest('/api/v1/hr/leaves/action.php', 'POST', [
        'action' => 'approve',
        'leave_id' => $testLeaveId,
    ], $token);
    $leaveState = $pdo->prepare('SELECT status FROM leaves WHERE id=?');
    $leaveState->execute([$testLeaveId]);
    mobileApiExpect(
        $r['status'] === 403 && (string)$leaveState->fetchColumn() === 'pending',
        'nanny cannot approve a leave request and the pending state remains unchanged'
    );

    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['hr_staff'], $userId]);
    $r = mobileApiRequest('/api/v1/auth/me.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 200 && ($r['json']['data']['user']['role_code'] ?? '') === 'hr_staff', 'role changes are reflected on the next authenticated request');
    $r = mobileApiRequest('/api/v1/hr/employees.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 200 && ($r['json']['ok'] ?? false) === true, 'HR staff can access the HR employee directory');

    $r = mobileApiRequest('/api/v1/nanny/group_verification.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 403, 'HR staff cannot access nanny group verification');
    $r = mobileApiRequest('/api/v1/nanny/pre_verification.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 403, 'HR staff cannot access nanny preliminary verification');
    $r = mobileApiRequest('/api/v1/nanny/group_verification_action.php', 'POST', ['action' => 'bulk_verify'], $token);
    mobileApiExpect($r['status'] === 403, 'HR staff cannot submit nanny group verification actions');
    $r = mobileApiRequest('/api/v1/nanny/pre_verification_action.php', 'POST', ['action' => 'bulk_verify'], $token);
    mobileApiExpect($r['status'] === 403, 'HR staff cannot submit nanny preliminary verification actions');

    $r = mobileApiRequest('/api/v1/hr/leaves/action.php', 'POST', [
        'action' => 'approve',
        'leave_id' => $testLeaveId,
    ], $token);
    $approvedLeave = $pdo->prepare('SELECT status, hr_approved_by FROM leaves WHERE id=?');
    $approvedLeave->execute([$testLeaveId]);
    $approvedLeaveRow = $approvedLeave->fetch(PDO::FETCH_ASSOC);
    $leaveAttendance = $pdo->prepare("SELECT status, notes FROM attendance WHERE employee_id=? AND date=?");
    $leaveAttendance->execute([$testLeaveEmployeeId, $testLeaveAttendanceDate]);
    $leaveAttendanceRow = $leaveAttendance->fetch(PDO::FETCH_ASSOC);
    mobileApiExpect(
        $r['status'] === 200
        && ($r['json']['data']['status'] ?? '') === 'hr_approved'
        && (string)($approvedLeaveRow['status'] ?? '') === 'hr_approved'
        && (int)($approvedLeaveRow['hr_approved_by'] ?? 0) === $userId
        && (string)($leaveAttendanceRow['status'] ?? '') === 'on_leave'
        && (string)($leaveAttendanceRow['notes'] ?? '') === 'إجازة معتمدة',
        'HR approval commits leave state and its attendance row together'
    );
    $r = mobileApiRequest('/api/v1/hr/leaves/action.php', 'POST', [
        'action' => 'approve',
        'leave_id' => $testLeaveId,
    ], $token);
    $approvedLeave->execute([$testLeaveId]);
    mobileApiExpect(
        $r['status'] === 409
        && (string)($approvedLeave->fetch(PDO::FETCH_ASSOC)['status'] ?? '') === 'hr_approved',
        'repeated leave approval is rejected without changing the approved state'
    );

    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['admin'], $userId]);

    $r = mobileApiRequest('/api/v1/sponsor_requests/action.php', 'POST', [
        'request_id' => $testSponsorRequestId,
        'action' => 'mark_contacted',
    ], $token);
    $sponsorRequestStatus->execute([$testSponsorRequestId]);
    mobileApiExpect(
        $r['status'] === 200
        && ($r['json']['data']['status'] ?? '') === 'contacted'
        && (string)$sponsorRequestStatus->fetchColumn() === 'contacted',
        'authorized admin can make the new-to-contacted sponsor request transition'
    );
    $r = mobileApiRequest('/api/v1/sponsor_requests/action.php', 'POST', [
        'request_id' => $testSponsorRequestId,
        'action' => 'mark_contacted',
    ], $token);
    $sponsorRequestStatus->execute([$testSponsorRequestId]);
    mobileApiExpect(
        $r['status'] === 409
        && ($r['json']['error']['code'] ?? '') === 'invalid_transition'
        && (string)$sponsorRequestStatus->fetchColumn() === 'contacted',
        'sponsor request rejects a repeated contacted transition without mutation'
    );

    $unassignedLetter = $pdo->prepare('SELECT l.id FROM letters l WHERE l.is_active=1 AND NOT EXISTS (SELECT 1 FROM supervisor_letters sl WHERE sl.supervisor_id=? AND sl.letter_id=l.id) ORDER BY l.id LIMIT 1');
    $unassignedLetter->execute([$userId]);
    $unassignedLetterId = (int)$unassignedLetter->fetchColumn();
    if ($unassignedLetterId < 1) throw new RuntimeException('Supervisor-scope test requires an active letter outside the test user\'s responsibility matrix.');
    $testSponsorCode = 'MOBILE-SPONSOR-CI-' . bin2hex(random_bytes(5));
    $testSponsorName = 'Mobile API out-of-scope sponsor ' . bin2hex(random_bytes(5));
    $pdo->prepare('INSERT INTO sponsors (full_name, first_letter_id, gender, status, supervisor_id, sponsor_code, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$testSponsorName, $unassignedLetterId, 'female', 'active', $userId, $testSponsorCode, $userId]);
    $testSponsorId = (int)$pdo->lastInsertId();

    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['supervisor'], $userId]);
    $r = mobileApiRequest('/api/v1/sponsors/detail.php?id=' . $testSponsorId, 'GET', null, $token);
    mobileApiExpect(
        $r['status'] === 403
        && ($r['json']['error']['code'] ?? '') === 'forbidden',
        'direct sponsor supervisor_id does not bypass the letter-and-gender responsibility matrix'
    );
    $r = mobileApiRequest('/api/v1/sponsors/index.php', 'GET', null, $token);
    $listedSponsorIds = array_map(
        static fn(array $item): int => (int)($item['id'] ?? 0),
        $r['json']['data']['items'] ?? []
    );
    mobileApiExpect(
        $r['status'] === 200
        && !in_array($testSponsorId, $listedSponsorIds, true),
        'supervisor sponsor listing excludes a sponsor outside the letter-and-gender matrix'
    );
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['admin'], $userId]);
    $r = mobileApiRequest('/api/v1/projects/index.php', 'GET', null, $token);
    $portfolioItems = $r['json']['data']['items'] ?? [];
    $fixture = null;
    foreach ($portfolioItems as $portfolioItem) {
        if (($portfolioItem['project_code'] ?? '') === $testProjectCode) {
            $fixture = $portfolioItem;
            break;
        }
    }
    mobileApiExpect(
        $r['status'] === 200
        && ($r['json']['ok'] ?? false) === true
        && is_array($fixture)
        && ($fixture['approval_status'] ?? '') === 'draft',
        'project portfolio defaults a missing approval row to draft'
    );

    $pdo->prepare('INSERT INTO project_milestones (project_id, title, description, planned_date, status, completion_percent, notes, created_by) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$testProjectId, 'Draft milestone must remain unchanged', 'Integration test only', date('Y-m-d'), 'in_progress', 10, null, $userId]);
    $testDraftMilestoneId = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['project_supervisor'], $userId]);
    $r = mobileApiRequest('/api/v1/projects/milestone_action.php', 'POST', [
        'action' => 'edit',
        'project_id' => $testProjectId,
        'milestone_id' => $testDraftMilestoneId,
        'title' => 'Unauthorized draft milestone edit',
        'completion_percent' => 80,
    ], $token);
    $draftMilestoneTitle = $pdo->prepare('SELECT title FROM project_milestones WHERE id=? AND project_id=?');
    $draftMilestoneTitle->execute([$testDraftMilestoneId, $testProjectId]);
    mobileApiExpect(
        $r['status'] === 409
        && ($r['json']['error']['code'] ?? '') === 'project_not_operational'
        && (string)$draftMilestoneTitle->fetchColumn() === 'Draft milestone must remain unchanged',
        'project supervisor cannot edit milestones before project approval and operational launch'
    );
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['admin'], $userId]);

    $pdo->prepare("INSERT INTO project_approval (project_id, approval_status) VALUES (?, 'approved')")->execute([$testProjectId]);
    $pdo->prepare("INSERT INTO project_lifecycle (project_id, lifecycle_status) VALUES (?, 'active')")->execute([$testProjectId]);
    $pdo->prepare('INSERT INTO project_supervisor_assignments (project_id, supervisor_user_id, assigned_by) VALUES (?, ?, ?)')->execute([$testProjectId, $userId, $userId]);
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['project_supervisor'], $userId]);

    $pdo->prepare('INSERT INTO project_milestones (project_id, title, description, planned_date, status, completion_percent, notes, created_by) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$testProjectId, 'Mobile API milestone ID fixture', 'Integration test only', date('Y-m-d'), 'in_progress', 10, null, $userId]);
    $testMilestoneId = (int)$pdo->lastInsertId();

    $foreignProjectCode = 'MOBILE-API-CI-FOREIGN-' . bin2hex(random_bytes(6));
    $pdo->prepare("INSERT INTO other_projects (name, project_code, status, created_by) VALUES (?, ?, 'planned', ?)")
        ->execute(['Mobile API cross-project isolation fixture', $foreignProjectCode, $userId]);
    $testForeignProjectId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO project_milestones (project_id, title, description, planned_date, status, completion_percent, notes, created_by) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$testForeignProjectId, 'Foreign project milestone must remain unchanged', 'Integration test only', date('Y-m-d'), 'in_progress', 10, null, $userId]);
    $testForeignMilestoneId = (int)$pdo->lastInsertId();

    $r = mobileApiRequest('/api/v1/projects/milestone_action.php', 'POST', [
        'action' => 'edit',
        'project_id' => $testProjectId,
        'milestone_id' => $testForeignMilestoneId,
        'title' => 'Cross-project mutation must fail',
        'completion_percent' => 80,
    ], $token);
    $foreignTitle = $pdo->prepare('SELECT title FROM project_milestones WHERE id=? AND project_id=?');
    $foreignTitle->execute([$testForeignMilestoneId, $testForeignProjectId]);
    mobileApiExpect(
        $r['status'] === 404
        && ($r['json']['error']['code'] ?? '') === 'milestone_not_found'
        && (string)$foreignTitle->fetchColumn() === 'Foreign project milestone must remain unchanged',
        'cross-project milestone isolation'
    );
    $r = mobileApiRequest('/api/v1/projects/detail.php?id=' . $testProjectId, 'GET', null, $token);
    $detailMilestone = null;
    foreach (($r['json']['data']['milestones'] ?? []) as $item) {
        if ((int)($item['id'] ?? 0) === $testMilestoneId) {
            $detailMilestone = $item;
            break;
        }
    }
    mobileApiExpect(
        $r['status'] === 200
        && ($r['json']['ok'] ?? false) === true
        && is_array($detailMilestone),
        'project detail returns each milestone ID needed by mobile edit/delete actions'
    );

    $progressBody = [
        'project_id' => $testProjectId,
        'summary' => 'Mobile API integration test progress update',
        'completion_percent' => 10,
        'update_date' => date('Y-m-d'),
    ];
    $r = mobileApiRequest('/api/v1/projects/progress_update.php', 'POST', $progressBody, $token);
    mobileApiExpect($r['status'] === 200 && ($r['json']['ok'] ?? false) === true, 'assigned project supervisor can add progress to an approved active project');

    $pdo->prepare('UPDATE project_supervisor_assignments SET ended_at=NOW() WHERE project_id=? AND supervisor_user_id=? AND ended_at IS NULL')
        ->execute([$testProjectId, $userId]);
    $r = mobileApiRequest('/api/v1/projects/progress_update.php', 'POST', array_merge($progressBody, ['summary' => 'Must be denied after assignment ends']), $token);
    mobileApiExpect($r['status'] === 403 && ($r['json']['error']['code'] ?? '') === 'forbidden', 'project supervisor cannot add progress after the assignment ends');
    $r = mobileApiRequest('/api/v1/projects/milestone_action.php', 'POST', [
        'action' => 'edit',
        'project_id' => $testProjectId,
        'milestone_id' => $testMilestoneId,
        'title' => 'Must not be saved after assignment ends',
        'completion_percent' => 25,
    ], $token);
    $milestoneTitle = $pdo->prepare('SELECT title FROM project_milestones WHERE id=? AND project_id=?');
    $milestoneTitle->execute([$testMilestoneId, $testProjectId]);
    mobileApiExpect(
        $r['status'] === 403
        && ($r['json']['error']['code'] ?? '') === 'assignment_required'
        && (string)$milestoneTitle->fetchColumn() === 'Mobile API milestone ID fixture',
        'project supervisor cannot edit a milestone after the project assignment ends'
    );

    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['hr_staff'], $userId]);

    $r = mobileApiRequest('/api/v1/accounting/fm_review_queue_action.php', 'POST', [
        'payment_id' => 1,
        'action' => 'return',
        'return_note' => 'Authorization boundary test',
    ], $token);
    mobileApiExpect($r['status'] === 403, 'HR staff cannot perform financial review actions');

    $pdo->prepare("INSERT INTO sponsor_payments (payment_type, supervisor_id, payment_period, amount, currency_code, status) VALUES ('other', ?, 'MOBILE-CI-TEST', 1.00, 'SDG', 'pending')")
        ->execute([$userId]);
    $testPaymentId = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['financial_manager'], $userId]);

    $r = mobileApiRequest('/api/v1/accounting/fm_review_queue_action.php', 'POST', [
        'payment_id' => $testPaymentId,
        'action' => 'return',
    ], $token);
    $pendingStatus = $pdo->prepare('SELECT status FROM sponsor_payments WHERE id=?');
    $pendingStatus->execute([$testPaymentId]);
    mobileApiExpect($r['status'] === 422 && (string)$pendingStatus->fetchColumn() === 'pending', 'financial return requires a reason and leaves the payment pending');

    $returnNote = 'Mobile API CI return test ' . bin2hex(random_bytes(4));
    $r = mobileApiRequest('/api/v1/accounting/fm_review_queue_action.php', 'POST', [
        'payment_id' => $testPaymentId,
        'action' => 'return',
        'return_note' => $returnNote,
    ], $token);
    $returned = $pdo->prepare('SELECT status, reviewed_by_user_id, return_note FROM sponsor_payments WHERE id=?');
    $returned->execute([$testPaymentId]);
    $returnedRow = $returned->fetch();
    mobileApiExpect(
        $r['status'] === 200
        && ($r['json']['data']['status'] ?? '') === 'returned'
        && $returnedRow
        && $returnedRow['status'] === 'returned'
        && (int)$returnedRow['reviewed_by_user_id'] === $userId
        && (string)$returnedRow['return_note'] === $returnNote,
        'financial manager return commits the expected sponsor payment transition'
    );

    $r = mobileApiRequest('/api/v1/accounting/fm_review_queue_action.php', 'POST', [
        'payment_id' => $testPaymentId,
        'action' => 'return',
        'return_note' => 'Duplicate return must fail',
    ], $token);
    mobileApiExpect($r['status'] === 409 && ($r['json']['error']['code'] ?? '') === 'payment_not_pending', 'already-returned sponsor payment cannot be returned twice');
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['hr_staff'], $userId]);

    $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$userId]);
    $r = mobileApiRequest('/api/v1/auth/me.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 401, 'deactivated accounts lose access to existing tokens');
    $pdo->prepare('UPDATE users SET is_active=1, password_change_required=1, role_id=? WHERE id=?')->execute([$roles['nanny'], $userId]);
    $r = mobileApiRequest('/api/v1/auth/login.php', 'POST', ['username' => $username, 'password' => $knownPassword]);
    mobileApiExpect($r['status'] === 403 && ($r['json']['error']['code'] ?? '') === 'password_change_required', 'password-change-required accounts cannot sign in');

    $expiredToken = bin2hex(random_bytes(32));
    $revokedToken = bin2hex(random_bytes(32));
    $expiredHash = hash('sha256', $expiredToken);
    $revokedHash = hash('sha256', $revokedToken);
    $testTokenHashes[] = $expiredHash;
    $testTokenHashes[] = $revokedHash;
    $insert = $pdo->prepare('INSERT INTO mobile_api_sessions (user_id, token_hash, expires_at, revoked_at, created_at, last_used_at) VALUES (?, ?, ?, NULL, NOW(), NOW())');
    $insert->execute([$userId, $expiredHash, date('Y-m-d H:i:s', time() - 60)]);
    $insert->execute([$userId, $revokedHash, date('Y-m-d H:i:s', time() + 3600)]);
    $pdo->prepare('UPDATE mobile_api_sessions SET revoked_at=NOW() WHERE token_hash=?')->execute([$revokedHash]);
    $r = mobileApiRequest('/api/v1/auth/me.php', 'GET', null, $expiredToken);
    mobileApiExpect($r['status'] === 401, 'expired sessions are rejected');
    $r = mobileApiRequest('/api/v1/auth/me.php', 'GET', null, $revokedToken);
    mobileApiExpect($r['status'] === 401, 'revoked sessions are rejected');

    $pdo->prepare('UPDATE users SET password_change_required=0 WHERE id=?')->execute([$userId]);
    $r = mobileApiRequest('/api/v1/auth/logout.php', 'POST', [], $token);
    mobileApiExpect($r['status'] === 200 && ($r['json']['data']['signed_out'] ?? false) === true, 'logout revokes the bearer session');
    $r = mobileApiRequest('/api/v1/auth/me.php', 'GET', null, $token);
    mobileApiExpect($r['status'] === 401, 'logged-out token can no longer authenticate');

    echo "PASS | mobile_api_auth_integration=PASS\n";
} catch (Throwable $e) {
    $failure = $e;
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    foreach ($serverPipes as $pipe) if (is_resource($pipe)) fclose($pipe);
    if ($testLeaveId > 0) {
        $pdo->prepare('DELETE FROM notifications WHERE link LIKE ?')->execute(['%leave_id=' . $testLeaveId . '%']);
        if ($testLeaveEmployeeId > 0 && $testLeaveAttendanceDate !== '') {
            $pdo->prepare("DELETE FROM attendance WHERE employee_id=? AND date=? AND status='on_leave' AND notes='إجازة معتمدة'")
                ->execute([$testLeaveEmployeeId, $testLeaveAttendanceDate]);
        }
        $pdo->prepare('DELETE FROM leaves WHERE id=?')->execute([$testLeaveId]);
    }
    if ($testNotificationId > 0) {
        $pdo->prepare('DELETE FROM notifications WHERE id=?')->execute([$testNotificationId]);
    }
    if ($testSponsorRequestId > 0) {
        $pdo->prepare('DELETE FROM sponsor_requests WHERE id=?')->execute([$testSponsorRequestId]);
    }
    if ($testNannyFamilyId > 0) {
        $pdo->prepare('DELETE FROM families WHERE id=?')->execute([$testNannyFamilyId]);
    }
    if ($testForeignNannyFamilyId > 0) {
        $pdo->prepare('DELETE FROM families WHERE id=?')->execute([$testForeignNannyFamilyId]);
    }
    if ($testSponsorId > 0) {
        $pdo->prepare('DELETE FROM sponsors WHERE id=?')->execute([$testSponsorId]);
    }
    if (isset($testTokenHashes) && $testTokenHashes) {
        $marks = implode(',', array_fill(0, count($testTokenHashes), '?'));
        $stmt = $pdo->prepare("DELETE FROM mobile_api_sessions WHERE token_hash IN ($marks)");
        $stmt->execute($testTokenHashes);
    }
    if ($testPaymentId > 0) {
        $pdo->prepare("DELETE FROM notifications WHERE recipient_user_id=? AND title=? AND created_at>=?")
            ->execute([$userId, 'تم إرجاع تحصيل مشرف #' . $testPaymentId, $testStart]);
        $pdo->prepare("DELETE FROM audit_log WHERE action='RETURN' AND entity_type='sponsor_payments' AND entity_id=? AND created_at>=?")
            ->execute([$testPaymentId, $testStart]);
        $pdo->prepare('DELETE FROM sponsor_payments WHERE id=?')->execute([$testPaymentId]);
    }
    if ($testProjectId > 0) {
        $pdo->prepare('DELETE FROM project_progress_updates WHERE project_id=?')->execute([$testProjectId]);
        $pdo->prepare('DELETE FROM project_milestones WHERE project_id=?')->execute([$testProjectId]);
        if ($testForeignProjectId > 0) $pdo->prepare('DELETE FROM project_milestones WHERE project_id=?')->execute([$testForeignProjectId]);
        $pdo->prepare('DELETE FROM project_supervisor_assignments WHERE project_id=?')->execute([$testProjectId]);
        $pdo->prepare('DELETE FROM project_lifecycle WHERE project_id=?')->execute([$testProjectId]);
        $pdo->prepare('DELETE FROM project_approval WHERE project_id=?')->execute([$testProjectId]);
        if ($testForeignProjectId > 0) $pdo->prepare('DELETE FROM other_projects WHERE id=?')->execute([$testForeignProjectId]);
        $pdo->prepare('DELETE FROM other_projects WHERE id=?')->execute([$testProjectId]);
    }
    $pdo->prepare('DELETE FROM audit_log WHERE user_id=? AND action=? AND entity_type=? AND entity_id=? AND created_at>=?')
        ->execute([$userId, 'LOGIN', 'users', $userId, $testStart]);
    $pdo->prepare('UPDATE users SET password_hash=?, is_active=?, password_change_required=?, role_id=?, last_login_at=? WHERE id=?')
        ->execute([$user['password_hash'], $user['is_active'], $user['password_change_required'], $user['role_id'], $user['last_login_at'], $userId]);
}
if ($failure !== null) {
    fwrite(STDERR, $failure->getMessage() . "\n");
    exit(1);
}
