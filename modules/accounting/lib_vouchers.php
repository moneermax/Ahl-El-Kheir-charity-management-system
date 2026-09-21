<?php
// modules/accounting/lib_vouchers.php - shared rules for receipt & payment vouchers (سندات القبض والصرف)
// Used by vouchers.php (issue / list / void) and voucher_print.php (printable receipt).

if (!defined('AK_VOUCHER_VIEW_ROLES')) {
    // may open the register and print a voucher
    define('AK_VOUCHER_VIEW_ROLES', ['admin', 'financial_manager', 'accountant_staff', 'accountant', 'general_manager', 'vice_general_manager']);
    // may issue (create + post) a voucher: the Financial Manager and the accounting staff
    define('AK_VOUCHER_ISSUE_ROLES', ['admin', 'financial_manager', 'accountant_staff']);
    // may void a voucher
    define('AK_VOUCHER_VOID_ROLES', ['admin', 'financial_manager']);
    // one lock shared with the manual journal screen, so entry codes never collide
    define('AK_VOUCHER_LOCK', 'ahl_el_kheir.manual_journal_code');
}

if (!function_exists('ak_voucher_can')) {
    function ak_voucher_can(string $action, ?string $role): bool
    {
        $map = ['view' => AK_VOUCHER_VIEW_ROLES, 'issue' => AK_VOUCHER_ISSUE_ROLES, 'void' => AK_VOUCHER_VOID_ROLES];
        return $role !== null && isset($map[$action]) && in_array($role, $map[$action], true);
    }
}

if (!function_exists('ak_voucher_cash_balance')) {
    /** Current balance of a cash / bank / wallet account = posted debits - posted credits (same rule as every report). */
    function ak_voucher_cash_balance(int $accountId): float
    {
        $r = dbFetchOne(
            "SELECT COALESCE(SUM(jl.debit), 0) d, COALESCE(SUM(jl.credit), 0) c
             FROM journal_lines jl
             JOIN journal_entries je ON je.id = jl.entry_id
             WHERE jl.account_id = ? AND je.status = 'posted'",
            [$accountId]
        );
        return round((float)($r['d'] ?? 0) - (float)($r['c'] ?? 0), 2);
    }
}

if (!function_exists('ak_voucher_lock')) {
    function ak_voucher_lock(): bool
    {
        $r = dbFetchOne("SELECT GET_LOCK(?, 5) AS locked", [AK_VOUCHER_LOCK]);
        return (int)($r['locked'] ?? 0) === 1;
    }
    function ak_voucher_unlock(): void
    {
        try { dbFetchOne("SELECT RELEASE_LOCK(?) AS released", [AK_VOUCHER_LOCK]); } catch (Throwable $e) {}
    }
}

