<<<<<<< HEAD
<?php
// modules/transactions/create.php - Unified Payment Entry & Journal Posting (v7: history below, details & edit modals)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'accountant', 'accountant_staff', 'financial_manager', 'supervisor', 'vice_general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$uid = Session::getUserId();
$pageTitle = 'تسجيل دفعة';
$active = 'transactions';
ak_ensure_tables(); ak_seed_accounts();

function ak_period_months(?string $p): ?int {
    if (!$p) return null;
    $d = DateTime::createFromFormat('!F/Y', $p);
    return $d ? ((int)$d->format('Y') * 12 + (int)$d->format('n')) : null;
}
$monthOptions = [];
for ($i = -24; $i <= 12; $i++) $monthOptions[] = date('F/Y', strtotime(date('Y-m-01') . " $i months"));
$CURRENT_MONTH = date('F/Y');
$CUR_IDX = (int)date('Y') * 12 + (int)date('n');

$purposeLabels = [
    'monthly_sponsorship' => 'كفالة شهرية',
    'school_fees' => 'رسوم دراسية',
    'medicine' => 'علاج وأدوية',
    'gift' => 'هدية/عيدية',
    'other' => 'أخرى',
];

$defaultFee = 5.0;
$errors = [];
$input = [
    'type' => 'monthly_sponsorship',
    'sponsorship_id' => (int)($_GET['sponsorship_id'] ?? $_POST['sponsorship_id'] ?? 0),
    'sponsor_id' => (int)($_GET['sponsor_id'] ?? $_POST['sponsor_id'] ?? 0),
    'project_id' => (int)($_POST['project_id'] ?? 0),
    'amount' => '',
    'date' => date('Y-m-d'),
    'method' => 'cash',
    'receipt' => '',
    'reference' => '',
    'other_source_note' => '',
    'description' => '',
    'fee' => (string)$defaultFee
];

$pre = $input['sponsorship_id'] ? dbFetchOne("SELECT id, monthly_amount, sponsor_id FROM sponsorships WHERE id = ?", [$input['sponsorship_id']]) : null;
if ($pre) {
    if ($input['amount'] === '') $input['amount'] = (string)((float)$pre['monthly_amount']);
    if ($input['sponsor_id'] <= 0) $input['sponsor_id'] = (int)$pre['sponsor_id'];
}

$mySponsorIds = [];
if ($role === 'supervisor') {
    $myLetterIds = array_map('intval', array_column(dbFetchAll("SELECT letter_id FROM supervisor_letters WHERE supervisor_id = ?", [$uid]), 'letter_id'));
    $sqlScope = "SELECT s.id FROM sponsors s WHERE s.supervisor_id = ?";
    $paramsScope = [$uid];
    if ($myLetterIds) {
        $ph = implode(',', array_fill(0, count($myLetterIds), '?'));
        $sqlScope .= " OR s.first_letter_id IN ($ph)";
        $paramsScope = array_merge($paramsScope, $myLetterIds);
    }
    $mySponsorIds = array_map('intval', array_column(dbFetchAll($sqlScope, $paramsScope), 'id'));
}

$projects = dbFetchAll("SELECT id, name FROM other_projects ORDER BY name");

$sSql = "SELECT sp.id, sp.sponsorship_code, sp.monthly_amount, s.full_name AS sponsor_name, fc.child_name
         FROM sponsorships sp
         JOIN sponsors s ON s.id = sp.sponsor_id
         JOIN family_children fc ON fc.id = sp.child_id
         WHERE sp.status = 'active'";
$sParams = [];
if ($input['sponsor_id'] > 0) {
    $sSql .= " AND s.id = ?";
    $sParams[] = $input['sponsor_id'];
} elseif ($role === 'supervisor') {
    if (!$mySponsorIds) $sSql .= " AND 0 = 1";
    else {
        $ph = implode(',', array_fill(0, count($mySponsorIds), '?'));
        $sSql .= " AND s.id IN ($ph)";
        $sParams = $mySponsorIds;
    }
}
$sSql .= " ORDER BY s.full_name LIMIT 500";
$ships = dbFetchAll($sSql, $sParams);

$currentSponsor = null;
if ($input['sponsor_id'] > 0) {
    $currentSponsor = dbFetchOne("SELECT id, full_name, sponsor_code FROM sponsors WHERE id = ?", [$input['sponsor_id']]);
}

