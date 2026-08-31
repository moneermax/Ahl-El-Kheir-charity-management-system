<<<<<<< HEAD
<?php
// modules/system/gender_backfill.php - one-time sponsor gender backfill assistant (Admin only)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
if (Session::getUserRole() !== 'admin') { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle = 'تعبئة جنس الكفلاء';
$active    = 'system';

/* ================= classifier ================= */
function ak_first_token(string $name): string {
    $name = trim((string)preg_replace('/\s+/u', ' ', $name));
    $p = explode(' ', $name);
    return $p[0] ?? '';
}
function ak_guess_gender(string $fullName): array { // [gender, reason, confidence]
    $t = ak_first_token($fullName);
    if ($t === '') return ['', 'لا يوجد اسم', ''];
    $n = normalize_arabic_letter($t);

    static $maleH = null; // male exceptions that END with ه/ة
    static $male  = null;
    static $female = null;
    if ($male === null) {
        $maleH = ['اسامه','حمزه','معاويه','عطيه','عبيده','طلحه','خليفه','جمعه','سلامه'];
        $male = ['احمد','محمد','مصطفي','ابراهيم','خالد','عمر','ياسر','طارق','يوسف','اسماعيل','ايوب','صهيب','معاذ','مجتبي','منتصر','معتز','وليد','هاشم','حاتم','حيدر','صدام','عمار','امجد','اشرف','ايمن','انور','عثمان','علي','حسن','حسين','عادل','كمال','صلاح','فيصل','مامون','بدر','بابكر','تاج','جعفر','خليل','راشد','زكريا','سامي','سعود','سليمان','صادق','عباس','عصام','عطا','غسان','فتحي','فاروق','ماهر','مجدي','محجوب','مدثر','معتصم','معز','مكي','منذر','مهدي','ناصر','نزار','وائل','وسام','ياسين','يعقوب','يونس','هشام','هاني','مبارك','توفيق','الفاضل','الطيب','السر','بخيت','موسى','عيسي','يحي','الزين','جبريل','كمال'];
        $female = ['فاطمه','خديجه','عائشه','مريم','امينه','زينب','ساره','سلمي','سميه','هاله','ناديه','نجلاء','هبه','هدي','منال','مني','مها','مروه','امل','الهام','امنه','انعام','بثينه','بخيته','بدرية','تغريد','تماضر','تهاني','ثريا','جواهر','جيهان','حسنه','حليمه','خالده','داليا','رانيا','راويه','رباب','رحاب','رحمه','رشيده','رقيه','روضه','ريهام','سعاد','سلافه','سناء','سهام','سهير','سوزان','سوسن','سيده','شيراز','شيرين','صفاء','ضحي','طيبه','عزه','عزيزه','علويه','فايزه','فردوس','فريده','كلثوم','كوثر','لبني','لمياء','ماجده','ماريا','مجاهده','مدينه','مستوره','مشاعر','مشيره','معزه','مناهل','منيره','مهيله','مواهب','ميسون','ناهد','نبيله','نجود','نفيسه','نهي','هاجر','هديل','هنادي','هنديه','هويده','وجدان','ولاء','ياسمين','يسري','حياة','بلقيس','تسابيح','تسنيم','احلام','اريج','اسماء','اسراء','اماني','اميمه','انصاف','ايمان','ايثار','بتول','بشري','ثويبه','احسان','اكرام','الهام','تقوي','جواهر'];
    }

    if (in_array($n, $male, true))   return ['male', 'اسم مذكر معروف', 'high'];
    if (in_array($n, $female, true)) return ['female', 'اسم مؤنث معروف', 'high'];
    if (str_starts_with($n, 'عبد'))  return ['male', 'بادئة عبد', 'high'];
    if (str_starts_with($n, 'ابو'))  return ['male', 'بادئة أبو', 'high'];
    if ($n === 'ام')                 return ['female', 'كنية أم', 'high'];
    if (preg_match('/[ةه]$/u', $n) && mb_strlen($n) >= 3 && !in_array($n, $maleH, true)) {
        return ['female', 'نهاية مؤنثة (ة/ه)', 'high'];
    }
    if (preg_match('/اء$/u', $n))    return ['female', 'نهاية (اء) — راجع', 'medium'];
    return ['', 'غير محدد — أدخل يدوياً', ''];
}

/* ================= actions ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
    } else {
        if (isset($_POST['auto_apply'])) {
            $missing = dbFetchAll("SELECT id, full_name FROM sponsors WHERE gender IS NULL OR gender = ''");
            $count = 0;
            foreach ($missing as $s) {
                [$g, , $conf] = ak_guess_gender((string)$s['full_name']);
                if ($conf === 'high' && $g !== '') {
                    dbExecute("UPDATE sponsors SET gender = ? WHERE id = ?", [$g, (int)$s['id']]);
                    $count++;
                }
            }
            dbExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent)
                 VALUES (?, 'GENDER_BACKFILL', 'sponsors', 0, ?, ?, ?)",
                [Session::getUserId(), json_encode(['auto_applied' => $count], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
            );
            flash('success', "تمت تعبئة الجنس تلقائياً لعدد {$count} كفيل (ثقة عالية فقط).");
        }
        if (isset($_POST['save_manual'])) {
            $count = 0;
            foreach ($_POST['g'] ?? [] as $sid => $g) {
                $sid = (int)$sid;
                $g = in_array($g, ['male', 'female'], true) ? $g : '';
                if ($g === '') continue;
                dbExecute("UPDATE sponsors SET gender = ? WHERE id = ?", [$g, $sid]);
                $count++;
            }
            dbExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent)
                 VALUES (?, 'GENDER_BACKFILL', 'sponsors', 0, ?, ?, ?)",
                [Session::getUserId(), json_encode(['manual_saved' => $count], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
            );
            flash('success', "تم حفظ {$count} تعديل يدوي للجنس.");
        }
    }
    redirect('modules/system/gender_backfill.php');
}

/* ================= data ================= */
$stats = dbFetchOne("
    SELECT COUNT(*) total,
           SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) m,
           SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) f,
           SUM(CASE WHEN gender IS NULL OR gender = '' THEN 1 ELSE 0 END) empty
    FROM sponsors
