<?php
declare(strict_types=1);

/**
 * Read-only sponsorship arrears/advance reporting.
 * Uses the posted allocation ledger as the sole source of confirmed paid amounts.
 * No DDL and no writes.
 */
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/sponsor_assignments.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_sponsorship_payment_accounting.php';

function ak_sar_load_report_data(string $role, int $userId): array
{
    if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) {
        throw new RuntimeException('ليس لديك صلاحية عرض تقارير المتأخرات أو الدفعات المقدمة.');
    }

    $allowedSponsorIds = null;
    if ($role === 'supervisor') {
        $candidates = dbFetchAll(
            "SELECT id, supervisor_id, first_letter_id, gender, is_manual_override, sponsor_type, created_by
             FROM sponsors
             WHERE first_letter_id IN (SELECT letter_id FROM supervisor_letters WHERE supervisor_id = ?)
                OR (is_manual_override = 1 AND supervisor_id = ?)
                OR (sponsor_type IN ('company','organization') AND created_by = ?)",
            [$userId, $userId, $userId]
        );
        $allowedSponsorIds = [];
        foreach ($candidates as $candidate) {
            if (supervisorCanAccessSponsor($userId, $candidate)) {
                $allowedSponsorIds[] = (int)$candidate['id'];
            }
        }
        $allowedSponsorIds = array_values(array_unique($allowedSponsorIds));
        if (!$allowedSponsorIds) {
            return ['sponsorships'=>[], 'allocations'=>[], 'pause_events'=>[], 'pending'=>[]];
        }
    }

    $sql = "SELECT sp.id, sp.sponsorship_code, sp.monthly_amount, sp.status, sp.start_date,
                   sp.end_date, sp.pause_start_date, sp.pause_end_date,
                   s.id AS sponsor_id, s.full_name AS sponsor_name, s.sponsor_code,
                   s.supervisor_id, s.first_letter_id, s.gender, s.is_manual_override,
                   s.sponsor_type, s.created_by AS sponsor_created_by,
                   fc.child_name
            FROM sponsorships sp
            JOIN sponsors s ON s.id = sp.sponsor_id
            JOIN family_children fc ON fc.id = sp.child_id";
    $params = [];
    if ($allowedSponsorIds !== null) {
        $sql .= " WHERE s.id IN (" . implode(',', array_fill(0, count($allowedSponsorIds), '?')) . ")";
        $params = $allowedSponsorIds;
    }
    $sql .= " ORDER BY s.full_name, fc.child_name, sp.id";
    $sponsorships = dbFetchAll($sql, $params);
    if (!$sponsorships) {
        return ['sponsorships'=>[], 'allocations'=>[], 'pause_events'=>[], 'pending'=>[]];
    }

    // Re-check each supervisor-visible sponsor with the canonical authorization function.
    if ($role === 'supervisor') {
        $sponsorships = array_values(array_filter($sponsorships, static function(array $row) use ($userId): bool {
            return supervisorCanAccessSponsor($userId, [
                'id'=>$row['sponsor_id'],
                'supervisor_id'=>$row['supervisor_id'],
                'first_letter_id'=>$row['first_letter_id'],
                'gender'=>$row['gender'],
                'is_manual_override'=>$row['is_manual_override'],
                'sponsor_type'=>$row['sponsor_type'],
                'created_by'=>$row['sponsor_created_by']
            ]);
        }));
    }
    if (!$sponsorships) {
        return ['sponsorships'=>[], 'allocations'=>[], 'pause_events'=>[], 'pending'=>[]];
    }

    $ids = array_values(array_unique(array_map(static fn(array $r): int => (int)$r['id'], $sponsorships)));
    $marks = implode(',', array_fill(0, count($ids), '?'));

    $allocationRows = dbFetchAll(
        "SELECT a.sponsorship_id, a.covered_month,
                COUNT(DISTINCT a.commitment_amount) AS commitment_versions,
                MIN(a.commitment_amount) AS commitment_amount,
                SUM(a.allocated_amount) AS allocated_amount,
                SUM(a.unallocated_amount) AS unallocated_amount
         FROM sponsorship_payment_allocations a
         JOIN transactions t ON t.id = a.transaction_id
         WHERE a.sponsorship_id IN ($marks)
           AND t.status = 'posted' AND t.voided_at IS NULL
         GROUP BY a.sponsorship_id, a.covered_month",
        $ids
    );
    $allocations = [];
    foreach ($allocationRows as $row) {
        $sid = (int)$row['sponsorship_id'];
        $month = (string)($row['covered_month'] ?? '');
        if ($month !== '') $allocations[$sid][$month] = $row;
    }

    $pauseRows = dbFetchAll(
        "SELECT sponsorship_id, event_type, effective_date
         FROM sponsorship_pause_events
         WHERE sponsorship_id IN ($marks)
         ORDER BY sponsorship_id, effective_date, id",
        $ids
    );
    $pauseEvents = [];
    foreach ($pauseRows as $row) $pauseEvents[(int)$row['sponsorship_id']][] = $row;

    // Pending/returned entries are displayed separately and never reduce arrears.
    // Approved sponsor_payments are intentionally omitted: their linked transaction
    // is the single accounting record.
    $pending = [];
    $directRows = dbFetchAll(
        "SELECT id, sponsorship_id, payment_period, amount, status,
                transaction_date AS created_at, 'transaction' AS source
         FROM transactions
         WHERE sponsorship_id IN ($marks)
           AND transaction_type = 'sponsorship_payment'
           AND status IN ('pending_fm_review','returned')",
        $ids
    );
    $submissionRows = dbFetchAll(
        "SELECT id, sponsorship_id, payment_period, amount, status,
                created_at, 'submission' AS source
         FROM sponsor_payments
         WHERE sponsorship_id IN ($marks)
           AND payment_type = 'monthly_sponsorship'
           AND status IN ('pending','returned')
           AND transaction_id IS NULL",
        $ids
    );
    foreach (array_merge($directRows, $submissionRows) as $row) {
        $period = trim((string)($row['payment_period'] ?? ''));
        if ($period === '') continue;
        try {
            $month = ak_sp_parse_payment_period($period);
        } catch (Throwable $e) {
            continue;
        }
        $pending[(int)$row['sponsorship_id']][$month][] = $row;
    }

    return [
        'sponsorships'=>$sponsorships,
        'allocations'=>$allocations,
        'pause_events'=>$pauseEvents,
        'pending'=>$pending
    ];
}

