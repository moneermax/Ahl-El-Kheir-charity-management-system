<?php
declare(strict_types=1);

require_once __DIR__ . '/lib_salary_advance_request.php';
require_once dirname(__DIR__) . '/accounting/lib.php';
require_once dirname(__DIR__) . '/accounting/lib_vouchers.php';
require_once dirname(__DIR__) . '/accounting/lib_transaction_review.php';

function hrSalaryAdvanceAccountingCan(string $role): bool
{
    return in_array($role, ['financial_manager', 'accountant_staff', 'admin'], true);
}

function hrSalaryAdvanceAccountingGetRequest(PDO $pdo, int $requestId): ?array
{
    if ($requestId <= 0) return null;

    return dbFetchOne(
        "SELECT r.*,
                p.version_no, p.policy_name, p.require_accounting_verification,
                e.full_name AS employee_name, e.employee_code, e.user_id AS employee_user_id,
                u.full_name AS submitted_by_name,
                av.full_name AS accounting_verified_by_name,
                dv.full_name AS disbursed_by_name,
                sa.code AS disbursement_account_code,
                sa.name_ar AS disbursement_account_name,
                je.entry_code AS disbursement_entry_code,
                d.id AS payment_receipt_id,
                d.original_name AS payment_receipt_name,
                d.uploaded_at AS payment_receipt_uploaded_at
         FROM hr_salary_advance_requests r
         JOIN hr_salary_advance_policy_versions p ON p.id = r.policy_version_id
         JOIN employees e ON e.id = r.employee_id
         LEFT JOIN users u ON u.id = r.submitted_by
         LEFT JOIN users av ON av.id = r.accounting_verified_by
         LEFT JOIN users dv ON dv.id = r.disbursed_by
         LEFT JOIN accounts sa ON sa.id = r.disbursement_account_id
         LEFT JOIN journal_entries je ON je.id = r.disbursement_journal_entry_id
         LEFT JOIN hr_salary_advance_documents d
           ON d.salary_advance_request_id = r.id
          AND d.document_type = 'payment_receipt'
         WHERE r.id = ?
         LIMIT 1",
        [$requestId]
    );
}

function hrSalaryAdvanceAccountingQueue(PDO $pdo): array
{
    return dbFetchAll(
        "SELECT r.id, r.request_no, r.status, r.accounting_status,
                r.approved_amount, r.approved_repayment_method,
                r.approved_monthly_amount, r.approved_start_month,
                r.fm_reviewed_at, r.accounting_verified_at,
                e.full_name AS employee_name, e.employee_code,
                p.require_accounting_verification
         FROM hr_salary_advance_requests r
         JOIN employees e ON e.id = r.employee_id
         JOIN hr_salary_advance_policy_versions p ON p.id = r.policy_version_id
         WHERE r.status = 'approved'
         ORDER BY r.fm_reviewed_at ASC, r.id ASC"
    );
}

