<?php
// Reassign supervisor letters released by permanent departure.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'vgm'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'إعادة تعيين الحروف المحررة';
$active = 'supervisors';
$actorId = (int)Session::getUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
        redirect('modules/supervisors/reassign-letters.php');
    }

    $historyId = (int)($_POST['history_id'] ?? 0);
    $targetSupervisorId = (int)($_POST['supervisor_id'] ?? 0);

    try {
        if ($historyId <= 0 || $targetSupervisorId <= 0) {
            throw new RuntimeException('بيانات إعادة التعيين غير مكتملة.');
        }

        $pdo = db();
        $pdo->beginTransaction();

        $source = dbFetchOne("SELECT h.id, h.supervisor_id AS old_supervisor_id, h.letter_id, h.gender,
                h.ended_at, h.end_reason, l.code AS letter_code, u.full_name AS old_supervisor_name
            FROM supervisor_letter_assignment_history h
            INNER JOIN letters l ON l.id = h.letter_id
            INNER JOIN users u ON u.id = h.supervisor_id
            WHERE h.id = ?
              AND h.ended_at IS NOT NULL
              AND h.end_reason = 'supervisor_departure'
              AND h.id = (
                  SELECT MAX(h2.id)
                  FROM supervisor_letter_assignment_history h2
                  WHERE h2.letter_id = h.letter_id AND h2.gender = h.gender
              )
            FOR UPDATE", [$historyId]);

        if (!$source) {
            throw new RuntimeException('هذا التعيين لم يعد متاحاً لإعادة التعيين. ربما تمت معالجته بالفعل.');
        }

        $target = dbFetchOne("SELECT u.id, u.full_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.id = ?
              AND r.code = 'supervisor'
              AND u.is_active = 1
              AND COALESCE(u.supervisor_status, 'active') = 'active'
            FOR UPDATE", [$targetSupervisorId]);

        if (!$target) {
            throw new RuntimeException('المشرف المختار غير متاح للعمل حالياً.');
        }

        $occupied = dbFetchOne("SELECT sl.id, sl.supervisor_id, u.full_name
            FROM supervisor_letters sl
            INNER JOIN users u ON u.id = sl.supervisor_id
            WHERE sl.letter_id = ? AND sl.gender = ?
            LIMIT 1
            FOR UPDATE", [(int)$source['letter_id'], $source['gender']]);

        if ($occupied) {
            throw new RuntimeException('هذا الحرف/الجنس تم تعيينه بالفعل للمشرف: ' . $occupied['full_name'] . '.');
        }

        dbExecute("INSERT INTO supervisor_letters
            (supervisor_id, letter_id, gender, assigned_by)
            VALUES (?, ?, ?, ?)", [
            $targetSupervisorId,
            (int)$source['letter_id'],
            $source['gender'],
            $actorId
        ]);
        $newSupervisorLetterId = (int)$pdo->lastInsertId();

        dbExecute("INSERT INTO supervisor_letter_assignment_history
            (supervisor_letter_id, supervisor_id, letter_id, gender, assigned_by, assignment_type, assignment_reason)
            VALUES (?, ?, ?, ?, ?, 'permanent', 'supervisor_reassignment')", [
            $newSupervisorLetterId,
            $targetSupervisorId,
            (int)$source['letter_id'],
            $source['gender'],
            $actorId
        ]);

        try {
            dbExecute("INSERT INTO audit_log
                (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, 'REASSIGN', 'supervisor_letters', ?, ?, ?, ?, ?)", [
                $actorId,
                (int)$source['letter_id'],
                json_encode([
                    'released_from_supervisor_id' => (int)$source['old_supervisor_id'],
                    'released_from_supervisor_name' => $source['old_supervisor_name'],
                    'letter' => $source['letter_code'],
                    'gender' => $source['gender'],
                    'history_id' => $historyId,
                ], JSON_UNESCAPED_UNICODE),
                json_encode([
                    'assigned_to_supervisor_id' => $targetSupervisorId,
                    'assigned_to_supervisor_name' => $target['full_name'],
                    'letter' => $source['letter_code'],
                    'gender' => $source['gender'],
                    'reason' => 'supervisor_reassignment',
                ], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Throwable $auditError) {
            error_log('Supervisor letter reassignment audit: ' . $auditError->getMessage());
        }

        $pdo->commit();

        $genderLabel = $source['gender'] === 'male' ? 'ذكور' : 'إناث';
        flash('success', 'تمت إعادة تعيين الحرف «' . $source['letter_code'] . '» (' . $genderLabel . ') إلى المشرف: ' . $target['full_name'] . '.');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Supervisor letter reassignment: ' . $e->getMessage());
        flash('error', $e->getMessage());
    }

    redirect('modules/supervisors/reassign-letters.php');
}

$supervisors = dbFetchAll("SELECT u.id, u.full_name
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE r.code = 'supervisor'
      AND u.is_active = 1
      AND COALESCE(u.supervisor_status, 'active') = 'active'
    ORDER BY u.full_name");

$releasedLetters = dbFetchAll("SELECT h.id AS history_id, h.supervisor_id AS old_supervisor_id,
        h.letter_id, h.gender, h.ended_at, h.end_reason,
        l.code AS letter_code, u.full_name AS old_supervisor_name
    FROM supervisor_letter_assignment_history h
    INNER JOIN letters l ON l.id = h.letter_id
    INNER JOIN users u ON u.id = h.supervisor_id
    WHERE h.ended_at IS NOT NULL
      AND h.end_reason = 'supervisor_departure'
      AND h.id = (
          SELECT MAX(h2.id)
          FROM supervisor_letter_assignment_history h2
          WHERE h2.letter_id = h.letter_id AND h2.gender = h.gender
      )
      AND NOT EXISTS (
          SELECT 1
          FROM supervisor_letters sl
          WHERE sl.letter_id = h.letter_id AND sl.gender = h.gender
      )
    ORDER BY h.ended_at DESC, l.sort_order, h.gender");

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.ak-reassign-table { font-size: 13px; }
.ak-reassign-table th, .ak-reassign-table td { padding: 9px 10px; vertical-align: middle; }
.ak-letter-chip { display:inline-flex; min-width:42px; height:38px; align-items:center; justify-content:center; border-radius:9px; font-weight:800; font-size:17px; border:1px solid #d8e0ee; background:#eef2f9; }
</style>

<div class="welcome-section fade-in">
    <h2>إعادة تعيين الحروف المحررة</h2>
    <p>الحروف التي تم تحريرها بسبب مغادرة مشرف نهائياً تظهر هنا حتى يتم تعيينها يدوياً لمشرف آخر متاح للعمل.</p>
    <div class="quick-actions mt-3">
        <a href="<?php echo APP_URL; ?>modules/supervisors/assign-letters.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-table me-1"></i> مصفوفة الحروف
        </a>
        <a href="<?php echo APP_URL; ?>modules/supervisors/index.php" class="btn btn-outline-dark btn-sm">
            <i class="fas fa-users me-1"></i> المشرفون
        </a>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="alert alert-info fade-in py-2">
    <i class="fas fa-circle-info me-1"></i>
    <strong>قاعدة العمل:</strong> يمكن إعادة الحرف للمشرف الأصلي فقط إذا كان ما يزال نشطاً، أو تعيينه لمشرف نشط آخر. المشرف المؤرشف نهائياً لا يظهر كهدف للتعيين.
</div>

<div class="card fade-in">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <span><i class="fas fa-right-left me-2"></i>الحروف بانتظار إعادة التعيين</span>
        <span class="badge bg-warning text-dark"><?php echo count($releasedLetters); ?> تعيين</span>
    </div>
    <div class="card-body p-2 p-md-3">
        <?php if (!$releasedLetters): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-check-circle fa-2x mb-2 d-block"></i>
                لا توجد حروف محررة بانتظار إعادة التعيين.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 ak-reassign-table">
                    <thead>
                    <tr>
                        <th class="text-center">الحرف</th>
                        <th>الجنس</th>
                        <th>المشرف السابق</th>
                        <th>تاريخ التحرير</th>
                        <th style="min-width:260px">إعادة التعيين إلى</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($releasedLetters as $row): ?>
                        <tr>
                            <td class="text-center"><span class="ak-letter-chip"><?php echo e($row['letter_code']); ?></span></td>
                            <td>
                                <?php echo $row['gender'] === 'male' ? 'ذكور' : ($row['gender'] === 'female' ? 'إناث' : 'كلاهما'); ?>
                            </td>
                            <td>
                                <strong><?php echo e($row['old_supervisor_name']); ?></strong>
                                <small class="d-block text-muted">مغادرة نهائية</small>
                            </td>
                            <td><?php echo e($row['ended_at']); ?></td>
                            <td>
                                <?php if ($supervisors): ?>
                                    <form method="post" class="d-flex gap-2 align-items-center">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="history_id" value="<?php echo (int)$row['history_id']; ?>">
                                        <select name="supervisor_id" class="form-select form-select-sm" required>
                                            <option value="">— اختر المشرف —</option>
                                            <?php foreach ($supervisors as $sup): ?>
                                                <option value="<?php echo (int)$sup['id']; ?>"><?php echo e($sup['full_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm text-nowrap" title="إعادة التعيين">
                                            <i class="fas fa-right-left me-1"></i> تعيين
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">لا يوجد مشرف نشط متاح حالياً.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
