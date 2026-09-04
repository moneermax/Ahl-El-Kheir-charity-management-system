<?php
// modules/supervisors/sponsors.php - Sponsors of one supervisor (letter + GENDER aware)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$pageTitle = t('supervisors.sponsors_title');
$active = 'supervisors';
function ak_norm_gender($raw): string { $v = strtolower(trim((string)$raw)); if (in_array($v, ['female', 'f', 'أنثى', 'انثى'], true)) return 'female'; if (in_array($v, ['male', 'm', 'ذكر'], true)) return 'male'; return ''; }
$G_LABEL = ['male' => t('supervisors.male'), 'female' => t('supervisors.female'), '' => t('supervisors.gender_unknown')];
$id = (int)($_GET['supervisor'] ?? 0); if ($role === 'supervisor') { $id = Session::getUserId(); }
$sup = dbFetchOne("SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND r.code = 'supervisor'", [$id]);
if (!$sup) { flash('error', t('supervisors.sponsor_not_found')); redirect('modules/supervisors/index.php'); }
$letterRows = dbFetchAll("SELECT l.id, l.code, sl.gender FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id WHERE sl.supervisor_id = ? ORDER BY l.sort_order", [$id]);
$letterChips = [];
foreach ($letterRows as $r) { $g = ak_norm_gender($r['gender']); $suffix = ($g === 'male') ? t('supervisors.male_suffix') : (($g === 'female') ? t('supervisors.female_suffix') : ''); $letterChips[] = $r['code'] . $suffix; }
$letterNormById = [];
foreach (dbFetchAll("SELECT id, code FROM letters") as $L) $letterNormById[(int)$L['id']] = normalize_arabic_letter($L['code']);
$ownByNorm = [];
foreach (dbFetchAll("SELECT sl.supervisor_id, sl.gender, l.code FROM supervisor_letters sl JOIN letters l ON l.id = sl.letter_id") as $mr) { $n = normalize_arabic_letter($mr['code']); $g = ak_norm_gender($mr['gender']); if ($g === '') { $ownByNorm[$n]['male'] = (int)$mr['supervisor_id']; $ownByNorm[$n]['female'] = (int)$mr['supervisor_id']; } else { $ownByNorm[$n][$g] = (int)$mr['supervisor_id']; } }
$all = dbFetchAll("SELECT s.id, s.sponsor_code, s.full_name, s.first_letter_raw, s.first_letter_id, s.supervisor_id, s.phone, s.sponsor_type, s.status, s.gender, l.code AS sponsor_letter, (SELECT COUNT(*) FROM sponsorships sp WHERE sp.sponsor_id = s.id AND sp.status = 'active') AS active_sponsorships FROM sponsors s LEFT JOIN letters l ON l.id = s.first_letter_id ORDER BY s.full_name");
$sponsors = [];
foreach ($all as $row) { $sg = ak_norm_gender($row['gender'] ?? ''); $norm = ''; if ($row['first_letter_id'] !== null) $norm = $letterNormById[(int)$row['first_letter_id']] ?? ''; if ($norm === '') [, $norm] = first_letter_of((string)($row['first_letter_raw'] ?? '')); if ($norm === '') [, $norm] = first_letter_of((string)$row['full_name']); $genders = ($sg !== '') ? [$sg] : ['male', 'female']; $owners = []; if ($norm !== '') { foreach ($genders as $g) { $o = $ownByNorm[$norm][$g] ?? null; if ($o) $owners[] = $o; } } if ($owners) { if (in_array($id, $owners, true)) $sponsors[] = $row; continue; } if ($id > 0 && (int)($row['supervisor_id'] ?? 0) === $id) { $row['ak_direct'] = 1; $sponsors[] = $row; } }
$total = count($sponsors); $activeCount = count(array_filter($sponsors, fn($r) => $r['status'] === 'active')); $commit = ['total' => 0];
if ($total) { $ids = array_map(fn($r) => (int)$r['id'], $sponsors); $ph = implode(',', array_fill(0, count($ids), '?')); $commit = dbFetchOne("SELECT COALESCE(SUM(monthly_amount), 0) AS total FROM sponsorships WHERE status = 'active' AND sponsor_id IN ($ph)", $ids); }
include dirname(__DIR__, 2) . '/includes/header.php'; ?>
<div class="welcome-section fade-in">
    <h2><?php echo e(t('supervisors.sponsors_title')); ?>: <?php echo e($sup['full_name']); ?></h2>
    <p><?php echo e(t('supervisors.letters_gender')); ?> <?php if ($letterChips): foreach ($letterChips as $L): ?><span class="badge bg-light text-dark border"><?php echo e($L); ?></span> <?php endforeach; else: echo e(t('supervisors.no_letters')); endif; ?></p>
    <div class="quick-actions mt-3">
        <a href="<?php echo APP_URL; ?>modules/supervisors/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-right me-1"></i> <?php echo e(t('supervisors.back_to_supervisors')); ?></a>
        <a href="<?php echo APP_URL; ?>modules/supervisors/edit.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-pen me-1"></i> <?php echo e(t('supervisors.edit_supervisor')); ?></a>
    </div>