function ak_sar_first_full_month(string $date): DateTimeImmutable
{
    $start = new DateTimeImmutable($date);
    $first = $start->modify('first day of this month');
    return $start->format('d') === '01' ? $first : $first->modify('+1 month');
}

function ak_sar_month_rows(array $sponsorship, array $allocations, array $pauseEvents): array
{
    $id = (int)$sponsorship['id'];
    $monitoringStart = str_starts_with((string)$sponsorship['sponsorship_code'], 'IMP-SH-')
        ? '2026-08-01'
        : (string)$sponsorship['start_date'];
    if ((string)$sponsorship['start_date'] > $monitoringStart) {
        $monitoringStart = (string)$sponsorship['start_date'];
    }
    if (in_array((string)$sponsorship['status'], ['completed','cancelled'], true) && empty($sponsorship['end_date'])) {
        throw new RuntimeException('تاريخ انتهاء الكفالة غير موثوق؛ تعذر حساب الالتزامات.');
    }

    $month = ak_sar_first_full_month($monitoringStart);
    $currentMonth = new DateTimeImmutable('first day of this month');
    $lastCompleted = $currentMonth->modify('-1 month');
    $rows = [];
    while ($month <= $lastCompleted) {
        $monthStart = $month->format('Y-m-01');
        $monthEnd = $month->modify('last day of this month')->format('Y-m-d');
        if (!empty($sponsorship['end_date']) && (string)$sponsorship['end_date'] < $monthEnd) {
            $month = $month->modify('+1 month');
            continue;
        }
        if (ak_sp_month_is_pause_affected($id, $monthStart, $monthEnd, $sponsorship, $pauseEvents)) {
            $month = $month->modify('+1 month');
            continue;
        }

        $snapshot = $allocations[$monthStart] ?? null;
        if ($snapshot && (int)$snapshot['commitment_versions'] > 1) {
            throw new RuntimeException('توجد قيم التزام متعارضة لهذا الشهر.');
        }
        $commitment = $snapshot && (int)$snapshot['commitment_versions'] === 1
            ? round((float)$snapshot['commitment_amount'], 2)
            : round((float)$sponsorship['monthly_amount'], 2);
        if ($commitment <= 0) throw new RuntimeException('قيمة الالتزام الشهري غير صالحة.');
        $paid = $snapshot ? round((float)$snapshot['allocated_amount'], 2) : 0.00;
        $rows[] = [
            'month'=>$monthStart,
            'label'=>$month->format('F/Y'),
            'commitment'=>$commitment,
            'paid'=>$paid,
            'outstanding'=>max(0.00, round($commitment - $paid, 2)),
            'unallocated'=>$snapshot ? round((float)$snapshot['unallocated_amount'], 2) : 0.00
        ];
        $month = $month->modify('+1 month');
    }
    return $rows;
}

