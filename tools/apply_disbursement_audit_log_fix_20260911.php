<?php
/**
 * One-time narrow patch for modules/accounting/disbursements.php.
 *
 * Fixes only disb_audit(): audit_log uses `action`, not `action_type`.
 * The script refuses to touch an unexpected source version and creates a backup.
 */

$root = dirname(__DIR__);
$file = $root . '/modules/accounting/disbursements.php';

if (!is_file($file)) {
    fwrite(STDERR, "ERROR: disbursements.php was not found.\n");
    exit(1);
}

$expectedSha = '92b808e1df79f10d8d2b419437f440eafea10193';

// Verify using Git's own blob hashing so the check matches `git hash-object` exactly.
$escapedFile = escapeshellarg($file);
$gitOutput = [];
$gitExitCode = 0;
exec("git -C " . escapeshellarg($root) . " hash-object {$escapedFile}", $gitOutput, $gitExitCode);
$currentSha = $gitOutput[0] ?? '';

if ($gitExitCode !== 0 || !preg_match('/^[0-9a-f]{40}$/', $currentSha)) {
    fwrite(STDERR, "ERROR: unable to verify disbursements.php with Git hash-object.\nNo changes were made.\n");
    exit(1);
}

if ($currentSha !== $expectedSha) {
    fwrite(STDERR, "ERROR: unexpected source version.\nExpected blob SHA: {$expectedSha}\nActual Git blob SHA: {$currentSha}\nNo changes were made.\n");
    exit(1);
}

$source = file_get_contents($file);
if ($source === false) {
    fwrite(STDERR, "ERROR: unable to read disbursements.php.\n");
    exit(1);
}

$old = 'INSERT INTO audit_log (user_id, action_type, entity_type, entity_id, new_values, ip_address, user_agent, created_at)';
$new = 'INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent, created_at)';

if (substr_count($source, $old) !== 1) {
    fwrite(STDERR, "ERROR: expected exactly one disb_audit action_type occurrence.\nNo changes were made.\n");
    exit(1);
}

$backup = $file . '.before_audit_log_fix_20260911.bak';
if (!file_exists($backup) && file_put_contents($backup, $source) === false) {
    fwrite(STDERR, "ERROR: could not create backup.\n");
    exit(1);
}

$patched = str_replace($old, $new, $source);

if ($patched === $source) {
    fwrite(STDERR, "ERROR: patch produced no change.\nNo changes were made.\n");
    exit(1);
}

if (file_put_contents($file, $patched) === false) {
    fwrite(STDERR, "ERROR: unable to write patched disbursements.php.\n");
    exit(1);
}

$verifySource = file_get_contents($file);
if ($verifySource === false) {
    fwrite(STDERR, "ERROR: unable to reread patched disbursements.php.\n");
    exit(1);
}

$verifyGitOutput = [];
$verifyGitExitCode = 0;
exec("git -C " . escapeshellarg($root) . " hash-object " . escapeshellarg($file), $verifyGitOutput, $verifyGitExitCode);
$patchedSha = $verifyGitOutput[0] ?? '';

if ($verifyGitExitCode !== 0 || substr_count($verifySource, $old) !== 0 || substr_count($verifySource, $new) !== 1) {
    fwrite(STDERR, "ERROR: post-patch verification failed.\n");
    exit(1);
}

echo "PASS: disbursements.php audit_log column fixed.\n";
echo "Old blob SHA: {$expectedSha}\n";
echo "New local blob SHA: {$patchedSha}\n";
echo "Backup: {$backup}\n";
echo "Only action_type -> action in disb_audit() was changed.\n";
