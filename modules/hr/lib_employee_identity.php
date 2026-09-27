<?php
declare(strict_types=1);

/**
 * Resolve the employee profile for the currently authenticated user.
 *
 * In this system the user account and employee profile represent the same
 * person. employees.user_id is the canonical relationship.
 *
 * Legacy employee rows may have a missing user_id, so the resolver also
 * matches the authenticated user's identity fields. A fallback is accepted
 * only when exactly one employee profile matches; ambiguous matches are
 * never guessed.
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

    $user = dbFetchOne(
        "SELECT full_name, email, phone
         FROM users
         WHERE id = ?
         LIMIT 1",
        [$userId]
    );

    if (!$user) {
        return null;
    }

    $conditions = [];
    $params = [];

    $fullName = trim((string)($user['full_name'] ?? ''));
    if ($fullName !== '') {
        $conditions[] = "TRIM(e.full_name) = TRIM(?)";
        $params[] = $fullName;
    }

    $email = trim((string)($user['email'] ?? ''));
    if ($email !== '') {
        $conditions[] = "TRIM(e.email) = TRIM(?)";
        $params[] = $email;
    }

    $phone = trim((string)($user['phone'] ?? ''));
    if ($phone !== '') {
        $conditions[] = "TRIM(e.phone) = TRIM(?)";
        $params[] = $phone;
    }

    if (!$conditions) {
        return null;
    }

    $matches = dbFetchAll(
        "SELECT e.*
         FROM employees e
         WHERE " . implode(' OR ', $conditions) . "
         LIMIT 2",
        $params
    );

    // Never guess when more than one employee profile could represent
    // the authenticated user.
    if (count($matches) !== 1) {
        return null;
    }

    return $matches[0];
}
