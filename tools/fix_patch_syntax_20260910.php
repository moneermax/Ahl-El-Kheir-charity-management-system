<?php
/**
 * One-time syntax repair for patch_disbursements_integrity_20260910.php.
 *
 * The integrity patch itself was correct in intent, but its UI anchor was placed
 * in a double-quoted PHP string, causing PHP interpolation of $viewBatch and a
 * parse error. This helper changes only that anchor to nowdoc literals.
 */

$root = dirname(__DIR__);
$file = $root . '/tools/patch_disbursements_integrity_20260910.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: integrity patch file was not found.\n");
    exit(1);
}

$source = file_get_contents($file);
if ($source === false) {
    fwrite(STDERR, "ERROR: unable to read integrity patch file.\n");
    exit(1);
}

$old = <<<'PHP'
$uiOld = "<?php if ($canManage && in_array($viewBatch['status'], ['received', 'returned'])): ?>";
$uiNew = "<?php if ($canManage && $viewBatch['status'] === 'received'): ?>";
PHP;

$new = <<<'PHP'
$uiOld = <<<'HTML'
<?php if ($canManage && in_array($viewBatch['status'], ['received', 'returned'])): ?>
HTML;
$uiNew = <<<'HTML'
<?php if ($canManage && $viewBatch['status'] === 'received'): ?>
HTML;
PHP;

$count = substr_count($source, $old);
if ($count !== 1) {
    fwrite(STDERR, "ERROR: expected exactly one faulty UI-anchor block; found {$count}. No changes made.\n");
    exit(1);
}

$source = str_replace($old, $new, $source);

if (file_put_contents($file, $source, LOCK_EX) === false) {
    fwrite(STDERR, "ERROR: unable to write repaired integrity patch.\n");
    exit(1);
}

fwrite(STDOUT, "PATCH SYNTAX REPAIRED SUCCESSFULLY\n");
fwrite(STDOUT, "Repaired only the UI-anchor string interpolation issue.\n");
?>
