<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if ((string)($user['raw_role_code'] ?? '') !== 'admin') {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Database browser access is restricted to the administrator role.',
    ]]);
}

try {
    $tableRows = dbFetchAll(
        "SELECT TABLE_NAME AS table_name
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME"
    );
    $tables = [];
    $names = [];
    $quote = chr(96);
    foreach ($tableRows as $tableRow) {
        $name = (string)$tableRow['table_name'];
        $quoted = $quote . str_replace($quote, $quote . $quote, $name) . $quote;
        $count = dbFetchOne("SELECT COUNT(*) AS row_count FROM " . $quoted);
        $tables[] = ['name' => $name, 'count' => (int)($count['row_count'] ?? 0)];
        $names[] = $name;
    }

    $selected = trim((string)($_GET['table'] ?? ''));
    $columns = [];
    $rows = [];
    if ($selected !== '') {
        if (!in_array($selected, $names, true)) {
            ak_api_json(404, ['ok' => false, 'error' => [
                'code' => 'table_not_found',
                'message' => 'The requested table does not exist in the current database.',
            ]]);
        }
        $quoted = $quote . str_replace($quote, $quote . $quote, $selected) . $quote;
        foreach (dbFetchAll("SHOW COLUMNS FROM " . $quoted) as $column) {
            $columns[] = [
                'name' => (string)$column['Field'],
                'type' => (string)$column['Type'],
                'nullable' => (string)$column['Null'],
                'key' => (string)$column['Key'],
                'default' => $column['Default'] === null ? null : (string)$column['Default'],
                'extra' => (string)$column['Extra'],
            ];
        }
        foreach (dbFetchAll("SELECT * FROM " . $quoted . " LIMIT 50") as $rawRow) {
            $row = [];
            foreach ($rawRow as $key => $value) {
                if (is_string($value)) {
                    if (!mb_check_encoding($value, 'UTF-8')) {
                        $value = '[binary data]';
                    } elseif (strlen($value) > 2000) {
                        $value = substr($value, 0, 2000) . '…';
                    }
                }
                $row[(string)$key] = $value;
            }
            $rows[] = $row;
        }
    }

    ak_api_json(200, ['ok' => true, 'data' => [
        'tables' => $tables,
        'selected_table' => $selected !== '' ? $selected : null,
        'columns' => $columns,
        'rows' => $rows,
        'row_limit' => 50,
    ]]);
} catch (Throwable $e) {
    error_log('Mobile database browser failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'database_browser_failed',
        'message' => 'The database browser could not load the requested information.',
    ]]);
}
