<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/lib_employee_incentives.php';

Session::start();
$userRole = Session::getUserRole();
if (!Session::isLoggedIn() || $userRole !== 'hr_manager') {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pdo = db();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf()) {
            throw new RuntimeException('انتهت صلاحية نموذج الحماية. أعد تحميل الصفحة وحاول مرة أخرى.');
        }

        $action = (string)($_POST['action'] ?? '');
        $eventId = (int)($_POST['event_id'] ?? 0);
        $note = trim((string)($_POST['verification_note'] ?? ''));
        if ($action !== 'verify_event' || $eventId <= 0) {
            throw new InvalidArgumentException('طلب مراجعة الحافز غير صالح.');
        }
        if (mb_strlen($note) > 2000) {
            throw new InvalidArgumentException('ملاحظة التحقق لا يجوز أن تتجاوز 2000 حرف.');
        }

        $pdo->beginTransaction();
        $eventStmt = $pdo->prepare(
            "SELECT ev.*, r.reward_amount, r.currency_code AS reward_currency_code,
                    r.is_enabled, t.name_ar AS incentive_name_ar, t.name_en AS incentive_name_en
             FROM hr_incentive_events ev
             JOIN hr_incentive_policy_rules r
               ON r.policy_version_id = ev.policy_version_id
              AND r.incentive_type = ev.incentive_type
             JOIN hr_incentive_types t ON t.type_code = ev.incentive_type
             WHERE ev.id = ? LIMIT 1 FOR UPDATE"
        );
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            throw new RuntimeException('حدث الحافز غير موجود.');
        }
        if ($event['status'] !== 'pending_verification') {
            throw new RuntimeException('تمت معالجة هذا الحدث مسبقاً؛ حدّث الصفحة لمراجعة حالته الحالية.');
        }
        if ($event['incentive_type'] === 'project_completion') {
            throw new RuntimeException('حوافز إكمال المشاريع موقوفة حتى اعتماد مسار توزيع الحصص على الموظفين.');
        }
        if ((int)$event['is_enabled'] !== 1 || (float)$event['reward_amount'] <= 0) {
            throw new RuntimeException('قاعدة الحافز المرتبطة بالحدث غير مفعلة أو مبلغها غير صالح.');
        }

        $recipientUserId = (int)($event['recipient_user_id'] ?? 0);
        $recipientEmployeeId = (int)($event['recipient_employee_id'] ?? 0);
        if ($recipientUserId <= 0 || $recipientEmployeeId <= 0) {
            throw new RuntimeException('لا توجد هوية مستفيد موثوقة محفوظة لهذا الحدث؛ لا يمكن إصدار الحافز.');
        }

        $employeeStmt = $pdo->prepare(
            "SELECT e.id, e.user_id, es.category, es.is_active AS state_is_active,
                    u.is_active AS user_is_active
             FROM employees e
             JOIN hr_employment_states es ON es.id = e.employment_state_id
             JOIN users u ON u.id = e.user_id
             WHERE e.id = ? LIMIT 1 FOR UPDATE"
        );
        $employeeStmt->execute([$recipientEmployeeId]);
        $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee
            || (int)$employee['user_id'] !== $recipientUserId
            || $employee['category'] !== 'working'
            || (int)$employee['state_is_active'] !== 1
            || (int)$employee['user_is_active'] !== 1) {
            throw new RuntimeException('المستفيد غير مرتبط بحساب المستخدم المحفوظ أو لم يعد موظفاً عاملاً مؤهلاً.');
        }

        $existingAwardStmt = $pdo->prepare(
            'SELECT id FROM hr_incentive_awards WHERE event_id = ? AND employee_id = ? LIMIT 1 FOR UPDATE'
        );
        $existingAwardStmt->execute([$eventId, $recipientEmployeeId]);
        if ($existingAwardStmt->fetchColumn()) {
            throw new RuntimeException('يوجد سجل حافز لهذا الحدث والموظف؛ تم إيقاف التكرار للمراجعة.');
        }

        $verifyStmt = $pdo->prepare(
            "UPDATE hr_incentive_events
             SET status = 'verified', verification_note = ?, verified_by_user_id = ?, verified_at = NOW()
             WHERE id = ? AND status = 'pending_verification'"
        );
        $verifyStmt->execute([$note !== '' ? $note : null, Session::getUserId(), $eventId]);
        if ($verifyStmt->rowCount() !== 1) {
            throw new RuntimeException('تغيرت حالة الحدث أثناء المراجعة؛ لم يتم إصدار الحافز.');
        }

        $awardStmt = $pdo->prepare(
            "INSERT INTO hr_incentive_awards
                (event_id, employee_id, recipient_user_id, reward_amount,
                 reward_currency_code, status, earned_at)
             VALUES (?, ?, ?, ?, ?, 'earned', ?)"
        );
        $awardStmt->execute([
            $eventId,
            $recipientEmployeeId,
            $recipientUserId,
            round((float)$event['reward_amount'], 2),
            strtoupper(trim((string)$event['reward_currency_code'])),
            (string)$event['occurred_at'],
        ]);
        $awardId = (int)$pdo->lastInsertId();

        // This helper recognizes only verified events. It joins the same outer
        // transaction, so the event, award, and recognition journal commit together.
        $recognitionEntryId = hrIncentiveRecognizeAward($pdo, $awardId, Session::getUserId());
        $pdo->commit();

        if ($recognitionEntryId > 0) {
            $message = 'تم التحقق من الإنجاز وإصدار الحافز وإثبات استحقاقه محاسبياً. سيُدرج في مسير مؤهل مفتوح بعد اجتياز فحص العملة.';
        } else {
            $message = 'تم التحقق من الإنجاز وحفظ الحافز، لكنه موقوف بسبب مبلغ أو عملة غير صالحة؛ لم يُنشأ قيد إثبات.';
            $messageType = 'warning';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
            ? $e->getMessage()
            : 'تعذرت معالجة الحدث. لم تُحفظ تغييرات هذه المحاولة.';
        $messageType = 'danger';
        error_log('HR incentive verification failed: ' . $e->getMessage());
    }
}

