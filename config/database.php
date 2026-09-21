<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);

            if (defined('APP_ENV') && APP_ENV === 'development') {
                exit('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            }

            exit('Database connection failed. Please contact the system administrator.');
        }
    }

    return $pdo;
}

function dbFetchOne(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function dbFetchAll(string $sql, array $params = []): array
{
    /*
     * Compatibility normalization for the sponsor payment history query.
     * transactions and sponsor_payments were created with different
     * utf8mb4 collations. MySQL therefore rejects their textual UNION
     * columns before the result can be returned. Keep the fix local to
     * this known history query instead of changing table/database
     * collations globally.
     */
    if (
        strpos($sql, "SELECT 'transaction' AS src") !== false &&
        strpos($sql, 'FROM transactions WHERE sponsor_id = ? UNION ALL') !== false &&
        strpos($sql, "FROM sponsor_payments WHERE sponsor_id = ?") !== false
    ) {
        $sql = "SELECT 'transaction' COLLATE utf8mb4_unicode_ci AS src,
                       id,
                       transaction_date AS dt,
                       amount,
                       transaction_type COLLATE utf8mb4_unicode_ci AS type,
                       status COLLATE utf8mb4_unicode_ci AS status,
                       receipt_path COLLATE utf8mb4_unicode_ci AS receipt_path,
                       unified_receipt_path COLLATE utf8mb4_unicode_ci AS unified_receipt_path,
                       payment_period COLLATE utf8mb4_unicode_ci AS payment_period,
                       purpose_note COLLATE utf8mb4_unicode_ci AS purpose_note,
                       purpose COLLATE utf8mb4_unicode_ci AS purpose,
                       0.00 AS admin_fee_percent,
                       created_by
                FROM transactions
                WHERE sponsor_id = ?
                UNION ALL
                SELECT 'pending' COLLATE utf8mb4_unicode_ci AS src,
                       id,
                       created_at AS dt,
                       amount,
                       payment_type COLLATE utf8mb4_unicode_ci AS type,
                       status COLLATE utf8mb4_unicode_ci AS status,
                       receipt_file_path COLLATE utf8mb4_unicode_ci AS receipt_path,
                       unified_receipt_path COLLATE utf8mb4_unicode_ci AS unified_receipt_path,
                       payment_period COLLATE utf8mb4_unicode_ci AS payment_period,
                       purpose_note COLLATE utf8mb4_unicode_ci AS purpose_note,
                       payment_type COLLATE utf8mb4_unicode_ci AS purpose,
                       0.00 AS admin_fee_percent,
                       supervisor_id AS created_by
                FROM sponsor_payments
                WHERE sponsor_id = ?
                ORDER BY dt DESC
                LIMIT 50";
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function dbExecute(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
}

function dbLastInsertId(): string
{
    return db()->lastInsertId();
}