<?php
// modules/administration/available_families.php - Families currently available for sponsorship assignment
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }

$role = Session::getUserRole();
if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager', 'administration'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$pageTitle = t('الأسر المتاحة للتكليف');
$active = 'winback';

$families = dbFetchAll("SELECT f.id, f.family_code, f.mother_name, f.children_count, f.monthly_need_amount, f.city
    FROM families f
    WHERE f.status IN ('active','pending')
      AND NOT EXISTS (
          SELECT 1
          FROM sponsorships sp
          JOIN family_children fc ON fc.id = sp.child_id
          WHERE fc.family_id = f.id
            AND sp.status = 'active'
      )
    ORDER BY f.children_count DESC, f.monthly_need_amount DESC");

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h2><i class="fas fa-house-circle-check me-2"></i><?php echo t('الأسر المتاحة للتكليف'); ?></h2>
            <p><?php echo t('الأسر النشطة أو قيد الإجراء التي لا توجد لها كفالة نشطة حالياً.'); ?></p>
        </div>
        <a href="<?php echo APP_URL; ?>modules/administration/winback.php#uncovered" class="btn btn-outline-success">
            <i class="fas fa-arrow-right me-1"></i><?php echo t('العودة إلى متابعات الاسترجاع'); ?>
        </a>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="card fade-in border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i><?php echo t('قائمة الأسر المتاحة حالياً'); ?></span>
        <span class="badge bg-light text-success"><?php echo count($families); ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:620px; overflow-y:auto;">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-success" style="position:sticky; top:0; z-index:2;">
                    <tr>
                        <th><?php echo t('اسم الأم'); ?></th>
                        <th class="text-center"><?php echo t('الأطفال'); ?></th>
                        <th class="text-center"><?php echo t('الاحتياج الشهري'); ?></th>
                        <th class="text-center"><?php echo t('إجراء'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$families): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4"><?php echo t('لا توجد أسر متاحة حالياً.'); ?></td></tr>
                <?php else: foreach ($families as $family): ?>
                    <tr>
                        <td>
                            <strong><?php echo e($family['mother_name']); ?></strong><br>
                            <small class="text-muted"><?php echo e($family['family_code']); ?><?php echo !empty($family['city']) ? ' — ' . e($family['city']) : ''; ?></small>
                        </td>
                        <td class="text-center"><span class="badge bg-warning text-dark"><?php echo (int)$family['children_count']; ?></span></td>
                        <td class="text-center"><?php echo number_format((float)$family['monthly_need_amount'], 0); ?></td>
                        <td class="text-center">
                            <a href="<?php echo APP_URL; ?>modules/administration/sponsorship_family_view.php?id=<?php echo (int)$family['id']; ?>" class="btn btn-sm btn-outline-primary" title="<?php echo t('عرض ملف الكفالة'); ?>">
                                <i class="fas fa-eye me-1"></i><?php echo t('عرض'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>