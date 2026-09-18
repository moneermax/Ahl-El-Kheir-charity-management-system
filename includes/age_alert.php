<?php
// includes/age_alert.php - v13: Reduced modal size & narrowed Family column
// Safe to include from dashboards AND footer: renders only once per request.
if (!isset($GLOBALS['ak_age_alert_rendered'])) {
$GLOBALS['ak_age_alert_rendered'] = true;

// Resolve role using the same alias map as header.php
$raw_role = (string)current_user_role();
$aliasMap = ['sudo' => 'admin', 'gm' => 'general_manager', 'vgm' => 'vice_general_manager', 'fm' => 'financial_manager'];
$resolved_role = $aliasMap[$raw_role] ?? $raw_role;

// Check if role should see the alert
if (in_array($resolved_role, ['nanny', 'admin', 'vice_general_manager', 'general_manager'], true)) {
    $akScopeSql = '';
    $akScopeParams = [];
    if ($resolved_role === 'nanny') {
        $akScopeSql = " AND f.nanny_id = ?";
        $akScopeParams[] = current_user_id();
    }

    $ageKids = [];
    try {
        $ageKids = dbFetchAll("
            SELECT fc.id, fc.child_name, fc.birth_date, f.mother_name, f.family_code, f.id as family_id
            FROM family_children fc
            INNER JOIN families f ON fc.family_id = f.id
            WHERE fc.is_active = 1
            AND fc.birth_date IS NOT NULL
            AND fc.birth_date <= DATE_SUB(DATE_ADD(CURDATE(), INTERVAL 90 DAY), INTERVAL 18 YEAR)
            AND fc.birth_date >= DATE_SUB(CURDATE(), INTERVAL 21 YEAR)
            {$akScopeSql}
            ORDER BY fc.birth_date ASC
        ", $akScopeParams);
    } catch (Throwable $e) {
        // Silently fail if query error
    }

    $akAgeCount = count($ageKids);
    if ($akAgeCount > 0):
?>
<style>
#ageAlertModal .legend-container {
    padding: 10px 15px;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    position: sticky;
    top: 0;
    z-index: 100;
}
#ageAlertModal .table-responsive {
    max-height: 60vh;
    overflow-y: auto;
}
#ageAlertModal .table thead th {
    position: sticky;
    top: 0;
    background-color: #e9ecef;
    z-index: 50;
    border-bottom: 2px solid #adb5bd;
    white-space: nowrap;
}
</style>

<!-- Age Alert Modal -->
<div class="modal fade" id="ageAlertModal" tabindex="-1" aria-hidden="true">
    <!-- Changed from modal-xl to modal-lg to reduce overall width -->
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo t('تنبيه: أطفال بلغوا أو سيبلغون السن القانوني خلال 90 يوماً'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                
                <!-- Color Legend (Sticky at top) -->
                <div class="legend-container">
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-3 py-2">
                            <i class="fas fa-circle-exclamation me-1"></i>
                            <?php echo t('بلغ السن اليوم أو سابقاً'); ?>
                        </span>
                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-3 py-2">
                            <i class="fas fa-clock me-1"></i>
                            <?php echo t('خلال أسبوع'); ?>
                        </span>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info px-3 py-2">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo t('خلال شهر'); ?>
                        </span>
                        <span class="badge bg-light text-secondary border border-secondary px-3 py-2">
                            <i class="fas fa-calendar-day me-1"></i>
                            <?php echo t('خلال 90 يوم'); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Table with fixed layout to enforce column widths -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" style="table-layout: fixed;">
                        <thead>
                            <tr>
                                <th style="width: 20%;"><?php echo t('اسم الطفل'); ?></th>
                                <th style="width: 45%;"><?php echo t('الأسرة'); ?></th>
                                <th style="width: 20%;"><?php echo t('تاريخ الميلاد'); ?></th>
                                <th class="text-center" style="width: 20%;"><?php echo t('إجراء'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ageKids as $k): ?>
                            <?php
                            $daysDiff = 999;
                            try {
                                $birthDate = new DateTime($k['birth_date']);
                                $legalDate = clone $birthDate;
                                $legalDate->modify('+18 years');
                                $today = new DateTime(date('Y-m-d'));
                                $interval = $today->diff($legalDate);
                                $daysDiff = $interval->invert ? -$interval->days : $interval->days;
                            } catch (Exception $e) {
                                // fallback
                            }

                            $rowClass = '';
                            if ($daysDiff <= 0) {
                                $rowClass = 'table-danger';
                            } elseif ($daysDiff <= 7) {
                                $rowClass = 'table-warning';
                            } elseif ($daysDiff <= 30) {
                                $rowClass = 'table-info';
                            }
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td><strong><?php echo e($k['child_name']); ?></strong></td>
                                <td style="overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo e($k['mother_name']); ?> 
                                    <small class="text-muted d-block">(<?php echo e($k['family_code']); ?>)</small>
                                </td>
                                <td><?php echo e($k['birth_date']); ?></td>
                                <td class="text-center text-nowrap">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?php echo APP_URL; ?>modules/families/view.php?id=<?php echo (int)$k['family_id']; ?>" 
                                           class="btn btn-outline-primary" 
                                           target="_blank" 
                                           title="<?php echo t('عرض التفاصيل'); ?>">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo APP_URL; ?>modules/families/suspensions.php?type=child&id=<?php echo (int)$k['id']; ?>" 
                                           class="btn btn-outline-danger" 
                                           title="<?php echo t('تعليق الكفالة'); ?>">
                                            <i class="fas fa-hand me-1"></i><?php echo t('تعليق'); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('إغلاق التنبيه'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var existingBtn = document.getElementById('akAgeReopenBtn');
    if (existingBtn) {
        existingBtn.remove();
    }

    var btn = document.createElement('button');
    btn.id = 'akAgeReopenBtn';
    btn.className = 'qa-btn position-relative';
    btn.style.cssText = 'background: #ffc107; color: #000 !important; font-weight: 700;';
    btn.innerHTML = '<i class="fas fa-user-clock me-1"></i><span class="d-none d-md-inline"><?php echo t('بلغوا السن'); ?></span><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;"><?php echo $akAgeCount; ?></span>';
    btn.title = '<?php echo t('أطفال بلغوا السن القانوني'); ?>';

    btn.addEventListener('click', function() {
        var modal = new bootstrap.Modal(document.getElementById('ageAlertModal'));
        modal.show();
    });

    var modalEl = document.getElementById('ageAlertModal');

    // Keep the alert available in the header after the modal is closed.
    // Insert it immediately so it never disappears from the header.
    var langSwitchBtn = document.querySelector('.qa-user-controls a[href*="lang="]');
    if (langSwitchBtn) {
        langSwitchBtn.insertAdjacentElement('beforebegin', btn);
    } else {
        var userControls = document.querySelector('.qa-user-controls');
        if (userControls) {
            userControls.insertBefore(btn, userControls.firstChild);
        }
    }

    // The quick-action button remains available after the modal is closed.
    // localStorage prevents the automatic popup from returning on refreshes
    // or in additional tabs in the same browser.
    var modalShownKey = 'ak_age_alert_modal_shown_<?php echo (int)current_user_id(); ?>';
    if (!localStorage.getItem(modalShownKey)) {
        localStorage.setItem(modalShownKey, '1');
        var modal = new bootstrap.Modal(modalEl);
        setTimeout(function() {
            modal.show();
        }, 500);
    }
});
</script>
<?php endif; ?>
<?php
    }
}
?>