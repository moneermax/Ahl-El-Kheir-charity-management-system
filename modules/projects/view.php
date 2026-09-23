<?php
// modules/projects/view.php - Project profile, financial controls, documents, operations, closure
require_once dirname(__DIR__, 2) . '/modules/projects/project_lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_transaction_review.php';
Session::start();

$requestedProjectId = (int)($_GET['id'] ?? $_POST['project_id'] ?? 0);
if (!isset($_GET['role_view'])) {
    if (akp_role() === 'financial_manager') {
        header('Location: ' . APP_URL . 'modules/projects/view_fm.php?id=' . $requestedProjectId);
        exit();
    }
    if (akp_role() === 'projects_manager') {
        header('Location: ' . APP_URL . 'modules/projects/view_pm.php?id=' . $requestedProjectId);
        exit();
    }
}

$id = (int)($_GET['id'] ?? $_POST['project_id'] ?? 0);
$project = $id ? akp_get_project($id) : null;
if (!$project) {
    flash('error', 'المشروع غير موجود.');
    redirect('modules/projects/index.php');
}

if (!akp_can_view_project($id)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$project = akp_get_project($id);
$totals = akp_project_totals($id);
$details = dbFetchOne('SELECT * FROM project_details WHERE project_id = ?', [$id]) ?: [];

$governmentRequirementRows = dbFetchAll(
    'SELECT requirement_text, fee_amount
     FROM project_government_requirements
     WHERE project_id = ?
     ORDER BY id ASC',
    [$id]
);
$partnerRows = dbFetchAll(
    'SELECT partner_name, role_description
     FROM project_partners
     WHERE project_id = ?
     ORDER BY id ASC',
    [$id]
);
$procurementRows = dbFetchAll(
    'SELECT method_name, notes
     FROM project_procurement_methods
     WHERE project_id = ?
     ORDER BY id ASC',
    [$id]
);
$contactRows = dbFetchAll(
    'SELECT contact_name, role_description, phone, email, notes
     FROM project_contacts
     WHERE project_id = ?
     ORDER BY id ASC',
    [$id]
);

$budgets = dbFetchAll("SELECT b.*, COALESCE(SUM(bl.estimated_amount),0) AS line_total, COUNT(bl.id) AS line_count FROM project_budgets b LEFT JOIN project_budget_lines bl ON bl.budget_id = b.id WHERE b.project_id = ? GROUP BY b.id ORDER BY b.version_no DESC", [$id]);
$approvedBudgetId = 0; 
$draftBudgetId = 0; 
foreach ($budgets as $budget) { 
    if ($budget['status'] === 'approved' && !$approvedBudgetId) $approvedBudgetId = (int)$budget['id']; 
    if ($budget['status'] === 'draft' && !$draftBudgetId) $draftBudgetId = (int)$budget['id']; 
}

$fundings = dbFetchAll("SELECT f.*, a.code AS source_account_code, a.name_ar AS source_account_name FROM project_funding_allocations f LEFT JOIN accounts a ON a.id = f.source_account_id WHERE f.project_id = ? ORDER BY f.created_at DESC", [$id]);
$expenses = dbFetchAll("SELECT e.*, je.entry_code FROM project_expenses e LEFT JOIN journal_entries je ON je.id = e.journal_entry_id WHERE e.project_id = ? ORDER BY e.expense_date DESC, e.id DESC", [$id]);
$documents = dbFetchAll('SELECT d.*, u.full_name AS uploader_name FROM project_documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.project_id = ? ORDER BY d.id DESC', [$id]);
$milestones = dbFetchAll('SELECT * FROM project_milestones WHERE project_id = ? ORDER BY planned_date, id', [$id]);
$progressUpdates = dbFetchAll('SELECT p.*, u.full_name AS submitter_name FROM project_progress_updates p LEFT JOIN users u ON p.submitted_by = u.id WHERE p.project_id = ? ORDER BY p.update_date DESC', [$id]);
$labors = dbFetchAll('SELECT lh.*, u.full_name AS supervisor_name FROM project_labor_helpers lh LEFT JOIN users u ON u.id = lh.supervisor_user_id WHERE lh.project_id = ? ORDER BY lh.id DESC', [$id]);
$team = dbFetchAll('SELECT pt.*, u.full_name, u.username FROM project_team pt JOIN users u ON u.id = pt.user_id WHERE pt.project_id = ? AND pt.unassigned_at IS NULL ORDER BY pt.section_code, pt.is_lead DESC, u.full_name', [$id]);
$primarySupervisor = dbFetchOne("SELECT u.id, u.full_name, u.username FROM project_supervisor_assignments psa JOIN users u ON u.id = psa.supervisor_user_id WHERE psa.project_id = ? AND psa.ended_at IS NULL ORDER BY psa.id DESC LIMIT 1", [$id]);
$history = dbFetchAll('SELECT h.*, u.full_name FROM project_status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.project_id = ? ORDER BY h.created_at DESC LIMIT 20', [$id]);

$closed = akp_project_is_closed($id);
$role = akp_role();
$approval = dbFetchOne('SELECT * FROM project_approval WHERE project_id = ?', [$id]) ?: ['approval_status' => 'approved'];
$errors = [];

function akp_redirect_project(int $id): void {
    header('Location: ' . APP_URL . 'modules/projects/view.php?id=' . $id);
    exit();
}

function akp_post_value(string $name, string $default = ''): string {
    return trim((string)($_POST[$name] ?? $default));
}


/** Create a balanced reversal entry while preserving the original posted entry. */
function akp_reverse_project_journal(int $originalEntryId, int $projectId, string $projectName, string $reason): int {
    $original = dbFetchOne('SELECT * FROM journal_entries WHERE id = ?', [$originalEntryId]);
    if (!$original) throw new RuntimeException('القيد المحاسبي الأصلي غير موجود.');

    $lines = dbFetchAll('SELECT account_id, debit, credit, description FROM journal_lines WHERE entry_id = ? ORDER BY id', [$originalEntryId]);
    if (!$lines) throw new RuntimeException('القيد المحاسبي الأصلي لا يحتوي على بنود يمكن عكسها.');

    $entryCode = 'JE-PRJ-' . str_pad((string)$projectId, 4, '0', STR_PAD_LEFT) . '-REV-' . date('YmdHis');
    dbExecute('INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by) VALUES (?, ?, ?, ?, ?, \'posted\', ?)', [
        $entryCode,
        date('Y-m-d'),
        'عكس قيد اعتماد مشروع: ' . $projectName . ' - ' . $reason,
        'project_journal_reversal',
        $originalEntryId,
        akp_user_id()
    ]);
    $reversalId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
    if ($reversalId <= 0) throw new RuntimeException('تعذر إنشاء قيد العكس.');

    foreach ($lines as $line) {
        dbExecute('INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)', [
            $reversalId,
            $line['account_id'],
            (float)$line['credit'],
            (float)$line['debit'],
            'عكس: ' . ($line['description'] ?? '')
        ]);
    }

    akp_audit('REVERSE_PROJECT_JOURNAL', 'journal_entries', $reversalId,
        ['original_entry_id' => $originalEntryId],
        ['project_id' => $projectId, 'original_entry_id' => $originalEntryId, 'reason' => $reason]
    );
    return $reversalId;
}

/** Create the project approval journal from explicit FM funding allocations. */
function akp_create_project_approval_journal(int $projectId, string $projectName, float $budgetFallbackAmount): int {
    $fundingRows = dbFetchAll(
        'SELECT source_account_id, SUM(amount) AS amount FROM project_funding_allocations WHERE project_id = ? GROUP BY source_account_id',
        [$projectId]
    );

    $fundingTotal = 0.0;
    foreach ($fundingRows as $row) {
        $fundingTotal += (float)$row['amount'];
    }

    if ($fundingTotal <= 0.009) {
        throw new RuntimeException('لا يمكن إنشاء قيد اعتماد المشروع قبل تسجيل تخصيصات تمويل صريحة من حسابات المؤسسة.');
    }
    $journalAmount = $fundingTotal;

    $projectAccount = dbFetchOne('SELECT id FROM accounts WHERE code = \'5100\'');
    if (!$projectAccount) throw new RuntimeException('حساب مصروفات البرامج والمساعدات (5100) غير موجود.');

    $entryCode = 'JE-PRJ-' . str_pad((string)$projectId, 4, '0', STR_PAD_LEFT) . '-' . date('YmdHis');
    dbExecute('INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by) VALUES (?, ?, ?, \'project\', ?, \'posted\', ?)', [
        $entryCode,
        date('Y-m-d'),
        'تخصيص تمويل مشروع: ' . $projectName,
        $projectId,
        akp_user_id()
    ]);
    $entryId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
    if ($entryId <= 0) throw new RuntimeException('تعذر إنشاء قيد اعتماد المشروع.');

    dbExecute('INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)', [
        $entryId, $projectAccount['id'], $journalAmount, 0, 'تخصيص تمويل مشروع'
    ]);

    if ($fundingTotal > 0.009) {
        foreach ($fundingRows as $row) {
            $sourceAccountId = (int)$row['source_account_id'];
            $sourceAmount = (float)$row['amount'];
            if ($sourceAccountId <= 0 || $sourceAmount <= 0) {
                throw new RuntimeException('يوجد تخصيص تمويل بدون حساب مصدر صالح.');
            }
            if (!dbFetchOne('SELECT id FROM accounts WHERE id = ?', [$sourceAccountId])) {
                throw new RuntimeException('أحد حسابات مصادر التمويل غير موجود.');
            }
            dbExecute('INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)', [
                $entryId, $sourceAccountId, 0, $sourceAmount, 'تمويل مشروع من مصدر التمويل'
            ]);
        }
    }

    akp_audit('CREATE_PROJECT_JOURNAL', 'journal_entries', $entryId, null, [
        'project_id' => $projectId,
        'amount' => $journalAmount,
        'funding_total' => $fundingTotal,
        'source_accounts' => $fundingRows
    ]);
    return $entryId;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة.');
        akp_redirect_project($id);
    }
    
    $action = (string)($_POST['action'] ?? '');
    $project = akp_get_project($id);
    $closed = akp_project_is_closed($id);
    
    try {
        if ($action === 'submit_project') {
            if ($role !== 'projects_manager') throw new RuntimeException('إرسال المشروع للاعتماد متاح لمدير المشاريع فقط.');
            $approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
            if (!$approvalCheck || !in_array($approvalCheck['approval_status'], ['draft', 'rejected'], true)) throw new RuntimeException('المشروع ليس في حالة تسمح بالإرسال.');
            dbExecute("UPDATE project_approval SET approval_status = 'submitted', submitted_by = ?, submitted_at = NOW(), rejection_reason = NULL, fm_rejection_reason = NULL WHERE project_id = ?", [akp_user_id(), $id]);
            akp_audit('SUBMIT_APPROVAL', 'project_approval', $id, ['approval_status' => $approvalCheck['approval_status']], ['approval_status' => 'submitted']);

            // Reuse the existing event-aware FM notification infrastructure.
            // Delivery is isolated from the completed project state transition,
            // and unread notifications are deduplicated by project/event reference.
            ak_transaction_review_notify_fm_event(
                $id,
                'project_submission',
                'مشروع بانتظار المراجعة المالية',
                'المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') بانتظار مراجعة المدير المالي.',
                APP_URL . 'modules/projects/view.php?id=' . $id
            );

            flash('success', 'تم إرسال المشروع إلى المدير المالي للمراجعة والاعتماد المبدئي.');
            
        } elseif ($action === 'fm_approve_project') {
            if ($role !== 'financial_manager') throw new RuntimeException('اعتماد المشروع مالياً محصور بالمدير المالي.');
            $approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
            if (!$approvalCheck || $approvalCheck['approval_status'] !== 'submitted') throw new RuntimeException('المشروع ليس في حالة انتظار الاعتماد المالي.');

            // Financial approval is only valid against an explicitly approved budget.
            // The lifecycle amount may contain an initial proposal and must not be
            // treated as an approved accounting basis.
            $approvedBudgetCheck = dbFetchOne(
                "SELECT b.id,
                        COALESCE(SUM(bl.estimated_amount), 0) AS budget_total
                 FROM project_budgets b
                 LEFT JOIN project_budget_lines bl ON bl.budget_id = b.id
                 WHERE b.project_id = ?
                   AND b.status = 'approved'
                 GROUP BY b.id
                 ORDER BY b.version_no DESC
                 LIMIT 1",
                [$id]
            );
            $approvedBudgetTotal = (float)($approvedBudgetCheck['budget_total'] ?? 0);
            if (!$approvedBudgetCheck || $approvedBudgetTotal <= 0) {
                throw new RuntimeException('لا يمكن اعتماد المشروع مالياً قبل اعتماد نسخة ميزانية سارية بمبلغ أكبر من صفر.');
            }

            $fundingTotal = (float)(dbFetchOne(
                "SELECT COALESCE(SUM(amount), 0) AS n
                 FROM project_funding_allocations
                 WHERE project_id = ? AND status = 'draft'",
                [$id]
            )['n'] ?? 0);
            if (abs($fundingTotal - $approvedBudgetTotal) > 0.01) {
                $remaining = max(0, $approvedBudgetTotal - $fundingTotal);
                throw new RuntimeException('لا يمكن اعتماد المشروع مالياً قبل اكتمال تخصيص التمويل من حسابات المؤسسة. الميزانية: ' . number_format($approvedBudgetTotal, 2) . '، المخصص: ' . number_format($fundingTotal, 2) . '، المتبقي: ' . number_format($remaining, 2) . '.');
            }

            dbExecute("UPDATE project_approval SET approval_status = 'fm_approved', fm_reviewed_by = ?, fm_reviewed_at = NOW() WHERE project_id = ?", [akp_user_id(), $id]);
            akp_audit('FM_APPROVE_PROJECT', 'project_approval', $id, ['approval_status' => 'submitted'], ['approval_status' => 'fm_approved']);

            // Notify active General Manager recipients through the existing notification infrastructure.
            // Notification delivery is isolated so it cannot roll back the completed FM approval.
            try {
                $gmUsers = dbFetchAll(
                    "SELECT u.id
                     FROM users u
                     JOIN roles r ON u.role_id = r.id
                     WHERE r.code = 'general_manager'
                       AND u.is_active = 1"
                );
                foreach ($gmUsers as $gmUser) {
                    ak_transaction_review_notify_event(
                        (int)$gmUser['id'],
                        'مشروع بانتظار الاعتماد النهائي',
                        'المشروع «' . (string)($project['name'] ?? '') . '» (' . (string)($project['project_code'] ?? '') . ') تم اعتماده مالياً وبانتظار اعتماد المدير العام.',
                        APP_URL . 'modules/projects/view.php?id=' . $id,
                        $id,
                        'project_fm_approval'
                    );
                }
            } catch (Throwable $notificationError) {
                // Notification delivery must never roll back the completed FM approval.
            }

            flash('success', 'تم اعتماد المشروع مالياً. المشروع الآن بانتظار اعتماد المدير العام.');
            
        } elseif ($action === 'fm_return_to_review') {
            if ($role !== 'financial_manager') throw new RuntimeException('إعادة المشروع للمراجعة المالية متاحة للمدير المالي فقط.');
            $reason = akp_post_value('return_reason');
            if ($reason === '') throw new RuntimeException('سبب إعادة المشروع للمراجعة المالية مطلوب.');
            $approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
            if (!$approvalCheck || $approvalCheck['approval_status'] !== 'fm_approved') throw new RuntimeException('المشروع ليس في حالة اعتماد مالي تسمح بإعادته للمراجعة.');
            dbExecute("UPDATE project_approval SET approval_status = 'submitted' WHERE project_id = ?", [$id]);
            akp_audit('FM_RETURN_TO_REVIEW', 'project_approval', $id, ['approval_status' => 'fm_approved'], ['approval_status' => 'submitted', 'reason' => $reason]);
            flash('success', 'تمت إعادة المشروع إلى مرحلة المراجعة المالية لاستكمال تخصيص التمويل.');

        } elseif ($action === 'fm_reject_project') {
            if ($role !== 'financial_manager') throw new RuntimeException('رفض المشروع مالياً محصور بالمدير المالي.');
            $reason = akp_post_value('rejection_reason');
            if ($reason === '') throw new RuntimeException('سبب الرفض المالي مطلوب.');
            $approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
            if (!$approvalCheck || $approvalCheck['approval_status'] !== 'submitted') throw new RuntimeException('المشروع ليس في حالة انتظار الاعتماد المالي.');
            dbExecute("UPDATE project_approval SET approval_status = 'rejected', fm_rejection_reason = ?, fm_reviewed_by = ?, fm_reviewed_at = NOW() WHERE project_id = ?", [$reason, akp_user_id(), $id]);
            akp_audit('FM_REJECT_PROJECT', 'project_approval', $id, ['approval_status' => 'submitted'], ['approval_status' => 'rejected', 'reason' => $reason]);
            flash('success', 'تم رفض المشروع مالياً وإعادته لمدير المشاريع.');
            
        } elseif ($action === 'approve_project') {
            if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager'], true)) throw new RuntimeException('اعتماد المشروع نهائياً محصور بالمدير العام أو نائبه.');
            $approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
            if (!$approvalCheck || $approvalCheck['approval_status'] !== 'fm_approved') throw new RuntimeException('المشروع لم يتم اعتماده مالياً بعد.');

            // Final approval must use the currently approved budget, never a draft
            // budget or a stale/proposed lifecycle amount.
            $approvedBudgetCheck = dbFetchOne(
                "SELECT b.id,
                        COALESCE(SUM(bl.estimated_amount), 0) AS budget_total
                 FROM project_budgets b
                 LEFT JOIN project_budget_lines bl ON bl.budget_id = b.id
                 WHERE b.project_id = ?
                   AND b.status = 'approved'
                 GROUP BY b.id
                 ORDER BY b.version_no DESC
                 LIMIT 1",
                [$id]
            );
            $budgetAmount = (float)($approvedBudgetCheck['budget_total'] ?? 0);
            if (!$approvedBudgetCheck || $budgetAmount <= 0) {
                throw new RuntimeException('لا يمكن اعتماد المشروع نهائياً قبل وجود نسخة ميزانية سارية ومعتمدة بمبلغ أكبر من صفر.');
            }

            // Keep funding within the approved budget before creating the
            // final accounting allocation.
            $fundingTotal = (float)(dbFetchOne(
                "SELECT COALESCE(SUM(amount), 0) AS n
                 FROM project_funding_allocations
                 WHERE project_id = ?",
                [$id]
            )['n'] ?? 0);
            if (abs($fundingTotal - $budgetAmount) > 0.01) {
                throw new RuntimeException('لا يمكن اعتماد المشروع نهائياً قبل أن يساوي إجمالي تخصيصات التمويل الميزانية المعتمدة.');
            }
            
            try {
                dbExecute('START TRANSACTION');
                dbExecute("UPDATE project_approval SET approval_status = 'approved', approved_by = ?, approved_at = NOW() WHERE project_id = ?", [akp_user_id(), $id]);
                dbExecute('UPDATE other_projects SET status = \'active\' WHERE id = ?', [$id]);
                dbExecute('UPDATE project_lifecycle SET lifecycle_status = \'active\' WHERE project_id = ?', [$id]);
                
                dbExecute('UPDATE project_lifecycle SET final_budget_amount = ? WHERE project_id = ?', [$budgetAmount, $id]);

                $entryId = akp_create_project_approval_journal($id, $project['name'], 0);
                
                dbExecute('COMMIT');
                akp_audit('GM_APPROVE_PROJECT', 'project_approval', $id, ['approval_status' => 'fm_approved'], ['approval_status' => 'approved']);
                flash('success', 'تم اعتماد المشروع نهائياً وتخصيص الميزانية في الدفاتر.');
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                throw $e;
            }
            
        } elseif ($action === 'reject_project') {
            if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager'], true)) throw new RuntimeException('رفض المشروع نهائياً محصور بالمدير العام أو نائبه.');
            $reason = akp_post_value('rejection_reason');
            if ($reason === '') throw new RuntimeException('سبب الرفض مطلوب.');
            $approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
            if (!$approvalCheck || $approvalCheck['approval_status'] !== 'fm_approved') throw new RuntimeException('المشروع ليس في حالة انتظار الاعتماد النهائي.');
            dbExecute("UPDATE project_approval SET approval_status = 'rejected', rejection_reason = ?, approved_by = NULL, approved_at = NULL WHERE project_id = ?", [$reason, $id]);
            akp_audit('REJECT_PROJECT', 'project_approval', $id, ['approval_status' => 'fm_approved'], ['approval_status' => 'rejected', 'reason' => $reason]);
            flash('success', 'تم رفض المشروع نهائياً وإعادته لمدير المشاريع.');
            
        } elseif ($action === 'change_status') {
            // ... (Original change_status logic preserved exactly)
            $newStatus = akp_post_value('new_status');
            if (!akp_can_edit_section('operations', $id) || $closed) throw new RuntimeException('لا تملك صلاحية تغيير حالة هذا المشروع.');
            if (!in_array($newStatus, ['planned','active','completed','under_review','cancelled'], true)) throw new RuntimeException('الحالة غير صالحة.');
            $oldStatus = (string)($project['lifecycle_status'] ?: $project['status']);
            $legacyStatus = in_array($newStatus, ['planned','active','completed','cancelled'], true) ? $newStatus : 'completed';
            dbExecute('UPDATE other_projects SET status = ?, updated_by = ? WHERE id = ?', [$legacyStatus, akp_user_id(), $id]);
            dbExecute('UPDATE project_lifecycle SET lifecycle_status = ? WHERE project_id = ?', [$newStatus, $id]);
            dbExecute('INSERT INTO project_status_history (project_id, old_status, new_status, reason, changed_by) VALUES (?,?,?,?,?)', [$id, $oldStatus, $newStatus, akp_post_value('reason') ?: null, akp_user_id()]);
            akp_audit('STATUS_CHANGE', 'project_lifecycle', $id, ['status' => $oldStatus], ['status' => $newStatus]);
            flash('success', 'تم تحديث حالة المشروع.');
            
        } elseif ($action === 'assign_team') {
            // ... (Original assign_team logic preserved exactly)
            if (!akp_can_edit_section('team', $id) || $closed) throw new RuntimeException('إدارة فريق المشروع متاحة للإدارة التنفيذية فقط.');
            $userId = (int)($_POST['team_user_id'] ?? 0);
            $section = akp_post_value('team_section');
            if (!$userId || $section === '') throw new RuntimeException('بيانات التكليف غير مكتملة.');
            if (!in_array($section, ['finance', 'operations', 'documents', 'closure'], true)) throw new RuntimeException('قسم التكليف غير صالح.');
            $already = dbFetchOne('SELECT id FROM project_team WHERE project_id = ? AND user_id = ? AND section_code = ? AND unassigned_at IS NULL', [$id, $userId, $section]);
            if (!$already) dbExecute('INSERT INTO project_team (project_id, user_id, section_code, is_lead, assigned_by, notes) VALUES (?,?,?,?,?,?)', [$id, $userId, $section, (int)($_POST['team_is_lead'] ?? 0), akp_user_id(), akp_post_value('team_notes') ?: null]);
            akp_audit('ASSIGN', 'project_team', $id, null, ['user_id' => $userId, 'section' => $section]);
            flash('success', 'تمت إضافة المستخدم إلى القسم المحدد.');
            
        } elseif ($action === 'unassign_team') {
            // ... (Original unassign_team logic preserved exactly)
            if (!akp_can_edit_section('team', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إزالة التكليف.');
            $teamId = (int)($_POST['team_id'] ?? 0);
            dbExecute('UPDATE project_team SET unassigned_at = NOW(), unassigned_by = ? WHERE id = ? AND project_id = ?', [akp_user_id(), $teamId, $id]);
            akp_audit('UNASSIGN', 'project_team', $teamId, null, ['project_id' => $id]);
            flash('success', 'تمت إزالة تكليف المستخدم.');
            
        } elseif ($action === 'add_budget') {
            // ... (Original add_budget logic preserved exactly)
            if (!akp_can_prepare_finance($id) || $closed) throw new RuntimeException('إعداد الميزانية قبل المراجعة المالية محصور بمدير المشاريع.');
            $name = akp_post_value('budget_name');
            $lineCategory = akp_post_value('line_category');
            $lineDescription = akp_post_value('line_description');
            $estimate = (float)($_POST['estimated_amount'] ?? 0);
            if ($name === '' || $lineCategory === '' || $lineDescription === '' || $estimate <= 0) throw new RuntimeException('اسم الميزانية وبندها ووصفه ومبلغه مطلوبة.');
            $nextVersion = (int)(dbFetchOne('SELECT COALESCE(MAX(version_no),0) + 1 AS n FROM project_budgets WHERE project_id = ?', [$id])['n'] ?? 1);
            dbExecute('INSERT INTO project_budgets (project_id, version_no, budget_name, currency_code, status, created_by) VALUES (?,?,?,?,?,?)', [$id, $nextVersion, $name, $project['currency_code'] ?: 'SDG', 'draft', akp_user_id()]);
            $budgetId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
            dbExecute('INSERT INTO project_budget_lines (budget_id, category, description, account_id, estimated_amount, approved_amount, notes, created_by) VALUES (?,?,?,?,?,?,?,?)', [$budgetId, $lineCategory, $lineDescription, (int)($_POST['budget_account_id'] ?? 0) ?: null, $estimate, null, akp_post_value('line_notes') ?: null, akp_user_id()]);
            akp_audit('CREATE', 'project_budget', $budgetId, null, ['project_id' => $id, 'version_no' => $nextVersion, 'amount' => $estimate]);
            flash('success', 'تم إنشاء نسخة ميزانية وإضافة البند الأول.');
            
        } elseif ($action === 'add_budget_line') {
            // ... (Original add_budget_line logic preserved exactly)
            if (!akp_can_prepare_finance($id) || $closed) throw new RuntimeException('إعداد بنود الميزانية قبل المراجعة المالية محصور بمدير المشاريع.');
            $budgetId = (int)($_POST['budget_id'] ?? 0);
            $budget = dbFetchOne('SELECT * FROM project_budgets WHERE id = ? AND project_id = ?', [$budgetId, $id]);
            if (!$budget || $budget['status'] !== 'draft') throw new RuntimeException('لا يمكن تعديل نسخة ميزانية معتمدة.');
            $category = akp_post_value('line_category');
            $description = akp_post_value('line_description');
            $estimate = (float)($_POST['estimated_amount'] ?? 0);
            if ($category === '' || $description === '' || $estimate <= 0) throw new RuntimeException('الفئة والوصف والمبلغ التقديري مطلوبة.');
            dbExecute('INSERT INTO project_budget_lines (budget_id, category, description, account_id, estimated_amount, notes, created_by) VALUES (?,?,?,?,?,?,?)', [$budgetId, $category, $description, (int)($_POST['budget_account_id'] ?? 0) ?: null, $estimate, akp_post_value('line_notes') ?: null, akp_user_id()]);
            akp_audit('CREATE', 'project_budget_line', $budgetId, null, ['project_id' => $id, 'category' => $category, 'amount' => $estimate]);
            flash('success', 'تمت إضافة بند الميزانية.');
            
        } elseif ($action === 'add_funding') {
            if (!akp_can_manage_funding($id) || $closed) {
                throw new RuntimeException('إضافة تخصيصات التمويل واختيار حسابات المصدر محصوران بالمدير المالي.');
            }
            $approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
            if (!$approvalCheck || !in_array($approvalCheck['approval_status'], ['submitted', 'rejected'], true)) {
                throw new RuntimeException('يمكن للمدير المالي تسجيل مصادر التمويل فقط أثناء المراجعة المالية أو بعد الرفض المالي.');
            }

            $sourceAccountIds = $_POST['funding_source_account_id'] ?? [];
            $amounts = $_POST['funding_amount'] ?? [];
            $dates = $_POST['funding_allocation_date'] ?? [];
            $references = $_POST['funding_reference'] ?? [];
            $descriptions = $_POST['funding_description'] ?? [];

            if (!is_array($sourceAccountIds)) $sourceAccountIds = [$sourceAccountIds];
            if (!is_array($amounts)) $amounts = [$amounts];
            if (!is_array($dates)) $dates = [$dates];
            if (!is_array($references)) $references = [$references];
            if (!is_array($descriptions)) $descriptions = [$descriptions];

            $approvedBudgetRow = dbFetchOne(
                "SELECT COALESCE(SUM(bl.estimated_amount), 0) AS n
                 FROM project_budgets b
                 JOIN project_budget_lines bl ON bl.budget_id = b.id
                 WHERE b.project_id = ? AND b.status = 'approved'",
                [$id]
            );
            $approvedBudgetTotal = (float)($approvedBudgetRow['n'] ?? 0);
            if ($approvedBudgetTotal <= 0) {
                throw new RuntimeException('لا يمكن تسجيل التمويل قبل اعتماد نسخة الميزانية.');
            }

            $existingTotal = (float)(dbFetchOne(
                "SELECT COALESCE(SUM(amount),0) AS n
                 FROM project_funding_allocations
                 WHERE project_id = ? AND status = 'draft'",
                [$id]
            )['n'] ?? 0);

            $rowsToInsert = [];
            $batchTotal = 0.0;
            $rowCount = max(count($sourceAccountIds), count($amounts));
            if ($rowCount <= 0) {
                throw new RuntimeException('يجب إضافة مصدر تمويل واحد على الأقل.');
            }

            for ($i = 0; $i < $rowCount; $i++) {
                $sourceAccountId = (int)($sourceAccountIds[$i] ?? 0);
                $amount = (float)($amounts[$i] ?? 0);
                $sourceAccount = $sourceAccountId ? dbFetchOne('SELECT id, code, name_ar FROM accounts WHERE id = ?', [$sourceAccountId]) : null;

                if (!$sourceAccount || !in_array($sourceAccount['code'], ['1100', '1200', '1300'], true)) {
                    throw new RuntimeException('كل تخصيص يجب أن يستخدم أحد مصادر التمويل التالية: الصندوق النقدي (1100)، البنك (1200)، أو المحفظة الإلكترونية (1300).');
                }
                if ($amount <= 0) {
                    throw new RuntimeException('يجب أن يكون مبلغ كل تخصيص تمويل أكبر من صفر.');
                }

                $rowsToInsert[] = [
                    'account' => $sourceAccount,
                    'amount' => $amount,
                    'date' => trim((string)($dates[$i] ?? '')) ?: date('Y-m-d'),
                    'reference' => trim((string)($references[$i] ?? '')) ?: null,
                    'description' => trim((string)($descriptions[$i] ?? '')) ?: null
                ];
                $batchTotal += $amount;
            }

            if ($existingTotal + $batchTotal > $approvedBudgetTotal + 0.01) {
                $remaining = max(0, $approvedBudgetTotal - $existingTotal);
                throw new RuntimeException('لا يمكن أن يتجاوز إجمالي التمويل الميزانية المعتمدة (' . number_format($approvedBudgetTotal, 2) . '). المتبقي المتاح للتخصيص: ' . number_format($remaining, 2) . '.');
            }

            $activeBudgetId = (int)(dbFetchOne(
                "SELECT id FROM project_budgets WHERE project_id = ? AND status = 'approved' ORDER BY version_no DESC LIMIT 1",
                [$id]
            )['id'] ?? 0) ?: null;

            foreach ($rowsToInsert as $row) {
                dbExecute('INSERT INTO project_funding_allocations (project_id, budget_id, source_type, source_account_id, destination_account_id, transaction_id, amount, currency_code, allocation_date, reference_number, description, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                    $id, $activeBudgetId, $row['account']['code'], $row['account']['id'], null, null, $row['amount'],
                    $project['currency_code'] ?: 'SDG',
                    $row['date'],
                    $row['reference'],
                    $row['description'],
                    'draft', akp_user_id()
                ]);
                $allocationId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
                akp_audit('CREATE', 'project_funding_allocation', $allocationId, null, [
                    'project_id' => $id,
                    'amount' => $row['amount'],
                    'source_account' => $row['account']['code']
                ]);
            }

            $remainingAfter = max(0, $approvedBudgetTotal - $existingTotal - $batchTotal);
            flash('success', 'تم تسجيل تخصيصات التمويل بنجاح. المتبقي من الميزانية المعتمدة: ' . number_format($remainingAfter, 2) . '.');

        } elseif ($action === 'edit_funding') {
            $approvalStatus = (string)(dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id])['approval_status'] ?? '');
            if ($closed) throw new RuntimeException('لا يمكن تعديل تخصيص تمويل لمشروع مغلق.');
            if (!in_array($approvalStatus, ['submitted', 'rejected'], true) || !akp_can_manage_funding($id)) {
                throw new RuntimeException('تعديل تخصيصات التمويل أثناء المراجعة المالية أو بعد الرفض المالي محصور بالمدير المالي.');
            }

            $allocationId = (int)($_POST['allocation_id'] ?? 0);
            $allocation = dbFetchOne('SELECT * FROM project_funding_allocations WHERE id = ? AND project_id = ?', [$allocationId, $id]);
            if (!$allocation) throw new RuntimeException('تخصيص التمويل غير موجود.');

            $sourceAccountId = (int)($_POST['source_account_id'] ?? 0);
            $amount = (float)($_POST['funding_amount'] ?? 0);
            $sourceAccount = $sourceAccountId ? dbFetchOne('SELECT id, code, name_ar FROM accounts WHERE id = ?', [$sourceAccountId]) : null;
            if (!$sourceAccount || !in_array($sourceAccount['code'], ['1100', '1200', '1300'], true)) {
                throw new RuntimeException('يجب اختيار مصدر تمويل صالح: الصندوق النقدي (1100)، البنك (1200)، أو المحفظة الإلكترونية (1300).');
            }
            if ($amount <= 0) throw new RuntimeException('مبلغ التمويل يجب أن يكون أكبر من صفر.');

            $approvedBudgetRow = dbFetchOne(
                "SELECT COALESCE(SUM(bl.estimated_amount), 0) AS n
                 FROM project_budgets b
                 JOIN project_budget_lines bl ON bl.budget_id = b.id
                 WHERE b.project_id = ? AND b.status = 'approved'",
                [$id]
            );
            $proposedBudget = (float)($approvedBudgetRow['n'] ?? 0);
            if ($proposedBudget <= 0) {
                throw new RuntimeException('لا يمكن تسجيل التمويل قبل اعتماد نسخة الميزانية.');
            }

            $otherTotal = (float)(dbFetchOne(
                'SELECT COALESCE(SUM(amount),0) AS n FROM project_funding_allocations WHERE project_id = ? AND id <> ?',
                [$id, $allocationId]
            )['n'] ?? 0);
            if ($otherTotal + $amount > $proposedBudget + 0.01) {
                $remaining = max(0, $proposedBudget - $otherTotal);
                throw new RuntimeException('لا يمكن أن يتجاوز إجمالي التمويل الميزانية المقترحة (' . number_format($proposedBudget, 2) . '). الحد الأقصى لهذا السجل: ' . number_format($remaining, 2) . '.');
            }

            dbExecute('START TRANSACTION');
            try {
                $oldAmount = (float)$allocation['amount'];
                $oldApprovalJournal = null;
                $newApprovalJournal = null;

                if ($approvalStatus === 'approved') {
                    $oldApprovalJournal = dbFetchOne("SELECT id FROM journal_entries WHERE reference_type = 'project' AND reference_id = ? ORDER BY id DESC LIMIT 1", [$id]);
                    if ($oldApprovalJournal) {
                        akp_reverse_project_journal((int)$oldApprovalJournal['id'], $id, $project['name'], 'تعديل تخصيص تمويل رقم ' . $allocationId);
                    }
                }

                dbExecute('UPDATE project_funding_allocations SET source_type = ?, source_account_id = ?, amount = ?, currency_code = ?, allocation_date = ?, reference_number = ?, description = ? WHERE id = ? AND project_id = ?', [
                    $sourceAccount['code'],
                    $sourceAccount['id'],
                    $amount,
                    akp_post_value('funding_currency', $project['currency_code'] ?: 'SDG'),
                    akp_post_value('allocation_date', date('Y-m-d')),
                    akp_post_value('funding_reference') ?: null,
                    akp_post_value('funding_description') ?: null,
                    $allocationId,
                    $id
                ]);

                if ($approvalStatus === 'approved') {
                    $newApprovalJournal = akp_create_project_approval_journal($id, $project['name'], $proposedBudget);
                }

                dbExecute('COMMIT');
                akp_audit('UPDATE', 'project_funding_allocation', $allocationId,
                    ['project_id' => $id, 'amount' => $oldAmount],
                    ['project_id' => $id, 'amount' => $amount, 'approval_status' => $approvalStatus,
                     'replaced_journal_id' => $oldApprovalJournal['id'] ?? null, 'new_journal_id' => $newApprovalJournal]
                );
                flash('success', $approvalStatus === 'approved'
                    ? 'تم تعديل تخصيص التمويل. تم عكس القيد السابق وإنشاء القيد الجديد تلقائياً.'
                    : 'تم تعديل تخصيص التمويل بنجاح.');
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                throw $e;
            }

        } elseif ($action === 'delete_funding') {
            $approvalStatus = (string)(dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id])['approval_status'] ?? '');
            if ($closed) throw new RuntimeException('لا يمكن حذف تخصيص تمويل من مشروع مغلق.');
            if (!in_array($approvalStatus, ['submitted', 'rejected'], true) || !akp_can_manage_funding($id)) {
                throw new RuntimeException('حذف تخصيصات التمويل أثناء المراجعة المالية أو بعد الرفض المالي محصور بالمدير المالي.');
            }

            $allocationId = (int)($_POST['allocation_id'] ?? 0);
            $allocation = dbFetchOne('SELECT * FROM project_funding_allocations WHERE id = ? AND project_id = ?', [$allocationId, $id]);
            if (!$allocation) throw new RuntimeException('تخصيص التمويل غير موجود.');

            dbExecute('START TRANSACTION');
            try {
                $oldApprovalJournal = null;
                $newApprovalJournal = null;

                if ($approvalStatus === 'approved') {
                    $oldApprovalJournal = dbFetchOne("SELECT id FROM journal_entries WHERE reference_type = 'project' AND reference_id = ? ORDER BY id DESC LIMIT 1", [$id]);
                    if ($oldApprovalJournal) {
                        akp_reverse_project_journal((int)$oldApprovalJournal['id'], $id, $project['name'], 'حذف تخصيص تمويل رقم ' . $allocationId);
                    }
                }

                dbExecute('DELETE FROM project_funding_allocations WHERE id = ? AND project_id = ?', [$allocationId, $id]);

                if ($approvalStatus === 'approved') {
                    $lifecycle = dbFetchOne('SELECT final_budget_amount FROM project_lifecycle WHERE project_id = ?', [$id]);
                    $budgetFallback = (float)($lifecycle['final_budget_amount'] ?? 0);
                    $newApprovalJournal = akp_create_project_approval_journal($id, $project['name'], $budgetFallback);
                }

                dbExecute('COMMIT');
                akp_audit('DELETE', 'project_funding_allocation', $allocationId,
                    ['project_id' => $id, 'amount' => $allocation['amount']],
                    ['approval_status' => $approvalStatus,
                     'replaced_journal_id' => $oldApprovalJournal['id'] ?? null, 'new_journal_id' => $newApprovalJournal]
                );
                flash('success', $approvalStatus === 'approved'
                    ? 'تم حذف تخصيص التمويل. تم عكس القيد السابق وإنشاء القيد الجديد تلقائياً.'
                    : 'تم حذف تخصيص التمويل.');
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                throw $e;
            }

        } elseif ($action === 'edit_budget_line') {
            if (!akp_can_prepare_finance($id) || $closed) throw new RuntimeException('تعديل بنود الميزانية قبل المراجعة المالية محصور بمدير المشاريع.');
            $lineId = (int)($_POST['line_id'] ?? 0);
            $line = dbFetchOne('SELECT bl.*, b.status AS budget_status, b.project_id FROM project_budget_lines bl JOIN project_budgets b ON b.id = bl.budget_id WHERE bl.id = ?', [$lineId]);
            if (!$line || (int)$line['project_id'] !== $id) throw new RuntimeException('بند الميزانية غير موجود.');
            if ($line['budget_status'] !== 'draft') throw new RuntimeException('لا يمكن تعديل بند من نسخة ميزانية معتمدة.');
            $category = akp_post_value('line_category');
            $description = akp_post_value('line_description');
            $estimate = (float)($_POST['estimated_amount'] ?? 0);
            if ($category === '' || $description === '' || $estimate <= 0) throw new RuntimeException('الفئة والوصف والمبلغ التقديري مطلوبة.');
            dbExecute('UPDATE project_budget_lines SET category = ?, description = ?, estimated_amount = ?, notes = ? WHERE id = ?', [$category, $description, $estimate, akp_post_value('line_notes') ?: null, $lineId]);
            akp_audit('UPDATE', 'project_budget_line', $lineId, ['amount' => $line['estimated_amount']], ['amount' => $estimate]);
            flash('success', 'تم تعديل بند الميزانية.');

        } elseif ($action === 'delete_budget_line') {
            if (!akp_can_prepare_finance($id) || $closed) throw new RuntimeException('حذف بنود الميزانية قبل المراجعة المالية محصور بمدير المشاريع.');
            $lineId = (int)($_POST['line_id'] ?? 0);
            $line = dbFetchOne('SELECT bl.*, b.status AS budget_status, b.project_id FROM project_budget_lines bl JOIN project_budgets b ON b.id = bl.budget_id WHERE bl.id = ?', [$lineId]);
            if (!$line || (int)$line['project_id'] !== $id) throw new RuntimeException('بند الميزانية غير موجود.');
            if ($line['budget_status'] !== 'draft') throw new RuntimeException('لا يمكن حذف بند من نسخة ميزانية معتمدة.');
            dbExecute('DELETE FROM project_budget_lines WHERE id = ?', [$lineId]);
            akp_audit('DELETE', 'project_budget_line', $lineId, ['amount' => $line['estimated_amount']], null);
            flash('success', 'تم حذف بند الميزانية.');

        } elseif ($action === 'approve_funding') {
            throw new RuntimeException('هذه الخطوة لم تعد مطلوبة — يتم اعتماد كل مصادر التمويل تلقائياً عند الاعتماد المالي للمشروع بالكامل.');

        } elseif ($action === 'post_funding') {
            throw new RuntimeException('هذه الخطوة لم تعد مطلوبة — يتم ترحيل القيد المحاسبي تلقائياً عند الاعتماد النهائي من المدير العام.');
            flash('success', 'تم ترحيل تخصيص التمويل بقيد مزدوج متوازن.');
            
        } elseif ($action === 'add_expense') {
            // ... (Original add_expense logic preserved exactly)
            if (!akp_can_edit_section('finance', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إدخال مصروف.');
            $amount = (float)($_POST['expense_amount'] ?? 0);
            $description = akp_post_value('expense_description');
            if ($amount <= 0 || $description === '') throw new RuntimeException('وصف المصروف والمبلغ مطلوبان.');
            $expenseAccount = (int)($_POST['expense_account_id'] ?? 0) ?: null;
            $paymentAccount = (int)($_POST['payment_account_id'] ?? 0) ?: null;
            if (!$expenseAccount || !$paymentAccount) throw new RuntimeException('حساب المصروف وحساب الدفع مطلوبان.');
            $primaryDocumentId = (int)($_POST['primary_document_id'] ?? 0) ?: null;
            if ($primaryDocumentId && !dbFetchOne('SELECT id FROM project_documents WHERE id = ? AND project_id = ?', [$primaryDocumentId, $id])) throw new RuntimeException('مستند المصروف غير موجود لهذا المشروع.');
            if (!dbFetchOne('SELECT id FROM accounts WHERE id = ?', [$expenseAccount]) || !dbFetchOne('SELECT id FROM accounts WHERE id = ?', [$paymentAccount])) throw new RuntimeException('حساب المصروف أو الدفع غير موجود.');
            dbExecute('INSERT INTO project_expenses (project_id, budget_id, budget_line_id, expense_date, category, description, vendor_name, vendor_contact, invoice_number, government_fee_type, amount, currency_code, transaction_reference, expense_account_id, payment_account_id, primary_document_id, status, submitted_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$id, (int)($_POST['expense_budget_id'] ?? 0) ?: null, (int)($_POST['budget_line_id'] ?? 0) ?: null, akp_post_value('expense_date', date('Y-m-d')), akp_post_value('expense_category', 'عام'), $description, akp_post_value('vendor_name') ?: null, akp_post_value('vendor_contact') ?: null, akp_post_value('invoice_number') ?: null, akp_post_value('government_fee_type') ?: null, $amount, akp_post_value('expense_currency', $project['currency_code'] ?: 'SDG'), akp_post_value('transaction_reference') ?: null, $expenseAccount, $paymentAccount, $primaryDocumentId, 'draft', akp_user_id()]);
            $expenseId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
            akp_audit('CREATE', 'project_expense', $expenseId, null, ['project_id' => $id, 'amount' => $amount]);
            flash('success', 'تم حفظ المصروف كمسودة. أرفق المستند ثم أرسله للاعتماد.');
            
        } elseif ($action === 'submit_expense') {
            // ... (Original submit_expense logic preserved exactly)
            if (!akp_can_edit_section('finance', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إرسال المصروف.');
            $expenseId = (int)($_POST['expense_id'] ?? 0);
            $expense = dbFetchOne('SELECT * FROM project_expenses WHERE id = ? AND project_id = ?', [$expenseId, $id]);
            if (!$expense || $expense['status'] !== 'draft') throw new RuntimeException('المصروف ليس في حالة مسودة.');
            dbExecute("UPDATE project_expenses SET status = 'submitted', submitted_by = ? WHERE id = ? AND project_id = ?", [akp_user_id(), $expenseId, $id]);
            dbExecute("INSERT INTO project_expense_approvals (expense_id, action, actor_id) VALUES (?, 'submitted', ?)", [$expenseId, akp_user_id()]);
            flash('success', 'تم إرسال المصروف للاعتماد.');
            
        } elseif ($action === 'approve_expense') {
            // ... (Original approve_expense logic preserved exactly)
            if (!akp_can_edit_section('finance', $id) || $closed) throw new RuntimeException('لا تملك صلاحية اعتماد المصروف.');
            $expenseId = (int)($_POST['expense_id'] ?? 0);
            $expense = dbFetchOne('SELECT * FROM project_expenses WHERE id = ? AND project_id = ?', [$expenseId, $id]);
            if (!$expense || $expense['status'] !== 'submitted') throw new RuntimeException('المصروف ليس في حالة مرسل.');
            dbExecute("UPDATE project_expenses SET status = 'approved', approved_by = ? WHERE id = ? AND project_id = ?", [akp_user_id(), $expenseId, $id]);
            dbExecute("INSERT INTO project_expense_approvals (expense_id, action, actor_id) VALUES (?, 'approved', ?)", [$expenseId, akp_user_id()]);
            flash('success', 'تم اعتماد المصروف ويمكن ترحيله محاسبياً.');
            
        } elseif ($action === 'post_expense') {
            // ... (Original post_expense logic preserved exactly)
            if (!in_array($role, ['admin', 'accountant', 'general_manager'], true) || $closed) throw new RuntimeException('ترحيل المصروفات محصور بالمحاسب أو المدير العام.');
            $expenseId = (int)($_POST['expense_id'] ?? 0);
            $expense = dbFetchOne('SELECT * FROM project_expenses WHERE id = ? AND project_id = ?', [$expenseId, $id]);
            if (!$expense || $expense['status'] !== 'approved') throw new RuntimeException('المصروف يجب أن يكون معتمداً أولاً.');
            if (!$expense['payment_account_id'] || !$expense['expense_account_id']) throw new RuntimeException('حسابات المصروف والدفع مطلوبة.');
            dbExecute('START TRANSACTION');
            try {
                $entryCode = 'PRJ-EXP-' . $expenseId . '-' . date('YmdHis');
                dbExecute("INSERT INTO journal_entries (entry_code, entry_date, description, reference_type, reference_id, status, created_by) VALUES (?,?,?,?,?,'posted',?)", [$entryCode, date('Y-m-d'), 'مصروف مشروع: ' . $project['name'] . ' - ' . $expense['description'], 'project_expense', $expenseId, akp_user_id()]);
                $entryId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
                dbExecute('INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)', [$entryId, $expense['expense_account_id'], $expense['amount'], 0, 'مصروف مشروع']);
                dbExecute('INSERT INTO journal_lines (entry_id, account_id, debit, credit, description) VALUES (?, ?, ?, ?, ?)', [$entryId, $expense['payment_account_id'], 0, $expense['amount'], 'مصروف مشروع']);
                dbExecute("UPDATE project_expenses SET status = 'posted', posted_by = ?, journal_entry_id = ? WHERE id = ?", [akp_user_id(), $entryId, $expenseId]);
                dbExecute("INSERT INTO project_expense_approvals (expense_id, action, actor_id) VALUES (?, 'posted', ?)", [$expenseId, akp_user_id()]);
                dbExecute('COMMIT');
            } catch (Throwable $e) {
                dbExecute('ROLLBACK');
                throw $e;
            }
            akp_audit('POST', 'project_expense', $expenseId, ['status' => 'approved'], ['status' => 'posted', 'journal_entry_id' => $entryId]);
            flash('success', 'تم ترحيل المصروف بقيد مزدوج متوازن.');
            
        } elseif ($action === 'upload_document') {
            // ... (Original upload_document logic preserved exactly)
            if (!akp_can_edit_section('documents', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إضافة وثائق لهذا المشروع.');
            if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('فشل في رفع الملف.');
            $file = $_FILES['document'];
            if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('حجم وثيقة المشروع يجب ألا يتجاوز 10 ميجابايت.');
            $mime = mime_content_type($file['tmp_name']);
            $allowed = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx'];
            if (!isset($allowed[$mime])) throw new RuntimeException('نوع الملف غير مسموح. استخدم PDF أو JPG أو PNG أو DOCX أو XLSX.');
            $relativeDir = 'storage/documents/projects/' . $id;
            $absoluteDir = dirname(__DIR__, 2) . '/' . $relativeDir;
            if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true)) throw new RuntimeException('تعذر إنشاء مجلد الوثائق.');
            $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($file['tmp_name'], $absoluteDir . '/' . $stored)) throw new RuntimeException('تعذر حفظ الملف.');
            $relativePath = $relativeDir . '/' . $stored;
            dbExecute('INSERT INTO project_documents (project_id, document_type, title, file_path, original_name, mime_type, file_size, document_date, issuer, reference_number, amount, currency_code, notes, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$id, akp_post_value('document_type', 'other'), akp_post_value('document_title'), $relativePath, $file['name'], $mime, $file['size'], akp_post_value('document_date') ?: null, akp_post_value('document_issuer') ?: null, akp_post_value('document_reference') ?: null, (float)(akp_post_value('document_amount') ?: 0), akp_post_value('document_currency', $project['currency_code'] ?: 'SDG'), akp_post_value('document_notes') ?: null, akp_user_id()]);
            $documentId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
            akp_audit('UPLOAD', 'project_document', $documentId, null, ['project_id' => $id, 'document_type' => akp_post_value('document_type', 'other')]);
            flash('success', 'تم حفظ الوثيقة في التخزين المحمي.');
            
        } elseif ($action === 'verify_document') {
            // ... (Original verify_document logic preserved exactly)
            if (!akp_can_edit_section('documents', $id) || $closed) throw new RuntimeException('لا تملك صلاحية التحقق من الوثائق.');
            $documentId = (int)($_POST['document_id'] ?? 0);
            $verification = akp_post_value('verification_status');
            if (!in_array($verification, ['verified','rejected'], true)) throw new RuntimeException('حالة التحقق غير صالحة.');
            $rejectionReason = akp_post_value('rejection_reason');
            if ($verification === 'rejected' && $rejectionReason === '') throw new RuntimeException('سبب رفض الوثيقة مطلوب.');
            $doc = dbFetchOne('SELECT * FROM project_documents WHERE id = ? AND project_id = ?', [$documentId, $id]);
            if (!$doc) throw new RuntimeException('الوثيقة غير موجودة.');
            dbExecute('UPDATE project_documents SET verification_status = ?, verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE id = ? AND project_id = ?', [$verification, akp_user_id(), $verification === 'rejected' ? $rejectionReason : null, $documentId, $id]);
            akp_audit('VERIFY', 'project_document', $documentId, ['verification_status' => $doc['verification_status']], ['verification_status' => $verification]);
            flash('success', 'تم تحديث حالة الوثيقة.');
            
        } elseif ($action === 'add_labor') {
            // ... (Original add_labor logic preserved exactly)
            if ($role !== 'project_supervisor' || (!akp_is_primary_supervisor($id) && !akp_has_project_section($id, 'operations')) || $closed) throw new RuntimeException('إضافة بيانات العمالة الخارجية متاحة لمشرف المشروع المكلّف فقط.');
            $providerType = akp_post_value('labor_provider_type', 'individual');
            $providerName = akp_post_value('labor_provider_name');
            $workDescription = akp_post_value('labor_work_description');
            $workers = max(1, (int)($_POST['labor_number_of_workers'] ?? 1));
            $amount = max(0, (float)($_POST['labor_payment_amount'] ?? 0));
            $paymentTiming = akp_post_value('labor_payment_timing', 'upon_completion');
            $laborStatus = akp_post_value('labor_status', 'planned');
            if (!in_array($providerType, ['individual', 'company'], true) || $providerName === '' || $workDescription === '' || $amount <= 0) throw new RuntimeException('نوع مقدم الخدمة والاسم ووصف العمل والمبلغ مطلوبة.');
            if (!in_array($paymentTiming, ['upfront', 'daily', 'weekly', 'monthly', 'upon_completion'], true)) throw new RuntimeException('توقيت الدفع غير صالح.');
            if (!in_array($laborStatus, ['planned', 'in_progress', 'completed'], true)) throw new RuntimeException('حالة العمالة غير صالحة.');
            if ($providerType === 'company' && akp_post_value('labor_contact_person_name') === '') throw new RuntimeException('اسم جهة الاتصال مطلوب للشركة.');
            dbExecute('INSERT INTO project_labor_helpers (project_id, supervisor_user_id, provider_type, provider_name, phone, contact_person_name, contact_person_phone, number_of_workers, work_description, payment_amount, currency_code, payment_timing, status, notes, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$id, akp_user_id(), $providerType, $providerName, akp_post_value('labor_phone') ?: null, akp_post_value('labor_contact_person_name') ?: null, akp_post_value('labor_contact_person_phone') ?: null, $workers, $workDescription, $amount, akp_post_value('labor_currency', $project['currency_code'] ?: 'SDG'), $paymentTiming, $laborStatus, akp_post_value('labor_notes') ?: null, akp_user_id(), akp_user_id()]);
            $laborId = (int)(dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
            akp_audit('CREATE', 'project_labor_helper', $laborId, null, ['project_id' => $id, 'provider_name' => $providerName, 'amount' => $amount]);
            flash('success', 'تم حفظ بيانات العامل/الجهة الخارجية.');
            
        } elseif ($action === 'comment_labor') {
            // ... (Original comment_labor logic preserved exactly)
            if (!akp_is_executive()) throw new RuntimeException('التعليق الإداري على العمالة الخارجية متاح للإدارة التنفيذية فقط.');
            $laborId = (int)($_POST['labor_id'] ?? 0);
            $comment = akp_post_value('labor_manager_comment');
            if (!$laborId || $comment === '' || !dbFetchOne('SELECT id FROM project_labor_helpers WHERE id = ? AND project_id = ?', [$laborId, $id])) throw new RuntimeException('سجل العمالة أو التعليق غير صالح.');
            dbExecute('INSERT INTO project_labor_comments (labor_id, manager_user_id, comment) VALUES (?,?,?)', [$laborId, akp_user_id(), $comment]);
            dbExecute('UPDATE project_labor_helpers SET manager_comment = ?, manager_comment_by = ?, manager_comment_at = NOW() WHERE id = ? AND project_id = ?', [$comment, akp_user_id(), $laborId, $id]);
            akp_audit('COMMENT', 'project_labor_helper', $laborId, null, ['project_id' => $id]);
            flash('success', 'تم حفظ تعليق مدير المشاريع.');
            
        } elseif ($action === 'add_milestone') {
            // ... (Original add_milestone logic preserved exactly)
            if (!akp_can_edit_section('operations', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إضافة مراحل.');
            $title = akp_post_value('milestone_title');
            if ($title === '') throw new RuntimeException('عنوان المرحلة مطلوب.');
            dbExecute('INSERT INTO project_milestones (project_id, title, description, planned_date, status, completion_percent, notes, created_by) VALUES (?,?,?,?,?,?,?,?)', [$id, $title, akp_post_value('milestone_description') ?: null, akp_post_value('planned_date') ?: null, akp_post_value('milestone_status', 'pending'), max(0, min(100, (float)($_POST['completion_percent'] ?? 0))), akp_post_value('milestone_notes') ?: null, akp_user_id()]);
            flash('success', 'تمت إضافة المرحلة.');
            
        } elseif ($action === 'add_progress') {
            // ... (Original add_progress logic preserved exactly)
            if (!akp_can_edit_section('operations', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إضافة تحديث تشغيلي.');
            $summary = akp_post_value('progress_summary');
            $progressPercentRaw = akp_post_value('progress_percent');
            $progressPercent = $progressPercentRaw === '' ? 0.0 : (float)$progressPercentRaw;
            if ($progressPercent < 0 || $progressPercent > 100) throw new RuntimeException('نسبة الإنجاز يجب أن تكون بين 0 و100.');
            if ($summary === '') throw new RuntimeException('ملخص التقدم مطلوب.');
            dbExecute('INSERT INTO project_progress_updates (project_id, update_date, completion_percent, summary, achievements, issues, next_steps, submitted_by) VALUES (?,?,?,?,?,?,?,?)', [$id, akp_post_value('update_date') ?: date('Y-m-d'), $progressPercent, $summary, akp_post_value('achievements') ?: null, akp_post_value('issues') ?: null, akp_post_value('next_steps') ?: null, akp_user_id()]);
            akp_audit('CREATE', 'project_progress_update', (int)dbFetchOne('SELECT LAST_INSERT_ID() AS id')['id'], null, ['project_id' => $id]);
            flash('success', 'تم حفظ تحديث التقدم.');
            
        } elseif ($action === 'add_beneficiary_record') {
            // ... (Original add_beneficiary_record logic preserved exactly)
            if (!akp_can_edit_section('operations', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إضافة مستفيدين.');
            $beneficiaryName = akp_post_value('record_beneficiary_name');
            $householdCount = (int)($_POST['household_count'] ?? 0);
            $plannedSupport = $_POST['planned_support_amount'] !== '' ? (float)($_POST['planned_support_amount'] ?? 0) : null;
            $deliveredSupport = $_POST['delivered_support_amount'] !== '' ? (float)($_POST['delivered_support_amount'] ?? 0) : null;
            if ($beneficiaryName === '') throw new RuntimeException('اسم المستفيد مطلوب.');
            if ($householdCount < 0 || ($plannedSupport !== null && $plannedSupport < 0) || ($deliveredSupport !== null && $deliveredSupport < 0)) throw new RuntimeException('بيانات المستفيد المالية أو عدد الأفراد لا يمكن أن تكون سالبة.');
            dbExecute('INSERT INTO project_beneficiary_records (project_id, beneficiary_name, beneficiary_type, beneficiary_phone, beneficiary_location, household_count, planned_support_amount, delivered_support_amount, support_date, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$id, $beneficiaryName, akp_post_value('beneficiary_type') ?: null, akp_post_value('beneficiary_phone') ?: null, akp_post_value('beneficiary_location') ?: null, $householdCount ?: null, $plannedSupport, $deliveredSupport, akp_post_value('support_date') ?: null, akp_post_value('beneficiary_notes') ?: null, akp_user_id()]);
            flash('success', 'تمت إضافة سجل المستفيد.');
            
        } elseif ($action === 'close_project') {
            // ... (Original close_project logic preserved exactly)
            if (!akp_can_edit_section('closure', $id) || $closed) throw new RuntimeException('لا تملك صلاحية إغلاق المشروع أو أنه مغلق مسبقاً.');
            $pending = dbFetchOne("SELECT COUNT(*) AS n FROM project_expenses WHERE project_id = ? AND status IN ('draft','submitted','approved')", [$id]);
            if ((int)($pending['n'] ?? 0) > 0) throw new RuntimeException('لا يمكن الإغلاق مع وجود مصروفات غير مرحلة.');
            $summary = akp_post_value('closure_summary');
            $varianceExplanation = akp_post_value('variance_explanation');
            if ($summary === '') throw new RuntimeException('ملخص الإغلاق مطلوب.');
            $totals = akp_sync_closure_totals($id);
            dbExecute("UPDATE project_lifecycle SET lifecycle_status = 'closed', closed_at = NOW(), closed_by = ?, close_reason = ?, closure_summary = ?, variance_explanation = ? WHERE project_id = ?", [akp_user_id(), akp_post_value('closure_reason', 'other'), $summary, $varianceExplanation, $id]);
            dbExecute("UPDATE other_projects SET status = 'completed', updated_by = ? WHERE id = ?", [akp_user_id(), $id]);
            dbExecute("INSERT INTO project_status_history (project_id, old_status, new_status, reason, changed_by) VALUES (?, ?, 'closed', ?, ?)", [$id, $project['lifecycle_status'] ?: $project['status'], $summary, akp_user_id()]);
            akp_audit('CLOSE', 'project_lifecycle', $id, ['status' => $project['lifecycle_status'] ?: $project['status']], ['status' => 'closed', 'reason' => $summary]);
            flash('success', 'تم إغلاق المشروع. لن يستطيع تعديله بعد ذلك إلا المدير العام.');
            
        } elseif ($action === 'reopen_project') {
            // ... (Original reopen_project logic preserved exactly)
            if (!akp_is_dg()) throw new RuntimeException('إعادة فتح المشروع محصورة بالمدير العام.');
            if (!$closed) throw new RuntimeException('المشروع ليس مغلقاً.');
            $reason = akp_post_value('reopen_reason');
            if ($reason === '') throw new RuntimeException('سبب إعادة الفتح مطلوب.');
            dbExecute("UPDATE project_lifecycle SET lifecycle_status = 'reopened', reopened_at = NOW(), reopened_by = ?, reopen_reason = ? WHERE project_id = ?", [akp_user_id(), $reason, $id]);
            dbExecute("UPDATE other_projects SET status = 'active', updated_by = ? WHERE id = ?", [akp_user_id(), $id]);
            dbExecute("INSERT INTO project_status_history (project_id, old_status, new_status, reason, changed_by) VALUES (?, 'closed', 'reopened', ?, ?)", [$id, $reason, akp_user_id()]);
            akp_audit('REOPEN', 'project_lifecycle', $id, ['status' => 'closed'], ['status' => 'reopened', 'reason' => $reason]);
            flash('success', 'تمت إعادة فتح المشروع.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    
    akp_redirect_project($id);
}

$currency = $project['currency_code'] ?: 'SDG';
$status = $project['lifecycle_status'] ?: $project['status'];
$badge = ['planned'=>'bg-secondary','active'=>'bg-success','completed'=>'bg-info','under_review'=>'bg-warning text-dark','closed'=>'bg-dark','reopened'=>'bg-primary','cancelled'=>'bg-danger'][$status] ?? 'bg-secondary';
$varianceClass = $totals['variance'] > 0 ? 'text-danger' : 'text-success';

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<link rel="stylesheet" href="<?php echo e(APP_URL . 'assets/css/projects-ui.css'); ?>">

<div class="project-module-page">

<div class="project-page-banner fade-in">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h2><?php echo e($project['name']); ?></h2>
            <p><code><?php echo e($project['project_code'] ?? ''); ?></code> · <?php echo e($project['project_type'] ?? ''); ?> · <span class="badge <?php echo $badge; ?>"><?php echo e(akp_status_label($status)); ?></span> · اعتماد: <span class="badge bg-light text-dark"><?php echo e($approval['approval_status']); ?></span></p>
            <?php if ($primarySupervisor): ?>
                <p class="small mb-0"><i class="fas fa-user-tie me-1"></i>مشرف المشروع: <strong><?php echo e($primarySupervisor['full_name']); ?></strong></p>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <?php if ($role === 'projects_manager' && in_array($approval['approval_status'], ['draft', 'rejected'], true)): ?>
                <a href="<?php echo e(APP_URL . 'modules/projects/form.php?id=' . $id); ?>" class="btn btn-primary text-white">
                    <i class="fas fa-edit me-1"></i> تعديل المشروع
                </a>
                <form method="post" class="project-action-form d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="submit_project">
                    <button class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> إرسال للمدير المالي</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<?php if ($approval['approval_status'] === 'submitted'): ?>
    <div class="alert alert-info fade-in"><i class="fas fa-info-circle me-2"></i>المشروع بانتظار المراجعة والاعتماد المالي من المدير المالي.</div>
<?php elseif ($approval['approval_status'] === 'fm_approved'): ?>
    <div class="alert alert-warning fade-in"><i class="fas fa-clock me-2"></i>تم اعتماد المشروع مالياً. بانتظار الاعتماد النهائي من المدير العام.</div>
<?php elseif ($approval['approval_status'] === 'rejected'): ?>
    <div class="alert alert-danger fade-in">
        <i class="fas fa-exclamation-triangle me-2"></i>تم رفض المشروع.
        <?php if (!empty($approval['fm_rejection_reason'])): ?>
            <br><strong>سبب الرفض المالي:</strong> <?php echo e($approval['fm_rejection_reason']); ?>
        <?php elseif (!empty($approval['rejection_reason'])): ?>
            <br><strong>سبب الرفض النهائي:</strong> <?php echo e($approval['rejection_reason']); ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($role === 'financial_manager' && $approval['approval_status'] === 'submitted'): ?>
    <div class="card mb-4 fade-in border-primary">
        <div class="card-header bg-primary text-white"><i class="fas fa-money-check-alt me-2"></i>مراجعة المدير المالي</div>
        <div class="card-body">
            <div class="project-module-note mb-3">راجع الميزانية المعتمدة وتخصيصات التمويل. يجب تحديد حسابات التمويل الفعلية (1100 النقدية، 1200 البنك، 1300 المحفظة الإلكترونية) وتخصيص كامل مبلغ الميزانية قبل الاعتماد.</div>
            <form method="post" class="project-action-form d-inline">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="fm_approve_project">
                <button class="btn btn-success" onclick="return confirm('هل أنت متأكد من اعتماد هذا المشروع مالياً؟')"><i class="fas fa-check me-1"></i> اعتماد مالي</button>
            </form>
            <form method="post" class="project-action-form d-inline ms-2">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="fm_reject_project">
                <input type="text" name="rejection_reason" class="form-control d-inline-block" style="width: 300px;" placeholder="سبب الرفض المالي (مطلوب)" required>
                <button class="btn btn-danger ms-2"><i class="fas fa-times me-1"></i> رفض</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($role === 'financial_manager' && $approval['approval_status'] === 'fm_approved'): ?>
    <div class="card mb-4 fade-in border-warning">
        <div class="card-header bg-warning text-dark"><i class="fas fa-rotate-left me-2"></i>استكمال تخصيص التمويل</div>
        <div class="card-body">
            <p class="mb-3">هذا المشروع تم اعتماده مالياً قبل تطبيق شرط تخصيص التمويل الصريح. أعده للمراجعة المالية، ثم سجّل حسابات التمويل الفعلية قبل إعادة الاعتماد.</p>
            <form method="post" class="project-action-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="fm_return_to_review">
                <div class="input-group">
                    <input type="text" name="return_reason" class="form-control" placeholder="سبب إعادة المراجعة (مطلوب)" required value="استكمال تخصيص حسابات تمويل المشروع">
                    <button class="btn btn-warning" onclick="return confirm('سيُعاد المشروع إلى مرحلة المراجعة المالية. هل تريد المتابعة؟')"><i class="fas fa-rotate-left me-1"></i> إعادة للمراجعة المالية</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if (in_array($role, ['admin', 'general_manager', 'vice_general_manager'], true) && $approval['approval_status'] === 'fm_approved'): ?>
    <div class="card mb-4 fade-in border-success">
        <div class="card-header bg-success text-white"><i class="fas fa-user-tie me-2"></i>اعتماد المدير العام</div>
        <div class="card-body">
            <p class="mb-3">المشروع معتمد مالياً. راجع مصادر التمويل والمبالغ المسجلة أدناه، ثم اعتمد نهائياً. سيُنشأ القيد المحاسبي من حسابات التمويل التي اعتمدها المدير المالي فقط.</p>
            <form method="post" class="project-action-form d-inline">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="approve_project">
                <button class="btn btn-success" onclick="return confirm('هل أنت متأكد من الاعتماد النهائي وإنشاء القيد المحاسبي؟')"><i class="fas fa-check-double me-1"></i> اعتماد نهائي وإنشاء قيد</button>
            </form>
            <form method="post" class="project-action-form d-inline ms-2">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reject_project">
                <input type="text" name="rejection_reason" class="form-control d-inline-block" style="width: 300px;" placeholder="سبب الرفض النهائي (مطلوب)" required>
                <button class="btn btn-danger ms-2"><i class="fas fa-times me-1"></i> رفض</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Rest of the original UI continues exactly as it was -->
<div class="project-summary-grid mb-4">
    <div class="project-summary-card">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-5 fw-bold text-primary"><?php echo akp_money($totals['approved_budget'] ?? 0); ?></div>
                <div class="text-muted small">الميزانية النهائية</div>
            </div>
        </div>
    </div>
    <div class="project-summary-card">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-5 fw-bold text-success"><?php echo akp_money($totals['total_funded'] ?? 0); ?></div>
                <div class="text-muted small">التمويل المعتمد</div>
            </div>
        </div>
    </div>
    <div class="project-summary-card">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-5 fw-bold text-danger"><?php echo akp_money($totals['total_expensed'] ?? 0); ?></div>
                <div class="text-muted small">المصروفات المرحلة</div>
            </div>
        </div>
    </div>
    <div class="project-summary-card">
        <div class="card text-center">
            <div class="card-body py-2">
                <div class="fs-5 fw-bold <?php echo $varianceClass; ?>"><?php echo akp_money($totals['variance']); ?></div>
                <div class="text-muted small">فرق الميزانية</div>
            </div>
        </div>
    </div>
</div>

<?php if ($status === 'closed'): ?>
    <div class="alert alert-dark"><strong>المشروع مغلق.</strong> لا يمكن تعديل أي قسم أو إضافة مستندات أو مصروفات. إعادة الفتح متاحة للمدير العام فقط.</div>
<?php endif; ?>

<div class="row g-4 project-view-sections-grid">
    <div class="col-lg-8 project-view-main-column">
        <div class="card mb-4 fade-in">
            <div class="card-header"><i class="fas fa-circle-info me-2"></i>ملخص المشروع</div>
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-md-6"><strong>الوصف</strong><div><?php echo nl2br(e($project['description'] ?? '—')); ?></div></div>
                    <div class="col-md-6"><strong>الأهداف</strong><div><?php echo nl2br(e($details['objectives'] ?? '—')); ?></div></div>
                    <div class="col-md-6"><strong>الموقع</strong><div><?php echo e(implode(' · ', array_filter([$project['location'], $project['city'], $project['district']])) ?: '—'); ?></div></div>
                    <div class="col-md-6"><strong>الفترة</strong><div><?php echo e(($project['start_date'] ?: '—') . ' → ' . ($project['end_date'] ?: '—')); ?></div></div>
                    <div class="col-md-6"><strong>النتائج المتوقعة</strong><div><?php echo nl2br(e($details['expected_outcomes'] ?? '—')); ?></div></div>
                    <div class="col-md-6"><strong>الشريك المنفذ</strong><div><?php echo e($details['implementing_partner'] ?? '—'); ?></div></div>
                    <div class="col-md-6"><strong>الاستدامة</strong><div><?php echo nl2br(e($details['sustainability_plan'] ?? '—')); ?></div></div>
                    <div class="col-md-6"><strong>المخاطر والحد منها</strong><div><?php echo nl2br(e($details['risk_mitigation'] ?? '—')); ?></div></div>
                </div>
            </div>
        </div>

        <div class="card mb-4 fade-in">
            <div class="card-header"><i class="fas fa-people-group me-2"></i>التنفيذ والشركاء والتوريد</div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold">الجهات المنفذة أو الشركاء</h6>
                        <?php if ($partnerRows): ?>
                            <?php foreach ($partnerRows as $partner): ?>
                                <div class="border-bottom py-2 small">
                                    <strong><?php echo e($partner['partner_name']); ?></strong>
                                    <?php if (!empty($partner['role_description'])): ?>
                                        <div class="text-muted"><?php echo e($partner['role_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-muted small">لا توجد جهات منفذة أو شركاء مسجلون.</div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <h6 class="fw-bold">طرق الشراء أو التوريد</h6>
                        <?php if ($procurementRows): ?>
                            <?php foreach ($procurementRows as $procurement): ?>
                                <div class="border-bottom py-2 small">
                                    <strong><?php echo e($procurement['method_name']); ?></strong>
                                    <?php if (!empty($procurement['notes'])): ?>
                                        <div class="text-muted"><?php echo e($procurement['notes']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-muted small">لا توجد طرق شراء أو توريد مسجلة.</div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0">المتطلبات الحكومية الأولية</h6>
                            <?php
                            $governmentFeesTotal = 0.0;
                            foreach ($governmentRequirementRows as $requirement) {
                                $governmentFeesTotal += (float)($requirement['fee_amount'] ?? 0);
                            }
                            ?>
                            <?php if ($governmentRequirementRows): ?>
                                <span class="small text-muted">إجمالي الرسوم: <strong><?php echo akp_money($governmentFeesTotal); ?> SDG</strong></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($governmentRequirementRows): ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>المتطلب</th>
                                            <th class="text-nowrap">الرسوم (SDG)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($governmentRequirementRows as $requirement): ?>
                                            <tr>
                                                <td><?php echo e($requirement['requirement_text']); ?></td>
                                                <td class="text-nowrap"><?php echo akp_money($requirement['fee_amount']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-muted small">لا توجد متطلبات حكومية مسجلة.</div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <h6 class="fw-bold">بيانات الاتصال</h6>
                        <?php if ($contactRows): ?>
                            <?php foreach ($contactRows as $contact): ?>
                                <div class="border-bottom py-2 small">
                                    <strong><?php echo e($contact['contact_name']); ?></strong>
                                    <?php if (!empty($contact['role_description'])): ?>
                                        <div class="text-muted"><?php echo e($contact['role_description']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($contact['phone'])): ?>
                                        <div>الهاتف: <?php echo e($contact['phone']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($contact['email'])): ?>
                                        <div>البريد: <?php echo e($contact['email']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($contact['notes'])): ?>
                                        <div class="text-muted"><?php echo e($contact['notes']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-muted small">لا توجد جهات اتصال مسجلة.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 fade-in">
            <div class="card-body">
                <div class="card border-primary mb-4">
                    <div class="card-header bg-primary text-white"><i class="fas fa-file-invoice-dollar me-2"></i>الميزانية</div>
                    <div class="card-body">
                <?php if ($approvedBudgetId && in_array((string)$approval['approval_status'], ['submitted', 'rejected'], true) && akp_can_manage_funding($id) && !$closed): ?>
                    <form method="post" class="project-form-panel" id="projectFundingForm">
                        <input type="hidden" name="action" value="add_funding">
                        <?php echo csrf_field(); ?>
                        <div id="fundingRows">
                            <div class="funding-row border rounded p-2 mb-2 bg-white">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label small">حساب التمويل</label>
                                        <select name="funding_source_account_id[]" class="form-select form-select-sm" required>
                                            <option value="">اختر الحساب الذي سيموّل المشروع</option>
                                            <?php foreach (dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE is_active = 1 AND code IN ('1100','1200','1300') ORDER BY code") as $account): ?>
                                                <option value="<?php echo (int)$account['id']; ?>"><?php echo e($account['code'] . ' · ' . $account['name_ar']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">المبلغ</label>
                                        <input type="number" step="0.01" min="0.01" name="funding_amount[]" class="form-control form-control-sm" placeholder="المبلغ" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">التاريخ</label>
                                        <input type="date" name="funding_allocation_date[]" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">المرجع</label>
                                        <input name="funding_reference[]" class="form-control form-control-sm" placeholder="المرجع">
                                    </div>
                                    <div class="col-md-1 d-flex justify-content-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-funding-row d-none" title="حذف مصدر التمويل"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div class="col-12">
                                        <input name="funding_description[]" class="form-control form-control-sm" placeholder="الوصف">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" class="btn btn-sm btn-outline-success" id="addFundingRow"><i class="fas fa-plus me-1"></i> إضافة مصدر تمويل آخر</button>
                            <span class="small text-muted">يمكن إضافة أكثر من مصدر في نفس العملية، من الصندوق أو البنك أو المحفظة الإلكترونية.</span>
                        </div>
                        <div class="mt-2 small">
                            إجمالي التخصيصات الحالية: <strong><?php echo akp_money(array_sum(array_map('floatval', array_column($fundings, 'amount')))); ?> <?php echo e($project['currency_code'] ?: 'SDG'); ?></strong>
                        </div>
                        <div class="col-12 mt-2 small text-muted">يجب أن يساوي مجموع التخصيصات الميزانية المعتمدة قبل الاعتماد المالي.</div>
                        <div class="col-12 mt-2"><button class="btn btn-sm btn-primary">حفظ تخصيصات التمويل</button></div>
                    </form>
                    <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const rows = document.getElementById('fundingRows');
                        const addButton = document.getElementById('addFundingRow');
                        if (!rows || !addButton) return;

                        function refreshRemoveButtons() {
                            const items = rows.querySelectorAll('.funding-row');
                            items.forEach(function (item) {
                                const remove = item.querySelector('.remove-funding-row');
                                if (remove) remove.classList.toggle('d-none', items.length === 1);
                            });
                        }

                        addButton.addEventListener('click', function () {
                            const source = rows.querySelector('.funding-row');
                            const clone = source.cloneNode(true);
                            clone.querySelectorAll('input').forEach(function (input) {
                                if (input.name === 'funding_allocation_date[]') input.value = '<?php echo date('Y-m-d'); ?>';
                                else input.value = '';
                            });
                            clone.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; });
                            rows.appendChild(clone);
                            refreshRemoveButtons();
                        });

                        rows.addEventListener('click', function (event) {
                            const button = event.target.closest('.remove-funding-row');
                            if (!button) return;
                            const row = button.closest('.funding-row');
                            if (row) row.remove();
                            refreshRemoveButtons();
                        });

                        refreshRemoveButtons();
                    });
                    </script>
                <?php endif; ?>

                <div class="table-responsive mt-3">
                    <table class="table table-sm">
                        <thead><tr><th>النسخة</th><th>الاسم</th><th>عدد البنود</th><th>الإجمالي</th><th>الحالة</th><th>إجراء</th></tr></thead>
                        <tbody>
                            <?php foreach ($budgets as $budget): ?>
                                <tr>
                                    <td><?php echo (int)$budget['version_no']; ?></td>
                                    <td><?php echo e($budget['budget_name']); ?></td>
                                    <td><?php echo (int)$budget['line_count']; ?></td>
                                    <td><?php echo number_format((float)$budget['line_total'], 2) . ' ' . e($budget['currency_code']); ?></td>
                                    <td><span class="badge <?php echo $budget['status'] === 'approved' ? 'bg-success' : ($budget['status'] === 'superseded' ? 'bg-secondary' : 'bg-warning text-dark'); ?>"><?php echo e($budget['status']); ?></span></td>
                                    <td>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$budgets): ?><tr><td colspan="6" class="text-center text-muted">لا توجد نسخ ميزانية.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                    </div>
                </div>

                <div class="card border-success mb-0">
                    <div class="card-header bg-success text-white"><i class="fas fa-money-bill-transfer me-2"></i>تخصيص التمويل</div>
                    <div class="card-body">
                <?php if ($role === 'financial_manager' && $approval['approval_status'] === 'submitted' && !$closed): ?>
                    <div class="alert alert-info small mb-3">
                        <i class="fas fa-eye me-1"></i>
                        تم إعداد تخصيصات التمويل قبل الإرسال. دور المدير المالي هنا هو المراجعة المالية والاعتماد أو الرفض، دون تعديل بيانات التمويل.
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>التاريخ</th><th>حساب التمويل</th><th>المبلغ</th><th>المرجع/الوصف</th><th>الحالة</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($fundings as $funding): ?>
                                <tr>
                                    <td><?php echo e($funding['allocation_date']); ?></td>
                                    <td><?php echo e((string)($funding['source_account_code'] ?? $funding['source_type'])); ?> · <?php echo e((string)($funding['source_account_name'] ?? '')); ?></td>
                                    <td><?php echo akp_money($funding['amount']); ?></td>
                                    <td><small><?php echo e((string)($funding['reference_number'] ?? '')); ?><?php if (!empty($funding['description'])): ?><br><?php echo e((string)$funding['description']); ?><?php endif; ?></small></td>
                                    <td><?php echo e($funding['status']); ?></td>
                                    <td>
                                        <?php if (in_array((string)$approval['approval_status'], ['submitted', 'rejected'], true) && akp_can_manage_funding($id) && !$closed): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal" data-bs-target="#editFundingModal"
                                                    data-id="<?php echo (int)$funding['id']; ?>"
                                                    data-source-account="<?php echo (int)$funding['source_account_id']; ?>"
                                                    data-amount="<?php echo e((string)$funding['amount']); ?>"
                                                    data-date="<?php echo e((string)$funding['allocation_date']); ?>"
                                                    data-reference="<?php echo e((string)($funding['reference_number'] ?? '')); ?>"
                                                    data-description="<?php echo e((string)($funding['description'] ?? '')); ?>">
                                                <i class="fas fa-edit"></i> تعديل
                                            </button>
                                            <form method="post" class="project-action-form d-inline" onsubmit="return confirm('هل أنت متأكد من حذف تخصيص التمويل هذا؟');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_funding">
                                                <input type="hidden" name="allocation_id" value="<?php echo (int)$funding['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> حذف</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; if (!$fundings): ?>
                                <tr><td colspan="6" class="text-center text-muted">لا توجد تخصيصات تمويل مسجلة بعد.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (in_array((string)$approval['approval_status'], ['submitted', 'rejected'], true) && akp_can_manage_funding($id) && !$closed): ?>
                    <div class="modal fade" id="editFundingModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="edit_funding">
                                    <input type="hidden" name="allocation_id" id="editFundingId">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>تعديل تخصيص التمويل</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?php if ($approval['approval_status'] === 'approved'): ?>
                                            <div class="alert alert-warning small">
                                                <i class="fas fa-calculator me-1"></i>
                                                هذا المشروع معتمد من المدير العام. عند حفظ التعديل سيتم عكس قيد الاعتماد السابق وإنشاء قيد جديد بالقيمة الصحيحة تلقائياً.
                                            </div>
                                        <?php endif; ?>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="form-label small">حساب المصدر</label>
                                                <select name="source_account_id" id="editFundingSourceAccount" class="form-select form-select-sm" required>
                                                    <option value="">اختر حساب التمويل</option>
                                                    <?php foreach (dbFetchAll("SELECT id, code, name_ar FROM accounts WHERE is_active = 1 AND code IN ('1100','1200','1300') ORDER BY code") as $account): ?>
                                                        <option value="<?php echo (int)$account['id']; ?>"><?php echo e($account['code'] . ' · ' . $account['name_ar']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">المبلغ</label>
                                                <input type="number" step="0.01" min="0.01" name="funding_amount" id="editFundingAmount" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small">تاريخ التخصيص</label>
                                                <input type="date" name="allocation_date" id="editFundingDate" class="form-control form-control-sm" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small">المرجع</label>
                                                <input name="funding_reference" id="editFundingReference" class="form-control form-control-sm">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small">الوصف</label>
                                                <input name="funding_description" id="editFundingDescription" class="form-control form-control-sm">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ التعديل</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var modal = document.getElementById('editFundingModal');
                        if (!modal) return;
                        modal.addEventListener('show.bs.modal', function (event) {
                            var button = event.relatedTarget;
                            document.getElementById('editFundingId').value = button.getAttribute('data-id') || '';
                            document.getElementById('editFundingSourceAccount').value = button.getAttribute('data-source-account') || '';
                            document.getElementById('editFundingAmount').value = button.getAttribute('data-amount') || '';
                            document.getElementById('editFundingDate').value = button.getAttribute('data-date') || '';
                            document.getElementById('editFundingReference').value = button.getAttribute('data-reference') || '';
                            document.getElementById('editFundingDescription').value = button.getAttribute('data-description') || '';
                        });
                    });
                    </script>
                <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($approval['approval_status'] === 'approved'): ?>
<div class="card mb-4 fade-in">
            <div class="card-header"><i class="fas fa-receipt me-2"></i>المصروفات</div>
            <div class="card-body">
                <?php if (akp_can_edit_section('finance', $id) && !$closed): ?>
                    <form method="post" class="project-form-panel">
                        <input type="hidden" name="action" value="add_expense">
                        <?php echo csrf_field(); ?>
                        <div class="row g-2">
                            <div class="col-md-2"><input type="date" name="expense_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>"></div>
                            <div class="col-md-2"><input name="expense_category" class="form-control form-control-sm" placeholder="الفئة" required></div>
                            <div class="col-md-2"><input type="number" step="0.01" min="0.01" name="expense_amount" class="form-control form-control-sm" placeholder="المبلغ" required></div>
                            <div class="col-md-6"><input name="expense_description" class="form-control form-control-sm" placeholder="وصف المصروف *" required></div>
                            <div class="col-md-3"><input name="vendor_name" class="form-control form-control-sm" placeholder="اسم المورد"></div>
                            <div class="col-md-3"><input name="invoice_number" class="form-control form-control-sm" placeholder="رقم الفاتورة"></div>
                            <div class="col-md-3"><input name="government_fee_type" class="form-control form-control-sm" placeholder="نوع الرسم الحكومي"></div>
                            <div class="col-md-3"><input name="transaction_reference" class="form-control form-control-sm" placeholder="مرجع الدفع"></div>
                            <div class="col-md-6">
                                <select name="expense_account_id" class="form-select form-select-sm" required>
                                    <option value="">حساب المصروف *</option>
                                    <?php foreach (dbFetchAll('SELECT id, code, name_ar FROM accounts WHERE is_active = 1 ORDER BY code') as $account): ?>
                                        <option value="<?php echo (int)$account['id']; ?>"><?php echo e($account['code'] . ' · ' . $account['name_ar']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <select name="payment_account_id" class="form-select form-select-sm" required>
                                    <option value="">حساب الدفع *</option>
                                    <?php foreach (dbFetchAll('SELECT id, code, name_ar FROM accounts WHERE is_active = 1 ORDER BY code') as $account): ?>
                                        <option value="<?php echo (int)$account['id']; ?>"><?php echo e($account['code'] . ' · ' . $account['name_ar']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <select name="primary_document_id" class="form-select form-select-sm">
                                    <option value="">إيصال/مستند المصروف (اختياري)</option>
                                    <?php foreach ($documents as $doc): ?>
                                        <option value="<?php echo (int)$doc['id']; ?>"><?php echo e($doc['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12"><button class="btn btn-sm btn-primary">حفظ المصروف</button></div>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>التاريخ</th><th>الوصف</th><th>المورد/الفاتورة</th><th>المبلغ</th><th>الحالة</th><th>القيد</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?php echo e($expense['expense_date']); ?></td>
                                    <td><?php echo e($expense['description']); ?><?php if ($expense['government_fee_type']): ?><br><small class="text-muted">رسم: <?php echo e($expense['government_fee_type']); ?></small><?php endif; ?></td>
                                    <td><?php echo e($expense['vendor_name'] ?: '—'); ?><br><small><?php echo e($expense['invoice_number'] ?: ''); ?></small></td>
                                    <td><?php echo akp_money($expense['amount']); ?></td>
                                    <td><span class="badge bg-<?php echo $expense['status'] === 'posted' ? 'dark' : ($expense['status'] === 'approved' ? 'success' : 'warning'); ?>"><?php echo e($expense['status']); ?></span></td>
                                    <td><small class="text-muted"><?php echo e($expense['entry_code'] ?: '—'); ?></small></td>
                                    <td>
                                        <?php if ($expense['status'] === 'draft' && akp_can_edit_section('finance', $id) && !$closed): ?>
                                            <form method="post" class="project-action-form d-inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="submit_expense">
                                                <input type="hidden" name="expense_id" value="<?php echo (int)$expense['id']; ?>">
                                                <button class="btn btn-sm btn-outline-primary">إرسال</button>
                                            </form>
                                        <?php elseif ($expense['status'] === 'submitted' && akp_can_edit_section('finance', $id) && !$closed): ?>
                                            <form method="post" class="project-action-form d-inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="approve_expense">
                                                <input type="hidden" name="expense_id" value="<?php echo (int)$expense['id']; ?>">
                                                <button class="btn btn-sm btn-outline-success">اعتماد</button>
                                            </form>
                                        <?php elseif ($expense['status'] === 'approved' && in_array($role, ['admin','accountant','general_manager'], true) && !$closed): ?>
                                            <form method="post" class="project-action-form d-inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="post_expense">
                                                <input type="hidden" name="expense_id" value="<?php echo (int)$expense['id']; ?>">
                                                <button class="btn btn-sm btn-outline-dark">ترحيل</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; if (!$expenses): ?>
                                <tr><td colspan="7" class="text-center text-muted">لا توجد مصروفات.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4 fade-in">
            <div class="card-header"><i class="fas fa-file-shield me-2"></i>الوثائق والإيصالات والشهادات</div>
            <div class="card-body">
                <?php if (akp_can_edit_section('documents', $id) && !$closed): ?>
                    <form method="post" enctype="multipart/form-data" class="project-form-panel">
                        <input type="hidden" name="action" value="upload_document">
                        <?php echo csrf_field(); ?>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <select name="document_type" class="form-select form-select-sm">
                                    <option value="receipt">إيصال</option>
                                    <option value="invoice">فاتورة</option>
                                    <option value="certificate">شهادة</option>
                                    <option value="government_fee">رسم حكومي</option>
                                    <option value="permit">تصريح</option>
                                    <option value="contract">عقد</option>
                                    <option value="quotation">عرض سعر</option>
                                    <option value="progress_report">تقرير تقدم</option>
                                    <option value="closure_report">تقرير إغلاق</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>
                            <div class="col-md-5"><input name="document_title" class="form-control form-control-sm" placeholder="عنوان الوثيقة" required></div>
                            <div class="col-md-4"><input type="file" name="document" class="form-control form-control-sm" required accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"></div>
                            <div class="col-md-4"><input name="document_issuer" class="form-control form-control-sm" placeholder="الجهة المصدرة"></div>
                            <div class="col-md-4"><input name="document_reference" class="form-control form-control-sm" placeholder="رقم الوثيقة/المرجع"></div>
                            <div class="col-md-4"><input type="date" name="document_date" class="form-control form-control-sm"></div>
                            <div class="col-12"><input name="document_notes" class="form-control form-control-sm" placeholder="ملاحظات"></div>
                            <div class="col-12"><button class="btn btn-sm btn-primary">رفع الوثيقة</button></div>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="row">
                    <?php foreach ($documents as $doc): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo e($doc['title']); ?></h6>
                                    <p class="card-text small text-muted mb-1">
                                        <span class="badge bg-secondary"><?php echo e($doc['document_type']); ?></span>
                                        <span class="badge bg-<?php echo $doc['verification_status'] === 'verified' ? 'success' : ($doc['verification_status'] === 'rejected' ? 'danger' : 'warning'); ?>"><?php echo e($doc['verification_status']); ?></span>
                                    </p>
                                    <p class="small mb-1">رفع بواسطة: <?php echo e($doc['uploader_name'] ?? '—'); ?></p>
                                    <div class="d-flex gap-2 mt-2">
                                        <a href="modules/projects/serve_project_document.php?id=<?php echo (int)$doc['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">عرض</a>
                                        <?php if ($doc['verification_status'] === 'unverified' && akp_can_edit_section('documents', $id) && !$closed): ?>
                                            <form method="post" class="project-action-form d-inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="verify_document">
                                                <input type="hidden" name="document_id" value="<?php echo (int)$doc['id']; ?>">
                                                <input type="hidden" name="verification_status" value="verified">
                                                <button class="btn btn-sm btn-outline-success">تحقق</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$documents): ?>
                        <div class="col-12 text-center text-muted">لا توجد وثائق مرفقة.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card mb-4 fade-in">
            <div class="card-header"><i class="fas fa-hard-hat me-2"></i>العمالة الخارجية والمساعدون</div>
            <div class="card-body">
                <?php if ($role === 'project_supervisor' && (akp_is_primary_supervisor($id) || akp_has_project_section($id, 'operations')) && !$closed): ?>
                    <form method="post" class="project-form-panel row g-2 mb-3">
                        <input type="hidden" name="action" value="add_labor">
                        <?php echo csrf_field(); ?>
                        <div class="col-5"><select name="labor_provider_type" class="form-select form-select-sm"><option value="individual">فرد</option><option value="company">شركة</option></select></div>
                        <div class="col-7"><input name="labor_provider_name" class="form-control form-control-sm" placeholder="اسم العامل/الشركة *" required></div>
                        <div class="col-6"><input name="labor_phone" class="form-control form-control-sm" placeholder="الهاتف"></div>
                        <div class="col-6"><input name="labor_number_of_workers" type="number" min="1" value="1" class="form-control form-control-sm" placeholder="عدد العمال"></div>
                        <div class="col-6"><input name="labor_contact_person_name" class="form-control form-control-sm" placeholder="جهة الاتصال للشركة"></div>
                        <div class="col-6"><input name="labor_contact_person_phone" class="form-control form-control-sm" placeholder="هاتف جهة الاتصال"></div>
                        <div class="col-12"><input name="labor_work_description" class="form-control form-control-sm" placeholder="وصف العمل/المساعدة *" required></div>
                        <div class="col-5"><input name="labor_payment_amount" type="number" step="0.01" min="0.01" class="form-control form-control-sm" placeholder="إجمالي المبلغ *" required></div>
                        <div class="col-4"><select name="labor_payment_timing" class="form-select form-select-sm"><option value="upfront">مقدم</option><option value="daily">يومي</option><option value="weekly">أسبوعي</option><option value="monthly">شهري</option><option value="upon_completion">عند الإنجاز</option></select></div>
                        <div class="col-3"><select name="labor_status" class="form-select form-select-sm"><option value="planned">مخطط</option><option value="in_progress">قيد التنفيذ</option><option value="completed">مكتمل</option></select></div>
                        <div class="col-12"><textarea name="labor_notes" class="form-control form-control-sm" rows="2" placeholder="ملاحظة المشرف"></textarea></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary">حفظ بيانات العمالة</button></div>
                    </form>
                <?php endif; ?>

                <?php foreach ($labors as $labor): ?>
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between">
                            <strong><?php echo e($labor['provider_name']); ?></strong>
                            <span class="badge bg-<?php echo $labor['status'] === 'completed' ? 'success' : 'primary'; ?>"><?php echo e($labor['status']); ?></span>
                        </div>
                        <div class="small text-muted mb-2"><?php echo e($labor['work_description']); ?> · <?php echo akp_money($labor['payment_amount']); ?> <?php echo e($labor['currency_code']); ?></div>
                        <?php if ($labor['manager_comment']): ?>
                            <div class="alert alert-light border small mb-2"><strong>تعليق الإدارة:</strong> <?php echo nl2br(e($labor['manager_comment'])); ?></div>
                        <?php endif; ?>
                        <?php if (akp_is_executive() && !$closed): ?>
                            <form method="post" class="project-inline-form input-group-sm">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="comment_labor">
                                <input type="hidden" name="labor_id" value="<?php echo (int)$labor['id']; ?>">
                                <input name="labor_manager_comment" class="form-control" placeholder="تعليق مدير المشاريع">
                                <button class="btn btn-outline-primary">تعليق</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; if (!$labors): ?>
                    <div class="text-muted small">لا توجد عمالة أو مساعدون مسجلون.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4 fade-in">
            <div class="card-header"><i class="fas fa-list-check me-2"></i>التشغيل والتقدم</div>
            <div class="card-body">
                <?php if (akp_can_edit_section('operations', $id) && !$closed): ?>
                    <form method="post" class="project-form-panel mb-3">
                        <input type="hidden" name="action" value="add_milestone">
                        <?php echo csrf_field(); ?>
                        <div class="row g-2">
                            <div class="col-6"><input name="milestone_title" class="form-control form-control-sm" placeholder="عنوان المرحلة *" required></div>
                            <div class="col-4"><input type="date" name="planned_date" class="form-control form-control-sm"></div>
                            <div class="col-2"><input type="number" min="0" max="100" name="completion_percent" class="form-control form-control-sm" placeholder="%"></div>
                            <div class="col-12"><textarea name="milestone_description" class="form-control form-control-sm" rows="2" placeholder="وصف المرحلة"></textarea></div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary mt-2">إضافة مرحلة</button>
                    </form>
                <?php endif; ?>
                
                <?php foreach ($milestones as $milestone): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <strong><?php echo e($milestone['title']); ?></strong><br>
                        <small><?php echo e($milestone['planned_date'] ?: 'بدون تاريخ'); ?> · <?php echo e($milestone['status']); ?> · <?php echo akp_money($milestone['completion_percent']); ?>%</small>
                        <div class="progress mt-1" style="height:6px"><div class="progress-bar" style="width:<?php echo (float)$milestone['completion_percent']; ?>%"></div></div>
                    </div>
                <?php endforeach; if (!$milestones): ?>
                    <div class="text-muted small">لا توجد مراحل بعد.</div>
                <?php endif; ?>

                <hr>
                <h6>تحديث تقدم</h6>
                <?php if (akp_can_edit_section('operations', $id) && !$closed): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="add_progress">
                        <?php echo csrf_field(); ?>
                        <div class="row g-2">
                            <div class="col-6"><input type="date" name="update_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>"></div>
                            <div class="col-6"><input type="number" min="0" max="100" name="progress_percent" class="form-control form-control-sm" placeholder="% الإنجاز"></div>
                            <div class="col-12"><textarea name="progress_summary" class="form-control form-control-sm" rows="2" placeholder="ملخص التقدم *" required></textarea></div>
                            <div class="col-6"><textarea name="achievements" class="form-control form-control-sm" rows="2" placeholder="الإنجازات"></textarea></div>
                            <div class="col-6"><textarea name="issues" class="form-control form-control-sm" rows="2" placeholder="المعوقات"></textarea></div>
                            <div class="col-12"><textarea name="next_steps" class="form-control form-control-sm" rows="2" placeholder="الخطوات القادمة"></textarea></div>
                            <div class="col-12"><button class="btn btn-sm btn-outline-primary">حفظ التحديث</button></div>
                        </div>
                    </form>
                <?php endif; ?>
                
                <?php foreach ($progressUpdates as $update): ?>
                    <div class="border-top mt-3 pt-2 small">
                        <strong><?php echo e($update['update_date']); ?> · <?php echo akp_money($update['completion_percent']); ?>%</strong>
                        <div><?php echo nl2br(e($update['summary'])); ?></div>
                        <?php if ($update['submitter_name']): ?><small class="text-muted"><?php echo e($update['submitter_name']); ?></small><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
<?php else: ?>
        <div class="alert alert-light border mb-4 small text-muted">
            <i class="fas fa-lock me-2"></i>تظهر المصروفات والوثائق والعمالة والتشغيل والتقدم بعد اعتماد المشروع نهائياً من المدير العام.
        </div>
<?php endif; ?>
    </div>

    <div class="col-lg-4 project-view-side-column">
        <div class="card mb-4 fade-in">
            <div class="card-header"><i class="fas fa-user-shield me-2"></i>فريق المشروع وصلاحيات الأقسام</div>
            <div class="card-body">
                <p class="small text-muted">المستخدم المكلّف بقسم يستطيع تعديل ذلك القسم فقط. إغلاق المشروع يلغي جميع صلاحيات التعديل، ولا يعيدها إلا المدير العام عند إعادة الفتح.</p>
                <?php if (akp_can_edit_section('team', $id) && !$closed): ?>
                    <form method="post" class="project-form-panel mb-3">
                        <input type="hidden" name="action" value="assign_team">
                        <?php echo csrf_field(); ?>
                        <div class="row g-2">
                            <div class="col-12">
                                <select name="team_user_id" class="form-select form-select-sm" required>
                                    <option value="">اختر المستخدم</option>
                                    <?php foreach (dbFetchAll('SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name') as $user): ?>
                                        <option value="<?php echo (int)$user['id']; ?>"><?php echo e($user['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-8">
                                <select name="team_section" class="form-select form-select-sm" required>
                                    <option value="">القسم</option>
                                    <option value="finance">المالية</option>
                                    <option value="operations">التشغيل</option>
                                    <option value="documents">المستندات</option>
                                    <option value="closure">الإغلاق</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="team_is_lead" value="1" id="team_is_lead">
                                    <label class="form-check-label small" for="team_is_lead">مسؤول</label>
                                </div>
                            </div>
                            <div class="col-12"><input name="team_notes" class="form-control form-control-sm" placeholder="ملاحظات التكليف"></div>
                            <div class="col-12"><button class="btn btn-sm btn-primary w-100">إضافة تكليف</button></div>
                        </div>
                    </form>
                <?php endif; ?>

                <?php foreach ($team as $member): ?>
                    <div class="border-bottom py-2 small">
                        <strong><?php echo e($member['full_name']); ?></strong> · <?php echo e($member['section_code']); ?>
                        <?php if ($member['is_lead']): ?> <span class="badge bg-primary">مسؤول</span><?php endif; ?>
                        <?php if (akp_can_edit_section('team', $id) && !$closed): ?>
                            <form method="post" class="project-inline-form d-inline float-end">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="unassign_team">
                                <input type="hidden" name="team_id" value="<?php echo (int)$member['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger py-0 px-1">×</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$team): ?><div class="text-muted small">لا يوجد فريق مكلف.</div><?php endif; ?>
            </div>
        </div>

        <div class="card mb-4 fade-in">
            <div class="card-header"><i class="fas fa-users me-2"></i>المستفيدون</div>
            <div class="card-body">
                <?php if (akp_can_edit_section('operations', $id) && !$closed): ?>
                    <form method="post" class="project-form-panel mb-3">
                        <input type="hidden" name="action" value="add_beneficiary_record">
                        <?php echo csrf_field(); ?>
                        <input name="record_beneficiary_name" class="form-control form-control-sm mb-2" placeholder="اسم المستفيد" required>
                        <div class="row g-2">
                            <div class="col-6"><input name="beneficiary_type" class="form-control form-control-sm" placeholder="الفئة"></div>
                            <div class="col-6"><input name="beneficiary_phone" class="form-control form-control-sm" placeholder="الهاتف"></div>
                            <div class="col-6"><input name="beneficiary_location" class="form-control form-control-sm" placeholder="الموقع"></div>
                            <div class="col-6"><input type="number" min="0" name="household_count" class="form-control form-control-sm" placeholder="عدد الأفراد"></div>
                            <div class="col-6"><input type="number" step="0.01" name="planned_support_amount" class="form-control form-control-sm" placeholder="المبلغ المخطط"></div>
                            <div class="col-6"><input type="number" step="0.01" name="delivered_support_amount" class="form-control form-control-sm" placeholder="المبلغ المسلم"></div>
                            <div class="col-6"><input type="date" name="support_date" class="form-control form-control-sm"></div>
                            <div class="col-12"><textarea name="beneficiary_notes" class="form-control form-control-sm" rows="2" placeholder="ملاحظات"></textarea></div>
                            <div class="col-12"><button class="btn btn-sm btn-primary w-100">إضافة مستفيد</button></div>
                        </div>
                    </form>
                <?php endif; ?>

                <?php 
                $beneficiaryRecords = dbFetchAll('SELECT * FROM project_beneficiary_records WHERE project_id = ? ORDER BY created_at DESC', [$id]);
                // Legacy fallback if needed, though new system uses project_beneficiary_records
                $legacyBeneficiaries = []; 
                ?>
                
                <?php foreach ($beneficiaryRecords as $record): ?>
                    <div class="border-bottom py-2 small">
                        <strong><?php echo e($record['beneficiary_name']); ?></strong><br>
                        <?php echo e($record['beneficiary_type'] ?: ''); ?> · <?php echo e($record['location'] ?: ''); ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($legacyBeneficiaries): ?>
                    <hr><small class="text-muted">السجلات القديمة</small>
                    <?php foreach ($legacyBeneficiaries as $record): ?>
                        <div class="border-bottom py-1 small">
                            <?php echo e($record['beneficiary_name']); ?>
                            <?php if ($record['amount'] !== null): ?> · <?php echo akp_money($record['amount']); ?><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!$beneficiaryRecords && !$legacyBeneficiaries): ?>
                    <div class="text-muted small">لا توجد سجلات مستفيدين.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4 fade-in">
            <div class="card-header"><i class="fas fa-lock me-2"></i>الإغلاق وإعادة الفتح</div>
            <div class="card-body">
                <?php if ($status !== 'closed' && akp_can_edit_section('closure', $id)): ?>
                    <p class="small">إغلاق المشروع يمنع أي تعديلات أو مصروفات جديدة. تأكد من ترحيل جميع القيود.</p>
                    <form method="post" class="project-form-panel">
                        <input type="hidden" name="action" value="close_project">
                        <?php echo csrf_field(); ?>
                        <textarea name="closure_summary" class="form-control form-control-sm mb-2" rows="3" placeholder="ملخص الإنجاز والأسباب *" required></textarea>
                        <select name="closure_reason" class="form-select form-select-sm mb-2">
                            <option value="completed_successfully">إنجاز كامل</option>
                            <option value="cancelled">إلغاء</option>
                            <option value="transferred_to_another_project">نقل لمشروع آخر</option>
                            <option value="retained_for_followup">احتفاظ للمتابعة</option>
                            <option value="other">أخرى</option>
                        </select>
                        <button class="btn btn-sm btn-dark w-100">إغلاق المشروع</button>
                    </form>
                <?php elseif ($status === 'closed' && akp_is_dg()): ?>
                    <p class="small">إعادة الفتح تعد استثناءً إدارياً وتحتاج سبباً واضحاً.</p>
                    <form method="post" class="project-form-panel">
                        <input type="hidden" name="action" value="reopen_project">
                        <?php echo csrf_field(); ?>
                        <textarea name="reopen_reason" class="form-control form-control-sm mb-2" rows="3" placeholder="سبب إعادة الفتح *" required></textarea>
                        <button class="btn btn-sm btn-warning w-100">إعادة فتح المشروع</button>
                    </form>
                <?php else: ?>
                    <div class="text-muted small">
                        <?php if ($status === 'closed'): ?>المشروع مغلق. إعادة الفتح متاحة للمدير العام فقط.<?php else: ?>لا تملك صلاحية الإغلاق.<?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4 fade-in">
            <div class="card-header"><i class="fas fa-history me-2"></i>سجل التغييرات</div>
            <div class="card-body">
                <?php foreach ($history as $h): ?>
                    <div class="small border-bottom pb-2 mb-2">
                        <div><strong><?php echo e($h['old_status']); ?></strong> → <strong><?php echo e($h['new_status']); ?></strong></div>
                        <div class="text-muted"><?php echo e($h['full_name'] ?? 'نظام'); ?> · <?php echo e($h['created_at']); ?></div>
                        <?php if ($h['reason']): ?><div class="fst-italic">"<?php echo e($h['reason']); ?>"</div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$history): ?><div class="text-muted small">لا يوجد سجل تغييرات.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>