<?php
// modules/families/orphan_form.php - Digital orphan form: exact paper replica (filled/blank) + edit + photo + print
// Package 6 revision: accepts ?id= AND ?child=; post-pivot sponsorships (sp.child_id); replica preserved.
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'nanny', 'accountant', 'accountant_staff', 'administration', 'social_media'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}
$canEdit = in_array($role, ['admin', 'vice_general_manager', 'supervisor', 'nanny'], true);
$returnQuery=trim((string)($_GET['return']??$_POST['return']??'')); $backUrl=APP_URL.'modules/families/index.php'; if($returnQuery!==''){$backUrl.='?'.ltrim(rawurldecode($returnQuery),'?');}
$pageTitle = t('Orphan Registration Form');
$active = 'families';
$isEn = ($_SESSION['lang'] ?? 'ar') === 'en';

/* - photo streaming endpoint (guaranteed display) - */
if (isset($_GET['photo'])) {
    $pid = (int)$_GET['photo'];
    $prow = dbFetchOne("SELECT photo_path FROM family_children WHERE id = ?", [$pid]);
    if ($prow && !empty($prow['photo_path'])) {
        $f = dirname(__DIR__, 2) . '/' . $prow['photo_path'];
        if (is_file($f)) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'][$ext] ?? 'image/jpeg';
            header('Content-Type: ' . $mime);
            header('Cache-Control: private, max-age=3600');
            readfile($f);
            exit;
        }
    }
    http_response_code(404); exit;
}

$AK_ORPHAN_OPTS = include dirname(__DIR__, 2) . '/includes/orphan_options.php';

/* - idempotent schema evolution (incl. photo + form serial) - */
try {
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS nationality VARCHAR(50) NOT NULL DEFAULT 'سودانية'");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS health_status VARCHAR(150) DEFAULT NULL");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS health_status_other VARCHAR(150) DEFAULT NULL");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS psychological_state VARCHAR(150) DEFAULT NULL");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS psychological_state_other VARCHAR(150) DEFAULT NULL");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS guardian_name VARCHAR(150) DEFAULT NULL");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS guardian_relationship VARCHAR(60) DEFAULT NULL");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) DEFAULT NULL");
    dbExecute("ALTER TABLE family_children ADD COLUMN IF NOT EXISTS form_serial VARCHAR(20) DEFAULT NULL");
    dbExecute("ALTER TABLE families ADD COLUMN IF NOT EXISTS father_death_cause VARCHAR(150) DEFAULT NULL");
    dbExecute("ALTER TABLE families ADD COLUMN IF NOT EXISTS mother_marital_status VARCHAR(30) DEFAULT 'أرملة'");
} catch (Throwable $e) {}

