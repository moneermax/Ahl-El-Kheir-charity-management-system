<?php
declare(strict_types=1);

/**
 * Stage 5 — payroll draft integration for salary-advance repayments.
 *
 * This unit only calculates the salary-advance component for a payroll draft.
 * It does not mark repayments paid, reduce the outstanding balance, or post
 * accounting entries. Those actions belong to the transactional payroll-paid
 * integration unit.
 */

function hrSalaryAdvancePayrollPendingRows(PDO $pdo, int $employeeId, string $periodStart): array
{
    if ($employeeId <= 0 || $periodStart === '') {
        return [];
    }

    return dbFetchAll(
        "SELECT
            r.id AS request_id,
            r.request_no,
            r.employee_id,
            r.outstanding_balance,
            r.approved_repayment_method,
            s.id AS schedule_id,
            s.installment_no,
            s.scheduled_amount,
            s.applied_amount,
            s.status AS schedule_status,
            p.maximum_monthly_deduction,
            p.insufficient_salary_rule,
            p.eligible_salary_basis
         FROM hr_salary_advance_repayment_schedule s
         JOIN hr_salary_advance_requests r
           ON r.id = s.salary_advance_request_id
         JOIN hr_salary_advance_policy_versions p
           ON p.id = r.policy_version_id
         WHERE r.employee_id = ?
           AND r.status = 'disbursed'
           AND COALESCE(r.outstanding_balance, 0) > 0
           AND s.scheduled_month = ?
           AND s.status IN ('pending', 'partial')
           AND NOT EXISTS (
               SELECT 1
               FROM hr_salary_advance_waiver_items wi
               JOIN hr_salary_advance_waiver_decisions wd
                 ON wd.id = wi.decision_id
               WHERE wi.salary_advance_request_id = r.id
                 AND wd.status = 'executed'
                 AND wd.effective_month <= ?
           )
         ORDER BY s.installment_no ASC, s.id ASC",
        [$employeeId, $periodStart, $periodStart]
    );
}

/**
 * Calculate the salary-advance repayment component for one payroll draft.
 *
 * The result is a preview only. No repayment/schedule state is mutated.
 */
