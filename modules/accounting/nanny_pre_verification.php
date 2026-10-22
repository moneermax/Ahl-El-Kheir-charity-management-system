<?php
// modules/accounting/nanny_pre_verification.php - Monthly preliminary family verification for Nannies.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib_group_workflow.php';

Session::start();

if (!Session::isLoggedIn() || Session::getUserRole() !== 'nanny') {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$uid = (int)Session::getUserId();
$currentMonth = date('Y-m');
$success_message = '';
$error_message = '';

/**
 * Verification is stored by family + month, not by group. Only permit edits
 * for a family in this Nanny's pending groups, and avoid changing a family
 * also present in an active non-pending group whose monthly record is shared.
 */
$canPreVerifyFamily = static function (int $familyId, int $nannyId): bool {
    return dbFetchOne(
        "SELECT f.id
         FROM families f
         WHERE f.id = ?
           AND EXISTS (
               SELECT 1
               FROM family_children fc
               INNER JOIN group_children gc
                   ON gc.child_id = fc.id AND gc.left_date IS NULL
               INNER JOIN orphan_groups og
                   ON og.id = gc.group_id AND og.is_active = 1
                      AND og.verification_status = 'pending'
               INNER JOIN nanny_group_assignments nga
                   ON nga.group_id = og.id AND nga.nanny_id = ? AND nga.end_date IS NULL
               WHERE fc.family_id = f.id AND fc.is_active = 1
           )
           AND NOT EXISTS (
               SELECT 1
               FROM family_children fc_locked
               INNER JOIN group_children gc_locked
                   ON gc_locked.child_id = fc_locked.id AND gc_locked.left_date IS NULL
               INNER JOIN orphan_groups og_locked
                   ON og_locked.id = gc_locked.group_id AND og_locked.is_active = 1
               WHERE fc_locked.family_id = f.id
                 AND COALESCE(og_locked.verification_status, '') <> 'pending'
           )
         LIMIT 1",
        [$familyId, $nannyId]
    ) !== null;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf()) {
        $error_message = 'انتهت صلاحية الطلب. حدّث الصفحة ثم حاول مرة أخرى.';
    } elseif (isset($_POST['bulk_verify'])) {
        $rawFamilyIds = $_POST['family_ids'] ?? [];
        $familyIds = is_array($rawFamilyIds)
            ? array_values(array_unique(array_filter(array_map('intval', $rawFamilyIds), static fn($id) => $id > 0)))
            : [];
        $operation = (string)($_POST['bulk_operation'] ?? '');
        $allowedOperations = ['orphan', 'mother', 'bank', 'all'];

        if (empty($familyIds)) {
            $error_message = 'يرجى تحديد أسرة واحدة على الأقل.';
        } elseif (count($familyIds) > 500) {
            $error_message = 'عدد الأسر المحدد يتجاوز الحد المسموح للعملية الواحدة.';
        } elseif (!in_array($operation, $allowedOperations, true)) {
            $error_message = 'نوع التوثيق الجماعي غير صالح.';
        } else {
            try {
                foreach ($familyIds as $familyId) {
                    if (!$canPreVerifyFamily($familyId, $uid)) {
                        throw new DomainException('تتضمن القائمة أسرة خارج نطاق التوثيق المسبق المسموح. لم يتم حفظ أي تغييرات.');
                    }
                }

                $pdo = db();
                $pdo->beginTransaction();
                $recordStmt = $pdo->prepare(
                    "SELECT id, is_orphan_verified, is_mother_contact_verified, is_bank_verified
                     FROM nanny_family_verifications
                     WHERE family_id = ? AND month = ? FOR UPDATE"
                );
                $insertStmt = $pdo->prepare(
                    "INSERT INTO nanny_family_verifications
                     (family_id, month, nanny_id, is_orphan_verified, is_mother_contact_verified, is_bank_verified,
                      verification_notes, verified_by_user_id, verified_at)
                     VALUES (?, ?, ?, ?, ?, ?, '', ?, NOW())"
                );
                $updateStmt = $pdo->prepare(
                    "UPDATE nanny_family_verifications
                     SET nanny_id = ?, is_orphan_verified = ?, is_mother_contact_verified = ?, is_bank_verified = ?,
                         verified_by_user_id = ?, verified_at = NOW()
                     WHERE id = ?"
                );
                $auditStmt = $pdo->prepare(
                    "INSERT INTO verification_audit_log
                     (family_id, month, nanny_id, old_orphan, old_mother, old_bank, new_orphan, new_mother, new_bank, changed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                );

                foreach ($familyIds as $familyId) {
                    $recordStmt->execute([$familyId, $currentMonth]);
                    $record = $recordStmt->fetch(PDO::FETCH_ASSOC);
                    $oldOrphan = $record ? (int)$record['is_orphan_verified'] : 0;
                    $oldMother = $record ? (int)$record['is_mother_contact_verified'] : 0;
                    $oldBank = $record ? (int)$record['is_bank_verified'] : 0;

                    $newOrphan = ($operation === 'orphan' || $operation === 'all') ? 1 : $oldOrphan;
                    $newMother = ($operation === 'mother' || $operation === 'all') ? 1 : $oldMother;
                    $newBank = ($operation === 'bank' || $operation === 'all') ? 1 : $oldBank;

                    if ($record) {
                        $updateStmt->execute([$uid, $newOrphan, $newMother, $newBank, $uid, (int)$record['id']]);
                    } else {
                        $insertStmt->execute([$familyId, $currentMonth, $uid, $newOrphan, $newMother, $newBank, $uid]);
                    }

                    $auditStmt->execute([
                        $familyId, $currentMonth, $uid,
                        $oldOrphan, $oldMother, $oldBank,
                        $newOrphan, $newMother, $newBank
                    ]);
                }

                $pdo->commit();
                $operationLabels = [
                    'orphan' => 'توثيق اليتيم فقط',
                    'mother' => 'توثيق تواصل الأم فقط',
                    'bank' => 'توثيق الحساب البنكي فقط',
                    'all' => 'توثيق النقاط الثلاث'
                ];
                $success_message = sprintf(
                    'تم حفظ %s بنجاح لـ %d أسرة لشهر الصرف %s.',
                    $operationLabels[$operation],
                    count($familyIds),
                    $currentMonth
                );
            } catch (Throwable $e) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_message = $e instanceof DomainException
                    ? $e->getMessage()
                    : 'تعذر حفظ التوثيق الجماعي. لم يتم اعتماد العملية؛ يرجى المحاولة مرة أخرى.';
                error_log('Nanny preliminary bulk verification failed: ' . $e->getMessage());
            }
        }
    } elseif (isset($_POST['verify_family'])) {
        $familyId = (int)($_POST['family_id'] ?? 0);
        $isOrphan = isset($_POST['is_orphan']) ? 1 : 0;
        $isMother = isset($_POST['is_mother']) ? 1 : 0;
        $isBank = isset($_POST['is_bank']) ? 1 : 0;
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($familyId < 1 || !$canPreVerifyFamily($familyId, $uid)) {
            $error_message = 'لا يمكنك تعديل توثيق هذه الأسرة؛ تحقق من تعيين المجموعة وحالة التوثيق.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $existing = dbFetchOne(
                    "SELECT id, is_orphan_verified, is_mother_contact_verified, is_bank_verified
                     FROM nanny_family_verifications
                     WHERE family_id = ? AND month = ? FOR UPDATE",
                    [$familyId, $currentMonth]
                );
                $oldOrphan = $existing ? (int)$existing['is_orphan_verified'] : 0;
                $oldMother = $existing ? (int)$existing['is_mother_contact_verified'] : 0;
                $oldBank = $existing ? (int)$existing['is_bank_verified'] : 0;

                if ($existing) {
                    dbExecute(
                        "UPDATE nanny_family_verifications
                         SET nanny_id = ?, is_orphan_verified = ?, is_mother_contact_verified = ?, is_bank_verified = ?,
                             verification_notes = ?, verified_by_user_id = ?, verified_at = NOW()
                         WHERE id = ?",
                        [$uid, $isOrphan, $isMother, $isBank, $notes, $uid, (int)$existing['id']]
                    );
                } else {
                    dbExecute(
                        "INSERT INTO nanny_family_verifications
                         (family_id, month, nanny_id, is_orphan_verified, is_mother_contact_verified, is_bank_verified,
                          verification_notes, verified_by_user_id, verified_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        [$familyId, $currentMonth, $uid, $isOrphan, $isMother, $isBank, $notes, $uid]
                    );
                }

                dbExecute(
                    "INSERT INTO verification_audit_log
                     (family_id, month, nanny_id, old_orphan, old_mother, old_bank, new_orphan, new_mother, new_bank, changed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$familyId, $currentMonth, $uid, $oldOrphan, $oldMother, $oldBank, $isOrphan, $isMother, $isBank]
                );
                $pdo->commit();
                $success_message = 'تم حفظ التوثيق المسبق للأسرة بنجاح لشهر الصرف ' . $currentMonth . '.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_message = 'تعذر حفظ بيانات التوثيق. لم يتم اعتماد التغيير؛ يرجى المحاولة مرة أخرى.';
                error_log('Nanny preliminary individual verification failed: ' . $e->getMessage());
            }
        }
    }
}

