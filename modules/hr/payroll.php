<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_employment.php';
require_once __DIR__ . '/lib_contract_salary.php';

Session::start();
$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'hr_staff', 'admin'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pdo = db();
$message = '';
$msg_type = 'success';
$selectedMonth = max(1, min(12, (int)($_GET['month'] ?? $_POST['month'] ?? date('n'))));
$selectedYear = max(2020, min(2100, (int)($_GET['year'] ?? $_POST['year'] ?? date('Y'))));
$periodStart = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
$periodEnd = date('Y-m-t', strtotime($periodStart));

function payrollEligibleEmployees(PDO $pdo, string $periodEnd): array
{
    return dbFetchAll(
        "SELECT e.id, e.employee_code, e.full_name, e.hire_date,
                s.basic_salary, s.salary_currency, s.pay_frequency,
                c.id AS contract_id, c.contract_number, c.contract_type
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
         LEFT JOIN hr_employee_contracts c ON c.id = s.contract_id
         WHERE e.hire_date IS NULL OR e.hire_date <= ?
         ORDER BY e.full_name ASC",
        [$periodEnd, $periodEnd, $periodEnd]
    );
}

function payrollStateAtDate(int $employeeId, string $periodEnd): ?array
{
    return dbFetchOne(
        "SELECT s.code, s.name_ar, s.name_en, s.category
         FROM hr_employee_state_history h
         JOIN hr_employment_states s ON s.id = h.employment_state_id
         WHERE h.employee_id = ?
           AND h.effective_from <= ?
           AND (h.effective_to IS NULL OR h.effective_to >= ?)
         ORDER BY h.effective_from DESC, h.id DESC
         LIMIT 1",
        [$employeeId, $periodEnd . ' 23:59:59', $periodEnd . ' 00:00:00']
    );
}

