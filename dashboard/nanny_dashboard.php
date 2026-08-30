<?php
// dashboard/nanny_dashboard.php - Nanny workspace
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';
require_once dirname(__DIR__) . '/config/session.php';

Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'nanny') {
    header('Location: ' . APP_URL . dashboard_for_role(Session::getUserRole()));
    exit();
}

$pageTitle = 'لوحة أخصائية شؤون الأمهات';
$active = 'dashboard';
$uid = Session::getUserId();

$me = dbFetchOne("SELECT u.*, r.name_ar AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?", [$uid]);

// Stats
$stats = dbFetchOne("SELECT
    COUNT(DISTINCT f.id) AS total_families,
    COUNT(DISTINCT fc.id) AS total_children,
    COALESCE(SUM(CASE WHEN sp.status = 'active' THEN sp.monthly_amount ELSE 0 END), 0) AS total_monthly
FROM families f
LEFT JOIN family_children fc ON fc.family_id = f.id AND fc.is_active = 1
LEFT JOIN sponsorships sp ON sp.child_id = fc.id AND sp.status = 'active'
WHERE f.nanny_id = ? AND f.status IN ('active', 'pending')", [$uid]);

// Verification progress for the current month
$ver = dbFetchOne("SELECT
    COUNT(*) AS fully_verified
    FROM nanny_family_verifications
    WHERE nanny_id = ? AND month = ?
    AND is_orphan_verified = 1 AND is_mother_contact_verified = 1 AND is_bank_verified = 1",
    [$uid, date('Y-m')]);

// Upcoming age-outs
$ageOuts = dbFetchAll("SELECT fc.child_name, fc.birth_date, f.mother_name, f.family_code, f.id as family_id
FROM family_children fc
JOIN families f ON f.id = fc.family_id
WHERE f.nanny_id = ? AND fc.is_active = 1 AND fc.birth_date IS NOT NULL
AND fc.birth_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 18 YEAR) AND DATE_SUB(CURDATE(), INTERVAL 17 YEAR)
ORDER BY fc.birth_date ASC", [$uid]);

// Recent families
$families = dbFetchAll("SELECT f.id, f.family_code, f.mother_name, f.status, f.children_count
FROM families f
WHERE f.nanny_id = ?
ORDER BY f.updated_at DESC
LIMIT 10", [$uid]);

// ==========================================
// NEW: Pending confirmations for nanny
// ==========================================
$pending_batches = dbFetchAll("
    SELECT 
        d.id as disbursement_id,
        d.month,
        d.total_amount,
        d.receipt_file_path,
        og.group_name,
        (SELECT COUNT(*) FROM disbursement_items WHERE disbursement_id = d.id) as total_families,
        (SELECT COUNT(*) FROM disbursement_items WHERE disbursement_id = d.id AND status = 'paid') as confirmed_families,
        (SELECT COUNT(*) FROM disbursement_items WHERE disbursement_id = d.id AND status = 'pending') as pending_families
    FROM monthly_disbursements d
    LEFT JOIN orphan_groups og ON og.id = d.group_id
    WHERE d.nanny_id = ? AND d.status = 'transferred'
    ORDER BY d.created_at DESC
", [$uid]);

$total_pending_families = array_sum(array_column($pending_batches, 'pending_families'));
$total_families_transferred = array_sum(array_column($pending_batches, 'total_families'));
$total_confirmed_families = array_sum(array_column($pending_batches, 'confirmed_families'));

// Calculate overall progress
$overall_progress = $total_families_transferred > 0 
    ? round(($total_confirmed_families / $total_families_transferred) * 100) 
    : 0;

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2>مرحباً، <?php echo e($me['full_name']); ?></h2>
    <p>لوحة أخصائية شؤون الأمهات - إدارة الأسر والأطفال المكفولين</p>
    <div class="quick-actions mt-3">
        <a href="<?php echo url('modules/accounting/group_verification.php'); ?>" class="btn btn-info btn-sm me-2">
            <i class="fas fa-clipboard-check me-1"></i> توثيق المجموعات
        </a>
        <a href="<?php echo url('modules/accounting/disbursements.php'); ?>" class="btn btn-primary btn-sm me-2">
            <i class="fas fa-money-check-dollar me-1"></i> التحويلات الشهرية
        </a>
        <a href="<?php echo url('modules/transactions/sponsor-monthly-report.php'); ?>" class="btn btn-danger btn-sm">
            <i class="fas fa-file-pdf me-1"></i> تقارير الكفلاء (PDF)
        </a>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/alerts.php'; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card fade-in border-primary">
            <div class="card-body text-center">
                <i class="fas fa-house-chimney fa-2x text-primary mb-2"></i>
                <h3><?php echo (int)($stats['total_families'] ?? 0); ?></h3>
                <p class="text-muted mb-0">الأسر التابعة لك</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card fade-in border-success">
            <div class="card-body text-center">
                <i class="fas fa-children fa-2x text-success mb-2"></i>
                <h3><?php echo (int)($stats['total_children'] ?? 0); ?></h3>
                <p class="text-muted mb-0">الأطفال المكفولون</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card fade-in border-warning">
            <div class="card-body text-center">
                <i class="fas fa-hand-holding-dollar fa-2x text-warning mb-2"></i>
                <h3><?php echo e(number_format((float)($stats['total_monthly'] ?? 0), 0)); ?></h3>
                <p class="text-muted mb-0">الكفالات الشهرية النشطة</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card fade-in border-info">
            <div class="card-body text-center">
                <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                <h3><?php echo (int)($ver['fully_verified'] ?? 0); ?></h3>
                <p class="text-muted mb-0">تم التوثيق هذا الشهر</p>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- NEW: Pending Confirmations Card -->
<!-- ========================================== -->
<div class="card fade-in border-warning mb-4">
    <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-clock me-2 text-warning"></i> 
            <strong>دفعات في انتظار تأكيد الاستلام</strong>
            <span class="badge bg-warning text-dark ms-2"><?php echo count($pending_batches); ?></span>
        </div>
        <?php if (!empty($pending_batches)): ?>
        <span class="badge bg-primary">التقدم: <?php echo $overall_progress; ?>%</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($pending_batches)): ?>
            <p class="text-muted text-center py-3 mb-0">
                <i class="fas fa-check-circle text-success fa-2x d-block mb-2"></i>
                🎉 لا توجد دفعات في انتظار تأكيد الاستلام
            </p>
        <?php else: ?>
            <!-- Overall progress bar -->
            <div class="mb-4 p-3 bg-light rounded">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>التقدم الإجمالي</strong>
                    <span><?php echo (int)$total_confirmed_families; ?>/<?php echo (int)$total_families_transferred; ?> عائلة</span>
                </div>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar <?php echo $overall_progress == 100 ? 'bg-success' : 'bg-info'; ?>" 
                         style="width: <?php echo $overall_progress; ?>%;">
                        <?php echo $overall_progress; ?>%
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>الشهر</th>
                            <th>المجموعة</th>
                            <th>المبلغ</th>
                            <th>التقدم</th>
                            <th>الحالة</th>
                            <th>إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_batches as $batch): 
                            $progress = $batch['total_families'] > 0 
                                ? round(($batch['confirmed_families'] / $batch['total_families']) * 100) 
                                : 0;
                            $isComplete = ($batch['confirmed_families'] == $batch['total_families'] && $batch['total_families'] > 0);
                            $pendingCount = (int)$batch['pending_families'];
                        ?>
                        <tr class="<?php echo $isComplete ? 'table-success' : ''; ?>">
                            <td><?php echo e($batch['month']); ?></td>
                            <td><?php echo e($batch['group_name'] ?? '—'); ?></td>
                            <td><?php echo number_format((float)$batch['total_amount'], 0); ?> ج.س</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 8px; min-width: 80px;">
                                        <div class="progress-bar <?php echo $isComplete ? 'bg-success' : ($progress > 0 ? 'bg-info' : 'bg-secondary'); ?>" 
                                             style="width: <?php echo $progress; ?>%;">
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo (int)$batch['confirmed_families']; ?>/<?php echo (int)$batch['total_families']; ?></small>
                                </div>
                                <?php if ($pendingCount > 0): ?>
                                    <small class="text-warning d-block">
                                        ⏳ <?php echo $pendingCount; ?> عائلة في انتظار التأكيد
                                    </small>
                                <?php endif; ?>
                                <?php if ($isComplete): ?>
                                    <span class="badge bg-success mt-1">✅ جميع العائلات مؤكدة</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isComplete): ?>
                                    <span class="badge bg-success">جاهز للإقفال</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">قيد التأكيد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo APP_URL; ?>modules/accounting/disbursements.php?view=<?php echo (int)$batch['disbursement_id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> عرض وتأكيد
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Age Outs & Recent Families -->
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card fade-in h-100">
            <div class="card-header">
                <i class="fas fa-birthday-cake me-2"></i>يقتربون من السن القانوني (18)
            </div>
            <div class="card-body p-0">
                <?php if (empty($ageOuts)): ?>
                    <p class="text-muted text-center py-4 mb-0">لا يوجد أطفال في هذه الفئة حالياً.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>اليتيم</th>
                                    <th>الأسرة</th>
                                    <th>تاريخ الميلاد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ageOuts as $a): ?>
                                    <tr>
                                        <td><?php echo e($a['child_name']); ?></td>
                                        <td><a href="<?php echo url('modules/families/view.php?id=' . $a['family_id']); ?>"><?php echo e($a['mother_name']); ?></a></td>
                                        <td><?php echo e($a['birth_date']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card fade-in h-100">
            <div class="card-header">
                <i class="fas fa-list me-2"></i>آخر الأسر المحدثة
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>الكود</th>
                                <th>اسم الأم</th>
                                <th>الأطفال</th>
                                <th>الحالة</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($families)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">لا توجد أسر معينة لك بعد.</td></tr>
                            <?php else: foreach ($families as $f): ?>
                                <tr>
                                    <td><?php echo e($f['family_code']); ?></td>
                                    <td><strong><?php echo e($f['mother_name']); ?></strong></td>
                                    <td><?php echo (int)$f['children_count']; ?></td>
                                    <td>
                                        <?php
                                        $st = ['pending' => ['قيد الانتظار','bg-warning'], 'active' => ['نشطة','bg-success'], 'paused' => ['متوقفة','bg-secondary']];
                                        [$sl, $sc] = $st[$f['status']] ?? [$f['status'], 'bg-secondary'];
                                        ?>
                                        <span class="badge <?php echo $sc; ?>"><?php echo $sl; ?></span>
                                    </td>
                                    <td><a href="<?php echo url('modules/families/view.php?id=' . $f['id']); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/age_alert.php'; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>