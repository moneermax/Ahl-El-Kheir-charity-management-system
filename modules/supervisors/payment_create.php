<?php
// modules/supervisors/payment_create.php - Supervisor logs raw payment & receipt (Directive 4)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();
if (!Session::isLoggedIn()) { header('Location: ' . APP_URL . 'index.php'); exit(); }
$role = Session::getUserRole();
if (!in_array($role, ['admin', 'vice_general_manager', 'supervisor'], true)) {
    header('Location: ' . APP_URL . 'index.php'); exit();
}

$pageTitle = t('supervisors.payment_title');
$active = 'supervisors';

$sp_id = (int)($_GET['sponsorship_id'] ?? $_POST['sponsorship_id'] ?? 0);
$sp = dbFetchOne("SELECT sp.*, s.full_name AS sponsor_name, s.sponsor_code, s.id AS sponsor_id, fc.child_name, f.family_code
    FROM sponsorships sp
    JOIN sponsors s ON s.id = sp.sponsor_id
    JOIN family_children fc ON fc.id = sp.child_id
    JOIN families f ON f.id = fc.family_id
    WHERE sp.id = ?", [$sp_id]);

if (!$sp) { flash('error', t('supervisors.payment_missing')); redirect('modules/sponsorships/index.php'); }

// Supervisor ownership check
$uid = Session::getUserId();
if ($role === 'supervisor') {
    $myLetterIds = array_map('intval', array_column(dbFetchAll("SELECT letter_id FROM supervisor_letters WHERE supervisor_id = ?", [$uid]), 'letter_id'));
    $own = dbFetchOne("SELECT id FROM sponsors WHERE id = ? AND (supervisor_id = ?" .
        ($myLetterIds ? " OR first_letter_id IN (" . implode(',', $myLetterIds) . ")" : '') . ")",
        array_merge([(int)$sp['sponsor_id'], $uid]));
    if (!$own) { flash('error', t('supervisors.payment_out_of_scope')); redirect('modules/sponsorships/index.php'); }
}

$errors = [];
$input = [
    'payment_period' => $_POST['payment_period'] ?? date('F/Y'),
    'amount' => $_POST['amount'] ?? $sp['monthly_amount'],
    'notes' => $_POST['notes'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $input['payment_period'] = trim($_POST['payment_period'] ?? '');
    $input['amount'] = (float)($_POST['amount'] ?? 0);
    $input['notes'] = trim($_POST['notes'] ?? '');

    if ($input['payment_period'] === '') $errors[] = t('supervisors.payment_period_required');
    if ($input['amount'] <= 0) $errors[] = t('supervisors.payment_amount_positive');

    $receiptPath = null;
    if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['receipt_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
            if ($file['size'] <= 10 * 1024 * 1024) {
                $dir = dirname(__DIR__, 2) . '/storage/receipts';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $fileName = 'SUP-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                $dest = $dir . '/' . $fileName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $receiptPath = 'storage/receipts/' . $fileName;
                } else {
                    $errors[] = t('supervisors.receipt_save_failed');
                }
            } else { $errors[] = t('supervisors.receipt_too_large'); }
        } else { $errors[] = t('supervisors.receipt_invalid_format'); }
    } elseif (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = t('supervisors.receipt_upload_error');
    }

    if (!$errors) {
        dbExecute(
            "INSERT INTO sponsor_payments (sponsorship_id, supervisor_id, payment_period, amount, currency_code, receipt_file_path, notes)
             VALUES (?, ?, ?, ?, 'SDG', ?, ?)",
            [$sp_id, $uid, $input['payment_period'], $input['amount'], $receiptPath, $input['notes'] !== '' ? $input['notes'] : null]
        );
        $newId = dbLastInsertId();

        dbExecute(
            "INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, 'CREATE', 'sponsor_payments', ?, NULL, ?, ?, ?)",
            [$uid, $newId, json_encode(['period' => $input['payment_period'], 'amount' => $input['amount']], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
        );

        flash('success', t('supervisors.payment_saved'));
        redirect('modules/sponsors/view.php?id=' . $sp['sponsor_id']);
    }
}

$prev_payments = dbFetchAll("SELECT sp.*, u.full_name AS supervisor_name
    FROM sponsor_payments sp
    LEFT JOIN users u ON u.id = sp.supervisor_id
    WHERE sp.sponsorship_id = ?
    ORDER BY sp.created_at DESC LIMIT 10", [$sp_id]);

include dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="welcome-section fade-in">
    <h2><i class="fas fa-receipt me-2"></i><?= e(t('supervisors.payment_title')) ?></h2>
    <p><?= e(t('supervisors.sponsorship_context', ['sponsor' => $sp['sponsor_name'], 'orphan' => $sp['child_name']])) ?></p>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger fade-in">
        <ul class="mb-0"><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card fade-in">
            <div class="card-header text-white" style="background:#1b4d8f"><i class="fas fa-plus-circle me-2"></i><?= e(t('supervisors.new_payment')) ?></div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="sponsorship_id" value="<?php echo $sp_id; ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= e(t('supervisors.payment_period')) ?></label>
                            <input type="text" name="payment_period" class="form-control" placeholder="<?= e(t('supervisors.payment_period_placeholder')) ?>" required value="<?php echo e($input['payment_period']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= e(t('supervisors.amount_collected')) ?></label>
                            <input type="number" step="500" min="1" name="amount" class="form-control" required value="<?php echo e($input['amount']); ?>">
                            <div class="form-text"><?= e(t('supervisors.monthly_commitment', ['amount' => number_format((float)$sp['monthly_amount'], 0)])) ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= e(t('supervisors.receipt_file')) ?></label>
                            <input type="file" name="receipt_file" class="form-control" accept="image/jpeg,image/png,application/pdf" required>
                            <div class="form-text"><?= e(t('supervisors.receipt_formats')) ?></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= e(t('supervisors.notes')) ?></label>
                            <textarea name="notes" class="form-control" rows="2"><?php echo e($input['notes']); ?></textarea>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-primary"><i class="fas fa-save me-1"></i> <?= e(t('supervisors.save_payment')) ?></button>
                        <a href="<?php echo APP_URL; ?>modules/sponsors/view.php?id=<?php echo $sp['sponsor_id']; ?>" class="btn btn-secondary"><?= e(t('supervisors.cancel')) ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card fade-in">
            <div class="card-header"><i class="fas fa-history me-2"></i><?= e(t('supervisors.recent_payments')) ?></div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px;overflow:auto">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th><?= e(t('supervisors.period')) ?></th>
                                <th><?= e(t('supervisors.amount')) ?></th>
                                <th><?= e(t('supervisors.receipt')) ?></th>
                                <th><?= e(t('supervisors.date')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$prev_payments): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3"><?= e(t('supervisors.no_previous_payments')) ?></td></tr>
                            <?php else: foreach ($prev_payments as $p): ?>
                                <tr>
                                    <td><?php echo e($p['payment_period']); ?></td>
                                    <td><?php echo number_format((float)$p['amount'], 0); ?></td>
                                    <td>
                                        <?php if ($p['receipt_file_path']): ?>
                                            <a href="<?php echo APP_URL . e($p['receipt_file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> <?= e(t('supervisors.view')) ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?php echo date('Y-m-d', strtotime($p['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>