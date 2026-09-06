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

function attendanceApprovedLeave(int $employeeId, string $date): ?array
{
    return dbFetchOne(
        "SELECT id, employee_id, start_date, end_date, leave_type, status
         FROM leaves
         WHERE employee_id = ?
           AND status = 'hr_approved'
           AND start_date <= ?
           AND end_date >= ?
         ORDER BY start_date DESC, id DESC
         LIMIT 1",
        [$employeeId, $date, $date]
    );
}

function attendanceHasReturnOverride(int $employeeId, string $date): bool
{
    return dbFetchOne(
        "SELECT id FROM attendance
         WHERE employee_id = ? AND date = ?
           AND status = 'absent'
           AND notes LIKE 'عودة من الإجازة%'
         LIMIT 1",
        [$employeeId, $date]
    ) !== null;
}

function attendanceIsOnLeave(int $employeeId, string $date): bool
{
    $leave = attendanceApprovedLeave($employeeId, $date);
    return $leave !== null && !attendanceHasReturnOverride($employeeId, $date);
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
        throw new RuntimeException('الموظف في إجازة معتمدة. يجب تنفيذ "عودة من الإجازة" أولاً قبل تسجيل أي إجراء حضور.');
    }
}

function attendanceReturnFromLeave(int $employeeId, string $date): void
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

    $leave = attendanceApprovedLeave($employeeId, $date);
    if (!$leave) {
        throw new RuntimeException('لا توجد إجازة معتمدة لهذا الموظف في التاريخ المحدد.');
    }

    if (attendanceHasReturnOverride($employeeId, $date)) {
        throw new RuntimeException('تم تسجيل عودة الموظف من الإجازة مسبقاً لهذا التاريخ.');
    }

    dbExecute(
        "INSERT INTO attendance (employee_id, date, status, notes)
         VALUES (?, ?, 'absent', 'عودة من الإجازة - بانتظار تسجيل الحضور')
         ON DUPLICATE KEY UPDATE
            status = 'absent', check_in = NULL, check_out = NULL,
            work_mode = NULL, notes = 'عودة من الإجازة - بانتظار تسجيل الحضور'",
        [$employeeId, $date]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $employeeId = (int)($_POST['employee_id'] ?? 0);

    try {
        if ($action === 'return_from_leave') {
            attendanceReturnFromLeave($employeeId, $selectedDate);
            $message = 'تم تسجيل عودة الموظف من الإجازة. أصبح الآن مؤهلاً لتسجيل الحضور.';
        } elseif (in_array($action, ['check_in', 'check_out', 'mark_absent', 'mark_leave'], true)) {
            attendanceRequireEligible($employeeId, $selectedDate);

            if ($action === 'check_in') {
                $mode = (string)($_POST['work_mode'] ?? 'remote');
                if (!in_array($mode, ['remote', 'onsite', 'hybrid'], true)) $mode = 'remote';
                dbExecute(
                    "INSERT INTO attendance (employee_id, date, check_in, work_mode, status, notes)
                     VALUES (?, ?, ?, ?, 'present', NULL)
                     ON DUPLICATE KEY UPDATE check_in = VALUES(check_in), work_mode = VALUES(work_mode), status = 'present', notes = NULL",
                    [$employeeId, $selectedDate, date('H:i:s'), $mode]
                );
                $message = t('hr.attendance_recorded');
            } elseif ($action === 'check_out') {
                $record = dbFetchOne(
                    "SELECT id FROM attendance WHERE employee_id = ? AND date = ? AND status <> 'on_leave' LIMIT 1",
                    [$employeeId, $selectedDate]
                );
                if (!$record) throw new RuntimeException('لا يمكن تسجيل الانصراف قبل وجود سجل حضور لهذا اليوم.');
                dbExecute(
                    "UPDATE attendance SET check_out = ?, status = CASE WHEN status = 'absent' THEN 'present' ELSE status END
                     WHERE employee_id = ? AND date = ? AND status <> 'on_leave'",
                    [date('H:i:s'), $employeeId, $selectedDate]
                );
                $message = t('hr.checkout_recorded');
            } elseif ($action === 'mark_absent') {
                dbExecute(
                    "INSERT INTO attendance (employee_id, date, status) VALUES (?, ?, 'absent')
                     ON DUPLICATE KEY UPDATE status = 'absent', check_in = NULL, check_out = NULL, work_mode = NULL, notes = NULL",
                    [$employeeId, $selectedDate]
                );
                $message = t('hr.absence_recorded');
            } else {
                dbExecute(
                    "INSERT INTO attendance (employee_id, date, status, notes) VALUES (?, ?, 'on_leave', 'إجازة يدوية')
                     ON DUPLICATE KEY UPDATE status = 'on_leave', check_in = NULL, check_out = NULL, work_mode = NULL, notes = 'إجازة يدوية'",
                    [$employeeId, $selectedDate]
                );
                $message = t('hr.attendance_leave_recorded');
            }
        }
    } catch (Throwable $e) {
        $message = t('hr.attendance_error', ['message' => $e->getMessage()]);
        $msgType = 'error';
    }
}