function payrollRefreshDraftBaseSalary(PDO $pdo, int $month, int $year): int
{
    $periodStart = sprintf('%04d-%02d-01', $year, $month);
    $periodEnd = date('Y-m-t', strtotime($periodStart));
    $drafts = dbFetchAll(
        "SELECT p.id, p.employee_id, p.allowances, p.overtime, p.deductions,
                s.basic_salary
         FROM payroll p
         JOIN hr_employee_salary_history s ON s.id = (
             SELECT s2.id
             FROM hr_employee_salary_history s2
             WHERE s2.employee_id = p.employee_id
               AND s2.effective_from <= ?
               AND (s2.effective_to IS NULL OR s2.effective_to >= ?)
             ORDER BY s2.effective_from DESC, s2.id DESC
             LIMIT 1
         )
         WHERE p.month = ? AND p.year = ? AND p.status = 'draft'",
        [$periodEnd, $periodEnd, $month, $year]
    );

    $updated = 0;
    $stmt = $pdo->prepare(
        "UPDATE payroll
         SET basic_salary = ?,
             net_salary = ?
         WHERE id = ? AND status = 'draft'"
    );
    foreach ($drafts as $row) {
        $basic = (float)$row['basic_salary'];
        $net = $basic + (float)$row['allowances'] + (float)$row['overtime'] - (float)$row['deductions'];
        if ($net < 0) $net = 0;
        $stmt->execute([$basic, $net, (int)$row['id']]);
        if ($stmt->rowCount() > 0) $updated++;
    }
    return $updated;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $month = max(1, min(12, (int)($_POST['month'] ?? $selectedMonth)));
        $year = max(2020, min(2100, (int)($_POST['year'] ?? $selectedYear)));
        $periodStart = sprintf('%04d-%02d-01', $year, $month);
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        $selectedMonth = $month;
        $selectedYear = $year;

        if ($action === 'generate') {
            $eligible = payrollEligibleEmployees($pdo, $periodEnd);
            $inserted = 0;
            $refreshed = 0;
            $skipped = 0;
            $noSalary = 0;
            $pdo->beginTransaction();
            try {
                $existsStmt = $pdo->prepare(
                    "SELECT id, status, allowances, overtime, deductions
                     FROM payroll WHERE employee_id = ? AND month = ? AND year = ? LIMIT 1"
                );
                $insertStmt = $pdo->prepare(
                    "INSERT INTO payroll
                        (employee_id, month, year, basic_salary, allowances, overtime, deductions, net_salary, status)
                     VALUES (?, ?, ?, ?, 0, 0, 0, ?, 'draft')"
                );
                $refreshStmt = $pdo->prepare(
                    "UPDATE payroll
                     SET basic_salary = ?, net_salary = ?
                     WHERE id = ? AND status = 'draft'"
                );

                foreach ($eligible as $candidate) {
                    $state = payrollStateAtDate((int)$candidate['id'], $periodEnd);
                    if (!$state || !in_array($state['category'], ['working'], true)) {
                        $skipped++;
                        continue;
                    }

                    $salary = (float)$candidate['basic_salary'];
                    if ($salary <= 0) {
                        $noSalary++;
                        continue;
                    }

                    $existsStmt->execute([(int)$candidate['id'], $month, $year]);
                    $existing = $existsStmt->fetch(PDO::FETCH_ASSOC);
                    if ($existing) {
                        if ($existing['status'] === 'draft') {
                            $net = $salary + (float)$existing['allowances'] + (float)$existing['overtime'] - (float)$existing['deductions'];
                            if ($net < 0) $net = 0;
                            $refreshStmt->execute([$salary, $net, (int)$existing['id']]);
                            $refreshed++;
                        } else {
                            // Approved/paid payroll is historical and must remain immutable.
                            $skipped++;
                        }
                        continue;
                    }

                    $insertStmt->execute([(int)$candidate['id'], $month, $year, $salary, $salary]);
                    $inserted++;
                }
                $pdo->commit();
                $message = "تم إنشاء {$inserted} مسير راتب" . ($refreshed ? " وتحديث {$refreshed} مسودة" : '') . " للفترة {$year}-" . str_pad((string)$month, 2, '0', STR_PAD_LEFT);
                if ($noSalary) $message .= " — تم تجاوز {$noSalary} موظفاً بدون راتب تاريخي صالح.";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        } elseif ($action === 'update_amounts') {
            $id = (int)($_POST['payroll_id'] ?? 0);
            $row = dbFetchOne("SELECT id, status, basic_salary FROM payroll WHERE id = ? LIMIT 1", [$id]);
            if (!$row) throw new RuntimeException('سجل الرواتب غير موجود.');
            if ($row['status'] !== 'draft') throw new RuntimeException('لا يمكن تعديل سجل بعد اعتماده.');

            $allowances = max(0, (float)($_POST['allowances'] ?? 0));
            $overtime = max(0, (float)($_POST['overtime'] ?? 0));
            $deductions = max(0, (float)($_POST['deductions'] ?? 0));
            $basic = (float)$row['basic_salary'];
            $net = $basic + $allowances + $overtime - $deductions;
            if ($net < 0) throw new RuntimeException('صافي الراتب لا يمكن أن يكون سالباً.');

            $pdo->prepare("UPDATE payroll SET allowances = ?, overtime = ?, deductions = ?, net_salary = ? WHERE id = ? AND status = 'draft'")
                ->execute([$allowances, $overtime, $deductions, $net, $id]);
            $message = 'تم تحديث مكونات مسير الراتب.';
        } elseif ($action === 'update_status') {
            $id = (int)($_POST['payroll_id'] ?? 0);
            $newStatus = $_POST['status'] ?? '';
            if (!in_array($newStatus, ['approved', 'paid'], true)) throw new RuntimeException('حالة الرواتب غير صالحة.');

            $row = dbFetchOne("SELECT id, status, basic_salary FROM payroll WHERE id = ? LIMIT 1", [$id]);
            if (!$row) throw new RuntimeException('سجل الرواتب غير موجود.');
            if ((float)$row['basic_salary'] <= 0) throw new RuntimeException('لا يمكن اعتماد أو صرف مسير بدون راتب أساسي صالح.');
            if ($newStatus === 'approved' && $row['status'] !== 'draft') throw new RuntimeException('لا يمكن اعتماد هذا السجل من حالته الحالية.');
            if ($newStatus === 'paid' && $row['status'] !== 'approved') throw new RuntimeException('يجب اعتماد مسير الراتب أولاً.');

            $paymentDate = $newStatus === 'paid' ? date('Y-m-d') : null;
            $pdo->prepare("UPDATE payroll SET status = ?, payment_date = ? WHERE id = ?")
                ->execute([$newStatus, $paymentDate, $id]);
            $message = $newStatus === 'approved' ? 'تم اعتماد مسير الراتب.' : 'تم تسجيل صرف مسير الراتب.';
        } elseif ($action === 'approve_all') {
            $invalid = dbFetchOne("SELECT COUNT(*) AS c FROM payroll WHERE month = ? AND year = ? AND status = 'draft' AND basic_salary <= 0", [$month, $year]);
            if ((int)($invalid['c'] ?? 0) > 0) throw new RuntimeException('يوجد مسودات بدون راتب أساسي صالح. حدّث الراتب التاريخي أولاً.');
            $stmt = $pdo->prepare("UPDATE payroll SET status = 'approved' WHERE month = ? AND year = ? AND status = 'draft'");
            $stmt->execute([$month, $year]);
            $message = 'تم اعتماد مسودات الرواتب للفترة المحددة.';
        }
    }

    // Draft payroll is allowed to follow the authoritative salary history until approval.
    if ($selectedYear >= 2020) payrollRefreshDraftBaseSalary($pdo, $selectedMonth, $selectedYear);
} catch (Throwable $e) {
    $message = 'خطأ: ' . $e->getMessage();
    $msg_type = 'error';
}

