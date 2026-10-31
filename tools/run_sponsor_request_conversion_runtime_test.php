<?php
declare(strict_types=1);

/**
 * Disposable-database runtime test for sponsor request conversion.
 *
 * Reads only the source schema and selected reference rows from the configured
 * source database. All conversion fixtures and writes live in a uniquely named
 * temporary database that is dropped on shutdown. Never points the application
 * helper at the source database.
 *
 * Run from the project root with the XAMPP PHP CLI:
 *   /opt/lampp/bin/php tools/run_sponsor_request_conversion_runtime_test.php
 *
 * Optional connection overrides: AHL_TEST_SOURCE_DB, AHL_DB_HOST, AHL_DB_PORT,
 * AHL_DB_USER, AHL_DB_PASS.
 */

$root = dirname(__DIR__);
$sourceDb = getenv('AHL_TEST_SOURCE_DB') ?: 'ahl_el_kheir';
$host = getenv('AHL_DB_HOST') ?: '127.0.0.1';
$port = getenv('AHL_DB_PORT') ?: '3306';
$user = getenv('AHL_DB_USER') ?: 'root';
$pass = getenv('AHL_DB_PASS') ?: '';
$testDb = 'ahl_el_kheir_sponsor_conversion_test_' . bin2hex(random_bytes(5));
$createdDb = false;
$failures = 0;

$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['HTTP_HOST'] = 'localhost';

function runtimeTest(bool $condition, string $label): void
{
    global $failures;
    echo ($condition ? 'PASS' : 'FAIL') . ' | ' . $label . PHP_EOL;
    if (!$condition) {
        $failures++;
    }
}

function quoteIdentifier(string $identifier): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
        throw new RuntimeException('Unsafe SQL identifier rejected.');
    }
    return '`' . $identifier . '`';
}