");
$missing = dbFetchAll("SELECT id, sponsor_code, full_name FROM sponsors WHERE gender IS NULL OR gender = '' ORDER BY full_name LIMIT 500");

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
<h2>تعبئة جنس الكفلاء (أداة لمرة واحدة)</h2>
<p>اقتراح تلقائي مبني على قواعد الأسماء العربية — الثقة العالية تُطبَّق بزر، والباقي مراجعة يدوية قبل الحفظ.</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="row g-4 mb-4 fade-in">
<div class="col-6 col-md-3"><div class="card text-center"><div class="card-body">
<div class="fs-4 fw-bold" style="color:#1b4d8f"><?php echo (int)$stats['total']; ?></div><div class="text-muted small">إجمالي الكفلاء</div>
</div></div></div>
<div class="col-6 col-md-3"><div class="card text-center"><div class="card-body">
<div class="fs-4 fw-bold" style="color:#1b4d8f"><?php echo (int)$stats['m']; ?></div><div class="text-muted small">ذكور</div>
</div></div></div>
<div class="col-6 col-md-3"><div class="card text-center"><div class="card-body">
<div class="fs-4 fw-bold" style="color:#8f1b4d"><?php echo (int)$stats['f']; ?></div><div class="text-muted small">إناث</div>
</div></div></div>
<div class="col-6 col-md-3"><div class="card text-center"><div class="card-body">
<div class="fs-4 fw-bold text-danger"><?php echo (int)$stats['empty']; ?></div><div class="text-muted small">بدون جنس</div>
</div></div></div>
</div>

<div class="card mb-4 fade-in">
<div class="card-body d-flex justify-content-between align-items-center">
<div>
<h6 class="mb-1"><i class="fas fa-magic me-2"></i>تطبيق تلقائي (ثقة عالية فقط)</h6>
<small class="text-muted">قواعد: بادئة عبد/أبو ⇒ ذكر · كنية أم ⇒ أنثى · نهاية ة/ه ⇒ أنثى (مع قائمة استثناءات ذكورية) · قوائم أسماء معروفة.</small>
</div>
<form method="post" onsubmit="return confirm('تطبيق الاقتراحات عالية الثقة على جميع الكفلاء بدون جنس؟');">
<?php echo csrf_field(); ?>
<button name="auto_apply" value="1" class="btn btn-warning"><i class="fas fa-magic me-1"></i> تطبيق تلقائي</button>
</form>
</div>
</div>