$events = dbFetchAll(
    "SELECT ev.id, ev.incentive_type, ev.source_table, ev.source_record_id,
            ev.occurred_at, ev.source_amount, ev.source_currency_code, ev.status,
            ev.recipient_user_id, ev.recipient_employee_id,
            t.name_ar AS incentive_name_ar, t.name_en AS incentive_name_en,
            r.reward_amount, r.currency_code AS reward_currency_code, r.is_enabled,
            e.full_name AS recipient_name, es.category AS employment_category,
            es.is_active AS employment_state_active, u.is_active AS user_active,
            a.id AS award_id, a.status AS award_status, a.hold_reason
     FROM hr_incentive_events ev
     JOIN hr_incentive_types t ON t.type_code = ev.incentive_type
     JOIN hr_incentive_policy_rules r
       ON r.policy_version_id = ev.policy_version_id
      AND r.incentive_type = ev.incentive_type
     LEFT JOIN employees e ON e.id = ev.recipient_employee_id
     LEFT JOIN hr_employment_states es ON es.id = e.employment_state_id
     LEFT JOIN users u ON u.id = ev.recipient_user_id
     LEFT JOIN hr_incentive_awards a
       ON a.event_id = ev.id AND a.employee_id = ev.recipient_employee_id
     WHERE ev.status IN ('pending_verification','verified','held')
     ORDER BY FIELD(ev.status,'pending_verification','held','verified'), ev.occurred_at ASC, ev.id ASC
     LIMIT 250"
);

