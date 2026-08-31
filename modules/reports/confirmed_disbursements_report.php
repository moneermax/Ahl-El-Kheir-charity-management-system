<<<<<<< HEAD
<?php
// modules/reports/confirmed_disbursements_report.php - Confirmed Disbursements Report
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
$role = Session::getUserRole();

// Allow nannies to see their own reports
if (!Session::isLoggedIn() || !in_array($role, ['admin', 'accountant', 'accountant_staff', 'nanny'])) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'تقرير التحويلات المؤكدة';
$active = 'reports';

// Get filters
$monthFrom = $_GET['month_from'] ?? date('Y-m');
$monthTo = $_GET['month_to'] ?? date('Y-m');
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;
$nannyId = isset($_GET['nanny_id']) ? (int)$_GET['nanny_id'] : null;

// Build query
$sql = "
SELECT 
    d.id as disbursement_id,
    d.month,
    d.total_amount,
    d.status,
    d.created_at,
    d.transferred_at,
    og.group_name,
    og.id as group_id,
    u.full_name as nanny_name,
    u.id as nanny_id,
    COUNT(i.id) as total_items,
    SUM(CASE WHEN i.status = 'paid' THEN 1 ELSE 0 END) as confirmed_items,
    SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) as pending_items,
    SUM(CASE WHEN i.status = 'paid' THEN i.amount ELSE 0 END) as confirmed_amount
FROM monthly_disbursements d
LEFT JOIN orphan_groups og ON og.id = d.group_id
LEFT JOIN users u ON u.id = d.nanny_id
LEFT JOIN disbursement_items i ON i.disbursement_id = d.id
WHERE d.status IN ('transferred', 'received')
AND d.month BETWEEN ? AND ?
";

$params = [$monthFrom, $monthTo];

// If user is a nanny, restrict to their own data only
if ($role === 'nanny') {
    $sql .= " AND d.nanny_id = ?";
    $params[] = Session::getUserId();
} else {
    if ($groupId) {
        $sql .= " AND og.id = ?";
        $params[] = $groupId;
    }
    if ($nannyId) {
        $sql .= " AND d.nanny_id = ?";
        $params[] = $nannyId;
    }
}

$sql .= " GROUP BY d.id, d.month, d.total_amount, d.status, d.created_at, d.transferred_at, og.group_name, og.id, u.full_name, u.id";
$sql .= " ORDER BY d.month DESC, og.group_name";

$disbursements = dbFetchAll($sql, $params);

// Get groups and nannies for filters (only for non-nanny roles)
$groups = [];
$nannies = [];
if ($role !== 'nanny') {
    $groups = dbFetchAll("SELECT id, group_name FROM orphan_groups ORDER BY group_name");
    $nannies = dbFetchAll("SELECT u.id, u.full_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code = 'nanny' ORDER BY u.full_name");
}

// Calculate statistics
$totalConfirmed = 0;
$totalPending = 0;
$totalConfirmedAmount = 0;
foreach ($disbursements as $d) {
    $totalConfirmed += (int)$d['confirmed_items'];
    $totalPending += (int)$d['pending_items'];
    $totalConfirmedAmount += (float)$d['confirmed_amount'];
}

$confirmationRate = ($totalConfirmed + $totalPending) > 0 
    ? round(($totalConfirmed / ($totalConfirmed + $totalPending)) * 100) 
    : 0;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-check-circle me-2 text-success"></i>تقرير التحويلات المؤكدة</h2>
    <p>تتبع التحويلات المؤكدة والمستلمة من قبل الأخصائيات</p>
</div>

