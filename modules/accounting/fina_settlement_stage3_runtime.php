<?php
// TEMPORARY Stage 3 runtime verification harness.
// Remove this file immediately after the controlled verification passes.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/fina_settlement_lib.php';

Session::start();
if (!Session::isLoggedIn() || Session::getUserRole() !== 'financial_manager') {
    http_response_code(403);
    exit('Stage 3 runtime verification is restricted to the Financial Manager.');
}

function stage3_harness_out(string $status, string $message, array $data = []): void
{
    $payload = ['status' => $status, 'message' => $message, 'data' => $data];
    echo '<pre style="font-family:Consolas,monospace;white-space:pre-wrap;">' . htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') . '</pre>';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>Stage 3 Runtime Verification</title></head>
    <body style="font-family:Arial,sans-serif;max-width:900px;margin:40px auto;line-height:1.8">
      <h1>تحقق تشغيلي مؤقت — Fina Settlement Stage 3</h1>
      <p>هذا ملف تحقق مؤقت ومحمي بجلسة المدير المالي. سيستخدم التحصيلين المعتمدين الحاليين #3 و#4 فقط.</p>
      <ul>
        <li>التحصيل #3: تسوية كاملة 50,000 SDG.</li>
        <li>التحصيل #4: تسوية جزئية 100,000 SDG من أصل 200,000 SDG.</li>
        <li>سيتم اختبار رفض تجاوز الرصيد ورفض مرجع التحويل الفارغ.</li>
        <li>لن يتم تعديل التحصيلات الأصلية أو قيودها.</li>
        <li>سيتم إنشاء قيود تسوية فعلية تجريبية في قاعدة التطوير.</li>
      </ul>
      <form method="post">
        <label>اكتب RUN_STAGE3 للتأكيد:</label><br>
        <input name="confirm" autocomplete="off" style="padding:8px;width:260px"><br><br>
        <button type="submit" style="padding:10px 20px">تشغيل التحقق</button>
      </form>
    </body></html>
    <?php
    exit;
}

if (($_POST['confirm'] ?? '') !== 'RUN_STAGE3') {
    http_response_code(400);
    stage3_harness_out('blocked', 'لم يتم إدخال عبارة التأكيد المطلوبة.');
    exit;
}

