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

/**
 * Enforce the same family record boundary on direct-ID family/child routes
 * that historically did not repeat the family/view.php scope check locally.
 *
 * This hook is intentionally limited to the affected routes. It runs after
 * Session::start() so the authenticated user and database helpers are ready.
 */
function ak_enforce_family_request_scope(): void
{
    if (!class_exists('Session') || !Session::isLoggedIn()) {
        return;
    }

    $script = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $protectedScripts = ['documents.php', 'orphan_form.php', 'orphan_profile.php'];
    if (!in_array($script, $protectedScripts, true)) {
        return;
    }

    $role = Session::getUserRole();
    if (!in_array($role, ['supervisor', 'nanny'], true)) {
        return;
    }

    $familyId = 0;

    if ($script === 'documents.php') {
        $familyId = (int)($_GET['id'] ?? 0);
    } else {
        $childId = (int)($_GET['child'] ?? $_GET['id'] ?? $_GET['photo'] ?? 0);
        if ($childId > 0) {
            $child = dbFetchOne("SELECT family_id FROM family_children WHERE id = ?", [$childId]);
            if ($child) {
                $familyId = (int)$child['family_id'];
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $familyId = (int)($_POST['family_id'] ?? 0);
        }
    }

    if ($familyId <= 0) {
        return;
    }

    $family = dbFetchOne(
        "SELECT id, nanny_id, supervisor_id, legacy_mother_first_letter FROM families WHERE id = ?",
        [$familyId]
    );
    if (!$family) {
        return;
    }

    if (!ak_family_user_in_scope($family, $role, Session::getUserId())) {
        flash('error', 'هذه الأسرة ليست ضمن نطاقك.');
        redirect('modules/families/index.php');
        exit();
    }
}
