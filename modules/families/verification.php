<?php
// modules/families/verification.php - Nanny Family Verification
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn() || Session::getUserRole() !== 'nanny') { header('Location: ' . APP_URL . 'index.php'); exit(); }
$uid = Session::getUserId();
$currentMonth = date('Y-m');
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_family'])) {
    $family_id = (int)$_POST['family_id'];
    $is_orphan = isset($_POST['is_orphan']) ? 1 : 0;
    $is_mother = isset($_POST['is_mother']) ? 1 : 0;
    $is_bank = isset($_POST['is_bank']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');
    try {
        $existing = dbFetchOne("SELECT id FROM nanny_family_verifications WHERE family_id = ? AND month = ? AND nanny_id = ?", [$family_id, $currentMonth, $uid]);
        if ($existing) {
            dbExecute("UPDATE nanny_family_verifications SET is_orphan_verified = ?, is_mother_contact_verified = ?, is_bank_verified = ?, verification_notes = ?, verified_by_user_id = ?, verified_at = NOW(), updated_at = NOW() WHERE id = ?", [$is_orphan, $is_mother, $is_bank, $notes, $uid, $existing['id']]);
        } else {
            dbExecute("INSERT INTO nanny_family_verifications (family_id, month, nanny_id, is_orphan_verified, is_mother_contact_verified, is_bank_verified, verification_notes, verified_by_user_id, verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())", [$family_id, $currentMonth, $uid, $is_orphan, $is_mother, $is_bank, $notes, $uid]);
        }
        $success_message = t('families.verification_saved');
    } catch (Throwable $e) {
        $error_message = t('families.verification_error', ['message' => $e->getMessage()]);
    }
}
$families = dbFetchAll("SELECT f.id, f.family_code, f.mother_name, f.status, v.is_orphan_verified, v.is_mother_contact_verified, v.is_bank_verified, v.verification_notes, v.verified_at FROM families f LEFT JOIN nanny_family_verifications v ON v.family_id = f.id AND v.month = ? AND v.nanny_id = ? WHERE f.nanny_id = ? AND f.status IN ('active', 'pending') ORDER BY f.family_code ASC", [$currentMonth, $uid, $uid]);
$pageTitle = t('families.verification_title');
$active = 'verification';
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-clipboard-check me-2"></i><?php echo e(t('families.verification_monthly_title')); ?></h2>
    <p><?php echo e(t('families.verification_current_month', ['month' => $currentMonth])); ?></p>
    <div class="quick-actions mt-3"><a href="<?php echo url('dashboard/nanny_dashboard.php'); ?>" class="btn btn-secondary btn-sm me-2"><i class="fas fa-arrow-left me-1"></i><?php echo e(t('families.verification_back_dashboard')); ?></a></div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($success_message): ?><div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo e($success_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle me-2"></i><?php echo e($error_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="card fade-in">
    <div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0"><i class="fas fa-list me-2"></i><?php echo e(t('families.verification_list_title')); ?></h5><span class="badge bg-primary"><?php echo e(t('families.verification_family_count', ['count' => count($families)])); ?></span></div>
    <div class="card-body p-0">
        <?php if (empty($families)): ?><div class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-3"></i><p><?php echo e(t('families.verification_none')); ?></p></div>
        <?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr>
            <th><?php echo e(t('families.verification_code')); ?></th><th><?php echo e(t('families.verification_mother')); ?></th><th class="text-center"><?php echo e(t('families.verification_orphan')); ?></th><th class="text-center"><?php echo e(t('families.verification_mother_contact')); ?></th><th class="text-center"><?php echo e(t('families.verification_bank')); ?></th><th><?php echo e(t('families.verification_notes')); ?></th><th class="text-center"><?php echo e(t('families.verification_action')); ?></th>
        </tr></thead><tbody>
        <?php foreach ($families as $f): ?><tr class="<?php echo ($f['is_orphan_verified'] && $f['is_mother_contact_verified'] && $f['is_bank_verified']) ? 'table-success' : ''; ?>">
            <td><strong><?php echo e($f['family_code']); ?></strong></td><td><?php echo e($f['mother_name']); ?></td>
            <?php foreach (['is_orphan_verified','is_mother_contact_verified','is_bank_verified'] as $flag): ?><td class="text-center"><?php if ($f[$flag]): ?><span class="badge bg-success"><i class="fas fa-check"></i> <?php echo e(t('families.verification_yes')); ?></span><?php else: ?><span class="badge bg-danger"><i class="fas fa-times"></i> <?php echo e(t('families.verification_no')); ?></span><?php endif; ?></td><?php endforeach; ?>
            <td><small class="text-muted"><?php echo e($f['verification_notes'] ?: '—'); ?></small><?php if ($f['verified_at']): ?><br><small class="text-info"><?php echo e(t('families.verification_last_update', ['date' => date('Y-m-d H:i', strtotime($f['verified_at']))])); ?></small><?php endif; ?></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#verifyModal<?php echo $f['id']; ?>"><i class="fas fa-edit me-1"></i><?php echo e(t('families.verification_button')); ?></button></td>
        </tr>
        <div class="modal fade" id="verifyModal<?php echo $f['id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="">
            <div class="modal-header"><h5 class="modal-title"><?php echo e(t('families.verification_modal_title', ['mother' => $f['mother_name'], 'code' => $f['family_code']])); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><input type="hidden" name="family_id" value="<?php echo $f['id']; ?>">
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_orphan" id="is_orphan_<?php echo $f['id']; ?>" value="1" <?php echo $f['is_orphan_verified'] ? 'checked' : ''; ?>><label class="form-check-label" for="is_orphan_<?php echo $f['id']; ?>"><?php echo e(t('families.verification_orphan_details')); ?></label></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_mother" id="is_mother_<?php echo $f['id']; ?>" value="1" <?php echo $f['is_mother_contact_verified'] ? 'checked' : ''; ?>><label class="form-check-label" for="is_mother_<?php echo $f['id']; ?>"><?php echo e(t('families.verification_mother_details')); ?></label></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_bank" id="is_bank_<?php echo $f['id']; ?>" value="1" <?php echo $f['is_bank_verified'] ? 'checked' : ''; ?>><label class="form-check-label" for="is_bank_<?php echo $f['id']; ?>"><?php echo e(t('families.verification_bank_details')); ?></label></div>
                <div class="mb-3"><label class="form-label"><?php echo e(t('families.verification_notes_optional')); ?></label><textarea class="form-control" name="notes" rows="2"><?php echo e($f['verification_notes']); ?></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo e(t('families.verification_cancel')); ?></button><button type="submit" name="verify_family" class="btn btn-primary"><i class="fas fa-save me-1"></i><?php echo e(t('families.verification_save')); ?></button></div>
        </form></div></div></div>
        <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>