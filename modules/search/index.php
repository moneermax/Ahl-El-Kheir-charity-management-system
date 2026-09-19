<?php
/**
 * modules/search/index.php
 *
 * Global search across the core of the system: families & orphans, sponsors,
 * sponsorships, and monthly disbursements (payments). The shared header opens
 * this route as a dedicated Search Center; results remain rendered here so
 * query state, filters, permissions, and pagination stay in one workspace.
 *
 * When q is empty, shows all results filtered by type and status/month.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$uid  = Session::getUserId();
$role = Session::getUserRole();

$FULL_ACCESS   = ['admin', 'general_manager', 'vice_general_manager', 'financial_manager', 'accountant'];
$NO_ACCESS_ALL = ['hr_manager', 'hr_staff'];
$NO_PAYMENTS   = ['administration', 'social_media'];

$q      = trim((string)($_GET['q'] ?? ''));
$type   = $_GET['type'] ?? 'all';
$status = trim((string)($_GET['status'] ?? ''));
$month  = trim((string)($_GET['month'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$allowedTypes = ['all', 'families', 'sponsors', 'sponsorships', 'payments'];
if (!in_array($type, $allowedTypes, true)) { $type = 'all'; }

$pageTitle = 'نتائج البحث';
$active = 'search';

/* ==========================================================
   Supervisor letter+gender matrix
   ========================================================== */
$supVisibleSponsorIds = [];
$supVisibleFamilyIds  = [];
if ($role === 'supervisor') {
    $letterNormById = [];
    foreach (dbFetchAll("SELECT id, code FROM letters") as $L) {
        $letterNormById[(int)$L['id']] = normalize_arabic_letter($L['code']);
    }
    $ownByNorm = [];
    foreach (dbFetchAll("SELECT sl.supervisor_id, sl.gender, l.code FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id") as $mr) {
        $n = normalize_arabic_letter($mr['code']);
        $g = ak_norm_gender_search($mr['gender']);
        if ($g === '') {
            $ownByNorm[$n]['male'] = (int)$mr['supervisor_id'];
            $ownByNorm[$n]['female'] = (int)$mr['supervisor_id'];
        } else {
            $ownByNorm[$n][$g] = (int)$mr['supervisor_id'];
        }
    }
    $allSponsorsForMatrix = dbFetchAll("SELECT id, full_name, first_letter_raw, first_letter_id, supervisor_id, gender FROM sponsors");
    foreach ($allSponsorsForMatrix as $row) {
        $sg = ak_norm_gender_search($row['gender'] ?? '');
        $norm = '';
        if ($row['first_letter_id'] !== null) { $norm = $letterNormById[(int)$row['first_letter_id']] ?? ''; }
        if ($norm === '') { [, $norm] = first_letter_of((string)($row['first_letter_raw'] ?? '')); }
        if ($norm === '') { [, $norm] = first_letter_of((string)$row['full_name']); }

        $genders = ($sg !== '') ? [$sg] : ['male', 'female'];
        $owners = [];
        if ($norm !== '') {
            foreach ($genders as $g) {
                $o = $ownByNorm[$norm][$g] ?? null;
                if ($o) { $owners[] = $o; }
            }
        }
        if ($owners) {
            if (in_array($uid, $owners, true)) { $supVisibleSponsorIds[] = (int)$row['id']; }
            continue;
        }
        if ((int)($row['supervisor_id'] ?? 0) === $uid) { $supVisibleSponsorIds[] = (int)$row['id']; }
    }

    $directFamilies = dbFetchAll("SELECT id FROM families WHERE supervisor_id = ?", [$uid]);
    foreach ($directFamilies as $r) { $supVisibleFamilyIds[] = (int)$r['id']; }
    if ($supVisibleSponsorIds) {
        $ph = implode(',', array_fill(0, count($supVisibleSponsorIds), '?'));
        $linked = dbFetchAll(
            "SELECT DISTINCT fc.family_id FROM sponsorships sp
             JOIN family_children fc ON fc.id = sp.child_id
             WHERE sp.sponsor_id IN ($ph)",
            $supVisibleSponsorIds
        );
        foreach ($linked as $r) { $supVisibleFamilyIds[] = (int)$r['family_id']; }
    }
    $supVisibleFamilyIds = array_values(array_unique($supVisibleFamilyIds));
}

