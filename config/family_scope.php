<?php
declare(strict_types=1);

/**
 * Centralized family-level authorization for operational roles.
 *
 * A supervisor can access a family only when that family is explicitly
 * assigned to the supervisor through families.supervisor_id.
 *
 * Sponsor responsibility is intentionally separate from family-management
 * scope. A supervisor's sponsor letter+gender matrix must never grant access
 * to an otherwise unassigned family.
 *
 * Nannies remain restricted to their directly assigned families. Other roles
 * already authorized by their module guards are not narrowed by this helper.
 */
function ak_family_user_in_scope(array $family, string $role, int $userId): bool
{
    if ($role === 'nanny') return (int)($family['nanny_id'] ?? 0) === $userId;

    if ($role === 'supervisor') {
        return (int)($family['supervisor_id'] ?? 0) === $userId;
    }

    return true;
}

/**
 * Enforce the family record boundary on direct-ID family/child routes.
 * Supervisor family access is based only on explicit family assignment.
 * Sponsor ownership is governed separately by the sponsor letter+gender
 * responsibility matrix and does not grant family-management access.
 *
 * IMPORTANT: this helper is loaded globally and Session::start() invokes it
 * for every authenticated request. Therefore route detection MUST be scoped
 * to the actual families module. A generic basename() check would incorrectly
 * treat unrelated routes such as modules/sponsors/view.php?id=942 as a family
 * request and interpret the sponsor ID as a family ID.
 */
function ak_enforce_family_request_scope(): void
{
    if (!class_exists('Session') || !Session::isLoggedIn()) return;

    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $familyModulePath = rtrim(APP_BASE_PATH, '/') . '/modules/families/';

    /* Only the families module is allowed to trigger this family-ID guard. */
    if (strpos($scriptName, $familyModulePath) !== 0) return;

    $script = basename($scriptName);
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
