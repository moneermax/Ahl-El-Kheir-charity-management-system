<?php
// modules/system/sync_sponsors.php - Session-7 v2: infer missing gender + matrix re-link (idempotent)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn() || Session::getUserRole() !== 'admin') { http_response_code(403); die('Admin access only.'); }
$pageTitle = 'مزامنة الكفلاء';
$active = 'sponsors';

dbExecute("ALTER TABLE sponsors ADD COLUMN IF NOT EXISTS is_manual_override TINYINT(1) NOT NULL DEFAULT 0");

/* ---------- Arabic name canonicalizer + gender heuristic ---------- */
if (!function_exists('ak_canon_name')) {
function ak_canon_name(string $s): string {
$s = mb_strtolower(trim($s), 'UTF-8');
$s = str_replace(['أ', 'إ', 'آ'], 'ا', $s);
$s = str_replace('ة', 'ه', $s);
return $s;
}
}
if (!function_exists('ak_infer_gender')) {
function ak_infer_gender(string $fullName): string {
$first = trim(strtok($fullName, ' '));
if ($first === '') return '';
$f = ak_canon_name($first);
static $F = null, $M = null;
if ($F === null) {
$F = array_map('ak_canon_name', ['عائشه','فاطمه','خديجه','مريم','زينب','رقيه','امنه','امينه','حليمه','سعاد','هند','امل','ايمان','ابتسام','احلام','انتصار','الهام','اسماء','هدى','نجاه','اكرام','فضيله','نعمه','رحمه','سلمى','ذكرى','بكريه','عيشه','ساره','منى','مها','ليلى','نجوى','وداد','اقبال','اعتدال','تسنيم','حنان','ختام','صفيه','ضحيه','طيبه','عفاف','غاده','كريمه','لطيفه','هويدا','قمر','محاسن','اشراق','افراح','بركه','تحيه','حسنيه','خيريه','دلال','رباب','رشا','رضيه','سكينه','شفاء','عزيزه','علويه','فتحيه','كوثر','مرام','نفيسه','يسرا']);
$M = array_map('ak_canon_name', ['محمد','احمد','عبدالله','عبدالرحمن','عبدالقادر','عبدالسلام','عمر','عثمان','علي','حسن','حسين','ابراهيم','اسماعيل','خالد','سعد','سعيد','سليمان','صالح','صديق','طارق','عادل','عاطف','عباس','عوض','عيسى','غسان','فاروق','فيصل','كريم','كمال','ماهر','مبارك','مصطفي','معتز','معاذ','مامون','منتصر','منصور','موسى','هاشم','وليد','ياسر','يوسف','زكريا','يحيي','يونس','ايوب','ادم','انور','امجد','امير','ايمن','ايهاب','بشير','بدر','بركات','جمال','جميل','حاتم','حبيب','حسان','الطيب','ابوبكر','الفاضل','الهادي','انس','ازهر','حمزه','اسامه','معاويه','خليفه','طلحه','عبيده','ورقه']);
}
if (in_array($f, $F, true)) return 'female';
if (in_array($f, $M, true)) return 'male';
if (mb_substr($f, -1) === 'ه' || mb_substr($f, -1) === 'ة') return 'female'; // ta-marbuta heuristic
return '';
}
}

/* ---------- maps ---------- */
$letterMap = [];
foreach (dbFetchAll("SELECT id, code FROM letters WHERE is_active = 1") as $L) $letterMap[normalize_arabic_letter($L['code'])] = (int)$L['id'];
$matrix = [];
foreach (dbFetchAll("SELECT sl.supervisor_id, l.code, sl.gender FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id") as $r) {
$n = normalize_arabic_letter($r['code']);
$matrix[$n][$r['gender']] = (int)$r['supervisor_id'];
}