function ak_norm_gender_search($raw): string {
    $v = strtolower(trim((string)$raw));
    if (in_array($v, ['female', 'f', 'أنثى', 'انثى'], true)) return 'female';
    if (in_array($v, ['male', 'm', 'ذكر'], true)) return 'male';
    return '';
}

function ids_in_clause(array $ids, string $col): array {
    if (empty($ids)) { return ["$col = -1", []]; }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    return ["$col IN ($ph)", $ids];
}

$accountantAssignedNannyIds = [];
if ($role === 'accountant_staff') {
    $accountantAssignedNannyIds = array_map('intval', array_column(
        dbFetchAll("SELECT nanny_id FROM accountant_nanny_assignments WHERE accountant_id = ?", [$uid]),
        'nanny_id'
    ));
}

$hasNoAccess = in_array($role, $NO_ACCESS_ALL, true);
$hasNoPaymentsAccess = in_array($role, $NO_PAYMENTS, true) || $hasNoAccess;

$allFamilies = [];
$allSponsors = [];
$allSponsorships = [];
$allPayments = [];
$searched = true;

$FAMILY_STATUS_LABELS = ['pending' => 'قيد الانتظار', 'active' => 'نشطة', 'paused' => 'موقوفة', 'completed' => 'مكتملة', 'archived' => 'مؤرشفة', 'inactive' => 'غير نشطة', 'closed' => 'مغلقة'];
$SPONSOR_STATUS_LABELS = ['active' => 'نشط', 'inactive' => 'غير نشط', 'suspended' => 'موقوف', 'cancelled' => 'ملغى'];
$SPONSORSHIP_STATUS_LABELS = ['active' => 'نشطة', 'paused' => 'موقوفة', 'completed' => 'مكتملة', 'cancelled' => 'ملغاة'];
$PAYMENT_STATUS_LABELS = [
    'draft' => ['مسودة', 'secondary'], 'pending_approval' => ['بانتظار الاعتماد', 'warning'],
    'approved' => ['معتمدة', 'info'], 'transferred' => ['محوّلة', 'primary'],
    'received' => ['مستلمة', 'success'], 'returned' => ['مغلقة مع إرجاع', 'danger'],
    'cancelled' => ['ملغاة', 'secondary'], 'voided' => ['ملغاة (فسخ)', 'dark'],
];

