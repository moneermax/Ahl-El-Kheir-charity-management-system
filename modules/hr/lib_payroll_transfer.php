<?php
declare(strict_types=1);

/**
 * FM payroll transfer preparation helpers.
 *
 * Proof bytes live beneath storage/, which is denied by the existing storage/.htaccess.
 * These helpers do not confirm payment, change payroll status, or post accounting.
 */
const HR_PAYROLL_PROOF_MAX_BYTES = 10 * 1024 * 1024;

function hrPayrollTransferSchemaAvailable(PDO $pdo): bool
{
    $settings = $pdo->query("SHOW TABLES LIKE 'hr_payroll_period_settings'")->fetchColumn();
    $proofs = $pdo->query("SHOW TABLES LIKE 'hr_payroll_payment_proofs'")->fetchColumn();
    return $settings !== false && $proofs !== false;
}

function hrPayrollProofStorageDirectory(): string
{
    $storageRoot = realpath(dirname(__DIR__, 2) . '/storage');
    if ($storageRoot === false) {
        throw new RuntimeException('مسار التخزين الخاص غير متاح.');
    }

    $directory = $storageRoot . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'payroll-proofs';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('تعذر تجهيز التخزين الخاص لإثباتات الرواتب.');
    }
    @chmod($directory, 0700);

    $realDirectory = realpath($directory);
    if ($realDirectory === false || !str_starts_with($realDirectory, $storageRoot . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('مسار التخزين الخاص غير صالح.');
    }

    return $realDirectory;
}

function hrPayrollProofPath(string $storageKey): string
{
    if (!preg_match('/\A[a-f0-9]{64}\.(pdf|jpg|png)\z/', $storageKey)) {
        throw new RuntimeException('مرجع إثبات الدفع غير صالح.');
    }

    return hrPayrollProofStorageDirectory() . DIRECTORY_SEPARATOR . $storageKey;
}

