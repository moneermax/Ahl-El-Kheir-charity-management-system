<?php
/**
 * CLI-only, ONE-TIME data correction.
 *
 * Before this fix, final project approval posted a journal entry that recognized the entire
 * approved budget as an EXPENSE (debiting account 5100) and deducted it from cash/bank/wallet
 * immediately — before any money was actually spent. Real per-expense postings later deducted
 * the same cash again as it was genuinely spent, double-counting the outflow.
 *
 * This script finds every such entry still in effect (posted, never voided) and reverses it
 * with a balanced, audited reversal entry — the SAME safe pattern used everywhere else in this
 * system (see modules/accounting/lib_transaction_void.php): the original stays posted, exactly
 * as it happened, and a new entry cancels its effect. Nothing is deleted or altered in place.
 *
 * It does NOT touch project_expenses, project_funding_allocations, project_payment_evidence, or
 * project_approval status — those are unaffected by this bug and are left exactly as they are.
 *
 * Usage (from the project root):
 *   php tools/fix_project_approval_journals.php            (dry run: lists what would change)
 *   php tools/fix_project_approval_journals.php --apply     (actually reverses the entries)
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
$root = dirname(__DIR__);
require $root . '/config/config.php';
require $root . '/config/database.php';
require $root . '/config/functions.php';
require $root . '/config/session.php';
while (ob_get_level() > 0) ob_end_clean();

$apply = in_array('--apply', $argv ?? [], true);
$pdo = db();

$candidates = dbFetchAll(
    "SELECT je.id, je.entry_code, je.entry_date, je.reference_id AS project_id, p.name AS project_name
     FROM journal_entries je
     LEFT JOIN other_projects p ON p.id = je.reference_id
     WHERE je.reference_type = 'project' AND je.status = 'posted' AND je.voided_at IS NULL
     ORDER BY je.id"
);

if (!$candidates) {
    echo "Nothing to fix: no live project-approval journal entries found.\n";
    exit(0);
}

echo "Found " . count($candidates) . " project-approval journal entr" . (count($candidates) === 1 ? "y" : "ies") . " to reverse:\n";
foreach ($candidates as $row) {
    $lines = dbFetchAll(
        "SELECT a.code, a.name_ar, jl.debit, jl.credit FROM journal_lines jl JOIN accounts a ON a.id = jl.account_id WHERE jl.entry_id = ?",
        [(int)$row['id']]
    );
    printf("  #%d  %s  %s  project #%s (%s)\n", $row['id'], $row['entry_code'], $row['entry_date'], $row['project_id'], $row['project_name'] ?? '?');
    foreach ($lines as $line) {
        printf("      %s %-30s  Dr %12s  Cr %12s\n", $line['code'], mb_substr($line['name_ar'], 0, 30), number_format((float)$line['debit'], 2), number_format((float)$line['credit'], 2));
    }
}

if (!$apply) {
    echo "\nThis was a DRY RUN — nothing was changed. Re-run with --apply to reverse these entries.\n";
    exit(0);
}

$userId = null; // no logged-in session in CLI; NULL is allowed on voided_by/created_by per schema
$done = 0;
foreach ($candidates as $row) {
    $entryId = (int)$row['id'];
    try {
        $pdo->beginTransaction();

        $lines = dbFetchAll("SELECT account_id, debit, credit FROM journal_lines WHERE entry_id = ? ORDER BY id FOR UPDATE", [$entryId]);
        if (count($lines) < 2) throw new RuntimeException('too few lines');

        $reversalCode = 'JE-PRJ-FIX-' . $entryId . '-' . date('YmdHis');
        dbExecute(
            "INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
             VALUES (?, CURDATE(), ?, 'project_approval_correction', ?, 'posted', ?)",
            [$reversalCode, 'تصحيح: إلغاء قيد الاعتماد الوهمي لمشروع #' . $row['project_id'] . ' - لم يُصرف أي مبلغ فعلياً بعد', $entryId, $userId]
        );
        $reversalId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
        if ($reversalId <= 0) throw new RuntimeException('could not create reversal entry');

        foreach ($lines as $line) {
            dbExecute(
                "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)",
                [$reversalId, (int)$line['account_id'], (float)$line['credit'], (float)$line['debit'], 'تصحيح: عكس قيد اعتماد مشروع وهمي']
            );
        }

        $affected = dbExecute(
            "UPDATE journal_entries SET voided_at = NOW(), voided_by = ?, void_reason = ?
             WHERE id = ? AND status = 'posted' AND voided_at IS NULL",
            [$userId, 'تصحيح نظامي: خطأ في تصميم اعتماد المشاريع كان يسجل الميزانية كاملة كمصروف فعلي عند الاعتماد', $entryId]
        );
        if ($affected !== 1) throw new RuntimeException('could not mark original as corrected');

        $pdo->commit();
        echo "  Reversed #$entryId with $reversalCode\n";
        $done++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "  FAILED on #$entryId: " . $e->getMessage() . "\n";
    }
}
echo "\nDone: $done of " . count($candidates) . " entries reversed.\n";