/* - load child (FIX: accept ?id= from families buttons AND ?child= internally) - */
$childId = (int)($_GET['child'] ?? $_GET['id'] ?? 0);
$blankMode = isset($_GET['blank']);
$child = $childId ? dbFetchOne("SELECT * FROM family_children WHERE id = ?", [$childId]) : null;
$fam = null;
if ($child) {
    $fam = dbFetchOne("SELECT * FROM families WHERE id = ?", [(int)$child['family_id']]);
    if (!$fam) { flash('error', t('Family not found.')); redirect('modules/families/index.php'); }
    if ($role === 'nanny' && (int)($fam['nanny_id'] ?? 0) !== Session::getUserId()) {
        flash('error', t('Access denied for this family.'));
        redirect('dashboard/nanny_dashboard.php');
    }
    /* lazy-assign a unique form serial (separate from orphan id) */
    if (empty($child['form_serial'])) {
        $mx = dbFetchOne("SELECT MAX(CAST(SUBSTRING(form_serial, 4) AS UNSIGNED)) m FROM family_children WHERE form_serial LIKE 'AK-%'");
        $serial = 'AK-' . str_pad((string)(((int)($mx['m'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT);
        dbExecute("UPDATE family_children SET form_serial = ? WHERE id = ?", [$serial, $childId]);
        $child['form_serial'] = $serial;
    }
}
if (!$child && !$blankMode) { flash('error', t('Child not found.')); redirect('modules/families/index.php'); }

/* - save (fields + photo) - */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit && $child) {
    if (!verify_csrf()) {
        flash('error', t('Session expired.'));
    } else {
        $health = trim($_POST['health_status'] ?? 'سليم');
        $psych = trim($_POST['psychological_state'] ?? 'سليم');
        $healthOther = $health === 'أخرى' ? trim($_POST['health_status_other'] ?? '') : null;
        $psychOther = $psych === 'أخرى' ? trim($_POST['psychological_state_other'] ?? '') : null;
        $old = $child;
        dbExecute("UPDATE family_children SET nationality = ?, health_status = ?, health_status_other = ?,
            psychological_state = ?, psychological_state_other = ?, guardian_name = ?, guardian_relationship = ?, updated_at = NOW()
            WHERE id = ?",
            [trim($_POST['nationality'] ?? '') !== '' ? trim($_POST['nationality']) : 'سودانية',
            $health, $healthOther, $psych, $psychOther,
            trim($_POST['guardian_name'] ?? '') ?: null,
            trim($_POST['guardian_relationship'] ?? '') ?: null,
            $childId]);
        dbExecute("UPDATE families SET father_death_cause = ?, mother_marital_status = ?, updated_by = ? WHERE id = ?",
            [trim($_POST['father_death_cause'] ?? '') ?: null,
            trim($_POST['mother_marital_status'] ?? '') !== '' ? trim($_POST['mother_marital_status']) : 'أرملة',
            Session::getUserId(), (int)$fam['id']]);
        /* remove photo */
        if (isset($_POST['remove_photo']) && !empty($child['photo_path'])) {
            dbExecute("UPDATE family_children SET photo_path = NULL WHERE id = ?", [$childId]);
        }
        /* photo upload (up to 10MB) */
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                flash('error', t('Photo format must be JPG or PNG.'));
            } elseif ($_FILES['photo']['size'] > 10 * 1024 * 1024) {
                flash('error', t('Photo size must not exceed 10MB.'));
            } else {
                $dir = dirname(__DIR__, 2) . '/storage/photos';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $file = 'child_' . $childId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $file)) {
                    dbExecute("UPDATE family_children SET photo_path = ? WHERE id = ?", ['storage/photos/' . $file, $childId]);
                } else {
                    flash('error', t('Could not save the photo on server.'));
                }
            }
        }
        try { dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, 'update_orphan', 'family_children', ?, ?, ?, ?, ?)",
            [Session::getUserId(), $childId, json_encode($old, JSON_UNESCAPED_UNICODE),
             json_encode(['health' => $health, 'psych' => $psych, 'guardian' => trim($_POST['guardian_name'] ?? '')], JSON_UNESCAPED_UNICODE),
             $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']); } catch (Throwable $e) {}
        flash('success', t('Form data saved successfully.'));
    }
    redirect('modules/families/orphan_form.php?child=' . $childId . (isset($_GET['tab']) ? '&tab=' . e($_GET['tab']) : '') . ($returnQuery !== '' ? '&return=' . rawurlencode($returnQuery) : ''));
}

/* - display values - */
$editTab = isset($_GET['tab']) && $_GET['tab'] === 'edit' && $child && $canEdit;
$dmy = function ($d) { return $d ? date('d/m/Y', strtotime($d)) : ''; };
$hasPhoto = ($child && !empty($child['photo_path']));
$photoUrl = $hasPhoto
    ? APP_URL . 'modules/families/orphan_form.php?photo=' . $childId
    : ($child ? asset('img/orphan-placeholder.jpg') : null);
