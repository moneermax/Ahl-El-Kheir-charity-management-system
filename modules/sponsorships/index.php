<?php
// modules/sponsorships/index.php - Sponsorships list (Orphan-level pivot)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'general_manager', 'supervisor', 'accountant'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$pageTitle = t('sponsorships.title');
$active = 'sponsorships';

/* - Arabic normalizer for search - */
$norm = function (string $s): string {
    $s = preg_replace('/[\\x{064B}-\\x{0652}\\x{0640}\\x{200B}-\\x{200D}\\x{FEFF}]/u', '', $s);
    $s = str_replace(['أ','إ','آ','ٱ'], 'ا', $s);
    $s = str_replace(['ة'], 'ه', $s);
    $s = preg_replace('/\\s+/u', ' ', trim($s));
    return mb_strtolower($s, 'UTF-8');
};

/* - filters - */
$q = trim($_GET['q'] ?? '');
$fType = trim($_GET['stype'] ?? '');
if (!in_array($fType, ['all', 'sponsor', 'orphan', 'family', 'code'], true)) $fType = 'all';
$fStatus = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

/* - supervisor scope: direct assignment OR assigned letter + matching gender - */
$mySponsorIds = null;
if ($role === 'supervisor') {
    $uid = Session::getUserId();
    $sql = "SELECT DISTINCT s.id
            FROM sponsors s
            LEFT JOIN supervisor_letters sl ON sl.supervisor_id = ? AND sl.letter_id = s.first_letter_id
            LEFT JOIN letters l ON l.id = sl.letter_id
            WHERE s.supervisor_id = ?
               OR (sl.id IS NOT NULL AND l.gender = s.gender)";
    $mySponsorIds = array_map('intval', array_column(dbFetchAll($sql, [$uid, $uid]), 'id'));
}

/* - NEW ORPHAN-LEVEL QUERY - */
$all = dbFetchAll("SELECT sp.id, sp.sponsorship_code, sp.monthly_amount, sp.status, sp.start_date, sp.pause_reason,
    s.id AS sponsor_id, s.full_name AS sponsor_name, s.sponsor_code,
    fc.id AS child_id, fc.child_name,
    f.id AS family_id, f.mother_name, f.family_code
    FROM sponsorships sp
    JOIN sponsors s ON s.id = sp.sponsor_id
    JOIN family_children fc ON fc.id = sp.child_id
    JOIN families f ON f.id = fc.family_id
    ORDER BY sp.id DESC");

/* - apply filters - */
$filtered = [];
$nq = $norm($q);
foreach ($all as $row) {
    if ($mySponsorIds !== null && !in_array((int)$row['sponsor_id'], $mySponsorIds, true)) continue;
    if ($fStatus !== '' && $row['status'] !== $fStatus) continue;
    if ($nq !== '') {
        $sponsorN = $norm((string)$row['sponsor_name']);
        $orphanN  = $norm((string)$row['child_name']);
        $familyN  = $norm((string)$row['mother_name']);
        $codes    = mb_strtolower($row['sponsorship_code'] . ' ' . $row['sponsor_code'] . ' ' . $row['family_code'], 'UTF-8');
        
        $ok = false;
        switch ($fType) {
            case 'sponsor': $ok = str_starts_with($sponsorN, $nq); break;
            case 'orphan':  $ok = str_starts_with($orphanN, $nq); break;
            case 'family':  $ok = str_starts_with($familyN, $nq); break;
            case 'code':    $ok = mb_strpos($codes, $nq, 0, 'UTF-8') !== false; break;
            default: // all
                $ok = str_starts_with($sponsorN, $nq) 
                   || str_starts_with($orphanN, $nq)
                   || str_starts_with($familyN, $nq) 
                   || mb_strpos($codes, $nq, 0, 'UTF-8') !== false;
        }
        if (!$ok) continue;
    }
    $filtered[] = $row;
}

$total = count($filtered);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$rows = array_slice($filtered, ($page - 1) * $perPage, $perPage);

$activeCount = 0; $activeMonthly = 0;
foreach ($filtered as $r) if ($r['status'] === 'active') { $activeCount++; $activeMonthly += (float)$r['monthly_amount']; }

$qs = fn(array $extra) => APP_URL . 'modules/sponsorships/index.php?' . http_build_query(array_merge($_GET, $extra));

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2><?php echo e(t('sponsorships.title')); ?></h2>
    <p><?php echo e(t('families.count', ['count' => $total])); ?> · <?php echo e(t('families.status_active')); ?>: <?php echo $activeCount; ?> · <?php echo e(t('families.monthly_commitment')); ?>: <?php echo number_format($activeMonthly, 0); ?> <?php echo e(t('accounting.currency_sdg')); ?></p>
    <div class="quick-actions mt-3">
        <?php if (in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)): ?>
            <a href="<?php echo APP_URL; ?>modules/sponsorships/create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> <?php echo e(t('sponsorships.create')); ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3 fade-in">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><?php echo e(t('common.search')); ?> <small class="text-muted"><?php echo e(t('families.search_hint')); ?></small></label>
                <input type="text" name="q" class="form-control" value="<?php echo e($q); ?>" placeholder="<?php echo e(t('search.placeholder')); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo e(t('common.search')); ?> <?php echo e(t('search.all_types')); ?></label>
                <select name="stype" class="form-select">
                    <option value="all" <?php echo $fType === 'all' ? 'selected' : ''; ?>><?php echo e(t('common.all')); ?></option>
                    <option value="sponsor" <?php echo $fType === 'sponsor' ? 'selected' : ''; ?>><?php echo e(t('common.sponsors')); ?></option>
                    <option value="orphan" <?php echo $fType === 'orphan' ? 'selected' : ''; ?>><?php echo e(t('common.orphans')); ?></option>
                    <option value="family" <?php echo $fType === 'family' ? 'selected' : ''; ?>><?php echo e(t('common.families')); ?></option>
                    <option value="code" <?php echo $fType === 'code' ? 'selected' : ''; ?>><?php echo e(t('families.code')); ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo e(t('common.status')); ?></label>
                <select name="status" class="form-select">
                    <option value=""><?php echo e(t('common.all')); ?></option>
                    <option value="active" <?php echo $fStatus === 'active' ? 'selected' : ''; ?>><?php echo e(t('common.active')); ?></option>
                    <option value="paused" <?php echo $fStatus === 'paused' ? 'selected' : ''; ?>><?php echo e(t('common.paused')); ?></option>
                    <option value="completed" <?php echo $fStatus === 'completed' ? 'selected' : ''; ?>><?php echo e(t('common.completed')); ?></option>
                    <option value="cancelled" <?php echo $fStatus === 'cancelled' ? 'selected' : ''; ?>><?php echo e(t('common.cancelled')); ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> <?php echo e(t('common.search')); ?></button>
            </div>
            <div class="col-md-2">
                <a class="btn btn-secondary w-100" href="<?php echo APP_URL; ?>modules/sponsorships/index.php" title="<?php echo e(t('common.clear')); ?>"><i class="fas fa-rotate-right me-1"></i> <?php echo e(t('common.clear')); ?></a>
            </div>
        </form>
    </div>