function hrPayrollGetPeriodSetting(PDO $pdo, int $month, int $year): ?array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, a.code AS account_code, a.name_ar AS account_name_ar, a.name_en AS account_name_en
         FROM hr_payroll_period_settings s
         JOIN accounts a ON a.id = s.default_payment_account_id
         WHERE s.month = ? AND s.year = ? LIMIT 1'
    );
    $stmt->execute([$month, $year]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function hrPayrollActivePaymentAccounts(PDO $pdo): array
{
    return dbFetchAll(
        'SELECT id, code, name_ar, name_en FROM accounts WHERE is_active = 1 ORDER BY code, name_ar, id'
    );
}

/**
 * Save the period default and apply it only to approved rows that have no explicit account.
 * Existing per-row choices are preserved.
 */
function hrPayrollSavePeriodDefaultAccount(PDO $pdo, int $month, int $year, int $accountId, int $userId): void
{
    if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100 || $userId <= 0) {
        throw new RuntimeException('بيانات فترة الرواتب أو المستخدم غير صالحة.');
    }
    $resolvedAccountId = hrPayrollResolvePaymentAccount($pdo, $accountId);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO hr_payroll_period_settings
                (month, year, default_payment_account_id, created_by_user_id, updated_by_user_id)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                default_payment_account_id = VALUES(default_payment_account_id),
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$month, $year, $resolvedAccountId, $userId, $userId]);

        $apply = $pdo->prepare(
            "UPDATE payroll SET payment_account_id = ?
             WHERE month = ? AND year = ? AND status = 'approved' AND payment_account_id IS NULL"
        );
        $apply->execute([$resolvedAccountId, $month, $year]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Explicitly override the payment account for selected payroll rows in the chosen period. */
function hrPayrollSetPaymentAccountForRows(PDO $pdo, array $payrollIds, int $month, int $year, int $accountId): int
{
    if (!$payrollIds || count($payrollIds) > 500) {
        throw new RuntimeException('حدد سجلات رواتب صالحة لتغيير حساب الدفع.');
    }
    $accountId = hrPayrollResolvePaymentAccount($pdo, $accountId);
    $ids = [];
    foreach ($payrollIds as $value) {
        if (!is_scalar($value) || !preg_match('/\A[1-9][0-9]*\z/', (string)$value)) {
            throw new RuntimeException('تتضمن قائمة الرواتب معرفاً غير صالح.');
        }
        $ids[] = (int)$value;
    }
    $ids = array_values(array_unique($ids));
    if (!$ids) {
        throw new RuntimeException('حدد سجلات رواتب صالحة لتغيير حساب الدفع.');
    }

    $pdo->beginTransaction();
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $lock = $pdo->prepare(
            "SELECT id, status FROM payroll WHERE id IN ($placeholders) AND month = ? AND year = ? FOR UPDATE"
        );
        $lock->execute([...$ids, $month, $year]);
        $rows = $lock->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== count($ids)) {
            throw new RuntimeException('يجب أن تنتمي كل السجلات المحددة إلى فترة الرواتب المختارة.');
        }
        foreach ($rows as $row) {
            if ($row['status'] !== 'approved') {
                throw new RuntimeException('يمكن تعيين حساب التحويل لسجلات الرواتب المعتمدة فقط.');
            }
        }
        $update = $pdo->prepare(
            "UPDATE payroll SET payment_account_id = ? WHERE id IN ($placeholders) AND month = ? AND year = ? AND status = 'approved'"
        );
        $update->execute([$accountId, ...$ids, $month, $year]);
        $pdo->commit();
        return count($rows);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Persist one private proof for an approved transfer or evidence for an already-paid legacy row.
 * This function never changes payroll status or accounting.
 */
function hrPayrollUploadProof(PDO $pdo, int $payrollId, array $upload, int $userId, bool $legacyEvidence = false): void
{
    if ($payrollId <= 0 || $userId <= 0 || !isset($upload['error'], $upload['tmp_name'], $upload['name'], $upload['size'])) {
        throw new RuntimeException('بيانات رفع إثبات الدفع غير مكتملة.');
    }
    if ((int)$upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$upload['tmp_name'])) {
        throw new RuntimeException('تعذر استقبال الملف المرفوع. تحقق من حجمه ثم أعد المحاولة.');
    }
    $size = (int)$upload['size'];
    if ($size < 1 || $size > HR_PAYROLL_PROOF_MAX_BYTES || (int)filesize((string)$upload['tmp_name']) !== $size) {
        throw new RuntimeException('الحد الأقصى لإثبات الدفع هو 10 ميجابايت.');
    }

    $originalName = basename(str_replace('\\', '/', (string)$upload['name']));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];
    if (!isset($allowed[$extension])) {
        throw new RuntimeException('الصيغ المسموح بها لإثبات الدفع هي PDF وJPG وPNG فقط.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file((string)$upload['tmp_name']);
    $mimeToExtension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($mimeToExtension[$mime]) || $allowed[$extension] !== $mime) {
        throw new RuntimeException('نوع محتوى الملف لا يطابق صيغة إثبات الدفع المسموح بها.');
    }
    $safeExtension = $mimeToExtension[$mime];

    $payrollStmt = $pdo->prepare(
        'SELECT id, status, accounting_status, accounting_entry_id
         FROM payroll WHERE id = ? LIMIT 1'
    );
    $payrollStmt->execute([$payrollId]);
    $payroll = $payrollStmt->fetch(PDO::FETCH_ASSOC);
    if (!$payroll) {
        throw new RuntimeException('سجل الرواتب غير موجود.');
    }
    if ($legacyEvidence) {
        if ($payroll['status'] !== 'paid' || $payroll['accounting_status'] !== 'posted' || empty($payroll['accounting_entry_id'])) {
            throw new RuntimeException('يمكن إرفاق الدليل التاريخي لسجل مصروف ومرحل محاسبياً فقط.');
        }
    } elseif ($payroll['status'] !== 'approved') {
        throw new RuntimeException('يمكن تجهيز إثبات التحويل لسجل راتب معتمد وغير مصروف فقط.');
    }

    $existingStmt = $pdo->prepare('SELECT id FROM hr_payroll_payment_proofs WHERE payroll_id = ? LIMIT 1');
    $existingStmt->execute([$payrollId]);
    if ($existingStmt->fetchColumn()) {
        throw new RuntimeException('يوجد إثبات محفوظ لهذا السجل بالفعل؛ لا يمكن استبداله تلقائياً.');
    }

    $storageKey = bin2hex(random_bytes(32)) . '.' . $safeExtension;
    $destination = hrPayrollProofPath($storageKey);
    if (file_exists($destination)) {
        throw new RuntimeException('تعذر إنشاء مرجع فريد لإثبات الدفع؛ أعد المحاولة.');
    }
    if (!move_uploaded_file((string)$upload['tmp_name'], $destination)) {
        throw new RuntimeException('تعذر حفظ إثبات الدفع في التخزين الخاص.');
    }
    @chmod($destination, 0600);

    try {
        $insert = $pdo->prepare(
            "INSERT INTO hr_payroll_payment_proofs
                (payroll_id, proof_state, storage_key, original_filename, detected_mime_type,
                 file_size_bytes, uploaded_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $insert->execute([
            $payrollId,
            $legacyEvidence ? 'legacy_evidence' : 'prepared',
            $storageKey,
            mb_substr($originalName, 0, 255, 'UTF-8'),
            $mime,
            $size,
            $userId,
        ]);
    } catch (Throwable $e) {
        if (is_file($destination)) {
            @unlink($destination);
        }
        throw $e;
    }
}