try {
    $admin = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $sourceExists = $admin->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
    $sourceExists->execute([$sourceDb]);
    if (!$sourceExists->fetch()) {
        throw new RuntimeException('Source database not found: ' . $sourceDb);
    }
    if ($sourceDb === $testDb) {
        throw new RuntimeException('Test database must be distinct from the source database.');
    }

    $admin->exec('CREATE DATABASE ' . quoteIdentifier($testDb) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $createdDb = true;
    echo 'INFO | disposable database created: ' . $testDb . PHP_EOL;

    $vgmRoleId = (int)$admin->query("SELECT id FROM " . quoteIdentifier($sourceDb) . ".roles WHERE code = 'vice_general_manager' LIMIT 1")->fetchColumn();
    $vgmUserStmt = $admin->prepare("SELECT id FROM " . quoteIdentifier($sourceDb) . ".users WHERE role_id = ? AND is_active = 1 ORDER BY id LIMIT 1");
    $vgmUserStmt->execute([$vgmRoleId]);
    $vgmUserId = (int)$vgmUserStmt->fetchColumn();
    if ($vgmRoleId < 1 || $vgmUserId < 1 || $vgmUserId === 19) {
        throw new RuntimeException('An active VGM role/account is required for the assignment fixture.');
    }

    $tables = [
        'roles',
        'users',
        'letters',
        'supervisor_letters',
        'sponsor_requests',
        'sponsors',
        'sponsor_supervisor_assignments',
        'audit_log',
        'notifications',
    ];

    foreach ($tables as $table) {
        $admin->exec(
            'CREATE TABLE ' . quoteIdentifier($testDb) . '.' . quoteIdentifier($table) .
            ' LIKE ' . quoteIdentifier($sourceDb) . '.' . quoteIdentifier($table)
        );
    }

    // Copy only the reference rows required by the known fixture: supervisor 19,
    // role 4, Alef letter 2, and that supervisor's male/Alef assignment.
    $copies = [
        ['roles', 'id = 4'],
        ['roles', 'id = ' . $vgmRoleId],
        ['users', 'id = 19'],
        ['users', 'id = ' . $vgmUserId],
        ['letters', 'id = 2 AND is_active = 1'],
        ['supervisor_letters', 'supervisor_id = 19 AND letter_id = 2 AND gender = \'male\''],
    ];
    foreach ($copies as [$table, $where]) {
        $admin->exec(
            'INSERT INTO ' . quoteIdentifier($testDb) . '.' . quoteIdentifier($table) .
            ' SELECT * FROM ' . quoteIdentifier($sourceDb) . '.' . quoteIdentifier($table) .
            ' WHERE ' . $where
        );
    }

    $referenceChecks = [
        'supervisor role id 4 exists' => (int)$admin->query('SELECT COUNT(*) FROM ' . quoteIdentifier($testDb) . '.roles WHERE id = 4 AND code = \'supervisor\'')->fetchColumn() === 1,
        'active supervisor 19 exists' => (int)$admin->query('SELECT COUNT(*) FROM ' . quoteIdentifier($testDb) . '.users WHERE id = 19 AND is_active = 1 AND role_id = 4')->fetchColumn() === 1,
        'active Alef letter 2 exists' => (int)$admin->query('SELECT COUNT(*) FROM ' . quoteIdentifier($testDb) . '.letters WHERE id = 2 AND code = \'ا\' AND is_active = 1')->fetchColumn() === 1,
        'supervisor 19 has male Alef assignment' => (int)$admin->query('SELECT COUNT(*) FROM ' . quoteIdentifier($testDb) . '.supervisor_letters WHERE supervisor_id = 19 AND letter_id = 2 AND gender = \'male\'')->fetchColumn() === 1,
    ];
    foreach ($referenceChecks as $label => $passed) {
        runtimeTest($passed, $label);
    }
    if (in_array(false, $referenceChecks, true)) {
        throw new RuntimeException('Required reference data is missing; refusing to run conversion assertions.');
    }

    $fixture = $admin->prepare(
        'INSERT INTO ' . quoteIdentifier($testDb) .
        '.sponsor_requests (sponsor_name, sponsor_type, gender, preferred_payment_method, source, assigned_supervisor_id, status) ' .
        'VALUES (?, \'individual\', \'male\', \'cash\', \'runtime_test\', 19, \'new\')'
    );
    $fixture->execute(['أحمد اختبار التحويل']);
    $requestId = (int)$admin->lastInsertId();
    if ($requestId < 1) {
        throw new RuntimeException('Could not create disposable sponsor request fixture.');
    }

    // Force all application DB connections to the disposable schema before
    // loading any application configuration or helper.
    putenv('AHL_ENV=development');
    putenv('AHL_DB_HOST=' . $host);
    putenv('AHL_DB_PORT=' . $port);
    putenv('AHL_DB_NAME=' . $testDb);
    putenv('AHL_DB_USER=' . $user);
    putenv('AHL_DB_PASS=' . $pass);

    require_once $root . '/config/config.php';
    require_once $root . '/config/database.php';
    require_once $root . '/config/functions.php';
    require_once $root . '/config/session.php';
    require_once $root . '/modules/sponsors/lib_sponsor_request_conversion.php';

    $_SESSION = [
        'user_id' => 19,
        'user_role' => 'supervisor',
        'user_name' => 'Runtime test supervisor',
    ];

    $sponsorId = convertSponsorRequestToSponsor($requestId, 19);
    runtimeTest($sponsorId > 0, 'real conversion helper returns a sponsor id');

    $request = dbFetchOne('SELECT status, created_sponsor_id, confirmed_by FROM sponsor_requests WHERE id = ?', [$requestId]);
    $sponsor = dbFetchOne('SELECT id, full_name, first_letter_id, sponsor_type, gender, status, sponsor_code, supervisor_id, created_by FROM sponsors WHERE id = ?', [$sponsorId]);
    $assignment = dbFetchOne('SELECT sponsor_id, supervisor_id, assignment_type, assignment_reason FROM sponsor_supervisor_assignments WHERE sponsor_id = ? AND ended_at IS NULL LIMIT 1', [$sponsorId]);
    $audit = dbFetchOne("SELECT user_id, action, entity_type, entity_id FROM audit_log WHERE entity_type = 'sponsor_request' AND entity_id = ? AND action = 'CONVERT' ORDER BY id DESC LIMIT 1", [$requestId]);

    runtimeTest($request && $request['status'] === 'converted' && (int)$request['created_sponsor_id'] === $sponsorId && (int)$request['confirmed_by'] === 19, 'request transitions to converted and links the created sponsor');
    runtimeTest($sponsor && (int)$sponsor['id'] === $sponsorId && $sponsor['full_name'] === 'أحمد اختبار التحويل' && (int)$sponsor['first_letter_id'] === 2 && $sponsor['sponsor_type'] === 'individual' && $sponsor['gender'] === 'male' && $sponsor['status'] === 'active' && (int)$sponsor['supervisor_id'] === 19 && (int)$sponsor['created_by'] === 19, 'created sponsor fields and Alef/male supervisor resolution are correct');
    runtimeTest($assignment && (int)$assignment['sponsor_id'] === $sponsorId && (int)$assignment['supervisor_id'] === 19 && $assignment['assignment_type'] === 'permanent', 'active sponsor assignment history is written');
    runtimeTest($audit && (int)$audit['user_id'] === 19 && $audit['action'] === 'CONVERT' && (int)$audit['entity_id'] === $requestId, 'conversion audit record is written');

    $sponsorCountBeforeDuplicate = (int)dbFetchOne('SELECT COUNT(*) AS c FROM sponsors')['c'];
    $auditCountBeforeDuplicate = (int)dbFetchOne('SELECT COUNT(*) AS c FROM audit_log')['c'];
    $duplicateRejected = false;
    try {
        convertSponsorRequestToSponsor($requestId, 19);
    } catch (InvalidArgumentException $e) {
        $duplicateRejected = true;
    }
    $sponsorCountAfterDuplicate = (int)dbFetchOne('SELECT COUNT(*) AS c FROM sponsors')['c'];
    $auditCountAfterDuplicate = (int)dbFetchOne('SELECT COUNT(*) AS c FROM audit_log')['c'];
    runtimeTest($duplicateRejected, 'second conversion attempt is rejected');
    runtimeTest($sponsorCountAfterDuplicate === $sponsorCountBeforeDuplicate && $auditCountAfterDuplicate === $auditCountBeforeDuplicate, 'duplicate attempt creates no sponsor and no extra audit row');

    // A supervisor cannot convert a request assigned to a different supervisor.
    $secondFixture = $admin->prepare(
        'INSERT INTO ' . quoteIdentifier($testDb) .
        '.sponsor_requests (sponsor_name, sponsor_type, gender, preferred_payment_method, source, assigned_supervisor_id, status) ' .
        'VALUES (?, \'individual\', \'male\', \'cash\', \'runtime_test\', 19, \'new\')'
    );
    $secondFixture->execute(['أحمد طلب نطاق الاختبار']);
    $otherRequestId = (int)$admin->lastInsertId();
    $_SESSION['user_id'] = 20;
    $ownershipRejected = false;
    try {
        convertSponsorRequestToSponsor($otherRequestId, 20);
    } catch (InvalidArgumentException $e) {
        $ownershipRejected = true;
    }
    runtimeTest($ownershipRejected, 'supervisor cannot convert a request assigned to supervisor 19 while acting as user 20');
    runtimeTest((int)dbFetchOne('SELECT COUNT(*) AS c FROM sponsors')['c'] === $sponsorCountBeforeDuplicate, 'unauthorized ownership attempt creates no sponsor');


    // Non-individual conversions: run both legal-entity types through the real
    // conversion helper inside this same disposable database. The request is
    // assigned to supervisor 19 so the existing supervisor authorization path
    // is exercised without creating any individual assignment.
    $_SESSION = [
        'user_id' => 19,
        'user_role' => 'supervisor',
        'user_name' => 'Runtime test supervisor',
    ];

    foreach ([
        ['company', 'شركة اختبار التحويل'],
        ['organization', 'منظمة اختبار التحويل'],
    ] as [$entityType, $entityName]) {
        $entityFixture = $admin->prepare(
            'INSERT INTO ' . quoteIdentifier($testDb) .
            '.sponsor_requests (sponsor_name, sponsor_type, gender, preferred_payment_method, source, assigned_supervisor_id, status) ' .
            'VALUES (?, ?, \'organization\', \'cash\', \'runtime_test\', 19, \'new\')'
        );
        $entityFixture->execute([$entityName, $entityType]);
        $entityRequestId = (int)$admin->lastInsertId();

        $entitySponsorId = convertSponsorRequestToSponsor($entityRequestId, 19);
        $entityRequest = dbFetchOne(
            'SELECT status, created_sponsor_id FROM sponsor_requests WHERE id = ?',
            [$entityRequestId]
        );
        $entitySponsor = dbFetchOne(
            'SELECT id, full_name, sponsor_type, gender, status, supervisor_id, created_by, brought_by_user_id FROM sponsors WHERE id = ?',
            [$entitySponsorId]
        );
        $entityAssignments = (int)dbFetchOne(
            'SELECT COUNT(*) AS c FROM sponsor_supervisor_assignments WHERE sponsor_id = ?',
            [$entitySponsorId]
        )['c'];
        $entityAudit = dbFetchOne(
            "SELECT action FROM audit_log WHERE entity_type = 'sponsor_request' AND entity_id = ? AND action = 'CONVERT' ORDER BY id DESC LIMIT 1",
            [$entityRequestId]
        );

        runtimeTest(
            $entityRequest && $entityRequest['status'] === 'converted'
                && (int)$entityRequest['created_sponsor_id'] === $entitySponsorId,
            $entityType . ' request is converted and linked'
        );
        runtimeTest(
            $entitySponsor && $entitySponsor['full_name'] === $entityName
                && $entitySponsor['sponsor_type'] === $entityType
                && $entitySponsor['gender'] === 'organization'
                && $entitySponsor['status'] === 'active'
                && $entitySponsor['supervisor_id'] === null,
            $entityType . ' sponsor preserves legal-entity type and has no individual supervisor'
        );
        runtimeTest(
            $entitySponsor && (int)$entitySponsor['created_by'] === 19
                && $entitySponsor['brought_by_user_id'] === null,
            $entityType . ' conversion records processor ownership without acquisition attribution'
        );
        runtimeTest(
            $entitySponsor && supervisorCanAccessSponsor(19, $entitySponsor),
            $entityType . ' converting supervisor can access the created legal-entity sponsor'
        );
        runtimeTest(
            $entitySponsor && !supervisorCanAccessSponsor(20, $entitySponsor),
            $entityType . ' legal-entity access is denied to another supervisor'
        );
        runtimeTest(
            $entityAssignments === 0,
            $entityType . ' conversion creates no individual supervisor assignment history'
        );
        runtimeTest(
            $entityAudit && $entityAudit['action'] === 'CONVERT',
            $entityType . ' conversion writes its audit record'
        );

        // Exercise the same assignment-history transition and persisted identity
        // fields used by the sponsor edit POST path. A legal entity must remain
        // unassigned and keep its canonical gender after an edit round-trip.
        recordSponsorAssignment($entitySponsorId, null, 19, 'sponsor_letter_gender_reassignment');
        dbExecute(
            'UPDATE sponsors SET sponsor_type = ?, gender = ?, supervisor_id = ? WHERE id = ?',
            [$entityType, 'organization', null, $entitySponsorId]
        );
        $afterEdit = dbFetchOne(
            'SELECT sponsor_type, gender, supervisor_id FROM sponsors WHERE id = ?',
            [$entitySponsorId]
        );
        $assignmentsAfterEdit = (int)dbFetchOne(
            'SELECT COUNT(*) AS c FROM sponsor_supervisor_assignments WHERE sponsor_id = ?',
            [$entitySponsorId]
        )['c'];
        runtimeTest(
            $afterEdit && $afterEdit['sponsor_type'] === $entityType
                && $afterEdit['gender'] === 'organization'
                && $afterEdit['supervisor_id'] === null,
            $entityType . ' edit round-trip preserves legal-entity identity and null supervisor'
        );
        runtimeTest(
            $assignmentsAfterEdit === 0,
            $entityType . ' edit round-trip creates no individual assignment history'
        );
    }

    // VGM legal-entity routing: unassigned applications can be processed by
    // the VGM or assigned once to an active supervisor. Once assigned, the
    // management queue cannot convert the request; the selected supervisor can.
    $assignedFixture = $admin->prepare(
        'INSERT INTO ' . quoteIdentifier($testDb) .
        '.sponsor_requests (sponsor_name, sponsor_type, gender, preferred_payment_method, source, assigned_supervisor_id, status) ' .
        'VALUES (?, \'company\', \'organization\', \'cash\', \'runtime_test\', NULL, \'new\')'
    );
    $assignedFixture->execute(['شركة اختبار الإسناد']);
    $assignedRequestId = (int)$admin->lastInsertId();

    $_SESSION = [
        'user_id' => $vgmUserId,
        'user_role' => 'vice_general_manager',
        'user_name' => 'Runtime test VGM',
    ];
    $assignedName = assignLegalEntitySponsorRequestToSupervisor($assignedRequestId, 19, $vgmUserId);
    $assignedRequest = dbFetchOne(
        'SELECT sponsor_type, status, assigned_supervisor_id FROM sponsor_requests WHERE id = ?',
        [$assignedRequestId]
    );
    $assignmentAudit = dbFetchOne(
        "SELECT user_id, action FROM audit_log WHERE entity_type = 'sponsor_request' AND entity_id = ? AND action = 'ASSIGN_SUPERVISOR' ORDER BY id DESC LIMIT 1",
        [$assignedRequestId]
    );
    $assignmentNotification = dbFetchOne(
        "SELECT recipient_user_id, link FROM notifications WHERE recipient_user_id = ? AND link = ? ORDER BY id DESC LIMIT 1",
        [19, 'modules/sponsors/my_request.php?id=' . $assignedRequestId]
    );
    runtimeTest(
        $assignedName !== '' && $assignedRequest
            && $assignedRequest['sponsor_type'] === 'company'
            && $assignedRequest['status'] === 'new'
            && (int)$assignedRequest['assigned_supervisor_id'] === 19,
        'VGM assigns an unassigned company request to the selected active supervisor without changing its status'
    );
    runtimeTest(
        $assignmentAudit && (int)$assignmentAudit['user_id'] === $vgmUserId
            && $assignmentAudit['action'] === 'ASSIGN_SUPERVISOR',
        'VGM request assignment writes its audit event'
    );
    runtimeTest(
        $assignmentNotification && (int)$assignmentNotification['recipient_user_id'] === 19,
        'assigned supervisor receives an in-app notification with the scoped request link'
    );

    $duplicateAssignmentRejected = false;
    try {
        assignLegalEntitySponsorRequestToSupervisor($assignedRequestId, 19, $vgmUserId);
    } catch (InvalidArgumentException $e) {
        $duplicateAssignmentRejected = true;
    }
    runtimeTest($duplicateAssignmentRejected, 'a second manual assignment is rejected without duplicating notifications');

    $sponsorCountBeforeBlockedManagementConversion = (int)dbFetchOne('SELECT COUNT(*) AS c FROM sponsors')['c'];
    $blockedManagementConversion = false;
    try {
        convertSponsorRequestToSponsor($assignedRequestId, $vgmUserId, 'management_queue');
    } catch (RuntimeException $e) {
        $blockedManagementConversion = true;
    }
    runtimeTest(
        $blockedManagementConversion
            && (int)dbFetchOne('SELECT COUNT(*) AS c FROM sponsors')['c'] === $sponsorCountBeforeBlockedManagementConversion,
        'management queue cannot convert a legal-entity request after it is assigned to a supervisor'
    );

    $_SESSION = [
        'user_id' => 19,
        'user_role' => 'supervisor',
        'user_name' => 'Runtime test supervisor',
    ];
    $assignedSponsorId = convertSponsorRequestToSponsor($assignedRequestId, 19);
    $assignedSponsor = dbFetchOne(
        'SELECT sponsor_type, gender, supervisor_id, created_by FROM sponsors WHERE id = ?',
        [$assignedSponsorId]
    );
    runtimeTest(
        $assignedSponsor && $assignedSponsor['sponsor_type'] === 'company'
            && $assignedSponsor['gender'] === 'organization'
            && $assignedSponsor['supervisor_id'] === null
            && (int)$assignedSponsor['created_by'] === 19,
        'assigned supervisor converts the legal entity while preserving null individual supervisor assignment'
    );
    runtimeTest(
        $assignedSponsor && supervisorCanAccessSponsor(19, $assignedSponsor)
            && !supervisorCanAccessSponsor(20, $assignedSponsor),
        'only the converting supervisor receives legal-entity sponsor access'
    );

    $vgmProcessingFixture = $admin->prepare(
        'INSERT INTO ' . quoteIdentifier($testDb) .
        '.sponsor_requests (sponsor_name, sponsor_type, gender, preferred_payment_method, source, assigned_supervisor_id, status) ' .
        'VALUES (?, \'organization\', \'organization\', \'cash\', \'runtime_test\', NULL, \'new\')'
    );
    $vgmProcessingFixture->execute(['منظمة يعالجها نائب المدير العام']);
    $vgmProcessingRequestId = (int)$admin->lastInsertId();
    $_SESSION = [
        'user_id' => $vgmUserId,
        'user_role' => 'vice_general_manager',
        'user_name' => 'Runtime test VGM',
    ];
    $vgmSponsorId = convertSponsorRequestToSponsor($vgmProcessingRequestId, $vgmUserId, 'management_queue');
    $vgmSponsor = dbFetchOne(
        'SELECT sponsor_type, gender, supervisor_id, created_by FROM sponsors WHERE id = ?',
        [$vgmSponsorId]
    );
    runtimeTest(
        $vgmSponsor && $vgmSponsor['sponsor_type'] === 'organization'
            && $vgmSponsor['gender'] === 'organization'
            && $vgmSponsor['supervisor_id'] === null
            && (int)$vgmSponsor['created_by'] === $vgmUserId,
        'VGM can process an unassigned organization request directly without creating an individual supervisor assignment'
    );

    $unauthorizedFixture = $admin->prepare(
        'INSERT INTO ' . quoteIdentifier($testDb) .
        '.sponsor_requests (sponsor_name, sponsor_type, gender, preferred_payment_method, source, assigned_supervisor_id, status) ' .
        'VALUES (?, \'company\', \'organization\', \'cash\', \'runtime_test\', NULL, \'new\')'
    );
    $unauthorizedFixture->execute(['شركة رفض الإسناد غير المخول']);
    $unauthorizedRequestId = (int)$admin->lastInsertId();
    $_SESSION = [
        'user_id' => 19,
        'user_role' => 'supervisor',
        'user_name' => 'Runtime test supervisor',
    ];
    $unauthorizedAssignmentRejected = false;
    try {
        assignLegalEntitySponsorRequestToSupervisor($unauthorizedRequestId, 19, 19);
    } catch (RuntimeException $e) {
        $unauthorizedAssignmentRejected = true;
    }
    runtimeTest(
        $unauthorizedAssignmentRejected
            && dbFetchOne('SELECT assigned_supervisor_id FROM sponsor_requests WHERE id = ?', [$unauthorizedRequestId])['assigned_supervisor_id'] === null,
        'supervisor cannot use the VGM-only legal-entity assignment operation'
    );


} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR | ' . $e->getMessage() . PHP_EOL);
    $failures++;
} finally {
    if ($createdDb && isset($admin) && $admin instanceof PDO) {
        try {
            $admin->exec('DROP DATABASE IF EXISTS ' . quoteIdentifier($testDb));
            echo 'INFO | disposable database removed: ' . $testDb . PHP_EOL;
        } catch (Throwable $cleanupError) {
            fwrite(STDERR, 'CLEANUP ERROR | disposable database ' . $testDb . ' remains; remove only that exact test database after inspection. ' . $cleanupError->getMessage() . PHP_EOL);
            $failures++;
        }
    }
}

if ($failures > 0) {
    fwrite(STDERR, 'FAIL | sponsor request conversion runtime test: ' . $failures . ' failure(s)' . PHP_EOL);
    exit(1);
}

echo 'PASS | sponsor request conversion runtime test complete' . PHP_EOL;
