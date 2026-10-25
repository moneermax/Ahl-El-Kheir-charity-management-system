<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/v1/sponsors/assignment_service.php';

$pdo = db();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$actor = dbFetchOne("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code IN ('admin','sudo','super_admin') ORDER BY u.id LIMIT 1");
$sponsor = dbFetchOne('SELECT id, supervisor_id FROM sponsors ORDER BY id LIMIT 1');
$assert($actor !== null, 'A system-admin actor is required.');
$assert($sponsor !== null, 'A sponsor fixture is required.');

$supervisor = dbFetchOne(
    "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id
     WHERE r.code='supervisor' AND u.is_active=1 AND u.id <> ?
     ORDER BY u.id LIMIT 1",
    [(int)($sponsor['supervisor_id'] ?? 0)]
);
if (!$supervisor) {
    $supervisor = dbFetchOne("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='supervisor' AND u.is_active=1 ORDER BY u.id LIMIT 1");
}
$assert($supervisor !== null, 'An active supervisor fixture is required.');

$options = ak_mobile_sponsor_assignment_options();
$assert(isset($options['unassigned_sponsors'], $options['active_supervisors']), 'Assignment options response is incomplete.');
$assert(is_array($options['unassigned_sponsors']) && is_array($options['active_supervisors']), 'Assignment options must be arrays.');

$sponsorId = (int)$sponsor['id'];
$supervisorId = (int)$supervisor['id'];
$actorId = (int)$actor['id'];
$original = dbFetchOne('SELECT supervisor_id, is_manual_override, assigned_by, assigned_at, updated_by FROM sponsors WHERE id=?', [$sponsorId]);
$originalHistory = (int)(dbFetchOne('SELECT COUNT(*) c FROM sponsor_supervisor_assignments WHERE sponsor_id=? AND ended_at IS NULL', [$sponsorId])['c'] ?? 0);

$pdo->beginTransaction();
try {
    $result = ak_mobile_assign_sponsor($sponsorId, $supervisorId, $actorId);
    $assert((int)$result['sponsor_id'] === $sponsorId && (int)$result['supervisor_id'] === $supervisorId, 'Assignment response does not match the request.');
    $saved = dbFetchOne('SELECT supervisor_id, is_manual_override, assigned_by, updated_by FROM sponsors WHERE id=?', [$sponsorId]);
    $assert($saved !== null && (int)$saved['supervisor_id'] === $supervisorId, 'Sponsor supervisor was not updated.');
    $assert((int)$saved['is_manual_override'] === 1, 'Manual assignment override was not recorded.');
    $assert((int)$saved['assigned_by'] === $actorId && (int)$saved['updated_by'] === $actorId, 'Assignment actor was not recorded.');
    $activeHistory = (int)(dbFetchOne('SELECT COUNT(*) c FROM sponsor_supervisor_assignments WHERE sponsor_id=? AND supervisor_id=? AND ended_at IS NULL', [$sponsorId, $supervisorId])['c'] ?? 0);
    $assert($activeHistory > 0, 'Assignment history row was not recorded.');

    $invalidRejected = false;
    try {
        ak_mobile_assign_sponsor($sponsorId, PHP_INT_MAX, $actorId);
    } catch (DomainException $e) {
        $invalidRejected = true;
    }
    $assert($invalidRejected, 'An inactive/nonexistent supervisor was not rejected.');
    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

$restored = dbFetchOne('SELECT supervisor_id, is_manual_override, assigned_by, assigned_at, updated_by FROM sponsors WHERE id=?', [$sponsorId]);
$restoredHistory = (int)(dbFetchOne('SELECT COUNT(*) c FROM sponsor_supervisor_assignments WHERE sponsor_id=? AND ended_at IS NULL', [$sponsorId])['c'] ?? 0);
$assert($restored !== null, 'Sponsor disappeared after rollback.');
$assert(($restored['supervisor_id'] ?? null) === ($original['supervisor_id'] ?? null), 'Sponsor supervisor was not restored by rollback.');
$assert((int)$restored['is_manual_override'] === (int)$original['is_manual_override'], 'Manual override flag was not restored by rollback.');
$assert(($restored['assigned_by'] ?? null) === ($original['assigned_by'] ?? null), 'Assignment actor was not restored by rollback.');
$assert(($restored['assigned_at'] ?? null) === ($original['assigned_at'] ?? null), 'Assignment timestamp was not restored by rollback.');
$assert(($restored['updated_by'] ?? null) === ($original['updated_by'] ?? null), 'Updated-by actor was not restored by rollback.');
$assert($restoredHistory === $originalHistory, 'Assignment history was not restored by rollback.');

echo 'PASS | mobile sponsor assignment service | sponsor=' . $sponsorId . ' | supervisor=' . $supervisorId . ' | rollback=PASS' . PHP_EOL;