$employees = dbFetchAll(
    "SELECT e.id, e.full_name, e.department_id, d.name_ar AS dept_name
     FROM employees e LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.status = 'active' ORDER BY e.full_name",
    []
);

$attendanceRecords = [];
foreach (dbFetchAll("SELECT * FROM attendance WHERE date = ?", [$selectedDate]) as $att) {
    $attendanceRecords[(int)$att['employee_id']] = $att;
}

$rows = [];
$stats = ['present' => 0, 'absent' => 0, 'late' => 0, 'on_leave' => 0];
$departments = [];

foreach ($employees as $employee) {
    $id = (int)$employee['id'];
    $att = $attendanceRecords[$id] ?? null;
    $approvedLeave = attendanceApprovedLeave($id, $selectedDate);
    $returned = $approvedLeave ? attendanceHasReturnOverride($id, $selectedDate) : false;

    // The effective status is authoritative for the UI. This is important:
    // even if an old attendance row says on_leave, or an approved leave is
    // detected independently, both paths must produce the same disabled state.
    $onLeave = $approvedLeave !== null && !$returned;
    $status = $onLeave ? 'on_leave' : (string)($att['status'] ?? 'absent');
    if ($status === 'on_leave' && !$onLeave) {
        $status = $returned ? 'absent' : 'absent';
    }

    if (isset($stats[$status])) $stats[$status]++;
    if (!empty($employee['dept_name'])) $departments[(int)$employee['department_id']] = $employee['dept_name'];

    $rows[] = [
        'employee' => $employee,
        'attendance' => $att,
        'leave' => $approvedLeave,
        'returned' => $returned,
        'on_leave' => $onLeave,
        'status' => $status,
    ];
}

