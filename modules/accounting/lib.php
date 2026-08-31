<?php
// modules/accounting/lib.php - Accounting core (tables, COA seed, auto-posting, tafqit, projects)

if (!function_exists('ak_ensure_tables')) {
function ak_ensure_tables(): void {
    static $done = false; if ($done) return; $done = true;
    dbExecute("CREATE TABLE IF NOT EXISTS accounts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) NOT NULL,
        name_ar VARCHAR(150) NOT NULL,
        name_en VARCHAR(150) DEFAULT NULL,
        account_type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
        parent_id INT UNSIGNED NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        description VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_accounts_code (code), KEY idx_accounts_type (account_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dbExecute("CREATE TABLE IF NOT EXISTS journal_entries (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entry_code VARCHAR(50) NOT NULL,
        entry_date DATE NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        reference_type VARCHAR(50) DEFAULT NULL,
        reference_id INT UNSIGNED NULL,
        status ENUM('posted','voided') NOT NULL DEFAULT 'posted',
        voided_at DATETIME NULL, voided_by INT UNSIGNED NULL, void_reason VARCHAR(255) NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_je_code (entry_code), KEY idx_je_date (entry_date), KEY idx_je_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dbExecute("CREATE TABLE IF NOT EXISTS journal_lines (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entry_id INT UNSIGNED NOT NULL,
        account_id INT UNSIGNED NOT NULL,
        debit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        credit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        description VARCHAR(255) DEFAULT NULL,
        KEY idx_jl_entry (entry_id), KEY idx_jl_account (account_id),
        CONSTRAINT fk_jl_entry FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
        CONSTRAINT fk_jl_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dbExecute("CREATE TABLE IF NOT EXISTS vouchers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        voucher_type ENUM('receipt','payment') NOT NULL,
        voucher_no VARCHAR(50) NOT NULL,
        voucher_date DATE NOT NULL,
        party_name VARCHAR(150) DEFAULT NULL,
        amount DECIMAL(14,2) NOT NULL,
        cash_account_id INT UNSIGNED NOT NULL,
        other_account_id INT UNSIGNED NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        reference_number VARCHAR(100) DEFAULT NULL,
        entry_id INT UNSIGNED NULL,
        status ENUM('posted','voided') NOT NULL DEFAULT 'posted',
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_vouchers_no (voucher_no),
        KEY idx_vouchers_type (voucher_type), KEY idx_vouchers_date (voucher_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
}

if (!function_exists('ak_ensure_project_tables')) {
function ak_ensure_project_tables(): void {
    static $done = false; if ($done) return; $done = true;
    dbExecute("ALTER TABLE other_projects ADD COLUMN IF NOT EXISTS revenue_account_id INT UNSIGNED NULL");
    dbExecute("ALTER TABLE other_projects ADD COLUMN IF NOT EXISTS expense_account_id INT UNSIGNED NULL");
    dbExecute("CREATE TABLE IF NOT EXISTS project_beneficiaries (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id INT UNSIGNED NOT NULL,
        beneficiary_name VARCHAR(150) NOT NULL,
        details VARCHAR(255) DEFAULT NULL,
        amount DECIMAL(12,2) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_pb_project (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
}

if (!function_exists('ak_sync_project_accounts')) {
function ak_sync_project_accounts(int $projectId, string $name): void {
    $p = dbFetchOne("SELECT revenue_account_id, expense_account_id FROM other_projects WHERE id = ?", [$projectId]);
    if (!$p) return;
    $revId = (int)($p['revenue_account_id'] ?? 0);
    $expId = (int)($p['expense_account_id'] ?? 0);
    if ($revId <= 0) {
        $code = '4400-' . $projectId;
        $ex = dbFetchOne("SELECT id FROM accounts WHERE code = ?", [$code]);
        if ($ex) { $revId = (int)$ex['id']; }
        else {
            dbExecute("INSERT INTO accounts (code, name_ar, account_type) VALUES (?, ?, 'revenue')", [$code, 'إيرادات مشروع: ' . $name]);
            $revId = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);
        }
    }
    if ($expId <= 0) {
        $code = '5100-' . $projectId;
        $ex = dbFetchOne("SELECT id FROM accounts WHERE code = ?", [$code]);
        if ($ex) { $expId = (int)$ex['id']; }
        else {
            dbExecute("INSERT INTO accounts (code, name_ar, account_type) VALUES (?, ?, 'expense')", [$code, 'مصروفات مشروع: ' . $name]);
            $expId = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);
        }
    }
    dbExecute("UPDATE other_projects SET revenue_account_id = ?, expense_account_id = ? WHERE id = ?", [$revId, $expId, $projectId]);
}
}

if (!function_exists('ak_seed_accounts')) {
function ak_seed_accounts(): void {
    if ((int)(dbFetchOne("SELECT COUNT(*) c FROM accounts")['c'] ?? 0) > 0) return;
    $seed = [
        ['1100','الصندوق (نقدي)','Cash','asset'],
        ['1200','البنك','Bank','asset'],
        ['1300','المحافظ الإلكترونية','Mobile Wallets','asset'],
        ['1400','ذمم مدينة (مستحقات قبض)','Receivables','asset'],
        ['2100','ذمم دائنة (مستحقات دفع)','Payables','liability'],
        ['2200','إيرادات مؤجلة (كفالات مقدماً)','Deferred Revenue','liability'],
        ['3100','الأرصدة الافتتاحية','Opening Balances','equity'],
        ['3200','فائض مدور','Retained Surplus','equity'],
        ['4100','إيرادات الكفالات','Sponsorship Revenue','revenue'],
        ['4200','الرسوم الإدارية','Admin Fees Revenue','revenue'],
        ['4300','التبرعات العامة','General Donations','revenue'],
        ['4400','إيرادات المشاريع','Projects Revenue','revenue'],
        ['5100','مصروفات البرامج والمساعدات','Programs & Aid Expenses','expense'],
        ['5200','الرواتب والأجور','Salaries','expense'],
        ['5300','المصروفات التشغيلية','Operating Expenses','expense'],
        ['5400','المصروفات الإدارية','Admin Expenses','expense'],
    ];
    foreach ($seed as $s) dbExecute("INSERT INTO accounts (code, name_ar, name_en, account_type) VALUES (?,?,?,?)", $s);
}
}

if (!function_exists('ak_account_id')) {
function ak_account_id(string $code): int {
    $r = dbFetchOne("SELECT id FROM accounts WHERE code = ?", [$code]);
    return $r ? (int)$r['id'] : 0;
}
}

if (!function_exists('ak_cash_code')) {
function ak_cash_code(string $method): string {
    return ['cash'=>'1100','bank_transfer'=>'1200','credit_card'=>'1200','mobile'=>'1300','other'=>'1100'][$method] ?? '1100';
}
}

if (!function_exists('ak_post_transaction_journal')) {
function ak_post_transaction_journal(int $txnId): int {
    ak_ensure_tables(); ak_seed_accounts();
    $t = dbFetchOne("SELECT * FROM transactions WHERE id = ?", [$txnId]);
    if (!$t || $t['status'] !== 'posted') return 0;
    $ex = dbFetchOne("SELECT id FROM journal_entries WHERE reference_type='transaction' AND reference_id=? AND status='posted'", [$txnId]);
    if ($ex) return (int)$ex['id'];
    $amount = (float)$t['amount']; $fee = (float)$t['admin_fee_amount'];
    $net = (float)$t['net_amount'] > 0 ? (float)$t['net_amount'] : ($amount - $fee);
    $type = (string)($t['transaction_type'] ?? 'sponsorship_payment');
    $lines = [[ak_cash_code((string)$t['payment_method']), $amount, 0.0, 'تحصيل ' . $t['transaction_code']]];
    if ($type === 'general_donation') {
        $lines[] = ['4300', 0.0, $amount, 'تبرع عام ' . $t['transaction_code']];
    } elseif ($type === 'project_donation') {
        $revCode = '4400';
        if (!empty($t['project_id'])) {
            $prj = dbFetchOne("SELECT revenue_account_id FROM other_projects WHERE id = ?", [(int)$t['project_id']]);
            if ($prj && !empty($prj['revenue_account_id'])) {
                $acc = dbFetchOne("SELECT code FROM accounts WHERE id = ?", [(int)$prj['revenue_account_id']]);
                if ($acc) $revCode = $acc['code'];
            }
        }
        $lines[] = [$revCode, 0.0, $amount, 'إيراد مشروع ' . $t['transaction_code']];
    } else {
        $lines[] = ['4100', 0.0, $net, 'إيراد كفالات ' . $t['transaction_code']];
        if ($fee > 0) $lines[] = ['4200', 0.0, $fee, 'رسوم إدارية ' . $t['transaction_code']];
    }
    $n = (int)(dbFetchOne("SELECT COUNT(*) c FROM journal_entries")['c'] ?? 0) + 1;
    $code = 'JE-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
    dbExecute("INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
               VALUES (?,?,?,'transaction',?,'posted',?)",
        [$code, $t['transaction_date'], 'قيد آلي من ' . $t['transaction_code'], $txnId, Session::getUserId()]);
    $eid = (int)(dbFetchOne("SELECT LAST_INSERT_ID() id")['id']);
    foreach ($lines as $l) {
        $aid = ak_account_id($l[0]);
        if ($aid > 0) dbExecute("INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)", [$eid, $aid, $l[1], $l[2], $l[3]]);
    }
    return $eid;
}
}

if (!function_exists('ak_void_journal_for_transaction')) {
function ak_void_journal_for_transaction(int $txnId, string $reason): void {
    dbExecute("UPDATE journal_entries SET status='voided', voided_at=NOW(), voided_by=?, void_reason=?
               WHERE reference_type='transaction' AND reference_id=? AND status='posted'",
        [Session::getUserId(), $reason, $txnId]);
}
}

if (!function_exists('ak_void_journal_for_voucher')) {
function ak_void_journal_for_voucher(int $voucherId, string $reason): void {
    dbExecute("UPDATE journal_entries SET status='voided', voided_at=NOW(), voided_by=?, void_reason=?
               WHERE reference_type='voucher' AND reference_id=? AND status='posted'",
        [Session::getUserId(), $reason, $voucherId]);
}
}

if (!function_exists('ak_tafqit')) {
function ak_tafqit(int $n): string {
    if ($n === 0) return 'صفر';
    $ones = ['','واحد','اثنان','ثلاثة','أربعة','خمسة','ستة','سبعة','ثمانية','تسعة','عشرة','أحد عشر','اثنا عشر','ثلاثة عشر','أربعة عشر','خمسة عشر','ستة عشر','سبعة عشر','ثمانية عشر','تسعة عشر'];
    $tens = ['','عشرة','عشرون','ثلاثون','أربعون','خمسون','ستون','سبعون','ثمانون','تسعون'];
    $hunds = ['','مائة','مائتان','ثلاثمائة','أربعمائة','خمسمائة','ستمائة','سبعمائة','ثمانمائة','تسعمائة'];
    $three = function (int $x) use ($ones, $tens, $hunds): string {
        $parts = [];
        $h = intdiv($x, 100); $r = $x % 100;
        if ($h) $parts[] = $hunds[$h];
        if ($r) {
            if ($r < 20) $parts[] = $ones[$r];
            else { $o = $r % 10; $t = intdiv($r, 10); $parts[] = $o ? $ones[$o] . ' و' . $tens[$t] : $tens[$t]; }
        }
        return implode(' و', $parts);
    };
    $parts = [];
    $m = intdiv($n, 1000000); $th = intdiv($n % 1000000, 1000); $rest = $n % 1000;
    if ($m)  $parts[] = $m === 1 ? 'مليون' : ($m === 2 ? 'مليونان' : $three($m) . ' مليون');
    if ($th) $parts[] = $th === 1 ? 'ألف' : ($th === 2 ? 'ألفان' : $three($th) . ' ألف');
    if ($rest) $parts[] = $three($rest);
    return implode(' و', $parts);
}
}