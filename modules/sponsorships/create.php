<?php
// modules/sponsorships/create.php - Sponsor→Orphan sponsorship with Auto-Matching Engine
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle = 'إنشاء كفالة'; $active = 'sponsorships';

function ak_sp_norm_gender($raw): string {
    $v = strtolower(trim((string)$raw));
    if (in_array($v, ['female', 'f', 'أنثى', 'انثى'], true)) return 'female';
    if (in_array($v, ['male', 'm', 'ذكر'], true)) return 'male';
    return '';
}
$AK_SP_GENDER_LABEL = ['male' => 'ذكور', 'female' => 'إناث'];

/* Auto-matching prioritizes eligible orphan records only. Supervisor scope is
   applied to the sponsor, not to the orphan's family/mother name. */
function ak_sp_auto_match(int $limit): array {
    $rows = dbFetchAll(
        "SELECT fc.id, fc.child_name, fc.gender, fc.birth_date, fc.is_critical,
                fc.match_status, fc.application_date,
                f.id AS family_id, f.mother_name, f.family_code
         FROM family_children fc
         JOIN families f ON f.id = fc.family_id
         WHERE fc.match_status IN ('unmatched','waiting_list','lost_sponsor')
           AND NOT EXISTS (SELECT 1 FROM sponsorships sp
                           WHERE sp.child_id = fc.id AND sp.status IN ('active','paused'))
         ORDER BY fc.is_critical DESC,
                  FIELD(fc.match_status,'lost_sponsor') DESC,
                  fc.application_date ASC, fc.id ASC
         LIMIT 500"
    );
    return array_slice($rows, 0, $limit);
}

$errors = [];
$input = ['sponsor_id' => (int)($_POST['sponsor_id'] ?? $_GET['sponsor_id'] ?? 0), 'notes' => (string)($_POST['notes'] ?? '')];
$selectedOrphans = array_map('intval', (array)($_POST['orphans'] ?? []));
$perAmounts = [];
foreach ((array)($_POST['orphan_amount'] ?? []) as $k => $v) $perAmounts[(int)$k] = (float)str_replace(',', '', (string)$v);
$oq = trim((string)($_GET['oq'] ?? ''));

