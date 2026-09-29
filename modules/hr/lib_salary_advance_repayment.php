<?php
declare(strict_types=1);

require_once __DIR__ . '/lib_salary_advance_request.php';

/**
 * Stage 5 — salary advance repayment schedule generation.
 *
 * This unit only creates the approved repayment plan after disbursement.
 * Payroll deduction calculation, accounting, balance reduction, and
 * repayment allocation are implemented in later Stage 5 units.
 */
function hrSalaryAdvanceScheduleGetRequestForUpdate(PDO $pdo, int $requestId): ?array
{
    if ($requestId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT r.*,
                p.maximum_monthly_deduction,
                p.maximum_repayment_months,
                p.repayment_start_rule,
                p.eligible_salary_basis,
                p.insufficient_salary_rule,
                e.full_name AS employee_name,
                e.employee_code
         FROM hr_salary_advance_requests r
         JOIN hr_salary_advance_policy_versions p
           ON p.id = r.policy_version_id
         JOIN employees e
           ON e.id = r.employee_id
         WHERE r.id = ?
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$requestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function hrSalaryAdvanceScheduleFirstMonth(array $request): string
{
    $disbursedAt = trim((string)($request['disbursed_at'] ?? ''));
    if ($disbursedAt === '') {
        throw new RuntimeException('لا يمكن إنشاء جدول السداد قبل تسجيل تاريخ صرف السلفة.');
    }

    $nextPayrollMonth = date('Y-m-01', strtotime(date('Y-m-01', strtotime($disbursedAt)) . ' +1 month'));

    if ((string)($request['repayment_start_rule'] ?? 'next_payroll') === 'specified_month') {
        $approvedStart = trim((string)($request['approved_start_month'] ?? ''));
        if ($approvedStart === '') {
            throw new RuntimeException('شهر بدء السداد المعتمد غير محدد.');
        }

        $d = DateTime::createFromFormat('!Y-m-d', $approvedStart);
        if (!$d || $d->format('Y-m-d') !== $approvedStart) {
            throw new RuntimeException('شهر بدء السداد المعتمد غير صالح.');
        }

        return max($approvedStart, $nextPayrollMonth);
    }

    return $nextPayrollMonth;
}

function hrSalaryAdvanceScheduleGet(PDO $pdo, int $requestId): array
{
    if ($requestId <= 0) {
        return [];
    }

    return dbFetchAll(
        "SELECT installment_no, scheduled_month, scheduled_amount, applied_amount, status,
                applied_payroll_id, applied_at, skip_reason
         FROM hr_salary_advance_repayment_schedule
         WHERE salary_advance_request_id = ?
         ORDER BY installment_no",
        [$requestId]
    );
}

function hrSalaryAdvanceScheduleGenerate(PDO $pdo, int $requestId, ?int $userId = null): int
{
    if ($requestId <= 0) {
        throw new InvalidArgumentException('طلب السلفة غير صالح.');
    }

    $request = hrSalaryAdvanceScheduleGetRequestForUpdate($pdo, $requestId);
    if (!$request) {
        throw new RuntimeException('طلب السلفة غير موجود.');
    }

    if ((string)$request['status'] !== 'disbursed') {
        throw new RuntimeException('لا يمكن إنشاء جدول السداد إلا بعد صرف السلفة فعلياً.');
    }

    $approvedAmount = round((float)($request['approved_amount'] ?? 0), 2);
    $outstanding = round((float)($request['outstanding_balance'] ?? 0), 2);
    if ($approvedAmount <= 0 || $outstanding <= 0) {
        throw new RuntimeException('لا يوجد رصيد قائم صالح لإنشاء جدول السداد.');
    }

    $existing = dbFetchOne(
        "SELECT COUNT(*) AS c
         FROM hr_salary_advance_repayment_schedule
         WHERE salary_advance_request_id = ?",
        [$requestId]
    );
    if ((int)($existing['c'] ?? 0) > 0) {
        return (int)$existing['c'];
    }

    $method = (string)($request['approved_repayment_method'] ?? '');
    if (!in_array($method, ['fixed_monthly', 'full_eligible_salary'], true)) {
        throw new RuntimeException('طريقة السداد المعتمدة لا تدخل ضمن جدول سداد الرواتب في Stage 5.');
    }

    $maximumDeduction = $request['maximum_monthly_deduction'] !== null
        ? round((float)$request['maximum_monthly_deduction'], 2)
        : null;
    if ($maximumDeduction !== null && $maximumDeduction <= 0) {
        $maximumDeduction = null;
    }

    $maximumMonths = $request['maximum_repayment_months'] !== null
        ? (int)$request['maximum_repayment_months']
        : null;

    $startMonth = hrSalaryAdvanceScheduleFirstMonth($request);
    $installments = [];

    if ($method === 'fixed_monthly') {
        $approvedMonthly = round((float)($request['approved_monthly_amount'] ?? 0), 2);
        if ($approvedMonthly <= 0) {
            throw new RuntimeException('القسط الشهري المعتمد غير صالح.');
        }

        $scheduledMonthly = $maximumDeduction !== null
            ? min($approvedMonthly, $maximumDeduction)
            : $approvedMonthly;

        $requiredMonths = (int)ceil($outstanding / $scheduledMonthly);
        if ($maximumMonths !== null && $requiredMonths > $maximumMonths) {
            throw new RuntimeException('شروط السداد المعتمدة تتجاوز الحد الأقصى لعدد أشهر السداد في السياسة المرجعية.');
        }

        $remaining = $outstanding;
        for ($i = 1; $i <= $requiredMonths && $remaining > 0.000001; $i++) {
            $amount = round(min($scheduledMonthly, $remaining), 2);
            $month = date('Y-m-01', strtotime($startMonth . ' +' . ($i - 1) . ' month'));
            $installments[] = [$i, $month, $amount];
            $remaining = round($remaining - $amount, 2);
        }
    } else {
        // Full eligible salary is determined from the actual payroll period,
        // so the schedule stores the maximum permitted monthly amount rather
        // than guessing a salary value at disbursement time. A finite horizon
        // is required so the generated plan remains deterministic.
        if ($maximumMonths === null) {
            throw new RuntimeException('طريقة خصم كامل الراتب المؤهل تتطلب تحديد أقصى عدد أشهر للسداد قبل إنشاء الجدول.');
        }

        $scheduledMonthly = $maximumDeduction !== null ? $maximumDeduction : $outstanding;
        $remaining = $outstanding;

        for ($i = 1; $i <= $maximumMonths && $remaining > 0.000001; $i++) {
            $amount = round(min($scheduledMonthly, $remaining), 2);
            $month = date('Y-m-01', strtotime($startMonth . ' +' . ($i - 1) . ' month'));
            $installments[] = [$i, $month, $amount];
            $remaining = round($remaining - $amount, 2);
        }

        if ($remaining > 0.000001) {
            throw new RuntimeException('الحد الأقصى لأشهر السداد لا يغطي الرصيد وفق الحد الأقصى الشهري المسموح.');
        }
    }

    if (!$installments) {
        throw new RuntimeException('تعذر إنشاء أي قسط سداد.');
    }

    $insert = $pdo->prepare(
        "INSERT INTO hr_salary_advance_repayment_schedule
            (salary_advance_request_id, installment_no, scheduled_month, scheduled_amount, status)
         VALUES (?, ?, ?, ?, 'pending')"
    );

    foreach ($installments as [$number, $month, $amount]) {
        $insert->execute([$requestId, $number, $month, $amount]);
    }

    if ($userId !== null && $userId > 0) {
        dbExecute(
            "INSERT INTO audit_log
             (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'HR_SALARY_ADVANCE_SCHEDULE_GENERATE', 'hr_salary_advance_request', ?, ?, ?, ?, ?)",
            [
                $userId,
                $requestId,
                json_encode(['schedule_rows' => 0], JSON_UNESCAPED_UNICODE),
                json_encode([
                    'schedule_rows' => count($installments),
                    'start_month' => $startMonth,
                    'repayment_method' => $method,
                    'approved_amount' => $approvedAmount,
                    'outstanding_balance' => $outstanding,
                ], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
    }

    return count($installments);
}