$created = [];
try {
    fina_settlement_ensure_tables();

    $settlementCount = (int)(dbFetchOne("SELECT COUNT(*) n FROM fina_settlements")['n'] ?? 0);
    $allocationCount = (int)(dbFetchOne("SELECT COUNT(*) n FROM fina_settlement_allocations")['n'] ?? 0);
    if ($settlementCount !== 0 || $allocationCount !== 0) {
        throw new RuntimeException('التحقق الآمن متوقف: توجد بالفعل سجلات تسوية/تخصيص، ولن يلمسها هذا الاختبار.');
    }

    $fixture = dbFetchAll("SELECT c.id,c.amount,c.currency_code,c.status,c.accounting_journal_id,je.status journal_status FROM fina_collections c LEFT JOIN journal_entries je ON je.id=c.accounting_journal_id WHERE c.id IN (3,4) ORDER BY c.id");
    if (count($fixture) !== 2) throw new RuntimeException('التحصيلان المتوقعان #3 و#4 غير متوفرين كما هو متوقع.');
    foreach ($fixture as $row) {
        $expected = ((int)$row['id'] === 3) ? 50000.00 : 200000.00;
        if ($row['status'] !== 'approved' || $row['currency_code'] !== APP_CURRENCY_CODE || round((float)$row['amount'],2) !== $expected || $row['journal_status'] !== 'posted') {
            throw new RuntimeException('بيانات fixture للتحصيل #' . $row['id'] . ' لا تطابق الحالة المتوقعة.');
        }
    }

    $beforeOutstanding = fina_settlement_outstanding_total();
    if ($beforeOutstanding !== 250000.00) throw new RuntimeException('الرصيد القابل للتسوية قبل الاختبار يجب أن يكون 250,000 SDG.');

    // Negative control: allocation must not exceed collection #3 remaining balance.
    $negativePassed = false;
    try {
        fina_settlement_create_draft([
            'settlement_date' => date('Y-m-d'),
            'amount' => 50001.00,
            'currency_code' => APP_CURRENCY_CODE,
            'payment_method' => 'bank_transfer',
            'remitting_account_id' => 2,
            'allocations' => [3 => 50001.00],
        ]);
    } catch (Throwable $e) {
        $negativePassed = true;
    }
    if (!$negativePassed) throw new RuntimeException('اختبار تجاوز الرصيد فشل: تم قبول تخصيص 50,001 على تحصيل 50,000.');

    // Positive control: full settlement of collection #3.
    $s1 = fina_settlement_create_draft([
        'settlement_date' => date('Y-m-d'),
        'amount' => 50000.00,
        'currency_code' => APP_CURRENCY_CODE,
        'payment_method' => 'bank_transfer',
        'remitting_account_id' => 2,
        'allocations' => [3 => 50000.00],
    ]);
    $created[] = $s1;
    fina_settlement_approve($s1);

    // Negative control: actual transfer requires a reference.
    $missingRefPassed = false;
    try {
        fina_settlement_transfer($s1, '');
    } catch (Throwable $e) {
        $missingRefPassed = true;
    }
    if (!$missingRefPassed) throw new RuntimeException('اختبار مرجع التحويل الفارغ فشل.');

    $j1 = fina_settlement_transfer($s1, 'STAGE3-RUNTIME-20260917-C3', null, date('Y-m-d'));
    fina_settlement_mark_reconciled($s1, 'Stage 3 controlled runtime verification — collection #3');
    fina_settlement_close($s1);

    // Positive control: partial settlement of collection #4.
    $s2 = fina_settlement_create_draft([
        'settlement_date' => date('Y-m-d'),
        'amount' => 100000.00,
        'currency_code' => APP_CURRENCY_CODE,
        'payment_method' => 'bank_transfer',
        'remitting_account_id' => 2,
        'allocations' => [4 => 100000.00],
    ]);
    $created[] = $s2;
    fina_settlement_approve($s2);
    $j2 = fina_settlement_transfer($s2, 'STAGE3-RUNTIME-20260917-C4-PARTIAL', null, date('Y-m-d'));
    fina_settlement_mark_reconciled($s2, 'Stage 3 controlled runtime verification — collection #4 partial');
    fina_settlement_close($s2);

    $final = dbFetchAll("SELECT id,settlement_code,amount,status,remitting_account_id,transfer_reference,settlement_journal_id FROM fina_settlements WHERE id IN (?,?) ORDER BY id", [$s1,$s2]);
    $alloc = dbFetchAll("SELECT settlement_id,fina_collection_id,allocated_amount FROM fina_settlement_allocations WHERE settlement_id IN (?,?) ORDER BY settlement_id", [$s1,$s2]);
    $orig = dbFetchAll("SELECT id,status,accounting_journal_id FROM fina_collections WHERE id IN (3,4) ORDER BY id");
    $outstanding = fina_settlement_outstanding_total();

    if ($outstanding !== 100000.00) throw new RuntimeException('الرصيد القابل للتسوية بعد الاختبار يجب أن يكون 100,000 SDG.');
    foreach ($orig as $row) {
        if ($row['status'] !== 'approved') throw new RuntimeException('تم تغيير حالة التحصيل الأصلي #' . $row['id'] . ' بشكل غير متوقع.');
        $j = dbFetchOne("SELECT status FROM journal_entries WHERE id=? LIMIT 1", [(int)$row['accounting_journal_id']]);
        if (!$j || $j['status'] !== 'posted') throw new RuntimeException('قيد التحصيل الأصلي #' . $row['accounting_journal_id'] . ' لم يعد مرحلاً.');
    }

    stage3_harness_out('passed', 'نجح التحقق التشغيلي المرحلي Stage 3.', [
        'before_outstanding' => $beforeOutstanding,
        'negative_overallocation_rejected' => true,
        'negative_missing_transfer_reference_rejected' => true,
        'full_settlement_id' => $s1,
        'full_settlement_journal_id' => $j1,
        'partial_settlement_id' => $s2,
        'partial_settlement_journal_id' => $j2,
        'settlements' => $final,
        'allocations' => $alloc,
        'original_collections_unchanged' => $orig,
        'after_outstanding' => $outstanding,
    ]);
} catch (Throwable $e) {
    stage3_harness_out('failed', $e->getMessage(), ['created_settlement_ids' => $created]);
}
