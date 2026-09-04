<?php
// Explicit supervisor letter restoration/reassignment after permanent departure.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/supervisor_lifecycle.php';
Session::start();

if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'vgm'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }

$pageTitle = 'إعادة تعيين الحروف المحررة';
$active = 'supervisors';
$actorId = (int)Session::getUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.'); redirect('modules/supervisors/reassign-letters.php'); }

    $historyId = (int)($_POST['history_id'] ?? 0);
    $targetSupervisorId = (int)($_POST['supervisor_id'] ?? 0);
    $action = (string)($_POST['letter_action'] ?? 'assign');

    try {
        if ($historyId <= 0) throw new RuntimeException('بيانات إعادة التعيين غير مكتملة.');
        $pdo = db();
        $pdo->beginTransaction();

        // Resolve the latest permanent-departure event for this exact letter+gender.
        // Later assignments do not erase or hide that historical release event.
        $source = dbFetchOne("SELECT h.id, h.supervisor_id AS old_supervisor_id, h.letter_id, h.gender,
                h.ended_at, l.code AS letter_code, u.full_name AS old_supervisor_name,
                COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) AS old_supervisor_status
            FROM supervisor_letter_assignment_history h
            JOIN letters l ON l.id = h.letter_id
            JOIN users u ON u.id = h.supervisor_id
            WHERE h.id = ? AND h.ended_at IS NOT NULL AND h.end_reason = 'supervisor_departure'
              AND h.id = (SELECT MAX(h2.id) FROM supervisor_letter_assignment_history h2
                          WHERE h2.letter_id = h.letter_id AND h2.gender = h.gender
                            AND h2.end_reason = 'supervisor_departure')
            FOR UPDATE", [$historyId]);
        if (!$source) throw new RuntimeException('سجل التحرير المطلوب غير متاح أو تم تسجيل مغادرة أحدث لهذا الحرف.');

        $current = dbFetchOne("SELECT sl.id, sl.supervisor_id, u.full_name
            FROM supervisor_letters sl JOIN users u ON u.id = sl.supervisor_id
            WHERE sl.letter_id = ? AND sl.gender = ? LIMIT 1 FOR UPDATE",
            [(int)$source['letter_id'], $source['gender']]);

        if ($action === 'restore') {
            if ((string)$source['old_supervisor_status'] !== 'active') {
                throw new RuntimeException('لا يمكن إعادة الحرف للمشرف السابق لأنه ليس نشطاً حالياً.');
            }
            if ($current && (int)$current['supervisor_id'] === (int)$source['old_supervisor_id']) {
                throw new RuntimeException('هذا الحرف موجود بالفعل لدى المشرف السابق.');
            }

            if ($current) {
                if (($_POST['confirm_replace'] ?? '') !== '1') {
                    throw new RuntimeException('هذا الحرف معيّن حالياً لمشرف آخر. يجب تأكيد الاستبدال.');
                }
                $currentHistory = dbFetchOne("SELECT id FROM supervisor_letter_assignment_history
                    WHERE supervisor_letter_id = ? AND ended_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE", [(int)$current['id']]);
                if ($currentHistory) {
                    dbExecute("UPDATE supervisor_letter_assignment_history
                        SET ended_at = NOW(), ended_by = ?, end_reason = 'supervisor_reassignment'
                        WHERE id = ? AND ended_at IS NULL", [$actorId, (int)$currentHistory['id']]);
                }
                dbExecute("DELETE FROM supervisor_letters WHERE id = ?", [(int)$current['id']]);
            }

            dbExecute("INSERT INTO supervisor_letters (supervisor_id, letter_id, gender, assigned_by) VALUES (?, ?, ?, ?)", [
                (int)$source['old_supervisor_id'], (int)$source['letter_id'], $source['gender'], $actorId
            ]);
            $newId = (int)$pdo->lastInsertId();
            dbExecute("INSERT INTO supervisor_letter_assignment_history
                (supervisor_letter_id, supervisor_id, letter_id, gender, assigned_by, assignment_type, assignment_reason)
                VALUES (?, ?, ?, ?, ?, 'permanent', 'supervisor_return_restore')", [
                $newId, (int)$source['old_supervisor_id'], (int)$source['letter_id'], $source['gender'], $actorId
            ]);

            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                    VALUES (?, 'RESTORE', 'supervisor_letters', ?, ?, ?, ?, ?)", [
                    $actorId, (int)$source['letter_id'],
                    json_encode(['previous_supervisor_id'=>(int)$source['old_supervisor_id'], 'previous_supervisor_name'=>$source['old_supervisor_name'], 'current_supervisor_id'=>$current ? (int)$current['supervisor_id'] : null, 'current_supervisor_name'=>$current['full_name'] ?? null, 'departure_history_id'=>$historyId], JSON_UNESCAPED_UNICODE),
                    json_encode(['assigned_to_supervisor_id'=>(int)$source['old_supervisor_id'], 'assigned_to_supervisor_name'=>$source['old_supervisor_name'], 'reason'=>'supervisor_return_restore', 'replaced_current_assignment'=>(bool)$current], JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Throwable $auditError) { error_log('Supervisor letter restore audit: ' . $auditError->getMessage()); }

            $pdo->commit();
            flash('success', 'تمت إعادة الحرف «' . $source['letter_code'] . '» إلى المشرف السابق: ' . $source['old_supervisor_name'] . '.');
        } else {
            if ($targetSupervisorId <= 0) throw new RuntimeException('اختر المشرف الذي سيستلم الحرف.');
            $target = dbFetchOne("SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id
                WHERE u.id = ? AND r.code = 'supervisor' AND u.is_active = 1
                  AND COALESCE(u.supervisor_status, 'active') = 'active' FOR UPDATE", [$targetSupervisorId]);
            if (!$target) throw new RuntimeException('المشرف المختار غير متاح للعمل حالياً.');
            if ($current) throw new RuntimeException('هذا الحرف معيّن حالياً للمشرف: ' . $current['full_name'] . '. لا يمكن سحبه من هنا دون قرار استبدال صريح.');

            dbExecute("INSERT INTO supervisor_letters (supervisor_id, letter_id, gender, assigned_by) VALUES (?, ?, ?, ?)", [
                $targetSupervisorId, (int)$source['letter_id'], $source['gender'], $actorId
            ]);
            $newId = (int)$pdo->lastInsertId();
            dbExecute("INSERT INTO supervisor_letter_assignment_history
                (supervisor_letter_id, supervisor_id, letter_id, gender, assigned_by, assignment_type, assignment_reason)
                VALUES (?, ?, ?, ?, ?, 'permanent', 'supervisor_reassignment')", [
                $newId, $targetSupervisorId, (int)$source['letter_id'], $source['gender'], $actorId
            ]);

            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                    VALUES (?, 'REASSIGN', 'supervisor_letters', ?, ?, ?, ?, ?)", [
                    $actorId, (int)$source['letter_id'],
                    json_encode(['released_from_supervisor_id'=>(int)$source['old_supervisor_id'], 'released_from_supervisor_name'=>$source['old_supervisor_name'], 'departure_history_id'=>$historyId], JSON_UNESCAPED_UNICODE),
                    json_encode(['assigned_to_supervisor_id'=>$targetSupervisorId, 'assigned_to_supervisor_name'=>$target['full_name'], 'reason'=>'supervisor_reassignment'], JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Throwable $auditError) { error_log('Supervisor letter reassignment audit: ' . $auditError->getMessage()); }

            $pdo->commit();
            flash('success', 'تم تعيين الحرف «' . $source['letter_code'] . '» إلى المشرف: ' . $target['full_name'] . '.');
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        error_log('Supervisor letter reassignment: ' . $e->getMessage());
        flash('error', $e->getMessage());
    }
    redirect('modules/supervisors/reassign-letters.php');
}

$supervisors = dbFetchAll("SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id
    WHERE r.code = 'supervisor' AND u.is_active = 1 AND COALESCE(u.supervisor_status, 'active') = 'active'
    ORDER BY u.full_name");

$releasedLetters = dbFetchAll("SELECT h.id AS history_id, h.supervisor_id AS old_supervisor_id, h.letter_id, h.gender, h.ended_at,
        l.code AS letter_code, u.full_name AS old_supervisor_name,
        COALESCE(u.supervisor_status, CASE WHEN u.is_active = 1 THEN 'active' ELSE 'suspended' END) AS old_supervisor_status,
        sl.supervisor_id AS current_supervisor_id, cu.full_name AS current_supervisor_name
    FROM supervisor_letter_assignment_history h
    JOIN letters l ON l.id = h.letter_id JOIN users u ON u.id = h.supervisor_id
    LEFT JOIN supervisor_letters sl ON sl.letter_id = h.letter_id AND sl.gender = h.gender
    LEFT JOIN users cu ON cu.id = sl.supervisor_id
    WHERE h.ended_at IS NOT NULL AND h.end_reason = 'supervisor_departure'
      AND h.id = (SELECT MAX(h2.id) FROM supervisor_letter_assignment_history h2
                  WHERE h2.letter_id = h.letter_id AND h2.gender = h.gender AND h2.end_reason = 'supervisor_departure')
      AND (sl.supervisor_id IS NULL OR sl.supervisor_id <> h.supervisor_id)
    ORDER BY h.ended_at DESC, l.sort_order, h.gender");

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.ak-reassign-table{font-size:13px}.ak-reassign-table th,.ak-reassign-table td{padding:8px 9px;vertical-align:middle}
.ak-letter-chip{display:inline-flex;min-width:42px;height:36px;align-items:center;justify-content:center;border-radius:8px;font-weight:800;font-size:16px;border:1px solid #d8e0ee;background:#eef2f9}
.ak-current-owner{font-size:12px}
</style>
<div class="welcome-section fade-in">
    <h2>إدارة الحروف المحررة</h2>
    <p>تمييز واضح بين إعادة الحرف للمشرف السابق وتعيينه لمشرف آخر، مع الحفاظ على سجل جميع التعيينات.</p>
    <div class="quick-actions mt-3">
        <a href="<?php echo APP_URL; ?>modules/supervisors/assign-letters.php" class="btn btn-secondary btn-sm"><i class="fas fa-table me-1"></i> مصفوفة الحروف</a>
        <a href="<?php echo APP_URL; ?>modules/supervisors/index.php?show_archived=1" class="btn btn-outline-dark btn-sm"><i class="fas fa-users me-1"></i> المشرفون المؤرشفون</a>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="alert alert-info fade-in py-2"><i class="fas fa-circle-info me-1"></i>
    <strong>قاعدة العمل:</strong> عودة المشرف لا تعيد حروفه تلقائياً. إذا كان نشطاً يظهر خيار <strong>إعادة للمشرف السابق</strong>. وإذا كان الحرف لدى مشرف آخر، فلن يُسحب منه إلا بعد تأكيد إداري صريح.
</div>
<div class="card fade-in">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <span><i class="fas fa-right-left me-2"></i>الحروف التي سبق تحريرها بسبب مغادرة نهائية</span>
        <span class="badge bg-warning text-dark"><?php echo count($releasedLetters); ?> حرف/مجموعة</span>
    </div>
    <div class="card-body p-2 p-md-3">
    <?php if (!$releasedLetters): ?>
        <div class="text-center text-muted py-5"><i class="fas fa-check-circle fa-2x mb-2 d-block"></i>لا توجد حروف تحتاج إلى قرار إعادة تعيين.</div>
    <?php else: ?>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 ak-reassign-table">
            <thead><tr><th class="text-center">الحرف</th><th>الجنس</th><th>المشرف السابق</th><th>تاريخ التحرير</th><th>الحالة الحالية</th><th style="min-width:330px">الإجراء</th></tr></thead>
            <tbody>
            <?php foreach ($releasedLetters as $row):
                $previousIsActive = (string)$row['old_supervisor_status'] === 'active';
                $hasCurrentOwner = !empty($row['current_supervisor_id']);
                $genderLabel = $row['gender'] === 'male' ? 'ذكور' : ($row['gender'] === 'female' ? 'إناث' : 'كلاهما');
                $restoreMessage = $hasCurrentOwner
                    ? 'الحرف «' . $row['letter_code'] . '» معيّن حالياً للمشرف «' . $row['current_supervisor_name'] . '». سيتم إنهاء التعيين الحالي وإعادته إلى المشرف السابق «' . $row['old_supervisor_name'] . '». هل تريد المتابعة؟'
                    : 'هل تريد إعادة الحرف «' . $row['letter_code'] . '» (' . $genderLabel . ') إلى المشرف السابق «' . $row['old_supervisor_name'] . '»؟';
            ?>
                <tr>
                    <td class="text-center"><span class="ak-letter-chip"><?php echo e($row['letter_code']); ?></span></td>
                    <td><?php echo e($genderLabel); ?></td>
                    <td><strong><?php echo e($row['old_supervisor_name']); ?></strong><small class="d-block <?php echo $previousIsActive ? 'text-success' : 'text-muted'; ?>"><?php echo $previousIsActive ? 'عاد وأصبح نشطاً' : 'غير متاح للعودة حالياً'; ?></small></td>
                    <td><?php echo e($row['ended_at']); ?></td>
                    <td><?php if ($hasCurrentOwner): ?><strong class="ak-current-owner"><?php echo e($row['current_supervisor_name']); ?></strong><small class="d-block text-muted">مُعيّن حالياً</small><?php else: ?><span class="badge bg-warning text-dark">غير معيّن</span><?php endif; ?></td>
                    <td><div class="d-flex flex-column gap-2">
                        <?php if ($previousIsActive): ?>
                            <form method="post" data-confirm="<?php echo e($restoreMessage); ?>">
                                <?php echo csrf_field(); ?><input type="hidden" name="history_id" value="<?php echo (int)$row['history_id']; ?>"><input type="hidden" name="letter_action" value="restore">
                                <?php if ($hasCurrentOwner): ?><input type="hidden" name="confirm_replace" value="1"><?php endif; ?>
                                <button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-rotate-left me-1"></i> إعادة للمشرف السابق</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$hasCurrentOwner): ?>
                            <?php if ($supervisors): ?>
                                <form method="post" class="d-flex gap-2 align-items-center" data-confirm="هل تريد تعيين هذا الحرف للمشرف المختار؟">
                                    <?php echo csrf_field(); ?><input type="hidden" name="history_id" value="<?php echo (int)$row['history_id']; ?>"><input type="hidden" name="letter_action" value="assign">
                                    <select name="supervisor_id" class="form-select form-select-sm" required><option value="">— اختر مشرفاً آخر —</option><?php foreach ($supervisors as $sup): ?><option value="<?php echo (int)$sup['id']; ?>"><?php echo e($sup['full_name']); ?></option><?php endforeach; ?></select>
                                    <button type="submit" class="btn btn-primary btn-sm text-nowrap"><i class="fas fa-user-plus me-1"></i> تعيين</button>
                                </form>
                            <?php else: ?><span class="text-muted">لا يوجد مشرف نشط متاح حالياً.</span><?php endif; ?>
                        <?php else: ?><small class="text-muted">الحرف معيّن حالياً. استخدم «إعادة للمشرف السابق» فقط إذا كان قرار الاستبدال مقصوداً.</small><?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