/* Supervisor responsibility is determined solely by sponsor first letter + gender. */
$uid = Session::getUserId();
$myLetterGenders = [];
if ($role === 'supervisor') {
    foreach (dbFetchAll("SELECT letter_id, gender FROM supervisor_letters WHERE supervisor_id = ?", [$uid]) as $lr) {
        $g = ak_sp_norm_gender($lr['gender']);
        if ($g !== '') $myLetterGenders[(int)$lr['letter_id']][] = $g;
    }
}
$mySponsorIds = null;
if ($role === 'supervisor') {
    $mySponsorIds = [];
    foreach (dbFetchAll("SELECT id, first_letter_id, gender FROM sponsors") as $s) {
        $gs = $myLetterGenders[(int)$s['first_letter_id']] ?? [];
        $sg = ak_sp_norm_gender($s['gender']);
        if ($sg === '') continue;
        foreach ($gs as $g) {
            if ($g === $sg) { $mySponsorIds[] = (int)$s['id']; break; }
        }
    }
    $mySponsorIds = array_values(array_unique($mySponsorIds));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!verify_csrf()) $errors[] = 'انتهت صلاحية الجلسة.';
    else {
        if (!$input['sponsor_id']) $errors[] = 'اختر الكفيل أولاً.';
        if (!$selectedOrphans) $errors[] = 'اختر يتيماً واحداً على الأقل من قائمة الاقتراحات.';
        $sponsor = $input['sponsor_id'] ? dbFetchOne("SELECT id, full_name, first_letter_id, gender FROM sponsors WHERE id = ?", [$input['sponsor_id']]) : null;
        if (!$sponsor) $errors[] = 'الكفيل غير موجود.';
        elseif ($mySponsorIds !== null && !in_array((int)$sponsor['id'], $mySponsorIds, true)) $errors[] = 'هذا الكفيل ليس من كفلائك.';

        $valid = [];
        if ($selectedOrphans) {
            $ph = implode(',', array_fill(0, count($selectedOrphans), '?')); $byId = [];
            foreach (dbFetchAll("SELECT fc.id, fc.child_name, fc.gender FROM family_children fc WHERE fc.id IN ($ph)", $selectedOrphans) as $o) $byId[(int)$o['id']] = $o;
            foreach ($selectedOrphans as $cid) {
                $o = $byId[$cid] ?? null;
                if (!$o) { $errors[] = 'يتيم غير موجود (#' . $cid . ').'; continue; }
                if (dbFetchOne("SELECT id FROM sponsorships WHERE child_id = ? AND status IN ('active','paused')", [$cid])) { $errors[] = 'الطفل «' . $o['child_name'] . '» مكفول بالفعل.'; continue; }
                $amt = $perAmounts[$cid] ?? 0;
                if ($amt <= 0) { $errors[] = 'أدخل المبلغ الشهري للطفل «' . $o['child_name'] . '».'; continue; }
                $valid[] = ['id' => $cid, 'name' => $o['child_name'], 'amount' => $amt];
            }
        }

        if (!$errors && $valid) {
            db()->beginTransaction();
            try {
                $created = [];
                foreach ($valid as $v) {
                    dbExecute("INSERT INTO sponsorships (sponsor_id, child_id, monthly_amount, currency_code, start_date, status, notes, created_by) VALUES (?, ?, ?, 'SDG', CURDATE(), 'active', ?, ?)", [$input['sponsor_id'], $v['id'], $v['amount'], $input['notes'], $uid]);
                    $newId = (int)dbLastInsertId();
                    $code = 'SH-' . str_pad((string)$newId, 6, '0', STR_PAD_LEFT);
                    dbExecute("UPDATE sponsorships SET sponsorship_code = ? WHERE id = ?", [$code, $newId]);
                    dbExecute("UPDATE family_children SET match_status = 'matched' WHERE id = ?", [$v['id']]);
                    dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, 'CREATE', 'sponsorships', ?, NULL, ?, ?, ?)", [$uid, $newId, json_encode(['code'=>$code,'sponsor_id'=>$input['sponsor_id'],'child_id'=>$v['id'],'amount'=>$v['amount']], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    $created[] = $code;
                }
                db()->commit(); flash('success', 'تم إنشاء ' . count($created) . ' كفالة: ' . implode('، ', $created)); header('Location: ' . APP_URL . 'modules/sponsorships/index.php'); exit();
            } catch (Throwable $e) { db()->rollBack(); $errors[] = 'فشل الحفظ: ' . $e->getMessage(); }
        }
    }
}

$sq = trim((string)($_GET['sq'] ?? $_POST['sq'] ?? ''));
$sSql = "SELECT id, full_name, sponsor_code FROM sponsors WHERE status = 'active'"; $sParams = [];
if ($mySponsorIds !== null) $sSql .= $mySponsorIds ? " AND id IN (" . implode(',', $mySponsorIds) . ")" : " AND 0 = 1";
if ($sq !== '') { $sSql .= " AND (full_name LIKE ? OR sponsor_code LIKE ?)"; $sParams[] = "%$sq%"; $sParams[] = "%$sq%"; }
$sSql .= " ORDER BY full_name LIMIT 200"; $sponsors = dbFetchAll($sSql, $sParams);
$suggestions = ak_sp_auto_match(30);

$manual = [];
if ($oq !== '') {
    $manual = dbFetchAll("SELECT fc.id, fc.child_name, fc.gender, fc.is_critical, fc.match_status, f.id AS family_id, f.supervisor_id, f.mother_name, f.family_code, sp.id AS active_sp_id FROM family_children fc JOIN families f ON f.id = fc.family_id LEFT JOIN sponsorships sp ON sp.child_id = fc.id AND sp.status IN ('active','paused') WHERE (fc.child_name LIKE ? OR f.family_code LIKE ? OR f.mother_name LIKE ?) ORDER BY fc.id LIMIT 50", ["%$oq%", "%$oq%", "%$oq%"]);
}

