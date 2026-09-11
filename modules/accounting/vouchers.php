<?php
// modules/accounting/vouchers.php - Receipt & payment vouchers (create / list / void)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';
Session::start();

$voucherRole = Session::getUserRole();

$allowedVoucherRoles = [
    'admin',
    'financial_manager',
    'accountant_staff',
    'general_manager',
    'vice_general_manager'
];

if (!Session::isLoggedIn() || !in_array($voucherRole, $allowedVoucherRoles, true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$canManage = in_array(Session::getUserRole(), ['admin', 'financial_manager'], true);
$pageTitle = 'السندات';
$active    = 'vouchers';
ak_ensure_tables(); ak_seed_accounts();

$cashAccounts  = dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE code IN ('1100','1200','1300') ORDER BY code");
$incomeAccounts = dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE account_type IN ('revenue','liability') AND is_active = 1 ORDER BY code");
$expenseAccounts = dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE account_type IN ('expense','liability') AND is_active = 1 ORDER BY code");

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!verify_csrf()) $errors[] = 'انتهت صلاحية الجلسة.';
    elseif (isset($_POST['save_voucher'])) {
        $vType  = ($_POST['voucher_type'] ?? 'receipt') === 'payment' ? 'payment' : 'receipt';
        $date   = trim($_POST['voucher_date'] ?? '') ?: date('Y-m-d');
        $amount = (float)str_replace(',', '', (string)($_POST['amount'] ?? 0));
        $cashId = (int)($_POST['cash_account_id'] ?? 0);
        $otherId = (int)($_POST['other_account_id'] ?? 0);
        $party  = trim($_POST['party_name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $ref    = trim($_POST['reference_number'] ?? '');

        if ($amount <= 0) $errors[] = 'المبلغ يجب أن يكون أكبر من صفر.';
        if (!in_array($cashId, array_map(fn($c) => (int)$c['id'], $cashAccounts), true)) $errors[] = 'اختر مكان النقد (صندوق/بنك/محفظة).';
        if ($otherId <= 0) $errors[] = 'اختر الحساب المقابل.';

        if (!$errors) {
            try {
                db()->beginTransaction();

                $otherAccount = dbFetchOne(
                    "SELECT id, account_type, is_active FROM accounts WHERE id = ? FOR UPDATE",
                    [$otherId]
                );
                if (!$otherAccount || (int)$otherAccount['is_active'] !== 1) {
                    throw new RuntimeException('الحساب المقابل غير موجود أو غير نشط.');
                }

                $requiredType = $vType === 'receipt' ? 'revenue' : 'expense';
                if ($otherAccount['account_type'] !== $requiredType) {
                    throw new RuntimeException(
                        $vType === 'receipt'
                            ? 'سند القبض يجب أن يستخدم حساب إيراد كحساب مقابل.'
                            : 'سند الصرف يجب أن يستخدم حساب مصروف كحساب مقابل.'
                    );
                }

                $cnt = (int)(dbFetchOne("SELECT COUNT(*) c FROM vouchers WHERE voucher_type = ?", [$vType])['c'] ?? 0) + 1;
                $vNo = ($vType === 'receipt' ? 'RV-' : 'PV-') . str_pad((string)$cnt, 6, '0', STR_PAD_LEFT);
                dbExecute("INSERT INTO vouchers (voucher_type, voucher_no, voucher_date, party_name, amount, cash_account_id, other_account_id, description, reference_number, status, created_by)
                           VALUES (?,?,?,?,?,?,?,?,?,'posted',?)",
                    [$vType, $vNo, $date, $party !== '' ? $party : null, $amount, $cashId, $otherId,
                     $desc !== '' ? $desc : null, $ref !== '' ? $ref : null, Session::getUserId()]);
                $vId = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);
                if ($vId <= 0) {
                    throw new RuntimeException('تعذر إنشاء السند.');
                }

                // Balanced double-entry: receipt => Dr cash / Cr other ; payment => Dr other / Cr cash
                $n = (int)(dbFetchOne("SELECT COUNT(*) c FROM journal_entries")['c'] ?? 0) + 1;
                $jeCode = 'JE-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
                $label = ($vType === 'receipt' ? 'سند قبض ' : 'سند صرف ') . $vNo . ($party !== '' ? ' — ' . $party : '');
                dbExecute("INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
                           VALUES (?,?,?,'voucher',?,'posted',?)",
                    [$jeCode, $date, $label, $vId, Session::getUserId()]);
                $eid = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);
                if ($eid <= 0) {
                    throw new RuntimeException('تعذر إنشاء القيد المحاسبي للسند.');
                }
                if ($vType === 'receipt') {
                    dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)", [$eid, $cashId, $amount, 0, $label]);
                    dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)", [$eid, $otherId, 0, $amount, $label]);
                } else {
                    dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)", [$eid, $otherId, $amount, 0, $label]);
                    dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)", [$eid, $cashId, 0, $amount, $label]);
                }
                dbExecute("UPDATE vouchers SET entry_id = ? WHERE id = ?", [$eid, $vId]);

                try {
                    dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                               VALUES (?, 'CREATE', 'vouchers', ?, NULL, ?, ?, ?)",
                        [Session::getUserId(), $vId, json_encode(['no' => $vNo, 'type' => $vType, 'amount' => $amount], JSON_UNESCAPED_UNICODE),
                         $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                } catch (Throwable $auditError) {}

                db()->commit();
                flash('success', 'تم ترحيل ' . ($vType === 'receipt' ? 'سند القبض ' : 'سند الصرف ') . $vNo);
                header('Location: ' . APP_URL . 'modules/accounting/vouchers.php?tab=list'); exit();
            } catch (Throwable $e) {
                try {
                    if (db()->inTransaction()) db()->rollBack();
                } catch (Throwable $rollbackError) {}
                $errors[] = 'تعذر ترحيل السند: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['void_voucher'])) {
        $vId = (int)$_POST['void_voucher'];
        try {
            db()->beginTransaction();

            $vLocked = dbFetchOne("SELECT id, voucher_no, status, entry_id FROM vouchers WHERE id = ? FOR UPDATE", [$vId]);
            if (!$vLocked || $vLocked['status'] !== 'posted') {
                throw new RuntimeException('السند غير موجود أو ليس في حالة مرحّل.');
            }
            if (empty($vLocked['entry_id'])) {
                throw new RuntimeException('لا يمكن إبطال السند: لا يوجد قيد محاسبي مرتبط به.');
            }

            $journal = dbFetchOne("SELECT id, status, reference_type, reference_id
                                   FROM journal_entries
                                   WHERE id = ?
                                     AND reference_type = 'voucher'
                                     AND reference_id = ?
                                   FOR UPDATE", [(int)$vLocked['entry_id'], $vId]);
            if (!$journal || $journal['reference_type'] !== 'voucher' || (int)$journal['reference_id'] !== $vId) {
                throw new RuntimeException('لا يمكن إبطال السند: القيد المرتبط به غير موجود أو مرجعه غير صحيح.');
            }
            if ($journal['status'] !== 'posted') {
                throw new RuntimeException('لا يمكن إبطال السند: القيد المرتبط به ليس مرحّلاً.');
            }

            $linkedCount = (int)(dbFetchOne("SELECT COUNT(*) c
                                             FROM journal_entries
                                             WHERE reference_type = 'voucher'
                                               AND reference_id = ?", [$vId])['c'] ?? 0);
            if ($linkedCount !== 1) {
                throw new RuntimeException('لا يمكن إبطال السند: يجب أن يرتبط السند بقيد محاسبي واحد فقط.');
            }

            $voidReason = trim($_POST['void_reason'] ?? '') ?: 'إبطال سند';
            $journalAffected = dbExecute("UPDATE journal_entries
                                          SET status='voided', voided_at=NOW(), voided_by=?, void_reason=?
                                          WHERE id=? AND status='posted'",
                [Session::getUserId(), $voidReason, (int)$journal['id']]);
            if ($journalAffected !== 1) {
                throw new RuntimeException('تعذر إبطال القيد المحاسبي المرتبط بالسند.');
            }

            $voucherAffected = dbExecute("UPDATE vouchers SET status='voided' WHERE id=? AND status='posted'", [$vId]);
            if ($voucherAffected !== 1) {
                throw new RuntimeException('تعذر تحديث حالة السند إلى مبطل.');
            }

            db()->commit();
            flash('success', 'تم إبطال السند ' . $vLocked['voucher_no']);
        } catch (Throwable $e) {
            try {
                if (db()->inTransaction()) db()->rollBack();
            } catch (Throwable $rollbackError) {}
            flash('danger', $e->getMessage());
        }
        header('Location: ' . APP_URL . 'modules/accounting/vouchers.php?tab=list'); exit();
    }
}

$tab = $_GET['tab'] ?? ($canManage ? 'new' : 'list');
$fType = $_GET['vtype'] ?? '';
$list = dbFetchAll("SELECT v.*, c1.code cash_code, c1.name_ar cash_name, c2.code other_code, c2.name_ar other_name
                    FROM vouchers v
                    JOIN accounts c1 ON c1.id = v.cash_account_id
                    JOIN accounts c2 ON c2.id = v.other_account_id
                    " . ($fType !== '' ? "WHERE v.voucher_type = '" . ($fType === 'payment' ? 'payment' : 'receipt') . "' " : '') . "
                    ORDER BY v.id DESC LIMIT 300");

include dirname(__DIR__, 2) . '/includes/header.php';
?>