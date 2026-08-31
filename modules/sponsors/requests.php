<<<<<<< HEAD
<?php
// modules/sponsors/requests.php - Sponsor requests / social-media leads pipeline + Directive 2
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'social_media'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$pageTitle = 'طلبات الكفلاء';
$active = 'sponsors'; 

// Source icon map
$sourceOptions = [
    'تيك توك' => 'fab fa-tiktok text-danger',
    'فيسبوك' => 'fab fa-facebook text-primary',
    'حملة إعلامية' => 'fas fa-bullhorn text-info',
    'موظف' => 'fas fa-user-tie text-success',
    'مباشر' => 'fas fa-walking text-secondary',
    'أخرى' => 'fas fa-question-circle text-muted'
];

// Idempotent schema check
dbExecute("CREATE TABLE IF NOT EXISTS sponsor_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sponsor_name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    source VARCHAR(100),
    brought_by INT,
    brought_by_name VARCHAR(255),
    status ENUM('new','contacted','converted','lost') DEFAULT 'new',
    notes TEXT,
    created_sponsor_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$rc = [];
foreach (dbFetchAll("SHOW COLUMNS FROM sponsor_requests") as $c) $rc[$c['Field']] = true;
if (!isset($rc['created_sponsor_id'])) dbExecute("ALTER TABLE sponsor_requests ADD COLUMN created_sponsor_id INT NULL");

$currentUser = dbFetchOne("SELECT full_name FROM users WHERE id = ?", [Session::getUserId()]);
$currentUserName = $currentUser['full_name'] ?? '';

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['sponsor_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $source = trim($_POST['source'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $byName = trim($_POST['brought_by_name'] ?? '');
        
        if ($byName === '') $byName = $currentUserName;
        
        if ($name !== '' && $source !== '') {
            dbExecute("INSERT INTO sponsor_requests (sponsor_name, phone, source, brought_by, brought_by_name, notes) VALUES (?,?,?,?,?,?)",
                [$name, $phone !== '' ? $phone : null, $source, Session::getUserId(), $byName, $notes !== '' ? $notes : null]);
            
            flash('success', 'تمت إضافة الطلب بنجاح.');
            header('Location: ' . APP_URL . 'modules/sponsors/requests.php'); exit();
        } else {
            flash('error', 'يرجى إدخال اسم الكفيل واختيار المصدر.');
        }
    }
    
    if ($action === 'convert' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $req = dbFetchOne("SELECT * FROM sponsor_requests WHERE id = ?", [$id]);
        
        if ($req && $req['status'] !== 'converted') {
            $n = (int)(dbFetchOne("SELECT COUNT(*) c FROM sponsors")['c'] ?? 0) + 1;
            $code = 'SP-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
            
            // Directive 2: Map source and brought_by directly to the new sponsor record
            dbExecute("INSERT INTO sponsors (full_name, phone, sponsor_code, status, sponsor_type, gender, preferred_payment_method, acquisition_source, brought_by_user_id, created_by) 
                       VALUES (?, ?, ?, 'active', 'individual', 'unknown', 'cash', ?, ?, ?)",
                [$req['sponsor_name'], $req['phone'], $code, $req['source'], $req['brought_by'], Session::getUserId()]);
            
            $newId = (int)dbLastInsertId();
            
            dbExecute("UPDATE sponsor_requests SET status = 'converted', created_sponsor_id = ? WHERE id = ?", [$newId, $id]);
            
            dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, 'CONVERT', 'sponsor_request', ?, ?, ?)",
                [Session::getUserId(), $id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                
            flash('success', 'تم تحويل الطلب إلى كفيل بنجاح.');
            header('Location: ' . APP_URL . 'modules/sponsors/view.php?id=' . $newId); exit();
        }
    }
}

$leads = dbFetchAll("SELECT r.*, u.full_name AS brought_by_user FROM sponsor_requests r LEFT JOIN users u ON u.id = r.brought_by ORDER BY r.created_at DESC");

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-bullhorn me-2"></i>طلبات الكفلاء — مصادر التواصل الاجتماعي</h2>
    <p>تسجيل العملاء المحتملين الواردين من المنصات والمتابعات، ثم تحويلهم إلى كفلاء.</p>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="d-flex justify-content-end mb-3 fade-in">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeadModal"><i class="fas fa-plus me-1"></i>إضافة طلب جديد</button>
</div>

