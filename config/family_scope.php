<?php
declare(strict_types=1);

/**
 * Centralized family-level authorization for operational roles.
 *
 * A supervisor can access a family when either:
 * 1) the family is explicitly assigned to that supervisor, or
 * 2) the family has a sponsorship whose sponsor is explicitly assigned to
 *    that supervisor, or
 * 3) the family has a sponsorship whose sponsor falls under the supervisor's
 *    first-letter + gender responsibility matrix.
 *
 * Nannies remain restricted to their directly assigned families. Other roles
 * already authorized by their module guards are not narrowed by this helper.
 */
function ak_family_user_in_scope(array $family, string $role, int $userId): bool
{
    if ($role === 'nanny') return (int)($family['nanny_id'] ?? 0) === $userId;

    if ($role === 'supervisor') {
        $familyId = (int)($family['id'] ?? 0);
        if ($familyId <= 0) return false;

        /* Direct family assignment always grants access. */
        if ((int)($family['supervisor_id'] ?? 0) === $userId) return true;

        /*
         * Explicit sponsor assignment is authoritative. A supervisor who is
         * responsible for a sponsor must be able to see every family/orphan
         * connected to that sponsor, regardless of the family's own
         * supervisor assignment.
         */
        $linkedSponsor = dbFetchOne(
            "SELECT sp.id
             FROM sponsorships s
             JOIN family_children fc ON fc.id = s.child_id
             JOIN sponsors sp ON sp.id = s.sponsor_id
             WHERE fc.family_id = ?
               AND sp.supervisor_id = ?
             LIMIT 1",
            [$familyId, $userId]
        );

        if (!empty($linkedSponsor)) return true;

        /*
         * Matrix responsibility is checked in PHP rather than comparing the
         * database strings directly. This deliberately avoids collation
         * differences between supervisor_letters.gender and sponsors.gender
         * and accepts the same male/female/Arabic/both values used elsewhere.
         */
        $matrixSponsors = dbFetchAll(
            "SELECT sp.gender AS sponsor_gender, sl.gender AS matrix_gender
             FROM sponsorships s
             JOIN family_children fc ON fc.id = s.child_id
             JOIN sponsors sp ON sp.id = s.sponsor_id
             JOIN supervisor_letters sl
               ON sl.supervisor_id = ?
              AND sl.letter_id = sp.first_letter_id
             WHERE fc.family_id = ?",
            [$userId, $familyId]
        );

        foreach ($matrixSponsors as $row) {
            $sponsorGender = strtolower(trim((string)($row['sponsor_gender'] ?? '')));
            $sponsorGender = match ($sponsorGender) {
                'm', 'male', 'ذكر' => 'male',
                'f', 'female', 'أنثى', 'انثى' => 'female',
                default => 'other',
            };

            $matrixGender = strtolower(trim((string)($row['matrix_gender'] ?? '')));
            $matrixGender = match ($matrixGender) {
                'm', 'male', 'ذكر' => 'male',
                'f', 'female', 'أنثى', 'انثى' => 'female',
                'both', 'all', 'كلاهما', 'الكل' => 'both',
                default => 'other',
            };

            if ($matrixGender === 'both' || $matrixGender === $sponsorGender) {
                return true;
            }
        }

        return false;
    }

    return true;
}

/**
 * Enforce the family record boundary on direct-ID family/child routes.
 * Supervisor access includes both explicit family assignment and families
 * related to sponsors inside the supervisor's responsibility matrix.
 */
function ak_enforce_family_request_scope(): void
{
    if (!class_exists('Session') || !Session::isLoggedIn()) return;
    $script = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $protectedScripts = ['documents.php', 'edit.php', 'view.php', 'orphan_form.php', 'orphan_profile.php'];
    if (!in_array($script, $protectedScripts, true)) return;
    $role = Session::getUserRole();
    if (!in_array($role, ['supervisor', 'nanny'], true)) return;

    $familyId = 0;
    if ($script === 'documents.php' || $script === 'edit.php' || $script === 'view.php') {
        $familyId = (int)($_GET['id'] ?? $_POST['id'] ?? $_POST['family_id'] ?? 0);
    } else {
        $childId = (int)($_GET['child'] ?? $_GET['id'] ?? $_GET['photo'] ?? 0);
        if ($childId > 0) {
            $child = dbFetchOne("SELECT family_id FROM family_children WHERE id = ?", [$childId]);
            if ($child) $familyId = (int)$child['family_id'];
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $familyId = (int)($_POST['family_id'] ?? 0);
        }
    }
    if ($familyId <= 0) return;
    $family = dbFetchOne("SELECT id, nanny_id, supervisor_id FROM families WHERE id = ?", [$familyId]);
    if (!$family) return;
    if (!ak_family_user_in_scope($family, $role, Session::getUserId())) {
        flash('error', 'هذه الأسرة ليست ضمن نطاقك.');
        redirect('modules/families/index.php');
        exit();
    }
}
