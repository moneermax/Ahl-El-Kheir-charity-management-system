<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)($user['raw_role_code'] ?? $user['role_code'] ?? '');
$allowedRoles = ['financial_manager', 'fm', 'supervisor', 'admin', 'vice_general_manager'];
if (!in_array($role, $allowedRoles, true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Fina collection report access is not permitted.']]);
}

function ak_fina_report_valid_date(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false
        && $date->format('Y-m-d') === $value
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
}

$from = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!ak_fina_report_valid_date($from) || !ak_fina_report_valid_date($to) || $from > $to) {
    ak_api_json(422, ['ok' => false, 'error' => ['code' => 'invalid_date_range', 'message' => 'Choose a valid date range with the start date on or before the end date.']]);
}

$where = ['fc.collection_date BETWEEN ? AND ?'];
$params = [$from, $to];
if ($role === 'supervisor') {
    $where[] = 'fc.created_by = ?';
    $params[] = (int)$user['id'];
}
$filter = implode(' AND ', $where);

try {
    $summary = dbFetchAll(
        "SELECT fc.currency_code, COUNT(*) AS total_count,
                COALESCE(SUM(fc.amount), 0) AS total_amount,
                SUM(fc.status = 'pending') AS pending_count,
                COALESCE(SUM(CASE WHEN fc.status = 'pending' THEN fc.amount ELSE 0 END), 0) AS pending_amount,
                SUM(fc.status = 'approved') AS approved_count,
                COALESCE(SUM(CASE WHEN fc.status = 'approved' THEN fc.amount ELSE 0 END), 0) AS approved_amount,
                SUM(fc.status = 'returned') AS returned_count,
                COALESCE(SUM(CASE WHEN fc.status = 'returned' THEN fc.amount ELSE 0 END), 0) AS returned_amount
         FROM fina_collections fc WHERE {$filter}
         GROUP BY fc.currency_code ORDER BY fc.currency_code",
        $params
    );
    $methods = dbFetchAll(
        "SELECT fc.payment_method, COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN fc.status = 'approved' THEN fc.amount ELSE 0 END), 0) AS total_amount,
                SUM(fc.status = 'pending') AS pending_count,
                SUM(fc.status = 'approved') AS approved_count,
                SUM(fc.status = 'returned') AS returned_count
         FROM fina_collections fc WHERE {$filter}
         GROUP BY fc.payment_method ORDER BY total_amount DESC, fc.payment_method",
        $params
    );
    $sources = dbFetchAll(
        "SELECT fs.source_type, COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN fc.status = 'approved' THEN fc.amount ELSE 0 END), 0) AS total_amount,
                SUM(fc.status = 'approved') AS approved_count,
                SUM(fc.status = 'returned') AS returned_count
         FROM fina_collections fc
         JOIN fina_sources fs ON fs.id = fc.fina_source_id
         WHERE {$filter}
         GROUP BY fs.source_type ORDER BY total_amount DESC, fs.source_type",
        $params
    );
    $reviewed = dbFetchOne(
        "SELECT COUNT(*) AS total_count,
                SUM(fc.status = 'approved') AS approved_count,
                SUM(fc.status = 'returned') AS returned_count,
                COALESCE(SUM(CASE WHEN fc.status = 'approved' THEN fc.amount ELSE 0 END), 0) AS approved_amount,
                COALESCE(SUM(CASE WHEN fc.status = 'returned' THEN fc.amount ELSE 0 END), 0) AS returned_amount
         FROM fina_collections fc WHERE {$filter}",
        $params
    ) ?: [];
    ak_api_json(200, ['ok' => true, 'data' => [
        'from' => $from,
        'to' => $to,
        'currency_code' => (string)APP_CURRENCY_CODE,
        'supervisor_own_only' => $role === 'supervisor',
        'summary' => $summary,
        'methods' => $methods,
        'sources' => $sources,
        'reviewed' => $reviewed,
    ]]);
} catch (Throwable $e) {
    error_log('Fina report API failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'report_unavailable', 'message' => 'The Fina report could not be loaded.']]);
}
