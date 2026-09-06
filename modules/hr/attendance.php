<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();
$userRole = Session::getUserRole();

if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'hr_staff', 'admin'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$selectedDate = (string)($_GET['date'] ?? date('Y-m-d'));
$dateObject = DateTime::createFromFormat('Y-m-d', $selectedDate);
if (!$dateObject || $dateObject->format('Y-m-d') !== $selectedDate) {
    $selectedDate = date('Y-m-d');
}

$message = '';
$msgType = 'success';

function attendanceIsOnLeave(int $employeeId, string $date): bool
{
    $row = dbFetchOne(
        "SELECT status FROM attendance WHERE employee_id = ? AND date = ? LIMIT 1",
        [$employeeId, $date]
    );
    return $row !== null && ($row['status'] ?? '') === 'on_leave';
}

function attendanceRequireEligible(int $employeeId, string $date): void
{
    if ($employeeId <= 0) {
        throw new InvalidArgumentException('الموظف المحدد غير صالح.');
    }

    $employee = dbFetchOne(
        "SELECT id FROM employees WHERE id = ? AND status = 'active' LIMIT 1",
        [$employeeId]
    );

    if (!$employee) {
        throw new InvalidArgumentException('الموظف غير موجود أو غير نشط.');
    }

    if (attendanceIsOnLeave($employeeId, $date)) {
        throw new RuntimeException('الموظف في إجازة. يجب تنفيذ "عودة من الإجازة" أولاً قبل تسجيل أي إجراء حضور.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $ids = isset($_POST['employee_ids']) && is_array($_POST['employee_ids'])
        ? array_values(array_unique(array_filter(array_map('intval', $_POST['employee_ids']), static fn($id) => $id > 0)))
        : [];

    if (!$ids && $employeeId > 0) {
        $ids = [$employeeId];
    }

    try {
        if ($action === 'return_from_leave') {
            if ($employeeId <= 0) {
                throw new InvalidArgumentException('الموظف المحدد غير صالح.');
            }

            $leaveRecord = dbFetchOne(
                "SELECT id FROM attendance WHERE employee_id = ? AND date = ? AND status = 'on_leave' LIMIT 1",
                [$employeeId, $selectedDate]
            );

            if (!$leaveRecord) {
                throw new RuntimeException('لا يوجد سجل إجازة مفتوح لهذا الموظف في التاريخ المحدد.');
            }

            dbExecute(
                "UPDATE attendance
                 SET status = 'absent', check_in = NULL, check_out = NULL, work_mode = NULL,
                     notes = 'عودة من الإجازة - بانتظار تسجيل الحضور'
                 WHERE employee_id = ? AND date = ? AND status = 'on_leave'",
                [$employeeId, $selectedDate]
            );

            $message = 'تمت عودة الموظف من الإجازة. أصبح الآن مؤهلاً لتسجيل الحضور.';
        } elseif (in_array($action, ['check_in', 'check_out', 'mark_absent', 'mark_leave'], true)) {
            attendanceRequireEligible($employeeId, $selectedDate);

            if ($action === 'check_in') {
                $mode = (string)($_POST['work_mode'] ?? 'remote');
                if (!in_array($mode, ['remote', 'onsite', 'hybrid'], true)) {
                    $mode = 'remote';
                }
                dbExecute(
                    "INSERT INTO attendance (employee_id, date, check_in, work_mode, status, notes)
                     VALUES (?, ?, ?, ?, 'present', NULL)
                     ON DUPLICATE KEY UPDATE
                        check_in = VALUES(check_in),
                        work_mode = VALUES(work_mode),
                        status = 'present',
                        notes = NULL",
                    [$employeeId, $selectedDate, date('H:i:s'), $mode]
                );
                $message = t('hr.attendance_recorded');
            } elseif ($action === 'check_out') {
                dbExecute(
                    "UPDATE attendance SET check_out = ?, status = CASE WHEN status = 'absent' THEN 'present' ELSE status END
                     WHERE employee_id = ? AND date = ? AND status <> 'on_leave'",
                    [date('H:i:s'), $employeeId, $selectedDate]
                );
                $message = t('hr.checkout_recorded');
            } elseif ($action === 'mark_absent') {
                dbExecute(
                    "INSERT INTO attendance (employee_id, date, status)
                     VALUES (?, ?, 'absent')
                     ON DUPLICATE KEY UPDATE status = 'absent', check_in = NULL, check_out = NULL, work_mode = NULL",
                    [$employeeId, $selectedDate]
                );
                $message = t('hr.absence_recorded');
            } elseif ($action === 'mark_leave') {
                dbExecute(
                    "INSERT INTO attendance (employee_id, date, status, notes)
                     VALUES (?, ?, 'on_leave', 'إجازة يدوية')
                     ON DUPLICATE KEY UPDATE status = 'on_leave', check_in = NULL, check_out = NULL, work_mode = NULL, notes = 'إجازة يدوية'",
                    [$employeeId, $selectedDate]
                );
                $message = t('hr.attendance_leave_recorded');
            }
        } elseif (in_array($action, ['bulk_check_in', 'bulk_check_out', 'bulk_absent', 'bulk_leave'], true)) {
            if (!$ids) {
                throw new InvalidArgumentException('لم يتم تحديد أي موظف.');
            }

            foreach ($ids as $id) {
                attendanceRequireEligible($id, $selectedDate);
            }

            $mode = (string)($_POST['bulk_work_mode'] ?? 'remote');
            if (!in_array($mode, ['remote', 'onsite', 'hybrid'], true)) {
                $mode = 'remote';
            }

            foreach ($ids as $id) {
                if ($action === 'bulk_check_in') {
                    dbExecute(
                        "INSERT INTO attendance (employee_id, date, check_in, work_mode, status, notes)
                         VALUES (?, ?, ?, ?, 'present', NULL)
                         ON DUPLICATE KEY UPDATE check_in = VALUES(check_in), work_mode = VALUES(work_mode), status = 'present', notes = NULL",
                        [$id, $selectedDate, date('H:i:s'), $mode]
                    );
                } elseif ($action === 'bulk_check_out') {
                    dbExecute(
                        "UPDATE attendance SET check_out = ?, status = CASE WHEN status = 'absent' THEN 'present' ELSE status END
                         WHERE employee_id = ? AND date = ? AND status <> 'on_leave'",
                        [date('H:i:s'), $id, $selectedDate]
                    );
                } elseif ($action === 'bulk_absent') {
                    dbExecute(
                        "INSERT INTO attendance (employee_id, date, status)
                         VALUES (?, ?, 'absent')
                         ON DUPLICATE KEY UPDATE status = 'absent', check_in = NULL, check_out = NULL, work_mode = NULL",
                        [$id, $selectedDate]
                    );
                } elseif ($action === 'bulk_leave') {
                    dbExecute(
                        "INSERT INTO attendance (employee_id, date, status, notes)
                         VALUES (?, ?, 'on_leave', 'إجازة يدوية')
                         ON DUPLICATE KEY UPDATE status = 'on_leave', check_in = NULL, check_out = NULL, work_mode = NULL, notes = 'إجازة يدوية'",
                        [$id, $selectedDate]
                    );
                }
            }

            $message = 'تم تنفيذ الإجراء للموظفين المحددين بنجاح.';
        }
    } catch (Throwable $e) {
        $message = t('hr.attendance_error', ['message' => $e->getMessage()]);
        $msgType = 'error';
    }
}

$employees = dbFetchAll(
    "SELECT e.id, e.full_name, e.department_id, d.name_ar AS dept_name
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.status = 'active'
     ORDER BY e.full_name",
    []
);

$attendanceRecords = [];
foreach (dbFetchAll("SELECT * FROM attendance WHERE date = ?", [$selectedDate]) as $attendance) {
    $attendanceRecords[(int)$attendance['employee_id']] = $attendance;
}

$stats = ['present' => 0, 'absent' => 0, 'late' => 0, 'on_leave' => 0];
foreach ($attendanceRecords as $attendance) {
    $status = $attendance['status'] ?? '';
    if (isset($stats[$status])) {
        $stats[$status]++;
    }
}

$departments = [];
foreach ($employees as $employee) {
    if (!empty($employee['dept_name'])) {
        $departments[(int)$employee['department_id']] = $employee['dept_name'];
    }
}

$pageTitle = t('hr.attendance_title');
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.att4{--ink:#172033;--muted:#667085;--line:#e6e9ee;--soft:#f7f8fa;--brand:#1b4d8f;background:#f5f6f8;margin:-10px -12px 0;padding:20px}.att4-head{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:16px}.att4-title{font-size:1.35rem;font-weight:800;color:var(--ink);margin:0}.att4-sub{font-size:.76rem;color:var(--muted);margin-top:5px}.att4-date{display:flex;align-items:center;gap:5px}.att4-date a,.att4-date button{width:34px;height:34px;border:1px solid var(--line);background:#fff;border-radius:8px;color:#475467;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}.att4-date input{height:34px;width:145px;border:1px solid var(--line);border-radius:8px;background:#fff;text-align:center;font-size:.78rem;font-weight:700;color:#344054;padding:0 7px}.att4-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin-bottom:14px}.att4-kpi{background:#fff;border:1px solid var(--line);border-radius:10px;padding:10px 13px;display:flex;align-items:center;justify-content:space-between}.att4-label{font-size:.69rem;color:var(--muted)}.att4-num{font-size:1.12rem;font-weight:800;color:var(--ink);margin-top:2px}.att4-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#eef1f5;color:#667085;font-size:.82rem}.att4-kpi.p .att4-icon{background:#eaf7ef;color:#16804a}.att4-kpi.l .att4-icon{background:#fff5d7;color:#947000}.att4-kpi.a .att4-icon{background:#fdebed;color:#c73543}.att4-kpi.lv .att4-icon{background:#eaf5fb;color:#167395}.att4-panel{background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden}.att4-tools{padding:10px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:7px;flex-wrap:wrap}.att4-search{position:relative;flex:1;min-width:220px}.att4-search input{height:35px;width:100%;border:1px solid var(--line);border-radius:8px;padding:0 32px 0 10px;font-size:.77rem}.att4-search i{position:absolute;right:11px;top:10px;color:#98a2b3;font-size:.72rem}.att4-tools select{height:35px;border:1px solid var(--line);border-radius:8px;padding:0 8px;font-size:.75rem;color:#475467;background:#fff;min-width:135px}.att4-selection{display:none;align-items:center;gap:7px;padding:8px 10px;background:#f1f6fc;border-bottom:1px solid #dbe7f5}.att4-selection.show{display:flex}.att4-count{font-size:.74rem;font-weight:800;color:var(--brand);margin-right:auto}.att4-btn{height:30px;border:0;border-radius:7px;padding:0 9px;font-size:.69rem;font-weight:750;display:inline-flex;align-items:center;gap:5px;cursor:pointer}.att4-in{background:#dff3e7;color:#176b40}.att4-out{background:#fbe1e5;color:#a82e3b}.att4-leave{background:#fff0bd;color:#785d00}.att4-absent{background:#eceff3;color:#475467}.att4-mode{height:30px;border:1px solid #d7e0eb;border-radius:7px;background:#fff;font-size:.7rem;padding:0 7px}.att4-table{width:100%;border-collapse:separate;border-spacing:0}.att4-table th{height:38px;background:#fafbfc;border-bottom:1px solid var(--line);font-size:.66rem;font-weight:800;color:#667085;text-align:right;padding:0 12px;white-space:nowrap}.att4-table td{height:54px;border-bottom:1px solid #f0f1f3;padding:7px 12px;font-size:.75rem;color:#344054;vertical-align:middle}.att4-table tbody tr:hover{background:#fbfcfe}.att4-table tbody tr:last-child td{border-bottom:0}.att4-check{width:38px;text-align:center!important}.att4-check input{width:15px;height:15px;accent-color:var(--brand);cursor:pointer}.att4-check input:disabled{cursor:not-allowed;opacity:.45}.att4-person{display:flex;align-items:center;gap:8px;min-width:185px}.att4-avatar{width:31px;height:31px;border-radius:50%;background:#edf3fb;color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;flex:none}.att4-name{font-size:.76rem;font-weight:800;color:#273444}.att4-meta{font-size:.64rem;color:#98a2b3;margin-top:1px}.att4-dept{color:#667085;font-size:.71rem}.att4-time{font-variant-numeric:tabular-nums;font-weight:700;color:#344054}.att4-time.empty{font-weight:500;color:#adb5bd}.att4-mode-label{font-size:.66rem;font-weight:700;padding:3px 7px;background:#f1f3f5;border-radius:5px;color:#596579}.att4-status{display:inline-flex;align-items:center;gap:4px;border-radius:15px;padding:3px 8px;font-size:.66rem;font-weight:800}.att4-status.p{background:#eaf7ef;color:#167447}.att4-status.l{background:#fff5d7;color:#8a6700}.att4-status.a{background:#fdebed;color:#b42331}.att4-status.lv{background:#eaf5fb;color:#126c8e}.att4-status.e{background:#eef0f3;color:#667085}.att4-row-actions{display:flex;justify-content:flex-end;gap:5px;align-items:center}.att4-row-actions select{height:29px;border:1px solid var(--line);border-radius:6px;font-size:.67rem;padding:0 5px;max-width:95px}.att4-row-btn{height:29px;border:0;border-radius:6px;padding:0 8px;font-size:.67rem;font-weight:750;cursor:pointer}.att4-row-in{background:#eaf7ef;color:#167447}.att4-row-out{background:#fdebed;color:#b42331}.att4-row-leave{background:#fff4cf;color:#7b5c00}.att4-row-return{background:#eaf5fb;color:#126c8e}.att4-done{font-size:.67rem;color:#98a2b3;display:inline-flex;align-items:center;gap:4px}.att4-leave-note{font-size:.64rem;color:#126c8e;margin-top:2px}.att4-empty{text-align:center!important;padding:35px!important;color:#98a2b3}@media(max-width:950px){.att4{padding:16px}.att4-kpis{grid-template-columns:repeat(2,1fr)}.att4-table{min-width:930px}.att4-panel{overflow-x:auto}}@media(max-width:600px){.att4-head{align-items:stretch;flex-direction:column}.att4-kpis{grid-template-columns:1fr 1fr}}
</style>

<div class="att4">
    <div class="att4-head">
        <div>
            <h1 class="att4-title"><i class="fas fa-calendar-check me-2" style="color:#1b4d8f"></i><?php echo e(t('hr.attendance_title')); ?></h1>
            <div class="att4-sub">إدارة الحضور والانصراف يومياً مع حماية حالة الإجازة ومنع تسجيل أي حضور قبل العودة منها.</div>
        </div>
        <div class="att4-date">
            <a href="?date=<?php echo htmlspecialchars(date('Y-m-d', strtotime($selectedDate . ' -1 day'))); ?>" title="اليوم السابق"><i class="fas fa-chevron-right"></i></a>
            <form method="GET" class="m-0"><input type="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>" onchange="this.form.submit()"></form>
            <a href="?date=<?php echo htmlspecialchars(date('Y-m-d', strtotime($selectedDate . ' +1 day'))); ?>" title="اليوم التالي"><i class="fas fa-chevron-left"></i></a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $msgType === 'error' ? 'danger' : 'success'; ?> py-2 px-3 mb-3" style="border-radius:8px;font-size:.78rem"><?php echo e($message); ?><button type="button" class="btn-close"></button></div>
    <?php endif; ?>

    <div class="att4-kpis">
        <div class="att4-kpi p"><div><div class="att4-label"><?php echo e(t('hr.present')); ?></div><div class="att4-num"><?php echo $stats['present']; ?></div></div><div class="att4-icon"><i class="fas fa-check"></i></div></div>
        <div class="att4-kpi l"><div><div class="att4-label"><?php echo e(t('hr.late')); ?></div><div class="att4-num"><?php echo $stats['late']; ?></div></div><div class="att4-icon"><i class="fas fa-clock"></i></div></div>
        <div class="att4-kpi a"><div><div class="att4-label"><?php echo e(t('hr.absent')); ?></div><div class="att4-num"><?php echo $stats['absent']; ?></div></div><div class="att4-icon"><i class="fas fa-user-xmark"></i></div></div>
        <div class="att4-kpi lv"><div><div class="att4-label"><?php echo e(t('hr.on_leave')); ?></div><div class="att4-num"><?php echo $stats['on_leave']; ?></div></div><div class="att4-icon"><i class="fas fa-calendar-day"></i></div></div>
    </div>

    <div class="att4-panel">
        <div class="att4-tools">
            <div class="att4-search"><i class="fas fa-search"></i><input id="att4Search" type="search" placeholder="البحث باسم الموظف..." autocomplete="off"></div>
            <select id="att4Dept"><option value="">كل الأقسام</option><?php foreach ($departments as $department): ?><option value="<?php echo htmlspecialchars((string)$department); ?>"><?php echo htmlspecialchars((string)$department); ?></option><?php endforeach; ?></select>
            <select id="att4Status"><option value="">كل الحالات</option><option value="present"><?php echo e(t('hr.present')); ?></option><option value="late"><?php echo e(t('hr.late')); ?></option><option value="absent"><?php echo e(t('hr.absent')); ?></option><option value="on_leave"><?php echo e(t('hr.on_leave')); ?></option></select>
        </div>

        <form method="POST" id="att4BulkForm">
            <input type="hidden" name="selected_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
            <div class="att4-selection" id="att4Selection">
                <select name="bulk_work_mode" class="att4-mode"><option value="remote"><?php echo e(t('hr.work_mode_remote')); ?></option><option value="onsite"><?php echo e(t('hr.work_mode_onsite')); ?></option><option value="hybrid"><?php echo e(t('hr.work_mode_hybrid')); ?></option></select>
                <button type="submit" name="action" value="bulk_check_in" class="att4-btn att4-in"><i class="fas fa-right-to-bracket"></i> تسجيل حضور</button>
                <button type="submit" name="action" value="bulk_check_out" class="att4-btn att4-out"><i class="fas fa-right-from-bracket"></i> تسجيل انصراف</button>
                <button type="submit" name="action" value="bulk_leave" class="att4-btn att4-leave"><i class="fas fa-calendar-check"></i> إجازة</button>
                <button type="submit" name="action" value="bulk_absent" class="att4-btn att4-absent"><i class="fas fa-user-xmark"></i> غياب</button>
                <span class="att4-count" id="att4Count">0 محدد</span>
            </div>

            <div class="table-responsive">
                <table class="att4-table" id="att4Table">
                    <thead><tr><th class="att4-check"><input id="att4SelectAll" type="checkbox" title="تحديد جميع الموظفين المؤهلين"></th><th><?php echo e(t('hr.employee')); ?></th><th><?php echo e(t('groups.group')); ?></th><th><?php echo e(t('hr.check_in')); ?></th><th><?php echo e(t('hr.check_out')); ?></th><th>النمط</th><th><?php echo e(t('common.status')); ?></th><th class="text-end"><?php echo e(t('common.actions')); ?></th></tr></thead>
                    <tbody>
                    <?php if (empty($employees)): ?>
                        <tr><td colspan="8" class="att4-empty">لا يوجد موظفون نشطون.</td></tr>
                    <?php else: foreach ($employees as $employee):
                        $employeeId = (int)$employee['id'];
                        $attendance = $attendanceRecords[$employeeId] ?? null;
                        $status = (string)($attendance['status'] ?? 'absent');
                        $onLeave = $status === 'on_leave';
                        $workMode = (string)($attendance['work_mode'] ?? '');
                        $initial = mb_substr(trim((string)$employee['full_name']), 0, 1, 'UTF-8');
                        $statusClass = ['present'=>'p','late'=>'l','absent'=>'a','on_leave'=>'lv','half_day'=>'e'][$status] ?? 'e';
                        $statusIcon = ['present'=>'fa-check','late'=>'fa-clock','absent'=>'fa-user-xmark','on_leave'=>'fa-calendar-check','half_day'=>'fa-minus'][$status] ?? 'fa-minus';
                        $statusKey = ['present'=>'hr.present','late'=>'hr.late','absent'=>'hr.absent','on_leave'=>'hr.on_leave','half_day'=>'hr.half_day'][$status] ?? 'common.status';
                        $modeKey = ['remote'=>'hr.work_mode_remote','onsite'=>'hr.work_mode_onsite','hybrid'=>'hr.work_mode_hybrid'][$workMode] ?? '';
                    ?>
                        <tr data-name="<?php echo htmlspecialchars(mb_strtolower((string)$employee['full_name'], 'UTF-8')); ?>" data-dept="<?php echo htmlspecialchars((string)($employee['dept_name'] ?? '')); ?>" data-status="<?php echo htmlspecialchars($status); ?>" data-on-leave="<?php echo $onLeave ? '1' : '0'; ?>">
                            <td class="att4-check"><input class="att4-row-check" type="checkbox" name="employee_ids[]" value="<?php echo $employeeId; ?>" <?php echo $onLeave ? 'disabled title="الموظف في إجازة - يجب إعادته أولاً"' : ''; ?>></td>
                            <td><div class="att4-person"><div class="att4-avatar"><?php echo htmlspecialchars($initial); ?></div><div><div class="att4-name"><?php echo htmlspecialchars((string)$employee['full_name']); ?></div><div class="att4-meta">ID #<?php echo $employeeId; ?></div></div></div></td>
                            <td class="att4-dept"><?php echo htmlspecialchars((string)($employee['dept_name'] ?? '-')); ?></td>
                            <td><span class="att4-time <?php echo (!$attendance || !$attendance['check_in']) ? 'empty' : ''; ?>"><?php echo ($attendance && $attendance['check_in']) ? substr((string)$attendance['check_in'], 0, 5) : '--:--'; ?></span></td>
                            <td><span class="att4-time <?php echo (!$attendance || !$attendance['check_out']) ? 'empty' : ''; ?>"><?php echo ($attendance && $attendance['check_out']) ? substr((string)$attendance['check_out'], 0, 5) : '--:--'; ?></span></td>
                            <td><span class="att4-mode-label"><?php echo $modeKey ? e(t($modeKey)) : '—'; ?></span></td>
                            <td><span class="att4-status <?php echo $statusClass; ?>"><i class="fas <?php echo $statusIcon; ?>"></i><?php echo e(t($statusKey)); ?></span><?php if ($onLeave): ?><div class="att4-leave-note">يجب تنفيذ عودة من الإجازة أولاً</div><?php endif; ?></td>
                            <td class="text-end">
                                <div class="att4-row-actions">
                                    <?php if ($onLeave): ?>
                                        <form method="POST" class="m-0"><input type="hidden" name="action" value="return_from_leave"><input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>"><button class="att4-row-btn att4-row-return" type="submit" title="عودة من الإجازة"><i class="fas fa-person-walking-arrow-right me-1"></i>عودة من الإجازة</button></form>
                                    <?php elseif (!$attendance || !$attendance['check_in']): ?>
                                        <form method="POST" class="m-0"><input type="hidden" name="action" value="check_in"><input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>"><select name="work_mode"><option value="remote"><?php echo e(t('hr.work_mode_remote')); ?></option><option value="onsite"><?php echo e(t('hr.work_mode_onsite')); ?></option><option value="hybrid"><?php echo e(t('hr.work_mode_hybrid')); ?></option></select><button class="att4-row-btn att4-row-in" type="submit" title="تسجيل حضور"><i class="fas fa-right-to-bracket"></i></button></form>
                                    <?php elseif (!$attendance['check_out']): ?>
                                        <form method="POST" class="m-0"><input type="hidden" name="action" value="check_out"><input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>"><button class="att4-row-btn att4-row-out" type="submit" title="تسجيل انصراف"><i class="fas fa-right-from-bracket"></i></button></form>
                                    <?php else: ?>
                                        <span class="att4-done"><i class="fas fa-circle-check"></i><?php echo e(t('hr.complete')); ?></span>
                                    <?php endif; ?>
                                    <?php if (!$onLeave && (!$attendance || $status === 'absent')): ?><form method="POST" class="m-0"><input type="hidden" name="action" value="mark_leave"><input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>"><button class="att4-row-btn att4-row-leave" type="submit" title="تسجيل إجازة"><i class="fas fa-calendar-check"></i></button></form><?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('att4Table');
    const selectAll = document.getElementById('att4SelectAll');
    const selection = document.getElementById('att4Selection');
    const count = document.getElementById('att4Count');
    const form = document.getElementById('att4BulkForm');
    const search = document.getElementById('att4Search');
    const dept = document.getElementById('att4Dept');
    const status = document.getElementById('att4Status');
    if (!table || !selectAll || !form) return;

    const rows = () => Array.from(table.querySelectorAll('tbody tr[data-name]'));
    const visibleChecks = () => rows().filter(row => row.style.display !== 'none').map(row => row.querySelector('.att4-row-check')).filter(Boolean);
    const eligibleChecks = () => visibleChecks().filter(check => !check.disabled);

    function refreshSelection() {
        const eligible = eligibleChecks();
        const selected = eligible.filter(check => check.checked);
        count.textContent = selected.length + ' محدد';
        selection.classList.toggle('show', selected.length > 0);
        selectAll.checked = eligible.length > 0 && selected.length === eligible.length;
        selectAll.indeterminate = selected.length > 0 && selected.length < eligible.length;
    }

    function applyFilters() {
        const query = (search.value || '').trim().toLocaleLowerCase();
        rows().forEach(row => {
            const matches = (!query || row.dataset.name.includes(query)) && (!dept.value || row.dataset.dept === dept.value) && (!status.value || row.dataset.status === status.value);
            row.style.display = matches ? '' : 'none';
        });
        refreshSelection();
    }

    selectAll.addEventListener('change', function () {
        const shouldSelect = selectAll.checked;
        eligibleChecks().forEach(check => { check.checked = shouldSelect; });
        refreshSelection();
    });

    table.addEventListener('change', function (event) {
        if (event.target.classList.contains('att4-row-check')) refreshSelection();
    });

    search.addEventListener('input', applyFilters);
    dept.addEventListener('change', applyFilters);
    status.addEventListener('change', applyFilters);

    form.addEventListener('submit', function (event) {
        const submitter = event.submitter;
        if (!submitter || !/^bulk_/.test(submitter.value || '')) return;

        const selected = eligibleChecks().filter(check => check.checked);
        if (!selected.length) {
            event.preventDefault();
            if (window.Swal) Swal.fire({icon:'warning', title:'لم يتم تحديد موظفين', text:'يرجى تحديد موظف واحد على الأقل.', confirmButtonText:'حسناً'});
            else alert('يرجى تحديد موظف واحد على الأقل.');
            return;
        }

        /* The server is authoritative. This client-side check only keeps the UI coherent. */
        if (selected.some(check => check.closest('tr').dataset.onLeave === '1')) {
            event.preventDefault();
            if (window.Swal) Swal.fire({icon:'warning', title:'موظف في إجازة', text:'لا يمكن تسجيل الحضور لموظف في إجازة. يجب تنفيذ عودة من الإجازة أولاً.', confirmButtonText:'حسناً'});
            return;
        }
    });

    refreshSelection();
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>