</div>

<div class="card fade-in">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th><?php echo e(t('families.code')); ?></th>
                        <th><?php echo e(t('common.sponsors')); ?></th>
                        <th><?php echo e(t('common.orphans')); ?></th>
                        <th><?php echo e(t('common.families')); ?></th>
                        <th><?php echo e(t('families.monthly_commitment')); ?></th>
                        <th><?php echo e(t('accounting.date')); ?></th>
                        <th><?php echo e(t('common.status')); ?></th>
                        <th class="text-center"><?php echo e(t('common.view')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4"><?php echo e(t('common.no_data')); ?></td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo e($r['sponsorship_code']); ?></td>
                            <td><?php echo e($r['sponsor_name']); ?> <small class="text-muted">(<?php echo e($r['sponsor_code']); ?>)</small></td>
                            <td><strong><?php echo e($r['child_name']); ?></strong></td>
                            <td><?php echo e($r['mother_name']); ?> <small class="text-muted">(<?php echo e($r['family_code']); ?>)</small></td>
                            <td><?php echo number_format((float)$r['monthly_amount'], 0); ?></td>
                            <td><?php echo e($r['start_date']); ?></td>
                            <td><?php
                                $st = ['active' => 'common.active', 'paused' => 'common.paused', 'completed' => 'common.completed', 'cancelled' => 'common.cancelled'];
                                $stKey = $st[$r['status']] ?? null;
                                $stLabel = $stKey ? t($stKey) : (string)$r['status'];
                                $stClass = ['active' => 'bg-success', 'paused' => 'bg-warning', 'completed' => 'bg-info', 'cancelled' => 'bg-danger'][$r['status']] ?? 'bg-secondary';
                            ?>
                                <span class="badge <?php echo $stClass; ?>"><?php echo e($stLabel); ?></span>
                            </td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-primary" href="<?php echo APP_URL; ?>modules/sponsorships/view.php?id=<?php echo (int)$r['id']; ?>" title="<?php echo e(t('common.view')); ?>"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="mt-2" aria-label="<?php echo e(t('families.pages_label')); ?>">
                <ul class="pagination pagination-sm justify-content-center">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo e($qs(['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>