<?php
// modules/system/split_plus_sponsors.php - one-time tool: split combined "+" sponsor records (Admin + VGM)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
if (!in_array(Session::getUserRole(), ['admin', 'vice_general_manager'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle = 'فصل سجلات الكفلاء المركبة (+)';
$active    = 'system';

/* ---------- helpers ---------- */
function ak_guess_gender(string $name): string {
    $t = trim((string)preg_replace('/\s+/u', ' ', $name));
    $p = explode(' ', $t);
    $n = normalize_arabic_letter($p[0] ?? '');
    if ($n === '') return '';
    if (str_starts_with($n, 'عبد')) return 'male';
    if (str_starts_with($n, 'ابو')) return 'male';
    if ($n === 'ام') return 'female';
    $maleH = ['اسامه','حمزه','معاويه','عطيه','عبيده','طلحه','خليفه','جمعه','سلامه'];
    if (preg_match('/[ةه]$/u', $n) && mb_strlen($n) >= 3 && !in_array($n, $maleH, true)) return 'female';
    return '';
}
function ak_clean_part(string $p): array { // [cleanName, suggestedAmount]
    $p = trim($p, " \t/+");
    $amt = 0;
    if (preg_match('/^(.*?)\((\d+)\)\s*$/u', $p, $m)) { $p = trim($m[1], " \t/+"); $amt = (int)$m[2]; }
    return [trim((string)preg_replace('/\s+/u', ' ', $p)), $amt];
}
function ak_letter_for(string $name): array { // [raw, letter_id]
    [$raw, $norm] = first_letter_of($name);
    $lid = null;
    if ($norm !== '') {
        $r = dbFetchOne("SELECT id FROM letters WHERE code = ? LIMIT 1", [$norm]);
        if (!$r) $r = dbFetchOne("SELECT id FROM letters WHERE code = ? LIMIT 1", [$raw]);
        $lid = $r ? (int)$r['id'] : null;
    }
    return [$raw, $lid];
}
function ak_next_sh_code(): string {
    $n = (int)(dbFetchOne("SELECT COUNT(*) c FROM sponsorships")['c'] ?? 0) + 1;
    return 'SH-' . str_pad((string)$n, 6, '0', STR_PAD_LEFT);
}
function ak_split_one(array $sp, bool $sameSup, int $userId): array { // [newSponsors, newLinks]
    $parts = array_values(array_filter(array_map('trim', explode('+', (string)$sp['full_name'])), fn($x) => $x !== ''));
    if (count($parts) < 2) return [0, 0];

    // 1) original keeps part 1, "+" removed, letter recomputed
    [$clean0, ] = ak_clean_part($parts[0]);
    [$raw0, $lid0] = ak_letter_for($clean0);
    dbExecute("UPDATE sponsors SET full_name = ?, first_letter_raw = ?, first_letter_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ?",
        [$clean0, $raw0, $lid0, $userId, (int)$sp['id']]);

    $origSups = dbFetchAll("SELECT family_id, status, start_date, monthly_amount FROM sponsorships WHERE sponsor_id = ?", [(int)$sp['id']]);
    $created = 0; $linked = 0;

    for ($i = 1; $i < count($parts); $i++) {
        [$clean, $amt] = ak_clean_part($parts[$i]);
        if ($clean === '') continue;
        [$raw, $lid] = ak_letter_for($clean);
        $g = ak_guess_gender($clean);

        dbExecute("INSERT INTO sponsors
            (full_name, first_letter_raw, first_letter_id, phone, email, address, sponsor_type, preferred_payment_method,
             status, notes, created_by, created_at, sponsor_code, supervisor_id, gender)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $clean, $raw, $lid, $sp['phone'], $sp['email'], $sp['address'], $sp['sponsor_type'], $sp['preferred_payment_method'],
                $sp['status'], 'فُصل من السجل ' . $sp['sponsor_code'] . ' (أداة فصل +)', $userId, date('Y-m-d H:i:s'),
                'TMP', $sameSup ? $sp['supervisor_id'] : null, $g !== '' ? $g : null
            ]);
        $newId = (int)(dbFetchOne("SELECT LAST_INSERT_ID() AS id")['id'] ?? 0);
        if ($newId > 0) {
            dbExecute("UPDATE sponsors SET sponsor_code = ? WHERE id = ?", ['SPL-SP-' . str_pad((string)$newId, 6, '0', STR_PAD_LEFT), $newId]);
            $created++;
            // 2) link new sponsor back to the SAME families 
            // FIX: Use monthly_amount = 1 and status = 'paused' to bypass the >0 check constraint and avoid double-counting active totals
            foreach ($origSups as $os) {
                $note = 'شريك من سجل + — المبلغ قيد المراجعة (رصيد وهمي 1 لتجاوز قيود النظام، الكفالة موقوفة مؤقتاً لمنع الازدواج المالي)';
                if ($amt > 0) $note .= ' | مبلغ مقترح من البيانات القديمة: ' . $amt;
                dbExecute("INSERT INTO sponsorships
                    (sponsor_id, family_id, monthly_amount, currency_code, start_date, status, notes, created_by, sponsorship_code)
                    VALUES (?,?,?,?,?,?,?,?,?)",
                    [$newId, (int)$os['family_id'], 1, 'SDG', $os['start_date'], 'paused', $note, $userId, ak_next_sh_code()]);
                $linked++;
            }
        }
    }
    return [$created, $linked];
}

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
    } else {
        $sameSup = isset($_POST['same_sup']);
        if (isset($_POST['split_one'])) {
            $targets = dbFetchAll("SELECT * FROM sponsors WHERE id = ? AND full_name LIKE '%+%'", [(int)$_POST['split_one']]);
        } else {
            $targets = dbFetchAll("SELECT * FROM sponsors WHERE full_name LIKE '%+%' ORDER BY id");
        }
        $c = 0; $l = 0;
        foreach ($targets as $t) {
            [$a, $b] = ak_split_one($t, $sameSup, Session::getUserId());
            $c += $a; $l += $b;
        }
        try {
            dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent)
                       VALUES (?, 'SPLIT_PLUS', 'sponsors', 0, ?, ?, ?)",
                [Session::getUserId(), json_encode(['new_sponsors' => $c, 'new_links' => $l, 'same_supervisor' => $sameSup], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
        } catch (Throwable $e) {}
        flash('success', "تم الفصل: {$c} كفيل جديد و {$l} رابط كفالة للأسر.");
    }
    redirect('modules/system/split_plus_sponsors.php');
}

/* ---------- list ---------- */
$plusSponsors = dbFetchAll("SELECT s.*,
    (SELECT GROUP_CONCAT(sp2.family_id) FROM sponsorships sp2 WHERE sp2.sponsor_id = s.id) AS family_ids
    FROM sponsors s WHERE s.full_name LIKE '%+%' ORDER BY s.id LIMIT 500");
foreach ($plusSponsors as &$sp) {
    $sp['parts'] = array_values(array_filter(array_map('trim', explode('+', (string)$sp['full_name'])), fn($x) => $x !== ''));
    $sp['family_codes'] = [];
    if (!empty($sp['family_ids'])) {
        $ids = array_map('intval', explode(',', $sp['family_ids']));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sp['family_codes'] = array_column(dbFetchAll("SELECT family_code FROM families WHERE id IN ($ph)", $ids), 'family_code');
    }
}
unset($sp);
$total = count($plusSponsors);

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
<h2>فصل سجلات الكفلاء المركبة (+)</h2>
<p>كل اسم بعد علامة + يتحول لكفيل مستقل ويُربط بنفس الأسر — المبالغ تُترك برصيد وهمي (1) وحالة "موقوفة" لتجاوز قيود قاعدة البيانات ومنع الازدواج المالي — والكفيل الجديد يبقى مع مشرف السجل الأصلي ما لم تلغِ الخيار.</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="card mb-4 fade-in">
<div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
<div>
<h6 class="mb-1"><i class="fas fa-cut me-2"></i>سجلات تحتوي + : <span class="badge bg-danger"><?php echo $total; ?></span></h6>
<small class="text-muted">الأداة قابلة للتكرار بأمان — بعد الفصل يختفي السجل من القائمة تلقائياً.</small>
</div>
<form method="post" class="d-flex align-items-center gap-2" onsubmit="return confirm('فصل جميع السجلات المركبة الآن؟');">
<?php echo csrf_field(); ?>
<label class="form-check-label small">
<input type="checkbox" name="same_sup" checked class="form-check-input"> إبقاء الكفلاء الجدد مع نفس المشرف الأصلي
</label>
<button class="btn btn-warning"><i class="fas fa-cut me-1"></i> فصل الكل</button>
</form>
</div>
</div>

<div class="card fade-in">
<div class="card-header"><i class="fas fa-list me-2"></i>السجلات المركبة (أول 500)</div>
<div class="card-body p-2">
<div class="table-responsive">
<table class="table table-hover align-middle bg-white mb-0">
<thead><tr><th>الكود</th><th>الاسم الحالي</th><th>الأجزاء بعد الفصل</th><th>الأسر المرتبطة</th><th></th></tr></thead>
<tbody>
<?php if (!$plusSponsors): ?>
<tr><td colspan="5" class="text-center text-success py-4"><i class="fas fa-check-circle me-2"></i>لا توجد سجلات مركبة — البيانات نظيفة.</td></tr>
<?php else: foreach ($plusSponsors as $sp): ?>
<tr>
<td><?php echo e($sp['sponsor_code']); ?></td>
<td><strong><?php echo e($sp['full_name']); ?></strong></td>
<td>
<?php foreach ($sp['parts'] as $i => $part): ?>
<span class="badge <?php echo $i === 0 ? 'bg-primary' : 'bg-warning text-dark'; ?>"><?php echo e($part); ?></span>
<?php endforeach; ?>
</td>
<td>
<?php if ($sp['family_codes']): foreach ($sp['family_codes'] as $fc): ?><span class="badge bg-light text-dark border"><?php echo e($fc); ?></span><?php endforeach; else: ?><span class="text-muted">— لا أسر —</span><?php endif; ?>
</td>
<td>
<form method="post" class="m-0" onsubmit="return confirm('فصل هذا السجل الآن؟');">
<?php echo csrf_field(); ?>
<input type="hidden" name="same_sup" value="1">
<input type="hidden" name="split_one" value="<?php echo (int)$sp['id']; ?>">
<button class="btn btn-sm btn-primary"><i class="fas fa-cut me-1"></i> فصل</button>
</form>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>