$families = dbFetchAll(
    "SELECT f.id, f.family_code, f.mother_name,
            GROUP_CONCAT(DISTINCT og.group_name ORDER BY og.group_name SEPARATOR '، ') AS group_names
     FROM families f
     INNER JOIN family_children fc ON fc.family_id = f.id AND fc.is_active = 1
     INNER JOIN group_children gc ON gc.child_id = fc.id AND gc.left_date IS NULL
     INNER JOIN orphan_groups og
         ON og.id = gc.group_id AND og.is_active = 1 AND og.verification_status = 'pending'
     INNER JOIN nanny_group_assignments nga
         ON nga.group_id = og.id AND nga.nanny_id = ? AND nga.end_date IS NULL
     WHERE NOT EXISTS (
         SELECT 1
         FROM family_children fc_locked
         INNER JOIN group_children gc_locked
             ON gc_locked.child_id = fc_locked.id AND gc_locked.left_date IS NULL
         INNER JOIN orphan_groups og_locked
             ON og_locked.id = gc_locked.group_id AND og_locked.is_active = 1
         WHERE fc_locked.family_id = f.id
           AND COALESCE(og_locked.verification_status, '') <> 'pending'
     )
     GROUP BY f.id, f.family_code, f.mother_name
     ORDER BY f.family_code ASC",
    [$uid]
);

