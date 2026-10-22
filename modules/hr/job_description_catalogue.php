<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/session.php';

Session::start();
$role = Session::getUserRole();
if (!Session::isLoggedIn() || !in_array($role, ['hr_manager', 'hr_staff', 'admin'], true)) {
    http_response_code(403);
    exit('ليس لديك صلاحية إدارة الأوصاف الوظيفية.');
}
$canApprove = in_array($role, ['hr_manager', 'admin'], true);
$pdo = db();
$jobDescriptionCatalogueAvailable = (bool) dbFetchOne(
    "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1",
    ['hr_job_description_templates']
);
if (!$jobDescriptionCatalogueAvailable) {
    http_response_code(503);
    $pageTitle = 'فهرس الأوصاف الوظيفية';
    $active = 'hr';
    require_once __DIR__ . '/../../includes/header.php';
    echo '<div class="container py-4"><div class="alert alert-warning" role="status">'
        . 'فهرس الأوصاف الوظيفية غير مهيأ في قاعدة البيانات الحالية. لم يتم تغيير أي بيانات. '
        . 'يجب تطبيق ترحيلات فهرس الأوصاف الوظيفية بالترتيب قبل استخدام هذه الصفحة. '
        . '<a class="alert-link" href="' . e(APP_URL) . 'modules/hr/contract_builder.php">العودة إلى بوابة عقود العمل</a>'
        . '</div></div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
$message = '';
$error = '';
$editingId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

function akJobDescriptionItems(string $value): array
{
    $items = preg_split('/\\R/u', trim($value)) ?: [];
    return array_values(array_filter(array_map(static function ($item) {
        return trim((string)preg_replace('/^\\s*(?:[•*\\-–—]|\\d+[.)])\\s*/u', '', (string)$item));
    }, $items), static fn($item) => $item !== ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf()) {
            http_response_code(403);
            throw new RuntimeException('انتهت صلاحية النموذج. أعد تحميل الصفحة ثم حاول مرة أخرى.');
        }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save') {
            $title = trim((string)($_POST['position_title'] ?? ''));
            $systemRoleCode = trim((string)($_POST['system_role_code'] ?? ''));
            if ($systemRoleCode !== '' && !dbFetchOne('SELECT code FROM roles WHERE code = ? LIMIT 1', [$systemRoleCode])) {
                throw new InvalidArgumentException('دور النظام المحدد غير موجود.');
            }
            $readItems = static function (string $field, int $maximum, string $label): string {
                $input = $_POST[$field] ?? [];
                if (!is_array($input)) throw new InvalidArgumentException('قائمة ' . $label . ' غير صالحة.');
                $items = [];
                foreach ($input as $item) {
                    $item = trim((string)$item);
                    if ($item === '') continue;
                    if (mb_strlen($item, 'UTF-8') > 1000) throw new InvalidArgumentException('يجب ألا يتجاوز كل بند من ' . $label . ' 1000 حرف.');
                    $items[] = $item;
                }
                if (count($items) > $maximum) throw new InvalidArgumentException('يمكن إدخال ' . $maximum . ' بنداً كحد أقصى في ' . $label . '.');
                return implode("\n", $items);
            };
            $purpose = $readItems('role_purpose', 10, 'الغرض من الوظيفة');
            $responsibilities = $readItems('responsibilities', 30, 'المهام والمسؤوليات');
            $qualifications = $readItems('qualifications', 30, 'المؤهلات والخبرات');
            $reportingTo = trim((string)($_POST['reporting_to'] ?? ''));
            $roleTerms = $readItems('role_specific_terms', 20, 'البنود الإضافية');
            if ($reportingTo !== '' && !dbFetchOne("SELECT position FROM employees WHERE TRIM(position) = ? LIMIT 1", [$reportingTo])) {
                throw new InvalidArgumentException('جهة الإشراف المحددة غير موجودة ضمن المسميات الحالية.');
            }
            foreach ([
                'المسمى الوظيفي' => [$title, 180],
                'الغرض الوظيفي' => [$purpose, 5000],
                'المسؤوليات' => [$responsibilities, 12000],
                'المؤهلات' => [$qualifications, 5000],
                'جهة الإشراف' => [$reportingTo, 180],
                'بنود العقد الخاصة بالوظيفة' => [$roleTerms, 5000],
            ] as $label => [$value, $limit]) {
                if (mb_strlen($value, 'UTF-8') > $limit) {
                    throw new InvalidArgumentException('تجاوز أحد الحقول الحد المسموح به: ' . $label . '.');
                }
            }
            if ($title === '') {
                throw new InvalidArgumentException('المسمى الوظيفي مطلوب.');
            }
            $status = ($canApprove && ($_POST['description_status'] ?? '') === 'approved')
                ? 'approved'
                : 'needs_review';
            if ($status === 'approved' && $responsibilities === '') {
                throw new InvalidArgumentException('لا يمكن اعتماد وصف وظيفي قبل إدخال المهام والمسؤوليات الأساسية.');
            }
            $active = isset($_POST['is_active']) ? 1 : 0;

            if ($editingId > 0) {
                $before = dbFetchOne('SELECT * FROM hr_job_description_templates WHERE id = ? LIMIT 1', [$editingId]);
                if (!$before) {
                    throw new RuntimeException('الوصف الوظيفي المطلوب غير موجود.');
                }
                if ($title !== (string)$before['position_title']) {
                    $linkedEmployees = (int)(dbFetchOne(
                        'SELECT COUNT(*) AS total FROM employees WHERE TRIM(position) = ?',
                        [(string)$before['position_title']]
                    )['total'] ?? 0);
                    if ($linkedEmployees > 0) {
                        throw new InvalidArgumentException('لا يمكن تغيير المسمى هنا لأنه مرتبط بموظفين. حدّث المسمى في سجلات الموظفين أولاً ثم أنشئ/حدّث وصف المسمى الجديد.');
                    }
                }
                $stmt = $pdo->prepare(
                    'UPDATE hr_job_description_templates
                     SET position_title = ?, system_role_code = ?, role_purpose = ?, responsibilities = ?, qualifications = ?,
                         reporting_to = ?, role_specific_terms = ?, description_status = ?, is_active = ?, updated_by = ?
                     WHERE id = ?'
                );
                $stmt->execute([$title, $systemRoleCode, $purpose ?: null, $responsibilities ?: null, $qualifications ?: null,
                    $reportingTo ?: null, $roleTerms ?: null, $status, $active, Session::getUserID(), $editingId]);
                $actionLabel = 'UPDATE';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO hr_job_description_templates
                     (position_title, system_role_code, role_purpose, responsibilities, qualifications, reporting_to, role_specific_terms,
                      description_status, is_active, created_by, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$title, $systemRoleCode, $purpose ?: null, $responsibilities ?: null, $qualifications ?: null,
                    $reportingTo ?: null, $roleTerms ?: null, $status, $active, Session::getUserID(), Session::getUserID()]);
                $editingId = (int)$pdo->lastInsertId();
                $actionLabel = 'CREATE';
                $before = null;
            }
            try {
                dbExecute(
                    "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                     VALUES (?, ?, 'hr_job_description_template', ?, ?, ?, ?, ?)",
                    [
                        Session::getUserID(), $actionLabel, $editingId,
                        $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                        json_encode([
                            'position_title' => $title, 'system_role_code' => $systemRoleCode, 'role_purpose' => $purpose,
                            'responsibilities' => $responsibilities, 'qualifications' => $qualifications,
                            'reporting_to' => $reportingTo, 'role_specific_terms' => $roleTerms, 'description_status' => $status, 'is_active' => $active
                        ], JSON_UNESCAPED_UNICODE),
                        $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]
                );
            } catch (Throwable $auditError) {
                // Catalogue changes remain usable if legacy audit logging is temporarily unavailable.
            }
            header('Location: ' . APP_URL . 'modules/hr/job_description_catalogue.php?id=' . $editingId . '&saved=1');
            exit;
        } elseif ($action === 'new') {
            header('Location: ' . APP_URL . 'modules/hr/job_description_catalogue.php');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e instanceof PDOException && (string)$e->getCode() === '23000'
            ? 'هذا المسمى الوظيفي موجود بالفعل في الفهرس.'
            : $e->getMessage();
    }
}
if (isset($_GET['saved'])) {
    $message = 'تم حفظ الوصف الوظيفي. راجع حالة الاعتماد قبل استخدامه في عقد رسمي.';
}
$editing = $editingId > 0
    ? dbFetchOne('SELECT * FROM hr_job_description_templates WHERE id = ? LIMIT 1', [$editingId])
    : null;