<div class="card fade-in">
<div class="card-header"><i class="fas fa-user-edit me-2"></i>مراجعة يدوية (الكفلاء بدون جنس — أول 500)</div>
<div class="card-body p-2">
<form method="post">
<?php echo csrf_field(); ?>
<div class="table-responsive">
<table class="table table-hover align-middle bg-white mb-0">
<thead><tr><th>الكود</th><th>الاسم</th><th>الاقتراح</th><th style="width:160px">الجنس النهائي</th></tr></thead>
<tbody>
<?php if (!$missing): ?>
<tr><td colspan="4" class="text-center text-success py-4"><i class="fas fa-check-circle me-2"></i>ممتاز — جميع الكفلاء لديهم جنس.</td></tr>
<?php else: foreach ($missing as $s):
    [$g, $reason, $conf] = ak_guess_gender((string)$s['full_name']);
?>
<tr>
<td><?php echo e($s['sponsor_code']); ?></td>
<td><strong><?php echo e($s['full_name']); ?></strong></td>
<td>
<?php if ($g !== ''): ?>
<span class="badge <?php echo $g === 'male' ? 'bg-primary' : 'bg-danger'; ?>"><?php echo $g === 'male' ? 'ذكر' : 'أنثى'; ?></span>
<small class="text-muted"><?php echo e($reason); ?></small>
<?php else: ?><span class="text-muted"><?php echo e($reason); ?></span><?php endif; ?>
</td>
<td>
<select name="g[<?php echo (int)$s['id']; ?>]" class="form-select form-select-sm">
<option value="">— اختر —</option>
<option value="male" <?php echo $g === 'male' ? 'selected' : ''; ?>>ذكر</option>
<option value="female" <?php echo $g === 'female' ? 'selected' : ''; ?>>أنثى</option>
</select>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
<?php if ($missing): ?>
<div class="p-2">
<button name="save_manual" value="1" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ المحدد</button>
</div>
<?php endif; ?>
</form>
</div>
</div>
=======
<?php
// modules/system/gender_backfill.php - one-time sponsor gender backfill assistant (Admin only)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
if (Session::getUserRole() !== 'admin') { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle = 'تعبئة جنس الكفلاء';
$active    = 'system';

/* ================= classifier ================= */
function ak_first_token(string $name): string {
    $name = trim((string)preg_replace('/\s+/u', ' ', $name));
    $p = explode(' ', $name);
    return $p[0] ?? '';
}
function ak_guess_gender(string $fullName): array { // [gender, reason, confidence]
    $t = ak_first_token($fullName);
    if ($t === '') return ['', 'لا يوجد اسم', ''];
    $n = normalize_arabic_letter($t);

    static $maleH = null; // male exceptions that END with ه/ة
    static $male  = null;
    static $female = null;
    if ($male === null) {
        $maleH = ['اسامه','حمزه','معاويه','عطيه','عبيده','طلحه','خليفه','جمعه','سلامه'];
        $male = ['احمد','محمد','مصطفي','ابراهيم','خالد','عمر','ياسر','طارق','يوسف','اسماعيل','ايوب','صهيب','معاذ','مجتبي','منتصر','معتز','وليد','هاشم','حاتم','حيدر','صدام','عمار','امجد','اشرف','ايمن','انور','عثمان','علي','حسن','حسين','عادل','كمال','صلاح','فيصل','مامون','بدر','بابكر','تاج','جعفر','خليل','راشد','زكريا','سامي','سعود','سليمان','صادق','عباس','عصام','عطا','غسان','فتحي','فاروق','ماهر','مجدي','محجوب','مدثر','معتصم','معز','مكي','منذر','مهدي','ناصر','نزار','وائل','وسام','ياسين','يعقوب','يونس','هشام','هاني','مبارك','توفيق','الفاضل','الطيب','السر','بخيت','موسى','عيسي','يحي','الزين','جبريل','كمال'];
        $female = ['فاطمه','خديجه','عائشه','مريم','امينه','زينب','ساره','سلمي','سميه','هاله','ناديه','نجلاء','هبه','هدي','منال','مني','مها','مروه','امل','الهام','امنه','انعام','بثينه','بخيته','بدرية','تغريد','تماضر','تهاني','ثريا','جواهر','جيهان','حسنه','حليمه','خالده','داليا','رانيا','راويه','رباب','رحاب','رحمه','رشيده','رقيه','روضه','ريهام','سعاد','سلافه','سناء','سهام','سهير','سوزان','سوسن','سيده','شيراز','شيرين','صفاء','ضحي','طيبه','عزه','عزيزه','علويه','فايزه','فردوس','فريده','كلثوم','كوثر','لبني','لمياء','ماجده','ماريا','مجاهده','مدينه','مستوره','مشاعر','مشيره','معزه','مناهل','منيره','مهيله','مواهب','ميسون','ناهد','نبيله','نجود','نفيسه','نهي','هاجر','هديل','هنادي','هنديه','هويده','وجدان','ولاء','ياسمين','يسري','حياة','بلقيس','تسابيح','تسنيم','احلام','اريج','اسماء','اسراء','اماني','اميمه','انصاف','ايمان','ايثار','بتول','بشري','ثويبه','احسان','اكرام','الهام','تقوي','جواهر'];
    }

    if (in_array($n, $male, true))   return ['male', 'اسم مذكر معروف', 'high'];
    if (in_array($n, $female, true)) return ['female', 'اسم مؤنث معروف', 'high'];
    if (str_starts_with($n, 'عبد'))  return ['male', 'بادئة عبد', 'high'];
    if (str_starts_with($n, 'ابو'))  return ['male', 'بادئة أبو', 'high'];
    if ($n === 'ام')                 return ['female', 'كنية أم', 'high'];
    if (preg_match('/[ةه]$/u', $n) && mb_strlen($n) >= 3 && !in_array($n, $maleH, true)) {
        return ['female', 'نهاية مؤنثة (ة/ه)', 'high'];
    }
    if (preg_match('/اء$/u', $n))    return ['female', 'نهاية (اء) — راجع', 'medium'];
    return ['', 'غير محدد — أدخل يدوياً', ''];
}

/* ================= actions ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'انتهت صلاحية الجلسة، حاول مرة أخرى.');
    } else {
        if (isset($_POST['auto_apply'])) {
            $missing = dbFetchAll("SELECT id, full_name FROM sponsors WHERE gender IS NULL OR gender = ''");
            $count = 0;
            foreach ($missing as $s) {
                [$g, , $conf] = ak_guess_gender((string)$s['full_name']);
                if ($conf === 'high' && $g !== '') {
                    dbExecute("UPDATE sponsors SET gender = ? WHERE id = ?", [$g, (int)$s['id']]);
                    $count++;
                }
            }
            dbExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent)
                 VALUES (?, 'GENDER_BACKFILL', 'sponsors', 0, ?, ?, ?)",
                [Session::getUserId(), json_encode(['auto_applied' => $count], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
            );
            flash('success', "تمت تعبئة الجنس تلقائياً لعدد {$count} كفيل (ثقة عالية فقط).");
        }
        if (isset($_POST['save_manual'])) {
            $count = 0;
            foreach ($_POST['g'] ?? [] as $sid => $g) {
                $sid = (int)$sid;
                $g = in_array($g, ['male', 'female'], true) ? $g : '';
                if ($g === '') continue;
                dbExecute("UPDATE sponsors SET gender = ? WHERE id = ?", [$g, $sid]);
                $count++;
            }
            dbExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent)
                 VALUES (?, 'GENDER_BACKFILL', 'sponsors', 0, ?, ?, ?)",
                [Session::getUserId(), json_encode(['manual_saved' => $count], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
            );
            flash('success', "تم حفظ {$count} تعديل يدوي للجنس.");
        }
    }
    redirect('modules/system/gender_backfill.php');
}

/* ================= data ================= */
$stats = dbFetchOne("
    SELECT COUNT(*) total,
           SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) m,
           SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) f,
           SUM(CASE WHEN gender IS NULL OR gender = '' THEN 1 ELSE 0 END) empty
    FROM sponsors
