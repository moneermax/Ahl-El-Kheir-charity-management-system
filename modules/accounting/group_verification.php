<?php
// modules/accounting/group_verification.php - Nanny Group Verification
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/modules/accounting/lib_group_workflow.php';

Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'nanny') {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$uid = Session::getUserId();
$currentMonth = date('Y-m');
$success_message = '';
$error_message = '';

// Get selected group from URL
$selectedGroupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    
    // 1. Handle individual family verification
    if (isset($_POST['verify_family'])) {
        $family_id = (int)$_POST['family_id'];
        $is_orphan = isset($_POST['is_orphan']) ? 1 : 0;
        $is_mother = isset($_POST['is_mother']) ? 1 : 0;
        $is_bank = isset($_POST['is_bank']) ? 1 : 0;
        $notes = trim($_POST['notes'] ?? '');

        try {
            // Get old status for audit
            $old_status = get_latest_verification_status($family_id, $currentMonth, $uid);
            
            // Check if a record already exists for this family and month
            $existing = dbFetchOne("SELECT id FROM nanny_family_verifications WHERE family_id = ? AND month = ?", [$family_id, $currentMonth]);
            
            if ($existing) {
                // UPDATE existing record
                dbExecute("UPDATE nanny_family_verifications 
                           SET is_orphan_verified = ?, 
                               is_mother_contact_verified = ?, 
                               is_bank_verified = ?, 
                               verification_notes = ?, 
                               verified_by_user_id = ?, 
                               verified_at = NOW()
                           WHERE family_id = ? AND month = ?",
                          [$is_orphan, $is_mother, $is_bank, $notes, $uid, $family_id, $currentMonth]);
            } else {
                // INSERT new record
                dbExecute("INSERT INTO nanny_family_verifications 
                           (family_id, month, nanny_id, is_orphan_verified, is_mother_contact_verified, is_bank_verified, verification_notes, verified_by_user_id, verified_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                          [$family_id, $currentMonth, $uid, $is_orphan, $is_mother, $is_bank, $notes, $uid]);
            }
            
            // Get new status for audit
            $new_status = get_latest_verification_status($family_id, $currentMonth, $uid);
            
            // Log the change
            log_verification_change($family_id, $currentMonth, $uid, $old_status, $new_status);
            
            $success_message = 'تم حفظ بيانات التوثيق بنجاح.';
        } catch (Throwable $e) {
            $error_message = 'حدث خطأ: ' . $e->getMessage();
        }
    }

    // 2. Handle GROUP SUBMISSION to Accountant (Stage 1)
    if (isset($_POST['submit_group'])) {
        $gid = (int)$_POST['group_id'];
        
        // Use centralized function to count verified families
        $counts = count_verified_families_in_group($gid, $currentMonth, $uid);
        
        if ($counts['verified_count'] === 0) {
            $error_message = 'لا يمكن إرسال المجموعة. لا توجد أي أسرة موثقة بالكامل.';
        } else {
            try {
                dbExecute("UPDATE orphan_groups SET verification_status = 'submitted', submitted_at = NOW() WHERE id = ?", [$gid]);
                $success_message = sprintf(
                    'تم إرسال %d أسرة موثقة للمحاسب بنجاح (المبلغ: %s ج.س). %d أسرة غير موثقة ستظل قابلة للتعديل.',
                    $counts['verified_count'],
                    number_format($counts['verified_amount'], 0),
                    $counts['unverified_count']
                );
            } catch (Throwable $e) {
                $error_message = 'حدث خطأ أثناء الإرسال: ' . $e->getMessage();
            }
        }
    }
}

// Get groups assigned to this nanny
$myGroups = dbFetchAll("
    SELECT 
        og.id as group_id,
        og.group_name,
        og.description,
        nga.start_date as assigned_date
    FROM orphan_groups og
    JOIN nanny_group_assignments nga ON nga.group_id = og.id AND nga.end_date IS NULL
    WHERE nga.nanny_id = ? AND og.is_active = 1
    ORDER BY og.group_name
", [$uid]);

// Initialize variables
$families = [];
$selectedGroup = null;
$counts = ['verified_count' => 0, 'unverified_count' => 0, 'verified_amount' => 0, 'unverified_amount' => 0];

// If a group is selected, fetch its families
if ($selectedGroupId) {
    $selectedGroup = dbFetchOne("SELECT id, group_name, description, verification_status, submitted_at FROM orphan_groups WHERE id = ? AND is_active = 1", [$selectedGroupId]);
    
    if ($selectedGroup) {
        // Use centralized function
        $counts = count_verified_families_in_group($selectedGroupId, $currentMonth, $uid);
        $families = array_merge($counts['verified_families'], $counts['unverified_families']);
        
        // Sort by family code
        usort($families, function($a, $b) {
            return strcmp($a['family_code'], $b['family_code']);
        });
    }
}

$pageTitle = 'توثيق المجموعات والعائلات';
include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="bi bi-clipboard-check me-2"></i>توثيق المجموعات والعائلات</h2>
            <p>الشهر الحالي: <strong><?php echo e($currentMonth); ?></strong></p>
        </div>
    </div>

    <!-- Group Selection -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-people me-2"></i>اختر المجموعة للتوثيق</h5>
        </div>
        <div class="card-body">
            <?php if (empty($myGroups)): ?>
                <p class="text-muted">لا توجد مجموعات معينة لك.</p>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($myGroups as $group): ?>
                        <div class="col-md-4">
                            <a href="?group_id=<?php echo $group['group_id']; ?>" class="text-decoration-none">
                                <div class="card h-100 <?php echo $selectedGroupId == $group['group_id'] ? 'border-primary border-2' : ''; ?>">
                                    <div class="card-body text-center">
                                        <h6><?php echo e($group['group_name']); ?></h6>
                                        <small class="text-muted"><?php echo e($group['description'] ?: 'لا يوجد وصف'); ?></small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Families in Selected Group -->
    <?php if ($selectedGroupId): ?>
        <?php if (!$selectedGroup): ?>
            <div class="alert alert-warning">المجموعة غير موجودة أو غير نشطة.</div>
        <?php elseif (empty($families)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                لا توجد عائلات في هذه المجموعة.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-list me-2"></i>
                        العائلات في مجموعة: <?php echo e($selectedGroup['group_name']); ?>
                        <span class="badge bg-primary"><?php echo count($families); ?> عائلة</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>الكود</th>
                                    <th>اسم الأم</th>
                                    <th class="text-center">توثيق اليتيم</th>
                                    <th class="text-center">توثيق الأم</th>
                                    <th class="text-center">توثيق البنك</th>
                                    <th>ملاحظات</th>
                                    <th>الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
    <?php foreach ($families as $f): 
        $status = $f['status'];
        $isComplete = $status['is_fully_verified'];
        $verifiedCount = (int)$status['is_orphan'] + (int)$status['is_mother'] + (int)$status['is_bank'];
    ?>
        <tr class="<?php echo $isComplete ? 'table-success' : ($verifiedCount > 0 ? 'table-warning' : ''); ?>">
            <td>
                <span class="fw-bold"><?php echo e($f['family_code']); ?></span>
                <?php if ($isComplete): ?>
                    <span class="badge bg-success ms-1"><i class="fas fa-check"></i> مكتمل</span>
                <?php elseif ($verifiedCount > 0): ?>
                    <span class="badge bg-warning text-dark ms-1"><i class="fas fa-spinner"></i> <?php echo $verifiedCount; ?>/3</span>
                <?php else: ?>
                    <span class="badge bg-secondary ms-1">لم يبدأ</span>
                <?php endif; ?>
            </td>
            <td><?php echo e($f['mother_name']); ?></td>
            <td class="text-center">
                <?php if ($status['is_orphan'] === 1): ?>
                    <span class="badge bg-success" style="font-size: 1.2rem; padding: 6px 12px;">✓</span>
                <?php else: ?>
                    <span class="badge bg-danger" style="font-size: 1.2rem; padding: 6px 12px;">✗</span>
                <?php endif; ?>
            </td>
            <td class="text-center">
                <?php if ($status['is_mother'] === 1): ?>
                    <span class="badge bg-success" style="font-size: 1.2rem; padding: 6px 12px;">✓</span>
                <?php else: ?>
                    <span class="badge bg-danger" style="font-size: 1.2rem; padding: 6px 12px;">✗</span>
                <?php endif; ?>
            </td>
            <td class="text-center">
                <?php if ($status['is_bank'] === 1): ?>
                    <span class="badge bg-success" style="font-size: 1.2rem; padding: 6px 12px;">✓</span>
                <?php else: ?>
                    <span class="badge bg-danger" style="font-size: 1.2rem; padding: 6px 12px;">✗</span>
                <?php endif; ?>
            </td>
            <td>
                <small><?php echo e($status['notes'] ?: '-'); ?></small>
                <?php if ($status['verified_at']): ?>
                    <br><small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($status['verified_at'])); ?></small>
                <?php endif; ?>
            </td>
            <td>
                <button type="button" class="btn btn-sm <?php echo $isComplete ? 'btn-success' : 'btn-primary'; ?>" data-bs-toggle="modal" data-bs-target="#modal<?php echo $f['id']; ?>">
                    <i class="bi <?php echo $isComplete ? 'bi-check-circle' : 'bi-pencil'; ?>"></i> 
                    <?php echo $isComplete ? 'مراجعة' : 'توثيق'; ?>
                </button>
            </td>
        </tr>

        <!-- Modal -->
        <div class="modal fade" id="modal<?php echo $f['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" id="verifyForm<?php echo $f['id']; ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <?php echo $isComplete ? 'مراجعة توثيق' : 'توثيق'; ?>: <?php echo e($f['mother_name']); ?>
                                <span class="badge <?php echo $isComplete ? 'bg-success' : 'bg-warning'; ?> ms-2">
                                    <?php echo $verifiedCount; ?>/3
                                </span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="family_id" value="<?php echo $f['id']; ?>">
                            <?php echo csrf_field(); ?>
                            
                            <div class="alert alert-<?php echo $isComplete ? 'success' : 'info'; ?> mb-3">
                                <i class="fas fa-<?php echo $isComplete ? 'check-circle' : 'info-circle'; ?>"></i>
                                <?php if ($isComplete): ?>
                                    هذه العائلة موثقة بالكامل. يمكنك تعديل التوثيق إذا لزم الأمر.
                                <?php elseif ($verifiedCount > 0): ?>
                                    تم توثيق <?php echo $verifiedCount; ?> من 3 نقاط. أكمل التوثيق لإرسال العائلة للمحاسب.
                                <?php else: ?>
                                    لم يتم توثيق أي نقطة بعد. يجب توثيق جميع النقاط الثلاث لإكمال التوثيق.
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_orphan" id="orphan<?php echo $f['id']; ?>" value="1" <?php echo $status['is_orphan'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="orphan<?php echo $f['id']; ?>">
                                    <strong>توثيق اليتيم</strong>
                                    <br><small class="text-muted">تأكيد أن اليتيم لا زال على قيد الحياة ومستحق للكفالة</small>
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_mother" id="mother<?php echo $f['id']; ?>" value="1" <?php echo $status['is_mother'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mother<?php echo $f['id']; ?>">
                                    <strong>توثيق تواصل الأم</strong>
                                    <br><small class="text-muted">تأكيد أن رقم هاتف الأم يعمل ويمكن التواصل معها</small>
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_bank" id="bank<?php echo $f['id']; ?>" value="1" <?php echo $status['is_bank'] === 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="bank<?php echo $f['id']; ?>">
                                    <strong>توثيق الحساب البنكي</strong>
                                    <br><small class="text-muted">تأكيد أن بيانات الحساب البنكي صحيحة ومحدثة</small>
                                </label>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ملاحظات</label>
                                <textarea class="form-control" name="notes" rows="2" placeholder="أي ملاحظات إضافية عن هذه العائلة..."><?php echo e($status['notes']); ?></textarea>
                            </div>

                            <?php if ($isComplete && $status['verified_at']): ?>
                                <div class="text-muted small">
                                    <i class="fas fa-clock"></i> آخر توثيق: <?php echo date('Y-m-d H:i', strtotime($status['verified_at'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" name="verify_family" class="btn <?php echo $isComplete ? 'btn-success' : 'btn-primary'; ?>">
                                <i class="fas fa-save"></i> <?php echo $isComplete ? 'تحديث' : 'حفظ'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</tbody>
                        </table>
                    </div>
                </div>

                <!-- SUBMIT GROUP SECTION (Stage 1) -->
                <?php 
                $v_status = $selectedGroup['verification_status'] ?? 'pending';
                $hasVerified = $counts['verified_count'] > 0;
                ?>
                
                <?php if ($v_status === 'pending' && $hasVerified): ?>
                    <div class="card-footer bg-warning text-dark text-center py-3">
                        <div class="row">
                            <div class="col-md-4">
                                <strong>الأسر الموثقة:</strong> <?php echo $counts['verified_count']; ?> أسرة<br>
                                <span class="text-success">المبلغ القابل للتحويل: <?php echo number_format($counts['verified_amount'], 0); ?> ج.س</span>
                            </div>
                            <div class="col-md-4">
                                <strong>الأسر غير الموثقة:</strong> <?php echo $counts['unverified_count']; ?> أسرة<br>
                                <span class="text-muted">المبلغ المتبقي: <?php echo number_format($counts['unverified_amount'], 0); ?> ج.س</span>
                            </div>
                            <div class="col-md-4">
                                <form method="POST" action="" id="submitGroupForm">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="group_id" value="<?php echo $selectedGroup['id']; ?>">
                                    <input type="hidden" name="submit_group" value="1">
                                    <button type="button" class="btn btn-success btn-lg" onclick="confirmSubmitGroup(<?php echo $counts['verified_count']; ?>, <?php echo $counts['unverified_count']; ?>, '<?php echo number_format($counts['verified_amount'], 0); ?>', '<?php echo number_format($counts['unverified_amount'], 0); ?>')">
                                        <i class="fas fa-paper-plane me-2"></i> إرسال الأسر الموثقة للمحاسب
                                    </button>
                                </form>
                                <small class="d-block mt-2">يمكنك إضافة المزيد من الأسر الموثقة لاحقاً</small>
                            </div>
                        </div>
                    </div>
                <?php elseif ($v_status === 'submitted'): ?>
                    <div class="card-footer bg-info text-white text-center py-3">
                        <i class="fas fa-clock me-2"></i>
                        تم إرسال المجموعة في: <?php echo date('Y-m-d H:i', strtotime($selectedGroup['submitted_at'] ?? 'now')); ?>
                        <br>
                        <small>بانتظار مراجعة المحاسب واعتماد الدفعة</small>
                    </div>
                <?php elseif ($v_status === 'approved' || $v_status === 'transferred'): ?>
                    <div class="card-footer bg-primary text-white text-center py-3">
                        <i class="fas fa-check-circle me-2"></i>
                        تم اعتماد الدفعة من قبل المحاسب. يرجى البدء في توزيع المبالغ ورفع الإيصالات لكل أسرة.
                    </div>
                <?php elseif ($v_status === 'closed'): ?>
                    <div class="card-footer bg-secondary text-white text-center py-3">
                        <i class="fas fa-lock me-2"></i>
                        تم إغلاق المجموعة واكتمال جميع الإجراءات.
                    </div>
                <?php elseif (!$hasVerified && count($families) > 0): ?>
                    <div class="card-footer bg-light text-center py-3">
                        <i class="fas fa-info-circle me-2"></i>
                        لا توجد أسر موثقة بالكامل بعد. يرجى توثيق أسرة واحدة على الأقل (تفعيل الخانات الثلاثة) قبل الإرسال.
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-hand-index me-2"></i>
            يرجى اختيار مجموعة من الأعلى لعرض العائلات.
        </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>

<!-- SweetAlert2 for confirmation -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmSubmitGroup(verifiedCount, unverifiedCount, verifiedAmount, unverifiedAmount) {
    // If there are no verified families, show warning
    if (verifiedCount === 0) {
        Swal.fire({
            title: 'لا توجد أسر موثقة',
            text: 'يجب توثيق أسرة واحدة على الأقل (تفعيل الخانات الثلاثة) قبل الإرسال.',
            icon: 'warning',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#ffc107'
        });
        return;
    }
    
    // Build the confirmation message
    let message = '';
    if (verifiedCount > 0) {
        message += '✅ <strong>' + verifiedCount + ' أسرة موثقة</strong> (المبلغ: ' + verifiedAmount + ' ج.س)<br>';
    }
    if (unverifiedCount > 0) {
        message += '⚠️ <strong>' + unverifiedCount + ' أسرة غير موثقة</strong> (المبلغ: ' + unverifiedAmount + ' ج.س)<br><br>';
        message += 'سيتم إرسال الأسر الموثقة فقط للمحاسب. الأسر غير الموثقة ستبقى مفتوحة للتعديل.';
    } else {
        message += '<br>جميع الأسر موثقة بالكامل. سيتم إرسالها جميعاً للمحاسب.';
    }
    
    Swal.fire({
        title: 'تأكيد إرسال الأسر الموثقة',
        html: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'نعم، إرسال',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Submit the form
            document.getElementById('submitGroupForm').submit();
        }
    });
}

// Show success/error messages after page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success_message): ?>
        Swal.fire({
            title: '✅ تم بنجاح',
            text: '<?php echo addslashes($success_message); ?>',
            icon: 'success',
            timer: 4000,
            timerProgressBar: true,
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#198754'
        });
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        Swal.fire({
            title: '❌ حدث خطأ',
            text: '<?php echo addslashes($error_message); ?>',
            icon: 'error',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#dc3545'
        });
    <?php endif; ?>
});
</script>