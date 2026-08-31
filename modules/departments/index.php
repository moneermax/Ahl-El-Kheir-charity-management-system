<?php
// modules/departments/index.php - Department and employee management (Admin only)
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') {
    flash('error', 'غير مصرح لك بالوصول إلى إدارة الأقسام.');
    redirect('index.php');
}

$pageTitle = 'إدارة الأقسام والموظفين';
$active = 'departments';
$errors = [];
$editDepartment = null;
$editEmployee = null;
$selectedDepartmentId = (int)($_GET['edit'] ?? $_POST['return_department_id'] ?? 0);

function departmentRedirect(int $id = 0): void {
    redirect('modules/departments/index.php' . ($id > 0 ? '?edit=' . $id : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.');
        departmentRedirect($selectedDepartmentId);
    }

    try {
        if (isset($_POST['create_department'])) {
            $nameAr = trim((string)($_POST['name_ar'] ?? ''));
            $nameEn = trim((string)($_POST['name_en'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            if ($nameAr === '') $errors[] = 'اسم القسم بالعربية مطلوب.';
            if ($nameEn === '') $errors[] = 'اسم القسم بالإنجليزية مطلوب.';
            if (mb_strlen($nameAr, 'UTF-8') > 100) $errors[] = 'اسم القسم بالعربية يجب ألا يتجاوز 100 حرف.';
            if (mb_strlen($nameEn, 'UTF-8') > 100) $errors[] = 'اسم القسم بالإنجليزية يجب ألا يتجاوز 100 حرف.';
            if (!$errors && dbFetchOne('SELECT id FROM departments WHERE name_ar = ? OR name_en = ?', [$nameAr, $nameEn])) $errors[] = 'يوجد قسم بنفس الاسم بالعربية أو الإنجليزية.';
            if (!$errors) { dbExecute('INSERT INTO departments (name_ar, name_en, description) VALUES (?, ?, ?)', [$nameAr, $nameEn, $description !== '' ? $description : null]); flash('success', 'تم إنشاء القسم بنجاح.'); departmentRedirect(); }
        }

        if (isset($_POST['update_department'])) {
            $id = (int)($_POST['department_id'] ?? 0); $nameAr = trim((string)($_POST['name_ar'] ?? '')); $nameEn = trim((string)($_POST['name_en'] ?? '')); $description = trim((string)($_POST['description'] ?? ''));
            if ($id <= 0) $errors[] = 'القسم غير صالح.';
            if ($nameAr === '') $errors[] = 'اسم القسم بالعربية مطلوب.';
            if ($nameEn === '') $errors[] = 'اسم القسم بالإنجليزية مطلوب.';
            if (!$errors && dbFetchOne('SELECT id FROM departments WHERE (name_ar = ? OR name_en = ?) AND id <> ?', [$nameAr, $nameEn, $id])) $errors[] = 'يوجد قسم آخر بنفس الاسم.';
            if (!$errors) { dbExecute('UPDATE departments SET name_ar = ?, name_en = ?, description = ? WHERE id = ?', [$nameAr, $nameEn, $description !== '' ? $description : null, $id]); flash('success', 'تم تحديث القسم بنجاح.'); departmentRedirect($id); }
            $editDepartment = ['id' => $id, 'name_ar' => $nameAr, 'name_en' => $nameEn, 'description' => $description];
            $selectedDepartmentId = $id;
        }

        if (isset($_POST['assign_employee'])) {
            $departmentId = (int)($_POST['department_id'] ?? 0); $employeeId = (int)($_POST['employee_id'] ?? 0);
            if (!$departmentId || !$employeeId) { $errors[] = 'اختر الموظف والقسم.'; } elseif (!dbFetchOne('SELECT id FROM departments WHERE id = ?', [$departmentId])) { $errors[] = 'القسم غير موجود.'; } elseif (!dbFetchOne('SELECT id FROM employees WHERE id = ?', [$employeeId])) { $errors[] = 'الموظف غير موجود.'; }
            if (!$errors) { dbExecute('UPDATE employees SET department_id = ? WHERE id = ?', [$departmentId, $employeeId]); flash('success', 'تمت إضافة الموظف إلى القسم أو نقله إليه.'); departmentRedirect($departmentId); }
        }

        if (isset($_POST['update_employee'])) {
            $employeeId = (int)($_POST['employee_id'] ?? 0); $returnDept = (int)($_POST['return_department_id'] ?? 0);
            $fullName = trim((string)($_POST['full_name'] ?? '')); $gender = in_array($_POST['gender'] ?? '', ['male', 'female'], true) ? $_POST['gender'] : null; $phone = trim((string)($_POST['phone'] ?? '')); $email = trim((string)($_POST['email'] ?? '')); $address = trim((string)($_POST['address'] ?? '')); $code = trim((string)($_POST['employee_code'] ?? '')); $hireDate = trim((string)($_POST['hire_date'] ?? '')); $position = trim((string)($_POST['position'] ?? '')); $employmentType = in_array($_POST['employment_type'] ?? '', ['full_time','part_time','contract','volunteer'], true) ? $_POST['employment_type'] : ''; $workMode = in_array($_POST['work_mode'] ?? '', ['remote','onsite','hybrid'], true) ? $_POST['work_mode'] : ''; $status = in_array($_POST['status'] ?? '', ['active','on_leave','terminated','suspended'], true) ? $_POST['status'] : ''; $salary = is_numeric($_POST['basic_salary'] ?? '') ? (float)$_POST['basic_salary'] : 0;
            if ($fullName === '') $errors[] = 'اسم الموظف مطلوب.'; if ($code === '') $errors[] = 'الرقم الوظيفي مطلوب.'; if ($hireDate === '') $errors[] = 'تاريخ التعيين مطلوب.'; if ($position === '') $errors[] = 'المسمى الوظيفي مطلوب.'; if ($employmentType === '' || $workMode === '' || $status === '') $errors[] = 'اختر بيانات العمل المطلوبة.';
            if (!$errors && dbFetchOne('SELECT id FROM employees WHERE employee_code = ? AND id <> ?', [$code, $employeeId])) $errors[] = 'الرقم الوظيفي مستخدم من موظف آخر.';
            if (!$errors) { dbExecute('UPDATE employees SET full_name=?, gender=?, phone=?, email=?, address=?, employee_code=?, hire_date=?, position=?, employment_type=?, work_mode=?, basic_salary=?, status=?, updated_at=NOW() WHERE id=?', [$fullName, $gender, $phone !== '' ? $phone : null, $email !== '' ? $email : null, $address !== '' ? $address : null, $code, $hireDate, $position, $employmentType, $workMode, $salary, $status, $employeeId]); flash('success', 'تم تحديث بيانات الموظف والمسمى الوظيفي بنجاح.'); departmentRedirect($returnDept); }
            $editEmployee = ['id'=>$employeeId,'full_name'=>$fullName,'gender'=>$gender,'phone'=>$phone,'email'=>$email,'address'=>$address,'employee_code'=>$code,'hire_date'=>$hireDate,'position'=>$position,'employment_type'=>$employmentType,'work_mode'=>$workMode,'basic_salary'=>$salary,'status'=>$status,'department_id'=>$returnDept]; $selectedDepartmentId=$returnDept;
        }

        if (isset($_POST['delete_department'])) {
            $id = (int)$_POST['delete_department']; $usage = dbFetchOne('SELECT (SELECT COUNT(*) FROM users WHERE department_id = ?) AS users_count, (SELECT COUNT(*) FROM employees WHERE department_id = ?) AS employees_count', [$id, $id]); $usersCount=(int)($usage['users_count']??0); $employeesCount=(int)($usage['employees_count']??0);
            if ($usersCount || $employeesCount) flash('error', "لا يمكن حذف القسم لأنه مستخدم من {$usersCount} مستخدم و{$employeesCount} موظف."); else { dbExecute('DELETE FROM departments WHERE id = ?', [$id]); flash('success', 'تم حذف القسم بنجاح.'); }
            departmentRedirect();
        }
    } catch (Throwable $e) { error_log('Departments module: ' . $e->getMessage()); $errors[] = 'حدث خطأ أثناء تنفيذ العملية. تأكد من صحة البيانات.'; }
}

if (!$editDepartment && $selectedDepartmentId > 0) $editDepartment = dbFetchOne('SELECT id, name_ar, name_en, description FROM departments WHERE id = ?', [$selectedDepartmentId]);
if (!$editEmployee && isset($_GET['employee'])) $editEmployee = dbFetchOne('SELECT * FROM employees WHERE id = ?', [(int)$_GET['employee']]);
$departments = dbFetchAll('SELECT d.id, d.name_ar, d.name_en, d.description, d.created_at, (SELECT COUNT(*) FROM users u WHERE u.department_id=d.id) AS users_count, (SELECT COUNT(*) FROM employees e WHERE e.department_id=d.id) AS employees_count FROM departments d ORDER BY d.id');
$departmentEmployees = $editDepartment ? dbFetchAll('SELECT * FROM employees WHERE department_id = ? ORDER BY status, full_name', [(int)$editDepartment['id']]) : [];
$allEmployees = $editDepartment ? dbFetchAll('SELECT id, full_name, employee_code, department_id FROM employees ORDER BY full_name') : [];

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="mb-1"><i class="fas fa-building me-2"></i>إدارة الأقسام والموظفين</h3><p class="text-muted mb-0">إدارة الأقسام، الموظفين، النقل الوظيفي، والترقيات.</p></div><a href="<?php echo e(APP_URL); ?>dashboard/admin_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-right me-1"></i>لوحة التحكم</a></div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error) echo '<li>'.e($error).'</li>'; ?></ul></div><?php endif; ?>
<div class="row g-4">
<div class="col-lg-4"><div class="card mb-4"><div class="card-header"><i class="fas fa-<?php echo $editDepartment ? 'pen-to-square' : 'plus'; ?> me-2"></i><?php echo $editDepartment ? 'تعديل القسم' : 'إضافة قسم جديد'; ?></div><div class="card-body"><form method="post"><?php echo csrf_field(); ?><?php if($editDepartment): ?><input type="hidden" name="department_id" value="<?php echo (int)$editDepartment['id']; ?>"><?php endif; ?><div class="mb-3"><label class="form-label fw-bold">اسم القسم بالعربية</label><input class="form-control" name="name_ar" maxlength="100" required value="<?php echo e($editDepartment['name_ar']??''); ?>"></div><div class="mb-3"><label class="form-label fw-bold">اسم القسم بالإنجليزية</label><input class="form-control" name="name_en" maxlength="100" required value="<?php echo e($editDepartment['name_en']??''); ?>"></div><div class="mb-3"><label class="form-label fw-bold">الوصف</label><textarea class="form-control" name="description" rows="3"><?php echo e($editDepartment['description']??''); ?></textarea></div><button class="btn btn-primary" name="<?php echo $editDepartment?'update_department':'create_department'; ?>" value="1"><i class="fas fa-save me-1"></i><?php echo $editDepartment?'حفظ التعديلات':'إنشاء القسم'; ?></button><?php if($editDepartment): ?> <a class="btn btn-secondary" href="<?php echo e(APP_URL); ?>modules/departments/index.php">إلغاء</a><?php endif; ?></form></div></div>
<?php if($editDepartment): ?><div class="card mb-4 border-success"><div class="card-header bg-success text-white"><i class="fas fa-user-plus me-2"></i>إضافة أو نقل موظف</div><div class="card-body"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="department_id" value="<?php echo (int)$editDepartment['id']; ?>"><select class="form-select mb-3" name="employee_id" required><option value="">اختر موظفاً...</option><?php foreach($allEmployees as $employee): ?><option value="<?php echo (int)$employee['id']; ?>"><?php echo e($employee['full_name'].' — '.$employee['employee_code'].($employee['department_id']==$editDepartment['id']?' (في هذا القسم)':'')); ?></option><?php endforeach; ?></select><button class="btn btn-success" name="assign_employee" value="1"><i class="fas fa-user-arrow-up me-1"></i>إضافة / نقل إلى هذا القسم</button></form></div></div><?php endif; ?>
<?php if($editEmployee): ?><div class="card border-warning"><div class="card-header bg-warning"><i class="fas fa-user-edit me-2"></i>تعديل بيانات الموظف</div><div class="card-body"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="employee_id" value="<?php echo (int)$editEmployee['id']; ?>"><input type="hidden" name="return_department_id" value="<?php echo (int)($editEmployee['department_id']??$selectedDepartmentId); ?>"><div class="mb-2"><label class="form-label">الاسم الكامل</label><input class="form-control" name="full_name" required value="<?php echo e($editEmployee['full_name']??''); ?>"></div><div class="row g-2"><div class="col-md-6"><label class="form-label">الجنس</label><select class="form-select" name="gender"><option value="">غير محدد</option><option value="male" <?php echo ($editEmployee['gender']??'')==='male'?'selected':''; ?>>ذكر</option><option value="female" <?php echo ($editEmployee['gender']??'')==='female'?'selected':''; ?>>أنثى</option></select></div><div class="col-md-6"><label class="form-label">الرقم الوظيفي</label><input class="form-control" name="employee_code" required value="<?php echo e($editEmployee['employee_code']??''); ?>"></div><div class="col-md-6"><label class="form-label">الهاتف</label><input class="form-control" name="phone" value="<?php echo e($editEmployee['phone']??''); ?>"></div><div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input class="form-control" name="email" type="email" value="<?php echo e($editEmployee['email']??''); ?>"></div><div class="col-md-12"><label class="form-label">العنوان</label><textarea class="form-control" name="address" rows="2"><?php echo e($editEmployee['address']??''); ?></textarea></div><div class="col-md-6"><label class="form-label">تاريخ التعيين</label><input class="form-control" name="hire_date" type="date" required value="<?php echo e($editEmployee['hire_date']??''); ?>"></div><div class="col-md-6"><label class="form-label">المسمى الوظيفي / الترقية</label><input class="form-control" name="position" required value="<?php echo e($editEmployee['position']??''); ?>"></div><div class="col-md-6"><label class="form-label">نوع العمل</label><select class="form-select" name="employment_type"><option value="full_time" <?php echo ($editEmployee['employment_type']??'')==='full_time'?'selected':''; ?>>دوام كامل</option><option value="part_time" <?php echo ($editEmployee['employment_type']??'')==='part_time'?'selected':''; ?>>دوام جزئي</option><option value="contract" <?php echo ($editEmployee['employment_type']??'')==='contract'?'selected':''; ?>>عقد</option><option value="volunteer" <?php echo ($editEmployee['employment_type']??'')==='volunteer'?'selected':''; ?>>متطوع</option></select></div><div class="col-md-6"><label class="form-label">نمط العمل</label><select class="form-select" name="work_mode"><option value="remote" <?php echo ($editEmployee['work_mode']??'')==='remote'?'selected':''; ?>>عن بُعد</option><option value="onsite" <?php echo ($editEmployee['work_mode']??'')==='onsite'?'selected':''; ?>>حضوري</option><option value="hybrid" <?php echo ($editEmployee['work_mode']??'')==='hybrid'?'selected':''; ?>>مختلط</option></select></div><div class="col-md-6"><label class="form-label">الراتب الأساسي</label><input class="form-control" name="basic_salary" type="number" step="0.01" min="0" value="<?php echo e((string)($editEmployee['basic_salary']??0)); ?>"></div><div class="col-md-6"><label class="form-label">الحالة</label><select class="form-select" name="status"><option value="active" <?php echo ($editEmployee['status']??'')==='active'?'selected':''; ?>>نشط</option><option value="on_leave" <?php echo ($editEmployee['status']??'')==='on_leave'?'selected':''; ?>>إجازة</option><option value="terminated" <?php echo ($editEmployee['status']??'')==='terminated'?'selected':''; ?>>منتهية خدمته</option><option value="suspended" <?php echo ($editEmployee['status']??'')==='suspended'?'selected':''; ?>>موقوف</option></select></div></div><button class="btn btn-warning mt-3" name="update_employee" value="1"><i class="fas fa-save me-1"></i>حفظ بيانات الموظف</button></form></div></div><?php endif; ?></div>
<div class="col-lg-8"><div class="card"><div class="card-header"><i class="fas fa-list me-2"></i>الأقسام الحالية (<?php echo count($departments); ?>)</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>#</th><th>القسم</th><th>English</th><th>المستخدمون</th><th>الموظفون</th><th>إجراءات</th></tr></thead><tbody><?php foreach($departments as $department): ?><tr class="<?php echo $editDepartment&&$editDepartment['id']==$department['id']?'table-primary':''; ?>"><td><?php echo (int)$department['id']; ?></td><td class="fw-bold"><?php echo e($department['name_ar']); ?></td><td><?php echo e($department['name_en']); ?></td><td><?php echo (int)$department['users_count']; ?></td><td><?php echo (int)$department['employees_count']; ?></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo (int)$department['id']; ?>"><i class="fas fa-pen"></i> إدارة</a><form method="post" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا القسم؟');"><?php echo csrf_field(); ?><button class="btn btn-sm btn-outline-danger" name="delete_department" value="<?php echo (int)$department['id']; ?>"><i class="fas fa-trash"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php if($editDepartment): ?><div class="card mt-4"><div class="card-header"><i class="fas fa-users me-2"></i>موظفو قسم: <?php echo e($editDepartment['name_ar']); ?> (<?php echo count($departmentEmployees); ?>)</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>الموظف</th><th>الرقم الوظيفي</th><th>المسمى الوظيفي</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody><?php foreach($departmentEmployees as $employee): ?><tr><td><?php echo e($employee['full_name']); ?></td><td><?php echo e($employee['employee_code']); ?></td><td><?php echo e($employee['position']); ?></td><td><?php echo e($employee['status']); ?></td><td><a class="btn btn-sm btn-outline-warning" href="?edit=<?php echo (int)$editDepartment['id']; ?>&employee=<?php echo (int)$employee['id']; ?>"><i class="fas fa-user-edit"></i> تعديل / ترقية</a></td></tr><?php endforeach; ?><?php if(!$departmentEmployees): ?><tr><td colspan="5" class="text-center text-muted py-4">لا يوجد موظفون في هذا القسم.</td></tr><?php endif; ?></tbody></table></div></div></div><?php endif; ?></div></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