if (!$hasNoAccess) {
    $like = '%' . $q . '%';

    /* ---------------- Families & orphans ---------------- */
    if (in_array($type, ['all', 'families'], true)) {
        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = "(f.family_code LIKE ? OR f.mother_name LIKE ? OR f.mother_phone LIKE ? OR f.father_name LIKE ? OR EXISTS (SELECT 1 FROM family_children fc WHERE fc.family_id = f.id AND fc.child_name LIKE ?))";
            $params = [$like, $like, $like, $like, $like];
        } else {
            $where[] = '1=1';
        }

        if ($role === 'nanny') { $where[] = 'f.nanny_id = ?'; $params[] = $uid; }
        elseif ($role === 'accountant_staff') {
            [$c, $p] = ids_in_clause($accountantAssignedNannyIds, 'f.nanny_id'); $where[] = $c; array_push($params, ...$p);
        } elseif ($role === 'supervisor') {
            [$c, $p] = ids_in_clause($supVisibleFamilyIds, 'f.id'); $where[] = $c; array_push($params, ...$p);
        } elseif (!in_array($role, $FULL_ACCESS, true) && !in_array($role, $NO_PAYMENTS, true)) {
            $where[] = '1=0';
        }
        if ($status !== '' && $type === 'families') { $where[] = 'f.status = ?'; $params[] = $status; }

        /*
         * IMPORTANT DOMAIN RULE:
         * family_children is authoritative for the number of children/orphans.
         * families.children_count is only a cache and must never drive UI counts.
         */
        $sql = "SELECT f.id, f.family_code, f.mother_name, f.mother_phone, f.status,
                (SELECT COUNT(*) FROM family_children fc_count WHERE fc_count.family_id = f.id) AS actual_children_count,
                COALESCE(u.full_name, '—') AS nanny_name
                FROM families f LEFT JOIN users u ON u.id = f.nanny_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY f.updated_at DESC";
        $allFamilies = dbFetchAll($sql, $params);
    }

    /* ---------------- Sponsors ---------------- */
    if (in_array($type, ['all', 'sponsors'], true)) {
        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = "(s.sponsor_code LIKE ? OR s.full_name LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)";
            $params = [$like, $like, $like, $like];
        } else {
            $where[] = '1=1';
        }

        if ($role === 'supervisor') {
            [$c, $p] = ids_in_clause($supVisibleSponsorIds, 's.id'); $where[] = $c; array_push($params, ...$p);
        }
        if ($status !== '' && $type === 'sponsors') { $where[] = 's.status = ?'; $params[] = $status; }

        $sql = "SELECT s.id, s.sponsor_code, s.full_name, s.phone, s.status, s.sponsor_type,
                (SELECT COUNT(*) FROM sponsorships sp WHERE sp.sponsor_id = s.id AND sp.status = 'active') AS active_count
                FROM sponsors s
                WHERE " . implode(' AND ', $where) . "
                ORDER BY s.updated_at DESC";
        $allSponsors = dbFetchAll($sql, $params);
    }

    /* ---------------- Sponsorships ---------------- */
    if (in_array($type, ['all', 'sponsorships'], true)) {
        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = "(sp.sponsorship_code LIKE ? OR s.full_name LIKE ? OR s.sponsor_code LIKE ? OR fc.child_name LIKE ? OR f.family_code LIKE ? OR f.mother_name LIKE ?)";
            $params = [$like, $like, $like, $like, $like, $like];
        } else {
            $where[] = '1=1';
        }

        if ($role === 'nanny') { $where[] = 'f.nanny_id = ?'; $params[] = $uid; }
        elseif ($role === 'accountant_staff') {
            [$c, $p] = ids_in_clause($accountantAssignedNannyIds, 'f.nanny_id'); $where[] = $c; array_push($params, ...$p);
        } elseif ($role === 'supervisor') {
            [$c, $p] = ids_in_clause($supVisibleSponsorIds, 'sp.sponsor_id'); $where[] = $c; array_push($params, ...$p);
        } elseif (!in_array($role, $FULL_ACCESS, true) && !in_array($role, $NO_PAYMENTS, true)) {
            $where[] = '1=0';
        }
        if ($status !== '' && $type === 'sponsorships') { $where[] = 'sp.status = ?'; $params[] = $status; }

        $sql = "SELECT sp.id, sp.sponsorship_code, sp.monthly_amount, sp.status, sp.start_date,
                s.full_name AS sponsor_name, s.sponsor_code,
                fc.child_name, f.family_code, f.mother_name
                FROM sponsorships sp
                JOIN sponsors s ON s.id = sp.sponsor_id
                LEFT JOIN family_children fc ON fc.id = sp.child_id
                LEFT JOIN families f ON f.id = fc.family_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY sp.updated_at DESC";
        $allSponsorships = dbFetchAll($sql, $params);
    }

    /* ---------------- Monthly payments (disbursements) ---------------- */
    if (in_array($type, ['all', 'payments'], true) && !$hasNoPaymentsAccess) {
        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = "(d.month LIKE ? OR u.full_name LIKE ? OR og.group_name LIKE ? OR EXISTS (SELECT 1 FROM disbursement_items di JOIN families f2 ON f2.id = di.family_id WHERE di.disbursement_id = d.id AND (f2.family_code LIKE ? OR f2.mother_name LIKE ?)))";
            $params = [$like, $like, $like, $like, $like];
        } else {
            $where[] = '1=1';
        }

        if ($role === 'nanny') { $where[] = 'd.nanny_id = ?'; $params[] = $uid; }
        elseif ($role === 'accountant_staff') {
            [$c, $p] = ids_in_clause($accountantAssignedNannyIds, 'd.nanny_id'); $where[] = $c; array_push($params, ...$p);
        } elseif ($role === 'supervisor') {
            [$c, $p] = ids_in_clause($supVisibleFamilyIds, 'f2s.family_id');
            $where[] = "EXISTS (SELECT 1 FROM disbursement_items f2s WHERE f2s.disbursement_id = d.id AND $c)";
            array_push($params, ...$p);
        } elseif (!in_array($role, $FULL_ACCESS, true)) {
            $where[] = '1=0';
        }
        if ($status !== '' && $type === 'payments') { $where[] = 'd.status = ?'; $params[] = $status; }
        if ($month !== '' && $type === 'payments') { $where[] = 'd.month = ?'; $params[] = $month; }

        $sql = "SELECT d.id, d.month, d.status, d.total_amount, COALESCE(u.full_name, '—') AS nanny_name, og.group_name
                FROM monthly_disbursements d
                LEFT JOIN users u ON u.id = d.nanny_id
                LEFT JOIN orphan_groups og ON og.id = d.group_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY d.created_at DESC";
        $allPayments = dbFetchAll($sql, $params);
    }
}