/* ---- EDIT HANDLER ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_record']) && verify_csrf()) {
    $src = $_POST['edit_src'] ?? '';
    $rid = (int)$_POST['edit_id'];
    $newMonth = trim($_POST['edit_month'] ?? '');
    $newPurpose = trim($_POST['edit_purpose'] ?? '');
    $newNote = trim($_POST['edit_note'] ?? '');
    $newAmount = (float)str_replace(',', '', $_POST['edit_amount'] ?? 0);
    
    $canEdit = in_array($role, ['admin', 'financial_manager', 'accountant'], true);
    if ($src === 'pending' && $role === 'supervisor') {
        $chk = dbFetchOne("SELECT supervisor_id FROM sponsor_payments WHERE id = ?", [$rid]);
        if ($chk && (int)$chk['supervisor_id'] === $uid) $canEdit = true;
    }
    
    if (!$canEdit) $errors[] = 'لا تملك صلاحية التعديل.';
    elseif ($newAmount <= 0) $errors[] = 'المبلغ يجب أن يكون أكبر من صفر.';
    else {
        $lineReceiptPath = null;
        if (isset($_FILES['edit_receipt_file']) && $_FILES['edit_receipt_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['edit_receipt_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','pdf'], true) && $file['size'] <= 10*1024*1024) {
                $dir = dirname(__DIR__, 2) . '/storage/receipts';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $fn = 'TR-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . '/' . $fn)) $lineReceiptPath = 'storage/receipts/' . $fn;
            } else $errors[] = 'صيغة أو حجم إيصال البند غير صالح.';
        }
        $uniReceiptPath = null;
        if (isset($_FILES['edit_unified_file']) && $_FILES['edit_unified_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['edit_unified_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','pdf'], true) && $file['size'] <= 10*1024*1024) {
                $dir = dirname(__DIR__, 2) . '/storage/receipts';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $fn = 'TR-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . '/' . $fn)) $uniReceiptPath = 'storage/receipts/' . $fn;
            } else $errors[] = 'صيغة أو حجم الإيصال الموحّد غير صالح.';
        }
        
        if (!$errors) {
            if ($src === 'pending') {
                $old = dbFetchOne("SELECT * FROM sponsor_payments WHERE id = ?", [$rid]);
                if ($old) {
                    $upd = [
                        'payment_period' => $newMonth,
                        'payment_type' => $newPurpose,
                        'purpose_note' => $newNote !== '' ? $newNote : null,
                        'amount' => $newAmount,
                    ];
                    if ($lineReceiptPath) $upd['receipt_file_path'] = $lineReceiptPath;
                    if ($uniReceiptPath) $upd['unified_receipt_path'] = $uniReceiptPath;
                    
                    $sets = []; $params = [];
                    foreach ($upd as $k => $v) { $sets[] = "`$k` = ?"; $params[] = $v; }
                    $params[] = $rid;
                    dbExecute("UPDATE sponsor_payments SET " . implode(', ', $sets) . " WHERE id = ?", $params);
                    
                    try {
                        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                                   VALUES (?, 'EDIT_PENDING', 'sponsor_payments', ?, ?, ?, ?, ?)",
                            [$uid, $rid, json_encode($old, JSON_UNESCAPED_UNICODE), json_encode($upd, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    } catch (Throwable $e) {}
                    flash('success', 'تم تعديل الدفعة المعلقة بنجاح.');
                }
            } else {
                $old = dbFetchOne("SELECT * FROM transactions WHERE id = ?", [$rid]);
                if ($old) {
                    $isSpon = ($newPurpose === 'monthly_sponsorship');
                    $txnType = $isSpon ? 'sponsorship_payment' : 'general_donation';
                    if ($old['transaction_type'] === 'project_donation') $txnType = 'project_donation';
                    if ($old['transaction_type'] === 'other' && !$isSpon) $txnType = 'other';
                    
                    $feePct = $isSpon ? (float)$old['admin_fee_percent'] : 0.0;
                    $feeAmt = round($newAmount * $feePct / 100, 2);
                    $net = round($newAmount - $feeAmt, 2);
                    
                    $upd = [
                        'payment_period' => $newMonth,
                        'purpose' => $newPurpose,
                        'purpose_note' => $newNote !== '' ? $newNote : null,
                        'amount' => $newAmount,
                        'admin_fee_amount' => $feeAmt,
                        'net_amount' => $net,
                    ];
                    if ($lineReceiptPath) $upd['receipt_path'] = $lineReceiptPath;
                    if ($uniReceiptPath) $upd['unified_receipt_path'] = $uniReceiptPath;
                    
                    $sets = []; $params = [];
                    foreach ($upd as $k => $v) { $sets[] = "`$k` = ?"; $params[] = $v; }
                    $params[] = $rid;
                    dbExecute("UPDATE transactions SET " . implode(', ', $sets) . " WHERE id = ?", $params);
                    
                    if ((float)$old['amount'] !== $newAmount || ($old['purpose'] ?? '') !== $newPurpose) {
                        ak_void_journal_for_transaction($rid, 'تعديل بيانات الدفعة');
                        ak_post_transaction_journal($rid);
                    }
                    
                    try {
                        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                                   VALUES (?, 'EDIT_POSTED', 'transactions', ?, ?, ?, ?, ?)",
                            [$uid, $rid, json_encode($old, JSON_UNESCAPED_UNICODE), json_encode($upd, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                        $gms = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code IN ('general_manager','vice_general_manager') AND u.is_active = 1");
                        foreach ($gms as $g) {
                            dbExecute("INSERT INTO notifications (user_id, title, body, link) VALUES (?, ?, ?, ?)",
                                [$g['id'], 'تعديل دفعة مرحّلة', 'تم تعديل بيانات دفعة مرحّلة بواسطة ' . (Session::getUserName() ?? '') . '.', 'modules/transactions/index.php']);
                        }
                    } catch (Throwable $e) {}
                    flash('success', 'تم تعديل الدفعة المرحّلة وتحديث القيود المحاسبية بنجاح.');
                }
            }
            header('Location: ' . APP_URL . 'modules/transactions/create.php?sponsor_id=' . $input['sponsor_id']);
            exit();
        }
    }
}

/* ---- CREATE HANDLER ---- */
$lines = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_record'])) {
    $pm = $_POST['pay_month'] ?? [];
    $pp = $_POST['pay_purpose'] ?? [];
    $pn = $_POST['pay_note'] ?? [];
    $pa = $_POST['pay_amount'] ?? [];
    foreach ($pm as $i => $m) {
        $m = trim((string)$m);
        $pur = trim((string)($pp[$i] ?? ''));
        $note = trim((string)($pn[$i] ?? ''));
        $a = trim((string)($pa[$i] ?? ''));
        if ($m === '' && $a === '' && $pur === '' && $note === '') continue;
        $lines[] = ['idx' => $i, 'month' => $m, 'purpose' => $pur, 'note' => $note, 'amount' => $a];
    }
} else {
    $lines[] = ['idx' => 0, 'month' => $CURRENT_MONTH, 'purpose' => 'monthly_sponsorship', 'note' => '', 'amount' => $input['amount']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_record'])) {
    if (!verify_csrf()) $errors[] = 'انتهت صلاحية الجلسة.';
    else {
        $input['type'] = in_array($_POST['type'] ?? '', ['monthly_sponsorship','admin_fee','general_donation','project_donation','other'], true) ? $_POST['type'] : 'monthly_sponsorship';
        $input['sponsorship_id'] = (int)($_POST['sponsorship_id'] ?? 0);
        $input['sponsor_id'] = (int)($_POST['sponsor_id'] ?? 0);
        $input['project_id'] = (int)($_POST['project_id'] ?? 0);
        $input['amount'] = trim($_POST['amount'] ?? '');
        $input['date'] = trim($_POST['date'] ?? '') ?: date('Y-m-d');
        $input['method'] = in_array($_POST['method'] ?? '', ['cash','bank_transfer','credit_card','mobile','other'], true) ? $_POST['method'] : 'cash';
        $input['receipt'] = trim($_POST['receipt'] ?? '');
        $input['reference'] = trim($_POST['reference'] ?? '');
        $input['other_source_note'] = trim($_POST['other_source_note'] ?? '');
        $input['description'] = trim($_POST['description'] ?? '');
        $input['fee'] = (float)($_POST['fee'] ?? $defaultFee);

        $amount = (float)str_replace(',', '', $input['amount']);

        $ship = null;
        if ($input['sponsorship_id'] > 0) {
            $ship = dbFetchOne("SELECT id, sponsor_id, status FROM sponsorships WHERE id = ?", [$input['sponsorship_id']]);
            if ($ship) $input['sponsor_id'] = (int)$ship['sponsor_id'];
        }

        $unifiedPath = null;
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['receipt_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
                if ($file['size'] <= 10 * 1024 * 1024) {
                    $dir = dirname(__DIR__, 2) . '/storage/receipts';
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);
                    $fileName = 'TR-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $fileName)) $unifiedPath = 'storage/receipts/' . $fileName;
                    else $errors[] = 'فشل حفظ ملف الإيصال على الخادم.';
                } else $errors[] = 'حجم ملف الإيصال يتجاوز 10 ميجابايت.';
            } else $errors[] = 'صيغة الإيصال يجب أن تكون JPG أو PNG أو PDF.';
        } elseif (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'حدث خطأ أثناء رفع الإيصال.';
        }

        $lineReceipts = [];
        if (isset($_FILES['pay_receipt_file']) && is_array($_FILES['pay_receipt_file']['name'] ?? null)) {
            $dir = dirname(__DIR__, 2) . '/storage/receipts';
            foreach (array_keys($_FILES['pay_receipt_file']['name']) as $i) {
                $lineReceipts[$i] = null;
                $err = $_FILES['pay_receipt_file']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                if ($err !== UPLOAD_ERR_OK) { $errors[] = 'خطأ في رفع إيصال السطر ' . ($i + 1) . '.'; continue; }
                $name = $_FILES['pay_receipt_file']['name'][$i];
                $tmp  = $_FILES['pay_receipt_file']['tmp_name'][$i];
                $size = $_FILES['pay_receipt_file']['size'][$i];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) { $errors[] = 'صيغة إيصال السطر ' . ($i + 1) . ' يجب أن تكون JPG أو PNG أو PDF.'; continue; }
                if ($size > 10 * 1024 * 1024) { $errors[] = 'حجم إيصال السطر ' . ($i + 1) . ' يتجاوز 10MB.'; continue; }
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $fn = 'TR-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (move_uploaded_file($tmp, $dir . '/' . $fn)) $lineReceipts[$i] = 'storage/receipts/' . $fn;
                else $errors[] = 'فشل حفظ إيصال السطر ' . ($i + 1) . '.';
            }
        }

        $validPurposes = array_keys($purposeLabels);
        if ($input['type'] === 'monthly_sponsorship') {
            if (!$ship) $errors[] = 'اختر الكفالة (اليتيم).';
            if ($ship && $ship['status'] !== 'active') $errors[] = 'الكفالة المحددة غير نشطة.';
            if (!$lines) $errors[] = 'أضف سطراً واحداً على الأقل (شهر + غرض + مبلغ).';
            $seen = [];
            foreach ($lines as $ln) {
                if ($ln['month'] === '') { $errors[] = 'اختر الشهر لكل سطر.'; continue; }
                if (!in_array($ln['month'], $monthOptions, true)) { $errors[] = 'شهر غير صالح: ' . $ln['month']; continue; }
                if (!in_array($ln['purpose'], $validPurposes, true)) { $errors[] = 'غرض غير صالح.'; continue; }
                if ($ln['purpose'] === 'other' && $ln['note'] === '') $errors[] = 'حدد توضيح الغرض للشهر: ' . $ln['month'];
                $key = $ln['month'] . '|' . $ln['purpose'];
                if (isset($seen[$key])) $errors[] = 'سطر مكرر (نفس الشهر والغرض): ' . $ln['month'] . ' — ' . ($purposeLabels[$ln['purpose']] ?? '');
                $seen[$key] = true;
                if ((float)str_replace(',', '', $ln['amount']) <= 0) $errors[] = 'المبلغ يجب أن يكون أكبر من صفر للشهر: ' . $ln['month'];
            }
        } else {
            if ($amount <= 0) $errors[] = 'المبلغ يجب أن يكون أكبر من صفر.';
            if ($input['type'] === 'project_donation' && $input['project_id'] <= 0) $errors[] = 'اختر المشروع.';
            if ($input['type'] === 'other' && $input['other_source_note'] === '') $errors[] = 'يجب تحديد مصدر الدفعة.';
        }
        if (!$errors && $role === 'supervisor' && $input['sponsor_id'] > 0 && !in_array($input['sponsor_id'], $mySponsorIds, true)) {
            $errors[] = 'هذا الكفيل خارج نطاق إشرافك.';
        }

        if (!$errors) {
            if ($role === 'supervisor') {
                $count = 0;
                if ($input['type'] === 'monthly_sponsorship') {
                    foreach ($lines as $ln) {
                        $own = $lineReceipts[$ln['idx']] ?? null;
                        dbExecute("INSERT INTO sponsor_payments
                            (sponsorship_id, supervisor_id, payment_type, sponsor_id, project_id, other_source_note, purpose_note, payment_period, amount, currency_code, receipt_file_path, unified_receipt_path, notes, status)
                            VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, 'SDG', ?, ?, ?, 'pending')",
                            [$ship['id'], $uid, $ln['purpose'], $input['sponsor_id'],
                             $ln['note'] !== '' ? $ln['note'] : null, $ln['month'], (float)str_replace(',', '', $ln['amount']),
                             $own, $unifiedPath, $input['description'] !== '' ? $input['description'] : null]);
                        $count++;
                    }
                } else {
                    dbExecute("INSERT INTO sponsor_payments
                        (sponsorship_id, supervisor_id, payment_type, sponsor_id, project_id, other_source_note, purpose_note, payment_period, amount, currency_code, receipt_file_path, unified_receipt_path, notes, status)
                        VALUES (NULL, ?, ?, ?, ?, ?, NULL, NULL, ?, 'SDG', ?, NULL, ?, 'pending')",
                        [$uid, $input['type'], $input['sponsor_id'] > 0 ? $input['sponsor_id'] : null,
                         $input['project_id'] > 0 ? $input['project_id'] : null,
                         $input['other_source_note'] !== '' ? $input['other_source_note'] : null,
                         $amount, $unifiedPath, $input['description'] !== '' ? $input['description'] : null]);
                    $count = 1;
                }
                flash('success', "تم استلام $count دفعة وإرسالها للمدير المالي للاعتماد والترحيل.");
                header('Location: ' . APP_URL . 'modules/sponsors/view.php?id=' . $input['sponsor_id']);
                exit();
            } else {
                $txnTypeMap = ['monthly_sponsorship' => 'sponsorship_payment', 'admin_fee' => 'other', 'general_donation' => 'general_donation', 'project_donation' => 'project_donation', 'other' => 'other'];
                $count = 0;
                if ($input['type'] === 'monthly_sponsorship') {
                    $postLines = array_map(fn($l) => [
                        'idx' => $l['idx'], 'month' => $l['month'], 'purpose' => $l['purpose'], 'note' => $l['note'],
                        'amount' => (float)str_replace(',', '', $l['amount']), 'own' => $lineReceipts[$l['idx']] ?? null, 'uni' => $unifiedPath
                    ], $lines);
                } else {
                    $postLines = [['idx' => 0, 'month' => null, 'purpose' => null, 'note' => $input['other_source_note'], 'amount' => $amount, 'own' => $unifiedPath, 'uni' => null]];
                }

                foreach ($postLines as $pl) {
                    $isSpon = ($pl['purpose'] === 'monthly_sponsorship');
                    $txnType = $pl['purpose'] === null ? $txnTypeMap[$input['type']] : ($isSpon ? 'sponsorship_payment' : 'general_donation');
                    $feePct = $isSpon ? (float)$input['fee'] : 0.0;
                    $feeAmt = round($pl['amount'] * $feePct / 100, 2);
                    $net = round($pl['amount'] - $feeAmt, 2);
                    $purLabel = $pl['purpose'] !== null ? ($purposeLabels[$pl['purpose']] ?? $pl['purpose']) : '';
                    $desc = trim(($input['description'] !== '' ? $input['description'] : 'دفعة')
                        . ($purLabel !== '' ? ' — ' . $purLabel : '')
                        . ($pl['month'] ? ' عن شهر ' . $pl['month'] : '')
                        . ($pl['note'] !== '' ? ' (' . $pl['note'] . ')' : ''));
                    $n = (int)(dbFetchOne("SELECT COUNT(*) c FROM transactions")['c'] ?? 0) + 1;
                    $code = 'TR-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
                    dbExecute("INSERT INTO transactions
                        (sponsorship_id, amount, currency_code, payment_method, transaction_date, receipt_number, description,
                         months_covered, admin_fee_percent, admin_fee_amount, net_amount, status, created_by, transaction_code,
                         transaction_type, sponsor_id, project_id, reference_number, receipt_path, unified_receipt_path, payment_period, purpose_note, purpose)
                        VALUES (?, ?, 'SDG', ?, ?, ?, ?, ?, ?, ?, ?, 'posted', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$ship ? $ship['id'] : null, $pl['amount'], $input['method'], $input['date'],
                         $input['receipt'] !== '' ? $input['receipt'] : null, $desc,
                         $isSpon ? 1.00 : 0.00, $feePct, $feeAmt, $net, $uid, $code, $txnType,
                         $input['sponsor_id'] > 0 ? $input['sponsor_id'] : null,
                         $input['project_id'] > 0 ? $input['project_id'] : null,
                         $input['reference'] !== '' ? $input['reference'] : null,
                         $pl['own'], $pl['uni'], $pl['month'], $pl['note'] !== '' ? $pl['note'] : null, $pl['purpose']]);
                    ak_post_transaction_journal((int)dbLastInsertId());
                    $count++;
                }
                try {
                    dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                               VALUES (?, 'DIRECT_POST', 'transactions', NULL, NULL, ?, ?, ?)",
                        [$uid, json_encode(['lines' => $count, 'type' => $input['type'], 'sponsor_id' => $input['sponsor_id']], JSON_UNESCAPED_UNICODE),
                         $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    $gms = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code IN ('general_manager','vice_general_manager') AND u.is_active = 1");
                    foreach ($gms as $g) {
                        dbExecute("INSERT INTO notifications (user_id, title, body, link) VALUES (?, ?, ?, ?)",
                            [$g['id'], 'تدفق نقدي وارد', 'تم ترحيل ' . $count . ' دفعة مباشرة بالخزينة.', 'modules/accounting/fm_dashboard.php']);
                    }
                } catch (Throwable $e) {}
                flash('success', "تم تسجيل $count دفعة وترحيل القيود المحاسبية بنجاح.");
                header('Location: ' . APP_URL . ($input['sponsor_id'] > 0 ? 'modules/sponsors/view.php?id=' . $input['sponsor_id'] : 'modules/transactions/index.php'));
                exit();
            }
        }
    }
}