$pageTitle = t('hr.attendance_title');
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.att5{--ink:#172033;--muted:#667085;--line:#e6e9ee;--brand:#1b4d8f;background:#f5f6f8;margin:-10px -12px 0;padding:20px}.att5-head{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:16px}.att5-title{font-size:1.35rem;font-weight:800;color:var(--ink);margin:0}.att5-sub{font-size:.76rem;color:var(--muted);margin-top:5px}.att5-date{display:flex;align-items:center;gap:5px}.att5-date a{width:34px;height:34px;border:1px solid var(--line);background:#fff;border-radius:8px;color:#475467;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}.att5-date input{height:34px;width:145px;border:1px solid var(--line);border-radius:8px;background:#fff;text-align:center;font-size:.78rem;font-weight:700;color:#344054;padding:0 7px}.att5-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin-bottom:14px}.att5-kpi{background:#fff;border:1px solid var(--line);border-radius:10px;padding:10px 13px;display:flex;align-items:center;justify-content:space-between}.att5-label{font-size:.69rem;color:var(--muted)}.att5-num{font-size:1.12rem;font-weight:800;color:var(--ink);margin-top:2px}.att5-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#eef1f5;color:#667085;font-size:.82rem}.att5-kpi.p .att5-icon{background:#eaf7ef;color:#16804a}.att5-kpi.l .att5-icon{background:#fff5d7;color:#947000}.att5-kpi.a .att5-icon{background:#fdebed;color:#c73543}.att5-kpi.lv .att5-icon{background:#eaf5fb;color:#167395}.att5-panel{background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden}.att5-tools{padding:10px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:7px;flex-wrap:wrap}.att5-search{position:relative;flex:1;min-width:220px}.att5-search input{height:35px;width:100%;border:1px solid var(--line);border-radius:8px;padding:0 32px 0 10px;font-size:.77rem}.att5-search i{position:absolute;right:11px;top:10px;color:#98a2b3;font-size:.72rem}.att5-tools select,.att5-mode{height:35px;border:1px solid var(--line);border-radius:8px;padding:0 8px;font-size:.75rem;color:#475467;background:#fff;min-width:135px}.att5-selection{display:none;align-items:center;gap:7px;padding:8px 10px;background:#f1f6fc;border-bottom:1px solid #dbe7f5}.att5-selection.show{display:flex}.att5-count{font-size:.74rem;font-weight:800;color:var(--brand);margin-right:auto}.att5-btn,.att5-row-btn{height:30px;border:0;border-radius:7px;padding:0 9px;font-size:.69rem;font-weight:750;display:inline-flex;align-items:center;gap:5px;cursor:pointer}.att5-in{background:#dff3e7;color:#176b40}.att5-out{background:#fbe1e5;color:#a82e3b}.att5-leave{background:#fff0bd;color:#785d00}.att5-absent{background:#eceff3;color:#475467}.att5-table{width:100%;border-collapse:separate;border-spacing:0}.att5-table th{height:38px;background:#fafbfc;border-bottom:1px solid var(--line);font-size:.66rem;font-weight:800;color:#667085;text-align:right;padding:0 12px;white-space:nowrap}.att5-table td{height:54px;border-bottom:1px solid #f0f1f3;padding:7px 12px;font-size:.75rem;color:#344054;vertical-align:middle}.att5-table tbody tr:hover{background:#fbfcfe}.att5-check{width:38px;text-align:center!important}.att5-check input{width:15px;height:15px;accent-color:var(--brand);cursor:pointer}.att5-check input:disabled{cursor:not-allowed;opacity:.45}.att5-person{display:flex;align-items:center;gap:8px;min-width:185px}.att5-avatar{width:31px;height:31px;border-radius:50%;background:#edf3fb;color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;flex:none}.att5-name{font-size:.76rem;font-weight:800;color:#273444}.att5-meta{font-size:.64rem;color:#98a2b3;margin-top:1px}.att5-dept{color:#667085;font-size:.71rem}.att5-time{font-variant-numeric:tabular-nums;font-weight:700;color:#344054}.att5-time.empty{font-weight:500;color:#adb5bd}.att5-mode-label{font-size:.66rem;font-weight:700;padding:3px 7px;background:#f1f3f5;border-radius:5px;color:#596579}.att5-status{display:inline-flex;align-items:center;gap:4px;border-radius:15px;padding:3px 8px;font-size:.66rem;font-weight:800}.att5-status.p{background:#eaf7ef;color:#167447}.att5-status.l{background:#fff5d7;color:#8a6700}.att5-status.a{background:#fdebed;color:#b42331}.att5-status.lv{background:#eaf5fb;color:#126c8e}.att5-row-actions{display:flex;justify-content:flex-end;gap:5px;align-items:center}.att5-row-actions select{height:29px;border:1px solid var(--line);border-radius:6px;font-size:.67rem;padding:0 5px;max-width:95px}.att5-row-in{background:#eaf7ef;color:#167447}.att5-row-out{background:#fdebed;color:#b42331}.att5-row-leave{background:#fff4cf;color:#7b5c00}.att5-row-return{background:#eaf5fb;color:#126c8e}.att5-leave-note{font-size:.64rem;color:#126c8e;margin-top:2px}.att5-empty{text-align:center!important;padding:35px!important;color:#98a2b3}@media(max-width:950px){.att5{padding:16px}.att5-kpis{grid-template-columns:repeat(2,1fr)}.att5-table{min-width:930px}.att5-panel{overflow-x:auto}}@media(max-width:600px){.att5-head{align-items:stretch;flex-direction:column}.att5-kpis{grid-template-columns:1fr 1fr}}
/* Leave rows are visually and interactively locked. */
.att5-table tr.att5-on-leave{background:#f8fbfd}.att5-table tr.att5-on-leave .att5-employee{pointer-events:none}.att5-table tr.att5-on-leave td{color:#667085}.att5-table tr.att5-on-leave:hover{background:#f8fbfd}
</style>
<div class="att5">
<div class="att5-head"><div><h1 class="att5-title"><i class="fas fa-calendar-check me-2" style="color:#1b4d8f"></i><?php echo e(t('hr.attendance_title')); ?></h1><div class="att5-sub">الإجازة المعتمدة هي المصدر الأساسي لحماية الموظف. لا يمكن تسجيل الحضور إلا بعد تنفيذ «عودة من الإجازة».</div></div><div class="att5-date"><a href="?date=<?php echo htmlspecialchars(date('Y-m-d',strtotime($selectedDate.' -1 day'))); ?>" title="اليوم السابق"><i class="fas fa-chevron-right"></i></a><form method="GET" class="m-0"><input type="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>" onchange="this.form.submit()"></form><a href="?date=<?php echo htmlspecialchars(date('Y-m-d',strtotime($selectedDate.' +1 day'))); ?>" title="اليوم التالي"><i class="fas fa-chevron-left"></i></a></div></div>
<?php if($message): ?><div class="alert alert-<?php echo $msgType==='error'?'danger':'success'; ?> py-2 px-3 mb-3" style="border-radius:8px;font-size:.78rem"><?php echo e($message); ?><button type="button" class="btn-close"></button></div><?php endif; ?>
<div class="att5-kpis"><div class="att5-kpi p"><div><div class="att5-label"><?php echo e(t('hr.present')); ?></div><div class="att5-num"><?php echo $stats['present']; ?></div></div><div class="att5-icon"><i class="fas fa-check"></i></div></div><div class="att5-kpi l"><div><div class="att5-label"><?php echo e(t('hr.late')); ?></div><div class="att5-num"><?php echo $stats['late']; ?></div></div><div class="att5-icon"><i class="fas fa-clock"></i></div></div><div class="att5-kpi a"><div><div class="att5-label"><?php echo e(t('hr.absent')); ?></div><div class="att5-num"><?php echo $stats['absent']; ?></div></div><div class="att5-icon"><i class="fas fa-user-xmark"></i></div></div><div class="att5-kpi lv"><div><div class="att5-label"><?php echo e(t('hr.on_leave')); ?></div><div class="att5-num"><?php echo $stats['on_leave']; ?></div></div><div class="att5-icon"><i class="fas fa-calendar-day"></i></div></div></div>
<div class="att5-panel">
<div class="att5-tools"><div class="att5-search"><i class="fas fa-search"></i><input id="att5Search" type="search" placeholder="البحث باسم الموظف..." autocomplete="off"></div><select id="att5Dept"><option value="">كل الأقسام</option><?php foreach($departments as $department): ?><option value="<?php echo htmlspecialchars((string)$department); ?>"><?php echo htmlspecialchars((string)$department); ?></option><?php endforeach; ?></select><select id="att5Status"><option value="">كل الحالات</option><option value="present"><?php echo e(t('hr.present')); ?></option><option value="late"><?php echo e(t('hr.late')); ?></option><option value="absent"><?php echo e(t('hr.absent')); ?></option><option value="on_leave"><?php echo e(t('hr.on_leave')); ?></option></select></div>
<div class="att5-selection" id="att5Selection"><select id="att5BulkMode" class="att5-mode"><option value="remote">عن بُعد</option><option value="onsite">من المكتب</option><option value="hybrid">هجين</option></select><span class="att5-count" id="att5Count">0 موظف محدد</span><button type="button" class="att5-btn att5-in" data-bulk-action="bulk_check_in"><i class="fas fa-right-to-bracket"></i> حضور</button><button type="button" class="att5-btn att5-out" data-bulk-action="bulk_check_out"><i class="fas fa-right-from-bracket"></i> انصراف</button><button type="button" class="att5-btn att5-absent" data-bulk-action="bulk_absent"><i class="fas fa-user-xmark"></i> غياب</button><button type="button" class="att5-btn att5-leave" data-bulk-action="bulk_leave"><i class="fas fa-calendar-day"></i> إجازة يدوية</button></div>
<table class="att5-table"><thead><tr><th class="att5-check"><input type="checkbox" id="att5SelectAll" title="تحديد الموظفين المؤهلين"></th><th>الموظف</th><th>القسم</th><th>الحالة</th><th>الحضور</th><th>الانصراف</th><th>النمط</th><th>الإجراء</th></tr></thead><tbody id="att5Body">
<?php if(!$rows): ?><tr><td colspan="8" class="att5-empty">لا يوجد موظفون نشطون.</td></tr><?php endif; ?>
<?php foreach($rows as $row): $employee=$row['employee']; $att=$row['attendance']; $status=$row['status']; $onLeave=($status==='on_leave'); $returned=$row['returned']; $initials=mb_substr((string)$employee['full_name'],0,1,'UTF-8'); $searchText=mb_strtolower((string)$employee['full_name'].' '.(string)($employee['dept_name']??''),'UTF-8'); ?>
<tr class="<?php echo $onLeave?'att5-on-leave':''; ?>" data-name="<?php echo htmlspecialchars($searchText); ?>" data-dept="<?php echo htmlspecialchars((string)($employee['dept_name']??'')); ?>" data-status="<?php echo htmlspecialchars($status); ?>" data-eligible="<?php echo $onLeave?'0':'1'; ?>">
<td class="att5-check"><input type="checkbox" class="att5-employee" value="<?php echo (int)$employee['id']; ?>" <?php echo $onLeave?'disabled aria-disabled="true" title="الموظف في إجازة معتمدة"':''; ?>></td>
<td><div class="att5-person"><div class="att5-avatar"><?php echo e($initials); ?></div><div><div class="att5-name"><?php echo e($employee['full_name']); ?></div><?php if($onLeave && $row['leave']): ?><div class="att5-leave-note">إجازة معتمدة: <?php echo e($row['leave']['start_date']); ?> → <?php echo e($row['leave']['end_date']); ?></div><?php elseif($returned): ?><div class="att5-meta">تمت العودة من الإجازة لهذا اليوم</div><?php endif; ?></div></div></td>
<td class="att5-dept"><?php echo e($employee['dept_name'] ?? '—'); ?></td>
<td><?php if($status==='present'): ?><span class="att5-status p"><i class="fas fa-check"></i><?php echo e(t('hr.present')); ?></span><?php elseif($status==='late'): ?><span class="att5-status l"><i class="fas fa-clock"></i><?php echo e(t('hr.late')); ?></span><?php elseif($status==='on_leave'): ?><span class="att5-status lv"><i class="fas fa-calendar-day"></i><?php echo e(t('hr.on_leave')); ?></span><?php else: ?><span class="att5-status a"><i class="fas fa-user-xmark"></i><?php echo e(t('hr.absent')); ?></span><?php endif; ?></td>
<td class="att5-time <?php echo empty($att['check_in'])?'empty':''; ?>"><?php echo e($att['check_in'] ?? '—'); ?></td><td class="att5-time <?php echo empty($att['check_out'])?'empty':''; ?>"><?php echo e($att['check_out'] ?? '—'); ?></td><td><?php echo !empty($att['work_mode']) ? '<span class="att5-mode-label">'.e($att['work_mode']).'</span>' : '<span class="att5-time empty">—</span>'; ?></td>
<td><div class="att5-row-actions">
<?php if($onLeave): ?><form method="POST" class="att5-return-form m-0"><input type="hidden" name="action" value="return_from_leave"><input type="hidden" name="employee_id" value="<?php echo (int)$employee['id']; ?>"><button type="submit" class="att5-row-btn att5-row-return"><i class="fas fa-person-walking-arrow-right"></i> عودة من الإجازة</button></form>
<?php else: ?><form method="POST" class="m-0"><input type="hidden" name="action" value="check_in"><input type="hidden" name="employee_id" value="<?php echo (int)$employee['id']; ?>"><select name="work_mode" title="نمط العمل"><option value="remote">عن بُعد</option><option value="onsite">مكتب</option><option value="hybrid">هجين</option></select><button type="submit" class="att5-row-btn att5-row-in" title="تسجيل الحضور"><i class="fas fa-right-to-bracket"></i></button></form><form method="POST" class="m-0"><input type="hidden" name="action" value="check_out"><input type="hidden" name="employee_id" value="<?php echo (int)$employee['id']; ?>"><button type="submit" class="att5-row-btn att5-row-out" title="تسجيل الانصراف"><i class="fas fa-right-from-bracket"></i></button></form><form method="POST" class="m-0"><input type="hidden" name="action" value="mark_absent"><input type="hidden" name="employee_id" value="<?php echo (int)$employee['id']; ?>"><button type="submit" class="att5-row-btn att5-row-leave" title="تسجيل الغياب"><i class="fas fa-user-xmark"></i></button></form><?php endif; ?>
</div></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const body=document.getElementById('att5Body');
    const all=document.getElementById('att5SelectAll');
    const selection=document.getElementById('att5Selection');
    const count=document.getElementById('att5Count');
    const search=document.getElementById('att5Search');
    const dept=document.getElementById('att5Dept');
    const status=document.getElementById('att5Status');

    // Eligibility is determined from the row's effective status, not merely
    // from the checkbox property. This prevents an on-leave row from ever
    // entering a bulk selection, even if another script changes checkbox state.
    const eligible=()=>Array.from(body.querySelectorAll('tr')).filter(r=>{
        if(r.style.display==='none') return false;
        if(r.dataset.eligible==='0' || r.dataset.status==='on_leave') return false;
        const c=r.querySelector('.att5-employee');
        return !!c && !c.disabled;
    }).map(r=>r.querySelector('.att5-employee'));

    const allEmployees=()=>Array.from(body.querySelectorAll('.att5-employee'));
    const checked=()=>eligible().filter(c=>c.checked);

    const sanitize=()=>{
        body.querySelectorAll('tr[data-status="on_leave"] .att5-employee').forEach(c=>{
            c.checked=false;
            c.disabled=true;
        });
    };

    const refresh=()=>{
        sanitize();
        const cs=checked();
        const es=eligible();
        count.textContent=cs.length+' موظف محدد';
        selection.classList.toggle('show',cs.length>0);
        all.disabled=es.length===0;
        all.checked=es.length>0 && cs.length===es.length;
        all.indeterminate=cs.length>0 && cs.length<es.length;
    };

    const filter=()=>{
        const q=(search.value||'').trim().toLowerCase(), d=dept.value, s=status.value;
        body.querySelectorAll('tr').forEach(r=>{
            if(!r.querySelector('.att5-employee')) return;
            const n=(r.dataset.name||'').toLowerCase(), rd=r.dataset.dept||'', rs=r.dataset.status||'';
            r.style.display=(!q||n.includes(q))&&(!d||rd===d)&&(!s||rs===s)?'':'none';
        });
        refresh();
    };

    all.addEventListener('change',()=>{
        // First clear every checkbox. Then select ONLY eligible employees.
        allEmployees().forEach(c=>c.checked=false);
        if(all.checked) eligible().forEach(c=>c.checked=true);
        refresh();
    });

    body.addEventListener('change',e=>{
        if(e.target.classList.contains('att5-employee')){
            if(e.target.disabled || e.target.closest('tr')?.dataset.status==='on_leave') e.target.checked=false;
            refresh();
        }
    });

    [search,dept,status].forEach(el=>el.addEventListener('input',filter));
    [dept,status].forEach(el=>el.addEventListener('change',filter));

    document.querySelectorAll('[data-bulk-action]').forEach(btn=>btn.addEventListener('click',async()=>{
        refresh();
        const cs=checked();
        if(!cs.length)return;
        const action=btn.dataset.bulkAction;
        const mode=document.getElementById('att5BulkMode').value;
        if(window.Swal){
            const result=await Swal.fire({icon:'question',title:'تأكيد الإجراء',html:'سيتم تنفيذ الإجراء على <b>'+cs.length+'</b> موظف.',showCancelButton:true,confirmButtonText:'تنفيذ',cancelButtonText:'إلغاء'});
            if(!result.isConfirmed)return;
        }
        const fd=new FormData();
        fd.append('action',action);
        fd.append('selected_date',<?php echo json_encode($selectedDate); ?>);
        fd.append('bulk_work_mode',mode);
        fd.append('employee_ids_json',JSON.stringify(cs.map(c=>Number(c.value))));
        try{
            const res=await fetch('bulk_attendance.php',{method:'POST',body:fd,credentials:'same-origin'});
            const data=await res.json();
            if(!res.ok||!data.ok)throw new Error(data.message||'تعذر تنفيذ الإجراء.');
            if(window.Swal)await Swal.fire({icon:data.skipped>0?'warning':'success',title:'تم التنفيذ',text:data.message,confirmButtonText:'حسناً'});
            location.reload();
        }catch(err){
            if(window.Swal)Swal.fire({icon:'error',title:'تعذر التنفيذ',text:err.message,confirmButtonText:'حسناً'});else alert(err.message);
        }
    }));

    document.querySelectorAll('.att5-return-form').forEach(form=>form.addEventListener('submit',async e=>{
        e.preventDefault();
        if(window.Swal){
            const r=await Swal.fire({icon:'question',title:'عودة من الإجازة',text:'هل تؤكد عودة الموظف من الإجازة لهذا التاريخ؟ بعد التأكيد سيصبح مؤهلاً لتسجيل الحضور.',showCancelButton:true,confirmButtonText:'تأكيد العودة',cancelButtonText:'إلغاء'});
            if(!r.isConfirmed)return;
        }
        form.submit();
    }));

    // Initial cleanup also protects against browser-restored checkbox state.
    sanitize();
    refresh();
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
