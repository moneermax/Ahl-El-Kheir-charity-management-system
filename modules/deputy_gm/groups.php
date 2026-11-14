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
require_once __DIR__ . '/lib_group_management.php';

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
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['vgm_group_flash'])) {
    $success_message = (string) ($_SESSION['vgm_group_flash']['success'] ?? '');
    $error_message = (string) ($_SESSION['vgm_group_flash']['error'] ?? '');
    unset($_SESSION['vgm_group_flash']);
}
$vgm_group_details_only = $vgm_group_details_only ?? false;
$view_group_id = $vgm_group_details_only ? (int) ($_GET['id'] ?? 0) : 0;

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
    return ak_vgm_group_usage($pdo, $group_id);
}

function groupUsed(array $usage): bool {
    return ak_vgm_group_is_used($usage);
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

$action = '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) throw new RuntimeException('انتهت صلاحية نموذج الصفحة أو رمز الحماية غير صالح. حدّث الصفحة ثم أعد المحاولة.');
        $action = $_POST['action'] ?? '';
        $group_id = (int) ($_POST['group_id'] ?? 0);

        if ($action === 'open_monthly_cycle') {
            $raw_ids = $_POST['group_ids'] ?? [];
            $result = ak_vgm_open_monthly_cycles($pdo, is_array($raw_ids) ? $raw_ids : [], $current_month, $actor_id);
            if (!$result['opened']) {
                $reasons = array_map(static fn(array $row): string => '#' . $row['id'] . ': ' . $row['reason'], $result['skipped']);
                throw new RuntimeException('لم تُفتح أي دورة. ' . implode('؛ ', $reasons));
            }
            $success_message = 'تم فتح دورة شهر ' . $current_month . ' لعدد ' . count($result['opened']) . ' مجموعة، وإرسال ' . $result['notification_attempts'] . ' محاولة إرسال إشعار إلى الحاضنات.';
            if ($result['skipped']) {
                $success_message .= ' تم تجاوز ' . count($result['skipped']) . ' مجموعة: ' . implode('؛ ', array_map(static fn(array $row): string => '#' . $row['id'] . ' — ' . $row['reason'], $result['skipped']));
            }
        }

        elseif ($action === 'create_group') {
            $pdo->beginTransaction();
            $created_group = ak_vgm_create_group($pdo, $_POST['description'] ?? '', $actor_id);
            $view_group_id = (int)$created_group['id'];
            ak_vgm_log_group_created(
                $pdo,
                $view_group_id,
                $actor_id,
                (string)$created_group['group_name'],
                (string)$created_group['group_code'],
                (string)$created_group['description']
            );
            $pdo->commit();
            $success_message = 'تم إنشاء المجموعة كمسودة. أضف الأعضاء ثم عيّن الحاضنة وفعّل المجموعة عند جاهزيتها.';
        }

        elseif ($action === 'update_group') {
            if ($group_id <= 0) throw new RuntimeException('بيانات المجموعة غير مكتملة');
            $description = ak_vgm_validate_group_description($_POST['description'] ?? '');
            $stmt = $pdo->prepare('SELECT status FROM orphan_groups WHERE id = :id');
            $stmt->execute(['id' => $group_id]);
            $status = $stmt->fetchColumn();
            if (!$status) throw new RuntimeException('المجموعة غير موجودة');
            if (in_array($status, ['closed', 'cancelled', 'archived'], true)) {
                throw new RuntimeException('لا يمكن تعديل مجموعة مغلقة أو ملغاة أو مؤرشفة');
            }
            $pdo->prepare('UPDATE orphan_groups SET description = :description, updated_at = NOW() WHERE id = :id')
                ->execute(['description' => $description, 'id' => $group_id]);
            $success_message = 'تم تحديث وصف المجموعة';
        }

        elseif (in_array($action, ['activate_group', 'suspend_group', 'close_group', 'cancel_group', 'archive_group'], true)) {
            if ($group_id <= 0) throw new RuntimeException('المجموعة غير محددة');
            $pdo->beginTransaction();
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
                if ($new_status === 'archived') {
                    ak_vgm_assert_group_archivable($pdo, $group_id, (string)$group['verification_status']);
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
                if ($new_status === 'archived') {
                    $pdo->prepare('UPDATE nanny_group_assignments SET end_date = CURDATE() WHERE group_id = :id AND end_date IS NULL')->execute(['id' => $group_id]);
                }
                $success_message = [
                    'suspended' => 'تم إيقاف المجموعة مؤقتاً',
                    'closed' => 'تم إغلاق المجموعة مع الحفاظ على كامل تاريخها',
                    'cancelled' => 'تم إلغاء المجموعة مع الحفاظ على سجلها',
                    'archived' => 'تم أرشفة المجموعة'
                ][$new_status];
            }
            $pdo->commit();
        }

        elseif ($action === 'assign_nanny') {
            $nanny_id = filter_var($_POST['nanny_id'] ?? null, FILTER_VALIDATE_INT);
            if ($group_id <= 0 || !$nanny_id || $nanny_id < 1) throw new RuntimeException('يرجى اختيار المجموعة والحاضنة');
            $pdo->beginTransaction();
            ak_vgm_lock_operational_group($pdo, $group_id);
            ak_vgm_assert_active_nanny($pdo, (int)$nanny_id);
            $pdo->prepare('UPDATE nanny_group_assignments SET end_date = CURDATE() WHERE group_id = :id AND end_date IS NULL')->execute(['id' => $group_id]);
            $pdo->prepare('INSERT INTO nanny_group_assignments (group_id, nanny_id, start_date, assigned_by) VALUES (:group_id, :nanny_id, CURDATE(), :actor)')->execute(['group_id' => $group_id, 'nanny_id' => (int)$nanny_id, 'actor' => $actor_id]);
            $pdo->commit();
            $success_message = 'تم تحديث الحاضنة مع الحفاظ على سجل التعيينات السابق';
        }

        elseif (in_array($action, ['add_orphan', 'add_orphan_bulk'], true)) {
            if ($group_id <= 0) throw new RuntimeException('المجموعة غير محددة');
            $pdo->beginTransaction();
            ak_vgm_lock_operational_group($pdo, $group_id);
            if (groupHasCurrentDisbursement($pdo, $group_id, $current_month)) {
                throw new RuntimeException('لا يمكن تعديل أعضاء هذه المجموعة بعد إنشاء صرف الشهر الحالي. يتم نقل أي تغيير مالي إلى دورة الشهر التالية.');
            }
            $child_ids = $action === 'add_orphan' ? [(int) ($_POST['child_id'] ?? 0)] : array_map('intval', (array) ($_POST['child_ids'] ?? []));
            $child_ids = array_values(array_unique(array_filter($child_ids)));
            if (!$child_ids) throw new RuntimeException('لم يتم اختيار أي يتيم');
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
            $pdo->beginTransaction();
            ak_vgm_lock_operational_group($pdo, $group_id);
            if (groupHasCurrentDisbursement($pdo, $group_id, $current_month)) {
                throw new RuntimeException('لا يمكن إزالة عضو من المجموعة بعد إنشاء صرف الشهر الحالي. يحافظ النظام على سجل الصرف ولا يعدله بأثر رجعي.');
            }
            $stmt = $pdo->prepare('UPDATE group_children SET left_date = CURDATE() WHERE group_id = :group_id AND child_id = :child_id AND left_date IS NULL');
            $stmt->execute(['group_id' => $group_id, 'child_id' => $child_id]);
            if ($stmt->rowCount() === 0) throw new RuntimeException('العضو غير موجود كعضو حالي في هذه المجموعة');
            syncGroupState($pdo, $group_id, $actor_id);
            $pdo->commit();
            $success_message = 'تم إزالة اليتيم من المجموعة مع الحفاظ على سجل العضوية';
        }

        elseif ($action === 'transfer_orphan') {
            $child_id = (int) ($_POST['child_id'] ?? 0);
            $target_group_id = (int) ($_POST['target_group_id'] ?? 0);
            if ($group_id <= 0 || $child_id <= 0 || $target_group_id <= 0 || $target_group_id === $group_id) throw new RuntimeException('بيانات النقل غير مكتملة');
            $pdo->beginTransaction();
            $statuses = $pdo->prepare('SELECT id, status FROM orphan_groups WHERE id IN (:source, :target) ORDER BY id FOR UPDATE');
            $statuses->execute(['source' => $group_id, 'target' => $target_group_id]);
            $status_map = [];
            foreach ($statuses->fetchAll(PDO::FETCH_ASSOC) as $row) $status_map[(int) $row['id']] = $row['status'];
            if (!isset($status_map[$group_id], $status_map[$target_group_id]) || !groupCanChangeMembers($status_map[$group_id]) || !groupCanChangeMembers($status_map[$target_group_id])) {
                throw new RuntimeException('النقل متاح فقط بين مجموعات تشغيلية');
            }
            if (groupHasCurrentDisbursement($pdo, $group_id, $current_month) || groupHasCurrentDisbursement($pdo, $target_group_id, $current_month)) {
                throw new RuntimeException('لا يمكن نقل عضو بين مجموعات مرتبطة بصرف الشهر الحالي. يتم تنفيذ التغيير من الدورة التالية.');
            }
            $check = $pdo->prepare('SELECT 1 FROM group_children WHERE group_id = :group_id AND child_id = :child_id AND left_date IS NULL LIMIT 1 FOR UPDATE');
            $check->execute(['group_id' => $group_id, 'child_id' => $child_id]);
            if (!$check->fetchColumn()) throw new RuntimeException('اليتيم ليس عضواً حالياً في المجموعة المصدر');
            $check->execute(['group_id' => $target_group_id, 'child_id' => $child_id]);
            if ($check->fetchColumn()) throw new RuntimeException('اليتيم لديه سجل عضوية سابق في المجموعة الهدف');
            $pdo->prepare('UPDATE group_children SET left_date = CURDATE() WHERE group_id = :group_id AND child_id = :child_id AND left_date IS NULL')->execute(['group_id' => $group_id, 'child_id' => $child_id]);
            $pdo->prepare('INSERT INTO group_children (group_id, child_id, joined_date, added_by) VALUES (:group_id, :child_id, CURDATE(), :actor)')->execute(['group_id' => $target_group_id, 'child_id' => $child_id, 'actor' => $actor_id]);
            syncGroupState($pdo, $group_id, $actor_id);
            syncGroupState($pdo, $target_group_id, $actor_id);
            $pdo->commit();
            $success_message = 'تم نقل اليتيم مع حفظ نهاية العضوية القديمة وبداية العضوية الجديدة';
            $view_group_id = $target_group_id;
        }

        elseif ($action === 'delete_group') {
            if ($group_id <= 0) throw new RuntimeException('المجموعة غير محددة');
            $result = ak_vgm_delete_or_archive_group($pdo, $group_id, $actor_id, trim((string)($_POST['reason'] ?? '')));
            if ($result === 'deleted') {
                $success_message = 'تم حذف المجموعة نهائياً لأنها لم تُستخدم ولم يكن لها أي سجل مرتبط';
            } else {
                $success_message = 'تمت أرشفة المجموعة مع الحفاظ على جميع الأعضاء وسجلات العضوية والحاضنات والصرف.';
            }
            $view_group_id = 0;
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $error_message = $e instanceof RuntimeException || $e instanceof DomainException
        ? $e->getMessage()
        : (($e instanceof PDOException && ak_vgm_is_group_identity_collision($e))
            ? 'تعذر تخصيص رمز فريد للمجموعة. أعد المحاولة.'
            : 'تعذر تنفيذ العملية. لم يتم فقد أي بيانات.');
    if ($action === 'create_group') $view_group_id = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['vgm_group_flash'] = ['success' => $success_message, 'error' => $error_message];
    $detailActions = ['create_group', 'update_group', 'activate_group', 'suspend_group', 'close_group', 'cancel_group', 'archive_group', 'assign_nanny', 'add_orphan', 'add_orphan_bulk', 'remove_orphan', 'transfer_orphan'];
    if ($action === 'create_group' && $view_group_id > 0) {
        header('Location: ' . APP_URL . 'modules/deputy_gm/group_details.php?id=' . $view_group_id);
    } elseif ($action === 'transfer_orphan' && $view_group_id > 0) {
        header('Location: ' . APP_URL . 'modules/deputy_gm/group_details.php?id=' . $view_group_id);
    } elseif (in_array($action, $detailActions, true) && $action !== 'create_group' && $group_id > 0) {
        header('Location: ' . APP_URL . 'modules/deputy_gm/group_details.php?id=' . $group_id);
    } else {
        header('Location: ' . APP_URL . 'modules/deputy_gm/groups.php');
    }
    exit();
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
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 bg-primary text-white rounded-3 p-3 shadow-sm">
        <div>
            <h2 class="mb-1"><i class="bi bi-people-fill"></i> <?php echo $vgm_group_details_only ? 'تفاصيل المجموعة' : 'إدارة مجموعات الأيتام'; ?></h2>
            <div class="small text-white-50"><?php echo $vgm_group_details_only ? 'بيانات المجموعة والأعضاء والحاضنة والإجراءات' : 'إنشاء المجموعات ومتابعة حالتها'; ?></div>
        </div>
        <?php if ($vgm_group_details_only): ?><a class="btn btn-outline-light btn-sm" href="groups.php"><i class="bi bi-arrow-right"></i> العودة إلى المجموعات</a><?php endif; ?>
        <div class="small text-white">دورة الصرف الحالية: <strong class="badge bg-light text-primary fs-6"><?php echo htmlspecialchars($current_month); ?></strong></div>
    </div>

    <?php if (!$vgm_group_details_only): ?>
            <div class="alert alert-light border mx-auto mb-3" style="max-width: 1400px; width: 100%;">
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <i class="bi bi-info-circle text-primary mt-1"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold mb-1">إرشادات إدارة المجموعات</div>
                        <div class="small text-muted mb-2">يمكنك إنشاء مجموعة جديدة كمسودة، ثم فتح صفحة تفاصيلها لإضافة الأيتام وتعيين الحاضنة وتفعيلها.</div>
                        <div class="row g-2 small">
                            <div class="col-12 col-md-6">• الحذف النهائي متاح فقط للمجموعة التي لم تُستخدم مطلقاً.</div>
                            <div class="col-12 col-md-6">• المجموعة المستخدمة لا تُحذف؛ تُغلق أو تُلغى مع بقاء التاريخ.</div>
                            <div class="col-12 col-md-6">• لا يتم تعديل أعضاء مجموعة بعد إنشاء صرف الشهر الحالي.</div>
                            <div class="col-12 col-md-6">• نقل اليتيم يحفظ نهاية العضوية القديمة وبداية الجديدة.</div>
                        </div>
                    </div>
                </div>
            </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div data-ak-flash="success" data-ak-message="<?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>" data-ak-duration="3200" hidden></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div data-ak-flash="error" data-ak-message="<?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>" data-ak-duration="3200" hidden></div>
    <?php endif; ?>

    <div class="row g-3">
        <?php if (!$vgm_group_details_only): ?>
            <div class="col-12">
                <div class="card mb-3 mx-auto" style="max-width: 1400px; width: 100%;">
                    <div class="card-header py-2"><strong><i class="bi bi-plus-circle"></i> إنشاء مجموعة</strong></div>
                    <div class="card-body py-2">
                        <form method="POST" action="group_details.php" data-confirm="سيُنشئ النظام اسماً ورمزاً فريدين للمجموعة. هل تريد المتابعة؟" class="row g-2 align-items-end"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="create_group">
                            <div class="col-12 col-md-9">
                                <label class="form-label small mb-1" for="vgmGroupDescription">وصف المجموعة (اختياري)</label>
                                <input id="vgmGroupDescription" type="text" class="form-control form-control-sm" name="description" maxlength="1000" placeholder="اكتب وصفاً مختصراً للمجموعة">
                            </div>
                            <div class="col-12 col-md-3">
                                <button class="btn btn-primary btn-sm w-100"><i class="bi bi-save"></i> إنشاء كمسودة</button>
                            </div>
                        </form>
                        <div class="form-text mt-2">سيُنشئ النظام اسم المجموعة ورمزها تلقائياً. ستُنشأ كمسودة، ثم تنتقل إلى صفحة التفاصيل لإضافة الأيتام وتعيين الحاضنة وتفعيلها.</div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card mb-3 mx-auto" style="max-width: 1400px; width: 100%;">
                    <div class="card-header py-2"><strong><i class="bi bi-list-ul"></i> المجموعات</strong></div>
                    <div class="card-body p-0">
                        <form method="POST" data-confirm="سيتم فتح دورة التوثيق والصرف للشهر الحالي للمجموعات المحددة فقط. هل تريد المتابعة؟"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="open_monthly_cycle">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom p-3">
                                <div>
                                    <div class="fw-semibold">فتح دورة الشهر الحالي</div>
                                    <div class="small text-muted">حدد المجموعات النشطة التي لديها أعضاء وحاضنة معيّنة.</div>
                                </div>
                                <button class="btn btn-sm btn-outline-primary">فتح الدورة للمحدد</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle text-center mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">فتح الدورة</th>
                                            <th scope="col">اسم المجموعة</th>
                                            <th scope="col">الحالة</th>
                                            <th scope="col">الأعضاء</th>
                                            <th scope="col">الحاضنة</th>
                                            <th scope="col">دفعات الصرف</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$groups): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-4">لا توجد مجموعات</td></tr>
                                        <?php else: foreach ($groups as $group): ?>
                                            <?php $can_open_cycle = $group['status'] === 'active' && (int)$group['orphan_count'] > 0 && !empty($group['nanny_name']); ?>
                                            <tr>
                                                <td>
                                                    <?php if ($can_open_cycle): ?>
                                                        <input class="form-check-input mt-0" type="checkbox" name="group_ids[]" value="<?php echo (int)$group['id']; ?>" aria-label="<?php echo htmlspecialchars('فتح الدورة لمجموعة ' . $group['group_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                                </td>
                                                <td class="text-start">
                                                    <a class="fw-semibold text-decoration-none" href="group_details.php?id=<?php echo (int)$group['id']; ?>"><?php echo htmlspecialchars($group['group_name']); ?></a>
                                                    <?php if (!empty($group['group_code'])): ?><div class="small text-muted font-monospace"><?php echo htmlspecialchars($group['group_code']); ?></div><?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-<?php echo groupStatusClass($group['status']); ?>"><?php echo groupStatusLabel($group['status']); ?></span></td>
                                                <td><?php echo (int)$group['orphan_count']; ?></td>
                                                <td><?php echo $group['nanny_name'] ? htmlspecialchars($group['nanny_name']) : '<span class="text-muted">بدون حاضنة</span>'; ?></td>
                                                <td><?php echo (int)$group['disbursement_count']; ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-12">
        <?php if ($selected_group): ?>
            <?php $status = (string)$selected_group['status']; $current_members = count($group_orphans); $has_current_disbursement = groupHasCurrentDisbursement($pdo, (int)$selected_group['id'], $current_month); ?>
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="mb-1"><i class="bi bi-folder2-open"></i> <?php echo htmlspecialchars($selected_group['group_name']); ?></h5>
                        <span class="badge bg-<?php echo groupStatusClass($status); ?>"><?php echo groupStatusLabel($status); ?></span>
                        <?php if (!empty($selected_group['group_code'])): ?><span class="badge bg-light text-dark font-monospace"><?php echo htmlspecialchars($selected_group['group_code']); ?></span><?php endif; ?>
                    </div>
                    <div class="d-flex gap-1 flex-wrap">
                        <?php if (in_array($status, ['draft','empty','suspended'], true) && $current_members > 0): ?><form method="POST" action="groups.php" class="d-inline" data-confirm="هل تريد تفعيل هذه المجموعة؟"><?php echo csrf_field(); ?><input type="hidden" name="action" value="activate_group"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><button class="btn btn-success btn-sm">تفعيل</button></form><?php endif; ?>
                        <?php if (in_array($status, ['active','empty'], true)): ?><form method="POST" action="groups.php" class="d-inline" data-confirm="هل تريد إيقاف المجموعة مؤقتاً؟"><?php echo csrf_field(); ?><input type="hidden" name="action" value="suspend_group"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><button class="btn btn-outline-warning btn-sm">إيقاف مؤقت</button></form><?php endif; ?>
                        <?php if (in_array($status, ['active','empty','suspended'], true)): ?><form method="POST" action="groups.php" class="d-inline" data-confirm="سيتم إغلاق المجموعة مع الاحتفاظ بكل تاريخها. هل تريد المتابعة؟"><?php echo csrf_field(); ?><input type="hidden" name="action" value="close_group"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><button class="btn btn-outline-dark btn-sm">إغلاق</button></form><?php endif; ?>
                        <?php if ($status !== 'archived'): ?>
                            <form method="POST" action="groups.php" class="d-inline" data-confirm="<?php echo groupUsed($group_usage) ? 'سيتم أرشفة المجموعة المستخدمة مع الإبقاء على جميع الأعضاء والسجلات دون حذفها. هل تريد المتابعة؟' : 'سيتم حذف المجموعة نهائياً لأنها لم تُستخدم. هل تريد المتابعة؟'; ?>"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_group"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>">
                                <button class="btn btn-outline-danger btn-sm"><?php echo groupUsed($group_usage) ? 'حذف المجموعة (أرشفة آمنة)' : 'حذف نهائي'; ?></button>
                            </form>
                        <?php endif; ?>
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
                                    </div>
            </div>

            <div class="row justify-content-center mb-3">
                <div class="col-12 col-lg-10 col-xl-8">
                    <div class="card border">
                        <div class="card-header py-2">
                            <strong><i class="bi bi-sliders"></i> إعدادات المجموعة</strong>
                            <div class="small text-muted mt-1">تعديل الوصف وإدارة تعيين الحاضنة</div>
                        </div>
                        <div class="card-body py-3">
                            <div class="row g-3 align-items-start">
                                <div class="col-12 col-md-6">
                                    <form method="POST" action="groups.php"><?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="update_group"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>">
                                        <label class="form-label small mb-1">وصف المجموعة</label>
                                        <div class="input-group input-group-sm flex-nowrap">
                                            <input class="form-control" name="description" maxlength="1000" value="<?php echo htmlspecialchars($selected_group['description'] ?? ''); ?>" <?php echo in_array($status,['closed','cancelled','archived'],true) ? 'disabled' : ''; ?>>
                                            <button class="btn btn-outline-primary text-nowrap" <?php echo in_array($status,['closed','cancelled','archived'],true) ? 'disabled' : ''; ?>>حفظ</button>
                                        </div>
                                    </form>
                                </div>
                                <?php if (groupCanChangeMembers($status)): ?>
                                    <?php $current_nanny = $group_history[0] ?? null; ?>
                                    <div class="col-12 col-md-6">
                                        <form method="POST" action="groups.php"><?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="assign_nanny"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>">
                                            <label class="form-label small mb-1">تعيين الحاضنة</label>
                                            <div class="input-group input-group-sm flex-nowrap">
                                                <select class="form-select" name="nanny_id" required><option value="">-- اختر الحاضنة --</option><?php foreach ($nannies as $nanny): ?><option value="<?php echo (int)$nanny['id']; ?>"><?php echo htmlspecialchars($nanny['full_name']); ?></option><?php endforeach; ?></select>
                                                <button class="btn btn-primary text-nowrap"><i class="bi bi-check-circle"></i> تعيين</button>
                                            </div>
                                            <div class="form-text" style="min-height: 1.25rem;">
                                                <?php if ($current_nanny && empty($current_nanny['end_date'])): ?><i class="bi bi-person-check"></i> الحالية: <?php echo htmlspecialchars($current_nanny['nanny_name']); ?> — منذ <?php echo htmlspecialchars($current_nanny['start_date']); ?><?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between"><strong>الأعضاء الحاليون</strong><span class="badge bg-secondary"><?php echo $current_members; ?></span></div>
                <div class="card-body p-0">
                    <?php if (!$group_orphans): ?><div class="text-center text-muted py-4">لا يوجد أعضاء حاليون. استخدم قسم «إضافة أيتام جدد» أسفل الصفحة.</div><?php else: ?>
                    <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>اليتيم</th><th>الأسرة</th><th>العمر</th><th>الانضمام</th><th class="text-end">الإجراءات</th></tr></thead><tbody>
                    <?php foreach ($group_orphans as $orphan): ?><tr><td><?php echo htmlspecialchars($orphan['child_name']); ?></td><td><?php echo htmlspecialchars($orphan['family_name']); ?></td><td><?php echo (int)$orphan['age']; ?></td><td><?php echo htmlspecialchars($orphan['joined_date']); ?></td><td class="text-end">
                        <?php if (groupCanChangeMembers($status) && !$has_current_disbursement): ?><form method="POST" action="groups.php" class="d-inline" data-confirm="هل تريد نقل هذا اليتيم إلى مجموعة أخرى؟"><?php echo csrf_field(); ?><input type="hidden" name="action" value="transfer_orphan"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><input type="hidden" name="child_id" value="<?php echo (int)$orphan['child_id']; ?>"><select name="target_group_id" class="form-select form-select-sm d-inline-block" style="width:180px" required><option value="">نقل إلى...</option><?php foreach ($target_groups as $target): ?><option value="<?php echo (int)$target['id']; ?>"><?php echo htmlspecialchars($target['group_name']); ?> (<?php echo (int)$target['orphan_count']; ?>)</option><?php endforeach; ?></select><button class="btn btn-outline-primary btn-sm">نقل</button></form>
                        <form method="POST" action="groups.php" class="d-inline" data-confirm="سيتم إنهاء العضوية الحالية مع الاحتفاظ بسجلها. هل تريد المتابعة؟"><?php echo csrf_field(); ?><input type="hidden" name="action" value="remove_orphan"><input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>"><input type="hidden" name="child_id" value="<?php echo (int)$orphan['child_id']; ?>"><button class="btn btn-outline-danger btn-sm">إزالة</button></form><?php endif; ?>
                    </td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2"><strong>سجل الحاضنات</strong></div>
                <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>الحاضنة</th><th>من</th><th>إلى</th><th>بواسطة</th></tr></thead><tbody>
                <?php if (!$group_history): ?><tr><td colspan="4" class="text-center text-muted py-3">لا يوجد سجل</td></tr><?php else: foreach ($group_history as $history): ?><tr><td><?php echo htmlspecialchars($history['nanny_name']); ?></td><td><?php echo htmlspecialchars($history['start_date']); ?></td><td><?php echo $history['end_date'] ? htmlspecialchars($history['end_date']) : '<span class="badge bg-success">حالياً</span>'; ?></td><td><?php echo htmlspecialchars($history['assigned_by_name'] ?? '—'); ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div>
            </div>
        <?php elseif ($vgm_group_details_only): ?>
            <div class="alert alert-danger">المجموعة غير موجودة أو لا يمكن الوصول إليها. <a href="groups.php">العودة إلى قائمة المجموعات</a></div>
        <?php else: ?>

        <?php endif; ?>
        </div>
    </div>

    <?php if ($selected_group && groupCanChangeMembers($status)): ?>
    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> إضافة أيتام جدد للمجموعة: <?php echo htmlspecialchars($selected_group['group_name']); ?></h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border mb-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span><i class="bi bi-info-circle text-primary"></i> يوجد <strong><?php echo count($orphans); ?></strong> يتيم مؤهل للإضافة (كفالة نشطة + لم يُصرف له هذا الشهر بعد)</span>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllBtn">تحديد الكل</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn">إلغاء التحديد</button>
                            </div>
                        </div>
                        <div class="text-muted small mt-2 border-top pt-2">
                            <i class="bi bi-exclamation-circle"></i> ملاحظة: قد لا يظهر كل أطفال أسرة واحدة معاً — إذا كان بعض إخوتهم مضافاً بالفعل لمجموعة أخرى هذا الشهر عبر كفيل مختلف، سيبقون مستبعدين من هذه القائمة فقط دون التأثير على بقية الأسرة.
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="orphanSearch" placeholder="ابحث بالاسم أو اسم الأسرة أو الكفيل...">
                            <button class="btn btn-outline-secondary" type="button" id="clearSearch">مسح</button>
                        </div>
                        <div class="form-text">اختر الأيتام ثم اضغط على "إضافة المحددين"</div>
                    </div>

                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover table-bordered" id="orphansTable">
                            <thead class="table-primary sticky-top" style="z-index: 10; background: white;">
                                <tr>
                                    <th width="40"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                    <th>#</th><th>الاسم</th><th>العمر</th><th>الأسرة</th><th>الكفيل</th><th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $counter = 1;
                                $lastFamilyId = null;
                                foreach ($orphans as $orphan):
                                    $already_in_group = false;
                                    foreach ($group_orphans as $go) {
                                        if ($go['child_id'] == $orphan['id']) { $already_in_group = true; break; }
                                    }
                                    if ($already_in_group) continue;
                                    if ($orphan['family_id'] !== $lastFamilyId):
                                        $lastFamilyId = $orphan['family_id'];
                                ?>
                                    <tr class="family-header-row" data-family="<?php echo (int)$orphan['family_id']; ?>">
                                        <td colspan="7" class="bg-light">
                                            <input type="checkbox" class="form-check-input family-checkbox" data-family="<?php echo (int)$orphan['family_id']; ?>">
                                            <strong class="ms-1"><i class="bi bi-house-heart"></i> أسرة: <?php echo htmlspecialchars($orphan['family_name'] ?: '-'); ?></strong>
                                            <span class="text-muted small">(<?php echo htmlspecialchars($orphan['family_code'] ?: '—'); ?>)</span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                    <tr class="orphan-row" data-family="<?php echo (int)$orphan['family_id']; ?>" data-name="<?php echo htmlspecialchars(mb_strtolower($orphan['child_name'] . ' ' . ($orphan['family_name'] ?? '') . ' ' . ($orphan['sponsor_name'] ?? ''), 'UTF-8'), ENT_QUOTES); ?>">
                                        <td><input type="checkbox" class="form-check-input orphan-checkbox" data-family="<?php echo (int)$orphan['family_id']; ?>" name="selected_orphans[]" value="<?php echo (int)$orphan['id']; ?>"></td>
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($orphan['child_name']); ?></strong></td>
                                        <td><?php echo (int)$orphan['age']; ?> سنة</td>
                                        <td><?php echo htmlspecialchars($orphan['family_name'] ?: '-'); ?></td>
                                        <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($orphan['sponsor_name'] ?: '—'); ?></span></td>
                                        <td><span class="badge bg-success">متاح</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 p-3 bg-white border rounded">
                        <div><strong>المحدد: <span id="selectedCount" class="text-primary">0</span> يتيم</strong></div>
                        <button type="button" class="btn btn-warning" id="addSelectedBtn">
                            <i class="bi bi-plus-circle"></i> إضافة المحددين (<span id="btnCount">0</span>)
                        </button>
                    </div>

                    <form method="POST" action="groups.php" id="bulkAddForm" style="display:none;"><?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="add_orphan_bulk">
                        <input type="hidden" name="group_id" value="<?php echo (int)$selected_group['id']; ?>">
                        <div id="orphansContainer"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const search = document.getElementById('orphanSearch');
    const clear = document.getElementById('clearSearch');
    const selectAll = document.getElementById('selectAll');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deselectAllBtn = document.getElementById('deselectAllBtn');
    const addSelectedBtn = document.getElementById('addSelectedBtn');

    if (!search || !addSelectedBtn) return;

    function syncFamilyHeaders() {
        document.querySelectorAll('.family-header-row').forEach(function (header) {
            const familyId = header.dataset.family;
            const rows = document.querySelectorAll('.orphan-row[data-family="' + familyId + '"]');
            const visible = Array.from(rows).some(function (row) { return row.style.display !== 'none'; });
            header.style.display = visible ? '' : 'none';
        });
    }

    function updateSelectedCount() {
        const checked = document.querySelectorAll('.orphan-checkbox:checked').length;
        const count = document.getElementById('selectedCount');
        const buttonCount = document.getElementById('btnCount');
        if (count) count.textContent = checked;
        if (buttonCount) buttonCount.textContent = checked;

        document.querySelectorAll('.family-checkbox').forEach(function (familyBox) {
            const familyId = familyBox.dataset.family;
            const children = Array.from(document.querySelectorAll('.orphan-checkbox[data-family="' + familyId + '"]'))
                .filter(function (cb) { return cb.closest('tr').style.display !== 'none'; });
            familyBox.checked = children.length > 0 && children.every(function (cb) { return cb.checked; });
        });
    }

    function setVisibleCheckboxes(checked) {
        document.querySelectorAll('.orphan-checkbox').forEach(function (cb) {
            if (cb.closest('tr').style.display !== 'none') cb.checked = checked;
        });
        updateSelectedCount();
    }

    search.addEventListener('input', function () {
        const term = this.value.trim().toLocaleLowerCase('ar');
        const words = term.split(/\s+/).filter(Boolean);
        document.querySelectorAll('.orphan-row').forEach(function (row) {
            const name = (row.dataset.name || '').toLocaleLowerCase('ar');
            const show = !term || words.every(function (word) { return name.includes(word); });
            row.style.display = show ? '' : 'none';
        });
        syncFamilyHeaders();
        updateSelectedCount();
    });

    clear.addEventListener('click', function () {
        search.value = '';
        document.querySelectorAll('.orphan-row').forEach(function (row) { row.style.display = ''; });
        syncFamilyHeaders();
        updateSelectedCount();
        search.focus();
    });

    selectAll.addEventListener('change', function () { setVisibleCheckboxes(this.checked); });
    selectAllBtn.addEventListener('click', function () { setVisibleCheckboxes(true); selectAll.checked = true; });
    deselectAllBtn.addEventListener('click', function () { setVisibleCheckboxes(false); selectAll.checked = false; });

    document.querySelectorAll('.family-checkbox').forEach(function (familyBox) {
        familyBox.addEventListener('change', function () {
            const familyId = this.dataset.family;
            document.querySelectorAll('.orphan-checkbox[data-family="' + familyId + '"]').forEach(function (cb) {
                if (cb.closest('tr').style.display !== 'none') cb.checked = familyBox.checked;
            });
            updateSelectedCount();
        });
    });

    document.querySelectorAll('.orphan-checkbox').forEach(function (cb) {
        cb.addEventListener('change', updateSelectedCount);
    });

    addSelectedBtn.addEventListener('click', function () {
        const checked = Array.from(document.querySelectorAll('.orphan-checkbox:checked'));
        if (!checked.length) {
            if (window.AKNotify && typeof AKNotify.toast === 'function') {
                AKNotify.toast('warning', 'يرجى اختيار يتيم واحد على الأقل');
            }
            return;
        }

        const submit = function () {
            const container = document.getElementById('orphansContainer');
            container.innerHTML = '';
            checked.forEach(function (cb) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'child_ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });
            document.getElementById('bulkAddForm').requestSubmit();
        };

        const confirmationMessage = 'هل تريد إضافة ' + checked.length + ' يتيم للمجموعة؟';
        if (window.AKNotify && typeof window.AKNotify.confirm === 'function') {
            window.AKNotify.confirm(confirmationMessage, submit, {
                title: 'إضافة أيتام للمجموعة',
                icon: 'question',
                confirmButtonText: 'نعم، إضافة',
                cancelButtonText: 'إلغاء'
            });
        } else if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                title: 'إضافة أيتام للمجموعة',
                text: confirmationMessage,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، إضافة',
                cancelButtonText: 'إلغاء',
                reverseButtons: true,
                rtl: true
            }).then(function (result) { if (result.isConfirmed) submit(); });
        } else if (window.confirm(confirmationMessage)) {
            submit();
        }
    });

    updateSelectedCount();
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
