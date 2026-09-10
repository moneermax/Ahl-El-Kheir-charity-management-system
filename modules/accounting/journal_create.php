<?php
require_once dirname(__DIR__,2).'/config/config.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/config/functions.php';
require_once dirname(__DIR__,2).'/config/session.php';
require_once __DIR__.'/lib.php';
Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin','financial_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = t('accounting.new_manual_entry');
$active = 'journal';
ak_ensure_tables();
ak_seed_accounts();
$accounts = dbFetchAll("SELECT id,code,name_ar FROM accounts WHERE is_active=1 ORDER BY code");
$accountMap = [];
foreach ($accounts as $account) {
    $accountMap[(int)$account['id']] = $account;
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'supervisors.session_expired';
    } else {
        $date = trim((string)($_POST['entry_date'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $accIds = is_array($_POST['account_id'] ?? null) ? $_POST['account_id'] : [];
        $debits = is_array($_POST['debit'] ?? null) ? $_POST['debit'] : [];
        $credits = is_array($_POST['credit'] ?? null) ? $_POST['credit'] : [];
        $ldesc = is_array($_POST['line_desc'] ?? null) ? $_POST['line_desc'] : [];
        $lines = [];
        $sumD = 0;
        $sumC = 0;
        $usedAccounts = [];

        $dateObj = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            $errors[] = 'التاريخ المحاسبي غير صالح.';
        }
        if ($desc === '') {
            $errors[] = 'وصف القيد مطلوب.';
        } elseif (mb_strlen($desc, 'UTF-8') > 255) {
            $errors[] = 'وصف القيد يتجاوز الحد المسموح.';
        }

        if (!$errors) {
            foreach ($accIds as $i => $rawAid) {
                $aid = filter_var($rawAid, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $rawDebit = trim(str_replace(',', '', (string)($debits[$i] ?? '')));
                $rawCredit = trim(str_replace(',', '', (string)($credits[$i] ?? '')));

                if ($rawAid === '' && $rawDebit === '' && $rawCredit === '') {
                    continue;
                }
                if ($aid === false || !isset($accountMap[(int)$aid])) {
                    $errors[] = 'الحساب المحاسبي المحدد غير صالح أو غير نشط.';
                    break;
                }
                if (isset($usedAccounts[(int)$aid])) {
                    $errors[] = 'accounting.duplicate_account';
                    break;
                }

                $validAmount = static function (string $value): bool {
                    return $value !== '' && preg_match('/^(?:0|[1-9]\d{0,11})(?:\.\d{1,2})?$/', $value) === 1;
                };
                $debitValid = $rawDebit === '' || $validAmount($rawDebit);
                $creditValid = $rawCredit === '' || $validAmount($rawCredit);
                if (!$debitValid || !$creditValid) {
                    $errors[] = 'القيمة المحاسبية يجب أن تكون رقماً موجباً أو صفراً وبحد أقصى منزلتين عشريتين.';
                    break;
                }

                $dCents = $rawDebit === '' ? 0 : (int)round(((float)$rawDebit) * 100);
                $cCents = $rawCredit === '' ? 0 : (int)round(((float)$rawCredit) * 100);
                if ($dCents < 0 || $cCents < 0 || ($dCents > 0 && $cCents > 0) || ($dCents === 0 && $cCents === 0)) {
                    $errors[] = 'كل سطر يجب أن يحتوي قيمة في جانب واحد فقط، ولا يجوز أن يكون صفراً.';
                    break;
                }

                $usedAccounts[(int)$aid] = true;
                $lines[] = [
                    (int)$aid,
                    number_format($dCents / 100, 2, '.', ''),
                    number_format($cCents / 100, 2, '.', ''),
                    trim((string)($ldesc[$i] ?? '')),
                ];
                $sumD += $dCents;
                $sumC += $cCents;
            }
        }

        if (!$errors && count($lines) < 2) {
            $errors[] = 'accounting.minimum_lines';
        }
        if (!$errors && $sumD <= 0) {
            $errors[] = 'accounting.enter_amounts';
        }
        if (!$errors && $sumD !== $sumC) {
            $errors[] = 'accounting.unbalanced';
            $balanceParams = [
                'debit' => number_format($sumD / 100, 2),
                'credit' => number_format($sumC / 100, 2),
            ];
        }

        if (!$errors) {
            $lockName = 'ahl_el_kheir.manual_journal_code';
            $lockAcquired = false;
            $pdo = db();
            try {
                $lockResult = dbFetchOne("SELECT GET_LOCK(?, 5) AS locked", [$lockName]);
                $lockAcquired = ((int)($lockResult['locked'] ?? 0) === 1);
                if (!$lockAcquired) {
                    throw new RuntimeException('تعذر الحصول على قفل ترقيم القيود. حاول مرة أخرى.');
                }

                $pdo->beginTransaction();
                $last = dbFetchOne("SELECT COALESCE(MAX(CAST(SUBSTRING(entry_code, 4) AS UNSIGNED)), 0) AS max_no
                                    FROM journal_entries
                                    WHERE entry_code REGEXP '^JE-[0-9]+$'");
                $n = (int)($last['max_no'] ?? 0) + 1;
                $code = 'JE-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);

                dbExecute("INSERT INTO journal_entries
                    (entry_code,entry_date,description,reference_type,reference_id,status,created_by)
                    VALUES (?,?,?,?,NULL,'posted',?)",
                    [$code, $date, $desc, 'manual', Session::getUserId()]);
                $eid = (int)dbLastInsertId();
                if ($eid <= 0) {
                    throw new RuntimeException('تعذر إنشاء رأس القيد المحاسبي.');
                }

                foreach ($lines as $line) {
                    dbExecute("INSERT INTO journal_lines (entry_id,account_id,debit,credit,description)
                               VALUES (?,?,?,?,?)",
                        [$eid, $line[0], $line[1], $line[2], $line[3]]);
                }

                $lineCount = (int)(dbFetchOne("SELECT COUNT(*) c FROM journal_lines WHERE entry_id=?", [$eid])['c'] ?? 0);
                if ($lineCount !== count($lines)) {
                    throw new RuntimeException('لم يتم إنشاء جميع أسطر القيد المحاسبي.');
                }

                $postedTotals = dbFetchOne("SELECT COALESCE(SUM(debit),0) debit_total,
                                                   COALESCE(SUM(credit),0) credit_total
                                            FROM journal_lines WHERE entry_id=?", [$eid]);
                if ((int)round(((float)$postedTotals['debit_total']) * 100) !== $sumD ||
                    (int)round(((float)$postedTotals['credit_total']) * 100) !== $sumC) {
                    throw new RuntimeException('فشل التحقق من توازن القيد بعد الحفظ.');
                }

                $pdo->commit();
                flash('success', t('accounting.entry_posted', ['code' => $code]));
                header('Location: ' . APP_URL . 'modules/accounting/journal.php?view=' . $eid);
                exit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'تعذر حفظ القيد المحاسبي بالكامل. لم يتم حفظ أي جزء منه. يرجى المحاولة مرة أخرى.';
            } finally {
                if ($lockAcquired) {
                    dbFetchOne("SELECT RELEASE_LOCK(?) AS released", [$lockName]);
                }
            }
        }
    }
}

include dirname(__DIR__,2).'/includes/header.php'; ?>
<div class="welcome-section fade-in"><h2><?php echo e($pageTitle); ?></h2><p><?php echo e(t('accounting.entry_balance_rule')); ?></p></div>
<?php include dirname(__DIR__,2).'/includes/alerts.php'; ?>
<?php if ($errors): ?><div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach ($errors as $er) { $params = $er === 'accounting.unbalanced' ? ($balanceParams ?? []) : []; echo '<li>' . e(t($er, $params)) . '</li>'; } ?></ul></div><?php endif; ?>
<div class="card fade-in"><div class="card-body"><form method="post" id="jeForm">
<?php echo csrf_field(); ?>
<div class="row g-2 mb-3"><div class="col-md-3"><label class="form-label"><?php echo e(t('accounting.date')); ?> *</label><input type="date" name="entry_date" class="form-control" value="<?php echo e(date('Y-m-d')); ?>" required></div><div class="col-md-9"><label class="form-label"><?php echo e(t('accounting.description')); ?> *</label><input type="text" name="description" class="form-control" required placeholder="<?php echo e(t('accounting.example_description')); ?>"></div></div>
<div class="table-responsive"><table class="table align-middle" id="linesTable"><thead><tr><th style="width:40%"><?php echo e(t('accounting.account')); ?></th><th><?php echo e(t('accounting.amount')); ?></th><th><?php echo e(t('accounting.amount')); ?></th><th><?php echo e(t('accounting.line_description')); ?></th><th></th></tr></thead><tbody>
<?php for ($i = 0; $i < 2; $i++): ?><tr class="je-line"><td><select name="account_id[]" class="form-select" required><option value=""><?php echo e(t('accounting.choose')); ?></option><?php foreach ($accounts as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo e($a['code']); ?> — <?php echo e($a['name_ar']); ?></option><?php endforeach; ?></select></td><td><input type="number" step="0.01" min="0" name="debit[]" class="form-control amt" placeholder="0.00"></td><td><input type="number" step="0.01" min="0" name="credit[]" class="form-control amt" placeholder="0.00"></td><td><input type="text" name="line_desc[]" class="form-control"></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove();recalc();"><i class="fas fa-trash"></i></button></td></tr><?php endfor; ?>
</tbody><tfoot><tr class="table-active fw-bold"><td><?php echo e(t('accounting.total')); ?></td><td id="totD">0.00</td><td id="totC">0.00</td><td id="diff" colspan="2"><?php echo e(t('accounting.balanced')); ?></td></tr></table></div>
<div class="d-flex gap-2"><button type="button" class="btn btn-secondary" onclick="addLine()"><i class="fas fa-plus me-1"></i><?php echo e(t('accounting.add_line')); ?></button><button class="btn btn-primary"><i class="fas fa-save me-1"></i><?php echo e(t('accounting.post_entry')); ?></button></div>
</form></div></div>
<script>
function addLine(){var tbody=document.querySelector('#linesTable tbody'),row=tbody.querySelector('tr.je-line').cloneNode(true);row.querySelectorAll('input').forEach(function(i){i.value='';});row.querySelector('select').value='';tbody.appendChild(row);}
function recalc(){var d=0,c=0;document.querySelectorAll('#linesTable tbody tr').forEach(function(tr){var i=tr.querySelectorAll('.amt');d+=parseFloat(i[0].value||0);c+=parseFloat(i[1].value||0);});document.getElementById('totD').textContent=d.toFixed(2);document.getElementById('totC').textContent=c.toFixed(2);var diff=Math.abs(d-c),el=document.getElementById('diff');el.textContent=diff<.01?<?php echo json_encode(t('accounting.balanced')); ?>:<?php echo json_encode(t('accounting.difference',['amount'=>'__DIFF__'])); ?>.replace('__DIFF__',diff.toFixed(2));}
document.getElementById('jeForm').addEventListener('input',function(e){if(!e.target.classList.contains('amt'))return;var tr=e.target.closest('tr'),i=tr.querySelectorAll('.amt'),other=e.target===i[0]?i[1]:i[0];if(parseFloat(e.target.value||0)>0)other.value='';recalc();});
recalc();
</script>
<?php include dirname(__DIR__,2).'/includes/footer.php'; ?>