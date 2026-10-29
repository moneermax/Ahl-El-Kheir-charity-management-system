<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/sponsor_assignments.php';

/**
 * Convert one actionable sponsor request into an active sponsor.
 *
 * The request row is locked and all writes share one transaction so duplicate
 * submissions cannot create multiple sponsor records for the same request.
 * Field mapping and supervisor resolution mirror the established staff flow.
 */
function convertSponsorRequestToSponsor(int $requestId, int $actorId): int
{
    if ($requestId < 1 || $actorId < 1) {
        throw new InvalidArgumentException('بيانات تحويل الطلب غير صالحة.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT * FROM sponsor_requests WHERE id = ? FOR UPDATE');
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if (!$req) {
            throw new InvalidArgumentException('طلب الرعاية غير موجود.');
        }
        if (!in_array((string)$req['status'], ['new', 'contacted'], true)) {
            throw new InvalidArgumentException('لا يمكن تحويل هذا الطلب في حالته الحالية؛ ربما تم تحويله مسبقاً.');
        }

        $name = trim((string)$req['sponsor_name']);
        $type = (string)($req['sponsor_type'] ?? 'individual');
        if (!in_array($type, ['individual', 'company', 'organization'], true)) {
            $type = 'individual';
        }
        $gender = $type === 'individual'
            ? strtolower(trim((string)($req['gender'] ?? '')))
            : 'organization';

        if ($name === '') {
            throw new InvalidArgumentException('اسم مقدم طلب الكفالة مطلوب.');
        }
        if ($type === 'individual' && !in_array($gender, ['male', 'female'], true)) {
            throw new InvalidArgumentException('يرجى تحديد جنس مقدم الطلب قبل التحويل.');
        }

        [$rawLetter, $normalizedLetter] = first_letter_of($name);
        $letterRow = dbFetchOne(
            'SELECT id FROM letters WHERE is_active = 1 AND code = ? LIMIT 1',
            [$normalizedLetter]
        );
        $letterId = $letterRow ? (int)$letterRow['id'] : null;
        $supervisorId = $type === 'individual'
            ? resolveSponsorSupervisorId($name, $gender)
            : null;
        $n = (int)(dbFetchOne('SELECT COUNT(*) c FROM sponsors')['c'] ?? 0) + 1;
        $code = 'SP-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
        $payment = in_array((string)($req['preferred_payment_method'] ?? ''), ['cash', 'bank_transfer', 'credit_card', 'mobile', 'other'], true)
            ? (string)$req['preferred_payment_method']
            : 'cash';
        $broughtByUserId = is_numeric($req['brought_by'] ?? null) && (int)$req['brought_by'] > 0
            ? (int)$req['brought_by']
            : null;

        dbExecute(
            "INSERT INTO sponsors
                (full_name, first_letter_raw, first_letter_id, phone, phone_purpose, alt_phone, alt_phone_purpose,
                 email, address, sponsor_code, status, sponsor_type, gender, preferred_payment_method, notes,
                 desired_orphans, acquisition_source, brought_by_user_id, brought_by_name, created_by,
                 supervisor_id, assigned_by, assigned_at, is_manual_override)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)",
            [
                $name, $rawLetter, $letterId, $req['phone'] ?? null,
                $req['phone_purpose'] ?? 'both', $req['alt_phone'] ?? null,
                $req['alt_phone_purpose'] ?? 'both', $req['email'] ?? null, $req['address'] ?? null,
                $code, $type, $gender, $payment, $req['notes'] ?? null,
                isset($req['desired_orphans']) && $req['desired_orphans'] !== '' ? (int)$req['desired_orphans'] : null,
                $req['source'] ?? 'public_form', $broughtByUserId,
                $req['brought_by_name'] ?? null, $actorId, $supervisorId, $actorId
            ]
        );

        $newId = (int)$pdo->lastInsertId();
        if ($newId < 1) {
            throw new RuntimeException('تعذر إنشاء سجل الكفيل.');
        }
        if ($supervisorId) {
            ensureActiveSponsorAssignmentHistory($newId, $supervisorId, $actorId);
        }

        $updated = dbExecute(
            "UPDATE sponsor_requests
             SET status = 'converted', created_sponsor_id = ?, confirmed_at = NOW(), confirmed_by = ?
             WHERE id = ? AND status IN ('new', 'contacted')",
            [$newId, $actorId, $requestId]
        );
        if ($updated !== 1) {
            throw new RuntimeException('تغيرت حالة الطلب أثناء التحويل؛ لم يتم إكمال العملية.');
        }

        dbExecute(
            "INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address, user_agent)
             VALUES (?, 'CONVERT', 'sponsor_request', ?, ?, ?)",
            [$actorId, $requestId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
        );

        $pdo->commit();
        return $newId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