$ships = []; $totalMonthly = 0; $firstStart = '';
if ($child) {
    $healthShow = $child['health_status'] ?? 'سليم';
    if ($healthShow === 'أخرى' && !empty($child['health_status_other'])) $healthShow = $child['health_status_other'];
    $psychShow = $child['psychological_state'] ?? 'سليم';
    if ($psychShow === 'أخرى' && !empty($child['psychological_state_other'])) $psychShow = $child['psychological_state_other'];
    $age = '';
    if (!empty($child['birth_date'])) {
        $age = (new DateTime($child['birth_date']))->diff(new DateTime('today'))->y . ' ' . t('years');
    }
    /* FIX: post-pivot — sponsorships link via child_id (sp.family_id no longer exists) */
    $ships = dbFetchAll("SELECT sp.monthly_amount, sp.start_date, s.full_name sname, s.sponsor_code
        FROM sponsorships sp JOIN sponsors s ON s.id = sp.sponsor_id
        WHERE sp.child_id = ? AND sp.status IN ('active','paused') ORDER BY sp.id", [(int)$childId]);
    foreach ($ships as $sh) { $totalMonthly += (float)$sh['monthly_amount']; if ($firstStart === '') $firstStart = $sh['start_date']; }
}
$V = function ($x) { return ($x !== null && $x !== '') ? e($x) : '<span class="ak-blank"></span>'; };
$akPrintOwnLetterhead = true; // official layout with its own letterhead: see assets/css/print.css
include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.ak-toolbar{max-width:210mm;margin:12px auto 0}
.ak-sheet{width:210mm;margin:10px auto;background:#fff;padding:8mm 8mm 6mm;position:relative;color:#000;box-shadow:0 2px 14px rgba(0,0,0,.15);display:flex;flex-direction:column}
.ak-sheet.blank{min-height:296mm}
.ak-basmala{text-align:center;font-size:20px;line-height:1.4}
.ak-org{text-align:center;color:#178a3f;font-weight:800;font-size:21px}
.ak-band-row{position:relative}
.ak-logo{position:absolute;top:50%;transform:translateY(-50%);right:0;width:27mm;height:27mm;border-radius:50%;overflow:hidden;border:1px solid #bbb;background:#fff;display:flex;align-items:center;justify-content:center}
.ak-logo img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.ak-flourish{display:block;width:360px;max-width:85%;height:30px;margin:1px auto}
.ak-band-wrap{width:fit-content;margin:0 auto;padding:2px;background:#8d5a5a;clip-path:polygon(0 10px,10px 10px,10px 0,calc(100% - 10px) 0,calc(100% - 10px) 10px,100% 10px,100% calc(100% - 10px),calc(100% - 10px) calc(100% - 10px),calc(100% - 10px) 100%,10px 100%,10px calc(100% - 10px),0 calc(100% - 10px))}
.ak-band{background:linear-gradient(180deg,#f2d2d2 0%,#e5bcbc 55%,#d9a7a7 100%);color:#000;text-align:center;font-weight:800;font-size:21px;padding:10px 80px;clip-path:polygon(0 10px,10px 10px,10px 0,calc(100% - 10px) 0,calc(100% - 10px) 10px,100% 10px,100% calc(100% - 10px),calc(100% - 10px) calc(100% - 10px),calc(100% - 10px) 100%,10px 100%,10px calc(100% - 10px),0 calc(100% - 10px))}
.ak-verse{text-align:center;font-size:10.5px;margin-bottom:4px}
.ak-meta{display:flex;justify-content:space-between;align-items:center;font-size:12px;font-weight:700;margin:4px 0}
.ak-code{color:#0a7a2f;font-size:12px}
table.ak-strip{width:100%;border-collapse:collapse;font-size:11px;margin-bottom:5px}
table.ak-strip th,table.ak-strip td{border:1px solid #000;padding:2px 6px;text-align:right}
table.ak-main{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:5px}
table.ak-main th,table.ak-main td{border:1px solid #000;padding:4px 7px;text-align:right;vertical-align:top}
table.ak-main th{width:18%;font-weight:700}
.ak-photo{width:30mm;height:30mm;background:#fff;text-align:center;padding:2px;vertical-align:middle;box-sizing:border-box;overflow:hidden}
.ak-photo img{display:block;width:100%;height:100%;object-fit:cover}
.ak-blank{display:inline-block;min-width:28mm;min-height:4mm;border-bottom:1px dotted #000}
.ak-motto td{color:#d00000;font-weight:800;text-align:center;font-size:11.5px}
.ak-red{color:#d00000;font-weight:700;font-size:11px;margin-top:5px}
.ak-green{color:#0a7a2f;font-weight:700;font-size:11.5px;margin-top:4px;text-align:center}
.ak-ribbon-wrap{position:relative;width:130px;margin-top:8mm}
.ak-ribbon{background:linear-gradient(180deg,#b6a6c9 0%,#a593bd 60%,#9b89b5 100%);padding:12px 10px 26px;text-align:center;font-weight:700;font-size:12px;color:#1d1430;line-height:1.9;clip-path:polygon(0 0,100% 0,100% 78%,50% 100%,0 78%)}
.ak-ribbon-fold{position:absolute;top:0;right:-15px;width:15px;height:30px;background:linear-gradient(180deg,#83719f,#5e4f7d);clip-path:polygon(0 0,100% 14%,100% 100%,0 86%);border-radius:0 8px 8px 0}
.ak-wave{position:relative;height:16mm;margin-top:0mm}
.ak-sheet.blank .ak-wave{margin-top:auto}
.ak-sheet.en-mode{font-family:'Segoe UI',Tahoma,sans-serif !important}
.ak-sheet.en-mode .ak-band{font-size:18px !important;padding:10px 40px !important}
.ak-sheet.en-mode .ak-org{font-size:18px !important}
.ak-sheet.en-mode .ak-verse{font-size:9px !important}
.ak-sheet.en-mode table.ak-main th,.ak-sheet.en-mode table.ak-main td{font-size:10px !important;padding:3px 4px !important}
.ak-sheet.en-mode .ak-ribbon{font-size:10px !important;padding:10px 8px 20px !important}
.ak-sheet.en-mode .ak-motto td{font-size:9.5px !important}
.ak-sheet.en-mode .ak-green{font-size:10px !important}
.ak-sheet.en-mode .ak-basmala{font-size:16px !important}
@media print{.sidebar,.topbar,.app-footer,.no-print,.ak-toolbar{display:none !important}.content{padding:0 !important}body{background:#fff !important}.ak-sheet{width:auto;margin:0;box-shadow:none}*{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>

<div class="ak-toolbar no-print d-flex gap-2 flex-wrap justify-content-center">
<?php if ($child): ?>
<a class="btn btn-secondary btn-sm" href="<?php echo e($backUrl); ?>" onclick="return akGoBack(this.href);"><i class="fas fa-arrow-right me-1"></i><?php echo t('Family'); ?></a>
<a class="btn <?php echo $editTab ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm" href="<?php echo APP_URL; ?>modules/families/orphan_form.php?child=<?php echo $childId; ?>&tab=edit"><i class="fas fa-pen me-1"></i><?php echo t('Fill Data'); ?></a>
<a class="btn <?php echo !$editTab ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm" href="<?php echo APP_URL; ?>modules/families/orphan_form.php?child=<?php echo $childId; ?>"><i class="fas fa-file-signature me-1"></i><?php echo t('Form View'); ?></a>
<?php if (!$editTab): ?><button class="btn btn-success btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i><?php echo t('Print / PDF'); ?></button><?php endif; ?>
<?php endif; ?>
<a class="btn btn-outline-secondary btn-sm" href="<?php echo APP_URL; ?>modules/families/orphan_form.php?blank=1"><i class="fas fa-file me-1"></i><?php echo t('Blank Form'); ?></a>
<?php if ($blankMode): ?><button class="btn btn-success btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i><?php echo t('Print Blank Form'); ?></button><?php endif; ?>
</div>

<?php if ($editTab): /* ══════════ EDIT TAB ══════════ */ ?>
<div class="container-fluid py-3" style="max-width:900px">
<div class="card fade-in"><div class="card-header"><i class="fas fa-pen me-2"></i><?php echo t('Fill Form Data — '); ?><?php echo e($child['child_name']); ?></div>
<div class="card-body">
<form method="post" enctype="multipart/form-data" class="row g-3"><?php echo csrf_field(); ?>
<div class="col-md-4"><label class="form-label"><?php echo t('Nationality'); ?></label><input type="text" name="nationality" class="form-control" value="<?php echo e($child['nationality'] ?? 'سودانية'); ?>"></div>
<div class="col-md-4"><label class="form-label"><?php echo t('Health Status'); ?></label><select name="health_status" class="form-select" onchange="document.getElementById('hO').style.display=this.value==='أخرى'?'':'none'"><?php foreach ($AK_ORPHAN_OPTS['health'] as $o): ?><option value="<?php echo e($o); ?>" <?php echo ($child['health_status'] ?? 'سليم') === $o ? 'selected' : ''; ?>><?php echo t($o); ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label"><?php echo t('If other, specify'); ?></label><input type="text" name="health_status_other" id="hO" class="form-control" style="display:<?php echo ($child['health_status'] ?? '') === 'أخرى' ? 'block' : 'none'; ?>" value="<?php echo e($child['health_status_other'] ?? ''); ?>"></div>
<div class="col-md-4"><label class="form-label"><?php echo t('Psychological State'); ?></label><select name="psychological_state" class="form-select" onchange="document.getElementById('pO').style.display=this.value==='أخرى'?'':'none'"><?php foreach ($AK_ORPHAN_OPTS['psych'] as $o): ?><option value="<?php echo e($o); ?>" <?php echo ($child['psychological_state'] ?? 'سليم') === $o ? 'selected' : ''; ?>><?php echo t($o); ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label"><?php echo t('If other, specify'); ?></label><input type="text" name="psychological_state_other" id="pO" class="form-control" style="display:<?php echo ($child['psychological_state'] ?? '') === 'أخرى' ? 'block' : 'none'; ?>" value="<?php echo e($child['psychological_state_other'] ?? ''); ?>"></div>
<div class="col-md-4"><label class="form-label"><?php echo t('Mother Marital Status'); ?></label><select name="mother_marital_status" class="form-select"><?php foreach ($AK_ORPHAN_OPTS['marital'] as $o): ?><option value="<?php echo e($o); ?>" <?php echo ($fam['mother_marital_status'] ?? 'أرملة') === $o ? 'selected' : ''; ?>><?php echo t($o); ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label"><?php echo t('Guardian / Provider Name'); ?></label><input type="text" name="guardian_name" class="form-control" value="<?php echo e($child['guardian_name'] ?? ''); ?>"></div>
<div class="col-md-4"><label class="form-label"><?php echo t('Guardian Relationship'); ?></label><input type="text" name="guardian_relationship" list="akRelList" class="form-control" value="<?php echo e($child['guardian_relationship'] ?? ''); ?>"><datalist id="akRelList"><?php foreach ($AK_ORPHAN_OPTS['relationships'] as $o): ?><option value="<?php echo e($o); ?>"><?php endforeach; ?></datalist></div>
<div class="col-md-4"><label class="form-label"><?php echo t('Father Cause of Death'); ?></label><input type="text" name="father_death_cause" class="form-control" value="<?php echo e($fam['father_death_cause'] ?? ''); ?>"></div>
<div class="col-md-6"><label class="form-label"><?php echo t('Orphan Photo (JPG/PNG ≤ 10MB)'); ?></label><input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png"></div>
<div class="col-md-6 d-flex align-items-center gap-2">
<?php if ($hasPhoto): ?><img src="<?php echo e($photoUrl); ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid #ccc"><label class="form-check-label small ms-2"><input type="checkbox" name="remove_photo" value="1" class="form-check-input"> <?php echo t('Remove Photo'); ?></label><?php elseif ($child): ?><img src="<?php echo e($photoUrl); ?>" alt="<?php echo t('No photo uploaded yet.'); ?>" style="width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid #ccc"><?php else: ?><span class="text-muted small"><?php echo t('No photo uploaded yet.'); ?></span><?php endif; ?>
</div>
<div class="col-12"><button class="btn btn-primary"><i class="fas fa-save me-1"></i><?php echo t('Save'); ?></button>
<a class="btn btn-success" href="<?php echo APP_URL; ?>modules/families/orphan_form.php?child=<?php echo $childId; ?>"><i class="fas fa-file-signature me-1"></i><?php echo t('View Form'); ?></a></div>
</form></div></div></div>

<?php else: /* ══════════ REPLICA SHEET (filled or blank) ══════════ */ ?>
<div class="ak-sheet <?php echo $blankMode ? 'blank' : ''; ?> <?php echo $isEn ? 'en-mode' : ''; ?>">
<div class="ak-basmala"><?php echo t('﷽'); ?></div>
<div class="ak-org"><?php echo t('منظمة أهل الخير النسوية'); ?></div>
<svg class="ak-flourish" viewBox="0 0 420 36" aria-hidden="true"><g fill="none" stroke="#c58a94" stroke-width="2" stroke-linecap="round"><path d="M210 18 C 190 4, 160 4, 150 18 C 160 32, 190 32, 210 18"/><path d="M210 18 C 230 4, 260 4, 270 18 C 260 32, 230 32, 210 18"/><path d="M150 18 C 120 6, 90 10, 70 18 C 90 26, 120 30, 150 18"/><path d="M270 18 C 300 6, 330 10, 350 18 C 330 26, 300 30, 270 18"/></g><g fill="#c58a94"><circle cx="210" cy="18" r="4"/><circle cx="70" cy="18" r="3"/><circle cx="350" cy="18" r="3"/><circle cx="120" cy="9" r="2"/><circle cx="300" cy="9" r="2"/><circle cx="120" cy="27" r="2"/><circle cx="300" cy="27" r="2"/></g></svg>
<div class="ak-band-row">
<div class="ak-logo"><img src="<?php echo asset('img/logo.png'); ?>" alt="<?php echo t('Ahl El Kheir Charity'); ?>"></div>
<div class="ak-band-wrap"><div class="ak-band"><?php echo t('إستمارة يتيم'); ?></div></div>
</div>
<svg class="ak-flourish" viewBox="0 0 420 36" aria-hidden="true"><g fill="none" stroke="#c58a94" stroke-width="2" stroke-linecap="round"><path d="M210 18 C 190 4, 160 4, 150 18 C 160 32, 190 32, 210 18"/><path d="M210 18 C 230 4, 260 4, 270 18 C 260 32, 230 32, 210 18"/><path d="M150 18 C 120 6, 90 10, 70 18 C 90 26, 120 30, 150 18"/><path d="M270 18 C 300 6, 330 10, 350 18 C 330 26, 300 30, 270 18"/></g><g fill="#c58a94"><circle cx="210" cy="18" r="4"/><circle cx="70" cy="18" r="3"/><circle cx="350" cy="18" r="3"/><circle cx="120" cy="9" r="2"/><circle cx="300" cy="9" r="2"/><circle cx="120" cy="27" r="2"/><circle cx="300" cy="27" r="2"/></g></svg>
<div class="ak-verse"><?php echo t('قال تعالى : ﴿وَيُطْعِمُونَ الطَّعَامَ عَلَى حُبِّهِ مِسْكِينًا وَيَتِيمًا وَأَسِيرًا﴾ ﴿الإنسان 8﴾'); ?></div>
<div class="ak-meta">
<span><?php echo t('بيانات اليتيم :'); ?> &nbsp; <?php echo t('رقم اليتيم'); ?> <span class="ak-code">#<?php echo $child ? (int)$child['id'] : '___'; ?></span></span>
<span class="ak-code"><?php echo t('رقم الاستمارة:'); ?> <?php echo $child ? e($child['form_serial']) : 'AK-_____'; ?></span>
</div>
<table class="ak-strip"><tr><th><?php echo t('الدولة'); ?></th><td><?php echo t('السودان'); ?></td><th><?php echo t('المشروع'); ?></th><td><?php echo t('كفالة الأيتام'); ?></td><th><?php echo t('رقم التقييم'); ?></th><td></td></tr></table>
<table class="ak-main">
<tr><th><?php echo t('الاسم'); ?></th><td colspan="3"><?php echo $child ? $V(trim($child['child_name'] . ' ' . ($child['father_name'] ?? ($fam['father_name'] ?? '')))) : '<span class="ak-blank" style="min-width:120mm"></span>'; ?></td><td class="ak-photo" rowspan="7"><?php if ($photoUrl): ?><img src="<?php echo e($photoUrl); ?>" alt=""><?php endif; ?></td></tr>
<tr><th><?php echo t('النوع'); ?></th><td colspan="3"><?php echo $child ? $V(['male' => 'ذكر', 'female' => 'أنثى'][$child['gender'] ?? ''] ?? '—') : '<span class="ak-blank"></span>'; ?></td></tr>
<tr><th><?php echo t('تاريخ الميلاد'); ?></th><td><?php echo $child ? $V($dmy($child['birth_date'] ?? '')) : '<span class="ak-blank"></span>'; ?></td><th><?php echo t('العمر'); ?></th><td><?php echo $child ? $V($age ?? '') : '<span class="ak-blank"></span>'; ?></td></tr>
<tr><th><?php echo t('الجنسية'); ?></th><td colspan="3"><?php echo $child ? $V($child['nationality'] ?? 'سودانية') : '<span class="ak-blank"></span>'; ?></td></tr>
<tr><th><?php echo t('الحالة الصحية'); ?></th><td colspan="3"><?php echo $child ? $V(t($healthShow)) : '<span class="ak-blank"></span>'; ?></td></tr>
<tr><th><?php echo t('الحالة النفسية'); ?></th><td colspan="3"><?php echo $child ? $V(t($psychShow)) : '<span class="ak-blank"></span>'; ?></td></tr>
<tr><th><?php echo t('العنوان'); ?></th><td colspan="3"><?php echo $child ? $V(trim(($fam['address'] ?? '') . ' ' . ($fam['district'] ?? ''))) : '<span class="ak-blank" style="min-width:120mm"></span>'; ?></td></tr>
</table>
<table class="ak-main">
<tr><th><?php echo t('تاريخ وفاة الأب'); ?></th><td><?php echo $child ? $V($dmy($fam['father_death_date'] ?? '')) : '<span class="ak-blank"></span>'; ?></td><th><?php echo t('اسم الأم'); ?></th><td><?php echo $child ? $V($fam['mother_name'] ?? '') : '<span class="ak-blank"></span>'; ?></td></tr>
<tr><th><?php echo t('سبب الوفاة'); ?></th><td><?php echo $child ? $V($fam['father_death_cause'] ?? '') : '<span class="ak-blank"></span>'; ?></td><th><?php echo t('الحالة الاجتماعية للأم'); ?></th><td><?php echo $child ? $V(t($fam['mother_marital_status'] ?? 'أرملة')) : '<span class="ak-blank"></span>'; ?></td></tr>
<tr><th><?php echo t('اسم المعيل الحالي'); ?></th><td><?php echo $child ? $V($child['guardian_name'] ?? '') : '<span class="ak-blank"></span>'; ?></td><th><?php echo t('تاريخ وفاة الأم'); ?></th><td><span class="ak-blank"></span></td></tr>
<tr><th><?php echo t('صلتها باليتيم'); ?></th><td><?php echo $child ? $V($child['guardian_relationship'] ?? '') : '<span class="ak-blank"></span>'; ?></td><th><?php echo t('الرقم الوطني'); ?></th><td><?php echo $child ? $V($child['national_id'] ?? '') : '<span class="ak-blank"></span>'; ?></td></tr>
</table>
<table class="ak-main" style="margin-top:5px">
<tr><th><?php echo t('مدة الكفالة'); ?></th><td><?php echo t('من :'); ?> <?php echo $child ? $V($dmy($firstStart ?? '')) : '<span class="ak-blank"></span>'; ?> &nbsp;&nbsp; <?php echo t('إلى :'); ?> <span class="ak-blank"></span></td></tr>
<tr><th><?php echo t('قيمة الكفالة'); ?></th><td><?php echo $child ? number_format((float)$totalMonthly, 0) : ''; ?> &nbsp; <?php echo t('قيمة التزامات أخرى'); ?> : <span class="ak-blank" style="min-width:60mm"></span></td></tr>
<?php foreach ($ships as $sh): ?>
<tr><th><?php echo t('الكفيل'); ?></th><td><?php echo $V($sh['sname'] ?? ''); ?> <span class="ak-code"><?php echo e($sh['sponsor_code'] ?? ''); ?></span></td><th><?php echo t('منذ'); ?></th><td><?php echo $V($dmy($sh['start_date'] ?? '')); ?></td></tr>
<?php endforeach; ?>
<tr class="ak-motto"><td colspan="4"><?php echo t('لا تحقرن قليلا من الخير تفعله .. فإن قليل الخير كثيره'); ?></td></tr>
</table>
<div class="ak-green"><?php echo t('اللهم أغفر لكافل اليتيم ووالدية وزده على ما بذله واجعله في ميزان حسناته ... آمين'); ?></div>
<div class="ak-ribbon-wrap"><div class="ak-ribbon-fold"></div><div class="ak-ribbon"><?php echo t('الإدارة العامة :'); ?><br><?php echo t('سمر إسحق عامر'); ?></div></div>
<svg class="ak-wave" viewBox="0 0 800 60" preserveAspectRatio="none"><path d="M0,60 C150,8 340,52 500,26 C650,2 760,32 800,14 L800,60 Z" fill="#2e9e44"/><path d="M0,60 C200,30 420,56 620,34 C720,24 780,42 800,36 L800,60 Z" fill="#9ed89e"/></svg>
</div>
<?php endif; ?>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>