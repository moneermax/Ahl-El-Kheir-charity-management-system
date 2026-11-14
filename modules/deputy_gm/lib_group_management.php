<?php
/**
 * VGM group lifecycle and monthly-cycle actions.
 * All database structure changes belong in versioned migrations.
 */

/**
 * Validate and normalize group details for every web/API write path.
 * The database unique index remains the final concurrency-safe uniqueness
 * boundary; this query provides an immediate, user-friendly validation error.
 */
function ak_vgm_validate_group_details(PDO $pdo, $rawName, $rawDescription, int $excludeGroupId = 0): array
{
    if (!is_string($rawName) || !is_string($rawDescription)) {
        throw new DomainException('اسم المجموعة والوصف يجب أن يكونا نصاً صالحاً.');
    }

    $name = trim($rawName);
    $description = trim($rawDescription);
    if ($name === '') throw new DomainException('اسم المجموعة مطلوب.');
    if (mb_strlen($name, 'UTF-8') > 150) throw new DomainException('اسم المجموعة يجب ألا يتجاوز 150 حرفاً.');
    if (mb_strlen($description, 'UTF-8') > 1000) throw new DomainException('وصف المجموعة يجب ألا يتجاوز 1000 حرف.');

    $duplicate = $pdo->prepare(
        'SELECT id FROM orphan_groups WHERE group_name = ? AND id <> ? LIMIT 1'
    );
    $duplicate->execute([$name, $excludeGroupId]);
    if ($duplicate->fetchColumn()) {
        throw new DomainException('اسم المجموعة مستخدم بالفعل. اختر اسماً آخر.');
    }

    return [$name, $description];
}

/** Identify the database unique-index collision for a group name. */
function ak_vgm_is_duplicate_group_name_error(PDOException $error): bool
{
    return isset($error->errorInfo[1]) && (int)$error->errorInfo[1] === 1062;
}

/** Require the submitted account to be an active user with the Nanny role. */
function ak_vgm_assert_active_nanny(PDO $pdo, int $nannyId): void
{
    if ($nannyId < 1) throw new DomainException('يرجى اختيار حاضنة صالحة.');
    $stmt = $pdo->prepare(
        "SELECT u.id FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? AND r.code = 'nanny' AND u.is_active = 1
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$nannyId]);
    if (!$stmt->fetchColumn()) {
        throw new DomainException('الحساب المحدد ليس لحاضنة نشطة.');
    }
}

/** Lock and revalidate a group before changing its members or nanny. */
function ak_vgm_lock_operational_group(PDO $pdo, int $groupId): array
{
    if ($groupId < 1) throw new DomainException('المجموعة غير محددة.');
    $stmt = $pdo->prepare('SELECT id, status FROM orphan_groups WHERE id = ? FOR UPDATE');
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) throw new DomainException('المجموعة غير موجودة.');
    if (!in_array((string)$group['status'], ['draft', 'active', 'empty'], true)) {
        throw new DomainException('لا يمكن تعديل أعضاء أو حاضنة مجموعة غير تشغيلية.');
    }
    return $group;
}

function ak_vgm_group_usage(PDO $pdo, int $groupId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM group_children WHERE group_id = :id1) AS member_history,
            (SELECT COUNT(*) FROM nanny_group_assignments WHERE group_id = :id2) AS nanny_history,
            (SELECT COUNT(*) FROM monthly_disbursements WHERE group_id = :id3) AS disbursement_history,
            (SELECT COUNT(*) FROM group_workflow_audit_log WHERE group_id = :id4) +
            (SELECT COUNT(*) FROM audit_log WHERE entity_type = 'orphan_groups' AND entity_id = :id5) AS workflow_history"
    );
    $stmt->execute(['id1' => $groupId, 'id2' => $groupId, 'id3' => $groupId, 'id4' => $groupId, 'id5' => $groupId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'member_history' => (int)($row['member_history'] ?? 0),
        'nanny_history' => (int)($row['nanny_history'] ?? 0),
        'disbursement_history' => (int)($row['disbursement_history'] ?? 0),
        'workflow_history' => (int)($row['workflow_history'] ?? 0),
    ];
}

function ak_vgm_group_is_used(array $usage): bool
{
    foreach ($usage as $count) {
        if ((int)$count > 0) return true;
    }
    return false;
}

