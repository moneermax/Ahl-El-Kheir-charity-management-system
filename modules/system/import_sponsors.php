<?php
// modules/system/import_sponsors.php - Excel/CSV sponsor+family import (Admin + VGM)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();

// ---- Access guard ----
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
if (!in_array(Session::getUserRole(), ['admin', 'vice_general_manager'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$pageTitle = 'استيراد الكفلاء';
$active    = 'system';

/* ================= helpers (guarded against double-pasting) ================= */

if (!function_exists('imp_normalize_name')) {
    function imp_normalize_name(string $s): string {
        $s = preg_replace('/[\x{064B}-\x{0652}\x{0640}\x{200B}-\x{200D}\x{FEFF}]/u', '', $s);
        $s = str_replace(['أ','إ','آ','ٱ'], 'ا', $s);
        $s = str_replace(['ة'], 'ه', $s);
        $s = preg_replace('/\([^)]*\)/u', ' ', $s);
        $s = preg_replace('/من ناس\s*كندا|كندا|كند\b/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', trim($s));
        return $s;
    }
}

if (!function_exists('imp_parse_birth')) {
    function imp_parse_birth(string $v): ?string {
        $v = trim($v);
        if ($v === '') return null;
        if (preg_match('#^(\d{1,2})[\\/\\\\-](\d{1,2})[\\/\\\\-](\d{4})$#', $v, $m))
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        if (preg_match('#^\d{4}$#', $v)) return $v . '-01-01';
        if (is_numeric($v) && (int)$v > 20000 && (int)$v < 60000)
            return gmdate('Y-m-d', ((int)$v - 25569) * 86400);
        return null;
    }
}

if (!function_exists('imp_parse_sponsor_cell')) {
    function imp_parse_sponsor_cell(string $cell): array {
        $entries = [];
        foreach (preg_split('/\\\\+/', $cell) as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $cancelled = false;
            if (mb_strpos($p, 'الغاء') !== false) { $cancelled = true; $p = str_replace('الغاء', ' ', $p); }
            $amount = null;
            if (preg_match('/([0-9][0-9,]{2,})\s*$/u', $p, $m)) {
                $amount = (int)str_replace(',', '', $m[1]);
                $p = substr($p, 0, -strlen($m[0]));
            }
            $name = imp_normalize_name($p);
            if ($name === '') continue;
            $entries[] = ['name' => $name, 'raw' => trim($p), 'amount' => $amount, 'cancelled' => $cancelled];
        }
        return $entries;
    }
}

if (!function_exists('imp_read_csv_sheet')) {
    function imp_read_csv_sheet(string $path): array {
        $h = fopen($path, 'rb');
        if (!$h) return [null, []];
        $bom = fread($h, 3);
        if (substr((string)$bom, 0, 3) !== "\xEF\xBB\xBF") fseek($h, 0);
        $header = null; $rows = [];
        while (($data = fgetcsv($h, 0, ',', '"')) !== false) {
            $line = implode(' ', array_map(fn($c) => (string)$c, $data));
            if ($header === null) {
                if (mb_strpos($line, 'الكفيل') !== false && mb_strpos($line, 'امهات') !== false) $header = $data;
                continue;
            }
            $rows[] = $data;
        }
        fclose($h);
        return [$header, $rows];
    }
}

if (!function_exists('imp_read_xlsx_sheet')) {
    function imp_read_xlsx_sheet(string $path, string $sheetName): array {
        if (!class_exists('ZipArchive', false)) return [null, []];
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return [null, []];
        $wbXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!$wbXml || !$relsXml) { $zip->close(); return [null, []]; }
        
        $wb   = simplexml_load_string($wbXml);
        $rels = simplexml_load_string($relsXml);
        $target = null;
        foreach ($wb->sheets->sheet as $sh) {
            if ((string)$sh['name'] !== $sheetName) continue;
            $rid = (string)$sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            foreach ($rels->Relationship as $rel)
                if ((string)$rel['Id'] === $rid) { $target = (string)$rel['Target']; break 2; }
        }
        if (!$target) { $zip->close(); return [null, []]; }
        if (strpos($target, 'xl/') !== 0) $target = 'xl/' . ltrim($target, '/');
        $sheetXml = $zip->getFromName($target);
        if (!$sheetXml) { $zip->close(); return [null, []]; }
        $sheet = simplexml_load_string($sheetXml);

        $shared = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            foreach (simplexml_load_string($ssXml)->si as $si) {
                $shared[] = isset($si->r)
                    ? implode('', array_map(fn($r) => (string)$r->t, $si->r))
                    : (string)$si->t;
            }
        }
        $colSort = fn($a, $b) => [strlen($a), $a] <=> [strlen($b), $b];
        $header = null; $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = preg_replace('/\d/', '', (string)$c['r']);
                $t = (string)$c['t'];
                $v = isset($c->v) ? (string)$c->v : '';
                if ($t === 's') $v = $shared[(int)$v] ?? '';
                elseif ($t === 'inlineStr') $v = (string)$c->is->t;
                $cells[$col] = $v;
            }
            uksort($cells, $colSort);
            $lineArr = array_values($cells);
            $line = implode(' ', $lineArr);
            if ($header === null) {
                if (mb_strpos($line, 'الكفيل') !== false && mb_strpos($line, 'امهات') !== false) $header = $lineArr;
                continue;
            }
            $rows[] = $lineArr;
        }
        $zip->close();
        return [$header, $rows];
    }
}

