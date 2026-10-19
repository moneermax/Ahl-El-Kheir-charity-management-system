<?php
declare(strict_types=1);

/**
 * Returns the employees eligible for the selected payroll period using the
 * same period-end salary-history, hire-date and working-state rules as payroll
 * generation. Completion requires every eligible employee to have a paid,
 * accounting-posted payroll with a journal entry.
 */
function hrGmPayrollPeriodState(PDO $pdo, int $month, int $year): array
{
    if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
        throw new InvalidArgumentException('فترة الرواتب غير صالحة.');
    }

    $periodEnd = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    $candidates = dbFetchAll(
        "SELECT e.id AS employee_id, e.employee_code, e.full_name,
                s.basic_salary AS effective_basic_salary,
                s.salary_currency,
                p.id AS payroll_id, p.month, p.year, p.basic_salary,
                p.allowances, p.overtime, p.deductions,
                p.attendance_deduction, p.salary_advance_deduction,
                p.incentive_amount, p.net_salary, p.payment_date,
                p.accounting_entry_id, p.accounting_status, p.status
         FROM employees e
         JOIN hr_employee_salary_history s ON s.id = (
             SELECT s2.id
             FROM hr_employee_salary_history s2
             WHERE s2.employee_id = e.id
               AND s2.effective_from <= ?
               AND (s2.effective_to IS NULL OR s2.effective_to >= ?)
             ORDER BY s2.effective_from DESC, s2.id DESC
             LIMIT 1
         )
         LEFT JOIN payroll p
           ON p.employee_id = e.id AND p.month = ? AND p.year = ?
         WHERE (e.hire_date IS NULL OR e.hire_date <= ?)
         ORDER BY e.full_name ASC, e.id ASC",
        [$periodEnd, $periodEnd, $month, $year, $periodEnd]
    );

    $eligible = [];
    $paidCount = 0;
    foreach ($candidates as $candidate) {
        $state = dbFetchOne(
            "SELECT s.category
             FROM hr_employee_state_history h
             JOIN hr_employment_states s ON s.id = h.employment_state_id
             WHERE h.employee_id = ?
               AND h.effective_from <= ?
               AND (h.effective_to IS NULL OR h.effective_to >= ?)
             ORDER BY h.effective_from DESC, h.id DESC
             LIMIT 1",
            [(int)$candidate['employee_id'], $periodEnd . ' 23:59:59', $periodEnd . ' 00:00:00']
        );

        if (!$state || $state['category'] !== 'working'
            || (float)$candidate['effective_basic_salary'] <= 0) {
            continue;
        }

        $isPaidAndPosted = ($candidate['status'] ?? '') === 'paid'
            && ($candidate['accounting_status'] ?? '') === 'posted'
            && (int)($candidate['accounting_entry_id'] ?? 0) > 0;
        $candidate['completion_paid'] = $isPaidAndPosted;
        if ($isPaidAndPosted) {
            $paidCount++;
        }
        $eligible[] = $candidate;
    }

    $eligibleCount = count($eligible);
    return [
        'month' => $month,
        'year' => $year,
        'period_end' => $periodEnd,
        'eligible_count' => $eligibleCount,
        'paid_count' => $paidCount,
        'complete' => $eligibleCount > 0 && $paidCount === $eligibleCount,
        'employees' => $eligible,
    ];
}

/**
 * Inform active GM accounts once the complete period is paid and posted.
 * Notification failures must never affect payroll or accounting.
 */
function hrGmPayrollNotifyPeriodComplete(PDO $pdo, int $month, int $year): void
{
    try {
        $state = hrGmPayrollPeriodState($pdo, $month, $year);
        if (!$state['complete']) {
            return;
        }

        $period = sprintf('%04d-%02d', $year, $month);
        $link = APP_URL . 'modules/hr/gm_payroll_period_report.php?month=' . $month . '&year=' . $year;
        $title = 'اكتمل صرف رواتب الفترة ' . $period;
        $body = 'اكتمل صرف رواتب جميع الموظفين المؤهلين للفترة ' . $period
            . '، وتم ترحيل القيود المحاسبية. يمكنك عرض التقرير الموحد وطباعته.';

        $recipients = dbFetchAll(
            "SELECT u.id
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.is_active = 1 AND r.code IN ('general_manager', 'gm')"
        );

        foreach ($recipients as $recipient) {
            $recipientId = (int)$recipient['id'];
            if ($recipientId <= 0) {
                continue;
            }
            $existing = dbFetchOne(
                "SELECT id FROM notifications
                 WHERE recipient_user_id = ? AND link = ? AND title = ?
                 LIMIT 1",
                [$recipientId, $link, $title]
            );
            if ($existing) {
                continue;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO notifications
                    (recipient_user_id, type, title, body, link, is_read, created_at)
                 VALUES (?, 'info', ?, ?, ?, 0, NOW())"
            );
            $stmt->execute([$recipientId, $title, $body, $link]);
        }
    } catch (Throwable $notificationError) {
        // This is informational only. Never affect committed payroll/accounting.
        return;
    }
}
