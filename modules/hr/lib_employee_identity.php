<?php
declare(strict_types=1);

/**
 * Resolve the employee profile for the currently authenticated user.
 *
 * In this system the user account and employee profile represent the same
 * person. The employees.user_id relationship is the canonical link. The
 * exact-name fallback exists only for legacy employee rows that were created
 * before the account/profile link was preserved; it is used only when there
 * is exactly one matching employee profile for the user's full name.
 */
function hrGetEmployeeForUser(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $employee = dbFetchOne(
        "SELECT e.*
         FROM employees e
         WHERE e.user_id = ?
         LIMIT 1",
        [$userId]
    );

    if ($employee) {
        return $employee;
    }

    $employee = dbFetchOne(
        "SELECT e.*
         FROM employees e
         JOIN users u ON u.id = ?
         WHERE e.full_name = u.full_name
         LIMIT 2",
        [$userId]
    );

    if (!$employee) {
        return null;
    }

    // Do not guess when more than one employee profile has the same name.
    $matches = dbFetchAll(
        "SELECT e.id
         FROM employees e
         JOIN users u ON u.id = ?
         WHERE e.full_name = u.full_name
         LIMIT 2",
        [$userId]
    );

    if (count($matches) !== 1) {
        return null;
    }

    return $employee;
}
