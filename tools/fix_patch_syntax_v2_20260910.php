<?php
/** One-time repair for patch_disbursements_integrity_20260910.php. */
$file = __DIR__ . '/patch_disbursements_integrity_20260910.php';
$source = file_get_contents($file);
if ($source === false) {
    fwrite(STDERR, "ERROR: cannot read patch script.\n");
    exit(1);
}

$old = '$uiNew = "<?php if ($canManage && $viewBatch[\'status\'] === \'received\'): ?>";';
$new = '$uiNew = \'<?php if ($canManage && $viewBatch["status"] === "received"): ?>\';';
if (substr_count($source, $old) !== 1) {
    fwrite(STDERR, "ERROR: UI interpolation anchor not found exactly once.\n");
    exit(1);
}
$source = str_replace($old, $new, $source);

$old = '    "if (!$b || $b[\'status\'] !== \'received\')",';
$new = '    \'if (!$b || $b["status"] !== "received")\',';
if (substr_count($source, $old) !== 1) {
    fwrite(STDERR, "ERROR: final-check interpolation anchor not found exactly once.\n");
    exit(1);
}
$source = str_replace($old, $new, $source);

// Match the application's existing journal-entry INSERT shape; do not assume created_at exists.
$old = '        (entry_code, entry_date, description, reference_type, reference_id, status, created_by, created_at)\n        VALUES (?, ?, ?, ?, ?, \'posted\', ?, NOW())';
$new = '        (entry_code, entry_date, description, reference_type, reference_id, status, created_by)\n        VALUES (?, ?, ?, ?, ?, \'posted\', ?)';
$count = substr_count($source, $old);
if ($count < 1) {
    fwrite(STDERR, "ERROR: journal INSERT shape was not found.\n");
    exit(1);
}
$source = str_replace($old, $new, $source);

$old = '        (entry_code, entry_date, description, reference_type, reference_id, status, created_by, created_at)\n        VALUES (?, ?, ?, \'disbursement_item_reopen\', ?, \'posted\', ?, NOW())';
$new = '        (entry_code, entry_date, description, reference_type, reference_id, status, created_by)\n        VALUES (?, ?, ?, \'disbursement_item_reopen\', ?, \'posted\', ?)';
if (substr_count($source, $old) !== 1) {
    fwrite(STDERR, "ERROR: item-reopen INSERT shape was not found exactly once.\n");
    exit(1);
}
$source = str_replace($old, $new, $source);

// The original expected value is a Git blob SHA, not a raw file SHA.
$old = '$currentSha = hash(\'sha1\', $source);';
$new = '$currentSha = sha1("blob " . strlen($source) . "\\0" . $source);';
if (substr_count($source, $old) !== 1) {
    fwrite(STDERR, "ERROR: source SHA calculation was not found exactly once.\n");
    exit(1);
}
$source = str_replace($old, $new, $source);

if (file_put_contents($file, $source, LOCK_EX) === false) {
    fwrite(STDERR, "ERROR: cannot write repaired patch script.\n");
    exit(1);
}

echo "PATCH SYNTAX REPAIRED SUCCESSFULLY (v2)\n";
echo "Fixed remaining interpolation anchors, journal INSERT compatibility, and Git blob SHA validation.\n";
?>
