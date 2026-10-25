<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$userId = (int)$user['id'];
$rawRole = (string)$user['role_code'];
$aliases = [
    'sudo' => 'admin',
    'super_admin' => 'admin',
    'vgm' => 'vice_general_manager',
];
$role = $aliases[$rawRole] ?? $rawRole;
if (!in_array($role, ['admin', 'vice_general_manager', 'nanny'], true)) {
    ak_api_json(403, [
        'ok' => false,
        'error' => ['code' => 'forbidden', 'message' => 'This account cannot suspend sponsorships from legal-age alerts.'],
    ]);
}

$input = ak_api_read_json();
$childId = filter_var($input['child_id'] ?? null, FILTER_VALIDATE_INT);
if (!$childId || $childId < 1) {
    ak_api_json(422, [
        'ok' => false,
        'error' => ['code' => 'invalid_child_id', 'message' => 'A valid child ID is required.'],
    ]);
}
$notes = trim((string)($input['notes'] ?? ''));
if (mb_strlen($notes, 'UTF-8') > 1000) {
    ak_api_json(422, [
        'ok' => false,
        'error' => ['code' => 'notes_too_long', 'message' => 'Notes must not exceed 1000 characters.'],
    ]);
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $child = dbFetchOne(
            "SELECT fc.id, fc.family_id, fc.child_name, fc.birth_date, fc.is_active,
                    f.nanny_id
             FROM family_children fc
             INNER JOIN families f ON f.id = fc.family_id
             WHERE fc.id = ?
               AND fc.is_active = 1
               AND fc.birth_date IS NOT NULL
               AND fc.birth_date <= DATE_SUB(DATE_ADD(CURDATE(), INTERVAL 90 DAY), INTERVAL 18 YEAR)
               AND fc.birth_date >= DATE_SUB(CURDATE(), INTERVAL 21 YEAR)
             FOR UPDATE",
            [(int)$childId]
        );
        if (!$child) {
            throw new OutOfBoundsException('The child is no longer eligible for the legal-age alert action.');
        }
        if ($role === 'nanny' && (int)$child['nanny_id'] !== $userId) {
            $pdo->rollBack();
            ak_api_json(403, [
                'ok' => false,
                'error' => ['code' => 'forbidden', 'message' => 'This family is outside your assigned scope.'],
            ]);
        }

        $existing = dbFetchOne(
            "SELECT id FROM suspension_cases
             WHERE entity_type = 'child' AND entity_id = ? AND status = 'active'
             LIMIT 1 FOR UPDATE",
            [(int)$childId]
        );
        if ($existing) {
            throw new DomainException('An active suspension already exists for this child.');
        }

        dbExecute(
            "INSERT INTO suspension_cases (entity_type, entity_id, reason, notes, suspended_by)
             VALUES ('child', ?, 'age_out', ?, ?)",
            [(int)$childId, $notes !== '' ? $notes : null, $userId]
        );
        $caseId = (int)$pdo->lastInsertId();
        if ($caseId < 1) {
            throw new RuntimeException('The suspension case could not be created.');
        }

        $marker = 'تعليق [CASE#' . $caseId . '] بلوغ السن القانوني';
        dbExecute(
            "UPDATE family_children
             SET is_active = 0, disqualified_at = CURDATE(), disqualification_reason = 'age_out'
             WHERE id = ? AND is_active = 1",
            [(int)$childId]
        );
        if ((int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn() !== 1) {
            throw new RuntimeException('The child record changed before suspension could be saved.');
        }

        dbExecute(
            "UPDATE sponsorships sp
             INNER JOIN sponsorship_children sc ON sc.sponsorship_id = sp.id
             SET sp.status = 'paused', sp.pause_reason = ?,
                 sp.pause_start_date = CURDATE(), sp.updated_at = NOW()
             WHERE sc.family_child_id = ? AND sp.status = 'active'",
            [$marker, (int)$childId]
        );
        $pausedSponsorships = (int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    try {
        dbExecute(
            "INSERT INTO audit_log
                (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'SUSPEND', 'suspensions', ?, NULL, ?, ?, ?)",
            [
                $userId,
                $caseId,
                json_encode(['type' => 'child', 'entity_id' => (int)$childId, 'reason' => 'age_out'], JSON_UNESCAPED_UNICODE),
                (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]
        );
    } catch (Throwable $e) {
        error_log('Mobile legal-age suspension audit failed: ' . $e->getMessage());
    }

    ak_api_json(200, [
        'ok' => true,
        'data' => [
            'case_id' => $caseId,
            'child_id' => (int)$childId,
            'sponsorships_paused' => $pausedSponsorships,
            'message' => 'The child was disqualified for legal age and active linked sponsorships were paused.',
        ],
    ]);
} catch (OutOfBoundsException $e) {
    ak_api_json(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => $e->getMessage()]]);
} catch (DomainException $e) {
    ak_api_json(409, ['ok' => false, 'error' => ['code' => 'already_suspended', 'message' => $e->getMessage()]]);
} catch (Throwable $e) {
    error_log('Mobile legal-age suspension failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'suspension_failed', 'message' => 'Unable to complete the suspension.']);
}
