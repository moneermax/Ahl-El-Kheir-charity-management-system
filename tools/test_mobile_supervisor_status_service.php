<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/v1/supervisors/action_service.php';

$pdo = db();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$actor = dbFetchOne("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code IN ('admin','sudo','super_admin') ORDER BY u.id LIMIT 1");
$target = dbFetchOne(
    "SELECT u.id, u.is_active, u.legacy_status
     FROM users u JOIN roles r ON r.id=u.role_id
     WHERE r.code='supervisor' AND COALESCE(u.legacy_status,'') <> 'deleted'
     ORDER BY u.id LIMIT 1"
);
$assert($actor !== null, 'A system-admin actor is required.');
$assert($target !== null, 'A non-archived supervisor fixture is required.');
$actorId = (int)$actor['id'];
$supervisorId = (int)$target['id'];
$originalActive = (int)$target['is_active'];

$pdo->beginTransaction();
try {
    $result = ak_mobile_toggle_supervisor_status($supervisorId, $actorId, '127.0.0.1', 'CI rollback test');
    $expectedActive = $originalActive === 1 ? 0 : 1;
    $assert((int)$result['supervisor_id'] === $supervisorId, 'Supervisor response ID is incorrect.');
    $assert($result['is_active'] === ($expectedActive === 1), 'Supervisor response status is incorrect.');
    $saved = dbFetchOne('SELECT is_active FROM users WHERE id=?', [$supervisorId]);
    $assert($saved !== null && (int)$saved['is_active'] === $expectedActive, 'Supervisor status was not updated.');

    $invalidRejected = false;
    try {
        ak_mobile_toggle_supervisor_status($actorId, $actorId);
    } catch (DomainException $e) {
        $invalidRejected = true;
    }
    $assert($invalidRejected, 'A non-supervisor account was incorrectly accepted as the target.');
    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

$restored = dbFetchOne('SELECT is_active FROM users WHERE id=?', [$supervisorId]);
$assert($restored !== null && (int)$restored['is_active'] === $originalActive, 'Supervisor status was not restored by rollback.');

echo 'PASS | mobile supervisor status service | supervisor=' . $supervisorId . ' | rollback=PASS' . PHP_EOL;