function paginateArray($items, $page, $perPage) {
    $total = count($items);
    $offset = ($page - 1) * $perPage;
    $paginated = array_slice($items, $offset, $perPage);
    return [
        'data' => $paginated,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($total / $perPage)
    ];
}

if ($type === 'all') {
    $familiesData = paginateArray($allFamilies, $page, $perPage);
    $sponsorsData = paginateArray($allSponsors, $page, $perPage);
    $sponsorshipsData = paginateArray($allSponsorships, $page, $perPage);
    $paymentsData = paginateArray($allPayments, $page, $perPage);
} else {
    if ($type === 'families') {
        $familiesData = paginateArray($allFamilies, $page, $perPage);
        $sponsorsData = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0];
        $sponsorshipsData = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0];
        $paymentsData = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0];
    } elseif ($type === 'sponsors') {
        $familiesData = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0];
        $sponsorsData = paginateArray($allSponsors, $page, $perPage);
        $sponsorshipsData = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0];
        $paymentsData = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0];
    } elseif ($type === 'sponsorships') {
        $familiesData = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0];
        $sponsorsData = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0];
        $sponsorshipsData = paginateArray($allSponsorships, $page, $perPage);
        $paymentsData = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0];
    } else {
        $familiesData = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0];
        $sponsorsData = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0];
        $sponsorshipsData = ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0];
        $paymentsData = paginateArray($allPayments, $page, $perPage);
    }
}

$families = $familiesData['data'];
$sponsors = $sponsorsData['data'];
$sponsorships = $sponsorshipsData['data'];
$payments = $paymentsData['data'];

$resultCounts = [
    'families' => $familiesData['total'],
    'sponsors' => $sponsorsData['total'],
    'sponsorships' => $sponsorshipsData['total'],
    'payments' => $paymentsData['total'],
];
$totalResults = array_sum($resultCounts);

