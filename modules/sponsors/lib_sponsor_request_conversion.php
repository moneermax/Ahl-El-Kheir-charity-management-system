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
function convertSponsorRequestToSponsor(int $requestId, int $actorId, string $authorizationScope = 'supervisor'): int
{
    if ($requestId < 1 || $actorId < 1) {
        throw new InvalidArgumentException('بيانات تحويل الطلب غير صالحة.');
    }

    require_once dirname(__DIR__, 2) . '/config/session.php';

    if (!Session::isLoggedIn() || (int)Session::getUserId() !== $actorId) {
        throw new RuntimeException('لا تملك صلاحية تحويل هذا الطلب.');
    }

    if ($authorizationScope === 'supervisor') {
        if (Session::getUserRole() !== 'supervisor') {
            throw new RuntimeException('لا تملك صلاحية تحويل هذا الطلب.');
        }
    } elseif ($authorizationScope === 'management_queue') {
        if (!in_array(Session::getUserRole(), ['admin', 'vice_general_manager', 'general_manager', 'administration', 'staff', 'social_media'], true)) {
            throw new RuntimeException('لا تملك صلاحية تحويل هذا الطلب.');
        }
    } else {
        throw new InvalidArgumentException('نطاق صلاحية تحويل الطلب غير صالح.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Keep the supervisor ownership check inside the row lock to prevent
        // conversion after reassignment between the page read and this transaction.
        $lookupSql = $authorizationScope === 'supervisor'
            ? 'SELECT * FROM sponsor_requests WHERE id = ? AND assigned_supervisor_id = ? FOR UPDATE'
            : 'SELECT * FROM sponsor_requests WHERE id = ? FOR UPDATE';
        $stmt = $pdo->prepare($lookupSql);
        $stmt->execute($authorizationScope === 'supervisor' ? [$requestId, $actorId] : [$requestId]);
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

/**
 * Assign an unassigned company/organization application to an active supervisor.
 * This is a VGM-only routing decision; it does not create a sponsor or alter
 * the individual first-letter + gender assignment matrix.
 */
function assignLegalEntitySponsorRequestToSupervisor(int $requestId, int $supervisorId, int $actorId): string
{
    require_once dirname(__DIR__, 2) . '/config/session.php';

    if ($requestId < 1 || $supervisorId < 1 || $actorId < 1
        || !Session::isLoggedIn()
        || (int)Session::getUserId() !== $actorId
        || Session::getUserRole() !== 'vice_general_manager') {
        throw new RuntimeException('لا تملك صلاحية إسناد طلب الكفالة.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $request = dbFetchOne(
            'SELECT id, sponsor_type, status, assigned_supervisor_id FROM sponsor_requests WHERE id = ? FOR UPDATE',
            [$requestId]
        );
        if (!$request) {
            throw new InvalidArgumentException('طلب الكفالة غير موجود.');
        }
        if (!in_array((string)$request['sponsor_type'], ['company', 'organization'], true)) {
            throw new InvalidArgumentException('الإسناد اليدوي متاح لطلبات الشركات والمنظمات فقط.');
        }
        if (!in_array((string)$request['status'], ['new', 'contacted'], true)) {
            throw new InvalidArgumentException('لا يمكن إسناد طلب مغلق أو تم تحويله.');
        }
        if (!empty($request['assigned_supervisor_id'])) {
            throw new InvalidArgumentException('هذا الطلب مسند بالفعل إلى مشرف.');
        }

        $supervisor = dbFetchOne(
            "SELECT u.id, u.full_name,
                    COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) AS supervisor_status
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND r.code = 'supervisor' AND u.is_active = 1
             FOR UPDATE",
            [$supervisorId]
        );
        if (!$supervisor || (string)$supervisor['supervisor_status'] !== 'active') {
            throw new InvalidArgumentException('المشرف المحدد غير نشط أو غير متاح للإسناد.');
        }

        dbExecute(
            'UPDATE sponsor_requests SET assigned_supervisor_id = ? WHERE id = ? AND status IN (\'new\', \'contacted\')',
            [$supervisorId, $requestId]
        );
        if ((int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn() !== 1
            && (int)($request['assigned_supervisor_id'] ?? 0) !== $supervisorId) {
            throw new RuntimeException('تعذر حفظ إسناد الطلب؛ حدّث الصفحة وحاول مجدداً.');
        }

        dbExecute(
            "INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address, user_agent)
             VALUES (?, 'ASSIGN_SUPERVISOR', 'sponsor_request', ?, ?, ?)",
            [$actorId, $requestId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
        );
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    try {
        dbExecute(
            "INSERT INTO notifications (recipient_user_id, type, title, body, link)
             VALUES (?, 'sponsor_request', ?, ?, ?)",
            [$supervisorId, 'طلب كفالة مسند إليك', 'تم إسناد طلب كفالة جديد إليك لمتابعته.', 'modules/sponsors/my_request.php?id=' . $requestId]
        );
    } catch (Throwable $e) {
        error_log('Sponsor request assignment notification failed for request ' . $requestId . ': ' . $e->getMessage());
    }

    return trim((string)$supervisor['full_name']);
}

