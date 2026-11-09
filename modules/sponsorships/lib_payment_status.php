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

function ak_sar_assert_no_pending_month_payment(int $sponsorshipId, string $paymentPeriod): void
{
    if (!db()->inTransaction()) {
        throw new LogicException('فحص تكرار التحصيل يتطلب معاملة قاعدة بيانات مفتوحة.');
    }
    if ($sponsorshipId <= 0) throw new InvalidArgumentException('رقم الكفالة غير صالح.');
    $month = ak_sp_parse_payment_period($paymentPeriod);
    if ($month === null) throw new InvalidArgumentException('شهر التحصيل غير صالح.');

    // Both submission paths lock the same sponsorship row before checking and inserting.
    // This serializes concurrent submissions even though the legacy schema has no
    // unique constraint covering the two separate pending-entry tables.
    $locked = dbFetchOne("SELECT id, status FROM sponsorships WHERE id=? FOR UPDATE", [$sponsorshipId]);
    if (!$locked) throw new RuntimeException('الكفالة المحددة غير موجودة.');
    if ((string)$locked['status'] !== 'active') {
        throw new RuntimeException('لا يمكن تسجيل تحصيل شهري إلا لكفالة نشطة.');
    }

    $direct = dbFetchOne(
        "SELECT id FROM transactions
         WHERE sponsorship_id=? AND payment_period=? AND purpose='monthly_sponsorship'
           AND transaction_type='sponsorship_payment' AND status='pending_fm_review'
         LIMIT 1",
        [$sponsorshipId, $paymentPeriod]
    );
    $supervisor = dbFetchOne(
        "SELECT id FROM sponsor_payments
         WHERE sponsorship_id=? AND payment_period=? AND payment_type='monthly_sponsorship'
           AND status='pending' AND transaction_id IS NULL
         LIMIT 1",
        [$sponsorshipId, $paymentPeriod]
    );
    if ($direct || $supervisor) {
        throw new RuntimeException('يوجد تحصيل لهذا الشهر بانتظار المراجعة المالية؛ لا يمكن تسجيل طلب مكرر.');
    }
}

function ak_sar_normalize_arrears_filters(array $filters): array
{
    $scalar = static fn($value): string => is_scalar($value) ? trim((string)$value) : '';
    $normalized = [
        'code' => $scalar($filters['code'] ?? ''),
        'sponsor' => $scalar($filters['sponsor'] ?? ''),
        'child' => $scalar($filters['child'] ?? ''),
        'supervisor_id' => max(0, is_scalar($filters['supervisor_id'] ?? null) ? (int)$filters['supervisor_id'] : 0)
    ];
    foreach (['code', 'sponsor', 'child'] as $field) {
        if (strlen($normalized[$field]) > 150) {
            throw new InvalidArgumentException('قيمة البحث طويلة أكثر من اللازم.');
        }
    }
    return $normalized;
}

function ak_sar_has_arrears_filter(array $filters): bool
{
    return $filters['code'] !== ''
        || $filters['sponsor'] !== ''
        || $filters['child'] !== ''
        || $filters['supervisor_id'] > 0;
}

function ak_sar_like_value(string $value): string
{
    return '%' . strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
}

/**
 * SQL prefilter matching the same sponsor responsibility cases that are
 * subsequently rechecked by supervisorCanAccessSponsor().
 */
function ak_sar_supervisor_scope_sql(): string
{
    return "(
        (s.is_manual_override = 1 AND s.supervisor_id = ?)
        OR (COALESCE(s.is_manual_override, 0) <> 1 AND s.sponsor_type IN ('company','organization') AND s.created_by = ?)
        OR (
            COALESCE(s.is_manual_override, 0) <> 1
            AND COALESCE(s.sponsor_type, 'individual') NOT IN ('company','organization')
            AND EXISTS (
                SELECT 1
                FROM supervisor_letters sl
                WHERE sl.supervisor_id = ?
                  AND sl.letter_id = s.first_letter_id
                  AND (
                    LOWER(TRIM(sl.gender)) IN ('both','all','كلاهما','الكل')
                    OR (
                        LOWER(TRIM(s.gender)) IN ('m','male','ذكر')
                        AND LOWER(TRIM(sl.gender)) IN ('m','male','ذكر')
                    )
                    OR (
                        LOWER(TRIM(s.gender)) IN ('f','female','أنثى','انثى')
                        AND LOWER(TRIM(sl.gender)) IN ('f','female','أنثى','انثى')
                    )
                  )
            )
        )
    )";
}