$currentTotalPages = 1;
if ($type === 'families') $currentTotalPages = $familiesData['totalPages'];
elseif ($type === 'sponsors') $currentTotalPages = $sponsorsData['totalPages'];
elseif ($type === 'sponsorships') $currentTotalPages = $sponsorshipsData['totalPages'];
elseif ($type === 'payments') $currentTotalPages = $paymentsData['totalPages'];
else {
    $currentTotalPages = max($familiesData['totalPages'], $sponsorsData['totalPages'], $sponsorshipsData['totalPages'], $paymentsData['totalPages']);
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.table-container { position: relative; max-height: 600px; overflow-y: auto; }
.table-container table thead th { position: sticky; top: 0; z-index: 10; background: #f8f9fa; border-bottom: 2px solid #dee2e6; }
body.theme-dark .table-container table thead th { background: #2d3748; border-bottom-color: #4a5568; color: #e2e8f0; }
.pagination-wrapper { display: flex; justify-content: center; align-items: center; gap: 8px; padding: 16px 0; flex-wrap: wrap; }
.pagination-wrapper .page-item.active .page-link { background-color: var(--navy, #1b4d8f); border-color: var(--navy, #1b4d8f); color: #fff; }
.pagination-wrapper .page-link { color: var(--navy, #1b4d8f); border-radius: 4px; }
.pagination-wrapper .page-link:hover { background-color: #e9ecef; }
body.theme-dark .pagination-wrapper .page-link { background: #2d3748; color: #e2e8f0; border-color: #4a5568; }
body.theme-dark .pagination-wrapper .page-link:hover { background: #4a5568; }
body.theme-dark .pagination-wrapper .page-item.active .page-link { background-color: var(--navy, #1b4d8f); border-color: var(--navy, #1b4d8f); }
.pagination-info { text-align: center; color: #6c757d; font-size: 0.9rem; margin-top: 8px; }
body.theme-dark .pagination-info { color: #a0aec0; }
.nav-tabs .nav-link { color: var(--navy, #1b4d8f); }
.nav-tabs .nav-link.active { background-color: var(--navy, #1b4d8f); color: #fff; border-color: var(--navy, #1b4d8f); }
body.theme-dark .nav-tabs .nav-link { color: #e2e8f0; }
body.theme-dark .nav-tabs .nav-link.active { background-color: var(--navy, #1b4d8f); color: #fff; }
.filter-bar { background: #f8f9fa; border-radius: 8px; padding: 12px 16px; }
body.theme-dark .filter-bar { background: #2d3748; }
</style>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-search me-2"></i>نتائج البحث</h2>
    <p class="text-muted">
        <?php if ($q !== ''): ?>
            عن: "<strong><?php echo e($q); ?></strong>" —
        <?php endif; ?>
        <?php echo $totalResults; ?> نتيجة
        <?php if ($type !== 'all'): ?>
            في <?php
                $typeNames = ['families' => 'الأسر', 'sponsors' => 'الكفلاء', 'sponsorships' => 'الكفالات', 'payments' => 'الدفعات الشهرية'];
                echo e($typeNames[$type] ?? $type);
            ?>
        <?php endif; ?>
        <?php if ($currentTotalPages > 1): ?>
            <span class="badge bg-secondary ms-2">الصفحة <?php echo $page; ?> من <?php echo $currentTotalPages; ?></span>
        <?php endif; ?>
    </p>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<?php if ($hasNoAccess): ?>
    <div class="alert alert-info">هذا البحث مخصص لبيانات الأسر والكفالات، وهي خارج نطاق صلاحيات دورك الحالي.</div>
<?php elseif ($totalResults === 0): ?>
    <div class="alert alert-light border text-center py-5 text-muted">
        <i class="fas fa-search fa-2x mb-3 d-block"></i>
        <?php if ($q !== ''): ?>
            لا توجد نتائج مطابقة لبحثك "<strong><?php echo e($q); ?></strong>".
        <?php else: ?>
            لا توجد بيانات متاحة في هذا القسم.
        <?php endif; ?>
    </div>
<?php else: ?>

    <ul class="nav nav-tabs mb-3">
        <?php
        $tabs = ['all' => 'الكل', 'families' => 'الأسر والأيتام (' . $resultCounts['families'] . ')',
                 'sponsors' => 'الكفلاء (' . $resultCounts['sponsors'] . ')',
                 'sponsorships' => 'الكفالات (' . $resultCounts['sponsorships'] . ')'];
        if (!$hasNoPaymentsAccess) { $tabs['payments'] = 'الدفعات الشهرية (' . $resultCounts['payments'] . ')'; }
        foreach ($tabs as $tv => $tl):
            $qs = http_build_query(['type' => $tv, 'q' => $q, 'status' => $status, 'month' => $month, 'page' => 1]);
        ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $type === $tv ? 'active' : ''; ?>" href="?<?php echo e($qs); ?>"><?php echo e($tl); ?></a>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($type !== 'all'): ?>
    <div class="filter-bar mb-4">
        <form method="get" class="row g-2 align-items-end" id="filterForm">
            <input type="hidden" name="type" value="<?php echo e($type); ?>">
            <input type="hidden" name="q" value="<?php echo e($q); ?>">
            <input type="hidden" name="page" value="1">
            <?php
            $statusOptions = [];
            if ($type === 'families') { $statusOptions = $FAMILY_STATUS_LABELS; }
            if ($type === 'sponsors') { $statusOptions = $SPONSOR_STATUS_LABELS; }
            if ($type === 'sponsorships') { $statusOptions = $SPONSORSHIP_STATUS_LABELS; }
            if ($type === 'payments') { foreach ($PAYMENT_STATUS_LABELS as $k => $v) { $statusOptions[$k] = $v[0]; } }
            ?>
            <?php if ($statusOptions): ?>
            <div class="col-auto">
                <label class="form-label small mb-1">الحالة</label>
                <select name="status" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit();">
                    <option value="">الكل</option>
                    <?php foreach ($statusOptions as $sv => $sl): ?>
                    <option value="<?php echo e($sv); ?>" <?php echo $status === $sv ? 'selected' : ''; ?>><?php echo e($sl); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if ($type === 'payments'): ?>
            <div class="col-auto">
                <label class="form-label small mb-1">الشهر</label>
                <input type="month" name="month" class="form-control form-control-sm" value="<?php echo e($month); ?>" onchange="document.getElementById('filterForm').submit();">
            </div>
            <?php endif; ?>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i> تطبيق الفلتر</button>
                <a href="?type=<?php echo e($type); ?>&q=<?php echo e($q); ?>&page=1" class="btn btn-sm btn-outline-secondary"><i class="fas fa-undo me-1"></i> إعادة ضبط</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Families: orphan count comes from family_children, never families.children_count. -->
    <?php if (in_array($type, ['all', 'families'], true) && !empty($families)): ?>
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-house-chimney me-2"></i>الأسر والأيتام (<?php echo $familiesData['total']; ?>)</span>
            <?php if ($type === 'all' && $resultCounts['families'] > 0): ?>
                <a href="?type=families&q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>&month=<?php echo urlencode($month); ?>&page=1" class="small">عرض الكل</a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-container">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>الكود</th><th>اسم الأم</th><th>الهاتف</th><th>عدد الأطفال</th><th>الأخصائية</th><th>الحالة</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($families as $f): ?>
                    <tr>
                        <td><?php echo e($f['family_code'] ?? '—'); ?></td>
                        <td><?php echo e($f['mother_name']); ?></td>
                        <td><?php echo e($f['mother_phone'] ?? '—'); ?></td>
                        <td><?php echo (int)($f['actual_children_count'] ?? 0); ?></td>
                        <td><?php echo e($f['nanny_name']); ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo e($FAMILY_STATUS_LABELS[$f['status']] ?? $f['status']); ?></span></td>
                        <td><a href="<?php echo APP_URL; ?>modules/families/view.php?id=<?php echo (int)$f['id']; ?>&return=<?php echo rawurlencode(http_build_query($_GET)); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($familiesData['totalPages'] > 1): ?>
                <?php echo renderPagination($familiesData['totalPages'], $page, 'families', $q, $status, $month); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sponsors -->
    <?php if (in_array($type, ['all', 'sponsors'], true) && !empty($sponsors)): ?>
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-hand-holding-heart me-2"></i>الكفلاء (<?php echo $sponsorsData['total']; ?>)</span>
            <?php if ($type === 'all' && $resultCounts['sponsors'] > 0): ?>
                <a href="?type=sponsors&q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>&month=<?php echo urlencode($month); ?>&page=1" class="small">عرض الكل</a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0"><div class="table-container"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>الكود</th><th>الاسم</th><th>الهاتف</th><th>النوع</th><th>كفالات نشطة</th><th>الحالة</th><th></th></tr></thead>
            <tbody><?php foreach ($sponsors as $s): ?><tr>
                <td><?php echo e($s['sponsor_code'] ?? '—'); ?></td><td><?php echo e($s['full_name']); ?></td><td><?php echo e($s['phone'] ?? '—'); ?></td><td><?php echo e($s['sponsor_type']); ?></td><td><?php echo (int)$s['active_count']; ?></td>
                <td><span class="badge bg-light text-dark border"><?php echo e($SPONSOR_STATUS_LABELS[$s['status']] ?? $s['status']); ?></span></td>
                <td><a href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo (int)$s['id']; ?>&return=<?php echo rawurlencode(http_build_query($_GET)); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
        <?php if ($sponsorsData['totalPages'] > 1): ?><?php echo renderPagination($sponsorsData['totalPages'], $page, 'sponsors', $q, $status, $month); ?><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sponsorships -->
    <?php if (in_array($type, ['all', 'sponsorships'], true) && !empty($sponsorships)): ?>
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-file-contract me-2"></i>الكفالات (<?php echo $sponsorshipsData['total']; ?>)</span>
            <?php if ($type === 'all' && $resultCounts['sponsorships'] > 0): ?><a href="?type=sponsorships&q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>&month=<?php echo urlencode($month); ?>&page=1" class="small">عرض الكل</a><?php endif; ?>
        </div>
        <div class="card-body p-0"><div class="table-container"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>الكود</th><th>الكفيل</th><th>الطفل</th><th>الأسرة</th><th>المبلغ الشهري</th><th>الحالة</th><th></th></tr></thead>
            <tbody><?php foreach ($sponsorships as $sp): ?><tr>
                <td><?php echo e($sp['sponsorship_code'] ?? '—'); ?></td><td><?php echo e($sp['sponsor_name']); ?></td><td><?php echo e($sp['child_name'] ?? '—'); ?></td><td><?php echo e($sp['family_code'] ?? '—'); ?></td><td><?php echo number_format((float)$sp['monthly_amount'], 0); ?> ج.س</td>
                <td><span class="badge bg-light text-dark border"><?php echo e($SPONSORSHIP_STATUS_LABELS[$sp['status']] ?? $sp['status']); ?></span></td>
                <td><a href="<?php echo APP_URL; ?>modules/sponsorships/view.php?id=<?php echo (int)$sp['id']; ?>&return=<?php echo rawurlencode(http_build_query($_GET)); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
        <?php if ($sponsorshipsData['totalPages'] > 1): ?><?php echo renderPagination($sponsorshipsData['totalPages'], $page, 'sponsorships', $q, $status, $month); ?><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Payments -->
    <?php if (in_array($type, ['all', 'payments'], true) && !$hasNoPaymentsAccess && !empty($payments)): ?>
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-money-check-dollar me-2"></i>الدفعات الشهرية (<?php echo $paymentsData['total']; ?>)</span>
            <?php if ($type === 'all' && $resultCounts['payments'] > 0): ?><a href="?type=payments&q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>&month=<?php echo urlencode($month); ?>&page=1" class="small">عرض الكل</a><?php endif; ?>
        </div>
        <div class="card-body p-0"><div class="table-container"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>الشهر</th><th>المجموعة</th><th>الأخصائية</th><th>المبلغ الإجمالي</th><th>الحالة</th><th></th></tr></thead>
            <tbody><?php foreach ($payments as $p): [$pl, $pc] = $PAYMENT_STATUS_LABELS[$p['status']] ?? [$p['status'], 'secondary']; ?><tr>
                <td><?php echo e($p['month']); ?></td><td><?php echo e($p['group_name'] ?? '—'); ?></td><td><?php echo e($p['nanny_name']); ?></td><td><?php echo number_format((float)$p['total_amount'], 0); ?> ج.س</td>
                <td><span class="badge bg-<?php echo $pc; ?>"><?php echo e($pl); ?></span></td>
                <td><a href="<?php echo APP_URL; ?>modules/accounting/disbursements.php?view=<?php echo (int)$p['id']; ?>&return=<?php echo rawurlencode(http_build_query($_GET)); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
        <?php if ($paymentsData['totalPages'] > 1): ?><?php echo renderPagination($paymentsData['totalPages'], $page, 'payments', $q, $status, $month); ?><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>

<?php
function renderPagination($totalPages, $currentPage, $type, $q, $status, $month) {
    if ($totalPages <= 1) return '';
    $html = '<div class="pagination-wrapper"><nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0">';
    if ($currentPage > 1) {
        $prev = $currentPage - 1;
        $html .= '<li class="page-item"><a class="page-link" href="?type=' . urlencode($type) . '&q=' . urlencode($q) . '&status=' . urlencode($status) . '&month=' . urlencode($month) . '&page=' . $prev . '">&laquo;</a></li>';
    } else { $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>'; }
    $start = max(1, $currentPage - 2); $end = min($totalPages, $currentPage + 2);
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?type=' . urlencode($type) . '&q=' . urlencode($q) . '&status=' . urlencode($status) . '&month=' . urlencode($month) . '&page=1">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        else $html .= '<li class="page-item"><a class="page-link" href="?type=' . urlencode($type) . '&q=' . urlencode($q) . '&status=' . urlencode($status) . '&month=' . urlencode($month) . '&page=' . $i . '">' . $i . '</a></li>';
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="?type=' . urlencode($type) . '&q=' . urlencode($q) . '&status=' . urlencode($status) . '&month=' . urlencode($month) . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    if ($currentPage < $totalPages) {
        $next = $currentPage + 1;
        $html .= '<li class="page-item"><a class="page-link" href="?type=' . urlencode($type) . '&q=' . urlencode($q) . '&status=' . urlencode($status) . '&month=' . urlencode($month) . '&page=' . $next . '">&raquo;</a></li>';
    } else { $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>'; }
    $html .= '</ul></nav></div>';
    $startResult = ($currentPage - 1) * 20 + 1;
    $endResult = min($currentPage * 20, $totalPages);
    $html .= '<div class="pagination-info">عرض ' . $startResult . ' - ' . $endResult . ' من ' . $totalPages . ' نتيجة</div>';
    return $html;
}
?>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>