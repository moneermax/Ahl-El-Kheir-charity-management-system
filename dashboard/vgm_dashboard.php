<?php
// dashboard/vgm_dashboard.php - Vice General Manager dashboard
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/session.php';

Session::start();

// Access guard
if (!Session::isLoggedIn() || Session::getUserRole() !== 'vice_general_manager') {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

// Supervisor account actions: edit, pause/resume, and safe account archiving.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supervisor_action'])) {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
        redirect('dashboard/vgm_dashboard.php');
    }

    $supervisorId = (int)($_POST['supervisor_id'] ?? 0);
    $action = (string)($_POST['supervisor_action'] ?? '');
    $target = dbFetchOne("SELECT u.id, u.full_name, u.is_active, u.legacy_status
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.id = ? AND r.code = 'supervisor'", [$supervisorId]);

    if (!$target) {
        flash('error', 'المشرف غير موجود.');
        redirect('dashboard/vgm_dashboard.php');
    }

    try {
        if ($action === 'toggle_status') {
            // Archived accounts are historical records and cannot be reactivated from here.
            if (($target['legacy_status'] ?? '') === 'deleted') {
                flash('error', 'هذا الحساب مؤرشف ولا يمكن إعادة تفعيله من هذه الصفحة.');
                redirect('dashboard/vgm_dashboard.php');
            }

            $newActive = ((int)$target['is_active'] === 1) ? 0 : 1;
            dbExecute('UPDATE users SET is_active = ? WHERE id = ?', [$newActive, $supervisorId]);
            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                    VALUES (?, 'UPDATE', 'users', ?, ?, ?, ?, ?)", [
                    Session::getUserId(), $supervisorId,
                    json_encode(['is_active' => (int)$target['is_active']], JSON_UNESCAPED_UNICODE),
                    json_encode(['is_active' => $newActive], JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Throwable $e) { /* audit must never break the action */ }
            flash('success', $newActive ? 'تم تفعيل حساب المشرف.' : 'تم إيقاف حساب المشرف.');
        } elseif ($action === 'delete_account') {
            // Logical account archiving: revoke login access and detach assignments, but retain the
            // user row so audit/history and all business records remain intact.
            if (($target['legacy_status'] ?? '') === 'deleted') {
                flash('error', 'هذا الحساب مؤرشف بالفعل.');
                redirect('dashboard/vgm_dashboard.php');
            }

            $pdo = db();
            $pdo->beginTransaction();
            dbExecute('UPDATE families SET supervisor_id = NULL WHERE supervisor_id = ?', [$supervisorId]);
            dbExecute('UPDATE sponsors SET supervisor_id = NULL WHERE supervisor_id = ?', [$supervisorId]);
            dbExecute('UPDATE sponsor_payments SET supervisor_id = NULL WHERE supervisor_id = ?', [$supervisorId]);
            dbExecute('DELETE FROM supervisor_letters WHERE supervisor_id = ?', [$supervisorId]);
            dbExecute('DELETE FROM user_sessions WHERE user_id = ?', [$supervisorId]);
            dbExecute("UPDATE users
                SET is_active = 0,
                    legacy_status = 'deleted',
                    username = CONCAT('deleted_supervisor_', id, '_', UNIX_TIMESTAMP()),
                    password_hash = ?,
                    email = NULL,
                    phone = NULL
                WHERE id = ?", [password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $supervisorId]);
            $pdo->commit();
            flash('success', 'تمت أرشفة حساب المشرف وتعطيل صلاحية الدخول مع الحفاظ على البيانات والسجل التاريخي.');
        } else {
            flash('error', 'إجراء غير صالح.');
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('VGM supervisor action: ' . $e->getMessage());
        flash('error', 'تعذر تنفيذ الإجراء، ولم يتم حذف أو تعديل البيانات المرتبطة.');
    }

    redirect('dashboard/vgm_dashboard.php');
}

// Statistics (live schema)
$stats = dbFetchOne("SELECT
    (SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = 'supervisor' AND u.is_active = 1) AS active_supervisors,
    (SELECT COUNT(*) FROM supervisor_letters) AS letters_assigned,
    (SELECT COUNT(*) FROM letters l WHERE NOT EXISTS (SELECT 1 FROM supervisor_letters sl WHERE sl.letter_id = l.id)) AS letters_unassigned,
    (SELECT COUNT(*) FROM sponsors) AS total_sponsors,
    (SELECT COUNT(*) FROM families) AS total_families,
    (SELECT COUNT(*) FROM families WHERE status = 'active') AS active_families,
    (SELECT COUNT(*) FROM sponsorships WHERE status = 'active') AS active_sponsorships
");

$stats = is_array($stats) ? $stats : [];

$supervisors = dbFetchAll("SELECT
    u.id,
    u.full_name,
    u.is_active,
    COALESCE(u.legacy_status, '') AS legacy_status,
    GROUP_CONCAT(l.name_ar SEPARATOR '، ') AS letters,
    (SELECT COUNT(*) FROM sponsors s WHERE s.supervisor_id = u.id) AS sponsor_count
FROM users u
INNER JOIN roles r ON r.id = u.role_id
LEFT JOIN supervisor_letters sl ON sl.supervisor_id = u.id
LEFT JOIN letters l ON l.id = sl.letter_id
WHERE r.code = 'supervisor'
GROUP BY u.id, u.full_name, u.is_active, u.legacy_status
ORDER BY
    u.is_active DESC,
    CASE WHEN COALESCE(u.legacy_status, '') = 'deleted' THEN 2 ELSE 1 END ASC,
    u.full_name
");

$cards = [
    ['href' => 'modules/supervisors/index.php', 'label' => 'إدارة المشرفين', 'description' => 'عرض وإدارة حسابات المشرفين', 'icon' => 'fa-user-tie', 'class' => 'action-primary'],
    ['href' => 'modules/supervisors/create.php', 'label' => 'إضافة مشرف', 'description' => 'إنشاء حساب مشرف جديد', 'icon' => 'fa-user-plus', 'class' => 'action-secondary'],
    ['href' => 'modules/supervisors/assign-letters.php', 'label' => 'توزيع الحروف', 'description' => 'توزيع الحروف على المشرفين', 'icon' => 'fa-font', 'class' => 'action-secondary'],
    ['href' => 'modules/deputy_gm/groups.php', 'label' => 'مجموعات الأيتام', 'description' => 'إنشاء المجموعات وتعيين الحاضنات', 'icon' => 'fa-users', 'class' => 'action-success'],
];

$statCards = [
    ['value' => (int)($stats['active_supervisors'] ?? 0), 'label' => 'مشرفون نشطون', 'icon' => 'fa-user-tie'],
    ['value' => (int)($stats['letters_assigned'] ?? 0), 'label' => 'حروف موزعة', 'icon' => 'fa-font'],
    ['value' => (int)($stats['letters_unassigned'] ?? 0), 'label' => 'حروف غير موزعة', 'icon' => 'fa-keyboard'],
    ['value' => (int)($stats['total_sponsors'] ?? 0), 'label' => 'إجمالي الكفلاء', 'icon' => 'fa-hand-holding-heart'],
];

$pageTitle = 'لوحة نائب المدير العام';
$active = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<style>
.stat-card {
    border-right: 4px solid #1b4d8f;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(10,31,68,.07);
}
.stat-value {
    font-size: 1.55rem;
    font-weight: 700;
    color: #1b4d8f;
}
.stat-label {
    color: #6c757d;
    font-size: .82rem;
}

.quick-action-grid {
    max-width: 760px;
    margin: 0 auto 1.5rem;
}
.quick-action-row {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 1rem;
}
.quick-action-row:last-child {
    margin-bottom: 0;
}
.quick-action-card {
    width: 230px;
    min-height: 128px;
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(10,31,68,.08);
    transition: transform .2s, box-shadow .2s;
    text-decoration: none;
    color: inherit;
}
.quick-action-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 18px rgba(10,31,68,.14);
    color: inherit;
}
.quick-action-card .card-body {
    padding: 1rem .75rem;
}
.quick-action-icon {
    font-size: 1.65rem;
    margin-bottom: .5rem;
}
.quick-action-card h6 {
    font-size: .95rem;
    margin-bottom: .25rem;
}
.quick-action-card p {
    font-size: .75rem;
    margin-bottom: 0;
}
.action-primary { border-top: 3px solid #1b4d8f; }
.action-secondary { border-top: 3px solid #6c757d; }
.action-success { border-top: 3px solid #198754; }

.supervisor-table tbody tr.supervisor-disabled td {
    background-color: #fff8e1;
}
.supervisor-table tbody tr.supervisor-archived td {
    background-color: #eeeeee;
    color: #6c757d;
}
.supervisor-table tbody tr.supervisor-archived strong {
    color: #6c757d;
}
.supervisor-table tbody tr.supervisor-archived:hover td,
.supervisor-table tbody tr.supervisor-disabled:hover td {
    background-color: inherit;
}
.archived-badge {
    background-color: #6c757d;
    color: #fff;
}
.disabled-badge {
    background-color: #ffc107;
    color: #212529;
}

@media (max-width: 576px) {
    .quick-action-row {
        gap: .65rem;
    }
    .quick-action-card {
        width: 46%;
        min-height: 120px;
    }
}
</style>

<div class="welcome-section fade-in">
    <h2>مرحباً، <?php echo e(Session::getUserName()); ?></h2>
    <p>إدارة المشرفين وتوزيع الحروف ومتابعة العمليات</p>
</div>

<?php include __DIR__ . '/../includes/alerts.php'; ?>

<!-- Quick actions: moved from the header into a compact two-row clickable card layout. -->
<div class="quick-action-grid fade-in">
    <div class="quick-action-row">
        <?php foreach (array_slice($cards, 0, 2) as $c): ?>
            <a href="<?php echo APP_URL . $c['href']; ?>" class="quick-action-card card <?php echo e($c['class']); ?>">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="quick-action-icon">
                        <i class="fas <?php echo e($c['icon']); ?>"></i>
                    </div>
                    <h6 class="card-title"><?php echo e($c['label']); ?></h6>
                    <p class="card-text text-muted"><?php echo e($c['description']); ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="quick-action-row">
        <?php foreach (array_slice($cards, 2, 2) as $c): ?>
            <a href="<?php echo APP_URL . $c['href']; ?>" class="quick-action-card card <?php echo e($c['class']); ?>">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="quick-action-icon">
                        <i class="fas <?php echo e($c['icon']); ?>"></i>
                    </div>
                    <h6 class="card-title"><?php echo e($c['label']); ?></h6>
                    <p class="card-text text-muted"><?php echo e($c['description']); ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Compact live statistics -->
<div class="row g-2 mb-4 fade-in">
    <?php foreach ($statCards as $c): ?>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center h-100">
                <div class="card-body py-2">
                    <div class="stat-value"><?php echo $c['value']; ?></div>
                    <div class="stat-label">
                        <i class="fas <?php echo e($c['icon']); ?> me-1"></i>
                        <?php echo e($c['label']); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card fade-in">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-user-tie me-2"></i>المشرفون وحروفهم</span>
        <span class="badge bg-primary"><?php echo count($supervisors); ?> مشرف</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle supervisor-table">
                <thead>
                    <tr>
                        <th>المشرف</th>
                        <th>الحروف</th>
                        <th>الكفلاء</th>
                        <th>الحالة</th>
                        <th class="text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($supervisors)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">لا يوجد مشرفون حتى الآن</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($supervisors as $s): ?>
                            <?php
                            $isArchived = ($s['legacy_status'] ?? '') === 'deleted';
                            $isInactive = (int)$s['is_active'] !== 1;
                            $rowClass = $isArchived ? 'supervisor-archived' : ($isInactive ? 'supervisor-disabled' : '');
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td>
                                    <strong><?php echo e($s['full_name']); ?></strong>
                                    <?php if ($isArchived): ?>
                                        <span class="badge archived-badge ms-1">مؤرشف</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($s['letters'] ?: 'لا يوجد'); ?></td>
                                <td><?php echo (int)$s['sponsor_count']; ?></td>
                                <td>
                                    <?php if ($isArchived): ?>
                                        <span class="badge archived-badge">مؤرشف</span>
                                    <?php elseif ((int)$s['is_active'] === 1): ?>
                                        <span class="badge bg-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge disabled-badge">موقوف</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" style="white-space:nowrap;">
                                    <?php if (!$isArchived): ?>
                                        <a class="btn btn-sm btn-warning" title="تعديل بيانات الحساب"
                                           href="<?php echo APP_URL; ?>modules/supervisors/edit.php?id=<?php echo (int)$s['id']; ?>">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <form method="post" class="d-inline js-supervisor-action" data-confirm-type="toggle">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="supervisor_id" value="<?php echo (int)$s['id']; ?>">
                                            <button type="submit" name="supervisor_action" value="toggle_status"
                                                    class="btn btn-sm <?php echo ((int)$s['is_active'] === 1) ? 'btn-danger' : 'btn-success'; ?>"
                                                    title="<?php echo ((int)$s['is_active'] === 1) ? 'إيقاف الحساب' : 'تفعيل الحساب'; ?>">
                                                <i class="fas <?php echo ((int)$s['is_active'] === 1) ? 'fa-pause' : 'fa-play'; ?>"></i>
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline js-supervisor-action" data-confirm-type="delete">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="supervisor_id" value="<?php echo (int)$s['id']; ?>">
                                            <button type="submit" name="supervisor_action" value="delete_account" class="btn btn-sm btn-outline-danger" title="أرشفة حساب المشرف وفصل الارتباطات">
                                                <i class="fas fa-box-archive"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small"><i class="fas fa-box-archive me-1"></i>سجل تاريخي</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- System confirmation dialog: replaces the browser's native confirm() dialog. -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    'use strict';

    document.querySelectorAll('.js-supervisor-action').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var type = form.getAttribute('data-confirm-type');
            var isDelete = type === 'delete';
            var title = isDelete ? 'تأكيد أرشفة حساب المشرف' : 'تأكيد تغيير حالة الحساب';
            var text = isDelete
                ? 'سيتم تعطيل صلاحية الدخول وأرشفة الحساب وفصل ارتباطاته مع إبقاء جميع البيانات والسجل التاريخي. هل تريد المتابعة؟'
                : 'هل تريد تغيير حالة حساب هذا المشرف؟';

            Swal.fire({
                title: title,
                text: text,
                icon: isDelete ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، متابعة',
                cancelButtonText: 'إلغاء',
                reverseButtons: true,
                focusCancel: true,
                allowOutsideClick: false,
                customClass: {
                    confirmButton: 'btn btn-danger px-4 ms-2',
                    cancelButton: 'btn btn-secondary px-4'
                },
                buttonsStyling: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
})();
</script>

<!-- ADDED: Load the Age Alert Popup Widget for VGM Dashboard -->
<?php include __DIR__ . '/../includes/age_alert.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>