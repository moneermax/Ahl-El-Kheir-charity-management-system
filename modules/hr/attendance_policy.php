<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_attendance_policy.php';

Session::start();
$role = (string)Session::getUserRole();

if (!Session::isLoggedIn() || !in_array($role, ['hr_manager', 'admin'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit;
}

$pdo = db();
$message = '';
$error = '';

$flashes = get_flashes();
foreach ($flashes as $flash) {
    if (($flash['type'] ?? '') === 'success') {
        $message = (string)($flash['message'] ?? '');
    } elseif (($flash['type'] ?? '') === 'error') {
        $error = (string)($flash['message'] ?? '');
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة وحاول مرة أخرى.');
        }

        $action = (string)($_POST['action'] ?? '');

        if ($action === 'update_policy') {
            $policyId = (int)($_POST['policy_id'] ?? 0);
            $existingPolicy = dbFetchOne('SELECT * FROM hr_attendance_policy_versions WHERE id = ? LIMIT 1', [$policyId]);
            if (!$existingPolicy) throw new InvalidArgumentException('إصدار السياسة المطلوب غير موجود.');
            if ((string)$existingPolicy['effective_from'] < date('Y-m-d')) throw new InvalidArgumentException('لا يمكن تعديل سياسة منتهية.');
            if ((string)$existingPolicy['effective_from'] === date('Y-m-d') && hrAttendancePolicyHasOperationalAttendance($pdo, $existingPolicy)) {
                throw new InvalidArgumentException('لا يمكن تعديل سياسة اليوم بعد تسجيل أي حركة حضور فعلية تحت نطاق سريانها.');
            }
            $p = hrAttendancePolicyValidate($_POST);
            $collision = dbFetchOne('SELECT id FROM hr_attendance_policy_versions WHERE effective_from = ? AND id <> ? LIMIT 1', [$p['effective_from'], $policyId]);
            if ($collision) throw new InvalidArgumentException('يوجد إصدار سياسة آخر بنفس تاريخ السريان بالفعل.');
            $pdo->prepare("UPDATE hr_attendance_policy_versions SET policy_name=?, effective_from=?, working_start_time=?, working_end_time=?, working_days=?, attendance_cutoff_time=?, absence_finalization_time=?, auto_login_attendance=?, auto_absence_enabled=?, default_work_mode=?, notes=? WHERE id=?")
                ->execute([$p['policy_name'],$p['effective_from'],$p['working_start_time'],$p['working_end_time'],$p['working_days'],$p['attendance_cutoff_time'],$p['absence_finalization_time'],$p['auto_login_attendance'],$p['auto_absence_enabled'],$p['default_work_mode'],$p['notes'],$policyId]);
            flash('success', 'تم تحديث إصدار سياسة الحضور V' . (int)$existingPolicy['version_no'] . ' بأمان.');
            header('Location: ' . APP_URL . 'modules/hr/attendance_policy.php');
            exit;
        } elseif ($action === 'create_policy') {
            $p = hrAttendancePolicyValidate($_POST);
            $existing = dbFetchOne(
                'SELECT id FROM hr_attendance_policy_versions WHERE effective_from = ? LIMIT 1',
                [$p['effective_from']]
            );
            if ($existing) {
                throw new InvalidArgumentException('يوجد إصدار سياسة آخر بنفس تاريخ السريان بالفعل.');
            }

            $next = dbFetchOne(
                'SELECT COALESCE(MAX(version_no),0)+1 AS next_version FROM hr_attendance_policy_versions'
            );
            $version = (int)$next['next_version'];

            $pdo->prepare(
                "INSERT INTO hr_attendance_policy_versions
                 (version_no, policy_name, effective_from, working_start_time, working_end_time, working_days,
                  attendance_cutoff_time, absence_finalization_time, auto_login_attendance,
                  auto_absence_enabled, default_work_mode, notes, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $version, $p['policy_name'], $p['effective_from'],
                $p['working_start_time'], $p['working_end_time'], $p['working_days'],
                $p['attendance_cutoff_time'], $p['absence_finalization_time'],
                $p['auto_login_attendance'], $p['auto_absence_enabled'],
                $p['default_work_mode'], $p['notes'], Session::getUserID()
            ]);

            flash('success', 'تم إنشاء إصدار سياسة الحضور V' . $version . ' بتاريخ سريان ' . $p['effective_from'] . '.');
            header('Location: ' . APP_URL . 'modules/hr/attendance_policy.php');
            exit;
        } elseif ($action === 'delete_policy') {
            $policyId = (int)($_POST['policy_id'] ?? 0);
            $policy = dbFetchOne(
                'SELECT * FROM hr_attendance_policy_versions WHERE id = ? LIMIT 1',
                [$policyId]
            );
            if (!$policy) throw new InvalidArgumentException('إصدار السياسة المطلوب غير موجود.');

            if ((string)$policy['effective_from'] < date('Y-m-d')) {
                throw new InvalidArgumentException('لا يمكن حذف سياسة منتهية.');
            }
            if ((string)$policy['effective_from'] === date('Y-m-d') && hrAttendancePolicyHasOperationalAttendance($pdo, $policy)) {
                throw new InvalidArgumentException('لا يمكن حذف سياسة اليوم بعد تسجيل أي حركة حضور فعلية تحت نطاق سريانها.');
            }

            $pdo->prepare('DELETE FROM hr_attendance_policy_versions WHERE id = ?')->execute([$policyId]);
            flash('success', 'تم حذف إصدار السياسة المستقبلية بأمان.');
            header('Location: ' . APP_URL . 'modules/hr/attendance_policy.php');
            exit;
        }
    }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    header('Location: ' . APP_URL . 'modules/hr/attendance_policy.php');
    exit;
}

$activePolicy = hrAttendancePolicyGetActive($pdo);
$policies = hrAttendancePolicyGetAll($pdo);
$editPolicyId = (int)($_GET['edit'] ?? 0);
$editPolicy = null;
if ($editPolicyId > 0) {
    $editPolicy = dbFetchOne('SELECT * FROM hr_attendance_policy_versions WHERE id = ? LIMIT 1', [$editPolicyId]);
    if ($editPolicy && (string)$editPolicy['effective_from'] < date('Y-m-d')) $editPolicy = null;
if ($editPolicy && (string)$editPolicy['effective_from'] === date('Y-m-d') && hrAttendancePolicyHasOperationalAttendance($pdo, $editPolicy)) $editPolicy = null;
}

$pageTitle = 'سياسة الحضور والانصراف';
$active = 'attendance_policy';
$attendanceBackUrl = APP_URL . 'modules/hr/attendance.php';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.attendance-policy{max-width:1250px;margin:0 auto}
.attendance-policy .hero{background:linear-gradient(135deg,#173f73,#2d67ad);color:#fff;border-radius:14px;padding:22px 25px;margin-bottom:16px}
.attendance-policy .hero h1{font-size:1.35rem;font-weight:800;margin:0}
.attendance-policy .hero p{font-size:.76rem;margin:6px 0 0;opacity:.9;line-height:1.6}
.attendance-policy .card{background:#fff;border:1px solid #e5eaf0;border-radius:12px;box-shadow:0 2px 12px rgba(16,24,40,.05);margin-bottom:15px;overflow:hidden}
.attendance-policy .card-body{padding:16px}
.attendance-policy .section-title{font-weight:800;color:#173f73;border-bottom:1px solid #edf0f4;padding-bottom:7px;margin-bottom:12px}
.attendance-policy-feedback{position:fixed;top:76px;right:20px;z-index:1080;width:min(420px,calc(100vw - 40px));pointer-events:none}
.attendance-policy-feedback .alert{box-shadow:0 10px 28px rgba(16,24,40,.16);border-radius:10px;margin-bottom:8px;pointer-events:auto}
@media (max-width:576px){.attendance-policy-feedback{top:66px;right:12px;width:calc(100vw - 24px)}}
</style>
<script>
window.AK_PAGE_BACK_URL=<?php echo json_encode($attendanceBackUrl, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.attendance-policy form');
    const hidden = document.getElementById('workingDaysValue');
    if (!form || !hidden) return;

    function syncWorkingDays() {
        const selected = Array.from(document.querySelectorAll('.attendance-day-status'))
            .filter(function (select) { return select.value === 'work'; })
            .map(function (select) { return Number(select.dataset.day); })
            .sort(function (a, b) { return a - b; });
        hidden.value = selected.join(',');
    }

    document.querySelectorAll('.attendance-day-status').forEach(function (select) {
        select.addEventListener('change', syncWorkingDays);
    });

    form.addEventListener('submit', syncWorkingDays);

    document.querySelectorAll('.attendance-policy-feedback .alert').forEach(function (alert) {
        window.setTimeout(function () {
            alert.style.transition = 'opacity .25s ease, transform .25s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-6px)';
            window.setTimeout(function () {
                if (alert.parentNode) alert.parentNode.removeChild(alert);
            }, 280);
        }, 6000);
    });
});
</script>
<div class="attendance-policy">
<section class="hero">
    <h1><i class="fas fa-calendar-check me-2"></i>سياسة الحضور والانصراف</h1>
    <p>إعدادات مؤسسية مُصدرة بإصدارات زمنية. يمكن تصحيح سياسة اليوم قبل أول حركة حضور فعلية، ثم تُقفل تلقائياً لحماية السجل التاريخي.</p>
</section>

<div class="card">
<div class="card-body">
<?php if ($message || $error): ?>
<div class="attendance-policy-feedback" aria-live="polite">
    <?php if ($message): ?><div class="alert alert-success py-2" role="alert"><i class="fas fa-circle-check me-1"></i><?=e($message)?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><i class="fas fa-triangle-exclamation me-1"></i><?=e($error)?></div><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($activePolicy): ?>
<div class="alert alert-info py-2 small">السياسة السارية حالياً: <strong>V<?= (int)$activePolicy['version_no'] ?></strong> — <?=e($activePolicy['policy_name'])?> — <?=e($activePolicy['effective_from'])?></div>
<?php else: ?>
<div class="alert alert-warning py-2 small">لا توجد سياسة حضور سارية حالياً. لن يتم إنشاء حضور تلقائي حتى يتم إصدار سياسة.</div>
<?php endif; ?>

<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="<?= $editPolicy ? 'update_policy' : 'create_policy' ?>"><?php if ($editPolicy): ?><input type="hidden" name="policy_id" value="<?= (int)$editPolicy['id'] ?>"><?php endif; ?>

<div class="section-title">ساعات العمل</div>
<div class="row g-3">
<div class="col-md-4"><label class="form-label">اسم السياسة *</label><input name="policy_name" class="form-control" required value="<?=e($editPolicy['policy_name'] ?? 'سياسة الحضور والانصراف الأساسية')?>"></div>
<div class="col-md-4"><label class="form-label">تاريخ السريان *</label><input type="date" name="effective_from" class="form-control" min="<?=e(date('Y-m-d'))?>" value="<?=e($editPolicy['effective_from'] ?? '')?>" required></div>
<div class="col-md-4"><label class="form-label">نمط العمل الافتراضي</label><select name="default_work_mode" class="form-select"><option value="remote" <?= (($editPolicy['default_work_mode'] ?? 'remote') === 'remote') ? 'selected' : '' ?>>عن بُعد</option><option value="onsite" <?= (($editPolicy['default_work_mode'] ?? '') === 'onsite') ? 'selected' : '' ?>>من المكتب</option><option value="hybrid" <?= (($editPolicy['default_work_mode'] ?? '') === 'hybrid') ? 'selected' : '' ?>>هجين</option></select></div>
<div class="col-md-3"><label class="form-label">بداية العمل<input type="time" name="working_start_time" class="form-control" value="<?=e(substr((string)($editPolicy['working_start_time'] ?? '07:00:00'),0,5))?>" required></label></div>
<div class="col-12">
<label class="form-label fw-bold">جدول أيام العمل الأسبوعي</label>
<div class="table-responsive border rounded-3">
<table class="table table-sm align-middle mb-0 attendance-weekly-schedule">
<thead><tr><th>اليوم</th><th style="width:190px">الحالة</th></tr></thead>
<tbody>
<?php
$selectedWorkingDays = array_map('intval', explode(',', (string)($editPolicy['working_days'] ?? '1,2,3,4,5')));
$weeklyDays = [
    6 => 'السبت',
    7 => 'الأحد',
    1 => 'الاثنين',
    2 => 'الثلاثاء',
    3 => 'الأربعاء',
    4 => 'الخميس',
    5 => 'الجمعة',
];
foreach ($weeklyDays as $dayNo => $dayName):
?>
<tr>
<td class="fw-semibold"><?=e($dayName)?></td>
<td>
<select class="form-select form-select-sm attendance-day-status" data-day="<?= $dayNo ?>">
<option value="work" <?=in_array($dayNo, $selectedWorkingDays, true) ? 'selected' : ''?>>يوم عمل</option>
<option value="off" <?=in_array($dayNo, $selectedWorkingDays, true) ? '' : 'selected'?>>عطلة</option>
</select>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="form-text">يمكن تحديد أي عدد من أيام العطلة الأسبوعية. هذا الجدول يحدد النمط الأسبوعي المعتاد فقط، ولا يمثل الإجازات أو العطلات الاستثنائية في تواريخ محددة.</div>
<input type="hidden" name="working_days" id="workingDaysValue" value="<?=e(implode(',', $selectedWorkingDays))?>" required>
</div>
<div class="col-md-3"><label class="form-label">نهاية العمل<input type="time" name="working_end_time" class="form-control" value="<?=e(substr((string)($editPolicy['working_end_time'] ?? '16:00:00'),0,5))?>" required></label></div>
<div class="col-md-3"><label class="form-label">آخر وقت لاحتساب الحضور<input type="time" name="attendance_cutoff_time" class="form-control" value="<?=e(substr((string)($editPolicy['attendance_cutoff_time'] ?? '16:00:00'),0,5))?>" required></label></div>
<div class="col-md-3"><label class="form-label">وقت تثبيت الغياب<input type="time" name="absence_finalization_time" class="form-control" value="<?=e(substr((string)($editPolicy['absence_finalization_time'] ?? '16:00:00'),0,5))?>" required></label></div>
</div>

<div class="section-title mt-4">التشغيل الآلي</div>
<div class="row g-3">
<div class="col-md-6"><label><input type="checkbox" name="auto_login_attendance" <?= (($editPolicy['auto_login_attendance'] ?? 1) ? 'checked' : '') ?>> تسجيل الحضور تلقائياً عند تسجيل الدخول</label><div class="small text-muted mt-1">لا يتم استبدال وقت الدخول الأول بدخول لاحق في نفس اليوم.</div></div>
<div class="col-md-6"><label><input type="checkbox" name="auto_absence_enabled" <?= (($editPolicy['auto_absence_enabled'] ?? 1) ? 'checked' : '') ?>> تثبيت الغياب تلقائياً عند بلوغ وقت تثبيت الغياب</label><div class="small text-muted mt-1">يستثني الموظفين غير العاملين والإجازات المعتمدة.</div></div>
<div class="col-12"><label class="form-label">ملاحظات<textarea name="notes" class="form-control" rows="2"><?=e($editPolicy['notes'] ?? '')?></textarea></label></div>
</div>

<div class="mt-4"><button class="btn btn-primary" type="submit"><i class="fas <?= $editPolicy ? 'fa-save' : 'fa-plus' ?> me-1"></i><?= $editPolicy ? 'حفظ تعديلات السياسة' : 'إنشاء إصدار السياسة' ?></button><?php if ($editPolicy): ?><a class="btn btn-outline-secondary ms-2" href="<?=e(APP_URL.'modules/hr/attendance_policy.php')?>">إلغاء التعديل</a><?php endif; ?></div>
</form>
</div>
</div>

<div class="card">
<div class="card-body">
<h5 class="fw-bold mb-3">سجل إصدارات السياسة</h5>
<div class="table-responsive">
<table class="table table-sm align-middle">
<thead><tr><th>الإصدار</th><th>السياسة</th><th>السريان</th><th>العمل</th><th>الحضور</th><th>الغياب</th><th>النمط</th><th>الإجراءات</th></tr></thead>
<tbody>
<?php foreach ($policies as $p): ?>
<?php
$policyDate = (string)$p['effective_from'];
$future = $policyDate > date('Y-m-d');
$todayUnused = $policyDate === date('Y-m-d') && !hrAttendancePolicyHasOperationalAttendance($pdo, $p);
$canManage = $future || $todayUnused;
?>
<tr>
<td><strong>V<?= (int)$p['version_no'] ?></strong></td>
<td><?=e($p['policy_name'])?></td>
<td><?=e($p['effective_from'])?></td>
<td><?=e(substr((string)$p['working_start_time'],0,5))?> → <?=e(substr((string)$p['working_end_time'],0,5))?></td>
<td><?=e(substr((string)$p['attendance_cutoff_time'],0,5))?></td>
<td><?=e(substr((string)$p['absence_finalization_time'],0,5))?></td>
<td><?=e((string)$p['default_work_mode'])?></td>
<td class="text-nowrap">
<a class="btn btn-sm btn-outline-primary <?= $canManage ? '' : 'disabled' ?>" href="<?= $canManage ? e(APP_URL.'modules/hr/attendance_policy.php?edit='.(int)$p['id']) : '#' ?>"><i class="fas fa-pen"></i></a>
<form method="post" class="d-inline" onsubmit="return confirm('هل تريد حذف إصدار السياسة هذا؟ لا يمكن التراجع عن الحذف بعد التنفيذ.');">
<?=csrf_field()?>
<input type="hidden" name="action" value="delete_policy">
<input type="hidden" name="policy_id" value="<?= (int)$p['id'] ?>">
<button class="btn btn-sm btn-outline-danger" type="submit" <?=$canManage?'':'disabled'?>><i class="fas fa-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