if (!function_exists('imp_col_index')) {
    function imp_col_index(?array $header, array $needles): ?int {
        if (!$header) return null;
        foreach ($header as $i => $h) {
            $h = trim((string)$h);
            foreach ($needles as $n) if ($h !== '' && mb_strpos($h, $n) !== false) return $i;
        }
        return null;
    }
}

if (!function_exists('imp_match_family')) {
    function imp_match_family(string $needle, array $famNorm): array {
        if ($needle === '') return ['none', null];
        $cands = [];
        foreach ($famNorm as $id => $name) {
            if ($name === $needle) return ['exact', $id];
            if (mb_strlen($needle) >= 6 && (strpos($name, $needle) === 0 || strpos($needle, $name) === 0)) $cands[$id] = 2;
        }
        if (!$cands) {
            $a = explode(' ', $needle);
            if (isset($a[0], $a[1])) foreach ($famNorm as $id => $name) {
                $b = explode(' ', $name);
                if (isset($b[0], $b[1]) && $a[0] === $b[0] && $a[1] === $b[1]) $cands[$id] = 1;
            }
        }
        if ($cands) {
            arsort($cands);
            $top = current($cands);
            $ids = array_keys($cands, $top, true);
            return count($ids) === 1 ? ['fuzzy', $ids[0]] : ['ambiguous', $ids];
        }
        return ['none', null];
    }
}

/* ================= file discovery ================= */

$importsDir = dirname(__DIR__, 2) . '/storage/imports';
if (!is_dir($importsDir)) @mkdir($importsDir, 0777, true);
$files = array_merge(glob($importsDir . '/*.csv') ?: [], glob($importsDir . '/*.xlsx') ?: []);

$selected = basename((string)($_GET['file'] ?? $_POST['file'] ?? ''));
if ($selected && !in_array($importsDir . '/' . $selected, $files, true)) $selected = '';
if (!$selected && $files) {
    foreach ($files as $f) if (substr($f, -4) === '.csv') { $selected = basename($f); break; }
    if (!$selected) $selected = basename($files[0]);
}
$sheetName      = trim($_POST['sheet_name'] ?? $_GET['sheet_name'] ?? 'قاعده لمياء المحدثه');
$commit         = ($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_POST['confirm_import']);
$dryRun         = ($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_POST['dry_run']);
$planFamilies   = isset($_POST['create_families']);