function ak_sar_get_arrears(string $role, int $userId): array
{
    $data = ak_sar_load_report_data($role, $userId);
    $result = [];
    foreach ($data['sponsorships'] as $sponsorship) {
        try {
            $months = ak_sar_month_rows(
                $sponsorship,
                $data['allocations'][(int)$sponsorship['id']] ?? [],
                $data['pause_events'][(int)$sponsorship['id']] ?? []
            );
        } catch (Throwable $e) {
            $result[] = ['sponsorship'=>$sponsorship, 'months'=>[], 'threshold'=>false, 'error'=>$e->getMessage()];
            continue;
        }
        $streak = 0;
        $threshold = false;
        $unpaid = [];
        foreach ($months as $month) {
            if ($month['outstanding'] > 0.009) {
                $streak++;
                $unpaid[] = $month;
                if ($streak >= 2) $threshold = true;
            } else {
                $streak = 0;
            }
        }
        if ($threshold) {
            $sid = (int)$sponsorship['id'];
            foreach ($unpaid as &$month) {
                $month['pending'] = $data['pending'][$sid][$month['month']] ?? [];
            }
            unset($month);
            $result[] = ['sponsorship'=>$sponsorship, 'months'=>$unpaid, 'threshold'=>true, 'error'=>null];
        }
    }
    return $result;
}

function ak_sar_get_advances(string $role, int $userId): array
{
    $data = ak_sar_load_report_data($role, $userId);
    $currentMonth = (new DateTimeImmutable('first day of this month'))->format('Y-m-01');
    $result = [];
    foreach ($data['sponsorships'] as $sponsorship) {
        $sid = (int)$sponsorship['id'];
        $future = [];
        $unallocated = 0.00;
        $seenFutureMonths = [];
        foreach ($data['allocations'][$sid] ?? [] as $month => $allocation) {
            $unallocated += round((float)$allocation['unallocated_amount'], 2);
            if ($month > $currentMonth && (float)$allocation['allocated_amount'] > 0) {
                $seenFutureMonths[$month] = true;
                if ((int)$allocation['commitment_versions'] > 1) {
                    $future[] = ['month'=>$month, 'error'=>'توجد قيم التزام متعارضة لهذا الشهر.'];
                } else {
                    $future[] = [
                        'month'=>$month,
                        'commitment'=>round((float)$allocation['commitment_amount'],2),
                        'allocated'=>round((float)$allocation['allocated_amount'],2),
                        'remaining'=>max(0.00, round((float)$allocation['commitment_amount']-(float)$allocation['allocated_amount'],2)),
                        'pending'=>$data['pending'][$sid][$month] ?? [],
                        'error'=>null
                    ];
                }
            }
        }
        // Pending/returned future submissions are visible but never count as paid.
        foreach ($data['pending'][$sid] ?? [] as $month => $items) {
            if ($month <= $currentMonth || isset($seenFutureMonths[$month])) continue;
            $future[] = [
                'month'=>$month,
                'commitment'=>round((float)$sponsorship['monthly_amount'],2),
                'allocated'=>0.00,
                'remaining'=>round((float)$sponsorship['monthly_amount'],2),
                'pending'=>$items,
                'error'=>null
            ];
            $seenFutureMonths[$month] = true;
        }
        usort($future, static fn(array $a, array $b): int => strcmp($a['month'], $b['month']));
        if ($future || $unallocated > 0.009) {
            $result[] = ['sponsorship'=>$sponsorship, 'future'=>$future, 'unallocated'=>$unallocated];
        }
    }
    return $result;
}
