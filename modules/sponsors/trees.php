<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/sponsor_assignments.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) {
    http_response_code(403);
    exit('ليس لديك صلاحية لإدارة الأشجار.');
}

$pageTitle = 'إدارة أشجار الكفلاء';
$active = 'sponsor_trees';
$uid = (int) Session::getUserId();
$treeId = (int) ($_GET['id'] ?? 0);
$errors = [];
$success = '';
$submittedMemberIds = [];

function ak_tree_gender_label(string $gender): string
{
    $value = trim($gender);
    $labels = ['male' => 'ذكر', 'female' => 'أنثى', 'm' => 'ذكر', 'f' => 'أنثى', 'ذكر' => 'ذكر', 'أنثى' => 'أنثى'];
    return $labels[strtolower($value)] ?? $value;
}

function ak_tree_audit(int $uid, string $action, string $type, int $id, ?array $old, ?array $new): void
{
    dbExecute(
        "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $uid, $action, $type, $id,
            $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
            $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'انتهت صلاحية الجلسة. حدّث الصفحة وحاول مرة أخرى.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'create_tree') {
                $name = trim((string) ($_POST['full_name'] ?? ''));
                $entityType = (string) ($_POST['sponsor_type'] ?? '');
                $treeType = (string) ($_POST['tree_type'] ?? '');
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $address = trim((string) ($_POST['address'] ?? ''));
                $contactName = trim((string) ($_POST['contact_person_name'] ?? ''));
                $contactPosition = trim((string) ($_POST['contact_person_position'] ?? ''));
                $contactMethod = (string) ($_POST['preferred_contact_method'] ?? 'any');
                $contactTime = trim((string) ($_POST['preferred_contact_time'] ?? ''));
                $payment = (string) ($_POST['payment'] ?? 'cash');
                $memberIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['member_ids'] ?? [])))));
                $submittedMemberIds = $memberIds;

                if ($name === '') $errors[] = 'اسم الشجرة مطلوب.';
                if (!in_array($entityType, ['company', 'organization'], true)) $errors[] = 'اختر نوع الجهة.';
                if (!in_array($treeType, ['optional_members', 'required_members'], true)) $errors[] = 'اختر نوع الشجرة.';
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صالح.';
                if (!in_array($contactMethod, ['call', 'whatsapp', 'email', 'any'], true)) $errors[] = 'طريقة التواصل غير صحيحة.';
                if (!in_array($payment, ['cash', 'bank_transfer', 'credit_card', 'mobile', 'other'], true)) $errors[] = 'طريقة الدفع غير صحيحة.';
                if ($treeType === 'required_members' && !$memberIds) $errors[] = 'هذا النوع من الأشجار يتطلب إضافة عضو واحد على الأقل.';

                $members = [];
                if ($memberIds) {
                    $ph = implode(',', array_fill(0, count($memberIds), '?'));
                    $members = dbFetchAll(
                        "SELECT id, full_name, first_letter_id, gender, supervisor_id, is_manual_override, created_by
                         FROM sponsors WHERE id IN ($ph) AND sponsor_type = 'individual' AND status = 'active'",
                        $memberIds
                    );
                    if (count($members) !== count($memberIds)) $errors[] = 'تأكد أن جميع الأعضاء المختارين أفراد كفلاء نشطون.';
                    if ($role === 'supervisor') {
                        foreach ($members as $member) {
                            if (!supervisorCanAccessSponsor($uid, $member)) {
                                $errors[] = 'أحد الأفراد المختارين ليس ضمن نطاق الكفلاء المسموح لك بإدارتهم.';
                                break;
                            }
                        }
                    }
                }

                if (!$errors) {
                    db()->beginTransaction();
                    $sponsorId = 0;
                    try {
                        dbExecute(
                            "INSERT INTO sponsors
                                (full_name, phone, email, address, sponsor_type, gender, preferred_payment_method, status, created_by,
                                 contact_person_name, contact_person_position, preferred_contact_method, preferred_contact_time)
                             VALUES (?, ?, ?, ?, ?, 'organization', ?, 'active', ?, ?, ?, ?, ?)",
                            [
                                $name, $phone !== '' ? $phone : null, $email !== '' ? $email : null,
                                $address !== '' ? $address : null, $entityType, $payment, $uid,
                                $contactName !== '' ? $contactName : null,
                                $contactPosition !== '' ? $contactPosition : null,
                                $contactMethod, $contactTime !== '' ? $contactTime : null
                            ]
                        );
                        $sponsorId = (int) dbLastInsertId();
                        $sponsorCode = 'SP-' . str_pad((string) $sponsorId, 6, '0', STR_PAD_LEFT);
                        dbExecute("UPDATE sponsors SET sponsor_code = ? WHERE id = ?", [$sponsorCode, $sponsorId]);
                        dbExecute(
                            "INSERT INTO sponsor_trees (sponsor_id, tree_type, created_by) VALUES (?, ?, ?)",
                            [$sponsorId, $treeType, $uid]
                        );
                        $newTreeId = (int) dbLastInsertId();
                        foreach ($memberIds as $memberId) {
                            dbExecute(
                                "INSERT INTO sponsor_tree_members (tree_id, member_sponsor_id, status, created_by)
                                 VALUES (?, ?, 'active', ?)",
                                [$newTreeId, $memberId, $uid]
                            );
                        }
                        ak_tree_audit($uid, 'CREATE', 'sponsor_trees', $newTreeId, null, [
                            'sponsor_id' => $sponsorId, 'sponsor_code' => $sponsorCode,
                            'tree_type' => $treeType, 'member_ids' => $memberIds
                        ]);
                        db()->commit();
                        flash('success', 'تم إنشاء الشجرة بنجاح، وتم تسجيلها ككفيل عادي.');
                        header('Location: ' . APP_URL . 'modules/sponsors/trees.php?id=' . $newTreeId);
                        exit();
                    } catch (Throwable $e) {
                        if (db()->inTransaction()) db()->rollBack();
                        throw $e;
                    }
                }
            } elseif ($action === 'add_member') {
                $targetTreeId = (int) ($_POST['tree_id'] ?? 0);
                $memberId = (int) ($_POST['member_sponsor_id'] ?? 0);
                db()->beginTransaction();
                try {
                    $tree = dbFetchOne(
                        "SELECT st.id AS tree_id, st.tree_type, s.id AS sponsor_id, s.sponsor_type, s.created_by, s.supervisor_id, s.first_letter_id, s.gender, s.is_manual_override
                         FROM sponsor_trees st JOIN sponsors s ON s.id = st.sponsor_id
                         WHERE st.id = ? FOR UPDATE",
                        [$targetTreeId]
                    );
                    $member = dbFetchOne(
                        "SELECT id, full_name, sponsor_type, first_letter_id, gender, supervisor_id, is_manual_override, created_by
                         FROM sponsors WHERE id = ? AND sponsor_type = 'individual' AND status = 'active'",
                        [$memberId]
                    );
                    if (!$tree || !$member || $tree['sponsor_type'] === 'individual') {
                        throw new RuntimeException('الشجرة أو العضو غير صالح.');
                    }
                    if ($role === 'supervisor' && !supervisorCanAccessSponsor($uid, $tree)) {
                        throw new RuntimeException('ليس لديك صلاحية لإدارة هذه الشجرة.');
                    }
                    if ($role === 'supervisor' && !supervisorCanAccessSponsor($uid, $member)) {
                        throw new RuntimeException('هذا الفرد ليس ضمن نطاق الكفلاء المسموح لك بإدارتهم.');
                    }
                    $existing = dbFetchOne(
                        "SELECT id, status FROM sponsor_tree_members WHERE tree_id = ? AND member_sponsor_id = ? FOR UPDATE",
                        [$targetTreeId, $memberId]
                    );
                    if ($existing) {
                        if ($existing['status'] === 'active') throw new RuntimeException('هذا الفرد عضو نشط بالفعل في هذه الشجرة.');
                        dbExecute(
                            "UPDATE sponsor_tree_members SET status = 'active', joined_at = NOW(), ended_at = NULL, updated_by = ? WHERE id = ?",
                            [$uid, (int) $existing['id']]
                        );
                        ak_tree_audit($uid, 'REACTIVATE', 'sponsor_tree_members', (int) $existing['id'], ['status' => 'inactive'], ['status' => 'active', 'tree_id' => $targetTreeId, 'member_sponsor_id' => $memberId]);
                    } else {
                        dbExecute(
                            "INSERT INTO sponsor_tree_members (tree_id, member_sponsor_id, status, created_by)
                             VALUES (?, ?, 'active', ?)",
                            [$targetTreeId, $memberId, $uid]
                        );
                        $membershipId = (int) dbLastInsertId();
                        ak_tree_audit($uid, 'CREATE', 'sponsor_tree_members', $membershipId, null, ['tree_id' => $targetTreeId, 'member_sponsor_id' => $memberId, 'status' => 'active']);
                    }
                    db()->commit();
                    flash('success', 'تمت إضافة العضو إلى الشجرة.');
                    header('Location: ' . APP_URL . 'modules/sponsors/trees.php?id=' . $targetTreeId);
                    exit();
                } catch (Throwable $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    throw $e;
                }
            } elseif ($action === 'set_member_status') {
                $targetTreeId = (int) ($_POST['tree_id'] ?? 0);
                $membershipId = (int) ($_POST['membership_id'] ?? 0);
                $newStatus = (string) ($_POST['new_status'] ?? '');
                if (!in_array($newStatus, ['active', 'inactive'], true)) throw new RuntimeException('حالة العضوية غير صحيحة.');
                db()->beginTransaction();
                try {
                    $tree = dbFetchOne(
                        "SELECT st.id, st.tree_type, s.id AS sponsor_id, s.sponsor_type, s.created_by, s.supervisor_id, s.first_letter_id, s.gender, s.is_manual_override
                         FROM sponsor_trees st JOIN sponsors s ON s.id = st.sponsor_id WHERE st.id = ? FOR UPDATE",
                        [$targetTreeId]
                    );
                    $membership = dbFetchOne(
                        "SELECT id, status FROM sponsor_tree_members WHERE id = ? AND tree_id = ? FOR UPDATE",
                        [$membershipId, $targetTreeId]
                    );
                    if (!$tree || !$membership) throw new RuntimeException('العضوية غير موجودة.');
                    if ($role === 'supervisor' && !supervisorCanAccessSponsor($uid, $tree)) {
                        throw new RuntimeException('ليس لديك صلاحية لإدارة هذه الشجرة.');
                    }
                    if ($membership['status'] !== $newStatus) {
                        if ($newStatus === 'inactive' && $tree['tree_type'] === 'required_members') {
                            $activeCount = (int) (dbFetchOne(
                                "SELECT COUNT(*) AS total FROM sponsor_tree_members WHERE tree_id = ? AND status = 'active'",
                                [$targetTreeId]
                            )['total'] ?? 0);
                            if ($activeCount <= 1) throw new RuntimeException('لا يمكن إيقاف آخر عضو نشط في هذا النوع من الأشجار.');
                        }
                        dbExecute(
                            "UPDATE sponsor_tree_members SET status = ?, ended_at = ?, updated_by = ? WHERE id = ?",
                            [$newStatus, $newStatus === 'inactive' ? date('Y-m-d H:i:s') : null, $uid, $membershipId]
                        );
                        ak_tree_audit($uid, $newStatus === 'active' ? 'REACTIVATE' : 'DEACTIVATE', 'sponsor_tree_members', $membershipId, ['status' => $membership['status']], ['status' => $newStatus, 'tree_id' => $targetTreeId]);
                    }
                    db()->commit();
                    flash('success', 'تم تحديث حالة العضوية دون تغيير حالة الفرد أو سجلات كفالاته.');
                    header('Location: ' . APP_URL . 'modules/sponsors/trees.php?id=' . $targetTreeId);
                    exit();
                } catch (Throwable $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    throw $e;
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$treeSql = "SELECT st.id, st.tree_type, st.created_at, s.id AS sponsor_id, s.full_name, s.sponsor_code, s.sponsor_type, s.status AS sponsor_status,
            COUNT(CASE WHEN tm.status = 'active' THEN 1 END) AS active_members,
            COUNT(tm.id) AS total_members
     FROM sponsor_trees st
     JOIN sponsors s ON s.id = st.sponsor_id
     LEFT JOIN sponsor_tree_members tm ON tm.tree_id = st.id";
$treeParams = [];
if ($role === 'supervisor') {
    $treeSql .= " WHERE s.created_by = ?";
    $treeParams[] = $uid;
}
$treeSql .= " GROUP BY st.id, st.tree_type, st.created_at, s.id, s.full_name, s.sponsor_code, s.sponsor_type, s.status ORDER BY st.id DESC";
$trees = dbFetchAll($treeSql, $treeParams);
$currentTree = $treeId > 0 ? dbFetchOne(
    "SELECT st.*, s.id AS sponsor_id, s.full_name, s.sponsor_code, s.sponsor_type, s.status AS sponsor_status,
            s.created_by, s.supervisor_id, s.first_letter_id, s.gender, s.is_manual_override,
            s.phone, s.email, s.address, s.contact_person_name, s.contact_person_position,
            s.preferred_contact_method, s.preferred_contact_time
     FROM sponsor_trees st JOIN sponsors s ON s.id = st.sponsor_id WHERE st.id = ?",
    [$treeId]
) : null;
if ($role === 'supervisor' && $currentTree && !supervisorCanAccessSponsor($uid, $currentTree)) {
    http_response_code(403);
    exit('ليس لديك صلاحية لإدارة هذه الشجرة.');
}
$members = [];
$availableMembers = [];
if ($currentTree) {
    $members = dbFetchAll(
        "SELECT tm.id AS membership_id, tm.status AS membership_status, tm.joined_at, tm.ended_at,
                s.id AS sponsor_id, s.full_name, s.sponsor_code, s.status AS sponsor_status, s.phone
         FROM sponsor_tree_members tm JOIN sponsors s ON s.id = tm.member_sponsor_id
         WHERE tm.tree_id = ? ORDER BY tm.status ASC, s.full_name ASC",
        [$treeId]
    );
    $availableMembers = dbFetchAll(
        "SELECT s.id, s.full_name, s.sponsor_code, s.first_letter_id, s.gender, s.supervisor_id, s.is_manual_override, s.created_by
         FROM sponsors s
         WHERE s.sponsor_type = 'individual' AND s.status = 'active'
           AND NOT EXISTS (SELECT 1 FROM sponsor_tree_members tm WHERE tm.tree_id = ? AND tm.member_sponsor_id = s.id AND tm.status = 'active')
         ORDER BY s.full_name LIMIT 500",
        [$treeId]
    );
    if ($role === 'supervisor') {
        $availableMembers = array_values(array_filter($availableMembers, static fn(array $member): bool => supervisorCanAccessSponsor($uid, $member)));
    }
}
$activeMembers = array_values(array_filter($members, static fn(array $m): bool => $m['membership_status'] === 'active'));
$people = dbFetchAll(
    "SELECT id, full_name, COALESCE(NULLIF(TRIM(sponsor_code), ''), CONCAT('SP-', LPAD(id, 6, '0'))) AS sponsor_code,
            first_letter_id, gender, supervisor_id, is_manual_override, created_by
     FROM sponsors WHERE sponsor_type = 'individual' AND status = 'active' ORDER BY full_name"
);
if ($role === 'supervisor') {
    $people = array_values(array_filter($people, static fn(array $person): bool => supervisorCanAccessSponsor($uid, $person)));
}
$memberSupervisors = dbFetchAll(
    "SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id
     WHERE r.code IN ('supervisor', 'admin', 'vice_general_manager') ORDER BY u.full_name"
);
$memberGenders = array_values(array_unique(array_filter(array_map(
    static fn(array $person): string => trim((string) ($person['gender'] ?? '')),
    $people
))));
sort($memberGenders, SORT_NATURAL | SORT_FLAG_CASE);
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2>أشجار الكفلاء</h2>
    <p class="mb-2">كل شجرة تُسجّل ككفيل عادي، وعضويات الأفراد مستقلة عن كفالات الأيتام وسجلات الدفع.</p>
    <div class="quick-actions"><a class="btn btn-primary btn-sm" href="#createTree"><i class="fas fa-plus me-1"></i>إنشاء شجرة جديدة</a></div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($currentTree): ?>
<div class="card mb-4 fade-in">
    <div class="card-header d-flex justify-content-between align-items-center"><strong><?php echo e($currentTree['full_name']); ?></strong><a class="btn btn-outline-secondary btn-sm" href="<?php echo APP_URL; ?>modules/sponsors/trees.php">كل الأشجار</a></div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3"><small class="text-muted">كود الكفيل</small><div><?php echo e($currentTree['sponsor_code']); ?></div></div>
            <div class="col-md-3"><small class="text-muted">نوع الجهة</small><div><?php echo e($currentTree['sponsor_type']); ?></div></div>
            <div class="col-md-3"><small class="text-muted">نوع الشجرة</small><div><?php echo $currentTree['tree_type'] === 'required_members' ? 'أعضاء إلزاميون' : 'أعضاء اختياريون'; ?></div></div>
            <div class="col-md-3"><small class="text-muted">حالة الكفيل</small><div><?php echo e($currentTree['sponsor_status']); ?></div></div>
            <div class="col-md-3"><small class="text-muted">الشخص المسؤول للتواصل</small><div><?php echo e($currentTree['contact_person_name'] ?: '—'); ?></div></div>
            <div class="col-md-3"><small class="text-muted">المنصب</small><div><?php echo e($currentTree['contact_person_position'] ?: '—'); ?></div></div>
            <div class="col-md-3"><small class="text-muted">الهاتف</small><div dir="ltr"><?php echo e($currentTree['phone'] ?: '—'); ?></div></div>
            <div class="col-md-3"><small class="text-muted">البريد</small><div><?php echo e($currentTree['email'] ?: '—'); ?></div></div>
        </div>
        <p class="text-muted small mb-0">إيقاف عضوية أو تعطيل ملف فرد لا يغيّر أي كفالة أو دفعة مرتبطة بذلك الفرد.</p>
    </div>
</div>
<div class="card mb-4 fade-in">
    <div class="card-header">أعضاء الشجرة (<?php echo count($activeMembers); ?> نشط)</div>
    <div class="card-body">
        <?php if (!$members): ?><p class="text-muted mb-0">لا يوجد أعضاء مضافون حالياً. يمكن إضافة أعضاء لاحقاً لهذا النوع من الأشجار.</p><?php else: ?>
        <div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>الفرد</th><th>كود الكفيل</th><th>حالة الفرد</th><th>حالة العضوية</th><th>تاريخ الانضمام</th><th>الإجراء</th></tr></thead><tbody>
        <?php foreach ($members as $member): ?><tr>
            <td><a href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo (int) $member['sponsor_id']; ?>"><?php echo e($member['full_name']); ?></a></td>
            <td><?php echo e($member['sponsor_code']); ?></td><td><?php echo e($member['sponsor_status']); ?></td>
            <td><?php echo $member['membership_status'] === 'active' ? '<span class="badge bg-success">نشط</span>' : '<span class="badge bg-secondary">غير نشط</span>'; ?></td>
            <td><?php echo e($member['joined_at']); ?></td>
            <td><?php if ($member['membership_status'] === 'active'): ?><form method="post" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="action" value="set_member_status"><input type="hidden" name="tree_id" value="<?php echo $treeId; ?>"><input type="hidden" name="membership_id" value="<?php echo (int) $member['membership_id']; ?>"><input type="hidden" name="new_status" value="inactive"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('إيقاف العضوية فقط؟ لن يتم تعطيل الفرد أو المساس بكفالاته.')">إزالة من الشجرة</button></form><?php else: ?><form method="post" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="action" value="set_member_status"><input type="hidden" name="tree_id" value="<?php echo $treeId; ?>"><input type="hidden" name="membership_id" value="<?php echo (int) $member['membership_id']; ?>"><input type="hidden" name="new_status" value="active"><button class="btn btn-sm btn-outline-success">إعادة تفعيل العضوية</button></form><?php endif; ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
        <hr><h6>إضافة فرد إلى الشجرة</h6>
        <?php if ($availableMembers): ?><form method="post" class="row g-2 align-items-end"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_member"><input type="hidden" name="tree_id" value="<?php echo $treeId; ?>"><div class="col-md-8"><label class="form-label">الفرد</label><select class="form-select" name="member_sponsor_id" required><option value="">اختر فرداً نشطاً</option><?php foreach ($availableMembers as $person): ?><option value="<?php echo (int) $person['id']; ?>"><?php echo e($person['full_name']); ?> (<?php echo e($person['sponsor_code']); ?>)</option><?php endforeach; ?></select></div><div class="col-md-4"><button class="btn btn-primary w-100">إضافة العضو</button></div></form><?php else: ?><p class="text-muted small mb-0">لا يوجد أفراد كفلاء نشطون متاحون للإضافة.</p><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4 fade-in" id="createTree">
    <div class="card-header">إنشاء شجرة جديدة</div>
    <div class="card-body">
        <form method="post"><input type="hidden" name="action" value="create_tree"><?php echo csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">اسم الشجرة *</label><input class="form-control" name="full_name" required></div>
                <div class="col-md-3"><label class="form-label">نوع الجهة *</label><select class="form-select" name="sponsor_type" required><option value="">اختر</option><option value="company">شركة</option><option value="organization">منظمة</option></select></div>
                <div class="col-md-3"><label class="form-label">نوع الشجرة *</label><select class="form-select" name="tree_type" id="treeType" required><option value="">اختر</option><option value="optional_members">أعضاء اختياريون (يمكن الإنشاء دون أعضاء)</option><option value="required_members">أعضاء إلزاميون (عضو واحد على الأقل)</option></select></div>
                <div class="col-md-3"><label class="form-label">الهاتف</label><input class="form-control" name="phone" dir="ltr"></div>
                <div class="col-md-3"><label class="form-label">البريد الإلكتروني</label><input class="form-control" type="email" name="email" dir="ltr"></div>
                <div class="col-md-6"><label class="form-label">العنوان</label><input class="form-control" name="address"></div>
                <div class="col-md-3"><label class="form-label">اسم مسؤول التواصل</label><input class="form-control" name="contact_person_name"></div>
                <div class="col-md-3"><label class="form-label">منصبه</label><input class="form-control" name="contact_person_position"></div>
                <div class="col-md-3"><label class="form-label">طريقة التواصل</label><select class="form-select" name="preferred_contact_method"><option value="any">أي وسيلة</option><option value="call">اتصال</option><option value="whatsapp">واتساب</option><option value="email">بريد إلكتروني</option></select></div>
                <div class="col-md-3"><label class="form-label">الوقت المناسب</label><input class="form-control" name="preferred_contact_time"></div>
                <div class="col-md-3"><label class="form-label">طريقة الدفع المفضلة</label><select class="form-select" name="payment"><option value="cash">نقدي</option><option value="bank_transfer">تحويل بنكي</option><option value="mobile">محفظة إلكترونية</option><option value="other">أخرى</option></select></div>
                <div class="col-12">
                    <label class="form-label">أعضاء الشجرة (يمكن اختيار أكثر من فرد)</label>
                    <div class="tree-member-picker border rounded-3 p-3">
                        <div class="row g-2 mb-3">
                            <div class="col-lg-6">
                                <label class="form-label" for="treeMemberSearch">بحث بالاسم أو كود الكفيل</label>
                                <input type="search" class="form-control" id="treeMemberSearch" placeholder="اكتب اسم الفرد أو كود الكفيل..." autocomplete="off">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label class="form-label" for="treeMemberGenderFilter">الجنس</label>
                                <select class="form-select" id="treeMemberGenderFilter">
                                    <option value="">كل الأفراد</option>
                                    <?php foreach ($memberGenders as $gender): ?>
                                        <option value="<?php echo e($gender); ?>"><?php echo e(ak_tree_gender_label($gender)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label class="form-label" for="treeMemberSupervisorFilter">المشرف</label>
                                <select class="form-select" id="treeMemberSupervisorFilter">
                                    <option value="">كل المشرفين</option>
                                    <option value="0">بدون مشرف</option>
                                    <?php foreach ($memberSupervisors as $memberSupervisor): ?>
                                        <option value="<?php echo (int) $memberSupervisor['id']; ?>"><?php echo e($memberSupervisor['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="small fw-bold" id="treeMemberCounts" aria-live="polite">المعروض: <?php echo count($people); ?> | المحدد: <?php echo count($submittedMemberIds); ?></span>
                            <span class="flex-grow-1"></span>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="treeMemberSelectVisible">تحديد النتائج الظاهرة</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="treeMemberClearVisible">إلغاء تحديد الظاهر</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="treeMemberClearAll">إلغاء تحديد الكل</button>
                        </div>
                        <div class="border rounded-2 overflow-auto" id="treeMemberOptions" style="max-height: 340px;">
                            <?php foreach ($people as $person): ?>
                                <?php $personSupervisorId = (int) ($person['supervisor_id'] ?? 0); ?>
                                <label class="tree-member-option d-flex align-items-center gap-2 p-2 border-bottom mb-0"
                                       data-name="<?php echo e($person['full_name']); ?>"
                                       data-code="<?php echo e($person['sponsor_code']); ?>"
                                       data-gender="<?php echo e((string) ($person['gender'] ?? '')); ?>"
                                       data-supervisor="<?php echo $personSupervisorId; ?>">
                                    <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="member_ids[]"
                                           value="<?php echo (int) $person['id']; ?>" data-tree-member-checkbox
                                           <?php echo in_array((int) $person['id'], $submittedMemberIds, true) ? 'checked' : ''; ?>>
                                    <span class="flex-grow-1">
                                        <span class="d-block fw-semibold"><?php echo e($person['full_name']); ?></span>
                                        <small class="text-muted"><?php echo e($person['sponsor_code']); ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                            <div class="p-3 text-center text-muted" id="treeMemberNoResults" <?php echo $people ? 'hidden' : ''; ?>>
                                <?php echo $people ? 'لا توجد نتائج مطابقة للبحث أو عوامل التصفية.' : 'لا يوجد أفراد كفلاء نشطون متاحون.'; ?>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">الفرد الواحد يمكنه الانضمام إلى أكثر من شجرة. التصفية لا تلغي اختياراتك السابقة، ويمكن إدارة العضويات لاحقاً دون حذف سجل الفرد.</small>
                    </div>
                </div>
            </div>
            <div class="mt-3"><button class="btn btn-primary">إنشاء الشجرة</button></div>
        </form>
    </div>
</div>

<div class="card fade-in"><div class="card-header">الأشجار المسجلة</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>الشجرة</th><th>الكود</th><th>نوع الشجرة</th><th>الأعضاء النشطون</th><th>إجمالي العضويات</th><th>حالة الكفيل</th><th></th></tr></thead><tbody>
<?php if (!$trees): ?><tr><td colspan="7" class="text-center text-muted py-3">لا توجد أشجار مسجلة.</td></tr><?php else: foreach ($trees as $tree): ?><tr><td><?php echo e($tree['full_name']); ?></td><td><?php echo e($tree['sponsor_code']); ?></td><td><?php echo $tree['tree_type'] === 'required_members' ? 'أعضاء إلزاميون' : 'أعضاء اختياريون'; ?></td><td><?php echo (int) $tree['active_members']; ?></td><td><?php echo (int) $tree['total_members']; ?></td><td><?php echo e($tree['sponsor_status']); ?></td><td><a class="btn btn-sm btn-primary" href="<?php echo APP_URL; ?>modules/sponsors/trees.php?id=<?php echo (int) $tree['id']; ?>">إدارة الأعضاء</a><a class="btn btn-sm btn-outline-secondary ms-1" href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo (int) $tree['sponsor_id']; ?>">ملف الكفيل</a></td></tr><?php endforeach; endif; ?>
</tbody></table></div></div></div>
<script>
(function () {
    const picker = document.querySelector('.tree-member-picker');
    if (!picker) return;

    const search = picker.querySelector('#treeMemberSearch');
    const gender = picker.querySelector('#treeMemberGenderFilter');
    const supervisor = picker.querySelector('#treeMemberSupervisorFilter');
    const rows = Array.from(picker.querySelectorAll('.tree-member-option'));
    const counts = picker.querySelector('#treeMemberCounts');
    const empty = picker.querySelector('#treeMemberNoResults');
    const normalize = (value) => String(value || '')
        .normalize('NFKD')
        .replace(/[\\u064B-\\u065F\\u0670]/g, '')
        .replace(/[أإآ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .toLocaleLowerCase('ar');

    function updatePicker() {
        const query = normalize(search.value.trim());
        const selectedGender = gender.value;
        const selectedSupervisor = supervisor.value;
        let visible = 0;
        let selected = 0;

        rows.forEach((row) => {
            const text = normalize(row.dataset.name + ' ' + row.dataset.code);
            const matches = (!query || text.includes(query))
                && (!selectedGender || row.dataset.gender === selectedGender)
                && (!selectedSupervisor || row.dataset.supervisor === selectedSupervisor);
            row.hidden = !matches;
            if (matches) visible++;
            const checkbox = row.querySelector('[data-tree-member-checkbox]');
            if (checkbox && checkbox.checked) selected++;
        });

        if (counts) counts.textContent = 'المعروض: ' + visible + ' من ' + rows.length + ' | المحدد: ' + selected;
        if (empty) empty.hidden = visible > 0;
    }

    function setVisibleSelection(checked) {
        rows.forEach((row) => {
            if (row.hidden) return;
            const checkbox = row.querySelector('[data-tree-member-checkbox]');
            if (checkbox) checkbox.checked = checked;
        });
        updatePicker();
    }

    search.addEventListener('input', updatePicker);
    gender.addEventListener('change', updatePicker);
    supervisor.addEventListener('change', updatePicker);
    picker.querySelector('#treeMemberSelectVisible').addEventListener('click', () => setVisibleSelection(true));
    picker.querySelector('#treeMemberClearVisible').addEventListener('click', () => setVisibleSelection(false));
    picker.querySelector('#treeMemberClearAll').addEventListener('click', () => {
        rows.forEach((row) => {
            const checkbox = row.querySelector('[data-tree-member-checkbox]');
            if (checkbox) checkbox.checked = false;
        });
        updatePicker();
    });
    rows.forEach((row) => {
        const checkbox = row.querySelector('[data-tree-member-checkbox]');
        if (checkbox) checkbox.addEventListener('change', updatePicker);
    });
    updatePicker();
})();
</script>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
