<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_contract_salary.php';
require_once dirname(__DIR__) . '/accounting/lib.php';
Session::start();
$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'admin'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pdo = db(); $message = ''; $msgType = 'success';

dbExecute("CREATE TABLE IF NOT EXISTS hr_payroll_reversals (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 payroll_id INT UNSIGNED NOT NULL,
 original_entry_id INT UNSIGNED NOT NULL,
 reversal_entry_id INT UNSIGNED NOT NULL,
 reason VARCHAR(255) NOT NULL,
 reversed_by INT UNSIGNED NULL,
 reversed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_hr_payroll_reversal_payroll (payroll_id),
 KEY idx_hr_payroll_reversal_entry (reversal_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function hrReversePaidPayroll(int $payrollId, int $userId, string $reason): int
{
    if ($payrollId <= 0) throw new RuntimeException('سجل مسير الراتب غير صالح.');
    if ($userId <= 0) throw new RuntimeException('المستخدم المنفذ غير صالح.');
    $reason = trim($reason);
    if ($reason === '') throw new RuntimeException('سبب العكس مطلوب لأغراض التدقيق المحاسبي.');

    ak_ensure_tables();
    ak_seed_accounts();
    $pdo = db();
    $startedHere = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedHere = true;
    }

    try {
        $payroll = dbFetchOne(
            "SELECT id, status, accounting_status FROM payroll WHERE id=? LIMIT 1 FOR UPDATE",
            [$payrollId]
        );
        if (!$payroll) throw new RuntimeException('سجل مسير الراتب غير موجود.');
        if ($payroll['status'] !== 'paid') throw new RuntimeException('لا يمكن عكس مسير غير مصروف.');

        if (dbFetchOne("SELECT id FROM hr_payroll_reversals WHERE payroll_id=? LIMIT 1 FOR UPDATE", [$payrollId])) {
            throw new RuntimeException('تم عكس هذا المسير مسبقاً ولا يمكن إنشاء عكس ثانٍ.');
        }

        $original = dbFetchOne(
            "SELECT id, entry_code, entry_date, description, created_by
             FROM journal_entries
             WHERE reference_type='payroll' AND reference_id=? AND status='posted'
             ORDER BY id ASC LIMIT 1 FOR UPDATE",
            [$payrollId]
        );
        if (!$original) throw new RuntimeException('لا يوجد قيد محاسبي مرحّل يمكن عكسه لهذا المسير.');

        $existingReversal = dbFetchOne(
            "SELECT id FROM journal_entries
             WHERE reference_type='manual_void' AND reference_id=?
             LIMIT 1 FOR UPDATE",
            [(int)$original['id']]
        );
        if ($existingReversal) throw new RuntimeException('يوجد قيد عكس محاسبي سابق لهذا المسير.');

        $lines = dbFetchAll(
            "SELECT account_id, debit, credit, description
             FROM journal_lines WHERE entry_id=? ORDER BY id",
            [(int)$original['id']]
        );
        if (count($lines) < 2) throw new RuntimeException('القيد الأصلي لا يحتوي على أسطر محاسبية كافية للعكس.');

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lines as $line) {
            $debit = round((float)$line['debit'], 2);
            $credit = round((float)$line['credit'], 2);
            if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) {
                throw new RuntimeException('سطر القيد الأصلي غير صالح للعكس.');
            }
            $totalDebit += $debit;
            $totalCredit += $credit;
        }
        if (round($totalDebit, 2) !== round($totalCredit, 2) || round($totalDebit, 2) <= 0) {
            throw new RuntimeException('القيد الأصلي غير متوازن أو صفري ولا يمكن عكسه.');
        }

        $entryCode = 'JE-REV-PAY-' . $payrollId . '-' . date('YmdHis');
        dbExecute(
            "INSERT INTO journal_entries
                (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
             VALUES (?,?,?,?,?,'posted',?)",
            [
                $entryCode,
                $original['entry_date'],
                'عكس مسير راتب: ' . $payrollId . ' — ' . $reason,
                'manual_void',
                (int)$original['id'],
                $userId
            ]
        );
        $reversalId = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id'] ?? 0);
        if ($reversalId <= 0) throw new RuntimeException('تعذر إنشاء قيد العكس.');

        foreach ($lines as $line) {
            dbExecute(
                "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
                 VALUES (?,?,?,?,?)",
                [
                    $reversalId,
                    (int)$line['account_id'],
                    round((float)$line['credit'], 2),
                    round((float)$line['debit'], 2),
                    'عكس: ' . ((string)($line['description'] ?? ''))
                ]
            );
        }

        $check = dbFetchOne(
            "SELECT ROUND(SUM(debit),2) AS debit_total, ROUND(SUM(credit),2) AS credit_total,
                    COUNT(*) AS line_count
             FROM journal_lines WHERE entry_id=?",
            [$reversalId]
        );
        if ((int)($check['line_count'] ?? 0) < 2 ||
            round((float)$check['debit_total'], 2) !== round((float)$check['credit_total'], 2) ||
            round((float)$check['debit_total'], 2) <= 0) {
            throw new RuntimeException('قيد العكس الناتج غير متوازن أو صفري.');
        }

        $updated = dbExecute(
            "UPDATE journal_entries
             SET status='voided', voided_at=NOW(), voided_by=?, void_reason=?
             WHERE id=? AND status='posted'",
            [$userId, $reason, (int)$original['id']]
        );
        if ($updated !== 1) throw new RuntimeException('تعذر إغلاق القيد المحاسبي الأصلي.');

        if ($startedHere) $pdo->commit();
        return $reversalId;
    } catch (Throwable $e) {
        if ($startedHere && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

try {
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reverse') {
  $payrollId=(int)($_POST['payroll_id']??0); $reason=trim((string)($_POST['reason']??''));
  if($payrollId<=0) throw new RuntimeException('سجل مسير الراتب غير صالح.');
  if($reason==='') throw new RuntimeException('سبب العكس مطلوب لأغراض التدقيق المحاسبي.');
  if(mb_strlen($reason)>255) throw new RuntimeException('سبب العكس طويل جداً.');
  $payroll=dbFetchOne("SELECT p.*,e.full_name AS employee_name,e.employee_code FROM payroll p JOIN employees e ON e.id=p.employee_id WHERE p.id=? LIMIT 1",[$payrollId]);
  if(!$payroll) throw new RuntimeException('سجل مسير الراتب غير موجود.');
  if($payroll['status']!=='paid') throw new RuntimeException('لا يمكن عكس مسير غير مصروف.');
  if(dbFetchOne("SELECT id FROM hr_payroll_reversals WHERE payroll_id=? LIMIT 1",[$payrollId])) throw new RuntimeException('تم عكس هذا المسير مسبقاً ولا يمكن إنشاء عكس ثانٍ.');
  $original=dbFetchOne("SELECT id FROM journal_entries WHERE reference_type='payroll' AND reference_id=? AND status='posted' ORDER BY id ASC LIMIT 1",[$payrollId]);
  if(!$original) throw new RuntimeException('هذا المسير مصروف، لكنه لم يُرحّل إلى المحاسبة؛ لا يوجد قيد محاسبي يمكن عكسه.');
  $reversalEntryId=hrReversePaidPayroll($payrollId,(int)Session::getUserId(),$reason);
  try {
   $pdo->prepare("INSERT INTO hr_payroll_reversals (payroll_id,original_entry_id,reversal_entry_id,reason,reversed_by) VALUES (?,?,?,?,?)")
    ->execute([$payrollId,(int)$original['id'],$reversalEntryId,$reason,(int)Session::getUserId()]);
  } catch(Throwable $auditError) {
   if(!dbFetchOne("SELECT id FROM hr_payroll_reversals WHERE payroll_id=? LIMIT 1",[$payrollId])) throw $auditError;
  }
  $message='تم عكس القيد المحاسبي لمسير الراتب بنجاح. بقي سجل المسير الأصلي محفوظاً وغير قابل للتعديل.';
 }
} catch(Throwable $e) { $message='خطأ: '.$e->getMessage(); $msgType='error'; }
$rows=dbFetchAll("SELECT p.id,p.month,p.year,p.net_salary,p.payment_date,p.accounting_status,e.employee_code,e.full_name AS employee_name,
 COALESCE(je.id,0) AS original_entry_id,r.reversal_entry_id,r.reason AS reversal_reason,r.reversed_at
 FROM payroll p JOIN employees e ON e.id=p.employee_id
 LEFT JOIN journal_entries je ON je.reference_type='payroll' AND je.reference_id=p.id AND je.status='posted'
 LEFT JOIN hr_payroll_reversals r ON r.payroll_id=p.id
 WHERE p.status='paid' ORDER BY p.year DESC,p.month DESC,e.full_name ASC");
$pageTitle='عكس مسيرات الرواتب'; require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.prr-wrap{max-width:1450px;margin:auto}.prr-header{background:linear-gradient(135deg,#7f1d1d,#991b1b);color:#fff;padding:22px;border-radius:12px;margin-bottom:18px}.prr-header h1{margin:0;font-size:1.55rem}.prr-header p{margin:5px 0 0;opacity:.9}.prr-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden}.prr-head{padding:14px 18px;border-bottom:1px solid #e9ecef}.prr-body{padding:18px}.prr-table{width:100%;border-collapse:collapse}.prr-table th,.prr-table td{padding:10px;border-bottom:1px solid #edf0f2;text-align:right;vertical-align:middle;font-size:.84rem}.prr-table th{background:#f8f9fa;font-weight:800;white-space:nowrap}.prr-btn{border:0;border-radius:7px;padding:7px 11px;font-size:.76rem;font-weight:800;cursor:pointer}.prr-danger{background:#991b1b;color:#fff}.prr-done{background:#e2e3e5;color:#495057}.prr-disabled{background:#f1f3f5;color:#6c757d;cursor:not-allowed}.prr-muted{font-size:.72rem;color:#6c757d}.prr-locked{background:#fff4f4;border-right:3px solid #991b1b;padding:10px 13px;border-radius:7px;color:#5b1a1a;font-size:.82rem}.prr-warning{background:#fff8e1;color:#664d03;border-right:3px solid #ffc107;padding:8px 10px;border-radius:6px;font-size:.72rem}
</style>
<div class="prr-wrap">
<div class="prr-header"><h1><i class="fas fa-rotate-left me-2"></i> عكس مسيرات الرواتب</h1><p>إجراء تصحيحي محاسبي مستقل — لا يعيد فتح المسير الأصلي ولا يغيّر بيانات الصرف التاريخية.</p></div>
<?php if($message): ?><div class="alert alert-<?php echo $msgType==='error'?'danger':'success'; ?> alert-dismissible fade show"><i class="fas <?php echo $msgType==='error'?'fa-exclamation-circle':'fa-check-circle'; ?> me-2"></i><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="prr-card"><div class="prr-head"><strong><i class="fas fa-shield-alt text-danger me-1"></i> المسيرات المصروفة</strong></div><div class="prr-body">
<div class="prr-locked mb-3"><i class="fas fa-lock me-1"></i> بعد الصرف، تبقى بيانات الموظف والفترة والمبالغ والحالة الأصلية ثابتة. العكس ينشئ قيداً محاسبياً جديداً يعكس القيد الأصلي، مع الاحتفاظ بسجل التدقيق.</div>
<div class="table-responsive"><table class="prr-table"><thead><tr><th>الموظف</th><th>الفترة</th><th>الصافي</th><th>تاريخ الصرف</th><th>الحالة المحاسبية</th><th>الإجراء</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="6" class="text-center py-5 text-muted">لا توجد مسيرات مصروفة.</td></tr>
<?php else: foreach($rows as $row): ?><tr>
<td><strong><?php echo htmlspecialchars($row['employee_name']); ?></strong><div class="prr-muted"><?php echo htmlspecialchars($row['employee_code']??''); ?></div></td>
<td><?php echo htmlspecialchars(sprintf('%04d-%02d',(int)$row['year'],(int)$row['month'])); ?></td>
<td><strong><?php echo number_format((float)$row['net_salary'],2); ?></strong> <span class="prr-muted"><?php echo htmlspecialchars(APP_CURRENCY_CODE); ?></span></td>
<td><?php echo htmlspecialchars($row['payment_date']?:'—'); ?></td>
<td>
<?php if(!empty($row['reversal_entry_id'])): ?>
 <span class="badge text-bg-warning">تم العكس</span><div class="prr-muted">قيد العكس #<?php echo (int)$row['reversal_entry_id']; ?></div>
<?php elseif(!empty($row['original_entry_id'])): ?>
 <span class="badge text-bg-success">مصروف — مرحل محاسبياً</span><div class="prr-muted">القيد الأصلي #<?php echo (int)$row['original_entry_id']; ?></div>
<?php else: ?>
 <span class="badge text-bg-secondary">مصروف — غير مرحل</span><div class="prr-warning mt-1">لا يوجد قيد محاسبي أصلي لهذا المسير</div>
<?php endif; ?>
</td>
<td>
<?php if(!empty($row['reversal_entry_id'])): ?>
 <button class="prr-btn prr-done" type="button" disabled><i class="fas fa-check me-1"></i> تم العكس</button>
<?php elseif(empty($row['original_entry_id'])): ?>
 <button class="prr-btn prr-disabled" type="button" disabled title="لا يوجد قيد محاسبي أصلي"><i class="fas fa-ban me-1"></i> لا يوجد قيد</button>
<?php else: ?>
 <button class="prr-btn prr-danger" type="button" onclick="openReverse(<?php echo (int)$row['id']; ?>,<?php echo htmlspecialchars(json_encode($row['employee_name'],JSON_UNESCAPED_UNICODE),ENT_QUOTES,'UTF-8'); ?>,'<?php echo sprintf('%04d-%02d',(int)$row['year'],(int)$row['month']); ?>','<?php echo number_format((float)$row['net_salary'],2); ?>')"><i class="fas fa-rotate-left me-1"></i> عكس المسير</button>
<?php endif; ?>
</td>
</tr><?php endforeach; endif; ?></tbody></table></div>
</div></div></div>
<div class="modal fade" id="reverseModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" dir="rtl">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-triangle-exclamation text-danger me-2"></i> تأكيد عكس مسير الراتب</h5><button type="button" class="btn-close ms-0 me-auto" data-bs-dismiss="modal"></button></div>
<form method="POST" onsubmit="return validateReverse()"><div class="modal-body"><input type="hidden" name="action" value="reverse"><input type="hidden" name="payroll_id" id="reversePayrollId"><p class="mb-2">سيتم إنشاء قيد محاسبي عكسي للمسير:</p><div class="bg-light rounded p-3 mb-3"><strong id="reverseEmployee"></strong><div id="reversePeriod" class="prr-muted"></div><div class="mt-1">الصافي: <strong id="reverseAmount"></strong> <?php echo htmlspecialchars(APP_CURRENCY_CODE); ?></div></div><div class="alert alert-warning py-2 small"><i class="fas fa-info-circle me-1"></i> لا يمكن التراجع عن هذا الإجراء من خلال إعادة فتح المسير. سيتم الاحتفاظ بالقيد الأصلي وسجل التدقيق.</div><label class="form-label fw-bold">سبب العكس <span class="text-danger">*</span></label><textarea class="form-control" name="reason" id="reverseReason" rows="3" maxlength="255" required placeholder="مثال: تصحيح خطأ في صرف الراتب"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button type="submit" class="btn btn-danger"><i class="fas fa-rotate-left me-1"></i> تنفيذ العكس</button></div></form>
</div></div></div>
<script>
function openReverse(id,employee,period,amount){document.getElementById('reversePayrollId').value=id;document.getElementById('reverseEmployee').textContent=employee;document.getElementById('reversePeriod').textContent='الفترة: '+period;document.getElementById('reverseAmount').textContent=amount;document.getElementById('reverseReason').value='';bootstrap.Modal.getOrCreateInstance(document.getElementById('reverseModal')).show();}
function validateReverse(){const reason=document.getElementById('reverseReason').value.trim();if(!reason){document.getElementById('reverseReason').focus();return false;}return true;}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