/**
 * Lightweight initial-page suggestions. These are the ten newest sponsorship
 * records in the user's authorized scope, not a pre-calculated arrears result.
 */
function ak_sar_get_arrears_candidates(string $role, int $userId): array
{
    if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) {
        throw new RuntimeException('ليس لديك صلاحية عرض تقارير المتأخرات أو الدفعات المقدمة.');
    }

    $sql = "SELECT sp.id, sp.sponsorship_code, s.full_name AS sponsor_name,
                   s.supervisor_id, s.first_letter_id, s.gender,
                   s.is_manual_override, s.sponsor_type, s.created_by,
                   fc.child_name, u.full_name AS supervisor_name
            FROM sponsorships sp
            JOIN sponsors s ON s.id = sp.sponsor_id
            LEFT JOIN family_children fc ON fc.id = sp.child_id
            LEFT JOIN users u ON u.id = s.supervisor_id";
    $params = [];
    if ($role === 'supervisor') {
        $sql .= " WHERE " . ak_sar_supervisor_scope_sql();
        $params = [$userId, $userId, $userId];
    }
    $sql .= " ORDER BY sp.id DESC LIMIT 100";
    $candidates = dbFetchAll($sql, $params);

    if ($role === 'supervisor') {
        $letterScope = dbFetchAll(
            "SELECT letter_id, gender FROM supervisor_letters WHERE supervisor_id = ?",
            [$userId]
        );
        $candidates = array_values(array_filter(
            $candidates,
            static fn(array $candidate): bool => supervisorCanAccessSponsor($userId, $candidate, $letterScope)
        ));
    }

    return array_slice($candidates, 0, 10);
}

