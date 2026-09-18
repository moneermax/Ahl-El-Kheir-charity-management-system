<?php
// modules/notifications/index.php - Full notification history for the current user
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'login.php'); exit; }

$pageTitle = 'الإشعارات';
$active = 'notifications';
$uid = current_user_id();
$perPage = 30;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalRow = dbFetchOne('SELECT COUNT(*) AS total FROM notifications WHERE recipient_user_id = ?', [$uid]);
$total = (int)($totalRow['total'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$items = dbFetchAll(
    'SELECT id, title, body, link, created_at, is_read FROM notifications WHERE recipient_user_id = ? ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
    [$uid]
);
$unreadRow = dbFetchOne('SELECT COUNT(*) AS unread FROM notifications WHERE recipient_user_id = ? AND is_read = 0', [$uid]);
$unread = (int)($unreadRow['unread'] ?? 0);

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="mb-1"><i class="fas fa-bell me-2"></i>الإشعارات</h2>
            <div class="text-muted small">إجمالي الإشعارات: <?php echo $total; ?> — غير المقروء: <?php echo $unread; ?></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($unread > 0): ?>
            <form method="post" action="<?php echo e(APP_URL); ?>modules/notifications/mark_all_read.php" class="m-0">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="redirect" value="<?php echo e(APP_URL); ?>modules/notifications/index.php">
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-check-double me-1"></i>تحديد الكل كمقروء</button>
            </form>
            <?php endif; ?>
            <?php if ($total > 0): ?>
            <form method="post" action="<?php echo e(APP_URL); ?>modules/notifications/clear_all.php" class="m-0" onsubmit="return confirm('هل أنت متأكد من حذف جميع الإشعارات القديمة؟');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="redirect" value="<?php echo e(APP_URL); ?>modules/notifications/index.php">
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash-can me-1"></i>حذف الكل</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if (!$items): ?>
                <div class="text-center text-muted py-5"><i class="fas fa-bell-slash fa-2x mb-3"></i><div>لا توجد إشعارات.</div></div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                <?php foreach ($items as $item):
                    $id = (int)($item['id'] ?? 0);
                    $isUnread = (int)($item['is_read'] ?? 0) === 0;
                    $link = trim((string)($item['link'] ?? ''));
                    if ($link === '' && ($item['title'] ?? '') === 'تم إرجاع تحصيل فينا الخير') {
                        $body = (string)($item['body'] ?? '');
                        if (preg_match('/رقم\s*#(\d+)/u', $body, $m)) {
                            $link = APP_URL . 'modules/accounting/fina_payment_history.php?id=' . (int)$m[1];
                        }
                    }
                    $redirect = $link !== '' ? $link : APP_URL . 'modules/notifications/index.php';
                ?>
                    <form method="post" action="<?php echo e(APP_URL); ?>modules/notifications/mark_read.php" class="m-0">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="notification_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="redirect" value="<?php echo e($redirect); ?>">
                        <button type="submit" class="list-group-item list-group-item-action text-start border-0 border-bottom py-3 <?php echo $isUnread ? 'bg-warning-subtle' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <strong><?php echo e($item['title'] ?? ''); ?></strong>
                                    <?php if ($isUnread): ?><span class="badge bg-warning text-dark ms-2">جديد</span><?php endif; ?>
                                    <div class="small text-muted mt-1"><?php echo e($item['body'] ?? ''); ?></div>
                                </div>
                                <small class="text-muted text-nowrap"><?php echo e($item['created_at'] ?? ''); ?></small>
                            </div>
                        </button>
                    </form>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="mt-3" aria-label="صفحات الإشعارات"><ul class="pagination justify-content-center flex-wrap">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="<?php echo e(APP_URL); ?>modules/notifications/index.php?page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>