</div>
<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>
<div class="row g-4 mb-4 fade-in">
    <div class="col-md-4"><div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold" style="color:#1b4d8f"><?php echo $total; ?></div><div class="text-muted small"><?php echo e(t('supervisors.total_sponsors')); ?></div></div></div></div>
    <div class="col-md-4"><div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold" style="color:#1b4d8f"><?php echo $activeCount; ?></div><div class="text-muted small"><?php echo e(t('supervisors.active_sponsors')); ?></div></div></div></div>
    <div class="col-md-4"><div class="card text-center"><div class="card-body"><div class="fs-3 fw-bold" style="color:#1b4d8f"><?php echo number_format((float)($commit['total'] ?? 0), 0); ?></div><div class="text-muted small"><?php echo e(t('supervisors.monthly_commitment_active')); ?></div></div></div></div>
</div>
<div class="card fade-in"><div class="card-header"><i class="fas fa-hand-holding-heart me-2"></i><?php echo e(t('supervisors.sponsors_list', ['count' => $total])); ?></div><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr>
<th><?php echo e(t('supervisors.code')); ?></th><th><?php echo e(t('supervisors.name')); ?></th><th><?php echo e(t('supervisors.letter')); ?></th><th><?php echo e(t('supervisors.gender')); ?></th><th><?php echo e(t('supervisors.phone')); ?></th><th><?php echo e(t('supervisors.type')); ?></th><th><?php echo e(t('supervisors.active_sponsorships')); ?></th><th><?php echo e(t('supervisors.status')); ?></th><th></th>
</tr></thead><tbody>
<?php if (!$sponsors): ?><tr><td colspan="9" class="text-center text-muted py-4"><?php echo e(t('supervisors.no_sponsors')); ?></td></tr>
<?php else: foreach ($sponsors as $r): ?><tr>
<td><?php echo e($r['sponsor_code']); ?></td><td><strong><?php echo e($r['full_name']); ?></strong><?php if (!empty($r['ak_direct'])): ?> <span class="badge bg-info" title="<?php echo e(t('supervisors.direct_title')); ?>"><?php echo e(t('supervisors.direct')); ?></span><?php endif; ?></td>
<td><span class="badge bg-light text-dark border"><?php echo e($r['sponsor_letter'] ?? '-'); ?></span></td><td><?php echo e($G_LABEL[ak_norm_gender($r['gender'] ?? '')]); ?></td><td><?php echo e($r['phone'] ?? '-'); ?></td><td><?php echo e($r['sponsor_type']); ?></td><td><?php echo (int)$r['active_sponsorships']; ?></td>
<td><?php echo $r['status'] === 'active' ? '<span class="badge bg-success">' . e(t('supervisors.active')) . '</span>' : '<span class="badge bg-secondary">' . e(t('supervisors.suspended')) . '</span>'; ?></td>
<td><a class="btn btn-sm btn-primary" title="<?php echo e(t('supervisors.view')); ?>" href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo (int)$r['id']; ?>"><i class="fas fa-eye"></i></a></td>
</tr><?php endforeach; endif; ?></tbody></table></div></div></div>
<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
