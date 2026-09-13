<?php
/**
 * Administrative-fee policy and calculation helpers.
 * Currency for the application is SDG. Historical transactions keep the
 * policy snapshot that was used when they were posted.
 */

if (!function_exists('ak_ensure_admin_fee_policy_table')) {
    function ak_ensure_admin_fee_policy_table(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        dbExecute("CREATE TABLE IF NOT EXISTS accounting_admin_fee_policies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            policy_name VARCHAR(150) NOT NULL DEFAULT 'سياسة الرسوم الإدارية',
            method ENUM('none','fixed','percentage') NOT NULL DEFAULT 'none',
            value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            currency_code CHAR(3) NOT NULL DEFAULT 'SDG',
            effective_from DATE NOT NULL,
            status ENUM('draft','active','superseded') NOT NULL DEFAULT 'draft',
            opening_balance_entry_id INT UNSIGNED NULL,
            published_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_afp_status_effective (status, effective_from),
            KEY idx_afp_opening_balance (opening_balance_entry_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Keep the existing schema compatible while adding immutable policy snapshots.
        dbExecute("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS admin_fee_method VARCHAR(20) NOT NULL DEFAULT 'none'");
        dbExecute("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS admin_fee_value DECIMAL(14,2) NOT NULL DEFAULT 0.00");
        dbExecute("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS admin_fee_policy_id INT UNSIGNED NULL");
        dbExecute("ALTER TABLE sponsor_payments ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30) NOT NULL DEFAULT 'cash'");
    }
}

if (!function_exists('ak_admin_fee_method_label')) {
    function ak_admin_fee_method_label(string $method): string
    {
        return [
            'none' => 'بدون رسوم',
            'fixed' => 'مبلغ ثابت',
            'percentage' => 'نسبة مئوية',
        ][$method] ?? 'بدون رسوم';
    }
}

if (!function_exists('ak_get_admin_fee_policy')) {
    function ak_get_admin_fee_policy(?string $onDate = null): ?array
    {
        ak_ensure_admin_fee_policy_table();
        $onDate = $onDate ?: date('Y-m-d');
        return dbFetchOne("SELECT * FROM accounting_admin_fee_policies
                           WHERE status = 'active' AND effective_from <= ?
                           ORDER BY effective_from DESC, id DESC LIMIT 1", [$onDate]);
    }
}

if (!function_exists('ak_get_admin_fee_draft')) {
    function ak_get_admin_fee_draft(): ?array
    {
        ak_ensure_admin_fee_policy_table();
        return dbFetchOne("SELECT * FROM accounting_admin_fee_policies WHERE status='draft' ORDER BY id DESC LIMIT 1");
    }
}

if (!function_exists('ak_has_live_opening_balance')) {
    function ak_has_live_opening_balance(): bool
    {
        return (int)(dbFetchOne("SELECT COUNT(*) c FROM journal_entries WHERE reference_type='opening_balance' AND status='posted'")['c'] ?? 0) > 0;
    }
}

if (!function_exists('ak_save_admin_fee_policy')) {
    function ak_save_admin_fee_policy(string $method, float $value, string $effectiveFrom, int $uid): int
    {
        ak_ensure_admin_fee_policy_table();
        $allowed = ['none','fixed','percentage'];
        if (!in_array($method, $allowed, true)) throw new RuntimeException('طريقة الرسوم الإدارية غير صالحة.');
        if ($value < 0) throw new RuntimeException('قيمة الرسوم الإدارية لا يمكن أن تكون سالبة.');
        if ($method === 'none') $value = 0.00;
        if ($method === 'percentage' && $value > 100) throw new RuntimeException('نسبة الرسوم الإدارية لا يمكن أن تتجاوز 100%.');
        if ($effectiveFrom === '') $effectiveFrom = date('Y-m-d');
        if (ak_has_live_opening_balance()) throw new RuntimeException('السياسة الحالية مقفلة لأن الرصيد الافتتاحي أصبح فعالاً. أنشئ سياسة جديدة بتاريخ سريان جديد بدلاً من تعديل السياسة السابقة.');

        $draft = ak_get_admin_fee_draft();
        if ($draft) {
            dbExecute("UPDATE accounting_admin_fee_policies SET method=?, value=?, currency_code='SDG', effective_from=?, created_by=? WHERE id=? AND status='draft'",
                [$method, round($value,2), $effectiveFrom, $uid, (int)$draft['id']]);
            return (int)$draft['id'];
        }
        dbExecute("INSERT INTO accounting_admin_fee_policies (policy_name, method, value, currency_code, effective_from, status, created_by)
                   VALUES ('سياسة الرسوم الإدارية', ?, ?, 'SDG', ?, 'draft', ?)",
            [$method, round($value,2), $effectiveFrom, $uid]);
        return (int)dbLastInsertId();
    }
}

if (!function_exists('ak_activate_admin_fee_policy')) {
    function ak_activate_admin_fee_policy(int $policyId, int $openingBalanceEntryId, int $uid): void
    {
        ak_ensure_admin_fee_policy_table();
        $policy = dbFetchOne("SELECT * FROM accounting_admin_fee_policies WHERE id=? AND status='draft' FOR UPDATE", [$policyId]);
        if (!$policy) throw new RuntimeException('سياسة الرسوم الإدارية غير موجودة أو ليست مسودة.');
        dbExecute("UPDATE accounting_admin_fee_policies SET status='superseded' WHERE status='active'");
        dbExecute("UPDATE accounting_admin_fee_policies SET status='active', opening_balance_entry_id=?, published_at=NOW(), created_by=? WHERE id=? AND status='draft'",
            [$openingBalanceEntryId, $uid, $policyId]);
    }
}

if (!function_exists('ak_calculate_admin_fee')) {
    function ak_calculate_admin_fee(float $grossAmount, ?array $policy = null): array
    {
        $grossAmount = round(max(0, $grossAmount), 2);
        $policy = $policy ?: ak_get_admin_fee_policy();
        if (!$policy) {
            return ['method'=>'none','value'=>0.00,'amount'=>0.00,'net_amount'=>$grossAmount,'policy_id'=>null];
        }
        $method = (string)$policy['method'];
        $value = round((float)$policy['value'], 2);
        if ($method === 'fixed') $fee = min($grossAmount, $value);
        elseif ($method === 'percentage') $fee = round($grossAmount * $value / 100, 2);
        else $fee = 0.00;
        $fee = min($grossAmount, max(0.00, round($fee, 2)));
        return ['method'=>$method,'value'=>$value,'amount'=>$fee,'net_amount'=>round($grossAmount-$fee,2),'policy_id'=>(int)$policy['id']];
    }
}

if (!function_exists('ak_apply_admin_fee_snapshot')) {
    function ak_apply_admin_fee_snapshot(int $txnId): array
    {
        ak_ensure_admin_fee_policy_table();
        $t = dbFetchOne("SELECT * FROM transactions WHERE id=? FOR UPDATE", [$txnId]);
        if (!$t) throw new RuntimeException('المعاملة غير موجودة.');
        $type = (string)($t['transaction_type'] ?? 'sponsorship_payment');
        if (!in_array($type, ['sponsorship_payment','admin_fee'], true)) {
            return ['method'=>'none','value'=>0.00,'amount'=>0.00,'net_amount'=>(float)$t['amount'],'policy_id'=>null];
        }
        // Preserve an existing snapshot on already-posted historical transactions.
        if (!empty($t['admin_fee_policy_id']) && $t['admin_fee_method'] !== null) {
            return [
                'method'=>(string)$t['admin_fee_method'],
                'value'=>(float)$t['admin_fee_value'],
                'amount'=>(float)$t['admin_fee_amount'],
                'net_amount'=>(float)$t['net_amount'],
                'policy_id'=>(int)$t['admin_fee_policy_id'],
            ];
        }
        $calc = ak_calculate_admin_fee((float)$t['amount'], ak_get_admin_fee_policy((string)$t['transaction_date']));
        dbExecute("UPDATE transactions SET admin_fee_method=?, admin_fee_value=?, admin_fee_amount=?, net_amount=?, admin_fee_policy_id=? WHERE id=?",
            [$calc['method'], $calc['value'], $calc['amount'], $calc['net_amount'], $calc['policy_id'], $txnId]);
        return $calc;
    }
}
