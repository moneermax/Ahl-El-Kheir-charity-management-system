<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_employment.php';

Session::start();
$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($userRole, ['hr_manager', 'admin'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$states = hrGetEmploymentStates();
$rows = dbFetchAll(
    "SELECT e.id, e.employee_code, e.full_name, e.status,
            e.employment_state_id, e.employment_state_changed_at,
            s.code AS state_code, s.name_ar AS state_name,
            h.effective_from AS history_from, h.reason AS history_reason
     FROM employees e
     LEFT JOIN hr_employment_states s ON s.id = e.employment_state_id
     LEFT JOIN hr_employee_state_history h
       ON h.employee_id = e.id AND h.effective_to IS NULL
     ORDER BY e.full_name ASC, e.id ASC"
);

$pageTitle = 'الحالة الوظيفية';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.hr-state-wrap{max-width:1400px;margin:0 auto;padding:20px}
.hr-state-header{background:#fff;border:1px solid #e8edf3;border-radius:12px;padding:20px 24px;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.hr-state-header h1{font-size:1.45rem;margin:0;color:#1b4d8f;font-weight:700}
.hr-state-header p{margin:6px 0 0;color:#6c757d}
.hr-state-card{background:#fff;border:1px solid #e8edf3;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.hr-state-card-head{padding:14px 18px;background:#f8fafc;border-bottom:1px solid #e8edf3;font-weight:700;color:#344054}
.hr-state-table{width:100%;border-collapse:collapse}
.hr-state-table th,.hr-state-table td{padding:11px 14px;border-bottom:1px solid #eef1f4;text-align:right;vertical-align:middle;font-size:.88rem}
.hr-state-table th{background:#fbfcfd;color:#667085;font-weight:700;white-space:nowrap}
.hr-state-table tr:last-child td{border-bottom:0}
.hr-state-table tr:hover td{background:#fafcff}
.hr-state-table tr.state-suspended td{background:#fff8e6;border-bottom-color:#f5df9a}
.hr-state-table tr.state-suspended:hover td{background:#fff3d6}
.state-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:999px;background:#eef4ff;color:#1b4d8f;font-weight:700;font-size:.78rem}
.state-pill.suspended{background:#fff0c2;color:#8a5a00}
.state-dot{width:7px;height:7px;border-radius:50%;background:#1b4d8f}
.state-dot.suspended{background:#d39e00}
.state-code{font-size:.72rem;color:#98a2b3;margin-top:4px}
.metric-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:18px}
.metric{background:#fff;border:1px solid #e8edf3;border-radius:10px;padding:14px 16px}.metric small{display:block;color:#667085;margin-bottom:4px}.metric strong{font-size:1.35rem;color:#101828}
@media(max-width:768px){.metric-grid{grid-template-columns:1fr}.hr-state-wrap{padding:12px}}
</style>

<div class="hr-state-wrap">
    <div class="hr-state-header">
        <h1><i class="fas fa-id-badge me-2"></i>الحالة الوظيفية</h1>
        <p>المرجع الجديد لدورة حياة الموظف — للعرض والتحقق فقط في هذه المرحلة.</p>
    </div>

    <div class="metric-grid">
        <div class="metric"><small>حالات التوظيف المعرفة</small><strong><?php echo count($states); ?></strong></div>
        <div class="metric"><small>الموظفون المرتبطون بحالة</small><strong><?php echo count(array_filter($rows, static fn($r) => !empty($r['employment_state_id']))); ?></strong></div>
        <div class="metric"><small>سجلات التاريخ الحالية</small><strong><?php echo count(array_filter($rows, static fn($r) => !empty($r['history_from']))); ?></strong></div>
    </div>

    <div class="hr-state-card">
        <div class="hr-state-card-head">سجل الحالة الوظيفية الحالي</div>
        <div class="table-responsive">
            <table class="hr-state-table">
                <thead><tr><th>الكود</th><th>الموظف</th><th>الحالة الوظيفية</th><th>الحالة القديمة</th><th>بدأت الحالة في</th><th>سبب السجل</th></tr></thead>
                <tbody>
                <?php
                $legacyLabels = [
                    'active' => 'نشط',
                    'suspended' => 'موقوف مؤقتاً',
                    'on_leave' => 'في إجازة',
                    'terminated' => 'منهي الخدمة',
                ];
                foreach ($rows as $row):
                    $isSuspended = (($row['state_code'] ?? '') === 'suspended');
                    $legacyStatus = (string)($row['status'] ?? '');
                    $legacyLabel = $legacyLabels[$legacyStatus] ?? ($legacyStatus !== '' ? $legacyStatus : '-');
                ?>
                    <tr class="<?php echo $isSuspended ? 'state-suspended' : ''; ?>">
                        <td><code><?php echo e((string)$row['employee_code']); ?></code></td>
                        <td><strong><?php echo e((string)$row['full_name']); ?></strong></td>
                        <td>
                            <?php if (!empty($row['state_name'])): ?>
                                <span class="state-pill<?php echo $isSuspended ? ' suspended' : ''; ?>">
                                    <span class="state-dot<?php echo $isSuspended ? ' suspended' : ''; ?>"></span>
                                    <?php echo e((string)$row['state_name']); ?>
                                </span>
                                <div class="state-code"><?php echo e((string)$row['state_code']); ?></div>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td><span class="text-muted"><?php echo e($legacyLabel); ?></span></td>
                        <td><?php echo e((string)($row['history_from'] ?? $row['employment_state_changed_at'] ?? '-')); ?></td>
                        <td><?php echo e((string)($row['history_reason'] ?? '-')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="modules/hr/index.php" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