$pageTitle = 'التحقق من حوافز الموظفين';
$active = 'hr';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="container-fluid py-3" dir="rtl">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">التحقق من حوافز الموظفين</h1>
            <p class="text-muted mb-0">تحقق من الإنجاز وهوية المستفيد قبل إصدار الحافز. لا تُعالج حوافز المشاريع قبل اعتماد حصص المستفيدين.</p>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo e(APP_URL); ?>modules/hr/incentive_policy.php">سياسة الحوافز</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo e($messageType); ?>" data-ak-transient-alert="1"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if (!$events): ?>
                <div class="text-center text-muted py-5">لا توجد أحداث حوافز بانتظار المراجعة.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>الحدث</th><th>المستفيد المحفوظ</th><th>المصدر</th>
                                <th>تاريخ الاستحقاق</th><th>مبلغ السياسة</th><th>الحالة</th><th>الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($events as $event): ?>
                            <?php
                            $isProject = $event['incentive_type'] === 'project_completion';
                            $recipientEligible = (int)($event['recipient_user_id'] ?? 0) > 0
                                && (int)($event['recipient_employee_id'] ?? 0) > 0
                                && $event['recipient_name'] !== null
                                && $event['employment_category'] === 'working'
                                && (int)$event['employment_state_active'] === 1
                                && (int)$event['user_active'] === 1;
                            $canVerify = $event['status'] === 'pending_verification'
                                && !$isProject && $recipientEligible
                                && (int)$event['is_enabled'] === 1
                                && (float)$event['reward_amount'] > 0;
                            $typeName = AK_LANG === 'en' && !empty($event['incentive_name_en'])
                                ? $event['incentive_name_en'] : ($event['incentive_name_ar'] ?? $event['incentive_type']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($typeName); ?></strong>
                                    <div class="small text-muted">#<?php echo (int)$event['id']; ?> · <?php echo e($event['incentive_type']); ?></div>
                                </td>
                                <td>
                                    <?php echo e($event['recipient_name'] ?? 'غير محدد'); ?>
                                    <div class="small text-muted">موظف #<?php echo (int)($event['recipient_employee_id'] ?? 0); ?></div>
                                </td>
                                <td><?php echo e($event['source_table']); ?> #<?php echo (int)$event['source_record_id']; ?></td>
                                <td><?php echo e($event['occurred_at']); ?></td>
                                <td><?php echo number_format((float)$event['reward_amount'], 2); ?> <?php echo e($event['reward_currency_code']); ?></td>
                                <td>
                                    <?php if ($event['status'] === 'pending_verification'): ?>
                                        <span class="badge bg-warning text-dark">بانتظار التحقق</span>
                                    <?php elseif ($event['status'] === 'held' || $event['award_status'] === 'held_currency'): ?>
                                        <span class="badge bg-danger">موقوف</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">تم التحقق</span>
                                    <?php endif; ?>
                                    <?php if (!empty($event['hold_reason'])): ?><div class="small text-danger mt-1"><?php echo e($event['hold_reason']); ?></div><?php endif; ?>
                                </td>
                                <td style="min-width:220px">
                                    <?php if ($isProject && $event['status'] === 'pending_verification'): ?>
                                        <span class="small text-warning">موقوف حتى اعتماد توزيع الحصص.</span>
                                    <?php elseif ($event['status'] === 'pending_verification' && !$recipientEligible): ?>
                                        <span class="small text-danger">هوية المستفيد أو حالة التوظيف تحتاج مراجعة.</span>
                                    <?php elseif ($canVerify): ?>
                                        <form method="post" class="d-grid gap-2">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="verify_event">
                                            <input type="hidden" name="event_id" value="<?php echo (int)$event['id']; ?>">
                                            <input type="text" name="verification_note" class="form-control form-control-sm" maxlength="2000" placeholder="ملاحظة التحقق (اختيارية)">
                                            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('تأكيد الإنجاز وإصدار الحافز لهذا الموظف؟');">تحقق وأصدر الحافز</button>
                                        </form>
                                    <?php elseif ($event['status'] === 'pending_verification'): ?>
                                        <span class="small text-muted">قاعدة السياسة غير مفعلة أو المبلغ غير صالح.</span>
                                    <?php elseif (!empty($event['award_id'])): ?>
                                        <span class="small text-muted">الحافز #<?php echo (int)$event['award_id']; ?> · <?php echo e($event['award_status'] ?? ''); ?></span>
                                    <?php else: ?>
                                        <span class="small text-muted">لا يوجد إجراء متاح لهذه الحالة.</span>
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
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
