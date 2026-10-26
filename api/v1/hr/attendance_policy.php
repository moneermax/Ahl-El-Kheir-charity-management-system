<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/hr/lib_attendance_policy.php';

$user = ak_api_authenticated_user();
if (!in_array((string)($user['raw_role_code'] ?? ''), ['hr_manager', 'admin'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Attendance policy management is restricted to HR Manager and administrator roles.',
    ]]);
}

function mobileAttendancePolicyCanManage(PDO $pdo, array $policy, string $today): bool
{
    $effectiveFrom = (string)($policy['effective_from'] ?? '');
    if ($effectiveFrom > $today) return true;
    if ($effectiveFrom < $today) return false;
    return !hrAttendancePolicyHasOperationalAttendance($pdo, $policy);
}

function mobileAttendancePolicySerialize(PDO $pdo, array $policy, string $today): array
{
    $locked = (string)$policy['effective_from'] === $today
        && hrAttendancePolicyHasOperationalAttendance($pdo, $policy);
    return [
        'id' => (int)$policy['id'], 'version_no' => (int)$policy['version_no'],
        'policy_name' => (string)$policy['policy_name'], 'effective_from' => (string)$policy['effective_from'],
        'working_start_time' => (string)$policy['working_start_time'], 'working_end_time' => (string)$policy['working_end_time'],
        'working_days' => (string)$policy['working_days'], 'attendance_cutoff_time' => (string)$policy['attendance_cutoff_time'],
        'absence_finalization_time' => (string)$policy['absence_finalization_time'],
        'auto_login_attendance' => (int)$policy['auto_login_attendance'] === 1,
        'auto_absence_enabled' => (int)$policy['auto_absence_enabled'] === 1,
        'default_work_mode' => (string)$policy['default_work_mode'], 'notes' => (string)($policy['notes'] ?? ''),
        'created_by_name' => (string)($policy['created_by_name'] ?? ''), 'created_at' => (string)($policy['created_at'] ?? ''),
        'can_manage' => mobileAttendancePolicyCanManage($pdo, $policy, $today), 'locked_by_attendance' => $locked,
    ];
}

