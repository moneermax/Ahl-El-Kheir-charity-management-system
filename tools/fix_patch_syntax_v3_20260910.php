<?php
/**
 * One-time repair for patch_disbursements_integrity_20260910.php.
 * Repairs all known parser-sensitive interpolated source strings and aligns
 * the patch with the application's existing journal_entries INSERT pattern.
 */

$root = dirname(__DIR__);
$file = $root . '/tools/patch_disbursements_integrity_20260910.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: patch script was not found.\n");
    exit(1);
}

$source = file_get_contents($file);
if ($source === false) {
    fwrite(STDERR, "ERROR: unable to read patch script.\n");
    exit(1);
}

$original = $source;

// Replace the UI-anchor assignment with valid PHP source text.
$source = preg_replace(
    '/^\$uiOld\s*=\s*.*;$/m',
    '$uiOld = \'<?php if ($canManage && in_array($viewBatch["status"], [\'received\', \'returned\'])): ?>\';',
    $source,
    -1,
    $countOld
);
if ($countOld !== 1) {
    fwrite(STDERR, "ERROR: could not uniquely repair uiOld anchor. Found {$countOld} occurrence(s).\nNo changes were written.\n");
    exit(1);
}

$source = preg_replace(
    '/^\$uiNew\s*=\s*.*;$/m',
    '$uiNew = \'<?php if ($canManage && $viewBatch["status"] === \'received\'): ?>\';',
    $source,
    -1,
    $countNew
);
if ($countNew !== 1) {
    fwrite(STDERR, "ERROR: could not uniquely repair uiNew anchor. Found {$countNew} occurrence(s).\nNo changes were written.\n");
    exit(1);
}

// The patch script must search for source text containing PHP variables without
// interpolating them while the patch script itself is parsed.
$source = preg_replace(
    "/^\s*\"if \(!\$b \|\| \$b\['status'\] !== 'received'\)\",$/m",
    '    \'if (!$b || $b[\'status\'] !== \'received\')\',',
    $source,
    -1,
    $countRequired
);
if ($countRequired !== 1) {
    fwrite(STDERR, "ERROR: could not uniquely repair final required-check anchor. Found {$countRequired} occurrence(s).\nNo changes were written.\n");
    exit(1);
}

// Match the application's existing journal_entries INSERTs: created_at is not
// required by the current disbursement implementation, so do not assume it.
$source = str_replace(
    "(entry_code, entry_date, description, reference_type, reference_id, status, created_by, created_at)",
    "(entry_code, entry_date, description, reference_type, reference_id, status, created_by)",
    $source,
    $countColumns
);
$source = str_replace(
    "VALUES (?, ?, ?, ?, ?, 'posted', ?, NOW())",
    "VALUES (?, ?, ?, ?, ?, 'posted', ?)",
    $source,
    $countValues
);

// Correct Git blob SHA calculation for the original canonical file.
$source = preg_replace(
    '/\$currentSha\s*=\s*hash(\'sha1\',\s*\$source);/',
    '$currentSha = sha1("blob " . strlen($source) . "\\0" . $source);',
    $source,
    -1,
    $countSha
);
if ($countSha !== 1) {
    fwrite(STDERR, "ERROR: Git blob SHA calculation anchor not found exactly once. Found {$countSha}.\nNo changes were written.\n");
    exit(1);
}

if ($source === $original) {
    fwrite(STDERR, "ERROR: no changes were produced.\n");
    exit(1);
}

if (file_put_contents($file, $source, LOCK_EX) === false) {
    fwrite(STDERR, "ERROR: could not write repaired patch script.\n");
    exit(1);
}

echo "PATCH SCRIPT REPAIRED SUCCESSFULLY\n";
echo "UI anchors, final source check, journal INSERT shape, and Git blob SHA calculation repaired.\n";
