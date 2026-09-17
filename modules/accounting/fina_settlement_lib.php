<?php
// Fina Al-Khair settlement accounting engine.
// Procedural PHP only. Settlement execution is Financial Manager (FM) only.
// 2300 is permanent. Production settlement is always the full currently owed balance.
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
        throw new RuntimeException('جداول تسوية فينا الخير غير مهيأة. يجب تطبيق migrations الخاصة بالتسوية أولاً.');
    }
}

function fina_settlement_validate_date(string $date): string
{
    $date = trim($date);
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) throw new RuntimeException('تاريخ التسوية غير صالح.');
    return $date;
}

function fina_settlement_generate_code(): string
{
    $row = dbFetchOne("SELECT COALESCE(MAX(CASE WHEN settlement_code REGEXP '^FINA-SET-[0-9]+$' THEN CAST(SUBSTRING(settlement_code,10) AS UNSIGNED) ELSE 0 END),0) n FROM fina_settlements");
    return 'FINA-SET-' . str_pad((string)((int)($row['n'] ?? 0) + 1), 6, '0', STR_PAD_LEFT);
}

function fina_settlement_payment_method(string $method): string
{
    $method = trim($method);
    if (!in_array($method, ['cash','bank_transfer','credit_card','mobile','other'], true)) {
        throw new RuntimeException('طريقة تحويل التسوية غير صالحة.');
    }
    return $method;
}

function fina_settlement_production_outstanding(): float
{
    fina_settlement_ensure_tables();
    $row = dbFetchOne(
        "SELECT COALESCE(SUM(c.amount),0) approved_total,
                COALESCE(SUM(CASE WHEN x.fina_collection_id IS NOT NULL THEN c.amount ELSE 0 END),0) settled_total
           FROM fina_collections c
           LEFT JOIN (
               SELECT DISTINCT a.fina_collection_id
                 FROM fina_settlement_allocations a
                 INNER JOIN fina_settlements s ON s.id=a.settlement_id
                WHERE s.is_test=0
                  AND s.status IN ('transferred','reconciled','closed')
           ) x ON x.fina_collection_id=c.id
          WHERE c.status='approved' AND c.currency_code=?",
        [APP_CURRENCY_CODE]
    );
    return round(max(0, (float)($row['approved_total'] ?? 0) - (float)($row['settled_total'] ?? 0)), 2);
}

function fina_settlement_collection_candidates(): array
{
    return dbFetchAll(
        "SELECT c.id,c.amount,c.currency_code,c.payment_method,c.collection_date,c.status,c.accounting_journal_id,
                fs.source_type,fs.source_name,s.full_name sponsor_name,s.sponsor_code
           FROM fina_collections c
           JOIN fina_sources fs ON fs.id=c.fina_source_id
           LEFT JOIN sponsors s ON s.id=fs.sponsor_id
           LEFT JOIN (
               SELECT DISTINCT a.fina_collection_id
                 FROM fina_settlement_allocations a
                 INNER JOIN fina_settlements st ON st.id=a.settlement_id
                WHERE st.is_test=0
                  AND st.status IN ('transferred','reconciled','closed')
           ) settled ON settled.fina_collection_id=c.id
          WHERE c.status='approved'
            AND c.currency_code=?
            AND settled.fina_collection_id IS NULL
          ORDER BY c.collection_date ASC,c.id ASC",
        [APP_CURRENCY_CODE]
    );
}

function fina_settlement_has_active_cycle(): bool
{
    $row = dbFetchOne("SELECT COUNT(*) n FROM fina_settlements WHERE is_test=0 AND status IN ('draft','approved')");
    return (int)($row['n'] ?? 0) > 0;
}

