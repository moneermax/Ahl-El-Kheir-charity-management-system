<?php
// modules/accounting/disbursements.php - Monthly disbursement workflow (Accountant → Nanny)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_group_workflow.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_transaction_review.php';
Session::start();
if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'accountant_staff', 'financial_manager', 'general_manager', 'vice_general_manager', 'nanny'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$role = Session::getUserRole();
$isNanny = ($role === 'nanny');
$isAccountantStaff = ($role === 'accountant_staff');
$canManage = in_array($role, ['admin','general_manager','financial_manager'], true);
$canManageAssigned = $canManage || $isAccountantStaff;
if (!$isNanny && !$canManageAssigned) {
    flash('error', 'ليس لديك صلاحية الوصول.');
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
$uid = Session::getUserId();
$receiptsDir = dirname(__DIR__, 2) . '/storage/receipts';
if (!is_dir($receiptsDir)) @mkdir($receiptsDir, 0777, true);
function disb_audit(int $userId, string $action, int $entityId, array $new): void {
    try {
        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent, created_at)
        VALUES (?, CONCAT('DISBURSEMENT_', ?), 'monthly_disbursements', ?, ?, ?, ?, NOW())",
        [$userId, $action, $entityId, json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $_SERVER['REMOTE_ADDR'] ?? 'cli', $_SERVER['HTTP_USER_AGENT'] ?? 'cli']);
    } catch (Throwable $e) {
        error_log('Disbursement audit error: ' . $e->getMessage());
    }
}
// Self-contained replacement for lib_group_workflow.php's log_group_workflow_action().
// That function requires a raw PDO object passed as its first argument, and every call
// site in this file supplied $GLOBALS['pdo'] — which is never actually populated here,
// causing "Call to a member function prepare() on null" the moment any of these actions
// were actually used. This version uses dbExecute() instead, matching the proven-working
// pattern already used everywhere else in this file.
function log_group_action(?int $groupId, ?int $familyId, ?string $month, string $actionType, int $actorUserId, string $actorRole, ?string $oldStatus, ?string $newStatus, string $reason): void {
    try {
        dbExecute(
            "INSERT INTO group_workflow_audit_log (group_id, family_id, month, action_type, actor_user_id, actor_role, old_status, new_status, reason, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$groupId, $familyId, $month, $actionType, $actorUserId, $actorRole, $oldStatus, $newStatus, $reason,
            $_SERVER['REMOTE_ADDR'] ?? 'cli', $_SERVER['HTTP_USER_AGENT'] ?? 'cli']
        );
    } catch (Throwable $e) {
        error_log('log_group_action error: ' . $e->getMessage());
    }
}
function uploadFamilyReceipt($file, $itemId, $familyId) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif'
    ];
    if (!isset($allowed[$mime])) {
        return ['success' => false, 'message' => 'نوع الملف غير مسموح. يُسمح فقط بـ JPG, PNG, GIF.'];
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'message' => 'حجم الملف يجب أن لا يتجاوز 10 ميجابايت.'];
    }
    $ext = $allowed[$mime];
    $fname = 'family_receipt_item_' . $itemId . '_' . $familyId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = dirname(__DIR__, 2) . '/storage/receipts/family/' . $fname;
    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0777, true);
    }
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => true, 'path' => 'storage/receipts/family/' . $fname];
    }
    return ['success' => false, 'message' => 'فشل حفظ الملف على الخادم.'];
}
/**
* Upload the receipt proving that the nanny returned the outstanding amount.
*/
function uploadReturnReceipt($file, $disbursementId) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'فشل رفع إيصال الإرجاع.'];
    }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'ملف إيصال الإرجاع غير صالح.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);
    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif'
    ];
    if (!isset($allowed[$mime])) {
        return ['success' => false, 'message' => 'نوع الملف غير مسموح. استخدم PDF أو JPG أو PNG أو GIF فقط.'];
    }
    if ((int)$file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'message' => 'حجم ملف الإرجاع يجب ألا يتجاوز 10 ميجابايت.'];
    }
    $dir = dirname(__DIR__, 2) . '/storage/receipts/returns';
    if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
        return ['success' => false, 'message' => 'تعذر إنشاء مجلد إيصالات الإرجاع.'];
    }
    $ext = $allowed[$mime];
    $fname = 'return_receipt_' . (int)$disbursementId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . '/' . $fname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'message' => 'فشل حفظ إيصال الإرجاع على الخادم.'];
    }
    return [
        'success' => true,
        'path' => 'storage/receipts/returns/' . $fname,
        'absolute_path' => $dest
    ];
}
/**
* Safely close a partially confirmed disbursement and reverse the unpaid amount
* back into the organization's cash account.
*/
function closeDisbursementWithReturn(int $disbursementId, int $nannyId, string $reason, string $returnReceiptPath, int $userId): array {
    $transactionStarted = false;
    try {
        dbExecute('START TRANSACTION');
        $transactionStarted = true;
        $batch = dbFetchOne(
            "SELECT id, month, nanny_id, group_id, total_amount, status, expense_account_code
            FROM monthly_disbursements WHERE id = ? FOR UPDATE",
            [$disbursementId]
        );
        if (!$batch) {
            throw new Exception('الدفعة غير موجودة.');
        }
        if ((int)$batch['nanny_id'] !== $nannyId) {
            throw new Exception('هذه الدفعة ليست ضمن صلاحياتك.');
        }
        if ($batch['status'] !== 'transferred') {
            throw new Exception('لا يمكن إقفال هذه الدفعة لأنها ليست في حالة "محوّل".');
        }
        $pendingItems = dbFetchAll(
            "SELECT id, family_id, amount FROM disbursement_items
            WHERE disbursement_id = ? AND status = 'pending' FOR UPDATE",
            [$disbursementId]
        );
        if (empty($pendingItems)) {
            throw new Exception('لا توجد مبالغ معلقة لإرجاعها. إذا تم تأكيد جميع الأسر، استخدم إقفال الدفعة العادي.');
        }
        $returnAmount = 0.0;
        foreach ($pendingItems as $pendingItem) {
            $returnAmount += (float)$pendingItem['amount'];
        }
        if ($returnAmount <= 0) {
            throw new Exception('المبلغ المطلوب إرجاعه يساوي صفراً.');
        }
        // Find the expense account used by the original disbursement journal.
        // This avoids hard-coding 5100/5110 for the reversal.
        $originalExpense = dbFetchOne(
            "SELECT jl.account_id, a.code
            FROM journal_entries je
            INNER JOIN journal_lines jl ON jl.entry_id = je.id
            INNER JOIN accounts a ON a.id = jl.account_id
            WHERE je.reference_type = 'disbursement'
            AND je.reference_id = ?
            AND je.status = 'posted'
            AND jl.debit > 0
            ORDER BY jl.id ASC LIMIT 1",
            [$disbursementId]
        );
        if ($originalExpense) {
            $expenseAccountId = (int)$originalExpense['account_id'];
            $expenseAccountCode = (string)$originalExpense['code'];
        } else {
            $fallbackCode = trim((string)($batch['expense_account_code'] ?? '5100'));
            $expenseAccount = dbFetchOne("SELECT id, code FROM accounts WHERE code = ? LIMIT 1", [$fallbackCode]);
            if (!$expenseAccount) {
                throw new Exception('تعذر تحديد حساب المصروف المرتبط بالدفعة.');
            }
            $expenseAccountId = (int)$expenseAccount['id'];
            $expenseAccountCode = (string)$expenseAccount['code'];
        }
        $cashAccount = dbFetchOne("SELECT id, code FROM accounts WHERE code = '1100' LIMIT 1");
        if (!$cashAccount) {
            throw new Exception('حساب الصندوق 1100 غير موجود في دليل الحسابات.');
        }
        $cashAccountId = (int)$cashAccount['id'];
        $entryCode = 'JE-RET-DISB-' . $disbursementId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $description = 'قيد إرجاع المبلغ غير المصروف للدفعة #' . $disbursementId . ' - شهر ' . $batch['month'];
        dbExecute(
            "INSERT INTO journal_entries
            (entry_code, entry_date, description, reference_type, reference_id, status, created_by, created_at)
            VALUES (?, CURDATE(), ?, 'disbursement_return', ?, 'posted', ?, NOW())",
            [$entryCode, $description, $disbursementId, $userId]
        );
        $journalId = (int)dbFetchOne("SELECT LAST_INSERT_ID() AS id")['id'];
        dbExecute(
            "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
            VALUES (?, ?, ?, 0, ?)",
            [$journalId, $cashAccountId, $returnAmount, 'إرجاع المبلغ غير المصروف إلى الصندوق - دفعة #' . $disbursementId]
        );
        dbExecute(
            "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
            VALUES (?, ?, 0, ?, ?)",
            [$journalId, $expenseAccountId, $returnAmount, 'عكس المصروف غير المصروف - حساب ' . $expenseAccountCode]
        );
        foreach ($pendingItems as $pendingItem) {
            dbExecute(
                "UPDATE disbursement_items
                SET status = 'returned', return_reason = ?, returned_at = NOW(),
                returned_by_user_id = ?, reversal_journal_id = ?
                WHERE id = ? AND disbursement_id = ? AND status = 'pending'",
                [$reason, $userId, $journalId, (int)$pendingItem['id'], $disbursementId]
            );
        }
        dbExecute(
            "UPDATE monthly_disbursements
            SET status = 'returned',
            return_note = ?,
            returned_amount = ?,
            return_receipt_file_path = ?,
            returned_by_user_id = ?,
            returned_at = NOW(),
            return_receipt_by_user_id = ?,
            return_receipt_at = NOW(),
            reversal_journal_id = ?
            WHERE id = ? AND nanny_id = ? AND status = 'transferred'",
            [$reason, $returnAmount, $returnReceiptPath, $userId, $userId, $journalId, $disbursementId, $nannyId]
        );
        $updatedBatch = dbFetchOne(
            "SELECT status, returned_amount, return_receipt_file_path, reversal_journal_id
            FROM monthly_disbursements WHERE id = ?",
            [$disbursementId]
        );
        if (!$updatedBatch || $updatedBatch['status'] !== 'returned' || (float)$updatedBatch['returned_amount'] !== (float)$returnAmount) {
            throw new Exception('فشل التحقق من إقفال الدفعة بعد الإرجاع.');
        }
        // Close the orphan group permanently when this is the group's active disbursement.
        if (!empty($batch['group_id'])) {
            dbExecute(
                "UPDATE orphan_groups
                SET verification_status = 'closed', closed_at = NOW(), closed_by = ?
                WHERE id = ?",
                [$userId, $batch['group_id']]
            );
        }
        // Note: this event used to also log to group_workflow_audit_log here, but that
        // table's action_type enum has no value for "closed with returns", and the call
        // relied on the same broken $GLOBALS['pdo'] pattern that crashed reopen actions
        // elsewhere in this file. Removed — disb_audit() below already records this
        // event correctly, with no enum constraints to fight.
        disb_audit($userId, 'CLOSE_WITH_RETURN', $disbursementId, [
            'month' => $batch['month'],
            'group_id' => $batch['group_id'],
            'returned_amount' => $returnAmount,
            'pending_items_returned' => count($pendingItems),
            'return_reason' => $reason,
            'return_receipt' => $returnReceiptPath,
            'reversal_journal_id' => $journalId
        ]);
        dbExecute('COMMIT');
        $transactionStarted = false;
        return [
            'success' => true,
            'message' => 'تم إقفال الدفعة وإرجاع المبلغ غير المصروف إلى الصندوق بنجاح.',
            'return_amount' => $returnAmount,
            'journal_id' => $journalId
        ];
    } catch (Throwable $e) {
        if ($transactionStarted) {
            try { dbExecute('ROLLBACK'); } catch (Throwable $rollbackError) {}
        }
        error_log('Close disbursement with return error: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : null; $returnQuery=trim((string)($_GET['return']??'')); $backUrl=APP_URL.'modules/accounting/disbursements.php'; if($returnQuery!==''){$backUrl.='?'.ltrim(rawurldecode($returnQuery),'?');}
$batches = [];
$groups = [];
// ==========================================
// Handle POST actions
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    // 1. Accountant: create batches from groups
    if ($canManageAssigned && isset($_POST['create_batch'])) {
        $month = trim($_POST['month'] ?? '');
        $groupIds = isset($_POST['group_ids']) ? array_map('intval', $_POST['group_ids']) : [];
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            flash('error', 'شهر غير صالح.');
        } elseif (empty($groupIds)) {
            flash('error', 'يرجى اختيار مجموعة واحدة على الأقل.');
        } else {
            $created = 0;
            $skipped = 0;
            $skip_reasons = [];
            foreach ($groupIds as $gid) {
                // Exclude voided/cancelled batches from this check — a corrected mistake
                // must not permanently block a group from ever getting a real batch again.
                $exists = dbFetchOne("SELECT id FROM monthly_disbursements WHERE group_id = ? AND month = ? AND status NOT IN ('voided', 'cancelled')", [$gid, $month]);
                if ($exists) {
                    $skipped++;
                    $skip_reasons[] = "المجموعة ID {$gid}: دفعة موجودة مسبقاً لهذا الشهر.";
                    continue;
                }
                $groupData = dbFetchOne("
                SELECT
                nga.nanny_id,
                og.id as group_id,
                COUNT(DISTINCT fc.family_id) as verified_families,
                COALESCE(SUM(sp.monthly_amount), 0) as total_amount
                FROM orphan_groups og
                LEFT JOIN nanny_group_assignments nga ON nga.group_id = og.id AND nga.end_date IS NULL
                LEFT JOIN group_children gc ON gc.group_id = og.id AND gc.left_date IS NULL
                LEFT JOIN family_children fc ON fc.id = gc.child_id
                INNER JOIN nanny_family_verifications v ON v.family_id = fc.family_id AND v.month = ?
                AND v.is_orphan_verified = 1 AND v.is_mother_contact_verified = 1 AND v.is_bank_verified = 1
                LEFT JOIN sponsorships sp ON sp.child_id = gc.child_id AND sp.status = 'active'
                WHERE og.id = ?
                GROUP BY og.id, nga.nanny_id
                ", [$month, $gid]);
                if (!$groupData || !$groupData['nanny_id']) {
                    $skipped++;
                    $skip_reasons[] = "المجموعة ID {$gid}: لا توجد مربية معينة للمجموعة.";
                    continue;
                }
                if ($isAccountantStaff && !dbFetchOne("SELECT 1 FROM accountant_nanny_assignments WHERE accountant_id = ? AND nanny_id = ?", [$uid, (int)$groupData['nanny_id']])) {
                    $skipped++;
                    $skip_reasons[] = "المجموعة ID {$gid}: ليست ضمن المجموعات المسندة إليك.";
                    continue;
                }
                if ((int)$groupData['verified_families'] === 0) {
                    $skipped++;
                    $skip_reasons[] = "المجموعة ID {$gid}: لا توجد عائلات موثقة بالكامل (3 خانات) لهذا الشهر.";
                    continue;
                }
                dbExecute("
                INSERT INTO monthly_disbursements
                (month, nanny_id, group_id, total_amount, status, expense_account_code, created_by, submitted_by, submitted_at)
                VALUES (?, ?, ?, ?, 'pending_approval', '5110', ?, ?, NOW())
                ", [$month, $groupData['nanny_id'], $gid, $groupData['total_amount'], $uid, $uid]);
                $disbursementId = dbFetchOne("SELECT LAST_INSERT_ID() as id")['id'];
                $families = dbFetchAll("
                SELECT fc.family_id, COALESCE(SUM(sp.monthly_amount), 0) as amount
                FROM group_children gc
                INNER JOIN family_children fc ON fc.id = gc.child_id
                INNER JOIN nanny_family_verifications v ON v.family_id = fc.family_id
                AND v.month = ? AND v.is_orphan_verified = 1 AND v.is_mother_contact_verified = 1 AND v.is_bank_verified = 1
                LEFT JOIN sponsorships sp ON sp.child_id = gc.child_id AND sp.status = 'active'
                WHERE gc.group_id = ? AND gc.left_date IS NULL
                GROUP BY fc.family_id
                ", [$month, $gid]);
                foreach ($families as $fam) {
                    dbExecute(
                        "INSERT INTO disbursement_items (disbursement_id, family_id, amount, status, is_orphan_verified, is_mother_contact_verified, is_bank_verified) VALUES (?, ?, ?, 'pending', 1, 1, 1)",
                        [$disbursementId, $fam['family_id'], $fam['amount']]
                    );
                }
                $created++;
            }
            $msg = "تم إنشاء {$created} دفعة بنجاح.";
            if ($skipped > 0) {
                $msg .= " (تم تخطي {$skipped} مجموعة. الأسباب: " . implode(' | ', $skip_reasons) . ")";
            }
            flash('success', $msg);
            header('Location: ' . APP_URL . 'modules/accounting/disbursements.php');
            exit();
        }
    }
    // 2. Accountant: Upload transfer receipt AND mark as transferred (DEDUCTS FROM SAFE)
    if ($canManage && isset($_POST['transfer_with_receipt'])) {
        $id = (int)$_POST['transfer_with_receipt'];
        $b = dbFetchOne("SELECT * FROM monthly_disbursements WHERE id = ?", [$id]);
        if (!$b || $b['status'] !== 'pending_approval') {
            flash('error', 'الدفعة غير متاحة أو ليست بحالة انتظار.');
        } elseif (empty($_FILES['transfer_receipt']['tmp_name'])) {
            flash('error', 'يرجى اختيار ملف إيصال التحويل (إجباري).');
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($_FILES['transfer_receipt']['tmp_name']);
            $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (!isset($allowed[$mime])) {
                flash('error', 'نوع الملف غير مسموح. استخدم PDF أو JPG أو PNG فقط.');
            } elseif ($_FILES['transfer_receipt']['size'] > 10 * 1024 * 1024) {
                flash('error', 'حجم الملف يجب أن لا يتجاوز 10 ميجابايت.');
            } else {
                $ext = $allowed[$mime];
                $fname = 'transfer_receipt_' . $id . '_' . time() . '.' . $ext;
                $dest = $receiptsDir . '/' . $fname;
                if (move_uploaded_file($_FILES['transfer_receipt']['tmp_name'], $dest)) {
                    try {
                        dbExecute("START TRANSACTION");
                        dbExecute("UPDATE monthly_disbursements SET status = 'transferred', transferred_at = NOW(), receipt_file_path = ?, transfer_receipt_by_user_id = ?, transfer_receipt_at = NOW() WHERE id = ?", ['storage/receipts/' . $fname, $uid, $id]);
                        dbExecute("INSERT INTO transactions (transaction_type, reference_number, description, amount, currency_code, payment_method, transaction_date, status, created_by, created_at, payment_period, purpose_note, admin_fee_percent, admin_fee_amount, net_amount) VALUES ('disbursement', CONCAT('DISB-OUT-', ?), ?, ?, 'SDG', 'bank_transfer', CURDATE(), 'posted', ?, NOW(), ?, 'صرف دفعة كفالات', 0, 0, ?)", [$id, 'صرف دفعة #' . $id . ' شهر ' . $b['month'], $b['total_amount'], $uid, $b['month'], $b['total_amount']]);
                        $transactionId = dbFetchOne("SELECT LAST_INSERT_ID() as id")['id'];
                        $entryCode = 'JE-DISB-' . $id . '-' . uniqid();
                        dbExecute("INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by, created_at) VALUES (?, CURDATE(), ?, 'disbursement', ?, 'posted', ?, NOW())", [$entryCode, 'قيد صرف دفعة #' . $id . ' - شهر ' . $b['month'], $id, $uid]);
                        $journalEntryId = dbFetchOne("SELECT LAST_INSERT_ID() as id")['id'];
                        $expenseAccountCode = $b['expense_account_code'] ?: '5110';
                        $expenseAccount = dbFetchOne("SELECT id FROM accounts WHERE code = ?", [$expenseAccountCode]);
                        $cashAccount = dbFetchOne("SELECT id FROM accounts WHERE code = '1100'");
                        if (!$expenseAccount) {
                            throw new Exception('حساب المصروف (' . $expenseAccountCode . ') غير موجود في دليل الحسابات. لا يمكن إتمام القيد المحاسبي.');
                        }
                        if (!$cashAccount) {
                            throw new Exception('حساب الصندوق (1100) غير موجود في دليل الحسابات. لا يمكن إتمام القيد المحاسبي.');
                        }
                        if ($expenseAccount && $cashAccount) {
                            dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, 0, ?)", [$journalEntryId, $expenseAccount['id'], $b['total_amount'], 'صرف دفعة كفالات شهر ' . $b['month']]);
                            dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, 0, ?, ?)", [$journalEntryId, $cashAccount['id'], $b['total_amount'], 'خصم من الصندوق/البنك']);
                        }
                        dbExecute("UPDATE monthly_disbursements SET transaction_id = ? WHERE id = ?", [$transactionId, $id]);
                        dbExecute('COMMIT');

ak_transaction_review_notify_event(
    (int)$b['nanny_id'],
    'تم تحويل دفعة الكفالة وأصبحت متاحة للتنفيذ',
    'تم اعتماد تحويل دفعة الكفالة ويمكنك الآن متابعة وتأكيد بنود الأسر.',
    APP_URL . 'modules/accounting/disbursements.php?view=' . $id,
    $id,
    'disbursement_transferred'
);

flash('success', 'تم رفع الإيصال وتأكيد التحويل وخصم المبلغ من الصندوق بنجاح.');
                    } catch (Exception $e) {
                        dbExecute("ROLLBACK");
                        error_log('Transfer error: ' . $e->getMessage());
                        flash('error', 'حدث خطأ أثناء عملية التحويل: ' . $e->getMessage());
                    }
                } else {
                    flash('error', 'فشل حفظ ملف الإيصال على الخادم.');
                }
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php?view=' . $id);
        exit();
    }
    // 3. Nanny: confirm family item WITH receipt upload (REQUIRED)
    if ($isNanny && isset($_POST['confirm_item'])) {
        $itemId = (int)$_POST['confirm_item'];
        $item = dbFetchOne("SELECT i.*, d.nanny_id, d.status as dstatus FROM disbursement_items i JOIN monthly_disbursements d ON d.id = i.disbursement_id WHERE i.id = ?", [$itemId]);
        if (!$item || $item['nanny_id'] !== $uid || $item['status'] !== 'pending' || !in_array($item['dstatus'], ['transferred', 'received'])) {
            flash('error', 'العنصر غير متاح للتأكيد.');
        } else {
            // Check if receipt was uploaded
            $receiptKey = 'receipt_' . $itemId;
            if (empty($_FILES[$receiptKey]['tmp_name'])) {
                flash('error', 'يرجى رفع إيصال استلام المبلغ (إجباري).');
            } else {
                $uploadResult = uploadFamilyReceipt($_FILES[$receiptKey], $itemId, $item['family_id']);
                if (!$uploadResult['success']) {
                    flash('error', $uploadResult['message']);
                } else {
                    dbExecute("UPDATE disbursement_items SET status='paid', confirmed_at=NOW(), receipt_file_path = ? WHERE id = ?", [$uploadResult['path'], $itemId]);
                    // Log the confirmation
                    disb_audit($uid, 'CONFIRM_ITEM', $item['disbursement_id'], [
                        'item_id' => $itemId,
                        'family_id' => $item['family_id'],
                        'receipt' => $uploadResult['path']
                    ]);
                    flash('success', 'تم تأكيد استلام المبلغ ورفع الإيصال بنجاح.');
                }
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php?view=' . $item['disbursement_id']);
        exit();
    }
    // 4. Nanny: permanently close a partially confirmed batch and return the outstanding amount
    if ($isNanny && isset($_POST['close_with_return'])) {
        $id = (int)$_POST['close_with_return'];
        $reasonType = trim((string)($_POST['return_reason_type'] ?? ''));
        $reasonDetails = trim((string)($_POST['return_reason_details'] ?? ''));
        $reasonMap = [
            'انقطاع الاتصال مع الأم' => 'انقطاع الاتصال مع الأم',
            'تعذر الوصول للأسرة' => 'تعذر الوصول للأسرة',
            'رفض استلام المبلغ' => 'رفض استلام المبلغ',
            'تغيير بيانات الحساب البنكي' => 'تغيير بيانات الحساب البنكي',
            'انتقال الأسرة' => 'انتقال الأسرة',
            'وفاة المستفيد' => 'وفاة المستفيد',
            'إيقاف الكفالة' => 'إيقاف الكفالة',
            'سبب آخر' => 'سبب آخر'
        ];
        if (!isset($reasonMap[$reasonType])) {
            flash('error', 'يرجى اختيار سبب واضح لإرجاع المبلغ.');
        } elseif ($reasonType === 'سبب آخر' && $reasonDetails === '') {
            flash('error', 'يرجى كتابة تفاصيل سبب الإرجاع.');
        } elseif (empty($_FILES['return_receipt']['tmp_name'])) {
            flash('error', 'إيصال إرجاع المبلغ إجباري عند إقفال الدفعة جزئياً.');
        } else {
            $reason = $reasonMap[$reasonType];
            if ($reasonDetails !== '') {
                $reason .= ' — ' . $reasonDetails;
            }
            $uploadResult = uploadReturnReceipt($_FILES['return_receipt'], $id);
            if (!$uploadResult['success']) {
                flash('error', $uploadResult['message']);
            } else {
                $result = closeDisbursementWithReturn($id, $uid, $reason, $uploadResult['path'], $uid);
                if ($result['success']) {
    ak_transaction_review_notify_fm_event(
        $id,
        'disbursement_returned',
        'تم إرجاع دفعة كفالة إلى الصندوق',
        'أقفلت الحاضنة الدفعة جزئياً وأُعيد المبلغ غير المصروف إلى الصندوق. يرجى مراجعة الإجراء.',
        APP_URL . 'modules/accounting/disbursements.php?view=' . $id,
        $uid
    );

    flash('success', $result['message'] . ' المبلغ المعاد: ' . number_format((float)$result['return_amount'], 0) . ' ج.س.');
                } else {
                    // The database was rolled back; remove the uploaded file so we don't leave orphan files.
                    if (!empty($uploadResult['absolute_path']) && is_file($uploadResult['absolute_path'])) {
                        @unlink($uploadResult['absolute_path']);
                    }
                    flash('error', 'تعذر إقفال الدفعة: ' . $result['message']);
                }
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php?view=' . $id);
        exit();
    }
    // 5. Nanny: upload final receipt (OPTIONAL - only when all families confirmed)
    if ($isNanny && isset($_POST['upload_final_receipt'])) {
        $id = (int)$_POST['upload_final_receipt'];
        $b = dbFetchOne("SELECT * FROM monthly_disbursements WHERE id = ? AND nanny_id = ? AND status = 'transferred'", [$id, $uid]);
        if (!$b) {
            flash('error', 'الدفعة غير متاحة.');
        } else {
            // Check all items are paid
            $pending = dbFetchOne("SELECT COUNT(*) as cnt FROM disbursement_items WHERE disbursement_id = ? AND status = 'pending'", [$id]);
            if ((int)$pending['cnt'] > 0) {
                flash('error', 'لا يمكن إقفال الدفعة حتى يتم تأكيد جميع العائلات.');
            } else {
                $markedReceived = false;
                // Check if receipt uploaded
                if (empty($_FILES['final_receipt']['tmp_name'])) {
                    // No receipt uploaded - just mark as received
                    dbExecute("UPDATE monthly_disbursements SET status = 'received', received_at = NOW() WHERE id = ?", [$id]);
                    disb_audit($uid, 'RECEIVE_FINAL', $id, ['status' => 'received', 'no_receipt' => true]);
                    flash('success', 'تم إقفال الدفعة بنجاح (بدون إيصال نهائي).');
                    $markedReceived = true;
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = (string)$finfo->file($_FILES['final_receipt']['tmp_name']);
                    $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
                    if (!isset($allowed[$mime])) {
                        flash('error', 'نوع الملف غير مسموح. يُسمح فقط بـ PDF, JPG, PNG, GIF.');
                    } elseif ($_FILES['final_receipt']['size'] > 10 * 1024 * 1024) {
                        flash('error', 'حجم الملف يجب أن لا يتجاوز 10 ميجابايت.');
                    } else {
                        $ext = $allowed[$mime];
                        $fname = 'final_receipt_' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $dest = dirname(__DIR__, 2) . '/storage/receipts/final/' . $fname;
                        if (!is_dir(dirname($dest))) {
                            mkdir(dirname($dest), 0777, true);
                        }
                        if (move_uploaded_file($_FILES['final_receipt']['tmp_name'], $dest)) {
                            dbExecute("UPDATE monthly_disbursements SET status = 'received', received_at = NOW(), receipt_file_path = CONCAT('storage/receipts/final/', ?) WHERE id = ?", [$fname, $id]);
                            disb_audit($uid, 'RECEIVE_FINAL', $id, ['status' => 'received', 'receipt' => $fname]);
                            flash('success', 'تم رفع الإيصال النهائي وإقفال الدفعة بنجاح.');
                            $markedReceived = true;
                        } else {
                            flash('error', 'فشل حفظ الملف.');
                        }
                    }
                }
                // Once fully received, permanently close the orphan group too — same as the
                // partial-return path already does. This locks the whole group from further
                // nanny edits until the accountant explicitly reopens it (below).
                if ($markedReceived && !empty($b['group_id'])) {
    dbExecute("UPDATE orphan_groups SET verification_status = 'closed', closed_at = NOW(), closed_by = ? WHERE id = ?", [$uid, $b['group_id']]);

    try {
        $assignedAccountants = dbFetchAll(
            "SELECT u.id
             FROM accountant_nanny_assignments ana
             JOIN users u ON u.id = ana.accountant_id
             JOIN roles r ON r.id = u.role_id
             WHERE ana.nanny_id = ?
               AND u.is_active = 1
               AND r.code IN ('accountant_staff', 'accountant')",
            [(int)$b['nanny_id']]
        );

        foreach ($assignedAccountants as $accountant) {
            ak_transaction_review_notify_event(
                (int)$accountant['id'],
                'تم إقفال دفعة الكفالة بالكامل',
                'تم تأكيد استلام جميع بنود دفعة الكفالة وإقفالها من قبل الحاضنة. يرجى مراجعة الدفعة عند الحاجة.',
                APP_URL . 'modules/accounting/disbursements.php?view=' . $id,
                $id,
                'disbursement_received'
            );
        }
    } catch (Throwable $e) {
        // Notification delivery must never affect the completed disbursement closure.
    }
                    // Note: group_workflow_audit_log.action_type has no enum value for "fully closed"
                    // (only group_reopened/family_reopened/etc.), so this event is recorded in the
                    // general audit_log via disb_audit() above instead of being mislabeled here.
                }
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php?view=' . $id);
        exit();
    }
    // 6. Accountant: Reopen an entire closed/returned batch and its group for further nanny edits
    if ($canManageAssigned && isset($_POST['reopen_batch'])) {
        $id = (int)$_POST['reopen_batch'];
        $reason = trim((string)($_POST['reopen_batch_reason'] ?? ''));
        $b = dbFetchOne("SELECT * FROM monthly_disbursements WHERE id = ?", [$id]);
        if (!$b || !in_array($b['status'], ['received', 'returned'], true)) {
            flash('error', 'لا يمكن إعادة فتح هذه الدفعة من حالتها الحالية.');
        } elseif ($isAccountantStaff && !dbFetchOne("SELECT 1 FROM accountant_nanny_assignments WHERE accountant_id = ? AND nanny_id = ?", [$uid, (int)$b['nanny_id']])) {
            flash('error', 'هذه الدفعة ليست ضمن الدفعات المسندة إليك.');
        } elseif ($reason === '') {
            flash('error', 'يرجى كتابة سبب إعادة الفتح.');
        } else {
            try {
                dbExecute('START TRANSACTION');
                $oldStatus = $b['status'];
                dbExecute("UPDATE monthly_disbursements SET status = 'transferred' WHERE id = ?", [$id]);
                if (!empty($b['group_id'])) {
                    dbExecute("UPDATE orphan_groups SET verification_status = 'transferred', closed_at = NULL, closed_by = NULL WHERE id = ?", [$b['group_id']]);
                }
                log_group_action($b['group_id'] ?: null, null, $b['month'], 'group_reopened', $uid, $role, $oldStatus, 'transferred', $reason);
                disb_audit($uid, 'REOPEN_BATCH', $id, ['from' => $oldStatus, 'to' => 'transferred', 'reason' => $reason]);
                dbExecute('COMMIT');

ak_transaction_review_notify_event(
    (int)$b['nanny_id'],
    'تم إعادة فتح دفعة الكفالة',
    'تمت إعادة فتح الدفعة ويمكنك الآن تعديل سجلات الأسر ومتابعة التأكيد.',
    APP_URL . 'modules/accounting/disbursements.php?view=' . $id,
    $id,
    'disbursement_reopened'
);

flash('success', 'تم إعادة فتح الدفعة والمجموعة بالكامل. يمكن للحاضنة الآن تعديل السجلات.');
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                error_log('Reopen batch error: ' . $e->getMessage());
                flash('error', 'تعذر إعادة فتح الدفعة: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php?view=' . $id);
        exit();
    }
    // 7. Accountant: Reopen a single family record within a batch for re-confirmation
    // (e.g. she uploaded the wrong receipt, or regained contact with a family
    // previously marked as lost / returned to the fund).
    if ($canManageAssigned && isset($_POST['reopen_item'])) {
        $itemId = (int)$_POST['reopen_item'];
        $reason = trim((string)($_POST['reopen_item_reason'] ?? ''));
        $item = dbFetchOne(
            "SELECT i.*, d.id AS disbursement_id, d.nanny_id, d.status AS dstatus, d.group_id, d.month
            FROM disbursement_items i JOIN monthly_disbursements d ON d.id = i.disbursement_id
            WHERE i.id = ?", [$itemId]
        );
        $redirectId = $item['disbursement_id'] ?? null;
        if (!$item || !in_array($item['status'], ['paid', 'returned'], true)) {
            flash('error', 'لا يمكن إعادة فتح هذا السجل من حالته الحالية.');
        } elseif ($isAccountantStaff && !dbFetchOne("SELECT 1 FROM accountant_nanny_assignments WHERE accountant_id = ? AND nanny_id = ?", [$uid, (int)$item['nanny_id']])) {
            flash('error', 'هذا السجل ليس ضمن الدفعات المسندة إليك.');
        } elseif ($reason === '') {
            flash('error', 'يرجى كتابة سبب إعادة الفتح.');
        } else {
            try {
                dbExecute('START TRANSACTION');
                $oldItemStatus = $item['status'];
                dbExecute(
                    "UPDATE disbursement_items SET status = 'pending', confirmed_at = NULL, receipt_file_path = NULL,
                    return_reason = NULL, return_requested_at = NULL, return_requested_by_user_id = NULL,
                    returned_at = NULL, returned_by_user_id = NULL, reversal_journal_id = NULL
                    WHERE id = ?", [$itemId]
                );
                // If the parent batch/group had already been fully closed, reopen it too
                // so the nanny actually has somewhere to act on this item.
                if (in_array($item['dstatus'], ['received', 'returned'], true)) {
                    dbExecute("UPDATE monthly_disbursements SET status = 'transferred' WHERE id = ?", [$item['disbursement_id']]);
                    if (!empty($item['group_id'])) {
                        dbExecute("UPDATE orphan_groups SET verification_status = 'transferred', closed_at = NULL, closed_by = NULL WHERE id = ?", [$item['group_id']]);
                    }
                }
                log_group_action($item['group_id'] ?: null, (int)$item['family_id'], $item['month'], 'family_reopened', $uid, $role, $oldItemStatus, 'pending', $reason);
                disb_audit($uid, 'REOPEN_ITEM', (int)$item['disbursement_id'], ['item_id' => $itemId, 'family_id' => $item['family_id'], 'from' => $oldItemStatus, 'reason' => $reason]);
                dbExecute('COMMIT');

ak_transaction_review_notify_event(
    (int)$item['nanny_id'],
    'تم إعادة فتح سجل أسرة في دفعة الكفالة',
    'تمت إعادة فتح سجل الأسرة ويمكنك الآن تأكيده وإرفاق الإيصال من جديد.',
    APP_URL . 'modules/accounting/disbursements.php?view=' . $item['disbursement_id'],
    $itemId,
    'disbursement_item_reopened'
);

flash('success', 'تم إعادة فتح سجل الأسرة للتعديل بنجاح.');
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                error_log('Reopen item error: ' . $e->getMessage());
                flash('error', 'تعذر إعادة فتح السجل: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php' . ($redirectId ? '?view=' . $redirectId : ''));
        exit();
    }

    // 8. Admin/Financial Manager: Void a mistakenly created/transferred batch.
    // Restricted to admin/financial_manager (not accountant/accountant_staff) as a basic
    // segregation-of-duties control — the person who posted an entry should not be the
    // sole party able to reverse it. Properly voids the linked journal entry AND
    // transaction (not just the batch row), matching real accounting practice: reversed,
    // never deleted, so the audit trail stays intact.
    if (in_array($role, ['admin', 'financial_manager'], true) && isset($_POST['void_batch'])) {
        $id = (int)$_POST['void_batch'];
        $reason = trim((string)($_POST['void_batch_reason'] ?? ''));
        $b = dbFetchOne("SELECT * FROM monthly_disbursements WHERE id = ?", [$id]);
        if (!$b || in_array($b['status'], ['voided', 'cancelled', 'draft'], true)) {
            flash('error', 'لا يمكن إبطال هذه الدفعة من حالتها الحالية.');
        } elseif ($reason === '') {
            flash('error', 'يرجى كتابة سبب الإبطال.');
        } else {
            try {
                dbExecute('START TRANSACTION');
                $oldStatus = $b['status'];

                // Void the linked journal entry, if one was actually posted (a batch that
                // never reached the transfer step won't have one — that's fine, skip it).
                // Unreachable in practice: modules/accounting/disbursement_void_guard.php intercepts
                // this exact POST earlier (see config/config.php) and always exit()s first, so this
                // block never runs. Fixed anyway as defense in depth: it never created an offsetting
                // reversal entry, so flipping the original to 'voided' here would have removed its
                // effect from every balance with no correcting entry at all.
                $je = dbFetchOne("SELECT id FROM journal_entries WHERE reference_type = 'disbursement' AND reference_id = ? AND status = 'posted'", [$id]);
                if ($je) {
                    dbExecute(
                        "UPDATE journal_entries SET voided_at = NOW(), voided_by = ?, void_reason = ? WHERE id = ? AND voided_at IS NULL",
                        [$uid, $reason, $je['id']]
                    );
                }
                // Void the linked transaction, found via the same DISB-OUT-{id} convention
                // used when it was created (there is no direct foreign key between
                // transactions and journal_entries in this schema — both are located via
                // their shared reference to the disbursement batch itself).
                $tx = dbFetchOne("SELECT id FROM transactions WHERE reference_number = ? AND status = 'posted'", ['DISB-OUT-' . $id]);
                if ($tx) {
                    dbExecute(
                        "UPDATE transactions SET status = 'voided', voided_at = NOW(), voided_by = ?, void_reason = ? WHERE id = ?",
                        [$uid, $reason, $tx['id']]
                    );
                }
                dbExecute(
                    "UPDATE monthly_disbursements SET status = 'voided', voided_by_user_id = ?, voided_at = NOW(), void_reason = ? WHERE id = ?",
                    [$uid, $reason, $id]
                );

                disb_audit($uid, 'VOID_BATCH', $id, [
                    'from' => $oldStatus, 'to' => 'voided', 'reason' => $reason,
                    'journal_entry_voided' => $je['id'] ?? null, 'transaction_voided' => $tx['id'] ?? null,
                ]);
                dbExecute('COMMIT');

if (in_array($oldStatus, ['transferred', 'received'], true) && !empty($b['nanny_id'])) {
    ak_transaction_review_notify_event(
        (int)$b['nanny_id'],
        'تم إبطال دفعة الكفالة',
        'تم إبطال هذه الدفعة من قبل الإدارة ولم تعد متاحة للتنفيذ.',
        APP_URL . 'modules/accounting/disbursements.php?view=' . $id,
        $id,
        'disbursement_voided'
    );
}

flash('success', 'تم إبطال الدفعة والقيد المحاسبي المرتبط بها بنجاح. يمكن الآن إنشاء دفعة جديدة صحيحة لهذه المجموعة.');
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                error_log('Void batch error: ' . $e->getMessage());
                flash('error', 'تعذر إبطال الدفعة: ' . $e->getMessage());
            }
        }
        header('Location: ' . APP_URL . 'modules/accounting/disbursements.php?view=' . $id);
        exit();
    }
}
// ==========================================
// Fetch data for display
// ==========================================
if ($isNanny) {
    $batches = dbFetchAll("
    SELECT
    d.*,
    og.group_name,
    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id) items_total,
    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='paid') items_paid,
    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='pending') items_pending,
    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='returned') items_returned,
    (SELECT COALESCE(SUM(i.amount),0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='paid') paid_amount,
    (SELECT COALESCE(SUM(i.amount),0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='pending') pending_amount,
    (SELECT COALESCE(SUM(i.amount),0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='returned') returned_amount
    FROM monthly_disbursements d
    LEFT JOIN orphan_groups og ON og.id = d.group_id
    WHERE d.nanny_id = ?
    ORDER BY d.created_at DESC
    ", [$uid]);
} else {
    $scope = '';
    if ($role === 'accountant_staff') {
        $myNannyIds = array_map('intval', array_column(dbFetchAll("SELECT nanny_id FROM accountant_nanny_assignments WHERE accountant_id = ?", [$uid]), 'nanny_id'));
        $scope = $myNannyIds ? 'WHERE d.nanny_id IN (' . implode(',', $myNannyIds) . ')' : 'WHERE 0';
    }
    $sql = "SELECT
    d.*,
    n.full_name nanny_name,
    og.group_name,
    og.id as group_id,
    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id) items_total,
    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='paid') items_paid,
    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='pending') items_pending,
    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='returned') items_returned,
    (SELECT COALESCE(SUM(i.amount),0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='paid') paid_amount,
    (SELECT COALESCE(SUM(i.amount),0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='pending') pending_amount,
    (SELECT COALESCE(SUM(i.amount),0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='returned') returned_amount
    FROM monthly_disbursements d
    LEFT JOIN users n ON n.id = d.nanny_id
    LEFT JOIN orphan_groups og ON og.id = d.group_id
    " . ($scope ? $scope : '') . "
    ORDER BY d.created_at DESC";
    $batches = dbFetchAll($sql);
}
$viewBatch = null;
$viewItems = [];
if ($viewId) {
    $viewBatch = dbFetchOne("
    SELECT
    d.*,
    n.full_name nanny_name,
    og.group_name,
    og.id as group_id,
    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id) items_total,
    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='paid') items_paid,
    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='pending') items_pending,
    (SELECT COUNT(*) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='returned') items_returned,
    (SELECT COALESCE(SUM(i.amount),0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='paid') paid_amount,
    (SELECT COALESCE(SUM(i.amount),0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='pending') pending_amount,
    (SELECT COALESCE(SUM(i.amount),0) FROM disbursement_items i WHERE i.disbursement_id = d.id AND i.status='returned') returned_amount
    FROM monthly_disbursements d
    LEFT JOIN users n ON n.id = d.nanny_id
    LEFT JOIN orphan_groups og ON og.id = d.group_id
    WHERE d.id = ?
    ", [$viewId]);
    if ($viewBatch) {
        $viewItems = dbFetchAll("
        SELECT
        i.*,
        f.family_code,
        f.mother_name,
        (SELECT COUNT(*) FROM family_children WHERE family_id = f.id AND is_active = 1) as children_count
        FROM disbursement_items i
        JOIN families f ON f.id = i.family_id
        WHERE i.disbursement_id = ?
        ORDER BY f.mother_name
        ", [$viewId]);
        $viewBatch['items_total'] = count($viewItems);
        $viewBatch['items_paid'] = count(array_filter($viewItems, fn($it) => $it['status'] === 'paid'));
        $viewBatch['items_pending'] = count(array_filter($viewItems, fn($it) => $it['status'] === 'pending'));
        $viewBatch['items_returned'] = count(array_filter($viewItems, fn($it) => $it['status'] === 'returned'));
        $viewBatch['paid_amount'] = array_sum(array_map(fn($it) => $it['status'] === 'paid' ? (float)$it['amount'] : 0.0, $viewItems));
        $viewBatch['pending_amount'] = array_sum(array_map(fn($it) => $it['status'] === 'pending' ? (float)$it['amount'] : 0.0, $viewItems));
        $viewBatch['returned_amount'] = array_sum(array_map(fn($it) => $it['status'] === 'returned' ? (float)$it['amount'] : 0.0, $viewItems));
        if (!$isNanny && $role === 'accountant_staff') {
            $ok = dbFetchOne("SELECT 1 FROM accountant_nanny_assignments WHERE accountant_id = ? AND nanny_id = ?", [$uid, $viewBatch['nanny_id']]);
            if (!$ok) $viewBatch = null;
        }
    }
}
if ($canManageAssigned) {
    // Shared default month for the pre-creation checklist AND the month input field below,
    // so the preview total is computed for the same month the accountant is about to submit.
    $previewMonth = $_GET['month'] ?? date('Y-m');
    $groups = dbFetchAll("
    SELECT
    og.id,
    og.group_name,
    og.description,
    u.full_name as nanny_name,
    nga.nanny_id,
    COUNT(DISTINCT gc.child_id) as orphan_count,
    COALESCE(SUM(sp.monthly_amount), 0) as expected_amount
    FROM orphan_groups og
    LEFT JOIN nanny_group_assignments nga ON nga.group_id = og.id AND nga.end_date IS NULL
    LEFT JOIN users u ON u.id = nga.nanny_id
    LEFT JOIN group_children gc ON gc.group_id = og.id AND gc.left_date IS NULL
    LEFT JOIN family_children fc ON fc.id = gc.child_id
    INNER JOIN nanny_family_verifications v ON v.family_id = fc.family_id AND v.month = ?
    AND v.is_orphan_verified = 1 AND v.is_mother_contact_verified = 1 AND v.is_bank_verified = 1
    LEFT JOIN sponsorships sp ON sp.child_id = gc.child_id AND sp.status = 'active'
    WHERE og.verification_status IN ('submitted', 'approved', 'pending')
      AND (
          ? = 0
          OR EXISTS (
              SELECT 1
              FROM accountant_nanny_assignments ana
              WHERE ana.accountant_id = ?
                AND ana.nanny_id = nga.nanny_id
          )
      )
    GROUP BY og.id, og.group_name, u.full_name, nga.nanny_id
    ORDER BY og.group_name
    ", [$previewMonth, $isAccountantStaff ? $uid : 0, $isAccountantStaff ? $uid : 0]);
}
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-money-check-dollar me-2"></i>التحويلات الشهرية</h2>
    <p><?php echo $isNanny ? 'استلام الدفعات وتأكيد التوزيع ورفع الإيصالات' : 'إنشاء دفعات الكفالات الشهرية للمجموعات ومتابعة استلامها'; ?></p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($viewBatch): ?>
<div class="card mb-4 fade-in">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>دفعة #<?php echo (int)$viewBatch['id']; ?> - <?php echo e($viewBatch['month']); ?></h5>
        <a href="<?php echo e($backUrl); ?>" class="btn btn-sm btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fas fa-arrow-left me-1"></i> رجوع</a>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="border rounded p-2 text-center">
                    <div class="fs-6 fw-bold"><?php echo e($viewBatch['nanny_name'] ?? '—'); ?></div>
                    <small class="text-muted">الأخصائية</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-2 text-center">
                    <div class="fs-6 fw-bold"><?php echo number_format((float)$viewBatch['total_amount'], 0); ?> ج.س</div>
                    <small class="text-muted">المبلغ الإجمالي</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-2 text-center">
                    <div class="fs-6 fw-bold"><?php echo e($viewBatch['group_name'] ?? 'بدون مجموعة'); ?></div>
                    <small class="text-muted">المجموعة</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-2 text-center">
                    <div class="fs-6 fw-bold">
                        <?php
                        $st = [
                            'pending_approval' => ['بانتظار الاعتماد', 'warning'],
                            'transferred' => ['محوّل', 'primary'],
                            'received' => ['مستلم', 'success'],
                            'returned' => ['مغلق مع إرجاع', 'danger']
                        ];
                        $s = $viewBatch['status'];
                        [$label, $color] = $st[$s] ?? [$s, 'secondary'];
                        ?>
                        <span class="badge bg-<?php echo $color; ?>"><?php echo $label; ?></span>
                    </div>
                    <small class="text-muted">الحالة</small>
                </div>
            </div>
        </div>
        <?php if ($isNanny && in_array($viewBatch['status'], ['transferred', 'returned', 'received'])): ?>
        <div class="row g-2 mt-1">
            <div class="col-md-4">
                <div class="border rounded p-2 text-center bg-success bg-opacity-10">
                    <small class="text-muted">تم تأكيده</small>
                    <div class="fw-bold text-success"><?php echo number_format((float)($viewBatch['paid_amount'] ?? 0), 0); ?> ج.س</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-2 text-center bg-warning bg-opacity-10">
                    <small class="text-muted">متبقي قبل الإقفال</small>
                    <div class="fw-bold text-warning"><?php echo number_format((float)($viewBatch['pending_amount'] ?? 0), 0); ?> ج.س</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-2 text-center bg-danger bg-opacity-10">
                    <small class="text-muted">تم إرجاعه</small>
                    <div class="fw-bold text-danger"><?php echo number_format((float)($viewBatch['returned_amount'] ?? 0), 0); ?> ج.س</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!-- Progress Bar for Nanny -->
    <?php if ($isNanny && in_array($viewBatch['status'], ['transferred', 'received'])): ?>
    <div class="mb-4 p-3 bg-light rounded">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>تقدم التأكيد</strong>
            <span><?php echo (int)$viewBatch['items_paid']; ?>/<?php echo (int)$viewBatch['items_total']; ?> عائلة</span>
        </div>
        <div class="progress" style="height: 25px;">
            <div class="progress-bar <?php echo ($viewBatch['items_paid'] == $viewBatch['items_total'] && $viewBatch['items_total'] > 0) ? 'bg-success' : 'bg-info'; ?>"
                style="width: <?php echo $viewBatch['items_total'] > 0 ? round(($viewBatch['items_paid'] / $viewBatch['items_total']) * 100) : 0; ?>%;">
                <?php echo $viewBatch['items_total'] > 0 ? round(($viewBatch['items_paid'] / $viewBatch['items_total']) * 100) : 0; ?>%
            </div>
        </div>
        <?php if ($viewBatch['items_paid'] == $viewBatch['items_total'] && $viewBatch['items_total'] > 0): ?>
        <div class="text-success mt-1"><i class="fas fa-check-circle"></i> ✅ تم تأكيد جميع العائلات</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>الكود</th>
                    <th>الأسرة</th>
                    <th>الأطفال</th>
                    <th>المبلغ</th>
                    <th>الحالة</th>
                    <th>الإيصال</th>
                    <th>تاريخ التأكيد</th>
                    <?php
                    $anyReopenableItem = false;
                    foreach ($viewItems as $vi) {
                        if (in_array($vi['status'], ['paid', 'returned'], true)) { $anyReopenableItem = true; break; }
                    }
                    $showItemActionCol = ($isNanny && in_array($viewBatch['status'], ['transferred', 'received']))
                        || ($canManageAssigned && $anyReopenableItem);
                    ?>
                    <?php if ($showItemActionCol): ?>
                    <th class="text-center">إجراء</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($viewItems)): ?>
                <tr><td colspan="<?php echo $showItemActionCol ? '8' : '7'; ?>" class="text-center text-muted py-4">لا توجد بنود في هذه الدفعة</td></tr>
                <?php else: ?>
                <?php foreach ($viewItems as $it): ?>
                <tr>
                    <td><?php echo e($it['family_code']); ?></td>
                    <td><?php echo e($it['mother_name']); ?></td>
                    <td><?php echo (int)$it['children_count']; ?></td>
                    <td><?php echo number_format((float)$it['amount'], 0); ?> ج.س</td>
                    <td>
                        <?php
                        $its = [
                            'pending' => ['قيد الانتظار', 'warning'],
                            'paid' => ['مُصرَف', 'success'],
                            'returned' => ['مُعاد للصندوق', 'danger'],
                            'return_requested' => ['طلب إرجاع', 'warning']
                        ];
                        [$il, $ic] = $its[$it['status']] ?? [$it['status'], 'secondary'];
                        ?>
                        <span class="badge bg-<?php echo $ic; ?>"><?php echo $il; ?></span>
                    </td>
                    <td>
                        <?php if (!empty($it['receipt_file_path'])): ?>
                        <!-- FIXED: Use serve_receipt.php instead of direct file access -->
                        <a href="<?php echo APP_URL; ?>modules/accounting/serve_receipt.php?id=<?php echo (int)$it['id']; ?>&kind=item" target="_blank" class="btn btn-sm btn-outline-success" title="عرض الإيصال">
                            <i class="fas fa-paperclip"></i>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($it['confirmed_at'] ?? '—'); ?></td>
                    <?php if ($isNanny && in_array($viewBatch['status'], ['transferred', 'received']) && $it['status'] === 'pending'): ?>
                    <td class="text-center">
                        <form method="post" enctype="multipart/form-data" style="display:inline-block;">
                            <?php echo csrf_field(); ?>
                            <div class="d-flex flex-column gap-1">
                                <input type="file" name="receipt_<?php echo (int)$it['id']; ?>"
                                    class="form-control form-control-sm"
                                    style="width:120px;display:inline-block;"
                                    accept=".jpg,.jpeg,.png,.gif"
                                    required>
                                <button type="submit" name="confirm_item" value="<?php echo (int)$it['id']; ?>"
                                    class="btn btn-sm btn-success w-100">
                                    <i class="fas fa-check"></i> تأكيد
                                </button>
                            </div>
                        </form>
                    </td>
                    <?php elseif ($canManageAssigned && in_array($it['status'], ['paid', 'returned'], true)): ?>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#reopenItemModal<?php echo (int)$it['id']; ?>">
                            <i class="fas fa-unlock"></i> إعادة فتح
                        </button>
                        <div class="modal fade" id="reopenItemModal<?php echo (int)$it['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="reopen_item" value="<?php echo (int)$it['id']; ?>">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">إعادة فتح سجل الأسرة: <?php echo e($it['mother_name']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="text-muted small">سيعود هذا السجل إلى حالة "قيد الانتظار" ليتمكن من تأكيده أو رفع إيصال جديد. سيتم أيضاً إعادة فتح الدفعة والمجموعة بالكامل إذا كانتا مُغلقتين.</p>
                                            <label class="form-label">سبب إعادة الفتح <span class="text-danger">*</span></label>
                                            <textarea name="reopen_item_reason" class="form-control" rows="3" required placeholder="مثال: رفعت الحاضنة إيصالاً خاطئاً، أو تم التواصل مع الأسرة مجدداً بعد فقدان الاتصال"></textarea>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                            <button type="submit" class="btn btn-warning">تأكيد إعادة الفتح</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!-- Accountant: Reopen the entire batch/group once fully closed -->
    <?php if ($canManageAssigned && in_array($viewBatch['status'], ['received', 'returned'])): ?>
    <div class="mt-4 p-3 bg-warning bg-opacity-10 rounded border border-warning">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <i class="fas fa-lock text-warning fa-2x"></i>
                <strong class="ms-2">هذه الدفعة مغلقة حالياً.</strong>
                <br><small class="text-muted">لن تتمكن الحاضنة من تعديل أي سجل حتى تتم إعادة فتح الدفعة، أو إعادة فتح سجل أسرة محدد من الجدول أعلاه.</small>
            </div>
            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#reopenBatchModal">
                <i class="fas fa-unlock me-1"></i> إعادة فتح الدفعة بالكامل
            </button>
        </div>
    </div>
    <div class="modal fade" id="reopenBatchModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="reopen_batch" value="<?php echo (int)$viewBatch['id']; ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">إعادة فتح الدفعة بالكامل</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">ستعود الدفعة والمجموعة المرتبطة بها إلى حالة "محوّل"، ليصبح بإمكان الحاضنة تعديل السجلات مجدداً.</p>
                        <label class="form-label">سبب إعادة الفتح <span class="text-danger">*</span></label>
                        <textarea name="reopen_batch_reason" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-warning">تأكيد إعادة الفتح</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <!-- Admin/Financial Manager: Void a mistakenly created/transferred batch -->
    <?php if (in_array($role, ['admin', 'financial_manager'], true) && !in_array($viewBatch['status'], ['voided', 'cancelled', 'draft'])): ?>
    <div class="mt-4 p-3 bg-danger bg-opacity-10 rounded border border-danger">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <i class="fas fa-triangle-exclamation text-danger fa-2x"></i>
                <strong class="ms-2">إبطال الدفعة (للمدير المالي/المسؤول فقط)</strong>
                <br><small class="text-muted">يُستخدم عند إنشاء دفعة بمبلغ خاطئ أو بدون بنود عن طريق الخطأ. سيتم إبطال القيد المحاسبي والمعاملة المرتبطة بها بشكل صحيح (وليس حذفها)، مع الحفاظ على سجل التدقيق الكامل.</small>
            </div>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#voidBatchModal">
                <i class="fas fa-ban me-1"></i> إبطال الدفعة
            </button>
        </div>
    </div>
    <div class="modal fade" id="voidBatchModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="void_batch" value="<?php echo (int)$viewBatch['id']; ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">إبطال الدفعة #<?php echo (int)$viewBatch['id']; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">
                            سيتم إبطال: (1) القيد المحاسبي المرتبط إن وُجد، (2) المعاملة المالية المرتبطة إن وُجدت، (3) هذه الدفعة نفسها.
                            لن يتم حذف أي سجل — سيبقى الجميع ظاهراً بحالة "مُبطل" في السجلات، ويصبح بإمكان إنشاء دفعة صحيحة جديدة لنفس المجموعة والشهر.
                        </p>
                        <label class="form-label">سبب الإبطال <span class="text-danger">*</span></label>
                        <textarea name="void_batch_reason" class="form-control" rows="3" required placeholder="مثال: تم إنشاء الدفعة بمبلغ خاطئ شمل عائلات غير موثقة، بدون بنود صرف فعلية."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger">تأكيد الإبطال</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <!-- Accountant: Transfer receipt upload -->
    <?php if ($canManage && $viewBatch['status'] === 'pending_approval'): ?>
    <div class="mt-4 p-4 border rounded bg-light">
        <h6 class="mb-3"><i class="fas fa-exclamation-triangle text-warning me-2"></i>تأكيد التحويل ورفع الإيصال</h6>
        <p class="text-muted small mb-3"><strong>مهم:</strong> يجب رفع إيصال التحويل البنكي/الكاش قبل تأكيد عملية التحويل. سيتم خصم المبلغ تلقائياً من رصيد المنظمة.</p>
        <form method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label"><strong>إيصال التحويل (مطلوب)</strong> <span class="text-danger">*</span></label>
                    <input type="file" name="transfer_receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                    <small class="text-muted">PDF, JPG, PNG - الحد الأقصى 10 ميجابايت</small>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-2 text-center bg-white">
                        <small class="text-muted">المبلغ المخصوم</small>
                        <div class="fw-bold text-danger"><?php echo number_format((float)$viewBatch['total_amount'], 0); ?> ج.س</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <button name="transfer_with_receipt" value="<?php echo (int)$viewBatch['id']; ?>" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane me-1"></i> تأكيد التحويل وخصم المبلغ
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
    <!-- Nanny: Permanent closure / return outstanding amount -->
    <?php if ($isNanny && $viewBatch['status'] === 'transferred' && $viewBatch['items_total'] > 0): ?>
        <?php if ((int)$viewBatch['items_pending'] > 0): ?>
        <div class="mt-4 p-3 rounded border border-warning bg-warning bg-opacity-10">
            <div class="d-flex align-items-start gap-3 mb-3">
                <div class="text-warning"><i class="fas fa-triangle-exclamation fa-2x"></i></div>
                <div>
                    <strong>هذه الدفعة ما زالت تحتوي على مبالغ غير مستلمة</strong>
                    <div class="small text-muted mt-1">
                        تم تأكيد <?php echo (int)$viewBatch['items_paid']; ?> من أصل <?php echo (int)$viewBatch['items_total']; ?> أسرة.
                        المتبقي: <strong class="text-danger"><?php echo number_format((float)$viewBatch['pending_amount'], 0); ?> ج.س</strong>
                    </div>
                    <div class="small mt-1">يمكنك إبقاء الدفعة مفتوحة، أو إقفالها نهائياً وإرجاع المبلغ غير المصروف إلى الصندوق.</div>
                </div>
            </div>
            <form method="post" enctype="multipart/form-data" id="returnClosureForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="close_with_return" value="<?php echo (int)$viewBatch['id']; ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">سبب الإرجاع <span class="text-danger">*</span></label>
                        <select name="return_reason_type" class="form-select" required>
                            <option value="">-- اختر السبب --</option>
                            <option value="انقطاع الاتصال مع الأم">انقطاع الاتصال مع الأم</option>
                            <option value="تعذر الوصول للأسرة">تعذر الوصول للأسرة</option>
                            <option value="رفض استلام المبلغ">رفض استلام المبلغ</option>
                            <option value="تغيير بيانات الحساب البنكي">تغيير بيانات الحساب البنكي</option>
                            <option value="انتقال الأسرة">انتقال الأسرة</option>
                            <option value="وفاة المستفيد">وفاة المستفيد</option>
                            <option value="إيقاف الكفالة">إيقاف الكفالة</option>
                            <option value="سبب آخر">سبب آخر</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">تفاصيل إضافية</label>
                        <textarea name="return_reason_details" class="form-control" rows="2" placeholder="اكتب أي تفاصيل مهمة عن سبب الإرجاع"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">إيصال إرجاع المبلغ <span class="text-danger">*</span></label>
                        <input type="file" name="return_receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.gif" required>
                        <small class="text-muted">PDF, JPG, PNG, GIF — الحد الأقصى 10 ميجابايت</small>
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                            <div class="me-auto">
                                <span class="text-muted">المبلغ الذي سيُعاد للصندوق:</span>
                                <strong class="text-danger fs-5"><?php echo number_format((float)$viewBatch['pending_amount'], 0); ?> ج.س</strong>
                            </div>
                            <button type="submit" id="closeWithReturnBtn" class="btn btn-danger">
                                <i class="fas fa-rotate-left me-1"></i> إقفال الدفعة وإرجاع المبلغ
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="mt-4 p-3 bg-success bg-opacity-10 rounded border border-success">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <i class="fas fa-check-circle text-success fa-2x"></i>
                    <strong class="ms-2">تم تأكيد استلام جميع العائلات (<?php echo (int)$viewBatch['items_paid']; ?>/<?php echo (int)$viewBatch['items_total']; ?>)</strong>
                    <br><small class="text-muted">يمكنك إقفال الدفعة الآن. الإيصال النهائي اختياري.</small>
                </div>
                <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center flex-wrap">
                    <?php echo csrf_field(); ?>
                    <input type="file" name="final_receipt" class="form-control form-control-sm" style="width:auto;display:inline-block;" accept=".pdf,.jpg,.jpeg,.png,.gif">
                    <button type="submit" name="upload_final_receipt" value="<?php echo (int)$viewBatch['id']; ?>" class="btn btn-success">
                        <i class="fas fa-upload me-1"></i> رفع الإيصال النهائي وإقفال الدفعة
                    </button>
                    <button type="submit" name="upload_final_receipt" value="<?php echo (int)$viewBatch['id']; ?>" class="btn btn-outline-secondary">
                        إقفال بدون إيصال
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($viewBatch['return_receipt_file_path'])): ?>
    <div class="mt-3">
        <a href="<?php echo APP_URL . e($viewBatch['return_receipt_file_path']); ?>" target="_blank" class="btn btn-outline-danger">
            <i class="fas fa-rotate-left me-1"></i> عرض إيصال إرجاع المبلغ
        </a>
        <small class="text-muted ms-2">المبلغ المعاد: <?php echo number_format((float)($viewBatch['returned_amount'] ?? 0), 0); ?> ج.س</small>
    </div>
    <?php endif; ?>
    <?php if (!empty($viewBatch['receipt_file_path'])): ?>
    <div class="mt-3">
        <!-- FIXED: Use serve_receipt.php instead of disbursement_receipt.php -->
        <a href="<?php echo APP_URL; ?>modules/accounting/serve_receipt.php?id=<?php echo (int)$viewBatch['id']; ?>&kind=batch" target="_blank" class="btn btn-success">
            <i class="fas fa-file-alt me-1"></i> عرض الإيصال المرفوع
        </a>
    </div>
    <?php endif; ?>
</div>
</div>
<?php endif; ?>
<?php if ($canManageAssigned && !$viewId): ?>
<div class="card mb-4 fade-in">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-plus me-2"></i>إنشاء دفعات شهر جديد</h5></div>
    <div class="card-body">
        <form method="post">
            <?php echo csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">الشهر</label>
                    <input type="month" name="month" class="form-control" value="<?php echo e($previewMonth); ?>" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label">المجموعات (سيتم إنشاء دفعات للعائلات الموثقة فقط)</label>
                    <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($groups)): ?>
                        <p class="text-muted mb-0">لا توجد مجموعات متاحة لإنشاء دفعات لها.</p>
                        <?php else: ?>
                        <?php foreach ($groups as $g): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="group_ids[]" value="<?php echo (int)$g['id']; ?>" id="group_<?php echo (int)$g['id']; ?>">
                            <label class="form-check-label" for="group_<?php echo (int)$g['id']; ?>">
                                <strong><?php echo e($g['group_name']); ?></strong> — <?php echo e($g['nanny_name'] ?? 'بدون مربية'); ?>
                                <br><small class="text-muted"><?php echo (int)$g['orphan_count']; ?> يتيم — المتوقع: <?php echo number_format((float)$g['expected_amount'], 0); ?> ج.س</small>
                            </label>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" name="create_batch" class="btn btn-primary"><i class="fas fa-save me-1"></i> إنشاء الدفعات</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if (!$viewId): ?>
<div class="card fade-in">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>سجل الدفعات</h5>
        <?php if ($isNanny): ?>
        <span class="badge bg-primary"><?php echo count($batches); ?> دفعة</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($batches)): ?>
        <p class="text-muted text-center py-4">لا توجد دفعات</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الشهر</th>
                        <th>المجموعة</th>
                        <th>الأخصائية</th>
                        <th>المبلغ</th>
                        <th>التقدم</th>
                        <th>الحالة</th>
                        <th class="text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $b): ?>
                    <tr>
                        <td><?php echo (int)$b['id']; ?></td>
                        <td><?php echo e($b['month']); ?></td>
                        <td><?php echo e($b['group_name'] ?? '—'); ?></td>
                        <td><?php echo e($b['nanny_name'] ?? '—'); ?></td>
                        <td><?php echo number_format((float)$b['total_amount'], 0); ?> ج.س</td>
                        <td>
                            <div class="progress" style="height:8px;min-width:80px">
                                <div class="progress-bar <?php echo ($b['items_total'] > 0 && $b['items_paid'] == $b['items_total']) ? 'bg-success' : 'bg-info'; ?>"
                                    style="width:<?php echo $b['items_total'] ? round(100 * $b['items_paid'] / $b['items_total']) : 0; ?>%">
                                </div>
                            </div>
                            <small class="text-muted"><?php echo (int)$b['items_paid']; ?>/<?php echo (int)$b['items_total']; ?></small>
                            <?php if ($isNanny && $b['status'] === 'transferred' && $b['items_pending'] > 0): ?>
                            <span class="badge bg-warning text-dark ms-1"><?php echo (int)$b['items_pending']; ?> في الانتظار</span>
                            <?php elseif ($b['status'] === 'returned' && !empty($b['items_returned'])): ?>
                            <span class="badge bg-danger ms-1"><?php echo (int)$b['items_returned']; ?> مُعادة</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $st = [
                                'pending_approval' => ['بانتظار الاعتماد', 'warning'],
                                'transferred' => ['محوّل', 'primary'],
                                'received' => ['مستلم', 'success'],
                                'returned' => ['مغلق مع إرجاع', 'danger']
                            ];
                            $s = $b['status'];
                            [$label, $color] = $st[$s] ?? [$s, 'secondary'];
                            ?>
                            <span class="badge bg-<?php echo $color; ?>"><?php echo $label; ?></span>
                        </td>
                        <td class="text-center">
                            <a href="?view=<?php echo (int)$b['id']; ?>&return=<?php echo rawurlencode(http_build_query($_GET)); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> عرض</a>
                            <?php if ($canManage && $b['status'] === 'pending_approval'): ?>
                            <a href="?view=<?php echo (int)$b['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-upload me-1"></i> تحويل</a>
                            <?php endif; ?>
                            <?php if (!empty($b['receipt_file_path'])): ?>
                            <!-- FIXED: Use serve_receipt.php instead of disbursement_receipt.php -->
                            <a href="<?php echo APP_URL; ?>modules/accounting/serve_receipt.php?id=<?php echo (int)$b['id']; ?>&kind=batch" target="_blank" class="btn btn-sm btn-outline-success" title="الإيصال"><i class="fas fa-paperclip"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<!-- SweetAlert2 for Custom Confirmations -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Family receipt confirmation (Nanny)
    document.querySelectorAll('button[name="confirm_item"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const itemId = this.value;
            const fileInput = form.querySelector('input[name="receipt_' + itemId + '"]');
            if (!fileInput || fileInput.files.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'يرجى اختيار إيصال أولاً',
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: '#198754'
                });
                return;
            }
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: 'سيتم تأكيد استلام المبلغ ورفع الإيصال',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، تأكيد',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Preserve the clicked submit action/name when submitting programmatically.
                    const submitter = document.createElement('input');
                    submitter.type = 'hidden';
                    submitter.name = 'confirm_item';
                    submitter.value = itemId;
                    form.appendChild(submitter);
                    form.submit();
                }
            });
        });
    });
    // 2. Partial closure: return outstanding amount to the organization cash
    const returnClosureForm = document.getElementById('returnClosureForm');
    if (returnClosureForm) {
        returnClosureForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const reason = form.querySelector('[name="return_reason_type"]');
            const receipt = form.querySelector('[name="return_receipt"]');
            const amount = <?php echo isset($viewBatch) ? json_encode(number_format((float)($viewBatch['pending_amount'] ?? 0), 0), JSON_UNESCAPED_UNICODE) : '"0"'; ?>;
            if (!reason || !reason.value) {
                Swal.fire({icon:'warning', title:'تنبيه', text:'يرجى اختيار سبب الإرجاع أولاً', confirmButtonText:'حسناً'});
                return;
            }
            if (!receipt || receipt.files.length === 0) {
                Swal.fire({icon:'warning', title:'تنبيه', text:'يرجى اختيار إيصال إرجاع المبلغ أولاً', confirmButtonText:'حسناً'});
                return;
            }
            Swal.fire({
                title: 'هل أنت متأكد من إقفال الدفعة؟',
                html: '<div class="text-start">' +
                    '<p>سيتم إقفال المجموعة نهائياً وتحويل الأسر غير المؤكدة إلى <strong>مُعادة</strong>.</p>' +
                    '<p class="mb-2">سيتم إعادة <strong>' + amount + ' ج.س</strong> إلى صندوق المنظمة.</p>' +
                    '<p class="text-danger mb-0"><strong>بعد الإقفال لن تتمكن من تأكيد هذه الأسر.</strong></p>' +
                    '</div>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، إقفال وإرجاع المبلغ',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then(function(result) {
                if (result.isConfirmed) {
                    const btn = document.getElementById('closeWithReturnBtn');
                    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جارٍ الإقفال...'; }
                    form.submit();
                }
            });
        });
    }
    // 3. Transfer confirmation (Accountant)
    document.querySelectorAll('button[name="transfer_with_receipt"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const fileInput = form.querySelector('input[name="transfer_receipt"]');
            const amount = '<?php echo isset($viewBatch) ? number_format((float)$viewBatch['total_amount'], 0) : '0'; ?>';
            if (!fileInput || fileInput.files.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'يرجى اختيار إيصال التحويل أولاً',
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: '#0d6efd'
                });
                return;
            }
            Swal.fire({
                title: 'تأكيد التحويل',
                html: '<div class="text-start"><p>سيتم:</p><ul class="list-unstyled mb-0"><li>✓ رفع إيصال التحويل</li><li>✓ خصم <strong>' + amount + ' ج.س</strong> من الصندوق</li><li>✓ تغيير الحالة إلى "محوّل"</li></ul></div>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، تأكيد التحويل',
                cancelButtonText: 'إلغاء',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    const submitter = document.createElement('input');
                    submitter.type = 'hidden';
                    submitter.name = 'transfer_with_receipt';
                    submitter.value = form.querySelector('button[name="transfer_with_receipt"]')?.value || '';
                    form.appendChild(submitter);
                    form.submit();
                }
            });
        });
    });
    // 4. Close without receipt (Nanny)
    document.querySelectorAll('button[name="upload_final_receipt"]').forEach(function(btn) {
        if (btn.textContent.includes('إقفال بدون إيصال')) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('form');
                const btnValue = this.value;
                Swal.fire({
                    title: 'إقفال الدفعة',
                    text: 'هل تريد إقفال الدفعة بدون إيصال نهائي؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#6c757d',
                    cancelButtonColor: '#198754',
                    confirmButtonText: 'نعم، إقفال بدون إيصال',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // form.submit() does NOT include any button's name/value pair,
                        // so PHP never saw upload_final_receipt. Attach it manually.
                        const submitter = document.createElement('input');
                        submitter.type = 'hidden';
                        submitter.name = 'upload_final_receipt';
                        submitter.value = btnValue;
                        form.appendChild(submitter);
                        form.submit();
                    }
                });
            });
        }
    });
});
</script>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>