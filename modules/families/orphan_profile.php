<?php
// modules/families/orphan_form.php - Add/Edit/View Orphan Form with Sponsor Info
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
if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'nanny'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$uid = Session::getUserId();
$pageTitle = 'بيانات اليتيم';
$active = 'families';

// Get child ID - accept both 'child' and 'id' parameters
$childId = (int)($_GET['child'] ?? $_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'edit';
$child = null;
$family_id = (int)($_GET['family_id'] ?? 0);

// If we have a child ID, load the data
if ($childId > 0) {
    $child = dbFetchOne("SELECT * FROM family_children WHERE id = ?", [$childId]);
    if (!$child) {
        flash('error', 'اليتيم غير موجود.');
        redirect('modules/families/index.php');
    }
    $family_id = $child['family_id'];
}

// Get active sponsor info for this orphan
$sponsorInfo = null;
if ($childId > 0) {
    $sponsorInfo = dbFetchOne("
        SELECT 
            s.id as sponsorship_id,
            s.monthly_amount,
            s.start_date,
            s.status,
            sp.full_name as sponsor_name,
            sp.phone as sponsor_phone,
            sp.email as sponsor_email,
            sp.sponsor_code
        FROM sponsorships s
        LEFT JOIN sponsors sp ON sp.id = s.sponsor_id
        WHERE s.child_id = ? AND s.status = 'active'
        ORDER BY s.id DESC
        LIMIT 1
    ", [$childId]);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    // Get form data - ONLY columns that exist in the table
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

    // Validate
    $errors = [];
    if (empty($data['child_name'])) $errors[] = 'اسم اليتيم مطلوب';
    if (empty($data['gender'])) $errors[] = 'الجنس مطلوب';
    if (empty($data['birth_date'])) $errors[] = 'تاريخ الميلاد مطلوب';
    if ($data['family_id'] <= 0) $errors[] = 'الأسرة مطلوبة';

    if (empty($errors)) {
        try {
            if ($childId > 0) {
                // UPDATE existing orphan
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
                    // Log audit
                    try {
                        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent) 
                                   VALUES (?, 'edit_orphan', 'family_children', ?, ?, ?, ?)",
                                   [$uid, $childId, json_encode($data, JSON_UNESCAPED_UNICODE), 
                                    $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    } catch (Throwable $e) {}
                    
                    flash('success', 'تم تحديث بيانات اليتيم بنجاح!');
                    redirect('modules/families/orphan_profile.php?child=' . $childId);
                } else {
                    flash('error', 'حدث خطأ أثناء تحديث البيانات.');
                }
                
            } else {
                // INSERT new orphan
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
                    $newId = dbLastInsertId();
                    
                    // Log audit
                    try {
                        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent) 
                                   VALUES (?, 'add_orphan', 'family_children', ?, ?, ?, ?)",
                                   [$uid, $newId, json_encode($data, JSON_UNESCAPED_UNICODE), 
                                    $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    } catch (Throwable $e) {}
                    
                    flash('success', 'تم إضافة اليتيم بنجاح!');
                    redirect('modules/families/orphan_profile.php?child=' . $newId);
                } else {
                    flash('error', 'حدث خطأ أثناء إضافة البيانات.');
                }
            }
        } catch (Exception $e) {
            flash('error', 'حدث خطأ: ' . $e->getMessage());
        }
    } else {
        flash('error', implode(', ', $errors));
    }
}

// Get families for dropdown (for add mode)
$families = [];
if ($childId == 0) {
    $families = dbFetchAll("SELECT id, family_code, mother_name FROM families ORDER BY family_code");
}

// Determine mode
$isViewMode = ($tab === 'view');
$isEditMode = ($tab === 'edit');
$isAddMode = ($childId == 0);

// If user has permission to edit, default to edit mode
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
        <a href="<?php echo APP_URL; ?>modules/families/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-right me-1"></i>رجوع للأسر
        </a>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="card">
    <div class="card-header">
    <i class="fas fa-<?php echo $isViewMode ? 'eye' : ($isEditMode ? 'edit' : 'plus'); ?> me-1"></i>
    <?php if ($isViewMode): ?>
        عرض بيانات اليتيم
    <?php elseif ($isEditMode): ?>
        تحديث بيانات اليتيم
    <?php else: ?>
        إضافة يتيم جديد
    <?php endif; ?>
