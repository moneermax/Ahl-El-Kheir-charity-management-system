<?php
// modules/accounting/lib_outflows.php — Package 6.5 v7 (Flexible Batch: live item verification + returns + auto-close)
// Requires: config/database.php, config/functions.php, modules/accounting/lib.php
// NEVER touch modules/accounting/lib.php (inflow engine).

/* - schema introspection helpers - */
if (!function_exists('ak_out_columns')) {
function ak_out_columns(string $table): array {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    try {
        $rows = dbFetchAll("SHOW COLUMNS FROM `$table`");
        $cols = [];
        foreach ($rows as $r) if (!empty($r['Field'])) $cols[$r['Field']] = strtolower((string)($r['Type'] ?? ''));
        $cache[$table] = $cols;
    } catch (Throwable $ex) { $cache[$table] = []; }
    return $cache[$table];
}}

if (!function_exists('ak_out_add_col')) {
function ak_out_add_col(string $table, string $col, string $def): void {
    $cols = ak_out_columns($table);
    if (!isset($cols[$col])) {
        try { dbExecute("ALTER TABLE `$table` ADD COLUMN `$col` $def"); } catch (Throwable $ex) {}
    }
}}

if (!function_exists('ak_out_enum_values')) {
function ak_out_enum_values(string $table, string $col): array {
    $type = ak_out_columns($table)[$col] ?? '';
    if (preg_match('/enum\((.+)\)/i', $type, $m)) {
        preg_match_all("/'([^']+)'/", $m[1], $vals);
        return $vals[1] ?? [];
    }
    return [];
}}

if (!function_exists('ak_out_pick_enum')) {
function ak_out_pick_enum(string $table, string $col, array $preferred, string $fallback): string {
    $vals = ak_out_enum_values($table, $col);
    if (!$vals) return $fallback;
    foreach ($preferred as $p) if (in_array($p, $vals, true)) return $p;
    return in_array($fallback, $vals, true) ? $fallback : ($vals[0] ?? $fallback);
}}

