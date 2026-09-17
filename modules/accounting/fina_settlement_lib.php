<?php
// Fina Al-Khair settlement accounting engine.
// Procedural PHP only. Settlement execution is Financial Manager (FM) only.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/fina_lib.php';

function fina_settlement_require_fm(): int
{
    Session::start();
    if (!Session::isLoggedIn() || Session::getUserRole() !== 'financial_manager') {
        throw new RuntimeException('إجراء تسوية فينا الخير متاح للمدير المالي فقط.');
    }
    $uid = (int) Session::getUserId();
    if ($uid <= 0) throw new RuntimeException('تعذر تحديد مستخدم المدير المالي.');
    return $uid;
}

function fina_settlement_ensure_tables(): void
{
    $s = dbFetchOne("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='fina_settlements'");
    $a = dbFetchOne("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='fina_settlement_allocations'");
    if ((int)($s['n'] ?? 0) !== 1 || (int)($a['n'] ?? 0) !== 1) {
        throw new RuntimeException('جداول تسوية فينا الخير غير مهيأة. يجب تطبيق migration الخاصة بالتسوية أولاً.');
    }
}

function fina_settlement_normalize_allocations(array $allocations): array
{
    $out = [];
    foreach ($allocations as $collectionId => $amount) {
        if (is_array($amount)) {
            $collectionId = $amount['collection_id'] ?? $collectionId;
            $amount = $amount['amount'] ?? $amount['allocated_amount'] ?? 0;
        }
        $cid = (int)$collectionId;
        $value = round((float)str_replace(',', '', (string)$amount), 2);
        if ($cid <= 0 || $value <= 0) continue;
        if (isset($out[$cid])) $out[$cid] = round($out[$cid] + $value, 2);
        else $out[$cid] = $value;
    }
    return $out;
}

function fina_settlement_validate_remitting_account(int $accountId): array
{
    if ($accountId <= 0) throw new RuntimeException('حساب التحويل غير صالح.');
    $row = dbFetchOne("SELECT id,code,name_ar,account_type,is_active FROM accounts WHERE id=? LIMIT 1", [$accountId]);
    if (!$row) throw new RuntimeException('حساب التحويل غير موجود.');
    if ((int)$row['is_active'] !== 1) throw new RuntimeException('حساب التحويل غير نشط.');
    if ($row['account_type'] !== 'asset') throw new RuntimeException('حساب التحويل يجب أن يكون حساب أصل مالي.');
    if (!in_array((string)$row['code'], ['1100','1200','1300'], true)) {
        throw new RuntimeException('حساب التحويل يجب أن يكون من حسابات الخزينة المسموح بها: 1100 أو 1200 أو 1300.');
    }
    return $row;
}

function fina_settlement_validate_date(string $date): string
{
    $date = trim($date);
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) throw new RuntimeException('تاريخ التسوية غير صالح.');
    return $date;
}

function fina_settlement_collection_available(int $collectionId, ?int $forSettlementId = null): array
{
    $collection = dbFetchOne("SELECT id,amount,currency_code,payment_method,collection_date,status,accounting_journal_id FROM fina_collections WHERE id=? LIMIT 1 FOR UPDATE", [$collectionId]);
    if (!$collection) throw new RuntimeException('تحصيل فينا الخير غير موجود: #' . $collectionId);
    if ($collection['status'] !== 'approved') throw new RuntimeException('لا يمكن تسوية تحصيل غير معتمد: #' . $collectionId);
    if ((string)$collection['currency_code'] !== APP_CURRENCY_CODE) throw new RuntimeException('عملة تحصيل فينا الخير لا تطابق عملة النظام الحالية.');

    $journalId = (int)($collection['accounting_journal_id'] ?? 0);
    if ($journalId <= 0) {
        $journal = dbFetchOne("SELECT id FROM journal_entries WHERE reference_type='fina_collection' AND reference_id=? AND status='posted' ORDER BY id ASC LIMIT 1", [$collectionId]);
        $journalId = (int)($journal['id'] ?? 0);
    }
    if ($journalId <= 0) throw new RuntimeException('التحصيل المعتمد #' . $collectionId . ' لا يملك قيد تحصيل مرحّلاً.');
    $journal = dbFetchOne("SELECT id,status FROM journal_entries WHERE id=? LIMIT 1", [$journalId]);
    if (!$journal || $journal['status'] !== 'posted') throw new RuntimeException('قيد التحصيل #' . $journalId . ' ليس مرحّلاً.');

    $already = dbFetchOne(
        "SELECT COALESCE(SUM(a.allocated_amount),0) allocated
           FROM fina_settlement_allocations a
           INNER JOIN fina_settlements s ON s.id=a.settlement_id
          WHERE a.fina_collection_id=? AND s.status <> 'cancelled' AND (? IS NULL OR s.id <> ?)",
        [$collectionId, $forSettlementId, $forSettlementId]
    );
    $allocated = round((float)($already['allocated'] ?? 0), 2);
    $remaining = round((float)$collection['amount'] - $allocated, 2);
    if ($remaining <= 0) throw new RuntimeException('تحصيل فينا الخير #' . $collectionId . ' تمت تسويته بالكامل سابقاً.');
    return ['collection'=>$collection,'journal_id'=>$journalId,'allocated'=>$allocated,'remaining'=>$remaining];
}