function fina_settlement_create_draft(array $data, ?int $fmUserId = null): int
{
    $uid = $fmUserId ?? fina_settlement_require_fm();
    if ($fmUserId !== null && Session::getUserRole() !== 'financial_manager') throw new RuntimeException('إجراء تسوية فينا الخير متاح للمدير المالي فقط.');
    fina_settlement_ensure_tables();

    $date = fina_settlement_validate_date((string)($data['settlement_date'] ?? ''));
    $currency = (string)($data['currency_code'] ?? APP_CURRENCY_CODE);
    if ($currency !== APP_CURRENCY_CODE) throw new RuntimeException('تسويات فينا الخير تستخدم عملة النظام: ' . APP_CURRENCY_CODE . '.');
    $method = fina_settlement_payment_method((string)($data['payment_method'] ?? ''));
    $outstanding = fina_settlement_production_outstanding();
    if ($outstanding <= 0) throw new RuntimeException('لا يوجد حالياً رصيد مستحق لتسوية فينا الخير.');
    if (fina_settlement_has_active_cycle()) throw new RuntimeException('توجد بالفعل مسودة أو تسوية معتمدة تنتظر التنفيذ. أكملها أولاً.');

    $amount = round((float)str_replace(',', '', (string)($data['amount'] ?? $outstanding)), 2);
    if ($amount !== $outstanding) throw new RuntimeException('تسوية فينا الخير يجب أن تغطي كامل الرصيد المستحق الحالي: ' . number_format($outstanding,2) . ' ' . APP_CURRENCY_CODE . '.');
    $candidates = fina_settlement_collection_candidates();
    if (!$candidates) throw new RuntimeException('لا توجد تحصيلات معتمدة متاحة لتكوين دورة التسوية الحالية.');

    db()->beginTransaction();
    try {
        $code = fina_settlement_generate_code();
        dbExecute("INSERT INTO fina_settlements (settlement_code,settlement_date,amount,currency_code,payment_method,remitting_account_id,status,is_test,created_by) VALUES (?,?,?,?,?,NULL,'draft',0,?)", [$code,$date,$amount,$currency,$method,$uid]);
        $settlementId = (int)dbLastInsertId();
        if ($settlementId <= 0) throw new RuntimeException('تعذر إنشاء سجل تسوية فينا الخير.');

        $sum = 0.0;
        foreach ($candidates as $c) {
            $value = round((float)$c['amount'], 2);
            if ($value <= 0) throw new RuntimeException('تحصيل فينا الخير #' . (int)$c['id'] . ' يحمل مبلغاً غير صالح.');
            $sum = round($sum + $value, 2);
            dbExecute("INSERT INTO fina_settlement_allocations (settlement_id,fina_collection_id,allocated_amount,created_by) VALUES (?,?,?,?)", [$settlementId,(int)$c['id'],$value,$uid]);
        }
        if ($sum !== $amount) throw new RuntimeException('مجموع التحصيلات المرشحة لا يساوي كامل الرصيد المستحق.');
        db()->commit();
        return $settlementId;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

function fina_settlement_validate_cycle_locked(array $s): array
{
    if ((int)$s['is_test'] === 1) throw new RuntimeException('سجلات الاختبار التاريخية لا تدخل في دورة التسوية الإنتاجية.');
    if ((string)$s['currency_code'] !== APP_CURRENCY_CODE) throw new RuntimeException('عملة التسوية غير صالحة.');
    if ((float)$s['amount'] <= 0) throw new RuntimeException('مبلغ التسوية غير صالح.');
    $current = fina_settlement_production_outstanding();
    if (round($current,2) !== round((float)$s['amount'],2)) {
        throw new RuntimeException('تغير الرصيد المستحق منذ إنشاء التسوية. يجب أن تغطي التسوية كامل الرصيد الحالي: ' . number_format($current,2) . ' ' . APP_CURRENCY_CODE . '.');
    }
    $allocs = dbFetchAll("SELECT fina_collection_id,allocated_amount FROM fina_settlement_allocations WHERE settlement_id=? ORDER BY id FOR UPDATE", [(int)$s['id']]);
    if (!$allocs) throw new RuntimeException('لا توجد تحصيلات مرتبطة بدورة التسوية.');
    $sum = 0.0;
    foreach ($allocs as $a) {
        $collection = dbFetchOne("SELECT id,amount,currency_code,status,accounting_journal_id FROM fina_collections WHERE id=? LIMIT 1 FOR UPDATE", [(int)$a['fina_collection_id']]);
        if (!$collection || $collection['status'] !== 'approved' || (string)$collection['currency_code'] !== APP_CURRENCY_CODE) throw new RuntimeException('أحد تحصيلات التسوية لم يعد معتمداً أو صالحاً.');
        $journalId = (int)($collection['accounting_journal_id'] ?? 0);
        if ($journalId <= 0) {
            $journal = dbFetchOne("SELECT id FROM journal_entries WHERE reference_type='fina_collection' AND reference_id=? AND status='posted' ORDER BY id ASC LIMIT 1", [(int)$collection['id']]);
            $journalId = (int)($journal['id'] ?? 0);
        }
        if ($journalId <= 0) throw new RuntimeException('التحصيل المعتمد #' . (int)$collection['id'] . ' لا يملك قيد تحصيل مرحّلاً.');
        $journal = dbFetchOne("SELECT id,status FROM journal_entries WHERE id=? LIMIT 1", [$journalId]);
        if (!$journal || $journal['status'] !== 'posted') throw new RuntimeException('قيد التحصيل #' . $journalId . ' ليس مرحّلاً.');
        if (round((float)$a['allocated_amount'],2) !== round((float)$collection['amount'],2)) throw new RuntimeException('دورة التسوية يجب أن تسوي كل تحصيل مرتبط بها بالكامل.');
        $sum = round($sum + (float)$a['allocated_amount'], 2);
    }
    if ($sum !== round((float)$s['amount'],2)) throw new RuntimeException('مجموع تحصيلات دورة التسوية لا يساوي مبلغها الكامل.');
    return $allocs;
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
        fina_settlement_validate_cycle_locked($s);
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
        fina_settlement_validate_cycle_locked($s);

        $liabilityId = fina_ensure_liability_account();
        $holdingId = fina_ensure_holding_account();
        $entryNo = (int)(dbFetchOne("SELECT COALESCE(MAX(CASE WHEN entry_code REGEXP '^JE-[0-9]+$' THEN CAST(SUBSTRING(entry_code,4) AS UNSIGNED) ELSE 0 END),0) n FROM journal_entries")['n'] ?? 0) + 1;
        $entryCode = 'JE-' . str_pad((string)$entryNo, 6, '0', STR_PAD_LEFT);
        $desc = 'تسوية دورة فينا الخير ' . $s['settlement_code'] . ' — تحويل كامل إلى فينا الخير — ' . $s['currency_code'];
        dbExecute("INSERT INTO journal_entries (entry_code,entry_date,description,reference_type,reference_id,status,created_by) VALUES (?,?,?,?,?,'posted',?)", [$entryCode,$date,$desc,'fina_settlement',$settlementId,$uid]);
        $journalId = (int)dbLastInsertId();
        if ($journalId <= 0) throw new RuntimeException('تعذر إنشاء قيد تسوية فينا الخير.');
        dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)", [$journalId,$liabilityId,$s['amount'],0,'تصفير الالتزام المستحق لفينا الخير للدورة الحالية']);
        dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)", [$journalId,$holdingId,0,$s['amount'],'تحويل أموال فينا الخير المحتفظ بها إلى فينا الخير — '.$transferReference]);
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
        $s = dbFetchOne("SELECT id,status,settlement_journal_id,is_test FROM fina_settlements WHERE id=? LIMIT 1 FOR UPDATE", [$settlementId]);
        if (!$s) throw new RuntimeException('سجل التسوية غير موجود.');
        if ((int)$s['is_test'] === 1) throw new RuntimeException('سجل اختبار تاريخي لا يدخل في دورة الإنتاج.');
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
    fina_settlement_require_fm();
    fina_settlement_ensure_tables();
    $affected = dbExecute("UPDATE fina_settlements SET status='closed' WHERE id=? AND is_test=0 AND status='reconciled'", [$settlementId]);
    if ($affected !== 1) throw new RuntimeException('لا يمكن إغلاق التسوية إلا بعد إتمام المطابقة.');
}

function fina_settlement_cancel(int $settlementId, string $note, ?int $fmUserId = null): void
{
    $uid = $fmUserId ?? fina_settlement_require_fm();
    fina_settlement_ensure_tables();
    $note = trim($note);
    if ($note === '') throw new RuntimeException('سبب إلغاء التسوية مطلوب.');
    $affected = dbExecute("UPDATE fina_settlements SET status='cancelled',cancelled_by=?,cancelled_at=NOW(),cancellation_note=? WHERE id=? AND is_test=0 AND status IN ('draft','approved')", [$uid,$note,$settlementId]);
    if ($affected !== 1) throw new RuntimeException('لا يمكن إلغاء التسوية من حالتها الحالية.');
}

function fina_settlement_outstanding_total(): float
{
    return fina_settlement_production_outstanding();
}
