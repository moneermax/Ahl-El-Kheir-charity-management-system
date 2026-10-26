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
function mobileApiRequest(string $path, string $method = 'GET', ?array $body = null, ?string $token = null, int $timeoutSeconds = 5, bool $followRedirects = true): array {
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
        'follow_location' => $followRedirects ? 1 : 0,
        'max_redirects' => $followRedirects ? 20 : 0,
    ]]);
    $raw = file_get_contents('http://127.0.0.1:18080' . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $m)) $status = (int)$m[1];
    }
    $json = is_string($raw) ? json_decode($raw, true) : null;
    return ['status' => $status, 'json' => is_array($json) ? $json : [], 'raw' => (string)$raw, 'headers' => $responseHeaders];
}

function mobileApiMultipartRequest(string $path, array $fields, string $fileField, string $fileName, string $fileBytes, string $mime, ?string $token = null, int $timeoutSeconds = 10): array {
    $boundary = '----MobileApiIntegration' . bin2hex(random_bytes(12));
    $content = '';
    foreach ($fields as $name => $value) {
        $content .= '--' . $boundary . "\r\n";
        $content .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
        $content .= (string)$value . "\r\n";
    }
    $content .= '--' . $boundary . "\r\n";
    $content .= 'Content-Disposition: form-data; name="' . $fileField . '"; filename="' . basename($fileName) . '"' . "\r\n";
    $content .= 'Content-Type: ' . $mime . "\r\n\r\n" . $fileBytes . "\r\n";
    $content .= '--' . $boundary . "--\r\n";
    $headers = [
        'Accept: application/json',
        'Content-Type: multipart/form-data; boundary=' . $boundary,
    ];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    $context = stream_context_create(['http' => [
        'method' => 'POST',
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
    return ['status' => $status, 'json' => is_array($json) ? $json : [], 'raw' => (string)$raw, 'headers' => $responseHeaders];
}

$roles = [];
foreach ($pdo->query("SELECT id, code FROM roles WHERE code IN ('admin','nanny','hr_staff','project_supervisor','financial_manager','supervisor','general_manager','accountant','accountant_staff')") as $role) $roles[(string)$role['code']] = (int)$role['id'];
if (!isset($roles['admin'], $roles['nanny'], $roles['hr_staff'], $roles['project_supervisor'], $roles['financial_manager'], $roles['supervisor'], $roles['general_manager'])) {
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
$testArchiveSourceId = 0;
$testArchiveDocumentId = 0;
$testArchiveVersionId = 0;
$testArchiveBlobId = 0;
$testArchiveAbsolutePath = '';
$testArchiveDirectory = '';
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
$testFinaSourceId = 0;
$testFinaCollectionId = 0;
$testBackupName = '';
$testLogoPath = '';
$testPreviousLogoValue = null;
$testLogoSettingExisted = false;
$testOrphanFormChildId = 0;
$testOrphanFormChildSnapshot = null;
$testOrphanFormFamilySnapshot = null;
$testDisbursementId = 0;
$testDisbursementItemId = 0;
$testDisbursementReturnItemId = 0;
$testBatchCreatedId = 0;
$testBatchCreatedItemId = 0;
$testBatchGroupId = 0;
$testBatchNannyId = 0;
$testBatchVerificationFamilyId = 0;
$testBatchVerificationMonth = '2099-12';
$testBatchTransferTransactionId = 0;
$testBatchTransferJournalId = 0;
$testAccountId = 0;
$testAttendancePolicyId = 0;
$testPayrollId = 0;
$testPayrollProofStorageKey = '';
$testPayrollMonth = 0;
$testPayrollYear = 0;
$testPayrollEmployeeId = 0;
$testPayrollJournalId = 0;
$testLegacyPayrollId = 0;
$testLegacyPayrollProofStorageKey = '';
$testLegacyPayrollMonth = 0;
$testLegacyPayrollYear = 0;
$testLegacyPayrollJournalId = 0;
$testDraftPayrollId = 0;
$testDraftPayrollMonth = 0;
$testDraftPayrollYear = 0;
$testPayrollPeriodSettingsTouched = false;
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
    $r = mobileApiRequest('/api/v1/search/documents.php');
    mobileApiExpect($r['status'] === 401, 'archived document search rejects unauthenticated requests');
    $r = mobileApiRequest('/api/v1/search/document_file.php?version_id=1');
    mobileApiExpect($r['status'] === 401, 'archived document delivery rejects unauthenticated requests');
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

    // Exercise the native finance overview against the imported full schema,
    // using the existing disposable account and restoring its role afterward.
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['supervisor'], $userId]);
    $supervisorDisbursements = mobileApiRequest('/api/v1/admin/disbursements.php', 'GET', null, $token);
    mobileApiExpect($supervisorDisbursements['status'] === 403, 'supervisor role is denied monthly disbursements because the existing web page does not permit it');
    $supervisorPayroll = mobileApiRequest('/api/v1/hr/payroll_management.php?month=10&year=2026', 'GET', null, $token);
    mobileApiExpect($supervisorPayroll['status'] === 403, 'supervisor is denied native payroll management by the existing web role boundary');
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['hr_staff'], $userId]);
    $hrStaffDocumentSearch = mobileApiRequest('/api/v1/search/documents.php?q=receipt', 'GET', null, $token);
    mobileApiExpect($hrStaffDocumentSearch['status'] === 403, 'HR Staff is denied archived document search because the existing search permission matrix excludes documents');
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['admin'], $userId]);
    $adminLogin = mobileApiRequest('/api/v1/auth/login.php', 'POST', ['username' => $username, 'password' => $knownPassword]);
    mobileApiExpect($adminLogin['status'] === 200 && isset($adminLogin['json']['data']['access_token']), 'administrator login succeeds for finance overview integration');
    $adminToken = (string)$adminLogin['json']['data']['access_token'];
    $adminHash = hash('sha256', $adminToken);
    $testTokenHashes[] = $adminHash;
    // Register one real file through the existing archive adapter so both search and
    // bearer-protected binary delivery are exercised with a real archive row.
    $testArchiveRelativePath = 'storage/documents/projects/' . $testProjectId . '/mobile-api-archive-fixture.pdf';
    $testArchiveAbsolutePath = dirname(__DIR__) . '/' . $testArchiveRelativePath;
    $testArchiveDirectory = dirname($testArchiveAbsolutePath);
    if (!is_dir($testArchiveDirectory) && !mkdir($testArchiveDirectory, 0775, true) && !is_dir($testArchiveDirectory)) {
        throw new RuntimeException('Could not create the temporary archive fixture directory.');
    }
    $testArchiveBytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    if (file_put_contents($testArchiveAbsolutePath, $testArchiveBytes) !== strlen($testArchiveBytes)) {
        throw new RuntimeException('Could not write the temporary archive fixture file.');
    }
    $pdo->prepare('INSERT INTO project_documents (project_id, document_type, title, file_path, original_name, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$testProjectId, 'other', 'Mobile API archive fixture unique', $testArchiveRelativePath, 'mobile-api-archive-fixture.pdf', 'application/pdf', strlen($testArchiveBytes), $userId]);
    $testArchiveSourceId = (int)$pdo->lastInsertId();
    require_once dirname(__DIR__) . '/modules/projects/lib_project_document_archive.php';
    $archiveDb = db();
    $archiveDb->beginTransaction();
    try {
        akp_archive_register_project_file('project_documents', $testArchiveSourceId, $testProjectId, $testArchiveRelativePath, 'mobile-api-archive-fixture.pdf', 'application/pdf', strlen($testArchiveBytes), 'Mobile API archive fixture unique');
        $archiveDb->commit();
    } catch (Throwable $archiveError) {
        if ($archiveDb->inTransaction()) $archiveDb->rollBack();
        throw $archiveError;
    }
    $archiveLocator = $pdo->prepare('SELECT document_id, current_version_id FROM document_archive_source_links WHERE source_table=? AND source_record_id=? AND source_path_column=? LIMIT 1');
    $archiveLocator->execute(['project_documents', $testArchiveSourceId, 'file_path']);
    $archiveLink = $archiveLocator->fetch(PDO::FETCH_ASSOC) ?: [];
    $testArchiveDocumentId = (int)($archiveLink['document_id'] ?? 0);
    $testArchiveVersionId = (int)($archiveLink['current_version_id'] ?? 0);
    $archiveBlobQuery = $pdo->prepare('SELECT blob_id FROM document_archive_versions WHERE id=? AND document_id=?');
    $archiveBlobQuery->execute([$testArchiveVersionId, $testArchiveDocumentId]);
    $testArchiveBlobId = (int)$archiveBlobQuery->fetchColumn();
    mobileApiExpect($testArchiveSourceId > 0 && $testArchiveDocumentId > 0 && $testArchiveVersionId > 0 && $testArchiveBlobId > 0, 'archive fixture is registered through the existing versioning helper');

    $archiveFixtureSearch = mobileApiRequest('/api/v1/search/documents.php?q=' . rawurlencode('Mobile API archive fixture unique'), 'GET', null, $adminToken, 20);
    $archiveFixtureItems = $archiveFixtureSearch['json']['data']['items'] ?? [];
    mobileApiExpect(
        $archiveFixtureSearch['status'] === 200
        && count($archiveFixtureItems) === 1
        && (int)($archiveFixtureItems[0]['version_id'] ?? 0) === $testArchiveVersionId
        && !array_key_exists('storage_key', $archiveFixtureItems[0] ?? []),
        'administrator search returns the archived project document without exposing its storage key'
    );
    $archiveFile = mobileApiRequest('/api/v1/search/document_file.php?version_id=' . $testArchiveVersionId, 'GET', null, $adminToken, 20);
    $archiveFileHeaders = implode("\n", $archiveFile['headers']);
    mobileApiExpect(
        $archiveFile['status'] === 200
        && str_starts_with($archiveFile['raw'], '%PDF-1.4')
        && stripos($archiveFileHeaders, 'Content-Type: application/pdf') !== false,
        'authorized archive version streams private PDF bytes inside the native API'
    );
    $archiveSearch = mobileApiRequest('/api/v1/search/documents.php?q=__mobile_api_no_match__', 'GET', null, $adminToken, 20);
    mobileApiExpect(
        $archiveSearch['status'] === 200
        && is_array($archiveSearch['json']['data']['items'] ?? null)
        && (int)($archiveSearch['json']['data']['total'] ?? -1) === 0,
        'administrator can query the native archived document search with pagination and no leaked file paths'
    );
    $archiveLongQuery = mobileApiRequest('/api/v1/search/documents.php?q=' . str_repeat('x', 101), 'GET', null, $adminToken);
    mobileApiExpect($archiveLongQuery['status'] === 422, 'archived document search rejects overlong queries');
    $archiveMissingVersion = mobileApiRequest('/api/v1/search/document_file.php?version_id=999999999', 'GET', null, $adminToken);
    mobileApiExpect($archiveMissingVersion['status'] === 404, 'archived document delivery does not expose missing archive versions');

    $payrollRead = mobileApiRequest('/api/v1/hr/payroll_management.php?month=10&year=2026', 'GET', null, $adminToken, 20);
    mobileApiExpect(
        $payrollRead['status'] === 200
        && ($payrollRead['json']['data']['period'] ?? '') === '2026-10'
        && is_array($payrollRead['json']['data']['rows'] ?? null)
        && ($payrollRead['json']['data']['read_only'] ?? false) === true
        && ($payrollRead['json']['data']['transfer_schema_available'] ?? false) === true,
        'administrator can read native payroll rows, account choices and transfer-proof state'
    );
    $payrollInvalidPeriod = mobileApiRequest('/api/v1/hr/payroll_management.php?month=13&year=2026', 'GET', null, $adminToken);
    mobileApiExpect($payrollInvalidPeriod['status'] === 422, 'native payroll management rejects invalid periods');

    $testPayrollEmployeeId = (int)$pdo->query(
        "SELECT e.id FROM employees e
         WHERE NOT EXISTS (
           SELECT 1 FROM hr_salary_advance_requests r
           WHERE r.employee_id=e.id AND r.status='disbursed' AND COALESCE(r.outstanding_balance,0)>0
         )
         AND NOT EXISTS (
           SELECT 1 FROM hr_incentive_awards ia
           JOIN hr_incentive_events ie ON ie.id=ia.event_id
           WHERE ia.employee_id=e.id AND ia.payroll_id IS NULL
             AND ia.status IN ('earned','held_currency') AND ie.status='verified'
         )
         ORDER BY e.id LIMIT 1"
    )->fetchColumn();
    $testPayrollAccountId = (int)$pdo->query("SELECT id FROM accounts WHERE code='1200' AND is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
    mobileApiExpect($testPayrollEmployeeId > 0 && $testPayrollAccountId > 0, 'payroll proof fixture has an employee without outstanding salary advances and the existing active 1200 account');
    for ($candidateYear = 2099; $candidateYear >= 2090 && $testPayrollMonth === 0; $candidateYear--) {
        for ($candidateMonth = 12; $candidateMonth >= 1; $candidateMonth--) {
            $exists = $pdo->prepare('SELECT id FROM payroll WHERE employee_id=? AND month=? AND year=? LIMIT 1');
            $exists->execute([$testPayrollEmployeeId, $candidateMonth, $candidateYear]);
            if (!$exists->fetchColumn()) {
                $testPayrollMonth = $candidateMonth;
                $testPayrollYear = $candidateYear;
                break;
            }
        }
    }
    mobileApiExpect($testPayrollMonth > 0 && $testPayrollYear > 0, 'disposable payroll test period is unique for the selected employee');
    $pdo->prepare(
        "INSERT INTO payroll
          (employee_id,month,year,basic_salary,allowances,overtime,deductions,salary_advance_deduction,net_salary,status,payment_account_id,accounting_status)
         VALUES (?,?,?,500,0,0,0,0,500,'approved',?,'ready')"
    )->execute([$testPayrollEmployeeId, $testPayrollMonth, $testPayrollYear, $testPayrollAccountId]);
    $testPayrollId = (int)$pdo->lastInsertId();

    $payrollProofBytes = "%PDF-1.4\n% Disposable mobile payroll proof fixture\n%%EOF\n";
    $uploadPayrollProof = mobileApiMultipartRequest(
        '/api/v1/hr/payroll_management.php',
        ['action'=>'upload_payment_proof','payroll_id'=>(string)$testPayrollId,'month'=>(string)$testPayrollMonth,'year'=>(string)$testPayrollYear],
        'proof_file','payroll-proof.pdf',$payrollProofBytes,'application/pdf',$adminToken
    );
    $payrollProofRow = $pdo->prepare('SELECT proof_state,storage_key FROM hr_payroll_payment_proofs WHERE payroll_id=?');
    $payrollProofRow->execute([$testPayrollId]);
    $proofAfterUpload = $payrollProofRow->fetch(PDO::FETCH_ASSOC) ?: [];
    $testPayrollProofStorageKey = (string)($proofAfterUpload['storage_key'] ?? '');
    $statusAfterProof = $pdo->prepare('SELECT status,accounting_status FROM payroll WHERE id=?');
    $statusAfterProof->execute([$testPayrollId]);
    $afterProofPayroll = $statusAfterProof->fetch(PDO::FETCH_ASSOC) ?: [];
    $journalCountAfterProof = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type='payroll' AND reference_id=" . $testPayrollId)->fetchColumn();
    mobileApiExpect(
        $uploadPayrollProof['status'] === 200
        && ($proofAfterUpload['proof_state'] ?? '') === 'prepared'
        && ($afterProofPayroll['status'] ?? '') === 'approved'
        && ($afterProofPayroll['accounting_status'] ?? '') === 'ready'
        && $journalCountAfterProof === 0,
        'uploading a payroll proof stores prepared evidence only and does not pay or post accounting'
    );

    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['supervisor'], $userId]);
    $unauthorizedPayrollProof = mobileApiRequest('/api/v1/hr/payroll_proof.php?payroll_id=' . $testPayrollId, 'GET', null, $token);
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['admin'], $userId]);
    mobileApiExpect($unauthorizedPayrollProof['status'] === 403, 'non-payroll staff cannot view a prepared payroll proof');
    $viewPayrollProof = mobileApiRequest('/api/v1/hr/payroll_proof.php?payroll_id=' . $testPayrollId, 'GET', null, $adminToken);
    $proofContentType = '';
    foreach ($viewPayrollProof['headers'] as $proofHeader) {
        if (stripos($proofHeader, 'Content-Type:') === 0) $proofContentType = strtolower(trim(substr($proofHeader, strlen('Content-Type:'))));
    }
    mobileApiExpect(
        $viewPayrollProof['status'] === 200
        && str_starts_with($viewPayrollProof['raw'], '%PDF-1.4')
        && str_starts_with($proofContentType, 'application/pdf'),
        'authorized native proof endpoint streams private PDF bytes without exposing a storage path'
    );

    $wrongPeriodConfirm = mobileApiRequest('/api/v1/hr/payroll_management.php', 'POST', [
        'action'=>'confirm_transfer','payroll_id'=>$testPayrollId,'month'=>$testPayrollMonth === 12 ? 11 : $testPayrollMonth + 1,'year'=>$testPayrollYear,
    ], $adminToken);
    mobileApiExpect($wrongPeriodConfirm['status'] === 409, 'payroll transfer confirmation rejects a row outside the selected period');

    $confirmPayroll = mobileApiRequest('/api/v1/hr/payroll_management.php', 'POST', [
        'action'=>'confirm_transfer','payroll_id'=>$testPayrollId,'month'=>$testPayrollMonth,'year'=>$testPayrollYear,
    ], $adminToken, 20);
    $confirmedPayrollStmt = $pdo->prepare('SELECT status,accounting_status,accounting_entry_id FROM payroll WHERE id=?');
    $confirmedPayrollStmt->execute([$testPayrollId]);
    $confirmedPayroll = $confirmedPayrollStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $confirmedProofStmt = $pdo->prepare('SELECT proof_state,confirmed_by_user_id,confirmed_at FROM hr_payroll_payment_proofs WHERE payroll_id=?');
    $confirmedProofStmt->execute([$testPayrollId]);
    $confirmedProof = $confirmedProofStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $testPayrollJournalId = (int)($confirmedPayroll['accounting_entry_id'] ?? 0);
    $journalBalance = $pdo->prepare('SELECT ROUND(COALESCE(SUM(debit),0),2) AS debit_total,ROUND(COALESCE(SUM(credit),0),2) AS credit_total FROM journal_lines WHERE entry_id=?');
    $journalBalance->execute([$testPayrollJournalId]);
    $balanced = $journalBalance->fetch(PDO::FETCH_ASSOC) ?: [];
    $journalActorStmt = $pdo->prepare('SELECT created_by FROM journal_entries WHERE id=?');
    $journalActorStmt->execute([$testPayrollJournalId]);
    mobileApiExpect(
        $confirmPayroll['status'] === 200
        && ($confirmedPayroll['status'] ?? '') === 'paid'
        && ($confirmedPayroll['accounting_status'] ?? '') === 'posted'
        && $testPayrollJournalId > 0
        && ($confirmedProof['proof_state'] ?? '') === 'confirmed'
        && (int)($confirmedProof['confirmed_by_user_id'] ?? 0) === $userId
        && !empty($confirmedProof['confirmed_at'])
        && (float)($balanced['debit_total'] ?? 0) === 500.0
        && (float)($balanced['credit_total'] ?? 0) === 500.0
        && (int)$journalActorStmt->fetchColumn() === $userId,
        'explicit payroll transfer confirmation atomically records actor, paid status, confirmed proof and balanced posted accounting'
    );
    $duplicatePayrollConfirm = mobileApiRequest('/api/v1/hr/payroll_management.php', 'POST', [
        'action'=>'confirm_transfer','payroll_id'=>$testPayrollId,'month'=>$testPayrollMonth,'year'=>$testPayrollYear,
    ], $adminToken, 20);
    $journalCountAfterDuplicate = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type='payroll' AND reference_id=" . $testPayrollId . " AND status='posted'")->fetchColumn();
    mobileApiExpect($duplicatePayrollConfirm['status'] === 409 && $journalCountAfterDuplicate === 1, 'duplicate payroll transfer confirmation is rejected without duplicate journal posting');

    $legacyExpenseAccountId = (int)$pdo->query("SELECT id FROM accounts WHERE code='5200' LIMIT 1")->fetchColumn();
    mobileApiExpect($legacyExpenseAccountId > 0, 'legacy evidence fixture has the existing payroll expense account');
    for ($candidateYear = 2099; $candidateYear >= 2090 && $testLegacyPayrollMonth === 0; $candidateYear--) {
        for ($candidateMonth = 12; $candidateMonth >= 1; $candidateMonth--) {
            if ($candidateYear === $testPayrollYear && $candidateMonth === $testPayrollMonth) continue;
            $exists = $pdo->prepare('SELECT id FROM payroll WHERE employee_id=? AND month=? AND year=? LIMIT 1');
            $exists->execute([$testPayrollEmployeeId, $candidateMonth, $candidateYear]);
            if (!$exists->fetchColumn()) {
                $testLegacyPayrollMonth = $candidateMonth;
                $testLegacyPayrollYear = $candidateYear;
                break;
            }
        }
    }
    mobileApiExpect($testLegacyPayrollMonth > 0 && $testLegacyPayrollYear > 0, 'legacy evidence fixture has a second unique payroll period');
    $legacyPaymentDate = sprintf('%04d-%02d-15', $testLegacyPayrollYear, $testLegacyPayrollMonth);
    $pdo->prepare(
        "INSERT INTO payroll
          (employee_id,month,year,basic_salary,allowances,overtime,deductions,salary_advance_deduction,net_salary,status,payment_date,payment_account_id,accounting_status)
         VALUES (?,?,?,500,0,0,0,0,500,'paid',?,?,'posted')"
    )->execute([$testPayrollEmployeeId, $testLegacyPayrollMonth, $testLegacyPayrollYear, $legacyPaymentDate, $testPayrollAccountId]);
    $testLegacyPayrollId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by)
         VALUES (?,?,?,'payroll',?,'posted',?)"
    )->execute(['MOB-LEGACY-' . $testLegacyPayrollId, $legacyPaymentDate, 'Disposable legacy payroll evidence fixture', $testLegacyPayrollId, $userId]);
    $testLegacyPayrollJournalId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,500,0,?),(?,?,0,500,?)')
        ->execute([$testLegacyPayrollJournalId,$legacyExpenseAccountId,'Legacy payroll expense',$testLegacyPayrollJournalId,$testPayrollAccountId,'Legacy payroll transfer']);
    $pdo->prepare('UPDATE payroll SET accounting_entry_id=? WHERE id=?')->execute([$testLegacyPayrollJournalId,$testLegacyPayrollId]);
    $legacyUpload = mobileApiMultipartRequest(
        '/api/v1/hr/payroll_management.php',
        ['action'=>'upload_legacy_payment_evidence','payroll_id'=>(string)$testLegacyPayrollId,'month'=>(string)$testLegacyPayrollMonth,'year'=>(string)$testLegacyPayrollYear],
        'proof_file','legacy-payroll-proof.pdf',$payrollProofBytes,'application/pdf',$adminToken
    );
    $legacyProofStmt = $pdo->prepare('SELECT proof_state,storage_key FROM hr_payroll_payment_proofs WHERE payroll_id=?');
    $legacyProofStmt->execute([$testLegacyPayrollId]);
    $legacyProof = $legacyProofStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $testLegacyPayrollProofStorageKey = (string)($legacyProof['storage_key'] ?? '');
    $legacyPayrollStmt = $pdo->prepare('SELECT status,payment_date,accounting_status,accounting_entry_id FROM payroll WHERE id=?');
    $legacyPayrollStmt->execute([$testLegacyPayrollId]);
    $legacyPayrollAfter = $legacyPayrollStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $legacyJournalLines = $pdo->prepare('SELECT COUNT(*) AS line_count,ROUND(SUM(debit),2) AS debit_total,ROUND(SUM(credit),2) AS credit_total FROM journal_lines WHERE entry_id=?');
    $legacyJournalLines->execute([$testLegacyPayrollJournalId]);
    $legacyJournalAfter = $legacyJournalLines->fetch(PDO::FETCH_ASSOC) ?: [];
    mobileApiExpect(
        $legacyUpload['status'] === 200
        && ($legacyProof['proof_state'] ?? '') === 'legacy_evidence'
        && ($legacyPayrollAfter['status'] ?? '') === 'paid'
        && ($legacyPayrollAfter['payment_date'] ?? '') === $legacyPaymentDate
        && ($legacyPayrollAfter['accounting_status'] ?? '') === 'posted'
        && (int)($legacyPayrollAfter['accounting_entry_id'] ?? 0) === $testLegacyPayrollJournalId
        && (int)($legacyJournalAfter['line_count'] ?? 0) === 2
        && (float)($legacyJournalAfter['debit_total'] ?? 0) === 500.0
        && (float)($legacyJournalAfter['credit_total'] ?? 0) === 500.0,
        'legacy evidence attaches without changing paid history, payment date, journal link or balanced accounting'
    ); 

    for ($candidateYear = 2099; $candidateYear >= 2090 && $testDraftPayrollMonth === 0; $candidateYear--) {
        for ($candidateMonth = 12; $candidateMonth >= 1; $candidateMonth--) {
            if (($candidateYear === $testPayrollYear && $candidateMonth === $testPayrollMonth)
                || ($candidateYear === $testLegacyPayrollYear && $candidateMonth === $testLegacyPayrollMonth)) continue;
            $exists = $pdo->prepare('SELECT id FROM payroll WHERE employee_id=? AND month=? AND year=? LIMIT 1');
            $exists->execute([$testPayrollEmployeeId, $candidateMonth, $candidateYear]);
            if (!$exists->fetchColumn()) {
                $testDraftPayrollMonth = $candidateMonth;
                $testDraftPayrollYear = $candidateYear;
                break;
            }
        }
    }
    mobileApiExpect($testDraftPayrollMonth > 0 && $testDraftPayrollYear > 0, 'draft payroll fixture has a third unique period');
    $pdo->prepare(
        "INSERT INTO payroll
          (employee_id,month,year,basic_salary,allowances,overtime,deductions,salary_advance_deduction,net_salary,status)
         VALUES (?,?,?,1000,0,0,0,0,1000,'draft')"
    )->execute([$testPayrollEmployeeId, $testDraftPayrollMonth, $testDraftPayrollYear]);
    $testDraftPayrollId = (int)$pdo->lastInsertId();
    $draftEdit = mobileApiRequest('/api/v1/hr/payroll_management.php', 'POST', [
        'action'=>'update_amounts','payroll_id'=>$testDraftPayrollId,'month'=>$testDraftPayrollMonth,'year'=>$testDraftPayrollYear,
        'allowances'=>100,'overtime'=>25,'deductions'=>50,
    ], $adminToken, 20);
    $draftAfterEditStmt = $pdo->prepare('SELECT status,basic_salary,allowances,overtime,deductions,attendance_deduction,salary_advance_deduction,incentive_amount,net_salary FROM payroll WHERE id=?');
    $draftAfterEditStmt->execute([$testDraftPayrollId]);
    $draftAfterEdit = $draftAfterEditStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $draftExpectedNet = round(
        (float)($draftAfterEdit['basic_salary'] ?? 0)
        + (float)($draftAfterEdit['allowances'] ?? 0)
        + (float)($draftAfterEdit['overtime'] ?? 0)
        - (float)($draftAfterEdit['deductions'] ?? 0)
        - (float)($draftAfterEdit['attendance_deduction'] ?? 0)
        - (float)($draftAfterEdit['salary_advance_deduction'] ?? 0)
        + (float)($draftAfterEdit['incentive_amount'] ?? 0),
        2
    );
    mobileApiExpect(
        $draftEdit['status'] === 200
        && ($draftAfterEdit['status'] ?? '') === 'draft'
        && (float)($draftAfterEdit['allowances'] ?? 0) === 100.0
        && (float)($draftAfterEdit['overtime'] ?? 0) === 25.0
        && (float)($draftAfterEdit['deductions'] ?? 0) === 50.0
        && (float)($draftAfterEdit['net_salary'] ?? 0) === $draftExpectedNet,
        'native payroll draft edits retain draft status and recompute net from all payroll components'
    );
    $draftApprove = mobileApiRequest('/api/v1/hr/payroll_management.php', 'POST', [
        'action'=>'approve','payroll_id'=>$testDraftPayrollId,'month'=>$testDraftPayrollMonth,'year'=>$testDraftPayrollYear,
    ], $adminToken, 20);
    $draftAfterApproveStmt = $pdo->prepare('SELECT status,accounting_status,accounting_entry_id FROM payroll WHERE id=?');
    $draftAfterApproveStmt->execute([$testDraftPayrollId]);
    $draftAfterApprove = $draftAfterApproveStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $draftJournalCount = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE reference_type='payroll' AND reference_id=" . $testDraftPayrollId)->fetchColumn();
    mobileApiExpect(
        $draftApprove['status'] === 200
        && ($draftAfterApprove['status'] ?? '') === 'approved'
        && ($draftAfterApprove['accounting_status'] ?? '') === 'ready'
        && empty($draftAfterApprove['accounting_entry_id'])
        && $draftJournalCount === 0,
        'native payroll approval changes only draft status and does not mark paid or post a journal'
    );


    $adminNannyActionDenied = mobileApiRequest('/api/v1/admin/disbursements_action.php', 'POST', [
        'action' => 'confirm_item', 'item_id' => 1,
    ], $adminToken);
    mobileApiExpect($adminNannyActionDenied['status'] === 403, 'administrator cannot use nanny-only family receipt confirmation action');

    $eligibleBatchGroup = $pdo->query(
        "SELECT og.id AS group_id, nga.nanny_id, fc.family_id
         FROM orphan_groups og
         JOIN nanny_group_assignments nga ON nga.group_id=og.id AND nga.end_date IS NULL
         JOIN group_children gc ON gc.group_id=og.id AND gc.left_date IS NULL
         JOIN family_children fc ON fc.id=gc.child_id AND fc.is_active=1
         JOIN sponsorships sp ON sp.child_id=gc.child_id AND sp.status='active'
         WHERE og.verification_status IN ('submitted','approved','pending')
           AND NOT EXISTS (
             SELECT 1 FROM monthly_disbursements d
             WHERE d.group_id=og.id AND d.month='2099-12' AND d.status NOT IN ('voided','cancelled')
           )
         ORDER BY og.id, fc.family_id
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if (!$eligibleBatchGroup) throw new RuntimeException('The checked-in schema needs an eligible active group with an active sponsorship for batch creation integration.');
    $testBatchGroupId = (int)$eligibleBatchGroup['group_id'];
    $testBatchNannyId = (int)$eligibleBatchGroup['nanny_id'];
    $testBatchVerificationFamilyId = (int)$eligibleBatchGroup['family_id'];
    $pdo->prepare(
        "INSERT INTO nanny_family_verifications
         (family_id,month,nanny_id,is_orphan_verified,is_mother_contact_verified,is_bank_verified,verified_by_user_id,verified_at)
         VALUES (?, ?, ?, 1, 1, 1, ?, NOW())"
    )->execute([$testBatchVerificationFamilyId,$testBatchVerificationMonth,$testBatchNannyId,$testBatchNannyId]);
    $eligibleBatchGroups = mobileApiRequest('/api/v1/admin/disbursements.php?month=' . rawurlencode($testBatchVerificationMonth), 'GET', null, $adminToken, 20);
    $eligibleGroupIds = array_map(
        static fn(array $group): int => (int)($group['id'] ?? 0),
        $eligibleBatchGroups['json']['data']['eligible_groups'] ?? []
    );
    mobileApiExpect(
        $eligibleBatchGroups['status'] === 200
        && ($eligibleBatchGroups['json']['data']['capabilities']['can_create_batches'] ?? false) === true
        && ($eligibleBatchGroups['json']['data']['eligible_month'] ?? '') === $testBatchVerificationMonth
        && in_array($testBatchGroupId, $eligibleGroupIds, true),
        'eligible group preview exposes fully verified groups and role capabilities for the requested month'
    );
    $createBatch = mobileApiRequest('/api/v1/admin/disbursements_action.php', 'POST', [
        'action' => 'create_batch',
        'month' => $testBatchVerificationMonth,
        'group_ids' => [$testBatchGroupId],
    ], $adminToken, 20);
    $testBatchCreatedId = (int)($createBatch['json']['data']['created'][0]['disbursement_id'] ?? 0);
    $createdBatchRow = null;
    $createdBatchItemCount = 0;
    if ($testBatchCreatedId > 0) {
        $createdBatchLookup = $pdo->prepare('SELECT id,status,total_amount,transaction_id,receipt_file_path FROM monthly_disbursements WHERE id=?');
        $createdBatchLookup->execute([$testBatchCreatedId]);
        $createdBatchRow = $createdBatchLookup->fetch(PDO::FETCH_ASSOC);
        $createdBatchItemCount = (int)$pdo->query('SELECT COUNT(*) FROM disbursement_items WHERE disbursement_id=' . $testBatchCreatedId)->fetchColumn();
    }
    mobileApiExpect(
        $createBatch['status'] === 200
        && ($createBatch['json']['data']['created_count'] ?? 0) === 1
        && $testBatchCreatedId > 0
        && (string)($createdBatchRow['status'] ?? '') === 'pending_approval'
        && $createdBatchItemCount === 1,
        'administrator creates one pending batch from a fully verified eligible group and the verified family item'
    );
    $testBatchCreatedItemId = (int)$pdo->query('SELECT id FROM disbursement_items WHERE disbursement_id=' . $testBatchCreatedId . ' ORDER BY id LIMIT 1')->fetchColumn();
    $duplicateBatch = mobileApiRequest('/api/v1/admin/disbursements_action.php', 'POST', [
        'action' => 'create_batch',
        'month' => $testBatchVerificationMonth,
        'group_ids' => [$testBatchGroupId],
    ], $adminToken, 20);
    mobileApiExpect(
        $duplicateBatch['status'] === 200
        && ($duplicateBatch['json']['data']['created_count'] ?? -1) === 0
        && ($duplicateBatch['json']['data']['skipped_count'] ?? 0) === 1,
        'duplicate monthly batch creation is skipped without creating a second batch'
    );
    $transferPngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true);
    if (!is_string($transferPngBytes) || $transferPngBytes === '') throw new RuntimeException('Could not prepare the in-memory transfer PNG fixture.');
    $transferBatch = mobileApiMultipartRequest(
        '/api/v1/admin/disbursements_action.php',
        ['action'=>'transfer_with_receipt','batch_id'=>(string)$testBatchCreatedId],
        'transfer_receipt','transfer.png',$transferPngBytes,'image/png',$adminToken,20
    );
    $transferredBatchLookup = $pdo->prepare('SELECT status,transaction_id,receipt_file_path FROM monthly_disbursements WHERE id=?');
    $transferredBatchLookup->execute([$testBatchCreatedId]);
    $transferredBatchRow = $transferredBatchLookup->fetch(PDO::FETCH_ASSOC);
    $testBatchTransferTransactionId = (int)($transferredBatchRow['transaction_id'] ?? 0);
    $testBatchTransferJournalId = (int)$pdo->query(
        "SELECT id FROM journal_entries WHERE reference_type='disbursement' AND reference_id=" . $testBatchCreatedId . " AND status='posted' ORDER BY id DESC LIMIT 1"
    )->fetchColumn();
    $journalTotals = $pdo->query(
        "SELECT COALESCE(SUM(debit),0) debit_total, COALESCE(SUM(credit),0) credit_total FROM journal_lines WHERE entry_id=" . $testBatchTransferJournalId
    )->fetch(PDO::FETCH_ASSOC);
    $transferReceiptPath = (string)($transferredBatchRow['receipt_file_path'] ?? '');
    mobileApiExpect(
        $transferBatch['status'] === 200
        && ($transferBatch['json']['data']['status'] ?? '') === 'transferred'
        && (string)($transferredBatchRow['status'] ?? '') === 'transferred'
        && $testBatchTransferTransactionId > 0
        && $testBatchTransferJournalId > 0
        && abs((float)($journalTotals['debit_total'] ?? 0) - (float)($journalTotals['credit_total'] ?? 0)) < 0.005
        && str_starts_with($transferReceiptPath,'storage/receipts/transfer_receipt_')
        && is_file(dirname(__DIR__) . '/' . $transferReceiptPath),
        'transfer receipt commits one transaction, a balanced journal and the transferred batch state'
    );
    $duplicateTransfer = mobileApiMultipartRequest(
        '/api/v1/admin/disbursements_action.php',
        ['action'=>'transfer_with_receipt','batch_id'=>(string)$testBatchCreatedId],
        'transfer_receipt','transfer.png',$transferPngBytes,'image/png',$adminToken,20
    );
    $transferCount = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE reference_number=? AND status='posted'");
    $transferCount->execute(['DISB-OUT-' . $testBatchCreatedId]);
    mobileApiExpect(
        $duplicateTransfer['status'] === 409
        && (int)$transferCount->fetchColumn() === 1,
        'repeated transfer is rejected without posting a duplicate transaction'
    );

    $pdo->prepare('UPDATE disbursement_items SET status=\'paid\',confirmed_at=NOW() WHERE id=?')->execute([$testBatchCreatedItemId]);
    $reopenItem = mobileApiRequest('/api/v1/admin/disbursements_action.php', 'POST', [
        'action'=>'reopen_item','item_id'=>$testBatchCreatedItemId,'reason'=>'Disposable integration reopen item',
    ], $adminToken, 20);
    $reopenedItemRow = $pdo->prepare('SELECT status,confirmed_at FROM disbursement_items WHERE id=?');
    $reopenedItemRow->execute([$testBatchCreatedItemId]);
    $reopenedItem = $reopenedItemRow->fetch(PDO::FETCH_ASSOC);
    mobileApiExpect(
        $reopenItem['status'] === 200
        && (string)($reopenItem['json']['data']['status'] ?? '') === 'pending'
        && (string)($reopenedItem['status'] ?? '') === 'pending'
        && ($reopenedItem['confirmed_at'] ?? null) === null,
        'reopening a paid family item returns it to pending state and clears its confirmation timestamp'
    );
    $pdo->prepare("UPDATE monthly_disbursements SET group_id=NULL,status='returned' WHERE id=?")->execute([$testBatchCreatedId]);
    $reopenBatch = mobileApiRequest('/api/v1/admin/disbursements_action.php', 'POST', [
        'action'=>'reopen_batch','batch_id'=>$testBatchCreatedId,'reason'=>'Disposable integration reopen batch',
    ], $adminToken, 20);
    $reopenedBatchStatus = (string)$pdo->query('SELECT status FROM monthly_disbursements WHERE id=' . $testBatchCreatedId)->fetchColumn();
    mobileApiExpect(
        $reopenBatch['status'] === 200
        && ($reopenBatch['json']['data']['status'] ?? '') === 'transferred'
        && $reopenedBatchStatus === 'transferred',
        'reopening a returned batch restores transferred status'
    );
    $voidBatch = mobileApiRequest('/api/v1/admin/disbursements_action.php', 'POST', [
        'action'=>'void_batch','batch_id'=>$testBatchCreatedId,'reason'=>'Disposable integration void',
    ], $adminToken, 20);
    $voidedBatch = $pdo->prepare('SELECT status,reversal_journal_id,transaction_id FROM monthly_disbursements WHERE id=?');
    $voidedBatch->execute([$testBatchCreatedId]);
    $voidedRow = $voidedBatch->fetch(PDO::FETCH_ASSOC);
    $voidReversalId = (int)($voidedRow['reversal_journal_id'] ?? 0);
    $voidReversalTotals = $pdo->query(
        'SELECT COALESCE(SUM(debit),0) debit_total,COALESCE(SUM(credit),0) credit_total FROM journal_lines WHERE entry_id=' . $voidReversalId
    )->fetch(PDO::FETCH_ASSOC);
    $voidedTransactionStatus = (string)$pdo->query('SELECT status FROM transactions WHERE id=' . (int)($voidedRow['transaction_id'] ?? 0))->fetchColumn();
    mobileApiExpect(
        $voidBatch['status'] === 200
        && (string)($voidedRow['status'] ?? '') === 'voided'
        && $voidReversalId > 0
        && $voidedTransactionStatus === 'voided'
        && abs((float)($voidReversalTotals['debit_total'] ?? 0) - (float)($voidReversalTotals['credit_total'] ?? 0)) < 0.005,
        'voiding a transferred batch reverses the posted journal, voids its transaction and preserves a balanced reversal'
    );
    $duplicateVoid = mobileApiRequest('/api/v1/admin/disbursements_action.php', 'POST', [
        'action'=>'void_batch','batch_id'=>$testBatchCreatedId,'reason'=>'Duplicate void should be rejected',
    ], $adminToken, 20);
    mobileApiExpect($duplicateVoid['status'] === 409, 'a voided batch cannot be voided a second time');

    $logoUnauth = mobileApiMultipartRequest('/api/v1/admin/settings.php', [], 'site_logo', 'logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/f1sAAAAASUVORK5CYII=', true), 'image/png');
    mobileApiExpect($logoUnauth['status'] === 401, 'organization logo upload requires authentication');
    $oldLogoStmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
    $oldLogoStmt->execute(['site_logo']);
    $oldLogoRow = $oldLogoStmt->fetch(PDO::FETCH_ASSOC);
    $testLogoSettingExisted = is_array($oldLogoRow);
    $testPreviousLogoValue = $testLogoSettingExisted ? (string)$oldLogoRow['setting_value'] : null;
    $logoBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/f1sAAAAASUVORK5CYII=', true);
    $logoUpload = mobileApiMultipartRequest('/api/v1/admin/settings.php', [], 'site_logo', 'logo.png', $logoBytes, 'image/png', $adminToken, 20);
    $testLogoPath = (string)($logoUpload['json']['data']['values']['site_logo'] ?? '');
    $testLogoFile = dirname(__DIR__) . '/' . $testLogoPath;
    $storedLogo = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
    $storedLogo->execute(['site_logo']);
    mobileApiExpect(
        $logoUpload['status'] === 200
        && preg_match('/^assets\/uploads\/logo_[a-f0-9]{12}\.png$/', $testLogoPath) === 1
        && is_file($testLogoFile)
        && (string)$storedLogo->fetchColumn() === $testLogoPath,
        'administrator can upload a validated logo and update the organization logo setting'
    );
    $orphanFormChildId = (int)$pdo->query('SELECT id FROM family_children ORDER BY id LIMIT 1')->fetchColumn();
    $testOrphanFormChildId = $orphanFormChildId;
    $orphanChildSnapshotStmt = $pdo->prepare('SELECT nationality,health_status,health_status_other,psychological_state,psychological_state_other,guardian_name,guardian_relationship,updated_at FROM family_children WHERE id=?');
    $orphanChildSnapshotStmt->execute([$testOrphanFormChildId]);
    $testOrphanFormChildSnapshot = $orphanChildSnapshotStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $orphanFamilySnapshotStmt = $pdo->prepare('SELECT father_death_cause,mother_marital_status,updated_by,updated_at FROM families WHERE id=(SELECT family_id FROM family_children WHERE id=?)');
    $orphanFamilySnapshotStmt->execute([$testOrphanFormChildId]);
    $testOrphanFormFamilySnapshot = $orphanFamilySnapshotStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $orphanFormsList = mobileApiRequest('/api/v1/families/orphan_forms.php?q=' . rawurlencode((string)$pdo->query('SELECT child_name FROM family_children WHERE id=' . $testOrphanFormChildId)->fetchColumn()), 'GET', null, $adminToken, 20);
    mobileApiExpect(
        $orphanFormsList['status'] === 200
        && is_array($orphanFormsList['json']['data']['children'] ?? null)
        && in_array($testOrphanFormChildId, array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $orphanFormsList['json']['data']['children']), true),
        'administrator can search the native orphan forms index'
    );
    $orphanFormRead = mobileApiRequest('/api/v1/families/orphan_form.php?child_id=' . $orphanFormChildId, 'GET', null, $adminToken, 20);
    mobileApiExpect(
        $orphanFormChildId > 0 && $orphanFormRead['status'] === 200
        && (int)($orphanFormRead['json']['data']['child']['id'] ?? 0) === $orphanFormChildId
        && is_array($orphanFormRead['json']['data']['options']['health'] ?? null),
        'administrator can open native orphan form with schema-backed fields and dropdown options'
    );
    $readOnlyOrphanRole = $roles['accountant_staff'] ?? $roles['accountant'] ?? null;
    if ($readOnlyOrphanRole !== null) {
        $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$readOnlyOrphanRole, $userId]);
        $readOnlyOrphanLogin = mobileApiRequest('/api/v1/auth/login.php', 'POST', ['username' => $username, 'password' => $knownPassword]);
        mobileApiExpect($readOnlyOrphanLogin['status'] === 200 && isset($readOnlyOrphanLogin['json']['data']['access_token']), 'read-only accounting role login succeeds for orphan form integration');
        $readOnlyOrphanToken = (string)$readOnlyOrphanLogin['json']['data']['access_token'];
        $testTokenHashes[] = hash('sha256', $readOnlyOrphanToken);
        $readOnlyOrphanList = mobileApiRequest('/api/v1/families/orphan_forms.php', 'GET', null, $readOnlyOrphanToken);
        mobileApiExpect($readOnlyOrphanList['status'] === 200 && ($readOnlyOrphanList['json']['data']['read_only'] ?? false) === true, 'read-only accounting role can browse orphan forms');
        $readOnlyOrphanRead = mobileApiRequest('/api/v1/families/orphan_form.php?child_id=' . $orphanFormChildId, 'GET', null, $readOnlyOrphanToken);
        mobileApiExpect($readOnlyOrphanRead['status'] === 200 && ($readOnlyOrphanRead['json']['data']['can_edit'] ?? true) === false, 'read-only accounting role can view but cannot edit orphan form details');
        $readOnlySponsorships = $readOnlyOrphanRead['json']['data']['sponsorships'] ?? [];
        mobileApiExpect(
            is_array($readOnlySponsorships)
            && !array_filter($readOnlySponsorships, static fn(array $item): bool => array_key_exists('monthly_amount', $item)),
            'read-only accounting role does not receive sponsorship amounts hidden by the web role guard'
        );
        $readOnlyOrphanSave = mobileApiRequest('/api/v1/families/orphan_form.php?child_id=' . $orphanFormChildId, 'POST', ['values' => ['health_status' => (string)($testOrphanFormChildSnapshot['health_status'] ?? 'سليم')]], $readOnlyOrphanToken);
        mobileApiExpect($readOnlyOrphanSave['status'] === 403, 'read-only accounting role cannot edit orphan form fields');
    }
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['admin'], $userId]);

    $invalidOrphanForm = mobileApiRequest('/api/v1/families/orphan_form.php?child_id=' . $orphanFormChildId, 'POST', ['values' => ['health_status' => 'not-a-valid-health-option']], $adminToken);
    mobileApiExpect($invalidOrphanForm['status'] === 422, 'native orphan form rejects unsupported health options');
    $orphanSnapshot = $testOrphanFormChildSnapshot ?? [];
    $orphanFamilySnapshot = $testOrphanFormFamilySnapshot ?? [];
    $savedOrphanForm = mobileApiRequest('/api/v1/families/orphan_form.php?child_id=' . $testOrphanFormChildId, 'POST', ['values' => [
        'nationality' => (string)($orphanSnapshot['nationality'] ?? 'سودانية'),
        'health_status' => (string)($orphanSnapshot['health_status'] ?? 'سليم'),
        'health_status_other' => (string)($orphanSnapshot['health_status_other'] ?? ''),
        'psychological_state' => (string)($orphanSnapshot['psychological_state'] ?? 'سليم'),
        'psychological_state_other' => (string)($orphanSnapshot['psychological_state_other'] ?? ''),
        'guardian_name' => (string)($orphanSnapshot['guardian_name'] ?? ''),
        'guardian_relationship' => (string)($orphanSnapshot['guardian_relationship'] ?? ''),
        'father_death_cause' => (string)($orphanFamilySnapshot['father_death_cause'] ?? ''),
        'mother_marital_status' => (string)($orphanFamilySnapshot['mother_marital_status'] ?? 'أرملة'),
    ]], $adminToken, 20);
    mobileApiExpect(
        $savedOrphanForm['status'] === 200
        && (int)($savedOrphanForm['json']['data']['child']['id'] ?? 0) === $testOrphanFormChildId,
        'administrator can save the native orphan form through the existing family schema'
    );
    $financialReport = mobileApiRequest('/api/v1/reports/detail.php?report=financial&type=income_expense&from=2026-10-01&to=2026-10-31', 'GET', null, $adminToken, 20);
    mobileApiExpect($financialReport['status'] === 200 && is_array($financialReport['json']['data']['rows'] ?? null), 'administrator can load native financial report rows');
    foreach ([
        ['sponsorship', 'coverage'],
        ['operational', 'supervisor'],
        ['hr', 'headcount'],
    ] as [$reportKey, $reportType]) {
        $reportResult = mobileApiRequest(
            '/api/v1/reports/detail.php?report=' . $reportKey . '&type=' . $reportType . '&from=2026-10-01&to=2026-10-31',
            'GET',
            null,
            $adminToken,
            20
        );
        mobileApiExpect(
            $reportResult['status'] === 200
            && is_array($reportResult['json']['data']['rows'] ?? null)
            && is_array($reportResult['json']['data']['summary'] ?? null),
            'administrator can load native ' . $reportKey . ' report data'
        );
    }
    $invalidReportRange = mobileApiRequest('/api/v1/reports/detail.php?report=financial&type=income_expense&from=2026-11-01&to=2026-10-01', 'GET', null, $adminToken);
    mobileApiExpect($invalidReportRange['status'] === 422, 'native report rejects reversed date ranges');
    $policyDate = (new DateTimeImmutable('+30 days'))->format('Y-m-d');
    $createdAttendancePolicy = mobileApiRequest('/api/v1/hr/attendance_policy.php', 'POST', [
        'action' => 'create',
        'policy_name' => 'Mobile API disposable policy',
        'effective_from' => $policyDate,
        'working_start_time' => '07:00',
        'working_end_time' => '16:00',
        'working_days' => '1,2,3,4,5',
        'attendance_cutoff_time' => '16:00',
        'absence_finalization_time' => '15:30',
        'auto_login_attendance' => true,
        'auto_absence_enabled' => false,
        'default_work_mode' => 'remote',
        'notes' => 'Disposable CI policy',
    ], $adminToken);
    $testAttendancePolicyId = (int)($createdAttendancePolicy['json']['data']['id'] ?? 0);
    mobileApiExpect($createdAttendancePolicy['status'] === 200 && $testAttendancePolicyId > 0, 'administrator can create a future attendance policy version');
    $attendancePolicyRead = mobileApiRequest('/api/v1/hr/attendance_policy.php', 'GET', null, $adminToken);
    $policyItem = null;
    foreach (($attendancePolicyRead['json']['data']['policies'] ?? []) as $policy) {
        if ((int)($policy['id'] ?? 0) === $testAttendancePolicyId) $policyItem = $policy;
    }
    mobileApiExpect(
        $attendancePolicyRead['status'] === 200 && $policyItem
        && ($policyItem['working_days'] ?? '') === '1,2,3,4,5'
        && ($policyItem['auto_login_attendance'] ?? false) === true
        && ($policyItem['auto_absence_enabled'] ?? true) === false
        && ($policyItem['can_manage'] ?? false) === true,
        'attendance policy history returns weekly schedule, automation flags and future editability'
    );
    $updatedAttendancePolicy = mobileApiRequest('/api/v1/hr/attendance_policy.php', 'POST', [
        'action' => 'update', 'policy_id' => $testAttendancePolicyId,
        'policy_name' => 'Mobile API disposable policy updated',
        'effective_from' => $policyDate,
        'working_start_time' => '08:00',
        'working_end_time' => '16:00',
        'working_days' => '1,2,3,4,5,6',
        'attendance_cutoff_time' => '15:00',
        'absence_finalization_time' => '14:30',
        'auto_login_attendance' => false,
        'auto_absence_enabled' => true,
        'default_work_mode' => 'hybrid',
        'notes' => 'Updated disposable CI policy',
    ], $adminToken);
    mobileApiExpect($updatedAttendancePolicy['status'] === 200, 'administrator can update an editable future attendance policy');
    $invalidAttendancePolicy = mobileApiRequest('/api/v1/hr/attendance_policy.php', 'POST', [
        'action' => 'update', 'policy_id' => $testAttendancePolicyId,
        'policy_name' => 'Invalid policy', 'effective_from' => $policyDate,
        'working_start_time' => '16:00', 'working_end_time' => '07:00',
        'working_days' => '1,2,3', 'attendance_cutoff_time' => '16:00',
        'absence_finalization_time' => '16:00', 'default_work_mode' => 'remote',
        'auto_login_attendance' => true, 'auto_absence_enabled' => true,
    ], $adminToken);
    mobileApiExpect($invalidAttendancePolicy['status'] === 422, 'attendance policy rejects invalid work-time ordering without saving');
    $deletedAttendancePolicy = mobileApiRequest('/api/v1/hr/attendance_policy.php', 'POST', [
        'action' => 'delete', 'policy_id' => $testAttendancePolicyId,
    ], $adminToken);
    mobileApiExpect($deletedAttendancePolicy['status'] === 200, 'administrator can delete a future attendance policy version');
    $pdo->prepare('DELETE FROM hr_attendance_policy_versions WHERE id=?')->execute([$testAttendancePolicyId]);
    $testAttendancePolicyId = 0;

    $databaseIndex = mobileApiRequest('/api/v1/admin/database.php', 'GET', null, $adminToken, 20);
    mobileApiExpect(
        $databaseIndex['status'] === 200
        && ($databaseIndex['json']['ok'] ?? false) === true
        && is_array($databaseIndex['json']['data']['tables'] ?? null)
        && count($databaseIndex['json']['data']['tables']) > 0,
        'administrator can list real database tables and row counts'
    );
    $backupList = mobileApiRequest('/api/v1/admin/backups.php', 'GET', null, $adminToken, 20);
    mobileApiExpect(
        $backupList['status'] === 200
        && is_array($backupList['json']['data']['backups'] ?? null),
        'administrator can list available database backup files'
    );
    $createdBackup = mobileApiRequest('/api/v1/admin/backups.php', 'POST', ['action' => 'create'], $adminToken, 45);
    $testBackupName = (string)($createdBackup['json']['data']['backup']['filename'] ?? '');
    $backupPath = dirname(__DIR__) . '/storage/backups/' . $testBackupName;
    mobileApiExpect(
        $createdBackup['status'] === 201
        && preg_match('/^backup_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}\\.sql$/', $testBackupName) === 1
        && is_file($backupPath)
        && filesize($backupPath) > 500,
        'administrator can create a real SQL backup on the disposable database'
    );
    $downloadedBackup = mobileApiRequest('/api/v1/admin/backups.php?download=' . rawurlencode($testBackupName), 'GET', null, $adminToken, 30);
    mobileApiExpect(
        $downloadedBackup['status'] === 200
        && strlen($downloadedBackup['raw']) > 500
        && str_contains($downloadedBackup['raw'], 'CREATE TABLE')
        && preg_match('/^-- (?:MariaDB|MySQL) dump/m', ltrim($downloadedBackup['raw'])) === 1,
        'administrator can download a clean SQL dump through the authenticated API'
    );
    $deletedBackup = mobileApiRequest('/api/v1/admin/backups.php', 'POST', ['action' => 'delete', 'filename' => $testBackupName], $adminToken, 20);
    mobileApiExpect(
        $deletedBackup['status'] === 200,
        'administrator backup delete API returns success (HTTP ' . $deletedBackup['status'] . '; response ' . json_encode($deletedBackup['json']) . ')'
    );
    clearstatcache(true, $backupPath);
    mobileApiExpect(
        !is_file($backupPath),
        'administrator can delete a selected backup file after the API action'
    );

    $finaDashboard = mobileApiRequest('/api/v1/fina/dashboard.php', 'GET', null, $adminToken, 20);
    mobileApiExpect(
        $finaDashboard['status'] === 200
        && is_array($finaDashboard['json']['data'] ?? null)
        && array_key_exists('pending_count', $finaDashboard['json']['data'])
        && array_key_exists('approved_amount', $finaDashboard['json']['data']),
        'administrator can load the native Fina dashboard summary'
    );
    $employmentStates = mobileApiRequest('/api/v1/hr/employment_states.php', 'GET', null, $adminToken, 20);
    mobileApiExpect(
        $employmentStates['status'] === 200
        && is_array($employmentStates['json']['data']['states'] ?? null)
        && is_array($employmentStates['json']['data']['employees'] ?? null)
        && is_array($employmentStates['json']['data']['metrics'] ?? null)
        && ($employmentStates['json']['data']['read_only'] ?? false) === true,
        'administrator can load the read-only employment state catalog and employee history'
    );

    $payrollIntegrity = mobileApiRequest('/api/v1/hr/payroll_integrity.php', 'GET', null, $adminToken, 30);
    mobileApiExpect(
        $payrollIntegrity['status'] === 200
        && ($payrollIntegrity['json']['ok'] ?? false) === true
        && count($payrollIntegrity['json']['data']['checks'] ?? []) === 8
        && (int)($payrollIntegrity['json']['data']['total_checks'] ?? 0) === 8
        && ($payrollIntegrity['json']['data']['read_only'] ?? false) === true,
        'administrator can run all eight read-only payroll integrity checks, including reversal audit, against the full schema'
    );

    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['general_manager'], $userId]);
    $gmLogin = mobileApiRequest('/api/v1/auth/login.php', 'POST', ['username' => $username, 'password' => $knownPassword]);
    mobileApiExpect($gmLogin['status'] === 200 && isset($gmLogin['json']['data']['access_token']), 'general manager login succeeds for payroll period history integration');
    $gmToken = (string)$gmLogin['json']['data']['access_token'];
    $testTokenHashes[] = hash('sha256', $gmToken);
    $gmHistory = mobileApiRequest('/api/v1/hr/gm_payroll_period_history.php', 'GET', null, $gmToken, 30);
    mobileApiExpect($gmHistory['status'] === 200 && is_array($gmHistory['json']['data']['periods'] ?? null) && ($gmHistory['json']['data']['read_only'] ?? false) === true, 'general manager can load read-only payroll period history');
    $gmReport = mobileApiRequest('/api/v1/hr/gm_payroll_period_report.php?month=10&year=2026', 'GET', null, $gmToken, 30);
    mobileApiExpect($gmReport['status'] === 200 && ($gmReport['json']['data']['period'] ?? '') === '2026-10' && is_array($gmReport['json']['data']['employees'] ?? null) && ($gmReport['json']['data']['read_only'] ?? false) === true, 'general manager can load the read-only payroll period report');
    $gmInvalidPeriod = mobileApiRequest('/api/v1/hr/gm_payroll_period_report.php?month=13&year=2026', 'GET', null, $gmToken);
    mobileApiExpect($gmInvalidPeriod['status'] === 422, 'payroll period report rejects an invalid month');
    $gmDeniedIntegrity = mobileApiRequest('/api/v1/hr/payroll_integrity.php', 'GET', null, $gmToken);
    mobileApiExpect($gmDeniedIntegrity['status'] === 403, 'general manager cannot access HR-manager-only payroll integrity diagnostics');
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['admin'], $userId]);

    $databaseUsers = mobileApiRequest('/api/v1/admin/database.php?table=users', 'GET', null, $adminToken, 20);
    $databaseColumnNames = array_map(
        static fn(array $column): string => (string)($column['name'] ?? ''),
        $databaseUsers['json']['data']['columns'] ?? []
    );
    mobileApiExpect(
        $databaseUsers['status'] === 200
        && ($databaseUsers['json']['data']['selected_table'] ?? '') === 'users'
        && in_array('username', $databaseColumnNames, true)
        && is_array($databaseUsers['json']['data']['rows'] ?? null),
        'administrator can inspect actual table columns and recent rows'
    );

    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['nanny'], $userId]);
    $nannyFinaLogin = mobileApiRequest('/api/v1/auth/login.php', 'POST', ['username' => $username, 'password' => $knownPassword]);
    mobileApiExpect($nannyFinaLogin['status'] === 200 && isset($nannyFinaLogin['json']['data']['access_token']), 'nanny login succeeds for Fina role-denial integration');
    $nannyFinaToken = (string)$nannyFinaLogin['json']['data']['access_token'];
    $testTokenHashes[] = hash('sha256', $nannyFinaToken);
    $nannyAttendancePolicy = mobileApiRequest('/api/v1/hr/attendance_policy.php', 'GET', null, $nannyFinaToken);
    mobileApiExpect($nannyAttendancePolicy['status'] === 403, 'nanny cannot read or manage attendance policy');
    $nannyCreateFina = mobileApiRequest('/api/v1/fina/collections.php', 'POST', [
        'source_type' => 'person', 'source_name' => 'Nanny denied fixture',
        'amount' => '10.00', 'method' => 'cash', 'date' => date('Y-m-d'),
    ], $nannyFinaToken);
    mobileApiExpect($nannyCreateFina['status'] === 403, 'nanny cannot create Fina collections');
    $nannyFinaSponsors = mobileApiRequest('/api/v1/fina/sponsors.php?q=Test', 'GET', null, $nannyFinaToken);
    mobileApiExpect($nannyFinaSponsors['status'] === 403, 'nanny cannot use Fina sponsor lookup');
    $nannyFinaReview = mobileApiRequest('/api/v1/fina/review.php', 'GET', null, $nannyFinaToken);
    mobileApiExpect($nannyFinaReview['status'] === 403, 'nanny cannot view Fina review/history');
    $nannyFinaReceipt = mobileApiRequest('/api/v1/fina/receipt.php?id=1', 'GET', null, $nannyFinaToken);
    mobileApiExpect($nannyFinaReceipt['status'] === 403, 'nanny cannot access Fina receipt delivery');
    $nannyConfirmWithoutReceipt = mobileApiRequest('/api/v1/admin/disbursements_action.php', 'POST', [
        'action' => 'confirm_item', 'item_id' => 1,
    ], $nannyFinaToken);
    mobileApiExpect(
        $nannyConfirmWithoutReceipt['status'] === 422
        && ($nannyConfirmWithoutReceipt['json']['error']['code'] ?? '') === 'receipt_required',
        'nanny item confirmation requires a receipt before any state change'
    );
    $nannyDisbursements = mobileApiRequest('/api/v1/admin/disbursements.php', 'GET', null, $nannyFinaToken);
    mobileApiExpect(
        $nannyDisbursements['status'] === 200
        && ($nannyDisbursements['json']['data']['capabilities']['can_confirm_items'] ?? false) === true
        && is_array($nannyDisbursements['json']['data']['items'] ?? null)
        && array_reduce(
            $nannyDisbursements['json']['data']['items'],
            static fn(bool $ok, array $item): bool => $ok && (int)($item['nanny_id'] ?? 0) === $userId,
            true
        ),
        'nanny can access monthly disbursements but only their own batches'
    );

    $nannyFinaDashboard = mobileApiRequest('/api/v1/fina/dashboard.php', 'GET', null, $nannyFinaToken);
    mobileApiExpect($nannyFinaDashboard['status'] === 403, 'nanny cannot access the Fina dashboard');
    $nannyFinancialReport = mobileApiRequest('/api/v1/reports/detail.php?report=financial&type=income_expense&from=2026-10-01&to=2026-10-31', 'GET', null, $nannyFinaToken);
    mobileApiExpect($nannyFinancialReport['status'] === 403, 'nanny cannot access financial reports');
    $nannyLogoUpload = mobileApiMultipartRequest('/api/v1/admin/settings.php', [], 'site_logo', 'logo.png', $logoBytes, 'image/png', $nannyFinaToken);
    mobileApiExpect($nannyLogoUpload['status'] === 403, 'nanny cannot upload the organization logo');
    $nannyEmploymentStates = mobileApiRequest('/api/v1/hr/employment_states.php', 'GET', null, $nannyFinaToken);
    mobileApiExpect($nannyEmploymentStates['status'] === 403, 'nanny cannot access employment state records');
    $nannyPayrollIntegrity = mobileApiRequest('/api/v1/hr/payroll_integrity.php', 'GET', null, $nannyFinaToken);
    mobileApiExpect($nannyPayrollIntegrity['status'] === 403, 'nanny cannot access payroll integrity checks');
    $nannyDatabase = mobileApiRequest('/api/v1/admin/database.php', 'GET', null, $nannyFinaToken);
    mobileApiExpect($nannyDatabase['status'] === 403, 'nanny cannot access the administrator database browser');
    $nannyBackupList = mobileApiRequest('/api/v1/admin/backups.php', 'GET', null, $nannyFinaToken);
    mobileApiExpect($nannyBackupList['status'] === 403, 'nanny cannot list database backups');
    $nannyBackupCreate = mobileApiRequest('/api/v1/admin/backups.php', 'POST', ['action' => 'create'], $nannyFinaToken);
    mobileApiExpect($nannyBackupCreate['status'] === 403, 'nanny cannot create database backups');
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['admin'], $userId]);

    $unauthenticatedFina = mobileApiRequest('/api/v1/fina/collections.php', 'POST', [
        'source_type' => 'person', 'source_name' => 'Unauthenticated fixture',
        'amount' => '10.00', 'method' => 'cash', 'date' => date('Y-m-d'),
    ]);
    mobileApiExpect($unauthenticatedFina['status'] === 401, 'Fina collection creation requires bearer authentication');

    $invalidFina = mobileApiRequest('/api/v1/fina/collections.php', 'POST', [
        'source_type' => 'person', 'source_name' => 'Invalid amount fixture',
        'amount' => '0', 'method' => 'cash', 'date' => date('Y-m-d'),
    ], $adminToken);
    mobileApiExpect(
        $invalidFina['status'] === 422 && ($invalidFina['json']['error']['code'] ?? '') === 'invalid_amount',
        'Fina collection creation rejects zero amounts before writing data'
    );

    $finaName = 'Mobile Fina API fixture ' . bin2hex(random_bytes(5));
    $createdFina = mobileApiRequest('/api/v1/fina/collections.php', 'POST', [
        'source_type' => 'person',
        'source_name' => $finaName,
        'amount' => '125.50',
        'method' => 'cash',
        'date' => date('Y-m-d'),
        'purpose_note' => 'Disposable integration fixture',
        'description' => 'Must remain pending until reviewed',
    ], $adminToken, 20);
    $testFinaCollectionId = (int)($createdFina['json']['data']['id'] ?? 0);
    $finaRow = null;
    if ($testBackupName !== '') {
        @unlink(dirname(__DIR__) . '/storage/backups/' . basename($testBackupName));
        $pdo->prepare("DELETE FROM audit_log WHERE entity_type IN ('database','backup') AND new_values LIKE ?")
            ->execute(['%' . $testBackupName . '%']);
    }
    if ($testFinaCollectionId > 0) {
        $stmt = $pdo->prepare('SELECT fina_source_id,status,amount,accounting_journal_id,created_by FROM fina_collections WHERE id=?');
        $stmt->execute([$testFinaCollectionId]);
        $finaRow = $stmt->fetch();
        $testFinaSourceId = (int)($finaRow['fina_source_id'] ?? 0);
    }
    mobileApiExpect(
        $createdFina['status'] === 201
        && $testFinaCollectionId > 0
        && $finaRow
        && $finaRow['status'] === 'pending'
        && (float)$finaRow['amount'] === 125.50
        && $finaRow['accounting_journal_id'] === null
        && (int)$finaRow['created_by'] === $userId,
        'administrator can register a Fina collection as pending without posting a journal'
    );
    $finaJournalCount = $pdo->prepare("SELECT COUNT(*) FROM journal_entries WHERE reference_type='fina_collection' AND reference_id=?");
    $finaJournalCount->execute([$testFinaCollectionId]);
    mobileApiExpect((int)$finaJournalCount->fetchColumn() === 0, 'Fina registration creates no accounting journal entry');

    $finaQueue = mobileApiRequest('/api/v1/fina/review.php', 'GET', null, $adminToken, 20);
    $foundFinaPending = false;
    foreach (($finaQueue['json']['data']['pending'] ?? []) as $item) {
        if ((int)($item['id'] ?? 0) === $testFinaCollectionId) $foundFinaPending = true;
    }
    mobileApiExpect(
        $finaQueue['status'] === 200
        && ($finaQueue['json']['data']['can_review'] ?? false) === true
        && $foundFinaPending,
        'Fina pending review queue exposes the newly registered collection'
    );

    $selfApprove = mobileApiRequest('/api/v1/fina/review.php', 'POST', [
        'id' => $testFinaCollectionId, 'action' => 'approve',
    ], $adminToken);
    $pendingAfterSelfApprove = $pdo->prepare('SELECT status,accounting_journal_id FROM fina_collections WHERE id=?');
    $pendingAfterSelfApprove->execute([$testFinaCollectionId]);
    $afterSelfApprove = $pendingAfterSelfApprove->fetch();
    mobileApiExpect(
        $selfApprove['status'] === 422
        && $afterSelfApprove
        && $afterSelfApprove['status'] === 'pending'
        && $afterSelfApprove['accounting_journal_id'] === null,
        'Fina creator cannot approve their own collection and no journal is posted'
    );

    $finance = mobileApiRequest('/api/v1/admin/finance_overview.php', 'GET', null, $adminToken, 20);
    $financeData = $finance['json']['data'] ?? [];
    mobileApiExpect(
        $finance['status'] === 200
        && ($finance['json']['ok'] ?? false) === true
        && isset($financeData['period']['start'], $financeData['period']['end'])
        && isset($financeData['treasury']['cash'], $financeData['treasury']['bank'], $financeData['treasury']['wallet'], $financeData['treasury']['total'])
        && isset($financeData['monthly_flow']['income_total'], $financeData['monthly_flow']['outgoing_total'])
        && isset($financeData['admin_fees']['admin_fees_total'], $financeData['admin_fees']['admin_fees_month'])
        && isset($financeData['disbursements']['pending_approval'], $financeData['disbursements']['received'], $financeData['disbursements']['pending_amount'])
        && isset($financeData['quick_stats']['active_sponsorships'], $financeData['quick_stats']['active_families'], $financeData['quick_stats']['active_nannies']),
        'administrator finance overview executes full-schema queries and returns its documented metric groups'
    );

    $accountList = mobileApiRequest('/api/v1/admin/accounts.php', 'GET', null, $adminToken, 20);
    mobileApiExpect(
        $accountList['status'] === 200
        && is_array($accountList['json']['data']['items'] ?? null)
        && count($accountList['json']['data']['items']) > 0
        && isset($accountList['json']['data']['items'][0]['balance'], $accountList['json']['data']['items'][0]['account_type']),
        'native chart of accounts lists existing accounts with posted-ledger balances'
    );
    $accountCode = (string)random_int(100000, 999999);
    $createAccount = mobileApiRequest('/api/v1/admin/accounts_action.php', 'POST', [
        'action' => 'create',
        'code' => $accountCode,
        'name_ar' => 'حساب اختبار API المحمول',
        'name_en' => 'Mobile API chart test',
        'account_type' => 'expense',
    ], $adminToken, 20);
    $testAccountId = (int)($createAccount['json']['data']['id'] ?? 0);
    mobileApiExpect(
        $createAccount['status'] === 201 && $testAccountId > 0
        && (string)($createAccount['json']['data']['code'] ?? '') === $accountCode,
        'administrator can create a chart account with validated fields'
    );
    $toggleAccount = mobileApiRequest('/api/v1/admin/accounts_action.php', 'POST', [
        'action' => 'toggle_status',
        'id' => $testAccountId,
    ], $adminToken, 20);
    mobileApiExpect(
        $toggleAccount['status'] === 200
        && ($toggleAccount['json']['data']['is_active'] ?? null) === 0,
        'administrator can toggle chart-account status without changing ledger entries'
    );
    $listedAccount = mobileApiRequest('/api/v1/admin/accounts.php', 'GET', null, $adminToken, 20);
    $accountItem = null;
    foreach (($listedAccount['json']['data']['items'] ?? []) as $candidate) {
        if ((int)($candidate['id'] ?? 0) === $testAccountId) { $accountItem = $candidate; break; }
    }
    mobileApiExpect(
        $listedAccount['status'] === 200 && is_array($accountItem)
        && (int)($accountItem['is_active'] ?? 1) === 0
        && (float)($accountItem['balance'] ?? -1) === 0.0,
        'chart account list reflects status change and zero balance'
    );

    $sudoRoleId = (int)$pdo->query("SELECT id FROM roles WHERE code='sudo' LIMIT 1")->fetchColumn();
    if ($sudoRoleId > 0) {
        $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$sudoRoleId, $userId]);
        $sudoLogin = mobileApiRequest('/api/v1/auth/login.php', 'POST', ['username' => $username, 'password' => $knownPassword]);
        mobileApiExpect($sudoLogin['status'] === 200 && isset($sudoLogin['json']['data']['access_token']), 'sudo login succeeds for exact-role denial test');
        $sudoToken = (string)$sudoLogin['json']['data']['access_token'];
        $testTokenHashes[] = hash('sha256', $sudoToken);
        $deniedFina = mobileApiRequest('/api/v1/fina/collections.php', 'POST', [
            'source_type' => 'person', 'source_name' => 'Sudo denied fixture',
            'amount' => '10.00', 'method' => 'cash', 'date' => date('Y-m-d'),
        ], $sudoToken);
        mobileApiExpect(
            $deniedFina['status'] === 403 && ($deniedFina['json']['error']['code'] ?? '') === 'forbidden',
            'Sudo cannot create Fina collections when the existing API policy permits only admin and financial_manager'
        );
        $deniedFinance = mobileApiRequest('/api/v1/admin/finance_overview.php', 'GET', null, $sudoToken, 20);
        mobileApiExpect(
            $deniedFinance['status'] === 403 && ($deniedFinance['json']['error']['code'] ?? '') === 'forbidden',
            'finance overview rejects Sudo despite normalized administrator dashboard routing'
        );
    }
    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['nanny'], $userId]);

    $handoffCountBefore = (int)$pdo->query('SELECT COUNT(*) FROM mobile_web_handoffs')->fetchColumn();
    $r = mobileApiRequest('/api/v1/auth/web_handoff.php', 'POST', ['path' => 'https://example.com'], $token);
    mobileApiExpect(
        $r['status'] === 410 && ($r['json']['error']['code'] ?? '') === 'native_only'
        && !isset($r['json']['data']['handoff_token']),
        'website handoff API is disabled even for external redirect targets'
    );

    $r = mobileApiRequest('/api/v1/auth/web_handoff.php', 'POST', [
        'path' => 'modules/users/profile.php',
        'query' => ['source' => 'mobile'],
    ], $token);
    mobileApiExpect(
        $r['status'] === 410 && ($r['json']['error']['code'] ?? '') === 'native_only'
        && !isset($r['json']['data']['handoff_token']),
        'website handoff API does not issue tokens for internal pages'
    );
    $handoffCountAfter = (int)$pdo->query('SELECT COUNT(*) FROM mobile_web_handoffs')->fetchColumn();
    mobileApiExpect($handoffCountAfter === $handoffCountBefore, 'disabled handoff API creates no handoff records');

    $r = mobileApiRequest('/mobile_web_handoff.php?token=' . str_repeat('a', 64), 'GET', null, null, 5, false);
    $location = '';
    $setCookie = '';
    foreach (($r['headers'] ?? []) as $header) {
        if (stripos($header, 'Location:') === 0) $location = trim(substr($header, strlen('Location:')));
        if (stripos($header, 'Set-Cookie: AhlElKheirSession=') === 0) $setCookie = $header;
    }
    mobileApiExpect(
        $r['status'] === 410 && $location === '' && $setCookie === '',
        'retired website route never redirects or establishes a browser session'
    );

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

    $pdo->prepare(
        "INSERT INTO monthly_disbursements
         (month,nanny_id,group_id,total_amount,status,expense_account_code,created_by,submitted_by,submitted_at)
         VALUES (?, ?, NULL, 500.00, 'transferred', '5110', ?, ?, NOW())"
    )->execute([date('Y-m'), $userId, $userId, $userId]);
    $testDisbursementId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO disbursement_items
         (disbursement_id,family_id,amount,status,is_orphan_verified,is_mother_contact_verified,is_bank_verified)
         VALUES (?, ?, 500.00, 'pending', 1, 1, 1)"
    )->execute([$testDisbursementId, $testNannyFamilyId]);
    $testDisbursementItemId = (int)$pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO disbursement_items
         (disbursement_id,family_id,amount,status,is_orphan_verified,is_mother_contact_verified,is_bank_verified)
         VALUES (?, ?, 250.00, 'pending', 1, 1, 1)"
    )->execute([$testDisbursementId, $testForeignNannyFamilyId]);
    $testDisbursementReturnItemId = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE monthly_disbursements SET total_amount=750.00 WHERE id=?')->execute([$testDisbursementId]);

    $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$roles['nanny'], $userId]);
    $receiptNannyLogin = mobileApiRequest('/api/v1/auth/login.php', 'POST', ['username' => $username, 'password' => $knownPassword]);
    mobileApiExpect($receiptNannyLogin['status'] === 200 && isset($receiptNannyLogin['json']['data']['access_token']), 'nanny session is available for receipt-confirmation integration');
    $receiptNannyToken = (string)$receiptNannyLogin['json']['data']['access_token'];
    $testTokenHashes[] = hash('sha256', $receiptNannyToken);
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true);
    if (!is_string($pngBytes) || $pngBytes === '') throw new RuntimeException('Could not prepare the in-memory PNG receipt fixture.');
    $confirmReceipt = mobileApiMultipartRequest(
        '/api/v1/admin/disbursements_action.php',
        ['action' => 'confirm_item', 'item_id' => (string)$testDisbursementItemId],
        'receipt_file',
        'receipt.png',
        $pngBytes,
        'image/png',
        $receiptNannyToken
    );
    $confirmedItem = $pdo->prepare('SELECT status, receipt_file_path FROM disbursement_items WHERE id=?');
    $confirmedItem->execute([$testDisbursementItemId]);
    $confirmedRow = $confirmedItem->fetch(PDO::FETCH_ASSOC);
    $receiptStoredPath = (string)($confirmedRow['receipt_file_path'] ?? '');
    mobileApiExpect(
        $confirmReceipt['status'] === 200
        && ($confirmReceipt['json']['data']['status'] ?? '') === 'paid'
        && (string)($confirmedRow['status'] ?? '') === 'paid'
        && str_starts_with($receiptStoredPath, 'storage/receipts/family/')
        && is_file(dirname(__DIR__) . '/' . $receiptStoredPath),
        'nanny receipt upload confirms the assigned pending family item and persists its receipt'
    );
    $duplicateConfirm = mobileApiMultipartRequest(
        '/api/v1/admin/disbursements_action.php',
        ['action' => 'confirm_item', 'item_id' => (string)$testDisbursementItemId],
        'receipt_file',
        'receipt.png',
        $pngBytes,
        'image/png',
        $receiptNannyToken
    );
    mobileApiExpect(
        $duplicateConfirm['status'] === 409
        && (string)($pdo->query('SELECT status FROM disbursement_items WHERE id=' . $testDisbursementItemId)->fetchColumn()) === 'paid',
        'repeated nanny receipt confirmation is rejected without changing the paid item'
    );
    $closeWithReturn = mobileApiMultipartRequest(
        '/api/v1/admin/disbursements_action.php',
        [
            'action' => 'close_with_return',
            'batch_id' => (string)$testDisbursementId,
            'return_reason_type' => 'سبب آخر',
            'return_reason_details' => 'Disposable integration return',
        ],
        'return_receipt',
        'return.png',
        $pngBytes,
        'image/png',
        $receiptNannyToken
    );
    $returnedBatchLookup = $pdo->prepare('SELECT status,returned_amount,return_receipt_file_path,reversal_journal_id FROM monthly_disbursements WHERE id=?');
    $returnedBatchLookup->execute([$testDisbursementId]);
    $returnedBatchRow = $returnedBatchLookup->fetch(PDO::FETCH_ASSOC);
    $returnedItemLookup = $pdo->prepare('SELECT status,return_reason FROM disbursement_items WHERE id=?');
    $returnedItemLookup->execute([$testDisbursementReturnItemId]);
    $returnedItemRow = $returnedItemLookup->fetch(PDO::FETCH_ASSOC);
    $returnJournalId = (int)($returnedBatchRow['reversal_journal_id'] ?? 0);
    $returnJournalTotals = $pdo->query(
        'SELECT COALESCE(SUM(debit),0) debit_total, COALESCE(SUM(credit),0) credit_total FROM journal_lines WHERE entry_id=' . $returnJournalId
    )->fetch(PDO::FETCH_ASSOC);
    $returnReceiptPath = (string)($returnedBatchRow['return_receipt_file_path'] ?? '');
    mobileApiExpect(
        $closeWithReturn['status'] === 200
        && ($closeWithReturn['json']['data']['status'] ?? '') === 'returned'
        && (string)($returnedBatchRow['status'] ?? '') === 'returned'
        && abs((float)($returnedBatchRow['returned_amount'] ?? 0) - 250.0) < 0.005
        && (string)($returnedItemRow['status'] ?? '') === 'returned'
        && $returnJournalId > 0
        && abs((float)($returnJournalTotals['debit_total'] ?? 0) - (float)($returnJournalTotals['credit_total'] ?? 0)) < 0.005
        && str_starts_with($returnReceiptPath, 'storage/receipts/returns/')
        && is_file(dirname(__DIR__) . '/' . $returnReceiptPath),
        'nanny closes a partially paid batch with a required return receipt and a balanced cash/expense reversal'
    );

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
    mobileApiExpect($r['status'] === 401, 'expired sessions are rejected (HTTP ' . $r['status'] . ', code=' . ($r['json']['error']['code'] ?? 'none') . ')');
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
    if ($testDraftPayrollId > 0) $pdo->prepare('DELETE FROM payroll WHERE id=?')->execute([$testDraftPayrollId]);
    if ($testLegacyPayrollId > 0) {
        $legacyProofLookup = $pdo->prepare('SELECT storage_key FROM hr_payroll_payment_proofs WHERE payroll_id=?');
        $legacyProofLookup->execute([$testLegacyPayrollId]);
        $storedLegacyProofKey = (string)($legacyProofLookup->fetchColumn() ?: $testLegacyPayrollProofStorageKey);
        if ($storedLegacyProofKey !== '' && preg_match('/^[a-f0-9]{64}\\.(pdf|jpg|png)$/', $storedLegacyProofKey)) {
            $legacyProofPath = dirname(__DIR__) . '/storage/private/payroll-proofs/' . $storedLegacyProofKey;
            if (is_file($legacyProofPath)) @unlink($legacyProofPath);
        }
        $pdo->prepare('DELETE FROM hr_payroll_payment_proofs WHERE payroll_id=?')->execute([$testLegacyPayrollId]);
        $pdo->prepare('UPDATE payroll SET accounting_entry_id=NULL WHERE id=?')->execute([$testLegacyPayrollId]);
        $pdo->prepare('DELETE FROM journal_lines WHERE entry_id=?')->execute([$testLegacyPayrollJournalId]);
        $pdo->prepare('DELETE FROM journal_entries WHERE id=?')->execute([$testLegacyPayrollJournalId]);
        $pdo->prepare('DELETE FROM payroll WHERE id=?')->execute([$testLegacyPayrollId]);
    }
    if ($testPayrollId > 0) {
        $payrollProofLookup = $pdo->prepare('SELECT storage_key FROM hr_payroll_payment_proofs WHERE payroll_id=?');
        $payrollProofLookup->execute([$testPayrollId]);
        $storedPayrollProofKey = (string)($payrollProofLookup->fetchColumn() ?: $testPayrollProofStorageKey);
        if ($storedPayrollProofKey !== '' && preg_match('/^[a-f0-9]{64}\\.(pdf|jpg|png)$/', $storedPayrollProofKey)) {
            $proofPath = dirname(__DIR__) . '/storage/private/payroll-proofs/' . $storedPayrollProofKey;
            if (is_file($proofPath)) @unlink($proofPath);
        }
        $pdo->prepare("DELETE FROM notifications WHERE created_at>=? AND (link LIKE ? OR link LIKE ?)")
            ->execute([$testStart, '%payroll_statement.php?id=' . $testPayrollId, '%gm_payroll_period_report.php?month=' . $testPayrollMonth . '&year=' . $testPayrollYear]);
        $pdo->prepare('DELETE FROM hr_payroll_payment_proofs WHERE payroll_id=?')->execute([$testPayrollId]);
        $pdo->prepare("UPDATE payroll SET accounting_entry_id=NULL,accounting_status='ready',status='approved' WHERE id=?")->execute([$testPayrollId]);
        $journalIds = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type='payroll' AND reference_id=?");
        $journalIds->execute([$testPayrollId]);
        foreach ($journalIds->fetchAll(PDO::FETCH_COLUMN) as $journalId) {
            $pdo->prepare('DELETE FROM journal_lines WHERE entry_id=?')->execute([(int)$journalId]);
            $pdo->prepare('DELETE FROM journal_entries WHERE id=?')->execute([(int)$journalId]);
        }
        $pdo->prepare('DELETE FROM payroll WHERE id=?')->execute([$testPayrollId]);
    }
    if ($testAttendancePolicyId > 0) $pdo->prepare('DELETE FROM hr_attendance_policy_versions WHERE id=?')->execute([$testAttendancePolicyId]);
    if ($testOrphanFormChildId > 0 && is_array($testOrphanFormChildSnapshot) && is_array($testOrphanFormFamilySnapshot)) {
        $pdo->prepare('UPDATE family_children SET nationality=?,health_status=?,health_status_other=?,psychological_state=?,psychological_state_other=?,guardian_name=?,guardian_relationship=?,updated_at=? WHERE id=?')
            ->execute([
                $testOrphanFormChildSnapshot['nationality'],$testOrphanFormChildSnapshot['health_status'],$testOrphanFormChildSnapshot['health_status_other'],
                $testOrphanFormChildSnapshot['psychological_state'],$testOrphanFormChildSnapshot['psychological_state_other'],$testOrphanFormChildSnapshot['guardian_name'],
                $testOrphanFormChildSnapshot['guardian_relationship'],$testOrphanFormChildSnapshot['updated_at'],$testOrphanFormChildId,
            ]);
        $orphanFamilyId = (int)$pdo->query('SELECT family_id FROM family_children WHERE id=' . $testOrphanFormChildId)->fetchColumn();
        $pdo->prepare('UPDATE families SET father_death_cause=?,mother_marital_status=?,updated_by=?,updated_at=? WHERE id=?')
            ->execute([
                $testOrphanFormFamilySnapshot['father_death_cause'],$testOrphanFormFamilySnapshot['mother_marital_status'],
                $testOrphanFormFamilySnapshot['updated_by'],$testOrphanFormFamilySnapshot['updated_at'],$orphanFamilyId,
            ]);
        $pdo->prepare("DELETE FROM audit_log WHERE entity_type='orphan_form' AND entity_id=? AND created_at>=?")
            ->execute([$testOrphanFormChildId,$testStart]);
    }
    if ($testLogoPath !== '') {
        @unlink(dirname(__DIR__) . '/' . $testLogoPath);
        $pdo->prepare("DELETE FROM audit_log WHERE entity_type='settings' AND new_values LIKE ? AND created_at>=?")
            ->execute(['%' . $testLogoPath . '%', $testStart]);
    }
    if ($testLogoSettingExisted) {
        $pdo->prepare('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')
            ->execute(['site_logo', (string)$testPreviousLogoValue]);
    } else {
        $pdo->prepare('DELETE FROM settings WHERE setting_key=?')->execute(['site_logo']);
    }
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
    if ($testBatchCreatedId > 0 || $testBatchGroupId > 0) {
        if ($testBatchCreatedId < 1 && $testBatchGroupId > 0) {
            $lookupBatch = $pdo->prepare('SELECT id FROM monthly_disbursements WHERE group_id=? AND month=? AND created_by=? ORDER BY id DESC LIMIT 1');
            $lookupBatch->execute([$testBatchGroupId,$testBatchVerificationMonth,$userId]);
            $testBatchCreatedId = (int)$lookupBatch->fetchColumn();
        }
        if ($testBatchCreatedId > 0) {
            $batchReceiptLookup = $pdo->prepare('SELECT receipt_file_path,transaction_id FROM monthly_disbursements WHERE id=?');
            $batchReceiptLookup->execute([$testBatchCreatedId]);
            $batchCleanupRow = $batchReceiptLookup->fetch(PDO::FETCH_ASSOC) ?: [];
            $batchReceiptPath = (string)($batchCleanupRow['receipt_file_path'] ?? '');
            if (str_starts_with($batchReceiptPath, 'storage/receipts/')) {
                $batchReceiptFile = dirname(__DIR__) . '/' . $batchReceiptPath;
                if (is_file($batchReceiptFile)) @unlink($batchReceiptFile);
            }
            $journalIds = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type IN ('disbursement','disbursement_void') AND reference_id=?");
            $journalIds->execute([$testBatchCreatedId]);
            foreach ($journalIds->fetchAll(PDO::FETCH_COLUMN) as $journalId) {
                $pdo->prepare('DELETE FROM journal_lines WHERE entry_id=?')->execute([(int)$journalId]);
                $pdo->prepare('DELETE FROM journal_entries WHERE id=?')->execute([(int)$journalId]);
            }
            $transactionId = (int)($batchCleanupRow['transaction_id'] ?? 0);
            if ($transactionId > 0) $pdo->prepare('DELETE FROM transactions WHERE id=?')->execute([$transactionId]);
            $pdo->prepare("DELETE FROM audit_log WHERE entity_type='monthly_disbursements' AND entity_id=? AND action IN ('DISBURSEMENT_CREATE','DISBURSEMENT_TRANSFER','DISBURSEMENT_REOPEN_ITEM','DISBURSEMENT_REOPEN_BATCH','DISBURSEMENT_VOID') AND created_at>=?")
                ->execute([$testBatchCreatedId,$testStart]);
            $pdo->prepare("DELETE FROM notifications WHERE title IN ('تم تحويل دفعة الكفالة وأصبحت متاحة للتنفيذ','تم إعادة فتح دفعة الكفالة','تم إعادة فتح سجل أسرة في دفعة الكفالة','تم إبطال دفعة الكفالة') AND created_at>=?")
                ->execute([$testStart]);
            $pdo->prepare('DELETE FROM disbursement_items WHERE disbursement_id=?')->execute([$testBatchCreatedId]);
            $pdo->prepare('DELETE FROM monthly_disbursements WHERE id=?')->execute([$testBatchCreatedId]);
        }
    }
    if ($testBatchVerificationFamilyId > 0) {
        $pdo->prepare('DELETE FROM nanny_family_verifications WHERE family_id=? AND month=? AND nanny_id=?')
            ->execute([$testBatchVerificationFamilyId,$testBatchVerificationMonth,$testBatchNannyId]);
    }
    if ($testDisbursementItemId > 0) {
        $receiptLookup = $pdo->prepare('SELECT receipt_file_path FROM disbursement_items WHERE id=?');
        $receiptLookup->execute([$testDisbursementItemId]);
        $storedReceipt = (string)($receiptLookup->fetchColumn() ?: '');
        if (str_starts_with($storedReceipt, 'storage/receipts/family/')) {
            $receiptFile = dirname(__DIR__) . '/' . $storedReceipt;
            if (is_file($receiptFile)) @unlink($receiptFile);
        }
        if ($testDisbursementId > 0) {
            $batchReturnLookup = $pdo->prepare('SELECT return_receipt_file_path FROM monthly_disbursements WHERE id=?');
            $batchReturnLookup->execute([$testDisbursementId]);
            $returnStoredPath = (string)($batchReturnLookup->fetchColumn() ?: '');
            if (str_starts_with($returnStoredPath, 'storage/receipts/returns/')) {
                $returnStoredFile = dirname(__DIR__) . '/' . $returnStoredPath;
                if (is_file($returnStoredFile)) @unlink($returnStoredFile);
            }
            $pdo->prepare("DELETE FROM audit_log WHERE action IN ('DISBURSEMENT_CONFIRM_ITEM','DISBURSEMENT_CLOSE_WITH_RETURN') AND entity_type='monthly_disbursements' AND entity_id=? AND created_at>=?")
                ->execute([$testDisbursementId, $testStart]);
            $pdo->prepare('DELETE FROM disbursement_items WHERE disbursement_id=?')->execute([$testDisbursementId]);
            $returnJournalLookup = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type='disbursement_return' AND reference_id=?");
            $returnJournalLookup->execute([$testDisbursementId]);
            foreach ($returnJournalLookup->fetchAll(PDO::FETCH_COLUMN) as $returnJournalId) {
                $pdo->prepare('DELETE FROM journal_lines WHERE entry_id=?')->execute([(int)$returnJournalId]);
                $pdo->prepare('DELETE FROM journal_entries WHERE id=?')->execute([(int)$returnJournalId]);
            }
            $pdo->prepare("DELETE FROM notifications WHERE title='تم إرجاع دفعة كفالة إلى الصندوق' AND created_at>=?")
                ->execute([$testStart]);
        }
    }
    if ($testDisbursementId > 0) {
        $pdo->prepare('DELETE FROM monthly_disbursements WHERE id=?')->execute([$testDisbursementId]);
    }
    if ($testNannyFamilyId > 0) {
        $pdo->prepare('DELETE FROM families WHERE id=?')->execute([$testNannyFamilyId]);
    }
    if ($testForeignNannyFamilyId > 0) {
        $pdo->prepare('DELETE FROM families WHERE id=?')->execute([$testForeignNannyFamilyId]);
    }
    if ($testFinaCollectionId > 0) {
        $pdo->prepare("DELETE FROM notifications WHERE title='تحصيل فينا الخير بانتظار المراجعة' AND created_at>=?")
            ->execute([$testStart]);
        $pdo->prepare('DELETE FROM fina_collections WHERE id=?')->execute([$testFinaCollectionId]);
    }
    if ($testFinaSourceId > 0) {
        $pdo->prepare('DELETE FROM fina_sources WHERE id=?')->execute([$testFinaSourceId]);
    }
    if ($testSponsorId > 0) {
        $pdo->prepare('DELETE FROM sponsors WHERE id=?')->execute([$testSponsorId]);
    }
    if ($testAccountId > 0) {
        $pdo->prepare('DELETE FROM accounts WHERE id=?')->execute([$testAccountId]);
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
        if ($testArchiveDocumentId > 0) {
            $pdo->prepare('DELETE FROM document_archive_events WHERE document_id=?')->execute([$testArchiveDocumentId]);
            $pdo->prepare('DELETE FROM document_archive_source_links WHERE document_id=?')->execute([$testArchiveDocumentId]);
            $pdo->prepare('DELETE FROM document_archive_versions WHERE document_id=?')->execute([$testArchiveDocumentId]);
            $pdo->prepare('DELETE FROM document_archive_documents WHERE id=?')->execute([$testArchiveDocumentId]);
        }
        if ($testArchiveBlobId > 0) $pdo->prepare('DELETE FROM document_archive_blobs WHERE id=?')->execute([$testArchiveBlobId]);
        if ($testArchiveSourceId > 0) $pdo->prepare('DELETE FROM project_documents WHERE id=? AND project_id=?')->execute([$testArchiveSourceId, $testProjectId]);
        if ($testArchiveAbsolutePath !== '' && is_file($testArchiveAbsolutePath)) @unlink($testArchiveAbsolutePath);
        if ($testArchiveDirectory !== '' && is_dir($testArchiveDirectory)) @rmdir($testArchiveDirectory);
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