function fina_settlement_generate_code(): string
{
    $row = dbFetchOne("SELECT COALESCE(MAX(CASE WHEN settlement_code REGEXP '^FINA-SET-[0-9]+$' THEN CAST(SUBSTRING(settlement_code,10) AS UNSIGNED) ELSE 0 END),0) n FROM fina_settlements");
    $n = (int)($row['n'] ?? 0) + 1;
    return 'FINA-SET-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
}

function fina_settlement_create_draft(array $data, ?int $fmUserId = null): int
{
    $uid = $fmUserId ?? fina_settlement_require_fm();
    if ($fmUserId !== null && Session::getUserRole() !== 'financial_manager') throw new RuntimeException('إجراء تسوية فينا الخير متاح للمدير المالي فقط.');
    fina_settlement_ensure_tables();

    $date = fina_settlement_validate_date((string)($data['settlement_date'] ?? ''));
    $amount = round((float)str_replace(',', '', (string)($data['amount'] ?? 0)), 2);
    if ($amount <= 0) throw new RuntimeException('مبلغ التسوية يجب أن يكون أكبر من صفر.');
    $currency = (string)($data['currency_code'] ?? APP_CURRENCY_CODE);
    if ($currency !== APP_CURRENCY_CODE) throw new RuntimeException('تسويات فينا الخير تستخدم عملة النظام: ' . APP_CURRENCY_CODE . '.');
    $method = trim((string)($data['payment_method'] ?? ''));
    if (!in_array($method, ['cash','bank_transfer','credit_card','mobile','other'], true)) throw new RuntimeException('طريقة تحويل التسوية غير صالحة.');
    $account = fina_settlement_validate_remitting_account((int)($data['remitting_account_id'] ?? 0));
    $allocations = fina_settlement_normalize_allocations((array)($data['allocations'] ?? []));
    if (!$allocations) throw new RuntimeException('يجب تحديد تحصيل واحد على الأقل لتخصيص التسوية.');
    $sum = round(array_sum($allocations), 2);
    if ($sum !== $amount) throw new RuntimeException('إجمالي تخصيصات التسوية يجب أن يساوي مبلغ التسوية.');

    db()->beginTransaction();
    try {
        $checked = [];
        foreach ($allocations as $cid => $allocated) {
            $available = fina_settlement_collection_available((int)$cid);
            if ($allocated > $available['remaining']) throw new RuntimeException('المبلغ المخصص للتحصيل #' . $cid . ' يتجاوز المتبقي القابل للتسوية: ' . number_format($available['remaining'],2));
            $checked[] = [(int)$cid, $allocated];
        }

        $code = fina_settlement_generate_code();
        dbExecute("INSERT INTO fina_settlements (settlement_code,settlement_date,amount,currency_code,payment_method,remitting_account_id,status,created_by) VALUES (?,?,?,?,?,?,'draft',?)", [$code,$date,$amount,$currency,$method,(int)$account['id'],$uid]);
        $settlementId = (int)dbLastInsertId();
        if ($settlementId <= 0) throw new RuntimeException('تعذر إنشاء سجل تسوية فينا الخير.');
        foreach ($checked as [$cid,$allocated]) {
            dbExecute("INSERT INTO fina_settlement_allocations (settlement_id,fina_collection_id,allocated_amount,created_by) VALUES (?,?,?,?)", [$settlementId,$cid,$allocated,$uid]);
        }
        db()->commit();
        return $settlementId;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function fina_settlement_approve(int $settlementId, ?int $fmUserId = null): void
{
    $uid = $fmUserId ?? fina_settlement_require_fm();
    fina_settlement_ensure_tables();
    db()->beginTransaction();
    try {
        $s = dbFetchOne("SELECT * FROM fina_settlements WHERE id=? LIMIT 1 FOR UPDATE", [$settlementId]);
        if (!$s) throw new RuntimeException('سجل التسوية غير موجود.');
        if ($s['status'] !== 'draft') throw new RuntimeException('لا يمكن اعتماد التسوية من حالتها الحالية.');
        if ((string)$s['currency_code'] !== APP_CURRENCY_CODE) throw new RuntimeException('عملة التسوية غير صالحة.');
        if ((float)$s['amount'] <= 0) throw new RuntimeException('مبلغ التسوية غير صالح.');
        $allocs = dbFetchAll("SELECT fina_collection_id,allocated_amount FROM fina_settlement_allocations WHERE settlement_id=? ORDER BY id FOR UPDATE", [$settlementId]);
        $sum = 0.0;
        foreach ($allocs as $a) {
            $sum = round($sum + (float)$a['allocated_amount'], 2);
            $available = fina_settlement_collection_available((int)$a['fina_collection_id'], $settlementId);
            if ((float)$a['allocated_amount'] <= 0 || (float)$a['allocated_amount'] > $available['remaining']) throw new RuntimeException('تخصيص التسوية للتحصيل #' . $a['fina_collection_id'] . ' غير صالح.');
        }
        if (round($sum,2) !== round((float)$s['amount'],2)) throw new RuntimeException('إجمالي تخصيصات التسوية لا يساوي مبلغها.');
        fina_settlement_validate_remitting_account((int)$s['remitting_account_id']);
        dbExecute("UPDATE fina_settlements SET status='approved',approved_by=?,approved_at=NOW() WHERE id=? AND status='draft'", [$uid,$settlementId]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function fina_settlement_transfer(int $settlementId, string $transferReference = '', ?string $evidencePath = null, ?string $actualDate = null, ?int $fmUserId = null): int
{
    $uid = $fmUserId ?? fina_settlement_require_fm();
    fina_settlement_ensure_tables();
    $date = fina_settlement_validate_date($actualDate ?? date('Y-m-d'));
    $transferReference = trim($transferReference);
    if ($transferReference === '') throw new RuntimeException('مرجع التحويل الفعلي مطلوب عند تنفيذ التسوية.');

    db()->beginTransaction();
    try {
        $s = dbFetchOne("SELECT * FROM fina_settlements WHERE id=? LIMIT 1 FOR UPDATE", [$settlementId]);
        if (!$s) throw new RuntimeException('سجل التسوية غير موجود.');
        if ($s['status'] !== 'approved') throw new RuntimeException('يجب اعتماد التسوية قبل تسجيل التحويل الفعلي.');
        if ((string)$s['currency_code'] !== APP_CURRENCY_CODE) throw new RuntimeException('عملة التسوية غير صالحة.');
        $account = fina_settlement_validate_remitting_account((int)$s['remitting_account_id']);
        $allocs = dbFetchAll("SELECT fina_collection_id,allocated_amount FROM fina_settlement_allocations WHERE settlement_id=? ORDER BY id FOR UPDATE", [$settlementId]);
        if (!$allocs) throw new RuntimeException('لا توجد تخصيصات لهذه التسوية.');
        $sum = 0.0;
        foreach ($allocs as $a) {
            $sum = round($sum + (float)$a['allocated_amount'], 2);
            $available = fina_settlement_collection_available((int)$a['fina_collection_id'], $settlementId);
            if ((float)$a['allocated_amount'] <= 0 || (float)$a['allocated_amount'] > $available['remaining']) throw new RuntimeException('تخصيص التسوية للتحصيل #' . $a['fina_collection_id'] . ' لم يعد متاحاً.');
        }
        if (round($sum,2) !== round((float)$s['amount'],2)) throw new RuntimeException('إجمالي تخصيصات التسوية لا يساوي مبلغها.');

        $liabilityId = fina_ensure_liability_account();
        $entryNo = (int)(dbFetchOne("SELECT COALESCE(MAX(CASE WHEN entry_code REGEXP '^JE-[0-9]+$' THEN CAST(SUBSTRING(entry_code,4) AS UNSIGNED) ELSE 0 END),0) n FROM journal_entries")['n'] ?? 0) + 1;
        $entryCode = 'JE-' . str_pad((string)$entryNo, 6, '0', STR_PAD_LEFT);
        $desc = 'تسوية فينا الخير ' . $s['settlement_code'] . ' — تحويل فعلي — ' . $s['currency_code'];
        dbExecute("INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by) VALUES (?,?,?,?,?,'posted',?)", [$entryCode,$date,$desc,'fina_settlement',$settlementId,$uid]);
        $journalId = (int)dbLastInsertId();
        if ($journalId <= 0) throw new RuntimeException('تعذر إنشاء قيد تسوية فينا الخير.');
        dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)", [$journalId,$liabilityId,$s['amount'],0,'خفض الالتزام المستحق لفينا الخير']);
        dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)", [$journalId,(int)$account['id'],0,$s['amount'],'تحويل فعلي إلى فينا الخير — '.$transferReference]);

        $lineTotals = dbFetchOne("SELECT COALESCE(SUM(debit),0) debit_total,COALESCE(SUM(credit),0) credit_total FROM journal_lines WHERE entry_id=?", [$journalId]);
        if (round((float)$lineTotals['debit_total'],2) !== round((float)$lineTotals['credit_total'],2) || round((float)$lineTotals['debit_total'],2) !== round((float)$s['amount'],2)) throw new RuntimeException('قيد تسوية فينا الخير غير متوازن.');

        dbExecute("UPDATE fina_settlements SET status='transferred',settlement_date=?,transfer_reference=?,evidence_path=?,settlement_journal_id=?,transferred_by=?,transferred_at=NOW() WHERE id=? AND status='approved'", [$date,$transferReference,$evidencePath ?: null,$journalId,$uid,$settlementId]);
        db()->commit();
        return $journalId;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function fina_settlement_mark_reconciled(int $settlementId, string $note = '', ?int $fmUserId = null): void
{
    $uid = $fmUserId ?? fina_settlement_require_fm();
    fina_settlement_ensure_tables();
    db()->beginTransaction();
    try {
        $s = dbFetchOne("SELECT id,status,settlement_journal_id FROM fina_settlements WHERE id=? LIMIT 1 FOR UPDATE", [$settlementId]);
        if (!$s) throw new RuntimeException('سجل التسوية غير موجود.');
        if ($s['status'] !== 'transferred' || (int)$s['settlement_journal_id'] <= 0) throw new RuntimeException('لا يمكن إقفال التسوية قبل تسجيل التحويل الفعلي.');
        $j = dbFetchOne("SELECT status FROM journal_entries WHERE id=? LIMIT 1", [(int)$s['settlement_journal_id']]);
        if (!$j || $j['status'] !== 'posted') throw new RuntimeException('قيد التسوية غير مرحّل.');
        dbExecute("UPDATE fina_settlements SET status='reconciled',reconciliation_note=?,reconciled_by=?,reconciled_at=NOW() WHERE id=? AND status='transferred'", [trim($note) ?: null,$uid,$settlementId]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function fina_settlement_close(int $settlementId, ?int $fmUserId = null): void
{
    $uid = $fmUserId ?? fina_settlement_require_fm();
    fina_settlement_ensure_tables();
    $affected = dbExecute("UPDATE fina_settlements SET status='closed' WHERE id=? AND status='reconciled'", [$settlementId]);
    if ($affected !== 1) throw new RuntimeException('لا يمكن إغلاق التسوية إلا بعد إتمام المطابقة.');
}

function fina_settlement_cancel(int $settlementId, string $note, ?int $fmUserId = null): void
{
    $uid = $fmUserId ?? fina_settlement_require_fm();
    fina_settlement_ensure_tables();
    $note = trim($note);
    if ($note === '') throw new RuntimeException('سبب إلغاء التسوية مطلوب.');
    $affected = dbExecute("UPDATE fina_settlements SET status='cancelled',cancelled_by=?,cancelled_at=NOW(),cancellation_note=? WHERE id=? AND status IN ('draft','approved')", [$uid,$note,$settlementId]);
    if ($affected !== 1) throw new RuntimeException('لا يمكن إلغاء التسوية من حالتها الحالية.');
}

function fina_settlement_outstanding_total(): float
{
    fina_settlement_ensure_tables();
    $row = dbFetchOne(
        "SELECT COALESCE(SUM(c.amount),0) total,
                COALESCE(SUM(COALESCE(a.allocated,0)),0) allocated
           FROM fina_collections c
           LEFT JOIN (
               SELECT a.fina_collection_id, SUM(a.allocated_amount) allocated
                 FROM fina_settlement_allocations a
                 INNER JOIN fina_settlements s ON s.id=a.settlement_id
                WHERE s.status <> 'cancelled'
                GROUP BY a.fina_collection_id
           ) a ON a.fina_collection_id=c.id
          WHERE c.status='approved' AND c.currency_code=?",
        [APP_CURRENCY_CODE]
    );
    return round(max(0, (float)($row['total'] ?? 0) - (float)($row['allocated'] ?? 0)), 2);
}
