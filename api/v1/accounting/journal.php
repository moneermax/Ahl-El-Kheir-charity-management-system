<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if (!in_array((string)$user['role_code'], ['admin', 'financial_manager', 'general_manager', 'vice_general_manager'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Journal access is not allowed for this account.',
    ]]);
}

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
foreach (['from' => $from, 'to' => $to] as $field => $date) {
    if ($date !== '' && (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))
    )) {
        ak_api_json(422, ['ok' => false, 'error' => [
            'code' => 'invalid_date',
            'message' => $field . ' must use YYYY-MM-DD format.',
        ]]);
    }
}
if ($from !== '' && $to !== '' && $from > $to) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'invalid_date_range',
        'message' => 'The start date must not be after the end date.',
    ]]);
}

$viewId = filter_var($_GET['view'] ?? null, FILTER_VALIDATE_INT);
if (isset($_GET['view']) && (!$viewId || $viewId < 1)) {
    ak_api_json(422, ['ok' => false, 'error' => [
        'code' => 'invalid_entry_id',
        'message' => 'A valid journal entry ID is required.',
    ]]);
}

$pdo = db();
if ($viewId) {
    $stmt = $pdo->prepare(
        'SELECT je.*, u.full_name AS creator
         FROM journal_entries je
         LEFT JOIN users u ON u.id = je.created_by
         WHERE je.id = ? LIMIT 1'
    );
    $stmt->execute([(int)$viewId]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entry) {
        ak_api_json(404, ['ok' => false, 'error' => [
            'code' => 'journal_entry_not_found',
            'message' => 'The journal entry was not found.',
        ]]);
    }

    $stmt = $pdo->prepare(
        'SELECT jl.id, jl.account_id, a.code AS account_code,
                a.name_ar AS account_name_ar, a.name_en AS account_name_en,
                jl.debit, jl.credit, jl.description
         FROM journal_lines jl
         JOIN accounts a ON a.id = jl.account_id
         WHERE jl.entry_id = ?
         ORDER BY jl.id ASC'
    );
    $stmt->execute([(int)$viewId]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debit = 0.0;
    $credit = 0.0;
    foreach ($lines as &$line) {
        $line['id'] = (int)$line['id'];
        $line['account_id'] = (int)$line['account_id'];
        $line['debit'] = round((float)$line['debit'], 2);
        $line['credit'] = round((float)$line['credit'], 2);
        $debit += $line['debit'];
        $credit += $line['credit'];
    }
    unset($line);

    $entry['id'] = (int)$entry['id'];
    $entry['created_by'] = $entry['created_by'] === null ? null : (int)$entry['created_by'];
    $entry['reference_id'] = $entry['reference_id'] === null ? null : (int)$entry['reference_id'];
    $entry['total_debit'] = round($debit, 2);
    $entry['total_credit'] = round($credit, 2);
    $entry['line_count'] = count($lines);
    $entry['is_balanced'] = count($lines) >= 2 && round($debit, 2) === round($credit, 2) && $debit > 0;

    ak_api_json(200, ['ok' => true, 'data' => ['entry' => $entry, 'lines' => $lines]]);
}

$sql = 'SELECT je.id, je.entry_code, je.entry_date, je.description,
               je.reference_type, je.reference_id, je.status, je.created_by,
               je.voided_at, je.voided_by, je.void_reason,
               u.full_name AS creator,
               (SELECT COALESCE(SUM(jl.debit), 0) FROM journal_lines jl WHERE jl.entry_id = je.id) AS total_debit,
               (SELECT COALESCE(SUM(jl.credit), 0) FROM journal_lines jl WHERE jl.entry_id = je.id) AS total_credit,
               (SELECT COUNT(*) FROM journal_lines jl WHERE jl.entry_id = je.id) AS line_count
        FROM journal_entries je
        LEFT JOIN users u ON u.id = je.created_by
        WHERE 1=1';
$params = [];
if ($from !== '') {
    $sql .= ' AND je.entry_date >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $sql .= ' AND je.entry_date <= ?';
    $params[] = $to;
}
$sql .= ' ORDER BY je.entry_date DESC, je.id DESC LIMIT 300';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($entries as &$entry) {
        $entry['id'] = (int)$entry['id'];
        $entry['created_by'] = $entry['created_by'] === null ? null : (int)$entry['created_by'];
        $entry['reference_id'] = $entry['reference_id'] === null ? null : (int)$entry['reference_id'];
        $entry['total_debit'] = round((float)$entry['total_debit'], 2);
        $entry['total_credit'] = round((float)$entry['total_credit'], 2);
        $entry['line_count'] = (int)$entry['line_count'];
        $entry['is_balanced'] = $entry['line_count'] >= 2
            && $entry['total_debit'] === $entry['total_credit']
            && $entry['total_debit'] > 0;
    }
    unset($entry);
    ak_api_json(200, ['ok' => true, 'data' => ['entries' => $entries, 'limit' => 300]]);
} catch (Throwable $e) {
    error_log('Mobile journal list failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'journal_list_failed',
        'message' => 'The journal entries could not be loaded.',
    ]]);
}