function ak_sar_load_report_data(string $role, int $userId, ?array $filters = null): array
{
    if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) {
        throw new RuntimeException('ليس لديك صلاحية عرض تقارير المتأخرات أو الدفعات المقدمة.');
    }

    $filters = $filters === null ? null : ak_sar_normalize_arrears_filters($filters);
    if ($filters !== null && !ak_sar_has_arrears_filter($filters)) {
        throw new InvalidArgumentException('أدخل معيار بحث واحداً على الأقل قبل عرض المتأخرات.');
    }

    $sql = "SELECT sp.id, sp.child_id, sp.sponsorship_code, sp.monthly_amount, sp.status, sp.start_date,
                   sp.end_date, sp.pause_start_date, sp.pause_end_date,
                   s.id AS sponsor_id, s.full_name AS sponsor_name, s.sponsor_code,
                   fc.id AS family_child_record_id, COALESCE(fc.child_name, 'سجل اليتيم غير مرتبط') AS child_name,
                   s.supervisor_id, s.first_letter_id, s.gender, s.is_manual_override,
                   s.sponsor_type, s.created_by AS sponsor_created_by
            FROM sponsorships sp
            JOIN sponsors s ON s.id = sp.sponsor_id
            LEFT JOIN family_children fc ON fc.id = sp.child_id";
    $where = [];
    $params = [];

    if ($role === 'supervisor') {
        $where[] = ak_sar_supervisor_scope_sql();
        $params = array_merge($params, [$userId, $userId, $userId]);
    }

    if ($filters !== null) {
        if ($filters['code'] !== '') {
            $where[] = "sp.sponsorship_code LIKE ? ESCAPE '!'";
            $params[] = ak_sar_like_value($filters['code']);
        }
        if ($filters['sponsor'] !== '') {
            $where[] = "s.full_name LIKE ? ESCAPE '!'";
            $params[] = ak_sar_like_value($filters['sponsor']);
        }
        if ($filters['child'] !== '') {
            $where[] = "fc.child_name LIKE ? ESCAPE '!'";
            $params[] = ak_sar_like_value($filters['child']);
        }
        if ($filters['supervisor_id'] > 0) {
            $where[] = "s.supervisor_id = ?";
            $params[] = $filters['supervisor_id'];
        }
    }

    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY s.full_name, fc.child_name, sp.id";
    // This query runs only after an explicit search or "show all arrears" action.
    // The caller paginates rendered sponsorships after calculating complete totals.
    $sponsorships = dbFetchAll($sql, $params);

    if ($role === 'supervisor' && $sponsorships) {
        $letterScope = dbFetchAll(
            "SELECT letter_id, gender FROM supervisor_letters WHERE supervisor_id = ?",
            [$userId]
        );
        $sponsorships = array_values(array_filter(
            $sponsorships,
            static function (array $sponsorship) use ($userId, $letterScope): bool {
                $sponsor = [
                    'id' => $sponsorship['sponsor_id'],
                    'supervisor_id' => $sponsorship['supervisor_id'],
                    'first_letter_id' => $sponsorship['first_letter_id'],
                    'gender' => $sponsorship['gender'],
                    'is_manual_override' => $sponsorship['is_manual_override'],
                    'sponsor_type' => $sponsorship['sponsor_type'],
                    'created_by' => $sponsorship['sponsor_created_by']
                ];
                return supervisorCanAccessSponsor($userId, $sponsor, $letterScope);
            }
        ));
    }

    if (!$sponsorships) {
        return ['sponsorships'=>[], 'allocations'=>[], 'pause_events'=>[], 'pending'=>[], 'limit_exceeded'=>false];
    }

    $ids = array_values(array_unique(array_map(static fn(array $r): int => (int)$r['id'], $sponsorships)));
    $marks = implode(',', array_fill(0, count($ids), '?'));

    $allocationRows = dbFetchAll(
        "SELECT a.sponsorship_id, a.covered_month,
                COUNT(DISTINCT NULLIF(a.commitment_amount,0)) AS commitment_versions,
                MIN(NULLIF(a.commitment_amount,0)) AS commitment_amount,
                SUM(a.allocated_amount) AS allocated_amount,
                SUM(a.unallocated_amount) AS unallocated_amount
         FROM sponsorship_payment_allocations a
         JOIN transactions t ON t.id = a.transaction_id
         WHERE a.sponsorship_id IN ($marks)
           AND t.status = 'posted' AND t.voided_at IS NULL
         GROUP BY a.sponsorship_id, a.covered_month",
        $ids
    );

    $unallocatedRows = dbFetchAll(
        "SELECT a.sponsorship_id, SUM(a.unallocated_amount) AS unallocated_amount
         FROM sponsorship_payment_allocations a
         JOIN transactions t ON t.id = a.transaction_id
         WHERE a.sponsorship_id IN ($marks)
           AND t.status = 'posted' AND t.voided_at IS NULL
         GROUP BY a.sponsorship_id",
        $ids
    );
    $unallocatedTotals = [];
    foreach ($unallocatedRows as $row) {
        $unallocatedTotals[(int)$row['sponsorship_id']] = round((float)$row['unallocated_amount'], 2);
    }
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
        'unallocated_totals'=>$unallocatedTotals,
        'pause_events'=>$pauseEvents,
        'pending'=>$pending,
        'limit_exceeded'=>false
    ];
}

function ak_sar_first_full_month(string $date): DateTimeImmutable
{
    $start = new DateTimeImmutable($date);
    $first = $start->modify('first day of this month');
    return $start->format('d') === '01' ? $first : $first->modify('+1 month');
}

function ak_sar_month_rows(array $sponsorship, array $allocations, array $pauseEvents, ?DateTimeImmutable $asOf = null): array
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
    $currentMonth = ($asOf ?? new DateTimeImmutable('today'))->modify('first day of this month');
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