");
$missing = dbFetchAll("SELECT id, sponsor_code, full_name FROM sponsors WHERE gender IS NULL OR gender = '' ORDER BY full_name LIMIT 500");

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
<h2>تعبئة جنس الكفلاء (أداة لمرة واحدة)</h2>
<p>اقتراح تلقائي مبني على قواعد الأسماء العربية — الثقة العالية تُطبَّق بزر، والباقي مراجعة يدوية قبل الحفظ.</p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="row g-4 mb-4 fade-in">
<div class="col-6 col-md-3"><div class="card text-center"><div class="card-body">
<div class="fs-4 fw-bold" style="color:#1b4d8f"><?php echo (int)$stats['total']; ?></div><div class="text-muted small">إجمالي الكفلاء</div>
</div></div></div>
<div class="col-6 col-md-3"><div class="card text-center"><div class="card-body">
<div class="fs-4 fw-bold" style="color:#1b4d8f"><?php echo (int)$stats['m']; ?></div><div class="text-muted small">ذكور</div>
</div></div></div>
<div class="col-6 col-md-3"><div class="card text-center"><div class="card-body">
<div class="fs-4 fw-bold" style="color:#8f1b4d"><?php echo (int)$stats['f']; ?></div><div class="text-muted small">إناث</div>
</div></div></div>
<div class="col-6 col-md-3"><div class="card text-center"><div class="card-body">
<div class="fs-4 fw-bold text-danger"><?php echo (int)$stats['empty']; ?></div><div class="text-muted small">بدون جنس</div>
</div></div></div>
</div>

