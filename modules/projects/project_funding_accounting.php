<?php
// modules/projects/project_funding_accounting.php
// Phase 5: FM-controlled project funding release and unused-fund return.
// Procedural PHP only. No runtime DDL.

if (!function_exists('akp_project_expense_account_id')) {
    function akp_project_expense_account_id(int $projectId): int
    {
        $project = dbFetchOne('SELECT expense_account_id FROM other_projects WHERE id = ?', [$projectId]);
        $accountId = (int)($project['expense_account_id'] ?? 0);
        if ($accountId > 0) return $accountId;
        $account = dbFetchOne("SELECT id FROM accounts WHERE code = '5100' AND is_active = 1 LIMIT 1");
        if (!$account) throw new RuntimeException('حساب مصروفات المشاريع 5100 غير موجود أو غير نشط.');
        return (int)$account['id'];
    }
}

if (!function_exists('akp_project_journal_code')) {
    function akp_project_journal_code(string $prefix, int $projectId, int $recordId): string
    {
        return $prefix . '-' . $projectId . '-' . $recordId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    }
}

if (!function_exists('akp_post_project_funding_release')) {
    function akp_post_project_funding_release(int $projectId): array
    {
        $allocations = dbFetchAll(
            "SELECT f.*, a.code AS source_account_code
             FROM project_funding_allocations f
             JOIN accounts a ON a.id = f.source_account_id
             WHERE f.project_id = ? AND f.status = 'draft'
             ORDER BY f.id",
            [$projectId]
        );
        if (!$allocations) throw new RuntimeException('لا توجد تخصيصات تمويل مسودة قابلة للإفراج المالي.');

        $expenseAccountId = akp_project_expense_account_id($projectId);
        $project = dbFetchOne('SELECT name, currency_code FROM other_projects WHERE id = ?', [$projectId]);
        if (!$project) throw new RuntimeException('المشروع غير موجود.');

        $journalIds = [];
        foreach ($allocations as $allocation) {
            $existing = dbFetchOne(
                "SELECT id FROM journal_entries
                 WHERE reference_type = 'project_funding_release'
                   AND reference_id = ?
                   AND status = 'posted'
                   AND voided_at IS NULL
                 ORDER BY id DESC LIMIT 1",
                [(int)$allocation['id']]
            );
            if ($existing) {
                $journalIds[(int)$allocation['id']] = (int)$existing['id'];
                dbExecute(
                    "UPDATE project_funding_allocations
                     SET status='posted', approved_by=?, posted_by=?, posted_at=NOW()
                     WHERE id=? AND project_id=? AND status='draft'",
                    [akp_user_id(), akp_user_id(), (int)$allocation['id'], $projectId]
                );
                continue;
            }

            $amount = round((float)$allocation['amount'], 2);
            if ($amount <= 0) throw new RuntimeException('تخصيص تمويل غير صالح.');

            $entryCode = akp_project_journal_code('JE-PRJ-REL', $projectId, (int)$allocation['id']);
            dbExecute(
                "INSERT INTO journal_entries
                 (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
                 VALUES (?,?,?,?,?,'posted',?)",
                [$entryCode, $allocation['allocation_date'] ?: date('Y-m-d'),
                 'إفراج مالي لتمويل المشروع: ' . (string)($project['name'] ?? ''),
                 'project_funding_release', (int)$allocation['id'], akp_user_id()]
            );
            $entryId = (int)dbLastInsertId();
            if ($entryId <= 0) throw new RuntimeException('تعذر إنشاء قيد الإفراج المالي للمشروع.');

            dbExecute(
                'INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)',
                [$entryId, $expenseAccountId, $amount, 0, 'إفراج تمويل المشروع إلى عهدة التنفيذ']
            );
            dbExecute(
                'INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)',
                [$entryId, (int)$allocation['source_account_id'], 0, $amount, 'خصم من حساب تمويل المشروع']
            );
            dbExecute(
                "UPDATE project_funding_allocations
                 SET status='posted', approved_by=?, posted_by=?, posted_at=NOW()
                 WHERE id=? AND project_id=? AND status='draft'",
                [akp_user_id(), akp_user_id(), (int)$allocation['id'], $projectId]
            );
            $journalIds[(int)$allocation['id']] = $entryId;
        }
        return $journalIds;
    }
}

if (!function_exists('akp_reverse_project_funding_release')) {
    function akp_reverse_project_funding_release(int $projectId, string $reason): void
    {
        $postedExpenses = dbFetchOne(
            "SELECT COALESCE(SUM(amount),0) AS total FROM project_expenses WHERE project_id=? AND status='posted'",
            [$projectId]
        );
        if ((float)($postedExpenses['total'] ?? 0) > 0.009) {
            throw new RuntimeException('لا يمكن عكس الإفراج المالي بعد تسجيل مصروفات تنفيذ فعلية للمشروع.');
        }

        $documented = dbFetchOne(
            "SELECT COUNT(*) AS n FROM project_payment_evidence WHERE project_id=? AND status='documented'",
            [$projectId]
        );
        if ((int)($documented['n'] ?? 0) > 0) {
            throw new RuntimeException('لا يمكن عكس الإفراج المالي بعد توثيق دفعات فعلية للمشروع.');
        }

        $rows = dbFetchAll(
            "SELECT je.id, je.entry_date, f.id AS allocation_id
             FROM project_funding_allocations f
             JOIN journal_entries je
               ON je.reference_type='project_funding_release'
              AND je.reference_id=f.id
              AND je.status='posted'
              AND je.voided_at IS NULL
             WHERE f.project_id=? AND f.status='posted'
             ORDER BY f.id",
            [$projectId]
        );

        foreach ($rows as $row) {
            $lines = dbFetchAll(
                'SELECT account_id, debit, credit, description FROM journal_lines WHERE entry_id=? ORDER BY id',
                [(int)$row['id']]
            );
            if (!$lines) throw new RuntimeException('قيد الإفراج المالي لا يحتوي على أسطر.');

            $totalDebit = 0.0; $totalCredit = 0.0;
            foreach ($lines as $line) {
                $debit = round((float)$line['debit'], 2);
                $credit = round((float)$line['credit'], 2);
                if ($debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) throw new RuntimeException('سطر قيد الإفراج المالي غير صالح.');
                $totalDebit += $debit; $totalCredit += $credit;
            }
            if (round($totalDebit,2) !== round($totalCredit,2) || round($totalDebit,2) <= 0) {
                throw new RuntimeException('قيد الإفراج المالي غير متوازن.');
            }

            $voided = dbExecute(
                "UPDATE journal_entries
                 SET voided_at=NOW(), voided_by=?, void_reason=?
                 WHERE id=? AND status='posted' AND voided_at IS NULL",
                [akp_user_id(), $reason, (int)$row['id']]
            );
            if ($voided !== 1) throw new RuntimeException('تعذر إبطال قيد الإفراج المالي.');

            $entryCode = akp_project_journal_code('JE-PRJ-REL-REV', $projectId, (int)$row['allocation_id']);
            dbExecute(
                "INSERT INTO journal_entries
                 (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
                 VALUES (?,?,?,?,?,'posted',?)",
                [$entryCode, date('Y-m-d'), 'عكس إفراج تمويل المشروع: ' . $reason,
                 'project_funding_release_reversal', (int)$row['allocation_id'], akp_user_id()]
            );
            $reversalId = (int)dbLastInsertId();
            foreach ($lines as $line) {
                dbExecute(
                    'INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)',
                    [$reversalId, (int)$line['account_id'], round((float)$line['credit'],2),
                     round((float)$line['debit'],2), 'عكس: ' . (string)($line['description'] ?? '')]
                );
            }

            dbExecute(
                "UPDATE project_funding_allocations
                 SET status='draft', approved_by=NULL, posted_by=NULL, posted_at=NULL
                 WHERE id=? AND project_id=? AND status='posted'",
                [(int)$row['allocation_id'], $projectId]
            );
        }

        dbExecute("DELETE FROM project_payment_evidence WHERE project_id=? AND status='pending'", [$projectId]);
    }
}

if (!function_exists('akp_project_controlled_balance')) {
    function akp_project_controlled_balance(int $projectId): float
    {
        $funded = dbFetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM project_funding_allocations WHERE project_id=? AND status='posted'", [$projectId]);
        $expenses = dbFetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM project_expenses WHERE project_id=? AND status='posted'", [$projectId]);
        $returned = dbFetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM project_funding_returns WHERE project_id=?", [$projectId]);
        return round(max(0, (float)($funded['total'] ?? 0) - (float)($expenses['total'] ?? 0) - (float)($returned['total'] ?? 0)), 2);
    }
}

if (!function_exists('akp_return_project_funding')) {
    function akp_return_project_funding(int $projectId, int $allocationId, float $amount, string $returnDate, ?string $reference, ?string $description): int
    {
        if (akp_role() !== 'financial_manager') throw new RuntimeException('إرجاع الرصيد المتبقي محصور بالمدير المالي.');

        $approval = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id=?', [$projectId]);
        if (!$approval || $approval['approval_status'] !== 'approved') throw new RuntimeException('لا يمكن إرجاع رصيد قبل الاعتماد النهائي للمشروع.');

        $lifecycle = dbFetchOne('SELECT lifecycle_status FROM project_lifecycle WHERE project_id=?', [$projectId]);
        if (!$lifecycle || !in_array((string)$lifecycle['lifecycle_status'], ['closure_requested','completed','under_review'], true)) {
            throw new RuntimeException('إرجاع الرصيد المتبقي متاح عند طلب إغلاق المشروع أو أثناء المراجعة الختامية.');
        }

        $allocation = dbFetchOne(
            "SELECT f.*, a.code AS source_account_code
             FROM project_funding_allocations f
             JOIN accounts a ON a.id=f.source_account_id
             WHERE f.id=? AND f.project_id=? AND f.status='posted'",
            [$allocationId, $projectId]
        );
        if (!$allocation) throw new RuntimeException('تخصيص التمويل المطلوب غير موجود أو غير مرحّل.');
        $amount = round($amount, 2);
        if ($amount <= 0) throw new RuntimeException('مبلغ الإرجاع يجب أن يكون أكبر من صفر.');
        $returnedForAllocation = dbFetchOne(
            'SELECT COALESCE(SUM(amount),0) AS total FROM project_funding_returns WHERE funding_allocation_id=?',
            [$allocationId]
        );
        $allocationRemaining = round((float)$allocation['amount'] - (float)($returnedForAllocation['total'] ?? 0), 2);
        if ($amount > $allocationRemaining + 0.01) {
            throw new RuntimeException('مبلغ الإرجاع يتجاوز الرصيد المتبقي لهذا المصدر المالي: ' . number_format($allocationRemaining, 2) . '.');
        }
        $controlledBalance = akp_project_controlled_balance($projectId);
        if ($amount > $controlledBalance + 0.01) {
            throw new RuntimeException('مبلغ الإرجاع يتجاوز الرصيد المتبقي تحت سيطرة المشروع: ' . number_format($controlledBalance, 2) . '.');
        }

        $project = dbFetchOne('SELECT name, currency_code FROM other_projects WHERE id=?', [$projectId]);
        $expenseAccountId = akp_project_expense_account_id($projectId);

        dbExecute('START TRANSACTION');
        try {
            $entryCode = akp_project_journal_code('JE-PRJ-RET', $projectId, $allocationId);
            dbExecute(
                "INSERT INTO journal_entries
                 (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
                 VALUES (?,?,?,?,?,'posted',?)",
                [$entryCode, $returnDate ?: date('Y-m-d'),
                 'إرجاع رصيد مشروع غير مستخدم: ' . (string)($project['name'] ?? ''),
                 'project_funding_return', $allocationId, akp_user_id()]
            );
            $entryId = (int)dbLastInsertId();

            dbExecute(
                'INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)',
                [$entryId, (int)$allocation['source_account_id'], $amount, 0, 'إعادة الرصيد إلى حساب المؤسسة']
            );
            dbExecute(
                'INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?,?,?,?,?)',
                [$entryId, $expenseAccountId, 0, $amount, 'عكس الجزء غير المستخدم من مصروف المشروع']
            );
            dbExecute(
                "INSERT INTO project_funding_returns
                 (project_id, funding_allocation_id, source_account_id, journal_entry_id, amount, currency_code, return_date, reference_number, description, returned_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$projectId, $allocationId, (int)$allocation['source_account_id'], $entryId, $amount,
                 $allocation['currency_code'] ?: ($project['currency_code'] ?: 'SDG'),
                 $returnDate ?: date('Y-m-d'), $reference ?: null, $description ?: null, akp_user_id()]
            );
            dbExecute('COMMIT');
        } catch (Throwable $e) {
            dbExecute('ROLLBACK');
            throw $e;
        }
        // Notify the Projects Manager that the requested refund has been processed.
        try {
            $projectManagers = dbFetchAll("SELECT u.id FROM users u JOIN roles r ON u.role_id=r.id WHERE r.code='projects_manager' AND u.is_active=1");
            foreach ($projectManagers as $projectManager) {
                ak_transaction_review_notify_event(
                    (int)$projectManager['id'],
                    'تمت معالجة استرداد رصيد المشروع',
                    'تمت معالجة إرجاع الرصيد المتبقي للمشروع «' . (string)($project['name'] ?? '') . '». يمكن متابعة إجراء الإغلاق بعد اكتمال المراجعة.',
                    APP_URL . 'modules/projects/view.php?id=' . $projectId,
                    $projectId,
                    'project_funding_return_processed'
                );
            }
        } catch (Throwable $notificationError) {}
        return $entryId;
    }
}


if (!function_exists('akp_sync_project_payment_evidence_after_release')) {
    function akp_sync_project_payment_evidence_after_release(int $projectId): void
    {
        $project = dbFetchOne('SELECT currency_code FROM other_projects WHERE id=?', [$projectId]);
        $rows = dbFetchAll(
            "SELECT f.id, f.source_account_id, f.amount, f.currency_code, a.code AS source_code,
                    je.id AS journal_entry_id
             FROM project_funding_allocations f
             JOIN accounts a ON a.id=f.source_account_id
             JOIN journal_entries je
               ON je.reference_type='project_funding_release'
              AND je.reference_id=f.id
              AND je.status='posted'
              AND je.voided_at IS NULL
             WHERE f.project_id=? AND f.status='posted'
             ORDER BY f.id",
            [$projectId]
        );
        foreach ($rows as $row) {
            $method = akp_project_payment_method_from_account_code((string)$row['source_code']);
            if ($method === null) throw new RuntimeException('مصدر تمويل المشروع لا يملك طريقة دفع معروفة.');
            $existing = dbFetchOne('SELECT id FROM project_payment_evidence WHERE funding_allocation_id=? LIMIT 1', [(int)$row['id']]);
            if ($existing) {
                dbExecute(
                    "UPDATE project_payment_evidence
                     SET source_account_id=?, journal_entry_id=?, payment_method=?, amount=?, currency_code=?, payment_date=CURDATE(), status='pending'
                     WHERE funding_allocation_id=?",
                    [(int)$row['source_account_id'], (int)$row['journal_entry_id'], $method,
                     (float)$row['amount'], $row['currency_code'] ?: ($project['currency_code'] ?: 'SDG'), (int)$row['id']]
                );
            } else {
                dbExecute(
                    "INSERT INTO project_payment_evidence
                     (project_id, funding_allocation_id, source_account_id, journal_entry_id, payment_method, amount, currency_code, payment_date, status, created_at)
                     VALUES (?,?,?,?,?,?,?,CURDATE(),'pending',NOW())",
                    [$projectId, (int)$row['id'], (int)$row['source_account_id'], (int)$row['journal_entry_id'],
                     $method, (float)$row['amount'], $row['currency_code'] ?: ($project['currency_code'] ?: 'SDG')]
                );
            }
        }
    }
}
