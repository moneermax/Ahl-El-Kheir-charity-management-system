<?php
/**
 * Deputy General Manager / VGM - Orphan Groups Management
 *
 * Group lifecycle:
 * draft -> active <-> empty -> suspended -> closed -> archived
 * cancelled is a terminal state for groups created/used in error.
 *
 * Important: group/member/nanny/payment history is never deleted by lifecycle actions.
 * Physical group deletion is allowed only when the group has never been used.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';

Session::start();
$user_role = Session::getUserRole();
if (!Session::isLoggedIn() || ($user_role !== 'vice_general_manager' && $user_role !== 'vgm')) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pdo = db();
$current_month = date('Y-m');
$actor_id = (int) Session::getUserId();
$success_message = '';
$error_message = '';
$view_group_id = isset($_GET['view_group']) ? (int) $_GET['view_group'] : (int) ($_POST['view_group'] ?? 0);

function groupStatusLabel(string $status): string {
    return [
        'draft' => 'مسودة',
        'active' => 'نشطة',
        'empty' => 'فارغة',
        'suspended' => 'موقوفة',
        'closed' => 'مغلقة',
        'cancelled' => 'ملغاة',
        'archived' => 'مؤرشفة'
    ][$status] ?? $status;
}

function groupStatusClass(string $status): string {
    return [
        'draft' => 'secondary',
        'active' => 'success',
        'empty' => 'warning',
        'suspended' => 'danger',
        'closed' => 'dark',
        'cancelled' => 'danger',
        'archived' => 'secondary'
    ][$status] ?? 'secondary';
}

function groupCanChangeMembers(string $status): bool {
    return in_array($status, ['draft', 'active', 'empty'], true);
}

function groupHasCurrentDisbursement(PDO $pdo, int $group_id, string $month): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM monthly_disbursements WHERE group_id = :group_id AND month = :month LIMIT 1');
    $stmt->execute(['group_id' => $group_id, 'month' => $month]);
    return (bool) $stmt->fetchColumn();
}

function groupUsage(PDO $pdo, int $group_id): array {
    $stmt = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM group_children WHERE group_id = :id1) AS member_history,
            (SELECT COUNT(*) FROM nanny_group_assignments WHERE group_id = :id2) AS nanny_history,
            (SELECT COUNT(*) FROM monthly_disbursements WHERE group_id = :id3) AS disbursement_history'
    );
    $stmt->execute(['id1' => $group_id, 'id2' => $group_id, 'id3' => $group_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'member_history' => (int) ($row['member_history'] ?? 0),
        'nanny_history' => (int) ($row['nanny_history'] ?? 0),
        'disbursement_history' => (int) ($row['disbursement_history'] ?? 0)
    ];
}

function groupUsed(array $usage): bool {
    return ($usage['member_history'] + $usage['nanny_history'] + $usage['disbursement_history']) > 0;
}

function syncGroupState(PDO $pdo, int $group_id, int $actor_id): void {
    $stmt = $pdo->prepare('SELECT status FROM orphan_groups WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) return;

    if (in_array($group['status'], ['active', 'empty'], true)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM group_children WHERE group_id = :id AND left_date IS NULL');
        $stmt->execute(['id' => $group_id]);
        $has_members = (int) $stmt->fetchColumn() > 0;
        $new_status = $has_members ? 'active' : 'empty';
        $pdo->prepare('UPDATE orphan_groups SET status = :status, is_active = 1, updated_at = NOW() WHERE id = :id')
            ->execute(['status' => $new_status, 'id' => $group_id]);
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $group_id = (int) ($_POST['group_id'] ?? 0);

        if ($action === 'create_group') {
            $group_name = trim((string) ($_POST['group_name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($group_name === '') {
                throw new RuntimeException('اسم المجموعة مطلوب');
            }
            $stmt = $pdo->prepare(
                "INSERT INTO orphan_groups (group_name, description, created_by, is_active, status, updated_at)
                 VALUES (:name, :description, :created_by, 0, 'draft', NOW())"
            );
            $stmt->execute(['name' => $group_name, 'description' => $description, 'created_by' => $actor_id]);
            $view_group_id = (int) $pdo->lastInsertId();
            $success_message = 'تم إنشاء المجموعة كمسودة. أضف الأعضاء ثم فعّل المجموعة عند جاهزيتها.';
        }

        elseif ($action === 'update_group') {
            $name = trim((string) ($_POST['group_name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($group_id <= 0 || $name === '') throw new RuntimeException('بيانات المجموعة غير مكتملة');
            $stmt = $pdo->prepare('SELECT status FROM orphan_groups WHERE id = :id');
            $stmt->execute(['id' => $group_id]);
            $status = $stmt->fetchColumn();
            if (!$status) throw new RuntimeException('المجموعة غير موجودة');
            if (in_array($status, ['closed', 'cancelled', 'archived'], true)) {
                throw new RuntimeException('لا يمكن تعديل مجموعة مغلقة أو ملغاة أو مؤرشفة');
            }
            $pdo->prepare('UPDATE orphan_groups SET group_name = :name, description = :description, updated_at = NOW() WHERE id = :id')
                ->execute(['name' => $name, 'description' => $description, 'id' => $group_id]);
            $success_message = 'تم تحديث بيانات المجموعة';
        }

        elseif (in_array($action, ['activate_group', 'suspend_group', 'close_group', 'cancel_group', 'archive_group'], true)) {
            if ($group_id <= 0) throw new RuntimeException('المجموعة غير محددة');
            $stmt = $pdo->prepare('SELECT * FROM orphan_groups WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $group_id]);
            $group = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$group) throw new RuntimeException('المجموعة غير موجودة');

            $usage = groupUsage($pdo, $group_id);
            $current_members_stmt = $pdo->prepare('SELECT COUNT(*) FROM group_children WHERE group_id = :id AND left_date IS NULL');
            $current_members_stmt->execute(['id' => $group_id]);
            $current_members = (int) $current_members_stmt->fetchColumn();
            $has_financial_history = $usage['disbursement_history'] > 0;
            $new_status = [
                'activate_group' => 'active',
                'suspend_group' => 'suspended',
                'close_group' => 'closed',
                'cancel_group' => 'cancelled',
                'archive_group' => 'archived'
            ][$action];

            if ($new_status === 'active') {
                if (in_array($group['status'], ['closed', 'cancelled', 'archived'], true)) {
                    throw new RuntimeException('لا يمكن إعادة فتح هذه المجموعة بهذه العملية');
                }
                if ($current_members === 0) throw new RuntimeException('لا يمكن تفعيل مجموعة فارغة. أضف أعضاء أولاً.');
                $pdo->prepare("UPDATE orphan_groups SET status = 'active', is_active = 1, lifecycle_reason = :reason, updated_at = NOW() WHERE id = :id")
                    ->execute(['reason' => trim((string) ($_POST['reason'] ?? 'تفعيل المجموعة')), 'id' => $group_id]);
                $success_message = 'تم تفعيل المجموعة';
            } else {
                if ($new_status === 'archive' && $group['status'] === 'archived') {
                    $success_message = 'المجموعة مؤرشفة بالفعل';
                }
                if ($new_status === 'cancelled' && $has_financial_history) {
                    throw new RuntimeException('لا يمكن إلغاء مجموعة لها سجل صرف مالي. استخدم الإغلاق بدلاً من ذلك.');
                }
                $pdo->prepare('UPDATE orphan_groups SET status = :status, is_active = 0, lifecycle_reason = :reason, updated_at = NOW(), closed_at = CASE WHEN :status2 IN (\'closed\',\'cancelled\',\'archived\') THEN COALESCE(closed_at, NOW()) ELSE closed_at END, closed_by = CASE WHEN :status3 IN (\'closed\',\'cancelled\',\'archived\') THEN :actor ELSE closed_by END WHERE id = :id')
                    ->execute([
                        'status' => $new_status,
                        'status2' => $new_status,
                        'status3' => $new_status,
                        'reason' => trim((string) ($_POST['reason'] ?? '')),
                        'actor' => $actor_id,
                        'id' => $group_id
                    ]);
                $success_message = [
                    'suspended' => 'تم إيقاف المجموعة مؤقتاً',
                    'closed' => 'تم إغلاق المجموعة مع الحفاظ على كامل تاريخها',
                    'cancelled' => 'تم إلغاء المجموعة مع الحفاظ على سجلها',
                    'archived' => 'تم أرشفة المجموعة'
                ][$new_status];
            }
        }

        elseif ($action === 'assign_nanny') {
            $nanny_id = (int) ($_POST['nanny_id'] ?? 0);
            if ($group_id <= 0 || $nanny_id <= 0) throw new RuntimeException('يرجى اختيار المجموعة والحاضنة');
            $stmt = $pdo->prepare('SELECT status FROM orphan_groups WHERE id = :id');
            $stmt->execute(['id' => $group_id]);
            $status = $stmt->fetchColumn();
            if (!$status || !groupCanChangeMembers($status)) throw new RuntimeException('لا يمكن تعيين حاضنة لمجموعة غير تشغيلية');
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE nanny_group_assignments SET end_date = CURDATE() WHERE group_id = :id AND end_date IS NULL')
                ->execute(['id' => $group_id]);
            $pdo->prepare('INSERT INTO nanny_group_assignments (group_id, nanny_id, start_date, assigned_by) VALUES (:group_id, :nanny_id, CURDATE(), :actor)')
                ->execute(['group_id' => $group_id, 'nanny_id' => $nanny_id, 'actor' => $actor_id]);
            $pdo->commit();
            $success_message = 'تم تحديث الحاضنة مع الحفاظ على سجل التعيينات السابق';
        }

        elseif (in_array($action, ['add_orphan', 'add_orphan_bulk'], true)) {
            if ($group_id <= 0) throw new RuntimeException('المجموعة غير محددة');
            $stmt = $pdo->prepare('SELECT status FROM orphan_groups WHERE id = :id');
            $stmt->execute(['id' => $group_id]);
            $status = $stmt->fetchColumn();
            if (!$status || !groupCanChangeMembers($status)) throw new RuntimeException('لا يمكن إضافة أعضاء إلى مجموعة غير تشغيلية');
            if (groupHasCurrentDisbursement($pdo, $group_id, $current_month)) {
                throw new RuntimeException('لا يمكن تعديل أعضاء هذه المجموعة بعد إنشاء صرف الشهر الحالي. يتم نقل أي تغيير مالي إلى دورة الشهر التالية.');
            }
            $child_ids = $action === 'add_orphan' ? [(int) ($_POST['child_id'] ?? 0)] : array_map('intval', (array) ($_POST['child_ids'] ?? []));
            $child_ids = array_values(array_unique(array_filter($child_ids)));
            if (!$child_ids) throw new RuntimeException('لم يتم اختيار أي يتيم');

            $pdo->beginTransaction();
            $check = $pdo->prepare(
                "SELECT fc.id
                 FROM family_children fc
                 JOIN families f ON f.id = fc.family_id AND f.status = 'active'
                 JOIN sponsorships sp ON sp.child_id = fc.id AND sp.status = 'active'
                 WHERE fc.id = :child_id AND fc.is_active = 1
                   AND NOT EXISTS (
                       SELECT 1 FROM group_children gc
                       JOIN monthly_disbursements md ON md.group_id = gc.group_id AND md.month = :month
                       WHERE gc.child_id = fc.id AND gc.left_date IS NULL
                   )"
            );
            $insert = $pdo->prepare('INSERT INTO group_children (group_id, child_id, joined_date, added_by) VALUES (:group_id, :child_id, CURDATE(), :actor)');
            $added = 0;
            foreach ($child_ids as $child_id) {
                $check->execute(['child_id' => $child_id, 'month' => $current_month]);
                if (!$check->fetchColumn()) continue;
                try {
                    $insert->execute(['group_id' => $group_id, 'child_id' => $child_id, 'actor' => $actor_id]);
                    $added++;
                } catch (PDOException $e) {
                    if ((string) $e->getCode() !== '23000') throw $e;
                }
            }
            if ($added === 0) throw new RuntimeException('لم تتم إضافة أي عضو. قد يكون اليتيم غير مؤهل أو مرتبطاً بصرف الشهر الحالي أو موجوداً بالفعل.');
            syncGroupState($pdo, $group_id, $actor_id);
            $pdo->commit();
            $success_message = "تم إضافة {$added} يتيم للمجموعة";
        }

        elseif ($action === 'remove_orphan') {
            $child_id = (int) ($_POST['child_id'] ?? 0);
            if ($group_id <= 0 || $child_id <= 0) throw new RuntimeException('بيانات الإزالة غير مكتملة');
            if (groupHasCurrentDisbursement($pdo, $group_id, $current_month)) {
                throw new RuntimeException('لا يمكن إزالة عضو من المجموعة بعد إنشاء صرف الشهر الحالي. يحافظ النظام على سجل الصرف ولا يعدله بأثر رجعي.');
            }
            $stmt = $pdo->prepare('SELECT status FROM orphan_groups WHERE id = :id');
            $stmt->execute(['id' => $group_id]);
            $status = $stmt->fetchColumn();
            if (!$status || !groupCanChangeMembers($status)) throw new RuntimeException('لا يمكن تعديل أعضاء مجموعة غير تشغيلية');
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $stmt = $pdo->prepare('UPDATE group_children SET left_date = CURDATE() WHERE group_id = :group_id AND child_id = :child_id AND left_date IS NULL');
            $stmt->execute(['group_id' => $group_id, 'child_id' => $child_id]);
            if ($stmt->rowCount() === 0) throw new RuntimeException('العضو غير موجود كعضو حالي في هذه المجموعة');
            syncGroupState($pdo, $group_id, $actor_id);
            $success_message = $reason !== '' ? 'تم إزالة اليتيم وتسجيل سبب الإزالة' : 'تم إزالة اليتيم من المجموعة مع الحفاظ على سجل العضوية';
        }

        elseif ($action === 'transfer_orphan') {
            $child_id = (int) ($_POST['child_id'] ?? 0);
            $target_group_id = (int) ($_POST['target_group_id'] ?? 0);
            if ($group_id <= 0 || $child_id <= 0 || $target_group_id <= 0 || $target_group_id === $group_id) throw new RuntimeException('بيانات النقل غير مكتملة');
            if (groupHasCurrentDisbursement($pdo, $group_id, $current_month) || groupHasCurrentDisbursement($pdo, $target_group_id, $current_month)) {
                throw new RuntimeException('لا يمكن نقل عضو بين مجموعات مرتبطة بصرف الشهر الحالي. يتم تنفيذ التغيير من الدورة التالية.');
            }
            $statuses = $pdo->prepare('SELECT id, status FROM orphan_groups WHERE id IN (:source, :target)');
            $statuses->execute(['source' => $group_id, 'target' => $target_group_id]);
            $status_map = [];
            foreach ($statuses->fetchAll(PDO::FETCH_ASSOC) as $row) $status_map[(int) $row['id']] = $row['status'];
            if (!isset($status_map[$group_id], $status_map[$target_group_id]) || !groupCanChangeMembers($status_map[$group_id]) || !groupCanChangeMembers($status_map[$target_group_id])) {
                throw new RuntimeException('النقل متاح فقط بين مجموعات تشغيلية');
            }
            $pdo->beginTransaction();
            $check = $pdo->prepare('SELECT 1 FROM group_children WHERE group_id = :group_id AND child_id = :child_id AND left_date IS NULL LIMIT 1');
            $check->execute(['group_id' => $group_id, 'child_id' => $child_id]);
            if (!$check->fetchColumn()) throw new RuntimeException('اليتيم ليس عضواً حالياً في المجموعة المصدر');
            $check->execute(['group_id' => $target_group_id, 'child_id' => $child_id]);
            if ($check->fetchColumn()) throw new RuntimeException('اليتيم لديه سجل عضوية سابق في المجموعة الهدف');
            $pdo->prepare('UPDATE group_children SET left_date = CURDATE() WHERE group_id = :group_id AND child_id = :child_id AND left_date IS NULL')
                ->execute(['group_id' => $group_id, 'child_id' => $child_id]);
            $pdo->prepare('INSERT INTO group_children (group_id, child_id, joined_date, added_by) VALUES (:group_id, :child_id, CURDATE(), :actor)')
                ->execute(['group_id' => $target_group_id, 'child_id' => $child_id, 'actor' => $actor_id]);
            syncGroupState($pdo, $group_id, $actor_id);
            syncGroupState($pdo, $target_group_id, $actor_id);
            $pdo->commit();
            $success_message = 'تم نقل اليتيم مع حفظ نهاية العضوية القديمة وبداية العضوية الجديدة';
            $view_group_id = $target_group_id;
        }

        elseif ($action === 'delete_group') {
            if ($group_id <= 0) throw new RuntimeException('المجموعة غير محددة');
            $stmt = $pdo->prepare('SELECT * FROM orphan_groups WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $group_id]);
            $group = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$group) throw new RuntimeException('المجموعة غير موجودة');
            $usage = groupUsage($pdo, $group_id);
            if (groupUsed($usage)) {
                throw new RuntimeException('لا يمكن حذف هذه المجموعة لأنها استُخدمت بالفعل. استخدم الإغلاق أو الإلغاء للحفاظ على السجل.');
            }
            if (!in_array($group['status'], ['draft', 'empty'], true)) throw new RuntimeException('الحذف متاح فقط لمجموعة لم تُستخدم مطلقاً');
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM orphan_groups WHERE id = :id')->execute(['id' => $group_id]);
            $pdo->commit();
            $success_message = 'تم حذف المجموعة نهائياً لأنها لم تُستخدم ولم يكن لها أي سجل مرتبط';
            $view_group_id = 0;
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $error_message = $e instanceof RuntimeException ? $e->getMessage() : 'تعذر تنفيذ العملية. لم يتم فقد أي بيانات.';
}

$groups = dbFetchAll(
    "SELECT og.*, u.full_name AS nanny_name,
            nga.start_date AS assignment_start_date,
            (SELECT COUNT(*) FROM group_children gc WHERE gc.group_id = og.id AND gc.left_date IS NULL) AS orphan_count,
            (SELECT COUNT(*) FROM monthly_disbursements md WHERE md.group_id = og.id) AS disbursement_count
     FROM orphan_groups og
     LEFT JOIN nanny_group_assignments nga ON nga.group_id = og.id AND nga.end_date IS NULL
     LEFT JOIN users u ON u.id = nga.nanny_id
     ORDER BY FIELD(og.status, 'active','empty','draft','suspended','closed','cancelled','archived'), og.created_at DESC"
);

$nannies = dbFetchAll("SELECT u.id, u.full_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = 'nanny' AND u.is_active = 1 ORDER BY u.full_name ASC");

$orphans = dbFetchAll(
    "SELECT fc.id, fc.child_name, f.id AS family_id, f.mother_name AS family_name, f.family_code,
            TIMESTAMPDIFF(YEAR, fc.birth_date, CURDATE()) AS age,
            sp.sponsor_id, s.full_name AS sponsor_name
     FROM family_children fc
     JOIN families f ON f.id = fc.family_id AND f.status = 'active'
     JOIN sponsorships sp ON sp.child_id = fc.id AND sp.status = 'active'
     JOIN sponsors s ON s.id = sp.sponsor_id
     WHERE fc.is_active = 1
       AND NOT EXISTS (
           SELECT 1 FROM group_children gc
           JOIN monthly_disbursements md ON md.group_id = gc.group_id AND md.month = :month
           WHERE gc.child_id = fc.id AND gc.left_date IS NULL
       )
     ORDER BY f.mother_name ASC, fc.child_name ASC",
    ['month' => $current_month]
);

$selected_group = null;
$group_orphans = [];
$group_history = [];
$group_usage = ['member_history' => 0, 'nanny_history' => 0, 'disbursement_history' => 0];
$target_groups = [];

if ($view_group_id > 0) {
    $selected_group = dbFetchOne('SELECT * FROM orphan_groups WHERE id = :id', ['id' => $view_group_id]);
    if ($selected_group) {
        $group_orphans = dbFetchAll(
            "SELECT gc.*, fc.child_name, f.mother_name AS family_name,
                    TIMESTAMPDIFF(YEAR, fc.birth_date, CURDATE()) AS age
             FROM group_children gc
             JOIN family_children fc ON fc.id = gc.child_id
             LEFT JOIN families f ON f.id = fc.family_id
             WHERE gc.group_id = :group_id AND gc.left_date IS NULL
             ORDER BY fc.child_name ASC",
            ['group_id' => $view_group_id]
        );
        $group_history = dbFetchAll(
            "SELECT nga.*, u.full_name AS nanny_name, u2.full_name AS assigned_by_name
             FROM nanny_group_assignments nga
             JOIN users u ON u.id = nga.nanny_id
             LEFT JOIN users u2 ON u2.id = nga.assigned_by
             WHERE nga.group_id = :group_id ORDER BY nga.start_date DESC, nga.id DESC",
            ['group_id' => $view_group_id]
        );
        $group_usage = groupUsage($pdo, $view_group_id);
        $target_groups = dbFetchAll(
            "SELECT og.id, og.group_name, og.status,
                    (SELECT COUNT(*) FROM group_children gc WHERE gc.group_id = og.id AND gc.left_date IS NULL) AS orphan_count
             FROM orphan_groups og
             WHERE og.id <> :id AND og.status IN ('active','empty')
               AND NOT EXISTS (SELECT 1 FROM monthly_disbursements md WHERE md.group_id = og.id AND md.month = :month)
             ORDER BY og.group_name",
            ['id' => $view_group_id, 'month' => $current_month]
        );
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h2 class="mb-1"><i class="bi bi-people-fill"></i> إدارة مجموعات الأيتام</h2>
            <div class="text-muted small">إدارة دورة حياة المجموعة والعضوية والحاضنة مع حماية السجلات المالية</div>
        </div>
        <div class="small text-muted">دورة الصرف الحالية: <strong><?php echo htmlspecialchars($current_month); ?></strong></div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show py-2"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show py-2"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-4 col-xl-3">
            <div class="card mb-3">
                <div class="card-header py-2"><strong><i class="bi bi-plus-circle"></i> إنشاء مجموعة</strong></div>
                <div class="card-body">
                    <form method="POST" data-confirm="هل تريد إنشاء مجموعة جديدة كمسودة؟">
                        <input type="hidden" name="action" value="create_group">
                        <div class="mb-2"><label class="form-label small">اسم المجموعة</label><input type="text" class="form-control form-control-sm" name="group_name" required maxlength="150"></div>
                        <div class="mb-2"><label class="form-label small">الوصف</label><textarea class="form-control form-control-sm" name="description" rows="2" maxlength="1000"></textarea></div>
                        <button class="btn btn-primary btn-sm w-100"><i class="bi bi-save"></i> إنشاء كمسودة</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2"><strong><i class="bi bi-list-ul"></i> المجموعات</strong></div>
                <div class="card-body p-0">
                    <?php if (!$groups): ?><div class="text-center text-muted py-4">لا توجد مجموعات</div><?php endif; ?>
                    <div class="list-group list-group-flush">
                    <?php foreach ($groups as $group): ?>
                        <a href="?view_group=<?php echo (int)$group['id']; ?>" class="list-group-item list-group-item-action <?php echo ($selected_group && (int)$selected_group['id'] === (int)$group['id']) ? 'active' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2"><strong class="small"><?php echo htmlspecialchars($group['group_name']); ?></strong><span class="badge bg-<?php echo groupStatusClass($group['status']); ?>"><?php echo groupStatusLabel($group['status']); ?></span></div>
                            <div class="small mt-1 <?php echo ($selected_group && (int)$selected_group['id'] === (int)$group['id']) ? '' : 'text-muted'; ?>">
                                <?php echo (int)$group['orphan_count']; ?> عضو
                                <?php if ($group['nanny_name']): ?> · <?php echo htmlspecialchars($group['nanny_name']); ?><?php else: ?> · بدون حاضنة<?php endif; ?>
                                <?php if ((int)$group['disbursement_count'] > 0): ?> · <i class="bi bi-cash-stack"></i> <?php echo (int)$group['disbursement_count']; ?> صرف<?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-xl-9">
        <?php if ($selected_group): ?>
            <?php $status = (string)$selected_group['status']; $current_members = count($group_orphans); $has_current_disbursement = groupHasCurrentDisbursement($pdo, (int)$selected_group['id'], $current_month); ?>
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div><h5 class="mb-1"><i class="bi bi-folder2-open"></i> <?php echo htmlspecialchars($selected_group['group_name']); ?></h5><span class="badge bg-<?php echo groupStatusClass($status); ?>"><?php echo groupStatusLabel($status); ?></span></div>
                    <div class="d-flex gap-1 flex-wrap">
                        <?php if (in_array($status, ['draft','empty','suspended'], true) && $current_members > 0): ?><form method="POST" class="d-inline" data-confirm="هل تريد تفعيل هذه المجموعة؟"><input type="hidden" name="action" value="activate_group"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><button class="btn btn-success btn-sm">تفعيل</button></form><?php endif; ?>
                        <?php if (in_array($status, ['active','empty'], true)): ?><form method="POST" class="d-inline" data-confirm="هل تريد إيقاف المجموعة مؤقتاً؟"><input type="hidden" name="action" value="suspend_group"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><button class="btn btn-outline-warning btn-sm">إيقاف مؤقت</button></form><?php endif; ?>
                        <?php if (in_array($status, ['active','empty','suspended'], true)): ?><form method="POST" class="d-inline" data-confirm="سيتم إغلاق المجموعة مع الاحتفاظ بكل تاريخها. هل تريد المتابعة؟"><input type="hidden" name="action" value="close_group"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><button class="btn btn-outline-dark btn-sm">إغلاق</button></form><?php endif; ?>
                        <?php if (in_array($status, ['draft','empty'], true) && !$group_usage['disbursement_history']): ?><form method="POST" class="d-inline" data-confirm="هذه العملية تحذف المجموعة نهائياً ولا يمكن التراجع عنها. لا يُسمح بها إلا لأنها لم تُستخدم. هل تريد المتابعة؟"><input type="hidden" name="action" value="delete_group"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><button class="btn btn-outline-danger btn-sm">حذف نهائي</button></form><?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-3"><div class="border rounded p-2"><div class="small text-muted">الأعضاء الحاليون</div><strong><?php echo $current_members; ?></strong></div></div>
                        <div class="col-md-3"><div class="border rounded p-2"><div class="small text-muted">سجل العضويات</div><strong><?php echo (int)$group_usage['member_history']; ?></strong></div></div>
                        <div class="col-md-3"><div class="border rounded p-2"><div class="small text-muted">سجل الحاضنات</div><strong><?php echo (int)$group_usage['nanny_history']; ?></strong></div></div>
                        <div class="col-md-3"><div class="border rounded p-2"><div class="small text-muted">دفعات الصرف</div><strong><?php echo (int)$group_usage['disbursement_history']; ?></strong></div></div>
                    </div>
                    <?php if ($has_current_disbursement): ?><div class="alert alert-warning py-2 small mb-3"><i class="bi bi-shield-lock"></i> <strong>حماية الصرف:</strong> تم إنشاء صرف للشهر الحالي، لذلك يمنع النظام تعديل أعضاء هذه المجموعة حتى لا تتغير البيانات المالية بأثر رجعي.</div><?php endif; ?>
                    <p class="text-muted small mb-3"><?php echo nl2br(htmlspecialchars($selected_group['description'] ?: 'لا يوجد وصف')); ?></p>
                    <form method="POST" class="row g-2 mb-3">
                        <input type="hidden" name="action" value="update_group"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>">
                        <div class="col-md-4"><input class="form-control form-control-sm" name="group_name" value="<?php echo htmlspecialchars($selected_group['group_name']); ?>" <?php echo in_array($status,['closed','cancelled','archived'],true) ? 'disabled' : ''; ?> required></div>
                        <div class="col-md-6"><input class="form-control form-control-sm" name="description" value="<?php echo htmlspecialchars($selected_group['description'] ?? ''); ?>" <?php echo in_array($status,['closed','cancelled','archived'],true) ? 'disabled' : ''; ?>></div>
                        <div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100" <?php echo in_array($status,['closed','cancelled','archived'],true) ? 'disabled' : ''; ?>>حفظ البيانات</button></div>
                    </form>
                </div>
            </div>

            <?php if (groupCanChangeMembers($status)): ?>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="card h-100"><div class="card-header py-2"><strong>الحاضنة الحالية</strong></div><div class="card-body">
                        <?php $current_nanny = $group_history[0] ?? null; if ($current_nanny && empty($current_nanny['end_date'])): ?><div class="mb-2"><i class="bi bi-person-check"></i> <?php echo htmlspecialchars($current_nanny['nanny_name']); ?></div><?php else: ?><div class="text-muted small mb-2">لا توجد حاضنة حالية</div><?php endif; ?>
                        <form method="POST" class="d-flex gap-2"><input type="hidden" name="action" value="assign_nanny"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><select class="form-select form-select-sm" name="nanny_id" required><option value="">اختر الحاضنة</option><?php foreach ($nannies as $nanny): ?><option value="<?php echo (int)$nanny['id']; ?>"><?php echo htmlspecialchars($nanny['full_name']); ?></option><?php endforeach; ?></select><button class="btn btn-primary btn-sm">تعيين</button></form>
                    </div></div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100"><div class="card-header py-2"><strong>إضافة أعضاء</strong></div><div class="card-body">
                        <form method="POST"><input type="hidden" name="action" value="add_orphan_bulk"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><select class="form-select form-select-sm mb-2" name="child_ids[]" multiple size="5" <?php echo $has_current_disbursement ? 'disabled' : ''; ?>><?php foreach ($orphans as $orphan): ?><option value="<?php echo (int)$orphan['id']; ?>"><?php echo htmlspecialchars($orphan['child_name']); ?> — <?php echo htmlspecialchars($orphan['family_name']); ?><?php if ($orphan['sponsor_name']): ?> — <?php echo htmlspecialchars($orphan['sponsor_name']); ?><?php endif; ?></option><?php endforeach; ?></select><div class="small text-muted mb-2">يمكن اختيار أكثر من يتيم. اليتيم المرتبط بصرف الشهر الحالي لا يظهر هنا.</div><button class="btn btn-success btn-sm" <?php echo $has_current_disbursement ? 'disabled' : ''; ?>>إضافة المختارين</button></form>
                    </div></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between"><strong>الأعضاء الحاليون</strong><span class="badge bg-secondary"><?php echo $current_members; ?></span></div>
                <div class="card-body p-0">
                    <?php if (!$group_orphans): ?><div class="text-center text-muted py-4">لا يوجد أعضاء حاليون. هذه المجموعة تظهر كـ «فارغة» بعد المزامنة.</div><?php else: ?>
                    <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>اليتيم</th><th>الأسرة</th><th>العمر</th><th>الانضمام</th><th class="text-end">الإجراءات</th></tr></thead><tbody>
                    <?php foreach ($group_orphans as $orphan): ?><tr><td><?php echo htmlspecialchars($orphan['child_name']); ?></td><td><?php echo htmlspecialchars($orphan['family_name']); ?></td><td><?php echo (int)$orphan['age']; ?></td><td><?php echo htmlspecialchars($orphan['joined_date']); ?></td><td class="text-end">
                        <?php if (groupCanChangeMembers($status) && !$has_current_disbursement): ?><form method="POST" class="d-inline" data-confirm="هل تريد نقل هذا اليتيم إلى مجموعة أخرى؟"><input type="hidden" name="action" value="transfer_orphan"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><input type="hidden" name="child_id" value="<?php echo (int)$orphan['child_id']; ?>"><select name="target_group_id" class="form-select form-select-sm d-inline-block" style="width:180px" required><option value="">نقل إلى...</option><?php foreach ($target_groups as $target): ?><option value="<?php echo (int)$target['id']; ?>"><?php echo htmlspecialchars($target['group_name']); ?> (<?php echo (int)$target['orphan_count']; ?>)</option><?php endforeach; ?></select><button class="btn btn-outline-primary btn-sm">نقل</button></form>
                        <form method="POST" class="d-inline" data-confirm="سيتم إنهاء العضوية الحالية مع الاحتفاظ بسجلها. هل تريد المتابعة؟"><input type="hidden" name="action" value="remove_orphan"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><input type="hidden" name="child_id" value="<?php echo (int)$orphan['child_id']; ?>"><button class="btn btn-outline-danger btn-sm">إزالة</button></form><?php endif; ?>
                    </td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2"><strong>سجل الحاضنات</strong></div>
                <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>الحاضنة</th><th>من</th><th>إلى</th><th>بواسطة</th></tr></thead><tbody>
                <?php if (!$group_history): ?><tr><td colspan="4" class="text-center text-muted py-3">لا يوجد سجل</td></tr><?php else: foreach ($group_history as $history): ?><tr><td><?php echo htmlspecialchars($history['nanny_name']); ?></td><td><?php echo htmlspecialchars($history['start_date']); ?></td><td><?php echo $history['end_date'] ? htmlspecialchars($history['end_date']) : '<span class="badge bg-success">حالياً</span>'; ?></td><td><?php echo htmlspecialchars($history['assigned_by_name'] ?? '—'); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
            </div>
        <?php else: ?>
            <div class="card h-100"><div class="card-body text-center py-5"><i class="bi bi-folder2-open display-5 text-muted"></i><h4 class="mt-3">اختر مجموعة لعرض تفاصيلها</h4><p class="text-muted">يمكنك إنشاء مجموعة جديدة كمسودة، ثم إضافة الأعضاء وتعيين الحاضنة وتفعيلها.</p><div class="row justify-content-center mt-4"><div class="col-md-8"><div class="alert alert-light border text-start small mb-0"><strong>قواعد الحماية:</strong><br>• الحذف النهائي متاح فقط للمجموعة التي لم تُستخدم مطلقاً.<br>• المجموعة المستخدمة لا تُحذف؛ تُغلق أو تُلغى مع بقاء التاريخ.<br>• لا يتم تعديل أعضاء مجموعة بعد إنشاء صرف الشهر الحالي.<br>• نقل اليتيم يحفظ نهاية العضوية القديمة وبداية الجديدة.</div></div></div></div></div>
        <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
