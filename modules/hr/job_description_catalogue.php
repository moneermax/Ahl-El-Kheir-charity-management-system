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
$message = '';
$error = '';
$editingId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf()) {
            http_response_code(403);
            throw new RuntimeException('انتهت صلاحية النموذج. أعد تحميل الصفحة ثم حاول مرة أخرى.');
        }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save') {
            $title = trim((string)($_POST['position_title'] ?? ''));
            $purpose = trim((string)($_POST['role_purpose'] ?? ''));
            $responsibilities = trim((string)($_POST['responsibilities'] ?? ''));
            $qualifications = trim((string)($_POST['qualifications'] ?? ''));
            $reportingTo = trim((string)($_POST['reporting_to'] ?? ''));
            foreach ([
                'المسمى الوظيفي' => [$title, 180],
                'الغرض الوظيفي' => [$purpose, 5000],
                'المسؤوليات' => [$responsibilities, 12000],
                'المؤهلات' => [$qualifications, 5000],
                'جهة الإشراف' => [$reportingTo, 180],
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
            $active = isset($_POST['is_active']) ? 1 : 0;

            if ($editingId > 0) {
                $before = dbFetchOne('SELECT * FROM hr_job_description_templates WHERE id = ? LIMIT 1', [$editingId]);
                if (!$before) {
                    throw new RuntimeException('الوصف الوظيفي المطلوب غير موجود.');
                }
                $stmt = $pdo->prepare(
                    'UPDATE hr_job_description_templates
                     SET position_title = ?, role_purpose = ?, responsibilities = ?, qualifications = ?,
                         reporting_to = ?, description_status = ?, is_active = ?, updated_by = ?
                     WHERE id = ?'
                );
                $stmt->execute([$title, $purpose ?: null, $responsibilities ?: null, $qualifications ?: null,
                    $reportingTo ?: null, $status, $active, Session::getUserID(), $editingId]);
                $actionLabel = 'UPDATE';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO hr_job_description_templates
                     (position_title, role_purpose, responsibilities, qualifications, reporting_to,
                      description_status, is_active, created_by, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$title, $purpose ?: null, $responsibilities ?: null, $qualifications ?: null,
                    $reportingTo ?: null, $status, $active, Session::getUserID(), Session::getUserID()]);
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
                            'position_title' => $title, 'role_purpose' => $purpose,
                            'responsibilities' => $responsibilities, 'qualifications' => $qualifications,
                            'reporting_to' => $reportingTo, 'description_status' => $status, 'is_active' => $active
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
$templates = dbFetchAll(
    'SELECT j.*, (SELECT COUNT(*) FROM employees e WHERE TRIM(e.position) = j.position_title) AS employee_count
     FROM hr_job_description_templates j
     ORDER BY j.is_active DESC, j.position_title',
    []
);
$pageTitle = 'فهرس الأوصاف الوظيفية';
$active = 'hr';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.jd-wrap{max-width:1400px;margin:auto}.jd-hero{background:linear-gradient(120deg,#173f73,#2d67ad);color:#fff;padding:20px 24px;border-radius:14px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:14px}.jd-hero h1{font-size:1.35rem;font-weight:800;margin:0}.jd-hero p{margin:5px 0 0;font-size:.82rem;opacity:.9}.jd-card{background:#fff;border:1px solid #e4e9f0;border-radius:12px;overflow:hidden;margin-bottom:16px;box-shadow:0 3px 12px rgba(16,24,40,.04)}.jd-card-head{padding:13px 17px;border-bottom:1px solid #e7ecf2;font-weight:800;color:#173f73}.jd-card-body{padding:17px}.jd-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.jd-field label{display:block;font-size:.78rem;font-weight:800;margin-bottom:5px}.jd-field input,.jd-field select,.jd-field textarea{width:100%;border:1px solid #b9c7d8;border-radius:8px;padding:9px 10px;font:inherit;font-size:.82rem;background:#fff}.jd-field textarea{min-height:110px}.jd-full{grid-column:1/-1}.jd-table{font-size:.8rem}.jd-table td,.jd-table th{vertical-align:middle}.jd-status{white-space:nowrap}@media(max-width:720px){.jd-grid{grid-template-columns:1fr}.jd-full{grid-column:auto}.jd-hero{align-items:flex-start;flex-direction:column}}
</style>
<div class="jd-wrap">
 <div class="jd-hero"><div><h1><i class="fa-solid fa-list-check me-2"></i>فهرس الأوصاف الوظيفية</h1><p>إدارة أوصاف الوظائف بصورة مستقلة عن تصميم عقد العمل؛ لا تُعامل الأوصاف غير المعتمدة على أنها أوصاف رسمية.</p></div><a class="btn btn-light btn-sm fw-bold" href="<?= e(APP_URL) ?>modules/hr/contract_builder.php"><i class="fa-solid fa-file-signature me-1"></i> بوابة عقود العمل</a></div>
 <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
 <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
 <div class="alert alert-info small">تم إنشاء سجل لكل مسمى وظيفي موجود في بيانات الموظفين عند تطبيق الترحيل، دون اختلاق مهام أو مؤهلات. أكمل الحقول ثم اعتمد الوصف من مدير الموارد البشرية أو مدير النظام. تغيير أي وصف معتمد يعيده إلى «يحتاج مراجعة» ما لم يُعتمد صراحةً في عملية الحفظ نفسها.</div>
 <div class="jd-card"><div class="jd-card-head"><?= $editing ? 'تعديل وصف وظيفي' : 'إضافة مسمى ووصف وظيفي' ?></div><div class="jd-card-body">
  <form method="post" action="<?= e(APP_URL) ?>modules/hr/job_description_catalogue.php">
   <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
   <div class="jd-grid">
    <div class="jd-field"><label for="position_title">المسمى الوظيفي *</label><input id="position_title" name="position_title" maxlength="180" required value="<?= e($editing['position_title'] ?? '') ?>"></div>
    <div class="jd-field"><label for="reporting_to">جهة الإشراف المباشر (إن كانت محددة)</label><input id="reporting_to" name="reporting_to" maxlength="180" value="<?= e($editing['reporting_to'] ?? '') ?>"></div>
    <div class="jd-field jd-full"><label for="role_purpose">الغرض من الوظيفة</label><textarea id="role_purpose" name="role_purpose" maxlength="5000"><?= e($editing['role_purpose'] ?? '') ?></textarea></div>
    <div class="jd-field jd-full"><label for="responsibilities">المهام والمسؤوليات الأساسية</label><textarea id="responsibilities" name="responsibilities" maxlength="12000"><?= e($editing['responsibilities'] ?? '') ?></textarea></div>
    <div class="jd-field jd-full"><label for="qualifications">المؤهلات والخبرات المطلوبة (بعد اعتمادها داخلياً)</label><textarea id="qualifications" name="qualifications" maxlength="5000"><?= e($editing['qualifications'] ?? '') ?></textarea></div>
    <div class="jd-field"><label for="description_status">حالة الوصف</label><select id="description_status" name="description_status" <?= $canApprove ? '' : 'disabled' ?>><option value="needs_review" <?= ($editing['description_status'] ?? 'needs_review') === 'needs_review' ? 'selected' : '' ?>>يحتاج مراجعة HR</option><?php if ($canApprove): ?><option value="approved" <?= ($editing['description_status'] ?? '') === 'approved' ? 'selected' : '' ?>>معتمد داخلياً</option><?php endif; ?></select><?php if (!$canApprove): ?><small>اعتماد الوصف متاح لمدير الموارد البشرية أو مدير النظام.</small><input type="hidden" name="description_status" value="needs_review"><?php endif; ?></div>
    <div class="jd-field d-flex align-items-center gap-2 pt-4"><input class="form-check-input mt-0" type="checkbox" id="is_active" name="is_active" value="1" <?= !isset($editing['is_active']) || (int)$editing['is_active'] === 1 ? 'checked' : '' ?>><label for="is_active" class="mb-0">متاح للاستخدام في العقود</label></div>
   </div>
   <div class="d-flex gap-2 flex-wrap mt-3"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> حفظ الوصف</button><a class="btn btn-outline-secondary" href="<?= e(APP_URL) ?>modules/hr/job_description_catalogue.php">وصف جديد / إلغاء</a></div>
  </form>
 </div></div>
 <div class="jd-card"><div class="jd-card-head">الأوصاف حسب المسمى الوظيفي</div><div class="table-responsive"><table class="table table-hover jd-table mb-0"><thead class="table-light"><tr><th>المسمى الوظيفي</th><th>الموظفون</th><th>حالة الوصف</th><th>الاستخدام</th><th>آخر تحديث</th><th>الإجراء</th></tr></thead><tbody>
 <?php foreach ($templates as $item): ?><tr><td class="fw-bold"><?= e($item['position_title']) ?></td><td><?= (int)$item['employee_count'] ?></td><td class="jd-status"><?php if ($item['description_status'] === 'approved'): ?><span class="badge bg-success">معتمد داخلياً</span><?php else: ?><span class="badge bg-warning text-dark">يحتاج مراجعة HR</span><?php endif; ?></td><td><?= (int)$item['is_active'] === 1 ? '<span class="text-success">مفعل</span>' : '<span class="text-muted">غير مفعل</span>' ?></td><td><?= e($item['updated_at'] ?? '') ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>modules/hr/job_description_catalogue.php?id=<?= (int)$item['id'] ?>">تعديل</a></td></tr><?php endforeach; ?>
 <?php if (!$templates): ?><tr><td colspan="6" class="text-center text-muted p-4">لا توجد أوصاف بعد. تأكد من تطبيق ترحيل فهرس الأوصاف الوظيفية.</td></tr><?php endif; ?>
 </tbody></table></div></div>
 <div class="text-muted small mb-4">هذه حالة اعتماد داخلية للوصف الوظيفي وليست اعتماداً قانونياً لعقد العمل. تظل مراجعة محامٍ سوداني مؤهل مطلوبة قبل الاستخدام الرسمي للعقود.</div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