<div class="card mb-4 fade-in">
    <div class="card-body p-2">
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white mb-0">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>اسم الكفيل</th>
                        <th>رقم الهاتف</th>
                        <th>المصدر</th>
                        <th>جلب بواسطة</th>
                        <th>الحالة</th>
                        <th>ملاحظات</th>
                        <th class="text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$leads): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">لا توجد نتائج.</td></tr>
                    <?php else: foreach ($leads as $lead): 
                        $badges = ['new' => 'bg-info', 'contacted' => 'bg-warning text-dark', 'converted' => 'bg-success', 'lost' => 'bg-secondary'];
                        $statusAr = ['new' => 'جديد', 'contacted' => 'تم التواصل', 'converted' => 'محوّل', 'lost' => 'مفقود'];
                        $byDisplay = trim((string)($lead['brought_by_name'] ?? '')) !== '' ? $lead['brought_by_name'] : ($lead['brought_by_user'] ?? '—');
                    ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($lead['created_at'])); ?></td>
                            <td><?php echo e($lead['sponsor_name']); ?></td>
                            <td dir="ltr"><?php echo e($lead['phone'] ?? '-'); ?></td>
                            <td>
                                <i class="<?php echo $sourceOptions[$lead['source']] ?? 'fas fa-question-circle text-muted'; ?> me-1"></i>
                                <?php echo e($lead['source']); ?>
                            </td>
                            <td><?php echo e($byDisplay); ?></td>
                            <td><span class="badge <?php echo $badges[$lead['status']] ?? 'bg-secondary'; ?>"><?php echo $statusAr[$lead['status']] ?? $lead['status']; ?></span></td>
                            <td class="small text-muted"><?php echo e($lead['notes'] ?? ''); ?></td>
                            <td class="text-center">
                                <?php if ($lead['status'] !== 'converted'): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('سيتم إنشاء سجل كفيل جديد. متابعة؟');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="convert">
                                        <input type="hidden" name="id" value="<?php echo (int)$lead['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="تحويل إلى كفيل">
                                            <i class="fas fa-user-check"></i> تحويل
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted"><i class="fas fa-check-circle"></i> مكتمل</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add-lead modal -->
<div class="modal fade" id="addLeadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header" style="background:#1b4d8f;color:#fff">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>إضافة طلب جديد</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم الكفيل *</label>
                        <input type="text" name="sponsor_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control" dir="ltr">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المصدر *</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($sourceOptions as $val => $icon): $sid = 'src_' . md5($val); ?>
                                <input type="radio" class="btn-check" name="source" id="<?php echo $sid; ?>" value="<?php echo e($val); ?>" required>
                                <label class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" for="<?php echo $sid; ?>">
                                    <i class="<?php echo $icon; ?>"></i><span><?php echo $val; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">جلب بواسطة</label>
                        <input type="text" name="brought_by_name" class="form-control" value="<?php echo e($currentUserName); ?>">
                        <div class="form-text">الافتراضي: اسم المستخدم الحالي — يمكن تعديله.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

=======
<?php
// modules/sponsors/requests.php - Sponsor requests / social-media leads pipeline + Directive 2
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'social_media'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$pageTitle = 'طلبات الكفلاء';
$active = 'sponsors'; 

// Source icon map
$sourceOptions = [
    'تيك توك' => 'fab fa-tiktok text-danger',
    'فيسبوك' => 'fab fa-facebook text-primary',
    'حملة إعلامية' => 'fas fa-bullhorn text-info',
    'موظف' => 'fas fa-user-tie text-success',
    'مباشر' => 'fas fa-walking text-secondary',
    'أخرى' => 'fas fa-question-circle text-muted'
];

// Idempotent schema check
dbExecute("CREATE TABLE IF NOT EXISTS sponsor_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sponsor_name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    source VARCHAR(100),
    brought_by INT,
    brought_by_name VARCHAR(255),
    status ENUM('new','contacted','converted','lost') DEFAULT 'new',
    notes TEXT,
    created_sponsor_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$rc = [];
foreach (dbFetchAll("SHOW COLUMNS FROM sponsor_requests") as $c) $rc[$c['Field']] = true;
if (!isset($rc['created_sponsor_id'])) dbExecute("ALTER TABLE sponsor_requests ADD COLUMN created_sponsor_id INT NULL");

$currentUser = dbFetchOne("SELECT full_name FROM users WHERE id = ?", [Session::getUserId()]);
$currentUserName = $currentUser['full_name'] ?? '';

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['sponsor_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $source = trim($_POST['source'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $byName = trim($_POST['brought_by_name'] ?? '');
        
        if ($byName === '') $byName = $currentUserName;
        
        if ($name !== '' && $source !== '') {
            dbExecute("INSERT INTO sponsor_requests (sponsor_name, phone, source, brought_by, brought_by_name, notes) VALUES (?,?,?,?,?,?)",
                [$name, $phone !== '' ? $phone : null, $source, Session::getUserId(), $byName, $notes !== '' ? $notes : null]);
            
            flash('success', 'تمت إضافة الطلب بنجاح.');
            header('Location: ' . APP_URL . 'modules/sponsors/requests.php'); exit();
        } else {
            flash('error', 'يرجى إدخال اسم الكفيل واختيار المصدر.');
        }
    }
    
    if ($action === 'convert' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $req = dbFetchOne("SELECT * FROM sponsor_requests WHERE id = ?", [$id]);
        
        if ($req && $req['status'] !== 'converted') {
            $n = (int)(dbFetchOne("SELECT COUNT(*) c FROM sponsors")['c'] ?? 0) + 1;
            $code = 'SP-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
            
            // Directive 2: Map source and brought_by directly to the new sponsor record
            dbExecute("INSERT INTO sponsors (full_name, phone, sponsor_code, status, sponsor_type, gender, preferred_payment_method, acquisition_source, brought_by_user_id, created_by) 
                       VALUES (?, ?, ?, 'active', 'individual', 'unknown', 'cash', ?, ?, ?)",
                [$req['sponsor_name'], $req['phone'], $code, $req['source'], $req['brought_by'], Session::getUserId()]);
            
            $newId = (int)dbLastInsertId();
            
            dbExecute("UPDATE sponsor_requests SET status = 'converted', created_sponsor_id = ? WHERE id = ?", [$newId, $id]);
            
            dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, 'CONVERT', 'sponsor_request', ?, ?, ?)",
                [Session::getUserId(), $id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                
            flash('success', 'تم تحويل الطلب إلى كفيل بنجاح.');
            header('Location: ' . APP_URL . 'modules/sponsors/view.php?id=' . $newId); exit();
        }
    }
}

