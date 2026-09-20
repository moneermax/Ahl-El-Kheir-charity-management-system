<?php
// modules/sponsors/requests.php - Sponsor requests / social-media leads pipeline + Directive 2
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once dirname(__DIR__, 2) . '/config/sponsor_assignments.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'administration', 'staff', 'social_media'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$forcedStatusFilter = isset($forcedStatusFilter) ? trim((string)$forcedStatusFilter) : '';
$allowedStatusFilters = ['new', 'contacted', 'converted', 'lost'];
if ($forcedStatusFilter !== '' && !in_array($forcedStatusFilter, $allowedStatusFilters, true)) {
    $forcedStatusFilter = '';
}
$pageTitle = t('sponsors.requests_title'); $active = 'sponsors';
$sourceOptions = [
    'تيك توك' => ['icon'=>'fab fa-tiktok text-danger','key'=>'sponsors.request_source_tiktok'],
    'فيسبوك' => ['icon'=>'fab fa-facebook text-primary','key'=>'sponsors.request_source_facebook'],
    'حملة إعلامية' => ['icon'=>'fas fa-bullhorn text-info','key'=>'sponsors.request_source_campaign'],
    'موظف' => ['icon'=>'fas fa-user-tie text-success','key'=>'sponsors.request_source_employee'],
    'مباشر' => ['icon'=>'fas fa-walking text-secondary','key'=>'sponsors.request_source_direct'],
    'أخرى' => ['icon'=>'fas fa-question-circle text-muted','key'=>'sponsors.request_source_other']
];
$currentUser = dbFetchOne("SELECT full_name FROM users WHERE id = ?", [Session::getUserId()]); $currentUserName = $currentUser['full_name'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['sponsor_name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $source = trim($_POST['source'] ?? ''); $gender = strtolower(trim((string)($_POST['gender'] ?? ''))); $notes = trim($_POST['notes'] ?? ''); $byName = trim($_POST['brought_by_name'] ?? ''); if ($byName === '') $byName = $currentUserName;
        if ($name !== '' && $source !== '' && in_array($gender, ['male', 'female'], true)) { dbExecute("INSERT INTO sponsor_requests (sponsor_name, phone, gender, source, brought_by, brought_by_name, notes) VALUES (?,?,?,?,?,?,?)", [$name, $phone !== '' ? $phone : null, $gender, $source, Session::getUserId(), $byName, $notes !== '' ? $notes : null]); flash('success', t('sponsors.request_added')); header('Location: ' . APP_URL . ($forcedStatusFilter === 'new' ? 'modules/sponsors/new_requests.php' : ($forcedStatusFilter === 'contacted' ? 'modules/sponsors/contacted_requests.php' : 'modules/sponsors/requests.php'))); exit(); } else { flash('error', $name === '' ? t('sponsors.request_name_required') : t('sponsors.create_gender') . ': ' . t('sponsors.create_male') . ' / ' . t('sponsors.create_female')); }
    }
    if ($action === 'set_status' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $newStatus = trim((string)($_POST['status'] ?? ''));
        if (!in_array($newStatus, ['contacted', 'lost'], true)) {
            flash('error', 'حالة الطلب غير صالحة.');
        } else {
            $request = dbFetchOne("SELECT id, status FROM sponsor_requests WHERE id = ?", [$id]);
            if (!$request) {
                flash('error', 'طلب الرعاية غير موجود.');
            } elseif (!in_array((string)$request['status'], ['new', 'contacted'], true)) {
                flash('error', 'لا يمكن تغيير حالة هذا الطلب بعد إغلاقه أو تحويله.');
            } else {
                dbExecute("UPDATE sponsor_requests SET status = ? WHERE id = ?", [$newStatus, $id]);
                flash('success', $newStatus === 'contacted' ? t('sponsors.request_marked_contacted') : t('sponsors.request_marked_lost'));
            }
        }
        header('Location: ' . APP_URL . ($forcedStatusFilter === 'new' ? 'modules/sponsors/new_requests.php' : ($forcedStatusFilter === 'contacted' ? 'modules/sponsors/contacted_requests.php' : 'modules/sponsors/requests.php')));
        exit();
    }
    if ($action === 'reopen' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $request = dbFetchOne("SELECT id, status FROM sponsor_requests WHERE id = ?", [$id]);
        if (!$request) {
            flash('error', 'طلب الرعاية غير موجود.');
        } elseif ((string)$request['status'] !== 'lost') {
            flash('error', 'يمكن إعادة فتح الطلبات المغلقة فقط.');
        } else {
            dbExecute("UPDATE sponsor_requests SET status = 'contacted' WHERE id = ? AND status = 'lost'", [$id]);
            flash('success', t('sponsors.request_reopened'));
        }
        header('Location: ' . APP_URL . ($forcedStatusFilter === 'contacted' ? 'modules/sponsors/contacted_requests.php' : 'modules/sponsors/requests.php?status=contacted'));
        exit();
    }
    if ($action === 'delete' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $request = dbFetchOne("SELECT id, status, assigned_supervisor_id FROM sponsor_requests WHERE id = ?", [$id]);
        if (!$request) {
            flash('error', 'طلب الرعاية غير موجود.');
        } elseif ((string)$request['status'] !== 'new' || !empty($request['assigned_supervisor_id'])) {
            flash('error', 'لا يمكن حذف هذا الطلب بعد بدء التواصل أو تحويله إلى المشرف المختص.');
        } else {
            dbExecute("DELETE FROM sponsor_requests WHERE id = ? AND status = 'new' AND assigned_supervisor_id IS NULL", [$id]);
            flash('success', 'تم حذف طلب الرعاية نهائياً.');
        }
        header('Location: ' . APP_URL . ($forcedStatusFilter === 'new' ? 'modules/sponsors/new_requests.php' : ($forcedStatusFilter === 'contacted' ? 'modules/sponsors/contacted_requests.php' : 'modules/sponsors/requests.php')));
        exit();
    }
    if ($action === 'convert' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
            $req = dbFetchOne("SELECT * FROM sponsor_requests WHERE id = ?", [$id]);
            if ($req && $req['status'] !== 'converted') {
                $gender = strtolower(trim((string)($req['gender'] ?? '')));
                if (!in_array($gender, ['male', 'female'], true)) { flash('error', t('sponsors.create_gender') . ': ' . t('sponsors.create_male') . ' / ' . t('sponsors.create_female')); header('Location: ' . APP_URL . ($forcedStatusFilter === 'new' ? 'modules/sponsors/new_requests.php' : ($forcedStatusFilter === 'contacted' ? 'modules/sponsors/contacted_requests.php' : 'modules/sponsors/requests.php'))); exit(); }
                $name = trim((string)$req['sponsor_name']);
                [$rawLetter, $normalizedLetter] = first_letter_of($name);
                $letterRow = dbFetchOne("SELECT id FROM letters WHERE is_active = 1 AND code = ? LIMIT 1", [$normalizedLetter]);
                $letterId = $letterRow ? (int)$letterRow['id'] : null;
                $supervisorId = resolveSponsorSupervisorId($name, $gender);
                $n = (int)(dbFetchOne("SELECT COUNT(*) c FROM sponsors")['c'] ?? 0) + 1; $code = 'SP-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
                dbExecute("INSERT INTO sponsors (full_name, first_letter_raw, first_letter_id, phone, sponsor_code, status, sponsor_type, gender, preferred_payment_method, acquisition_source, brought_by_user_id, created_by, supervisor_id, assigned_by, assigned_at, is_manual_override) VALUES (?, ?, ?, ?, ?, 'active', 'individual', ?, 'cash', ?, ?, ?, ?, ?, NOW(), 0)", [$name, $rawLetter, $letterId, $req['phone'], $code, $gender, $req['source'], $req['brought_by'], Session::getUserId(), $supervisorId, Session::getUserId()]);
                $newId = (int)dbLastInsertId(); if ($supervisorId) ensureActiveSponsorAssignmentHistory($newId, $supervisorId, Session::getUserId());
                dbExecute("UPDATE sponsor_requests SET status = 'converted', created_sponsor_id = ? WHERE id = ?", [$newId, $id]);
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, 'CONVERT', 'sponsor_request', ?, ?, ?)", [Session::getUserId(), $id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                flash('success', t('sponsors.request_converted_success')); header('Location: ' . APP_URL . 'modules/sponsors/view.php?id=' . $newId); exit();
            }
        }
    }
