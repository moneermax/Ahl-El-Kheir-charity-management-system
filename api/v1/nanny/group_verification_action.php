<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
if ((string)$user['role_code'] !== 'nanny') {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Group verification is limited to nanny accounts.']]);
}
$input = ak_api_read_json();
$action = trim((string)($input['action'] ?? ''));
$month = (string)($input['month'] ?? date('Y-m'));
if (!preg_match('/^([0-9]{4})-([0-9]{2})$/', $month, $parts) || !checkdate((int)$parts[2], 1, (int)$parts[1])) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_month', 'message' => 'Month must use YYYY-MM format.']]);
}
$groupId = filter_var($input['group_id'] ?? null, FILTER_VALIDATE_INT);
if (!$groupId || $groupId < 1) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_group_id', 'message' => 'A valid group ID is required.']]);
}
$nannyId = (int)$user['id'];
$flag = static fn(string $key): int => in_array($input[$key] ?? false, [true, 1, '1'], true) ? 1 : 0;
$familyBelongs = static function (int $familyId, int $gid): bool {
    return dbFetchOne(
        "SELECT 1 FROM group_children gc
         INNER JOIN family_children fc ON fc.id = gc.child_id
         WHERE gc.group_id = ? AND gc.left_date IS NULL AND fc.family_id = ? LIMIT 1",
        [$gid, $familyId]
    ) !== null;
};

try {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $group = dbFetchOne(
            "SELECT og.id, og.verification_status
             FROM orphan_groups og
             INNER JOIN nanny_group_assignments nga ON nga.group_id = og.id AND nga.nanny_id = ? AND nga.end_date IS NULL
             WHERE og.id = ? AND og.is_active = 1 FOR UPDATE",
            [$nannyId, (int)$groupId]
        );
        if (!$group) throw new OutOfBoundsException('This group is not currently assigned to your account.');
        $status = (string)$group['verification_status'];

        if ($action === 'verify_family') {
            $familyId = filter_var($input['family_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$familyId || $familyId < 1 || !$familyBelongs((int)$familyId, (int)$groupId)) {
                throw new DomainException('The selected family does not belong to this assigned group.');
            }
            $orphan = $flag('is_orphan_verified');
            $mother = $flag('is_mother_contact_verified');
            $bank = $flag('is_bank_verified');
            $notes = trim((string)($input['verification_notes'] ?? ''));
            $existing = dbFetchOne(
                'SELECT id, is_orphan_verified, is_mother_contact_verified, is_bank_verified FROM nanny_family_verifications WHERE family_id = ? AND month = ? FOR UPDATE',
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
            ak_api_json(200, ['ok' => true, 'data' => ['action' => $action, 'family_id' => (int)$familyId, 'message' => 'Family verification saved.']]);
        }

        if ($action === 'bulk_verify') {
            if ($status !== 'pending') throw new DomainException('Bulk verification is available only while the group is pending.');
            $rawIds = $input['family_ids'] ?? [];
            if (!is_array($rawIds)) throw new DomainException('A list of family IDs is required.');
            $familyIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn(int $id): bool => $id > 0)));
            $operation = (string)($input['operation'] ?? '');
            if (!$familyIds) throw new DomainException('Select at least one family.');
            if (count($familyIds) > 500) throw new DomainException('A maximum of 500 families can be verified at once.');
            if (!in_array($operation, ['orphan', 'mother', 'bank', 'all'], true)) throw new DomainException('Unsupported bulk verification operation.');
            foreach ($familyIds as $familyId) {
                if (!$familyBelongs($familyId, (int)$groupId)) throw new DomainException('A selected family does not belong to this group; no changes were saved.');
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
            ak_api_json(200, ['ok' => true, 'data' => ['action' => $action, 'updated_count' => count($familyIds), 'message' => 'Bulk verification saved.']]);
        }

        if ($action === 'submit_group') {
            if ($status !== 'pending') throw new DomainException('This group is no longer open for submission.');
            $counts = dbFetchOne(
                "SELECT COUNT(DISTINCT f.id) AS verified_count
                 FROM group_children gc
                 INNER JOIN family_children fc ON fc.id=gc.child_id
                 INNER JOIN families f ON f.id=fc.family_id
                 INNER JOIN nanny_family_verifications v ON v.family_id=f.id AND v.month=?
                 WHERE gc.group_id=? AND gc.left_date IS NULL
                   AND v.is_orphan_verified=1 AND v.is_mother_contact_verified=1 AND v.is_bank_verified=1",
                [$month, (int)$groupId]
            );
            $verifiedCount = (int)($counts['verified_count'] ?? 0);
            if ($verifiedCount < 1) throw new DomainException('At least one fully verified family is required before submitting the group.');
            dbExecute(
                "UPDATE orphan_groups SET verification_status='submitted', submitted_at=NOW()
                 WHERE id=? AND is_active=1 AND verification_status='pending'
                   AND EXISTS (SELECT 1 FROM nanny_group_assignments WHERE group_id=orphan_groups.id AND nanny_id=? AND end_date IS NULL)",
                [(int)$groupId, $nannyId]
            );
            if ((int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn() !== 1) throw new DomainException('The group changed before submission; refresh and try again.');
            $pdo->commit();
            ak_api_json(200, ['ok' => true, 'data' => ['action' => $action, 'verified_count' => $verifiedCount, 'message' => 'Group submitted for accounting review.']]);
        }

        throw new DomainException('Unsupported group verification action.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
} catch (OutOfBoundsException $e) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'group_not_assigned', 'message' => $e->getMessage()]]);
} catch (DomainException $e) {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'verification_action_rejected', 'message' => $e->getMessage()]]);
} catch (Throwable $e) {
    error_log('Mobile nanny group verification failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'group_verification_failed', 'message' => 'Unable to save group verification.']);
}