function ak_vgm_assert_group_archivable(PDO $pdo, int $groupId, string $verificationStatus): void
{
    if (in_array($verificationStatus, ['submitted', 'approved', 'transferred', 'returned'], true)) {
        throw new RuntimeException('لا يمكن أرشفة المجموعة أثناء وجود دورة توثيق أو صرف غير مغلقة.');
    }
    if ($verificationStatus === 'pending') {
        $opened = $pdo->prepare(
            "SELECT 1 FROM audit_log
             WHERE entity_type = 'orphan_groups' AND entity_id = :id
               AND action = 'VGM_OPEN_MONTHLY_CYCLE'
             LIMIT 1"
        );
        $opened->execute(['id' => $groupId]);
        if ($opened->fetchColumn()) {
            throw new RuntimeException('لا يمكن أرشفة المجموعة بعد فتح دورة شهرية لم تُغلق بعد.');
        }
    }
    $openBatch = $pdo->prepare(
        "SELECT id FROM monthly_disbursements
         WHERE group_id = :id AND status NOT IN ('received', 'returned', 'cancelled', 'voided')
         LIMIT 1 FOR UPDATE"
    );
    $openBatch->execute(['id' => $groupId]);
    if ($openBatch->fetchColumn()) {
        throw new RuntimeException('لا يمكن أرشفة المجموعة قبل إكمال أو إلغاء دفعة الصرف المفتوحة.');
    }
}

/**
 * Delete a never-used group; archive a used group without deleting any
 * membership, nanny-assignment, disbursement, or workflow history.
 */
function ak_vgm_delete_or_archive_group(PDO $pdo, int $groupId, int $actorId, string $reason = ''): string
{
    if ($groupId < 1 || $actorId < 1) throw new RuntimeException('بيانات المجموعة غير مكتملة');
    if ($pdo->inTransaction()) throw new RuntimeException('لا يمكن بدء حذف/أرشفة المجموعة أثناء معاملة أخرى.');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, status, verification_status FROM orphan_groups WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $groupId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$group) throw new RuntimeException('المجموعة غير موجودة');
        if ($group['status'] === 'archived') throw new RuntimeException('المجموعة مؤرشفة بالفعل.');

        $usage = ak_vgm_group_usage($pdo, $groupId);
        if (!ak_vgm_group_is_used($usage)) {
            if (!in_array($group['status'], ['draft', 'empty'], true)) {
                throw new RuntimeException('الحذف النهائي متاح فقط لمجموعة مسودة أو فارغة لم تُستخدم مطلقاً.');
            }
            $pdo->prepare('DELETE FROM orphan_groups WHERE id = :id')->execute(['id' => $groupId]);
            $pdo->commit();
            return 'deleted';
        }

        ak_vgm_assert_group_archivable($pdo, $groupId, (string)$group['verification_status']);

        $archiveReason = trim($reason);
        if ($archiveReason === '') $archiveReason = 'أرشفة المجموعة بواسطة نائب المدير العام مع الحفاظ على كامل السجل';
        $pdo->prepare(
            "UPDATE orphan_groups
             SET status = 'archived', is_active = 0, lifecycle_reason = :reason,
                 closed_at = COALESCE(closed_at, NOW()), closed_by = COALESCE(closed_by, :actor),
                 updated_at = NOW()
             WHERE id = :id"
        )->execute(['reason' => mb_substr($archiveReason, 0, 500, 'UTF-8'), 'actor' => $actorId, 'id' => $groupId]);
        $pdo->prepare(
            'UPDATE nanny_group_assignments SET end_date = CURDATE()
             WHERE group_id = :id AND end_date IS NULL'
        )->execute(['id' => $groupId]);
        $pdo->commit();
        return 'archived';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Open the server's current month for individually/bulk-selected VGM groups.
 * A cycle can start only for an active, non-empty group with an active nanny.
 * A later cycle requires the previous group cycle to be closed by Nanny and
 * every prior disbursement to be in a completed state. The old batch remains
 * untouched; only the group's current verification pointer is reset.
 */
