<?php
// modules/reports/lost_contact_report.php - Lost Contact Report
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
$role = Session::getUserRole();

// Allow nannies to see their own lost contact reports
if (!Session::isLoggedIn() || !in_array($role, ['admin', 'accountant', 'accountant_staff', 'nanny'])) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'تقرير فقدان التواصل';
$active = 'reports';

// Get filters
$monthFrom = $_GET['month_from'] ?? date('Y-m');
$monthTo = $_GET['month_to'] ?? date('Y-m');
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;

// Fetch lost contact cases
$sql = "
SELECT 
    d.id as disbursement_id,
    d.month,
    d.total_amount,
    d.status,
    d.created_at,
    og.group_name,
    og.id as group_id,
    u.full_name as nanny_name,
    u.id as nanny_id,
    i.id as item_id,
    i.amount as item_amount,
    i.status as item_status,
    f.family_code,
    f.mother_name,
    f.id as family_id,
    CASE 
        WHEN i.confirmed_at IS NULL THEN 'lost_contact'
        ELSE 'confirmed'
    END as contact_status
FROM monthly_disbursements d
LEFT JOIN orphan_groups og ON og.id = d.group_id
LEFT JOIN users u ON u.id = d.nanny_id
LEFT JOIN disbursement_items i ON i.disbursement_id = d.id
LEFT JOIN families f ON f.id = i.family_id
WHERE d.month BETWEEN ? AND ?
AND i.status = 'pending'
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
}

$sql .= " ORDER BY d.month DESC, og.group_name, f.mother_name";

$lostContacts = dbFetchAll($sql, $params);

// Get groups for filter (only for non-nanny roles)
$groups = [];
if ($role !== 'nanny') {
    $groups = dbFetchAll("SELECT id, group_name FROM orphan_groups ORDER BY group_name");
}

// Calculate statistics
$totalLostFamilies = count($lostContacts);
$totalLostAmount = array_sum(array_column($lostContacts, 'item_amount'));
$groupedData = [];
foreach ($lostContacts as $lc) {
    $key = $lc['group_id'] . '_' . $lc['month'];
    if (!isset($groupedData[$key])) {
        $groupedData[$key] = [
            'group_name' => $lc['group_name'],
            'month' => $lc['month'],
            'nanny_name' => $lc['nanny_name'],
            'families' => [],
            'total_amount' => 0
        ];
    }
    $groupedData[$key]['families'][] = $lc;
    $groupedData[$key]['total_amount'] += $lc['item_amount'];
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-exclamation-triangle me-2 text-warning"></i>تقرير فقدان التواصل</h2>
    <p>العائلات التي لم يتم تأكيد استلامها خلال الفترة المحددة</p>
</div>

<?php if ($role !== 'nanny'): ?>
<div class="card mb-4 fade-in">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>تصفية التقرير</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">من شهر</label>
                <input type="month" name="month_from" class="form-control" value="<?php echo e($monthFrom); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">إلى شهر</label>
                <input type="month" name="month_to" class="form-control" value="<?php echo e($monthTo); ?>">
            </div>
            <div class="col-md-4">
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
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> عرض
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Statistics -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-warning fade-in">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x text-warning mb-2"></i>
                <h3><?php echo (int)$totalLostFamilies; ?></h3>
                <p class="text-muted mb-0">عائلة فقدان تواصل</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-danger fade-in">
            <div class="card-body text-center">
                <i class="fas fa-money-bill-wave fa-2x text-danger mb-2"></i>
                <h3><?php echo number_format((float)$totalLostAmount, 0); ?> ج.س</h3>
                <p class="text-muted mb-0">إجمالي المبالغ</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info fade-in">
            <div class="card-body text-center">
                <i class="fas fa-folder-open fa-2x text-info mb-2"></i>
                <h3><?php echo count($groupedData); ?></h3>
                <p class="text-muted mb-0">دفعات متأثرة</p>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Report -->
<div class="card fade-in">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>تفاصيل فقدان التواصل</h5>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-print me-1"></i> طباعة
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($groupedData)): ?>
            <p class="text-muted text-center py-4">لا توجد حالات فقدان تواصل للفترة المحددة</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>الشهر</th>
                            <th>المجموعة</th>
                            <th>الأخصائية</th>
                            <th>كود الأسرة</th>
                            <th>اسم الأم</th>
                            <th>المبلغ</th>
                            <th>الحالة</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groupedData as $group): ?>
                            <?php foreach ($group['families'] as $family): ?>
                            <tr>
                                <td><?php echo e($family['month']); ?></td>
                                <td><?php echo e($family['group_name']); ?></td>
                                <td><?php echo e($family['nanny_name']); ?></td>
                                <td><?php echo e($family['family_code']); ?></td>
                                <td><strong><?php echo e($family['mother_name']); ?></strong></td>
                                <td class="text-danger"><?php echo number_format((float)$family['item_amount'], 0); ?> ج.س</td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-exclamation-triangle"></i> فقدان تواصل
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        لم يتم التأكيد حتى <?php echo date('Y-m-d'); ?>
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <!-- Group Summary Row -->
                            <tr class="table-warning">
                                <td colspan="5" class="text-end"><strong>إجمالي المجموعة - <?php echo e($group['month']); ?></strong></td>
                                <td colspan="3"><strong><?php echo number_format((float)$group['total_amount'], 0); ?> ج.س</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>