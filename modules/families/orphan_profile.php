<?php
// modules/families/orphan_profile.php - Orphan profile/edit page
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/sponsor_assignments.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'nanny'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$uid = Session::getUserId();
$returnQuery=trim((string)($_GET['return']??'')); $backUrl=APP_URL.'modules/families/index.php'; if($returnQuery!==''){$backUrl.='?'.ltrim(rawurldecode($returnQuery),'?');}
$pageTitle = 'بيانات اليتيم';
$active = 'families';

$childId = (int)($_GET['child'] ?? $_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'edit';
$child = null;
$family_id = (int)($_GET['family_id'] ?? 0);

if ($childId > 0) {
    $child = dbFetchOne("SELECT * FROM family_children WHERE id = ?", [$childId]);
    if (!$child) {
        flash('error', 'اليتيم غير موجود.');
        redirect('modules/families/index.php');
    }
    $family_id = $child['family_id'];
}

$sponsorships = [];
if ($childId > 0) {
    $sponsorships = dbFetchAll("
        SELECT s.id AS sponsorship_id, s.sponsor_id, s.monthly_amount, s.start_date,
               s.end_date, s.status, s.notes,
               sp.full_name AS sponsor_name, sp.phone AS sponsor_phone,
               sp.email AS sponsor_email, sp.sponsor_code,
               sp.first_letter_id, sp.gender
        FROM sponsorships s
        LEFT JOIN sponsors sp ON sp.id = s.sponsor_id
        WHERE s.child_id = ?
        ORDER BY CASE WHEN s.status = 'active' THEN 0 WHEN s.status = 'paused' THEN 1 ELSE 2 END, s.id DESC
    ", [$childId]);
}
$activeSponsorships = array_values(array_filter($sponsorships, static fn(array $row): bool => in_array($row['status'], ['active', 'paused'], true)));
$activeSponsorIds = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['sponsor_id'], $activeSponsorships)));
$availableSponsors = [];
if ($childId > 0 && in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) {
    $availableSponsors = dbFetchAll("SELECT id, full_name, sponsor_code, phone, gender, first_letter_id FROM sponsors WHERE status = 'active' ORDER BY full_name");
    if ($role === 'supervisor') {
        $availableSponsors = array_values(array_filter($availableSponsors, static fn(array $sponsor): bool => supervisorCanAccessSponsor($uid, $sponsor)));
    }
    $availableSponsors = array_values(array_filter($availableSponsors, static fn(array $sponsor): bool => !in_array((int)$sponsor['id'], $activeSponsorIds, true)));
}

