<?php
// Supervisor organizational lifecycle helpers.
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/sponsor_assignments.php';

function supervisorLifecycleStatusLabel(string $status): string
{
    $labels = [
        'active' => 'نشط',
        'on_leave' => 'في إجازة',
        'suspended' => 'موقوف',
        'returning' => 'في طور العودة',
        'departed' => 'غادر المؤسسة',
        'archived' => 'مؤرشف',
    ];
    return $labels[$status] ?? $status;
}

function supervisorLifecycleCanWork(string $status): bool
{
    return $status === 'active';
}

function supervisorLifecycleIsFinal(string $status): bool
{
    return in_array($status, ['departed', 'archived'], true);
}

function getSupervisorLifecycle(int $supervisorId): ?array
{
    return dbFetchOne("SELECT u.id, u.full_name, u.is_active,
            COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) AS supervisor_status
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE u.id = ? AND r.code = 'supervisor'", [$supervisorId]);
}

function startSupervisorLeave(
    int $supervisorId,
    string $leaveType,
    string $startDate,
    ?string $expectedReturnDate,
    ?string $reason,
    ?string $notes,
    ?int $actorId
): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $supervisor = dbFetchOne("SELECT u.id, u.is_active, COALESCE(u.supervisor_status, 'active') AS supervisor_status
            FROM users u JOIN roles r ON r.id = u.role_id
            WHERE u.id = ? AND r.code = 'supervisor' FOR UPDATE", [$supervisorId]);
        if (!$supervisor) throw new RuntimeException('Supervisor not found.');
        if ($supervisor['supervisor_status'] !== 'active') throw new RuntimeException('Supervisor is not active.');

        $activeLeave = dbFetchOne("SELECT id FROM supervisor_leaves WHERE supervisor_id = ? AND status IN ('planned','active') LIMIT 1", [$supervisorId]);
        if ($activeLeave) throw new RuntimeException('Supervisor already has an open leave record.');

        $allowedTypes = ['vacation','personal','medical','maternity','study','other'];
        if (!in_array($leaveType, $allowedTypes, true)) throw new RuntimeException('Invalid leave type.');
        if (!$startDate || $startDate < date('Y-m-d')) throw new RuntimeException('Leave start date cannot be in the past.');
        if ($expectedReturnDate !== null && $expectedReturnDate !== '' && $expectedReturnDate < $startDate) {
            throw new RuntimeException('Expected return date cannot precede the leave start date.');
        }

        $isToday = $startDate === date('Y-m-d');
        $leaveStatus = $isToday ? 'active' : 'planned';
        dbExecute("INSERT INTO supervisor_leaves
            (supervisor_id, leave_type, start_date, expected_return_date, reason, notes, status, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$supervisorId, $leaveType, $startDate, $expectedReturnDate ?: null, $reason ?: null, $notes ?: null, $leaveStatus, $actorId, $actorId]);

        $leaveId = (int)db()->lastInsertId();
        if ($isToday) {
            dbExecute("UPDATE users SET supervisor_status = 'on_leave', is_active = 0, updated_at = NOW() WHERE id = ?", [$supervisorId]);
        }

        $pdo->commit();
        return $leaveId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function activatePlannedSupervisorLeave(int $supervisorId, ?int $actorId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $supervisor = dbFetchOne("SELECT u.id, COALESCE(u.supervisor_status, 'active') AS supervisor_status
            FROM users u JOIN roles r ON r.id = u.role_id
            WHERE u.id = ? AND r.code = 'supervisor' FOR UPDATE", [$supervisorId]);
        if (!$supervisor) throw new RuntimeException('Supervisor not found.');
        if ($supervisor['supervisor_status'] !== 'active') throw new RuntimeException('Supervisor is not active.');

        $leave = dbFetchOne("SELECT id, start_date FROM supervisor_leaves
            WHERE supervisor_id = ? AND status = 'planned' ORDER BY id DESC LIMIT 1 FOR UPDATE", [$supervisorId]);
        if (!$leave) throw new RuntimeException('No planned leave found.');
        if ($leave['start_date'] > date('Y-m-d')) throw new RuntimeException('Leave start date has not arrived.');

        dbExecute("UPDATE supervisor_leaves SET status = 'active', updated_by = ? WHERE id = ?", [$actorId, $leave['id']]);
        dbExecute("UPDATE users SET supervisor_status = 'on_leave', is_active = 0, updated_at = NOW() WHERE id = ?", [$supervisorId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function returnSupervisorFromLeave(int $supervisorId, ?int $actorId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $supervisor = dbFetchOne("SELECT u.id, COALESCE(u.supervisor_status, 'active') AS supervisor_status
            FROM users u JOIN roles r ON r.id = u.role_id
            WHERE u.id = ? AND r.code = 'supervisor' FOR UPDATE", [$supervisorId]);
        if (!$supervisor) throw new RuntimeException('Supervisor not found.');
        if ($supervisor['supervisor_status'] !== 'on_leave') throw new RuntimeException('Supervisor is not on leave.');

        $leave = dbFetchOne("SELECT id FROM supervisor_leaves
            WHERE supervisor_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1 FOR UPDATE", [$supervisorId]);
        if (!$leave) throw new RuntimeException('No active leave record found.');

        dbExecute("UPDATE supervisor_leaves SET actual_return_date = CURDATE(), status = 'completed', updated_by = ? WHERE id = ?", [$actorId, $leave['id']]);
        dbExecute("UPDATE users SET supervisor_status = 'active', is_active = 1, updated_at = NOW() WHERE id = ?", [$supervisorId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function preserveAndReleaseSupervisorLetters(int $supervisorId, ?int $actorId, string $reason = 'supervisor_departure'): int
{
    // The history table is provisioned by the database migration. Do not run
    // CREATE TABLE from inside a business transaction because MySQL DDL can
    // implicitly commit the transaction and break atomic departure handling.
    $rows = dbFetchAll("SELECT sl.id, sl.supervisor_id, sl.letter_id, COALESCE(sl.gender, 'both') AS gender, sl.assigned_by
        FROM supervisor_letters sl WHERE sl.supervisor_id = ? FOR UPDATE", [$supervisorId]);

    foreach ($rows as $row) {
        $existing = dbFetchOne("SELECT id FROM supervisor_letter_assignment_history
            WHERE supervisor_letter_id = ? AND ended_at IS NULL LIMIT 1", [(int)$row['id']]);
        if (!$existing) {
            dbExecute("INSERT INTO supervisor_letter_assignment_history
                (supervisor_letter_id, supervisor_id, letter_id, gender, assigned_by, assignment_type, assignment_reason, ended_at, ended_by, end_reason)
                VALUES (?, ?, ?, ?, ?, 'permanent', 'legacy_backfill', NOW(), ?, ?)",
                [(int)$row['id'], (int)$row['supervisor_id'], (int)$row['letter_id'], $row['gender'], $row['assigned_by'] ?: null, $actorId, $reason]);
        } else {
            dbExecute("UPDATE supervisor_letter_assignment_history
                SET ended_at = NOW(), ended_by = ?, end_reason = ?
                WHERE id = ? AND ended_at IS NULL", [$actorId, $reason, (int)$existing['id']]);
        }
    }

    if ($rows) dbExecute("DELETE FROM supervisor_letters WHERE supervisor_id = ?", [$supervisorId]);
    return count($rows);
}

function archiveSupervisor(int $supervisorId, ?int $actorId): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $supervisor = dbFetchOne("SELECT u.id, u.full_name,
                COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) AS supervisor_status
            FROM users u JOIN roles r ON r.id = u.role_id
            WHERE u.id = ? AND r.code = 'supervisor' FOR UPDATE", [$supervisorId]);
        if (!$supervisor) throw new RuntimeException('Supervisor not found.');
        if (supervisorLifecycleIsFinal((string)$supervisor['supervisor_status'])) {
            throw new RuntimeException('Supervisor is already departed or archived.');
        }

        $released = releaseSupervisorSponsors($supervisorId, $actorId, 'supervisor_departure');
        preserveAndReleaseSupervisorLetters($supervisorId, $actorId, 'supervisor_departure');

        dbExecute("UPDATE supervisor_leaves
            SET status = 'completed', actual_return_date = COALESCE(actual_return_date, CURDATE()), updated_by = ?
            WHERE supervisor_id = ? AND status IN ('planned','active')", [$actorId, $supervisorId]);
        dbExecute("DELETE FROM user_sessions WHERE user_id = ?", [$supervisorId]);
        dbExecute("UPDATE users SET
            is_active = 0,
            supervisor_status = 'archived',
            legacy_status = 'deleted',
            username = CONCAT('archived_supervisor_', id, '_', UNIX_TIMESTAMP()),
            password_hash = ?,
            email = NULL,
            phone = NULL,
            updated_at = NOW()
            WHERE id = ?", [password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $supervisorId]);

        $pdo->commit();
        return $released;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