function hrSalaryAdvancePayrollCalculateDraft(PDO $pdo, array $payroll, string $periodStart): array
{
    $employeeId = (int)($payroll['employee_id'] ?? 0);
    if ($employeeId <= 0) {
        return ['total' => 0.00, 'allocations' => []];
    }

    $rows = hrSalaryAdvancePayrollPendingRows($pdo, $employeeId, $periodStart);
    if (!$rows) {
        return ['total' => 0.00, 'allocations' => []];
    }

    $basic = round((float)($payroll['basic_salary'] ?? 0), 2);
    $allowances = round((float)($payroll['allowances'] ?? 0), 2);
    $overtime = round((float)($payroll['overtime'] ?? 0), 2);
    $ordinaryDeductions = round((float)($payroll['deductions'] ?? 0), 2);
    $attendanceDeduction = round((float)($payroll['attendance_deduction'] ?? 0), 2);

    $gross = round(max(0.00, $basic + $allowances + $overtime), 2);
    $netBeforeAdvance = round(max(0.00, $gross - $ordinaryDeductions - $attendanceDeduction), 2);

    $remainingEligibleSalary = null;
    $total = 0.00;
    $allocations = [];

    foreach ($rows as $row) {
        $scheduledRemaining = round(
            max(0.00, (float)$row['scheduled_amount'] - (float)($row['applied_amount'] ?? 0)),
            2
        );
        $requestOutstanding = round(max(0.00, (float)$row['outstanding_balance']), 2);
        $remaining = round(min($scheduledRemaining, $requestOutstanding), 2);

        if ($remaining <= 0.00) {
            continue;
        }

        $basis = (string)($row['eligible_salary_basis'] ?? 'net_before_advance');
        $eligibleSalary = $basis === 'gross' ? $gross : $netBeforeAdvance;

        if ($remainingEligibleSalary === null) {
            $remainingEligibleSalary = $eligibleSalary;
        }

        $availableSalary = round(max(0.00, $remainingEligibleSalary), 2);
        $maximumDeduction = $row['maximum_monthly_deduction'] !== null
            ? round(max(0.00, (float)$row['maximum_monthly_deduction']), 2)
            : null;

        $cap = $remaining;
        if ($maximumDeduction !== null && $maximumDeduction > 0) {
            $cap = min($cap, $maximumDeduction);
        }

        $method = (string)($row['approved_repayment_method'] ?? '');
        if ($method === 'full_eligible_salary') {
            $planned = min($cap, $availableSalary);
        } else {
            $planned = min($cap, $availableSalary);
        }

        $insufficientRule = (string)($row['insufficient_salary_rule'] ?? 'available_salary');
        $isInsufficient = $method === 'fixed_monthly'
            && $availableSalary + 0.000001 < $cap;

        $actual = $planned;
        $outcome = $actual + 0.000001 >= $remaining ? 'applied' : 'partial';
        $reason = null;

        if ($isInsufficient && $insufficientRule === 'skip_month') {
            $actual = 0.00;
            $outcome = 'skipped';
            $reason = 'راتب مؤهل غير كافٍ وفق سياسة السلفة — تم إعداد الشهر للتجاوز.';
        } elseif ($isInsufficient && $insufficientRule === 'available_salary') {
            $actual = min($planned, $availableSalary);
            $outcome = $actual + 0.000001 >= $remaining ? 'applied' : 'partial';
            $reason = 'تم تحديد الخصم وفق الراتب المؤهل المتاح.';
        }

        $actual = round(max(0.00, min($actual, $remaining)), 2);

        if ($actual > 0) {
            $remainingEligibleSalary = round(max(0.00, $remainingEligibleSalary - $actual), 2);
            $total = round($total + $actual, 2);
        }

        $allocations[] = [
            'request_id' => (int)$row['request_id'],
            'request_no' => (string)$row['request_no'],
            'schedule_id' => (int)$row['schedule_id'],
            'installment_no' => (int)$row['installment_no'],
            'scheduled_amount' => round((float)$row['scheduled_amount'], 2),
            'remaining_schedule_amount' => $remaining,
            'eligible_salary' => $eligibleSalary,
            'maximum_allowed_deduction' => $maximumDeduction,
            'actual_amount' => $actual,
            'outcome' => $outcome,
            'outcome_reason' => $reason,
        ];
    }

    return ['total' => $total, 'allocations' => $allocations];
}


/**
 * Apply the salary-advance repayment allocations when a payroll is paid.
 *
 * This function runs inside the same transaction as payroll status and
 * accounting posting. It locks the eligible request/schedule rows, rechecks
 * the draft calculation, writes one authoritative repayment trace per
 * request/payroll pair, and reduces the request outstanding balance.
 */
