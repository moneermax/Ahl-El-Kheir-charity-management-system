<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/config/sponsor_assignments.php';

if (!function_exists('ak_api_list_transactions')) {
    /**
     * Query the existing transaction ledger with the mobile API's role scope.
     * The caller must validate the incoming date/status/search parameters first.
     */
    function ak_api_list_transactions(int $userId, string $role, string $q, string $status, string $from, string $to, int $requestedPage): array
    {
        $page = max(1, $requestedPage);
        $perPage = 50;
        $params = [];
        $where = [];

        if ($role === 'accountant_staff') {
            $where[] = 't.created_by = ?';
            $params[] = $userId;
        } elseif ($role === 'supervisor') {
            $scopeRows = dbFetchAll(
                'SELECT id, supervisor_id, first_letter_id, gender FROM sponsors WHERE supervisor_id = ? OR first_letter_id IN (SELECT letter_id FROM supervisor_letters WHERE supervisor_id = ?)',
                [$userId, $userId]
            );
            $ids = [];
            foreach ($scopeRows as $row) {
                if (supervisorCanAccessSponsor($userId, $row)) $ids[] = (int)$row['id'];
            }
            $ids = array_values(array_unique($ids));
            if (!$ids) {
                return ['items' => [], 'total' => 0, 'pages' => 1, 'page' => 1, 'posted_total' => 0];
            }
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "t.sponsor_id IN ($marks)";
            $params = array_merge($params, $ids);
        }

        if ($status !== '') {
            $where[] = 't.status = ?';
            $params[] = $status;
        }
        if ($from !== '') {
            $where[] = 't.transaction_date >= ?';
            $params[] = $from;
        }
        if ($to !== '') {
            $where[] = 't.transaction_date <= ?';
            $params[] = $to;
        }

        $sql = "SELECT t.id, t.transaction_code, t.transaction_date, t.transaction_type, t.amount,
                       t.payment_method, t.receipt_number, t.receipt_path, t.status, t.created_by,
                       s.full_name AS sponsor_name, f.mother_name AS family_name
                FROM transactions t
                LEFT JOIN sponsors s ON s.id = t.sponsor_id
                LEFT JOIN families f ON f.id = t.family_id";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $rows = dbFetchAll($sql . ' ORDER BY t.transaction_date DESC, t.id DESC', $params);

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $haystack = mb_strtolower(
                    (string)($row['sponsor_name'] ?? '') . ' ' .
                    (string)($row['family_name'] ?? '') . ' ' .
                    (string)$row['transaction_code'] . ' ' .
                    (string)($row['receipt_number'] ?? ''),
                    'UTF-8'
                );
                return mb_strpos($haystack, $needle, 0, 'UTF-8') !== false;
            }));
        }

        $postedTotal = 0.0;
        foreach ($rows as $row) {
            if ((string)$row['status'] === 'posted') $postedTotal += (float)$row['amount'];
        }
        $total = count($rows);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $rows = array_slice($rows, ($page - 1) * $perPage, $perPage);

        $items = array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'transaction_code' => (string)$row['transaction_code'],
            'transaction_date' => (string)$row['transaction_date'],
            'transaction_type' => (string)($row['transaction_type'] ?? ''),
            'amount' => (float)$row['amount'],
            'payment_method' => (string)($row['payment_method'] ?? ''),
            'receipt_number' => (string)($row['receipt_number'] ?? ''),
            'has_receipt' => (string)($row['receipt_path'] ?? '') !== '',
            'status' => (string)$row['status'],
            'sponsor_name' => (string)($row['sponsor_name'] ?? ''),
            'family_name' => (string)($row['family_name'] ?? ''),
        ], $rows);

        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page, 'posted_total' => $postedTotal];
    }
}
