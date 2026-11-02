<?php
declare(strict_types=1);

/**
 * Database-free source contract for Sponsor Trees and shared orphan sponsorship isolation.
 */
$root = dirname(__DIR__);
$files = [
    'migration' => $root . '/database/migrations/2026-11-02_sponsor_trees.sql',
    'tree management' => $root . '/modules/sponsors/trees.php',
    'sponsor profile' => $root . '/modules/sponsors/view.php',
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
    'Tree creation is restricted to authorized management roles' =>
        str_contains($sources['tree management'], "['admin', 'vice_general_manager']"),
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
    'orphan match status remains matched while any active or paused sponsorship remains' =>
        str_contains($sources['sponsorship lifecycle'], "status IN ('active','paused')")
        && str_contains($sources['sponsorship lifecycle'], '$remaining ? \'matched\' : $fallback')
        && str_contains($sources['orphan profile'], "SET match_status = 'matched' WHERE id = ?"),
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