</div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <?php echo csrf_field(); ?>
            
            <?php if ($isAddMode): ?>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">الأسرة <span class="text-danger">*</span></label>
                    <select name="family_id" class="form-select" required>
                        <option value="">اختر الأسرة</option>
                        <?php foreach ($families as $f): ?>
                            <option value="<?php echo $f['id']; ?>" <?php echo ($family_id == $f['id']) ? 'selected' : ''; ?>>
                                <?php echo e($f['family_code']); ?> - <?php echo e($f['mother_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php else: ?>
                <input type="hidden" name="family_id" value="<?php echo $child['family_id']; ?>">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">الأسرة</label>
                        <p class="form-control-static">
                            <?php 
                            $family = dbFetchOne("SELECT family_code, mother_name FROM families WHERE id = ?", [$child['family_id']]);
                            echo e($family['family_code'] ?? ''); ?> - <?php echo e($family['mother_name'] ?? '');
                            ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <!-- Personal Information -->
                <div class="col-12">
                    <h5 class="border-bottom pb-2 text-primary">المعلومات الشخصية</h5>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">اسم اليتيم <span class="text-danger">*</span></label>
                    <input type="text" name="child_name" class="form-control" 
                           value="<?php echo e($child['child_name'] ?? ''); ?>" 
                           <?php echo $isViewMode ? 'readonly' : ''; ?> required>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">الجنس <span class="text-danger">*</span></label>
                    <select name="gender" class="form-select" <?php echo $isViewMode ? 'disabled' : ''; ?> required>
                        <option value="">اختر</option>
                        <option value="male" <?php echo ($child['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>ذكر</option>
                        <option value="female" <?php echo ($child['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>أنثى</option>
                        <option value="unknown" <?php echo ($child['gender'] ?? '') === 'unknown' ? 'selected' : ''; ?>>غير معروف</option>
                    </select>
                    <?php if ($isViewMode): ?>
                        <input type="hidden" name="gender" value="<?php echo e($child['gender'] ?? ''); ?>">
                    <?php endif; ?>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">تاريخ الميلاد <span class="text-danger">*</span></label>
                    <input type="date" name="birth_date" class="form-control" 
                           value="<?php echo e($child['birth_date'] ?? ''); ?>" 
                           <?php echo $isViewMode ? 'readonly' : ''; ?> required>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">الجنسية</label>
                    <input type="text" name="nationality" class="form-control" 
                           value="<?php echo e($child['nationality'] ?? 'سودانية'); ?>" 
                           <?php echo $isViewMode ? 'readonly' : ''; ?>>
                </div>

                <!-- Guardian Information -->
                <div class="col-12 mt-3">
                    <h5 class="border-bottom pb-2 text-primary">معلومات الوصي</h5>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">اسم الوصي</label>
                    <input type="text" name="guardian_name" class="form-control" 
                           value="<?php echo e($child['guardian_name'] ?? ''); ?>" 
                           <?php echo $isViewMode ? 'readonly' : ''; ?>>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">صلة القرابة</label>
                    <select name="guardian_relationship" class="form-select" <?php echo $isViewMode ? 'disabled' : ''; ?>>
                        <option value="">اختر</option>
                        <option value="أم" <?php echo ($child['guardian_relationship'] ?? '') === 'أم' ? 'selected' : ''; ?>>أم</option>
                        <option value="أب" <?php echo ($child['guardian_relationship'] ?? '') === 'أب' ? 'selected' : ''; ?>>أب</option>
                        <option value="جد" <?php echo ($child['guardian_relationship'] ?? '') === 'جد' ? 'selected' : ''; ?>>جد</option>
                        <option value="جدة" <?php echo ($child['guardian_relationship'] ?? '') === 'جدة' ? 'selected' : ''; ?>>جدة</option>
                        <option value="عم" <?php echo ($child['guardian_relationship'] ?? '') === 'عم' ? 'selected' : ''; ?>>عم</option>
                        <option value="خال" <?php echo ($child['guardian_relationship'] ?? '') === 'خال' ? 'selected' : ''; ?>>خال</option>
                        <option value="أخ" <?php echo ($child['guardian_relationship'] ?? '') === 'أخ' ? 'selected' : ''; ?>>أخ</option>
                        <option value="أخت" <?php echo ($child['guardian_relationship'] ?? '') === 'أخت' ? 'selected' : ''; ?>>أخت</option>
                        <option value="غير ذلك" <?php echo ($child['guardian_relationship'] ?? '') === 'غير ذلك' ? 'selected' : ''; ?>>غير ذلك</option>
                    </select>
                    <?php if ($isViewMode): ?>
                        <input type="hidden" name="guardian_relationship" value="<?php echo e($child['guardian_relationship'] ?? ''); ?>">
                    <?php endif; ?>
                </div>

                <!-- Health & Psychological -->
                <div class="col-12 mt-3">
                    <h5 class="border-bottom pb-2 text-primary">الحالة الصحية والنفسية</h5>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">الحالة الصحية</label>
                    <select name="health_status" class="form-select" <?php echo $isViewMode ? 'disabled' : ''; ?>>
                        <option value="سليم" <?php echo ($child['health_status'] ?? '') === 'سليم' ? 'selected' : ''; ?>>سليم</option>
                        <option value="يعاني من مرض" <?php echo ($child['health_status'] ?? '') === 'يعاني من مرض' ? 'selected' : ''; ?>>يعاني من مرض</option>
                        <option value="آخر" <?php echo ($child['health_status'] ?? '') === 'آخر' ? 'selected' : ''; ?>>آخر</option>
                    </select>
                    <?php if ($isViewMode): ?>
                        <input type="hidden" name="health_status" value="<?php echo e($child['health_status'] ?? ''); ?>">
                    <?php endif; ?>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">تفاصيل الحالة الصحية</label>
                    <input type="text" name="health_status_other" class="form-control" 
                           value="<?php echo e($child['health_status_other'] ?? ''); ?>" 
                           <?php echo $isViewMode ? 'readonly' : ''; ?>>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">الحالة النفسية</label>
                    <select name="psychological_state" class="form-select" <?php echo $isViewMode ? 'disabled' : ''; ?>>
                        <option value="سليم" <?php echo ($child['psychological_state'] ?? '') === 'سليم' ? 'selected' : ''; ?>>سليم</option>
                        <option value="يعاني من مشكلة" <?php echo ($child['psychological_state'] ?? '') === 'يعاني من مشكلة' ? 'selected' : ''; ?>>يعاني من مشكلة</option>
                        <option value="آخر" <?php echo ($child['psychological_state'] ?? '') === 'آخر' ? 'selected' : ''; ?>>آخر</option>
                    </select>
                    <?php if ($isViewMode): ?>
                        <input type="hidden" name="psychological_state" value="<?php echo e($child['psychological_state'] ?? ''); ?>">
                    <?php endif; ?>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">تفاصيل الحالة النفسية</label>
                    <input type="text" name="psychological_state_other" class="form-control" 
                           value="<?php echo e($child['psychological_state_other'] ?? ''); ?>" 
                           <?php echo $isViewMode ? 'readonly' : ''; ?>>
                </div>

                <!-- Education -->
                <div class="col-12 mt-3">
                    <h5 class="border-bottom pb-2 text-primary">المعلومات الدراسية</h5>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">المستوى التعليمي</label>
                    <select name="education_level" class="form-select" <?php echo $isViewMode ? 'disabled' : ''; ?>>
                        <option value="">اختر</option>
                        <option value="تمهيدي" <?php echo ($child['education_level'] ?? '') === 'تمهيدي' ? 'selected' : ''; ?>>تمهيدي</option>
                        <option value="ابتدائي" <?php echo ($child['education_level'] ?? '') === 'ابتدائي' ? 'selected' : ''; ?>>ابتدائي</option>
                        <option value="متوسط" <?php echo ($child['education_level'] ?? '') === 'متوسط' ? 'selected' : ''; ?>>متوسط</option>
                        <option value="ثانوي" <?php echo ($child['education_level'] ?? '') === 'ثانوي' ? 'selected' : ''; ?>>ثانوي</option>
                        <option value="جامعي" <?php echo ($child['education_level'] ?? '') === 'جامعي' ? 'selected' : ''; ?>>جامعي</option>
                        <option value="غير ملتحق" <?php echo ($child['education_level'] ?? '') === 'غير ملتحق' ? 'selected' : ''; ?>>غير ملتحق</option>
                    </select>
                    <?php if ($isViewMode): ?>
                        <input type="hidden" name="education_level" value="<?php echo e($child['education_level'] ?? ''); ?>">
                    <?php endif; ?>
                </div>

                <!-- Financial -->
                <div class="col-12 mt-3">
                    <h5 class="border-bottom pb-2 text-primary">البيانات المالية</h5>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">قيمة الكفالة الشهرية</label>
                    <input type="number" step="0.01" name="monthly_sponsorship_value" class="form-control" 
                           value="<?php echo e($child['monthly_sponsorship_value'] ?? ''); ?>" 
                           <?php echo $isViewMode ? 'readonly' : ''; ?>>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">مخصص إضافي</label>
                    <input type="number" step="0.01" name="extra_allowance" class="form-control" 
                           value="<?php echo e($child['extra_allowance'] ?? ''); ?>" 
                           <?php echo $isViewMode ? 'readonly' : ''; ?>>
                </div>

                <!-- Sponsor Information - NEW SECTION -->
                <div class="col-12 mt-3">
                    <h5 class="border-bottom pb-2 text-success">معلومات الكفيل</h5>
                </div>

                <?php if ($sponsorInfo): ?>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">اسم الكفيل</label>
                        <input type="text" class="form-control" 
                               value="<?php echo e($sponsorInfo['sponsor_name'] ?? '—'); ?>" 
                               <?php echo $isViewMode ? 'readonly' : 'readonly'; ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">رقم هاتف الكفيل</label>
                        <input type="text" class="form-control" 
                               value="<?php echo e($sponsorInfo['sponsor_phone'] ?? '—'); ?>" 
                               <?php echo $isViewMode ? 'readonly' : 'readonly'; ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">البريد الإلكتروني</label>
                        <input type="text" class="form-control" 
                               value="<?php echo e($sponsorInfo['sponsor_email'] ?? '—'); ?>" 
                               <?php echo $isViewMode ? 'readonly' : 'readonly'; ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">كود الكفيل</label>
                        <input type="text" class="form-control" 
                               value="<?php echo e($sponsorInfo['sponsor_code'] ?? '—'); ?>" 
                               <?php echo $isViewMode ? 'readonly' : 'readonly'; ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">المبلغ الشهري</label>
                        <input type="text" class="form-control" 
                               value="<?php echo number_format((float)($sponsorInfo['monthly_amount'] ?? 0), 2); ?>" 
                               <?php echo $isViewMode ? 'readonly' : 'readonly'; ?>>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">تاريخ بداية الكفالة</label>
                        <input type="text" class="form-control" 
                               value="<?php echo e($sponsorInfo['start_date'] ?? '—'); ?>" 
                               <?php echo $isViewMode ? 'readonly' : 'readonly'; ?>>
                    </div>

                    <div class="col-md-12 mt-2">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <?php if ($sponsorInfo['status'] === 'active'): ?>
                                <span class="badge bg-success">كفالة نشطة</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">كفالة غير نشطة</span>
                            <?php endif; ?>
                            <span class="ms-2">رقم الكفالة: <strong>#<?php echo e($sponsorInfo['sponsorship_id']); ?></strong></span>
                            <?php if ($isEditMode && !$isViewMode): ?>
                                <a href="<?php echo APP_URL; ?>modules/sponsorships/index.php?sponsorship=<?php echo $sponsorInfo['sponsorship_id']; ?>" 
                                   class="btn btn-sm btn-outline-primary ms-2" target="_blank">
                                    <i class="fas fa-edit me-1"></i>تعديل الكفالة
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            لا توجد كفالة نشطة لهذا اليتيم.
                            <?php if ($isEditMode && !$isViewMode): ?>
                                <a href="<?php echo APP_URL; ?>modules/sponsorships/index.php?child=<?php echo $childId; ?>" 
                                   class="btn btn-sm btn-primary ms-2">
                                    <i class="fas fa-plus me-1"></i>إضافة كفالة
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Submit -->
                <div class="col-12 mt-4">
                    <?php if ($isEditMode): ?>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-1"></i>حفظ التغييرات
                        </button>
                        <a href="<?php echo APP_URL; ?>modules/families/orphan_profile.php?child=<?php echo $childId; ?>" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times me-1"></i>إلغاء
                        </a>
                        <a href="<?php echo APP_URL; ?>modules/families/orphan_form.php?child=<?php echo $childId; ?>&tab=view" class="btn btn-outline-info btn-lg">
                            <i class="fas fa-eye me-1"></i>عرض البيانات
                        </a>
                    <?php elseif ($isViewMode): ?>
                        <a href="<?php echo APP_URL; ?>modules/families/orphan_form.php?child=<?php echo $childId; ?>&tab=edit" class="btn btn-primary btn-lg">
                            <i class="fas fa-pen me-1"></i>تعديل البيانات
                        </a>
                        <a href="<?php echo APP_URL; ?>modules/families/orphan_profile.php?child=<?php echo $childId; ?>" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left me-1"></i>رجوع للملف
                        </a>
                        <button type="button" onclick="window.print()" class="btn btn-outline-dark btn-lg">
                            <i class="fas fa-print me-1"></i>طباعة
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-1"></i>إضافة اليتيم
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>