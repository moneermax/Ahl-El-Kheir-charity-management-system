<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_employee_identity.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'login.php');
    exit;
}

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

$pdo = db();
$employee = hrGetEmployeeForUser($pdo, (int)Session::getUserID());
$payrollId = max(0, (int)($_GET['id'] ?? 0));
$statement = null;

if ($employee && $payrollId > 0) {
    $statement = dbFetchOne(
        "SELECT p.id, p.employee_id, p.month, p.year, p.basic_salary, p.allowances,
                p.overtime, p.deductions, p.attendance_deduction,
                p.salary_advance_deduction, p.incentive_amount, p.net_salary,
                p.payment_date, p.accounting_entry_id, p.accounting_status,
                e.employee_code, e.full_name AS employee_name,
                s.salary_currency
         FROM payroll p
         JOIN employees e ON e.id = p.employee_id
         LEFT JOIN hr_employee_salary_history s ON s.id = (
             SELECT s2.id
             FROM hr_employee_salary_history s2
             WHERE s2.employee_id = p.employee_id
               AND s2.effective_from <= LAST_DAY(STR_TO_DATE(CONCAT(p.year, '-', LPAD(p.month, 2, '0'), '-01'), '%Y-%m-%d'))
               AND (s2.effective_to IS NULL OR s2.effective_to >= LAST_DAY(STR_TO_DATE(CONCAT(p.year, '-', LPAD(p.month, 2, '0'), '-01'), '%Y-%m-%d')))
             ORDER BY s2.effective_from DESC, s2.id DESC
             LIMIT 1
         )
         WHERE p.id = ?
           AND p.employee_id = ?
           AND p.status = 'paid'
           AND p.accounting_status = 'posted'
         LIMIT 1",
        [$payrollId, (int)$employee['id']]
    );
}

