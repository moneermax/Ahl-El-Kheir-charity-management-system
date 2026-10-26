<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/accounting/lib_vouchers.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'GET') {
    header('Allow: GET');
    ak_api_json(405, ['ok' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'Only GET is supported by the voucher register endpoint.']]);
}

$user = ak_api_authenticated_user();
// Match the existing website's exact persisted-role allowlist; do not widen via aliases.
$role = (string)($user['raw_role_code'] ?? '');
if (!ak_voucher_can('view', $role)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Voucher access is not allowed for this account.']]);
}

$type = trim((string)($_GET['vtype'] ?? ''));
if ($type !== '' && !in_array($type, ['receipt', 'payment'], true)) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_voucher_type', 'message' => 'Voucher type must be receipt or payment.']]);
}

$pdo = db();
try {
    $cashAccounts = $pdo->query("SELECT id, code, name_ar FROM accounts WHERE code IN ('1100','1200','1300') ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
    $balanceStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0) AS balance
         FROM journal_lines jl
         JOIN journal_entries je ON je.id = jl.entry_id
         WHERE jl.account_id = ? AND je.status = 'posted'"
    );
    foreach ($cashAccounts as &$cashAccount) {
        $balanceStmt->execute([(int)$cashAccount['id']]);
        $cashAccount['balance'] = round((float)$balanceStmt->fetchColumn(), 2);
        $cashAccount['id'] = (int)$cashAccount['id'];
    }
    unset($cashAccount);

    $where = $type === '' ? '' : 'WHERE v.voucher_type = ?';
    $stmt = $pdo->prepare(
        "SELECT v.id, v.voucher_type, v.voucher_no, v.voucher_date, v.party_name, v.amount,
                v.cash_account_id, c1.code AS cash_code, c1.name_ar AS cash_name,
                v.other_account_id, c2.code AS other_code, c2.name_ar AS other_name,
                v.description, v.reference_number, v.entry_id, v.status, v.created_by,
                v.created_at, u.full_name AS creator, je.entry_code
         FROM vouchers v
         JOIN accounts c1 ON c1.id = v.cash_account_id
         JOIN accounts c2 ON c2.id = v.other_account_id
         LEFT JOIN users u ON u.id = v.created_by
         LEFT JOIN journal_entries je ON je.id = v.entry_id
         $where
         ORDER BY v.id DESC
         LIMIT 300"
    );
    $stmt->execute($type === '' ? [] : [$type]);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($vouchers as &$voucher) {
        $voucher['id'] = (int)$voucher['id'];
        $voucher['amount'] = round((float)$voucher['amount'], 2);
        $voucher['cash_account_id'] = (int)$voucher['cash_account_id'];
        $voucher['other_account_id'] = (int)$voucher['other_account_id'];
        $voucher['entry_id'] = $voucher['entry_id'] === null ? null : (int)$voucher['entry_id'];
        $voucher['created_by'] = $voucher['created_by'] === null ? null : (int)$voucher['created_by'];
    }
    unset($voucher);

    ak_api_json(200, ['ok' => true, 'data' => [
        'vouchers' => $vouchers,
        'cash_accounts' => $cashAccounts,
        'can_issue' => ak_voucher_can('issue', $role),
        'can_void' => ak_voucher_can('void', $role),
        'limit' => 300,
    ]]);
} catch (Throwable $e) {
    error_log('Mobile voucher register failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'voucher_list_failed', 'message' => 'The voucher register could not be loaded.']]);
}
