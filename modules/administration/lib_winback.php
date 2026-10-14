<?php
declare(strict_types=1);

/**
 * Atomically reactivate a sponsor and close a win-back campaign.
 *
 * The actor/time fields capture the first successful reactivation transition.
 * handled_by remains mutable operational ownership. The caller owns auditing
 * and user-facing messages; no incentive event or award is created here.
 *
 * @return array{sponsor_id:int,reactivated_by_user_id:int,reactivation_confirmed_by_user_id:int,reactivated_at:string}|null
 */
function wb_mark_returned(PDO $pdo, int $campaignId, int $reactivatedByUserId, int $confirmedByUserId): ?array
{
    if ($campaignId <= 0 || $reactivatedByUserId <= 0 || $confirmedByUserId <= 0) {
        throw new InvalidArgumentException('مرجع متابعة الاسترجاع أو المستخدم غير صالح.');
    }

    // Validate the selected recipient against the live employee/user relationship.
    // Exactly one currently working employee must map to this user.
    $employeeStmt = $pdo->prepare(
        "SELECT e.id
         FROM employees e
         JOIN users u ON u.id = e.user_id AND u.is_active = 1
         JOIN hr_employment_states es
           ON es.id = e.employment_state_id
          AND es.category = 'working'
          AND es.is_active = 1
         WHERE e.user_id = ?
         ORDER BY e.id
         LIMIT 2"
    );
    $employeeStmt->execute([$reactivatedByUserId]);
    $employeeIds = array_map('intval', $employeeStmt->fetchAll(PDO::FETCH_COLUMN));
    if (count($employeeIds) !== 1 || $employeeIds[0] <= 0) {
        throw new InvalidArgumentException('The selected reactivator must map to exactly one active employee.');
    }

    $startedHere = !$pdo->inTransaction();
    if ($startedHere) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, sponsor_id, status, reactivated_by_user_id, reactivation_confirmed_by_user_id, reactivated_at
             FROM winback_campaigns
             WHERE id = ?
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$campaign || !in_array($campaign['status'], ['open', 'contacted'], true)) {
            if ($startedHere) {
                $pdo->commit();
            }
            return null;
        }

        $sponsorId = (int)$campaign['sponsor_id'];
        $sponsorUpdate = $pdo->prepare(
            "UPDATE sponsors SET status = 'active', updated_at = NOW() WHERE id = ?"
        );
        $sponsorUpdate->execute([$sponsorId]);
        $sponsorCheck = $pdo->prepare("SELECT id FROM sponsors WHERE id = ? LIMIT 1");
        $sponsorCheck->execute([$sponsorId]);
        if (!$sponsorCheck->fetchColumn()) {
            throw new RuntimeException('تعذر العثور على الكفيل؛ لم تكتمل إعادة التفعيل.');
        }

        $campaignUpdate = $pdo->prepare(
            "UPDATE winback_campaigns
             SET status = 'persuaded',
                 closed_at = NOW(),
                 handled_by = ?,
                 reactivated_by_user_id = COALESCE(reactivated_by_user_id, ?),
                 reactivation_confirmed_by_user_id = COALESCE(reactivation_confirmed_by_user_id, ?),
                 reactivated_at = COALESCE(reactivated_at, NOW())
             WHERE id = ? AND status IN ('open', 'contacted')"
        );
        $campaignUpdate->execute([$confirmedByUserId, $reactivatedByUserId, $confirmedByUserId, $campaignId]);
        if ($campaignUpdate->rowCount() !== 1) {
            throw new RuntimeException('تغيرت حالة متابعة الاسترجاع أثناء العملية؛ تم إيقاف إعادة التفعيل.');
        }

        $attributionStmt = $pdo->prepare(
            "SELECT reactivated_by_user_id, reactivation_confirmed_by_user_id, reactivated_at
             FROM winback_campaigns WHERE id = ?"
        );
        $attributionStmt->execute([$campaignId]);
        $attribution = $attributionStmt->fetch(PDO::FETCH_ASSOC);
        if (!$attribution || empty($attribution['reactivated_by_user_id']) || empty($attribution['reactivation_confirmed_by_user_id']) || empty($attribution['reactivated_at'])) {
            throw new RuntimeException('تعذر التحقق من حفظ بيانات إسناد إعادة التفعيل.');
        }

        $result = [
            'sponsor_id' => $sponsorId,
            'reactivated_by_user_id' => (int)$attribution['reactivated_by_user_id'],
            'reactivation_confirmed_by_user_id' => (int)$attribution['reactivation_confirmed_by_user_id'],
            'reactivated_at' => (string)$attribution['reactivated_at'],
        ];

        if ($startedHere) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $e) {
        if ($startedHere && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
