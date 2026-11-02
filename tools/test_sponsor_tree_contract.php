<?php
declare(strict_types=1);

/**
 * Database-free source contract for Sponsor Trees and shared orphan sponsorship isolation.
 */
$root = dirname(__DIR__);
$files = [
    'migration' => $root . '/database/migrations/2026-11-02_sponsor_trees.sql',
    'tree management' => $root . '/modules/sponsors/trees.php',
    'sidebar' => $root . '/includes/sidebar.php',
    'sponsor register' => $root . '/modules/sponsors/index.php',
    'sponsor profile' => $root . '/modules/sponsors/view.php',
    'sponsor edit' => $root . '/modules/sponsors/edit.php',
    'sponsorship creation' => $root . '/modules/sponsorships/create.php',
    'sponsorship lifecycle' => $root . '/modules/sponsorships/view.php',
    'orphan profile' => $root . '/modules/families/orphan_profile.php',
];
$sources = [];
foreach ($files as $label => $file) {
    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, "FAIL | cannot read {$label}" . PHP_EOL);
        exit(1);
    }
    $sources[$label] = $source;
}

$checks = [
    'Tree and member relationships are separate tables' =>
        str_contains($sources['migration'], 'CREATE TABLE IF NOT EXISTS sponsor_trees')
        && str_contains($sources['migration'], 'CREATE TABLE IF NOT EXISTS sponsor_tree_members'),
    'Tree records reuse sponsor entities and prohibit destructive sponsor deletion' =>
        str_contains($sources['migration'], 'FOREIGN KEY (sponsor_id) REFERENCES sponsors(id)')
        && str_contains($sources['migration'], 'ON UPDATE CASCADE ON DELETE RESTRICT'),
    'one individual may be a member of multiple trees' =>
        str_contains($sources['migration'], 'UNIQUE KEY uq_sponsor_tree_member (tree_id, member_sponsor_id)')
        && !str_contains($sources['migration'], 'UNIQUE KEY uq_sponsor_tree_member (member_sponsor_id)'),
    'membership rows are retained when ended' =>
        str_contains($sources['migration'], "status ENUM('active','inactive')")
        && str_contains($sources['tree management'], "status = ?, ended_at = ?, updated_by = ?")
        && !str_contains($sources['tree management'], 'DELETE FROM sponsor_tree_members'),
    'VGM owns Tree creation and member administration; assigned supervisors are read-only' =>
        str_contains($sources['tree management'], '$canManageTrees = in_array($role, [\'admin\', \'vice_general_manager\'], true)')
        && str_contains($sources['tree management'], "['create_tree', 'add_member', 'set_member_status', 'assign_supervisor']")
        && str_contains($sources['tree management'], "s.supervisor_id = ? AND s.is_manual_override = 1"),
    'Tree follow-up supervisor is mandatory and uses normal sponsor assignment history' =>
        str_contains($sources['tree management'], 'name="supervisor_id" required')
        && str_contains($sources['tree management'], 'recordSponsorAssignment($sponsorId, $supervisorId, $uid, \'tree_follow_up_assignment\', \'permanent\')')
        && str_contains($sources['tree management'], 'recordSponsorAssignment(')
        && str_contains($sources['tree management'], 'tree_follow_up_reassignment')
        && str_contains($sources['tree management'], "NOT IN ('departed', 'archived')"),
    'Tree sponsor edit preserves manual supervisor assignment and legal-entity type' =>
        str_contains($sources['sponsor edit'], '$isTreeSponsor')
        && str_contains($sources['sponsor edit'], '$isTreeSponsor && in_array($input[\'sponsor_type\'],[\'company\',\'organization\'],true)')
        && str_contains($sources['sponsor edit'], 'لا يمكن تحويل كفيل الشجرة إلى فرد')
        && str_contains($sources['sponsor edit'], '$assignedBy')
        && str_contains($sources['sponsor edit'], '$assignedAt')
        && str_contains($sources['sponsor edit'], 'if (!$isTreeSponsor)'),
    'Tree page and navigation expose the green Tree icon' =>
        str_contains($sources['tree management'], 'fa-tree text-success')
        && str_contains($sources['sidebar'], "'active' => 'sponsor_trees'")
        && str_contains($sources['sidebar'], "'icon' => 'fa-tree text-success'")
        && str_contains($sources['sponsor register'], 'fa-tree text-success'),
    'Tree entity is persisted as a normal legal-entity sponsor' =>
        str_contains($sources['tree management'], "['company', 'organization']")
        && str_contains($sources['tree management'], "INSERT INTO sponsors")
        && str_contains($sources['tree management'], "INSERT INTO sponsor_trees"),
    'optional-member Tree can be created empty' =>
        str_contains($sources['tree management'], '$treeType === \'required_members\' && !$memberIds'),
    'required-member Tree cannot lose its final active membership' =>
        str_contains($sources['tree management'], "tree_type'] === 'required_members'")
        && str_contains($sources['tree management'], '$activeCount <= 1'),
    'membership activation/deactivation never changes the individual sponsor status' =>
        str_contains($sources['tree management'], 'status = ?, ended_at = ?, updated_by = ?')
        && !str_contains($sources['tree management'], 'UPDATE sponsors SET status'),
    'member identity and membership are linked independently for future reporting' =>
        str_contains($sources['sponsor profile'], 'FROM sponsor_tree_members tm')
        && str_contains($sources['sponsor profile'], 'WHERE tm.member_sponsor_id = ?'),
    'shared sponsorship duplicate protection is per sponsor and orphan, not orphan-wide' =>
        str_contains($sources['sponsorship creation'], 'WHERE sponsor_id = ? AND child_id = ? AND status IN (\'active\',\'paused\')')
        && !str_contains($sources['sponsorship creation'], 'SELECT id FROM sponsorships WHERE child_id = ? AND status IN'),
    'shared sponsorship creation locks the orphan row before insert' =>
        str_contains($sources['sponsorship creation'], 'SELECT id FROM family_children WHERE id = ? FOR UPDATE'),
    'sponsorship lifecycle uses the same orphan-first lock order as creation' =>
        strpos($sources['sponsorship lifecycle'], 'SELECT id FROM family_children WHERE id = ? FOR UPDATE')
            < strpos($sources['sponsorship lifecycle'], 'SELECT id, child_id, status FROM sponsorships WHERE id = ? FOR UPDATE'),
    'orphan match status remains matched while any active or paused sponsorship remains' =>
        str_contains($sources['sponsorship lifecycle'], "status IN ('active','paused')")
        && str_contains($sources['sponsorship lifecycle'], '$remaining ? \'matched\' : $fallback')
        && str_contains($sources['orphan profile'], "SET match_status = 'matched' WHERE id = ?"),
    'Tree heading subtitle keeps the white-on-blue contrast' =>
        str_contains($sources['tree management'], '<p class="mb-2">')
        && !str_contains($sources['tree management'], '<p class="text-muted mb-2">'),
    'Tree management labels use Tree terminology instead of entity wording' =>
        !str_contains($sources['tree management'], 'الكيان')
        && str_contains($sources['tree management'], 'اسم الشجرة')
        && str_contains($sources['tree management'], '<th>الشجرة</th>'),
    'initial member picker supports search and gender/supervisor filters' =>
        str_contains($sources['tree management'], 'id="treeMemberSearch"')
        && str_contains($sources['tree management'], 'id="treeMemberGenderFilter"')
        && str_contains($sources['tree management'], 'id="treeMemberSupervisorFilter"'),
    'member search matches full-name prefixes including multiple words and sponsor-code text immediately while typing or deleting' =>
        str_contains($sources['tree management'], 'normalize(row.dataset.name).trim()')
        && str_contains($sources['tree management'], 'fullName.startsWith(query)')
        && str_contains($sources['tree management'], 'sponsorCode.includes(query)')
        && str_contains($sources['tree management'], 'search.addEventListener(\'input\', updatePicker)')
        && !str_contains($sources['tree management'], "normalize(row.dataset.name + ' ' + row.dataset.code)"),
    'member picker supports bulk selection and preserves checked state after validation errors' =>
        str_contains($sources['tree management'], 'id="treeMemberSelectVisible"')
        && str_contains($sources['tree management'], 'id="treeMemberClearVisible"')
        && str_contains($sources['tree management'], 'id="treeMemberClearAll"')
        && str_contains($sources['tree management'], '$submittedMemberIds, true')
        && str_contains($sources['tree management'], '.tree-member-option[hidden] { display: none !important; }'),
    'initial member picker no longer truncates eligible individuals at 500' =>
        str_contains($sources['tree management'], 'FROM sponsors WHERE sponsor_type = \'individual\' AND status = \'active\' ORDER BY full_name')
        && !str_contains($sources['tree management'], 'ORDER BY full_name LIMIT 500'),
];
$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' | ' . $label . PHP_EOL;
    if (!$passed) $failed++;
}
if ($failed > 0) {
    fwrite(STDERR, "Sponsor Tree contract failed: {$failed} check(s)." . PHP_EOL);
    exit(1);
}
echo 'PASS | sponsor Tree contract complete' . PHP_EOL;
