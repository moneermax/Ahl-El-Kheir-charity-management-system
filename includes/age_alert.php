<?php
// includes/age_alert.php - v4: FINAL CORRECTED VERSION
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
                AND TIMESTAMPDIFF(YEAR, fc.birth_date, CURDATE()) >= 18
                AND TIMESTAMPDIFF(YEAR, fc.birth_date, CURDATE()) <= 21
                {$akScopeSql}
                ORDER BY fc.birth_date DESC
            ", $akScopeParams);
        } catch (Throwable $e) {
            // Silently fail if query error
        }
        
        $akAgeCount = count($ageKids);
        
        if ($akAgeCount > 0):
        ?>
        <!-- Age Alert Modal -->
        <div class="modal fade" id="ageAlertModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo t('تنبيه: أطفال بلغوا أو سيبلغون السن القانوني خلال 90 يوماً'); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th><?php echo t('اسم الطفل'); ?></th>
                                        <th><?php echo t('الأسرة'); ?></th>
                                        <th><?php echo t('تاريخ الميلاد'); ?></th>
                                        <th class="text-center"><?php echo t('إجراء'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ageKids as $k): ?>
                                    <tr>
                                        <td><strong><?php echo e($k['child_name']); ?></strong></td>
                                        <td><?php echo e($k['mother_name']); ?> <small class="text-muted">(<?php echo e($k['family_code']); ?>)</small></td>
                                        <td><?php echo e($k['birth_date']); ?></td>
                                        <td class="text-center">
                                            <a href="<?php echo APP_URL; ?>modules/families/suspensions.php?type=child&id=<?php echo (int)$k['id']; ?>" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-hand me-1"></i><?php echo t('تعليق الكفالة'); ?>
                                            </a>
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
            // Remove any existing age alert button to prevent duplicates
            var existingBtn = document.getElementById('akAgeReopenBtn');
            if (existingBtn) {
                existingBtn.remove();
            }
            
            // Create the reopen button
            var btn = document.createElement('button');
            btn.id = 'akAgeReopenBtn';
            btn.className = 'qa-btn position-relative';
            btn.style.cssText = 'background: #ffc107; color: #000 !important; font-weight: 700;';
            btn.innerHTML = '<i class="fas fa-user-clock me-1"></i><span class="d-none d-md-inline"><?php echo t('بلغوا السن'); ?></span><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;"><?php echo $akAgeCount; ?></span>';
            btn.title = '<?php echo t('أطفال بلغوا السن القانوني'); ?>';
            
            // Add click handler to reopen modal
            btn.addEventListener('click', function() {
                var modal = new bootstrap.Modal(document.getElementById('ageAlertModal'));
                modal.show();
            });
            
            // Show button only after modal is hidden
            var modalEl = document.getElementById('ageAlertModal');
            modalEl.addEventListener('hidden.bs.modal', function () {
                // Find the language switch button
                var langSwitchBtn = document.querySelector('.qa-user-controls a[href*="lang="]');
                if (langSwitchBtn) {
                    // FIXED: Use 'beforebegin' to place it visually to the RIGHT in RTL, and LEFT in LTR
                    langSwitchBtn.insertAdjacentElement('beforebegin', btn);
                }
            });
            
            // Auto-show modal on page load (first time per browser session only)
            // FIXED: Use constant key name (no Date.now()) to remember across page loads
            var modalShownKey = 'ageAlertModalShown';
            if (!sessionStorage.getItem(modalShownKey)) {
                sessionStorage.setItem(modalShownKey, '1');
                var modal = new bootstrap.Modal(modalEl);
                setTimeout(function() {
                    modal.show();
                }, 500); // Small delay to ensure page is fully loaded
            }
        });
        </script>
        <?php endif; ?>
    <?php
    }
}
?>