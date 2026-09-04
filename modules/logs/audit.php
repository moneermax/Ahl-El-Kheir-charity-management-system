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
    return '<pre class="mb-0 small" dir="ltr" style="max-height:160px;overflow:auto">'
         . e(json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
};

$qs = fn(array $extra) => APP_URL . 'modules/logs/audit.php?' . http_build_query(array_merge($_GET, $extra));

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><?php echo e(t('navigation.audit_log')); ?></h2>
    <p><?php echo $total; ?> <?php echo e(t('common.completed')); ?></p>
</div>

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
                <thead><tr><th>#</th><th><?php echo e(t('accounting.date')); ?></th><th><?php echo e(t('common.users')); ?></th><th><?php echo e(t('common.actions')); ?></th><th><?php echo e(t('common.status')); ?></th><th>Before</th><th>After</th><th>IP</th></tr></thead>
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