function hrSalaryAdvancePayrollApply(PDO $pdo, array $payroll, int $accountingEntryId): array
{
    $payrollId = (int)($payroll['id'] ?? 0);
    $employeeId = (int)($payroll['employee_id'] ?? 0);
    if ($payrollId <= 0 || $employeeId <= 0) {
        throw new RuntimeException('بيانات مسير الراتب غير صالحة لتطبيق سداد السلفة.');
    }

    $periodStart = sprintf(
        '%04d-%02d-01',
        (int)($payroll['year'] ?? 0),
        (int)($payroll['month'] ?? 0)
    );

    $salaryAdvanceDeduction = round((float)($payroll['salary_advance_deduction'] ?? 0), 2);
    if ($salaryAdvanceDeduction < 0.00) {
        throw new RuntimeException('خصم سلفة الراتب في مسير الراتب غير صالح.');
    }

    // Lock the schedule rows before recalculating so a concurrent payroll
    // action cannot consume the same installment.
    $locked = dbFetchAll(
        "SELECT s.id AS schedule_id, s.salary_advance_request_id AS request_id,
                s.scheduled_amount, s.applied_amount, s.status AS schedule_status,
                r.status AS request_status, r.outstanding_balance
         FROM hr_salary_advance_repayment_schedule s
         JOIN hr_salary_advance_requests r
           ON r.id = s.salary_advance_request_id
         WHERE r.employee_id = ?
           AND r.status = 'disbursed'
           AND COALESCE(r.outstanding_balance, 0) > 0
           AND s.scheduled_month = ?
           AND s.status IN ('pending', 'partial')
           AND NOT EXISTS (
               SELECT 1
               FROM hr_salary_advance_waiver_items wi
               JOIN hr_salary_advance_waiver_decisions wd
                 ON wd.id = wi.decision_id
               WHERE wi.salary_advance_request_id = r.id
                 AND wd.status = 'executed'
                 AND wd.effective_month <= ?
           )
         ORDER BY s.installment_no ASC, s.id ASC
         FOR UPDATE",
        [$employeeId, $periodStart, $periodStart]
    );

    if (!$locked) {
        throw new RuntimeException('مسير الراتب يحتوي على خصم سلفة، لكن لا يوجد قسط سداد قائم يمكن تطبيقه.');
    }

    $preview = hrSalaryAdvancePayrollCalculateDraft($pdo, $payroll, $periodStart);
    $calculatedTotal = round((float)($preview['total'] ?? 0), 2);
    if ($calculatedTotal !== $salaryAdvanceDeduction) {
        throw new RuntimeException('خصم السلفة في مسير الراتب لا يطابق إعادة احتساب جدول السداد؛ تم إيقاف الصرف لحماية رصيد السلفة.');
    }

    $allocations = $preview['allocations'] ?? [];
    $allocationBySchedule = [];
    foreach ($allocations as $allocation) {
        $allocationBySchedule[(int)$allocation['schedule_id']] = $allocation;
    }

    $insert = $pdo->prepare(
        "INSERT INTO hr_salary_advance_payroll_repayments
            (salary_advance_request_id, repayment_schedule_id, payroll_id,
             employee_id, eligible_salary, maximum_allowed_deduction,
             scheduled_amount, actual_amount, outcome, outcome_reason,
             accounting_entry_id, applied_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $updateSchedule = $pdo->prepare(
        "UPDATE hr_salary_advance_repayment_schedule
         SET applied_amount = ?,
             status = ?,
             applied_payroll_id = ?,
             applied_at = ?,
             skip_reason = ?
         WHERE id = ?
           AND status IN ('pending', 'partial')"
    );

    $updateRequest = $pdo->prepare(
        "UPDATE hr_salary_advance_requests
         SET outstanding_balance = ?
         WHERE id = ?
           AND status = 'disbursed'"
    );

    $existingTrace = $pdo->prepare(
        "SELECT id
         FROM hr_salary_advance_payroll_repayments
         WHERE salary_advance_request_id = ?
           AND payroll_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $totalApplied = 0.00;
    $written = 0;

    foreach ($locked as $row) {
        $scheduleId = (int)$row['schedule_id'];
        $requestId = (int)$row['request_id'];
        $allocation = $allocationBySchedule[$scheduleId] ?? [
            'eligible_salary' => 0.00,
            'maximum_allowed_deduction' => null,
            'scheduled_amount' => (float)$row['scheduled_amount'],
            'remaining_schedule_amount' => max(
                0.00,
                (float)$row['scheduled_amount'] - (float)$row['applied_amount']
            ),
            'actual_amount' => 0.00,
            'outcome' => 'skipped',
            'outcome_reason' => 'لم يتم تحديد خصم لهذا القسط وفق السياسة.',
        ];

        $actual = round(max(0.00, (float)$allocation['actual_amount']), 2);
        $oldApplied = round(max(0.00, (float)$row['applied_amount']), 2);
        $scheduledAmount = round(max(0.00, (float)$row['scheduled_amount']), 2);
        $newApplied = round($oldApplied + $actual, 2);

        if ($actual > 0.00 && $newApplied + 0.000001 >= $scheduledAmount) {
            $scheduleStatus = 'paid';
        } elseif ($actual > 0.00) {
            $scheduleStatus = 'partial';
        } else {
            $scheduleStatus = 'skipped';
        }

        $now = date('Y-m-d H:i:s');
        $appliedAt = $actual > 0.00 ? $now : null;
        $skipReason = $scheduleStatus === 'skipped'
            ? (string)($allocation['outcome_reason'] ?? 'تم تجاوز القسط وفق سياسة السداد.')
            : null;

        $existingTrace->execute([$requestId, $payrollId]);
        if ($existingTrace->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('يوجد سجل سداد رواتب سابق لنفس السلفة ومسير الراتب؛ تم إيقاف إعادة التطبيق.');
        }

        $request = dbFetchOne(
            "SELECT outstanding_balance
             FROM hr_salary_advance_requests
             WHERE id = ?
               AND employee_id = ?
               AND status = 'disbursed'
             LIMIT 1
             FOR UPDATE",
            [$requestId, $employeeId]
        );
        if (!$request) {
            throw new RuntimeException('طلب السلفة المرتبط بمسير الراتب غير موجود أو ليس في حالة صرف.');
        }

        $outstandingBefore = round(max(0.00, (float)$request['outstanding_balance']), 2);
        if ($actual > $outstandingBefore + 0.000001) {
            throw new RuntimeException('مبلغ سداد السلفة يتجاوز الرصيد القائم؛ تم إيقاف العملية.');
        }

        $outstandingAfter = round(max(0.00, $outstandingBefore - $actual), 2);

        $insert->execute([
            $requestId,
            $scheduleId,
            $payrollId,
            $employeeId,
            round((float)($allocation['eligible_salary'] ?? 0), 2),
            $allocation['maximum_allowed_deduction'] !== null
                ? round((float)$allocation['maximum_allowed_deduction'], 2)
                : null,
            $scheduledAmount,
            $actual,
            $scheduleStatus === 'skipped' ? 'skipped' : ($actual + 0.000001 >= max(0.00, $scheduledAmount - $oldApplied) ? 'applied' : 'partial'),
            $skipReason,
            $actual > 0.00 ? $accountingEntryId : null,
            $appliedAt,
        ]);

        $updateSchedule->execute([
            $newApplied,
            $scheduleStatus,
            $payrollId,
            $appliedAt,
            $skipReason,
            $scheduleId,
        ]);
        if ($updateSchedule->rowCount() !== 1) {
            throw new RuntimeException('تعذر تحديث حالة قسط سداد السلفة.');
        }

        $updateRequest->execute([$outstandingAfter, $requestId]);
        if ($updateRequest->rowCount() !== 1) {
            throw new RuntimeException('تعذر تحديث الرصيد القائم للسلفة.');
        }

        $auditUserId = null;
        if (class_exists('Session')) {
            $sessionUserId = Session::getUserId();
            if ($sessionUserId !== null && (int)$sessionUserId > 0) {
                $auditUserId = (int)$sessionUserId;
            }
        }
        dbExecute(
            "INSERT INTO audit_log
             (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'HR_SALARY_ADVANCE_PAYROLL_REPAYMENT', 'hr_salary_advance_request', ?, ?, ?, ?, ?)",
            [
                $auditUserId,
                $requestId,
                json_encode([
                    'outstanding_balance' => $outstandingBefore,
                    'schedule_status' => (string)$row['schedule_status'],
                    'applied_amount' => $oldApplied,
                ], JSON_UNESCAPED_UNICODE),
                json_encode([
                    'outstanding_balance' => $outstandingAfter,
                    'schedule_status' => $scheduleStatus,
                    'applied_amount' => $newApplied,
                    'payroll_id' => $payrollId,
                    'actual_amount' => $actual,
                    'outcome' => $scheduleStatus === 'skipped' ? 'skipped' : ($actual + 0.000001 >= max(0.00, $scheduledAmount - $oldApplied) ? 'applied' : 'partial'),
                    'accounting_entry_id' => $actual > 0.00 ? $accountingEntryId : null,
                ], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );

        $totalApplied = round($totalApplied + $actual, 2);
        $written++;
    }

    if ($totalApplied !== $salaryAdvanceDeduction) {
        throw new RuntimeException('إجمالي سداد السلفة المطبق لا يطابق خصم السلفة في مسير الراتب.');
    }

    return [
        'total' => $totalApplied,
        'allocations' => $allocations,
        'trace_rows' => $written,
    ];
}


/**
 * Notify the employee after a committed payroll repayment allocation.
 *
 * Notifications are deliberately emitted after the payroll transaction
 * commits. They are informational only; the repayment tables and journal
 * remain authoritative.
 */
function hrSalaryAdvancePayrollNotifyApplied(PDO $pdo, int $payrollId): void
{
    if ($payrollId <= 0) {
        return;
    }

    require_once dirname(__DIR__) . '/accounting/lib_transaction_review.php';

    $rows = dbFetchAll(
        "SELECT pr.id AS repayment_id,
                pr.salary_advance_request_id AS request_id,
                pr.actual_amount,
                pr.outcome,
                pr.outcome_reason,
                r.request_no,
                r.outstanding_balance,
                e.user_id AS employee_user_id,
                e.full_name AS employee_name
         FROM hr_salary_advance_payroll_repayments pr
         JOIN hr_salary_advance_requests r
           ON r.id = pr.salary_advance_request_id
         JOIN employees e
           ON e.id = pr.employee_id
         WHERE pr.payroll_id = ?
         ORDER BY pr.id ASC",
        [$payrollId]
    );

    foreach ($rows as $row) {
        $employeeUserId = (int)($row['employee_user_id'] ?? 0);
        if ($employeeUserId <= 0) {
            continue;
        }

        $actual = round((float)$row['actual_amount'], 2);
        $outstanding = round(max(0.00, (float)$row['outstanding_balance']), 2);
        $outcome = (string)$row['outcome'];

        if ($outcome === 'skipped') {
            $title = 'تم تجاوز قسط سلفة الراتب';
            $body = 'تم تجاوز قسط السلفة «' . (string)$row['request_no'] . '» لهذا الشهر وفق سياسة السداد. لم يتم تخفيض الرصيد القائم.';
        } elseif ($outcome === 'partial') {
            $title = 'تم خصم جزء من قسط سلفة الراتب';
            $body = 'تم خصم ' . number_format($actual, 2) . ' ج.س. من السلفة «' . (string)$row['request_no'] . '» وفق الراتب المؤهل المتاح. الرصيد القائم: ' . number_format($outstanding, 2) . ' ج.س.';
        } elseif ($outstanding <= 0.00) {
            $title = 'اكتمل سداد سلفة الراتب';
            $body = 'تم استرداد آخر مبلغ من السلفة «' . (string)$row['request_no'] . '» عبر مسير الراتب. الرصيد القائم الآن صفر.';
        } else {
            $title = 'تم خصم قسط سلفة الراتب';
            $body = 'تم خصم ' . number_format($actual, 2) . ' ج.س. من السلفة «' . (string)$row['request_no'] . '» عبر مسير الراتب. الرصيد القائم: ' . number_format($outstanding, 2) . ' ج.س.';
        }

        if (trim((string)$row['outcome_reason']) !== '' && $outcome !== 'applied') {
            $body .= ' ' . trim((string)$row['outcome_reason']);
        }

        ak_transaction_review_notify_event(
            $employeeUserId,
            $title,
            $body,
            APP_URL . 'modules/hr/salary_advance_request.php',
            (int)$row['repayment_id'],
            'salary_advance_payroll_repayment'
        );
    }
}

function hrSalaryAdvancePayrollRefreshDraft(PDO $pdo, int $payrollId): void
{
    if ($payrollId <= 0) {
        throw new InvalidArgumentException('سجل الرواتب غير صالح.');
    }

    $payroll = dbFetchOne(
        "SELECT id, employee_id, month, year, basic_salary, allowances, overtime,
                deductions, attendance_deduction, status, salary_advance_deduction
         FROM payroll
         WHERE id = ?
         LIMIT 1",
        [$payrollId]
    );

    if (!$payroll) {
        throw new RuntimeException('سجل الرواتب غير موجود.');
    }
    if ((string)$payroll['status'] !== 'draft') {
        return;
    }

    $periodStart = sprintf(
        '%04d-%02d-01',
        (int)$payroll['year'],
        (int)$payroll['month']
    );

    $preview = hrSalaryAdvancePayrollCalculateDraft($pdo, $payroll, $periodStart);
    $salaryAdvanceDeduction = round((float)$preview['total'], 2);

    $gross = round(
        max(
            0.00,
            (float)$payroll['basic_salary']
            + (float)$payroll['allowances']
            + (float)$payroll['overtime']
        ),
        2
    );
    // Attendance deduction is a payroll deduction that must reduce the
    // employee's cash salary before salary-advance recovery is applied.
    // hrSalaryAdvancePayrollCalculateDraft() already uses the same basis
    // when eligible_salary_basis = net_before_advance.
    $net = round(
        $gross
        - (float)$payroll['deductions']
        - (float)$payroll['attendance_deduction']
        - $salaryAdvanceDeduction,
        2
    );
    if ($net < 0) {
        $net = 0.00;
    }

    dbExecute(
        "UPDATE payroll
         SET salary_advance_deduction = ?,
             net_salary = ?
         WHERE id = ?
           AND status = 'draft'",
        [$salaryAdvanceDeduction, $net, $payrollId]
    );
}
