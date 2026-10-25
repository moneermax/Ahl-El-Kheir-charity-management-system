<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/v1/_bootstrap.php';
require_once dirname(__DIR__) . '/api/v1/age_alerts/suspension_service.php';
require_once dirname(__DIR__) . '/api/v1/suspensions/case_service.php';

$pdo = db();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$actor = dbFetchOne(
    "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
     WHERE r.code IN ('admin', 'sudo', 'super_admin') ORDER BY u.id LIMIT 1"
);
$nanny = dbFetchOne(
    "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
     WHERE r.code = 'nanny' ORDER BY u.id LIMIT 1"
);
$candidate = dbFetchOne(
    "SELECT fc.id AS child_id, fc.family_id, fc.birth_date, fc.is_active,
            fc.disqualified_at, fc.disqualification_reason, f.nanny_id
     FROM family_children fc
     JOIN families f ON f.id = fc.family_id
     JOIN sponsorship_children sc ON sc.family_child_id = fc.id
     JOIN sponsorships sp ON sp.id = sc.sponsorship_id AND sp.status = 'active'
     WHERE fc.is_active = 1
       AND NOT EXISTS (
           SELECT 1 FROM suspension_cases sx
           WHERE sx.entity_type = 'child' AND sx.entity_id = fc.id AND sx.status = 'active'
       )
     ORDER BY fc.id
     LIMIT 1"
);
$assert($actor !== null, 'A system-admin fixture account is required.');
$assert($nanny !== null, 'A nanny fixture account is required.');
$assert($candidate !== null, 'An active child with an active linked sponsorship is required.');

$childId = (int)$candidate['child_id'];
$familyId = (int)$candidate['family_id'];
$actorId = (int)$actor['id'];
$nannyId = (int)$nanny['id'];
$otherUser = dbFetchOne('SELECT id FROM users WHERE id <> ? AND id <> ? ORDER BY id LIMIT 1', [$nannyId, (int)($candidate['nanny_id'] ?? 0)]);
$assert($otherUser !== null, 'A second account is required for the nanny scope test.');
$wrongNannyId = (int)$otherUser['id'];
$activeBefore = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM sponsorship_children sc
     JOIN sponsorships sp ON sp.id = sc.sponsorship_id
     WHERE sc.family_child_id = ? AND sp.status = 'active'",
    [$childId]
)['c'] ?? 0);
$assert($activeBefore > 0, 'The chosen child must have at least one active sponsorship.');