/* ---- history ---- */
$history = [];
if ($currentSponsor) {
    $history = dbFetchAll("SELECT 'transaction' AS src, id, transaction_date AS dt, amount, transaction_type AS type, status, receipt_path, unified_receipt_path, payment_period, purpose_note, purpose, admin_fee_percent, created_by
                           FROM transactions WHERE sponsor_id = ?
                           UNION ALL
                           SELECT 'pending' AS src, id, created_at AS dt, amount, payment_type AS type, status, receipt_file_path AS receipt_path, unified_receipt_path, payment_period, purpose_note, payment_type AS purpose, 0.00 AS admin_fee_percent, supervisor_id AS created_by
                           FROM sponsor_payments WHERE sponsor_id = ?
                           ORDER BY dt DESC LIMIT 50", [$currentSponsor['id'], $currentSponsor['id']]);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
input[type=number] { -moz-appearance:textfield; appearance:textfield; }
.pay-line { background:#fbfcff; }
</style>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-money-bill-transfer me-2"></i>تسجيل دفعة</h2>
    <p>
        <?php if ($currentSponsor): ?>
            الكفيل: <strong><?php echo e($currentSponsor['full_name']); ?></strong> (<code><?php echo e($currentSponsor['sponsor_code']); ?></code>)
        <?php else: ?>
            كل دفعة مرحّلة تُنشئ قيداً مزدوجاً تلقائياً (صندوق/بنك ← إيراد)
        <?php endif; ?>
    </p>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?>
<div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
<?php endif; ?>

<!-- FORM -->
<div class="card fade-in mb-4">
    <div class="card-header text-white" style="background:#1b4d8f"><i class="fas fa-plus-circle me-2"></i>تفاصيل الدفعة</div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="sponsor_id" value="<?php echo $input['sponsor_id']; ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">نوع الدفعة *</label>
                    <select name="type" id="payType" class="form-select" required onchange="toggleFields()">
                        <option value="monthly_sponsorship" <?php echo $input['type']==='monthly_sponsorship'?'selected':''; ?>>تحصيل كفالة شهرية</option>
                        <option value="admin_fee" <?php echo $input['type']==='admin_fee'?'selected':''; ?>>رسوم إدارية</option>
                        <option value="general_donation" <?php echo $input['type']==='general_donation'?'selected':''; ?>>تبرع عام</option>
                        <option value="project_donation" <?php echo $input['type']==='project_donation'?'selected':''; ?>>تبرع لحملة/مشروع</option>
                        <option value="other" <?php echo $input['type']==='other'?'selected':''; ?>>مصدر آخر</option>
                    </select>
                </div>
                <div class="col-md-6" id="grpShip">
                    <label class="form-label">الكفالة (اليتيم) *</label>
                    <select name="sponsorship_id" class="form-select">
                        <option value="">— اختر —</option>
                        <?php foreach ($ships as $sh): ?>
                            <option value="<?php echo $sh['id']; ?>" data-amount="<?php echo (float)$sh['monthly_amount']; ?>" <?php echo $input['sponsorship_id']===(int)$sh['id']?'selected':''; ?>>
                                <?php echo e($sh['sponsor_name']); ?> — <?php echo e($sh['child_name']); ?> (<?php echo e($sh['sponsorship_code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12" id="grpLines">
                    <label class="form-label d-block">تفاصيل السداد (شهر + غرض + مبلغ + إيصال مستقل) *</label>
                    <div class="form-text mb-2 bg-light border rounded p-2">
                        <i class="fas fa-info-circle me-1"></i>كل سطر يحتفظ بإيصاله المستقل (إن وُجد) <strong>وأيضاً</strong> بالإيصال الموحّد للدفعة كاملة — لا يُستبدل أحدهما بالآخر.
                    </div>
                    <div id="linesWrap">
                        <?php foreach ($lines as $ln): ?>
                        <div class="pay-line border rounded p-2 mb-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">سداد عن شهر</label>
                                    <select name="pay_month[]" class="form-select form-select-sm">
                                        <?php foreach ($monthOptions as $m): ?>
                                            <option value="<?php echo $m; ?>" <?php echo $ln['month']===$m?'selected':''; ?>><?php echo $m; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">غرض الدفعة</label>
                                    <select name="pay_purpose[]" class="form-select form-select-sm line-purpose">
                                        <?php foreach ($purposeLabels as $pk => $pl2): ?>
                                            <option value="<?php echo $pk; ?>" <?php echo $ln['purpose']===$pk?'selected':''; ?>><?php echo $pl2; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted mb-1">المبلغ</label>
                                    <input type="number" step="0.01" min="0" name="pay_amount[]" class="form-control form-control-sm line-amount" value="<?php echo e($ln['amount']); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">إيصال هذا السطر</label>
                                    <input type="file" name="pay_receipt_file[]" class="form-control form-control-sm" accept="image/jpeg,image/png,application/pdf">
                                </div>
                                <div class="col-md-1 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)"><i class="fas fa-trash"></i></button>
                                </div>
                                <div class="col-md-6 line-note-wrap" <?php echo $ln['purpose']==='other'?'':'style="display:none"'; ?>>
                                    <label class="form-label small text-muted mb-1">توضيح الغرض</label>
                                    <input type="text" name="pay_note[]" class="form-control form-control-sm" value="<?php echo e($ln['note']); ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addLine()"><i class="fas fa-plus me-1"></i>إضافة سطر آخر (شهر/غرض)</button>
                    <div class="form-text mt-2">إجمالي التحصيل: <strong id="linesTotal" style="color:#1b4d8f">0</strong> ج.س</div>
                </div>
                <div class="col-md-4" id="grpSingleAmount" style="display:none;">
                    <label class="form-label">المبلغ *</label>
                    <input type="number" step="0.01" name="amount" class="form-control" value="<?php echo e($input['amount']); ?>">
                </div>
                <div class="col-md-4" id="grpProject" style="display:none;">
                    <label class="form-label">المشروع *</label>
                    <select name="project_id" class="form-select">
                        <option value="">— اختر —</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $input['project_id']===(int)$p['id']?'selected':''; ?>><?php echo e($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4" id="grpOther" style="display:none;">
                    <label class="form-label">تفاصيل المصدر الآخر *</label>
                    <input type="text" name="other_source_note" class="form-control" placeholder="حدد الجهة أو المصدر" value="<?php echo e($input['other_source_note']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">التاريخ *</label>
                    <input type="date" name="date" class="form-control" required value="<?php echo e($input['date']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">طريقة الدفع</label>
                    <select name="method" class="form-select">
                        <option value="cash" <?php echo $input['method']==='cash'?'selected':''; ?>>نقدي / كاش</option>
                        <option value="bank_transfer" <?php echo $input['method']==='bank_transfer'?'selected':''; ?>>تحويل بنكي</option>
                        <option value="mobile" <?php echo $input['method']==='mobile'?'selected':''; ?>>محفظة إلكترونية</option>
                        <option value="credit_card" <?php echo $input['method']==='credit_card'?'selected':''; ?>>بطاقة ائتمانية</option>
                        <option value="other" <?php echo $input['method']==='other'?'selected':''; ?>>أخرى</option>
                    </select>
                </div>
                <div class="col-md-4" id="grpFee">
                    <label class="form-label">نسبة الرسوم الإدارية (%)</label>
                    <input type="number" step="0.01" name="fee" class="form-control" value="<?php echo e($input['fee']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">رقم الإيصال / المرجع</label>
                    <input type="text" name="receipt" class="form-control" dir="ltr" value="<?php echo e($input['receipt']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">رقم مرجعي إضافي</label>
                    <input type="text" name="reference" class="form-control" dir="ltr" value="<?php echo e($input['reference']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">ملاحظات / البيان</label>
                    <textarea name="description" class="form-control" rows="2"><?php echo e($input['description']); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">إيصال موحّد للدفعة كاملة (غطاء)</label>
                    <input type="file" name="receipt_file" class="form-control" accept="image/jpeg,image/png,application/pdf">
                    <div class="form-text">يُحفظ مع كل سطر كإيصال غطاء — لا يلغي إيصالات الأسطر المستقلة</div>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i> حفظ الدفعة</button>
                <?php if ($currentSponsor): ?>
                    <a href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo $currentSponsor['id']; ?>" class="btn btn-secondary btn-lg ms-2">إلغاء</a>
                <?php else: ?>
                    <a href="<?php echo APP_URL; ?>modules/transactions/index.php" class="btn btn-secondary btn-lg ms-2">إلغاء</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- HISTORY -->
<?php if ($currentSponsor): ?>
<div class="card fade-in mb-4">
    <div class="card-header"><i class="fas fa-history me-2"></i>سجل الدفعات الشهرية لهذا الكفيل</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>الشهر</th>
                        <th>الغرض</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>الإيصالات</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">لا توجد دفعات سابقة.</td></tr>
                    <?php else: foreach ($history as $h):
                        $typeAr = ['monthly_sponsorship'=>'كفالة شهرية','sponsorship_payment'=>'كفالة شهرية','school_fees'=>'رسوم دراسية','medicine'=>'علاج وأدوية','gift'=>'هدية/عيدية','admin_fee'=>'رسوم إدارية','general_donation'=>'تبرع عام','project_donation'=>'تبرع مشروع','other'=>'أخرى'][$h['purpose'] ?? $h['type']] ?? $h['type'];
                        $kind = '';
                        if (in_array($h['type'], ['monthly_sponsorship','sponsorship_payment'], true) && $h['payment_period']) {
                            $pm2 = ak_period_months($h['payment_period']);
                            if ($pm2 !== null) {
                                if ($pm2 > $CUR_IDX) $kind = '<span class="badge bg-info text-dark">مقدم</span>';
                                elseif ($pm2 === $CUR_IDX) $kind = '<span class="badge bg-success">الشهر الحالي</span>';
                                else $kind = '<span class="badge bg-warning text-dark">متأخرات</span>';
                            }
                        }
                        $statusBadge = $h['status'] === 'posted' ? '<span class="badge bg-success">مرحّل</span>' :
                                       ($h['status'] === 'pending' ? '<span class="badge bg-warning text-dark">بانتظار المدير المالي</span>' :
                                       '<span class="badge bg-secondary">' . e($h['status']) . '</span>');
                        $ownUrl = ''; $uniUrl = '';
                        if ($h['receipt_path']) {
                            $ownUrl = ($h['src'] === 'transaction')
                                ? APP_URL . 'modules/transactions/receipt_file.php?id=' . (int)$h['id']
                                : APP_URL . 'modules/transactions/receipt_sp.php?id=' . (int)$h['id'];
                        }
                        if (!empty($h['unified_receipt_path'])) {
                            $uniUrl = ($h['src'] === 'transaction')
                                ? APP_URL . 'modules/transactions/receipt_file.php?id=' . (int)$h['id'] . '&kind=unified'
                                : APP_URL . 'modules/transactions/receipt_sp.php?id=' . (int)$h['id'] . '&kind=unified';
                        }
                    ?>
                        <tr>
                            <td>
                                <?php echo e($h['payment_period'] ?? '—'); ?>
                                <?php echo $kind; ?>
                            </td>
                            <td>
                                <small><?php echo e($typeAr); ?></small>
                                <?php if (!empty($h['purpose_note'])): ?><small class="text-muted d-block"><i class="fas fa-note-sticky me-1"></i><?php echo e($h['purpose_note']); ?></small><?php endif; ?>
                            </td>
                            <td><strong><?php echo number_format((float)$h['amount'], 0); ?></strong></td>
                            <td><?php echo $statusBadge; ?></td>
                            <td class="text-nowrap">
                                <?php if ($ownUrl): ?><a href="<?php echo $ownUrl; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="إيصال البند"><i class="fas fa-eye"></i></a><?php endif; ?>
                                <?php if ($uniUrl): ?><a href="<?php echo $uniUrl; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="الإيصال الموحّد"><i class="fas fa-layer-group"></i></a><?php endif; ?>
                            </td>
                            <td class="text-nowrap text-center">
                                <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#detailsModal"
                                    data-src="<?php echo $h['src']; ?>" data-id="<?php echo $h['id']; ?>"
                                    data-period="<?php echo e($h['payment_period'] ?? '—'); ?>"
                                    data-type="<?php echo e($typeAr); ?>" data-note="<?php echo e($h['purpose_note'] ?? ''); ?>"
                                    data-amount="<?php echo number_format((float)$h['amount'], 2); ?>"
                                    data-status="<?php echo $h['status']; ?>" data-fee="<?php echo e($h['admin_fee_percent'] ?? 0); ?>"
                                    title="تفاصيل"><i class="fas fa-circle-info"></i></button>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
                                    data-src="<?php echo $h['src']; ?>" data-id="<?php echo $h['id']; ?>"
                                    data-period="<?php echo e($h['payment_period'] ?? ''); ?>"
                                    data-purpose="<?php echo e($h['purpose'] ?? ''); ?>" data-note="<?php echo e($h['purpose_note'] ?? ''); ?>"
                                    data-amount="<?php echo (float)$h['amount']; ?>"
                                    title="تعديل"><i class="fas fa-pen"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:#1b4d8f; color:white">
        <h5 class="modal-title"><i class="fas fa-circle-info me-2"></i>تفاصيل الدفعة</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between"><span>الشهر:</span> <strong id="det-period"></strong></li>
          <li class="list-group-item d-flex justify-content-between"><span>الغرض:</span> <strong id="det-type"></strong></li>
          <li class="list-group-item d-flex justify-content-between"><span>توضيح:</span> <span id="det-note"></span></li>
          <li class="list-group-item d-flex justify-content-between"><span>المبلغ:</span> <strong id="det-amount" style="color:#1b4d8f"></strong></li>
          <li class="list-group-item d-flex justify-content-between"><span>نسبة الرسوم:</span> <span id="det-fee"></span></li>
          <li class="list-group-item d-flex justify-content-between"><span>الحالة:</span> <span id="det-status"></span></li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="edit_record" value="1">
      <input type="hidden" name="edit_src" id="edit-src">
      <input type="hidden" name="edit_id" id="edit-id">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title"><i class="fas fa-pen me-2"></i>تعديل بيانات الدفعة</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info small mb-3"><i class="fas fa-shield-halved me-1"></i>سيتم حفظ التعديل في سجل التدقيق. إذا كانت الدفعة مرحّلة، سيتم إبطال القيد القديم وإنشاء قيد جديد تلقائياً.</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">سداد عن شهر</label>
              <select name="edit_month" id="edit-month" class="form-select">
                <?php foreach ($monthOptions as $m): ?><option value="<?php echo $m; ?>"><?php echo $m; ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">غرض الدفعة</label>
              <select name="edit_purpose" id="edit-purpose" class="form-select">
                <?php foreach ($purposeLabels as $pk => $pl2): ?><option value="<?php echo $pk; ?>"><?php echo $pl2; ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">توضيح الغرض</label>
              <input type="text" name="edit_note" id="edit-note" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">المبلغ</label>
              <input type="number" step="0.01" name="edit_amount" id="edit-amount" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">استبدال إيصال البند (اختياري)</label>
              <input type="file" name="edit_receipt_file" class="form-control" accept="image/jpeg,image/png,application/pdf">
            </div>
            <div class="col-12">
              <label class="form-label">استبدال الإيصال الموحّد (اختياري)</label>
              <input type="file" name="edit_unified_file" class="form-control" accept="image/jpeg,image/png,application/pdf">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-warning" onclick="return confirm('حفظ التعديلات؟')">حفظ التعديلات</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
const MONTHS = <?php echo json_encode($monthOptions); ?>;
const CUR = <?php echo json_encode($CURRENT_MONTH); ?>;
const PURPOSES = <?php echo json_encode($purposeLabels, JSON_UNESCAPED_UNICODE); ?>;

function monthSelectHtml(selected) {
    let html = '<select name="pay_month[]" class="form-select form-select-sm">';
    MONTHS.forEach(m => { html += '<option value="' + m + '"' + (m === selected ? ' selected' : '') + '>' + m + '</option>'; });
    return html + '</select>';
}
function purposeSelectHtml(selected) {
    let html = '<select name="pay_purpose[]" class="form-select form-select-sm line-purpose">';
    for (const [v, l] of Object.entries(PURPOSES)) html += '<option value="' + v + '"' + (v === selected ? ' selected' : '') + '>' + l + '</option>';
    return html + '</select>';
}
function addLine() {
    const wrap = document.getElementById('linesWrap');
    const div = document.createElement('div');
    div.className = 'pay-line border rounded p-2 mb-2';
    div.innerHTML =
        '<div class="row g-2 align-items-end">' +
        '<div class="col-md-3"><label class="form-label small text-muted mb-1">سداد عن شهر</label>' + monthSelectHtml(CUR) + '</div>' +
        '<div class="col-md-3"><label class="form-label small text-muted mb-1">غرض الدفعة</label>' + purposeSelectHtml('monthly_sponsorship') + '</div>' +
        '<div class="col-md-2"><label class="form-label small text-muted mb-1">المبلغ</label><input type="number" step="0.01" min="0" name="pay_amount[]" class="form-control form-control-sm line-amount"></div>' +
        '<div class="col-md-3"><label class="form-label small text-muted mb-1">إيصال هذا السطر</label><input type="file" name="pay_receipt_file[]" class="form-control form-control-sm" accept="image/jpeg,image/png,application/pdf"></div>' +
        '<div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)"><i class="fas fa-trash"></i></button></div>' +
        '<div class="col-md-6 line-note-wrap" style="display:none"><label class="form-label small text-muted mb-1">توضيح الغرض</label><input type="text" name="pay_note[]" class="form-control form-control-sm"></div>' +
        '</div>';
    wrap.appendChild(div);
    recalcTotal();
}
function removeLine(btn) {
    const lines = document.querySelectorAll('.pay-line');
    if (lines.length > 1) btn.closest('.pay-line').remove();
    else {
        const l = lines[0];
        l.querySelectorAll('select')[0].value = CUR;
        l.querySelectorAll('select')[1].value = 'monthly_sponsorship';
        l.querySelector('.line-note-wrap').style.display = 'none';
        l.querySelector('.line-amount').value = '';
    }
    recalcTotal();
}
function recalcTotal() {
    let t = 0;
    document.querySelectorAll('.line-amount').forEach(i => { t += parseFloat(i.value) || 0; });
    document.getElementById('linesTotal').textContent = t.toLocaleString('en-US');
}
function toggleFields() {
    const t = document.getElementById('payType').value;
    const monthly = (t === 'monthly_sponsorship');
    document.getElementById('grpShip').style.display = monthly ? '' : 'none';
    document.getElementById('grpLines').style.display = monthly ? '' : 'none';
    document.getElementById('grpFee').style.display = monthly ? '' : 'none';
    document.getElementById('grpSingleAmount').style.display = monthly ? 'none' : '';
    document.getElementById('grpProject').style.display = (t === 'project_donation') ? '' : 'none';
    document.getElementById('grpOther').style.display = (t === 'other') ? '' : 'none';
}
document.addEventListener('input', e => { if (e.target.classList.contains('line-amount')) recalcTotal(); });
document.addEventListener('change', e => {
    if (e.target.classList.contains('line-purpose')) {
        e.target.closest('.pay-line').querySelector('.line-note-wrap').style.display = (e.target.value === 'other') ? '' : 'none';
    }
    if (e.target.name === 'sponsorship_id') {
        const amt = (e.target.selectedOptions[0] && e.target.selectedOptions[0].dataset.amount) || '';
        document.querySelectorAll('.line-amount').forEach(i => { if (!i.value && amt) i.value = amt; });
        recalcTotal();
    }
});

// Modals data binding
document.addEventListener('DOMContentLoaded', function() {
    var detModal = document.getElementById('detailsModal');
    if (detModal) {
        detModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            document.getElementById('det-period').textContent = btn.getAttribute('data-period');
            document.getElementById('det-type').textContent = btn.getAttribute('data-type');
            document.getElementById('det-note').textContent = btn.getAttribute('data-note') || '—';
            document.getElementById('det-amount').textContent = btn.getAttribute('data-amount') + ' ج.س';
            document.getElementById('det-fee').textContent = btn.getAttribute('data-fee') + ' %';
            document.getElementById('det-status').textContent = btn.getAttribute('data-status');
        });
    }
    var editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            document.getElementById('edit-src').value = btn.getAttribute('data-src');
            document.getElementById('edit-id').value = btn.getAttribute('data-id');
            document.getElementById('edit-month').value = btn.getAttribute('data-period');
            document.getElementById('edit-purpose').value = btn.getAttribute('data-purpose');
            document.getElementById('edit-note').value = btn.getAttribute('data-note');
            document.getElementById('edit-amount').value = btn.getAttribute('data-amount');
        });
    }
});

toggleFields();
recalcTotal();
</script>

=======
<?php
// modules/transactions/create.php - Unified Payment Entry & Journal Posting (v7: history below, details & edit modals)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'accountant', 'accountant_staff', 'financial_manager', 'supervisor', 'vice_general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$uid = Session::getUserId();
$pageTitle = 'تسجيل دفعة';
$active = 'transactions';
ak_ensure_tables(); ak_seed_accounts();

function ak_period_months(?string $p): ?int {
    if (!$p) return null;
    $d = DateTime::createFromFormat('!F/Y', $p);
    return $d ? ((int)$d->format('Y') * 12 + (int)$d->format('n')) : null;
}
$monthOptions = [];
for ($i = -24; $i <= 12; $i++) $monthOptions[] = date('F/Y', strtotime(date('Y-m-01') . " $i months"));
$CURRENT_MONTH = date('F/Y');
$CUR_IDX = (int)date('Y') * 12 + (int)date('n');

$purposeLabels = [
    'monthly_sponsorship' => 'كفالة شهرية',
    'school_fees' => 'رسوم دراسية',
    'medicine' => 'علاج وأدوية',
    'gift' => 'هدية/عيدية',
    'other' => 'أخرى',
];

$defaultFee = 5.0;
$errors = [];
$input = [
    'type' => 'monthly_sponsorship',
    'sponsorship_id' => (int)($_GET['sponsorship_id'] ?? $_POST['sponsorship_id'] ?? 0),
    'sponsor_id' => (int)($_GET['sponsor_id'] ?? $_POST['sponsor_id'] ?? 0),
    'project_id' => (int)($_POST['project_id'] ?? 0),
    'amount' => '',
    'date' => date('Y-m-d'),
    'method' => 'cash',
    'receipt' => '',
    'reference' => '',
    'other_source_note' => '',
    'description' => '',
    'fee' => (string)$defaultFee
];

$pre = $input['sponsorship_id'] ? dbFetchOne("SELECT id, monthly_amount, sponsor_id FROM sponsorships WHERE id = ?", [$input['sponsorship_id']]) : null;
if ($pre) {
    if ($input['amount'] === '') $input['amount'] = (string)((float)$pre['monthly_amount']);
    if ($input['sponsor_id'] <= 0) $input['sponsor_id'] = (int)$pre['sponsor_id'];
}

$mySponsorIds = [];
if ($role === 'supervisor') {
    $myLetterIds = array_map('intval', array_column(dbFetchAll("SELECT letter_id FROM supervisor_letters WHERE supervisor_id = ?", [$uid]), 'letter_id'));
    $sqlScope = "SELECT s.id FROM sponsors s WHERE s.supervisor_id = ?";
    $paramsScope = [$uid];
    if ($myLetterIds) {
        $ph = implode(',', array_fill(0, count($myLetterIds), '?'));
        $sqlScope .= " OR s.first_letter_id IN ($ph)";
        $paramsScope = array_merge($paramsScope, $myLetterIds);
    }
    $mySponsorIds = array_map('intval', array_column(dbFetchAll($sqlScope, $paramsScope), 'id'));
}

$projects = dbFetchAll("SELECT id, name FROM other_projects ORDER BY name");

$sSql = "SELECT sp.id, sp.sponsorship_code, sp.monthly_amount, s.full_name AS sponsor_name, fc.child_name
         FROM sponsorships sp
         JOIN sponsors s ON s.id = sp.sponsor_id
         JOIN family_children fc ON fc.id = sp.child_id
         WHERE sp.status = 'active'";
$sParams = [];
if ($input['sponsor_id'] > 0) {
    $sSql .= " AND s.id = ?";
    $sParams[] = $input['sponsor_id'];
} elseif ($role === 'supervisor') {
    if (!$mySponsorIds) $sSql .= " AND 0 = 1";
    else {
        $ph = implode(',', array_fill(0, count($mySponsorIds), '?'));
        $sSql .= " AND s.id IN ($ph)";
        $sParams = $mySponsorIds;
    }
}
$sSql .= " ORDER BY s.full_name LIMIT 500";
$ships = dbFetchAll($sSql, $sParams);

$currentSponsor = null;
if ($input['sponsor_id'] > 0) {
    $currentSponsor = dbFetchOne("SELECT id, full_name, sponsor_code FROM sponsors WHERE id = ?", [$input['sponsor_id']]);
}

/* ---- EDIT HANDLER ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_record']) && verify_csrf()) {
    $src = $_POST['edit_src'] ?? '';
    $rid = (int)$_POST['edit_id'];
    $newMonth = trim($_POST['edit_month'] ?? '');
    $newPurpose = trim($_POST['edit_purpose'] ?? '');
    $newNote = trim($_POST['edit_note'] ?? '');
    $newAmount = (float)str_replace(',', '', $_POST['edit_amount'] ?? 0);
    
    $canEdit = in_array($role, ['admin', 'financial_manager', 'accountant'], true);
    if ($src === 'pending' && $role === 'supervisor') {
        $chk = dbFetchOne("SELECT supervisor_id FROM sponsor_payments WHERE id = ?", [$rid]);
        if ($chk && (int)$chk['supervisor_id'] === $uid) $canEdit = true;
    }
    
    if (!$canEdit) $errors[] = 'لا تملك صلاحية التعديل.';
    elseif ($newAmount <= 0) $errors[] = 'المبلغ يجب أن يكون أكبر من صفر.';
    else {
        $lineReceiptPath = null;
        if (isset($_FILES['edit_receipt_file']) && $_FILES['edit_receipt_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['edit_receipt_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','pdf'], true) && $file['size'] <= 10*1024*1024) {
                $dir = dirname(__DIR__, 2) . '/storage/receipts';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $fn = 'TR-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . '/' . $fn)) $lineReceiptPath = 'storage/receipts/' . $fn;
            } else $errors[] = 'صيغة أو حجم إيصال البند غير صالح.';
        }
        $uniReceiptPath = null;
        if (isset($_FILES['edit_unified_file']) && $_FILES['edit_unified_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['edit_unified_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','pdf'], true) && $file['size'] <= 10*1024*1024) {
                $dir = dirname(__DIR__, 2) . '/storage/receipts';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $fn = 'TR-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . '/' . $fn)) $uniReceiptPath = 'storage/receipts/' . $fn;
            } else $errors[] = 'صيغة أو حجم الإيصال الموحّد غير صالح.';
        }
        
        if (!$errors) {
            if ($src === 'pending') {
                $old = dbFetchOne("SELECT * FROM sponsor_payments WHERE id = ?", [$rid]);
                if ($old) {
                    $upd = [
                        'payment_period' => $newMonth,
                        'payment_type' => $newPurpose,
                        'purpose_note' => $newNote !== '' ? $newNote : null,
                        'amount' => $newAmount,
                    ];
                    if ($lineReceiptPath) $upd['receipt_file_path'] = $lineReceiptPath;
                    if ($uniReceiptPath) $upd['unified_receipt_path'] = $uniReceiptPath;
                    
                    $sets = []; $params = [];
                    foreach ($upd as $k => $v) { $sets[] = "`$k` = ?"; $params[] = $v; }
                    $params[] = $rid;
                    dbExecute("UPDATE sponsor_payments SET " . implode(', ', $sets) . " WHERE id = ?", $params);
                    
                    try {
                        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                                   VALUES (?, 'EDIT_PENDING', 'sponsor_payments', ?, ?, ?, ?, ?)",
                            [$uid, $rid, json_encode($old, JSON_UNESCAPED_UNICODE), json_encode($upd, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    } catch (Throwable $e) {}
                    flash('success', 'تم تعديل الدفعة المعلقة بنجاح.');
                }
            } else {
                $old = dbFetchOne("SELECT * FROM transactions WHERE id = ?", [$rid]);
                if ($old) {
                    $isSpon = ($newPurpose === 'monthly_sponsorship');
                    $txnType = $isSpon ? 'sponsorship_payment' : 'general_donation';
                    if ($old['transaction_type'] === 'project_donation') $txnType = 'project_donation';
                    if ($old['transaction_type'] === 'other' && !$isSpon) $txnType = 'other';
                    
                    $feePct = $isSpon ? (float)$old['admin_fee_percent'] : 0.0;
                    $feeAmt = round($newAmount * $feePct / 100, 2);
                    $net = round($newAmount - $feeAmt, 2);
                    
                    $upd = [
                        'payment_period' => $newMonth,
                        'purpose' => $newPurpose,
                        'purpose_note' => $newNote !== '' ? $newNote : null,
                        'amount' => $newAmount,
                        'admin_fee_amount' => $feeAmt,
                        'net_amount' => $net,
                    ];
                    if ($lineReceiptPath) $upd['receipt_path'] = $lineReceiptPath;
                    if ($uniReceiptPath) $upd['unified_receipt_path'] = $uniReceiptPath;
                    
                    $sets = []; $params = [];
                    foreach ($upd as $k => $v) { $sets[] = "`$k` = ?"; $params[] = $v; }
                    $params[] = $rid;
                    dbExecute("UPDATE transactions SET " . implode(', ', $sets) . " WHERE id = ?", $params);
                    
                    if ((float)$old['amount'] !== $newAmount || ($old['purpose'] ?? '') !== $newPurpose) {
                        ak_void_journal_for_transaction($rid, 'تعديل بيانات الدفعة');
                        ak_post_transaction_journal($rid);
                    }
                    
                    try {
                        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                                   VALUES (?, 'EDIT_POSTED', 'transactions', ?, ?, ?, ?, ?)",
                            [$uid, $rid, json_encode($old, JSON_UNESCAPED_UNICODE), json_encode($upd, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                        $gms = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code IN ('general_manager','vice_general_manager') AND u.is_active = 1");
                        foreach ($gms as $g) {
                            dbExecute("INSERT INTO notifications (user_id, title, body, link) VALUES (?, ?, ?, ?)",
                                [$g['id'], 'تعديل دفعة مرحّلة', 'تم تعديل بيانات دفعة مرحّلة بواسطة ' . (Session::getUserName() ?? '') . '.', 'modules/transactions/index.php']);
                        }
                    } catch (Throwable $e) {}
                    flash('success', 'تم تعديل الدفعة المرحّلة وتحديث القيود المحاسبية بنجاح.');
                }
            }
            header('Location: ' . APP_URL . 'modules/transactions/create.php?sponsor_id=' . $input['sponsor_id']);
            exit();
        }
    }
}

/* ---- CREATE HANDLER ---- */
$lines = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_record'])) {
    $pm = $_POST['pay_month'] ?? [];
    $pp = $_POST['pay_purpose'] ?? [];
    $pn = $_POST['pay_note'] ?? [];
    $pa = $_POST['pay_amount'] ?? [];
    foreach ($pm as $i => $m) {
        $m = trim((string)$m);
        $pur = trim((string)($pp[$i] ?? ''));
        $note = trim((string)($pn[$i] ?? ''));
        $a = trim((string)($pa[$i] ?? ''));
        if ($m === '' && $a === '' && $pur === '' && $note === '') continue;
        $lines[] = ['idx' => $i, 'month' => $m, 'purpose' => $pur, 'note' => $note, 'amount' => $a];
    }
} else {
    $lines[] = ['idx' => 0, 'month' => $CURRENT_MONTH, 'purpose' => 'monthly_sponsorship', 'note' => '', 'amount' => $input['amount']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_record'])) {
    if (!verify_csrf()) $errors[] = 'انتهت صلاحية الجلسة.';
    else {
        $input['type'] = in_array($_POST['type'] ?? '', ['monthly_sponsorship','admin_fee','general_donation','project_donation','other'], true) ? $_POST['type'] : 'monthly_sponsorship';
        $input['sponsorship_id'] = (int)($_POST['sponsorship_id'] ?? 0);
        $input['sponsor_id'] = (int)($_POST['sponsor_id'] ?? 0);
        $input['project_id'] = (int)($_POST['project_id'] ?? 0);
        $input['amount'] = trim($_POST['amount'] ?? '');
        $input['date'] = trim($_POST['date'] ?? '') ?: date('Y-m-d');
        $input['method'] = in_array($_POST['method'] ?? '', ['cash','bank_transfer','credit_card','mobile','other'], true) ? $_POST['method'] : 'cash';
        $input['receipt'] = trim($_POST['receipt'] ?? '');
        $input['reference'] = trim($_POST['reference'] ?? '');
        $input['other_source_note'] = trim($_POST['other_source_note'] ?? '');
        $input['description'] = trim($_POST['description'] ?? '');
        $input['fee'] = (float)($_POST['fee'] ?? $defaultFee);

        $amount = (float)str_replace(',', '', $input['amount']);

        $ship = null;
        if ($input['sponsorship_id'] > 0) {
            $ship = dbFetchOne("SELECT id, sponsor_id, status FROM sponsorships WHERE id = ?", [$input['sponsorship_id']]);
            if ($ship) $input['sponsor_id'] = (int)$ship['sponsor_id'];
        }

        $unifiedPath = null;
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['receipt_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
                if ($file['size'] <= 10 * 1024 * 1024) {
                    $dir = dirname(__DIR__, 2) . '/storage/receipts';
                    if (!is_dir($dir)) @mkdir($dir, 0777, true);
                    $fileName = 'TR-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $fileName)) $unifiedPath = 'storage/receipts/' . $fileName;
                    else $errors[] = 'فشل حفظ ملف الإيصال على الخادم.';
                } else $errors[] = 'حجم ملف الإيصال يتجاوز 10 ميجابايت.';
            } else $errors[] = 'صيغة الإيصال يجب أن تكون JPG أو PNG أو PDF.';
        } elseif (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'حدث خطأ أثناء رفع الإيصال.';
        }

        $lineReceipts = [];
        if (isset($_FILES['pay_receipt_file']) && is_array($_FILES['pay_receipt_file']['name'] ?? null)) {
            $dir = dirname(__DIR__, 2) . '/storage/receipts';
            foreach (array_keys($_FILES['pay_receipt_file']['name']) as $i) {
                $lineReceipts[$i] = null;
                $err = $_FILES['pay_receipt_file']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                if ($err !== UPLOAD_ERR_OK) { $errors[] = 'خطأ في رفع إيصال السطر ' . ($i + 1) . '.'; continue; }
                $name = $_FILES['pay_receipt_file']['name'][$i];
                $tmp  = $_FILES['pay_receipt_file']['tmp_name'][$i];
                $size = $_FILES['pay_receipt_file']['size'][$i];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) { $errors[] = 'صيغة إيصال السطر ' . ($i + 1) . ' يجب أن تكون JPG أو PNG أو PDF.'; continue; }
                if ($size > 10 * 1024 * 1024) { $errors[] = 'حجم إيصال السطر ' . ($i + 1) . ' يتجاوز 10MB.'; continue; }
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $fn = 'TR-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (move_uploaded_file($tmp, $dir . '/' . $fn)) $lineReceipts[$i] = 'storage/receipts/' . $fn;
                else $errors[] = 'فشل حفظ إيصال السطر ' . ($i + 1) . '.';
            }
        }

        $validPurposes = array_keys($purposeLabels);
        if ($input['type'] === 'monthly_sponsorship') {
            if (!$ship) $errors[] = 'اختر الكفالة (اليتيم).';
            if ($ship && $ship['status'] !== 'active') $errors[] = 'الكفالة المحددة غير نشطة.';
            if (!$lines) $errors[] = 'أضف سطراً واحداً على الأقل (شهر + غرض + مبلغ).';
            $seen = [];
            foreach ($lines as $ln) {
                if ($ln['month'] === '') { $errors[] = 'اختر الشهر لكل سطر.'; continue; }
                if (!in_array($ln['month'], $monthOptions, true)) { $errors[] = 'شهر غير صالح: ' . $ln['month']; continue; }
                if (!in_array($ln['purpose'], $validPurposes, true)) { $errors[] = 'غرض غير صالح.'; continue; }
                if ($ln['purpose'] === 'other' && $ln['note'] === '') $errors[] = 'حدد توضيح الغرض للشهر: ' . $ln['month'];
                $key = $ln['month'] . '|' . $ln['purpose'];
                if (isset($seen[$key])) $errors[] = 'سطر مكرر (نفس الشهر والغرض): ' . $ln['month'] . ' — ' . ($purposeLabels[$ln['purpose']] ?? '');
                $seen[$key] = true;
                if ((float)str_replace(',', '', $ln['amount']) <= 0) $errors[] = 'المبلغ يجب أن يكون أكبر من صفر للشهر: ' . $ln['month'];
            }
        } else {
            if ($amount <= 0) $errors[] = 'المبلغ يجب أن يكون أكبر من صفر.';
            if ($input['type'] === 'project_donation' && $input['project_id'] <= 0) $errors[] = 'اختر المشروع.';
            if ($input['type'] === 'other' && $input['other_source_note'] === '') $errors[] = 'يجب تحديد مصدر الدفعة.';
        }
        if (!$errors && $role === 'supervisor' && $input['sponsor_id'] > 0 && !in_array($input['sponsor_id'], $mySponsorIds, true)) {
            $errors[] = 'هذا الكفيل خارج نطاق إشرافك.';
        }

        if (!$errors) {
            if ($role === 'supervisor') {
                $count = 0;
                if ($input['type'] === 'monthly_sponsorship') {
                    foreach ($lines as $ln) {
                        $own = $lineReceipts[$ln['idx']] ?? null;
                        dbExecute("INSERT INTO sponsor_payments
                            (sponsorship_id, supervisor_id, payment_type, sponsor_id, project_id, other_source_note, purpose_note, payment_period, amount, currency_code, receipt_file_path, unified_receipt_path, notes, status)
                            VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, 'SDG', ?, ?, ?, 'pending')",
                            [$ship['id'], $uid, $ln['purpose'], $input['sponsor_id'],
                             $ln['note'] !== '' ? $ln['note'] : null, $ln['month'], (float)str_replace(',', '', $ln['amount']),
                             $own, $unifiedPath, $input['description'] !== '' ? $input['description'] : null]);
                        $count++;
                    }
                } else {
                    dbExecute("INSERT INTO sponsor_payments
                        (sponsorship_id, supervisor_id, payment_type, sponsor_id, project_id, other_source_note, purpose_note, payment_period, amount, currency_code, receipt_file_path, unified_receipt_path, notes, status)
                        VALUES (NULL, ?, ?, ?, ?, ?, NULL, NULL, ?, 'SDG', ?, NULL, ?, 'pending')",
                        [$uid, $input['type'], $input['sponsor_id'] > 0 ? $input['sponsor_id'] : null,
                         $input['project_id'] > 0 ? $input['project_id'] : null,
                         $input['other_source_note'] !== '' ? $input['other_source_note'] : null,
                         $amount, $unifiedPath, $input['description'] !== '' ? $input['description'] : null]);
                    $count = 1;
                }
                flash('success', "تم استلام $count دفعة وإرسالها للمدير المالي للاعتماد والترحيل.");
                header('Location: ' . APP_URL . 'modules/sponsors/view.php?id=' . $input['sponsor_id']);
                exit();
            } else {
                $txnTypeMap = ['monthly_sponsorship' => 'sponsorship_payment', 'admin_fee' => 'other', 'general_donation' => 'general_donation', 'project_donation' => 'project_donation', 'other' => 'other'];
                $count = 0;
                if ($input['type'] === 'monthly_sponsorship') {
                    $postLines = array_map(fn($l) => [
                        'idx' => $l['idx'], 'month' => $l['month'], 'purpose' => $l['purpose'], 'note' => $l['note'],
                        'amount' => (float)str_replace(',', '', $l['amount']), 'own' => $lineReceipts[$l['idx']] ?? null, 'uni' => $unifiedPath
                    ], $lines);
                } else {
                    $postLines = [['idx' => 0, 'month' => null, 'purpose' => null, 'note' => $input['other_source_note'], 'amount' => $amount, 'own' => $unifiedPath, 'uni' => null]];
                }

                foreach ($postLines as $pl) {
                    $isSpon = ($pl['purpose'] === 'monthly_sponsorship');
                    $txnType = $pl['purpose'] === null ? $txnTypeMap[$input['type']] : ($isSpon ? 'sponsorship_payment' : 'general_donation');
                    $feePct = $isSpon ? (float)$input['fee'] : 0.0;
                    $feeAmt = round($pl['amount'] * $feePct / 100, 2);
                    $net = round($pl['amount'] - $feeAmt, 2);
                    $purLabel = $pl['purpose'] !== null ? ($purposeLabels[$pl['purpose']] ?? $pl['purpose']) : '';
                    $desc = trim(($input['description'] !== '' ? $input['description'] : 'دفعة')
                        . ($purLabel !== '' ? ' — ' . $purLabel : '')
                        . ($pl['month'] ? ' عن شهر ' . $pl['month'] : '')
                        . ($pl['note'] !== '' ? ' (' . $pl['note'] . ')' : ''));
                    $n = (int)(dbFetchOne("SELECT COUNT(*) c FROM transactions")['c'] ?? 0) + 1;
                    $code = 'TR-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
                    dbExecute("INSERT INTO transactions
                        (sponsorship_id, amount, currency_code, payment_method, transaction_date, receipt_number, description,
                         months_covered, admin_fee_percent, admin_fee_amount, net_amount, status, created_by, transaction_code,
                         transaction_type, sponsor_id, project_id, reference_number, receipt_path, unified_receipt_path, payment_period, purpose_note, purpose)
                        VALUES (?, ?, 'SDG', ?, ?, ?, ?, ?, ?, ?, ?, 'posted', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$ship ? $ship['id'] : null, $pl['amount'], $input['method'], $input['date'],
                         $input['receipt'] !== '' ? $input['receipt'] : null, $desc,
                         $isSpon ? 1.00 : 0.00, $feePct, $feeAmt, $net, $uid, $code, $txnType,
                         $input['sponsor_id'] > 0 ? $input['sponsor_id'] : null,
                         $input['project_id'] > 0 ? $input['project_id'] : null,
                         $input['reference'] !== '' ? $input['reference'] : null,
                         $pl['own'], $pl['uni'], $pl['month'], $pl['note'] !== '' ? $pl['note'] : null, $pl['purpose']]);
                    ak_post_transaction_journal((int)dbLastInsertId());
                    $count++;
                }
                try {
                    dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                               VALUES (?, 'DIRECT_POST', 'transactions', NULL, NULL, ?, ?, ?)",
                        [$uid, json_encode(['lines' => $count, 'type' => $input['type'], 'sponsor_id' => $input['sponsor_id']], JSON_UNESCAPED_UNICODE),
                         $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    $gms = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code IN ('general_manager','vice_general_manager') AND u.is_active = 1");
                    foreach ($gms as $g) {
                        dbExecute("INSERT INTO notifications (user_id, title, body, link) VALUES (?, ?, ?, ?)",
                            [$g['id'], 'تدفق نقدي وارد', 'تم ترحيل ' . $count . ' دفعة مباشرة بالخزينة.', 'modules/accounting/fm_dashboard.php']);
                    }
                } catch (Throwable $e) {}
                flash('success', "تم تسجيل $count دفعة وترحيل القيود المحاسبية بنجاح.");
                header('Location: ' . APP_URL . ($input['sponsor_id'] > 0 ? 'modules/sponsors/view.php?id=' . $input['sponsor_id'] : 'modules/transactions/index.php'));
                exit();
            }
        }
    }
}