/* ---------- pass ---------- */
$sponsors = dbFetchAll("SELECT id, full_name, first_letter_raw, first_letter_id, gender, supervisor_id, is_manual_override FROM sponsors");
$gFixed = 0; $moved = 0; $skippedOverride = 0; $unknownLeft = [];
foreach ($sponsors as $sp) {
$g = (string)($sp['gender'] ?? '');
if (!in_array($g, ['male', 'female', 'organization'], true)) {
$guess = ak_infer_gender((string)$sp['full_name']);
if ($guess !== '') { dbExecute("UPDATE sponsors SET gender = ? WHERE id = ?", [$guess, $sp['id']]); $g = $guess; $gFixed++; }
else { $g = 'unknown'; $unknownLeft[] = $sp; }
}
if ((int)$sp['is_manual_override'] === 1) { $skippedOverride++; continue; }
[$raw, $norm] = first_letter_of($sp['full_name']);
$letterId = $letterMap[$norm] ?? null;
$matrixSupId = null;
if (isset($matrix[$norm])) {
if (in_array($g, ['male', 'female'], true)) $matrixSupId = $matrix[$norm][$g] ?? $matrix[$norm]['both'] ?? null;
else $matrixSupId = $matrix[$norm]['both'] ?? $matrix[$norm]['male'] ?? $matrix[$norm]['female'] ?? null;
}
$oldSup = (int)($sp['supervisor_id'] ?? 0);
$newSup = (int)($matrixSupId ?? 0);
if ($oldSup !== $newSup || (string)$sp['first_letter_raw'] !== $raw || (int)($sp['first_letter_id'] ?? 0) !== (int)($letterId ?? 0)) {
dbExecute("UPDATE sponsors SET first_letter_raw = ?, first_letter_id = ?, supervisor_id = ? WHERE id = ?", [$raw, $letterId, $newSup ?: null, $sp['id']]);
$moved++;
}
}
try {
dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
VALUES (?, 'SYSTEM_SYNC', 'sponsors', NULL, ?, ?, ?, ?)",
[Session::getUserId(),
json_encode(['tool' => 'sync_sponsors_v2'], JSON_UNESCAPED_UNICODE),
json_encode(['gender_inferred' => $gFixed, 'relinked' => $moved, 'overrides_skipped' => $skippedOverride, 'unknown_left' => count($unknownLeft)], JSON_UNESCAPED_UNICODE),
$_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
} catch (Throwable $e) {}
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in"><h2>تقرير مزامنة الكفلاء</h2></div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="row g-3">
<div class="col-md-3"><div class="card text-center"><div class="card-body"><h3><?php echo $gFixed; ?></h3><div>تم استنتاج الجنس تلقائياً</div></div></div></div>
<div class="col-md-3"><div class="card text-center"><div class="card-body"><h3><?php echo $moved; ?></h3><div>تمت إعادة ربطهم بالمصفوفة</div></div></div></div>
<div class="col-md-3"><div class="card text-center"><div class="card-body"><h3><?php echo $skippedOverride; ?></h3><div>تجاوزات يدوية محمية</div></div></div></div>
<div class="col-md-3"><div class="card text-center"><div class="card-body"><h3><?php echo count($unknownLeft); ?></h3><div>بقيت بدون جنس (تحتاج مراجعة)</div></div></div></div>
</div>
<?php if ($unknownLeft): ?>
<div class="card mt-3"><div class="card-body">
<h6>كفلاء لم يمكن استنتاج جنسهم — عدّلهم يدوياً:</h6>
<div class="table-responsive"><table class="table table-sm">
<thead><tr><th>الكود</th><th>الاسم</th><th></th></tr></thead><tbody>
<?php foreach (array_slice($unknownLeft, 0, 100) as $u): ?>
<tr><td><?php echo e($u['sponsor_code'] ?? ''); ?></td><td><?php echo e($u['full_name']); ?></td>
<td><a class="btn btn-sm btn-warning" href="<?php echo APP_URL; ?>modules/sponsors/edit.php?id=<?php echo (int)$u['id']; ?>">تعديل</a></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</div></div>
<?php endif; ?>
<div class="mt-3"><a href="<?php echo APP_URL; ?>modules/sponsors/index.php" class="btn btn-primary">العودة لقائمة الكفلاء</a></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>