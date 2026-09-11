<?php
/**
 * One-time local patch for modules/accounting/disbursements.php.
 *
 * Run from the repository root with:
 *   php tools/apply_disbursement_auth_hardening_20260911.php
 *
 * The script is source-version guarded and creates a backup before changing
 * anything. It does not modify the database.
 */

$root = dirname(__DIR__);
$file = $root . '/modules/accounting/disbursements.php';
if (!is_file($file)) {
    fwrite(STDERR, "ERROR: disbursements.php was not found.\n");
    exit(1);
}
$source = file_get_contents($file);
if ($source === false) {
    fwrite(STDERR, "ERROR: unable to read disbursements.php.\n");
    exit(1);
}
$expectedSha = '0d8d6c094f838b3d2260513070cf59fb727113a9';
$currentSha = sha1('blob ' . strlen($source) . "\0" . $source);
if ($currentSha !== $expectedSha) {
    fwrite(STDERR, "ERROR: unexpected source version.\nExpected: {$expectedSha}\nActual:   {$currentSha}\nNo changes were made.\n");
    exit(1);
}
$backup = $file . '.before_auth_hardening_20260911.bak';
if (!file_exists($backup) && file_put_contents($backup, $source, LOCK_EX) === false) {
    fwrite(STDERR, "ERROR: could not create backup.\n");
    exit(1);
}

function replace_once(string &$source, string $old, string $new, string $label): void
{
    $count = substr_count($source, $old);
    if ($count !== 1) {
        throw new RuntimeException($label . ': expected 1 occurrence, found ' . $count);
    }
    $source = str_replace($old, $new, $source);
}

