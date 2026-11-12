<?php
declare(strict_types=1);

/**
 * Monthly sponsorship agreement and actual-receipt component contract.
 * Read/write helpers only; schema is managed by the dated migration.
 */

if (!function_exists('ak_spma_parse_period')) {
function ak_spma_parse_period(string $period): ?string
{
    $period = trim($period);
    $date = DateTimeImmutable::createFromFormat('!F/Y', $period);
    if (!$date || $date->format('F/Y') !== $period) return null;
    return $date->format('Y-m-01');
}
}

if (!function_exists('ak_spma_latest_agreement')) {
function ak_spma_latest_agreement(int $sponsorshipId, string $period, bool $forUpdate = false): ?array
{
    if ($sponsorshipId <= 0) throw new InvalidArgumentException('رقم الكفالة غير صالح.');
    $month = ak_spma_parse_period($period);
    if ($month === null) throw new InvalidArgumentException('شهر اتفاق الكفالة غير صالح.');
    return dbFetchOne(
        "SELECT a.*
         FROM sponsorship_monthly_agreements a
         WHERE a.sponsorship_id = ? AND a.covered_month = ?
         ORDER BY a.version_no DESC, a.id DESC
         LIMIT 1" . ($forUpdate ? " FOR UPDATE" : ""),
        [$sponsorshipId, $month]
    );
}
}

if (!function_exists('ak_spma_build_submission_snapshot')) {
function ak_spma_build_submission_snapshot(
    int $sponsorshipId,
    string $period,
    $actualSponsorshipRaw,
    $actualServiceFeeRaw,
    bool $forUpdate = false
): array {
    $agreement = ak_spma_latest_agreement($sponsorshipId, $period, $forUpdate);
    if (!$agreement) {
        throw new RuntimeException('لا توجد اتفاقية شهرية مسجلة لهذه الكفالة عن ' . $period . '. يرجى مراجعة المدير المالي قبل تسجيل التحصيل.');
    }
    $parse = static function ($raw, string $label): float {
        $value = trim(str_replace(',', '', (string)$raw));
        if ($value === '' || !is_numeric($value)) throw new RuntimeException('أدخل ' . $label . ' كقيمة رقمية صحيحة.');
        $number = (float)$value;
        if (!is_finite($number) || $number < 0 || $number > 9999999999.99) {
            throw new RuntimeException('قيمة ' . $label . ' خارج النطاق المسموح.');
        }
        return round($number, 2);
    };
    $actualSponsorship = $parse($actualSponsorshipRaw, 'الكفالة المستلمة');
    $actualServiceFee = $parse($actualServiceFeeRaw, 'رسوم الخدمة المستلمة');
    $total = round($actualSponsorship + $actualServiceFee, 2);
    if ($total <= 0) throw new RuntimeException('إجمالي التحصيل الفعلي يجب أن يكون أكبر من صفر.');
    if ($total > 9999999999.99) throw new RuntimeException('إجمالي التحصيل يتجاوز الحد الأقصى المسموح به.');
    return [
        'component_model' => 'monthly_agreement',
        'monthly_agreement_id' => (int)$agreement['id'],
        'agreed_sponsorship_amount' => round((float)$agreement['agreed_sponsorship_amount'], 2),
        'agreed_service_fee_amount' => round((float)$agreement['agreed_service_fee_amount'], 2),
        'actual_sponsorship_received' => $actualSponsorship,
        'actual_service_fee_received' => $actualServiceFee,
        'amount' => $total,
        'currency_code' => (string)$agreement['currency_code'],
    ];
}
}

if (!function_exists('ak_spma_resolve_allocation_commitment')) {
function ak_spma_resolve_allocation_commitment(array $transaction, array $snapshots, array $sponsorship): float
{
    if (count($snapshots) > 1) {
        throw new RuntimeException('توجد لالتزامات الشهر قيم متعارضة في تخصيصات سابقة.');
    }
    $hasSnapshot = count($snapshots) === 1;
    $snapshotCommitment = $hasSnapshot ? round((float)$snapshots[0]['commitment_amount'], 2) : null;
    if ((string)($transaction['component_model'] ?? 'legacy') === 'monthly_agreement') {
        $agreedCommitment = round((float)($transaction['agreed_sponsorship_amount'] ?? 0), 2);
        $agreementId = (int)($transaction['monthly_agreement_id'] ?? 0);
        if ($agreementId <= 0 || $agreedCommitment <= 0) {
            throw new RuntimeException('لقطة اتفاق الكفالة الشهرية غير مكتملة؛ تعذر إنشاء التخصيص.');
        }
        $commitment = $hasSnapshot ? $snapshotCommitment : $agreedCommitment;
        if ($commitment !== $agreedCommitment) {
            throw new RuntimeException('قيمة الاتفاقية لا تطابق الالتزام المثبت بتخصيص سابق لهذا الشهر.');
        }
    } else {
        $commitment = $hasSnapshot ? $snapshotCommitment : round((float)$sponsorship['monthly_amount'], 2);
    }
    if ($commitment <= 0) throw new RuntimeException('قيمة الالتزام الشهري للكفالة غير صالحة.');
    return $commitment;
}
}

