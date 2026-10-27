<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();

// Match modules/accounting/trial_balance.php exactly. Do not authorize through
// the normalized aliases (fm, finance, sudo, super_admin, gm, or vgm).
$rawRole = (string)($user['raw_role_code'] ?? '');
if (!in_array($rawRole, ['admin', 'financial_manager'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Trial-balance access is restricted to administrator and Financial Manager.',
    ]]);
}

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
if ($from !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $from)) $from = '';
if ($to !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $to)) $to = '';
if ($from !== '' && $to !== '' && $from > $to) {
    [$from, $to] = [$to, $from];
}

$where = "je.status='posted'";
$params = [];
if ($from !== '') {
    $where .= ' AND je.entry_date >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where .= ' AND je.entry_date <= ?';
    $params[] = $to;
}

try {
    $accounts = dbFetchAll(
        "SELECT a.id, a.code, a.name_ar, a.name_en, a.account_type,
                COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.debit ELSE 0 END),0) debit_total,
                COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.credit ELSE 0 END),0) credit_total
         FROM accounts a
         LEFT JOIN journal_lines jl ON jl.account_id=a.id
         LEFT JOIN journal_entries je ON je.id=jl.entry_id AND {$where}
         GROUP BY a.id, a.code, a.name_ar, a.name_en, a.account_type
         HAVING COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.debit ELSE 0 END),0) <> 0
             OR COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.credit ELSE 0 END),0) <> 0
         ORDER BY a.code",
        $params
    );

    $totals = dbFetchOne(
        "SELECT COALESCE(SUM(jl.debit),0) total_debit,
                COALESCE(SUM(jl.credit),0) total_credit,
                COUNT(DISTINCT je.id) journal_count,
                COUNT(jl.id) line_count
         FROM journal_lines jl
         JOIN journal_entries je ON je.id=jl.entry_id
         WHERE {$where}",
        $params
    ) ?: [];
} catch (Throwable $exception) {
    error_log('Mobile trial-balance read failed: ' . $exception->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'trial_balance_unavailable',
        'message' => 'Trial-balance data is temporarily unavailable.',
    ]]);
}

$totalDebit = (float)($totals['total_debit'] ?? 0);
$totalCredit = (float)($totals['total_credit'] ?? 0);
$difference = round($totalDebit - $totalCredit, 2);
$items = array_map(static fn(array $row): array => [
    'id' => (int)$row['id'],
    'code' => (string)$row['code'],
    'name_ar' => (string)$row['name_ar'],
    'name_en' => (string)($row['name_en'] ?? ''),
    'account_type' => (string)$row['account_type'],
    'debit_total' => (float)$row['debit_total'],
    'credit_total' => (float)$row['credit_total'],
], $accounts);

ak_api_json(200, [
    'ok' => true,
    'data' => [
        'from' => $from,
        'to' => $to,
        'accounts' => $items,
        'totals' => [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'difference' => $difference,
            'is_balanced' => $difference === 0.0,
            'journal_count' => (int)($totals['journal_count'] ?? 0),
            'line_count' => (int)($totals['line_count'] ?? 0),
        ],
    ],
]);
