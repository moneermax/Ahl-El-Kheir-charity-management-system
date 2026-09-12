<?php
declare(strict_types=1);

/**
 * Centralized family-level authorization for operational roles.
 *
 * This preserves the existing family/view.php and family/edit.php supervisor
 * rule: direct family assignment OR an assigned supervisor letter matching
 * the family's legacy mother first letter. Nannies remain restricted to their
 * directly assigned families. Other already-authorized roles are not narrowed
 * by this helper; their module-level role guards remain authoritative.
 */
function ak_family_user_in_scope(array $family, string $role, int $userId): bool
{
    if ($role === 'nanny') {
        return (int)($family['nanny_id'] ?? 0) === $userId;
    }

    if ($role !== 'supervisor') {
        return true;
    }

    if ((int)($family['supervisor_id'] ?? 0) === $userId) {
        return true;
    }

    $legacyCode = normalize_arabic_letter((string)($family['legacy_mother_first_letter'] ?? ''));
    if ($legacyCode === '') {
        return false;
    }

    $rows = dbFetchAll(
        "SELECT l.code
         FROM supervisor_letters sl
         JOIN letters l ON l.id = sl.letter_id
         WHERE sl.supervisor_id = ?",
        [$userId]
    );

    foreach ($rows as $row) {
        if (normalize_arabic_letter((string)($row['code'] ?? '')) === $legacyCode) {
            return true;
        }
    }

    return false;
}