dbExecute("CREATE TABLE IF NOT EXISTS sponsor_import_exceptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50) DEFAULT NULL,
    sheet_row INT DEFAULT NULL,
    mother_name VARCHAR(255) DEFAULT NULL,
    sponsor_entry VARCHAR(500) DEFAULT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* ================= reference data ================= */

$letters   = dbFetchAll("SELECT id, code FROM letters");
$letterMap = []; foreach ($letters as $L) $letterMap[normalize_arabic_letter($L['code'])] = (int)$L['id'];
$supMap    = [];
foreach (dbFetchAll("SELECT sl.supervisor_id, l.code FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id") as $r)
    $supMap[normalize_arabic_letter($r['code'])] = (int)$r['supervisor_id'];

$famNorm = [];
foreach (dbFetchAll("SELECT id, mother_name FROM families") as $f)
    $famNorm[(int)$f['id']] = imp_normalize_name($f['mother_name']);

$spByName = [];
foreach (dbFetchAll("SELECT id, full_name FROM sponsors") as $s)
    $spByName[imp_normalize_name($s['full_name'])] = (int)$s['id'];

$pairExists = [];
foreach (dbFetchAll("SELECT sponsor_id, family_id FROM sponsorships") as $p)
    $pairExists[(int)$p['sponsor_id'] . '_' . (int)$p['family_id']] = true;

$childByKey = [];
foreach (dbFetchAll("SELECT id, family_id, child_name, father_name FROM family_children") as $c)
    $childByKey[(int)$c['family_id'] . '|' . imp_normalize_name($c['child_name']) . '|' . imp_normalize_name((string)$c['father_name'])] = (int)$c['id'];

$defaultAmount = (int)(dbFetchOne("SELECT setting_value FROM settings WHERE setting_key = 'default_sponsorship_amount'")['setting_value'] ?? 9000);
$spCounter  = (int)(dbFetchOne("SELECT COUNT(*) c FROM sponsors")['c'] ?? 0);
$shCounter  = (int)(dbFetchOne("SELECT COUNT(*) c FROM sponsorships")['c'] ?? 0);
$famCounter = (int)(dbFetchOne("SELECT COUNT(*) c FROM families")['c'] ?? 0);

/* ================= the run ================= */

$report = null;

if (($dryRun || $commit) && $selected) {
    $path = $importsDir . '/' . $selected;
    if (substr($selected, -4) === '.csv') {
        [$header, $rows] = imp_read_csv_sheet($path);
    } elseif (class_exists('ZipArchive', false)) {
        [$header, $rows] = imp_read_xlsx_sheet($path, $sheetName);
    } else {
        [$header, $rows] = [null, []];
        flash('error', 'ملف Excel محدد لكن امتداد zip غير مفعل في PHP. فعّل extension=zip أو احفظ الورقة كـ CSV UTF-8.');
    }

    if ($header !== null) {
        $ci = [
            'rowno'   => imp_col_index($header, ['Column1', 'Column']),
            'child'   => imp_col_index($header, ['اسم اليتيم']),
            'father'  => imp_col_index($header, ['اسم الاب']),
            'birth'   => imp_col_index($header, ['عمر اليتيم']),
            'mother'  => imp_col_index($header, ['امهات']),
            'sponsor' => imp_col_index($header, ['الكفيل']),
            'amount'  => imp_col_index($header, ['الدعم الوارد', 'قيمة الكفالة/']),
            'status'  => imp_col_index($header, ['حالة المستفيد']),
            'notes'   => imp_col_index($header, ['ملاحظات']),
        ];
        $g = fn(array $row, ?int $i) => ($i !== null && isset($row[$i])) ? trim((string)$row[$i]) : '';

        $R = [
            'rows' => 0, 'fam_exact' => 0, 'fam_fuzzy' => 0, 'fam_missing' => 0, 'fam_ambiguous' => 0,
            'fam_created' => 0, 'sponsors_new' => 0, 'sponsors_existing' => 0, 'ships_new' => 0, 'ships_dup' => 0,
            'child_linked' => 0, 'child_created' => 0, 'exceptions' => [], 'preview' => [],
        ];
        $batch = date('Ymd-His');

        foreach ($rows as $idx => $row) {
            $rowNo  = $g($row, $ci['rowno']) !== '' ? (int)$g($row, $ci['rowno']) : $idx + 2;
            $mother = $g($row, $ci['mother']);
            $child  = $g($row, $ci['child']);
            $father = $g($row, $ci['father']);
            $birth  = imp_parse_birth($g($row, $ci['birth']));
            $cell   = $g($row, $ci['sponsor']);
            $rowAmt = (int)preg_replace('/[^0-9]/', '', $g($row, $ci['amount'])) ?: null;

            if ($mother === '' && $child === '') continue;
            if (mb_strpos($mother, 'القانوني') !== false) continue;
            $R['rows']++;

            [$mStatus, $mId] = imp_match_family(imp_normalize_name($mother), $famNorm);
            $familyIsNew = false;

            if ($mStatus === 'exact') $R['fam_exact']++;
            elseif ($mStatus === 'fuzzy') $R['fam_fuzzy']++;
            elseif ($mStatus === 'ambiguous') {
                $R['fam_ambiguous']++;
                $R['exceptions'][] = [$rowNo, $mother, $cell, 'اسم الأم يطابق أكثر من أسرة — مطلوب مراجعة يدوية'];
                $mId = null;
            } else { 
                if ($planFamilies && $mother !== '') {
                    $familyIsNew = true;
                    $R['fam_created']++;
                    if ($commit) {
                        $famCounter++;
                        $famCode = 'IMP2-FAM-' . str_pad((string)$famCounter, 6, '0', STR_PAD_LEFT);
                        [, $famLetter] = first_letter_of($mother);
                        dbExecute(
                            "INSERT INTO families (mother_name, status, family_code, legacy_mother_first_letter, notes, created_by, registration_number)
                             VALUES (?, 'pending', ?, ?, ?, ?, ?)",
                            [$mother, $famCode, $famLetter,
                             'Imported from sheet "' . $sheetName . '" row ' . $rowNo,
                             Session::getUserId(), 'IMP2-' . $rowNo]
                        );
                        $fRow = dbFetchOne("SELECT LAST_INSERT_ID() id");
                        $mId = (int)$fRow['id'];
                        $famNorm[$mId] = imp_normalize_name($mother); 
                    } else {
                        $mId = 0; 
                    }
                } else {
                    $R['fam_missing']++;
                    $R['exceptions'][] = [$rowNo, $mother, $cell, 'الأم غير موجودة في جدول الأسر'];
                    $mId = null;
                }
            }

            if ($cell === '') {
                if ($rowAmt) $R['exceptions'][] = [$rowNo, $mother, '', 'دعم وارد بمبلغ ' . $rowAmt . ' بدون اسم كفيل'];
                continue;
            }

            foreach (imp_parse_sponsor_cell($cell) as $e) {
                if (isset($spByName[$e['name']])) {
                    $sponsorId = $spByName[$e['name']];
                    $R['sponsors_existing']++;
                } else {
                    $sponsorId = null;
                    $R['sponsors_new']++;
                }
                [, $normLetter] = first_letter_of($e['name']);
                $letterId = $letterMap[$normLetter] ?? null;
                $supId    = $supMap[$normLetter] ?? null;

                if ($mId === null) continue; 

                if ($sponsorId !== null && isset($pairExists[$sponsorId . '_' . $mId])) {
                    $R['ships_dup']++;
                    continue;
                }
                                $R['ships_new']++;
                $amount = $e['amount'] ?? $rowAmt ?? null;
                if ($amount === null || (float)$amount <= 0) {
                    $amount = $defaultAmount > 0 ? $defaultAmount : 9000;
                }

                $R['preview'][] = [
                    'row' => $rowNo, 'sponsor' => $e['name'], 'letter' => $normLetter,
                    'supervisor' => $supId ?? '—',
                    'family' => $familyIsNew ? 'أسرة جديدة' : '#' . $mId,
                    'amount' => $amount, 'cancelled' => $e['cancelled'],
                ];

                if ($commit) {
                    if ($sponsorId === null) {
                        $spCounter++;
                        $code = 'IMP-SP-' . str_pad((string)$spCounter, 6, '0', STR_PAD_LEFT);
                        dbExecute(
                            "INSERT INTO sponsors (full_name, first_letter_raw, first_letter_id, sponsor_type, status, notes, created_by, sponsor_code, supervisor_id, assigned_by, assigned_at)
                             VALUES (?, ?, ?, 'individual', 'active', ?, ?, ?, ?, ?, NOW())",
                            [$e['name'], mb_substr($e['name'], 0, 1, 'UTF-8'), $letterId,
                             'Imported from sheet "' . $sheetName . '" row ' . $rowNo,
                             Session::getUserId(), $code, $supId, $supId ? Session::getUserId() : null]
                        );
                        $newRow = dbFetchOne("SELECT LAST_INSERT_ID() id");
                        $sponsorId = (int)$newRow['id'];
                        $spByName[$e['name']] = $sponsorId;
                    }
                    $shCounter++;
                    $shCode = 'IMP-SH-' . str_pad((string)$shCounter, 6, '0', STR_PAD_LEFT);
                    dbExecute(
                        "INSERT INTO sponsorships (sponsor_id, family_id, monthly_amount, currency_code, start_date, status, notes, created_by, sponsorship_code)
                         VALUES (?, ?, ?, 'SDG', '2025-01-01', ?, ?, ?, ?)",
                        [$sponsorId, $mId, $amount,
                         $e['cancelled'] ? 'cancelled' : 'active',
                         'Imported row ' . $rowNo, Session::getUserId(), $shCode]
                    );
                    $shipRow = dbFetchOne("SELECT LAST_INSERT_ID() id");
                    $shipId = (int)$shipRow['id'];
                    $pairExists[$sponsorId . '_' . $mId] = true;

                    if ($child !== '') {
                        $ck = $mId . '|' . imp_normalize_name($child) . '|' . imp_normalize_name($father);
                        if (isset($childByKey[$ck])) {
                            $childId = $childByKey[$ck];
                            $R['child_linked']++;
                        } else {
                            dbExecute(
                                "INSERT INTO family_children (family_id, child_name, father_name, birth_date, gender, is_active, legacy_status)
                                 VALUES (?, ?, ?, ?, 'unknown', 1, 'imported')",
                                [$mId, $child, $father !== '' ? $father : null, $birth]
                            );
                            $cRow = dbFetchOne("SELECT LAST_INSERT_ID() id");
                            $childId = (int)$cRow['id'];
                            $childByKey[$ck] = $childId;
                            $R['child_created']++;
                        }
                        dbExecute("INSERT IGNORE INTO sponsorship_children (sponsorship_id, family_child_id) VALUES (?, ?)", [$shipId, $childId]);
                    }
                }
            }
        }

        if ($commit) {
            foreach ($R['exceptions'] as $ex) {
                dbExecute(
                    "INSERT INTO sponsor_import_exceptions (batch_id, sheet_row, mother_name, sponsor_entry, reason) VALUES (?, ?, ?, ?, ?)",
                    [$batch, $ex[0], $ex[1], $ex[2], $ex[3]]
                );
            }
            dbExecute(
                "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES (?, 'IMPORT', 'sponsors', NULL, NULL, ?, ?, ?)",
                [Session::getUserId(),
                 json_encode(['file' => $selected, 'sheet' => $sheetName, 'batch' => $batch, 'create_families' => $planFamilies,
                              'stats' => ['rows' => $R['rows'], 'families_created' => $R['fam_created'], 'sponsors_new' => $R['sponsors_new'],
                                          'sponsorships' => $R['ships_new'], 'exceptions' => count($R['exceptions'])]], JSON_UNESCAPED_UNICODE),
                 $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
            );
            flash('success', 'اكتمل الاستيراد: ' . $R['fam_created'] . ' أسرة، ' . $R['sponsors_new'] . ' كفيل، ' . $R['ships_new'] . ' كفالة، ' . count($R['exceptions']) . ' استثناء.');
        }
        $report = $R;
    } else {
        if (empty($_SESSION['flash'])) {
            flash('error', 'تعذر العثور على صف العناوين (الكفيل / امهات) في الملف المحدد.');
        }
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
    .imp-stat{border-right:4px solid #1b4d8f;border-radius:12px}
    .imp-stat .v{font-size:1.6rem;font-weight:800;color:#1b4d8f}
</style>

<div class="welcome-section fade-in">
    <h2>استيراد الكفلاء من Excel</h2>
    <p>يقرأ الورقة محلياً من storage/imports — جرّب أولاً (Dry run) ثم أكّد التنفيذ</p>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="card mb-4 fade-in">
    <div class="card-body">
        <form method="post" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">الملف</label>
                <select name="file" class="form-select">
                    <?php if (!$files): ?><option value="">— لا توجد ملفات في storage/imports —</option><?php endif; ?>
                    <?php foreach ($files as $f): ?>
                        <option value="<?php echo e(basename($f)); ?>" <?php echo basename($f) === $selected ? 'selected' : ''; ?>>
                            <?php echo e(basename($f)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">اسم الورقة (لملفات xlsx)</label>
                <input type="text" name="sheet_name" class="form-control" value="<?php echo e($sheetName); ?>">
            </div>
            <div class="col-md-5">
                <div class="form-check">
                    <input type="checkbox" name="create_families" value="1" id="cf" class="form-check-input" checked>
                    <label class="form-check-label" for="cf">إنشاء الأسر غير الموجودة تلقائياً (بحالة pending للمراجعة)</label>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" name="dry_run" value="1" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> معاينة بدون تنفيذ
                </button>
                <?php if ($report): ?>
                    <button type="submit" name="confirm_import" value="1" class="btn btn-success"
                            onclick="return confirm('تنفيذ الاستيراد نهائياً؟');">
                        <i class="fas fa-check me-1"></i> تأكيد التنفيذ
                    </button>
                <?php endif; ?>
            </div>
            <?php if ($selected): ?><input type="hidden" name="file" value="<?php echo e($selected); ?>"><?php endif; ?>
        </form>
        <?php if (!$files): ?>
            <div class="alert alert-warning mt-3 mb-0">
                انسخ ملف <code>.xlsx</code> إلى <code>D:\xampp\htdocs\AhlElKheir\storage\imports\</code>
                أو احفظ الورقة من Excel بصيغة <strong>CSV UTF-8</strong> بنفس المجلد ثم أعد تحميل الصفحة.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($report): ?>
<div class="row g-3 mb-4 fade-in">
    <?php foreach ([
        ['rows', 'صفوف مقروءة'], ['fam_exact', 'مطابقة تامة'], ['fam_fuzzy', 'مطابقة تقريبية'],
        ['fam_created', 'أسر ستُنشأ/أُنشئت'], ['fam_missing', 'أسر غير موجودة'], ['fam_ambiguous', 'أسر ملتبسة'],
        ['sponsors_new', 'كفلاء جدد'], ['sponsors_existing', 'كفلاء موجودون'],
        ['ships_new', 'كفالات ستُنشأ/أُنشئت'], ['ships_dup', 'مكررة (تُتخطى)'],
        ['child_linked', 'أطفال مرتبطون'], ['child_created', 'أطفال أُنشئوا'],
    ] as $k): ?>
        <div class="col-6 col-md-2">
            <div class="card imp-stat text-center"><div class="card-body py-2">
                <div class="v"><?php echo (int)$report[$k[0]]; ?></div>
                <div class="text-muted small"><?php echo $k[1]; ?></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-4 fade-in">
    <div class="card-header"><i class="fas fa-list me-2"></i>عينة من الكفالات المزمع إنشاؤها (أول 30)</div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead><tr><th>صف</th><th>الكفيل</th><th>الحرف</th><th>المشرف</th><th>الأسرة</th><th>المبلغ</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($report['preview'], 0, 30) as $p): ?>
                <tr>
                    <td><?php echo (int)$p['row']; ?></td>
                    <td><?php echo e($p['sponsor']); ?></td>
                    <td><span class="badge bg-light text-dark border"><?php echo e($p['letter']); ?></span></td>
                    <td><?php echo $p['supervisor'] === '—' ? '—' : (int)$p['supervisor']; ?></td>
                    <td><?php echo e($p['family']); ?></td>
                    <td><?php echo number_format((float)$p['amount'], 0); ?></td>
                    <td><?php echo $p['cancelled'] ? '<span class="badge bg-danger">ملغي</span>' : '<span class="badge bg-success">نشط</span>'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card fade-in">
    <div class="card-header"><i class="fas fa-exclamation-triangle me-2"></i>الاستثناءات (<?php echo count($report['exceptions']); ?>)</div>
    <div class="card-body table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>صف</th><th>الأم</th><th>الكفيل</th><th>السبب</th></tr></thead>
            <tbody>
            <?php if (!$report['exceptions']): ?>
                <tr><td colspan="4" class="text-center text-muted">لا استثناءات 🎉</td></tr>
            <?php else: foreach ($report['exceptions'] as $ex): ?>
                <tr><td><?php echo (int)$ex[0]; ?></td><td><?php echo e($ex[1]); ?></td><td><?php echo e($ex[2]); ?></td><td><?php echo e($ex[3]); ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>