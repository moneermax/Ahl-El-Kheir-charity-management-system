<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
if ((string)$user['role_code'] !== 'nanny') {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Preliminary verification is limited to nanny accounts.']]);
}
$input = ak_api_read_json();
$action = trim((string)($input['action'] ?? ''));
$month = (string)($input['month'] ?? date('Y-m'));
if (!preg_match('/^([0-9]{4})-([0-9]{2})$/', $month, $parts) || !checkdate((int)$parts[2], 1, (int)$parts[1])) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_month', 'message' => 'Month must use YYYY-MM format.']]);
}
$nannyId = (int)$user['id'];
$flag = static fn(string $key): int => in_array($input[$key] ?? false, [true, 1, '1'], true) ? 1 : 0;
$canPreVerifyFamily = static function (int $familyId) use ($nannyId): bool {
    return dbFetchOne(
        "SELECT f.id FROM families f
         WHERE f.id = ? AND EXISTS (
             SELECT 1 FROM family_children fc
             INNER JOIN group_children gc ON gc.child_id = fc.id AND gc.left_date IS NULL
             INNER JOIN orphan_groups og ON og.id = gc.group_id AND og.is_active = 1
             INNER JOIN nanny_group_assignments nga ON nga.group_id = og.id AND nga.nanny_id = ? AND nga.end_date IS NULL
             WHERE fc.family_id = f.id AND fc.is_active = 1
         ) LIMIT 1",
        [$familyId, $nannyId]
    ) !== null;
};

try {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($action === 'verify_family') {
            $familyId = filter_var($input['family_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$familyId || $familyId < 1 || !$canPreVerifyFamily((int)$familyId)) {
                throw new DomainException('The family is outside your active-group preliminary verification scope.');
            }
            $orphan = $flag('is_orphan_verified');
            $mother = $flag('is_mother_contact_verified');
            $bank = $flag('is_bank_verified');
            $notes = trim((string)($input['verification_notes'] ?? ''));
            $existing = dbFetchOne(
                'SELECT id, is_orphan_verified, is_mother_contact_verified, is_bank_verified FROM nanny_family_verifications WHERE family_id=? AND month=? FOR UPDATE',
                [(int)$familyId, $month]
            );
            $oldOrphan = (int)($existing['is_orphan_verified'] ?? 0);
            $oldMother = (int)($existing['is_mother_contact_verified'] ?? 0);
            $oldBank = (int)($existing['is_bank_verified'] ?? 0);
            if ($existing) {
                dbExecute(
                    'UPDATE nanny_family_verifications SET nanny_id=?, is_orphan_verified=?, is_mother_contact_verified=?, is_bank_verified=?, verification_notes=?, verified_by_user_id=?, verified_at=NOW() WHERE id=?',
                    [$nannyId, $orphan, $mother, $bank, $notes, $nannyId, (int)$existing['id']]
                );
            } else {
                dbExecute(
                    'INSERT INTO nanny_family_verifications (family_id, month, nanny_id, is_orphan_verified, is_mother_contact_verified, is_bank_verified, verification_notes, verified_by_user_id, verified_at) VALUES (?,?,?,?,?,?,?,?,NOW())',
                    [(int)$familyId, $month, $nannyId, $orphan, $mother, $bank, $notes, $nannyId]
                );
            }
            dbExecute(
                'INSERT INTO verification_audit_log (family_id, month, nanny_id, old_orphan, old_mother, old_bank, new_orphan, new_mother, new_bank, changed_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())',
                [(int)$familyId, $month, $nannyId, $oldOrphan, $oldMother, $oldBank, $orphan, $mother, $bank]
            );
            $pdo->commit();
            ak_api_json(200, ['ok' => true, 'data' => ['action' => $action, 'family_id' => (int)$familyId, 'message' => 'Preliminary family verification saved.']]);
        }

        if ($action === 'bulk_verify') {
            $rawIds = $input['family_ids'] ?? [];
            if (!is_array($rawIds)) throw new DomainException('A list of family IDs is required.');
            $familyIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn(int $id): bool => $id > 0)));
            $operation = (string)($input['operation'] ?? '');
            if (!$familyIds) throw new DomainException('Select at least one family.');
            if (count($familyIds) > 500) throw new DomainException('A maximum of 500 families can be verified at once.');
            if (!in_array($operation, ['orphan', 'mother', 'bank', 'all'], true)) throw new DomainException('Unsupported bulk verification operation.');
            foreach ($familyIds as $familyId) {
                if (!$canPreVerifyFamily($familyId)) throw new DomainException('A selected family is outside your scope; no changes were saved.');
            }
            $recordStmt = $pdo->prepare('SELECT id, is_orphan_verified, is_mother_contact_verified, is_bank_verified FROM nanny_family_verifications WHERE family_id=? AND month=? FOR UPDATE');
            $insertStmt = $pdo->prepare('INSERT INTO nanny_family_verifications (family_id, month, nanny_id, is_orphan_verified, is_mother_contact_verified, is_bank_verified, verification_notes, verified_by_user_id, verified_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
            $updateStmt = $pdo->prepare('UPDATE nanny_family_verifications SET nanny_id=?, is_orphan_verified=?, is_mother_contact_verified=?, is_bank_verified=?, verified_by_user_id=?, verified_at=NOW() WHERE id=?');
            $auditStmt = $pdo->prepare('INSERT INTO verification_audit_log (family_id, month, nanny_id, old_orphan, old_mother, old_bank, new_orphan, new_mother, new_bank, changed_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
            foreach ($familyIds as $familyId) {
                $recordStmt->execute([$familyId, $month]);
                $record = $recordStmt->fetch(PDO::FETCH_ASSOC);
                $oldOrphan = (int)($record['is_orphan_verified'] ?? 0);
                $oldMother = (int)($record['is_mother_contact_verified'] ?? 0);
                $oldBank = (int)($record['is_bank_verified'] ?? 0);
                $newOrphan = in_array($operation, ['orphan', 'all'], true) ? 1 : $oldOrphan;
                $newMother = in_array($operation, ['mother', 'all'], true) ? 1 : $oldMother;
                $newBank = in_array($operation, ['bank', 'all'], true) ? 1 : $oldBank;
                if ($record) {
                    $updateStmt->execute([$nannyId, $newOrphan, $newMother, $newBank, $nannyId, (int)$record['id']]);
                } else {
                    $insertStmt->execute([$familyId, $month, $nannyId, $newOrphan, $newMother, $newBank, '', $nannyId]);
                }
                $auditStmt->execute([$familyId, $month, $nannyId, $oldOrphan, $oldMother, $oldBank, $newOrphan, $newMother, $newBank]);
            }
            $pdo->commit();
            ak_api_json(200, ['ok' => true, 'data' => ['action' => $action, 'updated_count' => count($familyIds), 'message' => 'Preliminary bulk verification saved.']]);
        }
        throw new DomainException('Unsupported preliminary verification action.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
} catch (DomainException $e) {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'pre_verification_action_rejected', 'message' => $e->getMessage()]]);
} catch (Throwable $e) {
    error_log('Mobile nanny preliminary verification failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'pre_verification_failed', 'message' => 'Unable to save preliminary verification.']]);
}