/* - schema sync (idempotent) - */
if (!function_exists('ak_out_ensure_schema')) {
function ak_out_ensure_schema(): void {
    try {
        dbExecute("CREATE TABLE IF NOT EXISTS `monthly_disbursements` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`month` VARCHAR(7) NOT NULL,`nanny_id` INT UNSIGNED NOT NULL,`total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,`status` ENUM('draft','pending','pending_approval','approved','returned','transferred','received','cancelled','voided') NOT NULL DEFAULT 'draft',`transaction_id` INT UNSIGNED NULL,`expense_account_code` VARCHAR(20) NOT NULL DEFAULT '5100',`created_by` INT UNSIGNED NULL,`submitted_by` INT UNSIGNED NULL,`submitted_at` DATETIME NULL,`reviewed_by_user_id` INT UNSIGNED NULL,`reviewed_at` DATETIME NULL,`return_note` TEXT NULL,`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,`transferred_at` DATETIME NULL,`received_at` DATETIME NULL,`receipt_file_path` VARCHAR(255) NULL,`transfer_receipt_by_user_id` INT UNSIGNED NULL,`transfer_receipt_at` DATETIME NULL,`voided_by_user_id` INT UNSIGNED NULL,`voided_at` DATETIME NULL,`void_reason` TEXT NULL,`reversal_journal_id` INT UNSIGNED NULL,CONSTRAINT `fk_md_nanny` FOREIGN KEY (`nanny_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $ex) {}
    
    $st = ak_out_columns('monthly_disbursements')['status'] ?? '';
    if ($st !== '' && stripos($st, 'voided') === false) {
        try { dbExecute("ALTER TABLE `monthly_disbursements` MODIFY COLUMN `status` ENUM('draft','pending','pending_approval','approved','returned','transferred','received','cancelled','voided') NOT NULL DEFAULT 'draft'"); } catch (Throwable $ex) {}
    }
    
    foreach (['expense_account_code' => "VARCHAR(20) NOT NULL DEFAULT '5100'", 'transaction_id' => 'INT UNSIGNED NULL',
        'submitted_by' => 'INT UNSIGNED NULL', 'submitted_at' => 'DATETIME NULL',
        'reviewed_by_user_id' => 'INT UNSIGNED NULL', 'reviewed_at' => 'DATETIME NULL', 'return_note' => 'TEXT NULL',
        'transferred_at' => 'DATETIME NULL', 'received_at' => 'DATETIME NULL', 'receipt_file_path' => 'VARCHAR(255) NULL',
        'transfer_receipt_by_user_id' => 'INT UNSIGNED NULL', 'transfer_receipt_at' => 'DATETIME NULL',
        'voided_by_user_id' => 'INT UNSIGNED NULL', 'voided_at' => 'DATETIME NULL', 'void_reason' => 'TEXT NULL',
        'reversal_journal_id' => 'INT UNSIGNED NULL',
    ] as $c => $d) ak_out_add_col('monthly_disbursements', $c, $d);
    
    try {
        dbExecute("CREATE TABLE IF NOT EXISTS `disbursement_items` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,`disbursement_id` INT UNSIGNED NOT NULL,`family_id` INT UNSIGNED NOT NULL,`amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,`status` ENUM('pending','paid','return_requested','returned') NOT NULL DEFAULT 'pending',`receipt_file_path` VARCHAR(255) NULL,`child_id` INT UNSIGNED NULL,`description` VARCHAR(255) NULL,`is_orphan_verified` TINYINT(1) NOT NULL DEFAULT 0,`is_mother_contact_verified` TINYINT(1) NOT NULL DEFAULT 0,`is_bank_verified` TINYINT(1) NOT NULL DEFAULT 0,`verification_notes` TEXT NULL,`verified_by_user_id` INT UNSIGNED NULL,`verified_at` DATETIME NULL,`return_reason` TEXT NULL,`return_requested_at` DATETIME NULL,`return_requested_by_user_id` INT UNSIGNED NULL,`returned_at` DATETIME NULL,`returned_by_user_id` INT UNSIGNED NULL,`reversal_journal_id` INT UNSIGNED NULL,CONSTRAINT `fk_di_disbursement` FOREIGN KEY (`disbursement_id`) REFERENCES `monthly_disbursements`(`id`) ON DELETE CASCADE,CONSTRAINT `fk_di_family` FOREIGN KEY (`family_id`) REFERENCES `families`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $ex) {}
    
    $itSt = ak_out_columns('disbursement_items')['status'] ?? '';
    if ($itSt !== '' && stripos($itSt, 'return_requested') === false) {
        try { dbExecute("ALTER TABLE `disbursement_items` MODIFY COLUMN `status` ENUM('pending','paid','return_requested','returned') NOT NULL DEFAULT 'pending'"); } catch (Throwable $ex) {}
    }
    
    foreach (['receipt_file_path' => 'VARCHAR(255) NULL', 'child_id' => 'INT UNSIGNED NULL', 'description' => 'VARCHAR(255) NULL',
        'is_orphan_verified' => 'TINYINT(1) NOT NULL DEFAULT 0', 'is_mother_contact_verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'is_bank_verified' => 'TINYINT(1) NOT NULL DEFAULT 0', 'verification_notes' => 'TEXT NULL',
        'verified_by_user_id' => 'INT UNSIGNED NULL', 'verified_at' => 'DATETIME NULL',
        'return_reason' => 'TEXT NULL', 'return_requested_at' => 'DATETIME NULL',
        'return_requested_by_user_id' => 'INT UNSIGNED NULL', 'returned_at' => 'DATETIME NULL',
        'returned_by_user_id' => 'INT UNSIGNED NULL', 'reversal_journal_id' => 'INT UNSIGNED NULL',
    ] as $c => $d) ak_out_add_col('disbursement_items', $c, $d);
    
    try {
        dbExecute("CREATE TABLE IF NOT EXISTS `nanny_family_verifications` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `family_id` INT UNSIGNED NOT NULL,`month` VARCHAR(7) NOT NULL,`nanny_id` INT UNSIGNED NOT NULL,
        `is_orphan_verified` TINYINT(1) NOT NULL DEFAULT 0,`is_mother_contact_verified` TINYINT(1) NOT NULL DEFAULT 0,
        `is_bank_verified` TINYINT(1) NOT NULL DEFAULT 0,`verification_notes` TEXT NULL,
        `verified_by_user_id` INT UNSIGNED NULL,`verified_at` DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_nfv_family_month` (`family_id`, `month`),KEY `idx_nfv_nanny_month` (`nanny_id`, `month`),
        CONSTRAINT `fk_nfv_family` FOREIGN KEY (`family_id`) REFERENCES `families`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_nfv_nanny` FOREIGN KEY (`nanny_id`) REFERENCES `users`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $ex) {}
}}

/* - user & auth - */
if (!function_exists('ak_out_user')) {
function ak_out_user(): array {
    static $u = null;
    if ($u !== null) return $u;
    if (!function_exists('Session') && !class_exists('Session')) {
        require_once dirname(__DIR__, 2) . '/config/session.php';
    }
    if (!Session::isLoggedIn()) return ['id' => 0, 'role' => 'guest', 'name' => 'Guest'];
    $u = ['id' => (int)Session::getUserId(), 'role' => (string)Session::getUserRole(), 'name' => (string)Session::getUserName()];
    return $u;
}}

if (!function_exists('ak_out_can_manage')) {
function ak_out_can_manage(): bool { return in_array(ak_out_user()['role'], ['admin', 'accountant', 'accountant_staff'], true); }}

if (!function_exists('ak_out_can_approve')) {
function ak_out_can_approve(): bool { return in_array(ak_out_user()['role'], ['financial_manager', 'admin', 'general_manager', 'vice_general_manager'], true); }}

if (!function_exists('ak_out_scoped_nanny_ids')) {
function ak_out_scoped_nanny_ids(): ?array {
    $u = ak_out_user();
    if ($u['role'] !== 'accountant_staff') return null;
    return array_map('intval', array_column(dbFetchAll("SELECT nanny_id FROM accountant_nanny_assignments WHERE accountant_id = ?", [$u['id']]), 'nanny_id'));
}}

/* - audit & alerts - */
if (!function_exists('ak_out_audit')) {
function ak_out_audit(int $userId, string $action, int $entityId, $old, $new): void {
    try {
        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, 'disbursements', ?, ?, ?, ?, ?)",
            [$userId, $action, $entityId, $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE), $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (Throwable $ex) {}
}}

if (!function_exists('ak_out_notify_treasury')) {
function ak_out_notify_treasury(string $direction, float $amount, string $desc, ?int $txnId, int $actorId): void {
    ak_out_audit($actorId, 'TREASURY_MOVEMENT', $txnId ?? 0, null, ['direction' => $direction, 'amount' => $amount, 'description' => $desc]);
    try {
        // Optional: push to dashboard/notification queue
    } catch (Throwable $ex) {}
}}

/* - generic row fetch - */
if (!function_exists('ak_out_row')) {
function ak_out_row(string $table, int $id): ?array {
    if (!ak_out_columns($table)) return null;
    try { return dbFetchOne("SELECT * FROM `$table` WHERE id = ?", [$id]); } catch (Throwable $ex) { return null; }
}}

/* - generic safe write helpers - */
if (!function_exists('ak_out_insert')) {
function ak_out_insert(string $table, array $data): int {
    $cols = ak_out_columns($table);
    if (!$cols) throw new RuntimeException('Table unavailable: ' . $table);
    $data = array_intersect_key($data, array_flip(array_keys($cols)));
    if (!$data) throw new RuntimeException('No valid columns for INSERT into ' . $table);
    $columns = array_keys($data);
    $sql = "INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', array_fill(0, count($data), '?')) . ")";
    dbExecute($sql, array_values($data));
    return (int)dbLastInsertId();
}}

if (!function_exists('ak_out_update')) {
function ak_out_update(string $table, array $data, array $where): int {
    $cols = ak_out_columns($table);
    if (!$cols) throw new RuntimeException('Table unavailable: ' . $table);
    $data = array_intersect_key($data, array_flip(array_keys($cols)));
    if (!$data) return 0;
    $set = []; $vals = [];
    foreach ($data as $k => $v) { $set[] = "`$k` = ?"; $vals[] = $v; }
    $whereClause = [];
    foreach ($where as $k => $v) { $whereClause[] = "`$k` = ?"; $vals[] = $v; }
    $sql = "UPDATE `$table` SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $whereClause);
    return dbExecute($sql, $vals);
}}

/* - accounting helpers - */
if (!function_exists('ak_out_dr_cr')) {
function ak_out_dr_cr(): array {
    $cols = ak_out_columns('journal_lines');
    $dr = in_array('debit_amount', $cols, true) ? 'debit_amount' : 'debit';
    $cr = in_array('credit_amount', $cols, true) ? 'credit_amount' : 'credit';
    return [$dr, $cr];
}}

if (!function_exists('ak_out_post_journal')) {
function ak_out_post_journal(int $txnId, int $disbId, string $date, float $amount, string $expCode, string $cashCode, string $desc, int $userId): int {
    if ($amount <= 0) throw new RuntimeException('مبلغ غير صالح.');
    $expAcc = function_exists('ak_account_id') ? ak_account_id($expCode) : 0;
    $cashAcc = function_exists('ak_account_id') ? ak_account_id($cashCode) : 0;
    if ($expAcc <= 0 || $cashAcc <= 0) throw new RuntimeException('حسابات دفتر الأستاذ غير موجودة.');
    [$dr, $cr] = ak_out_dr_cr();
    $entryId = ak_out_insert('journal_entries', ['entry_code' => 'JE-OUT-' . str_pad((string)$disbId, 6, '0', STR_PAD_LEFT), 'entry_date' => $date, 'description' => $desc, 'reference_type' => 'disbursement', 'reference_id' => $disbId, 'status' => 'posted', 'created_by' => $userId, 'created_at' => date('Y-m-d H:i:s')]);
    if ($entryId <= 0) throw new RuntimeException('تعذر إنشاء القيد.');
    ak_out_insert('journal_lines', ['entry_id' => $entryId, 'account_id' => $expAcc, $dr => $amount, $cr => 0.00, 'description' => 'مصروف #' . $disbId]);
    ak_out_insert('journal_lines', ['entry_id' => $entryId, 'account_id' => $cashAcc, $dr => 0.00, $cr => $amount, 'description' => 'نقد/بنك #' . $disbId]);
    return $entryId;
}}

if (!function_exists('ak_out_reversal_journal')) {
function ak_out_reversal_journal(int $origTxnId, int $disbId, string $date, float $amount, string $expCode, string $cashCode, string $desc, int $userId): int {
    if ($amount <= 0) throw new RuntimeException('مبلغ غير صالح للإبطال.');
    $expAcc = function_exists('ak_account_id') ? ak_account_id($expCode) : 0;
    $cashAcc = function_exists('ak_account_id') ? ak_account_id($cashCode) : 0;
    if ($expAcc <= 0 || $cashAcc <= 0) throw new RuntimeException('حسابات دفتر الأستاذ غير موجودة.');
    [$dr, $cr] = ak_out_dr_cr();
    $entryId = ak_out_insert('journal_entries', ['entry_code' => 'JE-REV-' . str_pad((string)$disbId, 6, '0', STR_PAD_LEFT), 'entry_date' => $date, 'description' => $desc, 'reference_type' => 'disbursement_void', 'reference_id' => $disbId, 'status' => 'posted', 'created_by' => $userId, 'created_at' => date('Y-m-d H:i:s')]);
    if ($entryId <= 0) throw new RuntimeException('تعذر إنشاء القيد العكسي.');
    ak_out_insert('journal_lines', ['entry_id' => $entryId, 'account_id' => $expAcc, $dr => 0.00, $cr => $amount, 'description' => 'عكس مصروف #' . $disbId]);
    ak_out_insert('journal_lines', ['entry_id' => $entryId, 'account_id' => $cashAcc, $dr => $amount, $cr => 0.00, 'description' => 'عكس نقد/بنك #' . $disbId]);
    return $entryId;
}}

if (!function_exists('ak_out_item_reversal_journal')) {
function ak_out_item_reversal_journal(int $itemId, int $disbId, float $amount, string $expCode, int $userId): int {
    if ($amount <= 0) throw new RuntimeException('مبلغ غير صالح.');
    $expAcc = function_exists('ak_account_id') ? ak_account_id($expCode) : 0;
    $cashAcc = function_exists('ak_account_id') ? ak_account_id('1100') : 0;
    if ($expAcc <= 0 || $cashAcc <= 0) throw new RuntimeException('حسابات دفتر الأستاذ غير موجودة.');
    [$dr, $cr] = ak_out_dr_cr();
    $entryId = ak_out_insert('journal_entries', ['entry_code' => 'JE-RET-' . str_pad((string)$itemId, 6, '0', STR_PAD_LEFT), 'entry_date' => date('Y-m-d'), 'description' => 'إرجاع نقد أسرة — بند #' . $itemId . ' دفعة #' . $disbId, 'reference_type' => 'item_return', 'reference_id' => $itemId, 'status' => 'posted', 'created_by' => $userId, 'created_at' => date('Y-m-d H:i:s')]);
    if ($entryId <= 0) throw new RuntimeException('تعذر إنشاء قيد الإرجاع.');
    ak_out_insert('journal_lines', ['entry_id' => $entryId, 'account_id' => $expAcc, $dr => 0.00, $cr => $amount, 'description' => 'عكس مصروف #' . $disbId]);
    ak_out_insert('journal_lines', ['entry_id' => $entryId, 'account_id' => $cashAcc, $dr => $amount, $cr => 0.00, 'description' => 'استرداد نقد #' . $disbId]);
    return $entryId;
}}

/* - approval & void - */
if (!function_exists('ak_out_approve')) {
function ak_out_approve(int $id, int $userId): void {
    $old = ak_out_row('monthly_disbursements', $id);
    if (!$old) throw new RuntimeException('الدفعة غير موجودة.');
    if (!in_array($old['status'], ['pending', 'pending_approval'], true)) throw new RuntimeException('لا يمكن الاعتماد في الحالة الحالية.');
    $amount = (float)$old['total_amount'];
    if ($amount <= 0) throw new RuntimeException('المبلغ غير صالح.');
    dbExecute('START TRANSACTION');
    try {
        $expCode = (string)($old['expense_account_code'] ?? '5100') ?: '5100';
        $tt = ak_out_pick_enum('transactions', 'transaction_type', ['outflow', 'expense', 'disbursement'], 'outflow');
        $pm = ak_out_pick_enum('transactions', 'payment_method', ['cash', 'other'], 'other');
        $net = round($amount - 0.00, 2);
        $sql = "INSERT INTO transactions (amount, currency_code, payment_method, transaction_date, description, transaction_type, status, created_by, payment_period, purpose, admin_fee_percent, admin_fee_amount, net_amount) ";
        $sql .= "VALUES (?, 'SDG', ?, ?, ?, ?, 'posted', ?, ?, 'صرف شهري للأسر', 0.00, 0.00, ?)";
        dbExecute($sql, [$amount, $pm, date('Y-m-d'), 'صرف شهري #' . $id, $tt, $userId, (string)$old['month'], $net]);
        $txnId = (int)dbLastInsertId();
        ak_out_post_journal($txnId, $id, date('Y-m-d'), $amount, $expCode, '1100', 'صرف شهري #' . $id . ' شهر ' . $old['month'], $userId);
        $new = ['status' => 'approved', 'transaction_id' => $txnId, 'reviewed_by_user_id' => $userId, 'reviewed_at' => date('Y-m-d H:i:s'), 'return_note' => null];
        ak_out_update('monthly_disbursements', $new, ['id' => $id]);
        ak_out_audit($userId, 'APPROVE', $id, $old, array_merge($old, $new));
        ak_out_notify_treasury('out', $amount, 'اعتماد صرف خرجي #' . $old['id'], $txnId, $userId);
        dbExecute('COMMIT');
    } catch (Throwable $ex) { dbExecute('ROLLBACK'); throw $ex; }
}}

if (!function_exists('ak_out_void')) {
function ak_out_void(int $id, int $userId, string $reason): void {
    $old = ak_out_row('monthly_disbursements', $id);
    if (!$old) throw new RuntimeException('الدفعة غير موجودة.');
    if (!in_array($old['status'], ['approved', 'transferred'], true)) throw new RuntimeException('يمكن إبطال المعتمد أو المحوّل فقط.');
    $reason = trim($reason);
    if ($reason === '') throw new RuntimeException('سبب الإبطال مطلوب.');
    try {
        $blocked = dbFetchOne("SELECT id FROM disbursement_items WHERE disbursement_id = ? AND status IN ('paid','returned','return_requested') LIMIT 1", [$id]);
        if ($blocked) throw new RuntimeException('لا يمكن الإبطال بعد صرف/إرجاع أي بند — عالج البنود أولًا.');
    } catch (RuntimeException $ex) { throw $ex; } catch (Throwable $ex) {}
    $amount = (float)$old['total_amount'];
    $txnId = (int)($old['transaction_id'] ?? 0);
    dbExecute('START TRANSACTION');
    try {
        $revId = null;
        if ($txnId > 0 && $amount > 0) {
            $expCode = (string)($old['expense_account_code'] ?? '5100') ?: '5100';
            $revId = ak_out_reversal_journal($txnId, $id, date('Y-m-d'), $amount, $expCode, '1100', 'قيد عكسي لإبطال صرف #' . $id, $userId);
            // FIX: Added voided_at to satisfy CHECK constraint
            ak_out_update('transactions', ['status' => 'voided', 'voided_at' => date('Y-m-d H:i:s')], ['id' => $txnId]);
        }
        $new = ['status' => 'voided', 'voided_by_user_id' => $userId, 'voided_at' => date('Y-m-d H:i:s'), 'void_reason' => $reason, 'reversal_journal_id' => $revId, 'reviewed_by_user_id' => $userId, 'reviewed_at' => date('Y-m-d H:i:s')];
        ak_out_update('monthly_disbursements', $new, ['id' => $id]);
        ak_out_audit($userId, 'VOID', $id, $old, array_merge($old, $new));
        ak_out_notify_treasury('void_outflow', $amount, 'إبطال صرف #' . $old['id'], $txnId ?: null, $userId);
        dbExecute('COMMIT');
    } catch (Throwable $ex) { dbExecute('ROLLBACK'); throw $ex; }
}}

/* - verification - */
if (!function_exists('ak_out_family_locked')) {
function ak_out_family_locked(int $familyId, string $month): bool {
    $sql = "SELECT i.id FROM disbursement_items i ";
    $sql .= "JOIN monthly_disbursements d ON d.id = i.disbursement_id ";
    $sql .= "WHERE i.family_id = ? AND d.month = ? AND d.status NOT IN ('voided','cancelled') LIMIT 1";
    try { return (bool)dbFetchOne($sql, [$familyId, $month]); } catch (Throwable $ex) { return false; }
}}

if (!function_exists('ak_out_get_verification')) {
function ak_out_get_verification(int $familyId, string $month): ?array {
    try { return dbFetchOne("SELECT * FROM nanny_family_verifications WHERE family_id = ? AND month = ?", [$familyId, $month]); } catch (Throwable $ex) { return null; }
}}

if (!function_exists('ak_out_save_verification')) {
function ak_out_save_verification(int $familyId, string $month, int $nannyId, int $orphan, int $contact, int $bank, string $notes): void {
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) throw new RuntimeException('شهر غير صالح.');
    if (!in_array($month, ak_out_allowed_months(), true)) throw new RuntimeException('يمكن التوثيق للشهر الحالي أو القادم فقط.');
    $fam = ak_out_row('families', $familyId);
    if (!$fam || (int)$fam['nanny_id'] !== $nannyId) throw new RuntimeException('الأسرة غير تابعة لك.');
    if (($fam['status'] ?? '') !== 'active') throw new RuntimeException('الأسرة غير نشطة — لا يمكن التوثيق.');
    if (ak_out_family_locked($familyId, $month)) throw new RuntimeException('الأسرة ضمن دفعة الشهر — يتم التوثيق من داخل الدفعة.');
    $old = ak_out_get_verification($familyId, $month);
    $data = ['is_orphan_verified' => $orphan ? 1 : 0, 'is_mother_contact_verified' => $contact ? 1 : 0, 'is_bank_verified' => $bank ? 1 : 0, 'verification_notes' => $notes, 'verified_by_user_id' => $nannyId, 'verified_at' => date('Y-m-d H:i:s')];
    if ($old) { ak_out_update('nanny_family_verifications', $data, ['id' => (int)$old['id']]); $id = (int)$old['id']; }
    else { $data['family_id'] = $familyId; $data['month'] = $month; $data['nanny_id'] = $nannyId; $id = ak_out_insert('nanny_family_verifications', $data); }
}}

if (!function_exists('ak_out_save_item_verification')) {
function ak_out_save_item_verification(int $itemId, int $userId, int $orphan, int $contact, int $bank, string $notes): void {
    $it = ak_out_row('disbursement_items', $itemId);
    if (!$it) throw new RuntimeException('البند غير موجود.');
    $b = ak_out_row('monthly_disbursements', (int)$it['disbursement_id']);
    if (!$b || in_array($b['status'], ['voided', 'cancelled', 'received'], true)) throw new RuntimeException('الدفعة مقفلة.');
    $u = ak_out_user();
    $isOwnerNanny = ($u['role'] === 'nanny' && (int)$b['nanny_id'] === $userId);
    if (!$isOwnerNanny && $u['role'] !== 'admin') throw new RuntimeException('غير مصرح.');
    $new = ['is_orphan_verified' => $orphan ? 1 : 0, 'is_mother_contact_verified' => $contact ? 1 : 0, 'is_bank_verified' => $bank ? 1 : 0, 'verification_notes' => $notes, 'verified_by_user_id' => $userId, 'verified_at' => date('Y-m-d H:i:s')];
    ak_out_update('disbursement_items', $new, ['id' => $itemId]);
    ak_out_audit($userId, 'ITEM_CHECKLIST', $itemId, $it, array_merge($it, $new));
}}

/* - families & nannies - */
if (!function_exists('ak_out_nanny_active_families')) {
function ak_out_nanny_active_families(int $nannyId): array {
    $sql = "SELECT f.id, f.family_code, f.mother_name, f.mother_phone, f.bank_name, f.bank_account_number ";
    $sql .= "FROM families f WHERE f.nanny_id = ? AND f.status = 'active' ORDER BY f.family_code";
    try { return dbFetchAll($sql, [$nannyId]); } catch (Throwable $ex) { return []; }
}}

if (!function_exists('ak_out_allowed_months')) {
function ak_out_allowed_months(): array {
    $now = new DateTime();
    $current = $now->format('Y-m');
    $next = (clone $now)->modify('+1 month')->format('Y-m');
    return array_unique([$current, $next]);
}}

if (!function_exists('ak_out_nanny_families_with_totals')) {
function ak_out_nanny_families_with_totals(int $nannyId, ?string $month = null): array {
    $params = [];
    $sql = "SELECT f.id, f.family_code, f.mother_name, f.mother_phone, f.bank_name, f.bank_account_number, ";
    $sql .= "COALESCE(SUM(s.amount), 0) total_amount, ";
    $sql .= "MAX(v.is_orphan_verified) is_orphan_verified, MAX(v.is_mother_contact_verified) is_mother_contact_verified, MAX(v.is_bank_verified) is_bank_verified, ";
    $sql .= "MAX(v.verification_notes) verification_notes, MAX(v.verified_by_user_id) verified_by, MAX(v.verified_at) verified_at ";
    $sql .= "FROM families f ";
    $sql .= "JOIN family_children fc ON fc.family_id = f.id ";
    $sql .= "JOIN sponsorships s ON s.child_id = fc.id AND s.status = 'active' ";
    if ($month !== null) { $sql .= "LEFT JOIN nanny_family_verifications v ON v.family_id = f.id AND v.month = ? "; $params[] = $month; }
    else { $sql .= "LEFT JOIN nanny_family_verifications v ON v.family_id = f.id AND v.month = '' "; }
    $sql .= "WHERE f.nanny_id = ? AND f.status = 'active' ";
    $params[] = $nannyId;
    $sql .= "GROUP BY f.id, f.family_code, f.mother_name ORDER BY f.family_code";
    try { return dbFetchAll($sql, $params); } catch (Throwable $ex) { return []; }
}}

if (!function_exists('ak_out_item_verified')) {
function ak_out_item_verified(array $it): bool {
    return ((int)($it['is_orphan_verified'] ?? 0) === 1)
        && ((int)($it['is_mother_contact_verified'] ?? 0) === 1)
        && ((int)($it['is_bank_verified'] ?? 0) === 1);
}}

// Was called by my_nannies.php but never defined anywhere in this file — every click into
// a nanny's family-detail view was hitting "Call to undefined function
// ak_out_is_verified_row()" and crashing the whole page. Same 3-flag check as
// ak_out_item_verified() above, just for a nanny_family_verifications row instead of a
// disbursement_items row (both tables use the same 3 column names).
if (!function_exists('ak_out_is_verified_row')) {
function ak_out_is_verified_row(array $row): bool {
    return ((int)($row['is_orphan_verified'] ?? 0) === 1)
        && ((int)($row['is_mother_contact_verified'] ?? 0) === 1)
        && ((int)($row['is_bank_verified'] ?? 0) === 1);
}}

if (!function_exists('ak_out_fetch_nannies')) {
function ak_out_fetch_nannies(): array {
    $scope = ak_out_scoped_nanny_ids();
    $sql = "SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'nanny' AND u.is_active = 1";
    if ($scope !== null) {
        if (!$scope) return [];
        $sql .= ' AND u.id IN (' . implode(',', $scope) . ')';
    }
    $sql .= ' ORDER BY u.full_name';
    return dbFetchAll($sql);
}}

/* - returns flow (nanny requests → accountant confirms + partial reversal) - */
if (!function_exists('ak_out_request_return')) {
function ak_out_request_return(int $itemId, int $userId, string $reason): void {
    $reason = trim($reason);
    if ($reason === '') throw new RuntimeException('سبب الإرجاع مطلوب.');
    $it = ak_out_row('disbursement_items', $itemId);
    if (!$it || $it['status'] !== 'pending') throw new RuntimeException('البند ليس قيد الانتظار.');
    $b = ak_out_row('monthly_disbursements', (int)$it['disbursement_id']);
    if (!$b || $b['status'] !== 'transferred' || (int)$b['nanny_id'] !== $userId) throw new RuntimeException('غير مصرح بطلب الإرجاع.');
    $new = ['status' => 'return_requested', 'return_reason' => $reason, 'return_requested_at' => date('Y-m-d H:i:s'), 'return_requested_by_user_id' => $userId];
    ak_out_update('disbursement_items', $new, ['id' => $itemId]);
    ak_out_audit($userId, 'REQUEST_RETURN', $itemId, $it, array_merge($it, $new));
}}

if (!function_exists('ak_out_confirm_return')) {
function ak_out_confirm_return(int $itemId, int $userId): void {
    $it = ak_out_row('disbursement_items', $itemId);
    if (!$it || $it['status'] !== 'return_requested') throw new RuntimeException('البند ليس في حالة طلب إرجاع.');
    $b = ak_out_row('monthly_disbursements', (int)$it['disbursement_id']);
    if (!$b || $b['status'] !== 'transferred') throw new RuntimeException('الدفعة ليست محوّلة.');
    $u = ak_out_user();
    if (!in_array($u['role'], ['admin', 'accountant', 'accountant_staff'], true)) throw new RuntimeException('غير مصرح.');
    $amount = (float)$it['amount'];
    $expCode = (string)($b['expense_account_code'] ?? '5100') ?: '5100';
    dbExecute('START TRANSACTION');
    try {
        $revId = ak_out_item_reversal_journal($itemId, (int)$it['disbursement_id'], $amount, $expCode, $userId);
        $new = ['status' => 'returned', 'returned_at' => date('Y-m-d H:i:s'), 'returned_by_user_id' => $userId, 'reversal_journal_id' => $revId];
        ak_out_update('disbursement_items', $new, ['id' => $itemId]);
        ak_out_audit($userId, 'CONFIRM_RETURN', $itemId, $it, array_merge($it, $new));
        // Auto-close if no pending items
        $left = dbFetchOne("SELECT id FROM disbursement_items WHERE disbursement_id = ? AND status IN ('pending','return_requested') LIMIT 1", [(int)$it['disbursement_id']]);
        if (!$left) {
            $newBatch = ['status' => 'received', 'received_at' => date('Y-m-d H:i:s')];
            ak_out_update('monthly_disbursements', $newBatch, ['id' => (int)$it['disbursement_id']]);
            ak_out_audit($userId, 'AUTO_CLOSE', (int)$it['disbursement_id'], $b, array_merge($b, $newBatch));
        }
        dbExecute('COMMIT');
    } catch (Throwable $ex) { dbExecute('ROLLBACK'); throw $ex; }
}}
/* - Reopen paid item (FM only) - */
if (!function_exists('ak_out_reopen_item')) {
function ak_out_reopen_item(int $itemId, int $userId): void {
    $it = ak_out_row('disbursement_items', $itemId);
    if (!$it) throw new RuntimeException('البند غير موجود.');
    if ($it['status'] !== 'paid') throw new RuntimeException('يمكن إعادة فتح البنود المدفوعة (paid) فقط.');
    
    $b = ak_out_row('monthly_disbursements', (int)$it['disbursement_id']);
    if (!$b) throw new RuntimeException('الدفعة غير موجودة.');
    
    $u = ak_out_user();
    // المدير المالي أو المدير العام فقط
    if (!in_array($u['role'], ['financial_manager', 'admin', 'general_manager', 'vice_general_manager'], true)) {
        throw new RuntimeException('غير مصرح. هذه العملية للمدير المالي فقط.');
    }

    dbExecute('START TRANSACTION');
    try {
        // 1. إعادة البند إلى "قيد الانتظار"
        $newItem = ['status' => 'pending', 'confirmed_at' => null];
        ak_out_update('disbursement_items', $newItem, ['id' => $itemId]);
        
        // 2. إذا كانت الدفعة "مستلمة" (received)، نعيدها إلى "محوّلة" (transferred) لتبقى مفتوحة
        if ($b['status'] === 'received') {
            $newBatch = ['status' => 'transferred', 'received_at' => null];
            ak_out_update('monthly_disbursements', $newBatch, ['id' => (int)$it['disbursement_id']]);
        }
        
        ak_out_audit($userId, 'REOPEN_ITEM', $itemId, $it, array_merge($it, $newItem));
        dbExecute('COMMIT');
    } catch (Throwable $ex) { dbExecute('ROLLBACK'); throw $ex; }
}}
/* - auto-close - */
if (!function_exists('ak_out_auto_close_batch')) {
function ak_out_auto_close_batch(int $batchId, int $userId): void {
    $b = ak_out_row('monthly_disbursements', $batchId);
    if (!$b || in_array($b['status'], ['voided', 'cancelled', 'received'], true)) return;
    $left = dbFetchOne("SELECT id FROM disbursement_items WHERE disbursement_id = ? AND status IN ('pending','return_requested') LIMIT 1", [$batchId]);
    if (!$left) {
        $new = ['status' => 'received', 'received_at' => date('Y-m-d H:i:s')];
        ak_out_update('monthly_disbursements', $new, ['id' => $batchId]);
        ak_out_audit($userId, 'AUTO_CLOSE', $batchId, $b, array_merge($b, $new));
    }
}}

/* - presentation helpers - */
if (!function_exists('ak_out_money')) {
function ak_out_money($n): string { return number_format((float)$n, 0); }}

if (!function_exists('ak_out_status_badge')) {
function ak_out_status_badge(string $s): string {
    $map = ['draft' => ['مسوّدة', 'gray'], 'pending' => ['معلّق', 'amber'], 'pending_approval' => ['بانتظار الاعتماد', 'amber'],
        'approved' => ['معتمد', 'blue'], 'returned' => ['مُرجَع', 'dark'], 'transferred' => ['تم التحويل', 'green'],
        'received' => ['مستلم', 'green'], 'cancelled' => ['مُلغى', 'red'], 'voided' => ['مُبطَل', 'red']];
    [$l, $c] = $map[$s] ?? [$s, 'gray'];
    return '<span class="ak-badge ak-b-' . e($c) . '">' . e($l) . '</span>';
}}

if (!function_exists('ak_out_item_badge')) {
function ak_out_item_badge(string $s): string {
    $map = ['pending' => ['بانتظار التأكيد', 'amber'], 'paid' => ['تم الاستلام', 'green'], 'return_requested' => ['بانتظار تأكيد الإرجاع', 'amber'], 'returned' => ['مُرجَع', 'dark']];
    [$l, $c] = $map[$s] ?? [$s, 'gray'];
    return '<span class="ak-badge ak-b-' . e($c) . '">' . e($l) . '</span>';
}}

/* - receipts (secure) - */
if (!function_exists('ak_out_upload_receipt')) {
function ak_out_upload_receipt(array $file, int $id): string {
    $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    $mime = (string)($file['type'] ?? '');
    if (!isset($allowed[$mime]) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new RuntimeException('نوع ملف غير مسموح.');
    if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('حجم الإيصال يجب ألا يتجاوز 10 ميجابايت.');
    $dir = dirname(__DIR__, 2) . '/storage/receipts';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $fname = 'receipt_' . $id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $fname)) throw new RuntimeException('تعذر حفظ الملف.');
    return 'storage/receipts/' . $fname;
}}

if (!function_exists('ak_out_serve_receipt')) {
function ak_out_serve_receipt(int $id, int $uid, bool $allowed, string $kind = 'batch'): void {
    if ($kind === 'item') {
        $it = ak_out_row('disbursement_items', $id);
        $row = $it ? ak_out_row('monthly_disbursements', (int)$it['disbursement_id']) : null;
        $p = (string)($it['receipt_file_path'] ?? '');
    } else {
        $row = ak_out_row('monthly_disbursements', $id);
        $p = (string)($row['receipt_file_path'] ?? '');
    }
    $owner = $row && (int)$row['nanny_id'] === $uid;
    if (!$row || !($owner || $allowed)) { http_response_code(403); exit('403'); }
    if ($p === '') { http_response_code(404); exit('404'); }
    $base = realpath(dirname(__DIR__, 2));
    $full = realpath($base . '/' . $p);
    if ($full === false || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0) { http_response_code(404); exit('404'); }
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string)filesize($full));
    header('Cache-Control: no-store');
    readfile($full);
    exit;
}}

// Initialize schema on first load
ak_out_ensure_schema();