function hrSalaryAdvanceAccountingVerify(PDO $pdo, int $requestId, int $userId, string $decision, string $reason = ''): void
{
    if ($requestId <= 0 || $userId <= 0) {
        throw new InvalidArgumentException('بيانات المراجعة المحاسبية غير صالحة.');
    }

    if (!in_array($decision, ['verify', 'reject'], true)) {
        throw new InvalidArgumentException('قرار التحقق المحاسبي غير صالح.');
    }

    $request = hrSalaryAdvanceAccountingGetRequest($pdo, $requestId);
    if (!$request) {
        throw new RuntimeException('طلب السلفة غير موجود.');
    }
    if ($request['status'] !== 'approved') {
        throw new RuntimeException('لا يمكن التحقق المحاسبي إلا لطلب معتمد من FM ولم يُصرف بعد.');
    }
    if ((float)$request['approved_amount'] <= 0) {
        throw new RuntimeException('المبلغ المعتمد للسلفة غير صالح.');
    }

    if ($decision === 'reject' && trim($reason) === '') {
        throw new InvalidArgumentException('سبب رفض التحقق المحاسبي مطلوب.');
    }

    $newStatus = $decision === 'verify' ? 'verified' : 'rejected';
    $newReason = $decision === 'reject' ? trim($reason) : null;

    $affected = dbExecute(
        "UPDATE hr_salary_advance_requests
         SET accounting_status = ?,
             accounting_verified_by = ?,
             accounting_verified_at = NOW(),
             accounting_rejection_reason = ?,
             updated_at = NOW()
         WHERE id = ? AND status = 'approved'",
        [$newStatus, $userId, $newReason, $requestId]
    );

    if ($affected !== 1) {
        throw new RuntimeException('تعذر حفظ نتيجة التحقق المحاسبي.');
    }

    dbExecute(
        "INSERT INTO audit_log
         (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
         VALUES (?, ?, 'hr_salary_advance_request', ?, ?, ?, ?, ?)",
        [
            $userId,
            $decision === 'verify' ? 'HR_SALARY_ADVANCE_ACCOUNTING_VERIFY' : 'HR_SALARY_ADVANCE_ACCOUNTING_REJECT',
            $requestId,
            json_encode([
                'status' => 'approved',
                'accounting_status' => $request['accounting_status'],
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                'accounting_status' => $newStatus,
                'accounting_rejection_reason' => $newReason,
            ], JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]
    );
}

function hrSalaryAdvanceAccountingDisburse(PDO $pdo, int $requestId, int $userId, int $cashAccountId, string $reference = ''): int
{
    if ($requestId <= 0 || $userId <= 0 || $cashAccountId <= 0) {
        throw new InvalidArgumentException('بيانات صرف السلفة غير صالحة.');
    }

    $request = hrSalaryAdvanceAccountingGetRequest($pdo, $requestId);
    if (!$request) {
        throw new RuntimeException('طلب السلفة غير موجود.');
    }
    if ($request['status'] !== 'approved') {
        throw new RuntimeException('لا يمكن صرف السلفة إلا بعد اعتماد FM وقبل صرفها سابقاً.');
    }

    $amount = round((float)$request['approved_amount'], 2);
    if ($amount <= 0) {
        throw new RuntimeException('المبلغ المعتمد للسلفة غير صالح.');
    }

    if ((int)$request['require_accounting_verification'] === 1 && $request['accounting_status'] !== 'verified') {
        throw new RuntimeException('يجب إكمال التحقق المحاسبي قبل صرف السلفة.');
    }

    $existing = dbFetchOne(
        "SELECT id, entry_code
         FROM journal_entries
         WHERE reference_type = 'salary_advance_disbursement'
           AND reference_id = ?
           AND status = 'posted'
         LIMIT 1",
        [$requestId]
    );
    if ($existing) {
        throw new RuntimeException('تم ترحيل قيد صرف لهذه السلفة مسبقاً.');
    }

    $lockAcquired = false;
    try {
        $lockAcquired = ak_voucher_lock();
        if (!$lockAcquired) {
            throw new RuntimeException('تعذر الحصول على قفل الترقيم المحاسبي. حاول مرة أخرى.');
        }

        $pdo->beginTransaction();

        $cash = dbFetchOne(
            "SELECT id, code, name_ar, account_type, is_active
             FROM accounts
             WHERE id = ? AND code IN ('1100','1200','1300')
             FOR UPDATE",
            [$cashAccountId]
        );
        if (!$cash || (int)$cash['is_active'] !== 1) {
            throw new RuntimeException('حساب الصرف المحدد غير صالح.');
        }

        $lockedRequest = dbFetchOne(
            "SELECT r.*, p.require_accounting_verification,
                    e.full_name AS employee_name, e.user_id AS employee_user_id
             FROM hr_salary_advance_requests r
             JOIN hr_salary_advance_policy_versions p ON p.id = r.policy_version_id
             JOIN employees e ON e.id = r.employee_id
             WHERE r.id = ?
             FOR UPDATE",
            [$requestId]
        );
        if (!$lockedRequest || $lockedRequest['status'] !== 'approved') {
            throw new RuntimeException('طلب السلفة لم يعد متاحاً للصرف.');
        }

        if ($lockedRequest['accounting_status'] === 'rejected') {
            throw new RuntimeException('تم رفض التحقق المحاسبي لهذه السلفة. يجب إعادة التحقق واعتمادها قبل الصرف.');
        }
        if ((int)$lockedRequest['require_accounting_verification'] === 1 && $lockedRequest['accounting_status'] !== 'verified') {
            throw new RuntimeException('يجب إكمال التحقق المحاسبي قبل صرف السلفة.');
        }

        $amount = round((float)$lockedRequest['approved_amount'], 2);
        $available = ak_voucher_cash_balance($cashAccountId);
        if ($amount > $available + 0.000001) {
            throw new RuntimeException(
                'الرصيد غير كافٍ في الحساب المختار. المتاح: ' . number_format($available, 2) . ' ج.س'
            );
        }

        $advanceAccount = dbFetchOne(
            "SELECT id, code, name_ar, account_type, is_active
             FROM accounts
             WHERE code = '1410'
             LIMIT 1
             FOR UPDATE"
        );
        if (!$advanceAccount || $advanceAccount['account_type'] !== 'asset' || (int)$advanceAccount['is_active'] !== 1) {
            throw new RuntimeException('حساب ذمم سلف الموظفين (1410) غير مهيأ أو غير صالح.');
        }

        $jeCode = ak_voucher_next_journal_code();
        $label = 'صرف سلفة راتب ' . $lockedRequest['request_no'] . ' — ' . ($request['employee_name'] ?? 'الموظف');
        $label = mb_substr($label, 0, 250);

        dbExecute(
            "INSERT INTO journal_entries
             (entry_code, entry_date, description, reference_type, reference_id, status, created_by)
             VALUES (?, ?, ?, 'salary_advance_disbursement', ?, 'posted', ?)",
            [$jeCode, date('Y-m-d'), $label, $requestId, $userId]
        );
        $entryId = (int)dbLastInsertId();
        if ($entryId <= 0) {
            throw new RuntimeException('تعذر إنشاء قيد صرف السلفة.');
        }

        // Salary advance disbursement is a receivable, not an expense:
        // Dr 1410 Employee Salary Advances Receivable
        // Cr 1100/1200/1300 selected cash source.
        dbExecute(
            "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
             VALUES (?, ?, ?, 0, ?)",
            [$entryId, (int)$advanceAccount['id'], $amount, $label]
        );
        dbExecute(
            "INSERT INTO journal_lines (entry_id, account_id, debit, credit, description)
             VALUES (?, ?, 0, ?, ?)",
            [$entryId, $cashAccountId, $amount, $label]
        );

        $check = dbFetchOne(
            "SELECT COUNT(*) line_count,
                    COALESCE(SUM(debit),0) debit_total,
                    COALESCE(SUM(credit),0) credit_total
             FROM journal_lines
             WHERE entry_id = ?",
            [$entryId]
        );
        if ((int)$check['line_count'] !== 2 ||
            abs((float)$check['debit_total'] - $amount) > 0.000001 ||
            abs((float)$check['credit_total'] - $amount) > 0.000001) {
            throw new RuntimeException('فشل التحقق من توازن قيد صرف السلفة.');
        }

        $reference = trim($reference);
        if ($reference === '') {
            $reference = 'SAL-ADV-' . $lockedRequest['request_no'];
        }
        if (mb_strlen($reference) > 100) {
            throw new InvalidArgumentException('مرجع الصرف أطول من الحد المسموح.');
        }

        $affected = dbExecute(
            "UPDATE hr_salary_advance_requests
             SET status = 'disbursed',
                 disbursed_by = ?,
                 disbursed_at = NOW(),
                 disbursement_account_id = ?,
                 disbursement_journal_entry_id = ?,
                 disbursement_reference = ?,
                 outstanding_balance = ?,
                 updated_at = NOW()
             WHERE id = ? AND status = 'approved'",
            [$userId, $cashAccountId, $entryId, $reference, $amount, $requestId]
        );
        if ($affected !== 1) {
            throw new RuntimeException('تعذر تحديث دورة حياة السلفة بعد الترحيل.');
        }

        try {
            dbExecute(
                "INSERT INTO audit_log
                 (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, 'HR_SALARY_ADVANCE_DISBURSE', 'hr_salary_advance_request', ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $requestId,
                    json_encode([
                        'status' => 'approved',
                        'accounting_status' => $lockedRequest['accounting_status'],
                        'approved_amount' => $amount,
                    ], JSON_UNESCAPED_UNICODE),
                    json_encode([
                        'status' => 'disbursed',
                        'disbursed_amount' => $amount,
                        'cash_account_id' => $cashAccountId,
                        'journal_entry_id' => $entryId,
                        'reference' => $reference,
                        'outstanding_balance' => $amount,
                    ], JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );
        } catch (Throwable $auditError) {
            // Audit logging must not turn a successfully balanced financial transaction into a partial posting.
        }

        $pdo->commit();

        $employeeUserId = (int)($lockedRequest['employee_user_id'] ?? 0);
        if ($employeeUserId <= 0) {
            $employeeUserId = (int)$lockedRequest['submitted_by'];
        }
        if ($employeeUserId > 0) {
            ak_transaction_review_notify_event(
                $employeeUserId,
                'تم صرف السلفة',
                'تم صرف السلفة «' . (string)$lockedRequest['request_no'] . '» بمبلغ ' . number_format($amount, 2) . ' ج.س. الرصيد القائم للسلفة: ' . number_format($amount, 2) . ' ج.س.',
                APP_URL . 'modules/hr/salary_advance_request.php',
                $requestId,
                'salary_advance_disbursement'
            );
        }

        return $entryId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        if ($lockAcquired) {
            ak_voucher_unlock();
        }
    }
}


function hrSalaryAdvanceAccountingUploadReceipt(PDO $pdo, int $requestId, int $userId, array $file): void
{
    if ($requestId <= 0 || $userId <= 0) {
        throw new InvalidArgumentException('بيانات رفع الإيصال غير صالحة.');
    }

    $request = hrSalaryAdvanceAccountingGetRequest($pdo, $requestId);
    if (!$request || $request['status'] !== 'disbursed' || (int)($request['disbursement_journal_entry_id'] ?? 0) <= 0) {
        throw new RuntimeException('لا يمكن رفع إيصال إلا لسلفة تم صرفها وترحيل قيدها.');
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('تعذر رفع إيصال الدفع.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('حجم إيصال الدفع يجب أن يكون أكبر من صفر وألا يتجاوز 5 ميجابايت.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('ملف الإيصال المرفوع غير صالح.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('صيغة إيصال الدفع غير مدعومة. المسموح: JPG, PNG, PDF.');
    }

    $originalName = trim((string)($file['name'] ?? 'receipt'));
    $originalName = mb_substr($originalName !== '' ? $originalName : 'receipt.' . $allowed[$mime], 0, 255);

    $root = dirname(__DIR__, 2);
    $dir = $root . '/storage/receipts/salary-advances';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('تعذر إنشاء مجلد تخزين إيصالات السلف.');
    }

    $filename = 'SAL-ADV-' . $request['request_no'] . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $relativePath = 'storage/receipts/salary-advances/' . $filename;
    $absolutePath = $root . '/' . $relativePath;

    if (!move_uploaded_file($tmp, $absolutePath)) {
        throw new RuntimeException('فشل حفظ إيصال الدفع على الخادم.');
    }

    $oldPath = '';
    try {
        $pdo->beginTransaction();

        $existing = dbFetchOne(
            "SELECT id, file_path
             FROM hr_salary_advance_documents
             WHERE salary_advance_request_id = ? AND document_type = 'payment_receipt'
             FOR UPDATE",
            [$requestId]
        );
        $oldPath = (string)($existing['file_path'] ?? '');

        if ($existing) {
            dbExecute(
                "UPDATE hr_salary_advance_documents
                 SET file_path = ?, original_name = ?, mime_type = ?, file_size = ?, uploaded_by = ?, uploaded_at = NOW()
                 WHERE id = ?",
                [$relativePath, $originalName, $mime, $size, $userId, (int)$existing['id']]
            );
        } else {
            dbExecute(
                "INSERT INTO hr_salary_advance_documents
                 (salary_advance_request_id, document_type, file_path, original_name, mime_type, file_size, uploaded_by)
                 VALUES (?, 'payment_receipt', ?, ?, ?, ?, ?)",
                [$requestId, $relativePath, $originalName, $mime, $size, $userId]
            );
        }

        try {
            dbExecute(
                "INSERT INTO audit_log
                 (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, 'HR_SALARY_ADVANCE_RECEIPT_UPLOAD', 'hr_salary_advance_request', ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $requestId,
                    json_encode(['previous_receipt' => $oldPath !== '' ? $oldPath : null], JSON_UNESCAPED_UNICODE),
                    json_encode(['receipt_path' => $relativePath, 'original_name' => $originalName], JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );
        } catch (Throwable $auditError) {}

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        @unlink($absolutePath);
        throw $e;
    }

    if ($oldPath !== '' && $oldPath !== $relativePath) {
        $oldAbsolute = $root . '/' . ltrim($oldPath, '/\\');
        if (is_file($oldAbsolute)) {
            @unlink($oldAbsolute);
        }
    }
}
