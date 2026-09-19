<?php
// modules/logs/audit.php - Audit log viewer (Admin + GM)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (!Session::isLoggedIn() || !in_array(Session::getUserRole(), ['admin', 'general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = t('navigation.audit_log');
$active    = 'logs';

// Audit-log deletion is intentionally restricted to administrators.
// General Manager retains read-only audit-log access.
$deleteMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['audit_action'] ?? '') === 'delete_old') {
    if (Session::getUserRole() !== 'admin') {
        $deleteMessage = ['type' => 'danger', 'text' => 'ليس لديك صلاحية حذف سجلات التدقيق.'];
    } else {
        $deleteBefore = trim((string)($_POST['delete_before'] ?? ''));
        $confirmed = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === '1';
        $isValidDate = (bool)preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $deleteBefore);

        if (!$isValidDate || !$confirmed) {
            $deleteMessage = ['type' => 'danger', 'text' => 'حدد تاريخًا صحيحًا ثم فعّل مربع تأكيد الحذف.'];
        } else {
            try {
                // The selected date is inclusive: delete records created on or before it.
                // Use an exclusive next-day boundary so the whole selected day is covered.
                $deleted = dbExecute(
                    'DELETE FROM audit_log WHERE created_at < DATE_ADD(?, INTERVAL 1 DAY)',
                    [$deleteBefore]
                );

                $remaining = (int)(dbFetchOne(
                    'SELECT COUNT(*) c FROM audit_log WHERE created_at < DATE_ADD(?, INTERVAL 1 DAY)',
                    [$deleteBefore]
                )['c'] ?? 0);

                if ($remaining > 0) {
                    $deleteMessage = [
                        'type' => 'danger',
                        'text' => 'لم تكتمل عملية التنظيف: ما زال هناك ' . number_format($remaining) . ' سجل ضمن نطاق الحذف. لم يتم اعتبار العملية ناجحة.',
                    ];
                } else {
                    $deleteMessage = [
                        'type' => 'success',
                        'text' => 'تم حذف ' . number_format($deleted) . ' سجل تدقيق حتى تاريخ ' . $deleteBefore . '، وتم التحقق من عدم بقاء سجلات ضمن هذا النطاق.',
                    ];
                }
            } catch (Throwable $e) {
                $deleteMessage = ['type' => 'danger', 'text' => 'تعذر حذف سجلات التدقيق.'];
            }
        }
    }
}