/* ---- history ---- */
$history = [];
if ($currentSponsor) {
    $history = dbFetchAll("SELECT 'transaction' AS src, id, transaction_date AS dt, amount, transaction_type AS type, status, receipt_path, unified_receipt_path, payment_period, purpose_note, purpose, admin_fee_percent, created_by
                           FROM transactions WHERE sponsor_id = ?
                           UNION ALL
                           SELECT 'pending' AS src, id, created_at AS dt, amount, payment_type AS type, status, receipt_file_path AS receipt_path, unified_receipt_path, payment_period, purpose_note, payment_type AS purpose, 0.00 AS admin_fee_percent, supervisor_id AS created_by
                           FROM sponsor_payments WHERE sponsor_id = ?
                           ORDER BY dt DESC LIMIT 50", [$currentSponsor['id'], $currentSponsor['id']]);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
input[type=number] { -moz-appearance:textfield; appearance:textfield; }
.pay-line { background:#fbfcff; }
</style>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-money-bill-transfer me-2"></i>تسجيل دفعة</h2>
    <p>
        <?php if ($currentSponsor): ?>
            الكفيل: <strong><?php echo e($currentSponsor['full_name']); ?></strong> (<code><?php echo e($currentSponsor['sponsor_code']); ?></code>)
        <?php else: ?>
            كل دفعة مرحّلة تُنشئ قيداً مزدوجاً تلقائياً (صندوق/بنك ← إيراد)
        <?php endif; ?>
    </p>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?>
<div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
<?php endif; ?>

<!-- FORM -->
<div class="card fade-in mb-4">
    <div class="card-header text-white" style="background:#1b4d8f"><i class="fas fa-plus-circle me-2"></i>تفاصيل الدفعة</div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="sponsor_id" value="<?php echo $input['sponsor_id']; ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">نوع الدفعة *</label>
                    <select name="type" id="payType" class="form-select" required onchange="toggleFields()">
                        <option value="monthly_sponsorship" <?php echo $input['type']==='monthly_sponsorship'?'selected':''; ?>>تحصيل كفالة شهرية</option>
                        <option value="admin_fee" <?php echo $input['type']==='admin_fee'?'selected':''; ?>>رسوم إدارية</option>
                        <option value="general_donation" <?php echo $input['type']==='general_donation'?'selected':''; ?>>تبرع عام</option>
                        <option value="project_donation" <?php echo $input['type']==='project_donation'?'selected':''; ?>>تبرع لحملة/مشروع</option>
                        <option value="other" <?php echo $input['type']==='other'?'selected':''; ?>>مصدر آخر</option>
                    </select>
                </div>
                <div class="col-md-6" id="grpShip">
                    <label class="form-label">الكفالة (اليتيم) *</label>
                    <select name="sponsorship_id" class="form-select">
                        <option value="">— اختر —</option>
                        <?php foreach ($ships as $sh): ?>
                            <option value="<?php echo $sh['id']; ?>" data-amount="<?php echo (float)$sh['monthly_amount']; ?>" <?php echo $input['sponsorship_id']===(int)$sh['id']?'selected':''; ?>>
                                <?php echo e($sh['sponsor_name']); ?> — <?php echo e($sh['child_name']); ?> (<?php echo e($sh['sponsorship_code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12" id="grpLines">
                    <label class="form-label d-block">تفاصيل السداد (شهر + غرض + مبلغ + إيصال مستقل) *</label>
                    <div class="form-text mb-2 bg-light border rounded p-2">
                        <i class="fas fa-info-circle me-1"></i>كل سطر يحتفظ بإيصاله المستقل (إن وُجد) <strong>وأيضاً</strong> بالإيصال الموحّد للدفعة كاملة — لا يُستبدل أحدهما بالآخر.
                    </div>
                    <div id="linesWrap">
                        <?php foreach ($lines as $ln): ?>
                        <div class="pay-line border rounded p-2 mb-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">سداد عن شهر</label>
                                    <select name="pay_month[]" class="form-select form-select-sm">
                                        <?php foreach ($monthOptions as $m): ?>
                                            <option value="<?php echo $m; ?>" <?php echo $ln['month']===$m?'selected':''; ?>><?php echo $m; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">غرض الدفعة</label>
                                    <select name="pay_purpose[]" class="form-select form-select-sm line-purpose">
                                        <?php foreach ($purposeLabels as $pk => $pl2): ?>
                                            <option value="<?php echo $pk; ?>" <?php echo $ln['purpose']===$pk?'selected':''; ?>><?php echo $pl2; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted mb-1">المبلغ</label>
                                    <input type="number" step="0.01" min="0" name="pay_amount[]" class="form-control form-control-sm line-amount" value="<?php echo e($ln['amount']); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">إيصال هذا السطر</label>
                                    <input type="file" name="pay_receipt_file[]" class="form-control form-control-sm" accept="image/jpeg,image/png,application/pdf">
                                </div>
                                <div class="col-md-1 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)"><i class="fas fa-trash"></i></button>
                                </div>
                                <div class="col-md-6 line-note-wrap" <?php echo $ln['purpose']==='other'?'':'style="display:none"'; ?>>
                                    <label class="form-label small text-muted mb-1">توضيح الغرض</label>
                                    <input type="text" name="pay_note[]" class="form-control form-control-sm" value="<?php echo e($ln['note']); ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addLine()"><i class="fas fa-plus me-1"></i>إضافة سطر آخر (شهر/غرض)</button>
                    <div class="form-text mt-2">إجمالي التحصيل: <strong id="linesTotal" style="color:#1b4d8f">0</strong> ج.س</div>
                </div>
                <div class="col-md-4" id="grpSingleAmount" style="display:none;">
                    <label class="form-label">المبلغ *</label>
                    <input type="number" step="0.01" name="amount" class="form-control" value="<?php echo e($input['amount']); ?>">
                </div>
                <div class="col-md-4" id="grpProject" style="display:none;">
                    <label class="form-label">المشروع *</label>
                    <select name="project_id" class="form-select">
                        <option value="">— اختر —</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $input['project_id']===(int)$p['id']?'selected':''; ?>><?php echo e($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4" id="grpOther" style="display:none;">
                    <label class="form-label">تفاصيل المصدر الآخر *</label>
                    <input type="text" name="other_source_note" class="form-control" placeholder="حدد الجهة أو المصدر" value="<?php echo e($input['other_source_note']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">التاريخ *</label>
                    <input type="date" name="date" class="form-control" required value="<?php echo e($input['date']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">طريقة الدفع</label>
                    <select name="method" class="form-select">
                        <option value="cash" <?php echo $input['method']==='cash'?'selected':''; ?>>نقدي / كاش</option>
                        <option value="bank_transfer" <?php echo $input['method']==='bank_transfer'?'selected':''; ?>>تحويل بنكي</option>
                        <option value="mobile" <?php echo $input['method']==='mobile'?'selected':''; ?>>محفظة إلكترونية</option>
                        <option value="credit_card" <?php echo $input['method']==='credit_card'?'selected':''; ?>>بطاقة ائتمانية</option>
                        <option value="other" <?php echo $input['method']==='other'?'selected':''; ?>>أخرى</option>
                    </select>
                </div>
                <div class="col-md-4" id="grpFee">
                    <label class="form-label">نسبة الرسوم الإدارية (%)</label>
                    <input type="number" step="0.01" name="fee" class="form-control" value="<?php echo e($input['fee']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">رقم الإيصال / المرجع</label>
                    <input type="text" name="receipt" class="form-control" dir="ltr" value="<?php echo e($input['receipt']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">رقم مرجعي إضافي</label>
                    <input type="text" name="reference" class="form-control" dir="ltr" value="<?php echo e($input['reference']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">ملاحظات / البيان</label>
                    <textarea name="description" class="form-control" rows="2"><?php echo e($input['description']); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">إيصال موحّد للدفعة كاملة (غطاء)</label>
                    <input type="file" name="receipt_file" class="form-control" accept="image/jpeg,image/png,application/pdf">
                    <div class="form-text">يُحفظ مع كل سطر كإيصال غطاء — لا يلغي إيصالات الأسطر المستقلة</div>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i> حفظ الدفعة</button>
                <?php if ($currentSponsor): ?>
                    <a href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo $currentSponsor['id']; ?>" class="btn btn-secondary btn-lg ms-2">إلغاء</a>
                <?php else: ?>
                    <a href="<?php echo APP_URL; ?>modules/transactions/index.php" class="btn btn-secondary btn-lg ms-2">إلغاء</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- HISTORY -->
<?php if ($currentSponsor): ?>
<div class="card fade-in mb-4">
    <div class="card-header"><i class="fas fa-history me-2"></i>سجل الدفعات الشهرية لهذا الكفيل</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>الشهر</th>
                        <th>الغرض</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>الإيصالات</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">لا توجد دفعات سابقة.</td></tr>
                    <?php else: foreach ($history as $h):
                        $typeAr = ['monthly_sponsorship'=>'كفالة شهرية','sponsorship_payment'=>'كفالة شهرية','school_fees'=>'رسوم دراسية','medicine'=>'علاج وأدوية','gift'=>'هدية/عيدية','admin_fee'=>'رسوم إدارية','general_donation'=>'تبرع عام','project_donation'=>'تبرع مشروع','other'=>'أخرى'][$h['purpose'] ?? $h['type']] ?? $h['type'];
                        $kind = '';
                        if (in_array($h['type'], ['monthly_sponsorship','sponsorship_payment'], true) && $h['payment_period']) {
                            $pm2 = ak_period_months($h['payment_period']);
                            if ($pm2 !== null) {
                                if ($pm2 > $CUR_IDX) $kind = '<span class="badge bg-info text-dark">مقدم</span>';
                                elseif ($pm2 === $CUR_IDX) $kind = '<span class="badge bg-success">الشهر الحالي</span>';
                                else $kind = '<span class="badge bg-warning text-dark">متأخرات</span>';
                            }
                        }
                        $statusBadge = $h['status'] === 'posted' ? '<span class="badge bg-success">مرحّل</span>' :
                                       ($h['status'] === 'pending' ? '<span class="badge bg-warning text-dark">بانتظار المدير المالي</span>' :
                                       '<span class="badge bg-secondary">' . e($h['status']) . '</span>');
                        $ownUrl = ''; $uniUrl = '';
                        if ($h['receipt_path']) {
                            $ownUrl = ($h['src'] === 'transaction')
                                ? APP_URL . 'modules/transactions/receipt_file.php?id=' . (int)$h['id']
                                : APP_URL . 'modules/transactions/receipt_sp.php?id=' . (int)$h['id'];
                        }
                        if (!empty($h['unified_receipt_path'])) {
                            $uniUrl = ($h['src'] === 'transaction')
                                ? APP_URL . 'modules/transactions/receipt_file.php?id=' . (int)$h['id'] . '&kind=unified'
                                : APP_URL . 'modules/transactions/receipt_sp.php?id=' . (int)$h['id'] . '&kind=unified';
                        }
                    ?>
                        <tr>
                            <td>
                                <?php echo e($h['payment_period'] ?? '—'); ?>
                                <?php echo $kind; ?>
                            </td>
                            <td>
                                <small><?php echo e($typeAr); ?></small>
                                <?php if (!empty($h['purpose_note'])): ?><small class="text-muted d-block"><i class="fas fa-note-sticky me-1"></i><?php echo e($h['purpose_note']); ?></small><?php endif; ?>
                            </td>
                            <td><strong><?php echo number_format((float)$h['amount'], 0); ?></strong></td>
                            <td><?php echo $statusBadge; ?></td>
                            <td class="text-nowrap">
                                <?php if ($ownUrl): ?><a href="<?php echo $ownUrl; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="إيصال البند"><i class="fas fa-eye"></i></a><?php endif; ?>
                                <?php if ($uniUrl): ?><a href="<?php echo $uniUrl; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="الإيصال الموحّد"><i class="fas fa-layer-group"></i></a><?php endif; ?>
                            </td>
                            <td class="text-nowrap text-center">
                                <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#detailsModal"
                                    data-src="<?php echo $h['src']; ?>" data-id="<?php echo $h['id']; ?>"
                                    data-period="<?php echo e($h['payment_period'] ?? '—'); ?>"
                                    data-type="<?php echo e($typeAr); ?>" data-note="<?php echo e($h['purpose_note'] ?? ''); ?>"
                                    data-amount="<?php echo number_format((float)$h['amount'], 2); ?>"
                                    data-status="<?php echo $h['status']; ?>" data-fee="<?php echo e($h['admin_fee_percent'] ?? 0); ?>"
                                    title="تفاصيل"><i class="fas fa-circle-info"></i></button>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
                                    data-src="<?php echo $h['src']; ?>" data-id="<?php echo $h['id']; ?>"
                                    data-period="<?php echo e($h['payment_period'] ?? ''); ?>"
                                    data-purpose="<?php echo e($h['purpose'] ?? ''); ?>" data-note="<?php echo e($h['purpose_note'] ?? ''); ?>"
                                    data-amount="<?php echo (float)$h['amount']; ?>"
                                    title="تعديل"><i class="fas fa-pen"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:#1b4d8f; color:white">
        <h5 class="modal-title"><i class="fas fa-circle-info me-2"></i>تفاصيل الدفعة</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between"><span>الشهر:</span> <strong id="det-period"></strong></li>
          <li class="list-group-item d-flex justify-content-between"><span>الغرض:</span> <strong id="det-type"></strong></li>
          <li class="list-group-item d-flex justify-content-between"><span>توضيح:</span> <span id="det-note"></span></li>
          <li class="list-group-item d-flex justify-content-between"><span>المبلغ:</span> <strong id="det-amount" style="color:#1b4d8f"></strong></li>
          <li class="list-group-item d-flex justify-content-between"><span>نسبة الرسوم:</span> <span id="det-fee"></span></li>
          <li class="list-group-item d-flex justify-content-between"><span>الحالة:</span> <span id="det-status"></span></li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="edit_record" value="1">
      <input type="hidden" name="edit_src" id="edit-src">
      <input type="hidden" name="edit_id" id="edit-id">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title"><i class="fas fa-pen me-2"></i>تعديل بيانات الدفعة</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info small mb-3"><i class="fas fa-shield-halved me-1"></i>سيتم حفظ التعديل في سجل التدقيق. إذا كانت الدفعة مرحّلة، سيتم إبطال القيد القديم وإنشاء قيد جديد تلقائياً.</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">سداد عن شهر</label>
              <select name="edit_month" id="edit-month" class="form-select">
                <?php foreach ($monthOptions as $m): ?><option value="<?php echo $m; ?>"><?php echo $m; ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">غرض الدفعة</label>
              <select name="edit_purpose" id="edit-purpose" class="form-select">
                <?php foreach ($purposeLabels as $pk => $pl2): ?><option value="<?php echo $pk; ?>"><?php echo $pl2; ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">توضيح الغرض</label>
              <input type="text" name="edit_note" id="edit-note" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">المبلغ</label>
              <input type="number" step="0.01" name="edit_amount" id="edit-amount" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">استبدال إيصال البند (اختياري)</label>
              <input type="file" name="edit_receipt_file" class="form-control" accept="image/jpeg,image/png,application/pdf">
            </div>
            <div class="col-12">
              <label class="form-label">استبدال الإيصال الموحّد (اختياري)</label>
              <input type="file" name="edit_unified_file" class="form-control" accept="image/jpeg,image/png,application/pdf">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="submit" class="btn btn-warning" onclick="return confirm('حفظ التعديلات؟')">حفظ التعديلات</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
const MONTHS = <?php echo json_encode($monthOptions); ?>;
const CUR = <?php echo json_encode($CURRENT_MONTH); ?>;
const PURPOSES = <?php echo json_encode($purposeLabels, JSON_UNESCAPED_UNICODE); ?>;

function monthSelectHtml(selected) {
    let html = '<select name="pay_month[]" class="form-select form-select-sm">';
    MONTHS.forEach(m => { html += '<option value="' + m + '"' + (m === selected ? ' selected' : '') + '>' + m + '</option>'; });
    return html + '</select>';
}
function purposeSelectHtml(selected) {
    let html = '<select name="pay_purpose[]" class="form-select form-select-sm line-purpose">';
    for (const [v, l] of Object.entries(PURPOSES)) html += '<option value="' + v + '"' + (v === selected ? ' selected' : '') + '>' + l + '</option>';
    return html + '</select>';
}
function addLine() {
    const wrap = document.getElementById('linesWrap');
    const div = document.createElement('div');
    div.className = 'pay-line border rounded p-2 mb-2';
    div.innerHTML =
        '<div class="row g-2 align-items-end">' +
        '<div class="col-md-3"><label class="form-label small text-muted mb-1">سداد عن شهر</label>' + monthSelectHtml(CUR) + '</div>' +
        '<div class="col-md-3"><label class="form-label small text-muted mb-1">غرض الدفعة</label>' + purposeSelectHtml('monthly_sponsorship') + '</div>' +
        '<div class="col-md-2"><label class="form-label small text-muted mb-1">المبلغ</label><input type="number" step="0.01" min="0" name="pay_amount[]" class="form-control form-control-sm line-amount"></div>' +
        '<div class="col-md-3"><label class="form-label small text-muted mb-1">إيصال هذا السطر</label><input type="file" name="pay_receipt_file[]" class="form-control form-control-sm" accept="image/jpeg,image/png,application/pdf"></div>' +
        '<div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)"><i class="fas fa-trash"></i></button></div>' +
        '<div class="col-md-6 line-note-wrap" style="display:none"><label class="form-label small text-muted mb-1">توضيح الغرض</label><input type="text" name="pay_note[]" class="form-control form-control-sm"></div>' +
        '</div>';
    wrap.appendChild(div);
    recalcTotal();
}
function removeLine(btn) {
    const lines = document.querySelectorAll('.pay-line');
    if (lines.length > 1) btn.closest('.pay-line').remove();
    else {
        const l = lines[0];
        l.querySelectorAll('select')[0].value = CUR;
        l.querySelectorAll('select')[1].value = 'monthly_sponsorship';
        l.querySelector('.line-note-wrap').style.display = 'none';
        l.querySelector('.line-amount').value = '';
    }
    recalcTotal();
}
function recalcTotal() {
    let t = 0;
    document.querySelectorAll('.line-amount').forEach(i => { t += parseFloat(i.value) || 0; });
    document.getElementById('linesTotal').textContent = t.toLocaleString('en-US');
}
function toggleFields() {
    const t = document.getElementById('payType').value;
    const monthly = (t === 'monthly_sponsorship');
    document.getElementById('grpShip').style.display = monthly ? '' : 'none';
    document.getElementById('grpLines').style.display = monthly ? '' : 'none';
    document.getElementById('grpFee').style.display = monthly ? '' : 'none';
    document.getElementById('grpSingleAmount').style.display = monthly ? 'none' : '';
    document.getElementById('grpProject').style.display = (t === 'project_donation') ? '' : 'none';
    document.getElementById('grpOther').style.display = (t === 'other') ? '' : 'none';
}
document.addEventListener('input', e => { if (e.target.classList.contains('line-amount')) recalcTotal(); });
document.addEventListener('change', e => {
    if (e.target.classList.contains('line-purpose')) {
        e.target.closest('.pay-line').querySelector('.line-note-wrap').style.display = (e.target.value === 'other') ? '' : 'none';
    }
    if (e.target.name === 'sponsorship_id') {
        const amt = (e.target.selectedOptions[0] && e.target.selectedOptions[0].dataset.amount) || '';
        document.querySelectorAll('.line-amount').forEach(i => { if (!i.value && amt) i.value = amt; });
        recalcTotal();
    }
});

// Modals data binding
document.addEventListener('DOMContentLoaded', function() {
    var detModal = document.getElementById('detailsModal');
    if (detModal) {
        detModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            document.getElementById('det-period').textContent = btn.getAttribute('data-period');
            document.getElementById('det-type').textContent = btn.getAttribute('data-type');
            document.getElementById('det-note').textContent = btn.getAttribute('data-note') || '—';
            document.getElementById('det-amount').textContent = btn.getAttribute('data-amount') + ' ج.س';
            document.getElementById('det-fee').textContent = btn.getAttribute('data-fee') + ' %';
            document.getElementById('det-status').textContent = btn.getAttribute('data-status');
        });
    }
    var editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            document.getElementById('edit-src').value = btn.getAttribute('data-src');
            document.getElementById('edit-id').value = btn.getAttribute('data-id');
            document.getElementById('edit-month').value = btn.getAttribute('data-period');
            document.getElementById('edit-purpose').value = btn.getAttribute('data-purpose');
            document.getElementById('edit-note').value = btn.getAttribute('data-note');
            document.getElementById('edit-amount').value = btn.getAttribute('data-amount');
        });
    }
});

toggleFields();
recalcTotal();
</script>

>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>