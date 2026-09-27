<?php
declare(strict_types=1);

/**
 * Resolve the employee profile for the currently authenticated user.
 *
 * In this system the user account and employee profile represent the same
 * person. employees.user_id is the canonical relationship.
 *
 * Legacy employee rows may have a missing user_id, so the resolver also
 * supports an exact full-name match against the authenticated user's
 * users.full_name. A fallback is accepted only when exactly one employee
 * profile matches; ambiguous matches are never guessed.
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

    // Keep the compatibility path limited to the verified users.full_name
    // field. Do not depend on optional/unverified users.email or users.phone
    // columns, because an SQL exception here would hide the employee links.
    $user = dbFetchOne(
        "SELECT full_name
         FROM users
         WHERE id = ?
         LIMIT 1",
        [$userId]
    );

    $fullName = trim((string)($user['full_name'] ?? ''));
    if ($fullName === '') {
        return null;
    }

    $matches = dbFetchAll(
        "SELECT e.*
         FROM employees e
         WHERE TRIM(e.full_name) = TRIM(?)
         LIMIT 2",
        [$fullName]
    );

    // Never guess when more than one employee profile could represent
    // the authenticated user.
    if (count($matches) !== 1) {
        return null;
    }

    return $matches[0];
}