function ak_vgm_open_monthly_cycles(PDO $pdo, array $groupIds, string $month, int $actorId): array
{
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $actorId < 1) {
        throw new RuntimeException('شهر أو مستخدم العملية غير صالح.');
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $groupIds), static fn(int $id): bool => $id > 0)));
    if (!$ids || count($ids) > 200) throw new RuntimeException('اختر مجموعة واحدة على الأقل، وبحد أقصى 200 مجموعة.');

    if ($pdo->inTransaction()) throw new RuntimeException('تعذر فتح الدورة أثناء معاملة أخرى.');
    $pdo->beginTransaction();
    $opened = [];
    $skipped = [];
    $notifications = [];

    try {
        foreach ($ids as $groupId) {
            $stmt = $pdo->prepare('SELECT id, group_name, status, is_active, verification_status FROM orphan_groups WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $groupId]);
            $group = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$group) { $skipped[] = ['id' => $groupId, 'reason' => 'المجموعة غير موجودة']; continue; }
            if ($group['status'] !== 'active' || (int)$group['is_active'] !== 1) {
                $skipped[] = ['id' => $groupId, 'reason' => 'المجموعة ليست نشطة']; continue;
            }

            $members = $pdo->prepare('SELECT COUNT(*) FROM group_children WHERE group_id = :id AND left_date IS NULL');
            $members->execute(['id' => $groupId]);
            if ((int)$members->fetchColumn() < 1) {
                $skipped[] = ['id' => $groupId, 'reason' => 'المجموعة لا تضم أعضاء حاليين']; continue;
            }

            $assignment = $pdo->prepare(
                "SELECT nga.nanny_id, u.full_name
                 FROM nanny_group_assignments nga
                 JOIN users u ON u.id = nga.nanny_id
                 JOIN roles r ON r.id = u.role_id
                 WHERE nga.group_id = :id AND nga.end_date IS NULL
                   AND u.is_active = 1 AND r.code = 'nanny'
                 ORDER BY nga.id DESC LIMIT 1 FOR UPDATE"
            );
            $assignment->execute(['id' => $groupId]);
            $nanny = $assignment->fetch(PDO::FETCH_ASSOC);
            if (!$nanny) { $skipped[] = ['id' => $groupId, 'reason' => 'لا توجد حاضنة نشطة معينة للمجموعة']; continue; }

            $batches = $pdo->prepare(
                'SELECT id, month, status FROM monthly_disbursements
                 WHERE group_id = :id ORDER BY month DESC, id DESC FOR UPDATE'
            );
            $batches->execute(['id' => $groupId]);
            $history = $batches->fetchAll(PDO::FETCH_ASSOC);
            $currentBatch = false;
            $allPriorBatchesCompleted = true;
            foreach ($history as $batch) {
                if ((string)$batch['month'] === $month) $currentBatch = true;
                if (!in_array($batch['status'], ['received', 'returned', 'cancelled', 'voided'], true)) $allPriorBatchesCompleted = false;
            }
            if ($currentBatch) {
                $skipped[] = ['id' => $groupId, 'reason' => 'توجد دفعة صرف مسجلة للشهر الحالي']; continue;
            }

            $oldStatus = (string)$group['verification_status'];
            if (!$history) {
                $priorAudit = $pdo->prepare('SELECT 1 FROM group_workflow_audit_log WHERE group_id = :id LIMIT 1');
                $priorAudit->execute(['id' => $groupId]);
                if ($oldStatus !== 'pending' || $priorAudit->fetchColumn()) {
                    $skipped[] = ['id' => $groupId, 'reason' => 'لا يمكن اعتبار المجموعة دورة أولى غير مستخدمة']; continue;
                }
            } else {
                $latest = $history[0];
                if ($oldStatus !== 'closed' || (string)$latest['month'] >= $month
                    || !in_array($latest['status'], ['received', 'returned'], true)
                    || !$allPriorBatchesCompleted) {
                    $skipped[] = ['id' => $groupId, 'reason' => 'الدورة السابقة لم تُغلق بالكامل أو توجد دفعة سابقة غير مكتملة']; continue;
                }
            }

            $update = $pdo->prepare(
                "UPDATE orphan_groups
                 SET verification_status = 'pending', submitted_at = NULL, approved_at = NULL, paid_at = NULL,
                     updated_at = NOW()
                 WHERE id = :id"
            );
            $update->execute(['id' => $groupId]);

            $audit = $pdo->prepare(
                "INSERT INTO audit_log
                 (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at)
                 VALUES (:actor, 'VGM_OPEN_MONTHLY_CYCLE', 'orphan_groups', :group_id, :old_values, :new_values, :ip, :agent, NOW())"
            );
            $audit->execute([
                'actor' => $actorId, 'group_id' => $groupId,
                'old_values' => json_encode(['verification_status' => $oldStatus], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'new_values' => json_encode(['verification_status' => 'pending', 'month' => $month], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli', 'agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'cli',
            ]);
            $opened[] = ['id' => $groupId, 'name' => (string)$group['group_name'], 'nanny_id' => (int)$nanny['nanny_id']];
            $notifications[] = [
                'user_id' => (int)$nanny['nanny_id'],
                'title' => 'تم فتح دورة المجموعة لشهر ' . $month,
                'body' => 'فتح نائب المدير العام دورة التوثيق والصرف للمجموعة «' . (string)$group['group_name'] . '» لشهر ' . $month . '.',
                'link' => APP_URL . 'modules/accounting/group_verification.php?group_id=' . $groupId,
            ];
        }

        if (!$opened) {
            $pdo->rollBack();
            return ['opened' => [], 'skipped' => $skipped, 'notification_attempts' => 0];
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    require_once __DIR__ . '/../accounting/lib_transaction_review.php';
    foreach ($notifications as $notification) {
        ak_transaction_review_notify_event(
            $notification['user_id'], $notification['title'], $notification['body'], $notification['link']
        );
    }

    return ['opened' => $opened, 'skipped' => $skipped, 'notification_attempts' => count($notifications)];
}
