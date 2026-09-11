<?php
/** One-time repair for patch_disbursements_integrity_20260910.php. */
$root = dirname(__DIR__);
$file = $root . '/tools/patch_disbursements_integrity_20260910.php';
if (!is_file($file)) { fwrite(STDERR, "ERROR: patch script was not found.\n"); exit(1); }
$source = file_get_contents($file);
if ($source === false) { fwrite(STDERR, "ERROR: unable to read patch script.\n"); exit(1); }
$original = $source;

$uiOldBlock = <<<'PHP'
$uiOld = <<<'HTML'
<?php if ($canManage && in_array($viewBatch['status'], ['received', 'returned'])): ?>
HTML;
PHP;
$uiOldFixed = <<<'PHP'
$uiOld = <<<'HTML'
<?php if ($canManage && in_array($viewBatch["status"], ['received', 'returned'])): ?>
HTML;
PHP;
if (substr_count($source, $uiOldBlock) !== 1) { fwrite(STDERR, "ERROR: could not uniquely locate uiOld heredoc block.\nNo changes were written.\n"); exit(1); }
$source = str_replace($uiOldBlock, $uiOldFixed, $source);

$uiNewBlock = <<<'PHP'
$uiNew = <<<'HTML'
<?php if ($canManage && $viewBatch['status'] === 'received'): ?>
HTML;
PHP;
$uiNewFixed = <<<'PHP'
$uiNew = <<<'HTML'
<?php if ($canManage && $viewBatch["status"] === 'received'): ?>
HTML;
PHP;
if (substr_count($source, $uiNewBlock) !== 1) { fwrite(STDERR, "ERROR: could not uniquely locate uiNew heredoc block.\nNo changes were written.\n"); exit(1); }
$source = str_replace($uiNewBlock, $uiNewFixed, $source);

$requiredOld = <<<'PHP'
    "if (!$b || $b['status'] !== 'received')",
PHP;
$requiredNew = <<<'PHP'
    'if (!$b || $b['status'] !== 'received')',
PHP;
if (substr_count($source, $requiredOld) !== 1) { fwrite(STDERR, "ERROR: could not uniquely locate final required-check anchor.\nNo changes were written.\n"); exit(1); }
$source = str_replace($requiredOld, $requiredNew, $source);

$source = str_replace("(entry_code, entry_date, description, reference_type, reference_id, status, created_by, created_at)", "(entry_code, entry_date, description, reference_type, reference_id, status, created_by)", $source, $countColumns);
$source = str_replace("VALUES (?, ?, ?, ?, ?, 'posted', ?, NOW())", "VALUES (?, ?, ?, ?, ?, 'posted', ?)", $source, $countValues);

$shaOld = <<<'PHP'
$currentSha = hash('sha1', $source);
PHP;
$shaNew = <<<'PHP'
$currentSha = sha1("blob " . strlen($source) . "\0" . $source);
PHP;
if (substr_count($source, $shaOld) !== 1) { fwrite(STDERR, "ERROR: Git blob SHA calculation anchor not found exactly once.\nNo changes were written.\n"); exit(1); }
$source = str_replace($shaOld, $shaNew, $source);

if ($source === $original) { fwrite(STDERR, "ERROR: no changes were produced.\n"); exit(1); }
if (file_put_contents($file, $source, LOCK_EX) === false) { fwrite(STDERR, "ERROR: could not write repaired patch script.\n"); exit(1); }
echo "PATCH SCRIPT REPAIRED SUCCESSFULLY\n";
echo "Repaired the actual heredoc UI anchors, final source check, journal INSERT shape, and Git blob SHA calculation.\n";