$leads = dbFetchAll("SELECT r.*, u.full_name AS brought_by_user FROM sponsor_requests r LEFT JOIN users u ON u.id = r.brought_by ORDER BY r.created_at DESC");

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-bullhorn me-2"></i>طلبات الكفلاء — مصادر التواصل الاجتماعي</h2>
    <p>تسجيل العملاء المحتملين الواردين من المنصات والمتابعات، ثم تحويلهم إلى كفلاء.</p>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="d-flex justify-content-end mb-3 fade-in">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeadModal"><i class="fas fa-plus me-1"></i>إضافة طلب جديد</button>
</div>

<div class="card mb-4 fade-in">
    <div class="card-body p-2">
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white mb-0">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>اسم الكفيل</th>
                        <th>رقم الهاتف</th>
                        <th>المصدر</th>
                        <th>جلب بواسطة</th>
                        <th>الحالة</th>
                        <th>ملاحظات</th>
                        <th class="text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$leads): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">لا توجد نتائج.</td></tr>
                    <?php else: foreach ($leads as $lead): 
                        $badges = ['new' => 'bg-info', 'contacted' => 'bg-warning text-dark', 'converted' => 'bg-success', 'lost' => 'bg-secondary'];
                        $statusAr = ['new' => 'جديد', 'contacted' => 'تم التواصل', 'converted' => 'محوّل', 'lost' => 'مفقود'];
                        $byDisplay = trim((string)($lead['brought_by_name'] ?? '')) !== '' ? $lead['brought_by_name'] : ($lead['brought_by_user'] ?? '—');
                    ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($lead['created_at'])); ?></td>
                            <td><?php echo e($lead['sponsor_name']); ?></td>
                            <td dir="ltr"><?php echo e($lead['phone'] ?? '-'); ?></td>
                            <td>
                                <i class="<?php echo $sourceOptions[$lead['source']] ?? 'fas fa-question-circle text-muted'; ?> me-1"></i>
                                <?php echo e($lead['source']); ?>
                            </td>
                            <td><?php echo e($byDisplay); ?></td>
                            <td><span class="badge <?php echo $badges[$lead['status']] ?? 'bg-secondary'; ?>"><?php echo $statusAr[$lead['status']] ?? $lead['status']; ?></span></td>
                            <td class="small text-muted"><?php echo e($lead['notes'] ?? ''); ?></td>
                            <td class="text-center">
                                <?php if ($lead['status'] !== 'converted'): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('سيتم إنشاء سجل كفيل جديد. متابعة؟');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="convert">
                                        <input type="hidden" name="id" value="<?php echo (int)$lead['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="تحويل إلى كفيل">
                                            <i class="fas fa-user-check"></i> تحويل
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted"><i class="fas fa-check-circle"></i> مكتمل</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add-lead modal -->
<div class="modal fade" id="addLeadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-header" style="background:#1b4d8f;color:#fff">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>إضافة طلب جديد</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم الكفيل *</label>
                        <input type="text" name="sponsor_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control" dir="ltr">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المصدر *</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($sourceOptions as $val => $icon): $sid = 'src_' . md5($val); ?>
                                <input type="radio" class="btn-check" name="source" id="<?php echo $sid; ?>" value="<?php echo e($val); ?>" required>
                                <label class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" for="<?php echo $sid; ?>">
                                    <i class="<?php echo $icon; ?>"></i><span><?php echo $val; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">جلب بواسطة</label>
                        <input type="text" name="brought_by_name" class="form-control" value="<?php echo e($currentUserName); ?>">
                        <div class="form-text">الافتراضي: اسم المستخدم الحالي — يمكن تعديله.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>