$payrolls = dbFetchAll(
    "SELECT p.*, e.employee_code, e.full_name AS emp_name,
            c.contract_number, c.contract_type,
            s.salary_currency, s.effective_from AS salary_effective_from
     FROM payroll p
     JOIN employees e ON p.employee_id = e.id
     LEFT JOIN hr_employee_salary_history s ON s.id = (
         SELECT s2.id
         FROM hr_employee_salary_history s2
         WHERE s2.employee_id = p.employee_id
           AND s2.effective_from <= LAST_DAY(STR_TO_DATE(CONCAT(p.year, '-', LPAD(p.month, 2, '0'), '-01'), '%Y-%m-%d'))
           AND (s2.effective_to IS NULL OR s2.effective_to >= LAST_DAY(STR_TO_DATE(CONCAT(p.year, '-', LPAD(p.month, 2, '0'), '-01'), '%Y-%m-%d')))
         ORDER BY s2.effective_from DESC, s2.id DESC
         LIMIT 1
     )
     LEFT JOIN hr_employee_contracts c ON c.id = s.contract_id
     WHERE p.month = ? AND p.year = ?
     ORDER BY e.full_name ASC, p.id ASC",
    [$selectedMonth, $selectedYear]
);

$summary = ['count' => count($payrolls), 'draft' => 0, 'approved' => 0, 'paid' => 0, 'net' => 0.0];
foreach ($payrolls as $p) {
    if (isset($summary[$p['status']])) $summary[$p['status']]++;
    $summary['net'] += (float)$p['net_salary'];
}

