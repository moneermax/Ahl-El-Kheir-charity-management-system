<?php
declare(strict_types=1);

/**
 * Atomically reactivate a sponsor and close a win-back campaign.
 *
 * The actor/time fields capture the first successful reactivation transition.
 * handled_by remains mutable operational ownership. The caller owns auditing
 * and user-facing messages; no incentive event or award is created here.
 *
 * @return array{sponsor_id:int,reactivated_by_user_id:int,reactivated_at:string}|null
 */
function wb_mark_returned(PDO $pdo, int $campaignId, int $actorUserId): ?array
{
    if ($campaignId <= 0 || $actorUserId <= 0) {
        throw new InvalidArgumentException('مرجع متابعة الاسترجاع أو المستخدم غير صالح.');
    }

    $startedHere = !$pdo->inTransaction();
    if ($startedHere) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, sponsor_id, status, reactivated_by_user_id, reactivated_at
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
        if ($sponsorUpdate->rowCount() !== 1) {
            throw new RuntimeException('تعذر تحديث حالة الكفيل؛ لم تكتمل إعادة التفعيل.');
        }

        $campaignUpdate = $pdo->prepare(
            "UPDATE winback_campaigns
             SET status = 'persuaded',
                 closed_at = NOW(),
                 handled_by = ?,
                 reactivated_by_user_id = COALESCE(reactivated_by_user_id, ?),
                 reactivated_at = COALESCE(reactivated_at, NOW())
             WHERE id = ? AND status IN ('open', 'contacted')"
        );
        $campaignUpdate->execute([$actorUserId, $actorUserId, $campaignId]);
        if ($campaignUpdate->rowCount() !== 1) {
            throw new RuntimeException('تغيرت حالة متابعة الاسترجاع أثناء العملية؛ تم إيقاف إعادة التفعيل.');
        }

        $attributionStmt = $pdo->prepare(
            "SELECT reactivated_by_user_id, reactivated_at
             FROM winback_campaigns WHERE id = ?"
        );
        $attributionStmt->execute([$campaignId]);
        $attribution = $attributionStmt->fetch(PDO::FETCH_ASSOC);
        if (!$attribution || empty($attribution['reactivated_by_user_id']) || empty($attribution['reactivated_at'])) {
            throw new RuntimeException('تعذر التحقق من حفظ بيانات إسناد إعادة التفعيل.');
        }

        $result = [
            'sponsor_id' => $sponsorId,
            'reactivated_by_user_id' => (int)$attribution['reactivated_by_user_id'],
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