$editingRoleSpecificCount = 0;
$editingGeneralIsEmpty = false;
if ($editing && trim((string)($editing['system_role_code'] ?? '')) === '') {
    $editingRoleSpecificCount = (int)(dbFetchOne(
        "SELECT COUNT(*) AS total FROM hr_job_description_templates
         WHERE position_title = ? AND TRIM(COALESCE(system_role_code, '')) <> '' AND is_active = 1",
        [(string)$editing['position_title']]
    )['total'] ?? 0);
    $editingGeneralIsEmpty =
        trim((string)($editing['role_purpose'] ?? '')) === ''
        && trim((string)($editing['responsibilities'] ?? '')) === '';
}
$templates = dbFetchAll(
    "SELECT j.*, r.name_ar AS system_role_name,
        (SELECT COUNT(*) FROM employees e
         LEFT JOIN users u ON u.id = e.user_id
         LEFT JOIN roles er ON er.id = u.role_id
         WHERE TRIM(e.position) = j.position_title
           AND (
             (j.system_role_code <> '' AND er.code = j.system_role_code)
             OR (TRIM(COALESCE(j.system_role_code, '')) = '' AND NOT EXISTS (
                 SELECT 1 FROM hr_job_description_templates jr
                 WHERE jr.position_title = j.position_title
                   AND jr.system_role_code = er.code
                   AND jr.system_role_code <> ''
                   AND jr.is_active = 1
             ))
           )) AS employee_count,
        (SELECT COUNT(*) FROM hr_job_description_templates jr
         WHERE jr.position_title = j.position_title
           AND TRIM(COALESCE(jr.system_role_code, '')) <> ''
           AND jr.is_active = 1) AS role_specific_template_count
     FROM hr_job_description_templates j
     LEFT JOIN roles r ON r.code = j.system_role_code
     ORDER BY j.is_active DESC, j.position_title, j.system_role_code",
    []
);
$systemRoles = dbFetchAll("SELECT code, name_ar, name_en FROM roles ORDER BY name_ar", []);
$reportingPositions = dbFetchAll(
    "SELECT DISTINCT TRIM(position) AS position_title FROM employees
     WHERE position IS NOT NULL AND TRIM(position) <> '' ORDER BY position_title",
    []
);
$pageTitle = 'فهرس الأوصاف الوظيفية';
$active = 'hr';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.jd-wrap{max-width:1400px;margin:auto}.jd-toast{position:fixed;top:1rem;left:1rem;z-index:1090;display:flex;align-items:center;gap:.7rem;max-width:min(92vw,460px);padding:13px 16px;background:#198754;color:#fff;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.18);font-size:.9rem;font-weight:700;direction:rtl;animation:jd-toast-in .2s ease-out}.jd-toast>i{font-size:1.15rem}.jd-toast-close{border:0;background:transparent;color:#fff;font-size:1.35rem;line-height:1;padding:0 .1rem;cursor:pointer;margin-inline-start:auto}.jd-toast.is-hiding{opacity:0;transform:translateY(-8px);transition:opacity .2s ease,transform .2s ease}@keyframes jd-toast-in{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}@media(prefers-reduced-motion:reduce){.jd-toast{animation:none}.jd-toast.is-hiding{transition:none}}.jd-hero{background:linear-gradient(120deg,#173f73,#2d67ad);color:#fff;padding:20px 24px;border-radius:14px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:14px}.jd-hero h1{font-size:1.35rem;font-weight:800;margin:0}.jd-hero p{margin:5px 0 0;font-size:.82rem;opacity:.9}.jd-card{background:#fff;border:1px solid #e4e9f0;border-radius:12px;overflow:hidden;margin-bottom:16px;box-shadow:0 3px 12px rgba(16,24,40,.04)}.jd-card-head{padding:13px 17px;border-bottom:1px solid #e7ecf2;font-weight:800;color:#173f73}.jd-card-body{padding:17px}.jd-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.jd-field label{display:block;font-size:.78rem;font-weight:800;margin-bottom:5px}.jd-field input,.jd-field select,.jd-field textarea{width:100%;border:1px solid #b9c7d8;border-radius:8px;padding:9px 10px;font:inherit;font-size:.82rem;background:#fff}.jd-field textarea{min-height:110px}.jd-responsibility-row input{min-width:0}.jd-responsibility-row>span{min-width:24px;text-align:center}.jd-full{grid-column:1/-1}.jd-table{font-size:.8rem}.jd-table td,.jd-table th{vertical-align:middle}.jd-status{white-space:nowrap}@media(max-width:720px){.jd-grid{grid-template-columns:1fr}.jd-full{grid-column:auto}.jd-hero{align-items:flex-start;flex-direction:column}}
.jd-drag-handle{cursor:grab;touch-action:none;flex:0 0 auto}.jd-drag-handle:active{cursor:grabbing}.jd-item-row.is-dragging{opacity:.45}
</style>
<div class="jd-wrap">
 <div class="jd-hero"><div><h1><i class="fa-solid fa-list-check me-2"></i>فهرس الأوصاف الوظيفية</h1><p>إدارة أوصاف الوظائف بصورة مستقلة عن تصميم عقد العمل؛ لا تُعامل الأوصاف غير المعتمدة على أنها أوصاف رسمية.</p></div><a class="btn btn-light btn-sm fw-bold" href="<?= e(APP_URL) ?>modules/hr/contract_builder.php"><i class="fa-solid fa-file-signature me-1"></i> بوابة عقود العمل</a></div>
 <?php if ($message): ?><div id="jdSuccessToast" class="jd-toast" role="status" aria-live="polite"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><?= e($message) ?></span><button type="button" class="jd-toast-close" aria-label="إغلاق التنبيه">&times;</button></div><?php endif; ?>
 <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
 <div class="alert alert-info small">تم إنشاء سجل لكل مسمى وظيفي موجود في بيانات الموظفين عند تطبيق الترحيل، دون اختلاق مهام أو مؤهلات. أكمل الحقول ثم اعتمد الوصف من مدير الموارد البشرية أو مدير النظام. تغيير أي وصف معتمد يعيده إلى «يحتاج مراجعة» ما لم يُعتمد صراحةً في عملية الحفظ نفسها.</div>
 <div class="jd-card"><div class="jd-card-head"><?= $editing ? 'تعديل وصف وظيفي' : 'إضافة مسمى ووصف وظيفي' ?></div><div class="jd-card-body">
  <?php if ($editing && trim((string)($editing['system_role_code'] ?? '')) === ''): ?>
   <div class="alert <?= $editingGeneralIsEmpty && $editingRoleSpecificCount > 0 ? 'alert-warning' : 'alert-info' ?> small" role="status">
    <strong>هذا سجل عام للمسمى الوظيفي، وليس وصفاً مرتبطاً بدور حساب محدد.</strong>
    <?php if ($editingRoleSpecificCount > 0): ?>
     <?php if ($editingGeneralIsEmpty): ?>
      حقلا الغرض والمسؤوليات فارغان في هذا السجل العام، وتوجد <?= $editingRoleSpecificCount ?> سجلات نشطة مرتبطة بأدوار النظام لهذا المسمى. عند إعداد العقد، يُفضَّل الوصف المطابق لدور حساب الموظف أولاً؛ لذلك لا يلزم نسخ محتوى تلك السجلات إلى هذا السجل. استخدم قائمة الفهرس لفتح الوصف المرتبط بالدور عند الحاجة.
     <?php else: ?>
      توجد <?= $editingRoleSpecificCount ?> سجلات نشطة مرتبطة بأدوار النظام لهذا المسمى. يظل هذا السجل خياراً احتياطياً للموظفين الذين لا يتوفر لهم وصف مطابق لدور حسابهم.
     <?php endif; ?>
    <?php else: ?>
     هذا السجل يُستخدم كخيار احتياطي عندما لا يتوفر وصف مطابق لدور حساب الموظف.
    <?php endif; ?>
   </div>
  <?php endif; ?>
  <form method="post" action="<?= e(APP_URL) ?>modules/hr/job_description_catalogue.php">
   <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
   <div class="jd-grid">
    <div class="jd-field"><label for="position_title">المسمى الوظيفي *</label><input id="position_title" name="position_title" maxlength="180" required value="<?= e($editing['position_title'] ?? '') ?>"></div>
    <div class="jd-field"><label for="system_role_code">دور الحساب داخل النظام (اختياري)</label><select id="system_role_code" name="system_role_code"><option value="">وصف عام للمسمى (لا يحدد دور نظام بعينه)</option><?php foreach ($systemRoles as $systemRole): ?><option value="<?= e($systemRole['code']) ?>" <?= ($editing['system_role_code'] ?? '') === $systemRole['code'] ? 'selected' : '' ?>><?= e($systemRole['name_ar'] ?: $systemRole['name_en'] ?: $systemRole['code']) ?> (<?= e($systemRole['code']) ?>)</option><?php endforeach; ?></select><small>استخدم هذا الخيار عندما تختلف مهام الموظفين الذين يحملون المسمى نفسه بحسب دور حساباتهم. هذا الدليل لا يغيّر صلاحيات الحساب.</small></div>
    <div class="jd-field"><label for="reporting_to">جهة الإشراف المباشر (إن كانت محددة)</label><select id="reporting_to" name="reporting_to"><option value="">— غير محددة —</option><?php foreach ($reportingPositions as $reportingPosition): ?><option value="<?= e($reportingPosition['position_title']) ?>" <?= (string)($editing['reporting_to'] ?? '') === (string)$reportingPosition['position_title'] ? 'selected' : '' ?>><?= e($reportingPosition['position_title']) ?></option><?php endforeach; ?></select></div>
    <?php
    $editingPurpose = akJobDescriptionItems((string)($editing['role_purpose'] ?? '')) ?: [''];
    $editingResponsibilities = akJobDescriptionItems((string)($editing['responsibilities'] ?? '')) ?: [''];
    $editingQualifications = akJobDescriptionItems((string)($editing['qualifications'] ?? '')) ?: [''];
    $editingTerms = akJobDescriptionItems((string)($editing['role_specific_terms'] ?? '')) ?: [''];
    ?>
    <div class="jd-field jd-full"><label>الغرض من الوظيفة</label><small class="d-block mb-2">اكتب هدفاً أو نتيجة أساسية واحدة في كل حقل؛ هذا يوضح لماذا توجد الوظيفة، وليس قائمة مهامها.</small><div id="purposeFields" class="jd-item-fields d-flex flex-column gap-2"><?php foreach ($editingPurpose as $index => $item): ?><div class="jd-item-row d-flex gap-2 align-items-start"><span class="pt-2 text-muted jd-item-number"><?= (int)($index + 1) ?>.</span><button type="button" class="btn btn-light btn-sm jd-drag-handle" aria-label="اسحب لإعادة ترتيب البند" title="اسحب لإعادة ترتيب البند"><i class="fa-solid fa-grip-vertical"></i></button><input type="text" name="role_purpose[]" maxlength="1000" class="form-control" value="<?= e($item) ?>" placeholder="الغرض أو الهدف من الوظيفة"><button type="button" class="btn btn-outline-danger jd-remove-item" aria-label="حذف البند"><i class="fa-solid fa-trash"></i></button></div><?php endforeach; ?></div><button type="button" class="btn btn-sm btn-outline-primary mt-2 jd-add-item" data-target="purposeFields" data-name="role_purpose[]" data-limit="10" data-placeholder="الغرض أو الهدف من الوظيفة"><i class="fa-solid fa-plus me-1"></i> إضافة بند للغرض</button></div>
    <div class="jd-field jd-full"><label>المهام والمسؤوليات الأساسية</label><small class="d-block mb-2">أدخل كل مهمة في حقل مستقل. ستظهر في النسخة النهائية كقائمة عربية مرتبة ومسافة بادئة لكل بند.</small><div id="responsibilityFields" class="jd-item-fields d-flex flex-column gap-2"><?php foreach ($editingResponsibilities as $index => $item): ?><div class="jd-item-row d-flex gap-2 align-items-start"><span class="pt-2 text-muted jd-item-number"><?= (int)($index + 1) ?>.</span><button type="button" class="btn btn-light btn-sm jd-drag-handle" aria-label="اسحب لإعادة ترتيب البند" title="اسحب لإعادة ترتيب البند"><i class="fa-solid fa-grip-vertical"></i></button><input type="text" name="responsibilities[]" maxlength="1000" class="form-control" value="<?= e($item) ?>" placeholder="مهمة أو مسؤولية واحدة"><button type="button" class="btn btn-outline-danger jd-remove-item" aria-label="حذف البند"><i class="fa-solid fa-trash"></i></button></div><?php endforeach; ?></div><button type="button" class="btn btn-sm btn-outline-primary mt-2 jd-add-item" data-target="responsibilityFields" data-name="responsibilities[]" data-limit="30" data-placeholder="مهمة أو مسؤولية واحدة"><i class="fa-solid fa-plus me-1"></i> إضافة مسؤولية</button></div>
    <div class="jd-field jd-full"><label>المؤهلات والخبرات المطلوبة (بعد اعتمادها داخلياً)</label><small class="d-block mb-2">كل مؤهل أو خبرة في حقل مستقل. لا تُعتمد المتطلبات إلا بعد مراجعتها داخلياً.</small><div id="qualificationFields" class="jd-item-fields d-flex flex-column gap-2"><?php foreach ($editingQualifications as $index => $item): ?><div class="jd-item-row d-flex gap-2 align-items-start"><span class="pt-2 text-muted jd-item-number"><?= (int)($index + 1) ?>.</span><button type="button" class="btn btn-light btn-sm jd-drag-handle" aria-label="اسحب لإعادة ترتيب البند" title="اسحب لإعادة ترتيب البند"><i class="fa-solid fa-grip-vertical"></i></button><input type="text" name="qualifications[]" maxlength="1000" class="form-control" value="<?= e($item) ?>" placeholder="مؤهل أو خبرة مطلوبة"><button type="button" class="btn btn-outline-danger jd-remove-item" aria-label="حذف البند"><i class="fa-solid fa-trash"></i></button></div><?php endforeach; ?></div><button type="button" class="btn btn-sm btn-outline-primary mt-2 jd-add-item" data-target="qualificationFields" data-name="qualifications[]" data-limit="30" data-placeholder="مؤهل أو خبرة مطلوبة"><i class="fa-solid fa-plus me-1"></i> إضافة مؤهل / خبرة</button></div>
    <div class="jd-field jd-full"><label>بنود إضافية خاصة بهذا المسمى في العقد (اختيارية)</label><small class="d-block mb-2">كل بند في حقل مستقل؛ تُترك فارغة ما لم توجد شروط مهنية خاصة موثقة ومعتمدة لهذا المسمى.</small><div id="termFields" class="jd-item-fields d-flex flex-column gap-2"><?php foreach ($editingTerms as $index => $item): ?><div class="jd-item-row d-flex gap-2 align-items-start"><span class="pt-2 text-muted jd-item-number"><?= (int)($index + 1) ?>.</span><button type="button" class="btn btn-light btn-sm jd-drag-handle" aria-label="اسحب لإعادة ترتيب البند" title="اسحب لإعادة ترتيب البند"><i class="fa-solid fa-grip-vertical"></i></button><input type="text" name="role_specific_terms[]" maxlength="1000" class="form-control" value="<?= e($item) ?>" placeholder="بند مهني موثق ومتفق عليه"><button type="button" class="btn btn-outline-danger jd-remove-item" aria-label="حذف البند"><i class="fa-solid fa-trash"></i></button></div><?php endforeach; ?></div><button type="button" class="btn btn-sm btn-outline-primary mt-2 jd-add-item" data-target="termFields" data-name="role_specific_terms[]" data-limit="20" data-placeholder="بند مهني موثق ومتفق عليه"><i class="fa-solid fa-plus me-1"></i> إضافة بند</button><small class="d-block mt-2">هذه البنود تُضاف إلى الشروط الخاصة للعقد عند اختيار الموظف؛ لا تضع هنا استحقاقات قانونية غير متحقق منها.</small></div>
    <div class="jd-field"><label for="description_status">حالة الوصف</label><select id="description_status" name="description_status" <?= $canApprove ? '' : 'disabled' ?>><option value="needs_review" <?= ($editing['description_status'] ?? 'needs_review') === 'needs_review' ? 'selected' : '' ?>>يحتاج مراجعة HR</option><?php if ($canApprove): ?><option value="approved" <?= ($editing['description_status'] ?? '') === 'approved' ? 'selected' : '' ?>>معتمد داخلياً</option><?php endif; ?></select><?php if (!$canApprove): ?><small>اعتماد الوصف متاح لمدير الموارد البشرية أو مدير النظام.</small><input type="hidden" name="description_status" value="needs_review"><?php endif; ?></div>
    <div class="jd-field d-flex align-items-center gap-2 pt-4"><input class="form-check-input mt-0" type="checkbox" id="is_active" name="is_active" value="1" <?= !isset($editing['is_active']) || (int)$editing['is_active'] === 1 ? 'checked' : '' ?>><label for="is_active" class="mb-0">متاح للاستخدام في العقود</label></div>
   </div>
   <div class="d-flex gap-2 flex-wrap mt-3"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> حفظ الوصف</button><a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>modules/hr/job_description_catalogue.php">وصف جديد / إلغاء</a></div>
  </form>
 </div></div>
 <div class="jd-card"><div class="jd-card-head">الأوصاف حسب المسمى الوظيفي ودور الحساب</div><div class="table-responsive"><table class="table table-hover jd-table mb-0"><thead class="table-light"><tr><th>المسمى الوظيفي</th><th>دور النظام</th><th>الموظفون</th><th>حالة الوصف</th><th>الاستخدام</th><th>آخر تحديث</th><th>الإجراء</th></tr></thead><tbody>
 <?php foreach ($templates as $item): $isGeneralTemplate = trim((string)($item['system_role_code'] ?? '')) === ''; $templateFieldsEmpty = trim((string)($item['role_purpose'] ?? '')) === '' && trim((string)($item['responsibilities'] ?? '')) === ''; ?><tr><td class="fw-bold"><?= e($item['position_title']) ?></td><td><?php if ($isGeneralTemplate): ?>عام / خيار احتياطي<?php else: ?><?= e($item['system_role_name'] ?: $item['system_role_code']) ?><?php endif; ?><?php if ($isGeneralTemplate && (int)$item['role_specific_template_count'] > 0): ?><div class="small text-muted mt-1"><?= (int)$item['role_specific_template_count'] ?> وصفاً نشطاً حسب دور النظام</div><?php endif; ?><?php if ($isGeneralTemplate && $templateFieldsEmpty && (int)$item['role_specific_template_count'] > 0): ?><span class="badge bg-warning text-dark mt-1">احتياطي فارغ — توجد أوصاف حسب الدور</span><?php endif; ?></td><td><?= (int)$item['employee_count'] ?></td><td class="jd-status"><?php if ($item['description_status'] === 'approved'): ?><span class="badge bg-success">معتمد داخلياً</span><?php else: ?><span class="badge bg-warning text-dark">يحتاج مراجعة HR</span><?php endif; ?></td><td><?= (int)$item['is_active'] === 1 ? '<span class="text-success">مفعل</span>' : '<span class="text-muted">غير مفعل</span>' ?></td><td><?= e($item['updated_at'] ?? '') ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>modules/hr/job_description_catalogue.php?id=<?= (int)$item['id'] ?>">تعديل</a></td></tr><?php endforeach; ?>
 <?php if (!$templates): ?><tr><td colspan="7" class="text-center text-muted p-4">لا توجد أوصاف بعد. تأكد من تطبيق ترحيل فهرس الأوصاف الوظيفية.</td></tr><?php endif; ?>
 </tbody></table></div></div>
 <div class="text-muted small mb-4">هذه حالة اعتماد داخلية للوصف الوظيفي وليست اعتماداً قانونياً لعقد العمل. تظل مراجعة محامٍ سوداني مؤهل مطلوبة قبل الاستخدام الرسمي للعقود.</div>
</div>
<script>
(function(){
 const toast=document.getElementById('jdSuccessToast');
 if(toast){
  let hideTimer=window.setTimeout(hideToast,5000);
  function hideToast(){toast.classList.add('is-hiding');window.setTimeout(()=>toast.remove(),220);}
  const closeButton=toast.querySelector('.jd-toast-close');
  if(closeButton)closeButton.addEventListener('click',()=>{window.clearTimeout(hideTimer);hideToast();});
 }
 function renumber(container){Array.from(container.querySelectorAll('.jd-item-row')).forEach((row,index)=>{const number=row.querySelector('.jd-item-number');if(number)number.textContent=(index+1)+'.';});}
 function createDragHandle(){const handle=document.createElement('button');handle.type='button';handle.className='btn btn-light btn-sm jd-drag-handle';handle.setAttribute('aria-label','اسحب لإعادة ترتيب البند');handle.title='اسحب لإعادة ترتيب البند';handle.innerHTML='<i class="fa-solid fa-grip-vertical"></i>';return handle;}
 document.querySelectorAll('.jd-add-item').forEach(button=>button.addEventListener('click',()=>{
  const container=document.getElementById(button.dataset.target);if(!container)return;
  const limit=parseInt(button.dataset.limit||'30',10);
  if(container.querySelectorAll('.jd-item-row').length>=limit){alert('يمكن إدخال '+limit+' بنداً كحد أقصى.');return;}
  const row=document.createElement('div');row.className='jd-item-row d-flex gap-2 align-items-start';
  const number=document.createElement('span');number.className='pt-2 text-muted jd-item-number';
  const input=document.createElement('input');input.type='text';input.name=button.dataset.name;input.maxLength=1000;input.className='form-control';input.placeholder=button.dataset.placeholder||'';
  const remove=document.createElement('button');remove.type='button';remove.className='btn btn-outline-danger jd-remove-item';remove.setAttribute('aria-label','حذف البند');remove.innerHTML='<i class="fa-solid fa-trash"></i>';
  row.append(number,createDragHandle(),input,remove);container.appendChild(row);renumber(container);input.focus();
 }));
 document.querySelectorAll('.jd-item-fields').forEach(container=>{
  let draggedRow=null;
  container.addEventListener('click',event=>{
   const button=event.target.closest('.jd-remove-item');if(!button)return;
   button.closest('.jd-item-row').remove();
   if(!container.querySelector('.jd-item-row')){const addButton=document.querySelector('.jd-add-item[data-target="'+container.id+'"]');if(addButton)addButton.click();}
   renumber(container);
  });
  container.addEventListener('pointerdown',event=>{
   const handle=event.target.closest('.jd-drag-handle');if(!handle||event.button!==0)return;
   draggedRow=handle.closest('.jd-item-row');if(!draggedRow)return;
   event.preventDefault();draggedRow.classList.add('is-dragging');
   if(container.setPointerCapture)container.setPointerCapture(event.pointerId);
  });
  container.addEventListener('pointermove',event=>{
   if(!draggedRow)return;
   const target=document.elementFromPoint(event.clientX,event.clientY);
   const targetRow=target&&target.closest?target.closest('.jd-item-row'):null;
   if(!targetRow||targetRow===draggedRow||targetRow.parentElement!==container)return;
   const rect=targetRow.getBoundingClientRect();
   container.insertBefore(draggedRow,event.clientY<rect.top+rect.height/2?targetRow:targetRow.nextSibling);
   renumber(container);
  });
  const finishDrag=()=>{if(draggedRow){draggedRow.classList.remove('is-dragging');draggedRow=null;renumber(container);}};
  container.addEventListener('pointerup',finishDrag);
  container.addEventListener('pointercancel',finishDrag);
  renumber(container);
 });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
