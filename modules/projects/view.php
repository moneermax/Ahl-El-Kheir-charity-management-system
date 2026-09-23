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
            // Funding-source selection and allocation are owned by the FM.
            if ($role !== 'financial_manager' || $closed) {
                throw new RuntimeException('إضافة تخصيصات التمويل واختيار حسابات المصدر محصوران بالمدير المالي.'); 
            }
            if (!akp_can_manage_funding($id)) throw new RuntimeException('لا تملك صلاحية إضافة تخصيص تمويل.');
            $approvalCheck = dbFetchOne('SELECT approval_status FROM project_approval WHERE project_id = ?', [$id]);
            if (!$approvalCheck || !in_array($approvalCheck['approval_status'], ['submitted', 'rejected'], true)) throw new RuntimeException('يمكن تسجيل مصادر التمويل أثناء إعداد المشروع بعد الرفض أو أثناء المراجعة المالية.');

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
            if ($role !== 'financial_manager' || !in_array($approvalStatus, ['submitted', 'rejected'], true) || !akp_can_manage_funding($id)) {
                throw new RuntimeException('تعديل تخصيصات التمويل محصور بالمدير المالي أثناء مرحلة المراجعة المالية أو بعد الرفض لإعادة ضبط التمويل.');
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