function ak_sar_has_two_consecutive_unpaid_months(array $eligibleMonths): bool
{
    $streak = 0;
    foreach ($eligibleMonths as $month) {
        if ((float)$month['outstanding'] > 0.009) {
            $streak++;
            if ($streak >= 2) return true;
        } else {
            $streak = 0;
        }
    }
    return false;
}

function ak_sar_get_arrears(string $role, int $userId, ?array $filters = null): array
{
    $data = ak_sar_load_report_data($role, $userId, $filters);
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
        $threshold = ak_sar_has_two_consecutive_unpaid_months($months);
        $unpaid = array_values(array_filter($months, static fn(array $month): bool => $month['outstanding'] > 0.009));
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


/**
 * Complete arrears totals are calculated from every qualifying sponsorship,
 * before the page applies its display pagination or a card-specific view.
 */
function ak_sar_summarize_arrears(array $items): array
{
    $summary = [
        'sponsorship_count' => 0,
        'outstanding_total' => 0.00,
        'unpaid_month_count' => 0,
        'pending_request_count' => 0,
        'calculation_error_count' => 0
    ];

    foreach ($items as $item) {
        if (($item['error'] ?? null) !== null) {
            $summary['calculation_error_count']++;
            continue;
        }

        $summary['sponsorship_count']++;
        foreach (($item['months'] ?? []) as $month) {
            $summary['outstanding_total'] += (float)($month['outstanding'] ?? 0);
            $summary['unpaid_month_count']++;
            $summary['pending_request_count'] += count($month['pending'] ?? []);
        }
    }

    $summary['outstanding_total'] = round($summary['outstanding_total'], 2);
    return $summary;
}

/**
 * Calculate the role-wide dashboard totals without loading sponsor/child detail
 * rows or constructing the full report result. The eligibility and payment
 * rules deliberately reuse the same month calculator as the detailed report.
 */
function ak_sar_get_arrears_summary(string $role, int $userId): array
{
    if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) {
        throw new RuntimeException('ليس لديك صلاحية عرض تقارير المتأخرات أو الدفعات المقدمة.');
    }

    $sql = "SELECT sp.id, sp.sponsorship_code, sp.monthly_amount, sp.status,
                   sp.start_date, sp.end_date, sp.pause_start_date, sp.pause_end_date,
                   s.id AS sponsor_id, s.supervisor_id, s.first_letter_id, s.gender,
                   s.is_manual_override, s.sponsor_type, s.created_by AS sponsor_created_by
            FROM sponsorships sp
            JOIN sponsors s ON s.id = sp.sponsor_id";
    $params = [];
    if ($role === 'supervisor') {
        $sql .= " WHERE " . ak_sar_supervisor_scope_sql();
        $params = [$userId, $userId, $userId];
    }
    $sql .= " ORDER BY sp.id";
    $sponsorships = dbFetchAll($sql, $params);

    // Keep the authoritative permission helper as the final supervisor gate.
    if ($role === 'supervisor' && $sponsorships) {
        $letterScope = dbFetchAll(
            "SELECT letter_id, gender FROM supervisor_letters WHERE supervisor_id = ?",
            [$userId]
        );
        $sponsorships = array_values(array_filter(
            $sponsorships,
            static function (array $row) use ($userId, $letterScope): bool {
                return supervisorCanAccessSponsor($userId, [
                    'id' => $row['sponsor_id'],
                    'supervisor_id' => $row['supervisor_id'],
                    'first_letter_id' => $row['first_letter_id'],
                    'gender' => $row['gender'],
                    'is_manual_override' => $row['is_manual_override'],
                    'sponsor_type' => $row['sponsor_type'],
                    'created_by' => $row['sponsor_created_by']
                ], $letterScope);
            }
        ));
    }

    $summary = [
        'sponsorship_count' => 0,
        'outstanding_total' => 0.00,
        'unpaid_month_count' => 0,
        'pending_request_count' => 0,
        'calculation_error_count' => 0
    ];
    if (!$sponsorships) return $summary;

    $ids = array_values(array_unique(array_map(
        static fn(array $row): int => (int)$row['id'],
        $sponsorships
    )));
    $marks = implode(',', array_fill(0, count($ids), '?'));

    $allocationRows = dbFetchAll(
        "SELECT a.sponsorship_id, a.covered_month,
                COUNT(DISTINCT NULLIF(a.commitment_amount,0)) AS commitment_versions,
                MIN(NULLIF(a.commitment_amount,0)) AS commitment_amount,
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

    // Keep only the exact unpaid sponsorship/month pairs needed by the pending
    // counter. Pending/returned requests never reduce confirmed arrears.
    $unpaidMonthKeys = [];
    foreach ($sponsorships as $sponsorship) {
        $sid = (int)$sponsorship['id'];
        try {
            $months = ak_sar_month_rows(
                $sponsorship,
                $allocations[$sid] ?? [],
                $pauseEvents[$sid] ?? []
            );
        } catch (Throwable $e) {
            $summary['calculation_error_count']++;
            continue;
        }

        if (!ak_sar_has_two_consecutive_unpaid_months($months)) continue;
        $summary['sponsorship_count']++;
        foreach ($months as $month) {
            if ((float)$month['outstanding'] <= 0.009) continue;
            $summary['outstanding_total'] += (float)$month['outstanding'];
            $summary['unpaid_month_count']++;
            $unpaidMonthKeys[$sid][(string)$month['month']] = true;
        }
    }

    $summary['outstanding_total'] = round($summary['outstanding_total'], 2);
    if (!$unpaidMonthKeys) return $summary;

    $arrearsIds = array_keys($unpaidMonthKeys);
    $arrearsMarks = implode(',', array_fill(0, count($arrearsIds), '?'));
    $pendingRows = dbFetchAll(
        "SELECT sponsorship_id, payment_period
         FROM transactions
         WHERE sponsorship_id IN ($arrearsMarks)
           AND transaction_type = 'sponsorship_payment'
           AND status IN ('pending_fm_review','returned')
         UNION ALL
         SELECT sponsorship_id, payment_period
         FROM sponsor_payments
         WHERE sponsorship_id IN ($arrearsMarks)
           AND payment_type = 'monthly_sponsorship'
           AND status IN ('pending','returned')
           AND transaction_id IS NULL",
        array_merge($arrearsIds, $arrearsIds)
    );
    foreach ($pendingRows as $row) {
        try {
            $month = ak_sp_parse_payment_period((string)($row['payment_period'] ?? ''));
        } catch (Throwable $e) {
            continue;
        }
        $sid = (int)$row['sponsorship_id'];
        if ($month !== null && isset($unpaidMonthKeys[$sid][$month])) {
            $summary['pending_request_count']++;
        }
    }

    return $summary;
}

/**
 * The pending card is a presentation filter only. Pending/returned requests
 * never reduce the posted-allocation arrears calculation.
 */
function ak_sar_filter_arrears_view(array $items, string $view): array
{
    if ($view !== 'pending') return $items;

    $filtered = [];
    foreach ($items as $item) {
        if (($item['error'] ?? null) !== null) continue;
        $months = array_values(array_filter(
            $item['months'] ?? [],
            static fn(array $month): bool => !empty($month['pending'])
        ));
        if ($months) {
            $item['months'] = $months;
            $filtered[] = $item;
        }
    }
    return $filtered;
}

function ak_sar_get_advances(string $role, int $userId): array
{
    $data = ak_sar_load_report_data($role, $userId);
    $currentMonth = (new DateTimeImmutable('first day of this month'))->format('Y-m-01');
    $result = [];
    foreach ($data['sponsorships'] as $sponsorship) {
        $sid = (int)$sponsorship['id'];
        $future = [];
        $unallocated = (float)($data['unallocated_totals'][$sid] ?? 0.00);
        $seenFutureMonths = [];
        foreach ($data['allocations'][$sid] ?? [] as $month => $allocation) {
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
