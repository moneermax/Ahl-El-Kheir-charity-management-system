<?php
// modules/families/suspensions.php - Sponsorship suspension workflow (Mothers Affairs)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager', 'nanny'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = t('تعليق الكفالات');
$active    = 'suspensions';
$uid = Session::getUserId();
$canLift = in_array($role, ['admin', 'vice_general_manager'], true);

$reasons = [
    'age_out'      => 'بلوغ السن القانوني',
    'false_info'   => 'معلومات غير صحيحة عند التقديم',
    'other_support'=> 'وجود معيل/كفيل آخر من الأقارب',
    'other'        => 'سبب آخر',
];

/* ══════════ schema sync ══════════ */
try {
    dbExecute("CREATE TABLE IF NOT EXISTS suspension_cases (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entity_type ENUM('family','child') NOT NULL,
        entity_id INT UNSIGNED NOT NULL,
        reason VARCHAR(30) NOT NULL,
        notes TEXT,
        suspended_by INT UNSIGNED NULL,
        suspended_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active','lifted') NOT NULL DEFAULT 'active',
        lifted_by INT UNSIGNED NULL,
        lifted_at DATETIME NULL,
        KEY idx_sc_entity (entity_type, entity_id),
        FOREIGN KEY (suspended_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (lifted_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) { error_log('Suspensions schema sync: ' . $e->getMessage()); }

function susp_audit(int $uid, string $action, int $caseId, array $new): void {
    try {
        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, 'suspensions', ?, NULL, ?, ?, ?)",
            [$uid, $action, $caseId, json_encode($new, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (Throwable $e) {}
}

function family_id_of(string $type, int $eid): ?int {
    if ($type === 'family') {
        $f = dbFetchOne("SELECT id FROM families WHERE id = ?", [$eid]);
        return $f ? (int)$f['id'] : null;
    }
    $c = dbFetchOne("SELECT family_id FROM family_children WHERE id = ?", [$eid]);
    return $c ? (int)$c['family_id'] : null;
}

/* ══════════ POST actions ══════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {

    /* ---- create suspension ---- */
    if (isset($_POST['create_case'])) {
        $type   = ($_POST['entity_type'] ?? '') === 'child' ? 'child' : 'family';
        $eid    = (int)($_POST['entity_id'] ?? 0);
        $reason = isset($reasons[$_POST['reason'] ?? '']) ? $_POST['reason'] : '';
        $notes  = trim($_POST['notes'] ?? '');
        $famId  = family_id_of($type, $eid);

        if (!$eid || !$famId) flash('error', 'اختر الأسرة أو الطفل أولاً.');
        elseif (!$reason) flash('error', 'اختر سبب التعليق.');
        elseif ($role === 'nanny') {
            $own = dbFetchOne("SELECT id FROM families WHERE id = ? AND nanny_id = ?", [$famId, $uid]);
            if (!$own) flash('error', 'هذه الأسرة ليست ضمن نطاقك.');
        }

        if (!flash_has_error()) {
            $dup = dbFetchOne("SELECT id FROM suspension_cases WHERE entity_type = ? AND entity_id = ? AND status = 'active'", [$type, $eid]);
            if ($dup) flash('error', 'يوجد تعليق نشط بالفعل على هذا السجل.');
        }

        if (!flash_has_error()) {
            dbExecute("INSERT INTO suspension_cases (entity_type, entity_id, reason, notes, suspended_by) VALUES (?, ?, ?, ?, ?)",
                [$type, $eid, $reason, $notes !== '' ? $notes : null, $uid]);
            $caseId = (int)dbFetchOne("SELECT LAST_INSERT_ID() id")['id'];
            $marker = 'تعليق [CASE#' . $caseId . '] ' . $reasons[$reason];

            if ($type === 'child') {
                dbExecute("UPDATE family_children SET is_active = 0, disqualified_at = CURDATE(), disqualification_reason = ? WHERE id = ?", [$reason, $eid]);
                dbExecute("UPDATE sponsorships sp JOIN sponsorship_children sc ON sc.sponsorship_id = sp.id
                    SET sp.status = 'paused', sp.pause_reason = ?, sp.pause_start_date = CURDATE(), sp.updated_at = NOW()
                    WHERE sc.family_child_id = ? AND sp.status = 'active'", [$marker, $eid]);
            } else {
                dbExecute("UPDATE families SET status = 'paused', updated_at = NOW() WHERE id = ?", [$eid]);
                dbExecute("UPDATE sponsorships SET status = 'paused', pause_reason = ?, pause_start_date = CURDATE(), updated_at = NOW()
                    WHERE family_id = ? AND status = 'active'", [$marker, $eid]);
            }
            susp_audit($uid, 'SUSPEND', $caseId, ['type' => $type, 'entity_id' => $eid, 'reason' => $reason]);
            flash('success', 'تم تنفيذ التعليق وإيقاف الكفالات المرتبطة.');
        }
        header('Location: ' . APP_URL . 'modules/families/suspensions.php'); exit();
    }

    /* ---- lift suspension (admin/vgm only) ---- */
    if (isset($_POST['lift_case'])) {
        if (!$canLift) { flash('error', 'ليس لديك صلاحية رفع التعليق.'); }
        else {
            $cid = (int)$_POST['lift_case'];
            $c = dbFetchOne("SELECT * FROM suspension_cases WHERE id = ? AND status = 'active'", [$cid]);
            if ($c) {
                dbExecute("UPDATE suspension_cases SET status = 'lifted', lifted_by = ?, lifted_at = NOW() WHERE id = ?", [$uid, $cid]);
                if ($c['entity_type'] === 'child') {
                    dbExecute("UPDATE family_children SET is_active = 1, disqualified_at = NULL, disqualification_reason = NULL WHERE id = ?", [$c['entity_id']]);
                } else {
                    dbExecute("UPDATE families SET status = 'active', updated_at = NOW() WHERE id = ? AND status = 'paused'", [$c['entity_id']]);
                }
                $like = '%[CASE#' . $cid . ']%';
                dbExecute("UPDATE sponsorships SET status = 'active', pause_end_date = CURDATE(), updated_at = NOW()
                    WHERE status = 'paused' AND pause_reason LIKE ?", [$like]);
                susp_audit($uid, 'LIFT', $cid, ['type' => $c['entity_type'], 'entity_id' => $c['entity_id']]);
                flash('success', 'تم رفع التعليق واستئناف الكفالات المرتبطة.');
            }
        }
        header('Location: ' . APP_URL . 'modules/families/suspensions.php'); exit();
    }
}

function flash_has_error(): bool {
    $f = $_SESSION['flash'] ?? [];
    foreach ($f as $x) if (($x['type'] ?? '') === 'error') return true;
    return false;
}

/* ══════════ form data ══════════ */
$type = ($_POST['entity_type'] ?? $_GET['type'] ?? '') === 'child' ? 'child' : 'family';
$selFamily = (int)($_POST['family_id'] ?? $_GET['family'] ?? 0);
$preId = (int)($_GET['id'] ?? 0);
if ($preId && ($type === 'child')) {
    $fid = family_id_of('child', $preId);
    if ($fid) $selFamily = $fid;
}

if ($role === 'nanny') {
    $familyOpts = dbFetchAll("SELECT id, family_code, mother_name FROM families WHERE nanny_id = ? ORDER BY mother_name", [$uid]);
} else {
    $familyOpts = dbFetchAll("SELECT id, family_code, mother_name FROM families WHERE status NOT IN ('closed','archived') ORDER BY mother_name LIMIT 1000");
}
$childOpts = $selFamily
    ? dbFetchAll("SELECT id, child_name, is_active FROM family_children WHERE family_id = ? ORDER BY child_name", [$selFamily])
    : [];

/* ══════════ cases list ══════════ */
$scopeWhere = ''; $params = [];
if ($role === 'nanny') { $scopeWhere = " AND COALESCE(f.nanny_id, f2.nanny_id) = ?"; $params[] = $uid; }
$cases = dbFetchAll("SELECT c.*, u.full_name by_name, lb.full_name lifted_name,
    COALESCE(f.mother_name, fc.child_name) AS entity_name,
    COALESCE(f.family_code, f2.family_code) AS family_code
    FROM suspension_cases c
    LEFT JOIN families f ON f.id = c.entity_id AND c.entity_type = 'family'
    LEFT JOIN family_children fc ON fc.id = c.entity_id AND c.entity_type = 'child'
    LEFT JOIN families f2 ON f2.id = fc.family_id
    LEFT JOIN users u ON u.id = c.suspended_by
    LEFT JOIN users lb ON lb.id = c.lifted_by
    WHERE 1=1 $scopeWhere
    ORDER BY (c.status = 'active') DESC, c.id DESC LIMIT 300", $params);

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-hand me-2"></i><?php echo t('تعليق الكفالات'); ?></h2>
    <p><?php echo t('تعليق كفالة طفل أو أسرة لأسباب: بلوغ السن، معلومات غير صحيحة، وجود معيل آخر'); ?></p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<?php if ($role !== 'general_manager'): ?>
<div class="card mb-4 fade-in">
    <div class="card-header"><i class="fas fa-plus me-2"></i><?php echo t('تعليق جديد'); ?></div>
    <div class="card-body">
        <form method="post">
            <?php echo csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label"><?php echo t('نوع التعليق'); ?></label>
                    <select name="entity_type" class="form-select" onchange="this.form.submit()">
                        <option value="family" <?php echo $type === 'family' ? 'selected' : ''; ?>><?php echo t('تعليق أسرة كاملة'); ?></option>
                        <option value="child" <?php echo $type === 'child' ? 'selected' : ''; ?>><?php echo t('تعليق طفل محدد'); ?></option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo t('الأسرة'); ?></label>
                    <select name="family_id" class="form-select" onchange="this.form.submit()">
                        <option value="">— اختر —</option>
                        <?php foreach ($familyOpts as $f): ?>
                        <option value="<?php echo (int)$f['id']; ?>" <?php echo $selFamily === (int)$f['id'] ? 'selected' : ''; ?>><?php echo e($f['mother_name']); ?> (<?php echo e($f['family_code']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($type === 'child'): ?>
                <div class="col-md-3">
                    <label class="form-label"><?php echo t('الطفل'); ?></label>
                    <select name="entity_id" class="form-select" required>
                        <option value="">— اختر —</option>
                        <?php foreach ($childOpts as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>" <?php echo $preId === (int)$c['id'] ? 'selected' : ''; ?>><?php echo e($c['child_name']); ?><?php echo $c['is_active'] ? '' : ' (معلق)'; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="entity_id" value="<?php echo $selFamily; ?>">
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label"><?php echo t('السبب'); ?> *</label>
                    <select name="reason" class="form-select" required>
                        <?php foreach ($reasons as $k => $label): ?>
                        <option value="<?php echo $k; ?>"><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label"><?php echo t('ملاحظات'); ?></label><input type="text" name="notes" class="form-control"></div>
                <div class="col-md-3 d-flex align-items-end">
                    <button name="create_case" value="1" class="btn btn-danger w-100" onclick="return confirm('تنفيذ التعليق وإيقاف الكفالات المرتبطة؟')"><i class="fas fa-hand me-1"></i><?php echo t('تنفيذ التعليق'); ?></button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card fade-in">
    <div class="card-header"><i class="fas fa-list me-2"></i><?php echo t('سجل التعليقات'); ?> (<?php echo count($cases); ?>)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark"><tr><th>#</th><th><?php echo t('النوع'); ?></th><th><?php echo t('الاسم'); ?></th><th><?php echo t('الكود'); ?></th><th><?php echo t('السبب'); ?></th><th><?php echo t('علّق بواسطة'); ?></th><th><?php echo t('التاريخ'); ?></th><th><?php echo t('الحالة'); ?></th><?php if ($canLift): ?><th class="text-center"><?php echo t('إجراء'); ?></th><?php endif; ?></tr></thead>
                <tbody>
                <?php if (!$cases): ?>
                <tr><td colspan="9" class="text-center text-muted py-4"><?php echo t('لا توجد تعليقات مسجلة.'); ?></td></tr>
                <?php else: foreach ($cases as $c): ?>
                <tr>
                    <td><?php echo (int)$c['id']; ?></td>
                    <td><?php echo $c['entity_type'] === 'child' ? t('تعليق طفل محدد') : t('تعليق أسرة كاملة'); ?></td>
                    <td><strong><?php echo e($c['entity_name'] ?? '—'); ?></strong></td>
                    <td><small class="text-muted"><?php echo e($c['family_code'] ?? '—'); ?></small></td>
                    <td><?php echo e($reasons[$c['reason']] ?? $c['reason']); ?><?php echo !empty($c['notes']) ? '<br><small class="text-muted">' . e($c['notes']) . '</small>' : ''; ?></td>
                    <td><?php echo e($c['by_name'] ?? '—'); ?></td>
                    <td><small><?php echo e($c['suspended_at']); ?></small></td>
                    <td><?php echo $c['status'] === 'active'
                        ? '<span class="badge bg-danger">' . t('معلق') . '</span>'
                        : '<span class="badge bg-success">' . t('مرفوع') . '</span> <small class="text-muted">(' . e($c['lifted_name'] ?? '') . ')</small>'; ?></td>
                    <?php if ($canLift): ?>
                    <td class="text-center">
                        <?php if ($c['status'] === 'active'): ?>
                        <form method="post" class="d-inline"><?php echo csrf_field(); ?>
                            <button name="lift_case" value="<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('رفع التعليق واستئناف الكفالات؟')"><i class="fas fa-rotate-left me-1"></i><?php echo t('رفع التعليق'); ?></button>
                        </form>
                        <?php else: echo '—'; endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>