function ak_orphan_profile_save_photo(int $childId): ?string
{
    if ($childId <= 0 || empty($_FILES['photo']['name'])) return null;
    if (($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'حدث خطأ أثناء رفع صورة اليتيم.';
    }
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) return 'صيغة الصورة يجب أن تكون JPG أو PNG.';
    if ((int)$_FILES['photo']['size'] > 10 * 1024 * 1024) return 'حجم صورة اليتيم يجب ألا يتجاوز 10 ميجابايت.';

    $dir = dirname(__DIR__, 2) . '/storage/photos';
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) return 'تعذر إنشاء مجلد صور الأيتام.';
    $file = 'child_' . $childId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $file)) return 'تعذر حفظ صورة اليتيم على الخادم.';

    dbExecute("UPDATE family_children SET photo_path = ? WHERE id = ?", ['storage/photos/' . $file, $childId]);
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf() && isset($_POST['add_sponsorship'])) {
    $errors = [];
    if ($childId <= 0 || !$child) $errors[] = 'اليتيم غير موجود.';
    if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) $errors[] = 'ليس لديك صلاحية لإضافة كفالة.';

    $newSponsorId = (int)($_POST['sponsor_id'] ?? 0);
    $newAmount = round((float)str_replace(',', '', (string)($_POST['sponsorship_amount'] ?? 0)), 2);
    $newStartDate = trim((string)($_POST['sponsorship_start_date'] ?? ''));
    $newNotes = trim((string)($_POST['sponsorship_notes'] ?? ''));

    if ($newSponsorId <= 0) $errors[] = 'اختر الكفيل.';
    if ($newAmount <= 0) $errors[] = 'أدخل مبلغ الكفالة الشهري.';
    if ($newStartDate === '') $newStartDate = date('Y-m-d');
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $newStartDate)) $errors[] = 'تاريخ بداية الكفالة غير صحيح.';

    $newSponsor = $newSponsorId > 0
        ? dbFetchOne("SELECT id, full_name, sponsor_code, phone, email, gender, first_letter_id FROM sponsors WHERE id = ? AND status = 'active'", [$newSponsorId])
        : null;
    if (!$newSponsor) $errors[] = 'الكفيل غير موجود أو غير نشط.';
    elseif ($role === 'supervisor' && !supervisorCanAccessSponsor($uid, $newSponsor)) $errors[] = 'هذا الكفيل ليس من كفلائك.';

    if (!$errors && in_array($newSponsorId, $activeSponsorIds, true)) {
        $errors[] = 'هذا الكفيل لديه كفالة نشطة أو موقوفة لهذا اليتيم بالفعل.';
    }

    if (!$errors) {
        try {
            db()->beginTransaction();
            dbExecute("INSERT INTO sponsorships (sponsor_id, child_id, monthly_amount, currency_code, start_date, status, notes, created_by) VALUES (?, ?, ?, 'SDG', ?, 'active', ?, ?)",
                [$newSponsorId, $childId, $newAmount, $newStartDate, $newNotes, $uid]);
            $newSponsorshipId = (int)dbLastInsertId();
            $newCode = 'SH-' . str_pad((string)$newSponsorshipId, 6, '0', STR_PAD_LEFT);
            dbExecute("UPDATE sponsorships SET sponsorship_code = ? WHERE id = ?", [$newCode, $newSponsorshipId]);
            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, 'CREATE', 'sponsorships', ?, NULL, ?, ?, ?)",
                    [$uid, $newSponsorshipId, json_encode(['code'=>$newCode,'sponsor_id'=>$newSponsorId,'child_id'=>$childId,'amount'=>$newAmount,'start_date'=>$newStartDate,'source'=>'orphan_profile'], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            } catch (Throwable $e) {}
            db()->commit();
            flash('success', 'تمت إضافة الكفالة بنجاح للكفيل ' . ($newSponsor['full_name'] ?? '') . ' برقم ' . $newCode . '.');
            redirect('modules/families/orphan_profile.php?child=' . $childId);
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            flash('error', 'تعذر إضافة الكفالة: ' . $e->getMessage());
        }
    } else {
        flash('error', implode(', ', $errors));
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $data = [
        'family_id' => (int)$_POST['family_id'],
        'child_name' => trim($_POST['child_name'] ?? ''),
        'gender' => $_POST['gender'] ?? 'unknown',
        'birth_date' => $_POST['birth_date'] ?? null,
        'nationality' => $_POST['nationality'] ?? 'سودانية',
        'guardian_name' => trim($_POST['guardian_name'] ?? ''),
        'guardian_relationship' => trim($_POST['guardian_relationship'] ?? ''),
        'health_status' => $_POST['health_status'] ?? 'سليم',
        'health_status_other' => trim($_POST['health_status_other'] ?? ''),
        'psychological_state' => $_POST['psychological_state'] ?? 'سليم',
        'psychological_state_other' => trim($_POST['psychological_state_other'] ?? ''),
        'education_level' => $_POST['education_level'] ?? '',
        'monthly_sponsorship_value' => round((float)($_POST['monthly_sponsorship_value'] ?? 0), 2),
        'extra_allowance' => round((float)($_POST['extra_allowance'] ?? 0), 2)
    ];

    $errors = [];
    if (empty($data['child_name'])) $errors[] = 'اسم اليتيم مطلوب';
    if (empty($data['gender'])) $errors[] = 'الجنس مطلوب';    if (empty($data['birth_date'])) $errors[] = 'تاريخ الميلاد مطلوب';
    if ($data['family_id'] <= 0) $errors[] = 'الأسرة مطلوبة';

    if (empty($errors)) {
        try {
            if ($childId > 0) {
                $sql = "UPDATE family_children SET 
                    child_name = ?, gender = ?, birth_date = ?, nationality = ?,
                    guardian_name = ?, guardian_relationship = ?,
                    health_status = ?, health_status_other = ?, 
                    psychological_state = ?, psychological_state_other = ?,
                    education_level = ?,
                    monthly_sponsorship_value = ?, extra_allowance = ?,
                    updated_at = NOW()
                    WHERE id = ?";
                
                $params = [
                    $data['child_name'], $data['gender'], $data['birth_date'], $data['nationality'],
                    $data['guardian_name'], $data['guardian_relationship'],
                    $data['health_status'], $data['health_status_other'],
                    $data['psychological_state'], $data['psychological_state_other'],
                    $data['education_level'],
                    $data['monthly_sponsorship_value'], $data['extra_allowance'],
                    $childId
                ];
                
                $result = dbExecute($sql, $params);
                
                if ($result !== false) {
                    if (isset($_POST['remove_photo']) && !empty($child['photo_path'])) {
                        dbExecute("UPDATE family_children SET photo_path = NULL WHERE id = ?", [$childId]);
                    }
                    $photoError = ak_orphan_profile_save_photo($childId);
                    if ($photoError) $errors[] = $photoError;
                    try {
                        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent) 
                                   VALUES (?, 'edit_orphan', 'family_children', ?, ?, ?, ?)",
                                   [$uid, $childId, json_encode($data, JSON_UNESCAPED_UNICODE), 
                                    $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    } catch (Throwable $e) {}
                    
                    if (!$errors) {
                        flash('success', 'تم تحديث بيانات اليتيم بنجاح!');
                        redirect('modules/families/orphan_profile.php?child=' . $childId);
                    }
                } else {
                    $errors[] = 'حدث خطأ أثناء تحديث البيانات.';
                }
                
            } else {
                $sql = "INSERT INTO family_children (
                    family_id, child_name, gender, birth_date, nationality,
                    guardian_name, guardian_relationship,
                    health_status, health_status_other, 
                    psychological_state, psychological_state_other,
                    education_level,
                    monthly_sponsorship_value, extra_allowance,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                
                $params = [
                    $data['family_id'], $data['child_name'], $data['gender'], $data['birth_date'], $data['nationality'],
                    $data['guardian_name'], $data['guardian_relationship'],
                    $data['health_status'], $data['health_status_other'],
                    $data['psychological_state'], $data['psychological_state_other'],
                    $data['education_level'],
                    $data['monthly_sponsorship_value'], $data['extra_allowance']
                ];
                
                $result = dbExecute($sql, $params);
                
                if ($result !== false) {
                    $newId = (int)dbLastInsertId();
                    $photoError = ak_orphan_profile_save_photo($newId);
                    if ($photoError) $errors[] = $photoError;
                    try {
                        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent) 
                                   VALUES (?, 'add_orphan', 'family_children', ?, ?, ?, ?)",
                                   [$uid, $newId, json_encode($data, JSON_UNESCAPED_UNICODE), 
                                    $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    } catch (Throwable $e) {}
                    
                    if (!$errors) {
                        flash('success', 'تم إضافة اليتيم بنجاح!');
                        redirect('modules/families/orphan_profile.php?child=' . $newId);
                    }
                } else {
                    $errors[] = 'حدث خطأ أثناء إضافة البيانات.';
                }
            }
        } catch (Exception $e) {
            $errors[] = 'حدث خطأ: ' . $e->getMessage();
        }
    }
    if ($errors) flash('error', implode(', ', $errors));
}

