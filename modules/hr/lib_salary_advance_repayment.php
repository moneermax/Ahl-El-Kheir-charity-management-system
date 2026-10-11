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
        "SELECT s.installment_no, s.scheduled_month, s.scheduled_amount, s.applied_amount, s.status,
                s.applied_payroll_id, s.applied_at, s.skip_reason,
                p.id AS repayment_plan_id, p.plan_no, p.status AS plan_status,
                p.start_month AS plan_start_month, p.maximum_repayment_months AS plan_duration_months,
                p.reason AS plan_reason
         FROM hr_salary_advance_repayment_schedule s
         JOIN hr_salary_advance_repayment_plans p ON p.id = s.repayment_plan_id
         WHERE s.salary_advance_request_id = ?
         ORDER BY p.plan_no ASC, s.installment_no ASC",
        [$requestId]
    );
}


/**
 * Explicit FM-managed replacement of an expired repayment plan.
 *
 * The caller must enforce the FM role and CSRF. This service independently
 * enforces the saved policy authorization, locks the request and active plan,
 * and preserves all old schedule rows and repayment allocations.
 */
function hrSalaryAdvanceScheduleReschedule(
    PDO $pdo,
    int $requestId,
    string $startMonth,
    int $durationMonths,
    float $monthlyAmount,
    string $reason,
    int $userId
): array {
    if ($requestId <= 0 || $userId <= 0) {
        throw new InvalidArgumentException('بيانات إعادة جدولة السلفة غير صالحة.');
    }

    $reason = trim($reason);
    if ($reason === '' || mb_strlen($reason) > 1000) {
        throw new InvalidArgumentException('سبب إعادة جدولة السلفة مطلوب وبحد أقصى 1000 حرف.');
    }

    $month = DateTime::createFromFormat('!Y-m-d', $startMonth);
    if (!$month || $month->format('Y-m-d') !== $startMonth || $month->format('d') !== '01') {
        throw new InvalidArgumentException('يجب أن يكون شهر بدء الخطة الجديدة هو اليوم الأول من شهر صحيح.');
    }
    if ($startMonth <= date('Y-m-01')) {
        throw new InvalidArgumentException('يجب أن تبدأ الخطة الجديدة في شهر مستقبلي.');
    }
    if ($durationMonths <= 0 || $monthlyAmount <= 0 || !is_finite($monthlyAmount)) {
        throw new InvalidArgumentException('مدة السداد والقسط الشهري يجب أن يكونا أكبر من صفر.');
    }
    $monthlyAmount = round($monthlyAmount, 2);

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $request = dbFetchOne(
            "SELECT r.*,
                    p.allow_fm_repayment_rescheduling,
                    p.maximum_repayment_months,
                    p.maximum_monthly_deduction,
                    e.user_id AS employee_user_id
             FROM hr_salary_advance_requests r
             JOIN hr_salary_advance_policy_versions p ON p.id = r.policy_version_id
             LEFT JOIN employees e ON e.id = r.employee_id
             WHERE r.id = ?
             LIMIT 1
             FOR UPDATE",
            [$requestId]
        );
        if (!$request) {
            throw new RuntimeException('طلب السلفة غير موجود.');
        }
        if ((string)$request['status'] !== 'disbursed' || (float)$request['outstanding_balance'] <= 0) {
            throw new RuntimeException('إعادة الجدولة متاحة فقط لسلفة مصروفة لها رصيد قائم.');
        }
        if ((int)($request['allow_fm_repayment_rescheduling'] ?? 0) !== 1) {
            throw new RuntimeException('إعادة الجدولة غير مصرح بها في إصدار السياسة المحفوظ لهذا الطلب.');
        }

        $policyMonths = $request['maximum_repayment_months'] !== null
            ? (int)$request['maximum_repayment_months']
            : null;
        if ($policyMonths === null || $policyMonths <= 0) {
            throw new RuntimeException('لا يمكن التحقق من استثناء مدة السداد لأن السياسة المرجعية لا تحدد حداً أقصى صالحاً للأشهر.');
        }
        if ($durationMonths > $policyMonths) {
            throw new RuntimeException('مدة الخطة الجديدة تتجاوز الحد الأقصى المحدد في السياسة المرجعية.');
        }

        $policyCap = $request['maximum_monthly_deduction'] !== null
            ? round((float)$request['maximum_monthly_deduction'], 2)
            : null;
        if ($policyCap !== null && $policyCap > 0 && $monthlyAmount > $policyCap + 0.000001) {
            throw new RuntimeException('القسط الشهري المقترح يتجاوز الحد الأقصى للخصم الشهري في السياسة.');
        }

        $activePlan = dbFetchOne(
            "SELECT *
             FROM hr_salary_advance_repayment_plans
             WHERE salary_advance_request_id = ? AND status = 'active'
             ORDER BY plan_no DESC
             LIMIT 1
             FOR UPDATE",
            [$requestId]
        );
        if (!$activePlan) {
            throw new RuntimeException('لا توجد خطة سداد نشطة يمكن استبدالها. يلزم فحص سجل الجدول قبل المتابعة.');
        }

        $horizon = DateTime::createFromFormat('!Y-m-d', (string)$activePlan['start_month']);
        if (!$horizon) {
            throw new RuntimeException('تاريخ بدء خطة السداد الحالية غير صالح.');
        }
        $horizon->modify('+' . (int)$activePlan['maximum_repayment_months'] . ' months');
        if (date('Y-m-01') < $horizon->format('Y-m-d')) {
            throw new RuntimeException('لم تنتهِ مدة خطة السداد الحالية بعد؛ لا يمكن استخدام استثناء إعادة الجدولة قبل انتهاء المدة المعتمدة.');
        }

        $outstanding = round((float)$request['outstanding_balance'], 2);
        $requiredMonths = (int)ceil($outstanding / $monthlyAmount);
        if ($requiredMonths > $durationMonths) {
            throw new RuntimeException(
                'القسط المقترح لا يغطي الرصيد القائم خلال مدة الخطة الجديدة. يلزم قسط لا يقل عن ' .
                number_format(ceil(($outstanding / $durationMonths) * 100) / 100, 2) . '.'
            );
        }

        $next = dbFetchOne(
            "SELECT COALESCE(MAX(plan_no), 0) + 1 AS next_plan_no
             FROM hr_salary_advance_repayment_plans
             WHERE salary_advance_request_id = ?",
            [$requestId]
        );
        $planNo = max(2, (int)($next['next_plan_no'] ?? 2));

        $supersede = $pdo->prepare(
            "UPDATE hr_salary_advance_repayment_plans
             SET status = 'superseded', superseded_at = CURRENT_TIMESTAMP
             WHERE id = ? AND status = 'active'"
        );
        $supersede->execute([(int)$activePlan['id']]);
        if ($supersede->rowCount() !== 1) {
            throw new RuntimeException('تغيرت الخطة النشطة أثناء إعادة الجدولة؛ أعد تحميل الطلب وحاول مرة أخرى.');
        }

        $insertPlan = $pdo->prepare(
            "INSERT INTO hr_salary_advance_repayment_plans
                (salary_advance_request_id, plan_no, status, reason,
                 outstanding_balance_at_creation, start_month,
                 maximum_repayment_months, maximum_monthly_deduction,
                 created_by, activated_at)
             VALUES (?, ?, 'active', ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
        );
        $insertPlan->execute([
            $requestId, $planNo, $reason, $outstanding, $startMonth,
            $durationMonths, $monthlyAmount, $userId
        ]);
        $planId = (int)$pdo->lastInsertId();
        if ($planId <= 0) {
            throw new RuntimeException('تعذر إنشاء إصدار خطة السداد الجديدة.');
        }

        $insertSchedule = $pdo->prepare(
            "INSERT INTO hr_salary_advance_repayment_schedule
                (salary_advance_request_id, repayment_plan_id, installment_no,
                 scheduled_month, scheduled_amount, status)
             VALUES (?, ?, ?, ?, ?, 'pending')"
        );
        $remaining = $outstanding;
        for ($i = 1; $i <= $requiredMonths && $remaining > 0.000001; $i++) {
            $amount = round(min($monthlyAmount, $remaining), 2);
            $scheduledMonth = date('Y-m-01', strtotime($startMonth . ' +' . ($i - 1) . ' months'));
            $insertSchedule->execute([$requestId, $planId, $i, $scheduledMonth, $amount]);
            $remaining = round($remaining - $amount, 2);
        }
        if ($remaining > 0.000001) {
            throw new RuntimeException('تعذر تغطية كامل الرصيد ضمن خطة السداد الجديدة.');
        }

        dbExecute(
            "INSERT INTO audit_log
             (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'HR_SALARY_ADVANCE_REPAYMENT_RESCHEDULE', 'hr_salary_advance_request', ?, ?, ?, ?, ?)",
            [
                $userId,
                $requestId,
                json_encode([
                    'active_plan_id' => (int)$activePlan['id'],
                    'plan_no' => (int)$activePlan['plan_no'],
                    'outstanding_balance' => $outstanding,
                    'start_month' => $activePlan['start_month'],
                    'maximum_repayment_months' => (int)$activePlan['maximum_repayment_months'],
                ], JSON_UNESCAPED_UNICODE),
                json_encode([
                    'active_plan_id' => $planId,
                    'plan_no' => $planNo,
                    'reason' => $reason,
                    'outstanding_balance' => $outstanding,
                    'start_month' => $startMonth,
                    'maximum_repayment_months' => $durationMonths,
                    'monthly_amount' => $monthlyAmount,
                    'schedule_rows' => $requiredMonths,
                ], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]
        );

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return [
            'plan_id' => $planId,
            'plan_no' => $planNo,
            'schedule_rows' => $requiredMonths,
            'outstanding_balance' => $outstanding,
            'request_no' => (string)$request['request_no'],
            'employee_user_id' => (int)($request['employee_user_id'] ?? 0),
        ];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
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
    if (!in_array($method, ['fixed_monthly', 'full_eligible_salary', 'full_settlement'], true)) {
        throw new RuntimeException('طريقة السداد المعتمدة لا تدخل ضمن جدول سداد الرواتب.');
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
        // Full eligible salary and full settlement are determined from the actual
        // payroll period, so the schedule stores the maximum permitted monthly
        // amount rather than guessing a salary value at disbursement time. A
        // finite horizon is required so the generated plan remains deterministic.
        if ($maximumMonths === null) {
            throw new RuntimeException('طريقة السداد من الراتب تتطلب تحديد أقصى عدد أشهر للسداد قبل إنشاء الجدول.');
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

    // Every schedule belongs to an immutable plan version. Legacy schedules
    // are backfilled by the Stage 7 migration; this creates plan 1 for newly
    // disbursed requests whose first schedule is generated after that migration.
    $plan = dbFetchOne(
        "SELECT id
         FROM hr_salary_advance_repayment_plans
         WHERE salary_advance_request_id = ? AND plan_no = 1
         LIMIT 1
         FOR UPDATE",
        [$requestId]
    );
    if ($plan) {
        $planId = (int)$plan['id'];
    } else {
        $planInsert = $pdo->prepare(
            "INSERT INTO hr_salary_advance_repayment_plans
                (salary_advance_request_id, plan_no, status, reason,
                 outstanding_balance_at_creation, start_month,
                 maximum_repayment_months, maximum_monthly_deduction,
                 created_by, activated_at)
             VALUES (?, 1, 'active', 'Initial approved repayment schedule.',
                     ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
        );
        $planInsert->execute([
            $requestId,
            $outstanding,
            $startMonth,
            $maximumMonths,
            max(0.01, $maximumDeduction ?? (float)max(array_column($installments, 2))),
            $userId,
        ]);
        $planId = (int)$pdo->lastInsertId();
    }

    $insert = $pdo->prepare(
        "INSERT INTO hr_salary_advance_repayment_schedule
            (salary_advance_request_id, repayment_plan_id, installment_no,
             scheduled_month, scheduled_amount, status)
         VALUES (?, ?, ?, ?, ?, 'pending')"
    );

    foreach ($installments as [$number, $month, $amount]) {
        $insert->execute([$requestId, $planId, $number, $month, $amount]);
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
