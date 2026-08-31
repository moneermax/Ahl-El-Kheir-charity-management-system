<?php
/**
 * Deputy General Manager / VGM - Orphan Groups Management
 * Uses dynamic column discovery - no hardcoded column names
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/functions.php';

Session::start();

// Access guard
$user_role = Session::getUserRole();
if (!Session::isLoggedIn() || ($user_role !== 'vice_general_manager' && $user_role !== 'vgm')) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$success_message = '';
$error_message = '';
$pdo = db();

// Column names below are hardcoded from the verified schema (see backup_20260821_094902.sql) —
// do not replace this with runtime INFORMATION_SCHEMA discovery. That approach previously
// picked family_children.is_active (a per-child flag) as a stand-in for "is this family
// active", silently ignoring families.status entirely. An inactive family's children kept
// showing up as selectable for new groups because nobody had ever flagged that specific
// child inactive. Hardcoding removes the guess.
$current_month = date('Y-m');

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CREATE NEW GROUP
    if (isset($_POST['action']) && $_POST['action'] === 'create_group') {
        $group_name  = trim($_POST['group_name']);
        $description = trim($_POST['description']);
        $created_by  = Session::getUserId();
        if (empty($group_name)) {
            $error_message = 'اسم المجموعة مطلوب';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO orphan_groups (group_name, description, created_by, is_active) VALUES (:group_name, :description, :created_by, 1)");
                $stmt->execute(['group_name' => $group_name, 'description' => $description, 'created_by' => $created_by]);
                $success_message = 'تم إنشاء المجموعة بنجاح';
            } catch (PDOException $e) {
                $error_message = 'خطأ: ' . $e->getMessage();
            }
        }
    }

    // 2. ASSIGN NANNY TO GROUP
    if (isset($_POST['action']) && $_POST['action'] === 'assign_nanny') {
        $group_id    = (int)$_POST['group_id'];
        $nanny_id    = (int)$_POST['nanny_id'];
        $assigned_by = Session::getUserId();
        if (empty($nanny_id)) {
            $error_message = 'يرجى اختيار الحاضنة';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE nanny_group_assignments SET end_date = CURDATE() WHERE group_id = :group_id AND end_date IS NULL")->execute(['group_id' => $group_id]);
                $pdo->prepare("INSERT INTO nanny_group_assignments (group_id, nanny_id, start_date, assigned_by) VALUES (:group_id, :nanny_id, CURDATE(), :assigned_by)")->execute(['group_id' => $group_id, 'nanny_id' => $nanny_id, 'assigned_by' => $assigned_by]);
                $pdo->commit();
                $success_message = 'تم تعيين الحاضنة للمجموعة بنجاح';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = 'خطأ: ' . $e->getMessage();
            }
        }
    }

    // 3. ADD SINGLE ORPHAN TO GROUP (kept for backward compatibility)
    if (isset($_POST['action']) && $_POST['action'] === 'add_orphan') {
        $group_id = (int)$_POST['group_id'];
        $child_id = (int)$_POST['child_id'];
        $added_by = Session::getUserId();
        try {
            $pdo->prepare("INSERT INTO group_children (group_id, child_id, joined_date, added_by) VALUES (:group_id, :child_id, CURDATE(), :added_by)")->execute(['group_id' => $group_id, 'child_id' => $child_id, 'added_by' => $added_by]);
            $success_message = 'تم إضافة اليتيم للمجموعة بنجاح';
        } catch (PDOException $e) {
            $error_message = ($e->getCode() == 23000) ? 'هذا اليتيم موجود بالفعل' : 'خطأ: ' . $e->getMessage();
        }
    }

    // 3.5. BULK ADD ORPHANS TO GROUP (NEW)
    if (isset($_POST['action']) && $_POST['action'] === 'add_orphan_bulk') {
        $group_id = (int)$_POST['group_id'];
        $child_ids = isset($_POST['child_ids']) ? $_POST['child_ids'] : [];
        $added_by = Session::getUserId();

        if (empty($child_ids)) {
            $error_message = 'لم يتم اختيار أي يتيم';
        } else {
            $added_count = 0;
            $skipped_count = 0;
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO group_children (group_id, child_id, joined_date, added_by) VALUES (:group_id, :child_id, CURDATE(), :added_by)");
                foreach ($child_ids as $child_id) {
                    $child_id = (int)$child_id;
                    try {
                        $stmt->execute(['group_id' => $group_id, 'child_id' => $child_id, 'added_by' => $added_by]);
                        $added_count++;
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $skipped_count++;
                        } else {
                            throw $e;
                        }
                    }
                }
                $pdo->commit();
                $success_message = "تم إضافة {$added_count} يتيم بنجاح";
                if ($skipped_count > 0) {
                    $success_message .= " (تم تخطي {$skipped_count} موجودين مسبقاً)";
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = 'حدث خطأ أثناء الإضافة: ' . $e->getMessage();
            }
        }
    }

    // 4. REMOVE ORPHAN FROM GROUP
    if (isset($_POST['action']) && $_POST['action'] === 'remove_orphan') {
        $group_id = (int)$_POST['group_id'];
        $child_id = (int)$_POST['child_id'];
        try {
            $pdo->prepare("UPDATE group_children SET left_date = CURDATE() WHERE group_id = :group_id AND child_id = :child_id AND left_date IS NULL")->execute(['group_id' => $group_id, 'child_id' => $child_id]);
            $success_message = 'تم إزالة اليتيم من المجموعة بنجاح';
        } catch (PDOException $e) {
            $error_message = 'خطأ: ' . $e->getMessage();
        }
    }

    // 5. DEACTIVATE GROUP
    if (isset($_POST['action']) && $_POST['action'] === 'deactivate_group') {
        $group_id = (int)$_POST['group_id'];
        try {
            $pdo->prepare("UPDATE orphan_groups SET is_active = 0 WHERE id = :group_id")->execute(['group_id' => $group_id]);
            $success_message = 'تم تعطيل المجموعة';
        } catch (PDOException $e) {
            $error_message = 'خطأ: ' . $e->getMessage();
        }
    }
}

// FETCH DATA

$groups = dbFetchAll("SELECT og.*, u.full_name as nanny_name, nga.start_date as assignment_start_date, (SELECT COUNT(*) FROM group_children gc WHERE gc.group_id = og.id AND gc.left_date IS NULL) as orphan_count FROM orphan_groups og LEFT JOIN nanny_group_assignments nga ON og.id = nga.group_id AND nga.end_date IS NULL LEFT JOIN users u ON nga.nanny_id = u.id ORDER BY og.created_at DESC");

$nannies = dbFetchAll("SELECT u.id, u.full_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = 'nanny' AND u.is_active = 1 ORDER BY u.full_name ASC");

$orphans = dbFetchAll("
    SELECT fc.id, fc.child_name, f.id AS family_id, f.mother_name AS family_name, f.family_code,
           TIMESTAMPDIFF(YEAR, fc.birth_date, CURDATE()) AS age,
           sp.sponsor_id, s.full_name AS sponsor_name
    FROM family_children fc
    JOIN families f ON f.id = fc.family_id
    JOIN sponsorships sp ON sp.child_id = fc.id AND sp.status = 'active'
    JOIN sponsors s ON s.id = sp.sponsor_id
    WHERE fc.is_active = 1
      AND f.status = 'active'
      AND NOT EXISTS (
          -- Excludes this CHILD only if they are already a member of some group
          -- that already has a disbursement batch this month. Checking at the
          -- family level here would be wrong: siblings can have different
          -- sponsors and end up in different groups/nannies, so one sibling
          -- already being paid must not hide the other.
          SELECT 1 FROM group_children gc2
          JOIN monthly_disbursements md ON md.group_id = gc2.group_id AND md.month = :current_month
          WHERE gc2.child_id = fc.id AND gc2.left_date IS NULL
      )
    ORDER BY f.mother_name ASC, fc.child_name ASC
", ['current_month' => $current_month]);

$selected_group = null;
$group_orphans = [];
$group_nanny_history = [];

if (isset($_GET['view_group']) && !empty($_GET['view_group'])) {
    $group_id = (int)$_GET['view_group'];
    $selected_group = dbFetchOne("SELECT * FROM orphan_groups WHERE id = :id", ['id' => $group_id]);
    if ($selected_group) {
        $group_orphans = dbFetchAll("
            SELECT gc.*, fc.child_name, f.mother_name AS family_name,
                   TIMESTAMPDIFF(YEAR, fc.birth_date, CURDATE()) AS age
            FROM group_children gc
            JOIN family_children fc ON gc.child_id = fc.id
            LEFT JOIN families f ON fc.family_id = f.id
            WHERE gc.group_id = :group_id AND gc.left_date IS NULL
            ORDER BY fc.child_name ASC
        ", ['group_id' => $group_id]);
        $group_nanny_history = dbFetchAll("SELECT nga.*, u.full_name as nanny_name, u2.full_name as assigned_by_name FROM nanny_group_assignments nga JOIN users u ON nga.nanny_id = u.id LEFT JOIN users u2 ON nga.assigned_by = u2.id WHERE nga.group_id = :group_id ORDER BY nga.start_date DESC", ['group_id' => $group_id]);
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-3"><i class="bi bi-people-fill"></i> إدارة مجموعات الأيتام</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/vgm_dashboard.php">الرئيسية</a></li>
                    <li class="breadcrumb-item active">مجموعات الأيتام</li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card mb-3">
                <div class="card-header bg-primary text-white py-2"><h6 class="mb-0"><i class="bi bi-plus-circle"></i> إنشاء مجموعة جديدة</h6></div>
                <div class="card-body py-2">
                    <form method="POST" action=""><input type="hidden" name="action" value="create_group">
                        <div class="mb-2"><input type="text" class="form-control form-control-sm" name="group_name" placeholder="اسم المجموعة *" required></div>
                        <div class="mb-2"><textarea class="form-control form-control-sm" name="description" rows="1" placeholder="الوصف (اختياري)"></textarea></div>
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-save"></i> حفظ المجموعة</button>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="bi bi-list-ul"></i> المجموعات</h5></div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($groups)): ?>
                            <div class="list-group-item text-center text-muted py-4">لا توجد مجموعات حتى الآن</div>
                        <?php else: foreach ($groups as $group): ?>
                            <a href="?view_group=<?php echo $group['id']; ?>" class="list-group-item list-group-item-action <?php echo ($selected_group && $selected_group['id'] == $group['id']) ? 'active' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between"><h6 class="mb-1"><?php echo htmlspecialchars($group['group_name']); ?></h6><small><?php echo $group['orphan_count']; ?> يتيم</small></div>
                                <p class="mb-1 small text-muted"><?php if ($group['nanny_name']): ?><i class="bi bi-person"></i> <?php echo htmlspecialchars($group['nanny_name']); ?><?php else: ?><span class="text-warning">بدون حاضنة</span><?php endif; ?></p>
                            </a>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <?php if ($selected_group): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-folder"></i> <?php echo htmlspecialchars($selected_group['group_name']); ?></h5>
                        <span class="badge bg-<?php echo $selected_group['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $selected_group['is_active'] ? 'نشط' : 'معطل'; ?></span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($selected_group['description'] ?: 'لا يوجد وصف')); ?></p>
                        <div class="row mb-3">
                            <div class="col-md-6"><strong>تاريخ الإنشاء:</strong> <span class="text-muted"><?php echo date('Y-m-d', strtotime($selected_group['created_at'])); ?></span></div>
                            <div class="col-md-6"><strong>عدد الأيتام:</strong> <span class="text-muted"><?php echo count($group_orphans); ?></span></div>
                        </div>

                        <div class="card bg-light mb-3"><div class="card-body">
                            <h6 class="card-title mb-3"><i class="bi bi-person-badge"></i> تعيين حاضنة للمجموعة</h6>
                            <form method="POST" action="" class="row g-2"><input type="hidden" name="action" value="assign_nanny"><input type="hidden" name="group_id" value="<?php echo $selected_group['id']; ?>">
                                <div class="col-md-6"><select class="form-select" name="nanny_id" required><option value="">-- اختر الحاضنة --</option><?php foreach ($nannies as $nanny): ?><option value="<?php echo $nanny['id']; ?>"><?php echo htmlspecialchars($nanny['full_name']); ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-6"><button type="submit" class="btn btn-success w-100"><i class="bi bi-check-circle"></i> تعيين</button></div>
                            </form>
                        </div></div>

                        <?php if (!empty($group_nanny_history) && $group_nanny_history[0]['end_date'] === null): ?>
                            <div class="alert alert-info"><strong><i class="bi bi-info-circle"></i> الحاضنة الحالية:</strong> <?php echo htmlspecialchars($group_nanny_history[0]['nanny_name']); ?><br><small>منذ: <?php echo date('Y-m-d', strtotime($group_nanny_history[0]['start_date'])); ?></small></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Current members of this group -->
                <div class="card mb-4">
                    <div class="card-header bg-warning"><h5 class="mb-0"><i class="bi bi-people"></i> الأيتام في المجموعة (<?php echo count($group_orphans); ?>)</h5></div>
                    <div class="card-body">
                        <?php if (empty($group_orphans)): ?>
                            <p class="text-muted mb-0">لا يوجد أيتام في هذه المجموعة بعد. استخدم قسم "إضافة أيتام جدد" أسفل الصفحة.</p>
                        <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>الاسم</th>
                                                <th>العمر</th>
                                                <th>الأسرة</th>
                                                <th>تاريخ الإضافة</th>
                                                <th>إجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $c = 1; foreach ($group_orphans as $orphan): ?>
                                                <tr>
                                                    <td><?php echo $c++; ?></td>
                                                    <td><?php echo htmlspecialchars($orphan['child_name']); ?></td>
                                                    <td><?php echo $orphan['age']; ?> سنة</td>
                                                    <td><?php echo htmlspecialchars($orphan['family_name'] ?: '-'); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($orphan['joined_date'])); ?></td>
                                                    <td>
                                                        <form method="POST" action="" id="removeOrphanForm-<?php echo (int)$orphan['child_id']; ?>" style="display: inline;">
                                                            <input type="hidden" name="action" value="remove_orphan">
                                                            <input type="hidden" name="group_id" value="<?php echo $selected_group['id']; ?>">
                                                            <input type="hidden" name="child_id" value="<?php echo $orphan['child_id']; ?>">
                                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmRemoveOrphan('<?php echo (int)$orphan['child_id']; ?>', '<?php echo htmlspecialchars($orphan['child_name'], ENT_QUOTES); ?>')"><i class="bi bi-trash"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-secondary text-white"><h5 class="mb-0"><i class="bi bi-clock-history"></i> سجل تعيين الحاضنات</h5></div>
                    <div class="card-body">
                        <?php if (empty($group_nanny_history)): ?>
                            <p class="text-muted mb-0">لا يوجد سجل تعيينات</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr><th>الحاضنة</th><th>تاريخ البداية</th><th>تاريخ النهاية</th><th>الحالة</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($group_nanny_history as $a): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($a['nanny_name']); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($a['start_date'])); ?></td>
                                                <td><?php echo $a['end_date'] ? date('Y-m-d', strtotime($a['end_date'])) : '<span class="badge bg-success">حالي</span>'; ?></td>
                                                <td><?php echo $a['end_date'] ? '<span class="badge bg-secondary">منتهية</span>' : '<span class="badge bg-success">نشطة</span>'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($selected_group['is_active']): ?>
                    <div class="mt-3 text-end">
                        <form method="POST" action="" id="deactivateGroupForm" style="display: inline;">
                            <input type="hidden" name="action" value="deactivate_group">
                            <input type="hidden" name="group_id" value="<?php echo $selected_group['id']; ?>">
                            <button type="button" class="btn btn-outline-danger" onclick="confirmDeactivateGroup()"><i class="bi bi-x-circle"></i> تعطيل المجموعة</button>
                        </form>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-folder2-open" style="font-size: 4rem; color: #ccc;"></i>
                        <h5 class="mt-3 text-muted">اختر مجموعة من القائمة لعرض التفاصيل</h5>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selected_group): ?>
    <div class="row mt-4">
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

                    <!-- Search Box -->
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="orphanSearch" placeholder="ابحث بالاسم أو اسم الأسرة أو الكفيل...">
                            <button class="btn btn-outline-secondary" type="button" id="clearSearch">مسح</button>
                        </div>
                        <div class="form-text">اختر الأيتام ثم اضغط على "إضافة المحددين"</div>
                    </div>

                    <!-- Available Orphans Table -->
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover table-bordered" id="orphansTable">
                            <thead class="table-primary sticky-top" style="z-index: 10; background: white;">
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>#</th>
                                    <th>الاسم</th>
                                    <th>العمر</th>
                                    <th>الأسرة</th>
                                    <th>الكفيل</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $counter = 1;
                                $lastFamilyId = null;
                                foreach ($orphans as $orphan):
                                    $already_in_group = false;
                                    foreach ($group_orphans as $go) {
                                        if ($go['child_id'] == $orphan['id']) {
                                            $already_in_group = true;
                                            break;
                                        }
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
                                    <tr class="orphan-row" data-family="<?php echo (int)$orphan['family_id']; ?>" data-name="<?php echo strtolower(htmlspecialchars($orphan['child_name'] . ' ' . ($orphan['family_name'] ?? '') . ' ' . ($orphan['sponsor_name'] ?? ''))); ?>">
                                        <td>
                                            <input type="checkbox" class="form-check-input orphan-checkbox" data-family="<?php echo (int)$orphan['family_id']; ?>" name="selected_orphans[]" value="<?php echo $orphan['id']; ?>">
                                        </td>
                                        <td><?php echo $counter++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($orphan['child_name']); ?></strong></td>
                                        <td><?php echo $orphan['age']; ?> سنة</td>
                                        <td><?php echo htmlspecialchars($orphan['family_name'] ?: '-'); ?></td>
                                        <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($orphan['sponsor_name'] ?: '—'); ?></span></td>
                                        <td><span class="badge bg-success">متاح</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Selected Count & Add Button -->
                    <div class="d-flex justify-content-between align-items-center mt-3 p-3 bg-white border rounded">
                        <div>
                            <strong>المحدد: <span id="selectedCount" class="text-primary">0</span> يتيم</strong>
                        </div>
                        <button type="button" class="btn btn-warning" id="addSelectedBtn" onclick="addSelectedOrphans()">
                            <i class="bi bi-plus-circle"></i> إضافة المحددين (<span id="btnCount">0</span>)
                        </button>
                    </div>

                    <!-- Hidden form for bulk add -->
                    <form method="POST" action="" id="bulkAddForm" style="display: none;">
                        <input type="hidden" name="action" value="add_orphan_bulk">
                        <input type="hidden" name="group_id" value="<?php echo $selected_group['id']; ?>">
                        <div id="orphansContainer"></div>
                    </form>

                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- SweetAlert2 for Custom Confirmations -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Search functionality - Enhanced with exact + partial matching
document.getElementById('orphanSearch').addEventListener('keyup', function() {
    const searchTerm = this.value.trim().toLowerCase();
    const rows = document.querySelectorAll('.orphan-row');
    
    if (searchTerm === '') {
        // Show all rows if search is empty
        rows.forEach(row => {
            row.style.display = '';
            row.style.backgroundColor = '';
        });
        syncFamilyHeaderVisibility();
        updateSelectedCount();
        return;
    }
    
    // Split search term into words for multi-word search
    const searchWords = searchTerm.split(/\s+/).filter(word => word.length > 0);
    
    rows.forEach(row => {
        const name = row.getAttribute('data-name');
        let matchScore = 0;
        let shouldShow = false;
        
        // Check for exact match (highest priority)
        if (name === searchTerm) {
            matchScore = 100;
            shouldShow = true;
        }
        // Check if name starts with search term (high priority)
        else if (name.startsWith(searchTerm)) {
            matchScore = 80;
            shouldShow = true;
        }
        // Check if name contains the exact search phrase
        else if (name.includes(searchTerm)) {
            matchScore = 60;
            shouldShow = true;
        }
        // Multi-word search: check if ALL words are found
        else if (searchWords.length > 1) {
            const wordsFound = searchWords.filter(word => name.includes(word)).length;
            if (wordsFound === searchWords.length) {
                // All words found - high priority
                matchScore = 70;
                shouldShow = true;
            } else if (wordsFound > 0) {
                // Some words found - lower priority
                matchScore = wordsFound * 20;
                shouldShow = true;
            }
        }
        // Single word partial match (lowest priority)
        else if (searchWords.length === 1 && name.includes(searchWords[0])) {
            matchScore = 40;
            shouldShow = true;
        }
        
        // Display row if it matches
        row.style.display = shouldShow ? '' : 'none';
        
        // Highlight rows with higher match scores
        if (shouldShow && matchScore >= 80) {
            row.style.backgroundColor = '#fff3cd'; // Light yellow for high priority
        } else if (shouldShow && matchScore >= 60) {
            row.style.backgroundColor = ''; // Normal background
        } else {
            row.style.backgroundColor = '';
        }
    });
    
    syncFamilyHeaderVisibility();
    // Update selected count after filtering
    updateSelectedCount();
});

// Hide a family header row when every child under it is currently filtered out,
// so a search doesn't leave behind an empty-looking family heading.
function syncFamilyHeaderVisibility() {
    document.querySelectorAll('.family-header-row').forEach(header => {
        const familyId = header.getAttribute('data-family');
        const children = document.querySelectorAll('.orphan-row[data-family="' + familyId + '"]');
        const anyVisible = Array.from(children).some(r => r.style.display !== 'none');
        header.style.display = anyVisible ? '' : 'none';
    });
}

// Clear search
document.getElementById('clearSearch').addEventListener('click', function() {
    document.getElementById('orphanSearch').value = '';
    const rows = document.querySelectorAll('.orphan-row');
    rows.forEach(row => {
        row.style.display = '';
        row.style.backgroundColor = '';
    });
    syncFamilyHeaderVisibility();
    updateSelectedCount();
});

// Select all checkbox in the table header (only selects visible rows)
document.getElementById('selectAll').addEventListener('change', function() {
    setVisibleCheckboxes(this.checked);
});

// Prominent select-all / deselect-all buttons above the search box (same behavior,
// easier to find than the small header checkbox — this is what makes bulk-adding an
// entire eligible list a one-click action instead of searching + checking one by one).
document.getElementById('selectAllBtn').addEventListener('click', function() {
    setVisibleCheckboxes(true);
    document.getElementById('selectAll').checked = true;
});
document.getElementById('deselectAllBtn').addEventListener('click', function() {
    setVisibleCheckboxes(false);
    document.getElementById('selectAll').checked = false;
});
function setVisibleCheckboxes(checked) {
    document.querySelectorAll('.orphan-checkbox').forEach(cb => {
        const row = cb.closest('tr');
        if (row.style.display !== 'none') { cb.checked = checked; }
    });
    updateSelectedCount();
}

// Per-family checkbox: select/deselect every visible child in that family at once —
// useful since a disbursement batch is created per family, so grabbing a whole
// family in one click is usually what's actually wanted.
document.querySelectorAll('.family-checkbox').forEach(fcb => {
    fcb.addEventListener('change', function() {
        const familyId = this.getAttribute('data-family');
        document.querySelectorAll('.orphan-checkbox[data-family="' + familyId + '"]').forEach(cb => {
            const row = cb.closest('tr');
            if (row.style.display !== 'none') { cb.checked = fcb.checked; }
        });
        updateSelectedCount();
    });
});

// Individual checkbox change
document.querySelectorAll('.orphan-checkbox').forEach(cb => {
    cb.addEventListener('change', updateSelectedCount);
});

// Update selected count, and keep each family checkbox in sync with its children
function updateSelectedCount() {
    const checked = document.querySelectorAll('.orphan-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = checked;
    document.getElementById('btnCount').textContent = checked;

    document.querySelectorAll('.family-checkbox').forEach(fcb => {
        const familyId = fcb.getAttribute('data-family');
        const children = Array.from(document.querySelectorAll('.orphan-checkbox[data-family="' + familyId + '"]'))
            .filter(cb => cb.closest('tr').style.display !== 'none');
        const allChecked = children.length > 0 && children.every(cb => cb.checked);
        fcb.checked = allChecked;
    });
}

// Resilient confirm/alert helpers: use the styled SweetAlert2 popup when the
// library is loaded on this page, and fall back to the plain browser dialog
// if it isn't — so the action always works either way instead of silently
// doing nothing if Swal failed to load.
function niceConfirm(opts) {
    if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
        Swal.fire({
            title: opts.title,
            text: opts.text,
            icon: opts.icon || 'question',
            showCancelButton: true,
            confirmButtonColor: opts.confirmColor || '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: opts.confirmText || 'نعم',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) { opts.onConfirm(); }
        });
    } else {
        if (confirm(opts.text)) { opts.onConfirm(); }
    }
}
function niceAlert(title, text, icon) {
    if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
        Swal.fire({ title: title, text: text, icon: icon || 'info', confirmButtonText: 'حسناً' });
    } else {
        alert(text);
    }
}

// Confirm removing a single orphan from the group
function confirmRemoveOrphan(childId, childName) {
    niceConfirm({
        title: 'إزالة يتيم من المجموعة',
        text: 'هل أنت متأكد من إزالة "' + childName + '" من هذه المجموعة؟',
        icon: 'warning',
        confirmColor: '#dc3545',
        confirmText: 'نعم، إزالة',
        onConfirm: function() { document.getElementById('removeOrphanForm-' + childId).submit(); }
    });
}

// Confirm deactivating the whole group
function confirmDeactivateGroup() {
    niceConfirm({
        title: 'تعطيل المجموعة',
        text: 'هل أنت متأكد من تعطيل هذه المجموعة؟ لن تظهر بعد ذلك كمجموعة نشطة.',
        icon: 'warning',
        confirmColor: '#dc3545',
        confirmText: 'نعم، تعطيل',
        onConfirm: function() { document.getElementById('deactivateGroupForm').submit(); }
    });
}

// Add selected orphans
function addSelectedOrphans() {
    const checkboxes = document.querySelectorAll('.orphan-checkbox:checked');
    if (checkboxes.length === 0) {
        niceAlert('لم يتم الاختيار', 'الرجاء اختيار يتيم واحد على الأقل', 'info');
        return;
    }
    niceConfirm({
        title: 'إضافة أيتام للمجموعة',
        text: 'هل أنت متأكد من إضافة ' + checkboxes.length + ' يتيم للمجموعة؟',
        icon: 'question',
        confirmColor: '#198754',
        confirmText: 'نعم، إضافة',
        onConfirm: function() {
            const container = document.getElementById('orphansContainer');
            container.innerHTML = '';
            checkboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'child_ids[]';
                input.value = cb.value;
                container.appendChild(input);
            });
            document.getElementById('bulkAddForm').submit();
        }
    });
}

// Initialize count
updateSelectedCount();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>