<div class="card mb-4 fade-in">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>تصفية التقرير</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">من شهر</label>
                <input type="month" name="month_from" class="form-control" value="<?php echo e($monthFrom); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">إلى شهر</label>
                <input type="month" name="month_to" class="form-control" value="<?php echo e($monthTo); ?>">
            </div>
            <?php if ($role !== 'nanny'): ?>
            <div class="col-md-3">
                <label class="form-label">المجموعة</label>
                <select name="group_id" class="form-select">
                    <option value="">جميع المجموعات</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?php echo (int)$g['id']; ?>" <?php echo ($groupId == $g['id']) ? 'selected' : ''; ?>>
                            <?php echo e($g['group_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">الأخصائية</label>
                <select name="nanny_id" class="form-select">
                    <option value="">جميع الأخصائيات</option>
                    <?php foreach ($nannies as $n): ?>
                        <option value="<?php echo (int)$n['id']; ?>" <?php echo ($nannyId == $n['id']) ? 'selected' : ''; ?>>
                            <?php echo e($n['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> عرض التقرير
                </button>
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
                    <i class="fas fa-print me-1"></i> طباعة
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Statistics -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-success fade-in">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h3><?php echo (int)$totalConfirmed; ?></h3>
                <p class="text-muted mb-0">عائلة مؤكدة</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning fade-in">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                <h3><?php echo (int)$totalPending; ?></h3>
                <p class="text-muted mb-0">في انتظار التأكيد</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-primary fade-in">
            <div class="card-body text-center">
                <i class="fas fa-percentage fa-2x text-primary mb-2"></i>
                <h3><?php echo (int)$confirmationRate; ?>%</h3>
                <p class="text-muted mb-0">نسبة التأكيد</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info fade-in">
            <div class="card-body text-center">
                <i class="fas fa-money-bill-wave fa-2x text-info mb-2"></i>
                <h3><?php echo number_format((float)$totalConfirmedAmount, 0); ?> ج.س</h3>
                <p class="text-muted mb-0">إجمالي المبالغ المؤكدة</p>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Report -->
<div class="card fade-in">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>تفاصيل التحويلات المؤكدة</h5>
    </div>
    <div class="card-body">
        <?php if (empty($disbursements)): ?>
            <p class="text-muted text-center py-4">لا توجد تحويلات مؤكدة للفترة المحددة</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>الشهر</th>
                            <th>المجموعة</th>
                            <th>الأخصائية</th>
                            <th>المبلغ الإجمالي</th>
                            <th>المبلغ المؤكد</th>
                            <th>التقدم</th>
                            <th>الحالة</th>
                            <th>تاريخ التحويل</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disbursements as $d): 
                            $progress = $d['total_items'] > 0 
                                ? round(($d['confirmed_items'] / $d['total_items']) * 100) 
                                : 0;
                            $isComplete = ($d['confirmed_items'] == $d['total_items'] && $d['total_items'] > 0);
                        ?>
                        <tr class="<?php echo $isComplete ? 'table-success' : ''; ?>">
                            <td><?php echo (int)$d['disbursement_id']; ?></td>
                            <td><?php echo e($d['month']); ?></td>
                            <td><?php echo e($d['group_name']); ?></td>
                            <td><?php echo e($d['nanny_name']); ?></td>
                            <td><?php echo number_format((float)$d['total_amount'], 0); ?> ج.س</td>
                            <td class="text-success"><?php echo number_format((float)$d['confirmed_amount'], 0); ?> ج.س</td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar <?php echo $isComplete ? 'bg-success' : 'bg-info'; ?>" 
                                         style="width: <?php echo $progress; ?>%;">
                                        <?php echo $progress; ?>%
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo (int)$d['confirmed_items']; ?>/<?php echo (int)$d['total_items']; ?> عائلة
                                </small>
                            </td>
                            <td>
                                <?php if ($d['status'] === 'received'): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check"></i> مستلم
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-primary">
                                        <i class="fas fa-paper-plane"></i> محوّل
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($d['transferred_at'] ?? '—'); ?></td>
                            <td>
                                <a href="<?php echo APP_URL; ?>modules/accounting/disbursements.php?view=<?php echo (int)$d['disbursement_id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> عرض
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">الإجمالي:</th>
                            <th><?php echo number_format(array_sum(array_column($disbursements, 'total_amount')), 0); ?> ج.س</th>
                            <th class="text-success"><?php echo number_format($totalConfirmedAmount, 0); ?> ج.س</th>
                            <th colspan="4"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

=======
<?php
// modules/reports/confirmed_disbursements_report.php - Confirmed Disbursements Report
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
$role = Session::getUserRole();

// Allow nannies to see their own reports
if (!Session::isLoggedIn() || !in_array($role, ['admin', 'accountant', 'accountant_staff', 'nanny'])) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'تقرير التحويلات المؤكدة';
$active = 'reports';

// Get filters
$monthFrom = $_GET['month_from'] ?? date('Y-m');
$monthTo = $_GET['month_to'] ?? date('Y-m');
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;
$nannyId = isset($_GET['nanny_id']) ? (int)$_GET['nanny_id'] : null;

// Build query
$sql = "
SELECT 
    d.id as disbursement_id,
    d.month,
    d.total_amount,
    d.status,
    d.created_at,
    d.transferred_at,
    og.group_name,
    og.id as group_id,
    u.full_name as nanny_name,
    u.id as nanny_id,
    COUNT(i.id) as total_items,
    SUM(CASE WHEN i.status = 'paid' THEN 1 ELSE 0 END) as confirmed_items,
    SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) as pending_items,
    SUM(CASE WHEN i.status = 'paid' THEN i.amount ELSE 0 END) as confirmed_amount
FROM monthly_disbursements d
LEFT JOIN orphan_groups og ON og.id = d.group_id
LEFT JOIN users u ON u.id = d.nanny_id
LEFT JOIN disbursement_items i ON i.disbursement_id = d.id
WHERE d.status IN ('transferred', 'received')
AND d.month BETWEEN ? AND ?
";

$params = [$monthFrom, $monthTo];

// If user is a nanny, restrict to their own data only
if ($role === 'nanny') {
    $sql .= " AND d.nanny_id = ?";
    $params[] = Session::getUserId();
} else {
    if ($groupId) {
        $sql .= " AND og.id = ?";
        $params[] = $groupId;
    }
    if ($nannyId) {
        $sql .= " AND d.nanny_id = ?";
        $params[] = $nannyId;
    }
}

$sql .= " GROUP BY d.id, d.month, d.total_amount, d.status, d.created_at, d.transferred_at, og.group_name, og.id, u.full_name, u.id";
$sql .= " ORDER BY d.month DESC, og.group_name";

$disbursements = dbFetchAll($sql, $params);

// Get groups and nannies for filters (only for non-nanny roles)
$groups = [];
$nannies = [];
if ($role !== 'nanny') {
    $groups = dbFetchAll("SELECT id, group_name FROM orphan_groups ORDER BY group_name");
    $nannies = dbFetchAll("SELECT u.id, u.full_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.code = 'nanny' ORDER BY u.full_name");
}

// Calculate statistics
$totalConfirmed = 0;
$totalPending = 0;
$totalConfirmedAmount = 0;
foreach ($disbursements as $d) {
    $totalConfirmed += (int)$d['confirmed_items'];
    $totalPending += (int)$d['pending_items'];
    $totalConfirmedAmount += (float)$d['confirmed_amount'];
}

$confirmationRate = ($totalConfirmed + $totalPending) > 0 
    ? round(($totalConfirmed / ($totalConfirmed + $totalPending)) * 100) 
    : 0;

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-check-circle me-2 text-success"></i>تقرير التحويلات المؤكدة</h2>
    <p>تتبع التحويلات المؤكدة والمستلمة من قبل الأخصائيات</p>
</div>

<div class="card mb-4 fade-in">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>تصفية التقرير</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">من شهر</label>
                <input type="month" name="month_from" class="form-control" value="<?php echo e($monthFrom); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">إلى شهر</label>
                <input type="month" name="month_to" class="form-control" value="<?php echo e($monthTo); ?>">
            </div>
            <?php if ($role !== 'nanny'): ?>
            <div class="col-md-3">
                <label class="form-label">المجموعة</label>
                <select name="group_id" class="form-select">
                    <option value="">جميع المجموعات</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?php echo (int)$g['id']; ?>" <?php echo ($groupId == $g['id']) ? 'selected' : ''; ?>>
                            <?php echo e($g['group_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">الأخصائية</label>
                <select name="nanny_id" class="form-select">
                    <option value="">جميع الأخصائيات</option>
                    <?php foreach ($nannies as $n): ?>
                        <option value="<?php echo (int)$n['id']; ?>" <?php echo ($nannyId == $n['id']) ? 'selected' : ''; ?>>
                            <?php echo e($n['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> عرض التقرير
                </button>
                <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
                    <i class="fas fa-print me-1"></i> طباعة
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Statistics -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-success fade-in">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h3><?php echo (int)$totalConfirmed; ?></h3>
                <p class="text-muted mb-0">عائلة مؤكدة</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning fade-in">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                <h3><?php echo (int)$totalPending; ?></h3>
                <p class="text-muted mb-0">في انتظار التأكيد</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-primary fade-in">
            <div class="card-body text-center">
                <i class="fas fa-percentage fa-2x text-primary mb-2"></i>
                <h3><?php echo (int)$confirmationRate; ?>%</h3>
                <p class="text-muted mb-0">نسبة التأكيد</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info fade-in">
            <div class="card-body text-center">
                <i class="fas fa-money-bill-wave fa-2x text-info mb-2"></i>
                <h3><?php echo number_format((float)$totalConfirmedAmount, 0); ?> ج.س</h3>
                <p class="text-muted mb-0">إجمالي المبالغ المؤكدة</p>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Report -->
<div class="card fade-in">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>تفاصيل التحويلات المؤكدة</h5>
    </div>
    <div class="card-body">
        <?php if (empty($disbursements)): ?>
            <p class="text-muted text-center py-4">لا توجد تحويلات مؤكدة للفترة المحددة</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>الشهر</th>
                            <th>المجموعة</th>
                            <th>الأخصائية</th>
                            <th>المبلغ الإجمالي</th>
                            <th>المبلغ المؤكد</th>
                            <th>التقدم</th>
                            <th>الحالة</th>
                            <th>تاريخ التحويل</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disbursements as $d): 
                            $progress = $d['total_items'] > 0 
                                ? round(($d['confirmed_items'] / $d['total_items']) * 100) 
                                : 0;
                            $isComplete = ($d['confirmed_items'] == $d['total_items'] && $d['total_items'] > 0);
                        ?>
                        <tr class="<?php echo $isComplete ? 'table-success' : ''; ?>">
                            <td><?php echo (int)$d['disbursement_id']; ?></td>
                            <td><?php echo e($d['month']); ?></td>
                            <td><?php echo e($d['group_name']); ?></td>
                            <td><?php echo e($d['nanny_name']); ?></td>
                            <td><?php echo number_format((float)$d['total_amount'], 0); ?> ج.س</td>
                            <td class="text-success"><?php echo number_format((float)$d['confirmed_amount'], 0); ?> ج.س</td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar <?php echo $isComplete ? 'bg-success' : 'bg-info'; ?>" 
                                         style="width: <?php echo $progress; ?>%;">
                                        <?php echo $progress; ?>%
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo (int)$d['confirmed_items']; ?>/<?php echo (int)$d['total_items']; ?> عائلة
                                </small>
                            </td>
                            <td>
                                <?php if ($d['status'] === 'received'): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check"></i> مستلم
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-primary">
                                        <i class="fas fa-paper-plane"></i> محوّل
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($d['transferred_at'] ?? '—'); ?></td>
                            <td>
                                <a href="<?php echo APP_URL; ?>modules/accounting/disbursements.php?view=<?php echo (int)$d['disbursement_id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> عرض
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">الإجمالي:</th>
                            <th><?php echo number_format(array_sum(array_column($disbursements, 'total_amount')), 0); ?> ج.س</th>
                            <th class="text-success"><?php echo number_format($totalConfirmedAmount, 0); ?> ج.س</th>
                            <th colspan="4"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>