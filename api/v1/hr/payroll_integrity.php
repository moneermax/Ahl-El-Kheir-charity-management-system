<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if (!in_array((string)($user['raw_role_code'] ?? ''), ['hr_manager', 'admin'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'Payroll integrity access is restricted to HR Manager and administrator roles.',
    ]]);
}

function mobilePayrollIntegrityTableExists(string $table): bool
{
    return (bool)dbFetchOne(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
        [$table]
    );
}

function mobilePayrollIntegrityColumnExists(string $table, string $column): bool
{
    return (bool)dbFetchOne(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
        [$table, $column]
    );
}

function mobilePayrollIntegrityRows(string $sql): array
{
    $rows = dbFetchAll($sql);
    $count = count($rows);
    $displayRows = array_slice($rows, 0, 100);
    return ['count' => $count, 'rows' => $displayRows, 'rows_truncated' => $count > count($displayRows)];
}

try {
    if (!mobilePayrollIntegrityTableExists('payroll')) {
        ak_api_json(500, ['ok' => false, 'error' => [
            'code' => 'payroll_table_missing',
            'message' => 'The payroll table is not available in the current database.',
        ]]);
    }

    $checks = [];
    $add = static function (string $key, string $title, bool $ok, string $summary, array $result = []) use (&$checks): void {
        $checks[] = [
            'key' => $key,
            'title' => $title,
            'ok' => $ok,
            'summary' => $summary,
            'count' => (int)($result['count'] ?? 0),
            'rows' => $result['rows'] ?? [],
            'rows_truncated' => (bool)($result['rows_truncated'] ?? false),
        ];
    };

    $schemaOk = mobilePayrollIntegrityColumnExists('payroll', 'accounting_status')
        && mobilePayrollIntegrityColumnExists('payroll', 'accounting_entry_id')
        && mobilePayrollIntegrityColumnExists('payroll', 'payment_account_id');
    $add('schema', 'تكامل أعمدة المحاسبة', $schemaOk,
        $schemaOk ? 'أعمدة حالة المحاسبة والقيد وحساب الدفع موجودة في payroll.' : 'أعمدة تكامل المحاسبة المطلوبة غير مكتملة.');

    $queries = [
        'paid_missing_journal' => [
            'المسيرات المصروفة غير المرحلة',
            "SELECT p.id,e.employee_code,e.full_name,p.month,p.year,p.net_salary,p.accounting_status,p.accounting_entry_id
             FROM payroll p JOIN employees e ON e.id=p.employee_id
             LEFT JOIN journal_entries j ON j.reference_type='payroll' AND j.reference_id=p.id AND j.status='posted'
             WHERE p.status='paid' AND p.net_salary > 0 AND j.id IS NULL
             ORDER BY p.year DESC,p.month DESC,e.full_name ASC",
            'كل مسير مصروف بصافي موجب له قيد محاسبي مرحل.',
            'يوجد مسير مصروف بصافي موجب بدون قيد محاسبي مرحل.',
        ],
        'paid_status' => [
            'حالة المحاسبة للمسيرات المصروفة',
            "SELECT p.id,e.employee_code,e.full_name,p.month,p.year,p.accounting_status,p.accounting_entry_id
             FROM payroll p JOIN employees e ON e.id=p.employee_id
             WHERE p.status='paid' AND (COALESCE(p.accounting_status,'none') <> 'posted' OR COALESCE(p.accounting_entry_id,0)=0)
             ORDER BY p.year DESC,p.month DESC,e.full_name ASC",
            'كل المسيرات المصروفة تحمل حالة posted ورقم قيد محاسبي.',
            'توجد مسيرات مصروفة بحالة أو قيد محاسبي غير مكتمل.',
        ],
        'approved_status' => [
            'حالة المحاسبة للمسيرات المعتمدة',
            "SELECT p.id,e.employee_code,e.full_name,p.month,p.year,p.accounting_status
             FROM payroll p JOIN employees e ON e.id=p.employee_id
             WHERE p.status='approved' AND COALESCE(p.accounting_status,'none') <> 'ready'
             ORDER BY p.year DESC,p.month DESC,e.full_name ASC",
            'كل المسيرات المعتمدة جاهزة للمحاسبة.',
            'توجد مسيرات معتمدة ليست في حالة ready.',
        ],
        'draft_posted' => [
            'المسودات المرتبطة بقيود مرحّلة',
            "SELECT p.id,e.employee_code,e.full_name,p.month,p.year,j.id AS journal_id
             FROM payroll p JOIN employees e ON e.id=p.employee_id
             JOIN journal_entries j ON j.reference_type='payroll' AND j.reference_id=p.id AND j.status='posted'
             WHERE p.status='draft'
             ORDER BY p.year DESC,p.month DESC,e.full_name ASC",
            'لا توجد مسودة مرتبطة بقيد محاسبي مرحل.',
            'توجد مسودة مرتبطة بقيد مرحل.',
        ],
        'duplicates' => [
            'التكرار في مسيرات الموظفين',
            'SELECT employee_id,month,year,COUNT(*) AS row_count FROM payroll GROUP BY employee_id,month,year HAVING COUNT(*) > 1 ORDER BY year DESC,month DESC,employee_id ASC',
            'لا يوجد أكثر من مسير لنفس الموظف والفترة.',
            'يوجد تكرار في نفس الموظف والفترة.',
        ],
        'balance' => [
            'توازن قيود الرواتب',
            "SELECT j.id,j.entry_code,j.reference_type,j.reference_id,
                    ROUND(COALESCE(SUM(l.debit),0),2) AS total_debit,
                    ROUND(COALESCE(SUM(l.credit),0),2) AS total_credit
             FROM journal_entries j JOIN journal_lines l ON l.entry_id=j.id
             WHERE j.status='posted' AND j.reference_type IN ('payroll','payroll_reversal')
             GROUP BY j.id,j.entry_code,j.reference_type,j.reference_id
             HAVING ROUND(COALESCE(SUM(l.debit),0),2) <> ROUND(COALESCE(SUM(l.credit),0),2)
             ORDER BY j.id DESC",
            'كل قيود الرواتب والعكس المرحّلة متوازنة.',
            'يوجد قيد رواتب غير متوازن.',
        ],
    ];

    foreach ($queries as $key => [$title, $sql, $passSummary, $failSummary]) {
        $result = mobilePayrollIntegrityRows($sql);
        $add($key, $title, $result['count'] === 0,
            $result['count'] === 0 ? $passSummary : $failSummary, $result);
    }

    if (mobilePayrollIntegrityTableExists('hr_payroll_reversals')) {
        $result = mobilePayrollIntegrityRows(
            "SELECT r.id,r.payroll_id,r.original_entry_id,r.reversal_entry_id,
                    CASE WHEN op.id IS NULL THEN 1 ELSE 0 END AS original_missing,
                    CASE WHEN rv.id IS NULL THEN 1 ELSE 0 END AS reversal_missing
             FROM hr_payroll_reversals r
             LEFT JOIN journal_entries op ON op.id=r.original_entry_id AND op.status='posted'
             LEFT JOIN journal_entries rv ON rv.id=r.reversal_entry_id AND rv.status='posted' AND rv.reference_type='payroll_reversal' AND rv.reference_id=r.payroll_id
             WHERE op.id IS NULL OR rv.id IS NULL
             ORDER BY r.id DESC"
        );
        $add('reversal_audit', 'سلامة سجل عكس الرواتب', $result['count'] === 0,
            $result['count'] === 0 ? 'كل عمليات العكس المسجلة مرتبطة بالقيد الأصلي وقيد العكس.' : 'يوجد سجل عكس غير مكتمل الارتباط بالقيود المحاسبية.',
            $result);
    } else {
        $add('reversal_audit', 'سلامة سجل عكس الرواتب', true,
            'جدول سجل العكس لم يُنشأ بعد؛ لا توجد بيانات تدقيق لفحصها.');
    }

    $failed = count(array_filter($checks, static fn(array $check): bool => !$check['ok']));
    ak_api_json(200, ['ok' => true, 'data' => [
        'checks' => $checks,
        'total_checks' => count($checks),
        'passed_checks' => count($checks) - $failed,
        'failed_checks' => $failed,
        'read_only' => true,
    ]]);
} catch (Throwable $e) {
    error_log('Mobile payroll integrity check failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => [
        'code' => 'payroll_integrity_failed',
        'message' => 'The payroll integrity checks could not be completed.',
    ]]);
}