$pdo->beginTransaction();
try {
    dbExecute('UPDATE families SET nanny_id = ? WHERE id = ?', [$nannyId, $familyId]);

    // Put the fixture inside the exact age-alert window without changing the real database:
    // every mutation below is rolled back before this test exits.
    dbExecute(
        "UPDATE family_children
         SET birth_date = DATE_SUB(CURDATE(), INTERVAL 18 YEAR),
             is_active = 1, disqualified_at = NULL, disqualification_reason = NULL
         WHERE id = ?",
        [$childId]
    );

    $result = ak_api_suspend_legal_age_child($actorId, 'admin', $childId, 'CI rollback fixture');
    $caseId = (int)$result['case_id'];
    $assert($caseId > 0, 'Suspension case ID was not created.');
    $assert((int)$result['sponsorships_paused'] === $activeBefore, 'Not all active linked sponsorships were paused.');

    $case = dbFetchOne(
        "SELECT id, reason, status FROM suspension_cases WHERE id = ? AND entity_type = 'child' AND entity_id = ?",
        [$caseId, $childId]
    );
    $assert($case !== null && $case['reason'] === 'age_out' && $case['status'] === 'active', 'Suspension case fields do not match the web workflow.');

    $adminCases = ak_api_list_suspension_cases($actorId, 'admin');
    $adminCaseIds = array_map(static fn(array $item): int => (int)$item['id'], $adminCases['items']);
    $assert(in_array($caseId, $adminCaseIds, true), 'Administrator case list omitted the active suspension.');
    $assert($adminCases['can_lift'] === true, 'Administrator case list did not expose its lift capability.');

    $nannyCases = ak_api_list_suspension_cases($nannyId, 'nanny');
    $nannyCaseIds = array_map(static fn(array $item): int => (int)$item['id'], $nannyCases['items']);
    $assert(in_array($caseId, $nannyCaseIds, true), 'Nanny case list omitted a case from its assigned family.');
    $assert($nannyCases['can_lift'] === false, 'Nanny case list incorrectly exposed lift capability.');

    $child = dbFetchOne(
        'SELECT is_active, disqualified_at, disqualification_reason FROM family_children WHERE id = ?',
        [$childId]
    );
    $assert($child !== null && (int)$child['is_active'] === 0, 'Child was not marked inactive.');
    $assert((string)$child['disqualified_at'] === date('Y-m-d'), 'Child disqualification date is incorrect.');
    $assert((string)$child['disqualification_reason'] === 'age_out', 'Child disqualification reason is incorrect.');

    $activeAfter = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM sponsorship_children sc
         JOIN sponsorships sp ON sp.id = sc.sponsorship_id
         WHERE sc.family_child_id = ? AND sp.status = 'active'",
        [$childId]
    )['c'] ?? 0);
    $assert($activeAfter === 0, 'An active linked sponsorship remained after suspension.');

    $duplicateRejected = false;
    try {
        ak_api_suspend_legal_age_child($actorId, 'admin', $childId, '');
    } catch (DomainException $e) {
        $duplicateRejected = true;
    }
    $assert($duplicateRejected, 'A duplicate active suspension was not rejected.');

    $scopeRejected = false;
    try {
        ak_api_suspend_legal_age_child($wrongNannyId, 'nanny', $childId, '');
    } catch (AkLegalAgeSuspensionForbidden $e) {
        $scopeRejected = true;
    }
    $assert($scopeRejected, 'A nanny outside the assigned family scope was not rejected.');

    $gmRejected = false;
    try {
        ak_api_suspend_legal_age_child($actorId, 'general_manager', $childId, '');
    } catch (AkLegalAgeSuspensionForbidden $e) {
        $gmRejected = true;
    }
    $assert($gmRejected, 'General Manager was incorrectly permitted to initiate suspension.');

    $liftForbidden = false;
    try {
        ak_api_lift_suspension_case($nannyId, 'nanny', $caseId);
    } catch (AkSuspensionLiftForbidden $e) {
        $liftForbidden = true;
    }
    $assert($liftForbidden, 'Nanny was incorrectly permitted to lift a suspension.');

    $lifted = ak_api_lift_suspension_case($actorId, 'admin', $caseId);
    $assert((int)$lifted['case_id'] === $caseId, 'Lift response did not return the case ID.');
    $assert((int)$lifted['sponsorships_reactivated'] === $activeBefore, 'Lifting did not resume every sponsorship paused by the case.');

    $liftedCase = dbFetchOne(
        "SELECT status, lifted_by FROM suspension_cases WHERE id = ?",
        [$caseId]
    );
    $assert($liftedCase !== null && $liftedCase['status'] === 'lifted', 'Suspension case was not marked lifted.');
    $assert($liftedCase !== null && (int)$liftedCase['lifted_by'] === $actorId, 'Lift actor was not recorded.');

    $restoredChildInTransaction = dbFetchOne(
        'SELECT is_active, disqualified_at, disqualification_reason FROM family_children WHERE id = ?',
        [$childId]
    );
    $assert($restoredChildInTransaction !== null && (int)$restoredChildInTransaction['is_active'] === 1, 'Lifting did not reactivate the child.');
    $assert($restoredChildInTransaction !== null && $restoredChildInTransaction['disqualified_at'] === null, 'Lifting did not clear the disqualification date.');
    $assert($restoredChildInTransaction !== null && $restoredChildInTransaction['disqualification_reason'] === null, 'Lifting did not clear the disqualification reason.');

    $activeAfterLift = (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM sponsorship_children sc
         JOIN sponsorships sp ON sp.id = sc.sponsorship_id
         WHERE sc.family_child_id = ? AND sp.status = 'active'",
        [$childId]
    )['c'] ?? 0);
    $assert($activeAfterLift === $activeBefore, 'Linked sponsorships were not fully restored by lifting.');

    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$restoredChild = dbFetchOne(
    'SELECT birth_date, is_active, disqualified_at, disqualification_reason FROM family_children WHERE id = ?',
    [$childId]
);
$restoredFamily = dbFetchOne('SELECT nanny_id FROM families WHERE id = ?', [$familyId]);
$restoredActive = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM sponsorship_children sc
     JOIN sponsorships sp ON sp.id = sc.sponsorship_id
     WHERE sc.family_child_id = ? AND sp.status = 'active'",
    [$childId]
)['c'] ?? 0);
$remainingCase = dbFetchOne(
    "SELECT id FROM suspension_cases WHERE entity_type = 'child' AND entity_id = ? AND status = 'active' LIMIT 1",
    [$childId]
);
$assert($restoredChild !== null && $restoredChild['birth_date'] === $candidate['birth_date'], 'Rollback did not restore the original birth date.');
$assert($restoredChild !== null && (int)$restoredChild['is_active'] === (int)$candidate['is_active'], 'Rollback did not restore the original child status.');
$assert($restoredChild !== null && $restoredChild['disqualified_at'] === $candidate['disqualified_at'], 'Rollback did not restore the original disqualification date.');
$assert($restoredChild !== null && $restoredChild['disqualification_reason'] === $candidate['disqualification_reason'], 'Rollback did not restore the original disqualification reason.');
$assert($restoredFamily !== null && (int)($restoredFamily['nanny_id'] ?? 0) === (int)($candidate['nanny_id'] ?? 0), 'Rollback did not restore the family assignment.');
$assert($restoredActive === $activeBefore, 'Rollback did not restore linked sponsorship statuses.');
$assert($remainingCase === null, 'Rollback left an active suspension case behind.');

echo 'PASS | mobile legal-age suspension service | suspend/lift, linked sponsorship pause/resume, duplicate guard, role/scope guards, rollback' . PHP_EOL;