try {
    replace_once(
        $source,
        <<<'OLD'
$canManage = in_array($role, ['admin','general_manager','financial_manager','accountant', 'accountant_staff'], true);
OLD,
        <<<'NEW'
$isAccountantStaff = ($role === 'accountant_staff');
$canManage = in_array($role, ['admin','general_manager','financial_manager','accountant'], true);
$canManageAssigned = $canManage || $isAccountantStaff;
NEW,
        'role permissions'
    );

    replace_once($source, <<<'OLD'
if (!$isNanny && !$canManage) {
OLD, <<<'NEW'
if (!$isNanny && !$canManageAssigned) {
NEW, 'access guard');

    replace_once($source, <<<'OLD'
if ($canManage && isset($_POST['create_batch'])) {
OLD, <<<'NEW'
if ($canManageAssigned && isset($_POST['create_batch'])) {
NEW, 'create guard');

    replace_once($source, <<<'OLD'
                if ((int)$groupData['verified_families'] === 0) {
                    $skipped++;
                    $skip_reasons[] = "المجموعة ID {$gid}: لا توجد عائلات موثقة بالكامل (3 خانات) لهذا الشهر.";
                    continue;
                }
OLD, <<<'NEW'
                if ($isAccountantStaff && !dbFetchOne("SELECT 1 FROM accountant_nanny_assignments WHERE accountant_id = ? AND nanny_id = ?", [$uid, (int)$groupData['nanny_id']])) {
                    $skipped++;
                    $skip_reasons[] = "المجموعة ID {$gid}: ليست ضمن المجموعات المسندة إليك.";
                    continue;
                }
                if ((int)$groupData['verified_families'] === 0) {
                    $skipped++;
                    $skip_reasons[] = "المجموعة ID {$gid}: لا توجد عائلات موثقة بالكامل (3 خانات) لهذا الشهر.";
                    continue;
                }
NEW, 'create assignment check');

    replace_once($source, <<<'OLD'
if ($canManage && isset($_POST['reopen_batch'])) {
OLD, <<<'NEW'
if ($canManageAssigned && isset($_POST['reopen_batch'])) {
NEW, 'reopen batch guard');

    replace_once($source, <<<'OLD'
        if (!$b || !in_array($b['status'], ['received', 'returned'], true)) {
            flash('error', 'لا يمكن إعادة فتح هذه الدفعة من حالتها الحالية.');
        } elseif ($reason === '') {
OLD, <<<'NEW'
        if (!$b || !in_array($b['status'], ['received', 'returned'], true)) {
            flash('error', 'لا يمكن إعادة فتح هذه الدفعة من حالتها الحالية.');
        } elseif ($isAccountantStaff && !dbFetchOne("SELECT 1 FROM accountant_nanny_assignments WHERE accountant_id = ? AND nanny_id = ?", [$uid, (int)$b['nanny_id']])) {
            flash('error', 'هذه الدفعة ليست ضمن الدفعات المسندة إليك.');
        } elseif ($reason === '') {
NEW, 'reopen batch assignment check');

    replace_once($source, <<<'OLD'
if ($canManage && isset($_POST['reopen_item'])) {
OLD, <<<'NEW'
if ($canManageAssigned && isset($_POST['reopen_item'])) {
NEW, 'reopen item guard');

    replace_once($source, <<<'OLD'
SELECT i.*, d.id AS disbursement_id, d.status AS dstatus, d.group_id, d.month
OLD, <<<'NEW'
SELECT i.*, d.id AS disbursement_id, d.nanny_id, d.status AS dstatus, d.group_id, d.month
NEW, 'reopen item nanny id');

    replace_once($source, <<<'OLD'
        if (!$item || !in_array($item['status'], ['paid', 'returned'], true)) {
            flash('error', 'لا يمكن إعادة فتح هذا السجل من حالته الحالية.');
        } elseif ($reason === '') {
OLD, <<<'NEW'
        if (!$item || !in_array($item['status'], ['paid', 'returned'], true)) {
            flash('error', 'لا يمكن إعادة فتح هذا السجل من حالته الحالية.');
        } elseif ($isAccountantStaff && !dbFetchOne("SELECT 1 FROM accountant_nanny_assignments WHERE accountant_id = ? AND nanny_id = ?", [$uid, (int)$item['nanny_id']])) {
            flash('error', 'هذا السجل ليس ضمن الدفعات المسندة إليك.');
        } elseif ($reason === '') {
NEW, 'reopen item assignment check');

    replace_once($source, <<<'OLD'
if ($canManage) {
    // Shared default month
OLD, <<<'NEW'
if ($canManageAssigned) {
    // Shared default month
NEW, 'group loading guard');

    replace_once($source, '($canManage && $anyReopenableItem)', '($canManageAssigned && $anyReopenableItem)', 'item action column');
    replace_once($source, <<<'OLD'
elseif ($canManage && in_array($it['status'], ['paid', 'returned'], true))
OLD, <<<'NEW'
elseif ($canManageAssigned && in_array($it['status'], ['paid', 'returned'], true))
NEW, 'item reopen ui');
    replace_once($source, <<<'OLD'
if ($canManage && in_array($viewBatch['status'], ['received', 'returned']))
OLD, <<<'NEW'
if ($canManageAssigned && in_array($viewBatch['status'], ['received', 'returned']))
NEW, 'batch reopen ui');
    replace_once($source, <<<'OLD'
if ($canManage && !$viewId)
OLD, <<<'NEW'
if ($canManageAssigned && !$viewId)
NEW, 'create ui');

    replace_once($source, <<<'OLD'
    WHERE og.verification_status IN ('submitted', 'approved', 'pending')
    GROUP BY og.id, og.group_name, u.full_name, nga.nanny_id
OLD, <<<'NEW'
    WHERE og.verification_status IN ('submitted', 'approved', 'pending')
      AND (
          ? = 0
          OR EXISTS (
              SELECT 1
              FROM accountant_nanny_assignments ana
              WHERE ana.accountant_id = ?
                AND ana.nanny_id = nga.nanny_id
          )
      )
    GROUP BY og.id, og.group_name, u.full_name, nga.nanny_id
NEW, 'group assignment filter');

    replace_once($source, <<<'OLD'
    ", [$previewMonth]);
OLD, <<<'NEW'
    ", [$previewMonth, $isAccountantStaff ? $uid : 0, $isAccountantStaff ? $uid : 0]);
NEW, 'group query params');

    replace_once($source, 'SELECT jl.account_id, a.code, a.name', 'SELECT jl.account_id, a.code', 'return expense account select');
    replace_once($source, 'SELECT id, code, name FROM accounts WHERE code = ? LIMIT 1', 'SELECT id, code FROM accounts WHERE code = ? LIMIT 1', 'return fallback account select');
    replace_once($source, "SELECT id, code, name FROM accounts WHERE code = '1100' LIMIT 1", "SELECT id, code FROM accounts WHERE code = '1100' LIMIT 1", 'return cash account select');

    if (file_put_contents($file, $source, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write patched disbursements.php.');
    }

    passthru(PHP_BINARY . ' -l ' . escapeshellarg($file), $lintExit);
    if ($lintExit !== 0) {
        copy($backup, $file);
        throw new RuntimeException('PHP syntax check failed; original file restored.');
    }

    echo "PASS: disbursements.php authorization hardening applied and PHP syntax is valid.\n";
    echo "Backup: {$backup}\n";
} catch (Throwable $e) {
    @copy($backup, $file);
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
