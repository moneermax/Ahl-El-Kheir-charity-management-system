<<<<<<< HEAD
<?php
// modules/administration/winback.php - Lapsed sponsor follow-ups (Lost & Found dept)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager', 'administration'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = t('متابعات الاسترجاع');
$active    = 'winback';
$uid = Session::getUserId();

/* ══════════ schema sync (soft policy: no triggers, no blacklist) ══════════ */
try {
    dbExecute("DROP TRIGGER IF EXISTS trg_sponsorship_blacklist_insert");
    dbExecute("DROP TRIGGER IF EXISTS trg_sponsorship_blacklist_update");
    dbExecute("DROP TABLE IF EXISTS sponsor_family_blacklist");
    dbExecute("CREATE TABLE IF NOT EXISTS winback_campaigns (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sponsor_id INT UNSIGNED NOT NULL,
        handled_by INT UNSIGNED NULL,
        status ENUM('open','contacted','persuaded','declined') NOT NULL DEFAULT 'open',
        opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_contact_at DATETIME NULL,
        closed_at DATETIME NULL,
        notes TEXT,
        FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE,
        FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dbExecute("CREATE TABLE IF NOT EXISTS winback_contacts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT UNSIGNED NOT NULL,
        contacted_by INT UNSIGNED NULL,
        contact_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        method VARCHAR(30) NOT NULL DEFAULT 'phone',
        outcome VARCHAR(50) NOT NULL DEFAULT 'no_answer',
        notes TEXT,
        FOREIGN KEY (campaign_id) REFERENCES winback_campaigns(id) ON DELETE CASCADE,
        FOREIGN KEY (contacted_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) { error_log('Winback schema sync: ' . $e->getMessage()); }

function wb_audit(int $uid, string $action, int $entityId, array $new): void {
    try {
        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, 'winback', ?, NULL, ?, ?, ?)",
            [$uid, $action, $entityId, json_encode($new, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (Throwable $e) {}
}

$caseStatus = [
    'open'      => ['مفتوحة', 'bg-warning text-dark'],
    'contacted' => ['تم التواصل', 'bg-info text-dark'],
    'persuaded' => ['عاد للكفالة', 'bg-success'],
    'declined'  => ['اعتذر عن العودة', 'bg-danger'],
];

/* ══════════ POST actions ══════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {

    if (isset($_POST['open_case'])) {
        $sid = (int)$_POST['open_case'];
        if (!dbFetchOne("SELECT id FROM winback_campaigns WHERE sponsor_id = ? AND status IN ('open','contacted')", [$sid])) {
            dbExecute("INSERT INTO winback_campaigns (sponsor_id, handled_by) VALUES (?, ?)", [$sid, $uid]);
            wb_audit($uid, 'OPEN', $sid, []);
            flash('success', t('تم بدء المتابعة.'));
        }
        header('Location: ' . APP_URL . 'modules/administration/winback.php'); exit();
    }

    if (isset($_POST['add_contact'])) {
        $cid = (int)$_POST['add_contact'];
        $method  = in_array($_POST['method'] ?? '', ['phone','whatsapp','visit'], true) ? $_POST['method'] : 'phone';
        $outcome = trim($_POST['outcome'] ?? '') ?: 'no_answer';
        $notes   = trim($_POST['notes'] ?? '');
        dbExecute("INSERT INTO winback_contacts (campaign_id, contacted_by, method, outcome, notes) VALUES (?, ?, ?, ?, ?)",
            [$cid, $uid, $method, $outcome, $notes !== '' ? $notes : null]);
        dbExecute("UPDATE winback_campaigns SET last_contact_at = NOW(), status = IF(status='open','contacted',status), handled_by = ? WHERE id = ?", [$uid, $cid]);
        wb_audit($uid, 'CONTACT', $cid, ['method' => $method, 'outcome' => $outcome]);
        flash('success', t('تم تسجيل محاولة التواصل.'));
        header('Location: ' . APP_URL . 'modules/administration/winback.php?view=' . $cid); exit();
    }

    if (isset($_POST['mark_returned'])) {
        $cid = (int)$_POST['mark_returned'];
        $c = dbFetchOne("SELECT * FROM winback_campaigns WHERE id = ?", [$cid]);
        if ($c && in_array($c['status'], ['open','contacted'], true)) {
            dbExecute("UPDATE sponsors SET status = 'active', updated_at = NOW() WHERE id = ?", [$c['sponsor_id']]);
            dbExecute("UPDATE winback_campaigns SET status='persuaded', closed_at=NOW(), handled_by=? WHERE id = ?", [$uid, $cid]);
            wb_audit($uid, 'RETURNED', $cid, ['sponsor_id' => $c['sponsor_id']]);
            flash('success', t('تمت إعادة تفعيل الكفيل. أسرته السابقة ستظهر مميزة عند التكليف الجديد.'));
        }
        header('Location: ' . APP_URL . 'modules/administration/winback.php'); exit();
    }

    if (isset($_POST['mark_declined'])) {
        $cid = (int)$_POST['mark_declined'];
        dbExecute("UPDATE winback_campaigns SET status='declined', closed_at=NOW(), handled_by=? WHERE id = ?", [$uid, $cid]);
        wb_audit($uid, 'DECLINED', $cid, []);
        flash('success', t('تم إغلاق المتابعة (اعتذر عن العودة).'));
        header('Location: ' . APP_URL . 'modules/administration/winback.php'); exit();
    }
}

/* ══════════ data ══════════ */
$viewId = (int)($_GET['view'] ?? 0);
$view = null; $contacts = []; $pastFamilies = [];
if ($viewId) {
    $view = dbFetchOne("SELECT wc.*, s.full_name sponsor_name, s.sponsor_code, s.phone sponsor_phone, s.status sponsor_status, u.full_name handler_name
        FROM winback_campaigns wc JOIN sponsors s ON s.id = wc.sponsor_id LEFT JOIN users u ON u.id = wc.handled_by WHERE wc.id = ?", [$viewId]);
    if ($view) {
        $contacts = dbFetchAll("SELECT c.*, u.full_name by_name FROM winback_contacts c LEFT JOIN users u ON u.id = c.contacted_by WHERE c.campaign_id = ? ORDER BY c.id DESC", [$viewId]);
        $pastFamilies = dbFetchAll("SELECT f.id, f.family_code, f.mother_name,
            (SELECT COUNT(*) FROM sponsorships x WHERE x.family_id = f.id AND x.status = 'active') active_now
            FROM sponsorships sp JOIN families f ON f.id = sp.family_id
            WHERE sp.sponsor_id = ? GROUP BY f.id ORDER BY f.mother_name", [$view['sponsor_id']]);
    }
}

// Eligible lapsed sponsors (stopped > 90 days, no open follow-up yet)
$queue = dbFetchAll("SELECT s.id, s.full_name, s.phone, s.status, s.sponsor_code,
    (SELECT COUNT(*) FROM sponsorships sp WHERE sp.sponsor_id = s.id) total_ships,
    (SELECT MAX(COALESCE(sp.pause_start_date, sp.end_date, sp.updated_at)) FROM sponsorships sp WHERE sp.sponsor_id = s.id AND sp.status IN ('cancelled','paused')) last_stop
    FROM sponsors s
    WHERE s.status IN ('inactive','suspended','cancelled')
      AND NOT EXISTS (SELECT 1 FROM winback_campaigns wc WHERE wc.sponsor_id = s.id AND wc.status IN ('open','contacted'))
    HAVING (last_stop IS NULL OR last_stop < DATE_SUB(NOW(), INTERVAL 90 DAY))
    ORDER BY last_stop ASC");

// Quick reference: top families currently available for assignment
$uncovered = dbFetchAll("SELECT f.id, f.family_code, f.mother_name, f.children_count, f.monthly_need_amount, f.city
    FROM families f
    WHERE f.status IN ('active','pending')
      AND NOT EXISTS (SELECT 1 FROM sponsorships sp WHERE sp.family_id = f.id AND sp.status = 'active')
    ORDER BY f.children_count DESC, f.monthly_need_amount DESC
    LIMIT 10");

$cases = dbFetchAll("SELECT wc.*, s.full_name sponsor_name, u.full_name handler_name
    FROM winback_campaigns wc JOIN sponsors s ON s.id = wc.sponsor_id LEFT JOIN users u ON u.id = wc.handled_by
    ORDER BY (wc.status IN ('open','contacted')) DESC, wc.id DESC LIMIT 200");

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-rotate-left me-2"></i><?php echo t('متابعات الاسترجاع'); ?></h2>
    <p><?php echo t('متابعة الكفلاء المتوقفين منذ أكثر من 3 أشهر ومحاولة إعادتهم للكفالة'); ?></p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<?php if ($view): ?>
<!-- ══════════ Follow-up details ══════════ -->
<div class="card mb-4 fade-in">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file-lines me-2"></i><?php echo e($view['sponsor_name']); ?> — <?php echo e($view['sponsor_code']); ?></span>
        <span>
            <?php [$sl, $sc] = $caseStatus[$view['status']] ?? [$view['status'], 'bg-secondary']; ?>
            <span class="badge <?php echo $sc; ?>"><?php echo $sl; ?></span>
            <a href="<?php echo APP_URL; ?>modules/administration/winback.php" class="btn btn-sm btn-secondary ms-2"><i class="fas fa-arrow-right"></i> <?php echo t('رجوع'); ?></a>
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="border rounded p-2 text-center"><small class="text-muted d-block"><?php echo t('الهاتف'); ?></small><strong dir="ltr"><?php echo e($view['sponsor_phone'] ?? '—'); ?></strong></div></div>
            <div class="col-md-3"><div class="border rounded p-2 text-center"><small class="text-muted d-block"><?php echo t('المسؤول عن المتابعة'); ?></small><strong><?php echo e($view['handler_name'] ?? '—'); ?></strong></div></div>
            <div class="col-md-3"><div class="border rounded p-2 text-center"><small class="text-muted d-block"><?php echo t('آخر تواصل'); ?></small><strong><?php echo e($view['last_contact_at'] ?? '—'); ?></strong></div></div>
            <div class="col-md-3"><div class="border rounded p-2 text-center"><small class="text-muted d-block"><?php echo t('بدأت في'); ?></small><strong><?php echo e($view['opened_at']); ?></strong></div></div>
        </div>

        <h6 class="mb-2"><i class="fas fa-history me-2 text-warning"></i><?php echo t('الأسر السابقة لهذا الكفيل'); ?></h6>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-hover align-middle">
                <thead><tr><th><?php echo t('الكود'); ?></th><th><?php echo t('اسم الأم'); ?></th><th><?php echo t('الحالة'); ?></th></tr></thead>
                <tbody>
                <?php if (!$pastFamilies): ?>
                <tr><td colspan="3" class="text-center text-muted py-3"><?php echo t('لا توجد كفالات سابقة.'); ?></td></tr>
                <?php else: foreach ($pastFamilies as $f): ?>
                <tr>
                    <td><?php echo e($f['family_code']); ?></td>
                    <td><?php echo e($f['mother_name']); ?></td>
                    <td><?php echo ((int)$f['active_now'] === 0)
                        ? '<span class="badge bg-success">' . t('متاحة حالياً') . '</span>'
                        : '<span class="badge bg-secondary">' . t('مكفولة حالياً') . '</span>'; ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small"><i class="fas fa-circle-info me-1"></i><?php echo t('عند تكليف الكفيل بعد عودته، تظهر أسره السابقة بلون مميز في صفحة إنشاء الكفالة ولا يتم منعها.'); ?></p>

        <h6 class="mb-2"><i class="fas fa-phone me-2 text-primary"></i><?php echo t('سجل التواصل'); ?></h6>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-hover align-middle">
                <thead><tr><th><?php echo t('التاريخ'); ?></th><th><?php echo t('بواسطة'); ?></th><th><?php echo t('طريقة التواصل'); ?></th><th><?php echo t('النتيجة'); ?></th><th><?php echo t('ملاحظات'); ?></th></tr></thead>
                <tbody>
                <?php if (!$contacts): ?>
                <tr><td colspan="5" class="text-center text-muted py-3"><?php echo t('لا توجد محاولات مسجلة.'); ?></td></tr>
                <?php else: foreach ($contacts as $c): ?>
                <tr>
                    <td><small><?php echo e($c['contact_date']); ?></small></td>
                    <td><?php echo e($c['by_name'] ?? '—'); ?></td>
                    <td><?php echo e($c['method']); ?></td>
                    <td><?php echo e($c['outcome']); ?></td>
                    <td><?php echo e($c['notes'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (in_array($view['status'], ['open','contacted'], true)): ?>
        <div class="row g-3">
            <div class="col-md-8">
                <form method="post" class="row g-2 align-items-end border rounded p-3">
                    <?php echo csrf_field(); ?>
                    <div class="col-md-2">
                        <label class="form-label"><?php echo t('طريقة التواصل'); ?></label>
                        <select name="method" class="form-select">
                            <option value="phone"><?php echo t('هاتفياً'); ?></option>
                            <option value="whatsapp"><?php echo t('واتساب'); ?></option>
                            <option value="visit"><?php echo t('زيارة'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?php echo t('النتيجة'); ?></label>
                        <select name="outcome" class="form-select">
                            <option value="no_answer"><?php echo t('لا رد'); ?></option>
                            <option value="reached"><?php echo t('تم الوصول'); ?></option>
                            <option value="promised"><?php echo t('وعد بالعودة'); ?></option>
                            <option value="refused"><?php echo t('اعتذر عن العودة'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-5"><label class="form-label"><?php echo t('ملاحظات'); ?></label><input type="text" name="notes" class="form-control"></div>
                    <div class="col-md-2"><button name="add_contact" value="<?php echo (int)$view['id']; ?>" class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i><?php echo t('تسجيل'); ?></button></div>
                </form>
            </div>
            <div class="col-md-4 d-flex gap-2 align-items-end">
                <form method="post" class="flex-fill"><?php echo csrf_field(); ?>
                    <button name="mark_returned" value="<?php echo (int)$view['id']; ?>" class="btn btn-success w-100" onclick="return confirm('إعادة تفعيل الكفيل؟')"><i class="fas fa-user-check me-1"></i><?php echo t('تأكيد العودة وتفعيل الكفيل'); ?></button>
                </form>
                <form method="post" class="flex-fill"><?php echo csrf_field(); ?>
                    <button name="mark_declined" value="<?php echo (int)$view['id']; ?>" class="btn btn-danger w-100" onclick="return confirm('إغلاق المتابعة؟')"><i class="fas fa-user-xmark me-1"></i><?php echo t('اعتذر عن العودة'); ?></button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<div class="row g-4 mb-4">
    <!-- ══════════ Queue ══════════ -->
    <div class="col-lg-8">
        <div class="card fade-in h-100">
            <div class="card-header"><i class="fas fa-hourglass-half me-2"></i><?php echo t('الكفلاء المتوقفون المؤهلون'); ?> (<?php echo count($queue); ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark"><tr><th><?php echo t('الكفيل'); ?></th><th><?php echo t('الهاتف'); ?></th><th><?php echo t('كفالات سابقة'); ?></th><th><?php echo t('تاريخ التوقف'); ?></th><th class="text-center"><?php echo t('إجراء'); ?></th></tr></thead>
                        <tbody>
                        <?php if (!$queue): ?>
                        <tr><td colspan="5" class="text-center text-success py-4"><i class="fas fa-check-circle me-2"></i><?php echo t('لا يوجد كفلاء مؤهلون حالياً.'); ?></td></tr>
                        <?php else: foreach ($queue as $q): ?>
                        <tr>
                            <td><strong><?php echo e($q['full_name']); ?></strong> <small class="text-muted">(<?php echo e($q['sponsor_code']); ?>)</small></td>
                            <td dir="ltr"><?php echo e($q['phone'] ?? '—'); ?></td>
                            <td><?php echo (int)$q['total_ships']; ?></td>
                            <td><?php echo e($q['last_stop'] ?? '—'); ?></td>
                            <td class="text-center">
                                <form method="post" class="d-inline"><?php echo csrf_field(); ?>
                                    <button name="open_case" value="<?php echo (int)$q['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-folder-open me-1"></i><?php echo t('بدء متابعة'); ?></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ Available families quick reference ══════════ -->
    <div class="col-lg-4">
        <div class="card fade-in h-100 border-success">
            <div class="card-header bg-success text-white"><i class="fas fa-house-circle-check me-2"></i><?php echo t('أسر متاحة للتكليف حالياً'); ?></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th><?php echo t('اسم الأم'); ?></th><th class="text-center"><?php echo t('الأطفال'); ?></th><th class="text-end"><?php echo t('الاحتياج الشهري'); ?></th></tr></thead>
                        <tbody>
                        <?php if (!$uncovered): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3"><?php echo t('لا توجد أسر متاحة.'); ?></td></tr>
                        <?php else: foreach ($uncovered as $uf): ?>
                        <tr>
                            <td><strong><?php echo e($uf['mother_name']); ?></strong><br><small class="text-muted"><?php echo e($uf['family_code']); ?><?php echo !empty($uf['city']) ? ' — ' . e($uf['city']) : ''; ?></small></td>
                            <td class="text-center"><span class="badge bg-warning text-dark"><?php echo (int)$uf['children_count']; ?></span></td>
                            <td class="text-end"><?php echo number_format((float)$uf['monthly_need_amount'], 0); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer small text-muted">
                <i class="fas fa-circle-info me-1"></i><?php echo t('مرجع سريع أثناء المكالمات — القائمة الكاملة في مركز التقارير.'); ?>
                <a href="<?php echo APP_URL; ?>modules/reports/index.php?tab=orphaned" class="ms-1 fw-bold" style="color:#1b4d8f"><?php echo t('القائمة الكاملة'); ?></a>
            </div>
        </div>
    </div>
</div>

<!-- ══════════ Follow-ups log ══════════ -->
<div class="card fade-in">
    <div class="card-header"><i class="fas fa-list me-2"></i><?php echo t('سجل المتابعات'); ?> (<?php echo count($cases); ?>)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark"><tr><th>#</th><th><?php echo t('الكفيل'); ?></th><th><?php echo t('المسؤول'); ?></th><th><?php echo t('آخر تواصل'); ?></th><th><?php echo t('الحالة'); ?></th><th class="text-center"><?php echo t('عرض'); ?></th></tr></thead>
                <tbody>
                <?php if (!$cases): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo t('لا توجد متابعات بعد.'); ?></td></tr>
                <?php else: foreach ($cases as $c): ?>
                <tr>
                    <td><?php echo (int)$c['id']; ?></td>
                    <td><strong><?php echo e($c['sponsor_name']); ?></strong></td>
                    <td><?php echo e($c['handler_name'] ?? '—'); ?></td>
                    <td><small><?php echo e($c['last_contact_at'] ?? '—'); ?></small></td>
                    <td><?php [$sl, $sc] = $caseStatus[$c['status']] ?? [$c['status'], 'bg-secondary']; ?><span class="badge <?php echo $sc; ?>"><?php echo $sl; ?></span></td>
                    <td class="text-center"><a href="?view=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
=======
<?php
// modules/administration/winback.php - Lapsed sponsor follow-ups (Lost & Found dept)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'general_manager', 'vice_general_manager', 'administration'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$pageTitle = t('متابعات الاسترجاع');
$active    = 'winback';
$uid = Session::getUserId();

/* ══════════ schema sync (soft policy: no triggers, no blacklist) ══════════ */
try {
    dbExecute("DROP TRIGGER IF EXISTS trg_sponsorship_blacklist_insert");
    dbExecute("DROP TRIGGER IF EXISTS trg_sponsorship_blacklist_update");
    dbExecute("DROP TABLE IF EXISTS sponsor_family_blacklist");
    dbExecute("CREATE TABLE IF NOT EXISTS winback_campaigns (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sponsor_id INT UNSIGNED NOT NULL,
        handled_by INT UNSIGNED NULL,
        status ENUM('open','contacted','persuaded','declined') NOT NULL DEFAULT 'open',
        opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_contact_at DATETIME NULL,
        closed_at DATETIME NULL,
        notes TEXT,
        FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE CASCADE,
        FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    dbExecute("CREATE TABLE IF NOT EXISTS winback_contacts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT UNSIGNED NOT NULL,
        contacted_by INT UNSIGNED NULL,
        contact_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        method VARCHAR(30) NOT NULL DEFAULT 'phone',
        outcome VARCHAR(50) NOT NULL DEFAULT 'no_answer',
        notes TEXT,
        FOREIGN KEY (campaign_id) REFERENCES winback_campaigns(id) ON DELETE CASCADE,
        FOREIGN KEY (contacted_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) { error_log('Winback schema sync: ' . $e->getMessage()); }

function wb_audit(int $uid, string $action, int $entityId, array $new): void {
    try {
        dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, 'winback', ?, NULL, ?, ?, ?)",
            [$uid, $action, $entityId, json_encode($new, JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (Throwable $e) {}
}

$caseStatus = [
    'open'      => ['مفتوحة', 'bg-warning text-dark'],
    'contacted' => ['تم التواصل', 'bg-info text-dark'],
    'persuaded' => ['عاد للكفالة', 'bg-success'],
    'declined'  => ['اعتذر عن العودة', 'bg-danger'],
];

/* ══════════ POST actions ══════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {

    if (isset($_POST['open_case'])) {
        $sid = (int)$_POST['open_case'];
        if (!dbFetchOne("SELECT id FROM winback_campaigns WHERE sponsor_id = ? AND status IN ('open','contacted')", [$sid])) {
            dbExecute("INSERT INTO winback_campaigns (sponsor_id, handled_by) VALUES (?, ?)", [$sid, $uid]);
            wb_audit($uid, 'OPEN', $sid, []);
            flash('success', t('تم بدء المتابعة.'));
        }
        header('Location: ' . APP_URL . 'modules/administration/winback.php'); exit();
    }

    if (isset($_POST['add_contact'])) {
        $cid = (int)$_POST['add_contact'];
        $method  = in_array($_POST['method'] ?? '', ['phone','whatsapp','visit'], true) ? $_POST['method'] : 'phone';
        $outcome = trim($_POST['outcome'] ?? '') ?: 'no_answer';
        $notes   = trim($_POST['notes'] ?? '');
        dbExecute("INSERT INTO winback_contacts (campaign_id, contacted_by, method, outcome, notes) VALUES (?, ?, ?, ?, ?)",
            [$cid, $uid, $method, $outcome, $notes !== '' ? $notes : null]);
        dbExecute("UPDATE winback_campaigns SET last_contact_at = NOW(), status = IF(status='open','contacted',status), handled_by = ? WHERE id = ?", [$uid, $cid]);
        wb_audit($uid, 'CONTACT', $cid, ['method' => $method, 'outcome' => $outcome]);
        flash('success', t('تم تسجيل محاولة التواصل.'));
        header('Location: ' . APP_URL . 'modules/administration/winback.php?view=' . $cid); exit();
    }

    if (isset($_POST['mark_returned'])) {
        $cid = (int)$_POST['mark_returned'];
        $c = dbFetchOne("SELECT * FROM winback_campaigns WHERE id = ?", [$cid]);
        if ($c && in_array($c['status'], ['open','contacted'], true)) {
            dbExecute("UPDATE sponsors SET status = 'active', updated_at = NOW() WHERE id = ?", [$c['sponsor_id']]);
            dbExecute("UPDATE winback_campaigns SET status='persuaded', closed_at=NOW(), handled_by=? WHERE id = ?", [$uid, $cid]);
            wb_audit($uid, 'RETURNED', $cid, ['sponsor_id' => $c['sponsor_id']]);
            flash('success', t('تمت إعادة تفعيل الكفيل. أسرته السابقة ستظهر مميزة عند التكليف الجديد.'));
        }
        header('Location: ' . APP_URL . 'modules/administration/winback.php'); exit();
    }

    if (isset($_POST['mark_declined'])) {
        $cid = (int)$_POST['mark_declined'];
        dbExecute("UPDATE winback_campaigns SET status='declined', closed_at=NOW(), handled_by=? WHERE id = ?", [$uid, $cid]);
        wb_audit($uid, 'DECLINED', $cid, []);
        flash('success', t('تم إغلاق المتابعة (اعتذر عن العودة).'));
        header('Location: ' . APP_URL . 'modules/administration/winback.php'); exit();
    }
}

/* ══════════ data ══════════ */
$viewId = (int)($_GET['view'] ?? 0);
$view = null; $contacts = []; $pastFamilies = [];
if ($viewId) {
    $view = dbFetchOne("SELECT wc.*, s.full_name sponsor_name, s.sponsor_code, s.phone sponsor_phone, s.status sponsor_status, u.full_name handler_name
        FROM winback_campaigns wc JOIN sponsors s ON s.id = wc.sponsor_id LEFT JOIN users u ON u.id = wc.handled_by WHERE wc.id = ?", [$viewId]);
    if ($view) {
        $contacts = dbFetchAll("SELECT c.*, u.full_name by_name FROM winback_contacts c LEFT JOIN users u ON u.id = c.contacted_by WHERE c.campaign_id = ? ORDER BY c.id DESC", [$viewId]);
        $pastFamilies = dbFetchAll("SELECT f.id, f.family_code, f.mother_name,
            (SELECT COUNT(*) FROM sponsorships x WHERE x.family_id = f.id AND x.status = 'active') active_now
            FROM sponsorships sp JOIN families f ON f.id = sp.family_id
            WHERE sp.sponsor_id = ? GROUP BY f.id ORDER BY f.mother_name", [$view['sponsor_id']]);
    }
}

// Eligible lapsed sponsors (stopped > 90 days, no open follow-up yet)
$queue = dbFetchAll("SELECT s.id, s.full_name, s.phone, s.status, s.sponsor_code,
    (SELECT COUNT(*) FROM sponsorships sp WHERE sp.sponsor_id = s.id) total_ships,
    (SELECT MAX(COALESCE(sp.pause_start_date, sp.end_date, sp.updated_at)) FROM sponsorships sp WHERE sp.sponsor_id = s.id AND sp.status IN ('cancelled','paused')) last_stop
    FROM sponsors s
    WHERE s.status IN ('inactive','suspended','cancelled')
      AND NOT EXISTS (SELECT 1 FROM winback_campaigns wc WHERE wc.sponsor_id = s.id AND wc.status IN ('open','contacted'))
    HAVING (last_stop IS NULL OR last_stop < DATE_SUB(NOW(), INTERVAL 90 DAY))
    ORDER BY last_stop ASC");

// Quick reference: top families currently available for assignment
$uncovered = dbFetchAll("SELECT f.id, f.family_code, f.mother_name, f.children_count, f.monthly_need_amount, f.city
    FROM families f
    WHERE f.status IN ('active','pending')
      AND NOT EXISTS (SELECT 1 FROM sponsorships sp WHERE sp.family_id = f.id AND sp.status = 'active')
    ORDER BY f.children_count DESC, f.monthly_need_amount DESC
    LIMIT 10");

$cases = dbFetchAll("SELECT wc.*, s.full_name sponsor_name, u.full_name handler_name
    FROM winback_campaigns wc JOIN sponsors s ON s.id = wc.sponsor_id LEFT JOIN users u ON u.id = wc.handled_by
    ORDER BY (wc.status IN ('open','contacted')) DESC, wc.id DESC LIMIT 200");

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-rotate-left me-2"></i><?php echo t('متابعات الاسترجاع'); ?></h2>
    <p><?php echo t('متابعة الكفلاء المتوقفين منذ أكثر من 3 أشهر ومحاولة إعادتهم للكفالة'); ?></p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<?php if ($view): ?>
<!-- ══════════ Follow-up details ══════════ -->
<div class="card mb-4 fade-in">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file-lines me-2"></i><?php echo e($view['sponsor_name']); ?> — <?php echo e($view['sponsor_code']); ?></span>
        <span>
            <?php [$sl, $sc] = $caseStatus[$view['status']] ?? [$view['status'], 'bg-secondary']; ?>
            <span class="badge <?php echo $sc; ?>"><?php echo $sl; ?></span>
            <a href="<?php echo APP_URL; ?>modules/administration/winback.php" class="btn btn-sm btn-secondary ms-2"><i class="fas fa-arrow-right"></i> <?php echo t('رجوع'); ?></a>
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="border rounded p-2 text-center"><small class="text-muted d-block"><?php echo t('الهاتف'); ?></small><strong dir="ltr"><?php echo e($view['sponsor_phone'] ?? '—'); ?></strong></div></div>
            <div class="col-md-3"><div class="border rounded p-2 text-center"><small class="text-muted d-block"><?php echo t('المسؤول عن المتابعة'); ?></small><strong><?php echo e($view['handler_name'] ?? '—'); ?></strong></div></div>
            <div class="col-md-3"><div class="border rounded p-2 text-center"><small class="text-muted d-block"><?php echo t('آخر تواصل'); ?></small><strong><?php echo e($view['last_contact_at'] ?? '—'); ?></strong></div></div>
            <div class="col-md-3"><div class="border rounded p-2 text-center"><small class="text-muted d-block"><?php echo t('بدأت في'); ?></small><strong><?php echo e($view['opened_at']); ?></strong></div></div>
        </div>

        <h6 class="mb-2"><i class="fas fa-history me-2 text-warning"></i><?php echo t('الأسر السابقة لهذا الكفيل'); ?></h6>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-hover align-middle">
                <thead><tr><th><?php echo t('الكود'); ?></th><th><?php echo t('اسم الأم'); ?></th><th><?php echo t('الحالة'); ?></th></tr></thead>
                <tbody>
                <?php if (!$pastFamilies): ?>
                <tr><td colspan="3" class="text-center text-muted py-3"><?php echo t('لا توجد كفالات سابقة.'); ?></td></tr>
                <?php else: foreach ($pastFamilies as $f): ?>
                <tr>
                    <td><?php echo e($f['family_code']); ?></td>
                    <td><?php echo e($f['mother_name']); ?></td>
                    <td><?php echo ((int)$f['active_now'] === 0)
                        ? '<span class="badge bg-success">' . t('متاحة حالياً') . '</span>'
                        : '<span class="badge bg-secondary">' . t('مكفولة حالياً') . '</span>'; ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small"><i class="fas fa-circle-info me-1"></i><?php echo t('عند تكليف الكفيل بعد عودته، تظهر أسره السابقة بلون مميز في صفحة إنشاء الكفالة ولا يتم منعها.'); ?></p>

        <h6 class="mb-2"><i class="fas fa-phone me-2 text-primary"></i><?php echo t('سجل التواصل'); ?></h6>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-hover align-middle">
                <thead><tr><th><?php echo t('التاريخ'); ?></th><th><?php echo t('بواسطة'); ?></th><th><?php echo t('طريقة التواصل'); ?></th><th><?php echo t('النتيجة'); ?></th><th><?php echo t('ملاحظات'); ?></th></tr></thead>
                <tbody>
                <?php if (!$contacts): ?>
                <tr><td colspan="5" class="text-center text-muted py-3"><?php echo t('لا توجد محاولات مسجلة.'); ?></td></tr>
                <?php else: foreach ($contacts as $c): ?>
                <tr>
                    <td><small><?php echo e($c['contact_date']); ?></small></td>
                    <td><?php echo e($c['by_name'] ?? '—'); ?></td>
                    <td><?php echo e($c['method']); ?></td>
                    <td><?php echo e($c['outcome']); ?></td>
                    <td><?php echo e($c['notes'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (in_array($view['status'], ['open','contacted'], true)): ?>
        <div class="row g-3">
            <div class="col-md-8">
                <form method="post" class="row g-2 align-items-end border rounded p-3">
                    <?php echo csrf_field(); ?>
                    <div class="col-md-2">
                        <label class="form-label"><?php echo t('طريقة التواصل'); ?></label>
                        <select name="method" class="form-select">
                            <option value="phone"><?php echo t('هاتفياً'); ?></option>
                            <option value="whatsapp"><?php echo t('واتساب'); ?></option>
                            <option value="visit"><?php echo t('زيارة'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><?php echo t('النتيجة'); ?></label>
                        <select name="outcome" class="form-select">
                            <option value="no_answer"><?php echo t('لا رد'); ?></option>
                            <option value="reached"><?php echo t('تم الوصول'); ?></option>
                            <option value="promised"><?php echo t('وعد بالعودة'); ?></option>
                            <option value="refused"><?php echo t('اعتذر عن العودة'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-5"><label class="form-label"><?php echo t('ملاحظات'); ?></label><input type="text" name="notes" class="form-control"></div>
                    <div class="col-md-2"><button name="add_contact" value="<?php echo (int)$view['id']; ?>" class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i><?php echo t('تسجيل'); ?></button></div>
                </form>
            </div>
            <div class="col-md-4 d-flex gap-2 align-items-end">
                <form method="post" class="flex-fill"><?php echo csrf_field(); ?>
                    <button name="mark_returned" value="<?php echo (int)$view['id']; ?>" class="btn btn-success w-100" onclick="return confirm('إعادة تفعيل الكفيل؟')"><i class="fas fa-user-check me-1"></i><?php echo t('تأكيد العودة وتفعيل الكفيل'); ?></button>
                </form>
                <form method="post" class="flex-fill"><?php echo csrf_field(); ?>
                    <button name="mark_declined" value="<?php echo (int)$view['id']; ?>" class="btn btn-danger w-100" onclick="return confirm('إغلاق المتابعة؟')"><i class="fas fa-user-xmark me-1"></i><?php echo t('اعتذر عن العودة'); ?></button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<div class="row g-4 mb-4">
    <!-- ══════════ Queue ══════════ -->
    <div class="col-lg-8">
        <div class="card fade-in h-100">
            <div class="card-header"><i class="fas fa-hourglass-half me-2"></i><?php echo t('الكفلاء المتوقفون المؤهلون'); ?> (<?php echo count($queue); ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark"><tr><th><?php echo t('الكفيل'); ?></th><th><?php echo t('الهاتف'); ?></th><th><?php echo t('كفالات سابقة'); ?></th><th><?php echo t('تاريخ التوقف'); ?></th><th class="text-center"><?php echo t('إجراء'); ?></th></tr></thead>
                        <tbody>
                        <?php if (!$queue): ?>
                        <tr><td colspan="5" class="text-center text-success py-4"><i class="fas fa-check-circle me-2"></i><?php echo t('لا يوجد كفلاء مؤهلون حالياً.'); ?></td></tr>
                        <?php else: foreach ($queue as $q): ?>
                        <tr>
                            <td><strong><?php echo e($q['full_name']); ?></strong> <small class="text-muted">(<?php echo e($q['sponsor_code']); ?>)</small></td>
                            <td dir="ltr"><?php echo e($q['phone'] ?? '—'); ?></td>
                            <td><?php echo (int)$q['total_ships']; ?></td>
                            <td><?php echo e($q['last_stop'] ?? '—'); ?></td>
                            <td class="text-center">
                                <form method="post" class="d-inline"><?php echo csrf_field(); ?>
                                    <button name="open_case" value="<?php echo (int)$q['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-folder-open me-1"></i><?php echo t('بدء متابعة'); ?></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════ Available families quick reference ══════════ -->
    <div class="col-lg-4">
        <div class="card fade-in h-100 border-success">
            <div class="card-header bg-success text-white"><i class="fas fa-house-circle-check me-2"></i><?php echo t('أسر متاحة للتكليف حالياً'); ?></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th><?php echo t('اسم الأم'); ?></th><th class="text-center"><?php echo t('الأطفال'); ?></th><th class="text-end"><?php echo t('الاحتياج الشهري'); ?></th></tr></thead>
                        <tbody>
                        <?php if (!$uncovered): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3"><?php echo t('لا توجد أسر متاحة.'); ?></td></tr>
                        <?php else: foreach ($uncovered as $uf): ?>
                        <tr>
                            <td><strong><?php echo e($uf['mother_name']); ?></strong><br><small class="text-muted"><?php echo e($uf['family_code']); ?><?php echo !empty($uf['city']) ? ' — ' . e($uf['city']) : ''; ?></small></td>
                            <td class="text-center"><span class="badge bg-warning text-dark"><?php echo (int)$uf['children_count']; ?></span></td>
                            <td class="text-end"><?php echo number_format((float)$uf['monthly_need_amount'], 0); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer small text-muted">
                <i class="fas fa-circle-info me-1"></i><?php echo t('مرجع سريع أثناء المكالمات — القائمة الكاملة في مركز التقارير.'); ?>
                <a href="<?php echo APP_URL; ?>modules/reports/index.php?tab=orphaned" class="ms-1 fw-bold" style="color:#1b4d8f"><?php echo t('القائمة الكاملة'); ?></a>
            </div>
        </div>
    </div>
</div>

<!-- ══════════ Follow-ups log ══════════ -->
<div class="card fade-in">
    <div class="card-header"><i class="fas fa-list me-2"></i><?php echo t('سجل المتابعات'); ?> (<?php echo count($cases); ?>)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark"><tr><th>#</th><th><?php echo t('الكفيل'); ?></th><th><?php echo t('المسؤول'); ?></th><th><?php echo t('آخر تواصل'); ?></th><th><?php echo t('الحالة'); ?></th><th class="text-center"><?php echo t('عرض'); ?></th></tr></thead>
                <tbody>
                <?php if (!$cases): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo t('لا توجد متابعات بعد.'); ?></td></tr>
                <?php else: foreach ($cases as $c): ?>
                <tr>
                    <td><?php echo (int)$c['id']; ?></td>
                    <td><strong><?php echo e($c['sponsor_name']); ?></strong></td>
                    <td><?php echo e($c['handler_name'] ?? '—'); ?></td>
                    <td><small><?php echo e($c['last_contact_at'] ?? '—'); ?></small></td>
                    <td><?php [$sl, $sc] = $caseStatus[$c['status']] ?? [$c['status'], 'bg-secondary']; ?><span class="badge <?php echo $sc; ?>"><?php echo $sl; ?></span></td>
                    <td class="text-center"><a href="?view=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>