$verifiedCount = 0;
$partialCount = 0;
foreach ($families as &$family) {
    $family['status'] = get_latest_verification_status((int)$family['id'], $currentMonth, $uid);
    $family['verified_checks'] = (int)$family['status']['is_orphan']
        + (int)$family['status']['is_mother']
        + (int)$family['status']['is_bank'];
    if ($family['status']['is_fully_verified']) {
        $verifiedCount++;
    } elseif ($family['verified_checks'] > 0) {
        $partialCount++;
    }
}
unset($family);

$pageTitle = 'التحقق المسبق للأسر';
$active = 'nanny_pre_verification';
include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h2><i class="fas fa-clipboard-check me-2"></i>التحقق المسبق للأسر</h2>
            <p class="text-muted mb-0">شهر الصرف: <strong><?php echo e($currentMonth); ?></strong></p>
        </div>
        <a href="<?php echo APP_URL; ?>modules/accounting/group_verification.php" class="btn btn-outline-primary">
            <i class="fas fa-people-group me-1"></i> الانتقال إلى توثيق المجموعة
        </a>
    </div>

    <div class="alert alert-info">
        <strong>هذه خطوة تمهيدية وليست إرسالاً للمحاسب.</strong>
        تُحفظ نقاط التحقق على سجل الأسرة لشهر الصرف المعروض، وستظهر في توثيق المجموعة للشهر نفسه.
        الأسر التي لم تعد ضمن مجموعة قيد التوثيق لا يمكن تعديلها من هذه الصفحة.
    </div>

    <?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

    <div class="row g-3 mb-4">
        <div class="col-sm-4"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">الأسر ضمن نطاق التوثيق المسبق</div>
            <div class="fs-3 fw-bold"><?php echo count($families); ?></div>
        </div></div></div>
        <div class="col-sm-4"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">موثقة بالكامل</div>
            <div class="fs-3 fw-bold text-success"><?php echo $verifiedCount; ?></div>
        </div></div></div>
        <div class="col-sm-4"><div class="card h-100"><div class="card-body">
            <div class="text-muted small">بدأ توثيقها ولم يكتمل</div>
            <div class="fs-3 fw-bold text-warning"><?php echo $partialCount; ?></div>
        </div></div></div>
    </div>

    <?php if (empty($families)): ?>
        <div class="card"><div class="card-body text-center py-5">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <h5>لا توجد أسر متاحة للتوثيق المسبق حالياً.</h5>
            <p class="text-muted mb-0">تظهر هنا الأسر التابعة لمجموعاتك النشطة التي ما زالت قيد التوثيق.</p>
        </div></div>
    <?php else: ?>
        <form method="POST" id="bulkVerificationForm" class="border rounded bg-light p-3 mb-3"
              onsubmit="return confirmBulkVerification(event);">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="bulk_verify" value="1">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label for="bulkOperation" class="form-label fw-bold">نوع التوثيق الجماعي</label>
                    <select class="form-select" id="bulkOperation" name="bulk_operation" required>
                        <option value="orphan">توثيق اليتيم فقط — مع الحفاظ على بقية التوثيقات</option>
                        <option value="mother">توثيق تواصل الأم فقط — مع الحفاظ على بقية التوثيقات</option>
                        <option value="bank">توثيق الحساب البنكي فقط — مع الحفاظ على بقية التوثيقات</option>
                        <option value="all">توثيق النقاط الثلاث المحددة كمنجزة</option>
                    </select>
                </div>
                <div class="col-lg-4">
                    <div class="small text-muted">حدد الأسر التي أُنجز التحقق منها فعلياً. لا يمس التوثيق الجماعي النقاط الأخرى المحفوظة.</div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="selectAllBulkFamilies">
                        <label class="form-check-label" for="selectAllBulkFamilies">تحديد / إلغاء تحديد كل الأسر</label>
                    </div>
                </div>
                <div class="col-lg-3 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check-double me-1"></i> حفظ التوثيق للأسر المحددة</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">الأسر المسندة إليك</h5>
                <span class="badge bg-primary"><?php echo count($families); ?> أسرة</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr>
                        <th class="text-center">تحديد</th><th>كود الأسرة</th><th>اسم الأم</th><th>المجموعة</th>
                        <th class="text-center">اليتيم</th><th class="text-center">تواصل الأم</th><th class="text-center">الحساب البنكي</th><th>الإجراء</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($families as $family): ?>
                        <?php $status = $family['status']; $isComplete = $status['is_fully_verified']; $checks = $family['verified_checks']; ?>
                        <tr class="<?php echo $isComplete ? 'table-success' : ($checks > 0 ? 'table-warning' : ''); ?>">
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input bulk-family-checkbox"
                                       name="family_ids[]" value="<?php echo (int)$family['id']; ?>"
                                       form="bulkVerificationForm"
                                       aria-label="<?php echo e('تحديد الأسرة ' . $family['family_code']); ?>">
                            </td>
                            <td><strong><?php echo e($family['family_code']); ?></strong>
                                <?php if ($isComplete): ?><span class="badge bg-success ms-1">مكتمل</span>
                                <?php elseif ($checks > 0): ?><span class="badge bg-warning text-dark ms-1"><?php echo $checks; ?>/3</span>
                                <?php else: ?><span class="badge bg-secondary ms-1">لم يبدأ</span><?php endif; ?>
                            </td>
                            <td><?php echo e($family['mother_name']); ?></td>
                            <td><small><?php echo e($family['group_names'] ?: '—'); ?></small></td>
                            <td class="text-center"><?php echo $status['is_orphan'] ? '<span class="badge bg-success">✓</span>' : '<span class="badge bg-danger">✗</span>'; ?></td>
                            <td class="text-center"><?php echo $status['is_mother'] ? '<span class="badge bg-success">✓</span>' : '<span class="badge bg-danger">✗</span>'; ?></td>
                            <td class="text-center"><?php echo $status['is_bank'] ? '<span class="badge bg-success">✓</span>' : '<span class="badge bg-danger">✗</span>'; ?></td>
                            <td><button type="button" class="btn btn-sm <?php echo $isComplete ? 'btn-success' : 'btn-primary'; ?>"
                                        data-bs-toggle="modal" data-bs-target="#modal<?php echo (int)$family['id']; ?>">
                                <i class="fas <?php echo $isComplete ? 'fa-check-circle' : 'fa-pen'; ?>"></i>
                                <?php echo $isComplete ? 'مراجعة' : 'توثيق'; ?>
                            </button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php foreach ($families as $family): ?>
            <?php $status = $family['status']; $isComplete = $status['is_fully_verified']; $checks = $family['verified_checks']; ?>
            <div class="modal fade" id="modal<?php echo (int)$family['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog"><div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title"><?php echo $isComplete ? 'مراجعة توثيق الأسرة' : 'توثيق الأسرة'; ?>: <?php echo e($family['mother_name']); ?>
                                <span class="badge <?php echo $isComplete ? 'bg-success' : 'bg-warning text-dark'; ?> ms-2"><?php echo $checks; ?>/3</span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                        </div>
                        <div class="modal-body">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="verify_family" value="1">
                            <input type="hidden" name="family_id" value="<?php echo (int)$family['id']; ?>">
                            <div class="alert alert-<?php echo $isComplete ? 'success' : 'info'; ?>">
                                <?php if ($isComplete): ?>الأسرة موثقة بالكامل لشهر الصرف الحالي. يمكنك تعديل أي نقطة ثم الحفظ.
                                <?php elseif ($checks > 0): ?>تم توثيق <?php echo $checks; ?> من 3 نقاط. احفظ النقاط التي تم التحقق منها فعلياً.
                                <?php else: ?>لم يتم توثيق أي نقطة لهذه الأسرة في شهر الصرف الحالي.<?php endif; ?>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_orphan" id="orphan<?php echo (int)$family['id']; ?>" value="1" <?php echo $status['is_orphan'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="orphan<?php echo (int)$family['id']; ?>"><strong>توثيق اليتيم</strong><br><small class="text-muted">تأكيد استمرار حياة اليتيم وأهليته للكفالة</small></label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_mother" id="mother<?php echo (int)$family['id']; ?>" value="1" <?php echo $status['is_mother'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mother<?php echo (int)$family['id']; ?>"><strong>توثيق تواصل الأم</strong><br><small class="text-muted">تأكيد عمل رقم الهاتف وإمكانية التواصل</small></label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_bank" id="bank<?php echo (int)$family['id']; ?>" value="1" <?php echo $status['is_bank'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="bank<?php echo (int)$family['id']; ?>"><strong>توثيق الحساب البنكي</strong><br><small class="text-muted">تأكيد صحة بيانات الحساب وحداثتها</small></label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="notes<?php echo (int)$family['id']; ?>">ملاحظات</label>
                                <textarea class="form-control" name="notes" id="notes<?php echo (int)$family['id']; ?>" rows="3"><?php echo e($status['notes']); ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ التوثيق</button>
                        </div>
                    </form>
                </div></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmBulkVerification(event) {
    event.preventDefault();
    const form = document.getElementById('bulkVerificationForm');
    const selected = document.querySelectorAll('.bulk-family-checkbox:checked');
    const operation = document.getElementById('bulkOperation');
    const labels = {
        orphan: 'توثيق اليتيم فقط',
        mother: 'توثيق تواصل الأم فقط',
        bank: 'توثيق الحساب البنكي فقط',
        all: 'توثيق النقاط الثلاث'
    };
    if (!selected.length) {
        Swal.fire({title: 'لم يتم تحديد أسر', text: 'حدد أسرة واحدة على الأقل قبل الحفظ.', icon: 'warning', confirmButtonText: 'حسناً'});
        return false;
    }
    Swal.fire({
        title: 'تأكيد التوثيق المسبق',
        text: 'سيتم تنفيذ "' + labels[operation.value] + '" لـ ' + selected.length + ' أسرة في شهر الصرف <?php echo e($currentMonth); ?>. لن يتم إرسال المجموعة للمحاسب.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'نعم، حفظ',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            form.onsubmit = null;
            form.submit();
        }
    });
    return false;
}

document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAllBulkFamilies');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.bulk-family-checkbox').forEach(function(checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
    }
});
</script>