include dirname(__DIR__, 2) . '/includes/header.php'; ?>
<style>input[type=number]::-webkit-inner-spin-button,input[type=number]::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}input[type=number]{-moz-appearance:textfield}.match-row-critical{background:#fdecea}.match-row-lost{background:#fff3cd}</style>
<div class="welcome-section fade-in"><h2>إنشاء كفالة (كفيل ← يتيم)</h2><p class="text-muted">محرك المطابقة يقترح الأيتام حسب الأولوية: الحالات الحرجة، ثم من فقدوا كفلاءهم، ثم الأقدم طلباً.</p></div>
<?php if($errors): ?><div class="alert alert-danger fade-in"><ul class="mb-0"><?php foreach($errors as $er)echo '<li>'.e($er).'</li>'; ?></ul></div><?php endif; ?>
<div class="card fade-in mb-3"><div class="card-body"><form method="get" class="row g-2 align-items-end"><div class="col-md-5"><label class="form-label">بحث يدوي عن يتيم (اسم / كود أسرة / اسم أم):</label><input type="text" name="oq" class="form-control" value="<?php echo e($oq); ?>"></div><div class="col-auto"><button class="btn btn-secondary"><i class="fas fa-search me-1"></i>بحث</button></div></form></div></div>
<div class="card fade-in"><div class="card-body"><form method="post"><?php echo csrf_field(); ?><div class="row g-3"><div class="col-md-4"><label class="form-label">الكفيل:</label><select name="sponsor_id" class="form-select" required><option value="">— اختر الكفيل —</option><?php foreach($sponsors as $s): ?><option value="<?php echo(int)$s['id']; ?>" <?php echo $input['sponsor_id']===(int)$s['id']?'selected':''; ?>><?php echo e($s['full_name']); ?> (<?php echo e($s['sponsor_code']); ?>)</option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label d-block">الأيتام المقترحون (محرك المطابقة):</label><?php if(!$suggestions&&!$manual): ?><div class="alert alert-info mb-0">لا يوجد أيتام متاحون في قائمة الانتظار حالياً.</div><?php else: ?><div class="table-responsive border rounded" style="max-height:420px;overflow:auto"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-light sticky-top"><tr><th>اختيار</th><th>اليتيم</th><th>الأسرة</th><th>الجنس</th><th>الحالة</th><th>تاريخ الطلب</th><th style="min-width:130px">المبلغ الشهري</th></tr></thead><tbody><?php $renderRow=function(array $r,bool $busy=false)use($selectedOrphans,$perAmounts,$AK_SP_GENDER_LABEL){$cg=ak_sp_norm_gender($r['gender']);$rowCls=(int)$r['is_critical']===1?'match-row-critical':(($r['match_status']??'')==='lost_sponsor'?'match-row-lost':''); ?><tr class="<?php echo $rowCls; ?>"><td><input class="form-check-input" type="checkbox" name="orphans[]" value="<?php echo(int)$r['id']; ?>" id="o_<?php echo(int)$r['id']; ?>" <?php echo in_array((int)$r['id'],$selectedOrphans,true)?'checked':''; ?> <?php echo $busy?'disabled':''; ?>></td><td><label for="o_<?php echo(int)$r['id']; ?>" class="mb-0"><?php echo e($r['child_name']); ?></label></td><td class="small text-muted"><?php echo e($r['mother_name']??''); ?> (<?php echo e($r['family_code']??''); ?>)</td><td class="small"><?php echo e($AK_SP_GENDER_LABEL[$cg]??'—'); ?></td><td><?php if((int)$r['is_critical']===1): ?><span class="badge bg-danger">حالة حرجة</span><?php endif; ?><?php if(($r['match_status']??'')==='lost_sponsor'): ?><span class="badge bg-warning text-dark">فقد كفيله</span><?php endif; ?><?php if($busy): ?><span class="badge bg-secondary">مكفول</span><?php endif; ?></td><td class="small"><?php echo e($r['application_date']??'—'); ?></td><td><input type="number" min="0" step="500" name="orphan_amount[<?php echo(int)$r['id']; ?>]" class="form-control form-control-sm" value="<?php echo e((string)($perAmounts[(int)$r['id']]??'')); ?>" placeholder="المبلغ"></td></tr><?php }; foreach($suggestions as $r)$renderRow($r); foreach($manual as $r)$renderRow($r,!empty($r['active_sp_id'])); ?></tbody></table></div><?php endif; ?></div>
<div class="col-12"><label class="form-label">ملاحظات:</label><input type="text" name="notes" class="form-control" value="<?php echo e($input['notes']); ?>"></div><div class="col-12"><button name="save" value="1" class="btn btn-primary btn-lg px-5"><i class="fas fa-save me-1"></i>حفظ الكفالة</button> <a href="<?php echo APP_URL; ?>modules/sponsorships/index.php" class="btn btn-secondary btn-lg">إلغاء</a></div></div></form></div></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>