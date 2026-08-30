<?php
// modules/supervisors/assign-letters.php - Letter + GENDER matrix (Admin + VGM) + native legacy 'both' handling
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

// ---- Access guard ----
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}
if (!in_array(Session::getUserRole(), ['admin', 'vice_general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'توزيع الحروف';
$active    = 'supervisors';

/* ---------- gender helpers (robust to legacy stored values) ---------- */
function ak_norm_gender($raw): string {
    $v = strtolower(trim((string)$raw));
    if (in_array($v, ['female', 'f', 'أنثى', 'انثى'], true)) return 'female';
    if (in_array($v, ['male', 'm', 'ذكر'], true)) return 'male';
    return '';
}
$AK_GENDERS = ['male' => 'ذكور', 'female' => 'إناث'];

/* ---------- base data ---------- */
$supervisors = dbFetchAll("
    SELECT u.id, u.full_name
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE r.code = 'supervisor' AND u.is_active = 1
    ORDER BY u.full_name
");

$highlightId = (int)($_GET['supervisor'] ?? 0);

$letters = dbFetchAll("SELECT id, code FROM letters WHERE is_active = 1 ORDER BY sort_order");
$letterCodes = [];
foreach ($letters as $L) $letterCodes[(int)$L['id']] = $L['code'];

$rows = dbFetchAll("
    SELECT sl.id, sl.letter_id, sl.gender, sl.supervisor_id, u.full_name, l.code
    FROM supervisor_letters sl
    LEFT JOIN users u ON u.id = sl.supervisor_id
    LEFT JOIN letters l ON l.id = sl.letter_id
");

$matrix = []; // [letter_id][male|female] = row
$legacy = []; // old rows without a single gender ('both'/null/unknown)
foreach ($rows as $r) {
    $g = ak_norm_gender($r['gender']);
    if ($g === '') { $legacy[] = $r; continue; }
    $matrix[(int)$r['letter_id']][$g] = $r;
}

/* ---------- POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
    } else {
        if (isset($_POST['set_cell'])) {
            $letterId = (int)($_POST['letter_id'] ?? 0);
            $gender   = ak_norm_gender($_POST['gender'] ?? '');
            $supId    = (int)($_POST['supervisor_id'] ?? 0);

            if ($letterId > 0 && $gender !== '' && isset($letterCodes[$letterId])) {
                $old = $matrix[$letterId][$gender] ?? null;
                if ($old) {
                    dbExecute("DELETE FROM supervisor_letters WHERE id = ?", [$old['id']]);
                }
                if ($supId > 0) {
                    dbExecute("INSERT INTO supervisor_letters (supervisor_id, letter_id, gender, assigned_by) VALUES (?, ?, ?, ?)", [$supId, $letterId, $gender, Session::getUserId()]);
                }

                $gLabel = $AK_GENDERS[$gender];
                $code = $letterCodes[$letterId];

                if ($supId > 0) {
                    $supName = dbFetchOne("SELECT full_name FROM users WHERE id = ?", [$supId])['full_name'] ?? ('#' . $supId);
                    flash('success', "تم تعيين الحرف «{$code}» ({$gLabel}) للمشرف: {$supName}");
                } else {
                    flash('success', "تم إلغاء تعيين الحرف «{$code}» ({$gLabel}).");
                }

                dbExecute(
                    "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                     VALUES (?, 'ASSIGN', 'supervisor_letters', ?, ?, ?, ?, ?)",
                    [
                        Session::getUserId(),
                        $letterId,
                        $old ? json_encode(['supervisor_id' => (int)$old['supervisor_id'], 'letter' => $code, 'gender' => $gender], JSON_UNESCAPED_UNICODE) : null,
                        json_encode(['supervisor_id' => $supId, 'letter' => $code, 'gender' => $gender], JSON_UNESCAPED_UNICODE),
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]
                );
            }
        }

        /* ---- NEW: split a legacy 'both' row into male + female for the same supervisor ---- */
        if (isset($_POST['split_legacy'])) {
            $slId = (int)$_POST['split_legacy'];
            $row = dbFetchOne("
                SELECT sl.id, sl.supervisor_id, sl.letter_id, sl.gender, l.code, u.full_name
                FROM supervisor_letters sl
                JOIN letters l ON l.id = sl.letter_id
                LEFT JOIN users u ON u.id = sl.supervisor_id
                WHERE sl.id = ?
            ", [$slId]);

            if ($row && ak_norm_gender($row['gender']) === '') {
                $lid = (int)$row['letter_id'];
                $sid = (int)$row['supervisor_id'];
                $block = [];

                foreach (['male', 'female'] as $g) {
                    $cell = $matrix[$lid][$g] ?? null;
                    if ($cell && (int)$cell['supervisor_id'] !== $sid) {
                        $block[] = $AK_GENDERS[$g] . ' → ' . ($cell['full_name'] ?? ('#' . $cell['supervisor_id']));
                    }
                }

                if ($block) {
                    flash('error', 'تعذر التقسيم: خانة ' . implode('، وخانة ', $block) . ' معيّنة لمشرف آخر — حررها من المصفوفة أولاً.');
                } else {
                    foreach (['male', 'female'] as $g) {
                        dbExecute("DELETE FROM supervisor_letters WHERE letter_id = ? AND gender = ?", [$lid, $g]);
                        dbExecute("INSERT INTO supervisor_letters (supervisor_id, letter_id, gender, assigned_by) VALUES (?, ?, ?, ?)", [$sid, $lid, $g, Session::getUserId()]);
                    }
                    dbExecute("DELETE FROM supervisor_letters WHERE id = ?", [$slId]);

                    dbExecute(
                        "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                         VALUES (?, 'SPLIT_LEGACY', 'supervisor_letters', ?, ?, ?, ?, ?)",
                        [
                            Session::getUserId(),
                            $lid,
                            json_encode(['letter' => $row['code'], 'gender' => 'both(legacy)', 'supervisor_id' => $sid], JSON_UNESCAPED_UNICODE),
                            json_encode(['letter' => $row['code'], 'gender' => ['male', 'female'], 'supervisor_id' => $sid], JSON_UNESCAPED_UNICODE),
                            $_SERVER['REMOTE_ADDR'] ?? '',
                            $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]
                    );
                    flash('success', "تم تقسيم التعيين القديم للحرف «{$row['code']}» إلى ذكور + إناث للمشرف: {$row['full_name']}");
                }
            }
        }

        if (isset($_POST['remove_legacy'])) {
            $slId = (int)$_POST['remove_legacy'];
            $row = dbFetchOne("
                SELECT sl.id, sl.gender, l.code
                FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id
                WHERE sl.id = ?
            ", [$slId]);

            if ($row && ak_norm_gender($row['gender']) === '') {
                dbExecute("DELETE FROM supervisor_letters WHERE id = ?", [$slId]);
                dbExecute(
                    "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                     VALUES (?, 'ASSIGN', 'supervisor_letters', ?, ?, ?, ?, ?)",
                    [
                        Session::getUserId(),
                        (int)$row['id'],
                        json_encode(['letter' => $row['code'], 'gender' => null, 'removed_legacy' => true], JSON_UNESCAPED_UNICODE),
                        json_encode(['removed' => true], JSON_UNESCAPED_UNICODE),
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]
                );
                flash('success', "تمت إزالة التعيين القديم للحرف «{$row['code']}».");
            }
        }
    }
    redirect('modules/supervisors/assign-letters.php' . ($highlightId ? '?supervisor=' . $highlightId : ''));
}

/* ---------- per-supervisor summary (both genders in one row) ---------- */
$perSup = [];
foreach ($matrix as $lid => $genders) {
    foreach ($genders as $g => $row) {
        $sid = (int)$row['supervisor_id'];
        $perSup[$sid]['name'] = $row['full_name'] ?? ('#' . $sid);
        $perSup[$sid][$g][] = $letterCodes[$lid] ?? ('#' . $lid);
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.letter-chip{display:inline-flex;width:46px;height:46px;align-items:center;justify-content:center;border-radius:12px;font-weight:800;font-size:18px;margin:3px}
.letter-chip.free{background:#eef2f9;color:#0a1f44;border:2px solid #d8e0ee}
.ak-hl{background:#fff3cd !important}
.ak-mini-chip{display:inline-block;background:#1b4d8f;color:#fff;border-radius:8px;padding:2px 9px;margin:2px;font-weight:700}
.ak-mini-chip.f{background:#8f1b4d}
</style>

<div class="welcome-section fade-in">
    <h2>توزيع الحروف — مصفوفة الحرف + الجنس</h2>
    <p>القاعدة: كل <strong>حرف + جنس</strong> يُمنح لمشرف واحد فقط — يمكن للمشرف حمل ذكور حرفٍ وإناث حرفٍ آخر، ويظهر ذلك في صف واحد.</p>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="card mb-4 fade-in">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label">تمييز مشرف في الجدول (اختياري)</label>
                <select name="supervisor" class="form-select" onchange="this.form.submit()">
                    <option value="">— بدون تمييز —</option>
                    <?php foreach ($supervisors as $s): ?>
                        <option value="<?php echo (int)$s['id']; ?>" <?php echo $highlightId === (int)$s['id'] ? 'selected' : ''; ?>>
                            <?php echo e($s['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($letters && $supervisors): ?>
    <div class="card mb-4 fade-in">
        <div class="card-header"><i class="fas fa-table me-2"></i>مصفوفة الحروف (حرف لكل صف + مشرف الذكور + مشرف الإناث)</div>
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-bordered align-middle bg-white mb-0" style="min-width:520px">
                    <thead>
                        <tr>
                            <th style="width:90px" class="text-center">الحرف</th>
                            <th>ذكور</th>
                            <th>إناث</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($letters as $L): $lid = (int)$L['id']; ?>
                            <tr>
                                <td class="text-center"><span class="letter-chip free"><?php echo e($L['code']); ?></span></td>
                                <?php foreach (['male', 'female'] as $g):
                                    $cell = $matrix[$lid][$g] ?? null;
                                    $hl = $cell && $highlightId && (int)$cell['supervisor_id'] === $highlightId;
                                ?>
                                    <td class="<?php echo $hl ? 'ak-hl' : ''; ?>">
                                        <form method="post" class="m-0">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="set_cell" value="1">
                                            <input type="hidden" name="letter_id" value="<?php echo $lid; ?>">
                                            <input type="hidden" name="gender" value="<?php echo $g; ?>">
                                            <select name="supervisor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="">— غير معيّن —</option>
                                                <?php foreach ($supervisors as $s): ?>
                                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo $cell && (int)$cell['supervisor_id'] === (int)$s['id'] ? 'selected' : ''; ?>>
                                                        <?php echo e($s['full_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4 fade-in">
        <div class="card-header"><i class="fas fa-user-tie me-2"></i>ملخص المشرفين (الجنسان في صف واحد)</div>
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-hover align-middle bg-white mb-0">
                    <thead>
                        <tr><th>المشرف</th><th>ذكور</th><th>إناث</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$perSup): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">لا توجد تعيينات بعد.</td></tr>
                        <?php else: foreach ($perSup as $sid => $info): ?>
                            <tr <?php echo $highlightId === $sid ? 'class="ak-hl"' : ''; ?>>
                                <td><strong><?php echo e($info['name']); ?></strong></td>
                                <td>
                                    <?php if (!empty($info['male'])): foreach ($info['male'] as $c): ?><span class="ak-mini-chip"><?php echo e($c); ?></span><?php endforeach; else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($info['female'])): foreach ($info['female'] as $c): ?><span class="ak-mini-chip f"><?php echo e($c); ?></span><?php endforeach; else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?> <!-- THIS IS THE MISSING ENDIF THAT FIXED THE PARSE ERROR -->

<?php if ($legacy): ?>
    <div class="card border-warning mb-4 fade-in">
        <div class="card-header bg-warning text-dark"><i class="fas fa-triangle-exclamation me-2"></i>تعيينات قديمة بدون جنس واحد — قم بتقسيمها إلى ذكور + إناث أو إزالتها</div>
        <div class="card-body">
            <?php foreach ($legacy as $row): ?>
                <div class="d-inline-flex align-items-center me-3 mb-2">
                    <form method="post" class="d-inline" onsubmit="return confirm('تقسيم التعيين القديم للحرف «<?php echo e($row['code'] ?? '#'); ?>» إلى ذكور + إناث لنفس المشرف؟');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="split_legacy" value="<?php echo (int)$row['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary me-1" title="تقسيم إلى ذكور + إناث">
                            <i class="fas fa-code-branch me-1"></i>تقسيم
                        </button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('إزالة التعيين القديم للحرف «<?php echo e($row['code'] ?? '#'); ?>»؟');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="remove_legacy" value="<?php echo (int)$row['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger me-1" title="حذف">
                            <i class="fas fa-trash me-1"></i>حذف
                        </button>
                    </form>
                    <span class="badge bg-light text-dark border"><?php echo e($row['code'] ?? ('حرف #' . $row['letter_id'])); ?> ← <?php echo e($row['full_name'] ?? ('#' . $row['supervisor_id'])); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php elseif (!$supervisors): ?>
    <div class="alert alert-warning">لا يوجد مشرفون نشطون — أنشئ مشرفاً أولاً من صفحة «إدارة المشرفين».</div>
<?php else: ?>
    <div class="alert alert-warning">لا توجد حروف نشطة في النظام.</div>
<?php endif; ?>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>