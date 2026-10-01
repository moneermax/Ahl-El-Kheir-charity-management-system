<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_salary_advance_accounting.php';

function hrSalaryAdvanceDirectRepaymentEvidenceGet(PDO $pdo, int $repaymentId): ?array
{
    if ($repaymentId <= 0) return null;

    return dbFetchOne(
        "SELECT d.id, d.direct_repayment_id, d.file_path, d.original_name,
                d.mime_type, d.file_size, d.uploaded_by, d.uploaded_at
         FROM hr_salary_advance_direct_repayment_documents d
         JOIN hr_salary_advance_direct_repayments r ON r.id = d.direct_repayment_id
         WHERE d.direct_repayment_id = ?
         LIMIT 1",
        [$repaymentId]
    ) ?: null;
}

function hrSalaryAdvanceDirectRepaymentEvidenceUpload(
    PDO $pdo,
    int $requestId,
    int $repaymentId,
    int $userId,
    array $file
): void {
    if ($requestId <= 0 || $repaymentId <= 0 || $userId <= 0) {
        throw new InvalidArgumentException('بيانات إثبات السداد غير صالحة.');
    }

    $repayment = dbFetchOne(
        "SELECT d.id, d.repayment_account_id, d.repayment_amount, d.repayment_reference,
                d.repayment_date, d.salary_advance_request_id
         FROM hr_salary_advance_direct_repayments d
         JOIN hr_salary_advance_requests r ON r.id = d.salary_advance_request_id
         WHERE d.id = ? AND d.salary_advance_request_id = ?
         LIMIT 1",
        [$repaymentId, $requestId]
    );
    if (!$repayment) {
        throw new RuntimeException('عملية السداد المباشر المطلوبة غير موجودة.');
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('تعذر رفع مستند إثبات السداد.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('حجم مستند إثبات السداد يجب أن يكون أكبر من صفر وألا يتجاوز 5 ميجابايت.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('ملف إثبات السداد المرفوع غير صالح.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('صيغة إثبات السداد غير مدعومة. المسموح: JPG, PNG, PDF.');
    }

    $originalName = trim((string)($file['name'] ?? 'repayment-evidence'));
    $originalName = mb_substr(
        $originalName !== '' ? $originalName : 'repayment-evidence.' . $allowed[$mime],
        0,
        255
    );

    $root = dirname(__DIR__, 2);
    $dir = $root . '/storage/receipts/salary-advance-repayments';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('تعذر إنشاء مجلد تخزين مستندات السداد المباشر.');
    }

    $filename = 'SAL-ADV-REP-' . $repaymentId . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $relativePath = 'storage/receipts/salary-advance-repayments/' . $filename;
    $absolutePath = $root . '/' . $relativePath;

    if (!move_uploaded_file($tmp, $absolutePath)) {
        throw new RuntimeException('فشل حفظ مستند إثبات السداد على الخادم.');
    }

    $oldPath = '';
    try {
        $pdo->beginTransaction();

        $existing = dbFetchOne(
            "SELECT id, file_path
             FROM hr_salary_advance_direct_repayment_documents
             WHERE direct_repayment_id = ?
             FOR UPDATE",
            [$repaymentId]
        );
        $oldPath = (string)($existing['file_path'] ?? '');

        if ($existing) {
            dbExecute(
                "UPDATE hr_salary_advance_direct_repayment_documents
                 SET file_path = ?, original_name = ?, mime_type = ?, file_size = ?,
                     uploaded_by = ?, uploaded_at = NOW()
                 WHERE id = ?",
                [$relativePath, $originalName, $mime, $size, $userId, (int)$existing['id']]
            );
        } else {
            dbExecute(
                "INSERT INTO hr_salary_advance_direct_repayment_documents
                 (direct_repayment_id, file_path, original_name, mime_type, file_size, uploaded_by)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$repaymentId, $relativePath, $originalName, $mime, $size, $userId]
            );
        }

        dbExecute(
            "INSERT INTO audit_log
             (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'HR_SALARY_ADVANCE_REPAYMENT_EVIDENCE_UPLOAD',
                     'hr_salary_advance_direct_repayment', ?, ?, ?, ?, ?)",
            [
                $userId,
                $repaymentId,
                json_encode(['previous_evidence' => $oldPath !== '' ? $oldPath : null], JSON_UNESCAPED_UNICODE),
                json_encode([
                    'evidence_path' => $relativePath,
                    'original_name' => $originalName,
                    'repayment_account_id' => (int)$repayment['repayment_account_id'],
                    'repayment_amount' => (float)$repayment['repayment_amount'],
                ], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        @unlink($absolutePath);
        throw $e;
    }

    if ($oldPath !== '' && $oldPath !== $relativePath) {
        $oldAbsolute = $root . '/' . ltrim($oldPath, '/\\');
        if (is_file($oldAbsolute)) @unlink($oldAbsolute);
    }
}