if (!function_exists('ak_spma_resolve_report_commitment')) {
function ak_spma_resolve_report_commitment(?array $allocation, ?array $agreement, float $legacyMonthlyAmount): float
{
    if ($allocation && (int)($allocation['commitment_versions'] ?? 0) > 1) {
        throw new RuntimeException('توجد قيم التزام متعارضة لهذا الشهر.');
    }
    if ($allocation && (int)($allocation['commitment_versions'] ?? 0) === 1) {
        $commitment = round((float)$allocation['commitment_amount'], 2);
    } elseif ($agreement) {
        $commitment = round((float)$agreement['agreed_sponsorship_amount'], 2);
    } else {
        $commitment = round($legacyMonthlyAmount, 2);
    }
    if ($commitment <= 0) throw new RuntimeException('قيمة الالتزام الشهري غير صالحة.');
    return $commitment;
}
}

if (!function_exists('ak_spma_validate_snapshot_for_posting')) {
function ak_spma_validate_snapshot_for_posting(array $transaction): array
{
    if ((string)($transaction['component_model'] ?? 'legacy') !== 'monthly_agreement') {
        return ['is_monthly_agreement' => false];
    }
    $agreementId = (int)($transaction['monthly_agreement_id'] ?? 0);
    $agreedSponsorship = $transaction['agreed_sponsorship_amount'] ?? null;
    $agreedFee = $transaction['agreed_service_fee_amount'] ?? null;
    $actualSponsorship = $transaction['actual_sponsorship_received'] ?? null;
    $actualFee = $transaction['actual_service_fee_received'] ?? null;
    if ($agreementId <= 0 || $agreedSponsorship === null || $agreedFee === null
        || $actualSponsorship === null || $actualFee === null) {
        throw new RuntimeException('لقطة اتفاق الكفالة أو مكونات التحصيل غير مكتملة؛ تم إيقاف الترحيل.');
    }
    $sponsorship = round((float)$actualSponsorship, 2);
    $serviceFee = round((float)$actualFee, 2);
    $agreement = dbFetchOne(
        "SELECT id, sponsorship_id, covered_month, agreed_sponsorship_amount,
                agreed_service_fee_amount, currency_code
         FROM sponsorship_monthly_agreements WHERE id = ? LIMIT 1",
        [$agreementId]
    );
    $expectedMonth = ak_spma_parse_period((string)($transaction['payment_period'] ?? ''));
    if (!$agreement || $expectedMonth === null
        || (int)$agreement['sponsorship_id'] !== (int)($transaction['sponsorship_id'] ?? 0)
        || (string)$agreement['covered_month'] !== $expectedMonth
        || round((float)$agreement['agreed_sponsorship_amount'], 2) !== round((float)$agreedSponsorship, 2)
        || round((float)$agreement['agreed_service_fee_amount'], 2) !== round((float)$agreedFee, 2)
        || strtoupper((string)$agreement['currency_code']) !== strtoupper((string)($transaction['currency_code'] ?? ''))) {
        throw new RuntimeException('لقطة الدفعة لا تطابق إصدار اتفاقية الكفالة المرتبط بها؛ تم إيقاف الترحيل.');
    }
    $total = round((float)($transaction['amount'] ?? 0), 2);
    if ($sponsorship < 0 || $serviceFee < 0 || $total <= 0
        || round($sponsorship + $serviceFee, 2) !== $total) {
        throw new RuntimeException('إجمالي التحصيل لا يطابق مجموع الكفالة ورسوم الخدمة الفعلية.');
    }
    if ($serviceFee > 0) {
        throw new RuntimeException('لا يمكن ترحيل رسوم خدمة الكفالة قبل اعتماد المعالجة المحاسبية وتحديد حساب الإيراد المعتمد.');
    }
    return [
        'is_monthly_agreement' => true,
        'sponsorship_received' => $sponsorship,
        'service_fee_received' => $serviceFee,
        'total_received' => $total,
    ];
}
}