$statusFilter = $forcedStatusFilter !== '' ? $forcedStatusFilter : trim((string)($_GET['status'] ?? ''));
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = '';
}
if ($statusFilter !== '') {
    $leads = dbFetchAll("SELECT r.*, u.full_name AS brought_by_user FROM sponsor_requests r LEFT JOIN users u ON u.id = r.brought_by WHERE r.status = ? ORDER BY r.created_at DESC", [$statusFilter]);
} else {
    $leads = dbFetchAll("SELECT r.*, u.full_name AS brought_by_user FROM sponsor_requests r LEFT JOIN users u ON u.id = r.brought_by ORDER BY r.created_at DESC");
}
$statusKeys = ['new'=>'sponsors.request_new','contacted'=>'sponsors.request_contacted','converted'=>'sponsors.request_converted','lost'=>'sponsors.request_lost'];
$queueTitleKeys = [
    'new' => 'sponsors.request_queue_new',
    'contacted' => 'sponsors.request_queue_contacted',
    'converted' => 'sponsors.request_queue_converted',
    'lost' => 'sponsors.request_queue_lost',
];
$queueTitle = $statusFilter !== '' ? t($queueTitleKeys[$statusFilter]) : t('sponsors.requests_title');
include dirname(__DIR__, 2) . '/includes/header.php'; ?>
<div class="welcome-section fade-in"><h2><i class="fas fa-bullhorn me-2"></i><?php echo e($queueTitle); ?></h2><p><?php echo e(t('sponsors.requests_intro')); ?></p></div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="d-flex justify-content-end mb-3 fade-in"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeadModal"><i class="fas fa-plus me-1"></i><?php echo e(t('sponsors.request_add')); ?></button></div>
<div class="card mb-3 fade-in"><div class="card-body"><form method="get" class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label mb-1">تصفية حسب الحالة</label><select name="status" class="form-select"><option value="">كل الطلبات</option><option value="new" <?php echo $statusFilter === 'new' ? 'selected' : ''; ?>>جديد</option><option value="contacted" <?php echo $statusFilter === 'contacted' ? 'selected' : ''; ?>>تم التواصل</option><option value="converted" <?php echo $statusFilter === 'converted' ? 'selected' : ''; ?>>تم التحويل إلى كفيل</option><option value="lost" <?php echo $statusFilter === 'lost' ? 'selected' : ''; ?>>مغلق / لم يكتمل</option></select></div><div class="col-md-auto"><button type="submit" class="btn btn-outline-primary"><i class="fas fa-filter me-1"></i>تطبيق</button></div><div class="col-md-auto"><a href="<?php echo url('modules/sponsors/requests.php'); ?>" class="btn btn-outline-secondary">إظهار الكل</a></div></form></div></div>
<div class="card mb-4 fade-in"><div class="card-body p-2"><div class="table-responsive"><table class="table table-hover align-middle bg-white mb-0"><thead><tr>
<th><?php echo e(t('sponsors.request_date')); ?></th><th><?php echo e(t('sponsors.request_sponsor_name')); ?></th><th><?php echo e(t('sponsors.request_phone')); ?></th><th><?php echo e(t('sponsors.request_source')); ?></th><th><?php echo e(t('sponsors.request_brought_by')); ?></th><th><?php echo e(t('sponsors.request_status')); ?></th><th><?php echo e(t('sponsors.request_notes')); ?></th><th class="text-center"><?php echo e(t('sponsors.request_actions')); ?></th>
</tr></thead><tbody>
<?php if (!$leads): ?><tr><td colspan="8" class="text-center text-muted py-4"><?php echo e(t('sponsors.request_no_results')); ?></td></tr><?php else: foreach ($leads as $lead): $badges=['new'=>'bg-info','contacted'=>'bg-warning text-dark','converted'=>'bg-success','lost'=>'bg-secondary']; $byDisplay=trim((string)($lead['brought_by_name']??''))!==''?$lead['brought_by_name']:($lead['brought_by_user']??'—'); $sourceMeta=$sourceOptions[$lead['source']]??['icon'=>'fas fa-question-circle text-muted','key'=>'sponsors.request_source_other']; ?>
<tr><td><?php echo date('Y-m-d',strtotime($lead['created_at'])); ?></td><td><?php echo e($lead['sponsor_name']); ?></td><td dir="ltr"><?php echo e($lead['phone']??'-'); ?></td><td><i class="<?php echo e($sourceMeta['icon']); ?> me-1"></i><?php echo e(t($sourceMeta['key'])); ?></td><td><?php echo e($byDisplay); ?></td><td><span class="badge <?php echo $badges[$lead['status']]??'bg-secondary'; ?>"><?php echo e(t($statusKeys[$lead['status']]??'sponsors.request_lost')); ?></span></td><td class="small text-muted"><?php echo e($lead['notes']??''); ?></td><td class="text-center">
<?php if ($lead['status'] === 'new' || $lead['status'] === 'contacted'): ?>
<?php if ($lead['status'] === 'new' && empty($lead['assigned_supervisor_id'])): ?><form method="post" class="d-inline" onsubmit="return confirm(<?php echo e(json_encode(t('sponsors.request_delete_confirm'), JSON_UNESCAPED_UNICODE)); ?>);"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$lead['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo e(t('sponsors.request_delete')); ?>"><i class="fas fa-trash"></i> <?php echo e(t('sponsors.request_delete')); ?></button></form><?php endif; ?>
<?php if ($lead['status'] === 'new'): ?><form method="post" class="d-inline" onsubmit="return confirm(<?php echo e(json_encode(t('sponsors.request_contacted_confirm'), JSON_UNESCAPED_UNICODE)); ?>);"><?php echo csrf_field(); ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="status" value="contacted"><input type="hidden" name="id" value="<?php echo (int)$lead['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-info" title="<?php echo e(t('sponsors.request_mark_contacted')); ?>"><i class="fas fa-phone"></i> <?php echo e(t('sponsors.request_mark_contacted')); ?></button></form><?php endif; ?>
<form method="post" class="d-inline" onsubmit="return confirm(<?php echo e(json_encode(t('sponsors.request_lost_confirm'), JSON_UNESCAPED_UNICODE)); ?>);"><?php echo csrf_field(); ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="status" value="lost"><input type="hidden" name="id" value="<?php echo (int)$lead['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-secondary" title="<?php echo e(t('sponsors.request_mark_lost')); ?>"><i class="fas fa-xmark"></i> <?php echo e(t('sponsors.request_mark_lost')); ?></button></form>
<button type="button" class="btn btn-sm btn-outline-success" title="<?php echo e(t('sponsors.request_convert_title')); ?>" data-bs-toggle="modal" data-bs-target="#convertLeadModal" data-request-id="<?php echo (int)$lead['id']; ?>"><i class="fas fa-user-check"></i> <?php echo e(t('sponsors.request_convert')); ?></button>
<?php elseif ($lead['status'] === 'lost'): ?><form method="post" class="d-inline" onsubmit="return confirm(<?php echo e(json_encode(t('sponsors.request_reopen_confirm'), JSON_UNESCAPED_UNICODE)); ?>);"><?php echo csrf_field(); ?><input type="hidden" name="action" value="reopen"><input type="hidden" name="id" value="<?php echo (int)$lead['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-primary" title="<?php echo e(t('sponsors.request_reopen')); ?>"><i class="fas fa-rotate-left"></i> <?php echo e(t('sponsors.request_reopen')); ?></button></form>
<?php else: ?><span class="badge bg-light text-muted"><i class="fas fa-check-circle"></i> <?php echo e(t('sponsors.request_complete')); ?></span><?php endif; ?>
</td></tr><?php endforeach; endif; ?></tbody></table></div></div></div>
<div class="modal fade" id="addLeadModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add"><div class="modal-header" style="background:#1b4d8f;color:#fff"><h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i><?php echo e(t('sponsors.request_add_title')); ?></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="mb-3"><label class="form-label"><?php echo e(t('sponsors.request_sponsor_name')); ?> *</label><input type="text" name="sponsor_name" class="form-control" required></div><div class="mb-3"><label class="form-label"><?php echo e(t('sponsors.request_phone')); ?></label><input type="text" name="phone" class="form-control" dir="ltr"></div><div class="mb-3"><label class="form-label"><?php echo e(t('sponsors.create_gender')); ?> *</label><select name="gender" class="form-select" required><option value=""><?php echo e(t('sponsors.create_gender')); ?></option><option value="male"><?php echo e(t('sponsors.create_male')); ?></option><option value="female"><?php echo e(t('sponsors.create_female')); ?></option></select></div><div class="mb-3"><label class="form-label"><?php echo e(t('sponsors.request_source')); ?> *</label><div class="d-flex flex-wrap gap-2"><?php foreach ($sourceOptions as $val=>$meta): $sid='src_'.md5($val); ?><input type="radio" class="btn-check" name="source" id="<?php echo $sid; ?>" value="<?php echo e($val); ?>" required><label class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" for="<?php echo $sid; ?>"><i class="<?php echo e($meta['icon']); ?>"></i><span><?php echo e(t($meta['key'])); ?></span></label><?php endforeach; ?></div></div>
<div class="mb-3"><label class="form-label"><?php echo e(t('sponsors.request_brought_by')); ?></label><input type="text" name="brought_by_name" class="form-control" value="<?php echo e($currentUserName); ?>"><div class="form-text"><?php echo e(t('sponsors.request_default_brought_by')); ?></div></div><div class="mb-3"><label class="form-label"><?php echo e(t('sponsors.request_notes')); ?></label><textarea name="notes" class="form-control" rows="2"></textarea></div></div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo e(t('sponsors.request_cancel')); ?></button><button type="submit" class="btn btn-primary"><?php echo e(t('sponsors.request_save')); ?></button></div></form></div></div></div>

<div class="container-fluid px-3 pb-4"><div class="d-flex justify-content-start"><a href="<?php echo APP_URL . 'modules/sponsors/index.php'; ?>" class="btn btn-outline-secondary" onclick="return akGoBack(this.href);"><i class="fa-solid fa-arrow-right me-1"></i> العودة</a></div></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>