$families = [];
if ($childId == 0) {
    $families = dbFetchAll("SELECT id, family_code, mother_name FROM families ORDER BY family_code");
}

$isViewMode = ($tab === 'view');
$isEditMode = ($tab === 'edit');
$isAddMode = ($childId == 0);
if ($childId > 0 && !$isViewMode && in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) {
    $isEditMode = true;
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">
        <i class="fas fa-child me-2"></i>
        <?php if ($isViewMode): ?>
            عرض بيانات اليتيم: <?php echo e($child['child_name'] ?? ''); ?>
        <?php elseif ($isEditMode): ?>
            تعديل بيانات اليتيم: <?php echo e($child['child_name'] ?? ''); ?>
        <?php else: ?>
            إضافة يتيم جديد
        <?php endif; ?>
    </h3>
    <div>
        <?php if ($childId > 0): ?>
            <?php if ($isViewMode): ?>
                <a href="<?php echo APP_URL; ?>modules/families/orphan_form.php?child=<?php echo $childId; ?>&tab=edit" class="btn btn-primary btn-sm">
                    <i class="fas fa-pen me-1"></i>تعديل البيانات
                </a>
            <?php else: ?>
                <a href="<?php echo APP_URL; ?>modules/families/orphan_form.php?child=<?php echo $childId; ?>&tab=view" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-eye me-1"></i>عرض البيانات
                </a>
            <?php endif; ?>
            <a href="<?php echo APP_URL; ?>modules/families/orphan_profile.php?child=<?php echo $childId; ?>" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>رجوع للملف
            </a>
        <?php endif; ?>
        <a href="<?php echo e($backUrl); ?>" class="btn btn-outline-secondary btn-sm" onclick="return akGoBack(this.href);">
            <i class="fas fa-arrow-right me-1"></i>رجوع للأسر
        </a>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="card">
    <div class="card-header">
    <i class="fas fa-<?php echo $isViewMode ? 'eye' : ($isEditMode ? 'edit' : 'plus'); ?> me-1"></i>
    <?php if ($isViewMode): ?>عرض بيانات اليتيم<?php elseif ($isEditMode): ?>تحديث بيانات اليتيم<?php else: ?>إضافة يتيم جديد<?php endif; ?>    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?php echo csrf_field(); ?>
            <?php if ($isAddMode): ?>
            <div class="row mb-3"><div class="col-md-6"><label class="form-label fw-bold">الأسرة <span class="text-danger">*</span></label><select name="family_id" class="form-select" required><option value="">اختر الأسرة</option><?php foreach ($families as $f): ?><option value="<?php echo $f['id']; ?>" <?php echo ($family_id == $f['id']) ? 'selected' : ''; ?>><?php echo e($f['family_code']); ?> - <?php echo e($f['mother_name']); ?></option><?php endforeach; ?></select></div></div>
            <?php else: ?>
                <input type="hidden" name="family_id" value="<?php echo $child['family_id']; ?>">
                <div class="row mb-3"><div class="col-md-6"><label class="form-label fw-bold">الأسرة</label><p class="form-control-static"><?php $family = dbFetchOne("SELECT family_code, mother_name FROM families WHERE id = ?", [$child['family_id']]); echo e($family['family_code'] ?? ''); ?> - <?php echo e($family['mother_name'] ?? ''); ?></p></div></div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-12"><h5 class="border-bottom pb-2 text-primary">المعلومات الشخصية</h5></div>
                <div class="col-md-4"><label class="form-label fw-bold">اسم اليتيم <span class="text-danger">*</span></label><input type="text" name="child_name" class="form-control" value="<?php echo e($child['child_name'] ?? ''); ?>" <?php echo $isViewMode ? 'readonly' : ''; ?> required></div>
                <div class="col-md-4"><label class="form-label fw-bold">الجنس <span class="text-danger">*</span></label><select name="gender" class="form-select" <?php echo $isViewMode ? 'disabled' : ''; ?> required><option value="">اختر</option><option value="male" <?php echo ($child['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>ذكر</option><option value="female" <?php echo ($child['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>أنثى</option><option value="unknown" <?php echo ($child['gender'] ?? '') === 'unknown' ? 'selected' : ''; ?>>غير معروف</option></select><?php if ($isViewMode): ?><input type="hidden" name="gender" value="<?php echo e($child['gender'] ?? ''); ?>"><?php endif; ?></div>
                <div class="col-md-4"><label class="form-label fw-bold">تاريخ الميلاد <span class="text-danger">*</span></label><input type="date" name="birth_date" class="form-control" value="<?php echo e($child['birth_date'] ?? ''); ?>" <?php echo $isViewMode ? 'readonly' : ''; ?> required></div>
                <div class="col-md-4"><label class="form-label fw-bold">الجنسية</label><input type="text" name="nationality" class="form-control" value="<?php echo e($child['nationality'] ?? 'سودانية'); ?>" <?php echo $isViewMode ? 'readonly' : ''; ?>></div>

                <div class="col-12 mt-3"><h5 class="border-bottom pb-2 text-primary">صورة اليتيم</h5></div>
                <div class="col-md-4">
                    <?php if ($child && !empty($child['photo_path'])): ?>
                        <div class="mb-2"><img src="<?php echo APP_URL; ?>modules/families/orphan_form.php?photo=<?php echo (int)$childId; ?>" alt="صورة اليتيم" class="img-thumbnail" style="width:150px;height:150px;object-fit:cover;"></div>
                    <?php else: ?>
                        <div class="text-muted mb-2">لا توجد صورة محفوظة حالياً.</div>
                    <?php endif; ?>
                    <?php if (!$isViewMode): ?>
                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png">
                        <div class="form-text">JPG أو PNG فقط، بحد أقصى 10 ميجابايت.</div>
                        <?php if ($child && !empty($child['photo_path'])): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="removePhoto"><label class="form-check-label" for="removePhoto">حذف الصورة الحالية</label></div><?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="col-12 mt-3"><h5 class="border-bottom pb-2 text-primary">معلومات الوصي</h5></div>
                <div class="col-md-6"><label class="form-label fw-bold">اسم الوصي</label><input type="text" name="guardian_name" class="form-control" value="<?php echo e($child['guardian_name'] ?? ''); ?>" <?php echo $isViewMode ? 'readonly' : ''; ?>></div>
                <div class="col-md-6"><label class="form-label fw-bold">صلة القرابة</label><select name="guardian_relationship" class="form-select" <?php echo $isViewMode ? 'disabled' : ''; ?>><option value="">اختر</option><?php foreach (['أم','أب','جد','جدة','عم','خال','أخ','أخت','غير ذلك'] as $rel): ?><option value="<?php echo e($rel); ?>" <?php echo ($child['guardian_relationship'] ?? '') === $rel ? 'selected' : ''; ?>><?php echo e($rel); ?></option><?php endforeach; ?></select><?php if ($isViewMode): ?><input type="hidden" name="guardian_relationship" value="<?php echo e($child['guardian_relationship'] ?? ''); ?>"><?php endif; ?></div>

                <div class="col-12 mt-3"><h5 class="border-bottom pb-2 text-primary">الحالة الصحية والنفسية</h5></div>
                <div class="col-md-4"><label class="form-label fw-bold">الحالة الصحية</label><select name="health_status" class="form-select" onchange="document.getElementById('health_status_other_wrap').style.display = this.value === 'أخرى' ? '' : 'none';" <?php echo $isViewMode ? 'disabled' : ''; ?>><?php $healthOptions = $AK_ORPHAN_OPTS['health']; $currentHealth = $child['health_status'] ?? 'سليم'; if ($currentHealth !== '' && !in_array($currentHealth, $healthOptions, true)) $healthOptions[] = $currentHealth; foreach ($healthOptions as $o): ?><option value="<?php echo e($o); ?>" <?php echo $currentHealth === $o ? 'selected' : ''; ?>><?php echo e($o); ?></option><?php endforeach; ?></select><?php if ($isViewMode): ?><input type="hidden" name="health_status" value="<?php echo e($child['health_status'] ?? ''); ?>"><?php endif; ?></div>
                <div class="col-md-4" id="health_status_other_wrap" style="display:<?php echo ($child['health_status'] ?? '') === 'أخرى' ? '' : 'none'; ?>"><label class="form-label fw-bold">تفاصيل الحالة الصحية</label><input type="text" name="health_status_other" class="form-control" value="<?php echo e($child['health_status_other'] ?? ''); ?>" <?php echo $isViewMode ? 'readonly' : ''; ?>></div>
                <div class="col-md-4"><label class="form-label fw-bold">الحالة النفسية</label><select name="psychological_state" class="form-select" onchange="document.getElementById('psychological_state_other_wrap').style.display = this.value === 'أخرى' ? '' : 'none';" <?php echo $isViewMode ? 'disabled' : ''; ?>><?php $psychOptions = $AK_ORPHAN_OPTS['psych']; $currentPsych = $child['psychological_state'] ?? 'سليم'; if ($currentPsych !== '' && !in_array($currentPsych, $psychOptions, true)) $psychOptions[] = $currentPsych; foreach ($psychOptions as $o): ?><option value="<?php echo e($o); ?>" <?php echo $currentPsych === $o ? 'selected' : ''; ?>><?php echo e($o); ?></option><?php endforeach; ?></select><?php if ($isViewMode): ?><input type="hidden" name="psychological_state" value="<?php echo e($child['psychological_state'] ?? ''); ?>"><?php endif; ?></div>
                <div class="col-md-4" id="psychological_state_other_wrap" style="display:<?php echo ($child['psychological_state'] ?? '') === 'أخرى' ? '' : 'none'; ?>"><label class="form-label fw-bold">تفاصيل الحالة النفسية</label><input type="text" name="psychological_state_other" class="form-control" value="<?php echo e($child['psychological_state_other'] ?? ''); ?>" <?php echo $isViewMode ? 'readonly' : ''; ?>></div>

                <div class="col-12 mt-3"><h5 class="border-bottom pb-2 text-primary">المعلومات الدراسية</h5></div>
                <div class="col-md-4"><label class="form-label fw-bold">المستوى التعليمي</label><select name="education_level" class="form-select" <?php echo $isViewMode ? 'disabled' : ''; ?>><option value="">اختر</option><?php foreach (['تمهيدي','ابتدائي','متوسط','ثانوي','جامعي','غير ملتحق'] as $edu): ?><option value="<?php echo e($edu); ?>" <?php echo ($child['education_level'] ?? '') === $edu ? 'selected' : ''; ?>><?php echo e($edu); ?></option><?php endforeach; ?></select><?php if ($isViewMode): ?><input type="hidden" name="education_level" value="<?php echo e($child['education_level'] ?? ''); ?>"><?php endif; ?></div>

                <div class="col-12 mt-3"><h5 class="border-bottom pb-2 text-primary">البيانات المالية</h5></div>
                <div class="col-md-4"><label class="form-label fw-bold">قيمة الكفالة الشهرية</label><input type="number" step="0.01" name="monthly_sponsorship_value" class="form-control" value="<?php echo e($child['monthly_sponsorship_value'] ?? ''); ?>" <?php echo $isViewMode ? 'readonly' : ''; ?>></div>
                <div class="col-md-4"><label class="form-label fw-bold">مخصص إضافي</label><input type="number" step="0.01" name="extra_allowance" class="form-control" value="<?php echo e($child['extra_allowance'] ?? ''); ?>" <?php echo $isViewMode ? 'readonly' : ''; ?>></div>

                <div class="col-12 mt-3"><h5 class="border-bottom pb-2 text-success">معلومات الكفالة</h5></div>
                <div class="col-12">
                    <?php if ($sponsorships): ?>
                        <?php $activeTotal = 0.0; foreach ($sponsorships as $spRow) if ($spRow['status'] === 'active') $activeTotal += (float)$spRow['monthly_amount']; ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-2">
                                <thead class="table-light"><tr><th>الكفيل</th><th>كود الكفيل</th><th>المبلغ الشهري</th><th>تاريخ البداية</th><th>الحالة</th><th class="text-center">الإجراء</th></tr></thead>
                                <tbody>
                                <?php foreach ($sponsorships as $spRow): ?>
                                    <tr>
                                        <td><strong><?php echo e($spRow['sponsor_name'] ?? '—'); ?></strong><?php if (!empty($spRow['sponsor_phone'])): ?><div class="small text-muted"><?php echo e($spRow['sponsor_phone']); ?></div><?php endif; ?></td>
                                        <td><?php echo e($spRow['sponsor_code'] ?? '—'); ?></td>
                                        <td><?php echo number_format((float)$spRow['monthly_amount'], 2); ?> ج.س</td>
                                        <td><?php echo e($spRow['start_date'] ?? '—'); ?></td>
                                        <td><?php $spStatusLabels=['active'=>'نشطة','paused'=>'موقوفة','completed'=>'مكتملة','cancelled'=>'ملغاة']; $spStatusClasses=['active'=>'bg-success','paused'=>'bg-warning text-dark','completed'=>'bg-info text-dark','cancelled'=>'bg-danger']; ?><span class="badge <?php echo $spStatusClasses[$spRow['status']] ?? 'bg-secondary'; ?>"><?php echo e($spStatusLabels[$spRow['status']] ?? $spRow['status']); ?></span></td>
                                        <td class="text-center"><a href="<?php echo APP_URL; ?>modules/sponsorships/view.php?id=<?php echo (int)$spRow['sponsorship_id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-edit me-1"></i>تعديل</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($activeTotal > 0): ?><div class="alert alert-info py-2 mb-2"><i class="fas fa-calculator me-1"></i><strong>إجمالي الكفالة الشهرية النشطة:</strong> <?php echo number_format($activeTotal, 2); ?> ج.س</div><?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning mb-2"><i class="fas fa-exclamation-triangle me-2"></i>لا توجد كفالات مسجلة لهذا اليتيم حالياً.</div>
                    <?php endif; ?>

                    <?php if ($isEditMode && !$isViewMode && in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)): ?>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSponsorshipModal"><i class="fas fa-user-plus me-1"></i><?php echo $sponsorships ? 'إضافة كفيل آخر' : 'إضافة كفيل'; ?></button>
                    <?php endif; ?>
                </div>

                <div class="col-12 mt-4">
                    <?php if ($isEditMode): ?><button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i>حفظ التغييرات</button><a href="<?php echo APP_URL; ?>modules/families/orphan_profile.php?child=<?php echo $childId; ?>" class="btn btn-secondary btn-lg"><i class="fas fa-times me-1"></i>إلغاء</a><a href="<?php echo APP_URL; ?>modules/families/orphan_form.php?child=<?php echo $childId; ?>&tab=view" class="btn btn-outline-info btn-lg"><i class="fas fa-eye me-1"></i>عرض البيانات</a>
                    <?php elseif ($isViewMode): ?><a href="<?php echo APP_URL; ?>modules/families/orphan_form.php?child=<?php echo $childId; ?>&tab=edit" class="btn btn-primary btn-lg"><i class="fas fa-pen me-1"></i>تعديل البيانات</a><a href="<?php echo APP_URL; ?>modules/families/orphan_profile.php?child=<?php echo $childId; ?>" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left me-1"></i>رجوع للملف</a><button type="button" onclick="window.print()" class="btn btn-outline-dark btn-lg"><i class="fas fa-print me-1"></i>طباعة</button>
                    <?php else: ?><button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i>إضافة اليتيم</button><?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="addSponsorshipModal" tabindex="-1" aria-labelledby="addSponsorshipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="addSponsorshipModalLabel"><i class="fas fa-hand-holding-heart me-2 text-success"></i>إضافة كفيل لليتيم</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
            <form method="post">
                <?php echo csrf_field(); ?><input type="hidden" name="add_sponsorship" value="1">
                <div class="modal-body">
                    <div class="alert alert-light border"><strong>اليتيم:</strong> <?php echo e($child['child_name'] ?? ''); ?> <span class="text-muted">(<?php echo (int)$childId; ?>)</span></div>
                    <?php if ($availableSponsors): ?>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-bold">الكفيل <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="text" id="sponsorSearchInput" class="form-control" placeholder="ابحث باسم الكفيل أو كود الكفيل..." autocomplete="off" aria-label="البحث عن الكفيل">
                                <input type="hidden" name="sponsor_id" id="selectedSponsorId" required>
                                <div id="sponsorSearchResults" class="list-group position-absolute w-100 shadow-sm" style="z-index:1080;max-height:260px;overflow-y:auto;display:none;"></div>
                            </div>
                            <div id="selectedSponsorHint" class="form-text">ابدأ بكتابة اسم الكفيل أو كوده، ثم اختر النتيجة.</div>
                            <div id="sponsorNoResults" class="small text-danger mt-1" style="display:none;">لا توجد نتائج مطابقة.</div>
                        </div>
                        <div class="col-md-5"><label class="form-label fw-bold">المبلغ الشهري <span class="text-danger">*</span></label><div class="input-group"><input type="number" name="sponsorship_amount" class="form-control" min="0.01" step="0.01" required><span class="input-group-text">ج.س</span></div></div>
                        <div class="col-md-5"><label class="form-label fw-bold">تاريخ بداية الكفالة <span class="text-danger">*</span></label><input type="date" name="sponsorship_start_date" class="form-control" value="<?php echo e(date('Y-m-d')); ?>" required></div>
                        <div class="col-12"><label class="form-label fw-bold">ملاحظات</label><textarea name="sponsorship_notes" class="form-control" rows="2"></textarea></div>
                    </div>
                    <?php else: ?><div class="alert alert-warning mb-0"><i class="fas fa-info-circle me-2"></i>لا يوجد كفلاء نشطون متاحون للإضافة ضمن نطاق صلاحيتك لهذا اليتيم.</div><?php endif; ?>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><?php if ($availableSponsors): ?><button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>حفظ الكفالة</button><?php endif; ?></div>
            </form>
        </div>
    </div>
</div>
<script>
(function() {
    'use strict';

    const sponsorInput = document.getElementById('sponsorSearchInput');
    const sponsorIdInput = document.getElementById('selectedSponsorId');
    const sponsorResults = document.getElementById('sponsorSearchResults');
    const sponsorHint = document.getElementById('selectedSponsorHint');
    const sponsorNoResults = document.getElementById('sponsorNoResults');

    let sponsorSearchRequest = 0;

    function normalizeSearch(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[\u064B-\u065F\u0670]/g, '')
            .replace(/[إأآ]/g, 'ا')
            .replace(/ى/g, 'ي')
            .trim();
    }

    function showSponsorNoResults(message) {
        if (sponsorNoResults) {
            sponsorNoResults.textContent = message || 'لا توجد نتائج مطابقة.';
            sponsorNoResults.style.display = '';
        }
        closeSponsorResults();
    }

    function renderSponsorResults(matches) {
        if (!sponsorInput || !sponsorResults) return;

        sponsorResults.innerHTML = '';

        if (!matches.length) {
            showSponsorNoResults();
            return;
        }

        if (sponsorNoResults) sponsorNoResults.style.display = 'none';

        matches.forEach(function(sponsor) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action text-end';
            item.innerHTML = '<strong>' + escapeHtml(sponsor.name) + '</strong> <span class="text-muted">(' + escapeHtml(sponsor.code) + ')</span>';

            item.addEventListener('mousedown', function(event) {
                event.preventDefault();
            });

            item.addEventListener('click', function() {
                sponsorInput.value = sponsor.name + ' (' + sponsor.code + ')';
                sponsorIdInput.value = String(sponsor.id);
                sponsorHint.textContent = 'تم اختيار الكفيل: ' + sponsor.name + ' (' + sponsor.code + ')';
                sponsorHint.className = 'form-text text-success';
                sponsorInput.classList.remove('is-invalid');
                sponsorResults.innerHTML = '';
                closeSponsorResults();
            });

            sponsorResults.appendChild(item);
        });

        sponsorResults.style.display = 'block';
    }

    async function searchSponsors() {
        if (!sponsorInput || !sponsorResults) return;

        const rawQuery = sponsorInput.value.trim();
        const query = normalizeSearch(rawQuery);

        if (!query) {
            if (sponsorNoResults) sponsorNoResults.style.display = 'none';
            sponsorResults.innerHTML = '';
            closeSponsorResults();
            return;
        }

        const requestId = ++sponsorSearchRequest;

        try {
            const url = new URL('<?php echo APP_URL; ?>modules/families/sponsor_search.php');
            url.searchParams.set('q', rawQuery);
            url.searchParams.set('child_id', '<?php echo (int)$childId; ?>');

            const response = await fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error('Sponsor search request failed.');
            }

            const matches = await response.json();

            if (requestId !== sponsorSearchRequest) return;

            renderSponsorResults(Array.isArray(matches) ? matches : []);
        } catch (error) {
            if (requestId !== sponsorSearchRequest) return;
            showSponsorNoResults('تعذر تحميل نتائج البحث. حاول مرة أخرى.');
        }
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value || '');
        return div.innerHTML;
    }

    function closeSponsorResults() {
        if (sponsorResults) {            sponsorResults.style.display = 'none';
        }
    }

    if (sponsorInput) {
        sponsorInput.addEventListener('input', function(event) {
            if (event.isComposing) return;
            sponsorIdInput.value = '';
            sponsorHint.textContent = 'ابدأ بكتابة اسم الكفيل أو كوده، ثم اختر النتيجة.';
            sponsorHint.className = 'form-text';
            searchSponsors();
        });

        sponsorInput.addEventListener('compositionend', function() {
            sponsorIdInput.value = '';
            sponsorHint.textContent = 'ابدأ بكتابة اسم الكفيل أو كوده، ثم اختر النتيجة.';
            sponsorHint.className = 'form-text';
            searchSponsors();
        });

        sponsorInput.addEventListener('search', function() {
            sponsorIdInput.value = '';
            searchSponsors();
        });

        sponsorInput.addEventListener('focus', function() {
            if (normalizeSearch(sponsorInput.value)) {
                searchSponsors();
            }
        });

        document.addEventListener('click', function(event) {
            if (!event.target.closest('#sponsorSearchInput') &&
                !event.target.closest('#sponsorSearchResults')) {
                closeSponsorResults();
            }
        });
    }

    const sponsorshipForm = sponsorInput ? sponsorInput.closest('form') : null;
    if (sponsorshipForm) {
        sponsorshipForm.addEventListener('submit', function(event) {
            if (!sponsorIdInput.value) {
                event.preventDefault();
                sponsorInput.classList.add('is-invalid');
                sponsorHint.textContent = 'يجب اختيار كفيل من نتائج البحث.';
                sponsorHint.className = 'form-text text-danger';
            } else {
                sponsorInput.classList.remove('is-invalid');
            }
        });
    }

    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>