if (!$statement) {
    http_response_code(404);
    $pageTitle = 'كشف الراتب غير متاح';
    include __DIR__ . '/../../includes/header.php';
    echo '<main class="container py-5"><div class="alert alert-warning text-center" role="alert">كشف الراتب غير متاح لهذا الحساب أو لم يكتمل ترحيله المحاسبي.</div></main>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$monthNames = [
    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
];
$month = (int)$statement['month'];
$period = ($monthNames[$month] ?? (string)$month) . ' ' . (int)$statement['year'];
$currency = trim((string)($statement['salary_currency'] ?? ''));
if ($currency === '') {
    $currency = defined('APP_CURRENCY_CODE') ? APP_CURRENCY_CODE : 'SDG';
}
$money = static fn($amount): string => number_format((float)$amount, 2) . ' ' . $currency;
$netSalary = round((float)$statement['net_salary'], 2);
$incentiveAmount = round((float)($statement['incentive_amount'] ?? 0), 2);
$totalPaid = round($netSalary + $incentiveAmount, 2);
$paymentDate = trim((string)($statement['payment_date'] ?? ''));
$pageTitle = 'كشف تفاصيل الراتب';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle . ' — ' . $period); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--ink:#172b4d;--navy:#17365d;--gold:#b38a3e;--muted:#66758a;--line:#dce3eb;--pale:#f4f7fb}
        *{box-sizing:border-box}
        body{margin:0;background:#edf1f6;color:var(--ink);font-family:'Cairo',Tahoma,Arial,sans-serif;font-size:14px}
        .statement-shell{max-width:900px;margin:28px auto;padding:0 16px}
        .statement{background:#fff;border:1px solid #e2e7ee;border-radius:16px;overflow:hidden;box-shadow:0 12px 35px rgba(23,54,93,.09)}
        .statement-top{padding:28px 34px;background:linear-gradient(120deg,#142b4b,#234a75);color:#fff;border-bottom:4px solid var(--gold)}
        .brandline{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap}
        .brand{font-weight:800;font-size:21px;letter-spacing:.1px}
        .brand small{display:block;font-size:11px;font-weight:500;opacity:.78;direction:ltr;text-align:right}
        .doc-kind{text-align:left;direction:ltr}
        .doc-kind strong{display:block;font-size:18px;letter-spacing:1.3px}
        .doc-kind span{font-size:13px;opacity:.9}
        .period-ribbon{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:24px;padding-top:18px;border-top:1px solid rgba(255,255,255,.2)}
        .period-ribbon .period{font-size:19px;font-weight:800}
        .period-ribbon .status{align-self:center;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.28);padding:6px 13px;border-radius:999px;font-size:12px}
        .statement-body{padding:28px 34px 32px}
        .section-title{display:flex;align-items:center;gap:10px;font-weight:800;font-size:14px;margin:0 0 14px;color:var(--navy)}
        .section-title:before{content:"";width:4px;height:19px;background:var(--gold);border-radius:4px}
        .employee-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:28px}
        .info-cell{background:var(--pale);border:1px solid #e5ebf2;border-radius:9px;padding:12px 14px;min-width:0}
        .info-label{font-size:11px;color:var(--muted);margin-bottom:4px}
        .info-value{font-weight:700;overflow-wrap:anywhere}
        .info-value small{display:block;font-size:10px;color:var(--muted);font-weight:500;direction:ltr;text-align:right}
        .pay-table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid var(--line);border-radius:10px;overflow:hidden;margin-bottom:22px}
        .pay-table th{background:#eef3f9;color:var(--navy);font-size:12px;font-weight:800;padding:12px 15px}
        .pay-table td{padding:12px 15px;border-top:1px solid var(--line);vertical-align:middle}
        .pay-table .amount{font-weight:700;white-space:nowrap;text-align:left;direction:ltr}
        .pay-table .subhead td{background:#fafbfd;color:#64748b;font-size:11px;font-weight:800;padding:8px 15px}
        .pay-table .muted-row td{color:#64748b}
        .totals{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:8px}
        .total-card{border:1px solid var(--line);border-radius:11px;padding:15px 18px}
        .total-label{font-size:12px;color:var(--muted)}
        .total-value{font-size:19px;font-weight:800;margin-top:5px;direction:ltr;text-align:right}
        .total-card.net{background:#f7f9fc}
        .total-card.paid{background:#f8f5ed;border-color:#e6d8b8}
        .total-card.paid .total-value{font-size:24px;color:#17365d}
        .payment-meta{display:flex;gap:18px;flex-wrap:wrap;padding:15px 0 0;margin-top:20px;border-top:1px solid var(--line);font-size:12px;color:var(--muted)}
        .payment-meta strong{color:var(--ink)}
        .statement-foot{padding:16px 34px;background:#fafbfd;border-top:1px solid var(--line);color:#68778b;font-size:11px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
        .actions{display:flex;justify-content:center;gap:10px;margin:18px auto 28px}
        .actions button{border:0;border-radius:8px;padding:10px 20px;font:700 13px 'Cairo',sans-serif;cursor:pointer}
        .print-btn{background:var(--navy);color:#fff}.back-btn{background:#fff;color:var(--navy);border:1px solid var(--line)!important}
        @media(max-width:620px){.statement-shell{margin:10px auto;padding:0 8px}.statement-top,.statement-body{padding:20px 16px}.statement-foot{padding:14px 16px}.employee-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.totals{grid-template-columns:1fr}.doc-kind{text-align:right}.brandline{align-items:flex-start}}
        @page{size:A4;margin:12mm}
        @media print{body{background:#fff;font-size:12px}.statement-shell{max-width:none;margin:0;padding:0}.statement{border:0;border-radius:0;box-shadow:none}.statement-top{-webkit-print-color-adjust:exact;print-color-adjust:exact;border-radius:0}.statement-body{padding:20px 24px}.statement-foot{padding:12px 24px}.actions{display:none!important}.employee-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.total-card.paid{-webkit-print-color-adjust:exact;print-color-adjust:exact}.pay-table th,.pay-table .subhead td{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
    </style>
</head>
<body>
<main class="statement-shell">
    <article class="statement">
        <header class="statement-top">
            <div class="brandline">
                <div class="brand">جمعية أهل الخير<small>AHL EL KHEIR CHARITY</small></div>
                <div class="doc-kind"><strong>SALARY STATEMENT</strong><span>بيان تفاصيل الراتب</span></div>
            </div>
            <div class="period-ribbon">
                <div><div style="font-size:11px;opacity:.78">فترة الراتب · PAY PERIOD</div><div class="period"><?php echo e($period); ?></div></div>
                <div class="status">تم الصرف والترحيل المحاسبي · PAID</div>
            </div>
        </header>
        <div class="statement-body">
            <h2 class="section-title">بيانات الموظف <span style="font-size:11px;color:var(--muted);font-weight:500">EMPLOYEE DETAILS</span></h2>
            <div class="employee-grid">
                <div class="info-cell"><div class="info-label">اسم الموظف · Employee</div><div class="info-value"><?php echo e((string)$statement['employee_name']); ?></div></div>
                <div class="info-cell"><div class="info-label">الرقم الوظيفي · Employee ID</div><div class="info-value"><?php echo e((string)$statement['employee_code']); ?></div></div>
                <div class="info-cell"><div class="info-label">العملة · Currency</div><div class="info-value"><?php echo e($currency); ?></div></div>
            </div>

            <h2 class="section-title">تفاصيل الاستحقاقات والاستقطاعات <span style="font-size:11px;color:var(--muted);font-weight:500">EARNINGS &amp; DEDUCTIONS</span></h2>
            <table class="pay-table">
                <thead><tr><th>البيان · Description</th><th class="amount">المبلغ · Amount</th></tr></thead>
                <tbody>
                    <tr><td>الراتب الأساسي <div class="info-label">Basic salary</div></td><td class="amount"><?php echo e($money($statement['basic_salary'])); ?></td></tr>
                    <tr><td>البدلات <div class="info-label">Allowances</div></td><td class="amount"><?php echo e($money($statement['allowances'])); ?></td></tr>
                    <tr><td>العمل الإضافي <div class="info-label">Overtime</div></td><td class="amount"><?php echo e($money($statement['overtime'])); ?></td></tr>
                    <tr class="subhead"><td colspan="2">الاستقطاعات · DEDUCTIONS</td></tr>
                    <tr><td>الاستقطاعات العامة <div class="info-label">General deductions</div></td><td class="amount">− <?php echo e($money($statement['deductions'])); ?></td></tr>
                    <tr><td>استقطاع الحضور والإجازات <div class="info-label">Attendance / leave deduction</div></td><td class="amount">− <?php echo e($money($statement['attendance_deduction'])); ?></td></tr>
                    <tr><td>استقطاع سلفة الراتب <div class="info-label">Salary advance repayment</div></td><td class="amount">− <?php echo e($money($statement['salary_advance_deduction'])); ?></td></tr>
                    <tr class="subhead"><td colspan="2">ملخص الصرف · PAYMENT SUMMARY</td></tr>
                    <tr><td><strong>صافي الراتب المصروف</strong><div class="info-label">Net salary paid, after recorded deductions</div></td><td class="amount"><strong><?php echo e($money($netSalary)); ?></strong></td></tr>
                    <?php if ($incentiveAmount > 0): ?>
                    <tr><td>الحوافز المصروفة <div class="info-label">Incentives paid with this payroll</div></td><td class="amount"><?php echo e($money($incentiveAmount)); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="totals">
                <div class="total-card net"><div class="total-label">صافي الراتب · NET SALARY</div><div class="total-value"><?php echo e($money($netSalary)); ?></div></div>
                <div class="total-card paid"><div class="total-label">إجمالي المبلغ المصروف · TOTAL PAID</div><div class="total-value"><?php echo e($money($totalPaid)); ?></div></div>
            </div>
            <div class="payment-meta">
                <span>تاريخ الصرف · Payment date: <strong><?php echo e($paymentDate !== '' ? $paymentDate : '—'); ?></strong></span>
                <span>مرجع القيد المحاسبي · Journal reference: <strong><?php echo e((string)($statement['accounting_entry_id'] ?? '—')); ?></strong></span>
            </div>
        </div>
        <footer class="statement-foot">
            <span>كشف إلكتروني صادر من نظام أهل الخير بعد اكتمال تسجيل الصرف والترحيل المحاسبي.</span>
            <span>Electronic statement · <?php echo e((string)$statement['employee_code']); ?> · <?php echo e(sprintf('%04d-%02d', (int)$statement['year'], $month)); ?></span>
        </footer>
    </article>
    <div class="actions">
        <button type="button" class="print-btn" onclick="window.print()">طباعة / حفظ PDF · Print / Save PDF</button>
        <button type="button" class="back-btn" onclick="history.back()">رجوع · Back</button>
    </div>
</main>
</body>
</html>