$monthNames = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
$pageTitle = 'إدارة الرواتب';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.pr-wrap{max-width:1500px;margin:auto}.pr-header{background:linear-gradient(135deg,#1b4d8f,#2c5aa0);color:#fff;padding:24px;border-radius:12px;margin-bottom:20px}.pr-header h1{margin:0;font-size:1.7rem}.pr-header p{margin:5px 0 0;opacity:.9}.pr-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:18px;overflow:hidden}.pr-head{padding:14px 18px;border-bottom:1px solid #e9ecef;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.pr-body{padding:18px}.pr-filter{display:flex;gap:8px;align-items:end;flex-wrap:wrap}.pr-filter label{font-size:.78rem;font-weight:700;color:#495057;display:block;margin-bottom:4px}.pr-filter select{min-width:130px}.pr-table{width:100%;border-collapse:collapse}.pr-table th,.pr-table td{padding:10px 11px;border-bottom:1px solid #edf0f2;text-align:right;vertical-align:middle;font-size:.86rem}.pr-table th{background:#f7f9fb;color:#1b4d8f;font-weight:800;white-space:nowrap}.pr-table tr:hover{background:#fafcff}.pr-btn{border:0;border-radius:7px;padding:6px 10px;font-size:.78rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}.pr-primary{background:#1b4d8f;color:#fff}.pr-success{background:#198754;color:#fff}.pr-outline{background:#fff;border:1px solid #ced4da;color:#344054}.pr-badge{display:inline-block;padding:4px 9px;border-radius:999px;font-size:.72rem;font-weight:800}.b-draft{background:#fff3cd;color:#856404}.b-approved{background:#dbeafe;color:#1d4ed8}.b-paid{background:#d1e7dd;color:#146c43}.pr-stat{border:1px solid #e8edf3;border-radius:10px;padding:13px 15px;background:#fff;min-width:145px}.pr-stat small{color:#6c757d}.pr-stat strong{display:block;font-size:1.25rem;margin-top:3px}.money-input{width:105px;padding:5px 7px;border:1px solid #ced4da;border-radius:6px;text-align:right}.employee-main{font-weight:800}.employee-code{display:block;color:#6c757d;font-size:.7rem;margin-top:2px}.salary-source{font-size:.72rem;color:#6c757d}.pr-note{background:#f5f8fc;border-right:3px solid #1b4d8f;padding:10px 13px;border-radius:6px;color:#495057;font-size:.82rem}
</style>
<div class="pr-wrap">
    <div class="pr-header"><h1><i class="fas fa-money-bill-wave me-2"></i> إدارة الرواتب</h1><p>مسير الرواتب مرتبط بسجل الراتب التاريخي وحالة الموظف الوظيفية.</p></div>
    <?php if ($message): ?><div class="alert alert-<?php echo $msg_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show"><i class="fas <?php echo $msg_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> me-2"></i><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="pr-card"><div class="pr-body"><form method="GET" class="pr-filter">
        <div><label>الشهر</label><select name="month" class="form-select form-select-sm"><?php foreach($monthNames as $m=>$name): ?><option value="<?php echo $m; ?>" <?php echo $m===$selectedMonth?'selected':''; ?>><?php echo $name; ?></option><?php endforeach; ?></select></div>
        <div><label>السنة</label><select name="year" class="form-select form-select-sm"><?php for($y=date('Y')-2;$y<=date('Y')+2;$y++): ?><option value="<?php echo $y; ?>" <?php echo $y===$selectedYear?'selected':''; ?>><?php echo $y; ?></option><?php endfor; ?></select></div>
        <button class="pr-btn pr-primary" type="submit"><i class="fas fa-filter me-1"></i> عرض الفترة</button>
    </form></div></div>

    <div class="d-flex gap-2 flex-wrap mb-3">
        <div class="pr-stat"><small>السجلات</small><strong><?php echo number_format($summary['count']); ?></strong></div>
        <div class="pr-stat"><small>مسودات</small><strong><?php echo number_format($summary['draft']); ?></strong></div>
        <div class="pr-stat"><small>معتمدة</small><strong><?php echo number_format($summary['approved']); ?></strong></div>
        <div class="pr-stat"><small>مصروفة</small><strong><?php echo number_format($summary['paid']); ?></strong></div>
        <div class="pr-stat"><small>إجمالي الصافي</small><strong><?php echo number_format($summary['net'],2); ?> <span style="font-size:.75rem"><?php echo htmlspecialchars(APP_CURRENCY_CODE); ?></span></strong></div>
    </div>

    <div class="pr-card"><div class="pr-head">
        <div><strong><i class="fas fa-calendar-alt me-1 text-primary"></i> <?php echo $monthNames[$selectedMonth] . ' ' . $selectedYear; ?></strong><div class="text-muted" style="font-size:.75rem">الراتب التاريخي المحتسب حتى <?php echo htmlspecialchars($periodEnd); ?></div></div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST"><input type="hidden" name="action" value="generate"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"><button class="pr-btn pr-primary" type="submit"><i class="fas fa-sync-alt me-1"></i> إنشاء المسير</button></form>
            <?php if($summary['draft']>0): ?><form method="POST"><input type="hidden" name="action" value="approve_all"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"><button class="pr-btn pr-success" type="submit"><i class="fas fa-check-double me-1"></i> اعتماد المسودات</button></form><?php endif; ?>
        </div>
    </div><div class="pr-body">
        <div class="pr-note mb-3"><i class="fas fa-info-circle me-1"></i> يتم اختيار آخر راتب تاريخي صالح لنهاية الشهر. المسودات فقط يمكن أن تتبع تغييرات الراتب؛ السجلات المعتمدة والمصروفة تظل تاريخية وغير قابلة للتغيير. العملة النظامية: <strong><?php echo htmlspecialchars(APP_CURRENCY_NAME_AR . ' (' . APP_CURRENCY_CODE . ')'); ?></strong>.</div>
        <div class="table-responsive"><table class="pr-table"><thead><tr><th>الموظف</th><th>العقد</th><th>الراتب الأساسي</th><th>البدلات</th><th>الإضافي</th><th>الخصومات</th><th>الصافي</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>
        <?php if(empty($payrolls)): ?><tr><td colspan="9" class="text-center py-5 text-muted"><i class="fas fa-file-invoice-dollar fa-2x mb-2 d-block"></i>لا توجد سجلات لهذه الفترة. اضغط «إنشاء المسير».</td></tr>
        <?php else: foreach($payrolls as $p): ?>
            <tr>
                <td><span class="employee-main"><?php echo htmlspecialchars($p['emp_name']); ?></span><span class="employee-code"><?php echo htmlspecialchars($p['employee_code']??''); ?></span></td>
                <td><?php echo htmlspecialchars($p['contract_number'] ?: '—'); ?><span class="salary-source"><?php echo htmlspecialchars($p['contract_type'] ?: ''); ?></span></td>
                <td><strong><?php echo number_format((float)$p['basic_salary'],2); ?></strong><span class="salary-source"><?php echo htmlspecialchars(APP_CURRENCY_CODE); ?> · <?php echo htmlspecialchars($p['salary_effective_from'] ?: ''); ?></span></td>
                <?php if($p['status']==='draft'): ?>
                <form method="POST"><input type="hidden" name="action" value="update_amounts"><input type="hidden" name="payroll_id" value="<?php echo $p['id']; ?>"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                    <td><input class="money-input" type="number" min="0" step="0.01" name="allowances" value="<?php echo htmlspecialchars((string)$p['allowances']); ?>"></td><td><input class="money-input" type="number" min="0" step="0.01" name="overtime" value="<?php echo htmlspecialchars((string)$p['overtime']); ?>"></td><td><input class="money-input" type="number" min="0" step="0.01" name="deductions" value="<?php echo htmlspecialchars((string)$p['deductions']); ?>"></td><td><strong><?php echo number_format((float)$p['net_salary'],2); ?></strong><span class="salary-source"><?php echo htmlspecialchars(APP_CURRENCY_CODE); ?></span><button class="pr-btn pr-outline" type="submit" style="display:block;margin-top:5px">حفظ المكونات</button></td>
                </form>
                <?php else: ?><td>+<?php echo number_format((float)$p['allowances'],2); ?></td><td>+<?php echo number_format((float)$p['overtime'],2); ?></td><td>-<?php echo number_format((float)$p['deductions'],2); ?></td><td><strong><?php echo number_format((float)$p['net_salary'],2); ?></strong><span class="salary-source"><?php echo htmlspecialchars(APP_CURRENCY_CODE); ?></span></td><?php endif; ?>
                <td><?php $map=['draft'=>['b-draft','مسودة'],'approved'=>['b-approved','معتمد'],'paid'=>['b-paid','مصروف']]; $st=$map[$p['status']]??['b-draft',$p['status']]; ?><span class="pr-badge <?php echo $st[0]; ?>"><?php echo $st[1]; ?></span></td>
                <td class="text-nowrap">
                    <?php if($p['status']==='draft'): ?><form method="POST" style="display:inline"><input type="hidden" name="action" value="update_status"><input type="hidden" name="payroll_id" value="<?php echo $p['id']; ?>"><input type="hidden" name="status" value="approved"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"><button class="pr-btn pr-primary" type="submit">اعتماد</button></form>
                    <?php elseif($p['status']==='approved'): ?><form method="POST" style="display:inline"><input type="hidden" name="action" value="update_status"><input type="hidden" name="payroll_id" value="<?php echo $p['id']; ?>"><input type="hidden" name="status" value="paid"><input type="hidden" name="month" value="<?php echo $selectedMonth; ?>"><input type="hidden" name="year" value="<?php echo $selectedYear; ?>"><button class="pr-btn pr-success" type="submit">تسجيل الصرف</button></form><?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
    </div></div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