if (!function_exists('ak_voucher_next_journal_code')) {
    /** Next JE-000123 code. Call while holding ak_voucher_lock(). */
    function ak_voucher_next_journal_code(): string
    {
        $r = dbFetchOne("SELECT COALESCE(MAX(CAST(SUBSTRING(entry_code, 4) AS UNSIGNED)), 0) AS max_no
                         FROM journal_entries WHERE entry_code REGEXP '^JE-[0-9]+$'");
        return 'JE-' . str_pad((string)((int)($r['max_no'] ?? 0) + 1), 6, '0', STR_PAD_LEFT);
    }
    /** Next RV-000123 / PV-000123 number. Call while holding ak_voucher_lock(). */
    function ak_voucher_next_number(string $type): string
    {
        $prefix = $type === 'payment' ? 'PV' : 'RV';
        $r = dbFetchOne("SELECT COALESCE(MAX(CAST(SUBSTRING(voucher_no, 4) AS UNSIGNED)), 0) AS max_no
                         FROM vouchers WHERE voucher_no REGEXP ?", ['^' . $prefix . '-[0-9]+$']);
        return $prefix . '-' . str_pad((string)((int)($r['max_no'] ?? 0) + 1), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('ak_voucher_amount_words')) {
    /** Whole number in Arabic words with correct thousands / millions forms (ثلاثة آلاف, اثنا عشر ألفاً, ...). */
    function ak_voucher_words_int(int $n): string
    {
        if ($n === 0) return 'صفر';
        $ones = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
        $tens = ['', 'عشرة', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
        $hunds = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];
        $three = static function (int $x) use ($ones, $tens, $hunds): string {
            $p = []; $h = intdiv($x, 100); $r = $x % 100;
            if ($h) $p[] = $hunds[$h];
            if ($r) {
                if ($r < 20) $p[] = $ones[$r];
                else { $o = $r % 10; $t = intdiv($r, 10); $p[] = $o ? $ones[$o] . ' و' . $tens[$t] : $tens[$t]; }
            }
            return implode(' و', $p);
        };
        // scale word: one, two, 3-10 plural, 11-99 accusative, 100+ singular
        $scale = static function (int $x, array $f) use ($three): string {
            if ($x === 1) return $f[0];
            if ($x === 2) return $f[1];
            $w = $three($x); $t = $x % 100;
            if ($x < 100 && $x <= 10) return $w . ' ' . $f[2];
            if ($t >= 3 && $t <= 10) return $w . ' ' . $f[2];
            if ($t >= 11) return $w . ' ' . $f[3];
            return $w . ' ' . $f[4];
        };
        $parts = [];
        $bn = intdiv($n, 1000000000); $m = intdiv($n % 1000000000, 1000000); $th = intdiv($n % 1000000, 1000); $rest = $n % 1000;
        if ($bn) $parts[] = $scale($bn, ['مليار', 'ملياران', 'مليارات', 'ملياراً', 'مليار']);
        if ($m) $parts[] = $scale($m, ['مليون', 'مليونان', 'ملايين', 'مليوناً', 'مليون']);
        if ($th) $parts[] = $scale($th, ['ألف', 'ألفان', 'آلاف', 'ألفاً', 'ألف']);
        if ($rest) $parts[] = $three($rest);
        return implode(' و', $parts);
    }

    /** Counted noun in the correct form: 1 / 2 / 3-10 / 11-99 / 100+ */
    function ak_voucher_counted(int $n, array $forms): string
    {
        // $forms = [one, two, plural(3-10), accusative(11-99), singular(100+)]
        if ($n === 1) return $forms[0];
        if ($n === 2) return $forms[1];
        if ($n >= 3 && $n <= 10) return $forms[2];
        if ($n >= 11 && $n <= 99) return $forms[3];
        $last = $n % 100;
        if ($last >= 3 && $last <= 10) return $forms[2];
        if ($last >= 11) return $forms[3];
        return $forms[4];
    }

    /** "ألف جنيه سوداني و خمسون قرشاً فقط لا غير" */
    function ak_voucher_amount_words(float $amount): string
    {
        $amount = round($amount, 2);
        $pounds = (int)floor($amount + 0.000001);
        $qirsh = (int)round(($amount - $pounds) * 100);
        if ($qirsh >= 100) { $pounds++; $qirsh = 0; }
        $parts = [];
        if ($pounds > 0 || $qirsh === 0) {
            $unit = ak_voucher_counted($pounds, ['جنيه سوداني', 'جنيهان سودانيان', 'جنيهات سودانية', 'جنيهاً سودانياً', 'جنيه سوداني']);
            $parts[] = $pounds === 1 ? 'جنيه سوداني واحد' : ($pounds === 2 ? $unit : ak_voucher_words_int($pounds) . ' ' . $unit);
        }
        if ($qirsh > 0) {
            $unit = ak_voucher_counted($qirsh, ['قرش', 'قرشان', 'قروش', 'قرشاً', 'قرش']);
            $parts[] = $qirsh === 1 ? 'قرش واحد' : ($qirsh === 2 ? $unit : ak_voucher_words_int($qirsh) . ' ' . $unit);
        }
        return implode(' و', $parts) . ' فقط لا غير';
    }
}