$fAction = trim($_GET['action'] ?? '');
$fUser   = (int)($_GET['user'] ?? 0);
$from    = trim($_GET['from'] ?? '');
$to      = trim($_GET['to'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$sql = "SELECT a.*, u.full_name AS user_name, u.username
        FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE 1=1";
$params = [];
if ($fAction !== '') { $sql .= " AND a.action = ?"; $params[] = $fAction; }
if ($fUser > 0)      { $sql .= " AND a.user_id = ?"; $params[] = $fUser; }
if ($from !== '')    { $sql .= " AND a.created_at >= ?"; $params[] = $from . ' 00:00:00'; }
if ($to !== '')      { $sql .= " AND a.created_at <= ?"; $params[] = $to . ' 23:59:59'; }

$total = (int)(dbFetchOne("SELECT COUNT(*) c FROM ($sql) x", $params)['c'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$rows  = dbFetchAll($sql . " ORDER BY a.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);

$actions = array_column(dbFetchAll("SELECT DISTINCT action FROM audit_log ORDER BY action"), 'action');
$users   = dbFetchAll("SELECT id, username FROM users ORDER BY username");

$pretty = function (?string $json): string {
    if (!$json) return '—';

    $d = json_decode($json, true);
    $formatted = json_last_error() === JSON_ERROR_NONE
        ? json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        : $json;

    $id = 'audit-details-' . bin2hex(random_bytes(4));

    return '<details class="audit-details">'
         . '<summary class="small text-primary" role="button"><i class="fas fa-eye me-1"></i>عرض التفاصيل</summary>'
         . '<pre id="' . e($id) . '" class="mb-0 small mt-2 p-2 border rounded bg-light" dir="ltr" style="max-height:220px;overflow:auto">'
         . e((string)$formatted) . '</pre>'
         . '</details>';
};

$qs = fn(array $extra) => APP_URL . 'modules/logs/audit.php?' . http_build_query(array_merge($_GET, $extra));

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><?php echo e(t('navigation.audit_log')); ?></h2>
    <p><?php echo $total; ?> <?php echo e(t('common.completed')); ?></p>
</div>

<?php if ($deleteMessage): ?>
<div class="alert alert-<?php echo e($deleteMessage['type']); ?> fade-in" role="alert">
    <?php echo e($deleteMessage['text']); ?>
</div>
<?php endif; ?>

<?php if (Session::getUserRole() === 'admin'): ?>
<div class="card mb-3 border-danger fade-in">
    <div class="card-header text-danger fw-bold">
        <i class="fas fa-trash-can me-2"></i>تنظيف سجلات التدقيق القديمة
    </div>
    <div class="card-body">
        <form method="post" class="row g-2 align-items-end" onsubmit="return confirm('سيتم حذف جميع سجلات التدقيق حتى التاريخ المحدد نهائيًا. هل تريد المتابعة؟');">
            <input type="hidden" name="audit_action" value="delete_old">
            <div class="col-md-4">
                <label class="form-label">حذف السجلات حتى تاريخ</label>
                <input type="date" name="delete_before" class="form-control" required>
            </div>
            <div class="col-md-4">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="confirm_delete" value="1" id="confirmAuditDelete" required>
                    <label class="form-check-label text-danger" for="confirmAuditDelete">أؤكد حذف جميع سجلات التدقيق حتى التاريخ المحدد</label>
                </div>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-danger w-100">
                    <i class="fas fa-trash-can me-1"></i>حذف السجلات القديمة
                </button>
            </div>
        </form>
        <div class="form-text text-danger mt-2">هذه العملية نهائية. مدير النظام فقط يستطيع تنفيذها، والمدير العام يستطيع عرض السجلات دون حذفها.</div>
    </div>
</div>
<?php endif; ?>

<div class="card mb-3 fade-in">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><?php echo e(t('common.actions')); ?></label>
                <select name="action" class="form-select">
                    <option value=""><?php echo e(t('common.all')); ?></option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?php echo e($a); ?>" <?php echo $fAction === $a ? 'selected' : ''; ?>><?php echo e($a); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo e(t('common.users')); ?></label>
                <select name="user" class="form-select">
                    <option value=""><?php echo e(t('common.all')); ?></option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>" <?php echo $fUser === (int)$u['id'] ? 'selected' : ''; ?>><?php echo e($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label"><?php echo e(t('common.previous')); ?></label><input type="date" name="from" class="form-control" value="<?php echo e($from); ?>"></div>
            <div class="col-md-2"><label class="form-label"><?php echo e(t('common.next')); ?></label><input type="date" name="to" class="form-control" value="<?php echo e($to); ?>"></div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-primary w-100" title="<?php echo e(t('common.search')); ?>"><i class="fas fa-search"></i></button>
                <a class="btn btn-secondary" href="<?php echo APP_URL; ?>modules/logs/audit.php" title="<?php echo e(t('common.clear')); ?>"><i class="fas fa-rotate-right"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead><tr><th>#</th><th><?php echo e(t('accounting.date')); ?></th><th><?php echo e(t('common.users')); ?></th><th><?php echo e(t('common.actions')); ?></th><th><?php echo e(t('common.status')); ?></th><th><?php echo e(t('common.previous')); ?></th><th><?php echo e(t('common.next')); ?></th><th>IP</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><?php echo e(t('common.no_data')); ?></td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo (int)$r['id']; ?></td>
                        <td><small><?php echo e($r['created_at']); ?></small></td>
                        <td><?php echo e($r['username'] ?? '—'); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo e($r['action']); ?></span></td>
                        <td><small><?php echo e($r['entity_type']); ?> #<?php echo e((string)($r['entity_id'] ?? '')); ?></small></td>
                        <td><?php echo $pretty($r['old_values']); ?></td>
                        <td><?php echo $pretty($r['new_values']); ?></td>
                        <td><small dir="ltr"><?php echo e($r['ip_address'] ?? ''); ?></small></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="mt-2">
                <ul class="pagination pagination-sm justify-content-center">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo e($qs(['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>