<div class="card mb-4 fade-in">
<div class="card-body d-flex justify-content-between align-items-center">
<div>
<h6 class="mb-1"><i class="fas fa-magic me-2"></i>تطبيق تلقائي (ثقة عالية فقط)</h6>
<small class="text-muted">قواعد: بادئة عبد/أبو ⇒ ذكر · كنية أم ⇒ أنثى · نهاية ة/ه ⇒ أنثى (مع قائمة استثناءات ذكورية) · قوائم أسماء معروفة.</small>
</div>
<form method="post" onsubmit="return confirm('تطبيق الاقتراحات عالية الثقة على جميع الكفلاء بدون جنس؟');">
<?php echo csrf_field(); ?>
<button name="auto_apply" value="1" class="btn btn-warning"><i class="fas fa-magic me-1"></i> تطبيق تلقائي</button>
</form>
</div>
</div>

<div class="card fade-in">
<div class="card-header"><i class="fas fa-user-edit me-2"></i>مراجعة يدوية (الكفلاء بدون جنس — أول 500)</div>
<div class="card-body p-2">
<form method="post">
<?php echo csrf_field(); ?>
<div class="table-responsive">
<table class="table table-hover align-middle bg-white mb-0">
<thead><tr><th>الكود</th><th>الاسم</th><th>الاقتراح</th><th style="width:160px">الجنس النهائي</th></tr></thead>
<tbody>
<?php if (!$missing): ?>
<tr><td colspan="4" class="text-center text-success py-4"><i class="fas fa-check-circle me-2"></i>ممتاز — جميع الكفلاء لديهم جنس.</td></tr>
<?php else: foreach ($missing as $s):
    [$g, $reason, $conf] = ak_guess_gender((string)$s['full_name']);
?>
<tr>
<td><?php echo e($s['sponsor_code']); ?></td>
<td><strong><?php echo e($s['full_name']); ?></strong></td>
<td>
<?php if ($g !== ''): ?>
<span class="badge <?php echo $g === 'male' ? 'bg-primary' : 'bg-danger'; ?>"><?php echo $g === 'male' ? 'ذكر' : 'أنثى'; ?></span>
<small class="text-muted"><?php echo e($reason); ?></small>
<?php else: ?><span class="text-muted"><?php echo e($reason); ?></span><?php endif; ?>
</td>
<td>
<select name="g[<?php echo (int)$s['id']; ?>]" class="form-select form-select-sm">
<option value="">— اختر —</option>
<option value="male" <?php echo $g === 'male' ? 'selected' : ''; ?>>ذكر</option>
<option value="female" <?php echo $g === 'female' ? 'selected' : ''; ?>>أنثى</option>
</select>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
<?php if ($missing): ?>
<div class="p-2">
<button name="save_manual" value="1" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ المحدد</button>
</div>
<?php endif; ?>
</form>
</div>
</div>
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>