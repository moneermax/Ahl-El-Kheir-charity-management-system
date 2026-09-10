<?php
/** One-time launcher: fixes the bridge's Git blob SHA check, then executes it. */
$bridge = __DIR__ . '/patch_disbursements_integrity_20260910.php';
if (!is_file($bridge)) {
    fwrite(STDERR, "ERROR: patch bridge not found.\n");
    exit(1);
}
$source = file_get_contents($bridge);
if ($source === false) {
    fwrite(STDERR, "ERROR: unable to read patch bridge.\n");
    exit(1);
}
$old = "\$currentSha = hash('sha1', \$source);";
$new = "\$currentSha = sha1('blob ' . strlen(\$source) . \"\\0\" . \$source);";
if (substr_count($source, $old) === 1) {
    $source = str_replace($old, $new, $source);
    if (file_put_contents($bridge, $source, LOCK_EX) === false) {
        fwrite(STDERR, "ERROR: unable to prepare patch bridge.\n");
        exit(1);
    }
}
require $bridge;