try {
    $pdo = db();
    $today = date('Y-m-d');
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
        ak_api_require_method('GET');
        $policies = dbFetchAll(
            'SELECT p.*, u.full_name AS created_by_name FROM hr_attendance_policy_versions p
             LEFT JOIN users u ON u.id = p.created_by ORDER BY p.effective_from DESC, p.version_no DESC'
        );
        $active = hrAttendancePolicyGetActive($pdo, $today);
        $serialized = array_map(static fn(array $p): array => mobileAttendancePolicySerialize($pdo, $p, $today), $policies);
        $activeSerialized = null;
        foreach ($serialized as $p) if ((int)$p['id'] === (int)($active['id'] ?? 0)) { $activeSerialized = $p; break; }
        ak_api_json(200, ['ok' => true, 'data' => ['today' => $today, 'active_policy' => $activeSerialized, 'policies' => $serialized]]);
    }

    ak_api_require_method('POST');
    $body = ak_api_read_json();
    $action = (string)($body['action'] ?? '');
    if (!in_array($action, ['create', 'update', 'delete'], true)) {
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_action', 'message' => 'Action must be create, update, or delete.']]);
    }

    $pdo->beginTransaction();
    try {
        if ($action === 'create') {
            $input = $body;
            if (empty($body['auto_login_attendance'])) unset($input['auto_login_attendance']);
            if (empty($body['auto_absence_enabled'])) unset($input['auto_absence_enabled']);
            $policy = hrAttendancePolicyValidate($input);
            if (dbFetchOne('SELECT id FROM hr_attendance_policy_versions WHERE effective_from=? LIMIT 1', [$policy['effective_from']])) {
                throw new InvalidArgumentException('A policy already exists with that effective date.');
            }
            $next = dbFetchOne('SELECT COALESCE(MAX(version_no),0)+1 AS next_version FROM hr_attendance_policy_versions');
            $version = (int)($next['next_version'] ?? 1);
            dbExecute(
                'INSERT INTO hr_attendance_policy_versions
                 (version_no,policy_name,effective_from,working_start_time,working_end_time,working_days,attendance_cutoff_time,
                  absence_finalization_time,auto_login_attendance,auto_absence_enabled,default_work_mode,notes,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$version,$policy['policy_name'],$policy['effective_from'],$policy['working_start_time'],$policy['working_end_time'],
                 $policy['working_days'],$policy['attendance_cutoff_time'],$policy['absence_finalization_time'],
                 $policy['auto_login_attendance'],$policy['auto_absence_enabled'],$policy['default_work_mode'],$policy['notes'],(int)$user['id']]
            );
            $id = (int)$pdo->lastInsertId();
            $message = 'Attendance policy version created.';
        } elseif ($action === 'update') {
            $id = (int)($body['policy_id'] ?? 0);
            $existing = dbFetchOne('SELECT * FROM hr_attendance_policy_versions WHERE id=? LIMIT 1 FOR UPDATE', [$id]);
            if (!$existing) throw new InvalidArgumentException('The requested policy version does not exist.');
            if (!mobileAttendancePolicyCanManage($pdo, $existing, $today)) throw new InvalidArgumentException('An expired or operationally locked policy cannot be changed.');
            $input = $body;
            if (empty($body['auto_login_attendance'])) unset($input['auto_login_attendance']);
            if (empty($body['auto_absence_enabled'])) unset($input['auto_absence_enabled']);
            $policy = hrAttendancePolicyValidate($input);
            if (dbFetchOne('SELECT id FROM hr_attendance_policy_versions WHERE effective_from=? AND id<>? LIMIT 1', [$policy['effective_from'],$id])) {
                throw new InvalidArgumentException('A policy already exists with that effective date.');
            }
            dbExecute(
                'UPDATE hr_attendance_policy_versions SET policy_name=?,effective_from=?,working_start_time=?,working_end_time=?,working_days=?,
                 attendance_cutoff_time=?,absence_finalization_time=?,auto_login_attendance=?,auto_absence_enabled=?,default_work_mode=?,notes=? WHERE id=?',
                [$policy['policy_name'],$policy['effective_from'],$policy['working_start_time'],$policy['working_end_time'],$policy['working_days'],
                 $policy['attendance_cutoff_time'],$policy['absence_finalization_time'],$policy['auto_login_attendance'],$policy['auto_absence_enabled'],
                 $policy['default_work_mode'],$policy['notes'],$id]
            );
            $message = 'Attendance policy version updated.';
        } else {
            $id = (int)($body['policy_id'] ?? 0);
            $existing = dbFetchOne('SELECT * FROM hr_attendance_policy_versions WHERE id=? LIMIT 1 FOR UPDATE', [$id]);
            if (!$existing) throw new InvalidArgumentException('The requested policy version does not exist.');
            if (!mobileAttendancePolicyCanManage($pdo, $existing, $today)) throw new InvalidArgumentException('An expired or operationally locked policy cannot be deleted.');
            dbExecute('DELETE FROM hr_attendance_policy_versions WHERE id=?', [$id]);
            $message = 'Attendance policy version deleted.';
        }
        $pdo->commit();
        ak_api_json(200, ['ok' => true, 'data' => ['id' => $id, 'message' => $message]]);
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        ak_api_json(422, ['ok' => false, 'error' => ['code' => 'policy_validation_failed', 'message' => $e->getMessage()]]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((string)$e->getCode() === '23000') {
            ak_api_json(409, ['ok' => false, 'error' => ['code' => 'policy_conflict', 'message' => 'The policy version or effective date conflicts with an existing record.']]);
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
} catch (Throwable $e) {
    error_log('Mobile attendance policy failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'attendance_policy_failed', 'message' => 'Attendance policy information could not be processed.']]);
}
